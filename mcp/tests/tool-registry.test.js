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

function createFrameworkAwareClient(framework = 'picowind') {
  const calls = []

  return {
    calls,
    async getSnapshot() {
      return {
        snapshot: {
          detected_framework: framework
        }
      }
    },
    async runCommand(argumentsMap = {}) {
      calls.push(argumentsMap)
      return { ok: true, result: { ok: true } }
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

  const handoffPackage = tools.find((tool) => tool.name === 'get_agent_handoff_package')
  assert.ok(handoffPackage, 'get_agent_handoff_package should be registered')
  assert.ok(handoffPackage.inputSchema.properties.limit, 'get_agent_handoff_package should expose a run limit')
  assert.match(
    handoffPackage.description,
    /handoff package/i,
    'get_agent_handoff_package should describe the handoff package'
  )

  const validateMarkup = tools.find((tool) => tool.name === 'validate_markup_for_framework')
  assert.ok(validateMarkup, 'validate_markup_for_framework should be registered')
  assert.ok(validateMarkup.inputSchema.properties.body_html_lines, 'validate_markup_for_framework should expose body_html_lines')
  assert.ok(validateMarkup.inputSchema.properties.body_html_lines.items, 'validate_markup_for_framework body_html_lines should declare array items')
  assert.ok(validateMarkup.inputSchema.properties.footer_script_lines, 'validate_markup_for_framework should expose footer_script_lines')
  assert.ok(validateMarkup.inputSchema.properties.footer_script_lines.items, 'validate_markup_for_framework footer_script_lines should declare array items')

  const runLcCommand = tools.find((tool) => tool.name === 'run_lc_command')
  assert.ok(runLcCommand, 'run_lc_command should be registered')
  assert.ok(runLcCommand.inputSchema.properties.auto_apply, 'run_lc_command should expose auto_apply in its schema')
  assert.ok(runLcCommand.inputSchema.properties.prompt, 'run_lc_command should expose prompt in its schema')
  assert.ok(runLcCommand.inputSchema.properties.body_html, 'run_lc_command should expose body_html for structured page writes')
  assert.ok(runLcCommand.inputSchema.properties.body_html_lines, 'run_lc_command should expose body_html_lines for structured page writes')
  assert.ok(runLcCommand.inputSchema.properties.body_html_lines.items, 'run_lc_command should declare array items for body_html_lines')
  assert.ok(runLcCommand.inputSchema.properties.footer_script, 'run_lc_command should expose footer_script for structured page writes')
  assert.ok(runLcCommand.inputSchema.properties.footer_script_lines, 'run_lc_command should expose footer_script_lines for structured page writes')
  assert.ok(runLcCommand.inputSchema.properties.footer_script_lines.items, 'run_lc_command should declare array items for footer_script_lines')
  assert.ok(runLcCommand.inputSchema.properties.section_intent, 'run_lc_command should expose section_intent for editor section starters')
  assert.ok(runLcCommand.inputSchema.properties.section_operation, 'run_lc_command should expose section_operation for precise section placement')
  assert.ok(runLcCommand.inputSchema.properties.selected_section_anchor, 'run_lc_command should expose selected_section_anchor for editor-selected insertion')
  assert.ok(runLcCommand.inputSchema.properties.visual_reference, 'run_lc_command should expose visual_reference for screenshot-informed generation')
  assert.ok(runLcCommand.inputSchema.properties.header_html, 'run_lc_command should expose header_html for global shell writes')
  assert.ok(runLcCommand.inputSchema.properties.header_html_lines.items, 'run_lc_command should declare array items for header_html_lines')
  assert.ok(runLcCommand.inputSchema.properties.footer_html, 'run_lc_command should expose footer_html for global shell writes')
  assert.ok(runLcCommand.inputSchema.properties.footer_html_lines.items, 'run_lc_command should declare array items for footer_html_lines')
  assert.ok(runLcCommand.inputSchema.properties.pages.items, 'run_lc_command should declare array items for site_foundation_run pages')
  assert.ok(runLcCommand.inputSchema.properties.design_system, 'run_lc_command should expose design_system for foundation orchestration')
  assert.ok(runLcCommand.inputSchema.properties.template_assignment, 'run_lc_command should expose template_assignment for dynamic template assignment')
  assert.ok(runLcCommand.inputSchema.properties.template_target, 'run_lc_command should expose template_target for native LiveCanvas dynamic template assignment')
  assert.ok(runLcCommand.inputSchema.properties.native_key, 'run_lc_command should expose native_key for direct LiveCanvas template meta assignment')
  assert.ok(runLcCommand.inputSchema.properties.specialty, 'run_lc_command should expose specialty for WooCommerce and global template targets')
  assert.match(
    runLcCommand.description,
    /DaisyUI-first/i,
    'run_lc_command should explain the DaisyUI-first Picowind policy'
  )
  assert.match(
    runLcCommand.description,
    /body_html/i,
    'run_lc_command should document the structured page fast-path'
  )
  assert.match(
    runLcCommand.description,
    /Never wrap generated LiveCanvas page content in <main>/i,
    'run_lc_command should explicitly tell agents not to generate an outer main wrapper'
  )
  assert.match(
    validateMarkup.description,
    /Never wrap generated LiveCanvas page content in <main>/i,
    'validate_markup_for_framework should explicitly tell agents not to generate an outer main wrapper'
  )
  assert.match(
    runLcCommand.description,
    /JavaScript is allowed when necessary/i,
    'run_lc_command should explain that JavaScript is allowed when necessary on Picowind'
  )
  assert.match(
    runLcCommand.description,
    /site_foundation_run/i,
    'run_lc_command should document the foundation orchestration action'
  )

  const frameworkClient = createFrameworkAwareClient('picowind')
  const frameworkRegistry = createToolRegistry(
    frameworkClient,
    createNoopThemeFiles(),
    createNoopWindPressCompiler(),
    createNoopPicostrapCompiler()
  )

  await frameworkRegistry.invoke('validate_markup_for_framework', {
    body_html: '<main></main>',
    footer_script: 'console.log("pricing")'
  })

  assert.equal(frameworkClient.calls.length, 1, 'validate_markup_for_framework should call the plugin once')
  assert.equal(frameworkClient.calls[0].action, 'validate_markup_for_framework', 'validate_markup_for_framework should set the command action automatically')
  assert.equal(frameworkClient.calls[0].framework, 'picowind', 'validate_markup_for_framework should inject the active framework when it is missing from the payload')
  assert.equal(frameworkClient.calls[0].body_html, '<main></main>', 'validate_markup_for_framework should forward structured body_html payloads unchanged')
  assert.equal(frameworkClient.calls[0].footer_script, 'console.log("pricing")', 'validate_markup_for_framework should forward structured footer_script payloads unchanged')

  await frameworkRegistry.invoke('run_lc_command', {
    action: 'page_upsert',
    title: 'Pricing',
    body_html: '<main></main>',
    footer_script: 'console.log("pricing")'
  })

  assert.equal(frameworkClient.calls.length, 2, 'run_lc_command should call the plugin once after validation preflight')
  assert.equal(frameworkClient.calls[1].framework, 'picowind', 'run_lc_command should inject the active framework when it is missing from the payload')
  assert.equal(frameworkClient.calls[1].body_html, '<main></main>', 'run_lc_command should forward structured body_html payloads unchanged')
  assert.equal(frameworkClient.calls[1].footer_script, 'console.log("pricing")', 'run_lc_command should forward structured footer_script payloads unchanged')
}

run()
  .then(() => {
    console.log('PASS')
  })
  .catch((error) => {
    console.error(error)
    process.exit(1)
  })
