<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');

$GLOBALS['lcfa_test_get_plugins_calls'] = 0;
$GLOBALS['lcfa_test_wp_get_themes_calls'] = 0;
$GLOBALS['lcfa_test_wp_query_calls'] = 0;
$GLOBALS['lcfa_test_remote_request_calls'] = 0;
$GLOBALS['lcfa_test_transients'] = [];

function __(string $text, string $domain = ''): string {
    return $text;
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

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function get_locale(): string {
    return 'en_US';
}

function wp_timezone_string(): string {
    return 'Europe/Rome';
}

function wp_strip_all_tags(string $value): string {
    return strip_tags($value);
}

function get_plugins(): array {
    $GLOBALS['lcfa_test_get_plugins_calls']++;

    return [
        'livecanvas/livecanvas.php' => ['TextDomain' => 'livecanvas'],
        'windpress/windpress.php' => ['TextDomain' => 'windpress'],
    ];
}

function is_plugin_active(string $plugin_file): bool {
    return in_array($plugin_file, ['livecanvas/livecanvas.php', 'windpress/windpress.php'], true);
}

function wp_get_theme(?string $stylesheet = null) {
    return new LCFA_Test_Theme(
        $stylesheet ?: 'picostrap5-child-base',
        'Picostrap Child',
        'picostrap5',
        $stylesheet ? '1.0.0' : '1.0.0',
        $stylesheet === null
    );
}

function wp_get_themes(): array {
    $GLOBALS['lcfa_test_wp_get_themes_calls']++;

    return [
        'picostrap5-child-base' => new LCFA_Test_Theme('picostrap5-child-base', 'Picostrap Child', 'picostrap5', '1.0.0', true),
        'picowind' => new LCFA_Test_Theme('picowind', 'Picowind', 'picowind', '1.0.0', false),
    ];
}

function lc_get_framework_slug(): string {
    return 'bootstrap';
}

function get_posts(array $args): array {
    return [];
}

function get_post($post_id = null) {
    return null;
}

function get_post_field(string $field, int $post_id, string $context = 'display'): string {
    return '';
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    return '';
}

function get_the_title(int $post_id = 0): string {
    return '';
}

function get_edit_post_link(int $post_id = 0, string $context = 'display'): string {
    return '';
}

function get_permalink(int $post_id = 0): string {
    return '';
}

function get_post_types(array $args = [], string $output = 'names'): array {
    return [];
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function get_transient(string $key) {
    return $GLOBALS['lcfa_test_transients'][$key] ?? false;
}

function set_transient(string $key, $value, int $expiration = 0): bool {
    $GLOBALS['lcfa_test_transients'][$key] = $value;
    return true;
}

function is_wp_error($thing): bool {
    return $thing instanceof WP_Error;
}

function wp_remote_request(string $url, array $options = []) {
    $GLOBALS['lcfa_test_remote_request_calls']++;

    return [
        'response' => ['code' => 200],
        'body' => json_encode([
            'snapshot' => [
                'current_theme_name' => 'Remote Theme',
                'detected_framework' => 'picostrap',
                'livecanvas_active' => true,
                'windpress_active' => false,
                'site_mode' => 'remote',
            ],
            'mcp' => [
                'enabled' => true,
                'rest_base' => 'https://example.com/wp-json/lcfa/v1/',
                'token' => 'remote-token',
                'filesystem_mode' => 'remote-rest-primary',
            ],
        ]),
    ];
}

function wp_remote_retrieve_response_code(array $response): int {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body(array $response): string {
    return (string) ($response['body'] ?? '');
}

class WP_Error {
    private string $message;

    public function __construct(string $code = '', string $message = '') {
        $this->message = $message;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

class WP_Query {
    public int $found_posts = 7;

    public function __construct(array $args = []) {
        $GLOBALS['lcfa_test_wp_query_calls']++;
    }
}

final class LCFA_Test_Theme {
    private string $stylesheet;
    private string $name;
    private string $template;
    private string $version;
    private bool $child;

    public function __construct(string $stylesheet, string $name, string $template, string $version, bool $child) {
        $this->stylesheet = $stylesheet;
        $this->name = $name;
        $this->template = $template;
        $this->version = $version;
        $this->child = $child;
    }

    public function get(string $key): string {
        switch ($key) {
            case 'Name':
                return $this->name;
            case 'Version':
                return $this->version;
            case 'TextDomain':
                return $this->stylesheet;
            default:
                return '';
        }
    }

    public function get_stylesheet(): string {
        return $this->stylesheet;
    }

    public function get_template(): string {
        return $this->template;
    }

    public function parent() {
        return $this->child ? new self('picostrap5', 'Picostrap', 'picostrap5', '1.0.0', false) : null;
    }
}

final class LCFA_Settings {
    public static function get_connections(): array {
        return [
            'remote_site_url' => 'https://example.com',
            'remote_username' => 'demo',
            'remote_application_password' => 'secret',
        ];
    }
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

require dirname(__DIR__, 2) . '/includes/class-lcfa-environment.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-inventory.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-remote-client.php';

$environment = new LCFA_Environment();
$environment->get_snapshot();
$environment->get_snapshot();

lcfa_assert_same(1, $GLOBALS['lcfa_test_get_plugins_calls'], 'environment snapshot should scan installed plugins only once per request');
lcfa_assert_same(1, $GLOBALS['lcfa_test_wp_get_themes_calls'], 'environment snapshot should scan themes only once per request');

$inventory = new LCFA_Inventory($environment);
$inventory->get_summary();
$inventory->get_summary();

lcfa_assert_same(6, $GLOBALS['lcfa_test_wp_query_calls'], 'inventory summary should execute its count queries only once per request');

$remote_client_first = new LCFA_Remote_Client();
$remote_client_first->get_status();
$remote_client_second = new LCFA_Remote_Client();
$remote_client_second->get_status();

lcfa_assert_same(1, $GLOBALS['lcfa_test_remote_request_calls'], 'remote status should be cached briefly across client instances');
lcfa_assert_true(!empty($GLOBALS['lcfa_test_transients']), 'remote status caching should persist the computed status');

echo "PASS\n";
