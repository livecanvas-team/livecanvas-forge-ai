const fs = require('node:fs/promises')
const fsSync = require('node:fs')
const os = require('node:os')
const path = require('node:path')

const READINESS_SCHEMA = 'visual-check-readiness.v1'

class VisualCheck {
  constructor({ config = {}, moduleLoader = null, existsSync = null, env = null } = {}) {
    this.config = config
    this.moduleLoader = moduleLoader || require
    this.existsSync = existsSync || fsSync.existsSync
    this.env = env || process.env
  }

  async getReadiness(options = {}) {
    const runtime = await this.prepareRuntime(options, options.probe_launch === true)
    return runtime.readiness
  }

  async run(options = {}) {
    const url = String(options.url || this.config.siteUrl || '').trim()
    if (!url) {
      return {
        ok: false,
        status: 'missing_url',
        message: 'Pass a URL or configure LCFA_SITE_URL before running visual_check.'
      }
    }

    const runtime = await this.prepareRuntime(options, false)
    if (!runtime.readiness.ready) {
      return runtime.readiness
    }

    const viewports = this.normalizeViewports(options)
    const outputDirectory = await this.resolveOutputDirectory(options)
    let browser

    try {
      browser = await runtime.chromium.launch(runtime.launchOptions)
    } catch (error) {
      return this.browserLaunchFailure(runtime.readiness, error)
    }

    const results = []

    try {
      for (const viewport of viewports) {
        const page = await browser.newPage({
          viewport: {
            width: viewport.width,
            height: viewport.height
          }
        })
        const consoleErrors = []
        const pageErrors = []

        page.on('console', (message) => {
          if (message.type() === 'error') {
            consoleErrors.push(message.text())
          }
        })
        page.on('pageerror', (error) => {
          pageErrors.push(error instanceof Error ? error.message : String(error))
        })

        await page.goto(url, {
          waitUntil: this.normalizeWaitUntil(options.wait_until),
          timeout: Number.isInteger(options.timeout_ms) ? options.timeout_ms : 30000
        })

        await page.evaluate(async () => {
          if (document.fonts && document.fonts.ready) {
            await document.fonts.ready
          }
        }).catch(() => {})

        const waitMs = Number.isInteger(options.wait_ms) ? options.wait_ms : 250
        if (waitMs > 0) {
          await page.waitForTimeout(waitMs)
        }

        const screenshotPath = path.join(outputDirectory, `visual-check-${Date.now()}-${viewport.name}.png`)
        await page.screenshot({
          path: screenshotPath,
          fullPage: options.full_page !== false
        })

        const analysis = await page.evaluate((selectors) => {
          const root = document.documentElement
          const body = document.body
          const overflowX = Math.max(root.scrollWidth, body ? body.scrollWidth : 0) > root.clientWidth + 1
          const overflowY = Math.max(root.scrollHeight, body ? body.scrollHeight : 0) > root.clientHeight + 1
          const selectorResults = {}

          for (const selector of selectors) {
            const element = document.querySelector(selector)
            if (!element) {
              selectorResults[selector] = {
                found: false
              }
              continue
            }

            const rect = element.getBoundingClientRect()
            const style = window.getComputedStyle(element)
            selectorResults[selector] = {
              found: true,
              rect: {
                x: rect.x,
                y: rect.y,
                width: rect.width,
                height: rect.height
              },
              display: style.display,
              position: style.position,
              zIndex: style.zIndex,
              color: style.color,
              backgroundColor: style.backgroundColor,
              fontSize: style.fontSize,
              fontFamily: style.fontFamily
            }
          }

          const brokenImages = Array.from(document.images || [])
            .filter((image) => !image.complete || image.naturalWidth === 0)
            .slice(0, 20)
            .map((image) => image.currentSrc || image.src || '')

          return {
            title: document.title,
            overflow_x: overflowX,
            overflow_y: overflowY,
            scroll_width: root.scrollWidth,
            client_width: root.clientWidth,
            scroll_height: root.scrollHeight,
            client_height: root.clientHeight,
            shell: {
              headers: document.querySelectorAll('header').length,
              mains: document.querySelectorAll('main').length,
              footers: document.querySelectorAll('footer').length
            },
            broken_images: brokenImages,
            selectors: selectorResults
          }
        }, Array.isArray(options.selectors) ? options.selectors.map(String).filter(Boolean) : [])

        results.push({
          viewport,
          screenshot_path: screenshotPath,
          console_errors: consoleErrors.slice(0, 20),
          page_errors: pageErrors.slice(0, 20),
          analysis
        })

        await page.close()
      }
    } catch (error) {
      return {
        ok: false,
        status: 'visual_check_failed',
        message: 'The browser started, but the visual check did not complete.',
        detail: error instanceof Error ? error.message : String(error),
        url,
        output_directory: outputDirectory,
        partial_results: results,
        runtime: runtime.readiness
      }
    } finally {
      await browser.close().catch(() => {})
    }

    return {
      ok: true,
      status: 'completed',
      url,
      output_directory: outputDirectory,
      runtime: runtime.readiness,
      results
    }
  }

