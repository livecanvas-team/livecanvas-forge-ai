'use strict'
// Real WordPress journal, synthetic App Server. Never launches a coding agent.
const { execFile } = require('node:child_process')
const { EventEmitter } = require('node:events')
const assert = require('node:assert/strict')
const path = require('node:path')
const { CodexThreadProtocol } = require('../../mcp/src/codex-thread-protocol')

async function main() {
  let input = ''
  for await (const chunk of process.stdin) input += chunk
  const config = JSON.parse(input)
  assert.match(config.marker, /^delivery-fixture-[a-f0-9]{16}$/)
  assert.equal(config.desktop_thread_id, config.marker + '-protocol')
  const invoke = (operation, payload = {}) => new Promise((resolve, reject) => {
    const child = execFile(config.php_binary, ['-d', `mysqli.default_socket=${config.db_socket}`, path.join(__dirname, 'desktop-delivery-worker.php')],
      { timeout: 15000, maxBuffer: 256 * 1024 }, (error, stdout) => {
        if (error) return reject(Error('Private journal fixture failed'))
        try { resolve(JSON.parse(stdout)) } catch { reject(Error('Invalid private journal fixture response')) }
      })
    child.stdin.on('error', () => {})
    child.stdin.end(JSON.stringify({ ...config, ...payload, operation }))
  })
  const journal = {
    pending: desktop_thread_id => invoke('pending', { desktop_thread_id }),
    reserve: payload => invoke('reserve', payload),
    record: payload => invoke('record', payload)
  }
  class Transport extends EventEmitter {
    starts = 0
    send(raw) {
      const message = JSON.parse(raw)
      if (!message.id) return
      if (message.method === 'turn/start') { this.starts++; this.emit('close'); return }
      const result = message.method === 'initialize'
        ? { userAgent: 'synthetic', platformFamily: 'unix', platformOs: 'macos' }
        : { thread: { id: config.desktop_thread_id, status: { type: 'idle' }, turns: [] } }
      this.emit('message', JSON.stringify({ id: message.id, result }))
    }
    close() {}
  }
  const create = () => new CodexThreadProtocol({ threadId: config.desktop_thread_id, authorize: async () => true, deliveryJournal: journal, timeoutMs: 20000 })
  const first = create(), firstTransport = new Transport()
  try {
    await first.connect(firstTransport)
    await assert.rejects(first.startTurn({ requestId: config.request_id, text: 'Synthetic delivery restart fixture' }), /disconnected/)
    assert.equal(firstTransport.starts, 1)
  } finally { first.close() }
  // A new protocol instance has no memory of the earlier attempted request.
  const restarted = create(), secondTransport = new Transport()
  try {
    const state = await restarted.connect(secondTransport)
    assert.equal(state.phase, 'needs_reconciliation')
    assert.equal(state.deliveryUncertain, true)
    assert.equal(state.desktopConversationVerified, false)
    await assert.rejects(restarted.startTurn({ requestId: config.request_id, text: 'Synthetic delivery restart fixture' }), /already_attempted/)
    assert.equal(secondTransport.starts, 0)
    assert.ok(!JSON.stringify(state).includes('receipt_token'))
  } finally { restarted.close() }
  process.stdout.write(JSON.stringify({ ok: true, actual_wordpress_journal: true, synthetic_app_server: true, starts_before_restart: 1, starts_after_restart: 0, provider_invoked: false }))
}
main().catch(() => { process.stderr.write('Desktop delivery protocol fixture failed.\n'); process.exitCode = 1 })
