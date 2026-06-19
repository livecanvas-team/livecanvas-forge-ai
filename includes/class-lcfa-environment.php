<?php

defined('ABSPATH') || exit;

final class LCFA_Environment {
    private ?array $snapshot_cache = null;
    private array $plugin_file_cache = [];
    private ?array $plugins_cache = null;
    private ?array $themes_cache = null;
    private ?string $livecanvas_menu_slug_cache = null;
    private array $theme_candidates_cache = [];

    public function get_snapshot(): array {
        if ($this->snapshot_cache !== null) {
            return $this->snapshot_cache;
        }

        $current_theme = wp_get_theme();

        $this->snapshot_cache = [
            'livecanvas_installed'    => $this->is_plugin_installed('livecanvas'),
            'livecanvas_active'       => $this->is_livecanvas_active(),
            'livecanvas_license_active' => $this->is_livecanvas_license_active(),
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
            'mcp_adapter'             => $this->get_mcp_adapter_status(),
        ];

        return $this->snapshot_cache;
    }

    public function get_mcp_adapter_status(): array {
        $adapter_class = 'WP\\MCP\\Core\\McpAdapter';
        $http_transport = 'WP\\MCP\\Transport\\HttpTransport';
        $error_handler = 'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler';
        $observability_handler = 'WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler';
        $classes = [
            'adapter'       => class_exists($adapter_class),
            'http_transport' => class_exists($http_transport),
            'error_handler' => class_exists($error_handler),
            'observability' => class_exists($observability_handler),
        ];
        $available = !in_array(false, $classes, true);

        return [
            'available' => $available,
            'classes'   => $classes,
            'custom_server' => [
                'id'        => 'livecanvas-forge-ai',
                'namespace' => 'livecanvas-forge-ai',
                'route'     => 'mcp',
                'url'       => $this->build_rest_url('livecanvas-forge-ai/mcp'),
            ],
            'remote_proxy' => [
                'package' => '@livecanvas/ai-bridge-mcp',
                'env'     => [
                    'LCFA_SITE_URL',
                    'LCFA_SITE_FINGERPRINT',
                    'LCFA_PROJECT_LABEL',
                ],
                'auth'    => 'ai_bridge_pairing',
                'legacy_package' => '@automattic/mcp-wordpress-remote',
            ],
        ];
    }

    public function is_livecanvas_active(): bool {
        if (function_exists('lc_post_is_using_livecanvas')) {
            return true;
        }

        $plugin_file = $this->find_plugin_file_by_slug('livecanvas');

        return $plugin_file ? $this->is_plugin_active($plugin_file) : false;
    }

    public function is_livecanvas_license_active(): bool {
        if (function_exists('lc_get_apikey')) {
            $api_key = lc_get_apikey();

            return is_scalar($api_key) && trim((string) $api_key) !== '';
        }

        if (function_exists('get_site_option')) {
            $api_key = get_site_option('lc_apikey');

            return is_scalar($api_key) && trim((string) $api_key) !== '';
        }

        return false;
    }

    public function is_windpress_active(): bool {
        $plugin_file = $this->find_plugin_file_by_slug('windpress');

        return $plugin_file ? $this->is_plugin_active($plugin_file) : false;
    }

    public function is_plugin_installed(string $slug): bool {
        return (bool) $this->find_plugin_file_by_slug($slug);
    }

    public function find_plugin_file_by_slug(string $slug): ?string {
        if (array_key_exists($slug, $this->plugin_file_cache)) {
            return $this->plugin_file_cache[$slug];
        }

        $this->load_plugin_api();

        foreach ($this->get_plugins_index() as $file => $data) {
            $directory = dirname($file);
            $filename  = basename($file, '.php');
            $textdomain = $data['TextDomain'] ?? '';

            if ($directory === $slug || $filename === $slug || $textdomain === $slug) {
                $this->plugin_file_cache[$slug] = $file;

                return $file;
            }
        }

        $this->plugin_file_cache[$slug] = null;

        return null;
    }

    public function get_livecanvas_menu_slug(): ?string {
        if ($this->livecanvas_menu_slug_cache !== null) {
            return $this->livecanvas_menu_slug_cache;
        }

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
                $this->livecanvas_menu_slug_cache = $slug;

                return $slug;
            }
        }

        $this->livecanvas_menu_slug_cache = '';

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

    private function build_rest_url(string $path): string {
        $path = ltrim($path, '/');

        if (function_exists('rest_url')) {
            return rest_url($path);
        }

        if (function_exists('home_url')) {
            return trailingslashit(home_url('/')) . 'wp-json/' . $path;
        }

        return '/wp-json/' . $path;
    }

    public function find_theme_candidates(string $family): array {
        if (array_key_exists($family, $this->theme_candidates_cache)) {
            return $this->theme_candidates_cache[$family];
        }

        $themes     = $this->get_themes_index();
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

        $this->theme_candidates_cache[$family] = array_values($candidates);

        return $this->theme_candidates_cache[$family];
    }

    public function get_preferred_theme_stylesheet(string $family): ?string {
        $candidates = $this->find_theme_candidates($family);

        return $candidates[0]['stylesheet'] ?? null;
    }

    public function refresh_theme_caches(): void {
        if (function_exists('wp_clean_themes_cache')) {
            wp_clean_themes_cache();
        }

        $this->snapshot_cache          = null;
        $this->themes_cache            = null;
        $this->theme_candidates_cache  = [];
    }

    public function refresh_plugin_caches(): void {
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(false);
        }

        $this->snapshot_cache      = null;
        $this->plugins_cache       = null;
        $this->plugin_file_cache   = [];
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

    private function get_plugins_index(): array {
        if ($this->plugins_cache !== null) {
            return $this->plugins_cache;
        }

        $this->plugins_cache = get_plugins();

        return $this->plugins_cache;
    }

    private function get_themes_index(): array {
        if ($this->themes_cache !== null) {
            return $this->themes_cache;
        }

        $this->themes_cache = wp_get_themes();

        return $this->themes_cache;
    }
}
