<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', 'test');

$GLOBALS['lcfa_test_posts'] = [];
$GLOBALS['lcfa_test_history'] = [];
$GLOBALS['lcfa_test_rollbacks'] = [];

function __($text, $domain = null) { return $text; }
function add_action($hook, $callback) {}
function current_user_can($capability) { return in_array($capability, ['edit_pages', 'manage_options'], true); }
function absint($value) { return max(0, (int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_kses_post($value) { return (string) $value; }
function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) { return 'ApplyAudit01'; }
function current_time($type, $gmt = false) { return '2026-05-29 12:00:00'; }
function is_wp_error($value) { return false; }
function get_edit_post_link($post_id, $context = 'display') { return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit'; }
function get_permalink($post_id) { return 'https://example.test/native-' . (int) $post_id . '/'; }
function wp_insert_post(array $postarr, bool $wp_error = false) {
    $post_id = count($GLOBALS['lcfa_test_posts']) + 100;
    $GLOBALS['lcfa_test_posts'][$post_id] = $postarr + ['ID' => $post_id];

    return $post_id;
}

final class LCFA_Settings {
    public static function get_connections(): array {
        return [
            'mcp_write_abilities_enabled' => false,
            'mcp_public_write_abilities' => [],
        ];
    }

    public static function get_public_connections(): array {
        return ['preferred_client' => 'codex'];
    }

    public static function get_history(): array {
        return $GLOBALS['lcfa_test_history'];
    }

    public static function append_history(array $entry): void {
        array_unshift($GLOBALS['lcfa_test_history'], $entry);
    }

    public static function store_rollback_record(string $audit_id, array $record): void {
        $GLOBALS['lcfa_test_rollbacks'][$audit_id] = $record;
    }

    public static function get_mcp_write_ability_options(): array {
        return [
            'livecanvas-forge-ai/apply-native-pattern-page' => ['label' => 'Apply native pattern page'],
        ];
    }

    public static function sanitize_mcp_write_abilities($abilities): array {
        $allowed = array_keys(self::get_mcp_write_ability_options());
        $abilities = is_array($abilities) ? $abilities : [];

        return array_values(array_intersect($allowed, array_values(array_unique(array_map('strval', $abilities)))));
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'livecanvas_active' => false,
            'detected_framework' => 'core',
            'current_theme_stylesheet' => 'twentytwentysix',
        ];
    }

    public function get_mcp_adapter_status(): array {
        return ['available' => false];
    }
}

final class LCFA_Inventory {
    public function get_inventory(): array {
        return [];
    }
}

final class LCFA_Context_Builder {
    public function get_mcp_status(): array { return []; }
    public function build_context(array $args = []): array { return []; }
    public function get_theme_context(array $args = []): array { return []; }
    public function get_page_html(int $post_id): array { return []; }
}

final class LCFA_Command_Deck {
    public function get_actions(): array { return []; }
    public function execute(array $payload): array { return []; }
}

final class LCFA_WindPress_Bridge {
    public function get_status(): array { return []; }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-ai-client.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-block-patterns.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-ability-registry.php';

function lcfa_native_apply_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function lcfa_native_apply_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$environment = new LCFA_Environment();
$registry = new LCFA_Ability_Registry(
    $environment,
    new LCFA_Inventory(),
    new LCFA_Context_Builder(),
    new LCFA_Command_Deck(),
    new LCFA_WindPress_Bridge(),
    new LCFA_AI_Client(),
    new LCFA_Block_Patterns($environment)
);

$result = $registry->apply_native_pattern_page([
    'title' => 'Native Apply Smoke',
    'slug' => 'native-apply-smoke',
    'status' => 'publish',
    'blueprint' => 'starter-landing',
]);
$apply = $result['native_pattern_page_apply'] ?? [];

lcfa_native_apply_assert_true(!empty($apply['ok']), 'native pattern page apply should create a page');
lcfa_native_apply_assert_same('native-pattern-page-apply.v1', $apply['schema_version'] ?? '', 'native pattern page apply should expose schema version');
lcfa_native_apply_assert_same(100, $apply['page']['id'] ?? 0, 'native pattern page apply should expose created page id');
lcfa_native_apply_assert_same('draft', $apply['page']['status'] ?? '', 'native pattern page apply should force unsupported publish requests back to draft');
lcfa_native_apply_assert_same('starter-landing', $apply['page']['blueprint'] ?? '', 'native pattern page apply should expose selected blueprint');
lcfa_native_apply_assert_same('page', $GLOBALS['lcfa_test_posts'][100]['post_type'] ?? '', 'native pattern page apply should create a page post type');
lcfa_native_apply_assert_same('draft', $GLOBALS['lcfa_test_posts'][100]['post_status'] ?? '', 'native pattern page apply should store draft status');
lcfa_native_apply_assert_true(strpos((string) ($GLOBALS['lcfa_test_posts'][100]['post_content'] ?? ''), 'wp:livecanvas-forge-ai/section-shell') !== false, 'native pattern page apply should store WordPress block content');
lcfa_native_apply_assert_true(!empty($apply['rollback_available']), 'native pattern page apply should expose rollback availability');
lcfa_native_apply_assert_same('native_pattern_page_apply', $GLOBALS['lcfa_test_history'][0]['action'] ?? '', 'native pattern page apply should append history');
lcfa_native_apply_assert_same(100, $GLOBALS['lcfa_test_history'][0]['target_id'] ?? 0, 'native pattern page apply history should include target id');

$audit_id = (string) ($apply['audit_id'] ?? '');
lcfa_native_apply_assert_true($audit_id !== '', 'native pattern page apply should expose an audit id');
lcfa_native_apply_assert_same('created_post', $GLOBALS['lcfa_test_rollbacks'][$audit_id]['restore']['type'] ?? '', 'native pattern page apply should store created-post rollback metadata');
lcfa_native_apply_assert_same(100, $GLOBALS['lcfa_test_rollbacks'][$audit_id]['restore']['target_id'] ?? 0, 'native pattern page rollback should target the created page');

echo "PASS\n";
