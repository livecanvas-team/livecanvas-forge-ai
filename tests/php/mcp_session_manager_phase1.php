<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_transients'] = [];

class WP_Error {
    private string $code;
    private string $message;
    private $data;

    public function __construct(string $code, string $message, $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }
}

class WP_REST_Request {
    private array $headers;

    public function __construct(array $headers = []) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function get_header(string $name): string {
        return (string) ($this->headers[strtolower($name)] ?? '');
    }

    public function get_param(string $name) {
        return null;
    }
}

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function sanitize_text_field($value): string {
    return trim((string) $value);
}

function absint($value): int {
    return abs((int) $value);
}

function admin_url(string $path = ''): string {
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_salt(string $scheme = 'auth'): string {
    return 'test-salt';
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value, bool $autoload = true): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function get_transient(string $key) {
    return $GLOBALS['lcfa_test_transients'][$key]['value'] ?? false;
}

function set_transient(string $key, $value, int $expiration = 0): bool {
    $GLOBALS['lcfa_test_transients'][$key] = [
        'value' => $value,
        'expiration' => $expiration,
    ];
    return true;
}

function delete_transient(string $key): bool {
    unset($GLOBALS['lcfa_test_transients'][$key]);
    return true;
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

final class LCFA_Settings {
    public static function get_site_fingerprint(): string {
        return 'site-fp';
    }

    public static function get_connections(): array {
        return $GLOBALS['lcfa_test_connections'] ?? [
            'connection_status' => '',
            'connection_current_step' => 'smoke_test',
            'connection_last_verified_at' => '',
        ];
    }

    public static function update_connections(array $connections): void {
        $GLOBALS['lcfa_test_connections'] = $connections;
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_false(bool $condition, string $message): void {
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-mcp-session-manager.php';

$pairing = LCFA_MCP_Session_Manager::start_pairing([
    'client' => 'codex',
    'project_label' => 'Remote Example',
    'site_fingerprint' => 'site-fp',
]);

lcfa_assert_true(!empty($pairing['ok']), 'pairing start should succeed');
lcfa_assert_true(!empty($pairing['pairing_id']), 'pairing start should return a pairing id');
lcfa_assert_true(!empty($pairing['device_secret']), 'pairing start should return the device secret to the MCP client');
lcfa_assert_true(!empty($pairing['user_code']), 'pairing start should return a user code');
lcfa_assert_true(strpos((string) ($pairing['verification_url'] ?? ''), '#lcfa-secure-codex-pairing-sessions') !== false, 'pairing verification URL should deep-link to the approval panel');
lcfa_assert_true(LCFA_MCP_Session_Manager::get_pending_pairings() !== [], 'pending pairing should be visible to admins');

$approve = LCFA_MCP_Session_Manager::approve_pairing((string) $pairing['pairing_id']);
lcfa_assert_true(!empty($approve['ok']), 'pairing approval should create a session');

$status = LCFA_MCP_Session_Manager::get_pairing_status((string) $pairing['pairing_id'], (string) $pairing['device_secret']);
lcfa_assert_same('approved', $status['status'] ?? '', 'approved pairing status should return the session token once');
lcfa_assert_true(!empty($status['session_token']), 'approved pairing status should include a one-time session token');

$sessions = LCFA_MCP_Session_Manager::get_sessions();
$session = reset($sessions);
lcfa_assert_true(is_array($session), 'session should be stored');
lcfa_assert_true(!empty($session['token_hash']), 'session should store only a token hash');
lcfa_assert_false(isset($session['session_token']), 'session option should not store the raw token');

$validated = LCFA_MCP_Session_Manager::validate_session_token((string) $status['session_token'], 'read');
lcfa_assert_true(is_array($validated), 'session token should validate for read scope');
lcfa_assert_true((bool) LCFA_MCP_Session_Manager::validate_session_token((string) $status['session_token'], 'write'), 'default session token should validate for write scope');
lcfa_assert_same('ready', $GLOBALS['lcfa_test_connections']['connection_status'] ?? '', 'valid session usage should mark the connection ready');
lcfa_assert_same('ready', $GLOBALS['lcfa_test_connections']['connection_current_step'] ?? '', 'valid session usage should move the connection to ready');
lcfa_assert_same('ai-bridge-session', $GLOBALS['lcfa_test_connections']['connection_strategy'] ?? '', 'valid session usage should store the secure strategy');

$reported = LCFA_MCP_Session_Manager::get_session_from_request(new WP_REST_Request([
    'X-LCFA-MCP-Session' => (string) $status['session_token'],
    'X-LCFA-MCP-Package-Version' => '0.2.0-beta.1',
]), 'read');
lcfa_assert_same('0.2.0-beta.1', $reported['mcp_package_version'] ?? '', 'session requests should record the detected MCP package version');
$reported_sessions = LCFA_MCP_Session_Manager::get_public_sessions();
lcfa_assert_same('0.2.0-beta.1', $reported_sessions[0]['mcp_package_version'] ?? '', 'public session diagnostics should expose the detected MCP package version without exposing the token');

$second_status = LCFA_MCP_Session_Manager::get_pairing_status((string) $pairing['pairing_id'], (string) $pairing['device_secret']);
lcfa_assert_same('consumed', $second_status['status'] ?? '', 'pairing status should not return the session token twice');

$revoke = LCFA_MCP_Session_Manager::revoke_session((string) ($validated['session_id'] ?? ''));
lcfa_assert_true(!empty($revoke['ok']), 'session revoke should succeed');
lcfa_assert_false((bool) LCFA_MCP_Session_Manager::validate_session_token((string) $status['session_token'], 'read'), 'revoked session token should not validate');

$read_only_pairing = LCFA_MCP_Session_Manager::start_pairing([
    'client' => 'codex',
    'project_label' => 'Read Only',
    'site_fingerprint' => 'site-fp',
    'scopes' => ['read', 'preview'],
]);
$read_only_approve = LCFA_MCP_Session_Manager::approve_pairing((string) $read_only_pairing['pairing_id']);
lcfa_assert_true(!empty($read_only_approve['ok']), 'read-only pairing approval should create a session');
$read_only_status = LCFA_MCP_Session_Manager::get_pairing_status((string) $read_only_pairing['pairing_id'], (string) $read_only_pairing['device_secret']);
lcfa_assert_true((bool) LCFA_MCP_Session_Manager::validate_session_token((string) $read_only_status['session_token'], 'preview'), 'read-only session should validate for preview scope');
lcfa_assert_false((bool) LCFA_MCP_Session_Manager::validate_session_token((string) $read_only_status['session_token'], 'write'), 'explicit read-only session should not validate for write scope');

$opencode_pairing = LCFA_MCP_Session_Manager::start_pairing([
    'client' => 'opencode',
    'project_label' => 'OpenCode Project',
    'site_fingerprint' => 'site-fp',
]);
lcfa_assert_true(strpos((string) ($opencode_pairing['message'] ?? ''), 'OpenCode') !== false, 'pairing guidance should name OpenCode');
$opencode_approve = LCFA_MCP_Session_Manager::approve_pairing((string) $opencode_pairing['pairing_id']);
lcfa_assert_true(strpos((string) ($opencode_approve['message'] ?? ''), 'OpenCode') !== false, 'approval guidance should name OpenCode');
lcfa_assert_same('opencode', $opencode_approve['client'] ?? '', 'approval result should preserve the OpenCode client identity');
$opencode_status = LCFA_MCP_Session_Manager::get_pairing_status((string) $opencode_pairing['pairing_id'], (string) $opencode_pairing['device_secret']);
lcfa_assert_true((bool) LCFA_MCP_Session_Manager::validate_session_token((string) $opencode_status['session_token'], 'read'), 'OpenCode session should validate');
lcfa_assert_same('opencode', $GLOBALS['lcfa_test_connections']['preferred_client'] ?? '', 'OpenCode session usage should store the correct preferred client');

echo "PASS\n";
