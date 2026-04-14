const fs = require('node:fs')
const fsp = require('node:fs/promises')
const path = require('node:path')
const sass = require('sass')

class PicostrapCompiler {
  constructor({ client, config, themeFiles }) {
    this.client = client
    this.config = config
    this.themeFiles = themeFiles
    this.localRootsPromise = null
  }

  async buildBundle(options = {}) {
    const manifestResponse = await this.client.getPicostrapCompileManifest()
    const manifest = unwrapResultEnvelope(manifestResponse)

    if (!manifest || manifest.framework !== 'picostrap') {
      throw new Error('Picostrap compile manifest is not available for the current site.')
    }

    const sourceCache = new Map()
    const missingCache = new Set()
    const importer = this.createImporter({ manifest, sourceCache, missingCache })
    const result = await sass.compileStringAsync(String(manifest.main_sass || ''), {
      style: manifest.compile_mode === 'compressed' ? 'compressed' : 'expanded',
      syntax: 'scss',
      importers: [importer],
      url: new URL('https://lcfa.invalid/__lcfa_entry__.scss'),
      quietDeps: true,
      logger: {
        warn() {},
        debug() {}
      }
    })

    const storedResponse = await this.client.storePicostrapBundle(result.css)
    const stored = unwrapResultEnvelope(storedResponse)

    if (!stored || stored.ok === false) {
      throw new Error(stored && stored.message ? stored.message : 'Failed to store the Picostrap bundle.')
    }

    return {
      ok: true,
      action: 'picostrap_compile_bundle',
      mode: 'apply',
      target_stack: 'picostrap',
      source_of_truth: 'theme_mods',
      build_strategy: 'bridge_dart_sass',
      build_required: true,
      build_executed: true,
      bundle_path: stored.bundle_path || '',
      bundle_url: stored.bundle_url || '',
      bundle_version: stored.bundle_version || 0,
      compiled_at: stored.compiled_at || '',
      warnings: []
    }
  }

  createImporter(context) {
    return {
      canonicalize: async (url, options) => {
        const request = String(url || '').trim()

        if (!request || request.startsWith('http://') || request.startsWith('https://') || request.endsWith('.css')) {
          return null
        }

        const resolved = await this.resolveImportPath(request, options && options.containingUrl ? options.containingUrl : null, context)

        if (!resolved) {
          return null
        }

        const encoded = resolved.split('/').map((segment) => encodeURIComponent(segment)).join('/')
        return new URL(`https://lcfa.invalid/${encoded}`)
      },
      load: async (canonicalUrl) => {
        const relativePath = decodeURIComponent(String(canonicalUrl.pathname || '').replace(/^\/+/, ''))
        const contents = await this.readSource(relativePath, context)

        return {
          contents,
          syntax: 'scss'
        }
      }
    }
  }

  async resolveImportPath(request, containingUrl, context) {
    const baseDir = this.resolveBaseDirectory(containingUrl)
    const searchBases = []
    const preferLocal = await this.canUseLocalFilesystem(context.manifest)

    if (baseDir !== '') {
      searchBases.push(baseDir)
    }

    searchBases.push('')

    for (const base of searchBases) {
      const normalized = normalizeImportPath(base ? path.posix.join(base, request) : request)
      const candidates = buildImportCandidates(normalized)

      if (preferLocal) {
        for (const candidate of candidates) {
          if (await this.localSourceExists(candidate, context.manifest)) {
            return candidate
          }
        }
      }

      for (const candidate of candidates) {
        if (!preferLocal && await this.sourceExists(candidate, context)) {
          return candidate
        }

        if (preferLocal && await this.remoteSourceExists(candidate, context)) {
          return candidate
        }
      }
    }

    return null
  }

  resolveBaseDirectory(containingUrl) {
    if (!containingUrl || !(containingUrl instanceof URL) || containingUrl.hostname !== 'lcfa.invalid') {
      return ''
    }

    const current = decodeURIComponent(String(containingUrl.pathname || '').replace(/^\/+/, ''))
    const directory = path.posix.dirname(current)

    return directory === '.' ? '' : directory
  }

  async sourceExists(relativePath, context) {
    try {
      await this.readSource(relativePath, context)
      return true
    } catch (_error) {
      return false
    }
  }

  async remoteSourceExists(relativePath, context) {
    try {
      const remoteResponse = await this.client.getPicostrapCompileSource(relativePath)
      const remote = unwrapResultEnvelope(remoteResponse)
      return Boolean(remote && remote.ok !== false && typeof remote.contents === 'string')
    } catch (_error) {
      return false
    }
  }

