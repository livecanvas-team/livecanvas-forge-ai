<?php

defined('ABSPATH') || exit;

final class LCFA_Admin {
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
    private LCFA_Inventory $inventory;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;

    public function __construct(LCFA_Environment $environment, LCFA_Installer $installer, LCFA_Inventory $inventory, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Context_Builder $context_builder, LCFA_Command_Deck $command_deck) {
        $this->environment  = $environment;
        $this->installer    = $installer;
        $this->inventory    = $inventory;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->context_builder = $context_builder;
        $this->command_deck = $command_deck;
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menus'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('admin_post_lcfa_setup', [$this, 'handle_setup_post']);
        add_action('admin_post_lcfa_reset_setup', [$this, 'handle_reset_setup_post']);
        add_action('admin_post_lcfa_connections', [$this, 'handle_connections_post']);
        add_action('admin_post_lcfa_project_brief', [$this, 'handle_project_brief_post']);
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
            'lcfa-admin',
            LCFA_URL . 'assets/admin.css',
            [],
            LCFA_VERSION
        );
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
        echo '.lcfa-editor-bridge__hint{font-size:12px;line-height:1.6;color:rgba(245,247,255,.62)}';
        echo '@media (max-width:782px){.lcfa-editor-shell{right:12px;left:12px;bottom:12px}.lcfa-editor-launcher{width:100%;justify-content:center}.lcfa-editor-drawer{width:auto;left:0}.lcfa-editor-bridge__actions{grid-template-columns:1fr}}';
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
        echo '<script id="lcfa-editor-bridge-script">(function(){var shell=document.querySelector("[data-lcfa-editor-shell]");if(!shell||shell.dataset.bound==="1"){return;}shell.dataset.bound="1";var drawer=shell.querySelector(".lcfa-editor-drawer");var openBtn=shell.querySelector("[data-lcfa-editor-open]");var closeBtn=shell.querySelector("[data-lcfa-editor-close]");var setOpen=function(next){shell.classList.toggle("is-open",next);if(drawer){drawer.setAttribute("aria-hidden",next?"false":"true");}};if(openBtn){openBtn.addEventListener("click",function(){setOpen(true);});}if(closeBtn){closeBtn.addEventListener("click",function(){setOpen(false);});}document.addEventListener("keydown",function(event){if(event.key==="Escape"){setOpen(false);}});document.addEventListener("click",function(event){if(!shell.classList.contains("is-open")){return;}if(shell.contains(event.target)){return;}setOpen(false);});})();</script>';
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
                wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
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

        LCFA_Settings::update_connections($_POST);

        if (!empty($_POST['rotate_mcp_token'])) {
            LCFA_Settings::rotate_mcp_token();
        }

        $preferred_client = sanitize_key($_POST['preferred_client'] ?? '');
        if (in_array($preferred_client, ['codex', 'opencode', 'claude-code', 'cursor', 'other'], true)) {
            LCFA_Settings::patch([
                'ai_tool' => $preferred_client,
            ]);
        }

        LCFA_Settings::set_notice(!empty($_POST['rotate_mcp_token']) ? __('Connection settings saved and MCP token rotated.', 'livecanvas-forge-ai') : __('Connection settings saved.', 'livecanvas-forge-ai'));

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    public function handle_reset_setup_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_reset_setup');

        LCFA_Settings::update(LCFA_Settings::defaults());
        LCFA_Settings::set_notice(__('Forge Setup has been reset. Connections, history, and Genesis brief were preserved.', 'livecanvas-forge-ai'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=1'));
        exit;
    }

    public function handle_project_brief_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_project_brief');

        LCFA_Settings::update_project_brief($_POST);
        LCFA_Settings::set_notice(__('Genesis brief updated.', 'livecanvas-forge-ai'));

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=genesis'));
        exit;
    }

