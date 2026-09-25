const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const os = require('node:os')
const { SessionAuth } = require('../src/session-auth')
const { WPClient } = require('../src/wp-client')

async function main() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'forge-auth-test-'))
  const originalFetch = global.fetch
  const config = { restBase: 'https://example.test/wp-json/lcfa/v1/', siteFingerprint: 'site-1', projectLabel: 'project', agent: 'cursor', connectionAttempt: 'a'.repeat(32), pairingScopes: 'read,preview,write,media,theme_files,debug,cache,seo' }
  let allowed = false
  let expires = false
  const calls = []
  global.fetch = async (url, options) => {
    calls.push({ url: String(url), ...options })
    let data
    if (String(url).endsWith('pairing/start')) {
      assert.equal(JSON.parse(options.body).connection_attempt, config.connectionAttempt)
      data = { ok: true, pairing_id: 'pair-1', device_secret: 'device-secret', user_code: 'CODE-1234', verification_url: 'https://example.test/wp-admin/', expires_at: '2999-01-01T00:00:00Z' }
    } else if (String(url).endsWith('pairing/status')) {
      assert.equal(options.method, 'POST')
      assert.equal(JSON.parse(options.body).device_secret, 'device-secret')
      data = expires ? { ok: false, status: 'expired' } : allowed ? { ok: true, status: 'approved', session_token: 'session-secret', expires_at: '2999-01-01T00:00:00Z' } : { ok: true, status: 'pending' }
    } else {
      assert.equal(options.headers['X-LCFA-Connection-Attempt'], config.connectionAttempt)
      assert.equal(options.headers['X-LCFA-MCP-Session'], 'session-secret')
      data = { connection_handoff: { site_fingerprint: 'site-1' } }
    }
    return { ok: true, text: async () => JSON.stringify(data) }
  }
  try {
    const auth = new SessionAuth({ ...config })
    const legacy = new SessionAuth({ ...config, connectionAttempt: '' })
    assert.notEqual(auth.cachePath, legacy.cachePath, 'New consent must not reuse an older scoped session.')
    auth.cachePath = path.join(root, 'auth.json')
    let prompts = 0
    const ready = await auth.waitForAuthorization({ intervalMs: 1, timeoutMs: 1000, onPending: () => { prompts++; allowed = true } })
    assert.ok(ready.ok)
    assert.equal(prompts, 1)
    assert.ok(!calls.some(call => call.url.includes('device-secret')))
    const client = new WPClient({ ...config })
    client.sessionAuth.cachePath = auth.cachePath
    assert.equal((await client.getConnectionHandoff()).connection_handoff.site_fingerprint, 'site-1')
    const expired = new SessionAuth({ ...config })
    expired.cachePath = path.join(root, 'expired.json')
    allowed = false
    expires = true
    const failed = await expired.waitForAuthorization({ intervalMs: 1, timeoutMs: 1000 })
    assert.equal(failed.status, 'pairing_failed')
    assert.equal(calls.filter(call => call.url.endsWith('pairing/start')).length, 2, 'An expired attempt must not loop creating new pairings.')
    const waiting = new SessionAuth({ ...config })
    waiting.resolve = async () => ({ ok: false, status: 'pairing_pending', pairing_id: 'wait' })
    assert.equal((await waiting.waitForAuthorization({ intervalMs: 1, timeoutMs: 10 })).status, 'authorization_timeout')
    const controller = new AbortController()
    controller.abort()
    assert.equal((await waiting.waitForAuthorization({ signal: controller.signal })).status, 'cancelled')
    global.fetch = async (url, options) => new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => reject(options.signal.reason), { once: true })
    })
    const slow = new SessionAuth({ ...config })
    slow.cachePath = path.join(root, 'slow.json')
    const started = Date.now()
    assert.equal((await slow.waitForAuthorization({ timeoutMs: 30 })).status, 'authorization_timeout')
    assert.ok(Date.now() - started < 1000, 'The authorization deadline must interrupt an in-flight fetch.')
    const duringFetch = new AbortController()
    const abortTimer = setTimeout(() => duringFetch.abort(), 20)
    assert.equal((await slow.waitForAuthorization({ timeoutMs: 1000, signal: duringFetch.signal })).status, 'cancelled')
    clearTimeout(abortTimer)
    console.log('PASS: isolated consent cache, automatic approval polling, POST secrets, proof header, bounded timeout and cancellation')
  } finally { global.fetch = originalFetch; fs.rmSync(root, { recursive: true, force: true }) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
