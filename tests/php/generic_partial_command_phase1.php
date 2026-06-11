<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function lcfa_generic_partial_read(string $path): string {
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        fwrite(STDERR, 'Unable to read ' . $path . PHP_EOL);
        exit(1);
    }

    return $contents;
}

function lcfa_generic_partial_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_generic_partial_assert_matches(string $pattern, string $haystack, string $message): void {
    if (!preg_match($pattern, $haystack)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Pattern: ' . $pattern . PHP_EOL);
        exit(1);
    }
}

$command_deck = lcfa_generic_partial_read($root . '/includes/class-lcfa-command-deck.php');
$admin = lcfa_generic_partial_read($root . '/includes/class-lcfa-admin.php');
$context_builder = lcfa_generic_partial_read($root . '/includes/class-lcfa-context-builder.php');
$prompt_suggester = lcfa_generic_partial_read($root . '/includes/class-lcfa-prompt-suggester.php');
$settings = lcfa_generic_partial_read($root . '/includes/class-lcfa-settings.php');
$autorunner = lcfa_generic_partial_read($root . '/includes/class-lcfa-codex-autorunner.php');
$tool_registry = lcfa_generic_partial_read($root . '/mcp/src/tool-registry.js');
$tool_registry_test = lcfa_generic_partial_read($root . '/mcp/tests/tool-registry.test.js');
$readme = lcfa_generic_partial_read($root . '/README.md');

lcfa_generic_partial_assert_contains("'update_partial' => [", $command_deck, 'Command Deck should expose update_partial as an action.');
lcfa_generic_partial_assert_contains("case 'update_partial':", $command_deck, 'Command Deck should route update_partial.');
lcfa_generic_partial_assert_contains("get_target_content('partial'", $command_deck, 'update_partial should read generic partial content.');
lcfa_generic_partial_assert_contains("'post_type'] ?? '') !== 'lc_partial'", $command_deck, 'update_partial should validate lc_partial post type.');
lcfa_generic_partial_assert_contains('Use update_header or update_footer for global shell partials.', $command_deck, 'update_partial should reject global shell partials.');
lcfa_generic_partial_assert_contains("'update_page', 'update_partial'", $command_deck, 'Structured content fast path should support update_partial.');
lcfa_generic_partial_assert_contains("'update_partial',", $command_deck, 'Advanced action list should include update_partial.');

lcfa_generic_partial_assert_matches('/\\$context\\[\\s*\'action\'\\s*\\]\\s*=\\s*\'update_partial\';/', $admin, 'Admin editor context should map generic lc_partial targets to update_partial.');
lcfa_generic_partial_assert_contains("get_target_content('partial'", $admin, 'Admin command form should hydrate generic partial targets.');
lcfa_generic_partial_assert_contains("if (\$action === 'update_partial')", $admin, 'Editor target labels should name generic partials.');

lcfa_generic_partial_assert_contains("'partial' => [", $context_builder, 'Context builder should expose a partial target context.');
lcfa_generic_partial_assert_contains("'command_action' => 'update_partial'", $context_builder, 'Partial target context should guide agents to update_partial.');
lcfa_generic_partial_assert_contains("\$inventory['other_partials']", $context_builder, 'Partial target context should count other_partials inventory.');

lcfa_generic_partial_assert_contains("\$context_target_type === 'partial'", $prompt_suggester, 'Prompt suggester should recognize generic partial editor context.');
lcfa_generic_partial_assert_contains("\$suggested['action'] = 'update_partial';", $prompt_suggester, 'Prompt suggester should prefer update_partial for partial write requests.');

lcfa_generic_partial_assert_contains("'update_partial'          => ['preview' => '', 'apply' => '']", $settings, 'Agent ability contracts should keep update_partial on the Command Deck path.');
lcfa_generic_partial_assert_contains('Use action "', $autorunner, 'Frontend worker prompt should use the target action without page-only wording.');
lcfa_generic_partial_assert_contains('update_partial for generic lc_partial posts', $tool_registry, 'MCP run_lc_command docs should expose generic partial writes.');
lcfa_generic_partial_assert_contains('/update_partial/i', $tool_registry_test, 'MCP tool registry test should guard update_partial docs.');
lcfa_generic_partial_assert_contains('| `update_partial` |', $readme, 'README should document update_partial.');

define('ABSPATH', '/tmp/lcfa-tests/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function absint($value): int {
    return max(0, (int) $value);
}

function sanitize_text_field($value): string {
    return trim(strip_tags((string) $value));
}

function sanitize_title($value): string {
    return strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $value), '-'));
}

function sanitize_key($value): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?: '';
}

final class WP_Post {
    public int $ID = 0;
    public string $post_title = '';
    public string $post_name = '';
    public string $post_type = '';
    public string $post_status = '';
    public string $post_content = '';

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

final class LCFA_Environment {
    public function detect_framework_family(): string {
        return 'picostrap';
    }
}

final class LCFA_Inventory {}

$GLOBALS['lcfa_generic_partial_posts'] = [
    25 => new WP_Post([
        'ID'           => 25,
        'post_title'   => 'Video Consultala Play',
        'post_name'    => 'video-consultala-play',
        'post_type'    => 'lc_partial',
        'post_status'  => 'publish',
        'post_content' => '<section class="video-partial">Video</section>',
    ]),
];
$GLOBALS['lcfa_generic_partial_post_meta'] = [
    25 => [],
];

function get_post($post_id) {
    return $GLOBALS['lcfa_generic_partial_posts'][(int) $post_id] ?? null;
}

function get_post_meta($post_id, string $key, bool $single = false) {
    return $GLOBALS['lcfa_generic_partial_post_meta'][(int) $post_id][$key] ?? '';
}

require $root . '/includes/class-lcfa-prompt-suggester.php';

$suggester = new LCFA_Prompt_Suggester(new LCFA_Environment(), new LCFA_Inventory());
$runtime_suggestion = $suggester->suggest([
    'user_prompt'     => 'Migliora questo partial video su mobile e mantieni gli stessi video.',
    'context_post_id' => 25,
    'target_id'       => 25,
]);
$small_logo_suggestion = $suggester->suggest([
    'user_prompt'     => 'Rimpicciolisci il logo.',
    'context_post_id' => 25,
    'target_id'       => 25,
]);

lcfa_generic_partial_assert_contains('update_partial', (string) ($runtime_suggestion['suggested_payload']['action'] ?? ''), 'Italian partial improvement prompts should map to update_partial.');
lcfa_generic_partial_assert_contains('update_partial', (string) ($small_logo_suggestion['suggested_payload']['action'] ?? ''), 'Italian resize prompts in partial context should map to update_partial.');

fwrite(STDOUT, 'Generic partial command contract OK' . PHP_EOL);
