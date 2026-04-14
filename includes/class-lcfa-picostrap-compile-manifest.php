<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Compile_Manifest {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function build(): array {
        $this->ensure_picostrap_helpers_loaded();

        $theme = wp_get_theme();
        $stylesheet = (string) $theme->get_stylesheet();
        $template = (string) $theme->get_template();
        $bundle_relative_path = 'wp-content/themes/' . $stylesheet . '/' . $this->get_bundle_relative_path();

        return [
            'framework' => 'picostrap',
            'stylesheet' => $stylesheet,
            'template' => $template,
            'is_child_theme' => $template !== '' && $template !== $stylesheet,
            'site_mode' => $this->environment->detect_site_mode(),
            'main_sass' => $this->get_main_sass(),
            'entry_virtual_file' => 'main.scss',
            'base_relative_dir' => 'sass',
            'import_roots' => $this->build_import_roots($stylesheet, $template),
            'target_bundle_relative_path' => $bundle_relative_path,
            'current_bundle_version' => (int) get_theme_mod('css_bundle_version_number', 0),
            'theme_mods' => $this->collect_theme_mods(),
            'source_map' => false,
            'compile_mode' => 'expanded',
        ];
    }

    private function build_import_roots(string $stylesheet, string $template): array {
        $roots = [
            [
                'origin' => 'child',
                'theme' => $stylesheet,
                'relative_root' => 'wp-content/themes/' . $stylesheet . '/sass',
            ],
        ];

        if ($template !== '' && $template !== $stylesheet) {
            $roots[] = [
                'origin' => 'parent',
                'theme' => $template,
                'relative_root' => 'wp-content/themes/' . $template . '/sass',
            ];
        }

        return $roots;
    }

    private function get_main_sass(): string {
        if (!function_exists('ps_get_main_sass')) {
            throw new RuntimeException(__('Picostrap Sass source builder is not available.', 'livecanvas-forge-ai'));
        }

        return (string) ps_get_main_sass();
    }

    private function collect_theme_mods(): array {
        $mods = function_exists('get_theme_mods') ? (array) get_theme_mods() : [];

        return array_filter($mods, static function ($value, $key): bool {
            return strpos((string) $key, 'SCSSvar_') === 0;
        }, ARRAY_FILTER_USE_BOTH);
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

        if (!function_exists('ps_get_main_sass')) {
            $compiler = trailingslashit(get_template_directory()) . 'inc/picosass-compiler-integration.php';

            if (is_readable($compiler)) {
                require_once $compiler;
            }
        }
    }
}
