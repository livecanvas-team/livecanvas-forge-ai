<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_posts'] = [
    123 => [
        'ID'           => 123,
        'post_content' => '<section>After</section>',
        'post_status'  => 'publish',
    ],
    456 => [
        'ID'           => 456,
        'post_content' => '<section>Created</section>',
        'post_status'  => 'publish',
    ],
];

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key($value): string {
    return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value));
}

function sanitize_text_field($value): string {
    return trim(strip_tags((string) $value));
}

function absint($value): int {
    return max(0, (int) $value);
}

function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
    return substr(str_repeat('rollback123', 4), 0, $length);
}

function current_time(string $type, bool $gmt = false): string {
    return '2026-05-27 12:00:00';
}

function current_user_can(string $capability): bool {
    return true;
}

function get_post(int $post_id) {
    if (!isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return null;
    }

    return (object) $GLOBALS['lcfa_test_posts'][$post_id];
}

function wp_update_post(array $postarr, bool $wp_error = false) {
    $post_id = absint($postarr['ID'] ?? 0);

    if ($post_id < 1 || !isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return 0;
    }

    if (array_key_exists('post_content', $postarr)) {
        $GLOBALS['lcfa_test_posts'][$post_id]['post_content'] = (string) $postarr['post_content'];
    }

    return $post_id;
}

function wp_trash_post(int $post_id) {
    if (!isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return false;
    }

    $GLOBALS['lcfa_test_posts'][$post_id]['post_status'] = 'trash';

    return (object) $GLOBALS['lcfa_test_posts'][$post_id];
}

function is_wp_error($value): bool {
    return false;
}

final class LCFA_Settings {
    public static array $records = [];

    public static function get(): array {
        return [
            'permission_profile' => 'advanced_templates',
            'allow_file_fallback' => true,
        ];
    }

    public static function store_rollback_record(string $audit_id, array $record): void {
        self::$records[sanitize_key($audit_id)] = $record;
    }

    public static function get_rollback_record(string $audit_id): array {
        return self::$records[sanitize_key($audit_id)] ?? [];
    }

    public static function mark_rollback_record_restored(string $audit_id, array $restore_result): void {
        self::$records[sanitize_key($audit_id)]['restored_at'] = current_time('mysql', true);
    }
}

final class LCFA_Environment {}
final class LCFA_Inventory {}
final class LCFA_WindPress_Bridge {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Local_MCP_Bridge {}
final class LCFA_Remote_Client {}
final class LCFA_Design_System_Compose {}
final class LCFA_Design_System_Apply {}
final class LCFA_Design_System_Picostrap_Executor {}
final class LCFA_Design_System_Picowind_Executor {
    public function __construct(...$args) {}
}
final class LCFA_Design_System_Build_Gateway {
    public function __construct(...$args) {}
}
final class LCFA_Design_System_Picostrap_Composer {}
final class LCFA_Design_System_Preview {}

function lcfa_rollback_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_rollback_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-command-deck.php';

$reflection = new ReflectionClass('LCFA_Command_Deck');
$instance = $reflection->newInstanceWithoutConstructor();
$attach_audit = new ReflectionMethod('LCFA_Command_Deck', 'attach_audit_envelope');
$restore_rollback = new ReflectionMethod('LCFA_Command_Deck', 'restore_audit_rollback');

$apply_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'page',
    'target_id'        => 123,
    'target_title'     => 'Existing page',
    'existing_html'    => '<section>Before</section>',
    'data'             => [
        'operation' => 'update',
    ],
];

$attach_audit->invokeArgs($instance, [&$apply_result, ['action' => 'page_upsert'], ['origin' => 'wp_ability']]);
$audit_id = (string) ($apply_result['audit_id'] ?? '');

lcfa_rollback_assert_true($audit_id !== '', 'apply audit should create an audit ID');
lcfa_rollback_assert_same('<section>Before</section>', LCFA_Settings::$records[$audit_id]['restore']['previous_content'] ?? '', 'rollback record should store previous content privately');

$preview = $restore_rollback->invoke($instance, $audit_id, true);
lcfa_rollback_assert_same('preview', $preview['mode'] ?? '', 'rollback restore should support preview mode');
lcfa_rollback_assert_same('<section>Before</section>', $preview['proposed_html'] ?? '', 'rollback preview should propose the stored previous content');
lcfa_rollback_assert_same('<section>After</section>', $GLOBALS['lcfa_test_posts'][123]['post_content'], 'rollback preview should not write post content');

$restored = $restore_rollback->invoke($instance, $audit_id, false);
lcfa_rollback_assert_true(!empty($restored['ok']), 'rollback apply should succeed for previous post content');
lcfa_rollback_assert_same('<section>Before</section>', $GLOBALS['lcfa_test_posts'][123]['post_content'], 'rollback apply should restore previous post content');
lcfa_rollback_assert_same('2026-05-27 12:00:00', LCFA_Settings::$records[$audit_id]['restored_at'] ?? '', 'rollback apply should mark the record as restored');

$created_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'page',
    'target_id'        => 456,
    'target_title'     => 'Created page',
    'existing_html'    => '',
    'data'             => [
        'operation' => 'create',
    ],
];

$attach_audit->invokeArgs($instance, [&$created_result, ['action' => 'page_upsert'], ['origin' => 'wp_ability']]);
$created_audit_id = (string) ($created_result['audit_id'] ?? '');
$created_restore = $restore_rollback->invoke($instance, $created_audit_id, false);

lcfa_rollback_assert_true(!empty($created_restore['ok']), 'created-post rollback should succeed');
lcfa_rollback_assert_same('trash', $GLOBALS['lcfa_test_posts'][456]['post_status'], 'created-post rollback should move the created post to trash');

echo "PASS\n";
