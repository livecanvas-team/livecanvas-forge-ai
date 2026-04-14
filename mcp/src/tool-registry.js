function createToolRegistry(client, themeFiles, windpressCompiler, picostrapCompiler = null) {
  const tools = [
    {
      name: 'get_snapshot',
      description: 'Read the current WordPress + LiveCanvas runtime snapshot.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getSnapshot()
    },
    {
      name: 'get_inventory',
      description: 'Read the LiveCanvas-aware inventory of pages, templates, blocks, and sections.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getInventory()
    },
    {
      name: 'get_context',
      description: 'Read the structured AI context built by the plugin.',
      inputSchema: {
        type: 'object',
        properties: {
          post_id: { type: 'integer' },
          post_type: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getContext(argumentsMap)
    },
    {
      name: 'get_theme_context',
      description: 'Read the stack, theme, output rules, and ACF-aware theme context.',
      inputSchema: {
        type: 'object',
        properties: {
          post_id: { type: 'integer' },
          post_type: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getThemeContext(argumentsMap)
    },
    {
      name: 'get_genesis_plan',
      description: 'Read the stored Genesis build plan generated from the persistent project brief.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getGenesisPlan()
    },
    {
      name: 'generate_genesis_plan',
      description: 'Generate and store a Genesis build plan from the current or provided brief.',
      inputSchema: {
        type: 'object',
        properties: {
          brief: { type: 'object' },
          project_mode: { type: 'string' },
          brand_name: { type: 'string' },
          sector: { type: 'string' },
          tone: { type: 'string' },
          logo_status: { type: 'string' },
          required_pages: { type: 'string' },
          notes: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.generateGenesisPlan(argumentsMap)
    },
    {
      name: 'get_page_html',
      description: 'Read the raw post_content HTML for a WordPress post or page.',
      inputSchema: {
        type: 'object',
        required: ['post_id'],
        properties: {
          post_id: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getPageHtml(argumentsMap.post_id)
    },
    {
      name: 'get_acf_fields',
      description: 'Read ACF field groups registered for a specific post type.',
      inputSchema: {
        type: 'object',
        properties: {
          post_type: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getAcfFields(argumentsMap.post_type || 'page')
    },
    {
      name: 'list_lc_blocks',
      description: 'Read reusable LiveCanvas blocks and sections from the library.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getBlocksLibrary()
    },
    {
      name: 'list_command_actions',
      description: 'Read the executable write actions exposed by the plugin.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getCommandActions()
    },
    {
      name: 'suggest_lc_command',
      description: 'Analyze a natural-language request and return the safest suggested companion action payload.',
      inputSchema: {
        type: 'object',
        required: ['user_prompt'],
        properties: {
          user_prompt: { type: 'string' },
          execution_target: { type: 'string' },
          target_id: { type: 'integer' },
          variant: { type: 'string' },
          provider_id: { type: 'string' },
          relative_path: { type: 'string' },
          root_scope: { type: 'string' },
          file_path: { type: 'string' },
          backup_id: { type: 'string' },
          status: { type: 'string' },
          context_post_id: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => client.suggestCommand(argumentsMap)
    },
    {
      name: 'run_lc_command',
      description: 'Execute a LiveCanvas Forge command through the plugin contract. The MCP bridge auto-detects the active framework when it is omitted; new LiveCanvas pages use the Empty Page template automatically, and Picowind page markup must stay Tailwind or DaisyUI-compatible instead of Bootstrap-based.',
      inputSchema: {
        type: 'object',
        required: ['action'],
        properties: {
          action: { type: 'string' },
          dry_run: { type: 'boolean' },
          auto_apply: { type: 'boolean' },
          execution_target: { type: 'string' },
          framework: { type: 'string' },
          target_id: { type: 'integer' },
          variant: { type: 'string' },
          title: { type: 'string' },
          slug: { type: 'string' },
          status: { type: 'string' },
          provider_id: { type: 'string' },
          relative_path: { type: 'string' },
          root_scope: { type: 'string' },
          file_path: { type: 'string' },
          backup_id: { type: 'string' },
          content: { type: 'string' },
          prompt: { type: 'string' },
          colors: { type: 'object' },
          typography: { type: 'object' },
          radius: { type: 'object' },
          buttons: { type: 'object' }
        }
      },
      invoke: async (argumentsMap = {}) => invokeRunLcCommand(argumentsMap, client, picostrapCompiler)
    },
    {
      name: 'compile_picostrap_bundle',
      description: 'Compile the active Picostrap Sass bundle through the bridge and store it back into WordPress.',
      inputSchema: {
        type: 'object',
        properties: {
          force: { type: 'boolean' },
          label: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => {
        if (!picostrapCompiler) {
          throw new Error('Picostrap compiler is not available in this MCP runtime.')
        }

        return picostrapCompiler.buildBundle(argumentsMap)
      }
    },
    {
      name: 'get_mcp_status',
      description: 'Read the current MCP bridge status from the plugin.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getMcpStatus()
    },
    {
      name: 'get_mcp_bootstrap',
      description: 'Read the bootstrap configuration generated by the plugin.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getMcpBootstrap()
    },
    {
      name: 'get_windpress_status',
      description: 'Read WindPress runtime, cache, providers, and handler status through the companion.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getWindPressStatus()
    },
    {
      name: 'list_windpress_volume_entries',
      description: 'List WindPress volume entries, optionally including raw content.',
      inputSchema: {
        type: 'object',
        properties: {
          include_content: { type: 'boolean' },
          handler: { type: 'string' },
          extension: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getWindPressVolume(argumentsMap)
    },
    {
      name: 'list_windpress_volume_handlers',
      description: 'List available WindPress volume handlers, including Picowind handlers when present.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getWindPressHandlers()
    },
    {
      name: 'list_windpress_providers',
      description: 'List WindPress cache providers available for scan/build orchestration.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getWindPressProviders()
    },
    {
      name: 'scan_windpress_provider',
      description: 'Scan a WindPress provider and return normalized content batches used for cache generation.',
      inputSchema: {
        type: 'object',
        required: ['provider_id'],
        properties: {
          provider_id: { type: 'string' },
          metadata: { type: 'object' },
          decode_contents: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => client.scanWindPressProvider(
        argumentsMap.provider_id || '',
        argumentsMap.metadata || {},
        argumentsMap.decode_contents !== false
      )
    },
    {
      name: 'scan_windpress_provider_full',
      description: 'Scan all batches for a WindPress provider until completion and return the aggregated contents.',
      inputSchema: {
        type: 'object',
        required: ['provider_id'],
        properties: {
          provider_id: { type: 'string' },
          metadata: { type: 'object' },
          decode_contents: { type: 'boolean' },
          max_batches: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => client.scanWindPressProviderFull(
        argumentsMap.provider_id || '',
        {
          metadata: argumentsMap.metadata || {},
          decode_contents: argumentsMap.decode_contents !== false,
          max_batches: argumentsMap.max_batches
        }
      )
    },
    {
      name: 'save_windpress_volume_entries',
      description: 'Store WindPress volume entries through the companion.',
      inputSchema: {
        type: 'object',
        required: ['entries'],
        properties: {
          entries: {
            type: 'array',
            items: { type: 'object' }
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.saveWindPressVolumeEntries(argumentsMap.entries || [])
    },
    {
      name: 'store_windpress_theme_json',
      description: 'Write a theme.json payload into WindPress cache.',
      inputSchema: {
        type: 'object',
        required: ['theme_json'],
        properties: {
          theme_json: {
            anyOf: [
              { type: 'string' },
              { type: 'object' },
              {
                type: 'array',
                items: {}
              }
            ]
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.saveWindPressThemeJson(argumentsMap.theme_json)
    },
    {
      name: 'store_windpress_cache_css',
      description: 'Write a compiled CSS payload into WindPress cache.',
      inputSchema: {
        type: 'object',
        required: ['css'],
        properties: {
          css: { type: 'string' },
          sourcemap: { type: 'string' },
          full_build: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => client.saveWindPressCache(
        argumentsMap.css || '',
        argumentsMap.sourcemap || '',
        argumentsMap.full_build ?? null
      )
    },
    {
      name: 'flush_windpress_cache',
      description: 'Flush WordPress and WindPress runtime caches.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.flushWindPressCache()
    },
    {
      name: 'reset_windpress_volume_entry',
      description: 'Reset a WindPress internal volume entry such as main.css, tailwind.config.js, wizard.js, or wizard.css.',
      inputSchema: {
        type: 'object',
        required: ['relative_path'],
        properties: {
          relative_path: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.resetWindPressVolumeEntry(argumentsMap.relative_path || '')
    },
    {
      name: 'build_windpress_cache',
      description: 'Compile WindPress cache locally using the shipped WindPress compiler bundles.',
      inputSchema: {
        type: 'object',
        properties: {
          provider_ids: {
            type: 'array',
            items: { type: 'string' }
          },
          kind: { type: 'string' },
          store: { type: 'boolean' },
          source_map: { type: 'boolean' },
          max_batches: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => windpressCompiler.buildCache(argumentsMap)
    },
    {
      name: 'get_theme_roots',
      description: 'Resolve the local WordPress and active theme roots available to the MCP.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => themeFiles.getThemeRoots()
    },
    {
      name: 'list_theme_files',
      description: 'List readable files from the active stylesheet or template theme roots.',
      inputSchema: {
        type: 'object',
        properties: {
          root_scope: { type: 'string' },
          directory: { type: 'string' },
          extensions: {
            type: 'array',
            items: { type: 'string' }
          },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listFiles(argumentsMap)
    },
    {
      name: 'list_theme_templates',
      description: 'List template-oriented files from Picowind, Picostrap, or generic theme directories.',
      inputSchema: {
        type: 'object',
        properties: {
          root_scope: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listTemplates(argumentsMap)
    },
    {
      name: 'list_twig_templates',
      description: 'List Twig templates from Picowind or other compatible theme directories.',
      inputSchema: {
        type: 'object',
        properties: {
          root_scope: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listTemplatesByExtension('twig', argumentsMap)
    },
    {
      name: 'list_latte_templates',
      description: 'List Latte templates from the active theme roots.',
      inputSchema: {
        type: 'object',
        properties: {
          root_scope: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listTemplatesByExtension('latte', argumentsMap)
    },
    {
      name: 'list_php_templates',
      description: 'List PHP templates from the allowed theme template directories.',
      inputSchema: {
        type: 'object',
        properties: {
          root_scope: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listTemplatesByExtension('php', argumentsMap)
    },
    {
      name: 'read_theme_file',
      description: 'Read a local theme file from the allowed stylesheet or template roots.',
      inputSchema: {
        type: 'object',
        required: ['path'],
        properties: {
          root_scope: { type: 'string' },
          path: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.readFile(argumentsMap)
    },
    {
      name: 'read_template_file',
      description: 'Read a local Twig, Latte, HTML, or PHP template file from the allowed theme roots.',
      inputSchema: {
        type: 'object',
        required: ['path'],
        properties: {
          root_scope: { type: 'string' },
          path: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.readTemplateFile(argumentsMap)
    },
    {
      name: 'write_theme_file',
      description: 'Write a local theme file with backup protection inside the allowed roots.',
      inputSchema: {
        type: 'object',
        required: ['path', 'content'],
        properties: {
          root_scope: { type: 'string' },
          path: { type: 'string' },
          content: { type: 'string' },
          dry_run: { type: 'boolean' },
          create_directories: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.writeFile(argumentsMap)
    },
    {
      name: 'write_template_file',
      description: 'Write a local Twig, Latte, HTML, or PHP template file with backup protection.',
      inputSchema: {
        type: 'object',
        required: ['path', 'content'],
        properties: {
          root_scope: { type: 'string' },
          path: { type: 'string' },
          content: { type: 'string' },
          dry_run: { type: 'boolean' },
          create_directories: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.writeTemplateFile(argumentsMap)
    },
    {
      name: 'list_theme_backups',
      description: 'List local theme and template backups captured by the fallback filesystem layer.',
      inputSchema: {
        type: 'object',
        properties: {
          path: { type: 'string' },
          kind: { type: 'string' },
          limit: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.listBackups(argumentsMap)
    },
    {
      name: 'read_theme_backup',
      description: 'Read one local theme backup file and return its metadata plus contents.',
      inputSchema: {
        type: 'object',
        required: ['backup_id'],
        properties: {
          backup_id: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.readBackup(argumentsMap)
    },
    {
      name: 'restore_theme_backup',
      description: 'Restore a local theme backup back into the active theme roots with preview support.',
      inputSchema: {
        type: 'object',
        required: ['backup_id'],
        properties: {
          backup_id: { type: 'string' },
          root_scope: { type: 'string' },
          path: { type: 'string' },
          dry_run: { type: 'boolean' },
          create_directories: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => themeFiles.restoreBackup(argumentsMap)
    }
  ]

  const toolMap = new Map(tools.map((tool) => [tool.name, tool]))

  return {
    list() {
      return tools.map(({ name, description, inputSchema }) => ({
        name,
        description,
        inputSchema
      }))
    },
    has(name) {
      return toolMap.has(name)
    },
    async invoke(name, argumentsMap = {}) {
      const tool = toolMap.get(name)

      if (!tool) {
        throw new Error(`Unknown tool "${name}"`)
      }

      return tool.invoke(argumentsMap)
    }
  }
}

async function invokeRunLcCommand(argumentsMap, client, picostrapCompiler) {
  const hydratedArguments = await hydrateFrameworkArgument(argumentsMap, client)
  const response = await client.runCommand(hydratedArguments)
  const payload = unwrapResultEnvelope(response)

  if (!shouldAutoCompilePicostrap(hydratedArguments, payload) || !picostrapCompiler) {
    return response
  }

  try {
    const compileResult = await picostrapCompiler.buildBundle({
      force: hydratedArguments.force === true,
      label: hydratedArguments.label || 'design_system_compose_auto_apply'
    })
    const merged = mergeCommandAndCompileResults(payload, compileResult)
    return wrapResultEnvelope(response, merged)
  } catch (error) {
    const merged = mergeCommandAndCompileResults(payload, {
      ok: false,
      build_strategy: 'bridge_dart_sass',
      build_required: true,
      build_executed: false,
      warnings: [error instanceof Error ? error.message : String(error)]
    })

    merged.ok = false
    merged.message = merged.message || 'Design system applied, but Picostrap bundle compilation failed.'
    merged.summary = merged.summary || 'Picostrap design system applied, but the bundle was not compiled automatically.'

    return wrapResultEnvelope(response, merged)
  }
}

async function hydrateFrameworkArgument(argumentsMap, client) {
  if (!argumentsMap || typeof argumentsMap !== 'object') {
    return argumentsMap
  }

  if (argumentsMap.framework) {
    return argumentsMap
  }

  try {
    const snapshotResponse = await client.getSnapshot()
    const snapshotPayload = snapshotResponse && typeof snapshotResponse === 'object' && snapshotResponse.snapshot && typeof snapshotResponse.snapshot === 'object'
      ? snapshotResponse.snapshot
      : snapshotResponse
    const framework = snapshotPayload && typeof snapshotPayload === 'object'
      ? String(snapshotPayload.detected_framework || '')
      : ''

    if (framework === '') {
      return argumentsMap
    }

    return {
      ...argumentsMap,
      framework
    }
  } catch (error) {
    return argumentsMap
  }
}

function shouldAutoCompilePicostrap(argumentsMap, payload) {
  if (!payload || payload.ok === false || payload.target_stack !== 'picostrap') {
    return false
  }

  if (argumentsMap.action === 'design_system_compose') {
    return argumentsMap.auto_apply === true && payload.mode === 'apply'
  }

  if (argumentsMap.action === 'design_system_apply') {
    return argumentsMap.dry_run !== true && payload.mode === 'apply'
  }

  return false
}

function mergeCommandAndCompileResults(commandPayload, compilePayload) {
  const payload = commandPayload && typeof commandPayload === 'object' ? { ...commandPayload } : {}
  const warnings = normalizeWarnings(payload.warnings).concat(normalizeWarnings(compilePayload.warnings))

  return {
    ...payload,
    ok: Boolean(payload.ok !== false && compilePayload.ok !== false),
    build_strategy: compilePayload.build_strategy || payload.build_strategy || '',
    build_required: compilePayload.build_required !== undefined ? compilePayload.build_required : true,
    build_executed: compilePayload.build_executed === true,
    bundle_path: compilePayload.bundle_path || '',
    bundle_url: compilePayload.bundle_url || '',
    bundle_version: compilePayload.bundle_version || 0,
    compiled_at: compilePayload.compiled_at || '',
    warnings: uniqueStrings(warnings),
    data: {
      ...(payload.data || {}),
      compile: compilePayload
    }
  }
}

function unwrapResultEnvelope(payload) {
  if (payload && typeof payload === 'object' && payload.result && typeof payload.result === 'object') {
    return payload.result
  }

  return payload
}

function wrapResultEnvelope(originalPayload, nextPayload) {
  if (originalPayload && typeof originalPayload === 'object' && originalPayload.result && typeof originalPayload.result === 'object') {
    return {
      ...originalPayload,
      result: nextPayload
    }
  }

  return nextPayload
}

function normalizeWarnings(value) {
  return Array.isArray(value) ? value.map((entry) => String(entry || '')).filter(Boolean) : []
}

function uniqueStrings(values) {
  return Array.from(new Set(values))
}

module.exports = {
  createToolRegistry
}
