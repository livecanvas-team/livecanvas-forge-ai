(function () {
  'use strict';
  const root = document.getElementById('lcfa-connect');
  if (!root || !window.lcfaAdmin) return;
  const client = root.querySelector('#lcfa-connect-client');
  const copy = root.querySelector('[data-connect-copy]');
  const approve = root.querySelector('[data-connect-approve]');
  const retry = root.querySelector('[data-connect-retry]');
  const status = root.querySelector('[data-connect-status]');
  const code = root.querySelector('[data-connect-code]');
  const prompt = root.querySelector('#lcfa-connect-prompt');
  const instruction = root.querySelector('[data-connect-instruction]');
  const labels = window.lcfaConnect || {};
  let attempt = null;
  let attemptId = root.dataset.attempt;
  let revision = 0;
  let timer;
  let pending = false;
  let busy = false;

  function message(text) { if (status.textContent !== text) status.textContent = text; }
  function rememberAttempt(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('connection_attempt', id);
    else url.searchParams.delete('connection_attempt');
    window.history.replaceState(null, '', url.href);
  }
  function updateInstruction() {
    instruction.textContent = client.value === 'claude-desktop' ? labels.desktop : labels.project;
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
      if (!response.ok || result.ok === false) throw new Error(result.message || labels.failed);
      return result;
    } finally { clearTimeout(timeout); }
  }
  function render(value) {
    attempt = value;
    attemptId = value.id;
    rememberAttempt(attemptId);
    prompt.value = client.value === 'claude-desktop' ? value.command || '' : value.prompt || '';
    root.dataset.state = value.state;
    approve.hidden = value.state !== 'authorization_required';
    copy.hidden = value.state === 'ready' || !approve.hidden;
    retry.hidden = !['expired', 'reconnect_required'].includes(value.state);
    code.hidden = !value.user_code || approve.hidden;
    code.textContent = value.user_code ? `${labels.code}: ${value.user_code}` : '';
    message(labels[value.state] || labels.waiting_for_client);
  }
  function schedule() {
    clearTimeout(timer);
    if (attemptId && !document.hidden && !['ready', 'expired', 'reconnect_required'].includes(attempt?.state)) timer = setTimeout(poll, 3000);
  }
  async function poll() {
    if (!attemptId || document.hidden || pending) return;
    pending = true;
    const ownRevision = revision;
    try {
      const value = await api(`connections/attempts/${encodeURIComponent(attemptId)}`);
      if (ownRevision === revision) render(value);
    } catch (error) {
      if (ownRevision === revision) { message(error.message); retry.hidden = false; }
      // Pause after a transport/authentication error; a user action can resume safely.
      return;
    } finally { pending = false; }
    schedule();
  }
  copy.addEventListener('click', async function () {
    if (busy) return;
    busy = true; copy.disabled = true; client.disabled = true;
    const ownRevision = revision;
    try {
      if (!attempt || ['expired', 'reconnect_required'].includes(attempt.state)) render(await api('connections/attempts', { client: client.value }));
      if (ownRevision !== revision) return;
      try {
        if (!navigator.clipboard) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(prompt.value);
        message(labels.copied);
      } catch (_) {
        root.querySelector('[data-connect-details]').open = true;
        prompt.focus(); prompt.select(); message(labels.copyManually);
      }
      schedule();
    } catch (error) { message(error.message); retry.hidden = false; }
    finally { busy = false; copy.disabled = false; client.disabled = false; }
  });
  approve.addEventListener('click', async function () {
    if (!attempt?.pairing_id || busy) return;
    busy = true; approve.disabled = true;
    try { await api('mcp/pairing/approve', { pairing_id: attempt.pairing_id }); await poll(); }
    catch (error) { message(error.message); }
    finally { busy = false; approve.disabled = false; }
  });
  function reset() {
    revision++; clearTimeout(timer); attempt = null; attemptId = ''; prompt.value = '';
    rememberAttempt('');
    copy.hidden = false; approve.hidden = true; code.hidden = true; retry.hidden = true;
    root.dataset.state = ''; message(''); updateInstruction();
  }
  client.addEventListener('change', reset);
  retry.addEventListener('click', function () { reset(); copy.focus(); });
  root.querySelector('[data-connect-reset]').addEventListener('click', function () { reset(); copy.focus(); });
  document.addEventListener('visibilitychange', function () {
    clearTimeout(timer);
    if (!document.hidden) poll();
  });
  updateInstruction();
  if (attemptId) poll();
})();
