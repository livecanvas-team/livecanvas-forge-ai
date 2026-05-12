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
$GLOBALS['lcfa_test_filters'] = [];
$GLOBALS['lcfa_test_transients'] = [];

@mkdir($GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet'], 0777, true);
@mkdir($GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet'] . '/page-templates', 0777, true);
@file_put_contents($GLOBALS['lcfa_test_theme_root'] . '/' . $GLOBALS['lcfa_test_stylesheet'] . '/page-templates/empty.php', '<?php // empty template');
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

function wp_strip_all_tags(string $text): string {
    return strip_tags($text);
}

function get_post_types(array $args = [], string $output = 'names'): array {
    if ($output === 'objects') {
        return [
            'page' => (object) [
                'name'         => 'page',
                'label'        => 'Pages',
                'public'       => true,
                'show_ui'      => true,
                'has_archive'  => false,
                'show_in_rest' => true,
            ],
        ];
    }

    return ['page'];
}

function get_bloginfo(string $show = ''): string {
    if ($show === 'description') {
        return 'Test site description';
    }

    return 'Test site';
}

function get_locale(): string {
    return 'en_US';
}

function wp_timezone_string(): string {
    return 'UTC';
}

function set_transient(string $key, $value, int $expiration = 0): bool {
    $GLOBALS['lcfa_test_transients'][$key] = $value;
    return true;
}

function get_transient(string $key) {
    return $GLOBALS['lcfa_test_transients'][$key] ?? false;
}

function delete_transient(string $key): bool {
    unset($GLOBALS['lcfa_test_transients'][$key]);
    return true;
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

function lcfa_callback_id($callback): string {
    if (is_string($callback)) {
        return $callback;
    }

    if (is_array($callback)) {
        $target = is_object($callback[0]) ? spl_object_hash($callback[0]) : (string) $callback[0];
        return $target . '::' . (string) $callback[1];
    }

    if ($callback instanceof Closure) {
        return spl_object_hash($callback);
    }

    return md5(serialize($callback));
}

function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
    $GLOBALS['lcfa_test_filters'][$hook_name][$priority][lcfa_callback_id($callback)] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];

    return true;
}

function remove_filter(string $hook_name, $callback, int $priority = 10): bool {
    $id = lcfa_callback_id($callback);

    if (!isset($GLOBALS['lcfa_test_filters'][$hook_name][$priority][$id])) {
        return false;
    }

    unset($GLOBALS['lcfa_test_filters'][$hook_name][$priority][$id]);

    if (empty($GLOBALS['lcfa_test_filters'][$hook_name][$priority])) {
        unset($GLOBALS['lcfa_test_filters'][$hook_name][$priority]);
    }

    if (empty($GLOBALS['lcfa_test_filters'][$hook_name])) {
        unset($GLOBALS['lcfa_test_filters'][$hook_name]);
    }

    return true;
}

function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
    return add_filter($hook_name, $callback, $priority, $accepted_args);
}

function remove_action(string $hook_name, $callback, int $priority = 10): bool {
    return remove_filter($hook_name, $callback, $priority);
}

function apply_filters(string $hook_name, $value) {
    $args = func_get_args();

    if (empty($GLOBALS['lcfa_test_filters'][$hook_name])) {
        return $value;
    }

    ksort($GLOBALS['lcfa_test_filters'][$hook_name]);

    foreach ($GLOBALS['lcfa_test_filters'][$hook_name] as $callbacks) {
        foreach ($callbacks as $definition) {
            $call_args = array_slice($args, 0, (int) $definition['accepted_args']);
            $call_args[0] = $value;
            $value = $definition['callback'](...$call_args);
        }
    }

    return $value;
}

