const assert = require('node:assert/strict')
const { WPClient } = require('../src/wp-client')
const { createToolRegistry } = require('../src/tool-registry')

async function main() {
  const client = new WPClient({ restBase: 'http://fixture.invalid/', agent: 'codex' })
  let stopped = false; let ack = 0; let writes = 0; let finishTool; let toolStarted
  const started = new Promise(resolve => { toolStarted = resolve })
  const calls = []
  client.request = async (method, route, options) => {
    calls.push(route)
    if (route === 'agent/request/claim') return { request: { id: options.body.request_id || 'request-fixture', status: 'running' }, lease_token: 'private-fixture' }
    if (route === 'agent/request/renew') return { request: { status: stopped ? 'stop_requested' : 'running' } }
    if (route === 'agent/request/acknowledge-stop') { ack++; return { request: { status: 'stopped' } } }
    if (route === 'agent/request') return { request: { id: options.query.request_id, status: 'running' } }
    if (route === 'command') {
      writes++; toolStarted(); await new Promise(resolve => { finishTool = resolve }); return { result: { ok: true } }
    }
    throw new Error('Unexpected request')
  }
  const registry = createToolRegistry(client, {}, {})
  try {
    await client.getNextAgentRequest('codex', 'request-fixture')
    const lease = client.agentLeases.get('request-fixture')
    const running = registry.invoke('update_discussion_settings', { target_id: 1 })
    await started
    stopped = true
    await lease.timer._onTimeout()
    assert.equal(ack, 0, 'Do not acknowledge Stop while a Bridge tool is running')
    await assert.rejects(registry.invoke('update_discussion_settings', { target_id: 2 }), /Stop requested/)
    assert.equal(writes, 1)
    await assert.rejects(client.completeAgentRequest('request-fixture', {}), /current Bridge tools/)
    finishTool(); await running
    assert.equal(ack, 1, 'Acknowledge only after in-flight tools finish')
    assert.equal(lease.stopAcknowledged, true)
    await assert.rejects(registry.invoke('update_discussion_settings', { target_id: 3 }), /Stop requested/)
    await assert.rejects(client.getNextAgentRequest('codex'), /different explicit request ID/)
    await assert.rejects(client.getNextAgentRequest('codex', 'request-fixture'), /different explicit request ID/)
    stopped = false
    await client.getNextAgentRequest('codex', 'request-new')
    assert.equal(client.agentLeases.has('request-fixture'), false)
    assert.equal(client.agentLeases.has('request-new'), true)
    assert.equal(JSON.stringify(await client.getAgentRequest('request-new')).includes('private-fixture'), false)
  } finally { for (const id of client.agentLeases.keys()) client.releaseAgentLease(id) }

  const retrying = new WPClient({ restBase: 'http://fixture.invalid/', agent: 'codex' })
  let acknowledgementAttempts = 0
  retrying.request = async (method, route) => {
    if (route === 'agent/request/renew') return { request: { status: 'stop_requested' } }
    if (route === 'agent/request/acknowledge-stop') {
      if (++acknowledgementAttempts < 3) throw new Error('Temporary network failure')
      return { request: { status: 'stopped' } }
    }
    throw new Error('Unexpected request')
  }
  retrying.keepAgentLease('request-retry', 'private-retry-capability')
  try {
    const lease = retrying.agentLeases.get('request-retry')
    await lease.timer._onTimeout()
    assert.equal(lease.stopAcknowledged, undefined)
    assert.equal(lease.timer._destroyed, false, 'Unconfirmed Stop must retain its acknowledgement retry')
    await assert.rejects(retrying.withFrontendWork(async () => assert.fail('Stopped work resumed')), /Stop requested/)
    assert.equal(acknowledgementAttempts, 2)
    assert.equal(lease.stopAcknowledged, undefined)
    await lease.timer._onTimeout()
    assert.equal(acknowledgementAttempts, 3)
    assert.equal(lease.stopAcknowledged, true)
    assert.equal(lease.timer._destroyed, true)
    assert.match(lease.error.message, /Stop requested/)
  } finally { retrying.releaseAgentLease('request-retry') }

  const originalFetch = global.fetch
  const bound = new WPClient({ restBase: 'http://fixture.invalid/', agent: 'codex', sessionToken: 'fixture-session' })
  bound.keepAgentLease('request-header', 'hidden-worker-capability')
  global.fetch = async (url, options) => {
    assert.equal(options.headers['X-LCFA-Request-ID'], 'request-header')
    assert.equal(options.headers['X-LCFA-Worker-Lease'], 'hidden-worker-capability')
    return { ok: true, text: async () => '{"ok":true}' }
  }
  try { await bound.getWriteContext({ target_id: 42 }) }
  finally { global.fetch = originalFetch; bound.releaseAgentLease('request-header') }
  console.log('PASS stop blocks new tools, waits for in-flight work, preserves private capabilities and requires an explicit new request')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