    public function handle_command_post(): void {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_command');

        $result = $this->command_deck->execute($_POST);
        LCFA_Settings::set_command_result($result);

        if (!empty($result['ok'])) {
            $notice = $result['mode'] === 'preview'
                ? __('Command preview prepared.', 'livecanvas-forge-ai')
                : ($result['message'] ?: __('Command applied.', 'livecanvas-forge-ai'));

            LCFA_Settings::set_notice($notice, 'success');
        } else {
            LCFA_Settings::set_notice($result['message'] ?: __('The command failed.', 'livecanvas-forge-ai'), 'error');
        }

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=command'));
        exit;
    }

    public function render_setup_page(): void {
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=setup&step=' . max(1, absint($_GET['step'] ?? 1))));
        exit;
    }

    public function render_dashboard_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = LCFA_Settings::get();
        $snapshot = $this->environment->get_snapshot();
        $brief    = LCFA_Settings::get_project_brief();
        $summary  = $this->inventory->get_summary();
        $notice   = LCFA_Settings::consume_notice();
        $tab      = sanitize_key($_GET['tab'] ?? ($settings['completed'] ? 'genesis' : 'setup'));

        if (!in_array($tab, ['setup', 'genesis', 'connections', 'command'], true)) {
            $tab = $settings['completed'] ? 'genesis' : 'setup';
        }

