<?php

declare(strict_types=1);

$test_root = sys_get_temp_dir() . '/lcfa-theme-parent-guard-' . getmypid();
define('ABSPATH', $test_root . '/wordpress/');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

$GLOBALS['lcfa_test_theme_root'] = WP_CONTENT_DIR . '/themes';
$GLOBALS['lcfa_test_stylesheet'] = 'parent-theme';
$GLOBALS['lcfa_test_template'] = 'parent-theme';
$GLOBALS['lcfa_allow_parent_theme_writes'] = false;

function __(string $text, string $domain = ''): string { return $text; }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function trailingslashit(string $value): string { return untrailingslashit($value) . '/'; }
function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
function get_theme_root(string $stylesheet = ''): string { return $GLOBALS['lcfa_test_theme_root']; }
function get_stylesheet_directory(): string { return $GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet']; }
function get_template_directory(): string { return $GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_template']; }
function wp_get_upload_dir(): array { return ['basedir' => WP_CONTENT_DIR . '/uploads']; }
function wp_json_encode($value, int $flags = 0, int $depth = 512): string { return (string) json_encode($value, $flags, $depth); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_file_name(string $value): string { return preg_replace('/[^A-Za-z0-9._-]/', '-', $value) ?: ''; }
function wp_mkdir_p(string $path): bool { return is_dir($path) || mkdir($path, 0777, true); }
function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string { return substr(str_repeat('themeaudit', 3), 0, $length); }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 10:00:00'; }
function apply_filters(string $hook, $value, ...$args) {
    if ($hook === 'lcfa_allow_parent_theme_writes') {
        return $GLOBALS['lcfa_allow_parent_theme_writes'];
    }

    return $value;
}

final class LCFA_Test_Theme {
    public function get_stylesheet(): string { return $GLOBALS['lcfa_test_stylesheet']; }
    public function get_template(): string { return $GLOBALS['lcfa_test_template']; }
}

function wp_get_theme(): LCFA_Test_Theme { return new LCFA_Test_Theme(); }

final class LCFA_Environment {
    public function detect_framework_family(): string { return 'picostrap'; }
    public function detect_site_mode(): string { return 'remote'; }
}

final class LCFA_Settings {
    public static array $records = [];

    public static function store_rollback_record(string $audit_id, array $record): void {
        self::$records[$audit_id] = $record;
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-files-bridge.php';

function lcfa_theme_guard_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

wp_mkdir_p(get_stylesheet_directory());
file_put_contents(get_stylesheet_directory() . '/style.css', '/* parent */');

$bridge = new LCFA_Theme_Files_Bridge(new LCFA_Environment());
$parent_preview = $bridge->write_file([
    'root_scope' => 'stylesheet',
    'path' => 'style.css',
    'content' => '/* changed */',
    'dry_run' => true,
]);

lcfa_theme_guard_assert(!empty($parent_preview['ok']), 'A parent-theme preview should return a guided result.');
lcfa_theme_guard_assert(($parent_preview['writable'] ?? true) === false, 'A parent theme must be read-only by default.');
lcfa_theme_guard_assert(($parent_preview['status'] ?? '') === 'parent_theme_read_only', 'The preview should identify the parent-theme guard.');

$parent_apply_failed = false;
try {
    $bridge->write_file([
        'root_scope' => 'stylesheet',
        'path' => 'style.css',
        'content' => '/* changed */',
    ]);
} catch (RuntimeException $exception) {
    $parent_apply_failed = str_contains($exception->getMessage(), 'parent theme');
}
lcfa_theme_guard_assert($parent_apply_failed, 'A parent-theme apply must be rejected.');
lcfa_theme_guard_assert(file_get_contents(get_stylesheet_directory() . '/style.css') === '/* parent */', 'Rejected parent writes must not alter the file.');

$GLOBALS['lcfa_test_stylesheet'] = 'child-theme';
$GLOBALS['lcfa_test_template'] = 'parent-theme';
wp_mkdir_p(get_stylesheet_directory());
file_put_contents(get_stylesheet_directory() . '/style.css', '/* child */');

$child_apply = $bridge->write_file([
    'root_scope' => 'stylesheet',
    'path' => 'style.css',
    'content' => '/* child changed */',
]);
lcfa_theme_guard_assert(!empty($child_apply['ok']) && !empty($child_apply['writable']), 'The active child theme should remain writable.');
lcfa_theme_guard_assert(file_get_contents(get_stylesheet_directory() . '/style.css') === '/* child changed */', 'The child-theme write should update the file.');
lcfa_theme_guard_assert(!empty($child_apply['audit_id']), 'A child-theme write should return an audit ID.');
lcfa_theme_guard_assert(!empty($child_apply['rollback_available']), 'A child-theme write should expose rollback.');
lcfa_theme_guard_assert((LCFA_Settings::$records[$child_apply['audit_id']]['restore']['type'] ?? '') === 'theme_file_write', 'The write should store a theme-file rollback record.');

$rollback_preview = $bridge->rollback_write(array_merge(
    LCFA_Settings::$records[$child_apply['audit_id']]['restore'],
    ['dry_run' => true]
));
lcfa_theme_guard_assert(($rollback_preview['operation'] ?? '') === 'restore_backup', 'An updated file should preview a backup restore.');

$rollback_apply = $bridge->rollback_write(LCFA_Settings::$records[$child_apply['audit_id']]['restore']);
lcfa_theme_guard_assert(!empty($rollback_apply['ok']), 'Theme-file rollback should succeed.');
lcfa_theme_guard_assert(file_get_contents(get_stylesheet_directory() . '/style.css') === '/* child */', 'Theme-file rollback should restore the previous content.');

$created_apply = $bridge->write_file([
    'root_scope' => 'stylesheet',
    'path' => 'assets/generated.css',
    'content' => '/* generated */',
]);
lcfa_theme_guard_assert(file_exists(get_stylesheet_directory() . '/assets/generated.css'), 'A new child-theme file should be created.');
$created_rollback = $bridge->rollback_write(LCFA_Settings::$records[$created_apply['audit_id']]['restore']);
lcfa_theme_guard_assert(!empty($created_rollback['ok']) && !file_exists(get_stylesheet_directory() . '/assets/generated.css'), 'Rollback should delete a file created by AI Bridge.');

$template_preview = $bridge->write_file([
    'root_scope' => 'template',
    'path' => 'style.css',
    'content' => '/* parent through template scope */',
    'dry_run' => true,
]);
lcfa_theme_guard_assert(($template_preview['writable'] ?? true) === false, 'Explicit template-root writes should also be blocked.');

$GLOBALS['lcfa_allow_parent_theme_writes'] = true;
$parent_opt_in = $bridge->write_file([
    'root_scope' => 'template',
    'path' => 'style.css',
    'content' => '/* explicit opt-in */',
]);
lcfa_theme_guard_assert(!empty($parent_opt_in['ok']) && !empty($parent_opt_in['writable']), 'The explicit parent-theme filter should allow trusted writes.');

echo "PASS\n";
