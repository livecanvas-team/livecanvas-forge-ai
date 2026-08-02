const path = require('node:path')

const repoRoot = path.resolve(__dirname, '..', '..')

function repoPath(...segments) {
  return path.join(repoRoot, ...segments)
}

module.exports = {
  repoPath,
  repoRoot
}