        $hero = [
            'setup' => [
                'title'    => __('Forge Setup', 'livecanvas-forge-ai'),
                'subtitle' => __('Run the onboarding wizard, confirm the framework, and prepare the plugin for local or remote AI-assisted development.', 'livecanvas-forge-ai'),
            ],
            'genesis' => [
                'title'    => __('Genesis Brief', 'livecanvas-forge-ai'),
                'subtitle' => __('Define the brand, site scope, and build mode so the AI starts with a persistent project brief instead of isolated prompts.', 'livecanvas-forge-ai'),
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

        echo '<div class="wrap lcfa-admin">';
        $this->render_page_header(
            $hero[$tab]['title'],
            $hero[$tab]['subtitle'],
            $snapshot,
            $settings
        );

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
                $this->render_genesis_tab($settings, $snapshot, $brief, $summary);
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
            'genesis' => ['label' => __('Genesis', 'livecanvas-forge-ai'), 'icon' => 'stars'],
            'connections' => ['label' => __('Connections', 'livecanvas-forge-ai'), 'icon' => 'plug'],
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

        echo '<div class="lcfa-grid">';
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
        echo '<aside class="lcfa-sidebar">';
        $this->render_snapshot_card($snapshot, $settings);
        echo '</aside>';
        echo '</div>';
    }

    private function render_genesis_tab(array $settings, array $snapshot, array $brief, array $summary): void {
        echo '<div class="lcfa-grid">';
        echo '<div class="lcfa-main">';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('stars');
        echo '<div><h2>' . esc_html__('Genesis Brief', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Capture the inputs required to start from zero or to guide the build step by step.', 'livecanvas-forge-ai') . '</p></div>';
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

        echo '<button class="button button-primary">' . esc_html__('Save Genesis Brief', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('Operations overview', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Once the brief is stored, use Connections and Command Deck from the same screen to execute the build progressively.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<div class="lcfa-cta-row">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=command')) . '">' . esc_html__('Open Command Deck', 'livecanvas-forge-ai') . '</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lcfa-dashboard&tab=connections')) . '">' . esc_html__('Open Connections', 'livecanvas-forge-ai') . '</a>';
        echo '</div>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('Inventory ready: %1$d pages, %2$d headers, %3$d footers, %4$d dynamic templates.', 'livecanvas-forge-ai'), $summary['pages'], $summary['headers'], $summary['footers'], $summary['dynamic_templates'])) . '</li>';
        echo '<li>' . esc_html__('Execution model: preview first, diff when content changes, apply only when requested.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Use this brief as the persistent input for generating structure, copy, sections, and dynamic templates.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';
        echo '</section>';

        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_snapshot_card($snapshot, $settings);
        echo '</aside>';
        echo '</div>';
    }

    private function render_connections_tab(array $settings, array $snapshot): void {
        $connections      = LCFA_Settings::get_connections();
        $preferred_client = $connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex');
        $guides           = $this->get_tool_guides($settings['site_mode'] ?: $snapshot['site_mode']);
        $guide            = $guides[$preferred_client] ?? $guides['other'];
        $mcp_status       = $this->context_builder->get_mcp_status();
        $local_bridge     = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];
        $mcp_bootstrap    = $this->context_builder->get_bootstrap_payload();
        $command_example  = wp_json_encode([
            'action'  => 'site_audit',
            'dry_run' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $preferred_bootstrap = $mcp_bootstrap['clients'][$preferred_client] ?? $mcp_bootstrap['clients']['codex'];
        $common_environment = [
            'LCFA_SITE_URL=' . $mcp_bootstrap['common']['site_url'],
            'LCFA_REST_BASE=' . $mcp_bootstrap['common']['rest_base'],
            'LCFA_MCP_ENDPOINT=' . $mcp_bootstrap['common']['mcp_endpoint'],
            'LCFA_MCP_TOKEN=' . $mcp_bootstrap['common']['mcp_token'],
            'LCFA_FRAMEWORK=' . $mcp_bootstrap['common']['framework'],
            'LCFA_THEME=' . $mcp_bootstrap['common']['theme'],
        ];

        if (($mcp_bootstrap['common']['filesystem_mode'] ?? '') === 'local-theme-access' && !empty($mcp_bootstrap['common']['wp_root'])) {
            $common_environment[] = 'LCFA_WP_ROOT=' . $mcp_bootstrap['common']['wp_root'];
        }

        $common_bootstrap = implode("\n", $common_environment);

        echo '<div class="lcfa-grid">';
        echo '<div class="lcfa-main">';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('plug');
        echo '<div><h2>' . esc_html__('Connection profile', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('Use this page to feed the installer and define how your preferred client should talk to the companion.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_connections');
        echo '<input type="hidden" name="action" value="lcfa_connections">';

        echo '<label><span>' . esc_html__('Preferred transport', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="transport">';
        echo '<option value="rest"' . selected($connections['transport'], 'rest', false) . '>' . esc_html__('REST first', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="mcp"' . selected($connections['transport'], 'mcp', false) . '>' . esc_html__('MCP first', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="hybrid"' . selected($connections['transport'], 'hybrid', false) . '>' . esc_html__('Hybrid', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__('Preferred AI client', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="preferred_client">';
        echo '<option value="codex"' . selected($preferred_client, 'codex', false) . '>' . esc_html__('Codex', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="opencode"' . selected($preferred_client, 'opencode', false) . '>' . esc_html__('OpenCode', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="claude-code"' . selected($preferred_client, 'claude-code', false) . '>' . esc_html__('Claude Code', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="cursor"' . selected($preferred_client, 'cursor', false) . '>' . esc_html__('Cursor', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="other"' . selected($preferred_client, 'other', false) . '>' . esc_html__('Other', 'livecanvas-forge-ai') . '</option>';
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
        echo '<button class="button button-primary">' . esc_html__('Save connection settings', 'livecanvas-forge-ai') . '</button>';
        echo '<button class="button" type="submit" name="rotate_mcp_token" value="1">' . esc_html__('Rotate MCP token', 'livecanvas-forge-ai') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('command');
        echo '<div><h2>' . esc_html__('MCP bootstrap', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('This is the plugin-side contract that your own MCP server can consume immediately.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('REST base: %s', 'livecanvas-forge-ai'), rest_url('lcfa/v1/'))) . '</li>';
        echo '<li>' . esc_html(sprintf(__('MCP endpoint: %s', 'livecanvas-forge-ai'), $mcp_status['endpoint'])) . '</li>';
        echo '<li>' . esc_html__('Context endpoints: /context, /theme-context, /page-html, /acf-fields, /library/blocks.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Execution endpoints: /command/actions, /command, /mcp/status, /mcp/local-status, /mcp/bootstrap.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('WindPress endpoints: /windpress/status, /windpress/providers, /windpress/providers/scan, /windpress/volume, /windpress/volume/handlers, /windpress/volume/reset, /windpress/theme-json, /windpress/cache, /windpress/build-local. MCP bridge also exposes /windpress/providers/scan/full and /windpress/build for local compilation.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Theme endpoints: /theme/roots, /theme/files, /theme/templates, /theme/templates/{type}, /theme/file, /theme/template.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Filesystem tools: get_theme_roots, list_theme_files, list_theme_templates, list_twig_templates, list_latte_templates, list_php_templates, read_theme_file, read_template_file, write_theme_file, write_template_file.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Recommended auth for remote use: a dedicated WordPress user plus Application Password.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Installer note: the framework installer consumes the package URLs stored on this page.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Common environment', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-code-block"><pre><code>' . esc_html($common_bootstrap) . '</code></pre></div>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Preferred client bootstrap', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-code-block"><pre><code>' . esc_html($preferred_bootstrap['command'] . "\n\n" . implode("\n", $preferred_bootstrap['env'])) . '</code></pre></div>';
        echo '</div>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Example command request', 'livecanvas-forge-ai') . '</h3>';
        echo '<div class="lcfa-code-block"><pre><code>' . esc_html("POST " . rest_url('lcfa/v1/command') . "\nContent-Type: application/json\n\n" . $command_example) . '</code></pre></div>';
        echo '</div>';
        echo '</section>';

        echo '<section class="lcfa-card">';
        echo '<div class="lcfa-card-head">';
        echo $this->get_icon_svg('stars');
        echo '<div><h2>' . esc_html__('Preferred client guide', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('A concrete starting point for the client currently selected in the setup or on this page.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<p>' . esc_html($guide['summary']) . '</p>';
        echo '<ul class="lcfa-bullets">';
        foreach ($guide['steps'] as $line) {
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';
        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('MCP status', 'livecanvas-forge-ai') . '</h3>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html(sprintf(__('Enabled: %s', 'livecanvas-forge-ai'), $mcp_status['enabled'] ? __('yes', 'livecanvas-forge-ai') : __('no', 'livecanvas-forge-ai'))) . '</li>';
        echo '<li>' . esc_html(sprintf(__('Filesystem mode: %s', 'livecanvas-forge-ai'), $mcp_status['filesystem_mode'])) . '</li>';
        echo '<li>' . esc_html(sprintf(__('Local MCP bridge: %s', 'livecanvas-forge-ai'), !empty($local_bridge['available']) ? __('ready', 'livecanvas-forge-ai') : __('not available', 'livecanvas-forge-ai'))) . '</li>';
        echo '<li>' . esc_html(sprintf(__('Local WindPress build: %s', 'livecanvas-forge-ai'), !empty($local_bridge['build_available']) ? __('ready', 'livecanvas-forge-ai') : __('not available', 'livecanvas-forge-ai'))) . '</li>';
        if (!empty($local_bridge['node_version'])) {
            echo '<li>' . esc_html(sprintf(__('Node version: %s', 'livecanvas-forge-ai'), $local_bridge['node_version'])) . '</li>';
        }
        echo '<li>' . esc_html(sprintf(__('Token: %s', 'livecanvas-forge-ai'), $connections['mcp_token'])) . '</li>';
        echo '</ul>';
        if (!empty($local_bridge['message'])) {
            echo '<p>' . esc_html($local_bridge['message']) . '</p>';
        }
        echo '</div>';
        echo '</section>';

        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_snapshot_card($snapshot, $settings);
        echo '</aside>';
        echo '</div>';
    }

    private function render_command_tab(array $settings, array $snapshot): void {
        $inventory      = $this->inventory->get_inventory();
        $actions        = $this->command_deck->get_actions();
        $command_result = LCFA_Settings::consume_command_result();
        $history        = LCFA_Settings::get_history();
        $theme_context  = $this->context_builder->get_theme_context();
        $windpress      = is_array($theme_context['windpress'] ?? null) ? $theme_context['windpress'] : [];
        $mcp_status     = $this->context_builder->get_mcp_status();
        $local_bridge   = is_array($mcp_status['local_bridge'] ?? null) ? $mcp_status['local_bridge'] : [];
        $command_form   = $this->get_command_form_context($actions);
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

        if (!empty($local_bridge)) {
            echo '<div class="lcfa-guide">';
            echo '<h3>' . esc_html__('Local build runtime', 'livecanvas-forge-ai') . '</h3>';
            echo '<p>' . esc_html(!empty($local_bridge['build_available']) ? __('Local Node + MCP execution is available. You can run build_windpress_cache directly from this tab.', 'livecanvas-forge-ai') : ($local_bridge['message'] ?? __('Local MCP execution is not available.', 'livecanvas-forge-ai'))) . '</p>';
            if (!empty($local_bridge['node_version'])) {
                echo '<p><code>' . esc_html(sprintf(__('Node %s', 'livecanvas-forge-ai'), $local_bridge['node_version'])) . '</code></p>';
            }
            echo '</div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lcfa-form">';
        wp_nonce_field('lcfa_command');
        echo '<input type="hidden" name="action" value="lcfa_command">';

        echo '<label><span>' . esc_html__('Action', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="action">';
        foreach ($actions as $action_key => $action_data) {
            echo '<option value="' . esc_attr($action_key) . '"' . selected($command_form['action'], $action_key, false) . '>' . esc_html($action_data['label']) . '</option>';
        }
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
        echo '<label><span>' . esc_html__('Post status', 'livecanvas-forge-ai') . '</span>';
        echo '<select name="status">';
        echo '<option value="draft"' . selected($command_form['status'], 'draft', false) . '>' . esc_html__('Draft', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="publish"' . selected($command_form['status'], 'publish', false) . '>' . esc_html__('Publish', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="private"' . selected($command_form['status'], 'private', false) . '>' . esc_html__('Private', 'livecanvas-forge-ai') . '</option>';
        echo '<option value="pending"' . selected($command_form['status'], 'pending', false) . '>' . esc_html__('Pending', 'livecanvas-forge-ai') . '</option>';
        echo '</select></label>';
        echo '<label><span>' . esc_html__('HTML / template / CSS / theme.json content', 'livecanvas-forge-ai') . '</span><textarea name="content" rows="16" placeholder="' . esc_attr__('<section>...</section>', 'livecanvas-forge-ai') . '">' . esc_textarea($command_form['content']) . '</textarea></label>';
        echo '<label class="lcfa-checkbox"><input type="checkbox" name="dry_run" value="1"' . checked($command_form['dry_run'], true, false) . '> ' . esc_html__('Run as preview only', 'livecanvas-forge-ai') . '</label>';

        echo '<button class="button button-primary">' . esc_html__('Run command', 'livecanvas-forge-ai') . '</button>';
        echo '</form>';

        echo '<div class="lcfa-guide">';
        echo '<h3>' . esc_html__('Action guide', 'livecanvas-forge-ai') . '</h3>';
        echo '<ul class="lcfa-bullets">';
        foreach ($actions as $action_key => $action_data) {
            echo '<li><strong>' . esc_html($action_data['label']) . '</strong>: ' . esc_html($action_data['description']) . ' ';
            echo '<code>' . esc_html($action_key) . '</code></li>';
        }
        echo '<li>' . esc_html__('For build_windpress_cache, the provider field accepts one provider ID or a comma-separated list.', 'livecanvas-forge-ai') . '</li>';
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
        $this->render_snapshot_card($snapshot, $settings);
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
        echo '<div><h2>' . esc_html__('Step 6. Finish setup', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The stack is profiled. The next useful move is to save the Genesis brief and prepare the future Command Deck flow.', 'livecanvas-forge-ai') . '</p></div>';
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
        echo '<span class="lcfa-radio-icon">' . $this->get_icon_svg($icon) . '</span>';
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

    private function render_page_header(string $title, string $subtitle, array $snapshot, array $settings): void {
        echo '<section class="lcfa-hero">';
        echo '<div class="lcfa-hero-copy">';
        echo '<div class="lcfa-kicker">';
        echo '<span>' . esc_html__('LiveCanvas Forge AI', 'livecanvas-forge-ai') . '</span>';
        echo '</div>';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="lcfa-lead">' . esc_html($subtitle) . '</p>';
        echo '<div class="lcfa-hero-badges">';
        if (!empty($settings['framework'])) {
            $this->render_badge(__('Framework', 'livecanvas-forge-ai'), $settings['framework'], 'active');
        }
        $this->render_badge(__('Theme', 'livecanvas-forge-ai'), $snapshot['current_theme_stylesheet'], 'active');
        if (!empty($settings['site_mode'])) {
            $this->render_badge(__('Site mode', 'livecanvas-forge-ai'), $settings['site_mode'], 'active');
        }
        echo '</div>';

        echo '<div class="lcfa-mini-partners">';
        echo '<span class="lcfa-mini-partner">' . $this->get_partner_logo('livecanvas-micro') . '<span>' . esc_html__('LiveCanvas', 'livecanvas-forge-ai') . '</span></span>';
        echo '<span class="lcfa-mini-partner">' . $this->get_partner_logo('bootstrap') . '<span>' . esc_html__('Bootstrap', 'livecanvas-forge-ai') . '</span></span>';
        echo '<span class="lcfa-mini-partner">' . $this->get_partner_logo('windpress') . '<span>' . esc_html__('WindPress', 'livecanvas-forge-ai') . '</span></span>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
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
            echo '<div class="lcfa-code-block"><pre><code>' . esc_html(wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></pre></div>';
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
                echo '<div class="lcfa-code-block"><pre><code>' . esc_html($result['existing_html']) . '</code></pre></div>';
                echo '</div>';
            }

            if ($result['proposed_html'] !== '') {
                echo '<div class="lcfa-result-panel">';
                echo '<h3>' . esc_html__('Proposed content', 'livecanvas-forge-ai') . '</h3>';
                echo '<div class="lcfa-code-block"><pre><code>' . esc_html($result['proposed_html']) . '</code></pre></div>';
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</section>';
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

        $items = is_array($templates['files'] ?? null) ? $templates['files'] : [];
        if (!$items) {
            echo '<p class="lcfa-empty">' . esc_html__('No template files were detected in the common theme template directories.', 'livecanvas-forge-ai') . '</p>';
            return;
        }

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
            'target_id'     => '',
            'variant'       => '1',
            'title'         => '',
            'slug'          => '',
            'provider_id'   => '',
            'relative_path' => '',
            'root_scope'    => 'stylesheet',
            'file_path'     => '',
            'status'        => 'draft',
            'content'       => '',
            'dry_run'       => true,
            'context_label' => '',
        ];

        $requested_action = sanitize_key($_GET['suggest_action'] ?? '');
        if ($requested_action !== '' && isset($actions[$requested_action])) {
            $defaults['action'] = $requested_action;
        }

        $defaults['target_id']     = absint($_GET['target_id'] ?? 0) ?: '';
        $defaults['variant']       = sanitize_text_field($_GET['variant'] ?? '1');
        $defaults['provider_id']   = sanitize_key($_GET['provider_id'] ?? '');
        $defaults['relative_path'] = sanitize_text_field($_GET['relative_path'] ?? '');
        $defaults['root_scope']    = sanitize_key($_GET['root_scope'] ?? 'stylesheet');
        $defaults['file_path']     = sanitize_text_field($_GET['file_path'] ?? '');

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

        return $this->hydrate_command_form_context($defaults);
    }

    private function hydrate_command_form_context(array $context): array {
        $action = (string) ($context['action'] ?? '');
        $target = [
            'post'    => null,
            'content' => '',
        ];

        switch ($action) {
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
            $context['action'] = 'update_page';
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
            $context['action'] = 'update_page';
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
        return $this->get_dashboard_url(array_merge([
            'tab' => 'command',
        ], $args));
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
