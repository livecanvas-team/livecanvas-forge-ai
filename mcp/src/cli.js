const { loadConfig } = require('./config')
const { WPClient } = require('./wp-client')
const { ThemeFilesystem } = require('./theme-files')
const { WindPressCompiler } = require('./windpress-compiler')
const { PicostrapCompiler } = require('./picostrap-compiler')
const { createToolRegistry } = require('./tool-registry')
const { runStdioServer } = require('./mcp-stdio-server')
const { startBridgeServer } = require('./bridge-server')
const { syncWorkspaceRoot } = require('./workspace-root-sync')

async function runCli(argv = []) {
  const config = loadConfig(argv)
  const client = new WPClient(config)
  const themeFiles = new ThemeFilesystem({ client, config })
  const windpressCompiler = new WindPressCompiler({ client, config })
  const picostrapCompiler = new PicostrapCompiler({ client, config, themeFiles })
  const tools = createToolRegistry(client, themeFiles, windpressCompiler, picostrapCompiler)

  if (config.tool) {
    if (config.wpRoot) {
      await syncWorkspaceRoot({ client, config })
    }
    await runToolMode({ config, tools })
    return
  }

  if (config.transport === 'bridge') {
    if (config.wpRoot) {
      await syncWorkspaceRoot({ client, config })
    }
    await startBridgeServer({ client, tools, themeFiles, windpressCompiler, config })
    return
  }

  if (config.wpRoot) {
    void syncWorkspaceRoot({ client, config })
  }
  await runStdioServer({ client, tools, config })
}

async function runToolMode({ config, tools }) {
  if (!tools.has(config.tool)) {
    throw new Error(`Unknown tool "${config.tool}"`)
  }

  const payload = {
    ok: true,
    tool: config.tool,
    arguments: config.toolArgs || {},
    result: await tools.invoke(config.tool, config.toolArgs || {})
  }

  process.stdout.write(`${serializePayload(payload, config.output)}\n`)
}

function serializePayload(payload, output) {
  const spacing = output === 'pretty' ? 2 : 0
  return JSON.stringify(payload, null, spacing)
}

module.exports = {
  runCli
}
