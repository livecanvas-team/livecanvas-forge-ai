const fs = require('fs');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js',
  'utf8'
);

if (!script.includes('data-lcfa-mode-switch')) {
  console.error('admin.js should bootstrap Codex mode switch forms');
  process.exit(1);
}

if (!script.includes('data-lcfa-current-mode')) {
  console.error('admin.js should compare the selected mode with the current mode');
  process.exit(1);
}

if (!script.includes('data-lcfa-mode-switch-submit')) {
  console.error('admin.js should manage the Codex mode switch submit button');
  process.exit(1);
}

if (!script.includes('submit.disabled = !hasChanged')) {
  console.error('mode switch submit should stay disabled until the radio selection changes');
  process.exit(1);
}

if (!script.includes('bootstrapModeSwitches')) {
  console.error('admin.js should initialize mode switches on page load');
  process.exit(1);
}

console.log('PASS admin_mode_switch_phase1');
