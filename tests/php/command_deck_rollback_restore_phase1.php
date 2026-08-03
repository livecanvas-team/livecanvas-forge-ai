<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_posts'] = [
    123 => [
        'ID'           => 123,
        'post_content' => '<section>After</section>',
        'post_status'  => 'publish',
    ],
    456 => [
        'ID'           => 456,
        'post_content' => '<section>Created</section>',
        'post_status'  => 'publish',
    ],
    789 => [
        'ID'           => 789,
        'post_content' => '<div>Partial after</div>',
        'post_status'  => 'publish',
    ],
    901 => [
        'ID'           => 901,
        'post_content' => '',
        'post_status'  => 'inherit',
        'post_type'    => 'attachment',
    ],
];
$GLOBALS['lcfa_test_partial_terms'] = [789 => ['current-type']];
$GLOBALS['lcfa_test_thumbnails'] = [123 => 901];

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key($value): string {
    return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value));
}

function sanitize_text_field($value): string {
    return trim(strip_tags((string) $value));
}

function wp_normalize_path(string $value): string {
    return str_replace('\\', '/', $value);
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function sanitize_title($value): string {
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-');
}

function esc_url_raw($value): string {
    return (string) $value;
}

function absint($value): int {
    return max(0, (int) $value);
}

function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
    return substr(str_repeat('rollback123', 4), 0, $length);
}

function current_time(string $type, bool $gmt = false): string {
    return '2026-05-27 12:00:00';
}

function current_user_can(string $capability): bool {
    return true;
}

function get_post(int $post_id) {
    if (!isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return null;
    }

    return (object) $GLOBALS['lcfa_test_posts'][$post_id];
}

function get_post_status(int $post_id): string {
    return (string) ($GLOBALS['lcfa_test_posts'][$post_id]['post_status'] ?? '');
}

function update_post_meta(int $post_id, string $key, $value): bool {
    $GLOBALS['lcfa_test_posts'][$post_id]['meta'][$key] = $value;
    return true;
}

function delete_post_meta(int $post_id, string $key): bool {
    unset($GLOBALS['lcfa_test_posts'][$post_id]['meta'][$key]);
    return true;
}

function wp_update_post(array $postarr, bool $wp_error = false) {
    $post_id = absint($postarr['ID'] ?? 0);

    if ($post_id < 1 || !isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return 0;
    }

    if (array_key_exists('post_content', $postarr)) {
        $GLOBALS['lcfa_test_posts'][$post_id]['post_content'] = (string) $postarr['post_content'];
    }

    if (array_key_exists('post_status', $postarr)) {
        $GLOBALS['lcfa_test_posts'][$post_id]['post_status'] = (string) $postarr['post_status'];
    }

    return $post_id;
}

function wp_trash_post(int $post_id) {
    if (!isset($GLOBALS['lcfa_test_posts'][$post_id])) {
        return false;
    }

    $GLOBALS['lcfa_test_posts'][$post_id]['post_status'] = 'trash';

    return (object) $GLOBALS['lcfa_test_posts'][$post_id];
}

function set_post_thumbnail(int $post_id, int $attachment_id) {
    $GLOBALS['lcfa_test_thumbnails'][$post_id] = $attachment_id;
    return $attachment_id;
}

function delete_post_thumbnail(int $post_id): bool {
    unset($GLOBALS['lcfa_test_thumbnails'][$post_id]);
    return true;
}

function wp_delete_attachment(int $attachment_id, bool $force_delete = false) {
    if (!isset($GLOBALS['lcfa_test_posts'][$attachment_id])) {
        return false;
    }

    $attachment = (object) $GLOBALS['lcfa_test_posts'][$attachment_id];
    unset($GLOBALS['lcfa_test_posts'][$attachment_id]);
    return $attachment;
}

function is_wp_error($value): bool {
    return false;
}

function taxonomy_exists(string $taxonomy): bool {
    return $taxonomy === 'lc_partial_type';
}

function wp_set_object_terms(int $post_id, array $terms, string $taxonomy, bool $append = false): array {
    $GLOBALS['lcfa_test_partial_terms'][$post_id] = array_values(array_map('strval', $terms));

    return array_keys($terms);
}

final class LCFA_Settings {
    public static array $records = [];

