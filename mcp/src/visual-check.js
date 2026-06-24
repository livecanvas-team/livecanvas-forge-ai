const fs = require('node:fs/promises')
const os = require('node:os')
const path = require('node:path')

class VisualCheck {
  constructor({ config }) {
    this.config = config
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

    const playwright = await this.loadPlaywright()
    if (!playwright.ok) {
      return playwright
    }

    const viewports = this.normalizeViewports(options)
    const outputDirectory = await this.resolveOutputDirectory(options)
    const browser = await playwright.chromium.launch({ headless: true })
    const results = []

    try {
      for (const viewport of viewports) {
        const page = await browser.newPage({
          viewport: {
            width: viewport.width,
            height: viewport.height
          }
        })

        await page.goto(url, {
          waitUntil: 'networkidle',
          timeout: Number.isInteger(options.timeout_ms) ? options.timeout_ms : 30000
        })

        if (Number.isInteger(options.wait_ms) && options.wait_ms > 0) {
          await page.waitForTimeout(options.wait_ms)
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

          return {
            title: document.title,
            overflow_x: overflowX,
            overflow_y: overflowY,
            scroll_width: root.scrollWidth,
            client_width: root.clientWidth,
            scroll_height: root.scrollHeight,
            client_height: root.clientHeight,
            selectors: selectorResults
          }
        }, Array.isArray(options.selectors) ? options.selectors.map(String).filter(Boolean) : [])

        results.push({
          viewport,
          screenshot_path: screenshotPath,
          analysis
        })

        await page.close()
      }
    } finally {
      await browser.close()
    }

    return {
      ok: true,
      url,
      output_directory: outputDirectory,
      results
    }
  }

  async loadPlaywright() {
    try {
      const playwright = require('playwright')
      if (!playwright || !playwright.chromium) {
        throw new Error('playwright.chromium is unavailable')
      }

      return {
        ok: true,
        chromium: playwright.chromium
      }
    } catch (error) {
      return {
        ok: false,
        status: 'browser_unavailable',
        message: 'visual_check requires Playwright in the local MCP runtime. Install it in this project or run from a runtime that already provides Playwright.',
        detail: error instanceof Error ? error.message : String(error)
      }
    }
  }

  normalizeViewports(options = {}) {
    if (Array.isArray(options.viewports) && options.viewports.length > 0) {
      return options.viewports.map((viewport, index) => ({
        name: String(viewport.name || `viewport-${index + 1}`).replace(/[^a-z0-9_-]/gi, '-').toLowerCase(),
        width: Number.parseInt(String(viewport.width || 1440), 10),
        height: Number.parseInt(String(viewport.height || 1000), 10)
      })).filter((viewport) => viewport.width > 0 && viewport.height > 0)
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
