<?php

defined('ABSPATH') || exit;

final class LCFA_Admin {
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;

    public function __construct(LCFA_Environment $environment, LCFA_Installer $installer) {
        $this->environment = $environment;
        $this->installer   = $installer;
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menus'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('admin_post_lcfa_setup', [$this, 'handle_setup_post']);
        add_action('admin_post_lcfa_project_brief', [$this, 'handle_project_brief_post']);
    }

    public function register_menus(): void {
        $parent_slug = $this->environment->get_livecanvas_menu_slug();
        $capability  = 'manage_options';

        if ($parent_slug) {
            add_submenu_page(
                $parent_slug,
                __('Forge AI', 'livecanvas-forge-ai'),
                __('Forge AI', 'livecanvas-forge-ai'),
                $capability,
                'lcfa-dashboard',
                [$this, 'render_dashboard_page']
            );

            add_submenu_page(
                $parent_slug,
                __('Forge Setup', 'livecanvas-forge-ai'),
                __('Forge Setup', 'livecanvas-forge-ai'),
                $capability,
                'lcfa-setup',
                [$this, 'render_setup_page']
            );

            return;
        }

        add_menu_page(
            __('LiveCanvas Forge AI', 'livecanvas-forge-ai'),
            __('Forge AI', 'livecanvas-forge-ai'),
            $capability,
            'lcfa-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-superhero-alt',
            59
        );

        add_submenu_page(
            'lcfa-dashboard',
            __('Forge Setup', 'livecanvas-forge-ai'),
            __('Forge Setup', 'livecanvas-forge-ai'),
            $capability,
            'lcfa-setup',
            [$this, 'render_setup_page']
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

        wp_safe_redirect(admin_url('admin.php?page=lcfa-setup'));
        exit;
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
                wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard'));
                exit;

            default:
                $this->redirect_to_step(1);
        }
    }

