<?php

defined('ABSPATH') || exit;

if (!class_exists('LCFA_Thread_Message_Actions', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-thread-message-actions.php';
}
if (!class_exists('LCFA_Genesis_Executor', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-genesis-executor.php';
}
if (!class_exists('LCFA_Codex_Autorunner', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-codex-autorunner.php';
}

final class LCFA_Rest_Api {
    private const COMMAND_EXECUTION_TRANSIENT_PREFIX = 'lcfa_command_execution_';
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Prompt_Suggester $prompt_suggester;
    private LCFA_Genesis_Planner $genesis_planner;
    private LCFA_Genesis_Executor $genesis_executor;
    private LCFA_Picostrap_Compile_Service $picostrap_compile_service;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory, LCFA_WindPress_Bridge $windpress_bridge, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Local_MCP_Bridge $local_mcp_bridge, LCFA_Context_Builder $context_builder, LCFA_Command_Deck $command_deck, LCFA_Prompt_Suggester $prompt_suggester, LCFA_Genesis_Planner $genesis_planner, ?LCFA_Genesis_Executor $genesis_executor = null) {
        $this->environment        = $environment;
        $this->inventory          = $inventory;
        $this->windpress_bridge   = $windpress_bridge;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->local_mcp_bridge   = $local_mcp_bridge;
        $this->context_builder    = $context_builder;
        $this->command_deck       = $command_deck;
        $this->prompt_suggester   = $prompt_suggester;
        $this->genesis_planner    = $genesis_planner;
        $this->genesis_executor   = $genesis_executor ?: new LCFA_Genesis_Executor($environment, $command_deck);
        $this->picostrap_compile_service = new LCFA_Picostrap_Compile_Service($environment);
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

        register_rest_route('lcfa/v1', '/genesis/execution-plan', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_genesis_execution_plan'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/genesis/execute-next', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'execute_next_genesis_task'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/genesis/execute-task', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'execute_genesis_task'],
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

        register_rest_route('lcfa/v1', '/chat/send', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'send_chat_message'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/chat/thread', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'manage_chat_thread'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/agent/request', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'enqueue_agent_request'],
                'permission_callback' => [$this, 'can_write'],
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_agent_request_status'],
                'permission_callback' => [$this, 'can_read'],
            ],
        ]);

        register_rest_route('lcfa/v1', '/agent/request/complete', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'complete_agent_request'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/agent/request/fail', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'fail_agent_request'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/command', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'run_command'],
            'permission_callback' => [$this, 'can_write'],
        ]);

        register_rest_route('lcfa/v1', '/command/execution', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'enqueue_command_execution'],
                'permission_callback' => [$this, 'can_write'],
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_command_execution_status'],
                'permission_callback' => [$this, 'can_read'],
            ],
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

        register_rest_route('lcfa/v1', '/picostrap/compile-manifest', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_picostrap_compile_manifest'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/picostrap/compile-source', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_picostrap_compile_source'],
            'permission_callback' => [$this, 'can_read'],
        ]);

        register_rest_route('lcfa/v1', '/picostrap/bundle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'store_picostrap_bundle'],
            'permission_callback' => [$this, 'can_write'],
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

    public function get_genesis_execution_plan(): WP_REST_Response {
        return new WP_REST_Response($this->genesis_executor->get_execution_plan());
    }

    public function execute_next_genesis_task(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->genesis_executor->execute_next([
            'dry_run'          => !empty($payload['dry_run']),
            'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
            'thread_id'        => sanitize_key((string) ($payload['thread_id'] ?? 'default')),
            'request_context'  => [
                'thread_id' => sanitize_key((string) ($payload['thread_id'] ?? 'default')),
            ],
            'overrides'        => is_array($payload['overrides'] ?? null) ? $payload['overrides'] : [],
        ]);

        return new WP_REST_Response($result, !empty($result['ok']) ? 200 : 400);
    }

    public function execute_genesis_task(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $task_id = sanitize_key((string) ($payload['task_id'] ?? ''));

        if ($task_id === '') {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => __('A valid Genesis task_id is required.', 'livecanvas-forge-ai'),
            ], 400);
        }

        $result = $this->genesis_executor->execute_task($task_id, [
            'dry_run'          => !empty($payload['dry_run']),
            'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
            'thread_id'        => sanitize_key((string) ($payload['thread_id'] ?? 'default')),
            'request_context'  => [
                'thread_id' => sanitize_key((string) ($payload['thread_id'] ?? 'default')),
            ],
            'overrides'        => is_array($payload['overrides'] ?? null) ? $payload['overrides'] : [],
        ]);

        return new WP_REST_Response($result, !empty($result['ok']) ? 200 : 400);
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
        $payload = $this->get_request_payload($request);
        $payload = $this->add_payload_provenance($payload, $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules'));

        $suggestion = $this->prompt_suggester->suggest($payload);
        $suggestion['provenance'] = $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules');
        $status     = !empty($suggestion['ok']) ? 200 : 400;

        return new WP_REST_Response([
            'suggestion' => $suggestion,
        ], $status);
    }

    public function send_chat_message(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->get_request_payload($request);
        $provenance = $this->get_payload_provenance($payload, 'frontend_bridge', 'forge_local_rules');
        $payload = $this->add_payload_provenance($payload, $provenance);

        $thread_id = LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? 'default'));
        $user_prompt = sanitize_textarea_field((string) ($payload['user_prompt'] ?? $payload['message'] ?? ''));
        $attachments = $this->sanitize_chat_attachments((array) ($payload['attachments'] ?? []));

        if ($user_prompt === '') {
            return new WP_REST_Response([
                'suggestion' => [
                    'ok'      => false,
                    'message' => __('A user prompt is required.', 'livecanvas-forge-ai'),
                ],
                'thread' => $this->decorate_thread(LCFA_Settings::get_thread($thread_id), $thread_id),
            ], 400);
        }

        LCFA_Settings::append_thread_message($thread_id, [
            'role'    => 'user',
            'label'   => __('Request', 'livecanvas-forge-ai'),
            'content' => $user_prompt,
            'meta'    => [
                'action'           => sanitize_key((string) ($payload['action'] ?? '')),
                'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
                'post_id'          => absint($payload['post_id'] ?? 0),
                'context_post_id'  => absint($payload['context_post_id'] ?? 0),
                'target_id'        => absint($payload['target_id'] ?? 0),
                'variant'          => sanitize_text_field((string) ($payload['variant'] ?? '1')),
            ] + $provenance,
            'attachments' => $attachments,
        ]);

        $payload['attachments'] = $attachments;
        $payload['attachment_count'] = count($attachments);
        $suggestion = $this->prompt_suggester->suggest($payload);
        $suggestion['provenance'] = $provenance;
        $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_chat_suggestion_message($suggestion, $payload, $thread_id));
        $thread = $this->decorate_thread($thread, $thread_id);
        $status = !empty($suggestion['ok']) ? 200 : 400;

        return new WP_REST_Response([
            'suggestion' => $suggestion,
            'thread'     => $thread,
        ], $status);
    }

    public function manage_chat_thread(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        $default_thread_id = LCFA_Settings::normalize_thread_id('default');

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $operation = sanitize_key((string) ($payload['operation'] ?? ''));
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? 'default'));
        $title = sanitize_text_field((string) ($payload['title'] ?? ''));

        switch ($operation) {
            case 'create':
                $thread = LCFA_Settings::create_thread($title);
                break;

            case 'duplicate':
                $thread = LCFA_Settings::duplicate_thread($thread_id, $title);
                break;

            case 'rename':
                if ($title === '') {
                    return new WP_REST_Response([
                        'error' => __('Thread rename requires a title.', 'livecanvas-forge-ai'),
                    ], 400);
                }

                $thread = LCFA_Settings::rename_thread($thread_id, $title);
                break;

            case 'clear':
                $thread = LCFA_Settings::clear_thread($thread_id);
                break;

            case 'delete':
                if ($thread_id === $default_thread_id) {
                    return new WP_REST_Response([
                        'error' => __('The default thread cannot be deleted.', 'livecanvas-forge-ai'),
                    ], 400);
                }

                $thread = LCFA_Settings::delete_thread($thread_id);
                break;

            default:
                return new WP_REST_Response([
                    'error' => __('Unsupported thread operation.', 'livecanvas-forge-ai'),
                ], 400);
        }

        $selected_thread_id = $operation === 'delete'
            ? $default_thread_id
            : LCFA_Settings::normalize_thread_id((string) ($thread['id'] ?? $thread_id));

        return new WP_REST_Response([
            'thread'             => $this->decorate_thread($thread, $selected_thread_id),
            'threads'            => $this->get_chat_thread_payloads(),
            'thread_summaries'   => array_values(LCFA_Settings::get_thread_summaries()),
            'selected_thread_id' => $selected_thread_id,
        ]);
    }

    public function enqueue_agent_request(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->get_request_payload($request);
        $connections = LCFA_Settings::get_connections();
        $agent = sanitize_key((string) ($payload['agent'] ?? $connections['preferred_client'] ?? ''));

        if (($connections['connection_status'] ?? '') !== 'ready' || $agent === '') {
            return new WP_REST_Response([
                'error' => __('No verified coding agent is connected yet. Finish the Connections smoke test or use the local Forge fallback.', 'livecanvas-forge-ai'),
            ], 409);
        }

        $payload['agent'] = $agent;
        $payload['_lcfa_origin'] = 'frontend_bridge';
        $payload['_lcfa_transport'] = 'browser_rest';
        $payload['_lcfa_processed_by'] = 'agent_queue';
        $payload['attachments'] = $this->sanitize_chat_attachments((array) ($payload['attachments'] ?? []));

        $agent_request = LCFA_Settings::enqueue_agent_request($payload);
        LCFA_Codex_Autorunner::maybe_spawn($agent_request);
        $agent_request = LCFA_Settings::get_agent_request((string) ($agent_request['id'] ?? '')) ?: $agent_request;
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? $payload['thread_id'] ?? 'default'));

        return new WP_REST_Response([
            'request' => $this->build_agent_request_response($agent_request),
            'thread'  => $this->decorate_thread(LCFA_Settings::get_thread($thread_id), $thread_id),
        ], 202);
    }

    public function get_agent_request_status(WP_REST_Request $request): WP_REST_Response {
        $request_id = sanitize_key((string) ($request->get_param('request_id') ?: $request->get_param('id') ?: ''));
        $claim = in_array((string) $request->get_param('claim'), ['1', 'true', 'yes'], true);
        $agent = sanitize_key((string) ($request->get_param('agent') ?: ''));

        if ($request_id !== '') {
            $agent_request = null;

            if ($claim) {
                $agent_request = LCFA_Settings::claim_agent_request($request_id, $agent);
            }

            if (!is_array($agent_request)) {
                $agent_request = LCFA_Settings::get_agent_request($request_id);
            }

            if (!is_array($agent_request)) {
                return new WP_REST_Response([
                    'error' => __('Agent request not found.', 'livecanvas-forge-ai'),
                ], 404);
            }

            $agent_request = $this->maybe_fail_stale_agent_request($agent_request);
            $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default'));

            return new WP_REST_Response([
                'request' => $this->build_agent_request_response($agent_request),
                'thread'  => $this->decorate_thread(LCFA_Settings::get_thread($thread_id), $thread_id),
                'status'  => $claim && (($agent_request['status'] ?? '') === 'running') ? 'claimed' : sanitize_key((string) ($agent_request['status'] ?? 'queued')),
            ], 200);
        }

        $agent_request = LCFA_Settings::claim_next_agent_request($agent);

        if (!is_array($agent_request)) {
            return new WP_REST_Response([
                'request' => null,
                'status'  => 'empty',
            ], 200);
        }

        return new WP_REST_Response([
            'request' => $this->build_agent_request_response($agent_request),
            'status'  => 'claimed',
        ], 200);
    }

    public function complete_agent_request(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->get_request_payload($request);
        $request_id = sanitize_key((string) ($payload['request_id'] ?? $payload['id'] ?? ''));
        $agent_request = LCFA_Settings::get_agent_request($request_id);

        if (!is_array($agent_request)) {
            return new WP_REST_Response([
                'error' => __('Agent request not found.', 'livecanvas-forge-ai'),
            ], 404);
        }

        $result = $this->normalize_agent_result_payload((array) ($payload['result'] ?? []), $agent_request);
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default'));
        $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_agent_result_message($result, $agent_request));
        $thread = $this->decorate_thread($thread, $thread_id);
        $agent_request = LCFA_Settings::complete_agent_request($request_id, $result, $thread);

        return new WP_REST_Response([
            'request' => $this->build_agent_request_response(is_array($agent_request) ? $agent_request : []),
            'thread'  => $thread,
        ], 200);
    }

    public function fail_agent_request(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->get_request_payload($request);
        $request_id = sanitize_key((string) ($payload['request_id'] ?? $payload['id'] ?? ''));
        $agent_request = LCFA_Settings::get_agent_request($request_id);

        if (!is_array($agent_request)) {
            return new WP_REST_Response([
                'error' => __('Agent request not found.', 'livecanvas-forge-ai'),
            ], 404);
        }

        $message = sanitize_textarea_field((string) ($payload['message'] ?? __('Agent request failed.', 'livecanvas-forge-ai')));
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default'));
        $result = $this->normalize_agent_result_payload([
            'ok'      => false,
            'message' => $message,
            'summary' => $message,
        ], $agent_request);
        $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_agent_result_message($result, $agent_request));
        $thread = $this->decorate_thread($thread, $thread_id);
        $agent_request = LCFA_Settings::fail_agent_request($request_id, $message, $thread);

        return new WP_REST_Response([
            'request' => $this->build_agent_request_response(is_array($agent_request) ? $agent_request : []),
            'thread'  => $thread,
        ], 200);
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
        $payload = $this->get_request_payload($request);
        $payload = $this->add_payload_provenance($payload, $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules'));

        $append_thread = array_key_exists('thread_id', $payload);
        $thread_id = $append_thread
            ? LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? 'default'))
            : '';
        $result = $this->command_deck->execute($payload);
        $thread = null;

        if ($append_thread) {
            $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_command_result_message($result, $payload));
            $thread = $this->decorate_thread($thread, $thread_id);
        }

        $status = !empty($result['ok']) ? 200 : 400;

        $response = [
            'result' => $result,
        ];

        if (is_array($thread)) {
            $response['thread'] = $thread;
        }

        return new WP_REST_Response($response, $status);
    }

    public function enqueue_command_execution(WP_REST_Request $request): WP_REST_Response {
        $payload = $this->get_request_payload($request);
        $payload = $this->add_payload_provenance($payload, $this->get_payload_provenance($payload, 'frontend_bridge', 'forge_local_rules'));
        $record = $this->create_command_execution_record($payload);
        $this->store_command_execution_record($record);

        return new WP_REST_Response([
            'execution' => $this->build_command_execution_response($record),
        ], 202);
    }

    public function get_command_execution_status(WP_REST_Request $request): WP_REST_Response {
        $execution_id = sanitize_key((string) ($request->get_param('execution_id') ?: $request->get_param('id') ?: ''));
        $record = $this->get_command_execution_record($execution_id);

        if (!is_array($record)) {
            return new WP_REST_Response([
                'error' => __('Inline execution not found.', 'livecanvas-forge-ai'),
            ], 404);
        }

        if (($record['status'] ?? 'queued') === 'queued') {
            $record['status'] = 'running';
            $record['started_at'] = current_time('mysql', true);
            $this->store_command_execution_record($record);
            $record = $this->resolve_command_execution_record($record);
        }

        return new WP_REST_Response([
            'execution' => $this->build_command_execution_response($record),
        ], 200);
    }

    private function build_chat_suggestion_message(array $suggestion, array $request_payload = [], string $thread_id = 'default'): array {
        $suggested_payload = is_array($suggestion['suggested_payload'] ?? null) ? $suggestion['suggested_payload'] : [];
        $summary = (string) ($suggestion['summary'] ?? $suggestion['message'] ?? '');
        $provenance = $this->get_payload_provenance($request_payload, 'frontend_bridge', 'forge_local_rules');

        return [
            'role'    => 'suggestion_result',
            'label'   => !empty($suggestion['ok']) ? __('Suggestion ready', 'livecanvas-forge-ai') : __('Suggestion failed', 'livecanvas-forge-ai'),
            'content' => $summary !== '' ? $summary : __('No suggestion summary available.', 'livecanvas-forge-ai'),
            'meta'    => [
                'ok'               => !empty($suggestion['ok']),
                'action'           => sanitize_key((string) ($suggested_payload['action'] ?? '')),
                'execution_target' => sanitize_key((string) ($suggested_payload['execution_target'] ?? '')),
                'confidence'       => sanitize_text_field((string) ($suggestion['confidence'] ?? '')),
                'attachment_count' => absint($request_payload['attachment_count'] ?? 0),
                'warnings'         => array_map('sanitize_text_field', (array) ($suggestion['warnings'] ?? [])),
                'reasons'          => array_map('sanitize_text_field', (array) ($suggestion['reasons'] ?? [])),
            ] + $provenance,
            'actions' => LCFA_Thread_Message_Actions::build_suggestion_actions($suggested_payload, $request_payload, [
                'thread_id' => LCFA_Settings::normalize_thread_id($thread_id),
            ]),
        ];
    }

    private function build_command_result_message(array $result, array $payload): array {
        $lines = [];
        $summary = trim((string) ($result['summary'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));
        $execution_target = sanitize_key((string) ($payload['execution_target'] ?? 'local'));
        $target_type = (string) ($result['target_type'] ?? '');
        $provenance = $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules');

        if ($summary !== '') {
            $lines[] = $summary;
        }

        if ($message !== '' && $message !== $summary) {
            $lines[] = $message;
        }

        if (!empty($result['target_title'])) {
            $lines[] = sprintf(__('Target label: %s', 'livecanvas-forge-ai'), (string) $result['target_title']);
        } elseif (!empty($result['target_type'])) {
            $lines[] = sprintf(__('Target type: %s', 'livecanvas-forge-ai'), (string) $result['target_type']);
        }

        if (!empty($result['data']['backup_file'])) {
            $lines[] = sprintf(__('Backup captured before write: %s', 'livecanvas-forge-ai'), (string) $result['data']['backup_file']);
        }

        if (!empty($result['data']['restored_from_backup']['backup_id'])) {
            $lines[] = sprintf(__('Restored from backup: %s', 'livecanvas-forge-ai'), (string) ($result['data']['restored_from_backup']['backup_id'] ?? ''));
        }

        $message = [
            'role'    => 'tool_result',
            'label'   => !empty($result['ok']) ? __('Execution result', 'livecanvas-forge-ai') : __('Execution error', 'livecanvas-forge-ai'),
            'content' => implode("\n", array_filter($lines)),
            'meta'    => [
                'action'           => (string) ($result['action'] ?? ''),
                'mode'             => (string) ($result['mode'] ?? ''),
                'execution_target' => $execution_target,
                'genesis_task_id'  => sanitize_key((string) ($payload['genesis_task_id'] ?? '')),
                'ok'               => !empty($result['ok']),
                'target_type'      => $target_type,
                'target_id'        => (int) ($result['target_id'] ?? 0),
                'target_title'     => (string) ($result['target_title'] ?? ''),
            ] + $provenance,
            'actions' => LCFA_Thread_Message_Actions::build_result_actions($result, $payload, [
                'thread_id' => LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? '')),
            ]),
        ];

        return LCFA_Thread_Message_Actions::decorate_message($message, [
            'thread_id' => LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? '')),
        ]);
    }

    private function build_agent_request_response(array $agent_request): array {
        if ($agent_request === []) {
            return [];
        }

        return [
            'id'               => sanitize_key((string) ($agent_request['id'] ?? '')),
            'status'           => sanitize_key((string) ($agent_request['status'] ?? 'queued')),
            'agent'            => sanitize_key((string) ($agent_request['agent'] ?? '')),
            'queued_for'       => sanitize_key((string) ($agent_request['queued_for'] ?? '')),
            'thread_id'        => LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default')),
            'user_prompt'      => (string) ($agent_request['user_prompt'] ?? ''),
            'action'           => sanitize_key((string) ($agent_request['action'] ?? '')),
            'execution_target' => sanitize_key((string) ($agent_request['execution_target'] ?? 'local')),
            'post_id'          => absint($agent_request['post_id'] ?? 0),
            'context_post_id'  => absint($agent_request['context_post_id'] ?? 0),
            'target_id'        => absint($agent_request['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($agent_request['variant'] ?? '1')),
            'codex_options'    => LCFA_Settings::sanitize_codex_options((array) ($agent_request['codex_options'] ?? [])),
            'payload'          => is_array($agent_request['payload'] ?? null) ? $agent_request['payload'] : [],
            'attachments'      => is_array($agent_request['attachments'] ?? null) ? $agent_request['attachments'] : [],
            'provenance'       => is_array($agent_request['provenance'] ?? null) ? $agent_request['provenance'] : [],
            'runner'           => is_array($agent_request['runner'] ?? null) ? $agent_request['runner'] : [],
            'created_at'       => sanitize_text_field((string) ($agent_request['created_at'] ?? '')),
            'updated_at'       => sanitize_text_field((string) ($agent_request['updated_at'] ?? '')),
            'claimed_at'       => sanitize_text_field((string) ($agent_request['claimed_at'] ?? '')),
            'completed_at'     => sanitize_text_field((string) ($agent_request['completed_at'] ?? '')),
            'claimed_by'       => sanitize_key((string) ($agent_request['claimed_by'] ?? '')),
            'result'           => is_array($agent_request['result'] ?? null) ? $agent_request['result'] : null,
            'thread'           => is_array($agent_request['thread'] ?? null) ? $agent_request['thread'] : null,
            'error'            => sanitize_textarea_field((string) ($agent_request['error'] ?? '')),
        ];
    }

    private function normalize_agent_result_payload(array $result, array $agent_request): array {
        if (isset($result['result']) && is_array($result['result'])) {
            $result = $result['result'];
        }

        if (!array_key_exists('ok', $result)) {
            $result['ok'] = true;
        }

        $agent = sanitize_key((string) ($agent_request['agent'] ?? 'generic'));
        $processed_by = $agent === 'generic' ? 'generic_mcp' : $agent . '_mcp';

        $result['provenance'] = [
            'origin'       => 'mcp_agent',
            'transport'    => 'mcp_stdio',
            'agent'        => $agent,
            'processed_by' => $processed_by,
            'request_id'   => sanitize_key((string) ($agent_request['id'] ?? '')),
        ];

        return $result;
    }

    private function build_agent_result_message(array $result, array $agent_request): array {
        $payload = is_array($agent_request['payload'] ?? null) ? $agent_request['payload'] : [];
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default'));
        $summary = trim((string) ($result['summary'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));
        $lines = [];

        if ($summary !== '') {
            $lines[] = $summary;
        }

        if ($message !== '' && $message !== $summary) {
            $lines[] = $message;
        }

        if ($lines === []) {
            $lines[] = !empty($result['ok'])
                ? __('Agent completed the frontend request.', 'livecanvas-forge-ai')
                : __('Agent could not complete the frontend request.', 'livecanvas-forge-ai');
        }

        $provenance = is_array($result['provenance'] ?? null) ? $result['provenance'] : [];

        $message_payload = [
            'role'    => 'tool_result',
            'label'   => !empty($result['ok']) ? __('Execution result', 'livecanvas-forge-ai') : __('Execution error', 'livecanvas-forge-ai'),
            'content' => implode("\n", array_filter($lines)),
            'meta'    => [
                'request_id'       => sanitize_key((string) ($agent_request['id'] ?? '')),
                'action'           => sanitize_key((string) ($result['action'] ?? $agent_request['action'] ?? '')),
                'mode'             => sanitize_key((string) ($result['mode'] ?? 'apply')),
                'execution_target' => sanitize_key((string) ($result['execution_target'] ?? $agent_request['execution_target'] ?? 'local')),
                'ok'               => !empty($result['ok']),
                'target_type'      => sanitize_key((string) ($result['target_type'] ?? '')),
                'target_id'        => absint($result['target_id'] ?? $agent_request['target_id'] ?? 0),
                'target_title'     => sanitize_text_field((string) ($result['target_title'] ?? '')),
                'origin'           => sanitize_key((string) ($provenance['origin'] ?? 'mcp_agent')),
                'transport'        => sanitize_key((string) ($provenance['transport'] ?? 'mcp_stdio')),
                'agent'            => sanitize_key((string) ($provenance['agent'] ?? $agent_request['agent'] ?? 'generic')),
                'processed_by'     => sanitize_key((string) ($provenance['processed_by'] ?? $agent_request['queued_for'] ?? 'generic_mcp')),
            ],
            'actions' => LCFA_Thread_Message_Actions::build_result_actions($result, $payload, [
                'thread_id' => $thread_id,
            ]),
        ];

        return LCFA_Thread_Message_Actions::decorate_message($message_payload, [
            'thread_id' => $thread_id,
        ]);
    }

    private function maybe_fail_stale_agent_request(array $agent_request): array {
        if (!LCFA_Codex_Autorunner::is_stale_queued_request($agent_request, time(), LCFA_Codex_Autorunner::get_stale_timeout())) {
            return $agent_request;
        }

        $request_id = sanitize_key((string) ($agent_request['id'] ?? ''));
        if ($request_id === '') {
            return $agent_request;
        }

        $runner = is_array($agent_request['runner'] ?? null) ? $agent_request['runner'] : [];
        $runner['state'] = 'timed_out';
        $runner['reason'] = 'codex_exec_did_not_claim_request';
        $runner['message'] = __('Codex was started, but it did not claim the frontend request before the timeout.', 'livecanvas-forge-ai');
        $runner['updated_at'] = current_time('mysql', true);
        LCFA_Settings::update_agent_request_runner($request_id, $runner);

        $agent_request = LCFA_Settings::get_agent_request($request_id) ?: $agent_request;
        $message = __('Codex was started, but it did not claim the frontend request before the timeout. Re-send the prompt; Forge will launch Codex with local MCP network access.', 'livecanvas-forge-ai');
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($agent_request['thread_id'] ?? 'default'));
        $result = $this->normalize_agent_result_payload([
            'ok'      => false,
            'message' => $message,
            'summary' => $message,
        ], $agent_request);
        $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_agent_result_message($result, $agent_request));
        $thread = $this->decorate_thread($thread, $thread_id);
        $failed_request = LCFA_Settings::fail_agent_request($request_id, $message, $thread);

        return is_array($failed_request) ? $failed_request : $agent_request;
    }

    private function get_request_payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        return is_array($payload) ? $payload : [];
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

        $connections = LCFA_Settings::get_connections();
        $default_client = $origin === 'mcp_agent'
            ? sanitize_key((string) ($connections['preferred_client'] ?? 'codex'))
            : 'forge';
        $client = sanitize_key((string) ($payload['_lcfa_agent'] ?? $payload['agent'] ?? $default_client));
        $allowed_clients = ['forge', 'codex', 'opencode', 'claude', 'cursor', 'generic'];
        if (!in_array($client, $allowed_clients, true)) {
            $client = $default_client !== '' ? $default_client : 'forge';
        }

        $processed_by = sanitize_key((string) ($payload['_lcfa_processed_by'] ?? $payload['processed_by'] ?? $default_processed_by));
        $allowed_processors = ['forge_local_rules', 'agent_queue', 'codex_mcp', 'opencode_mcp', 'claude_mcp', 'cursor_mcp', 'generic_mcp', 'remote_companion'];
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

    private function add_payload_provenance(array $payload, array $provenance): array {
        foreach ($provenance as $key => $value) {
            $payload['_lcfa_' . $key] = $value;
        }

        return $payload;
    }

    private function sanitize_chat_attachments(array $attachments): array {
        $sanitized = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $kind = sanitize_key((string) ($attachment['kind'] ?? ''));
            $mime = sanitize_text_field((string) ($attachment['mime'] ?? ''));
            $data_url = trim((string) ($attachment['data_url'] ?? ''));

            if ($kind !== 'image' || $mime === '' || strpos($mime, 'image/') !== 0 || strpos($data_url, 'data:image/') !== 0) {
                continue;
            }

            if (strlen($data_url) > 500000) {
                continue;
            }

            $sanitized[] = [
                'kind'     => 'image',
                'name'     => sanitize_text_field((string) ($attachment['name'] ?? '')),
                'mime'     => $mime,
                'caption'  => sanitize_text_field((string) ($attachment['caption'] ?? '')),
                'data_url' => $data_url,
                'size'     => absint($attachment['size'] ?? 0),
                'width'    => absint($attachment['width'] ?? 0),
                'height'   => absint($attachment['height'] ?? 0),
                'orientation' => sanitize_key((string) ($attachment['orientation'] ?? '')),
            ];
        }

        return array_slice($sanitized, 0, 2);
    }

    private function create_command_execution_record(array $payload): array {
        $execution_id = sanitize_key('exec-' . strtolower(wp_generate_password(10, false, false)));
        $payload['thread_id'] = LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? 'default'));
        $provenance = $this->get_payload_provenance($payload, 'frontend_bridge', 'forge_local_rules');

        return [
            'id'               => $execution_id,
            'status'           => 'queued',
            'action'           => sanitize_key((string) ($payload['action'] ?? '')),
            'mode'             => !empty($payload['dry_run']) ? 'preview' : 'apply',
            'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
            'thread_id'        => (string) ($payload['thread_id'] ?? 'default'),
            'created_at'       => current_time('mysql', true),
            'provenance'       => $provenance,
            'payload'          => $payload,
        ];
    }

    private function resolve_command_execution_record(array $record): array {
        $payload = is_array($record['payload'] ?? null) ? $record['payload'] : [];
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($record['thread_id'] ?? ($payload['thread_id'] ?? 'default')));

        try {
            $result = $this->command_deck->execute($payload);
            $thread = null;

            if ($thread_id !== '') {
                $thread = LCFA_Settings::append_thread_message($thread_id, $this->build_command_result_message($result, $payload));
                $thread = $this->decorate_thread($thread, $thread_id);
            }

            $record['status'] = !empty($result['ok']) ? 'completed' : 'failed';
            $record['completed_at'] = current_time('mysql', true);
            $record['result'] = $result;

            if (is_array($thread)) {
                $record['thread'] = $thread;
            }
        } catch (Throwable $throwable) {
            $record['status'] = 'failed';
            $record['completed_at'] = current_time('mysql', true);
            $record['result'] = [
                'ok'               => false,
                'action'           => sanitize_key((string) ($payload['action'] ?? '')),
                'mode'             => !empty($payload['dry_run']) ? 'preview' : 'apply',
                'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
                'message'          => $throwable->getMessage(),
                'summary'          => $throwable->getMessage(),
                'warnings'         => [],
                'diff_html'        => '',
                'existing_html'    => '',
                'proposed_html'    => '',
                'data'             => [],
            ];
        }

        $this->store_command_execution_record($record);

        return $record;
    }

    private function build_command_execution_response(array $record): array {
        return [
            'id'               => sanitize_key((string) ($record['id'] ?? '')),
            'status'           => sanitize_key((string) ($record['status'] ?? 'queued')),
            'action'           => sanitize_key((string) ($record['action'] ?? '')),
            'mode'             => sanitize_key((string) ($record['mode'] ?? 'apply')),
            'execution_target' => sanitize_key((string) ($record['execution_target'] ?? 'local')),
            'thread_id'        => LCFA_Settings::normalize_thread_id((string) ($record['thread_id'] ?? 'default')),
            'provenance'       => is_array($record['provenance'] ?? null) ? $record['provenance'] : [],
            'created_at'       => sanitize_text_field((string) ($record['created_at'] ?? '')),
            'started_at'       => sanitize_text_field((string) ($record['started_at'] ?? '')),
            'completed_at'     => sanitize_text_field((string) ($record['completed_at'] ?? '')),
            'result'           => is_array($record['result'] ?? null) ? $record['result'] : null,
            'thread'           => is_array($record['thread'] ?? null) ? $record['thread'] : null,
        ];
    }

    private function get_command_execution_record(string $execution_id): ?array {
        if ($execution_id === '') {
            return null;
        }

        $record = get_transient($this->get_command_execution_transient_key($execution_id));

        return is_array($record) ? $record : null;
    }

    private function store_command_execution_record(array $record): void {
        if (empty($record['id'])) {
            return;
        }

        $expiration = defined('MINUTE_IN_SECONDS') ? (15 * MINUTE_IN_SECONDS) : 900;
        set_transient($this->get_command_execution_transient_key((string) $record['id']), $record, $expiration);
    }

    private function get_command_execution_transient_key(string $execution_id): string {
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        return self::COMMAND_EXECUTION_TRANSIENT_PREFIX . $user_id . '_' . sanitize_key($execution_id);
    }

    private function decorate_thread(array $thread, string $thread_id): array {
        $messages = is_array($thread['messages'] ?? null) ? $thread['messages'] : [];

        $thread['messages'] = array_map(static function ($message) use ($thread_id) {
            return is_array($message)
                ? LCFA_Thread_Message_Actions::decorate_message($message, ['thread_id' => $thread_id])
                : $message;
        }, $messages);

        return $thread;
    }

    private function get_chat_thread_payloads(): array {
        $payloads = [];

        foreach (array_slice(LCFA_Settings::get_thread_summaries(), 0, 8) as $thread_summary) {
            if (!is_array($thread_summary)) {
                continue;
            }

            $thread_id = LCFA_Settings::normalize_thread_id((string) ($thread_summary['id'] ?? 'default'));
            $thread = $this->decorate_thread(LCFA_Settings::get_thread($thread_id), $thread_id);

            $payloads[$thread_id] = [
                'id'       => $thread_id,
                'title'    => (string) ($thread_summary['title'] ?? $thread_id),
                'messages' => array_values(is_array($thread['messages'] ?? null) ? $thread['messages'] : []),
            ];
        }

        return $payloads;
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

    public function get_picostrap_compile_manifest(): WP_REST_Response {
        try {
            $result = $this->picostrap_compile_service->get_manifest();
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'result' => [
                    'ok' => false,
                    'message' => $throwable->getMessage(),
                ],
            ], 400);
        }

        return new WP_REST_Response([
            'result' => $result,
        ], 200);
    }

    public function get_picostrap_compile_source(WP_REST_Request $request): WP_REST_Response {
        try {
            $result = $this->picostrap_compile_service->get_source((string) $request->get_param('import_path'));
        } catch (Throwable $throwable) {
            return new WP_REST_Response([
                'result' => [
                    'ok' => false,
                    'message' => $throwable->getMessage(),
                ],
            ], 400);
        }

        $status = !empty($result['ok']) ? 200 : 404;

        return new WP_REST_Response([
            'result' => $result,
        ], $status);
    }

    public function store_picostrap_bundle(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $css = (string) ($payload['css'] ?? '');

        if ($css === '') {
            return new WP_REST_Response([
                'result' => [
                    'ok' => false,
                    'message' => __('A compiled CSS payload is required.', 'livecanvas-forge-ai'),
                ],
            ], 400);
        }

        $result = $this->picostrap_compile_service->store_bundle($css);
        $status = !empty($result['ok']) ? 200 : 400;

        return new WP_REST_Response([
            'result' => $result,
        ], $status);
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
