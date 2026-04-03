const fs = require('node:fs')
const fsp = require('node:fs/promises')
const path = require('node:path')

const DEFAULT_LIST_LIMIT = 250
const READABLE_EXTENSIONS = new Set([
  '.css',
  '.html',
  '.js',
  '.json',
  '.latte',
  '.md',
  '.php',
  '.scss',
  '.svg',
  '.twig',
  '.txt',
  '.xml',
  '.yml',
  '.yaml'
])
const WRITABLE_EXTENSIONS = new Set([
  '.css',
  '.html',
  '.js',
  '.json',
  '.latte',
  '.md',
  '.php',
  '.scss',
  '.twig',
  '.txt',
  '.xml',
  '.yml',
  '.yaml'
])
const TEMPLATE_EXTENSIONS = new Set(['.html', '.latte', '.php', '.twig'])
const TEMPLATE_DIRECTORIES = ['views', 'templates', 'partials', 'page-templates', 'loops', 'livecanvas']
const BLOCKED_SEGMENTS = new Set(['.git', '.github', 'node_modules', 'vendor'])
const BLOCKED_PREFIXES = ['public/build/']

class ThemeFilesystem {
  constructor({ client, config }) {
    this.client = client
    this.config = config
    this.cachedRoots = null
    this.backupsDirectory = path.resolve(__dirname, '..', '.lcfa-backups')
  }

  async getThemeRoots() {
    if (this.cachedRoots) {
      return this.cachedRoots
    }

    const snapshotResponse = await this.client.getSnapshot()
    const mcpStatusResponse = await this.client.getMcpStatus()
    const snapshot = snapshotResponse.snapshot || {}
    const mcp = mcpStatusResponse.mcp || {}
    const wpRoot = this.resolveWordPressRoot(mcp.filesystem_mode)
    const themesRoot = path.join(wpRoot, 'wp-content', 'themes')
    const stylesheet = snapshot.current_theme_stylesheet || ''
    const template = snapshot.current_theme_template || stylesheet

    if (!stylesheet) {
      throw new Error('Unable to resolve the active stylesheet from the LiveCanvas Forge plugin.')
    }

    const stylesheetRoot = path.join(themesRoot, stylesheet)
    const templateRoot = path.join(themesRoot, template)

    if (!fs.existsSync(stylesheetRoot)) {
      throw new Error(`Stylesheet theme directory not found: ${stylesheetRoot}`)
    }

    if (!fs.existsSync(templateRoot)) {
      throw new Error(`Template theme directory not found: ${templateRoot}`)
    }

    const roots = [
      {
        key: 'stylesheet',
        label: stylesheet,
        path: stylesheetRoot
      }
    ]

    if (template !== stylesheet) {
      roots.push({
        key: 'template',
        label: template,
        path: templateRoot
      })
    }

    this.cachedRoots = {
      ok: true,
      wp_root: wpRoot,
      themes_root: themesRoot,
      backups_directory: this.backupsDirectory,
      stylesheet,
      template,
      stylesheet_root: stylesheetRoot,
      template_root: templateRoot,
      framework: snapshot.detected_framework || 'unknown',
      site_mode: snapshot.site_mode || 'remote',
      filesystem_mode: mcp.filesystem_mode || 'remote-rest-primary',
      is_child_theme: stylesheet !== template,
      roots
    }

    return this.cachedRoots
  }

  async listFiles(options = {}) {
    const roots = await this.getThemeRoots()
    const rootScope = options.root_scope || 'active'
    const directory = sanitizeRelativePath(options.directory || '', { allowEmpty: true })
    const extensions = normalizeExtensions(options.extensions, READABLE_EXTENSIONS)
    const limit = normalizeLimit(options.limit)
    const files = []

    for (const root of this.resolveTargets(rootScope, roots, { forWrite: false })) {
      const baseDirectory = directory ? this.resolveAbsolutePath(root.path, directory) : root.path

      if (!fs.existsSync(baseDirectory) || !fs.statSync(baseDirectory).isDirectory()) {
        continue
      }

      const shouldContinue = await walkDirectory(baseDirectory, async (absolutePath, relativePath, stats) => {
        if (files.length >= limit) {
          return false
        }

        if (!extensions.has(path.extname(relativePath).toLowerCase())) {
          return true
        }

        files.push(this.formatFileDescriptor(root, relativePath, absolutePath, stats))
        return true
      }, {
        rootPath: root.path,
        blockedSegments: BLOCKED_SEGMENTS
      })

      if (shouldContinue === false || files.length >= limit) {
        break
      }
    }

    return {
      ok: true,
      root_scope: rootScope,
      directory,
      limit,
      truncated: files.length >= limit,
      files
    }
  }

