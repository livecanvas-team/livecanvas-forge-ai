const assert = require('node:assert/strict')
const { createToolRegistry } = require('../src/tool-registry')

function createNoopClient() {
  return new Proxy({}, {
    get() {
      return async () => ({ ok: true })
    }
  })
}

function createNoopThemeFiles() {
  return new Proxy({}, {
    get() {
      return async () => ({ ok: true })
    }
  })
}

function createNoopWindPressCompiler() {
  return {
    async buildCache() {
      return { ok: true }
    }
  }
}

function createNoopPicostrapCompiler() {
  return {
    async buildBundle() {
      return { ok: true }
    }
  }
}

function getArrayBranches(schema, matches = []) {
  if (!schema || typeof schema !== 'object') {
    return matches
  }

  if (schema.type === 'array') {
    matches.push(schema)
  }

  for (const value of Object.values(schema)) {
    if (Array.isArray(value)) {
      value.forEach((entry) => getArrayBranches(entry, matches))
      continue
    }

    getArrayBranches(value, matches)
  }

  return matches
}

async function run() {
  const registry = createToolRegistry(
    createNoopClient(),
    createNoopThemeFiles(),
    createNoopWindPressCompiler(),
    createNoopPicostrapCompiler()
  )

  const tools = registry.list()
  const storeThemeJson = tools.find((tool) => tool.name === 'store_windpress_theme_json')

  assert.ok(storeThemeJson, 'store_windpress_theme_json should be registered')

  const arrayBranches = getArrayBranches(storeThemeJson.inputSchema)

  assert.ok(arrayBranches.length > 0, 'store_windpress_theme_json should contain an array-compatible schema branch')

  for (const branch of arrayBranches) {
    assert.ok(branch.items, 'every array schema branch in store_windpress_theme_json must declare items for MCP schema validation')
  }

  const compilePicostrap = tools.find((tool) => tool.name === 'compile_picostrap_bundle')
  assert.ok(compilePicostrap, 'compile_picostrap_bundle should be registered')

  const runLcCommand = tools.find((tool) => tool.name === 'run_lc_command')
  assert.ok(runLcCommand, 'run_lc_command should be registered')
  assert.ok(runLcCommand.inputSchema.properties.auto_apply, 'run_lc_command should expose auto_apply in its schema')
  assert.ok(runLcCommand.inputSchema.properties.prompt, 'run_lc_command should expose prompt in its schema')
}

run()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
