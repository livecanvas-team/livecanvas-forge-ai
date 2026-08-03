const fs = require('fs')
const { repoPath } = require('./test-paths.cjs')

const script = fs.readFileSync(
  repoPath('assets', 'admin.js'),
  'utf8'
)

const requiredTokens = [
  'getActiveThemeLibraryFilter',
  'setActiveThemeLibraryFilter',
  'applyThemeLibraryFilters',
  'bootstrapThemeLibraryFilters',
  '[data-lcfa-theme-framework-filter]',
  '[data-lcfa-theme-category-filter]',
  '[data-lcfa-theme-card]',
  '[data-lcfa-theme-results]',
  '[data-lcfa-theme-empty]',
  "candidate.setAttribute('aria-pressed', active ? 'true' : 'false')",
  "var frameworkMatches = framework === 'all' || cardFramework === framework",
  "var categoryMatches = category === 'all' || cardCategory === category",
  'card.hidden = !matches'
]

for (const token of requiredTokens) {
  if (!script.includes(token)) {
    console.error(`admin.js should include Theme Library filtering support for ${token}`)
    process.exit(1)
  }
}

console.log('PASS')
