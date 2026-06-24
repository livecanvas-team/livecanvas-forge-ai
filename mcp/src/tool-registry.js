function createToolRegistry(client, themeFiles, windpressCompiler, picostrapCompiler = null, visualCheck = null) {
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
      description: 'Read the stack, theme, output rules, and ACF-aware theme context. On Picowind sites, the policy is DaisyUI-first, Tailwind-compatible, and JavaScript is allowed when necessary.',
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
      name: 'get_genesis_execution_plan',
      description: 'Read the current Genesis execution state, including task statuses and the next actionable task.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getGenesisExecutionPlan()
    },
    {
      name: 'execute_genesis_next',
      description: 'Execute the next pending Genesis task, optionally as preview-only, while updating Genesis progress.',
      inputSchema: {
        type: 'object',
        properties: {
          dry_run: { type: 'boolean' },
          execution_target: { type: 'string' },
          thread_id: { type: 'string' },
          overrides: { type: 'object' }
        }
      },
      invoke: async (argumentsMap = {}) => client.executeGenesisNext(argumentsMap)
    },
    {
      name: 'execute_genesis_task',
      description: 'Execute one specific Genesis task by id, optionally overriding part of its payload.',
      inputSchema: {
        type: 'object',
        required: ['task_id'],
        properties: {
          task_id: { type: 'string' },
          dry_run: { type: 'boolean' },
          execution_target: { type: 'string' },
          thread_id: { type: 'string' },
          overrides: { type: 'object' }
        }
      },
      invoke: async (argumentsMap = {}) => client.executeGenesisTask(argumentsMap)
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
      name: 'get_agent_handoff_package',
      description: 'Read the copy-ready virtual handoff package for Codex and MCP agents, including runbook, smoke tests, readiness files, and checksums.',
      inputSchema: {
        type: 'object',
        properties: {
          limit: {
            type: 'integer',
            minimum: 1,
            maximum: 40
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.getAgentHandoffPackage(argumentsMap)
    },
    {
      name: 'get_handoff_summary',
      description: 'Read the compact readiness summary for Codex and MCP agents, including status, score, blockers, warnings, missing tests, and next action.',
      inputSchema: {
        type: 'object',
        properties: {
          limit: {
            type: 'integer',
            minimum: 1,
            maximum: 40
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.getHandoffSummary(argumentsMap)
    },
    {
      name: 'get_connection_handoff',
      description: 'Read only the first prompt, connection mode, transport, and read-only guardrails for a new Codex or MCP agent session.',
      inputSchema: {
        type: 'object',
        properties: {
          limit: {
            type: 'integer',
            minimum: 1,
            maximum: 40
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.getConnectionHandoff(argumentsMap)
    },
    {
      name: 'get_block_pattern_library',
      description: 'Read export-ready WordPress-native AI Bridge block patterns with checksums for fallback pages and reusable pattern previews.',
      inputSchema: {
        type: 'object',
        properties: {
          limit: {
            type: 'integer',
            minimum: 1,
            maximum: 40
          },
          include_content: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getBlockPatternLibrary(argumentsMap)
    },
    {
      name: 'get_native_pattern_page_blueprints',
      description: 'Read no-write WordPress-native page blueprint recipes composed from registered AI Bridge block patterns.',
      inputSchema: {
        type: 'object',
        properties: {
          include_patterns: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getNativePatternPageBlueprints(argumentsMap)
    },
    {
      name: 'preview_native_pattern_page',
      description: 'Compose a WordPress-native block page preview from registered AI Bridge block patterns without creating or updating a page.',
      inputSchema: {
        type: 'object',
        properties: {
          title: { type: 'string' },
          blueprint: { type: 'string' },
          blueprint_id: { type: 'string' },
          pattern_name: { type: 'string' },
          pattern_names: {
            type: 'array',
            items: { type: 'string' }
          },
          patterns: {
            type: 'array',
            items: { type: 'string' }
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.previewNativePatternPage(argumentsMap)
    },
    {
      name: 'apply_native_pattern_page',
      description: 'Create a new draft WordPress-native page from registered AI Bridge block patterns. This is a dedicated write action and never updates existing content.',
      inputSchema: {
        type: 'object',
        properties: {
          title: { type: 'string' },
          slug: { type: 'string' },
          status: {
            type: 'string',
            enum: ['draft', 'pending', 'private']
          },
          blueprint: { type: 'string' },
          blueprint_id: { type: 'string' },
          pattern_name: { type: 'string' },
          pattern_names: {
            type: 'array',
            items: { type: 'string' }
          },
          patterns: {
            type: 'array',
            items: { type: 'string' }
          }
        }
      },
      invoke: async (argumentsMap = {}) => client.applyNativePatternPage(argumentsMap)
    },
    {
      name: 'content_patch_preview',
      description: 'Preview a targeted text, selector, attribute, append, prepend, or LiveCanvas section patch. Fails when a selector is missing or ambiguous instead of rewriting the full document.',
      inputSchema: contentPatchSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.previewContentPatch(argumentsMap)
    },
    {
      name: 'content_patch_apply',
      description: 'Apply a targeted content patch after preview. Creates audit/rollback metadata through the WordPress plugin.',
      inputSchema: contentPatchSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.applyContentPatch(argumentsMap)
    },
    {
      name: 'theme_file_read',
      description: 'Read an allowed active theme file through the remote WordPress/PHP bridge.',
      inputSchema: themeFileReadSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.remoteThemeFileRead(argumentsMap)
    },
    {
      name: 'theme_file_preview_write',
      description: 'Preview an allowed child-theme file write through the remote WordPress/PHP bridge without writing.',
      inputSchema: themeFileWriteSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.remoteThemeFilePreviewWrite(argumentsMap)
    },
    {
      name: 'theme_file_write',
      description: 'Write an allowed child-theme file through the remote WordPress/PHP bridge with automatic backup protection.',
      inputSchema: themeFileWriteSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.remoteThemeFileWrite(argumentsMap)
    },
    {
      name: 'theme_file_backups',
      description: 'List recent remote theme-file backups captured by AI Bridge.',
      inputSchema: themeBackupListSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.remoteThemeFileBackups(argumentsMap)
    },
    {
      name: 'theme_file_restore',
      description: 'Restore a remote theme-file backup through the WordPress/PHP bridge.',
      inputSchema: themeBackupRestoreSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.remoteThemeFileRestore(argumentsMap)
    },
    {
      name: 'media_upload',
      description: 'Upload URL or base64 media to the WordPress Media Library, with alt/title/caption and optional featured image.',
      inputSchema: mediaUploadSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.uploadMedia(argumentsMap)
    },
    {
      name: 'media_replace',
      description: 'Replace a media URL inside LiveCanvas content through an audited content update.',
      inputSchema: mediaReplaceSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.replaceMedia(argumentsMap)
    },
    {
      name: 'picostrap_compile_preview',
      description: 'Read Picostrap compile manifest and optional SCSS source before compiling.',
      inputSchema: {
        type: 'object',
        properties: {
          import_path: { type: 'string' },
          source_path: { type: 'string' }
        }
      },
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.previewPicostrapCompile(argumentsMap)
    },
    {
      name: 'picostrap_compile_apply',
      description: 'Compile and store the Picostrap bundle through the local MCP runtime, or store provided compiled_css.',
      inputSchema: {
        type: 'object',
        properties: {
          compiled_css: { type: 'string' },
          css: { type: 'string' },
          force: { type: 'boolean' },
          label: { type: 'string' }
        }
      },
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (argumentsMap.compiled_css || argumentsMap.css) {
          return client.applyPicostrapCompile(argumentsMap)
        }
        if (!picostrapCompiler) {
          throw new Error('Picostrap compiler is not available in this MCP runtime.')
        }
        return picostrapCompiler.buildBundle(argumentsMap)
      }
    },
    {
      name: 'wp_debug',
      description: 'Read WordPress/PHP debug context, active plugins, theme status, recent debug.log lines, and recent AI Bridge runs.',
      inputSchema: {
        type: 'object',
        properties: {
          limit: { type: 'integer', minimum: 10, maximum: 300 }
        }
      },
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.getDebugSnapshot(argumentsMap)
    },
    {
      name: 'cache_flush',
      description: 'Flush WordPress object cache, common cache plugins, opcache when available, and bump the AI Bridge asset version.',
      inputSchema: {
        type: 'object',
        properties: {
          dry_run: { type: 'boolean' }
        }
      },
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.flushCache(argumentsMap)
    },
    {
      name: 'polylang_tools',
      description: 'Read or update Polylang language relationships when Polylang is active; returns unavailable when absent.',
      inputSchema: polylangToolsSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.runPolylangTool(argumentsMap)
    },
    {
      name: 'seo_tools',
      description: 'Read or update SEOPress title, description, canonical, and social image metadata when SEOPress is active.',
      inputSchema: seoToolsSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.runSeoTool(argumentsMap)
    },
    {
      name: 'visual_check',
      description: 'Run a local browser visual check with desktop/mobile screenshots, overflow checks, and optional computed style snapshots for selectors.',
      inputSchema: visualCheckSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (!visualCheck) {
          return {
            ok: false,
            status: 'visual_check_unavailable',
            message: 'The visual check runtime was not initialized.'
          }
        }
        return visualCheck.run(argumentsMap)
      }
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
      name: 'validate_markup_for_framework',
      description: 'Preflight page markup against the active framework policy before page_upsert and report global shell conflicts that can affect the rendered page. The MCP bridge auto-detects the active framework when it is omitted and accepts either a raw content string or the structured page fast-path fields body_html/body_html_lines plus footer_script/footer_script_lines. For full homepage or framework migrations, run site_prepare first and address global_shell_apply/site_foundation_run and build_windpress_cache warnings before judging the result. Never wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>; LiveCanvas already owns the page shell.',
      inputSchema: {
        type: 'object',
        properties: {
          framework: { type: 'string' },
          content: { type: 'string' },
          body_html: { type: 'string' },
          body_html_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          footer_html: { type: 'string' },
          footer_html_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          footer_script: { type: 'string' },
          footer_script_lines: {
            type: 'array',
            items: { type: 'string' }
          }
        }
      },
      invoke: async (argumentsMap = {}) => invokeValidateMarkupForFramework(argumentsMap, client)
    },
    {
      name: 'run_lc_command',
      description: 'Execute a LiveCanvas AI Bridge command through the plugin contract, including site_prepare, global_shell_apply, site_foundation_run, page_upsert, update_partial for generic lc_partial posts, and dynamic template writes. The MCP bridge auto-detects the active framework when it is omitted; new LiveCanvas pages use the Empty Page template automatically, and Picowind page markup must stay Tailwind or DaisyUI-compatible instead of Bootstrap-based. Picowind policy is DaisyUI-first, and JavaScript is allowed when necessary for the interaction. For full homepage or framework migrations, run site_prepare first, resolve global shell warnings with global_shell_apply/site_foundation_run, and rebuild WindPress with build_windpress_cache. For page_upsert and update_partial flows, prefer the structured fast-path with body_html/body_html_lines plus footer_script/footer_script_lines instead of sending one large content blob when the page includes interactivity. Never wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>; LiveCanvas already owns the page shell.',
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
          section_intent: { type: 'string' },
          section_operation: { type: 'string' },
          content_strategy: { type: 'string' },
          selected_section_anchor: { type: 'object' },
          visual_reference: { type: 'object' },
          attachments: {
            type: 'array',
            items: { type: 'object' }
          },
          header_html: { type: 'string' },
          header_html_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          footer_html: { type: 'string' },
          footer_html_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          pages: {
            type: 'array',
            items: { type: 'object' }
          },
          design_system: { type: 'object' },
          template_assignment: { type: 'object' },
          template_target: { type: 'string' },
          native_key: { type: 'string' },
          specialty: { type: 'string' },
          content: { type: 'string' },
          body_html: { type: 'string' },
          body_html_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          footer_script: { type: 'string' },
          footer_script_lines: {
            type: 'array',
            items: { type: 'string' }
          },
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
      name: 'get_frontend_prompt_request',
      description: 'Claim an AI Bridge frontend prompt queued for this coding agent. Pass request_id when the drawer or autorunner provides one; otherwise this claims the next queued prompt. After applying the page change with run_lc_command, call complete_frontend_prompt_request.',
      inputSchema: {
        type: 'object',
        properties: {
          agent: { type: 'string' },
          request_id: { type: 'string' }
        }
      },
      invoke: async (argumentsMap = {}) => client.getNextAgentRequest(
        argumentsMap.agent || null,
        argumentsMap.request_id || ''
      )
    },
    {
      name: 'complete_frontend_prompt_request',
      description: 'Mark a queued AI Bridge frontend prompt as completed and return the result to the LiveCanvas drawer. Call this after run_lc_command or another MCP tool has produced the final action result.',
      inputSchema: {
        type: 'object',
        required: ['request_id', 'result'],
        properties: {
          request_id: { type: 'string' },
          result: { type: 'object' },
          thread: { type: 'object' }
        }
      },
      invoke: async (argumentsMap = {}) => client.completeAgentRequest(
        argumentsMap.request_id || '',
        argumentsMap.result || {},
        argumentsMap.thread || null
      )
    },
    {
      name: 'fail_frontend_prompt_request',
      description: 'Mark a queued AI Bridge frontend prompt as failed with a clear reason. Use this if the agent cannot safely produce or apply a valid action.',
      inputSchema: {
        type: 'object',
        required: ['request_id', 'message'],
        properties: {
          request_id: { type: 'string' },
          message: { type: 'string' },
          thread: { type: 'object' }
        }
      },
      invoke: async (argumentsMap = {}) => client.failAgentRequest(
        argumentsMap.request_id || '',
        argumentsMap.message || 'Agent request failed.',
        argumentsMap.thread || null
      )
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
      description: 'Compile WindPress cache locally using the shipped WindPress compiler bundles discovered from the installed WindPress manifest/assets.',
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
      return tools.map(({ name, description, inputSchema, outputSchema }) => ({
        name,
        description,
        inputSchema,
        outputSchema: outputSchema || objectOutputSchema(),
        annotations: {
          lcfaCacheTtlMs: isReadMostlyTool(name) ? 30000 : 0,
          lcfaCacheScope: isReadMostlyTool(name) ? 'site_session' : 'none'
        }
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

async function invokeValidateMarkupForFramework(argumentsMap, client) {
  const hydratedArguments = await hydrateFrameworkArgument({
    ...argumentsMap,
    action: 'validate_markup_for_framework'
  }, client)

  return client.runCommand(hydratedArguments)
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

function objectOutputSchema() {
  return {
    type: 'object',
    additionalProperties: true
  }
}

function isReadMostlyTool(name) {
  return /^(get_|list_|preview_|validate_|content_patch_preview|theme_file_read|theme_file_backups|wp_debug|visual_check)/.test(name)
}

function contentPatchSchema() {
  return {
    type: 'object',
    required: ['target_type'],
    properties: {
      target_type: { type: 'string', enum: ['page', 'partial', 'header', 'footer', 'dynamic_template'] },
      target_id: { type: 'integer' },
      post_id: { type: 'integer' },
      variant: { type: 'string' },
      operation: {
        type: 'string',
        enum: ['replace_text', 'replace_html', 'replace_outer_html', 'append_html', 'prepend_html', 'set_attribute']
      },
      search: { type: 'string' },
      selector: { type: 'string' },
      livecanvas_block: { type: 'string' },
      replacement: { type: 'string' },
      html: { type: 'string' },
      content: { type: 'string' },
      attribute: { type: 'string' },
      value: { type: 'string' },
      allow_multiple: { type: 'boolean' }
    }
  }
}

function themeFileReadSchema() {
  return {
    type: 'object',
    required: ['path'],
    properties: {
      root_scope: { type: 'string', enum: ['active', 'stylesheet', 'template', 'all'] },
      path: { type: 'string' }
    }
  }
}

function themeFileWriteSchema() {
  return {
    type: 'object',
    required: ['path', 'content'],
    properties: {
      root_scope: { type: 'string', enum: ['active', 'stylesheet', 'template', 'all'] },
      path: { type: 'string' },
      content: { type: 'string' },
      create_directories: { type: 'boolean' }
    }
  }
}

function themeBackupListSchema() {
  return {
    type: 'object',
    properties: {
      path: { type: 'string' },
      kind: { type: 'string' },
      limit: { type: 'integer' }
    }
  }
}

function themeBackupRestoreSchema() {
  return {
    type: 'object',
    required: ['backup_id'],
    properties: {
      backup_id: { type: 'string' },
      root_scope: { type: 'string', enum: ['active', 'stylesheet', 'template', 'all'] },
      path: { type: 'string' },
      dry_run: { type: 'boolean' },
      create_directories: { type: 'boolean' }
    }
  }
}

function mediaUploadSchema() {
  return {
    type: 'object',
    properties: {
      source_type: { type: 'string', enum: ['url', 'base64'] },
      url: { type: 'string' },
      data_url: { type: 'string' },
      base64: { type: 'string' },
      mime_type: { type: 'string' },
      filename: { type: 'string' },
      post_id: { type: 'integer' },
      set_featured: { type: 'boolean' },
      title: { type: 'string' },
      alt: { type: 'string' },
      caption: { type: 'string' },
      description: { type: 'string' }
    }
  }
}

function mediaReplaceSchema() {
  return {
    type: 'object',
    required: ['target_type', 'target_id', 'old_url'],
    properties: {
      target_type: { type: 'string', enum: ['page', 'partial', 'header', 'footer', 'dynamic_template'] },
      target_id: { type: 'integer' },
      variant: { type: 'string' },
      old_url: { type: 'string' },
      new_url: { type: 'string' },
      attachment_id: { type: 'integer' }
    }
  }
}

function polylangToolsSchema() {
  return {
    type: 'object',
    properties: {
      action: { type: 'string', enum: ['list_languages', 'get_translations', 'set_translations', 'create_translation'] },
      post_id: { type: 'integer' },
      language: { type: 'string' },
      translations: { type: 'object', additionalProperties: true },
      title: { type: 'string' },
      slug: { type: 'string' },
      content: { type: 'string' },
      excerpt: { type: 'string' },
      status: { type: 'string', enum: ['draft', 'pending', 'private', 'publish'] }
    }
  }
}

function seoToolsSchema() {
  return {
    type: 'object',
    required: ['post_id'],
    properties: {
      action: { type: 'string', enum: ['get', 'update'] },
      post_id: { type: 'integer' },
      title: { type: 'string' },
      description: { type: 'string' },
      canonical: { type: 'string' },
      social_image: { type: 'string' },
      twitter_image: { type: 'string' }
    }
  }
}

function visualCheckSchema() {
  return {
    type: 'object',
    properties: {
      url: { type: 'string' },
      full_page: { type: 'boolean' },
      wait_ms: { type: 'integer' },
      timeout_ms: { type: 'integer' },
      output_directory: { type: 'string' },
      selectors: {
        type: 'array',
        items: { type: 'string' }
      },
      viewports: {
        type: 'array',
        items: {
          type: 'object',
          properties: {
            name: { type: 'string' },
            width: { type: 'integer' },
            height: { type: 'integer' }
          }
        }
      }
    }
  }
}

module.exports = {
  createToolRegistry
}
