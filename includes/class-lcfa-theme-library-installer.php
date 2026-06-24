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
            $existing_theme = wp_get_theme($stylesheet);
            if ($existing_theme->exists()) {
                $this->delete_file($zip_path);
                switch_theme($stylesheet);

                return [
                    'ok'               => true,
                    'status'           => 'already_installed',
                    'message'          => __('Theme Library child theme was already installed and has been activated.', 'livecanvas-forge-ai'),
                    'theme'            => $theme,
                    'manifest'         => $manifest,
                    'theme_stylesheet' => $stylesheet,
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

        if ($stylesheet !== '') {
            $theme_object = wp_get_theme($stylesheet);
            if ($theme_object->exists()) {
                switch_theme($stylesheet);
            }
        }

        return [
            'ok'              => true,
            'message'         => __('Theme Library child theme installed and activated.', 'livecanvas-forge-ai'),
            'theme'           => $theme,
            'manifest'        => $manifest,
            'theme_stylesheet'=> $stylesheet,
        ];
    }

    public function download_theme_zip(array $theme): array {
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

    private function load_upgrader_dependencies(): void {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }

    private function delete_file(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
