'use strict'

const { test } = require('node:test')
const assert = require('node:assert/strict')
const { EventEmitter } = require('node:events')
const { CodexThreadProtocol } = require('../src/codex-thread-protocol')
const THREAD = 'thread-fixture'
const tick = () => new Promise(resolve => setImmediate(resolve))
const turn = (id = 'turn-fixture', status = 'inProgress') => ({ id, status, items: [] })

class FakeTransport extends EventEmitter {
  sent = []; closed = false; state = 'idle'; history = []; hook = null
  send(raw) {
    const message = JSON.parse(raw); this.sent.push(message)
    if (this.hook?.(message) === true) return
    if (!message.id) return
    const results = {
      initialize: { userAgent: 'synthetic', codexHome: '/private-fixture', platformFamily: 'unix', platformOs: 'macos' },
      'thread/resume': { thread: { id: THREAD, status: { type: this.state }, turns: [], preview: 'private fixture preview' } },
      'thread/read': { thread: { id: THREAD, status: { type: this.state }, turns: message.params.includeTurns ? this.history : [] } },
      'turn/start': { turn: turn() }, 'turn/interrupt': {}
    }
    assert.ok(Object.hasOwn(results, message.method), `Unexpected RPC: ${message.method}`)
    if (message.method === 'turn/start') this.state = 'active'
    this.reply(message.id, results[message.method])
  }
  reply(id, result) { this.emit('message', JSON.stringify({ id, result })) }
  event(method, params, id) { this.emit('message', JSON.stringify({ method, params: { threadId: THREAD, ...params }, ...(id === undefined ? {} : { id }) })) }
  close() { this.closed = true }
}

function fixture(t, options = {}) {
  const events = [], permissions = []
  const journal = new MemoryJournal()
  const protocol = new CodexThreadProtocol({ threadId: THREAD, deliveryJournal: journal, authorize: async action => { permissions.push(action); return true }, onEvent: event => events.push(event), ...options })
  t.after(() => protocol.close())
  return { protocol, transport: new FakeTransport(), events, permissions, journal }
}

class MemoryJournal {
  rows = new Map()
  async pending(threadId) { const row = [...this.rows.values()].find(r => r.desktop_thread_id === threadId && r.state !== 'settled'); return row ? { ...row, send_allowed: false } : null }
  async reserve({ request_id, desktop_thread_id }) {
    if (this.rows.has(request_id)) { const { receipt_token, ...row } = this.rows.get(request_id); return { ...row, send_allowed: false } }
    if (await this.pending(desktop_thread_id)) throw Error('review required')
    const row = { request_id, desktop_thread_id, receipt_token: 'a'.repeat(64), turn_id: null, turn_status: null, state: 'reserved' }
    this.rows.set(request_id, row); return { ...row, send_allowed: true }
  }
  async record({ request_id, desktop_thread_id, receipt_token, turn_id, turn_status }) {
    const row = this.rows.get(request_id)
    assert.equal(row.desktop_thread_id, desktop_thread_id); assert.equal(row.receipt_token, receipt_token)
    if (row.turn_id) assert.equal(row.turn_id, turn_id)
    if (row.state === 'settled') assert.equal(row.turn_status, turn_status)
    Object.assign(row, { turn_id, turn_status, state: turn_status === 'inProgress' ? 'acknowledged' : 'settled' })
    const { receipt_token: token, ...result } = row
    return { ...result, send_allowed: false }
  }
}

test('metadata-only exact-thread handshake never claims Desktop verification', async t => {
  const f = fixture(t)
  const status = await f.protocol.connect(f.transport)
  assert.equal(status.phase, 'idle'); assert.equal(status.desktopConversationVerified, false)
  assert.deepEqual(f.transport.sent.map(m => m.method), ['initialize', 'initialized', 'thread/resume'])
  assert.deepEqual(f.transport.sent.at(-1).params, { threadId: THREAD, excludeTurns: true })
  assert.equal(f.transport.sent[0].params.capabilities.experimentalApi, false)
  assert.ok(!JSON.stringify(status).includes('private'))
  assert.ok(!JSON.stringify(f.events).includes('private'))
  assert.throws(() => new CodexThreadProtocol({ threadId: THREAD }), /codex_binding_required/)
})

