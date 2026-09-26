'use strict'

const { test } = require('node:test')
const assert = require('node:assert/strict')
const { EventEmitter } = require('node:events')
const { inspectSocket, probeUnixHandshake } = require('../../tests/integration/codex-unix-handshake')
const identity = { target: '/private-fixture/server.sock', dev: 1, ino: 2 }
const reply = { id: 'lcfa-readonly-probe', result: { userAgent: 'fixture private metadata',
  codexHome: '/private-data', platformFamily: 'unix', platformOs: 'macos' } }

function fixture(options = {}) {
  const calls = []
  const socket = new EventEmitter()
  socket.terminated = false
  socket.terminate = () => { socket.terminated = true }
  socket.send = (text, cb) => {
    calls.push(JSON.parse(text)); cb?.()
    if (calls.length === 1 && options.reply !== false) queueMicrotask(() => socket.emit('message',
      Buffer.from(options.raw ?? JSON.stringify(options.reply ?? reply)), options.binary ?? false))
  }
  const createSocket = (target, config) => {
    assert.equal(target, identity.target)
    assert.equal(config.maxPayload, 65536)
    assert.equal(config.followRedirects, false)
    assert.equal(config.perMessageDeflate, false)
    queueMicrotask(() => socket.emit(options.error ? 'error' : 'open', new Error('private failure')))
    return socket
  }
  return { socket, calls, createSocket, inspect: async () => identity }
}

test('Unix handshake sends only initialize/initialized and never qualifies Desktop', async () => {
  const f = fixture()
  const r = await probeUnixHandshake(f)
  assert.deepEqual(f.calls.map(x => x.method), ['initialize', 'initialized'])
  assert.equal(r.handshake_verified, true)
  assert.equal(r.code, 'desktop_binding_unverified')
  for (const key of ['desktop_conversation_verified', 'frontend_chat_available', 'conversation_accessed',
    'provider_request_sent', 'settings_changed']) assert.equal(r[key], false)
  assert.ok(!JSON.stringify(r).includes('private'))
  assert.equal(f.socket.terminated, true)
})

test('invalid, oversized, binary and unsolicited private frames fail closed', async () => {
  for (const opts of [{ raw: 'invalid private json' }, { raw: 'a'.repeat(65537) }, { binary: true },
    { reply: { ...reply, id: 'foreign' } }, { reply: { ...reply, method: 'account/private' } },
    { reply: { ...reply, error: { message: 'private error' } } },
    { reply: { ...reply, result: { userAgent: 'private' } } }]) {
    const f = fixture(opts)
    const r = await probeUnixHandshake(f)
    assert.equal(r.code, 'local_handshake_invalid')
    assert.equal(r.handshake_verified, false)
    assert.equal(f.calls.length, 1)
    assert.equal(f.socket.terminated, true)
    assert.ok(!JSON.stringify(r).includes('private'))
  }
})

test('socket replacement during connection prevents sending initialization', async () => {
  const f = fixture(); let reads = 0
  f.inspect = async () => ({ ...identity, ino: ++reads })
  const r = await probeUnixHandshake(f)
  assert.equal(r.code, 'local_socket_changed')
  assert.deepEqual(f.calls, [])
})

test('connection errors, close and timeouts are bounded and redacted', async () => {
  const failed = fixture({ error: true })
  assert.equal((await probeUnixHandshake(failed)).code, 'local_handshake_failed')
  const silent = fixture({ reply: false })
  assert.equal((await probeUnixHandshake({ ...silent, timeoutMs: 10 })).code, 'local_handshake_timeout')
  assert.equal(silent.socket.terminated, true)
  const closed = fixture({ reply: false })
  setImmediate(() => closed.socket.emit('close'))
  assert.equal((await probeUnixHandshake(closed)).code, 'local_handshake_closed')
})

test('late filesystem results after timeout cannot send a request', async () => {
  const f = fixture(); let reads = 0, release
  f.inspect = async () => ++reads === 1 ? identity : new Promise(resolve => { release = resolve })
  assert.equal((await probeUnixHandshake({ ...f, timeoutMs: 10 })).code, 'local_handshake_timeout')
  release(identity); await new Promise(resolve => setImmediate(resolve))
  assert.deepEqual(f.calls, [])
})

test('send callback failures do not become successful handshakes', async () => {
  const f = fixture()
  f.socket.send = (text, callback) => callback(new Error('private write error'))
  const r = await probeUnixHandshake(f)
  assert.equal(r.code, 'local_handshake_failed'); assert.equal(r.handshake_verified, false)
  assert.equal(f.socket.terminated, true)
})

