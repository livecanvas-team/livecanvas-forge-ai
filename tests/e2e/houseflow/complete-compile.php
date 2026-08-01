<?php

declare(strict_types=1);

$wp_root = rtrim((string) getenv('LCFA_WP_ROOT'), '/');
$compiled_css_path = (string) getenv('LCFA_COMPILED_CSS');
if ($wp_root === '' || !is_readable($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "LCFA_WP_ROOT must point to a readable WordPress installation.\n");
    exit(1);
}
if ($compiled_css_path === '' || !is_readable($compiled_css_path)) {
    fwrite(STDERR, "LCFA_COMPILED_CSS must point to a readable CSS bundle.\n");
    exit(1);
}

require $wp_root . '/wp-load.php';
wp_set_current_user(1);

function houseflow_compile_execute(string $name, array $input): array {
    $ability = wp_get_ability($name);
    if (!$ability) {
        throw new RuntimeException('Ability not registered: ' . $name);
    }
    $result = $ability->execute($input);
    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    return is_array($result) ? $result : [];
}

try {
    $preview = houseflow_compile_execute('livecanvas-forge-ai/picostrap-compile-preview', []);
    $apply = houseflow_compile_execute('livecanvas-forge-ai/picostrap-compile-apply', [
        'compiled_css' => (string) file_get_contents($compiled_css_path),
    ]);
    $bundle = (array) ($apply['picostrap_compile_apply']['bundle'] ?? []);
    if (empty($apply['picostrap_compile_apply']['ok']) || empty($bundle['ok'])) {
        throw new RuntimeException((string) ($apply['picostrap_compile_apply']['message'] ?? $bundle['message'] ?? 'Picostrap compile apply failed.'));
    }

    $cache_preview = houseflow_compile_execute('livecanvas-forge-ai/cache-flush', ['dry_run' => true]);
    $cache_apply = houseflow_compile_execute('livecanvas-forge-ai/cache-flush', ['dry_run' => false]);
    $result = [
        'ok'            => true,
        'preview'       => $preview['picostrap_compile_preview'] ?? [],
        'bundle'        => $bundle,
        'cache_preview' => $cache_preview['cache_flush'] ?? [],
        'cache_apply'   => $cache_apply['cache_flush'] ?? [],
    ];
    update_option('lcfa_houseflow_compile_report', $result, false);
    echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