test('a mismatched resume response fails without revealing its contents', async t => {
  const f = fixture(t)
  f.transport.hook = m => { if (m.method === 'thread/resume') { f.transport.reply(m.id, { thread: { id: 'foreign', preview: 'secret' } }); return true } }
  await assert.rejects(f.protocol.connect(f.transport), /codex_thread_identity_mismatch/)
  assert.equal(f.transport.closed, true); assert.deepEqual(f.events, [])
})

test('start preserves model choice, inherits permissions, rejects arbitrary overrides and duplicates', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  for (const extra of [{ sandboxPolicy: 'dangerFullAccess' }, { threadId: 'foreign' }, { model: {} }, { effort: 'invented' }]) {
    await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture prompt', ...extra }), /codex_turn_input_invalid/)
  }
  const r = await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture prompt', model: 'gpt-6-sol', effort: 'high' })
  assert.equal(r.turnId, 'turn-fixture')
  assert.deepEqual(f.transport.sent.at(-1).params, { threadId: THREAD, input: [{ type: 'text', text: 'Fixture prompt' }], model: 'gpt-6-sol', effort: 'high' })
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Again' }), /already_attempted/)
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-2', text: 'Another' }), /not_idle/)
  assert.equal(f.transport.sent.filter(m => m.method === 'turn/start').length, 1)
})

test('fresh remote busy state blocks sending even when the local panel looked idle', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport); f.transport.state = 'active'
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /not_idle/)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.ok(!f.transport.sent.some(m => m.method === 'turn/start'))
})

test('only bound-thread public messages reach the sink; raw reasoning and tool payloads are excluded', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  const p = { turnId: 'turn-fixture', itemId: 'item-fixture', delta: 'Hello' }
  f.transport.event('item/agentMessage/delta', { ...p, threadId: 'foreign', delta: 'private' })
  f.transport.event('item/agentMessage/delta', { ...p, turnId: 'foreign', delta: 'private' })
  f.transport.event('item/reasoning/textDelta', { ...p, delta: 'private reasoning' })
  f.transport.event('item/completed', { turnId: 'turn-fixture', item: { id: 'item-secret', type: 'mcpToolCall', arguments: 'private' } })
  f.transport.event('item/agentMessage/delta', p)
  f.transport.event('item/completed', { turnId: 'turn-fixture', item: { id: 'item-fixture', type: 'agentMessage', text: 'Hello world', phase: 'final_answer', internal: 'private' } })
  await tick()
  assert.deepEqual(f.events.map(e => e.type), ['message_delta', 'message_completed'])
  assert.ok(!JSON.stringify(f.events).includes('private'))
  assert.equal(f.events[1].text, 'Hello world')
})

test('Stop acknowledgement stays pending until an interrupted terminal event', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  const result = await f.protocol.interrupt()
  assert.equal(result.stopRequested, true); assert.equal(result.stopConfirmed, false)
  assert.equal(f.protocol.status().phase, 'stopping')
  assert.deepEqual(f.transport.sent.at(-1).params, { threadId: THREAD, turnId: 'turn-fixture' })
  f.transport.event('turn/completed', { turn: turn('turn-fixture', 'interrupted') })
  await tick()
  assert.equal(f.events.at(-1).status, 'interrupted'); assert.equal(f.protocol.status().phase, 'idle')
})

test('approvals remain in Desktop; duplicate, foreign and resolved approvals are scoped', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  const p = { turnId: 'turn-fixture', itemId: 'item-fixture', command: 'secret credential' }
  const count = f.transport.sent.length
  f.transport.event('item/commandExecution/requestApproval', { ...p, threadId: 'foreign' }, 9)
  f.transport.event('item/commandExecution/requestApproval', p, 10)
  f.transport.event('item/commandExecution/requestApproval', p, 10)
  f.transport.event('serverRequest/resolved', { requestId: 10, threadId: 'foreign' })
  f.transport.event('serverRequest/resolved', { requestId: 10 })
  await tick()
  assert.deepEqual(f.events.map(e => e.type), ['approval_required', 'approval_resolved'])
  assert.equal(f.events[0].reviewInDesktop, true)
  assert.ok(!JSON.stringify(f.events).includes('secret'))
  assert.equal(f.transport.sent.length, count, 'No competing approval answer or policy change')
})

