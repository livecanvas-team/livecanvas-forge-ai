// Synthetic HTML rendered by the production PHP control. No live-site browser automation.
const assert = require('node:assert/strict')
const { execFileSync } = require('node:child_process')
const path = require('node:path')
const fs = require('node:fs')
const { chromium } = require('../../mcp/node_modules/playwright')

async function main() {
  const root = path.resolve(__dirname, '../..'), out = path.join(root, '.impeccable/review/site-knowledge')
  fs.mkdirSync(out, { recursive: true })
  const browser = await chromium.launch({ channel: 'chrome', headless: true }), checks = [], errors = []
  try {
    for (const [device, width] of [['desktop', 1280], ['mobile', 390]]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } })
      page.on('pageerror', error => errors.push(error.message))
      let state = 'empty', submitted
      await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url())
        assert.equal(url.origin, 'http://fixture.local')
        if (url.pathname === '/assets/admin.css') return route.fulfill({ path: path.join(root, 'assets/admin.css') })
        if (request.method() === 'POST') {
          submitted = new URLSearchParams(request.postData())
          state = 'approved'
        }
        return route.fulfill({ contentType: 'text/html', body: execFileSync('php', [path.join(root, 'tests/fixtures/site-knowledge-ui.php'), state], { encoding: 'utf8' }) })
      })
      for (state of ['empty', 'approved', 'conflict', 'unavailable']) {
        await page.goto('http://fixture.local/')
        const details = page.locator('details')
        if (!await details.evaluate(el => el.open)) {
          await page.locator('summary').focus(); await page.keyboard.press('Enter')
          assert.equal(await details.evaluate(el => el.open), true)
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${device}/${state} overflow`)
        if (state !== 'unavailable') {
          const input = page.getByRole('textbox', { name: 'Shared site instructions', exact: true })
          const save = page.getByRole('button', { name: 'Save shared instructions' })
          await input.focus(); await page.keyboard.press('Tab')
          assert.equal(await save.evaluate(el => el === document.activeElement), true)
          assert.equal(await save.evaluate(el => getComputedStyle(el).outlineStyle), 'solid')
          assert.ok((await save.boundingBox()).height >= 44, 'Touch target below 44px')
          assert.ok(await page.locator('input[name="knowledge_revision"]').inputValue())
          if (state === 'conflict') {
            assert.match(await input.inputValue(), /Preserve this draft/)
            assert.equal(await page.getByRole('textbox', { name: 'Current shared instructions' }).inputValue(), 'Use concise English. Keep the site palette.')
          }
        } else {
          assert.equal(await page.getByRole('button').count(), 0)
          assert.match(await page.getByRole('textbox', { name: 'Your unsaved draft' }).inputValue(), /Preserve this draft/)
        }
        await page.screenshot({ path: path.join(out, `${device}-${state}.png`), fullPage: true })
        checks.push(`${device}/${state}`)
      }
      state = 'empty'; await page.goto('http://fixture.local/'); await page.locator('summary').click()
      await page.getByRole('textbox', { name: 'Shared site instructions', exact: true }).fill('Use concise English.')
      await page.getByRole('button', { name: 'Save shared instructions' }).click()
      await page.waitForURL('**/wp-admin/admin-post.php')
      assert.equal(submitted.get('instructions'), 'Use concise English.')
      assert.equal(submitted.get('action'), 'lcfa_site_knowledge'); assert.equal(submitted.get('_wpnonce'), 'review')
      await page.close()
    }
    assert.deepEqual(errors, [])
    process.stdout.write(JSON.stringify({ ok: true, checks, keyboard: true, form_submission: 'synthetic', live_wordpress_ui: 'not_tested', screenshots: out }) + '\n')
  } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
