'use strict'

const { randomUUID, createHash } = require('node:crypto')
const VERSION = require('../package.json').version
const MAX_FRAME = 2 * 1024 * 1024
const MAX_QUEUED_BYTES = 4 * 1024 * 1024
const TERMINAL = new Set(['completed', 'interrupted', 'failed'])
const THREAD_STATES = new Set(['idle', 'active', 'notLoaded', 'systemError'])
const APPROVALS = new Map([
  ['item/commandExecution/requestApproval', 'command'],
  ['item/fileChange/requestApproval', 'file_change'],
  ['item/permissions/requestApproval', 'permissions'],
  ['item/tool/requestUserInput', 'question'],
  ['mcpServer/elicitation/request', 'mcp']
])
const EVENTS = new Set(['turn/started', 'turn/completed', 'item/agentMessage/delta',
  'item/completed', 'thread/status/changed', 'serverRequest/resolved'])
const identifier = value => typeof value === 'string' && value.length >= 1 && value.length <= 128 &&
  /^[A-Za-z0-9]/.test(value) && !/[^A-Za-z0-9_-]/.test(value)
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value)
const failure = code => Object.assign(new Error(code), { code })

/**
 * Internal protocol component, deliberately NOT wired to HTTP, MCP or PHP.
 * A future controller must authenticate the transport and prove the Desktop /
 * site / owner / session / exact-thread binding through authorize(). No binding
 * issuer exists yet. A successful handshake is never Desktop identity evidence.
 * transport: EventEmitter message(text), close, error; send(text), close().
 * This class never opens a socket, spawns Codex, creates a thread or logs payloads.
 */
class CodexThreadProtocol {
  #threadId; #authorize; #onEvent; #timeout; #transport; #listeners; #journal; #delivery = null
  #pending = new Map(); #attempted = new Set(); #approvals = new Map()
  #usedTransports = new WeakSet()
  #generation = 0; #sequence = 0; #prefix = randomUUID()
  #phase = 'new'; #turnId = null; #terminalId = null; #terminalStatus = null; #uncertain = false
  #operation = false; #revoked = false; #queue = Promise.resolve(); #queuedBytes = 0; #queuedCount = 0
  #journalWrites = Promise.resolve()
  #provisionalTurnId = null; #deferredCompletion = null

  constructor({ threadId, authorize, deliveryJournal, onEvent = () => {}, timeoutMs = 10000 } = {}) {
    if (!identifier(threadId) || typeof authorize !== 'function' || typeof onEvent !== 'function' ||
        !Number.isInteger(timeoutMs) || timeoutMs < 10 || timeoutMs > 30000) throw failure('codex_binding_required')
    this.#threadId = threadId; this.#authorize = authorize; this.#onEvent = onEvent; this.#timeout = timeoutMs
    if (!deliveryJournal || ['pending', 'reserve', 'record'].some(name => typeof deliveryJournal[name] !== 'function')) throw failure('codex_delivery_journal_required')
    this.#journal = deliveryJournal
  }

