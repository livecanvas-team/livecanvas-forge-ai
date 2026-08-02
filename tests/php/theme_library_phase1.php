<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-theme-library-tests/');

$GLOBALS['lcfa_theme_library_transients'] = [];
$GLOBALS['lcfa_theme_library_catalog_payload'] = [];

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $key): string { return strtolower(preg_replace('/[^a-zA-Z0-9_\\-]/', '', $key)); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function esc_url_raw(string $url): string { return trim($url); }
function current_time(string $type, bool $gmt = false): string { return '2026-06-24 00:00:00'; }
function get_transient(string $key) { return $GLOBALS['lcfa_theme_library_transients'][$key] ?? false; }
function set_transient(string $key, $value, int $ttl = 0): bool { $GLOBALS['lcfa_theme_library_transients'][$key] = $value; return true; }
function apply_filters(string $hook, $value) { return $value; }
function is_wp_error($value): bool { return false; }
function wp_remote_retrieve_response_code($response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response): string { return (string) ($response['body'] ?? ''); }
function wp_remote_get(string $url, array $args = []) {
    return [
        'response' => ['code' => 200],
        'body'     => json_encode($GLOBALS['lcfa_theme_library_catalog_payload']),
    ];
}
function wp_mkdir_p(string $path): bool { return is_dir($path) || mkdir($path, 0777, true); }

class LCFA_Settings {
    public static array $rollback_record = [];

    public static function get_rollback_record(string $audit_id) {
        return self::$rollback_record[$audit_id] ?? null;
    }
}

function lcfa_theme_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_theme_assert_false(bool $condition, string $message): void {
    lcfa_theme_assert_true(!$condition, $message);
}

function lcfa_theme_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_theme_create_zip(array $manifest_overrides = [], array $omit_files = [], array $file_overrides = []): string {
    $zip_path = tempnam(sys_get_temp_dir(), 'lcfa-theme') . '.zip';
    $zip = new ZipArchive();
    $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $root = 'sample-theme/';
    $files = [
        'style.css' => "/*\nTheme Name: Sample Theme\nTemplate: picowind\n*/",
        'functions.php' => "<?php\n",
        'screenshot.jpg' => 'fake-jpg',
        'page-templates/empty.php' => "<?php\n/* Template Name: Empty Page Template */\n",
        'views/page-templates/empty.twig' => '<!doctype html>{{ function("wp_head") }}{{ function("wp_body_open") }}{{ function("lc_get_header", 1) }}<main>{{ post.content }}</main>{{ function("lc_get_footer", 1) }}{{ function("wp_footer") }}',
        'livecanvas/configuration.php' => "<?php\n",
        'public/styles/presets/daisyui.css' => '/* daisyui */',
        'public/styles/tailwind.css' => 'body{}',
        'starter-data/livecanvas-settings.json' => '{"options":{}}',
        'starter-data/design-system.json' => '{"theme":"sample"}',
        'starter-data/media-manifest.json' => '{"items":[]}',
        'starter-data/menus.json' => '{"menus":[]}',
        'starter-data/qa-report.json' => '{"ok":true}',
        'starter-data/home.html' => '<section>Home {{media:hero}}</section>',
        'starter-data/header.html' => '<nav>Header</nav>',
        'starter-data/footer.html' => '<div>Footer</div>',
    ];
    foreach ($file_overrides as $path => $contents) {
        if ($contents === null) {
            unset($files[$path]);
        } else {
            $files[$path] = (string) $contents;
        }
    }
    $manifest = array_replace_recursive([
        'schema' => 'lcfa-theme.v1',
        'theme' => [
            'slug' => 'sample-theme',
            'name' => 'Sample Theme',
            'version' => '1.0.0',
            'stylesheet' => 'sample-theme',
        ],
        'homepage' => [
            'title' => 'Home',
            'slug' => 'home',
            'template' => 'page-templates/empty.php',
            'content_file' => 'starter-data/home.html',
        ],
        'header' => [
            'variant' => '1',
            'content_file' => 'starter-data/header.html',
        ],
        'footer' => [
            'variant' => '1',
            'content_file' => 'starter-data/footer.html',
        ],
    ], $manifest_overrides);
    $files['starter-data/lcfa-theme.json'] = json_encode($manifest);

    foreach ($files as $path => $contents) {
        if (in_array($path, $omit_files, true)) {
            continue;
        }
        $zip->addFromString($root . $path, $contents);
    }
    $zip->close();

    return $zip_path;
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-catalog.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-validator.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-rollback.php';

$valid_zip = lcfa_theme_create_zip();
$checksum = hash_file('sha256', $valid_zip);

$GLOBALS['lcfa_theme_library_catalog_payload'] = [
    'themes' => [
        [
            'slug' => 'sample-theme',
            'name' => 'Sample Theme',
            'version' => '1.0.0',
            'package_url' => 'https://example.test/sample-theme.zip',
            'checksum' => 'sha256:' . $checksum,
            'screenshot' => 'https://example.test/screenshot.jpg',
        ],
        [
            'slug' => 'broken-theme',
            'name' => 'Broken Theme',
            'version' => '1.0.0',
            'package_url' => 'https://example.test/broken.zip',
            'checksum' => str_repeat('a', 64),
        ],
    ],
];

$catalog = (new LCFA_Theme_Library_Catalog())->get_catalog(true);
lcfa_theme_assert_true(!empty($catalog['ok']), 'valid catalog should load');
lcfa_theme_assert_same(1, count($catalog['themes']), 'catalog should include only themes with required screenshot/package/checksum data');
lcfa_theme_assert_same('sample-theme', $catalog['themes'][0]['slug'], 'catalog should normalize theme slug');
lcfa_theme_assert_same('picowind', $catalog['themes'][0]['framework'], 'catalog should default Theme Library items to Picowind');
lcfa_theme_assert_same('tailwind', $catalog['themes'][0]['stack']['css'], 'catalog should expose CSS stack metadata');
lcfa_theme_assert_same('daisyui', $catalog['themes'][0]['stack']['ui'], 'catalog should expose UI stack metadata');
lcfa_theme_assert_true(count($catalog['errors']) === 1, 'catalog should report invalid entries');

$validator = new LCFA_Theme_Library_Validator();
$valid = $validator->validate_zip($valid_zip, ['checksum' => $checksum]);
lcfa_theme_assert_true(!empty($valid['ok']), 'valid ZIP should pass validation');
lcfa_theme_assert_same('lcfa-theme.v1', $valid['manifest']['schema'] ?? '', 'manifest schema should be preserved');

$example_catalog_path = dirname(__DIR__, 2) . '/examples/theme-library/catalog.json';
$example_catalog = json_decode((string) file_get_contents($example_catalog_path), true);
foreach ((array) ($example_catalog['themes'] ?? []) as $example_theme) {
    $example_slug = (string) ($example_theme['slug'] ?? '');
    $example_zip_path = dirname(__DIR__, 2) . '/examples/theme-library/themes/' . $example_slug . '/' . $example_slug . '.zip';
    lcfa_theme_assert_true(is_file($example_zip_path), 'example Theme Library ZIP should exist for beta fallback catalog testing: ' . $example_slug);
    lcfa_theme_assert_same('picowind', (string) ($example_theme['framework'] ?? ''), 'example Theme Library catalog entries should declare Picowind: ' . $example_slug);
    lcfa_theme_assert_same('tailwind', (string) ($example_theme['css'] ?? ''), 'example Theme Library catalog entries should declare Tailwind: ' . $example_slug);
    lcfa_theme_assert_same('daisyui', (string) ($example_theme['ui'] ?? ''), 'example Theme Library catalog entries should declare DaisyUI: ' . $example_slug);
    lcfa_theme_assert_same((string) ($example_theme['checksum'] ?? ''), 'sha256:' . hash_file('sha256', $example_zip_path), 'example catalog checksum should match the packaged ZIP: ' . $example_slug);
    $example_validation = $validator->validate_zip($example_zip_path, $example_theme);
    lcfa_theme_assert_true(!empty($example_validation['ok']), 'example Theme Library ZIP should pass validation: ' . $example_slug);
}

$not_picowind_zip = lcfa_theme_create_zip([], [], [
    'style.css' => "/*\nTheme Name: Sample Theme\nTemplate: twentytwentyfour\n*/",
]);
$not_picowind = $validator->validate_zip($not_picowind_zip, ['checksum' => hash_file('sha256', $not_picowind_zip)]);
lcfa_theme_assert_false(!empty($not_picowind['ok']), 'non-Picowind child themes should fail validation');

$inline_shell_zip = lcfa_theme_create_zip([], [], [
    'starter-data/home.html' => '<header>Wrong</header><section>Home</section>',
]);
$inline_shell = $validator->validate_zip($inline_shell_zip, ['checksum' => hash_file('sha256', $inline_shell_zip)]);
lcfa_theme_assert_false(!empty($inline_shell['ok']), 'homepage files with inline header/footer markup should fail validation');

$missing_partial_shell_zip = lcfa_theme_create_zip([], [], [
    'views/page-templates/empty.twig' => '<!doctype html>{{ function("wp_head") }}{{ function("wp_body_open") }}<main>{{ post.content }}</main>{{ function("wp_footer") }}',
]);
$missing_partial_shell = $validator->validate_zip($missing_partial_shell_zip, ['checksum' => hash_file('sha256', $missing_partial_shell_zip)]);
lcfa_theme_assert_false(!empty($missing_partial_shell['ok']), 'page templates that omit LiveCanvas header/footer partials should fail validation');

$wrapped_partial_zip = lcfa_theme_create_zip([], [], [
    'starter-data/header.html' => '<header><nav>Wrong shell</nav></header>',
]);
$wrapped_partial = $validator->validate_zip($wrapped_partial_zip, ['checksum' => hash_file('sha256', $wrapped_partial_zip)]);
lcfa_theme_assert_false(!empty($wrapped_partial['ok']), 'partial files with duplicate outer header/footer shells should fail validation');

$missing_media_zip = lcfa_theme_create_zip([], [], [
    'starter-data/media-manifest.json' => '{"items":[{"id":"hero","file":"starter-data/media/missing.jpg"}]}',
]);
$missing_media = $validator->validate_zip($missing_media_zip, ['checksum' => hash_file('sha256', $missing_media_zip)]);
lcfa_theme_assert_false(!empty($missing_media['ok']), 'media manifest files should exist inside the ZIP');

$missing_css_import_zip = lcfa_theme_create_zip([], [], [
    'public/styles/tailwind.css' => '@import "./presets/missing.css";',
]);
$missing_css_import = $validator->validate_zip($missing_css_import_zip, ['checksum' => hash_file('sha256', $missing_css_import_zip)]);
lcfa_theme_assert_false(!empty($missing_css_import['ok']), 'missing relative CSS imports should fail validation');

$bad_checksum = $validator->validate_zip($valid_zip, ['checksum' => str_repeat('b', 64)]);
lcfa_theme_assert_false(!empty($bad_checksum['ok']), 'checksum mismatch should fail validation');

$traversal_zip = lcfa_theme_create_zip([
    'homepage' => [
        'content_file' => '../outside.html',
    ],
]);
$traversal = $validator->validate_zip($traversal_zip, ['checksum' => hash_file('sha256', $traversal_zip)]);
lcfa_theme_assert_false(!empty($traversal['ok']), 'manifest path traversal should fail validation');

$missing_zip = lcfa_theme_create_zip([], ['screenshot.jpg']);
$missing = $validator->validate_zip($missing_zip, ['checksum' => hash_file('sha256', $missing_zip)]);
lcfa_theme_assert_false(!empty($missing['ok']), 'missing required files should fail validation');

LCFA_Settings::$rollback_record = [
    'theme-import-sample-theme-abc123' => [
        'type' => 'theme_library_import',
        'previous_theme' => 'picowind-child',
        'updated_options' => ['show_on_front' => ['exists' => true, 'value' => 'posts']],
        'updated_posts' => [101 => ['post_title' => 'Old home']],
        'created_posts' => [102],
        'created_media' => [103],
        'created_menus' => [104],
        'windpress_runtime' => [
            'available' => true,
            'files' => [
                'main_css' => ['exists' => true],
                'cache_css' => ['exists' => false],
            ],
        ],
    ],
];
$rollback_preview = (new LCFA_Theme_Library_Rollback())->rollback('theme-import-sample-theme-abc123', true);
lcfa_theme_assert_true(!empty($rollback_preview['ok']), 'rollback dry-run should prepare a plan for Theme Library import records');
lcfa_theme_assert_same([102], $rollback_preview['plan']['created_posts'] ?? [], 'rollback dry-run should list created posts');
lcfa_theme_assert_same(['main_css', 'cache_css'], $rollback_preview['plan']['windpress_runtime']['files'] ?? [], 'rollback dry-run should disclose the WindPress runtime files that will be restored');
$rollback_missing = (new LCFA_Theme_Library_Rollback())->rollback('missing-audit', true);
lcfa_theme_assert_false(!empty($rollback_missing['ok']), 'rollback should fail when no Theme Library import record exists');

$rest_source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-rest-api.php');
$ability_source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-ability-registry.php');
$importer_source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php');
$admin_source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-admin.php');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/theme-library/import'"), 'REST API should register Theme Library import endpoint');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "'permission_callback' => [\$this, 'can_manage']"), 'Theme Library REST endpoints should use admin-only can_manage permission');
lcfa_theme_assert_true(is_string($ability_source) && !str_contains($ability_source, 'theme-library'), 'Theme Library endpoints should not be MCP-public abilities in v1');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, "'status'     => 'failed'"), 'failed Theme Library imports should be tracked for rollback visibility');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, 'ensure_picowind_runtime'), 'Theme Library import should initialize the Picowind/WindPress runtime');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, 'windpress_source_css_ready'), 'Theme Library import should not store Tailwind source CSS as compiled WindPress cache');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'THEME_LIBRARY_PREVIEW_TRANSIENT_PREFIX'), 'Theme Library UI should remember a successful package preview per user');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'Preview and validate the package before installing it.'), 'Theme Library UI should require preview before the first child-theme install');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, '$operation === \'import\' && $show_force'), 'Theme Library UI should show force update only for existing imports');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-filter="all"'), 'Theme Library category controls should be functional buttons');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-category='), 'Theme Library cards should expose their filter category');

@unlink($valid_zip);
@unlink($not_picowind_zip);
@unlink($inline_shell_zip);
@unlink($missing_media_zip);
@unlink($missing_css_import_zip);
@unlink($traversal_zip);
@unlink($missing_zip);

echo "PASS\n";