function do_action(string $hook_name, ...$args): void {
    if (empty($GLOBALS['lcfa_test_filters'][$hook_name])) {
        return;
    }

    ksort($GLOBALS['lcfa_test_filters'][$hook_name]);

    foreach ($GLOBALS['lcfa_test_filters'][$hook_name] as $callbacks) {
        foreach ($callbacks as $definition) {
            $call_args = array_slice($args, 0, (int) $definition['accepted_args']);
            $definition['callback'](...$call_args);
        }
    }
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

function wp_filter_post_kses($value): string {
    $value = (string) $value;
    $value = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '$1', $value) ?? $value;
    $value = preg_replace('#<input\b[^>]*?/?>#is', '', $value) ?? $value;

    return $value;
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

function delete_post_meta(int $post_id, string $meta_key): bool {
    unset($GLOBALS['lcfa_test_post_meta'][$post_id][$meta_key]);
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

        if (isset($args['meta_key'])) {
            $has_meta = array_key_exists($args['meta_key'], $GLOBALS['lcfa_test_post_meta'][$post->ID] ?? []);
            if (!$has_meta) {
                return false;
            }

            if (array_key_exists('meta_value', $args)) {
                $meta_value = $GLOBALS['lcfa_test_post_meta'][$post->ID][$args['meta_key']] ?? null;
                if ((string) $meta_value !== (string) $args['meta_value']) {
                    return false;
                }
            }
        }

        if (isset($args['meta_query']) && is_array($args['meta_query'])) {
            foreach ($args['meta_query'] as $meta_query) {
                if (!is_array($meta_query) || empty($meta_query['key'])) {
                    continue;
                }

                $meta_value = $GLOBALS['lcfa_test_post_meta'][$post->ID][$meta_query['key']] ?? null;
                $compare = strtoupper((string) ($meta_query['compare'] ?? '='));

                if ($compare === 'EXISTS' && $meta_value === null) {
                    return false;
                }

                if ($compare === '!=' && (string) $meta_value === (string) ($meta_query['value'] ?? '')) {
                    return false;
                }

                if ($compare === '=' && (string) $meta_value !== (string) ($meta_query['value'] ?? '')) {
                    return false;
                }
            }
        }

        if (isset($args['post__not_in']) && is_array($args['post__not_in'])) {
            if (in_array((int) $post->ID, array_map('intval', $args['post__not_in']), true)) {
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
    $content = apply_filters('content_save_pre', (string) ($postarr['post_content'] ?? ''));

    $post = new WP_Post([
        'ID'                => $post_id,
        'post_type'         => (string) ($postarr['post_type'] ?? 'page'),
        'post_status'       => (string) ($postarr['post_status'] ?? 'draft'),
        'post_title'        => (string) ($postarr['post_title'] ?? ''),
        'post_name'         => $slug,
        'post_content'      => $content,
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
            $post->{$field} = $field === 'post_content'
                ? apply_filters('content_save_pre', (string) $postarr[$field])
                : (string) $postarr[$field];
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

add_filter('content_save_pre', 'wp_filter_post_kses', 10, 1);
add_filter('content_filtered_save_pre', 'wp_filter_post_kses', 10, 1);

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
require LCFA_DIR . 'includes/class-lcfa-genesis-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-fallback-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-preview.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';
require LCFA_DIR . 'includes/class-lcfa-picostrap-compile-manifest.php';
require LCFA_DIR . 'includes/class-lcfa-picostrap-bundle-store.php';
require LCFA_DIR . 'includes/class-lcfa-picostrap-compile-service.php';
require LCFA_DIR . 'includes/class-lcfa-command-deck.php';
require LCFA_DIR . 'includes/class-lcfa-thread-message-actions.php';
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
$genesis_executor = new LCFA_Genesis_Executor($environment, $command_deck);
$rest_api = new LCFA_Rest_Api($environment, $inventory, $windpress_bridge, $theme_files_bridge, $local_mcp_bridge, $context_builder, $command_deck, $prompt_suggester, $genesis_planner, $genesis_executor);

LCFA_Settings::update([
    'permission_profile'  => 'advanced_templates',
    'allow_file_fallback' => true,
]);

$editor_thread = LCFA_Settings::create_thread('Editor chat');
lcfa_assert_true(method_exists($rest_api, 'send_chat_message'), 'REST API should expose send_chat_message for the editor chat flow');
lcfa_assert_true(method_exists($rest_api, 'manage_chat_thread'), 'REST API should expose manage_chat_thread for editor thread operations');
lcfa_assert_true(method_exists($rest_api, 'enqueue_agent_request'), 'REST API should expose enqueue_agent_request for frontend prompts handled by MCP agents');
lcfa_assert_true(method_exists($rest_api, 'get_agent_request_status'), 'REST API should expose get_agent_request_status for browser polling and MCP claiming');
lcfa_assert_true(method_exists($rest_api, 'complete_agent_request'), 'REST API should expose complete_agent_request for MCP agent results');
lcfa_assert_true(method_exists($rest_api, 'fail_agent_request'), 'REST API should expose fail_agent_request for MCP agent errors');
lcfa_assert_true(method_exists($rest_api, 'enqueue_command_execution'), 'REST API should expose enqueue_command_execution for async inline execution');
lcfa_assert_true(method_exists($rest_api, 'get_command_execution_status'), 'REST API should expose get_command_execution_status for async inline execution polling');
lcfa_assert_true(method_exists($rest_api, 'get_genesis_execution_plan'), 'REST API should expose get_genesis_execution_plan for Genesis execution state');
lcfa_assert_true(method_exists($rest_api, 'execute_next_genesis_task'), 'REST API should expose execute_next_genesis_task for Genesis execution');
lcfa_assert_true(method_exists($rest_api, 'execute_genesis_task'), 'REST API should expose execute_genesis_task for explicit Genesis task execution');

if (method_exists($rest_api, 'send_chat_message')) {
    $chat_response = $rest_api->send_chat_message(new WP_REST_Request([
        'thread_id'        => (string) ($editor_thread['id'] ?? 'default'),
        'user_prompt'      => 'Refresh the hero with a simpler CTA and keep the current logo.',
        'execution_target' => 'local',
        'post_id'          => 42,
        'context_post_id'  => 42,
        'target_id'        => 42,
        'variant'          => '1',
        'genesis_task_id'  => 'task-42',
        'action'           => 'update_header',
        'attachments'      => [
            [
                'kind'     => 'image',
                'name'     => 'hero-reference.png',
                'mime'     => 'image/png',
                'data_url' => 'data:image/png;base64,AAAA',
                'caption'  => 'Hero reference screenshot',
            ],
        ],
    ]));

    $chat_payload = $chat_response->get_data();
    $thread_after_chat = $chat_payload['thread'] ?? [];
    $messages_after_chat = is_array($thread_after_chat['messages'] ?? null) ? $thread_after_chat['messages'] : [];
    $last_user_message = $messages_after_chat[count($messages_after_chat) - 2] ?? [];
    $last_suggestion_message = $messages_after_chat[count($messages_after_chat) - 1] ?? [];

    lcfa_assert_same(200, $chat_response->get_status(), 'send_chat_message should return a success response for valid editor prompts');
    lcfa_assert_true(is_array($chat_payload['suggestion'] ?? null), 'send_chat_message should return a suggestion payload');
    lcfa_assert_same((string) ($editor_thread['id'] ?? 'default'), (string) ($thread_after_chat['id'] ?? ''), 'send_chat_message should return the updated selected thread');
    lcfa_assert_same('user', (string) ($last_user_message['role'] ?? ''), 'send_chat_message should append the editor prompt as a user message');
    lcfa_assert_same('image', (string) ($last_user_message['attachments'][0]['kind'] ?? ''), 'send_chat_message should persist screenshot attachments on the user message');
    lcfa_assert_contains('data:image/png;base64,AAAA', (string) ($last_user_message['attachments'][0]['data_url'] ?? ''), 'send_chat_message should keep a previewable data URL for screenshot attachments');
    lcfa_assert_same('suggestion_result', (string) ($last_suggestion_message['role'] ?? ''), 'send_chat_message should append the suggestion summary as a suggestion_result message');
    lcfa_assert_same('page_upsert', (string) ($last_suggestion_message['meta']['action'] ?? ''), 'suggestion_result chat message metadata should persist the suggested action');
    lcfa_assert_same(1, (int) ($last_suggestion_message['meta']['attachment_count'] ?? 0), 'suggestion_result metadata should keep the current attachment count');
    lcfa_assert_same('apply', (string) ($last_suggestion_message['actions'][0]['kind'] ?? ''), 'suggestion_result messages should persist a preview-inline action first');
    lcfa_assert_same('page_upsert', (string) ($last_suggestion_message['actions'][0]['payload']['action'] ?? ''), 'suggestion_result preview-inline action should keep the suggested payload');
    lcfa_assert_true(!empty($last_suggestion_message['actions'][0]['payload']['dry_run']), 'suggestion_result preview-inline action should force dry_run');
    lcfa_assert_same('apply', (string) ($last_suggestion_message['actions'][1]['kind'] ?? ''), 'suggestion_result messages should persist an apply-inline action second');
    lcfa_assert_same('page_upsert', (string) ($last_suggestion_message['actions'][1]['payload']['action'] ?? ''), 'suggestion_result apply-inline action should keep the suggested payload');
    lcfa_assert_true(empty($last_suggestion_message['actions'][1]['payload']['dry_run']), 'suggestion_result apply-inline action should stay in apply mode');
    lcfa_assert_same('url', (string) ($last_suggestion_message['actions'][2]['kind'] ?? ''), 'suggestion_result messages should persist a command-deck deeplink action');
    lcfa_assert_contains('suggest_action=page_upsert', (string) ($last_suggestion_message['actions'][2]['url'] ?? ''), 'suggestion_result deeplink should open the suggested payload in Command Deck');
    lcfa_assert_contains('post_id=42', (string) ($last_suggestion_message['actions'][2]['url'] ?? ''), 'suggestion_result deeplink should preserve the current post context');
    lcfa_assert_contains('thread_id=' . (string) ($editor_thread['id'] ?? 'default'), (string) ($last_suggestion_message['actions'][2]['url'] ?? ''), 'suggestion_result deeplink should preserve the active thread id');
    lcfa_assert_contains('genesis_task_id=task-42', (string) ($last_suggestion_message['actions'][2]['url'] ?? ''), 'suggestion_result deeplink should preserve the current Genesis task context');
    lcfa_assert_contains('user_prompt=Refresh+the+hero+with+a+simpler+CTA+and+keep+the+current+logo.', (string) ($last_suggestion_message['actions'][2]['url'] ?? ''), 'suggestion_result deeplink should preserve the current prompt');
}

if (method_exists($rest_api, 'enqueue_agent_request')) {
    LCFA_Settings::update_connections([
        'preferred_client'  => 'codex',
        'connection_status' => 'ready',
    ]);

    $agent_thread = LCFA_Settings::create_thread('Codex frontend queue');
    $agent_enqueue_response = $rest_api->enqueue_agent_request(new WP_REST_Request([
        'thread_id'        => (string) ($agent_thread['id'] ?? 'default'),
        'agent'            => 'codex',
        'user_prompt'      => 'Change the first price to 19.',
        'execution_target' => 'local',
        'post_id'          => 42,
        'context_post_id'  => 42,
        'target_id'        => 42,
        'variant'          => '1',
        'action'           => 'page_upsert',
    ]));
    $agent_enqueue_payload = $agent_enqueue_response->get_data();
    $queued_agent_request = is_array($agent_enqueue_payload['request'] ?? null) ? $agent_enqueue_payload['request'] : [];
    $agent_request_id = sanitize_key((string) ($queued_agent_request['id'] ?? ''));

    lcfa_assert_same(202, $agent_enqueue_response->get_status(), 'enqueue_agent_request should queue verified agent prompts');
    lcfa_assert_true($agent_request_id !== '', 'enqueue_agent_request should return a request id');
    lcfa_assert_same('queued', (string) ($queued_agent_request['status'] ?? ''), 'enqueue_agent_request should start in queued status');
    lcfa_assert_same('codex', (string) ($queued_agent_request['agent'] ?? ''), 'enqueue_agent_request should preserve the selected Codex agent');
    lcfa_assert_same('agent_queue', (string) ($queued_agent_request['provenance']['processed_by'] ?? ''), 'enqueue_agent_request should mark browser submissions as agent_queue, not local Forge');

    $agent_claim_response = $rest_api->get_agent_request_status(new WP_REST_Request([
        'agent' => 'codex',
    ]));
    $agent_claim_payload = $agent_claim_response->get_data();
    $running_agent_request = is_array($agent_claim_payload['request'] ?? null) ? $agent_claim_payload['request'] : [];

    lcfa_assert_same(200, $agent_claim_response->get_status(), 'get_agent_request_status should let MCP agents claim queued prompts');
    lcfa_assert_same($agent_request_id, (string) ($running_agent_request['id'] ?? ''), 'get_agent_request_status should claim the queued frontend request');
    lcfa_assert_same('running', (string) ($running_agent_request['status'] ?? ''), 'get_agent_request_status should move the claimed request to running');

    $agent_complete_response = $rest_api->complete_agent_request(new WP_REST_Request([
        'request_id' => $agent_request_id,
        'result'     => [
            'result' => [
                'ok'        => true,
                'action'    => 'page_upsert',
                'mode'      => 'apply',
                'target_id' => 42,
                'summary'   => 'Updated the first price.',
                'message'   => 'Page updated by Codex.',
            ],
        ],
    ]));
    $agent_complete_payload = $agent_complete_response->get_data();
    $completed_agent_request = is_array($agent_complete_payload['request'] ?? null) ? $agent_complete_payload['request'] : [];
    $completed_thread = is_array($agent_complete_payload['thread'] ?? null) ? $agent_complete_payload['thread'] : [];
    $completed_messages = is_array($completed_thread['messages'] ?? null) ? $completed_thread['messages'] : [];
    $completed_last_message = $completed_messages[count($completed_messages) - 1] ?? [];

    lcfa_assert_same(200, $agent_complete_response->get_status(), 'complete_agent_request should accept MCP agent results');
    lcfa_assert_same('completed', (string) ($completed_agent_request['status'] ?? ''), 'complete_agent_request should mark the request as completed');
    lcfa_assert_same('codex_mcp', (string) ($completed_agent_request['result']['provenance']['processed_by'] ?? ''), 'complete_agent_request should stamp results with Codex MCP provenance');
    lcfa_assert_same('tool_result', (string) ($completed_last_message['role'] ?? ''), 'complete_agent_request should append a tool_result thread message');
    lcfa_assert_same('codex_mcp', (string) ($completed_last_message['meta']['processed_by'] ?? ''), 'complete_agent_request thread message should show Codex MCP as processor');
}

if (method_exists($rest_api, 'manage_chat_thread')) {
    $created_thread_response = $rest_api->manage_chat_thread(new WP_REST_Request([
        'operation' => 'create',
        'title'     => 'New editor flow',
    ]));
    $created_thread_payload = $created_thread_response->get_data();
    $created_thread = $created_thread_payload['thread'] ?? [];

    lcfa_assert_same(200, $created_thread_response->get_status(), 'manage_chat_thread(create) should return success');
    lcfa_assert_same('New editor flow', (string) ($created_thread['title'] ?? ''), 'manage_chat_thread(create) should use the requested thread title');
    lcfa_assert_true(!empty($created_thread_payload['threads']), 'manage_chat_thread(create) should return the refreshed thread payload collection');

    LCFA_Settings::append_thread_message((string) ($created_thread['id'] ?? ''), [
        'role'    => 'user',
        'label'   => 'Prompt',
        'content' => 'Make the hero tighter.',
        'meta'    => [],
    ]);

    $duplicated_thread_response = $rest_api->manage_chat_thread(new WP_REST_Request([
        'operation' => 'duplicate',
        'thread_id' => (string) ($created_thread['id'] ?? ''),
        'title'     => 'New editor flow copy',
    ]));
    $duplicated_thread_payload = $duplicated_thread_response->get_data();
    $duplicated_thread = $duplicated_thread_payload['thread'] ?? [];
    $duplicated_contents = array_map(static function ($message): string {
        return is_array($message) ? (string) ($message['content'] ?? '') : '';
    }, (array) ($duplicated_thread['messages'] ?? []));

    lcfa_assert_same(200, $duplicated_thread_response->get_status(), 'manage_chat_thread(duplicate) should return success');
    lcfa_assert_same('New editor flow copy', (string) ($duplicated_thread['title'] ?? ''), 'manage_chat_thread(duplicate) should use the requested duplicate title');
    lcfa_assert_true(in_array('Make the hero tighter.', $duplicated_contents, true), 'manage_chat_thread(duplicate) should preserve source thread messages');

    $cleared_thread_response = $rest_api->manage_chat_thread(new WP_REST_Request([
        'operation' => 'clear',
        'thread_id' => (string) ($duplicated_thread['id'] ?? ''),
    ]));
    $cleared_thread_payload = $cleared_thread_response->get_data();
    $cleared_thread = $cleared_thread_payload['thread'] ?? [];

    lcfa_assert_same(200, $cleared_thread_response->get_status(), 'manage_chat_thread(clear) should return success');
    lcfa_assert_same(0, count((array) ($cleared_thread['messages'] ?? [])), 'manage_chat_thread(clear) should empty the selected thread messages');

    $renamed_thread_response = $rest_api->manage_chat_thread(new WP_REST_Request([
        'operation' => 'rename',
        'thread_id' => (string) ($duplicated_thread['id'] ?? ''),
        'title'     => 'Renamed editor flow',
    ]));
    $renamed_thread_payload = $renamed_thread_response->get_data();
    $renamed_thread = $renamed_thread_payload['thread'] ?? [];

    lcfa_assert_same(200, $renamed_thread_response->get_status(), 'manage_chat_thread(rename) should return success');
    lcfa_assert_same('Renamed editor flow', (string) ($renamed_thread['title'] ?? ''), 'manage_chat_thread(rename) should update the selected thread title');

    $deleted_thread_response = $rest_api->manage_chat_thread(new WP_REST_Request([
        'operation' => 'delete',
        'thread_id' => (string) ($renamed_thread['id'] ?? ''),
    ]));
    $deleted_thread_payload = $deleted_thread_response->get_data();

    lcfa_assert_same(200, $deleted_thread_response->get_status(), 'manage_chat_thread(delete) should return success for non-default threads');
    lcfa_assert_same('default', (string) ($deleted_thread_payload['selected_thread_id'] ?? ''), 'manage_chat_thread(delete) should fall back to the default thread');
    lcfa_assert_true(!isset(($deleted_thread_payload['threads'] ?? [])[(string) ($renamed_thread['id'] ?? '')]), 'manage_chat_thread(delete) should remove the deleted thread from the refreshed payload collection');
}

$inline_apply_thread = LCFA_Settings::create_thread('Inline apply');
$inline_apply_response = $rest_api->run_command(new WP_REST_Request([
    'thread_id'        => (string) ($inline_apply_thread['id'] ?? 'default'),
    'action'           => 'validate_markup_for_framework',
    'execution_target' => 'local',
    'content'          => '<main><section><h1>Inline apply test</h1></section></main>',
]));
$inline_apply_payload = $inline_apply_response->get_data();
$inline_apply_thread_after = $inline_apply_payload['thread'] ?? [];
$inline_apply_messages = is_array($inline_apply_thread_after['messages'] ?? null) ? $inline_apply_thread_after['messages'] : [];
$inline_apply_last_message = $inline_apply_messages[count($inline_apply_messages) - 1] ?? [];

lcfa_assert_same(200, $inline_apply_response->get_status(), 'run_command should keep successful inline apply responses in the success range');
lcfa_assert_true(is_array($inline_apply_payload['result'] ?? null), 'run_command should still return the command result payload');
lcfa_assert_same((string) ($inline_apply_thread['id'] ?? 'default'), (string) ($inline_apply_thread_after['id'] ?? ''), 'run_command should return the updated thread when thread_id is provided');
lcfa_assert_same('tool_result', (string) ($inline_apply_last_message['role'] ?? ''), 'run_command should append the execution outcome as a tool_result thread message');
lcfa_assert_same('validate_markup_for_framework', (string) ($inline_apply_last_message['meta']['action'] ?? ''), 'run_command should persist the executed action in thread message metadata');
lcfa_assert_contains('Validate page markup', (string) ($inline_apply_last_message['content'] ?? ''), 'run_command should append the execution summary to the thread message content');

$async_preview_thread = LCFA_Settings::create_thread('Async preview');
$async_enqueue_response = $rest_api->enqueue_command_execution(new WP_REST_Request([
    'thread_id'        => (string) ($async_preview_thread['id'] ?? 'default'),
    'action'           => 'page_upsert',
    'execution_target' => 'local',
    'post_id'          => 42,
    'context_post_id'  => 42,
    'target_id'        => 42,
    'variant'          => '1',
    'title'            => 'Async Pricing',
    'slug'             => 'async-pricing',
    'content'          => '<main><section><h1>Async pricing preview</h1></section></main>',
    'dry_run'          => true,
]));
$async_enqueue_payload = $async_enqueue_response->get_data();
$async_execution = is_array($async_enqueue_payload['execution'] ?? null) ? $async_enqueue_payload['execution'] : [];
$async_execution_id = sanitize_key((string) ($async_execution['id'] ?? ''));

lcfa_assert_same(202, $async_enqueue_response->get_status(), 'enqueue_command_execution should accept async inline executions');
lcfa_assert_true($async_execution_id !== '', 'enqueue_command_execution should return an execution id');
lcfa_assert_same('queued', (string) ($async_execution['status'] ?? ''), 'enqueue_command_execution should queue the execution first');
lcfa_assert_same('preview', (string) ($async_execution['mode'] ?? ''), 'enqueue_command_execution should describe the preview mode for dry runs');

$async_status_response = $rest_api->get_command_execution_status(new WP_REST_Request([
    'execution_id' => $async_execution_id,
]));
$async_status_payload = $async_status_response->get_data();
$async_status_execution = is_array($async_status_payload['execution'] ?? null) ? $async_status_payload['execution'] : [];
$async_status_thread = is_array($async_status_execution['thread'] ?? null) ? $async_status_execution['thread'] : [];
$async_status_messages = is_array($async_status_thread['messages'] ?? null) ? $async_status_thread['messages'] : [];
$async_status_last_message = $async_status_messages[count($async_status_messages) - 1] ?? [];

lcfa_assert_same(200, $async_status_response->get_status(), 'get_command_execution_status should resolve queued executions');
lcfa_assert_same('completed', (string) ($async_status_execution['status'] ?? ''), 'get_command_execution_status should complete queued executions on poll');
lcfa_assert_same('preview', (string) ($async_status_execution['result']['mode'] ?? ''), 'get_command_execution_status should return the preview result payload');
lcfa_assert_true((string) ($async_status_execution['result']['proposed_html'] ?? '') !== '', 'async preview executions should expose proposed HTML for support details');
lcfa_assert_same('tool_result', (string) ($async_status_last_message['role'] ?? ''), 'async executions should append a tool_result to the thread');
lcfa_assert_same('preview', (string) ($async_status_last_message['meta']['mode'] ?? ''), 'async preview executions should persist preview mode in the thread');

$rest_api_reflection = new ReflectionClass('LCFA_Rest_Api');
$build_command_result_message = $rest_api_reflection->getMethod('build_command_result_message');
$decorated_result_message = $build_command_result_message->invoke($rest_api, [
    'ok'           => true,
    'action'       => 'page_upsert',
    'summary'      => 'Created pricing page.',
    'message'      => 'LiveCanvas page created.',
    'target_type'  => 'theme_file',
    'target_id'    => 42,
    'target_title' => 'assets/theme.css',
    'frontend_url' => 'https://example.test/pricing/',
    'edit_url'     => 'https://example.test/wp-admin/post.php?post=42&action=edit',
    'data'         => [
        'restored_from_backup' => [
            'backup_id' => '2026-04-16/theme.css.bak',
        ],
    ],
], [
    'thread_id'        => (string) ($inline_apply_thread['id'] ?? 'default'),
    'execution_target' => 'local',
    'file_path'        => 'assets/theme.css',
    'root_scope'       => 'stylesheet',
]);
$decorated_actions = is_array($decorated_result_message['actions'] ?? null) ? $decorated_result_message['actions'] : [];

lcfa_assert_same(2, count($decorated_actions), 'build_command_result_message should attach at most two ranked actions');
lcfa_assert_same('View page', (string) ($decorated_actions[0]['label'] ?? ''), 'build_command_result_message should prioritize the frontend action first');
lcfa_assert_same('Edit page', (string) ($decorated_actions[1]['label'] ?? ''), 'build_command_result_message should prioritize the edit action second');
lcfa_assert_true(!array_key_exists('frontend_url', (array) ($decorated_result_message['meta'] ?? [])), 'build_command_result_message should not keep frontend_url in meta when actions are already attached');
lcfa_assert_true(!array_key_exists('edit_url', (array) ($decorated_result_message['meta'] ?? [])), 'build_command_result_message should not keep edit_url in meta when actions are already attached');
lcfa_assert_true(!array_key_exists('file_path', (array) ($decorated_result_message['meta'] ?? [])), 'build_command_result_message should not keep file_path in meta when actions are already attached');
lcfa_assert_true(!array_key_exists('root_scope', (array) ($decorated_result_message['meta'] ?? [])), 'build_command_result_message should not keep root_scope in meta when actions are already attached');
lcfa_assert_true(!array_key_exists('backup_id', (array) ($decorated_result_message['meta'] ?? [])), 'build_command_result_message should not keep backup_id in meta when actions are already attached');

$failed_result_message = $build_command_result_message->invoke($rest_api, [
    'ok'      => false,
    'action'  => 'page_upsert',
    'summary' => 'Page creation failed.',
    'message' => 'The generated markup must be reviewed before apply.',
], [
    'thread_id'        => (string) ($inline_apply_thread['id'] ?? 'default'),
    'action'           => 'page_upsert',
    'execution_target' => 'local',
    'title'            => 'Retry Pricing',
    'slug'             => 'retry-pricing',
    'post_id'          => 42,
    'genesis_task_id'  => 'task-retry',
    'user_prompt'      => 'Retry the pricing page with lighter markup.',
]);
$failed_actions = is_array($failed_result_message['actions'] ?? null) ? $failed_result_message['actions'] : [];

lcfa_assert_same(3, count($failed_actions), 'failed tool_result messages should expose recovery actions');
lcfa_assert_same('apply', (string) ($failed_actions[0]['kind'] ?? ''), 'failed tool_result messages should offer a preview retry first');
lcfa_assert_same('Preview', (string) ($failed_actions[0]['label'] ?? ''), 'failed tool_result messages should label the first recovery action as Preview');
lcfa_assert_true(!empty($failed_actions[0]['payload']['dry_run']), 'failed tool_result preview recovery should force dry_run');
lcfa_assert_same('apply', (string) ($failed_actions[1]['kind'] ?? ''), 'failed tool_result messages should offer a retry apply action second');
lcfa_assert_same('Retry', (string) ($failed_actions[1]['label'] ?? ''), 'failed tool_result messages should label the second recovery action as Retry');
lcfa_assert_true(empty($failed_actions[1]['payload']['dry_run']), 'failed tool_result retry should stay in apply mode');
lcfa_assert_same('url', (string) ($failed_actions[2]['kind'] ?? ''), 'failed tool_result messages should keep a Command Deck recovery deeplink');
lcfa_assert_contains('suggest_action=page_upsert', (string) ($failed_actions[2]['url'] ?? ''), 'failed tool_result recovery deeplink should preserve the action');
lcfa_assert_contains('thread_id=' . (string) ($inline_apply_thread['id'] ?? 'default'), (string) ($failed_actions[2]['url'] ?? ''), 'failed tool_result recovery deeplink should preserve the thread');
lcfa_assert_contains('genesis_task_id=task-retry', (string) ($failed_actions[2]['url'] ?? ''), 'failed tool_result recovery deeplink should preserve the Genesis task');

$suggestion = $prompt_suggester->suggest([
    'user_prompt' => 'Crea una landing page Tailwind con hero, feature e CTA',
]);

lcfa_assert_true(!empty($suggestion['ok']), 'prompt suggestion should succeed for page requests');
lcfa_assert_same('page_upsert', $suggestion['suggested_payload']['action'] ?? '', 'page creation prompts should keep page_upsert even when Tailwind is mentioned');

$previous_stylesheet = $GLOBALS['lcfa_test_stylesheet'];
$previous_template = $GLOBALS['lcfa_test_template'];
$GLOBALS['lcfa_test_stylesheet'] = 'picowind-child';
$GLOBALS['lcfa_test_template'] = 'picowind';

$picowind_suggestion = $prompt_suggester->suggest([
    'user_prompt' => 'Usa livecanvas-forge per creare una pricing page come questa',
]);

lcfa_assert_true(!empty($picowind_suggestion['ok']), 'prompt suggestion should succeed for Picowind page requests');
lcfa_assert_same('page_upsert', $picowind_suggestion['suggested_payload']['action'] ?? '', 'Picowind page requests should still target page_upsert');
lcfa_assert_same('validate_markup_for_framework', $picowind_suggestion['preflight']['action'] ?? '', 'Picowind page requests should recommend framework validation before page_upsert');
lcfa_assert_same('picowind', $picowind_suggestion['preflight']['payload']['framework'] ?? '', 'Picowind preflight payload should pin the active framework');
lcfa_assert_same('validate_markup_for_framework', $picowind_suggestion['workflow'][0]['action'] ?? '', 'Picowind workflow should start with validation');
lcfa_assert_same('page_upsert', $picowind_suggestion['workflow'][1]['action'] ?? '', 'Picowind workflow should continue with page_upsert after validation');

$context_reflection = new ReflectionClass($context_builder);
$picowind_rules_method = $context_reflection->getMethod('get_output_rules');
$picowind_rules = $picowind_rules_method->invoke($context_builder, 'picowind');

lcfa_assert_true(($picowind_rules['html_only'] ?? null) === false, 'Picowind output rules should not force HTML-only output');
lcfa_assert_true(($picowind_rules['allow_javascript'] ?? null) === true, 'Picowind output rules should allow JavaScript when needed');
lcfa_assert_contains('Do not wrap generated LiveCanvas page content in <main>', implode("\n", (array) ($picowind_rules['notes'] ?? [])), 'Picowind output rules should tell agents not to add a duplicate main wrapper');

$picostrap_rules = $picowind_rules_method->invoke($context_builder, 'picostrap');
lcfa_assert_contains('Do not wrap generated LiveCanvas page content in <main>', implode("\n", (array) ($picostrap_rules['notes'] ?? [])), 'Picostrap output rules should tell agents not to add a duplicate main wrapper');

$audit_page_id = 42;
$GLOBALS['lcfa_test_posts'][$audit_page_id] = new WP_Post([
    'ID'           => $audit_page_id,
    'post_title'   => 'Existing Audit Page',
    'post_name'    => 'existing-audit-page',
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_content' => '<main><section>Existing page</section></main>',
]);

LCFA_Settings::update_project_brief([
    'project_mode'   => 'step_by_step',
    'brand_name'     => 'Consultala',
    'sector'         => 'Consulting',
    'tone'           => 'Precise',
    'logo_status'    => 'existing',
    'required_pages' => "Home\nAbout",
    'notes'          => 'Audit current structure before changes.',
]);

$generated_execution_plan_response = $rest_api->generate_genesis_plan(new WP_REST_Request([
    'project_mode'   => 'step_by_step',
    'brand_name'     => 'Consultala',
    'sector'         => 'Consulting',
    'tone'           => 'Precise',
    'logo_status'    => 'existing',
    'required_pages' => "Home\nAbout",
    'notes'          => 'Audit current structure before changes.',
]));

lcfa_assert_same(200, $generated_execution_plan_response->get_status(), 'generate_genesis_plan should still succeed for step-by-step execution tests');

$execution_plan_response = $rest_api->get_genesis_execution_plan();
$execution_plan_payload = $execution_plan_response->get_data();
$initial_next_task_id = sanitize_key((string) ($execution_plan_payload['next_task_id'] ?? ''));
$initial_next_task = null;
$safe_preview_task_id = '';
$safe_preview_task = null;
$seen_safe_task = false;

foreach ((array) ($execution_plan_payload['tasks'] ?? []) as $candidate_task) {
    if (is_array($candidate_task) && sanitize_key((string) ($candidate_task['id'] ?? '')) === $initial_next_task_id) {
        $initial_next_task = $candidate_task;
    }

    if (!$seen_safe_task && is_array($candidate_task) && in_array((string) ($candidate_task['payload']['action'] ?? ''), ['site_audit', 'theme_files_audit', 'windpress_audit'], true)) {
        $safe_preview_task_id = sanitize_key((string) ($candidate_task['id'] ?? ''));
        $safe_preview_task = $candidate_task;
        $seen_safe_task = true;
    }
}

lcfa_assert_same(200, $execution_plan_response->get_status(), 'get_genesis_execution_plan should return success');
lcfa_assert_true(!empty($execution_plan_payload['available']), 'get_genesis_execution_plan should report availability after plan generation');
lcfa_assert_true($initial_next_task_id !== '', 'get_genesis_execution_plan should expose the first actionable Genesis task');
lcfa_assert_true(((int) ($execution_plan_payload['counts']['pending'] ?? 0)) >= 1, 'get_genesis_execution_plan should count pending Genesis tasks');
lcfa_assert_true($safe_preview_task_id !== '', 'get_genesis_execution_plan should expose at least one safe read-only task for orchestration tests');

foreach ((array) ($execution_plan_payload['tasks'] ?? []) as $candidate_task) {
    if (!is_array($candidate_task)) {
        continue;
    }

    $candidate_task_id = sanitize_key((string) ($candidate_task['id'] ?? ''));

    if ($candidate_task_id === '' || $candidate_task_id === $safe_preview_task_id) {
        break;
    }

    LCFA_Settings::update_genesis_task_progress($candidate_task_id, [
        'status'    => 'applied',
        'thread_id' => 'default',
        'action'    => sanitize_key((string) ($candidate_task['payload']['action'] ?? '')),
        'mode'      => 'apply',
        'ok'        => true,
        'message'   => 'Pre-applied for safe executor test.',
    ]);
}

$safe_execution_plan_response = $rest_api->get_genesis_execution_plan();
$safe_execution_plan_payload = $safe_execution_plan_response->get_data();

$preview_next_response = $rest_api->execute_next_genesis_task(new WP_REST_Request([
    'dry_run'          => true,
    'execution_target' => 'local',
    'thread_id'        => 'default',
]));
$preview_next_payload = $preview_next_response->get_data();

lcfa_assert_same(200, $preview_next_response->get_status(), 'execute_next_genesis_task should return success when previewing the next task');
lcfa_assert_same($safe_preview_task_id, (string) ($safe_execution_plan_payload['next_task_id'] ?? ''), 'get_genesis_execution_plan should advance to the selected safe read-only task after pre-applying earlier steps');
lcfa_assert_same($safe_preview_task_id, (string) ($preview_next_payload['task_id'] ?? ''), 'execute_next_genesis_task should execute the selected safe pending task');
lcfa_assert_same('previewed', (string) ($preview_next_payload['execution_plan']['progress']['tasks'][$safe_preview_task_id]['status'] ?? ''), 'execute_next_genesis_task should mark dry-run tasks as previewed');
lcfa_assert_same((string) ($safe_preview_task['payload']['action'] ?? ''), (string) ($preview_next_payload['result']['action'] ?? ''), 'execute_next_genesis_task should reuse the Command Deck action from the Genesis task payload');

$apply_next_response = $rest_api->execute_next_genesis_task(new WP_REST_Request([
    'execution_target' => 'local',
    'thread_id'        => 'default',
]));
$apply_next_payload = $apply_next_response->get_data();

lcfa_assert_same(200, $apply_next_response->get_status(), 'execute_next_genesis_task should return success when applying the next task');
lcfa_assert_same($safe_preview_task_id, (string) ($apply_next_payload['task_id'] ?? ''), 'execute_next_genesis_task should re-run the previewed task until applied');
lcfa_assert_same('applied', (string) ($apply_next_payload['execution_plan']['progress']['tasks'][$safe_preview_task_id]['status'] ?? ''), 'execute_next_genesis_task should mark applied tasks as applied');

$execution_context_builder = new LCFA_Context_Builder($environment, $inventory);
$execution_context = $execution_context_builder->build_context();

lcfa_assert_same($apply_next_payload['execution_plan']['next_task_id'] ?? '', $execution_context['genesis']['execution']['next_task_id'] ?? '', 'build_context should expose the same next Genesis task id after execution updates');
lcfa_assert_true(((int) ($execution_context['genesis']['execution']['counts']['applied'] ?? 0)) >= 1, 'build_context should expose Genesis execution counts');

$GLOBALS['lcfa_test_post_meta'][$audit_page_id]['_lc_livecanvas_enabled'] = '1';
$GLOBALS['lcfa_test_posts'][43] = new WP_Post([
    'ID'           => 43,
    'post_title'   => 'Header Partial',
    'post_name'    => 'header-partial',
    'post_type'    => 'lc_partial',
    'post_status'  => 'publish',
    'post_content' => '<header>Header partial</header>',
]);
$GLOBALS['lcfa_test_post_meta'][43]['is_header'] = '1';
$GLOBALS['lcfa_test_posts'][44] = new WP_Post([
    'ID'           => 44,
    'post_title'   => 'Footer Partial',
    'post_name'    => 'footer-partial',
    'post_type'    => 'lc_partial',
    'post_status'  => 'publish',
    'post_content' => '<footer>Footer partial</footer>',
]);
$GLOBALS['lcfa_test_post_meta'][44]['is_footer'] = '1';
$GLOBALS['lcfa_test_posts'][45] = new WP_Post([
    'ID'           => 45,
    'post_title'   => 'Single Service Template',
    'post_name'    => 'single-service-template',
    'post_type'    => 'lc_dynamic_template',
    'post_status'  => 'publish',
    'post_content' => '<main>Dynamic template</main>',
]);

$target_inventory = new LCFA_Inventory($environment);
$target_context_builder = new LCFA_Context_Builder($environment, $target_inventory);
$targeted_context = $target_context_builder->build_context([
    'post_id' => $audit_page_id,
]);
$target_contexts = is_array($targeted_context['target_contexts'] ?? null) ? $targeted_context['target_contexts'] : [];
$current_target_pack = is_array($targeted_context['current_target']['context_pack'] ?? null) ? $targeted_context['current_target']['context_pack'] : [];
$theme_context_with_target = $target_context_builder->get_theme_context([
    'post_id' => $audit_page_id,
]);

lcfa_assert_same('page', (string) ($targeted_context['current_target']['target_type'] ?? ''), 'build_context should classify standard pages as page targets');
lcfa_assert_same('page_upsert', (string) ($target_contexts['page']['command_action'] ?? ''), 'target_contexts should expose page command guidance');
lcfa_assert_same('update_header', (string) ($target_contexts['header']['command_action'] ?? ''), 'target_contexts should expose header command guidance');
lcfa_assert_same(1, (int) ($target_contexts['header']['count'] ?? 0), 'target_contexts should count header partials from inventory');
lcfa_assert_same('update_footer', (string) ($target_contexts['footer']['command_action'] ?? ''), 'target_contexts should expose footer command guidance');
lcfa_assert_same('update_dynamic_template', (string) ($target_contexts['dynamic_template']['command_action'] ?? ''), 'target_contexts should expose dynamic template command guidance');
lcfa_assert_same('write_theme_file', (string) ($target_contexts['theme_file']['command_action'] ?? ''), 'target_contexts should expose theme file command guidance');
lcfa_assert_same('restore_theme_backup', (string) ($target_contexts['backup_restore']['command_action'] ?? ''), 'target_contexts should expose backup restore guidance');
lcfa_assert_same($execution_context['genesis']['execution']['next_task_id'] ?? '', (string) ($target_contexts['genesis_task']['next_task_id'] ?? ''), 'target_contexts should surface the next Genesis task id');
lcfa_assert_same('page_upsert', (string) ($current_target_pack['command_action'] ?? ''), 'current_target should inherit the matching target context pack');
lcfa_assert_true(!empty($theme_context_with_target['target_contexts']), 'get_theme_context should expose target_contexts for consumers that only read the theme context contract');
lcfa_assert_same('page', (string) ($theme_context_with_target['current_target']['target_type'] ?? ''), 'get_theme_context should expose the resolved current target when post_id is provided');

$header_context_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Refresh this partial with a slimmer nav and a single CTA.',
    'post_id'         => 43,
    'context_post_id' => 43,
]);
$footer_context_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Tighten this layout and add legal links.',
    'post_id'         => 44,
    'context_post_id' => 44,
]);
$dynamic_template_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Refine this template to feel more editorial.',
    'post_id'         => 45,
    'context_post_id' => 45,
]);