    public static function get(): array {
        return [
            'permission_profile' => 'advanced_templates',
            'allow_file_fallback' => true,
        ];
    }

    public static function store_rollback_record(string $audit_id, array $record): void {
        self::$records[sanitize_key($audit_id)] = $record;
    }

    public static function get_rollback_record(string $audit_id): array {
        return self::$records[sanitize_key($audit_id)] ?? [];
    }

    public static function mark_rollback_record_restored(string $audit_id, array $restore_result): void {
        self::$records[sanitize_key($audit_id)]['restored_at'] = current_time('mysql', true);
    }
}

final class LCFA_Environment {}
final class LCFA_Inventory {}
final class LCFA_WindPress_Bridge {}
final class LCFA_Theme_Files_Bridge {
    public array $rollback_calls = [];

    public function rollback_write(array $options): array {
        $this->rollback_calls[] = $options;

        return [
            'ok' => true,
            'operation' => !empty($options['created_file']) ? 'delete_created_file' : 'restore_backup',
            'dry_run' => !empty($options['dry_run']),
        ];
    }
}
final class LCFA_Local_MCP_Bridge {}
final class LCFA_Remote_Client {}
final class LCFA_Design_System_Compose {}
final class LCFA_Design_System_Apply {
    public array $restore_calls = [];

    public function restore(array $snapshot, bool $dry_run = false): array {
        $this->restore_calls[] = compact('snapshot', 'dry_run');

        return [
            'ok' => true,
            'mode' => $dry_run ? 'preview' : 'apply',
            'message' => 'Picostrap design system restored.',
        ];
    }
}
final class LCFA_Design_System_Picostrap_Executor {}
final class LCFA_Design_System_Picowind_Executor {
    public function __construct(...$args) {}
}
final class LCFA_Design_System_Build_Gateway {
    public function __construct(...$args) {}
}
final class LCFA_Design_System_Picostrap_Composer {}
final class LCFA_Design_System_Preview {}

function lcfa_rollback_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_rollback_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-command-deck.php';

$reflection = new ReflectionClass('LCFA_Command_Deck');
$instance = $reflection->newInstanceWithoutConstructor();
$design_system_apply = new LCFA_Design_System_Apply();
$design_system_property = lcfa_test_reflection_property('LCFA_Command_Deck', 'design_system_apply');
$design_system_property->setValue($instance, $design_system_apply);
$theme_files_bridge = new LCFA_Theme_Files_Bridge();
$theme_files_property = lcfa_test_reflection_property('LCFA_Command_Deck', 'theme_files_bridge');
$theme_files_property->setValue($instance, $theme_files_bridge);
$attach_audit = lcfa_test_reflection_method('LCFA_Command_Deck', 'attach_audit_envelope');
$restore_rollback = lcfa_test_reflection_method('LCFA_Command_Deck', 'restore_audit_rollback');

$apply_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'page',
    'target_id'        => 123,
    'target_title'     => 'Existing page',
    'existing_html'    => '<section>Before</section>',
    'data'             => [
        'operation' => 'update',
        'page_runtime_rollback' => [
            'post_status' => 'publish',
        ],
    ],
];

$attach_audit->invokeArgs($instance, [&$apply_result, ['action' => 'page_upsert'], ['origin' => 'wp_ability']]);
$audit_id = (string) ($apply_result['audit_id'] ?? '');

lcfa_rollback_assert_true($audit_id !== '', 'apply audit should create an audit ID');
lcfa_rollback_assert_same('<section>Before</section>', LCFA_Settings::$records[$audit_id]['restore']['previous_content'] ?? '', 'rollback record should store previous content privately');

$preview = $restore_rollback->invoke($instance, $audit_id, true);
lcfa_rollback_assert_same('preview', $preview['mode'] ?? '', 'rollback restore should support preview mode');
lcfa_rollback_assert_same('<section>Before</section>', $preview['proposed_html'] ?? '', 'rollback preview should propose the stored previous content');
lcfa_rollback_assert_same('<section>After</section>', $GLOBALS['lcfa_test_posts'][123]['post_content'], 'rollback preview should not write post content');

$GLOBALS['lcfa_test_posts'][123]['post_status'] = 'draft';