  async listTemplates(options = {}) {
    const roots = await this.getThemeRoots()
    const rootScope = options.root_scope || 'active'
    const limit = normalizeLimit(options.limit)
    const directories = this.getTemplateDirectories(roots.framework)
    const files = []

    for (const root of this.resolveTargets(rootScope, roots, { forWrite: false })) {
      for (const directory of directories) {
        const absoluteDirectory = this.resolveAbsolutePath(root.path, directory)

        if (!fs.existsSync(absoluteDirectory) || !fs.statSync(absoluteDirectory).isDirectory()) {
          continue
        }

        const shouldContinue = await walkDirectory(absoluteDirectory, async (absolutePath, relativePath, stats) => {
          if (files.length >= limit) {
            return false
          }

          if (!TEMPLATE_EXTENSIONS.has(path.extname(relativePath).toLowerCase())) {
            return true
          }

          files.push(this.formatFileDescriptor(root, relativePath, absolutePath, stats))
          return true
        }, {
          rootPath: root.path,
          blockedSegments: BLOCKED_SEGMENTS
        })

        if (shouldContinue === false || files.length >= limit) {
          break
        }
      }

      if (files.length >= limit) {
        break
      }
    }

    return {
      ok: true,
      root_scope: rootScope,
      directories,
      limit,
      truncated: files.length >= limit,
      files
    }
  }

  async listTemplatesByExtension(extension, options = {}) {
    const normalizedExtension = normalizeTemplateExtension(extension)
    const roots = await this.getThemeRoots()
    const rootScope = options.root_scope || 'active'
    const limit = normalizeLimit(options.limit)
    const directories = this.getTemplateDirectories(roots.framework)
    const files = []

    for (const root of this.resolveTargets(rootScope, roots, { forWrite: false })) {
      for (const directory of directories) {
        const absoluteDirectory = this.resolveAbsolutePath(root.path, directory)

        if (!fs.existsSync(absoluteDirectory) || !fs.statSync(absoluteDirectory).isDirectory()) {
          continue
        }

        const shouldContinue = await walkDirectory(absoluteDirectory, async (absolutePath, relativePath, stats) => {
          if (files.length >= limit) {
            return false
          }

          if (path.extname(relativePath).toLowerCase() !== normalizedExtension) {
            return true
          }

          files.push(this.formatFileDescriptor(root, relativePath, absolutePath, stats))
          return true
        }, {
          rootPath: root.path,
          blockedSegments: BLOCKED_SEGMENTS
        })

        if (shouldContinue === false || files.length >= limit) {
          break
        }
      }

      if (files.length >= limit) {
        break
      }
    }

    return {
      ok: true,
      root_scope: rootScope,
      template_type: normalizedExtension.slice(1),
      directories,
      limit,
      truncated: files.length >= limit,
      files
    }
  }

  async readFile(options = {}) {
    const roots = await this.getThemeRoots()
    const rootScope = options.root_scope || 'active'
    const relativePath = sanitizeRelativePath(options.path)
    assertAllowedExtension(relativePath, READABLE_EXTENSIONS, 'read')

    const resolved = this.resolveReadableFile(rootScope, relativePath, roots)
    const stats = await fsp.stat(resolved.absolute_path)
    const content = await fsp.readFile(resolved.absolute_path, 'utf8')

    return {
      ok: true,
      root_scope: rootScope,
      root: resolved.root.key,
      theme: resolved.root.label,
      relative_path: relativePath,
      absolute_path: resolved.absolute_path,
      extension: path.extname(relativePath).toLowerCase(),
      kind: classifyFileKind(relativePath),
      size: stats.size,
      modified_at: stats.mtime.toISOString(),
      content
    }
  }

  async readTemplateFile(options = {}) {
    const relativePath = sanitizeRelativePath(options.path)
    normalizeTemplateExtension(path.extname(relativePath))

    return this.readFile({
      ...options,
      path: relativePath
    })
  }

