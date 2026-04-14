<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('WP_CONTENT_DIR', '/Users/commander/Studio/consultala/wp-content');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    $value = strtolower($value);

    return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function sanitize_text_field($value): string {
    return trim((string) $value);
}

function sanitize_textarea_field($value): string {
    return trim((string) $value);
}

function sanitize_file_name(string $value): string {
    return (string) preg_replace('/[^A-Za-z0-9\.\-_]/', '-', $value);
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function esc_html(string $value): string {
    return $value;
}

function esc_attr(string $value): string {
    return $value;
}

function esc_url(string $value): string {
    return $value;
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function trailingslashit(string $value): string {
    return rtrim($value, '/\\') . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

function wp_normalize_path(string $value): string {
    return str_replace('\\', '/', $value);
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_false(bool $condition, string $message): void {
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';
require LCFA_DIR . 'includes/class-lcfa-connection-wizard-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-workspace-access.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$builder = new LCFA_Connection_Bundle_Builder();

$workspace_access_ready = LCFA_Workspace_Access::inspect('/Users/commander/Studio/consultala', static function (string $path): bool {
    return in_array($path, ['/Users/commander/Studio/consultala'], true);
}, static function (string $path): bool {
    return in_array($path, ['/Users/commander/Studio/consultala'], true);
});

lcfa_assert_true(!empty($workspace_access_ready['available']), 'workspace access should be available when the directory exists and is writable');
lcfa_assert_same('ready', $workspace_access_ready['reason'] ?? '', 'workspace access should report ready for writable directories');

$workspace_access_unreachable = LCFA_Workspace_Access::inspect('/Users/commander/Studio/consultala', static function (string $path): bool {
    return false;
}, static function (string $path): bool {
    return false;
});

lcfa_assert_false(!empty($workspace_access_unreachable['available']), 'workspace access should be unavailable when the PHP process cannot see the host path');
lcfa_assert_same('unreachable', $workspace_access_unreachable['reason'] ?? '', 'workspace access should report unreachable for invisible host paths');

$workspace_access_runtime = LCFA_Workspace_Access::inspect('/wordpress');

lcfa_assert_false(!empty($workspace_access_runtime['available']), 'runtime-only mount paths should not be treated as writable workspaces');
lcfa_assert_same('runtime_only', $workspace_access_runtime['reason'] ?? '', 'runtime-only mount paths should be rejected explicitly');

$bundle = $builder->build([
    'client'         => 'opencode',
    'mode'           => 'local',
    'workspace_root' => '/Users/commander/Studio/consultala',
    'common'         => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'test-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=test-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('opencode', $bundle['client'], 'bundle should preserve the selected client');
lcfa_assert_same('local', $bundle['mode'], 'bundle should preserve local mode');
lcfa_assert_same('/Users/commander/Studio/consultala/opencode.json', $bundle['workspace_files'][0]['path'] ?? '', 'local OpenCode bundle should target workspace opencode.json');
lcfa_assert_same('/Users/commander/Studio/consultala', $bundle['environment']['LCFA_WP_ROOT'] ?? '', 'local bundle should replace runtime wp_root with workspace_root');
lcfa_assert_same('/Users/commander/Studio/consultala', $bundle['workspace_root'] ?? '', 'local bundle should expose the normalized workspace root');
lcfa_assert_same('generated', $bundle['status'] ?? '', 'bundle should expose generated status');
$opencode_config = json_decode((string) ($bundle['workspace_files'][0]['content'] ?? ''), true);
lcfa_assert_true(is_array($opencode_config), 'local OpenCode bundle should serialize a valid JSON config');
lcfa_assert_same('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js', $opencode_config['mcp']['livecanvas-forge']['command'][1] ?? '', 'local OpenCode bundle should resolve the MCP script to an absolute workspace path');
lcfa_assert_true(strpos((string) ($bundle['command_string'] ?? ''), '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js') !== false, 'connection summary should display the absolute MCP script path');

$fallback_bundle = $builder->build([
    'client'         => 'cursor',
    'mode'           => 'local',
    'workspace_root' => '',
    'common'         => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'test-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=cursor',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=test-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('/Users/commander/Studio/consultala', $fallback_bundle['workspace_root'] ?? '', 'local bundle should fall back to common wp_root when workspace_root is empty');
lcfa_assert_same('/Users/commander/Studio/consultala', $fallback_bundle['environment']['LCFA_WP_ROOT'] ?? '', 'fallback local bundle should expose the inferred workspace root');
lcfa_assert_same('/Users/commander/Studio/consultala/.cursor/mcp.json', $fallback_bundle['workspace_files'][0]['path'] ?? '', 'fallback local bundle should still target the inferred workspace path');

$legacy_saved_bundle = $builder->build([
    'client'         => 'codex',
    'mode'           => 'local',
    'workspace_root' => '/wordpress',
    'common'         => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'test-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=test-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('/Users/commander/Studio/consultala', $legacy_saved_bundle['workspace_root'] ?? '', 'legacy saved runtime roots should be replaced with the derived local workspace root');
lcfa_assert_same('/Users/commander/Studio/consultala', $legacy_saved_bundle['environment']['LCFA_WP_ROOT'] ?? '', 'legacy saved runtime roots should not leak into the generated environment');
lcfa_assert_true(strpos((string) ($legacy_saved_bundle['shortcut_command'] ?? ''), 'mcp add livecanvas-forge') !== false, 'codex bundles should expose the registration shortcut');
lcfa_assert_true(strpos((string) ($legacy_saved_bundle['shortcut_command'] ?? ''), '/Applications/Codex.app/Contents/Resources/codex') !== false, 'codex shortcut should auto-detect the desktop app CLI when codex is not in PATH');
lcfa_assert_true(strpos((string) ($legacy_saved_bundle['shortcut_command'] ?? ''), '[mcp_servers.livecanvas-forge]') !== false, 'codex shortcut should include a config.toml fallback snippet');
lcfa_assert_same((string) ($legacy_saved_bundle['shortcut_command'] ?? ''), (string) ($legacy_saved_bundle['copy_command_string'] ?? ''), 'codex bundles should prefer copying the Codex shortcut, not the raw MCP command');
lcfa_assert_true(strpos((string) ($legacy_saved_bundle['workspace_files'][0]['content'] ?? ''), '/Applications/Codex.app/Contents/Resources/codex') !== false, 'codex helper script should auto-detect the desktop app CLI');
lcfa_assert_true(strpos((string) ($legacy_saved_bundle['workspace_files'][0]['content'] ?? ''), '[mcp_servers.livecanvas-forge]') !== false, 'codex helper script should include a config.toml fallback snippet');

$runtime_only_script = <<<'PHP'
<?php
chdir('/');
define('ABSPATH', '/wordpress/');
define('LCFA_DIR', '/wordpress/wp-content/plugins/livecanvas-forge-ai/');
function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { $value = strtolower($value); return (string) preg_replace('/[^a-z0-9_\-]/', '', $value); }
function trailingslashit(string $value): string { return rtrim($value, '/\\') . '/'; }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); }
function wp_normalize_path(string $value): string { return str_replace('\\', '/', $value); }
require '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-connection-bundle-builder.php';
$builder = new LCFA_Connection_Bundle_Builder();
$bundle = $builder->build([
    'client' => 'opencode',
    'mode' => 'local',
    'workspace_root' => '/wordpress',
    'common' => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'test-token',
        'wp_root' => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode',
        'env' => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=test-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);
echo json_encode($bundle, JSON_UNESCAPED_SLASHES);
PHP;

$runtime_only_output = shell_exec('php <<\'PHP\'' . "\n" . $runtime_only_script . "\nPHP\n");
$runtime_only_bundle = json_decode((string) $runtime_only_output, true);

lcfa_assert_true(is_array($runtime_only_bundle), 'runtime-only builder subprocess should return a JSON bundle');
lcfa_assert_same('', $runtime_only_bundle['workspace_root'] ?? null, 'runtime-only environments should not autofill a misleading local workspace root');
lcfa_assert_false(isset($runtime_only_bundle['environment']['LCFA_WP_ROOT']), 'runtime-only environments should not leak runtime LCFA_WP_ROOT into the generated environment');
lcfa_assert_true(empty($runtime_only_bundle['workspace_files']), 'runtime-only environments should not offer local workspace writes before a host path is known');

$remote_bundle = $builder->build([
    'client'         => 'opencode',
    'mode'           => 'remote',
    'workspace_root' => '',
    'common'         => [
        'rest_base' => 'https://example.com/wp-json/lcfa/v1/',
        'mcp_token' => 'remote-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode',
        'env'     => [
            'LCFA_REST_BASE=https://example.com/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=remote-token',
        ],
    ],
]);

lcfa_assert_true(empty($remote_bundle['environment']['LCFA_WP_ROOT']), 'remote bundle should not expose LCFA_WP_ROOT');
lcfa_assert_true(empty($remote_bundle['workspace_files']), 'remote bundle should not propose local workspace writes');
lcfa_assert_same('opencode.json', $remote_bundle['download_files'][0]['name'] ?? '', 'remote bundle should expose downloadable client config');
lcfa_assert_true(strpos((string) ($remote_bundle['download_files'][0]['content'] ?? ''), '"livecanvas-forge"') !== false, 'remote bundle should serialize the MCP server name');

$onboarding = new LCFA_Connection_Onboarding($builder);

$choose_client_state = $onboarding->derive_state([
    'preferred_client'            => '',
    'connection_mode'             => '',
    'workspace_root'              => '',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => '',
]);

lcfa_assert_same('not_connected', $choose_client_state['status'] ?? '', 'missing client should keep the connection in not_connected');
lcfa_assert_same('choose_client', $choose_client_state['current_step'] ?? '', 'missing client should open the choose_client step');

$state = $onboarding->derive_state([
    'preferred_client'            => 'opencode',
    'connection_mode'             => 'local',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => 'generate_bundle',
], [
    'local_ready'  => true,
    'remote_ready' => false,
]);

lcfa_assert_same('not_connected', $state['status'] ?? '', 'empty verification metadata should keep the page in not_connected');
lcfa_assert_same('generate_bundle', $state['current_step'] ?? '', 'saved wizard progress should reopen the bundle step');

$fast_path_state = $onboarding->derive_state([
    'preferred_client'            => 'opencode',
    'connection_mode'             => 'local',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => 'choose_mode',
], [
    'local_ready'  => true,
    'remote_ready' => false,
]);

lcfa_assert_same('confirm_details', $fast_path_state['current_step'] ?? '', 'OpenCode local flow should skip the separate choose_mode step once local mode is known');

$presenter = new LCFA_Connection_Wizard_Presenter();
$opencode_fast_path = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'generate_bundle',
    ],
    'bundle' => [
        'client'          => 'opencode',
        'mode'            => 'local',
        'workspace_root'  => '/Users/commander/Studio/consultala',
        'workspace_files' => [['path' => '/Users/commander/Studio/consultala/opencode.json', 'content' => '{}']],
        'download_files'  => [['name' => 'opencode.json', 'content' => '{}']],
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Download opencode.json', $opencode_fast_path['active_panel']['primary_cta']['label'] ?? '', 'OpenCode fast path should use direct bundle copy');
lcfa_assert_same('Copy command', $opencode_fast_path['active_panel']['secondary_ctas'][0]['label'] ?? '', 'non-writable local mode should expose copy command as the secondary action');
lcfa_assert_same('What this looks like in OpenCode', $opencode_fast_path['visual_help']['title'] ?? '', 'OpenCode fast path should expose visual helper strip');
lcfa_assert_same('Check MCP: livecanvas-forge', $opencode_fast_path['visual_help']['items'][1]['title'] ?? '', 'OpenCode visual strip should explain the green MCP state');
lcfa_assert_false(($opencode_fast_path['technical_summary']['expanded'] ?? true), 'OpenCode bundle step should keep technical summary secondary');
lcfa_assert_same(4, count($opencode_fast_path['steps'] ?? []), 'OpenCode local flow should use the shortened four-step path');
lcfa_assert_same('confirm_details', $opencode_fast_path['steps'][1]['key'] ?? '', 'OpenCode local flow should collapse the mode step into confirm details');
lcfa_assert_same('active', $opencode_fast_path['steps'][2]['state'] ?? '', 'generate_bundle should be the active step in the shortened path');
lcfa_assert_same('locked', $opencode_fast_path['steps'][3]['state'] ?? '', 'smoke_test should remain locked before bundle completion');

$opencode_smoke = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'smoke_test',
    ],
    'bundle' => [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Run smoke test', $opencode_smoke['active_panel']['primary_cta']['label'] ?? '', 'smoke test CTA should remain explicit');
lcfa_assert_same('Return here and run the smoke test', $opencode_smoke['visual_help']['items'][2]['title'] ?? '', 'OpenCode smoke step should keep the return-to-WordPress cue');

$codex_bundle_view = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'generate_bundle',
    ],
    'bundle' => [
        'client'              => 'codex',
        'mode'                => 'local',
        'workspace_root'      => '/Users/commander/Studio/consultala',
        'command_string'      => '\'node\' \'/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js\' \'--transport=stdio\'',
        'copy_command_string' => "codex mcp add livecanvas-forge \\\n  --env LCFA_REST_BASE='http://localhost:8887/wp-json/lcfa/v1/' \\\n  --env LCFA_MCP_TOKEN='test-token' \\\n  --env LCFA_WP_ROOT='/Users/commander/Studio/consultala' \\\n  -- 'node' '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js' '--transport=stdio'",
        'shortcut_title'      => 'Codex shortcut',
        'shortcut_command'    => "codex mcp add livecanvas-forge \\\n  --env LCFA_REST_BASE='http://localhost:8887/wp-json/lcfa/v1/' \\\n  --env LCFA_MCP_TOKEN='test-token' \\\n  --env LCFA_WP_ROOT='/Users/commander/Studio/consultala' \\\n  -- 'node' '/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js' '--transport=stdio'",
        'workspace_files'     => [['path' => '/Users/commander/Studio/consultala/livecanvas-forge.codex.sh', 'content' => '#!/usr/bin/env bash']],
        'download_files'      => [['name' => 'livecanvas-forge.codex.sh', 'content' => '#!/usr/bin/env bash']],
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Copy Codex shortcut', $codex_bundle_view['active_panel']['primary_cta']['label'] ?? '', 'Codex local flow should elevate the registration shortcut to the primary action');
lcfa_assert_same('Download Codex helper', $codex_bundle_view['active_panel']['secondary_ctas'][0]['label'] ?? '', 'Codex local flow should still offer the helper script as a fallback');
lcfa_assert_same('What to do in Codex', $codex_bundle_view['visual_help']['title'] ?? '', 'Codex local flow should expose a Codex-specific visual strip');

$codex_smoke = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'smoke_test',
    ],
    'bundle' => [
        'client'         => 'codex',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Ready to verify Codex?', $codex_smoke['active_panel']['title'] ?? '', 'Codex smoke-test step should explain that Codex registration must happen first');
lcfa_assert_true(strpos((string) ($codex_smoke['active_panel']['description'] ?? ''), 'Codex shortcut') !== false, 'Codex smoke-test description should mention the shortcut explicitly');

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin_instance = $admin_reflection->newInstanceWithoutConstructor();
$default_tab_method = new ReflectionMethod('LCFA_Admin', 'get_default_dashboard_tab');
$post_setup_redirect_method = new ReflectionMethod('LCFA_Admin', 'get_post_setup_redirect_tab');
$hero_content_method = new ReflectionMethod('LCFA_Admin', 'get_dashboard_hero_content');
$reconfigure_connections_method = new ReflectionMethod('LCFA_Admin', 'get_reconfigured_connections');
$visual_help_method = new ReflectionMethod('LCFA_Admin', 'render_connection_visual_help_strip');
$admin_codex_command_method = new ReflectionMethod('LCFA_Admin', 'build_codex_register_command');

lcfa_assert_same('connections', $default_tab_method->invoke($admin_instance, [
    'completed' => true,
]), 'completed setup should default the dashboard to Connections');
lcfa_assert_same('setup', $default_tab_method->invoke($admin_instance, [
    'completed' => false,
]), 'incomplete setup should still default the dashboard to Setup');
lcfa_assert_same('connections', $post_setup_redirect_method->invoke($admin_instance), 'setup completion should redirect to Connections');

$genesis_hero = $hero_content_method->invoke($admin_instance, 'genesis');
lcfa_assert_same('Project Brief & Build Plan', $genesis_hero['title'] ?? '', 'genesis hero should explain the real purpose of the tab');
lcfa_assert_true(strpos((string) ($genesis_hero['subtitle'] ?? ''), 'after your coding agent connection is ready') !== false, 'genesis hero subtitle should position Genesis after Connections');

$admin_codex_command = (string) $admin_codex_command_method->invoke($admin_instance, [
    'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
    'env'     => [
        'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
        'LCFA_MCP_TOKEN=test-token',
        'LCFA_WP_ROOT=/Users/commander/Studio/consultala',
    ],
]);
lcfa_assert_true(strpos($admin_codex_command, '/Applications/Codex.app/Contents/Resources/codex') !== false, 'admin Codex guide should mention the desktop app CLI path');
lcfa_assert_true(strpos($admin_codex_command, '[mcp_servers.livecanvas-forge]') !== false, 'admin Codex guide should include the config.toml fallback snippet');

ob_start();
$visual_help_method->invoke($admin_instance, $opencode_fast_path);
$visual_help_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($visual_help_markup, 'What this looks like in OpenCode') !== false, 'admin should render the visual strip title');
lcfa_assert_true(strpos($visual_help_markup, 'Check MCP: livecanvas-forge') !== false, 'admin should render the OpenCode MCP instruction');

$ready_state = $onboarding->derive_state([
    'preferred_client'            => 'opencode',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => 'ready',
    'connection_last_verified_at' => '2026-04-13 10:00:00',
    'connection_last_error'       => '',
    'connection_current_step'     => 'smoke_test',
], [
    'local_ready'  => true,
    'remote_ready' => false,
]);

lcfa_assert_same('ready', $ready_state['status'] ?? '', 'ready status should be preserved when verification metadata exists');

$ready_view = $presenter->build([
    'state' => [
        'status'           => 'ready',
        'current_step'     => 'ready',
        'last_verified_at' => '2026-04-13 21:00:00',
    ],
    'bundle' => [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('ready', $ready_view['mode'] ?? '', 'presenter should switch to ready mode after a passing smoke test');
lcfa_assert_same('Run checks', $ready_view['ready_panel']['primary_cta']['label'] ?? '', 'ready state should expose Run checks as primary action');
lcfa_assert_same('Change coding agent', $ready_view['ready_panel']['secondary_ctas'][1]['label'] ?? '', 'ready state should expose an explicit coding-agent reset action');

$reconfigured_connections = $reconfigure_connections_method->invoke($admin_instance, [
    'preferred_client'            => 'opencode',
    'connection_mode'             => 'local',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => 'ready',
    'connection_last_verified_at' => '2026-04-13 21:00:00',
    'connection_last_error'       => 'old error',
    'connection_current_step'     => 'ready',
]);

lcfa_assert_same('', $reconfigured_connections['preferred_client'] ?? '', 'reconfiguring should clear the previously selected coding agent');
lcfa_assert_same('local', $reconfigured_connections['connection_mode'] ?? '', 'reconfiguring should preserve the chosen mode');
lcfa_assert_same('/Users/commander/Studio/consultala', $reconfigured_connections['workspace_root'] ?? '', 'reconfiguring should preserve the known workspace root');
lcfa_assert_same('', $reconfigured_connections['connection_status'] ?? '', 'reconfiguring should clear the ready status');
lcfa_assert_same('', $reconfigured_connections['connection_last_verified_at'] ?? '', 'reconfiguring should clear the verification timestamp');
lcfa_assert_same('', $reconfigured_connections['connection_last_error'] ?? '', 'reconfiguring should clear the last error state');
lcfa_assert_same('choose_client', $reconfigured_connections['connection_current_step'] ?? '', 'reconfiguring should reopen the wizard from choose_client');

echo "PASS\n";
