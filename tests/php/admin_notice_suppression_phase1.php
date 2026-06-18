<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');

$GLOBALS['lcfa_removed_actions'] = [];

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    $value = strtolower($value);

    return (string) preg_replace('/[^a-z0-9_\\-]/', '', $value);
}

function remove_all_actions(string $hook_name, $priority = false): bool {
    $GLOBALS['lcfa_removed_actions'][] = $hook_name;

    return true;
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

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$method = new ReflectionMethod('LCFA_Admin', 'suppress_external_admin_notices');

$_GET['page'] = 'lcfa-dashboard';
$GLOBALS['lcfa_removed_actions'] = [];

$method->invoke($admin, (object) ['id' => 'livecanvas_page_lcfa-dashboard']);

lcfa_assert_true(in_array('network_admin_notices', $GLOBALS['lcfa_removed_actions'], true), 'AI Bridge dashboard should suppress network admin notices');
lcfa_assert_true(in_array('user_admin_notices', $GLOBALS['lcfa_removed_actions'], true), 'AI Bridge dashboard should suppress user admin notices');
lcfa_assert_true(in_array('admin_notices', $GLOBALS['lcfa_removed_actions'], true), 'AI Bridge dashboard should suppress global admin notices');
lcfa_assert_true(in_array('all_admin_notices', $GLOBALS['lcfa_removed_actions'], true), 'AI Bridge dashboard should suppress all admin notices');
lcfa_assert_true(in_array('livecanvas_page_lcfa-dashboard_admin_notices', $GLOBALS['lcfa_removed_actions'], true), 'AI Bridge dashboard should suppress screen-specific admin notices');

$_GET['page'] = 'plugins';
$GLOBALS['lcfa_removed_actions'] = [];

$method->invoke($admin, (object) ['id' => 'plugins']);

lcfa_assert_false($GLOBALS['lcfa_removed_actions'] !== [], 'Non-AI Bridge screens should not suppress admin notices');

echo "PASS\n";
