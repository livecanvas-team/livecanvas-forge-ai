const crypto = require('node:crypto')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const { applyHttpBasicAuth } = require('./http-auth')

class SessionAuth {
  constructor(config) {
    this.config = config
    this.cachePath = resolveCachePath(config)
  }

  async resolve({ signal } = {}) {
    signal?.throwIfAborted()
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
      return this.checkPairing(cached, { signal })
    }

    return this.startPairing({ signal })
  }

  invalidateSession() {
    this.config.sessionToken = ''
    clearCache(this.cachePath)
  }

  async startPairing({ signal } = {}) {
    const agentLabel = formatAgentLabel(this.config.agent)
    const response = await this.request('POST', 'mcp/pairing/start', {
      client: this.config.agent || 'codex',
      project_label: this.config.projectLabel || `${agentLabel} project`,
      site_fingerprint: this.config.siteFingerprint || '',
      connection_mode: this.config.wpRoot ? 'local' : 'remote',
      scopes: normalizePairingScopes(this.config.pairingScopes),
      ...(this.config.connectionAttempt ? { connection_attempt: this.config.connectionAttempt } : {})
    }, { signal })

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

    return pairingPending(cache, this.config.agent)
  }

  async checkPairing(cached, { signal } = {}) {
    const url = new URL('mcp/pairing/status', this.config.restBase)
    const options = {
      method: this.config.connectionAttempt ? 'POST' : 'GET',
      redirect: 'error',
      headers: applyHttpBasicAuth({
        Accept: 'application/json'
      }, this.config),
      signal: requestSignal(signal)
    }
    if (this.config.connectionAttempt) {
      options.headers['Content-Type'] = 'application/json'
      options.body = JSON.stringify({ pairing_id: cached.pairing_id, device_secret: cached.device_secret })
    } else {
      // Older servers accept GET only. New attempts never put the device secret in a URL.
      url.searchParams.set('pairing_id', cached.pairing_id)
      url.searchParams.set('device_secret', cached.device_secret)
    }
    const response = await fetch(url, options)
    const payload = await parseResponse(response)

    if (payload.status === 'expired') {
      clearCache(this.cachePath)
      if (this.config.connectionAttempt) return pairingError({ message: 'This connection request expired. Start a new connection in WordPress.' })
      return this.startPairing({ signal })
    }

    if (!response.ok || payload.ok === false) {
      clearCache(this.cachePath)
      return pairingError(payload)
    }
    if (this.config.connectionAttempt && payload.status === 'consumed') {
      return pairingError({ message: 'The one-time session token was already retrieved. If the client did not save it, start a new connection in WordPress.' })
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

    return pairingPending({
      pairing_id: cached.pairing_id,
      user_code: payload.user_code || cached.user_code || '',
      verification_url: cached.verification_url || '',
      pairing_expires_at: payload.expires_at || cached.pairing_expires_at || ''
    }, this.config.agent)
  }

  async request(method, route, body = null, { signal } = {}) {
    const url = new URL(route, this.config.restBase)
    const options = {
      method,
      redirect: 'error',
      headers: applyHttpBasicAuth({
        Accept: 'application/json'
      }, this.config),
      signal: requestSignal(signal)
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

  async waitForAuthorization({ timeoutMs = 600000, intervalMs = 2000, onPending = () => {}, signal } = {}) {
    const duration = Number.isFinite(timeoutMs) ? Math.min(Math.max(timeoutMs, 1), 600000) : 600000
    const deadline = Date.now() + duration
    const controller = new AbortController()
    const cancel = () => controller.abort()
    const timeout = setTimeout(cancel, duration)
    if (signal?.aborted) cancel()
    else signal?.addEventListener('abort', cancel, { once: true })
    let lastPairing = ''
    try {
    while (Date.now() < deadline && !controller.signal.aborted) {
      const result = await this.resolve({ signal: controller.signal })
      if (result.ok || result.status !== 'pairing_pending') return result
      if (lastPairing !== result.pairing_id) { onPending(result); lastPairing = result.pairing_id }
      const expires = Date.parse(result.expires_at)
      if (Number.isFinite(expires) && expires <= Date.now()) break
      await new Promise(resolve => {
        const done = () => { clearTimeout(timer); controller.signal.removeEventListener('abort', done); resolve() }
        const timer = setTimeout(done, Math.min(Math.max(intervalMs, 1), Math.max(1, deadline - Date.now())))
        controller.signal.addEventListener('abort', done, { once: true })
        if (controller.signal.aborted) done()
      })
    }
    } catch (error) {
      if (!controller.signal.aborted) throw error
    } finally {
      clearTimeout(timeout)
      signal?.removeEventListener('abort', cancel)
    }
    return { ok: false, status: signal?.aborted ? 'cancelled' : 'authorization_timeout', message: 'Authorization is unfinished. Resume setup while this request is valid, or start a new connection in WordPress.' }
  }
}

function requestSignal(signal) {
  const timeout = AbortSignal.timeout(20000)
  return signal ? AbortSignal.any([signal, timeout]) : timeout
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

function pairingPending(cache, agent = 'codex') {
  const agentLabel = formatAgentLabel(agent)

  return {
    ok: false,
    auth_required: true,
    auth_method: 'ai_bridge_pairing',
    status: 'pairing_pending',
    pairing_id: cache.pairing_id || '',
    user_code: cache.user_code || '',
    verification_url: cache.verification_url || '',
    expires_at: cache.pairing_expires_at || '',
    message: `Approve ${agentLabel} pairing in WordPress. User code: ${cache.user_code || 'pending'}`
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
  const agent = String(config.agent || 'codex').trim().toLowerCase()
  const agentKey = agent === 'codex' ? '' : `|${agent}`
  const key = crypto
    .createHash('sha256')
    .update(`${config.restBase}|${config.siteFingerprint || ''}|${config.projectLabel || ''}${agentKey}${config.connectionAttempt ? `|attempt:${config.connectionAttempt}` : ''}`)
    .digest('hex')
    .slice(0, 24)

  return path.join(os.homedir(), '.livecanvas-ai-bridge', `${key}.json`)
}

function formatAgentLabel(agent) {
  return require('./agent-registry').getAgent(agent || 'codex').label
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
  const allowed = new Set(['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo'])
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
