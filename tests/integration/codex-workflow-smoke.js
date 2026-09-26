// Real Codex CLI qualification for read-only workflow discovery. This is
// deliberately NOT evidence of a shared Codex Desktop conversation.
const assert = require('node:assert/strict')
const { spawn } = require('node:child_process')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const host = process.env.LCFA_TEST_HOST
assert.ok(['test-ai-forge.local', 'marketing-rocks.local'].includes(host), 'Explicit authorized test host required')
const workspace = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-codex-workflow-'))
fs.chmodSync(workspace, 0o700)
const config = {
  command: process.execPath,
  args: [path.join(__dirname, 'site-readonly-mcp.js')],
  env: { LCFA_TEST_HOST: host },
  required: true,
  startup_timeout_sec: 30,
  tool_timeout_sec: 60,
  enabled_tools: ['get_connection_handoff', 'list_workflows', 'read_workflow', 'get_write_context']
}
function toml(value) {
  if (value && typeof value === 'object' && !Array.isArray(value)) return `{ ${Object.entries(value).map(([key, item]) => `${key} = ${toml(item)}`).join(', ')} }`
  if (Array.isArray(value)) return `[${value.map(toml).join(', ')}]`
  return JSON.stringify(value)
}
const overrides = Object.entries(config).flatMap(([key, value]) => ['-c', `mcp_servers.livecanvas_workflow_test.${key}=${toml(value)}`])
const prompt = `Run a read-only LiveCanvas AI Bridge integration test for http://${host}/. Use only the livecanvas_workflow_test MCP tools, never shell or files. Call get_connection_handoff with limit 1, list_workflows, read_workflow for dynamic-template, and get_write_context with target_type site. Do not apply changes, create content, publish, compile or alter settings. Briefly report the verified theme/framework, whether DaisyUI is compiled, and which write prerequisites the workflow requires. Report errors honestly. Do not output filesystem roots, personal project names, credentials, or signed proof values.`
const child = spawn('/Applications/ChatGPT.app/Contents/Resources/codex', [
  'exec', '--ephemeral', '--ignore-user-config', '--skip-git-repo-check', '--sandbox', 'read-only', '--json', '-C', workspace,
  ...overrides, prompt
], { stdio: ['ignore', 'pipe', 'pipe'] })
let output = ''
let stderr = ''
let timedOut = false
child.stdout.on('data', data => { output += data })
child.stderr.on('data', data => { stderr += data })
const timer = setTimeout(() => { timedOut = true; child.kill('SIGTERM') }, 180000)
child.on('error', error => { clearTimeout(timer); console.error(error.message); process.exitCode = 1 })
child.on('close', code => {
  clearTimeout(timer)
  const events = output.split('\n').flatMap(line => { try { return [JSON.parse(line)] } catch { return [] } })
  const calls = events.filter(event => event.type === 'item.completed' && event.item?.type === 'mcp_tool_call').map(event => ({
    tool: event.item.tool,
    status: event.item.status,
    is_error: Boolean(event.item.error || event.item.result?.isError)
  }))
  const expected = config.enabled_tools
  const complete = code === 0 && expected.every(tool => calls.some(call => call.tool === tool && call.status === 'completed' && !call.is_error))
  const final = events.filter(event => event.type === 'item.completed' && event.item?.type === 'agent_message').map(event => event.item.text).join('\n')
  const errors = events.filter(event => event.type === 'error').map(event => event.message || event.error?.message || 'Codex error')
  console.log(JSON.stringify({ ok: complete, site: host, client: 'Codex CLI', same_desktop_conversation: false,
    timed_out: timedOut, exit_code: code, calls, final, errors,
    startup_error: complete ? '' : stderr.split('\n').filter(line => /error|failed|invalid/i.test(line)).slice(-5).join('\n') }, null, 2))
  process.exitCode = complete ? 0 : 1
})
