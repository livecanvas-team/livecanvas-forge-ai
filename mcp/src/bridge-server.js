const http = require('node:http')
const { BridgeSecurity, fail } = require('./bridge-security')
const VERSION = require('../package.json').version
const MAX_BODY = 8 * 1024 * 1024
// Explicit reviewed subset. New registry tools are private by default. Local
// file discovery, compilers, worker claims and generic commands stay off HTTP.
const BROWSER_TOOLS = new Set([
  'list_workflows', 'read_workflow', 'get_write_context', 'get_context',
  'get_theme_context', 'get_page_html', 'get_acf_fields', 'list_lc_blocks',
  'list_changesets', 'undo_changeset', 'update_discussion_settings',
  'content_patch_preview', 'content_patch_apply', 'theme_file_read',
  'theme_file_preview_write', 'theme_file_write', 'validate_markup_for_framework'
])

async function startBridgeServer({ client, tools, config }) {
  const security = await BridgeSecurity.create(config, client)
  client.config.transportBound = true
  const visible = () => tools.list().filter(tool => BROWSER_TOOLS.has(tool.name))
  let active = 0
  const server = http.createServer({ maxHeaderSize: 8192 }, async (request, response) => {
    response.setHeader('Cache-Control', 'no-store')
    response.setHeader('X-Content-Type-Options', 'nosniff')
    let counted = false
    try {
      const port = server.address().port
      security.checkAddress(request, port)
      if (request.headers.origin) {
        response.setHeader('Access-Control-Allow-Origin', security.origin)
        response.setHeader('Vary', 'Origin')
      }
      if (request.method === 'OPTIONS') {
        if (request.headers.origin !== security.origin ||
            !['GET', 'POST'].includes(request.headers['access-control-request-method']) ||
            String(request.headers['access-control-request-headers'] || '').toLowerCase().split(',').some(name => !['', 'authorization', 'content-type'].includes(name.trim()))) fail('bridge_preflight_rejected')
        response.setHeader('Access-Control-Allow-Methods', 'GET, POST')
        response.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
        response.writeHead(204); response.end(); return
      }
      if (active >= 4) fail('bridge_busy', 429)
      active++; counted = true
      await security.authorize(request, port)
      if (request.method === 'GET' && request.url === '/health') {
        sendJson(response, 200, { ok: true, transport: 'authenticated_loopback', version: VERSION, desktop_conversation_verified: false }); return
      }
      if (request.method === 'GET' && request.url === '/tools') {
        sendJson(response, 200, { ok: true, tools: visible() }); return
      }
      if (request.method === 'POST' && request.url === '/tools/call') {
        const payload = await readJsonBody(request)
        if (typeof payload.name !== 'string' || !visible().some(tool => tool.name === payload.name)) fail('bridge_tool_unavailable', 403)
        if (payload.arguments !== undefined && (!payload.arguments || typeof payload.arguments !== 'object' || Array.isArray(payload.arguments))) fail('bridge_arguments_invalid', 400)
        // Recheck after receiving the body, before a potentially mutating call.
        await security.authorize(request, port)
        const result = await tools.invoke(payload.name, payload.arguments || {})
        sendJson(response, 200, { ok: true, result }); return
      }
      sendJson(response, 410, { ok: false, code: 'legacy_bridge_route_retired', message: 'Use the authenticated /tools and /tools/call endpoints.' })
    } catch (error) {
      // Do not keep unread or oversized request bodies alive after rejection.
      response.setHeader('Connection', 'close')
      response.once('finish', () => request.socket.destroy())
      const status = Number.isInteger(error.status) ? error.status : 503
      const code = /^bridge_|^(owned_session|required_|approved_|private_bridge|invalid_bridge|expired_bridge)/.test(error.code || '') ? error.code : 'bridge_operation_failed'
      sendJson(response, status, { ok: false, code })
    } finally { if (counted) active-- }
  })
  // The former hand-written, unauthenticated WebSocket RPC is retired.
  // Streaming requires a separately qualified owner-bound desktop transport.
  server.on('upgrade', (request, socket) => {
    socket.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\nContent-Length: 0\r\n\r\n')
  })
  server.maxConnections = 32
  server.requestTimeout = 15000
  server.headersTimeout = 10000
  server.keepAliveTimeout = 1000
  await new Promise((resolve, reject) => {
    server.once('error', reject)
    server.listen(config.port, '127.0.0.1', resolve)
  })
  process.stderr.write('[livecanvas-forge-mcp] authenticated loopback listener ready; desktop chat is not connected\n')
  return server
}

function sendJson(response, status, data) {
  if (response.destroyed || response.writableEnded) return
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' })
  response.end(JSON.stringify(data))
}

function readJsonBody(request) {
  if (!/^application\/json(?:\s*;|$)/i.test(request.headers['content-type'] || '') || request.headers['content-encoding']) fail('bridge_json_required', 415)
  if (Number(request.headers['content-length']) > MAX_BODY) fail('bridge_body_too_large', 413)
  return new Promise((resolve, reject) => {
    const chunks = []; let bytes = 0
    const timer = setTimeout(() => reject(Object.assign(new Error('bridge_body_timeout'), { code: 'bridge_body_timeout', status: 408 })), 10000)
    const cleanup = () => clearTimeout(timer)
    request.once('end', cleanup); request.once('error', cleanup); request.once('aborted', cleanup); request.once('close', cleanup)
    request.on('data', chunk => {
      bytes += chunk.length
      if (bytes > MAX_BODY) { cleanup(); reject(Object.assign(new Error('bridge_body_too_large'), { code: 'bridge_body_too_large', status: 413 })); request.pause(); return }
      chunks.push(chunk)
    })
    request.on('end', () => {
      try {
        const value = JSON.parse(Buffer.concat(chunks).toString('utf8'))
        if (!value || typeof value !== 'object' || Array.isArray(value)) fail('bridge_json_invalid', 400)
        resolve(value)
      } catch (error) { reject(Object.assign(new Error('bridge_json_invalid'), { code: 'bridge_json_invalid', status: 400 })) }
    })
    request.on('error', reject)
    request.on('aborted', () => reject(Object.assign(new Error('bridge_body_aborted'), { code: 'bridge_body_aborted', status: 400 })))
  })
}

module.exports = { startBridgeServer }
