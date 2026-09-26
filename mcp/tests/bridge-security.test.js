const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const crypto = require('node:crypto')
const http = require('node:http')
const { startBridgeServer } = require('../src/bridge-server')
const { readCapability, verifyIdentity } = require('../src/bridge-security')
const { WPClient } = require('../src/wp-client')
const VERSION = require('../package.json').version

async function main() {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-transport-fixture-'))
  fs.chmodSync(dir, 0o700)
  const wpRoot = path.join(dir, 'wordpress'); fs.mkdirSync(wpRoot)
  const tokenFile = path.join(dir, 'capability.json')
  const token = crypto.randomBytes(32).toString('base64url')
  const identity = {
    session_id: 'fixture-session', owner_user_id: 1, site_fingerprint: 'fixture-site',
    client: 'codex', scopes: ['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo'],
    required_runtime: VERSION, site_url: 'http://fixture.invalid/', wordpress_root: fs.realpathSync(wpRoot),
    expires_at: new Date(Date.now() + 3600000).toISOString()
  }
  const capability = { version: 1, token, expires_at: Math.floor(Date.now() / 1000) + 600, session_id: identity.session_id, site_fingerprint: identity.site_fingerprint, owner_user_id: 1 }
  const config = { host: '127.0.0.1', port: 0, bridgeTokenFile: tokenFile, siteFingerprint: identity.site_fingerprint, restBase: identity.site_url, agent: 'codex' }
  const write = (value = capability) => fs.writeFileSync(tokenFile, JSON.stringify(value), { mode: 0o600 })
  let server; let revoked = false; let identityOverride; let invoked = 0; let hold
  const client = { config: {}, getMcpStatus: async () => ({ mcp: {} }), getTransportIdentity: async () => {
    if (hold) await hold
    if (revoked) throw new Error('Secret fixture session revoked')
    return { ok: true, identity: identityOverride || identity }
  } }
  const tools = {
    list: () => ['get_write_context', 'asset_discovery', 'media_upload_local_assets', 'visual_check', 'visual_check_status', 'run_lc_command', 'get_frontend_prompt_request', 'unreviewed_future_tool'].map(name => ({ name })),
    invoke: async (name, args) => { invoked++; return { name, args } }
  }
  async function start() { server = await startBridgeServer({ config, client, tools }) }
  async function stop() { if (server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); server = null } }
  function request(url = '/health', { method = 'GET', headers = {}, body, auth = true } = {}) {
    return new Promise((resolve, reject) => {
      const req = http.request({ host: '127.0.0.1', port: server.address().port, path: url, method, headers: {
        ...(auth ? { Authorization: `Bearer ${token}` } : {}), ...headers
      } }, res => {
        let text = ''; res.on('data', chunk => { text += chunk }); res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, data: text ? JSON.parse(text) : null }))
      })
      req.on('error', reject); req.end(body)
    })
  }
  try {
    write()
    assert.equal(readCapability(tokenFile, identity).token, token)
    fs.chmodSync(tokenFile, 0o644)
    assert.throws(() => readCapability(tokenFile, identity), /private_bridge_capability_required/)
    fs.chmodSync(tokenFile, 0o600)
    fs.chmodSync(dir, 0o755)
    assert.throws(() => readCapability(tokenFile, identity), /private_bridge_capability_required/)
    fs.chmodSync(dir, 0o700)
    const link = path.join(dir, 'linked.json'); fs.symlinkSync(tokenFile, link)
    assert.throws(() => readCapability(link, identity), /private_bridge_capability_unavailable/)
    const webFile = path.join(wpRoot, 'capability.json'); fs.copyFileSync(tokenFile, webFile)
    assert.throws(() => readCapability(webFile, identity), /outside_webroot/)
    write({ ...capability, expires_at: 1 })
    assert.throws(() => readCapability(tokenFile, identity), /expired_bridge_capability/)
    write({ ...capability, owner_user_id: 2 })
    assert.throws(() => readCapability(tokenFile, identity), /identity_mismatch/)
    write()
    assert.throws(() => verifyIdentity({ ok: true, identity: { ...identity, required_runtime: 'old' } }, config), /update_required/)
    assert.throws(() => verifyIdentity({ ok: true, identity: { ...identity, scopes: ['read'] } }, config), /full_access/)
    await assert.rejects(startBridgeServer({ config: { ...config, host: '0.0.0.0' }, client, tools }), /loopback_required/)
    await start()
    assert.equal(client.config.transportBound, true)
    assert.equal((await request('/health', { auth: false })).status, 401)
    assert.equal((await request('/tools', { headers: { Authorization: 'Bearer wrong' } })).status, 401)
    assert.equal((await request('/health', { headers: { Host: 'rebound.invalid' } })).status, 403)
    for (const origin of ['null', 'http://evil.invalid', 'http://fixture.invalid.evil.test']) {
      const denied = await request('/health', { headers: { Origin: origin } })
      assert.equal(denied.status, 403); assert.equal(denied.headers['access-control-allow-origin'], undefined)
    }
    assert.equal((await request('/health', { headers: { 'Sec-Fetch-Mode': 'cors' } })).status, 403)
    assert.equal((await request('/health?token=never-use-url')).status, 403)
    const preflight = await request('/tools/call', { method: 'OPTIONS', auth: false, headers: { Origin: identity.site_url.slice(0, -1), 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'authorization, content-type' } })
    assert.equal(preflight.status, 204)
    assert.equal((await request('/tools/call', { method: 'OPTIONS', auth: false })).status, 403)
    const health = await request('/health', { headers: { Origin: 'http://fixture.invalid' } })
    assert.equal(health.status, 200); assert.equal(health.data.desktop_conversation_verified, false)
    assert.equal(health.headers['access-control-allow-origin'], 'http://fixture.invalid')
    assert.equal(health.headers['cache-control'], 'no-store')
    assert.deepEqual((await request('/tools')).data.tools, [{ name: 'get_write_context' }])
    assert.equal((await request('/snapshot')).status, 410)
    const call = payload => request('/tools/call', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
    assert.equal((await call({ name: 'asset_discovery', arguments: { path: '/private' } })).status, 403)
    assert.equal((await call({ name: 'unreviewed_future_tool' })).status, 403)
    assert.equal((await call({ name: 'get_write_context', arguments: [] })).status, 400)
    assert.equal((await call({ name: 'get_write_context', arguments: { target_id: 42 } })).status, 200)
    assert.equal(invoked, 1)
    assert.equal((await request('/tools/call', { method: 'POST', body: '{}' })).status, 415)
    assert.equal((await request('/tools/call', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{broken' })).status, 400)
    assert.equal((await request('/tools/call', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': 9 * 1024 * 1024 } })).status, 413)
    assert.equal((await request('/ws', { headers: { Connection: 'Upgrade', Upgrade: 'websocket' } })).status, 403)
    let release; hold = new Promise(resolve => { release = resolve })
    const waiting = Array.from({ length: 4 }, () => request())
    await new Promise(resolve => setTimeout(resolve, 50))
    assert.equal((await request()).status, 429)
    hold = null; release(); await Promise.all(waiting)
    revoked = true
    const rejection = await request()
    assert.equal(rejection.status, 403); assert.equal(rejection.data.code, 'bridge_session_unavailable')
    revoked = false
    assert.equal((await request()).status, 403, 'A rejected bound session cannot recover into another connection')
    await stop(); await start()
    write({ ...capability, token: crypto.randomBytes(32).toString('base64url') })
    assert.equal((await request()).data.code, 'bridge_capability_changed')
    write(); assert.equal((await request()).status, 403)
    await stop(); await start()
    fs.unlinkSync(tokenFile)
    assert.equal((await request()).status, 403)
    write(); assert.equal((await request()).status, 403)
    await stop(); await start()
    identityOverride = { ...identity, session_id: 'another-session' }
    assert.equal((await request()).data.code, 'bridge_identity_changed')
    assert.equal(invoked, 1, 'Rejected requests must not reach tools')

    // Bound WP clients must not invalidate, restart pairing or follow redirects.
    const wp = new WPClient({ restBase: 'http://fixture.invalid/', agent: 'codex', sessionToken: 'fixture', transportBound: true })
    let invalidations = 0; let fetches = 0; const originalFetch = global.fetch
    wp.sessionAuth.invalidateSession = () => { invalidations++ }
    global.fetch = async (url, options) => { fetches++; assert.equal(options.redirect, 'error'); return { ok: false, status: 403, text: async () => '{"message":"Revoked"}' } }
    try {
      await assert.rejects(wp.getTransportIdentity(), /Revoked/)
      assert.equal(fetches, 1); assert.equal(invalidations, 0)
      wp.config.sessionToken = ''
      await assert.rejects(wp.getTransportIdentity(), /owned_session_required/)
      assert.equal(fetches, 1)
    } finally { global.fetch = originalFetch }
    console.log('PASS authenticated loopback, origin/host isolation, explicit tool allowlist, bounded requests, revocation and no automatic re-pairing')
  } finally { await stop(); fs.rmSync(dir, { recursive: true, force: true }) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
