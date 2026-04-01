<?php

defined('ABSPATH') || exit;

final class LCFA_Environment {
    public function get_snapshot(): array {
        $current_theme = wp_get_theme();

        return [
            'livecanvas_installed'    => $this->is_plugin_installed('livecanvas'),
            'livecanvas_active'       => $this->is_livecanvas_active(),
            'livecanvas_plugin_file'  => $this->find_plugin_file_by_slug('livecanvas'),
            'livecanvas_menu_slug'    => $this->get_livecanvas_menu_slug(),
            'current_theme_name'      => $current_theme->get('Name'),
            'current_theme_stylesheet'=> $current_theme->get_stylesheet(),
            'current_theme_template'  => $current_theme->get_template(),
            'detected_framework'      => $this->detect_framework_family(),
            'framework_slug'          => $this->get_livecanvas_editor_config_slug(),
            'site_mode'               => $this->detect_site_mode(),
            'windpress_installed'     => $this->is_plugin_installed('windpress'),
            'windpress_active'        => $this->is_windpress_active(),
            'tangible_available'      => function_exists('tangible_template'),
            'acf_active'              => function_exists('get_field') || class_exists('ACF'),
            'woocommerce_active'      => class_exists('WooCommerce'),
            'picostrap_candidates'    => $this->find_theme_candidates('picostrap'),
            'picowind_candidates'     => $this->find_theme_candidates('picowind'),
        ];
    }

    public function is_livecanvas_active(): bool {
        if (function_exists('lc_post_is_using_livecanvas')) {
            return true;
        }

        $plugin_file = $this->find_plugin_file_by_slug('livecanvas');

        return $plugin_file ? $this->is_plugin_active($plugin_file) : false;
    }

    public function is_windpress_active(): bool {
        $plugin_file = $this->find_plugin_file_by_slug('windpress');

        return $plugin_file ? $this->is_plugin_active($plugin_file) : false;
    }

    public function is_plugin_installed(string $slug): bool {
        return (bool) $this->find_plugin_file_by_slug($slug);
    }

    public function find_plugin_file_by_slug(string $slug): ?string {
        $this->load_plugin_api();

        foreach (get_plugins() as $file => $data) {
            $directory = dirname($file);
            $filename  = basename($file, '.php');
            $textdomain = $data['TextDomain'] ?? '';

            if ($directory === $slug || $filename === $slug || $textdomain === $slug) {
                return $file;
            }
        }

        return null;
    }

    public function get_livecanvas_menu_slug(): ?string {
        global $menu;

        if (!is_array($menu)) {
            return null;
        }

        foreach ($menu as $item) {
            if (!is_array($item) || !isset($item[0], $item[2])) {
                continue;
            }

            $label = wp_strip_all_tags((string) $item[0]);
            $slug  = (string) $item[2];

            if ($label !== '' && (stripos($label, 'livecanvas') !== false || stripos($slug, 'livecanvas') !== false)) {
                return $slug;
            }
        }

        return null;
    }

    public function get_livecanvas_editor_config_slug(): string {
        if (function_exists('lc_get_framework_slug')) {
            return (string) lc_get_framework_slug();
        }

        if (function_exists('lc_define_editor_config')) {
            return (string) lc_define_editor_config('config_file_slug');
        }

        return '';
    }

    public function detect_framework_family(): string {
        $theme      = wp_get_theme();
        $stylesheet = strtolower($theme->get_stylesheet());
        $template   = strtolower($theme->get_template());
        $name       = strtolower($theme->get('Name'));
        $slug       = strtolower($this->get_livecanvas_editor_config_slug());

        foreach ([$stylesheet, $template, $name, $slug] as $value) {
            if (strpos($value, 'picowind') !== false || strpos($value, 'daisyui') !== false) {
                return 'picowind';
            }

            if (strpos($value, 'picostrap') !== false || strpos($value, 'bootstrap') !== false) {
                return 'picostrap';
            }
        }

        return 'unknown';
    }

    public function detect_site_mode(): string {
        return $this->is_local_url(home_url('/')) ? 'local' : 'remote';
    }

    public function is_local_url(string $url): bool {
        $host = (string) wp_parse_url($url, PHP_URL_HOST);

        if ($host === '') {
            return false;
        }

        $host = strtolower($host);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        foreach (['.local', '.test', '.localhost', '.ddev.site', '.lndo.site'] as $suffix) {
            if (substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        if (preg_match('/^10\./', $host)) {
            return true;
        }

        if (preg_match('/^192\.168\./', $host)) {
            return true;
        }

        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host)) {
            return true;
        }

        return false;
    }

    public function find_theme_candidates(string $family): array {
        $themes     = wp_get_themes();
        $candidates = [];

        foreach ($themes as $stylesheet => $theme) {
            $haystack = strtolower(
                implode(
                    ' ',
                    [
                        $stylesheet,
                        $theme->get_template(),
                        $theme->get('Name'),
                        $theme->get('TextDomain'),
                    ]
                )
            );

            if (strpos($haystack, $family) === false) {
                continue;
            }

            $candidates[$stylesheet] = [
                'stylesheet' => $stylesheet,
                'name'       => $theme->get('Name'),
                'template'   => $theme->get_template(),
                'version'    => $theme->get('Version'),
                'is_child'   => $theme->parent() instanceof WP_Theme,
            ];
        }

        uasort($candidates, static function (array $left, array $right): int {
            if ($left['is_child'] === $right['is_child']) {
                return strcmp($left['stylesheet'], $right['stylesheet']);
            }

            return $left['is_child'] ? -1 : 1;
        });

        return array_values($candidates);
    }

    public function get_preferred_theme_stylesheet(string $family): ?string {
        $candidates = $this->find_theme_candidates($family);

        return $candidates[0]['stylesheet'] ?? null;
    }

    public function is_plugin_active(string $plugin_file): bool {
        $this->load_plugin_api();

        return is_plugin_active($plugin_file);
    }

    private function load_plugin_api(): void {
        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
