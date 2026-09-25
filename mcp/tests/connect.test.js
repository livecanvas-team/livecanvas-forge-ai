const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const os = require('node:os')
const { version } = require('../package.json')
const { decodeDescriptor, connect, runConnect, FULL_ACCESS_SCOPES } = require('../src/connect')

async function main() {
  const root = fs.realpathSync(fs.mkdtempSync(path.join(os.tmpdir(), 'forge-connect-test-')))
  const base = { schema: 'forge.connect.v1', client: 'cursor', attempt: 'a'.repeat(32), fingerprint: 'site-1', package_version: version, site_url: 'https://example.test/', rest_base: 'https://example.test/wp-json/lcfa/v1/' }
  const encode = value => Buffer.from(JSON.stringify(value)).toString('base64url')
  const approved = []
  const authFactory = config => {
    approved.push(config)
    return { waitForAuthorization: async () => ({ ok: true, token: 'never-print-me' }), resolve: async () => ({ ok: false, status: 'pairing_pending', user_code: 'ABCD-1234', verification_url: 'https://example.test/wp-admin/' }) }
  }
  try {
    assert.equal(decodeDescriptor(encode(base)).client, 'cursor')
    for (const override of [
      { client: 'unrecognized' }, { client: 'claude' }, { package_version: 'wrong' }, { attempt: 'short' },
      { site_url: 'https://user:password@example.test/' }, { rest_base: 'https://other.test/wp-json/lcfa/v1/' },
      { runtime_package: `https://other.test/livecanvas-ai-bridge-mcp-${version}.tgz` },
      { site_url: 'http://production.com/', rest_base: 'http://production.com/wp-json/lcfa/v1/', allow_local_http: true },
      { site_url: 'http://example.local/', rest_base: 'http://example.local/wp-json/lcfa/v1/' }
    ]) assert.throws(() => decodeDescriptor(encode({ ...base, ...override })))
    const local = { ...base, site_url: 'http://example.local/', rest_base: 'http://example.local/wp-json/lcfa/v1/', allow_local_http: true }
    assert.equal(decodeDescriptor(encode(local)).site_url, local.site_url)
    for (const directory of ['x&whoami', 'x%20y', 'x!USERNAME!', 'x(p)']) {
      await assert.rejects(() => connect({ ...base, runtime_package: `https://example.test/${directory}/livecanvas-ai-bridge-mcp-${version}.tgz` }, { platform: 'win32', workspace: root, authFactory }), /Windows launcher/)
    }
    const windows = await connect(base, { platform: 'win32', workspace: path.join(root, 'windows'), authFactory })
    assert.equal(windows.state, 'reload_required')
    const windowsServer = Object.values(JSON.parse(fs.readFileSync(windows.installation.path)).mcpServers)[0]
    assert.equal(windowsServer.command, 'cmd')
    for (const client of ['codex', 'opencode', 'cursor', 'claude-code', 'claude-desktop']) {
      const result = await connect({ ...base, client }, { workspace: path.join(root, client), home: root, platform: 'darwin', authFactory })
      assert.equal(result.state, 'reload_required', 'The helper must not claim the client loaded MCP.')
      assert.ok(fs.existsSync(result.installation.path))
      assert.ok(!JSON.stringify(result).includes('never-print-me'))
      const config = approved.at(-1)
      assert.equal(config.agent, client)
      assert.equal(config.pairingScopes, FULL_ACCESS_SCOPES.join(','))
      assert.equal(config.connectionAttempt, base.attempt)
    }
    const output = [], progress = []
    const code = await runConnect(['--descriptor', encode(base), '--workspace', path.join(root, 'cli'), '--no-wait'], { authFactory, output: v => output.push(v), progress: v => progress.push(v) })
    assert.equal(code, 2)
    assert.equal(output[0].state, 'pairing_pending')
    assert.equal(progress[0].user_code, 'ABCD-1234')
    assert.ok(!JSON.stringify([...output, ...progress]).includes('never-print-me'))
    await assert.rejects(() => runConnect(['--force'], { output: () => {} }), /Unknown/)
    console.log('PASS: same installer for five declared clients, site-bound descriptors, Windows command validation, Full Access scopes and no credential output')
  } finally { fs.rmSync(root, { recursive: true, force: true }) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