lcfa_assert_same('update_header', (string) ($header_context_suggestion['suggested_payload']['action'] ?? ''), 'prompt suggestion should prefer update_header when the current context target is a header partial');
lcfa_assert_same(43, (int) ($header_context_suggestion['suggested_payload']['target_id'] ?? 0), 'prompt suggestion should preserve the current header partial target id');
lcfa_assert_same('update_footer', (string) ($footer_context_suggestion['suggested_payload']['action'] ?? ''), 'prompt suggestion should prefer update_footer when the current context target is a footer partial');
lcfa_assert_same(44, (int) ($footer_context_suggestion['suggested_payload']['target_id'] ?? 0), 'prompt suggestion should preserve the current footer partial target id');
lcfa_assert_same('update_dynamic_template', (string) ($dynamic_template_suggestion['suggested_payload']['action'] ?? ''), 'prompt suggestion should prefer update_dynamic_template when the current context target is a dynamic template');
lcfa_assert_same(45, (int) ($dynamic_template_suggestion['suggested_payload']['target_id'] ?? 0), 'prompt suggestion should preserve the current dynamic template target id');

$site_prepare_result = $command_deck->execute([
    'action'  => 'site_prepare',
    'dry_run' => true,
]);

lcfa_assert_true($site_prepare_result['ok'] === true, 'site_prepare should return a successful preflight result');
lcfa_assert_same('site_prepare', (string) ($site_prepare_result['target_type'] ?? ''), 'site_prepare should expose a dedicated target type');
lcfa_assert_true(is_array($site_prepare_result['data']['snapshot'] ?? null), 'site_prepare should include the environment snapshot');
lcfa_assert_true(is_array($site_prepare_result['data']['theme'] ?? null), 'site_prepare should include theme root diagnostics');

