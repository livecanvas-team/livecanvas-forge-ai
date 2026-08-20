<?php

define('ABSPATH', __DIR__);
define('LCFA_MCP_PACKAGE_VERSION', '0.2.0-beta.5');

function __($text, $domain = null) {
    return $text;
}

function sanitize_key($key) {
    return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
}

function sanitize_text_field($value) {
    return trim((string) $value);
}

function trailingslashit($value) {
    return rtrim((string) $value, '/') . '/';
}

function current_time($type, $gmt = false) {
    return '2026-04-21 08:47:28';
}

function wp_remote_get($url, $args = []) {
    return [
        'response' => ['code' => 200],
        'body'     => json_encode([
            'mcp' => [
                'enabled'          => true,
                'filesystem_mode'  => 'local-theme-access',
                'preferred_client' => 'codex',
            ],
        ]),
    ];
}

function wp_remote_retrieve_response_code($response) {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response) {
    return (string) ($response['body'] ?? '');
}

function is_wp_error($value) {
    return false;
}

final class LCFA_Settings {
    public static array $connections = [];

    public static function connection_defaults(): array {
        return [
            'preferred_client'          => '',
            'claude_connection_target'  => '',
            'local_bridge_url'          => '',
            'mcp_token'                 => '',
            'connection_mode'           => '',
            'workspace_root'            => '',
        ];
    }

    public static function get_connections(): array {
        return self::$connections;
    }
}

final class LCFA_Environment {}

final class LCFA_Local_MCP_Bridge {
    public function get_status(): array {
        return [
            'available'       => true,
            'local_site'      => true,
            'script_exists'   => true,
            'node_available'  => true,
            'node_version'    => 'v22.0.0',
            'rest_reachable'  => true,
            'build_available' => true,
            'message'         => 'ready',
        ];
    }
}

final class LCFA_Remote_Client {
    public function is_configured(): bool {
        return false;
    }

    public function get_status(): array {
        return ['available' => false];
    }
}

final class LCFA_MCP_Session_Manager {
    public static array $verified_session = [];

    public static function get_latest_verified_session(string $client = '', string $mode = ''): array {
        if (self::$verified_session === []) {
            return [];
        }

        return ($client === '' || $client === (self::$verified_session['client'] ?? ''))
            && ($mode === '' || $mode === (self::$verified_session['connection_mode'] ?? ''))
            ? self::$verified_session
            : [];
    }
}

function lcfa_assert_true($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function lcfa_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function lcfa_assert_contains($needle, $haystack, $message) {
    if (strpos((string) $haystack, (string) $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

require __DIR__ . '/../../includes/class-lcfa-connection-tester.php';

$previous_home = getenv('HOME');
$home = sys_get_temp_dir() . '/lcfa-codex-home-' . getmypid();
$project_root = $home . '/project';
$wordpress_root = $project_root . '/app/public';
$codex_dir = $project_root . '/.codex';
$codex_config = $codex_dir . '/config.toml';
@mkdir($codex_dir, 0777, true);
@unlink($codex_config);
putenv('HOME=' . $home);

LCFA_Settings::$connections = array_merge(LCFA_Settings::connection_defaults(), [
    'preferred_client' => 'codex',
    'local_bridge_url' => 'https://example.test/wp-json/lcfa/v1/',
    'mcp_token'        => 'test-token',
    'connection_mode'  => 'local',
    'workspace_root'   => $project_root,
    'wordpress_root'   => $wordpress_root,
]);

$tester = new LCFA_Connection_Tester(new LCFA_Environment(), new LCFA_Local_MCP_Bridge(), new LCFA_Remote_Client());

$missing_config = $tester->run_checks(['mode' => 'local']);

lcfa_assert_true($missing_config['ok'] === false, 'Codex local smoke should fail until Codex has livecanvas-forge in config.toml');
lcfa_assert_same('Codex MCP registration', $missing_config['checks']['client_registration']['label'] ?? '', 'Codex smoke should expose a dedicated registration check');
lcfa_assert_contains('.codex/config.toml', $missing_config['checks']['client_registration']['message'] ?? '', 'Missing Codex registration should identify the project config file');

file_put_contents($codex_config, implode("\n", [
    '[mcp_servers.livecanvas-forge]',
    'command = "node"',
    'args = ["/tmp/other-site/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js", "--transport=stdio"]',
    '',
    '[mcp_servers.livecanvas-forge.env]',
    'LCFA_REST_BASE = "https://other.test/wp-json/lcfa/v1/"',
    'LCFA_MCP_TOKEN = "old-token"',
    'LCFA_WP_ROOT = "/tmp/other-site"',
]));

$stale_config = $tester->run_checks(['mode' => 'local']);

lcfa_assert_true($stale_config['ok'] === false, 'Codex local smoke should fail when the registered MCP entry points to a different site or token');
lcfa_assert_contains('stale', $stale_config['checks']['client_registration']['message'] ?? '', 'Stale Codex registration should explain that the stored entry no longer matches this site');

file_put_contents($codex_config, implode("\n", [
    '[mcp_servers.livecanvas-forge]',
    'command = "node"',
    'args = ["' . $wordpress_root . '/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js", "--transport=stdio"]',
    '',
    '[mcp_servers.livecanvas-forge.env]',
    'LCFA_REST_BASE = "https://example.test/wp-json/lcfa/v1/"',
    'LCFA_AGENT = "codex"',
    'LCFA_PAIRING_SCOPES = "read,preview"',
    'LCFA_WP_ROOT = "' . $wordpress_root . '"',
]));

$without_handoff = $tester->run_checks(['mode' => 'local']);

lcfa_assert_true($without_handoff['ok'] === false, 'Codex local smoke should remain blocked until the real agent completes the handoff');
lcfa_assert_contains('get_connection_handoff', $without_handoff['checks']['agent_handoff']['message'] ?? '', 'missing handoff should explain the required agent action');

LCFA_MCP_Session_Manager::$verified_session = [
    'session_id' => 'sess_local_codex',
    'client' => 'codex',
    'connection_mode' => 'local',
    'last_seen_at' => '2026-04-21T08:47:28Z',
    'mcp_package_version' => '0.2.0-beta.4',
];
$stale_package = $tester->run_checks(['mode' => 'local']);

lcfa_assert_true($stale_package['ok'] === false, 'Codex local smoke should reject a stale MCP package after an AI Bridge upgrade');
lcfa_assert_contains('requires 0.2.0-beta.5', $stale_package['checks']['agent_handoff']['message'] ?? '', 'stale local package guidance should name the required version');

LCFA_MCP_Session_Manager::$verified_session = [
    'session_id' => 'sess_local_codex',
    'client' => 'codex',
    'connection_mode' => 'local',
    'last_seen_at' => '2026-04-21T08:47:28Z',
    'mcp_package_version' => '0.2.0-beta.5',
];
$registered = $tester->run_checks(['mode' => 'local']);

lcfa_assert_true($registered['ok'] === true, 'Codex local smoke should pass after config.toml contains the matching livecanvas-forge registration');
lcfa_assert_true(!empty($registered['checks']['client_registration']['ok']), 'Codex registration check should report success when the entry matches this site');
lcfa_assert_true(!empty($registered['checks']['agent_handoff']['ok']), 'Codex smoke should report the verified local handoff');

if ($previous_home === false) {
    putenv('HOME');
} else {
    putenv('HOME=' . $previous_home);
}

@unlink($codex_config);
@rmdir($codex_dir);
@rmdir($project_root);
@rmdir($home);

echo "PASS\n";
