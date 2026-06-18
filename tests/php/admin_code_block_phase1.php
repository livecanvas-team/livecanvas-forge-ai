<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function esc_html(string $value): string {
    return $value;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_attr(string $value): string {
    return $value;
}

function esc_url(string $value): string {
    return $value;
}

function esc_js(string $value): string {
    return addslashes($value);
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void {
}

function wp_create_nonce(string $action = ''): string {
    return 'nonce';
}

function selected($selected, $current, bool $display = true): string {
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [];
    }

    public function get_livecanvas_menu_slug(): ?string {
        return null;
    }

    public function find_plugin_file_by_slug(string $slug): string {
        return '';
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$environment_property = new ReflectionProperty('LCFA_Admin', 'environment');
$environment_property->setValue($admin, new LCFA_Environment());

$render_code_block = new ReflectionMethod('LCFA_Admin', 'render_code_block');
$render_bundle_details = new ReflectionMethod('LCFA_Admin', 'render_connection_bundle_details');

ob_start();
$render_code_block->invoke($admin, "echo 'hello';", [
    'language'   => 'bash',
    'label'      => 'Shell',
    'copy_label' => 'Copy shell',
]);
$code_block_markup = (string) ob_get_clean();

lcfa_assert_contains('lcfa-code-block', $code_block_markup, 'code blocks should render the lcfa-code-block wrapper');
lcfa_assert_contains('data-lcfa-code-language="bash"', $code_block_markup, 'code blocks should expose the Prism language');
lcfa_assert_contains('language-bash', $code_block_markup, 'code blocks should expose the language-* class for Prism');
lcfa_assert_contains('Copy shell', $code_block_markup, 'code blocks should render an explicit copy button label');

ob_start();
$render_bundle_details->invoke($admin, [
    'shortcut_title'      => 'Codex shortcut',
    'shortcut_command'    => "codex mcp add livecanvas-forge \\\n  --env LCFA_REST_BASE='http://localhost:8887/wp-json/lcfa/v1/' \\\n  -- node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio",
    'command_string'      => "node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio",
    'environment'         => ['LCFA_REST_BASE' => 'http://localhost:8887/wp-json/lcfa/v1/'],
    'smoke_test_command'  => 'codex mcp list',
    'agent_start_prompt'  => "Use the LiveCanvas AI Bridge MCP connection for this WordPress project.\nFirst call get_connection_handoff with {\"limit\":5}.\nThen call get_agent_handoff_package only if you need the full runbook.",
    'workspace_files'     => [['path' => '/Users/commander/Studio/consultala/livecanvas-forge.codex.sh']],
]);
$bundle_markup = (string) ob_get_clean();

lcfa_assert_contains('Copy shortcut', $bundle_markup, 'Codex shortcut window should expose a dedicated copy shortcut action');
lcfa_assert_contains('language-bash', $bundle_markup, 'bundle command blocks should default to shell highlighting');
lcfa_assert_contains('lcfa-agent-guide__bundle-layout', $bundle_markup, 'bundle details should render a dedicated bundle layout wrapper');
lcfa_assert_contains('lcfa-agent-guide__window lcfa-agent-guide__window--files', $bundle_markup, 'bundle details should render Files in a dedicated full-width row');
lcfa_assert_contains('lcfa-agent-guide__panel-grid lcfa-agent-guide__panel-grid--bundle', $bundle_markup, 'bundle details should render the remaining windows in a responsive bundle grid');
lcfa_assert_contains('First agent prompt', $bundle_markup, 'bundle details should render the first handoff prompt window');
lcfa_assert_contains('Copy prompt', $bundle_markup, 'first handoff prompt should expose a dedicated copy action');
lcfa_assert_contains('get_connection_handoff', $bundle_markup, 'first handoff prompt should tell the agent to fetch the lightweight connection handoff first');
lcfa_assert_contains('get_agent_handoff_package', $bundle_markup, 'first handoff prompt should keep the full handoff package as a follow-up option');

echo "PASS\n";
