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

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void {
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
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

function current_user_can(string $capability): bool {
    return $capability === 'manage_options';
}

function check_admin_referer(string $action = '', string $query_arg = '_wpnonce'): void {
}

function wp_die(string $message): void {
    fwrite(STDERR, 'wp_die: ' . $message . PHP_EOL);
    exit(1);
}

function wp_safe_redirect(string $location): void {
    $GLOBALS['lcfa_test_redirect'] = $location;
}

final class LCFA_Settings {
    public static bool $reset_called = false;
    public static array $notice = [
        'message' => '',
        'type' => '',
    ];

    public static function reset_setup_state(): void {
        self::$reset_called = true;
    }

    public static function set_notice(string $message, string $type = 'success'): void {
        self::$notice = [
            'message' => $message,
            'type' => $type,
        ];
    }
}

function lcfa_shutdown_assertions(): void {
    if (!LCFA_Settings::$reset_called) {
        fwrite(STDERR, "reset submit should invoke the full reset helper\n");
        exit(1);
    }

    $notice = LCFA_Settings::$notice;
    $redirect = $GLOBALS['lcfa_test_redirect'] ?? '';

    if (($notice['message'] ?? '') !== 'Forge state reset. Setup and connection status were cleared, a new MCP token was generated, and existing workspace files were left untouched.') {
        fwrite(STDERR, "reset submit should set the updated reset notice\n");
        exit(1);
    }

    if ($redirect !== 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=setup&step=1') {
        fwrite(STDERR, "reset submit should redirect back to setup step 1\n");
        exit(1);
    }

    echo "PASS\n";
}

register_shutdown_function('lcfa_shutdown_assertions');

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();
$admin->handle_reset_setup_post();

fwrite(STDERR, "handle_reset_setup_post should exit via redirect\n");
exit(1);
