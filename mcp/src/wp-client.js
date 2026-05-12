class WPClient {
  constructor(config) {
    this.config = config
    this.restBase = config.restBase
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
    const agent = ['codex', 'opencode', 'claude', 'cursor', 'generic'].includes(configuredAgent)
      ? configuredAgent
      : 'generic'
    const configuredTransport = this.config && this.config.transport ? String(this.config.transport) : 'stdio'
    const transport = configuredTransport === 'bridge' ? 'mcp_bridge' : 'mcp_stdio'

    return {
      ...payload,
      _lcfa_origin: 'mcp_agent',
      _lcfa_transport: transport,
      _lcfa_agent: agent,
      _lcfa_processed_by: processedBy
    }
  }

  async suggestCommand(payload = {}) {
    return this.request('POST', 'command/suggest', { body: this.withProvenance(payload, 'forge_local_rules') })
  }

  async runCommand(payload) {
    const configuredAgent = this.config && this.config.agent ? String(this.config.agent) : 'codex'
    const agent = ['codex', 'opencode', 'claude', 'cursor', 'generic'].includes(configuredAgent)
      ? configuredAgent
      : 'generic'
    const processedBy = agent === 'generic' ? 'generic_mcp' : `${agent}_mcp`

    return this.request('POST', 'command', { body: this.withProvenance(payload, processedBy) })
  }

  async getNextAgentRequest(agent = null, requestId = '') {
    const configuredAgent = this.config && this.config.agent ? String(this.config.agent) : 'codex'
    const targetAgent = agent || configuredAgent
    const query = {
      agent: targetAgent
    }

    if (requestId) {
      query.request_id = requestId
      query.claim = '1'
    }

    return this.request('GET', 'agent/request', {
      query
    })
  }

  async getAgentRequest(requestId) {
    return this.request('GET', 'agent/request', {
      query: {
        request_id: requestId
      }
    })
  }

  async completeAgentRequest(requestId, result = {}, thread = null) {
    const body = {
      request_id: requestId,
      result
    }

    if (thread && typeof thread === 'object') {
      body.thread = thread
    }

    return this.request('POST', 'agent/request/complete', { body })
  }

  async failAgentRequest(requestId, message, thread = null) {
    const body = {
      request_id: requestId,
      message: String(message || 'Agent request failed.')
    }

    if (thread && typeof thread === 'object') {
      body.thread = thread
    }

    return this.request('POST', 'agent/request/fail', { body })
  }

  async getPicostrapCompileManifest() {
    return this.request('GET', 'picostrap/compile-manifest')
  }

  async getPicostrapCompileSource(importPath) {
    return this.request('GET', 'picostrap/compile-source', {
      query: { import_path: importPath }
    })
  }

  async storePicostrapBundle(css) {
    return this.request('POST', 'picostrap/bundle', {
      body: { css }
    })
  }

  async getMcpStatus() {
    return this.request('GET', 'mcp/status')
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

  async saveWindPressVolumeEntries(entries = []) {
    return this.request('POST', 'windpress/volume', {
      body: { entries }
    })
  }

  async saveWindPressThemeJson(themeJson) {
    return this.request('POST', 'windpress/theme-json', {
      body: { theme_json: themeJson }
    })
  }

  async saveWindPressCache(css, sourcemap = '', fullBuild = null) {
    return this.request('POST', 'windpress/cache', {
      body: { css, sourcemap, full_build: fullBuild }
    })
  }

  async flushWindPressCache() {
    return this.request('POST', 'windpress/cache/flush', {
      body: {}
    })
  }

  async resetWindPressVolumeEntry(relativePath) {
    return this.request('POST', 'windpress/volume/reset', {
      body: {
        relative_path: relativePath
      }
    })
  }

  async request(method, path, options = {}) {
    const url = new URL(path.replace(/^\//, ''), this.restBase)
    const headers = {
      Accept: 'application/json',
      'X-LCFA-MCP-Token': this.config.token
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
      headers
    }

    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json'
      requestOptions.body = JSON.stringify(options.body)
    }

    const response = await fetch(url, requestOptions)
    const text = await response.text()
    const data = text ? safeJsonParse(text) : {}

    if (!response.ok) {
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
    return 'WordPress rejected the LiveCanvas Forge MCP token. Sync Codex config or rotate the token and regenerate.'
  }

  return message || `WordPress request failed (${status})`
}

module.exports = {
  WPClient
}
