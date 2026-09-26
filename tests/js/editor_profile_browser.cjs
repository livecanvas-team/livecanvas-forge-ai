// Only synthetic fixture markup is rendered; no live browser or personal data.
const assert = require('node:assert/strict')
const { execFileSync } = require('node:child_process')
const fs = require('node:fs')
const path = require('node:path')
const { chromium } = require('../../mcp/node_modules/playwright')
async function main() {
  const root = path.resolve(__dirname, '../..'), out = path.join(root, '.impeccable/review/editor-profile')
  fs.mkdirSync(out, { recursive: true })
  const html = execFileSync('php', [path.join(root, 'tests/fixtures/editor-profile-ui.php')], { encoding: 'utf8' })
  const browser = await chromium.launch({ channel: 'chrome', headless: true }), errors = [], checks = []
  try {
    for (const [device, width] of [['desktop', 1280], ['mobile', 390], ['zoom-layout', 640]]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } })
      page.on('pageerror', error => errors.push(error.message))
      await page.route('**/*', route => {
        const url = new URL(route.request().url())
        if (url.origin === 'http://fixture.local' && url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html })
        if (url.origin === 'http://fixture.local' && ['/assets/admin.css', '/assets/admin-v2.css'].includes(url.pathname)) return route.fulfill({ path: path.join(root, url.pathname) })
        if (url.href === 'http://example.test/wp-content/plugins/images/lc-logo.svg') return route.fulfill({ path: path.resolve(root, '../livecanvas/images/lc-logo.svg'), contentType: 'image/svg+xml' })
        if (url.href === 'http://example.test/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/codex-color.svg') return route.fulfill({ path: path.join(root, 'assets/agent-icons/codex-color.svg'), contentType: 'image/svg+xml' })
        return route.abort()
      })
      await page.goto('http://fixture.local/')
      const preset = page.locator('.lcfa-hero-chip').filter({ has: page.locator('dt', { hasText: 'Editor preset' }) })
      assert.equal(await preset.locator('dd').innerText(), 'daisyui-5')
      assert.ok((await preset.getAttribute('class')).includes('is-other'))
      const details = page.locator('.lcfa-hero-details-panel')
      const toggle = details.locator('summary')
      await toggle.focus(); await page.keyboard.press('Enter')
      assert.equal(await details.evaluate(el => el.open), true)
      assert.equal(await details.getByText('Editing suggestions only. Agents must verify compiled plugins before using their classes.').isVisible(), true)
      assert.ok((await page.locator('.lcfa-hero-copy').boundingBox()).width >= Math.min(280, width - 100), 'Details squeezed the page heading')
      assert.equal(await page.locator('img').evaluateAll(images => images.every(img => img.complete && img.naturalWidth > 0)), true, 'Fixture logo must load')
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, device + ' horizontal overflow')
      await page.screenshot({ path: path.join(out, `${device}.png`), fullPage: true })
      checks.push(device)
      await page.close()
    }
    assert.deepEqual(errors, [])
    process.stdout.write(JSON.stringify({ ok: true, checks, data: 'synthetic', screenshots: out }) + '\n')
  } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
