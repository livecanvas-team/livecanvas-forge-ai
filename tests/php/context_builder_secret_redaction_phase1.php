<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function rest_url(string $path = ''): string {
    return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function home_url(string $path = '/'): string {
    return 'https://example.test' . ($path === '/' ? '/' : '/' . ltrim($path, '/'));
}

function untrailingslashit(string $value): string {
    return rtrim($value, "/\\");
}

function trailingslashit(string $value): string {
    return rtrim($value, "/\\") . '/';
}

function get_stylesheet_directory(): string {
    return '/tmp/theme-child';
}

function get_template_directory(): string {
    return '/tmp/theme-parent';
}

final class LCFA_Settings {
    public static function get_connections(): array {
        return [
            'mcp_enabled'       => true,
            'mcp_host'          => '127.0.0.1',
            'mcp_port'          => '7681',
            'mcp_token'         => 'super-secret-token',
            'preferred_client'  => 'codex',
            'mcp_server_command'=> 'node bridge.js',
            'transport'         => 'rest',
        ];
    }

    public static function get_mcp_endpoint(): string {
        return 'ws://127.0.0.1:7681';
    }

    public static function get_site_fingerprint(): string {
        return 'site-fingerprint';
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'site_mode'                => 'remote',
            'detected_framework'       => 'picostrap',
            'current_theme_stylesheet' => 'picostrap-child',
            'windpress_active'         => false,
        ];
    }
}

final class LCFA_Inventory {}
final class LCFA_WindPress_Bridge {}

final class LCFA_Local_MCP_Bridge {
    public function get_status(): array {
        return [
            'available'       => false,
            'build_available' => false,
        ];
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

function lcfa_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-context-builder.php';

$builder = new LCFA_Context_Builder(
    new LCFA_Environment(),
    new LCFA_Inventory(),
    null,
    new LCFA_Local_MCP_Bridge()
);

lcfa_assert_same('generated', $builder->get_mcp_status()['token'] ?? '', 'public MCP status should redact the legacy token');
lcfa_assert_same('super-secret-token', $builder->get_mcp_status(true)['token'] ?? '', 'internal MCP status should retain the token for local admin setup');

$public_bootstrap = json_encode($builder->get_public_bootstrap_payload(), JSON_UNESCAPED_SLASHES) ?: '';
lcfa_assert_not_contains('super-secret-token', $public_bootstrap, 'public bootstrap should not expose the legacy MCP token');
lcfa_assert_not_contains('LCFA_MCP_TOKEN=', $public_bootstrap, 'public bootstrap should remove secret environment entries');

$internal_bootstrap = json_encode($builder->get_bootstrap_payload(), JSON_UNESCAPED_SLASHES) ?: '';
lcfa_assert_not_contains('super-secret-token', $internal_bootstrap, 'remote admin bootstrap should not expose the legacy MCP token');
lcfa_assert_not_contains('LCFA_MCP_TOKEN=', $internal_bootstrap, 'remote admin bootstrap should not generate legacy MCP token environment entries');
lcfa_assert_same(true, strpos($internal_bootstrap, '@livecanvas/ai-bridge-mcp@0.2.0-beta.2') !== false, 'remote admin bootstrap should use the pinned secure MCP package');
lcfa_assert_same(true, strpos($internal_bootstrap, 'LCFA_PAIRING_SCOPES=read,preview') !== false, 'remote admin bootstrap should request read and preview scopes by default');

echo "PASS\n";
