const fs = require('fs');

const script = fs.readFileSync(
  '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js',
  'utf8'
);

if (!script.includes('data-lcfa-read-more')) {
  console.error('admin.js should bootstrap generated-bundle read-more descriptions');
  process.exit(1);
}

if (!script.includes('data-lcfa-read-more-toggle')) {
  console.error('admin.js should handle generated-bundle read-more toggle buttons');
  process.exit(1);
}

if (!script.includes('is-collapsed')) {
  console.error('admin.js should collapse long generated-bundle explanations by default');
  process.exit(1);
}

console.log('PASS');
