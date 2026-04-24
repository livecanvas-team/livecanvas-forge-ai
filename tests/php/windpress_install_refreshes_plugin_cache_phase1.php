<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/lcfa-tests/');
}

foreach ([
    'wp-admin/includes/file.php',
    'wp-admin/includes/misc.php',
    'wp-admin/includes/class-wp-upgrader.php',
    'wp-admin/includes/plugin-install.php',
    'wp-admin/includes/plugin.php',
] as $relative_path) {
    $path = ABSPATH . $relative_path;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    if (!file_exists($path)) {
        file_put_contents($path, "<?php\n");
    }
}

final class WP_Error {
    public string $code;
    public string $message;

    public function __construct(string $code = '', string $message = '') {
        $this->code    = $code;
        $this->message = $message;
    }
}

final class LCFA_Environment {
    public bool $plugin_cache_refreshed = false;

    public function find_plugin_file_by_slug(string $slug): ?string {
        if ($slug !== 'windpress') {
            return null;
        }

        return $this->plugin_cache_refreshed ? 'windpress/windpress.php' : null;
    }

    public function refresh_plugin_caches(): void {
        $this->plugin_cache_refreshed = true;
    }

    public function is_plugin_active(string $plugin_file): bool {
        return false;
    }
}

function __(string $text, string $domain = ''): string {
    return $text;
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

function plugins_api(string $action, array $args) {
    return (object) [
        'download_link' => 'https://downloads.wordpress.org/plugin/windpress.latest-stable.zip',
    ];
}

final class Automatic_Upgrader_Skin {
}

final class Plugin_Upgrader {
    public function __construct($skin = null) {
    }

    public function install(string $url) {
        $GLOBALS['lcfa_installed_plugin_url'] = $url;

        return true;
    }
}

function activate_plugin(string $plugin_file) {
    $GLOBALS['lcfa_activated_plugin_file'] = $plugin_file;

    return true;
}

require_once dirname(__DIR__, 2) . '/includes/class-lcfa-installer.php';

$environment = new LCFA_Environment();
$installer    = new LCFA_Installer($environment);
$result       = $installer->ensure_windpress_active();

if ($result !== 'activated') {
    fwrite(STDERR, "WindPress should be activated after refreshing plugin caches.\n");
    fwrite(STDERR, 'Actual: ' . var_export($result, true) . "\n");
    exit(1);
}

if (!$environment->plugin_cache_refreshed) {
    fwrite(STDERR, "Installer should refresh plugin caches after installing WindPress.\n");
    exit(1);
}

if (($GLOBALS['lcfa_activated_plugin_file'] ?? '') !== 'windpress/windpress.php') {
    fwrite(STDERR, "Installer should activate the WindPress plugin file discovered after refresh.\n");
    exit(1);
}

echo "PASS\n";
