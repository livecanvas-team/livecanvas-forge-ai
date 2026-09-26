#!/usr/bin/env node
'use strict'

// Read-only diagnostic commands. Never starts a server, lists conversations,
// reads account credentials, or sends a turn to a model provider.
const { execFile } = require('node:child_process')
const { promisify } = require('node:util')
const path = require('node:path')

const execute = promisify(execFile)
const DEFAULT_BINARY = '/Applications/ChatGPT.app/Contents/Resources/codex'

function version(value) {
  return typeof value === 'string' && /^[0-9]+\.[0-9]+\.[0-9]+(?:[-+.][a-zA-Z0-9.+-]+)?$/.test(value) && value.length <= 80
    ? value : null
}

async function preflight({ binary = DEFAULT_BINARY, run = execute, env = process.env } = {}) {
  if (typeof binary !== 'string' || !path.isAbsolute(binary) || binary.includes('\0')) {
    throw new Error('The diagnostic requires an absolute Codex executable path.')
  }
  // Do not forward provider keys or unrelated application credentials.
  const safeEnv = {}
  for (const name of ['PATH', 'HOME', 'TMPDIR', 'SYSTEMROOT', 'CODEX_HOME']) {
    if (typeof env[name] === 'string') safeEnv[name] = env[name]
  }
  const options = { encoding: 'utf8', timeout: 5000, maxBuffer: 64 * 1024, windowsHide: true, env: safeEnv }
  const result = {
    diagnostic: 'codex_desktop_preflight',
    cli_version: null,
    daemon_version: null,
    daemon_reachable: false,
    desktop_conversation_verified: false,
    frontend_chat_available: false,
    state: 'needs_action',
    code: 'codex_executable_unavailable',
    provider_request_sent: false,
    conversation_accessed: false,
    settings_changed: false
  }
  try {
    const output = await run(binary, ['--version'], options)
    result.cli_version = version(String(output.stdout || '').trim().replace(/^codex-cli /, ''))
  } catch {
    return result
  }
  if (!result.cli_version) {
    result.code = 'codex_version_unrecognized'
    return result
  }
  try {
    const output = await run(binary, ['app-server', 'daemon', 'version'], options)
    const data = JSON.parse(output.stdout)
    result.daemon_version = version(data?.appServerVersion)
    if (!result.daemon_version) {
      result.code = 'daemon_response_unrecognized'
      return result
    }
    result.daemon_reachable = true
    // A live daemon may be independent of Desktop. Its version never attests
    // which process owns a particular Desktop conversation.
    result.code = 'desktop_binding_unverified'
  } catch {
    // Do not print stderr, paths, configuration, or an untrusted server payload.
    // A failed probe can mean missing socket, permissions, timeout or bad JSON.
    result.code = 'daemon_probe_unavailable'
  }
  return result
}

if (require.main === module) {
  preflight({ binary: process.env.LCFA_CODEX_BINARY || DEFAULT_BINARY }).then(result => {
    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`)
    // This diagnostic cannot qualify a Desktop binding, even with a daemon.
    process.exitCode = 2
  }).catch(() => {
    process.stderr.write('Codex Desktop preflight configuration is invalid.\n')
    process.exitCode = 1
  })
}

module.exports = { preflight }
