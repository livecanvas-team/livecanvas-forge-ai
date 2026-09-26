const fs = require('node:fs')
const path = require('node:path')
const crypto = require('node:crypto')
const VERSION = require('../package.json').version
const FULL_SCOPES = ['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo']

function fail(code, status = 403) { const error = new Error(code); error.code = code; error.status = status; throw error }

function readCapability(filename, identity) {
  if (!filename || !path.isAbsolute(filename) || process.platform === 'win32') fail('private_bridge_capability_required')
  let descriptor
  try {
    const canonical = fs.realpathSync(filename)
    const root = fs.realpathSync(identity.wordpress_root)
    if (canonical === root || canonical.startsWith(root + path.sep)) fail('bridge_capability_must_be_outside_webroot')
    const parent = fs.statSync(path.dirname(canonical))
    if (!parent.isDirectory() || (parent.mode & 0o077) || parent.uid !== process.getuid()) fail('private_bridge_capability_required')
    descriptor = fs.openSync(filename, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW)
    const stat = fs.fstatSync(descriptor)
    if (!stat.isFile() || stat.nlink !== 1 || stat.size > 4096 || (stat.mode & 0o077) || stat.uid !== process.getuid()) fail('private_bridge_capability_required')
    const data = JSON.parse(fs.readFileSync(descriptor, 'utf8'))
    if (data.version !== 1 || !/^[A-Za-z0-9_-]{43}$/.test(data.token || '') || Buffer.from(data.token, 'base64url').length !== 32) fail('invalid_bridge_capability')
    if (!Number.isSafeInteger(data.expires_at) || data.expires_at <= Date.now() / 1000 || data.expires_at > Date.now() / 1000 + 3600) fail('expired_bridge_capability')
    if (data.session_id !== identity.session_id || data.site_fingerprint !== identity.site_fingerprint || data.owner_user_id !== identity.owner_user_id) fail('bridge_capability_identity_mismatch')
    return data
  } catch (error) {
    if (error.code?.startsWith('bridge_') || error.code?.endsWith('_bridge_capability') || error.code === 'private_bridge_capability_required') throw error
    fail('private_bridge_capability_unavailable')
  } finally { if (descriptor !== undefined) fs.closeSync(descriptor) }
}

function verifyIdentity(response, config) {
  const identity = response?.identity
  if (!response?.ok || !identity || !identity.session_id || !Number.isSafeInteger(identity.owner_user_id) || identity.owner_user_id < 1) fail('owned_session_required')
  if (identity.required_runtime !== VERSION) fail('bridge_runtime_update_required')
  if (!config.siteFingerprint || identity.site_fingerprint !== config.siteFingerprint) fail('bridge_site_mismatch')
  if (identity.client !== config.agent || FULL_SCOPES.some(scope => !identity.scopes?.includes(scope))) fail('approved_full_access_session_required')
  if (!Number.isFinite(Date.parse(identity.expires_at)) || Date.parse(identity.expires_at) <= Date.now()) fail('bridge_session_expired')
  const expectedOrigin = new URL(config.restBase).origin
  if (new URL(identity.site_url).origin !== expectedOrigin || !identity.wordpress_root) fail('bridge_site_mismatch')
  return identity
}

class BridgeSecurity {
  constructor(config, client, identity) {
    this.config = config; this.client = client; this.identity = identity
    this.origin = new URL(identity.site_url).origin
    this.capability = readCapability(config.bridgeTokenFile, identity)
    this.tokenHash = crypto.createHash('sha256').update(this.capability.token).digest()
    this.invalid = false
  }

  static async create(config, client) {
    if (config.host !== '127.0.0.1') fail('bridge_loopback_required')
    // Resolve an already-approved cache before identity verification. A pending
    // pairing never starts a listener. Running listeners never repair credentials.
    const status = await client.getMcpStatus()
    if (!status?.mcp) fail('owned_session_required')
    const identity = verifyIdentity(await client.getTransportIdentity(), config)
    return new BridgeSecurity(config, client, identity)
  }

  checkAddress(request, port) {
    if (request.headers.host !== `127.0.0.1:${port}` || request.socket.remoteAddress !== '127.0.0.1') fail('bridge_host_rejected')
    if (request.headers.origin !== undefined && request.headers.origin !== this.origin) fail('bridge_origin_rejected')
    if (request.headers.origin === undefined && request.headers['sec-fetch-mode']) fail('bridge_origin_required')
    if (!request.url?.startsWith('/') || request.url.startsWith('//') || request.url.includes('?') || request.url.includes('#')) fail('bridge_url_rejected')
  }

  async authorize(request, port) {
    this.checkAddress(request, port)
    if (this.invalid) fail('bridge_session_unavailable')
    const bearer = /^Bearer ([A-Za-z0-9_-]{43})$/.exec(request.headers.authorization || '')
    if (!bearer || !crypto.timingSafeEqual(this.tokenHash, crypto.createHash('sha256').update(bearer[1]).digest())) fail('bridge_auth_required', 401)
    // Removing/rotating the private file immediately revokes this listener.
    let capability
    try { capability = readCapability(this.config.bridgeTokenFile, this.identity) }
    catch (error) { this.invalid = true; throw error }
    if (capability.token !== this.capability.token || capability.expires_at !== this.capability.expires_at) { this.invalid = true; fail('bridge_capability_changed') }
    let identity
    try { identity = verifyIdentity(await this.client.getTransportIdentity(), this.config) }
    catch (error) { this.invalid = true; fail('bridge_session_unavailable') }
    if (identity.session_id !== this.identity.session_id || identity.owner_user_id !== this.identity.owner_user_id || identity.wordpress_root !== this.identity.wordpress_root) {
      this.invalid = true; fail('bridge_identity_changed')
    }
    return identity
  }
}

module.exports = { BridgeSecurity, readCapability, verifyIdentity, fail }
