const assert = require('node:assert/strict')
const path = require('node:path')
const { execFileSync } = require('node:child_process')
const { WindPressCompiler } = require('../../mcp/src/windpress-compiler')
const { PicostrapCompiler } = require('../../mcp/src/picostrap-compiler')
const { WPClient } = require('../../mcp/src/wp-client')

if (!process.env.LCFA_TEST_WP_ROOT) { console.log('SKIP: LCFA_TEST_WP_ROOT is not configured'); process.exit(0) }
function call(op, args = {}) {
  const result = execFileSync('php', ['-d', `mysqli.default_socket=${process.env.LCFA_TEST_DB_SOCKET || ''}`, path.join(__dirname, 'wordpress-compiler-api.php')], {
    input: JSON.stringify({ op, args }), env: process.env, maxBuffer: 128 * 1024 * 1024, timeout: 120000
  }).toString()
  return JSON.parse(result)
}
const client = {
  getWindPressStatus: async () => call('status'),
  getWindPressVolume: async args => call('volume', args),
  scanWindPressProvider: async (providerId, metadata = {}) => call('scan', { provider_id: providerId, metadata }),
  async scanWindPressProviderFull(providerId, args) { return WPClient.prototype.scanWindPressProviderFull.call(this, providerId, args) },
  saveWindPressCache: async (css, sourcemap, full_build, context) => call('store', { ...context, css, sourcemap, full_build }),
  getPicostrapCompileManifest: async () => call('picostrap_manifest'),
  getPicostrapCompileSource: async import_path => call('picostrap_source', { import_path }),
  storePicostrapBundle: async (css, metadata) => call('picostrap_store', { ...metadata, source_fingerprint: metadata.sourceFingerprint, css })
}
async function run() {
  const previousTheme = process.env.LCFA_TEST_ACTIVATE_PICOWIND === '1' ? call('test_theme', { stylesheet: 'picowind-child' }).previous : null
  try {
  const stack = process.env.LCFA_TEST_STACK || 'windpress'
  const store = process.env.LCFA_TEST_STORE === '1'
  const proof = store ? call('context', { target_type: 'site' }) : null
  if (proof) assert.equal(proof.ok, true, JSON.stringify(proof))
  const options = { store, write_context: proof?.write_context, acknowledge_shared: true }
  if (stack === 'windpress') {
    const compiler = new WindPressCompiler({ client, config: { wpRoot: process.env.LCFA_TEST_WP_ROOT } })
    const result = await compiler.buildCache(options)
    assert.equal(result.ok, true)
    assert.ok(result.css.normal.includes('{'))
    console.log(JSON.stringify({ ok: true, stack, compiler: compiler.resolveCompilerAssetPath(4), candidates: result.candidate_count, bytes: result.css.minified.length, plugins: result.plugins, verification_states: result.verification_states }, null, 2))
  } else {
    const compiler = new PicostrapCompiler({ client, config: { wpRoot: process.env.LCFA_TEST_WP_ROOT }, themeFiles: null })
    const result = store ? await compiler.buildBundle(options) : await compiler.compileBundle(options)
    assert.equal(result.ok, true)
    console.log(JSON.stringify({ ok: true, stack, bytes: result.compiled_bytes, source_fingerprint: result.source_fingerprint, verification_states: { saved: store, compiled: true, visually_verified: 'not_checked', published: 'not_applicable' } }, null, 2))
  }
  } finally { if (previousTheme) call('test_theme', { stylesheet: previousTheme }) }
}
run().catch(error => { console.error(error.stack); process.exitCode = 1 })
