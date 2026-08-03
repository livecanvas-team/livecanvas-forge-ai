const fs = require('node:fs')
const path = require('node:path')
const crypto = require('node:crypto')
const { fileURLToPath, pathToFileURL } = require('node:url')

class WindPressCompiler {
  constructor({ client, config }) {
    this.client = client
    this.config = config
    this.compilerCache = new Map()
    this.pluginModuleCache = new Map()
  }

  async buildCache(options = {}) {
    const statusResponse = await this.client.getWindPressStatus()
    const status = statusResponse.windpress || statusResponse.result || statusResponse

    if (!status || status.available !== true) {
      throw new Error('WindPress is not available on the current WordPress site.')
    }

    const volumeResponse = await this.client.getWindPressVolume({
      include_content: true,
      limit: 2000
    })
    const volumePayload = volumeResponse.volume || volumeResponse.result || volumeResponse
    const volume = buildVolumeMap(volumePayload.entries || [])

    if (!volume['/main.css']) {
      throw new Error('WindPress volume does not expose main.css. Cache build cannot continue.')
    }

    const providerIds = normalizeProviderIds(options.provider_ids, status.providers || [])
    const providerResults = []

    for (const providerId of providerIds) {
      const scanResponse = await this.client.scanWindPressProviderFull(providerId, {
        metadata: options.metadata || {},
        decode_contents: false,
        max_batches: options.max_batches
      })
      const scanResult = scanResponse.result || scanResponse

      if (!scanResult || scanResult.ok === false) {
        throw new Error(scanResult && scanResult.message ? scanResult.message : `Failed to scan WindPress provider "${providerId}".`)
      }

      providerResults.push(scanResult)
    }

    const candidateSources = providerResults.flatMap((providerResult) => normalizeProviderContents(providerResult.contents || []))
    const tailwindVersion = Number(status.tailwind_version || 4) === 3 ? 3 : 4
    const sourceMapEnabled = options.source_map !== undefined ? Boolean(options.source_map) : Boolean(status.source_map)
    const compiler = await this.loadCompiler(tailwindVersion)

    let normalCss = ''
    let minifiedCss = ''
    let sourceMap = null
    let candidates = []

    if (tailwindVersion === 4) {
      const compileVolume = await this.prepareExternalPlugins(volume)
      const compiled = await compiler.compile({
        entrypoint: '/main.css',
        volume: compileVolume
      })

      const sourceContents = await compiler.loadSource(compiled.sources || [])
      candidates = await compiler.getCandidates([
        ...candidateSources,
        ...sourceContents
      ])

      const builtCss = compiled.build(candidates)
      const rawSourceMap = sourceMapEnabled && typeof compiled.buildSourceMap === 'function'
        ? compiled.buildSourceMap()
        : undefined

      const optimized = await compiler.optimize(builtCss, {
        file: 'main.css',
        map: rawSourceMap
      })
      const optimizedMinified = await compiler.optimize(builtCss, {
        file: 'main.css',
        map: rawSourceMap,
        minify: true
      })

      normalCss = optimized.code || ''
      sourceMap = optimized.map || null
      minifiedCss = optimizedMinified.code || ''
    } else {
      const built = await compiler.build({
        entrypoint: {
          css: '/main.css',
          config: '/tailwind.config.js'
        },
        contents: candidateSources,
        volume
      })

      const optimized = await compiler.optimize(built)
      const optimizedMinified = await compiler.optimize(built, true)

      normalCss = optimized.css || ''
      minifiedCss = optimizedMinified.css || ''
      sourceMap = null
    }

    const store = options.store !== false
    let stored = null

    if (store) {
      const fullBuildStamp = options.kind === 'full' || !options.kind ? Date.now() : null
      const cssToStore = sourceMap ? normalCss : minifiedCss
      stored = await this.client.saveWindPressCache(cssToStore, sourceMap || '', fullBuildStamp)
    }

    return {
      ok: true,
      tailwind_version: tailwindVersion,
      provider_ids: providerIds,
      provider_count: providerIds.length,
      candidate_count: Array.isArray(candidates) ? candidates.length : 0,
      candidates: Array.isArray(candidates) ? candidates.sort() : [],
      provider_scans: providerResults.map((providerResult) => ({
        provider: providerResult.provider || {},
        metadata: providerResult.metadata || {},
        content_count: Array.isArray(providerResult.contents) ? providerResult.contents.length : 0
      })),
      css: {
        normal: normalCss,
        minified: minifiedCss,
        sourcemap: sourceMap
      },
      stored: stored ? (stored.result || stored) : null
    }
  }

