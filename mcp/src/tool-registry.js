function createToolRegistry(client, themeFiles, windpressCompiler, picostrapCompiler = null, visualCheck = null, assetDiscovery = null, registryOptions = {}) {
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
      invoke: async (argumentsMap = {}) => attachMcpRuntimeStatus(
        await client.getHandoffSummary(argumentsMap),
        visualCheck
      )
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
      invoke: async (argumentsMap = {}) => attachMcpRuntimeStatus(
        await client.getConnectionHandoff(argumentsMap),
        visualCheck
      )
    },
    {
      name: 'get_ability_diagnostics',
      description: 'Read ability totals, MCP-public exposure, write allowlist state, and adapter diagnostics without changing WordPress.',
      inputSchema: {
        type: 'object',
        properties: {}
      },
      invoke: async () => client.getAbilityDiagnostics()
    },
    {
      name: 'get_runs',
      description: 'Read sanitized recent AI Bridge runs, audit IDs, errors, and rollback availability without changing WordPress.',
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
      invoke: async (argumentsMap = {}) => client.getRuns(argumentsMap)
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
      invoke: async (argumentsMap = {}) => argumentsMap.no_theme_edits
        ? noThemeEditsBlockedResult('theme_file_preview_write')
        : client.remoteThemeFilePreviewWrite(argumentsMap)
    },
    {
      name: 'theme_file_write',
      description: 'Write an allowed child-theme file through the remote WordPress/PHP bridge with automatic backup protection.',
      inputSchema: themeFileWriteSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => argumentsMap.no_theme_edits
        ? noThemeEditsBlockedResult('theme_file_write')
        : client.remoteThemeFileWrite(argumentsMap)
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
      description: 'Read or update Yoast, SEOPress, or AI Bridge fallback SEO metadata including title, description, canonical, noindex, and social images.',
      inputSchema: seoToolsSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => client.runSeoTool(argumentsMap)
    },
    {
      name: 'visual_check_status',
      description: 'Check whether this MCP runtime has Playwright and a launchable Chromium browser before running visual_check. Returns guided install commands without changing the machine.',
      inputSchema: visualCheckStatusSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (!visualCheck || typeof visualCheck.getReadiness !== 'function') {
          return visualCheckUnavailableStatus()
        }
        return visualCheck.getReadiness(argumentsMap)
      }
    },
    {
      name: 'visual_check',
      description: 'Run a local browser visual check with desktop/mobile screenshots, shell counts, broken-image and console diagnostics, overflow checks, and optional computed style snapshots. Call visual_check_status first when readiness is unknown.',
      inputSchema: visualCheckSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (!visualCheck) {
          return visualCheckUnavailableStatus()
        }
        return visualCheck.run(argumentsMap)
      }
    },
    {
      name: 'asset_discovery',
      description: 'Scan a local folder for image/video assets and return a deterministic manifest with checksums, mime types, and stable asset IDs. This tool does not write WordPress.',
      inputSchema: assetDiscoverySchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (!assetDiscovery) {
          return {
            ok: false,
            status: 'asset_discovery_unavailable',
            message: 'The asset discovery runtime was not initialized.'
          }
        }
        return assetDiscovery.run(argumentsMap)
      }
    },
    {
      name: 'media_upload_local_assets',
      description: 'Scan a local asset folder, upload matching image/video files to the WordPress Media Library, and return a manifest with attachment IDs and URLs. This does not edit pages or theme files.',
      inputSchema: localAssetUploadSchema(),
      outputSchema: objectOutputSchema(),
      invoke: async (argumentsMap = {}) => {
        if (!assetDiscovery) {
          return {
            ok: false,
            status: 'asset_upload_unavailable',
            message: 'The asset upload runtime was not initialized.'
          }
        }
        return assetDiscovery.uploadLocalAssets(client, argumentsMap)
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
      description: 'Execute a LiveCanvas AI Bridge command through the plugin contract, including site_prepare, global_shell_apply, site_foundation_run, page_upsert, update_partial for generic lc_partial posts, and dynamic template writes. The MCP bridge auto-detects the active framework when it is omitted; new LiveCanvas pages use the Empty Page template automatically, and Picowind page markup must stay Tailwind or DaisyUI-compatible instead of Bootstrap-based. Picowind policy is DaisyUI-first, and JavaScript is allowed when necessary for the interaction. For page_upsert and update_partial flows, prefer the structured fast-path with body_html/body_html_lines, page_css/page_css_lines, page_js/page_js_lines, no_theme_edits:true, and seo metadata instead of theme edits or one large content blob. Rollback restores use audit_id, not backup_id. Never wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>; LiveCanvas already owns the page shell.',
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
          audit_id: { type: 'string' },
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
          page_css: { type: 'string' },
          page_css_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          page_js: { type: 'string' },
          page_js_lines: {
            type: 'array',
            items: { type: 'string' }
          },
          no_theme_edits: { type: 'boolean' },
          seo: {
            type: 'object',
            properties: {
              title: { type: 'string' },
              description: { type: 'string' },
              canonical: { type: 'string' },
              noindex: { type: 'boolean' }
            }
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
          buttons: { type: 'object' },
          components: { type: 'object' },
          forms: { type: 'object' },
          navbars: { type: 'object' },
          scss_variables: { type: 'object' },
          unset_scss_variables: {
            type: 'array',
            items: { type: 'string' }
          },
          clear_existing_scss_variables: { type: 'boolean' },
          font_assets: { type: 'object' },
          compiled_css: { type: 'string' },
          compiled_source_fingerprint: { type: 'string' },
          expected_state_fingerprint: { type: 'string' }
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
      name: 'build_theme_library_css',
      description: 'Complete the pending CSS build for an admin-imported Picowind Theme Library item. Reuses a verified native WindPress cache when available; otherwise compiles locally and stores the CSS. On remote hosts without a local WordPress root, it returns the exact WindPress Generate step before retrying. Requires write and cache session scopes.',
      inputSchema: {
        type: 'object',
        required: ['theme_slug'],
        properties: {
          theme_slug: { type: 'string' },
          provider_ids: {
            type: 'array',
            items: { type: 'string' }
          },
          kind: { type: 'string' },
          source_map: { type: 'boolean' },
          max_batches: { type: 'integer' }
        }
      },
      invoke: async (argumentsMap = {}) => {
        const themeSlug = String(argumentsMap.theme_slug || '').trim()
        const pendingResponse = await client.getPendingThemeLibraryBuild(themeSlug)
        const pendingResult = pendingResponse.result || pendingResponse

        if (!pendingResult || pendingResult.ok === false || !pendingResult.pending) {
          return pendingResponse
        }

        const pending = pendingResult.pending
        const existingCache = pending && pending.existing_cache && typeof pending.existing_cache === 'object'
          ? pending.existing_cache
          : null
        const existingCacheSha256 = existingCache && existingCache.cache
          ? String(existingCache.cache.sha256 || '')
          : ''

        if (existingCache && existingCache.eligible === true && /^[a-f0-9]{64}$/i.test(existingCacheSha256)) {
          return client.completeThemeLibraryBuild({
            theme_slug: themeSlug,
            import_audit_id: pending.import_audit_id,
            expected_import_checksum: pending.expected_import_checksum,
            cache_sha256: existingCacheSha256.toLowerCase(),
            tailwind_version: Number(pending.tailwind_version || 4),
            candidate_count: 0,
            build_strategy: 'windpress_native_cache'
          })
        }

        let build
        try {
          build = await windpressCompiler.buildCache({
            provider_ids: argumentsMap.provider_ids,
            kind: argumentsMap.kind || 'full',
            store: false,
            source_map: argumentsMap.source_map === true,
            max_batches: argumentsMap.max_batches
          })
        } catch (error) {
          return {
            ok: false,
            ready: false,
            status: 'native_build_required',
            message: 'This remote project cannot use the local WindPress compiler. In WordPress, open WindPress > Settings > Performance, select Generate, wait for Last Generated to update, then retry build_theme_library_css.',
            theme_slug: themeSlug,
            next_action: 'generate_windpress_cache',
            wordpress_path: 'admin.php?page=windpress#/settings/performance',
            retry_tool: 'build_theme_library_css',
            diagnostic: error instanceof Error ? error.message : String(error || '')
          }
        }
        const compiledCss = build && build.css
          ? String(build.css.sourcemap ? build.css.normal || '' : build.css.minified || build.css.normal || '')
          : ''
        const semantic = verifyCompiledCss(compiledCss, pending.css_verification || {})

        if (!build || build.ok === false || Number(build.candidate_count || 0) < 1 || !semantic.ok) {
          return {
            ok: false,
            ready: false,
            status: 'build_failed',
            message: Number(build && build.candidate_count ? build.candidate_count : 0) < 1
              ? 'Tailwind did not discover any utility candidates for this Theme Library import.'
              : 'Compiled CSS failed the Theme Library semantic verification checks.',
            theme_slug: themeSlug,
            semantic,
            build: summarizeWindPressBuild(build)
          }
        }

        const storedResponse = await client.saveWindPressCache(
          compiledCss,
          build.css && build.css.sourcemap ? build.css.sourcemap : '',
          Date.now()
        )
        const stored = storedResponse.result || storedResponse
        const verification = stored && stored.verification ? stored.verification : null
        const cacheSha256 = verification && verification.cache
          ? String(verification.cache.sha256 || '')
          : ''

        if (!verification || verification.ready !== true || !cacheSha256) {
          return {
            ok: false,
            ready: false,
            status: 'build_failed',
            message: 'Tailwind compiled, but WordPress did not return a verifiable persistent WindPress cache checksum.',
            theme_slug: themeSlug,
            semantic,
            build: summarizeWindPressBuild(build)
          }
        }

        return client.completeThemeLibraryBuild({
          theme_slug: themeSlug,
          import_audit_id: pending.import_audit_id,
          expected_import_checksum: pending.expected_import_checksum,
          cache_sha256: cacheSha256,
          tailwind_version: Number(build.tailwind_version || 4),
          candidate_count: Number(build.candidate_count || 0),
          build_strategy: 'windpress_remote_mcp'
        })
      }
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
          create_directories: { type: 'boolean' },
          no_theme_edits: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => argumentsMap.no_theme_edits
        ? noThemeEditsBlockedResult('write_theme_file')
        : themeFiles.writeFile(argumentsMap)
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
          create_directories: { type: 'boolean' },
          no_theme_edits: { type: 'boolean' }
        }
      },
      invoke: async (argumentsMap = {}) => argumentsMap.no_theme_edits
        ? noThemeEditsBlockedResult('write_template_file')
        : themeFiles.writeTemplateFile(argumentsMap)
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

  const advertisedTools = filterAdvertisedTools(tools, registryOptions)
  const toolMap = new Map(tools.map((tool) => [tool.name, tool]))

  return {
    list() {
      return advertisedTools.map(({ name, description, inputSchema, outputSchema }) => {
        const readOnly = isReadMostlyTool(name)

        return {
          name,
          description,
          inputSchema,
          outputSchema: outputSchema || objectOutputSchema(),
          annotations: {
            readOnlyHint: readOnly,
            destructiveHint: !readOnly,
            idempotentHint: readOnly,
            openWorldHint: name === 'visual_check'
          },
          _meta: {
            'io.livecanvas/cache-ttl-ms': readOnly ? 30000 : 0,
            'io.livecanvas/cache-scope': readOnly ? 'site_session' : 'none'
          }
        }
      })
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

function filterAdvertisedTools(tools, options = {}) {
  if (String(options.toolProfile || '').trim().toLowerCase() !== 'compact') {
    return tools
  }

  const visible = new Set([
    'get_snapshot',
    'get_inventory',
    'get_context',
    'get_theme_context',
    'get_genesis_plan',
    'generate_genesis_plan',
    'get_genesis_execution_plan',
    'execute_genesis_next',
    'execute_genesis_task',
    'get_page_html',
    'get_acf_fields',
    'list_lc_blocks',
    'get_connection_handoff',
    'get_block_pattern_library',
    'content_patch_preview',
    'content_patch_apply',
    'media_upload',
    'media_replace',
    'wp_debug',
    'cache_flush',
    'polylang_tools',
    'seo_tools',
    'visual_check_status',
    'visual_check',
    'suggest_lc_command',
    'validate_markup_for_framework',
    'run_lc_command',
    'get_mcp_status'
  ])
  const mode = String(options.connectionMode || '').trim().toLowerCase()
  const framework = String(options.framework || '').trim().toLowerCase()

  if (mode === 'local') {
    for (const name of [
      'asset_discovery',
      'media_upload_local_assets',
      'get_theme_roots',
      'list_theme_files',
      'list_theme_templates',
      'read_theme_file',
      'write_theme_file',
      'list_theme_backups',
      'restore_theme_backup'
    ]) {
      visible.add(name)
    }
  } else if (mode === 'remote') {
    for (const name of [
      'theme_file_read',
      'theme_file_preview_write',
      'theme_file_write',
      'theme_file_backups',
      'theme_file_restore'
    ]) {
      visible.add(name)
    }
  } else {
    for (const name of [
      'theme_file_read',
      'theme_file_preview_write',
      'theme_file_write',
      'theme_file_backups',
      'theme_file_restore',
      'get_theme_roots',
      'list_theme_files',
      'list_theme_templates',
      'read_theme_file',
      'write_theme_file',
      'list_theme_backups',
      'restore_theme_backup'
    ]) {
      visible.add(name)
    }
  }

  if (framework === 'picowind' || framework === '') {
    for (const name of [
      'get_windpress_status',
      'list_windpress_providers',
      'scan_windpress_provider_full',
      'store_windpress_theme_json',
      'build_windpress_cache',
      'build_theme_library_css'
    ]) {
      visible.add(name)
    }
  }

  if (framework === 'picostrap' || framework === '') {
    visible.add('picostrap_compile_preview')
    visible.add('picostrap_compile_apply')
    visible.add('compile_picostrap_bundle')
  }

  return tools.filter((tool) => visible.has(tool.name))
}

async function invokeRunLcCommand(argumentsMap, client, picostrapCompiler) {
  const hydratedArguments = await hydrateFrameworkArgument(argumentsMap, client)
  const isPicostrap = hydratedArguments.framework === 'picostrap'

  if (!isPicostrap || hydratedArguments.dry_run === true || hydratedArguments.compiled_css) {
    return client.runCommand(hydratedArguments)
  }

  if (hydratedArguments.action === 'design_system_compose' && hydratedArguments.auto_apply === true) {
    const composeResponse = await client.runCommand({
      ...hydratedArguments,
      auto_apply: false,
      dry_run: true
    })
    const composePayload = unwrapResultEnvelope(composeResponse)

    if (!composePayload || composePayload.ok === false) {
      return composeResponse
    }

    const applyPayload = composePayload.apply_payload && typeof composePayload.apply_payload === 'object'
      ? composePayload.apply_payload
      : null

    if (!applyPayload) {
      return wrapResultEnvelope(composeResponse, transactionFailure(
        composePayload,
        'The Picostrap compose preview did not return an apply payload.'
      ))
    }

    const applyResponse = await invokePicostrapApplyTransaction({
      ...applyPayload,
      action: 'design_system_apply',
      framework: 'picostrap'
    }, client, picostrapCompiler)
    const applyResult = unwrapResultEnvelope(applyResponse)
    const merged = {
      ...composePayload,
      ...applyResult,
      action: 'design_system_compose',
      mode: 'apply',
      preview: composePayload.preview || {},
      apply_payload: applyPayload,
      preview_url: composePayload.preview_url || applyResult.preview_url || '',
      message: applyResult.ok === false
        ? (applyResult.message || 'Picostrap design system compilation or apply failed.')
        : 'Design system preview compiled and applied atomically.',
      summary: applyResult.ok === false
        ? 'The Picostrap design system was not changed.'
        : 'Composed, compiled, and applied a synchronized Picostrap design system.',
      data: {
        ...(composePayload.data || {}),
        ...(applyResult.data || {}),
        supports_apply: true,
        preview_only: false,
        auto_applied: applyResult.ok !== false,
        compose_preview: {
          preview_url: composePayload.preview_url || '',
          warnings: normalizeWarnings(composePayload.warnings)
        }
      }
    }

    return wrapResultEnvelope(composeResponse, merged)
  }

  if (hydratedArguments.action === 'design_system_apply') {
    return invokePicostrapApplyTransaction(hydratedArguments, client, picostrapCompiler)
  }

  if (hydratedArguments.action === 'site_foundation_run' && hasPicostrapDesignPayload(hydratedArguments)) {
    return invokePicostrapFoundationTransaction(hydratedArguments, client, picostrapCompiler)
  }

  return client.runCommand(hydratedArguments)
}

async function invokePicostrapFoundationTransaction(argumentsMap, client, picostrapCompiler) {
  const previewResponse = await client.runCommand({
    ...argumentsMap,
    dry_run: true
  })
  const previewPayload = unwrapResultEnvelope(previewResponse)
  const designPreview = previewPayload && previewPayload.data && previewPayload.data.steps
    ? previewPayload.data.steps.design_system_apply
    : null

  if (!previewPayload || previewPayload.ok === false || !designPreview || designPreview.ok === false) {
    return previewResponse
  }

  if (!picostrapCompiler || typeof picostrapCompiler.compileBundle !== 'function') {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      'The MCP runtime cannot compile the Picostrap foundation Sass. No foundation writes were made.'
    ))
  }

  const manifest = designPreview.data && designPreview.data.compile_manifest
  if (!manifest || typeof manifest !== 'object') {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      'The foundation preview did not return a Picostrap compile manifest. No foundation writes were made.'
    ))
  }

  let compiled
  try {
    compiled = await picostrapCompiler.compileBundle({ manifest })
  } catch (error) {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      `Picostrap foundation Sass compilation failed before apply: ${error instanceof Error ? error.message : String(error)}`
    ))
  }

  const finalResponse = await client.runCommand({
    ...argumentsMap,
    dry_run: false,
    compiled_css: compiled.css,
    compiled_source_fingerprint: compiled.source_fingerprint || String(manifest.source_fingerprint || ''),
    expected_state_fingerprint: String(designPreview.data.current_state_fingerprint || '')
  })
  const finalPayload = unwrapResultEnvelope(finalResponse)

  return wrapResultEnvelope(finalResponse, {
    ...finalPayload,
    data: {
      ...((finalPayload && finalPayload.data) || {}),
      compile: {
        ok: true,
        build_strategy: compiled.build_strategy || 'bridge_dart_sass_transaction',
        source_fingerprint: compiled.source_fingerprint || '',
        compiled_bytes: compiled.compiled_bytes || 0
      }
    }
  })
}

