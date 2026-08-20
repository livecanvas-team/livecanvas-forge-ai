const assert = require('node:assert/strict')
const { loadConfig } = require('../src/config')

function run() {
  const previousUsername = process.env.LCFA_HTTP_BASIC_USERNAME
  const previousPassword = process.env.LCFA_HTTP_BASIC_PASSWORD
  const previousFramework = process.env.LCFA_FRAMEWORK
  const previousToolProfile = process.env.LCFA_TOOL_PROFILE
  const previousAgentWorkspaceRoot = process.env.LCFA_AGENT_WORKSPACE_ROOT
  process.env.LCFA_HTTP_BASIC_USERNAME = 'protected-user'
  process.env.LCFA_HTTP_BASIC_PASSWORD = 'protected-pass'
  process.env.LCFA_FRAMEWORK = 'Picowind'
  process.env.LCFA_TOOL_PROFILE = 'compact'
  process.env.LCFA_AGENT_WORKSPACE_ROOT = '/Users/example/project'
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
  assert.equal(config.httpBasicUsername, 'protected-user')
  assert.equal(config.httpBasicPassword, 'protected-pass')
  assert.equal(config.framework, 'picowind')
  assert.equal(config.toolProfile, 'compact')
  assert.equal(config.agentWorkspaceRoot, '/Users/example/project')

  if (previousUsername === undefined) {
    delete process.env.LCFA_HTTP_BASIC_USERNAME
  } else {
    process.env.LCFA_HTTP_BASIC_USERNAME = previousUsername
  }
  if (previousPassword === undefined) {
    delete process.env.LCFA_HTTP_BASIC_PASSWORD
  } else {
    process.env.LCFA_HTTP_BASIC_PASSWORD = previousPassword
  }
  if (previousFramework === undefined) {
    delete process.env.LCFA_FRAMEWORK
  } else {
    process.env.LCFA_FRAMEWORK = previousFramework
  }
  if (previousToolProfile === undefined) {
    delete process.env.LCFA_TOOL_PROFILE
  } else {
    process.env.LCFA_TOOL_PROFILE = previousToolProfile
  }
  if (previousAgentWorkspaceRoot === undefined) {
    delete process.env.LCFA_AGENT_WORKSPACE_ROOT
  } else {
    process.env.LCFA_AGENT_WORKSPACE_ROOT = previousAgentWorkspaceRoot
  }
}

run()
console.log('PASS')
