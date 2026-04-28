<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');
define('MINUTE_IN_SECONDS', 60);

final class WP_Post {
    public int $ID = 0;
    public string $post_type = 'page';
    public string $post_title = '';

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

final class LCFA_Test_Redirect extends RuntimeException {
    public string $url;

    public function __construct(string $url) {
        $this->url = $url;
        parent::__construct($url);
    }
}

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_transients'] = [];
$_GET = [];

function __(string $text, string $domain = ''): string {
    return $text;
}

function _n(string $single, string $plural, int $number, string $domain = ''): string {
    return $number === 1 ? $single : $plural;
}

function esc_html(string $value): string {
    return $value;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_attr(string $value): string {
    return $value;
}

function esc_attr__(string $text, string $domain = ''): string {
    return $text;
}

function esc_url(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES);
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url): string {
    return $url . '?' . http_build_query($args);
}

function selected($selected, $current, bool $display = true): string {
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function wp_create_nonce(string $action = ''): string {
    return 'nonce';
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
    $markup = '<input type="hidden" name="' . $name . '" value="nonce">';

    if ($display) {
        echo $markup;
    }

    return $markup;
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
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
    return array_merge((array) $defaults, (array) $args);
}

function sanitize_key(string $value): string {
    $value = strtolower($value);
    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
}

function sanitize_text_field(string $value): string {
    return trim($value);
}

function sanitize_textarea_field(string $value): string {
    return trim($value);
}

function wp_unslash($value) {
    return $value;
}

function sanitize_html_class(string $value, string $fallback = ''): string {
    $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
    return $sanitized !== '' ? $sanitized : $fallback;
}

function absint($value): int {
    return max(0, (int) $value);
}

function current_time(string $type = 'mysql', bool $gmt = false): string {
    return $type === 'mysql' ? gmdate('Y-m-d H:i:s') : gmdate('c');
}

function get_date_from_gmt(string $date_string, string $format = 'Y-m-d H:i:s'): string {
    $timestamp = strtotime($date_string);
    return $timestamp ? gmdate($format, $timestamp) : $date_string;
}

function wp_trim_words(string $text, int $num_words = 55, ?string $more = null): string {
    $words = preg_split('/\s+/', trim($text)) ?: [];
    if (count($words) <= $num_words) {
        return trim($text);
    }

    $tail = $more !== null ? $more : '...';
    return implode(' ', array_slice($words, 0, $num_words)) . $tail;
}

function current_user_can(string $capability): bool {
    return true;
}

function get_current_user_id(): int {
    return 1;
}

function check_admin_referer(string $action = '', string $query_arg = '_wpnonce'): void {}

function wp_safe_redirect(string $location, int $status = 302, string $x_redirect_by = 'WordPress'): bool {
    throw new LCFA_Test_Redirect($location);
}

function wp_die(string $message = ''): void {
    throw new RuntimeException($message);
}

function get_the_title(int $post_id): string {
    return 'Editor Chat Test';
}

function get_post_meta(int $post_id, string $key, bool $single = false): string {
    return $key === '_lc_livecanvas_enabled' ? '1' : '';
}

function wp_generate_password(int $length = 12, bool $special_chars = false, bool $extra_special_chars = false): string {
    return str_repeat('a', $length);
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'detected_framework' => 'picostrap',
            'site_mode' => 'local',
            'windpress_active' => false,
        ];
    }

    public function get_livecanvas_menu_slug(): ?string {
        return null;
    }

    public function find_plugin_file_by_slug(string $slug): string {
        return '';
    }
}

final class LCFA_Inventory {
    public function resolve_partial_post_id(string $meta_key, string $value): int {
        return 0;
    }
}

final class LCFA_Remote_Client {
    public function get_status(): array {
        return ['configured' => false];
    }
}

final class LCFA_Context_Builder {
    public function get_mcp_status(): array {
        return [
            'local_bridge' => [
                'build_available' => false,
            ],
        ];
    }
}

final class LCFA_Command_Deck {
    public function get_actions(): array {
        return [
            'page_upsert' => [
                'label' => 'Page upsert',
            ],
            'site_audit' => [
                'label' => 'Site audit',
            ],
        ];
    }

    public function execute(array $payload): array {
        return [
            'ok' => true,
            'action' => (string) ($payload['action'] ?? ''),
            'mode' => !empty($payload['dry_run']) ? 'preview' : 'apply',
            'execution_target' => (string) ($payload['execution_target'] ?? 'local'),
            'message' => 'Stub command executed.',
            'summary' => 'Stub command executed.',
            'target_type' => '',
            'target_id' => 0,
            'target_title' => '',
            'frontend_url' => '',
            'edit_url' => '',
            'diff_html' => '',
            'existing_html' => '',
            'proposed_html' => '',
            'inventory' => null,
            'warnings' => [],
            'data' => [],
        ];
    }
}

require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

LCFA_Settings::append_thread_message('default', [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'content' => 'Applied inline hero update.',
    'meta' => [
        'mode' => 'apply',
        'frontend_url' => 'https://example.test/pricing/',
        'edit_url' => 'https://example.test/wp-admin/post.php?post=42&action=edit',
    ],
]);
LCFA_Settings::append_thread_message('ideas', [
    'role' => 'suggestion_result',
    'label' => 'Suggestion ready',
    'content' => 'Suggested action: site_audit.',
    'meta' => [
        'action' => 'site_audit',
        'execution_target' => 'local',
    ],
]);

