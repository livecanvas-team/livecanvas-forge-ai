const assert = require('node:assert/strict')
const fs = require('node:fs')
const http = require('node:http')
const os = require('node:os')
const { spawn } = require('node:child_process')
const path = require('node:path')
const packageVersion = require('../package.json').version
const initializeTimeoutMs = 6000
const delayedPreflightMs = 10000
const repoRoot = path.resolve(__dirname, '..', '..')
const mcpScript = path.join(repoRoot, 'mcp', 'bin', 'livecanvas-forge-mcp.js')
const wpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-stdio-wordpress-'))

fs.mkdirSync(path.join(wpRoot, 'wp-content'))
fs.writeFileSync(path.join(wpRoot, 'wp-config.php'), '<?php // MCP startup fixture.\n')
process.on('exit', () => fs.rmSync(wpRoot, { recursive: true, force: true }))

async function run() {
  const requests = []
  const server = http.createServer((req, res) => {
    requests.push(req.url)

    if (req.url === '/wp-json/lcfa/v1/mcp/workspace-root/' || req.url === '/wp-json/lcfa/v1/mcp/workspace-root') {
      const timer = setTimeout(() => {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ ok: true }))
      }, delayedPreflightMs)
      timer.unref()
      return
    }

    if (req.url === '/wp-json/lcfa/v1/mcp/status/' || req.url === '/wp-json/lcfa/v1/mcp/status') {
      const timer = setTimeout(() => {
        res.writeHead(200, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ mcp: { endpoint: 'ws://127.0.0.1:7681' } }))
      }, delayedPreflightMs)
      timer.unref()
      return
    }

    res.writeHead(404, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ ok: false }))
  })

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
  const address = server.address()
  const restBase = `http://127.0.0.1:${address.port}/wp-json/lcfa/v1/`

  const child = spawn(process.execPath, [
    mcpScript,
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: repoRoot,
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: wpRoot
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

  const output = await waitForStdout(child, initializeTimeoutMs)
  await waitForRequest(requests, '/wp-json/lcfa/v1/mcp/workspace-root', 3000)

  child.kill('SIGKILL')
  server.close()

  assert.match(output, /"protocolVersion":"2024-11-05"/, 'stdio server should answer initialize without waiting for REST preflight calls')
  assert.match(output, new RegExp(`"version":"${packageVersion.replace(/\./g, '\\.')}"`), 'stdio server should report the package version instead of a hardcoded stale value')
  assert.ok(requests.includes('/wp-json/lcfa/v1/mcp/workspace-root') || requests.includes('/wp-json/lcfa/v1/mcp/workspace-root/'), 'bridge should still attempt workspace sync')

  const lfChild = spawn(process.execPath, [
    mcpScript,
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: repoRoot,
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: wpRoot
    },
    stdio: ['pipe', 'pipe', 'pipe']
  })

  lfChild.stdin.write(Buffer.concat([
    Buffer.from(`Content-Length: ${initializeMessage.length}\n\n`),
    initializeMessage
  ]))

  const lfOutput = await waitForStdout(lfChild, initializeTimeoutMs)
  lfChild.kill('SIGKILL')

  assert.match(lfOutput, /"protocolVersion":"2024-11-05"/, 'stdio server should also accept LF-only headers used by some MCP clients')

  const ndjsonChild = spawn(process.execPath, [
    mcpScript,
    '--transport=stdio',
    '--agent=opencode'
  ], {
    cwd: repoRoot,
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: wpRoot
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

  const ndjsonOutput = await waitForStdout(ndjsonChild, initializeTimeoutMs)
  ndjsonChild.kill('SIGKILL')

  assert.match(ndjsonOutput, /"protocolVersion":"2025-11-25"/, 'stdio server should negotiate the client-requested protocol version for newline-delimited JSON-RPC clients')
  assert.ok(!ndjsonOutput.includes('Content-Length:'), 'newline-delimited JSON-RPC clients should receive newline-delimited responses, not Content-Length framing')

  const codexChild = spawn(process.execPath, [
    mcpScript,
    '--transport=stdio',
    '--agent=codex'
  ], {
    cwd: repoRoot,
    env: {
      ...process.env,
      LCFA_REST_BASE: restBase,
      LCFA_MCP_TOKEN: 'test-token',
      LCFA_WP_ROOT: wpRoot
    },
    stdio: ['pipe', 'pipe', 'pipe']
  })

  codexChild.stdin.write([
    JSON.stringify({
      jsonrpc: '2.0',
      id: 1,
      method: 'initialize',
      params: {
        protocolVersion: '2025-06-18',
        capabilities: {},
        clientInfo: { name: 'codex-mcp-client', version: '0.148.0' }
      }
    }),
    JSON.stringify({
      jsonrpc: '2.0',
      method: 'notifications/initialized',
      params: {}
    }),
    JSON.stringify({
      jsonrpc: '2.0',
      id: 2,
      method: 'tools/list',
      params: {}
    }),
    ''
  ].join('\n'))

  const codexListResponse = await waitForNdjsonResponse(codexChild, 2, initializeTimeoutMs)
  codexChild.kill('SIGKILL')

  assert.ok(Array.isArray(codexListResponse.result.tools), 'Codex protocol negotiation should return a valid MCP tools list')
  assert.equal(codexListResponse.result._meta['io.livecanvas/cache-scope'], 'site_session', 'tools/list should put vendor cache metadata in the MCP extension field')

  const standardAnnotationKeys = new Set([
    'title',
    'readOnlyHint',
    'destructiveHint',
    'idempotentHint',
    'openWorldHint'
  ])

  for (const tool of codexListResponse.result.tools) {
    const unsupportedKeys = Object.keys(tool.annotations || {})
      .filter((key) => !standardAnnotationKeys.has(key))

    assert.deepEqual(unsupportedKeys, [], `${tool.name} should serialize MCP-compliant ToolAnnotations for Codex`)
  }
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

function waitForNdjsonResponse(child, expectedId, timeoutMs) {
  return new Promise((resolve, reject) => {
    let stdout = ''
    let stderr = ''

    const timer = setTimeout(() => {
      cleanup()
      reject(new Error(`Timed out after ${timeoutMs}ms waiting for NDJSON response ${expectedId}. stderr=${stderr}`))
    }, timeoutMs)

    const cleanup = () => {
      clearTimeout(timer)
      child.stdout.off('data', onStdout)
      child.stderr.off('data', onStderr)
      child.off('exit', onExit)
    }

    const onStdout = (chunk) => {
      stdout += chunk.toString('utf8')

      for (const line of stdout.split(/\r?\n/)) {
        if (line.trim() === '') {
          continue
        }

        try {
          const message = JSON.parse(line)
          if (message.id === expectedId) {
            cleanup()
            resolve(message)
            return
          }
        } catch (_error) {
          // The final line may still be an incomplete chunk.
        }
      }
    }

    const onStderr = (chunk) => {
      stderr += chunk.toString('utf8')
    }

    const onExit = (code) => {
      cleanup()
      reject(new Error(`Child exited before NDJSON response ${expectedId} (code ${code}). stderr=${stderr}`))
    }

    child.stdout.on('data', onStdout)
    child.stderr.on('data', onStderr)
    child.on('exit', onExit)
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
