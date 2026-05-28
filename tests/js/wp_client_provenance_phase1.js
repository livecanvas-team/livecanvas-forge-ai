const assert = require('assert')

const { WPClient } = require('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/src/wp-client.js')

class CaptureClient extends WPClient {
  constructor(config = {}) {
    super({
      restBase: 'http://example.test/wp-json/lcfa/v1/',
      agent: 'codex',
      transport: 'stdio',
      ...config,
    })
    this.calls = []
  }

  async request(method, route, options = {}) {
    this.calls.push({ method, route, options })
    return { ok: true }
  }
}

(async () => {
  const client = new CaptureClient()

  await client.suggestCommand({ action: 'page_upsert', title: 'Pricing' })
  await client.runCommand({ action: 'page_upsert', title: 'Pricing' })
  await client.getNextAgentRequest('codex', 'req-123')
  await client.completeAgentRequest('req-123', {
    ok: true,
    message: 'Page updated by Codex.',
    provenance: {
      origin: 'mcp_agent',
      transport: 'mcp_stdio',
      agent: 'codex',
      processed_by: 'codex_mcp',
    },
  })
  await client.getAgentHandoffPackage({ limit: 3 })

  assert.strictEqual(client.calls.length, 5, 'suggestCommand, runCommand, agent queue helpers, and handoff package should call the REST API once each')

  const suggestBody = client.calls[0].options.body
  assert.strictEqual(client.calls[0].route, 'command/suggest', 'suggestCommand should call command/suggest')
  assert.strictEqual(suggestBody._lcfa_origin, 'mcp_agent', 'MCP suggestions should declare MCP agent origin')
  assert.strictEqual(suggestBody._lcfa_transport, 'mcp_stdio', 'MCP suggestions should declare stdio transport')
  assert.strictEqual(suggestBody._lcfa_agent, 'codex', 'MCP suggestions should declare the configured Codex agent')
  assert.strictEqual(suggestBody._lcfa_processed_by, 'forge_local_rules', 'MCP suggestions are still prepared by Forge local rules')

  const runBody = client.calls[1].options.body
  assert.strictEqual(client.calls[1].route, 'command', 'runCommand should call command')
  assert.strictEqual(runBody._lcfa_origin, 'mcp_agent', 'MCP command executions should declare MCP agent origin')
  assert.strictEqual(runBody._lcfa_transport, 'mcp_stdio', 'MCP command executions should declare stdio transport')
  assert.strictEqual(runBody._lcfa_agent, 'codex', 'MCP command executions should declare the configured Codex agent')
  assert.strictEqual(runBody._lcfa_processed_by, 'codex_mcp', 'MCP command executions should declare Codex as the processor')

  assert.strictEqual(client.calls[2].method, 'GET', 'getNextAgentRequest should read from the agent queue')
  assert.strictEqual(client.calls[2].route, 'agent/request', 'getNextAgentRequest should call agent/request')
  assert.strictEqual(client.calls[2].options.query.agent, 'codex', 'getNextAgentRequest should ask for the configured Codex queue')
  assert.strictEqual(client.calls[2].options.query.request_id, 'req-123', 'getNextAgentRequest should support claiming one exact frontend request')
  assert.strictEqual(client.calls[2].options.query.claim, '1', 'getNextAgentRequest should opt into claiming when a request id is provided')

  const completeBody = client.calls[3].options.body
  assert.strictEqual(client.calls[3].method, 'POST', 'completeAgentRequest should write the agent queue result')
  assert.strictEqual(client.calls[3].route, 'agent/request/complete', 'completeAgentRequest should call agent/request/complete')
  assert.strictEqual(completeBody.request_id, 'req-123', 'completeAgentRequest should send the request id')
  assert.strictEqual(completeBody.result.provenance.processed_by, 'codex_mcp', 'completeAgentRequest should preserve Codex MCP result provenance')

  assert.strictEqual(client.calls[4].method, 'GET', 'getAgentHandoffPackage should read from the REST API')
  assert.strictEqual(client.calls[4].route, 'studio/handoff-package', 'getAgentHandoffPackage should call the dedicated handoff package endpoint')
  assert.strictEqual(client.calls[4].options.query.limit, 3, 'getAgentHandoffPackage should pass the requested run limit')

  console.log('PASS wp_client_provenance_phase1')
})().catch((error) => {
  console.error(error && error.stack ? error.stack : error)
  process.exit(1)
})
