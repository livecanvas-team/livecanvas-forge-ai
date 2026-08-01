<?php

declare(strict_types=1);

$wp_root = rtrim((string) getenv('LCFA_WP_ROOT'), '/');
if ($wp_root === '' || !is_readable($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "LCFA_WP_ROOT must point to a readable WordPress installation.\n");
    exit(1);
}

require $wp_root . '/wp-load.php';
wp_set_current_user(1);

function houseflow_rollback_execute(string $name, array $input): array {
    $ability = wp_get_ability($name);
    if (!$ability) {
        throw new RuntimeException('Ability not registered: ' . $name);
    }

    $result = $ability->execute($input);
    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    return is_array($result) ? $result : [];
}

$state = (array) get_option('lcfa_houseflow_state', []);
$page_id = absint($state['homepage_id'] ?? 0);
$original = $page_id > 0 ? (string) get_post_field('post_content', $page_id) : '';
$original_status = $page_id > 0 ? (string) get_post_status($page_id) : '';
$search = 'One calm place for family life';
$replacement = 'One calm place for family life — rollback check';
$audit_id = '';
$report = [
    'checked_at' => gmdate('c'),
    'page_id'    => $page_id,
    'ok'         => false,
];

try {
    if ($page_id < 1 || $original === '') {
        throw new RuntimeException('The generated homepage is unavailable for rollback verification.');
    }
    if (substr_count($original, $search) !== 1) {
        throw new RuntimeException('The rollback test phrase is not unique in the homepage.');
    }

    $payload = [
        'target_type' => 'page',
        'target_id'   => $page_id,
        'operation'   => 'replace_text',
        'search'      => $search,
        'replacement' => $replacement,
        'framework'   => 'picostrap',
    ];
    $preview_envelope = houseflow_rollback_execute('livecanvas-forge-ai/content-patch-preview', $payload);
    $preview = (array) ($preview_envelope['content_patch_preview'] ?? []);
    if (empty($preview['ok']) || (int) ($preview['match_count'] ?? 0) !== 1) {
        throw new RuntimeException((string) ($preview['message'] ?? 'Content patch preview failed.'));
    }

    $apply_envelope = houseflow_rollback_execute('livecanvas-forge-ai/content-patch-apply', $payload);
    $apply = (array) ($apply_envelope['content_patch_apply'] ?? []);
    $audit_id = sanitize_key((string) ($apply['audit_id'] ?? $apply['data']['audit']['id'] ?? ''));
    $changed = (string) get_post_field('post_content', $page_id);
    $changed_status = (string) get_post_status($page_id);
    if (empty($apply['ok']) || $audit_id === '' || strpos($changed, $replacement) === false || $changed_status !== $original_status) {
        throw new RuntimeException((string) ($apply['message'] ?? 'Content patch apply failed.'));
    }

    $restore_envelope = houseflow_rollback_execute('livecanvas-forge-ai/restore-audit-rollback', [
        'audit_id' => $audit_id,
    ]);
    $restore = (array) ($restore_envelope['result'] ?? []);
    $restored = (string) get_post_field('post_content', $page_id);
    $restored_status = (string) get_post_status($page_id);
    if (empty($restore['ok']) || !hash_equals(hash('sha256', $original), hash('sha256', $restored)) || $restored_status !== $original_status) {
        throw new RuntimeException((string) ($restore['message'] ?? 'Rollback did not restore the exact homepage content.'));
    }

    $report += [
        'preview_ok'       => true,
        'match_count'      => 1,
        'apply_ok'         => true,
        'audit_id'         => $audit_id,
        'rollback_ok'      => true,
        'exact_hash_match' => true,
        'status_preserved' => true,
        'post_status'      => $restored_status,
        'content_sha256'   => hash('sha256', $restored),
    ];
    $report['ok'] = true;
} catch (Throwable $throwable) {
    $report['error'] = $throwable->getMessage();
} finally {
    $current = $page_id > 0 ? (string) get_post_field('post_content', $page_id) : '';
    $current_status = $page_id > 0 ? (string) get_post_status($page_id) : '';
    if ($page_id > 0 && $original !== '' && (!hash_equals(hash('sha256', $original), hash('sha256', $current)) || $current_status !== $original_status)) {
        wp_update_post([
            'ID'           => $page_id,
            'post_content' => $original,
            'post_status'  => $original_status,
        ]);
        $report['emergency_cleanup'] = true;
        $report['ok'] = false;
    }
}

update_option('lcfa_houseflow_rollback_report', $report, false);

if (empty($report['ok'])) {
    fwrite(STDERR, wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
