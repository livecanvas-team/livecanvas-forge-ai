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

function esc_html__(string $text, string $domain = ''): string {
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
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr__(string $value, string $domain = 'default'): string {
    return $value;
}

function esc_url(string $value): string {
    return $value;
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
    $markup = '<input type="hidden" name="' . $name . '" value="test-nonce">';

    if ($display) {
        echo $markup;
    }

    return $markup;
}

function selected($selected, $current = true, bool $display = true): string {
    $result = $selected === $current ? ' selected="selected"' : '';

    if ($display) {
        echo $result;
    }

    return $result;
}

function checked($checked, $current = true, bool $display = true): string {
    $result = $checked === $current ? ' checked="checked"' : '';

    if ($display) {
        echo $result;
    }

    return $result;
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

$remote_codex_bundle = $builder->build([
    'client'         => 'codex',
    'mode'           => 'remote',
    'workspace_root' => '',
    'common'         => [
        'connection_strategy' => 'remote-mcp-adapter',
        'mcp_adapter_url'     => 'https://remote.example/wp-json/livecanvas-forge-ai/mcp',
        'remote_site_url'     => 'https://remote.example/',
    ],
    'client_payload' => [
        'command' => 'npx -y @automattic/mcp-wordpress-remote@latest',
        'env'     => [
            'WP_API_URL=https://remote.example/wp-json/livecanvas-forge-ai/mcp',
            'WP_API_USERNAME=admin',
            'WP_API_PASSWORD=abcd efgh ijkl mnop',
            'LOG_FILE=/tmp/livecanvas-forge-codex-remote.log',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('remote-mcp-adapter', $remote_codex_bundle['connection_strategy'] ?? '', 'remote Codex bundle should declare the MCP Adapter strategy');
lcfa_assert_same('https://remote.example/wp-json/livecanvas-forge-ai/mcp', $remote_codex_bundle['mcp_adapter_url'] ?? '', 'remote Codex bundle should expose the MCP Adapter URL');
lcfa_assert_true(empty($remote_codex_bundle['workspace_files']), 'remote Codex bundle should not propose local workspace writes');
lcfa_assert_false(isset($remote_codex_bundle['environment']['LCFA_WP_ROOT']), 'remote Codex bundle should strip local filesystem environment');
lcfa_assert_same('@automattic/mcp-wordpress-remote@latest', $remote_codex_bundle['command'][2] ?? '', 'remote Codex bundle should use the WordPress MCP remote proxy package');
lcfa_assert_true(strpos((string) ($remote_codex_bundle['shortcut_command'] ?? ''), '--env WP_API_URL=') !== false, 'remote Codex shortcut should register WP_API_URL with Codex');
lcfa_assert_true(strpos((string) ($remote_codex_bundle['shortcut_command'] ?? ''), '--env WP_API_PASSWORD=') !== false, 'remote Codex shortcut should register the Application Password with Codex');
lcfa_assert_true(strpos((string) ($remote_codex_bundle['shortcut_command'] ?? ''), 'remote WordPress MCP Adapter') !== false, 'remote Codex shortcut should explain the remote MCP Adapter target');
lcfa_assert_true(strpos((string) ($remote_codex_bundle['smoke_test_command'] ?? ''), 'codex mcp get livecanvas-forge') !== false, 'remote Codex smoke command should verify the Codex MCP registration');
lcfa_assert_false(strpos((string) ($remote_codex_bundle['smoke_test_command'] ?? ''), '--tool') !== false, 'remote Codex smoke command should not append local bridge CLI flags to the proxy package');

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

$claude_target_state = $onboarding->derive_state([
    'preferred_client'            => 'claude',
    'claude_connection_target'    => '',
    'connection_mode'             => '',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => '',
], [
    'local_ready'  => true,
    'remote_ready' => false,
]);

lcfa_assert_same('choose_claude_target', $claude_target_state['current_step'] ?? '', 'Claude should stop on choose_claude_target before connection mode is selected');

$codex_next_step = $onboarding->next_step('choose_client', [
    'preferred_client' => 'codex',
    'connection_mode'  => 'local',
]);

lcfa_assert_same('choose_mode', $codex_next_step, 'Codex should move from choose_client to choose_mode, never to the Claude target step');

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
$claude_target_view = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'choose_claude_target',
    ],
    'bundle' => [
        'client'         => 'claude',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('choose_claude_target', $claude_target_view['steps'][1]['key'] ?? '', 'Claude wizard should insert choose_claude_target after choose_client');
lcfa_assert_same('Choose Claude connection target', $claude_target_view['steps'][1]['title'] ?? '', 'Claude wizard should name the Claude target step explicitly');
lcfa_assert_same('active', $claude_target_view['steps'][1]['state'] ?? '', 'Claude target step should become active when the target is missing');

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

$codex_view = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'choose_client',
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

lcfa_assert_same('choose_mode', $codex_view['steps'][1]['key'] ?? '', 'Codex wizard should show choose_mode immediately after choose_client');
lcfa_assert_true(
    !in_array('choose_claude_target', array_column((array) ($codex_view['steps'] ?? []), 'key'), true),
    'Codex wizard should not render the Claude target step at all'
);

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

$codex_remote_bundle_view = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'generate_bundle',
    ],
    'bundle' => [
        'client'              => 'codex',
        'mode'                => 'remote',
        'connection_strategy' => 'remote-mcp-adapter',
        'mcp_adapter_url'     => 'https://remote.example/wp-json/livecanvas-forge-ai/mcp',
        'workspace_root'      => '',
        'command_string'      => "'npx' '-y' '@automattic/mcp-wordpress-remote@latest'",
        'copy_command_string' => "codex mcp add livecanvas-forge \\\n  --env WP_API_URL='https://remote.example/wp-json/livecanvas-forge-ai/mcp' \\\n  --env WP_API_USERNAME='admin' \\\n  --env WP_API_PASSWORD='abcd efgh ijkl mnop' \\\n  -- 'npx' '-y' '@automattic/mcp-wordpress-remote@latest'",
        'shortcut_title'      => 'Codex shortcut',
        'shortcut_command'    => "codex mcp add livecanvas-forge \\\n  --env WP_API_URL='https://remote.example/wp-json/livecanvas-forge-ai/mcp' \\\n  --env WP_API_USERNAME='admin' \\\n  --env WP_API_PASSWORD='abcd efgh ijkl mnop' \\\n  -- 'npx' '-y' '@automattic/mcp-wordpress-remote@latest'",
        'workspace_files'     => [],
        'download_files'      => [['name' => 'livecanvas-forge.codex.sh', 'content' => '#!/usr/bin/env bash']],
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'missing',
        'path'      => '',
    ],
]);

lcfa_assert_same('Copy Codex shortcut', $codex_remote_bundle_view['active_panel']['primary_cta']['label'] ?? '', 'Codex remote flow should elevate the remote registration shortcut to the primary action');
lcfa_assert_true(strpos((string) ($codex_remote_bundle_view['active_panel']['description'] ?? ''), 'remote shortcut') !== false, 'Codex remote flow should explain that the shortcut is remote-safe');
lcfa_assert_true(strpos((string) ($codex_remote_bundle_view['visual_help']['items'][0]['caption'] ?? ''), 'remote proxy') !== false, 'Codex remote visual help should mention the WordPress MCP Adapter remote proxy');

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
$internal_tabs_method = new ReflectionMethod('LCFA_Admin', 'render_internal_tabs');
$reconfigure_connections_method = new ReflectionMethod('LCFA_Admin', 'get_reconfigured_connections');
$visual_help_method = new ReflectionMethod('LCFA_Admin', 'render_connection_visual_help_strip');
$admin_codex_command_method = new ReflectionMethod('LCFA_Admin', 'build_codex_register_command');
$stepper_method = new ReflectionMethod('LCFA_Admin', 'render_connection_stepper');
$connection_wizard_method = new ReflectionMethod('LCFA_Admin', 'render_connection_wizard');
$active_step_panel_method = new ReflectionMethod('LCFA_Admin', 'render_connection_active_step_panel');
$connection_hero_method = new ReflectionMethod('LCFA_Admin', 'render_connection_onboarding_hero');
$connection_ready_card_method = new ReflectionMethod('LCFA_Admin', 'render_connection_ready_card');
$framework_change_decision_method = new ReflectionMethod('LCFA_Admin', 'render_connection_framework_change_decision_card');
$remote_codex_payload_method = new ReflectionMethod('LCFA_Admin', 'build_remote_codex_mcp_adapter_payload');
$secondary_panels_method = new ReflectionMethod('LCFA_Admin', 'render_connections_secondary_panels');
$ability_diagnostics_card_method = new ReflectionMethod('LCFA_Admin', 'render_ability_diagnostics_card');

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

$studio_hero = $hero_content_method->invoke($admin_instance, 'studio');
lcfa_assert_same('Forge Studio', $studio_hero['title'] ?? '', 'studio hero should expose the operational Studio tab');
lcfa_assert_true(strpos((string) ($studio_hero['subtitle'] ?? ''), 'MCP exposure') !== false, 'studio hero subtitle should mention MCP exposure');

ob_start();
$internal_tabs_method->invoke($admin_instance, 'studio', ['completed' => true]);
$internal_tabs_markup = (string) ob_get_clean();
lcfa_assert_true(strpos($internal_tabs_markup, 'tab=studio') !== false, 'internal tabs should include the Forge Studio tab');
lcfa_assert_true(strpos($internal_tabs_markup, 'Forge Studio') !== false, 'internal tabs should label the Forge Studio tab');

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

$admin_remote_codex_payload = $remote_codex_payload_method->invoke($admin_instance, [
    'remote_site_url' => 'https://remote.example',
    'remote_username' => 'admin',
    'remote_application_password' => 'abcd efgh ijkl mnop',
], [
    'mcp_adapter' => [
        'available' => true,
        'custom_server' => [
            'url' => 'https://remote.example/wp-json/livecanvas-forge-ai/mcp',
        ],
    ],
]);

lcfa_assert_same('npx -y @automattic/mcp-wordpress-remote@latest', $admin_remote_codex_payload['client_payload']['command'] ?? '', 'admin remote Codex payload should use the WordPress MCP remote proxy command');
lcfa_assert_true(in_array('WP_API_URL=https://remote.example/wp-json/livecanvas-forge-ai/mcp', $admin_remote_codex_payload['client_payload']['env'] ?? [], true), 'admin remote Codex payload should point WP_API_URL at the Forge MCP Adapter route');
lcfa_assert_true(in_array('WP_API_PASSWORD=abcd efgh ijkl mnop', $admin_remote_codex_payload['client_payload']['env'] ?? [], true), 'admin remote Codex payload should preserve Application Password spacing');
lcfa_assert_same('remote-mcp-adapter', $admin_remote_codex_payload['common']['connection_strategy'] ?? '', 'admin remote Codex payload should mark the MCP Adapter strategy');

ob_start();
$visual_help_method->invoke($admin_instance, $opencode_fast_path);
$visual_help_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($visual_help_markup, 'What this looks like in OpenCode') !== false, 'admin should render the visual strip title');
lcfa_assert_true(strpos($visual_help_markup, 'Check MCP: livecanvas-forge') !== false, 'admin should render the OpenCode MCP instruction');

ob_start();
$stepper_method->invoke($admin_instance, $opencode_fast_path['steps']);
$stepper_markup = (string) ob_get_clean();

lcfa_assert_false(strpos($stepper_markup, 'lcfa-wizard__step-helper') !== false, 'wizard stepper should no longer render helper spans');
lcfa_assert_false(strpos($stepper_markup, 'Pick the client') !== false, 'wizard stepper should no longer render helper copy inside the step cards');
lcfa_assert_false(strpos($stepper_markup, 'Current') !== false, 'wizard stepper should stop rendering the Current badge');
lcfa_assert_false(strpos($stepper_markup, 'Done') !== false, 'wizard stepper should stop rendering the Done badge');
lcfa_assert_false(strpos($stepper_markup, '>Active<') !== false, 'wizard stepper should no longer label the active step as Active');

ob_start();
$connection_hero_method->invoke(
    $admin_instance,
    [
        'client' => 'claude',
    ],
    [
        'status'  => 'not_connected',
        'message' => 'Confirm the connection details and generate the client bundle.',
    ],
    [
        'local_bridge' => [
            'deferred'  => true,
            'available' => false,
        ],
    ],
    [
        'detected_framework' => 'picostrap',
    ],
    'local'
);
$connection_hero_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($connection_hero_markup, 'AI agent: Claude') !== false, 'connections hero should foreground only the selected AI agent');
lcfa_assert_true(strpos($connection_hero_markup, 'Not connected') !== false, 'connections hero should foreground the connection status');
lcfa_assert_false(strpos($connection_hero_markup, 'Mode:') !== false, 'connections hero should stop repeating the mode chip');
lcfa_assert_false(strpos($connection_hero_markup, 'Framework:') !== false, 'connections hero should stop repeating the framework chip');
lcfa_assert_false(strpos($connection_hero_markup, 'Local MCP bridge status') !== false, 'connections hero should stop repeating local bridge loading copy');

ob_start();
$secondary_panels_method->invoke($admin_instance);
$secondary_panels_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($secondary_panels_markup, 'data-lcfa-connections-panel="diagnostics"') !== false, 'connections tab should reserve an async ability diagnostics panel');
lcfa_assert_true(strpos($secondary_panels_markup, 'Ability diagnostics') !== false, 'connections diagnostics placeholder should be labelled clearly');

