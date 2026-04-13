const assert = require('node:assert/strict')
const http = require('node:http')
const { spawn } = require('node:child_process')

async function run() {
  const requests = []
  const server = http.createServer((req, res) => {
    requests.push(req.url)

    if (req.url === '/wp-json/lcfa/v1/mcp/workspace-root/' || req.url === '/wp-json/lcfa/v1/mcp/workspace-root') {
      setTimeout(() => {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ ok: true }))
      }, 2000)
      return
    }

    if (req.url === '/wp-json/lcfa/v1/mcp/status/' || req.url === '/wp-json/lcfa/v1/mcp/status') {
      setTimeout(() => {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ mcp: { endpoint: 'ws://127.0.0.1:7681' } }))
      }, 2000)
      return
    }

    res.writeHead(404, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ ok: false }))
  })

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
  const address = server.address()
  const restBase = `http://127.0.0.1:${address.port}/wp-json/lcfa/v1/`

  const child = spawn('node', [
    '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js',
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: '/Users/commander/Studio/consultala',
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: '/Users/commander/Studio/consultala'
    },
    stdio: ['pipe', 'pipe', 'pipe']
  })

  const initializeMessage = Buffer.from(JSON.stringify({
    jsonrpc: '2.0',
    id: 1,
    method: 'initialize',
    params: {
      protocolVersion: '2024-11-05',
      capabilities: {},
      clientInfo: { name: 'test-client', version: '1.0.0' }
    }
  }))

  child.stdin.write(Buffer.concat([
    Buffer.from(`Content-Length: ${initializeMessage.length}\r\n\r\n`),
    initializeMessage
  ]))

  const output = await waitForStdout(child, 800)
  await waitForRequest(requests, '/wp-json/lcfa/v1/mcp/workspace-root', 3000)

  child.kill('SIGKILL')
  server.close()

  assert.match(output, /"protocolVersion":"2024-11-05"/, 'stdio server should answer initialize without waiting for REST preflight calls')
  assert.ok(requests.includes('/wp-json/lcfa/v1/mcp/workspace-root') || requests.includes('/wp-json/lcfa/v1/mcp/workspace-root/'), 'bridge should still attempt workspace sync')

  const lfChild = spawn('node', [
    '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js',
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: '/Users/commander/Studio/consultala',
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: '/Users/commander/Studio/consultala'
    },
    stdio: ['pipe', 'pipe', 'pipe']
  })

  lfChild.stdin.write(Buffer.concat([
    Buffer.from(`Content-Length: ${initializeMessage.length}\n\n`),
    initializeMessage
  ]))

  const lfOutput = await waitForStdout(lfChild, 800)
  lfChild.kill('SIGKILL')

  assert.match(lfOutput, /"protocolVersion":"2024-11-05"/, 'stdio server should also accept LF-only headers used by some MCP clients')

  const ndjsonChild = spawn('node', [
    '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js',
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: '/Users/commander/Studio/consultala',
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: '/Users/commander/Studio/consultala'
    },
    stdio: ['pipe', 'pipe', 'pipe']
  })

  ndjsonChild.stdin.write(Buffer.concat([
    Buffer.from(JSON.stringify({
      jsonrpc: '2.0',
      id: 0,
      method: 'initialize',
      params: {
        protocolVersion: '2025-11-25',
        capabilities: {},
        clientInfo: { name: 'opencode', version: '1.4.3' }
      }
    })),
    Buffer.from('\n')
  ]))

  const ndjsonOutput = await waitForStdout(ndjsonChild, 800)
  ndjsonChild.kill('SIGKILL')

  assert.match(ndjsonOutput, /"protocolVersion":"2025-11-25"/, 'stdio server should negotiate the client-requested protocol version for newline-delimited JSON-RPC clients')
  assert.ok(!ndjsonOutput.includes('Content-Length:'), 'newline-delimited JSON-RPC clients should receive newline-delimited responses, not Content-Length framing')
}

function waitForStdout(child, timeoutMs) {
  return new Promise((resolve, reject) => {
    let stdout = ''
    let stderr = ''

    const timer = setTimeout(() => {
      cleanup()
      reject(new Error(`Timed out after ${timeoutMs}ms waiting for initialize response. stderr=${stderr}`))
    }, timeoutMs)

    const cleanup = () => {
      clearTimeout(timer)
      child.stdout.off('data', onStdout)
      child.stderr.off('data', onStderr)
      child.off('exit', onExit)
    }

    const onStdout = (chunk) => {
      stdout += chunk.toString('utf8')
      if (stdout.includes('"protocolVersion":"')) {
        cleanup()
        resolve(stdout)
      }
    }

    const onStderr = (chunk) => {
      stderr += chunk.toString('utf8')
    }

    const onExit = (code) => {
      cleanup()
      reject(new Error(`Child exited before initialize response (code ${code}). stderr=${stderr}`))
    }

    child.stdout.on('data', onStdout)
    child.stderr.on('data', onStderr)
    child.on('exit', onExit)
  })
}

function waitForRequest(requests, expectedPath, timeoutMs) {
  return new Promise((resolve, reject) => {
    const startedAt = Date.now()

    const tick = () => {
      if (requests.includes(expectedPath) || requests.includes(`${expectedPath}/`)) {
        resolve()
        return
      }

      if (Date.now() - startedAt >= timeoutMs) {
        reject(new Error(`Timed out after ${timeoutMs}ms waiting for request ${expectedPath}`))
        return
      }

      setTimeout(tick, 25)
    }

    tick()
  })
}

run()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
