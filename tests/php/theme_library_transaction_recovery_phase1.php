<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-theme-library-recovery-tests/');

$GLOBALS['lcfa_recovery_options'] = [
    'lcfa_theme_library_imports' => [
        'sample-theme' => [
            'slug' => 'sample-theme',
            'audit_id' => 'auto-success',
            'status' => 'failed',
        ],
    ],
];

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $key): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
function absint($value): int { return abs((int) $value); }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 10:00:00'; }
function get_option(string $key, $default = false) { return $GLOBALS['lcfa_recovery_options'][$key] ?? $default; }
function update_option(string $key, $value, bool $autoload = false): bool { $GLOBALS['lcfa_recovery_options'][$key] = $value; return true; }
function delete_option(string $key): bool { unset($GLOBALS['lcfa_recovery_options'][$key]); return true; }
function is_wp_error($value): bool { return false; }
function get_post(int $post_id) { return null; }
function wp_trash_post(int $post_id): bool { return true; }
function wp_delete_attachment(int $attachment_id, bool $force = false): bool { return true; }
function wp_delete_nav_menu(int $menu_id): bool { return true; }
function set_theme_mod(string $name, $value): void {}

final class LCFA_Settings {
    public static array $records = [
        'auto-success' => [
            'type' => 'theme_library_import',
            'previous_theme' => '',
            'updated_options' => [],
            'updated_posts' => [],
            'created_posts' => [],
            'created_media' => [],
            'created_menus' => [],
            'previous_theme_mods' => [],
            'windpress_runtime' => ['available' => false],
        ],
    ];

    public static array $restored = [];

    public static function get_rollback_record(string $audit_id): ?array {
        return self::$records[$audit_id] ?? null;
    }

    public static function mark_rollback_record_restored(string $audit_id, array $result): void {
        self::$restored[$audit_id] = $result;
    }
}

function lcfa_recovery_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-rollback.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php';

$importer_reflection = new ReflectionClass(LCFA_Theme_Library_Importer::class);
$importer = $importer_reflection->newInstanceWithoutConstructor();
$rollback_property = $importer_reflection->getProperty('rollback_service');
$rollback_property->setValue($importer, new LCFA_Theme_Library_Rollback());
$recover = $importer_reflection->getMethod('recover_failed_import');

$success = $recover->invoke($importer, 'auto-success', true);
lcfa_recovery_assert(!empty($success['attempted']) && !empty($success['ok']), 'Automatic recovery should apply a valid rollback record.');
lcfa_recovery_assert(($GLOBALS['lcfa_recovery_options']['lcfa_theme_library_imports']['sample-theme']['status'] ?? '') === 'rolled_back', 'Successful recovery should persist the rolled-back import state.');
lcfa_recovery_assert(!empty(LCFA_Settings::$restored['auto-success']['ok']), 'Successful recovery should mark the private rollback record restored.');

$disabled = $recover->invoke($importer, 'auto-success', false);
lcfa_recovery_assert(empty($disabled['attempted']), 'Explicitly disabled automatic recovery should not execute rollback.');

$missing = $recover->invoke($importer, 'missing-audit', true);
lcfa_recovery_assert(!empty($missing['attempted']) && empty($missing['ok']), 'A missing rollback record should produce an explicit recovery failure.');

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php');
lcfa_recovery_assert(str_contains($source, '$this->recover_failed_import($audit_id, $auto_rollback)'), 'The importer catch path should execute automatic recovery.');
lcfa_recovery_assert(str_contains($source, "'status'                    => \$status"), 'The importer should return explicit failed, failed_rolled_back, or rollback_failed status.');

echo "PASS\n";
