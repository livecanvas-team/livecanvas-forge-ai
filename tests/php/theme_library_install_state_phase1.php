<?php

declare(strict_types=1);

error_reporting(E_ALL);

$GLOBALS['lcfa_installer_root'] = sys_get_temp_dir() . '/lcfa-theme-installer-' . uniqid('', true);
$GLOBALS['lcfa_installer_options'] = [];
$GLOBALS['lcfa_installer_active_theme'] = 'original-child';
$GLOBALS['lcfa_installer_theme_mods'] = ['primary' => 42];
$GLOBALS['lcfa_installer_installed_version'] = '1.0.0';
$GLOBALS['lcfa_installer_target_version'] = '1.0.0';
$GLOBALS['lcfa_installer_upgrader_args'] = [];

define('ABSPATH', $GLOBALS['lcfa_installer_root'] . '/wordpress/');
define('LCFA_DIR', $GLOBALS['lcfa_installer_root'] . '/plugin/');
@mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
@mkdir(LCFA_DIR . 'packages', 0777, true);
file_put_contents(ABSPATH . 'wp-admin/includes/file.php', "<?php\n");
file_put_contents(ABSPATH . 'wp-admin/includes/misc.php', "<?php\n");
file_put_contents(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php', "<?php\n");
file_put_contents(ABSPATH . 'wp-admin/includes/theme.php', "<?php\n");
file_put_contents(LCFA_DIR . 'packages/sample-theme.zip', 'fake-package');

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $key): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function esc_url_raw(string $url): string { return trim($url); }
function trailingslashit(string $path): string { return rtrim($path, '/\\') . '/'; }
function wp_tempnam(string $filename = ''): string { return (string) tempnam(sys_get_temp_dir(), 'lcfa-installer-copy-'); }
function is_wp_error($value): bool { return false; }
function get_option(string $name, $default = false) { return array_key_exists($name, $GLOBALS['lcfa_installer_options']) ? $GLOBALS['lcfa_installer_options'][$name] : $default; }
function update_option(string $name, $value, bool $autoload = false): bool { $GLOBALS['lcfa_installer_options'][$name] = $value; return true; }
function delete_option(string $name): bool { unset($GLOBALS['lcfa_installer_options'][$name]); return true; }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 00:00:00'; }
function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string { return 'AbCd1234'; }
function get_theme_mod(string $name, $default = false) { return $name === 'nav_menu_locations' ? $GLOBALS['lcfa_installer_theme_mods'] : $default; }

final class LCFA_Installer_Test_Theme {
    private string $stylesheet;
    private bool $exists;

    public function __construct(string $stylesheet, bool $exists = true) {
        $this->stylesheet = $stylesheet;
        $this->exists = $exists;
    }

    public function exists(): bool { return $this->exists; }
    public function get_stylesheet(): string { return $this->stylesheet; }
    public function get(string $field): string {
        if ($field === 'Name') {
            return 'Sample Theme';
        }
        if ($field === 'TextDomain') {
            return 'sample-theme';
        }
        if ($field === 'Version' && $this->stylesheet === 'sample-theme') {
            return (string) $GLOBALS['lcfa_installer_installed_version'];
        }
        return '';
    }
    public function get_template(): string { return $this->stylesheet === 'sample-theme' ? 'picowind' : 'picowind'; }
}

function wp_get_theme(string $stylesheet = ''): LCFA_Installer_Test_Theme {
    if ($stylesheet === '') {
        return new LCFA_Installer_Test_Theme($GLOBALS['lcfa_installer_active_theme']);
    }
    return new LCFA_Installer_Test_Theme($stylesheet, in_array($stylesheet, ['original-child', 'sample-theme'], true));
}
function switch_theme(string $stylesheet): void { $GLOBALS['lcfa_installer_active_theme'] = $stylesheet; }
function wp_get_themes(): array { return ['sample-theme' => wp_get_theme('sample-theme')]; }

class LCFA_Theme_Library_Validator {
    public function validate_zip(string $zip_path, array $theme): array {
        return [
            'ok'       => true,
            'checksum' => 'abc123',
            'manifest' => [
                'theme' => [
                    'slug'       => 'sample-theme',
                    'stylesheet' => 'sample-theme',
                    'name'       => 'Sample Theme',
                    'version'    => (string) $GLOBALS['lcfa_installer_target_version'],
                ],
            ],
        ];
    }
}

class Automatic_Upgrader_Skin {}
class Theme_Upgrader {
    public function __construct($skin = null) {}

    public function install(string $zip_path, array $args = []): bool {
        $GLOBALS['lcfa_installer_upgrader_args'] = $args;
        $GLOBALS['lcfa_installer_installed_version'] = (string) $GLOBALS['lcfa_installer_target_version'];
        return true;
    }
}

class LCFA_WindPress_Bridge {
    public array $captured = [];
    public array $deleted = [];

    public function capture_runtime_state(string $audit_id): array {
        $this->captured[] = $audit_id;
        return [
            'ok'        => true,
            'available' => true,
            'audit_id'  => $audit_id,
            'files'     => ['cache_css' => ['exists' => true]],
        ];
    }

    public function delete_runtime_backup(array $state): array {
        $this->deleted[] = (string) ($state['audit_id'] ?? '');
        return ['ok' => true, 'removed' => true];
    }
}

function lcfa_installer_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_installer_remove_tree(string $path): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . '/' . $item;
        is_dir($child) ? lcfa_installer_remove_tree($child) : @unlink($child);
    }
    @rmdir($path);
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-installer.php';

