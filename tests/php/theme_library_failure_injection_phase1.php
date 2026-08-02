<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

define('ABSPATH', '/tmp/lcfa-theme-library-failure-injection/');
define('LCFA_E2E_MODE', true);

$GLOBALS['lcfa_failure_stage'] = '';

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $value)); }
function apply_filters(string $hook, $value, ...$args) {
    return $hook === 'lcfa_theme_library_e2e_failure_stage' ? $GLOBALS['lcfa_failure_stage'] : $value;
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php';

$reflection = new ReflectionClass(LCFA_Theme_Library_Importer::class);
$importer = $reflection->newInstanceWithoutConstructor();
$inject = lcfa_test_accessible_reflector($reflection->getMethod('maybe_inject_e2e_failure'));

foreach (['after_media', 'after_partials', 'after_homepage', 'after_build'] as $stage) {
    $GLOBALS['lcfa_failure_stage'] = $stage;
    $thrown = false;
    try {
        $inject->invoke($importer, $stage, 'audit-test', 'sample-theme');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (Throwable $throwable) {
        $thrown = str_contains($throwable->getMessage(), $stage);
    }

    if (!$thrown) {
        fwrite(STDERR, sprintf("E2E checkpoint %s should inject a controlled failure.\n", $stage));
        exit(1);
    }
}

$GLOBALS['lcfa_failure_stage'] = 'after_media';
$inject->invoke($importer, 'after_build', 'audit-test', 'sample-theme');

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php');
foreach (['after_media', 'after_partials', 'after_homepage', 'after_build'] as $stage) {
    if (!str_contains($source, "maybe_inject_e2e_failure('{$stage}'")) {
        fwrite(STDERR, sprintf("Importer should expose the %s checkpoint.\n", $stage));
        exit(1);
    }
}

echo "PASS\n";
