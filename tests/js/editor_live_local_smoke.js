const assert = require('assert');
const { execFileSync } = require('child_process');

const loginUrl = process.env.LCFA_LOGIN_URL || 'http://localhost:8887/studio-auto-login?redirect_to=http%3A%2F%2Flocalhost%3A8887%2Fwp-admin%2F';
const editorUrl = process.env.LCFA_EDITOR_URL || 'http://localhost:8887/?page_id=137&lc_action_launch_editing=1&from_url=%2Fwp-admin%2Fedit.php%3Fpost_type%3Dpage&from_page_edit=1';
const prompt = process.env.LCFA_EDITOR_PROMPT || 'fammi una hero più chiara per questa pagina';
const artifactsDir = '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/artifacts';

function run(command, args = []) {
  return execFileSync(command, args, { encoding: 'utf8' }).trim();
}

function runAppleScript(source) {
  return run('osascript', ['-e', source]);
}

function sleep(seconds) {
  execFileSync('sleep', [String(seconds)]);
}

function chromeJs(source) {
  const script = [
    'tell application "Google Chrome"',
    'tell active tab of front window',
    `execute javascript ${JSON.stringify(source)}`,
    'end tell',
    'end tell',
  ].join('\n');

  return runAppleScript(script);
}

function openChromeUrl(url) {
  const script = [
    'tell application "Google Chrome"',
    'activate',
    'if (count of windows) = 0 then make new window',
    `set URL of active tab of front window to ${JSON.stringify(url)}`,
    'end tell',
  ].join('\n');

  return runAppleScript(script);
}

function screenshot(filename) {
  run('screencapture', ['-x', `${artifactsDir}/${filename}`]);
}

function waitForState(states, maxSeconds) {
  const expected = new Set(states);

  for (let index = 0; index < maxSeconds; index += 1) {
    const state = chromeJs(
      "(function(){ var node=document.querySelector('[data-lcfa-editor-status]'); return node ? node.getAttribute('data-state') : ''; })()"
    );

    if (expected.has(state)) {
      return state;
    }

    sleep(1);
  }

  throw new Error(`Timed out waiting for states: ${Array.from(expected).join(', ')}`);
}

openChromeUrl(loginUrl);
sleep(4);

openChromeUrl(editorUrl);
sleep(8);

assert.strictEqual(chromeJs("document.title"), 'Consultala LiveCanvas Editor');
assert.strictEqual(chromeJs("String(Boolean(document.querySelector('[data-lcfa-editor-open]')))"), 'true');

chromeJs("document.querySelector('[data-lcfa-editor-open]').click(); 'clicked'");
sleep(1);

const drawerState = JSON.parse(
  chromeJs(
    "JSON.stringify({open: document.querySelector('[data-lcfa-editor-shell]').classList.contains('is-open'), hidden: document.querySelector('.lcfa-editor-drawer').getAttribute('aria-hidden')})"
  )
);

assert.strictEqual(drawerState.open, true);
assert.strictEqual(drawerState.hidden, 'false');

screenshot('editor-live-opened.png');

chromeJs(
  `(function(){ const input=document.querySelector('[data-lcfa-editor-prompt]'); input.value=${JSON.stringify(prompt)}; input.dispatchEvent(new Event('input',{bubbles:true})); input.dispatchEvent(new Event('change',{bubbles:true})); document.querySelector('[data-lcfa-editor-analyze]').click(); return 'analyze-clicked'; })()`
);

const suggestedState = waitForState(['suggested', 'previewed', 'failed', 'applied'], 30);
assert.notStrictEqual(suggestedState, 'failed');

const previewButton = JSON.parse(
  chromeJs(
    "JSON.stringify((function(){ var b=document.querySelector('[data-lcfa-editor-preview]'); return {hidden: b.hidden, text: b.textContent.trim()}; })())"
  )
);

assert.strictEqual(previewButton.hidden, false);
assert.ok(previewButton.text.toLowerCase().includes('preview'));

screenshot('editor-live-suggested.png');

chromeJs("document.querySelector('[data-lcfa-editor-preview]').click(); 'preview-clicked'");

const previewedState = waitForState(['previewed', 'applied', 'failed'], 45);
assert.notStrictEqual(previewedState, 'failed');

const resultSummary = chromeJs(
  "(function(){ var s=document.querySelector('[data-lcfa-editor-result-summary]'); return s ? s.textContent.replace(/\\s+/g,' ').trim() : ''; })()"
);
const proposedMarkup = chromeJs(
  "(function(){ var p=document.querySelector('[data-lcfa-editor-result-proposed]'); var text=p ? p.textContent.replace(/\\s+/g,' ').trim() : ''; return text.slice(0, 700); })()"
);
const supportFlags = JSON.parse(
  chromeJs(
    "JSON.stringify((function(){ var d=document.querySelector('[data-lcfa-editor-result-diff-wrap]'); var p=document.querySelector('[data-lcfa-editor-result-proposed-wrap]'); var e=document.querySelector('[data-lcfa-editor-result-existing-wrap]'); return {diff: !!(d && !d.hidden), proposed: !!(p && !p.hidden), existing: !!(e && !e.hidden)}; })())"
  )
);

assert.strictEqual(resultSummary, 'Update page #137.');
assert.ok(/lcfa-section--hero|Forge AI starter|hero/i.test(proposedMarkup));
assert.strictEqual(supportFlags.diff, true);
assert.strictEqual(supportFlags.proposed, true);
assert.strictEqual(supportFlags.existing, true);

screenshot('editor-live-previewed.png');

console.log('PASS');
