// Local compiler fixture, not a coding agent. Reads only the authorized theme.
const fs = require('node:fs')
const { PicostrapCompiler } = require('../../mcp/src/picostrap-compiler')
const root = process.argv[2]
if (root !== '/Users/commander/Local Sites/test-ai-forge/app/public') throw new Error('Test AI Forge root required')
const manifest = JSON.parse(fs.readFileSync(0, 'utf8'))
new PicostrapCompiler({ client: {}, config: { wpRoot: root }, themeFiles: {} })
  .compileBundle({ manifest })
  .then(result => process.stdout.write(JSON.stringify({ css: result.css, source_fingerprint: result.source_fingerprint, bytes: result.compiled_bytes })))
  .catch(error => { process.stderr.write(error.message); process.exitCode = 1 })
