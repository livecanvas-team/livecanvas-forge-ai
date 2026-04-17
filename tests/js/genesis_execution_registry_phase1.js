const assert = require('assert')

const { WPClient } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js')
const { createToolRegistry } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/tool-registry.js')

class MockClient extends WPClient {
  constructor() {
    super({ restBase: 'http://example.test/wp-json/lcfa/v1/' })
  }

  async getGenesisExecutionPlan() {
    return { ok: true }
  }

  async executeGenesisNext(payload = {}) {
    return { ok: true, payload }
  }

  async executeGenesisTask(payload = {}) {
    return { ok: true, payload }
  }
}

const registry = createToolRegistry(new MockClient(), {}, {})
const tools = registry.list()
const toolNames = tools.map((tool) => tool.name)

assert.ok(toolNames.includes('get_genesis_execution_plan'), 'tool registry should expose get_genesis_execution_plan')
assert.ok(toolNames.includes('execute_genesis_next'), 'tool registry should expose execute_genesis_next')
assert.ok(toolNames.includes('execute_genesis_task'), 'tool registry should expose execute_genesis_task')

assert.strictEqual(typeof WPClient.prototype.getGenesisExecutionPlan, 'function', 'WPClient should expose getGenesisExecutionPlan')
assert.strictEqual(typeof WPClient.prototype.executeGenesisNext, 'function', 'WPClient should expose executeGenesisNext')
assert.strictEqual(typeof WPClient.prototype.executeGenesisTask, 'function', 'WPClient should expose executeGenesisTask')

console.log('PASS genesis_execution_registry_phase1')
