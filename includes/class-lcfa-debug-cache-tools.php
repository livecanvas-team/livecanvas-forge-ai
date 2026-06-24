<?php

defined('ABSPATH') || exit;

final class LCFA_Debug_Cache_Tools {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_debug(array $payload = []): array {
        $limit = absint($payload['limit'] ?? 80);
        if ($limit < 10 || $limit > 300) {
            $limit = 80;
        }

        return [
            'ok' => true,
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'display_errors' => ini_get('display_errors'),
                'log_errors' => ini_get('log_errors'),
            ],
            'wordpress' => [
                'version' => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '',
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
                'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : '',
            ],
            'theme' => $this->get_theme_summary(),
            'plugins' => $this->get_plugin_summary(),
            'debug_log' => $this->read_debug_log($limit),
            'recent_runs' => $this->get_recent_runs(),
            'message' => __('Debug snapshot prepared.', 'livecanvas-forge-ai'),
        ];
    }

    public function flush_cache(array $payload = []): array {
        $dry_run = !empty($payload['dry_run']);
        $operations = [];

        $operations[] = [
            'name' => 'wp_cache_flush',
            'available' => function_exists('wp_cache_flush'),
            'executed' => !$dry_run && function_exists('wp_cache_flush') ? (bool) wp_cache_flush() : false,
        ];

        if (!$dry_run && function_exists('delete_expired_transients')) {
            delete_expired_transients(true);
            $operations[] = ['name' => 'delete_expired_transients', 'available' => true, 'executed' => true];
        } else {
            $operations[] = ['name' => 'delete_expired_transients', 'available' => function_exists('delete_expired_transients'), 'executed' => false];
        }

        $operations = array_merge($operations, $this->flush_common_cache_plugins($dry_run));

        $opcache_available = function_exists('opcache_reset');
        $opcache_executed = false;
        if (!$dry_run && $opcache_available) {
            $opcache_executed = @opcache_reset();
        }
        $operations[] = [
            'name' => 'opcache_reset',
            'available' => $opcache_available,
            'executed' => (bool) $opcache_executed,
        ];

        if (!$dry_run) {
            update_option('lcfa_asset_version_bump', time(), false);
        }

        return [
            'ok' => true,
            'dry_run' => $dry_run,
            'operations' => $operations,
            'asset_version_bump' => !$dry_run ? (int) get_option('lcfa_asset_version_bump', 0) : 0,
            'message' => $dry_run ? __('Cache flush preview prepared.', 'livecanvas-forge-ai') : __('Cache flush completed.', 'livecanvas-forge-ai'),
        ];
    }

    private function get_theme_summary(): array {
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
        if (!$theme) {
            return [];
        }

        return [
            'name' => (string) $theme->get('Name'),
            'stylesheet' => (string) $theme->get_stylesheet(),
            'template' => (string) $theme->get_template(),
            'version' => (string) $theme->get('Version'),
            'framework' => $this->environment->detect_framework_family(),
        ];
    }

    private function get_plugin_summary(): array {
        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('get_plugins')) {
            return [
                'active' => [],
                'count' => 0,
            ];
        }

        $plugins = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $items = [];
        foreach ($active as $plugin_file) {
            $items[] = [
                'file' => (string) $plugin_file,
                'name' => (string) ($plugins[$plugin_file]['Name'] ?? $plugin_file),
                'version' => (string) ($plugins[$plugin_file]['Version'] ?? ''),
            ];
        }

        return [
            'count' => count($items),
            'active' => $items,
        ];
    }

    private function read_debug_log(int $limit): array {
        $path = defined('WP_CONTENT_DIR') ? trailingslashit(WP_CONTENT_DIR) . 'debug.log' : '';
        if ($path === '' || !is_readable($path)) {
            return [
                'available' => false,
                'path' => $path,
                'lines' => [],
            ];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            $lines = [];
        }

        return [
            'available' => true,
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'modified_at' => gmdate('c', filemtime($path) ?: time()),
            'lines' => array_slice($lines, -$limit),
        ];
    }

    private function get_recent_runs(): array {
        if (!class_exists('LCFA_Settings', false)) {
            return [];
        }

        return array_slice(LCFA_Settings::get_history(), 0, 10);
    }

    private function flush_common_cache_plugins(bool $dry_run): array {
        $operations = [];

        $callbacks = [
            'rocket_clean_domain' => static function (): void {
                rocket_clean_domain();
            },
            'w3tc_flush_all' => static function (): void {
                w3tc_flush_all();
            },
            'wp_cache_clear_cache' => static function (): void {
                wp_cache_clear_cache();
            },
            'sg_cachepress_purge_cache' => static function (): void {
                sg_cachepress_purge_cache();
            },
        ];

        foreach ($callbacks as $name => $callback) {
            $available = function_exists($name);
            if ($available && !$dry_run) {
                try {
                    $callback();
                    $executed = true;
                } catch (Throwable $throwable) {
                    $executed = false;
                }
            } else {
                $executed = false;
            }
            $operations[] = [
                'name' => $name,
                'available' => $available,
                'executed' => $executed,
            ];
        }

        if (class_exists('autoptimizeCache')) {
            if (!$dry_run && method_exists('autoptimizeCache', 'clearall')) {
                autoptimizeCache::clearall();
            }
            $operations[] = [
                'name' => 'autoptimizeCache::clearall',
                'available' => method_exists('autoptimizeCache', 'clearall'),
                'executed' => !$dry_run && method_exists('autoptimizeCache', 'clearall'),
            ];
        }

        if (!$dry_run && function_exists('do_action')) {
            do_action('litespeed_purge_all');
            $operations[] = [
                'name' => 'litespeed_purge_all',
                'available' => true,
                'executed' => true,
            ];
        }

        return $operations;
    }
}
