<?php

defined('ABSPATH') || exit;

if (!class_exists('LCFA_Thread_Message_Actions', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-thread-message-actions.php';
}
if (!class_exists('LCFA_Genesis_Executor', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-genesis-executor.php';
}
if (!class_exists('LCFA_Direct_Agent_Onboarding', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-direct-agent-onboarding.php';
}
if (!class_exists('LCFA_Power_Mode', false) && defined('LCFA_DIR')) {
    require_once LCFA_DIR . 'includes/class-lcfa-power-mode.php';
}

final class LCFA_Admin {
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
    private LCFA_Inventory $inventory;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Connection_Tester $connection_tester;
    private LCFA_Remote_Client $remote_client;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Connection_Onboarding $connection_onboarding;
    private ?LCFA_Direct_Agent_Onboarding $direct_agent_onboarding = null;
    private ?LCFA_Power_Mode $power_mode = null;
    private LCFA_Connection_Wizard_Presenter $connection_wizard_presenter;
    private LCFA_Admin_Hero_Presenter $admin_hero_presenter;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Prompt_Suggester $prompt_suggester;
    private LCFA_Genesis_Planner $genesis_planner;
    private ?LCFA_Genesis_Executor $genesis_executor = null;
    private ?LCFA_Ability_Registry $ability_registry = null;

    public function __construct(LCFA_Environment $environment, LCFA_Installer $installer, LCFA_Inventory $inventory, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Connection_Tester $connection_tester, LCFA_Remote_Client $remote_client, LCFA_Context_Builder $context_builder, LCFA_Connection_Onboarding $connection_onboarding, LCFA_Command_Deck $command_deck, LCFA_Prompt_Suggester $prompt_suggester, LCFA_Genesis_Planner $genesis_planner, ?LCFA_Genesis_Executor $genesis_executor = null, ?LCFA_Ability_Registry $ability_registry = null) {
        $this->environment  = $environment;
        $this->installer    = $installer;
        $this->inventory    = $inventory;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->connection_tester = $connection_tester;
        $this->remote_client = $remote_client;
        $this->context_builder = $context_builder;
        $this->connection_onboarding = $connection_onboarding;
        $this->direct_agent_onboarding = new LCFA_Direct_Agent_Onboarding();
        $this->power_mode = new LCFA_Power_Mode();
        $this->connection_wizard_presenter = new LCFA_Connection_Wizard_Presenter();
        $this->admin_hero_presenter = new LCFA_Admin_Hero_Presenter();
        $this->command_deck = $command_deck;
        $this->prompt_suggester = $prompt_suggester;
        $this->genesis_planner = $genesis_planner;
        $this->genesis_executor = $genesis_executor;
        $this->ability_registry = $ability_registry;
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menus'], 20);
        add_action('current_screen', [$this, 'suppress_external_admin_notices'], 0);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('wp_ajax_lcfa_connections_secondary', [$this, 'handle_connections_secondary_ajax']);
        add_action('admin_post_lcfa_setup', [$this, 'handle_setup_post']);
        add_action('admin_post_lcfa_reset_setup', [$this, 'handle_reset_setup_post']);
        add_action('admin_post_lcfa_connections', [$this, 'handle_connections_post']);
        add_action('admin_post_lcfa_test_connections', [$this, 'handle_connection_test_post']);
        add_action('admin_post_lcfa_install_client_bundle', [$this, 'handle_install_client_bundle_post']);
        add_action('admin_post_lcfa_download_client_bundle', [$this, 'handle_download_client_bundle_post']);
        add_action('admin_post_lcfa_repair_codex_connection', [$this, 'handle_repair_codex_connection_post']);
        add_action('admin_post_lcfa_reconfigure_connection', [$this, 'handle_reconfigure_connection_post']);
        add_action('admin_post_lcfa_resolve_framework_change_connection', [$this, 'handle_framework_change_connection_post']);
        add_action('admin_post_lcfa_project_brief', [$this, 'handle_project_brief_post']);
        add_action('admin_post_lcfa_generate_plan', [$this, 'handle_generate_plan_post']);
        add_action('admin_post_lcfa_genesis_execute', [$this, 'handle_genesis_execute_post']);
        add_action('admin_post_lcfa_create_thread', [$this, 'handle_create_thread_post']);
        add_action('admin_post_lcfa_command', [$this, 'handle_command_post']);
        add_action('lc_editor_header', [$this, 'render_editor_bridge_styles']);
        add_action('lc_editor_before_body_closing', [$this, 'render_editor_bridge']);
    }

    public function suppress_external_admin_notices($screen = null): void {
        if (!$this->is_forge_dashboard_request()) {
            return;
        }

        $hooks = [
            'network_admin_notices',
            'user_admin_notices',
            'admin_notices',
            'all_admin_notices',
        ];

        $screen_id = '';
        if (is_object($screen) && isset($screen->id) && is_scalar($screen->id)) {
            $screen_id = sanitize_key((string) $screen->id);
        }

        if ($screen_id !== '') {
            $hooks[] = $screen_id . '_admin_notices';
        }

        foreach ($hooks as $hook) {
            remove_all_actions($hook);
        }
    }

    public function register_menus(): void {
        $parent_slug = $this->environment->get_livecanvas_menu_slug();
        $admin_capability = 'manage_options';

        if ($parent_slug) {
            add_submenu_page(
                $parent_slug,
                __('AI Bridge', 'livecanvas-forge-ai'),
                __('AI Bridge', 'livecanvas-forge-ai'),
                $admin_capability,
                'lcfa-dashboard',
                [$this, 'render_dashboard_page']
            );

            return;
        }

        add_menu_page(
            __('LiveCanvas AI Bridge', 'livecanvas-forge-ai'),
            __('AI Bridge', 'livecanvas-forge-ai'),
            $admin_capability,
            'lcfa-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-superhero-alt',
            59
        );

    }

    public function enqueue_assets(string $hook_suffix): void {
        if (strpos($hook_suffix, 'lcfa-') === false) {
            return;
        }

        wp_enqueue_style(
            'lcfa-prism',
            LCFA_URL . 'assets/vendor/prism/prism-tomorrow.min.css',
            [],
            '1.29.0'
        );

        wp_enqueue_style(
            'lcfa-admin',
            LCFA_URL . 'assets/admin.css',
            ['lcfa-prism'],
            LCFA_VERSION
        );

        wp_enqueue_script(
            'lcfa-prism-core',
            LCFA_URL . 'assets/vendor/prism/prism-core.min.js',
            [],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-prism-markup',
            LCFA_URL . 'assets/vendor/prism/prism-markup.min.js',
            ['lcfa-prism-core'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-prism-clike',
            LCFA_URL . 'assets/vendor/prism/prism-clike.min.js',
            ['lcfa-prism-core'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-prism-javascript',
            LCFA_URL . 'assets/vendor/prism/prism-javascript.min.js',
            ['lcfa-prism-clike'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-prism-bash',
            LCFA_URL . 'assets/vendor/prism/prism-bash.min.js',
            ['lcfa-prism-core'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-prism-json',
            LCFA_URL . 'assets/vendor/prism/prism-json.min.js',
            ['lcfa-prism-javascript'],
            '1.29.0',
            true
        );

        wp_enqueue_script(
            'lcfa-admin-script',
            LCFA_URL . 'assets/admin.js',
            ['lcfa-prism-markup', 'lcfa-prism-bash', 'lcfa-prism-json'],
            LCFA_VERSION,
            true
        );

        wp_enqueue_script(
            'lcfa-studio-app',
            LCFA_URL . 'assets/studio-app.js',
            ['wp-element', 'wp-api-fetch'],
            LCFA_VERSION,
            true
        );

        wp_localize_script('lcfa-admin-script', 'lcfaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('lcfa/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'studioEndpoint' => rest_url('lcfa/v1/studio'),
            'connectionsSecondaryAction' => 'lcfa_connections_secondary',
            'connectionsSecondaryNonce' => wp_create_nonce('lcfa_connections_secondary'),
            'labels' => [
                'loading' => __('Loading connection details…', 'livecanvas-forge-ai'),
                'loadFailed' => __('Failed to load connection details. Refresh the page or try again in a moment.', 'livecanvas-forge-ai'),
                'studioNoAbilities' => __('No abilities match the current filters.', 'livecanvas-forge-ai'),
                'studioNoRuns' => __('No runs match the current filters.', 'livecanvas-forge-ai'),
            ],
        ]);

        wp_localize_script('lcfa-studio-app', 'lcfaStudio', [
            'endpoint' => rest_url('lcfa/v1/studio'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'links'    => [
                'connections' => admin_url('admin.php?page=lcfa-dashboard&tab=connections'),
                'command'     => admin_url('admin.php?page=lcfa-dashboard&tab=command'),
            ],
            'labels'   => [
                'loading' => __('Loading AI Studio…', 'livecanvas-forge-ai'),
                'loadFailed' => __('AI Studio app could not load. The PHP fallback remains available below.', 'livecanvas-forge-ai'),
                'retry' => __('Retry', 'livecanvas-forge-ai'),
                'emptyAbilities' => __('No abilities match the current filters.', 'livecanvas-forge-ai'),
                'emptyRuns' => __('No runs match the current filters.', 'livecanvas-forge-ai'),
            ],
        ]);
    }

    public function maybe_redirect_after_activation(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!get_option(LCFA_Settings::REDIRECT_OPTION_KEY)) {
            return;
        }

        delete_option(LCFA_Settings::REDIRECT_OPTION_KEY);

        if (wp_doing_ajax() || isset($_GET['activate-multi'])) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup'));
        exit;
    }

    public function handle_connections_secondary_ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'livecanvas-forge-ai'),
            ], 403);
        }

        check_ajax_referer('lcfa_connections_secondary', 'nonce');

        $settings         = LCFA_Settings::get();
        $connections      = LCFA_Settings::get_connections();
        $preferred_client = $this->normalize_connection_client((string) ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex')));
        $workspace_root   = (string) ($connections['workspace_root'] ?? '');
        $mcp_status       = $this->context_builder->get_mcp_status();
        $mcp_bootstrap    = $this->context_builder->get_bootstrap_payload();
        $remote_status    = $this->remote_client->get_status();
        $preferred_bootstrap = $mcp_bootstrap['clients'][$preferred_client] ?? ($mcp_bootstrap['clients']['codex'] ?? ['command' => '', 'env' => []]);
        $command_example  = wp_json_encode([
            'action'  => 'site_audit',
            'dry_run' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        ob_start();
        $this->render_remote_companion_card($remote_status);
        $remote_html = (string) ob_get_clean();

        ob_start();
        $this->render_advanced_connection_settings(
            $connections,
            $preferred_client,
            $mcp_status,
            $preferred_bootstrap,
            $this->build_common_bootstrap_block($mcp_bootstrap),
            $command_example,
            $workspace_root
        );
        $advanced_html = (string) ob_get_clean();

        ob_start();
        $this->render_ability_diagnostics_card($this->get_ability_diagnostics_for_admin());
        $diagnostics_html = (string) ob_get_clean();

        wp_send_json_success([
            'panels' => [
                'remote'      => $remote_html,
                'advanced'    => $advanced_html,
                'diagnostics' => $diagnostics_html,
            ],
        ]);
    }

    public function render_editor_bridge_styles(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $asset_root    = defined('LCFA_DIR') ? LCFA_DIR : dirname(__DIR__) . '/';
        $asset_version = rawurlencode((string) LCFA_VERSION . '-' . (string) max(
            file_exists($asset_root . 'assets/editor-chat.css') ? (int) filemtime($asset_root . 'assets/editor-chat.css') : 0,
            file_exists($asset_root . 'assets/editor-chat.js') ? (int) filemtime($asset_root . 'assets/editor-chat.js') : 0
        ));
        $css_url       = add_query_arg(['ver' => $asset_version], LCFA_URL . 'assets/editor-chat.css');
        $js_url        = add_query_arg(['ver' => $asset_version], LCFA_URL . 'assets/editor-chat.js');

        echo '<link rel="stylesheet" id="lcfa-editor-chat-css" href="' . esc_url($css_url) . '">';
        echo '<script id="lcfa-editor-chat-js" src="' . esc_url($js_url) . '" defer></script>';
    }

    public function render_editor_bridge(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $post;

        if (!$post instanceof WP_Post) {
            return;
        }

        $snapshot = $this->environment->get_snapshot();
        $context = $this->get_command_context_for_post($post);
        $actions = $this->get_editor_bridge_actions($post, $context);
        $thread_summaries = array_slice(LCFA_Settings::get_thread_summaries(), 0, 8);
        $current_thread_id = LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default'));
        $thread_payloads = [];
        $current_thread_messages = [];

        foreach ($thread_summaries as $thread_summary) {
            if (!is_array($thread_summary)) {
                continue;
            }

            $thread_id = LCFA_Settings::normalize_thread_id((string) ($thread_summary['id'] ?? 'default'));
            $thread = LCFA_Settings::get_thread($thread_id);
            $thread_messages = array_map(function ($message) use ($thread_id) {
                return is_array($message)
                    ? LCFA_Thread_Message_Actions::decorate_message($message, [
                        'thread_id' => $thread_id,
                        'command_url_builder' => [$this, 'get_command_url'],
                    ])
                    : $message;
            }, array_slice((array) ($thread['messages'] ?? []), -8));

            $thread_payloads[$thread_id] = [
                'id'       => $thread_id,
                'title'    => (string) ($thread_summary['title'] ?? $thread_id),
                'state'    => $this->get_editor_initial_conversation_state($thread_messages),
                'messages' => $thread_messages,
            ];

            if ($thread_id === $current_thread_id) {
                $current_thread_messages = $thread_messages;
            }
        }

        if ($current_thread_messages === []) {
            $current_thread = LCFA_Settings::get_thread($current_thread_id);
            $current_thread_messages = array_map(function ($message) use ($current_thread_id) {
                return is_array($message)
                    ? LCFA_Thread_Message_Actions::decorate_message($message, [
                        'thread_id' => $current_thread_id,
                        'command_url_builder' => [$this, 'get_command_url'],
                    ])
                    : $message;
            }, array_slice((array) ($current_thread['messages'] ?? []), -8));
            $thread_payloads[$current_thread_id] = [
                'id'       => $current_thread_id,
                'title'    => $current_thread_id,
                'state'    => $this->get_editor_initial_conversation_state($current_thread_messages),
                'messages' => $current_thread_messages,
            ];
        }

        $initial_conversation_state = $this->get_editor_initial_conversation_state($current_thread_messages);
        $initial_conversation_label = $this->get_editor_conversation_state_label($initial_conversation_state);
        $remote_status              = $this->remote_client->get_status();
        $connections                = LCFA_Settings::get_connections();
        $connected_client           = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        $connection_state           = (($connections['connection_status'] ?? '') === 'ready') ? 'connected' : 'disconnected';
        $connection_icon_client     = $connected_client !== '' ? $connected_client : 'generic';
        $agent_processor            = $connected_client === 'generic' ? 'generic_mcp' : ($connected_client !== '' ? $connected_client . '_mcp' : 'forge_local_rules');
        $default_action             = !empty($context['action']) ? (string) $context['action'] : 'site_audit';
        $codex_runtime_defaults     = LCFA_Settings::sanitize_codex_options([
            'model'            => $connections['codex_model'] ?? '',
            'speed'            => $connections['codex_speed'] ?? '',
            'reasoning_effort' => $connections['codex_reasoning_effort'] ?? '',
            'sandbox'          => $connections['codex_sandbox'] ?? '',
        ]);
        $command_base_url           = $this->get_command_url([
            'post_id'   => $post->ID,
            'thread_id' => $current_thread_id,
        ]);
        $editor_config = [
            'restEndpoint' => rest_url('lcfa/v1/chat/send'),
            'threadEndpoint' => rest_url('lcfa/v1/chat/thread'),
            'agentRequestEndpoint' => rest_url('lcfa/v1/agent/request'),
            'commandEndpoint' => rest_url('lcfa/v1/command'),
            'commandExecutionEndpoint' => rest_url('lcfa/v1/command/execution'),
            'restNonce'    => wp_create_nonce('wp_rest'),
            'commandBaseUrl' => $command_base_url,
            'agent'        => [
                'client'       => $connected_client,
                'state'        => $connection_state,
                'enabled'      => $connection_state === 'connected' && $connected_client !== '',
                'processor'    => $agent_processor,
                'displayLabel' => $connected_client !== '' ? ucfirst(str_replace(['-', '_'], ' ', $connected_client)) : __('AI Bridge', 'livecanvas-forge-ai'),
            ],
            'codex'       => [
                'enabled'   => $connected_client === 'codex',
                'defaults'  => $codex_runtime_defaults,
                'models'    => LCFA_Settings::get_codex_model_options(),
                'speed'     => LCFA_Settings::get_codex_speed_options(),
                'reasoning' => LCFA_Settings::get_codex_reasoning_effort_options(),
                'sandbox'   => LCFA_Settings::get_codex_sandbox_options(),
            ],
            'postId'       => (int) $post->ID,
            'targetId'     => (int) ($context['target_id'] ?? 0),
            'variant'      => (string) ($context['variant'] ?? '1'),
            'defaultAction'=> $default_action,
            'threadId'     => $current_thread_id,
            'threads'      => $thread_payloads,
            'threadSummaries' => array_values($thread_summaries),
            'labels'       => [
                'requestRequired' => __('Write a request first so AI Bridge can suggest an action.', 'livecanvas-forge-ai'),
                'analysisFailed'  => __('The request analysis failed.', 'livecanvas-forge-ai'),
                'whySuggested'    => __('Why this was suggested', 'livecanvas-forge-ai'),
                'warnings'        => __('Warnings', 'livecanvas-forge-ai'),
                'recommendedWorkflow' => __('Recommended workflow', 'livecanvas-forge-ai'),
                'recommendedPreflight' => __('Recommended preflight payload', 'livecanvas-forge-ai'),
                'openDeck'        => __('Open suggested payload in Command Deck', 'livecanvas-forge-ai'),
                'previewSuggestion' => __('Preview', 'livecanvas-forge-ai'),
                'previewing'      => __('Preparing preview...', 'livecanvas-forge-ai'),
                'applySuggestion' => __('Apply', 'livecanvas-forge-ai'),
                'applying'        => __('Applying change...', 'livecanvas-forge-ai'),
                'applyFailed'     => __('The inline execution failed.', 'livecanvas-forge-ai'),
                'idleState'       => __('Ready for a new request.', 'livecanvas-forge-ai'),
                'thinkingState'   => __('Sending request...', 'livecanvas-forge-ai'),
                'suggestedState'  => __('Request prepared.', 'livecanvas-forge-ai'),
                'previewedState'  => __('Preview ready. Review the support details below.', 'livecanvas-forge-ai'),
                'appliedState'    => __('Change applied inline.', 'livecanvas-forge-ai'),
                'failedState'     => __('The current request failed. Review the support details below.', 'livecanvas-forge-ai'),
                'queuedState'     => __('Queued for inline execution.', 'livecanvas-forge-ai'),
                'runningState'    => __('Running inline execution...', 'livecanvas-forge-ai'),
                'agentQueuedState' => __('Waiting for the connected coding agent...', 'livecanvas-forge-ai'),
                'agentRunningState' => __('The coding agent is processing this request...', 'livecanvas-forge-ai'),
                'agentTimeoutState' => __('Request queued. Keep the coding agent open, process the AI Bridge frontend queue, then this panel will update.', 'livecanvas-forge-ai'),
                'creatingThread'  => __('Creating thread...', 'livecanvas-forge-ai'),
                'duplicatingThread' => __('Duplicating thread...', 'livecanvas-forge-ai'),
                'renamingThread'  => __('Renaming thread...', 'livecanvas-forge-ai'),
                'clearingThread'  => __('Clearing thread...', 'livecanvas-forge-ai'),
                'deletingThread'  => __('Deleting thread...', 'livecanvas-forge-ai'),
                'renameThreadPrompt' => __('Rename the current thread', 'livecanvas-forge-ai'),
                'confirmClearThread' => __('Clear all messages from the current thread?', 'livecanvas-forge-ai'),
                'confirmDeleteThread' => __('Delete the current thread and switch back to the default one?', 'livecanvas-forge-ai'),
                'newThreadLabel'  => __('New thread', 'livecanvas-forge-ai'),
                'duplicateThreadLabel' => __('Duplicate current', 'livecanvas-forge-ai'),
                'renameThreadLabel' => __('Rename current', 'livecanvas-forge-ai'),
                'clearThreadLabel' => __('Clear current', 'livecanvas-forge-ai'),
                'deleteThreadLabel' => __('Delete current', 'livecanvas-forge-ai'),
                'viewPage'        => __('View page', 'livecanvas-forge-ai'),
                'editPage'        => __('Edit page', 'livecanvas-forge-ai'),
                'openTemplate'    => __('Open template', 'livecanvas-forge-ai'),
                'openThemeFile'   => __('Open theme file', 'livecanvas-forge-ai'),
                'openThemeTemplate' => __('Open theme template', 'livecanvas-forge-ai'),
                'openBackup'      => __('Open backup', 'livecanvas-forge-ai'),
                'analyzeSuggestion' => __('Send', 'livecanvas-forge-ai'),
                'analyzing'       => __('Sending...', 'livecanvas-forge-ai'),
                'attachScreenshot' => __('Upload image', 'livecanvas-forge-ai'),
                'clearScreenshot' => __('Remove image', 'livecanvas-forge-ai'),
                'screenshotReady' => __('Image ready for this request.', 'livecanvas-forge-ai'),
                'dropScreenshotTitle' => __('Upload image', 'livecanvas-forge-ai'),
                'dropScreenshotHint' => __('Add a reference screenshot if needed.', 'livecanvas-forge-ai'),
                'diffPreview'     => __('Diff preview', 'livecanvas-forge-ai'),
                'currentMarkup'   => __('Current markup', 'livecanvas-forge-ai'),
                'proposedMarkup'  => __('Proposed markup', 'livecanvas-forge-ai'),
            ],
        ];

        echo '<div class="lcfa-editor-shell" data-lcfa-editor-shell>';
        echo '<button type="button" class="lcfa-editor-launcher" data-lcfa-editor-open>';
        echo $this->get_icon_svg('stars');
        echo '<span class="lcfa-editor-launcher__label">' . esc_html__('AI Bridge', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<aside class="lcfa-editor-drawer" aria-hidden="true">';
        echo '<div class="lcfa-editor-bridge__head">';
        echo '<div>';
        echo '<span class="lcfa-editor-bridge__eyebrow">' . esc_html__('AI Bridge', 'livecanvas-forge-ai') . '</span>';
        echo '<p class="lcfa-editor-bridge__title">' . esc_html(sprintf(__('You are in: %s', 'livecanvas-forge-ai'), get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai'))) . '</p>';
        echo '<p class="lcfa-editor-bridge__target-line" data-lcfa-editor-target-summary>' . esc_html($this->get_editor_target_summary($post, $context)) . '</p>';
        echo '<div class="lcfa-editor-bridge__connection" data-state="' . esc_attr($connection_state) . '">';
        echo '<span class="lcfa-editor-bridge__connection-media">' . $this->get_agent_icon_markup($connection_icon_client, $this->get_client_fallback_icon($connection_icon_client), 'lcfa-agent-icon lcfa-agent-icon--editor-status') . '</span>';
        echo '<span class="lcfa-editor-bridge__connection-copy">' . esc_html($this->get_editor_connection_status_label($connected_client, $connection_state)) . '</span>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__connection" data-state="' . esc_attr($connection_state === 'connected' ? 'connected' : 'local') . '">';
        echo '<span class="lcfa-editor-bridge__connection-media">' . $this->get_icon_svg('stars') . '</span>';
        echo '<span class="lcfa-editor-bridge__connection-copy">' . esc_html($connection_state === 'connected' ? sprintf(__('Frontend prompts: %s MCP', 'livecanvas-forge-ai'), ucfirst(str_replace(['-', '_'], ' ', $connected_client))) : __('Frontend prompts: Bridge local fallback', 'livecanvas-forge-ai')) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__head-actions">';
        echo '<a class="lcfa-editor-bridge__head-link is-icon-only" href="' . esc_url($command_base_url) . '" target="_blank" rel="noreferrer noopener" aria-label="' . esc_attr__('Open Command Deck', 'livecanvas-forge-ai') . '" title="' . esc_attr__('Open Command Deck', 'livecanvas-forge-ai') . '" data-lcfa-editor-open-deck>';
        echo $this->get_icon_svg('command');
        echo '</a>';
        echo '<button type="button" class="lcfa-editor-bridge__close" data-lcfa-editor-close aria-label="' . esc_attr__('Close AI Bridge panel', 'livecanvas-forge-ai') . '">' . $this->get_icon_svg('power') . '</button>';
        echo '</div>';
        echo '</div>';
        if ($connected_client === 'codex') {
            echo '<div class="lcfa-editor-bridge__runtime-top">';
            echo '<div class="lcfa-editor-bridge__codex-runtime">';
            echo '<div class="lcfa-editor-bridge__row lcfa-editor-bridge__runtime-primary">';
            echo '<label class="lcfa-editor-bridge__field">';
            echo '<span>' . esc_html__('Model', 'livecanvas-forge-ai') . '</span>';
            echo '<select data-lcfa-editor-codex-model>';
            foreach (LCFA_Settings::get_codex_model_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_runtime_defaults['model'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '<label class="lcfa-editor-bridge__field">';
            echo '<span>' . esc_html__('Intelligence', 'livecanvas-forge-ai') . '</span>';
            echo '<select data-lcfa-editor-codex-reasoning>';
            foreach (LCFA_Settings::get_codex_reasoning_effort_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_runtime_defaults['reasoning_effort'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
            echo '<details class="lcfa-editor-bridge__runtime-details">';
            echo '<summary>' . esc_html__('Runtime options', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-editor-bridge__row lcfa-editor-bridge__runtime-advanced">';
            echo '<label class="lcfa-editor-bridge__field">';
            echo '<span>' . esc_html__('Speed', 'livecanvas-forge-ai') . '</span>';
            echo '<select data-lcfa-editor-codex-speed>';
            foreach (LCFA_Settings::get_codex_speed_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_runtime_defaults['speed'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '<label class="lcfa-editor-bridge__field">';
            echo '<span>' . esc_html__('Sandbox', 'livecanvas-forge-ai') . '</span>';
            echo '<select data-lcfa-editor-codex-sandbox>';
            foreach (LCFA_Settings::get_codex_sandbox_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_runtime_defaults['sandbox'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
            echo '</details>';
            echo '</div>';
            echo '</div>';
        }
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Request', 'livecanvas-forge-ai') . '</div>';
        echo '<p class="lcfa-editor-bridge__helper">' . esc_html($connection_state === 'connected' ? sprintf(__('Describe the change you want on this page. AI Bridge sends it to %s through MCP and updates this LiveCanvas page when the agent completes it.', 'livecanvas-forge-ai'), ucfirst(str_replace(['-', '_'], ' ', $connected_client))) : __('Describe the change you want on this page. AI Bridge sends it and runs it inline on the current page.', 'livecanvas-forge-ai')) . '</p>';
        echo '<div class="lcfa-editor-bridge__controls" data-lcfa-editor-composer>';
        echo '<textarea data-lcfa-editor-prompt placeholder="' . esc_attr__('Example: refresh this header with a simpler navigation and keep the current logo.', 'livecanvas-forge-ai') . '"></textarea>';
        echo '<div class="lcfa-editor-bridge__attachment-row">';
        echo '<input type="file" accept="image/*" hidden data-lcfa-editor-attachment>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral is-upload" data-lcfa-editor-attachment-trigger>';
        echo $this->get_icon_svg('image');
        echo '<span data-lcfa-editor-button-label>' . esc_html__('Upload image', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" hidden data-lcfa-editor-attachment-clear>';
        echo $this->get_icon_svg('x-circle');
        echo '<span data-lcfa-editor-button-label>' . esc_html__('Remove image', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__attachment-preview-card" hidden data-lcfa-editor-attachment-preview>';
        echo '<img class="lcfa-editor-bridge__attachment-preview-image" data-lcfa-editor-attachment-preview-image alt="" hidden>';
        echo '<div class="lcfa-editor-bridge__attachment-preview-copy">';
        echo '<span class="lcfa-editor-bridge__attachment-meta" data-lcfa-editor-attachment-preview-meta></span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__actions">';
        echo '<button type="button" class="lcfa-editor-bridge__button is-primary is-step-primary" data-lcfa-editor-analyze disabled>';
        echo $this->get_icon_svg('stars');
        echo '<span data-lcfa-editor-button-label>' . esc_html__('Send', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '</div>';
        echo '<p class="lcfa-editor-bridge__action-note">' . esc_html($connection_state === 'connected' ? __('Send queues the prompt for the connected coding agent. The drawer waits for the MCP result, then refreshes the LiveCanvas editor.', 'livecanvas-forge-ai') : __('Send analyzes the request and executes the change immediately on this page.', 'livecanvas-forge-ai')) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Conversation', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__thread-bar">';
        echo '<span class="lcfa-editor-bridge__thread-status" data-lcfa-editor-status data-state="' . esc_attr($initial_conversation_state) . '">' . esc_html($initial_conversation_label) . '</span>';
        echo '</div>';
        echo '<div class="lcfa-editor-thread-log" data-lcfa-editor-thread-log>';
        foreach ($current_thread_messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            if (($message['role'] ?? '') === 'suggestion_result') {
                continue;
            }

            $this->render_editor_bridge_thread_message($message);
        }
        echo '</div>';
        echo '<p class="lcfa-editor-thread-empty"' . ($current_thread_messages ? ' hidden' : '') . ' data-lcfa-editor-thread-empty>' . esc_html__('No messages yet. Send a request to start this thread.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';
        echo '<details class="lcfa-editor-bridge__details" data-lcfa-editor-session-details>';
        echo '<summary>' . esc_html__('Session settings', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-editor-bridge__details-body">';
        echo '<div class="lcfa-editor-bridge__row">';
        echo '<label class="lcfa-editor-bridge__field">';
        echo '<span>' . esc_html__('Thread', 'livecanvas-forge-ai') . '</span>';
        echo '<select data-lcfa-editor-thread>';
        foreach ($thread_summaries as $thread_summary) {
            if (!is_array($thread_summary)) {
                continue;
            }

            $thread_id = (string) ($thread_summary['id'] ?? 'default');
            echo '<option value="' . esc_attr($thread_id) . '"' . selected($thread_id, $current_thread_id, false) . '>' . esc_html((string) ($thread_summary['title'] ?? $thread_id)) . '</option>';
        }
        echo '</select>';
        echo '</label>';
        echo '<label class="lcfa-editor-bridge__field">';
        echo '<span>' . esc_html__('Execution target', 'livecanvas-forge-ai') . '</span>';
        echo '<select data-lcfa-editor-target>';
        echo '<option value="local">' . esc_html__('Local site', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="remote"' . (!empty($remote_status['configured']) ? '' : ' disabled') . '>' . esc_html__('Remote site', 'livecanvas-forge-ai') . '</option>';
        echo '</select>';
        echo '</label>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__actions is-thread-tools">';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-thread-create>';
        echo $this->get_icon_svg('plus');
        echo '<span>' . esc_html__('New thread', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-thread-duplicate>';
        echo $this->get_icon_svg('copy');
        echo '<span>' . esc_html__('Duplicate current', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-thread-rename>';
        echo $this->get_icon_svg('file-earmark');
        echo '<span>' . esc_html__('Rename current', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-thread-clear>';
        echo $this->get_icon_svg('trash');
        echo '<span>' . esc_html__('Clear current', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<button type="button" class="lcfa-editor-bridge__button is-neutral" data-lcfa-editor-thread-delete>';
        echo $this->get_icon_svg('x-circle');
        echo '<span>' . esc_html__('Delete current', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</details>';
        echo '<details class="lcfa-editor-bridge__details" data-lcfa-editor-quick-actions>';
        echo '<summary>' . esc_html__('Quick actions', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-editor-bridge__details-body">';
        echo '<div class="lcfa-editor-bridge__actions">';
        foreach ($actions as $action) {
            echo '<a class="lcfa-editor-bridge__button is-' . esc_attr($action['tone']) . '" href="' . esc_url($action['url']) . '" target="_blank" rel="noreferrer noopener">';
            echo $this->get_icon_svg($action['icon']);
            echo '<span>' . esc_html($action['label']) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</details>';
        echo '<details class="lcfa-editor-bridge__details is-support" data-lcfa-editor-support-details>';
        echo '<summary>' . esc_html__('Support details', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-editor-bridge__details-body">';
        echo '<div class="lcfa-editor-bridge__result" data-lcfa-editor-result aria-live="polite">';
        echo '<div class="lcfa-editor-bridge__status" data-lcfa-editor-result-summary></div>';
        echo '<div class="lcfa-editor-bridge__result-meta" data-lcfa-editor-result-meta></div>';
        echo '<div data-lcfa-editor-result-reasons-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Why this was suggested', 'livecanvas-forge-ai') . '</div><ul class="lcfa-editor-bridge__list" data-lcfa-editor-result-reasons></ul></div>';
        echo '<div data-lcfa-editor-result-warnings-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Warnings', 'livecanvas-forge-ai') . '</div><ul class="lcfa-editor-bridge__list" data-lcfa-editor-result-warnings></ul></div>';
        echo '<div data-lcfa-editor-result-workflow-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Recommended workflow', 'livecanvas-forge-ai') . '</div><ol class="lcfa-editor-bridge__list" data-lcfa-editor-result-workflow></ol></div>';
        echo '<div data-lcfa-editor-result-preflight-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Recommended preflight payload', 'livecanvas-forge-ai') . '</div><pre class="lcfa-editor-bridge__code" data-lcfa-editor-result-preflight></pre></div>';
        echo '<div data-lcfa-editor-result-diff-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Diff preview', 'livecanvas-forge-ai') . '</div><div class="lcfa-editor-bridge__diff" data-lcfa-editor-result-diff></div></div>';
        echo '<div class="lcfa-editor-bridge__support-grid">';
        echo '<div data-lcfa-editor-result-existing-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Current markup', 'livecanvas-forge-ai') . '</div><pre class="lcfa-editor-bridge__code" data-lcfa-editor-result-existing></pre></div>';
        echo '<div data-lcfa-editor-result-proposed-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Proposed markup', 'livecanvas-forge-ai') . '</div><pre class="lcfa-editor-bridge__code" data-lcfa-editor-result-proposed></pre></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</details>';
        echo '</aside>';
        echo '</div>';
        echo '<script type="application/json" data-lcfa-editor-config>' . wp_json_encode($editor_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    private function is_forge_dashboard_request(): bool {
        return sanitize_key((string) ($_GET['page'] ?? '')) === 'lcfa-dashboard';
    }

    public function handle_setup_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_setup');

        $step     = max(1, absint($_POST['step'] ?? 1));
        $settings = LCFA_Settings::get();

        switch ($step) {
            case 1:
                if (!empty($_POST['activate_livecanvas'])) {
                    $activation_result = $this->installer->activate_livecanvas();

                    if (is_wp_error($activation_result)) {
                        LCFA_Settings::set_notice($activation_result->get_error_message(), 'error');
                    } else {
                        LCFA_Settings::set_notice(__('LiveCanvas activated successfully.', 'livecanvas-forge-ai'));
                    }
                }

                $snapshot = $this->environment->get_snapshot();
                $preflight_ready = $this->is_preflight_ready($snapshot);

                if (!$preflight_ready && empty($_POST['activate_livecanvas'])) {
                    LCFA_Settings::set_notice($this->get_preflight_blocking_message($snapshot), 'error');
                }

                $settings['last_completed_step'] = $preflight_ready ? max(1, (int) $settings['last_completed_step']) : 0;
                LCFA_Settings::update($settings);
                $this->redirect_to_step($preflight_ready ? 2 : 1);
                break;

            case 2:
                $framework = sanitize_key($_POST['framework'] ?? '');
                $snapshot = $this->environment->get_snapshot();
                $connections = LCFA_Settings::get_connections();
                $previous_framework = $this->normalize_supported_framework((string) ($settings['framework'] ?: ($snapshot['detected_framework'] ?? '')));

                if (!$this->environment->is_livecanvas_active()) {
                    LCFA_Settings::set_notice(__('Complete the LiveCanvas preflight first.', 'livecanvas-forge-ai'), 'error');
                    $this->redirect_to_step(1);
                }

                $result = $this->installer->apply_framework($framework);

                if (is_wp_error($result)) {
                    LCFA_Settings::set_notice($result->get_error_message(), 'error');
                    $this->redirect_to_step(2);
                }

                LCFA_Settings::patch([
                    'framework'           => $framework,
                    'last_completed_step' => max(2, (int) $settings['last_completed_step']),
                ]);

                if ($previous_framework !== '' && $previous_framework !== $framework && $this->has_verified_connection($connections)) {
                    LCFA_Settings::update_connections(array_merge($connections, [
                        'framework_change_pending'  => true,
                        'framework_change_previous' => $previous_framework,
                        'framework_change_next'     => $framework,
                    ]));

                    LCFA_Settings::set_notice(
                        sprintf(
                            __('Framework confirmed: %1$s. Active theme: %2$s. A verified %3$s connection already exists, so choose in Connections whether to keep it or regenerate it for the new stack.', 'livecanvas-forge-ai'),
                            $this->get_framework_display_name($framework),
                            $result['theme_stylesheet'],
                            ucfirst(str_replace('-', ' ', $this->normalize_connection_client((string) ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex')))))
                        )
                    );
                } else {
                    LCFA_Settings::update_connections($this->clear_framework_change_connection_decision($connections));
                    LCFA_Settings::set_notice(
                        sprintf(
                            __('Framework confirmed: %1$s. Active theme: %2$s.', 'livecanvas-forge-ai'),
                            $this->get_framework_display_name($framework),
                            $result['theme_stylesheet']
                        )
                    );
                }

                $this->redirect_to_step(3);
                break;

            case 3:
                $site_mode = sanitize_key($_POST['site_mode'] ?? '');

                if (!in_array($site_mode, ['local', 'remote', 'hybrid'], true)) {
                    LCFA_Settings::set_notice(__('Select a valid site profile.', 'livecanvas-forge-ai'), 'error');
                    $this->redirect_to_step(3);
                }

                LCFA_Settings::patch([
                    'site_mode'           => $site_mode,
                    'last_completed_step' => max(3, (int) $settings['last_completed_step']),
                ]);

                LCFA_Settings::set_notice(__('Site profile saved.', 'livecanvas-forge-ai'));
                $this->redirect_to_step(4);
                break;

            case 4:
                $ai_tool = sanitize_key($_POST['ai_tool'] ?? '');

                if (!in_array($ai_tool, ['codex', 'opencode', 'claude', 'cursor', 'other'], true)) {
                    LCFA_Settings::set_notice(__('Select a valid AI Coding Agent.', 'livecanvas-forge-ai'), 'error');
                    $this->redirect_to_step(4);
                }

                LCFA_Settings::patch([
                    'ai_tool'             => $ai_tool,
                    'last_completed_step' => max(4, (int) $settings['last_completed_step']),
                ]);
                LCFA_Settings::update_connections(array_merge(LCFA_Settings::get_connections(), [
                    'preferred_client' => $ai_tool === 'other' ? 'generic' : $ai_tool,
                ]));

                LCFA_Settings::set_notice(__('AI Coding Agent saved.', 'livecanvas-forge-ai'));
                $this->redirect_to_step(5);
                break;

            case 5:
                LCFA_Settings::patch([
                    'permission_profile'  => 'advanced_templates',
                    'allow_file_fallback' => true,
                    'last_completed_step' => max(5, (int) $settings['last_completed_step']),
                ]);

                LCFA_Settings::set_notice(__('Full access enabled.', 'livecanvas-forge-ai'));
                $this->redirect_to_step(6);
                break;

            case 6:
                LCFA_Settings::patch([
                    'completed'           => true,
                    'last_completed_step' => 6,
                ]);

                LCFA_Settings::set_notice(__('Bridge Setup is complete. You can now move into the operational dashboard.', 'livecanvas-forge-ai'));
                wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=' . $this->get_post_setup_redirect_tab()));
                exit;

            default:
                $this->redirect_to_step(1);
        }
    }

    public function handle_connections_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_connections');

        $current_connections = LCFA_Settings::get_connections();
        $has_wizard_step = array_key_exists('connection_current_step', $_POST);
        $current_step = sanitize_key((string) ($_POST['connection_current_step'] ?? ($current_connections['connection_current_step'] ?? 'choose_client')));
        $advance_step = $has_wizard_step && empty($_POST['rotate_mcp_token']);
        $connections = LCFA_Settings::sanitize_connections(array_merge($current_connections, $_POST, [
            'connection_mode'             => sanitize_key($_POST['connection_mode'] ?? ''),
            'connection_status'           => '',
            'connection_last_verified_at' => '',
            'connection_last_error'       => '',
            'connection_current_step'     => $current_step,
        ]));
        $next_step = $advance_step ? $this->connection_onboarding->next_step($current_step, $connections) : $current_step;
        $connections['connection_current_step'] = $next_step;

        LCFA_Settings::update_connections($connections);

        if (!empty($_POST['rotate_mcp_token'])) {
            LCFA_Settings::rotate_mcp_token();
        }

        $preferred_client = sanitize_key($_POST['preferred_client'] ?? '');
        if (in_array($preferred_client, ['codex', 'opencode', 'claude', 'claude-code', 'cursor', 'other', 'generic'], true)) {
            LCFA_Settings::patch([
                'ai_tool' => in_array($preferred_client, ['generic', 'other'], true)
                    ? 'other'
                    : ($preferred_client === 'claude-code' ? 'claude' : $preferred_client),
            ]);
        }

        LCFA_Settings::set_notice(!empty($_POST['rotate_mcp_token']) ? __('Connection settings saved and MCP token rotated.', 'livecanvas-forge-ai') : __('Connection settings saved.', 'livecanvas-forge-ai'));

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_connection_test_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_test_connections');

        $mode = sanitize_key((string) ($_POST['connection_mode'] ?? ''));
        if ($mode === 'local' && method_exists('LCFA_Settings', 'sync_local_workspace_root')) {
            LCFA_Settings::sync_local_workspace_root(true);
        }

        $result = $this->connection_tester->run_checks([
            'mode' => in_array($mode, ['local', 'remote'], true) ? $mode : 'all',
        ]);

        $connections = LCFA_Settings::get_connections();
        if (in_array($mode, ['local', 'remote'], true)) {
        $connections['connection_mode'] = $mode;
        }
        $connections['connection_last_verified_at'] = (string) ($result['checked_at'] ?? '');
        $connections['connection_status'] = !empty($result['ok']) ? 'ready' : 'needs_attention';
        $connections['connection_last_error'] = !empty($result['ok']) ? '' : (string) ($result['summary'] ?? '');
        $connections['connection_current_step'] = !empty($result['ok']) ? 'ready' : 'smoke_test';
        if (!empty($result['ok']) && $this->is_codex_local_connection($connections) && class_exists('LCFA_Codex_Config_Manager', false)) {
            $connections['connection_last_bundle_hash'] = (string) $this->get_codex_config_manager()->get_expected_config($connections)['hash'];
        }
        LCFA_Settings::update_connections($connections);

        LCFA_Settings::set_connection_test_result($result);
        LCFA_Settings::set_notice(
            (string) ($result['summary'] ?? __('Connection checks completed.', 'livecanvas-forge-ai')),
            !empty($result['ok']) ? 'success' : 'error'
        );

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_install_client_bundle_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_install_client_bundle');

        $bundle = $this->build_selected_connection_bundle($_POST);
        $target = $bundle['workspace_files'][0] ?? null;
        $workspace_root = (string) ($bundle['workspace_root'] ?? '');
        $workspace_write_state = LCFA_Workspace_Access::inspect($workspace_root);

        if (!is_array($target) || empty($target['path'])) {
            LCFA_Settings::set_notice(__('No writable workspace artifact was generated for this client.', 'livecanvas-forge-ai'), 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        if (empty($workspace_write_state['available'])) {
            LCFA_Settings::set_notice($this->get_workspace_write_notice($workspace_write_state), 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        $this->write_connection_artifact(
            (string) $target['path'],
            (string) ($target['content'] ?? ''),
            $workspace_root,
            !empty($_POST['create_backup'])
        );

        $connections = LCFA_Settings::get_connections();
        $connections['preferred_client'] = (string) $bundle['client'];
        $connections['claude_connection_target'] = (string) ($bundle['claude_connection_target'] ?? $connections['claude_connection_target']);
        $connections['connection_mode'] = (string) $bundle['mode'];
        $connections['workspace_root'] = sanitize_text_field($workspace_root !== '' ? $workspace_root : (string) $connections['workspace_root']);
        $connections['connection_last_bundle_hash'] = $this->get_connection_bundle_hash($bundle, (string) ($target['content'] ?? ''), $connections);
        $connections['connection_current_step'] = 'smoke_test';
        LCFA_Settings::update_connections($connections);

        LCFA_Settings::set_notice(__('Client bundle written to the local workspace.', 'livecanvas-forge-ai'));
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_download_client_bundle_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_download_client_bundle');

        $bundle = $this->build_selected_connection_bundle($_REQUEST);
        $file = $bundle['download_files'][0] ?? null;

        if (!is_array($file)) {
            wp_die(esc_html__('No bundle is available for download.', 'livecanvas-forge-ai'));
        }

        $connections = LCFA_Settings::get_connections();
        $connections['preferred_client'] = (string) ($bundle['client'] ?? $connections['preferred_client']);
        $connections['claude_connection_target'] = (string) ($bundle['claude_connection_target'] ?? $connections['claude_connection_target']);
        $connections['connection_mode'] = (string) ($bundle['mode'] ?? $connections['connection_mode']);
        $connections['workspace_root'] = sanitize_text_field((string) ($bundle['workspace_root'] ?? $connections['workspace_root']));
        $connections['connection_last_bundle_hash'] = $this->get_connection_bundle_hash($bundle, (string) ($file['content'] ?? ''), $connections);
        $connections['connection_current_step'] = 'smoke_test';
        LCFA_Settings::update_connections($connections);

        nocache_headers();
        header('Content-Type: ' . ((string) ($file['mime'] ?? 'text/plain')) . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) ($file['name'] ?? 'bundle.txt')) . '"');
        echo (string) ($file['content'] ?? '');
        exit;
    }

    public function handle_repair_codex_connection_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_repair_codex_connection');

        $repair_action = sanitize_key((string) ($_POST['codex_repair_action'] ?? ''));
        $stored_connections = LCFA_Settings::get_connections();
        $requested_mode = $this->normalize_connection_mode((string) ($_POST['connection_mode'] ?? ($stored_connections['connection_mode'] ?: 'remote')));
        $connections = $requested_mode === 'local' && method_exists('LCFA_Settings', 'sync_local_workspace_root')
            ? LCFA_Settings::sync_local_workspace_root(true)
            : $stored_connections;
        $connections['preferred_client'] = 'codex';
        $connections['connection_mode'] = $requested_mode;
        if ($requested_mode === 'local' && empty($connections['codex_config_scope'])) {
            $connections['codex_config_scope'] = 'project';
        }

        if (($repair_action === 'connect_codex' || $repair_action === 'sync_codex') && $requested_mode === 'remote') {
            $remote_prerequisites = $this->get_remote_codex_prerequisites($connections, $this->remote_client->get_status());
            if (empty($remote_prerequisites['ready'])) {
                $connections['connection_status'] = '';
                $connections['connection_last_verified_at'] = '';
                $connections['connection_last_error'] = __('Complete the remote URL, username, and Application Password before connecting Codex remotely.', 'livecanvas-forge-ai');
                $connections['connection_current_step'] = 'confirm_details';
                LCFA_Settings::update_connections($connections);
                LCFA_Settings::set_notice($connections['connection_last_error'], 'error');
                wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
                exit;
            }

            $bundle = $this->build_selected_connection_bundle([
                'preferred_client' => 'codex',
                'connection_mode'  => 'remote',
                'workspace_root'   => '',
            ]);
            $fingerprint_source = (string) ($bundle['shortcut_command'] ?? ($bundle['command_string'] ?? ''));
            $connections['connection_last_bundle_hash'] = $this->get_connection_bundle_hash($bundle, $fingerprint_source, $connections);
            $connections['connection_status'] = '';
            $connections['connection_last_verified_at'] = '';
            $connections['connection_last_error'] = __('Codex remote shortcut generated. Run it where Codex runs, then run the smoke test.', 'livecanvas-forge-ai');
            $connections['connection_current_step'] = 'smoke_test';
            LCFA_Settings::update_connections($connections);
            LCFA_Settings::set_notice(__('Codex remote setup is ready. Copy the shortcut, restart or reload Codex, then run the smoke test.', 'livecanvas-forge-ai'));
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        if (!class_exists('LCFA_Codex_Config_Manager', false)) {
            LCFA_Settings::set_notice(__('Codex config manager is not available in this runtime.', 'livecanvas-forge-ai'), 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }
        $manager = $this->get_codex_config_manager();

        if ($repair_action === 'sync_wp') {
            $connections['connection_current_step'] = 'generate_bundle';
            $connections['connection_status'] = '';
            $connections['connection_last_error'] = '';
            LCFA_Settings::update_connections($connections);
            LCFA_Settings::set_notice(__('WordPress connection settings synced to the current local site path.', 'livecanvas-forge-ai'));
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        if ($repair_action === 'connect_codex' || $repair_action === 'sync_codex') {
            $sync = $manager->sync($connections);
            $expected = $manager->get_expected_config($connections);
            if (!empty($sync['ok'])) {
                $connections['connection_last_bundle_hash'] = (string) $expected['hash'];
                $connections['connection_status'] = '';
                $connections['connection_last_verified_at'] = '';
                $connections['connection_last_error'] = __('Codex config updated. Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai');
                $connections['connection_current_step'] = 'smoke_test';
                LCFA_Settings::update_connections($connections);
                LCFA_Settings::set_notice($repair_action === 'connect_codex' ? __('Codex config updated. Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai') : (string) ($sync['message'] ?? __('Codex config updated.', 'livecanvas-forge-ai')));
            } else {
                LCFA_Settings::set_notice((string) ($sync['message'] ?? __('Codex config could not be updated.', 'livecanvas-forge-ai')), 'error');
            }
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        if ($repair_action === 'smoke') {
            if ($requested_mode === 'remote') {
                $result = $this->connection_tester->run_checks(['mode' => 'remote']);
                $connections['connection_last_verified_at'] = (string) ($result['checked_at'] ?? current_time('mysql', true));
                $connections['connection_status'] = !empty($result['ok']) ? 'ready' : 'needs_attention';
                $connections['connection_last_error'] = !empty($result['ok']) ? '' : (string) ($result['summary'] ?? '');
                $connections['connection_current_step'] = !empty($result['ok']) ? 'ready' : 'smoke_test';
                if (!empty($result['ok'])) {
                    $bundle = $this->build_selected_connection_bundle([
                        'preferred_client' => 'codex',
                        'connection_mode'  => 'remote',
                        'workspace_root'   => '',
                    ]);
                    $connections['connection_last_bundle_hash'] = $this->get_connection_bundle_hash($bundle, (string) ($bundle['shortcut_command'] ?? ($bundle['command_string'] ?? '')), $connections);
                }
                LCFA_Settings::update_connections($connections);
                LCFA_Settings::set_connection_test_result($result);
                LCFA_Settings::set_notice(
                    (string) ($result['summary'] ?? __('Connection checks completed.', 'livecanvas-forge-ai')),
                    !empty($result['ok']) ? 'success' : 'error'
                );
                wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
                exit;
            }

            $smoke = $manager->run_smoke_test($connections);
            $checked_at = current_time('mysql', true);
            $result = [
                'ok'         => !empty($smoke['ok']),
                'checked_at' => $checked_at,
                'summary'    => (string) ($smoke['message'] ?? __('Codex MCP smoke test completed.', 'livecanvas-forge-ai')),
                'checks'     => [
                    'codex_mcp_smoke' => [
                        'label'   => __('Codex MCP smoke test', 'livecanvas-forge-ai'),
                        'ok'      => !empty($smoke['ok']),
                        'skipped' => false,
                        'message' => (string) ($smoke['message'] ?? ''),
                        'details' => is_array($smoke['checks'] ?? null) ? $smoke['checks'] : [],
                    ],
                ],
            ];
            $connections['connection_status'] = !empty($smoke['ok']) ? 'ready' : 'needs_attention';
            $connections['connection_last_error'] = !empty($smoke['ok']) ? '' : (string) ($smoke['message'] ?? '');
            $connections['connection_current_step'] = !empty($smoke['ok']) ? 'ready' : 'smoke_test';
            if (!empty($smoke['ok'])) {
                $connections['connection_last_verified_at'] = $checked_at;
                $connections['connection_last_bundle_hash'] = (string) ($smoke['expected']['hash'] ?? $manager->get_expected_config($connections)['hash']);
            } else {
                $connections['connection_last_verified_at'] = '';
            }
            LCFA_Settings::update_connections($connections);
            LCFA_Settings::set_connection_test_result($result);
            LCFA_Settings::set_notice($result['summary'], !empty($result['ok']) ? 'success' : 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        LCFA_Settings::set_notice(__('Choose a Codex repair action.', 'livecanvas-forge-ai'), 'error');
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_reconfigure_connection_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_reconfigure_connection');

        $connections = $this->get_reconfigured_connections(LCFA_Settings::get_connections());
        LCFA_Settings::update_connections($connections);

        LCFA_Settings::set_notice(__('Choose a coding agent to generate a fresh client bundle.', 'livecanvas-forge-ai'));
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_framework_change_connection_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_framework_change_connection');

        $decision = sanitize_key((string) ($_POST['framework_connection_decision'] ?? ''));
        $connections = LCFA_Settings::get_connections();

        if (!$this->has_framework_change_connection_decision($connections)) {
            LCFA_Settings::set_notice(__('There is no framework change decision waiting right now.', 'livecanvas-forge-ai'), 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
            exit;
        }

        $client_label = ucfirst(str_replace('-', ' ', $this->normalize_connection_client((string) ($connections['preferred_client'] ?: 'codex'))));
        $next_framework_label = $this->get_framework_display_name((string) ($connections['framework_change_next'] ?? ''));

        if ($decision === 'keep') {
            LCFA_Settings::update_connections($this->clear_framework_change_connection_decision($connections));
            LCFA_Settings::set_notice(
                sprintf(
                    __('Kept the verified %1$s connection. You can keep working on the %2$s stack without regenerating the client bundle.', 'livecanvas-forge-ai'),
                    $client_label,
                    $next_framework_label
                )
            );
        } elseif ($decision === 'regenerate') {
            LCFA_Settings::update_connections($this->get_framework_regenerated_connections($connections));
            LCFA_Settings::set_notice(
                sprintf(
                    __('Generate a fresh %1$s client bundle for the %2$s stack, then rerun the smoke test.', 'livecanvas-forge-ai'),
                    $client_label,
                    $next_framework_label
                )
            );
        } else {
            LCFA_Settings::set_notice(__('Choose whether to keep the current connection or generate a new one.', 'livecanvas-forge-ai'), 'error');
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    private function build_selected_connection_bundle(array $request): array {
        $settings      = LCFA_Settings::get();
        $connections   = LCFA_Settings::get_connections();
        $client_key    = $this->normalize_connection_client((string) ($request['preferred_client'] ?? ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex'))));
        $default_mode  = $client_key === 'codex' ? 'remote' : 'local';
        $mode          = $this->normalize_connection_mode((string) ($request['connection_mode'] ?? ($connections['connection_mode'] ?: $default_mode)));
        $claude_connection_target = $this->normalize_claude_connection_target((string) ($request['claude_connection_target'] ?? ($connections['claude_connection_target'] ?? '')));
        $workspace_root = trim((string) ($request['workspace_root'] ?? $connections['workspace_root']));

        if ($mode === 'local') {
            $snapshot      = $this->environment->get_snapshot();
            $mcp_bootstrap = $this->get_lightweight_bootstrap_payload($connections, $snapshot);
            $client_payload = is_array($mcp_bootstrap['clients'][$client_key] ?? null)
                ? $mcp_bootstrap['clients'][$client_key]
                : (is_array($mcp_bootstrap['clients']['codex'] ?? null) ? $mcp_bootstrap['clients']['codex'] : ['command' => '', 'env' => []]);

            return $this->connection_onboarding->build_bundle([
                'client'                    => $client_key,
                'claude_connection_target'  => $claude_connection_target,
                'mode'                      => $mode,
                'workspace_root'            => $workspace_root,
                'common'                    => is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [],
                'client_payload' => [
                    'command' => (string) ($client_payload['command'] ?? ''),
                    'env'     => (array) ($client_payload['env'] ?? []),
                ],
            ]);
        }

        $mcp_bootstrap = $this->context_builder->get_bootstrap_payload();
        $remote_status = $this->remote_client->get_status();
        $common = is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [];
        $client_payload = is_array($mcp_bootstrap['clients'][$client_key] ?? null)
            ? $mcp_bootstrap['clients'][$client_key]
            : (is_array($mcp_bootstrap['clients']['codex'] ?? null) ? $mcp_bootstrap['clients']['codex'] : ['command' => '', 'env' => []]);

        if ($mode === 'remote' && $client_key === 'codex') {
            $remote_codex_payload = $this->build_remote_codex_mcp_adapter_payload($connections, $remote_status);
            $client_payload = $remote_codex_payload['client_payload'];
            $common = array_merge($common, $remote_codex_payload['common']);
        } elseif ($mode === 'remote') {
            $remote_rest_base = (string) ($remote_status['mcp']['rest_base'] ?? ($remote_status['endpoint'] ?? ''));
            $remote_token = (string) ($remote_status['mcp']['token'] ?? '');

            $client_payload['env'] = array_values(array_filter([
                $remote_rest_base !== '' ? 'LCFA_REST_BASE=' . $remote_rest_base : '',
                $remote_token !== '' ? 'LCFA_MCP_TOKEN=' . $remote_token : '',
            ]));
            $common = array_merge($common, [
                'connection_strategy' => 'remote-rest',
                'remote_site_url'     => trim((string) ($connections['remote_site_url'] ?? '')),
            ]);
        }

        return $this->connection_onboarding->build_bundle([
            'client'                    => $client_key,
            'claude_connection_target'  => $claude_connection_target,
            'mode'                      => $mode,
            'workspace_root'            => $workspace_root,
            'common'                    => $common,
            'client_payload' => [
                'command' => (string) ($client_payload['command'] ?? ''),
                'env'     => (array) ($client_payload['env'] ?? []),
            ],
        ]);
    }

    private function build_remote_codex_mcp_adapter_payload(array $connections, array $remote_status): array {
        $remote_site_url = trim((string) ($connections['remote_site_url'] ?? ''));
        $mcp_adapter_url = $this->get_remote_mcp_adapter_url($remote_site_url, $remote_status);
        $username = trim((string) ($connections['remote_username'] ?? ''));
        $application_password = trim((string) ($connections['remote_application_password'] ?? ''));
        $log_file = '/tmp/livecanvas-forge-codex-remote.log';
        $env = array_values(array_filter([
            $mcp_adapter_url !== '' ? 'WP_API_URL=' . $mcp_adapter_url : '',
            $username !== '' ? 'WP_API_USERNAME=' . $username : '',
            $application_password !== '' ? 'WP_API_PASSWORD=' . $application_password : '',
            'LOG_FILE=' . $log_file,
        ]));

        return [
            'client_payload' => [
                'command' => 'npx -y @automattic/mcp-wordpress-remote@latest',
                'env'     => $env,
            ],
            'common' => [
                'connection_strategy' => 'remote-mcp-adapter',
                'remote_site_url'     => $remote_site_url,
                'mcp_adapter_url'     => $mcp_adapter_url,
                'mcp_adapter_available' => !empty($remote_status['mcp_adapter']['available']),
                'mcp_proxy_package'   => '@automattic/mcp-wordpress-remote',
                'mcp_proxy_log_file'  => $log_file,
            ],
        ];
    }

    private function get_remote_mcp_adapter_url(string $remote_site_url, array $remote_status): string {
        $custom_server = is_array($remote_status['mcp_adapter']['custom_server'] ?? null)
            ? $remote_status['mcp_adapter']['custom_server']
            : [];
        $remote_url = trim((string) ($custom_server['url'] ?? ''));

        if ($remote_url !== '') {
            return $remote_url;
        }

        if ($remote_site_url === '') {
            return '';
        }

        return trailingslashit(untrailingslashit($remote_site_url)) . 'wp-json/livecanvas-forge-ai/mcp';
    }

    private function normalize_connection_client(string $client): string {
        $client = $this->sanitize_key_compat($client);

        if ($client === 'other') {
            return 'generic';
        }

        if ($client === 'claude-code') {
            return 'claude';
        }

        return in_array($client, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true)
            ? $client
            : 'codex';
    }

    private function normalize_connection_mode(string $mode): string {
        return $mode === 'remote' ? 'remote' : 'local';
    }

    private function get_direct_agent_onboarding(): LCFA_Direct_Agent_Onboarding {
        if (!$this->direct_agent_onboarding instanceof LCFA_Direct_Agent_Onboarding) {
            $this->direct_agent_onboarding = new LCFA_Direct_Agent_Onboarding();
        }

        return $this->direct_agent_onboarding;
    }

    private function get_power_mode(): LCFA_Power_Mode {
        if (!$this->power_mode instanceof LCFA_Power_Mode) {
            $this->power_mode = new LCFA_Power_Mode();
        }

        return $this->power_mode;
    }

    private function get_codex_config_manager() {
        return new LCFA_Codex_Config_Manager();
    }

    private function is_codex_local_connection(array $connections): bool {
        return $this->normalize_connection_client((string) ($connections['preferred_client'] ?? '')) === 'codex'
            && $this->normalize_connection_mode((string) ($connections['connection_mode'] ?? 'local')) === 'local';
    }

    private function get_connection_bundle_hash(array $bundle, string $content, array $connections): string {
        if ((string) ($bundle['client'] ?? '') === 'codex' && (string) ($bundle['mode'] ?? '') === 'local' && class_exists('LCFA_Codex_Config_Manager', false)) {
            $connections['workspace_root'] = (string) ($bundle['workspace_root'] ?? ($connections['workspace_root'] ?? ''));

            return (string) $this->get_codex_config_manager()->get_expected_config($connections)['hash'];
        }

        return md5($content);
    }

    private function normalize_claude_connection_target(string $target): string {
        $target = $this->sanitize_key_compat($target);

        return in_array($target, ['desktop_app', 'cli'], true) ? $target : '';
    }

    private function get_lightweight_bootstrap_payload(array $connections, array $snapshot): array {
        $site_url = trailingslashit(home_url('/'));
        $rest_base = trailingslashit(rest_url('lcfa/v1/'));
        $mcp_endpoint = LCFA_Settings::get_mcp_endpoint();
        $mcp_token = (string) ($connections['mcp_token'] ?? '');
        $wp_root = defined('ABSPATH') && is_string(ABSPATH) ? untrailingslashit((string) ABSPATH) : '';
        $site_fingerprint = method_exists('LCFA_Settings', 'get_site_fingerprint') ? LCFA_Settings::get_site_fingerprint() : '';
        $filesystem_mode = ($snapshot['site_mode'] ?? 'local') === 'local' ? 'local-theme-access' : 'remote-rest-primary';
        $local_mcp_command = 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';
        $common = [
            'site_url'       => $site_url,
            'rest_base'      => $rest_base,
            'site_fingerprint' => $site_fingerprint,
            'mcp_endpoint'   => $mcp_endpoint,
            'mcp_token'      => $mcp_token,
            'wp_root'        => $wp_root,
            'framework'      => (string) ($snapshot['detected_framework'] ?? 'unknown'),
            'theme'          => (string) ($snapshot['current_theme_stylesheet'] ?? ''),
            'transport'      => (string) ($connections['transport'] ?? 'rest'),
            'filesystem_mode'=> $filesystem_mode,
        ];
        $filesystem_env = $filesystem_mode === 'local-theme-access' && $wp_root !== ''
            ? ['LCFA_WP_ROOT=' . $wp_root]
            : [];

        $bootstrap = [
            'common' => $common,
            'clients' => [
                'codex' => [
                    'label'   => 'Codex',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio')),
                    'env'     => array_merge([
                        'LCFA_SITE_URL=' . $site_url,
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_SITE_FINGERPRINT=' . $site_fingerprint,
                        'LCFA_MCP_ENDPOINT=' . $mcp_endpoint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'opencode' => [
                    'label'   => 'OpenCode',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=opencode')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_SITE_FINGERPRINT=' . $site_fingerprint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'claude' => [
                    'label'   => 'Claude',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=claude')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_SITE_FINGERPRINT=' . $site_fingerprint,
                        'LCFA_MCP_ENDPOINT=' . $mcp_endpoint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'cursor' => [
                    'label'   => 'Cursor',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=cursor')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_SITE_FINGERPRINT=' . $site_fingerprint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
            ],
        ];

        $bootstrap['clients']['claude-code'] = $bootstrap['clients']['claude'];

        return $bootstrap;
    }

    private function get_deferred_mcp_status(array $snapshot): array {
        return [
            'enabled'         => true,
            'endpoint'        => LCFA_Settings::get_mcp_endpoint(),
            'rest_base'       => trailingslashit(rest_url('lcfa/v1/')),
            'filesystem_mode' => ($snapshot['site_mode'] ?? 'local') === 'local' ? 'local-theme-access' : 'remote-rest-primary',
            'local_bridge'    => [
                'deferred' => true,
            ],
        ];
    }

    private function build_common_bootstrap_block(array $mcp_bootstrap): string {
        $common = is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [];
        $environment = array_values(array_filter([
            !empty($common['site_url']) ? 'LCFA_SITE_URL=' . (string) $common['site_url'] : '',
            !empty($common['rest_base']) ? 'LCFA_REST_BASE=' . (string) $common['rest_base'] : '',
            !empty($common['site_fingerprint']) ? 'LCFA_SITE_FINGERPRINT=' . (string) $common['site_fingerprint'] : '',
            !empty($common['mcp_endpoint']) ? 'LCFA_MCP_ENDPOINT=' . (string) $common['mcp_endpoint'] : '',
            !empty($common['mcp_token']) ? 'LCFA_MCP_TOKEN=' . (string) $common['mcp_token'] : '',
            !empty($common['framework']) ? 'LCFA_FRAMEWORK=' . (string) $common['framework'] : '',
            !empty($common['theme']) ? 'LCFA_THEME=' . (string) $common['theme'] : '',
        ]));

        if (($common['filesystem_mode'] ?? '') === 'local-theme-access' && !empty($common['wp_root'])) {
            $environment[] = 'LCFA_WP_ROOT=' . (string) $common['wp_root'];
        }

        return implode("\n", $environment);
    }

    private function write_connection_artifact(string $path, string $content, string $workspace_root, bool $create_backup = false): void {
        $workspace_root = untrailingslashit($workspace_root);
        $normalized_path = wp_normalize_path($path);
        $normalized_root = $workspace_root !== '' ? wp_normalize_path($workspace_root) : '';
        $workspace_state = LCFA_Workspace_Access::inspect($workspace_root);

        if ($normalized_root === '' || strpos($normalized_path, $normalized_root . '/') !== 0) {
            wp_die(esc_html__('The bundle can only be written inside the configured local workspace root.', 'livecanvas-forge-ai'));
        }

        if (empty($workspace_state['available'])) {
            wp_die(esc_html($this->get_workspace_write_notice($workspace_state)));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            wp_die(esc_html__('Failed to create the bundle directory.', 'livecanvas-forge-ai'));
        }

        if ($create_backup && file_exists($path)) {
            $backup_path = $path . '.' . gmdate('YmdHis') . '.bak';
            if (!copy($path, $backup_path)) {
                wp_die(esc_html__('Failed to create the requested backup file.', 'livecanvas-forge-ai'));
            }
        }

        if (file_put_contents($path, $content) === false) {
            wp_die(esc_html__('Failed to write the generated bundle file.', 'livecanvas-forge-ai'));
        }
    }

    public function handle_reset_setup_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_reset_setup');

        LCFA_Settings::reset_setup_state();
        LCFA_Settings::set_notice(__('AI Bridge state reset. Setup and connection status were cleared, a new MCP token was generated, and existing workspace files were left untouched.', 'livecanvas-forge-ai'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=1'));
        exit;
    }

    public function handle_project_brief_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_project_brief');

        $previous_hash = LCFA_Settings::get_project_brief_hash();
        LCFA_Settings::update_project_brief($_POST);
        $current_hash = LCFA_Settings::get_project_brief_hash();

        if ($previous_hash !== $current_hash) {
            LCFA_Settings::clear_genesis_plan();
            LCFA_Settings::clear_genesis_progress();
            LCFA_Settings::set_notice(__('Project brief updated. Regenerate the build plan to refresh task suggestions.', 'livecanvas-forge-ai'));
        } else {
            LCFA_Settings::set_notice(__('Project brief updated.', 'livecanvas-forge-ai'));
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
        exit;
    }

    public function handle_generate_plan_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_generate_plan');

        $plan = $this->genesis_planner->generate();
        LCFA_Settings::update_genesis_plan($plan);
        LCFA_Settings::clear_genesis_progress();
        LCFA_Settings::set_notice(__('Build plan generated.', 'livecanvas-forge-ai'));

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
        exit;
    }

    public function handle_genesis_execute_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_genesis_execute');

        $execution_mode = sanitize_key((string) ($_POST['execution_mode'] ?? ''));
        $task_id        = sanitize_key((string) ($_POST['task_id'] ?? ''));
        $thread_id      = LCFA_Settings::normalize_thread_id((string) ($_POST['thread_id'] ?? 'default'));
        $execution_target = sanitize_key((string) ($_POST['execution_target'] ?? 'local'));

        if (!in_array($execution_target, ['local', 'remote'], true)) {
            $execution_target = 'local';
        }

        $result = null;

        switch ($execution_mode) {
            case 'preview_next':
                $result = $this->get_genesis_executor()->execute_next([
                    'dry_run'          => true,
                    'execution_target' => $execution_target,
                    'thread_id'        => $thread_id,
                    'request_context'  => [
                        'thread_id' => $thread_id,
                    ],
                ]);
                break;

            case 'apply_next':
                $result = $this->get_genesis_executor()->execute_next([
                    'dry_run'          => false,
                    'execution_target' => $execution_target,
                    'thread_id'        => $thread_id,
                    'request_context'  => [
                        'thread_id' => $thread_id,
                    ],
                ]);
                break;

            case 'preview_task':
                if ($task_id === '') {
                    LCFA_Settings::set_notice(__('Choose a valid Genesis task before previewing it.', 'livecanvas-forge-ai'), 'error');
                    wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
                    exit;
                }

                $result = $this->get_genesis_executor()->execute_task($task_id, [
                    'dry_run'          => true,
                    'execution_target' => $execution_target,
                    'thread_id'        => $thread_id,
                    'request_context'  => [
                        'thread_id' => $thread_id,
                    ],
                ]);
                break;

            case 'apply_task':
            case 'retry_task':
            case 'acknowledge_task':
                if ($task_id === '') {
                    LCFA_Settings::set_notice(__('Choose a valid Genesis task before applying it.', 'livecanvas-forge-ai'), 'error');
                    wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
                    exit;
                }

                $result = $this->get_genesis_executor()->execute_task($task_id, [
                    'dry_run'          => false,
                    'execution_target' => $execution_target,
                    'thread_id'        => $thread_id,
                    'request_context'  => [
                        'thread_id' => $thread_id,
                    ],
                ]);
                break;
        }

        if (!is_array($result)) {
            LCFA_Settings::set_notice(__('Choose a valid Genesis execution action.', 'livecanvas-forge-ai'), 'error');
            wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
            exit;
        }

        if (!empty($result['ok'])) {
            $notice = trim((string) ($result['message'] ?? ''));

            if ($notice === '') {
                $notice = __('Genesis task executed.', 'livecanvas-forge-ai');
            }

            LCFA_Settings::set_notice($notice, 'success');
        } else {
            LCFA_Settings::set_notice((string) ($result['message'] ?? __('The Genesis task could not be executed.', 'livecanvas-forge-ai')), 'error');
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
        exit;
    }

    public function handle_create_thread_post(): void {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_create_thread');

        $title  = sanitize_text_field((string) ($_POST['thread_title'] ?? ''));
        $thread = LCFA_Settings::create_thread($title);

        LCFA_Settings::set_notice(__('Command thread created.', 'livecanvas-forge-ai'));

        wp_safe_redirect($this->get_command_url([
            'thread_id' => (string) ($thread['id'] ?? 'default'),
        ]));
        exit;
    }

    public function handle_command_post(): void {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_command');

        $request_payload = $this->get_command_request_payload($_POST);
        $request_payload = $this->add_payload_provenance($request_payload, $this->get_payload_provenance($request_payload, 'admin_command_deck', 'forge_local_rules'));
        $thread_id       = LCFA_Settings::normalize_thread_id((string) ($request_payload['thread_id'] ?? 'default'));
        $thread_operation = sanitize_key((string) ($request_payload['thread_operation'] ?? ''));
        $thread_title    = sanitize_text_field((string) ($request_payload['thread_title'] ?? ''));
        $genesis_task_id = sanitize_key((string) ($request_payload['genesis_task_id'] ?? ''));
        $user_prompt     = sanitize_textarea_field((string) ($request_payload['user_prompt'] ?? ''));

        if ($thread_operation !== '') {
            $default_thread_id = LCFA_Settings::normalize_thread_id('default');
            $redirect_thread_id = $thread_id;

            switch ($thread_operation) {
                case 'create':
                    $thread = LCFA_Settings::create_thread($thread_title);
                    $redirect_thread_id = LCFA_Settings::normalize_thread_id((string) ($thread['id'] ?? $default_thread_id));
                    LCFA_Settings::set_notice(__('Command thread created.', 'livecanvas-forge-ai'));
                    break;

                case 'duplicate':
                    $thread = LCFA_Settings::duplicate_thread($thread_id, $thread_title);
                    $redirect_thread_id = LCFA_Settings::normalize_thread_id((string) ($thread['id'] ?? $default_thread_id));
                    LCFA_Settings::set_notice(__('Command thread duplicated.', 'livecanvas-forge-ai'));
                    break;

                case 'rename':
                    if ($thread_title === '') {
                        LCFA_Settings::set_notice(__('Thread rename requires a title.', 'livecanvas-forge-ai'), 'error');
                    } else {
                        LCFA_Settings::rename_thread($thread_id, $thread_title);
                        LCFA_Settings::set_notice(__('Command thread renamed.', 'livecanvas-forge-ai'));
                    }
                    break;

                case 'clear':
                    LCFA_Settings::clear_thread($thread_id);
                    LCFA_Settings::set_notice(__('Command thread cleared.', 'livecanvas-forge-ai'));
                    break;

                case 'delete':
                    if ($thread_id === $default_thread_id) {
                        LCFA_Settings::set_notice(__('The default thread cannot be deleted.', 'livecanvas-forge-ai'), 'error');
                    } else {
                        LCFA_Settings::delete_thread($thread_id);
                        $redirect_thread_id = $default_thread_id;
                        LCFA_Settings::set_notice(__('Command thread deleted.', 'livecanvas-forge-ai'));
                    }
                    break;
            }

            $redirect_args = [
                'thread_id' => $redirect_thread_id,
            ];

            if ($genesis_task_id !== '') {
                $redirect_args['genesis_task_id'] = $genesis_task_id;
            }

            if (!empty($request_payload['context_post_id'])) {
                $redirect_args['post_id'] = absint($request_payload['context_post_id']);
            }

            if ($user_prompt !== '') {
                $redirect_args['user_prompt'] = $user_prompt;
            }

            wp_safe_redirect($this->get_command_url($redirect_args));
            exit;
        }

        if (!empty($request_payload['analyze_request'])) {
            if ($user_prompt !== '') {
                LCFA_Settings::append_thread_message($thread_id, $this->build_thread_request_message($user_prompt, $request_payload, $genesis_task_id));
            }

            $suggestion = $this->prompt_suggester->suggest($request_payload);
            $suggestion['provenance'] = $this->get_payload_provenance($request_payload, 'admin_command_deck', 'forge_local_rules');
            LCFA_Settings::set_command_suggestion($suggestion);
            LCFA_Settings::append_thread_message($thread_id, $this->build_thread_suggestion_message($suggestion, $request_payload, $thread_id));

            if (!empty($suggestion['ok'])) {
                LCFA_Settings::set_notice(__('Request analyzed. Suggested action prepared in the Command Deck.', 'livecanvas-forge-ai'));
            } else {
                LCFA_Settings::set_notice((string) ($suggestion['message'] ?? __('The plugin could not infer a safe action from the current request.', 'livecanvas-forge-ai')), 'error');
            }

            $redirect_args = [
                'thread_id'   => $thread_id,
                'user_prompt' => $user_prompt,
            ];

            if ($genesis_task_id !== '') {
                $redirect_args['genesis_task_id'] = $genesis_task_id;
            }

            if (!empty($request_payload['context_post_id'])) {
                $redirect_args['post_id'] = absint($request_payload['context_post_id']);
            }

            if (!empty($suggestion['ok']) && is_array($suggestion['suggested_payload'] ?? null)) {
                $redirect_args = array_merge($redirect_args, $this->build_command_redirect_args((array) $suggestion['suggested_payload']));
            }

            wp_safe_redirect($this->get_command_url($redirect_args));
            exit;
        }

        if ($user_prompt !== '') {
            LCFA_Settings::append_thread_message($thread_id, $this->build_thread_request_message($user_prompt, $request_payload, $genesis_task_id));
        }

        $result = $this->command_deck->execute($request_payload);
        LCFA_Settings::set_command_result($result);
        LCFA_Settings::append_thread_message($thread_id, $this->build_thread_result_message($result, $request_payload));

        if ($genesis_task_id !== '') {
            LCFA_Settings::update_genesis_task_progress($genesis_task_id, [
                'status'       => !empty($result['ok']) ? (($result['mode'] ?? '') === 'preview' ? 'previewed' : 'applied') : 'failed',
                'updated_at'   => current_time('mysql', true),
                'thread_id'    => $thread_id,
                'action'       => (string) ($result['action'] ?? sanitize_key((string) ($request_payload['action'] ?? ''))),
                'mode'         => (string) ($result['mode'] ?? (!empty($request_payload['dry_run']) ? 'preview' : 'apply')),
                'ok'           => !empty($result['ok']),
                'message'      => (string) ($result['message'] ?? ''),
                'target_type'  => (string) ($result['target_type'] ?? ''),
                'target_id'    => (int) ($result['target_id'] ?? 0),
                'target_title' => (string) ($result['target_title'] ?? ''),
            ]);
        }

        if (!empty($result['ok'])) {
            $notice = $result['mode'] === 'preview'
                ? __('Command preview prepared.', 'livecanvas-forge-ai')
                : ($result['message'] ?: __('Command applied.', 'livecanvas-forge-ai'));

            LCFA_Settings::set_notice($notice, 'success');
        } else {
            LCFA_Settings::set_notice($result['message'] ?: __('The command failed.', 'livecanvas-forge-ai'), 'error');
        }

        $redirect_args = [
            'thread_id' => $thread_id,
        ];

        if ($genesis_task_id !== '') {
            $redirect_args['genesis_task_id'] = $genesis_task_id;
        }

        if (!empty($request_payload['context_post_id'])) {
            $redirect_args['post_id'] = absint($request_payload['context_post_id']);
        }

        if ($user_prompt !== '') {
            $redirect_args['user_prompt'] = $user_prompt;
        }

        wp_safe_redirect($this->get_command_url($redirect_args));
        exit;
    }

    private function get_command_request_payload(array $source): array {
        $payload = $source;

        if (!empty($source['command_payload_json']) && is_string($source['command_payload_json'])) {
            $decoded = json_decode(wp_unslash((string) $source['command_payload_json']), true);

            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        if (!empty($source['command_payload']) && is_array($source['command_payload'])) {
            $payload = array_merge($payload, (array) $source['command_payload']);
        }

        $payload = $this->merge_command_payload_from_content_json($payload);

        return $payload;
    }

    private function merge_command_payload_from_content_json(array $payload): array {
        if (empty($payload['content']) || !is_string($payload['content'])) {
            return $payload;
        }

        $content = trim((string) wp_unslash($payload['content']));

        if ($content === '' || $content[0] !== '{' || substr($content, -1) !== '}') {
            return $payload;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return $payload;
        }

        return array_merge($payload, $decoded);
    }

    private function get_payload_provenance(array $payload, string $default_origin = 'admin_command_deck', string $default_processed_by = 'forge_local_rules'): array {
        $origin = sanitize_key((string) ($payload['_lcfa_origin'] ?? $payload['origin'] ?? $default_origin));
        $transport = sanitize_key((string) ($payload['_lcfa_transport'] ?? $payload['transport'] ?? ($origin === 'mcp_agent' ? 'mcp_stdio' : 'browser_rest')));
        $agent = sanitize_key((string) ($payload['_lcfa_agent'] ?? $payload['agent'] ?? ($origin === 'mcp_agent' ? 'codex' : 'forge')));
        $processed_by = sanitize_key((string) ($payload['_lcfa_processed_by'] ?? $payload['processed_by'] ?? $default_processed_by));

        $allowed_origins = ['frontend_bridge', 'admin_command_deck', 'mcp_agent', 'remote_companion', 'api'];
        $allowed_transports = ['browser_rest', 'mcp_stdio', 'mcp_bridge', 'remote_rest', 'api'];
        $allowed_agents = ['forge', 'codex', 'opencode', 'claude', 'cursor', 'generic'];
        $allowed_processors = ['forge_local_rules', 'codex_mcp', 'opencode_mcp', 'claude_mcp', 'cursor_mcp', 'generic_mcp', 'remote_companion'];

        if (!in_array($origin, $allowed_origins, true)) {
            $origin = $default_origin;
        }

        if (!in_array($transport, $allowed_transports, true)) {
            $transport = $origin === 'mcp_agent' ? 'mcp_stdio' : 'browser_rest';
        }

        if (!in_array($agent, $allowed_agents, true)) {
            $agent = $origin === 'mcp_agent' ? 'codex' : 'forge';
        }

        if (!in_array($processed_by, $allowed_processors, true)) {
            $processed_by = $default_processed_by;
        }

        return [
            'origin'       => $origin,
            'transport'    => $transport,
            'agent'        => $agent,
            'processed_by' => $processed_by,
        ];
    }

    private function add_payload_provenance(array $payload, array $provenance): array {
        foreach ($provenance as $key => $value) {
            $payload['_lcfa_' . $key] = $value;
        }

        return $payload;
    }

    private function build_thread_request_message(string $user_prompt, array $payload, string $genesis_task_id = ''): array {
        $provenance = $this->get_payload_provenance($payload, 'admin_command_deck', 'forge_local_rules');

        return [
            'role'    => 'user',
            'label'   => __('Request', 'livecanvas-forge-ai'),
            'content' => $user_prompt,
            'meta'    => [
                'action'           => sanitize_key((string) ($payload['action'] ?? '')),
                'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
                'dry_run'          => !empty($payload['dry_run']),
                'genesis_task_id'  => $genesis_task_id,
            ] + $provenance,
        ];
    }

    private function build_thread_suggestion_message(array $suggestion, array $request_payload, string $thread_id): array {
        $suggested_payload = is_array($suggestion['suggested_payload'] ?? null) ? $suggestion['suggested_payload'] : [];
        $summary           = (string) ($suggestion['summary'] ?? $suggestion['message'] ?? '');
        $provenance        = $this->get_payload_provenance(is_array($suggestion['provenance'] ?? null) ? (array) $suggestion['provenance'] : $request_payload, 'admin_command_deck', 'forge_local_rules');

        return [
            'role'    => 'suggestion_result',
            'label'   => !empty($suggestion['ok']) ? __('Suggestion ready', 'livecanvas-forge-ai') : __('Suggestion failed', 'livecanvas-forge-ai'),
            'content' => $summary !== '' ? $summary : __('No suggestion summary available.', 'livecanvas-forge-ai'),
            'meta'    => [
                'ok'               => !empty($suggestion['ok']),
                'action'           => sanitize_key((string) ($suggested_payload['action'] ?? '')),
                'execution_target' => sanitize_key((string) ($suggested_payload['execution_target'] ?? '')),
                'confidence'       => sanitize_text_field((string) ($suggestion['confidence'] ?? '')),
                'warnings'         => array_map('sanitize_text_field', (array) ($suggestion['warnings'] ?? [])),
                'reasons'          => array_map('sanitize_text_field', (array) ($suggestion['reasons'] ?? [])),
            ] + $provenance,
            'actions' => LCFA_Thread_Message_Actions::build_suggestion_actions($suggested_payload, $request_payload, [
                'thread_id'          => LCFA_Settings::normalize_thread_id($thread_id),
                'command_url_builder' => [$this, 'get_command_url'],
            ]),
        ];
    }

    public function render_setup_page(): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=' . max(1, absint($_GET['step'] ?? 1))));
        exit;
    }

    private function get_default_dashboard_tab(array $settings): string {
        return !empty($settings['completed']) ? 'connections' : 'setup';
    }

    private function get_post_setup_redirect_tab(): string {
        return 'connections';
    }

    private function get_reconfigured_connections(array $connections): array {
        $connections = $this->clear_framework_change_connection_decision($connections);
        $connections['preferred_client'] = '';
        $connections['connection_status'] = '';
        $connections['connection_last_verified_at'] = '';
        $connections['connection_last_error'] = '';
        $connections['connection_current_step'] = 'choose_client';

        return $connections;
    }

    private function get_framework_regenerated_connections(array $connections): array {
        $connections = $this->clear_framework_change_connection_decision($connections);
        $connections['connection_status'] = '';
        $connections['connection_last_verified_at'] = '';
        $connections['connection_last_error'] = '';
        $connections['connection_last_bundle_hash'] = '';
        $connections['connection_current_step'] = $this->get_framework_regeneration_step($connections);

        return $connections;
    }

    private function clear_framework_change_connection_decision(array $connections): array {
        $connections['framework_change_pending'] = false;
        $connections['framework_change_previous'] = '';
        $connections['framework_change_next'] = '';

        return $connections;
    }

    private function has_verified_connection(array $connections): bool {
        return (string) ($connections['connection_status'] ?? '') === 'ready'
            && trim((string) ($connections['connection_last_verified_at'] ?? '')) !== ''
            && trim((string) ($connections['preferred_client'] ?? '')) !== '';
    }

    private function has_framework_change_connection_decision(array $connections): bool {
        return !empty($connections['framework_change_pending'])
            && $this->has_verified_connection($connections)
            && $this->normalize_supported_framework((string) ($connections['framework_change_previous'] ?? '')) !== ''
            && $this->normalize_supported_framework((string) ($connections['framework_change_next'] ?? '')) !== '';
    }

    private function get_framework_regeneration_step(array $connections): string {
        $raw_preferred_client = $this->sanitize_key_compat((string) ($connections['preferred_client'] ?? ''));
        $preferred_client = $raw_preferred_client === 'other' ? 'generic' : $this->normalize_connection_client($raw_preferred_client);
        $claude_connection_target = $this->normalize_claude_connection_target((string) ($connections['claude_connection_target'] ?? ''));
        $raw_connection_mode = $this->sanitize_key_compat((string) ($connections['connection_mode'] ?? ''));

        if ($raw_preferred_client === '') {
            return 'choose_client';
        }

        if ($preferred_client === 'claude' && $claude_connection_target === '') {
            return 'choose_claude_target';
        }

        if (!in_array($raw_connection_mode, ['local', 'remote'], true)) {
            return 'choose_mode';
        }

        return 'confirm_details';
    }

    private function get_dashboard_hero_content(string $tab): array {
        $hero = [
            'setup' => [
                'title'    => __('Bridge Setup', 'livecanvas-forge-ai'),
                'subtitle' => __('Run the onboarding wizard, confirm the framework, and prepare the plugin for local or remote AI-assisted development.', 'livecanvas-forge-ai'),
            ],
            'genesis' => [
                'title'    => __('Project Brief & Build Plan', 'livecanvas-forge-ai'),
                'subtitle' => __('Define the persistent project brief and generate a reusable execution plan after your coding agent connection is ready.', 'livecanvas-forge-ai'),
            ],
            'connections' => [
                'title'    => __('Connections', 'livecanvas-forge-ai'),
                'subtitle' => __('Configure the transport contract, remote credentials, package URLs, and preferred AI client from a single control surface.', 'livecanvas-forge-ai'),
            ],
            'studio' => [
                'title'    => __('AI Studio', 'livecanvas-forge-ai'),
                'subtitle' => __('Inspect WordPress-native abilities, MCP exposure, AI readiness, and recent audited runs from a single operational view.', 'livecanvas-forge-ai'),
            ],
            'command' => [
                'title'    => __('Command Deck', 'livecanvas-forge-ai'),
                'subtitle' => __('Preview or apply concrete LiveCanvas operations from inside WordPress using the same contract exposed by the REST API.', 'livecanvas-forge-ai'),
            ],
        ];

        return $hero[$tab] ?? $hero['connections'];
    }

    public function render_dashboard_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = LCFA_Settings::get();
        $snapshot = $this->environment->get_snapshot();
        $notice   = LCFA_Settings::consume_notice();
        $tab      = sanitize_key($_GET['tab'] ?? $this->get_default_dashboard_tab($settings));

        if (!in_array($tab, ['setup', 'genesis', 'connections', 'studio', 'command'], true)) {
            $tab = $this->get_default_dashboard_tab($settings);
        }

        echo '<div class="wrap lcfa-admin">';
        $this->render_page_header($tab, $snapshot, $settings);

        $this->render_notice($notice);
        $this->render_internal_tabs($tab, $settings);

        if (!$settings['completed'] && $tab !== 'setup') {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Bridge Setup is not complete yet. Finish the setup flow before using the operational dashboard.', 'livecanvas-forge-ai');
            echo ' <a href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=setup')) . '">' . esc_html__('Open Bridge Setup', 'livecanvas-forge-ai') . '</a>';
            echo '</p></div>';
        }

        switch ($tab) {
            case 'setup':
                $this->render_setup_tab($settings, $snapshot);
                break;

            case 'connections':
                $this->render_connections_tab($settings, $snapshot);
                break;

            case 'studio':
                $this->render_studio_tab($settings, $snapshot);
                break;

            case 'command':
                $this->render_command_tab($settings, $snapshot);
                break;

            case 'genesis':
            default:
                $brief      = LCFA_Settings::get_project_brief();
                $plan       = LCFA_Settings::get_genesis_plan();
                $progress   = LCFA_Settings::get_genesis_progress();
                $brief_hash = LCFA_Settings::get_project_brief_hash($brief);
                $summary    = $this->inventory->get_summary();
                $this->render_genesis_tab($settings, $snapshot, $brief, $summary, $plan, $progress, $brief_hash);
                break;
        }

        echo '</div>';
    }

    public function render_connections_page(): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function render_command_page(): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=command'));
        exit;
    }

    private function render_internal_tabs(string $current_tab, array $settings): void {
        $tabs = [
            'setup' => ['label' => __('Setup', 'livecanvas-forge-ai'), 'icon' => 'shield-check'],
            'connections' => ['label' => __('Connections', 'livecanvas-forge-ai'), 'icon' => 'plug'],
            'genesis' => ['label' => __('Project Brief', 'livecanvas-forge-ai'), 'icon' => 'stars'],
            'studio' => ['label' => __('AI Studio', 'livecanvas-forge-ai'), 'icon' => 'sparkles'],
            'command' => ['label' => __('Command Deck', 'livecanvas-forge-ai'), 'icon' => 'command'],
        ];

        echo '<div class="lcfa-tabbar">';
        echo '<nav class="lcfa-tabs">';
        foreach ($tabs as $tab => $data) {
            $classes = 'lcfa-tab' . ($tab === $current_tab ? ' is-current' : '');
            $url     = admin_url('admin.php?page=lcfa-dashboard&tab=' . $tab);
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '">';
            echo '<span class="lcfa-step-icon-wrap">' . $this->get_icon_svg($data['icon']) . '</span>';
            echo '<span>' . esc_html($data['label']) . '</span>';
            echo '</a>';
        }
        echo '</nav>';

        echo '</div>';
    }

    private function render_setup_tab(array $settings, array $snapshot): void {
        $step = max(1, absint($_GET['step'] ?? 1));

        if ($step > 1 && !$snapshot['livecanvas_active']) {
            $step = 1;
        }

        echo '<div class="lcfa-main">';
        $this->render_step_nav($step, $settings, $snapshot);

        switch ($step) {
            case 1:
                $this->render_preflight_step($snapshot);
                break;
            case 2:
                $this->render_framework_step($settings, $snapshot);
                break;
            case 3:
                $this->render_site_mode_step($settings, $snapshot);
                break;
            case 4:
                $this->render_ai_tool_step($settings);
                break;
            case 5:
                $this->render_permissions_step($settings);
                break;
            case 6:
            default:
                $this->render_finish_step($settings, $snapshot);
                break;
        }

        $this->render_setup_reset_panel();

        echo '</div>';
    }

    private function render_genesis_tab(array $settings, array $snapshot, array $brief, array $summary, array $plan, array $progress, string $brief_hash): void {
        $plan_pages      = is_array($plan['pages'] ?? null) ? $plan['pages'] : [];
        $plan_tasks      = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $plan_counts     = is_array($plan['counts'] ?? null) ? $plan['counts'] : [];
        $plan_stack      = is_array($plan['stack'] ?? null) ? $plan['stack'] : [];
        $progress_tasks  = is_array($progress['tasks'] ?? null) ? $progress['tasks'] : [];
        $execution_plan  = $this->get_genesis_executor()->get_execution_plan();
        $execution_counts = is_array($execution_plan['counts'] ?? null) ? $execution_plan['counts'] : [];
        $plan_available  = !empty($plan);
        $plan_stale      = $plan_available && (
            (string) ($plan['brief_hash'] ?? '') !== $brief_hash
            || (string) ($plan_stack['framework'] ?? '') !== (string) ($snapshot['detected_framework'] ?? '')
            || (string) ($plan_stack['theme'] ?? '') !== (string) ($snapshot['current_theme_stylesheet'] ?? '')
            || (string) ($plan_stack['site_mode'] ?? '') !== (string) ($snapshot['site_mode'] ?? '')
        );
        $next_task       = !$plan_stale && is_array($execution_plan['next_task'] ?? null)
            ? $execution_plan['next_task']
            : (!$plan_stale ? $this->get_next_genesis_task($plan_tasks, $progress_tasks) : null);

        echo '<div class="lcfa-main">';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('stars');
        echo '<div><h2>' . esc_html__('Project Brief', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Capture the persistent project context used to generate a reusable build plan after your coding agent connection is ready.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_project_brief');
        echo '<input type="hidden" name="action" value="lcfa_project_brief">';

        echo '<label><span>' . esc_html__('Project mode', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="project_mode">';
        echo '<option value="from_scratch"' . selected($brief['project_mode'], 'from_scratch', false) . '>' . esc_html__('Build from scratch', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="step_by_step"' . selected($brief['project_mode'], 'step_by_step', false) . '>' . esc_html__('Step-by-step evolution', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__('Brand name', 'livecanvas-forge-ai') . '</span><input type="text" name="brand_name" value="' . esc_attr($brief['brand_name']) . '"></label>';
        echo '<label><span>' . esc_html__('Sector', 'livecanvas-forge-ai') . '</span><input type="text" name="sector" value="' . esc_attr($brief['sector']) . '"></label>';
        echo '<label><span>' . esc_html__('Tone of voice', 'livecanvas-forge-ai') . '</span><input type="text" name="tone" value="' . esc_attr($brief['tone']) . '"></label>';

        echo '<label><span>' . esc_html__('Logo status', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="logo_status">';
        echo '<option value="existing"' . selected($brief['logo_status'], 'existing', false) . '>' . esc_html__('Already available', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="to_generate"' . selected($brief['logo_status'], 'to_generate', false) . '>' . esc_html__('Needs generation', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="not_needed"' . selected($brief['logo_status'], 'not_needed', false) . '>' . esc_html__('Not required', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__('Required pages', 'livecanvas-forge-ai') . '</span><textarea name="required_pages" rows="4">' . esc_textarea($brief['required_pages']) . '</textarea></label>';
        echo '<label><span>' . esc_html__('Additional notes', 'livecanvas-forge-ai') . '</span><textarea name="notes" rows="5">' . esc_textarea($brief['notes']) . '</textarea></label>';

        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary">' . esc_html__('Save Project Brief', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('layers');
        echo '<div><h2>' . esc_html__('Build plan', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Turn the project brief into an actionable sequence of pages and tasks that can be loaded directly into the Command Deck.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . ($plan_available && !$plan_stale ? ' is-positive' : ($plan_stale ? ' is-negative' : '')) . '">' . esc_html($plan_available ? ($plan_stale ? __('Plan outdated', 'livecanvas-forge-ai') : __('Plan ready', 'livecanvas-forge-ai')) : __('No plan yet', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Project mode: %s', 'livecanvas-forge-ai'), (string) ($brief['project_mode'] ?: 'from_scratch'))) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Framework: %s', 'livecanvas-forge-ai'), (string) ($snapshot['detected_framework'] ?: 'unknown'))) . '</span>';
        if ($plan_available && !empty($plan_counts['tasks'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Tasks: %d', 'livecanvas-forge-ai'), (int) $plan_counts['tasks'])) . '</span>';
        }
        if ($plan_available && !empty($plan_counts['pages'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Pages: %d', 'livecanvas-forge-ai'), (int) $plan_counts['pages'])) . '</span>';
        }
        if ($plan_available) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Pending: %d', 'livecanvas-forge-ai'), (int) ($execution_counts['pending'] ?? $this->count_genesis_tasks_by_status($progress_tasks, 'pending')))) . '</span>';
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Previewed: %d', 'livecanvas-forge-ai'), (int) ($execution_counts['previewed'] ?? $this->count_genesis_tasks_by_status($progress_tasks, 'previewed')))) . '</span>';
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Completed: %d', 'livecanvas-forge-ai'), (int) ($execution_counts['applied'] ?? $this->count_genesis_tasks_by_status($progress_tasks, 'applied')))) . '</span>';
            echo '<span class="lcfa-chip' . (((int) ($execution_counts['failed'] ?? $this->count_genesis_tasks_by_status($progress_tasks, 'failed'))) > 0 ? ' is-negative' : '') . '">' . esc_html(sprintf(__('Failed: %d', 'livecanvas-forge-ai'), (int) ($execution_counts['failed'] ?? $this->count_genesis_tasks_by_status($progress_tasks, 'failed')))) . '</span>';
        }
        echo '</div>';

        if ($plan_stale) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('The project brief changed after the last plan generation. Regenerate the plan before using task deep-links.', 'livecanvas-forge-ai') . '</p></div>';
        } elseif (!$plan_available) {
            echo '<p>' . esc_html__('No build plan has been generated yet. Generate one from the current brief to get page suggestions and task deep-links.', 'livecanvas-forge-ai') . '</p>';
        } elseif (!empty($plan['generated_at'])) {
            echo '<p>' . esc_html(sprintf(__('Last generated at %s.', 'livecanvas-forge-ai'), get_date_from_gmt((string) $plan['generated_at'], get_option('date_format') . ' ' . get_option('time_format')))) . '</p>';
        }

        echo '<details class="lcfa-command-details" data-lcfa-command-thread-tools>';
        echo '<summary>' . esc_html__('Thread tools', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-command-details__body">';
        echo '<div class="lcfa-cta-row">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_generate_plan');
        echo '<input type="hidden" name="action" value="lcfa_generate_plan">';
        echo '<button class="button button-primary" type="submit">' . esc_html($plan_available ? __('Regenerate build plan', 'livecanvas-forge-ai') : __('Generate build plan', 'livecanvas-forge-ai')) . '</button>';
        echo '</form>';
        if ($plan_available && !$plan_stale && is_array($next_task)) {
            echo $this->render_genesis_execute_form('preview_next', '', __('Preview next task', 'livecanvas-forge-ai'), false, 'button');
            echo $this->render_genesis_execute_form('apply_next', '', __('Apply next task', 'livecanvas-forge-ai'), false, 'button button-primary');
        }
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=command')) . '">' . esc_html__('Open Command Deck', 'livecanvas-forge-ai') . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections')) . '">' . esc_html__('Open Connections', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';

        if ($plan_available && !$plan_stale && is_array($next_task)) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Execution controls', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html__('Use Genesis as the execution queue for the project brief. Preview first when you want to inspect output, or apply directly when the task is deterministic.', 'livecanvas-forge-ai') . '</p>';
            echo '<div class="lcfa-chip-row">';
            if (!empty($next_task['stage'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Next stage: %s', 'livecanvas-forge-ai'), (string) $next_task['stage'])) . '</span>';
            }
            echo '<span class="lcfa-chip is-positive">' . esc_html(sprintf(__('Next task: %s', 'livecanvas-forge-ai'), (string) ($next_task['label'] ?? __('Genesis task', 'livecanvas-forge-ai')))) . '</span>';
            echo '</div>';
            echo '</div>';
        }

        if ($plan_available) {
            $this->render_genesis_plan_panel($plan_pages, $plan_tasks, $progress_tasks);
        }
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Operations overview', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Once the brief is stored, use Connections and Command Deck from the same screen to execute the build progressively.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('Inventory ready: %1$d pages, %2$d headers, %3$d footers, %4$d dynamic templates.', 'livecanvas-forge-ai'), $summary['pages'], $summary['headers'], $summary['footers'], $summary['dynamic_templates'])) . '</li>';
        echo '<li>' . esc_html__('Execution model: preview first, diff when content changes, apply only when requested.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Use this brief as the persistent input for generating structure, copy, sections, and dynamic templates.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';
        echo '</section>';

        echo '</div>';
    }

    private function render_genesis_plan_panel(array $pages, array $tasks, array $progress_tasks): void {
        if ($pages) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Planned pages', 'livecanvas-forge-ai') . '</h3>';
            echo '<div class="lcfa-genesis-page-grid">';
            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }

                echo '<article class="lcfa-genesis-page-card">';
                echo '<div class="lcfa-chip-row">';
                if (!empty($page['kind'])) {
                    echo '<span class="lcfa-chip">' . esc_html((string) $page['kind']) . '</span>';
                }
                if (!empty($page['homepage'])) {
                    echo '<span class="lcfa-chip is-positive">' . esc_html__('Homepage', 'livecanvas-forge-ai') . '</span>';
                }
                echo '</div>';
                echo '<strong>' . esc_html((string) ($page['title'] ?? __('Untitled', 'livecanvas-forge-ai'))) . '</strong>';
                echo '<code>' . esc_html((string) ($page['slug'] ?? '')) . '</code>';
                echo '<p>' . esc_html((string) ($page['description'] ?? '')) . '</p>';
                echo '</article>';
            }
            echo '</div>';
            echo '</div>';
        }

        if (!$tasks) {
            return;
        }

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Suggested execution order', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-genesis-task-list">';

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $payload = is_array($task['payload'] ?? null) ? $task['payload'] : [];
            $task_url = $this->get_genesis_task_command_url($task);
            $task_id = sanitize_key((string) ($task['id'] ?? ''));
            $task_progress = $task_id !== '' && isset($progress_tasks[$task_id]) && is_array($progress_tasks[$task_id]) ? $progress_tasks[$task_id] : [];
            $task_status = $this->get_genesis_task_status_label($task_progress);

            echo '<article class="lcfa-genesis-task">';
            echo '<div class="lcfa-history-copy">';
            echo '<strong>' . esc_html((string) ($task['label'] ?? __('Genesis task', 'livecanvas-forge-ai'))) . '</strong>';
            echo '<span>' . esc_html((string) ($task['description'] ?? '')) . '</span>';
            echo '</div>';
            echo '<div class="lcfa-chip-row">';
            if (!empty($task['stage'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Stage: %s', 'livecanvas-forge-ai'), (string) $task['stage'])) . '</span>';
            }
            if ($task_status['label'] !== '') {
                echo '<span class="lcfa-chip' . esc_attr($task_status['class']) . '">' . esc_html($task_status['label']) . '</span>';
            }
            if (!empty($payload['action'])) {
                echo '<span class="lcfa-chip is-positive">' . esc_html((string) $payload['action']) . '</span>';
            } else {
                echo '<span class="lcfa-chip">' . esc_html__('Advisory', 'livecanvas-forge-ai') . '</span>';
            }
            echo '</div>';
            if (!empty($task['user_prompt'])) {
                echo '<p class="lcfa-result-message">' . esc_html((string) $task['user_prompt']) . '</p>';
            }
            if (!empty($task_progress['message'])) {
                echo '<p class="lcfa-result-message">' . esc_html((string) $task_progress['message']) . '</p>';
            }
            echo '<div class="lcfa-cta-row">';
            if (!empty($payload['action'])) {
                echo $this->render_genesis_execute_form('preview_task', $task_id, __('Preview task', 'livecanvas-forge-ai'), true, 'button button-small');
                $apply_label = (($task_progress['status'] ?? '') === 'failed')
                    ? __('Retry task', 'livecanvas-forge-ai')
                    : __('Apply task', 'livecanvas-forge-ai');
                echo $this->render_genesis_execute_form(($task_progress['status'] ?? '') === 'failed' ? 'retry_task' : 'apply_task', $task_id, $apply_label, true, 'button button-small button-primary');
            } else {
                echo $this->render_genesis_execute_form('acknowledge_task', $task_id, __('Mark reviewed', 'livecanvas-forge-ai'), true, 'button button-small');
            }
            if ($task_url !== '') {
                echo '<a class="button button-small button-primary" href="' . esc_url($task_url) . '">' . esc_html__('Load in Command Deck', 'livecanvas-forge-ai') . '</a>';
            }
            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '</div>';
        echo '</details>';
        echo '</details>';
    }

    private function get_genesis_task_command_url(array $task): string {
        $payload = is_array($task['payload'] ?? null) ? $task['payload'] : [];

        if (empty($payload['action'])) {
            return '';
        }

        $redirect_args = $this->build_command_redirect_args($payload);

        if (!empty($task['user_prompt'])) {
            $redirect_args['user_prompt'] = sanitize_textarea_field((string) $task['user_prompt']);
        }

        if (!empty($task['id'])) {
            $redirect_args['genesis_task_id'] = sanitize_key((string) $task['id']);
        }

        if (!isset($redirect_args['execution_target'])) {
            $redirect_args['execution_target'] = 'local';
        }

        return $this->get_command_url($redirect_args);
    }

    private function get_next_genesis_task(array $tasks, array $progress_tasks): ?array {
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = sanitize_key((string) ($task['id'] ?? ''));
            $status  = $task_id !== '' && !empty($progress_tasks[$task_id]['status'])
                ? (string) $progress_tasks[$task_id]['status']
                : 'pending';

            if ($status !== 'applied') {
                return $task;
            }
        }

        return null;
    }

    private function get_genesis_executor(): LCFA_Genesis_Executor {
        if (!$this->genesis_executor instanceof LCFA_Genesis_Executor) {
            $this->genesis_executor = new LCFA_Genesis_Executor($this->environment, $this->command_deck);
        }

        return $this->genesis_executor;
    }

    private function render_genesis_execute_form(string $execution_mode, string $task_id, string $label, bool $small = false, string $button_class = 'button'): string {
        $classes = trim($button_class);

        if ($small && strpos($classes, 'button-small') === false) {
            $classes .= ' button-small';
        }

        ob_start();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_genesis_execute');
        echo '<input type="hidden" name="action" value="lcfa_genesis_execute">';
        echo '<input type="hidden" name="execution_mode" value="' . esc_attr($execution_mode) . '">';
        echo '<input type="hidden" name="thread_id" value="default">';
        echo '<input type="hidden" name="execution_target" value="local">';
        if ($task_id !== '') {
            echo '<input type="hidden" name="task_id" value="' . esc_attr($task_id) . '">';
        }
        echo '<button class="' . esc_attr($classes) . '" type="submit">' . esc_html($label) . '</button>';
        echo '</form>';

        return (string) ob_get_clean();
    }

    private function count_genesis_tasks_by_status(array $progress_tasks, string $status): int {
        $count = 0;

        foreach ($progress_tasks as $task_progress) {
            if (!is_array($task_progress)) {
                continue;
            }

            if ((string) ($task_progress['status'] ?? '') === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function get_genesis_task_status_label(array $progress): array {
        $status = (string) ($progress['status'] ?? 'pending');

        switch ($status) {
            case 'applied':
                return [
                    'label' => __('Applied', 'livecanvas-forge-ai'),
                    'class' => ' is-positive',
                ];
            case 'previewed':
                return [
                    'label' => __('Previewed', 'livecanvas-forge-ai'),
                    'class' => '',
                ];
            case 'failed':
                return [
                    'label' => __('Needs attention', 'livecanvas-forge-ai'),
                    'class' => ' is-negative',
                ];
            default:
                return [
                    'label' => __('Pending', 'livecanvas-forge-ai'),
                    'class' => '',
                ];
        }
    }

    private function render_connections_tab(array $settings, array $snapshot): void {
        $connections      = LCFA_Settings::get_connections();
        $stored_preferred_client = trim((string) ($connections['preferred_client'] ?? ''));
        $preferred_client = $this->normalize_connection_client($stored_preferred_client !== '' ? $stored_preferred_client : 'codex');
        $default_mode     = $preferred_client === 'codex' ? 'remote' : 'local';
        $selected_mode    = $this->normalize_connection_mode((string) ($connections['connection_mode'] ?: $default_mode));
        if ($selected_mode === 'local' && method_exists('LCFA_Settings', 'sync_local_workspace_root')) {
            $connections = LCFA_Settings::sync_local_workspace_root(true);
        }
        $show_codex_fast_path = $stored_preferred_client === '' || $preferred_client === 'codex';
        $connection_test  = LCFA_Settings::consume_connection_test_result();
        $is_local_mode    = $selected_mode === 'local';
        $remote_status    = $is_local_mode ? [] : $this->remote_client->get_status();
        $mcp_status       = $is_local_mode ? $this->get_deferred_mcp_status($snapshot) : $this->context_builder->get_mcp_status();
        $mcp_bootstrap    = $is_local_mode ? $this->get_lightweight_bootstrap_payload($connections, $snapshot) : $this->context_builder->get_bootstrap_payload();
        $bundle = $this->build_selected_connection_bundle([
            'preferred_client' => $preferred_client,
            'connection_mode'  => $selected_mode,
            'workspace_root'   => $connections['workspace_root'],
        ]);
        $codex_onboarding = $this->get_codex_onboarding_state($connections, $bundle, $selected_mode, $remote_status);
        if (!empty($codex_onboarding['should_invalidate_ready'])) {
            $connections['connection_status'] = 'needs_attention';
            $connections['connection_last_error'] = (string) ($codex_onboarding['message'] ?? __('Codex config is stale. Regenerate or sync the Codex MCP config.', 'livecanvas-forge-ai'));
            $connections['connection_current_step'] = 'smoke_test';
            LCFA_Settings::update_connections($connections);
            $codex_onboarding = $this->get_codex_onboarding_state($connections, $bundle, $selected_mode, $remote_status);
        }
        $onboarding_state = $this->connection_onboarding->derive_state($connections, [
            'local_ready'  => !empty($mcp_status['rest_base']),
            'remote_ready' => !empty($remote_status['available']),
        ]);
        if ($show_codex_fast_path) {
            $onboarding_state['status'] = (string) ($codex_onboarding['status'] ?? 'needs_setup');
            $onboarding_state['message'] = (string) ($codex_onboarding['message'] ?? '');
        }
        $workspace_write_state = LCFA_Workspace_Access::inspect((string) ($bundle['workspace_root'] ?? ''));
        $wizard_view = $this->connection_wizard_presenter->build([
            'state'            => $onboarding_state,
            'bundle'           => $bundle,
            'workspace_access' => $workspace_write_state,
        ]);

        echo '<div class="lcfa-main">';

        $this->render_connection_test_result($connection_test);
        if (!$show_codex_fast_path) {
            $this->render_connection_onboarding_hero($bundle, $onboarding_state, $mcp_status, $snapshot, $selected_mode);
        }

        if ($this->has_framework_change_connection_decision($connections)) {
            $this->render_connection_framework_change_decision_card(
                $connections,
                (string) ($connections['framework_change_previous'] ?? ''),
                (string) ($connections['framework_change_next'] ?? '')
            );
        } elseif ($show_codex_fast_path) {
            $this->render_codex_fast_path_panel($codex_onboarding, $bundle, $connections, $selected_mode, $workspace_write_state);
            $this->render_power_mode_status_card($connections, $snapshot);
            $this->render_codex_other_clients_panel($connections, $selected_mode);
        } elseif (($onboarding_state['status'] ?? 'not_connected') === 'ready') {
            $this->render_connection_ready_card($wizard_view, $bundle, $connections, $workspace_write_state);
        } else {
            $this->render_connection_wizard($wizard_view, $bundle, $connections, $preferred_client, $selected_mode, $mcp_bootstrap, $settings, $snapshot, $mcp_status, $onboarding_state, $workspace_write_state);
        }

        $this->render_connections_secondary_panels();

        echo '</div>';
    }

    private function render_connection_onboarding_hero(array $bundle, array $state, array $mcp_status, array $snapshot, string $selected_mode): void {
        $status = (string) ($state['status'] ?? 'not_connected');
        $status_label = ucfirst(str_replace('_', ' ', $status));
        $status_class = $status === 'ready' ? ' is-positive' : (($status === 'needs_attention' || $status === 'not_connected') ? ' is-negative' : '');
        $client_label = ucfirst(str_replace('-', ' ', (string) ($bundle['client'] ?? 'codex')));

        echo '<section class="lcfa-card lcfa-onboarding-hero">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Connection status', 'livecanvas-forge-ai') . '</h2></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip lcfa-chip--agent">' . $this->get_agent_icon_markup((string) ($bundle['client'] ?? 'codex'), 'stars') . '<span>' . esc_html(sprintf(__('AI agent: %s', 'livecanvas-forge-ai'), $client_label)) . '</span></span>';
        echo '<span class="lcfa-chip' . $status_class . '">' . esc_html($status_label) . '</span>';
        echo '</div>';
        if (!empty($state['message'])) {
            echo '<p class="lcfa-guide-copy">' . esc_html((string) $state['message']) . '</p>';
        }
        echo '</section>';
    }

    private function get_codex_onboarding_state(array $connections, array $bundle, string $selected_mode, array $remote_status): array {
        $mode = $this->normalize_connection_mode((string) ($bundle['mode'] ?? $selected_mode));
        $connection_status = sanitize_key((string) ($connections['connection_status'] ?? ''));
        $current_step = sanitize_key((string) ($connections['connection_current_step'] ?? ''));
        $last_verified = trim((string) ($connections['connection_last_verified_at'] ?? ''));
        $last_error = trim((string) ($connections['connection_last_error'] ?? ''));
        $status = 'needs_setup';
        $primary_action = 'sync_codex';
        $message = __('Connect Codex to this WordPress site.', 'livecanvas-forge-ai');
        $checks = [];
        $manual_fallback = [];
        $should_invalidate_ready = false;

        if ($mode === 'remote') {
            return $this->get_direct_agent_onboarding()->get_codex_direct_state($connections, $bundle, $remote_status);
        }

        if (!class_exists('LCFA_Codex_Config_Manager', false)) {
            return [
                'mode' => 'local',
                'status' => 'needs_setup',
                'primary_action' => 'none',
                'checks' => [
                    'wp_root_ok' => ['label' => __('WordPress root', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Codex config manager is unavailable.', 'livecanvas-forge-ai')],
                    'mcp_script_ok' => ['label' => __('MCP script', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Codex config manager is unavailable.', 'livecanvas-forge-ai')],
                    'codex_config_synced' => ['label' => __('Codex config', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Codex config manager is unavailable.', 'livecanvas-forge-ai')],
                    'hash_matches' => ['label' => __('Fingerprint', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Pending.', 'livecanvas-forge-ai')],
                    'restart_required' => ['label' => __('Restart/reload', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Pending.', 'livecanvas-forge-ai')],
                    'smoke_passed' => ['label' => __('Smoke test', 'livecanvas-forge-ai'), 'ok' => false, 'message' => __('Pending.', 'livecanvas-forge-ai')],
                ],
                'message' => __('Codex config manager is not available in this runtime.', 'livecanvas-forge-ai'),
                'manual_fallback' => [],
                'last_smoke' => ['verified_at' => $last_verified, 'error' => $last_error],
                'should_invalidate_ready' => false,
            ];
        }

        $manager = $this->get_codex_config_manager();
        $expected = $manager->get_expected_config($connections);
        $inspection = $manager->inspect($connections);
        $stored_hash = trim((string) ($connections['connection_last_bundle_hash'] ?? ''));
        $hash_matches = $stored_hash !== '' && hash_equals((string) ($expected['hash'] ?? ''), $stored_hash);
        $synced = !empty($inspection['synced']);
        $config_scope = sanitize_key((string) ($expected['config_scope'] ?? 'project'));
        $config_path = (string) ($expected['config_path'] ?? '');
        $site_fingerprint = (string) ($expected['site_fingerprint'] ?? '');
        $script_path = (string) ($expected['script_path'] ?? '');
        $script_ok = $script_path !== '' && is_readable($script_path);
        $wp_root = untrailingslashit((string) ($expected['wp_root'] ?? ''));
        $wp_root_ok = $wp_root !== '' && is_file($wp_root . '/wp-load.php');
        $smoke_passed = $connection_status === 'ready' && $last_verified !== '' && $synced && $hash_matches;
        $restart_required = $synced && $hash_matches && !$smoke_passed && ($current_step === 'smoke_test' || strpos($last_error, 'Restart Codex') !== false);
        $should_invalidate_ready = $connection_status === 'ready' && (!$synced || !$hash_matches);

        if ($smoke_passed) {
            $status = 'ready';
            $primary_action = 'none';
            $message = __('Codex is connected and the smoke test passed.', 'livecanvas-forge-ai');
        } elseif (!$synced || !$hash_matches || !$script_ok || !$wp_root_ok) {
            $status = trim((string) ($connections['preferred_client'] ?? '')) === '' && (string) ($inspection['status'] ?? '') === 'missing'
                ? 'needs_setup'
                : 'stale';
            $primary_action = 'sync_codex';
            $message = !$synced
                ? (string) ($inspection['message'] ?? __('Codex config is stale. Sync the Codex MCP config.', 'livecanvas-forge-ai'))
                : (!$hash_matches ? __('Codex config changed since the last smoke test. Repair Codex and rerun the smoke test.', 'livecanvas-forge-ai') : __('Codex local prerequisites need attention before testing.', 'livecanvas-forge-ai'));
        } elseif ($connection_status === 'needs_attention') {
            $status = 'test_failed';
            $primary_action = 'run_smoke';
            $message = $last_error !== '' ? $last_error : __('The last Codex smoke test failed.', 'livecanvas-forge-ai');
        } elseif ($restart_required) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = __('Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai');
        } else {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = __('Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai');
        }

        $checks = [
            'codex_config_scope' => [
                'label' => __('Codex scope', 'livecanvas-forge-ai'),
                'ok' => $config_scope === 'project',
                'message' => $config_scope === 'project'
                    ? __('Project-scoped config keeps this Codex project tied to this WordPress site.', 'livecanvas-forge-ai')
                    : __('Global config is advanced and can be shared across Codex projects.', 'livecanvas-forge-ai'),
            ],
            'wp_root_ok' => [
                'label' => __('WordPress root', 'livecanvas-forge-ai'),
                'ok' => $wp_root_ok,
                'message' => $wp_root_ok ? __('Current local site path detected.', 'livecanvas-forge-ai') : __('Current WordPress root is missing or stale.', 'livecanvas-forge-ai'),
            ],
            'mcp_script_ok' => [
                'label' => __('MCP script', 'livecanvas-forge-ai'),
                'ok' => $script_ok,
                'message' => $script_ok ? __('Readable.', 'livecanvas-forge-ai') : __('Missing or not readable.', 'livecanvas-forge-ai'),
            ],
            'codex_config_synced' => [
                'label' => __('Codex config', 'livecanvas-forge-ai'),
                'ok' => $synced,
                'message' => $synced ? __('Synced.', 'livecanvas-forge-ai') : __('Missing or stale.', 'livecanvas-forge-ai'),
            ],
            'hash_matches' => [
                'label' => __('Fingerprint', 'livecanvas-forge-ai'),
                'ok' => $hash_matches,
                'message' => $hash_matches ? __('Matches current token and paths.', 'livecanvas-forge-ai') : __('Needs a fresh sync and smoke test.', 'livecanvas-forge-ai'),
            ],
            'restart_required' => [
                'label' => __('Restart/reload', 'livecanvas-forge-ai'),
                'ok' => $restart_required,
                'message' => $restart_required ? __('Required before smoke test.', 'livecanvas-forge-ai') : __('Not required right now.', 'livecanvas-forge-ai'),
            ],
            'smoke_passed' => [
                'label' => __('Smoke test', 'livecanvas-forge-ai'),
                'ok' => $smoke_passed,
                'message' => $smoke_passed ? __('Passed.', 'livecanvas-forge-ai') : __('Pending.', 'livecanvas-forge-ai'),
            ],
        ];

        return [
            'mode' => 'local',
            'status' => $status,
            'primary_action' => $primary_action,
            'checks' => $checks,
            'message' => $message,
            'config_scope' => $config_scope,
            'config_path' => $config_path,
            'site_fingerprint' => $site_fingerprint,
            'manual_fallback' => [
                'remove_command' => (string) ($expected['remove_command'] ?? ''),
                'add_command' => (string) ($expected['add_command'] ?? ''),
                'snippet' => (string) ($expected['snippet'] ?? ''),
                'config_scope' => $config_scope,
                'config_path' => $config_path,
                'global_config_path' => (string) ($expected['global_config_path'] ?? ''),
                'site_fingerprint' => $site_fingerprint,
            ],
            'last_smoke' => [
                'verified_at' => $last_verified,
                'error' => $last_error,
            ],
            'should_invalidate_ready' => $should_invalidate_ready,
        ];
    }

    private function get_remote_codex_prerequisites(array $connections, array $remote_status): array {
        return $this->get_direct_agent_onboarding()->get_remote_codex_prerequisites($connections, $remote_status);
    }

    private function render_codex_fast_path_panel(array $state, array $bundle, array $connections, string $selected_mode, array $workspace_write_state): void {
        $mode = $this->normalize_connection_mode((string) ($state['connection_mode'] ?? ($bundle['mode'] ?? $selected_mode)));
        $state_mode = sanitize_key((string) ($state['mode'] ?? $mode));
        $status = sanitize_key((string) ($state['status'] ?? 'needs_setup'));
        $primary_action = sanitize_key((string) ($state['primary_action'] ?? 'sync_codex'));
        $status_labels = [
            'needs_setup' => __('Needs setup', 'livecanvas-forge-ai'),
            'missing_credentials' => __('Missing credentials', 'livecanvas-forge-ai'),
            'stale' => __('Config stale', 'livecanvas-forge-ai'),
            'restart_required' => __('Restart required', 'livecanvas-forge-ai'),
            'test_failed' => __('Test failed', 'livecanvas-forge-ai'),
            'ready' => __('Ready', 'livecanvas-forge-ai'),
        ];
        $status_label = (string) ($status_labels[$status] ?? $status_labels['needs_setup']);
        $checks = is_array($state['checks'] ?? null) ? $state['checks'] : [];
        $last_smoke = is_array($state['last_smoke'] ?? null) ? $state['last_smoke'] : [];

        echo '<section class="lcfa-card lcfa-ready-card lcfa-codex-fast-path">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Connect Codex', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Follow the highlighted step. Technical checks and manual commands stay collapsed unless you need them.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        $this->render_codex_mode_switch($mode, (string) ($bundle['workspace_root'] ?? ''));

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip lcfa-chip--agent">' . $this->get_agent_icon_markup('codex', 'stars') . '<span>' . esc_html__('Codex', 'livecanvas-forge-ai') . '</span></span>';
        echo '<span class="lcfa-chip' . ($status === 'ready' ? ' is-positive' : (in_array($status, ['test_failed', 'missing_credentials'], true) ? ' is-negative' : '')) . '">' . esc_html($status_label) . '</span>';
        $mode_label = $state_mode === 'direct' || $mode === 'remote' ? __('Direct Mode', 'livecanvas-forge-ai') : __('Local runtime', 'livecanvas-forge-ai');
        $target_label = $mode === 'remote' ? __('WordPress API', 'livecanvas-forge-ai') : __('Local filesystem', 'livecanvas-forge-ai');
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Mode: %s', 'livecanvas-forge-ai'), $mode_label)) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Target: %s', 'livecanvas-forge-ai'), $target_label)) . '</span>';
        if (!empty($state['strategy'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Strategy: %s', 'livecanvas-forge-ai'), (string) $state['strategy'])) . '</span>';
        }
        if ($mode === 'local') {
            $config_scope = sanitize_key((string) ($state['config_scope'] ?? 'project'));
            $config_scope_label = $config_scope === 'global' ? __('Global config', 'livecanvas-forge-ai') : __('Project config', 'livecanvas-forge-ai');
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Codex scope: %s', 'livecanvas-forge-ai'), $config_scope_label)) . '</span>';
            if (!empty($state['site_fingerprint'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Site ID: %s', 'livecanvas-forge-ai'), (string) $state['site_fingerprint'])) . '</span>';
            }
        }
        echo '</div>';

        $this->render_codex_next_step_alert($status, $mode, $state);

        if ($mode === 'remote') {
            $manual_fallback = is_array($state['manual_fallback'] ?? null) ? $state['manual_fallback'] : [];
            $prerequisites = is_array($manual_fallback['prerequisites'] ?? null) ? $manual_fallback['prerequisites'] : [];
            if (empty($prerequisites['ready'])) {
                $this->render_codex_direct_credentials_form($connections);
                $this->render_remote_codex_prerequisites($prerequisites);
            }
        }

        $this->render_codex_technical_checks($checks);

        echo '<div class="lcfa-cta-row">';
        if ($status === 'ready') {
            $this->render_codex_fast_path_smoke_form($mode, __('Re-test', 'livecanvas-forge-ai'));
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_reconfigure_connection');
            echo '<input type="hidden" name="action" value="lcfa_reconfigure_connection">';
            echo '<button class="button" type="submit">' . esc_html__('Change client', 'livecanvas-forge-ai') . '</button>';
            echo '</form>';
        } elseif ($primary_action === 'run_smoke') {
            $this->render_codex_fast_path_smoke_form($mode, __('Run Codex smoke test', 'livecanvas-forge-ai'));
        } elseif ($primary_action === 'sync_codex' || $primary_action === 'connect' || $primary_action === 'repair') {
            $button_label = $status === 'stale' ? __('Repair Codex', 'livecanvas-forge-ai') : __('Connect Codex', 'livecanvas-forge-ai');
            $this->render_codex_fast_path_action_form('connect_codex', $mode, $button_label, true);
        } else {
            echo '<a class="button" href="#lcfa-connections-secondary">' . esc_html__('Open settings', 'livecanvas-forge-ai') . '</a>';
        }
        echo '</div>';

        if (!empty($last_smoke['verified_at']) || !empty($last_smoke['error'])) {
            echo '<div class="lcfa-workspace-note">';
            echo '<strong>' . esc_html__('Last smoke test', 'livecanvas-forge-ai') . '</strong>';
            if (!empty($last_smoke['verified_at'])) {
                echo '<p>' . esc_html(sprintf(__('Verified at: %s', 'livecanvas-forge-ai'), (string) $last_smoke['verified_at'])) . '</p>';
            }
            if (!empty($last_smoke['error'])) {
                echo '<p>' . esc_html((string) $last_smoke['error']) . '</p>';
            }
            echo '</div>';
        }

        if ($status !== 'missing_credentials') {
            $this->render_codex_manual_fallback($mode, is_array($state['manual_fallback'] ?? null) ? $state['manual_fallback'] : [], $bundle, $status);
        }

        if ($mode === 'local' && !empty($bundle['workspace_files']) && empty($workspace_write_state['available'])) {
            echo '<div class="lcfa-workspace-note">';
            echo '<strong>' . esc_html__('Workspace write fallback available', 'livecanvas-forge-ai') . '</strong>';
            echo '<p>' . esc_html($this->get_workspace_write_notice($workspace_write_state)) . '</p>';
            echo '</div>';
        }

        echo '</section>';
    }

    private function render_codex_next_step_alert(string $status, string $mode, array $state): void {
        $guidance = $this->get_codex_next_step_guidance($status, $mode);
        $alert_class = 'lcfa-connection-now-alert lcfa-connection-now-alert--' . sanitize_html_class($status);

        echo '<div class="' . esc_attr($alert_class) . '">';
        echo '<span class="lcfa-connection-now-alert__eyebrow">' . esc_html((string) $guidance['eyebrow']) . '</span>';
        echo '<strong>' . esc_html((string) $guidance['title']) . '</strong>';
        if (!empty($guidance['description'])) {
            echo '<p>' . esc_html((string) $guidance['description']) . '</p>';
        }
        if (!empty($guidance['steps']) && is_array($guidance['steps'])) {
            echo '<ol class="lcfa-step-list">';
            foreach ($guidance['steps'] as $step) {
                echo '<li>' . esc_html((string) $step) . '</li>';
            }
            echo '</ol>';
        }
        if (!empty($state['message']) && !in_array($status, ['missing_credentials', 'restart_required', 'ready'], true)) {
            echo '<small>' . esc_html((string) $state['message']) . '</small>';
        }
        echo '</div>';
    }

    private function get_codex_next_step_guidance(string $status, string $mode): array {
        if ($mode === 'remote') {
            switch ($status) {
                case 'missing_credentials':
                    return [
                        'eyebrow' => __('Step 1', 'livecanvas-forge-ai'),
                        'title' => __('Add WordPress credentials', 'livecanvas-forge-ai'),
                        'description' => __('Direct Mode needs this site URL, a WordPress username, and an Application Password before it can generate the Codex setup.', 'livecanvas-forge-ai'),
                        'steps' => [
                            __('Create an Application Password from Users > Profile > Application Passwords.', 'livecanvas-forge-ai'),
                            __('Paste the site URL, username, and password in the form below.', 'livecanvas-forge-ai'),
                            __('Save credentials, then click Connect Codex.', 'livecanvas-forge-ai'),
                        ],
                    ];
                case 'restart_required':
                    return [
                        'eyebrow' => __('Step 3', 'livecanvas-forge-ai'),
                        'title' => __('Reload Codex, then test', 'livecanvas-forge-ai'),
                        'description' => __('The Codex setup has been generated. Codex must reload the MCP server before WordPress can verify it.', 'livecanvas-forge-ai'),
                        'steps' => [
                            __('Prefer copying the Project TOML below into the .codex/config.toml of the Codex project for this site.', 'livecanvas-forge-ai'),
                            __('Use the global shell shortcut only as an advanced fallback.', 'livecanvas-forge-ai'),
                            __('Restart Codex or reload the livecanvas-forge MCP server.', 'livecanvas-forge-ai'),
                            __('Come back here and run the Codex smoke test.', 'livecanvas-forge-ai'),
                        ],
                    ];
                case 'test_failed':
                    return [
                        'eyebrow' => __('Fix required', 'livecanvas-forge-ai'),
                        'title' => __('Smoke test failed', 'livecanvas-forge-ai'),
                        'description' => __('The site answered, but Codex could not complete the MCP handshake. Re-check the setup command and credentials, then test again.', 'livecanvas-forge-ai'),
                        'steps' => [
                            __('Confirm the Application Password is still valid.', 'livecanvas-forge-ai'),
                            __('Confirm Codex has the livecanvas-forge MCP server registered.', 'livecanvas-forge-ai'),
                            __('Run the smoke test again.', 'livecanvas-forge-ai'),
                        ],
                    ];
                case 'ready':
                    return [
                        'eyebrow' => __('Ready', 'livecanvas-forge-ai'),
                        'title' => __('Codex is connected', 'livecanvas-forge-ai'),
                        'description' => __('You can now test the integration from Codex or from the LiveCanvas frontend drawer.', 'livecanvas-forge-ai'),
                        'steps' => [
                            __('In Codex, call livecanvas-forge-ai/get-connection-handoff.', 'livecanvas-forge-ai'),
                            __('Ask for a read-only audit first.', 'livecanvas-forge-ai'),
                            __('Enable apply abilities only when you want Codex to write pages or templates.', 'livecanvas-forge-ai'),
                        ],
                    ];
                default:
                    return [
                        'eyebrow' => __('Step 2', 'livecanvas-forge-ai'),
                        'title' => __('Generate the Codex setup', 'livecanvas-forge-ai'),
                        'description' => __('Credentials are present. Now generate the WordPress MCP Adapter setup for Codex.', 'livecanvas-forge-ai'),
                        'steps' => [
                            __('Click Connect Codex.', 'livecanvas-forge-ai'),
                            __('Copy the Project TOML into the Codex project .codex/config.toml when it appears.', 'livecanvas-forge-ai'),
                            __('Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai'),
                        ],
                    ];
            }
        }

        switch ($status) {
            case 'restart_required':
                return [
                    'eyebrow' => __('Step 2', 'livecanvas-forge-ai'),
                    'title' => __('Local runtime needs a Codex reload', 'livecanvas-forge-ai'),
                    'description' => __('This is the advanced local runtime path. If you wanted the easier setup, select Direct Mode above and click Use selected mode.', 'livecanvas-forge-ai'),
                    'steps' => [
                        __('Continue local runtime only if Codex must access this machine files or local build tools.', 'livecanvas-forge-ai'),
                        __('AI Bridge now writes a project-scoped .codex/config.toml inside this WordPress folder by default.', 'livecanvas-forge-ai'),
                        __('Restart Codex or reload the livecanvas-forge MCP server.', 'livecanvas-forge-ai'),
                        __('Return here and run the Codex smoke test.', 'livecanvas-forge-ai'),
                    ],
                ];
            case 'test_failed':
                return [
                    'eyebrow' => __('Fix required', 'livecanvas-forge-ai'),
                    'title' => __('Local smoke test failed', 'livecanvas-forge-ai'),
                    'description' => __('Review the last smoke test message, then repair the local Codex config and test again.', 'livecanvas-forge-ai'),
                    'steps' => [
                        __('Click Repair Codex if the config, token, or site path changed.', 'livecanvas-forge-ai'),
                        __('Restart Codex or reload the MCP server.', 'livecanvas-forge-ai'),
                        __('Run the smoke test again.', 'livecanvas-forge-ai'),
                    ],
                ];
            case 'ready':
                return [
                    'eyebrow' => __('Ready', 'livecanvas-forge-ai'),
                    'title' => __('Codex is connected', 'livecanvas-forge-ai'),
                    'description' => __('The local MCP bridge passed the smoke test.', 'livecanvas-forge-ai'),
                    'steps' => [
                        __('In Codex, call get_connection_handoff.', 'livecanvas-forge-ai'),
                        __('Start with a read-only request before enabling writes.', 'livecanvas-forge-ai'),
                    ],
                ];
            default:
                return [
                    'eyebrow' => __('Advanced path', 'livecanvas-forge-ai'),
                    'title' => __('Local runtime is selected', 'livecanvas-forge-ai'),
                    'description' => __('For plug-and-play setup, select Direct Mode above and click Use selected mode. Local runtime is only for filesystem access, LocalWP path detection, or local build tools.', 'livecanvas-forge-ai'),
                    'steps' => [
                        __('Recommended: switch to Direct Mode and use WordPress URL plus Application Password.', 'livecanvas-forge-ai'),
                        __('Advanced only: click Connect Codex or Repair Codex to write this project .codex/config.toml.', 'livecanvas-forge-ai'),
                        __('If you continue local runtime, restart Codex before testing.', 'livecanvas-forge-ai'),
                    ],
                ];
        }
    }

    private function render_codex_direct_credentials_form(array $connections): void {
        $site_url = trim((string) ($connections['remote_site_url'] ?? ''));
        if ($site_url === '' && function_exists('home_url')) {
            $site_url = home_url('/');
        }
        $has_password = trim((string) ($connections['remote_application_password'] ?? '')) !== '';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-direct-credentials">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="preferred_client" value="codex">';
        echo '<input type="hidden" name="connection_mode" value="remote">';
        echo '<h3>' . esc_html__('Step 1: WordPress credentials', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html__('Use an Application Password, not your normal WordPress password.', 'livecanvas-forge-ai') . '</p>';
        echo '<label><span>' . esc_html__('Site URL', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_site_url" value="' . esc_attr($site_url) . '" placeholder="https://example.com"></label>';
        echo '<label><span>' . esc_html__('WordPress username', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_username" value="' . esc_attr((string) ($connections['remote_username'] ?? '')) . '"></label>';
        echo '<label><span>' . esc_html__('Application Password', 'livecanvas-forge-ai') . '</span><input type="password" name="remote_application_password" value="" placeholder="' . esc_attr($has_password ? __('Stored. Leave blank to keep current value.', 'livecanvas-forge-ai') : __('xxxx xxxx xxxx xxxx xxxx xxxx', 'livecanvas-forge-ai')) . '"></label>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html__('Save credentials', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_codex_technical_checks(array $checks): void {
        if ($checks === []) {
            return;
        }

        echo '<details class="lcfa-advanced-settings lcfa-codex-technical-checks">';
        echo '<summary>' . esc_html__('Technical checks', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-chip-row">';
        foreach ($checks as $check_key => $check) {
            if (!is_array($check)) {
                continue;
            }
            $ok = !empty($check['ok']);
            $skipped = !empty($check['skipped']);
            $chip_class = $ok && !$skipped ? ' is-positive' : (!$ok && !$skipped ? ' is-negative' : '');
            $check_value = $ok ? __('OK', 'livecanvas-forge-ai') : __('Pending', 'livecanvas-forge-ai');
            if ($skipped) {
                $check_value = __('N/A', 'livecanvas-forge-ai');
            } elseif ($check_key === 'restart_required') {
                $chip_class = $ok ? ' is-negative' : ' is-positive';
                $check_value = $ok ? __('Required', 'livecanvas-forge-ai') : __('No', 'livecanvas-forge-ai');
            }
            echo '<span class="lcfa-chip' . esc_attr($chip_class) . '" title="' . esc_attr((string) ($check['message'] ?? '')) . '">' . esc_html((string) ($check['label'] ?? $check_key)) . ': ' . esc_html($check_value) . '</span>';
        }
        echo '</div>';
        echo '</details>';
    }

    private function render_codex_mode_switch(string $selected_mode, string $workspace_root): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard lcfa-codex-mode-switch" data-lcfa-mode-switch data-lcfa-current-mode="' . esc_attr($selected_mode) . '">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_mode">';
        echo '<input type="hidden" name="preferred_client" value="codex">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr($workspace_root) . '">';
        echo '<p class="lcfa-guide-copy">' . esc_html__('Recommended: use Direct Mode for both local and remote WordPress sites. It connects Codex through the WordPress API with an Application Password and avoids local runtime setup.', 'livecanvas-forge-ai') . '</p>';
        echo '<div class="lcfa-radio-group lcfa-radio-group--inline">';
        $this->render_radio('connection_mode', 'remote', __('Direct Mode (recommended)', 'livecanvas-forge-ai'), $selected_mode, 'cloud', __('Best first setup: WordPress URL, username, and Application Password.', 'livecanvas-forge-ai'));
        $this->render_radio('connection_mode', 'local', __('Local runtime (advanced)', 'livecanvas-forge-ai'), $selected_mode, 'command', __('Use only when Codex must access local files or compile local build assets.', 'livecanvas-forge-ai'));
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button lcfa-mode-switch-submit" type="submit" disabled data-lcfa-mode-switch-submit data-lcfa-idle-label="' . esc_attr__('Current mode selected', 'livecanvas-forge-ai') . '" data-lcfa-active-label="' . esc_attr__('Use selected mode', 'livecanvas-forge-ai') . '">' . esc_html__('Current mode selected', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_remote_codex_prerequisites(array $prerequisites): void {
        $items = is_array($prerequisites['items'] ?? null) ? $prerequisites['items'] : [];
        echo '<div class="lcfa-connection-now-alert">';
        echo '<span class="lcfa-connection-now-alert__eyebrow">' . esc_html__('Remote Codex prerequisites', 'livecanvas-forge-ai') . '</span>';
        echo '<strong>' . esc_html__('Complete these fields before AI Bridge generates a remote Codex config.', 'livecanvas-forge-ai') . '</strong>';
        echo '<ul class="lcfa-plain-list">';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            echo '<li>' . esc_html((!empty($item['ok']) ? 'OK: ' : 'Missing: ') . (string) ($item['label'] ?? '')) . '</li>';
        }
        echo '</ul>';
        echo '<p>' . esc_html__('Remote Codex uses npx -y @automattic/mcp-wordpress-remote@latest with WP_API_URL, WP_API_USERNAME, WP_API_PASSWORD, and LOG_FILE. It does not use LCFA_WP_ROOT.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';
    }

    private function render_codex_fast_path_action_form(string $action, string $mode, string $button_label, bool $primary = false): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_repair_codex_connection');
        echo '<input type="hidden" name="action" value="lcfa_repair_codex_connection">';
        echo '<input type="hidden" name="codex_repair_action" value="' . esc_attr($action) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($mode) . '">';
        echo '<button class="button ' . ($primary ? 'button-primary ' : '') . 'lcfa-button--wide" type="submit">' . esc_html($button_label) . '</button>';
        echo '</form>';
    }

    private function render_codex_fast_path_smoke_form(string $mode, string $button_label): void {
        $this->render_codex_fast_path_action_form('smoke', $mode, $button_label, true);
    }

    private function render_codex_manual_fallback(string $mode, array $manual_fallback, array $bundle, string $status = ''): void {
        $open = $mode === 'remote' && in_array($status, ['needs_setup', 'restart_required'], true) ? ' open' : '';
        $summary = $mode === 'remote' ? __('Codex setup command', 'livecanvas-forge-ai') : __('Advanced/manual fallback', 'livecanvas-forge-ai');
        echo '<details class="lcfa-advanced-settings"' . $open . '>';
        echo '<summary>' . esc_html($summary) . '</summary>';
        if ($mode === 'remote') {
            $shortcut = (string) ($manual_fallback['shortcut_command'] ?? ($bundle['copy_command_string'] ?? ''));
            $command = (string) ($manual_fallback['command'] ?? ($bundle['command_string'] ?? ''));
            $snippet = (string) ($manual_fallback['codex_config_snippet'] ?? ($bundle['codex_config_snippet'] ?? ''));
            $project_config_path = (string) ($manual_fallback['codex_project_config_path'] ?? ($bundle['codex_project_config_path'] ?? '.codex/config.toml'));
            echo '<p class="lcfa-guide-copy">' . esc_html__('Use the project .codex/config.toml snippet when you manage multiple WordPress sites from Codex. The shell shortcut below writes a global MCP entry and is advanced fallback.', 'livecanvas-forge-ai') . '</p>';
            if ($project_config_path !== '') {
                $this->render_code_block($project_config_path, [
                    'language' => 'text',
                    'label' => __('Codex project config', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy config path', 'livecanvas-forge-ai'),
                ]);
            }
            if ($snippet !== '') {
                $this->render_code_block($snippet, [
                    'language' => 'toml',
                    'label' => __('Project TOML', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy project TOML', 'livecanvas-forge-ai'),
                ]);
            }
            if ($shortcut !== '') {
                $this->render_code_block($shortcut, [
                    'language' => 'bash',
                    'label' => __('Advanced global shortcut', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy Codex remote shortcut', 'livecanvas-forge-ai'),
                ]);
            }
            if ($command !== '' && $command !== $shortcut) {
                $this->render_code_block($command, [
                    'language' => 'bash',
                    'label' => __('Command', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy command', 'livecanvas-forge-ai'),
                ]);
            }
            echo '<p class="lcfa-guide-copy">' . esc_html(sprintf(__('Start tool: %s', 'livecanvas-forge-ai'), (string) ($manual_fallback['start_tool'] ?? 'livecanvas-forge-ai/get-connection-handoff'))) . '</p>';
        } else {
            $config_path = (string) ($manual_fallback['config_path'] ?? '');
            $config_scope = sanitize_key((string) ($manual_fallback['config_scope'] ?? 'project'));
            echo '<p class="lcfa-guide-copy">' . esc_html($config_scope === 'global'
                ? __('Use this if PHP cannot write the global Codex config for this local machine.', 'livecanvas-forge-ai')
                : __('Use this if PHP cannot write the project Codex config inside this WordPress folder.', 'livecanvas-forge-ai')) . '</p>';
            if ($config_path !== '') {
                $this->render_code_block($config_path, [
                    'language' => 'text',
                    'label' => __('Config file', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy config path', 'livecanvas-forge-ai'),
                ]);
            }
            $snippet = (string) ($manual_fallback['snippet'] ?? '');
            if ($snippet !== '') {
                $this->render_code_block($snippet, [
                'language' => 'toml',
                'label' => __('TOML', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy config snippet', 'livecanvas-forge-ai'),
                ]);
            }
            if ($config_scope === 'global') {
                $this->render_code_block((string) ($manual_fallback['remove_command'] ?? ''), [
                    'language' => 'bash',
                    'label' => __('Remove', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy remove command', 'livecanvas-forge-ai'),
                ]);
                $this->render_code_block((string) ($manual_fallback['add_command'] ?? ''), [
                    'language' => 'bash',
                    'label' => __('Add', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy add command', 'livecanvas-forge-ai'),
                ]);
            }
        }
        echo '</details>';
    }

    private function render_codex_other_clients_panel(array $connections, string $selected_mode): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Other clients', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('OpenCode, Claude, Cursor, and Generic MCP still use the full connection wizard.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<details class="lcfa-advanced-settings">';
        echo '<summary>' . esc_html__('Choose another MCP client', 'livecanvas-forge-ai') . '</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_client">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($connections['workspace_root'] ?? '')) . '">';
        echo '<div class="lcfa-radio-group lcfa-radio-group--agents">';
        $this->render_radio('preferred_client', 'opencode', __('OpenCode', 'livecanvas-forge-ai'), '', 'braces');
        $this->render_radio('preferred_client', 'claude', __('Claude', 'livecanvas-forge-ai'), '', 'cpu');
        $this->render_radio('preferred_client', 'cursor', __('Cursor', 'livecanvas-forge-ai'), '', 'cursor');
        $this->render_radio('preferred_client', 'generic', __('Generic MCP client', 'livecanvas-forge-ai'), '', 'plug');
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button" type="submit">' . esc_html__('Open client wizard', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</details>';
        echo '</section>';
    }

    private function render_power_mode_status_card(array $connections, array $snapshot): void {
        $power_state = $this->get_power_mode()->get_state($connections, $snapshot);
        $enabled = !empty($power_state['enabled']);

        echo '<section class="lcfa-card lcfa-power-mode-status">';
        echo '<details class="lcfa-advanced-settings">';
        echo '<summary>' . esc_html__('Advanced: Power Mode status', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('shield-check');
        echo '<div><h2>' . esc_html__('Power Mode status', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Prepared foundation for advanced filesystem, WP-CLI, upload, admin-link, and sandbox tools. These tools are not exposed to agents in this release.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . ($enabled ? ' is-warning' : ' is-positive') . '">' . esc_html($enabled ? __('Policy: enabled', 'livecanvas-forge-ai') : __('Policy: disabled', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Setting: %s', 'livecanvas-forge-ai'), (string) ($power_state['setting'] ?? 'auto'))) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Site: %s', 'livecanvas-forge-ai'), (string) ($power_state['site_mode'] ?? 'unknown'))) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Environment: %s', 'livecanvas-forge-ai'), (string) ($power_state['environment_type'] ?? 'production'))) . '</span>';
        echo '</div>';
        echo '<p class="lcfa-guide-copy">' . esc_html((string) ($power_state['reason'] ?? '')) . '</p>';
        if (empty($power_state['implemented'])) {
            echo '<p class="description">' . esc_html__('Next block: expose the advanced Power Mode tools behind explicit admin policy and per-tool guardrails.', 'livecanvas-forge-ai') . '</p>';
        }
        echo '</details>';
        echo '</section>';
    }

    private function get_codex_repair_state(array $connections, array $bundle): array {
        if ((string) ($bundle['client'] ?? '') !== 'codex' || (string) ($bundle['mode'] ?? '') !== 'local') {
            return ['enabled' => false];
        }

        if (!class_exists('LCFA_Codex_Config_Manager', false)) {
            return ['enabled' => false];
        }

        $manager = $this->get_codex_config_manager();
        $expected = $manager->get_expected_config($connections);
        $inspection = $manager->inspect($connections);
        $stored_hash = trim((string) ($connections['connection_last_bundle_hash'] ?? ''));
        $hash_matches = $stored_hash !== '' && hash_equals((string) $expected['hash'], $stored_hash);
        $synced = !empty($inspection['synced']);
        $message = !$synced
            ? (string) ($inspection['message'] ?? __('Codex config is stale. Regenerate or sync the Codex MCP config.', 'livecanvas-forge-ai'))
            : (!$hash_matches ? __('Codex config changed since the last smoke test. Rerun the smoke test.', 'livecanvas-forge-ai') : __('Codex config is aligned with this site.', 'livecanvas-forge-ai'));

        return [
            'enabled'                 => true,
            'expected'                => $expected,
            'inspection'              => $inspection,
            'synced'                  => $synced,
            'hash_matches'            => $hash_matches,
            'message'                 => $message,
            'should_invalidate_ready' => (string) ($connections['connection_status'] ?? '') === 'ready' && (!$synced || !$hash_matches),
        ];
    }

    private function render_codex_repair_panel(array $state): void {
        $expected = is_array($state['expected'] ?? null) ? $state['expected'] : [];
        $inspection = is_array($state['inspection'] ?? null) ? $state['inspection'] : [];
        $synced = !empty($state['synced']);
        $hash_matches = !empty($state['hash_matches']);
        $config_status = (string) ($inspection['status'] ?? 'unknown');
        $config_label = $synced ? __('synced', 'livecanvas-forge-ai') : ($config_status === 'missing' ? __('missing', 'livecanvas-forge-ai') : __('stale', 'livecanvas-forge-ai'));
        $script_ok = is_readable((string) ($expected['script_path'] ?? ''));
        $wp_root = (string) ($expected['wp_root'] ?? '');
        $wp_root_ok = $wp_root !== '' && is_file($wp_root . '/wp-load.php');

        echo '<section class="lcfa-card lcfa-ready-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Repair Codex Connection', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Use this when Codex has an old MCP token, an old plugin path, or a stale WordPress root in the project .codex/config.toml.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . ($wp_root_ok ? ' is-positive' : ' is-negative') . '">' . esc_html(sprintf(__('WordPress root: %s', 'livecanvas-forge-ai'), $wp_root_ok ? 'OK' : 'check')) . '</span>';
        echo '<span class="lcfa-chip is-positive">' . esc_html__('MCP token: current', 'livecanvas-forge-ai') . '</span>';
        echo '<span class="lcfa-chip' . ($synced ? ' is-positive' : ' is-negative') . '">' . esc_html(sprintf(__('Codex config: %s', 'livecanvas-forge-ai'), $config_label)) . '</span>';
        echo '<span class="lcfa-chip' . ($script_ok ? ' is-positive' : ' is-negative') . '">' . esc_html(sprintf(__('MCP script: %s', 'livecanvas-forge-ai'), $script_ok ? 'OK' : 'missing')) . '</span>';
        echo '<span class="lcfa-chip' . ($hash_matches ? ' is-positive' : '') . '">' . esc_html(sprintf(__('Smoke fingerprint: %s', 'livecanvas-forge-ai'), $hash_matches ? 'OK' : 'pending')) . '</span>';
        echo '</div>';
        echo '<div class="lcfa-connection-now-alert">';
        echo '<span class="lcfa-connection-now-alert__eyebrow">' . esc_html__('Codex repair status', 'livecanvas-forge-ai') . '</span>';
        echo '<strong>' . esc_html((string) ($state['message'] ?? __('Codex config should be checked.', 'livecanvas-forge-ai'))) . '</strong>';
        echo '<p>' . esc_html__('After updating the Codex project config, Codex must be restarted or the current MCP server must be reloaded before the new config is used.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';

        echo '<div class="lcfa-choice-grid lcfa-choice-grid--actions">';
        $this->render_codex_repair_action('sync_wp', __('Detect current WordPress path', 'livecanvas-forge-ai'), __('Sync WordPress connection settings', 'livecanvas-forge-ai'), __('Stores the current local ABSPATH as the active workspace root when the saved root is stale.', 'livecanvas-forge-ai'));
        $this->render_codex_repair_action('sync_codex', __('Update Codex config', 'livecanvas-forge-ai'), __('Write project .codex/config.toml', 'livecanvas-forge-ai'), __('Updates only mcp_servers.livecanvas-forge and creates a backup first.', 'livecanvas-forge-ai'), true);
        $this->render_codex_repair_action('smoke', __('Run smoke test', 'livecanvas-forge-ai'), __('Verify Codex MCP locally', 'livecanvas-forge-ai'), __('Runs REST health, Node, script, and get_mcp_status checks with the current token.', 'livecanvas-forge-ai'));
        echo '</div>';

        echo '<details class="lcfa-advanced-settings">';
        echo '<summary>' . esc_html__('Manual fallback', 'livecanvas-forge-ai') . '</summary>';
        echo '<p class="lcfa-guide-copy">' . esc_html__('Use this snippet if PHP cannot write the project Codex config for this local machine.', 'livecanvas-forge-ai') . '</p>';
        if (!empty($expected['config_path'])) {
            $this->render_code_block((string) $expected['config_path'], [
                'language' => 'text',
                'label' => __('Config file', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy config path', 'livecanvas-forge-ai'),
            ]);
        }
        $this->render_code_block((string) ($expected['snippet'] ?? ''), [
            'language' => 'toml',
            'label' => __('TOML', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy config snippet', 'livecanvas-forge-ai'),
        ]);
        if (sanitize_key((string) ($expected['config_scope'] ?? 'project')) === 'global') {
            $this->render_code_block((string) ($expected['remove_command'] ?? ''), [
                'language' => 'bash',
                'label' => __('Remove', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy remove command', 'livecanvas-forge-ai'),
            ]);
            $this->render_code_block((string) ($expected['add_command'] ?? ''), [
                'language' => 'bash',
                'label' => __('Add', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy add command', 'livecanvas-forge-ai'),
            ]);
        }
        echo '</details>';
        echo '</section>';
    }

    private function render_codex_repair_action(string $action, string $eyebrow, string $title, string $description, bool $primary = false): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action ' . ($primary ? 'lcfa-choice-card--primary' : 'lcfa-choice-card--secondary') . '">';
        wp_nonce_field('lcfa_repair_codex_connection');
        echo '<input type="hidden" name="action" value="lcfa_repair_codex_connection">';
        echo '<input type="hidden" name="codex_repair_action" value="' . esc_attr($action) . '">';
        echo '<span class="lcfa-choice-eyebrow">' . esc_html($eyebrow) . '</span>';
        echo '<span class="lcfa-choice-copy"><strong>' . esc_html($title) . '</strong><span>' . esc_html($description) . '</span></span>';
        echo '<button class="button ' . ($primary ? 'button-primary ' : '') . 'lcfa-button--wide" type="submit">' . esc_html($title) . '</button>';
        echo '</form>';
    }

    private function render_connection_framework_change_decision_card(array $connections, string $previous_framework, string $next_framework): void {
        $client = $this->normalize_connection_client((string) ($connections['preferred_client'] ?? 'codex'));
        $client_label = ucfirst(str_replace('-', ' ', $client));
        $previous_framework_label = $this->get_framework_display_name($previous_framework);
        $next_framework_label = $this->get_framework_display_name($next_framework);

        echo '<section class="lcfa-card lcfa-ready-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html(sprintf(__('Reuse the existing %s connection?', 'livecanvas-forge-ai'), $client_label)) . '</h2><p>' . esc_html__('The site framework changed, but this coding-agent connection was already verified. Decide whether to keep using the current connection or regenerate a fresh bundle for the new stack.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip is-positive">' . esc_html__('Verified connection found', 'livecanvas-forge-ai') . '</span>';
        echo '<span class="lcfa-chip lcfa-chip--agent">' . $this->get_agent_icon_markup($client, 'stars') . '<span>' . esc_html(sprintf(__('Client: %s', 'livecanvas-forge-ai'), $client_label)) . '</span></span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('From: %s', 'livecanvas-forge-ai'), $previous_framework_label)) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('To: %s', 'livecanvas-forge-ai'), $next_framework_label)) . '</span>';
        if (!empty($connections['connection_last_verified_at'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Verified: %s', 'livecanvas-forge-ai'), (string) $connections['connection_last_verified_at'])) . '</span>';
        }
        echo '</div>';
        echo '<div class="lcfa-connection-now-alert">';
        echo '<span class="lcfa-connection-now-alert__eyebrow">' . esc_html__('Decision required', 'livecanvas-forge-ai') . '</span>';
        echo '<strong>' . esc_html__('Changing framework does not always require a brand-new coding-agent registration.', 'livecanvas-forge-ai') . '</strong>';
        echo '<p>' . esc_html__('Keep the existing connection if the same machine, workspace, and coding agent are still valid. Generate a new connection if you want to re-run the bundle flow for the new frontend stack.', 'livecanvas-forge-ai') . '</p>';
        echo '<small>' . esc_html__('AI Bridge will not continue the connection flow until you choose one of these two paths.', 'livecanvas-forge-ai') . '</small>';
        echo '</div>';

        echo '<div class="lcfa-choice-grid lcfa-choice-grid--actions">';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--secondary">';
        wp_nonce_field('lcfa_framework_change_connection');
        echo '<input type="hidden" name="action" value="lcfa_resolve_framework_change_connection">';
        echo '<input type="hidden" name="framework_connection_decision" value="keep">';
        echo '<span class="lcfa-choice-eyebrow">' . esc_html__('Keep current setup', 'livecanvas-forge-ai') . '</span>';
        echo '<span class="lcfa-choice-copy">';
        echo '<strong>' . esc_html__('Keep existing connection', 'livecanvas-forge-ai') . '</strong>';
        echo '<span>' . esc_html__('Use the verified connection that is already attached to this site and continue without generating a new bundle.', 'livecanvas-forge-ai') . '</span>';
        echo '<small>' . esc_html__('Best when the same coding agent, machine, and workspace are still in use.', 'livecanvas-forge-ai') . '</small>';
        echo '</span>';
        echo '<button class="button lcfa-button--wide" type="submit">' . esc_html__('Keep existing connection', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--primary">';
        wp_nonce_field('lcfa_framework_change_connection');
        echo '<input type="hidden" name="action" value="lcfa_resolve_framework_change_connection">';
        echo '<input type="hidden" name="framework_connection_decision" value="regenerate">';
        echo '<span class="lcfa-choice-eyebrow">' . esc_html__('New bundle flow', 'livecanvas-forge-ai') . '</span>';
        echo '<span class="lcfa-choice-copy">';
        echo '<strong>' . esc_html__('Generate new connection', 'livecanvas-forge-ai') . '</strong>';
        echo '<span>' . esc_html__('Keep the same coding agent, but reopen the bundle-generation flow so the new stack is confirmed step by step.', 'livecanvas-forge-ai') . '</span>';
        echo '<small>' . esc_html__('AI Bridge preserves the selected client and workspace root, then reopens the connection wizard from the details step.', 'livecanvas-forge-ai') . '</small>';
        echo '</span>';
        echo '<button class="button button-primary lcfa-button--wide" type="submit">' . esc_html__('Generate new connection', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '</div>';
        echo '</section>';
    }

    private function render_connection_wizard(array $wizard_view, array $bundle, array $connections, string $preferred_client, string $selected_mode, array $mcp_bootstrap, array $settings, array $snapshot, array $mcp_status, array $onboarding_state, array $workspace_write_state): void {
        $panel = is_array($wizard_view['active_panel'] ?? null) ? $wizard_view['active_panel'] : [];
        $current_step = (string) ($onboarding_state['current_step'] ?? 'choose_client');

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Connection wizard', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Follow one step at a time. AI Bridge will show the next action clearly and only unlock the next stage when the current one is done.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-wizard__intro">';
        $this->render_connection_stepper((array) ($wizard_view['steps'] ?? []));
        echo '</div>';
        $this->render_connection_active_step_panel($panel, $current_step, $bundle, $connections, $preferred_client, $selected_mode, $mcp_bootstrap, $settings, $snapshot, $mcp_status, $workspace_write_state);
        $this->render_connection_visual_help_strip($wizard_view);
        $this->render_agent_connection_guide($mcp_bootstrap, $settings, $snapshot, $preferred_client, $mcp_status, true);
        $this->render_connection_technical_summary($bundle, !empty($wizard_view['technical_summary']['expanded']));
        echo '</section>';
    }

    private function render_connections_secondary_panels(): void {
        echo '<div id="lcfa-connections-secondary" class="lcfa-connections-secondary" data-lcfa-connections-secondary-root>';
        echo '<section class="lcfa-card lcfa-connections-secondary__panel is-loading" data-lcfa-connections-panel="remote" aria-busy="true">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('cloud');
        echo '<div><h2>' . esc_html__('Advanced remote target', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Loading optional remote companion status and credentials…', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-connections-secondary__placeholder">';
        echo '<span class="lcfa-connections-secondary__line is-wide"></span>';
        echo '<span class="lcfa-connections-secondary__line"></span>';
        echo '<span class="lcfa-connections-secondary__line is-short"></span>';
        echo '</div>';
        echo '</section>';

        echo '<section class="lcfa-card lcfa-connections-secondary__panel is-loading" data-lcfa-connections-panel="advanced" aria-busy="true">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Advanced settings', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Loading transport overrides, raw commands, and diagnostics…', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-connections-secondary__placeholder">';
        echo '<span class="lcfa-connections-secondary__line is-wide"></span>';
        echo '<span class="lcfa-connections-secondary__line"></span>';
        echo '<span class="lcfa-connections-secondary__line"></span>';
        echo '<span class="lcfa-connections-secondary__line is-short"></span>';
        echo '</div>';
        echo '</section>';

        echo '<section class="lcfa-card lcfa-connections-secondary__panel is-loading" data-lcfa-connections-panel="diagnostics" aria-busy="true">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('sparkles');
        echo '<div><h2>' . esc_html__('Ability diagnostics', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Loading WordPress 7 ability and MCP exposure summary…', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-connections-secondary__placeholder">';
        echo '<span class="lcfa-connections-secondary__line is-wide"></span>';
        echo '<span class="lcfa-connections-secondary__line"></span>';
        echo '<span class="lcfa-connections-secondary__line is-short"></span>';
        echo '</div>';
        echo '</section>';
        echo '</div>';
    }

    private function render_connection_ready_card(array $wizard_view, array $bundle, array $connections, array $workspace_write_state): void {
        $panel = is_array($wizard_view['ready_panel'] ?? null) ? $wizard_view['ready_panel'] : [];
        $show_workspace_install = !empty($bundle['workspace_files']) && !empty($workspace_write_state['available']);
        echo '<section class="lcfa-card lcfa-ready-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html((string) ($panel['title'] ?? __('Connection status', 'livecanvas-forge-ai'))) . '</h2><p>' . esc_html((string) ($panel['description'] ?? __('The selected client bundle has already been verified.', 'livecanvas-forge-ai'))) . '</p></div>';
        echo '</div>';
        $this->render_connection_now_alert((array) ($panel['alert'] ?? []));
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip is-positive">' . esc_html((string) ($panel['status_label'] ?? __('Ready', 'livecanvas-forge-ai'))) . '</span>';
        echo '<span class="lcfa-chip lcfa-chip--agent">' . $this->get_agent_icon_markup((string) ($bundle['client'] ?? 'codex'), 'stars') . '<span>' . esc_html(sprintf(__('Client: %s', 'livecanvas-forge-ai'), ucfirst(str_replace('-', ' ', (string) ($bundle['client'] ?? 'codex'))))) . '</span></span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Mode: %s', 'livecanvas-forge-ai'), (string) ($bundle['mode'] ?? 'local'))) . '</span>';
        if (!empty($connections['connection_last_verified_at'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Verified: %s', 'livecanvas-forge-ai'), (string) $connections['connection_last_verified_at'])) . '</span>';
        }
        echo '</div>';
        $this->render_connection_technical_summary($bundle, true);

        echo '<div class="lcfa-cta-row">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_test_connections');
        echo '<input type="hidden" name="action" value="lcfa_test_connections">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
        echo '<button class="button button-primary" type="submit">' . esc_html((string) (($panel['primary_cta']['label'] ?? __('Run checks', 'livecanvas-forge-ai')))) . '</button>';
        echo '</form>';

        if ($show_workspace_install) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_install_client_bundle');
            echo '<input type="hidden" name="action" value="lcfa_install_client_bundle">';
            echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
            echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
            echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
            echo '<button class="button" type="submit">' . esc_html__('Write config in workspace', 'livecanvas-forge-ai') . '</button>';
            echo '</form>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_download_client_bundle');
        echo '<input type="hidden" name="action" value="lcfa_download_client_bundle">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
        echo '<button class="button" type="submit">' . esc_html__('Regenerate bundle', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_reconfigure_connection');
        echo '<input type="hidden" name="action" value="lcfa_reconfigure_connection">';
        echo '<button class="button" type="submit">' . esc_html__('Change coding agent', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</div>';

        if (!empty($bundle['workspace_files']) && empty($workspace_write_state['available'])) {
            echo '<div class="lcfa-workspace-note">';
            echo '<strong>' . esc_html__('Browser write disabled for this workspace', 'livecanvas-forge-ai') . '</strong>';
            echo '<p>' . esc_html($this->get_workspace_write_notice($workspace_write_state)) . '</p>';
            echo '</div>';
        }
        echo '</section>';
    }

    private function render_connection_now_alert(array $alert): void {
        $title = trim((string) ($alert['title'] ?? ''));
        $body = trim((string) ($alert['body'] ?? ''));
        $next = trim((string) ($alert['next'] ?? ''));
        $eyebrow = trim((string) ($alert['eyebrow'] ?? ''));

        if ($title === '' && $body === '' && $next === '') {
            return;
        }

        echo '<div class="lcfa-connection-now-alert">';
        if ($eyebrow !== '') {
            echo '<span class="lcfa-connection-now-alert__eyebrow">' . esc_html($eyebrow) . '</span>';
        }
        if ($title !== '') {
            echo '<strong>' . esc_html($title) . '</strong>';
        }
        if ($body !== '') {
            echo '<p>' . esc_html($body) . '</p>';
        }
        if ($next !== '') {
            echo '<small>' . esc_html($next) . '</small>';
        }
        echo '</div>';
    }

    private function render_connection_stepper(array $steps): void {
        echo '<ol class="lcfa-wizard__steps">';
        foreach ($steps as $step) {
            $state = (string) ($step['state'] ?? 'locked');
            echo '<li class="is-' . esc_attr($state) . '">';
            echo '<span class="lcfa-wizard__step-number">' . esc_html((string) ($step['number'] ?? '')) . '</span>';
            echo '<strong>' . esc_html((string) ($step['title'] ?? '')) . '</strong>';
            echo '</li>';
        }
        echo '</ol>';
    }

    private function render_connection_active_step_panel(array $panel, string $current_step, array $bundle, array $connections, string $preferred_client, string $selected_mode, array $mcp_bootstrap, array $settings, array $snapshot, array $mcp_status, array $workspace_write_state): void {
        $claude_connection_target = $this->normalize_claude_connection_target((string) ($connections['claude_connection_target'] ?? ''));
        $panel_class = 'lcfa-wizard__panel';
        if ($current_step === 'smoke_test') {
            $panel_class .= ' lcfa-wizard__panel--blocking';
        }

        echo '<section class="' . esc_attr($panel_class) . '">';
        echo '<div class="lcfa-wizard__panel-head">';
        echo '<h3>' . esc_html((string) ($panel['title'] ?? __('Connection step', 'livecanvas-forge-ai'))) . '</h3>';
        if (!empty($panel['description'])) {
            echo '<p>' . esc_html((string) $panel['description']) . '</p>';
        }
        echo '</div>';

        switch ($current_step) {
            case 'choose_claude_target':
                $this->render_connection_choose_claude_target_form($preferred_client, $claude_connection_target, $selected_mode, (string) ($bundle['workspace_root'] ?? ''), (string) ($panel['primary_cta']['label'] ?? __('Continue', 'livecanvas-forge-ai')));
                break;

            case 'choose_mode':
                $this->render_connection_choose_mode_form($preferred_client, $claude_connection_target, $selected_mode, (string) ($bundle['workspace_root'] ?? ''), (string) ($panel['primary_cta']['label'] ?? __('Continue', 'livecanvas-forge-ai')));
                break;

            case 'confirm_details':
                $this->render_connection_confirm_details_form($bundle, $preferred_client, $claude_connection_target, $selected_mode, (string) ($panel['primary_cta']['label'] ?? __('Confirm details', 'livecanvas-forge-ai')));
                break;

            case 'generate_bundle':
                $this->render_connection_generate_bundle_actions($bundle, $workspace_write_state, $panel);
                break;

            case 'smoke_test':
                $this->render_connection_smoke_test_form($selected_mode, (string) ($panel['primary_cta']['label'] ?? __('Run smoke test', 'livecanvas-forge-ai')), $bundle);
                break;

            case 'choose_client':
            default:
                $this->render_connection_choose_client_form($preferred_client, $selected_mode, (string) ($bundle['workspace_root'] ?? ''), (string) ($panel['primary_cta']['label'] ?? __('Continue', 'livecanvas-forge-ai')));
                break;
        }

        if ($current_step !== 'choose_client') {
            echo '<div class="lcfa-cta-row">';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_reconfigure_connection');
            echo '<input type="hidden" name="action" value="lcfa_reconfigure_connection">';
            echo '<button class="button" type="submit">' . esc_html__('Change coding agent', 'livecanvas-forge-ai') . '</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '</section>';
    }

    private function render_connection_choose_client_form(string $preferred_client, string $selected_mode, string $workspace_root, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_client">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr($workspace_root) . '">';
        echo '<div class="lcfa-guide"><p class="lcfa-guide-copy">' . esc_html__('Choose the coding agent you want to connect. Each option uses its official client icon so the flow stays recognizable when you switch tools.', 'livecanvas-forge-ai') . '</p></div>';
        echo '<div class="lcfa-radio-group lcfa-radio-group--agents">';
        $this->render_radio('preferred_client', 'codex', __('Codex', 'livecanvas-forge-ai'), $preferred_client, 'stars');
        $this->render_radio('preferred_client', 'opencode', __('OpenCode', 'livecanvas-forge-ai'), $preferred_client, 'braces');
        $this->render_radio('preferred_client', 'claude', __('Claude', 'livecanvas-forge-ai'), $preferred_client, 'cpu');
        $this->render_radio('preferred_client', 'cursor', __('Cursor', 'livecanvas-forge-ai'), $preferred_client, 'cursor');
        $this->render_radio('preferred_client', 'generic', __('Generic MCP client', 'livecanvas-forge-ai'), $preferred_client, 'plug');
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_choose_claude_target_form(string $preferred_client, string $selected_target, string $selected_mode, string $workspace_root, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_claude_target">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr($workspace_root) . '">';
        echo '<div class="lcfa-radio-group lcfa-radio-group--inline">';
        $this->render_radio('claude_connection_target', 'desktop_app', __('Desktop App', 'livecanvas-forge-ai'), $selected_target, 'window-stack');
        $this->render_radio('claude_connection_target', 'cli', __('Command Line Interface', 'livecanvas-forge-ai'), $selected_target, 'command');
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_choose_mode_form(string $preferred_client, string $claude_connection_target, string $selected_mode, string $workspace_root, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_mode">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
        echo '<input type="hidden" name="claude_connection_target" value="' . esc_attr($claude_connection_target) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr($workspace_root) . '">';
        echo '<label><span>' . esc_html__('Connection mode', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="connection_mode">';
        echo '<option value="local"' . selected($selected_mode, 'local', false) . '>' . esc_html__('This local site', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="remote"' . selected($selected_mode, 'remote', false) . '>' . esc_html__('Remote site', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_confirm_details_form(array $bundle, string $preferred_client, string $claude_connection_target, string $selected_mode, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="confirm_details">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
        echo '<input type="hidden" name="claude_connection_target" value="' . esc_attr($claude_connection_target) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';

        echo '<div class="lcfa-wizard__details">';
        if ((string) ($bundle['connection_strategy'] ?? '') === 'remote-mcp-adapter') {
            echo '<div><span>' . esc_html__('MCP Adapter URL', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) ($bundle['mcp_adapter_url'] ?? ($bundle['environment']['WP_API_URL'] ?? ''))) . '</code></div>';
            echo '<div><span>' . esc_html__('Remote user', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) (($bundle['environment']['WP_API_USERNAME'] ?? ''))) . '</code></div>';
            echo '<div><span>' . esc_html__('Remote proxy', 'livecanvas-forge-ai') . '</span><code>@automattic/mcp-wordpress-remote</code></div>';
        } else {
            echo '<div><span>' . esc_html__('REST base', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) (($bundle['environment']['LCFA_REST_BASE'] ?? ''))) . '</code></div>';
            echo '<div><span>' . esc_html__('MCP token', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) (($bundle['environment']['LCFA_MCP_TOKEN'] ?? ''))) . '</code></div>';
        }
        if ($selected_mode === 'local') {
            echo '<label><span>' . esc_html__('Local workspace root', 'livecanvas-forge-ai') . '</span><input type="text" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '" placeholder="/Users/you/project"></label>';
            echo '<p class="lcfa-guide-copy">' . esc_html__('This must be the real project path on your machine, not the runtime mount path used by Local or Docker.', 'livecanvas-forge-ai') . '</p>';
        } else {
            echo '<input type="hidden" name="workspace_root" value="">';
        }
        echo '</div>';
        echo '<p class="lcfa-guide-copy">' . esc_html((string) ($bundle['connection_strategy'] ?? '') === 'remote-mcp-adapter' ? __('If these values look correct, confirm them to generate the Codex remote MCP Adapter shortcut.', 'livecanvas-forge-ai') : __('If these values look correct, confirm them to generate the client bundle for the selected coding agent.', 'livecanvas-forge-ai')) . '</p>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        if ((string) ($bundle['connection_strategy'] ?? '') !== 'remote-mcp-adapter') {
            echo '<button class="button" type="submit" name="rotate_mcp_token" value="1">' . esc_html__('Rotate MCP token', 'livecanvas-forge-ai') . '</button>';
        }
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_generate_bundle_actions(array $bundle, array $workspace_write_state, array $panel): void {
        $show_workspace_install = !empty($bundle['workspace_files']) && !empty($workspace_write_state['available']);
        $secondary_ctas = is_array($panel['secondary_ctas'] ?? null) ? $panel['secondary_ctas'] : [];
        $primary_cta = is_array($panel['primary_cta'] ?? null) ? $panel['primary_cta'] : [];
        $primary_action = (string) ($primary_cta['action'] ?? '');
        $copy_text = (string) ($bundle['copy_command_string'] ?? ($bundle['command_string'] ?? ''));
        $claude_connection_target = (string) ($bundle['claude_connection_target'] ?? '');

        echo '<div class="lcfa-choice-grid lcfa-choice-grid--actions">';
        if (($primary_cta['action'] ?? '') === 'copy_command' && $copy_text !== '') {
            $primary_copy = $this->get_generate_bundle_action_copy($bundle, 'copy_command', true);
            echo '<div class="lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--primary">';
            echo '<span class="lcfa-choice-eyebrow">' . esc_html($primary_copy['eyebrow']) . '</span>';
            echo '<span class="lcfa-choice-copy">';
            echo '<strong>' . esc_html($primary_copy['title']) . '</strong>';
            echo '<span>' . esc_html($primary_copy['description']) . '</span>';
            echo '<small>' . esc_html($primary_copy['note']) . '</small>';
            echo '</span>';
            echo '<button class="button button-primary lcfa-button--wide" type="button" data-lcfa-copy-text="' . esc_attr($copy_text) . '" data-lcfa-copy-label="' . esc_attr((string) ($primary_cta['label'] ?? __('Copy command', 'livecanvas-forge-ai'))) . '" data-lcfa-copied-label="' . esc_attr(__('Copied', 'livecanvas-forge-ai')) . '">' . esc_html((string) ($primary_cta['label'] ?? __('Copy command', 'livecanvas-forge-ai'))) . '</button>';
            echo '</div>';
        } elseif ($show_workspace_install && ($primary_cta['action'] ?? '') === 'install') {
            $primary_install = $this->get_generate_bundle_action_copy($bundle, 'install', true);
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--primary">';
            wp_nonce_field('lcfa_install_client_bundle');
            echo '<input type="hidden" name="action" value="lcfa_install_client_bundle">';
            echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
            echo '<input type="hidden" name="claude_connection_target" value="' . esc_attr($claude_connection_target) . '">';
            echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
            echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
            echo '<span class="lcfa-choice-eyebrow">' . esc_html($primary_install['eyebrow']) . '</span>';
            echo '<span class="lcfa-choice-copy">';
            echo '<strong>' . esc_html($primary_install['title']) . '</strong>';
            echo '<span>' . esc_html($primary_install['description']) . '</span>';
            echo '<small>' . esc_html($primary_install['note']) . '</small>';
            echo '</span>';
            echo '<label class="lcfa-checkbox lcfa-checkbox--card"><input type="checkbox" name="create_backup" value="1"> ' . esc_html__('Create backup before overwrite', 'livecanvas-forge-ai') . '</label>';
            echo '<button class="button button-primary lcfa-button--wide" type="submit">' . esc_html((string) ($panel['primary_cta']['label'] ?? __('Write config in workspace', 'livecanvas-forge-ai'))) . '</button>';
            echo '</form>';
        } else {
            $primary_download = $this->get_generate_bundle_action_copy($bundle, 'download', true);
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--primary">';
            wp_nonce_field('lcfa_download_client_bundle');
            echo '<input type="hidden" name="action" value="lcfa_download_client_bundle">';
            echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
            echo '<input type="hidden" name="claude_connection_target" value="' . esc_attr($claude_connection_target) . '">';
            echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
            echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
            echo '<span class="lcfa-choice-eyebrow">' . esc_html($primary_download['eyebrow']) . '</span>';
            echo '<span class="lcfa-choice-copy">';
            echo '<strong>' . esc_html($primary_download['title']) . '</strong>';
            echo '<span>' . esc_html($primary_download['description']) . '</span>';
            echo '<small>' . esc_html($primary_download['note']) . '</small>';
            echo '</span>';
            echo '<button class="button button-primary lcfa-button--wide" type="submit">' . esc_html((string) ($panel['primary_cta']['label'] ?? __('Download client bundle', 'livecanvas-forge-ai'))) . '</button>';
            echo '</form>';
        }

        foreach ($secondary_ctas as $secondary_cta) {
            $action = (string) ($secondary_cta['action'] ?? '');
            $label = (string) ($secondary_cta['label'] ?? '');

            if ($action === $primary_action) {
                continue;
            }

            if ($action === 'download') {
                $secondary_download = $this->get_generate_bundle_action_copy($bundle, 'download', false);
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--secondary">';
                wp_nonce_field('lcfa_download_client_bundle');
                echo '<input type="hidden" name="action" value="lcfa_download_client_bundle">';
                echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
                echo '<input type="hidden" name="claude_connection_target" value="' . esc_attr($claude_connection_target) . '">';
                echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
                echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
                echo '<span class="lcfa-choice-eyebrow">' . esc_html($secondary_download['eyebrow']) . '</span>';
                echo '<span class="lcfa-choice-copy">';
                echo '<strong>' . esc_html($secondary_download['title']) . '</strong>';
                echo '<span>' . esc_html($secondary_download['description']) . '</span>';
                echo '<small>' . esc_html($secondary_download['note']) . '</small>';
                echo '</span>';
                echo '<button class="button lcfa-button--wide" type="submit">' . esc_html($label ?: __('Download client bundle', 'livecanvas-forge-ai')) . '</button>';
                echo '</form>';
            }

            if ($action === 'copy_command' && $copy_text !== '') {
                $copy_label = $label ?: __('Copy command', 'livecanvas-forge-ai');
                $secondary_copy = $this->get_generate_bundle_action_copy($bundle, 'copy_command', false);
                echo '<div class="lcfa-choice-card lcfa-choice-card--action lcfa-choice-card--secondary">';
                echo '<span class="lcfa-choice-eyebrow">' . esc_html($secondary_copy['eyebrow']) . '</span>';
                echo '<span class="lcfa-choice-copy">';
                echo '<strong>' . esc_html($secondary_copy['title']) . '</strong>';
                echo '<span>' . esc_html($secondary_copy['description']) . '</span>';
                echo '<small>' . esc_html($secondary_copy['note']) . '</small>';
                echo '</span>';
                echo '<button class="button lcfa-button--wide" type="button" data-lcfa-copy-text="' . esc_attr($copy_text) . '" data-lcfa-copy-label="' . esc_attr($copy_label) . '" data-lcfa-copied-label="' . esc_attr(__('Copied', 'livecanvas-forge-ai')) . '">' . esc_html($copy_label) . '</button>';
                echo '</div>';
            }
        }
        echo '</div>';

        if (!empty($bundle['workspace_files']) && empty($workspace_write_state['available'])) {
            echo '<div class="lcfa-workspace-note">';
            echo '<strong>' . esc_html__('Browser write disabled for this workspace', 'livecanvas-forge-ai') . '</strong>';
            echo '<p>' . esc_html($this->get_workspace_write_notice($workspace_write_state)) . '</p>';
            if ((string) ($bundle['client'] ?? '') === 'codex' && (string) ($bundle['mode'] ?? 'local') === 'local') {
                echo '<p>' . esc_html__('Recommended Codex flow: copy the Codex shortcut, run it once in a terminal from this workspace, let it auto-detect the embedded Codex desktop CLI if codex is not in PATH, confirm with codex mcp list or /Applications/Codex.app/Contents/Resources/codex mcp list, then come back here and run the smoke test.', 'livecanvas-forge-ai') . '</p>';
            } else {
                echo '<p>' . esc_html__('Recommended local flow: download the client bundle, open the project in your coding agent, let the local MCP bridge start once, then come back here and run the smoke test.', 'livecanvas-forge-ai') . '</p>';
            }
            echo '</div>';
        }
    }

    private function get_generate_bundle_action_copy(array $bundle, string $action, bool $is_primary): array {
        $is_codex_local = $this->normalize_connection_client((string) ($bundle['client'] ?? '')) === 'codex'
            && (($bundle['mode'] ?? 'local') === 'local');
        $is_codex_remote_adapter = $this->normalize_connection_client((string) ($bundle['client'] ?? '')) === 'codex'
            && (string) ($bundle['connection_strategy'] ?? '') === 'remote-mcp-adapter';
        $is_opencode_local = $this->normalize_connection_client((string) ($bundle['client'] ?? '')) === 'opencode'
            && (($bundle['mode'] ?? 'local') === 'local');

        if ($action === 'install') {
            return [
                'eyebrow'     => $is_primary ? __('Recommended', 'livecanvas-forge-ai') : __('Alternative', 'livecanvas-forge-ai'),
                'title'       => __('Write the config for me', 'livecanvas-forge-ai'),
                'description' => $is_codex_local
                    ? __('AI Bridge writes the generated Codex helper directly into this workspace now. This is the fastest path when the browser can reach the project folder.', 'livecanvas-forge-ai')
                    : __('AI Bridge writes the generated client config directly into this workspace now, so you can verify the connection immediately after.', 'livecanvas-forge-ai'),
                'note'        => __('Optional: enable the backup toggle first if you want to preserve the current file before overwrite.', 'livecanvas-forge-ai'),
            ];
        }

        if ($action === 'copy_command') {
            return [
                'eyebrow'     => $is_primary ? __('Recommended', 'livecanvas-forge-ai') : __('Alternative', 'livecanvas-forge-ai'),
                'title'       => ($is_codex_local || $is_codex_remote_adapter) ? __('Copy and run the Codex shortcut', 'livecanvas-forge-ai') : __('Copy the setup command', 'livecanvas-forge-ai'),
                'description' => $is_codex_remote_adapter
                    ? __('Run it once on the machine where Codex runs. It registers the WordPress MCP Adapter remote proxy with Codex.', 'livecanvas-forge-ai')
                    : ($is_codex_local
                    ? __('Run it once from this exact project root, then return here and move to the smoke test.', 'livecanvas-forge-ai')
                    : __('Use this if you want to execute the setup manually from your coding agent shell.', 'livecanvas-forge-ai')),
                'note'        => $is_codex_remote_adapter
                    ? __('Best for remote WordPress because no local WordPress filesystem is required.', 'livecanvas-forge-ai')
                    : __('Best when the browser cannot write into the host workspace directly.', 'livecanvas-forge-ai'),
            ];
        }

        return [
            'eyebrow'     => $is_primary ? __('Recommended', 'livecanvas-forge-ai') : __('Manual option', 'livecanvas-forge-ai'),
            'title'       => $is_opencode_local ? __('Download the OpenCode config', 'livecanvas-forge-ai') : __('Download and place it yourself', 'livecanvas-forge-ai'),
            'description' => $is_opencode_local
                ? __('Save the generated OpenCode config and place it in the project root before you switch back to OpenCode.', 'livecanvas-forge-ai')
                : __('Use this if you prefer a manual install, want to move the bundle to another machine, or do not want the browser to write inside the workspace.', 'livecanvas-forge-ai'),
            'note'        => __('After the file is in place, come back here and run the smoke test.', 'livecanvas-forge-ai'),
        ];
    }

    private function render_connection_smoke_test_form(string $selected_mode, string $button_label, array $bundle = []): void {
        $is_codex = $this->normalize_connection_client((string) ($bundle['client'] ?? '')) === 'codex';
        if ($is_codex) {
            $workspace_root = trim((string) ($bundle['workspace_root'] ?? ''));
            $helper_path = '';
            $workspace_files = is_array($bundle['workspace_files'] ?? null) ? $bundle['workspace_files'] : [];
            if (isset($workspace_files[0]) && is_array($workspace_files[0])) {
                $helper_path = trim((string) ($workspace_files[0]['path'] ?? ''));
            }
            if ($helper_path === '' && $workspace_root !== '') {
                $helper_path = rtrim($workspace_root, '/\\') . '/livecanvas-forge.codex.sh';
            }

            echo '<div class="lcfa-codex-smoke-guide">';
            echo '<strong>' . esc_html__('To connect Codex, complete these steps in order:', 'livecanvas-forge-ai') . '</strong>';
            echo '<ol>';
            if ((string) ($bundle['connection_strategy'] ?? '') === 'remote-mcp-adapter') {
                echo '<li>' . esc_html__('Run the generated Codex shortcut on the machine where Codex runs. It starts the WordPress MCP Adapter remote proxy.', 'livecanvas-forge-ai') . '</li>';
            } elseif ($workspace_root !== '') {
                echo '<li>' . esc_html__('Open Terminal in this project root:', 'livecanvas-forge-ai') . ' <code>' . esc_html($workspace_root) . '</code></li>';
            } else {
                echo '<li>' . esc_html__('Open Terminal in the WordPress project root selected in this wizard.', 'livecanvas-forge-ai') . '</li>';
            }
            if ($helper_path !== '') {
                echo '<li>' . esc_html__('Run the generated helper:', 'livecanvas-forge-ai') . ' ' . $this->render_inline_copy_command('sh "' . $helper_path . '"') . '</li>';
            } elseif ((string) ($bundle['connection_strategy'] ?? '') === 'remote-mcp-adapter') {
                echo '<li>' . esc_html__('If you downloaded the helper, run it from any trusted local folder; it does not need the WordPress filesystem.', 'livecanvas-forge-ai') . '</li>';
            } else {
                echo '<li>' . esc_html__('Run the generated livecanvas-forge.codex.sh helper from that project root.', 'livecanvas-forge-ai') . '</li>';
            }
            echo '<li>' . esc_html__('Verify that Codex can see the MCP server:', 'livecanvas-forge-ai') . ' ' . $this->render_inline_copy_command('codex mcp list') . ' ' . esc_html__('or', 'livecanvas-forge-ai') . ' ' . $this->render_inline_copy_command('/Applications/Codex.app/Contents/Resources/codex mcp list') . '</li>';
            echo '<li>' . esc_html__('The command output must show:', 'livecanvas-forge-ai') . ' <code>livecanvas-forge</code>. ' . esc_html__('If the output does not show livecanvas-forge, the connection is not ready yet.', 'livecanvas-forge-ai') . '</li>';
            echo '</ol>';
            echo '<div class="lcfa-codex-smoke-alert">';
            echo '<strong>' . esc_html__('Important', 'livecanvas-forge-ai') . '</strong>';
            echo '<span>' . esc_html__('Reopen Codex if the registration was added while Codex was already open, then return here and run the smoke test below.', 'livecanvas-forge-ai') . '</span>';
            echo '</div>';
            echo '</div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_test_connections');
        echo '<input type="hidden" name="action" value="lcfa_test_connections">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</form>';
    }

    private function render_inline_copy_command(string $command, string $label = ''): string {
        $copy_label = $label !== '' ? $label : __('Copy', 'livecanvas-forge-ai');

        return '<span class="lcfa-inline-copy-code"><code>' . esc_html($command) . '</code><button class="lcfa-inline-copy-code__button" type="button" data-lcfa-copy-text="' . esc_attr($command) . '" data-lcfa-copy-label="' . esc_attr($copy_label) . '" data-lcfa-copied-label="' . esc_attr(__('Copied', 'livecanvas-forge-ai')) . '">' . esc_html($copy_label) . '</button></span>';
    }

    private function render_code_block_explanation(string $description): void {
        if (trim($description) === '') {
            return;
        }

        echo '<div class="lcfa-code-explanation" data-lcfa-read-more>';
        echo '<p data-lcfa-read-more-body>' . esc_html($description) . '</p>';
        echo '<button class="lcfa-code-explanation__toggle" type="button" data-lcfa-read-more-toggle data-lcfa-expanded-label="' . esc_attr(__('Show less', 'livecanvas-forge-ai')) . '" hidden>' . esc_html__('Read more', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
    }

    private function render_connection_visual_help_strip(array $wizard_view): void {
        $visual_help = is_array($wizard_view['visual_help'] ?? null) ? $wizard_view['visual_help'] : [];
        $items = is_array($visual_help['items'] ?? null) ? $visual_help['items'] : [];
        $client = $this->sanitize_key_compat((string) ($visual_help['client'] ?? ''));

        if ($items === []) {
            return;
        }

        echo '<section class="lcfa-visual-help">';
        echo '<div class="lcfa-guide">';
        echo '<h3 class="lcfa-visual-help__title">';
        if ($client !== '') {
            echo $this->get_agent_icon_markup($client, $this->get_client_fallback_icon($client), 'lcfa-agent-icon lcfa-agent-icon--section');
        }
        echo '<span>' . esc_html((string) ($visual_help['title'] ?? __('What this looks like', 'livecanvas-forge-ai'))) . '</span>';
        echo '</h3>';
        echo '</div>';
        echo '<div class="lcfa-visual-help__grid">';

        foreach ($items as $item) {
            $tone = sanitize_html_class((string) ($item['tone'] ?? 'default'));
            echo '<article class="lcfa-visual-help__card tone-' . esc_attr($tone) . '">';
            echo '<div class="lcfa-visual-help__frame" aria-hidden="true"></div>';
            echo '<strong>' . esc_html((string) ($item['title'] ?? '')) . '</strong>';
            echo '<p>' . esc_html((string) ($item['caption'] ?? '')) . '</p>';
            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
    }

    private function render_connection_technical_summary(array $bundle, bool $expanded): void {
        echo '<section class="lcfa-wizard__summary' . ($expanded ? '' : ' is-collapsed') . '">';
        if ($expanded) {
            $this->render_connection_bundle_details($bundle);
        } else {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Technical summary', 'livecanvas-forge-ai') . '</h3>';
            echo '<p class="lcfa-guide-copy">' . esc_html__('Bundle files, environment variables, and smoke test commands will appear here once you reach the generation step.', 'livecanvas-forge-ai') . '</p>';
            echo '</div>';
        }
        echo '</section>';
    }

    private function get_workspace_write_notice(array $workspace_state): string {
        $path = (string) ($workspace_state['path'] ?? '');
        $reason = (string) ($workspace_state['reason'] ?? 'unreachable');

        switch ($reason) {
            case 'missing':
                return __('Set a local workspace root first.', 'livecanvas-forge-ai');
            case 'runtime_only':
                return __('The current value points to a runtime mount such as /wordpress. Use the host-side project path instead.', 'livecanvas-forge-ai');
            case 'not_absolute':
                return __('Use an absolute host path for the local workspace root.', 'livecanvas-forge-ai');
            case 'not_writable':
                return sprintf(__('The browser runtime can see %s but cannot write there.', 'livecanvas-forge-ai'), $path);
            case 'unreachable':
            default:
                return sprintf(__('The browser runtime cannot reach %s. Use Download client bundle or let your coding agent create the file from the host machine.', 'livecanvas-forge-ai'), $path);
        }
    }

    private function render_connection_bundle_details(array $bundle): void {
        $is_claude_desktop = (string) ($bundle['client'] ?? '') === 'claude'
            && (string) ($bundle['claude_connection_target'] ?? '') === 'desktop_app';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Generated bundle', 'livecanvas-forge-ai') . '</h3>';
        if ($is_claude_desktop) {
            echo '<p class="lcfa-guide-copy">' . esc_html__('For Claude Desktop, this JSON is a snippet. Merge livecanvas-forge into ~/Library/Application Support/Claude/claude_desktop_config.json under mcpServers. Do not paste it as a second top-level JSON object and do not replace your existing preferences block.', 'livecanvas-forge-ai') . '</p>';
        }
        echo '<div class="lcfa-agent-guide__bundle-layout">';

        echo '<div class="lcfa-agent-guide__window lcfa-agent-guide__window--files">';
        echo '<h3>' . esc_html__('Files', 'livecanvas-forge-ai') . '</h3>';
        if (!empty($bundle['workspace_files'])) {
            echo '<ul class="lcfa-bullets">';
            foreach ((array) $bundle['workspace_files'] as $file) {
                echo '<li><code>' . esc_html((string) ($file['path'] ?? '')) . '</code></li>';
            }
            echo '</ul>';
        } elseif (!empty($bundle['download_files'])) {
            echo '<ul class="lcfa-bullets">';
            foreach ((array) $bundle['download_files'] as $file) {
                echo '<li><code>' . esc_html((string) ($file['name'] ?? '')) . '</code></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('This client currently uses copy or command-based setup.', 'livecanvas-forge-ai') . '</p>';
        }
        echo '</div>';

        echo '<div class="lcfa-agent-guide__panel-grid lcfa-agent-guide__panel-grid--bundle">';

        if (!empty($bundle['shortcut_command'])) {
            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html((string) ($bundle['shortcut_title'] ?? __('Shortcut', 'livecanvas-forge-ai'))) . '</h3>';
            if ($is_claude_desktop) {
                $this->render_claude_desktop_merge_guide();
                $this->render_code_block_explanation(__('Shows the JSON snippet that must be merged into Claude Desktop config. It does not replace the whole file; it only adds the livecanvas-forge MCP server under mcpServers.', 'livecanvas-forge-ai'));
            } elseif ((string) ($bundle['client'] ?? '') === 'codex') {
                $this->render_code_block_explanation(__('Registers livecanvas-forge inside Codex, so Codex can call the AI Bridge MCP server for this WordPress site. Run it once from the project root after generating the bundle; if Codex was already open, reopen it before the smoke test.', 'livecanvas-forge-ai'));
            } else {
                $this->render_code_block_explanation(__('Creates or runs the setup shortcut for the selected coding agent. Use it to register livecanvas-forge with the agent without manually rebuilding the raw MCP command.', 'livecanvas-forge-ai'));
            }
            $this->render_code_block((string) $bundle['shortcut_command'], [
                'language'   => $is_claude_desktop ? 'json' : 'bash',
                'label'      => $is_claude_desktop ? __('JSON', 'livecanvas-forge-ai') : __('Shell', 'livecanvas-forge-ai'),
                'copy_label' => $is_claude_desktop ? __('Copy config', 'livecanvas-forge-ai') : __('Copy shortcut', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Server command', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block_explanation(__('Shows the raw MCP server command the coding agent runs behind the scenes. Use it for manual MCP setup or diagnostics when the shortcut or generated config is not enough.', 'livecanvas-forge-ai'));
        $this->render_code_block((string) ($bundle['command_string'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy command', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Environment variables', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block_explanation(__('Defines the REST endpoint, token, site URL, and local project root passed to the MCP server. These values must match this WordPress install, otherwise the agent can connect to the wrong site or fail authorization.', 'livecanvas-forge-ai'));
        $this->render_code_block($this->build_environment_block((array) ($bundle['environment'] ?? [])), [
            'language'   => 'bash',
            'label'      => __('Environment', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy env', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Smoke test', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block_explanation(__('Runs get_snapshot and confirms the MCP bridge can reach WordPress before page edits. If this command fails, AI Bridge can show setup data but the coding agent is not ready to execute changes.', 'livecanvas-forge-ai'));
        $this->render_code_block((string) ($bundle['smoke_test_command'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy smoke test', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        if (!empty($bundle['agent_start_prompt'])) {
            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html__('First agent prompt', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block_explanation(__('Send this to the connected coding agent after the MCP server is visible. It asks the agent to fetch the lightweight connection handoff, inspect current guardrails, and stay read-only before reviewed previews.', 'livecanvas-forge-ai'));
            $this->render_code_block((string) $bundle['agent_start_prompt'], [
                'language'   => 'text',
                'label'      => __('Prompt', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy prompt', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function render_remote_companion_card(array $remote_status): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('cloud');
        echo '<div><h2>' . esc_html__('Advanced remote target', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Use this only when the agent should target another WordPress site. The main Codex Direct Mode above also works for this local site through URL and Application Password.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . (!empty($remote_status['available']) ? ' is-positive' : (!empty($remote_status['configured']) ? ' is-negative' : '')) . '">' . esc_html(!empty($remote_status['available']) ? __('Remote ready', 'livecanvas-forge-ai') : (!empty($remote_status['configured']) ? __('Configured but failing', 'livecanvas-forge-ai') : __('Not configured', 'livecanvas-forge-ai'))) . '</span>';
        if (!empty($remote_status['snapshot']['framework'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Framework: %s', 'livecanvas-forge-ai'), (string) $remote_status['snapshot']['framework'])) . '</span>';
        }
        if (!empty($remote_status['mcp']['token'])) {
            echo '<span class="lcfa-chip is-positive">' . esc_html__('Remote MCP token available', 'livecanvas-forge-ai') . '</span>';
        }
        if (!empty($remote_status['mcp_adapter']['available'])) {
            echo '<span class="lcfa-chip is-positive">' . esc_html__('WordPress MCP Adapter ready', 'livecanvas-forge-ai') . '</span>';
        } elseif (!empty($remote_status['available'])) {
            echo '<span class="lcfa-chip">' . esc_html__('MCP Adapter not detected', 'livecanvas-forge-ai') . '</span>';
        }
        echo '</div>';
        echo '<p class="lcfa-guide-copy">' . esc_html((string) ($remote_status['message'] ?? '')) . '</p>';
        if (!empty($remote_status['endpoint'])) {
            echo '<p><code>' . esc_html((string) $remote_status['endpoint']) . '</code></p>';
        }
        if (!empty($remote_status['mcp_adapter']['custom_server']['url'])) {
            echo '<p><code>' . esc_html((string) $remote_status['mcp_adapter']['custom_server']['url']) . '</code></p>';
        }
        echo '</section>';
    }

    private function get_ability_diagnostics_for_admin(): array {
        if ($this->ability_registry instanceof LCFA_Ability_Registry) {
            return $this->ability_registry->get_ability_diagnostics();
        }

        $mcp_adapter = method_exists($this->environment, 'get_mcp_adapter_status')
            ? $this->environment->get_mcp_adapter_status()
            : [];

        return [
            'ability_diagnostics' => [
                'total'                => 0,
                'mcp_public_total'     => 0,
                'mcp_public'           => [],
                'mcp_public_preview'   => [],
                'mcp_public_write'     => [],
                'has_mcp_public_write' => false,
                'mcp_write_opt_in_enabled' => false,
                'mcp_write_allowlist'  => [],
                'mcp_write_available'  => array_keys(LCFA_Settings::get_mcp_write_ability_options()),
                'items'                => [],
            ],
            'mcp_adapter' => $mcp_adapter,
            'ai_client' => [
                'available' => false,
                'text_generation_supported' => false,
                'connectors' => [
                    'available' => false,
                    'count' => 0,
                    'text_generation_count' => 0,
                ],
            ],
        ];
    }

    private function render_ability_diagnostics_card(array $diagnostics): void {
        $ability = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $adapter = is_array($diagnostics['mcp_adapter'] ?? null) ? $diagnostics['mcp_adapter'] : [];
        $ai_client = is_array($diagnostics['ai_client'] ?? null) ? $diagnostics['ai_client'] : [];
        $connectors = is_array($ai_client['connectors'] ?? null) ? $ai_client['connectors'] : [];
        $public_preview = is_array($ability['mcp_public_preview'] ?? null) ? $ability['mcp_public_preview'] : [];
        $public_write = is_array($ability['mcp_public_write'] ?? null) ? $ability['mcp_public_write'] : [];
        $write_allowlist = is_array($ability['mcp_write_allowlist'] ?? null) ? $ability['mcp_write_allowlist'] : [];

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('sparkles');
        echo '<div><h2>' . esc_html__('Ability diagnostics', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Shows the WordPress-native ability surface exposed to MCP-capable agents. Preview abilities are safe dry-run entry points; write abilities stay private by default.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Abilities: %d', 'livecanvas-forge-ai'), (int) ($ability['total'] ?? 0))) . '</span>';
        echo '<span class="lcfa-chip is-positive">' . esc_html(sprintf(__('MCP public: %d', 'livecanvas-forge-ai'), (int) ($ability['mcp_public_total'] ?? 0))) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Preview public: %d', 'livecanvas-forge-ai'), count($public_preview))) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($ability['has_mcp_public_write']) ? ' is-negative' : ' is-positive') . '">' . esc_html(!empty($ability['has_mcp_public_write']) ? __('Write exposed', 'livecanvas-forge-ai') : __('No public write abilities', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($ability['mcp_write_opt_in_enabled']) ? ' is-warning' : '') . '">' . esc_html(!empty($ability['mcp_write_opt_in_enabled']) ? __('Write opt-in enabled', 'livecanvas-forge-ai') : __('Write opt-in disabled', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($adapter['available']) ? ' is-positive' : '') . '">' . esc_html(!empty($adapter['available']) ? __('MCP Adapter ready', 'livecanvas-forge-ai') : __('MCP Adapter not detected', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($ai_client['text_generation_supported']) ? ' is-positive' : '') . '">' . esc_html(!empty($ai_client['text_generation_supported']) ? __('AI text ready', 'livecanvas-forge-ai') : __('AI text unavailable', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Connectors: %d', 'livecanvas-forge-ai'), (int) ($connectors['count'] ?? 0))) . '</span>';
        echo '</div>';

        if (!empty($adapter['custom_server']['url'])) {
            echo '<p><code>' . esc_html((string) $adapter['custom_server']['url']) . '</code></p>';
        }

        if ($public_preview) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Public preview abilities', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-check-list">';
            foreach ($public_preview as $ability_name) {
                echo '<li><code>' . esc_html((string) $ability_name) . '</code></li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ($public_write) {
            echo '<div class="lcfa-notice lcfa-notice--warning">';
            echo '<strong>' . esc_html__('Review MCP write exposure', 'livecanvas-forge-ai') . '</strong>';
            echo '<p>' . esc_html__('At least one destructive ability is public to MCP. Keep this disabled unless the site owner explicitly opted into write-capable remote agents.', 'livecanvas-forge-ai') . '</p>';
            echo '<ul class="lcfa-check-list">';
            foreach ($public_write as $ability_name) {
                echo '<li><code>' . esc_html((string) $ability_name) . '</code></li>';
            }
            echo '</ul>';
            echo '</div>';
        } elseif (!empty($ability['mcp_write_opt_in_enabled']) && $write_allowlist === []) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Write opt-in is enabled with no allowed write abilities', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html__('The MCP write master switch is on, but the per-ability allowlist is empty, so no destructive ability is public.', 'livecanvas-forge-ai') . '</p>';
            echo '</div>';
        }

        echo '</section>';
    }

    private function render_advanced_connection_settings(array $connections, string $preferred_client, array $mcp_status, array $preferred_bootstrap, string $common_bootstrap, string $command_example, string $workspace_root): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Advanced settings', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('These are the low-level controls behind the wizard. You only need them when you want to override transport details, remote companion credentials, or package sources manually.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<details class="lcfa-advanced-settings">';
        echo '<summary>' . esc_html__('Show advanced settings', 'livecanvas-forge-ai') . '</summary>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($connections['connection_mode'] ?: ($preferred_client === 'codex' ? 'remote' : 'local'))) . '">';
        echo '<input type="hidden" name="workspace_root" value="' . esc_attr($workspace_root) . '">';

        echo '<label><span>' . esc_html__('Preferred transport', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="transport">';
        echo '<option value="rest"' . selected($connections['transport'], 'rest', false) . '>' . esc_html__('REST first', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="mcp"' . selected($connections['transport'], 'mcp', false) . '>' . esc_html__('MCP first', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="hybrid"' . selected($connections['transport'], 'hybrid', false) . '>' . esc_html__('Hybrid', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__('Picowind package URL', 'livecanvas-forge-ai') . '</span><input type="text" name="picowind_package_url" value="' . esc_attr($connections['picowind_package_url']) . '" placeholder="https://...zip"></label>';
        echo '<label><span>' . esc_html__('Picostrap package URL', 'livecanvas-forge-ai') . '</span><input type="text" name="picostrap_package_url" value="' . esc_attr($connections['picostrap_package_url']) . '" placeholder="https://...zip"></label>';
        echo '<label><span>' . esc_html__('Local bridge URL', 'livecanvas-forge-ai') . '</span><input type="text" name="local_bridge_url" value="' . esc_attr($connections['local_bridge_url']) . '"></label>';
        echo '<label class="lcfa-checkbox"><input type="checkbox" name="mcp_enabled" value="1"' . checked((bool) $connections['mcp_enabled'], true, false) . '> ' . esc_html__('Enable the built-in MCP bridge profile for local tooling.', 'livecanvas-forge-ai') . '</label>';
        echo '<label class="lcfa-checkbox"><input type="checkbox" name="mcp_write_abilities_enabled" value="1"' . checked(!empty($connections['mcp_write_abilities_enabled']), true, false) . '> ' . esc_html__('Expose curated write abilities through the AI Bridge MCP server after admin opt-in.', 'livecanvas-forge-ai') . '</label>';
        echo '<p class="description">' . esc_html__('Keep this disabled unless the connected MCP client is trusted. Preview abilities remain available without this opt-in.', 'livecanvas-forge-ai') . '</p>';
        echo '<input type="hidden" name="mcp_public_write_abilities_submitted" value="1">';
        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('MCP write ability allowlist', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html__('Select the destructive abilities that may become MCP-public when the master write opt-in above is enabled. Leave all unchecked to expose no write abilities.', 'livecanvas-forge-ai') . '</p>';
        $selected_write_abilities = LCFA_Settings::sanitize_mcp_write_abilities($connections['mcp_public_write_abilities'] ?? []);
        foreach (LCFA_Settings::get_mcp_write_ability_options() as $ability_name => $ability_data) {
            echo '<label class="lcfa-checkbox"><input type="checkbox" name="mcp_public_write_abilities[]" value="' . esc_attr((string) $ability_name) . '"' . checked(in_array((string) $ability_name, $selected_write_abilities, true), true, false) . '> ';
            echo '<span><strong>' . esc_html((string) ($ability_data['label'] ?? $ability_name)) . '</strong> <code>' . esc_html((string) $ability_name) . '</code><br><small>' . esc_html((string) ($ability_data['description'] ?? '')) . '</small></span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<label><span>' . esc_html__('MCP host', 'livecanvas-forge-ai') . '</span><input type="text" name="mcp_host" value="' . esc_attr($connections['mcp_host']) . '" placeholder="127.0.0.1"></label>';
        echo '<label><span>' . esc_html__('MCP port', 'livecanvas-forge-ai') . '</span><input type="text" name="mcp_port" value="' . esc_attr($connections['mcp_port']) . '" placeholder="7681"></label>';
        echo '<label><span>' . esc_html__('Remote site URL', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_site_url" value="' . esc_attr($connections['remote_site_url']) . '" placeholder="https://example.com"></label>';
        echo '<label><span>' . esc_html__('Remote username', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_username" value="' . esc_attr($connections['remote_username']) . '"></label>';
        echo '<label><span>' . esc_html__('Remote application password', 'livecanvas-forge-ai') . '</span><input type="password" name="remote_application_password" value="" placeholder="' . esc_attr($connections['remote_application_password'] !== '' ? __('Stored. Leave blank to keep current value.', 'livecanvas-forge-ai') : __('xxxx xxxx xxxx xxxx xxxx xxxx', 'livecanvas-forge-ai')) . '"></label>';
        echo '<label><span>' . esc_html__('MCP server command', 'livecanvas-forge-ai') . '</span><textarea name="mcp_server_command" rows="4" placeholder="npx @livecanvas/forge-mcp">' . esc_textarea($connections['mcp_server_command']) . '</textarea></label>';

        $power_mode = LCFA_Settings::sanitize_power_mode((string) ($connections['power_mode'] ?? 'auto'));
        echo '<label><span>' . esc_html__('Power Mode policy', 'livecanvas-forge-ai') . '</span><select name="power_mode">';
        foreach (LCFA_Settings::get_power_mode_options() as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $power_mode, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select></label>';
        echo '<p class="description">' . esc_html__('Power Mode prepares advanced filesystem, WP-CLI, upload, admin-link, and sandbox tools. In auto mode it is available only for trusted local/development sites and remains off for remote/staging/production.', 'livecanvas-forge-ai') . '</p>';

        if ($preferred_client === 'codex') {
            $codex_defaults = LCFA_Settings::sanitize_codex_options([
                'model'            => $connections['codex_model'] ?? '',
                'speed'            => $connections['codex_speed'] ?? '',
                'reasoning_effort' => $connections['codex_reasoning_effort'] ?? '',
                'sandbox'          => $connections['codex_sandbox'] ?? '',
            ]);
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Codex defaults', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html__('These defaults are used by frontend LiveCanvas prompts before each run. The drawer can still override them for a single request.', 'livecanvas-forge-ai') . '</p>';
            echo '<label><span>' . esc_html__('Default model', 'livecanvas-forge-ai') . '</span><select name="codex_model">';
            foreach (LCFA_Settings::get_codex_model_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_defaults['model'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            echo '<label><span>' . esc_html__('Default speed', 'livecanvas-forge-ai') . '</span><select name="codex_speed">';
            foreach (LCFA_Settings::get_codex_speed_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_defaults['speed'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            echo '<label><span>' . esc_html__('Default intelligence', 'livecanvas-forge-ai') . '</span><select name="codex_reasoning_effort">';
            foreach (LCFA_Settings::get_codex_reasoning_effort_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_defaults['reasoning_effort'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            echo '<label><span>' . esc_html__('Default sandbox', 'livecanvas-forge-ai') . '</span><select name="codex_sandbox">';
            foreach (LCFA_Settings::get_codex_sandbox_options() as $value => $label) {
                echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, $codex_defaults['sandbox'], false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            echo '</div>';
        }

        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html__('Save advanced settings', 'livecanvas-forge-ai') . '</button>';
        echo '<button class="button" type="submit" name="rotate_mcp_token" value="1">' . esc_html__('Rotate MCP token', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Connection checks', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html__('Run live checks against the local bridge, the local MCP runtime, and the configured remote site credentials.', 'livecanvas-forge-ai') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_test_connections');
        echo '<input type="hidden" name="action" value="lcfa_test_connections">';
        echo '<button class="button" type="submit">' . esc_html__('Run connection checks', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('MCP bootstrap', 'livecanvas-forge-ai') . '</h3>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('REST base: %s', 'livecanvas-forge-ai'), rest_url('lcfa/v1/'))) . '</li>';
        echo '<li>' . esc_html(sprintf(__('MCP endpoint: %s', 'livecanvas-forge-ai'), (string) ($mcp_status['endpoint'] ?? ''))) . '</li>';
        echo '<li>' . esc_html__('Execution endpoints: /command/actions, /command/suggest, /command, /mcp/health, /mcp/status, /mcp/local-status, /mcp/bootstrap.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';
        $this->render_code_block($common_bootstrap, [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy bootstrap', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Preferred client bootstrap', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($preferred_bootstrap['command'] ?? '') . "\n\n" . implode("\n", (array) ($preferred_bootstrap['env'] ?? [])), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy client bootstrap', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Example command request', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block("POST " . rest_url('lcfa/v1/command') . "\nContent-Type: application/json\n\n" . $command_example, [
            'language'   => 'bash',
            'label'      => __('HTTP', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy request', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '</details>';
        echo '</section>';
    }

    private function render_agent_connection_guide(array $mcp_bootstrap, array $settings, array $snapshot, string $preferred_client, array $mcp_status, bool $is_wizard_context = false): void {
        $guides        = $this->get_agent_connection_guides($mcp_bootstrap, $settings, $snapshot);
        $selected_key  = $preferred_client === 'other'
            ? 'generic'
            : ($preferred_client === 'claude-code'
                ? 'claude'
                : (isset($guides[$preferred_client]) ? $preferred_client : 'codex'));
        $selected_claude_mode = $this->normalize_claude_connection_target((string) ($settings['claude_connection_target'] ?? ''));
        if ($selected_claude_mode === '') {
            $selected_claude_mode = 'desktop_app';
        }
        $local_bridge  = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];
        $local_bridge_deferred = !empty($local_bridge['deferred']);
        $local_bridge_ready = !$local_bridge_deferred && !empty($local_bridge['available']);
        $site_mode     = (string) (($settings['site_mode'] ?? '') ?: ($snapshot['site_mode'] ?? 'local'));
        $filesystem_mode = (string) ($mcp_status['filesystem_mode'] ?? '');

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('stars');
        if ($is_wizard_context) {
            echo '<div><h2>' . esc_html__('Do this next in your coding agent', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Follow these client-specific actions before using the raw bundle details. This section tells you what to click, copy, or run right now.', 'livecanvas-forge-ai') . '</p></div>';
        } else {
            echo '<div><h2>' . esc_html__('Client quickstart details', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('These quickstart guides are written in English on purpose. Pick your client to review the exact command, environment, and smoke test generated by the plugin.', 'livecanvas-forge-ai') . '</p></div>';
        }
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Site mode: %s', 'livecanvas-forge-ai'), $site_mode)) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Filesystem mode: %s', 'livecanvas-forge-ai'), $filesystem_mode ?: 'n/a')) . '</span>';
        echo '<span class="lcfa-chip' . ($local_bridge_ready ? ' is-positive' : ($local_bridge_deferred ? '' : '')) . '">' . esc_html($local_bridge_deferred ? __('Local MCP bridge status loading', 'livecanvas-forge-ai') : ($local_bridge_ready ? __('Local MCP bridge ready', 'livecanvas-forge-ai') : __('Local MCP bridge not ready', 'livecanvas-forge-ai'))) . '</span>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<p>' . esc_html__($is_wizard_context ? 'Use the MCP token for your coding agent. Treat the raw server command and generated files below as reference material after you complete the steps in this guide.' : 'Use the MCP token for your coding agent. WordPress Application Passwords are only needed when one WordPress site connects to another WordPress site through the remote companion.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';

        echo '<div class="lcfa-agent-guide">';
        foreach ($guides as $key => $guide) {
            $input_id = 'lcfa-agent-tab-' . $this->sanitize_key_compat($key);
            echo '<input class="lcfa-agent-guide__input" type="radio" name="lcfa_agent_tab" id="' . esc_attr($input_id) . '"' . checked($selected_key, $key, false) . '>';
        }

        echo '<div class="lcfa-agent-guide__tabs" role="tablist" aria-label="' . esc_attr__('Coding agent quickstart tabs', 'livecanvas-forge-ai') . '">';
        foreach ($guides as $key => $guide) {
            $input_id = 'lcfa-agent-tab-' . $this->sanitize_key_compat($key);
            echo '<label class="lcfa-agent-guide__tab" for="' . esc_attr($input_id) . '" role="tab">' . $this->get_agent_icon_markup($key, $this->get_client_fallback_icon($key), 'lcfa-agent-icon lcfa-agent-icon--tab') . '<span>' . esc_html($guide['label']) . '</span></label>';
        }
        echo '</div>';

        echo '<div class="lcfa-agent-guide__panels">';
        foreach ($guides as $key => $guide) {
            echo '<section class="lcfa-agent-guide__panel lcfa-agent-guide__panel--' . esc_attr(sanitize_html_class($key)) . '">';
            echo '<p class="lcfa-agent-guide__intro">' . esc_html($guide['intro']) . '</p>';
            if (!empty($guide['modes']) && is_array($guide['modes'])) {
                $this->render_agent_connection_mode_switcher($key, $guide, $selected_claude_mode);
            } else {
                $this->render_agent_connection_windows($guide);
            }
            echo '</section>';
        }
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }

    private function render_agent_connection_mode_switcher(string $client_key, array $guide, string $selected_mode): void {
        $modes = is_array($guide['modes'] ?? null) ? $guide['modes'] : [];
        if ($modes === []) {
            $this->render_agent_connection_windows($guide);
            return;
        }

        if (!isset($modes[$selected_mode])) {
            $selected_mode = (string) array_key_first($modes);
        }

        foreach ($modes as $mode_key => $mode_guide) {
            $input_id = 'lcfa-agent-' . $this->sanitize_key_compat($client_key) . '-mode-' . $this->sanitize_key_compat((string) $mode_key);
            echo '<input class="lcfa-agent-guide__input" type="radio" name="lcfa_agent_' . esc_attr($this->sanitize_key_compat($client_key)) . '_mode" id="' . esc_attr($input_id) . '"' . checked($selected_mode, $mode_key, false) . '>';
        }

        echo '<div class="lcfa-agent-guide__subtabs" role="tablist" aria-label="' . esc_attr__('Claude connection target', 'livecanvas-forge-ai') . '">';
        foreach ($modes as $mode_key => $mode_guide) {
            $input_id = 'lcfa-agent-' . $this->sanitize_key_compat($client_key) . '-mode-' . $this->sanitize_key_compat((string) $mode_key);
            echo '<label class="lcfa-agent-guide__subtab" for="' . esc_attr($input_id) . '" role="tab">' . esc_html((string) ($mode_guide['label'] ?? $mode_key)) . '</label>';
        }
        echo '</div>';

        echo '<div class="lcfa-agent-guide__subpanels">';
        foreach ($modes as $mode_key => $mode_guide) {
            echo '<section class="lcfa-agent-guide__subpanel lcfa-agent-guide__subpanel--' . esc_attr(sanitize_html_class($client_key . '-' . $mode_key)) . '">';
            if (!empty($mode_guide['summary'])) {
                echo '<p class="lcfa-agent-guide__mode-copy">' . esc_html((string) $mode_guide['summary']) . '</p>';
            }
            $this->render_agent_connection_windows($mode_guide);
            echo '</section>';
        }
        echo '</div>';
    }

    private function render_agent_connection_windows(array $guide): void {
        $is_claude_desktop = (string) ($guide['mode_key'] ?? '') === 'desktop_app';

        echo '<div class="lcfa-agent-guide__panel-grid">';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Step-by-step', 'livecanvas-forge-ai') . '</h3>';
        echo '<ol class="lcfa-agent-guide__steps">';
        foreach ((array) ($guide['steps'] ?? []) as $step) {
            echo '<li>' . esc_html((string) $step) . '</li>';
        }
        echo '</ol>';
        if (!empty($guide['note'])) {
            echo '<p class="lcfa-agent-guide__note">' . esc_html((string) $guide['note']) . '</p>';
        }
        echo '</div>';

        if (!empty($guide['shortcut_title']) && !empty($guide['shortcut'])) {
            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html((string) $guide['shortcut_title']) . '</h3>';
            if ($is_claude_desktop) {
                $this->render_claude_desktop_merge_guide();
            }
            $this->render_code_block((string) $guide['shortcut'], [
                'language'   => $is_claude_desktop ? 'json' : 'bash',
                'label'      => $is_claude_desktop ? __('JSON', 'livecanvas-forge-ai') : __('Shell', 'livecanvas-forge-ai'),
                'copy_label' => $is_claude_desktop ? __('Copy config', 'livecanvas-forge-ai') : __('Copy shortcut', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Server command', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($guide['command'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy command', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Environment variables', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($guide['environment'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Environment', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy env', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Quick terminal test', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($guide['test_command'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy test command', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '</div>';
    }

    private function get_agent_connection_guides(array $mcp_bootstrap, array $settings, array $snapshot): array {
        $clients = is_array($mcp_bootstrap['clients'] ?? null) ? $mcp_bootstrap['clients'] : [];
        $common  = is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [];
        $is_local_filesystem = (string) ($common['filesystem_mode'] ?? '') === 'local-theme-access';
        $site_mode = (string) (($settings['site_mode'] ?? '') ?: ($snapshot['site_mode'] ?? 'local'));
        $is_remote_site = in_array($site_mode, ['remote', 'hybrid'], true);
        $wp_root_note = $is_local_filesystem
            ? __('This site is detected as local, so keep LCFA_WP_ROOT in the environment if you want local file access and local build tools.', 'livecanvas-forge-ai')
            : __('This site is currently handled as remote. In most cases you can leave LCFA_WP_ROOT out of the client environment.', 'livecanvas-forge-ai');

        $codex = is_array($clients['codex'] ?? null) ? $clients['codex'] : ['command' => '', 'env' => []];
        $cursor = is_array($clients['cursor'] ?? null) ? $clients['cursor'] : $codex;
        $claude = is_array($clients['claude'] ?? null)
            ? $clients['claude']
            : (is_array($clients['claude-code'] ?? null) ? $clients['claude-code'] : $codex);
        $opencode = is_array($clients['opencode'] ?? null) ? $clients['opencode'] : $codex;
        $claude_desktop_shortcut_title = $is_remote_site
            ? __('Claude Desktop reference', 'livecanvas-forge-ai')
            : __('Claude Desktop config', 'livecanvas-forge-ai');
        $claude_desktop_shortcut = $is_remote_site
            ? $this->build_claude_desktop_reference_snippet($claude)
            : $this->build_claude_desktop_config_snippet($claude);

        return [
            'codex' => [
                'label'          => __('Codex', 'livecanvas-forge-ai'),
                'intro'          => __('Use this if Codex is your main coding agent. The easiest path is to register the MCP server once, then let Codex call the plugin tools from the current workspace.', 'livecanvas-forge-ai'),
                'steps'          => [
                    __('Open a terminal in the same workspace as your WordPress project.', 'livecanvas-forge-ai'),
                    __('Prefer a project .codex/config.toml for each WordPress project. The shortcut below can still register a global fallback.', 'livecanvas-forge-ai'),
                    __('Keep the server name as livecanvas-forge so it is easy to recognize later.', 'livecanvas-forge-ai'),
                    __('Restart Codex if needed, verify with codex mcp list or the embedded Codex CLI path, then call get_snapshot as your first tool check.', 'livecanvas-forge-ai'),
                ],
                'note'           => $wp_root_note,
                'shortcut_title' => __('Codex shortcut', 'livecanvas-forge-ai'),
                'shortcut'       => $this->build_codex_register_command($codex),
                'command'        => (string) ($codex['command'] ?? ''),
                'environment'    => $this->build_environment_block((array) ($codex['env'] ?? [])),
                'test_command'   => $this->build_manual_mcp_test_command((array) ($codex['env'] ?? [])),
            ],
            'cursor' => [
                'label'        => __('Cursor', 'livecanvas-forge-ai'),
                'intro'        => __('Use this if you want Cursor to discover the AI Bridge tools through MCP. Add one local stdio MCP server and keep the command and environment exactly as shown.', 'livecanvas-forge-ai'),
                'steps'        => [
                    __('Open the MCP server settings in Cursor.', 'livecanvas-forge-ai'),
                    __('Create a new local or stdio MCP server named livecanvas-forge.', 'livecanvas-forge-ai'),
                    __('Paste the server command and environment variables from this tab.', 'livecanvas-forge-ai'),
                    __('Save the server, reconnect it, and ask for get_snapshot to confirm that the bridge is live.', 'livecanvas-forge-ai'),
                ],
                'note'         => $wp_root_note,
                'command'      => (string) ($cursor['command'] ?? ''),
                'environment'  => $this->build_environment_block((array) ($cursor['env'] ?? [])),
                'test_command' => $this->build_manual_mcp_test_command((array) ($cursor['env'] ?? [])),
            ],
            'claude' => [
                'label' => __('Claude', 'livecanvas-forge-ai'),
                'intro' => __('Use this if Claude is your main agent. Choose whether you are connecting through Claude Desktop App or through the Command Line Interface.', 'livecanvas-forge-ai'),
                'modes' => [
                    'desktop_app' => [
                        'mode_key'       => 'desktop_app',
                        'label'          => __('Desktop App', 'livecanvas-forge-ai'),
                        'summary'        => $is_remote_site
                            ? __('Use this when Claude Desktop needs to reach a remote WordPress target. Review the reference block before editing the desktop config manually.', 'livecanvas-forge-ai')
                            : __('Use this when Claude Desktop is your main app. Merge the JSON block under mcpServers inside your existing Claude Desktop config, reopen the app, then verify the bridge with get_snapshot.', 'livecanvas-forge-ai'),
                        'steps'          => [
                            __('Open ~/Library/Application Support/Claude/claude_desktop_config.json on your machine.', 'livecanvas-forge-ai'),
                            __('Merge the JSON block under mcpServers inside your existing Claude Desktop config.', 'livecanvas-forge-ai'),
                            __('Do not paste it as a second top-level JSON object or replace your preferences block.', 'livecanvas-forge-ai'),
                            __('Quit and reopen Claude Desktop.', 'livecanvas-forge-ai'),
                            __('Ask Claude to run get_snapshot before requesting any writes.', 'livecanvas-forge-ai'),
                        ],
                        'note'           => $wp_root_note,
                        'shortcut_title' => $claude_desktop_shortcut_title,
                        'shortcut'       => $claude_desktop_shortcut,
                        'command'        => (string) ($claude['command'] ?? ''),
                        'environment'    => $this->build_environment_block((array) ($claude['env'] ?? [])),
                        'test_command'   => $this->build_manual_mcp_test_command((array) ($claude['env'] ?? [])),
                    ],
                    'cli' => [
                        'mode_key'       => 'cli',
                        'label'          => __('Command Line Interface', 'livecanvas-forge-ai'),
                        'summary'        => __('Use this when Claude runs from the terminal. Register the MCP server once, verify it, then keep working from the same project root.', 'livecanvas-forge-ai'),
                        'steps'          => [
                            __('Open a terminal in the same workspace as your WordPress project.', 'livecanvas-forge-ai'),
                            __('Run the Claude CLI shortcut below.', 'livecanvas-forge-ai'),
                            __('Verify the MCP registration with claude mcp list.', 'livecanvas-forge-ai'),
                            __('Run get_snapshot before requesting any writes.', 'livecanvas-forge-ai'),
                        ],
                        'note'           => $wp_root_note,
                        'shortcut_title' => __('Claude CLI shortcut', 'livecanvas-forge-ai'),
                        'shortcut'       => $this->build_claude_cli_register_command($claude),
                        'command'        => (string) ($claude['command'] ?? ''),
                        'environment'    => $this->build_environment_block((array) ($claude['env'] ?? [])),
                        'test_command'   => $this->build_manual_mcp_test_command((array) ($claude['env'] ?? [])),
                    ],
                ],
            ],
            'opencode' => [
                'label'        => __('OpenCode', 'livecanvas-forge-ai'),
                'intro'        => __('Use this if OpenCode is your main terminal agent. You only need one local MCP entry that points to the AI Bridge bridge.', 'livecanvas-forge-ai'),
                'steps'        => [
                    __('Open the MCP server configuration in OpenCode.', 'livecanvas-forge-ai'),
                    __('Add a new local MCP server named livecanvas-forge.', 'livecanvas-forge-ai'),
                    __('Paste the command and environment variables from this tab.', 'livecanvas-forge-ai'),
                    __('Reconnect the server and run get_snapshot to make sure the WordPress companion is reachable.', 'livecanvas-forge-ai'),
                ],
                'note'         => $wp_root_note,
                'command'      => (string) ($opencode['command'] ?? ''),
                'environment'  => $this->build_environment_block((array) ($opencode['env'] ?? [])),
                'test_command' => $this->build_manual_mcp_test_command((array) ($opencode['env'] ?? [])),
            ],
            'generic' => [
                'label'        => __('Generic MCP client', 'livecanvas-forge-ai'),
                'intro'        => __('Use this if your coding agent is MCP-compatible but not listed here. The same MCP bridge still works: you only need a stdio server entry and the variables shown below.', 'livecanvas-forge-ai'),
                'steps'        => [
                    __('Open the MCP server settings in your client.', 'livecanvas-forge-ai'),
                    __('Create a new stdio server named livecanvas-forge.', 'livecanvas-forge-ai'),
                    __('Paste the command and environment variables shown in this tab.', 'livecanvas-forge-ai'),
                    __('Reconnect the client and test with get_snapshot before running write commands.', 'livecanvas-forge-ai'),
                ],
                'note'         => $wp_root_note,
                'command'      => (string) ($codex['command'] ?? ''),
                'environment'  => $this->build_environment_block((array) ($codex['env'] ?? [])),
                'test_command' => $this->build_manual_mcp_test_command((array) ($codex['env'] ?? [])),
            ],
        ];
    }

    private function build_environment_block(array $environment): string {
        if (!$environment) {
            return __('No environment variables were generated for this client.', 'livecanvas-forge-ai');
        }

        $lines = [];

        foreach ($environment as $key => $value) {
            if (is_int($key)) {
                $lines[] = (string) $value;
                continue;
            }

            $lines[] = (string) $key . '=' . (string) $value;
        }

        return implode("\n", $lines);
    }

    private function build_codex_register_command(array $client): string {
        $command = trim((string) ($client['command'] ?? ''));
        $environment = (array) ($client['env'] ?? []);

        $lines = [
            'LCFA_CODEX_BIN=""',
            'if command -v codex >/dev/null 2>&1; then',
            '  LCFA_CODEX_BIN="$(command -v codex)"',
            'elif [ -x "/Applications/Codex.app/Contents/Resources/codex" ]; then',
            '  LCFA_CODEX_BIN="/Applications/Codex.app/Contents/Resources/codex"',
            'fi',
            '',
            'if [ -n "$LCFA_CODEX_BIN" ]; then',
            '  "$LCFA_CODEX_BIN" mcp add livecanvas-forge \\',
        ];

        foreach ($environment as $entry) {
            $lines[] = '    --env ' . (string) $entry . ' \\';
        }

        $lines[] = '    -- ' . $command;
        $lines[] = 'else';
        $lines[] = "  cat <<'EOF'";
        $lines[] = 'Codex CLI not found in PATH and the embedded desktop CLI was not found at /Applications/Codex.app/Contents/Resources/codex.';
        $lines[] = 'Add this MCP server to the project .codex/config.toml, then reopen Codex:';
        $lines[] = '';
        $lines[] = $this->build_codex_config_snippet($client);
        $lines[] = 'EOF';
        $lines[] = '  exit 1';
        $lines[] = 'fi';

        return implode("\n", $lines);
    }

    private function build_codex_config_snippet(array $client): string {
        $command = trim((string) ($client['command'] ?? ''));
        $command_tokens = preg_split('/\s+/', $command, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $command_bin = $command_tokens[0] ?? 'node';
        $args = array_slice($command_tokens, 1);
        $lines = [
            '[mcp_servers.livecanvas-forge]',
            'command = ' . $this->quote_toml_string($command_bin),
            'args = [' . implode(', ', array_map([$this, 'quote_toml_string'], $args)) . ']',
            '',
            '[mcp_servers.livecanvas-forge.env]',
        ];

        foreach ((array) ($client['env'] ?? []) as $entry) {
            $parts = explode('=', (string) $entry, 2);
            $key = trim((string) ($parts[0] ?? ''));
            $value = (string) ($parts[1] ?? '');

            if ($key === '') {
                continue;
            }

            $lines[] = $key . ' = ' . $this->quote_toml_string($value);
        }

        return implode("\n", $lines);
    }

    private function build_claude_cli_register_command(array $client): string {
        $command = trim((string) ($client['command'] ?? ''));
        $lines = [
            'claude mcp add --transport stdio livecanvas-forge \\',
        ];

        foreach ((array) ($client['env'] ?? []) as $entry) {
            $parts = explode('=', (string) $entry, 2);
            $key = trim((string) ($parts[0] ?? ''));
            $value = (string) ($parts[1] ?? '');

            if ($key === '') {
                continue;
            }

            $lines[] = '  --env ' . $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        $lines[] = '  -- ' . $command;

        return implode("\n", $lines);
    }

    private function build_claude_desktop_config_snippet(array $client): string {
        $environment = $this->normalize_environment_entries((array) ($client['env'] ?? []));
        $command_tokens = $this->resolve_claude_desktop_command_tokens(
            $this->tokenize_command_string((string) ($client['command'] ?? '')),
            $environment
        );

        return (string) wp_json_encode([
            'mcpServers' => [
                'livecanvas-forge' => [
                    'type'    => 'stdio',
                    'command' => $command_tokens[0] ?? 'node',
                    'args'    => array_slice($command_tokens, 1),
                    'env'     => (object) $environment,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function build_claude_desktop_reference_snippet(array $client): string {
        $environment = $this->normalize_environment_entries((array) ($client['env'] ?? []));
        $command_tokens = $this->resolve_claude_desktop_command_tokens(
            $this->tokenize_command_string((string) ($client['command'] ?? '')),
            $environment
        );
        $lines = [
            '# Claude Desktop reference',
            '# Review these values before editing Claude Desktop on another machine or remote target.',
            '# Command',
            $command_tokens[0] ?? 'node',
            '',
            '# Args',
        ];

        foreach (array_slice($command_tokens, 1) as $token) {
            $lines[] = $token;
        }

        $lines[] = '';
        $lines[] = '# Environment';
        foreach ($environment as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }

    private function tokenize_command_string(string $command): array {
        $command = trim($command);
        if ($command === '') {
            return [];
        }

        preg_match_all('/"(?:\\\\.|[^"])*"|\'(?:\\\\.|[^\'])*\'|[^\s]+/', $command, $matches);

        return array_values(array_filter(array_map(static function (string $token): string {
            $token = trim($token);
            if ($token === '') {
                return '';
            }

            if (($token[0] === '"' && substr($token, -1) === '"') || ($token[0] === "'" && substr($token, -1) === "'")) {
                return stripcslashes(substr($token, 1, -1));
            }

            return $token;
        }, $matches[0] ?? [])));
    }

    private function normalize_environment_entries(array $environment): array {
        $normalized = [];

        foreach ($environment as $key => $value) {
            if (is_int($key)) {
                $parts = explode('=', (string) $value, 2);
                $env_key = trim((string) ($parts[0] ?? ''));
                $env_value = (string) ($parts[1] ?? '');
            } else {
                $env_key = trim((string) $key);
                $env_value = (string) $value;
            }

            if ($env_key === '') {
                continue;
            }

            $normalized[$env_key] = $env_value;
        }

        return $normalized;
    }

    private function resolve_claude_desktop_command_tokens(array $command_tokens, array $environment): array {
        if ($command_tokens === []) {
            return [];
        }

        $wp_root = trim((string) ($environment['LCFA_WP_ROOT'] ?? ''));
        if ($wp_root === '' || count($command_tokens) < 2) {
            return $command_tokens;
        }

        $relative_script = 'wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';
        $script_path = str_replace('\\', '/', (string) ($command_tokens[1] ?? ''));
        $normalized_script_path = ltrim($script_path, './');

        if ($normalized_script_path !== $relative_script) {
            return $command_tokens;
        }

        $command_tokens[1] = rtrim($wp_root, "/\\") . '/' . $relative_script;

        return $command_tokens;
    }

    private function render_claude_desktop_merge_guide(): void {
        echo '<div class="lcfa-agent-guide__callout">';
        echo '<strong>' . esc_html__('What to do', 'livecanvas-forge-ai') . '</strong>';
        echo '<ol class="lcfa-agent-guide__callout-list">';
        echo '<li>' . esc_html__('Open Claude Desktop config', 'livecanvas-forge-ai') . ': <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></li>';
        echo '<li>' . esc_html__('If mcpServers already exists, paste only the livecanvas-forge entry inside it.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('If mcpServers does not exist, paste the full mcpServers block from below.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Do not replace your existing preferences or paste a second top-level JSON object.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Save the file and reopen Claude Desktop.', 'livecanvas-forge-ai') . '</li>';
        echo '</ol>';
        echo '</div>';
    }

    private function build_manual_mcp_test_command(array $environment): string {
        $lines = [];

        foreach ($environment as $entry) {
            $parts = explode('=', (string) $entry, 2);
            $key   = $parts[0] ?? '';
            $value = $parts[1] ?? '';

            if ($key === '') {
                continue;
            }

            $lines[] = $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        $lines[] = 'node ' . $this->quote_shell_value(LCFA_DIR . 'mcp/bin/livecanvas-forge-mcp.js') . ' \\';
        $lines[] = '  --tool get_snapshot \\';
        $lines[] = '  --output pretty';

        return implode("\n", $lines);
    }

    private function quote_shell_value(string $value): string {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function quote_toml_string(string $value): string {
        return (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function render_studio_tab(array $settings, array $snapshot): void {
        $diagnostics = $this->get_ability_diagnostics_for_admin();
        $history     = LCFA_Settings::get_history();
        $connections = LCFA_Settings::get_connections();

        echo '<div class="lcfa-studio-app-shell" data-lcfa-studio-app-root data-lcfa-studio-endpoint="' . esc_url(rest_url('lcfa/v1/studio')) . '"></div>';
        echo '<div class="lcfa-grid" data-lcfa-studio-root data-lcfa-studio-fallback>';
        echo '<div class="lcfa-main">';
        $this->render_studio_overview_panel($settings, $snapshot, $diagnostics, $history);
        $this->render_studio_abilities_panel($diagnostics);
        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_studio_write_policy_panel($diagnostics, $connections);
        $this->render_studio_runs_panel($history);
        echo '</aside>';
        echo '</div>';
    }

    private function render_studio_overview_panel(array $settings, array $snapshot, array $diagnostics, array $history): void {
        $ability       = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $adapter       = is_array($diagnostics['mcp_adapter'] ?? null) ? $diagnostics['mcp_adapter'] : [];
        $ai_client     = is_array($diagnostics['ai_client'] ?? null) ? $diagnostics['ai_client'] : [];
        $rollback_runs = 0;

        foreach ($history as $entry) {
            if (is_array($entry) && !empty($entry['rollback_available'])) {
                $rollback_runs++;
            }
        }

        $framework = '';
        if (isset($settings['framework']) && is_scalar($settings['framework'])) {
            $framework = (string) $settings['framework'];
        } elseif (isset($snapshot['framework']) && is_scalar($snapshot['framework'])) {
            $framework = (string) $snapshot['framework'];
        }

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('sparkles');
        echo '<div><h2>' . esc_html__('AI Studio overview', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('A consolidated control surface for the WordPress-native AI Bridge contract: abilities, MCP exposure, AI readiness, and audited runs.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-summary-grid">';
        $this->render_summary_tile(__('Abilities', 'livecanvas-forge-ai'), (string) (int) ($ability['total'] ?? 0));
        $this->render_summary_tile(__('MCP public', 'livecanvas-forge-ai'), (string) (int) ($ability['mcp_public_total'] ?? 0));
        $this->render_summary_tile(__('Public writes', 'livecanvas-forge-ai'), (string) count((array) ($ability['mcp_public_write'] ?? [])));
        $this->render_summary_tile(__('Runs', 'livecanvas-forge-ai'), (string) count($history));
        $this->render_summary_tile(__('Rollbacks', 'livecanvas-forge-ai'), (string) $rollback_runs);
        $this->render_summary_tile(__('Framework', 'livecanvas-forge-ai'), $framework !== '' ? $framework : __('Auto', 'livecanvas-forge-ai'));
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . (!empty($adapter['available']) ? ' is-positive' : '') . '">' . esc_html(!empty($adapter['available']) ? __('MCP Adapter ready', 'livecanvas-forge-ai') : __('MCP Adapter pending', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($ai_client['text_generation_supported']) ? ' is-positive' : '') . '">' . esc_html(!empty($ai_client['text_generation_supported']) ? __('AI text ready', 'livecanvas-forge-ai') : __('AI text unavailable', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip' . (!empty($settings['completed']) ? ' is-positive' : ' is-negative') . '">' . esc_html(!empty($settings['completed']) ? __('Setup complete', 'livecanvas-forge-ai') : __('Setup incomplete', 'livecanvas-forge-ai')) . '</span>';
        echo '</div>';

        echo '<div class="lcfa-cta-row">';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections')) . '">' . esc_html__('Connection settings', 'livecanvas-forge-ai') . '</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=command')) . '">' . esc_html__('Open Command Deck', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';

        echo '</section>';
    }

    private function render_studio_abilities_panel(array $diagnostics): void {
        $ability = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $items   = is_array($ability['items'] ?? null) ? $ability['items'] : [];

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('layers');
        echo '<div><h2>' . esc_html__('Abilities', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Registered AI Bridge abilities and their current MCP exposure state. Write abilities are private unless explicitly allowed in Connections.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        if (!$items) {
            echo '<p class="lcfa-empty">' . esc_html__('No AI Bridge abilities are registered in this runtime.', 'livecanvas-forge-ai') . '</p>';
            echo '</section>';

            return;
        }

        echo '<div class="lcfa-studio-toolbar" data-lcfa-studio-ability-controls>';
        echo '<label class="lcfa-studio-search"><span>' . esc_html__('Search abilities', 'livecanvas-forge-ai') . '</span><input type="search" data-lcfa-studio-ability-search placeholder="' . esc_attr__('Search name or label', 'livecanvas-forge-ai') . '"></label>';
        echo '<div class="lcfa-studio-segmented" role="group" aria-label="' . esc_attr__('Ability filters', 'livecanvas-forge-ai') . '">';
        echo '<button type="button" class="button is-current" data-lcfa-studio-ability-filter="all">' . esc_html__('All', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-ability-filter="public">' . esc_html__('MCP public', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-ability-filter="private">' . esc_html__('Private', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-ability-filter="write">' . esc_html__('Write', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-ability-filter="destructive">' . esc_html__('Destructive', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="lcfa-target-list">';
        foreach (array_slice($items, 0, 40) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name        = (string) ($item['name'] ?? '');
            $label       = (string) ($item['label'] ?? '');
            $label       = $label !== '' ? $label : $name;
            $is_public   = !empty($item['mcp_public']);
            $is_readonly = !empty($item['readonly']);
            $destructive = !empty($item['destructive']);
            $idempotent  = !empty($item['idempotent']);
            $search_text  = strtolower($label . ' ' . $name);

            echo '<div class="lcfa-target-item" data-lcfa-studio-ability-item data-lcfa-search="' . esc_attr($search_text) . '" data-lcfa-mcp="' . esc_attr($is_public ? 'public' : 'private') . '" data-lcfa-kind="' . esc_attr($is_readonly ? 'read' : 'write') . '" data-lcfa-destructive="' . esc_attr($destructive ? '1' : '0') . '">';
            echo '<div class="lcfa-target-copy">';
            echo '<strong>' . esc_html($label) . '</strong>';
            echo '<span><code>' . esc_html($name) . '</code></span>';
            echo '</div>';
            echo '<div class="lcfa-chip-row">';
            echo '<span class="lcfa-chip' . ($is_public ? ' is-positive' : '') . '">' . esc_html($is_public ? __('MCP public', 'livecanvas-forge-ai') : __('MCP private', 'livecanvas-forge-ai')) . '</span>';
            echo '<span class="lcfa-chip' . ($is_readonly ? ' is-positive' : ' is-warning') . '">' . esc_html($is_readonly ? __('Read-only', 'livecanvas-forge-ai') : __('Write', 'livecanvas-forge-ai')) . '</span>';
            echo '<span class="lcfa-chip' . ($destructive ? ' is-negative' : '') . '">' . esc_html($destructive ? __('Destructive', 'livecanvas-forge-ai') : __('Non-destructive', 'livecanvas-forge-ai')) . '</span>';
            echo '<span class="lcfa-chip">' . esc_html($idempotent ? __('Idempotent', 'livecanvas-forge-ai') : __('Non-idempotent', 'livecanvas-forge-ai')) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        if (count($items) > 40) {
            echo '<p class="lcfa-empty">' . esc_html(sprintf(__('Showing 40 of %d abilities.', 'livecanvas-forge-ai'), count($items))) . '</p>';
        }

        echo '<p class="lcfa-empty" data-lcfa-studio-ability-empty hidden>' . esc_html__('No abilities match the current filters.', 'livecanvas-forge-ai') . '</p>';
        echo '</section>';
    }

    private function render_studio_write_policy_panel(array $diagnostics, array $connections): void {
        $ability        = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $public_write   = is_array($ability['mcp_public_write'] ?? null) ? $ability['mcp_public_write'] : [];
        $allowlist      = is_array($ability['mcp_write_allowlist'] ?? null) ? $ability['mcp_write_allowlist'] : [];
        $available      = is_array($ability['mcp_write_available'] ?? null) ? $ability['mcp_write_available'] : [];
        $options        = method_exists('LCFA_Settings', 'get_mcp_write_ability_options') ? LCFA_Settings::get_mcp_write_ability_options() : [];
        $master_enabled = !empty($connections['mcp_write_abilities_enabled']) || !empty($ability['mcp_write_opt_in_enabled']);

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('shield-check');
        echo '<div><h2>' . esc_html__('MCP write policy', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Destructive abilities require the master switch and a per-ability allowlist before they become MCP-public.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . ($master_enabled ? ' is-warning' : ' is-positive') . '">' . esc_html($master_enabled ? __('Master opt-in enabled', 'livecanvas-forge-ai') : __('Master opt-in disabled', 'livecanvas-forge-ai')) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Allowed: %d', 'livecanvas-forge-ai'), count($allowlist))) . '</span>';
        echo '<span class="lcfa-chip' . ($public_write ? ' is-negative' : ' is-positive') . '">' . esc_html(sprintf(__('Exposed: %d', 'livecanvas-forge-ai'), count($public_write))) . '</span>';
        echo '</div>';

        if ($allowlist) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Allowed write abilities', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-check-list">';
            foreach ($allowlist as $ability_name) {
                $label = is_array($options[$ability_name] ?? null) ? (string) ($options[$ability_name]['label'] ?? $ability_name) : (string) $ability_name;
                echo '<li><strong>' . esc_html($label) . '</strong><br><code>' . esc_html((string) $ability_name) . '</code></li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<p class="lcfa-empty">' . esc_html__('No write ability is currently allowed for MCP exposure.', 'livecanvas-forge-ai') . '</p>';
        }

        if ($public_write) {
            echo '<div class="lcfa-notice lcfa-notice--warning">';
            echo '<strong>' . esc_html__('Review exposed write abilities', 'livecanvas-forge-ai') . '</strong>';
            echo '<p>' . esc_html__('These abilities can write through an MCP-capable client. Keep this list as small as possible.', 'livecanvas-forge-ai') . '</p>';
            echo '</div>';
        }

        if ($available) {
            echo '<p class="lcfa-empty">' . esc_html(sprintf(__('Available write abilities: %d', 'livecanvas-forge-ai'), count($available))) . '</p>';
        }

        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections')) . '">' . esc_html__('Edit write allowlist', 'livecanvas-forge-ai') . '</a></p>';
        echo '</section>';
    }

    private function render_studio_runs_panel(array $history): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('activity');
        echo '<div><h2>' . esc_html__('Runs & audit', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Recent AI Bridge operations with audit IDs and rollback shortcuts when a local restore record exists.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        if (!$history) {
            echo '<p class="lcfa-empty">' . esc_html__('No audited run has been recorded yet.', 'livecanvas-forge-ai') . '</p>';
            echo '</section>';

            return;
        }

        echo '<div class="lcfa-studio-toolbar" data-lcfa-studio-run-controls>';
        echo '<label class="lcfa-studio-search"><span>' . esc_html__('Search runs', 'livecanvas-forge-ai') . '</span><input type="search" data-lcfa-studio-run-search placeholder="' . esc_attr__('Search action, audit ID, or target', 'livecanvas-forge-ai') . '"></label>';
        echo '<div class="lcfa-studio-segmented" role="group" aria-label="' . esc_attr__('Run filters', 'livecanvas-forge-ai') . '">';
        echo '<button type="button" class="button is-current" data-lcfa-studio-run-filter="all">' . esc_html__('All', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-run-filter="rollback">' . esc_html__('Rollback', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-run-filter="error">' . esc_html__('Errors', 'livecanvas-forge-ai') . '</button>';
        echo '<button type="button" class="button" data-lcfa-studio-run-filter="apply">' . esc_html__('Apply', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="lcfa-history-list">';
        foreach (array_slice($history, 0, 12) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $time = !empty($entry['time'])
                ? get_date_from_gmt((string) $entry['time'], get_option('date_format') . ' ' . get_option('time_format'))
                : '';
            $summary = (string) ($entry['summary'] ?? ($entry['message'] ?? __('Unnamed operation', 'livecanvas-forge-ai')));
            $target = '';

            if (!empty($entry['target_title'])) {
                $target = (string) $entry['target_title'];
            } elseif (!empty($entry['target_id'])) {
                $target = sprintf(__('Target #%d', 'livecanvas-forge-ai'), (int) $entry['target_id']);
            }
            $search_text = strtolower($summary . ' ' . $target . ' ' . (string) ($entry['action'] ?? '') . ' ' . (string) ($entry['audit_id'] ?? ''));

            echo '<div class="lcfa-history-item" data-lcfa-studio-run-item data-lcfa-search="' . esc_attr($search_text) . '" data-lcfa-status="' . esc_attr(!empty($entry['ok']) ? 'ok' : 'error') . '" data-lcfa-mode="' . esc_attr((string) ($entry['mode'] ?? '')) . '" data-lcfa-rollback="' . esc_attr(!empty($entry['rollback_available']) ? '1' : '0') . '">';
            echo '<div class="lcfa-history-copy">';
            echo '<strong>' . esc_html($summary !== '' ? $summary : __('Unnamed operation', 'livecanvas-forge-ai')) . '</strong>';
            if ($time !== '') {
                echo '<span>' . esc_html($time) . '</span>';
            }
            if ($target !== '') {
                echo '<span>' . esc_html($target) . '</span>';
            }
            echo '</div>';
            echo '<div class="lcfa-chip-row">';
            if (!empty($entry['action'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['action']) . '</span>';
            }
            if (!empty($entry['mode'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['mode']) . '</span>';
            }
            if (!empty($entry['execution_target'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['execution_target']) . '</span>';
            }
            echo '<span class="lcfa-chip' . (!empty($entry['ok']) ? ' is-positive' : ' is-negative') . '">' . esc_html(!empty($entry['ok']) ? __('OK', 'livecanvas-forge-ai') : __('Error', 'livecanvas-forge-ai')) . '</span>';
            if (!empty($entry['audit_id'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['audit_id']) . '</span>';
            }
            if (!empty($entry['rollback_available'])) {
                echo '<span class="lcfa-chip is-positive">' . esc_html__('Rollback ready', 'livecanvas-forge-ai') . '</span>';
                if (($entry['execution_target'] ?? 'local') === 'local' && !empty($entry['audit_id'])) {
                    echo '<a class="button button-small" href="' . esc_url($this->get_command_url([
                        'suggest_action' => 'restore_audit_rollback',
                        'audit_id'       => (string) $entry['audit_id'],
                    ])) . '">' . esc_html__('Restore', 'livecanvas-forge-ai') . '</a>';
                }
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<p class="lcfa-empty" data-lcfa-studio-run-empty hidden>' . esc_html__('No runs match the current filters.', 'livecanvas-forge-ai') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=command')) . '">' . esc_html__('Open full history in Command Deck', 'livecanvas-forge-ai') . '</a></p>';
        echo '</section>';
    }

    private function render_command_tab(array $settings, array $snapshot): void {
        $inventory      = $this->inventory->get_inventory();
        $actions        = $this->command_deck->get_actions();
        $command_result = LCFA_Settings::consume_command_result();
        $command_suggestion = LCFA_Settings::consume_command_suggestion();
        $history        = LCFA_Settings::get_history();
        $theme_context  = $this->context_builder->get_theme_context();
        $windpress      = is_array($theme_context['windpress'] ?? null) ? $theme_context['windpress'] : [];
        $mcp_status     = $this->context_builder->get_mcp_status();
        $local_bridge   = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];
        $remote_status  = $this->remote_client->get_status();
        $command_form   = $this->get_command_form_context($actions);
        $current_thread_id = LCFA_Settings::normalize_thread_id((string) ($command_form['thread_id'] ?? 'default'));
        $current_thread = LCFA_Settings::get_thread($current_thread_id);
        $thread_summaries = LCFA_Settings::get_thread_summaries();
        $theme_roots    = null;
        $theme_templates = null;

        try {
            $theme_roots = $this->theme_files_bridge->get_theme_roots();
            $theme_templates = $this->theme_files_bridge->list_templates([
                'root_scope' => 'active',
                'limit'      => 8,
            ]);
        } catch (Throwable $throwable) {
            $theme_roots = [
                'error' => $throwable->getMessage(),
            ];
        }

        echo '<div class="lcfa-grid">';
        echo '<div class="lcfa-main">';
        $this->render_command_result($command_result);
        $this->render_command_suggestion($command_suggestion);
        $this->render_command_thread_panel($current_thread, $thread_summaries, $command_form);

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Run command', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Execute LiveCanvas targets and WindPress maintenance operations from the same backend console.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        if (!empty($command_form['context_label'])) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Current editor context', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html($command_form['context_label']) . '</p>';
            echo '</div>';
        }

        if (!empty($command_form['genesis_task_id'])) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Genesis task', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html(sprintf(__('This command run is linked to Genesis task ID %s. Preview and apply results will update the Genesis plan progress automatically.', 'livecanvas-forge-ai'), (string) $command_form['genesis_task_id'])) . '</p>';
            echo '</div>';
        }

        if (!empty($local_bridge)) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Local build runtime', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html(!empty($local_bridge['build_available']) ? __('Local Node + MCP execution is available. You can run build_windpress_cache directly from this tab.', 'livecanvas-forge-ai') : ($local_bridge['message'] ?? __('Local MCP execution is not available.', 'livecanvas-forge-ai'))) . '</p>';
            if (!empty($local_bridge['node_version'])) {
                echo '<p><code>' . esc_html(sprintf(__('Node %s', 'livecanvas-forge-ai'), $local_bridge['node_version'])) . '</code></p>';
            }
            echo '</div>';
        }

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Remote runtime', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html(!empty($remote_status['available']) ? __('Remote execution is available. You can switch the execution target to remote and run the same companion actions on the other WordPress instance.', 'livecanvas-forge-ai') : ((string) ($remote_status['message'] ?? __('Remote execution is not configured yet.', 'livecanvas-forge-ai')))) . '</p>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Execution policy', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html(sprintf(
            __('Active profile: %1$s. File fallback: %2$s.', 'livecanvas-forge-ai'),
            (string) ($settings['permission_profile'] ?: 'advanced_templates'),
            !empty($settings['allow_file_fallback']) ? __('enabled', 'livecanvas-forge-ai') : __('disabled', 'livecanvas-forge-ai')
        )) . '</p>';
        echo '<p>' . esc_html__('The Command Deck now enforces this profile directly. Some apply operations may be downgraded to preview when the policy requires it.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
        echo '<input type="hidden" name="genesis_task_id" value="' . esc_attr((string) ($command_form['genesis_task_id'] ?? '')) . '">';
        echo '<input type="hidden" name="context_post_id" value="' . esc_attr((string) ($command_form['context_post_id'] ?? '')) . '">';

        echo '<label><span>' . esc_html__('Request to AI Bridge', 'livecanvas-forge-ai') . '</span><textarea name="user_prompt" rows="4" placeholder="' . esc_attr__('Describe the requested change, why it matters, or what the assistant should keep in mind for this run.', 'livecanvas-forge-ai') . '">' . esc_textarea($command_form['user_prompt']) . '</textarea></label>';

        echo '<label><span>' . esc_html__('Action', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="action">';
        foreach ($actions as $action_key => $action_data) {
            echo '<option value="' . esc_attr($action_key) . '"' . selected($command_form['action'], $action_key, false) . '>' . esc_html($action_data['label']) . '</option>';
        }
        echo '</select></label>';
        echo '<label><span>' . esc_html__('Execution target', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="execution_target">';
        echo '<option value="local"' . selected($command_form['execution_target'], 'local', false) . '>' . esc_html__('Local site', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="remote"' . selected($command_form['execution_target'], 'remote', false) . (!empty($remote_status['configured']) ? '' : ' disabled') . '>' . esc_html__('Remote site', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__('Target ID', 'livecanvas-forge-ai') . '</span><input type="text" name="target_id" value="' . esc_attr($command_form['target_id']) . '" placeholder="' . esc_attr__('Required for update actions.', 'livecanvas-forge-ai') . '"></label>';
        echo '<label><span>' . esc_html__('Variant', 'livecanvas-forge-ai') . '</span><input type="text" name="variant" value="' . esc_attr($command_form['variant']) . '" placeholder="1"></label>';
        echo '<label><span>' . esc_html__('Title', 'livecanvas-forge-ai') . '</span><input type="text" name="title" value="' . esc_attr($command_form['title']) . '" placeholder="' . esc_attr__('Used for create actions.', 'livecanvas-forge-ai') . '"></label>';
        echo '<label><span>' . esc_html__('Slug', 'livecanvas-forge-ai') . '</span><input type="text" name="slug" value="' . esc_attr($command_form['slug']) . '" placeholder="optional-slug"></label>';
        echo '<label><span>' . esc_html__('WindPress provider ID', 'livecanvas-forge-ai') . '</span><input type="text" name="provider_id" value="' . esc_attr($command_form['provider_id']) . '" placeholder="wordpress-theme-json, livecanvas-content"></label>';
        echo '<label><span>' . esc_html__('WindPress relative path', 'livecanvas-forge-ai') . '</span><input type="text" name="relative_path" value="' . esc_attr($command_form['relative_path']) . '" placeholder="main.css"></label>';
        echo '<label><span>' . esc_html__('Theme root scope', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="root_scope">';
        echo '<option value="stylesheet"' . selected($command_form['root_scope'], 'stylesheet', false) . '>' . esc_html__('Stylesheet theme', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="template"' . selected($command_form['root_scope'], 'template', false) . '>' . esc_html__('Template theme', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="active"' . selected($command_form['root_scope'], 'active', false) . '>' . esc_html__('Active roots', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="all"' . selected($command_form['root_scope'], 'all', false) . '>' . esc_html__('All roots', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';
        echo '<label><span>' . esc_html__('Theme file path', 'livecanvas-forge-ai') . '</span><input type="text" name="file_path" value="' . esc_attr($command_form['file_path']) . '" placeholder="views/single.twig"></label>';
        echo '<label><span>' . esc_html__('Backup ID', 'livecanvas-forge-ai') . '</span><input type="text" name="backup_id" value="' . esc_attr($command_form['backup_id']) . '" placeholder="2026-04-03/theme-name/..."></label>';
        echo '<label><span>' . esc_html__('Audit ID', 'livecanvas-forge-ai') . '</span><input type="text" name="audit_id" value="' . esc_attr($command_form['audit_id']) . '" placeholder="audit-..."></label>';
        echo '<label><span>' . esc_html__('Post status', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="status">';
        echo '<option value="draft"' . selected($command_form['status'], 'draft', false) . '>' . esc_html__('Draft', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="publish"' . selected($command_form['status'], 'publish', false) . '>' . esc_html__('Publish', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="private"' . selected($command_form['status'], 'private', false) . '>' . esc_html__('Private', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="pending"' . selected($command_form['status'], 'pending', false) . '>' . esc_html__('Pending', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';
        echo '<label><span>' . esc_html__('HTML / template / CSS / theme.json content', 'livecanvas-forge-ai') . '</span><textarea name="content" rows="16" placeholder="' . esc_attr__('<section>...</section>', 'livecanvas-forge-ai') . '">' . esc_textarea($command_form['content']) . '</textarea></label>';
        echo '<label class="lcfa-checkbox"><input type="checkbox" name="dry_run" value="1"' . checked($command_form['dry_run'], true, false) . '> ' . esc_html__('Run as preview only', 'livecanvas-forge-ai') . '</label>';

        echo '<div class="lcfa-cta-row">';
        echo '<button class="button" type="submit" name="analyze_request" value="1">' . esc_html__('Analyze request', 'livecanvas-forge-ai') . '</button>';
        echo '<button class="button button-primary" type="submit">' . esc_html__('Run command', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Action guide', 'livecanvas-forge-ai') . '</h3>';
        echo '<ul class="lcfa-bullets">';
        foreach ($actions as $action_key => $action_data) {
            echo '<li><strong>' . esc_html($action_data['label']) . '</strong>: ' . esc_html($action_data['description']) . ' ';
            echo '<code>' . esc_html($action_key) . '</code></li>';
        }
        echo '<li>' . esc_html__('For build_windpress_cache, the provider field accepts one provider ID or a comma-separated list.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Use Analyze request to convert a natural-language prompt into a safer suggested action before you run it.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('restore_theme_backup expects a backup ID. Use the backup list below to prefill it safely.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('restore_audit_rollback expects an audit ID from a previous local apply run and can be previewed before restoring.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Remote execution reuses the same companion routes on the target WordPress, so target IDs and template paths must belong to that remote site.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('layers');
        echo '<div><h2>' . esc_html__('Inventory', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('LiveCanvas-aware targets currently discoverable by the companion.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        $this->render_inventory_panel($inventory);
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('wind');
        echo '<div><h2>' . esc_html__('WindPress runtime', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Provider scans, local cache builds, and cache maintenance for Picowind and Tailwind workflows.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        $this->render_windpress_panel($windpress, $local_bridge);
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('file-earmark');
        echo '<div><h2>' . esc_html__('Theme files', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Fallback templates and theme files available to the companion inside the active theme roots.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        $this->render_theme_files_panel($theme_roots, $theme_templates);
        echo '</section>';

        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_history_panel($history);
        echo '</aside>';
        echo '</div>';
    }

    private function render_preflight_step(array $snapshot): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('shield-check');
        echo '<div><h2>' . esc_html__('Step 1. LiveCanvas preflight', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The companion verifies that LiveCanvas really exists and is active before anything else happens.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-status-list">';
        $this->render_status_row(
            __('LiveCanvas plugin and license active', 'livecanvas-forge-ai'),
            !empty($snapshot['livecanvas_installed']) && !empty($snapshot['livecanvas_active']) && !empty($snapshot['livecanvas_license_active']),
            !empty($snapshot['livecanvas_license_active'])
                ? __('Plugin active with license/API key detected.', 'livecanvas-forge-ai')
                : __('Plugin or license/API key missing.', 'livecanvas-forge-ai')
        );
        $this->render_status_row(
            __('Detected LiveCanvas themes', 'livecanvas-forge-ai'),
            $this->has_livecanvas_theme_stack($snapshot),
            $this->get_preflight_theme_summary($snapshot)
        );

        if ($this->has_picowind_stack($snapshot)) {
            $this->render_status_row(
                __('WindPress active for Picowind', 'livecanvas-forge-ai'),
                !empty($snapshot['windpress_installed']) && !empty($snapshot['windpress_active']),
                !empty($snapshot['windpress_installed'])
                    ? __('WindPress plugin detected. It must stay active for Picowind.', 'livecanvas-forge-ai')
                    : __('WindPress plugin not detected.', 'livecanvas-forge-ai')
            );
        }
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="1">';

        if (!$snapshot['livecanvas_installed']) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('LiveCanvas is not installed. The wizard stops here until the primary plugin is installed.', 'livecanvas-forge-ai') . '</p></div>';
        } elseif (!$snapshot['livecanvas_active']) {
            echo '<p>' . esc_html__('LiveCanvas is installed but inactive. You can activate it directly from this screen.', 'livecanvas-forge-ai') . '</p>';
            echo '<button class="button button-primary" name="activate_livecanvas" value="1">' . esc_html__('Activate LiveCanvas', 'livecanvas-forge-ai') . '</button>';
        } elseif (!$this->is_preflight_ready($snapshot)) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($this->get_preflight_blocking_message($snapshot)) . '</p></div>';
        } else {
            echo '<p>' . esc_html__('LiveCanvas stack is ready. Move to the framework confirmation step.', 'livecanvas-forge-ai') . '</p>';
            echo '<button class="button button-primary">' . esc_html__('Continue', 'livecanvas-forge-ai') . '</button>';
        }

        echo '</form>';
        echo '</section>';
    }

    private function render_framework_step(array $settings, array $snapshot): void {
        $selected = $settings['framework'] ?: $snapshot['detected_framework'];

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('layers');
        echo '<div><h2>' . esc_html__('Step 2. Frontend framework', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Confirm whether this site should run on Picostrap or Picowind. The wizard detects the current theme and can switch to the correct stack.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="2">';

        echo '<div class="lcfa-choice-grid">';
        $this->render_framework_choice(
            'picostrap',
            __('Picostrap / Bootstrap', 'livecanvas-forge-ai'),
            __('Recommended for classic Bootstrap-based LiveCanvas flows and Picostrap child themes.', 'livecanvas-forge-ai'),
            $selected,
            $snapshot['picostrap_candidates']
        );
        $this->render_framework_choice(
            'picowind',
            __('Picowind / Tailwind + WindPress', 'livecanvas-forge-ai'),
            __('Recommended for Tailwind-first builds, Picowind child themes, and WindPress-managed styling.', 'livecanvas-forge-ai'),
            $selected,
            $snapshot['picowind_candidates']
        );
        echo '</div>';

        echo '<button class="button button-primary">' . esc_html__('Confirm framework', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';
    }

    private function render_site_mode_step(array $settings, array $snapshot): void {
        $selected = $settings['site_mode'] ?: $snapshot['site_mode'];

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('globe');
        echo '<div><h2>' . esc_html__('Step 3. Site profile', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('This decision does not change the core plugin. It changes the connection guide and the recommended auth flow shown to AI clients.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="3">';

        echo '<div class="lcfa-radio-group lcfa-radio-group--inline">';
        $this->render_radio('site_mode', 'local', __('This WordPress runs locally', 'livecanvas-forge-ai'), $selected, 'laptop');
        $this->render_radio('site_mode', 'remote', __('This WordPress runs remotely', 'livecanvas-forge-ai'), $selected, 'cloud');
        $this->render_radio('site_mode', 'hybrid', __('I will use both local and remote targets', 'livecanvas-forge-ai'), $selected, 'shuffle');
        echo '</div>';

        echo '<button class="button button-primary">' . esc_html__('Save site profile', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';
    }

    private function render_ai_tool_step(array $settings): void {
        $selected  = $settings['ai_tool'] ?: 'codex';
        if ($selected === 'claude-code') {
            $selected = 'claude';
        }
        $site_mode = LCFA_Settings::get()['site_mode'] ?: 'local';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Step 4. AI Coding Agent', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Choose the main coding agent you want to use first. The setup guide adapts to the current site profile and connection strategy.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="4">';
        echo '<div class="lcfa-radio-group lcfa-radio-group--inline">';
        $this->render_radio('ai_tool', 'codex', __('Codex', 'livecanvas-forge-ai'), $selected, 'stars');
        $this->render_radio('ai_tool', 'opencode', __('OpenCode', 'livecanvas-forge-ai'), $selected, 'braces');
        $this->render_radio('ai_tool', 'claude', __('Claude', 'livecanvas-forge-ai'), $selected, 'cpu');
        $this->render_radio('ai_tool', 'cursor', __('Cursor', 'livecanvas-forge-ai'), $selected, 'cursor');
        $this->render_radio('ai_tool', 'other', __('Other compatible client', 'livecanvas-forge-ai'), $selected, 'plug');
        echo '</div>';
        echo '<button class="button button-primary">' . esc_html__('Save AI Coding Agent', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        $guides = $this->get_tool_guides($site_mode);
        $guide  = $guides[$selected] ?? ($selected === 'claude-code' ? ($guides['claude'] ?? $guides['other']) : $guides['other']);

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Configuration guide', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html($guide['summary']) . '</p>';
        echo '<ul class="lcfa-bullets">';
        foreach ($guide['steps'] as $line) {
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</section>';
    }

    private function render_permissions_step(array $settings): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('shield-lock');
        echo '<div><h2>' . esc_html__('Step 5. Enable Full Access', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Continue only if you want AI Bridge to operate with the broadest execution scope for this project.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="5">';

        echo '<p>' . esc_html__('This grants the companion the broadest operating scope: it can create and update pages, sections, templates, headers, footers, and other advanced outputs without stopping on intermediate policy choices.', 'livecanvas-forge-ai') . '</p>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html__('Advanced template actions are enabled immediately.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Theme or PHP file fallback is allowed only as a last resort when the active stack requires it.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('By continuing, you are explicitly authorizing AI Bridge to operate with these permissions on this site.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';

        echo '<button class="button button-primary">' . esc_html__('Continue', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';
    }

    private function render_finish_step(array $settings, array $snapshot): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('rocket');
        echo '<div><h2>' . esc_html__('Step 6. Finish setup', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The stack is profiled. The next useful move is to open Connections, verify the coding agent link, then save the project brief when you want a reusable build plan.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('Confirmed framework: %s.', 'livecanvas-forge-ai'), $settings['framework'] ?: $snapshot['detected_framework'])) . '</li>';
        echo '<li>' . esc_html(sprintf(__('Site profile: %s.', 'livecanvas-forge-ai'), $settings['site_mode'] ?: $snapshot['site_mode'])) . '</li>';
        echo '<li>' . esc_html(sprintf(__('Primary AI client: %s.', 'livecanvas-forge-ai'), $settings['ai_tool'] ?: 'codex')) . '</li>';
        echo '<li>' . esc_html__('Access mode: Full access enabled.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Fallback policy: theme or PHP file fallback is available only as a last resort.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="6">';
        echo '<button class="button button-primary">' . esc_html__('Complete Bridge Setup', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';
    }

    private function render_setup_reset_panel(): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('x-circle');
        echo '<div><h2>' . esc_html__('Reset setup', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Use this when you want to switch AI Coding Agent, change the site profile, or rerun the setup wizard from scratch.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html__('This does not uninstall the plugin or remove pages, sections, templates, or other WordPress content.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Connections are cleared and a new MCP token is generated.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Project Brief and Command Deck history stay intact.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('If you already wrote a client config into your workspace, reset will not delete that file.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Any previously written or downloaded client bundle may no longer match the new token after reset.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('The wizard goes back to step 1 and asks for framework, site profile, AI Coding Agent, and access confirmation again.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';

        echo '<div class="lcfa-cta-row">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_reset_setup');
        echo '<input type="hidden" name="action" value="lcfa_reset_setup">';
        echo '<button class="button" type="submit">' . esc_html__('Reset setup and start again', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</section>';
    }

    private function render_step_nav(int $current_step, array $settings, array $snapshot): void {
        $steps = [
            1 => ['label' => __('Preflight', 'livecanvas-forge-ai'), 'icon' => 'shield-check'],
            2 => ['label' => __('Framework', 'livecanvas-forge-ai'), 'icon' => 'layers'],
            3 => ['label' => __('Site', 'livecanvas-forge-ai'), 'icon' => 'globe'],
            4 => ['label' => __('AI Coding Agent', 'livecanvas-forge-ai'), 'icon' => 'command'],
            5 => ['label' => __('Access', 'livecanvas-forge-ai'), 'icon' => 'shield-lock'],
            6 => ['label' => __('Finish', 'livecanvas-forge-ai'), 'icon' => 'rocket'],
        ];

        echo '<nav class="lcfa-steps">';

        foreach ($steps as $step => $data) {
            $is_locked = $step > 1 && !$snapshot['livecanvas_active'];
            $classes   = ['lcfa-step'];
            $label     = $step . '. ' . $data['label'];

            if ($step === $current_step) {
                $classes[] = 'is-current';
            }

            if ((int) $settings['last_completed_step'] >= $step) {
                $classes[] = 'is-done';
            }

            $content = '<span class="lcfa-step-icon-wrap">' . $this->get_icon_svg($data['icon']) . '</span><span>' . esc_html($label) . '</span>';

            if ($is_locked) {
                echo '<span class="' . esc_attr(implode(' ', $classes)) . '">' . $content . '</span>';
                continue;
            }

            echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=' . $step)) . '">' . $content . '</a>';
        }

        echo '</nav>';
    }

    private function render_snapshot_card(array $snapshot, array $settings): void {
        echo '<section class="lcfa-card lcfa-snapshot-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('activity');
        echo '<div><h2>' . esc_html__('Stack snapshot', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Live runtime, active theme, and editor profile as detected right now.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-brand-grid">';
        $this->render_brand_tile(
            __('LiveCanvas', 'livecanvas-forge-ai'),
            $this->get_partner_logo('livecanvas-micro'),
            $snapshot['livecanvas_active'] ? __('Active', 'livecanvas-forge-ai') : __('Missing or inactive', 'livecanvas-forge-ai'),
            $snapshot['livecanvas_active'] ? 'active' : 'other'
        );
        $this->render_brand_tile(
            $snapshot['detected_framework'] === 'picowind' ? __('Picowind', 'livecanvas-forge-ai') : __('Bootstrap', 'livecanvas-forge-ai'),
            $snapshot['detected_framework'] === 'picowind' ? $this->get_icon_svg('wind') : $this->get_partner_logo('bootstrap'),
            $snapshot['framework_slug'] ?: __('No editor config detected', 'livecanvas-forge-ai'),
            'other'
        );
        $this->render_brand_tile(
            __('WindPress', 'livecanvas-forge-ai'),
            $this->get_partner_logo('windpress'),
            $snapshot['windpress_active'] ? __('Active', 'livecanvas-forge-ai') : ($snapshot['windpress_installed'] ? __('Installed', 'livecanvas-forge-ai') : __('Not installed', 'livecanvas-forge-ai')),
            $snapshot['windpress_active'] ? 'active' : 'other'
        );
        echo '</div>';

        echo '<ul class="lcfa-facts">';
        $this->render_fact_row(__('Theme', 'livecanvas-forge-ai'), $snapshot['current_theme_name'], 'active');
        $this->render_fact_row(__('Detected framework', 'livecanvas-forge-ai'), $snapshot['detected_framework'], $snapshot['detected_framework'] !== 'unknown' ? 'active' : 'other');
        $this->render_fact_row(__('Editor config', 'livecanvas-forge-ai'), $snapshot['framework_slug'] ?: 'n/a', $snapshot['framework_slug'] ? 'active' : 'other');
        $this->render_fact_row(__('Site profile', 'livecanvas-forge-ai'), $settings['site_mode'] ?: $snapshot['site_mode'], !empty($settings['site_mode']) ? 'active' : 'other');
        $this->render_fact_row(__('Tangible', 'livecanvas-forge-ai'), $snapshot['tangible_available'] ? __('Available', 'livecanvas-forge-ai') : __('Unavailable', 'livecanvas-forge-ai'), $snapshot['tangible_available'] ? 'active' : 'other');
        $this->render_fact_row(__('WooCommerce', 'livecanvas-forge-ai'), $snapshot['woocommerce_active'] ? __('Detected', 'livecanvas-forge-ai') : __('Not detected', 'livecanvas-forge-ai'), $snapshot['woocommerce_active'] ? 'active' : 'other');
        $this->render_fact_row(__('ACF', 'livecanvas-forge-ai'), $snapshot['acf_active'] ? __('Detected', 'livecanvas-forge-ai') : __('Not detected', 'livecanvas-forge-ai'), $snapshot['acf_active'] ? 'active' : 'other');
        echo '</ul>';
        echo '</section>';
    }

    private function render_framework_choice(string $value, string $title, string $description, string $selected, array $candidates): void {
        $logo_markup = $value === 'picowind'
            ? $this->get_partner_logo('windpress')
            : $this->get_partner_logo('bootstrap');
        $candidate_summary = $candidates
            ? sprintf(__('Detected theme candidates: %s', 'livecanvas-forge-ai'), implode(', ', wp_list_pluck($candidates, 'stylesheet')))
            : __('No installed themes were detected for this family yet.', 'livecanvas-forge-ai');

        echo '<label class="lcfa-choice-card">';
        echo '<input type="radio" name="framework" value="' . esc_attr($value) . '"' . checked($selected, $value, false) . '>';
        echo '<span class="lcfa-choice-copy">';
        echo '<span class="lcfa-choice-media">' . $logo_markup . '</span>';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<span>' . esc_html($description) . '</span>';
        echo '<small>' . esc_html($candidate_summary) . '</small>';

        if ($value === 'picowind' && !$candidates) {
            echo '<small>' . esc_html__('Selecting Picowind installs the latest Picowind release from GitHub before the wizard switches the stack.', 'livecanvas-forge-ai') . ' <code>https://github.com/livecanvas-team/picowind/releases/latest</code></small>';
        }

        echo '</span>';
        echo '</label>';
    }

    private function render_status_row(string $label, bool $status, string $detail = ''): void {
        echo '<div class="lcfa-status-row">';
        echo '<span class="lcfa-status-copy">';
        echo '<span class="lcfa-status-label">' . esc_html($label) . '</span>';
        if ($detail !== '') {
            echo '<small>' . esc_html($detail) . '</small>';
        }
        echo '</span>';
        echo '<strong class="' . esc_attr($status ? 'ok' : 'ko') . '">' . $this->get_icon_svg($status ? 'check-circle' : 'x-circle') . '<span>' . esc_html($status ? 'OK' : 'KO') . '</span></strong>';
        echo '</div>';
    }

    private function is_preflight_ready(array $snapshot): bool {
        if (empty($snapshot['livecanvas_installed']) || empty($snapshot['livecanvas_active']) || empty($snapshot['livecanvas_license_active'])) {
            return false;
        }

        if (!$this->has_livecanvas_theme_stack($snapshot)) {
            return false;
        }

        if ($this->has_picowind_stack($snapshot)) {
            return !empty($snapshot['windpress_installed']) && !empty($snapshot['windpress_active']);
        }

        return true;
    }

    private function get_preflight_blocking_message(array $snapshot): string {
        if (empty($snapshot['livecanvas_installed'])) {
            return __('LiveCanvas is not installed. The wizard stops here until the primary plugin is installed.', 'livecanvas-forge-ai');
        }

        if (empty($snapshot['livecanvas_active'])) {
            return __('LiveCanvas is installed but inactive. Activate it before continuing.', 'livecanvas-forge-ai');
        }

        if (empty($snapshot['livecanvas_license_active'])) {
            return __('LiveCanvas is active, but its license/API key is not active. Activate LiveCanvas before continuing.', 'livecanvas-forge-ai');
        }

        if (!$this->has_livecanvas_theme_stack($snapshot)) {
            return __('No Picostrap or Picowind theme was detected. Install or activate a supported LiveCanvas theme before continuing.', 'livecanvas-forge-ai');
        }

        if ($this->has_picowind_stack($snapshot) && (empty($snapshot['windpress_installed']) || empty($snapshot['windpress_active']))) {
            return __('Picowind is installed, so WindPress must be installed and active before continuing.', 'livecanvas-forge-ai');
        }

        return __('Complete the LiveCanvas preflight before continuing.', 'livecanvas-forge-ai');
    }

    private function has_livecanvas_theme_stack(array $snapshot): bool {
        if (!empty($snapshot['picostrap_candidates']) || !empty($snapshot['picowind_candidates'])) {
            return true;
        }

        return in_array((string) ($snapshot['detected_framework'] ?? ''), ['picostrap', 'picowind'], true);
    }

    private function has_picowind_stack(array $snapshot): bool {
        return !empty($snapshot['picowind_candidates']) || (string) ($snapshot['detected_framework'] ?? '') === 'picowind';
    }

    private function normalize_supported_framework(string $framework): string {
        $framework = sanitize_key($framework);

        return in_array($framework, ['picostrap', 'picowind'], true) ? $framework : '';
    }

    private function get_framework_display_name(string $framework): string {
        $framework = $this->normalize_supported_framework($framework);

        if ($framework === 'picowind') {
            return __('Picowind / Tailwind + WindPress', 'livecanvas-forge-ai');
        }

        if ($framework === 'picostrap') {
            return __('Picostrap / Bootstrap', 'livecanvas-forge-ai');
        }

        return ucfirst(str_replace('-', ' ', $framework));
    }

    private function get_preflight_theme_summary(array $snapshot): string {
        $groups = [];
        $picostrap = $this->format_theme_candidate_names((array) ($snapshot['picostrap_candidates'] ?? []));
        $picowind = $this->format_theme_candidate_names((array) ($snapshot['picowind_candidates'] ?? []));

        if ($picostrap !== '') {
            $groups[] = sprintf(__('Picostrap: %s', 'livecanvas-forge-ai'), $picostrap);
        }

        if ($picowind !== '') {
            $groups[] = sprintf(__('Picowind: %s', 'livecanvas-forge-ai'), $picowind);
        }

        if ($groups) {
            return implode(' · ', $groups);
        }

        $detected = (string) ($snapshot['detected_framework'] ?? '');
        if (in_array($detected, ['picostrap', 'picowind'], true)) {
            return sprintf(__('Active stack detected as %s, but no installed theme candidate list was returned.', 'livecanvas-forge-ai'), $detected);
        }

        return __('No Picostrap or Picowind themes detected.', 'livecanvas-forge-ai');
    }

    private function format_theme_candidate_names(array $candidates): string {
        $names = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $name = trim((string) ($candidate['stylesheet'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($candidate['name'] ?? ''));
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));

        return implode(', ', $names);
    }

    private function render_radio(string $name, string $value, string $label, string $selected, string $icon, string $description = ''): void {
        echo '<label class="lcfa-radio">';
        echo '<input class="lcfa-radio-input" type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . checked($selected, $value, false) . '>';
        echo '<span class="lcfa-radio-icon">' . $this->get_radio_icon_markup($name, $value, $icon) . '</span>';
        echo '<span class="lcfa-radio-copy">' . esc_html($label);
        if ($description !== '') {
            echo '<small>' . esc_html($description) . '</small>';
        }
        echo '</span>';
        echo '</label>';
    }

    private function render_notice(?array $notice): void {
        if (!$notice) {
            return;
        }

        $class = $notice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    private function render_page_header(string $tab, array $snapshot, array $settings): void {
        $hero = $this->admin_hero_presenter->build($tab, $snapshot, $settings);

        echo '<section class="lcfa-hero">';
        echo '<div class="lcfa-hero-main">';
        echo '<div class="lcfa-hero-copy">';
        echo '<div class="lcfa-kicker">';
        echo '<span class="lcfa-kicker-brand" aria-hidden="true">' . $this->get_partner_logo('livecanvas') . '</span>';
        echo '<span>' . esc_html__('AI Bridge', 'livecanvas-forge-ai') . '</span>';
        echo '</div>';
        echo '<h1>' . esc_html((string) ($hero['title'] ?? '')) . '</h1>';
        echo '<p class="lcfa-lead">' . esc_html((string) ($hero['subtitle'] ?? '')) . '</p>';
        echo '</div>';
        echo '<div class="lcfa-hero-meta">';
        echo '<div class="lcfa-hero-stack">';
        foreach ((array) ($hero['marks'] ?? []) as $mark) {
            if (!is_array($mark)) {
                continue;
            }

            $this->render_admin_hero_mark($mark);
        }
        echo '</div>';
        echo '<div class="lcfa-hero-chips">';
        foreach ((array) ($hero['chips'] ?? []) as $chip) {
            if (!is_array($chip)) {
                continue;
            }

            $this->render_admin_hero_chip($chip);
        }
        echo '</div>';
        echo '<details class="lcfa-hero-details-panel">';
        echo '<summary class="lcfa-hero-details-toggle" aria-label="' . esc_attr__('Details', 'livecanvas-forge-ai') . '"><span class="lcfa-hero-details-toggle__icon" aria-hidden="true">i</span><span class="screen-reader-text">' . esc_html__('Details', 'livecanvas-forge-ai') . '</span></summary>';
        echo '<div class="lcfa-hero-details">';
        foreach ((array) ($hero['details'] ?? []) as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            echo '<div class="lcfa-hero-detail">';
            echo '<span class="lcfa-hero-detail-label">' . esc_html((string) ($detail['label'] ?? '')) . '</span>';
            echo '<strong class="lcfa-hero-detail-value">' . esc_html((string) ($detail['value'] ?? '')) . '</strong>';
            echo '</div>';
        }
        echo '</div>';
        echo '</details>';
        echo '</div>';
        echo '</section>';
    }

    private function render_admin_hero_mark(array $mark): void {
        $asset = (string) ($mark['asset'] ?? '');
        $label = (string) ($mark['label'] ?? '');
        $active = !empty($mark['active']);
        $type = (string) ($mark['type'] ?? 'partner');
        $media = $type === 'icon'
            ? $this->get_icon_svg($asset ?: 'stars')
            : $this->get_partner_logo($asset ?: 'livecanvas-micro');

        echo '<span class="lcfa-hero-mark' . ($active ? ' is-active' : '') . '" aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '">';
        echo '<span class="lcfa-hero-mark-media">' . $media . '</span>';
        echo '</span>';
    }

    private function render_admin_hero_chip(array $chip): void {
        $tone = in_array((string) ($chip['tone'] ?? 'other'), ['active', 'other'], true) ? (string) $chip['tone'] : 'other';
        $label = (string) ($chip['label'] ?? '');
        $value = (string) ($chip['value'] ?? '');
        $client = (string) ($chip['client'] ?? '');

        echo '<span class="lcfa-hero-chip is-' . esc_attr($tone) . '">';
        if ($client !== '') {
            echo '<span class="lcfa-hero-chip-media">' . $this->get_agent_icon_markup($client, $this->get_client_fallback_icon($client), 'lcfa-agent-icon lcfa-agent-icon--sm') . '</span>';
        }
        echo '<strong>' . esc_html($label) . '</strong>';
        echo '<span>' . esc_html($value) . '</span>';
        echo '</span>';
    }

    private function render_brand_tile(string $title, string $logo_markup, string $caption, string $tone = 'other'): void {
        $tone_class = in_array($tone, ['active', 'other'], true) ? $tone : 'other';

        echo '<div class="lcfa-brand-tile is-' . esc_attr($tone_class) . '">';
        echo '<div class="lcfa-brand-logo">' . $logo_markup . '</div>';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<span class="lcfa-brand-caption">' . esc_html($caption) . '</span>';
        echo '</div>';
    }

    private function render_fact_row(string $label, string $value, string $tone = 'other'): void {
        $tone_class = in_array($tone, ['active', 'other'], true) ? $tone : 'other';

        echo '<li><strong>' . esc_html($label) . ':</strong> <span class="lcfa-fact-value is-' . esc_attr($tone_class) . '">' . esc_html($value) . '</span></li>';
    }

    private function render_badge(string $label, string $value, string $tone = 'other'): void {
        $tone_class = in_array($tone, ['active', 'other'], true) ? $tone : 'other';

        echo '<span class="lcfa-badge is-' . esc_attr($tone_class) . '"><strong>' . esc_html($label) . '</strong><span>' . esc_html($value) . '</span></span>';
    }

    private function render_command_thread_panel(array $current_thread, array $thread_summaries, array $command_context = []): void {
        $current_thread_id = (string) ($current_thread['id'] ?? 'default');
        $messages = array_reverse((array) ($current_thread['messages'] ?? []));

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('activity');
        echo '<div><h2>' . esc_html__('Command thread', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Keep requests, execution notes, and results in one persistent thread instead of losing them between runs.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-thread-toolbar">';
        echo '<div class="lcfa-chip-row">';
        foreach (array_slice($thread_summaries, 0, 8) as $thread_summary) {
            if (!is_array($thread_summary)) {
                continue;
            }

            $thread_id = (string) ($thread_summary['id'] ?? '');
            $thread_url_args = [
                'thread_id' => $thread_id,
            ];

            if (!empty($command_context['context_post_id'])) {
                $thread_url_args['post_id'] = absint($command_context['context_post_id']);
            }

            if (!empty($command_context['genesis_task_id'])) {
                $thread_url_args['genesis_task_id'] = sanitize_key((string) $command_context['genesis_task_id']);
            }

            if (!empty($command_context['user_prompt'])) {
                $thread_url_args['user_prompt'] = sanitize_textarea_field((string) $command_context['user_prompt']);
            }

            $thread_url = $this->get_command_url($thread_url_args);

            echo '<a class="lcfa-thread-switch' . ($thread_id === $current_thread_id ? ' is-current' : '') . '" href="' . esc_url($thread_url) . '">';
            echo '<strong>' . esc_html((string) ($thread_summary['title'] ?? __('Thread', 'livecanvas-forge-ai'))) . '</strong>';
            echo '<span>' . esc_html(sprintf(_n('%d message', '%d messages', (int) ($thread_summary['message_count'] ?? 0), 'livecanvas-forge-ai'), (int) ($thread_summary['message_count'] ?? 0))) . '</span>';
            echo '</a>';
        }
        echo '</div>';

        echo '<details class="lcfa-command-details" data-lcfa-command-thread-tools>';
        echo '<summary>' . esc_html__('Thread tools', 'livecanvas-forge-ai') . '</summary>';
        echo '<div class="lcfa-command-details__body">';
        echo '<div class="lcfa-cta-row">';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-thread-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_operation" value="create">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
        $this->render_command_thread_context_inputs($command_context);
        echo '<input type="text" name="thread_title" value="" placeholder="' . esc_attr__('New thread title', 'livecanvas-forge-ai') . '">';
        echo '<button class="button" type="submit">' . esc_html__('New thread', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_operation" value="duplicate">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
        $this->render_command_thread_context_inputs($command_context);
        echo '<button class="button" type="submit">' . esc_html__('Duplicate current', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-thread-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_operation" value="rename">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
        $this->render_command_thread_context_inputs($command_context);
        echo '<input type="text" name="thread_title" value="' . esc_attr((string) ($current_thread['title'] ?? '')) . '" placeholder="' . esc_attr__('Rename current thread', 'livecanvas-forge-ai') . '">';
        echo '<button class="button" type="submit">' . esc_html__('Rename current', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_operation" value="clear">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
        $this->render_command_thread_context_inputs($command_context);
        echo '<button class="button" type="submit">' . esc_html__('Clear current', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        if ($current_thread_id !== 'default') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_command');
            echo '<input type="hidden" name="action" value="lcfa_command">';
            echo '<input type="hidden" name="thread_operation" value="delete">';
            echo '<input type="hidden" name="thread_id" value="' . esc_attr($current_thread_id) . '">';
            $this->render_command_thread_context_inputs($command_context);
            echo '<button class="button" type="submit">' . esc_html__('Delete current', 'livecanvas-forge-ai') . '</button>';
            echo '</form>';
        }

        echo '</div>';
        echo '</div>';
        echo '</details>';
        echo '</div>';

        if (!empty($command_context['context_post_id']) || !empty($command_context['genesis_task_id']) || !empty($command_context['user_prompt'])) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Current context', 'livecanvas-forge-ai') . '</h3>';
            echo '<div class="lcfa-chip-row">';

            if (!empty($command_context['context_post_id'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Post %d', 'livecanvas-forge-ai'), absint($command_context['context_post_id']))) . '</span>';
            }

            if (!empty($command_context['genesis_task_id'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Genesis task %s', 'livecanvas-forge-ai'), sanitize_key((string) $command_context['genesis_task_id']))) . '</span>';
            }

            if (!empty($command_context['user_prompt'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Prompt: %s', 'livecanvas-forge-ai'), sanitize_textarea_field((string) $command_context['user_prompt']))) . '</span>';
            }

            echo '</div>';
            echo '</div>';
        }

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html((string) ($current_thread['title'] ?? __('Thread', 'livecanvas-forge-ai'))) . '</h3>';
        echo '<p>' . esc_html(sprintf(
            __('Current thread ID: %1$s. Messages are persisted and reused across previews and apply runs.', 'livecanvas-forge-ai'),
            $current_thread_id
        )) . '</p>';
        echo '</div>';

        if (!$messages) {
            echo '<p class="lcfa-empty">' . esc_html__('No messages in this thread yet. Start with a request, then run a command to capture the result here.', 'livecanvas-forge-ai') . '</p>';
            echo '</section>';

            return;
        }

        echo '<div class="lcfa-thread-log">';
        foreach (array_slice($messages, -24) as $message) {
            if (!is_array($message)) {
                continue;
            }

            $this->render_command_thread_message($message);
        }
        echo '</div>';
        echo '</section>';
    }

    private function render_command_thread_context_inputs(array $command_context): void {
        if (!empty($command_context['context_post_id'])) {
            echo '<input type="hidden" name="context_post_id" value="' . esc_attr((string) absint($command_context['context_post_id'])) . '">';
        }

        if (!empty($command_context['genesis_task_id'])) {
            echo '<input type="hidden" name="genesis_task_id" value="' . esc_attr((string) sanitize_key((string) $command_context['genesis_task_id'])) . '">';
        }

        if (!empty($command_context['user_prompt'])) {
            echo '<input type="hidden" name="user_prompt" value="' . esc_attr((string) sanitize_textarea_field((string) $command_context['user_prompt'])) . '">';
        }
    }

    private function render_command_thread_message(array $message): void {
        $thread_id = LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default'));
        $message = LCFA_Thread_Message_Actions::decorate_message($message, [
            'thread_id' => $thread_id,
            'command_url_builder' => [$this, 'get_command_url'],
        ]);
        $role = in_array($message['role'] ?? '', ['user', 'assistant', 'suggestion_result', 'system', 'tool_result'], true) ? (string) $message['role'] : 'assistant';
        $time = !empty($message['time'])
            ? get_date_from_gmt((string) $message['time'], get_option('date_format') . ' ' . get_option('time_format'))
            : '';
        $label = (string) ($message['label'] ?? '');
        $content = trim((string) ($message['content'] ?? ''));
        $meta = is_array($message['meta'] ?? null) ? $message['meta'] : [];

        echo '<article class="lcfa-thread-message is-' . esc_attr($role) . '">';
        echo '<div class="lcfa-thread-message__head">';
        echo '<strong>' . esc_html($label !== '' ? $label : ucfirst($role)) . '</strong>';
        if ($time !== '') {
            echo '<span>' . esc_html($time) . '</span>';
        }
        echo '</div>';
        if ($content !== '') {
            echo '<div class="lcfa-thread-message__body"><pre>' . esc_html($content) . '</pre></div>';
        }
        $actions = $this->get_thread_message_actions($message);
        if ($actions) {
            $this->render_command_message_actions($actions, $thread_id);
        }
        $meta_badges = $this->get_thread_message_meta_badges($meta);
        if ($meta_badges) {
            echo '<div class="lcfa-chip-row">';
            foreach ($meta_badges as $badge) {
                echo '<span class="lcfa-chip">' . esc_html($badge) . '</span>';
            }
            echo '</div>';
        }
        echo '</article>';
    }

    private function get_thread_message_meta_badges(array $meta): array {
        $badges = [];

        if (!empty($meta['processed_by'])) {
            $badges[] = sprintf(__('Processed by: %s', 'livecanvas-forge-ai'), $this->get_provenance_label('processed_by', (string) $meta['processed_by']));
        }

        if (!empty($meta['origin'])) {
            $badges[] = sprintf(__('Origin: %s', 'livecanvas-forge-ai'), $this->get_provenance_label('origin', (string) $meta['origin']));
        }

        if (!empty($meta['agent'])) {
            $badges[] = sprintf(__('Agent: %s', 'livecanvas-forge-ai'), $this->get_provenance_label('agent', (string) $meta['agent']));
        }

        if (!empty($meta['transport'])) {
            $badges[] = sprintf(__('Transport: %s', 'livecanvas-forge-ai'), $this->get_provenance_label('transport', (string) $meta['transport']));
        }

        if (!empty($meta['action'])) {
            $badges[] = sprintf(__('Action: %s', 'livecanvas-forge-ai'), sanitize_key((string) $meta['action']));
        }

        if (!empty($meta['execution_target'])) {
            $badges[] = sprintf(__('Execution: %s', 'livecanvas-forge-ai'), sanitize_key((string) $meta['execution_target']));
        }

        if (!empty($meta['mode'])) {
            $badges[] = sprintf(__('Mode: %s', 'livecanvas-forge-ai'), sanitize_key((string) $meta['mode']));
        }

        if (!empty($meta['genesis_task_id'])) {
            $badges[] = sprintf(__('Genesis task: %s', 'livecanvas-forge-ai'), sanitize_key((string) $meta['genesis_task_id']));
        }

        if (!empty($meta['confidence'])) {
            $badges[] = sprintf(__('Confidence: %s', 'livecanvas-forge-ai'), sanitize_text_field((string) $meta['confidence']));
        }

        if (!empty($meta['target_type'])) {
            $badges[] = sprintf(__('Target: %s', 'livecanvas-forge-ai'), sanitize_key((string) $meta['target_type']));
        }

        if (!empty($meta['target_title'])) {
            $badges[] = sprintf(__('Label: %s', 'livecanvas-forge-ai'), sanitize_text_field((string) $meta['target_title']));
        }

        if (!empty($meta['target_id'])) {
            $badges[] = sprintf(__('ID: %d', 'livecanvas-forge-ai'), absint($meta['target_id']));
        }

        if (!empty($meta['warnings']) && is_array($meta['warnings'])) {
            $badges[] = sprintf(__('Warnings: %d', 'livecanvas-forge-ai'), count($meta['warnings']));
        }

        if (!empty($meta['reasons']) && is_array($meta['reasons'])) {
            $badges[] = sprintf(__('Reasons: %d', 'livecanvas-forge-ai'), count($meta['reasons']));
        }

        return $badges;
    }

    private function get_provenance_label(string $type, string $value): string {
        $value = sanitize_key($value);
        $labels = [
            'processed_by' => [
                'forge_local_rules' => __('AI Bridge local rules', 'livecanvas-forge-ai'),
                'codex_mcp'         => __('Codex via MCP', 'livecanvas-forge-ai'),
                'opencode_mcp'      => __('OpenCode via MCP', 'livecanvas-forge-ai'),
                'claude_mcp'        => __('Claude via MCP', 'livecanvas-forge-ai'),
                'cursor_mcp'        => __('Cursor via MCP', 'livecanvas-forge-ai'),
                'generic_mcp'       => __('Generic MCP client', 'livecanvas-forge-ai'),
                'remote_companion'  => __('Remote companion', 'livecanvas-forge-ai'),
            ],
            'origin' => [
                'frontend_bridge'    => __('Frontend bridge', 'livecanvas-forge-ai'),
                'admin_command_deck' => __('Command Deck', 'livecanvas-forge-ai'),
                'mcp_agent'          => __('MCP agent', 'livecanvas-forge-ai'),
                'remote_companion'   => __('Remote companion', 'livecanvas-forge-ai'),
                'api'                => __('API', 'livecanvas-forge-ai'),
            ],
            'agent' => [
                'forge'    => __('AI Bridge', 'livecanvas-forge-ai'),
                'codex'    => __('Codex', 'livecanvas-forge-ai'),
                'opencode' => __('OpenCode', 'livecanvas-forge-ai'),
                'claude'   => __('Claude', 'livecanvas-forge-ai'),
                'cursor'   => __('Cursor', 'livecanvas-forge-ai'),
                'generic'  => __('Generic', 'livecanvas-forge-ai'),
            ],
            'transport' => [
                'browser_rest' => __('Browser REST', 'livecanvas-forge-ai'),
                'mcp_stdio'    => __('MCP stdio', 'livecanvas-forge-ai'),
                'mcp_bridge'   => __('MCP bridge', 'livecanvas-forge-ai'),
                'remote_rest'  => __('Remote REST', 'livecanvas-forge-ai'),
                'api'          => __('API', 'livecanvas-forge-ai'),
            ],
        ];

        return (string) ($labels[$type][$value] ?? $value);
    }

    private function render_command_thread_apply_form(array $action, string $thread_id): void {
        $payload = $this->prepare_command_thread_action_payload((array) ($action['payload'] ?? []));
        $request_context = $this->get_current_command_request_context();
        $context_post_id = absint($request_context['context_post_id'] ?? 0);
        $genesis_task_id = sanitize_key((string) ($request_context['genesis_task_id'] ?? ''));
        $user_prompt = sanitize_textarea_field((string) ($request_context['user_prompt'] ?? ''));

        if (empty($payload['action'])) {
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form lcfa-inline-form--compact">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('lcfa_command');
        } else {
            echo '<input type="hidden" name="_wpnonce" value="">';
        }
        echo '<input type="hidden" name="action" value="lcfa_command">';
        echo '<input type="hidden" name="thread_id" value="' . esc_attr($thread_id) . '">';
        echo '<input type="hidden" name="command_payload_json" value="' . esc_attr(wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">';
        if ($context_post_id > 0) {
            echo '<input type="hidden" name="context_post_id" value="' . esc_attr((string) $context_post_id) . '">';
        }
        if ($genesis_task_id !== '') {
            echo '<input type="hidden" name="genesis_task_id" value="' . esc_attr($genesis_task_id) . '">';
        }
        if ($user_prompt !== '') {
            echo '<input type="hidden" name="user_prompt" value="' . esc_attr($user_prompt) . '">';
        }
        echo '<button class="button button-small" type="submit">' . esc_html((string) ($action['label'] ?? __('Apply', 'livecanvas-forge-ai'))) . '</button>';
        echo '</form>';
    }

    private function render_command_message_actions(array $actions, string $thread_id): void {
        if (!$actions) {
            return;
        }

        echo '<div class="lcfa-target-actions">';
        foreach ($actions as $action) {
            if (($action['kind'] ?? 'url') === 'apply' && !empty($action['payload'])) {
                $this->render_command_thread_apply_form((array) $action, $thread_id);
                continue;
            }

            echo '<a class="button button-small" href="' . esc_url((string) ($action['url'] ?? '')) . '" target="_blank" rel="noreferrer noopener">' . esc_html((string) ($action['label'] ?? '')) . '</a>';
        }
        echo '</div>';
    }

    private function build_thread_result_message(array $result, array $payload): array {
        $lines = [];
        $summary = trim((string) ($result['summary'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));
        $execution_target = sanitize_key((string) ($payload['execution_target'] ?? 'local'));
        $target_type = (string) ($result['target_type'] ?? '');
        $provenance = $this->get_payload_provenance(is_array($result['provenance'] ?? null) ? (array) $result['provenance'] : $payload, 'admin_command_deck', 'forge_local_rules');

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
            $lines[] = sprintf(__('Restored from backup: %s', 'livecanvas-forge-ai'), (string) $result['data']['restored_from_backup']['backup_id']);
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
                'command_url_builder' => [$this, 'get_command_url'],
            ]),
        ];

        return LCFA_Thread_Message_Actions::decorate_message($message, [
            'thread_id' => LCFA_Settings::normalize_thread_id((string) ($payload['thread_id'] ?? '')),
            'command_url_builder' => [$this, 'get_command_url'],
        ]);
    }

    private function get_thread_message_actions(array $message): array {
        $decorated = LCFA_Thread_Message_Actions::decorate_message($message, [
            'thread_id' => LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default')),
            'command_url_builder' => [$this, 'get_command_url'],
        ]);

        return LCFA_Thread_Message_Actions::sanitize_actions((array) ($decorated['actions'] ?? []));
    }

    private function prepare_command_thread_action_payload(array $payload): array {
        $prepared = $payload;

        if (empty($prepared['context_post_id']) && !empty($_GET['post_id'])) {
            $prepared['context_post_id'] = absint($_GET['post_id']);
        }

        if (empty($prepared['genesis_task_id']) && !empty($_GET['genesis_task_id'])) {
            $prepared['genesis_task_id'] = sanitize_key((string) $_GET['genesis_task_id']);
        }

        return $prepared;
    }

    private function get_current_command_request_context(): array {
        $context = [];
        $post_id = absint($_GET['post_id'] ?? 0);
        $genesis_task_id = sanitize_key((string) ($_GET['genesis_task_id'] ?? ''));
        $user_prompt = sanitize_textarea_field((string) ($_GET['user_prompt'] ?? ''));

        if ($post_id > 0) {
            $context['post_id'] = $post_id;
            $context['context_post_id'] = $post_id;
        }

        if ($genesis_task_id !== '') {
            $context['genesis_task_id'] = $genesis_task_id;
        }

        if ($user_prompt !== '') {
            $context['user_prompt'] = $user_prompt;
        }

        return $context;
    }

    private function render_command_result(?array $result): void {
        if (!$result) {
            return;
        }

        $is_success = !empty($result['ok']);
        $status_icon = $is_success ? 'check-circle' : 'x-circle';
        $status_label = __('Support details', 'livecanvas-forge-ai');

        echo '<section class="lcfa-card lcfa-command-result' . ($is_success ? ' is-success' : ' is-error') . '">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg($status_icon);
        echo '<div><h2>' . esc_html($status_label) . '</h2><p>' . esc_html($result['summary'] ?: $result['message']) . '</p></div>';
        echo '</div>';
        echo '<p class="lcfa-result-message">' . esc_html__('The thread above is the primary execution log. Use this panel for structured payloads, diffs, and supporting inspection details.', 'livecanvas-forge-ai') . '</p>';

        if (!empty($result['message']) && $result['message'] !== ($result['summary'] ?? '')) {
            echo '<p class="lcfa-result-message">' . esc_html($result['message']) . '</p>';
        }

        if (!empty($result['inventory']) && is_array($result['inventory'])) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="inventory">';
            echo '<summary>' . esc_html__('Audit inventory', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Audit inventory', 'livecanvas-forge-ai') . '</h3>';
            $this->render_inventory_panel($result['inventory']);
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if (!empty($result['data']) && is_array($result['data'])) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="payload">';
            echo '<summary>' . esc_html__('Structured payload', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-result-panel">';
            echo '<h3>' . esc_html__('Structured payload', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block(wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
                'language'   => 'json',
                'label'      => __('JSON', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy payload', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if (!empty($result['diff_html']) || $result['existing_html'] !== '' || $result['proposed_html'] !== '') {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="preview">';
            echo '<summary>' . esc_html__('Markup preview', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-result-grid">';

            if (!empty($result['diff_html'])) {
                echo '<div class="lcfa-result-panel lcfa-diff-panel">';
                echo '<h3>' . esc_html__('Diff preview', 'livecanvas-forge-ai') . '</h3>';
                echo '<div class="lcfa-diff">' . $result['diff_html'] . '</div>';
                echo '</div>';
            }

            if ($result['existing_html'] !== '') {
                echo '<div class="lcfa-result-panel">';
                echo '<h3>' . esc_html__('Current content', 'livecanvas-forge-ai') . '</h3>';
                $this->render_code_block($result['existing_html'], [
                    'language'   => 'markup',
                    'label'      => __('HTML', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy current HTML', 'livecanvas-forge-ai'),
                ]);
                echo '</div>';
            }

            if ($result['proposed_html'] !== '') {
                echo '<div class="lcfa-result-panel">';
                echo '<h3>' . esc_html__('Proposed content', 'livecanvas-forge-ai') . '</h3>';
                $this->render_code_block($result['proposed_html'], [
                    'language'   => 'markup',
                    'label'      => __('HTML', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy proposed HTML', 'livecanvas-forge-ai'),
                ]);
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        echo '</section>';
    }

    private function render_command_suggestion(?array $suggestion): void {
        if (!$suggestion) {
            return;
        }

        $is_success = !empty($suggestion['ok']);
        $status_icon = $is_success ? 'stars' : 'x-circle';
        $status_label = __('Support details', 'livecanvas-forge-ai');
        $payload = is_array($suggestion['suggested_payload'] ?? null) ? $suggestion['suggested_payload'] : [];
        $preflight = is_array($suggestion['preflight'] ?? null) ? $suggestion['preflight'] : [];
        $workflow = is_array($suggestion['workflow'] ?? null) ? $suggestion['workflow'] : [];
        $reasons = is_array($suggestion['reasons'] ?? null) ? $suggestion['reasons'] : [];
        $warnings = is_array($suggestion['warnings'] ?? null) ? $suggestion['warnings'] : [];

        echo '<section class="lcfa-card lcfa-command-result' . ($is_success ? ' is-success' : ' is-error') . '">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg($status_icon);
        echo '<div><h2>' . esc_html($status_label) . '</h2><p>' . esc_html((string) ($suggestion['summary'] ?? $suggestion['message'] ?? '')) . '</p></div>';
        echo '</div>';
        echo '<p class="lcfa-result-message">' . esc_html__('The thread above is the primary suggestion log. Use this panel for reasoning, warnings, workflow, and payload inspection.', 'livecanvas-forge-ai') . '</p>';

        if (!empty($payload['action'])) {
            $thread_id = LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default'));
            $suggestion_actions = LCFA_Thread_Message_Actions::build_suggestion_actions($payload, $this->get_current_command_request_context(), [
                'thread_id'           => $thread_id,
                'command_url_builder' => [$this, 'get_command_url'],
            ]);

            $this->render_command_message_actions($suggestion_actions, $thread_id);
        }

        if ($reasons) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="reasons">';
            echo '<summary>' . esc_html__('Why this was suggested', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Why this was suggested', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-bullets">';
            foreach ($reasons as $reason) {
                echo '<li>' . esc_html((string) $reason) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if ($warnings) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="warnings">';
            echo '<summary>' . esc_html__('Warnings', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Warnings', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-bullets">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if ($workflow) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="workflow">';
            echo '<summary>' . esc_html__('Recommended workflow', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Recommended workflow', 'livecanvas-forge-ai') . '</h3>';
            echo '<ol class="lcfa-bullets">';
            foreach ($workflow as $step) {
                $action = (string) ($step['action'] ?? '');
                $phase = (string) ($step['phase'] ?? '');
                $execution_target = (string) ($step['execution_target'] ?? '');
                $parts = array_values(array_filter([
                    $phase !== '' ? ucfirst($phase) : '',
                    $action !== '' ? $action : '',
                    $execution_target !== '' ? sprintf(__('execution %s', 'livecanvas-forge-ai'), $execution_target) : '',
                ]));
                echo '<li>' . esc_html(implode(' · ', $parts)) . '</li>';
            }
            echo '</ol>';
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if ($preflight) {
            echo '<details class="lcfa-command-details" data-lcfa-command-details="preflight">';
            echo '<summary>' . esc_html__('Recommended preflight payload', 'livecanvas-forge-ai') . '</summary>';
            echo '<div class="lcfa-command-details__body">';
            echo '<div class="lcfa-result-panel">';
            echo '<h3>' . esc_html__('Recommended preflight payload', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block(wp_json_encode($preflight, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
                'language'   => 'json',
                'label'      => __('JSON', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy preflight', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }

        if ($payload) {
            echo '<div class="lcfa-result-panel">';
            echo '<h3>' . esc_html__('Suggested payload', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
                'language'   => 'json',
                'label'      => __('JSON', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy payload', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        echo '</section>';
    }

    private function render_connection_test_result(?array $result): void {
        if (!$result) {
            return;
        }

        $is_success = !empty($result['ok']);
        $status_icon = $is_success ? 'check-circle' : 'x-circle';
        $status_label = $is_success ? __('Connection checks', 'livecanvas-forge-ai') : __('Connection issues', 'livecanvas-forge-ai');
        $checks = is_array($result['checks'] ?? null) ? $result['checks'] : [];

        echo '<section class="lcfa-card lcfa-command-result' . ($is_success ? ' is-success' : ' is-error') . '">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg($status_icon);
        echo '<div><h2>' . esc_html($status_label) . '</h2><p>' . esc_html((string) ($result['summary'] ?? '')) . '</p></div>';
        echo '</div>';

        if (!empty($result['checked_at'])) {
            echo '<p class="lcfa-result-message">' . esc_html(sprintf(
                __('Checked at %s.', 'livecanvas-forge-ai'),
                get_date_from_gmt((string) $result['checked_at'], get_option('date_format') . ' ' . get_option('time_format'))
            )) . '</p>';
        }

        if (!$checks) {
            echo '</section>';
            return;
        }

        echo '<div class="lcfa-history-list">';
        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }

            $chip_class = !empty($check['ok']) ? ' is-positive' : (!empty($check['skipped']) ? '' : ' is-negative');
            $chip_label = !empty($check['ok'])
                ? __('OK', 'livecanvas-forge-ai')
                : (!empty($check['skipped']) ? __('Skipped', 'livecanvas-forge-ai') : __('Error', 'livecanvas-forge-ai'));

            echo '<div class="lcfa-history-item">';
            echo '<div class="lcfa-history-copy">';
            echo '<strong>' . esc_html((string) ($check['label'] ?? __('Connection test', 'livecanvas-forge-ai'))) . '</strong>';
            echo '<span>' . esc_html((string) ($check['message'] ?? '')) . '</span>';
            echo '</div>';
            echo '<div class="lcfa-chip-row">';
            echo '<span class="lcfa-chip' . esc_attr($chip_class) . '">' . esc_html($chip_label) . '</span>';
            if (!empty($check['details']['status_code'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('HTTP %d', 'livecanvas-forge-ai'), (int) $check['details']['status_code'])) . '</span>';
            }
            if (!empty($check['details']['node_version'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $check['details']['node_version']) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="lcfa-result-panel">';
        echo '<h3>' . esc_html__('Structured payload', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
            'language'   => 'json',
            'label'      => __('JSON', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy payload', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';
        echo '</section>';
    }

    private function render_code_block(string $code, array $options = []): void {
        $language = sanitize_html_class((string) ($options['language'] ?? 'bash'));
        if ($language === '') {
            $language = 'bash';
        }

        $label = (string) ($options['label'] ?? $this->get_code_block_label($language));
        $copy_text = (string) ($options['copy_text'] ?? $code);
        $copy_label = (string) ($options['copy_label'] ?? __('Copy', 'livecanvas-forge-ai'));
        $copied_label = (string) ($options['copied_label'] ?? __('Copied', 'livecanvas-forge-ai'));

        echo '<div class="lcfa-code-block" data-lcfa-code-language="' . esc_attr($language) . '">';
        echo '<div class="lcfa-code-block__head">';
        echo '<span class="lcfa-code-block__label">' . esc_html($label) . '</span>';
        if ($copy_text !== '') {
            echo '<button class="lcfa-code-block__copy" type="button" data-lcfa-copy-text="' . esc_attr($copy_text) . '" data-lcfa-copy-label="' . esc_attr($copy_label) . '" data-lcfa-copied-label="' . esc_attr($copied_label) . '">' . esc_html($copy_label) . '</button>';
        }
        echo '</div>';
        echo '<pre class="language-' . esc_attr($language) . '"><code class="language-' . esc_attr($language) . '">' . esc_html($code) . '</code></pre>';
        echo '</div>';
    }

    private function get_code_block_label(string $language): string {
        switch ($language) {
            case 'json':
                return __('JSON', 'livecanvas-forge-ai');
            case 'markup':
                return __('HTML', 'livecanvas-forge-ai');
            case 'bash':
            default:
                return __('Shell', 'livecanvas-forge-ai');
        }
    }

    private function render_inventory_panel(array $inventory): void {
        $summary = $inventory['summary'] ?? [];
        $summary_items = [
            'pages'             => __('Pages', 'livecanvas-forge-ai'),
            'headers'           => __('Headers', 'livecanvas-forge-ai'),
            'footers'           => __('Footers', 'livecanvas-forge-ai'),
            'dynamic_templates' => __('Dynamic templates', 'livecanvas-forge-ai'),
            'blocks'            => __('Blocks', 'livecanvas-forge-ai'),
            'sections'          => __('Sections', 'livecanvas-forge-ai'),
        ];

        echo '<div class="lcfa-summary-grid">';
        foreach ($summary_items as $key => $label) {
            echo '<div class="lcfa-summary-tile">';
            echo '<span>' . esc_html($label) . '</span>';
            echo '<strong>' . esc_html((string) ($summary[$key] ?? 0)) . '</strong>';
            echo '</div>';
        }
        echo '</div>';

        $groups = [
            'livecanvas_pages'  => __('LiveCanvas pages', 'livecanvas-forge-ai'),
            'header_partials'   => __('Header partials', 'livecanvas-forge-ai'),
            'footer_partials'   => __('Footer partials', 'livecanvas-forge-ai'),
            'other_partials'    => __('Other partials', 'livecanvas-forge-ai'),
            'dynamic_templates' => __('Dynamic templates', 'livecanvas-forge-ai'),
            'blocks'            => __('Blocks', 'livecanvas-forge-ai'),
            'sections'          => __('Sections', 'livecanvas-forge-ai'),
        ];

        echo '<div class="lcfa-inventory-groups">';
        foreach ($groups as $key => $label) {
            $items = isset($inventory[$key]) && is_array($inventory[$key]) ? $inventory[$key] : [];
            $this->render_inventory_group($key, $label, $items);
        }
        echo '</div>';

        $custom_post_types = isset($inventory['custom_post_types']) && is_array($inventory['custom_post_types']) ? $inventory['custom_post_types'] : [];

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Custom post types', 'livecanvas-forge-ai') . '</h3>';
        if (!$custom_post_types) {
            echo '<p class="lcfa-empty">' . esc_html__('No custom post types detected beyond WordPress core and LiveCanvas internals.', 'livecanvas-forge-ai') . '</p>';
        } else {
            echo '<div class="lcfa-chip-row">';
            foreach ($custom_post_types as $post_type) {
                $label = $post_type['label'] ?: $post_type['name'];
                $flags = [];
                if (!empty($post_type['has_archive'])) {
                    $flags[] = __('archive', 'livecanvas-forge-ai');
                }
                if (!empty($post_type['show_in_rest'])) {
                    $flags[] = __('rest', 'livecanvas-forge-ai');
                }

                echo '<span class="lcfa-chip">';
                echo esc_html($label . ' (' . $post_type['name'] . ')');
                if ($flags) {
                    echo ' · ' . esc_html(implode(', ', $flags));
                }
                echo '</span>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function render_theme_files_panel(?array $roots, ?array $templates): void {
        if (!empty($roots['error'])) {
            echo '<p class="lcfa-empty">' . esc_html((string) $roots['error']) . '</p>';
            return;
        }

        if (!is_array($roots) || empty($roots['roots'])) {
            echo '<p class="lcfa-empty">' . esc_html__('Theme roots are not available for this site.', 'livecanvas-forge-ai') . '</p>';
            return;
        }

        echo '<div class="lcfa-chip-row">';
        foreach ((array) $roots['roots'] as $root) {
            if (!is_array($root)) {
                continue;
            }

            echo '<span class="lcfa-chip">';
            echo esc_html(sprintf('%1$s: %2$s', strtoupper((string) ($root['key'] ?? 'theme')), (string) ($root['label'] ?? '')));
            echo '</span>';
        }
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Writable fallback layer', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html__('Use these files when LiveCanvas dynamic tags or shortcodes are not enough and the companion needs a real Twig, Latte, PHP, CSS, or JS fallback.', 'livecanvas-forge-ai') . '</p>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Quick actions', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-cta-row">';
        echo '<a class="button button-primary" href="' . esc_url($this->get_command_url(['suggest_action' => 'theme_files_audit'])) . '">' . esc_html__('Inspect files', 'livecanvas-forge-ai') . '</a>';
        echo '<a class="button" href="' . esc_url($this->get_command_url(['suggest_action' => 'theme_backups_audit'])) . '">' . esc_html__('Inspect backups', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';
        echo '</div>';

        $items = is_array($templates['files'] ?? null) ? $templates['files'] : [];
        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Templates', 'livecanvas-forge-ai') . '</h3>';
        if (!$items) {
            echo '<p class="lcfa-empty">' . esc_html__('No template files were detected in the common theme template directories.', 'livecanvas-forge-ai') . '</p>';
        } else {
            echo '<ul class="lcfa-target-list">';
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $forge_url = $this->get_command_url([
                    'suggest_action' => 'write_theme_template',
                    'file_path'      => (string) ($item['relative_path'] ?? ''),
                    'root_scope'     => (string) ($item['root'] ?? 'active'),
                ]);

                echo '<li class="lcfa-target-item">';
                echo '<div class="lcfa-target-copy">';
                echo '<strong>' . esc_html((string) ($item['relative_path'] ?? '')) . '</strong>';
                echo '<span>' . esc_html(sprintf('%1$s · %2$s', (string) ($item['theme'] ?? ''), (string) ($item['kind'] ?? 'template'))) . '</span>';
                echo '</div>';
                echo '<div class="lcfa-target-actions">';
                echo '<a class="button button-small button-primary" href="' . esc_url($forge_url) . '">' . esc_html__('AI Bridge', 'livecanvas-forge-ai') . '</a>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        try {
            $backups = $this->theme_files_bridge->list_backups([
                'limit' => 6,
            ]);
        } catch (Throwable $throwable) {
            $backups = [
                'error' => $throwable->getMessage(),
            ];
        }

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Recent backups', 'livecanvas-forge-ai') . '</h3>';
        echo '<p>' . esc_html__('Every overwrite in the fallback layer creates a backup. Use restore to rehydrate a previous version with the same preview/apply flow as any other command.', 'livecanvas-forge-ai') . '</p>';

        if (!empty($backups['error'])) {
            echo '<p class="lcfa-empty">' . esc_html((string) $backups['error']) . '</p>';
            echo '</div>';

            return;
        }

        $backup_items = is_array($backups['backups'] ?? null) ? $backups['backups'] : [];

        if (!$backup_items) {
            echo '<p class="lcfa-empty">' . esc_html__('No backups have been captured yet.', 'livecanvas-forge-ai') . '</p>';
            echo '</div>';

            return;
        }

        echo '<ul class="lcfa-target-list">';
        foreach ($backup_items as $backup_item) {
            if (!is_array($backup_item)) {
                continue;
            }

            $created_at = !empty($backup_item['created_at'])
                ? get_date_from_gmt((string) $backup_item['created_at'], get_option('date_format') . ' ' . get_option('time_format'))
                : '';
            $restore_url = $this->get_command_url([
                'suggest_action' => 'restore_theme_backup',
                'backup_id'      => (string) ($backup_item['backup_id'] ?? ''),
                'file_path'      => (string) ($backup_item['relative_path'] ?? ''),
                'root_scope'     => (string) ($backup_item['root'] ?? 'stylesheet'),
            ]);

            echo '<li class="lcfa-target-item">';
            echo '<div class="lcfa-target-copy">';
            echo '<strong>' . esc_html((string) ($backup_item['relative_path'] ?? (string) ($backup_item['backup_id'] ?? ''))) . '</strong>';
            echo '<span>' . esc_html(implode(' · ', array_filter([
                (string) ($backup_item['theme'] ?? ''),
                (string) ($backup_item['root'] ?? ''),
                $created_at,
            ]))) . '</span>';
            echo '</div>';
            echo '<div class="lcfa-target-actions">';
            echo '<a class="button button-small" href="' . esc_url($restore_url) . '">' . esc_html__('Restore', 'livecanvas-forge-ai') . '</a>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function render_windpress_panel(array $windpress, array $local_bridge): void {
        $providers       = is_array($windpress['providers'] ?? null) ? $windpress['providers'] : [];
        $handlers        = is_array($windpress['volume_handlers'] ?? null) ? $windpress['volume_handlers'] : [];
        $css_cache       = is_array($windpress['cache']['css'] ?? null) ? $windpress['cache']['css'] : [];
        $theme_json_cache= is_array($windpress['cache']['theme_json'] ?? null) ? $windpress['cache']['theme_json'] : [];
        $default_ids     = $this->get_default_windpress_provider_ids($providers);
        $reset_entries   = ['main.css', 'tailwind.config.js', 'wizard.css', 'wizard.js'];

        if (empty($windpress['available'])) {
            echo '<p class="lcfa-empty">' . esc_html__('WindPress is not active on this site. This panel becomes operational when Picowind + WindPress are running.', 'livecanvas-forge-ai') . '</p>';

            if (!empty($local_bridge['message'])) {
                echo '<div class="lcfa-guide">';
                echo '<h3>' . esc_html__('Local bridge status', 'livecanvas-forge-ai') . '</h3>';
                echo '<p>' . esc_html((string) $local_bridge['message']) . '</p>';
                echo '</div>';
            }

            return;
        }

        echo '<div class="lcfa-summary-grid">';
        $this->render_summary_tile(__('Providers', 'livecanvas-forge-ai'), (string) count($providers));
        $this->render_summary_tile(__('Handlers', 'livecanvas-forge-ai'), (string) count($handlers));
        $this->render_summary_tile(__('CSS cache', 'livecanvas-forge-ai'), $this->format_file_size_label((int) ($css_cache['bytes'] ?? 0)));
        $this->render_summary_tile(__('theme.json', 'livecanvas-forge-ai'), $this->format_file_size_label((int) ($theme_json_cache['bytes'] ?? 0)));
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Quick actions', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-cta-row">';
        echo '<a class="button button-primary" href="' . esc_url($this->get_command_url(['suggest_action' => 'windpress_audit'])) . '">' . esc_html__('Run audit', 'livecanvas-forge-ai') . '</a>';

        if (!empty($local_bridge['build_available'])) {
            echo '<a class="button" href="' . esc_url($this->get_command_url([
                'suggest_action' => 'build_windpress_cache',
                'provider_id'    => $default_ids,
            ])) . '">' . esc_html__('Build local cache', 'livecanvas-forge-ai') . '</a>';
        }

        echo '<a class="button" href="' . esc_url($this->get_command_url(['suggest_action' => 'windpress_flush_cache'])) . '">' . esc_html__('Flush cache', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';

        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . (!empty($windpress['active']) ? ' is-positive' : '') . '">' . esc_html(!empty($windpress['active']) ? __('Active', 'livecanvas-forge-ai') : __('Inactive', 'livecanvas-forge-ai')) . '</span>';
        if (!empty($windpress['tailwind_version'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Tailwind v%d', 'livecanvas-forge-ai'), (int) $windpress['tailwind_version'])) . '</span>';
        }
        if (!empty($windpress['performance_mode'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Mode: %s', 'livecanvas-forge-ai'), (string) $windpress['performance_mode'])) . '</span>';
        }
        if (!empty($local_bridge['node_version'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Node: %s', 'livecanvas-forge-ai'), (string) $local_bridge['node_version'])) . '</span>';
        }
        echo '</div>';
        echo '</div>';

        if (!empty($local_bridge['message'])) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Local build runtime', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html((string) $local_bridge['message']) . '</p>';
            echo '</div>';
        }

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Providers', 'livecanvas-forge-ai') . '</h3>';
        if (!$providers) {
            echo '<p class="lcfa-empty">' . esc_html__('No WindPress providers were returned by the runtime.', 'livecanvas-forge-ai') . '</p>';
        } else {
            echo '<ul class="lcfa-target-list">';
            foreach ($providers as $provider) {
                if (!is_array($provider)) {
                    continue;
                }

                $provider_id = (string) ($provider['id'] ?? '');
                $meta = array_filter([
                    (string) ($provider['type'] ?? ''),
                    !empty($provider['enabled']) ? __('enabled', 'livecanvas-forge-ai') : __('disabled', 'livecanvas-forge-ai'),
                    array_key_exists('installed', $provider) && $provider['installed'] !== null
                        ? (!empty($provider['installed']) ? __('installed', 'livecanvas-forge-ai') : __('missing', 'livecanvas-forge-ai'))
                        : '',
                ]);

                echo '<li class="lcfa-target-item">';
                echo '<div class="lcfa-target-copy">';
                echo '<strong>' . esc_html((string) ($provider['name'] ?? $provider_id)) . '</strong>';
                echo '<span>' . esc_html(implode(' · ', $meta)) . '</span>';
                echo '</div>';
                echo '<div class="lcfa-target-actions">';
                echo '<a class="button button-small" href="' . esc_url($this->get_command_url([
                    'suggest_action' => 'windpress_scan_provider',
                    'provider_id'    => $provider_id,
                ])) . '">' . esc_html__('Scan', 'livecanvas-forge-ai') . '</a>';

                if (!empty($local_bridge['build_available'])) {
                    echo '<a class="button button-small button-primary" href="' . esc_url($this->get_command_url([
                        'suggest_action' => 'build_windpress_cache',
                        'provider_id'    => $provider_id,
                    ])) . '">' . esc_html__('Build', 'livecanvas-forge-ai') . '</a>';
                }
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Internal entries', 'livecanvas-forge-ai') . '</h3>';
        echo '<ul class="lcfa-target-list">';
        foreach ($reset_entries as $entry) {
            echo '<li class="lcfa-target-item">';
            echo '<div class="lcfa-target-copy">';
            echo '<strong>' . esc_html($entry) . '</strong>';
            echo '<span>' . esc_html__('Reset one internal WindPress cache entry.', 'livecanvas-forge-ai') . '</span>';
            echo '</div>';
            echo '<div class="lcfa-target-actions">';
            echo '<a class="button button-small" href="' . esc_url($this->get_command_url([
                'suggest_action' => 'windpress_reset_entry',
                'relative_path'  => $entry,
            ])) . '">' . esc_html__('Reset', 'livecanvas-forge-ai') . '</a>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function render_inventory_group(string $group_key, string $label, array $items): void {
        echo '<div class="lcfa-inventory-group">';
        echo '<h3>' . esc_html($label) . '</h3>';

        if (!$items) {
            echo '<p class="lcfa-empty">' . esc_html__('Nothing detected yet for this target type.', 'livecanvas-forge-ai') . '</p>';
            echo '</div>';

            return;
        }

        $visible_items = array_slice($items, 0, 12);

        echo '<ul class="lcfa-target-list">';
        foreach ($visible_items as $item) {
            $meta_parts = [
                '#' . (int) $item['id'],
                $item['status'],
            ];

            if (!empty($item['slug'])) {
                $meta_parts[] = $item['slug'];
            }

            echo '<li class="lcfa-target-item">';
            echo '<div class="lcfa-target-copy">';
            echo '<strong>' . esc_html($item['title']) . '</strong>';
            echo '<span>' . esc_html(implode(' · ', array_filter($meta_parts))) . '</span>';
            echo '</div>';
            echo '<div class="lcfa-target-actions">';
            $forge_url = $this->get_inventory_item_command_url($group_key, $item);
            if ($forge_url) {
                echo '<a class="button button-small button-primary" href="' . esc_url($forge_url) . '">' . esc_html__('AI Bridge', 'livecanvas-forge-ai') . '</a>';
            }
            if (!empty($item['edit_url'])) {
                echo '<a class="button button-small" href="' . esc_url($item['edit_url']) . '">' . esc_html__('Edit', 'livecanvas-forge-ai') . '</a>';
            }
            if (!empty($item['view_url'])) {
                echo '<a class="button button-small" href="' . esc_url($item['view_url']) . '" target="_blank" rel="noreferrer noopener">' . esc_html__('View', 'livecanvas-forge-ai') . '</a>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        if (count($items) > count($visible_items)) {
            echo '<p class="lcfa-empty">' . esc_html(sprintf(__('Showing %1$d of %2$d entries.', 'livecanvas-forge-ai'), count($visible_items), count($items))) . '</p>';
        }

        echo '</div>';
    }

    private function render_summary_tile(string $label, string $value): void {
        echo '<div class="lcfa-summary-tile">';
        echo '<span>' . esc_html($label) . '</span>';
        echo '<strong>' . esc_html($value) . '</strong>';
        echo '</div>';
    }

    private function render_history_panel(array $history): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('activity');
        echo '<div><h2>' . esc_html__('Recent runs', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The last preview or apply operations executed through the companion.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        if (!$history) {
            echo '<p class="lcfa-empty">' . esc_html__('No command history yet.', 'livecanvas-forge-ai') . '</p>';
            echo '</section>';

            return;
        }

        echo '<div class="lcfa-history-list">';
        foreach (array_slice($history, 0, 10) as $entry) {
            $time = !empty($entry['time'])
                ? get_date_from_gmt($entry['time'], get_option('date_format') . ' ' . get_option('time_format'))
                : '';

            echo '<div class="lcfa-history-item">';
            echo '<div class="lcfa-history-copy">';
            echo '<strong>' . esc_html($entry['summary'] ?: ($entry['message'] ?? __('Unnamed operation', 'livecanvas-forge-ai'))) . '</strong>';
            if ($time !== '') {
                echo '<span>' . esc_html($time) . '</span>';
            }
            echo '</div>';
            echo '<div class="lcfa-chip-row">';
            if (!empty($entry['action'])) {
                echo '<span class="lcfa-chip">' . esc_html($entry['action']) . '</span>';
            }
            if (!empty($entry['mode'])) {
                echo '<span class="lcfa-chip">' . esc_html($entry['mode']) . '</span>';
            }
            if (!empty($entry['execution_target'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['execution_target']) . '</span>';
            }
            echo '<span class="lcfa-chip' . (!empty($entry['ok']) ? ' is-positive' : ' is-negative') . '">' . esc_html(!empty($entry['ok']) ? __('OK', 'livecanvas-forge-ai') : __('Error', 'livecanvas-forge-ai')) . '</span>';
            if (!empty($entry['target_id'])) {
                echo '<span class="lcfa-chip">' . esc_html(sprintf(__('#%d', 'livecanvas-forge-ai'), (int) $entry['target_id'])) . '</span>';
            }
            if (!empty($entry['audit_id'])) {
                echo '<span class="lcfa-chip">' . esc_html((string) $entry['audit_id']) . '</span>';
            }
            if (!empty($entry['rollback_available'])) {
                echo '<span class="lcfa-chip is-positive">' . esc_html__('Rollback ready', 'livecanvas-forge-ai') . '</span>';
                if (($entry['execution_target'] ?? 'local') === 'local' && !empty($entry['audit_id'])) {
                    echo '<a class="button button-small" href="' . esc_url($this->get_command_url([
                        'suggest_action' => 'restore_audit_rollback',
                        'audit_id'       => (string) $entry['audit_id'],
                    ])) . '">' . esc_html__('Restore', 'livecanvas-forge-ai') . '</a>';
                }
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</section>';
    }

    private function get_tool_guides(string $site_mode): array {
        $remote_suffix = $site_mode === 'remote' || $site_mode === 'hybrid'
            ? __('Use a dedicated WordPress user with an Application Password and the minimum viable permissions.', 'livecanvas-forge-ai')
            : __('Use the local companion bridge first, then add MCP only where it provides a real benefit.', 'livecanvas-forge-ai');

        return [
            'codex' => [
                'summary' => __('Codex is the first-class client for the initial integration. The plugin will expose a local or REST bridge first, with MCP kept optional.', 'livecanvas-forge-ai'),
                'steps'   => [
                    __('Open the WordPress workspace in Codex.', 'livecanvas-forge-ai'),
                    __('Point the client to the companion bridge or to the REST surface when it becomes available.', 'livecanvas-forge-ai'),
                    $remote_suffix,
                ],
            ],
            'opencode' => [
                'summary' => __('OpenCode should use the same operational contract as the companion: context, plan, diff, preview, and controlled apply.', 'livecanvas-forge-ai'),
                'steps'   => [
                    __('Configure the target endpoint and credentials for the selected WordPress instance.', 'livecanvas-forge-ai'),
                    __('Keep preview and confirmation enabled for sensitive writes.', 'livecanvas-forge-ai'),
                    $remote_suffix,
                ],
            ],
            'claude' => [
                'summary' => __('Claude can talk through the same MCP or REST bridge and should follow the same permission matrix.', 'livecanvas-forge-ai'),
                'steps'   => [
                    __('Configure the companion MCP server when available.', 'livecanvas-forge-ai'),
                    __('If MCP is not in place yet, use REST plus Application Passwords.', 'livecanvas-forge-ai'),
                    $remote_suffix,
                ],
            ],
            'cursor' => [
                'summary' => __('Cursor should be treated as an advanced client. The guide should stay explicit about endpoints, auth, diff flow, and confirmation checkpoints.', 'livecanvas-forge-ai'),
                'steps'   => [
                    __('Open the site workspace and connect Cursor to the companion contract.', 'livecanvas-forge-ai'),
                    __('Keep diff-first execution enabled before any apply action.', 'livecanvas-forge-ai'),
                    $remote_suffix,
                ],
            ],
            'other' => [
                'summary' => __('Any client that supports MCP or REST can be attached, as long as it respects the companion contract and permission model.', 'livecanvas-forge-ai'),
                'steps'   => [
                    __('Choose the transport first: MCP or REST.', 'livecanvas-forge-ai'),
                    __('Then configure the endpoint, credentials, and operational policy.', 'livecanvas-forge-ai'),
                    $remote_suffix,
                ],
            ],
        ];
    }

    private function get_partner_logo(string $type): string {
        switch ($type) {
            case 'livecanvas':
                $logo = $this->get_plugin_asset_url('livecanvas', 'images/lc-logo.svg');

                if ($logo) {
                    return '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('LiveCanvas', 'livecanvas-forge-ai') . '" class="lcfa-logo lcfa-logo-livecanvas">';
                }

                return '<span class="lcfa-fallback-logo">LC</span>';

            case 'livecanvas-micro':
                $logo = $this->get_plugin_asset_url('livecanvas', 'images/lc-micrologo.svg');

                if ($logo) {
                    return '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('LiveCanvas', 'livecanvas-forge-ai') . '" class="lcfa-logo lcfa-logo-livecanvas-micro">';
                }

                return '<span class="lcfa-fallback-logo lcfa-fallback-logo-micro">LC</span>';

            case 'windpress':
                $logo = $this->get_plugin_asset_url('windpress', 'windpress.svg');

                if ($logo) {
                    return '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('WindPress', 'livecanvas-forge-ai') . '" class="lcfa-logo lcfa-logo-windpress">';
                }

                return '<span class="lcfa-fallback-logo">WP</span>';

            case 'bootstrap':
                return '<img src="' . esc_url('https://getbootstrap.com/docs/5.3/assets/brand/bootstrap-logo-shadow@2x.png') . '" alt="' . esc_attr__('Bootstrap', 'livecanvas-forge-ai') . '" class="lcfa-logo lcfa-logo-bootstrap-image">';

            default:
                return '<span class="lcfa-fallback-logo">AI</span>';
        }
    }

    private function get_command_form_context(array $actions): array {
        $defaults = [
            'action'        => 'site_audit',
            'execution_target' => 'local',
            'thread_id'     => 'default',
            'genesis_task_id' => '',
            'context_post_id' => '',
            'target_id'     => '',
            'variant'       => '1',
            'title'         => '',
            'slug'          => '',
            'provider_id'   => '',
            'relative_path' => '',
            'root_scope'    => 'stylesheet',
            'file_path'     => '',
            'backup_id'     => '',
            'audit_id'      => '',
            'status'        => 'draft',
            'user_prompt'   => '',
            'content'       => '',
            'dry_run'       => true,
            'context_label' => '',
        ];

        $requested_action = sanitize_key($_GET['suggest_action'] ?? '');
        if ($requested_action !== '' && isset($actions[$requested_action])) {
            $defaults['action'] = $requested_action;
        }

        $requested_execution_target = sanitize_key($_GET['execution_target'] ?? 'local');
        if (in_array($requested_execution_target, ['local', 'remote'], true)) {
            $defaults['execution_target'] = $requested_execution_target;
        }

        $defaults['thread_id']     = LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default'));
        $defaults['genesis_task_id'] = sanitize_key((string) ($_GET['genesis_task_id'] ?? ''));
        $defaults['context_post_id'] = absint($_GET['post_id'] ?? 0) ?: '';
        $defaults['target_id']     = absint($_GET['target_id'] ?? 0) ?: '';
        $defaults['variant']       = sanitize_text_field($_GET['variant'] ?? '1');
        $defaults['title']         = sanitize_text_field($_GET['title'] ?? '');
        $defaults['slug']          = sanitize_title($_GET['slug'] ?? '');
        $defaults['provider_id']   = sanitize_text_field($_GET['provider_id'] ?? '');
        $defaults['relative_path'] = sanitize_text_field($_GET['relative_path'] ?? '');
        $defaults['root_scope']    = sanitize_key($_GET['root_scope'] ?? 'stylesheet');
        $defaults['file_path']     = sanitize_text_field($_GET['file_path'] ?? '');
        $defaults['backup_id']     = sanitize_text_field($_GET['backup_id'] ?? '');
        $defaults['audit_id']      = sanitize_key((string) ($_GET['audit_id'] ?? ''));
        $defaults['status']        = sanitize_key($_GET['status'] ?? 'draft');
        $defaults['user_prompt']   = sanitize_textarea_field((string) ($_GET['user_prompt'] ?? ''));

        $post_id = absint($_GET['post_id'] ?? 0);
        if ($post_id) {
            $post = get_post($post_id);

            if ($post instanceof WP_Post) {
                $context = $this->get_command_context_for_post($post);
                if (!empty($context['action']) && isset($actions[$context['action']])) {
                    $defaults['action'] = $context['action'];
                }
                if (!empty($context['target_id'])) {
                    $defaults['target_id'] = (string) $context['target_id'];
                }
                if (!empty($context['variant'])) {
                    $defaults['variant'] = (string) $context['variant'];
                }

                $defaults['context_label'] = sprintf(
                    __('Imported from LiveCanvas editor: %1$s (#%2$d, %3$s). Suggested action: %4$s.', 'livecanvas-forge-ai'),
                    get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai'),
                    (int) $post->ID,
                    $post->post_type,
                    $this->get_action_label($defaults['action'])
                );
            }
        }

        if ($defaults['genesis_task_id'] !== '') {
            $plan = LCFA_Settings::get_genesis_plan();
            $tasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];

            foreach ($tasks as $task) {
                if (!is_array($task) || sanitize_key((string) ($task['id'] ?? '')) !== $defaults['genesis_task_id']) {
                    continue;
                }

                if ($defaults['user_prompt'] === '' && !empty($task['user_prompt'])) {
                    $defaults['user_prompt'] = sanitize_textarea_field((string) $task['user_prompt']);
                }

                if (is_array($task['payload'] ?? null)) {
                    $defaults = $this->apply_genesis_task_payload_to_command_context($defaults, (array) $task['payload']);
                }

                if ($defaults['context_label'] === '') {
                    $defaults['context_label'] = sprintf(
                        __('Genesis task loaded: %1$s. Suggested action: %2$s.', 'livecanvas-forge-ai'),
                        (string) ($task['label'] ?? __('Genesis task', 'livecanvas-forge-ai')),
                        $this->get_action_label((string) ($defaults['action'] ?? 'site_audit'))
                    );
                }

                break;
            }
        }

        return $this->hydrate_command_form_context($defaults);
    }

    private function apply_genesis_task_payload_to_command_context(array $context, array $payload): array {
        $scalar_keys = [
            'action',
            'execution_target',
            'target_id',
            'variant',
            'title',
            'slug',
            'provider_id',
            'relative_path',
            'root_scope',
            'file_path',
            'backup_id',
            'audit_id',
            'status',
            'template_target',
            'native_key',
            'specialty',
        ];

        foreach ($scalar_keys as $key) {
            if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
                continue;
            }

            switch ($key) {
                case 'action':
                case 'execution_target':
                case 'root_scope':
                case 'status':
                    $context[$key] = sanitize_key((string) $payload[$key]);
                    break;

                case 'target_id':
                    $context[$key] = absint($payload[$key]) ?: '';
                    break;

                case 'slug':
                    $context[$key] = sanitize_title((string) $payload[$key]);
                    break;

                default:
                    $context[$key] = sanitize_text_field((string) $payload[$key]);
                    break;
            }
        }

        if (!empty($payload['dry_run'])) {
            $context['dry_run'] = true;
        }

        if (!empty($payload['content']) && is_string($payload['content']) && $context['content'] === '') {
            $context['content'] = (string) $payload['content'];
            return $context;
        }

        $structured_payload = [];
        foreach ([
            'header_html',
            'header_html_lines',
            'footer_html',
            'footer_html_lines',
            'body_html',
            'body_html_lines',
            'footer_script',
            'footer_script_lines',
            'pages',
            'design_system',
            'template_assignment',
            'template_target',
            'native_key',
            'specialty',
            'colors',
            'typography',
            'radius',
            'buttons',
            'font_assets',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $structured_payload[$key] = $payload[$key];
            }
        }

        if ($structured_payload && $context['content'] === '') {
            $context['content'] = (string) wp_json_encode($structured_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $context;
    }

    private function hydrate_command_form_context(array $context): array {
        $action = (string) ($context['action'] ?? '');
        $target = [
            'post'    => null,
            'content' => '',
        ];

        switch ($action) {
            case 'page_upsert':
            case 'update_page':
                if (!empty($context['target_id'])) {
                    $target = $this->inventory->get_target_content('page', (int) $context['target_id']);
                }
                break;

            case 'update_dynamic_template':
                if (!empty($context['target_id'])) {
                    $target = $this->inventory->get_target_content('dynamic_template', (int) $context['target_id']);
                }
                break;

            case 'update_partial':
                if (!empty($context['target_id'])) {
                    $target = $this->inventory->get_target_content('partial', (int) $context['target_id']);
                }
                break;

            case 'update_header':
                $target = $this->inventory->get_target_content('header', 0, (string) ($context['variant'] ?? '1'));
                break;

            case 'update_footer':
                $target = $this->inventory->get_target_content('footer', 0, (string) ($context['variant'] ?? '1'));
                break;

            case 'write_theme_template':
                if (!empty($context['file_path'])) {
                    try {
                        $target = $this->theme_files_bridge->read_template_file([
                            'root_scope' => (string) ($context['root_scope'] ?? 'active'),
                            'path'       => (string) $context['file_path'],
                        ]);
                    } catch (Throwable $throwable) {
                        $target = [
                            'content' => '',
                        ];
                    }
                }
                break;

            case 'write_theme_file':
                if (!empty($context['file_path'])) {
                    try {
                        $target = $this->theme_files_bridge->read_file([
                            'root_scope' => (string) ($context['root_scope'] ?? 'active'),
                            'path'       => (string) $context['file_path'],
                        ]);
                    } catch (Throwable $throwable) {
                        $target = [
                            'content' => '',
                        ];
                    }
                }
                break;

            case 'restore_theme_backup':
                if (!empty($context['backup_id'])) {
                    try {
                        $backup = $this->theme_files_bridge->read_backup([
                            'backup_id' => (string) $context['backup_id'],
                        ]);

                        if ($context['file_path'] === '') {
                            $context['file_path'] = (string) ($backup['relative_path'] ?? '');
                        }

                        if (($context['root_scope'] ?? 'stylesheet') === 'stylesheet' && !empty($backup['root'])) {
                            $context['root_scope'] = (string) $backup['root'];
                        }

                        if ($context['content'] === '') {
                            $context['content'] = (string) ($backup['content'] ?? '');
                        }

                        if ($context['context_label'] === '') {
                            $context['context_label'] = sprintf(
                                __('Backup selected: %1$s (%2$s). Suggested action: %3$s.', 'livecanvas-forge-ai'),
                                (string) ($backup['relative_path'] ?? (string) $context['backup_id']),
                                (string) ($backup['created_at'] ?? __('time unknown', 'livecanvas-forge-ai')),
                                $this->get_action_label($action)
                            );
                        }
                    } catch (Throwable $throwable) {
                        $target = [
                            'content' => '',
                        ];
                    }
                }
                break;
        }

        if (!empty($target['post']) && is_array($target['post'])) {
            if ($context['title'] === '') {
                $context['title'] = (string) ($target['post']['title'] ?? '');
            }
            if ($context['slug'] === '') {
                $context['slug'] = (string) ($target['post']['slug'] ?? '');
            }
            if ($context['status'] === 'draft' && !empty($target['post']['status'])) {
                $context['status'] = (string) $target['post']['status'];
            }
            if ($context['content'] === '') {
                $context['content'] = (string) ($target['content'] ?? '');
            }
            if ($context['target_id'] === '' && !empty($target['post']['id'])) {
                $context['target_id'] = (string) (int) $target['post']['id'];
            }
        }

        if ($context['content'] === '' && !empty($target['content']) && is_string($target['content'])) {
            $context['content'] = $target['content'];
        }

        return $context;
    }

    private function build_command_redirect_args(array $payload): array {
        $redirect_args = [];

        if (!empty($payload['action'])) {
            $redirect_args['suggest_action'] = sanitize_key((string) $payload['action']);
        }

        if (!empty($payload['execution_target'])) {
            $redirect_args['execution_target'] = sanitize_key((string) $payload['execution_target']);
        }

        if (!empty($payload['target_id'])) {
            $redirect_args['target_id'] = absint($payload['target_id']);
        }

        if (!empty($payload['variant'])) {
            $redirect_args['variant'] = sanitize_text_field((string) $payload['variant']);
        }

        if (!empty($payload['title'])) {
            $redirect_args['title'] = sanitize_text_field((string) $payload['title']);
        }

        if (!empty($payload['slug'])) {
            $redirect_args['slug'] = sanitize_title((string) $payload['slug']);
        }

        if (!empty($payload['provider_id'])) {
            $redirect_args['provider_id'] = sanitize_text_field((string) $payload['provider_id']);
        }

        if (!empty($payload['relative_path'])) {
            $redirect_args['relative_path'] = sanitize_text_field((string) $payload['relative_path']);
        }

        if (!empty($payload['root_scope'])) {
            $redirect_args['root_scope'] = sanitize_key((string) $payload['root_scope']);
        }

        if (!empty($payload['file_path'])) {
            $redirect_args['file_path'] = sanitize_text_field((string) $payload['file_path']);
        }

        if (!empty($payload['backup_id'])) {
            $redirect_args['backup_id'] = sanitize_text_field((string) $payload['backup_id']);
        }

        if (!empty($payload['audit_id'])) {
            $redirect_args['audit_id'] = sanitize_key((string) $payload['audit_id']);
        }

        if (!empty($payload['status'])) {
            $redirect_args['status'] = sanitize_key((string) $payload['status']);
        }

        return $redirect_args;
    }

    private function get_command_context_for_post(WP_Post $post): array {
        $context = [
            'action'    => '',
            'target_id' => (int) $post->ID,
            'variant'   => '1',
        ];

        if ($post->post_type === 'lc_dynamic_template') {
            $context['action'] = 'update_dynamic_template';
            return $context;
        }

        if ($post->post_type === 'lc_partial') {
            $header_variant = (string) get_post_meta($post->ID, 'is_header', true);
            $footer_variant = (string) get_post_meta($post->ID, 'is_footer', true);

            if ($header_variant !== '') {
                $context['action']  = 'update_header';
                $context['variant'] = $header_variant ?: '1';
                return $context;
            }

            if ($footer_variant !== '') {
                $context['action']  = 'update_footer';
                $context['variant'] = $footer_variant ?: '1';
                return $context;
            }

            $context['action'] = 'update_partial';
            return $context;
        }

        if (get_post_meta($post->ID, '_lc_livecanvas_enabled', true) === '1' || $post->post_type === 'page') {
            $context['action'] = 'page_upsert';
        }

        return $context;
    }

    private function get_editor_bridge_actions(WP_Post $post, array $context): array {
        $actions = [];
        $current_action = !empty($context['action']) ? (string) $context['action'] : 'site_audit';
        $mcp_status = $this->context_builder->get_mcp_status();
        $local_bridge = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];

        $actions[] = [
            'label' => __('Current target', 'livecanvas-forge-ai'),
            'icon'  => 'command',
            'tone'  => 'primary',
            'url'   => $this->get_command_url([
                'post_id'        => $post->ID,
                'suggest_action' => $current_action,
                'target_id'      => (int) ($context['target_id'] ?? 0),
                'variant'        => (string) ($context['variant'] ?? '1'),
            ]),
        ];

        $actions[] = [
            'label' => __('Site audit', 'livecanvas-forge-ai'),
            'icon'  => 'activity',
            'tone'  => 'neutral',
            'url'   => $this->get_command_url([
                'suggest_action' => 'site_audit',
            ]),
        ];

        $inventory = method_exists($this->inventory, 'get_inventory') ? $this->inventory->get_inventory() : [];
        $header_partial = $this->get_first_inventory_partial((array) ($inventory['header_partials'] ?? []), 'header');
        $footer_partial = $this->get_first_inventory_partial((array) ($inventory['footer_partials'] ?? []), 'footer');

        if (!$header_partial && method_exists($this->inventory, 'resolve_partial_post_id')) {
            $header_id = (int) $this->inventory->resolve_partial_post_id('is_header', '1');
            if ($header_id > 0) {
                $header_partial = [
                    'id'           => $header_id,
                    'partial_type' => 'header',
                    'variant'      => '1',
                ];
            }
        }

        if (!$footer_partial && method_exists($this->inventory, 'resolve_partial_post_id')) {
            $footer_id = (int) $this->inventory->resolve_partial_post_id('is_footer', '1');
            if ($footer_id > 0) {
                $footer_partial = [
                    'id'           => $footer_id,
                    'partial_type' => 'footer',
                    'variant'      => '1',
                ];
            }
        }

        if ($header_partial) {
            $actions[] = [
                'label' => sprintf(__('Header partial v%s', 'livecanvas-forge-ai'), (string) ($header_partial['variant'] ?: '1')),
                'icon'  => 'layers',
                'tone'  => 'neutral',
                'url'   => $this->get_command_url([
                    'suggest_action' => 'update_header',
                    'target_id'      => (int) ($header_partial['id'] ?? 0),
                    'variant'        => (string) ($header_partial['variant'] ?: '1'),
                ]),
            ];
        }

        if ($footer_partial) {
            $actions[] = [
                'label' => sprintf(__('Footer partial v%s', 'livecanvas-forge-ai'), (string) ($footer_partial['variant'] ?: '1')),
                'icon'  => 'layers',
                'tone'  => 'neutral',
                'url'   => $this->get_command_url([
                    'suggest_action' => 'update_footer',
                    'target_id'      => (int) ($footer_partial['id'] ?? 0),
                    'variant'        => (string) ($footer_partial['variant'] ?: '1'),
                ]),
            ];
        }

        $actions[] = [
            'label' => __('WindPress audit', 'livecanvas-forge-ai'),
            'icon'  => 'wind',
            'tone'  => 'secondary',
            'url'   => $this->get_command_url([
                'suggest_action' => 'windpress_audit',
            ]),
        ];

        if (!empty($local_bridge['build_available'])) {
            $actions[] = [
                'label' => __('Build Tailwind', 'livecanvas-forge-ai'),
                'icon'  => 'rocket',
                'tone'  => 'secondary',
                'url'   => $this->get_command_url([
                    'suggest_action' => 'build_windpress_cache',
                ]),
            ];
        }

        $actions[] = [
            'label' => __('Connections', 'livecanvas-forge-ai'),
            'icon'  => 'plug',
            'tone'  => 'secondary',
            'url'   => $this->get_dashboard_url([
                'tab' => 'connections',
            ]),
        ];

        return array_slice($actions, 0, 7);
    }

    private function get_first_inventory_partial(array $partials, string $type): array {
        foreach ($partials as $partial) {
            if (!is_array($partial)) {
                continue;
            }

            if ((string) ($partial['partial_type'] ?? $type) !== $type) {
                continue;
            }

            return $partial + [
                'id'      => 0,
                'variant' => '1',
            ];
        }

        return [];
    }

    private function get_default_windpress_provider_ids(array $providers): string {
        $selected = [];

        foreach ($providers as $provider) {
            if (!is_array($provider) || empty($provider['id'])) {
                continue;
            }

            if (!empty($provider['enabled']) && (!array_key_exists('installed', $provider) || !empty($provider['installed']))) {
                $selected[] = (string) $provider['id'];
            }
        }

        if (!$selected && !empty($providers[0]['id'])) {
            $selected[] = (string) $providers[0]['id'];
        }

        return implode(',', array_values(array_unique($selected)));
    }

    private function format_file_size_label(int $bytes): string {
        return $bytes > 0 ? size_format($bytes) : __('0 B', 'livecanvas-forge-ai');
    }

    private function get_editor_target_summary(WP_Post $post, array $context): string {
        $variant = !empty($context['variant']) ? (string) $context['variant'] : '1';
        $label   = $this->get_editor_target_type_label($post, $context);

        if (($label === __('Header partial', 'livecanvas-forge-ai') || $label === __('Footer partial', 'livecanvas-forge-ai')) && $variant !== '1') {
            return sprintf(__('Target: %1$s · variant %2$s', 'livecanvas-forge-ai'), $label, $variant);
        }

        return sprintf(__('Target: %s', 'livecanvas-forge-ai'), $label);
    }

    private function get_editor_connection_status_label(string $client, string $state): string {
        $client = sanitize_key($client);

        if ($client === '') {
            return __('No agent connected', 'livecanvas-forge-ai');
        }

        $label = ucfirst(str_replace(['-', '_'], ' ', $client));

        if ($state === 'connected') {
            return sprintf(__('%s connected', 'livecanvas-forge-ai'), $label);
        }

        return sprintf(__('%s not connected', 'livecanvas-forge-ai'), $label);
    }

    private function get_editor_target_type_label(WP_Post $post, array $context): string {
        $action = !empty($context['action']) ? (string) $context['action'] : 'site_audit';

        if ($action === 'update_header') {
            return __('Header partial', 'livecanvas-forge-ai');
        }

        if ($action === 'update_footer') {
            return __('Footer partial', 'livecanvas-forge-ai');
        }

        if ($action === 'update_partial') {
            return __('Generic partial', 'livecanvas-forge-ai');
        }

        if ($post->post_type === 'lc_partial') {
            if ((string) get_post_meta($post->ID, 'is_header', true) === '1') {
                return __('Header partial', 'livecanvas-forge-ai');
            }

            if ((string) get_post_meta($post->ID, 'is_footer', true) === '1') {
                return __('Footer partial', 'livecanvas-forge-ai');
            }

            return __('Partial', 'livecanvas-forge-ai');
        }

        if ($post->post_type === 'lc_dynamic_template') {
            return __('Dynamic template', 'livecanvas-forge-ai');
        }

        if ($post->post_type === 'page') {
            return __('Page', 'livecanvas-forge-ai');
        }

        return ucwords(str_replace(['-', '_'], ' ', (string) $post->post_type));
    }

    private function get_editor_initial_conversation_state(array $messages): string {
        $latest_message = null;

        foreach (array_reverse($messages) as $message) {
            if (is_array($message)) {
                $latest_message = $message;
                break;
            }
        }

        if (!is_array($latest_message)) {
            return 'idle';
        }

        $role = (string) ($latest_message['role'] ?? '');
        $meta = is_array($latest_message['meta'] ?? null) ? $latest_message['meta'] : [];

        if ($role === 'suggestion_result') {
            return 'suggested';
        }

        if ($role === 'tool_result') {
            if (array_key_exists('ok', $meta) && empty($meta['ok'])) {
                return 'failed';
            }

            $mode = sanitize_key((string) ($meta['mode'] ?? ''));

            if (in_array($mode, ['preview', 'dry_run'], true)) {
                return 'previewed';
            }

            return 'applied';
        }

        return 'idle';
    }

    private function get_editor_conversation_state_label(string $state): string {
        switch ($state) {
            case 'thinking':
                return __('Sending request...', 'livecanvas-forge-ai');
            case 'queueing':
                return __('Queued for inline execution.', 'livecanvas-forge-ai');
            case 'running':
                return __('Running inline execution...', 'livecanvas-forge-ai');
            case 'suggested':
                return __('Request prepared.', 'livecanvas-forge-ai');
            case 'previewed':
                return __('Preview ready. Review the support details below.', 'livecanvas-forge-ai');
            case 'applied':
                return __('Change applied inline.', 'livecanvas-forge-ai');
            case 'failed':
                return __('The current request failed. Review the support details below.', 'livecanvas-forge-ai');
            case 'idle':
            default:
                return __('Ready for a new request.', 'livecanvas-forge-ai');
        }
    }

    private function render_editor_bridge_thread_message(array $message): void {
        $message = LCFA_Thread_Message_Actions::decorate_message($message, [
            'thread_id' => LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? 'default')),
            'command_url_builder' => [$this, 'get_command_url'],
        ]);
        $role = in_array((string) ($message['role'] ?? ''), ['user', 'assistant', 'suggestion_result', 'system', 'tool_result'], true)
            ? (string) $message['role']
            : 'assistant';

        if ($role === 'suggestion_result') {
            return;
        }

        echo '<article class="lcfa-editor-thread-message is-' . esc_attr($role) . '">';
        echo '<div class="lcfa-editor-thread-message__head">';
        echo '<span>' . esc_html((string) ($message['label'] ?? ucfirst($role))) . '</span>';
        echo '<span>' . esc_html((string) ($message['time'] ?? '')) . '</span>';
        echo '</div>';
        echo '<pre class="lcfa-editor-thread-message__body">' . esc_html((string) ($message['content'] ?? '')) . '</pre>';
        $attachments = is_array($message['attachments'] ?? null) ? (array) $message['attachments'] : [];
        if ($attachments) {
            echo '<div class="lcfa-editor-thread-message__attachments">';
            foreach ($attachments as $attachment) {
                if (!is_array($attachment) || ($attachment['kind'] ?? '') !== 'image' || empty($attachment['data_url'])) {
                    continue;
                }

                echo '<figure class="lcfa-editor-thread-message__attachment">';
                echo '<img class="lcfa-editor-thread-message__attachment-image" src="' . esc_attr((string) $attachment['data_url']) . '" alt="">';
                $attachment_copy = trim(implode(' • ', array_filter([
                    (string) ($attachment['name'] ?? ''),
                    (string) ($attachment['caption'] ?? ''),
                ])));
                if ($attachment_copy !== '') {
                    echo '<figcaption class="lcfa-editor-thread-message__attachment-copy">' . esc_html($attachment_copy) . '</figcaption>';
                }
                echo '</figure>';
            }
            echo '</div>';
        }
        $meta_badges = $this->get_thread_message_meta_badges(is_array($message['meta'] ?? null) ? (array) $message['meta'] : []);
        if ($meta_badges) {
            echo '<div class="lcfa-editor-thread-message__meta">';
            foreach ($meta_badges as $badge) {
                echo '<span class="lcfa-editor-thread-message__chip">' . esc_html($badge) . '</span>';
            }
            echo '</div>';
        }
        $actions = LCFA_Thread_Message_Actions::sanitize_actions((array) ($message['actions'] ?? []));
        if ($actions && $role !== 'suggestion_result') {
            echo '<div class="lcfa-editor-thread-message__actions">';
            foreach ($actions as $action) {
                if (($action['kind'] ?? 'url') === 'apply' && !empty($action['payload'])) {
                    echo '<button type="button" class="lcfa-editor-thread-message__link" data-lcfa-editor-thread-apply="' . esc_attr(wp_json_encode($action['payload'])) . '">' . esc_html((string) $action['label']) . '</button>';
                    continue;
                }

                echo '<a class="lcfa-editor-thread-message__link" href="' . esc_url((string) ($action['url'] ?? '')) . '" target="_blank" rel="noreferrer noopener">' . esc_html((string) $action['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</article>';
    }

    private function get_action_label(string $action): string {
        $actions = $this->command_deck->get_actions();

        return isset($actions[$action]['label']) ? (string) $actions[$action]['label'] : $action;
    }

    private function get_inventory_item_command_url(string $group_key, array $item): string {
        $post = !empty($item['id']) ? get_post((int) $item['id']) : null;

        if (!$post instanceof WP_Post) {
            return '';
        }

        $context = $this->get_command_context_for_post($post);

        if ($group_key === 'header_partials') {
            $context['action'] = 'update_header';
        } elseif ($group_key === 'footer_partials') {
            $context['action'] = 'update_footer';
        } elseif ($group_key === 'dynamic_templates') {
            $context['action'] = 'update_dynamic_template';
        } elseif ($group_key === 'livecanvas_pages') {
            $context['action'] = 'page_upsert';
        }

        if (empty($context['action'])) {
            return '';
        }

        return $this->get_command_url([
            'post_id'        => $post->ID,
            'suggest_action' => $context['action'] ?? '',
            'target_id'      => $context['target_id'] ?? 0,
            'variant'        => $context['variant'] ?? '',
        ]);
    }

    private function get_command_url(array $args = []): string {
        $defaults = [
            'tab' => 'command',
        ];
        $current_thread_id = LCFA_Settings::normalize_thread_id((string) ($_GET['thread_id'] ?? ''));

        if ($current_thread_id !== 'default' && !array_key_exists('thread_id', $args)) {
            $defaults['thread_id'] = $current_thread_id;
        }

        return $this->get_dashboard_url(array_merge($defaults, $args));
    }

    private function get_dashboard_url(array $args = []): string {
        return add_query_arg(array_merge([
            'page' => 'lcfa-dashboard',
        ], $args), admin_url('admin.php'));
    }

    private function get_plugin_asset_url(string $slug, string $relative_path): string {
        $plugin_file = $this->environment->find_plugin_file_by_slug($slug);

        if (!$plugin_file) {
            return '';
        }

        return plugins_url($relative_path, WP_PLUGIN_DIR . '/' . $plugin_file);
    }

    private function get_agent_icon_url(string $client): string {
        $map = [
            'codex'    => 'assets/agent-icons/codex-color.svg',
            'opencode' => 'assets/agent-icons/opencode.svg',
            'cursor'   => 'assets/agent-icons/cursor.svg',
            'claude'   => 'assets/agent-icons/claude-color.svg',
        ];

        if ($client === 'claude-code') {
            $client = 'claude';
        }

        if (empty($map[$client])) {
            return '';
        }

        $asset_relative_path = $map[$client];
        $asset_absolute_path = LCFA_DIR . $asset_relative_path;

        if (!file_exists($asset_absolute_path)) {
            return '';
        }

        return LCFA_URL . $asset_relative_path;
    }

    private function get_agent_icon_markup(string $client, string $fallback_icon = 'plug', string $class = 'lcfa-agent-icon'): string {
        $icon_url = $this->get_agent_icon_url($client);

        if ($icon_url !== '') {
            return '<img src="' . esc_url($icon_url) . '" alt="" class="' . esc_attr($class) . '" loading="lazy">';
        }

        return $this->get_icon_svg($fallback_icon);
    }

    private function get_radio_icon_markup(string $name, string $value, string $fallback_icon): string {
        if (in_array($name, ['ai_tool', 'preferred_client'], true)) {
            return $this->get_agent_icon_markup($value, $fallback_icon);
        }

        return $this->get_icon_svg($fallback_icon);
    }

    private function get_client_fallback_icon(string $client): string {
        $map = [
            'codex' => 'stars',
            'opencode' => 'braces',
            'claude' => 'cpu',
            'claude-code' => 'cpu',
            'cursor' => 'cursor',
            'generic' => 'plug',
        ];

        return $map[$client] ?? 'plug';
    }

    private function get_icon_svg(string $name): string {
        $icons = [
            'activity' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6 2a.5.5 0 0 1 .47.329L8.708 8H11.5a.5.5 0 0 1 .39.812l-2.5 3A.5.5 0 0 1 9 12H6.292l-1.823 1.823A.5.5 0 0 1 3.646 13H1.5a.5.5 0 0 1 0-1h1.793l1.854-1.854A.5.5 0 0 1 5.5 10H8.77l1.667-2H8.292a.5.5 0 0 1-.47-.329L5.59 2.53A.5.5 0 0 1 6 2Z"/></svg>',
            'braces' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6.114 2.68a.5.5 0 0 1-.496.444c-.694.049-1.198.292-1.546.721-.35.43-.54 1.09-.54 1.955v.662c0 .97-.19 1.683-.582 2.18-.194.246-.44.44-.734.58.294.14.54.334.734.58.392.497.582 1.21.582 2.18v.661c0 .865.19 1.526.54 1.955.348.43.852.672 1.546.721a.5.5 0 0 1-.07.998c-.93-.066-1.715-.404-2.252-1.066-.537-.662-.764-1.57-.764-2.608v-.661c0-.839-.161-1.326-.37-1.592C1.832 10.542 1.5 10.386 1 10.386v-.772c.5 0 .832-.156 1.178-.596.209-.266.37-.753.37-1.592v-.662c0-1.038.227-1.946.764-2.608.537-.662 1.322-1 2.252-1.066a.5.5 0 0 1 .55.49Zm3.772 0a.5.5 0 0 1 .55-.49c.93.066 1.715.404 2.252 1.066.537.662.764 1.57.764 2.608v.662c0 .839.161 1.326.37 1.592.346.44.678.596 1.178.596v.772c-.5 0-.832.156-1.178.596-.209.266-.37.753-.37 1.592v.661c0 1.038-.227 1.946-.764 2.608-.537.662-1.322 1-2.252 1.066a.5.5 0 0 1-.07-.998c.694-.049 1.198-.292 1.546-.721.35-.43.54-1.09.54-1.955v-.661c0-.97.19-1.683.582-2.18.194-.246.44-.44.734-.58-.294-.14-.54-.334-.734-.58-.392-.497-.582-1.21-.582-2.18v-.662c0-.865-.19-1.526-.54-1.955-.348-.43-.852-.672-1.546-.721a.5.5 0 0 1-.496-.444Z"/></svg>',
            'check-circle' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0ZM6.97 11.03a.75.75 0 0 0 1.08.022l3.992-4.99a.75.75 0 0 0-1.172-.938L7.404 9.462 5.383 7.44a.75.75 0 1 0-1.06 1.06l2.647 2.53Z"/></svg>',
            'check2-square' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12ZM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2Z"/><path fill="currentColor" d="M10.854 5.146a.5.5 0 0 1 0 .708l-3.5 3.5a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.293l3.146-3.147a.5.5 0 0 1 .708 0Z"/></svg>',
            'cloud' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M4.406 2.342A5.53 5.53 0 0 1 8 1c2.453 0 4.512 1.59 5.258 3.787A4.5 4.5 0 0 1 13.5 13h-9A4.5 4.5 0 0 1 4.406 2.342Z"/></svg>',
            'command' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M7.5 2a1.5 1.5 0 0 0-3 0V4h-2A1.5 1.5 0 0 0 1 5.5v1A1.5 1.5 0 0 0 2.5 8h2v2h-2A1.5 1.5 0 0 0 1 11.5v1A1.5 1.5 0 0 0 2.5 14h1A1.5 1.5 0 0 0 5 12.5V10h2v2.5A1.5 1.5 0 0 0 8.5 14h1a1.5 1.5 0 0 0 1.5-1.5V10h2a1.5 1.5 0 0 0 1.5-1.5v-1A1.5 1.5 0 0 0 13.5 6h-2V3.5A1.5 1.5 0 0 0 10 2h-1A1.5 1.5 0 0 0 7.5 3.5V6h-2V3.5A1.5 1.5 0 0 0 4 2h1A1.5 1.5 0 0 1 6.5 3.5V6h3V3.5A1.5 1.5 0 0 0 8 2h-.5Z"/></svg>',
            'cpu' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5 0a.5.5 0 0 1 .5.5V1h5V.5a.5.5 0 0 1 1 0V1h.5A1.5 1.5 0 0 1 13.5 2.5V3h.5a.5.5 0 0 1 0 1h-.5v2h.5a.5.5 0 0 1 0 1h-.5v2h.5a.5.5 0 0 1 0 1h-.5v.5A1.5 1.5 0 0 1 12 12h-.5v.5a.5.5 0 0 1-1 0V12h-5v.5a.5.5 0 0 1-1 0V12H4A1.5 1.5 0 0 1 2.5 10.5V10H2a.5.5 0 0 1 0-1h.5V7H2a.5.5 0 0 1 0-1h.5V4H2a.5.5 0 0 1 0-1h.5v-.5A1.5 1.5 0 0 1 4 1h.5V.5A.5.5 0 0 1 5 0Zm-1 2a.5.5 0 0 0-.5.5v8A.5.5 0 0 0 4 11h8a.5.5 0 0 0 .5-.5v-8A.5.5 0 0 0 12 2H4Zm2 2h4v4H6V4Zm1 1v2h2V5H7Z"/></svg>',
            'cursor' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.884.511a.5.5 0 0 1 .624.307l4.61 12.905a.5.5 0 0 1-.663.636L7.93 13.25l-1.762 2.817a.5.5 0 0 1-.9-.042L.53 1.836A.5.5 0 0 1 1.16 1.16L5.884.511Z"/></svg>',
            'eye' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8Zm-8 4a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-1.5A2.5 2.5 0 1 0 8 5.5a2.5 2.5 0 0 0 0 5Z"/></svg>',
            'file-earmark' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5ZM9.5 3A1.5 1.5 0 0 0 11 4.5H13L9.5 1v2Z"/></svg>',
            'globe' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0Zm4.96 7H9.6a13.45 13.45 0 0 0-.48-3.03A6.52 6.52 0 0 1 12.96 7ZM8 1.04c.83 0 1.84 1.23 2.27 3.46H5.73C6.16 2.27 7.17 1.04 8 1.04ZM3.04 7a6.52 6.52 0 0 1 3.84-3.03A13.45 13.45 0 0 0 6.4 7H3.04Zm0 2H6.4c.07 1.09.24 2.12.48 3.03A6.52 6.52 0 0 1 3.04 9Zm4.96 5.96c-.83 0-1.84-1.23-2.27-3.46h4.54c-.43 2.23-1.44 3.46-2.27 3.46ZM9.12 12.03c.24-.91.41-1.94.48-3.03h3.36a6.52 6.52 0 0 1-3.84 3.03Z"/></svg>',
            'laptop' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M13.5 3h-11A1.5 1.5 0 0 0 1 4.5v6A1.5 1.5 0 0 0 2.5 12h11a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 13.5 3ZM2 4.5a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-6ZM0 13.5a.5.5 0 0 1 .5-.5h15a.5.5 0 0 1 0 1H.5a.5.5 0 0 1-.5-.5Z"/></svg>',
            'layers' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M8.235 1.559a.5.5 0 0 0-.47 0l-6.5 3.25a.5.5 0 0 0 0 .894l6.5 3.25a.5.5 0 0 0 .47 0l6.5-3.25a.5.5 0 0 0 0-.894l-6.5-3.25ZM2.33 5.5 8 2.665 13.67 5.5 8 8.335 2.33 5.5Zm-1.565 2.54a.5.5 0 0 1 .67-.224L8 10.835l6.565-3.019a.5.5 0 0 1 .447.894l-6.8 3.125a.5.5 0 0 1-.424 0l-6.8-3.125a.5.5 0 0 1-.224-.67Zm0 3a.5.5 0 0 1 .67-.224L8 13.835l6.565-3.019a.5.5 0 0 1 .447.894l-6.8 3.125a.5.5 0 0 1-.424 0l-6.8-3.125a.5.5 0 0 1-.224-.67Z"/></svg>',
            'moon-stars' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6 0a.5.5 0 0 1 .5.5V2H8a.5.5 0 0 1 0 1H6.5v1.5a.5.5 0 0 1-1 0V3H4a.5.5 0 0 1 0-1h1.5V.5A.5.5 0 0 1 6 0Zm7 3a.5.5 0 0 1 .5.5V4H14a.5.5 0 0 1 0 1h-.5v.5a.5.5 0 0 1-1 0V5H12a.5.5 0 0 1 0-1h.5v-.5A.5.5 0 0 1 13 3Zm-4.36 1.5a.5.5 0 0 1 .363.648A5.5 5.5 0 1 0 12.852 12a.5.5 0 0 1 .648.363.5.5 0 0 1-.32.61A6.5 6.5 0 1 1 8.03 4.18a.5.5 0 0 1 .61.32Z"/></svg>',
            'plug' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6 0a.5.5 0 0 1 .5.5V3H8V.5a.5.5 0 0 1 1 0V3h1.5V.5a.5.5 0 0 1 1 0V3h.5A1.5 1.5 0 0 1 13.5 4.5v2A1.5 1.5 0 0 1 12 8h-.5v1A3.5 3.5 0 0 1 8 12.5V15a1 1 0 0 1-2 0v-2.5A3.5 3.5 0 0 1 2.5 9V8H2A1.5 1.5 0 0 1 .5 6.5v-2A1.5 1.5 0 0 1 2 3h.5V.5A.5.5 0 0 1 3 0h3Z"/></svg>',
            'power' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M7.25 1.5a.75.75 0 0 1 1.5 0v5.25a.75.75 0 0 1-1.5 0V1.5Z"/><path fill="currentColor" d="M4.11 2.89a.75.75 0 0 1 1.06 1.06A5.25 5.25 0 1 0 12.83 4a.75.75 0 1 1 1.06-1.06 6.75 6.75 0 1 1-9.78-.05Z"/></svg>',
            'rocket' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M8 0C5.613 2.016 4.5 4.515 4.5 7c0 1.377.268 2.486.67 3.311L2 13.48V16h2.52l3.17-3.17A6.37 6.37 0 0 0 9 13.5c2.485 0 4.984-1.113 7-3.5C16 5.582 14.418 0 8 0ZM5.5 6.5A1.5 1.5 0 1 1 8 5a1.5 1.5 0 0 1-2.5 1.5ZM3 9.5 0 13l3 3v-2.5l2.5-2.5L3 9.5Z"/></svg>',
            'shield-check' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.072.21a17.598 17.598 0 0 1 5.856 0A1 1 0 0 1 11.81 1l.39 2.924a16.99 16.99 0 0 1-.23 5.082A7.962 7.962 0 0 1 8 14.548a7.962 7.962 0 0 1-3.97-5.542 16.99 16.99 0 0 1-.23-5.082L4.19 1a1 1 0 0 1 .882-.79ZM10.854 5.146a.5.5 0 0 0-.708 0L7.5 7.793 6.354 6.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0 0-.708Z"/></svg>',
            'shield-lock' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.072.21a17.598 17.598 0 0 1 5.856 0A1 1 0 0 1 11.81 1l.39 2.924a16.99 16.99 0 0 1-.23 5.082A7.962 7.962 0 0 1 8 14.548a7.962 7.962 0 0 1-3.97-5.542 16.99 16.99 0 0 1-.23-5.082L4.19 1a1 1 0 0 1 .882-.79ZM8 8a1 1 0 0 0-1 1v1h2V9a1 1 0 0 0-1-1Zm2 2V9a2 2 0 1 0-4 0v1a1 1 0 0 0-1 1v1.5A1.5 1.5 0 0 0 6.5 14h3A1.5 1.5 0 0 0 11 12.5V11a1 1 0 0 0-1-1Z"/></svg>',
            'shuffle' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M9.5 3a.5.5 0 0 1 .5-.5h3A.5.5 0 0 1 13.5 3v3a.5.5 0 0 1-1 0V4.707l-2.646 2.647a.5.5 0 0 1-.708-.708L11.793 4H10a.5.5 0 0 1-.5-.5Zm-3 0a.5.5 0 0 1 .354.146l2 2a.5.5 0 1 1-.708.708l-2-2A.5.5 0 0 1 6.5 3Zm2.354 5.146a.5.5 0 0 1 0 .708l-2 2a.5.5 0 0 1-.708-.708l2-2a.5.5 0 0 1 .708 0ZM9.5 10a.5.5 0 0 1 .5-.5h1.793L9.146 6.854a.5.5 0 1 1 .708-.708L12.5 8.793V7.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5Z"/><path fill="currentColor" d="M3.5 4A1.5 1.5 0 0 0 2 5.5v5A1.5 1.5 0 0 0 3.5 12h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 1-.5-.5v-5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 0 0-1h-1Z"/></svg>',
            'stars' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M7.247 4.86 8 3l.753 1.86L10.613 5l-1.86.753L8 7.613 7.247 5.753 5.387 5l1.86-.14Zm6.192 3.332L14 7l.56 1.192L15.753 9l-1.193.808L14 11l-.56-1.192L12.247 9l1.192-.808ZM3.5 10.5 4 9l.5 1.5L6 11l-1.5.5L4 13l-.5-1.5L2 11l1.5-.5ZM8 8l1.09 2.91L12 12l-2.91 1.09L8 16l-1.09-2.91L4 12l2.91-1.09L8 8Z"/></svg>',
            'wind' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M6 1.5A2.5 2.5 0 1 1 8.5 4H1.5a.5.5 0 0 1 0-1h7a1.5 1.5 0 1 0-1.5-1.5.5.5 0 0 1-1 0Zm4 4A2.5 2.5 0 1 1 12.5 8H1.5a.5.5 0 0 1 0-1h11a1.5 1.5 0 1 0-1.5-1.5.5.5 0 0 1-1 0ZM4 9a2.5 2.5 0 1 1 2.45 3H1.5a.5.5 0 0 1 0-1h4.95A1.5 1.5 0 1 0 5 9.5a.5.5 0 0 1-1 0Z"/></svg>',
            'window-stack' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M3 1a2 2 0 0 0-2 2v7h1V3a1 1 0 0 1 1-1h8V1H3Zm2 3a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5Zm0 1h8a1 1 0 0 1 1 1v1H4V6a1 1 0 0 1 1-1Zm-1 3h10v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8Z"/></svg>',
            'x-circle' => '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0ZM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646Z"/></svg>',
        ];

        $svg = $icons[$name] ?? $icons['stars'];

        return '<span class="lcfa-icon lcfa-icon-' . esc_attr($name) . '">' . $svg . '</span>';
    }

    private function sanitize_key_compat(string $value): string {
        if (function_exists('sanitize_key')) {
            return sanitize_key($value);
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);

        return is_string($value) ? $value : '';
    }

    private function redirect_to_step(int $step): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=' . $step));
        exit;
    }
}
