<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

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
$picostrap_bundle = ':root{--bs-primary:#7047a8}.container{width:100%}.houseflow-header{position:sticky}'
    . str_repeat('.row{display:flex;flex-wrap:wrap}', 4);
$picostrap_zip = lcfa_theme_create_zip([
    'theme' => [
        'framework' => 'picostrap',
        'parent' => 'picostrap5',
    ],
    'compatibility' => [
        'framework' => 'picostrap',
        'picostrap' => '5',
    ],
    'css_verification' => [
        'required_fragments' => ['.houseflow-header'],
    ],
], [], [
    'style.css' => "/*\nTheme Name: Sample Picostrap\nTemplate: picostrap5\n*/",
    'page-templates/empty.php' => "<?php\n/* Template Name: Empty Page Template */\nget_header(); while (have_posts()) { the_post(); the_content(); } get_footer();\n",
    'views/page-templates/empty.twig' => null,
    'public/styles/presets/daisyui.css' => null,
    'public/styles/tailwind.css' => null,
    'css-output/bundle.css' => $picostrap_bundle,
    'sass/_theme_variables.scss' => '$primary: #7047a8;',
    'sass/_custom.scss' => '.houseflow-header { position: sticky; }',
    'js/bootstrap.bundle.min.js' => '/* Bootstrap 5 bundle */',
    'js/custom.js' => '/* Hearthline interactions */',
    'starter-data/header.html' => '<header><nav>Header</nav></header>',
    'starter-data/footer.html' => '<footer>Footer</footer>',
]);
$picostrap_checksum = hash_file('sha256', $picostrap_zip);

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
        [
            'slug' => 'sample-picostrap',
            'name' => 'Sample Picostrap',
            'version' => '1.0.0',
            'framework' => 'bootstrap-5',
            'package_url' => 'https://example.test/sample-picostrap.zip',
            'checksum' => 'sha256:' . $checksum,
            'screenshot' => 'https://example.test/picostrap.jpg',
        ],
        [
            'slug' => 'unsupported-stack',
            'name' => 'Unsupported Stack',
            'version' => '1.0.0',
            'framework' => 'unknown-framework',
            'package_url' => 'https://example.test/unsupported.zip',
            'checksum' => 'sha256:' . $checksum,
            'screenshot' => 'https://example.test/unsupported.jpg',
        ],
    ],
];

$catalog = (new LCFA_Theme_Library_Catalog())->get_catalog(true);
lcfa_theme_assert_true(!empty($catalog['ok']), 'valid catalog should load');
lcfa_theme_assert_same(2, count($catalog['themes']), 'catalog should include valid Picowind and Picostrap themes');
lcfa_theme_assert_same('sample-theme', $catalog['themes'][0]['slug'], 'catalog should normalize theme slug');
lcfa_theme_assert_same('picowind', $catalog['themes'][0]['framework'], 'catalog should default Theme Library items to Picowind');
lcfa_theme_assert_same('Picowind', $catalog['themes'][0]['framework_label'], 'catalog should expose a readable Picowind label');
lcfa_theme_assert_same('Tailwind CSS + DaisyUI', $catalog['themes'][0]['technology_label'], 'catalog should expose the Picowind technology label');
lcfa_theme_assert_same('tailwind', $catalog['themes'][0]['stack']['css'], 'catalog should expose CSS stack metadata');
lcfa_theme_assert_same('daisyui', $catalog['themes'][0]['stack']['ui'], 'catalog should expose UI stack metadata');
lcfa_theme_assert_same('picostrap', $catalog['themes'][1]['framework'], 'catalog should normalize Bootstrap aliases to Picostrap');
lcfa_theme_assert_same('Picostrap', $catalog['themes'][1]['framework_label'], 'catalog should expose a readable Picostrap label');
lcfa_theme_assert_same('Bootstrap 5', $catalog['themes'][1]['technology_label'], 'catalog should expose the Picostrap technology label');
lcfa_theme_assert_same(['picowind' => 1, 'picostrap' => 1], $catalog['frameworks'], 'catalog should expose framework counts for filtering');
lcfa_theme_assert_same(3, $catalog['normalization_version'], 'catalog should invalidate normalized cache when merge semantics change');
lcfa_theme_assert_true(count($catalog['errors']) === 2, 'catalog should report missing data and unsupported framework entries');

$merge_catalogs = lcfa_test_reflection_method(LCFA_Theme_Library_Catalog::class, 'merge_catalogs');
$merged_catalog = $merge_catalogs->invoke(new LCFA_Theme_Library_Catalog(), [
    'themes' => [],
    'errors' => [
        'Theme "sample-theme" is missing slug, name, version, package_url/package_path, checksum, or screenshot.',
        'Theme "sample-theme" is missing slug, name, version, package_url/package_path, checksum, or screenshot.',
    ],
], [
    'themes' => [$catalog['themes'][0]],
    'errors' => [],
]);
lcfa_theme_assert_same([], $merged_catalog['errors'], 'a valid bundled theme should clear duplicate stale errors for the same slug');

