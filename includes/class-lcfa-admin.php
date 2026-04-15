<?php

defined('ABSPATH') || exit;

final class LCFA_Admin {
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
    private LCFA_Inventory $inventory;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Connection_Tester $connection_tester;
    private LCFA_Remote_Client $remote_client;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Connection_Onboarding $connection_onboarding;
    private LCFA_Connection_Wizard_Presenter $connection_wizard_presenter;
    private LCFA_Admin_Hero_Presenter $admin_hero_presenter;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Prompt_Suggester $prompt_suggester;
    private LCFA_Genesis_Planner $genesis_planner;

    public function __construct(LCFA_Environment $environment, LCFA_Installer $installer, LCFA_Inventory $inventory, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Connection_Tester $connection_tester, LCFA_Remote_Client $remote_client, LCFA_Context_Builder $context_builder, LCFA_Connection_Onboarding $connection_onboarding, LCFA_Command_Deck $command_deck, LCFA_Prompt_Suggester $prompt_suggester, LCFA_Genesis_Planner $genesis_planner) {
        $this->environment  = $environment;
        $this->installer    = $installer;
        $this->inventory    = $inventory;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->connection_tester = $connection_tester;
        $this->remote_client = $remote_client;
        $this->context_builder = $context_builder;
        $this->connection_onboarding = $connection_onboarding;
        $this->connection_wizard_presenter = new LCFA_Connection_Wizard_Presenter();
        $this->admin_hero_presenter = new LCFA_Admin_Hero_Presenter();
        $this->command_deck = $command_deck;
        $this->prompt_suggester = $prompt_suggester;
        $this->genesis_planner = $genesis_planner;
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menus'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('wp_ajax_lcfa_connections_secondary', [$this, 'handle_connections_secondary_ajax']);
        add_action('admin_post_lcfa_setup', [$this, 'handle_setup_post']);
        add_action('admin_post_lcfa_reset_setup', [$this, 'handle_reset_setup_post']);
        add_action('admin_post_lcfa_connections', [$this, 'handle_connections_post']);
        add_action('admin_post_lcfa_test_connections', [$this, 'handle_connection_test_post']);
        add_action('admin_post_lcfa_install_client_bundle', [$this, 'handle_install_client_bundle_post']);
        add_action('admin_post_lcfa_download_client_bundle', [$this, 'handle_download_client_bundle_post']);
        add_action('admin_post_lcfa_reconfigure_connection', [$this, 'handle_reconfigure_connection_post']);
        add_action('admin_post_lcfa_project_brief', [$this, 'handle_project_brief_post']);
        add_action('admin_post_lcfa_generate_plan', [$this, 'handle_generate_plan_post']);
        add_action('admin_post_lcfa_create_thread', [$this, 'handle_create_thread_post']);
        add_action('admin_post_lcfa_command', [$this, 'handle_command_post']);
        add_action('lc_editor_header', [$this, 'render_editor_bridge_styles']);
        add_action('lc_editor_before_body_closing', [$this, 'render_editor_bridge']);
    }