  async writeFile(options = {}) {
    const roots = await this.getThemeRoots()
    const rootScope = options.root_scope || 'stylesheet'
    const relativePath = sanitizeRelativePath(options.path)
    const content = typeof options.content === 'string' ? options.content : String(options.content || '')
    const dryRun = Boolean(options.dry_run)
    const createDirectories = options.create_directories !== false

    assertAllowedExtension(relativePath, WRITABLE_EXTENSIONS, 'write')
    assertWritablePath(relativePath)

    const root = this.resolveWriteTarget(rootScope, roots)
    const absolutePath = this.resolveAbsolutePath(root.path, relativePath)
    const exists = fs.existsSync(absolutePath)
    const previousContent = exists ? await fsp.readFile(absolutePath, 'utf8') : ''
    const changed = !exists || previousContent !== content
    const created = !exists

    if (dryRun) {
      return {
        ok: true,
        dry_run: true,
        root_scope: rootScope,
        root: root.key,
        theme: root.label,
        relative_path: relativePath,
        absolute_path: absolutePath,
        exists,
        created,
        changed,
        bytes_before: Buffer.byteLength(previousContent, 'utf8'),
        bytes_after: Buffer.byteLength(content, 'utf8')
      }
    }

    if (createDirectories) {
      await fsp.mkdir(path.dirname(absolutePath), { recursive: true })
    }

    let backupFile = null

    if (exists) {
      backupFile = await this.createBackup({
        root,
        relativePath,
        content: previousContent
      })
    }

    await fsp.writeFile(absolutePath, content, 'utf8')
    const stats = await fsp.stat(absolutePath)

    return {
      ok: true,
      dry_run: false,
      root_scope: rootScope,
      root: root.key,
      theme: root.label,
      relative_path: relativePath,
      absolute_path: absolutePath,
      exists: true,
      created,
      changed,
      backup_file: backupFile,
      bytes_before: Buffer.byteLength(previousContent, 'utf8'),
      bytes_after: Buffer.byteLength(content, 'utf8'),
      modified_at: stats.mtime.toISOString()
    }
  }

  async writeTemplateFile(options = {}) {
    const relativePath = sanitizeRelativePath(options.path)
    normalizeTemplateExtension(path.extname(relativePath))

    return this.writeFile({
      ...options,
      path: relativePath
    })
  }

  async listBackups(options = {}) {
    const limit = normalizeLimit(options.limit)
    const filterPath = sanitizeRelativePath(options.path || '', { allowEmpty: true })
    const filterKind = String(options.kind || '').trim().toLowerCase()
    const backupFiles = []
    const descriptors = []

    if (!fs.existsSync(this.backupsDirectory)) {
      return {
        ok: true,
        backups_directory: this.backupsDirectory,
        limit,
        truncated: false,
        backups: []
      }
    }

    await collectBackupFiles(this.backupsDirectory, backupFiles)

    for (const backupFile of backupFiles) {
      const descriptor = await this.describeBackupFile(backupFile)

      if (filterPath && descriptor.relative_path !== filterPath) {
        continue
      }

      if (filterKind && descriptor.kind !== filterKind) {
        continue
      }

      descriptors.push(descriptor)
    }

    descriptors.sort((left, right) => (right._timestamp || 0) - (left._timestamp || 0))
    const truncated = descriptors.length > limit

    return {
      ok: true,
      backups_directory: this.backupsDirectory,
      limit,
      truncated,
      backups: descriptors.slice(0, limit).map((descriptor) => {
        const nextDescriptor = { ...descriptor }
        delete nextDescriptor._timestamp
        return nextDescriptor
      })
    }
  }

  async readBackup(options = {}) {
    const backupId = sanitizeRelativePath(options.backup_id || options.id)
    const absolutePath = this.resolveBackupAbsolutePath(backupId)
    const descriptor = await this.describeBackupFile(absolutePath)
    const content = await fsp.readFile(absolutePath, 'utf8')

    delete descriptor._timestamp

    return {
      ...descriptor,
      content
    }
  }

