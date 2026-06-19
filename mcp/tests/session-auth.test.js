const assert = require('node:assert')
const http = require('node:http')
const os = require('node:os')
const path = require('node:path')
const fs = require('node:fs')
const { WPClient } = require('../src/wp-client')

async function main() {
  const requests = []
  let approved = false

  const server = http.createServer((req, res) => {
    let body = ''
    req.on('data', (chunk) => {
      body += chunk
    })
    req.on('end', () => {
      requests.push({
        method: req.method,
        url: req.url,
        headers: req.headers,
        body
      })

      if (req.url === '/wp-json/lcfa/v1/mcp/pairing/start' && req.method === 'POST') {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({
          ok: true,
          pairing_id: 'pair_test',
          device_secret: 'dev_secret',
          user_code: 'ABCD-1234',
          verification_url: 'http://127.0.0.1/admin.php?page=lcfa-dashboard&tab=connections',
          expires_at: '2999-01-01T00:00:00Z'
        }))
        return
      }

      if (req.url && req.url.startsWith('/wp-json/lcfa/v1/mcp/pairing/status')) {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify(approved
          ? {
              ok: true,
              status: 'approved',
              session_id: 'sess_test',
              session_token: 'session_secret',
              expires_at: '2999-01-01T00:00:00Z'
            }
          : {
              ok: true,
              status: 'pending',
              user_code: 'ABCD-1234',
              expires_at: '2999-01-01T00:00:00Z'
            }))
        return
      }

      if (req.url === '/wp-json/lcfa/v1/snapshot') {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ snapshot: { ok: true } }))
        return
      }

      res.writeHead(404, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ ok: false }))
    })
  })

  const port = await listen(server)
  const cacheHome = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-session-auth-'))
  const originalHome = process.env.HOME
  process.env.HOME = cacheHome

  try {
    const config = {
      restBase: `http://127.0.0.1:${port}/wp-json/lcfa/v1/`,
      token: '',
      sessionToken: '',
      siteFingerprint: 'site-fp',
      projectLabel: 'Remote Example',
      agent: 'codex',
      transport: 'stdio'
    }
    const client = new WPClient(config)

    const pending = await client.getSnapshot()
    assert.strictEqual(pending.status, 'pairing_pending', 'first protected request should create a pending pairing')
    assert.strictEqual(pending.user_code, 'ABCD-1234', 'pending pairing should return the user code')

    approved = true
    const snapshot = await client.getSnapshot()
    assert.deepStrictEqual(snapshot, { snapshot: { ok: true } }, 'approved pairing should allow the protected request')

    const snapshotRequest = requests.find((request) => request.url === '/wp-json/lcfa/v1/snapshot')
    assert.strictEqual(snapshotRequest.headers['x-lcfa-mcp-session'], 'session_secret', 'protected requests should use the AI Bridge session header')
    assert.ok(!snapshotRequest.headers['x-lcfa-mcp-token'], 'protected requests should not send a legacy MCP token')
  } finally {
    process.env.HOME = originalHome
    server.close()
  }
}

function listen(server) {
  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => {
      resolve(server.address().port)
    })
  })
}

main()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
