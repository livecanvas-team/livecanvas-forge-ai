const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { spawnSync } = require('node:child_process')

const sourceRoot = path.resolve(__dirname, '..')
const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-mcp-lazy-runtime-'))

try {
  fs.cpSync(sourceRoot, temporaryRoot, {
    recursive: true,
    filter(source) {
      return path.basename(source) !== 'node_modules'
    }
  })

  const cliPath = path.join(temporaryRoot, 'src', 'cli.js')
  const result = spawnSync(process.execPath, ['-e', `require(${JSON.stringify(cliPath)})`], {
    encoding: 'utf8'
  })

  assert.equal(result.status, 0, `CLI should load without optional Sass or Playwright modules.\n${result.stderr}`)
  assert.equal(result.stderr, '')
} finally {
  fs.rmSync(temporaryRoot, { recursive: true, force: true })
}

process.stdout.write('PASS\n')
