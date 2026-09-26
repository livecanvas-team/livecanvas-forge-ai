const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')
const flush = () => new Promise(resolve => setImmediate(resolve))

async function main() {
  const elements = new Map()
  function element(selector) {
    if (!elements.has(selector)) elements.set(selector, { hidden:false, disabled:false, checked:false, textContent:'', value:'', handlers:{}, selected:0, focused:0,
      addEventListener(name, fn) { this.handlers[name] = fn }, focus() { this.focused++ }, select() { this.selected++ } })
    return elements.get(selector)
  }
  const root = { dataset: { attempt:'', attempts:'{}' }, querySelector:element }
  const client = element('#lcfa-connect-client'); client.value = 'cursor'
  Object.defineProperty(client, 'selectedOptions', { get: () => [{ textContent:client.value, dataset:{ logo:client.value + '.svg' } }] })
  const timers = new Map(), calls = [], clipboard = [], attempts = new Map()
  let clock = 0, counter = 0, rejectCopy = false, legacyCopy = false, legacyCalls = 0, networkError = null, holdGet = null, holdCopy = null
  const labels = { notConnected:'Not connected', checking:'Checking', setupPrompt:'Setup prompt', copyPrompt:'Copy prompt', copyCommand:'Copy command', setupCommand:'Setup command', waiting_for_client:'Waiting', authorization_required:'Approve', verifying:'Verifying', ready:'Connected', unavailable:'Unavailable', expired:'Expired', runtime_update_required:'Update required', access_revoked:'Access removed', copied:'Copied!', copyManually:'Press %s', project:'Paste into %s', desktop:'Use Terminal', restartAgent:'Restart %s', failed:'Failed', verificationPrompt:'VERIFY SITE', testPrompt:'READ ONLY', notVerified:'Not verified', firstTask:'First task', retry:'Try again', restart:'Restart', update:'Update', copying:'Copying' }
  const context = {
    URL, location:{href:'https://site.test/wp-admin/admin.php?page=lcfa-dashboard'},
    history:{replaceState(a,b,href) { context.location.href = href }},
    document:{hidden:false, handlers:{}, getElementById:() => root, addEventListener(name,fn) { this.handlers[name] = fn },
      execCommand(command) { assert.equal(command,'copy'); legacyCalls++; if (legacyCopy) clipboard.push(element('#lcfa-connect-prompt').value); return legacyCopy }},
    navigator:{platform:'MacIntel', clipboard:{writeText:async value => { if (holdCopy) await holdCopy; if (rejectCopy) throw new Error('Denied'); clipboard.push(value) }}},
    lcfaAdmin:{restUrl:'https://site.test/wp-json/lcfa/v1/',restNonce:'nonce'}, lcfaConnect:labels,
    AbortController, setTimeout:(fn,delay) => { timers.set(++clock,{fn,delay}); return clock }, clearTimeout:id => timers.delete(id),
    fetch:async (url,options) => {
      calls.push({url,options})
      if (options.method === 'POST' && url.endsWith('/attempts')) {
        const who = JSON.parse(options.body).client, id = (++counter).toString(16).padStart(32,'0')
        const value = {id, client:who, state:'waiting_for_client', prompt:'SETUP ' + who, command:'COMMAND ' + who, package_version:'beta.7'}
        attempts.set(id,value)
        return {ok:true,status:201,json:async () => ({...value})}
      }
      if (url.endsWith('/pairing/approve')) {
        const value = attempts.get(new URL(context.location.href).searchParams.get('connection_attempt'))
        value.state = 'verifying'
        return {ok:true,json:async () => ({ok:true})}
      }
      const value = {...attempts.get(url.split('/').pop())}
      if (holdGet) { const wait = holdGet; holdGet = null; await wait }
      if (networkError) return {ok:false,status:networkError.status,json:async () => networkError}
      return {ok:true,json:async () => value}
    }
  }
  context.window = context
  const button = element('[data-connect-copy]'), approve = element('[data-connect-approve]'), match = element('[data-connect-match]'), feedback = element('[data-connect-copy-feedback]'), prompt = element('#lcfa-connect-prompt')
  const current = () => attempts.get(new URL(context.location.href).searchParams.get('connection_attempt'))
  const tick = async delay => { const entry = [...timers].find(([,t]) => t.delay === delay); assert.ok(entry, 'Expected polling timer ' + delay); timers.delete(entry[0]); await entry[1].fn(); await flush() }
  const change = async value => { client.value = value; await client.handlers.change(); await flush() }
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../assets/connect.js'),'utf8'),context)
  await flush()
  assert.equal(prompt.value,'SETUP cursor','The startup prompt is prepared without a copy click.')
  assert.equal(root.dataset.tone,'red')
  assert.equal(calls[0].options.headers['X-WP-Nonce'],'nonce')
  assert.deepEqual(JSON.parse(calls[0].options.body),{client:'cursor'})
  await button.handlers.click()
  assert.equal(clipboard.at(-1),'SETUP cursor')
  assert.equal(root.dataset.tone,'amber')
  assert.equal(calls.filter(c => c.url.endsWith('/pairing/approve')).length,0)
  assert.equal(legacyCalls,0,'Use the modern clipboard without touching the fallback when it succeeds.')
  rejectCopy = true
  legacyCopy = true
  await button.handlers.click()
  assert.equal(feedback.textContent,'Copied!','A rejected Clipboard API can recover through selected-text copy.')
  assert.equal(clipboard.at(-1),'SETUP cursor')
  const modernClipboard = context.navigator.clipboard
  delete context.navigator.clipboard
  await button.handlers.click()
  assert.equal(feedback.textContent,'Copied!','HTTP pages without a Clipboard API can copy from the button.')
  assert.equal(clipboard.at(-1),'SETUP cursor')
  assert.equal(legacyCalls,2)
  context.navigator.clipboard = modernClipboard
  legacyCopy = false
  await button.handlers.click()
  assert.equal(feedback.textContent,'Press Command+C')
  const selected = prompt.selected
  await tick(3000)
  assert.equal(feedback.textContent,'Press Command+C','Polls must not overwrite clipboard recovery.')
  assert.equal(prompt.selected,selected,'Polls must not reselect the textarea.')
  current().state = 'authorization_required'; current().pairing_id = 'pair1'; current().user_code = 'ABCD-1234'
  await tick(3000)
  assert.equal(approve.disabled,true)
  await approve.handlers.click()
  assert.equal(calls.filter(c => c.url.endsWith('/pairing/approve')).length,0)
  match.checked = true; match.handlers.change()
  await approve.handlers.click()
  assert.equal(root.dataset.state,'verifying')
  assert.equal(root.dataset.tone,'amber','Consent alone is not proof.')
  assert.equal(prompt.value,'VERIFY SITE')
  current().state = 'ready'; current().verified_at = '2026-09-25T12:00:00Z'
  await tick(3000)
  assert.equal(root.dataset.tone,'green')
  assert.equal(prompt.value,'READ ONLY')
  assert.equal(feedback.hidden,true,'Old copy feedback must not apply to a different prompt after verification.')
  await button.handlers.click()
  assert.ok([...timers.values()].some(t => t.delay === 30000),'Keep checking after success.')
  const cursorId = current().id
  await change('claude-desktop')
  assert.equal(prompt.value,'COMMAND claude-desktop')
  assert.equal(element('[data-connect-instruction]').textContent,'Use Terminal')
  const count = counter
  await change('cursor')
  assert.equal(current().id,cursorId)
  assert.equal(counter,count,'Returning to an agent must reuse its exact attempt.')
  networkError = {status:503,message:'Offline'}
  await tick(30000)
  assert.equal(root.dataset.tone,'amber')
  assert.equal(button.disabled,true)
  assert.equal(feedback.textContent,'Press Command+C')
  networkError = null
  await element('[data-connect-retry]').handlers.click()
  assert.equal(root.dataset.tone,'green')
  assert.equal(counter,count,'Network recovery must not create a new session.')
  current().state = 'reconnect_required'; current().reason = 'runtime_update_required'
  await tick(30000)
  assert.equal(element('[data-connect-status]').textContent,'Update required')
  assert.equal(root.dataset.tone,'amber')
  assert.equal(prompt.hidden,true,'An old installer descriptor must not be offered after an update.')
  await element('[data-connect-retry]').handlers.click()
  assert.notEqual(current().id,cursorId)
  assert.equal(root.dataset.tone,'red')
  current().state = 'ready'; delete current().reason
  await tick(3000)
  current().state = 'reconnect_required'; current().reason = 'access_revoked'
  await tick(30000)
  assert.equal(root.dataset.tone,'red')
  assert.equal(element('[data-connect-status]').textContent,'Access removed')
  await element('[data-connect-reset]').handlers.click()
  let releaseGet
  holdGet = new Promise(resolve => { releaseGet = resolve })
  const oldPoll = tick(3000)
  await change('codex')
  releaseGet(); await oldPoll
  assert.equal(prompt.value,'SETUP codex','A previous agent response cannot replace the selected agent.')
  let releaseCopy
  holdCopy = new Promise(resolve => { releaseCopy = resolve }); rejectCopy = false
  const copyInFlight = button.handlers.click()
  await change('opencode')
  releaseCopy(); await copyInFlight; holdCopy = null
  assert.equal(feedback.hidden,true,'A late clipboard result cannot affect a different client.')
  assert.equal(button.disabled,false)
  const legacyBeforeStale = legacyCalls
  holdCopy = new Promise(resolve => { releaseCopy = resolve }); rejectCopy = true
  const staleCopy = button.handlers.click()
  current().state = 'verifying'
  await tick(3000)
  releaseCopy(); await staleCopy; holdCopy = null
  assert.equal(prompt.value,'VERIFY SITE')
  assert.equal(legacyCalls,legacyBeforeStale,'A rejected stale copy must not copy a replacement prompt.')
  assert.equal(feedback.hidden,true)
  current().state = 'waiting_for_client'
  await tick(3000)
  networkError = {code:'lcfa_attempt_not_found',status:404,message:'Expired'}
  await tick(3000)
  assert.equal(root.dataset.state,'expired')
  assert.equal(prompt.hidden,true)
  networkError = null
  await element('[data-connect-retry]').handlers.click()
  assert.equal(root.dataset.state,'waiting_for_client')
  context.document.hidden = true
  context.document.handlers.visibilitychange()
  assert.equal([...timers.values()].filter(t => t.delay !== 20000).length,0)
  context.document.hidden = false
  context.document.handlers.visibilitychange()
  await flush()
  assert.equal(root.dataset.tone,'red')
  console.log('PASS: prompt-first UI, separate clipboard feedback, consent gating, runtime update/revocation, resumed clients, live monitoring and stale async response guards')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
