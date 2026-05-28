const fs = require('fs');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/studio-app.js',
  'utf8'
);
const admin = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php',
  'utf8'
);

const scriptTokens = [
  'wp.element',
  'wp.apiFetch',
  'lcfaStudio',
  'data-lcfa-studio-app-root',
  'fetchStudioState',
  'setFallbackHidden(true)',
  'setFallbackHidden(false)',
  'STORAGE_PREFIX',
  'useStoredState',
  'clearStoredViewPreferences',
  'AlertsPanel',
  'ContractPanel',
  'HandoffReadinessPanel',
  'OperatorBriefingPanel',
  'AgentSmokeTestsPanel',
  'AgentRunbookPanel',
  'AgentHandoffPackagePanel',
  'RunHealthPanel',
  'AbilityManifestPanel',
  'AbilityExplorer',
  'WritePolicy',
  'InspectorPanel',
  'RunsExplorer',
  'runKey',
  'commandUrl',
  'sortAbilities',
  'sortRuns',
  'toggleColumn',
  'Sort abilities',
  'Sort runs',
  'data-lcfa-studio-column',
  'Copy name',
  'Copy audit',
  'Copy ability JSON',
  'Copy run JSON',
  'data-lcfa-studio-inspector',
  'Open Deck',
  'Refresh',
  'Reset view',
  'Copy Studio state',
  'Copy contract',
  'Copy readiness',
  'data-lcfa-studio-contract',
  'data-lcfa-studio-handoff-readiness',
  'Studio contract',
  'Handoff readiness',
  'Copy agent prompt',
  'Copy operator briefing',
  'Copy smoke tests',
  'Copy test payload',
  'Copy payload JSON',
  'Copy runbook markdown',
  'Copy agent runbook',
  'Copy handoff package',
  'Copy package manifest',
  'Copy package endpoint',
  'Copy file',
  'Copy checksum',
  'data-lcfa-studio-agent-smoke-tests',
  'data-lcfa-studio-agent-runbook',
  'data-lcfa-studio-handoff-package',
  'data-lcfa-studio-operator-briefing',
  'agent_smoke_tests',
  'agent_runbook',
  'agent_handoff_package',
  'handoff_package_route',
  'contract',
  'handoff_readiness',
  'operator_briefing',
  'Write guards',
  'Markdown handoff',
  'Agent handoff package',
  'Next actions',
  'Copy diagnostics',
  'Copy run analysis',
  'Copy ability manifest',
  'data-lcfa-studio-ability-manifest',
  'ability_manifest',
  'Input properties',
  'data-lcfa-studio-timeline',
  'run_analysis',
  'Action mix',
  'Origin mix',
  'data-lcfa-studio-alert',
  'generated_at',
  'createRoot',
];

for (const token of scriptTokens) {
  if (!script.includes(token)) {
    console.error(`studio-app.js should include ${token}`);
    process.exit(1);
  }
}

const adminTokens = [
  "'lcfa-studio-app'",
  "LCFA_URL . 'assets/studio-app.js'",
  "'wp-element'",
  "'wp-api-fetch'",
  "wp_localize_script('lcfa-studio-app'",
  'data-lcfa-studio-app-root',
  'data-lcfa-studio-fallback',
];

for (const token of adminTokens) {
  if (!admin.includes(token)) {
    console.error(`admin should enqueue/localize Studio React shell token ${token}`);
    process.exit(1);
  }
}

console.log('PASS');
