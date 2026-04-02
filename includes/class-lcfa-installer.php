<?php

defined('ABSPATH') || exit;

final class LCFA_Installer {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function apply_framework(string $framework) {
        if (!in_array($framework, ['picostrap', 'picowind'], true)) {
            return new WP_Error('lcfa_invalid_framework', __('Invalid framework.', 'livecanvas-forge-ai'));
        }

        $stylesheet = $this->environment->get_preferred_theme_stylesheet($framework);

        if (!$stylesheet) {
            $install_result = $this->install_framework_package($framework);

            if (is_wp_error($install_result)) {
                return $install_result;
            }

            $stylesheet = $this->environment->get_preferred_theme_stylesheet($framework);
        }

        if (!$stylesheet) {
            return new WP_Error('lcfa_missing_theme', __('The requested theme is not installed yet, and no package URL has been configured for automatic installation.', 'livecanvas-forge-ai'));
        }

        $current_stylesheet = wp_get_theme()->get_stylesheet();
        $switched           = false;

        if ($current_stylesheet !== $stylesheet) {
            switch_theme($stylesheet);
            $switched = true;
        }

        $windpress_status = 'not-required';

        if ($framework === 'picowind') {
            $windpress_result = $this->ensure_windpress_active();

            if (is_wp_error($windpress_result)) {
                return $windpress_result;
            }

            $windpress_status = $windpress_result;
        }

        return [
            'framework'        => $framework,
            'theme_stylesheet' => $stylesheet,
            'theme_switched'   => $switched,
            'windpress_status' => $windpress_status,
        ];
    }

    public function activate_livecanvas() {
        $plugin_file = $this->environment->find_plugin_file_by_slug('livecanvas');

        if (!$plugin_file) {
            return new WP_Error('lcfa_missing_livecanvas', __('LiveCanvas does not appear to be installed.', 'livecanvas-forge-ai'));
        }

        if ($this->environment->is_plugin_active($plugin_file)) {
            return true;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $result = activate_plugin($plugin_file);

        return is_wp_error($result) ? $result : true;
    }

    public function ensure_windpress_active() {
        $plugin_file = $this->environment->find_plugin_file_by_slug('windpress');

        if (!$plugin_file) {
            $install_result = $this->install_plugin_from_wporg('windpress');

            if (is_wp_error($install_result)) {
                return $install_result;
            }

            $plugin_file = $this->environment->find_plugin_file_by_slug('windpress');
        }

        if (!$plugin_file) {
            return new WP_Error('lcfa_missing_windpress', __('WindPress is still unavailable after the installation attempt.', 'livecanvas-forge-ai'));
        }

        if ($this->environment->is_plugin_active($plugin_file)) {
            return 'already-active';
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            return $result;
        }

        return 'activated';
    }

    private function install_framework_package(string $framework) {
        $urls = apply_filters('lcfa_framework_package_urls', []);
        $url  = '';
        $connections = LCFA_Settings::get_connections();

        if (is_array($urls) && isset($urls[$framework])) {
            $url = (string) $urls[$framework];
        }

        if ($url === '') {
            if ($framework === 'picowind') {
                $url = (string) $connections['picowind_package_url'];
            }

            if ($framework === 'picostrap') {
                $url = (string) $connections['picostrap_package_url'];
            }
        }

        if ($url === '') {
            return new WP_Error('lcfa_missing_package_url', __('The selected framework is missing a package URL.', 'livecanvas-forge-ai'));
        }

        $this->load_upgrader_dependencies();

        $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
        $result   = $upgrader->install($url);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            return new WP_Error('lcfa_theme_install_failed', __('Theme installation failed.', 'livecanvas-forge-ai'));
        }

        return true;
    }

    private function install_plugin_from_wporg(string $slug) {
        $this->load_upgrader_dependencies();
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $api = plugins_api(
            'plugin_information',
            [
                'slug'   => $slug,
                'fields' => [
                    'short_description' => false,
                    'sections'          => false,
                    'requires'          => false,
                    'rating'            => false,
                    'ratings'           => false,
                    'downloaded'        => false,
                    'last_updated'      => false,
                    'added'             => false,
                    'tags'              => false,
                    'compatibility'     => false,
                    'homepage'          => false,
                    'donate_link'       => false,
                ],
            ]
        );

        if (is_wp_error($api)) {
            return $api;
        }

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result   = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            return $result;
        }

        if (!$result) {
            return new WP_Error('lcfa_plugin_install_failed', __('Plugin installation failed.', 'livecanvas-forge-ai'));
        }

        return true;
    }

    private function load_upgrader_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }
}