function hasPicostrapDesignPayload(argumentsMap) {
  if (argumentsMap.design_system && typeof argumentsMap.design_system === 'object' && Object.keys(argumentsMap.design_system).length > 0) {
    return true
  }

  return ['preset', 'colors', 'typography', 'radius', 'buttons', 'components', 'forms', 'navbars', 'scss_variables', 'font_assets']
    .some((key) => argumentsMap[key] && typeof argumentsMap[key] === 'object' && Object.keys(argumentsMap[key]).length > 0)
}

async function invokePicostrapApplyTransaction(argumentsMap, client, picostrapCompiler) {
  const previewArguments = {
    ...argumentsMap,
    dry_run: true
  }
  delete previewArguments.compiled_css
  delete previewArguments.compiled_source_fingerprint
  delete previewArguments.expected_state_fingerprint

  const previewResponse = await client.runCommand(previewArguments)
  const previewPayload = unwrapResultEnvelope(previewResponse)

  if (!previewPayload || previewPayload.ok === false || previewPayload.target_stack !== 'picostrap') {
    return previewResponse
  }

  if (!picostrapCompiler || typeof picostrapCompiler.compileBundle !== 'function') {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      'The MCP runtime cannot compile Picostrap Sass. No Customizer values were changed.'
    ))
  }

  const manifest = previewPayload.data && previewPayload.data.compile_manifest
  if (!manifest || typeof manifest !== 'object') {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      'The Picostrap preview did not return a compile manifest. No Customizer values were changed.'
    ))
  }

  let compiled
  try {
    compiled = await picostrapCompiler.compileBundle({ manifest })
  } catch (error) {
    return wrapResultEnvelope(previewResponse, transactionFailure(
      previewPayload,
      `Picostrap Sass compilation failed before apply: ${error instanceof Error ? error.message : String(error)}`
    ))
  }

  const finalResponse = await client.runCommand({
    ...argumentsMap,
    dry_run: false,
    compiled_css: compiled.css,
    compiled_source_fingerprint: compiled.source_fingerprint || String(manifest.source_fingerprint || ''),
    expected_state_fingerprint: String(previewPayload.data.current_state_fingerprint || '')
  })
  const finalPayload = unwrapResultEnvelope(finalResponse)
  const compileMetadata = {
    ok: true,
    build_strategy: compiled.build_strategy || 'bridge_dart_sass_transaction',
    source_fingerprint: compiled.source_fingerprint || '',
    compiled_bytes: compiled.compiled_bytes || 0
  }

  return wrapResultEnvelope(finalResponse, {
    ...finalPayload,
    data: {
      ...((finalPayload && finalPayload.data) || {}),
      compile: compileMetadata
    }
  })
}

