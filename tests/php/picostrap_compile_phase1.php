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

if (!function_exists('remove_theme_mod')) {
    function remove_theme_mod(string $name): void {
        unset($GLOBALS['lcfa_test_theme_mods'][$name]);
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

if (!function_exists('picostrap_get_scss_variables_array')) {
    function picostrap_get_scss_variables_array(): array {
        return [
            'colors' => [
                'primary' => ['type' => 'color'],
                'body-bg' => ['type' => 'color'],
            ],
            'components' => [
                'enable-rounded' => ['type' => 'boolean'],
                'border-radius' => ['type' => 'text'],
            ],
        ];
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
    lcfa_assert_true(!empty($manifest['source_fingerprint']), 'Manifest should fingerprint Customizer variables and Sass sources');
    lcfa_assert_true(isset($manifest['synchronization']['status']), 'Manifest should report bundle synchronization');
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

function test_picostrap_apply_is_atomic_and_rollbackable(): void {
    $service = lcfa_make_design_system_service_for_picostrap();
    $bundle_path = get_stylesheet_directory() . '/css-output/bundle.css';
    file_put_contents($bundle_path, 'body{color:#111;}');
    $GLOBALS['lcfa_test_theme_mods']['SCSSvar_primary'] = '#111111';
    $GLOBALS['lcfa_test_theme_mods']['css_bundle_version_number'] = 30;
    unset(
        $GLOBALS['lcfa_test_theme_mods']['lcfa_picostrap_compiled_source_fingerprint'],
        $GLOBALS['lcfa_test_theme_mods']['lcfa_picostrap_compiled_at']
    );

    $tokens = [
        'action' => 'design_system_apply',
        'framework' => 'picostrap',
        'colors' => ['primary' => '#224466'],
        'components' => ['enable_rounded' => true],
    ];
    $preview = $service->run($tokens, true);
    $manifest = (array) ($preview['data']['compile_manifest'] ?? []);

    lcfa_assert_true(!empty($preview['ok']), 'Picostrap transaction preview should succeed');
    lcfa_assert_same('#111111', get_theme_mod('SCSSvar_primary'), 'Preview must not change Customizer variables');
    lcfa_assert_same('#224466', $manifest['theme_mods']['SCSSvar_primary'] ?? '', 'Preview manifest should contain proposed variables');

    $without_bundle = $service->run($tokens, false);
    lcfa_assert_true(empty($without_bundle['ok']), 'Picostrap apply without compiled CSS should be rejected');
    lcfa_assert_same('#111111', get_theme_mod('SCSSvar_primary'), 'Rejected apply must not change Customizer variables');

    $apply = $service->run($tokens + [
        'compiled_css' => 'body{color:#224466;}',
        'compiled_source_fingerprint' => (string) ($manifest['source_fingerprint'] ?? ''),
        'expected_state_fingerprint' => (string) ($preview['data']['current_state_fingerprint'] ?? ''),
    ], false);

    lcfa_assert_true(!empty($apply['ok']), 'Compiled Picostrap transaction should apply');
    lcfa_assert_same('#224466', get_theme_mod('SCSSvar_primary'), 'Atomic apply should update the Customizer variable');
    lcfa_assert_same('body{color:#224466;}', (string) file_get_contents($bundle_path), 'Atomic apply should replace the bundle');
    lcfa_assert_same('synchronized', $apply['data']['synchronization_after']['status'] ?? '', 'Atomic apply should report synchronized state');
    lcfa_assert_true(!empty($apply['data']['picostrap_design_system_rollback']), 'Atomic apply should return a rollback snapshot');

    $restore = $service->restore((array) $apply['data']['picostrap_design_system_rollback'], false);
    lcfa_assert_true(!empty($restore['ok']), 'Picostrap design-system rollback should succeed');
    lcfa_assert_same('#111111', get_theme_mod('SCSSvar_primary'), 'Rollback should restore the previous Customizer variable');
    lcfa_assert_same('body{color:#111;}', (string) file_get_contents($bundle_path), 'Rollback should restore the previous bundle');
    lcfa_assert_same(30, (int) get_theme_mod('css_bundle_version_number'), 'Rollback should restore the previous bundle version');
}

function test_picostrap_apply_rejects_stale_and_unsafe_payloads(): void {
    $service = lcfa_make_design_system_service_for_picostrap();
    $preview = $service->run([
        'framework' => 'picostrap',
        'scss_variables' => [
            'primary' => '#445566',
            'unknown-variable' => '10px',
            'body-bg' => '#fff; @import "evil"',
        ],
    ], true);

    lcfa_assert_same('#445566', $preview['data']['compile_manifest']['theme_mods']['SCSSvar_primary'] ?? '', 'Registered raw variable should be accepted');
    lcfa_assert_true(isset($preview['data']['rejected_scss_variables']['SCSSvar_unknown-variable']), 'Unknown raw variable should be rejected');
    lcfa_assert_true(isset($preview['data']['rejected_scss_variables']['SCSSvar_body-bg']), 'Unsafe Sass value should be rejected');

    $stale = $service->run([
        'framework' => 'picostrap',
        'colors' => ['primary' => '#445566'],
        'compiled_css' => 'body{color:#445566;}',
        'compiled_source_fingerprint' => (string) ($preview['data']['proposed_source_fingerprint'] ?? ''),
        'expected_state_fingerprint' => str_repeat('0', 64),
    ], false);

    lcfa_assert_true(empty($stale['ok']), 'A stale Picostrap apply must be rejected');
}

function run_all_tests(): void {
    test_manifest_uses_active_stylesheet_target();
    test_store_writes_bundle_and_bumps_version();
    test_compile_source_rejects_parent_escape();
    test_compile_source_reads_parent_scss_file();
    test_store_bundle_endpoint_returns_bundle_metadata();
    test_picostrap_apply_is_atomic_and_rollbackable();
    test_picostrap_apply_rejects_stale_and_unsafe_payloads();
    echo "PASS\n";
}

run_all_tests();