global $post;
$post = new WP_Post([
    'ID' => 42,
    'post_type' => 'page',
    'post_title' => 'Editor Chat Test',
]);

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

(new ReflectionProperty('LCFA_Admin', 'environment'))->setValue($admin, new LCFA_Environment());
(new ReflectionProperty('LCFA_Admin', 'inventory'))->setValue($admin, new LCFA_Inventory());
(new ReflectionProperty('LCFA_Admin', 'remote_client'))->setValue($admin, new LCFA_Remote_Client());
(new ReflectionProperty('LCFA_Admin', 'context_builder'))->setValue($admin, new LCFA_Context_Builder());
(new ReflectionProperty('LCFA_Admin', 'command_deck'))->setValue($admin, new LCFA_Command_Deck());

ob_start();
$admin->render_editor_bridge_styles();
$editor_header_markup = (string) ob_get_clean();
$plugin_root = dirname(__DIR__, 2);
$editor_chat_css = (string) file_get_contents($plugin_root . '/assets/editor-chat.css');
$editor_chat_js = (string) file_get_contents($plugin_root . '/assets/editor-chat.js');

lcfa_assert_contains('assets/editor-chat.css', $editor_header_markup, 'editor bridge header should load the dedicated editor chat stylesheet asset');
lcfa_assert_contains('assets/editor-chat.js', $editor_header_markup, 'editor bridge header should load the dedicated editor chat script asset');
lcfa_assert_not_contains('lcfa-editor-bridge-styles', $editor_header_markup, 'editor bridge header should no longer inline the chat stylesheet');
lcfa_assert_not_contains('.lcfa-editor-shell{position:fixed', $editor_header_markup, 'editor bridge header should no longer emit raw inline CSS rules');
lcfa_assert_contains('.lcfa-editor-shell{position:fixed;top:', $editor_chat_css, 'editor bridge stylesheet asset should pin the launcher near the top edge instead of the bottom-right overlay zone');
lcfa_assert_not_contains('.lcfa-editor-shell{position:fixed;right:20px;bottom:20px', $editor_chat_css, 'editor bridge stylesheet asset should no longer anchor the launcher over the bottom-right section controls');
lcfa_assert_contains('width:392px', $editor_chat_css, 'editor bridge stylesheet asset should slim the drawer width for a more balanced editor footprint');
lcfa_assert_contains('min-height:132px', $editor_chat_css, 'editor bridge stylesheet asset should enlarge the request textarea because the prompt composer is the primary interaction');
lcfa_assert_contains('.lcfa-editor-bridge__details>summary', $editor_chat_css, 'editor bridge stylesheet asset should support collapsible secondary sections for a leaner window');
lcfa_assert_contains('.lcfa-editor-bridge__head-link.is-icon-only', $editor_chat_css, 'editor bridge stylesheet asset should support a minimal icon-only Command Deck shortcut');
lcfa_assert_contains('.lcfa-editor-bridge__close .lcfa-icon', $editor_chat_css, 'editor bridge stylesheet asset should make the close/power control icon clearly visible');
lcfa_assert_contains('.lcfa-editor-bridge__attachment-preview-card', $editor_chat_css, 'editor bridge stylesheet asset should style a dedicated uploaded-image preview card');
lcfa_assert_contains('.lcfa-editor-bridge__attachment-preview-card[hidden]{display:none!important}', $editor_chat_css, 'editor bridge stylesheet asset should force-hide the upload preview card until an image is attached');
lcfa_assert_contains('.lcfa-editor-bridge__connection', $editor_chat_css, 'editor bridge stylesheet asset should style the connection status badge in the drawer header');
lcfa_assert_contains('var shell=document.querySelector("[data-lcfa-editor-shell]")', $editor_chat_js, 'editor bridge script asset should bootstrap the drawer runtime');
lcfa_assert_contains('commandExecutionEndpoint', $editor_chat_js, 'editor bridge runtime asset should support async command execution endpoints');
lcfa_assert_contains('new FileReader()', $editor_chat_js, 'editor bridge runtime asset should support screenshot attachments through FileReader');
lcfa_assert_contains('attachmentTriggerButton.addEventListener("click"', $editor_chat_js, 'editor bridge runtime asset should open the upload picker from a dedicated button');
lcfa_assert_not_contains('attachmentDropzone.addEventListener("dragover"', $editor_chat_js, 'editor bridge runtime asset should no longer depend on drag-and-drop screenshot uploads');
lcfa_assert_contains('attachmentPreviewImage.addEventListener("error"', $editor_chat_js, 'editor bridge runtime asset should gracefully hide a broken screenshot preview image without dropping the attachment');

ob_start();
$admin->render_editor_bridge();
$markup = (string) ob_get_clean();

