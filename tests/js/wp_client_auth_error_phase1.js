const assert = require('assert')
const http = require('http')
const { repoPath } = require('./test-paths.cjs')
const { WPClient } = require(repoPath('mcp', 'src', 'wp-client.js'))

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
      error.message.includes('coding agent project config') && error.message.includes('pair again'),
      'WPClient should provide client-neutral recovery guidance for rejected credentials'
    )
  } finally {
    server.close()
    console.log('PASS wp_client_auth_error_phase1')
  }
})
