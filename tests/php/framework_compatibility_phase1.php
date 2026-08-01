<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-framework-compatibility/');

$GLOBALS['lcfa_framework'] = 'picostrap';
$GLOBALS['lcfa_compatibility_override'] = null;
$GLOBALS['lcfa_registered_filters'] = [];

function sanitize_key($value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function add_filter(string $hook, callable $callback, int $priority = 10): bool {
    $GLOBALS['lcfa_registered_filters'][$hook] = [
        'callback' => $callback,
        'priority' => $priority,
    ];
    return true;
}

function apply_filters(string $hook, $value, ...$args) {
    if ($hook === 'lcfa_disable_windpress_runtime_on_picostrap' && $GLOBALS['lcfa_compatibility_override'] !== null) {
        return (bool) $GLOBALS['lcfa_compatibility_override'];
    }
    return $value;
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'detected_framework' => (string) $GLOBALS['lcfa_framework'],
        ];
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-framework-compatibility.php';

function lcfa_compatibility_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$compatibility = new LCFA_Framework_Compatibility(new LCFA_Environment());
$compatibility->hooks();

lcfa_compatibility_assert(
    isset($GLOBALS['lcfa_registered_filters']['f!windpress/core/runtime:is_prevent_load']),
    'The Picostrap compatibility guard should register on the WindPress runtime filter.'
);
lcfa_compatibility_assert(
    ($GLOBALS['lcfa_registered_filters']['f!windpress/core/runtime:is_prevent_load']['priority'] ?? 0) === 20,
    'The compatibility guard should run after WindPress integrations register.'
);
lcfa_compatibility_assert(
    $compatibility->prevent_windpress_runtime_on_picostrap(false),
    'WindPress frontend runtime should be suppressed for Picostrap.'
);

$GLOBALS['lcfa_framework'] = 'picowind';
lcfa_compatibility_assert(
    !$compatibility->prevent_windpress_runtime_on_picostrap(false),
    'WindPress frontend runtime should remain available for Picowind.'
);

$GLOBALS['lcfa_framework'] = 'picostrap';
$GLOBALS['lcfa_compatibility_override'] = false;
lcfa_compatibility_assert(
    !$compatibility->prevent_windpress_runtime_on_picostrap(false),
    'The compatibility filter should allow an explicit mixed-stack opt-out.'
);
lcfa_compatibility_assert(
    $compatibility->prevent_windpress_runtime_on_picostrap(true),
    'An earlier runtime suppression decision should never be reversed.'
);

echo "PASS\n";
