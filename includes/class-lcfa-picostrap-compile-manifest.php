<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Compile_Manifest {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function build(array $theme_mod_overrides = []): array {
        $this->ensure_picostrap_helpers_loaded();

        $theme = wp_get_theme();
        $stylesheet = (string) $theme->get_stylesheet();
        $template = (string) $theme->get_template();
        $bundle_theme_path = $this->get_bundle_relative_path();
        $bundle_relative_path = 'wp-content/themes/' . $stylesheet . '/' . $bundle_theme_path;
        $theme_mods = $this->collect_theme_mods($theme_mod_overrides);
        $main_sass = $this->build_main_sass($theme_mods);
        $source_tree_fingerprint = $this->build_source_tree_fingerprint();
        $source_fingerprint = hash('sha256', $main_sass . "\n" . $source_tree_fingerprint);
        $bundle_path = wp_normalize_path(trailingslashit(get_stylesheet_directory()) . $bundle_theme_path);
        $bundle_exists = is_file($bundle_path);
        $compiled_source_fingerprint = (string) get_theme_mod('lcfa_picostrap_compiled_source_fingerprint', '');
        $synchronization = $this->build_synchronization_status(
            $bundle_exists,
            $source_fingerprint,
            $compiled_source_fingerprint
        );

        return [
            'framework' => 'picostrap',
            'stylesheet' => $stylesheet,
            'template' => $template,
            'is_child_theme' => $template !== '' && $template !== $stylesheet,
            'site_mode' => $this->environment->detect_site_mode(),
            'main_sass' => $main_sass,
            'entry_virtual_file' => 'main.scss',
            'base_relative_dir' => 'sass',
            'import_roots' => $this->build_import_roots($stylesheet, $template),
            'target_bundle_relative_path' => $bundle_relative_path,
            'target_bundle_theme_path' => $bundle_theme_path,
            'bundle_exists' => $bundle_exists,
            'bundle_hash' => $bundle_exists ? (string) hash_file('sha256', $bundle_path) : '',
            'bundle_modified_at' => $bundle_exists ? gmdate('c', (int) filemtime($bundle_path)) : '',
            'current_bundle_version' => (int) get_theme_mod('css_bundle_version_number', 0),
            'theme_mods' => $theme_mods,
            'source_tree_fingerprint' => $source_tree_fingerprint,
            'source_fingerprint' => $source_fingerprint,
            'state_fingerprint' => $source_fingerprint,
            'compiled_source_fingerprint' => $compiled_source_fingerprint,
            'compiled_at' => (string) get_theme_mod('lcfa_picostrap_compiled_at', ''),
            'synchronization' => $synchronization,
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

    private function collect_theme_mods(array $overrides = []): array {
        $mods = function_exists('get_theme_mods') ? (array) get_theme_mods() : [];

        foreach ($overrides as $key => $value) {
            $key = (string) $key;
            if (strpos($key, 'SCSSvar_') === 0) {
                if ($value === null) {
                    unset($mods[$key]);
                } else {
                    $mods[$key] = $value;
                }
            }
        }

        $mods = array_filter($mods, static function ($value, $key): bool {
            return strpos((string) $key, 'SCSSvar_') === 0;
        }, ARRAY_FILTER_USE_BOTH);

        ksort($mods, SORT_STRING);

        return $mods;
    }

    private function build_main_sass(array $theme_mods): string {
        $sass = '';

        foreach ($theme_mods as $theme_mod_name => $theme_mod_value) {
            if (!is_scalar($theme_mod_value) || $theme_mod_value === '') {
                continue;
            }

            if (strpos((string) $theme_mod_name, 'enable-') !== false) {
                $theme_mod_value = !empty($theme_mod_value) ? 'true' : 'false';
            }

            $variable_name = str_replace('SCSSvar_', '$', (string) $theme_mod_name);
            $sass .= $variable_name . ': ' . (string) $theme_mod_value . '; ';
        }

        $sass .= " @import 'main'; ";

        return (string) apply_filters('ps_main_sass', $sass);
    }

    private function build_source_tree_fingerprint(): string {
        $roots = [
            'child' => get_stylesheet_directory(),
        ];
        $template_directory = get_template_directory();

        if (wp_normalize_path($template_directory) !== wp_normalize_path(get_stylesheet_directory())) {
            $roots['parent'] = $template_directory;
        }

        $entries = [];

        foreach ($roots as $origin => $theme_root) {
            $sass_root = wp_normalize_path(trailingslashit($theme_root) . 'sass');
            if (!is_dir($sass_root)) {
                $entries[$origin . ':missing'] = '';
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sass_root, FilesystemIterator::SKIP_DOTS)
                );
            } catch (Throwable $throwable) {
                $entries[$origin . ':unreadable'] = '';
                continue;
            }

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'scss') {
                    continue;
                }

                $absolute_path = wp_normalize_path($file->getPathname());
                if (strpos($absolute_path, trailingslashit($sass_root)) !== 0) {
                    continue;
                }

                $relative_path = ltrim(substr($absolute_path, strlen($sass_root)), '/');
                $entries[$origin . ':' . $relative_path] = (string) hash_file('sha256', $absolute_path);
            }
        }

        ksort($entries, SORT_STRING);

        return hash('sha256', (string) wp_json_encode($entries, JSON_UNESCAPED_SLASHES));
    }

    private function build_synchronization_status(bool $bundle_exists, string $source_fingerprint, string $compiled_source_fingerprint): array {
        if (!$bundle_exists) {
            $status = 'missing';
            $message = __('The Picostrap CSS bundle is missing.', 'livecanvas-forge-ai');
        } elseif ($compiled_source_fingerprint === '') {
            $status = 'unverified';
            $message = __('The Picostrap bundle exists, but AI Bridge cannot prove which Sass state produced it.', 'livecanvas-forge-ai');
        } elseif (hash_equals($compiled_source_fingerprint, $source_fingerprint)) {
            $status = 'synchronized';
            $message = __('Picostrap Customizer variables, Sass sources, and the compiled bundle are synchronized.', 'livecanvas-forge-ai');
        } else {
            $status = 'stale';
            $message = __('Picostrap Customizer variables or Sass sources changed after the current bundle was compiled.', 'livecanvas-forge-ai');
        }

        return [
            'status' => $status,
            'synchronized' => $status === 'synchronized',
            'build_required' => $status !== 'synchronized',
            'message' => $message,
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

        if (!function_exists('ps_get_main_sass')) {
            $compiler = trailingslashit(get_template_directory()) . 'inc/picosass-compiler-integration.php';

            if (is_readable($compiler)) {
                require_once $compiler;
            }
        }
    }
}