  async prepareRuntime(options = {}, probeLaunch = false) {
    const loaded = await this.loadPlaywright()
    const installGuidance = this.getInstallGuidance()

    if (!loaded.ok) {
      return {
        readiness: {
          schema_version: READINESS_SCHEMA,
          ok: false,
          ready: false,
          status: 'playwright_missing',
          package_available: false,
          package_version: '',
          browser_available: false,
          browser_source: '',
          executable_path: '',
          launch_verified: false,
          message: 'Playwright is not installed in the MCP runtime used by this coding-agent project.',
          detail: loaded.detail,
          next_action: installGuidance.package_command,
          install_guidance: installGuidance
        },
        chromium: null,
        launchOptions: null
      }
    }

    const resolved = this.resolveBrowser(loaded.chromium, options)
    if (!resolved.ok) {
      return {
        readiness: {
          schema_version: READINESS_SCHEMA,
          ok: false,
          ready: false,
          status: resolved.status,
          package_available: true,
          package_version: loaded.version,
          browser_available: false,
          browser_source: resolved.source,
          executable_path: resolved.executablePath,
          launch_verified: false,
          message: resolved.message,
          next_action: installGuidance.browser_command,
          install_guidance: installGuidance
        },
        chromium: loaded.chromium,
        launchOptions: null
      }
    }

    const launchOptions = {
      headless: options.headless !== false,
      executablePath: resolved.executablePath
    }
    const readiness = {
      schema_version: READINESS_SCHEMA,
      ok: true,
      ready: true,
      status: probeLaunch ? 'checking_launch' : 'ready',
      package_available: true,
      package_version: loaded.version,
      browser_available: true,
      browser_source: resolved.source,
      executable_path: resolved.executablePath,
      launch_verified: false,
      message: probeLaunch
        ? 'Playwright and Chromium were found; verifying browser launch.'
        : 'Playwright and a Chromium-compatible browser are available.',
      next_action: probeLaunch ? 'Wait for the launch probe.' : 'Run visual_check or call visual_check_status with probe_launch=true.',
      install_guidance: installGuidance
    }

    if (probeLaunch) {
      let browser
      try {
        browser = await loaded.chromium.launch(launchOptions)
        readiness.status = 'ready'
        readiness.launch_verified = true
        readiness.message = 'Playwright launched Chromium successfully.'
        readiness.next_action = 'Run visual_check.'
      } catch (error) {
        return {
          readiness: this.browserLaunchFailure(readiness, error),
          chromium: loaded.chromium,
          launchOptions
        }
      } finally {
        if (browser) {
          await browser.close().catch(() => {})
        }
      }
    }

    return {
      readiness,
      chromium: loaded.chromium,
      launchOptions
    }
  }

  async loadPlaywright() {
    try {
      const playwright = this.moduleLoader('playwright')
      if (!playwright || !playwright.chromium) {
        throw new Error('playwright.chromium is unavailable')
      }

      let version = ''
      try {
        version = String(this.moduleLoader('playwright/package.json').version || '')
      } catch (error) {
        version = ''
      }

      return {
        ok: true,
        chromium: playwright.chromium,
        version
      }
    } catch (error) {
      return {
        ok: false,
        detail: error instanceof Error ? error.message : String(error)
      }
    }
  }

