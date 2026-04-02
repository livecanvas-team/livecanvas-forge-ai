<?php

defined('ABSPATH') || exit;

final class LCFA_Context_Builder {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private ?LCFA_WindPress_Bridge $windpress_bridge;
    private ?LCFA_Local_MCP_Bridge $local_mcp_bridge;

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
        $target     = null;

        if ($post_id > 0) {
            $target = $this->get_post_context($post_id);

            if (!$post_type && !empty($target['post']['post_type'])) {
                $post_type = $target['post']['post_type'];
            }
        }

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
            'brief'         => LCFA_Settings::get_project_brief(),
            'connections'   => LCFA_Settings::get_public_connections(),
            'mcp'           => $this->get_mcp_status(),
            'windpress'     => $this->get_windpress_context(),
            'output_rules'  => $this->get_output_rules($snapshot['detected_framework']),
            'tool_contract' => $this->get_tool_contract(),
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
            'mcp'          => $context['mcp'],
            'windpress'    => $context['windpress'],
            'output_rules' => $context['output_rules'],
            'acf'          => $context['acf'],
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

        return [
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
    }

    public function get_bootstrap_payload(): array {
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

        return [
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
                'claude-code' => [
                    'label'   => 'Claude Code',
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
    }

    private function get_post_context(int $post_id): ?array {
        $page = $this->get_page_html($post_id);

        if (!$page['post']) {
            return null;
        }

        return $page;
    }

    private function get_output_rules(string $framework): array {
        if ($framework === 'picowind') {
            return [
                'html_only'            => true,
                'css_strategy'         => 'tailwind-utilities',
                'inline_css'           => false,
                'inline_js'            => false,
                'prefer_template_type' => $this->has_latte_templates() ? 'latte' : 'html',
                'notes'                => [
                    __('Use Tailwind utility classes that are compatible with WinPress scanning.', 'livecanvas-forge-ai'),
                    __('Prefer LiveCanvas HTML and Picowind-compatible templates before falling back to PHP.', 'livecanvas-forge-ai'),
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
        if (!$this->windpress_bridge) {
            return [];
        }

        $status = $this->windpress_bridge->get_status();

        if (empty($status['available'])) {
            return $status;
        }

        return [
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
