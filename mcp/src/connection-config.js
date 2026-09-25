const fs = require('node:fs')
const path = require('node:path')
const os = require('node:os')
const crypto = require('node:crypto')
const { isDeepStrictEqual } = require('node:util')
const jsonc = require('jsonc-parser')
const toml = require('smol-toml')
const { getAgent } = require('./agent-registry')

// Installation runs on the agent host. WordPress paths are never used as client paths.
function configTarget(agentId, { workspace = process.cwd(), home = os.homedir(), platform = process.platform, appData = process.env.APPDATA } = {}) {
  const agent = getAgent(agentId)
  if (agent.hidden || agent.id === 'generic') throw new Error('Select a supported agent or use the manual configuration.')
  let target
  if (agent.globalTarget === 'claude-desktop') {
    if (platform === 'darwin') target = path.join(home, 'Library/Application Support/Claude/claude_desktop_config.json')
    else if (platform === 'win32' && appData) target = path.join(appData, 'Claude/claude_desktop_config.json')
    else throw new Error('Automatic Claude Desktop setup is available on macOS and Windows. Use manual setup on this host.')
  } else if (agent.globalTarget === 'zed') target = path.join(home, '.config/zed/settings.json')
  else if (agent.globalTarget === 'cline') target = path.join(home, '.cline/mcp.json')
  else if (agent.globalTarget) throw new Error('This client requires version-specific setup. Use manual configuration.')
  else target = path.resolve(workspace, agent.projectFile)

  // Prefer the existing JSONC file; never create a second config that the client ignores.
  if (agent.id === 'opencode' && !fs.existsSync(target) && fs.existsSync(target.replace(/\.json$/, '.jsonc'))) target = target.replace(/\.json$/, '.jsonc')
  if (agent.id === 'kilo-code' && !fs.existsSync(target)) {
    const alternatives = ['kilo.jsonc', 'kilo.json', '.kilo/kilo.json'].map(file => path.resolve(workspace, file)).filter(file => fs.existsSync(file))
    if (alternatives.length > 1) throw new Error('Several Kilo configurations exist. Choose the active configuration in manual setup.')
    if (alternatives.length) target = alternatives[0]
  }
  return { agent, path: target, global: Boolean(agent.globalTarget) }
}

function serverConfig(agentId, { command, args = [], env, fingerprint }) {
  if (!command || !Array.isArray(args) || !fingerprint || env.LCFA_SITE_FINGERPRINT !== fingerprint) throw new Error('A site-bound runtime command is required.')
  const agent = getAgent(agentId)
  const name = `livecanvas-${crypto.createHash('sha256').update(fingerprint).digest('hex').slice(0, 12)}`
  const environment = { ...env, LCFA_AGENT: agent.id }
  if (Object.keys(environment).some(key => /TOKEN|PASSWORD|SECRET/i.test(key))) throw new Error('Credentials must be stored in the session cache, not the agent configuration.')
  const root = agent.format === 'toml' ? 'mcp_servers' : agent.format === 'opencode' ? 'mcp' : agent.format === 'servers' ? 'servers' : agent.format === 'context_servers' ? 'context_servers' : 'mcpServers'
  const server = agent.format === 'opencode'
    ? { type: 'local', command: [command, ...args], environment, enabled: true, timeout: 60000 }
    : { command, args, env: environment, ...(agent.format === 'servers' || agent.id === 'claude-code' ? { type: 'stdio' } : {}) }
  return { name, root, server, format: agent.format === 'toml' ? 'toml' : 'jsonc', fingerprint }
}

function parseJsonConfig(source) {
  const errors = []
  const tree = jsonc.parseTree(source, errors, { allowTrailingComma: true, allowEmptyContent: false })
  if (errors.length || !tree || tree.type !== 'object') throw new Error('The existing configuration is not a valid JSON/JSONC object. No files were changed.')
  function rejectDuplicates(node) {
    if (node.type === 'object') {
      const keys = new Set()
      for (const property of node.children || []) {
        const key = property.children[0].value
        if (keys.has(key)) throw new Error('The configuration contains duplicate keys. Resolve them before connecting.')
        keys.add(key)
      }
    }
    for (const child of node.children || []) rejectDuplicates(child)
  }
  rejectDuplicates(tree)
  // getNodeValue deliberately returns null-prototype objects. Normalize for semantic comparison.
  return JSON.parse(JSON.stringify(jsonc.getNodeValue(tree)))
}

function object(value) { return value !== null && typeof value === 'object' && !Array.isArray(value) }

