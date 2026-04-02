#!/usr/bin/env node

const { runCli } = require('../src/cli')

runCli(process.argv.slice(2)).catch((error) => {
  const message = error instanceof Error ? error.stack || error.message : String(error)
  process.stderr.write(`${message}\n`)
  process.exit(1)
})