ob_start();
$ability_diagnostics_card_method->invoke($admin_instance, [
    'ability_diagnostics' => [
        'total' => 25,
        'mcp_public_total' => 17,
        'mcp_public_preview' => [
            'livecanvas-forge-ai/preview-page-upsert',
            'livecanvas-forge-ai/preview-global-shell',
        ],
        'mcp_public_write' => [],
        'has_mcp_public_write' => false,
        'mcp_write_opt_in_enabled' => false,
        'items' => [],
    ],
    'mcp_adapter' => [
        'available' => true,
        'custom_server' => [
            'url' => 'https://example.test/wp-json/livecanvas-forge-ai/mcp',
        ],
    ],
    'ai_client' => [
        'available' => true,
        'text_generation_supported' => true,
        'connectors' => [
            'available' => true,
            'count' => 2,
            'text_generation_count' => 1,
        ],
    ],
]);
$ability_diagnostics_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($ability_diagnostics_markup, 'Abilities: 25') !== false, 'ability diagnostics card should show total ability count');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'MCP public: 17') !== false, 'ability diagnostics card should show MCP-public ability count');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'No public write abilities') !== false, 'ability diagnostics card should explicitly report write abilities are private');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'Write opt-in disabled') !== false, 'ability diagnostics card should show the default write opt-in state');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'AI text ready') !== false, 'ability diagnostics card should show AI Client text-generation readiness');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'Connectors: 2') !== false, 'ability diagnostics card should show connector count');
lcfa_assert_true(strpos($ability_diagnostics_markup, 'livecanvas-forge-ai/preview-page-upsert') !== false, 'ability diagnostics card should list public preview abilities');