  async prepareExternalPlugins(volume) {
    const prepared = { ...volume }
    const pluginPattern = /(@plugin\s+)(["'])([^"']+)\2/g
    const replacements = new Map()

    for (const content of Object.values(prepared)) {
      if (typeof content !== 'string') {
        continue
      }

      for (const match of content.matchAll(pluginPattern)) {
        const specifier = String(match[3] || '').trim()
        if (!isExternalPluginSpecifier(specifier) || replacements.has(specifier)) {
          continue
        }

        const source = await this.fetchBundledPlugin(specifier)
        const hash = crypto.createHash('sha256').update(specifier).digest('hex').slice(0, 12)
        const modulePath = `/__lcfa_plugins/plugin-${hash}.js`
        prepared[modulePath] = source
        replacements.set(specifier, modulePath)
      }
    }

    if (replacements.size === 0) {
      return prepared
    }

    for (const [filePath, content] of Object.entries(prepared)) {
      if (typeof content !== 'string' || filePath.startsWith('/__lcfa_plugins/')) {
        continue
      }

      prepared[filePath] = content.replace(pluginPattern, (full, prefix, quote, specifier) => {
        const replacement = replacements.get(String(specifier || '').trim())
        return replacement ? `${prefix}${quote}${replacement}${quote}` : full
      })
    }

    return prepared
  }

  async fetchBundledPlugin(specifier) {
    if (this.pluginModuleCache.has(specifier)) {
      return this.pluginModuleCache.get(specifier)
    }

    const promise = this.downloadBundledPlugin(specifier)
    this.pluginModuleCache.set(specifier, promise)

    try {
      return await promise
    } catch (error) {
      this.pluginModuleCache.delete(specifier)
      throw error
    }
  }

  async downloadBundledPlugin(specifier) {
    const entryUrl = resolveEsmPluginUrl(specifier)
    const entryResponse = await fetch(entryUrl)
    if (!entryResponse.ok) {
      throw new Error(`Unable to download Tailwind plugin ${entryUrl}: HTTP ${entryResponse.status}`)
    }

    const entrySource = await entryResponse.text()
    const bundleMatch = entrySource.match(/(?:export\s+\*\s+from|export\s+\{[^}]*\}\s+from)\s+["']([^"']+\.bundle\.mjs)["']/)
    let bundledSource = entrySource

    if (bundleMatch) {
      const bundleUrl = new URL(bundleMatch[1], entryUrl)
      const bundleResponse = await fetch(bundleUrl)
      if (!bundleResponse.ok) {
        throw new Error(`Unable to download bundled Tailwind plugin ${bundleUrl.href}: HTTP ${bundleResponse.status}`)
      }
      bundledSource = await bundleResponse.text()
    }

    if (Buffer.byteLength(bundledSource, 'utf8') > 2 * 1024 * 1024) {
      throw new Error(`Bundled Tailwind plugin ${entryUrl} exceeds the 2 MB safety limit.`)
    }
    if (/\bimport\s*(?:\(|[^;]*\bfrom\s*)["']https?:/m.test(bundledSource)) {
      throw new Error(`Bundled Tailwind plugin ${entryUrl} still contains network imports.`)
    }

    return bundledSource
  }

  async loadCompiler(tailwindVersion) {
    this.ensureFileFetchShim()
    this.ensureWindPressRuntimeShim()

    if (this.compilerCache.has(tailwindVersion)) {
      return this.compilerCache.get(tailwindVersion)
    }

    const compilerPromise = this.importCompiler(tailwindVersion)
    this.compilerCache.set(tailwindVersion, compilerPromise)

    try {
      return await compilerPromise
    } catch (error) {
      this.compilerCache.delete(tailwindVersion)
      throw error
    }
  }

  ensureWindPressRuntimeShim() {
    if (typeof globalThis.window === 'undefined') {
      globalThis.window = globalThis
    }

    const runtimeWindow = globalThis.window
    if (!runtimeWindow.window) {
      runtimeWindow.window = runtimeWindow
    }
    if (!runtimeWindow.__lcfaEventTarget && typeof EventTarget === 'function') {
      runtimeWindow.__lcfaEventTarget = new EventTarget()
    }
    if (runtimeWindow.__lcfaEventTarget) {
      if (typeof runtimeWindow.addEventListener !== 'function') {
        runtimeWindow.addEventListener = runtimeWindow.__lcfaEventTarget.addEventListener.bind(runtimeWindow.__lcfaEventTarget)
      }
      if (typeof runtimeWindow.removeEventListener !== 'function') {
        runtimeWindow.removeEventListener = runtimeWindow.__lcfaEventTarget.removeEventListener.bind(runtimeWindow.__lcfaEventTarget)
      }
      if (typeof runtimeWindow.dispatchEvent !== 'function') {
        runtimeWindow.dispatchEvent = runtimeWindow.__lcfaEventTarget.dispatchEvent.bind(runtimeWindow.__lcfaEventTarget)
      }
    }
    if (!runtimeWindow.windpress || typeof runtimeWindow.windpress !== 'object') {
      runtimeWindow.windpress = {}
    }
    if (!runtimeWindow.windpress.user_data || typeof runtimeWindow.windpress.user_data !== 'object') {
      runtimeWindow.windpress.user_data = {}
    }
    if (!runtimeWindow.windpress.user_data.data_dir || typeof runtimeWindow.windpress.user_data.data_dir !== 'object') {
      runtimeWindow.windpress.user_data.data_dir = {}
    }
    if (!runtimeWindow.windpress.user_data.data_dir.url) {
      const siteUrl = String(this.config.siteUrl || '').replace(/\/$/, '')
      runtimeWindow.windpress.user_data.data_dir.url = siteUrl
        ? `${siteUrl}/wp-content/uploads/windpress/`
        : 'https://localhost/wp-content/uploads/windpress/'
    }
  }

  async importCompiler(tailwindVersion) {
    const modulePath = this.resolveCompilerAssetPath(tailwindVersion)

    if (tailwindVersion === 3) {
      const moduleUrl = pathToFileURL(modulePath).href
      const mod = await import(moduleUrl)

      return {
        build: mod.build,
        optimize: mod.optimize
      }
    }

    const moduleUrl = pathToFileURL(modulePath).href
    const mod = await import(moduleUrl)

    if (mod.__tla) {
      await mod.__tla
    }

    return {
      compile: mod.compile,
      getCandidates: mod.getCandidates,
      loadSource: mod.loadSource,
      optimize: mod.optimize
    }
  }

  resolveCompilerAssetPath(tailwindVersion) {
    const buildRoot = this.resolveWindPressBuildRoot()
    const manifestPath = path.join(buildRoot, 'manifest.json')
    const sourceKey = tailwindVersion === 3
      ? 'assets/packages/core/tailwindcss-v3/index.ts'
      : 'assets/packages/core/tailwindcss/index.ts'

    const manifestAsset = this.resolveCompilerAssetFromManifest(manifestPath, sourceKey)

    if (manifestAsset) {
      return manifestAsset
    }

    return this.resolveCompilerAssetFromDirectory(path.join(buildRoot, 'assets'), tailwindVersion)
  }

  resolveCompilerAssetFromManifest(manifestPath, sourceKey) {
    if (!fs.existsSync(manifestPath)) {
      return ''
    }

    try {
      const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
      const entry = manifest && typeof manifest === 'object' ? manifest[sourceKey] : null
      const file = entry && typeof entry.file === 'string' ? entry.file : ''

      if (file) {
        const assetPath = path.join(path.dirname(manifestPath), file)

        if (fs.existsSync(assetPath)) {
          return assetPath
        }
      }
    } catch (_error) {
    }

    return ''
  }

  resolveCompilerAssetFromDirectory(assetsRoot, tailwindVersion) {
    if (!fs.existsSync(assetsRoot)) {
      throw new Error(`WindPress build assets not found: ${assetsRoot}`)
    }

    const expectedExports = tailwindVersion === 3
      ? ['build', 'optimize']
      : ['compile', 'getCandidates', 'loadSource', 'optimize']
    const prefixPattern = tailwindVersion === 3
      ? /^tailwindcss-v3-[A-Za-z0-9_-]+\.js$/
      : /^tailwindcss-(?!v3-)[A-Za-z0-9_-]+\.js$/
    const candidates = fs.readdirSync(assetsRoot)
      .filter((file) => prefixPattern.test(file))
      .map((file) => {
        const filePath = path.join(assetsRoot, file)
        const stats = fs.statSync(filePath)

        return {
          file,
          filePath,
          bytes: stats.size
        }
      })
      .sort((left, right) => left.bytes - right.bytes || left.file.localeCompare(right.file))

    for (const candidate of candidates) {
      const content = fs.readFileSync(candidate.filePath, 'utf8')

      if (expectedExports.every((name) => hasNamedExport(content, name))) {
        return candidate.filePath
      }
    }

    throw new Error(`Unable to locate a WindPress Tailwind v${tailwindVersion} compiler asset in ${assetsRoot}. Found: ${candidates.map((candidate) => candidate.file).join(', ') || 'none'}`)
  }

  ensureFileFetchShim() {
    if (typeof globalThis.fetch === 'function' && globalThis.fetch.__lcfaFileShimInstalled === true) {
      return
    }

    const originalFetch = typeof globalThis.fetch === 'function'
      ? globalThis.fetch.bind(globalThis)
      : null

    const shimmedFetch = async (input, init) => {
      const fileUrl = resolveFileUrlFromFetchInput(input)

      if (fileUrl) {
        return createFileFetchResponse(fileUrl)
      }

      if (!originalFetch) {
        throw new Error('Global fetch is not available for non-file requests in the local WindPress compiler.')
      }

      return originalFetch(input, init)
    }

    Object.defineProperties(shimmedFetch, {
      __lcfaFileShimInstalled: {
        value: true
      },
      __lcfaOriginalFetch: {
        value: originalFetch
      }
    })

    globalThis.fetch = shimmedFetch
  }

  resolveWindPressAssetsRoot() {
    const assetsRoot = path.join(this.resolveWindPressBuildRoot(), 'assets')

    if (!fs.existsSync(assetsRoot)) {
      throw new Error(`WindPress build assets not found: ${assetsRoot}`)
    }

    return assetsRoot
  }

  resolveWindPressBuildRoot() {
    const wpRoot = this.resolveWordPressRoot()
    const buildRoot = path.join(wpRoot, 'wp-content', 'plugins', 'windpress', 'build')

    if (!fs.existsSync(buildRoot)) {
      throw new Error(`WindPress build directory not found: ${buildRoot}`)
    }

    return buildRoot
  }

  resolveWordPressRoot() {
    if (this.config.wpRoot) {
      return path.resolve(this.config.wpRoot)
    }

    const candidates = [
      process.cwd(),
      path.resolve(__dirname, '../../../../../')
    ]

    for (const candidate of candidates) {
      const current = path.resolve(candidate)

      if (fs.existsSync(path.join(current, 'wp-content', 'plugins', 'windpress'))) {
        return current
      }
    }

    throw new Error('Unable to resolve the local WordPress root for WindPress compilation. Set LCFA_WP_ROOT or pass --wp-root.')
  }
}

function isExternalPluginSpecifier(specifier) {
  return specifier !== '' && !specifier.startsWith('.') && !specifier.startsWith('/')
}

function resolveEsmPluginUrl(specifier) {
  if (/^https?:\/\//i.test(specifier)) {
    const url = new URL(specifier)
    if (url.protocol !== 'https:' || url.hostname !== 'esm.sh') {
      throw new Error(`External Tailwind plugins must use https://esm.sh. Received: ${specifier}`)
    }
    url.searchParams.set('bundle', '')
    return url
  }

  if (!/^(?:@?[a-z0-9][a-z0-9._-]*)(?:\/[a-z0-9][a-z0-9._-]*)?(?:@[a-z0-9][a-z0-9._-]*)?$/i.test(specifier)) {
    throw new Error(`Unsupported Tailwind plugin specifier: ${specifier}`)
  }

  return new URL(`https://esm.sh/${specifier}?bundle`)
}

function hasNamedExport(content, name) {
  const escapedName = escapeRegExp(name)

  return new RegExp(`export\\s*\\{[\\s\\S]*(?:\\bas\\s+${escapedName}\\b|\\b${escapedName}\\b)[\\s\\S]*\\}`, 'm').test(content)
    || new RegExp(`export\\s+(?:async\\s+)?function\\s+${escapedName}\\b`, 'm').test(content)
    || new RegExp(`export\\s+(?:const|let|var)\\s+${escapedName}\\b`, 'm').test(content)
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function normalizeProviderIds(input, providers) {
  const requested = Array.isArray(input)
    ? input.map((item) => String(item || '').trim()).filter(Boolean)
    : typeof input === 'string' && input.trim() !== ''
      ? input.split(',').map((item) => item.trim()).filter(Boolean)
      : []

  if (requested.length > 0) {
    return requested
  }

  return providers
    .filter((provider) => provider && provider.enabled !== false)
    .map((provider) => String(provider.id || '').trim())
    .filter(Boolean)
}

function buildVolumeMap(entries) {
  return entries.reduce((carry, entry) => {
    if (!entry || typeof entry.relative_path !== 'string') {
      return carry
    }

    carry[`/${entry.relative_path.replace(/^\/+/, '')}`] = String(entry.content || '')
    return carry
  }, {})
}

function normalizeProviderContents(contents) {
  return contents.map((entry) => {
    let decoded = ''

    if (typeof entry.decoded_content === 'string') {
      decoded = entry.decoded_content
    } else if (typeof entry.content === 'string' && entry.content !== '') {
      decoded = Buffer.from(entry.content, 'base64').toString('utf8')
    }

    if (entry.type === 'json') {
      try {
        decoded = JSON.stringify(JSON.parse(decoded), null, 2)
      } catch (_error) {
      }
    }

    return decodeHtmlEntities(decoded)
  })
}

function decodeHtmlEntities(value) {
  const named = {
    '&amp;': '&',
    '&apos;': '\'',
    '&gt;': '>',
    '&lt;': '<',
    '&nbsp;': '\u00A0',
    '&quot;': '"'
  }

  return String(value || '')
    .replace(/&(amp|apos|gt|lt|nbsp|quot);/g, (match) => named[match] || match)
    .replace(/&#([0-9]+);/g, (_match, code) => decodeCodePoint(code, 10))
    .replace(/&#x([a-fA-F0-9]+);/g, (_match, code) => decodeCodePoint(code, 16))
}

function decodeCodePoint(value, radix) {
  const parsed = Number.parseInt(value, radix)

  if (!Number.isFinite(parsed) || parsed < 0 || parsed > 0x10ffff) {
    return '\uFFFD'
  }

  return String.fromCodePoint(parsed)
}

function resolveFileUrlFromFetchInput(input) {
  try {
    if (input instanceof URL) {
      return input.protocol === 'file:' ? input : null
    }

    if (typeof Request === 'function' && input instanceof Request) {
      const requestUrl = new URL(input.url)
      return requestUrl.protocol === 'file:' ? requestUrl : null
    }

    if (typeof input === 'string') {
      const requestUrl = new URL(input)
      return requestUrl.protocol === 'file:' ? requestUrl : null
    }
  } catch (_error) {
    return null
  }

  return null
}

async function createFileFetchResponse(fileUrl) {
  const filePath = fileURLToPath(fileUrl)
  const buffer = await fs.promises.readFile(filePath)
  const contentType = getContentType(filePath)

  if (typeof Response === 'function') {
    return new Response(buffer, {
      status: 200,
      headers: {
        'content-length': String(buffer.byteLength),
        'content-type': contentType
      }
    })
  }

  const headers = typeof Headers === 'function'
    ? new Headers({
      'content-length': String(buffer.byteLength),
      'content-type': contentType
    })
    : {
      get(name) {
        const normalized = String(name || '').toLowerCase()

        if (normalized === 'content-length') {
          return String(buffer.byteLength)
        }

        if (normalized === 'content-type') {
          return contentType
        }

        return null
      }
    }

  return {
    ok: true,
    status: 200,
    url: fileUrl.href,
    headers,
    async arrayBuffer() {
      return buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength)
    },
    async text() {
      return buffer.toString('utf8')
    },
    async json() {
      return JSON.parse(buffer.toString('utf8'))
    }
  }
}

function getContentType(filePath) {
  switch (path.extname(filePath).toLowerCase()) {
    case '.css':
      return 'text/css; charset=utf-8'
    case '.js':
    case '.mjs':
      return 'text/javascript; charset=utf-8'
    case '.json':
      return 'application/json; charset=utf-8'
    case '.wasm':
      return 'application/wasm'
    default:
      return 'application/octet-stream'
  }
}

module.exports = {
  WindPressCompiler
}