$windpress = new LCFA_WindPress_Bridge();
$installer = new LCFA_Theme_Library_Installer(
    new LCFA_Theme_Library_Validator(),
    $windpress,
    new LCFA_Framework_Prerequisites('8.2.0')
);
$result = $installer->install([
    'slug'         => 'sample-theme',
    'name'         => 'Sample Theme',
    'version'      => '1.0.0',
    'package_path' => 'packages/sample-theme.zip',
]);

lcfa_installer_assert(!empty($result['ok']), 'An existing Theme Library child theme should activate successfully.');
lcfa_installer_assert($GLOBALS['lcfa_installer_active_theme'] === 'sample-theme', 'The selected child theme should become active.');

$pending = $installer->get_pending_install_state('sample-theme', 'sample-theme');
lcfa_installer_assert(($pending['previous_theme'] ?? '') === 'original-child', 'The installer must preserve the theme active before activation.');
lcfa_installer_assert(($pending['previous_theme_mods']['nav_menu_locations']['primary'] ?? 0) === 42, 'The installer must preserve menu locations before changing themes.');
lcfa_installer_assert(!empty($pending['windpress_runtime']['available']), 'The installer must capture WindPress before theme activation hooks run.');
lcfa_installer_assert(count($windpress->captured) === 1, 'WindPress runtime should be captured once before activation.');

$consumed = $installer->consume_pending_install_state('sample-theme', 'sample-theme');
lcfa_installer_assert(($consumed['previous_theme'] ?? '') === 'original-child', 'Importer handoff should receive the pending activation state.');
lcfa_installer_assert($installer->get_pending_install_state('sample-theme', 'sample-theme') === [], 'Pending activation state should be consumed exactly once.');

$second = $installer->install([
    'slug'         => 'sample-theme',
    'name'         => 'Sample Theme',
    'version'      => '1.0.0',
    'package_path' => 'packages/sample-theme.zip',
]);
lcfa_installer_assert(!empty($second['ok']), 'Reactivating the current theme should remain idempotent.');
lcfa_installer_assert(count($windpress->captured) === 1, 'The current active theme should not create a redundant runtime backup.');

$GLOBALS['lcfa_installer_target_version'] = '1.0.1';
$updated = $installer->install([
    'slug'         => 'sample-theme',
    'name'         => 'Sample Theme',
    'version'      => '1.0.1',
    'package_path' => 'packages/sample-theme.zip',
]);
lcfa_installer_assert(!empty($updated['ok']) && ($updated['status'] ?? '') === 'updated', 'A newer Theme Library package should update an installed child theme.');
lcfa_installer_assert(!empty($GLOBALS['lcfa_installer_upgrader_args']['overwrite_package']), 'Child-theme updates should explicitly replace the existing package.');
lcfa_installer_assert($GLOBALS['lcfa_installer_installed_version'] === '1.0.1', 'The installed child theme should advance to the catalog version.');
lcfa_installer_assert($GLOBALS['lcfa_installer_active_theme'] === 'sample-theme', 'Updating the active child theme should keep it active.');

$GLOBALS['lcfa_installer_active_theme'] = 'original-child';
$blocked_installer = new LCFA_Theme_Library_Installer(
    new LCFA_Theme_Library_Validator(),
    $windpress,
    new LCFA_Framework_Prerequisites('8.1.34')
);
$blocked = $blocked_installer->install([
    'slug'         => 'sample-theme',
    'name'         => 'Sample Theme',
    'version'      => '1.0.0',
    'framework'    => 'picowind',
    'package_path' => 'packages/sample-theme.zip',
]);
lcfa_installer_assert(empty($blocked['ok']), 'Theme Library install should be blocked on PHP 8.1.');
lcfa_installer_assert(($blocked['status'] ?? '') === 'php_upgrade_required', 'Theme Library install should expose a guided PHP blocker status.');
lcfa_installer_assert($GLOBALS['lcfa_installer_active_theme'] === 'original-child', 'Blocked Theme Library install must not switch the active theme.');
$GLOBALS['lcfa_installer_active_theme'] = 'sample-theme';

$GLOBALS['lcfa_installer_options']['lcfa_theme_library_pending_installs'] = [
    'sample-theme' => [
        'stylesheet' => 'sample-theme',
        'captured_at' => '2026-01-01 00:00:00',
        'windpress_runtime' => ['available' => true, 'audit_id' => 'active-backup'],
    ],
    'abandoned-theme' => [
        'stylesheet' => 'abandoned-theme',
        'captured_at' => '2026-01-01 00:00:00',
        'windpress_runtime' => ['available' => true, 'audit_id' => 'abandoned-backup'],
    ],
    'fresh-theme' => [
        'stylesheet' => 'fresh-theme',
        'captured_at' => '2026-08-01 12:00:00',
        'windpress_runtime' => ['available' => true, 'audit_id' => 'fresh-backup'],
    ],
];
$cleanup = $installer->cleanup_stale_pending_install_states(604800, (int) strtotime('2026-08-02 00:00:00 UTC'));
lcfa_installer_assert(!empty($cleanup['ok']) && ($cleanup['removed'] ?? 0) === 1, 'Cleanup should remove only an inactive stale install handoff.');
lcfa_installer_assert(isset($GLOBALS['lcfa_installer_options']['lcfa_theme_library_pending_installs']['sample-theme']), 'Cleanup must retain a stale handoff for the currently active theme.');
lcfa_installer_assert(isset($GLOBALS['lcfa_installer_options']['lcfa_theme_library_pending_installs']['fresh-theme']), 'Cleanup must retain a recent inactive handoff.');
lcfa_installer_assert($windpress->deleted === ['abandoned-backup'], 'Cleanup should delete only the orphaned WindPress runtime backup.');

lcfa_installer_remove_tree($GLOBALS['lcfa_installer_root']);
echo "PASS\n";
