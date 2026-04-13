<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_TEST_TMP', sys_get_temp_dir() . '/lcfa-phase1-tests');

if (!is_dir(LCFA_TEST_TMP)) {
    mkdir(LCFA_TEST_TMP, 0777, true);
}

final class WP_Post {
    public int $ID = 0;
    public string $post_type = '';
    public string $post_status = '';
    public string $post_title = '';
    public string $post_name = '';
    public string $post_content = '';
    public string $post_modified_gmt = '';

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

final class WP_Error {
    private string $code;
    private string $message;

    public function __construct(string $code = '', string $message = '') {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_message(): string {
        return $this->message;
    }
}

final class WP_REST_Request {
    private array $params;
    private array $headers;

    public function __construct(array $params = [], array $headers = []) {
        $this->params = $params;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function get_json_params(): array {
        return $this->params;
    }

    public function get_params(): array {
        return $this->params;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_header(string $key): string {
        return (string) ($this->headers[strtolower($key)] ?? '');
    }
}

final class WP_REST_Response {
    private array $data;
    private int $status;

    public function __construct(array $data = [], int $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data(): array {
        return $this->data;
    }

    public function get_status(): int {
        return $this->status;
    }
}

final class WP_Theme {
    private array $data;

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function get(string $field): string {
        return (string) ($this->data[$field] ?? '');
    }

    public function get_stylesheet(): string {
        return (string) ($this->data['stylesheet'] ?? '');
    }

    public function get_template(): string {
        return (string) ($this->data['template'] ?? '');
    }

    public function parent() {
        return null;
    }
}

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_posts'] = [];
$GLOBALS['lcfa_test_post_meta'] = [];
$GLOBALS['lcfa_test_next_post_id'] = 100;
$GLOBALS['lcfa_test_theme_root'] = LCFA_TEST_TMP . '/wp-content/themes';
$GLOBALS['lcfa_test_stylesheet'] = 'picostrap-child';
$GLOBALS['lcfa_test_template'] = 'picostrap-child';
$GLOBALS['lcfa_test_uploads'] = LCFA_TEST_TMP . '/wp-content/uploads';
$GLOBALS['lcfa_test_remote_get_map'] = [];
$GLOBALS['lcfa_test_force_empty_edit_link'] = false;

@mkdir($GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet'], 0777, true);
@mkdir($GLOBALS['lcfa_test_uploads'], 0777, true);

function __(string $text, string $domain = ''): string {
    return $text;
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

function current_time(string $type = 'mysql', bool $gmt = false): string {
    return $type === 'mysql' ? gmdate('Y-m-d H:i:s') : gmdate('c');
}

function wp_generate_password(int $length = 12, bool $special_chars = false, bool $extra_special_chars = false): string {
    return str_repeat('a', $length);
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function wp_parse_args($args, $defaults = []): array {
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (!is_array($args)) {
        parse_str((string) $args, $parsed);
        $args = $parsed;
    }

    if (is_object($defaults)) {
        $defaults = get_object_vars($defaults);
    }

    return array_merge((array) $defaults, (array) $args);
}

function apply_filters(string $hook_name, $value) {
    return $value;
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function add_option(string $key, $value): bool {
    if (!array_key_exists($key, $GLOBALS['lcfa_test_options'])) {
        $GLOBALS['lcfa_test_options'][$key] = $value;
    }

    return true;
}

function delete_option(string $key): bool {
    unset($GLOBALS['lcfa_test_options'][$key]);
    return true;
}

function sanitize_key(string $value): string {
    $value = strtolower($value);
    return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function sanitize_text_field($value): string {
    return trim((string) $value);
}

function sanitize_textarea_field($value): string {
    return trim((string) $value);
}

function sanitize_title($value): string {
    $value = strtolower(trim((string) $value));
    $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-');
}

function sanitize_file_name(string $value): string {
    return (string) preg_replace('/[^A-Za-z0-9\.\-_]/', '-', $value);
}

function absint($value): int {
    return abs((int) $value);
}

function rest_sanitize_boolean($value): bool {
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function wp_unslash($value) {
    return is_string($value) ? stripslashes($value) : $value;
}

function current_user_can(string $capability): bool {
    return false;
}

function esc_url_raw(string $value): string {
    return trim($value);
}

function rest_url(string $path = ''): string {
    return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function home_url(string $path = ''): string {
    return 'https://example.test/' . ltrim($path, '/');
}

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function wp_remote_get(string $url, array $args = []) {
    if (isset($GLOBALS['lcfa_test_remote_get_map'][$url])) {
        return $GLOBALS['lcfa_test_remote_get_map'][$url];
    }

    return new WP_Error('http_missing', 'No fake HTTP response registered for ' . $url);
}

function wp_remote_retrieve_response_code($response): int {
    if ($response instanceof WP_Error) {
        return 0;
    }

    return (int) ($response['response']['code'] ?? 0);
}

function wp_remote_retrieve_body($response): string {
    if ($response instanceof WP_Error) {
        return '';
    }

    return (string) ($response['body'] ?? '');
}

function get_plugins(): array {
    return [
        'livecanvas/livecanvas.php' => ['TextDomain' => 'livecanvas'],
        'windpress/windpress.php'   => ['TextDomain' => 'windpress'],
    ];
}

function is_plugin_active(string $plugin_file): bool {
    return in_array($plugin_file, ['livecanvas/livecanvas.php', 'windpress/windpress.php'], true);
}

function wp_get_themes(): array {
    $theme = wp_get_theme();

    return [
        $theme->get_stylesheet() => $theme,
    ];
}

function get_post($post_id): ?WP_Post {
    return $GLOBALS['lcfa_test_posts'][(int) $post_id] ?? null;
}

function get_post_field(string $field, int $post_id, string $context = 'display') {
    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return '';
    }

    return $post->{$field} ?? '';
}

function get_the_title(int $post_id): string {
    $post = get_post($post_id);
    return $post instanceof WP_Post ? $post->post_title : '';
}

function get_edit_post_link(int $post_id, string $context = 'display'): string {
    if (!empty($GLOBALS['lcfa_test_force_empty_edit_link'])) {
        return '';
    }

    return 'https://example.test/wp-admin/post.php?post=' . $post_id . '&action=edit';
}

function admin_url(string $path = ''): string {
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function get_permalink(int $post_id): string {
    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return '';
    }

    return 'https://example.test/' . trim($post->post_name, '/') . '/';
}

function update_post_meta(int $post_id, string $meta_key, $meta_value): bool {
    $GLOBALS['lcfa_test_post_meta'][$post_id][$meta_key] = $meta_value;
    return true;
}

function get_post_meta(int $post_id, string $meta_key = '', bool $single = false) {
    $all = $GLOBALS['lcfa_test_post_meta'][$post_id] ?? [];

    if ($meta_key === '') {
        return $all;
    }

    $value = $all[$meta_key] ?? '';
    return $single ? $value : [$value];
}

function get_posts(array $args = []): array {
    $post_type = $args['post_type'] ?? '';
    $posts = array_values(array_filter($GLOBALS['lcfa_test_posts'], static function (WP_Post $post) use ($post_type, $args): bool {
        if ($post_type !== '' && $post->post_type !== $post_type) {
            return false;
        }

        if (isset($args['meta_key'], $args['meta_value'])) {
            $meta_value = $GLOBALS['lcfa_test_post_meta'][$post->ID][$args['meta_key']] ?? null;
            if ((string) $meta_value !== (string) $args['meta_value']) {
                return false;
            }
        }

        return true;
    }));

    $limit = (int) ($args['posts_per_page'] ?? count($posts));

    if ($limit > -1) {
        $posts = array_slice($posts, 0, $limit);
    }

    return $posts;
}

final class WP_Query {
    public int $found_posts = 0;

    public function __construct(array $args = []) {
        $this->found_posts = count(get_posts($args));
    }
}

function wp_insert_post(array $postarr, bool $wp_error = false) {
    $post_id = $GLOBALS['lcfa_test_next_post_id']++;
    $slug = $postarr['post_name'] !== '' ? $postarr['post_name'] : sanitize_title($postarr['post_title'] ?? 'untitled');

    $post = new WP_Post([
        'ID'                => $post_id,
        'post_type'         => (string) ($postarr['post_type'] ?? 'page'),
        'post_status'       => (string) ($postarr['post_status'] ?? 'draft'),
        'post_title'        => (string) ($postarr['post_title'] ?? ''),
        'post_name'         => $slug,
        'post_content'      => (string) ($postarr['post_content'] ?? ''),
        'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
    ]);

    $GLOBALS['lcfa_test_posts'][$post_id] = $post;

    return $post_id;
}

function wp_update_post(array $postarr, bool $wp_error = false) {
    $post_id = (int) ($postarr['ID'] ?? 0);
    $post = get_post($post_id);

    if (!$post instanceof WP_Post) {
        return new WP_Error('missing_post', 'Missing post');
    }

    foreach (['post_title', 'post_name', 'post_status', 'post_content'] as $field) {
        if (array_key_exists($field, $postarr)) {
            $post->{$field} = (string) $postarr[$field];
        }
    }

    $post->post_modified_gmt = gmdate('Y-m-d H:i:s');
    $GLOBALS['lcfa_test_posts'][$post_id] = $post;

    return $post_id;
}

function wp_text_diff(string $left, string $right, array $args = []): string {
    return $left === $right ? '' : 'diff';
}

function wp_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function trailingslashit(string $path): string {
    return rtrim($path, '/\\') . '/';
}

function untrailingslashit(string $path): string {
    return rtrim($path, '/\\');
}

function wp_mkdir_p(string $path): bool {
    if (is_dir($path)) {
        return true;
    }

    return mkdir($path, 0777, true);
}

function wp_get_upload_dir(): array {
    return [
        'basedir' => $GLOBALS['lcfa_test_uploads'],
    ];
}

function wp_get_theme(): WP_Theme {
    return new WP_Theme([
        'Name'       => 'Picostrap Child',
        'stylesheet' => $GLOBALS['lcfa_test_stylesheet'],
        'template'   => $GLOBALS['lcfa_test_template'],
    ]);
}

function get_stylesheet_directory(): string {
    return $GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet'];
}

function get_template_directory(): string {
    return $GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_template'];
}

function get_theme_root(string $stylesheet = ''): string {
    return $GLOBALS['lcfa_test_theme_root'];
}

function lc_post_is_using_livecanvas(): bool {
    return true;
}

function lc_get_framework_slug(): string {
    return 'bootstrap-5';
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected `' . var_export($expected, true) . '`, got `' . var_export($actual, true) . '`.');
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing `' . $needle . '` in `' . $haystack . '`.');
    }
}

require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-environment.php';
require LCFA_DIR . 'includes/class-lcfa-inventory.php';
require LCFA_DIR . 'includes/class-lcfa-windpress-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-theme-files-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-local-mcp-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-connection-tester.php';
require LCFA_DIR . 'includes/class-lcfa-remote-client.php';
require LCFA_DIR . 'includes/class-lcfa-context-builder.php';
require LCFA_DIR . 'includes/class-lcfa-prompt-suggester.php';
require LCFA_DIR . 'includes/class-lcfa-genesis-planner.php';
require LCFA_DIR . 'includes/class-lcfa-command-deck.php';
require LCFA_DIR . 'includes/class-lcfa-rest-api.php';

$environment = new LCFA_Environment();
$inventory = new LCFA_Inventory($environment);
$windpress_bridge = new LCFA_WindPress_Bridge($environment);
$theme_files_bridge = new LCFA_Theme_Files_Bridge($environment);
$local_mcp_bridge = new LCFA_Local_MCP_Bridge($environment);
$remote_client = new LCFA_Remote_Client();
$context_builder = new LCFA_Context_Builder($environment, $inventory, $windpress_bridge, $local_mcp_bridge);
$prompt_suggester = new LCFA_Prompt_Suggester($environment, $inventory);
$genesis_planner = new LCFA_Genesis_Planner($environment, $inventory);
$command_deck = new LCFA_Command_Deck($environment, $inventory, $windpress_bridge, $theme_files_bridge, $local_mcp_bridge, $remote_client);
$rest_api = new LCFA_Rest_Api($environment, $inventory, $windpress_bridge, $theme_files_bridge, $local_mcp_bridge, $context_builder, $command_deck, $prompt_suggester, $genesis_planner);

LCFA_Settings::update([
    'permission_profile'  => 'advanced_templates',
    'allow_file_fallback' => true,
]);

$suggestion = $prompt_suggester->suggest([
    'user_prompt' => 'Crea una landing page Tailwind con hero, feature e CTA',
]);

lcfa_assert_true(!empty($suggestion['ok']), 'prompt suggestion should succeed for page requests');
lcfa_assert_same('page_upsert', $suggestion['suggested_payload']['action'] ?? '', 'page creation prompts should keep page_upsert even when Tailwind is mentioned');

$page_result = $command_deck->execute([
    'action'  => 'page_upsert',
    'title'   => 'Landing Page 1',
    'slug'    => 'landing-page-1',
    'status'  => 'draft',
    'content' => '<main>Hero</main>',
]);

lcfa_assert_true($page_result['ok'] === true, 'page_upsert should succeed');
lcfa_assert_same('page', $page_result['target_type'], 'page_upsert should target page');
lcfa_assert_same(100, $page_result['target_id'], 'page_upsert should create the first fake post');
lcfa_assert_same('https://example.test/landing-page-1/', $page_result['frontend_url'] ?? '', 'page_upsert should return frontend_url');
lcfa_assert_contains('post.php?post=100&action=edit', $page_result['edit_url'] ?? '', 'page_upsert should return edit_url');

$updated_page_result = $command_deck->execute([
    'action'  => 'page_upsert',
    'post_id' => 100,
    'title'   => 'Landing Page 1 Updated',
    'slug'    => 'landing-page-1-updated',
    'status'  => 'publish',
    'content' => '<main>Updated Hero</main>',
]);

lcfa_assert_true($updated_page_result['ok'] === true, 'page_upsert should update an existing page when post_id is provided');
lcfa_assert_same(100, $updated_page_result['target_id'], 'page_upsert update should keep the same post id');

$GLOBALS['lcfa_test_force_empty_edit_link'] = true;
$fallback_edit_result = $command_deck->execute([
    'action'  => 'page_upsert',
    'title'   => 'Fallback Edit URL',
    'slug'    => 'fallback-edit-url',
    'status'  => 'draft',
    'content' => '<main>Fallback Edit URL</main>',
]);
$GLOBALS['lcfa_test_force_empty_edit_link'] = false;

lcfa_assert_true($fallback_edit_result['ok'] === true, 'page_upsert should still succeed when get_edit_post_link returns empty');
lcfa_assert_same('https://example.test/wp-admin/post.php?post=101&action=edit', $fallback_edit_result['edit_url'] ?? '', 'page_upsert should fall back to admin_url when get_edit_post_link is unavailable');

LCFA_Settings::update_connections(array_merge(LCFA_Settings::connection_defaults(), [
    'local_bridge_url' => 'https://example.test/wp-json/lcfa/v1/',
    'mcp_token'        => 'test-token',
]));

$GLOBALS['lcfa_test_remote_get_map']['https://example.test/wp-json/lcfa/v1/mcp/status'] = [
    'response' => ['code' => 200],
    'body'     => wp_json_encode([
        'mcp' => [
            'enabled'           => true,
            'filesystem_mode'   => 'local-theme-access',
            'preferred_client'  => 'opencode',
        ],
    ], JSON_UNESCAPED_SLASHES),
];

$connection_tester = new LCFA_Connection_Tester($environment, $local_mcp_bridge, $remote_client);
$local_mcp_reflection = new ReflectionObject($local_mcp_bridge);
$status_cache_property = $local_mcp_reflection->getProperty('status_cache');

$status_cache_property->setValue($local_mcp_bridge, [
    'available'        => false,
    'build_available'  => false,
    'local_site'       => true,
    'windpress_active' => true,
    'node_available'   => false,
    'node_version'     => '',
    'rest_reachable'   => true,
    'script_exists'    => true,
    'message'          => 'Node.js is not available to the current PHP process.',
]);

$local_checks = $connection_tester->run_checks([
    'mode' => 'local',
]);

lcfa_assert_true($local_checks['ok'] === true, 'missing Node.js in the PHP process should not block local coding-agent connections when the REST bridge is healthy');
lcfa_assert_true(!empty($local_checks['checks']['local_mcp']['skipped']), 'local_mcp should be downgraded to a non-blocking warning when only the PHP-side Node runtime is missing');

$status_cache_property->setValue($local_mcp_bridge, [
    'available'        => false,
    'build_available'  => false,
    'local_site'       => true,
    'windpress_active' => true,
    'node_available'   => true,
    'node_version'     => 'v22.0.0',
    'rest_reachable'   => true,
    'script_exists'    => false,
    'message'          => 'The local MCP CLI entrypoint was not found inside the plugin.',
]);

$missing_script_checks = $connection_tester->run_checks([
    'mode' => 'local',
]);

lcfa_assert_true($missing_script_checks['ok'] === false, 'missing MCP CLI entrypoint should remain a blocking issue');
lcfa_assert_true(empty($missing_script_checks['checks']['local_mcp']['skipped']), 'missing MCP CLI entrypoint should not be downgraded to skipped');
lcfa_assert_same('https://example.test/landing-page-1-updated/', $updated_page_result['frontend_url'] ?? '', 'page_upsert update should refresh frontend_url after slug changes');
lcfa_assert_same('<main>Updated Hero</main>', $GLOBALS['lcfa_test_posts'][100]->post_content, 'page_upsert update should persist new content');

LCFA_Settings::update([
    'permission_profile'  => 'draft_preview',
    'allow_file_fallback' => false,
]);

$theme_file = get_stylesheet_directory() . '/assets/theme.css';
if (file_exists($theme_file)) {
    unlink($theme_file);
}

$request = new WP_REST_Request([
    'root_scope' => 'stylesheet',
    'path'       => 'assets/theme.css',
    'content'    => 'body{color:red;}',
    'dry_run'    => false,
]);

$response = $rest_api->save_theme_file($request);
$payload = $response->get_data();

lcfa_assert_true(isset($payload['result']['dry_run']), 'save_theme_file should return a write result');
lcfa_assert_true($payload['result']['dry_run'] === true, 'direct theme writes should be downgraded to preview when fallback is disabled');
lcfa_assert_true(!file_exists($theme_file), 'policy downgrade should prevent writing the file');

echo "PASS\n";