  async readSource(relativePath, context) {
    if (context.sourceCache.has(relativePath)) {
      return context.sourceCache.get(relativePath)
    }

    if (context.missingCache.has(relativePath)) {
      throw new Error(`SCSS import not found: ${relativePath}`)
    }

    try {
      const local = await this.readLocalSource(relativePath, context.manifest)
      if (local !== null) {
        context.sourceCache.set(relativePath, local)
        return local
      }

      const remoteResponse = await this.client.getPicostrapCompileSource(relativePath)
      const remote = unwrapResultEnvelope(remoteResponse)

      if (remote && remote.ok !== false && typeof remote.contents === 'string') {
        context.sourceCache.set(relativePath, remote.contents)
        return remote.contents
      }
    } catch (_error) {
    }

    context.missingCache.add(relativePath)
    throw new Error(`SCSS import not found: ${relativePath}`)
  }

  async readLocalSource(relativePath, manifest) {
    if (!await this.canUseLocalFilesystem(manifest)) {
      return null
    }

    const roots = await this.getLocalSassRoots(manifest)

    for (const root of roots) {
      const absolutePath = path.join(root, relativePath)

      if (fs.existsSync(absolutePath) && fs.statSync(absolutePath).isFile()) {
        return fsp.readFile(absolutePath, 'utf8')
      }
    }

    return null
  }

  async localSourceExists(relativePath, manifest = null) {
    const roots = await this.getLocalSassRoots(manifest)

    for (const root of roots) {
      const absolutePath = path.join(root, relativePath)

      if (fs.existsSync(absolutePath) && fs.statSync(absolutePath).isFile()) {
        return true
      }
    }

    return false
  }

  async canUseLocalFilesystem(manifest) {
    if (!manifest || manifest.site_mode !== 'local') {
      return false
    }

    const roots = await this.getLocalSassRoots(manifest)
    return roots.length > 0
  }

  async getLocalSassRoots(manifest = null) {
    const manifestRoots = this.buildManifestLocalRoots(manifest)

    if (manifestRoots.length > 0) {
      return manifestRoots
    }

    if (this.localRootsPromise) {
      return this.localRootsPromise
    }

    this.localRootsPromise = (async () => {
      try {
        const roots = await this.themeFiles.getThemeRoots()
        const entries = Array.isArray(roots.roots) ? roots.roots : []

        return entries
          .map((entry) => path.join(entry.path, 'sass'))
          .filter((entry, index, all) => all.indexOf(entry) === index)
      } catch (_error) {
        return []
      }
    })()

    return this.localRootsPromise
  }

  buildManifestLocalRoots(manifest) {
    const wpRoot = String(this.config && this.config.wpRoot ? this.config.wpRoot : '').trim()

    if (!manifest || !wpRoot || !manifest.stylesheet) {
      return []
    }

    const themesRoot = path.join(wpRoot, 'wp-content', 'themes')
    const roots = []
    const pushRoot = (themeSlug) => {
      const slug = String(themeSlug || '').trim()

      if (!slug) {
        return
      }

      const sassRoot = path.join(themesRoot, slug, 'sass')

      if (fs.existsSync(sassRoot) && fs.statSync(sassRoot).isDirectory() && !roots.includes(sassRoot)) {
        roots.push(sassRoot)
      }
    }

    pushRoot(manifest.stylesheet)

    if (manifest.template && manifest.template !== manifest.stylesheet) {
      pushRoot(manifest.template)
    }

    return roots
  }
}

function unwrapResultEnvelope(payload) {
  if (payload && typeof payload === 'object' && payload.result && typeof payload.result === 'object') {
    return payload.result
  }

  return payload
}

function normalizeImportPath(value) {
  return String(value || '')
    .replace(/\\/g, '/')
    .replace(/^\/+/, '')
    .replace(/\/+/g, '/')
}

function buildImportCandidates(request) {
  const normalized = normalizeImportPath(request)

  if (!normalized) {
    return []
  }

  const directory = path.posix.dirname(normalized)
  const basename = path.posix.basename(normalized)
  const extension = path.posix.extname(basename)
  const nameWithoutExtension = extension ? basename.slice(0, -extension.length) : basename
  const baseDirectory = directory === '.' ? '' : directory
  const candidates = []

  const pushCandidate = (candidate) => {
    const next = normalizeImportPath(candidate)

    if (next && !candidates.includes(next)) {
      candidates.push(next)
    }
  }

  if (extension) {
    pushCandidate(path.posix.join(baseDirectory, basename))

    if (!basename.startsWith('_')) {
      pushCandidate(path.posix.join(baseDirectory, `_${basename}`))
    }

    return candidates
  }

  pushCandidate(path.posix.join(baseDirectory, `${nameWithoutExtension}.scss`))

  if (!nameWithoutExtension.startsWith('_')) {
    pushCandidate(path.posix.join(baseDirectory, `_${nameWithoutExtension}.scss`))
  }

  return candidates
}

module.exports = {
  PicostrapCompiler
}
