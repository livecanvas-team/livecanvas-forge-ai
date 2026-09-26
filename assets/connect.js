(function () {
  'use strict';
  const root = document.getElementById('lcfa-connect');
  if (!root || !window.lcfaAdmin) return;
  const $ = selector => root.querySelector(selector);
  const client = $('#lcfa-connect-client');
  const copy = $('[data-connect-copy]');
  const approve = $('[data-connect-approve]');
  const retry = $('[data-connect-retry]');
  const prompt = $('#lcfa-connect-prompt');
  const match = $('[data-connect-match]');
  const labels = window.lcfaConnect || {};
  let ids = {};
  try { ids = JSON.parse(root.dataset.attempts || '{}'); } catch (_) { /* Start a new scoped attempt if metadata is absent. */ }
  if (!ids || Array.isArray(ids) || typeof ids !== 'object') ids = {};
  let attemptId = root.dataset.attempt || ids[client.value] || '';
  let attempt = null, revision = 0, timer, pollingRevision = null, busy = '';
  let problem = null, feedback = '', feedbackValue = '', copied = false, manual = false, failures = 0;
  const saved = new Map();
  const shortcut = /Mac|iPhone|iPad/.test(navigator.platform || '') ? 'Command+C' : 'Ctrl+C';
  const format = (text, value) => (text || '').replace('%s', value);
  function text(selector, value) {
    const node = $(selector);
    if (node.textContent !== (value || '')) node.textContent = value || '';
  }
  function rememberAttempt() {
    const url = new URL(window.location.href);
    if (attemptId) url.searchParams.set('connection_attempt', attemptId);
    else url.searchParams.delete('connection_attempt');
    window.history.replaceState(null, '', url.href);
  }
  async function api(route, body) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(lcfaAdmin.restUrl + route, {
        method: body ? 'POST' : 'GET', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': lcfaAdmin.restNonce },
        ...(body ? { body: JSON.stringify(body) } : {})
      });
      const result = await response.json();
      if (!response.ok || result.ok === false) {
        const error = new Error(result.message || labels.failed);
        error.code = result.code; error.status = response.status; throw error;
      }
      return result;
    } finally { clearTimeout(timeout); }
  }
  function accept(value, expectedId) {
    if (!value || value.client !== client.value || !/^[a-f0-9]{32}$/.test(value.id || '') || (expectedId && value.id !== expectedId)) throw new Error(labels.failed);
    if (!attempt || attempt.pairing_id !== value.pairing_id || attempt.user_code !== value.user_code || value.state !== 'authorization_required') match.checked = false;
    attempt = value; attemptId = value.id; ids[client.value] = value.id;
    problem = null; failures = 0; rememberAttempt(); render();
  }
  function render() {
    const state = attempt?.state || '';
    const reason = attempt?.reason || '';
    const expired = problem?.code === 'lcfa_attempt_not_found';
    const needsNew = expired || ['expired', 'reconnect_required'].includes(state);
    const approval = state === 'authorization_required' && !problem;
    const verified = state === 'ready';
    const verification = state === 'verifying';
    const option = client.selectedOptions[0];
    const logo = option?.dataset.logo || '';
    $('[data-connect-logo]').hidden = !logo;
    if (logo) $('[data-connect-logo]').src = logo;
    let tone = 'amber', title = labels.checking;
    if (state) {
      title = labels[reason] || labels[state] || labels.unavailable;
      if (state === 'ready') tone = 'green';
      if (state === 'waiting_for_client') {
        tone = copied || manual ? 'amber' : 'red';
        title = copied || manual ? labels.waiting_for_client : labels.notConnected;
      }
      if (['access_revoked', 'access_changed', 'session_missing', 'session_mismatch', 'site_changed'].includes(reason)) tone = 'red';
    }
    if (problem) { tone = 'amber'; title = expired ? labels.expired : labels.unavailable; }
    root.dataset.state = expired ? 'expired' : problem ? 'unavailable' : state;
    root.dataset.tone = tone;
    text('[data-connect-status]', title);
    $('[data-connect-approval]').hidden = !approval;
    text('[data-connect-code]', attempt?.user_code || '');
    approve.hidden = !approval;
    approve.disabled = !!busy || !match.checked || !attempt?.pairing_id || !attempt?.user_code;
    retry.hidden = !needsNew && !problem;
    retry.disabled = !!busy;
    retry.textContent = needsNew ? reason === 'runtime_update_required' ? labels.update : labels.restart : labels.retry;
    const visiblePrompt = !approval && !needsNew;
    prompt.hidden = !visiblePrompt;
    $('[data-connect-prompt-label]').hidden = !visiblePrompt;
    copy.hidden = !visiblePrompt;
    const desktopSetup = client.value === 'claude-desktop' && !verified && !verification;
    const value = verified ? labels.testPrompt : verification ? labels.verificationPrompt : desktopSetup ? attempt?.command : attempt?.prompt;
    if (value && feedback && feedbackValue !== value) { feedback = ''; manual = false; copied = false; }
    // Polling must not disturb manual selection, scroll position or clipboard feedback.
    if (prompt.value !== (value || '')) prompt.value = value || '';
    copy.disabled = !!busy || !prompt.value || !!problem;
    text('[data-connect-prompt-label]', verified ? labels.firstTask : verification ? labels.verificationPromptLabel : desktopSetup ? labels.setupCommand : labels.setupPrompt);
    text('[data-connect-copy-label]', busy === 'copy' ? labels.copying : desktopSetup ? labels.copyCommand : labels.copyPrompt);
    const name = option?.textContent || client.value;
    $('[data-connect-instruction]').hidden = !visiblePrompt;
    text('[data-connect-instruction]', verification ? format(labels.restartAgent, name) : desktopSetup ? labels.desktop : format(labels.project, name));
    $('[data-connect-copy-feedback]').hidden = !feedback || !visiblePrompt || !value;
    text('[data-connect-copy-feedback]', feedback);
    $('[data-connect-error]').hidden = !problem;
    text('[data-connect-error]', problem?.message || '');
    text('[data-connect-verified]', attempt?.verified_at || labels.notVerified);
    text('[data-connect-version]', attempt?.package_version || labels.notVerified);
    $('[data-connect-reset]').disabled = !!busy;
  }
  function schedule() {
    clearTimeout(timer);
    if (!attemptId || document.hidden || ['expired', 'reconnect_required'].includes(attempt?.state) || problem?.code === 'lcfa_attempt_not_found') return;
    const delay = problem ? Math.min(60000, 15000 * Math.max(1, failures)) : attempt?.state === 'ready' ? 30000 : 3000;
    timer = setTimeout(poll, delay);
  }
  function failed(error) {
    problem = { code: error.code || '', message: error.name === 'AbortError' ? labels.failed : error.message || labels.failed };
    failures++; render();
  }
  async function poll() {
    if (!attemptId || document.hidden || pollingRevision === revision) return;
    const ownRevision = revision, id = attemptId;
    pollingRevision = ownRevision;
    try {
      const value = await api('connections/attempts/' + encodeURIComponent(id));
      if (ownRevision === revision) accept(value, id);
    } catch (error) { if (ownRevision === revision) failed(error); }
    finally {
      if (pollingRevision === ownRevision) pollingRevision = null;
      if (ownRevision === revision) schedule();
    }
  }
  async function prepare() {
    if (busy) return;
    const ownRevision = revision;
    busy = 'prepare'; render();
    try {
      const value = await api('connections/attempts', { client: client.value });
      if (ownRevision === revision) accept(value);
    } catch (error) { if (ownRevision === revision) failed(error); }
    finally { if (ownRevision === revision) { busy = ''; render(); schedule(); } }
  }
  copy.addEventListener('click', async function () {
    if (busy || !prompt.value || problem) return;
    const ownRevision = revision, value = prompt.value;
    busy = 'copy'; render();
    try {
      if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(value);
      if (ownRevision !== revision || prompt.value !== value) return;
      copied = true; manual = false; feedback = labels.copied; feedbackValue = value;
    } catch (_) {
      if (ownRevision !== revision || prompt.value !== value) return;
      // Local HTTP sites may not expose the Clipboard API. Keep this fallback
      // inside the user's click and copy only the visible, site-bound prompt.
      prompt.focus(); prompt.select(); prompt.scrollTop = 0;
      copied = false;
      try { copied = document.execCommand('copy') === true; } catch (_) { /* Keep manual selection available. */ }
      manual = !copied;
      feedback = copied ? labels.copied : format(labels.copyManually, shortcut); feedbackValue = value;
    } finally {
      if (ownRevision === revision) {
        busy = ''; render();
        if (manual) { prompt.focus(); prompt.select(); prompt.scrollTop = 0; }
        schedule();
      }
    }
  });
  match.addEventListener('change', render);
  approve.addEventListener('click', async function () {
    if (!attempt?.pairing_id || !attempt?.user_code || !match.checked || busy || problem) return;
    const ownRevision = revision, pairingId = attempt.pairing_id;
    busy = 'approve'; render();
    try {
      await api('mcp/pairing/approve', { pairing_id: pairingId });
      if (ownRevision === revision) { match.checked = false; await poll(); }
    } catch (error) { if (ownRevision === revision) failed(error); }
    finally { if (ownRevision === revision) { busy = ''; render(); schedule(); } }
  });
  async function reset() {
    revision++; clearTimeout(timer);
    attempt = null; attemptId = ''; problem = null; feedback = ''; feedbackValue = ''; copied = false; manual = false; busy = ''; match.checked = false; failures = 0;
    delete ids[client.value]; saved.delete(client.value);
    rememberAttempt(); render(); await prepare();
  }
  client.addEventListener('change', async function () {
    if (attempt) saved.set(attempt.client, { copied, manual, feedback, feedbackValue });
    revision++; clearTimeout(timer); busy = ''; attempt = null; problem = null; match.checked = false; failures = 0;
    ({ copied = false, manual = false, feedback = '', feedbackValue = '' } = saved.get(client.value) || {});
    attemptId = ids[client.value] || '';
    rememberAttempt(); render();
    if (attemptId) await poll(); else await prepare();
  });
  retry.addEventListener('click', async function () {
    if (busy) return;
    if (['expired', 'reconnect_required'].includes(attempt?.state) || problem?.code === 'lcfa_attempt_not_found') await reset();
    else if (attemptId) await poll();
    else await prepare();
  });
  $('[data-connect-reset]').addEventListener('click', reset);
  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (!document.hidden && attemptId) poll();
  });
  render();
  if (attemptId) poll(); else prepare();
})();
