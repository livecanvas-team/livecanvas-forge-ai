const path = require('node:path')
const { catalog, normalizeAgent } = require('./agent-registry')
const { configTarget, serverConfig, installConfig } = require('./connection-config')
const { SessionAuth } = require('./session-auth')
const { version } = require('../package.json')

const FULL_ACCESS_SCOPES = ['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo']

function decodeDescriptor(encoded) {
  if (typeof encoded !== 'string' || encoded.length > 16000 || !/^[A-Za-z0-9_-]+$/.test(encoded)) throw new Error('Copy fresh connection instructions from WordPress.')
  let descriptor
  try { descriptor = JSON.parse(Buffer.from(encoded, 'base64url').toString('utf8')) } catch { throw new Error('Invalid connection instructions. Copy them again from WordPress.') }
  if (!descriptor || descriptor.schema !== 'forge.connect.v1' || !/^[a-f0-9]{32}$/.test(descriptor.attempt || '')) throw new Error('These connection instructions are incomplete or outdated.')
  const agent = normalizeAgent(descriptor.client, '')
  if (!agent || catalog[agent].hidden || agent === 'generic') throw new Error('Choose a supported coding agent in WordPress.')
  if (descriptor.package_version !== version) throw new Error('The installer and WordPress require different runtime versions. Copy fresh instructions.')
  if (typeof descriptor.fingerprint !== 'string' || !descriptor.fingerprint || descriptor.fingerprint.length > 256) throw new Error('The expected WordPress site identity is missing.')
  const site = new URL(descriptor.site_url)
  const rest = new URL(descriptor.rest_base)
  if (!['https:', 'http:'].includes(site.protocol) || site.username || site.password || site.search || site.hash || rest.username || rest.password || rest.search || rest.hash || rest.origin !== site.origin || !rest.pathname.endsWith('/lcfa/v1/')) throw new Error('The REST endpoint must belong to the selected WordPress site.')
  const local = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(site.hostname) || /\.(local|test|localhost)$/.test(site.hostname)
  if (site.protocol === 'http:' && (!local || descriptor.allow_local_http !== true)) throw new Error('Use HTTPS. Plain HTTP requires an explicit local-development connection.')
  let runtimePackage = `@livecanvas/ai-bridge-mcp@${version}`
  if (descriptor.runtime_package !== undefined && descriptor.runtime_package !== runtimePackage) {
    const artifact = new URL(descriptor.runtime_package)
    if (artifact.origin !== site.origin || artifact.username || artifact.password || artifact.search || artifact.hash || !artifact.pathname.endsWith(`/livecanvas-ai-bridge-mcp-${version}.tgz`)) throw new Error('The runtime archive must be the versioned package on the selected site.')
    runtimePackage = artifact.href
  }
  return { ...descriptor, client: agent, site_url: site.href, rest_base: rest.href, runtime_package: runtimePackage }
}

function connectionEnvironment(descriptor, workspace) {
  return {
    LCFA_SITE_URL: descriptor.site_url,
    LCFA_REST_BASE: descriptor.rest_base,
    LCFA_SITE_FINGERPRINT: descriptor.fingerprint,
    LCFA_AGENT: descriptor.client,
    LCFA_PROJECT_LABEL: path.basename(path.resolve(workspace)),
    LCFA_AGENT_WORKSPACE_ROOT: path.resolve(workspace),
    LCFA_CONNECTION_ATTEMPT: descriptor.attempt,
    LCFA_PAIRING_SCOPES: FULL_ACCESS_SCOPES.join(','),
    LCFA_TOOL_PROFILE: 'full'
  }
}

async function connect(descriptor, { workspace = process.cwd(), platform = process.platform, home, appData, wait = true, onPending = () => {}, signal, authFactory = config => new SessionAuth(config) } = {}) {
  // Descriptor validation is shared by the CLI and callers; no arbitrary commands are accepted.
  descriptor = decodeDescriptor(Buffer.from(JSON.stringify(descriptor)).toString('base64url'))
  // cmd.exe expands percent variables and interprets metacharacters even when a
  // client passes an argument array. Refuse such URLs before touching config.
  if (platform === 'win32' && !/^[A-Za-z0-9_@:/.[\]-]+$/.test(descriptor.runtime_package)) {
    throw new Error('This runtime URL contains characters unsupported by the Windows launcher. Use manual setup with a local package path, or a site URL without encoded characters or shell metacharacters.')
  }
  const target = configTarget(descriptor.client, { workspace, platform, home, appData })
  const environment = connectionEnvironment(descriptor, workspace)
  // Desktop has no project working directory. Its cache binding must be stable across terminals.
  if (target.global) {
    environment.LCFA_PROJECT_LABEL = `Forge ${descriptor.client} ${descriptor.fingerprint}`
    delete environment.LCFA_AGENT_WORKSPACE_ROOT
  }
  const runtime = platform === 'win32'
    ? { command: 'cmd', args: ['/d', '/s', '/c', 'npx', '--yes', `--package=${descriptor.runtime_package}`, 'livecanvas-ai-bridge-mcp'] }
    : { command: 'npx', args: ['--yes', `--package=${descriptor.runtime_package}`, 'livecanvas-ai-bridge-mcp'] }
  const installation = installConfig(target.path, serverConfig(descriptor.client, { ...runtime, env: environment, fingerprint: descriptor.fingerprint }))
  const auth = authFactory({
    agent: descriptor.client, restBase: descriptor.rest_base, siteUrl: descriptor.site_url,
    siteFingerprint: descriptor.fingerprint, projectLabel: environment.LCFA_PROJECT_LABEL,
    connectionAttempt: descriptor.attempt, pairingScopes: FULL_ACCESS_SCOPES.join(',')
  })
  const result = wait ? await auth.waitForAuthorization({ onPending, signal }) : await auth.resolve()
  if (!wait && result.status === 'pairing_pending') onPending(result)
  // Never return credentials to stdout, even when the authentication library returns them.
  return {
    ok: Boolean(result.ok),
    state: result.ok ? 'reload_required' : result.status || 'authorization_failed',
    client: descriptor.client,
    installation,
    message: result.ok
      ? `Reload ${catalog[descriptor.client].label}'s MCP connection, then ask it to call get_connection_handoff. WordPress will confirm the connection after that read succeeds.`
      : result.message || 'Approve the pending connection in WordPress.'
  }
}

async function runConnect(argv, { output = value => process.stdout.write(`${JSON.stringify(value)}\n`), progress = value => process.stderr.write(`${JSON.stringify(value)}\n`), ...options } = {}) {
  const args = {}
  for (let i = 0; i < argv.length; i++) {
    const key = argv[i]
    if (key === '--no-wait') args.wait = false
    else if (key === '--descriptor' || key === '--workspace') {
      const value = argv[++i]
      if (!value || value.startsWith('--')) throw new Error(`Missing value for ${key}.`)
      args[key.slice(2)] = value
    } else throw new Error(`Unknown setup option: ${key}`)
  }
  const descriptor = decodeDescriptor(args.descriptor)
  const result = await connect(descriptor, {
    ...options, ...args,
    onPending: pending => progress({ state: 'authorization_required', site: descriptor.site_url, client: catalog[descriptor.client].label, verification_url: pending.verification_url, user_code: pending.user_code, message: 'Approve this code in WordPress. This process will resume automatically.' })
  })
  output(result)
  return result.ok ? 0 : 2
}

module.exports = { decodeDescriptor, connectionEnvironment, connect, runConnect, FULL_ACCESS_SCOPES }
