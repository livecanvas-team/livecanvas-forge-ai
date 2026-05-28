const fs = require('fs');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js',
  'utf8'
);

const requiredTokens = [
  'data-lcfa-studio-root',
  'data-lcfa-studio-ability-search',
  'data-lcfa-studio-ability-filter',
  'data-lcfa-studio-ability-item',
  'data-lcfa-studio-run-search',
  'data-lcfa-studio-run-filter',
  'data-lcfa-studio-run-item',
  'applyStudioAbilityFilters',
  'applyStudioRunFilters',
  'bootstrapStudioRoots',
  'data-lcfa-studio-ability-empty',
  'data-lcfa-studio-run-empty',
];

for (const token of requiredTokens) {
  if (!script.includes(token)) {
    console.error(`admin.js should include Forge Studio filter support for ${token}`);
    process.exit(1);
  }
}

console.log('PASS');
