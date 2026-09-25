const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const os = require('node:os')
const toml = require('smol-toml')
const { catalog, normalizeAgent } = require('../src/agent-registry')
const { configTarget, serverConfig, mergeConfig, installConfig, parseJsonConfig } = require('../src/connection-config')

const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'forge-config-test-')))
const runtime = { command: '/a path/node', args: ['/a path/bridge.js'], fingerprint: 'site-A', env: { LCFA_SITE_FINGERPRINT: 'site-A', LCFA_SITE_URL: 'https://example.test', LCFA_PAIRING_SCOPES: 'read,preview,write,media,theme_files,debug,cache,seo' } }
const descriptor = serverConfig('cursor', runtime)

try {
  assert.equal(normalizeAgent('unrecognized'), 'generic')
  assert.equal(normalizeAgent('claude-code'), 'claude-code')
  assert.equal(normalizeAgent('claude-desktop'), 'claude-desktop')
  for (const id of Object.keys(catalog).filter(id => !catalog[id].hidden)) {
    const config = serverConfig(id, runtime)
    const merged = mergeConfig(config.format === 'toml' ? 'model = "keep"\n' : '// keep\n{"custom":true}\n', config)
    const parsed = config.format === 'toml' ? toml.parse(merged) : parseJsonConfig(merged)
    assert.equal((parsed[config.root][config.name].env || parsed[config.root][config.name].environment).LCFA_AGENT, id)
    assert.equal(mergeConfig(merged, config), merged, `${id} should be idempotent`)
  }

  const source = '// keep this comment\n{\n  "mcpServers": {"other": {"command": "keep"}},\n  "theme": "dark",\n}\n'
  const merged = mergeConfig(source, descriptor)
  assert.ok(merged.startsWith('// keep this comment'))
  assert.deepEqual(parseJsonConfig(merged).mcpServers.other, { command: 'keep' })
  assert.equal(parseJsonConfig(merged).theme, 'dark')
  const changed = serverConfig('cursor', { ...runtime, env: { ...runtime.env, LCFA_CONNECTION_ATTEMPT: 'attempt2' } })
  assert.equal(parseJsonConfig(mergeConfig(merged, changed)).mcpServers[changed.name].env.LCFA_CONNECTION_ATTEMPT, 'attempt2')
  assert.throws(() => mergeConfig('{"mcpServers":{},"mcpServers":{}}', descriptor), /duplicate/)
  assert.throws(() => mergeConfig('{"mcpServers": []}', descriptor), /not an object/)
  assert.throws(() => mergeConfig('{not json', descriptor), /not a valid/)
  assert.throws(() => mergeConfig(JSON.stringify({ mcpServers: { [descriptor.name]: { command: 'other' } } }), descriptor), /different server/)
  assert.throws(() => serverConfig('cursor', { ...runtime, env: { ...runtime.env, LCFA_SESSION_TOKEN: 'secret' } }), /Credentials/)

  const codex = serverConfig('codex', runtime)
  const oldToml = '# Keep settings\nmodel = "user-choice"\n[mcp_servers.other]\ncommand = "other"\n[projects."/some/path"]\ntrust_level = "trusted"\n'
  const codexFirst = mergeConfig(oldToml, codex)
  const codexUpdated = mergeConfig(codexFirst, serverConfig('codex', { ...runtime, env: { ...runtime.env, LCFA_CONNECTION_ATTEMPT: 'next' } }))
  assert.ok(codexUpdated.startsWith(oldToml))
  assert.equal(toml.parse(codexUpdated).projects['/some/path'].trust_level, 'trusted')
  assert.equal(toml.parse(codexUpdated).model, 'user-choice')
  assert.throws(() => mergeConfig(toml.stringify({ mcp_servers: { [codex.name]: codex.server } }), { ...codex, server: { ...codex.server, args: ['new'] } }), /not managed/)
  assert.throws(() => mergeConfig('model = [broken', codex))

  assert.equal(configTarget('claude-code', { workspace: root }).path, path.join(root, '.mcp.json'))
  assert.equal(configTarget('claude-desktop', { home: root, platform: 'darwin' }).path, path.join(root, 'Library/Application Support/Claude/claude_desktop_config.json'))
  assert.throws(() => configTarget('claude-desktop', { platform: 'linux' }), /manual/)
  assert.throws(() => configTarget('unknown'), /manual/)
  fs.writeFileSync(path.join(root, 'opencode.jsonc'), '{}')
  assert.equal(configTarget('opencode', { workspace: root }).path, path.join(root, 'opencode.jsonc'))

  const target = path.join(root, '.cursor', 'mcp.json')
  const first = installConfig(target, descriptor)
  assert.equal(first.changed, true)
  assert.equal(first.backup, null)
  assert.equal(installConfig(target, descriptor).changed, false)
  const before = fs.readFileSync(target, 'utf8')
  const second = installConfig(target, changed)
  assert.equal(fs.readFileSync(second.backup, 'utf8'), before)
  fs.writeFileSync(`${target}.forge-lock`, 'existing lock')
  assert.throws(() => installConfig(target, descriptor), /EEXIST/)
  assert.equal(fs.readFileSync(`${target}.forge-lock`, 'utf8'), 'existing lock')
  fs.unlinkSync(`${target}.forge-lock`)
  const symlink = path.join(root, 'linked.json')
  fs.symlinkSync(target, symlink)
  assert.throws(() => installConfig(symlink, descriptor), /symlink/)
  assert.ok(!fs.readdirSync(path.dirname(target)).some(file => file.endsWith('.tmp')))
  console.log('PASS: shared agent schemas, safe JSONC/TOML merge, backups, idempotency and conflicts')
} finally {
  // Only this test's unique directory is disposable.
  fs.rmSync(root, { recursive: true, force: true })
}
