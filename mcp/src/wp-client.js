const { SessionAuth } = require('./session-auth')
const MCP_PACKAGE_VERSION = String(require('../package.json').version || 'unknown')
const { applyHttpBasicAuth } = require('./http-auth')

class WPClient {
  constructor(config) {
    this.config = config
    this.restBase = config.restBase
    this.sessionAuth = new SessionAuth(config)
    this.agentLeases = new Map()
    this.activeFrontendTools = 0
  }

  async getSnapshot() {
    return this.request('GET', 'snapshot')
  }

  async getInventory() {
    return this.request('GET', 'inventory')
  }

  async getContext(params = {}) {
    return this.request('GET', 'context', { query: params })
  }

  async getWriteContext(params = {}) {
    return this.request('GET', 'write-context', { query: params })
  }

  async listWorkflows() {
    return this.request('GET', 'workflows')
  }

  async getSiteKnowledge() {
    return this.request('GET', 'site-knowledge')
  }

  async listChangesets(params = {}) {
    return this.request('GET', 'changesets', { query: params })
  }

  async undoChangeset(payload = {}) {
    return this.request('POST', 'changesets/undo', { body: payload })
  }

  async readWorkflow(id) {
    if (typeof id !== 'string' || !/^[a-z][a-z0-9-]{0,63}$/.test(id)) throw new Error('Invalid workflow ID')
    return this.request('GET', `workflows/${encodeURIComponent(id)}`)
  }

  async validateWriteContext(payload = {}) {
    return this.request('POST', 'write-context/validate', { body: payload })
  }

  async getThemeContext(params = {}) {
    return this.request('GET', 'theme-context', { query: params })
  }

  async getGenesisPlan() {
    return this.request('GET', 'genesis/plan')
  }

  async generateGenesisPlan(payload = {}) {
    return this.request('POST', 'genesis/plan/generate', { body: payload })
  }

  async getGenesisExecutionPlan() {
    return this.request('GET', 'genesis/execution-plan')
  }

  async executeGenesisNext(payload = {}) {
    return this.request('POST', 'genesis/execute-next', { body: payload })
  }

  async executeGenesisTask(payload = {}) {
    return this.request('POST', 'genesis/execute-task', { body: payload })
  }

  async getPageHtml(postId) {
    return this.request('GET', 'page-html', {
      query: { post_id: postId }
    })
  }

  async getAcfFields(postType = 'page') {
    return this.request('GET', 'acf-fields', {
      query: { post_type: postType }
    })
  }

  async getBlocksLibrary() {
    return this.request('GET', 'library/blocks')
  }

  async getHistory() {
    return this.request('GET', 'history')
  }

  async getAgentHandoffPackage(params = {}) {
    return this.request('GET', 'studio/handoff-package', { query: params })
  }

  async getHandoffSummary(params = {}) {
    return this.request('GET', 'studio/handoff-summary', { query: params })
  }

  async getConnectionHandoff(params = {}) {
    return this.request('GET', 'studio/connection-handoff', { query: params })
  }

  async getAbilityDiagnostics() {
    return this.request('GET', 'studio/ability-diagnostics')
  }

  async getRuns(params = {}) {
    return this.request('GET', 'studio/runs', { query: params })
  }

  async getBlockPatternLibrary(params = {}) {
    return this.request('GET', 'studio/block-pattern-library', { query: params })
  }

  async getNativePatternPageBlueprints(params = {}) {
    return this.request('GET', 'studio/native-pattern-page-blueprints', { query: params })
  }

  async previewNativePatternPage(payload = {}) {
    return this.request('POST', 'studio/native-pattern-page-preview', { body: payload })
  }

  async applyNativePatternPage(payload = {}) {
    return this.request('POST', 'studio/native-pattern-page-apply', { body: payload })
  }

  async previewContentPatch(payload = {}) {
    return this.request('POST', 'content/patch/preview', {
      body: this.withProvenance(payload, 'content_patch_preview')
    })
  }

  async applyContentPatch(payload = {}) {
    return this.request('POST', 'content/patch/apply', {
      body: this.withProvenance(payload, 'content_patch_apply')
    })
  }

  async remoteThemeFileRead(payload = {}) {
    return this.request('GET', 'theme/file', { query: payload })
  }