    public function handle_project_brief_post(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'livecanvas-forge-ai'));
        }

        check_admin_referer('lcfa_project_brief');

        LCFA_Settings::update_project_brief($_POST);
        LCFA_Settings::set_notice(__('Genesis brief updated.', 'livecanvas-forge-ai'));

        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard'));
        exit;
    }

    public function render_setup_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = LCFA_Settings::get();
        $snapshot = $this->environment->get_snapshot();
        $step     = max(1, absint($_GET['step'] ?? 1));

        if ($step > 1 && !$snapshot['livecanvas_active']) {
            $step = 1;
        }

        $notice = LCFA_Settings::consume_notice();

        echo '<div class="wrap lcfa-admin">';
        $this->render_page_header(
            __('Forge Setup', 'livecanvas-forge-ai'),
            __('Prepare the stack, confirm your frontend framework, and choose how your AI clients will connect to LiveCanvas.', 'livecanvas-forge-ai'),
            $snapshot,
            $settings
        );

        $this->render_notice($notice);
        $this->render_step_nav($step, $settings, $snapshot);

        echo '<div class="lcfa-grid">';
        echo '<div class="lcfa-main">';

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
                $this->render_finish_step($settings, $snapshot);
                break;
        }

        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_snapshot_card($snapshot, $settings);
        echo '</aside>';
        echo '</div>';
        echo '</div>';
    }

    public function render_dashboard_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = LCFA_Settings::get();
        $snapshot = $this->environment->get_snapshot();
        $brief    = LCFA_Settings::get_project_brief();
        $notice   = LCFA_Settings::consume_notice();

        echo '<div class="wrap lcfa-admin">';
        $this->render_page_header(
            __('Forge AI Dashboard', 'livecanvas-forge-ai'),
            __('Capture the project brief now, then wire the future Command Deck into LiveCanvas with preview, diff, and rollback as the default workflow.', 'livecanvas-forge-ai'),
            $snapshot,
            $settings
        );

        $this->render_notice($notice);

        if (!$settings['completed']) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Forge Setup is not complete yet. Finish the setup flow before using the operational dashboard.', 'livecanvas-forge-ai');
            echo ' <a href="' . esc_url(admin_url('admin.php?page=lcfa-setup')) . '">' . esc_html__('Open Forge Setup', 'livecanvas-forge-ai') . '</a>';
            echo '</p></div>';
        }

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
        echo '<div><h2>' . esc_html__('Command Deck', 'livecanvas-forge-ai') . '</h2><p>' . esc_html__('The persistent operational chat will land here in the next milestone.', 'livecanvas-forge-ai') . '</p></div>';
        echo '</div>';
        echo '<p>' . esc_html__('This panel will consume the Genesis brief, the confirmed stack, and the selected permission profile to create or refine pages, headers, footers, dynamic templates, and safe fallbacks with preview first.', 'livecanvas-forge-ai') . '</p>';
        echo '<ul class="lcfa-bullets">';
        echo '<li>' . esc_html__('Targets planned: pages, headers, footers, partials, dynamic templates, PHP fallback templates.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Execution model: plan, preview, diff, apply, rollback.', 'livecanvas-forge-ai') . '</li>';
        echo '<li>' . esc_html__('Client integrations planned: Codex, OpenCode, Claude Code, Cursor, MCP, and REST.', 'livecanvas-forge-ai') . '</li>';
        echo '</ul>';
        echo '</section>';

        echo '</div>';
        echo '<aside class="lcfa-sidebar">';
        $this->render_snapshot_card($snapshot, $settings);
        echo '</aside>';
        echo '</div>';
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

            echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url(admin_url('admin.php?page=lcfa-setup&step=' . $step)) . '">' . $content . '</a>';
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
            $this->get_partner_logo('livecanvas'),
            $snapshot['livecanvas_active'] ? __('Active', 'livecanvas-forge-ai') : __('Missing or inactive', 'livecanvas-forge-ai'),
            $snapshot['livecanvas_active']
        );
        $this->render_brand_tile(
            $snapshot['detected_framework'] === 'picowind' ? __('Picowind', 'livecanvas-forge-ai') : __('Bootstrap', 'livecanvas-forge-ai'),
            $snapshot['detected_framework'] === 'picowind' ? $this->get_icon_svg('wind') : $this->get_partner_logo('bootstrap'),
            $snapshot['framework_slug'] ?: __('No editor config detected', 'livecanvas-forge-ai'),
            $snapshot['detected_framework'] !== 'unknown'
        );
        $this->render_brand_tile(
            __('WindPress', 'livecanvas-forge-ai'),
            $this->get_partner_logo('windpress'),
            $snapshot['windpress_active'] ? __('Active', 'livecanvas-forge-ai') : ($snapshot['windpress_installed'] ? __('Installed', 'livecanvas-forge-ai') : __('Not installed', 'livecanvas-forge-ai')),
            $snapshot['windpress_installed']
        );
        echo '</div>';

        echo '<ul class="lcfa-facts">';
        $this->render_fact_row(__('Theme', 'livecanvas-forge-ai'), $snapshot['current_theme_name']);
        $this->render_fact_row(__('Detected framework', 'livecanvas-forge-ai'), $snapshot['detected_framework']);
        $this->render_fact_row(__('Editor config', 'livecanvas-forge-ai'), $snapshot['framework_slug'] ?: 'n/a');
        $this->render_fact_row(__('Site profile', 'livecanvas-forge-ai'), $settings['site_mode'] ?: $snapshot['site_mode']);
        $this->render_fact_row(__('Tangible', 'livecanvas-forge-ai'), $snapshot['tangible_available'] ? __('Available', 'livecanvas-forge-ai') : __('Unavailable', 'livecanvas-forge-ai'));
        $this->render_fact_row(__('WooCommerce', 'livecanvas-forge-ai'), $snapshot['woocommerce_active'] ? __('Detected', 'livecanvas-forge-ai') : __('Not detected', 'livecanvas-forge-ai'));
        $this->render_fact_row(__('ACF', 'livecanvas-forge-ai'), $snapshot['acf_active'] ? __('Detected', 'livecanvas-forge-ai') : __('Not detected', 'livecanvas-forge-ai'));
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
        echo $this->get_icon_svg('moon-stars');
        echo '<span>' . esc_html__('LiveCanvas Forge AI', 'livecanvas-forge-ai') . '</span>';
        echo '</div>';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="lcfa-lead">' . esc_html($subtitle) . '</p>';
        echo '<div class="lcfa-hero-badges">';
        $this->render_badge(__('Framework', 'livecanvas-forge-ai'), $settings['framework'] ?: $snapshot['detected_framework']);
        $this->render_badge(__('Theme', 'livecanvas-forge-ai'), $snapshot['current_theme_stylesheet']);
        $this->render_badge(__('Site mode', 'livecanvas-forge-ai'), $settings['site_mode'] ?: $snapshot['site_mode']);
        echo '</div>';
        echo '</div>';

        echo '<div class="lcfa-hero-stack">';
        echo '<div class="lcfa-brand-pill">' . $this->get_partner_logo('livecanvas') . '<span>' . esc_html__('LiveCanvas', 'livecanvas-forge-ai') . '</span></div>';
        echo '<div class="lcfa-brand-pill">' . $this->get_partner_logo('bootstrap') . '<span>' . esc_html__('Bootstrap', 'livecanvas-forge-ai') . '</span></div>';
        echo '<div class="lcfa-brand-pill">' . $this->get_partner_logo('windpress') . '<span>' . esc_html__('WindPress', 'livecanvas-forge-ai') . '</span></div>';
        echo '</div>';
        echo '</section>';
    }

    private function render_brand_tile(string $title, string $logo_markup, string $caption, bool $is_active): void {
        echo '<div class="lcfa-brand-tile' . ($is_active ? ' is-active' : '') . '">';
        echo '<div class="lcfa-brand-logo">' . $logo_markup . '</div>';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<span>' . esc_html($caption) . '</span>';
        echo '</div>';
    }

    private function render_fact_row(string $label, string $value): void {
        echo '<li><strong>' . esc_html($label) . ':</strong> <span>' . esc_html($value) . '</span></li>';
    }

    private function render_badge(string $label, string $value): void {
        echo '<span class="lcfa-badge"><strong>' . esc_html($label) . '</strong><span>' . esc_html($value) . '</span></span>';
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

            case 'windpress':
                $logo = $this->get_plugin_asset_url('windpress', 'windpress.svg');

                if ($logo) {
                    return '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('WindPress', 'livecanvas-forge-ai') . '" class="lcfa-logo lcfa-logo-windpress">';
                }

                return '<span class="lcfa-fallback-logo">WP</span>';

            case 'bootstrap':
                return '<span class="lcfa-logo lcfa-logo-bootstrap"><svg viewBox="0 0 118 94" aria-hidden="true"><path fill="currentColor" d="M24.509 0h68.982C107.024 0 118 10.976 118 24.509v44.982C118 83.024 107.024 94 93.491 94H24.509C10.976 94 0 83.024 0 69.491V24.509C0 10.976 10.976 0 24.509 0Zm53.659 62.019c0 10.084-7.517 16.235-20.201 16.235H36.149V15.746H57.39c12.189 0 18.581 5.43 18.581 14.751 0 6.434-3.556 11.18-9.779 12.754v.253c8.483.887 11.976 6.139 11.976 18.515Zm-30.45-20.675h7.39c6.56 0 9.906-2.282 9.906-7.643 0-5.367-3.219-7.39-9.589-7.39h-7.707v15.033Zm0 9.716v17.541h8.356c6.876 0 10.222-2.409 10.222-8.166 0-5.811-3.346-9.375-10.285-9.375h-8.293Z"/></svg></span>';

            default:
                return '<span class="lcfa-fallback-logo">AI</span>';
        }
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
        wp_safe_redirect(admin_url('admin.php?page=lcfa-setup&step=' . $step));
        exit;
    }
}
