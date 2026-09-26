'use strict'
const { test } = require('node:test')
const assert = require('node:assert/strict')
const { createToolRegistry } = require('../src/tool-registry')
const { WPClient } = require('../src/wp-client')

test('site knowledge has one read-only MCP path and no agent approval tool', async () => {
  const result = { ok: true, site_knowledge: { state: 'approved', instructions: 'Use concise English.', permissions_granted: false } }
  const client = Object.create(WPClient.prototype)
  client.withFrontendWork = async fn => fn()
  client.request = async (method, path, options) => {
    assert.equal(method, 'GET'); assert.equal(path, 'site-knowledge'); assert.equal(options, undefined); return result
  }
  const tools = createToolRegistry(client, {}, {}, null, null, null, { toolProfile: 'compact' })
  const read = tools.list().find(tool => tool.name === 'get_site_knowledge')
  assert.ok(read); assert.deepEqual(await tools.invoke(read.name, { instructions: 'Untrusted attempt' }), result)
  assert.equal(read.annotations.readOnlyHint, true)
  assert.deepEqual(tools.list().filter(tool => /knowledge|site_instructions/.test(tool.name)).map(tool => tool.name), ['get_site_knowledge'])
  assert.match(read.description, /user-authored context/)
})
