const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const { repoPath } = require('./test-paths.cjs')
const script = fs.readFileSync(repoPath('assets/editor-chat.js'), 'utf8')
// Reuse the established mock DOM without executing its independent test suite.
const fixture = fs.readFileSync(repoPath('tests/js/editor_chat_agent_queue_runtime_phase1.js'), 'utf8').split('(async () => {')[0]
const exportsContext = { require, console, module: { exports: {} }, setTimeout }
vm.runInNewContext(fixture + '\nmodule.exports = { createShell, MockElement, buildResponse };', exportsContext)
const { createShell, MockElement, buildResponse } = exportsContext.module.exports
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve() }

async function main() {
  let status = 'running'; let failPoll = false; let loseDelivery = false; let failStop = true; let stopCalls = 0; let rejectDelivery = false
  const timers = []; const deliveries = []; const refreshes = []
  const config = { postId: 42, targetId: 42, threadId: 'thread-a', restNonce: 'fixture',
    threads: { 'thread-a': { id: 'thread-a', messages: [] }, 'thread-b': { id: 'thread-b', messages: [] } },
    agent: { enabled: true, client: 'codex' }, agentRequestEndpoint: '/agent/request', agentStopEndpoint: '/agent/request/cancel', agentPendingEndpoint: '/agent/request/pending',
    labels: { idleState: 'Ready', analyzing: 'Sending', analyzeSuggestion: 'Send' } }
  const nodes = createShell(config)
  const stop = new MockElement('button'); stop.hidden = true
  nodes.shell._queryMap['[data-lcfa-editor-stop]'] = stop; nodes.shell.appendChild(stop)
  const fetch = async (url, options = {}) => {
    if (url === config.agentPendingEndpoint) return buildResponse({ requests: [{ id: 'request-active', thread_id: 'thread-a', post_id: 42, status: 'running' }] })
    if (url === config.agentStopEndpoint) {
      stopCalls++
      if (failStop) throw new Error('Fixture offline')
      status = 'stop_requested'; return buildResponse({ request: { id: 'request-active', thread_id: 'thread-a', status } })
    }
    if (String(url).includes('?request_id=')) {
      if (failPoll) throw new Error('Fixture poll failed')
      return buildResponse({ request: { id: 'request-active', thread_id: 'thread-a', status } })
    }
    if (url === config.agentRequestEndpoint && options.method === 'POST') {
      deliveries.push(JSON.parse(options.body))
      if (rejectDelivery) return buildResponse({ error: 'Desktop binding unavailable', delivery_state: 'not_queued' }, false)
      if (loseDelivery) throw new Error('Fixture response lost')
      return buildResponse({ request: { id: 'request-delivery', thread_id: 'thread-a', status: 'cancelled' } })
    }
    throw new Error('Unexpected fixture URL: ' + url)
  }
  const context = { console, URL, fetch, FileReader: class {},
    setTimeout: fn => { timers.push(fn); return timers.length }, clearTimeout() {},
    document: { querySelector: selector => selector === '[data-lcfa-editor-shell]' ? nodes.shell : null, addEventListener() {}, createElement: tag => new MockElement(tag) },
    window: { location: { origin: 'http://fixture.invalid' }, loadURLintoEditor: url => refreshes.push(url), lc_editor_url_to_load: 'http://fixture.invalid/?page_id=42', localStorage: { getItem: () => null, setItem() {} } } }
  context.window.document = context.document
  context.window.LCFACreateEditorBuffer = () => ({ read: () => ({ state: 'clean' }), unchanged: () => true, refresh: async () => { assert.fail('Stop must never request a refresh') } })
  vm.runInNewContext(script, context); await flush()
  assert.equal(stop.hidden, false, 'Reload must recover the active request from the server')
  nodes.threadSelect.value = 'thread-b'; nodes.threadSelect.listeners.change(); assert.equal(stop.hidden, true)
  const unrelatedState = nodes.statusNode.getAttribute('data-state')
  failPoll = true
  timers.shift()(); await flush()
  assert.equal(nodes.statusNode.getAttribute('data-state'), unrelatedState, 'A background error must not change another conversation')
  assert.ok(timers.length, 'Network failure must schedule another read')
  nodes.threadSelect.value = 'thread-a'; nodes.threadSelect.listeners.change(); assert.equal(stop.hidden, false)
  stop.listeners.click(); stop.listeners.click(); await flush()
  assert.equal(stopCalls, 1, 'Stop must suppress duplicate clicks')
  assert.equal(stop.disabled, false, 'A failed Stop must allow retry')
  assert.match(nodes.statusNode.textContent, /not be confirmed/)
  failStop = false; stop.listeners.click(); await flush()
  assert.equal(stop.disabled, true); assert.equal(nodes.statusNode.getAttribute('data-state'), 'stop_requested')
  failPoll = false; status = 'stopped'; timers.shift()(); await flush()
  assert.equal(nodes.statusNode.getAttribute('data-state'), 'stopped')
  assert.equal(stop.hidden, true); assert.equal(refreshes.length, 0, 'Stop must never refresh or overwrite the editor')

  function send(prompt = 'Read this page') { nodes.promptInput.value = prompt; nodes.promptInput.listeners.input({}); nodes.analyzeButton.listeners.click({ preventDefault() {} }) }
  loseDelivery = true; send(); await flush(); send(); await flush()
  assert.equal(deliveries.length, 2)
  assert.equal(deliveries[0].idempotency_key, deliveries[1].idempotency_key, 'Uncertain delivery retry must reuse the same key')
  loseDelivery = false; send(); await flush()
  assert.equal(deliveries[0].idempotency_key, deliveries[2].idempotency_key)
  assert.equal(nodes.statusNode.getAttribute('data-state'), 'cancelled')
  assert.equal(refreshes.length, 0)
  rejectDelivery = true; send('First rejected prompt'); await flush()
  rejectDelivery = false; send('A different prompt after explicit rejection'); await flush()
  assert.equal(deliveries.length, 5, 'Explicit rejection before enqueue must allow a different prompt')
  console.log('PASS Stop resume, conversation isolation, retry after network loss, in-flight click guard and uncertain delivery deduplication')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
