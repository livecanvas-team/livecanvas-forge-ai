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

function wp_parse_args($args, $defaults = []): array {
    return array_merge((array) $defaults, (array) $args);
}

function sanitize_key(string $value): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?: '';
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
    return substr(str_repeat('r', $length), 0, $length);
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function get_current_user_id(): int {
    return 7;
}

function trailingslashit(string $value): string {
    return rtrim($value, '/\\') . '/';
}

function wp_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
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
require LCFA_DIR . 'includes/class-lcfa-codex-autorunner.php';

LCFA_Settings::update_connections([
    'preferred_client'   => 'codex',
    'connection_status'  => 'ready',
    'connection_mode'    => 'local',
    'workspace_root'     => '/tmp/lcfa workspace',
    'local_bridge_url'   => 'http://example.test/wp-json/lcfa/v1/',
    'mcp_token'          => 'test-token',
]);

$request = LCFA_Settings::enqueue_agent_request([
    'thread_id'        => 'default',
    'user_prompt'      => 'Cambia la prima card pricing a 19 euro.',
    'execution_target' => 'local',
    'post_id'          => 5964,
    'context_post_id'  => 5964,
    'target_id'        => 5964,
    'variant'          => '1',
    'action'           => 'page_upsert',
    'codex_options'    => [
        'model' => 'gpt-5.3-codex-spark',
        'speed' => 'fast',
        'reasoning_effort' => 'medium',
    ],
]);

$specific = LCFA_Settings::claim_agent_request((string) $request['id'], 'codex');
lcfa_assert_same((string) $request['id'], (string) ($specific['id'] ?? ''), 'Codex autorun must be able to claim the exact frontend request it was launched for');
lcfa_assert_same('running', (string) ($specific['status'] ?? ''), 'claim_agent_request should move the exact request to running');

$run_dir = sys_get_temp_dir() . '/lcfa-codex-runner-test-' . getmypid();
@mkdir($run_dir, 0777, true);
$plan = LCFA_Codex_Autorunner::build_launch_plan($request, '/Applications/Codex.app/Contents/Resources/codex', '/tmp/lcfa workspace', $run_dir);

lcfa_assert_contains('codex-prompt-' . $request['id'] . '.txt', $plan['prompt_file'], 'Codex autorunner should write one prompt file per request');
lcfa_assert_contains('codex-run-' . $request['id'] . '.log', $plan['log_file'], 'Codex autorunner should write one log file per request');
lcfa_assert_contains('get_frontend_prompt_request', $plan['prompt'], 'Codex autorunner prompt should tell Codex to claim the frontend request');
lcfa_assert_contains((string) $request['id'], $plan['prompt'], 'Codex autorunner prompt should include the frontend request id');
lcfa_assert_contains('complete_frontend_prompt_request', $plan['prompt'], 'Codex autorunner prompt should tell Codex to complete the request');
lcfa_assert_contains('fail_frontend_prompt_request', $plan['prompt'], 'Codex autorunner prompt should tell Codex how to fail safely');
lcfa_assert_contains('mcp__livecanvas_forge__get_frontend_prompt_request', $plan['prompt'], 'Codex autorunner prompt should name the LiveCanvas MCP claim tool explicitly');
lcfa_assert_contains('Do not call list_mcp_resources', $plan['prompt'], 'Codex autorunner prompt should prevent resource discovery detours');
lcfa_assert_contains('Never wrap generated LiveCanvas page content in <main>', $plan['prompt'], 'Codex autorunner prompt should enforce the LiveCanvas no-main-wrapper rule');
lcfa_assert_contains('Speed profile: fast.', $plan['prompt'], 'Codex autorunner prompt should include the selected frontend speed profile');
lcfa_assert_contains('--skip-git-repo-check', $plan['command'], 'Codex autorunner should work from a WordPress root that may not be a git repository');
lcfa_assert_contains("--model 'gpt-5.3-codex-spark'", $plan['command'], 'Codex autorunner should pass the selected frontend model to codex exec');
lcfa_assert_contains("'model_reasoning_effort=\"medium\"'", $plan['command'], 'Codex autorunner should pass the selected frontend intelligence to codex exec');
lcfa_assert_contains('--dangerously-bypass-approvals-and-sandbox', $plan['command'], 'Codex autorunner should run non-interactively without cancelling MCP tool calls');
lcfa_assert_contains('--ignore-rules', $plan['command'], 'Codex autorunner should avoid unrelated project rules during frontend queue work');
lcfa_assert_contains('dangerously-bypass-approvals-and-sandbox', $plan['command'], 'Codex autorunner should allow the local MCP bridge to reach WordPress over HTTP');
lcfa_assert_contains('shell_environment_policy.inherit=all', $plan['command'], 'Codex autorunner should preserve environment for MCP subprocesses');
lcfa_assert_contains('PATH=', $plan['command'], 'Codex autorunner should provide a deterministic PATH for node and local tools');
lcfa_assert_contains("'--cd' '/tmp/lcfa workspace'", $plan['command'], 'Codex autorunner should quote the workspace path safely');
lcfa_assert_contains('< ' . escapeshellarg($plan['prompt_file']), $plan['command'], 'Codex autorunner should feed the prompt through stdin');

$sandbox_request = $request;
$sandbox_request['codex_options']['sandbox'] = 'workspace-write';
$sandbox_plan = LCFA_Codex_Autorunner::build_launch_plan($sandbox_request, '/Applications/Codex.app/Contents/Resources/codex', '/tmp/lcfa workspace', $run_dir);
lcfa_assert_contains("--sandbox 'workspace-write'", $sandbox_plan['command'], 'Codex autorunner should pass the selected frontend sandbox mode when it is restricted');

$stale_request = $request;
$stale_request['runner'] = [
    'state' => 'started',
    'updated_at' => '2026-04-22 09:55:00',
];
lcfa_assert_true(LCFA_Codex_Autorunner::is_stale_queued_request($stale_request, strtotime('2026-04-22 10:00:00'), 120), 'Codex autorunner should detect a queued request whose spawned process never claimed it');

$fresh_request = $stale_request;
$fresh_request['runner']['updated_at'] = '2026-04-22 09:59:30';
lcfa_assert_true(!LCFA_Codex_Autorunner::is_stale_queued_request($fresh_request, strtotime('2026-04-22 10:00:00'), 120), 'Codex autorunner should not mark fresh queued requests as stale');

echo "PASS codex_autorunner_phase1\n";