  async remoteThemeFilePreviewWrite(payload = {}) {
    return this.request('POST', 'theme/file', {
      body: {
        ...payload,
        dry_run: true
      }
    })
  }

  async remoteThemeFileWrite(payload = {}) {
    return this.request('POST', 'theme/file', {
      body: {
        ...payload,
        dry_run: false
      }
    })
  }

  async remoteThemeFileBackups(payload = {}) {
    return this.request('GET', 'theme/backups', { query: payload })
  }

  async remoteThemeFileRestore(payload = {}) {
    return this.request('POST', 'theme/backup/restore', { body: payload })
  }

  async uploadMedia(payload = {}) {
    return this.request('POST', 'media/upload', { body: payload })
  }

  async replaceMedia(payload = {}) {
    return this.request('POST', 'media/replace', {
      body: this.withProvenance(payload, 'media_replace')
    })
  }

  async getDebugSnapshot(payload = {}) {
    return this.request('GET', 'debug', { query: payload })
  }

  async flushCache(payload = {}) {
    return this.request('POST', 'cache/flush', { body: payload })
  }

  async runPolylangTool(payload = {}) {
    return this.request('POST', 'polylang/tools', { body: payload })
  }

  async runSeoTool(payload = {}) {
    return this.request('POST', 'seo/tools', { body: payload })
  }

  async getThemeBackups(params = {}) {
    return this.request('GET', 'theme/backups', { query: params })
  }

  async getThemeBackup(backupId) {
    return this.request('GET', 'theme/backup', {
      query: { backup_id: backupId }
    })
  }

  async restoreThemeBackup(payload = {}) {
    return this.request('POST', 'theme/backup/restore', {
      body: payload
    })
  }

  async getCommandActions() {
    return this.request('GET', 'command/actions')
  }

  withProvenance(payload = {}, processedBy = 'codex_mcp') {
    const configuredAgent = this.config && this.config.agent ? String(this.config.agent) : 'codex'
    const agent = require('./agent-registry').normalizeAgent(configuredAgent)
    const configuredTransport = this.config && this.config.transport ? String(this.config.transport) : 'stdio'
    const transport = configuredTransport === 'bridge' ? 'mcp_bridge' : 'mcp_stdio'

    return {
      ...payload,
      _lcfa_origin: 'mcp_agent',
      _lcfa_transport: transport,
      _lcfa_agent: agent,
      _lcfa_processed_by: processedBy,
      _lcfa_site_fingerprint: this.config && this.config.siteFingerprint ? String(this.config.siteFingerprint) : ''
    }
  }

  async suggestCommand(payload = {}) {
    return this.request('POST', 'command/suggest', { body: this.withProvenance(payload, 'forge_local_rules') })
  }

  async runCommand(payload) {
    const configuredAgent = this.config && this.config.agent ? String(this.config.agent) : 'codex'
    const agent = require('./agent-registry').normalizeAgent(configuredAgent)
    const processedBy = agent === 'generic' ? 'generic_mcp' : `${agent}_mcp`

    return this.request('POST', 'command', { body: this.withProvenance(payload, processedBy) })
  }

  async getNextAgentRequest(agent = null, requestId = '') {
    if (this.agentLeases.size && (!requestId || [...this.agentLeases.entries()].some(([id, lease]) => !lease.stopAcknowledged || id === requestId))) throw new Error('Finish or stop the current frontend request before claiming another. After Stop, choose a different explicit request ID.')
    const configuredAgent = this.config && this.config.agent ? String(this.config.agent) : 'codex'
    const targetAgent = agent || configuredAgent
    const body = {
      agent: targetAgent
    }

    if (requestId) {
      body.request_id = requestId
    }

    const response = await this.request('POST', 'agent/request/claim', { body })
    if (response?.request?.id && response.lease_token) {
      for (const id of this.agentLeases.keys()) this.releaseAgentLease(id)
      this.keepAgentLease(response.request.id, response.lease_token)
    }
    // The MCP process owns the lease capability; do not send it to model logs.
    const { lease_token, ...publicResponse } = response
    return publicResponse
  }

