const assert = require('assert')

const { WPClient } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js')
const { createToolRegistry } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/tool-registry.js')

class MockClient extends WPClient {
  constructor() {
    super({
      restBase: 'http://example.test/wp-json/lcfa/v1/',
      agent: 'codex',
      transport: 'stdio',
    })
    this.calls = []
  }

  async getNextAgentRequest(agent = null, requestId = '') {
    this.calls.push({ name: 'getNextAgentRequest', agent, requestId })
    return { request: { id: 'req-123', status: 'running' } }
  }

  async completeAgentRequest(requestId, result = {}, thread = null) {
    this.calls.push({ name: 'completeAgentRequest', requestId, result, thread })
    return { request: { id: requestId, status: 'completed' } }
  }

  async failAgentRequest(requestId, message, thread = null) {
    this.calls.push({ name: 'failAgentRequest', requestId, message, thread })
    return { request: { id: requestId, status: 'failed' } }
  }
}

(async () => {
  const client = new MockClient()
  const registry = createToolRegistry(client, {}, {})
  const tools = registry.list()
  const toolNames = tools.map((tool) => tool.name)

  assert.ok(toolNames.includes('get_frontend_prompt_request'), 'tool registry should expose get_frontend_prompt_request')
  assert.ok(toolNames.includes('complete_frontend_prompt_request'), 'tool registry should expose complete_frontend_prompt_request')
  assert.ok(toolNames.includes('fail_frontend_prompt_request'), 'tool registry should expose fail_frontend_prompt_request')

  await registry.invoke('get_frontend_prompt_request', { agent: 'codex', request_id: 'req-123' })
  await registry.invoke('complete_frontend_prompt_request', {
    request_id: 'req-123',
    result: { ok: true, message: 'Updated by Codex.' },
  })
  await registry.invoke('fail_frontend_prompt_request', {
    request_id: 'req-124',
    message: 'No valid page HTML generated.',
  })

  assert.deepStrictEqual(client.calls.map((call) => call.name), [
    'getNextAgentRequest',
    'completeAgentRequest',
    'failAgentRequest',
  ], 'frontend prompt tools should call the WPClient queue helpers')

  assert.strictEqual(client.calls[0].agent, 'codex', 'get_frontend_prompt_request should pass the requested agent')
  assert.strictEqual(client.calls[0].requestId, 'req-123', 'get_frontend_prompt_request should pass the exact queued request id when provided')
  assert.strictEqual(client.calls[1].requestId, 'req-123', 'complete_frontend_prompt_request should pass the request id')
  assert.strictEqual(client.calls[2].message, 'No valid page HTML generated.', 'fail_frontend_prompt_request should pass the failure reason')

  console.log('PASS agent_request_tool_registry_phase1')
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error)
  process.exit(1)
})
