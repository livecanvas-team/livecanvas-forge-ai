<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', '0.1.0-test');
define('WP_CONTENT_DIR', '/Users/commander/Studio/consultala/wp-content');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_attr__(string $text, string $domain = ''): string {
    return $text;
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

function esc_js(string $value): string {
    return addslashes($value);
}

function esc_textarea(string $value): string {
    return $value;
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

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function home_url(string $path = '/'): string {
    return 'http://localhost:8887' . ($path === '/' ? '/' : '/' . ltrim($path, '/'));
}

function trailingslashit(string $value): string {
    return rtrim($value, "/\\") . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, "/\\");
}

function wp_normalize_path(string $value): string {
    return str_replace('\\', '/', $value);
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

function selected($selected, $current, bool $display = true): string {
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void {
}

function wp_create_nonce(string $action = ''): string {
    return 'nonce';
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
}

function get_option(string $key, $default = false) {
    return $default;
}

function get_bloginfo(string $show = '', string $filter = 'raw'): string {
    if ($show === 'name') {
        return 'Test Site';
    }

    return '';
}

function get_locale(): string {
    return 'en_US';
}

function wp_timezone_string(): string {
    return 'Europe/Rome';
}

function current_user_can(string $capability): bool {
    return true;
}

function absint($value): int {
    return abs((int) $value);
}

function wp_safe_redirect(string $url): void {
}

function get_date_from_gmt(string $value, string $format): string {
    return $value;
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
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

final class LCFA_Settings {
    public static function get(): array {
        return [
            'ai_tool' => 'codex',
        ];
    }

    public static function get_connections(): array {
        return [
            'preferred_client'            => 'codex',
            'connection_mode'             => 'local',
            'workspace_root'              => '/Users/commander/Studio/consultala',
            'mcp_token'                   => 'test-token',
            'mcp_server_command'          => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
            'transport'                   => 'rest',
            'remote_site_url'             => 'https://example.com',
            'remote_username'             => 'demo',
            'remote_application_password' => 'secret',
            'connection_status'           => '',
            'connection_last_verified_at' => '',
            'connection_last_error'       => '',
            'connection_current_step'     => 'generate_bundle',
        ];
    }

    public static function consume_connection_test_result(): ?array {
        return null;
    }

    public static function get_mcp_endpoint(): string {
        return 'ws://127.0.0.1:7681';
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'site_mode'                => 'local',
            'detected_framework'       => 'picowind',
            'framework_slug'           => 'daisyui-5',
            'current_theme_name'       => 'Picowind Child',
            'current_theme_stylesheet' => 'picowind-child',
            'current_theme_template'   => 'picowind',
            'windpress_active'         => true,
            'livecanvas_active'        => true,
            'tangible_available'       => false,
            'acf_active'               => false,
            'woocommerce_active'       => false,
        ];
    }

    public function get_livecanvas_menu_slug(): ?string {
        return null;
    }
}

final class LCFA_Installer {}
final class LCFA_Inventory {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Connection_Tester {}
final class LCFA_Command_Deck {}
final class LCFA_Prompt_Suggester {}
final class LCFA_Genesis_Planner {}

final class LCFA_Remote_Client {
    public int $status_calls = 0;

    public function get_status(): array {
        $this->status_calls++;

        return [
            'configured' => true,
            'available'  => true,
            'endpoint'   => 'https://example.com/wp-json/lcfa/v1/',
            'message'    => 'Remote companion reachable.',
            'mcp'        => [
                'rest_base' => 'https://example.com/wp-json/lcfa/v1/',
                'token'     => 'remote-token',
            ],
        ];
    }
}

final class LCFA_Context_Builder {
    public int $mcp_status_calls = 0;
    public int $bootstrap_calls = 0;

    public function get_mcp_status(): array {
        $this->mcp_status_calls++;

        return [
            'endpoint' => 'ws://127.0.0.1:7681',
            'local_bridge' => [
                'available' => true,
            ],
        ];
    }

    public function get_bootstrap_payload(): array {
        $this->bootstrap_calls++;

        return [
            'common' => [
                'site_url' => 'http://localhost:8887/',
                'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
                'mcp_endpoint' => 'ws://127.0.0.1:7681',
                'mcp_token' => 'test-token',
                'wp_root' => '/Users/commander/Studio/consultala',
                'framework' => 'picowind',
                'theme' => 'picowind-child',
                'filesystem_mode' => 'local-theme-access',
            ],
            'clients' => [
                'codex' => [
                    'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
                    'env' => [
                        'LCFA_SITE_URL=http://localhost:8887/',
                        'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
                        'LCFA_MCP_ENDPOINT=ws://127.0.0.1:7681',
                        'LCFA_MCP_TOKEN=test-token',
                        'LCFA_WP_ROOT=/Users/commander/Studio/consultala',
                    ],
                ],
            ],
        ];
    }
}

require LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';
require LCFA_DIR . 'includes/class-lcfa-connection-wizard-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-workspace-access.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();
$remote_client = new LCFA_Remote_Client();
$context_builder = new LCFA_Context_Builder();

(new ReflectionProperty('LCFA_Admin', 'environment'))->setValue($admin, new LCFA_Environment());
(new ReflectionProperty('LCFA_Admin', 'remote_client'))->setValue($admin, $remote_client);
(new ReflectionProperty('LCFA_Admin', 'context_builder'))->setValue($admin, $context_builder);
(new ReflectionProperty('LCFA_Admin', 'connection_onboarding'))->setValue($admin, new LCFA_Connection_Onboarding(new LCFA_Connection_Bundle_Builder()));
(new ReflectionProperty('LCFA_Admin', 'connection_wizard_presenter'))->setValue($admin, new LCFA_Connection_Wizard_Presenter());

$render_connections_tab = new ReflectionMethod('LCFA_Admin', 'render_connections_tab');

$settings = LCFA_Settings::get();
$snapshot = (new LCFA_Environment())->get_snapshot();

ob_start();
$render_connections_tab->invoke($admin, $settings, $snapshot);
$output = (string) ob_get_clean();

lcfa_assert_contains('data-lcfa-connections-secondary-root', $output, 'connections tab should render an async secondary root');
lcfa_assert_contains('data-lcfa-connections-panel="remote"', $output, 'connections tab should render a remote placeholder');
lcfa_assert_contains('data-lcfa-connections-panel="advanced"', $output, 'connections tab should render an advanced settings placeholder');
lcfa_assert_contains('lcfa-codex-fast-path', $output, 'connections tab should render Codex fast path as the primary default panel');
lcfa_assert_contains('Connect Codex', $output, 'connections tab should show the Codex one-click entry point by default');
lcfa_assert_contains('Other clients', $output, 'connections tab should keep non-Codex clients behind a secondary panel');
lcfa_assert_same(0, $remote_client->status_calls, 'initial connections render should not call remote status eagerly');
lcfa_assert_same(0, $context_builder->mcp_status_calls, 'initial connections render should not call mcp status eagerly for secondary panels');
lcfa_assert_same(0, $context_builder->bootstrap_calls, 'initial connections render should not build bootstrap payload eagerly');

echo "PASS\n";
