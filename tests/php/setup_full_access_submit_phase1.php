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

function sanitize_key(string $value): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\\-]/', '', $value) ?? '');
}

function absint($value): int {
    return abs((int) $value);
}

function wp_die(string $message): void {
    fwrite(STDERR, 'wp_die: ' . $message . PHP_EOL);
    exit(1);
}

function wp_safe_redirect(string $location): void {
    $GLOBALS['lcfa_test_redirect'] = $location;
}

final class LCFA_Settings {
    public static array $settings = [
        'completed'           => false,
        'framework'           => 'picowind',
        'site_mode'           => 'local',
        'ai_tool'             => 'codex',
        'permission_profile'  => 'draft_preview',
        'allow_file_fallback' => false,
        'last_completed_step' => 4,
    ];

    public static array $connections = [
        'preferred_client' => 'codex',
    ];

    public static array $notice = [
        'message' => '',
        'type'    => '',
    ];

    public static function get(): array {
        return self::$settings;
    }

    public static function patch(array $changes): array {
        self::$settings = array_merge(self::$settings, $changes);
        return self::$settings;
    }

    public static function get_connections(): array {
        return self::$connections;
    }

    public static function update_connections(array $connections): void {
        self::$connections = $connections;
    }

    public static function set_notice(string $message, string $type = 'success'): void {
        self::$notice = [
            'message' => $message,
            'type'    => $type,
        ];
    }
}

function lcfa_shutdown_assertions(): void {
    $settings = LCFA_Settings::$settings;
    $notice = LCFA_Settings::$notice;
    $redirect = $GLOBALS['lcfa_test_redirect'] ?? '';

    if (($settings['permission_profile'] ?? '') !== 'advanced_templates') {
        fwrite(STDERR, "step 5 should force the advanced_templates profile\n");
        exit(1);
    }

    if (($settings['allow_file_fallback'] ?? null) !== true) {
        fwrite(STDERR, "step 5 should force file fallback to enabled\n");
        exit(1);
    }

    if (($settings['last_completed_step'] ?? 0) !== 5) {
        fwrite(STDERR, "step 5 should mark the wizard as completed through step 5\n");
        exit(1);
    }

    if (($notice['message'] ?? '') !== 'Full access enabled.') {
        fwrite(STDERR, "step 5 should confirm that full access is enabled\n");
        exit(1);
    }

    if ($redirect !== 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=setup&step=6') {
        fwrite(STDERR, "step 5 should redirect to step 6\n");
        exit(1);
    }

    echo "PASS\n";
}

register_shutdown_function('lcfa_shutdown_assertions');

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$_POST = [
    'step' => '5',
];

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();
$admin->handle_setup_post();

fwrite(STDERR, "handle_setup_post should exit via redirect for step 5\n");
exit(1);