  async restoreBackup(options = {}) {
    const backup = await this.readBackup(options)
    const requestedRootScope = String(options.root_scope || '').trim()
    const rootScope = ['stylesheet', 'template', 'active', 'all'].includes(requestedRootScope)
      ? requestedRootScope
      : (backup.root || 'stylesheet')
    const relativePath = sanitizeRelativePath(options.path || backup.relative_path)
    const dryRun = Boolean(options.dry_run)

    if (!relativePath) {
      throw new Error('Unable to infer the original theme file path from the selected backup.')
    }

    let currentFile = null

    try {
      currentFile = await this.readFile({
        root_scope: rootScope === 'all' ? 'active' : rootScope,
        path: relativePath
      })
    } catch (error) {
      currentFile = null
    }

    const writeResult = await this.writeFile({
      root_scope: rootScope,
      path: relativePath,
      content: backup.content || '',
      dry_run: dryRun,
      create_directories: options.create_directories !== false
    })

    return {
      ...writeResult,
      restored_from_backup: {
        backup_id: backup.backup_id,
        created_at: backup.created_at,
        relative_path: backup.relative_path,
        root: backup.root,
        theme: backup.theme,
        kind: backup.kind,
        bytes: backup.bytes
      },
      current_file: currentFile
        ? {
            exists: true,
            root: currentFile.root,
            theme: currentFile.theme,
            relative_path: currentFile.relative_path,
            absolute_path: currentFile.absolute_path,
            size: currentFile.size,
            modified_at: currentFile.modified_at
          }
        : {
            exists: false
          }
    }
  }

  resolveReadableFile(rootScope, relativePath, roots) {
    for (const root of this.resolveTargets(rootScope, roots, { forWrite: false })) {
      const absolutePath = this.resolveAbsolutePath(root.path, relativePath)

      if (fs.existsSync(absolutePath) && fs.statSync(absolutePath).isFile()) {
        return {
          root,
          absolute_path: absolutePath
        }
      }
    }

    throw new Error(`Theme file not found inside the allowed roots: ${relativePath}`)
  }

  resolveWriteTarget(rootScope, roots) {
    const targets = this.resolveTargets(rootScope, roots, { forWrite: true })

    if (targets.length === 0) {
      throw new Error('No writable theme root is available for the requested scope.')
    }

    return targets[0]
  }

  resolveTargets(rootScope, roots, options = {}) {
    const forWrite = Boolean(options.forWrite)
    const stylesheetRoot = roots.roots.find((root) => root.key === 'stylesheet')
    const templateRoot = roots.roots.find((root) => root.key === 'template') || stylesheetRoot

    switch (rootScope) {
      case 'stylesheet':
        return stylesheetRoot ? [stylesheetRoot] : []
      case 'template':
        return templateRoot ? [templateRoot] : []
      case 'all':
        return uniqueRoots([stylesheetRoot, templateRoot])
      case 'active':
      default:
        if (forWrite) {
          return stylesheetRoot ? [stylesheetRoot] : []
        }

        return uniqueRoots([stylesheetRoot, templateRoot])
    }
  }

  resolveWordPressRoot(filesystemMode) {
    if (this.config.wpRoot) {
      return validateWordPressRoot(path.resolve(this.config.wpRoot))
    }

    if (filesystemMode !== 'local-theme-access') {
      throw new Error('Local filesystem tools are disabled for remote sites unless LCFA_WP_ROOT is set explicitly.')
    }

    const candidates = [
      process.cwd(),
      path.resolve(__dirname, '../../../../../')
    ]

    for (const candidate of candidates) {
      const detected = findWordPressRoot(candidate)

      if (detected) {
        return detected
      }
    }

    throw new Error('Unable to detect the local WordPress root. Set LCFA_WP_ROOT or pass --wp-root.')
  }

  resolveAbsolutePath(rootPath, relativePath) {
    const absolutePath = path.resolve(rootPath, relativePath)
    assertInsideRoot(absolutePath, rootPath)
    return absolutePath
  }

  formatFileDescriptor(root, relativePath, absolutePath, stats) {
    return {
      root: root.key,
      theme: root.label,
      relative_path: relativePath,
      absolute_path: absolutePath,
      extension: path.extname(relativePath).toLowerCase(),
      kind: classifyFileKind(relativePath),
      size: stats.size,
      modified_at: stats.mtime.toISOString()
    }
  }

  getTemplateDirectories(framework) {
    if (framework === 'picowind') {
      return ['views', 'page-templates', 'livecanvas']
    }

    if (framework === 'picostrap') {
      return ['partials', 'loops', 'page-templates', 'livecanvas']
    }

    return TEMPLATE_DIRECTORIES
  }