    public function register_menus(): void {
        $parent_slug = $this->environment->get_livecanvas_menu_slug();
        $admin_capability = 'manage_options';

        if ($parent_slug) {
            add_submenu_page(
                $parent_slug,
                __('Forge AI', 'livecanvas-forge-ai'),
                __('Forge AI', 'livecanvas-forge-ai'),
                $admin_capability,
                'lcfa-dashboard',
                [$this, 'render_dashboard_page']
            );

            return;
        }

        add_menu_page(
            __('LiveCanvas Forge AI', 'livecanvas-forge-ai'),
            __('Forge AI', 'livecanvas-forge-ai'),
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

        wp_localize_script('lcfa-admin-script', 'lcfaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'connectionsSecondaryAction' => 'lcfa_connections_secondary',
            'connectionsSecondaryNonce' => wp_create_nonce('lcfa_connections_secondary'),
            'labels' => [
                'loading' => __('Loading connection details…', 'livecanvas-forge-ai'),
                'loadFailed' => __('Failed to load connection details. Refresh the page or try again in a moment.', 'livecanvas-forge-ai'),
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

        wp_send_json_success([
            'panels' => [
                'remote'   => $remote_html,
                'advanced' => $advanced_html,
            ],
        ]);
    }

    public function render_editor_bridge_styles(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<style id="lcfa-editor-bridge-styles">';
        echo '.lcfa-editor-shell{position:fixed;right:20px;bottom:20px;z-index:999999;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}';
        echo '.lcfa-editor-launcher{display:inline-flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid rgba(53,208,242,.28);border-radius:999px;background:rgba(15,18,32,.94);box-shadow:0 20px 60px rgba(0,0,0,.45);color:#f5f7ff;cursor:pointer;backdrop-filter:blur(16px);font-size:13px;font-weight:700;transition:transform .2s ease,opacity .2s ease,border-color .2s ease}';
        echo '.lcfa-editor-launcher:hover{transform:translateY(-1px);border-color:rgba(53,208,242,.44)}';
        echo '.lcfa-editor-launcher .lcfa-icon{width:16px;height:16px;color:#35d0f2}';
        echo '.lcfa-editor-drawer{position:absolute;right:0;bottom:0;width:360px;max-width:min(360px,calc(100vw - 32px));padding:18px;border-radius:22px;background:linear-gradient(180deg,rgba(17,22,38,.96),rgba(13,16,29,.96));border:1px solid rgba(53,208,242,.2);box-shadow:0 24px 80px rgba(0,0,0,.52);backdrop-filter:blur(18px);color:#f5f7ff;opacity:0;transform:translateY(10px) scale(.98);pointer-events:none;transition:opacity .22s ease,transform .22s ease}';
        echo '.lcfa-editor-shell.is-open .lcfa-editor-drawer{opacity:1;transform:translateY(0) scale(1);pointer-events:auto}';
        echo '.lcfa-editor-shell.is-open .lcfa-editor-launcher{opacity:0;pointer-events:none;transform:translateY(6px)}';
        echo '.lcfa-editor-bridge__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}';
        echo '.lcfa-editor-bridge__eyebrow{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#35d0f2;font-weight:700;margin-bottom:6px}';
        echo '.lcfa-editor-bridge__title{font-size:17px;line-height:1.35;font-weight:700;margin:0;color:#fff}';
        echo '.lcfa-editor-bridge__close{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:#ff62bf;cursor:pointer}';
        echo '.lcfa-editor-bridge__close:hover{background:rgba(238,30,149,.16)}';
        echo '.lcfa-editor-bridge__stack{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}';
        echo '.lcfa-editor-bridge__pill{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:rgba(53,208,242,.08);border:1px solid rgba(53,208,242,.2);color:#35d0f2}';
        echo '.lcfa-editor-bridge__pill.is-secondary{background:rgba(238,30,149,.1);border-color:rgba(238,30,149,.26);color:#ff62bf}';
        echo '.lcfa-editor-bridge__section{padding:12px 0;border-top:1px solid rgba(255,255,255,.07)}';
        echo '.lcfa-editor-bridge__section:first-of-type{border-top:none;padding-top:0}';
        echo '.lcfa-editor-bridge__label{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:rgba(245,247,255,.54);font-weight:700;margin-bottom:6px}';
        echo '.lcfa-editor-bridge__meta{font-size:13px;line-height:1.6;color:rgba(245,247,255,.78)}';
        echo '.lcfa-editor-bridge__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}';
        echo '.lcfa-editor-bridge__button{display:flex;align-items:center;gap:8px;min-height:42px;padding:10px 12px;border-radius:14px;text-decoration:none;font-size:12px;font-weight:700;border:1px solid transparent;transition:transform .2s ease,opacity .2s ease,border-color .2s ease}';
        echo '.lcfa-editor-bridge__button:hover{transform:translateY(-1px)}';
        echo '.lcfa-editor-bridge__button .lcfa-icon{width:14px;height:14px}';
        echo '.lcfa-editor-bridge__button.is-primary{background:#35d0f2;color:#0b1020}';
        echo '.lcfa-editor-bridge__button.is-primary .lcfa-icon{color:#0b1020}';
        echo '.lcfa-editor-bridge__button.is-secondary{background:rgba(238,30,149,.12);border-color:rgba(238,30,149,.22);color:#ff62bf}';
        echo '.lcfa-editor-bridge__button.is-neutral{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08);color:#f5f7ff}';
        echo '.lcfa-editor-bridge__button.is-neutral .lcfa-icon{color:#35d0f2}';
        echo '.lcfa-editor-bridge__button[disabled]{opacity:.45;pointer-events:none}';
        echo '.lcfa-editor-bridge__controls{display:grid;gap:10px}';
        echo '.lcfa-editor-bridge__row{display:grid;grid-template-columns:1fr 1fr;gap:10px}';
        echo '.lcfa-editor-bridge__field{display:grid;gap:6px}';
        echo '.lcfa-editor-bridge__field span{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(245,247,255,.54);font-weight:700}';
        echo '.lcfa-editor-bridge__field select,.lcfa-editor-bridge__field textarea{width:100%;border-radius:14px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:#f5f7ff;padding:10px 12px;box-shadow:none}';
        echo '.lcfa-editor-bridge__field select option{color:#111}';
        echo '.lcfa-editor-bridge__field textarea{min-height:96px;resize:vertical;line-height:1.55}';
        echo '.lcfa-editor-bridge__result{display:none;gap:10px;margin-top:10px;padding:12px;border-radius:16px;border:1px solid rgba(53,208,242,.18);background:rgba(53,208,242,.06)}';
        echo '.lcfa-editor-bridge__result.is-visible{display:grid}';
        echo '.lcfa-editor-bridge__result.is-error{border-color:rgba(238,30,149,.24);background:rgba(238,30,149,.08)}';
        echo '.lcfa-editor-bridge__result-meta{display:flex;flex-wrap:wrap;gap:8px}';
        echo '.lcfa-editor-bridge__chip{display:inline-flex;align-items:center;padding:6px 9px;border-radius:999px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);font-size:11px;font-weight:700;color:#f5f7ff}';
        echo '.lcfa-editor-bridge__list{display:grid;gap:6px;margin:0;padding-left:18px;color:rgba(245,247,255,.76);font-size:12px;line-height:1.55}';
        echo '.lcfa-editor-bridge__code{margin:0;padding:10px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.08);background:rgba(11,16,32,.54);color:rgba(245,247,255,.84);font-size:11px;line-height:1.6;white-space:pre-wrap;word-break:break-word;overflow:auto}';
        echo '.lcfa-editor-bridge__status{font-size:12px;line-height:1.55;color:rgba(245,247,255,.74)}';
        echo '.lcfa-editor-bridge__hint{font-size:12px;line-height:1.6;color:rgba(245,247,255,.62)}';
        echo '@media (max-width:782px){.lcfa-editor-shell{right:12px;left:12px;bottom:12px}.lcfa-editor-launcher{width:100%;justify-content:center}.lcfa-editor-drawer{width:auto;left:0}.lcfa-editor-bridge__actions,.lcfa-editor-bridge__row{grid-template-columns:1fr}}';
        echo '</style>';
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
        $remote_status = $this->remote_client->get_status();
        $default_action = !empty($context['action']) ? (string) $context['action'] : 'site_audit';
        $command_base_url = $this->get_command_url([
            'post_id'   => $post->ID,
            'thread_id' => $current_thread_id,
        ]);
        $editor_config = [
            'restEndpoint' => rest_url('lcfa/v1/command/suggest'),
            'restNonce'    => wp_create_nonce('wp_rest'),
            'commandBaseUrl' => $command_base_url,
            'postId'       => (int) $post->ID,
            'targetId'     => (int) ($context['target_id'] ?? 0),
            'variant'      => (string) ($context['variant'] ?? '1'),
            'defaultAction'=> $default_action,
            'threadId'     => $current_thread_id,
            'labels'       => [
                'requestRequired' => __('Write a request first so Forge AI can suggest an action.', 'livecanvas-forge-ai'),
                'analysisFailed'  => __('The request analysis failed.', 'livecanvas-forge-ai'),
                'whySuggested'    => __('Why this was suggested', 'livecanvas-forge-ai'),
                'warnings'        => __('Warnings', 'livecanvas-forge-ai'),
                'recommendedWorkflow' => __('Recommended workflow', 'livecanvas-forge-ai'),
                'recommendedPreflight' => __('Recommended preflight payload', 'livecanvas-forge-ai'),
                'openDeck'        => __('Open suggested payload in Command Deck', 'livecanvas-forge-ai'),
                'analyzing'       => __('Analyzing request...', 'livecanvas-forge-ai'),
            ],
        ];

        echo '<div class="lcfa-editor-shell" data-lcfa-editor-shell>';
        echo '<button type="button" class="lcfa-editor-launcher" data-lcfa-editor-open>';
        echo $this->get_icon_svg('stars');
        echo '<span>' . esc_html__('Open Forge AI', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<aside class="lcfa-editor-drawer" aria-hidden="true">';
        echo '<div class="lcfa-editor-bridge__head">';
        echo '<div>';
        echo '<span class="lcfa-editor-bridge__eyebrow">' . esc_html__('Forge AI bridge', 'livecanvas-forge-ai') . '</span>';
        echo '<p class="lcfa-editor-bridge__title">' . esc_html(get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai')) . '</p>';
        echo '</div>';
        echo '<button type="button" class="lcfa-editor-bridge__close" data-lcfa-editor-close aria-label="' . esc_attr__('Close Forge AI panel', 'livecanvas-forge-ai') . '">' . $this->get_icon_svg('x-circle') . '</button>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__stack">';
        echo '<span class="lcfa-editor-bridge__pill">' . esc_html(strtoupper((string) ($snapshot['detected_framework'] ?: 'unknown'))) . '</span>';
        echo '<span class="lcfa-editor-bridge__pill">' . esc_html(strtoupper((string) ($snapshot['site_mode'] ?: 'local'))) . '</span>';
        echo '<span class="lcfa-editor-bridge__pill' . (!empty($snapshot['windpress_active']) ? '' : ' is-secondary') . '">' . esc_html(!empty($snapshot['windpress_active']) ? __('WindPress active', 'livecanvas-forge-ai') : __('WindPress inactive', 'livecanvas-forge-ai')) . '</span>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Current target', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__meta">' . esc_html($this->get_editor_target_summary($post, $context)) . '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Prompt composer', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__hint">' . esc_html__('Analyze a natural-language request against the current LiveCanvas target, then open the full Command Deck with the suggested payload already filled in.', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__controls" data-lcfa-editor-composer>';
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
        echo '<label class="lcfa-editor-bridge__field">';
        echo '<span>' . esc_html__('Request', 'livecanvas-forge-ai') . '</span>';
        echo '<textarea data-lcfa-editor-prompt placeholder="' . esc_attr__('Example: refresh this header with a simpler navigation and keep the current logo.', 'livecanvas-forge-ai') . '"></textarea>';
        echo '</label>';
        echo '<div class="lcfa-editor-bridge__actions">';
        echo '<button type="button" class="lcfa-editor-bridge__button is-primary" data-lcfa-editor-analyze>';
        echo $this->get_icon_svg('stars');
        echo '<span>' . esc_html__('Analyze request', 'livecanvas-forge-ai') . '</span>';
        echo '</button>';
        echo '<a class="lcfa-editor-bridge__button is-neutral" href="' . esc_url($command_base_url) . '" target="_blank" rel="noreferrer noopener" data-lcfa-editor-open-deck>';
        echo $this->get_icon_svg('command');
        echo '<span>' . esc_html__('Open Command Deck', 'livecanvas-forge-ai') . '</span>';
        echo '</a>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__result" data-lcfa-editor-result aria-live="polite">';
        echo '<div class="lcfa-editor-bridge__status" data-lcfa-editor-result-summary></div>';
        echo '<div class="lcfa-editor-bridge__result-meta" data-lcfa-editor-result-meta></div>';
        echo '<div data-lcfa-editor-result-reasons-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Why this was suggested', 'livecanvas-forge-ai') . '</div><ul class="lcfa-editor-bridge__list" data-lcfa-editor-result-reasons></ul></div>';
        echo '<div data-lcfa-editor-result-warnings-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Warnings', 'livecanvas-forge-ai') . '</div><ul class="lcfa-editor-bridge__list" data-lcfa-editor-result-warnings></ul></div>';
        echo '<div data-lcfa-editor-result-workflow-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Recommended workflow', 'livecanvas-forge-ai') . '</div><ol class="lcfa-editor-bridge__list" data-lcfa-editor-result-workflow></ol></div>';
        echo '<div data-lcfa-editor-result-preflight-wrap hidden><div class="lcfa-editor-bridge__label">' . esc_html__('Recommended preflight payload', 'livecanvas-forge-ai') . '</div><pre class="lcfa-editor-bridge__code" data-lcfa-editor-result-preflight></pre></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Quick actions', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__actions">';
        foreach ($actions as $action) {
            echo '<a class="lcfa-editor-bridge__button is-' . esc_attr($action['tone']) . '" href="' . esc_url($action['url']) . '" target="_blank" rel="noreferrer noopener">';
            echo $this->get_icon_svg($action['icon']);
            echo '<span>' . esc_html($action['label']) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="lcfa-editor-bridge__section">';
        echo '<div class="lcfa-editor-bridge__label">' . esc_html__('Workflow note', 'livecanvas-forge-ai') . '</div>';
        echo '<div class="lcfa-editor-bridge__hint">' . esc_html__('Use the current target action for content changes. Use WindPress audit when you are working on Picowind styles, cache, or theme.json.', 'livecanvas-forge-ai') . '</div>';
        echo '</div>';
        echo '</aside>';
        echo '</div>';
        echo '<script type="application/json" data-lcfa-editor-config>' . wp_json_encode($editor_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        $editor_bridge_script = <<<'JS'
(function(){
  var shell=document.querySelector("[data-lcfa-editor-shell]");
  if(!shell||shell.dataset.bound==="1"){return;}
  shell.dataset.bound="1";
  var drawer=shell.querySelector(".lcfa-editor-drawer");
  var openBtn=shell.querySelector("[data-lcfa-editor-open]");
  var closeBtn=shell.querySelector("[data-lcfa-editor-close]");
  var configNode=shell.querySelector("[data-lcfa-editor-config]");
  var config={};
  try{config=configNode?JSON.parse(configNode.textContent||"{}"):{};}catch(error){config={};}
  var threadSelect=shell.querySelector("[data-lcfa-editor-thread]");
  var targetSelect=shell.querySelector("[data-lcfa-editor-target]");
  var promptInput=shell.querySelector("[data-lcfa-editor-prompt]");
  var analyzeButton=shell.querySelector("[data-lcfa-editor-analyze]");
  var openDeckLink=shell.querySelector("[data-lcfa-editor-open-deck]");
  var resultBox=shell.querySelector("[data-lcfa-editor-result]");
  var resultSummary=shell.querySelector("[data-lcfa-editor-result-summary]");
  var resultMeta=shell.querySelector("[data-lcfa-editor-result-meta]");
  var reasonsWrap=shell.querySelector("[data-lcfa-editor-result-reasons-wrap]");
  var reasonsList=shell.querySelector("[data-lcfa-editor-result-reasons]");
  var warningsWrap=shell.querySelector("[data-lcfa-editor-result-warnings-wrap]");
  var warningsList=shell.querySelector("[data-lcfa-editor-result-warnings]");
  var workflowWrap=shell.querySelector("[data-lcfa-editor-result-workflow-wrap]");
  var workflowList=shell.querySelector("[data-lcfa-editor-result-workflow]");
  var preflightWrap=shell.querySelector("[data-lcfa-editor-result-preflight-wrap]");
  var preflightNode=shell.querySelector("[data-lcfa-editor-result-preflight]");
  var setOpen=function(next){shell.classList.toggle("is-open",next);if(drawer){drawer.setAttribute("aria-hidden",next?"false":"true");}};
  var updateDeckLink=function(payload){
    if(!openDeckLink||!config.commandBaseUrl){return;}
    var url=new URL(config.commandBaseUrl,window.location.origin);
    var args=payload||{};
    var threadId=threadSelect&&threadSelect.value?threadSelect.value:(config.threadId||"default");
    url.searchParams.set("thread_id",threadId);
    if(config.postId){url.searchParams.set("post_id",String(config.postId));}
    if(promptInput&&promptInput.value.trim()!==""){url.searchParams.set("user_prompt",promptInput.value.trim());}
    if(args.action){url.searchParams.set("suggest_action",String(args.action));}
    if(args.execution_target){url.searchParams.set("execution_target",String(args.execution_target));}
    if(args.target_id){url.searchParams.set("target_id",String(args.target_id));}
    if(args.variant){url.searchParams.set("variant",String(args.variant));}
    if(args.title){url.searchParams.set("title",String(args.title));}
    if(args.slug){url.searchParams.set("slug",String(args.slug));}
    if(args.provider_id){url.searchParams.set("provider_id",String(args.provider_id));}
    if(args.relative_path){url.searchParams.set("relative_path",String(args.relative_path));}
    if(args.root_scope){url.searchParams.set("root_scope",String(args.root_scope));}
    if(args.file_path){url.searchParams.set("file_path",String(args.file_path));}
    if(args.backup_id){url.searchParams.set("backup_id",String(args.backup_id));}
    if(args.status){url.searchParams.set("status",String(args.status));}
    openDeckLink.href=url.toString();
  };
  var renderList=function(listNode,wrapNode,items){
    if(!listNode||!wrapNode){return;}
    listNode.innerHTML="";
    if(!Array.isArray(items)||items.length===0){wrapNode.hidden=true;return;}
    items.forEach(function(item){
      var li=document.createElement("li");
      li.textContent=String(item||"");
      listNode.appendChild(li);
    });
    wrapNode.hidden=false;
  };
  var renderWorkflow=function(items){
    if(!workflowList||!workflowWrap){return;}
    workflowList.innerHTML="";
    if(!Array.isArray(items)||items.length===0){workflowWrap.hidden=true;return;}
    items.forEach(function(item){
      var li=document.createElement("li");
      var parts=[];
      if(item&&item.phase){parts.push(String(item.phase).charAt(0).toUpperCase()+String(item.phase).slice(1));}
      if(item&&item.action){parts.push(String(item.action));}
      if(item&&item.execution_target){parts.push("execution "+String(item.execution_target));}
      li.textContent=parts.join(" · ");
      workflowList.appendChild(li);
    });
    workflowWrap.hidden=false;
  };
  var renderJson=function(node,wrapNode,value){
    if(!node||!wrapNode){return;}
    if(!value||typeof value!=="object"){node.textContent="";wrapNode.hidden=true;return;}
    try{
      node.textContent=JSON.stringify(value,null,2);
      wrapNode.hidden=false;
    }catch(error){
      node.textContent="";
      wrapNode.hidden=true;
    }
  };
  var renderSuggestion=function(payload,isError){
    if(!resultBox||!resultSummary||!resultMeta){return;}
    resultBox.classList.add("is-visible");
    resultBox.classList.toggle("is-error",Boolean(isError));
    resultMeta.innerHTML="";
    var summary=payload&&payload.summary?payload.summary:(payload&&payload.message?payload.message:"");
    resultSummary.textContent=summary;
    if(payload&&payload.suggested_payload&&payload.suggested_payload.action){
      [{label:"Action",value:payload.suggested_payload.action},{label:"Confidence",value:payload.confidence||""},{label:"Execution",value:payload.suggested_payload.execution_target||""}].forEach(function(entry){
        if(!entry.value){return;}
        var chip=document.createElement("span");
        chip.className="lcfa-editor-bridge__chip";
        chip.textContent=entry.label+": "+entry.value;
        resultMeta.appendChild(chip);
      });
      updateDeckLink(payload.suggested_payload);
    }
    renderList(reasonsList,reasonsWrap,payload&&Array.isArray(payload.reasons)?payload.reasons:[]);
    renderList(warningsList,warningsWrap,payload&&Array.isArray(payload.warnings)?payload.warnings:[]);
    renderWorkflow(payload&&Array.isArray(payload.workflow)?payload.workflow:[]);
    renderJson(preflightNode,preflightWrap,payload&&payload.preflight&&typeof payload.preflight==="object"?payload.preflight:null);
  };
  var setBusy=function(next){
    if(!analyzeButton){return;}
    analyzeButton.disabled=next;
    var label=analyzeButton.querySelector("span");
    if(label){label.textContent=next?(config.labels&&config.labels.analyzing?config.labels.analyzing:"Analyzing request..."):"Analyze request";}
  };
  var analyzeRequest=function(){
    if(!promptInput){return;}
    var prompt=promptInput.value.trim();
    if(prompt===""){
      renderSuggestion({message:(config.labels&&config.labels.requestRequired)||"Write a request first so Forge AI can suggest an action."},true);
      return;
    }
    setBusy(true);
    fetch(config.restEndpoint,{
      method:"POST",
      credentials:"same-origin",
      headers:{"Content-Type":"application/json","X-WP-Nonce":config.restNonce||""},
      body:JSON.stringify({
        user_prompt:prompt,
        execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",
        context_post_id:config.postId||0,
        post_id:config.postId||0,
        target_id:config.targetId||0,
        variant:config.variant||"1",
        action:config.defaultAction||"site_audit"
      })
    }).then(function(response){
      return response.text().then(function(text){
        var data={};
        try{data=text?JSON.parse(text):{};}catch(error){data={};}
        if(!response.ok){
          var errorMessage=(data&&data.suggestion&&data.suggestion.message)||(data&&data.error)||(config.labels&&config.labels.analysisFailed)||"The request analysis failed.";
          throw new Error(errorMessage);
        }
        return data;
      });
    }).then(function(data){
      renderSuggestion(data&&data.suggestion?data.suggestion:{message:(config.labels&&config.labels.analysisFailed)||"The request analysis failed."},false);
    }).catch(function(error){
      renderSuggestion({message:error&&error.message?error.message:((config.labels&&config.labels.analysisFailed)||"The request analysis failed.")},true);
    }).finally(function(){setBusy(false);});
  };
  if(openBtn){openBtn.addEventListener("click",function(){setOpen(true);});}
  if(closeBtn){closeBtn.addEventListener("click",function(){setOpen(false);});}
  if(analyzeButton){analyzeButton.addEventListener("click",analyzeRequest);}
  if(promptInput){
    promptInput.addEventListener("keydown",function(event){
      if((event.metaKey||event.ctrlKey)&&event.key==="Enter"){event.preventDefault();analyzeRequest();}
    });
  }
  if(threadSelect){
    threadSelect.addEventListener("change",function(){
      updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",target_id:config.targetId||0,variant:config.variant||"1"});
    });
  }
  if(targetSelect){
    targetSelect.addEventListener("change",function(){
      updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect.value,target_id:config.targetId||0,variant:config.variant||"1"});
    });
  }
  updateDeckLink({action:config.defaultAction||"site_audit",execution_target:targetSelect&&targetSelect.value?targetSelect.value:"local",target_id:config.targetId||0,variant:config.variant||"1"});
  document.addEventListener("keydown",function(event){if(event.key==="Escape"){setOpen(false);}});
  document.addEventListener("click",function(event){if(!shell.classList.contains("is-open")){return;}if(shell.contains(event.target)){return;}setOpen(false);});
})();
JS;
        echo '<script id="lcfa-editor-bridge-script">' . $editor_bridge_script . '</script>';
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

                $settings['last_completed_step'] = $this->environment->is_livecanvas_active() ? max(1, (int) $settings['last_completed_step']) : 0;
                LCFA_Settings::update($settings);
                $this->redirect_to_step($this->environment->is_livecanvas_active() ? 2 : 1);
                break;

            case 2:
                $framework = sanitize_key($_POST['framework'] ?? '');

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

                LCFA_Settings::set_notice(
                    sprintf(
                        __('Framework confirmed: %1$s. Active theme: %2$s.', 'livecanvas-forge-ai'),
                        $framework,
                        $result['theme_stylesheet']
                    )
                );

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

                if (!in_array($ai_tool, ['codex', 'opencode', 'claude-code', 'cursor', 'other'], true)) {
                    LCFA_Settings::set_notice(__('Select a valid AI client.', 'livecanvas-forge-ai'), 'error');
                    $this->redirect_to_step(4);
                }

                LCFA_Settings::patch([
                    'ai_tool'             => $ai_tool,
                    'last_completed_step' => max(4, (int) $settings['last_completed_step']),
                ]);
                LCFA_Settings::update_connections(array_merge(LCFA_Settings::get_connections(), [
                    'preferred_client' => $ai_tool,
                ]));

                LCFA_Settings::set_notice(__('AI client saved.', 'livecanvas-forge-ai'));
                $this->redirect_to_step(5);
                break;

            case 5:
                $permission_profile  = sanitize_key($_POST['permission_profile'] ?? 'draft_preview');
                $allow_file_fallback = !empty($_POST['allow_file_fallback']);

                if (!in_array($permission_profile, ['read_only', 'draft_preview', 'confirmed_apply', 'advanced_templates'], true)) {
                    LCFA_Settings::set_notice(__('Select a valid permission profile.', 'livecanvas-forge-ai'), 'error');
                    $this->redirect_to_step(5);
                }

                LCFA_Settings::patch([
                    'permission_profile'  => $permission_profile,
                    'allow_file_fallback' => $allow_file_fallback,
                    'last_completed_step' => max(5, (int) $settings['last_completed_step']),
                ]);

                LCFA_Settings::set_notice(__('Operational policy saved.', 'livecanvas-forge-ai'));
                $this->redirect_to_step(6);
                break;

            case 6:
                LCFA_Settings::patch([
                    'completed'           => true,
                    'last_completed_step' => 6,
                ]);

                LCFA_Settings::set_notice(__('Forge Setup is complete. You can now move into the operational dashboard.', 'livecanvas-forge-ai'));
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
        if (in_array($preferred_client, ['codex', 'opencode', 'claude-code', 'cursor', 'other', 'generic'], true)) {
            LCFA_Settings::patch([
                'ai_tool' => $preferred_client === 'generic' ? 'other' : $preferred_client,
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
        $connections['connection_mode'] = (string) $bundle['mode'];
        $connections['workspace_root'] = sanitize_text_field($workspace_root !== '' ? $workspace_root : (string) $connections['workspace_root']);
        $connections['connection_last_bundle_hash'] = md5((string) ($target['content'] ?? ''));
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
        $connections['connection_mode'] = (string) ($bundle['mode'] ?? $connections['connection_mode']);
        $connections['workspace_root'] = sanitize_text_field((string) ($bundle['workspace_root'] ?? $connections['workspace_root']));
        $connections['connection_last_bundle_hash'] = md5((string) ($file['content'] ?? ''));
        $connections['connection_current_step'] = 'smoke_test';
        LCFA_Settings::update_connections($connections);

        nocache_headers();
        header('Content-Type: ' . ((string) ($file['mime'] ?? 'text/plain')) . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) ($file['name'] ?? 'bundle.txt')) . '"');
        echo (string) ($file['content'] ?? '');
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

    private function build_selected_connection_bundle(array $request): array {
        $settings      = LCFA_Settings::get();
        $connections   = LCFA_Settings::get_connections();
        $client_key    = $this->normalize_connection_client((string) ($request['preferred_client'] ?? ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex'))));
        $mode          = $this->normalize_connection_mode((string) ($request['connection_mode'] ?? ($connections['connection_mode'] ?: 'local')));
        $workspace_root = trim((string) ($request['workspace_root'] ?? $connections['workspace_root']));

        if ($mode === 'local') {
            $snapshot      = $this->environment->get_snapshot();
            $mcp_bootstrap = $this->get_lightweight_bootstrap_payload($connections, $snapshot);
            $client_payload = is_array($mcp_bootstrap['clients'][$client_key] ?? null)
                ? $mcp_bootstrap['clients'][$client_key]
                : (is_array($mcp_bootstrap['clients']['codex'] ?? null) ? $mcp_bootstrap['clients']['codex'] : ['command' => '', 'env' => []]);

            return $this->connection_onboarding->build_bundle([
                'client'         => $client_key,
                'mode'           => $mode,
                'workspace_root' => $workspace_root,
                'common'         => is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [],
                'client_payload' => [
                    'command' => (string) ($client_payload['command'] ?? ''),
                    'env'     => (array) ($client_payload['env'] ?? []),
                ],
            ]);
        }

        $mcp_bootstrap = $this->context_builder->get_bootstrap_payload();
        $remote_status = $this->remote_client->get_status();
        $client_payload = is_array($mcp_bootstrap['clients'][$client_key] ?? null)
            ? $mcp_bootstrap['clients'][$client_key]
            : (is_array($mcp_bootstrap['clients']['codex'] ?? null) ? $mcp_bootstrap['clients']['codex'] : ['command' => '', 'env' => []]);

        if ($mode === 'remote') {
            $remote_rest_base = (string) ($remote_status['mcp']['rest_base'] ?? ($remote_status['endpoint'] ?? ''));
            $remote_token = (string) ($remote_status['mcp']['token'] ?? '');

            $client_payload['env'] = array_values(array_filter([
                $remote_rest_base !== '' ? 'LCFA_REST_BASE=' . $remote_rest_base : '',
                $remote_token !== '' ? 'LCFA_MCP_TOKEN=' . $remote_token : '',
            ]));
        }

        return $this->connection_onboarding->build_bundle([
            'client'         => $client_key,
            'mode'           => $mode,
            'workspace_root' => $workspace_root,
            'common'         => is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [],
            'client_payload' => [
                'command' => (string) ($client_payload['command'] ?? ''),
                'env'     => (array) ($client_payload['env'] ?? []),
            ],
        ]);
    }

    private function normalize_connection_client(string $client): string {
        $client = sanitize_key($client);

        if ($client === 'other') {
            return 'generic';
        }

        return in_array($client, ['codex', 'opencode', 'claude-code', 'cursor', 'generic'], true)
            ? $client
            : 'codex';
    }

    private function normalize_connection_mode(string $mode): string {
        return $mode === 'remote' ? 'remote' : 'local';
    }

    private function get_lightweight_bootstrap_payload(array $connections, array $snapshot): array {
        $site_url = trailingslashit(home_url('/'));
        $rest_base = trailingslashit(rest_url('lcfa/v1/'));
        $mcp_endpoint = LCFA_Settings::get_mcp_endpoint();
        $mcp_token = (string) ($connections['mcp_token'] ?? '');
        $wp_root = defined('ABSPATH') && is_string(ABSPATH) ? untrailingslashit((string) ABSPATH) : '';
        $filesystem_mode = ($snapshot['site_mode'] ?? 'local') === 'local' ? 'local-theme-access' : 'remote-rest-primary';
        $local_mcp_command = 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';
        $common = [
            'site_url'       => $site_url,
            'rest_base'      => $rest_base,
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

        return [
            'common' => $common,
            'clients' => [
                'codex' => [
                    'label'   => 'Codex',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio')),
                    'env'     => array_merge([
                        'LCFA_SITE_URL=' . $site_url,
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_MCP_ENDPOINT=' . $mcp_endpoint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'opencode' => [
                    'label'   => 'OpenCode',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=opencode')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'claude-code' => [
                    'label'   => 'Claude Code',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=claude')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_MCP_ENDPOINT=' . $mcp_endpoint,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
                'cursor' => [
                    'label'   => 'Cursor',
                    'command' => (string) ($connections['mcp_server_command'] ?: ($local_mcp_command . ' --transport=stdio --agent=cursor')),
                    'env'     => array_merge([
                        'LCFA_REST_BASE=' . $rest_base,
                        'LCFA_MCP_TOKEN=' . $mcp_token,
                    ], $filesystem_env),
                ],
            ],
        ];
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

        LCFA_Settings::update(LCFA_Settings::defaults());
        LCFA_Settings::set_notice(__('Forge Setup has been reset. Connections, history, and the project brief were preserved.', 'livecanvas-forge-ai'), 'success');

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

        $thread_id   = LCFA_Settings::normalize_thread_id((string) ($_POST['thread_id'] ?? 'default'));
        $genesis_task_id = sanitize_key((string) ($_POST['genesis_task_id'] ?? ''));
        $user_prompt = sanitize_textarea_field((string) ($_POST['user_prompt'] ?? ''));

        if (!empty($_POST['analyze_request'])) {
            $suggestion = $this->prompt_suggester->suggest($_POST);
            LCFA_Settings::set_command_suggestion($suggestion);

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

            if (!empty($_POST['context_post_id'])) {
                $redirect_args['post_id'] = absint($_POST['context_post_id']);
            }

            if (!empty($suggestion['ok']) && is_array($suggestion['suggested_payload'] ?? null)) {
                $redirect_args = array_merge($redirect_args, $this->build_command_redirect_args((array) $suggestion['suggested_payload']));
            }

            wp_safe_redirect($this->get_command_url($redirect_args));
            exit;
        }

        if ($user_prompt !== '') {
            LCFA_Settings::append_thread_message($thread_id, [
                'role'    => 'user',
                'label'   => __('Request', 'livecanvas-forge-ai'),
                'content' => $user_prompt,
                'meta'    => [
                    'action'           => sanitize_key((string) ($_POST['action'] ?? '')),
                    'execution_target' => sanitize_key((string) ($_POST['execution_target'] ?? 'local')),
                    'dry_run'          => !empty($_POST['dry_run']),
                    'genesis_task_id'  => $genesis_task_id,
                ],
            ]);
        }

        $result = $this->command_deck->execute($_POST);
        LCFA_Settings::set_command_result($result);
        LCFA_Settings::append_thread_message($thread_id, $this->build_thread_result_message($result, $_POST));

        if ($genesis_task_id !== '') {
            LCFA_Settings::update_genesis_task_progress($genesis_task_id, [
                'status'       => !empty($result['ok']) ? ($result['mode'] === 'preview' ? 'previewed' : 'applied') : 'failed',
                'updated_at'   => current_time('mysql', true),
                'thread_id'    => $thread_id,
                'action'       => (string) ($result['action'] ?? sanitize_key((string) ($_POST['action'] ?? ''))),
                'mode'         => (string) ($result['mode'] ?? (!empty($_POST['dry_run']) ? 'preview' : 'apply')),
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

        wp_safe_redirect($this->get_command_url([
            'thread_id' => $thread_id,
        ]));
        exit;
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
        $connections['preferred_client'] = '';
        $connections['connection_status'] = '';
        $connections['connection_last_verified_at'] = '';
        $connections['connection_last_error'] = '';
        $connections['connection_current_step'] = 'choose_client';

        return $connections;
    }

    private function get_dashboard_hero_content(string $tab): array {
        $hero = [
            'setup' => [
                'title'    => __('Forge Setup', 'livecanvas-forge-ai'),
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

        if (!in_array($tab, ['setup', 'genesis', 'connections', 'command'], true)) {
            $tab = $this->get_default_dashboard_tab($settings);
        }

        echo '<div class="wrap lcfa-admin">';
        $this->render_page_header($tab, $snapshot, $settings);

        $this->render_notice($notice);
        $this->render_internal_tabs($tab, $settings);

        if (!$settings['completed'] && $tab !== 'setup') {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Forge Setup is not complete yet. Finish the setup flow before using the operational dashboard.', 'livecanvas-forge-ai');
            echo ' <a href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=setup')) . '">' . esc_html__('Open Forge Setup', 'livecanvas-forge-ai') . '</a>';
            echo '</p></div>';
        }

        switch ($tab) {
            case 'setup':
                $this->render_setup_tab($settings, $snapshot);
                break;

            case 'connections':
                $this->render_connections_tab($settings, $snapshot);
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

        if ((int) $settings['last_completed_step'] > 0 || !empty($settings['completed'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_reset_setup');
            echo '<input type="hidden" name="action" value="lcfa_reset_setup">';
            echo '<button class="button" type="submit">' . esc_html__('Restart Forge Setup', 'livecanvas-forge-ai') . '</button>';
            echo '</form>';
        }

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

        echo '</div>';
    }

    private function render_genesis_tab(array $settings, array $snapshot, array $brief, array $summary, array $plan, array $progress, string $brief_hash): void {
        $plan_pages      = is_array($plan['pages'] ?? null) ? $plan['pages'] : [];
        $plan_tasks      = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $plan_counts     = is_array($plan['counts'] ?? null) ? $plan['counts'] : [];
        $plan_stack      = is_array($plan['stack'] ?? null) ? $plan['stack'] : [];
        $progress_tasks  = is_array($progress['tasks'] ?? null) ? $progress['tasks'] : [];
        $plan_available  = !empty($plan);
        $plan_stale      = $plan_available && (
            (string) ($plan['brief_hash'] ?? '') !== $brief_hash
            || (string) ($plan_stack['framework'] ?? '') !== (string) ($snapshot['detected_framework'] ?? '')
            || (string) ($plan_stack['theme'] ?? '') !== (string) ($snapshot['current_theme_stylesheet'] ?? '')
            || (string) ($plan_stack['site_mode'] ?? '') !== (string) ($snapshot['site_mode'] ?? '')
        );
        $next_task       = !$plan_stale ? $this->get_next_genesis_task($plan_tasks, $progress_tasks) : null;

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
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Previewed: %d', 'livecanvas-forge-ai'), $this->count_genesis_tasks_by_status($progress_tasks, 'previewed'))) . '</span>';
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Completed: %d', 'livecanvas-forge-ai'), $this->count_genesis_tasks_by_status($progress_tasks, 'applied'))) . '</span>';
            echo '<span class="lcfa-chip' . ($this->count_genesis_tasks_by_status($progress_tasks, 'failed') > 0 ? ' is-negative' : '') . '">' . esc_html(sprintf(__('Failed: %d', 'livecanvas-forge-ai'), $this->count_genesis_tasks_by_status($progress_tasks, 'failed'))) . '</span>';
        }
        echo '</div>';

        if ($plan_stale) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('The project brief changed after the last plan generation. Regenerate the plan before using task deep-links.', 'livecanvas-forge-ai') . '</p></div>';
        } elseif (!$plan_available) {
            echo '<p>' . esc_html__('No build plan has been generated yet. Generate one from the current brief to get page suggestions and task deep-links.', 'livecanvas-forge-ai') . '</p>';
        } elseif (!empty($plan['generated_at'])) {
            echo '<p>' . esc_html(sprintf(__('Last generated at %s.', 'livecanvas-forge-ai'), get_date_from_gmt((string) $plan['generated_at'], get_option('date_format') . ' ' . get_option('time_format')))) . '</p>';
        }

        echo '<div class="lcfa-cta-row">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_generate_plan');
        echo '<input type="hidden" name="action" value="lcfa_generate_plan">';
        echo '<button class="button button-primary" type="submit">' . esc_html($plan_available ? __('Regenerate build plan', 'livecanvas-forge-ai') : __('Generate build plan', 'livecanvas-forge-ai')) . '</button>';
        echo '</form>';
        if (is_array($next_task)) {
            $next_task_url = $this->get_genesis_task_command_url($next_task);
            if ($next_task_url !== '') {
                echo '<a class="button button-primary" href="' . esc_url($next_task_url) . '">' . esc_html__('Continue next task', 'livecanvas-forge-ai') . '</a>';
            }
        }
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=command')) . '">' . esc_html__('Open Command Deck', 'livecanvas-forge-ai') . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections')) . '">' . esc_html__('Open Connections', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';

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
            if ($task_url !== '') {
                echo '<div class="lcfa-cta-row">';
                echo '<a class="button button-small button-primary" href="' . esc_url($task_url) . '">' . esc_html__('Load in Command Deck', 'livecanvas-forge-ai') . '</a>';
                echo '</div>';
            }
            echo '</article>';
        }

        echo '</div>';
        echo '</div>';
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
        $connection_test  = LCFA_Settings::consume_connection_test_result();
        $preferred_client = $this->normalize_connection_client((string) ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex')));
        $selected_mode    = $this->normalize_connection_mode((string) ($connections['connection_mode'] ?: 'local'));
        $is_local_mode    = $selected_mode === 'local';
        $remote_status    = $is_local_mode ? [] : $this->remote_client->get_status();
        $mcp_status       = $is_local_mode ? $this->get_deferred_mcp_status($snapshot) : $this->context_builder->get_mcp_status();
        $mcp_bootstrap    = $is_local_mode ? $this->get_lightweight_bootstrap_payload($connections, $snapshot) : $this->context_builder->get_bootstrap_payload();
        $bundle = $this->build_selected_connection_bundle([
            'preferred_client' => $preferred_client,
            'connection_mode'  => $selected_mode,
            'workspace_root'   => $connections['workspace_root'],
        ]);
        $onboarding_state = $this->connection_onboarding->derive_state($connections, [
            'local_ready'  => !empty($mcp_status['rest_base']),
            'remote_ready' => !empty($remote_status['available']),
        ]);
        $workspace_write_state = LCFA_Workspace_Access::inspect((string) ($bundle['workspace_root'] ?? ''));
        $wizard_view = $this->connection_wizard_presenter->build([
            'state'            => $onboarding_state,
            'bundle'           => $bundle,
            'workspace_access' => $workspace_write_state,
        ]);

        echo '<div class="lcfa-main">';

        $this->render_connection_test_result($connection_test);
        $this->render_connection_onboarding_hero($bundle, $onboarding_state, $mcp_status, $snapshot, $selected_mode);

        if (($onboarding_state['status'] ?? 'not_connected') === 'ready') {
            $this->render_connection_ready_card($wizard_view, $bundle, $connections, $workspace_write_state);
        } else {
            $this->render_connection_wizard($wizard_view, $bundle, $connections, $preferred_client, $selected_mode, $mcp_bootstrap, $settings, $snapshot, $mcp_status, $onboarding_state, $workspace_write_state);
        }

        $this->render_connections_secondary_panels();

        echo '</div>';
    }

    private function render_connection_onboarding_hero(array $bundle, array $state, array $mcp_status, array $snapshot, string $selected_mode): void {
        $local_bridge = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];
        $local_bridge_deferred = !empty($local_bridge['deferred']);
        $local_bridge_ready = !$local_bridge_deferred && !empty($local_bridge['available']);
        $local_bridge_label = $local_bridge_deferred
            ? __('Local MCP bridge status loading', 'livecanvas-forge-ai')
            : ($local_bridge_ready ? __('Local MCP bridge ready', 'livecanvas-forge-ai') : __('Local MCP bridge not ready', 'livecanvas-forge-ai'));

        echo '<section class="lcfa-card lcfa-onboarding-hero">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('stars');
        echo '<div><h2>' . esc_html__('Connect your coding agent', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Set up Codex, OpenCode, Cursor, Claude Code, or any generic MCP client from this page. The goal is to generate a working client bundle, verify it, and get to Ready without touching low-level transport settings unless you want to.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip lcfa-chip--agent">' . $this->get_agent_icon_markup((string) ($bundle['client'] ?? 'codex'), 'stars') . '<span>' . esc_html(sprintf(__('Client: %s', 'livecanvas-forge-ai'), ucfirst(str_replace('-', ' ', (string) ($bundle['client'] ?? 'codex'))))) . '</span></span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Mode: %s', 'livecanvas-forge-ai'), $selected_mode)) . '</span>';
        echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Framework: %s', 'livecanvas-forge-ai'), (string) ($snapshot['detected_framework'] ?? 'unknown'))) . '</span>';
        echo '<span class="lcfa-chip' . ($local_bridge_ready ? ' is-positive' : ($local_bridge_deferred ? '' : '')) . '">' . esc_html($local_bridge_label) . '</span>';
        echo '<span class="lcfa-chip' . (($state['status'] ?? '') === 'ready' ? ' is-positive' : (($state['status'] ?? '') === 'needs_attention' ? ' is-negative' : '')) . '">' . esc_html(ucfirst(str_replace('_', ' ', (string) ($state['status'] ?? 'not_connected')))) . '</span>';
        echo '</div>';
        if (!empty($state['message'])) {
            echo '<p class="lcfa-guide-copy">' . esc_html((string) $state['message']) . '</p>';
        }
        echo '</section>';
    }

    private function render_connection_wizard(array $wizard_view, array $bundle, array $connections, string $preferred_client, string $selected_mode, array $mcp_bootstrap, array $settings, array $snapshot, array $mcp_status, array $onboarding_state, array $workspace_write_state): void {
        $panel = is_array($wizard_view['active_panel'] ?? null) ? $wizard_view['active_panel'] : [];
        $banner = is_array($wizard_view['banner'] ?? null) ? $wizard_view['banner'] : [];
        $current_step = (string) ($onboarding_state['current_step'] ?? 'choose_client');

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Connection wizard', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Follow one step at a time. Forge AI will show the next action clearly and only unlock the next stage when the current one is done.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-wizard__intro">';
        $this->render_connection_now_alert($banner);
        $this->render_connection_stepper((array) ($wizard_view['steps'] ?? []));
        echo '</div>';
        $this->render_connection_active_step_panel($panel, $current_step, $bundle, $connections, $preferred_client, $selected_mode, $mcp_bootstrap, $settings, $snapshot, $mcp_status, $workspace_write_state);
        $this->render_connection_visual_help_strip($wizard_view);
        $this->render_agent_connection_guide($mcp_bootstrap, $settings, $snapshot, $preferred_client, $mcp_status, true);
        $this->render_connection_technical_summary($bundle, !empty($wizard_view['technical_summary']['expanded']));
        echo '</section>';
    }

    private function render_connections_secondary_panels(): void {
        echo '<div class="lcfa-connections-secondary" data-lcfa-connections-secondary-root>';
        echo '<section class="lcfa-card lcfa-connections-secondary__panel is-loading" data-lcfa-connections-panel="remote" aria-busy="true">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('cloud');
        echo '<div><h2>' . esc_html__('Remote site', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Loading remote companion status and credentials…', 'livecanvas-forge-ai') . '</p></div>';
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

    private function render_connection_now_alert(array $banner): void {
        if (empty($banner)) {
            return;
        }

        echo '<div class="lcfa-wizard__alert">';
        echo '<span class="lcfa-wizard__alert-eyebrow">' . esc_html((string) ($banner['eyebrow'] ?? __('What to do now', 'livecanvas-forge-ai'))) . '</span>';
        if (!empty($banner['title'])) {
            echo '<h3>' . esc_html((string) $banner['title']) . '</h3>';
        }
        if (!empty($banner['body'])) {
            echo '<p>' . esc_html((string) $banner['body']) . '</p>';
        }
        if (!empty($banner['next'])) {
            echo '<p class="lcfa-wizard__next">' . esc_html((string) $banner['next']) . '</p>';
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
            echo '<span class="lcfa-wizard__step-helper">' . esc_html((string) ($step['helper'] ?? '')) . '</span>';
            echo '<span class="lcfa-wizard__step-state">' . esc_html(ucfirst($state === 'done' ? __('Done', 'livecanvas-forge-ai') : ($state === 'active' ? __('Active', 'livecanvas-forge-ai') : __('Locked', 'livecanvas-forge-ai')))) . '</span>';
            echo '</li>';
        }
        echo '</ol>';
    }

    private function render_connection_active_step_panel(array $panel, string $current_step, array $bundle, array $connections, string $preferred_client, string $selected_mode, array $mcp_bootstrap, array $settings, array $snapshot, array $mcp_status, array $workspace_write_state): void {
        echo '<section class="lcfa-wizard__panel">';
        echo '<div class="lcfa-wizard__panel-head">';
        echo '<h3>' . esc_html((string) ($panel['title'] ?? __('Connection step', 'livecanvas-forge-ai'))) . '</h3>';
        if (!empty($panel['description'])) {
            echo '<p>' . esc_html((string) $panel['description']) . '</p>';
        }
        echo '</div>';

        switch ($current_step) {
            case 'choose_mode':
                $this->render_connection_choose_mode_form($preferred_client, $selected_mode, (string) ($bundle['workspace_root'] ?? ''), (string) ($panel['primary_cta']['label'] ?? __('Continue', 'livecanvas-forge-ai')));
                break;

            case 'confirm_details':
                $this->render_connection_confirm_details_form($bundle, $preferred_client, $selected_mode, (string) ($panel['primary_cta']['label'] ?? __('Confirm details', 'livecanvas-forge-ai')));
                break;

            case 'generate_bundle':
                $this->render_connection_generate_bundle_actions($bundle, $workspace_write_state, $panel);
                break;

            case 'smoke_test':
                $this->render_connection_smoke_test_form($selected_mode, (string) ($panel['primary_cta']['label'] ?? __('Run smoke test', 'livecanvas-forge-ai')));
                break;

            case 'choose_client':
            default:
                $this->render_connection_choose_client_form($preferred_client, $selected_mode, (string) ($bundle['workspace_root'] ?? ''), (string) ($panel['primary_cta']['label'] ?? __('Continue', 'livecanvas-forge-ai')));
                break;
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
        $this->render_radio('preferred_client', 'claude-code', __('Claude Code', 'livecanvas-forge-ai'), $preferred_client, 'cpu');
        $this->render_radio('preferred_client', 'cursor', __('Cursor', 'livecanvas-forge-ai'), $preferred_client, 'cursor');
        $this->render_radio('preferred_client', 'generic', __('Generic MCP client', 'livecanvas-forge-ai'), $preferred_client, 'plug');
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_choose_mode_form(string $preferred_client, string $selected_mode, string $workspace_root, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="choose_mode">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
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

    private function render_connection_confirm_details_form(array $bundle, string $preferred_client, string $selected_mode, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form lcfa-wizard">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';
        echo '<input type="hidden" name="connection_current_step" value="confirm_details">';
        echo '<input type="hidden" name="preferred_client" value="' . esc_attr($preferred_client) . '">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';

        echo '<div class="lcfa-wizard__details">';
        echo '<div><span>' . esc_html__('REST base', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) (($bundle['environment']['LCFA_REST_BASE'] ?? ''))) . '</code></div>';
        echo '<div><span>' . esc_html__('MCP token', 'livecanvas-forge-ai') . '</span><code>' . esc_html((string) (($bundle['environment']['LCFA_MCP_TOKEN'] ?? ''))) . '</code></div>';
        if ($selected_mode === 'local') {
            echo '<label><span>' . esc_html__('Local workspace root', 'livecanvas-forge-ai') . '</span><input type="text" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '" placeholder="/Users/you/project"></label>';
            echo '<p class="lcfa-guide-copy">' . esc_html__('This must be the real project path on your machine, not the runtime mount path used by Local or Docker.', 'livecanvas-forge-ai') . '</p>';
        } else {
            echo '<input type="hidden" name="workspace_root" value="">';
        }
        echo '</div>';

        echo '<div class="lcfa-cta-row">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '<button class="button" type="submit" name="rotate_mcp_token" value="1">' . esc_html__('Rotate MCP token', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
    }

    private function render_connection_generate_bundle_actions(array $bundle, array $workspace_write_state, array $panel): void {
        $show_workspace_install = !empty($bundle['workspace_files']) && !empty($workspace_write_state['available']);
        $secondary_ctas = is_array($panel['secondary_ctas'] ?? null) ? $panel['secondary_ctas'] : [];
        $primary_cta = is_array($panel['primary_cta'] ?? null) ? $panel['primary_cta'] : [];
        $primary_action = (string) ($primary_cta['action'] ?? '');
        $copy_text = (string) ($bundle['copy_command_string'] ?? ($bundle['command_string'] ?? ''));

        echo '<div class="lcfa-cta-row lcfa-cta-row--stacked">';
        if (($primary_cta['action'] ?? '') === 'copy_command' && $copy_text !== '') {
            echo '<button class="button button-primary" type="button" data-lcfa-copy-text="' . esc_attr($copy_text) . '" data-lcfa-copy-label="' . esc_attr((string) ($primary_cta['label'] ?? __('Copy command', 'livecanvas-forge-ai'))) . '" data-lcfa-copied-label="' . esc_attr(__('Copied', 'livecanvas-forge-ai')) . '">' . esc_html((string) ($primary_cta['label'] ?? __('Copy command', 'livecanvas-forge-ai'))) . '</button>';
        } elseif ($show_workspace_install && ($primary_cta['action'] ?? '') === 'install') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_install_client_bundle');
            echo '<input type="hidden" name="action" value="lcfa_install_client_bundle">';
            echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
            echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
            echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
            echo '<label class="lcfa-checkbox"><input type="checkbox" name="create_backup" value="1"> ' . esc_html__('Create backup before overwrite', 'livecanvas-forge-ai') . '</label>';
            echo '<button class="button button-primary" type="submit">' . esc_html((string) ($panel['primary_cta']['label'] ?? __('Write config in workspace', 'livecanvas-forge-ai'))) . '</button>';
            echo '</form>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
            wp_nonce_field('lcfa_download_client_bundle');
            echo '<input type="hidden" name="action" value="lcfa_download_client_bundle">';
            echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
            echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
            echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
            echo '<button class="button button-primary" type="submit">' . esc_html((string) ($panel['primary_cta']['label'] ?? __('Download client bundle', 'livecanvas-forge-ai'))) . '</button>';
            echo '</form>';
        }

        foreach ($secondary_ctas as $secondary_cta) {
            $action = (string) ($secondary_cta['action'] ?? '');
            $label = (string) ($secondary_cta['label'] ?? '');

            if ($action === $primary_action) {
                continue;
            }

            if ($action === 'download') {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
                wp_nonce_field('lcfa_download_client_bundle');
                echo '<input type="hidden" name="action" value="lcfa_download_client_bundle">';
                echo '<input type="hidden" name="preferred_client" value="' . esc_attr((string) ($bundle['client'] ?? 'codex')) . '">';
                echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($bundle['mode'] ?? 'local')) . '">';
                echo '<input type="hidden" name="workspace_root" value="' . esc_attr((string) ($bundle['workspace_root'] ?? '')) . '">';
                echo '<button class="button" type="submit">' . esc_html($label ?: __('Download client bundle', 'livecanvas-forge-ai')) . '</button>';
                echo '</form>';
            }

            if ($action === 'copy_command' && $copy_text !== '') {
                $copy_label = $label ?: __('Copy command', 'livecanvas-forge-ai');
                echo '<button class="button" type="button" data-lcfa-copy-text="' . esc_attr($copy_text) . '" data-lcfa-copy-label="' . esc_attr($copy_label) . '" data-lcfa-copied-label="' . esc_attr(__('Copied', 'livecanvas-forge-ai')) . '">' . esc_html($copy_label) . '</button>';
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

    private function render_connection_smoke_test_form(string $selected_mode, string $button_label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-form">';
        wp_nonce_field('lcfa_test_connections');
        echo '<input type="hidden" name="action" value="lcfa_test_connections">';
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr($selected_mode) . '">';
        echo '<button class="button button-primary" type="submit">' . esc_html($button_label) . '</button>';
        echo '</form>';
    }

    private function render_connection_visual_help_strip(array $wizard_view): void {
        $visual_help = is_array($wizard_view['visual_help'] ?? null) ? $wizard_view['visual_help'] : [];
        $items = is_array($visual_help['items'] ?? null) ? $visual_help['items'] : [];
        $client = sanitize_key((string) ($visual_help['client'] ?? ''));

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
        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Generated bundle', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-agent-guide__panel-grid">';

        if (!empty($bundle['shortcut_command'])) {
            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html((string) ($bundle['shortcut_title'] ?? __('Shortcut', 'livecanvas-forge-ai'))) . '</h3>';
            $this->render_code_block((string) $bundle['shortcut_command'], [
                'language'   => 'bash',
                'label'      => __('Shell', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy shortcut', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        echo '<div class="lcfa-agent-guide__window">';
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

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Server command', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($bundle['command_string'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy command', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Environment variables', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block($this->build_environment_block((array) ($bundle['environment'] ?? [])), [
            'language'   => 'bash',
            'label'      => __('Environment', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy env', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '<div class="lcfa-agent-guide__window">';
        echo '<h3>' . esc_html__('Smoke test', 'livecanvas-forge-ai') . '</h3>';
        $this->render_code_block((string) ($bundle['smoke_test_command'] ?? ''), [
            'language'   => 'bash',
            'label'      => __('Shell', 'livecanvas-forge-ai'),
            'copy_label' => __('Copy smoke test', 'livecanvas-forge-ai'),
        ]);
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private function render_remote_companion_card(array $remote_status): void {
        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('cloud');
        echo '<div><h2>' . esc_html__('Remote site', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Use this section when the coding agent should target another WordPress site running the Forge companion. The remote WordPress bridge uses Application Password auth and, when reachable, can also expose the remote MCP token for bundle generation.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-chip-row">';
        echo '<span class="lcfa-chip' . (!empty($remote_status['available']) ? ' is-positive' : (!empty($remote_status['configured']) ? ' is-negative' : '')) . '">' . esc_html(!empty($remote_status['available']) ? __('Remote ready', 'livecanvas-forge-ai') : (!empty($remote_status['configured']) ? __('Configured but failing', 'livecanvas-forge-ai') : __('Not configured', 'livecanvas-forge-ai'))) . '</span>';
        if (!empty($remote_status['snapshot']['framework'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Framework: %s', 'livecanvas-forge-ai'), (string) $remote_status['snapshot']['framework'])) . '</span>';
        }
        if (!empty($remote_status['mcp']['token'])) {
            echo '<span class="lcfa-chip is-positive">' . esc_html__('Remote MCP token available', 'livecanvas-forge-ai') . '</span>';
        }
        echo '</div>';
        echo '<p class="lcfa-guide-copy">' . esc_html((string) ($remote_status['message'] ?? '')) . '</p>';
        if (!empty($remote_status['endpoint'])) {
            echo '<p><code>' . esc_html((string) $remote_status['endpoint']) . '</code></p>';
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
        echo '<input type="hidden" name="connection_mode" value="' . esc_attr((string) ($connections['connection_mode'] ?: 'local')) . '">';
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
        echo '<label><span>' . esc_html__('MCP host', 'livecanvas-forge-ai') . '</span><input type="text" name="mcp_host" value="' . esc_attr($connections['mcp_host']) . '" placeholder="127.0.0.1"></label>';
        echo '<label><span>' . esc_html__('MCP port', 'livecanvas-forge-ai') . '</span><input type="text" name="mcp_port" value="' . esc_attr($connections['mcp_port']) . '" placeholder="7681"></label>';
        echo '<label><span>' . esc_html__('Remote site URL', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_site_url" value="' . esc_attr($connections['remote_site_url']) . '" placeholder="https://example.com"></label>';
        echo '<label><span>' . esc_html__('Remote username', 'livecanvas-forge-ai') . '</span><input type="text" name="remote_username" value="' . esc_attr($connections['remote_username']) . '"></label>';
        echo '<label><span>' . esc_html__('Remote application password', 'livecanvas-forge-ai') . '</span><input type="password" name="remote_application_password" value="" placeholder="' . esc_attr($connections['remote_application_password'] !== '' ? __('Stored. Leave blank to keep current value.', 'livecanvas-forge-ai') : __('xxxx xxxx xxxx xxxx xxxx xxxx', 'livecanvas-forge-ai')) . '"></label>';
        echo '<label><span>' . esc_html__('MCP server command', 'livecanvas-forge-ai') . '</span><textarea name="mcp_server_command" rows="4" placeholder="npx @livecanvas/forge-mcp">' . esc_textarea($connections['mcp_server_command']) . '</textarea></label>';

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
        echo '<li>' . esc_html__('Execution endpoints: /command/actions, /command/suggest, /command, /mcp/status, /mcp/local-status, /mcp/bootstrap.', 'livecanvas-forge-ai') . '</li>';
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
            : (isset($guides[$preferred_client]) ? $preferred_client : 'codex');
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
            $input_id = 'lcfa-agent-tab-' . sanitize_key($key);
            echo '<input class="lcfa-agent-guide__input" type="radio" name="lcfa_agent_tab" id="' . esc_attr($input_id) . '"' . checked($selected_key, $key, false) . '>';
        }

        echo '<div class="lcfa-agent-guide__tabs" role="tablist" aria-label="' . esc_attr__('Coding agent quickstart tabs', 'livecanvas-forge-ai') . '">';
        foreach ($guides as $key => $guide) {
            $input_id = 'lcfa-agent-tab-' . sanitize_key($key);
            echo '<label class="lcfa-agent-guide__tab" for="' . esc_attr($input_id) . '" role="tab">' . $this->get_agent_icon_markup($key, $this->get_client_fallback_icon($key), 'lcfa-agent-icon lcfa-agent-icon--tab') . '<span>' . esc_html($guide['label']) . '</span></label>';
        }
        echo '</div>';

        echo '<div class="lcfa-agent-guide__panels">';
        foreach ($guides as $key => $guide) {
            echo '<section class="lcfa-agent-guide__panel lcfa-agent-guide__panel--' . esc_attr(sanitize_html_class($key)) . '">';
            echo '<p class="lcfa-agent-guide__intro">' . esc_html($guide['intro']) . '</p>';
            echo '<div class="lcfa-agent-guide__panel-grid">';

            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html__('Step-by-step', 'livecanvas-forge-ai') . '</h3>';
            echo '<ol class="lcfa-agent-guide__steps">';
            foreach ($guide['steps'] as $step) {
                echo '<li>' . esc_html($step) . '</li>';
            }
            echo '</ol>';
            if (!empty($guide['note'])) {
                echo '<p class="lcfa-agent-guide__note">' . esc_html($guide['note']) . '</p>';
            }
            echo '</div>';

            if (!empty($guide['shortcut_title']) && !empty($guide['shortcut'])) {
                echo '<div class="lcfa-agent-guide__window">';
                echo '<h3>' . esc_html($guide['shortcut_title']) . '</h3>';
                $this->render_code_block($guide['shortcut'], [
                    'language'   => 'bash',
                    'label'      => __('Shell', 'livecanvas-forge-ai'),
                    'copy_label' => __('Copy shortcut', 'livecanvas-forge-ai'),
                ]);
                echo '</div>';
            }

            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html__('Server command', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block($guide['command'], [
                'language'   => 'bash',
                'label'      => __('Shell', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy command', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';

            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html__('Environment variables', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block($guide['environment'], [
                'language'   => 'bash',
                'label'      => __('Environment', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy env', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';

            echo '<div class="lcfa-agent-guide__window">';
            echo '<h3>' . esc_html__('Quick terminal test', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block($guide['test_command'], [
                'language'   => 'bash',
                'label'      => __('Shell', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy test command', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';

            echo '</div>';
            echo '</section>';
        }
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }

    private function get_agent_connection_guides(array $mcp_bootstrap, array $settings, array $snapshot): array {
        $clients = is_array($mcp_bootstrap['clients'] ?? null) ? $mcp_bootstrap['clients'] : [];
        $common  = is_array($mcp_bootstrap['common'] ?? null) ? $mcp_bootstrap['common'] : [];
        $is_local_filesystem = (string) ($common['filesystem_mode'] ?? '') === 'local-theme-access';
        $wp_root_note = $is_local_filesystem
            ? __('This site is detected as local, so keep LCFA_WP_ROOT in the environment if you want local file access and local build tools.', 'livecanvas-forge-ai')
            : __('This site is currently handled as remote. In most cases you can leave LCFA_WP_ROOT out of the client environment.', 'livecanvas-forge-ai');

        $codex = is_array($clients['codex'] ?? null) ? $clients['codex'] : ['command' => '', 'env' => []];
        $cursor = is_array($clients['cursor'] ?? null) ? $clients['cursor'] : $codex;
        $claude = is_array($clients['claude-code'] ?? null) ? $clients['claude-code'] : $codex;
        $opencode = is_array($clients['opencode'] ?? null) ? $clients['opencode'] : $codex;

        return [
            'codex' => [
                'label'          => __('Codex', 'livecanvas-forge-ai'),
                'intro'          => __('Use this if Codex is your main coding agent. The easiest path is to register the MCP server once, then let Codex call the plugin tools from the current workspace.', 'livecanvas-forge-ai'),
                'steps'          => [
                    __('Open a terminal in the same workspace as your WordPress project.', 'livecanvas-forge-ai'),
                    __('Run the Codex shortcut below. It auto-detects the embedded CLI in Codex.app, then falls back to a ~/.codex/config.toml snippet if needed.', 'livecanvas-forge-ai'),
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
                'intro'        => __('Use this if you want Cursor to discover the Forge tools through MCP. Add one local stdio MCP server and keep the command and environment exactly as shown.', 'livecanvas-forge-ai'),
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
            'claude-code' => [
                'label'        => __('Claude Code', 'livecanvas-forge-ai'),
                'intro'        => __('Use this if Claude Code is your main agent. The bridge still runs in stdio mode: the only difference is the client where you register it.', 'livecanvas-forge-ai'),
                'steps'        => [
                    __('Open the MCP settings in Claude Code.', 'livecanvas-forge-ai'),
                    __('Add a new stdio MCP server called livecanvas-forge.', 'livecanvas-forge-ai'),
                    __('Use the command and environment shown below without changing the token or the REST base.', 'livecanvas-forge-ai'),
                    __('Reconnect the server and run get_snapshot before asking Claude Code to write anything.', 'livecanvas-forge-ai'),
                ],
                'note'         => $wp_root_note,
                'command'      => (string) ($claude['command'] ?? ''),
                'environment'  => $this->build_environment_block((array) ($claude['env'] ?? [])),
                'test_command' => $this->build_manual_mcp_test_command((array) ($claude['env'] ?? [])),
            ],
            'opencode' => [
                'label'        => __('OpenCode', 'livecanvas-forge-ai'),
                'intro'        => __('Use this if OpenCode is your main terminal agent. You only need one local MCP entry that points to the Forge bridge.', 'livecanvas-forge-ai'),
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
        $lines[] = 'Add this MCP server to ~/.codex/config.toml, then reopen Codex:';
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
        $this->render_command_thread_panel($current_thread, $thread_summaries);

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
            (string) ($settings['permission_profile'] ?: 'draft_preview'),
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

        echo '<label><span>' . esc_html__('Request to Forge AI', 'livecanvas-forge-ai') . '</span><textarea name="user_prompt" rows="4" placeholder="' . esc_attr__('Describe the requested change, why it matters, or what the assistant should keep in mind for this run.', 'livecanvas-forge-ai') . '">' . esc_textarea($command_form['user_prompt']) . '</textarea></label>';

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
        $this->render_status_row(__('LiveCanvas plugin installed', 'livecanvas-forge-ai'), $snapshot['livecanvas_installed']);
        $this->render_status_row(__('LiveCanvas plugin active', 'livecanvas-forge-ai'), $snapshot['livecanvas_active']);
        $this->render_status_row(__('LiveCanvas admin menu detected', 'livecanvas-forge-ai'), !empty($snapshot['livecanvas_menu_slug']));
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
        } else {
            echo '<p>' . esc_html__('LiveCanvas is ready. Move to the framework confirmation step.', 'livecanvas-forge-ai') . '</p>';
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

        echo '<div class="lcfa-radio-group">';
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
        $site_mode = LCFA_Settings::get()['site_mode'] ?: 'local';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Step 4. AI client', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Choose the main client you want to use first. The setup guide adapts to the current site profile and connection strategy.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="4">';
        echo '<div class="lcfa-radio-group">';
        $this->render_radio('ai_tool', 'codex', __('Codex', 'livecanvas-forge-ai'), $selected, 'stars');
        $this->render_radio('ai_tool', 'opencode', __('OpenCode', 'livecanvas-forge-ai'), $selected, 'braces');
        $this->render_radio('ai_tool', 'claude-code', __('Claude Code', 'livecanvas-forge-ai'), $selected, 'cpu');
        $this->render_radio('ai_tool', 'cursor', __('Cursor', 'livecanvas-forge-ai'), $selected, 'cursor');
        $this->render_radio('ai_tool', 'other', __('Other compatible client', 'livecanvas-forge-ai'), $selected, 'plug');
        echo '</div>';
        echo '<button class="button button-primary">' . esc_html__('Save AI client', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        $guides = $this->get_tool_guides($site_mode);
        $guide  = $guides[$selected] ?? $guides['other'];

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
        $selected = $settings['permission_profile'] ?: 'draft_preview';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('shield-lock');
        echo '<div><h2>' . esc_html__('Step 5. Operational policy', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The companion should stay conservative by default. Define how far it can go before it must ask for confirmation.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="5">';

        echo '<div class="lcfa-radio-group">';
        $this->render_radio('permission_profile', 'read_only', __('Read only', 'livecanvas-forge-ai'), $selected, 'eye');
        $this->render_radio('permission_profile', 'draft_preview', __('Drafts and previews', 'livecanvas-forge-ai'), $selected, 'file-earmark');
        $this->render_radio('permission_profile', 'confirmed_apply', __('Apply with confirmation', 'livecanvas-forge-ai'), $selected, 'check2-square');
        $this->render_radio('permission_profile', 'advanced_templates', __('Advanced templates, headers, and footers', 'livecanvas-forge-ai'), $selected, 'window-stack');
        echo '</div>';

        echo '<label class="lcfa-checkbox"><input type="checkbox" name="allow_file_fallback" value="1"' . checked((bool) $settings['allow_file_fallback'], true, false) . '> ';
        echo esc_html__('Allow theme or PHP file fallbacks only as a last resort.', 'livecanvas-forge-ai') . '</label>';

        echo '<button class="button button-primary">' . esc_html__('Save policy', 'livecanvas-forge-ai') . '</button>';
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
        echo '<li>' . esc_html(sprintf(__('Permission profile: %s.', 'livecanvas-forge-ai'), $settings['permission_profile'])) . '</li>';
        echo '</ul>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('lcfa_setup');
        echo '<input type="hidden" name="action" value="lcfa_setup">';
        echo '<input type="hidden" name="step" value="6">';
        echo '<button class="button button-primary">' . esc_html__('Complete Forge Setup', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';
    }

    private function render_step_nav(int $current_step, array $settings, array $snapshot): void {
        $steps = [
            1 => ['label' => __('Preflight', 'livecanvas-forge-ai'), 'icon' => 'shield-check'],
            2 => ['label' => __('Framework', 'livecanvas-forge-ai'), 'icon' => 'layers'],
            3 => ['label' => __('Site', 'livecanvas-forge-ai'), 'icon' => 'globe'],
            4 => ['label' => __('Client', 'livecanvas-forge-ai'), 'icon' => 'command'],
            5 => ['label' => __('Policy', 'livecanvas-forge-ai'), 'icon' => 'shield-lock'],
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

        echo '<label class="lcfa-choice-card">';
        echo '<input type="radio" name="framework" value="' . esc_attr($value) . '"' . checked($selected, $value, false) . '>';
        echo '<span class="lcfa-choice-copy">';
        echo '<span class="lcfa-choice-media">' . $logo_markup . '</span>';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<span>' . esc_html($description) . '</span>';
        echo '<small>' . esc_html($candidates ? sprintf(__('Detected theme candidates: %s', 'livecanvas-forge-ai'), implode(', ', wp_list_pluck($candidates, 'stylesheet'))) : __('No installed themes were detected for this family yet.', 'livecanvas-forge-ai')) . '</small>';
        echo '</span>';
        echo '</label>';
    }

    private function render_status_row(string $label, bool $status): void {
        echo '<div class="lcfa-status-row">';
        echo '<span class="lcfa-status-label">' . esc_html($label) . '</span>';
        echo '<strong class="' . esc_attr($status ? 'ok' : 'ko') . '">' . $this->get_icon_svg($status ? 'check-circle' : 'x-circle') . '<span>' . esc_html($status ? 'OK' : 'KO') . '</span></strong>';
        echo '</div>';
    }

    private function render_radio(string $name, string $value, string $label, string $selected, string $icon): void {
        echo '<label class="lcfa-radio">';
        echo '<input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . checked($selected, $value, false) . '>';
        echo '<span class="lcfa-radio-icon">' . $this->get_radio_icon_markup($name, $value, $icon) . '</span>';
        echo '<span class="lcfa-radio-copy">' . esc_html($label) . '</span>';
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
        echo '<span>' . esc_html__('LiveCanvas Forge AI', 'livecanvas-forge-ai') . '</span>';
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
        echo '<summary class="lcfa-hero-details-toggle">' . esc_html__('Details', 'livecanvas-forge-ai') . '</summary>';
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

        echo '<span class="lcfa-hero-mark' . ($active ? ' is-active' : '') . '">';
        echo '<span class="lcfa-hero-mark-media">' . $media . '</span>';
        echo '<span class="lcfa-hero-mark-label">' . esc_html($label) . '</span>';
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

    private function render_command_thread_panel(array $current_thread, array $thread_summaries): void {
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
            $thread_url = $this->get_command_url([
                'thread_id' => $thread_id,
            ]);

            echo '<a class="lcfa-thread-switch' . ($thread_id === $current_thread_id ? ' is-current' : '') . '" href="' . esc_url($thread_url) . '">';
            echo '<strong>' . esc_html((string) ($thread_summary['title'] ?? __('Thread', 'livecanvas-forge-ai'))) . '</strong>';
            echo '<span>' . esc_html(sprintf(_n('%d message', '%d messages', (int) ($thread_summary['message_count'] ?? 0), 'livecanvas-forge-ai'), (int) ($thread_summary['message_count'] ?? 0))) . '</span>';
            echo '</a>';
        }
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-inline-thread-form">';
        wp_nonce_field('lcfa_create_thread');
        echo '<input type="hidden" name="action" value="lcfa_create_thread">';
        echo '<input type="text" name="thread_title" value="" placeholder="' . esc_attr__('New thread title', 'livecanvas-forge-ai') . '">';
        echo '<button class="button" type="submit">' . esc_html__('New thread', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</div>';

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

    private function render_command_thread_message(array $message): void {
        $role = in_array($message['role'] ?? '', ['user', 'assistant', 'system'], true) ? (string) $message['role'] : 'assistant';
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
        if ($meta) {
            echo '<div class="lcfa-chip-row">';
            foreach ($meta as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', array_map('strval', $value));
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                if ($value === '') {
                    continue;
                }

                echo '<span class="lcfa-chip">' . esc_html(sprintf('%1$s: %2$s', $key, (string) $value)) . '</span>';
            }
            echo '</div>';
        }
        echo '</article>';
    }

    private function build_thread_result_message(array $result, array $payload): array {
        $lines = [];
        $summary = trim((string) ($result['summary'] ?? ''));
        $message = trim((string) ($result['message'] ?? ''));
        $execution_target = sanitize_key((string) ($payload['execution_target'] ?? 'local'));

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

        return [
            'role'    => 'assistant',
            'label'   => !empty($result['ok']) ? __('Execution result', 'livecanvas-forge-ai') : __('Execution error', 'livecanvas-forge-ai'),
            'content' => implode("\n", array_filter($lines)),
            'meta'    => [
                'action'           => (string) ($result['action'] ?? ''),
                'mode'             => (string) ($result['mode'] ?? ''),
                'execution_target' => $execution_target,
                'genesis_task_id'  => sanitize_key((string) ($payload['genesis_task_id'] ?? '')),
                'ok'               => !empty($result['ok']),
                'target_type'      => (string) ($result['target_type'] ?? ''),
                'target_id'        => (int) ($result['target_id'] ?? 0),
            ],
        ];
    }

    private function render_command_result(?array $result): void {
        if (!$result) {
            return;
        }

        $is_success = !empty($result['ok']);
        $status_icon = $is_success ? 'check-circle' : 'x-circle';
        $status_label = $is_success ? __('Command result', 'livecanvas-forge-ai') : __('Command error', 'livecanvas-forge-ai');

        echo '<section class="lcfa-card lcfa-command-result' . ($is_success ? ' is-success' : ' is-error') . '">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg($status_icon);
        echo '<div><h2>' . esc_html($status_label) . '</h2><p>' . esc_html($result['summary'] ?: $result['message']) . '</p></div>';
        echo '</div>';

        echo '<div class="lcfa-result-meta">';
        if (!empty($result['action'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Action: %s', 'livecanvas-forge-ai'), $result['action'])) . '</span>';
        }
        if (!empty($result['mode'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Mode: %s', 'livecanvas-forge-ai'), $result['mode'])) . '</span>';
        }
        if (!empty($result['data']['genesis_task_id'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Genesis task: %s', 'livecanvas-forge-ai'), (string) $result['data']['genesis_task_id'])) . '</span>';
        }
        if (!empty($result['data']['execution_target'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Execution: %s', 'livecanvas-forge-ai'), (string) $result['data']['execution_target'])) . '</span>';
        }
        if (!empty($result['target_type'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Target: %s', 'livecanvas-forge-ai'), $result['target_type'])) . '</span>';
        }
        if (!empty($result['target_id'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('ID: %d', 'livecanvas-forge-ai'), (int) $result['target_id'])) . '</span>';
        }
        if (!empty($result['target_title'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Label: %s', 'livecanvas-forge-ai'), $result['target_title'])) . '</span>';
        }
        echo '</div>';

        if (!empty($result['message']) && $result['message'] !== ($result['summary'] ?? '')) {
            echo '<p class="lcfa-result-message">' . esc_html($result['message']) . '</p>';
        }

        if (!empty($result['inventory']) && is_array($result['inventory'])) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Audit inventory', 'livecanvas-forge-ai') . '</h3>';
            $this->render_inventory_panel($result['inventory']);
            echo '</div>';
        }

        if (!empty($result['data']) && is_array($result['data'])) {
            echo '<div class="lcfa-result-panel">';
            echo '<h3>' . esc_html__('Structured payload', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block(wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
                'language'   => 'json',
                'label'      => __('JSON', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy payload', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
        }

        if (!empty($result['diff_html']) || $result['existing_html'] !== '' || $result['proposed_html'] !== '') {
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
        }

        echo '</section>';
    }

    private function render_command_suggestion(?array $suggestion): void {
        if (!$suggestion) {
            return;
        }

        $is_success = !empty($suggestion['ok']);
        $status_icon = $is_success ? 'stars' : 'x-circle';
        $status_label = $is_success ? __('Request analysis', 'livecanvas-forge-ai') : __('Request analysis issue', 'livecanvas-forge-ai');
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

        echo '<div class="lcfa-result-meta">';
        if (!empty($payload['action'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Suggested action: %s', 'livecanvas-forge-ai'), (string) $payload['action'])) . '</span>';
        }
        if (!empty($suggestion['confidence'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Confidence: %s', 'livecanvas-forge-ai'), (string) $suggestion['confidence'])) . '</span>';
        }
        if (!empty($payload['execution_target'])) {
            echo '<span class="lcfa-chip">' . esc_html(sprintf(__('Execution: %s', 'livecanvas-forge-ai'), (string) $payload['execution_target'])) . '</span>';
        }
        echo '</div>';

        if ($reasons) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Why this was suggested', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-bullets">';
            foreach ($reasons as $reason) {
                echo '<li>' . esc_html((string) $reason) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ($warnings) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Warnings', 'livecanvas-forge-ai') . '</h3>';
            echo '<ul class="lcfa-bullets">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ($workflow) {
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
        }

        if ($preflight) {
            echo '<div class="lcfa-result-panel">';
            echo '<h3>' . esc_html__('Recommended preflight payload', 'livecanvas-forge-ai') . '</h3>';
            $this->render_code_block(wp_json_encode($preflight, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), [
                'language'   => 'json',
                'label'      => __('JSON', 'livecanvas-forge-ai'),
                'copy_label' => __('Copy preflight', 'livecanvas-forge-ai'),
            ]);
            echo '</div>';
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
                echo '<a class="button button-small button-primary" href="' . esc_url($forge_url) . '">' . esc_html__('Forge', 'livecanvas-forge-ai') . '</a>';
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
                echo '<a class="button button-small button-primary" href="' . esc_url($forge_url) . '">' . esc_html__('Forge', 'livecanvas-forge-ai') . '</a>';
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
            'claude-code' => [
                'summary' => __('Claude Code can talk through the same MCP or REST bridge and should follow the same permission matrix.', 'livecanvas-forge-ai'),
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

        if ($this->inventory->resolve_partial_post_id('is_header', '1')) {
            $actions[] = [
                'label' => __('Header partial', 'livecanvas-forge-ai'),
                'icon'  => 'layers',
                'tone'  => 'neutral',
                'url'   => $this->get_command_url([
                    'suggest_action' => 'update_header',
                    'variant'        => '1',
                ]),
            ];
        }

        if ($this->inventory->resolve_partial_post_id('is_footer', '1')) {
            $actions[] = [
                'label' => __('Footer partial', 'livecanvas-forge-ai'),
                'icon'  => 'layers',
                'tone'  => 'neutral',
                'url'   => $this->get_command_url([
                    'suggest_action' => 'update_footer',
                    'variant'        => '1',
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
        $action = !empty($context['action']) ? (string) $context['action'] : 'site_audit';
        $variant = !empty($context['variant']) ? (string) $context['variant'] : '1';
        $label = $this->get_action_label($action);

        if ($action === 'update_header' || $action === 'update_footer') {
            return sprintf(
                __('Post #%1$d · %2$s · %3$s · variant %4$s', 'livecanvas-forge-ai'),
                (int) $post->ID,
                $post->post_type,
                $label,
                $variant
            );
        }

        return sprintf(
            __('Post #%1$d · %2$s · %3$s', 'livecanvas-forge-ai'),
            (int) $post->ID,
            $post->post_type,
            $label
        );
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
            'codex'       => 'assets/agent-icons/codex.png',
            'opencode'    => 'assets/agent-icons/opencode.png',
            'cursor'      => 'assets/agent-icons/cursor.png',
            'claude-code' => 'assets/agent-icons/claude-code.svg',
        ];

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

    private function redirect_to_step(int $step): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=' . $step));
        exit;
    }
}
