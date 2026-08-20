<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-stack-capabilities-tests/');

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $key): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
function apply_filters(string $hook, $value) { return $value; }

function lcfa_stack_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_stack_runtime(string $framework): array {
    return [
        'wordpress' => [
            'rest_api' => true,
            'abilities_api' => true,
        ],
        'livecanvas' => [
            'page_detection' => true,
            'framework_api' => true,
            'partial_storage' => true,
            'license_api' => true,
        ],
        'picostrap' => [
            'livecanvas_config' => $framework === 'picostrap',
            'sass_source' => $framework === 'picostrap',
            'bundle_helpers' => $framework === 'picostrap',
            'child_theme_writable' => $framework === 'picostrap',
        ],
        'picowind' => [
            'livecanvas_config' => $framework === 'picowind',
            'tailwind_entrypoint' => $framework === 'picowind',
            'child_theme_writable' => $framework === 'picowind',
        ],
        'windpress' => [
            'cache_api' => true,
            'volume_api' => true,
            'config_api' => true,
            'runtime_version_api' => true,
            'cache_flush_api' => true,
        ],
    ];
}

function lcfa_stack_snapshot(string $framework): array {
    return [
        'wordpress_version' => '7.0.0',
        'livecanvas_installed' => true,
        'livecanvas_active' => true,
        'livecanvas_version' => '4.9.3',
        'detected_framework' => $framework,
        'framework_version' => $framework === 'picostrap' ? '3.8.6' : '0.0.14',
        'windpress_installed' => $framework === 'picowind',
        'windpress_active' => $framework === 'picowind',
        'windpress_version' => $framework === 'picowind' ? '3.2.86' : '',
    ];
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-stack-capabilities.php';

$capabilities = new LCFA_Stack_Capabilities();

$picowind = $capabilities->evaluate(lcfa_stack_snapshot('picowind'), lcfa_stack_runtime('picowind'));
lcfa_stack_assert(($picowind['status'] ?? '') === 'supported', 'A tested Picowind/WindPress stack should be supported.');
lcfa_stack_assert(($picowind['profile_version'] ?? '') === '2026.08.3', 'The versioned compatibility profile should be exposed.');
lcfa_stack_assert(($picowind['wordpress_mode'] ?? '') === 'abilities', 'WordPress 7 should expose the Abilities operating mode when available.');
lcfa_stack_assert(($picowind['components']['windpress']['status'] ?? '') === 'supported', 'WindPress should be supported inside its tested range.');

$wordpress68_snapshot = lcfa_stack_snapshot('picostrap');
$wordpress68_snapshot['wordpress_version'] = '6.8.0';
$wordpress68_runtime = lcfa_stack_runtime('picostrap');
$wordpress68_runtime['wordpress']['abilities_api'] = false;
$wordpress68 = $capabilities->evaluate($wordpress68_snapshot, $wordpress68_runtime);
lcfa_stack_assert(($wordpress68['status'] ?? '') === 'supported', 'WordPress 6.8 REST mode should be fully supported without the Abilities API.');
lcfa_stack_assert(($wordpress68['wordpress_mode'] ?? '') === 'legacy_rest', 'WordPress 6.8 should identify the supported legacy REST operating mode.');

$wordpress71_snapshot = lcfa_stack_snapshot('picostrap');
$wordpress71_snapshot['wordpress_version'] = '7.1.0';
$wordpress71 = $capabilities->evaluate($wordpress71_snapshot, lcfa_stack_runtime('picostrap'));
lcfa_stack_assert(($wordpress71['status'] ?? '') === 'supported', 'WordPress 7.1 should be inside the tested profile.');

$wordpress72_snapshot = lcfa_stack_snapshot('picostrap');
$wordpress72_snapshot['wordpress_version'] = '7.2.0';
$wordpress72 = $capabilities->evaluate($wordpress72_snapshot, lcfa_stack_runtime('picostrap'));
lcfa_stack_assert(($wordpress72['status'] ?? '') === 'degraded', 'A future WordPress version should be usable but outside the tested profile.');

$wordpress7_without_abilities_runtime = lcfa_stack_runtime('picostrap');
$wordpress7_without_abilities_runtime['wordpress']['abilities_api'] = false;
$wordpress7_without_abilities = $capabilities->evaluate(lcfa_stack_snapshot('picostrap'), $wordpress7_without_abilities_runtime);
lcfa_stack_assert(($wordpress7_without_abilities['status'] ?? '') === 'degraded', 'WordPress 7 without its expected Abilities API should be reported as degraded.');

$future_windpress_snapshot = lcfa_stack_snapshot('picowind');
$future_windpress_snapshot['windpress_version'] = '4.1.0';
$future_windpress = $capabilities->evaluate($future_windpress_snapshot, lcfa_stack_runtime('picowind'));
lcfa_stack_assert(($future_windpress['status'] ?? '') === 'degraded', 'A newer untested WindPress release should degrade rather than block the stack.');
lcfa_stack_assert(($future_windpress['components']['windpress']['version_status'] ?? '') === 'untested', 'Future versions should be reported as untested.');

$missing_cache_runtime = lcfa_stack_runtime('picowind');
$missing_cache_runtime['windpress']['cache_api'] = false;
$missing_cache = $capabilities->evaluate(lcfa_stack_snapshot('picowind'), $missing_cache_runtime);
lcfa_stack_assert(($missing_cache['status'] ?? '') === 'unsupported', 'Missing required WindPress cache APIs should block reliable Picowind operations.');
lcfa_stack_assert(in_array('windpress:cache_api', $missing_cache['missing_capabilities'] ?? [], true), 'The missing WindPress cache API should be explicit.');

$picostrap = $capabilities->evaluate(lcfa_stack_snapshot('picostrap'), lcfa_stack_runtime('picostrap'));
lcfa_stack_assert(($picostrap['status'] ?? '') === 'supported', 'Picostrap should not require WindPress.');
lcfa_stack_assert(($picostrap['components']['windpress']['status'] ?? '') === 'not_applicable', 'Inactive WindPress should be non-blocking on Picostrap.');

$old_picostrap_snapshot = lcfa_stack_snapshot('picostrap');
$old_picostrap_snapshot['framework_version'] = '2.9.9';
$old_picostrap = $capabilities->evaluate($old_picostrap_snapshot, lcfa_stack_runtime('picostrap'));
lcfa_stack_assert(($old_picostrap['status'] ?? '') === 'unsupported', 'A framework below the operational minimum should be unsupported.');

$unknown = $capabilities->evaluate(lcfa_stack_snapshot('unknown'), lcfa_stack_runtime('unknown'));
lcfa_stack_assert(($unknown['status'] ?? '') === 'unsupported', 'An unrecognized framework should be unsupported.');
lcfa_stack_assert(in_array('framework:recognized_framework', $unknown['missing_capabilities'] ?? [], true), 'Unknown frameworks should expose the missing recognition capability.');

echo "PASS\n";
