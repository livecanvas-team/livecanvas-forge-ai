const assert = require('node:assert/strict')
const { loadConfig } = require('../src/config')

function run() {
  const toolArgs = {
    action: 'page_upsert',
    body_html: '<section class="container" data-test="alpha=beta">Test</section>'
  }
  const config = loadConfig([
    '--site-url=https://example.com',
    '--tool=run_lc_command',
    `--tool-args=${JSON.stringify(toolArgs)}`
  ])

  assert.deepEqual(config.toolArgs, toolArgs, 'inline tool JSON should preserve equals signs inside HTML attributes')
  assert.equal(config.tool, 'run_lc_command')
  assert.equal(config.restBase, 'https://example.com/wp-json/lcfa/v1/')
}

run()
console.log('PASS')