  resolveBrowser(chromium, options = {}) {
    const explicitExecutable = String(options.executable_path || this.env.LCFA_PLAYWRIGHT_EXECUTABLE_PATH || '').trim()

    if (explicitExecutable !== '') {
      if (this.existsSync(explicitExecutable)) {
        return {
          ok: true,
          source: 'configured',
          executablePath: explicitExecutable
        }
      }

      return {
        ok: false,
        status: 'configured_browser_missing',
        source: 'configured',
        executablePath: explicitExecutable,
        message: 'LCFA_PLAYWRIGHT_EXECUTABLE_PATH points to a browser executable that does not exist.'
      }
    }

    const systemExecutable = this.getSystemBrowserCandidates().find((candidate) => this.existsSync(candidate))
    if (systemExecutable) {
      return {
        ok: true,
        source: 'system',
        executablePath: systemExecutable
      }
    }

    let managedExecutable = ''
    try {
      managedExecutable = typeof chromium.executablePath === 'function'
        ? String(chromium.executablePath() || '')
        : ''
    } catch (error) {
      managedExecutable = ''
    }

    if (managedExecutable !== '' && this.existsSync(managedExecutable)) {
      return {
        ok: true,
        source: 'playwright',
        executablePath: managedExecutable
      }
    }

    return {
      ok: false,
      status: 'chromium_missing',
      source: 'playwright',
      executablePath: managedExecutable,
      message: 'Playwright is installed, but no Chromium-compatible browser executable is available to this MCP runtime.'
    }
  }

  browserLaunchFailure(readiness, error) {
    const guidance = readiness.install_guidance || this.getInstallGuidance()

    return {
      ...readiness,
      ok: false,
      ready: false,
      status: 'browser_launch_failed',
      launch_verified: true,
      message: 'Chromium was found but could not be launched by the MCP runtime.',
      detail: error instanceof Error ? error.message : String(error),
      next_action: guidance.browser_command,
      install_guidance: guidance
    }
  }

  getInstallGuidance() {
    return {
      package_command: 'npm install --save-dev playwright',
      browser_command: 'npx playwright install chromium',
      verify_tool: 'visual_check_status',
      note: 'Run these commands on the same machine and user account that starts the LiveCanvas AI Bridge MCP server.'
    }
  }

  getSystemBrowserCandidates() {
    const candidates = [
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
      '/Applications/Chromium.app/Contents/MacOS/Chromium',
      '/usr/bin/google-chrome',
      '/usr/bin/google-chrome-stable',
      '/usr/bin/microsoft-edge',
      '/usr/bin/chromium',
      '/usr/bin/chromium-browser'
    ]
    const programFiles = String(this.env.PROGRAMFILES || '')
    const localAppData = String(this.env.LOCALAPPDATA || '')

    if (programFiles !== '') {
      candidates.push(path.join(programFiles, 'Google', 'Chrome', 'Application', 'chrome.exe'))
      candidates.push(path.join(programFiles, 'Microsoft', 'Edge', 'Application', 'msedge.exe'))
    }
    if (localAppData !== '') {
      candidates.push(path.join(localAppData, 'Google', 'Chrome', 'Application', 'chrome.exe'))
    }

    return candidates
  }

  normalizeWaitUntil(value) {
    const normalized = String(value || '').trim().toLowerCase()
    return ['load', 'domcontentloaded', 'networkidle', 'commit'].includes(normalized)
      ? normalized
      : 'domcontentloaded'
  }

  normalizeViewports(options = {}) {
    if (Array.isArray(options.viewports) && options.viewports.length > 0) {
      const normalized = options.viewports.map((viewport, index) => ({
        name: String(viewport.name || `viewport-${index + 1}`).replace(/[^a-z0-9_-]/gi, '-').toLowerCase(),
        width: Number.parseInt(String(viewport.width || 1440), 10),
        height: Number.parseInt(String(viewport.height || 1000), 10)
      })).filter((viewport) => viewport.width >= 200 && viewport.width <= 3840 && viewport.height >= 200 && viewport.height <= 4320)

      if (normalized.length > 0) {
        return normalized
      }
    }

    return [
      { name: 'desktop', width: 1440, height: 1000 },
      { name: 'mobile', width: 390, height: 844 }
    ]
  }

  async resolveOutputDirectory(options = {}) {
    const configured = String(options.output_directory || '').trim()
    const directory = configured || path.join(os.tmpdir(), 'livecanvas-ai-bridge-visual-checks')
    await fs.mkdir(directory, { recursive: true })
    return directory
  }
}

module.exports = {
  VisualCheck
}
