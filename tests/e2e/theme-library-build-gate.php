<?php

declare(strict_types=1);

/**
 * Real WordPress Theme Library build-gate harness.
 *
 * LCFA_E2E_WP_ROOT=/path/to/wordpress php tests/e2e/theme-library-build-gate.php baseline
 * LCFA_E2E_WP_ROOT=/path/to/wordpress php tests/e2e/theme-library-build-gate.php import asteria-search
 * LCFA_E2E_WP_ROOT=/path/to/wordpress php tests/e2e/theme-library-build-gate.php build asteria-search
 * LCFA_E2E_WP_ROOT=/path/to/wordpress php tests/e2e/theme-library-build-gate.php rollback asteria-search
 * LCFA_E2E_MODE=1 LCFA_E2E_FAILURE_STAGE=after_media LCFA_E2E_WP_ROOT=/path/to/wordpress php tests/e2e/theme-library-build-gate.php failure asteria-search
 */

$wp_root = rtrim((string) getenv('LCFA_E2E_WP_ROOT'), '/\\');
$wp_load = $wp_root . '/wp-load.php';
$e2e_mode = (string) getenv('LCFA_E2E_MODE') === '1';
$failure_stage = preg_replace('/[^a-z0-9_]/', '', strtolower((string) getenv('LCFA_E2E_FAILURE_STAGE')));
$failure_stages = ['after_media', 'after_partials', 'after_homepage', 'after_build'];

if ($wp_root === '' || !is_readable($wp_load)) {
    fwrite(STDERR, "Set LCFA_E2E_WP_ROOT to a readable WordPress root.\n");
    exit(2);
}

if ($e2e_mode) {
    define('LCFA_E2E_MODE', true);
    if (in_array($failure_stage, $failure_stages, true)) {
        define('LCFA_E2E_FAILURE_STAGE', $failure_stage);
    }
}

require $wp_load;

if (!class_exists('LCFA_Theme_Library_Importer', false)) {
    fwrite(STDERR, "LiveCanvas AI Bridge must be active on the target WordPress site.\n");
    exit(2);
}

$action = sanitize_key((string) ($argv[1] ?? 'baseline'));
$slug = sanitize_key((string) ($argv[2] ?? 'asteria-search'));

$environment = new LCFA_Environment();
$windpress = new LCFA_WindPress_Bridge($environment);
$local_mcp = new LCFA_Local_MCP_Bridge($environment);
$gateway = new LCFA_Design_System_Build_Gateway($local_mcp);
$validator = new LCFA_Theme_Library_Validator();
$installer = new LCFA_Theme_Library_Installer($validator, $windpress);
$importer = new LCFA_Theme_Library_Importer($installer, $validator, $windpress, $gateway);
$rollback = new LCFA_Theme_Library_Rollback($windpress);

$catalog_path = trailingslashit(LCFA_DIR) . 'examples/theme-library/catalog.json';
$catalog = is_readable($catalog_path) ? json_decode((string) file_get_contents($catalog_path), true) : [];
$theme = null;

foreach ((array) ($catalog['themes'] ?? []) as $candidate) {
    if (is_array($candidate) && sanitize_key((string) ($candidate['slug'] ?? '')) === $slug) {
        $theme = $candidate;
        break;
    }
}

$emit = static function (array $payload, int $status = 0): void {
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($status);
};

$baseline = static function () use ($environment, $windpress, $gateway): array {
    return [
        'site_url'       => home_url('/'),
        'theme'          => wp_get_theme()->get_stylesheet(),
        'front_page'     => (int) get_option('page_on_front', 0),
        'framework'      => (string) ($environment->get_snapshot()['detected_framework'] ?? ''),
        'windpress'      => $windpress->get_status(),
        'compiled_cache' => $windpress->get_compiled_cache_state(),
        'build_gateway'  => $gateway->refresh_status(),
        'imports'        => LCFA_Theme_Library_Importer::get_imports(),
    ];
};