lcfa_assert_contains('http://example.test/wp-json/lcfa/v1/chat/send', $markup, 'editor bridge should post prompts to the dedicated chat/send endpoint');
lcfa_assert_contains('http://example.test/wp-json/lcfa/v1/chat/thread', $markup, 'editor bridge should expose the dedicated chat/thread endpoint for thread management');
lcfa_assert_contains('http://example.test/wp-json/lcfa/v1/command/execution', $markup, 'editor bridge should expose the dedicated async command execution endpoint');
lcfa_assert_not_contains('http://example.test/wp-json/lcfa/v1/command/suggest', $markup, 'editor bridge should no longer post prompts directly to command/suggest');
lcfa_assert_contains('http://example.test/wp-json/lcfa/v1/command', $markup, 'editor bridge should expose the command endpoint for inline apply');
lcfa_assert_not_contains('lcfa-editor-bridge__stack', $markup, 'editor bridge should remove the high-visibility framework/site stack because the user already knows the installed stack');
lcfa_assert_contains('data-lcfa-editor-thread-log', $markup, 'editor bridge should render a thread log container');
lcfa_assert_contains('data-lcfa-editor-thread-empty', $markup, 'editor bridge should render an empty-state container for thread messages');
lcfa_assert_contains('data-lcfa-editor-status', $markup, 'editor bridge should render a dedicated conversation status node');
lcfa_assert_contains('data-state="applied"', $markup, 'editor bridge should derive the initial conversation state from the latest persisted thread message');
lcfa_assert_not_contains('data-lcfa-editor-preview', $markup, 'editor bridge should remove the inline preview control from the composer');
lcfa_assert_not_contains('data-lcfa-editor-apply', $markup, 'editor bridge should remove the inline apply control from the composer');
lcfa_assert_contains('data-lcfa-editor-thread-create', $markup, 'editor bridge should render a create-thread control inside the drawer');
lcfa_assert_contains('data-lcfa-editor-thread-duplicate', $markup, 'editor bridge should render a duplicate-thread control inside the drawer');
lcfa_assert_contains('data-lcfa-editor-thread-clear', $markup, 'editor bridge should render a clear-thread control inside the drawer');
lcfa_assert_contains('data-lcfa-editor-thread-rename', $markup, 'editor bridge should render a rename-thread control inside the drawer');
lcfa_assert_contains('data-lcfa-editor-thread-delete', $markup, 'editor bridge should render a delete-thread control inside the drawer');
lcfa_assert_contains('"ideas":{"id":"ideas"', $markup, 'editor bridge should preload secondary thread payloads for client-side switching');
lcfa_assert_contains('Suggested action: site_audit.', $markup, 'editor bridge should carry the secondary thread message payload into the client config');
lcfa_assert_contains('Duplicate current', $markup, 'editor bridge should label the duplicate-thread control clearly');
lcfa_assert_contains('Clear current', $markup, 'editor bridge should label the clear-thread control clearly');
lcfa_assert_contains('Rename current', $markup, 'editor bridge should label the rename-thread control clearly');
lcfa_assert_contains('Delete current', $markup, 'editor bridge should label the delete-thread control clearly');
lcfa_assert_contains('Describe the change you want on this page', $markup, 'editor bridge should lead with a prompt-first helper so the user understands the primary interaction');
lcfa_assert_contains('Send', $markup, 'editor bridge should label the primary action as a simple send step');
lcfa_assert_not_contains('>Preview<', $markup, 'editor bridge should not expose a secondary preview button in the composer');
lcfa_assert_not_contains('>Apply<', $markup, 'editor bridge should not expose a secondary apply button in the composer');
lcfa_assert_contains('data-lcfa-editor-attachment', $markup, 'editor bridge should expose a screenshot attachment input');
lcfa_assert_contains('data-lcfa-editor-attachment-trigger', $markup, 'editor bridge should expose a dedicated upload-image trigger');
lcfa_assert_contains('data-lcfa-editor-attachment-preview', $markup, 'editor bridge should expose a screenshot preview surface');
lcfa_assert_contains('lcfa-icon-command', $markup, 'editor bridge should add an icon to the Command Deck shortcut');
lcfa_assert_contains('lcfa-icon-power', $markup, 'editor bridge should render a dedicated power icon for the drawer close control');
lcfa_assert_contains('data-lcfa-editor-attachment-preview-image', $markup, 'editor bridge should render a real screenshot preview image after upload');
lcfa_assert_contains('data-lcfa-editor-result-diff', $markup, 'editor bridge should expose a diff preview support pane');
lcfa_assert_contains('data-lcfa-editor-result-existing', $markup, 'editor bridge should expose a current markup support pane');
lcfa_assert_contains('data-lcfa-editor-result-proposed', $markup, 'editor bridge should expose a proposed markup support pane');
lcfa_assert_contains('data-lcfa-editor-quick-actions', $markup, 'editor bridge should collapse quick actions into a secondary details panel');
lcfa_assert_contains('data-lcfa-editor-session-details', $markup, 'editor bridge should move thread and execution settings into a secondary session details panel');
lcfa_assert_contains('data-lcfa-editor-support-details', $markup, 'editor bridge should wrap support details inside a collapsible details panel');
lcfa_assert_contains('data-lcfa-editor-target-summary', $markup, 'editor bridge should keep the target summary visible in a compact header slot');
lcfa_assert_contains('Support details', $markup, 'editor bridge should de-emphasize the result panel as a support details section');
lcfa_assert_not_contains('<div class="lcfa-editor-bridge__label">Current target</div>', $markup, 'editor bridge should remove the standalone current target section to save vertical space');
lcfa_assert_not_contains('Workflow note', $markup, 'editor bridge should remove non-essential workflow note copy from the drawer');
lcfa_assert_contains('You are in:', $markup, 'editor bridge header should orient the user explicitly about the current location');
lcfa_assert_not_contains('Create or update page', $markup, 'editor bridge header should stop repeating the current command action in the target summary');
lcfa_assert_contains('Target: Page', $markup, 'editor bridge header should summarize the current target type for orientation');
lcfa_assert_not_contains('>Command Deck<', $markup, 'editor bridge should render the Command Deck shortcut as an icon-only control');
lcfa_assert_contains('data-lcfa-editor-config', $markup, 'editor bridge should keep the JSON config bootstrap in markup');
lcfa_assert_not_contains('lcfa-editor-bridge-script', $markup, 'editor bridge should no longer inline the editor chat runtime script');
lcfa_assert_not_contains('var shell=document.querySelector("[data-lcfa-editor-shell]")', $markup, 'editor bridge should no longer embed the drawer runtime inline');
lcfa_assert_contains('context_post_id:payload.context_post_id||config.postId||0', $editor_chat_js, 'editor bridge runtime asset should restore the current post context when replaying persisted thread actions');
lcfa_assert_contains('post_id:payload.post_id||config.postId||0', $editor_chat_js, 'editor bridge runtime asset should keep the current post id when replaying persisted thread actions');
lcfa_assert_contains('window.localStorage', $editor_chat_js, 'editor bridge runtime asset should persist the selected thread in browser localStorage when available');
lcfa_assert_contains('lcfa-editor-thread:', $editor_chat_js, 'editor bridge runtime asset should namespace the selected-thread persistence key by editor post id');
lcfa_assert_contains('Request prepared.', $markup, 'editor bridge should expose a dedicated prepared conversation state label');
lcfa_assert_contains('Preview ready. Review the support details below.', $markup, 'editor bridge should expose a dedicated previewed conversation state label');
lcfa_assert_contains('Change applied inline.', $markup, 'editor bridge should expose a dedicated applied conversation state label');
lcfa_assert_contains('The current request failed. Review the support details below.', $markup, 'editor bridge should expose a dedicated failed conversation state label');
lcfa_assert_contains('Queued for inline execution.', $markup, 'editor bridge should expose a queued async execution state label');
lcfa_assert_contains('Running inline execution...', $markup, 'editor bridge should expose a running async execution state label');
lcfa_assert_contains('runs it inline on the current page', $markup, 'editor bridge should explain that the request executes immediately on the current page');
lcfa_assert_contains('lcfa-editor-bridge__connection', $markup, 'editor bridge should render a connection status badge in the header');
lcfa_assert_contains('lcfa-editor-thread-message is-tool_result', $markup, 'editor bridge should preserve tool_result styling for execution messages');
lcfa_assert_contains('View page', $markup, 'editor bridge should render a frontend link for actionable tool_result messages');
lcfa_assert_contains('Edit page', $markup, 'editor bridge should render an edit link for actionable tool_result messages');

