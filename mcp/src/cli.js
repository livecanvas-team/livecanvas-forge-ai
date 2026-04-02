const { loadConfig } = require('./config')
const { WPClient } = require('./wp-client')
const { ThemeFilesystem } = require('./theme-files')
const { WindPressCompiler } = require('./windpress-compiler')
const { createToolRegistry } = require('./tool-registry')
const { runStdioServer } = require('./mcp-stdio-server')
const { startBridgeServer } = require('./bridge-server')

async function runCli(argv = []) {
  const config = loadConfig(argv)
  const client = new WPClient(config)
  const themeFiles = new ThemeFilesystem({ client, config })
  const windpressCompiler = new WindPressCompiler({ client, config })
  const tools = createToolRegistry(client, themeFiles, windpressCompiler)

  if (config.transport === 'bridge') {
    await startBridgeServer({ client, tools, themeFiles, windpressCompiler, config })
    return
  }

  await runStdioServer({ client, tools, config })
}

module.exports = {
  runCli
}