$restored = $restore_rollback->invoke($instance, $audit_id, false);
lcfa_rollback_assert_true(!empty($restored['ok']), 'rollback apply should succeed for previous post content');
lcfa_rollback_assert_same('<section>Before</section>', $GLOBALS['lcfa_test_posts'][123]['post_content'], 'rollback apply should restore previous post content');
lcfa_rollback_assert_same('publish', $GLOBALS['lcfa_test_posts'][123]['post_status'], 'rollback apply should restore the previous page status');
lcfa_rollback_assert_same('2026-05-27 12:00:00', LCFA_Settings::$records[$audit_id]['restored_at'] ?? '', 'rollback apply should mark the record as restored');

$created_result = [
    'ok'               => true,
    'action'           => 'page_upsert',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'page',
    'target_id'        => 456,
    'target_title'     => 'Created page',
    'existing_html'    => '',
    'data'             => [
        'operation' => 'create',
    ],
];

$attach_audit->invokeArgs($instance, [&$created_result, ['action' => 'page_upsert'], ['origin' => 'wp_ability']]);
$created_audit_id = (string) ($created_result['audit_id'] ?? '');
$created_restore = $restore_rollback->invoke($instance, $created_audit_id, false);

lcfa_rollback_assert_true(!empty($created_restore['ok']), 'created-post rollback should succeed');
lcfa_rollback_assert_same('trash', $GLOBALS['lcfa_test_posts'][456]['post_status'], 'created-post rollback should move the created post to trash');

$partial_result = [
    'ok'               => true,
    'action'           => 'update_partial',
    'mode'             => 'apply',
    'execution_target' => 'local',
    'target_type'      => 'partial',
    'target_id'        => 789,
    'target_title'     => 'Utility partial',
    'existing_html'    => '<div>Partial before</div>',
    'data'             => [
        'operation' => 'update',
        'previous_partial_types' => ['legacy-type'],
    ],
];

$attach_audit->invokeArgs($instance, [&$partial_result, ['action' => 'update_partial'], ['origin' => 'wp_ability']]);
$partial_audit_id = (string) ($partial_result['audit_id'] ?? '');
lcfa_rollback_assert_same(['legacy-type'], LCFA_Settings::$records[$partial_audit_id]['restore']['previous_partial_types'] ?? [], 'partial rollback records should preserve lc_partial_type terms');

$partial_restore = $restore_rollback->invoke($instance, $partial_audit_id, false);
lcfa_rollback_assert_true(!empty($partial_restore['ok']), 'partial rollback should restore content and taxonomy terms');
lcfa_rollback_assert_same('<div>Partial before</div>', $GLOBALS['lcfa_test_posts'][789]['post_content'], 'partial rollback should restore previous content');
lcfa_rollback_assert_same(['legacy-type'], $GLOBALS['lcfa_test_partial_terms'][789] ?? [], 'partial rollback should restore previous lc_partial_type terms');

$design_result = [
    'ok' => true,
    'action' => 'design_system_apply',
    'mode' => 'apply',
    'execution_target' => 'local',
    'target_type' => 'design_system',
    'target_title' => 'Picostrap Child',
    'data' => [
        'picostrap_design_system_rollback' => [
            'framework' => 'picostrap',
            'theme_mods' => [
                'SCSSvar_primary' => ['exists' => true, 'value' => '#112233'],
            ],
            'bundle' => [
                'bundle_relative_path' => 'css-output/bundle.css',
                'bundle_existed' => true,
                'bundle_created' => false,
                'backup_id' => 'picostrap-child/css-output/bundle.css/backup.css',
                'theme_mods' => [
                    'css_bundle_version_number' => ['exists' => true, 'value' => 12],
                ],
            ],
        ],
    ],
];

$attach_audit->invokeArgs($instance, [&$design_result, ['action' => 'design_system_apply'], ['origin' => 'wp_ability']]);
$design_audit_id = (string) ($design_result['audit_id'] ?? '');
lcfa_rollback_assert_true(!empty($design_result['rollback_available']), 'Picostrap design-system apply should expose rollback');
lcfa_rollback_assert_same('picostrap_design_system', LCFA_Settings::$records[$design_audit_id]['restore']['type'] ?? '', 'Design-system rollback record should use its dedicated type');

