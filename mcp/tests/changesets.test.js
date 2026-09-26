const assert = require('node:assert/strict')
const { createToolRegistry } = require('../src/tool-registry')
const { WPClient } = require('../src/wp-client')
const { formatToolResultForMcp } = require('../src/mcp-stdio-server')
async function run() {
  const requests = []
  const client = new WPClient({ restBase: 'http://fixture.invalid/' })
  client.request = async (...args) => { requests.push(args); return { ok: true } }
  const tools = createToolRegistry(client, {}, {}, null, null, null, { toolProfile: 'compact' })
  const list = tools.list().find(tool => tool.name === 'list_changesets')
  const undo = tools.list().find(tool => tool.name === 'undo_changeset')
  assert.ok(list && undo, 'Compact clients must discover changeset tools')
  assert.equal(list.annotations.readOnlyHint, true)
  assert.equal(undo.annotations.readOnlyHint, false)
  assert.equal(undo.annotations.destructiveHint, true)
  assert.ok(undo.inputSchema.required.includes('write_context'))
  assert.equal(undo.inputSchema.properties.write_context.type, 'object')
  const payload = { changeset_id: 'fixture', target_id: 42, dry_run: true, write_context: { fingerprint: 'unchanged-proof' }, acknowledge_shared: true }
  await tools.invoke('list_changesets', { limit: 3 })
  await tools.invoke('undo_changeset', payload)
  assert.deepEqual(requests, [['GET', 'changesets', { query: { limit: 3 } }], ['POST', 'changesets/undo', { body: payload }]])
  const result = { ok: true, changeset: { id: 'fixture', status: 'saved', undo_available: true }, verification_states: { saved: true, compiled: 'not_checked', visually_verified: 'not_checked', published: false } }
  const compact = formatToolResultForMcp('content_patch_apply', { result }).result
  assert.deepEqual(compact.changeset, result.changeset)
  assert.deepEqual(compact.verification_states, result.verification_states)
  console.log('PASS changeset tool discovery, payload routing and compact result preservation')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
