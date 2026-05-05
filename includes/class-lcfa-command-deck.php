<?php

defined('ABSPATH') || exit;

final class LCFA_Command_Deck {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Remote_Client $remote_client;
    private LCFA_Design_System_Compose $design_system_compose;
    private LCFA_Design_System_Apply $design_system_apply;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory, LCFA_WindPress_Bridge $windpress_bridge, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Local_MCP_Bridge $local_mcp_bridge, LCFA_Remote_Client $remote_client, ?LCFA_Design_System_Apply $design_system_apply = null, ?LCFA_Design_System_Compose $design_system_compose = null) {
        $this->environment        = $environment;
        $this->inventory          = $inventory;
        $this->windpress_bridge   = $windpress_bridge;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->local_mcp_bridge   = $local_mcp_bridge;
        $this->remote_client      = $remote_client;
        $this->design_system_apply = $design_system_apply ?: new LCFA_Design_System_Apply(
            $environment,
            new LCFA_Design_System_Picostrap_Executor(),
            new LCFA_Design_System_Picowind_Executor(
                $windpress_bridge,
                $theme_files_bridge,
                new LCFA_Design_System_Build_Gateway($local_mcp_bridge)
            ),
            new LCFA_Design_System_Fallback_Executor($environment, $theme_files_bridge)
        );
        $this->design_system_compose = $design_system_compose ?: new LCFA_Design_System_Compose(
            $environment,
            new LCFA_Design_System_Picostrap_Composer(),
            $this->design_system_apply,
            new LCFA_Design_System_Preview()
        );
    }

    public function get_actions(): array {
        return [
            'site_audit' => [
                'label'       => __('Run site audit', 'livecanvas-forge-ai'),
                'description' => __('Returns the current stack summary and LiveCanvas inventory without writing.', 'livecanvas-forge-ai'),
            ],
            'site_prepare' => [
                'label'       => __('Prepare site foundation', 'livecanvas-forge-ai'),
                'description' => __('Preflights stack, inventory, theme roots, and foundation readiness without writing.', 'livecanvas-forge-ai'),
            ],
            'validate_markup_for_framework' => [
                'label'       => __('Validate markup for framework', 'livecanvas-forge-ai'),
                'description' => __('Preflights page markup against the active framework rules without writing content.', 'livecanvas-forge-ai'),
            ],
            'site_foundation_run' => [
                'label'       => __('Run foundation setup', 'livecanvas-forge-ai'),
                'description' => __('Orchestrates prepare, design system, global shell, and starter pages from one payload.', 'livecanvas-forge-ai'),
            ],
            'page_upsert' => [
                'label'       => __('Create or update page', 'livecanvas-forge-ai'),
                'description' => __('Creates a page when no target exists, or updates the existing LiveCanvas page and always returns final URLs.', 'livecanvas-forge-ai'),
            ],
            'design_system_compose' => [
                'label'       => __('Compose design system preview', 'livecanvas-forge-ai'),
                'description' => __('Turns a simple creative brief into a previewable, apply-ready Picostrap design system without writing.', 'livecanvas-forge-ai'),
            ],
            'design_system_apply' => [
                'label'       => __('Apply design system', 'livecanvas-forge-ai'),
                'description' => __('Applies stack-native design tokens to Picostrap or Picowind and returns explicit build metadata.', 'livecanvas-forge-ai'),
            ],
            'create_page' => [
                'label'       => __('Create LiveCanvas page', 'livecanvas-forge-ai'),
                'description' => __('Creates a page in draft or publish state and enables LiveCanvas on it.', 'livecanvas-forge-ai'),
            ],
            'update_page' => [
                'label'       => __('Update page', 'livecanvas-forge-ai'),
                'description' => __('Updates an existing page with new HTML content.', 'livecanvas-forge-ai'),
            ],
            'update_header' => [
                'label'       => __('Update header partial', 'livecanvas-forge-ai'),
                'description' => __('Writes content into the LiveCanvas header partial.', 'livecanvas-forge-ai'),
            ],
            'update_footer' => [
                'label'       => __('Update footer partial', 'livecanvas-forge-ai'),
                'description' => __('Writes content into the LiveCanvas footer partial.', 'livecanvas-forge-ai'),
            ],
            'global_shell_apply' => [
                'label'       => __('Create or update global shell', 'livecanvas-forge-ai'),
                'description' => __('Creates or updates the header and footer partials for the requested variant.', 'livecanvas-forge-ai'),
            ],
            'create_dynamic_template' => [
                'label'       => __('Create dynamic template', 'livecanvas-forge-ai'),
                'description' => __('Creates a new LiveCanvas dynamic template entry.', 'livecanvas-forge-ai'),
            ],
            'update_dynamic_template' => [
                'label'       => __('Update dynamic template', 'livecanvas-forge-ai'),
                'description' => __('Updates an existing LiveCanvas dynamic template.', 'livecanvas-forge-ai'),
            ],
            'windpress_audit' => [
                'label'       => __('Inspect WindPress runtime', 'livecanvas-forge-ai'),
                'description' => __('Returns WindPress status, cache, providers, and handlers without writing.', 'livecanvas-forge-ai'),
            ],
            'windpress_scan_provider' => [
                'label'       => __('Scan WindPress provider', 'livecanvas-forge-ai'),
                'description' => __('Scans one WindPress provider and returns metadata plus content counts.', 'livecanvas-forge-ai'),
            ],
            'windpress_reset_entry' => [
                'label'       => __('Reset WindPress volume entry', 'livecanvas-forge-ai'),
                'description' => __('Resets one WindPress internal file such as main.css or tailwind.config.js.', 'livecanvas-forge-ai'),
            ],
            'windpress_store_theme_json' => [
                'label'       => __('Store WindPress theme.json', 'livecanvas-forge-ai'),
                'description' => __('Writes a theme.json payload into the WindPress cache layer.', 'livecanvas-forge-ai'),
            ],
            'windpress_store_cache_css' => [
                'label'       => __('Store WindPress cache CSS', 'livecanvas-forge-ai'),
                'description' => __('Writes a compiled CSS payload into the WindPress cache layer.', 'livecanvas-forge-ai'),
            ],
            'build_windpress_cache' => [
                'label'       => __('Build WindPress cache locally', 'livecanvas-forge-ai'),
                'description' => __('Runs the local MCP compiler against WindPress providers and optionally stores the generated cache.', 'livecanvas-forge-ai'),
            ],
            'windpress_flush_cache' => [
                'label'       => __('Flush WindPress cache', 'livecanvas-forge-ai'),
                'description' => __('Flushes WordPress and WindPress runtime cache.', 'livecanvas-forge-ai'),
            ],
            'theme_files_audit' => [
                'label'       => __('Inspect theme files', 'livecanvas-forge-ai'),
                'description' => __('Returns active theme roots plus a first template inventory sample.', 'livecanvas-forge-ai'),
            ],
            'theme_backups_audit' => [
                'label'       => __('Inspect theme backups', 'livecanvas-forge-ai'),
                'description' => __('Returns the most recent theme and template backups captured by the fallback layer.', 'livecanvas-forge-ai'),
            ],
            'write_theme_template' => [
                'label'       => __('Write theme template', 'livecanvas-forge-ai'),
                'description' => __('Writes a Twig, Latte, PHP, or HTML template file inside the active theme.', 'livecanvas-forge-ai'),
            ],
            'write_theme_file' => [
                'label'       => __('Write theme file', 'livecanvas-forge-ai'),
                'description' => __('Writes a generic allowed file such as CSS, JS, JSON, or PHP inside the active theme.', 'livecanvas-forge-ai'),
            ],
            'restore_theme_backup' => [
                'label'       => __('Restore theme backup', 'livecanvas-forge-ai'),
                'description' => __('Restores a previously captured theme or template backup back into the active theme roots.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    public function execute(array $payload): array {
        $action    = sanitize_key($payload['action'] ?? '');
        $dry_run   = !empty($payload['dry_run']);
        $provenance = $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules');
        $framework = $this->resolve_framework($payload);
        $title     = sanitize_text_field($payload['title'] ?? '');
        $slug      = sanitize_title($payload['slug'] ?? '');
        $status    = sanitize_key($payload['status'] ?? 'draft');
        $target_id = absint($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $variant   = sanitize_text_field($payload['variant'] ?? '1');
        $provider_id = sanitize_text_field($payload['provider_id'] ?? '');
        $relative_path = sanitize_text_field($payload['relative_path'] ?? '');
        $file_path = sanitize_text_field($payload['file_path'] ?? '');
        $backup_id = sanitize_text_field($payload['backup_id'] ?? '');
        $root_scope = sanitize_key($payload['root_scope'] ?? 'stylesheet');
        $execution_target = sanitize_key($payload['execution_target'] ?? 'local');
        $genesis_task_id = sanitize_key((string) ($payload['genesis_task_id'] ?? ''));
        $content   = $this->resolve_command_content($action, $payload);

        if ($content !== '' && (!isset($payload['content']) || trim((string) $payload['content']) === '')) {
            $payload['content'] = $content;
        }

        if (!isset($this->get_actions()[$action])) {
            return $this->error_result(__('Unsupported command action.', 'livecanvas-forge-ai'));
        }

        if ($this->requires_livecanvas($action) && !$this->environment->is_livecanvas_active()) {
            return $this->error_result(__('LiveCanvas must be active before the Command Deck can write targets.', 'livecanvas-forge-ai'));
        }

        if (!in_array($status, ['draft', 'publish', 'private', 'pending'], true)) {
            $status = 'draft';
        }

        if (!in_array($root_scope, ['stylesheet', 'template', 'active', 'all'], true)) {
            $root_scope = 'stylesheet';
        }

        if (!in_array($execution_target, ['local', 'remote'], true)) {
            $execution_target = 'local';
        }

        $policy = $this->evaluate_policy($action, $dry_run);

        if (empty($policy['ok'])) {
            return $this->error_result((string) ($policy['message'] ?? __('This action is blocked by the current policy profile.', 'livecanvas-forge-ai')));
        }

        $dry_run = !empty($policy['force_preview']) ? true : $dry_run;

        if ($execution_target === 'remote') {
            return $this->execute_remote($payload, $dry_run, $policy);
        }

        $result = [
            'ok'            => true,
            'action'        => $action,
            'mode'          => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message'       => '',
            'summary'       => '',
            'target_type'   => '',
            'target_id'     => 0,
            'target_title'  => '',
            'frontend_url'  => '',
            'edit_url'      => '',
            'diff_html'     => '',
            'existing_html' => '',
            'proposed_html' => $content,
            'inventory'     => null,
            'warnings'      => [],
            'data'          => [],
        ];

        switch ($action) {
            case 'site_audit':
                $inventory            = $this->inventory->get_inventory();
                $result['message']    = __('Site audit prepared.', 'livecanvas-forge-ai');
                $result['summary']    = sprintf(
                    __('Inventory: %1$d pages, %2$d headers, %3$d footers, %4$d dynamic templates.', 'livecanvas-forge-ai'),
                    $inventory['summary']['pages'],
                    $inventory['summary']['headers'],
                    $inventory['summary']['footers'],
                    $inventory['summary']['dynamic_templates']
                );
                $result['target_type'] = 'audit';
                $result['inventory']   = $inventory;
                break;

            case 'site_prepare':
                $prepared = $this->prepare_site_foundation();
                $result['message']     = __('Site foundation preflight prepared.', 'livecanvas-forge-ai');
                $result['summary']     = sprintf(
                    __('Foundation readiness: %1$d pages, %2$d headers, %3$d footers, %4$d dynamic templates on %5$s.', 'livecanvas-forge-ai'),
                    (int) ($prepared['inventory_summary']['pages'] ?? 0),
                    (int) ($prepared['inventory_summary']['headers'] ?? 0),
                    (int) ($prepared['inventory_summary']['footers'] ?? 0),
                    (int) ($prepared['inventory_summary']['dynamic_templates'] ?? 0),
                    (string) ($prepared['snapshot']['detected_framework'] ?? 'unknown')
                );
                $result['target_type'] = 'site_prepare';
                $result['inventory']   = $prepared['inventory'];
                $result['warnings']    = array_merge($result['warnings'], (array) ($prepared['warnings'] ?? []));
                $result['data']        = $prepared;
                break;

            case 'validate_markup_for_framework':
                if (trim($content) === '') {
                    return $this->error_result(__('Markup content is required for framework validation.', 'livecanvas-forge-ai'));
                }

                $inspection = $this->inspect_page_markup_for_framework($framework, $content);

                $result['target_type']   = 'framework_validation';
                $result['target_title']  = $framework;
                $result['summary']       = sprintf(__('Validate page markup against the %s framework policy.', 'livecanvas-forge-ai'), $framework !== '' ? $framework : __('active', 'livecanvas-forge-ai'));
                $result['proposed_html'] = $content;
                $result['message']       = !empty($inspection['valid'])
                    ? __('Markup validation passed.', 'livecanvas-forge-ai')
                    : __('Markup validation found framework conflicts.', 'livecanvas-forge-ai');
                $result['data']          = [
                    'framework'        => $framework,
                    'valid'            => !empty($inspection['valid']),
                    'signal_count'     => count((array) ($inspection['signals'] ?? [])),
                    'signals'          => array_values((array) ($inspection['signals'] ?? [])),
                    'content_bytes'    => strlen($content),
                    'validation_error' => (string) ($inspection['message'] ?? ''),
                ];

                if (empty($inspection['valid']) && (string) ($inspection['message'] ?? '') !== '') {
                    $result['warnings'][] = (string) $inspection['message'];
                }

                $this->append_global_shell_framework_warnings($result, $framework, $variant);
                break;

            case 'design_system_compose':
                $result = array_merge($result, $this->design_system_compose->run($payload));
                break;

            case 'design_system_apply':
                $result = array_merge($result, $this->design_system_apply->run($payload, $dry_run));
                break;

            case 'site_foundation_run':
                $foundation = $this->run_site_foundation($payload, $dry_run);
                $result = array_merge($result, $foundation);
                break;

            case 'page_upsert':
            case 'create_page':
            case 'update_page':
                if (trim($content) === '') {
                    return $this->error_result(__('Forge AI did not generate page HTML for this request, so the current page was left unchanged.', 'livecanvas-forge-ai'));
                }

                $framework_error = $this->validate_page_markup_for_framework($framework, $content);

                if ($framework_error !== '') {
                    return $this->error_result($framework_error);
                }

                $existing   = ['post' => null, 'content' => ''];
                $is_update  = false;
                $page_title = $title;
                $page_slug  = $slug;
                $page_target_id = $action === 'create_page' ? 0 : $target_id;

                if ($page_target_id > 0) {
                    $existing = $this->inventory->get_target_content('page', $page_target_id);

                    if ($action === 'update_page' && !$existing['post']) {
                        return $this->error_result(__('The requested page target was not found.', 'livecanvas-forge-ai'));
                    }

                    $is_update = !empty($existing['post']);
                } elseif ($action === 'update_page') {
                    return $this->error_result(__('A target page ID is required.', 'livecanvas-forge-ai'));
                }

                if (!$is_update && $page_title === '') {
                    return $this->error_result(__('A page title is required.', 'livecanvas-forge-ai'));
                }

                if ($is_update) {
                    $page_title = $page_title !== '' ? $page_title : (string) ($existing['post']['title'] ?? '');
                    $page_slug  = $page_slug !== '' ? $page_slug : (string) ($existing['post']['slug'] ?? '');
                }

                $result['target_type']   = 'page';
                $result['target_id']     = $page_target_id;
                $result['target_title']  = $page_title;
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['data']['framework'] = $framework;
                $this->append_global_shell_framework_warnings($result, $framework, $variant);
                if (sanitize_key((string) ($payload['content_strategy'] ?? '')) === 'section_starter') {
                    $result['data']['section_intent'] = sanitize_key((string) ($payload['section_intent'] ?? ''));
                    $result['data']['section_operation'] = $this->normalize_section_operation((string) ($payload['section_operation'] ?? ''));
                    if (is_array($payload['selected_section_anchor'] ?? null)) {
                        $result['data']['selected_section_anchor'] = $this->sanitize_selected_section_anchor((array) $payload['selected_section_anchor']);
                    }
                    if (is_array($payload['visual_reference'] ?? null)) {
                        $result['data']['visual_reference'] = $this->get_visual_reference_context($payload);
                    }
                }
                $result['summary']       = $is_update
                    ? sprintf(__('Update page #%d.', 'livecanvas-forge-ai'), $page_target_id)
                    : sprintf(__('Create LiveCanvas page "%s".', 'livecanvas-forge-ai'), $page_title);

                if ($is_update) {
                    $result['frontend_url'] = (string) ($existing['post']['view_url'] ?? '');
                    $result['edit_url']     = (string) ($existing['post']['edit_url'] ?? '');
                }

                if (!$dry_run) {
                    $page_id = $this->persist_page_content([
                        'ID'           => $page_target_id,
                        'post_type'    => 'page',
                        'post_title'   => $page_title,
                        'post_name'    => $page_slug !== '' ? $page_slug : '',
                        'post_status'  => $status,
                        'post_content' => $content,
                    ], $is_update);

                    if (is_wp_error($page_id)) {
                        return $this->error_result($page_id->get_error_message());
                    }

                    $page_id = (int) $page_id;
                    update_post_meta($page_id, '_lc_livecanvas_enabled', '1');
                    $page_template = $this->resolve_livecanvas_page_template();

                    if ($page_template !== '') {
                        update_post_meta($page_id, '_wp_page_template', $page_template);
                        $result['data']['page_template'] = $page_template;
                    } else {
                        $result['warnings'][] = __('Empty Page template was not found in the active theme roots.', 'livecanvas-forge-ai');
                    }

                    $this->hydrate_target_urls($result, 'page', $page_id);

                    $result['message'] = $is_update
                        ? __('Page updated.', 'livecanvas-forge-ai')
                        : __('LiveCanvas page created.', 'livecanvas-forge-ai');
                    break;
                }

                if (!$is_update) {
                    $preview_slug = $page_slug !== '' ? $page_slug : sanitize_title($page_title);

                    if ($preview_slug !== '') {
                        $result['frontend_url'] = trailingslashit(home_url($preview_slug));
                    }
                }

                $page_template = $this->resolve_livecanvas_page_template();
                if ($page_template !== '') {
                    $result['data']['page_template'] = $page_template;
                }
                break;

            case 'global_shell_apply':
                $shell = $this->run_global_shell_apply($payload, $variant, $dry_run);

                if (empty($shell['ok'])) {
                    return $this->error_result((string) ($shell['message'] ?? __('Global shell apply failed.', 'livecanvas-forge-ai')));
                }

                $result = array_merge($result, $shell);
                break;

            case 'update_header':
            case 'update_footer':
                $flag      = $action === 'update_header' ? 'is_header' : 'is_footer';
                $target_id = $this->inventory->resolve_partial_post_id($flag, $variant);

                if (!$target_id) {
                    return $this->error_result(__('The requested partial target was not found.', 'livecanvas-forge-ai'));
                }

                if (trim($content) === '') {
                    return $this->error_result($action === 'update_header'
                        ? __('Forge AI did not generate header HTML for this request, so the current header was left unchanged.', 'livecanvas-forge-ai')
                        : __('Forge AI did not generate footer HTML for this request, so the current footer was left unchanged.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content($action === 'update_header' ? 'header' : 'footer', $target_id, $variant);

                $result['target_type']   = $action === 'update_header' ? 'header' : 'footer';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'] ?? '';
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update %s variant %s.', 'livecanvas-forge-ai'), $result['target_type'], $variant);

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
                    }

                    $result['message'] = $action === 'update_header'
                        ? __('Header partial updated.', 'livecanvas-forge-ai')
                        : __('Footer partial updated.', 'livecanvas-forge-ai');
                }
                break;

            case 'create_dynamic_template':
                if ($title === '') {
                    return $this->error_result(__('A dynamic template title is required.', 'livecanvas-forge-ai'));
                }

                $template_assignment = $this->sanitize_dynamic_template_assignment($payload);
                $native_template_keys = $this->get_dynamic_template_native_meta_keys($template_assignment);
                $result['target_type']  = 'dynamic_template';
                $result['target_title'] = $title;
                $result['summary']      = sprintf(__('Create dynamic template "%s".', 'livecanvas-forge-ai'), $title);
                $result['diff_html']    = $this->build_diff('', $content);
                $result['data']['template_assignment'] = $template_assignment;
                $result['data']['native_template_keys'] = $native_template_keys;

                if (!$dry_run) {
                    $post_id = wp_insert_post([
                        'post_type'    => 'lc_dynamic_template',
                        'post_title'   => $title,
                        'post_name'    => $slug !== '' ? $slug : '',
                        'post_status'  => $status,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($post_id)) {
                        return $this->error_result($post_id->get_error_message());
                    }

                    $result['target_id'] = (int) $post_id;
                    if ($template_assignment) {
                        $this->persist_dynamic_template_assignment((int) $post_id, $template_assignment);
                    }
                    $result['message']   = __('Dynamic template created.', 'livecanvas-forge-ai');
                }
                break;

            case 'update_dynamic_template':
                if (!$target_id) {
                    return $this->error_result(__('A dynamic template ID is required.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content('dynamic_template', $target_id);
                $template_assignment = $this->sanitize_dynamic_template_assignment($payload);
                $native_template_keys = $this->get_dynamic_template_native_meta_keys($template_assignment);

                if (!$existing['post']) {
                    return $this->error_result(__('The requested dynamic template was not found.', 'livecanvas-forge-ai'));
                }

                $result['target_type']   = 'dynamic_template';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'];
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update dynamic template #%d.', 'livecanvas-forge-ai'), $target_id);
                $result['data']['template_assignment'] = $template_assignment;
                $result['data']['native_template_keys'] = $native_template_keys;

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
                    }

                    if ($template_assignment) {
                        $this->persist_dynamic_template_assignment($target_id, $template_assignment);
                    }
                    $result['message'] = __('Dynamic template updated.', 'livecanvas-forge-ai');
                }
                break;

            case 'windpress_audit':
                $status = $this->windpress_bridge->get_status();

                $result['target_type'] = 'windpress';
                $result['summary']     = __('Inspect current WindPress runtime state.', 'livecanvas-forge-ai');
                $result['message']     = __('WindPress audit prepared.', 'livecanvas-forge-ai');
                $result['data']        = [
                    'available'      => !empty($status['available']),
                    'installed'      => !empty($status['installed']),
                    'active'         => !empty($status['active']),
                    'tailwind_version' => (int) ($status['tailwind_version'] ?? 0),
                    'performance_mode' => (string) ($status['performance_mode'] ?? ''),
                    'provider_count' => is_array($status['providers'] ?? null) ? count($status['providers']) : 0,
                    'handler_count'  => is_array($status['volume_handlers'] ?? null) ? count($status['volume_handlers']) : 0,
                    'cache_status'   => $status['cache_status'] ?? [],
                    'cache'          => $status['cache'] ?? [],
                    'providers'      => $status['providers'] ?? [],
                    'volume_handlers'=> $status['volume_handlers'] ?? [],
                ];
                break;

            case 'windpress_scan_provider':
                $provider_id = sanitize_key($provider_id);

                if ($provider_id === '') {
                    return $this->error_result(__('A WindPress provider ID is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type']  = 'windpress_provider';
                $result['target_title'] = $provider_id;
                $result['summary']      = sprintf(__('Scan WindPress provider "%s".', 'livecanvas-forge-ai'), $provider_id);

                $scan = $this->windpress_bridge->scan_provider($provider_id, [], false);

                if (empty($scan['ok'])) {
                    return $this->error_result((string) ($scan['message'] ?? __('WindPress provider scan failed.', 'livecanvas-forge-ai')));
                }

                $result['message'] = __('WindPress provider scan prepared.', 'livecanvas-forge-ai');
                $result['data']    = [
                    'provider'      => $scan['provider'] ?? [],
                    'metadata'      => $scan['metadata'] ?? [],
                    'content_count' => is_array($scan['contents'] ?? null) ? count($scan['contents']) : 0,
                ];
                break;

            case 'windpress_reset_entry':
                if ($relative_path === '') {
                    return $this->error_result(__('A WindPress relative_path is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type']  = 'windpress_volume_entry';
                $result['target_title'] = $relative_path;
                $result['summary']      = sprintf(__('Reset WindPress volume entry "%s".', 'livecanvas-forge-ai'), $relative_path);

                if ($dry_run) {
                    $result['message'] = __('WindPress volume reset preview prepared.', 'livecanvas-forge-ai');
                    $result['data']    = [
                        'relative_path' => $relative_path,
                        'operation'     => 'reset_volume_entry',
                    ];
                    break;
                }

                $reset = $this->windpress_bridge->reset_volume_entry($relative_path);

                if (empty($reset['ok'])) {
                    return $this->error_result((string) ($reset['message'] ?? __('WindPress volume reset failed.', 'livecanvas-forge-ai')));
                }

                $result['message'] = (string) ($reset['message'] ?? __('WindPress volume entry reset.', 'livecanvas-forge-ai'));
                $result['data']    = [
                    'relative_path' => $relative_path,
                    'content'       => (string) ($reset['content'] ?? ''),
                ];
                break;

            case 'windpress_flush_cache':
                $result['target_type'] = 'windpress';
                $result['summary']     = __('Flush WindPress runtime cache.', 'livecanvas-forge-ai');

                if ($dry_run) {
                    $result['message'] = __('WindPress cache flush preview prepared.', 'livecanvas-forge-ai');
                    $result['data']    = [
                        'operation' => 'flush_runtime_cache',
                    ];
                    break;
                }

                $flush = $this->windpress_bridge->flush_runtime_cache();

                if (empty($flush['ok'])) {
                    return $this->error_result((string) ($flush['message'] ?? __('WindPress cache flush failed.', 'livecanvas-forge-ai')));
                }

                $result['message'] = (string) ($flush['message'] ?? __('WindPress runtime cache flushed.', 'livecanvas-forge-ai'));
                $result['data']    = [
                    'cache' => $flush['cache'] ?? [],
                ];
                break;

            case 'windpress_store_theme_json':
                if (trim($content) === '') {
                    return $this->error_result(__('A theme.json payload is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type'] = 'windpress_theme_json';
                $result['summary']     = __('Store a WindPress theme.json payload.', 'livecanvas-forge-ai');

                if ($dry_run) {
                    $result['message'] = __('WindPress theme.json preview prepared.', 'livecanvas-forge-ai');
                    break;
                }

                $stored = $this->windpress_bridge->save_theme_json($content);

                if (empty($stored['ok'])) {
                    return $this->error_result((string) ($stored['message'] ?? __('WindPress theme.json write failed.', 'livecanvas-forge-ai')));
                }

                $result['message'] = (string) ($stored['message'] ?? __('WindPress theme.json cache stored.', 'livecanvas-forge-ai'));
                $result['data']    = [
                    'cache' => $stored['cache'] ?? [],
                ];
                break;

            case 'windpress_store_cache_css':
                if (trim($content) === '') {
                    return $this->error_result(__('A CSS payload is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type'] = 'windpress_cache_css';
                $result['summary']     = __('Store a WindPress CSS cache payload.', 'livecanvas-forge-ai');

                if ($dry_run) {
                    $result['message'] = __('WindPress CSS cache preview prepared.', 'livecanvas-forge-ai');
                    break;
                }

                $stored = $this->windpress_bridge->save_cache_css($content);

                if (empty($stored['ok'])) {
                    return $this->error_result((string) ($stored['message'] ?? __('WindPress CSS cache write failed.', 'livecanvas-forge-ai')));
                }

                $result['message'] = (string) ($stored['message'] ?? __('WindPress CSS cache stored.', 'livecanvas-forge-ai'));
                $result['data']    = [
                    'cache' => $stored['cache'] ?? [],
                ];
                break;

            case 'build_windpress_cache':
                $local_status = $this->local_mcp_bridge->get_status();

                if (empty($local_status['build_available'])) {
                    return $this->error_result((string) ($local_status['message'] ?? __('Local WindPress build is not available on this site.', 'livecanvas-forge-ai')));
                }

                $provider_ids = array_values(array_filter(array_map(static function ($item): string {
                    return sanitize_key((string) $item);
                }, preg_split('/[\s,]+/', $provider_id) ?: [])));

                $result['target_type'] = 'windpress_build';
                $result['summary']     = $provider_ids
                    ? sprintf(__('Build WindPress cache locally for providers: %s.', 'livecanvas-forge-ai'), implode(', ', $provider_ids))
                    : __('Build WindPress cache locally for the default provider set.', 'livecanvas-forge-ai');

                $build = $this->local_mcp_bridge->build_windpress_cache([
                    'provider_ids' => $provider_ids,
                    'kind'         => 'full',
                    'store'        => !$dry_run,
                    'source_map'   => false,
                ]);

                if (empty($build['ok'])) {
                    return $this->error_result((string) ($build['message'] ?? __('The local WindPress build failed.', 'livecanvas-forge-ai')));
                }

                $build_result = is_array($build['result'] ?? null) ? $build['result'] : [];
                $css_payload  = is_array($build_result['css'] ?? null) ? $build_result['css'] : [];

                $result['message'] = $dry_run
                    ? __('Local WindPress build preview prepared.', 'livecanvas-forge-ai')
                    : __('Local WindPress cache built and stored.', 'livecanvas-forge-ai');
                $result['data'] = [
                    'provider_ids'    => $build_result['provider_ids'] ?? $provider_ids,
                    'provider_count'  => (int) ($build_result['provider_count'] ?? count($provider_ids)),
                    'candidate_count' => (int) ($build_result['candidate_count'] ?? 0),
                    'tailwind_version'=> (int) ($build_result['tailwind_version'] ?? 0),
                    'provider_scans'  => $build_result['provider_scans'] ?? [],
                    'css_bytes'       => [
                        'normal'    => strlen((string) ($css_payload['normal'] ?? '')),
                        'minified'  => strlen((string) ($css_payload['minified'] ?? '')),
                        'sourcemap' => strlen((string) ($css_payload['sourcemap'] ?? '')),
                    ],
                    'stored'          => $build_result['stored'] ?? null,
                    'local_bridge'    => [
                        'node_version' => (string) ($local_status['node_version'] ?? ''),
                        'command'      => (string) ($build['command'] ?? ''),
                    ],
                ];
                break;

            case 'theme_files_audit':
                try {
                    $roots     = $this->theme_files_bridge->get_theme_roots();
                    $templates = $this->theme_files_bridge->list_templates([
                        'root_scope' => 'active',
                        'limit'      => 12,
                    ]);
                } catch (Throwable $throwable) {
                    return $this->error_result($throwable->getMessage());
                }

                $result['target_type'] = 'theme';
                $result['summary']     = __('Inspect active theme roots and templates.', 'livecanvas-forge-ai');
                $result['message']     = __('Theme file audit prepared.', 'livecanvas-forge-ai');
                $result['data']        = [
                    'roots'          => $roots,
                    'template_count' => is_array($templates['files'] ?? null) ? count($templates['files']) : 0,
                    'templates'      => $templates['files'] ?? [],
                ];
                break;

            case 'theme_backups_audit':
                try {
                    $backups = $this->theme_files_bridge->list_backups([
                        'limit' => 12,
                    ]);
                } catch (Throwable $throwable) {
                    return $this->error_result($throwable->getMessage());
                }

                $result['target_type'] = 'theme_backups';
                $result['summary']     = __('Inspect recent theme and template backups.', 'livecanvas-forge-ai');
                $result['message']     = __('Theme backup audit prepared.', 'livecanvas-forge-ai');
                $result['data']        = [
                    'backups_directory' => (string) ($backups['backups_directory'] ?? ''),
                    'backup_count'      => is_array($backups['backups'] ?? null) ? count($backups['backups']) : 0,
                    'backups'           => $backups['backups'] ?? [],
                ];
                break;

            case 'write_theme_template':
            case 'write_theme_file':
                if ($file_path === '') {
                    return $this->error_result(__('A theme file path is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type']  = $action === 'write_theme_template' ? 'theme_template' : 'theme_file';
                $result['target_title'] = $file_path;
                $result['summary']      = sprintf(__('Write %1$s "%2$s".', 'livecanvas-forge-ai'), $result['target_type'], $file_path);

                try {
                    $existing = $action === 'write_theme_template'
                        ? $this->theme_files_bridge->read_template_file([
                            'root_scope' => $root_scope === 'all' ? 'active' : $root_scope,
                            'path'       => $file_path,
                        ])
                        : $this->theme_files_bridge->read_file([
                            'root_scope' => $root_scope === 'all' ? 'active' : $root_scope,
                            'path'       => $file_path,
                        ]);
                } catch (Throwable $throwable) {
                    $existing = [
                        'content' => '',
                    ];
                }

                $result['existing_html'] = (string) ($existing['content'] ?? '');
                $result['diff_html']     = $this->build_diff($result['existing_html'], $content);

                if ($dry_run) {
                    $result['message'] = $action === 'write_theme_template'
                        ? __('Theme template preview prepared.', 'livecanvas-forge-ai')
                        : __('Theme file preview prepared.', 'livecanvas-forge-ai');
                    $result['data'] = [
                        'path'       => $file_path,
                        'root_scope' => $root_scope,
                        'operation'  => $action,
                    ];
                    break;
                }

                try {
                    $write_result = $action === 'write_theme_template'
                        ? $this->theme_files_bridge->write_template_file([
                            'root_scope' => $root_scope,
                            'path'       => $file_path,
                            'content'    => $content,
                        ])
                        : $this->theme_files_bridge->write_file([
                            'root_scope' => $root_scope,
                            'path'       => $file_path,
                            'content'    => $content,
                        ]);
                } catch (Throwable $throwable) {
                    return $this->error_result($throwable->getMessage());
                }

                $result['message'] = $action === 'write_theme_template'
                    ? __('Theme template written.', 'livecanvas-forge-ai')
                    : __('Theme file written.', 'livecanvas-forge-ai');
                $result['data'] = $write_result;
                break;

            case 'restore_theme_backup':
                if ($backup_id === '') {
                    return $this->error_result(__('A backup ID is required.', 'livecanvas-forge-ai'));
                }

                try {
                    $backup = $this->theme_files_bridge->read_backup([
                        'backup_id' => $backup_id,
                    ]);
                } catch (Throwable $throwable) {
                    return $this->error_result($throwable->getMessage());
                }

                $effective_path = $file_path !== '' ? $file_path : (string) ($backup['relative_path'] ?? '');
                $effective_root_scope = $root_scope !== ''
                    ? $root_scope
                    : (string) ($backup['root'] ?? 'stylesheet');

                if ($effective_path === '') {
                    return $this->error_result(__('The selected backup does not contain a valid target path.', 'livecanvas-forge-ai'));
                }

                $existing = [
                    'content' => '',
                ];

                try {
                    $existing = $this->theme_files_bridge->read_file([
                        'root_scope' => $effective_root_scope === 'all' ? 'active' : $effective_root_scope,
                        'path'       => $effective_path,
                    ]);
                } catch (Throwable $throwable) {
                    $existing = [
                        'content' => '',
                    ];
                }

                $backup_content = (string) ($backup['content'] ?? '');

                $result['target_type']   = 'theme_backup_restore';
                $result['target_title']  = $effective_path;
                $result['existing_html'] = (string) ($existing['content'] ?? '');
                $result['proposed_html'] = $backup_content;
                $result['diff_html']     = $this->build_diff($result['existing_html'], $backup_content);
                $result['summary']       = sprintf(__('Restore backup for "%s".', 'livecanvas-forge-ai'), $effective_path);

                if ($dry_run) {
                    $result['message'] = __('Theme backup restore preview prepared.', 'livecanvas-forge-ai');
                    $result['data'] = [
                        'backup_id'     => (string) ($backup['backup_id'] ?? ''),
                        'relative_path' => $effective_path,
                        'root_scope'    => $effective_root_scope,
                        'backup'        => [
                            'created_at' => (string) ($backup['created_at'] ?? ''),
                            'theme'      => (string) ($backup['theme'] ?? ''),
                            'root'       => (string) ($backup['root'] ?? ''),
                            'kind'       => (string) ($backup['kind'] ?? ''),
                            'bytes'      => (int) ($backup['bytes'] ?? 0),
                        ],
                    ];
                    break;
                }

                try {
                    $restore_result = $this->theme_files_bridge->restore_backup([
                        'backup_id'  => $backup_id,
                        'root_scope' => $effective_root_scope,
                        'path'       => $effective_path,
                    ]);
                } catch (Throwable $throwable) {
                    return $this->error_result($throwable->getMessage());
                }

                $result['message'] = __('Theme backup restored.', 'livecanvas-forge-ai');
                $result['data']    = $restore_result;
                break;
        }

        if (!empty($policy['notice'])) {
            $result['message'] = trim((string) $policy['notice'] . ' ' . (string) ($result['message'] ?? ''));
        }

        if ($genesis_task_id !== '') {
            $result['data']['genesis_task_id'] = $genesis_task_id;
        }

        $result['data']['execution_target'] = $result['execution_target'];
        if ($result['frontend_url'] !== '') {
            $result['data']['frontend_url'] = $result['frontend_url'];
        }
        if ($result['edit_url'] !== '') {
            $result['data']['edit_url'] = $result['edit_url'];
        }
        $result['data']['policy'] = [
            'profile'             => $policy['profile'],
            'allow_file_fallback' => $policy['allow_file_fallback'],
            'force_preview'       => !empty($policy['force_preview']),
            'notice'              => (string) ($policy['notice'] ?? ''),
        ];
        $result['provenance'] = $provenance;
        $result['data']['provenance'] = $provenance;

        LCFA_Settings::append_history([
            'time'         => current_time('mysql', true),
            'action'       => $result['action'],
            'mode'         => $result['mode'],
            'ok'           => $result['ok'],
            'message'      => $result['message'],
            'summary'      => $result['summary'],
            'target_type'  => $result['target_type'],
            'target_id'    => $result['target_id'],
            'target_title' => $result['target_title'],
            'execution_target' => 'local',
        ] + $provenance);

        return $result;
    }

    private function execute_remote(array $payload, bool $dry_run, array $policy): array {
        $provenance = $this->get_payload_provenance($payload, 'admin_command_deck', 'remote_companion');
        $status = $this->remote_client->get_status();

        if (empty($status['available'])) {
            return $this->error_result((string) ($status['message'] ?? __('The remote companion is not reachable.', 'livecanvas-forge-ai')));
        }

        $payload['dry_run'] = $dry_run;
        $payload['execution_target'] = 'remote';
        $payload['provider_id'] = sanitize_text_field($payload['provider_id'] ?? '');

        $response = $this->remote_client->run_command($payload);

        if (is_wp_error($response)) {
            return $this->error_result($response->get_error_message());
        }

        $result = is_array($response['result'] ?? null) ? $response['result'] : [];

        if (!$result) {
            return $this->error_result(__('The remote companion returned an empty command payload.', 'livecanvas-forge-ai'));
        }

        if (!empty($policy['notice'])) {
            $result['message'] = trim((string) $policy['notice'] . ' ' . (string) ($result['message'] ?? ''));
        }

        if (!is_array($result['data'] ?? null)) {
            $result['data'] = [];
        }

        $result['execution_target'] = 'remote';
        if (!isset($result['frontend_url']) || !is_string($result['frontend_url'])) {
            $result['frontend_url'] = '';
        }
        if (!isset($result['edit_url']) || !is_string($result['edit_url'])) {
            $result['edit_url'] = '';
        }
        if (!is_array($result['warnings'] ?? null)) {
            $result['warnings'] = [];
        }
        $result['data']['policy'] = [
            'profile'             => $policy['profile'],
            'allow_file_fallback' => $policy['allow_file_fallback'],
            'force_preview'       => !empty($policy['force_preview']),
            'notice'              => (string) ($policy['notice'] ?? ''),
        ];
        $result['data']['execution_target'] = 'remote';
        if ($result['frontend_url'] !== '') {
            $result['data']['frontend_url'] = $result['frontend_url'];
        }
        if ($result['edit_url'] !== '') {
            $result['data']['edit_url'] = $result['edit_url'];
        }
        $result['data']['remote'] = [
            'endpoint'  => (string) ($status['endpoint'] ?? ''),
            'theme'     => (string) ($status['snapshot']['theme'] ?? ''),
            'framework' => (string) ($status['snapshot']['framework'] ?? ''),
        ];
        $result['provenance'] = $provenance;
        $result['data']['provenance'] = $provenance;

        LCFA_Settings::append_history([
            'time'             => current_time('mysql', true),
            'action'           => (string) ($result['action'] ?? ''),
            'mode'             => (string) ($result['mode'] ?? ($dry_run ? 'preview' : 'apply')),
            'ok'               => !empty($result['ok']),
            'message'          => (string) ($result['message'] ?? ''),
            'summary'          => (string) ($result['summary'] ?? ''),
            'target_type'      => (string) ($result['target_type'] ?? ''),
            'target_id'        => (int) ($result['target_id'] ?? 0),
            'target_title'     => (string) ($result['target_title'] ?? ''),
            'execution_target' => 'remote',
        ] + $provenance);

        return $result;
    }

    private function prepare_site_foundation(): array {
        $snapshot = $this->environment->get_snapshot();
        $inventory = $this->inventory->get_inventory();
        $summary = is_array($inventory['summary'] ?? null) ? $inventory['summary'] : $this->inventory->get_summary();
        $settings = LCFA_Settings::get();
        $windpress = $this->windpress_bridge->get_status();
        $local_mcp = $this->local_mcp_bridge->get_status();
        $warnings = [];
        $theme = [
            'ok' => false,
            'roots' => [],
            'templates' => [],
        ];

        try {
            $theme_roots = $this->theme_files_bridge->get_theme_roots();
            $theme_templates = $this->theme_files_bridge->list_templates([
                'root_scope' => 'active',
                'limit'      => 12,
            ]);
            $theme = [
                'ok'       => true,
                'roots'    => $theme_roots,
                'templates'=> $theme_templates['files'] ?? [],
            ];
        } catch (Throwable $throwable) {
            $warnings[] = $throwable->getMessage();
            $theme['message'] = $throwable->getMessage();
        }

        if (empty($snapshot['livecanvas_active'])) {
            $warnings[] = __('LiveCanvas is not active; write-intent foundation actions will be blocked.', 'livecanvas-forge-ai');
        }

        if ((string) ($snapshot['detected_framework'] ?? '') === 'unknown') {
            $warnings[] = __('The active theme is not recognized as Picostrap or Picowind; design-system writes will use fallback theme assets.', 'livecanvas-forge-ai');
        }

        if ((int) ($summary['headers'] ?? 0) === 0) {
            $warnings[] = __('No header partial is currently registered; global_shell_apply can create one.', 'livecanvas-forge-ai');
        }

        if ((int) ($summary['footers'] ?? 0) === 0) {
            $warnings[] = __('No footer partial is currently registered; global_shell_apply can create one.', 'livecanvas-forge-ai');
        }

        $global_shell = $this->inspect_global_shell_for_framework((string) ($snapshot['detected_framework'] ?? ''), '1');
        $warnings = array_merge($warnings, (array) ($global_shell['warnings'] ?? []));

        if ((string) ($snapshot['detected_framework'] ?? '') === 'picowind') {
            if (empty($windpress['available'])) {
                $warnings[] = __('Picowind is active, but WindPress is not available; Tailwind/DaisyUI output may render without compiled CSS.', 'livecanvas-forge-ai');
            } elseif (empty($windpress['cache']['css']['exists'])) {
                $warnings[] = __('Picowind is active, but the WindPress CSS cache is missing; run build_windpress_cache after content changes.', 'livecanvas-forge-ai');
            }

            if (empty($local_mcp['build_available'])) {
                $warnings[] = __('Local WindPress cache builds are not available from this runtime; verify the local MCP bridge before large Tailwind/DaisyUI rewrites.', 'livecanvas-forge-ai');
            }
        }

        return [
            'snapshot'          => $snapshot,
            'inventory_summary' => $summary,
            'inventory'         => $inventory,
            'theme'             => $theme,
            'windpress'         => $windpress,
            'local_mcp'         => $local_mcp,
            'global_shell'      => $global_shell,
            'policy'            => [
                'permission_profile'  => (string) ($settings['permission_profile'] ?? ''),
                'allow_file_fallback' => !empty($settings['allow_file_fallback']),
            ],
            'warnings'          => array_values(array_unique(array_filter($warnings))),
        ];
    }

    private function run_site_foundation(array $payload, bool $dry_run): array {
        $steps = [];
        $warnings = [];
        $ok = true;

        $steps['site_prepare'] = $this->execute($this->build_child_command_payload($payload, [
            'action'  => 'site_prepare',
            'dry_run' => true,
        ]));

        $ok = $ok && !empty($steps['site_prepare']['ok']);
        $warnings = array_merge($warnings, (array) ($steps['site_prepare']['warnings'] ?? []));

        $design_payload = $this->extract_foundation_design_payload($payload);
        if ($design_payload) {
            $steps['design_system_apply'] = $this->execute($this->build_child_command_payload($payload, array_merge($design_payload, [
                'action'  => 'design_system_apply',
                'dry_run' => $dry_run,
            ])));
            $ok = $ok && !empty($steps['design_system_apply']['ok']);
            $warnings = array_merge($warnings, (array) ($steps['design_system_apply']['warnings'] ?? []));
        }

        if (empty($payload['skip_global_shell'])) {
            $shell_payload = [
                'action'  => 'global_shell_apply',
                'dry_run' => $dry_run,
                'variant' => sanitize_text_field((string) ($payload['variant'] ?? '1')),
            ];

            foreach (['header_html', 'header_html_lines', 'footer_html', 'footer_html_lines', 'content'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $shell_payload[$key] = $payload[$key];
                }
            }

            $steps['global_shell_apply'] = $this->execute($this->build_child_command_payload($payload, $shell_payload));
            $ok = $ok && !empty($steps['global_shell_apply']['ok']);
            $warnings = array_merge($warnings, (array) ($steps['global_shell_apply']['warnings'] ?? []));
        }

        if (empty($payload['skip_pages'])) {
            $pages = $this->normalize_foundation_pages($payload);
            $page_results = [];

            foreach ($pages as $page) {
                $page_payload = array_merge([
                    'action'            => 'page_upsert',
                    'dry_run'           => $dry_run,
                    'status'            => 'draft',
                    'content_strategy'  => 'section_starter',
                    'section_intent'    => 'hero',
                    'section_operation' => 'append',
                    'user_prompt'       => sprintf(__('Create the starter structure for %s.', 'livecanvas-forge-ai'), (string) ($page['title'] ?? __('Untitled', 'livecanvas-forge-ai'))),
                ], $page);

                $page_results[] = $this->execute($this->build_child_command_payload($payload, $page_payload));
                $last = $page_results[count($page_results) - 1] ?? [];
                $ok = $ok && !empty($last['ok']);
                $warnings = array_merge($warnings, (array) ($last['warnings'] ?? []));
            }

            if ($page_results) {
                $steps['pages'] = $page_results;
            }
        }

        return [
            'ok'               => $ok,
            'action'           => 'site_foundation_run',
            'mode'             => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message'          => $dry_run
                ? __('Foundation setup preview prepared.', 'livecanvas-forge-ai')
                : __('Foundation setup run completed.', 'livecanvas-forge-ai'),
            'summary'          => sprintf(
                __('Foundation run executed %d step groups.', 'livecanvas-forge-ai'),
                count($steps)
            ),
            'target_type'      => 'site_foundation',
            'target_id'        => 0,
            'target_title'     => '',
            'warnings'         => array_values(array_unique(array_filter($warnings))),
            'data'             => [
                'steps'      => $steps,
                'step_count' => count($steps),
            ],
        ];
    }

    private function run_global_shell_apply(array $payload, string $variant, bool $dry_run): array {
        $header_html = $this->coerce_multiline_payload($payload, 'header_html', 'header_html_lines');
        $footer_html = $this->coerce_multiline_payload($payload, 'footer_html', 'footer_html_lines');

        if (trim($header_html) === '' && trim($footer_html) === '' && trim((string) ($payload['content'] ?? '')) !== '') {
            $shell_content = $this->extract_global_shell_content((string) wp_unslash((string) $payload['content']));
            $header_html = (string) ($shell_content['header_html'] ?? '');
            $footer_html = (string) ($shell_content['footer_html'] ?? '');
        }

        if (trim($header_html) === '' && trim($footer_html) === '') {
            $starter = $this->build_global_shell_starter($payload);
            $header_html = (string) ($starter['header_html'] ?? '');
            $footer_html = (string) ($starter['footer_html'] ?? '');
        }

        $parts = [];
        $proposed = [];

        if (trim($header_html) !== '' && empty($payload['skip_header'])) {
            $parts['header'] = $this->apply_global_shell_partial('header', 'is_header', $variant, $header_html, $dry_run);
            $proposed[] = trim($header_html);
        }

        if (trim($footer_html) !== '' && empty($payload['skip_footer'])) {
            $parts['footer'] = $this->apply_global_shell_partial('footer', 'is_footer', $variant, $footer_html, $dry_run);
            $proposed[] = trim($footer_html);
        }

        if (!$parts) {
            return [
                'ok'      => false,
                'message' => __('Header or footer HTML is required for global_shell_apply.', 'livecanvas-forge-ai'),
            ];
        }

        $failed = array_filter($parts, static function (array $part): bool {
            return empty($part['ok']);
        });

        if ($failed) {
            $first = reset($failed);

            return [
                'ok'      => false,
                'message' => (string) ($first['message'] ?? __('Global shell partial write failed.', 'livecanvas-forge-ai')),
            ];
        }

        $existing_html = implode("\n\n", array_map(static function (array $part): string {
            return (string) ($part['existing_html'] ?? '');
        }, $parts));
        $proposed_html = implode("\n\n", $proposed);

        return [
            'ok'               => true,
            'action'           => 'global_shell_apply',
            'mode'             => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message'          => $dry_run
                ? __('Global shell preview prepared.', 'livecanvas-forge-ai')
                : __('Global shell applied.', 'livecanvas-forge-ai'),
            'summary'          => sprintf(__('Create or update global shell variant %s.', 'livecanvas-forge-ai'), $variant),
            'target_type'      => 'global_shell',
            'target_id'        => 0,
            'target_title'     => sprintf(__('Variant %s', 'livecanvas-forge-ai'), $variant),
            'existing_html'    => $existing_html,
            'proposed_html'    => $proposed_html,
            'diff_html'        => $this->build_diff($existing_html, $proposed_html),
            'warnings'         => [],
            'data'             => [
                'variant' => $variant,
                'parts'   => $parts,
            ],
        ];
    }

    private function extract_global_shell_content(string $content): array {
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        $header_html = '';
        $footer_html = '';

        if (preg_match('/<header\b[^>]*>.*?<\/header>/is', $content, $matches)) {
            $header_html = trim((string) $matches[0]);
        }

        if (preg_match('/<footer\b[^>]*>.*?<\/footer>/is', $content, $matches)) {
            $footer_html = trim((string) $matches[0]);
        }

        if ($header_html === '' && $footer_html === '') {
            if (stripos($content, '<footer') !== false) {
                $footer_html = $content;
            } else {
                $header_html = $content;
            }
        }

        return [
            'header_html' => $header_html,
            'footer_html' => $footer_html,
        ];
    }

    private function apply_global_shell_partial(string $type, string $flag, string $variant, string $html, bool $dry_run): array {
        $variant = $variant !== '' ? $variant : '1';
        $target_id = $this->resolve_partial_post_id_for_write($flag, $variant);
        $operation = $target_id > 0 ? 'update' : 'create';
        $existing = [
            'post'    => null,
            'content' => '',
        ];

        if ($target_id > 0) {
            $existing = $this->inventory->get_target_content($type, $target_id, $variant);
        }

        $title = $target_id > 0
            ? (string) ($existing['post']['title'] ?? ucfirst($type) . ' Partial')
            : sprintf(__('%s Partial %s', 'livecanvas-forge-ai'), ucfirst($type), $variant);

        $part = [
            'ok'            => true,
            'type'          => $type,
            'operation'     => $operation,
            'variant'       => $variant,
            'target_id'     => $target_id,
            'target_title'  => $title,
            'existing_html' => (string) ($existing['content'] ?? ''),
            'proposed_html' => $html,
            'diff_html'     => $this->build_diff((string) ($existing['content'] ?? ''), $html),
        ];

        if ($dry_run) {
            return $part;
        }

        $post_id = 0;

        if ($target_id > 0) {
            $updated = $this->with_unfiltered_post_content(static function () use ($target_id, $html) {
                return wp_update_post([
                    'ID'           => $target_id,
                    'post_content' => $html,
                ], true);
            });

            if (is_wp_error($updated)) {
                $part['ok'] = false;
                $part['message'] = $updated->get_error_message();

                return $part;
            }

            $post_id = (int) $updated;
        } else {
            $inserted = $this->with_unfiltered_post_content(static function () use ($type, $variant, $html) {
                return wp_insert_post([
                    'post_type'    => 'lc_partial',
                    'post_title'   => sprintf(__('%s Partial %s', 'livecanvas-forge-ai'), ucfirst($type), $variant),
                    'post_name'    => sanitize_title($type . '-partial-' . $variant),
                    'post_status'  => 'publish',
                    'post_content' => $html,
                ], true);
            });

            if (is_wp_error($inserted)) {
                $part['ok'] = false;
                $part['message'] = $inserted->get_error_message();

                return $part;
            }

            $post_id = (int) $inserted;
        }

        update_post_meta($post_id, $flag, $variant);
        $part['target_id'] = $post_id;
        $part['target_title'] = html_entity_decode(get_the_title($post_id) ?: $title);
        $part['frontend_url'] = (string) get_permalink($post_id);
        $part['edit_url'] = $this->resolve_edit_post_url($post_id);
        $part['message'] = $operation === 'create'
            ? __('Partial created.', 'livecanvas-forge-ai')
            : __('Partial updated.', 'livecanvas-forge-ai');

        return $part;
    }

    private function resolve_partial_post_id_for_write(string $flag, string $variant): int {
        if (function_exists('lc_get_partial_postid')) {
            $resolved = lc_get_partial_postid($flag, $variant);

            if ($resolved) {
                return (int) $resolved;
            }
        }

        $posts = get_posts([
            'post_type'      => 'lc_partial',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'meta_key'       => $flag,
            'meta_value'     => $variant,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ]);

        return isset($posts[0]) ? (int) $posts[0]->ID : 0;
    }

    private function build_global_shell_starter(array $payload): array {
        $framework = $this->resolve_framework($payload);
        $brief = LCFA_Settings::get_project_brief();
        $brand_name = sanitize_text_field((string) ($brief['brand_name'] ?? ''));
        $brand_name = $brand_name !== '' ? $brand_name : get_bloginfo('name');
        $brand_name = $brand_name !== '' ? $brand_name : __('Your brand', 'livecanvas-forge-ai');
        $brand_html = htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8');
        $nav_items = $this->get_foundation_nav_items($brief);
        $nav_html = '';
        $footer_links = '';

        foreach ($nav_items as $item) {
            $label = htmlspecialchars(sanitize_text_field((string) ($item['label'] ?? '')), ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars(esc_url_raw((string) ($item['href'] ?? '#')), ENT_QUOTES, 'UTF-8');

            if ($framework === 'picowind') {
                $nav_html .= '<a class="text-sm font-medium text-base-content/75 no-underline transition hover:text-primary" href="' . $href . '">' . $label . '</a>';
                $footer_links .= '<a class="text-sm text-base-content/65 no-underline hover:text-primary" href="' . $href . '">' . $label . '</a>';
            } elseif ($framework === 'unknown') {
                $nav_html .= '<a class="lcfa-shell__link" href="' . $href . '">' . $label . '</a>';
                $footer_links .= '<a class="lcfa-shell__footer-link" href="' . $href . '">' . $label . '</a>';
            } else {
                $nav_html .= '<a class="nav-link px-2" href="' . $href . '">' . $label . '</a>';
                $footer_links .= '<a class="link-secondary text-decoration-none" href="' . $href . '">' . $label . '</a>';
            }
        }

        if ($framework === 'picowind') {
            return [
                'header_html' => <<<HTML
<header class="lcfa-global-shell lcfa-global-shell--header border-b border-base-300 bg-base-100">
  <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-4">
    <a class="text-lg font-semibold text-base-content no-underline" href="/">{$brand_html}</a>
    <nav class="hidden items-center gap-5 md:flex">{$nav_html}</nav>
    <a class="btn btn-primary btn-sm" href="#contact">Contact</a>
  </div>
</header>
HTML,
                'footer_html' => <<<HTML
<footer class="lcfa-global-shell lcfa-global-shell--footer border-t border-base-300 bg-base-200">
  <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 md:grid-cols-[1fr_auto] md:items-center">
    <div>
      <p class="text-lg font-semibold text-base-content">{$brand_html}</p>
      <p class="mt-2 max-w-xl text-sm leading-6 text-base-content/65">A clear global footer shell generated from the current Genesis brief.</p>
    </div>
    <nav class="flex flex-wrap gap-4">{$footer_links}</nav>
  </div>
</footer>
HTML,
            ];
        }

        if ($framework === 'unknown') {
            return [
                'header_html' => <<<HTML
<header class="lcfa-global-shell lcfa-global-shell--header">
  <div class="lcfa-shell__inner">
    <a class="lcfa-shell__brand" href="/">{$brand_html}</a>
    <nav class="lcfa-shell__nav">{$nav_html}</nav>
    <a class="lcfa-shell__cta" href="#contact">Contact</a>
  </div>
</header>
HTML,
                'footer_html' => <<<HTML
<footer class="lcfa-global-shell lcfa-global-shell--footer">
  <div class="lcfa-shell__inner lcfa-shell__inner--footer">
    <div>
      <p class="lcfa-shell__brand">{$brand_html}</p>
      <p class="lcfa-shell__copy">A clear global footer shell generated from the current Genesis brief.</p>
    </div>
    <nav class="lcfa-shell__nav lcfa-shell__nav--footer">{$footer_links}</nav>
  </div>
</footer>
HTML,
            ];
        }

        return [
            'header_html' => <<<HTML
<header class="lcfa-global-shell lcfa-global-shell--header border-bottom bg-white">
  <div class="container d-flex align-items-center justify-content-between gap-4 py-3">
    <a class="navbar-brand fw-bold text-decoration-none" href="/">{$brand_html}</a>
    <nav class="d-none d-md-flex align-items-center gap-3">{$nav_html}</nav>
    <a class="btn btn-primary btn-sm" href="#contact">Contact</a>
  </div>
</header>
HTML,
            'footer_html' => <<<HTML
<footer class="lcfa-global-shell lcfa-global-shell--footer border-top bg-light">
  <div class="container d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4 py-5">
    <div>
      <p class="fw-bold mb-2">{$brand_html}</p>
      <p class="text-body-secondary mb-0">A clear global footer shell generated from the current Genesis brief.</p>
    </div>
    <nav class="d-flex flex-wrap gap-3">{$footer_links}</nav>
  </div>
</footer>
HTML,
        ];
    }

    private function get_foundation_nav_items(array $brief): array {
        $raw_pages = preg_split('/[\n,;]+/', (string) ($brief['required_pages'] ?? '')) ?: [];
        $labels = [];

        foreach ($raw_pages as $page) {
            $page = sanitize_text_field(wp_strip_all_tags((string) $page));
            if ($page !== '') {
                $labels[] = $page;
            }
        }

        if (!$labels) {
            $labels = ['Home', 'About', 'Services', 'Contact'];
        }

        $labels = array_values(array_unique(array_slice($labels, 0, 6)));
        $items = [];

        foreach ($labels as $label) {
            $slug = sanitize_title($label);
            $items[] = [
                'label' => $label,
                'href'  => strtolower($label) === 'home' ? '/' : '/' . $slug . '/',
            ];
        }

        return $items;
    }

    private function build_child_command_payload(array $parent_payload, array $child_payload): array {
        foreach (['_lcfa_origin', '_lcfa_transport', '_lcfa_agent', '_lcfa_processed_by', 'origin', 'transport', 'agent', 'processed_by', 'execution_target', 'thread_id', 'genesis_task_id', 'context_post_id'] as $key) {
            if (!array_key_exists($key, $child_payload) && array_key_exists($key, $parent_payload)) {
                $child_payload[$key] = $parent_payload[$key];
            }
        }

        return $child_payload;
    }

    private function extract_foundation_design_payload(array $payload): array {
        $design_payload = is_array($payload['design_system'] ?? null) ? (array) $payload['design_system'] : [];

        foreach (['framework', 'preset', 'colors', 'typography', 'radius', 'buttons', 'font_assets'] as $key) {
            if (array_key_exists($key, $payload) && !array_key_exists($key, $design_payload)) {
                $design_payload[$key] = $payload[$key];
            }
        }

        foreach (['preset', 'colors', 'typography', 'radius', 'buttons', 'font_assets'] as $key) {
            if (!empty($design_payload[$key])) {
                return $design_payload;
            }
        }

        return [];
    }

    private function normalize_foundation_pages(array $payload): array {
        $raw_pages = [];

        if (is_array($payload['pages'] ?? null)) {
            $raw_pages = (array) $payload['pages'];
        } elseif (is_array($payload['starter_pages'] ?? null)) {
            $raw_pages = (array) $payload['starter_pages'];
        }

        if (!$raw_pages) {
            foreach ($this->get_foundation_nav_items(LCFA_Settings::get_project_brief()) as $item) {
                $raw_pages[] = [
                    'title' => $item['label'],
                    'slug'  => sanitize_title((string) $item['label']),
                ];
            }
        }

        $pages = [];

        foreach ($raw_pages as $index => $page) {
            if (is_scalar($page) || $page === null) {
                $page = [
                    'title' => sanitize_text_field((string) $page),
                ];
            }

            if (!is_array($page)) {
                continue;
            }

            $title = sanitize_text_field((string) ($page['title'] ?? ''));
            if ($title === '') {
                $title = sprintf(__('Page %d', 'livecanvas-forge-ai'), $index + 1);
            }

            $slug = sanitize_title((string) ($page['slug'] ?? $title));
            $normalized = [
                'title' => $title,
                'slug'  => $slug !== '' ? $slug : 'page-' . ($index + 1),
            ];

            foreach (['status', 'content', 'body_html', 'body_html_lines', 'footer_html', 'footer_html_lines', 'footer_script', 'footer_script_lines', 'content_strategy', 'section_intent', 'section_operation', 'user_prompt'] as $key) {
                if (array_key_exists($key, $page)) {
                    $normalized[$key] = $page[$key];
                }
            }

            $pages[] = $normalized;
        }

        return $pages;
    }

    private function sanitize_dynamic_template_assignment(array $payload): array {
        $assignment = is_array($payload['template_assignment'] ?? null) ? (array) $payload['template_assignment'] : [];

        foreach (['template_target', 'target', 'post_type', 'taxonomy', 'term', 'archive', 'single', 'acf_field_group', 'source', 'native_key', 'specialty'] as $key) {
            if (array_key_exists($key, $payload) && !array_key_exists($key, $assignment)) {
                $assignment[$key] = $payload[$key];
            }
        }

        if (is_array($payload['conditions'] ?? null) && !isset($assignment['conditions'])) {
            $assignment['conditions'] = $payload['conditions'];
        }

        $template_target = sanitize_key((string) ($assignment['template_target'] ?? ''));
        $native_key = $this->normalize_livecanvas_template_meta_key((string) ($assignment['native_key'] ?? ''));
        if ($native_key === '' && $template_target !== '') {
            $native_key = $this->normalize_livecanvas_template_meta_key($template_target);
        }

        $specialty = sanitize_key((string) ($assignment['specialty'] ?? ''));
        if ($specialty === '' && $template_target !== '' && $native_key === '') {
            $specialty = $template_target;
        }

        $target = sanitize_key((string) ($assignment['target'] ?? ''));
        if ($target === '' && $template_target !== '' && $native_key === '') {
            $target = $template_target;
        }

        if (!in_array($target, ['single', 'archive', 'taxonomy', 'post_type', 'acf', 'global'], true)) {
            if ($this->get_dynamic_template_specialty_meta_key($target) !== '') {
                $specialty = $target;
                $target = 'global';
            } elseif (!empty($assignment['single'])) {
                $target = 'single';
            } elseif (!empty($assignment['archive'])) {
                $target = 'archive';
            } elseif (!empty($assignment['taxonomy'])) {
                $target = 'taxonomy';
            } elseif (!empty($assignment['acf_field_group'])) {
                $target = 'acf';
            } elseif ($native_key !== '') {
                $target = $this->infer_dynamic_template_target_from_native_key($native_key);
            }
        }

        $conditions = [];
        if (is_array($assignment['conditions'] ?? null)) {
            foreach ((array) $assignment['conditions'] as $condition_key => $condition_value) {
                if (!is_scalar($condition_value) && $condition_value !== null) {
                    continue;
                }

                $conditions[sanitize_key((string) $condition_key)] = sanitize_text_field((string) $condition_value);
            }
        }

        return array_filter([
            'target'          => $target,
            'template_target' => $template_target,
            'native_key'      => $native_key,
            'specialty'       => $specialty,
            'post_type'       => sanitize_key((string) ($assignment['post_type'] ?? '')),
            'taxonomy'        => sanitize_key((string) ($assignment['taxonomy'] ?? '')),
            'term'            => sanitize_text_field((string) ($assignment['term'] ?? '')),
            'acf_field_group' => sanitize_text_field((string) ($assignment['acf_field_group'] ?? '')),
            'source'          => sanitize_key((string) ($assignment['source'] ?? 'forge')),
            'conditions'      => $conditions,
        ], static function ($value): bool {
            return $value !== '' && $value !== [] && $value !== null;
        });
    }

    private function get_dynamic_template_native_meta_keys(array $assignment): array {
        $keys = [];
        $direct_key = $this->normalize_livecanvas_template_meta_key((string) ($assignment['native_key'] ?? ''));

        if ($direct_key !== '') {
            $keys[] = $direct_key;
        }

        $template_target_key = $this->normalize_livecanvas_template_meta_key((string) ($assignment['template_target'] ?? ''));
        if ($template_target_key !== '') {
            $keys[] = $template_target_key;
        }

        $specialty_key = $this->get_dynamic_template_specialty_meta_key((string) ($assignment['specialty'] ?? $assignment['template_target'] ?? ''));
        if ($specialty_key !== '') {
            $keys[] = $specialty_key;
        }

        $target = sanitize_key((string) ($assignment['target'] ?? ''));
        $conditions = is_array($assignment['conditions'] ?? null) ? (array) $assignment['conditions'] : [];
        $post_type = sanitize_key((string) ($assignment['post_type'] ?? $conditions['post_type'] ?? ''));
        $taxonomy = sanitize_key((string) ($assignment['taxonomy'] ?? $conditions['taxonomy'] ?? ''));
        $term = sanitize_key((string) ($assignment['term'] ?? $conditions['term'] ?? $conditions['term_slug'] ?? ''));

        if ($target === 'single' && $post_type !== '') {
            if ($taxonomy !== '' && $term !== '') {
                $keys[] = 'is_single_' . $post_type . '__in_' . $taxonomy . '_' . $term;
            } else {
                $keys[] = 'is_single_' . $post_type;
            }
        }

        if (in_array($target, ['archive', 'post_type'], true)) {
            if ($taxonomy !== '') {
                $keys[] = 'is_archive_for_tax_' . $taxonomy . ($term !== '' ? '__' . $term : '');
            } elseif ($post_type !== '') {
                $keys[] = 'is_archive_for_post_type_' . $post_type;
            }
        }

        if ($target === 'taxonomy' && $taxonomy !== '') {
            $keys[] = 'is_archive_for_tax_' . $taxonomy . ($term !== '' ? '__' . $term : '');
        }

        $keys = array_values(array_unique(array_filter(array_map([$this, 'normalize_livecanvas_template_meta_key'], $keys))));

        return $keys;
    }

    private function normalize_livecanvas_template_meta_key(string $key): string {
        $key = sanitize_key($key);

        if (strpos($key, 'is_') !== 0) {
            return '';
        }

        return preg_match('/^is_[a-z0-9_\-]+(?:__[a-z0-9_\-]+)?$/', $key) ? $key : '';
    }

    private function get_dynamic_template_specialty_meta_key(string $specialty): string {
        $specialty = sanitize_key($specialty);

        $map = [
            'front'            => 'is_front_page',
            'front_page'       => 'is_front_page',
            'homepage'         => 'is_front_page',
            'home'             => 'is_front_page',
            'blog'             => 'is_blog_posts_index',
            'blog_index'       => 'is_blog_posts_index',
            'blog_posts_index' => 'is_blog_posts_index',
            'posts_index'      => 'is_blog_posts_index',
            'search'           => 'is_search',
            '404'              => 'is_404',
            'not_found'        => 'is_404',
            'post_loop'        => 'is_post_loop',
            'loop'             => 'is_post_loop',
            'shop'             => 'is_shop_page',
            'shop_page'        => 'is_shop_page',
            'cart'             => 'is_cart_page',
            'cart_page'        => 'is_cart_page',
            'checkout'         => 'is_checkout_page',
            'checkout_page'    => 'is_checkout_page',
            'account'          => 'is_account_page',
            'my_account'       => 'is_account_page',
            'account_page'     => 'is_account_page',
        ];

        return $map[$specialty] ?? '';
    }

    private function infer_dynamic_template_target_from_native_key(string $native_key): string {
        if (strpos($native_key, 'is_single_') === 0) {
            return 'single';
        }

        if (strpos($native_key, 'is_archive_for_tax_') === 0) {
            return 'taxonomy';
        }

        if (strpos($native_key, 'is_archive_for_post_type_') === 0) {
            return 'post_type';
        }

        return 'global';
    }

    private function persist_dynamic_template_assignment(int $post_id, array $assignment): void {
        update_post_meta($post_id, '_lcfa_template_assignment', $assignment);

        $native_template_keys = $this->get_dynamic_template_native_meta_keys($assignment);
        update_post_meta($post_id, '_lcfa_template_native_keys', $native_template_keys);

        foreach ([
            '_lcfa_template_target'      => 'target',
            '_lcfa_template_post_type'   => 'post_type',
            '_lcfa_template_taxonomy'    => 'taxonomy',
            '_lcfa_template_term'        => 'term',
            '_lcfa_template_acf_group'   => 'acf_field_group',
        ] as $meta_key => $assignment_key) {
            if (!empty($assignment[$assignment_key])) {
                update_post_meta($post_id, $meta_key, (string) $assignment[$assignment_key]);
            } elseif (function_exists('delete_post_meta')) {
                delete_post_meta($post_id, $meta_key);
            }
        }

        if (function_exists('delete_post_meta')) {
            foreach ((array) get_post_meta($post_id) as $meta_key => $meta_value) {
                if (strpos((string) $meta_key, 'is_') === 0) {
                    delete_post_meta($post_id, (string) $meta_key);
                }
            }
        }

        foreach ($native_template_keys as $native_template_key) {
            update_post_meta($post_id, $native_template_key, 1);
        }
    }

    private function requires_livecanvas(string $action): bool {
        return !in_array($action, [
            'site_prepare',
            'validate_markup_for_framework',
            'design_system_compose',
            'design_system_apply',
            'windpress_audit',
            'windpress_scan_provider',
            'windpress_reset_entry',
            'windpress_store_theme_json',
            'windpress_store_cache_css',
            'build_windpress_cache',
            'windpress_flush_cache',
            'theme_files_audit',
            'theme_backups_audit',
            'write_theme_template',
            'write_theme_file',
            'restore_theme_backup',
        ], true);
    }

    private function build_diff(string $existing, string $proposed): string {
        if (function_exists('wp_text_diff')) {
            return (string) wp_text_diff(
                $existing,
                $proposed,
                [
                    'title_left'       => __('Current', 'livecanvas-forge-ai'),
                    'title_right'      => __('Proposed', 'livecanvas-forge-ai'),
                    'show_split_view'  => false,
                ]
            );
        }

        return '';
    }

    private function error_result(string $message): array {
        return [
            'ok'            => false,
            'action'        => '',
            'mode'          => 'preview',
            'execution_target' => 'local',
            'message'       => $message,
            'summary'       => '',
            'target_type'   => '',
            'target_id'     => 0,
            'target_title'  => '',
            'frontend_url'  => '',
            'edit_url'      => '',
            'diff_html'     => '',
            'existing_html' => '',
            'proposed_html' => '',
            'inventory'     => null,
            'warnings'      => [],
            'data'          => [],
        ];
    }

    private function get_payload_provenance(array $payload, string $default_origin = 'admin_command_deck', string $default_processed_by = 'forge_local_rules'): array {
        $origin = sanitize_key((string) ($payload['_lcfa_origin'] ?? $payload['origin'] ?? $default_origin));
        $allowed_origins = ['frontend_bridge', 'admin_command_deck', 'mcp_agent', 'remote_companion', 'api'];
        if (!in_array($origin, $allowed_origins, true)) {
            $origin = $default_origin;
        }

        $default_transport = $origin === 'mcp_agent' ? 'mcp_stdio' : ($origin === 'remote_companion' ? 'remote_rest' : 'browser_rest');
        $transport = sanitize_key((string) ($payload['_lcfa_transport'] ?? $payload['transport'] ?? $default_transport));
        $allowed_transports = ['browser_rest', 'mcp_stdio', 'mcp_bridge', 'remote_rest', 'api'];
        if (!in_array($transport, $allowed_transports, true)) {
            $transport = $default_transport;
        }

        $default_client = $origin === 'mcp_agent' ? 'codex' : 'forge';
        $client = sanitize_key((string) ($payload['_lcfa_agent'] ?? $payload['agent'] ?? $default_client));
        $allowed_clients = ['forge', 'codex', 'opencode', 'claude', 'cursor', 'generic'];
        if (!in_array($client, $allowed_clients, true)) {
            $client = $default_client;
        }

        $processed_by = sanitize_key((string) ($payload['_lcfa_processed_by'] ?? $payload['processed_by'] ?? $default_processed_by));
        $allowed_processors = ['forge_local_rules', 'codex_mcp', 'opencode_mcp', 'claude_mcp', 'cursor_mcp', 'generic_mcp', 'remote_companion'];
        if (!in_array($processed_by, $allowed_processors, true)) {
            $processed_by = $default_processed_by;
        }

        return [
            'origin'       => $origin,
            'transport'    => $transport,
            'agent'        => $client,
            'processed_by' => $processed_by,
        ];
    }

    private function resolve_command_content(string $action, array $payload): string {
        $content = wp_unslash((string) ($payload['content'] ?? ''));

        if (!$this->supports_structured_page_content($action)) {
            return $content;
        }

        if (trim($content) !== '') {
            return $this->strip_outer_livecanvas_main_wrapper($content);
        }

        $body_html     = $this->coerce_multiline_payload($payload, 'body_html', 'body_html_lines');
        $footer_html   = $this->coerce_multiline_payload($payload, 'footer_html', 'footer_html_lines');
        $footer_script = $this->coerce_multiline_payload($payload, 'footer_script', 'footer_script_lines');
        $body_html     = $this->strip_outer_livecanvas_main_wrapper($body_html);

        if (trim($body_html) === '' && trim($footer_html) === '' && trim($footer_script) === '') {
            if (in_array($action, ['page_upsert', 'create_page', 'update_page'], true)) {
                return $this->build_page_starter_content($payload);
            }

            return '';
        }

        $parts = [];

        if (trim($body_html) !== '') {
            $parts[] = trim($body_html);
        }

        if (trim($footer_html) !== '') {
            $parts[] = trim($footer_html);
        }

        if (trim($footer_script) !== '') {
            $parts[] = $this->wrap_page_footer_script($footer_script);
        }

        return implode("\n\n", $parts);
    }

    private function strip_outer_livecanvas_main_wrapper(string $content): string {
        $trimmed = trim($content);

        if ($trimmed === '' || !preg_match('/^<main\b[^>]*>/i', $trimmed, $open_match)) {
            return $content;
        }

        if (!preg_match('/<\/main>\s*$/i', $trimmed, $close_match, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $start = strlen($open_match[0]);
        $end   = (int) $close_match[0][1];
        $inner = substr($trimmed, $start, max(0, $end - $start));

        return trim($inner);
    }

    private function build_page_starter_content(array $payload): string {
        $strategy = sanitize_key((string) ($payload['content_strategy'] ?? ''));
        $section_intent = sanitize_key((string) ($payload['section_intent'] ?? ''));

        if ($strategy !== 'section_starter' || $section_intent === '') {
            return '';
        }

        $framework = $this->resolve_framework($payload);
        $operation = $this->normalize_section_operation((string) ($payload['section_operation'] ?? ''));
        $target_id = absint($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $existing_html = '';

        if ($target_id > 0) {
            $existing = $this->inventory->get_target_content('page', $target_id);
            $existing_html = (string) ($existing['content'] ?? '');
            $payload['_lcfa_existing_html'] = $existing_html;
            $payload['_lcfa_target_title'] = sanitize_text_field((string) ($existing['post']['title'] ?? ''));
        }

        $section_html = $this->build_section_starter_html($section_intent, $framework, $payload);

        if ($section_html === '') {
            return '';
        }

        return $this->merge_section_starter_into_page($existing_html, $section_html, $operation, $payload);
    }

    private function normalize_section_operation(string $operation): string {
        return in_array($operation, ['prepend', 'append', 'replace_hero', 'before_footer', 'after_selected_section'], true) ? $operation : 'append';
    }

    private function build_section_starter_html(string $section_intent, string $framework, array $payload): string {
        $is_picowind = $framework === 'picowind';
        $brief = LCFA_Settings::get_project_brief();
        $brand_name = sanitize_text_field((string) ($brief['brand_name'] ?? ''));
        $brand_name = $brand_name !== '' ? $brand_name : __('Your brand', 'livecanvas-forge-ai');
        $sector = sanitize_text_field((string) ($brief['sector'] ?? ''));
        $sector_phrase = $sector !== '' ? strtolower($sector) . ' teams' : __('modern teams', 'livecanvas-forge-ai');
        $tone = sanitize_text_field((string) ($brief['tone'] ?? ''));
        $tone_phrase = $tone !== '' ? strtolower($tone) : __('clear', 'livecanvas-forge-ai');
        $eyebrow = sprintf(__('Forge AI starter · %s', 'livecanvas-forge-ai'), $brand_name);
        $layout_profile = $this->resolve_section_layout_profile($section_intent, $payload);
        $layout_class = $layout_profile !== '' ? ' lcfa-layout--' . $layout_profile : '';
        $visual_reference = $this->get_visual_reference_context($payload);
        $visual_attr = !empty($visual_reference['enabled']) ? ' data-lcfa-visual-reference="' . sanitize_key((string) ($visual_reference['layout'] ?? 'reference-aware')) . '"' : '';
        $page_title = sanitize_text_field((string) ($payload['_lcfa_target_title'] ?? $payload['title'] ?? ''));
        $page_context_phrase = $page_title !== ''
            ? sprintf(__('for the "%s" page', 'livecanvas-forge-ai'), $page_title)
            : __('for this page', 'livecanvas-forge-ai');
        $prompt_focus = $this->summarize_section_prompt((string) ($payload['user_prompt'] ?? $payload['prompt'] ?? ''), $section_intent);
        $prompt_focus = $prompt_focus !== '' ? $prompt_focus : __('the current page goal', 'livecanvas-forge-ai');
        $visual_note = !empty($visual_reference['enabled'])
            ? __('The attached visual reference is shaping the spacing, hierarchy, and section density for this starter.', 'livecanvas-forge-ai')
            : sprintf(__('This starter is tuned around %s and the active %s stack.', 'livecanvas-forge-ai'), $prompt_focus, $framework);

        switch ($section_intent) {
            case 'hero':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--hero{$layout_class} mx-auto max-w-6xl px-4 py-16"{$visual_attr}>
  <div class="grid gap-10 lg:grid-cols-[1.2fr_.8fr] lg:items-center">
    <div class="space-y-5">
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
      <div class="space-y-3">
        <h1 class="text-4xl font-semibold tracking-tight text-base-content sm:text-5xl">{$brand_name} for {$sector_phrase}, with a {$tone_phrase} first impression {$page_context_phrase}.</h1>
        <p class="max-w-2xl text-base leading-7 text-base-content/75">{$visual_note}</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <a class="inline-flex items-center justify-center rounded-full bg-primary px-5 py-3 text-sm font-semibold text-primary-content no-underline" href="#pricing">See pricing</a>
        <a class="inline-flex items-center justify-center rounded-full border border-base-300 px-5 py-3 text-sm font-semibold text-base-content no-underline" href="#contact">Talk to sales</a>
      </div>
    </div>
    <aside class="rounded-3xl border border-base-300 bg-base-200/70 p-6 shadow-xl">
      <p class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/55">What to validate</p>
      <ul class="mt-4 space-y-3 text-sm leading-6 text-base-content/75">
        <li>Context-aware headline and CTA structure</li>
        <li>Preview/apply loop from the editor drawer</li>
        <li>Framework-safe section markup for {$framework}</li>
      </ul>
    </aside>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--hero{$layout_class} py-5 py-lg-6"{$visual_attr}>
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
        <h1 class="display-4 fw-bold mb-3">{$brand_name} for {$sector_phrase}, with a {$tone_phrase} first impression {$page_context_phrase}.</h1>
        <p class="lead text-body-secondary mb-4">{$visual_note}</p>
        <div class="d-flex flex-wrap gap-3">
          <a class="btn btn-primary btn-lg" href="#pricing">See pricing</a>
          <a class="btn btn-outline-secondary btn-lg" href="#contact">Talk to sales</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 p-xl-5">
            <p class="text-uppercase fw-semibold small text-body-secondary mb-3">What to validate</p>
            <ul class="mb-0 ps-3 text-body-secondary">
              <li>Context-aware headline and CTA structure</li>
              <li>Preview/apply loop from the editor drawer</li>
              <li>Framework-safe section markup for {$framework}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
HTML;

            case 'pricing':
                if ($is_picowind) {
                    return <<<HTML
<section id="pricing" class="lcfa-section-starter lcfa-section--pricing mx-auto max-w-6xl px-4 py-16">
  <div class="mx-auto max-w-3xl text-center">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content sm:text-4xl">Pricing for {$brand_name}, shaped for {$sector_phrase}.</h2>
    <p class="mt-4 text-base leading-7 text-base-content/75">Three plans are enough to validate section insertion, visual hierarchy, and {$tone_phrase} conversion messaging.</p>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><p class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/55">Starter</p><h3 class="mt-4 text-2xl font-semibold text-base-content">€29</h3><p class="mt-2 text-sm text-base-content/70">For lightweight launches.</p></article>
    <article class="rounded-3xl border border-primary/40 bg-primary/10 p-6 shadow-xl"><p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Growth</p><h3 class="mt-4 text-2xl font-semibold text-base-content">€79</h3><p class="mt-2 text-sm text-base-content/70">For teams that need faster iteration.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><p class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/55">Scale</p><h3 class="mt-4 text-2xl font-semibold text-base-content">Custom</h3><p class="mt-2 text-sm text-base-content/70">For complex production flows.</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section id="pricing" class="lcfa-section-starter lcfa-section--pricing py-5 py-lg-6 bg-light">
  <div class="container">
    <div class="text-center mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-3">Pricing for {$brand_name}, shaped for {$sector_phrase}.</h2>
      <p class="lead text-body-secondary mb-0">Three plans are enough to validate section insertion, visual hierarchy, and {$tone_phrase} conversion messaging.</p>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase fw-semibold small text-body-secondary mb-3">Starter</p><h3 class="display-6 fw-bold mb-2">€29</h3><p class="text-body-secondary mb-0">For lightweight launches.</p></div></div></div>
      <div class="col"><div class="card h-100 border-primary shadow"><div class="card-body p-4"><p class="text-uppercase fw-semibold small text-primary mb-3">Growth</p><h3 class="display-6 fw-bold mb-2">€79</h3><p class="text-body-secondary mb-0">For teams that need faster iteration.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><p class="text-uppercase fw-semibold small text-body-secondary mb-3">Scale</p><h3 class="display-6 fw-bold mb-2">Custom</h3><p class="text-body-secondary mb-0">For complex production flows.</p></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'features':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--features mx-auto max-w-6xl px-4 py-16">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Feature highlights for {$brand_name} and {$sector_phrase}.</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Context-aware edits</h3><p class="mt-3 text-sm leading-6 text-base-content/75">The page context stays attached while you iterate.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Inline preview</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Preview and apply without leaving the editor.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Framework safety</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Generated markup respects the active stack rules.</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--features py-5 py-lg-6">
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">Feature highlights for {$brand_name} and {$sector_phrase}.</h2>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Context-aware edits</h3><p class="text-body-secondary mb-0">The page context stays attached while you iterate.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Inline preview</h3><p class="text-body-secondary mb-0">Preview and apply without leaving the editor.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Framework safety</h3><p class="text-body-secondary mb-0">Generated markup respects the active stack rules.</p></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'testimonials':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--testimonials mx-auto max-w-6xl px-4 py-16">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Proof points that make {$brand_name} feel credible for {$sector_phrase}.</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <blockquote class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><p class="text-base leading-7 text-base-content/80">“The first draft already gave us a solid structure to refine.”</p><footer class="mt-4 text-sm font-semibold text-base-content">Product Lead</footer></blockquote>
    <blockquote class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><p class="text-base leading-7 text-base-content/80">“Preview mode made the decision cycle much faster.”</p><footer class="mt-4 text-sm font-semibold text-base-content">Agency Founder</footer></blockquote>
    <blockquote class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><p class="text-base leading-7 text-base-content/80">“We could keep iterating without losing the page context.”</p><footer class="mt-4 text-sm font-semibold text-base-content">Ops Manager</footer></blockquote>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--testimonials py-5 py-lg-6 bg-light">
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">Proof points that make {$brand_name} feel credible for {$sector_phrase}.</h2>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><p class="mb-4 text-body-secondary">“The first draft already gave us a solid structure to refine.”</p><strong>Product Lead</strong></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><p class="mb-4 text-body-secondary">“Preview mode made the decision cycle much faster.”</p><strong>Agency Founder</strong></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><p class="mb-4 text-body-secondary">“We could keep iterating without losing the page context.”</p><strong>Ops Manager</strong></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'cta':
                if ($is_picowind) {
                    return <<<HTML
<section id="contact" class="lcfa-section-starter lcfa-section--cta mx-auto max-w-6xl px-4 py-16">
  <div class="rounded-[2rem] border border-primary/30 bg-primary/10 px-6 py-10 text-center shadow-xl sm:px-10">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-base-content">Ready to move {$brand_name} forward?</h2>
    <p class="mt-4 text-base leading-7 text-base-content/75">Use this CTA block to validate insertion, tone, and next-step prompts while keeping the message {$tone_phrase} for {$sector_phrase}.</p>
    <div class="mt-6">
      <a class="inline-flex items-center justify-center rounded-full bg-primary px-5 py-3 text-sm font-semibold text-primary-content no-underline" href="#contact">Start the conversation</a>
    </div>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section id="contact" class="lcfa-section-starter lcfa-section--cta py-5 py-lg-6">
  <div class="container">
    <div class="rounded-4 bg-primary-subtle p-4 p-lg-5 text-center">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-3">Ready to move {$brand_name} forward?</h2>
      <p class="lead text-body-secondary mb-4">Use this CTA block to validate insertion, tone, and next-step prompts while keeping the message {$tone_phrase} for {$sector_phrase}.</p>
      <a class="btn btn-primary btn-lg" href="#contact">Start the conversation</a>
    </div>
  </div>
</section>
HTML;

            case 'faq':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--faq mx-auto max-w-6xl px-4 py-16">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Questions {$brand_name} should answer for {$sector_phrase}.</h2>
  </div>
  <div class="mt-10 space-y-4">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-lg font-semibold text-base-content">How quickly can we start?</h3><p class="mt-3 text-sm leading-6 text-base-content/75">The first pass is designed to feel {$tone_phrase} and actionable from the first conversation.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-lg font-semibold text-base-content">What is included in the initial scope?</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Enough structure to validate positioning, hierarchy, and next-step conversion.</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--faq py-5 py-lg-6 bg-light">
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">Questions {$brand_name} should answer for {$sector_phrase}.</h2>
    </div>
    <div class="vstack gap-3">
      <div class="card border-0 shadow-sm"><div class="card-body p-4"><h3 class="h5 mb-2">How quickly can we start?</h3><p class="text-body-secondary mb-0">The first pass is designed to feel {$tone_phrase} and actionable from the first conversation.</p></div></div>
      <div class="card border-0 shadow-sm"><div class="card-body p-4"><h3 class="h5 mb-2">What is included in the initial scope?</h3><p class="text-body-secondary mb-0">Enough structure to validate positioning, hierarchy, and next-step conversion.</p></div></div>
    </div>
  </div>
</section>
HTML;

            case 'metrics':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--metrics mx-auto max-w-6xl px-4 py-16">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Fast proof points for {$brand_name}.</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 text-center shadow-xl"><strong class="text-3xl font-semibold text-base-content">3x</strong><p class="mt-3 text-sm text-base-content/75">Sharper qualification for {$sector_phrase}</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 text-center shadow-xl"><strong class="text-3xl font-semibold text-base-content">48h</strong><p class="mt-3 text-sm text-base-content/75">Faster validation cycle with {$tone_phrase} decisions</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 text-center shadow-xl"><strong class="text-3xl font-semibold text-base-content">1 flow</strong><p class="mt-3 text-sm text-base-content/75">Prompt, preview, apply in the same page context</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--metrics py-5 py-lg-6">
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">Fast proof points for {$brand_name}.</h2>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm text-center"><div class="card-body p-4"><strong class="display-6 d-block">3x</strong><p class="text-body-secondary mb-0">Sharper qualification for {$sector_phrase}</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm text-center"><div class="card-body p-4"><strong class="display-6 d-block">48h</strong><p class="text-body-secondary mb-0">Faster validation cycle with {$tone_phrase} decisions</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm text-center"><div class="card-body p-4"><strong class="display-6 d-block">1 flow</strong><p class="text-body-secondary mb-0">Prompt, preview, apply in the same page context</p></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'team':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--team mx-auto max-w-6xl px-4 py-16">
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">The team behind {$brand_name}.</h2>
  </div>
  <div class="mt-10 grid gap-6 md:grid-cols-3">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Strategy lead</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Keeps the {$sector_phrase} narrative {$tone_phrase} and focused.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Delivery lead</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Turns decisions into a clear build path.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Client partner</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Keeps the feedback loop tight from preview to apply.</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--team py-5 py-lg-6">
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">The team behind {$brand_name}.</h2>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Strategy lead</h3><p class="text-body-secondary mb-0">Keeps the {$sector_phrase} narrative {$tone_phrase} and focused.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Delivery lead</h3><p class="text-body-secondary mb-0">Turns decisions into a clear build path.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Client partner</h3><p class="text-body-secondary mb-0">Keeps the feedback loop tight from preview to apply.</p></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'contact':
                if ($is_picowind) {
                    return <<<HTML
<section id="contact" class="lcfa-section-starter lcfa-section--contact mx-auto max-w-6xl px-4 py-16">
  <div class="grid gap-8 rounded-[2rem] border border-base-300 bg-base-100 p-6 shadow-xl lg:grid-cols-[1fr_.9fr]">
    <div>
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
      <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Start a {$tone_phrase} conversation with {$brand_name}.</h2>
      <p class="mt-4 text-base leading-7 text-base-content/75">Use this contact block to validate lower-page conversion for {$sector_phrase}.</p>
    </div>
    <form class="grid gap-3">
      <input class="input input-bordered w-full" type="text" placeholder="Name">
      <input class="input input-bordered w-full" type="email" placeholder="Email">
      <textarea class="textarea textarea-bordered min-h-32 w-full" placeholder="What are you trying to solve?"></textarea>
      <button class="btn btn-primary" type="button">Request a call</button>
    </form>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section id="contact" class="lcfa-section-starter lcfa-section--contact py-5 py-lg-6 bg-light">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-5">
        <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
        <h2 class="display-6 fw-bold mb-3">Start a {$tone_phrase} conversation with {$brand_name}.</h2>
        <p class="lead text-body-secondary mb-0">Use this contact block to validate lower-page conversion for {$sector_phrase}.</p>
      </div>
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 p-lg-5"><div class="row g-3"><div class="col-md-6"><input class="form-control" type="text" placeholder="Name"></div><div class="col-md-6"><input class="form-control" type="email" placeholder="Email"></div><div class="col-12"><textarea class="form-control" rows="5" placeholder="What are you trying to solve?"></textarea></div><div class="col-12"><button class="btn btn-primary" type="button">Request a call</button></div></div></div></div>
      </div>
    </div>
  </div>
</section>
HTML;

            case 'logo_cloud':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--logo-cloud{$layout_class} mx-auto max-w-6xl px-4 py-12"{$visual_attr}>
  <div class="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center">
    <div>
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
      <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Trusted signals for {$brand_name} {$page_context_phrase}.</h2>
      <p class="mt-4 text-base leading-7 text-base-content/75">Use this strip to turn {$prompt_focus} into quick credibility before the next conversion block.</p>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-5 text-center text-sm font-semibold text-base-content/70 shadow-sm">Northstar</div>
      <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-5 text-center text-sm font-semibold text-base-content/70 shadow-sm">Signal Co.</div>
      <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-5 text-center text-sm font-semibold text-base-content/70 shadow-sm">Atlas</div>
      <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-5 text-center text-sm font-semibold text-base-content/70 shadow-sm">Brightline</div>
    </div>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--logo-cloud{$layout_class} py-5"{$visual_attr}>
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-4">
        <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
        <h2 class="h3 fw-bold mb-3">Trusted signals for {$brand_name} {$page_context_phrase}.</h2>
        <p class="text-body-secondary mb-0">Use this strip to turn {$prompt_focus} into quick credibility before the next conversion block.</p>
      </div>
      <div class="col-lg-8">
        <div class="row g-3 row-cols-2 row-cols-md-4 text-center">
          <div class="col"><div class="border rounded-3 bg-white py-4 fw-semibold text-body-secondary">Northstar</div></div>
          <div class="col"><div class="border rounded-3 bg-white py-4 fw-semibold text-body-secondary">Signal Co.</div></div>
          <div class="col"><div class="border rounded-3 bg-white py-4 fw-semibold text-body-secondary">Atlas</div></div>
          <div class="col"><div class="border rounded-3 bg-white py-4 fw-semibold text-body-secondary">Brightline</div></div>
        </div>
      </div>
    </div>
  </div>
</section>
HTML;

            case 'comparison':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--comparison{$layout_class} mx-auto max-w-6xl px-4 py-16"{$visual_attr}>
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">Why {$brand_name} is the clearer path for {$sector_phrase}.</h2>
    <p class="mt-4 text-base leading-7 text-base-content/75">A comparison block helps visitors evaluate {$prompt_focus} without leaving the page.</p>
  </div>
  <div class="mt-10 grid gap-4 md:grid-cols-2">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">Typical path</h3><ul class="mt-4 space-y-3 text-sm leading-6 text-base-content/70"><li>Unclear next step</li><li>Generic page structure</li><li>Slow validation cycle</li></ul></article>
    <article class="rounded-3xl border border-primary/30 bg-primary/10 p-6 shadow-xl"><h3 class="text-xl font-semibold text-base-content">{$brand_name} path</h3><ul class="mt-4 space-y-3 text-sm leading-6 text-base-content/80"><li>{$tone_phrase} decision points</li><li>Context-aware section flow</li><li>Preview before apply</li></ul></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--comparison{$layout_class} py-5 py-lg-6"{$visual_attr}>
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-3">Why {$brand_name} is the clearer path for {$sector_phrase}.</h2>
      <p class="lead text-body-secondary mb-0">A comparison block helps visitors evaluate {$prompt_focus} without leaving the page.</p>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-2">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><h3 class="h4">Typical path</h3><ul class="text-body-secondary mb-0"><li>Unclear next step</li><li>Generic page structure</li><li>Slow validation cycle</li></ul></div></div></div>
      <div class="col"><div class="card h-100 border-primary shadow-sm"><div class="card-body p-4"><h3 class="h4">{$brand_name} path</h3><ul class="text-body-secondary mb-0"><li>{$tone_phrase} decision points</li><li>Context-aware section flow</li><li>Preview before apply</li></ul></div></div></div>
    </div>
  </div>
</section>
HTML;

            case 'timeline':
                if ($is_picowind) {
                    return <<<HTML
<section class="lcfa-section-starter lcfa-section--timeline{$layout_class} mx-auto max-w-6xl px-4 py-16"{$visual_attr}>
  <div class="max-w-3xl">
    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">{$eyebrow}</p>
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-base-content">A {$tone_phrase} path from interest to action {$page_context_phrase}.</h2>
  </div>
  <div class="mt-10 grid gap-4 md:grid-cols-3">
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><span class="text-sm font-semibold text-primary">01</span><h3 class="mt-3 text-xl font-semibold text-base-content">Frame</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Clarify the visitor problem and connect it to {$sector_phrase}.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><span class="text-sm font-semibold text-primary">02</span><h3 class="mt-3 text-xl font-semibold text-base-content">Compare</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Show the main proof points behind {$prompt_focus}.</p></article>
    <article class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl"><span class="text-sm font-semibold text-primary">03</span><h3 class="mt-3 text-xl font-semibold text-base-content">Convert</h3><p class="mt-3 text-sm leading-6 text-base-content/75">Move the visitor toward a clear next step with less friction.</p></article>
  </div>
</section>
HTML;
                }

                return <<<HTML
<section class="lcfa-section-starter lcfa-section--timeline{$layout_class} py-5 py-lg-6"{$visual_attr}>
  <div class="container">
    <div class="mx-auto mb-5" style="max-width:48rem;">
      <p class="text-uppercase fw-semibold small text-primary mb-3">{$eyebrow}</p>
      <h2 class="display-6 fw-bold mb-0">A {$tone_phrase} path from interest to action {$page_context_phrase}.</h2>
    </div>
    <div class="row g-4 row-cols-1 row-cols-md-3">
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><span class="fw-bold text-primary">01</span><h3 class="h4 mt-3">Frame</h3><p class="text-body-secondary mb-0">Clarify the visitor problem and connect it to {$sector_phrase}.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><span class="fw-bold text-primary">02</span><h3 class="h4 mt-3">Compare</h3><p class="text-body-secondary mb-0">Show the main proof points behind {$prompt_focus}.</p></div></div></div>
      <div class="col"><div class="card h-100 border-0 shadow-sm"><div class="card-body p-4"><span class="fw-bold text-primary">03</span><h3 class="h4 mt-3">Convert</h3><p class="text-body-secondary mb-0">Move the visitor toward a clear next step with less friction.</p></div></div></div>
    </div>
  </div>
</section>
HTML;
        }

        return '';
    }

    private function merge_section_starter_into_page(string $existing_html, string $section_html, string $operation, array $payload = []): string {
        $existing_html = trim($existing_html);
        $section_html = trim($section_html);

        if ($section_html === '') {
            return $existing_html;
        }

        if ($existing_html === '') {
            return $section_html;
        }

        if ($operation === 'replace_hero') {
            $replaced_html = $this->replace_detected_hero_section($existing_html, $section_html);

            if ($replaced_html !== '') {
                return $replaced_html;
            }

            $operation = 'prepend';
        }

        if ($operation === 'before_footer') {
            $footer_insert_html = $this->insert_section_before_footer($existing_html, $section_html);

            if ($footer_insert_html !== '') {
                return $footer_insert_html;
            }

            $operation = 'append';
        }

        if ($operation === 'after_selected_section') {
            $selected_insert_html = $this->insert_section_after_selected_anchor($existing_html, $section_html, is_array($payload['selected_section_anchor'] ?? null) ? (array) $payload['selected_section_anchor'] : []);

            if ($selected_insert_html !== '') {
                return $selected_insert_html;
            }

            $operation = 'append';
        }

        if ($operation === 'prepend') {
            if (preg_match('/<main\b[^>]*>/i', $existing_html, $matches, PREG_OFFSET_CAPTURE)) {
                $opening_tag = (string) $matches[0][0];
                $insert_at = (int) $matches[0][1] + strlen($opening_tag);

                return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
            }

            return $section_html . "\n\n" . $existing_html;
        }

        if (preg_match('/<\/main>/i', $existing_html, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_at = (int) $matches[0][1];

            return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
        }

        return $existing_html . "\n\n" . $section_html;
    }

    private function replace_detected_hero_section(string $existing_html, string $section_html): string {
        $patterns = [
            '/<section\b[^>]*class=(["\'])[^"\']*lcfa-section--hero[^"\']*\1[^>]*>.*?<\/section>/is',
            '/<section\b[^>]*id=(["\'])hero\1[^>]*>.*?<\/section>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $existing_html) === 1) {
                return (string) preg_replace($pattern, $section_html, $existing_html, 1);
            }
        }

        return '';
    }

    private function insert_section_before_footer(string $existing_html, string $section_html): string {
        if (preg_match('/<footer\b[^>]*>/i', $existing_html, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_at = (int) $matches[0][1];

            return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
        }

        return '';
    }

    private function insert_section_after_selected_anchor(string $existing_html, string $section_html, array $anchor): string {
        $anchor = $this->sanitize_selected_section_anchor($anchor);

        if (!$anchor) {
            return '';
        }

        $tag_name = (string) ($anchor['tag_name'] ?? 'section');
        $tag_pattern = preg_quote($tag_name, '/');

        if (!empty($anchor['id'])) {
            $id = preg_quote((string) $anchor['id'], '/');
            $matched = $this->insert_after_first_element_match(
                $existing_html,
                $section_html,
                '/<' . $tag_pattern . '\b(?=[^>]*\bid=(["\'])' . $id . '\1)[^>]*>.*?<\/' . $tag_pattern . '>/is'
            );

            if ($matched !== '') {
                return $matched;
            }
        }

        if (!empty($anchor['class_token'])) {
            $matched = $this->insert_after_element_with_class($existing_html, $section_html, $tag_name, (string) $anchor['class_token']);

            if ($matched !== '') {
                return $matched;
            }
        }

        if (isset($anchor['section_index'])) {
            $section_index = (int) $anchor['section_index'];
            if ($section_index >= 0 && preg_match_all('/<section\b[^>]*>.*?<\/section>/is', $existing_html, $matches, PREG_OFFSET_CAPTURE) && isset($matches[0][$section_index])) {
                $match = $matches[0][$section_index];
                $insert_at = (int) $match[1] + strlen((string) $match[0]);

                return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
            }
        }

        return '';
    }

    private function insert_after_element_with_class(string $existing_html, string $section_html, string $tag_name, string $class_token): string {
        $tag_pattern = preg_quote($tag_name, '/');
        $class_token = $this->sanitize_anchor_token($class_token);

        if ($class_token === '') {
            return '';
        }

        if (preg_match_all('/<' . $tag_pattern . '\b[^>]*class=(["\'])([^"\']*)\1[^>]*>.*?<\/' . $tag_pattern . '>/is', $existing_html, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $index => $match) {
                $class_value = (string) ($matches[2][$index][0] ?? '');
                $classes = preg_split('/\s+/', $class_value) ?: [];

                if (!in_array($class_token, $classes, true)) {
                    continue;
                }

                $insert_at = (int) $match[1] + strlen((string) $match[0]);

                return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
            }
        }

        return '';
    }

    private function insert_after_first_element_match(string $existing_html, string $section_html, string $pattern): string {
        if (preg_match($pattern, $existing_html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $match = $matches[0];
        $insert_at = (int) $match[1] + strlen((string) $match[0]);

        return substr($existing_html, 0, $insert_at) . "\n" . $section_html . "\n" . substr($existing_html, $insert_at);
    }

    private function sanitize_selected_section_anchor(array $anchor): array {
        if (!$anchor) {
            return [];
        }

        $tag_name = sanitize_key((string) ($anchor['tag_name'] ?? 'section'));
        if (!in_array($tag_name, ['section', 'header', 'footer', 'article'], true)) {
            $tag_name = 'section';
        }

        $section_index = isset($anchor['section_index']) ? (int) $anchor['section_index'] : -1;

        return array_filter([
            'tag_name'      => $tag_name,
            'id'            => $this->sanitize_anchor_token((string) ($anchor['id'] ?? '')),
            'selector'      => sanitize_text_field((string) ($anchor['selector'] ?? '')),
            'class_token'   => $this->sanitize_anchor_token((string) ($anchor['class_token'] ?? '')),
            'section_index' => $section_index >= 0 ? $section_index : null,
            'source'        => sanitize_key((string) ($anchor['source'] ?? '')),
        ], static function ($value): bool {
            return $value !== '' && $value !== null;
        });
    }

    private function sanitize_anchor_token(string $value): string {
        return substr((string) preg_replace('/[^A-Za-z0-9_\-:.]/', '', $value), 0, 96);
    }

    private function get_visual_reference_context(array $payload): array {
        $visual_reference = is_array($payload['visual_reference'] ?? null) ? (array) $payload['visual_reference'] : [];
        $attachments = is_array($payload['attachments'] ?? null) ? (array) $payload['attachments'] : [];

        if (!$visual_reference && !$attachments) {
            return [];
        }

        $orientation = sanitize_key((string) ($visual_reference['orientation'] ?? ''));
        if ($orientation === '' && $attachments) {
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                $width = absint($attachment['width'] ?? 0);
                $height = absint($attachment['height'] ?? 0);
                if ($width > 0 && $height > 0) {
                    $orientation = $width > $height ? 'landscape' : ($height > $width ? 'portrait' : 'square');
                    break;
                }
            }
        }

        if (!in_array($orientation, ['landscape', 'portrait', 'square', 'unknown'], true)) {
            $orientation = 'unknown';
        }

        $layout = sanitize_key((string) ($visual_reference['layout'] ?? ''));
        if ($layout === '') {
            $layout = $orientation === 'portrait' ? 'stacked-reference' : ($orientation === 'landscape' ? 'split-reference' : 'reference-aware');
        }

        return [
            'enabled'     => true,
            'count'       => max(absint($visual_reference['count'] ?? 0), count($attachments)),
            'orientation' => $orientation,
            'layout'      => $layout,
        ];
    }

    private function resolve_section_layout_profile(string $section_intent, array $payload): string {
        $visual_reference = $this->get_visual_reference_context($payload);

        if (!empty($visual_reference['enabled'])) {
            return sanitize_key((string) ($visual_reference['layout'] ?? 'reference-aware'));
        }

        $prompt = strtolower((string) ($payload['user_prompt'] ?? $payload['prompt'] ?? ''));

        if (strpos($prompt, 'compact') !== false || strpos($prompt, 'dense') !== false || strpos($prompt, 'denso') !== false) {
            return 'compact';
        }

        if (in_array($section_intent, ['comparison', 'timeline', 'logo_cloud'], true)) {
            return $section_intent;
        }

        return '';
    }

    private function summarize_section_prompt(string $prompt, string $section_intent): string {
        $prompt = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($prompt)) ?: '');

        if ($prompt === '') {
            return $section_intent !== ''
                ? sprintf(__('a %s section', 'livecanvas-forge-ai'), str_replace('_', ' ', $section_intent))
                : '';
        }

        $prompt = preg_replace('/^(please|can you|could you|fammi|creami|aggiungi|inserisci|metti|sostituisci)\s+/i', '', $prompt) ?: $prompt;

        if (strlen($prompt) > 90) {
            $prompt = substr($prompt, 0, 87) . '...';
        }

        return sanitize_text_field($prompt);
    }

    private function coerce_multiline_payload(array $payload, string $string_key, string $lines_key): string {
        $string_value = wp_unslash((string) ($payload[$string_key] ?? ''));

        if ($string_value !== '') {
            return $string_value;
        }

        $lines = $payload[$lines_key] ?? null;

        if (!is_array($lines)) {
            return '';
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (is_scalar($line) || $line === null) {
                $normalized[] = wp_unslash((string) $line);
            }
        }

        return implode("\n", $normalized);
    }

    private function wrap_page_footer_script(string $script): string {
        $trimmed = trim($script);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/<script\b/i', $trimmed)) {
            return $trimmed;
        }

        return "<script>\n" . $trimmed . "\n</script>";
    }

    public function evaluate_action_policy_for_rest(string $action, bool $dry_run): array {
        return $this->evaluate_policy($action, $dry_run);
    }

    private function hydrate_target_urls(array &$result, string $target_type, int $target_id): void {
        $result['target_type'] = $target_type;
        $result['target_id']   = $target_id;

        if ($target_type === 'page' || $target_type === 'dynamic_template' || $target_type === 'partial' || $target_type === 'header' || $target_type === 'footer') {
            $result['target_title'] = html_entity_decode(get_the_title($target_id) ?: __('Untitled', 'livecanvas-forge-ai'));
            $result['frontend_url'] = (string) get_permalink($target_id);
            $result['edit_url']     = $this->resolve_edit_post_url($target_id);
        }
    }

    private function resolve_framework(array $payload): string {
        $explicit = sanitize_key((string) ($payload['framework'] ?? ''));

        if (in_array($explicit, ['picostrap', 'picowind'], true)) {
            return $explicit;
        }

        return $this->environment->detect_framework_family();
    }

    private function supports_structured_page_content(string $action): bool {
        return in_array($action, ['validate_markup_for_framework', 'page_upsert', 'create_page', 'update_page'], true);
    }

    private function resolve_livecanvas_page_template(): string {
        $candidates = [
            trailingslashit(get_stylesheet_directory()) . 'page-templates/empty.php',
            trailingslashit(get_template_directory()) . 'page-templates/empty.php',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return 'page-templates/empty.php';
            }
        }

        // LiveCanvas-aware stacks conventionally use this slug even when the current
        // runtime cannot resolve the underlying file path deterministically.
        if ($this->environment->is_livecanvas_active()) {
            return 'page-templates/empty.php';
        }

        return '';
    }

    private function validate_page_markup_for_framework(string $framework, string $content): string {
        $inspection = $this->inspect_page_markup_for_framework($framework, $content);

        return (string) ($inspection['message'] ?? '');
    }

    private function inspect_page_markup_for_framework(string $framework, string $content): array {
        if ($framework !== 'picowind' || trim($content) === '') {
            return [
                'valid'   => true,
                'signals' => [],
                'message' => '',
            ];
        }

        $signals = $this->detect_framework_conflict_signals($content);

        if (count($signals) < 2) {
            return [
                'valid'   => true,
                'signals' => $signals,
                'message' => '',
            ];
        }

        return [
            'valid'   => false,
            'signals' => $signals,
            'message' => __('Picowind is active. Regenerate this page with Tailwind or DaisyUI-compatible markup instead of Bootstrap classes.', 'livecanvas-forge-ai'),
        ];
    }

    private function append_global_shell_framework_warnings(array &$result, string $framework, string $variant): void {
        $inspection = $this->inspect_global_shell_for_framework($framework, $variant);

        if ($inspection['parts'] ?? []) {
            $result['data']['global_shell'] = $inspection;
        }

        foreach ((array) ($inspection['warnings'] ?? []) as $warning) {
            $result['warnings'][] = (string) $warning;
        }
    }

    private function inspect_global_shell_for_framework(string $framework, string $variant): array {
        if ($framework !== 'picowind') {
            return [
                'valid'    => true,
                'variant'  => $variant,
                'parts'    => [],
                'warnings' => [],
            ];
        }

        $parts = [];
        $warnings = [];

        foreach (['header', 'footer'] as $target_type) {
            $target = $this->inventory->get_target_content($target_type, 0, $variant);
            $content = (string) ($target['content'] ?? '');

            if (trim($content) === '') {
                continue;
            }

            $signals = $this->detect_framework_conflict_signals($content);
            $part = [
                'target_type' => $target_type,
                'target_id'   => (int) ($target['post']['id'] ?? 0),
                'title'       => (string) ($target['post']['title'] ?? ''),
                'signals'     => $signals,
                'valid'       => count($signals) < 2,
            ];

            $parts[$target_type] = $part;

            if (empty($part['valid'])) {
                $warnings[] = sprintf(
                    __('Picowind is active, but the global %1$s partial still contains Bootstrap-like markup (%2$s). Run global_shell_apply or site_foundation_run before judging page rendering.', 'livecanvas-forge-ai'),
                    $target_type,
                    implode(', ', array_slice($signals, 0, 5))
                );
            }
        }

        return [
            'valid'    => count($warnings) === 0,
            'variant'  => $variant,
            'parts'    => $parts,
            'warnings' => $warnings,
        ];
    }

    private function detect_framework_conflict_signals(string $content): array {
        $patterns = [
            'row'                    => '/\brow\b/i',
            'col-*'                  => '/\bcol-(?:sm|md|lg|xl|xxl)?-?\d+\b/i',
            'container-fluid'        => '/\bcontainer-fluid\b/i',
            'g-*'                    => '/\bg-\d+\b/i',
            'd-flex'                 => '/\bd-flex\b/i',
            'justify-content-*'      => '/\bjustify-content-[a-z-]+\b/i',
            'align-items-*'          => '/\balign-items-[a-z-]+\b/i',
            'navbar-expand-*'        => '/\bnavbar-expand(?:-[a-z]+)?\b/i',
            'navbar-brand'           => '/\bnavbar-brand\b/i',
            'navbar-toggler'         => '/\bnavbar-toggler\b/i',
            'navbar-collapse'        => '/\bnavbar-collapse\b/i',
            'dropdown-menu'          => '/\bdropdown-menu\b/i',
            'data-bs-*'              => '/\bdata-bs-[a-z-]+\s*=/i',
            '--bs-*'                 => '/--bs-[a-z0-9-]+\s*:/i',
            'bootstrap-icons'        => '/\bbootstrap-icons\b/i',
            'bi bi-*'                => '/\bbi\s+\bbi-[a-z0-9-]+\b/i',
            'btn btn-*'              => '/\bbtn\b[^"\']*\bbtn-[a-z0-9-]+\b/i',
        ];

        $signals = [];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                $signals[] = $label;
            }
        }

        return array_values(array_unique($signals));
    }

    private function resolve_edit_post_url(int $post_id): string {
        $edit_url = (string) get_edit_post_link($post_id, 'raw');

        if ($edit_url !== '') {
            return $edit_url;
        }

        return (string) admin_url(sprintf('post.php?post=%d&action=edit', $post_id));
    }

    private function persist_page_content(array $postarr, bool $is_update) {
        return $this->with_unfiltered_post_content(static function () use ($postarr, $is_update) {
            if ($is_update) {
                unset($postarr['post_type']);
                return wp_update_post($postarr, true);
            }

            unset($postarr['ID']);
            return wp_insert_post($postarr, true);
        });
    }

    private function with_unfiltered_post_content(callable $operation) {
        if (current_user_can('unfiltered_html') || !function_exists('remove_filter') || !function_exists('add_filter')) {
            return $operation();
        }

        $removed_filters = [];

        foreach ($this->get_content_sanitizer_filters() as $filter) {
            [$hook, $callback, $priority, $accepted_args] = $filter;

            if (remove_filter($hook, $callback, $priority)) {
                $removed_filters[] = [$hook, $callback, $priority, $accepted_args];
            }
        }

        try {
            return $operation();
        } finally {
            foreach ($removed_filters as [$hook, $callback, $priority, $accepted_args]) {
                add_filter($hook, $callback, $priority, $accepted_args);
            }
        }
    }

    private function get_content_sanitizer_filters(): array {
        return [
            ['content_save_pre', 'wp_filter_post_kses', 10, 1],
            ['content_filtered_save_pre', 'wp_filter_post_kses', 10, 1],
        ];
    }

    private function evaluate_policy(string $action, bool $dry_run): array {
        $settings = LCFA_Settings::get();
        $profile  = in_array($settings['permission_profile'] ?? '', ['read_only', 'draft_preview', 'confirmed_apply', 'advanced_templates'], true)
            ? (string) $settings['permission_profile']
            : 'advanced_templates';
        $allow_file_fallback = array_key_exists('allow_file_fallback', $settings)
            ? !empty($settings['allow_file_fallback'])
            : true;
        $is_read_action      = $this->is_read_action($action);
        $is_advanced_action  = $this->is_advanced_action($action);
        $is_file_action      = $this->is_file_fallback_action($action);

        if ($profile === 'read_only' && !$is_read_action) {
            return [
                'ok'                  => false,
                'profile'             => $profile,
                'allow_file_fallback' => $allow_file_fallback,
                'force_preview'       => false,
                'notice'              => '',
                'message'             => __('The current permission profile is read_only, so write-intent actions are blocked.', 'livecanvas-forge-ai'),
            ];
        }

        if (!$allow_file_fallback && $is_file_action && !$dry_run) {
            return [
                'ok'                  => true,
                'profile'             => $profile,
                'allow_file_fallback' => false,
                'force_preview'       => true,
                'notice'              => __('File fallback apply was downgraded to preview because theme/PHP fallback is disabled in policy.', 'livecanvas-forge-ai'),
            ];
        }

        if ($profile === 'draft_preview' && !$is_read_action && !$dry_run) {
            return [
                'ok'                  => true,
                'profile'             => $profile,
                'allow_file_fallback' => $allow_file_fallback,
                'force_preview'       => true,
                'notice'              => __('Apply was downgraded to preview because the active policy only allows drafts and previews.', 'livecanvas-forge-ai'),
            ];
        }

        if ($profile === 'confirmed_apply' && $is_advanced_action && !$dry_run) {
            return [
                'ok'                  => true,
                'profile'             => $profile,
                'allow_file_fallback' => $allow_file_fallback,
                'force_preview'       => true,
                'notice'              => __('Advanced template, WindPress, or partial writes were downgraded to preview because the active policy requires the advanced_templates profile for apply.', 'livecanvas-forge-ai'),
            ];
        }

        return [
            'ok'                  => true,
            'profile'             => $profile,
            'allow_file_fallback' => $allow_file_fallback,
            'force_preview'       => false,
            'notice'              => '',
        ];
    }

    private function is_read_action(string $action): bool {
        return in_array($action, [
            'site_audit',
            'site_prepare',
            'windpress_audit',
            'windpress_scan_provider',
            'theme_files_audit',
            'theme_backups_audit',
        ], true);
    }

    private function is_advanced_action(string $action): bool {
        return in_array($action, [
            'site_foundation_run',
            'global_shell_apply',
            'update_header',
            'update_footer',
            'create_dynamic_template',
            'update_dynamic_template',
            'windpress_reset_entry',
            'windpress_store_theme_json',
            'windpress_store_cache_css',
            'build_windpress_cache',
            'windpress_flush_cache',
            'write_theme_template',
            'write_theme_file',
            'restore_theme_backup',
        ], true);
    }

    private function is_file_fallback_action(string $action): bool {
        return in_array($action, [
            'write_theme_template',
            'write_theme_file',
            'restore_theme_backup',
        ], true);
    }
}