$design_preview = $restore_rollback->invoke($instance, $design_audit_id, true);
lcfa_rollback_assert_true(!empty($design_preview['ok']), 'Design-system rollback preview should succeed');
lcfa_rollback_assert_same(true, $design_system_apply->restore_calls[0]['dry_run'] ?? null, 'Rollback preview should delegate in dry-run mode');

$design_restore = $restore_rollback->invoke($instance, $design_audit_id, false);
lcfa_rollback_assert_true(!empty($design_restore['ok']), 'Design-system rollback apply should succeed');
lcfa_rollback_assert_same(false, $design_system_apply->restore_calls[1]['dry_run'] ?? null, 'Rollback apply should delegate to the design-system service');

$media_audit_id = 'audit-media-upload';
LCFA_Settings::store_rollback_record($media_audit_id, [
    'audit_id' => $media_audit_id,
    'created_at' => current_time('mysql', true),
    'action' => 'media_upload',
    'target_type' => 'media',
    'target_id' => 901,
    'target_title' => 'Test image',
    'restore' => [
        'type' => 'media_upload',
        'attachment_id' => 901,
        'target_id' => 901,
        'target_title' => 'Test image',
        'created_attachment' => true,
        'featured_image_changed' => true,
        'featured_image_post_id' => 123,
        'previous_featured_image_id' => 902,
    ],
]);

$media_preview = $restore_rollback->invoke($instance, $media_audit_id, true);
lcfa_rollback_assert_true(!empty($media_preview['ok']), 'Media rollback preview should succeed.');
lcfa_rollback_assert_true(isset($GLOBALS['lcfa_test_posts'][901]), 'Media rollback preview must not delete the attachment.');
lcfa_rollback_assert_same(901, $GLOBALS['lcfa_test_thumbnails'][123] ?? 0, 'Media rollback preview must not change the featured image.');

$media_restore = $restore_rollback->invoke($instance, $media_audit_id, false);
lcfa_rollback_assert_true(!empty($media_restore['ok']), 'Media rollback apply should succeed.');
lcfa_rollback_assert_true(!isset($GLOBALS['lcfa_test_posts'][901]), 'Media rollback should delete an attachment created by AI Bridge.');
lcfa_rollback_assert_same(902, $GLOBALS['lcfa_test_thumbnails'][123] ?? 0, 'Media rollback should restore the previous featured image.');
lcfa_rollback_assert_same('2026-05-27 12:00:00', LCFA_Settings::$records[$media_audit_id]['restored_at'] ?? '', 'Media rollback should mark the audit record restored.');

$theme_audit_id = 'audit-theme-file';
LCFA_Settings::store_rollback_record($theme_audit_id, [
    'audit_id' => $theme_audit_id,
    'created_at' => current_time('mysql', true),
    'action' => 'theme_file_write',
    'target_type' => 'theme_file',
    'target_title' => 'assets/custom.css',
    'restore' => [
        'type' => 'theme_file_write',
        'root_scope' => 'stylesheet',
        'relative_path' => 'assets/custom.css',
        'target_theme' => 'example-child',
        'backup_id' => '2026-08-02/example-child/custom.css',
        'created_file' => false,
        'expected_checksum' => str_repeat('a', 64),
    ],
]);

$theme_preview = $restore_rollback->invoke($instance, $theme_audit_id, true);
lcfa_rollback_assert_true(!empty($theme_preview['ok']), 'Theme-file rollback preview should succeed.');
lcfa_rollback_assert_same(true, $theme_files_bridge->rollback_calls[0]['dry_run'] ?? null, 'Theme-file rollback preview should delegate in dry-run mode.');

$theme_restore = $restore_rollback->invoke($instance, $theme_audit_id, false);
lcfa_rollback_assert_true(!empty($theme_restore['ok']), 'Theme-file rollback apply should succeed.');
lcfa_rollback_assert_same(false, $theme_files_bridge->rollback_calls[1]['dry_run'] ?? null, 'Theme-file rollback apply should delegate in apply mode.');
lcfa_rollback_assert_same('2026-05-27 12:00:00', LCFA_Settings::$records[$theme_audit_id]['restored_at'] ?? '', 'Theme-file rollback should mark the audit record restored.');

echo "PASS\n";
