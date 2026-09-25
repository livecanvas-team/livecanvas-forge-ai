#!/usr/bin/env node
const { runConnect } = require('../src/connect')

const controller = new AbortController()
process.once('SIGINT', () => controller.abort())
process.once('SIGTERM', () => controller.abort())
runConnect(process.argv.slice(2), { signal: controller.signal }).then(code => {
  process.exitCode = code
}).catch(error => {
  process.stderr.write(`${JSON.stringify({ ok: false, state: 'setup_failed', message: error.message })}\n`)
  process.exitCode = 1
})
