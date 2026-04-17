const crypto = require('node:crypto')
const http = require('node:http')
const { URL } = require('node:url')

async function startBridgeServer({ client, tools, themeFiles, windpressCompiler, config }) {
  const preflight = await client.getMcpStatus()

  if (config.verbose) {
    process.stderr.write(`[livecanvas-forge-mcp] bridge preflight ok for ${preflight.mcp.endpoint}\n`)
  }

  const server = http.createServer(async (request, response) => {
    try {
      await handleHttpRequest(request, response, client, tools, themeFiles, windpressCompiler)
    } catch (error) {
      sendJson(response, 500, {
        ok: false,
        error: error instanceof Error ? error.message : String(error)
      })
    }
  })

  server.on('upgrade', (request, socket) => {
    try {
      handleUpgrade(request, socket, tools)
    } catch (error) {
      socket.write('HTTP/1.1 500 Internal Server Error\r\n\r\n')
      socket.destroy()
    }
  })

  await new Promise((resolve, reject) => {
    server.once('error', reject)
    server.listen(config.port, config.host, () => resolve())
  })

  process.stderr.write(`[livecanvas-forge-mcp] bridge listening on http://${config.host}:${config.port}\n`)

  return server
}

async function handleHttpRequest(request, response, client, tools, themeFiles, windpressCompiler) {
  const url = new URL(request.url || '/', `http://${request.headers.host || '127.0.0.1'}`)

  if (request.method === 'GET' && url.pathname === '/health') {
    const status = await client.getMcpStatus()
    sendJson(response, 200, {
      ok: true,
      mode: 'bridge',
      status: status.mcp
    })
    return
  }

  if (request.method === 'GET' && url.pathname === '/bootstrap') {
    sendJson(response, 200, await client.getMcpBootstrap())
    return
  }

  if (request.method === 'GET' && url.pathname === '/tools') {
    sendJson(response, 200, {
      ok: true,
      tools: tools.list()
    })
    return
  }

  if (request.method === 'GET' && url.pathname === '/snapshot') {
    sendJson(response, 200, await client.getSnapshot())
    return
  }

  if (request.method === 'GET' && url.pathname === '/inventory') {
    sendJson(response, 200, await client.getInventory())
    return
  }

  if (request.method === 'GET' && url.pathname === '/context') {
    sendJson(response, 200, await client.getContext(queryToObject(url)))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme-context') {
    sendJson(response, 200, await client.getThemeContext(queryToObject(url)))
    return
  }

  if (request.method === 'GET' && url.pathname === '/genesis/plan') {
    sendJson(response, 200, await client.getGenesisPlan())
    return
  }

  if (request.method === 'GET' && url.pathname === '/genesis/execution-plan') {
    sendJson(response, 200, await client.getGenesisExecutionPlan())
    return
  }

  if (request.method === 'GET' && url.pathname === '/page-html') {
    sendJson(response, 200, await client.getPageHtml(url.searchParams.get('post_id')))
    return
  }

  if (request.method === 'GET' && url.pathname === '/acf-fields') {
    sendJson(response, 200, await client.getAcfFields(url.searchParams.get('post_type') || 'page'))
    return
  }

  if (request.method === 'GET' && url.pathname === '/library/blocks') {
    sendJson(response, 200, await client.getBlocksLibrary())
    return
  }

  if (request.method === 'GET' && url.pathname === '/windpress/status') {
    sendJson(response, 200, await client.getWindPressStatus())
    return
  }

  if (request.method === 'GET' && url.pathname === '/windpress/volume') {
    sendJson(response, 200, await client.getWindPressVolume({
      include_content: url.searchParams.get('include_content') === '1' || url.searchParams.get('include_content') === 'true',
      handler: url.searchParams.get('handler') || '',
      extension: url.searchParams.get('extension') || '',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/windpress/volume/handlers') {
    sendJson(response, 200, await client.getWindPressHandlers())
    return
  }

  if (request.method === 'GET' && url.pathname === '/windpress/providers') {
    sendJson(response, 200, await client.getWindPressProviders())
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/roots') {
    sendJson(response, 200, await themeFiles.getThemeRoots())
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/files') {
    sendJson(response, 200, await themeFiles.listFiles({
      root_scope: url.searchParams.get('root_scope') || 'active',
      directory: url.searchParams.get('directory') || '',
      extensions: collectQueryValues(url, 'extension'),
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/templates') {
    sendJson(response, 200, await themeFiles.listTemplates({
      root_scope: url.searchParams.get('root_scope') || 'active',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/templates/twig') {
    sendJson(response, 200, await themeFiles.listTemplatesByExtension('twig', {
      root_scope: url.searchParams.get('root_scope') || 'active',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/templates/latte') {
    sendJson(response, 200, await themeFiles.listTemplatesByExtension('latte', {
      root_scope: url.searchParams.get('root_scope') || 'active',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/templates/php') {
    sendJson(response, 200, await themeFiles.listTemplatesByExtension('php', {
      root_scope: url.searchParams.get('root_scope') || 'active',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/file') {
    sendJson(response, 200, await themeFiles.readFile({
      root_scope: url.searchParams.get('root_scope') || 'active',
      path: url.searchParams.get('path') || ''
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/template') {
    sendJson(response, 200, await themeFiles.readTemplateFile({
      root_scope: url.searchParams.get('root_scope') || 'active',
      path: url.searchParams.get('path') || ''
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/backups') {
    sendJson(response, 200, await themeFiles.listBackups({
      path: url.searchParams.get('path') || '',
      kind: url.searchParams.get('kind') || '',
      limit: url.searchParams.get('limit')
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/theme/backup') {
    sendJson(response, 200, await themeFiles.readBackup({
      backup_id: url.searchParams.get('backup_id') || url.searchParams.get('id') || ''
    }))
    return
  }

  if (request.method === 'GET' && url.pathname === '/command/actions') {
    sendJson(response, 200, await client.getCommandActions())
    return
  }

  if (request.method === 'POST' && url.pathname === '/command/suggest') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.suggestCommand(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/genesis/plan/generate') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.generateGenesisPlan(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/genesis/execute-next') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.executeGenesisNext(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/genesis/execute-task') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.executeGenesisTask(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/command') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.runCommand(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/volume') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.saveWindPressVolumeEntries(payload.entries || []))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/providers/scan') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.scanWindPressProvider(
      payload.provider_id || '',
      payload.metadata || {},
      payload.decode_contents !== false
    ))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/providers/scan/full') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.scanWindPressProviderFull(
      payload.provider_id || '',
      {
        metadata: payload.metadata || {},
        decode_contents: payload.decode_contents !== false,
        max_batches: payload.max_batches
      }
    ))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/theme-json') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.saveWindPressThemeJson(payload.theme_json ?? payload.data ?? ''))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/cache') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.saveWindPressCache(
      payload.css || '',
      payload.sourcemap || '',
      payload.full_build ?? null
    ))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/cache/flush') {
    sendJson(response, 200, await client.flushWindPressCache())
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/volume/reset') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await client.resetWindPressVolumeEntry(payload.relative_path || ''))
    return
  }

  if (request.method === 'POST' && url.pathname === '/windpress/build') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await windpressCompiler.buildCache(payload || {}))
    return
  }

  if (request.method === 'POST' && url.pathname === '/theme/file') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await themeFiles.writeFile(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/theme/template') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await themeFiles.writeTemplateFile(payload))
    return
  }

  if (request.method === 'POST' && url.pathname === '/theme/backup/restore') {
    const payload = await readJsonBody(request)
    sendJson(response, 200, await themeFiles.restoreBackup(payload))
    return
  }

  sendJson(response, 404, {
    ok: false,
    error: 'Route not found'
  })
}

function handleUpgrade(request, socket, tools) {
  const key = request.headers['sec-websocket-key']

  if (!key) {
    socket.write('HTTP/1.1 400 Bad Request\r\n\r\n')
    socket.destroy()
    return
  }

  const accept = crypto
    .createHash('sha1')
    .update(`${key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11`)
    .digest('base64')

  socket.write([
    'HTTP/1.1 101 Switching Protocols',
    'Upgrade: websocket',
    'Connection: Upgrade',
    `Sec-WebSocket-Accept: ${accept}`,
    '\r\n'
  ].join('\r\n'))

  let buffer = Buffer.alloc(0)

  socket.on('data', async (chunk) => {
    buffer = Buffer.concat([buffer, chunk])
    const parsed = extractFrames(buffer)
    buffer = parsed.remaining

    for (const frame of parsed.messages) {
      try {
        const payload = JSON.parse(frame)
        const reply = await handleSocketMessage(payload, tools)
        socket.write(encodeFrame(JSON.stringify(reply)))
      } catch (error) {
        socket.write(encodeFrame(JSON.stringify({
          ok: false,
          error: error instanceof Error ? error.message : String(error)
        })))
      }
    }
  })

  socket.on('error', () => {
    socket.destroy()
  })
}

async function handleSocketMessage(payload, tools) {
  if (payload.action === 'tools/list') {
    return {
      id: payload.id || null,
      ok: true,
      result: tools.list()
    }
  }

  if (payload.action === 'tools/call' && payload.name) {
    return {
      id: payload.id || null,
      ok: true,
      result: await tools.invoke(payload.name, payload.arguments || {})
    }
  }

  if (payload.tool) {
    return {
      id: payload.id || null,
      ok: true,
      result: await tools.invoke(payload.tool, payload.arguments || payload.params || {})
    }
  }

  if (payload.action && tools.has(payload.action)) {
    return {
      id: payload.id || null,
      ok: true,
      result: await tools.invoke(payload.action, payload.params || {})
    }
  }

  throw new Error('Unsupported bridge action')
}

function extractFrames(buffer) {
  const messages = []
  let offset = 0

  while (buffer.length - offset >= 2) {
    const firstByte = buffer[offset]
    const secondByte = buffer[offset + 1]
    const opcode = firstByte & 0x0f
    let payloadLength = secondByte & 0x7f
    let headerLength = 2

    if (opcode === 0x8) {
      return { messages, remaining: Buffer.alloc(0) }
    }

    if (payloadLength === 126) {
      if (buffer.length - offset < 4) {
        break
      }

      payloadLength = buffer.readUInt16BE(offset + 2)
      headerLength += 2
    } else if (payloadLength === 127) {
      if (buffer.length - offset < 10) {
        break
      }

      payloadLength = Number(buffer.readBigUInt64BE(offset + 2))
      headerLength += 8
    }

    const masked = (secondByte & 0x80) === 0x80
    const maskLength = masked ? 4 : 0
    const frameLength = headerLength + maskLength + payloadLength

    if (buffer.length - offset < frameLength) {
      break
    }

    let payload = buffer.slice(offset + headerLength + maskLength, offset + frameLength)

    if (masked) {
      const mask = buffer.slice(offset + headerLength, offset + headerLength + 4)
      const unmasked = Buffer.alloc(payload.length)

      for (let index = 0; index < payload.length; index += 1) {
        unmasked[index] = payload[index] ^ mask[index % 4]
      }

      payload = unmasked
    }

    if (opcode === 0x1) {
      messages.push(payload.toString('utf8'))
    }

    offset += frameLength
  }

  return {
    messages,
    remaining: buffer.slice(offset)
  }
}

function encodeFrame(payload) {
  const payloadBuffer = Buffer.from(payload, 'utf8')
  const payloadLength = payloadBuffer.length

  if (payloadLength < 126) {
    return Buffer.concat([
      Buffer.from([0x81, payloadLength]),
      payloadBuffer
    ])
  }

  if (payloadLength < 65536) {
    const header = Buffer.alloc(4)
    header[0] = 0x81
    header[1] = 126
    header.writeUInt16BE(payloadLength, 2)

    return Buffer.concat([header, payloadBuffer])
  }

  const header = Buffer.alloc(10)
  header[0] = 0x81
  header[1] = 127
  header.writeBigUInt64BE(BigInt(payloadLength), 2)

  return Buffer.concat([header, payloadBuffer])
}

function queryToObject(url) {
  const query = {}

  for (const [key, value] of url.searchParams.entries()) {
    query[key] = value
  }

  return query
}

function collectQueryValues(url, key) {
  const values = url.searchParams.getAll(key)

  if (values.length > 0) {
    return values
  }

  const inline = url.searchParams.get(`${key}s`)

  if (!inline) {
    return []
  }

  return inline
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
}

function sendJson(response, statusCode, payload) {
  response.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8'
  })
  response.end(JSON.stringify(payload))
}

function readJsonBody(request) {
  return new Promise((resolve, reject) => {
    let body = ''

    request.on('data', (chunk) => {
      body += chunk.toString('utf8')
    })

    request.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {})
      } catch (error) {
        reject(error)
      }
    })

    request.on('error', reject)
  })
}

module.exports = {
  startBridgeServer
}
