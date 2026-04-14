<?php

declare(strict_types=1);

require_once __DIR__ . '/design_system_apply_phase1.php';

if (!class_exists('WP_REST_Request')) {
    final class WP_REST_Request {
        private array $params;

        public function __construct(string $method = 'GET', string $route = '', array $params = []) {
            $this->params = $params;
        }

        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }

        public function get_json_params(): array {
            return $this->params;
        }

        public function get_params(): array {
            return $this->params;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    final class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

if (!class_exists('WP_REST_Server')) {
    final class WP_REST_Server {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args): bool {
        return true;
    }
}

if (!function_exists('get_theme_mods')) {
    function get_theme_mods(): array {
        return $GLOBALS['lcfa_test_theme_mods'] ?? [];
    }
}

if (!function_exists('get_stylesheet_directory_uri')) {
    function get_stylesheet_directory_uri(): string {
        return 'http://localhost:8887/wp-content/themes/' . $GLOBALS['lcfa_test_stylesheet'];
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri(): string {
        return 'http://localhost:8887/wp-content/themes/' . $GLOBALS['lcfa_test_template'];
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return false;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int {
        return 1;
    }
}

if (!function_exists('ps_get_main_sass')) {
    function ps_get_main_sass(): string {
        return '$primary: #ff2d55; @import "main";';
    }
}

if (!function_exists('picostrap_get_css_optional_subfolder_name')) {
    function picostrap_get_css_optional_subfolder_name(): string {
        return 'css-output/';
    }
}

if (!function_exists('picostrap_get_complete_css_filename')) {
    function picostrap_get_complete_css_filename(): string {
        return 'bundle.css';
    }
}

if (!function_exists('lcfa_assert_contains')) {
    function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . ' Missing `' . $needle . '` in `' . $haystack . '`.');
        }
    }
}

require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-manifest.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-bundle-store.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-service.php';
require_once LCFA_DIR . 'includes/class-lcfa-context-builder.php';
require_once LCFA_DIR . 'includes/class-lcfa-prompt-suggester.php';
require_once LCFA_DIR . 'includes/class-lcfa-genesis-planner.php';
require_once LCFA_DIR . 'includes/class-lcfa-rest-api.php';

// Reset theme globals after design_system_apply_phase1 side effects.
$GLOBALS['lcfa_test_stylesheet'] = 'picostrap-child';
$GLOBALS['lcfa_test_template'] = 'picostrap5';
$GLOBALS['lcfa_test_theme_name'] = 'Picostrap Child';

@mkdir(get_stylesheet_directory() . '/sass/bootstrap', 0777, true);
@mkdir(get_template_directory() . '/sass/bootstrap', 0777, true);
@mkdir(get_stylesheet_directory() . '/css-output', 0777, true);
file_put_contents(get_stylesheet_directory() . '/sass/main.scss', '@import "bootstrap/functions"; body { color: $primary; }');
file_put_contents(get_template_directory() . '/sass/bootstrap/_functions.scss', '@function tint-color($color, $weight) { @return $color; }');

$GLOBALS['lcfa_test_theme_mods']['SCSSvar_primary'] = '#ff2d55';
$GLOBALS['lcfa_test_theme_mods']['css_bundle_version_number'] = 22;

function lcfa_make_picostrap_rest_api(): LCFA_Rest_Api {
    $environment = new LCFA_Environment();
    $inventory = new LCFA_Inventory($environment);
    $windpress = new LCFA_WindPress_Bridge($environment);
    $theme_files = new LCFA_Theme_Files_Bridge($environment);
    $local_mcp = new LCFA_Local_MCP_Bridge($environment);
    $remote = new LCFA_Remote_Client();
    $context = new LCFA_Context_Builder($environment, $inventory, $windpress, $local_mcp);
    $prompt = new LCFA_Prompt_Suggester($environment, $inventory);
    $genesis = new LCFA_Genesis_Planner($environment, $inventory);
    $command_deck = new LCFA_Command_Deck($environment, $inventory, $windpress, $theme_files, $local_mcp, $remote);

    return new LCFA_Rest_Api($environment, $inventory, $windpress, $theme_files, $local_mcp, $context, $command_deck, $prompt, $genesis);
}

function test_manifest_uses_active_stylesheet_target(): void {
    $manifest = (new LCFA_Picostrap_Compile_Manifest(new LCFA_Environment()))->build();

    lcfa_assert_same('picostrap', $manifest['framework'], 'Manifest should target Picostrap');
    lcfa_assert_same('picostrap-child', $manifest['stylesheet'], 'Manifest should target the active stylesheet');
    lcfa_assert_same('picostrap5', $manifest['template'], 'Manifest should expose the parent template');
    lcfa_assert_same('wp-content/themes/picostrap-child/css-output/bundle.css', $manifest['target_bundle_relative_path'], 'Manifest should point at child-theme bundle');
    lcfa_assert_true(!empty($manifest['main_sass']), 'Manifest should expose main Sass');
}

function test_store_writes_bundle_and_bumps_version(): void {
    $store = new LCFA_Picostrap_Bundle_Store(new LCFA_Environment());
    $before = (int) get_theme_mod('css_bundle_version_number', 0);

    $result = $store->store('body{color:#123456;}');

    lcfa_assert_true(!empty($result['ok']), 'Bundle store should succeed');
    lcfa_assert_true(is_file($result['bundle_path']), 'Stored bundle should exist');
    lcfa_assert_true($result['bundle_version'] > $before, 'Bundle store should bump version');
    lcfa_assert_same($result['bundle_version'], (int) get_theme_mod('css_bundle_version_number', 0), 'Theme mod version should match store result');
}

function test_compile_source_rejects_parent_escape(): void {
    $api = lcfa_make_picostrap_rest_api();
    $request = new WP_REST_Request();
    $request->set_param('import_path', '../wp-config.php');

    $response = $api->get_picostrap_compile_source($request);

    lcfa_assert_same(400, $response->get_status(), 'Compile source endpoint should reject path traversal');
}

function test_compile_source_reads_parent_scss_file(): void {
    $api = lcfa_make_picostrap_rest_api();
    $request = new WP_REST_Request();
    $request->set_param('import_path', 'bootstrap/_functions.scss');

    $response = $api->get_picostrap_compile_source($request);
    $payload = $response->get_data()['result'] ?? [];

    lcfa_assert_same(200, $response->get_status(), 'Compile source endpoint should read valid SCSS files');
    lcfa_assert_true(!empty($payload['ok']), 'Compile source should succeed for a valid SCSS file');
    lcfa_assert_same('parent', $payload['origin'], 'Compile source should resolve to the parent theme when missing in child');
}

function test_store_bundle_endpoint_returns_bundle_metadata(): void {
    $api = lcfa_make_picostrap_rest_api();
    $request = new WP_REST_Request();
    $request->set_param('css', 'body{background:#fff8ef;}');

    $response = $api->store_picostrap_bundle($request);
    $payload = $response->get_data()['result'] ?? [];

    lcfa_assert_same(200, $response->get_status(), 'Bundle endpoint should return success');
    lcfa_assert_true(!empty($payload['ok']), 'Bundle endpoint should store CSS');
    lcfa_assert_contains('css-output/bundle.css?ver=', $payload['bundle_url'] ?? '', 'Bundle endpoint should expose bundle URL');
}

function run_all_tests(): void {
    test_manifest_uses_active_stylesheet_target();
    test_store_writes_bundle_and_bumps_version();
    test_compile_source_rejects_parent_escape();
    test_compile_source_reads_parent_scss_file();
    test_store_bundle_endpoint_returns_bundle_metadata();
    echo "PASS\n";
}

run_all_tests();