$composer_position = strpos($markup, 'data-lcfa-editor-composer');
$thread_log_position = strpos($markup, 'data-lcfa-editor-thread-log');
if ($composer_position === false || $thread_log_position === false || $composer_position >= $thread_log_position) {
    fwrite(STDERR, "editor bridge should render the prompt composer before the thread log so the primary action stays at the top\n");
    exit(1);
}

$render_editor_thread = $admin_reflection->getMethod('render_editor_bridge_thread_message');
ob_start();
$render_editor_thread->invoke($admin, [
    'role' => 'suggestion_result',
    'label' => 'Suggestion ready',
    'time' => '2026-04-16 09:55:00',
    'content' => 'Suggested action: page_upsert.',
    'meta' => [
        'action' => 'page_upsert',
        'execution_target' => 'local',
    ],
    'actions' => [
        [
            'kind' => 'apply',
            'label' => 'Preview',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
                'variant' => '1',
                'dry_run' => true,
            ],
        ],
        [
            'kind' => 'apply',
            'label' => 'Apply',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
                'variant' => '1',
            ],
        ],
        [
            'kind' => 'url',
            'label' => 'Open suggested payload in Command Deck',
            'url' => 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=command&suggest_action=page_upsert',
        ],
    ],
]);
$suggestion_thread_markup = (string) ob_get_clean();
lcfa_assert_same('', trim($suggestion_thread_markup), 'editor thread renderer should suppress suggestion_result messages entirely in the frontend drawer');

