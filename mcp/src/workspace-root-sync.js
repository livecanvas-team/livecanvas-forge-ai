const fs = require('node:fs')
const path = require('node:path')

function resolveWorkspaceRoot({ agentWorkspaceRoot = '', wpRoot = '', cwd = process.cwd(), existsSync = fs.existsSync } = {}) {
  const normalizedWpRoot = resolveWordPressRoot({ wpRoot, cwd, existsSync })
  const candidates = [agentWorkspaceRoot, cwd, inferAgentWorkspaceRoot(normalizedWpRoot)]

  for (const candidate of candidates) {
    const normalized = normalizeWorkspaceRoot(candidate)

    if (!normalized || looksLikeRuntimeWorkspaceRoot(normalized) || !existsSync(normalized)) {
      continue
    }

    if (normalizedWpRoot && !isPathInside(normalizedWpRoot, normalized)) {
      continue
    }

    return normalized
  }

  return ''
}

function resolveWordPressRoot({ wpRoot = '', cwd = process.cwd(), existsSync = fs.existsSync } = {}) {
  for (const candidate of [wpRoot, cwd]) {
    const normalized = normalizeWorkspaceRoot(candidate)

    if (!normalized || looksLikeRuntimeWorkspaceRoot(normalized)) {
      continue
    }

    if (looksLikeWordPressRoot(normalized, existsSync)) {
      return normalized
    }
  }

  return ''
}

async function syncWorkspaceRoot({ client, config, cwd = process.cwd(), existsSync = fs.existsSync, logger = console } = {}) {
  const wordpressRoot = resolveWordPressRoot({
    wpRoot: config && typeof config.wpRoot === 'string' ? config.wpRoot : '',
    cwd,
    existsSync
  })
  const workspaceRoot = resolveWorkspaceRoot({
    agentWorkspaceRoot: config && typeof config.agentWorkspaceRoot === 'string' ? config.agentWorkspaceRoot : '',
    wpRoot: config && typeof config.wpRoot === 'string' ? config.wpRoot : '',
    cwd,
    existsSync
  })

  if (!workspaceRoot) {
    return {
      ok: false,
      skipped: true,
      reason: 'workspace_root_unavailable'
    }
  }

  try {
    await client.syncWorkspaceRoot({
      agent_workspace_root: workspaceRoot,
      wordpress_root: wordpressRoot,
      workspace_root: workspaceRoot,
      source: 'mcp-bridge',
      agent: config && typeof config.agent === 'string' ? config.agent : ''
    })

    return {
      ok: true,
      skipped: false,
      workspaceRoot,
      wordpressRoot
    }
  } catch (error) {
    if (config && config.verbose && logger && typeof logger.error === 'function') {
      logger.error(`[lcfa] Workspace root sync failed: ${error.message}`)
    }

    return {
      ok: false,
      skipped: false,
      workspaceRoot,
      error
    }
  }
}

function looksLikeWordPressRoot(candidate, existsSync) {
  return existsSync(path.join(candidate, 'wp-content')) && existsSync(path.join(candidate, 'wp-config.php'))
}

function normalizeWorkspaceRoot(candidate) {
  if (typeof candidate !== 'string') {
    return ''
  }

  const trimmed = candidate.trim()
  if (!trimmed) {
    return ''
  }

  if (trimmed === path.sep) {
    return trimmed
  }

  return trimmed.replace(/[\\/]+$/, '')
}

function inferAgentWorkspaceRoot(wpRoot) {
  const normalized = normalizeWorkspaceRoot(wpRoot)
  if (!normalized) {
    return ''
  }

  if (path.basename(normalized) === 'public' && path.basename(path.dirname(normalized)) === 'app') {
    return path.dirname(path.dirname(normalized))
  }

  return normalized
}

function isPathInside(candidate, root) {
  const relative = path.relative(root, candidate)
  return relative === '' || (!relative.startsWith('..') && !path.isAbsolute(relative))
}

function looksLikeRuntimeWorkspaceRoot(candidate) {
  return [
    '/wordpress',
    '/app',
    '/app/public',
    '/var/www',
    '/var/www/html',
    '/srv/www',
    '/srv/www/html',
    '/usr/share/nginx/html'
  ].includes(candidate)
}

module.exports = {
  resolveWorkspaceRoot,
  resolveWordPressRoot,
  syncWorkspaceRoot
}
