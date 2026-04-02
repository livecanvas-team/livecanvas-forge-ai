const DEFAULTS = {
  transport: 'stdio',
  agent: 'codex',
  restBase: '',
  siteUrl: '',
  token: '',
  wpRoot: '',
  host: '127.0.0.1',
  port: 7681,
  tool: '',
  toolArgs: {},
  output: 'json',
  verbose: false
}

function loadConfig(argv = []) {
  const config = {
    transport: process.env.LCFA_TRANSPORT || DEFAULTS.transport,
    agent: process.env.LCFA_AGENT || DEFAULTS.agent,
    restBase: process.env.LCFA_REST_BASE || DEFAULTS.restBase,
    siteUrl: process.env.LCFA_SITE_URL || DEFAULTS.siteUrl,
    token: process.env.LCFA_MCP_TOKEN || DEFAULTS.token,
    wpRoot: process.env.LCFA_WP_ROOT || DEFAULTS.wpRoot,
    host: process.env.LCFA_MCP_HOST || DEFAULTS.host,
    port: parsePort(process.env.LCFA_MCP_PORT || DEFAULTS.port),
    tool: process.env.LCFA_TOOL || DEFAULTS.tool,
    toolArgs: parseToolArguments(process.env.LCFA_TOOL_ARGS || ''),
    output: process.env.LCFA_OUTPUT || DEFAULTS.output,
    verbose: process.env.LCFA_VERBOSE === '1'
  }

  for (let index = 0; index < argv.length; index += 1) {
    const item = argv[index]

    if (!item.startsWith('--')) {
      continue
    }

    const [rawKey, inlineValue] = item.slice(2).split('=')
    const key = rawKey.trim()
    const value = inlineValue !== undefined ? inlineValue : argv[index + 1]

    if (inlineValue === undefined && argv[index + 1] && !argv[index + 1].startsWith('--')) {
      index += 1
    }

    switch (key) {
      case 'transport':
        config.transport = value || config.transport
        break
      case 'agent':
        config.agent = value || config.agent
        break
      case 'rest-base':
        config.restBase = value || config.restBase
        break
      case 'site-url':
        config.siteUrl = value || config.siteUrl
        break
      case 'token':
        config.token = value || config.token
        break
      case 'wp-root':
        config.wpRoot = value || config.wpRoot
        break
      case 'host':
        config.host = value || config.host
        break
      case 'port':
        config.port = parsePort(value)
        break
      case 'tool':
        config.tool = value || config.tool
        break
      case 'tool-args':
        config.toolArgs = parseToolArguments(value)
        break
      case 'output':
        config.output = value || config.output
        break
      case 'verbose':
        config.verbose = true
        break
      default:
        break
    }
  }

  config.restBase = normalizeRestBase(config.restBase, config.siteUrl)

  if (!['stdio', 'bridge'].includes(config.transport)) {
    throw new Error(`Unsupported transport "${config.transport}". Use stdio or bridge.`)
  }

  if (!['json', 'pretty'].includes(config.output)) {
    throw new Error(`Unsupported output "${config.output}". Use json or pretty.`)
  }

  if (!config.restBase) {
    throw new Error('Missing REST base. Set LCFA_REST_BASE or pass --rest-base.')
  }

  if (!config.token) {
    throw new Error('Missing MCP token. Set LCFA_MCP_TOKEN or pass --token.')
  }

  return config
}

function normalizeRestBase(restBase, siteUrl) {
  if (restBase) {
    return ensureTrailingSlash(restBase)
  }

  if (!siteUrl) {
    return ''
  }

  return ensureTrailingSlash(`${siteUrl.replace(/\/$/, '')}/wp-json/lcfa/v1`)
}

function ensureTrailingSlash(value) {
  return value.endsWith('/') ? value : `${value}/`
}

function parsePort(value) {
  const port = Number.parseInt(String(value), 10)

  if (Number.isNaN(port) || port < 1 || port > 65535) {
    return DEFAULTS.port
  }

  return port
}

function parseToolArguments(value) {
  if (!value) {
    return {}
  }

  if (typeof value === 'object') {
    return value
  }

  try {
    const parsed = JSON.parse(String(value))
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch (error) {
    throw new Error('Invalid --tool-args payload. Pass a valid JSON object.')
  }
}

module.exports = {
  loadConfig
}
