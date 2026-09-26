const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { ThemeFilesystem } = require('../src/theme-files')

async function run() {
  const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-journal-routing-'))
  const child = path.join(fixture, 'wp-content/themes/child')
  const parent = path.join(fixture, 'wp-content/themes/parent')
  fs.mkdirSync(child, { recursive: true }); fs.mkdirSync(parent, { recursive: true })
  fs.writeFileSync(path.join(fixture, 'wp-config.php'), '<?php // fixture')
  const requests = []
  const result = { ok: true, changeset: { id: 'private-fixture', status: 'saved' }, verification_states: { saved: true, compiled: 'not_checked' } }
  const client = {
    async validateWriteContext() { return { ok: true, context: { roots: { wordpress: fs.realpathSync(fixture), stylesheet: fs.realpathSync(child), template: fs.realpathSync(parent) }, target: { file_sha256: '' } } } },
    async remoteThemeFileWrite(options) { requests.push(['write', options]); return { result } },
    async remoteThemeFilePreviewWrite(options) { requests.push(['preview', options]); return { result: { ok: true, dry_run: true } } }
  }
  const adapter = new ThemeFilesystem({ client, config: { wpRoot: fixture, backupsDirectory: path.join(fixture, '.lcfa-backups') } })
  try {
    const options = { path: 'views/example.twig', content: '<main>Example</main>', write_context: { fingerprint: 'exact' }, acknowledge_shared: true }
    assert.deepEqual(await adapter.writeFile(options), result)
    assert.deepEqual(requests, [['write', options]])
    assert.equal(fs.existsSync(path.join(child, 'views')), false, 'The adapter must not create directories or write files locally')
    assert.equal(fs.existsSync(path.join(fixture, '.lcfa-backups')), false, 'No local plaintext backup is created')
    assert.equal((await adapter.writeFile({ ...options, dry_run: true })).dry_run, true)
    assert.equal(requests[1][0], 'preview')
    client.remoteThemeFileWrite = async () => { throw new Error('coordinator unavailable') }
    await assert.rejects(() => adapter.writeTemplateFile(options), /coordinator unavailable/)
    assert.equal(fs.existsSync(path.join(child, 'views')), false, 'Coordinator failure cannot trigger a local fallback')
    await assert.rejects(() => adapter.restoreBackup({ backup_id: 'legacy', force: true }), /legacy_backup_review_required/)
    client.validateWriteContext = async () => ({ ok: false, code: 'stale_context', message: 'Refresh context' })
    await assert.rejects(() => adapter.writeFile(options), /stale_context/)
    assert.equal(requests.length, 2, 'Invalid context never reaches the write coordinator')
  } finally { fs.rmSync(fixture, { recursive: true, force: true }) }
  console.log('PASS file writes route to the private WordPress journal with no unsafe fallback')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
