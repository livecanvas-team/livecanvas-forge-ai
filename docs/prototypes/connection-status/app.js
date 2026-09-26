(function () {
  'use strict';
  const agents = {
    codex: { name: 'Codex', logo: 'codex-color.svg', destination: 'your Codex project chat' },
    opencode: { name: 'OpenCode', logo: 'opencode.svg', destination: 'your OpenCode project chat' },
    cursor: { name: 'Cursor', logo: 'cursor.svg', destination: 'your Cursor project chat' },
    'claude-code': { name: 'Claude Code', logo: 'claude-color.svg', destination: 'your Claude Code session', session: 'Hello Alfred' },
    'claude-desktop': { name: 'Claude Desktop', logo: 'claude-color.svg', destination: 'Terminal or PowerShell', desktop: true }
  };
  const states = {
    start: { tone: 'red', title: 'Not connected', kind: 'setup', evidence: 'No verified connection.' },
    manual: { tone: 'amber', title: 'Copy prompt to continue', kind: 'setup', evidence: 'No verified connection.', demo: 'Simulate agent request', next: 'approval' },
    waiting: { tone: 'amber', title: 'Waiting for your agent', kind: 'setup', evidence: 'Waiting for a setup request.', demo: 'Simulate agent request', next: 'approval' },
    approval: { tone: 'amber', title: 'Approve connection', evidence: 'Setup request received. Access not granted.' },
    verifying: { tone: 'amber', title: 'Waiting for verification', kind: 'verify', evidence: 'Approved. Authenticated reply pending.', demo: 'Simulate verified reply', next: 'connected' },
    connected: { tone: 'green', title: 'Connected · verified just now', kind: 'test', evidence: 'Authenticated check: just now (demo). Not continuous online status.' },
    update: { tone: 'amber', title: 'Connection update required', kind: 'setup', evidence: 'Previously verified. Required runtime capability is missing (demo).' },
    optional: { tone: 'green', title: 'Connected · update is optional', kind: 'test', evidence: 'Authenticated check: just now (demo). Current runtime remains compatible.' },
    stale: { tone: 'amber', title: 'Connection needs a check', kind: 'verify', evidence: 'Last verified 2 days ago (example). Current reachability unknown.', demo: 'Simulate verified reply', next: 'connected' },
    failed: { tone: 'red', title: 'Connection check failed', kind: 'verify', evidence: 'WordPress did not respond to the attempted check (demo).' },
    revoked: { tone: 'red', title: 'Access removed', evidence: 'Session revoked (demo). New approval required.' },
    expired: { tone: 'amber', title: 'Setup expired', evidence: 'Setup attempt expired. Existing sessions are unchanged.' }
  };
  const $ = id => document.getElementById(id);
  const perAgent = {};
  let selectedAgent = 'codex', state = 'start', feedback = '', codeMatched = false, busy = false, revision = 0;
  const shortcut = () => /Mac|iPhone|iPad/.test(navigator.platform) ? 'Command+C' : 'Ctrl+C';

  function promptText(kind) {
    const agent = agents[selectedAgent];
    if (kind === 'test') return 'PREVIEW ONLY. Inspect example.local with AI Bridge. Confirm the active theme, framework and rendering rules. Get the write context before proposing changes. Do not edit or publish anything.';
    if (kind === 'verify') return 'PREVIEW ONLY. Verify the AI Bridge connection to example.local with get_connection_handoff. Confirm site identity and runtime compatibility. Report any failed check. Do not change the site.';
    if (agent.desktop) return '# PREVIEW ONLY\n# Your site-specific setup command will appear here.\n# No installer or token is included in this sample.';
    return 'PREVIEW ONLY. Connect ' + agent.name + (agent.session ? ' (Hello Alfred)' : '') + ' to example.local with LiveCanvas AI Bridge. Verify the site and connection before making changes.';
  }

  function render() {
    const p = states[state], agent = agents[selectedAgent];
    $('agent-logo').src = 'logos/' + agent.logo;
    const approval = state === 'approval', recovery = ['revoked', 'expired'].includes(state);
    $('connection').dataset.state = state;
    $('connection').dataset.tone = p.tone;
    $('connection').style.setProperty('--state-color', 'var(--' + p.tone + ')');
    $('status-title').textContent = p.title;
    $('approval-panel').hidden = !approval;
    $('code-match').checked = codeMatched;
    $('cancel').hidden = !approval;
    $('prompt').hidden = !p.kind;
    $('prompt-label').hidden = !p.kind;
    $('prompt-label').textContent = p.kind === 'test' ? 'First task' : p.kind === 'verify' ? 'Verification prompt' : state === 'update' ? 'Update prompt' : agent.desktop ? 'Setup command' : 'Setup prompt';
    $('prompt').value = p.kind ? promptText(p.kind) : '';
    $('primary-label').textContent = busy ? 'Copying…' : approval ? 'Approve Full Access' : recovery ? 'Start setup again' : agent.desktop && p.kind === 'setup' ? 'Copy command' : 'Copy prompt';
    $('copy-icon').toggleAttribute('hidden', !p.kind);
    $('primary').disabled = busy || (approval && !codeMatched);
    $('destination').hidden = approval || recovery;
    const destination = agent.desktop && p.kind !== 'setup' ? 'your Claude Desktop chat' : agent.destination;
    $('destination').textContent = state === 'failed' ? 'Start your local site, then paste into ' + destination + '.' : p.kind === 'verify' && state === 'verifying' ? 'Restart ' + agent.name + ', then paste into ' + destination + '.' : 'Paste into ' + destination + '.';
    $('copy-feedback').hidden = !feedback;
    $('copy-feedback').textContent = feedback;
    $('scenario').value = state;
    $('demo-event').hidden = !p.demo;
    $('demo-event').textContent = p.demo || '';
    const rows = [['Site', 'example.local (sample)'], ['Agent', agent.name], ...(agent.session ? [['Session', agent.session]] : []), ['Verification', p.evidence], ['Bridge', '0.2.0-beta.6'], ['Agent runtime', state === 'update' ? '0.2.0-beta.5; requires beta.7' : p.tone === 'green' ? '0.2.0-beta.7 (demo)' : 'Not verified']];
    $('diagnostics').replaceChildren(...rows.flatMap(([key, value]) => { const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = key; dd.textContent = value; return [dt, dd]; }));
    $('help-text').textContent = state === 'update' ? 'Update the connection before editing. A compatible plugin update does not require setup again.' : 'A verified connection requires a reply from this agent for this site. Copying a prompt or approving access does not verify it.';
    $('announcer').textContent = p.title;
    perAgent[selectedAgent] = { state, feedback, codeMatched };
  }
  function transition(next) {
    revision++; state = next; codeMatched = false; busy = false;
    feedback = next === 'manual' ? 'Copy unavailable. Select the prompt and press ' + shortcut() + '.' : '';
    render();
  }
  async function copyPrompt() {
    if (busy) return;
    const p = states[state];
    if (!p.kind) return;
    busy = true; const currentRevision = revision; const agent = selectedAgent;
    const text = $('prompt').value;
    render();
    let copied = false;
    try { if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); copied = true; } } catch (_) { /* Selected-text fallback works on HTTP and permission denial. */ }
    if (revision !== currentRevision || agent !== selectedAgent) return;
    busy = false;
    if (p.kind === 'setup') state = copied ? 'waiting' : 'manual';
    feedback = copied ? 'Copied!' : 'Copy unavailable. Press ' + shortcut() + ' to copy the selected prompt.';
    render();
    if (!copied) { $('prompt').focus(); $('prompt').select(); }
  }
  $('primary').addEventListener('click', () => {
    if (state === 'approval') { if (codeMatched) { transition('verifying'); $('primary').focus(); } return; }
    if (['revoked', 'expired'].includes(state)) { transition('start'); $('primary').focus(); return; }
    copyPrompt();
  });
  $('code-match').addEventListener('change', e => { codeMatched = e.target.checked; render(); });
  $('cancel').addEventListener('click', () => { transition('start'); $('primary').focus(); });
  $('restart').addEventListener('click', () => { transition('start'); $('connection-details').open = false; $('primary').focus(); });
  $('demo-event').addEventListener('click', () => { if (states[state].next) transition(states[state].next); });
  $('scenario').addEventListener('change', e => transition(e.target.value));
  $('agent').addEventListener('change', e => {
    revision++; selectedAgent = e.target.value;
    ({ state, feedback, codeMatched } = perAgent[selectedAgent] || { state: 'start', feedback: '', codeMatched: false });
    busy = false; render();
  });
  render();
})();
