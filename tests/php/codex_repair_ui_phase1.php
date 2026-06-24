<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$admin = (string) file_get_contents($root . '/includes/class-lcfa-admin.php');
$plugin = (string) file_get_contents($root . '/livecanvas-forge-ai.php');
$settings = (string) file_get_contents($root . '/includes/class-lcfa-settings.php');
$bundle_builder = (string) file_get_contents($root . '/includes/class-lcfa-connection-bundle-builder.php');
$direct_onboarding = (string) file_get_contents($root . '/includes/class-lcfa-direct-agent-onboarding.php');
$power_mode = (string) file_get_contents($root . '/includes/class-lcfa-power-mode.php');
$admin_css = (string) file_get_contents($root . '/assets/admin.css');

function lcfa_repair_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

lcfa_repair_assert_contains("class-lcfa-codex-config-manager.php", $plugin, 'plugin bootstrap should load the Codex config manager');
lcfa_repair_assert_contains("class-lcfa-mcp-session-manager.php", $plugin, 'plugin bootstrap should load the secure MCP session manager');
lcfa_repair_assert_contains("class-lcfa-github-updater.php", $plugin, 'plugin bootstrap should load the GitHub updater');
lcfa_repair_assert_contains("class-lcfa-direct-agent-onboarding.php", $plugin, 'plugin bootstrap should load the Direct Agent onboarding service');
lcfa_repair_assert_contains("class-lcfa-power-mode.php", $plugin, 'plugin bootstrap should load the Power Mode service');
lcfa_repair_assert_contains("admin_post_lcfa_repair_codex_connection", $admin, 'admin should register the Codex repair post action');
lcfa_repair_assert_contains("Repair Codex Connection", $admin, 'Connections UI should render a repair panel for Codex');
lcfa_repair_assert_contains("codex_repair_action", $admin, 'Codex repair forms should post a specific repair action');
lcfa_repair_assert_contains("get_codex_onboarding_state", $admin, 'Connections UI should expose a unified Codex onboarding state model');
lcfa_repair_assert_contains("render_codex_fast_path_panel", $admin, 'Connections UI should render Codex as the fast path');
lcfa_repair_assert_contains("connect_codex", $admin, 'Codex fast path should post a composite connect action');
lcfa_repair_assert_contains("Other clients", $admin, 'non-Codex clients should remain available under Other clients');
lcfa_repair_assert_contains("sync_local_workspace_root(true)", $admin, 'Codex repair should force-sync WordPress workspace root');
lcfa_repair_assert_contains("Restart Codex or reload the MCP server", $admin, 'Codex repair should tell the user to restart Codex after config changes');
lcfa_repair_assert_contains("connection_current_step'] = 'smoke_test'", $admin, 'Codex connect should move to smoke_test instead of ready');
lcfa_repair_assert_contains("@livecanvas/ai-bridge-mcp@latest", $admin, 'remote Codex should use the secure AI Bridge MCP package');
lcfa_repair_assert_contains("admin_post_lcfa_mcp_pairing_approve", $admin, 'admin should allow approving secure Codex pairing requests');
lcfa_repair_assert_contains("lcfa-secure-codex-pairing-sessions", $admin, 'secure Codex pairing panel should expose a stable approval anchor');
lcfa_repair_assert_contains("WP_API_PASSWORD", $admin, 'admin should keep the legacy Application Password adapter only in advanced fallback');
lcfa_repair_assert_contains("Remote Codex prerequisites", $admin, 'remote Codex should block setup behind a prerequisite checklist');
lcfa_repair_assert_contains("does not use LCFA_WP_ROOT", $admin, 'remote Codex UI should make clear that no local WordPress root is used');
lcfa_repair_assert_contains("Power Mode status", $admin, 'Connections UI should show the Power Mode policy foundation');
lcfa_repair_assert_contains("AI Bridge auto updates are paused", $admin, 'dashboard should explain that auto updates require an active LiveCanvas license');
lcfa_repair_assert_contains(".lcfa-form label.lcfa-checkbox", $admin_css, 'admin CSS should keep checkbox labels aligned inside forms');
lcfa_repair_assert_contains("missing_credentials", $direct_onboarding, 'Direct Mode should expose a missing_credentials state');
lcfa_repair_assert_contains("ai-bridge-session", $direct_onboarding, 'Direct Mode should identify the secure AI Bridge session strategy');
lcfa_repair_assert_contains("content-patch", $power_mode, 'Power Mode should list targeted content patching tools');
lcfa_repair_assert_contains("theme-file-write", $power_mode, 'Power Mode should list guarded theme file tools');
lcfa_repair_assert_contains("connection_last_bundle_hash", $settings, 'settings should keep a fingerprint field for verified connection config');
lcfa_repair_assert_contains("power_mode", $settings, 'settings should persist the Power Mode policy');
lcfa_repair_assert_contains("normalize_local_workspace_root", $settings, 'settings should normalize stale local workspace roots');
lcfa_repair_assert_contains('mcp remove \' . $server_name', $bundle_builder, 'Codex helper should remove stale registration before adding the current one');

echo "PASS\n";
