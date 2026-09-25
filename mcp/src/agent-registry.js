const catalog = require('./agent-catalog.json')

const aliases = { other: 'generic', 'codex-cli': 'codex', 'codex-app': 'codex', 'copilot-cli': 'github-copilot', roo: 'roo-code', kilo: 'kilo-code' }

function normalizeAgent(value, fallback = 'generic') {
  const name = String(value || '').trim().toLowerCase()
  const id = aliases[name] || name
  return Object.hasOwn(catalog, id) ? id : fallback
}

function getAgent(value) {
  const id = normalizeAgent(value)
  return { id, ...catalog[id] }
}

module.exports = { catalog, normalizeAgent, getAgent }
