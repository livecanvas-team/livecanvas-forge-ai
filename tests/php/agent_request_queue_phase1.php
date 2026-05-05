<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['lcfa_test_options'] = [];

function __(string $text, string $domain = ''): string {
    return $text;
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function delete_option(string $key): bool {
    unset($GLOBALS['lcfa_test_options'][$key]);
    return true;
}

function wp_parse_args($args, $defaults = []): array {
    return array_merge((array) $defaults, (array) $args);
}

function sanitize_key(string $value): string {
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
}

function sanitize_text_field(string $value): string {
    return trim($value);
}

function sanitize_textarea_field(string $value): string {
    return trim($value);
}

function esc_url_raw(string $value): string {
    return $value;
}

function absint($value): int {
    return max(0, (int) $value);
}

function current_time(string $type = 'mysql', bool $gmt = false): string {
    return $type === 'mysql' ? '2026-04-22 10:00:00' : '2026-04-22T10:00:00+00:00';
}

function wp_generate_password(int $length = 12, bool $special_chars = false, bool $extra_special_chars = false): string {
    static $counter = 0;
    $counter++;
    return substr(str_repeat('a' . $counter, $length), 0, $length);
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function get_current_user_id(): int {
    return 7;
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $actual, string $message): void {
    if (!$actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-settings.php';

LCFA_Settings::update_connections([
    'preferred_client' => 'codex',
    'connection_status' => 'ready',
]);

$request = LCFA_Settings::enqueue_agent_request([
    'thread_id' => 'default',
    'user_prompt' => 'Aggiungi una pricing section',
    'execution_target' => 'local',
    'post_id' => 5964,
    'context_post_id' => 5964,
    'target_id' => 5964,
    'variant' => '1',
    'action' => 'page_upsert',
    'codex_options' => [
        'model' => 'gpt-5.3-codex-spark',
        'speed' => 'fast',
        'reasoning_effort' => 'medium',
        'sandbox' => 'workspace-write',
    ],
    'attachments' => [
        [
            'kind' => 'image',
            'name' => 'pricing.png',
            'mime' => 'image/png',
            'size' => 120,
            'data_url' => 'data:image/png;base64,AAAA',
        ],
    ],
]);

lcfa_assert_true((string) ($request['id'] ?? '') !== '', 'agent queue should assign a request id');
lcfa_assert_same('queued', $request['status'] ?? '', 'new frontend agent requests should start queued');
lcfa_assert_same('codex', $request['agent'] ?? '', 'queued frontend requests should target the ready configured agent');
lcfa_assert_same('codex_mcp', $request['queued_for'] ?? '', 'queued frontend requests should declare the target MCP processor');
lcfa_assert_same('frontend_bridge', $request['provenance']['origin'] ?? '', 'queued frontend requests should preserve frontend origin');
lcfa_assert_same('browser_rest', $request['provenance']['transport'] ?? '', 'queued frontend requests should preserve browser REST transport');
lcfa_assert_same(1, count($request['attachments'] ?? []), 'queued frontend requests should keep sanitized image attachments');
lcfa_assert_same('gpt-5.3-codex-spark', $request['codex_options']['model'] ?? '', 'queued frontend requests should preserve the selected Codex model');
lcfa_assert_same('fast', $request['codex_options']['speed'] ?? '', 'queued frontend requests should preserve the selected Codex speed');
lcfa_assert_same('medium', $request['codex_options']['reasoning_effort'] ?? '', 'queued frontend requests should preserve the selected Codex intelligence');
lcfa_assert_same('workspace-write', $request['codex_options']['sandbox'] ?? '', 'queued frontend requests should preserve the selected Codex sandbox');

$pending = LCFA_Settings::get_agent_request((string) $request['id']);
lcfa_assert_same($request['id'], $pending['id'] ?? '', 'queued frontend requests should be persisted by id');

$claimed = LCFA_Settings::claim_next_agent_request('codex');
lcfa_assert_same($request['id'], $claimed['id'] ?? '', 'Codex should claim the next queued request targeted to Codex');
lcfa_assert_same('running', $claimed['status'] ?? '', 'claimed frontend requests should move to running');

$none_left = LCFA_Settings::claim_next_agent_request('codex');
lcfa_assert_same(null, $none_left, 'a running request should not be claimed twice');

$completed = LCFA_Settings::complete_agent_request((string) $request['id'], [
    'ok' => true,
    'message' => 'Page updated by Codex.',
    'action' => 'page_upsert',
    'mode' => 'apply',
    'target_id' => 5964,
    'provenance' => [
        'origin' => 'mcp_agent',
        'transport' => 'mcp_stdio',
        'agent' => 'codex',
        'processed_by' => 'codex_mcp',
    ],
], [
    'id' => 'default',
    'messages' => [
        [
            'role' => 'tool_result',
            'content' => 'Page updated by Codex.',
        ],
    ],
]);

lcfa_assert_same('completed', $completed['status'] ?? '', 'completed frontend requests should move to completed');
lcfa_assert_same('codex_mcp', $completed['result']['provenance']['processed_by'] ?? '', 'completed frontend requests should expose Codex MCP provenance');
lcfa_assert_same('Page updated by Codex.', $completed['result']['message'] ?? '', 'completed frontend requests should preserve the agent result');

echo "PASS agent_request_queue_phase1\n";