$global_shell_preview = $command_deck->execute([
    'action'      => 'global_shell_apply',
    'variant'     => '1',
    'header_html' => '<header><nav>Preview Header</nav></header>',
    'footer_html' => '<footer>Preview Footer</footer>',
    'dry_run'     => true,
]);

lcfa_assert_true($global_shell_preview['ok'] === true, 'global_shell_apply should preview header/footer updates');
lcfa_assert_same('global_shell', (string) ($global_shell_preview['target_type'] ?? ''), 'global_shell_apply should expose a global shell target');
lcfa_assert_same('update', (string) ($global_shell_preview['data']['parts']['header']['operation'] ?? ''), 'global_shell preview should target the existing header partial when present');
lcfa_assert_same('<header>Header partial</header>', (string) $GLOBALS['lcfa_test_posts'][43]->post_content, 'global_shell preview must not mutate the existing header partial');

$global_shell_apply = $command_deck->execute([
    'action'      => 'global_shell_apply',
    'variant'     => '1',
    'header_html' => '<header><nav>Applied Header</nav></header>',
    'footer_html' => '<footer>Applied Footer</footer>',
]);

lcfa_assert_true($global_shell_apply['ok'] === true, 'global_shell_apply should apply header/footer updates');
lcfa_assert_same('<header><nav>Applied Header</nav></header>', (string) $GLOBALS['lcfa_test_posts'][43]->post_content, 'global_shell_apply should update the existing header partial');
lcfa_assert_same('<footer>Applied Footer</footer>', (string) $GLOBALS['lcfa_test_posts'][44]->post_content, 'global_shell_apply should update the existing footer partial');

