const assert = require('node:assert/strict')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')

const { PicostrapCompiler } = require('../src/picostrap-compiler')
const { createToolRegistry } = require('../src/tool-registry')

async function testLocalCompileUsesThemeFilesystemRoots() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-picostrap-local-'))
  const childRoot = path.join(root, 'wp-content', 'themes', 'picostrap-child')
  const parentRoot = path.join(root, 'wp-content', 'themes', 'picostrap5')

  fs.mkdirSync(path.join(childRoot, 'sass'), { recursive: true })
  fs.mkdirSync(path.join(parentRoot, 'sass', 'bootstrap'), { recursive: true })
  fs.writeFileSync(path.join(childRoot, 'sass', 'main.scss'), '@import "bootstrap/functions"; body { color: $primary; }')
  fs.writeFileSync(path.join(parentRoot, 'sass', 'bootstrap', '_functions.scss'), '@function tint-color($color, $weight) { @return $color; }')

  let storedCss = ''

  const client = {
    async getPicostrapCompileManifest() {
      return {
        result: {
          framework: 'picostrap',
          site_mode: 'local',
          main_sass: '$primary: #ff2d55; @import "main";',
          compile_mode: 'expanded'
        }
      }
    },
    async storePicostrapBundle(css) {
      storedCss = css
      return {
        result: {
          ok: true,
          bundle_path: '/tmp/bundle.css',
          bundle_url: 'http://localhost:8887/wp-content/themes/picostrap-child/css-output/bundle.css?ver=23',
          bundle_version: 23,
          compiled_at: '2026-04-14 10:30:00'
        }
      }
    },
    async getPicostrapCompileSource() {
      throw new Error('remote source endpoint should not be used for local compile')
    }
  }

  const themeFiles = {
    async getThemeRoots() {
      return {
        roots: [
          { path: childRoot },
          { path: parentRoot }
        ]
      }
    }
  }

  const compiler = new PicostrapCompiler({
    client,
    config: { wpRoot: root },
    themeFiles
  })

  const result = await compiler.buildBundle()

  assert.equal(result.ok, true)
  assert.equal(result.build_executed, true)
  assert.match(storedCss, /#ff2d55/)
  assert.match(storedCss, /body/)
}

async function testLocalCompileUsesManifestRootsBeforeThemeDiscovery() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-picostrap-manifest-roots-'))
  const childRoot = path.join(root, 'wp-content', 'themes', 'picostrap-child')
  const parentRoot = path.join(root, 'wp-content', 'themes', 'picostrap5')

  fs.mkdirSync(path.join(childRoot, 'sass'), { recursive: true })
  fs.mkdirSync(path.join(parentRoot, 'sass', 'bootstrap'), { recursive: true })
  fs.writeFileSync(path.join(childRoot, 'sass', 'main.scss'), '@import "bootstrap/functions"; body { color: $primary; }')
  fs.writeFileSync(path.join(parentRoot, 'sass', 'bootstrap', '_functions.scss'), '@function tint-color($color, $weight) { @return $color; }')

  let storedCss = ''

  const client = {
    async getPicostrapCompileManifest() {
      return {
        result: {
          framework: 'picostrap',
          site_mode: 'local',
          stylesheet: 'picostrap-child',
          template: 'picostrap5',
          main_sass: '$primary: #00cfff; @import "main";',
          compile_mode: 'expanded'
        }
      }
    },
    async storePicostrapBundle(css) {
      storedCss = css
      return {
        result: {
          ok: true,
          bundle_path: '/tmp/bundle.css',
          bundle_url: 'http://localhost:8887/wp-content/themes/picostrap-child/css-output/bundle.css?ver=24',
          bundle_version: 24,
          compiled_at: '2026-04-14 11:26:00'
        }
      }
    },
    async getPicostrapCompileSource() {
      throw new Error('remote source endpoint should not be used for manifest-root local compile')
    }
  }

  const compiler = new PicostrapCompiler({
    client,
    config: { wpRoot: root },
    themeFiles: {
      async getThemeRoots() {
        throw new Error('theme discovery should not run when manifest roots are available')
      }
    }
  })

  const result = await compiler.buildBundle()

  assert.equal(result.ok, true)
  assert.match(storedCss, /#00cfff/)
}