  async createBackup({ root, relativePath, content }) {
    const stamp = new Date().toISOString().replace(/[:.]/g, '-')
    const backupDirectory = path.join(this.backupsDirectory, stamp.slice(0, 10), root.label)
    const safeFilename = relativePath.replace(/[\\/]/g, '__')
    const backupPath = path.join(backupDirectory, `${stamp}__${safeFilename}`)

    await fsp.mkdir(backupDirectory, { recursive: true })
    await fsp.writeFile(backupPath, content, 'utf8')
    await fsp.writeFile(this.getBackupMetadataPath(backupPath), JSON.stringify({
      root: root.key,
      theme: root.label,
      relative_path: relativePath,
      kind: classifyFileKind(relativePath),
      created_at: new Date().toISOString()
    }, null, 2), 'utf8')

    return backupPath
  }

  getBackupMetadataPath(backupPath) {
    return `${backupPath}.json`
  }

  async readBackupMetadata(backupPath) {
    const metadataPath = this.getBackupMetadataPath(backupPath)

    if (!fs.existsSync(metadataPath)) {
      return {}
    }

    try {
      const content = await fsp.readFile(metadataPath, 'utf8')
      const parsed = JSON.parse(content)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      return {}
    }
  }

  inferBackupRelativePath(backupPath) {
    const filename = path.basename(backupPath)
    const marker = filename.indexOf('__')

    if (marker < 0) {
      return ''
    }

    return filename.slice(marker + 2).replace(/__/g, '/')
  }

  resolveBackupAbsolutePath(backupId) {
    const absolutePath = path.resolve(this.backupsDirectory, backupId)
    assertInsideRoot(absolutePath, this.backupsDirectory)

    if (!fs.existsSync(absolutePath) || !fs.statSync(absolutePath).isFile() || absolutePath.endsWith('.json')) {
      throw new Error(`Backup file not found: ${backupId}`)
    }

    return absolutePath
  }

  async describeBackupFile(absolutePath) {
    const metadata = await this.readBackupMetadata(absolutePath)
    const stats = await fsp.stat(absolutePath)
    const backupId = toPosix(path.relative(this.backupsDirectory, absolutePath))
    const createdAt = typeof metadata.created_at === 'string' && metadata.created_at
      ? metadata.created_at
      : stats.mtime.toISOString()
    const timestamp = Number.isNaN(Date.parse(createdAt)) ? stats.mtimeMs : Date.parse(createdAt)

    return {
      backup_id: backupId,
      backup_path: absolutePath,
      relative_path: metadata.relative_path || this.inferBackupRelativePath(absolutePath),
      root: metadata.root || '',
      theme: metadata.theme || backupId.split('/')[1] || '',
      kind: metadata.kind || classifyFileKind(metadata.relative_path || this.inferBackupRelativePath(absolutePath) || '.txt'),
      bytes: stats.size,
      created_at: createdAt,
      modified_at: stats.mtime.toISOString(),
      _timestamp: timestamp
    }
  }
}

async function walkDirectory(directory, onFile, options) {
  const entries = await fsp.readdir(directory, { withFileTypes: true })

  for (const entry of entries) {
    if (options.blockedSegments.has(entry.name)) {
      continue
    }

    const absolutePath = path.join(directory, entry.name)
    const relativePath = toPosix(path.relative(options.rootPath, absolutePath))

    if (entry.isDirectory()) {
      if (BLOCKED_PREFIXES.some((prefix) => relativePath.startsWith(prefix))) {
        continue
      }

      const shouldContinue = await walkDirectory(absolutePath, onFile, options)

      if (shouldContinue === false) {
        return false
      }

      continue
    }

    if (!entry.isFile()) {
      continue
    }

    const stats = await fsp.stat(absolutePath)
    const shouldContinue = await onFile(absolutePath, relativePath, stats)

    if (shouldContinue === false) {
      return false
    }
  }

  return true
}

async function collectBackupFiles(directory, files) {
  const entries = await fsp.readdir(directory, { withFileTypes: true })

  for (const entry of entries) {
    if (entry.name === '.' || entry.name === '..') {
      continue
    }

    const absolutePath = path.join(directory, entry.name)

    if (entry.isDirectory()) {
      await collectBackupFiles(absolutePath, files)
      continue
    }

    if (!entry.isFile() || absolutePath.endsWith('.json')) {
      continue
    }

    files.push(absolutePath)
  }
}

function findWordPressRoot(startPath) {
  let currentPath = path.resolve(startPath)

  while (currentPath !== path.dirname(currentPath)) {
    if (isWordPressRoot(currentPath)) {
      return currentPath
    }

    currentPath = path.dirname(currentPath)
  }

  return isWordPressRoot(currentPath) ? currentPath : null
}