ob_start();
$render_editor_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:00:00',
    'content' => 'Applied inline hero update.',
    'meta' => [
        'frontend_url' => 'https://example.test/pricing/',
        'edit_url' => 'https://example.test/wp-admin/post.php?post=42&action=edit',
    ],
    'actions' => [
        [
            'kind' => 'apply',
            'label' => 'Retry',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
            ],
        ],
    ],
]);
$editor_thread_markup = (string) ob_get_clean();
lcfa_assert_contains('lcfa-editor-thread-message is-tool_result', $editor_thread_markup, 'editor thread renderer should keep tool_result messages distinct');
lcfa_assert_contains('View page', $editor_thread_markup, 'editor thread renderer should output a frontend action link for tool_result messages');
lcfa_assert_contains('Edit page', $editor_thread_markup, 'editor thread renderer should output an edit action link for tool_result messages');
lcfa_assert_contains('Retry', $editor_thread_markup, 'editor thread renderer should output persisted recovery actions for failed or retryable tool_result messages');
lcfa_assert_contains('data-lcfa-editor-thread-apply', $editor_thread_markup, 'editor thread renderer should expose tool_result apply actions for JS handling');

$render_command_thread = $admin_reflection->getMethod('render_command_thread_message');
$render_command_thread_panel = $admin_reflection->getMethod('render_command_thread_panel');
$get_thread_actions = $admin_reflection->getMethod('get_thread_message_actions');
$render_command_result = $admin_reflection->getMethod('render_command_result');
$render_command_suggestion = $admin_reflection->getMethod('render_command_suggestion');
ob_start();
$render_command_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:00:00',
    'content' => 'Applied inline hero update.',
    'meta' => [
        'action' => 'page_upsert',
        'ok' => true,
        'target_type' => 'dynamic_template',
        'target_id' => 42,
        'target_title' => 'Pricing Hero',
        'frontend_url' => 'https://example.test/pricing/',
        'edit_url' => 'https://example.test/wp-admin/post.php?post=42&action=edit',
    ],
    'actions' => [
        [
            'kind' => 'apply',
            'label' => 'Retry',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
            ],
        ],
    ],
]);
$command_thread_markup = (string) ob_get_clean();
lcfa_assert_contains('lcfa-thread-message is-tool_result', $command_thread_markup, 'command thread renderer should keep tool_result messages distinct');
lcfa_assert_contains('View page', $command_thread_markup, 'command thread renderer should output a frontend action link for tool_result messages');
lcfa_assert_contains('Open template', $command_thread_markup, 'command thread renderer should output a template action link for dynamic template results');
lcfa_assert_contains('Retry', $command_thread_markup, 'command thread renderer should output persisted recovery actions for tool_result messages');
lcfa_assert_contains('Target: dynamic_template', $command_thread_markup, 'command thread renderer should expose the target type semantically');
lcfa_assert_contains('Label: Pricing Hero', $command_thread_markup, 'command thread renderer should expose the target label semantically');
lcfa_assert_contains('ID: 42', $command_thread_markup, 'command thread renderer should expose the target id semantically');

ob_start();
$render_command_thread_panel->invoke(
    $admin,
    LCFA_Settings::get_thread('ideas'),
    LCFA_Settings::get_thread_summaries(),
    [
        'thread_id' => 'ideas',
        'context_post_id' => 42,
        'genesis_task_id' => 'task-42',
        'user_prompt' => 'Audit header',
    ]
);
$command_thread_panel_markup = (string) ob_get_clean();
lcfa_assert_contains('action="http://example.test/wp-admin/admin-post.php"', $command_thread_panel_markup, 'command thread panel should post thread operations through the main admin-post endpoint');
lcfa_assert_contains('name="action" value="lcfa_command"', $command_thread_panel_markup, 'command thread panel should reuse the lcfa_command handler for thread operations');
lcfa_assert_contains('name="thread_operation" value="rename"', $command_thread_panel_markup, 'command thread panel should expose a rename operation for the current thread');
lcfa_assert_contains('name="thread_operation" value="delete"', $command_thread_panel_markup, 'command thread panel should expose a delete operation for non-default threads');
lcfa_assert_contains('Rename current', $command_thread_panel_markup, 'command thread panel should label the rename action clearly');
lcfa_assert_contains('Delete current', $command_thread_panel_markup, 'command thread panel should label the delete action clearly');
lcfa_assert_contains('thread_id=default&amp;post_id=42&amp;genesis_task_id=task-42&amp;user_prompt=Audit+header', $command_thread_panel_markup, 'command thread switch links should preserve the current command context');
lcfa_assert_contains('name="context_post_id" value="42"', $command_thread_panel_markup, 'command thread forms should preserve the current editor context target');
lcfa_assert_contains('name="genesis_task_id" value="task-42"', $command_thread_panel_markup, 'command thread forms should preserve the current Genesis task id');
lcfa_assert_contains('name="user_prompt" value="Audit header"', $command_thread_panel_markup, 'command thread forms should preserve the current prompt while switching or editing threads');
lcfa_assert_contains('Current context', $command_thread_panel_markup, 'command thread panel should summarize the preserved execution context');
lcfa_assert_contains('data-lcfa-command-thread-tools', $command_thread_panel_markup, 'command thread panel should wrap thread operations inside a collapsible tools panel');
lcfa_assert_contains('Thread tools', $command_thread_panel_markup, 'command thread panel should label the secondary thread operations clearly');
lcfa_assert_contains('Post 42', $command_thread_panel_markup, 'command thread panel should show the bound post context');
lcfa_assert_contains('Genesis task task-42', $command_thread_panel_markup, 'command thread panel should show the bound Genesis task');
lcfa_assert_contains('Prompt: Audit header', $command_thread_panel_markup, 'command thread panel should show the preserved prompt summary');