test('lost start acknowledgement is never replayed, including after reconnect', async t => {
  const f = fixture(t, { timeoutMs: 20 }); await f.protocol.connect(f.transport)
  f.transport.hook = m => m.method === 'turn/start'
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /response_timeout/)
  assert.equal(f.protocol.status().deliveryUncertain, true)
  const next = new FakeTransport()
  await f.protocol.connect(next)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /already_attempted/)
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-2', text: 'Fixture' }), /not_idle/)
  await assert.rejects(f.protocol.reconcile(), /delivery_review_required/)
  next.event('turn/started', { turn: turn('unrelated-later-turn') }); await tick()
  assert.equal(f.protocol.status().turnId, null, 'A later turn cannot be assumed to contain the unacknowledged input')
  assert.ok(!next.sent.some(m => m.method === 'turn/start'))
})

test('known turn reconnect reconciles only that thread and preserves terminal evidence', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.emit('close')
  const next = new FakeTransport(); next.history = [turn('turn-fixture', 'completed')]
  await f.protocol.connect(next)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.deepEqual(await f.protocol.reconcile(), { turnId: 'turn-fixture', status: 'completed' })
  assert.equal(f.protocol.status().phase, 'idle')
  assert.deepEqual(next.sent.at(-1).params, { threadId: THREAD, includeTurns: true })
  assert.ok(!next.sent.some(m => /list|start/.test(m.method)))
})

test('completion before start response cannot be resurrected by a late acknowledgement', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    f.transport.event('turn/started', { turn: turn() })
    f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') })
    f.transport.reply(m.id, { turn: turn() }); return true
  }
  const r = await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  assert.equal(r.status, 'terminal_observed'); assert.equal(f.protocol.status().phase, 'idle')
  f.transport.event('turn/started', { turn: turn() }); await tick()
  assert.equal(f.protocol.status().phase, 'idle')
})

test('revocation before or during a prompt check prevents sending', async t => {
  const denied = fixture(t, { authorize: async () => false })
  await assert.rejects(denied.protocol.connect(denied.transport), /binding_revoked/)
  assert.equal(denied.transport.sent.length, 0)
  let checks = 0
  const f = fixture(t, { authorize: async ({ action }) => action !== 'start' || ++checks === 1 })
  await f.protocol.connect(f.transport)
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /binding_revoked/)
  assert.ok(!f.transport.sent.some(m => m.method === 'turn/start'))
  assert.equal(f.protocol.status().phase, 'revoked')
})

test('event authorization is checked again and revocation discards queued output', async t => {
  let allowed = true
  const f = fixture(t, { authorize: async () => allowed })
  await f.protocol.connect(f.transport); await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  allowed = false
  f.transport.event('item/agentMessage/delta', { turnId: 'turn-fixture', itemId: 'item-fixture', delta: 'must not be disclosed' })
  await tick()
  assert.equal(f.events.length, 0); assert.equal(f.protocol.status().phase, 'revoked')
  await assert.rejects(f.protocol.connect(new FakeTransport()), /binding_revoked/)
})

test('malformed and oversized frames disconnect; RPC error text is redacted', async t => {
  for (const frame of ['[1,2]', '{invalid', 'x'.repeat(2 * 1024 * 1024 + 1)]) {
    const f = fixture(t); await f.protocol.connect(f.transport)
    f.transport.emit('message', frame)
    assert.equal(f.protocol.status().phase, 'disconnected')
  }
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    f.transport.emit('message', JSON.stringify({ id: m.id, error: { message: 'private secret' } })); return true
  }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), error => error.code === 'codex_request_rejected' && !error.message.includes('private'))
})

test('an unresponsive authorizer is bounded and cannot open a connection', async t => {
  const f = fixture(t, { timeoutMs: 10, authorize: () => new Promise(() => {}) })
  await assert.rejects(f.protocol.connect(f.transport), /binding_revoked/)
  assert.equal(f.transport.sent.length, 0)
})

test('event backpressure disconnects instead of buffering unbounded output', async t => {
  let release
  const held = new Promise(resolve => { release = resolve })
  const f = fixture(t, { authorize: async ({ action }) => action === 'event' ? held : true })
  await f.protocol.connect(f.transport); await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  for (let i = 0; i < 130; i++) f.transport.event('item/agentMessage/delta', { turnId: 'turn-fixture', itemId: 'item-fixture', delta: 'fixture' })
  release(true); await tick()
  assert.equal(f.transport.closed, true); assert.equal(f.events.length, 0)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
})

