const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { ThemeFilesystem } = require('../src/theme-files')

function createClient(stylesheet, template, wpRoot) {
  return {
    async remoteThemeFileWrite(options) {
      // Fixture coordinator, not the local filesystem adapter, performs writes.
      fs.writeFileSync(path.join(wpRoot, 'wp-content/themes', stylesheet, options.path), options.content)
      return { result: { ok: true, writable: true, changeset: { id: 'fixture' } } }
    },
    async validateWriteContext(options) {
      if (stylesheet === template || options.root_scope === 'template') return { ok: false, code: 'parent_theme_read_only', message: 'Parent theme is read-only. Use a child theme.' }
      const root = path.join(wpRoot, 'wp-content/themes', stylesheet)
      const file = path.join(root, options.path)
      return { ok: true, context: { roots: { wordpress: fs.realpathSync(wpRoot), stylesheet: fs.realpathSync(root), template: fs.realpathSync(path.join(wpRoot, 'wp-content/themes', template)) }, target: { file_sha256: fs.existsSync(file) ? require('node:crypto').createHash('sha256').update(fs.readFileSync(file)).digest('hex') : '' } } }
    },
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
      client: createClient('parent-theme', 'parent-theme', wpRoot),
      config: { wpRoot, allowParentThemeWrites: false, backupsDirectory: path.join(wpRoot, '.lcfa-backups') }
    })
    await assert.rejects(() => parentFilesystem.writeFile({
      root_scope: 'stylesheet',
      path: 'style.css',
      content: '/* changed */',
      dry_run: true
    }), /parent theme/i)
    await assert.rejects(
      () => parentFilesystem.writeFile({ root_scope: 'stylesheet', path: 'style.css', content: '/* changed */' }),
      /parent theme/i
    )
    assert.equal(fs.readFileSync(path.join(parentRoot, 'style.css'), 'utf8'), '/* parent */')

    fs.mkdirSync(childRoot, { recursive: true })
    fs.writeFileSync(path.join(childRoot, 'style.css'), '/* child */')
    const childFilesystem = new ThemeFilesystem({
      client: createClient('child-theme', 'parent-theme', wpRoot),
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

    await assert.rejects(() => childFilesystem.writeFile({
      root_scope: 'template',
      path: 'style.css',
      content: '/* parent from child */',
      dry_run: true
    }), /parent theme/i)

    const trustedFilesystem = new ThemeFilesystem({
      client: createClient('parent-theme', 'parent-theme', wpRoot),
      config: { wpRoot, allowParentThemeWrites: true, backupsDirectory: path.join(wpRoot, '.lcfa-backups') }
    })
    await assert.rejects(() => trustedFilesystem.writeFile({
      root_scope: 'stylesheet',
      path: 'style.css',
      content: '/* explicit opt-in */'
    }), /parent theme/i, 'Optional caller flags cannot bypass verified child-theme restrictions')
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