async function testLocalCompileTriesUnderscoredCandidatesBeforeRemoteFallback() {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'lcfa-picostrap-local-underscored-'))
  const childRoot = path.join(root, 'wp-content', 'themes', 'picostrap-child')

  fs.mkdirSync(path.join(childRoot, 'sass'), { recursive: true })
  fs.writeFileSync(path.join(childRoot, 'sass', 'main.scss'), '@import "bootstrap-loader"; body { color: $primary; }')
  fs.writeFileSync(path.join(childRoot, 'sass', '_bootstrap-loader.scss'), '$primary: #ff2d55;')

  let storedCss = ''
  let remoteFetches = 0

  const client = {
    async getPicostrapCompileManifest() {
      return {
        result: {
          framework: 'picostrap',
          site_mode: 'local',
          main_sass: '@import "main";',
          compile_mode: 'expanded'
        }
      }
    },
    async storePicostrapBundle(css) {
      storedCss = css
      return {
        result: {
          ok: true,
          bundle_path: '/tmp/bundle.css',
          bundle_url: 'http://localhost:8887/wp-content/themes/picostrap-child/css-output/bundle.css?ver=25',
          bundle_version: 25,
          compiled_at: '2026-04-14 11:25:00'
        }
      }
    },
    async getPicostrapCompileSource() {
      remoteFetches += 1
      throw new Error('remote source endpoint should not be used before checking underscored local candidates')
    }
  }

  const themeFiles = {
    async getThemeRoots() {
      return {
        roots: [
          { path: childRoot }
        ]
      }
    }
  }

  const compiler = new PicostrapCompiler({
    client,
    config: { wpRoot: root },
    themeFiles
  })

  const result = await compiler.buildBundle()

  assert.equal(result.ok, true)
  assert.equal(remoteFetches, 0)
  assert.match(storedCss, /#ff2d55/)
}