ob_start();
$connection_wizard_method->invoke(
    $admin_instance,
    $opencode_fast_path,
    [
        'client'         => 'claude',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    [
        'preferred_client'         => 'claude',
        'claude_connection_target' => 'desktop_app',
        'connection_mode'          => 'local',
        'workspace_root'           => '/Users/commander/Studio/consultala',
    ],
    'claude',
    'local',
    [],
    [],
    [],
    [],
    [
        'current_step' => 'smoke_test',
    ],
    [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$connection_wizard_markup = (string) ob_get_clean();

lcfa_assert_false(strpos($connection_wizard_markup, 'lcfa-wizard__alert') !== false, 'wizard should stop rendering the redundant What to do now banner');
lcfa_assert_false(strpos($connection_wizard_markup, 'What to do now') !== false, 'wizard should not repeat the step callout above the blocking panel');

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

ob_start();
$connection_ready_card_method->invoke(
    $admin_instance,
    $ready_view,
    [
        'client'         => 'codex',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
        'shortcut_title' => 'Codex shortcut',
        'shortcut_command' => 'codex mcp add livecanvas-forge -- node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
        'command_string' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
        'smoke_test_command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --tool get_snapshot --output pretty',
        'environment'    => [
            'LCFA_REST_BASE' => 'http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN' => 'test-token',
        ],
    ],
    [
        'connection_last_verified_at' => '2026-04-13 21:00:00',
    ],
    [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$ready_card_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($ready_card_markup, 'Connection ready') !== false, 'ready card should render its guidance alert without fatals');
lcfa_assert_true(strpos($ready_card_markup, 'The smoke test has already passed.') !== false, 'ready card alert should explain that verification is already complete');
lcfa_assert_false(strpos($ready_card_markup, 'Uncaught') !== false, 'ready card should not leak PHP fatal output into the admin page');
lcfa_assert_true(strpos($ready_card_markup, 'lcfa-code-explanation') !== false, 'ready card generated bundle should explain each technical command block');
lcfa_assert_true(strpos($ready_card_markup, 'data-lcfa-read-more') !== false, 'long generated bundle explanations should expose a read-more control');
lcfa_assert_true(strpos($ready_card_markup, 'Registers livecanvas-forge inside Codex') !== false, 'Codex shortcut explanation should describe why the command is needed');
lcfa_assert_true(strpos($ready_card_markup, 'raw MCP server command') !== false, 'server command explanation should describe its diagnostic purpose');
lcfa_assert_true(strpos($ready_card_markup, 'REST endpoint, token, site URL, and local project root') !== false, 'environment variables explanation should describe the values passed to the MCP server');
lcfa_assert_true(strpos($ready_card_markup, 'get_snapshot') !== false && strpos($ready_card_markup, 'confirms the MCP bridge') !== false, 'smoke test explanation should describe the snapshot verification command');

ob_start();
$framework_change_decision_method->invoke(
    $admin_instance,
    [
        'preferred_client'            => 'codex',
        'connection_mode'             => 'local',
        'workspace_root'              => '/Users/commander/Studio/consultala',
        'connection_status'           => 'ready',
        'connection_last_verified_at' => '2026-04-13 21:00:00',
    ],
    'picostrap',
    'picowind'
);
$framework_change_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($framework_change_markup, 'Reuse the existing Codex connection?') !== false, 'framework-change card should explain that a verified connection already exists');
lcfa_assert_true(strpos($framework_change_markup, 'Picostrap / Bootstrap') !== false, 'framework-change card should name the previous framework');
lcfa_assert_true(strpos($framework_change_markup, 'Picowind / Tailwind + WindPress') !== false, 'framework-change card should name the new framework');
lcfa_assert_true(strpos($framework_change_markup, 'Keep existing connection') !== false, 'framework-change card should offer to keep the current verified connection');
lcfa_assert_true(strpos($framework_change_markup, 'Generate new connection') !== false, 'framework-change card should offer to regenerate the connection for the new stack');

$admin_css = (string) file_get_contents(LCFA_DIR . 'assets/admin.css');
lcfa_assert_true(strpos($admin_css, '.lcfa-admin .lcfa-chip.is-positive') !== false, 'admin CSS should define positive chip states');
lcfa_assert_true(strpos($admin_css, 'rgba(34, 197, 94') !== false || strpos($admin_css, '#22c55e') !== false, 'positive ready chips should use green styling, not the default cyan state');

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

ob_start();
$active_step_panel_method->invoke(
    $admin_instance,
    [
        'title'       => 'Choose local or remote',
        'description' => 'Pick how this client should connect.',
    ],
    'choose_mode',
    [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    [
        'preferred_client'         => 'opencode',
        'claude_connection_target' => '',
        'connection_mode'          => 'local',
        'workspace_root'           => '/Users/commander/Studio/consultala',
    ],
    'opencode',
    'local',
    [],
    [],
    [],
    [],
    [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$active_step_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($active_step_markup, 'lcfa_reconfigure_connection') !== false, 'non-ready wizard steps should expose a reconfigure action');
lcfa_assert_true(strpos($active_step_markup, 'Change coding agent') !== false, 'non-ready wizard steps should let the user change the coding agent without finishing the flow');

ob_start();
$active_step_panel_method->invoke(
    $admin_instance,
    [
        'title'       => 'Are these connection details correct?',
        'description' => 'Review the generated connection details before you create the client bundle.',
        'primary_cta' => [
            'label' => 'Confirm details',
        ],
    ],
    'confirm_details',
    [
        'client' => 'codex',
        'mode'   => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
        'environment' => [
            'LCFA_REST_BASE' => 'http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN' => 'test-token',
        ],
    ],
    [
        'preferred_client'         => 'codex',
        'claude_connection_target' => '',
        'connection_mode'          => 'local',
        'workspace_root'           => '/Users/commander/Studio/consultala',
    ],
    'codex',
    'local',
    [],
    [],
    [],
    [],
    [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$confirm_details_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($confirm_details_markup, 'Confirm details') !== false, 'confirm details step should expose the primary CTA directly in the panel');
lcfa_assert_false(strpos($confirm_details_markup, 'Thread tools') !== false, 'confirm details step should not hide the primary CTA inside a thread tools disclosure');

ob_start();
$active_step_panel_method->invoke(
    $admin_instance,
    [
        'title'       => 'Ready to verify the connection?',
        'description' => 'Run the smoke test after the client bundle is in place.',
    ],
    'smoke_test',
    [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    [
        'preferred_client'         => 'opencode',
        'claude_connection_target' => '',
        'connection_mode'          => 'local',
        'workspace_root'           => '/Users/commander/Studio/consultala',
    ],
    'opencode',
    'local',
    [],
    [],
    [],
    [],
    [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$smoke_step_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($smoke_step_markup, 'lcfa-wizard__panel--blocking') !== false, 'smoke-test panel should render a dedicated blocking style hook');

ob_start();
$active_step_panel_method->invoke(
    $admin_instance,
    [
        'title'       => 'Ready to verify Codex?',
        'description' => 'Run the smoke test after you have executed the Codex shortcut and verified that Codex can see livecanvas-forge.',
    ],
    'smoke_test',
    [
        'client'          => 'codex',
        'mode'            => 'local',
        'workspace_root'  => '/Users/commander/Studio/consultala',
        'workspace_files' => [
            [
                'path' => '/Users/commander/Studio/consultala/livecanvas-forge.codex.sh',
            ],
        ],
    ],
    [
        'preferred_client'         => 'codex',
        'claude_connection_target' => '',
        'connection_mode'          => 'local',
        'workspace_root'           => '/Users/commander/Studio/consultala',
    ],
    'codex',
    'local',
    [],
    [],
    [],
    [],
    [
        'available' => true,
        'reason'    => 'ready',
        'path'      => '/Users/commander/Studio/consultala',
    ]
);
$codex_smoke_step_markup = (string) ob_get_clean();

lcfa_assert_true(strpos($codex_smoke_step_markup, 'livecanvas-forge.codex.sh') !== false, 'Codex smoke panel should tell the user exactly which helper script to run');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'codex mcp list') !== false, 'Codex smoke panel should tell the user how to verify the MCP registration');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'livecanvas-forge') !== false, 'Codex smoke panel should tell the user which MCP server name must appear');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'data-lcfa-copy-text="sh &quot;/Users/commander/Studio/consultala/livecanvas-forge.codex.sh&quot;"') !== false, 'Codex helper command should expose a copy button');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'data-lcfa-copy-text="codex mcp list"') !== false, 'Codex mcp list command should expose a copy button');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'data-lcfa-copy-text="/Applications/Codex.app/Contents/Resources/codex mcp list"') !== false, 'Codex desktop mcp list command should expose a copy button');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'lcfa-codex-smoke-alert') !== false, 'Codex reopen warning should render as a prominent alert');
lcfa_assert_true(strpos($codex_smoke_step_markup, 'If the output does not show livecanvas-forge, the connection is not ready yet.') !== false, 'Codex MCP output requirement should combine the expected and failure state in one step');
lcfa_assert_false(strpos($codex_smoke_step_markup, 'If it does not show livecanvas-forge, the connection is not ready yet.') !== false, 'Codex MCP output warning should not be a separate repeated step');

echo "PASS\n";
