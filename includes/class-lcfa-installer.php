<?php

defined('ABSPATH') || exit;

final class LCFA_Installer {
    private const PICOWIND_LATEST_RELEASE_API = 'https://api.github.com/repos/livecanvas-team/picowind/releases/latest';

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

            if (method_exists($this->environment, 'refresh_theme_caches')) {
                $this->environment->refresh_theme_caches();
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

            if (method_exists($this->environment, 'refresh_plugin_caches')) {
                $this->environment->refresh_plugin_caches();
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
        $url = $this->resolve_framework_package_url($framework);

        if (is_wp_error($url)) {
            return $url;
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

    private function resolve_framework_package_url(string $framework) {
        $urls        = apply_filters('lcfa_framework_package_urls', []);
        $url         = '';
        $connections = LCFA_Settings::get_connections();

        if (is_array($urls) && isset($urls[$framework])) {
            $url = (string) $urls[$framework];
        }

        if ($url === '') {
            if ($framework === 'picowind') {
                $url = (string) ($connections['picowind_package_url'] ?? '');
            }

            if ($framework === 'picostrap') {
                $url = (string) ($connections['picostrap_package_url'] ?? '');
            }
        }

        if ($url !== '') {
            return $this->normalize_package_url($url);
        }

        if ($framework === 'picowind') {
            return $this->fetch_latest_picowind_package_url();
        }

        return new WP_Error('lcfa_missing_package_url', __('The selected framework is missing a package URL.', 'livecanvas-forge-ai'));
    }

    private function fetch_latest_picowind_package_url() {
        $response = wp_remote_get(
            self::PICOWIND_LATEST_RELEASE_API,
            [
                'timeout' => 20,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'LiveCanvas Forge AI/' . (defined('LCFA_VERSION') ? LCFA_VERSION : 'unknown'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error('lcfa_picowind_release_unavailable', __('Picowind latest release could not be reached from GitHub.', 'livecanvas-forge-ai'));
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($payload)) {
            return new WP_Error('lcfa_picowind_release_invalid', __('Picowind latest release returned an invalid response.', 'livecanvas-forge-ai'));
        }

        $asset_url = $this->find_zip_asset_url((array) ($payload['assets'] ?? []), 'picowind');

        if ($asset_url === '' && !empty($payload['zipball_url'])) {
            $asset_url = (string) $payload['zipball_url'];
        }

        if ($asset_url === '') {
            return new WP_Error('lcfa_picowind_release_missing_zip', __('Picowind latest release does not include a downloadable zip asset.', 'livecanvas-forge-ai'));
        }

        return $this->normalize_package_url($asset_url);
    }

    private function find_zip_asset_url(array $assets, string $preferred_name_fragment): string {
        $fallback_url = '';

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = strtolower((string) ($asset['name'] ?? ''));
            $url  = (string) ($asset['browser_download_url'] ?? '');

            if ($url === '' || !$this->string_ends_with(strtolower($url), '.zip')) {
                continue;
            }

            if ($fallback_url === '') {
                $fallback_url = $url;
            }

            if ($name !== '' && strpos($name, strtolower($preferred_name_fragment)) !== false) {
                return $url;
            }
        }

        return $fallback_url;
    }

    private function normalize_package_url(string $url): string {
        $url = trim($url);

        if (function_exists('esc_url_raw')) {
            return esc_url_raw($url);
        }

        return $url;
    }

    private function string_ends_with(string $value, string $suffix): bool {
        if ($suffix === '') {
            return true;
        }

        return substr($value, -strlen($suffix)) === $suffix;
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
