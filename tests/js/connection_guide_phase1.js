const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const { repoPath } = require('./test-paths.cjs')

const html = fs.readFileSync(repoPath('docs/coding-agent-setup.html'), 'utf8')
const script = html.match(/<script>([\s\S]*?)<\/script>/)[1]
const elements = new Map()
function element(id) {
  if (!elements.has(id)) elements.set(id, {
    value: '', textContent: '', innerHTML: '', handlers: {}, style: {}, flags: {},
    addEventListener(event, callback) { this.handlers[event] = callback },
    classList: { toggle(name, value) { element(id).flags[name] = value } },
    querySelectorAll() { return [] }, querySelector() { return null }
  })
  return elements.get(id)
}
const storageKeys = []
const context = {
  document: { getElementById: element },
  location: { hash: '#codex' },
  history: { replaceState() {} },
  localStorage: { getItem(key) { storageKeys.push(key); return null }, setItem() {} }
}
context.window = context
vm.runInNewContext(script, context)

const clients = ['codex', 'opencode', 'claude-code', 'claude-desktop', 'cursor']
for (const client of clients) {
  element('agent-select').value = client
  element('agent-select').handlers.change()
  const instructions = element('steps').innerHTML
  assert.equal((instructions.match(/class="step"/g) || []).length, 4)
  assert.ok(instructions.includes('Copy setup instructions'))
  assert.ok(instructions.includes('Authorize Full Access'))
  assert.ok(instructions.indexOf('Authorize Full Access') < instructions.indexOf('Let the agent verify'))
  assert.ok(instructions.includes('get_connection_handoff'))
  assert.ok(!instructions.includes('Run smoke test'))
  assert.ok(!instructions.includes('Other coding agents'))
  assert.equal(element('ready-message').flags['is-visible'], false)
  assert.equal(element('progress-text').textContent, '0 of 4 checklist steps marked')
  if (client === 'claude-desktop') {
    assert.ok(instructions.includes('In Terminal or PowerShell'))
    assert.ok(instructions.includes('Do not paste it into the normal Chat screen'))
  }
}
assert.ok(storageKeys.every(key => key.startsWith('lcfa-agent-guide:beta8:')))
assert.ok(html.includes('This guide cannot inspect your connection.'))
assert.ok(html.includes('do not reuse a setup for another app'))
assert.ok(!html.includes('About 5 minutes'))
assert.ok(!html.includes('<strong>Ready.</strong>'))
console.log('PASS: guide uses the unified flow for five clients and cannot claim connection readiness')