test('event-sink failure is bounded, private and does not acknowledge cancellation', async t => {
  const f = fixture(t, { timeoutMs: 10, onEvent: () => new Promise(() => {}) })
  await f.protocol.connect(f.transport); await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.event('item/agentMessage/delta', { turnId: 'turn-fixture', itemId: 'item-fixture', delta: 'fixture' })
  await new Promise(resolve => setTimeout(resolve, 30))
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.ok(!f.transport.sent.some(m => m.method === 'turn/interrupt'))
})

test('invalid initialization and control characters fail closed', async t => {
  for (const id of ['thread\n', 'thread\r', 'thread\0', 'thread/other']) assert.throws(() => new CodexThreadProtocol({ threadId: id, authorize: async () => true }), /binding_required/)
  const f = fixture(t)
  f.transport.hook = m => { f.transport.reply(m.id, {}); return true }
  await assert.rejects(f.protocol.connect(f.transport), /initialize_invalid/)
  assert.equal(f.transport.sent.length, 1)
})

test('an active event during resume cannot be overwritten by stale idle metadata', async t => {
  const f = fixture(t)
  f.transport.hook = m => {
    if (m.method !== 'thread/resume') return
    f.transport.event('thread/status/changed', { status: { type: 'active' } })
    f.transport.reply(m.id, { thread: { id: THREAD, status: { type: 'idle' } } }); return true
  }
  await f.protocol.connect(f.transport)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /not_idle/)
})

test('simultaneous prompt submissions produce only one start request', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  const first = f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture one' })
  const second = f.protocol.startTurn({ requestId: 'request-2', text: 'Fixture two' })
  await assert.rejects(second, /not_idle/); await first
  assert.equal(f.transport.sent.filter(m => m.method === 'turn/start').length, 1)
})

test('disconnect during start send remains uncertain and old transport events are detached', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.transport.hook = m => { if (m.method === 'turn/start') { f.transport.emit('close'); return true } }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /disconnected/)
  const next = new FakeTransport(); await f.protocol.connect(next)
  f.transport.emit('error', Error('private old transport failure'))
  f.transport.event('turn/started', { turn: turn('old-turn') }); await tick()
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.equal(f.protocol.status().turnId, null)
  assert.deepEqual(f.events, [])
})

test('mismatched start response and unknown terminal events cannot retarget the client', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    f.transport.event('turn/started', { turn: turn() })
    f.transport.reply(m.id, { turn: turn('different-turn') }); return true
  }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /turn_identity_mismatch/)
  assert.equal(f.protocol.status().turnId, 'turn-fixture')
  assert.equal(f.protocol.status().deliveryUncertain, true)
})

test('completion during reconciliation wins over a stale in-progress read response', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.emit('close')
  const next = new FakeTransport(); next.state = 'active'; await f.protocol.connect(next)
  next.hook = m => {
    if (m.method !== 'thread/read' || !m.params.includeTurns) return
    next.event('turn/completed', { turn: turn('turn-fixture', 'interrupted') })
    next.reply(m.id, { thread: { id: THREAD, status: { type: 'active' }, turns: [turn()] } }); return true
  }
  assert.deepEqual(await f.protocol.reconcile(), { turnId: 'turn-fixture', status: 'interrupted' })
  assert.equal(f.protocol.status().phase, 'idle')
})

test('missing known turn on reconnect leaves recovery required and does not replay input', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.emit('close')
  const next = new FakeTransport(); await f.protocol.connect(next)
  await assert.rejects(f.protocol.reconcile(), /turn_not_found/)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.ok(!next.sent.some(m => m.method === 'turn/start'))
})

test('natural completion racing Stop is not called confirmed interruption', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.hook = m => {
    if (m.method !== 'turn/interrupt') return
    f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') })
    setImmediate(() => f.transport.reply(m.id, {})); return true
  }
  const result = await f.protocol.interrupt()
  assert.equal(result.stopConfirmed, false)
  assert.equal(f.events.at(-1).status, 'completed')
})

test('a Desktop turn arriving during the final authorization check prevents accidental steering', async t => {
  let f, checks = 0
  f = fixture(t, { authorize: async ({ action }) => {
    if (action === 'start' && ++checks === 2) {
      f.transport.event('turn/started', { turn: turn('desktop-turn') })
      await tick()
    }
    return true
  } })
  await f.protocol.connect(f.transport)
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /not_idle/)
  assert.equal(f.protocol.status().turnId, 'desktop-turn')
  assert.ok(!f.transport.sent.some(m => m.method === 'turn/start'))
})

