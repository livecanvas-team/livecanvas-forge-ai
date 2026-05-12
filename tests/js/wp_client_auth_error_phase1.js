const assert = require('assert')
const http = require('http')
const { WPClient } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js')

const server = http.createServer((req, res) => {
  res.statusCode = 401
  res.setHeader('Content-Type', 'application/json')
  res.end(JSON.stringify({
    code: 'rest_forbidden',
    message: 'Sorry, you are not allowed to do that.',
    data: { status: 401 }
  }))
})

server.listen(0, '127.0.0.1', async () => {
  const address = server.address()
  const client = new WPClient({
    restBase: `http://127.0.0.1:${address.port}/wp-json/lcfa/v1/`,
    token: 'stale-token'
  })

  try {
    await client.getMcpStatus()
    assert.fail('WPClient should reject 401 REST responses')
  } catch (error) {
    assert.strictEqual(error.status, 401, 'WPClient should preserve the HTTP status code')
    assert.ok(
      error.message.includes('Sync Codex config') || error.message.includes('rotate the token'),
      'WPClient should explain that 401 usually means a stale MCP token'
    )
  } finally {
    server.close()
    console.log('PASS wp_client_auth_error_phase1')
  }
})
