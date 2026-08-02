const fs = require('fs')
const { repoPath } = require('./test-paths.cjs')

const script = fs.readFileSync(
  repoPath('assets', 'admin.js'),
  'utf8'
)

const requiredTokens = [
  'applyThemeLibraryFilter',
  'bootstrapThemeLibraryFilters',
  '[data-lcfa-theme-filter]',
  '[data-lcfa-theme-card]',
  "button.setAttribute('aria-pressed', active ? 'true' : 'false')",
  "card.hidden = filter !== 'all' && category !== filter"
]

for (const token of requiredTokens) {
  if (!script.includes(token)) {
    console.error(`admin.js should include Theme Library filtering support for ${token}`)
    process.exit(1)
  }
}

console.log('PASS')
