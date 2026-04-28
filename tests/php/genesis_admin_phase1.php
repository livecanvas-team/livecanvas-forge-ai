<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('MINUTE_IN_SECONDS', 60);

final class LCFA_Test_Redirect extends RuntimeException {
    public string $url;

    public function __construct(string $url) {
        $this->url = $url;
        parent::__construct($url);
    }
}

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_transients'] = [];
$_POST = [];
$_GET = [];

function __(string $text, string $domain = ''): string {
    return $text;
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

function esc_url(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES);
}

function esc_textarea(string $value): string {
    return $value;
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string|false {
    return json_encode($value, $flags, $depth);
}

function selected($selected, $current, bool $display = true): string {
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url): string {
    return $url . '?' . http_build_query($args);
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

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
    $markup = '<input type="hidden" name="' . $name . '" value="nonce">';

    if ($display) {
        echo $markup;
    }

    return $markup;
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function delete_option(string $key): bool {
    unset($GLOBALS['lcfa_test_options'][$key]);
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

function sanitize_title(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function current_time(string $type = 'mysql', bool $gmt = false): string {
    return $type === 'mysql' ? gmdate('Y-m-d H:i:s') : gmdate('c');
}

function get_date_from_gmt(string $date_string, string $format = 'Y-m-d H:i:s'): string {
    $timestamp = strtotime($date_string);
    return $timestamp ? gmdate($format, $timestamp) : $date_string;
}

function wp_generate_password(int $length = 12, bool $special_chars = false, bool $extra_special_chars = false): string {
    return str_repeat('a', $length);
}

function absint($value): int {
    return max(0, (int) $value);
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'detected_framework' => 'picostrap',
            'current_theme_stylesheet' => 'picostrap-child',
            'site_mode' => 'local',
        ];
    }

    public function get_livecanvas_menu_slug(): ?string {
        return null;
    }
}

final class LCFA_Installer {}
final class LCFA_Inventory {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Connection_Tester {}
final class LCFA_Remote_Client {}
final class LCFA_Context_Builder {}
final class LCFA_Connection_Onboarding {}
final class LCFA_Connection_Wizard_Presenter {}
final class LCFA_Admin_Hero_Presenter {}
final class LCFA_Prompt_Suggester {}
final class LCFA_Genesis_Planner {}

final class LCFA_Command_Deck {
    public array $executed = [];

    public function execute(array $payload): array {
        $this->executed[] = $payload;

        return [
            'ok' => true,
            'action' => (string) ($payload['action'] ?? ''),
            'mode' => !empty($payload['dry_run']) ? 'preview' : 'apply',
            'execution_target' => (string) ($payload['execution_target'] ?? 'local'),
            'message' => !empty($payload['dry_run']) ? 'Preview prepared.' : 'Task applied.',
            'summary' => 'Stub Genesis execution.',
            'target_type' => 'audit',
            'target_id' => 0,
            'target_title' => 'Stub target',
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

    public function get_actions(): array {
        return [
            'update_header' => ['label' => 'Update header'],
            'global_shell_apply' => ['label' => 'Global shell apply'],
            'site_foundation_run' => ['label' => 'Site foundation run'],
            'site_audit' => ['label' => 'Site audit'],
            'create_page' => ['label' => 'Create page'],
        ];
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-genesis-executor.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$environment = new LCFA_Environment();
$command_deck = new LCFA_Command_Deck();
$executor = new LCFA_Genesis_Executor($environment, $command_deck);

LCFA_Settings::update_project_brief([
    'project_mode' => 'from_scratch',
    'brand_name' => 'Consultala',
    'sector' => 'Consulting',
    'tone' => 'Precise',
    'logo_status' => 'existing',
    'required_pages' => "Home\nAbout",
    'notes' => 'Genesis admin test',
]);

$plan = [
    'generated_at' => '2026-04-17 09:00:00',
    'brief_hash' => LCFA_Settings::get_project_brief_hash(),
    'stack' => [
        'framework' => 'picostrap',
        'theme' => 'picostrap-child',
        'site_mode' => 'local',
    ],
    'pages' => [
        [
            'title' => 'Home',
            'slug' => 'home',
            'kind' => 'home',
            'homepage' => true,
            'description' => 'Homepage',
        ],
    ],
    'tasks' => [
        [
            'id' => 'foundation-header',
            'stage' => 'foundation',
            'label' => 'Create or refresh the global header shell',
            'description' => 'Header foundation task.',
            'payload' => [
                'action' => 'global_shell_apply',
                'variant' => '2',
                'header_html' => '<header>Genesis Header</header>',
                'footer_html' => '<footer>Genesis Footer</footer>',
            ],
            'user_prompt' => 'Build the header shell.',
        ],
        [
            'id' => 'brand-logo',
            'stage' => 'foundation',
            'label' => 'Prepare the logo asset workflow',
            'description' => 'Advisory logo task.',
            'payload' => [],
            'user_prompt' => 'Generate the logo later.',
        ],
        [
            'id' => 'page-home',
            'stage' => 'pages',
            'label' => 'Create page: Home',
            'description' => 'Create the homepage.',
            'payload' => [
                'action' => 'create_page',
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ],
            'user_prompt' => 'Create the homepage.',
        ],
    ],
    'counts' => [
        'pages' => 1,
        'tasks' => 3,
        'advisories' => 1,
    ],
];

LCFA_Settings::update_genesis_plan($plan);
LCFA_Settings::update_genesis_progress([
    'brief_hash' => LCFA_Settings::get_project_brief_hash(),
    'tasks' => [
        'page-home' => [
            'status' => 'failed',
            'updated_at' => '2026-04-17 09:30:00',
            'thread_id' => 'default',
            'action' => 'create_page',
            'mode' => 'apply',
            'ok' => false,
            'message' => 'Task failed once.',
            'target_type' => '',
            'target_id' => 0,
            'target_title' => '',
        ],
    ],
]);

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

(new ReflectionProperty('LCFA_Admin', 'environment'))->setValue($admin, $environment);
(new ReflectionProperty('LCFA_Admin', 'installer'))->setValue($admin, new LCFA_Installer());
(new ReflectionProperty('LCFA_Admin', 'inventory'))->setValue($admin, new LCFA_Inventory());
(new ReflectionProperty('LCFA_Admin', 'theme_files_bridge'))->setValue($admin, new LCFA_Theme_Files_Bridge());
(new ReflectionProperty('LCFA_Admin', 'connection_tester'))->setValue($admin, new LCFA_Connection_Tester());
(new ReflectionProperty('LCFA_Admin', 'remote_client'))->setValue($admin, new LCFA_Remote_Client());
(new ReflectionProperty('LCFA_Admin', 'context_builder'))->setValue($admin, new LCFA_Context_Builder());
(new ReflectionProperty('LCFA_Admin', 'connection_onboarding'))->setValue($admin, new LCFA_Connection_Onboarding());
(new ReflectionProperty('LCFA_Admin', 'connection_wizard_presenter'))->setValue($admin, new LCFA_Connection_Wizard_Presenter());
(new ReflectionProperty('LCFA_Admin', 'admin_hero_presenter'))->setValue($admin, new LCFA_Admin_Hero_Presenter());
(new ReflectionProperty('LCFA_Admin', 'command_deck'))->setValue($admin, $command_deck);
(new ReflectionProperty('LCFA_Admin', 'prompt_suggester'))->setValue($admin, new LCFA_Prompt_Suggester());
(new ReflectionProperty('LCFA_Admin', 'genesis_planner'))->setValue($admin, new LCFA_Genesis_Planner());
(new ReflectionProperty('LCFA_Admin', 'genesis_executor'))->setValue($admin, $executor);

$render_genesis_tab = $admin_reflection->getMethod('render_genesis_tab');

ob_start();
$render_genesis_tab->invoke(
    $admin,
    [],
    $environment->get_snapshot(),
    LCFA_Settings::get_project_brief(),
    ['pages' => 1, 'headers' => 1, 'footers' => 1, 'dynamic_templates' => 0],
    LCFA_Settings::get_genesis_plan(),
    LCFA_Settings::get_genesis_progress(),
    LCFA_Settings::get_project_brief_hash()
);
$markup = (string) ob_get_clean();

lcfa_assert_contains('Preview next task', $markup, 'Genesis tab should expose a preview-next control');
lcfa_assert_contains('Apply next task', $markup, 'Genesis tab should expose an apply-next control');
lcfa_assert_contains('Execution controls', $markup, 'Genesis tab should show a dedicated execution controls section');
lcfa_assert_contains('Preview task', $markup, 'Genesis task cards should expose a preview-task control');
lcfa_assert_contains('Apply task', $markup, 'Genesis task cards should expose an apply-task control');
lcfa_assert_contains('Mark reviewed', $markup, 'Genesis advisory tasks should expose an acknowledge control');
lcfa_assert_contains('Retry task', $markup, 'Failed Genesis tasks should expose a retry control');
lcfa_assert_contains('name="action" value="lcfa_genesis_execute"', $markup, 'Genesis execution controls should post through the dedicated admin-post handler');
lcfa_assert_contains('name="execution_mode" value="preview_next"', $markup, 'Genesis next preview form should preserve execution mode');
lcfa_assert_contains('name="execution_mode" value="apply_task"', $markup, 'Genesis task apply form should preserve execution mode');
lcfa_assert_contains('name="execution_mode" value="retry_task"', $markup, 'Genesis retry form should preserve retry execution mode');
lcfa_assert_contains('name="execution_mode" value="acknowledge_task"', $markup, 'Genesis advisory form should preserve acknowledge execution mode');
lcfa_assert_contains('Next task: Create or refresh the global header shell', $markup, 'Genesis controls should expose the next task label');
lcfa_assert_contains('Pending: 2', $markup, 'Genesis summary chips should include the pending count from execution state');
lcfa_assert_contains('Failed: 1', $markup, 'Genesis summary chips should include failed execution count');

$get_command_form_context = $admin_reflection->getMethod('get_command_form_context');
$_GET = [
    'genesis_task_id' => 'foundation-header',
];
$loaded_command_context = $get_command_form_context->invoke($admin, $command_deck->get_actions());
$_GET = [];

lcfa_assert_same('global_shell_apply', (string) ($loaded_command_context['action'] ?? ''), 'Command Deck should hydrate the action from the selected Genesis task payload');
lcfa_assert_same('2', (string) ($loaded_command_context['variant'] ?? ''), 'Command Deck should hydrate the partial variant from the selected Genesis task payload');
lcfa_assert_contains('Genesis Header', (string) ($loaded_command_context['content'] ?? ''), 'Command Deck should serialize structured Genesis header payloads into the content field');
lcfa_assert_contains('Genesis Footer', (string) ($loaded_command_context['content'] ?? ''), 'Command Deck should serialize structured Genesis footer payloads into the content field');

$_POST = [
    'execution_mode' => 'preview_next',
    'thread_id' => 'default',
    'execution_target' => 'local',
];

try {
    $admin->handle_genesis_execute_post();
    fwrite(STDERR, 'handle_genesis_execute_post should redirect after preview_next' . PHP_EOL);
    exit(1);
} catch (LCFA_Test_Redirect $redirect) {
    lcfa_assert_contains('tab=genesis', $redirect->url, 'Genesis preview submit should redirect back to the Genesis tab');
}

$preview_notice = LCFA_Settings::consume_notice();
$preview_progress = LCFA_Settings::get_genesis_progress();

lcfa_assert_same('success', (string) ($preview_notice['type'] ?? ''), 'Genesis preview submit should set a success notice');
lcfa_assert_contains('Preview prepared.', (string) ($preview_notice['message'] ?? ''), 'Genesis preview submit should surface the executor message');
lcfa_assert_same('previewed', (string) ($preview_progress['tasks']['foundation-header']['status'] ?? ''), 'Genesis preview submit should mark the next task as previewed');

$_POST = [
    'execution_mode' => 'acknowledge_task',
    'task_id' => 'brand-logo',
    'thread_id' => 'default',
    'execution_target' => 'local',
];

try {
    $admin->handle_genesis_execute_post();
    fwrite(STDERR, 'handle_genesis_execute_post should redirect after acknowledge_task' . PHP_EOL);
    exit(1);
} catch (LCFA_Test_Redirect $redirect) {
    lcfa_assert_contains('tab=genesis', $redirect->url, 'Genesis advisory submit should redirect back to the Genesis tab');
}

$advisory_notice = LCFA_Settings::consume_notice();
$advisory_progress = LCFA_Settings::get_genesis_progress();

lcfa_assert_same('success', (string) ($advisory_notice['type'] ?? ''), 'Genesis advisory submit should set a success notice');
lcfa_assert_contains('logo', strtolower((string) ($advisory_notice['message'] ?? '')), 'Genesis advisory submit should surface the advisory guidance');
lcfa_assert_same('applied', (string) ($advisory_progress['tasks']['brand-logo']['status'] ?? ''), 'Genesis advisory submit should mark the advisory task as applied');

echo 'PASS' . PHP_EOL;