$remote_newer_theme = $catalog['themes'][0];
$remote_newer_theme['version'] = '1.0.3';
$remote_newer_theme['package_url'] = 'https://example.test/remote-1.0.3.zip';
$bundled_older_theme = $catalog['themes'][0];
$bundled_older_theme['version'] = '1.0.2';
$bundled_older_theme['package_url'] = 'https://example.test/bundled-1.0.2.zip';
$remote_newer_catalog = $merge_catalogs->invoke(new LCFA_Theme_Library_Catalog(), [
    'themes' => [$remote_newer_theme],
    'errors' => [],
], [
    'themes' => [$bundled_older_theme],
    'errors' => [],
]);
lcfa_theme_assert_same('1.0.3', $remote_newer_catalog['themes'][0]['version'] ?? '', 'an older bundled theme must not hide a newer remote release');
lcfa_theme_assert_same('https://example.test/remote-1.0.3.zip', $remote_newer_catalog['themes'][0]['package_url'] ?? '', 'equal-slug catalog merges should preserve the newer remote package');

$bundled_newer_theme = $bundled_older_theme;
$bundled_newer_theme['version'] = '1.0.4';
$bundled_newer_theme['package_url'] = 'https://example.test/bundled-1.0.4.zip';
$bundled_newer_catalog = $merge_catalogs->invoke(new LCFA_Theme_Library_Catalog(), [
    'themes' => [$remote_newer_theme],
    'errors' => [],
], [
    'themes' => [$bundled_newer_theme],
    'errors' => [],
]);
lcfa_theme_assert_same('1.0.4', $bundled_newer_catalog['themes'][0]['version'] ?? '', 'a newer bundled fallback should remain available when it leads the remote catalog');

$validator = new LCFA_Theme_Library_Validator();
$valid = $validator->validate_zip($valid_zip, ['checksum' => $checksum]);
lcfa_theme_assert_true(!empty($valid['ok']), 'valid ZIP should pass validation');
lcfa_theme_assert_same('lcfa-theme.v1', $valid['manifest']['schema'] ?? '', 'manifest schema should be preserved');
lcfa_theme_assert_same('', $valid['requires_php'] ?? '', 'validator should expose an empty PHP requirement when style.css does not declare one');
lcfa_theme_assert_same([], $valid['manifest']['css_verification']['required_fragments'] ?? null, 'validator should normalize missing CSS verification requirements');

$valid_picostrap = $validator->validate_zip($picostrap_zip, [
    'framework' => 'picostrap',
    'checksum' => $picostrap_checksum,
]);
lcfa_theme_assert_true(
    !empty($valid_picostrap['ok']),
    'valid Picostrap ZIP should pass validation: ' . json_encode($valid_picostrap, JSON_UNESCAPED_SLASHES)
);
lcfa_theme_assert_same('picostrap', $valid_picostrap['framework'] ?? '', 'validator should identify Picostrap from the Template header');
lcfa_theme_assert_same(hash('sha256', $picostrap_bundle), $valid_picostrap['framework_assets']['bundle_sha256'] ?? '', 'validator should checksum the packaged Picostrap bundle');
lcfa_theme_assert_true(in_array('css-output/bundle.css', $valid_picostrap['required_files'] ?? [], true), 'Picostrap packages should require a compiled bundle');
lcfa_theme_assert_false(in_array('public/styles/tailwind.css', $valid_picostrap['required_files'] ?? [], true), 'Picostrap packages should not require Tailwind source CSS');

$catalog_framework_mismatch = $validator->validate_zip($picostrap_zip, [
    'framework' => 'picowind',
    'checksum' => $picostrap_checksum,
]);
lcfa_theme_assert_false(!empty($catalog_framework_mismatch['ok']), 'catalog and child-theme framework mismatches should fail validation');

$invalid_css_verification_zip = lcfa_theme_create_zip([
    'css_verification' => [
        'required_fragments' => 'not-an-array',
    ],
]);
$invalid_css_verification = $validator->validate_zip($invalid_css_verification_zip, ['checksum' => hash_file('sha256', $invalid_css_verification_zip)]);
lcfa_theme_assert_false(!empty($invalid_css_verification['ok']), 'CSS verification fragments must use an array schema');

