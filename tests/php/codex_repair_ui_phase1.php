<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$admin = (string) file_get_contents($root . '/includes/class-lcfa-admin.php');
$plugin = (string) file_get_contents($root . '/livecanvas-forge-ai.php');
$settings = (string) file_get_contents($root . '/includes/class-lcfa-settings.php');
$bundle_builder = (string) file_get_contents($root . '/includes/class-lcfa-connection-bundle-builder.php');

function lcfa_repair_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

lcfa_repair_assert_contains("class-lcfa-codex-config-manager.php", $plugin, 'plugin bootstrap should load the Codex config manager');
lcfa_repair_assert_contains("admin_post_lcfa_repair_codex_connection", $admin, 'admin should register the Codex repair post action');
lcfa_repair_assert_contains("Repair Codex Connection", $admin, 'Connections UI should render a repair panel for Codex');
lcfa_repair_assert_contains("codex_repair_action", $admin, 'Codex repair forms should post a specific repair action');
lcfa_repair_assert_contains("sync_local_workspace_root(true)", $admin, 'Codex repair should force-sync WordPress workspace root');
lcfa_repair_assert_contains("Restart Codex or reload the MCP server", $admin, 'Codex repair should tell the user to restart Codex after config changes');
lcfa_repair_assert_contains("connection_last_bundle_hash", $settings, 'settings should keep a fingerprint field for verified connection config');
lcfa_repair_assert_contains("normalize_local_workspace_root", $settings, 'settings should normalize stale local workspace roots');
lcfa_repair_assert_contains("mcp remove livecanvas-forge", $bundle_builder, 'Codex helper should remove stale registration before adding the current one');

echo "PASS\n";