$_POST = [
    'thread_operation' => 'clear',
    'thread_id' => 'ideas',
    'context_post_id' => '42',
    'genesis_task_id' => 'task-42',
    'user_prompt' => 'Audit header',
];
try {
    $admin->handle_command_post();
    fwrite(STDERR, 'handle_command_post should redirect after thread operations' . PHP_EOL);
    exit(1);
} catch (LCFA_Test_Redirect $redirect) {
    lcfa_assert_contains('thread_id=ideas', $redirect->url, 'command post redirect should keep the current thread');
    lcfa_assert_contains('post_id=42', $redirect->url, 'command post redirect should preserve the current editor context');
    lcfa_assert_contains('genesis_task_id=task-42', $redirect->url, 'command post redirect should preserve the current Genesis task id');
    lcfa_assert_contains('user_prompt=Audit+header', $redirect->url, 'command post redirect should preserve the current prompt');
}
$_POST = [];
$_GET['thread_id'] = 'ideas';
$_GET['post_id'] = '42';
$_GET['genesis_task_id'] = 'task-42';
$_GET['user_prompt'] = 'Audit header';

ob_start();
$render_command_thread->invoke($admin, [
    'role' => 'suggestion_result',
    'label' => 'Suggestion ready',
    'time' => '2026-04-16 10:02:00',
    'content' => 'Suggested action: page_upsert.',
    'meta' => [
        'action' => 'page_upsert',
        'execution_target' => 'local',
        'genesis_task_id' => 'task-42',
        'confidence' => 'high',
    ],
    'actions' => [
        [
            'kind' => 'apply',
            'label' => 'Preview',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
                'variant' => '1',
                'dry_run' => true,
            ],
        ],
        [
            'kind' => 'apply',
            'label' => 'Apply',
            'payload' => [
                'action' => 'page_upsert',
                'execution_target' => 'local',
                'target_id' => 42,
                'variant' => '1',
            ],
        ],
        [
            'kind' => 'url',
            'label' => 'Open suggested payload in Command Deck',
            'url' => 'http://example.test/wp-admin/admin.php?page=lcfa-dashboard&tab=command&suggest_action=page_upsert',
        ],
    ],
]);
$command_suggestion_markup = (string) ob_get_clean();
lcfa_assert_contains('lcfa-thread-message is-suggestion_result', $command_suggestion_markup, 'command thread renderer should keep suggestion_result messages visually distinct');
lcfa_assert_contains('Preview', $command_suggestion_markup, 'command thread renderer should expose a preview control for persisted suggestion_result messages');
lcfa_assert_contains('Apply', $command_suggestion_markup, 'command thread renderer should expose an apply control for persisted suggestion_result messages');
lcfa_assert_contains('command_payload_json', $command_suggestion_markup, 'command thread renderer should serialize persisted suggestion payloads for apply-inline submissions');
lcfa_assert_contains('Open suggested payload in Command Deck', $command_suggestion_markup, 'command thread renderer should keep the command-deck deeplink for persisted suggestion_result messages');
lcfa_assert_contains('Action: page_upsert', $command_suggestion_markup, 'command thread renderer should expose a semantic action chip');
lcfa_assert_contains('Execution: local', $command_suggestion_markup, 'command thread renderer should expose a semantic execution chip');
lcfa_assert_contains('Genesis task: task-42', $command_suggestion_markup, 'command thread renderer should expose a semantic Genesis task chip');
lcfa_assert_contains('Confidence: high', $command_suggestion_markup, 'command thread renderer should expose a semantic confidence chip');
lcfa_assert_contains('name="context_post_id" value="42"', $command_suggestion_markup, 'command thread apply forms should preserve the current post context');
lcfa_assert_contains('name="genesis_task_id" value="task-42"', $command_suggestion_markup, 'command thread apply forms should preserve the current Genesis task context');
lcfa_assert_contains('name="user_prompt" value="Audit header"', $command_suggestion_markup, 'command thread apply forms should preserve the current prompt context');