  keepAgentLease(requestId, token) {
    this.releaseAgentLease(requestId)
    const lease = { token, renewing: false, error: null }
    lease.timer = setInterval(async () => {
      if (lease.renewing) return
      lease.renewing = true
      try {
        if (!lease.stopRequested) await this.checkAgentLease(requestId, lease)
        await this.acknowledgeStoppedWorkers()
      }
      catch (error) {
        // Stop already fences new work. A lost acknowledgement response may
        // be retried idempotently without renewing or resuming that work.
        if (!lease.stopRequested) { lease.error = error; clearInterval(lease.timer) }
      }
      finally { lease.renewing = false }
    }, 30000)
    lease.timer.unref?.()
    this.agentLeases.set(requestId, lease)
  }

  releaseAgentLease(requestId) {
    const lease = this.agentLeases.get(requestId)
    if (lease) clearInterval(lease.timer)
    this.agentLeases.delete(requestId)
  }

  agentLeaseToken(requestId) {
    const lease = this.agentLeases.get(requestId)
    if (!lease || lease.error) throw new Error('This MCP process has no current lease for the frontend request. Inspect its status before retrying; do not run it twice.')
    return lease.token
  }

  async checkAgentLease(requestId, lease) {
    const response = await this.request('POST', 'agent/request/renew', { body: { request_id: requestId, lease_token: lease.token } })
    if (response?.request?.status === 'stop_requested') {
      lease.stopRequested = true
      lease.error = new Error('Stop requested. Do not execute more tools for this frontend request. External shell or agent activity is not stopped by Bridge.')
    } else if (response?.request?.status !== 'running') throw new Error('The frontend request no longer has a running worker lease.')
  }

  async acknowledgeStoppedWorkers() {
    if (this.activeFrontendTools > 0) return
    for (const [id, lease] of this.agentLeases) {
      if (!lease.stopRequested || lease.stopAcknowledged || lease.acknowledging) continue
      lease.acknowledging = true
      try {
        const result = await this.request('POST', 'agent/request/acknowledge-stop', { body: { request_id: id, lease_token: lease.token } })
        if (result?.request?.status !== 'stopped') throw new Error('Bridge stop acknowledgement was not saved.')
        lease.stopAcknowledged = true
        clearInterval(lease.timer)
      } finally { lease.acknowledging = false }
    }
  }

  async withFrontendWork(operation) {
    this.activeFrontendTools++
    try {
      for (const [id, lease] of this.agentLeases) {
        if (lease.error) throw lease.error
        await this.checkAgentLease(id, lease)
        if (lease.error) throw lease.error
      }
      return await operation()
    } finally {
      this.activeFrontendTools--
      // Keep the original tool result/error. Failed acknowledgement remains
      // stop_requested and can retry on heartbeat; never report a false stop.
      try { await this.acknowledgeStoppedWorkers() } catch (error) { /* retry heartbeat */ }
    }
  }

  async getAgentRequest(requestId) {
    return this.request('GET', 'agent/request', {
      query: {
        request_id: requestId
      }
    })
  }

  async completeAgentRequest(requestId, result = {}, thread = null) {
    if (this.activeFrontendTools) throw new Error('Wait for current Bridge tools before completing the frontend request.')
    const body = {
      request_id: requestId,
      lease_token: this.agentLeaseToken(requestId),
      result
    }

    if (thread && typeof thread === 'object') {
      body.thread = thread
    }

    const response = await this.request('POST', 'agent/request/complete', { body })
    this.releaseAgentLease(requestId)
    return response
  }

  async failAgentRequest(requestId, message, thread = null) {
    if (this.activeFrontendTools) throw new Error('Wait for current Bridge tools before failing the frontend request.')
    const body = {
      request_id: requestId,
      lease_token: this.agentLeaseToken(requestId),
      message: String(message || 'Agent request failed.')
    }

    if (thread && typeof thread === 'object') {
      body.thread = thread
    }

    const response = await this.request('POST', 'agent/request/fail', { body })
    this.releaseAgentLease(requestId)
    return response
  }

  async getPicostrapCompileManifest() {
    return this.request('GET', 'picostrap/compile-manifest')
  }

  async getPicostrapCompileSource(importPath) {
    return this.request('GET', 'picostrap/compile-source', {
      query: { import_path: importPath }
    })
  }

