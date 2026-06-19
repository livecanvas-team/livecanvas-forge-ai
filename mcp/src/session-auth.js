const crypto = require('node:crypto')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')

class SessionAuth {
  constructor(config) {
    this.config = config
    this.cachePath = resolveCachePath(config)
  }

  async resolve() {
    if (this.config.token) {
      return {
        ok: true,
        type: 'legacy_mcp_token',
        token: this.config.token
      }
    }

    if (this.config.sessionToken) {
      return {
        ok: true,
        type: 'ai_bridge_session',
        token: this.config.sessionToken
      }
    }

    const cached = readCache(this.cachePath)
    if (cached && cached.session_token && !isExpired(cached.expires_at)) {
      this.config.sessionToken = String(cached.session_token)
      return {
        ok: true,
        type: 'ai_bridge_session',
        token: this.config.sessionToken,
        sessionId: cached.session_id || ''
      }
    }

    if (cached && cached.pairing_id && cached.device_secret && !isExpired(cached.pairing_expires_at)) {
      return this.checkPairing(cached)
    }

    return this.startPairing()
  }

  async startPairing() {
    const response = await this.request('POST', 'mcp/pairing/start', {
      client: this.config.agent || 'codex',
      project_label: this.config.projectLabel || 'Codex project',
      site_fingerprint: this.config.siteFingerprint || '',
      scopes: normalizePairingScopes(this.config.pairingScopes)
    })

    if (!response.ok) {
      return pairingError(response)
    }

    const cache = {
      pairing_id: response.pairing_id || '',
      device_secret: response.device_secret || '',
      user_code: response.user_code || '',
      verification_url: response.verification_url || '',
      pairing_expires_at: response.expires_at || ''
    }
    writeCache(this.cachePath, cache)

    return pairingPending(cache)
  }

  async checkPairing(cached) {
    const url = new URL('mcp/pairing/status', this.config.restBase)
    url.searchParams.set('pairing_id', cached.pairing_id)
    url.searchParams.set('device_secret', cached.device_secret)

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json'
      }
    })
    const payload = await parseResponse(response)

    if (!response.ok || payload.ok === false) {
      clearCache(this.cachePath)
      return pairingError(payload)
    }

    if (payload.status === 'approved' && payload.session_token) {
      const cache = {
        session_id: payload.session_id || '',
        session_token: payload.session_token,
        expires_at: payload.expires_at || ''
      }
      writeCache(this.cachePath, cache)
      this.config.sessionToken = payload.session_token

      return {
        ok: true,
        type: 'ai_bridge_session',
        token: payload.session_token,
        sessionId: payload.session_id || ''
      }
    }

    if (payload.status === 'expired') {
      clearCache(this.cachePath)
      return this.startPairing()
    }

    return pairingPending({
      pairing_id: cached.pairing_id,
      user_code: payload.user_code || cached.user_code || '',
      verification_url: cached.verification_url || '',
      pairing_expires_at: payload.expires_at || cached.pairing_expires_at || ''
    })
  }

  async request(method, route, body = null) {
    const url = new URL(route, this.config.restBase)
    const options = {
      method,
      headers: {
        Accept: 'application/json'
      }
    }

    if (body !== null) {
      options.headers['Content-Type'] = 'application/json'
      options.body = JSON.stringify(body)
    }

    const response = await fetch(url, options)
    const payload = await parseResponse(response)

    return {
      ...payload,
      ok: response.ok && payload.ok !== false
    }
  }
}

async function parseResponse(response) {
  const text = await response.text()
  if (!text) {
    return {}
  }

  try {
    return JSON.parse(text)
  } catch (error) {
    return {
      ok: false,
      message: text
    }
  }
}

function pairingPending(cache) {
  return {
    ok: false,
    auth_required: true,
    auth_method: 'ai_bridge_pairing',
    status: 'pairing_pending',
    pairing_id: cache.pairing_id || '',
    user_code: cache.user_code || '',
    verification_url: cache.verification_url || '',
    expires_at: cache.pairing_expires_at || '',
    message: `Approve Codex pairing in WordPress. User code: ${cache.user_code || 'pending'}`
  }
}

function pairingError(payload) {
  return {
    ok: false,
    auth_required: true,
    auth_method: 'ai_bridge_pairing',
    status: 'pairing_failed',
    message: payload && payload.message ? String(payload.message) : 'AI Bridge pairing failed.'
  }
}

function resolveCachePath(config) {
  const key = crypto
    .createHash('sha256')
    .update(`${config.restBase}|${config.siteFingerprint || ''}|${config.projectLabel || ''}`)
    .digest('hex')
    .slice(0, 24)

  return path.join(os.homedir(), '.livecanvas-ai-bridge', `${key}.json`)
}

function readCache(filePath) {
  try {
    if (!fs.existsSync(filePath)) {
      return null
    }
    const payload = JSON.parse(fs.readFileSync(filePath, 'utf8'))
    return payload && typeof payload === 'object' ? payload : null
  } catch (error) {
    return null
  }
}

function writeCache(filePath, payload) {
  const directory = path.dirname(filePath)
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 })
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2), { mode: 0o600 })
}

function clearCache(filePath) {
  try {
    fs.unlinkSync(filePath)
  } catch (error) {
    // Ignore missing cache files.
  }
}

function isExpired(value) {
  if (!value) {
    return false
  }

  const timestamp = Date.parse(String(value))
  return Number.isFinite(timestamp) && timestamp <= Date.now()
}

function normalizePairingScopes(value) {
  const allowed = new Set(['read', 'preview', 'write'])
  const scopes = String(value || 'read,preview,write')
    .split(',')
    .map((scope) => scope.trim().toLowerCase())
    .filter((scope) => allowed.has(scope))

  if (!scopes.includes('read')) {
    scopes.unshift('read')
  }

  return Array.from(new Set(scopes))
}

module.exports = {
  SessionAuth,
  normalizePairingScopes
}