async function testRemoteCompileFetchesScssSourcesThroughClient() {
  const requestedImports = []
  let storedCss = ''

  const client = {
    async getPicostrapCompileManifest() {
      return {
        result: {
          framework: 'picostrap',
          site_mode: 'remote',
          main_sass: '$primary: #6a00ff; @import "main";',
          compile_mode: 'expanded'
        }
      }
    },
    async getPicostrapCompileSource(importPath) {
      requestedImports.push(importPath)

      const map = {
        'main.scss': '@import "bootstrap/functions"; body { color: $primary; }',
        'bootstrap/_functions.scss': '@function tint-color($color, $weight) { @return $color; }'
      }

      if (!Object.prototype.hasOwnProperty.call(map, importPath)) {
        return {
          result: {
            ok: false,
            message: 'not found'
          }
        }
      }

      return {
        result: {
          ok: true,
          contents: map[importPath]
        }
      }
    },
    async storePicostrapBundle(css) {
      storedCss = css
      return {
        result: {
          ok: true,
          bundle_path: '/tmp/bundle.css',
          bundle_url: 'https://remote.test/wp-content/themes/picostrap-child/css-output/bundle.css?ver=8',
          bundle_version: 8,
          compiled_at: '2026-04-14 10:31:00'
        }
      }
    }
  }

  const compiler = new PicostrapCompiler({
    client,
    config: {},
    themeFiles: {
      async getThemeRoots() {
        throw new Error('local roots should not be used for remote compile')
      }
    }
  })

  const result = await compiler.buildBundle()

  assert.equal(result.ok, true)
  assert.equal(result.bundle_version, 8)
  assert.ok(requestedImports.includes('main.scss'))
  assert.ok(requestedImports.includes('bootstrap/_functions.scss'))
  assert.match(storedCss, /#6a00ff/)
}

async function testRunLcCommandAutoCompileOrchestration() {
  const calls = []
  const client = {
    async runCommand(payload) {
      calls.push(payload)

      if (payload.action === 'design_system_compose') {
        return {
          result: {
            ok: true,
            action: 'design_system_compose',
            mode: 'preview',
            target_stack: 'picostrap',
            preview_url: 'http://localhost:8887/?lcfa_design_preview=1',
            apply_payload: {
              action: 'design_system_apply',
              framework: 'picostrap',
              colors: { primary: '#ff2d55' }
            },
            warnings: [],
            data: {}
          }
        }
      }

      if (payload.dry_run === true) {
        return {
          result: {
            ok: true,
            action: 'design_system_apply',
            mode: 'preview',
            target_stack: 'picostrap',
            warnings: [],
            data: {
              current_state_fingerprint: 'current-state',
              compile_manifest: {
                framework: 'picostrap',
                source_fingerprint: 'proposed-state',
                main_sass: '$primary: #ff2d55; @import "main";'
              }
            }
          }
        }
      }

      assert.match(payload.compiled_css, /#ff2d55/)
      assert.equal(payload.compiled_source_fingerprint, 'proposed-state')
      assert.equal(payload.expected_state_fingerprint, 'current-state')

      return {
        result: {
          ok: true,
          action: payload.action,
          mode: 'apply',
          target_stack: 'picostrap',
          frontend_url: 'http://localhost:8887/',
          build_executed: true,
          bundle_version: 24,
          audit_id: 'audit-design-system',
          rollback_available: true,
          warnings: [],
          data: {}
        }
      }
    }
  }

  const registry = createToolRegistry(
    client,
    {},
    { async buildCache() { return { ok: true } } },
    {
      async compileBundle({ manifest }) {
        assert.equal(manifest.source_fingerprint, 'proposed-state')
        return {
          ok: true,
          css: 'body{color:#ff2d55;}',
          source_fingerprint: 'proposed-state',
          compiled_bytes: 23,
          build_strategy: 'bridge_dart_sass_transaction',
          build_required: true,
          build_executed: false,
          warnings: []
        }
      }
    }
  )

  const result = await registry.invoke('run_lc_command', {
    action: 'design_system_compose',
    framework: 'picostrap',
    auto_apply: true
  })

  const payload = result.result || result

  assert.equal(payload.ok, true)
  assert.equal(payload.build_executed, true)
  assert.equal(payload.bundle_version, 24)
  assert.equal(payload.audit_id, 'audit-design-system')
  assert.equal(payload.rollback_available, true)
  assert.equal(payload.data.compile.source_fingerprint, 'proposed-state')
  assert.equal(calls.length, 3)
  assert.equal(calls[0].auto_apply, false)
  assert.equal(calls[1].dry_run, true)
  assert.equal(calls[2].dry_run, false)
}

async function testRunLcCommandCompileFailureDoesNotApply() {
  const calls = []
  const client = {
    async runCommand(payload) {
      calls.push(payload)
      return {
        result: {
          ok: true,
          action: 'design_system_apply',
          mode: 'preview',
          target_stack: 'picostrap',
          warnings: [],
          data: {
            current_state_fingerprint: 'current-state',
            compile_manifest: {
              framework: 'picostrap',
              source_fingerprint: 'proposed-state',
              main_sass: '@import "missing";'
            }
          }
        }
      }
    }
  }

  const registry = createToolRegistry(client, {}, {}, {
    async compileBundle() {
      throw new Error('SCSS import not found: missing.scss')
    }
  })
  const result = await registry.invoke('run_lc_command', {
    action: 'design_system_apply',
    framework: 'picostrap',
    colors: { primary: '#ff2d55' }
  })
  const payload = result.result || result

  assert.equal(payload.ok, false)
  assert.match(payload.message, /failed before apply/i)
  assert.equal(payload.build_executed, false)
  assert.equal(calls.length, 1)
  assert.equal(calls[0].dry_run, true)
}

async function testSiteFoundationCompilesBeforeApply() {
  const calls = []
  const client = {
    async runCommand(payload) {
      calls.push(payload)

      if (payload.dry_run === true) {
        return {
          result: {
            ok: true,
            action: 'site_foundation_run',
            mode: 'preview',
            data: {
              steps: {
                design_system_apply: {
                  ok: true,
                  target_stack: 'picostrap',
                  data: {
                    current_state_fingerprint: 'foundation-current',
                    compile_manifest: {
                      framework: 'picostrap',
                      source_fingerprint: 'foundation-proposed',
                      main_sass: '$primary: #112233; @import "main";'
                    }
                  }
                }
              }
            }
          }
        }
      }

      assert.equal(payload.dry_run, false)
      assert.match(payload.compiled_css, /#112233/)
      assert.equal(payload.compiled_source_fingerprint, 'foundation-proposed')
      assert.equal(payload.expected_state_fingerprint, 'foundation-current')

      return {
        result: {
          ok: true,
          action: 'site_foundation_run',
          mode: 'apply',
          audit_id: 'foundation-audit',
          data: { steps: {} }
        }
      }
    }
  }

  const registry = createToolRegistry(client, {}, {}, {
    async compileBundle({ manifest }) {
      assert.equal(manifest.source_fingerprint, 'foundation-proposed')
      return {
        ok: true,
        css: 'body{color:#112233;}',
        source_fingerprint: 'foundation-proposed',
        compiled_bytes: 20,
        build_strategy: 'bridge_dart_sass_transaction'
      }
    }
  })

  const result = await registry.invoke('run_lc_command', {
    action: 'site_foundation_run',
    framework: 'picostrap',
    design_system: {
      colors: { primary: '#112233' }
    },
    skip_global_shell: true,
    skip_pages: true
  })
  const payload = result.result || result

  assert.equal(payload.ok, true)
  assert.equal(payload.audit_id, 'foundation-audit')
  assert.equal(payload.data.compile.source_fingerprint, 'foundation-proposed')
  assert.equal(calls.length, 2)
  assert.equal(calls[0].dry_run, true)
  assert.equal(calls[1].dry_run, false)
}

async function run() {
  await testLocalCompileUsesThemeFilesystemRoots()
  await testLocalCompileUsesManifestRootsBeforeThemeDiscovery()
  await testLocalCompileTriesUnderscoredCandidatesBeforeRemoteFallback()
  await testRemoteCompileFetchesScssSourcesThroughClient()
  await testRunLcCommandAutoCompileOrchestration()
  await testRunLcCommandCompileFailureDoesNotApply()
  await testSiteFoundationCompilesBeforeApply()
}

run()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