$global_shell_content_preview = $command_deck->execute([
    'action'  => 'global_shell_apply',
    'variant' => '1',
    'content' => '<header>Combined Header</header><footer>Combined Footer</footer>',
    'dry_run' => true,
]);

lcfa_assert_true($global_shell_content_preview['ok'] === true, 'global_shell_apply should accept combined header/footer content from the generic Command Deck content field');
lcfa_assert_contains('Combined Header', (string) ($global_shell_content_preview['proposed_html'] ?? ''), 'global_shell_apply should extract header markup from combined content');
lcfa_assert_contains('Combined Footer', (string) ($global_shell_content_preview['proposed_html'] ?? ''), 'global_shell_apply should extract footer markup from combined content');

$dynamic_assignment_result = $command_deck->execute([
    'action'      => 'update_dynamic_template',
    'target_id'   => 45,
    'content'     => '<main>Assigned dynamic template</main>',
    'template_assignment' => [
        'target'    => 'single',
        'post_type' => 'service',
        'source'    => 'contract_test',
    ],
]);

lcfa_assert_true($dynamic_assignment_result['ok'] === true, 'update_dynamic_template should accept assignment metadata');
lcfa_assert_same('single', (string) ($GLOBALS['lcfa_test_post_meta'][45]['_lcfa_template_target'] ?? ''), 'dynamic template assignments should persist the target');
lcfa_assert_same('service', (string) ($GLOBALS['lcfa_test_post_meta'][45]['_lcfa_template_post_type'] ?? ''), 'dynamic template assignments should persist the post type');
lcfa_assert_same('single', (string) ($dynamic_assignment_result['data']['template_assignment']['target'] ?? ''), 'dynamic template results should expose sanitized assignment metadata');
lcfa_assert_same('is_single_service', (string) ($dynamic_assignment_result['data']['native_template_keys'][0] ?? ''), 'dynamic template results should expose the native LiveCanvas single-template meta key');
lcfa_assert_same(1, $GLOBALS['lcfa_test_post_meta'][45]['is_single_service'] ?? null, 'dynamic template assignments should sync to native LiveCanvas single-template meta');
lcfa_assert_same('is_single_service', (string) ($GLOBALS['lcfa_test_post_meta'][45]['_lcfa_template_native_keys'][0] ?? ''), 'dynamic template assignments should persist generated native template keys');

$dynamic_taxonomy_assignment_result = $command_deck->execute([
    'action'      => 'update_dynamic_template',
    'target_id'   => 45,
    'content'     => '<main>Assigned taxonomy archive template</main>',
    'template_assignment' => [
        'target'   => 'taxonomy',
        'taxonomy' => 'category',
        'term'     => 'news',
        'source'   => 'contract_test',
    ],
]);

lcfa_assert_true($dynamic_taxonomy_assignment_result['ok'] === true, 'update_dynamic_template should accept taxonomy assignment metadata');
lcfa_assert_same('is_archive_for_tax_category__news', (string) ($dynamic_taxonomy_assignment_result['data']['native_template_keys'][0] ?? ''), 'taxonomy assignments should expose the native LiveCanvas taxonomy archive meta key');
lcfa_assert_same(1, $GLOBALS['lcfa_test_post_meta'][45]['is_archive_for_tax_category__news'] ?? null, 'taxonomy assignments should sync to native LiveCanvas archive meta');
lcfa_assert_true(!isset($GLOBALS['lcfa_test_post_meta'][45]['is_single_service']), 'changing dynamic template assignment should remove stale native LiveCanvas meta keys');
lcfa_assert_true(!isset($GLOBALS['lcfa_test_post_meta'][45]['_lcfa_template_post_type']), 'changing dynamic template assignment should remove stale Forge scalar assignment metadata');

$dynamic_shop_preview = $command_deck->execute([
    'action'          => 'create_dynamic_template',
    'title'           => 'Shop Template',
    'content'         => '<main>Shop template</main>',
    'template_target' => 'shop',
    'dry_run'         => true,
]);

lcfa_assert_true($dynamic_shop_preview['ok'] === true, 'create_dynamic_template preview should accept specialty template targets');
lcfa_assert_same('is_shop_page', (string) ($dynamic_shop_preview['data']['native_template_keys'][0] ?? ''), 'specialty template targets should map to native LiveCanvas WooCommerce meta keys');

$metadata_inventory = (new LCFA_Inventory($environment))->get_inventory();
$metadata_header = $metadata_inventory['header_partials'][0] ?? [];
$metadata_dynamic_template = $metadata_inventory['dynamic_templates'][0] ?? [];

lcfa_assert_same('header', (string) ($metadata_header['partial_type'] ?? ''), 'inventory should classify header partials for variant-aware quick actions');
lcfa_assert_same('1', (string) ($metadata_header['variant'] ?? ''), 'inventory should expose the stored header partial variant');
lcfa_assert_same('taxonomy', (string) ($metadata_dynamic_template['template_assignment']['target'] ?? ''), 'inventory should expose stored dynamic template assignment metadata');
lcfa_assert_same('category', (string) ($metadata_dynamic_template['template_assignment']['taxonomy'] ?? ''), 'inventory should expose stored dynamic template assignment taxonomy metadata');
lcfa_assert_same('is_archive_for_tax_category__news', (string) ($metadata_dynamic_template['native_template_keys'][0] ?? ''), 'inventory should expose native LiveCanvas template assignment keys');

$foundation_preview = $command_deck->execute([
    'action'        => 'site_foundation_run',
    'dry_run'       => true,
    'skip_pages'    => true,
    'header_html'   => '<header>Foundation Header</header>',
    'footer_html'   => '<footer>Foundation Footer</footer>',
    'framework'     => 'custom',
    'colors'        => ['primary' => '#112233'],
]);

