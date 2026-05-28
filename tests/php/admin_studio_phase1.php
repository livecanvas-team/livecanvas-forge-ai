<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('WP_PLUGIN_DIR', '/tmp/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_html(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr__(string $value, string $domain = 'default'): string {
    return $value;
}

function esc_url(string $value): string {
    return $value;
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url = ''): string {
    $separator = strpos($url, '?') === false ? '?' : '&';

    return $url . $separator . http_build_query($args);
}

function get_option(string $name, $default = false) {
    if ($name === 'date_format') {
        return 'Y-m-d';
    }

    if ($name === 'time_format') {
        return 'H:i:s';
    }

    return $default;
}

function get_date_from_gmt(string $date, string $format = 'Y-m-d H:i:s'): string {
    $timestamp = strtotime($date . ' GMT');

    return $timestamp ? gmdate($format, $timestamp) : $date;
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

final class LCFA_Settings {
    public static function get_mcp_write_ability_options(): array {
        return [
            'livecanvas-forge-ai/apply-page-upsert' => [
                'label' => 'Apply page upsert',
            ],
            'livecanvas-forge-ai/restore-audit-rollback' => [
                'label' => 'Restore audit rollback',
            ],
        ];
    }

    public static function normalize_thread_id(string $thread_id): string {
        $thread_id = trim($thread_id);

        return $thread_id !== '' ? $thread_id : 'default';
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$diagnostics = [
    'ability_diagnostics' => [
        'total' => 2,
        'mcp_public_total' => 1,
        'mcp_public_write' => ['livecanvas-forge-ai/apply-page-upsert'],
        'mcp_write_allowlist' => ['livecanvas-forge-ai/apply-page-upsert'],
        'mcp_write_available' => [
            'livecanvas-forge-ai/apply-page-upsert',
            'livecanvas-forge-ai/restore-audit-rollback',
        ],
        'items' => [
            [
                'name' => 'livecanvas-forge-ai/get-snapshot',
                'label' => 'Get Forge snapshot',
                'mcp_public' => true,
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true,
            ],
            [
                'name' => 'livecanvas-forge-ai/apply-page-upsert',
                'label' => 'Apply page upsert',
                'mcp_public' => true,
                'readonly' => false,
                'destructive' => true,
                'idempotent' => false,
            ],
        ],
    ],
];

$abilities_method = new ReflectionMethod('LCFA_Admin', 'render_studio_abilities_panel');
ob_start();
$abilities_method->invoke($admin, $diagnostics);
$abilities_markup = (string) ob_get_clean();
lcfa_assert_true(strpos($abilities_markup, 'Get Forge snapshot') !== false, 'studio abilities panel should list read abilities');
lcfa_assert_true(strpos($abilities_markup, 'Apply page upsert') !== false, 'studio abilities panel should list write abilities');
lcfa_assert_true(strpos($abilities_markup, 'MCP public') !== false, 'studio abilities panel should expose MCP state');
lcfa_assert_true(strpos($abilities_markup, 'Destructive') !== false, 'studio abilities panel should expose destructive state');
lcfa_assert_true(strpos($abilities_markup, 'data-lcfa-studio-ability-search') !== false, 'studio abilities panel should render a search control');
lcfa_assert_true(strpos($abilities_markup, 'data-lcfa-studio-ability-filter="write"') !== false, 'studio abilities panel should render a write filter');
lcfa_assert_true(strpos($abilities_markup, 'data-lcfa-studio-ability-item') !== false, 'studio abilities panel should mark filterable ability rows');

$policy_method = new ReflectionMethod('LCFA_Admin', 'render_studio_write_policy_panel');
ob_start();
$policy_method->invoke($admin, $diagnostics, [
    'mcp_write_abilities_enabled' => true,
]);
$policy_markup = (string) ob_get_clean();
lcfa_assert_true(strpos($policy_markup, 'Master opt-in enabled') !== false, 'studio write policy should show the master opt-in state');
lcfa_assert_true(strpos($policy_markup, 'Allowed: 1') !== false, 'studio write policy should show allowlist count');
lcfa_assert_true(strpos($policy_markup, 'Exposed: 1') !== false, 'studio write policy should show exposed write count');
lcfa_assert_true(strpos($policy_markup, 'tab=connections') !== false, 'studio write policy should link back to Connections');

$runs_method = new ReflectionMethod('LCFA_Admin', 'render_studio_runs_panel');
ob_start();
$runs_method->invoke($admin, [
    [
        'time' => '2026-05-27 10:00:00',
        'summary' => 'Updated homepage',
        'action' => 'page_upsert',
        'mode' => 'apply',
        'ok' => true,
        'target_id' => 86,
        'target_title' => 'Home',
        'audit_id' => 'audit-123',
        'rollback_available' => true,
        'execution_target' => 'local',
    ],
]);
$runs_markup = (string) ob_get_clean();
lcfa_assert_true(strpos($runs_markup, 'Updated homepage') !== false, 'studio runs panel should show run summary');
lcfa_assert_true(strpos($runs_markup, 'audit-123') !== false, 'studio runs panel should show audit ID');
lcfa_assert_true(strpos($runs_markup, 'suggest_action=restore_audit_rollback') !== false, 'studio runs panel should expose rollback shortcut');
lcfa_assert_true(strpos($runs_markup, 'tab=command') !== false, 'studio runs panel should link to the Command Deck');
lcfa_assert_true(strpos($runs_markup, 'data-lcfa-studio-run-search') !== false, 'studio runs panel should render a search control');
lcfa_assert_true(strpos($runs_markup, 'data-lcfa-studio-run-filter="rollback"') !== false, 'studio runs panel should render a rollback filter');
lcfa_assert_true(strpos($runs_markup, 'data-lcfa-studio-run-item') !== false, 'studio runs panel should mark filterable run rows');

echo "PASS\n";
