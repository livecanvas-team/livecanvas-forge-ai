<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.7');
define('LCFA_FILE', dirname(__DIR__, 2) . '/livecanvas-forge-ai.php');

$GLOBALS['lcfa_test_active_livecanvas'] = true;
$GLOBALS['lcfa_test_lc_apikey'] = '';
$GLOBALS['lcfa_test_transients'] = [];
$GLOBALS['lcfa_test_transient_expirations'] = [];
$GLOBALS['lcfa_test_remote_calls'] = 0;
$GLOBALS['lcfa_test_release_payload'] = [];
$GLOBALS['lcfa_test_response_code'] = 200;

function lcfa_updater_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_updater_assert_false(bool $condition, string $message): void {
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_updater_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
    return true;
}

function get_transient(string $key) {
    return $GLOBALS['lcfa_test_transients'][$key] ?? false;
}

function set_transient(string $key, $value, int $expiration = 0): bool {
    $GLOBALS['lcfa_test_transients'][$key] = $value;
    $GLOBALS['lcfa_test_transient_expirations'][$key] = $expiration;

    return true;
}

function wp_remote_get(string $url, array $args = []) {
    $GLOBALS['lcfa_test_remote_calls']++;

    return [
        'response' => ['code' => $GLOBALS['lcfa_test_response_code']],
        'body'     => json_encode($GLOBALS['lcfa_test_release_payload']),
    ];
}