lcfa_assert_true($foundation_preview['ok'] === true, 'site_foundation_run should orchestrate a dry-run foundation setup');
lcfa_assert_true(isset($foundation_preview['data']['steps']['site_prepare']), 'site_foundation_run should include a site_prepare step');
lcfa_assert_true(isset($foundation_preview['data']['steps']['design_system_apply']), 'site_foundation_run should include design system apply when design tokens are provided');
lcfa_assert_true(isset($foundation_preview['data']['steps']['global_shell_apply']), 'site_foundation_run should include global shell apply by default');
lcfa_assert_same('<header><nav>Applied Header</nav></header>', (string) $GLOBALS['lcfa_test_posts'][43]->post_content, 'site_foundation_run dry-run must not mutate the header partial');

$hero_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Fammi una hero più chiara per questa pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$pricing_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una pricing con tre piani per questa pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$features_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Inserisci una sezione features con tre punti chiave.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$testimonials_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Metti dei testimonials più credibili in questa pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$cta_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una CTA finale più forte.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$faq_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una sezione FAQ essenziale per questa pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$metrics_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Inserisci delle metriche chiave sopra la pricing.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$team_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una sezione team con tre profili.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$contact_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Metti una contact section semplice in fondo alla pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$logo_cloud_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi un logo cloud con loghi clienti.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$comparison_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Inserisci una sezione confronto tra opzione standard e Consultala.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$timeline_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una timeline del processo.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$replace_hero_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Sostituisci la hero di questa pagina con una versione piu pulita.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$before_footer_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Aggiungi una CTA prima del footer di questa pagina.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
]);
$after_selected_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'     => 'Inserisci una timeline dopo la sezione selezionata.',
    'post_id'         => $audit_page_id,
    'context_post_id' => $audit_page_id,
    'selected_section_anchor' => [
        'tag_name'      => 'section',
        'id'            => 'pricing',
        'class_token'   => 'lcfa-section--pricing',
        'section_index' => 1,
        'source'        => 'editor_click',
    ],
]);
$visual_reference_section_suggestion = $prompt_suggester->suggest([
    'user_prompt'      => 'Fammi una hero come nello screenshot.',
    'post_id'          => $audit_page_id,
    'context_post_id'  => $audit_page_id,
    'attachment_count' => 1,
    'attachments'      => [
        [
            'kind'        => 'image',
            'name'        => 'hero-reference.png',
            'mime'        => 'image/png',
            'data_url'    => 'data:image/png;base64,AAAA',
            'size'        => 128,
            'width'       => 1440,
            'height'      => 900,
            'orientation' => 'landscape',
        ],
    ],
]);

