<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Installer {
    private LCFA_Theme_Library_Validator $validator;

    public function __construct(LCFA_Theme_Library_Validator $validator) {
        $this->validator = $validator;
    }

    public function preview(array $theme): array {
        $download = $this->download_theme_zip($theme);
        if (empty($download['ok'])) {
            return $download;
        }

        $validation = $this->validator->validate_zip((string) $download['zip_path'], $theme);
        $this->delete_file((string) $download['zip_path']);

        if (empty($validation['ok'])) {
            return $validation;
        }

        return [
            'ok'           => true,
            'theme'        => $theme,
            'checksum'     => (string) ($validation['checksum'] ?? ''),
            'preview_plan' => $validation['preview_plan'] ?? [],
            'manifest'     => $validation['manifest'] ?? [],
        ];
    }

    public function install(array $theme): array {
        $download = $this->download_theme_zip($theme);
        if (empty($download['ok'])) {
            return $download;
        }

        $zip_path = (string) $download['zip_path'];
        $validation = $this->validator->validate_zip($zip_path, $theme);
        if (empty($validation['ok'])) {
            $this->delete_file($zip_path);
            return $validation;
        }

        $manifest = is_array($validation['manifest'] ?? null) ? $validation['manifest'] : [];
        $stylesheet = sanitize_key((string) ($manifest['theme']['stylesheet'] ?? $manifest['theme']['slug'] ?? $theme['slug'] ?? ''));

        if ($stylesheet !== '') {
            $installed_stylesheet = $this->resolve_installed_stylesheet($stylesheet, $manifest, $theme);
            $existing_theme = wp_get_theme($installed_stylesheet);
            if ($existing_theme->exists()) {
                $this->delete_file($zip_path);
                switch_theme($installed_stylesheet);

                return [
                    'ok'               => true,
                    'status'           => 'already_installed',
                    'message'          => __('Theme Library child theme was already installed and has been activated.', 'livecanvas-forge-ai'),
                    'theme'            => $theme,
                    'manifest'         => $manifest,
                    'theme_stylesheet' => $installed_stylesheet,
                ];
            }
        }

        $this->load_upgrader_dependencies();
        $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->install($zip_path);
        $this->delete_file($zip_path);

        if (is_wp_error($result)) {
            return [
                'ok'      => false,
                'message' => $result->get_error_message(),
            ];
        }

        if (!$result && $stylesheet === '') {
            return [
                'ok'      => false,
                'message' => __('Theme installation failed.', 'livecanvas-forge-ai'),
            ];
        }

        $installed_stylesheet = $stylesheet !== '' ? $this->resolve_installed_stylesheet($stylesheet, $manifest, $theme) : '';
        if ($installed_stylesheet !== '') {
            $theme_object = wp_get_theme($installed_stylesheet);
            if ($theme_object->exists()) {
                switch_theme($installed_stylesheet);
            }
        }

        return [
            'ok'              => true,
            'message'         => __('Theme Library child theme installed and activated.', 'livecanvas-forge-ai'),
            'theme'           => $theme,
            'manifest'        => $manifest,
            'theme_stylesheet'=> $installed_stylesheet !== '' ? $installed_stylesheet : $stylesheet,
        ];
    }

    public function download_theme_zip(array $theme): array {
        $local_path = $this->resolve_local_package_path((string) ($theme['package_path'] ?? ''));
        if ($local_path !== '') {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $tmp = wp_tempnam(basename($local_path));
            if ($tmp && copy($local_path, $tmp)) {
                return [
                    'ok'       => true,
                    'zip_path' => (string) $tmp,
                    'source'   => 'local_package_path',
                ];
            }

            return [
                'ok'      => false,
                'message' => __('Local Theme Library package could not be copied to a temporary file.', 'livecanvas-forge-ai'),
            ];
        }

        $url = esc_url_raw((string) ($theme['package_url'] ?? ''));
        if ($url === '') {
            return [
                'ok'      => false,
                'message' => __('Theme package URL is missing.', 'livecanvas-forge-ai'),
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $zip_path = download_url($url, 30);
        if (is_wp_error($zip_path)) {
            return [
                'ok'      => false,
                'message' => $zip_path->get_error_message(),
            ];
        }

        return [
            'ok'       => true,
            'zip_path' => (string) $zip_path,
        ];
    }

    private function resolve_local_package_path(string $relative_path): string {
        if ($relative_path === '' || !defined('LCFA_DIR')) {
            return '';
        }

        $relative_path = str_replace('\\', '/', trim($relative_path));
        $relative_path = ltrim($relative_path, '/');
        if ($relative_path === '' || strpos($relative_path, '..') !== false) {
            return '';
        }

        $base = realpath(LCFA_DIR);
        $path = realpath(trailingslashit(LCFA_DIR) . $relative_path);
        if (!$base || !$path || strpos($path, $base) !== 0 || !is_readable($path)) {
            return '';
        }

        return $path;
    }

    private function load_upgrader_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }

    private function resolve_installed_stylesheet(string $stylesheet, array $manifest, array $theme): string {
        if ($stylesheet !== '' && wp_get_theme($stylesheet)->exists()) {
            return $stylesheet;
        }

        $expected_name = sanitize_text_field((string) ($manifest['theme']['name'] ?? $theme['name'] ?? ''));
        $expected_slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? $stylesheet));
        $expected_text_domain = sanitize_key((string) ($manifest['theme']['text_domain'] ?? $expected_slug));

        foreach (wp_get_themes() as $candidate_stylesheet => $candidate) {
            if (!$candidate->exists()) {
                continue;
            }

            $candidate_name = sanitize_text_field((string) $candidate->get('Name'));
            $candidate_text_domain = sanitize_key((string) $candidate->get('TextDomain'));
            $candidate_template = sanitize_key((string) $candidate->get_template());
            $candidate_key = sanitize_key((string) $candidate_stylesheet);

            if ($expected_name !== '' && strcasecmp($candidate_name, $expected_name) === 0 && $candidate_template === 'picowind') {
                return (string) $candidate_stylesheet;
            }

            if ($expected_text_domain !== '' && $candidate_text_domain === $expected_text_domain) {
                return (string) $candidate_stylesheet;
            }

            if ($expected_slug !== '' && strpos($candidate_key, $expected_slug . '-') === 0) {
                return (string) $candidate_stylesheet;
            }
        }

        return $stylesheet;
    }

    private function delete_file(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
