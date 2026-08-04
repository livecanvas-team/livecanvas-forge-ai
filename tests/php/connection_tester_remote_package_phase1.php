<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-remote-package-test/');
define('LCFA_MCP_PACKAGE_VERSION', '0.2.0-beta.3');

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $value)); }
function sanitize_text_field(string $value): string { return trim($value); }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 12:00:00'; }

final class LCFA_Settings {
    public static function get_connections(): array {
        return ['connection_strategy' => 'ai-bridge-session'];
    }
}

final class LCFA_MCP_Session_Manager {
    public static string $version = '';

    public static function get_public_sessions(): array {
        return [[
            'session_id' => 'sess_test',
            'project_label' => 'Beta test',
            'last_seen_at' => '2026-08-02T12:00:00Z',
            'revoked' => false,
            'expired' => false,
            'mcp_package_version' => self::$version,
        ]];
    }

    public static function has_active_session(): bool { return true; }
}

final class LCFA_Environment {}
final class LCFA_Local_MCP_Bridge {}
final class LCFA_Remote_Client {
    public function is_configured(): bool { return false; }
    public function get_status(): array { return []; }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-connection-tester.php';

$tester = new LCFA_Connection_Tester(new LCFA_Environment(), new LCFA_Local_MCP_Bridge(), new LCFA_Remote_Client());

LCFA_MCP_Session_Manager::$version = '0.1.4';
$stale = $tester->run_checks(['mode' => 'remote']);
if (!empty($stale['ok']) || ($stale['checks']['remote_rest']['details']['package_version_matches'] ?? true) !== false) {
    fwrite(STDERR, "Remote smoke should reject a stale MCP package version.\n");
    exit(1);
}

LCFA_MCP_Session_Manager::$version = '0.2.0-beta.3';
$ready = $tester->run_checks(['mode' => 'remote']);
if (empty($ready['ok']) || ($ready['checks']['remote_rest']['details']['package_version_matches'] ?? false) !== true) {
    fwrite(STDERR, "Remote smoke should accept the exact beta MCP package version.\n");
    exit(1);
}

echo "PASS\n";