test('validated prompt is immutable across asynchronous checks and dead transports cannot be reused', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  const input = { requestId: 'request-1', text: 'Original fixture prompt', model: 'gpt-6-sol' }
  f.transport.hook = m => {
    if (m.method === 'thread/read') { input.text = 'Unexpected replacement'; input.model = 'other-model' }
  }
  await f.protocol.startTurn(input)
  assert.equal(f.transport.sent.at(-1).params.input[0].text, 'Original fixture prompt')
  assert.equal(f.transport.sent.at(-1).params.model, 'gpt-6-sol')
  f.transport.emit('close')
  await assert.rejects(f.protocol.connect(f.transport), /fresh_transport_required/)
})

test('a durable journal is mandatory and must be read before any protocol handshake', async t => {
  assert.throws(() => new CodexThreadProtocol({ threadId: THREAD, authorize: async () => true }), /delivery_journal_required/)
  const f = fixture(t, { deliveryJournal: { pending: async () => { throw Error('private database error') }, reserve() {}, record() {} } })
  await assert.rejects(f.protocol.connect(f.transport), error => error.code === 'codex_delivery_storage_unavailable' && !error.message.includes('private'))
  assert.equal(f.transport.sent.length, 0)
})

test('a new helper instance loads a lost acknowledgement from the shared journal', async t => {
  const first = fixture(t, { timeoutMs: 20 }); await first.protocol.connect(first.transport)
  first.transport.hook = message => message.method === 'turn/start'
  await assert.rejects(first.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /response_timeout/)
  first.protocol.close()
  const second = fixture(t, { deliveryJournal: first.journal }); await second.protocol.connect(second.transport)
  assert.equal(second.protocol.status().deliveryUncertain, true)
  await assert.rejects(second.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /already_attempted/)
  assert.ok(!second.transport.sent.some(message => message.method === 'turn/start'))
})

test('settled request IDs cannot be replayed after restarting with empty process memory', async t => {
  const first = fixture(t); await first.protocol.connect(first.transport)
  await first.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  first.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') }); await tick(); first.protocol.close()
  const second = fixture(t, { deliveryJournal: first.journal }); await second.protocol.connect(second.transport)
  await assert.rejects(second.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /already_attempted/)
  assert.equal(second.protocol.status().phase, 'idle')
  assert.ok(!second.transport.sent.some(message => message.method === 'turn/start'))
})

test('reservation storage failure blocks the actual turn request', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.journal.reserve = async () => { throw Error('private SQL failure') }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /delivery_storage_unavailable/)
  assert.ok(!f.transport.sent.some(message => message.method === 'turn/start'))
})

test('lost storage acknowledgement blocks a retry even though nothing was sent to Codex', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  const reserve = f.journal.reserve.bind(f.journal)
  f.journal.reserve = async input => { await reserve(input); throw Error('lost response') }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /delivery_storage_unavailable/)
  f.journal.reserve = reserve
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /delivery_review_required/)
  assert.ok(!f.transport.sent.some(message => message.method === 'turn/start'))
})

test('acknowledgement persistence failure cannot be mistaken for a safe resend', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.journal.record = async () => { throw Error('private record failure') }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /delivery_storage_unavailable/)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.equal(f.journal.rows.get('request-1').state, 'reserved')
  assert.equal(f.transport.sent.filter(message => message.method === 'turn/start').length, 1)
})

test('a completion racing durable acknowledgement is stored as terminal before returning', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  const record = f.journal.record.bind(f.journal), writes = []
  f.journal.record = async input => {
    writes.push(input.turn_status)
    if (input.turn_status === 'inProgress') {
      f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') })
      await tick()
    }
    return record(input)
  }
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  assert.deepEqual(writes, ['inProgress', 'completed'])
  assert.equal(f.journal.rows.get('request-1').state, 'settled')
})

test('failed terminal persistence requires recovery and does not publish a completion event', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  const record = f.journal.record.bind(f.journal)
  f.journal.record = async () => { throw Error('private SQL failure') }
  f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') }); await tick()
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.ok(!f.events.some(event => event.type === 'turn_completed'))
  f.journal.record = record
  const second = fixture(t, { deliveryJournal: f.journal }); second.transport.history = [turn('turn-fixture', 'completed')]
  await second.protocol.connect(second.transport); await second.protocol.reconcile()
  assert.equal(f.journal.rows.get('request-1').state, 'settled')
})

