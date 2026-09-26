<?php
/** Select working-tree or installed plugin only in an explicit local CLI fixture. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = realpath((string) getenv('LCFA_TEST_WP_ROOT'));
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized local site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
$source = realpath(dirname(__DIR__, 2));
$fixture_build = (string) (getenv('LCFA_TEST_PLUGIN_BUILD') ?: 'source');
if (!in_array($fixture_build, ['source', 'installed'], true)) throw new RuntimeException('Unknown fixture build.');
if ($fixture_build === 'source') {
$GLOBALS['wp_filter']['option_active_plugins'][PHP_INT_MAX][] = ['function' => static function ($plugins) {
    $bridge = 'livecanvas-forge-ai/livecanvas-forge-ai.php';
    if (!in_array($bridge, $plugins, true)) throw new RuntimeException('Expected installed Bridge activation is missing.');
    return array_values(array_filter($plugins, static fn($plugin) => $plugin !== $bridge));
}, 'accepted_args' => 1];
$GLOBALS['wp_filter']['muplugins_loaded'][10][] = ['function' => static function () use ($source) {
    require $source . '/livecanvas-forge-ai.php';
}, 'accepted_args' => 0];
}
require $root . '/wp-load.php';
$expected_plugin = $fixture_build === 'source' ? $source : realpath($root . '/wp-content/plugins/livecanvas-forge-ai');
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || realpath(LCFA_DIR) !== $expected_plugin || !class_exists('LCFA_Desktop_Delivery')) throw new RuntimeException('Fixture plugin identity mismatch.');
