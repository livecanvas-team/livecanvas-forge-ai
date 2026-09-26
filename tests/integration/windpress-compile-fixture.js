// Test-only local compiler. No provider or coding agent is invoked.
const path = require('node:path')
const { execFileSync } = require('node:child_process')
const { WindPressCompiler } = require('../../mcp/src/windpress-compiler')
const { WPClient } = require('../../mcp/src/wp-client')
if (process.env.LCFA_TEST_HOST !== 'marketing-rocks.local' || process.env.LCFA_TEST_WP_ROOT !== '/Users/commander/Local Sites/marketing-rocks/app/public') throw new Error('Authorized MarketingRocks fixture required')
if (!process.env.LCFA_TEST_DB_SOCKET?.startsWith('/')) throw new Error('Compiler fixture database socket is missing')
function call(op, args = {}) {
  return JSON.parse(execFileSync('php', ['-d', `mysqli.default_socket=${process.env.LCFA_TEST_DB_SOCKET}`, path.join(__dirname, 'wordpress-compiler-api.php')], {
    input: JSON.stringify({ op, args }), env: process.env, maxBuffer: 128 * 1024 * 1024, timeout: 60000
  }).toString())
}
const client = {
  getWindPressStatus: async () => call('status'),
  getWindPressVolume: async args => call('volume', args),
  scanWindPressProvider: async (provider_id, metadata = {}) => call('scan', { provider_id, metadata }),
  async scanWindPressProviderFull(id, args) { return WPClient.prototype.scanWindPressProviderFull.call(this, id, args) }
}
const compiler = new WindPressCompiler({ client, config: { wpRoot: process.env.LCFA_TEST_WP_ROOT } })
compiler.buildCache({ store: false, source_map: true }).then(result => {
  process.stdout.write(JSON.stringify({ css: result.css.sourcemap ? result.css.normal : result.css.minified, sourcemap: result.css.sourcemap || '', source_revision: result.source_revision, candidates: result.candidate_count, plugins: result.plugins, compiler: compiler.resolveCompilerAssetPath(4) }))
}).catch(error => { process.stderr.write(error.message); process.exitCode = 1 })