test('receipt tokens stay private and malformed journal grants cannot send a turn', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  const reserve = f.journal.reserve.bind(f.journal)
  f.journal.reserve = async input => ({ ...await reserve(input), receipt_token: 'a'.repeat(64) + '\n' })
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /journal_invalid/)
  assert.ok(!f.transport.sent.some(message => message.method === 'turn/start'))
  assert.ok(!JSON.stringify(f.protocol.status()).includes('receipt_token'))
})

test('early completion is withheld until the start acknowledgement and durable terminal receipt', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  let start, release
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    start = m
    f.transport.event('turn/started', { turn: turn() })
    f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') })
    return true
  }
  const record = f.journal.record.bind(f.journal)
  f.journal.record = async input => { await new Promise(resolve => { release = resolve }); return record(input) }
  const result = f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  result.catch(() => {}) // The cleanup hook can revoke an in-flight assertion failure.
  await tick()
  assert.ok(!f.events.some(event => event.type === 'turn_completed'), 'No completion before the matching start response')
  f.transport.reply(start.id, { turn: turn() }); await tick()
  assert.ok(!f.events.some(event => event.type === 'turn_completed'), 'No completion before receipt storage')
  assert.equal(f.protocol.status().deliveryUncertain, true)
  release(); await result
  assert.equal(f.journal.rows.get('request-1').state, 'settled')
  assert.equal(f.events.filter(event => event.type === 'turn_completed').length, 1)
})

test('an early terminal event cannot conceal a mismatched start acknowledgement', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    f.transport.event('turn/started', { turn: turn('conflicting-turn') })
    f.transport.event('turn/completed', { turn: turn('conflicting-turn', 'completed') })
    f.transport.reply(m.id, { turn: turn() }); return true
  }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /identity_mismatch/)
  assert.equal(f.journal.rows.get('request-1').state, 'reserved')
  assert.ok(!f.events.some(event => event.type === 'turn_completed'))
})

test('disconnect or revocation during terminal storage prevents late event disclosure', async t => {
  for (const interrupt of ['disconnect', 'revoke']) {
    let allowed = true
    const f = fixture(t, { authorize: async () => allowed }); await f.protocol.connect(f.transport)
    await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
    const record = f.journal.record.bind(f.journal)
    f.journal.record = async input => {
      if (interrupt === 'disconnect') f.transport.emit('close')
      else allowed = false
      return record(input)
    }
    f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') }); await tick()
    assert.ok(!f.events.some(event => event.type === 'turn_completed'), interrupt)
  }
})

test('revocation during journal recovery prevents the protocol handshake', async t => {
  let allowed = true
  const f = fixture(t, { authorize: async () => allowed })
  f.journal.pending = async () => { allowed = false; return null }
  await assert.rejects(f.protocol.connect(f.transport), /binding_revoked/)
  assert.equal(f.transport.sent.length, 0)
})

test('a deferred completion consumer failure is redacted and closes the transport', async t => {
  const f = fixture(t, { onEvent: event => { if (event.type === 'turn_completed') throw Error('private sink credentials') } })
  await f.protocol.connect(f.transport)
  f.transport.hook = m => {
    if (m.method !== 'turn/start') return
    f.transport.event('turn/started', { turn: turn() })
    f.transport.event('turn/completed', { turn: turn('turn-fixture', 'completed') })
    f.transport.reply(m.id, { turn: turn() }); return true
  }
  await assert.rejects(f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' }), /^Error: codex_event_failed$/)
  assert.equal(f.transport.closed, true)
  assert.equal(f.journal.rows.get('request-1').state, 'settled')
})

test('failed reconciliation storage disconnects with the durable record available for recovery', async t => {
  const f = fixture(t); await f.protocol.connect(f.transport)
  await f.protocol.startTurn({ requestId: 'request-1', text: 'Fixture' })
  f.transport.emit('close')
  const next = new FakeTransport(); next.history = [turn('turn-fixture', 'completed')]
  await f.protocol.connect(next)
  f.journal.record = async () => { throw Error('private failure') }
  await assert.rejects(f.protocol.reconcile(), /delivery_storage_unavailable/)
  assert.equal(next.closed, true)
  assert.equal(f.protocol.status().phase, 'needs_reconciliation')
  assert.equal(f.journal.rows.get('request-1').state, 'acknowledged')
})
