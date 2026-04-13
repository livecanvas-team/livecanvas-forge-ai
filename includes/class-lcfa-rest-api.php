<?php

defined('ABSPATH') || exit;

final class LCFA_Rest_Api {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Prompt_Suggester $prompt_suggester;
    private LCFA_Genesis_Planner $genesis_planner;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory, LCFA_WindPress_Bridge $windpress_bridge, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Local_MCP_Bridge $local_mcp_bridge, LCFA_Context_Builder $context_builder, LCFA_Command_Deck $command_deck, LCFA_Prompt_Suggester $prompt_suggester, LCFA_Genesis_Planner $genesis_planner) {
        $this->environment        = $environment;
        $this->inventory          = $inventory;
        $this->windpress_bridge   = $windpress_bridge;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->local_mcp_bridge   = $local_mcp_bridge;
        $this->context_builder    = $context_builder;
        $this->command_deck       = $command_deck;
        $this->prompt_suggester   = $prompt_suggester;
        $this->genesis_planner    = $genesis_planner;
    }

    public function hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('lcfa/v1', '/snapshot', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_snapshot'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/inventory', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_inventory'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/context', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_context'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme-context', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_context'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/genesis/plan', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_genesis_plan'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/genesis/plan/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generate_genesis_plan'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/page-html', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_page_html'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/acf-fields', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_acf_fields'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/library/blocks', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_blocks_library'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/roots', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_roots'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/files', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_files'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/templates', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_templates'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/templates/(?P<type>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_templates_by_type'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/file', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_theme_file'],
                'permission_callback' => [$this, 'can_read'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_theme_file'],
                'permission_callback' => [$this, 'can_write'],
            ],
        ]);

        register_rest_route('lcfa/v1', '/theme/template', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_theme_template_file'],
                'permission_callback' => [$this, 'can_read'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_theme_template_file'],
                'permission_callback' => [$this, 'can_write'],
            ],
        ]);

        register_rest_route('lcfa/v1', '/theme/backups', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_backups'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/backup', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_backup'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/theme/backup/restore', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'restore_theme_backup'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_history'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/command/actions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_actions'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/command/suggest', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'suggest_command'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/command', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'run_command'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/mcp/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_mcp_status'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/mcp/local-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_mcp_local_status'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/mcp/bootstrap', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_mcp_bootstrap'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/mcp/workspace-root', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sync_mcp_workspace_root'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/mcp/token', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rotate_mcp_token'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_windpress_status'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/volume', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_windpress_volume'],
                'permission_callback' => [$this, 'can_read'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save_windpress_volume'],
                'permission_callback' => [$this, 'can_write'],
            ],
        ]);

        register_rest_route('lcfa/v1', '/windpress/volume/handlers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_windpress_handlers'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/providers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_windpress_providers'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/providers/scan', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'scan_windpress_provider'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/theme-json', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_windpress_theme_json'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/cache', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_windpress_cache'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/build-local', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'build_windpress_cache_locally'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/cache/flush', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'flush_windpress_cache'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/windpress/volume/reset', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reset_windpress_volume_entry'],
            'permission_callback' => [$this, 'can_write'],
        ]);
    }

    public function get_snapshot(): WP_REST_Response {
        return new WP_REST_Response([
            'snapshot'    => $this->environment->get_snapshot(),
            'connections' => LCFA_Settings::get_public_connections(),
            'mcp'         => $this->context_builder->get_mcp_status(),
        ]);
    }

    public function get_inventory(): WP_REST_Response {
        return new WP_REST_Response([
            'inventory' => $this->inventory->get_inventory(),
        ]);
    }

    public function get_context(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'context' => $this->context_builder->build_context($request->get_params()),
        ]);
    }

    public function get_theme_context(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'theme_context' => $this->context_builder->get_theme_context($request->get_params()),
        ]);
    }

    public function get_genesis_plan(): WP_REST_Response {
        $brief_hash = LCFA_Settings::get_project_brief_hash();
        $plan       = LCFA_Settings::get_genesis_plan();
        $progress   = LCFA_Settings::get_genesis_progress();
        $snapshot   = $this->environment->get_snapshot();
        $plan_stack = is_array($plan['stack'] ?? null) ? $plan['stack'] : [];
        $stale      = !empty($plan) && (
            (string) ($plan['brief_hash'] ?? '') !== $brief_hash
            || (string) ($plan_stack['framework'] ?? '') !== (string) ($snapshot['detected_framework'] ?? '')
            || (string) ($plan_stack['theme'] ?? '') !== (string) ($snapshot['current_theme_stylesheet'] ?? '')
            || (string) ($plan_stack['site_mode'] ?? '') !== (string) ($snapshot['site_mode'] ?? '')
        );

        return new WP_REST_Response([
            'brief_hash' => $brief_hash,
            'available'  => !empty($plan),
            'stale'      => $stale,
            'plan'       => $plan,
            'progress'   => $progress,
        ]);
    }

    public function generate_genesis_plan(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $brief = [];
        if (isset($payload['brief']) && is_array($payload['brief'])) {
            $brief = $payload['brief'];
        } elseif (array_intersect(array_keys($payload), ['project_mode', 'brand_name', 'sector', 'tone', 'logo_status', 'required_pages', 'notes'])) {
            $brief = $payload;
        }

        $plan = $this->genesis_planner->generate($brief ?: null);
        LCFA_Settings::update_genesis_plan($plan);
        LCFA_Settings::clear_genesis_progress();

        return new WP_REST_Response([
            'plan' => $plan,
            'progress' => LCFA_Settings::get_genesis_progress(),
        ]);
    }

    public function get_page_html(WP_REST_Request $request): WP_REST_Response {
        $post_id = absint($request->get_param('post_id'));

        if (!$post_id) {
            return new WP_REST_Response([
                'error' => __('A valid post_id is required.', 'livecanvas-forge-ai'),
            ], 400);
        }

        $page = $this->context_builder->get_page_html($post_id);

        if (!$page['post']) {
            return new WP_REST_Response([
                'error' => __('The requested post was not found.', 'livecanvas-forge-ai'),
            ], 404);
        }

        return new WP_REST_Response([
            'page' => $page,
        ]);
    }

    public function get_acf_fields(WP_REST_Request $request): WP_REST_Response {
        $post_type = sanitize_key($request->get_param('post_type') ?: 'page');

        return new WP_REST_Response([
            'post_type'    => $post_type,
            'field_groups' => $this->context_builder->get_acf_fields($post_type),
        ]);
    }

    public function get_blocks_library(): WP_REST_Response {
        return new WP_REST_Response([
            'library' => $this->context_builder->get_blocks_library(),
        ]);
    }

    public function get_theme_roots(): WP_REST_Response {
        try {
            $roots = $this->theme_files_bridge->get_theme_roots();
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'roots' => $roots,
        ]);
    }

    public function get_theme_files(WP_REST_Request $request): WP_REST_Response {
        try {
            $files = $this->theme_files_bridge->list_files([
                'root_scope' => sanitize_key((string) ($request->get_param('root_scope') ?: 'active')),
                'directory'  => sanitize_text_field((string) ($request->get_param('directory') ?: '')),
                'extensions' => $request->get_param('extension') ?: [],
                'limit'      => absint($request->get_param('limit') ?: 250),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'files' => $files,
        ]);
    }

    public function get_theme_templates(WP_REST_Request $request): WP_REST_Response {
        try {
            $templates = $this->theme_files_bridge->list_templates([
                'root_scope' => sanitize_key((string) ($request->get_param('root_scope') ?: 'active')),
                'limit'      => absint($request->get_param('limit') ?: 250),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'templates' => $templates,
        ]);
    }

    public function get_theme_templates_by_type(WP_REST_Request $request): WP_REST_Response {
        try {
            $templates = $this->theme_files_bridge->list_templates_by_extension(
                sanitize_key((string) $request->get_param('type')),
                [
                    'root_scope' => sanitize_key((string) ($request->get_param('root_scope') ?: 'active')),
                    'limit'      => absint($request->get_param('limit') ?: 250),
                ]
            );
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'templates' => $templates,
        ]);
    }

    public function get_theme_file(WP_REST_Request $request): WP_REST_Response {
        try {
            $file = $this->theme_files_bridge->read_file([
                'root_scope' => sanitize_key((string) ($request->get_param('root_scope') ?: 'active')),
                'path'       => sanitize_text_field((string) ($request->get_param('path') ?: '')),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'file' => $file,
        ]);
    }

    public function get_theme_template_file(WP_REST_Request $request): WP_REST_Response {
        try {
            $file = $this->theme_files_bridge->read_template_file([
                'root_scope' => sanitize_key((string) ($request->get_param('root_scope') ?: 'active')),
                'path'       => sanitize_text_field((string) ($request->get_param('path') ?: '')),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'file' => $file,
        ]);
    }

    public function get_theme_backups(WP_REST_Request $request): WP_REST_Response {
        try {
            $backups = $this->theme_files_bridge->list_backups([
                'path'  => sanitize_text_field((string) ($request->get_param('path') ?: '')),
                'kind'  => sanitize_key((string) ($request->get_param('kind') ?: '')),
                'limit' => absint($request->get_param('limit') ?: 20),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'backups' => $backups,
        ]);
    }

    public function get_theme_backup(WP_REST_Request $request): WP_REST_Response {
        try {
            $backup = $this->theme_files_bridge->read_backup([
                'backup_id' => sanitize_text_field((string) ($request->get_param('backup_id') ?: $request->get_param('id') ?: '')),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'backup' => $backup,
        ]);
    }

    public function get_history(): WP_REST_Response {
        return new WP_REST_Response([
            'history' => LCFA_Settings::get_history(),
        ]);
    }

    public function get_actions(): WP_REST_Response {
        return new WP_REST_Response([
            'actions' => $this->command_deck->get_actions(),
        ]);
    }

    public function suggest_command(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $suggestion = $this->prompt_suggester->suggest($payload);
        $status     = !empty($suggestion['ok']) ? 200 : 400;

        return new WP_REST_Response([
            'suggestion' => $suggestion,
        ], $status);
    }

    public function get_mcp_status(): WP_REST_Response {
        return new WP_REST_Response([
            'mcp' => $this->context_builder->get_mcp_status(),
        ]);
    }

    public function get_mcp_local_status(): WP_REST_Response {
        return new WP_REST_Response([
            'local_mcp' => $this->local_mcp_bridge->get_status(),
        ]);
    }

    public function get_mcp_bootstrap(): WP_REST_Response {
        return new WP_REST_Response([
            'bootstrap' => $this->context_builder->get_bootstrap_payload(),
        ]);
    }

    public function sync_mcp_workspace_root(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $workspace_root = sanitize_text_field((string) ($payload['workspace_root'] ?? ''));
        $snapshot = $this->environment->get_snapshot();

        if (($snapshot['site_mode'] ?? '') !== 'local') {
            return new WP_REST_Response([
                'result' => [
                    'ok'             => false,
                    'updated'        => false,
                    'workspace_root' => '',
                    'message'        => __('Workspace sync is only available when this site is configured as local.', 'livecanvas-forge-ai'),
                ],
            ], 200);
        }

        if ($workspace_root === '' || $this->is_runtime_workspace_root($workspace_root) || !$this->looks_like_absolute_path($workspace_root)) {
            return new WP_REST_Response([
                'result' => [
                    'ok'             => false,
                    'updated'        => false,
                    'workspace_root' => '',
                    'message'        => __('The incoming workspace root is not a usable host path.', 'livecanvas-forge-ai'),
                ],
            ], 200);
        }

        $connections = LCFA_Settings::get_connections();
        $connections['workspace_root'] = $workspace_root;
        $connections['connection_mode'] = 'local';
        LCFA_Settings::update_connections($connections);

        return new WP_REST_Response([
            'result' => [
                'ok'             => true,
                'updated'        => true,
                'workspace_root' => $workspace_root,
                'source'         => sanitize_key((string) ($payload['source'] ?? '')),
                'agent'          => sanitize_key((string) ($payload['agent'] ?? '')),
            ],
        ]);
    }

    public function run_command(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->command_deck->execute($payload);

        $status = !empty($result['ok']) ? 200 : 400;

        return new WP_REST_Response([
            'result' => $result,
        ], $status);
    }

    public function rotate_mcp_token(): WP_REST_Response {
        $connections = LCFA_Settings::rotate_mcp_token();

        return new WP_REST_Response([
            'mcp' => [
                'token'    => $connections['mcp_token'],
                'endpoint' => LCFA_Settings::get_mcp_endpoint(),
            ],
        ]);
    }

    public function get_windpress_status(): WP_REST_Response {
        return new WP_REST_Response([
            'windpress' => $this->windpress_bridge->get_status(),
        ]);
    }

    public function get_windpress_volume(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'volume' => $this->windpress_bridge->get_volume_entries([
                'include_content' => rest_sanitize_boolean($request->get_param('include_content')),
                'handler'         => sanitize_key((string) $request->get_param('handler')),
                'extension'       => sanitize_text_field((string) $request->get_param('extension')),
                'limit'           => absint($request->get_param('limit') ?: 100),
            ]),
        ]);
    }

    public function get_windpress_handlers(): WP_REST_Response {
        return new WP_REST_Response([
            'handlers' => $this->windpress_bridge->get_volume_handlers(),
        ]);
    }

    public function get_windpress_providers(): WP_REST_Response {
        return new WP_REST_Response([
            'providers' => $this->windpress_bridge->get_providers(),
        ]);
    }

    public function save_windpress_volume(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        $entries = is_array($payload['entries'] ?? null) ? $payload['entries'] : [];
        $result  = $this->windpress_bridge->save_volume_entries($entries);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function scan_windpress_provider(WP_REST_Request $request): WP_REST_Response {
        $payload          = $request->get_json_params();
        $provider_id      = sanitize_key((string) ($payload['provider_id'] ?? ''));
        $metadata         = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $decode_contents  = array_key_exists('decode_contents', (array) $payload)
            ? rest_sanitize_boolean($payload['decode_contents'])
            : true;
        $result = $this->windpress_bridge->scan_provider($provider_id, $metadata, $decode_contents);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function save_windpress_theme_json(WP_REST_Request $request): WP_REST_Response {
        $payload    = $request->get_json_params();
        $theme_json = $payload['theme_json'] ?? $payload['data'] ?? '';
        $result     = $this->windpress_bridge->save_theme_json($theme_json);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function save_windpress_cache(WP_REST_Request $request): WP_REST_Response {
        $payload   = $request->get_json_params();
        $css       = (string) ($payload['css'] ?? '');
        $sourcemap = (string) ($payload['sourcemap'] ?? '');
        $full_build= absint($payload['full_build'] ?? 0);
        $result    = $this->windpress_bridge->save_cache_css($css, $sourcemap, $full_build ?: null);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function build_windpress_cache_locally(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $provider_ids = $payload['provider_ids'] ?? [];

        if (is_string($provider_ids)) {
            $provider_ids = array_values(array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', $provider_ids) ?: [])));
        } elseif (is_array($provider_ids)) {
            $provider_ids = array_values(array_filter(array_map(static function ($item): string {
                return sanitize_key((string) $item);
            }, $provider_ids)));
        } else {
            $provider_ids = [];
        }

        $result = $this->local_mcp_bridge->build_windpress_cache([
            'provider_ids' => $provider_ids,
            'kind'         => sanitize_key((string) ($payload['kind'] ?? 'full')),
            'store'        => !array_key_exists('store', $payload) || rest_sanitize_boolean($payload['store']),
            'source_map'   => rest_sanitize_boolean($payload['source_map'] ?? false),
            'max_batches'  => absint($payload['max_batches'] ?? 0),
        ]);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function flush_windpress_cache(): WP_REST_Response {
        $result = $this->windpress_bridge->flush_runtime_cache();

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function reset_windpress_volume_entry(WP_REST_Request $request): WP_REST_Response {
        $payload        = $request->get_json_params();
        $relative_path  = sanitize_text_field((string) ($payload['relative_path'] ?? ''));
        $result         = $this->windpress_bridge->reset_volume_entry($relative_path);

        return new WP_REST_Response([
            'result' => $result,
        ], !empty($result['ok']) ? 200 : 400);
    }

    public function save_theme_file(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $policy = $this->command_deck->evaluate_action_policy_for_rest('write_theme_file', !empty($payload['dry_run']));

        if (empty($policy['ok'])) {
            return new WP_REST_Response([
                'error'  => (string) ($policy['message'] ?? __('This action is blocked by the current policy profile.', 'livecanvas-forge-ai')),
                'policy' => $this->format_policy_result($policy),
            ], 403);
        }

        $effective_dry_run = !empty($policy['force_preview']) ? true : !empty($payload['dry_run']);

        try {
            $result = $this->theme_files_bridge->write_file([
                'root_scope'         => sanitize_key((string) ($payload['root_scope'] ?? 'stylesheet')),
                'path'               => sanitize_text_field((string) ($payload['path'] ?? '')),
                'content'            => wp_unslash((string) ($payload['content'] ?? '')),
                'dry_run'            => $effective_dry_run,
                'create_directories' => !array_key_exists('create_directories', $payload) || !empty($payload['create_directories']),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        $result['policy'] = $this->format_policy_result($policy);

        return new WP_REST_Response([
            'result' => $result,
        ]);
    }

    public function save_theme_template_file(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $policy = $this->command_deck->evaluate_action_policy_for_rest('write_theme_template', !empty($payload['dry_run']));

        if (empty($policy['ok'])) {
            return new WP_REST_Response([
                'error'  => (string) ($policy['message'] ?? __('This action is blocked by the current policy profile.', 'livecanvas-forge-ai')),
                'policy' => $this->format_policy_result($policy),
            ], 403);
        }

        $effective_dry_run = !empty($policy['force_preview']) ? true : !empty($payload['dry_run']);

        try {
            $result = $this->theme_files_bridge->write_template_file([
                'root_scope'         => sanitize_key((string) ($payload['root_scope'] ?? 'stylesheet')),
                'path'               => sanitize_text_field((string) ($payload['path'] ?? '')),
                'content'            => wp_unslash((string) ($payload['content'] ?? '')),
                'dry_run'            => $effective_dry_run,
                'create_directories' => !array_key_exists('create_directories', $payload) || !empty($payload['create_directories']),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        $result['policy'] = $this->format_policy_result($policy);

        return new WP_REST_Response([
            'result' => $result,
        ]);
    }

    public function restore_theme_backup(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        try {
            $result = $this->theme_files_bridge->restore_backup([
                'backup_id'          => sanitize_text_field((string) ($payload['backup_id'] ?? $payload['id'] ?? '')),
                'root_scope'         => sanitize_key((string) ($payload['root_scope'] ?? '')),
                'path'               => sanitize_text_field((string) ($payload['path'] ?? '')),
                'dry_run'            => !empty($payload['dry_run']),
                'create_directories' => !array_key_exists('create_directories', $payload) || !empty($payload['create_directories']),
            ]);
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'error' => $throwable->getMessage(),
            ], 400);
        }

        return new WP_REST_Response([
            'result' => $result,
        ]);
    }

    public function can_read(?WP_REST_Request $request = null): bool {
        return current_user_can('edit_pages') || $this->has_valid_mcp_token($request);
    }

    public function can_write(?WP_REST_Request $request = null): bool {
        return current_user_can('edit_pages') || $this->has_valid_mcp_token($request);
    }

    public function can_manage(?WP_REST_Request $request = null): bool {
        return current_user_can('manage_options');
    }

    private function has_valid_mcp_token(?WP_REST_Request $request = null): bool {
        if (!$request instanceof WP_REST_Request) {
            return false;
        }

        $token = (string) $request->get_header('x-lcfa-mcp-token');

        if ($token === '') {
            $authorization = trim((string) $request->get_header('authorization'));

            if (stripos($authorization, 'Bearer ') === 0) {
                $token = trim(substr($authorization, 7));
            }
        }

        if ($token === '') {
            $token = sanitize_text_field((string) $request->get_param('mcp_token'));
        }

        if ($token === '') {
            return false;
        }

        $connections = LCFA_Settings::get_connections();

        return $connections['mcp_token'] !== '' && hash_equals($connections['mcp_token'], $token);
    }

    private function looks_like_absolute_path(string $path): bool {
        if ($path === '') {
            return false;
        }

        return (bool) preg_match('#^(?:/|[A-Za-z]:[\\\\/])#', $path);
    }

    private function is_runtime_workspace_root(string $path): bool {
        $path = wp_normalize_path(untrailingslashit($path));

        return in_array($path, [
            '/wordpress',
            '/app',
            '/app/public',
            '/var/www',
            '/var/www/html',
            '/srv/www',
            '/srv/www/html',
            '/usr/share/nginx/html',
        ], true);
    }

    private function format_policy_result(array $policy): array {
        return [
            'profile'             => (string) ($policy['profile'] ?? ''),
            'allow_file_fallback' => !empty($policy['allow_file_fallback']),
            'force_preview'       => !empty($policy['force_preview']),
            'notice'              => (string) ($policy['notice'] ?? ''),
        ];
    }
}
