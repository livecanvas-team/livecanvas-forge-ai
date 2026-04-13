const PROTOCOL_VERSION = '2024-11-05'
const fs = require('node:fs')

async function runStdioServer({ client, tools, config }) {
  process.stdin.resume()
  let buffer = Buffer.alloc(0)
  const debugLogPath = process.env.LCFA_MCP_DEBUG_LOG || ''

  debugLog(debugLogPath, `server.start transport=stdio agent=${config && config.agent ? config.agent : ''}`)

  if (config.verbose) {
    void client.getMcpStatus()
      .then((preflight) => {
        process.stderr.write(`[livecanvas-forge-mcp] connected to ${preflight.mcp.endpoint}\n`)
      })
      .catch((error) => {
        process.stderr.write(`[livecanvas-forge-mcp] preflight failed: ${error instanceof Error ? error.message : String(error)}\n`)
      })
  }

  process.stdin.on('data', async (chunk) => {
    debugLog(debugLogPath, `stdin.chunk bytes=${chunk.length} preview=${previewChunk(chunk)}`)
    buffer = Buffer.concat([buffer, chunk])
    const parsed = extractMessages(buffer)
    buffer = parsed.remaining

    for (const entry of parsed.messages) {
      const message = entry && entry.message ? entry.message : entry
      const format = entry && entry.format ? entry.format : 'content-length'

      try {
        await handleMessage(message, tools, debugLogPath, format)
      } catch (error) {
        debugLog(debugLogPath, `message.error id=${message && Object.prototype.hasOwnProperty.call(message, 'id') ? message.id : 'none'} method=${message && message.method ? message.method : ''} error=${error instanceof Error ? error.message : String(error)}`)
        if (message && Object.prototype.hasOwnProperty.call(message, 'id')) {
          writeError(message.id, -32000, error instanceof Error ? error.message : String(error), debugLogPath, format)
        }
      }
    }
  })
}

async function handleMessage(message, tools, debugLogPath = '', format = 'content-length') {
  const method = message.method
  debugLog(debugLogPath, `message.received id=${Object.prototype.hasOwnProperty.call(message, 'id') ? message.id : 'none'} method=${method || ''}`)

  if (method === 'notifications/initialized') {
    return
  }

  if (method === 'initialize') {
    const negotiatedProtocolVersion = resolveProtocolVersion(message)

    writeResponse(message.id, {
      protocolVersion: negotiatedProtocolVersion,
      capabilities: {
        tools: {}
      },
      serverInfo: {
        name: 'livecanvas-forge-mcp',
        version: '0.1.0'
      }
    }, debugLogPath, format)

    return
  }

  if (method === 'ping') {
    writeResponse(message.id, {}, debugLogPath, format)
    return
  }

  if (method === 'tools/list') {
    writeResponse(message.id, {
      tools: tools.list()
    }, debugLogPath, format)

    return
  }

  if (method === 'tools/call') {
    const toolName = message.params && message.params.name ? message.params.name : ''
    const args = message.params && message.params.arguments ? message.params.arguments : {}
    const result = await tools.invoke(toolName, args)

    writeResponse(message.id, {
      content: [
        {
          type: 'text',
          text: JSON.stringify(result, null, 2)
        }
      ],
      structuredContent: result
    }, debugLogPath, format)

    return
  }

  writeError(message.id, -32601, `Unsupported method "${method}"`, debugLogPath, format)
}

function extractMessages(buffer) {
  const messages = []
  let working = buffer

  while (working.length > 0) {
    if (looksLikeHeaderFramedMessage(working)) {
      const separator = resolveHeaderSeparator(working)
      const headerEnd = separator.index

      if (headerEnd === -1) {
        break
      }

      const headerBlock = working.slice(0, headerEnd).toString('utf8')
      const lengthMatch = headerBlock.match(/Content-Length:\s*(\d+)/i)

      if (!lengthMatch) {
        throw new Error('Missing Content-Length header')
      }

      const contentLength = Number.parseInt(lengthMatch[1], 10)
      const messageEnd = headerEnd + separator.length + contentLength

      if (working.length < messageEnd) {
        break
      }

      const rawMessage = working.slice(headerEnd + separator.length, messageEnd).toString('utf8')
      messages.push({
        message: JSON.parse(rawMessage),
        format: 'content-length'
      })
      working = working.slice(messageEnd)
      continue
    }

    const newlineIndex = working.indexOf('\n')

    if (newlineIndex === -1) {
      break
    }

    const rawLine = working.slice(0, newlineIndex).toString('utf8').replace(/\r$/, '').trim()
    working = working.slice(newlineIndex + 1)

    if (rawLine === '') {
      continue
    }

    messages.push({
      message: JSON.parse(rawLine),
      format: 'ndjson'
    })
  }

  return {
    messages,
    remaining: working
  }
}

function resolveHeaderSeparator(buffer) {
  const crlfIndex = buffer.indexOf('\r\n\r\n')

  if (crlfIndex !== -1) {
    return {
      index: crlfIndex,
      length: 4
    }
  }

  const lfIndex = buffer.indexOf('\n\n')

  return {
    index: lfIndex,
    length: lfIndex === -1 ? 0 : 2
  }
}

function looksLikeHeaderFramedMessage(buffer) {
  const preview = buffer.slice(0, 32).toString('utf8')
  return /^Content-Length:/i.test(preview)
}

function resolveProtocolVersion(message) {
  const requested = message && message.params && typeof message.params.protocolVersion === 'string'
    ? message.params.protocolVersion.trim()
    : ''

  return requested || PROTOCOL_VERSION
}

function writeResponse(id, result, debugLogPath = '', format = 'content-length') {
  debugLog(debugLogPath, `message.response id=${id}`)
  writeMessage({
    jsonrpc: '2.0',
    id,
    result
  }, format)
}

function writeError(id, code, message, debugLogPath = '', format = 'content-length') {
  debugLog(debugLogPath, `message.error_response id=${id} code=${code} message=${message}`)
  writeMessage({
    jsonrpc: '2.0',
    id,
    error: {
      code,
      message
    }
  }, format)
}

function writeMessage(message, format = 'content-length') {
  const payload = JSON.stringify(message)
  if (format === 'ndjson') {
    process.stdout.write(`${payload}\n`)
    return
  }

  process.stdout.write(`Content-Length: ${Buffer.byteLength(payload, 'utf8')}\r\n\r\n${payload}`)
}

function debugLog(debugLogPath, line) {
  if (!debugLogPath) {
    return
  }

  try {
    fs.appendFileSync(debugLogPath, `[${new Date().toISOString()}] ${line}\n`)
  } catch (error) {
    // ignore debug logging failures
  }
}

function previewChunk(chunk) {
  return chunk
    .toString('utf8')
    .replace(/\r/g, '\\r')
    .replace(/\n/g, '\\n')
    .slice(0, 240)
}

module.exports = {
  runStdioServer
}