$example_catalog_path = dirname(__DIR__, 2) . '/examples/theme-library/catalog.json';
$example_catalog = json_decode((string) file_get_contents($example_catalog_path), true);
foreach ((array) ($example_catalog['themes'] ?? []) as $example_theme) {
    $example_slug = (string) ($example_theme['slug'] ?? '');
    $example_zip_path = dirname(__DIR__, 2) . '/examples/theme-library/themes/' . $example_slug . '/' . $example_slug . '.zip';
    lcfa_theme_assert_true(is_file($example_zip_path), 'example Theme Library ZIP should exist for beta fallback catalog testing: ' . $example_slug);
    $example_framework = (string) ($example_theme['framework'] ?? '');
    lcfa_theme_assert_true(in_array($example_framework, ['picowind', 'picostrap'], true), 'example Theme Library catalog entries should declare a supported framework: ' . $example_slug);
    lcfa_theme_assert_same($example_framework === 'picostrap' ? 'bootstrap' : 'tailwind', (string) ($example_theme['css'] ?? ''), 'example Theme Library catalog entries should declare the matching CSS stack: ' . $example_slug);
    lcfa_theme_assert_same($example_framework === 'picostrap' ? 'bootstrap' : 'daisyui', (string) ($example_theme['ui'] ?? ''), 'example Theme Library catalog entries should declare the matching UI stack: ' . $example_slug);
    lcfa_theme_assert_same((string) ($example_theme['checksum'] ?? ''), 'sha256:' . hash_file('sha256', $example_zip_path), 'example catalog checksum should match the packaged ZIP: ' . $example_slug);
    $example_validation = $validator->validate_zip($example_zip_path, $example_theme);
    lcfa_theme_assert_true(!empty($example_validation['ok']), 'example Theme Library ZIP should pass validation: ' . $example_slug);

    if ($example_slug === 'asteria-search') {
        $asteria_zip = new ZipArchive();
        lcfa_theme_assert_true($asteria_zip->open($example_zip_path) === true, 'Asteria package should be readable for navigation regression checks.');
        $asteria_header = (string) $asteria_zip->getFromName('asteria-search/starter-data/header.html');
        $asteria_footer = (string) $asteria_zip->getFromName('asteria-search/starter-data/footer.html');
        $asteria_functions = (string) $asteria_zip->getFromName('asteria-search/functions.php');
        $asteria_zip->close();

        foreach (['asteria-page', 'thesis', 'engagements', 'work'] as $anchor) {
            $partial = $anchor === 'asteria-page' ? $asteria_header . $asteria_footer : $asteria_header;
            lcfa_theme_assert_true(str_contains($partial, 'href="/#' . $anchor . '"'), 'Asteria navigation should use a homepage-rooted fallback for #' . $anchor . '.');
            lcfa_theme_assert_false(str_contains($partial, 'href="#' . $anchor . '"'), 'Asteria navigation must not resolve #' . $anchor . ' against the current inner page.');
        }

        lcfa_theme_assert_true(str_contains($asteria_functions, "add_filter('lc_modify_header_content'"), 'Asteria should resolve header anchors through the WordPress home URL.');
        lcfa_theme_assert_true(str_contains($asteria_functions, "add_filter('lc_modify_footer_content'"), 'Asteria should resolve footer anchors through the WordPress home URL.');
        lcfa_theme_assert_true(str_contains($asteria_functions, "home_url('/#' . \$anchor)"), 'Asteria home anchors should support WordPress subdirectory installations.');
    }
}

$unsupported_parent_zip = lcfa_theme_create_zip([], [], [
    'style.css' => "/*\nTheme Name: Sample Theme\nTemplate: twentytwentyfour\n*/",
]);
$unsupported_parent = $validator->validate_zip($unsupported_parent_zip, ['checksum' => hash_file('sha256', $unsupported_parent_zip)]);
lcfa_theme_assert_false(!empty($unsupported_parent['ok']), 'child themes outside Picowind and Picostrap should fail validation');

$missing_picostrap_bundle_zip = lcfa_theme_create_zip([
    'theme' => ['framework' => 'picostrap'],
], [], [
    'style.css' => "/*\nTheme Name: Sample Picostrap\nTemplate: picostrap5\n*/",
    'page-templates/empty.php' => "<?php get_header(); the_content(); get_footer();\n",
    'views/page-templates/empty.twig' => null,
    'public/styles/presets/daisyui.css' => null,
    'public/styles/tailwind.css' => null,
    'sass/_theme_variables.scss' => '$primary: #7047a8;',
    'sass/_custom.scss' => '.houseflow-header { position: sticky; }',
    'js/bootstrap.bundle.min.js' => '/* Bootstrap 5 bundle */',
    'js/custom.js' => '/* Hearthline interactions */',
]);
$missing_picostrap_bundle = $validator->validate_zip($missing_picostrap_bundle_zip, [
    'framework' => 'picostrap',
    'checksum' => hash_file('sha256', $missing_picostrap_bundle_zip),
]);
lcfa_theme_assert_false(!empty($missing_picostrap_bundle['ok']), 'Picostrap packages without a compiled bundle should fail validation');

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
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/theme-library/build'"), 'REST API should register the admin-only Theme Library build endpoint');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/theme-library/build/pending'"), 'REST API should register the protected pending remote build endpoint');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/theme-library/build/complete'"), 'REST API should register the protected verified remote build completion endpoint');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, 'can_theme_library_build'), 'Remote Theme Library build routes should require their combined write and cache permission');
$build_permission_start = is_string($rest_source) ? strpos($rest_source, 'public function can_theme_library_build') : false;
$build_permission_end = $build_permission_start !== false ? strpos($rest_source, 'public function can_seo', $build_permission_start) : false;
$build_permission_source = $build_permission_start !== false && $build_permission_end !== false
    ? substr($rest_source, $build_permission_start, $build_permission_end - $build_permission_start)
    : '';
