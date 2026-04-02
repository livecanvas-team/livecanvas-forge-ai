const PROTOCOL_VERSION = '2024-11-05'

async function runStdioServer({ client, tools, config }) {
  const preflight = await client.getMcpStatus()

  if (config.verbose) {
    process.stderr.write(`[livecanvas-forge-mcp] connected to ${preflight.mcp.endpoint}\n`)
  }

  process.stdin.resume()
  let buffer = Buffer.alloc(0)

  process.stdin.on('data', async (chunk) => {
    buffer = Buffer.concat([buffer, chunk])
    const parsed = extractMessages(buffer)
    buffer = parsed.remaining

    for (const message of parsed.messages) {
      try {
        await handleMessage(message, tools)
      } catch (error) {
        if (message && Object.prototype.hasOwnProperty.call(message, 'id')) {
          writeError(message.id, -32000, error instanceof Error ? error.message : String(error))
        }
      }
    }
  })
}

async function handleMessage(message, tools) {
  const method = message.method

  if (method === 'notifications/initialized') {
    return
  }

  if (method === 'initialize') {
    writeResponse(message.id, {
      protocolVersion: PROTOCOL_VERSION,
      capabilities: {
        tools: {}
      },
      serverInfo: {
        name: 'livecanvas-forge-mcp',
        version: '0.1.0'
      }
    })

    return
  }

  if (method === 'ping') {
    writeResponse(message.id, {})
    return
  }

  if (method === 'tools/list') {
    writeResponse(message.id, {
      tools: tools.list()
    })

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
    })

    return
  }

  writeError(message.id, -32601, `Unsupported method "${method}"`)
}

function extractMessages(buffer) {
  const messages = []
  let working = buffer

  while (working.length > 0) {
    const headerEnd = working.indexOf('\r\n\r\n')

    if (headerEnd === -1) {
      break
    }

    const headerBlock = working.slice(0, headerEnd).toString('utf8')
    const lengthMatch = headerBlock.match(/Content-Length:\s*(\d+)/i)

    if (!lengthMatch) {
      throw new Error('Missing Content-Length header')
    }

    const contentLength = Number.parseInt(lengthMatch[1], 10)
    const messageEnd = headerEnd + 4 + contentLength

    if (working.length < messageEnd) {
      break
    }

    const rawMessage = working.slice(headerEnd + 4, messageEnd).toString('utf8')
    messages.push(JSON.parse(rawMessage))
    working = working.slice(messageEnd)
  }

  return {
    messages,
    remaining: working
  }
}

function writeResponse(id, result) {
  writeMessage({
    jsonrpc: '2.0',
    id,
    result
  })
}

function writeError(id, code, message) {
  writeMessage({
    jsonrpc: '2.0',
    id,
    error: {
      code,
      message
    }
  })
}

function writeMessage(message) {
  const payload = JSON.stringify(message)
  process.stdout.write(`Content-Length: ${Buffer.byteLength(payload, 'utf8')}\r\n\r\n${payload}`)
}

module.exports = {
  runStdioServer
}
