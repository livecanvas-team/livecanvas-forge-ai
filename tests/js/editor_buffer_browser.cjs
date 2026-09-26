// Real browser, shipped assets, synthetic LiveCanvas contract and REST only.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { chromium } = require('../../mcp/node_modules/playwright')
async function main() {
  const root = path.resolve(__dirname, '../..'); const out = path.join(root, '.impeccable/review/editor-buffer')
  fs.mkdirSync(out, { recursive: true })
  const browser = await chromium.launch({ channel: 'chrome', headless: true }); const errors = []; const checks = []
  try {
    for (const [name, width, height] of [['desktop', 1280, 900], ['mobile', 390, 844]]) {
      const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' })
      let releaseResult; let posts = 0; let refreshes = 0; let mode = 'apply'; let payload; let verified = true
      page.on('pageerror', error => errors.push(error.message))
      const config = { postId: 42, targetId: 42, threadId: 'fixture', threads: { fixture: { id: 'fixture', title: 'Fixture A', messages: [] }, other: { id: 'other', title: 'Fixture B', messages: [] } },
        agent: { enabled: true, client: 'opencode' }, agentRequestEndpoint: '/api/request', labels: {} }
      await page.route('**/*', async route => {
        const request = route.request(); const url = new URL(request.url())
        assert.equal(url.origin, 'http://bridge-buffer.test')
        if (url.pathname === '/api/request' && request.method() === 'POST') {
          posts++; payload = request.postDataJSON()
          return route.fulfill({ json: { request: { id: 'fixture-request', thread_id: 'fixture', status: 'queued' } } })
        }
        if (url.pathname === '/api/request') {
          await new Promise(resolve => { releaseResult = resolve })
          return route.fulfill({ json: { request: { id: 'fixture-request', thread_id: 'fixture', status: 'completed', server_saved_targets: verified ? [42] : [], result: {
            ok: true, mode, action: 'update_page', target_id: 42, message: 'Fixture result.', verification_states: { saved: mode === 'apply', compiled: 'not_checked', visually_verified: 'not_checked', published: false }
          } } } })
        }
        if (url.pathname === '/editor-source') {
          refreshes++
          return route.fulfill({ contentType: 'text/html', body: '<html><head></head><body><main id="lc-main"><h1>Saved fixture result</h1></main></body></html>' })
        }
        if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, url.pathname) })
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/editor-chat.css"><style>*{box-sizing:border-box}body{margin:0;background:#12131c;color:#f6f7fd;font-family:system-ui}main{padding:32px;max-width:50ch}h1{font-size:24px}#previewiframe{width:100%;height:160px;border:0}button,textarea{font:inherit}.lcfa-editor-bridge__thread-bar{display:grid;gap:12px}</style></head><body><main><h1>Editor protection test</h1><p>Synthetic test. No coding agent is connected.</p><iframe id="previewiframe" srcdoc="<p>Local editor preview</p>"></iframe></main><button id="toggle-code-editor" hidden>Code</button><div class="lcfa-editor-shell is-open" data-lcfa-editor-shell><aside class="lcfa-editor-drawer" aria-label="AI Bridge"><div class="lcfa-editor-bridge__head"><h2 class="lcfa-editor-bridge__title">About us draft</h2></div><div class="lcfa-editor-bridge__section"><label class="lcfa-editor-bridge__field"><span>Request</span><textarea data-lcfa-editor-prompt>Update the draft.</textarea></label><button class="lcfa-editor-bridge__button is-primary" data-lcfa-editor-analyze>Send</button></div><select data-lcfa-editor-thread aria-label="Conversation"><option value="fixture">Fixture A</option><option value="other">Fixture B</option></select><div class="lcfa-editor-bridge__thread-bar"><span class="lcfa-editor-bridge__thread-status" role="status" aria-live="polite" data-lcfa-editor-status></span></div><div class="lcfa-editor-bridge__editor-notice" data-lcfa-editor-notice hidden><p role="status" aria-live="polite" data-lcfa-editor-notice-text></p><a class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-review-saved href="/saved-preview" target="_blank" rel="noopener noreferrer" hidden>Review saved page</a></div><details data-lcfa-editor-support-details><summary>Result details</summary><div data-lcfa-editor-result><p data-lcfa-editor-result-summary></p><div class="lcfa-editor-bridge__result-meta" data-lcfa-editor-result-meta></div></div></details></aside><script type="application/json" data-lcfa-editor-config>${JSON.stringify(config)}</script></div><script>
          window.doc=new DOMParser().parseFromString('<html><head></head><body><main id="lc-main"><h1>Original fixture</h1></main></body></html>','text/html');
          window.getPageHTML=()=>window.doc.querySelector('html').innerHTML;window.original_document_html=window.getPageHTML();
          window.lc_editor_current_post_id=42;window.lc_editor_url_to_load=location.origin+'/editor-source';
          window.parseFromComplexString=html=>new DOMParser().parseFromString(html,'text/html');window.filterPreviewHTML=html=>html;
          window.lcMainStore={setDoc(doc){this.doc=doc}};window.tryToEnrichPreview=()=>{};window.saveHistoryStep=()=>{};
        </script><script src="/assets/editor-buffer.js"></script><script src="/assets/editor-chat.js"></script></body></html>` })
      })
      await page.goto('http://bridge-buffer.test/')
      const send = page.getByRole('button', { name: 'Send', exact: true }); const status = page.locator('[data-lcfa-editor-status]')
      await page.evaluate(() => { doc.querySelector('h1').textContent = 'Local draft, keep me' })
      await send.click(); await page.waitForFunction(() => document.querySelector('[data-lcfa-editor-status]').dataset.state === 'editor_attention')
      assert.equal(posts, 0); assert.match(await status.textContent(), /Save your editor changes/)
      await page.screenshot({ path: path.join(out, name + '-dirty.png'), fullPage: true })
      await page.evaluate(() => { original_document_html = getPageHTML() })
      await send.click(); await page.waitForFunction(() => document.querySelector('[data-lcfa-editor-status]').dataset.state === 'queueing')
      // Wait for the actual test response handle, without assuming a timer fired.
      for (let i = 0; !releaseResult && i < 100; i++) await new Promise(resolve => setTimeout(resolve, 20))
      assert.equal(typeof releaseResult, 'function')
      await page.evaluate(() => { doc.querySelector('h1').textContent = 'New local edit during agent work' })
      await page.locator('[data-lcfa-editor-thread]').selectOption('other')
      const otherState = await status.getAttribute('data-state')
      releaseResult(); releaseResult = null
      await page.waitForFunction(() => !document.querySelector('[data-lcfa-editor-review-saved]').hidden)
      assert.equal(await page.evaluate(() => doc.querySelector('h1').textContent), 'New local edit during agent work')
      assert.equal(refreshes, 0); assert.equal(JSON.stringify(payload).includes('Local draft, keep me'), false)
      assert.equal(await status.getAttribute('data-state'), otherState, 'Completion in A must not change conversation B status')
      assert.match(await page.locator('[data-lcfa-editor-notice-text]').textContent(), /Editor kept/)
      const review = page.getByRole('link', { name: 'Review saved page' }); await page.keyboard.press('Tab'); await review.focus()
      assert.equal(await review.evaluate(el => getComputedStyle(el).outlineStyle), 'solid')
      assert.ok((await review.boundingBox()).height >= 44)
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true)
      await page.screenshot({ path: path.join(out, name + '-kept.png'), fullPage: true })

      await page.reload(); await page.locator('[data-lcfa-editor-thread]').selectOption('fixture'); await send.click()
      for (let i = 0; !releaseResult && i < 100; i++) await new Promise(resolve => setTimeout(resolve, 20))
      assert.equal(typeof releaseResult, 'function'); releaseResult(); releaseResult = null
      await page.waitForFunction(() => doc.querySelector('h1').textContent === 'Saved fixture result')
      assert.equal(refreshes, 1); assert.equal(await status.getAttribute('data-state'), 'completed')
      assert.match(await page.locator('[data-lcfa-editor-result-meta]').textContent(), /Agent reports Compiled: Not checked/)
      mode = 'preview'; await send.click()
      for (let i = 0; !releaseResult && i < 100; i++) await new Promise(resolve => setTimeout(resolve, 20))
      assert.equal(typeof releaseResult, 'function'); releaseResult(); releaseResult = null
      await page.waitForFunction(() => document.querySelector('[data-lcfa-editor-status]').dataset.state === 'previewed')
      assert.equal(refreshes, 1, 'Preview/read-only results must not reload the editor')
      mode = 'apply'; verified = false; await send.click()
      for (let i = 0; !releaseResult && i < 100; i++) await new Promise(resolve => setTimeout(resolve, 20))
      assert.equal(typeof releaseResult, 'function'); releaseResult(); releaseResult = null
      await page.waitForFunction(() => !document.querySelector('[data-lcfa-editor-review-saved]').hidden)
      assert.equal(refreshes, 1, 'Agent-authored saved=true must not authorize a reload')
      assert.match(await page.locator('[data-lcfa-editor-notice-text]').textContent(), /save is unverified/)
      checks.push({ viewport: name, unsaved_send_blocked: true, background_thread_edits_preserved: true, clean_refresh: true, preview_does_not_refresh: true, spoofed_save_does_not_refresh: true, reported_verification_separate: true })
      await page.close()
    }
    assert.deepEqual(errors, [])
    fs.writeFileSync(path.join(out, 'evidence.json'), JSON.stringify({ fixture: 'synthetic', actual_coding_agent: 'not_invoked', checks }, null, 2))
    console.log(JSON.stringify({ ok: true, fixture: 'synthetic', checks }))
  } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
