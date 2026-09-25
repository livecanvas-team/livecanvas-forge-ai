const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { WindPressCompiler } = require('../src/windpress-compiler')

async function run() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-dist-contract-'))
  try {
    const dist = path.join(root, 'wp-content/plugins/windpress/assets/dist')
    fs.mkdirSync(path.join(dist, 'assets'), { recursive: true })
    fs.writeFileSync(path.join(dist, 'assets/tailwindcss-hash.js'), 'export { compile, optimize, loadSource, getCandidates }')
    fs.writeFileSync(path.join(dist, 'manifest.json'), JSON.stringify({ 'resources/packages/core/tailwindcss/index.ts': { file: 'assets/tailwindcss-hash.js' } }))
    const compiler = new WindPressCompiler({ client: {}, config: { wpRoot: root } })
    assert.equal(compiler.resolveCompilerAssetPath(4), path.join(dist, 'assets/tailwindcss-hash.js'))
    fs.writeFileSync(path.join(dist, 'manifest.json'), JSON.stringify({ 'resources/packages/core/tailwindcss/index.ts': { file: '../../../../../../outside.js' } }))
    assert.equal(compiler.resolveCompilerAssetFromManifest(path.join(dist, 'manifest.json'), 'resources/packages/core/tailwindcss/index.ts'), '')
  } finally { fs.rmSync(root, { recursive: true, force: true }) }

  let saves = 0; let revision = 'a'; let statusReads = 0; let css = '.flex{display:flex}'; let main = '@import "tailwindcss";'; let saveOK = true; let truncated = false
  const client = {
    getWindPressStatus: async () => ({ available: true, source_revision: statusReads++ ? revision : 'a', providers: [{ id: 'posts' }] }),
    getWindPressVolume: async () => ({ entries: [{ relative_path: 'main.css', content: main }] }),
    scanWindPressProviderFull: async () => ({ ok: true, metadata: { truncated }, contents: [{ content: Buffer.from('flex').toString('base64') }] }),
    saveWindPressCache: async () => { saves++; return { result: { ok: saveOK, message: 'save rejected' } } }
  }
  const compiler = new WindPressCompiler({ client, config: {} })
  compiler.loadCompiler = async () => ({ compile: async () => ({ sources: [], build: () => css }), loadSource: async () => [], getCandidates: async () => ['flex'], optimize: async value => ({ code: value }) })
  compiler.prepareExternalPlugins = async volume => volume // Compiler behavior is isolated; no network.
  assert.equal((await compiler.buildCache({ store: false })).verification_states.compiled, true)
  assert.equal(saves, 0)
  statusReads = 0; saveOK = false
  await assert.rejects(() => compiler.buildCache(), /save rejected/)
  assert.equal(saves, 1, 'A rejected save must not report success')
  statusReads = 0; revision = 'b'
  await assert.rejects(() => compiler.buildCache(), /stale_sources/)
  assert.equal(saves, 1, 'Stale sources must never reach storage')
  statusReads = 0; revision = 'a'; main = '@plugin "daisyui";'
  await assert.rejects(() => compiler.buildCache(), /Required daisyui did not compile/)
  main = '/* @plugin "@tailwindcss/typography"; */ @import "tailwindcss";'; statusReads = 0
  assert.equal((await compiler.buildCache({ store: false })).plugins.typography, false, 'Commented plugins must not be loaded or advertised')
  truncated = true
  await assert.rejects(() => compiler.buildCache(), /truncated/)
  console.log('PASS: assets/dist manifest, traversal, compile evidence, stale/truncated sources, required plugins and failed storage')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
