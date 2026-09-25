const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')

async function main() {
  const elements = new Map()
  function element(selector) {
    if (!elements.has(selector)) elements.set(selector, { hidden: false, disabled: false, textContent: '', value: '', handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn }, focus() {}, select() {} })
    return elements.get(selector)
  }
  const root = { dataset: { attempt: '' }, querySelector: element }
  const client = element('#lcfa-connect-client'); client.value = 'cursor'
  const timers = new Map(); let clock = 0
  const calls = []; const copied = []
  const attempt = { id: 'a'.repeat(32), client: 'cursor', state: 'waiting_for_client', prompt: 'SAFE PROMPT', command: 'SAFE COMMAND' }
  let state = attempt
  let failCopy = false
  const context = {
    URL,
    location: { href: 'https://site.test/wp-admin/admin.php?page=lcfa-dashboard&tab=connections' },
    history: { replaceState(value, title, href) { context.location.href = href } },
    document: { hidden: false, getElementById: () => root, addEventListener() {} },
    navigator: { clipboard: { writeText: async text => { if (failCopy) throw new Error('Denied'); copied.push(text) } } },
    lcfaAdmin: { restUrl: 'https://site.test/wp-json/lcfa/v1/', restNonce: 'nonce' },
    lcfaConnect: { project: 'project', desktop: 'desktop', code: 'Code', copied: 'copied', ready: 'ready', copyManually: 'copy manually' },
    AbortController, setTimeout: (fn, delay) => { timers.set(++clock, { fn, delay }); return clock }, clearTimeout: id => timers.delete(id),
    fetch: async (url, options) => {
      calls.push({ url, options })
      if (url.endsWith('pairing/approve')) { state = { ...attempt, state: 'ready' }; return { ok: true, json: async () => ({ ok: true }) } }
      return { ok: true, json: async () => state }
    }
  }
  context.window = context
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/connect.js'), 'utf8'), context)
  await element('[data-connect-copy]').handlers.click()
  assert.deepEqual(copied, ['SAFE PROMPT'])
  assert.equal(new URL(context.location.href).searchParams.get('connection_attempt'), attempt.id, 'A reload must keep this tab bound to its attempt, even if another client starts setup.')
  assert.equal(calls[0].options.headers['X-WP-Nonce'], 'nonce')
  assert.deepEqual(JSON.parse(calls[0].options.body), { client: 'cursor' })
  assert.equal(calls.filter(call => call.url.endsWith('pairing/approve')).length, 0, 'Copying must not authorize access.')
  state = { ...attempt, state: 'authorization_required', pairing_id: 'pair1', user_code: 'ABCD-1234' }
  await [...timers.values()].find(timer => timer.delay === 3000).fn()
  assert.equal(element('[data-connect-approve]').hidden, false)
  assert.equal(element('[data-connect-code]').textContent, 'Code: ABCD-1234')
  await element('[data-connect-approve]').handlers.click()
  assert.equal(root.dataset.state, 'ready')
  assert.equal(element('[data-connect-copy]').hidden, true)
  assert.equal(element('[data-connect-approve]').hidden, true)
  client.value = 'claude-desktop'; client.handlers.change()
  assert.equal(new URL(context.location.href).searchParams.has('connection_attempt'), false)
  assert.equal(element('[data-connect-instruction]').textContent, 'desktop')
  state = { ...attempt, client: 'claude-desktop' }; failCopy = true
  await element('[data-connect-copy]').handlers.click()
  assert.equal(element('#lcfa-connect-prompt').value, 'SAFE COMMAND', 'Desktop must receive a terminal command, not shell instructions for its chat.')
  assert.equal(element('[data-connect-details]').open, true)
  assert.equal(element('[data-connect-status]').textContent, 'copy manually')
  console.log('PASS: one-action setup, explicit approval, automatic status, Desktop instructions and clipboard fallback')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