function transactionFailure(payload, message) {
  return {
    ...(payload || {}),
    ok: false,
    mode: 'apply',
    message,
    summary: 'Picostrap design system was left unchanged.',
    build_strategy: 'bridge_dart_sass_transaction',
    build_required: true,
    build_executed: false,
    warnings: uniqueStrings(normalizeWarnings(payload && payload.warnings).concat([message]))
  }
}

async function invokeValidateMarkupForFramework(argumentsMap, client) {
  const hydratedArguments = await hydrateFrameworkArgument({
    ...argumentsMap,
    action: 'validate_markup_for_framework',
    dry_run: true
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

function noThemeEditsBlockedResult(toolName) {
  return {
    ok: false,
    status: 'blocked_by_no_theme_edits',
    tool: toolName,
    message: 'This request declares no_theme_edits=true, so local theme file writes are blocked. Use page_upsert with body_html, page_css, page_js, and seo instead.'
  }
}

function isReadMostlyTool(name) {
  return /^(get_|list_|read_|scan_|preview_|validate_|suggest_|content_patch_preview|theme_file_read|theme_file_preview_write|theme_file_backups|picostrap_compile_preview|wp_debug|visual_check|asset_discovery)/.test(name)
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
      create_directories: { type: 'boolean' },
      dry_run: { type: 'boolean' },
      no_theme_edits: { type: 'boolean' }
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
      provider: { type: 'string', enum: ['auto', 'yoast', 'seopress', 'fallback'] },
      title: { type: 'string' },
      description: { type: 'string' },
      canonical: { type: 'string' },
      noindex: { type: 'boolean' },
      social_image: { type: 'string' },
      twitter_image: { type: 'string' }
    }
  }
}

function assetDiscoverySchema() {
  return {
    type: 'object',
    required: ['directory'],
    properties: {
      directory: { type: 'string' },
      recursive: { type: 'boolean' },
      limit: { type: 'integer' },
      name_includes: {
        type: 'array',
        items: { type: 'string' }
      }
    }
  }
}

function localAssetUploadSchema() {
  return {
    type: 'object',
    required: ['directory'],
    properties: {
      directory: { type: 'string' },
      recursive: { type: 'boolean' },
      limit: { type: 'integer' },
      name_includes: {
        type: 'array',
        items: { type: 'string' }
      },
      post_id: { type: 'integer' },
      set_first_featured: { type: 'boolean' },
      metadata: {
        type: 'object',
        additionalProperties: true
      }
    }
  }
}

function visualCheckSchema() {
  return {
    type: 'object',
    properties: {
      url: { type: 'string' },
      full_page: { type: 'boolean' },
      wait_until: {
        type: 'string',
        enum: ['load', 'domcontentloaded', 'networkidle', 'commit']
      },
      wait_ms: { type: 'integer' },
      timeout_ms: { type: 'integer' },
      output_directory: { type: 'string' },
      executable_path: { type: 'string' },
      headless: { type: 'boolean' },
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

function visualCheckStatusSchema() {
  return {
    type: 'object',
    properties: {
      probe_launch: { type: 'boolean' },
      executable_path: { type: 'string' },
      headless: { type: 'boolean' }
    }
  }
}

function verifyCompiledCss(css, requirements = {}) {
  const normalize = (values) => Array.isArray(values)
    ? [...new Set(values
      .filter((value) => typeof value === 'string')
      .map((value) => value.trim())
      .filter((value) => value.length > 0 && value.length <= 200))]
      .slice(0, 20)
    : []
  const required = normalize(requirements.required_fragments)
  const forbidden = normalize(requirements.forbidden_fragments)
  const missing = required.filter((fragment) => !css.includes(fragment))
  const unexpected = forbidden.filter((fragment) => css.includes(fragment))

  return {
    ok: css.trim() !== '' && missing.length === 0 && unexpected.length === 0,
    required_count: required.length,
    forbidden_count: forbidden.length,
    missing_fragments: missing,
    unexpected_fragments: unexpected
  }
}

function summarizeWindPressBuild(build) {
  if (!build || typeof build !== 'object') {
    return null
  }

  return {
    ok: build.ok !== false,
    tailwind_version: Number(build.tailwind_version || 0),
    provider_count: Number(build.provider_count || 0),
    candidate_count: Number(build.candidate_count || 0),
    css_bytes: build.css
      ? Buffer.byteLength(String(build.css.minified || build.css.normal || ''), 'utf8')
      : 0
  }
}

function visualCheckUnavailableStatus() {
  return {
    schema_version: 'visual-check-readiness.v1',
    ok: false,
    ready: false,
    status: 'visual_check_unavailable',
    package_available: false,
    browser_available: false,
    launch_verified: false,
    message: 'The visual check runtime was not initialized.',
    next_action: 'Restart or update the LiveCanvas AI Bridge MCP server.',
    install_guidance: {
      package_command: 'npm install --save-dev playwright',
      browser_command: 'npx playwright install chromium',
      verify_tool: 'visual_check_status'
    }
  }
}

async function attachMcpRuntimeStatus(response, visualCheck) {
  let visualStatus = visualCheckUnavailableStatus()

  if (visualCheck && typeof visualCheck.getReadiness === 'function') {
    try {
      visualStatus = await visualCheck.getReadiness({ probe_launch: false })
    } catch (error) {
      visualStatus = {
        ...visualCheckUnavailableStatus(),
        status: 'visual_check_status_failed',
        message: 'The MCP runtime could not inspect visual-check readiness.',
        detail: error instanceof Error ? error.message : String(error)
      }
    }
  }

  const runtime = {
    schema_version: 'mcp-runtime-capabilities.v1',
    visual_check: visualStatus
  }

  if (!response || typeof response !== 'object' || Array.isArray(response)) {
    return {
      result: response,
      mcp_runtime: runtime
    }
  }

  const next = {
    ...response,
    mcp_runtime: runtime
  }

  for (const key of ['connection_handoff', 'handoff_summary', 'result']) {
    if (next[key] && typeof next[key] === 'object' && !Array.isArray(next[key])) {
      next[key] = {
        ...next[key],
        mcp_runtime: runtime
      }
    }
  }

  return next
}

module.exports = {
  createToolRegistry
}
