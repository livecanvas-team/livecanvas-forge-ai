const fs = require('fs')
const { repoPath } = require('./test-paths.cjs')

const script = fs.readFileSync(
  repoPath('assets', 'admin.js'),
  'utf8'
)

const requiredTokens = [
  'updateFrameworkSubmit',
  'bootstrapFrameworkForms',
  'updateSetupProfileFramework',
  'bootstrapSetupProfileForms',
  '[data-lcfa-framework-form]',
  '[data-lcfa-framework-submit]',
  '[data-lcfa-framework-submit-label]',
  '[data-lcfa-framework-submit-note]',
  '[data-lcfa-setup-profile-form]',
  '[data-lcfa-profile-framework]',
  '[data-lcfa-profile-framework-help]',
  "submit.disabled = !selected",
  "submit.setAttribute('aria-busy', 'true')",
  "selected.getAttribute('data-lcfa-framework-progress')"
]

for (const token of requiredTokens) {
  if (!script.includes(token)) {
    console.error(`admin.js should include framework setup support for ${token}`)
    process.exit(1)
  }
}

console.log('PASS admin_framework_setup_phase1')
