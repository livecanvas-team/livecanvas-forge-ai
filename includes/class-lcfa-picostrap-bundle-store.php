<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Bundle_Store {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function store(string $css): array {
        $this->ensure_picostrap_helpers_loaded();

        $target_relative = $this->get_bundle_relative_path();
        $target_path = trailingslashit(get_stylesheet_directory()) . $target_relative;

        if (!wp_mkdir_p(dirname($target_path))) {
            return [
                'ok' => false,
                'message' => __('Unable to create the Picostrap bundle directory.', 'livecanvas-forge-ai'),
            ];
        }

        $written = file_put_contents($target_path, $css);

        if ($written === false) {
            return [
                'ok' => false,
                'message' => __('Unable to write Picostrap bundle.', 'livecanvas-forge-ai'),
            ];
        }

        $version = (int) get_theme_mod('css_bundle_version_number', 0);
        $version = $version > 0 ? $version + 1 : 1;
        set_theme_mod('css_bundle_version_number', $version);

        $bundle_url = trailingslashit(get_stylesheet_directory_uri()) . trim($target_relative, '/\\') . '?ver=' . $version;

        return [
            'ok' => true,
            'bundle_path' => $target_path,
            'bundle_url' => $bundle_url,
            'bundle_version' => $version,
            'compiled_at' => current_time('mysql', true),
        ];
    }

    private function get_bundle_relative_path(): string {
        $subfolder = function_exists('picostrap_get_css_optional_subfolder_name')
            ? (string) picostrap_get_css_optional_subfolder_name()
            : 'css-output/';
        $filename = function_exists('picostrap_get_complete_css_filename')
            ? (string) picostrap_get_complete_css_filename()
            : 'bundle.css';

        return trim($subfolder, '/\\') . '/' . ltrim($filename, '/\\');
    }

    private function ensure_picostrap_helpers_loaded(): void {
        if (!function_exists('picostrap_get_complete_css_filename')) {
            $enqueues = trailingslashit(get_template_directory()) . 'inc/enqueues.php';

            if (is_readable($enqueues)) {
                require_once $enqueues;
            }
        }
    }
}
