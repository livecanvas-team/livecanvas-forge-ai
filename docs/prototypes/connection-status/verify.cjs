// Prototype-only interaction tests. Never contacts WordPress or a coding agent.
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('../../../mcp/node_modules/playwright');
(async () => {
  const out = path.resolve(__dirname, '../../../.impeccable/review/connection-status/minimal');
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [], unexpectedRequests = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('request', r => { if (!r.url().startsWith('http://127.0.0.1:8768/') || r.method() !== 'GET') unexpectedRequests.push(r.url()); });
  await page.addInitScript(() => Object.defineProperty(navigator, 'clipboard', { value: { writeText: async () => { throw new Error('Blocked test clipboard'); } } }));
  await page.goto('http://127.0.0.1:8768/');
  const capture = async name => { await page.evaluate(() => { document.querySelector('.preview-controls').open = false; window.scrollTo(0, 0); }); await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true }); };
  const choose = async state => { await page.locator('.preview-controls').evaluate(el => { el.open = true; }); await page.selectOption('#scenario', state); };
  const tone = () => page.locator('#connection').getAttribute('data-tone');
  assert.equal(await page.locator('#prompt').isVisible(), true);
  assert.equal(await page.locator('#primary').innerText(), 'Copy prompt');
  assert.equal(await page.locator('#connection-details').getAttribute('open'), null);
  const prompt = await page.locator('#prompt').inputValue();
  assert.match(prompt, /PREVIEW ONLY/);
  await capture('desktop-start');
  await page.click('#primary');
  assert.equal(await tone(), 'amber');
  assert.match(await page.locator('#copy-feedback').innerText(), /Copy unavailable.*(Command|Ctrl)\+C/);
  assert.equal(await page.locator('#prompt').evaluate(el => el.selectionEnd - el.selectionStart === el.value.length), true);
  await page.selectOption('#agent', 'cursor'); await page.selectOption('#agent', 'codex');
  assert.match(await page.locator('#copy-feedback').innerText(), /Copy unavailable/);
  await capture('desktop-manual');
  await choose('approval');
  assert.equal(await page.locator('#primary').isEnabled(), false);
  assert.equal(await page.locator('#prompt').isVisible(), false);
  assert.equal(await page.locator('#copy-icon').isVisible(), false);
  await page.check('#code-match'); await page.click('#primary');
  assert.equal(await tone(), 'amber');
  assert.equal(await page.locator('#connection').getAttribute('data-state'), 'verifying');
  await page.click('#demo-event'); assert.equal(await tone(), 'green');
  await capture('desktop-connected');
  const tones = { start:'red', manual:'amber', waiting:'amber', approval:'amber', verifying:'amber', connected:'green', update:'amber', optional:'green', stale:'amber', failed:'red', revoked:'red', expired:'amber' };
  for (const width of [1440,1280,994,390,320]) {
    await page.setViewportSize({ width, height: width < 640 ? 844 : 720 });
    for (const agent of ['codex','opencode','cursor','claude-code','claude-desktop']) {
      await page.selectOption('#agent', agent);
      const expectedLogo = { codex:'codex-color.svg', opencode:'opencode.svg', cursor:'cursor.svg', 'claude-code':'claude-color.svg', 'claude-desktop':'claude-color.svg' }[agent];
      assert.equal(await page.locator('#agent-logo').getAttribute('src'), 'logos/' + expectedLogo);
      assert.ok(await page.locator('#agent-logo').evaluate(async image => { await image.decode(); return image.naturalWidth > 0 && image.naturalHeight > 0; }));
      if (width === 1280) await page.locator('.agent-field').screenshot({ path: path.join(out, 'logo-' + agent + '.png') });
      for (const [state, expected] of Object.entries(tones)) {
        await choose(state);
        assert.equal(await tone(), expected);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, width + '/' + agent + '/' + state);
        if (agent === 'claude-code') assert.match(await page.locator('#diagnostics').textContent(), /Hello Alfred/);
        if (agent === 'claude-desktop' && state === 'start') assert.match(await page.locator('#destination').textContent(), /Terminal or PowerShell/);
      }
    }
  }
  await page.selectOption('#agent', 'codex');
  await page.setViewportSize({ width:1280, height:720 }); await choose('update'); await capture('desktop-update');
  const bounds = await page.locator('#primary').boundingBox(); assert.ok(bounds.y + bounds.height <= 720);
  await page.setViewportSize({ width:390, height:844 });
  await choose('start'); await capture('mobile-start');
  await choose('manual'); await capture('mobile-manual');
  await choose('approval'); await capture('mobile-approval');
  await choose('update'); await capture('mobile-update');
  await page.setViewportSize({ width:1280, height:720 }); await choose('start');
  await page.evaluate(() => { navigator.clipboard.writeText = async text => { window.copiedSample = text; }; });
  await page.click('#primary');
  assert.equal(await page.evaluate(() => window.copiedSample), prompt);
  assert.equal(await page.locator('#copy-feedback').innerText(), 'Copied!');
  assert.equal(await tone(), 'amber');
  await choose('connected'); await page.click('#primary');
  assert.equal(await tone(), 'green');
  assert.match(await page.evaluate(() => window.copiedSample), /Do not edit or publish/);
  await choose('revoked'); await page.click('#primary');
  assert.equal(await page.locator('#connection').getAttribute('data-state'), 'start');
  await choose('expired'); await page.click('#primary');
  assert.equal(await page.locator('#connection').getAttribute('data-state'), 'start');
  await page.keyboard.press('Tab');
  assert.ok(await page.evaluate(() => document.activeElement !== document.body));
  assert.deepEqual(errors, []); assert.deepEqual(unexpectedRequests, []);
  const evidence = { revision: 'minimal-prompt-first', stateAgentViewportChecks:300, pageErrors:errors, unexpectedRequests, behavior:['prompt visible on arrival','exact prompt copied','clipboard failure selects full text','fallback persists per agent','approval requires code match','approval never verifies connection','simulation reply verifies','named status colors','no horizontal overflow at five widths','Claude Desktop terminal destination','Hello Alfred session'], screenshots:['desktop-start','desktop-manual','desktop-connected','desktop-update','mobile-start','mobile-manual','mobile-approval','mobile-update'] };
  fs.writeFileSync(path.join(out,'evidence.json'), JSON.stringify(evidence,null,2));
  console.log(JSON.stringify(evidence,null,2));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
