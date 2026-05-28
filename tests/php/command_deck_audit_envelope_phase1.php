<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-tests/');

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
    return substr(str_repeat('abcdef123456', 4), 0, $length);
}

function current_time(string $type, bool $gmt = false): string {
    return '2026-05-27 12:00:00';
}

final class LCFA_Settings {
    public static function get(): array {
        return [];
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

function lcfa_audit_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_audit_assert_same($expected, $actual, string $message): void {
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
$get_provenance = new ReflectionMethod('LCFA_Command_Deck', 'get_payload_provenance');

$provenance = $get_provenance->invoke($instance, [
    '_lcfa_origin'       => 'wp_ability',
    '_lcfa_processed_by' => 'wp_ability_preview_page_upsert',
]);

lcfa_audit_assert_same('wp_ability', $provenance['origin'] ?? '', 'command deck provenance should preserve WordPress ability origin');
lcfa_audit_assert_same('api', $provenance['transport'] ?? '', 'WordPress ability provenance should default to API transport');
lcfa_audit_assert_same('wp_ability_preview_page_upsert', $provenance['processed_by'] ?? '', 'command deck provenance should preserve dedicated ability processor names');

$apply_page_provenance = $get_provenance->invoke($instance, [
    '_lcfa_origin'       => 'wp_ability',
    '_lcfa_processed_by' => 'wp_ability_apply_page_upsert',
]);

lcfa_audit_assert_same('wp_ability_apply_page_upsert', $apply_page_provenance['processed_by'] ?? '', 'command deck provenance should preserve dedicated apply ability processor names');

$preview_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'preview',
    'execution_target' => 'local',
    'data'             => [],
];
$preview_payload = ['action' => 'page_upsert', 'dry_run' => true];
$attach_audit->invokeArgs($instance, [&$preview_result, $preview_payload, $provenance]);

lcfa_audit_assert_true(empty($preview_result['audit_id']), 'preview results should not receive an audit ID');
lcfa_audit_assert_true(!empty($preview_result['required_next_actions']), 'preview results should expose required next actions');

$apply_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'page',
    'target_id'        => 123,
    'target_title'     => 'Existing page',
    'existing_html'    => '<section>Before</section>',
    'data'             => [],
];
$apply_payload = ['action' => 'page_upsert'];
$apply_provenance = $get_provenance->invoke($instance, [
    '_lcfa_origin'       => 'wp_ability',
    '_lcfa_processed_by' => 'wp_ability_apply',
]);
$attach_audit->invokeArgs($instance, [&$apply_result, $apply_payload, $apply_provenance]);

lcfa_audit_assert_true(strpos((string) ($apply_result['audit_id'] ?? ''), 'audit-') === 0, 'apply results should receive a stable audit ID prefix');
lcfa_audit_assert_true(!empty($apply_result['rollback_available']), 'page apply results with previous content should expose rollback availability');
lcfa_audit_assert_same('previous_post_content', $apply_result['data']['audit']['rollback_reference']['type'] ?? '', 'page apply rollback should reference previous post content');
lcfa_audit_assert_same(md5('<section>Before</section>'), $apply_result['data']['audit']['rollback_reference']['content_hash'] ?? '', 'page apply rollback should include a previous content hash');
lcfa_audit_assert_same('wp_ability', $apply_result['data']['audit']['provenance']['origin'] ?? '', 'audit envelope should preserve provenance');

$read_result = [
    'ok'               => true,
    'action'           => 'site_audit',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'data'             => [],
];
$attach_audit->invokeArgs($instance, [&$read_result, ['action' => 'site_audit'], $apply_provenance]);
lcfa_audit_assert_true(empty($read_result['audit_id']), 'read actions should not receive write audit IDs');

echo "PASS\n";
