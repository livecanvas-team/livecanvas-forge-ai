<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$admin = (string) file_get_contents($root . '/includes/class-lcfa-admin.php');
$css = (string) file_get_contents($root . '/assets/admin-v2.css');

function lcfa_command_views_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
    exit(1);
}

foreach (['command', 'inventory', 'runtimes', 'files', 'history'] as $view) {
    lcfa_command_views_assert_contains("'{$view}'", $admin, "Command Deck should register the {$view} workspace.");
}

lcfa_command_views_assert_contains('render_command_view_nav', $admin, 'Command Deck should render progressive workspace navigation.');
lcfa_command_views_assert_contains('get_command_view_url', $admin, 'Command Deck workspace links should preserve a dedicated query state.');
lcfa_command_views_assert_contains('lcfa-command-action-reference', $admin, 'Long action documentation should stay collapsed by default.');
lcfa_command_views_assert_contains('Site-to-site runtime is separate from coding-agent pairing', $admin, 'Runtime UI should distinguish agent pairing from optional site-to-site execution.');
lcfa_command_views_assert_contains('lcfa-workspace-viewbar', $css, 'Shared workspace navigation should have operational UI styling.');
lcfa_command_views_assert_contains('lcfa-grid--single', $css, 'Single-purpose Command Deck workspaces should use the full available width.');

echo "PASS\n";