$_POST = [
    'thread_id' => 'ideas',
    'context_post_id' => '42',
    'genesis_task_id' => 'task-42',
    'user_prompt' => 'Audit header',
    'command_payload_json' => wp_json_encode([
        'action' => 'site_audit',
        'execution_target' => 'local',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
];
try {
    $admin->handle_command_post();
    fwrite(STDERR, 'handle_command_post should redirect after command thread apply submissions' . PHP_EOL);
    exit(1);
} catch (LCFA_Test_Redirect $redirect) {
    lcfa_assert_contains('thread_id=ideas', $redirect->url, 'command apply redirect should keep the current thread');
    lcfa_assert_contains('post_id=42', $redirect->url, 'command apply redirect should preserve the current post context');
    lcfa_assert_contains('genesis_task_id=task-42', $redirect->url, 'command apply redirect should preserve the current Genesis task id');
    lcfa_assert_contains('user_prompt=Audit+header', $redirect->url, 'command apply redirect should preserve the current prompt');
}
$_POST = [];

$get_command_request_payload = $admin_reflection->getMethod('get_command_request_payload');
$structured_command_payload = $get_command_request_payload->invoke($admin, [
    'action'  => 'site_foundation_run',
    'content' => wp_json_encode([
        'header_html' => '<header>JSON Header</header>',
        'footer_html' => '<footer>JSON Footer</footer>',
        'pages'       => [
            [
                'title' => 'Home',
                'slug'  => 'home',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
]);

lcfa_assert_same('site_foundation_run', (string) ($structured_command_payload['action'] ?? ''), 'content JSON should not overwrite the selected Command Deck action when action is omitted from the JSON');
lcfa_assert_same('<header>JSON Header</header>', (string) ($structured_command_payload['header_html'] ?? ''), 'Command Deck content JSON should hydrate header_html for structured actions');
lcfa_assert_same('<footer>JSON Footer</footer>', (string) ($structured_command_payload['footer_html'] ?? ''), 'Command Deck content JSON should hydrate footer_html for structured actions');
lcfa_assert_same('Home', (string) ($structured_command_payload['pages'][0]['title'] ?? ''), 'Command Deck content JSON should hydrate page arrays for foundation runs');

$_GET['thread_id'] = 'ideas';
$_GET['post_id'] = '42';
$_GET['genesis_task_id'] = 'task-42';
$_GET['user_prompt'] = 'Audit header';

ob_start();
$render_command_result->invoke($admin, [
    'ok' => true,
    'summary' => 'Pricing page updated.',
    'message' => 'Pricing page updated.',
    'action' => 'page_upsert',
    'mode' => 'apply',
    'target_type' => 'page',
    'target_id' => 42,
    'target_title' => 'Pricing',
    'diff_html' => '<div class="diff">changed</div>',
    'existing_html' => '<section>old</section>',
    'proposed_html' => '<section>new</section>',
    'data' => [
        'execution_target' => 'local',
        'genesis_task_id' => 'task-42',
        'foo' => 'bar',
    ],
]);
$command_result_panel_markup = (string) ob_get_clean();
lcfa_assert_contains('Support details', $command_result_panel_markup, 'command result panel should present itself as a support surface');
lcfa_assert_contains('Structured payload', $command_result_panel_markup, 'command result panel should keep structured payload inspection');
lcfa_assert_contains('Diff preview', $command_result_panel_markup, 'command result panel should keep diff inspection');
lcfa_assert_contains('data-lcfa-command-details="payload"', $command_result_panel_markup, 'command result panel should collapse payload inspection into a dedicated details block');
lcfa_assert_contains('data-lcfa-command-details="preview"', $command_result_panel_markup, 'command result panel should collapse markup preview inspection into a dedicated details block');
lcfa_assert_not_contains('Action: page_upsert', $command_result_panel_markup, 'command result panel should not duplicate action chips already shown in the thread');
lcfa_assert_not_contains('Mode: apply', $command_result_panel_markup, 'command result panel should not duplicate mode chips already shown in the thread');

ob_start();
$render_command_suggestion->invoke($admin, [
    'ok' => true,
    'summary' => 'Suggested page update.',
    'message' => 'Suggested page update.',
    'confidence' => 'high',
    'suggested_payload' => [
        'action' => 'page_upsert',
        'execution_target' => 'local',
        'target_id' => 42,
        'variant' => '1',
    ],
    'reasons' => ['Framework matches current page'],
    'warnings' => ['Review copy before apply'],
    'workflow' => ['Preview', 'Apply'],
]);
$command_suggestion_panel_markup = (string) ob_get_clean();
lcfa_assert_contains('Support details', $command_suggestion_panel_markup, 'command suggestion panel should present itself as a support surface');
lcfa_assert_contains('Why this was suggested', $command_suggestion_panel_markup, 'command suggestion panel should keep reasoning details');
lcfa_assert_contains('Warnings', $command_suggestion_panel_markup, 'command suggestion panel should keep warnings');
lcfa_assert_contains('Recommended workflow', $command_suggestion_panel_markup, 'command suggestion panel should keep workflow guidance');
lcfa_assert_contains('Preview', $command_suggestion_panel_markup, 'command suggestion panel should keep actionable suggestion controls');
lcfa_assert_contains('data-lcfa-command-details="reasons"', $command_suggestion_panel_markup, 'command suggestion panel should collapse reasoning into a dedicated details block');
lcfa_assert_contains('data-lcfa-command-details="workflow"', $command_suggestion_panel_markup, 'command suggestion panel should collapse workflow into a dedicated details block');
lcfa_assert_contains('name="context_post_id" value="42"', $command_suggestion_panel_markup, 'command suggestion panel apply controls should preserve the current post context');
lcfa_assert_contains('name="genesis_task_id" value="task-42"', $command_suggestion_panel_markup, 'command suggestion panel apply controls should preserve the current Genesis task context');
lcfa_assert_contains('name="user_prompt" value="Audit header"', $command_suggestion_panel_markup, 'command suggestion panel apply controls should preserve the current prompt context');
lcfa_assert_contains('suggest_action=page_upsert', $command_suggestion_panel_markup, 'command suggestion panel deeplink should keep the suggested action');
lcfa_assert_contains('thread_id=ideas', $command_suggestion_panel_markup, 'command suggestion panel deeplink should preserve the current thread');
lcfa_assert_contains('post_id=42', $command_suggestion_panel_markup, 'command suggestion panel deeplink should preserve the current post context');
lcfa_assert_contains('genesis_task_id=task-42', $command_suggestion_panel_markup, 'command suggestion panel deeplink should preserve the current Genesis task id');
lcfa_assert_contains('user_prompt=Audit+header', $command_suggestion_panel_markup, 'command suggestion panel deeplink should preserve the current prompt');
lcfa_assert_not_contains('Suggested action: page_upsert', $command_suggestion_panel_markup, 'command suggestion panel should not duplicate suggested action chips already shown in the thread');
lcfa_assert_not_contains('Confidence: high', $command_suggestion_panel_markup, 'command suggestion panel should not duplicate confidence chips already shown in the thread');
lcfa_assert_not_contains('Execution: local', $command_suggestion_panel_markup, 'command suggestion panel should not duplicate execution chips already shown in the thread');

ob_start();
$render_editor_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:05:00',
    'content' => 'Theme file updated.',
    'meta' => [
        'target_type' => 'theme_file',
        'file_path' => 'assets/theme.css',
        'root_scope' => 'stylesheet',
    ],
]);
$editor_theme_file_markup = (string) ob_get_clean();
lcfa_assert_contains('Open theme file', $editor_theme_file_markup, 'editor thread renderer should output a theme-file action link when the result targets a theme file');
lcfa_assert_contains('suggest_action=write_theme_file', $editor_theme_file_markup, 'theme-file action link should point back to the theme file command flow');

ob_start();
$render_command_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:10:00',
    'content' => 'Backup restored.',
    'meta' => [
        'target_type' => 'theme_backup_restore',
        'backup_id' => '2026-04-16/theme.css.bak',
        'file_path' => 'assets/theme.css',
        'root_scope' => 'stylesheet',
    ],
]);
$command_backup_markup = (string) ob_get_clean();
lcfa_assert_contains('Open backup', $command_backup_markup, 'command thread renderer should output a backup action link when a backup id is available');
lcfa_assert_contains('suggest_action=restore_theme_backup', $command_backup_markup, 'backup action link should point back to the restore backup command flow');

$ranked_actions = $get_thread_actions->invoke($admin, [
    'role' => 'tool_result',
    'meta' => [
        'target_type'  => 'theme_file',
        'frontend_url' => 'https://example.test/pricing/',
        'edit_url'     => 'https://example.test/wp-admin/post.php?post=42&action=edit',
        'file_path'    => 'assets/theme.css',
        'root_scope'   => 'stylesheet',
        'backup_id'    => '2026-04-16/theme.css.bak',
    ],
]);

if (count($ranked_actions) !== 2) {
    fwrite(STDERR, 'thread action builder should expose at most two ranked actions' . PHP_EOL);
    exit(1);
}

if (($ranked_actions[0]['label'] ?? '') !== 'View page' || ($ranked_actions[1]['label'] ?? '') !== 'Edit page') {
    fwrite(STDERR, 'thread action builder should prioritize direct page links over command-deck fallbacks' . PHP_EOL);
    exit(1);
}

ob_start();
$render_command_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:15:00',
    'content' => 'Page updated with backup available.',
    'meta' => [
        'target_type'  => 'theme_file',
        'frontend_url' => 'https://example.test/pricing/',
        'edit_url'     => 'https://example.test/wp-admin/post.php?post=42&action=edit',
        'file_path'    => 'assets/theme.css',
        'root_scope'   => 'stylesheet',
        'backup_id'    => '2026-04-16/theme.css.bak',
    ],
]);
$command_ranked_markup = (string) ob_get_clean();
lcfa_assert_contains('View page', $command_ranked_markup, 'ranked command thread actions should keep the frontend link');
lcfa_assert_contains('Edit page', $command_ranked_markup, 'ranked command thread actions should keep the edit link');
lcfa_assert_not_contains('Open theme file', $command_ranked_markup, 'ranked command thread actions should hide lower-priority theme-file links when two higher-priority actions exist');
lcfa_assert_not_contains('Open backup', $command_ranked_markup, 'ranked command thread actions should hide lower-priority backup links when two higher-priority actions exist');

ob_start();
$render_editor_thread->invoke($admin, [
    'role' => 'tool_result',
    'label' => 'Execution result',
    'time' => '2026-04-16 10:20:00',
    'content' => 'Stored action-only message.',
    'meta' => [
        'action' => 'page_upsert',
        'target_type' => 'page',
    ],
    'actions' => [
        [
            'label' => 'View page',
            'url'   => 'https://example.test/pricing/',
        ],
        [
            'label' => 'Edit page',
            'url'   => 'https://example.test/wp-admin/post.php?post=42&action=edit',
        ],
    ],
]);
$editor_action_only_markup = (string) ob_get_clean();
lcfa_assert_contains('View page', $editor_action_only_markup, 'editor thread renderer should preserve stored actions even when meta no longer carries CTA plumbing');
lcfa_assert_contains('Edit page', $editor_action_only_markup, 'editor thread renderer should preserve stored edit actions when meta is minimal');

echo "PASS\n";
