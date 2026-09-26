const assert = require('node:assert/strict')
const { execFileSync } = require('node:child_process')
const path = require('node:path')
const { workflowDiscovery } = require('../src/workflow-discovery')
const { createToolRegistry } = require('../src/tool-registry')
const { WPClient } = require('../src/wp-client')

// Read the real PHP service, so tool/resource/prompt parity covers shipped bodies.
const service = path.resolve(__dirname, '../../includes/class-lcfa-workflows.php')
function php(expression) {
  return JSON.parse(execFileSync('php', ['-r', `define('ABSPATH', '/tmp/'); require ${JSON.stringify(service)}; echo json_encode(${expression});`], { encoding: 'utf8' }))
}
async function run() {
  const client = {
    listWorkflows: async () => php('LCFA_Workflows::catalog()'),
    readWorkflow: async id => php(`LCFA_Workflows::read(${JSON.stringify(id)})`)
  }
  const tools = createToolRegistry(client, {}, {}, null, null, null, { toolProfile: 'compact' })
  for (const name of ['list_workflows', 'read_workflow']) {
    const tool = tools.list().find(t => t.name === name)
    assert.ok(tool, 'Minimal clients must discover workflow fallback tools')
    assert.equal(tool.annotations.readOnlyHint, true)
    assert.equal(tool.inputSchema.properties.write_context, undefined, 'Reading instructions must not need a write proof')
  }
  const catalog = await tools.invoke('list_workflows')
  const resources = await workflowDiscovery('resources/list', {}, tools)
  const prompts = await workflowDiscovery('prompts/list', {}, tools)
  assert.equal(resources.resources.length, catalog.workflows.length)
  assert.equal(prompts.prompts.length, catalog.workflows.length)
  for (const descriptor of catalog.workflows) {
    const read = await tools.invoke('read_workflow', { id: descriptor.id })
    const resource = await workflowDiscovery('resources/read', { uri: descriptor.uri }, tools)
    const prompt = await workflowDiscovery('prompts/get', { name: descriptor.id, arguments: { target: 'https://example.test/about/' } }, tools)
    assert.equal(resource.contents[0].text, read.workflow.instructions)
    assert.equal(prompt.messages[0].content.text, read.workflow.instructions)
    assert.match(prompt.messages[1].content.text, /Target data to verify/)
  }
  await assert.rejects(workflowDiscovery('resources/read', { uri: 'file:///etc/passwd' }, tools), /Unknown/)
  await assert.rejects(workflowDiscovery('resources/read', { uri: 'livecanvas://workflows/../../secret' }, tools), /Unknown/)
  await assert.rejects(workflowDiscovery('prompts/get', { name: 'missing' }, tools), /Use an ID/)
  await assert.rejects(workflowDiscovery('prompts/get', { name: 'page', arguments: { target: {} } }, tools), /Target/)
  await assert.rejects(workflowDiscovery('resources/list', {}, { invoke: async () => ({ ok: false, message: 'Session revoked' }) }), /Session revoked/)
  assert.equal(await workflowDiscovery('unrelated', {}, tools), null)
  const wp = new WPClient({ restBase: 'http://fixture.invalid/' })
  const requests = []
  wp.request = async (...args) => requests.push(args)
  await wp.listWorkflows()
  await wp.readWorkflow('dynamic-template')
  assert.deepEqual(requests, [['GET', 'workflows'], ['GET', 'workflows/dynamic-template']])
  await assert.rejects(wp.readWorkflow('../wp-config'), /Invalid/)
  console.log('PASS workflow-discovery')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
