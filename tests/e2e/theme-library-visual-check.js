const path = require('node:path')
const fs = require('node:fs')
const { chromium } = require('../../mcp/node_modules/playwright')

const targetUrl = process.env.LCFA_E2E_URL || 'http://test-ai-forge.local/'
const outputDirectory = process.env.LCFA_E2E_SCREENSHOT_DIR || '/private/tmp'
const systemChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'

async function run() {
  const executablePath = process.env.LCFA_E2E_CHROME_PATH
    || (fs.existsSync(systemChrome) ? systemChrome : undefined)
  const browser = await chromium.launch({ headless: true, executablePath })
  const results = []

  try {
    for (const target of [
      { name: 'desktop', width: 1440, height: 1000 },
      { name: 'mobile', width: 390, height: 844 },
    ]) {
      const page = await browser.newPage({
        viewport: { width: target.width, height: target.height },
        deviceScaleFactor: 1,
      })
      const consoleErrors = []

      page.on('console', (message) => {
        if (message.type() === 'error') {
          consoleErrors.push(message.text())
        }
      })

      const url = new URL(targetUrl)
      url.searchParams.set('_lcfa_e2e_visual_check', Date.now().toString())
      const response = await page.goto(url.toString(), {
        waitUntil: 'networkidle',
        timeout: 30000,
      })
      const metrics = await page.evaluate(() => ({
        title: document.title,
        bodyTextLength: document.body.innerText.trim().length,
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        scrollHeight: document.documentElement.scrollHeight,
        headers: document.querySelectorAll('header').length,
        mains: document.querySelectorAll('main').length,
        footers: document.querySelectorAll('footer').length,
        stylesheets: Array.from(document.styleSheets).map((sheet) => sheet.href).filter(Boolean),
        brokenImages: Array.from(document.images)
          .filter((image) => image.complete && image.naturalWidth === 0)
          .map((image) => image.currentSrc || image.src),
      }))
      const screenshot = path.join(outputDirectory, `lcfa-theme-library-${target.name}.png`)

      await page.screenshot({ path: screenshot, fullPage: true })
      await page.close()

      results.push({
        target,
        status: response ? response.status() : 0,
        screenshot,
        consoleErrors,
        metrics,
      })
    }
  } finally {
    await browser.close()
  }

  process.stdout.write(`${JSON.stringify(results, null, 2)}\n`)

  const failed = results.some((result) => (
    result.status !== 200
    || result.metrics.bodyTextLength < 200
    || result.metrics.scrollWidth > result.metrics.clientWidth + 1
    || result.metrics.brokenImages.length > 0
  ))

  if (failed) {
    process.exitCode = 1
  }
}

run().catch((error) => {
  console.error(error)
  process.exitCode = 1
})