  status() {
    return { phase: this.#phase, threadId: this.#threadId, turnId: this.#turnId,
      deliveryUncertain: this.#uncertain, desktopConversationVerified: false }
  }

  async #guard(action) {
    if (this.#revoked) throw failure('codex_binding_revoked')
    let timer
    try {
      const allowed = await Promise.race([
        Promise.resolve().then(() => this.#authorize(Object.freeze({ action, threadId: this.#threadId, turnId: this.#turnId }))),
        new Promise((resolve, reject) => { timer = setTimeout(() => reject(failure('codex_authorization_timeout')), this.#timeout) })
      ])
      if (allowed !== true || this.#revoked) throw failure('codex_binding_revoked')
    } catch {
      this.#revoked = true
      this.#disconnect('codex_binding_revoked')
      throw failure('codex_binding_revoked')
    } finally { clearTimeout(timer) }
  }

  async connect(transport) {
    if (this.#transport || this.#operation) throw failure('codex_connection_busy')
    if (!transport || ['on', 'off', 'send', 'close'].some(name => typeof transport[name] !== 'function')) throw failure('codex_transport_required')
    if (this.#usedTransports.has(transport)) throw failure('codex_fresh_transport_required')
    this.#operation = true
    try {
      await this.#guard('connect')
      const pending = await this.#journalCall('pending', this.#threadId)
      if (pending !== null) {
        this.#validateReceipt(pending, true)
        if (pending.state === 'settled' || (this.#delivery && pending.request_id !== this.#delivery.request_id)) throw failure('codex_delivery_journal_invalid')
        this.#delivery = pending; this.#turnId = pending.turn_id; this.#uncertain = true
        this.#attempted.add(pending.request_id)
      }
      await this.#guard('connect')
      this.#usedTransports.add(transport)
      this.#phase = 'connecting'; this.#transport = transport
      const generation = ++this.#generation
      const receive = data => this.#receive(data, generation)
      const closed = () => this.#disconnect('codex_transport_disconnected')
      this.#listeners = { receive, closed }
      transport.on('message', receive); transport.on('close', closed); transport.on('error', closed)
      const initialized = await this.#rpc('initialize', { clientInfo: { name: 'livecanvas_bridge', title: 'LiveCanvas AI Bridge', version: VERSION },
        capabilities: { experimentalApi: false, optOutNotificationMethods: ['item/reasoning/textDelta', 'item/reasoning/summaryTextDelta', 'item/reasoning/summaryPartAdded'] } })
      if (!object(initialized) || typeof initialized.userAgent !== 'string' ||
          typeof initialized.platformFamily !== 'string' || typeof initialized.platformOs !== 'string') throw failure('codex_initialize_invalid')
      this.#send({ method: 'initialized', params: {} })
      const data = await this.#rpc('thread/resume', { threadId: this.#threadId, excludeTurns: true })
      const state = this.#threadState(data)
      await this.#guard('read')
      await this.#queue
      if (generation !== this.#generation) throw failure('codex_transport_disconnected')
      this.#phase = this.#uncertain || this.#turnId || state !== 'idle' || this.#phase === 'needs_reconciliation' ? 'needs_reconciliation' : 'idle'
      return this.status()
    } catch (error) {
      this.#disconnect('codex_connection_failed')
      throw error
    } finally { this.#operation = false }
  }

  async startTurn(input) {
    if (!object(input) || Object.keys(input).some(key => !['requestId', 'text', 'model', 'effort'].includes(key)) ||
        !identifier(input.requestId) || input.requestId.length > 96 || input.requestId !== input.requestId.toLowerCase() ||
        typeof input.text !== 'string' || !input.text.trim() || Buffer.byteLength(input.text) > 128 * 1024 ||
        (input.model !== undefined && (typeof input.model !== 'string' || !identifier(input.model.replaceAll('.', '_')))) ||
        (input.effort !== undefined && !['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max', 'ultra'].includes(input.effort))) throw failure('codex_turn_input_invalid')
    input = Object.freeze({ ...input })
    if (this.#attempted.has(input.requestId)) throw failure('codex_request_already_attempted')
    if (this.#operation || this.#phase !== 'idle' || this.#turnId || this.#uncertain || this.#delivery) throw failure('codex_thread_not_idle')
    if (this.#attempted.size >= 1024) throw failure('codex_delivery_journal_required')
    this.#operation = true
    const generation = this.#generation
    try {
      await this.#guard('start')
      // Recheck the actual thread instead of treating the last UI state as a lock.
      const state = this.#threadState(await this.#rpc('thread/read', { threadId: this.#threadId, includeTurns: false }))
      await this.#queue
      if (state !== 'idle') this.#phase = 'needs_reconciliation'
      if (state !== 'idle' || this.#turnId || this.#phase !== 'idle') throw failure('codex_thread_not_idle')
      await this.#guard('start')
      if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
      if (this.#turnId || this.#phase !== 'idle' || this.#uncertain) throw failure('codex_thread_not_idle')
      const params = { threadId: this.#threadId, input: [{ type: 'text', text: input.text }] }
      if (input.model !== undefined) params.model = input.model
      if (input.effort !== undefined) params.effort = input.effort
      const payloadHash = createHash('sha256').update(JSON.stringify(params)).digest('hex')
      const receipt = await this.#journalCall('reserve', { request_id: input.requestId, desktop_thread_id: this.#threadId, payload_hash: payloadHash })
      this.#validateReceipt(receipt, receipt?.send_allowed === true)
      if (receipt.request_id !== input.requestId) throw failure('codex_delivery_journal_invalid')
      this.#attempted.add(input.requestId)
      if (!receipt.send_allowed) {
        if (receipt.state === 'settled') throw failure('codex_request_already_attempted')
        this.#uncertain = true
        throw failure('codex_delivery_review_required')
      }
      if (receipt.state !== 'reserved' || receipt.turn_id !== null) throw failure('codex_delivery_journal_invalid')
      this.#delivery = receipt
      this.#provisionalTurnId = null; this.#deferredCompletion = null
      await this.#guard('start')
      if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
      if (this.#turnId || this.#phase !== 'idle' || this.#uncertain) throw failure('codex_thread_not_idle')
      this.#attempted.add(input.requestId); this.#phase = 'starting'
      // Set BEFORE send: loss of the acknowledgement must never replay a prompt.
      this.#uncertain = true
      const data = await this.#rpc('turn/start', params)
      await this.#queue
      if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
      const turn = this.#turn(data?.turn)
      if ((this.#turnId && this.#turnId !== turn.id) ||
          (this.#provisionalTurnId && this.#provisionalTurnId !== turn.id)) throw failure('codex_turn_identity_mismatch')
      if (this.#terminalId !== turn.id) this.#trackTurn(turn)
      this.#uncertain = true
      await this.#recordDelivery(turn.id, this.#terminalId === turn.id ? this.#terminalStatus : turn.status)
      if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
      this.#uncertain = false
      await this.#guard('read')
      if (this.#deferredCompletion) {
        const event = this.#deferredCompletion; this.#deferredCompletion = null
        await this.#publish(event, generation)
      }
      return { turnId: turn.id, status: this.#terminalId === turn.id ? 'terminal_observed' : turn.status }
    } catch (error) {
      if (this.#uncertain || this.#delivery) this.#disconnect('codex_delivery_uncertain')
      throw error
    } finally { this.#operation = false }
  }

  async interrupt() {
    if (!this.#turnId || !this.#transport) throw failure('codex_known_turn_required')
    const turnId = this.#turnId
    const generation = this.#generation
    await this.#guard('interrupt')
    // Completion during authorization means Stop no longer has a current target.
    if (turnId !== this.#turnId) throw failure('codex_known_turn_required')
    this.#phase = 'stopping'
    await this.#rpc('turn/interrupt', { threadId: this.#threadId, turnId })
    await this.#guard('read')
    if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
    return { turnId, stopRequested: true, stopConfirmed: this.#terminalId === turnId && this.#terminalStatus === 'interrupted' }
  }

  async reconcile() {
    if (!this.#transport || this.#operation) throw failure('codex_connection_busy')
    // Without a confirmed turn ID, history similarity cannot prove delivery.
    if (!this.#turnId) throw failure('codex_delivery_review_required')
    this.#operation = true
    const knownId = this.#turnId
    const generation = this.#generation
    try {
      await this.#guard('reconcile')
      const data = await this.#rpc('thread/read', { threadId: this.#threadId, includeTurns: true })
      const state = this.#threadState(data)
      const found = Array.isArray(data.thread.turns) && data.thread.turns.find(turn => turn?.id === knownId)
      if (!found) throw failure('codex_turn_not_found')
      const turn = this.#turn(found)
      await this.#guard('read')
      await this.#queue
      if (generation !== this.#generation || !this.#transport) throw failure('codex_transport_disconnected')
      if (this.#turnId && this.#turnId !== knownId) throw failure('codex_turn_identity_mismatch')
      if (this.#terminalId === knownId) {
        await this.#recordDelivery(knownId, this.#terminalStatus)
        return { turnId: knownId, status: this.#terminalStatus }
      }
      this.#trackTurn(turn)
      await this.#recordDelivery(turn.id, turn.status)
      this.#uncertain = false
      if (TERMINAL.has(turn.status) && state !== 'idle') this.#phase = 'needs_reconciliation'
      return { turnId: turn.id, status: turn.status }
    } catch (error) {
      if (this.#uncertain || this.#delivery) this.#disconnect('codex_delivery_uncertain')
      throw error
    } finally { this.#operation = false }
  }

  close() { this.#revoked = true; this.#disconnect('codex_binding_revoked') }

  async #journalCall(method, input) {
    let timer
    try {
      return await Promise.race([Promise.resolve().then(() => this.#journal[method](input)),
        new Promise((resolve, reject) => { timer = setTimeout(() => reject(failure('codex_delivery_storage_unavailable')), this.#timeout) })])
    } catch { throw failure('codex_delivery_storage_unavailable') }
    finally { clearTimeout(timer) }
  }

  #validateReceipt(receipt, privateToken) {
    if (!object(receipt) || receipt.desktop_thread_id !== this.#threadId || !identifier(receipt.request_id) ||
        typeof receipt.send_allowed !== 'boolean' || !['reserved', 'acknowledged', 'settled'].includes(receipt.state) ||
        (receipt.state === 'reserved' ? receipt.turn_id !== null || receipt.turn_status !== null :
          !identifier(receipt.turn_id) || (receipt.state === 'acknowledged' ? receipt.turn_status !== 'inProgress' : !TERMINAL.has(receipt.turn_status))) ||
        (privateToken && (typeof receipt.receipt_token !== 'string' || receipt.receipt_token.length !== 64 || !/^[a-f0-9]{64}$/.test(receipt.receipt_token)))) throw failure('codex_delivery_journal_invalid')
  }

  async #recordDelivery(turnId, status) {
    const operation = this.#journalWrites.then(async () => {
      while (this.#delivery) {
        const current = this.#delivery
        const effectiveStatus = this.#terminalId === turnId ? this.#terminalStatus : status
        const result = await this.#journalCall('record', { request_id: current.request_id, desktop_thread_id: this.#threadId,
          receipt_token: current.receipt_token, turn_id: turnId, turn_status: effectiveStatus })
        this.#validateReceipt(result, false)
        if (result.request_id !== current.request_id || result.turn_id !== turnId || result.turn_status !== effectiveStatus || result.send_allowed) throw failure('codex_delivery_journal_invalid')
        this.#delivery = TERMINAL.has(effectiveStatus) ? null : { ...current, ...result }
        if (!this.#delivery && this.#transport && !this.#revoked && this.#phase === 'confirming') {
          this.#phase = 'idle'; this.#uncertain = false
        }
        // Completion can arrive while the acknowledgement is being persisted.
        if (TERMINAL.has(effectiveStatus) || this.#terminalId !== turnId) return
      }
    })
    this.#journalWrites = operation.catch(() => {})
    return operation
  }

  #threadState(data) {
    if (data?.thread?.id !== this.#threadId || !THREAD_STATES.has(data.thread.status?.type)) throw failure('codex_thread_identity_mismatch')
    return data.thread.status.type
  }

  #turn(turn) {
    if (!object(turn) || !identifier(turn.id) || !['inProgress', ...TERMINAL].includes(turn.status)) throw failure('codex_turn_response_invalid')
    return { id: turn.id, status: turn.status }
  }

  #trackTurn(turn) {
    if (TERMINAL.has(turn.status)) {
      this.#terminalId = turn.id; this.#terminalStatus = turn.status; this.#turnId = null
      this.#phase = this.#delivery ? 'confirming' : 'idle'; this.#uncertain = Boolean(this.#delivery); this.#approvals.clear()
    } else { this.#turnId = turn.id; this.#phase = 'running' }
  }

  #rpc(method, params) {
    if (!this.#transport) return Promise.reject(failure('codex_transport_disconnected'))
    if (this.#pending.size >= 4) return Promise.reject(failure('codex_protocol_busy'))
    const id = `${this.#prefix}-${++this.#sequence}`
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => this.#disconnect('codex_response_timeout'), this.#timeout)
      this.#pending.set(id, { resolve, reject, timer })
      try { this.#send({ id, method, params }) } catch { this.#disconnect('codex_transport_disconnected') }
    })
  }

  #send(message) {
    if (!this.#transport || this.#revoked) throw failure('codex_transport_disconnected')
    // Transport must report send failures by throwing or emitting error/close.
    this.#transport.send(JSON.stringify(message))
  }

  #receive(frame, generation) {
    if (generation !== this.#generation || this.#revoked) return
    if (typeof frame !== 'string' || Buffer.byteLength(frame) > MAX_FRAME) { this.#disconnect('codex_frame_invalid'); return }
    let message
    try { message = JSON.parse(frame) } catch { this.#disconnect('codex_frame_invalid'); return }
    if (!object(message)) { this.#disconnect('codex_frame_invalid'); return }
    if (typeof message.method !== 'string') {
      const pending = this.#pending.get(message.id)
      if (!pending) return
      this.#pending.delete(message.id); clearTimeout(pending.timer)
      if (Object.hasOwn(message, 'error') || !Object.hasOwn(message, 'result')) pending.reject(failure('codex_request_rejected'))
      else pending.resolve(message.result)
      return
    }
    const params = message.params
    if (!object(params) || params.threadId !== this.#threadId) return
    const isRequest = Object.hasOwn(message, 'id')
    if (isRequest ? !APPROVALS.has(message.method) : !EVENTS.has(message.method)) return
    const bytes = Buffer.byteLength(frame)
    if (this.#queuedCount >= 128 || this.#queuedBytes + bytes > MAX_QUEUED_BYTES) { this.#disconnect('codex_stream_overflow'); return }
    this.#queuedCount++; this.#queuedBytes += bytes
    this.#queue = this.#queue.then(async () => {
      if (generation !== this.#generation || this.#revoked) return
      await this.#guard('event')
      if (generation !== this.#generation || this.#revoked) return
      const event = this.#event(message)
      if (event) {
        if (event.type === 'turn_completed' && this.#delivery) {
          // Notifications can precede the turn/start response. Keep completion
          // private until that response binds it to this delivery and storage
          // confirms it. Waiting for the response inside this queue deadlocks.
          if (this.#delivery.turn_id === null) { this.#deferredCompletion = event; return }
          if (this.#delivery.turn_id !== event.turnId) throw failure('codex_turn_identity_mismatch')
          await this.#recordDelivery(event.turnId, event.status)
        }
        await this.#publish(event, generation)
      }
    }).catch(() => this.#disconnect('codex_event_failed')).finally(() => { this.#queuedCount--; this.#queuedBytes -= bytes })
  }

  async #publish(event, generation) {
    if (generation !== this.#generation || !this.#transport || this.#revoked) return
    // Storage and event authorization are asynchronous. Recheck after storage
    // so a revoked session or detached transport cannot disclose late output.
    await this.#guard('event')
    if (generation !== this.#generation || !this.#transport || this.#revoked) return
    let timer
    try {
      await Promise.race([Promise.resolve().then(() => this.#onEvent(Object.freeze(event))),
        new Promise((resolve, reject) => { timer = setTimeout(() => reject(failure('codex_event_timeout')), this.#timeout) })])
    } catch {
      this.#disconnect('codex_event_failed')
      throw failure('codex_event_failed')
    } finally { clearTimeout(timer) }
  }

  #event(message) {
    const p = message.params, method = message.method
    const base = { threadId: this.#threadId }
    if (Object.hasOwn(message, 'id')) {
      if (!this.#turnId || p.turnId !== this.#turnId || this.#approvals.size >= 64) return null
      const requestId = message.id
      if (!(typeof requestId === 'string' && identifier(requestId)) && !Number.isSafeInteger(requestId)) return null
      if (this.#approvals.has(requestId)) return null
      this.#approvals.set(requestId, this.#turnId)
      // Approvals stay in Desktop. Never auto-accept, change policies, expose
      // credential-bearing commands, or compete with Desktop to answer them.
      return { ...base, type: 'approval_required', turnId: this.#turnId, requestId, kind: APPROVALS.get(method), reviewInDesktop: true }
    }
    if (method === 'serverRequest/resolved') {
      if (!this.#approvals.has(p.requestId)) return null
      this.#approvals.delete(p.requestId)
      return { ...base, type: 'approval_resolved', requestId: p.requestId }
    }
    if (method === 'thread/status/changed') {
      if (!THREAD_STATES.has(p.status?.type)) return null
      if (p.status.type !== 'idle' && !this.#turnId) this.#phase = 'needs_reconciliation'
      return { ...base, type: 'thread_status', status: p.status.type }
    }
    if (method === 'turn/started' || method === 'turn/completed') {
      const turn = this.#turn(p.turn)
      if (method === 'turn/started' && turn.status !== 'inProgress') return null
      if (method === 'turn/completed' && (!TERMINAL.has(turn.status) || this.#turnId !== turn.id)) return null
      if (this.#turnId && this.#turnId !== turn.id) { this.#phase = 'needs_reconciliation'; return null }
      if (method === 'turn/started' && this.#uncertain && !this.#turnId && this.#phase !== 'starting') return null
      // Late start events must not reactivate an already completed turn.
      if (method === 'turn/started' && this.#terminalId === turn.id) return null
      if (method === 'turn/started' && this.#delivery?.turn_id === null) {
        if (this.#provisionalTurnId && this.#provisionalTurnId !== turn.id) throw failure('codex_turn_identity_mismatch')
        this.#provisionalTurnId = turn.id
      }
      this.#trackTurn(turn)
      return { ...base, type: method === 'turn/started' ? 'turn_started' : 'turn_completed', turnId: turn.id, status: turn.status }
    }
    if (!this.#turnId || p.turnId !== this.#turnId) return null
    if (method === 'item/agentMessage/delta' && identifier(p.itemId) && typeof p.delta === 'string') {
      return { ...base, type: 'message_delta', turnId: this.#turnId, itemId: p.itemId, text: p.delta }
    }
    if (method === 'item/completed' && p.item?.type === 'agentMessage' && identifier(p.item.id) && typeof p.item.text === 'string') {
      return { ...base, type: 'message_completed', turnId: this.#turnId, itemId: p.item.id, text: p.item.text,
        phase: ['commentary', 'final_answer'].includes(p.item.phase) ? p.item.phase : null }
    }
    return null
  }

  #disconnect(code) {
    const transport = this.#transport, listeners = this.#listeners
    this.#transport = null; this.#listeners = null; this.#generation++
    if (this.#turnId || this.#delivery || this.#phase === 'starting' || this.#phase === 'stopping') this.#uncertain = true
    this.#phase = this.#revoked ? 'revoked' : this.#uncertain ? 'needs_reconciliation' : 'disconnected'
    this.#approvals.clear()
    this.#deferredCompletion = null
    for (const pending of this.#pending.values()) { clearTimeout(pending.timer); pending.reject(failure(code)) }
    this.#pending.clear()
    if (transport) {
      transport.off('message', listeners.receive); transport.off('close', listeners.closed); transport.off('error', listeners.closed)
      // An old transport can emit a late network error after close. Do not let
      // it crash the helper or affect a replacement connection.
      transport.on('error', ignoreTransportError)
      try { transport.close() } catch { /* Closing cannot turn an uncertain delivery into a retry. */ }
    }
  }
}

function ignoreTransportError() {}

module.exports = { CodexThreadProtocol }
