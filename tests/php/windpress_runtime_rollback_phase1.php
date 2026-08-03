<?php

declare(strict_types=1);

namespace {
    error_reporting(E_ALL);

    $GLOBALS['lcfa_windpress_test_root'] = sys_get_temp_dir() . '/lcfa-windpress-runtime-' . uniqid('', true);
    $GLOBALS['lcfa_windpress_uploads'] = $GLOBALS['lcfa_windpress_test_root'] . '/custom-uploads';
    $GLOBALS['lcfa_windpress_options'] = [];
    $GLOBALS['lcfa_windpress_cache_flushed'] = 0;

    define('ABSPATH', $GLOBALS['lcfa_windpress_test_root'] . '/');
    define('WP_CONTENT_DIR', $GLOBALS['lcfa_windpress_test_root'] . '/wp-content');

    function __(string $text, string $domain = ''): string { return $text; }
    function sanitize_key(string $key): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
    function sanitize_file_name(string $name): string { return basename(str_replace('\\', '/', $name)); }
    function trailingslashit(string $path): string { return rtrim($path, '/\\') . '/'; }
    function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
    function wp_mkdir_p(string $path): bool { return is_dir($path) || mkdir($path, 0777, true); }
    function wp_upload_dir($time = null, bool $create_dir = true, bool $refresh_cache = false): array {
        $basedir = $GLOBALS['lcfa_windpress_uploads'];
        if ($create_dir) {
            wp_mkdir_p($basedir);
        }
        return ['basedir' => $basedir, 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false];
    }
    function current_time(string $type, bool $gmt = false): string { return '2026-08-02 00:00:00'; }
    function get_option(string $name, $default = false) { return array_key_exists($name, $GLOBALS['lcfa_windpress_options']) ? $GLOBALS['lcfa_windpress_options'][$name] : $default; }
    function update_option(string $name, $value, bool $autoload = false): bool { $GLOBALS['lcfa_windpress_options'][$name] = $value; return true; }
    function delete_option(string $name): bool { unset($GLOBALS['lcfa_windpress_options'][$name]); return true; }
    function wp_cache_flush(): bool { $GLOBALS['lcfa_windpress_cache_flushed']++; return true; }

    class LCFA_Environment {
        public function is_plugin_installed(string $slug): bool { return $slug === 'windpress'; }
        public function is_windpress_active(): bool { return true; }
    }

    class WIND_PRESS {
        public const WP_OPTION = 'windpress';
    }
}

namespace WindPress\WindPress\Core {
    final class Volume {}

    final class Cache {
        public const CSS_CACHE_FILE = 'tailwind.css';
        public const CSS_SOURCEMAP_FILE = 'tailwind.css.map';
        public const THEME_JSON_FILE = 'theme.json';

        public static function get_cache_path(string $file = ''): string {
            return \wp_upload_dir(null, false)['basedir'] . '/windpress/cache/' . $file;
        }

        public static function get_cache_url(string $file = ''): string {
            return 'https://example.test/wp-content/uploads/windpress/cache/' . $file;
        }
    }
}

namespace WindPress\WindPress\Utils {
    final class Cache {
        public static function flush_cache_plugin(): void {
            $GLOBALS['lcfa_windpress_cache_flushed']++;
        }
    }
}