  async storePicostrapBundle(css, metadata = {}) {
    return this.request('POST', 'picostrap/bundle', {
      body: {
        css,
        write_context: metadata.write_context,
        acknowledge_shared: metadata.acknowledge_shared,
        source_fingerprint: metadata.sourceFingerprint || metadata.source_fingerprint || ''
      }
    })
  }

  async previewPicostrapCompile(payload = {}) {
    const manifest = await this.getPicostrapCompileManifest()
    if (!payload.import_path && !payload.source_path) {
      return manifest
    }

    const source = await this.getPicostrapCompileSource(payload.import_path || payload.source_path)
    return {
      manifest,
      source
    }
  }

  async applyPicostrapCompile(payload = {}) {
    return this.request('POST', 'picostrap/bundle', {
      body: {
        css: payload.compiled_css || payload.css || '',
        write_context: payload.write_context,
        acknowledge_shared: payload.acknowledge_shared,
        source_fingerprint: payload.source_fingerprint || ''
      }
    })
  }

  async getMcpStatus() {
    return this.request('GET', 'mcp/status')
  }

  async getTransportIdentity() {
    // A revoked local listener must stop, not start a new pairing implicitly.
    return this.request('GET', 'mcp/transport-identity', { authRefreshAttempted: true, requireExistingSession: true, signal: AbortSignal.timeout(10000) })
  }

  async getMcpBootstrap() {
    return this.request('GET', 'mcp/bootstrap')
  }

  async syncWorkspaceRoot(payload = {}) {
    return this.request('POST', 'mcp/workspace-root', { body: payload })
  }

  async getWindPressStatus() {
    return this.request('GET', 'windpress/status')
  }

  async getWindPressVolume(params = {}) {
    return this.request('GET', 'windpress/volume', { query: params })
  }

  async getWindPressHandlers() {
    return this.request('GET', 'windpress/volume/handlers')
  }

  async getWindPressProviders() {
    return this.request('GET', 'windpress/providers')
  }

  async scanWindPressProvider(providerId, metadata = {}, decodeContents = true) {
    return this.request('POST', 'windpress/providers/scan', {
      body: {
        provider_id: providerId,
        metadata,
        decode_contents: decodeContents
      }
    })
  }

  async scanWindPressProviderFull(providerId, options = {}) {
    const decodeContents = options.decode_contents !== false
    const maxBatches = Number.isInteger(options.max_batches) && options.max_batches > 0 ? options.max_batches : 50
    let metadata = typeof options.metadata === 'object' && options.metadata ? { ...options.metadata } : {}
    const aggregated = []
    let firstResult = null
    let batchCount = 0

    while (batchCount < maxBatches) {
      const result = await this.scanWindPressProvider(providerId, metadata, decodeContents)
      const payload = result.result || result

      if (!payload || payload.ok === false) {
        return result
      }

      if (!firstResult) {
        firstResult = payload
      }

      aggregated.push(...(payload.contents || []))
      batchCount += 1

      const nextBatch = payload.metadata && payload.metadata.next_batch ? payload.metadata.next_batch : false

      if (!nextBatch) {
        return {
          result: {
            ...payload,
            contents: aggregated,
            metadata: {
              ...(payload.metadata || {}),
              scanned_batches: batchCount
            }
          }
        }
      }

      metadata = {
        ...metadata,
        next_batch: nextBatch
      }
    }

    return {
      result: {
        ...(firstResult || { ok: true, provider: { id: providerId } }),
        contents: aggregated,
        metadata: {
          ...((firstResult && firstResult.metadata) || {}),
          scanned_batches: batchCount,
          truncated: true
        }
      }
    }
  }

  async saveWindPressVolumeEntries(entries = [], context = {}) {
    return this.request('POST', 'windpress/volume', {
      body: { ...context, entries }
    })
  }

  async saveWindPressThemeJson(themeJson, context = {}) {
    return this.request('POST', 'windpress/theme-json', {
      body: { ...context, theme_json: themeJson }
    })
  }

  async saveWindPressCache(css, sourcemap = '', fullBuild = null, context = {}) {
    return this.request('POST', 'windpress/cache', {
      body: { ...context, css, sourcemap, full_build: fullBuild }
    })
  }

