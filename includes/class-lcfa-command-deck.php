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
            )
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
        $content   = wp_unslash((string) ($payload['content'] ?? ''));

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

            case 'design_system_compose':
                $result = array_merge($result, $this->design_system_compose->run($payload));
                break;

            case 'design_system_apply':
                $result = array_merge($result, $this->design_system_apply->run($payload, $dry_run));
                break;

            case 'page_upsert':
            case 'create_page':
            case 'update_page':
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

            case 'update_header':
            case 'update_footer':
                $flag      = $action === 'update_header' ? 'is_header' : 'is_footer';
                $target_id = $this->inventory->resolve_partial_post_id($flag, $variant);

                if (!$target_id) {
                    return $this->error_result(__('The requested partial target was not found.', 'livecanvas-forge-ai'));
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

                $result['target_type']  = 'dynamic_template';
                $result['target_title'] = $title;
                $result['summary']      = sprintf(__('Create dynamic template "%s".', 'livecanvas-forge-ai'), $title);
                $result['diff_html']    = $this->build_diff('', $content);

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
                    $result['message']   = __('Dynamic template created.', 'livecanvas-forge-ai');
                }
                break;

            case 'update_dynamic_template':
                if (!$target_id) {
                    return $this->error_result(__('A dynamic template ID is required.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content('dynamic_template', $target_id);

                if (!$existing['post']) {
                    return $this->error_result(__('The requested dynamic template was not found.', 'livecanvas-forge-ai'));
                }

                $result['target_type']   = 'dynamic_template';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'];
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update dynamic template #%d.', 'livecanvas-forge-ai'), $target_id);

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
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
        ]);

        return $result;
    }

    private function execute_remote(array $payload, bool $dry_run, array $policy): array {
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
        ]);

        return $result;
    }

    private function requires_livecanvas(string $action): bool {
        return !in_array($action, [
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

        return '';
    }

    private function validate_page_markup_for_framework(string $framework, string $content): string {
        if ($framework !== 'picowind' || trim($content) === '') {
            return '';
        }

        $patterns = [
            '/\brow\b/i',
            '/\bcol-(?:sm|md|lg|xl|xxl)?-?\d+\b/i',
            '/\bcontainer-fluid\b/i',
            '/\bg-\d+\b/i',
            '/\bd-flex\b/i',
            '/\bjustify-content-[a-z-]+\b/i',
            '/\balign-items-[a-z-]+\b/i',
            '/\bbtn\b[^"]*\bbtn-[a-z0-9-]+\b/i',
        ];

        $matches = 0;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $matches++;
            }
        }

        if ($matches < 2) {
            return '';
        }

        return __('Picowind is active. Regenerate this page with Tailwind or DaisyUI-compatible markup instead of Bootstrap classes.', 'livecanvas-forge-ai');
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
            : 'draft_preview';
        $allow_file_fallback = !empty($settings['allow_file_fallback']);
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
            'windpress_audit',
            'windpress_scan_provider',
            'theme_files_audit',
            'theme_backups_audit',
        ], true);
    }

    private function is_advanced_action(string $action): bool {
        return in_array($action, [
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
