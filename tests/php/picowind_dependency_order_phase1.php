<?php

declare(strict_types=1);

error_reporting(E_ALL);

$wp_admin_includes = '/tmp/lcfa-tests/wp-admin/includes';
@mkdir($wp_admin_includes, 0777, true);
foreach (['file.php', 'misc.php', 'class-wp-upgrader.php', 'plugin-install.php', 'plugin.php'] as $file) {
    @file_put_contents($wp_admin_includes . '/' . $file, "<?php\n");
}

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_VERSION', 'test-version');

$GLOBALS['lcfa_dependency_order'] = [];
$GLOBALS['lcfa_windpress_installed'] = false;
$GLOBALS['lcfa_windpress_active'] = false;
$GLOBALS['lcfa_picowind_installed'] = false;
$GLOBALS['lcfa_active_theme'] = 'twentytwentyfive';

final class WP_Error {
    public function __construct(public string $code = '', public string $message = '') {
    }
}

final class LCFA_Test_Active_Theme {
    public function get_stylesheet(): string {
        return (string) $GLOBALS['lcfa_active_theme'];
    }
}

final class LCFA_Environment {
    public function get_preferred_theme_stylesheet(string $framework): ?string {
        return $framework === 'picowind' && !empty($GLOBALS['lcfa_picowind_installed']) ? 'picowind' : null;
    }

    public function find_plugin_file_by_slug(string $slug): ?string {
        return $slug === 'windpress' && !empty($GLOBALS['lcfa_windpress_installed']) ? 'windpress/windpress.php' : null;
    }

    public function refresh_plugin_caches(): void {
    }

    public function refresh_theme_caches(): void {
    }

    public function is_plugin_active(string $plugin_file): bool {
        return $plugin_file === 'windpress/windpress.php' && !empty($GLOBALS['lcfa_windpress_active']);
    }
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

final class Plugin_Upgrader {
    public function __construct($skin = null) {
    }

    public function install(string $url): bool {
        $GLOBALS['lcfa_dependency_order'][] = 'install-windpress';
        $GLOBALS['lcfa_windpress_installed'] = true;

        return true;
    }
}

final class Theme_Upgrader {
    public function __construct($skin = null) {
    }

    public function install(string $url): bool {
        $GLOBALS['lcfa_dependency_order'][] = 'install-picowind';
        $GLOBALS['lcfa_picowind_installed'] = true;

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

function plugins_api(string $action, array $args) {
    return (object) ['download_link' => 'https://downloads.wordpress.org/plugin/windpress.latest-stable.zip'];
}

function activate_plugin(string $plugin_file): bool {
    $GLOBALS['lcfa_dependency_order'][] = 'activate-windpress';
    $GLOBALS['lcfa_windpress_active'] = true;

    return true;
}

function wp_remote_get(string $url, array $args = []): array {
    return [
        'response' => ['code' => 200],
        'body' => json_encode([
            'assets' => [[
                'name' => 'picowind.zip',
                'browser_download_url' => 'https://github.com/livecanvas-team/picowind/releases/download/test/picowind.zip',
            ]],
        ], JSON_UNESCAPED_SLASHES),
    ];
}

function wp_remote_retrieve_response_code(array $response): int {
    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body(array $response): string {
    return (string) ($response['body'] ?? '');
}

function wp_get_theme(): LCFA_Test_Active_Theme {
    return new LCFA_Test_Active_Theme();
}

function switch_theme(string $stylesheet): void {
    $GLOBALS['lcfa_dependency_order'][] = 'activate-picowind';
    $GLOBALS['lcfa_active_theme'] = $stylesheet;
}

function lcfa_dependency_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-installer.php';

$installer = new LCFA_Installer(new LCFA_Environment(), new LCFA_Framework_Prerequisites('8.2.0'));
$result = $installer->apply_framework('picowind');

lcfa_dependency_assert_same(
    ['install-windpress', 'activate-windpress', 'install-picowind', 'activate-picowind'],
    $GLOBALS['lcfa_dependency_order'],
    'Picowind setup must install and activate WindPress before installing and activating the theme.'
);
lcfa_dependency_assert_same('picowind', $result['framework'] ?? '', 'Picowind setup should complete successfully.');
lcfa_dependency_assert_same('installed', $result['theme_status'] ?? '', 'Picowind setup should report that the theme was installed.');

echo "PASS\n";
