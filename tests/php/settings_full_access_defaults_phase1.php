<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_options'] = [];

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function wp_parse_args($args, $defaults = []): array {
    return array_merge($defaults, is_array($args) ? $args : []);
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-settings.php';

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$defaults = LCFA_Settings::defaults();
lcfa_assert_same('advanced_templates', $defaults['permission_profile'] ?? null, 'settings defaults should expose advanced_templates as the default permission profile');
lcfa_assert_same(true, $defaults['allow_file_fallback'] ?? null, 'settings defaults should enable file fallback by default');

$resolved = LCFA_Settings::get();
lcfa_assert_same('advanced_templates', $resolved['permission_profile'] ?? null, 'settings get() should resolve advanced_templates when no option is stored');
lcfa_assert_same(true, $resolved['allow_file_fallback'] ?? null, 'settings get() should resolve file fallback enabled when no option is stored');

echo "PASS\n";
