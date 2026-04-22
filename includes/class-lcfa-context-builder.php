<?php

defined('ABSPATH') || exit;

final class LCFA_Context_Builder {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private ?LCFA_WindPress_Bridge $windpress_bridge;
    private ?LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private ?array $mcp_status_cache = null;
    private ?array $bootstrap_payload_cache = null;
    private ?array $windpress_context_cache = null;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory, ?LCFA_WindPress_Bridge $windpress_bridge = null, ?LCFA_Local_MCP_Bridge $local_mcp_bridge = null) {
        $this->environment      = $environment;
        $this->inventory        = $inventory;
        $this->windpress_bridge = $windpress_bridge;
        $this->local_mcp_bridge = $local_mcp_bridge;
    }

    public function build_context(array $args = []): array {
        $snapshot   = $this->environment->get_snapshot();
        $inventory  = $this->inventory->get_inventory();
        $theme      = wp_get_theme();
        $post_id    = absint($args['post_id'] ?? 0);
        $post_type  = sanitize_key($args['post_type'] ?? '');
        $brief      = LCFA_Settings::get_project_brief();
        $brief_hash = LCFA_Settings::get_project_brief_hash($brief);
        $genesis_plan = LCFA_Settings::get_genesis_plan();
        $genesis_progress = LCFA_Settings::get_genesis_progress();
        $genesis_execution = $this->build_genesis_execution_summary($genesis_plan, $genesis_progress);
        $plan_stack = is_array($genesis_plan['stack'] ?? null) ? $genesis_plan['stack'] : [];
        $target_contexts = $this->build_target_contexts($inventory, $snapshot, $genesis_plan, $genesis_execution);
        $target     = null;

        if ($post_id > 0) {
            $target = $this->get_post_context($post_id);

            if (!$post_type && !empty($target['post']['post_type'])) {
                $post_type = $target['post']['post_type'];
            }
        }

        $target = $this->decorate_current_target($target, $target_contexts);

        if ($post_type === '') {
            $post_type = 'page';
        }

        return [
            'site'          => [
                'name'        => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url'         => home_url('/'),
                'admin_url'   => admin_url(),
                'locale'      => get_locale(),
                'timezone'    => wp_timezone_string(),
            ],
            'stack'         => [
                'theme_name'            => $theme->get('Name'),
                'theme_stylesheet'      => $theme->get_stylesheet(),
                'theme_template'        => $theme->get_template(),
                'stylesheet_directory'  => get_stylesheet_directory(),
                'template_directory'    => get_template_directory(),
                'framework'             => $snapshot['detected_framework'],
                'editor_config'         => $snapshot['framework_slug'],
                'site_mode'             => $snapshot['site_mode'],
                'tangible_available'    => $snapshot['tangible_available'],
                'acf_active'            => $snapshot['acf_active'],
                'woocommerce_active'    => $snapshot['woocommerce_active'],
                'windpress_active'      => $snapshot['windpress_active'],
                'latte_templates'       => $this->has_latte_templates(),
                'template_directory_lc' => $this->get_theme_templates_directory(),
            ],
            'brief'         => $brief,
            'genesis'       => [
                'brief_hash' => $brief_hash,
                'available'  => !empty($genesis_plan),
                'stale'      => !empty($genesis_plan) && (
                    (string) ($genesis_plan['brief_hash'] ?? '') !== $brief_hash
                    || (string) ($plan_stack['framework'] ?? '') !== (string) ($snapshot['detected_framework'] ?? '')
                    || (string) ($plan_stack['theme'] ?? '') !== (string) ($snapshot['current_theme_stylesheet'] ?? '')
                    || (string) ($plan_stack['site_mode'] ?? '') !== (string) ($snapshot['site_mode'] ?? '')
                ),
                'plan'       => $genesis_plan,
                'progress'   => $genesis_progress,
                'execution'  => $genesis_execution,
            ],
            'connections'   => LCFA_Settings::get_public_connections(),
            'mcp'           => $this->get_mcp_status(),
            'windpress'     => $this->get_windpress_context(),
            'output_rules'  => $this->get_output_rules($snapshot['detected_framework']),
            'tool_contract' => $this->get_tool_contract(),
            'target_contexts' => $target_contexts,
            'inventory'     => [
                'summary'           => $inventory['summary'],
                'livecanvas_pages'  => array_slice($inventory['livecanvas_pages'], 0, 20),
                'dynamic_templates' => array_slice($inventory['dynamic_templates'], 0, 20),
                'blocks'            => array_slice($inventory['blocks'], 0, 20),
                'sections'          => array_slice($inventory['sections'], 0, 20),
                'custom_post_types' => $inventory['custom_post_types'],
            ],
            'acf'           => [
                'post_type'      => $post_type,
                'available'      => $snapshot['acf_active'],
                'field_groups'   => $this->get_acf_fields($post_type),
                'supported_types'=> $this->get_acf_supported_post_types(),
            ],
            'current_target' => $target,
        ];
    }

    public function get_theme_context(array $args = []): array {
        $context = $this->build_context($args);

        return [
            'site'         => $context['site'],
            'stack'        => $context['stack'],
            'brief'        => $context['brief'],
            'genesis'      => $context['genesis'],
            'mcp'          => $context['mcp'],
            'windpress'    => $context['windpress'],
            'output_rules' => $context['output_rules'],
            'acf'          => $context['acf'],
            'target_contexts' => $context['target_contexts'],
            'current_target' => $context['current_target'],
        ];
    }

    public function get_page_html(int $post_id): array {
        $post = get_post($post_id);

        if (!$post instanceof WP_Post) {
            return [
                'post'    => null,
                'content' => '',
            ];
        }

        return [
            'post'    => [
                'id'                 => (int) $post->ID,
                'title'              => html_entity_decode(get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai')),
                'slug'               => $post->post_name,
                'post_type'          => $post->post_type,
                'status'             => $post->post_status,
                'livecanvas_enabled' => get_post_meta($post->ID, '_lc_livecanvas_enabled', true) === '1',
                'edit_url'           => get_edit_post_link($post->ID, 'raw'),
                'view_url'           => get_permalink($post->ID),
            ],
            'content' => (string) get_post_field('post_content', $post->ID, 'raw'),
        ];
    }

    private function build_genesis_execution_summary(array $plan, array $progress): array {
        $plan_tasks     = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $progress_tasks = is_array($progress['tasks'] ?? null) ? $progress['tasks'] : [];
        $counts         = [
            'pending'   => 0,
            'previewed' => 0,
            'applied'   => 0,
            'failed'    => 0,
            'total'     => count($plan_tasks),
        ];
        $next_task = null;

        foreach ($plan_tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = sanitize_key((string) ($task['id'] ?? ''));

            if ($task_id === '') {
                continue;
            }

            $status = in_array($progress_tasks[$task_id]['status'] ?? '', ['pending', 'previewed', 'applied', 'failed'], true)
                ? (string) $progress_tasks[$task_id]['status']
                : 'pending';

            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }

            $counts[$status]++;

            if ($next_task === null && $status !== 'applied') {
                $next_task = [
                    'id'              => $task_id,
                    'label'           => sanitize_text_field((string) ($task['label'] ?? '')),
                    'stage'           => sanitize_key((string) ($task['stage'] ?? '')),
                    'status'          => $status,
                    'requires_action' => !empty($task['payload']['action']),
                    'action'          => sanitize_key((string) ($task['payload']['action'] ?? '')),
                ];
            }
        }

        return [
            'counts'       => $counts,
            'next_task'    => $next_task,
            'next_task_id' => sanitize_key((string) ($next_task['id'] ?? '')),
        ];
    }

    private function build_target_contexts(array $inventory, array $snapshot, array $genesis_plan, array $genesis_execution): array {
        $summary = is_array($inventory['summary'] ?? null) ? $inventory['summary'] : [];
        $task_count = count(is_array($genesis_plan['tasks'] ?? null) ? $genesis_plan['tasks'] : []);
        $local_files_supported = (string) ($snapshot['site_mode'] ?? '') === 'local';

        return [
            'page' => [
                'label'          => __('Page', 'livecanvas-forge-ai'),
                'target_type'    => 'page',
                'command_action' => 'page_upsert',
                'count'          => (int) ($summary['pages'] ?? 0),
                'supports_preview' => true,
                'notes'          => [
                    __('Use for full page generation or major page refreshes when the current target is a LiveCanvas-enabled page.', 'livecanvas-forge-ai'),
                ],
            ],
            'header' => [
                'label'          => __('Header partial', 'livecanvas-forge-ai'),
                'target_type'    => 'header',
                'command_action' => 'update_header',
                'count'          => (int) ($summary['headers'] ?? 0),
                'preferred_variant' => '1',
                'supports_preview' => true,
                'notes'          => [
                    __('Targets the LiveCanvas header partial for global navigation and top-of-page chrome.', 'livecanvas-forge-ai'),
                ],
            ],
            'footer' => [
                'label'          => __('Footer partial', 'livecanvas-forge-ai'),
                'target_type'    => 'footer',
                'command_action' => 'update_footer',
                'count'          => (int) ($summary['footers'] ?? 0),
                'preferred_variant' => '1',
                'supports_preview' => true,
                'notes'          => [
                    __('Targets the LiveCanvas footer partial for global closing sections and legal/footer UI.', 'livecanvas-forge-ai'),
                ],
            ],
            'dynamic_template' => [
                'label'          => __('Dynamic template', 'livecanvas-forge-ai'),
                'target_type'    => 'dynamic_template',
                'command_action' => 'update_dynamic_template',
                'count'          => (int) ($summary['dynamic_templates'] ?? 0),
                'supports_preview' => true,
                'notes'          => [
                    __('Use when editing archive, single, or query-driven LiveCanvas templates instead of a static page.', 'livecanvas-forge-ai'),
                ],
            ],
            'theme_file' => [
                'label'          => __('Theme file', 'livecanvas-forge-ai'),
                'target_type'    => 'theme_file',
                'command_action' => 'write_theme_file',
                'available'      => $local_files_supported,
                'roots'          => [
                    'stylesheet' => get_stylesheet_directory(),
                    'template'   => get_template_directory(),
                ],
                'notes'          => [
                    __('Use for local theme files when changes should live outside post content or partials.', 'livecanvas-forge-ai'),
                ],
            ],
            'backup_restore' => [
                'label'          => __('Backup restore', 'livecanvas-forge-ai'),
                'target_type'    => 'backup_restore',
                'command_action' => 'restore_theme_backup',
                'available'      => $local_files_supported,
                'notes'          => [
                    __('Use when recovering a previously backed up theme or template file through the filesystem bridge.', 'livecanvas-forge-ai'),
                ],
            ],
            'genesis_task' => [
                'label'          => __('Genesis task', 'livecanvas-forge-ai'),
                'target_type'    => 'genesis_task',
                'available'      => $task_count > 0,
                'task_count'     => $task_count,
                'next_task_id'   => sanitize_key((string) ($genesis_execution['next_task_id'] ?? '')),
                'next_task'      => is_array($genesis_execution['next_task'] ?? null) ? $genesis_execution['next_task'] : null,
                'counts'         => is_array($genesis_execution['counts'] ?? null) ? $genesis_execution['counts'] : [],
                'notes'          => [
                    __('Use when a request should advance the stored Genesis execution plan instead of targeting a specific page or partial directly.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    public function get_blocks_library(): array {
        $inventory = $this->inventory->get_inventory();

        return [
            'blocks'   => $inventory['blocks'],
            'sections' => $inventory['sections'],
        ];
    }

    public function get_acf_fields(string $post_type = 'page'): array {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $field_groups = acf_get_field_groups();
        $groups       = [];

        foreach ($field_groups as $group) {
            if (!$this->acf_group_matches_post_type($group, $post_type)) {
                continue;
            }

            $fields = acf_get_fields($group['key']);

            $groups[] = [
                'key'    => $group['key'],
                'title'  => $group['title'],
                'fields' => array_map(static function (array $field): array {
                    return [
                        'key'      => $field['key'] ?? '',
                        'name'     => $field['name'] ?? '',
                        'label'    => $field['label'] ?? '',
                        'type'     => $field['type'] ?? '',
                        'required' => !empty($field['required']),
                    ];
                }, is_array($fields) ? $fields : []),
            ];
        }

        return $groups;
    }

    public function get_mcp_status(): array {
        if ($this->mcp_status_cache !== null) {
            return $this->mcp_status_cache;
        }

        $connections = LCFA_Settings::get_connections();
        $snapshot    = $this->environment->get_snapshot();
        $local_bridge_status = $this->local_mcp_bridge
            ? $this->local_mcp_bridge->get_status()
            : [
                'available'       => false,
                'build_available' => false,
                'local_site'      => false,
                'message'         => __('Local MCP bridge is not configured.', 'livecanvas-forge-ai'),
            ];

        $this->mcp_status_cache = [
            'enabled'          => (bool) $connections['mcp_enabled'],
            'host'             => $connections['mcp_host'],
            'port'             => (int) $connections['mcp_port'],
            'endpoint'         => LCFA_Settings::get_mcp_endpoint(),
            'token'            => $connections['mcp_token'],
            'rest_base'        => rest_url('lcfa/v1/'),
            'preferred_client' => $connections['preferred_client'],
            'server_command'   => $connections['mcp_server_command'],
            'filesystem_mode'  => $snapshot['site_mode'] === 'local' ? 'local-theme-access' : 'remote-rest-primary',
            'local_bridge'     => $local_bridge_status,
            'capabilities'     => [
                'rest_context'      => true,
                'rest_commands'     => true,
                'filesystem_bridge' => !empty($local_bridge_status['available']),
                'theme_files'       => !empty($local_bridge_status['available']),
                'tailwind_compile'  => $snapshot['windpress_active'],
                'windpress_bridge'  => $snapshot['windpress_active'],
                'windpress_local_compile' => !empty($local_bridge_status['build_available']),
                'latte_templates'   => $this->has_latte_templates(),
            ],
        ];

        return $this->mcp_status_cache;
    }

    public function get_bootstrap_payload(): array {
        if ($this->bootstrap_payload_cache !== null) {
            return $this->bootstrap_payload_cache;
        }

        $mcp_status  = $this->get_mcp_status();
        $snapshot    = $this->environment->get_snapshot();
        $connections = LCFA_Settings::get_connections();
        $local_mcp_command = 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';

        $common = [
            'site_url'       => home_url('/'),
            'rest_base'      => rest_url('lcfa/v1/'),
            'mcp_endpoint'   => $mcp_status['endpoint'],
            'mcp_token'      => $mcp_status['token'],
            'wp_root'        => untrailingslashit(ABSPATH),
            'framework'      => $snapshot['detected_framework'],
            'theme'          => $snapshot['current_theme_stylesheet'],
            'stylesheet_directory' => get_stylesheet_directory(),
            'template_directory'   => get_template_directory(),
            'transport'      => $connections['transport'],
            'filesystem_mode'=> $mcp_status['filesystem_mode'],
        ];
        $filesystem_env = $common['filesystem_mode'] === 'local-theme-access'
            ? ['LCFA_WP_ROOT=' . $common['wp_root']]
            : [];

        $this->bootstrap_payload_cache = [
            'common' => $common,
            'clients'=> [
                'codex' => [
                    'label'   => 'Codex',
                    'command' => $connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio'),
                    'env'     => array_merge([
                        'LCFA_SITE_URL=' . $common['site_url'],
                        'LCFA_REST_BASE=' . $common['rest_base'],
                        'LCFA_MCP_ENDPOINT=' . $common['mcp_endpoint'],
                        'LCFA_MCP_TOKEN=' . $common['mcp_token'],
                    ], $filesystem_env),
                ],
                'opencode' => [
                    'label'   => 'OpenCode',
                    'command' => $connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=opencode'),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $common['rest_base'],
                        'LCFA_MCP_TOKEN=' . $common['mcp_token'],
                    ], $filesystem_env),
                ],
                'claude' => [
                    'label'   => 'Claude',
                    'command' => $connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=claude'),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $common['rest_base'],
                        'LCFA_MCP_ENDPOINT=' . $common['mcp_endpoint'],
                        'LCFA_MCP_TOKEN=' . $common['mcp_token'],
                    ], $filesystem_env),
                ],
                'cursor' => [
                    'label'   => 'Cursor',
                    'command' => $connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=cursor'),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $common['rest_base'],
                        'LCFA_MCP_TOKEN=' . $common['mcp_token'],
                    ], $filesystem_env),
                ],
            ],
        ];

        $this->bootstrap_payload_cache['clients']['claude-code'] = $this->bootstrap_payload_cache['clients']['claude'];

        return $this->bootstrap_payload_cache;
    }

    private function get_post_context(int $post_id): ?array {
        $page = $this->get_page_html($post_id);

        if (!$page['post']) {
            return null;
        }

        return $page;
    }

    private function decorate_current_target(?array $target, array $target_contexts): ?array {
        if (!is_array($target) || !is_array($target['post'] ?? null)) {
            return $target;
        }

        $target_type = $this->detect_target_type($target);
        $target['target_type'] = $target_type;
        $target['context_pack'] = $target_contexts[$target_type] ?? [
            'label'          => __('Generic target', 'livecanvas-forge-ai'),
            'target_type'    => $target_type,
            'command_action' => '',
            'notes'          => [
                __('No specialized target pack is available for this content type yet. Keep changes conservative.', 'livecanvas-forge-ai'),
            ],
        ];

        return $target;
    }

    private function detect_target_type(array $target): string {
        $post = is_array($target['post'] ?? null) ? $target['post'] : [];
        $post_id = (int) ($post['id'] ?? 0);
        $post_type = sanitize_key((string) ($post['post_type'] ?? ''));

        if ($post_type === 'lc_partial') {
            if ($post_id > 0 && get_post_meta($post_id, 'is_header', true) === '1') {
                return 'header';
            }

            if ($post_id > 0 && get_post_meta($post_id, 'is_footer', true) === '1') {
                return 'footer';
            }

            return 'partial';
        }

        if ($post_type === 'lc_dynamic_template') {
            return 'dynamic_template';
        }

        if ($post_type === 'page') {
            return 'page';
        }

        return $post_type !== '' ? $post_type : 'unknown';
    }

    private function get_output_rules(string $framework): array {
        if ($framework === 'picowind') {
            return [
                'html_only'                      => false,
                'css_strategy'                   => 'tailwind-utilities',
                'inline_css'                     => false,
                'inline_js'                      => true,
                'allow_javascript'               => true,
                'allow_page_level_inline_script' => true,
                'page_level_script_placement'    => 'footer',
                'prefer_daisyui_components'      => true,
                'allow_external_libraries'       => true,
                'external_library_policy'        => 'minimal-stable-needed-only',
                'avoid_frameworks'               => ['bootstrap'],
                'prefer_template_type'           => $this->has_latte_templates() ? 'latte' : 'html',
                'notes'                          => [
                    __('Use Tailwind utility classes that are compatible with WindPress scanning.', 'livecanvas-forge-ai'),
                    __('Prefer DaisyUI components and native utility-first solutions before adding custom JavaScript.', 'livecanvas-forge-ai'),
                    __('JavaScript is allowed when it is necessary for the interaction. Keep page-level scripts small, place them at the end of the page, and move reusable logic into theme files when it grows.', 'livecanvas-forge-ai'),
                    __('External JavaScript libraries are allowed only when they solve a real need. Prefer stable, popular libraries and load them in a non-blocking way.', 'livecanvas-forge-ai'),
                    __('Prefer LiveCanvas HTML and Picowind-compatible templates before falling back to PHP.', 'livecanvas-forge-ai'),
                    __('Do not wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>. LiveCanvas already owns the page shell; return only the sections and containers that belong inside it.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        if ($framework === 'picostrap') {
            return [
                'html_only'            => true,
                'css_strategy'         => 'bootstrap-5',
                'inline_css'           => false,
                'inline_js'            => false,
                'prefer_template_type' => $this->has_latte_templates() ? 'latte' : 'php-html',
                'notes'                => [
                    __('Use Bootstrap 5 classes and LiveCanvas-friendly markup.', 'livecanvas-forge-ai'),
                    __('Prefer clean HTML blocks and partials before introducing custom PHP templates.', 'livecanvas-forge-ai'),
                    __('Do not wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>. LiveCanvas already owns the page shell; return only the sections and containers that belong inside it.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        return [
            'html_only'            => true,
            'css_strategy'         => 'theme-native',
            'inline_css'           => false,
            'inline_js'            => false,
            'prefer_template_type' => 'php-html',
            'notes'                => [
                __('Stack not recognized. Keep changes conservative and target post content first.', 'livecanvas-forge-ai'),
                __('Do not wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>. LiveCanvas already owns the page shell; return only the sections and containers that belong inside it.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    private function get_tool_contract(): array {
        return [
            'read_tools'  => [
                'snapshot',
                'inventory',
                'context',
                'theme_context',
                'genesis_plan',
                'page_html',
                'acf_fields',
                'blocks_library',
                'mcp_status',
                'mcp_bootstrap',
                'windpress_status',
                'windpress_providers',
                'scan_windpress_provider',
                'scan_windpress_provider_full',
                'windpress_volume',
                'windpress_handlers',
                'build_windpress_cache',
                'get_theme_roots',
                'list_theme_files',
                'list_theme_templates',
                'list_twig_templates',
                'list_latte_templates',
                'list_php_templates',
                'read_theme_file',
                'read_template_file',
            ],
            'write_tools' => [
                'command',
                'generate_genesis_plan',
                'rotate_mcp_token',
                'save_windpress_volume_entries',
                'reset_windpress_volume_entry',
                'store_windpress_theme_json',
                'store_windpress_cache_css',
                'flush_windpress_cache',
                'write_theme_file',
                'write_template_file',
            ],
        ];
    }

    private function get_windpress_context(): array {
        if ($this->windpress_context_cache !== null) {
            return $this->windpress_context_cache;
        }

        if (!$this->windpress_bridge) {
            return [];
        }

        $status = $this->windpress_bridge->get_status();

        if (empty($status['available'])) {
            $this->windpress_context_cache = $status;

            return $this->windpress_context_cache;
        }

        $this->windpress_context_cache = [
            'installed'        => $status['installed'],
            'active'           => $status['active'],
            'available'        => $status['available'],
            'version'          => $status['version'] ?? '',
            'tailwind_version' => $status['tailwind_version'] ?? 0,
            'performance_mode' => $status['performance_mode'] ?? 'unknown',
            'cache'            => $status['cache'] ?? [],
            'data'             => $status['data'] ?? [],
            'providers'        => array_slice($status['providers'] ?? [], 0, 20),
            'volume_handlers'  => $status['volume_handlers'] ?? [],
        ];

        return $this->windpress_context_cache;
    }

    private function get_acf_supported_post_types(): array {
        $supported = ['post', 'page'];
        $inventory = $this->inventory->get_inventory();

        foreach ($inventory['custom_post_types'] as $post_type) {
            $supported[] = $post_type['name'];
        }

        return array_values(array_unique($supported));
    }

    private function acf_group_matches_post_type(array $group, string $post_type): bool {
        if (empty($group['location']) || !is_array($group['location'])) {
            return false;
        }

        foreach ($group['location'] as $and_group) {
            $has_post_type_rule = false;
            $matches_group      = true;

            foreach ($and_group as $rule) {
                if (($rule['param'] ?? '') !== 'post_type') {
                    continue;
                }

                $has_post_type_rule = true;
                $operator = $rule['operator'] ?? '==';
                $value    = $rule['value'] ?? '';

                if ($operator === '==' && $value !== $post_type) {
                    $matches_group = false;
                    break;
                }

                if ($operator === '!=' && $value === $post_type) {
                    $matches_group = false;
                    break;
                }
            }

            if ($has_post_type_rule && $matches_group) {
                return true;
            }
        }

        return false;
    }

    private function has_latte_templates(): bool {
        return is_dir($this->get_theme_templates_directory());
    }

    private function get_theme_templates_directory(): string {
        return trailingslashit(get_stylesheet_directory()) . 'templates';
    }
}