  async flushWindPressCache() {
    return this.request('POST', 'windpress/cache/flush', {
      body: {}
    })
  }

  async resetWindPressVolumeEntry(relativePath, context = {}) {
    return this.request('POST', 'windpress/volume/reset', {
      body: {
        ...context,
        relative_path: relativePath
      }
    })
  }

  async getPendingThemeLibraryBuild(themeSlug) {
    return this.request('GET', 'theme-library/build/pending', {
      query: { theme_slug: themeSlug }
    })
  }

  async completeThemeLibraryBuild(payload = {}) {
    return this.request('POST', 'theme-library/build/complete', {
      body: payload
    })
  }

  async request(method, path, options = {}) {
    if ((options.requireExistingSession || this.config.transportBound) && (!this.config.sessionToken || this.config.token)) throw new Error('owned_session_required: Connect an identified Bridge session before starting the local listener.')
    const auth = await this.sessionAuth.resolve()
    if (!auth.ok) {
      return auth
    }

    const url = new URL(path.replace(/^\//, ''), this.restBase)
    const headers = applyHttpBasicAuth({
      Accept: 'application/json',
      'X-LCFA-MCP-Package-Version': MCP_PACKAGE_VERSION
    }, this.config)

    if (auth.type === 'legacy_mcp_token') {
      headers['X-LCFA-MCP-Token'] = auth.token
    } else {
      headers['X-LCFA-MCP-Session'] = auth.token
    }

    if (this.config.siteFingerprint) {
      headers['X-LCFA-Site-Fingerprint'] = String(this.config.siteFingerprint)
    }
    if (this.config.connectionAttempt) {
      headers['X-LCFA-Connection-Attempt'] = String(this.config.connectionAttempt)
    }

    if (!path.startsWith('agent/request')) {
      for (const [id, lease] of this.agentLeases) {
        if (lease.error) throw lease.error
        headers['X-LCFA-Request-ID'] = id
        headers['X-LCFA-Worker-Lease'] = lease.token
      }
    }

    if (options.query && typeof options.query === 'object') {
      Object.entries(options.query).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          return
        }

        url.searchParams.set(key, String(value))
      })
    }

    const requestOptions = {
      method,
      headers,
      // Custom session headers must never be forwarded to a redirect destination.
      redirect: 'error'
    }
    if (options.signal) requestOptions.signal = options.signal

    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json'
      requestOptions.body = JSON.stringify(options.body)
    }

    const response = await fetch(url, requestOptions)
    const text = await response.text()
    const data = text ? safeJsonParse(text) : {}

    if (!response.ok) {
      if (
        (response.status === 401 || response.status === 403) &&
        auth.type === 'ai_bridge_session' &&
        !this.config.transportBound &&
        options.authRefreshAttempted !== true
      ) {
        this.sessionAuth.invalidateSession()
        return this.request(method, path, {
          ...options,
          authRefreshAttempted: true
        })
      }

      const message = getHttpErrorMessage(response.status, data)
      const error = new Error(message)
      error.status = response.status
      error.payload = data
      throw error
    }

    return data
  }
}

function safeJsonParse(value) {
  try {
    return JSON.parse(value)
  } catch (error) {
    return { raw: value }
  }
}

function extractErrorMessage(payload) {
  if (!payload || typeof payload !== 'object') {
    return ''
  }

  if (typeof payload.error === 'string') {
    return payload.error
  }

  if (typeof payload.message === 'string') {
    return payload.message
  }

  if (payload.result && typeof payload.result.message === 'string') {
    return payload.result.message
  }

  return ''
}

function getHttpErrorMessage(status, payload) {
  const message = extractErrorMessage(payload)
  const code = payload && typeof payload === 'object' ? String(payload.code || '') : ''

  if ((status === 401 || status === 403) && (code === 'rest_forbidden' || /not allowed|forbidden|unauthorized/i.test(message))) {
    return 'WordPress rejected the LiveCanvas AI Bridge credential. Regenerate this coding agent project config or rotate access and pair again.'
  }

  return message || `WordPress request failed (${status})`
}

module.exports = {
  WPClient
}
