// Real PHP component and shipped assets, synthetic REST responses. No WordPress writes.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const { execFileSync } = require('node:child_process')
const { chromium } = require('../../mcp/node_modules/playwright')

async function main() {
  const root = path.resolve(__dirname, '../..')
  const fixture = JSON.parse(execFileSync('php', [path.join(root, 'tests/fixtures/connection-screen.php')], { encoding:'utf8' }))
  const out = path.join(root, '.impeccable/review/connection-integration')
  fs.mkdirSync(out, { recursive:true })
  const browser = await chromium.launch({ channel:'chrome', headless:true })
  try {
    const page = await browser.newPage({ viewport:{width:1280,height:900}, reducedMotion:'reduce' })
    const errors = [], unexpected = [], calls = [], attempts = new Map()
    let serial = 0, errorResponse = false
    page.on('pageerror', error => errors.push(error.message))
    await page.clock.install()
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', { value:{writeText:async () => { throw new Error('Clipboard denied') }}, configurable:true })
      window.fixtureNativeCopy = document.execCommand.bind(document)
      document.execCommand = () => false
    })
    await page.route('**/*', async route => {
      const request = route.request(), url = new URL(request.url())
      if (url.origin !== 'http://bridge.test') { unexpected.push(url.href); return route.abort() }
      if (url.pathname.startsWith('/api/')) {
        calls.push({method:request.method(),path:url.pathname,body:request.postDataJSON()})
        if (url.pathname === '/api/connections/attempts') {
          const client = request.postDataJSON().client, id = (++serial).toString(16).padStart(32,'0')
          const attempt = {id,client,state:'waiting_for_client',...fixture.instructions[client],package_version:'0.2.0-beta.7',required_package_version:'0.2.0-beta.7'}
          attempts.set(id,attempt)
          return route.fulfill({json:attempt,status:201})
        }
        if (url.pathname.endsWith('/pairing/approve')) {
          const attempt = [...attempts.values()].find(value => value.pairing_id === request.postDataJSON().pairing_id)
          assert.ok(attempt); attempt.state = 'verifying'
          return route.fulfill({json:{ok:true}})
        }
        if (errorResponse) return route.fulfill({status:503,json:{message:'Synthetic network failure'}})
        const value = attempts.get(url.pathname.split('/').pop())
        return route.fulfill({json:value})
      }
      if (url.pathname.startsWith('/assets/')) {
        const asset = path.resolve(root, '.' + url.pathname)
        assert.ok(asset.startsWith(path.join(root,'assets') + path.sep))
        return route.fulfill({path:asset})
      }
      if (url.pathname === '/') return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/admin-v2.css"><link rel="stylesheet" href="/assets/connect.css"><style>body{margin:0;padding:32px;background:#12131c;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.lcfa-admin{margin:0 auto}@media(max-width:640px){body{padding:16px}}</style></head><body><div class="wrap lcfa-admin">${fixture.html}</div><script>window.lcfaAdmin={restUrl:'http://bridge.test/api/',restNonce:'fixture-nonce'};window.lcfaConnect=${JSON.stringify(fixture.labels)};</script><script src="/assets/connect.js"></script></body></html>`})
      unexpected.push(url.href); return route.abort()
    })
    await page.goto('http://bridge.test/')
    const current = () => attempts.get(new URL(page.url()).searchParams.get('connection_attempt'))
    const state = async value => page.waitForFunction(value => document.querySelector('#lcfa-connect').dataset.state === value,value)
    const tick = async ms => { await page.clock.runFor(ms); await page.waitForLoadState('networkidle') }
    const capture = async name => { await page.screenshot({path:path.join(out,name + '.png'),fullPage:true}) }
    await state('waiting_for_client')
    assert.match(await page.locator('#lcfa-connect-prompt').inputValue(),/npx --yes/)
    assert.equal(await page.locator('[data-connect-details]').getAttribute('open'),null)
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'red')
    await capture('desktop-setup')
    await page.click('[data-connect-copy]')
    const fallback = await page.locator('[data-connect-copy-feedback]').innerText()
    assert.match(fallback,/Copy unavailable/)
    assert.ok(await page.locator('#lcfa-connect-prompt').evaluate(el => el.selectionEnd === el.value.length && el.selectionStart === 0))
    await tick(3100)
    assert.equal(await page.locator('[data-connect-copy-feedback]').innerText(),fallback)
    await capture('desktop-manual')
    current().state = 'authorization_required'; current().pairing_id = 'fixture-pair'; current().user_code = 'ALFR-2048'
    await tick(3100); await state('authorization_required')
    assert.equal(await page.locator('[data-connect-approve]').isEnabled(),false)
    await page.check('[data-connect-match]'); await page.click('[data-connect-approve]'); await state('verifying')
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'amber')
    current().state = 'ready'; current().verified_at = '2026-09-25T12:00:00Z'
    await tick(3100); await state('ready')
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'green')
    assert.match(await page.locator('#lcfa-connect-prompt').inputValue(),/Do not edit or publish/)
    assert.equal(await page.locator('[data-connect-copy-feedback]').isVisible(),false)
    await capture('desktop-ready')
    const readyId = current().id
    errorResponse = true
    await tick(30100); await state('unavailable')
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'amber')
    errorResponse = false
    await page.click('[data-connect-retry]'); await state('ready')
    assert.equal(current().id,readyId)
    current().state = 'reconnect_required'; current().reason = 'runtime_update_required'; current().package_version = '0.2.0-beta.5'
    await tick(30100); await state('reconnect_required')
    assert.equal(await page.locator('[data-connect-status]').innerText(),'Connection update required')
    assert.equal(await page.locator('#lcfa-connect-prompt').isVisible(),false)
    await capture('desktop-update')
    await page.click('[data-connect-retry]'); await state('waiting_for_client')
    assert.notEqual(current().id,readyId)
    let layoutChecks = 0
    for (const width of [1280,994,390,320]) {
      await page.setViewportSize({width,height:900})
      for (const client of ['codex','opencode','cursor','claude-code','claude-desktop']) {
        await page.selectOption('#lcfa-connect-client',client)
        await page.waitForFunction(client => document.querySelector('#lcfa-connect').dataset.state === 'waiting_for_client' && document.querySelector('#lcfa-connect-client').value === client && !document.querySelector('[data-connect-copy]').disabled,client)
        await page.locator('[data-connect-logo]').evaluate(async image => { await image.decode(); if (!image.naturalWidth) throw new Error('Logo did not load') })
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),width + '/' + client)
        if (client === 'claude-desktop') assert.match(await page.locator('#lcfa-connect-prompt').inputValue(),/^npx /)
        layoutChecks++
      }
    }
    await page.setViewportSize({width:390,height:844})
    await page.selectOption('#lcfa-connect-client','codex'); await state('waiting_for_client')
    await capture('mobile-setup')
    current().state = 'authorization_required'; current().pairing_id = 'mobile-pair'; current().user_code = 'ALFR-2048'
    await tick(3100); await state('authorization_required'); await capture('mobile-approval')
    await page.check('[data-connect-match]'); await page.click('[data-connect-approve]'); await state('verifying')
    current().state = 'ready'; await tick(3100); await state('ready')
    current().state = 'reconnect_required'; current().reason = 'access_revoked'
    await tick(30100); await state('reconnect_required')
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'red')
    await capture('mobile-revoked')
    await page.selectOption('#lcfa-connect-client','claude-desktop'); await state('waiting_for_client')
    await page.evaluate(() => { navigator.clipboard.writeText = async value => { window.fixtureCopied = value } })
    const expected = await page.locator('#lcfa-connect-prompt').inputValue()
    await page.click('[data-connect-copy]')
    await page.waitForFunction(() => document.querySelector('[data-connect-copy-feedback]').textContent === 'Copied!')
    assert.equal(await page.evaluate(() => window.fixtureCopied),expected)
    assert.equal(await page.locator('#lcfa-connect').getAttribute('data-tone'),'amber')
    await page.evaluate(() => {
      Object.defineProperty(navigator, 'clipboard', { value:undefined, configurable:true })
      document.execCommand = window.fixtureNativeCopy
    })
    await page.click('[data-connect-copy]')
    await page.waitForFunction(() => document.querySelector('[data-connect-copy-feedback]').textContent === 'Copied!')
    assert.ok(await page.locator('#lcfa-connect-prompt').evaluate(el => el.selectionEnd === el.value.length && el.selectionStart === 0))
    assert.deepEqual(errors,[]); assert.deepEqual(unexpected,[])
    const evidence = {scope:'Real PHP screen/CSS/JS; synthetic REST and clipboard only',layoutChecks,errors,unexpected,checks:['visible real installer prompt','full manual selection preserved across polls','code-match consent gating','authenticated-proof-only green','runtime-update amber','network error invalidates green','same attempt recovered after network failure','revocation red','five agent logos','desktop terminal command','exact clipboard contents']}
    fs.writeFileSync(path.join(out,'evidence.json'),JSON.stringify(evidence,null,2))
    console.log(JSON.stringify(evidence,null,2))
  } finally { await browser.close() }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
