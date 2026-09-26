const assert = require('node:assert/strict')
const { WPClient } = require('../src/wp-client')
async function main() {
  const client = new WPClient({ restBase: 'http://fixture.invalid/', agent: 'codex' })
  const calls = []
  client.request = async (method, route, options) => {
    calls.push({ method, route, options })
    if (route === 'agent/request/claim') return { request: { id: 'request-fixture', status: 'running' }, lease_token: 'fixture-worker-capability' }
    if (route === 'agent/request/renew') return { request: { id: 'request-fixture', status: 'running' } }
    return { request: { id: 'request-fixture', status: 'completed' } }
  }
  try {
    const claim = await client.getNextAgentRequest('codex', 'request-fixture')
    assert.equal(calls[0].method, 'POST')
    assert.equal(calls[0].route, 'agent/request/claim')
    assert.equal(calls[0].options.body.request_id, 'request-fixture')
    assert.equal(JSON.stringify(claim).includes('fixture-worker-capability'), false, 'Worker capabilities must stay out of model-visible output')
    await client.getAgentRequest('request-fixture')
    assert.equal(calls[1].method, 'GET')
    assert.equal(calls[1].options.query.claim, undefined, 'Status reads cannot claim work')
    await client.completeAgentRequest('request-fixture', { ok: true })
    assert.equal(calls[2].options.body.lease_token, 'fixture-worker-capability')
    assert.equal(client.agentLeases.size, 0)
    await assert.rejects(client.failAgentRequest('unclaimed', 'No work'), /no current lease/)
    assert.equal(calls.length, 3, 'Unclaimed completion must not send a request')
    await client.getNextAgentRequest('codex')
    const lease = client.agentLeases.get('request-fixture')
    await lease.timer._onTimeout()
    assert.equal(calls.at(-1).route, 'agent/request/renew')
    assert.equal(calls.at(-1).options.body.lease_token, 'fixture-worker-capability')
    client.request = async () => { throw new Error('Connection revoked') }
    await lease.timer._onTimeout()
    assert.ok(lease.error)
    await assert.rejects(client.completeAgentRequest('request-fixture', {}), /no current lease/)
    console.log('PASS private queue POST claims, internal lease renewal, capability redaction and revoked-worker completion rejection')
  } finally { for (const id of client.agentLeases.keys()) client.releaseAgentLease(id) }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
