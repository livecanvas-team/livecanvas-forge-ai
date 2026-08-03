<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-framework-prerequisites/');

function __(string $text, string $domain = ''): string { return $text; }
function apply_filters(string $hook, $value, ...$args) { return $value; }

function lcfa_prerequisite_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-framework-prerequisites.php';

$php_81 = new LCFA_Framework_Prerequisites('8.1.34');
$blocked = $php_81->check('picowind');
lcfa_prerequisite_assert(empty($blocked['ready']), 'Picowind must be blocked on PHP 8.1.');
lcfa_prerequisite_assert(($blocked['status'] ?? '') === 'php_upgrade_required', 'Blocked runtime should expose php_upgrade_required.');
lcfa_prerequisite_assert(($blocked['required_php'] ?? '') === '8.2.0', 'Picowind minimum PHP should be 8.2.0.');
lcfa_prerequisite_assert(str_contains((string) ($blocked['message'] ?? ''), 'PHP 8.2'), 'Blocker message should state the required PHP version.');

$php_82 = new LCFA_Framework_Prerequisites('8.2.0');
$ready = $php_82->check('picowind');
lcfa_prerequisite_assert(!empty($ready['ready']), 'Picowind should be allowed on PHP 8.2.');

$stricter = (new LCFA_Framework_Prerequisites('8.2.9'))->check('picowind', '>=8.3');
lcfa_prerequisite_assert(empty($stricter['ready']), 'A stricter child-theme PHP requirement should override the framework minimum.');
lcfa_prerequisite_assert(($stricter['required_php'] ?? '') === '8.3.0', 'The highest declared PHP requirement should win.');

$picostrap = $php_81->check('picostrap');
lcfa_prerequisite_assert(!empty($picostrap['ready']), 'Picostrap should not inherit the Picowind PHP 8.2 requirement.');

$picostrap_windpress = $php_82->check_windpress('picostrap', true, true);
lcfa_prerequisite_assert(($picostrap_windpress['status'] ?? '') === 'deactivation_recommended', 'Active WindPress should be marked as redundant on Picostrap.');
lcfa_prerequisite_assert(($picostrap_windpress['action'] ?? '') === 'deactivate', 'Picostrap guidance should offer an explicit WindPress deactivation action.');
lcfa_prerequisite_assert(empty($picostrap_windpress['required']), 'Picostrap must not require WindPress.');

$picowind_windpress = $php_82->check_windpress('picowind', true, true);
lcfa_prerequisite_assert(($picowind_windpress['status'] ?? '') === 'ready', 'Active WindPress should satisfy the Picowind runtime requirement.');
lcfa_prerequisite_assert(!empty($picowind_windpress['required']), 'Picowind must declare WindPress as required.');

$picowind_inactive = $php_82->check_windpress('picowind', true, false);
lcfa_prerequisite_assert(($picowind_inactive['status'] ?? '') === 'activation_required', 'Installed but inactive WindPress should block the Picowind runtime.');

$picowind_missing = $php_82->check_windpress('picowind', false, false);
lcfa_prerequisite_assert(($picowind_missing['status'] ?? '') === 'installation_required', 'Missing WindPress should block the Picowind runtime.');

echo "PASS\n";
