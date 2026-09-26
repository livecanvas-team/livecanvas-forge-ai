// Test-only MCP host. It reuses the deployed plugin runtime and existing local
// credential in memory. No credential is returned to Codex or written to disk.
const { execFileSync } = require('node:child_process')
const path = require('node:path')
const sites = {
  'test-ai-forge.local': { name: 'test-ai-forge', localId: 'noPwcVjz7' },
  'marketing-rocks.local': { name: 'marketing-rocks', localId: 'GyfelnZXH' }
}
const host = process.env.LCFA_TEST_HOST
const site = sites[host]
if (!site) throw new Error('An explicitly authorized test host is required')
const root = `/Users/commander/Local Sites/${site.name}/app/public`
const socket = `/Users/commander/Library/Application Support/Local/run/${site.localId}/mysql/mysqld.sock`
const plugin = path.join(root, 'wp-content/plugins/livecanvas-forge-ai/mcp')
const token = execFileSync('php', ['-d', `mysqli.default_socket=${socket}`, '-r',
  `$_SERVER['HTTP_HOST']=${JSON.stringify(host)}; require ${JSON.stringify(root + '/wp-load.php')}; if(wp_parse_url(home_url(),PHP_URL_HOST)!==${JSON.stringify(host)}) exit(3); echo LCFA_Settings::get_connections()['mcp_token'];`
], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim()
if (!token) throw new Error('No existing local credential is available; no new access was created')
const { WPClient } = require(path.join(plugin, 'src/wp-client'))
const { createToolRegistry } = require(path.join(plugin, 'src/tool-registry'))
const { runStdioServer } = require(path.join(plugin, 'src/mcp-stdio-server'))
const config = { restBase: `http://${host}/wp-json/lcfa/v1/`, token, agent: 'codex', wpRoot: root, toolProfile: 'compact' }
const registry = createToolRegistry(new WPClient(config), {}, {}, null, null, null, config)
const allowed = new Set(['get_connection_handoff', 'list_workflows', 'read_workflow', 'get_write_context'])
const tools = {
  list: () => registry.list().filter(tool => allowed.has(tool.name)),
  has: name => allowed.has(name),
  invoke: (name, args) => {
    if (!allowed.has(name)) throw new Error('This test transport permits only workflow/context reads')
    return registry.invoke(name, args)
  }
}
runStdioServer({ client: null, tools, config }).catch(() => { process.stderr.write('Read-only MCP test host failed\n'); process.exitCode = 1 })