function isWordPressRoot(candidatePath) {
  return fs.existsSync(path.join(candidatePath, 'wp-content', 'themes'))
}

function validateWordPressRoot(candidatePath) {
  if (!isWordPressRoot(candidatePath)) {
    throw new Error(`Invalid WordPress root: ${candidatePath}`)
  }

  return candidatePath
}

function sanitizeRelativePath(value, options = {}) {
  const allowEmpty = Boolean(options.allowEmpty)
  const normalizedValue = toPosix(String(value || '').trim().replace(/^\/+/, ''))

  if (normalizedValue === '') {
    if (allowEmpty) {
      return ''
    }

    throw new Error('A relative theme file path is required.')
  }

  const normalizedPath = path.posix.normalize(normalizedValue)

  if (
    normalizedPath === '.' ||
    normalizedPath.startsWith('../') ||
    normalizedPath.includes('/../') ||
    normalizedPath.includes('\0')
  ) {
    throw new Error(`Invalid relative path: ${value}`)
  }

  return normalizedPath
}

function assertInsideRoot(absolutePath, rootPath) {
  const normalizedRoot = path.resolve(rootPath)
  const normalizedTarget = path.resolve(absolutePath)

  if (normalizedTarget !== normalizedRoot && !normalizedTarget.startsWith(`${normalizedRoot}${path.sep}`)) {
    throw new Error(`Path escapes the allowed root: ${absolutePath}`)
  }
}

function assertAllowedExtension(relativePath, allowedExtensions, mode) {
  const extension = path.extname(relativePath).toLowerCase()

  if (!allowedExtensions.has(extension)) {
    throw new Error(`Theme file extension not allowed for ${mode}: ${extension || '(none)'}`)
  }
}

function normalizeTemplateExtension(extension) {
  const normalizedExtension = String(extension || '').trim().toLowerCase()
  const withDot = normalizedExtension.startsWith('.') ? normalizedExtension : `.${normalizedExtension}`

  if (!TEMPLATE_EXTENSIONS.has(withDot)) {
    throw new Error(`Template extension not supported: ${extension || '(none)'}`)
  }

  return withDot
}

function assertWritablePath(relativePath) {
  const segments = relativePath.split('/')

  if (segments.some((segment) => BLOCKED_SEGMENTS.has(segment))) {
    throw new Error(`Writing inside protected directories is not allowed: ${relativePath}`)
  }

  if (BLOCKED_PREFIXES.some((prefix) => relativePath.startsWith(prefix))) {
    throw new Error(`Writing inside protected paths is not allowed: ${relativePath}`)
  }
}

function normalizeExtensions(input, allowedExtensions) {
  const source = Array.isArray(input)
    ? input
    : typeof input === 'string' && input.trim() !== ''
      ? input.split(',')
      : []

  if (source.length === 0) {
    return allowedExtensions
  }

  const normalized = source
    .map((item) => String(item || '').trim().toLowerCase())
    .filter(Boolean)
    .map((item) => (item.startsWith('.') ? item : `.${item}`))
    .filter((item) => allowedExtensions.has(item))

  return normalized.length > 0 ? new Set(normalized) : allowedExtensions
}

function normalizeLimit(value) {
  const parsed = Number.parseInt(String(value || DEFAULT_LIST_LIMIT), 10)

  if (Number.isNaN(parsed) || parsed < 1) {
    return DEFAULT_LIST_LIMIT
  }

  return Math.min(parsed, 1000)
}

function classifyFileKind(relativePath) {
  const extension = path.extname(relativePath).toLowerCase()

  if (TEMPLATE_EXTENSIONS.has(extension)) {
    return 'template'
  }

  if (extension === '.css' || extension === '.scss') {
    return 'style'
  }

  if (extension === '.js') {
    return 'script'
  }

  if (extension === '.json' || extension === '.yml' || extension === '.yaml' || extension === '.xml') {
    return 'config'
  }

  return 'text'
}

function toPosix(value) {
  return String(value || '').replace(/\\/g, '/')
}

function uniqueRoots(roots) {
  const seen = new Set()

  return roots.filter((root) => {
    if (!root || seen.has(root.path)) {
      return false
    }

    seen.add(root.path)
    return true
  })
}

module.exports = {
  ThemeFilesystem
}
