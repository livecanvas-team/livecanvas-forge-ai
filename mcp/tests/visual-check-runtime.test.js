const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')

const { VisualCheck } = require('../src/visual-check')

async function run() {
  if (process.env.LCFA_VISUAL_E2E !== '1') {
    console.log('SKIP: set LCFA_VISUAL_E2E=1 after installing Playwright Chromium')
    return
  }

  const visualCheck = new VisualCheck({ config: {} })
  const readiness = await visualCheck.getReadiness({ probe_launch: true })
  assert.equal(readiness.ready, true, readiness.message || 'Chromium should be ready')
  assert.equal(readiness.launch_verified, true, 'visual check should launch Chromium')

  const outputDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-visual-runtime-'))
  const result = await visualCheck.run({
    url: 'data:text/html,<title>AI%20Bridge</title><main><h1%20id="title">Visual%20check</h1></main>',
    output_directory: outputDirectory,
    wait_ms: 0,
    full_page: true,
    viewports: [
      { name: 'desktop', width: 1440, height: 900 },
      { name: 'mobile', width: 390, height: 844 }
    ],
    selectors: ['#title']
  })

  assert.equal(result.ok, true, result.message || 'visual check should complete')
  assert.equal(result.results.length, 2, 'visual check should cover desktop and mobile')
  for (const entry of result.results) {
    assert.equal(entry.analysis.shell.mains, 1)
    assert.equal(entry.analysis.selectors['#title'].found, true)
    assert.equal(fs.existsSync(entry.screenshot_path), true, 'visual check should write its screenshot')
  }
}

run()
  .then(() => console.log('PASS'))
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
