const crypto = require('node:crypto')
const fs = require('node:fs/promises')
const path = require('node:path')

const IMAGE_EXTENSIONS = new Set(['.avif', '.gif', '.jpeg', '.jpg', '.png', '.svg', '.webp'])
const VIDEO_EXTENSIONS = new Set(['.mov', '.mp4', '.webm'])

class AssetDiscovery {
  async run(options = {}) {
    const directory = path.resolve(String(options.directory || '').trim())
    if (!directory || directory === path.parse(directory).root) {
      return {
        ok: false,
        status: 'missing_directory',
        message: 'Pass a concrete asset directory to scan.'
      }
    }

    const recursive = options.recursive !== false
    const limit = Math.max(1, Math.min(Number.parseInt(String(options.limit || 200), 10) || 200, 1000))
    const nameIncludes = Array.isArray(options.name_includes)
      ? options.name_includes.map((value) => String(value).toLowerCase()).filter(Boolean)
      : []
    const assets = []

    await this.walk(directory, recursive, async (filePath) => {
      if (assets.length >= limit) {
        return
      }

      const ext = path.extname(filePath).toLowerCase()
      if (!IMAGE_EXTENSIONS.has(ext) && !VIDEO_EXTENSIONS.has(ext)) {
        return
      }

      const basename = path.basename(filePath)
      if (nameIncludes.length > 0 && !nameIncludes.some((part) => basename.toLowerCase().includes(part))) {
        return
      }

      const stat = await fs.stat(filePath)
      const buffer = await fs.readFile(filePath)
      assets.push({
        asset_id: this.assetId(filePath, buffer),
        path: filePath,
        relative_path: path.relative(directory, filePath),
        basename,
        extension: ext.replace(/^\./, ''),
        kind: IMAGE_EXTENSIONS.has(ext) ? 'image' : 'video',
        mime: this.mimeForExtension(ext),
        bytes: stat.size,
        checksum_sha256: crypto.createHash('sha256').update(buffer).digest('hex')
      })
    })

    return {
      ok: true,
      directory,
      recursive,
      count: assets.length,
      truncated: assets.length >= limit,
      assets
    }
  }

  async uploadLocalAssets(client, options = {}) {
    const discovery = await this.run(options)
    if (!discovery.ok) {
      return discovery
    }

    const uploads = []
    const postId = Number.parseInt(String(options.post_id || 0), 10) || 0
    const setFirstFeatured = Boolean(options.set_first_featured)
    const metadataMap = options.metadata && typeof options.metadata === 'object' ? options.metadata : {}

    for (let index = 0; index < discovery.assets.length; index += 1) {
      const asset = discovery.assets[index]
      const metadata = this.metadataForAsset(asset, metadataMap)
      const buffer = await fs.readFile(asset.path)
      const result = await client.uploadMedia({
        source_type: 'base64',
        filename: metadata.filename || asset.basename,
        mime_type: asset.mime,
        base64: buffer.toString('base64'),
        post_id: postId,
        set_featured: setFirstFeatured && index === 0,
        title: metadata.title || this.titleFromBasename(asset.basename),
        alt: metadata.alt || this.titleFromBasename(asset.basename),
        caption: metadata.caption || '',
        description: metadata.description || '',
        asset_id: asset.asset_id,
        checksum_sha256: asset.checksum_sha256
      })
      const payload = result && typeof result === 'object' && result.result && typeof result.result === 'object'
        ? result.result
        : result

      uploads.push({
        ...asset,
        upload: payload
      })
    }

    return {
      ok: uploads.every((entry) => entry.upload && entry.upload.ok !== false),
      directory: discovery.directory,
      count: uploads.length,
      uploaded: uploads.filter((entry) => entry.upload && entry.upload.ok !== false).length,
      failed: uploads.filter((entry) => !entry.upload || entry.upload.ok === false).length,
      assets: uploads
    }
  }

  async walk(directory, recursive, visitor) {
    const entries = await fs.readdir(directory, { withFileTypes: true })
    for (const entry of entries) {
      if (entry.name.startsWith('.')) {
        continue
      }

      const filePath = path.join(directory, entry.name)
      if (entry.isDirectory()) {
        if (recursive) {
          await this.walk(filePath, recursive, visitor)
        }
        continue
      }

      if (entry.isFile()) {
        await visitor(filePath)
      }
    }
  }

  assetId(filePath, buffer) {
    const name = path.basename(filePath).replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase()
    const hash = crypto.createHash('sha1').update(buffer).digest('hex').slice(0, 10)

    return `${name || 'asset'}-${hash}`
  }

  mimeForExtension(extension) {
    const map = {
      '.avif': 'image/avif',
      '.gif': 'image/gif',
      '.jpeg': 'image/jpeg',
      '.jpg': 'image/jpeg',
      '.mov': 'video/quicktime',
      '.mp4': 'video/mp4',
      '.png': 'image/png',
      '.svg': 'image/svg+xml',
      '.webm': 'video/webm',
      '.webp': 'image/webp'
    }

    return map[extension] || 'application/octet-stream'
  }

  metadataForAsset(asset, metadataMap) {
    const byId = metadataMap[asset.asset_id]
    if (byId && typeof byId === 'object') {
      return byId
    }

    const byBasename = metadataMap[asset.basename]
    if (byBasename && typeof byBasename === 'object') {
      return byBasename
    }

    const byRelativePath = metadataMap[asset.relative_path]
    if (byRelativePath && typeof byRelativePath === 'object') {
      return byRelativePath
    }

    return {}
  }

  titleFromBasename(basename) {
    return String(basename || '')
      .replace(/\.[a-z0-9]+$/i, '')
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
  }
}

module.exports = {
  AssetDiscovery
}