namespace {
    function lcfa_windpress_assert(bool $condition, string $message): void {
        if (!$condition) {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
    }

    function lcfa_windpress_remove_tree(string $path): void {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child)) {
                lcfa_windpress_remove_tree($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }

    require dirname(__DIR__, 2) . '/includes/class-lcfa-windpress-bridge.php';

    $main_css = wp_upload_dir(null, false)['basedir'] . '/windpress/data/main.css';
    $cache_css = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE);
    $sourcemap = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_SOURCEMAP_FILE);
    $theme_json = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::THEME_JSON_FILE);
    wp_mkdir_p(dirname($main_css));
    wp_mkdir_p(dirname($cache_css));
    file_put_contents($main_css, 'original-main');
    file_put_contents($cache_css, 'original-cache');
    file_put_contents($sourcemap, 'original-map');
    update_option('windpress_options', '{"integration":{"picowind":{"enabled":false}}}', false);

    $bridge = new LCFA_WindPress_Bridge(new LCFA_Environment());
    $state = $bridge->capture_runtime_state('theme-import-test-123');
    lcfa_windpress_assert(!empty($state['ok']) && !empty($state['available']), 'WindPress runtime backup should be captured.');
    lcfa_windpress_assert(empty($state['files']['theme_json']['exists']), 'Missing runtime files should be recorded as missing.');
    lcfa_windpress_assert(!isset($state['files']['cache_css']['content']), 'Large runtime files must not be stored in the rollback option payload.');

    file_put_contents($main_css, 'changed-main');
    file_put_contents($cache_css, 'changed-cache');
    file_put_contents($sourcemap, 'changed-map');
    file_put_contents($theme_json, '{"changed":true}');
    update_option('windpress_options', '{"integration":{"picowind":{"enabled":true}}}', false);

    $restored = $bridge->restore_runtime_state($state);
    lcfa_windpress_assert(!empty($restored['ok']), 'WindPress runtime backup should restore without errors.');
    lcfa_windpress_assert(file_get_contents($main_css) === 'original-main', 'WindPress main.css should be restored.');
    lcfa_windpress_assert(file_get_contents($cache_css) === 'original-cache', 'WindPress cache CSS should be restored.');
    lcfa_windpress_assert(file_get_contents($sourcemap) === 'original-map', 'WindPress sourcemap should be restored.');
    lcfa_windpress_assert(!is_file($theme_json), 'A theme.json created by the import should be removed by rollback.');
    lcfa_windpress_assert(get_option('windpress_options') === '{"integration":{"picowind":{"enabled":false}}}', 'WindPress options should be restored.');
    lcfa_windpress_assert($GLOBALS['lcfa_windpress_cache_flushed'] >= 2, 'Runtime caches should be flushed after restore.');

    $backup_root = wp_upload_dir(null, false)['basedir'] . '/livecanvas-forge-ai/windpress-runtime-backups';
    $backup_directory = $backup_root . '/theme-import-test-123';
    lcfa_windpress_assert(is_dir($backup_directory), 'Captured WindPress runtime backup should exist before cleanup.');
    $deleted = $bridge->delete_runtime_backup($state);
    lcfa_windpress_assert(!empty($deleted['ok']) && !empty($deleted['removed']), 'WindPress runtime backup cleanup should succeed.');
    lcfa_windpress_assert(!is_dir($backup_directory), 'WindPress runtime backup directory should be removed after cleanup.');
    $deleted_again = $bridge->delete_runtime_backup($state);
    lcfa_windpress_assert(!empty($deleted_again['ok']) && !empty($deleted_again['skipped']), 'Repeated backup cleanup should be idempotent.');

    $tampered = $bridge->capture_runtime_state('theme-import-tampered');
    file_put_contents($backup_root . '/theme-import-tampered/cache_css.bak', 'tampered');
    file_put_contents($cache_css, 'must-remain-on-checksum-error');
    $tampered_restore = $bridge->restore_runtime_state($tampered);
    lcfa_windpress_assert(empty($tampered_restore['ok']), 'A tampered WindPress backup must fail checksum validation.');
    lcfa_windpress_assert(file_get_contents($cache_css) === 'must-remain-on-checksum-error', 'A failed checksum must not overwrite the live cache file.');

    file_put_contents($main_css, "\n@import \"./@picowind/tailwind.css\";\n");
    $initialized = $bridge->ensure_picowind_runtime();
    $normalized_entrypoint = (string) file_get_contents($main_css);
    lcfa_windpress_assert(!empty($initialized['ok']), 'Picowind runtime initialization should succeed.');
    lcfa_windpress_assert(str_contains($normalized_entrypoint, '@import "tailwindcss/theme.css" layer(theme) theme(static);'), 'Picowind initialization must restore the Tailwind 4 theme import.');
    lcfa_windpress_assert(str_contains($normalized_entrypoint, '@import "tailwindcss/utilities.css" layer(utilities);'), 'Picowind initialization must restore Tailwind utility generation.');
    lcfa_windpress_assert(substr_count($normalized_entrypoint, '@import "./@picowind/tailwind.css";') === 1, 'Picowind initialization should keep exactly one child-theme import.');

    file_put_contents($cache_css, '.sample-theme-marker{display:block}');
    $semantic_ready = $bridge->get_compiled_cache_state([
        'required_fragments' => ['.sample-theme-marker'],
        'forbidden_fragments' => ['.foreign-theme-marker'],
    ]);
    lcfa_windpress_assert(!empty($semantic_ready['ready']), 'Compiled CSS should pass when all package-bound semantic fragments match.');
    $semantic_failed = $bridge->get_compiled_cache_state([
        'required_fragments' => ['.missing-theme-marker'],
    ]);
    lcfa_windpress_assert(empty($semantic_failed['ready']) && ($semantic_failed['status'] ?? '') === 'semantic_mismatch', 'Compiled CSS should fail readiness when a required theme fragment is missing.');

    lcfa_windpress_remove_tree($GLOBALS['lcfa_windpress_test_root']);
    echo "PASS\n";
}
