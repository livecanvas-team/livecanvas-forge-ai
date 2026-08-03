const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { ThemeFilesystem } = require('../src/theme-files')

function createClient(stylesheet, template) {
  return {
    async getSnapshot() {
      return {
        snapshot: {
          current_theme_stylesheet: stylesheet,
          current_theme_template: template,
          detected_framework: 'picostrap',
          site_mode: 'local'
        }
      }
    },
    async getMcpStatus() {
      return {
        mcp: {
          filesystem_mode: 'local-theme-access'
        }
      }
    }
  }
}

async function run() {
  const wpRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-theme-files-'))
  const themesRoot = path.join(wpRoot, 'wp-content', 'themes')
  const parentRoot = path.join(themesRoot, 'parent-theme')
  const childRoot = path.join(themesRoot, 'child-theme')

  try {
    fs.mkdirSync(parentRoot, { recursive: true })
    fs.writeFileSync(path.join(parentRoot, 'style.css'), '/* parent */')

    const parentFilesystem = new ThemeFilesystem({
      client: createClient('parent-theme', 'parent-theme'),
      config: { wpRoot, allowParentThemeWrites: false, backupsDirectory: path.join(wpRoot, '.lcfa-backups') }
    })
    const parentPreview = await parentFilesystem.writeFile({
      root_scope: 'stylesheet',
      path: 'style.css',
      content: '/* changed */',
      dry_run: true
    })

    assert.equal(parentPreview.ok, true, 'parent preview should return a guided result')
    assert.equal(parentPreview.writable, false, 'parent theme should be read-only by default')
    assert.equal(parentPreview.status, 'parent_theme_read_only')
    await assert.rejects(
      () => parentFilesystem.writeFile({ root_scope: 'stylesheet', path: 'style.css', content: '/* changed */' }),
      /parent theme/i
    )
    assert.equal(fs.readFileSync(path.join(parentRoot, 'style.css'), 'utf8'), '/* parent */')

    fs.mkdirSync(childRoot, { recursive: true })
    fs.writeFileSync(path.join(childRoot, 'style.css'), '/* child */')
    const childFilesystem = new ThemeFilesystem({
      client: createClient('child-theme', 'parent-theme'),
      config: { wpRoot, allowParentThemeWrites: false, backupsDirectory: path.join(wpRoot, '.lcfa-backups') }
    })
    const childWrite = await childFilesystem.writeFile({
      root_scope: 'stylesheet',
      path: 'style.css',
      content: '/* child changed */'
    })

    assert.equal(childWrite.ok, true)
    assert.equal(childWrite.writable, true)
    assert.equal(fs.readFileSync(path.join(childRoot, 'style.css'), 'utf8'), '/* child changed */')

    const templatePreview = await childFilesystem.writeFile({
      root_scope: 'template',
      path: 'style.css',
      content: '/* parent from child */',
      dry_run: true
    })
    assert.equal(templatePreview.writable, false, 'template scope should stay read-only when it resolves to the parent')

    const trustedFilesystem = new ThemeFilesystem({
      client: createClient('parent-theme', 'parent-theme'),
      config: { wpRoot, allowParentThemeWrites: true, backupsDirectory: path.join(wpRoot, '.lcfa-backups') }
    })
    const trustedWrite = await trustedFilesystem.writeFile({
      root_scope: 'stylesheet',
      path: 'style.css',
      content: '/* explicit opt-in */'
    })
    assert.equal(trustedWrite.writable, true, 'explicit local opt-in should allow parent writes')
  } finally {
    fs.rmSync(wpRoot, { recursive: true, force: true })
  }
}

run()
  .then(() => process.stdout.write('PASS\n'))
  .catch((error) => {
    process.stderr.write(`${error.stack || error.message}\n`)
    process.exit(1)
  })
