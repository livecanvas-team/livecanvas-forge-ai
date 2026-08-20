const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { ThemeFilesystem } = require('../src/theme-files')

async function run() {
  const fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-theme-backup-'))
  const wpRoot = path.join(fixtureRoot, 'app', 'public')
  const themesRoot = path.join(wpRoot, 'wp-content', 'themes')
  const childRoot = path.join(themesRoot, 'picowind-child')
  const parentRoot = path.join(themesRoot, 'picowind')
  const backupsDirectory = path.join(fixtureRoot, 'backups')

  fs.mkdirSync(childRoot, { recursive: true })
  fs.mkdirSync(parentRoot, { recursive: true })
  fs.writeFileSync(path.join(wpRoot, 'wp-config.php'), '<?php // fixture\n')

  const themeFiles = new ThemeFilesystem({
    client: {
      async getSnapshot() {
        return {
          snapshot: {
            current_theme_stylesheet: 'picowind-child',
            current_theme_template: 'picowind',
            detected_framework: 'picowind',
            site_mode: 'local'
          }
        }
      },
      async getMcpStatus() {
        return { mcp: { filesystem_mode: 'local-theme-access' } }
      }
    },
    config: {
      wpRoot,
      backupsDirectory,
      allowParentThemeWrites: false
    }
  })

  try {
    const relativePath = 'lcfa-created-file.css'
    const absolutePath = path.join(childRoot, relativePath)
    const created = await themeFiles.writeFile({
      root_scope: 'stylesheet',
      path: relativePath,
      content: 'body { --created: 1; }\n',
      dry_run: false
    })

    assert.equal(created.created, true, 'new theme write should report created=true')
    assert.ok(created.backup_id, 'new theme write should return a tombstone backup id')
    assert.equal(fs.existsSync(absolutePath), true, 'new theme write should create the file')

    const listed = await themeFiles.listBackups({ path: relativePath })
    assert.equal(listed.backups.length, 1, 'new theme write should be listed as a restorable backup')
    assert.equal(listed.backups[0].original_exists, false, 'new-file backup should record that the original did not exist')
    assert.equal(listed.backups[0].restore_action, 'delete_created_file', 'new-file backup should advertise delete rollback')

    const preview = await themeFiles.restoreBackup({
      backup_id: created.backup_id,
      dry_run: true
    })
    assert.equal(preview.deleted, true, 'rollback preview should report that it would delete the created file')
    assert.equal(fs.existsSync(absolutePath), true, 'rollback preview must not delete the file')

    const restored = await themeFiles.restoreBackup({
      backup_id: created.backup_id,
      dry_run: false
    })
    assert.equal(restored.deleted, true, 'rollback should report deletion of the created file')
    assert.equal(restored.restore_action, 'delete_created_file', 'rollback should expose the tombstone restore action')
    assert.equal(fs.existsSync(absolutePath), false, 'rollback should remove a file that did not exist before the write')

    const existingPath = path.join(childRoot, 'existing.css')
    fs.writeFileSync(existingPath, 'body { color: black; }\n')
    const changed = await themeFiles.writeFile({
      root_scope: 'stylesheet',
      path: 'existing.css',
      content: 'body { color: white; }\n',
      dry_run: false
    })
    assert.ok(changed.backup_id, 'existing-file write should return its content backup id')

    const restoredExisting = await themeFiles.restoreBackup({
      backup_id: changed.backup_id,
      dry_run: false
    })
    assert.equal(restoredExisting.restored_from_backup.original_exists, true, 'content rollback should preserve the original-exists marker')
    assert.equal(fs.readFileSync(existingPath, 'utf8'), 'body { color: black; }\n', 'content rollback should restore the previous file contents')
  } finally {
    fs.rmSync(fixtureRoot, { recursive: true, force: true })
  }
}

run()
  .then(() => console.log('PASS'))
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