if ($action === 'baseline') {
    $emit($baseline());
}

if ($action === 'import') {
    if (!$theme) {
        $emit(['ok' => false, 'message' => 'Theme not found in the bundled catalog.', 'slug' => $slug], 1);
    }

    $preview = $installer->preview($theme);
    if (empty($preview['ok'])) {
        $emit(['ok' => false, 'stage' => 'preview', 'result' => $preview], 1);
    }

    $install = $installer->install($theme);
    if (empty($install['ok'])) {
        $emit(['ok' => false, 'stage' => 'install', 'result' => $install], 1);
    }

    $result = $importer->import($theme, true);
    $emit([
        'ok'       => !empty($result['ok']),
        'stage'    => 'import',
        'preview'  => $preview,
        'install'  => $install,
        'result'   => $result,
        'baseline' => $baseline(),
    ], !empty($result['ok']) ? 0 : 1);
}

if ($action === 'failure') {
    if (!$e2e_mode || !in_array($failure_stage, $failure_stages, true)) {
        $emit([
            'ok'      => false,
            'message' => 'Set LCFA_E2E_MODE=1 and a supported LCFA_E2E_FAILURE_STAGE.',
            'stages'  => $failure_stages,
        ], 2);
    }

    if (!$theme) {
        $emit(['ok' => false, 'message' => 'Theme not found in the bundled catalog.', 'slug' => $slug], 1);
    }

    $before = $baseline();
    $preview = $installer->preview($theme);
    if (empty($preview['ok'])) {
        $emit(['ok' => false, 'stage' => 'preview', 'result' => $preview], 1);
    }

    $install = $installer->install($theme);
    if (empty($install['ok'])) {
        $emit(['ok' => false, 'stage' => 'install', 'result' => $install], 1);
    }

    $result = $importer->import($theme, true, true);
    $after = $baseline();
    $automatic_rollback = is_array($result['automatic_rollback'] ?? null)
        ? $result['automatic_rollback']
        : [];
    $passed = empty($result['ok'])
        && ($result['status'] ?? '') === 'failed_rolled_back'
        && !empty($automatic_rollback['attempted'])
        && !empty($automatic_rollback['ok'])
        && ($before['theme'] ?? '') === ($after['theme'] ?? '')
        && (int) ($before['front_page'] ?? 0) === (int) ($after['front_page'] ?? 0)
        && ($before['compiled_cache']['status'] ?? '') === ($after['compiled_cache']['status'] ?? '');

    $emit([
        'ok'            => $passed,
        'stage'         => $failure_stage,
        'result'        => $result,
        'before'        => $before,
        'after'         => $after,
        'state_matches' => [
            'theme'          => ($before['theme'] ?? '') === ($after['theme'] ?? ''),
            'front_page'     => (int) ($before['front_page'] ?? 0) === (int) ($after['front_page'] ?? 0),
            'compiled_cache' => ($before['compiled_cache']['status'] ?? '') === ($after['compiled_cache']['status'] ?? ''),
        ],
    ], $passed ? 0 : 1);
}

if ($action === 'build') {
    $result = $importer->build($slug);
    $emit([
        'ok'       => !empty($result['ok']),
        'stage'    => 'build',
        'result'   => $result,
        'baseline' => $baseline(),
    ], !empty($result['ok']) ? 0 : 1);
}

if ($action === 'rollback') {
    $imports = LCFA_Theme_Library_Importer::get_imports();
    $audit_id = (string) ($imports[$slug]['audit_id'] ?? '');
    $result = $rollback->rollback($audit_id, false);
    $emit([
        'ok'       => !empty($result['ok']),
        'stage'    => 'rollback',
        'audit_id' => $audit_id,
        'result'   => $result,
        'baseline' => $baseline(),
    ], !empty($result['ok']) ? 0 : 1);
}

$emit(['ok' => false, 'message' => 'Unknown action.', 'action' => $action], 2);
