const assert = require('node:assert/strict')
const createGuard = require('../../assets/editor-buffer')
function fixture() {
  const listeners = {}; let editing = false; let codeOpen = false; let saving = false; const requests = []
  const makeDoc = value => ({ querySelector: selector => selector === 'html' ? { innerHTML: value, outerHTML: '<html>' + value + '</html>' } : selector === 'main#lc-main' ? {} : null })
  const frame = { contentDocument: { addEventListener() {}, querySelector: () => editing ? {} : null }, srcdoc: 'original preview' }
  const document = { addEventListener: (type, fn) => { listeners[type] = fn }, activeElement: null,
    getElementById: id => id === 'previewiframe' ? frame : id === 'toggle-code-editor' ? { classList: { contains: () => codeOpen } } : null,
    querySelector: () => saving ? {} : null }
  const shell = { contains: target => target === shell }
  const win = { doc: makeDoc('original'), original_document_html: 'original', lc_editor_current_post_id: 42,
    lc_editor_url_to_load: 'http://fixture.test/?page_id=42&lc_page_editing_mode=1', location: { origin: 'http://fixture.test', href: 'http://fixture.test/editor' },
    getPageHTML: () => win.doc.querySelector('html').innerHTML, parseFromComplexString: makeDoc, filterPreviewHTML: html => html,
    tryToEnrichPreview() {}, saveHistoryStep() {}, lcMainStore: { setDoc(doc) { this.doc = doc } },
    fetch: async (url, options) => { requests.push({ url, options }); return { ok: true, text: async () => 'saved result' } } }
  return { guard: createGuard(win, document, { postId: 42 }, shell), win, document, frame, requests, listeners, shell, makeDoc,
    edit: value => { win.doc = makeDoc(value) }, code: value => { codeOpen = value }, rich: value => { editing = value }, saving: value => { saving = value } }
}
async function main() {
  const f = fixture(); const baseline = f.guard.read()
  assert.equal(baseline.state, 'clean')
  f.code(true); assert.equal(f.guard.read().state, 'editing'); f.code(false)
  f.rich(true); assert.equal(f.guard.read().state, 'editing'); f.rich(false)
  f.saving(true); assert.equal(f.guard.read().state, 'saving'); f.saving(false)
  f.document.activeElement = { tagName: 'INPUT' }; assert.equal(f.guard.read().state, 'editing'); f.document.activeElement = null
  f.win.lc_editor_current_post_id = 99; assert.equal(f.guard.read().state, 'target_changed'); f.win.lc_editor_current_post_id = 42
  f.listeners.input({ target: f.shell }); assert.equal(f.guard.unchanged(baseline), true, 'Typing a prompt must not dirty the page')
  f.edit('local HTML, CSS or JavaScript'); assert.equal(f.guard.read().state, 'dirty')
  assert.equal((await f.guard.refresh(baseline)).refreshed, false); assert.equal(f.requests.length, 0)
  assert.equal(f.win.getPageHTML(), 'local HTML, CSS or JavaScript')

  const race = fixture(); let respond
  race.win.fetch = () => new Promise(resolve => { respond = resolve })
  const pending = race.guard.refresh(race.guard.read())
  race.edit('local edit during fetch')
  respond({ ok: true, text: async () => 'server result' })
  assert.deepEqual(await pending, { refreshed: false, reason: 'editor_changed' })
  assert.equal(race.win.getPageHTML(), 'local edit during fetch'); assert.equal(race.frame.srcdoc, 'original preview')

  const inputRace = fixture(); let deliver
  inputRace.win.fetch = () => new Promise(resolve => { deliver = resolve })
  const inputPending = inputRace.guard.refresh(inputRace.guard.read())
  inputRace.listeners.input({ target: {} }) // An editor buffer has not yet flushed to the document.
  deliver({ ok: true, text: async () => 'server result' })
  assert.equal((await inputPending).refreshed, false)
  assert.equal(inputRace.win.getPageHTML(), 'original')

  const clean = fixture()
  assert.equal((await clean.guard.refresh()).refreshed, false, 'Recovered work has no original in-memory baseline')
  assert.deepEqual(await clean.guard.refresh(clean.guard.read()), { refreshed: true })
  assert.equal(clean.win.getPageHTML(), 'saved result'); assert.equal(clean.win.original_document_html, 'saved result')
  assert.equal(clean.win.lcMainStore.doc, clean.win.doc)
  assert.match(clean.requests[0].url, /lcfa_refresh=/)
  assert.equal(clean.requests[0].options.redirect, 'error')
  assert.equal(clean.requests[0].options.credentials, 'same-origin')
  const unknown = fixture(); delete unknown.win.original_document_html
  assert.equal(unknown.guard.read().state, 'unknown')
  const throttled = fixture(); let aceChange; let now = 1000; const realNow = Date.now
  const session = { on: (event, fn) => { aceChange = fn } }
  throttled.win.lc_html_editor = { getSession: () => session }
  try {
    Date.now = () => now
    assert.equal(throttled.guard.read().state, 'editing', 'Late Ace binding must wait for a pending native edit')
    now += 251; assert.equal(throttled.guard.read().state, 'clean', 'A settled unchanged Ace buffer must not block forever')
    aceChange(); assert.equal(throttled.guard.read().state, 'editing', 'Closing the code panel must not hide a pending HTML flush')
    now += 251; assert.equal(throttled.guard.read().state, 'clean')
  } finally { Date.now = realNow }
  console.log('PASS editor buffer identity, unflushed inputs, local edits, network races and guarded native refresh')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