lcfa_theme_assert_true($build_permission_source !== '' && !str_contains($build_permission_source, 'current_user_can') && !str_contains($build_permission_source, 'has_valid_mcp_token'), 'Remote Theme Library build completion should require a scoped paired session instead of an administrator cookie or legacy MCP token');
lcfa_theme_assert_true(is_string($rest_source) && str_contains($rest_source, "'permission_callback' => [\$this, 'can_manage']"), 'Theme Library REST endpoints should use admin-only can_manage permission');
lcfa_theme_assert_true(is_string($ability_source) && !str_contains($ability_source, 'theme-library'), 'Theme Library endpoints should not be MCP-public abilities in v1');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, "'status'     => 'failed'"), 'failed Theme Library imports should be tracked for rollback visibility');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, 'ensure_picowind_runtime'), 'Theme Library import should initialize the Picowind/WindPress runtime');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, 'windpress_source_css_ready'), 'Theme Library import should identify Tailwind source CSS before compilation');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, "'status'      => 'build_required'"), 'Theme Library import should expose an explicit build-required state');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, 'get_compiled_cache_state'), 'Theme Library import should verify the persistent WindPress cache after building');
lcfa_theme_assert_true(is_string($importer_source) && str_contains($importer_source, "'previous_import'"), 'Theme Library force re-import should retain the previous import state for chained rollback');
lcfa_theme_assert_true(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-rollback.php'), '$previous_import'), 'Theme Library rollback should restore the previous import metadata when available');
lcfa_theme_assert_true(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-rollback.php'), "'rolled_back'"), 'Theme Library rollback should persist a rolled-back import state');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'THEME_LIBRARY_PREVIEW_TRANSIENT_PREFIX'), 'Theme Library UI should remember a successful package preview per user');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'Preview and validate the package before installing it.'), 'Theme Library UI should require preview before the first child-theme install');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, '$operation === \'import\' && $show_force'), 'Theme Library UI should show force update only for existing imports');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-framework-filter="all"'), 'Theme Library should expose a dedicated framework filter');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-framework="'), 'Theme Library cards should expose their normalized framework');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-category-filter="all"'), 'Theme Library should expose a separate theme-type filter');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'data-lcfa-theme-category='), 'Theme Library cards should expose their filter category');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'get_theme_library_framework_profile'), 'Theme Library cards should render a prominent Picowind or Picostrap identity');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'No themes match these filters.'), 'Theme Library should explain empty filter results');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'PHP upgrade required'), 'Theme Library UI should show a guided PHP compatibility blocker');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'get_framework_prerequisites'), 'Theme Library UI should check framework PHP requirements before enabling install');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, '$needs_build_capability'), 'Theme Library UI should probe the local build bridge only when a CSS build is pending');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'Finish Tailwind CSS on this remote site'), 'Theme Library UI should replace an unavailable local compiler with an actionable remote WindPress step');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, "'verify_native_build'"), 'Theme Library UI should let administrators verify a native WindPress cache after generation');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'admin.php?page=windpress#/settings/performance'), 'Theme Library remote build guidance should link directly to WindPress Performance');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, '$child_active'), 'Theme Library UI should distinguish an installed child theme from the currently active child theme');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'Current child theme'), 'Theme Library UI should not offer to activate the child theme that is already active');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'Update child theme'), 'Theme Library UI should offer a validated package update when the catalog version is newer');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, '$child_update_available'), 'Theme Library UI should compare installed and catalog child-theme versions');
lcfa_theme_assert_true(is_string($admin_source) && str_contains($admin_source, 'get_theme_library_rollback_history'), 'Theme Library UI should expose older unresolved import audits for chained rollback');

@unlink($valid_zip);
@unlink($picostrap_zip);
@unlink($invalid_css_verification_zip);
@unlink($unsupported_parent_zip);
@unlink($missing_picostrap_bundle_zip);
@unlink($inline_shell_zip);
@unlink($missing_media_zip);
@unlink($missing_css_import_zip);
@unlink($traversal_zip);
@unlink($missing_zip);

echo "PASS\n";
