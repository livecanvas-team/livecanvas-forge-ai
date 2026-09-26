#!/usr/bin/env node
'use strict'

// Development diagnostic only. No conversation RPC, process startup, credential
// read or provider request. A local socket is not proof of Desktop attachment.
const fs = require('node:fs/promises')
const path = require('node:path')
const net = require('node:net')
const os = require('node:os')
const { ws: WebSocket } = require('../../mcp/node_modules/playwright-core/lib/utilsBundle.js')

const fail = code => Object.assign(new Error(code), { code })
const MAX_FRAME = 64 * 1024
const STARTUP_NOTIFICATIONS = new Set(['account/updated', 'remoteControl/status/changed', 'configWarning', 'warning'])

async function inspectSocket(socketPath, { files = fs, uid = process.getuid?.() } = {}) {
  if (!Number.isInteger(uid) || typeof socketPath !== 'string' || !path.isAbsolute(socketPath) ||
      socketPath.includes('\0') || socketPath !== path.normalize(socketPath)) throw fail('local_socket_path_invalid')
  try {
    const parent = await files.stat(path.dirname(socketPath))
    const target = await files.realpath(socketPath)
    const targetParent = await files.stat(path.dirname(target))
    const socket = await files.stat(target)
    // Codex can alias its canonical control socket into a short private /tmp
    // directory. Validate both private directories and the resolved socket.
    for (const dir of [parent, targetParent]) {
      if (!dir.isDirectory() || dir.uid !== uid || (dir.mode & 0o077) !== 0) throw fail('local_socket_not_private')
    }
    if (!socket.isSocket() || socket.uid !== uid || (socket.mode & 0o077) !== 0) throw fail('local_socket_not_private')
    return { target, dev: socket.dev, ino: socket.ino }
  } catch (error) {
    if (error.code === 'local_socket_not_private') throw error
    throw fail('local_socket_unavailable')
  }
}

async function probeUnixHandshake({ socketPath, timeoutMs = 5000, inspect = inspectSocket,
  createSocket = (target, options) => new WebSocket('ws://localhost/rpc', {
    ...options, createConnection: () => net.createConnection(target)
  }) } = {}) {
  const result = { diagnostic: 'codex_unix_handshake', handshake_verified: false,
    desktop_conversation_verified: false, frontend_chat_available: false,
    conversation_accessed: false, provider_request_sent: false, settings_changed: false,
    code: 'local_socket_unavailable' }
  if (!Number.isInteger(timeoutMs) || timeoutMs < 10 || timeoutMs > 10000) throw fail('diagnostic_timeout_invalid')
  let ws
  try {
    const before = await inspect(socketPath)
    await new Promise((resolve, reject) => {
      let done = false
      let initialized = false
      let received = false
      let notifications = 0
      const finish = (error) => {
        if (done) return
        done = true; clearTimeout(timer)
        if (error) reject(error); else resolve()
      }
      const timer = setTimeout(() => finish(fail('local_handshake_timeout')), timeoutMs)
      try {
        ws = createSocket(before.target, { handshakeTimeout: timeoutMs, maxPayload: MAX_FRAME,
          perMessageDeflate: false, followRedirects: false })
        ws.on('error', () => finish(fail('local_handshake_failed')))
        ws.on('close', () => finish(fail('local_handshake_closed')))
        ws.on('open', async () => {
          try {
            const after = await inspect(socketPath)
            if (done) return
            if (after.target !== before.target || after.ino !== before.ino || after.dev !== before.dev) {
              throw fail('local_socket_changed')
            }
            initialized = true
            ws.send(JSON.stringify({ id: 'lcfa-readonly-probe', method: 'initialize', params: {
              clientInfo: { name: 'livecanvas_bridge_probe', title: 'LiveCanvas Bridge read-only probe', version: '1.0.0' },
              capabilities: { experimentalApi: false }
            } }), error => { if (error) finish(fail('local_handshake_failed')) })
          } catch (error) { finish(fail(error.code === 'local_socket_changed' ? error.code : 'local_socket_unavailable')) }
        })
        ws.on('message', (raw, isBinary) => {
          if (done) return
          try {
            if (isBinary || Buffer.byteLength(raw) > MAX_FRAME || !initialized) throw Error()
            const message = JSON.parse(raw.toString())
            // These unsolicited notifications can race the initialize reply or
            // the initialized write callback. Discard their private bodies;
            // never interpret them as a response or enable Remote from them.
            if (message && !Object.hasOwn(message, 'id') && !Object.hasOwn(message, 'result') &&
                !Object.hasOwn(message, 'error') && STARTUP_NOTIFICATIONS.has(message.method)) {
              if (++notifications > 8) throw Error()
              return
            }
            if (received) throw Error()
            // Requests, unknown notifications and invalid replies remain fatal.
            if (!message || message.id !== 'lcfa-readonly-probe' || Object.hasOwn(message, 'method') ||
                Object.hasOwn(message, 'error') || typeof message.result?.userAgent !== 'string' ||
                message.result.userAgent.length > 4096 || message.result.platformFamily !== 'unix' ||
                !['macos', 'linux'].includes(message.result.platformOs)) throw Error()
            received = true
            ws.send(JSON.stringify({ method: 'initialized', params: {} }), error => {
              finish(error ? fail('local_handshake_failed') : null)
            })
          } catch { finish(fail('local_handshake_invalid')) }
        })
      } catch { finish(fail('local_handshake_failed')) }
    })
    result.handshake_verified = true
    result.code = 'desktop_binding_unverified'
  } catch (error) {
    const codes = new Set(['local_socket_path_invalid', 'local_socket_not_private', 'local_socket_unavailable',
      'local_socket_changed', 'local_handshake_timeout', 'local_handshake_failed', 'local_handshake_closed', 'local_handshake_invalid'])
    result.code = codes.has(error.code) ? error.code : 'local_handshake_failed'
  } finally { try { ws?.terminate() } catch { /* Never echo socket errors. */ } }
  return result
}

if (require.main === module) {
  const socketPath = path.join(os.homedir(), '.codex', 'app-server-control', 'app-server-control.sock')
  probeUnixHandshake({ socketPath }).then(result => {
    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`)
    process.exitCode = 2 // This diagnostic cannot qualify Desktop or frontend chat.
  }).catch(() => { process.stderr.write('Read-only diagnostic failed.\n'); process.exitCode = 1 })
}

module.exports = { inspectSocket, probeUnixHandshake }