function mergeConfig(source, descriptor) {
  const { root, name, server, format, fingerprint } = descriptor
  if (!/^livecanvas-[a-f0-9]{12}$/.test(name)) throw new Error('Invalid Forge server name.')
  const before = format === 'toml' ? toml.parse(source) : parseJsonConfig(source.trim() ? source : '{}')
  if (before[root] !== undefined && !object(before[root])) throw new Error(`The ${root} setting is not an object. No files were changed.`)
  const existing = before[root]?.[name]
  if (existing !== undefined && (!object(existing) || (existing.env || existing.environment)?.LCFA_SITE_FINGERPRINT !== fingerprint)) throw new Error('A different server owns this name. No files were changed.')
  const envKey = format === 'jsonc' && root === 'mcp' ? 'environment' : 'env'
  const merged = { ...existing, ...server, [envKey]: { ...existing?.[envKey], ...server[envKey] } }
  if (isDeepStrictEqual(existing, merged)) return source
  let next
  if (format === 'toml') {
    const start = `# BEGIN FORGE ${name}`
    const end = `# END FORGE ${name}`
    const serialized = toml.stringify({ [root]: { [name]: merged } }).replace(`[${root}]\n`, '')
    const block = `${start}\n${serialized}\n${end}\n`
    if (existing !== undefined) {
      const startAt = source.indexOf(start)
      const endAt = source.indexOf(end)
      if (startAt < 0 || endAt <= startAt || source.indexOf(start, startAt + start.length) >= 0 || source.indexOf(end, endAt + end.length) >= 0) throw new Error('The existing Forge TOML entry is not managed by this installer. Use manual setup to preserve it.')
      next = source.slice(0, startAt) + block.trimEnd() + source.slice(endAt + end.length)
    } else {
      if (source.includes(start) || source.includes(end)) throw new Error('An incomplete Forge configuration block needs manual repair.')
      next = `${source}${source.endsWith('\n') || !source ? '' : '\n'}\n${block}`
    }
  } else {
    const content = source.trim() ? source : '{}\n'
    next = jsonc.applyEdits(content, jsonc.modify(content, [root, name], merged, {
      formattingOptions: { insertSpaces: true, tabSize: 2, eol: source.includes('\r\n') ? '\r\n' : '\n' }
    }))
  }
  const after = format === 'toml' ? toml.parse(next) : parseJsonConfig(next)
  const expected = { ...before, [root]: { ...before[root], [name]: merged } }
  if (!isDeepStrictEqual(after, expected)) throw new Error('Configuration validation failed. No files were changed.')
  return next
}

function assertRegularPath(target) {
  let entry = path.resolve(target)
  while (true) {
    try {
      const stat = fs.lstatSync(entry)
      if (stat.isSymbolicLink()) throw new Error(`A symlink requires manual setup: ${entry}`)
      if (entry === path.resolve(target) && (!stat.isFile() || stat.nlink > 1)) throw new Error('The configuration must be a regular, unlinked file.')
    } catch (error) { if (error.code !== 'ENOENT') throw error }
    const parent = path.dirname(entry)
    if (parent === entry) break
    entry = parent
  }
}

function installConfig(target, descriptor) {
  assertRegularPath(target)
  fs.mkdirSync(path.dirname(target), { recursive: true, mode: 0o700 })
  const lock = `${target}.forge-lock`
  let lockFd
  let temporary
  try {
    lockFd = fs.openSync(lock, 'wx', 0o600)
    const existed = fs.existsSync(target)
    const source = existed ? fs.readFileSync(target, 'utf8') : ''
    const next = mergeConfig(source, descriptor)
    if (next === source) return { changed: false, path: target, backup: null }
    assertRegularPath(target)
    const mode = existed ? fs.statSync(target).mode & 0o777 : 0o600
    const backup = existed ? `${target}.forge-backup-${crypto.randomBytes(8).toString('hex')}` : null
    if (backup) fs.writeFileSync(backup, source, { flag: 'wx', mode: 0o600 })
    temporary = `${target}.forge-${crypto.randomBytes(8).toString('hex')}.tmp`
    const fd = fs.openSync(temporary, 'wx', mode)
    try { fs.writeFileSync(fd, next, 'utf8'); fs.fsyncSync(fd) } finally { fs.closeSync(fd) }
    // Catch edits made while calculating the merge instead of overwriting them.
    if (fs.existsSync(target) !== existed || (existed && fs.readFileSync(target, 'utf8') !== source)) throw new Error('The configuration changed during setup. Retry after the other editor has saved.')
    assertRegularPath(target)
    fs.renameSync(temporary, target)
    temporary = null
    return { changed: true, path: target, backup }
  } finally {
    if (temporary) fs.unlinkSync(temporary)
    if (lockFd !== undefined) { fs.closeSync(lockFd); fs.unlinkSync(lock) }
  }
}

module.exports = { configTarget, serverConfig, mergeConfig, installConfig, parseJsonConfig }
