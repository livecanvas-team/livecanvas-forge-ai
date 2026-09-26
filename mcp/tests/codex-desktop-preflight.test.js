'use strict'

const assert = require('node:assert/strict')
const { test } = require('node:test')
const { preflight } = require('../../tests/integration/codex-desktop-preflight')

function fixture(response, failure) {
  const calls = []
  const run = async (binary, args, options) => {
    calls.push({ binary, args, options })
    if (args[0] === '--version') return { stdout: 'codex-cli 0.155.0-alpha.16.4\n' }
    if (failure) throw failure
    return { stdout: response }
  }
  return { run, calls }
}

test('a reachable daemon never qualifies the Desktop conversation or enables chat', async () => {
  const f = fixture(JSON.stringify({ appServerVersion: '0.155.0-alpha.16.4', account: 'private fixture data' }))
  const r = await preflight({ run: f.run, env: { HOME: '/synthetic', OPENAI_API_KEY: 'private-key', PRIVATE_DATA: 'private-value' } })
  assert.equal(r.daemon_reachable, true)
  assert.equal(r.code, 'desktop_binding_unverified')
  assert.equal(r.desktop_conversation_verified, false)
  assert.equal(r.frontend_chat_available, false)
  assert.equal(r.state, 'needs_action')
  assert.equal(r.provider_request_sent, false)
  assert.equal(r.conversation_accessed, false)
  assert.equal(r.settings_changed, false)
  assert.deepEqual(f.calls.map(c => c.args), [['--version'], ['app-server', 'daemon', 'version']])
  assert.deepEqual(f.calls[0].options.env, { HOME: '/synthetic' })
  assert.equal(f.calls[0].options.timeout, 5000)
  assert.equal(f.calls[0].options.maxBuffer, 65536)
  assert.ok(!JSON.stringify(r).includes('private'))
})

test('missing or inaccessible daemon fails closed and does not disclose command errors', async () => {
  const f = fixture('', new Error('private account and path'))
  const r = await preflight({ run: f.run })
  assert.equal(r.code, 'daemon_probe_unavailable')
  assert.equal(r.daemon_reachable, false)
  assert.ok(!JSON.stringify(r).includes('private'))
  assert.equal(f.calls.length, 2)
})

test('unrecognized daemon responses cannot become connection evidence', async () => {
  for (const payload of ['null', '{}', '{"appServerVersion":"secret path"}', '{"appServerVersion":42}', JSON.stringify({ appServerVersion: '0.155.0\nprivate' })]) {
    const f = fixture(payload)
    const r = await preflight({ run: f.run })
    assert.equal(r.code, 'daemon_response_unrecognized')
    assert.equal(r.daemon_reachable, false)
    assert.equal(r.daemon_version, null)
  }
  const r = await preflight({ run: fixture('not JSON').run })
  assert.equal(r.code, 'daemon_probe_unavailable')
})

test('missing or unrecognized executable version stops before probing the daemon', async () => {
  let calls = 0
  const r = await preflight({ run: async () => { calls++; throw Error('private stderr') } })
  assert.equal(r.code, 'codex_executable_unavailable')
  assert.equal(calls, 1)
  const bad = await preflight({ run: async () => { calls++; return { stdout: 'private config' } } })
  assert.equal(bad.code, 'codex_version_unrecognized')
  assert.equal(calls, 2)
})

test('relative and NUL-bearing executable paths are rejected without executing anything', async () => {
  for (const binary of ['codex', './codex', '/tmp/codex\0--run', null]) {
    await assert.rejects(preflight({ binary, run: () => { throw Error('must not execute') } }), /absolute Codex executable/)
  }
})
