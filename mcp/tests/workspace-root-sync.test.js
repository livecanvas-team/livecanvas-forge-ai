const assert = require('node:assert/strict')

const {
  resolveWorkspaceRoot,
  syncWorkspaceRoot
} = require('../src/workspace-root-sync')

function createExistsSync(existingPaths) {
  const normalized = new Set(existingPaths)

  return function existsSync(path) {
    return normalized.has(path)
  }
}

async function run() {
  const existsSync = createExistsSync([
    '/Users/commander/Studio/consultala/wp-content',
    '/Users/commander/Studio/consultala/wp-config.php',
    '/Users/commander/Studio/consultala/custom/wp-content',
    '/Users/commander/Studio/consultala/custom/wp-config.php'
  ])

  assert.equal(
    resolveWorkspaceRoot({
      wpRoot: '',
      cwd: '/Users/commander/Studio/consultala',
      existsSync
    }),
    '/Users/commander/Studio/consultala',
    'should infer the workspace root from cwd when the current directory is a WordPress project'
  )

  assert.equal(
    resolveWorkspaceRoot({
      wpRoot: '/Users/commander/Studio/consultala/custom',
      cwd: '/tmp',
      existsSync
    }),
    '/Users/commander/Studio/consultala/custom',
    'should prefer an explicit host-side wpRoot when it points to a valid WordPress project'
  )

  assert.equal(
    resolveWorkspaceRoot({
      wpRoot: '/wordpress',
      cwd: '/',
      existsSync
    }),
    '',
    'should not infer a workspace root from runtime-only mount paths'
  )

  const calls = []
  const syncResult = await syncWorkspaceRoot({
    client: {
      async syncWorkspaceRoot(payload) {
        calls.push(payload)
        return { result: { ok: true } }
      }
    },
    config: {
      agent: 'opencode',
      wpRoot: '',
      verbose: false
    },
    cwd: '/Users/commander/Studio/consultala',
    existsSync
  })

  assert.equal(syncResult.ok, true, 'successful sync should report ok')
  assert.equal(syncResult.workspaceRoot, '/Users/commander/Studio/consultala', 'sync should use the inferred cwd workspace root')
  assert.deepEqual(calls[0], {
    workspace_root: '/Users/commander/Studio/consultala',
    source: 'mcp-bridge',
    agent: 'opencode'
  }, 'sync should send the inferred workspace root to WordPress')

  const failureResult = await syncWorkspaceRoot({
    client: {
      async syncWorkspaceRoot() {
        throw new Error('boom')
      }
    },
    config: {
      agent: 'codex',
      wpRoot: '',
      verbose: false
    },
    cwd: '/Users/commander/Studio/consultala',
    existsSync,
    logger: {
      error() {
        throw new Error('logger should not be used when verbose is disabled')
      }
    }
  })

  assert.equal(failureResult.ok, false, 'failed sync should report ok=false')
  assert.equal(failureResult.skipped, false, 'failed sync should not be marked as skipped')

  console.log('PASS')
}

run().catch((error) => {
  console.error(error)
  process.exit(1)
})