lcfa_assert_same('page_upsert', (string) ($hero_section_suggestion['suggested_payload']['action'] ?? ''), 'natural hero prompts should stay on page_upsert in the page editor');
lcfa_assert_same('hero', (string) ($hero_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'natural hero prompts should expose the detected hero section intent');
lcfa_assert_same('prepend', (string) ($hero_section_suggestion['suggested_payload']['section_operation'] ?? ''), 'hero suggestions should default to a prepend operation');
lcfa_assert_same('section_starter', (string) ($hero_section_suggestion['suggested_payload']['content_strategy'] ?? ''), 'hero suggestions should mark the payload as section starter driven');
lcfa_assert_same($audit_page_id, (int) ($hero_section_suggestion['suggested_payload']['target_id'] ?? 0), 'hero suggestions should preserve the current page target id');
lcfa_assert_same('pricing', (string) ($pricing_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'pricing prompts should expose the detected pricing section intent');
lcfa_assert_same('append', (string) ($pricing_section_suggestion['suggested_payload']['section_operation'] ?? ''), 'pricing suggestions should default to append');
lcfa_assert_same('features', (string) ($features_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'features prompts should expose the detected features section intent');
lcfa_assert_same('testimonials', (string) ($testimonials_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'testimonials prompts should expose the detected testimonials section intent');
lcfa_assert_same('cta', (string) ($cta_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'CTA prompts should expose the detected CTA section intent');
lcfa_assert_same('faq', (string) ($faq_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'FAQ prompts should expose the detected faq section intent');
lcfa_assert_same('metrics', (string) ($metrics_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'metric prompts should expose the detected metrics section intent');
lcfa_assert_same('team', (string) ($team_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'team prompts should expose the detected team section intent');
lcfa_assert_same('contact', (string) ($contact_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'contact prompts should expose the detected contact section intent');
lcfa_assert_same('logo_cloud', (string) ($logo_cloud_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'logo-cloud prompts should expose the detected logo_cloud section intent');
lcfa_assert_same('comparison', (string) ($comparison_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'comparison prompts should expose the detected comparison section intent');
lcfa_assert_same('timeline', (string) ($timeline_section_suggestion['suggested_payload']['section_intent'] ?? ''), 'timeline prompts should expose the detected timeline section intent');
lcfa_assert_same('replace_hero', (string) ($replace_hero_section_suggestion['suggested_payload']['section_operation'] ?? ''), 'hero replacement prompts should request replace_hero instead of a generic prepend');
lcfa_assert_same('before_footer', (string) ($before_footer_section_suggestion['suggested_payload']['section_operation'] ?? ''), 'before-footer prompts should request before_footer instead of a generic append');
lcfa_assert_same('after_selected_section', (string) ($after_selected_section_suggestion['suggested_payload']['section_operation'] ?? ''), 'selected-section prompts should request after_selected_section');
lcfa_assert_same('pricing', (string) ($after_selected_section_suggestion['suggested_payload']['selected_section_anchor']['id'] ?? ''), 'selected-section suggestions should preserve the editor-provided section anchor');
lcfa_assert_same('split-reference', (string) ($visual_reference_section_suggestion['suggested_payload']['visual_reference']['layout'] ?? ''), 'visual-reference suggestions should derive a layout profile from screenshot dimensions');
lcfa_assert_contains('visual reference', strtolower(implode("\n", (array) ($visual_reference_section_suggestion['reasons'] ?? []))), 'visual-reference suggestions should explain that screenshots influence layout');

LCFA_Settings::update_project_brief([
    'project_mode'   => 'from_scratch',
    'brand_name'     => 'Consultala',
    'sector'         => 'Consulting',
    'tone'           => 'Precise',
    'logo_status'    => 'to_generate',
    'required_pages' => "Home\nAbout",
    'notes'          => 'Logo still missing.',
]);

$generated_advisory_plan_response = $rest_api->generate_genesis_plan(new WP_REST_Request([
    'project_mode'   => 'from_scratch',
    'brand_name'     => 'Consultala',
    'sector'         => 'Consulting',
    'tone'           => 'Precise',
    'logo_status'    => 'to_generate',
    'required_pages' => "Home\nAbout",
    'notes'          => 'Logo still missing.',
]));

lcfa_assert_same(200, $generated_advisory_plan_response->get_status(), 'generate_genesis_plan should support advisory-task execution tests');

$advisory_task_response = $rest_api->execute_genesis_task(new WP_REST_Request([
    'task_id'  => 'brand-logo',
    'thread_id'=> 'default',
]));
$advisory_task_payload = $advisory_task_response->get_data();

lcfa_assert_same(200, $advisory_task_response->get_status(), 'execute_genesis_task should return success for advisory Genesis tasks');
lcfa_assert_same('brand-logo', (string) ($advisory_task_payload['task_id'] ?? ''), 'execute_genesis_task should return the executed advisory task id');
lcfa_assert_true(!empty($advisory_task_payload['result']['data']['advisory']), 'execute_genesis_task should expose advisory metadata for payload-less Genesis tasks');
lcfa_assert_same('applied', (string) ($advisory_task_payload['execution_plan']['progress']['tasks']['brand-logo']['status'] ?? ''), 'execute_genesis_task should mark advisory tasks as applied once acknowledged');
lcfa_assert_contains('logo', strtolower((string) ($advisory_task_payload['message'] ?? '')), 'execute_genesis_task should surface the advisory task guidance message');
lcfa_assert_true(($picowind_rules['allow_page_level_inline_script'] ?? null) === true, 'Picowind output rules should allow small page-level scripts when needed');
lcfa_assert_same('footer', $picowind_rules['page_level_script_placement'] ?? '', 'Picowind page-level scripts should be placed at the end of the page');
lcfa_assert_true(($picowind_rules['prefer_daisyui_components'] ?? null) === true, 'Picowind output rules should prefer DaisyUI components first');
lcfa_assert_true(($picowind_rules['allow_external_libraries'] ?? null) === true, 'Picowind output rules should allow external libraries when necessary');
lcfa_assert_contains('JavaScript is allowed when it is necessary for the interaction', implode("\n", (array) ($picowind_rules['notes'] ?? [])), 'Picowind notes should explain that JavaScript is allowed when needed');

$picowind_validation_ok = $command_deck->execute([
    'action'              => 'validate_markup_for_framework',
    'body_html_lines'     => [
        '<main class="mx-auto max-w-6xl px-4 py-12">',
        '  <section class="grid gap-6 md:grid-cols-3"><article class="card bg-base-100 shadow-xl"><div class="card-body"><h2 class="card-title">Team</h2></div></article></section>',
        '</main>',
    ],
    'footer_script_lines' => [
        '(() => {',
        '  console.log("pricing ok");',
        '})();',
    ],
]);

lcfa_assert_true($picowind_validation_ok['ok'] === true, 'validate_markup_for_framework should succeed for Tailwind/DaisyUI-friendly markup');
lcfa_assert_true(($picowind_validation_ok['data']['valid'] ?? false) === true, 'validate_markup_for_framework should mark Tailwind/DaisyUI-friendly markup as valid');
lcfa_assert_same('picowind', $picowind_validation_ok['data']['framework'] ?? '', 'validate_markup_for_framework should report the active framework');

$applied_header_content = (string) $GLOBALS['lcfa_test_posts'][43]->post_content;
$GLOBALS['lcfa_test_posts'][43]->post_content = '<header><nav class="navbar navbar-expand-lg"><a class="navbar-brand" href="/">Brand</a><button class="navbar-toggler" data-bs-toggle="collapse"></button><div class="navbar-collapse"><div class="row"><div class="col-md-6">Nav</div></div></div></nav></header>';

$picowind_validation_shell_warning = $command_deck->execute([
    'action'      => 'validate_markup_for_framework',
    'body_html'   => '<section class="mx-auto max-w-6xl px-4 py-12"><article class="card bg-base-100 shadow"><div class="card-body">Team</div></article></section>',
    'footer_html' => '',
]);

$GLOBALS['lcfa_test_posts'][43]->post_content = $applied_header_content;

lcfa_assert_true($picowind_validation_shell_warning['ok'] === true, 'global shell conflicts should not make valid page markup fail validation');
lcfa_assert_true(($picowind_validation_shell_warning['data']['valid'] ?? false) === true, 'valid page markup should remain valid when only the global shell conflicts');
lcfa_assert_contains('global header partial', implode("\n", (array) ($picowind_validation_shell_warning['warnings'] ?? [])), 'validation should warn when the Picowind global header still contains Bootstrap markup');
lcfa_assert_contains('data-bs-*', implode(',', (array) ($picowind_validation_shell_warning['data']['global_shell']['parts']['header']['signals'] ?? [])), 'global shell diagnostics should expose Bootstrap data attribute signals');

$picowind_validation_fail = $command_deck->execute([
    'action'  => 'validate_markup_for_framework',
    'content' => '<main><div class="container"><div class="row"><div class="col-md-6"><a class="btn btn-primary">Buy</a></div></div></div></main>',
]);

lcfa_assert_true($picowind_validation_fail['ok'] === true, 'validate_markup_for_framework should return a structured response even when markup is invalid');
lcfa_assert_true(($picowind_validation_fail['data']['valid'] ?? true) === false, 'validate_markup_for_framework should flag clearly Bootstrap-like Picowind markup as invalid');
lcfa_assert_contains('row', implode(',', (array) ($picowind_validation_fail['data']['signals'] ?? [])), 'validate_markup_for_framework should expose the matched framework conflict signals');
lcfa_assert_contains('Picowind', implode("\n", array_merge([(string) ($picowind_validation_fail['message'] ?? '')], (array) ($picowind_validation_fail['warnings'] ?? []))), 'validate_markup_for_framework should explain the active framework conflict');

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
lcfa_assert_same('page-templates/empty.php', $GLOBALS['lcfa_test_post_meta'][100]['_wp_page_template'] ?? '', 'page_upsert should assign the Empty Page template when it is available');
lcfa_assert_same('Hero', $GLOBALS['lcfa_test_posts'][100]->post_content, 'page_upsert should strip a generated outer main wrapper because LiveCanvas already owns the page shell');

$interactive_page_result = $command_deck->execute([
    'action'  => 'page_upsert',
    'title'   => 'Interactive Pricing',
    'slug'    => 'interactive-pricing',
    'status'  => 'draft',
    'content' => '<main><label class="join"><input type="radio" name="billing" checked></label><script>(function(){console.log("pricing");})();</script></main>',
]);

lcfa_assert_true($interactive_page_result['ok'] === true, 'page_upsert should allow trusted interactive page markup');
lcfa_assert_true(stripos((string) $GLOBALS['lcfa_test_posts'][101]->post_content, '<main') === false, 'page_upsert should strip outer main wrappers while preserving interactive content');
lcfa_assert_contains('<input type="radio"', $GLOBALS['lcfa_test_posts'][101]->post_content, 'page_upsert should preserve form inputs instead of letting KSES strip them');
lcfa_assert_contains('<script>', $GLOBALS['lcfa_test_posts'][101]->post_content, 'page_upsert should preserve page-level scripts instead of saving raw JavaScript text');

$structured_page_result = $command_deck->execute([
    'action'              => 'page_upsert',
    'title'               => 'Structured Pricing',
    'slug'                => 'structured-pricing',
    'status'              => 'draft',
    'body_html_lines'     => [
        '<main>',
        '  <section class="pricing-grid"><h1>Pricing</h1></section>',
        '</main>',
    ],
    'footer_script_lines' => [
        '(() => {',
        '  console.log("structured pricing");',
        '})();',
    ],
]);

lcfa_assert_true($structured_page_result['ok'] === true, 'page_upsert should accept structured page payloads without a raw content blob');
lcfa_assert_true(stripos((string) $GLOBALS['lcfa_test_posts'][102]->post_content, '<main') === false, 'structured page payloads should strip generated outer main wrappers');
lcfa_assert_contains('<section class="pricing-grid">', $GLOBALS['lcfa_test_posts'][102]->post_content, 'structured page payloads should persist body_html_lines into post_content');
lcfa_assert_contains('<script>', $GLOBALS['lcfa_test_posts'][102]->post_content, 'structured page payloads should wrap footer_script_lines in a script tag');
lcfa_assert_contains('structured pricing', $GLOBALS['lcfa_test_posts'][102]->post_content, 'structured page payloads should preserve footer_script_lines contents');

$starter_create_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'title'             => 'Starter Without Main',
    'slug'              => 'starter-without-main',
    'status'            => 'draft',
    'section_intent'    => 'hero',
    'section_operation' => 'append',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Create a starter hero section without adding a main wrapper.',
]);

lcfa_assert_true($starter_create_result['ok'] === true, 'page_upsert should allow creating a starter page without explicit content');
lcfa_assert_contains('lcfa-section--hero', $GLOBALS['lcfa_test_posts'][103]->post_content, 'starter page creation should persist the generated hero section');
lcfa_assert_true(stripos((string) $GLOBALS['lcfa_test_posts'][103]->post_content, '<main') === false, 'starter page creation should never add a synthetic main wrapper');

$section_preview_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $audit_page_id,
    'section_intent'    => 'hero',
    'section_operation' => 'prepend',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Fammi una hero più chiara per questa pagina.',
    'dry_run'           => true,
]);

lcfa_assert_true($section_preview_result['ok'] === true, 'page_upsert should generate a preview for section starter payloads without explicit content');
lcfa_assert_contains('Existing page', $section_preview_result['existing_html'] ?? '', 'section starter previews should keep the current page HTML as the diff baseline');
lcfa_assert_contains('Forge AI starter', $section_preview_result['proposed_html'] ?? '', 'section starter previews should synthesize starter HTML when the payload only carries section intent');
lcfa_assert_contains('lcfa-section--hero', $section_preview_result['proposed_html'] ?? '', 'section starter previews should mark the generated hero section');
lcfa_assert_contains('Consultala', $section_preview_result['proposed_html'] ?? '', 'section starter previews should use the project brief brand name in generated section copy');
lcfa_assert_contains('consulting', strtolower((string) ($section_preview_result['proposed_html'] ?? '')), 'section starter previews should use the project brief sector in generated section copy');
lcfa_assert_true($section_preview_result['diff_html'] !== '', 'section starter previews should generate a diff against the current page content');

$section_apply_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $audit_page_id,
    'section_intent'    => 'pricing',
    'section_operation' => 'append',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Aggiungi una pricing con tre piani per questa pagina.',
]);

lcfa_assert_true($section_apply_result['ok'] === true, 'page_upsert should apply section starter payloads without explicit content');
lcfa_assert_contains('Existing page', $GLOBALS['lcfa_test_posts'][$audit_page_id]->post_content, 'section starter applies should preserve the existing page markup');
lcfa_assert_contains('lcfa-section--pricing', $GLOBALS['lcfa_test_posts'][$audit_page_id]->post_content, 'section starter applies should append the generated pricing section markup');
lcfa_assert_contains('Forge AI starter', $GLOBALS['lcfa_test_posts'][$audit_page_id]->post_content, 'section starter applies should persist the generated starter copy');

$faq_apply_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $audit_page_id,
    'section_intent'    => 'faq',
    'section_operation' => 'append',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Aggiungi una sezione FAQ essenziale per questa pagina.',
]);

lcfa_assert_true($faq_apply_result['ok'] === true, 'page_upsert should support FAQ section starters');
lcfa_assert_contains('lcfa-section--faq', $GLOBALS['lcfa_test_posts'][$audit_page_id]->post_content, 'FAQ section starters should persist faq-specific section markup');

$replace_hero_page_id = 46;
$GLOBALS['lcfa_test_posts'][$replace_hero_page_id] = new WP_Post([
    'ID'           => $replace_hero_page_id,
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Replace Hero Test',
    'post_name'    => 'replace-hero-test',
    'post_content' => '<main><section class="lcfa-section-starter lcfa-section--hero"><h1>Old hero</h1></section><section><p>Body copy</p></section></main>',
]);
$GLOBALS['lcfa_test_post_meta'][$replace_hero_page_id]['_lc_livecanvas_enabled'] = '1';

$replace_hero_apply_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $replace_hero_page_id,
    'section_intent'    => 'hero',
    'section_operation' => 'replace_hero',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Sostituisci la hero di questa pagina con una versione piu pulita.',
]);

lcfa_assert_true($replace_hero_apply_result['ok'] === true, 'page_upsert should support replace_hero section operations');
lcfa_assert_true(substr_count($GLOBALS['lcfa_test_posts'][$replace_hero_page_id]->post_content, 'lcfa-section--hero') === 1, 'replace_hero should keep a single hero section instead of duplicating it');
lcfa_assert_true(strpos($GLOBALS['lcfa_test_posts'][$replace_hero_page_id]->post_content, 'Old hero') === false, 'replace_hero should replace the previous hero markup');

$before_footer_page_id = 47;
$GLOBALS['lcfa_test_posts'][$before_footer_page_id] = new WP_Post([
    'ID'           => $before_footer_page_id,
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Before Footer Test',
    'post_name'    => 'before-footer-test',
    'post_content' => '<main><section><p>Body copy</p></section><footer><p>Footer copy</p></footer></main>',
]);
$GLOBALS['lcfa_test_post_meta'][$before_footer_page_id]['_lc_livecanvas_enabled'] = '1';

$before_footer_apply_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $before_footer_page_id,
    'section_intent'    => 'cta',
    'section_operation' => 'before_footer',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Aggiungi una CTA prima del footer di questa pagina.',
]);

lcfa_assert_true($before_footer_apply_result['ok'] === true, 'page_upsert should support before_footer section operations');
lcfa_assert_true(preg_match('/lcfa-section--cta.*<footer>/s', $GLOBALS['lcfa_test_posts'][$before_footer_page_id]->post_content) === 1, 'before_footer should insert the generated section immediately before the footer');

$after_selected_page_id = 48;
$GLOBALS['lcfa_test_posts'][$after_selected_page_id] = new WP_Post([
    'ID'           => $after_selected_page_id,
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'After Selected Test',
    'post_name'    => 'after-selected-test',
    'post_content' => '<main><section id="intro"><p>Intro copy</p></section><section id="pricing"><p>Pricing copy</p></section><section id="faq"><p>FAQ copy</p></section></main>',
]);
$GLOBALS['lcfa_test_post_meta'][$after_selected_page_id]['_lc_livecanvas_enabled'] = '1';

$after_selected_apply_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $after_selected_page_id,
    'section_intent'    => 'timeline',
    'section_operation' => 'after_selected_section',
    'content_strategy'  => 'section_starter',
    'selected_section_anchor' => [
        'tag_name'      => 'section',
        'id'            => 'pricing',
        'class_token'   => '',
        'section_index' => 1,
    ],
    'user_prompt'       => 'Inserisci una timeline dopo la sezione selezionata.',
]);

lcfa_assert_true($after_selected_apply_result['ok'] === true, 'page_upsert should support after_selected_section operations');
lcfa_assert_true(preg_match('/id="pricing".*lcfa-section--timeline.*id="faq"/s', $GLOBALS['lcfa_test_posts'][$after_selected_page_id]->post_content) === 1, 'after_selected_section should insert the generated section after the selected anchor and before the next section');
lcfa_assert_same('after_selected_section', (string) ($after_selected_apply_result['data']['section_operation'] ?? ''), 'after_selected_section results should expose the resolved section operation');

