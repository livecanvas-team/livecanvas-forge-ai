// Shipped JS/CSS with synthetic English content and REST responses only.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { chromium } = require('../../mcp/node_modules/playwright')
async function main() {
  const root = path.resolve(__dirname, '../..')
  const out = path.join(root, '.impeccable/review/editor-stop')
  fs.mkdirSync(out, { recursive: true })
  const browser = await chromium.launch({ channel: 'chrome', headless: true })
  const errors = []; const checks = []
  try {
    for (const [name, width, height] of [['desktop', 1280, 900], ['mobile', 390, 844]]) {
      const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' })
      await page.clock.install()
      let state = 'running'
      const record = () => ({ id: 'request-fixture', thread_id: 'thread-fixture', post_id: 42, status: state })
      const config = { postId: 42, targetId: 42, threadId: 'thread-fixture', threads: { 'thread-fixture': { id: 'thread-fixture', messages: [] } },
        agentRequestEndpoint: '/api/request', agentStopEndpoint: '/api/cancel', agentPendingEndpoint: '/api/pending', labels: {}, agent: { enabled: true, client: 'codex' } }
      page.on('pageerror', error => errors.push(error.message))
      await page.route('**/*', route => {
        const url = new URL(route.request().url())
        if (url.origin !== 'http://bridge-stop.test') throw new Error('Unexpected external request')
        if (url.pathname === '/api/pending') return route.fulfill({ json: { requests: [record()] } })
        if (url.pathname === '/api/cancel') { state = 'stop_requested'; return route.fulfill({ json: { request: record() } }) }
        if (url.pathname === '/api/request') return route.fulfill({ json: { request: record() } })
        if (url.pathname === '/editor-chat.js') return route.fulfill({ path: path.join(root, 'assets/editor-chat.js') })
        if (url.pathname === '/editor-chat.css') return route.fulfill({ path: path.join(root, 'assets/editor-chat.css') })
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/editor-chat.css"><style>body{margin:0;background:#12131c;color:#f6f7fd;font-family:system-ui}main{padding:32px;max-width:50ch}h1{font-size:24px}button,textarea{font:inherit}*{box-sizing:border-box}</style></head><body><main><h1>Bridge stop test</h1><p>Synthetic interface fixture. No coding agent is connected.</p></main><div class="lcfa-editor-shell is-open" data-lcfa-editor-shell><button data-lcfa-editor-open hidden>Open</button><aside class="lcfa-editor-drawer" aria-label="AI Bridge chat"><div class="lcfa-editor-bridge__head"><h2 class="lcfa-editor-bridge__title">About us draft</h2></div><p class="lcfa-editor-bridge__target-line">Local test fixture</p><div class="lcfa-editor-bridge__section"><label class="lcfa-editor-bridge__field"><span>Request</span><textarea data-lcfa-editor-prompt readonly>Review the draft page.</textarea></label><div class="lcfa-editor-bridge__actions"><button class="lcfa-editor-bridge__button is-neutral is-step-primary" data-lcfa-editor-stop hidden>Stop</button></div></div><div class="lcfa-editor-bridge__section"><span class="lcfa-editor-bridge__thread-status" role="status" aria-live="polite" data-lcfa-editor-status>Checking worker status...</span></div></aside><script type="application/json" data-lcfa-editor-config>${JSON.stringify(config)}</script></div><script src="/editor-chat.js"></script></body></html>` })
      })
      await page.goto('http://bridge-stop.test/')
      const stop = page.getByRole('button', { name: 'Stop', exact: true })
      await stop.waitFor({ state: 'visible' }); await stop.focus()
      const dimensions = await stop.boundingBox()
      assert.ok(dimensions.height >= 44 && dimensions.x >= 0 && dimensions.x + dimensions.width <= width)
      assert.equal(await stop.evaluate(el => getComputedStyle(el).outlineStyle), 'solid')
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true)
      await page.screenshot({ path: path.join(out, name + '-running.png'), fullPage: true })
      await stop.press('Enter')
      await page.waitForFunction(() => document.querySelector('[data-lcfa-editor-status]').dataset.state === 'stop_requested')
      assert.equal(await stop.isDisabled(), true)
      await page.screenshot({ path: path.join(out, name + '-stop-requested.png'), fullPage: true })
      state = 'stopped'; await page.clock.fastForward(6000)
      await page.waitForFunction(() => document.querySelector('[data-lcfa-editor-status]').dataset.state === 'stopped')
      assert.equal(await stop.isVisible(), false)
      assert.match(await page.locator('[data-lcfa-editor-status]').textContent(), /Check the agent/)
      await page.screenshot({ path: path.join(out, name + '-stopped.png'), fullPage: true })
      checks.push({ viewport: name, stop_height: dimensions.height, keyboard_stop: true, external_agent_stop_claim: false, horizontal_overflow: false })
      await page.close()
    }
    assert.deepEqual(errors, [])
    fs.writeFileSync(path.join(out, 'evidence.json'), JSON.stringify({ fixture: 'synthetic', actual_coding_agent: 'not_invoked', checks }, null, 2))
    console.log(JSON.stringify({ ok: true, fixture: 'synthetic', checks }))
  } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
