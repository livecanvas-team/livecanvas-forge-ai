<?php

declare(strict_types=1);

error_reporting(E_ALL);

$wp_admin_includes = '/tmp/lcfa-tests/wp-admin/includes';
@mkdir($wp_admin_includes, 0777, true);
foreach (['file.php', 'misc.php', 'class-wp-upgrader.php'] as $file) {
    @file_put_contents($wp_admin_includes . '/' . $file, "<?php\n");
}

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_VERSION', 'test-version');

final class WP_Error {
    private string $code;
    private string $message;

    public function __construct(string $code = '', string $message = '') {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

final class LCFA_Environment {
}

final class LCFA_Settings {
    public static function get_connections(): array {
        return [
            'picowind_package_url'  => '',
            'picostrap_package_url' => '',
        ];
    }
}

final class Automatic_Upgrader_Skin {
}

final class Theme_Upgrader {
    public function __construct($skin = null) {
    }

    public function install(string $url): bool {
        $GLOBALS['lcfa_installed_theme_url'] = $url;
        return true;
    }
}

function __(string $text, string $domain = ''): string {
    return $text;
}

function apply_filters(string $hook_name, $value) {
    return $value;
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

function wp_remote_get(string $url, array $args = []) {
    $GLOBALS['lcfa_remote_get_url'] = $url;
    $GLOBALS['lcfa_remote_get_args'] = $args;

    return [
        'response' => ['code' => 200],
        'body'     => json_encode([
            'tag_name' => '0.0.13',
            'assets'   => [
                [
                    'name'                 => 'picowind-0.0.13.zip',
                    'browser_download_url' => 'https://github.com/livecanvas-team/picowind/releases/download/0.0.13/picowind-0.0.13.zip',
                ],
            ],
            'zipball_url' => 'https://api.github.com/repos/livecanvas-team/picowind/zipball/0.0.13',
        ], JSON_UNESCAPED_SLASHES),
    ];
}

function wp_remote_retrieve_response_code($response): int {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string {
    return (string) ($response['body'] ?? '');
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

require LCFA_DIR . 'includes/class-lcfa-installer.php';

$installer = new LCFA_Installer(new LCFA_Environment());
$method = new ReflectionMethod('LCFA_Installer', 'install_framework_package');
$result = $method->invoke($installer, 'picowind');

lcfa_assert_same(true, $result, 'Picowind installation should succeed with a GitHub latest release asset URL');
lcfa_assert_same(
    'https://api.github.com/repos/livecanvas-team/picowind/releases/latest',
    $GLOBALS['lcfa_remote_get_url'] ?? '',
    'installer should query the GitHub latest release endpoint for Picowind'
);
lcfa_assert_same(
    'https://github.com/livecanvas-team/picowind/releases/download/0.0.13/picowind-0.0.13.zip',
    $GLOBALS['lcfa_installed_theme_url'] ?? '',
    'installer should pass the Picowind release zip asset to Theme_Upgrader'
);
lcfa_assert_true(
    stripos((string) (($GLOBALS['lcfa_remote_get_args']['headers']['User-Agent'] ?? '')), 'LiveCanvas Forge AI/') !== false,
    'installer should identify the plugin in the GitHub request user agent'
);

echo "PASS\n";