$visual_reference_preview_result = $command_deck->execute([
    'action'            => 'page_upsert',
    'post_id'           => $audit_page_id,
    'section_intent'    => 'hero',
    'section_operation' => 'prepend',
    'content_strategy'  => 'section_starter',
    'user_prompt'       => 'Fammi una hero come nello screenshot.',
    'visual_reference'  => [
        'enabled'     => true,
        'count'       => 1,
        'orientation' => 'landscape',
        'layout'      => 'split-reference',
    ],
    'dry_run'           => true,
]);

lcfa_assert_true($visual_reference_preview_result['ok'] === true, 'page_upsert should generate visual-reference-aware previews');
lcfa_assert_contains('data-lcfa-visual-reference="split-reference"', $visual_reference_preview_result['proposed_html'] ?? '', 'visual-reference previews should mark the generated section with the reference layout');
lcfa_assert_contains('attached visual reference', strtolower((string) ($visual_reference_preview_result['proposed_html'] ?? '')), 'visual-reference previews should explain that the screenshot is shaping the starter');

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
lcfa_assert_same('page-templates/empty.php', $GLOBALS['lcfa_test_post_meta'][100]['_wp_page_template'] ?? '', 'page_upsert update should keep the Empty Page template assigned');

$page_content_before_empty_upsert = (string) $GLOBALS['lcfa_test_posts'][100]->post_content;
$empty_upsert_result = $command_deck->execute([
    'action'      => 'page_upsert',
    'post_id'     => 100,
    'target_id'   => 100,
    'status'      => 'publish',
    'user_prompt' => 'Change the font family of this page.',
]);

lcfa_assert_true($empty_upsert_result['ok'] === false, 'page_upsert should reject empty generated HTML for an existing page');
lcfa_assert_same($page_content_before_empty_upsert, (string) $GLOBALS['lcfa_test_posts'][100]->post_content, 'page_upsert should not erase existing page HTML when no content was generated');

$header_content_before_empty_update = (string) $GLOBALS['lcfa_test_posts'][43]->post_content;
$empty_header_update_result = $command_deck->execute([
    'action'    => 'update_header',
    'target_id' => 43,
    'variant'   => '1',
]);

lcfa_assert_true($empty_header_update_result['ok'] === false, 'update_header should reject empty generated HTML for an existing header partial');
lcfa_assert_same($header_content_before_empty_update, (string) $GLOBALS['lcfa_test_posts'][43]->post_content, 'update_header should not erase existing header HTML when no content was generated');

$footer_content_before_empty_update = (string) $GLOBALS['lcfa_test_posts'][44]->post_content;
$empty_footer_update_result = $command_deck->execute([
    'action'    => 'update_footer',
    'target_id' => 44,
    'variant'   => '1',
]);

lcfa_assert_true($empty_footer_update_result['ok'] === false, 'update_footer should reject empty generated HTML for an existing footer partial');
lcfa_assert_same($footer_content_before_empty_update, (string) $GLOBALS['lcfa_test_posts'][44]->post_content, 'update_footer should not erase existing footer HTML when no content was generated');

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
lcfa_assert_same(
    'https://example.test/wp-admin/post.php?post=' . (int) ($fallback_edit_result['target_id'] ?? 0) . '&action=edit',
    $fallback_edit_result['edit_url'] ?? '',
    'page_upsert should fall back to admin_url when get_edit_post_link is unavailable'
);

@mkdir($GLOBALS['lcfa_test_theme_root'] . '/picowind-child/page-templates', 0777, true);
@mkdir($GLOBALS['lcfa_test_theme_root'] . '/picowind/page-templates', 0777, true);
@file_put_contents($GLOBALS['lcfa_test_theme_root'] . '/picowind-child/page-templates/empty.php', '<?php // child empty template');
@file_put_contents($GLOBALS['lcfa_test_theme_root'] . '/picowind/page-templates/empty.php', '<?php // parent empty template');

$picowind_bootstrap_result = $command_deck->execute([
    'action'  => 'page_upsert',
    'title'   => 'Wrong Framework Pricing',
    'slug'    => 'wrong-framework-pricing',
    'status'  => 'draft',
    'content' => '<main><div class="container"><div class="row"><div class="col-md-6"><a class="btn btn-primary">Buy</a></div></div></div></main>',
]);

$GLOBALS['lcfa_test_stylesheet'] = $previous_stylesheet;
$GLOBALS['lcfa_test_template'] = $previous_template;

lcfa_assert_true($picowind_bootstrap_result['ok'] === false, 'page_upsert should reject clearly Bootstrap-oriented markup when Picowind is active');
lcfa_assert_contains('Picowind', $picowind_bootstrap_result['message'] ?? '', 'framework mismatch error should explain the active stack');

LCFA_Settings::update_connections(array_merge(LCFA_Settings::connection_defaults(), [
    'local_bridge_url' => 'https://example.test/wp-json/lcfa/v1/',
    'mcp_token'        => 'test-token',
]));

$GLOBALS['lcfa_test_remote_get_map']['https://example.test/wp-json/lcfa/v1/mcp/health'] = [
    'response' => ['code' => 200],
    'body'     => wp_json_encode([
        'ok'            => true,
        'plugin'        => 'livecanvas-forge-ai',
        'script_exists' => true,
        'wp_root'       => '/Users/commander/Studio/consultala',
        'rest_base'     => 'https://example.test/wp-json/lcfa/v1/',
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

$previous_home = getenv('HOME');
$claude_home = sys_get_temp_dir() . '/lcfa-claude-home';
$claude_config_dir = $claude_home . '/Library/Application Support/Claude';
$claude_config_path = $claude_config_dir . '/claude_desktop_config.json';

@mkdir($claude_config_dir, 0777, true);
@unlink($claude_config_path);
putenv('HOME=' . $claude_home);

LCFA_Settings::update_connections(array_merge(LCFA_Settings::connection_defaults(), [
    'preferred_client'          => 'claude',
    'claude_connection_target'  => 'desktop_app',
    'local_bridge_url'          => 'https://example.test/wp-json/lcfa/v1/',
    'mcp_token'                 => 'test-token',
    'connection_mode'           => 'local',
]));

$claude_desktop_missing_config = $connection_tester->run_checks([
    'mode' => 'local',
]);

lcfa_assert_true($claude_desktop_missing_config['ok'] === false, 'Claude Desktop local smoke should fail until the host config contains the livecanvas-forge entry');
lcfa_assert_same('Claude Desktop registration', $claude_desktop_missing_config['checks']['client_registration']['label'] ?? '', 'Claude Desktop smoke test should expose a dedicated registration check');
lcfa_assert_true(strpos((string) ($claude_desktop_missing_config['checks']['client_registration']['message'] ?? ''), 'claude_desktop_config.json') !== false, 'missing Claude Desktop config should explain which file still needs to be updated');

file_put_contents($claude_config_path, wp_json_encode([
    'mcpServers' => [
        'livecanvas-forge' => [
            'type'    => 'stdio',
            'command' => 'node',
            'args'    => ['wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js', '--transport=stdio'],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$claude_desktop_registered = $connection_tester->run_checks([
    'mode' => 'local',
]);

lcfa_assert_true($claude_desktop_registered['ok'] === true, 'Claude Desktop local smoke should pass once the host config contains the livecanvas-forge registration');
lcfa_assert_true(!empty($claude_desktop_registered['checks']['client_registration']['ok']), 'Claude Desktop registration check should report success when the entry exists');

if ($previous_home === false) {
    putenv('HOME');
} else {
    putenv('HOME=' . $previous_home);
}

lcfa_assert_same('https://example.test/landing-page-1-updated/', $updated_page_result['frontend_url'] ?? '', 'page_upsert update should refresh frontend_url after slug changes');
lcfa_assert_same('Updated Hero', $GLOBALS['lcfa_test_posts'][100]->post_content, 'page_upsert update should persist new content without a generated outer main wrapper');

$global_shell_create = $command_deck->execute([
    'action'      => 'global_shell_apply',
    'variant'     => '2',
    'header_html' => '<header>Variant Two Header</header>',
    'footer_html' => '<footer>Variant Two Footer</footer>',
]);

lcfa_assert_true($global_shell_create['ok'] === true, 'global_shell_apply should create missing header/footer variants');
lcfa_assert_same('create', (string) ($global_shell_create['data']['parts']['header']['operation'] ?? ''), 'global_shell_apply should report create for missing header variants');
lcfa_assert_true((int) ($global_shell_create['data']['parts']['header']['target_id'] ?? 0) > 0, 'created global shell headers should return a target id');
lcfa_assert_same('2', (string) ($GLOBALS['lcfa_test_post_meta'][(int) $global_shell_create['data']['parts']['header']['target_id']]['is_header'] ?? ''), 'created global shell headers should persist the requested variant');
lcfa_assert_same('2', (string) ($GLOBALS['lcfa_test_post_meta'][(int) $global_shell_create['data']['parts']['footer']['target_id']]['is_footer'] ?? ''), 'created global shell footers should persist the requested variant');

$variant_inventory = (new LCFA_Inventory($environment))->get_inventory();
$variant_header_id = (int) ($global_shell_create['data']['parts']['header']['target_id'] ?? 0);
$variant_footer_id = (int) ($global_shell_create['data']['parts']['footer']['target_id'] ?? 0);
$variant_header = [];
$variant_footer = [];

foreach ((array) ($variant_inventory['header_partials'] ?? []) as $partial) {
    if (is_array($partial) && (int) ($partial['id'] ?? 0) === $variant_header_id) {
        $variant_header = $partial;
        break;
    }
}

foreach ((array) ($variant_inventory['footer_partials'] ?? []) as $partial) {
    if (is_array($partial) && (int) ($partial['id'] ?? 0) === $variant_footer_id) {
        $variant_footer = $partial;
        break;
    }
}

$other_partial_ids = array_map(static function (array $partial): int {
    return (int) ($partial['id'] ?? 0);
}, array_filter((array) ($variant_inventory['other_partials'] ?? []), 'is_array'));

lcfa_assert_same('2', (string) ($variant_header['variant'] ?? ''), 'inventory should include created header variants beyond variant 1');
lcfa_assert_same('header', (string) ($variant_header['partial_type'] ?? ''), 'inventory should classify created header variants as headers');
lcfa_assert_same('2', (string) ($variant_footer['variant'] ?? ''), 'inventory should include created footer variants beyond variant 1');
lcfa_assert_same('footer', (string) ($variant_footer['partial_type'] ?? ''), 'inventory should classify created footer variants as footers');
lcfa_assert_true(!in_array($variant_header_id, $other_partial_ids, true), 'header variants beyond variant 1 should not leak into other partials');
lcfa_assert_true(!in_array($variant_footer_id, $other_partial_ids, true), 'footer variants beyond variant 1 should not leak into other partials');

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
