<?php

declare(strict_types=1);

$test_root = sys_get_temp_dir() . '/lcfa-media-audit-' . getmypid();
define('ABSPATH', $test_root . '/wordpress/');
@mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
@mkdir(ABSPATH . 'wp-content/uploads', 0777, true);
foreach (['file.php', 'media.php', 'image.php'] as $dependency) {
    file_put_contents(ABSPATH . 'wp-admin/includes/' . $dependency, "<?php\n");
}

$GLOBALS['lcfa_media_attachments'] = [];
$GLOBALS['lcfa_media_meta'] = [];
$GLOBALS['lcfa_media_thumbnails'] = [55 => 777];

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?: ''; }
function sanitize_file_name($value): string { return preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $value) ?: ''; }
function sanitize_text_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags((string) $value)); }
function sanitize_mime_type($value): string { return strtolower(trim((string) $value)); }
function esc_url_raw($value): string { return (string) $value; }
function absint($value): int { return max(0, (int) $value); }
function is_wp_error($value): bool { return false; }
function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string { return substr(str_repeat('MediaAudit1', 3), 0, $length); }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 20:00:00'; }
function get_post_thumbnail_id(int $post_id): int { return (int) ($GLOBALS['lcfa_media_thumbnails'][$post_id] ?? 0); }
function set_post_thumbnail(int $post_id, int $attachment_id) { $GLOBALS['lcfa_media_thumbnails'][$post_id] = $attachment_id; return $attachment_id; }
function wp_upload_bits(string $filename, $deprecated, string $bytes): array {
    $path = ABSPATH . 'wp-content/uploads/' . $filename;
    file_put_contents($path, $bytes);
    return ['file' => $path, 'url' => 'https://example.test/wp-content/uploads/' . $filename, 'error' => ''];
}
function wp_check_filetype_and_ext(string $path, string $filename): array { return ['type' => 'image/png', 'ext' => 'png']; }
function wp_insert_attachment(array $postarr, string $file_path, int $post_id = 0) {
    $attachment_id = 901;
    $GLOBALS['lcfa_media_attachments'][$attachment_id] = $postarr + ['ID' => $attachment_id, 'file' => $file_path, 'post_parent' => $post_id];
    return $attachment_id;
}
function wp_generate_attachment_metadata(int $attachment_id, string $file_path): array { return ['width' => 1, 'height' => 1, 'file' => basename($file_path)]; }
function wp_update_attachment_metadata(int $attachment_id, array $metadata): bool { $GLOBALS['lcfa_media_attachments'][$attachment_id]['metadata'] = $metadata; return true; }
function wp_get_attachment_metadata(int $attachment_id) { return $GLOBALS['lcfa_media_attachments'][$attachment_id]['metadata'] ?? []; }
function wp_get_attachment_url(int $attachment_id): string { return 'https://example.test/wp-content/uploads/test.png'; }
function get_post_mime_type(int $attachment_id): string { return (string) ($GLOBALS['lcfa_media_attachments'][$attachment_id]['post_mime_type'] ?? ''); }
function get_edit_post_link(int $attachment_id, string $context = ''): string { return 'https://example.test/wp-admin/post.php?post=' . $attachment_id; }
function wp_update_post(array $postarr) { return (int) ($postarr['ID'] ?? 0); }
function update_post_meta(int $post_id, string $key, $value): bool { $GLOBALS['lcfa_media_meta'][$post_id][$key] = $value; return true; }
function get_posts(array $args = []): array { return []; }

final class LCFA_Command_Deck {}
final class LCFA_Settings {
    public static array $records = [];
    public static array $history = [];
    public static function store_rollback_record(string $audit_id, array $record): void { self::$records[$audit_id] = $record; }
    public static function append_history(array $entry): void { self::$history[] = $entry; }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-media-tools.php';

function lcfa_media_audit_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$tools = new LCFA_Media_Tools(new LCFA_Command_Deck());
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
$result = $tools->upload([
    'source_type' => 'base64',
    'filename' => 'test.png',
    'mime_type' => 'image/png',
    'base64' => $png,
    'title' => 'Rollback image',
    'asset_id' => 'rollback-image',
    'post_id' => 55,
    'set_featured' => true,
    '_lcfa_origin' => 'wp_ability',
]);

$audit_id = (string) ($result['audit_id'] ?? '');
lcfa_media_audit_assert(!empty($result['ok']), 'Media upload should succeed.');
lcfa_media_audit_assert($audit_id !== '', 'Media upload should return an audit ID.');
lcfa_media_audit_assert(!empty($result['rollback_available']), 'Media upload should expose rollback availability.');
lcfa_media_audit_assert(($GLOBALS['lcfa_media_thumbnails'][55] ?? 0) === 901, 'Media upload should set the requested featured image.');
lcfa_media_audit_assert((LCFA_Settings::$records[$audit_id]['restore']['type'] ?? '') === 'media_upload', 'Media upload should store a dedicated rollback type.');
lcfa_media_audit_assert((LCFA_Settings::$records[$audit_id]['restore']['previous_featured_image_id'] ?? 0) === 777, 'Media rollback should preserve the previous featured image ID.');
lcfa_media_audit_assert(!empty(LCFA_Settings::$records[$audit_id]['restore']['created_attachment']), 'A newly uploaded attachment should be marked for deletion during rollback.');
lcfa_media_audit_assert((LCFA_Settings::$history[0]['audit_id'] ?? '') === $audit_id, 'Media upload should append an auditable history entry.');

echo "PASS\n";
