const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { WindPressCompiler } = require('../src/windpress-compiler')

function makeWpRoot() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-windpress-'))
  fs.mkdirSync(path.join(root, 'wp-content/plugins/windpress/build/assets'), { recursive: true })

  return root
}

function writeAsset(root, relativePath, content = 'export {}') {
  const filePath = path.join(root, 'wp-content/plugins/windpress/build', relativePath)
  fs.mkdirSync(path.dirname(filePath), { recursive: true })
  fs.writeFileSync(filePath, content)

  return filePath
}

function createCompiler(wpRoot) {
  return new WindPressCompiler({
    client: {},
    config: { wpRoot }
  })
}

function run() {
  const manifestRoot = makeWpRoot()

  try {
    const v4Path = writeAsset(
      manifestRoot,
      'assets/tailwindcss-newhash.js',
      'export { a as compile, b as getCandidates, c as loadSource, d as optimize }'
    )
    const v3Path = writeAsset(
      manifestRoot,
      'assets/tailwindcss-v3-newhash.js',
      'export { a as build, b as optimize }'
    )

    fs.writeFileSync(
      path.join(manifestRoot, 'wp-content/plugins/windpress/build/manifest.json'),
      JSON.stringify({
        'assets/packages/core/tailwindcss/index.ts': {
          file: 'assets/tailwindcss-newhash.js'
        },
        'assets/packages/core/tailwindcss-v3/index.ts': {
          file: 'assets/tailwindcss-v3-newhash.js'
        }
      })
    )

    const manifestCompiler = createCompiler(manifestRoot)

    assert.equal(manifestCompiler.resolveCompilerAssetPath(4), v4Path, 'Tailwind v4 compiler should be resolved from WindPress manifest')
    assert.equal(manifestCompiler.resolveCompilerAssetPath(3), v3Path, 'Tailwind v3 compiler should be resolved from WindPress manifest')
  } finally {
    fs.rmSync(manifestRoot, { recursive: true, force: true })
  }

  const fallbackRoot = makeWpRoot()

  try {
    writeAsset(
      fallbackRoot,
      'assets/tailwindcss-large.js',
      'export { a as n, b as t }'
    )
    const v4Path = writeAsset(
      fallbackRoot,
      'assets/tailwindcss-wrapper.js',
      'export { a as compile, b as getCandidates, c as loadSource, d as optimize }'
    )
    const v3Path = writeAsset(
      fallbackRoot,
      'assets/tailwindcss-v3-wrapper.js',
      'export { a as build, b as optimize }'
    )

    const fallbackCompiler = createCompiler(fallbackRoot)

    assert.equal(fallbackCompiler.resolveCompilerAssetPath(4), v4Path, 'Tailwind v4 compiler fallback should choose the named-export wrapper')
    assert.equal(fallbackCompiler.resolveCompilerAssetPath(3), v3Path, 'Tailwind v3 compiler fallback should choose the named-export wrapper')
  } finally {
    fs.rmSync(fallbackRoot, { recursive: true, force: true })
  }
}

try {
  run()
  console.log('PASS')
} catch (error) {
  console.error(error)
  process.exit(1)
}