test('a duplicate response before the final write completes fails closed', async () => {
  const f = fixture()
  f.socket.send = (text, callback) => {
    f.calls.push(JSON.parse(text))
    if (f.calls.length === 1) {
      callback()
      queueMicrotask(() => {
        f.socket.emit('message', Buffer.from(JSON.stringify(reply)), false)
        f.socket.emit('message', Buffer.from(JSON.stringify(reply)), false)
      })
    }
  }
  assert.equal((await probeUnixHandshake(f)).code, 'local_handshake_invalid')
  assert.equal(f.calls.length, 2)
})

test('bounded startup notifications never replace the initialization response or disclose metadata', async () => {
  for (const method of ['account/updated', 'remoteControl/status/changed', 'configWarning', 'warning']) {
    const f = fixture()
    f.socket.send = (text, callback) => {
      const message = JSON.parse(text); f.calls.push(message)
      const notification = Buffer.from(JSON.stringify({ method, params: { private: 'secret metadata' } }))
      if (message.method === 'initialize') {
        callback()
        queueMicrotask(() => {
          f.socket.emit('message', notification, false)
          f.socket.emit('message', Buffer.from(JSON.stringify(reply)), false)
        })
      } else {
        // Real servers can publish status before the initialized write callback.
        f.socket.emit('message', notification, false)
        callback()
      }
    }
    const r = await probeUnixHandshake(f)
    assert.equal(r.handshake_verified, true, method)
    assert.deepEqual(f.calls.map(m => m.method), ['initialize', 'initialized'])
    assert.ok(!JSON.stringify(r).includes('secret'))
    assert.equal(r.desktop_conversation_verified, false)
  }
})

test('startup notification floods, disguised requests and unknown notifications fail closed', async () => {
  for (const mode of ['flood', 'request', 'unknown']) {
    const f = fixture()
    f.socket.send = (text, callback) => {
      f.calls.push(JSON.parse(text)); callback()
      queueMicrotask(() => {
        const notification = { method: mode === 'unknown' ? 'account/unrecognized' : 'account/updated', params: {} }
        if (mode === 'request') notification.id = 'private-request'
        for (let i = 0; i < (mode === 'flood' ? 9 : 1); i++) {
          f.socket.emit('message', Buffer.from(JSON.stringify(notification)), false)
        }
      })
    }
    const r = await probeUnixHandshake(f)
    assert.equal(r.code, 'local_handshake_invalid')
    assert.equal(r.handshake_verified, false)
    assert.equal(f.calls.length, 1)
    assert.equal(f.socket.terminated, true)
  }
})

function fileFixture(overrides = {}) {
  const dir = { uid: 501, mode: 0o700, isDirectory: () => true }
  const socket = { uid: 501, mode: 0o600, isSocket: () => true, dev: 1, ino: 2 }
  return { uid: 501, files: {
    realpath: async () => identity.target,
    stat: async name => name === identity.target ? { ...socket, ...overrides.socket } : { ...dir, ...overrides.dir }
  } }
}

test('private canonical alias and resolved socket are accepted', async () => {
  assert.deepEqual(await inspectSocket('/canonical/server.sock', fileFixture()), identity)
})

test('wrong owner, permissive paths and non-sockets are rejected', async () => {
  for (const overrides of [{ dir: { uid: 502 } }, { socket: { uid: 502 } }, { dir: { mode: 0o750 } },
    { socket: { mode: 0o660 } }, { dir: { isDirectory: () => false } }, { socket: { isSocket: () => false } }]) {
    await assert.rejects(inspectSocket('/canonical/server.sock', fileFixture(overrides)), /local_socket_not_private/)
  }
})

test('invalid paths and absent Unix ownership support never open a socket', async () => {
  for (const socketPath of ['relative.sock', '/tmp/../server.sock', '/tmp/a\0b', null]) {
    await assert.rejects(inspectSocket(socketPath, fileFixture()), /local_socket_path_invalid/)
  }
  await assert.rejects(inspectSocket('/tmp/s.sock', { ...fileFixture(), uid: null }), /local_socket_path_invalid/)
  const f = fixture(); f.inspect = async () => { throw Error('private filesystem error') }
  assert.equal((await probeUnixHandshake(f)).handshake_verified, false)
  assert.deepEqual(f.calls, [])
})