function wp_remote_retrieve_response_code($response): int {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string {
    return (string) ($response['body'] ?? '');
}

function is_wp_error($thing): bool {
    return false;
}

function is_plugin_active(string $plugin_file): bool {
    return $GLOBALS['lcfa_test_active_livecanvas'] && in_array($plugin_file, [
        'livecanvas/livecanvas-plugin-index.php',
        'livecanvas/livecanvas.php',
    ], true);
}

function get_option(string $option, $default = false) {
    if ($option === 'active_plugins' && $GLOBALS['lcfa_test_active_livecanvas']) {
        return ['livecanvas/livecanvas-plugin-index.php'];
    }

    return $default;
}

function get_site_option(string $option, $default = false) {
    if ($option === 'lc_apikey') {
        return $GLOBALS['lcfa_test_lc_apikey'];
    }

    return $default;
}

function plugin_basename(string $file): string {
    return 'livecanvas-forge-ai/livecanvas-forge-ai.php';
}

function esc_html(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function lcfa_updater_reset(array $release_payload = [], string $api_key = '', bool $active_livecanvas = true, int $response_code = 200): void {
    $GLOBALS['lcfa_test_release_payload'] = $release_payload;
    $GLOBALS['lcfa_test_lc_apikey'] = $api_key;
    $GLOBALS['lcfa_test_active_livecanvas'] = $active_livecanvas;
    $GLOBALS['lcfa_test_response_code'] = $response_code;
    $GLOBALS['lcfa_test_transients'] = [];
    $GLOBALS['lcfa_test_transient_expirations'] = [];
    $GLOBALS['lcfa_test_remote_calls'] = 0;
    unset($_GET['force-check'], $_GET['lcfa-refresh-updates']);
}

function lcfa_updater_release(string $tag = 'v0.1.8', bool $draft = false, bool $prerelease = false, ?array $assets = null): array {
    return [
        'tag_name'     => $tag,
        'draft'        => $draft,
        'prerelease'   => $prerelease,
        'html_url'     => 'https://github.com/livecanvas-team/livecanvas-forge-ai/releases/tag/' . $tag,
        'published_at' => '2026-06-19T12:00:00Z',
        'body'         => "Release notes\n- Fixes",
        'assets'       => $assets ?? [
            [
                'name'                 => 'livecanvas-forge-ai.zip',
                'browser_download_url' => 'https://github.com/livecanvas-team/livecanvas-forge-ai/releases/download/' . $tag . '/livecanvas-forge-ai.zip',
            ],
        ],
    ];
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-github-updater.php';

$updater = new LCFA_GitHub_Updater();
$plugin_file = 'livecanvas-forge-ai/livecanvas-forge-ai.php';

lcfa_updater_reset(lcfa_updater_release(), '', true);
$state = $updater->get_update_state();
lcfa_updater_assert_false((bool) ($state['eligible'] ?? true), 'unlicensed LiveCanvas should block AI Bridge updates');
lcfa_updater_assert_same('livecanvas_license_inactive', $state['blocked_reason'] ?? '', 'blocked state should explain missing LiveCanvas license');
lcfa_updater_assert_same(0, $GLOBALS['lcfa_test_remote_calls'], 'unlicensed sites should not call GitHub');
lcfa_updater_assert_same(false, $updater->filter_update_uri_response(false, [], $plugin_file), 'unlicensed sites should not expose an update response');

$transient = (object) [
    'checked'  => [$plugin_file => LCFA_VERSION],
    'response' => [$plugin_file => (object) ['package' => 'stale']],
];
$filtered = $updater->filter_update_transient($transient);
lcfa_updater_assert_false(isset($filtered->response[$plugin_file]), 'unlicensed sites should remove stale AI Bridge update responses');

lcfa_updater_reset(lcfa_updater_release('v0.1.8'), 'valid-api-key', true);
$state = $updater->get_update_state();
lcfa_updater_assert_true((bool) ($state['eligible'] ?? false), 'licensed LiveCanvas should enable AI Bridge update checks');
lcfa_updater_assert_same('0.1.8', $state['latest_version'] ?? '', 'stable GitHub tag should be normalized to a plugin version');
lcfa_updater_assert_true((bool) ($state['update_available'] ?? false), 'newer GitHub release should be reported as an update');

$update = $updater->filter_update_uri_response(false, [], $plugin_file);
lcfa_updater_assert_true(is_object($update), 'licensed newer release should produce a WordPress update object');
lcfa_updater_assert_same('0.1.8', $update->version ?? '', 'update object should expose the core-required version field');
lcfa_updater_assert_same('0.1.8', $update->new_version ?? '', 'update object should expose the newer version');
lcfa_updater_assert_same('https://github.com/livecanvas-team/livecanvas-forge-ai/releases/download/v0.1.8/livecanvas-forge-ai.zip', $update->package ?? '', 'update package should point to the release zip asset');
lcfa_updater_assert_same(21600, $GLOBALS['lcfa_test_transient_expirations']['lcfa_github_latest_release'] ?? 0, 'available updates should use the long GitHub cache TTL');

$info = $updater->filter_plugins_api(false, 'plugin_information', (object) ['slug' => 'livecanvas-forge-ai']);
lcfa_updater_assert_true(is_object($info), 'plugins_api should return details for the AI Bridge slug');
lcfa_updater_assert_same('0.1.8', $info->version ?? '', 'plugins_api details should use the latest release version');
lcfa_updater_assert_same(false, $updater->filter_plugins_api(false, 'plugin_information', (object) ['slug' => 'other-plugin']), 'plugins_api should ignore other slugs');

lcfa_updater_reset(lcfa_updater_release('v0.1.7'), 'valid-api-key', true);
$state = $updater->get_update_state();
lcfa_updater_assert_false((bool) ($state['update_available'] ?? true), 'same-version releases should not expose an update');
lcfa_updater_assert_same(600, $GLOBALS['lcfa_test_transient_expirations']['lcfa_github_latest_release'] ?? 0, 'no-update checks should use a short cache TTL');

lcfa_updater_reset(lcfa_updater_release('v0.1.8'), 'valid-api-key', true);
$GLOBALS['lcfa_test_transients']['lcfa_github_latest_release'] = [
    'ok'      => true,
    'version' => '0.1.7',
];
$state = $updater->get_update_state();
lcfa_updater_assert_same(1, $GLOBALS['lcfa_test_remote_calls'], 'legacy cache entries without schema should be ignored');
lcfa_updater_assert_same('0.1.8', $state['latest_version'] ?? '', 'legacy stale cache should refresh to the latest release');

lcfa_updater_reset(lcfa_updater_release('v0.1.8'), 'valid-api-key', true);
$GLOBALS['lcfa_test_transients']['lcfa_github_latest_release'] = [
    'ok'                     => true,
    'version'                => '0.1.7',
    'cache_schema'           => 2,
    'checked_plugin_version' => LCFA_VERSION,
];
$_GET['force-check'] = '1';
$state = $updater->get_update_state();
lcfa_updater_assert_same(1, $GLOBALS['lcfa_test_remote_calls'], 'forced WordPress update checks should bypass the cached release');
lcfa_updater_assert_same('0.1.8', $state['latest_version'] ?? '', 'forced update check should refresh stale cached release metadata');

lcfa_updater_reset(lcfa_updater_release('v0.1.8', false, false, []), 'valid-api-key', true);
$state = $updater->get_update_state();
lcfa_updater_assert_false((bool) ($state['update_available'] ?? true), 'release without expected zip asset should not expose an update');
lcfa_updater_assert_same('release_unavailable', $state['blocked_reason'] ?? '', 'missing asset should mark release unavailable');

lcfa_updater_reset(lcfa_updater_release('v0.1.8', false, true), 'valid-api-key', true);
$state = $updater->get_update_state();
lcfa_updater_assert_false((bool) ($state['update_available'] ?? true), 'prerelease should not expose an update');

lcfa_updater_reset(lcfa_updater_release('v0.1.8'), 'valid-api-key', true, 500);
$state = $updater->get_update_state();
lcfa_updater_assert_false((bool) ($state['update_available'] ?? true), 'GitHub error should not expose an update');

echo "PASS\n";
