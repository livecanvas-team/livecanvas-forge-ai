<?php
/** Real WordPress fixtures, restricted to the two user-authorized local sites. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST');
$root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site and root required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || !class_exists('LCFA_Changesets')) throw new RuntimeException('Site or build mismatch.');
function cs_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function cs_call($route, array $payload = [], string $method = 'POST') {
    $request = new WP_REST_Request($method, '/lcfa/v1/' . $route);
    if ($method === 'GET') $request->set_query_params($payload);
    else { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload)); }
    $data = rest_do_request($request)->get_data();
    return $data['result'] ?? $data;
}
function cs_context($id) {
    $context = LCFA_Write_Contract::prepare($id ? ['target_id' => $id] : ['target_type' => 'new_page']);
    cs_check(!empty($context['ok']), 'Context must be available.');
    return $context['write_context'];
}
function cs_snapshot($id) { return (new ReflectionMethod(LCFA_Changesets::class, 'snapshot'))->invoke(null, $id); }
function cs_undo($change, $id, $extra = []) { return cs_call('changesets/undo', $extra + ['changeset_id' => $change, 'target_id' => $id, 'write_context' => cs_context($id), 'acknowledge_shared' => true]); }
global $wpdb;
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
cs_check(!empty($admins), 'Existing administrator identity required.');
$owner = (int) $admins[0]; wp_set_current_user($owner);
$prefix = 'Bridge changeset fixture ' . bin2hex(random_bytes(6));
$fixtures = []; $results = [];
$table = $wpdb->prefix . 'lcfa_changesets';
$baseline = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$baseline_ids = array_column($baseline, 'ID');
$baseline_where = implode(',', array_map('intval', $baseline_ids)) ?: '0';
$baseline_meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($baseline_where) ORDER BY meta_id", ARRAY_A);
$baseline_terms = $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} WHERE object_id IN ($baseline_where) ORDER BY object_id,term_taxonomy_id", ARRAY_A);
$markup = '<section class="container"><h1>About the test team</h1><p>Fictional test content.</p></section>';
try {
    $create = cs_call('command', ['action' => 'create_page', 'title' => $prefix, 'status' => 'draft', 'body_html' => $markup, 'write_context' => cs_context(0)]);
    $id = (int) ($create['target_id'] ?? 0);
    if ($id) $fixtures[] = $id;
    cs_check(!empty($create['ok']) && $id && ($create['changeset']['status'] ?? '') === 'saved', 'Draft creation must produce a saved changeset: ' . wp_json_encode($create));
    cs_check(get_post_status($id) === 'draft', 'New page must remain a draft.');
    $created_change = $create['changeset']['id'];
    $initial = cs_snapshot($id);
    $context = cs_context($id);
    $update = cs_call('command', ['action' => 'update_page', 'target_id' => $id, 'body_html' => str_replace('Fictional test content.', 'Updated test content.', $markup), 'page_css' => '.bridge-fixture-note { color: #123456; }', 'write_context' => $context]);
    cs_check(!empty($update['ok']) && !empty($update['changeset']['undo_available']), 'Page update must be undoable: ' . wp_json_encode($update));
    $change = $update['changeset']['id'];
    $after = cs_snapshot($id);
    $stale = cs_call('command', ['action' => 'update_page', 'target_id' => $id, 'body_html' => $markup, 'write_context' => $context]);
    cs_check(($stale['code'] ?? '') === 'stale_context', 'A stale context must be rejected.');
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s", $change), ARRAY_A);
    cs_check(strpos($record['before_snapshot'], 'Fictional test content') === false, 'Snapshot must be encrypted in the private table.');
    $list = cs_call('changesets', [], 'GET');
    cs_check(!empty($list['ok']) && strpos(wp_json_encode($list), 'before_snapshot') === false && strpos(wp_json_encode($list), 'post_content') === false, 'Public changeset summaries must not contain snapshot bytes.');
    $ability = wp_get_ability('livecanvas-forge-ai/list-changesets');
    cs_check($ability && $ability->execute([]) === $list, 'Remote Ability and REST index must match.');
    $wpdb->update($table, ['owner_user_id' => $owner + 1000000], ['id' => $change]);
    cs_check((cs_undo($change, $id)['code'] ?? '') === 'changeset_not_found', 'Another owner must not access the change.');
    $wpdb->update($table, ['owner_user_id' => $owner], ['id' => $change]);
    $wpdb->update($table, ['before_snapshot' => 'damaged'], ['id' => $change]);
    cs_check((cs_undo($change, $id)['code'] ?? '') === 'changeset_snapshot_invalid', 'Tampered snapshots must block Undo.');
    cs_check(cs_snapshot($id) === $after, 'Rejected snapshot must leave all content and metadata unchanged.');
    $wpdb->update($table, ['before_snapshot' => $record['before_snapshot']], ['id' => $change]);
    update_post_meta($id, '_lcfa_test_concurrent_edit', 'manual edit');
    $manual = cs_snapshot($id);
    cs_check((cs_undo($change, $id)['code'] ?? '') === 'changeset_conflict', 'Intervening metadata edits must block Undo.');
    cs_check(cs_snapshot($id) === $manual, 'Conflict rejection must preserve the manual edit.');
    delete_post_meta($id, '_lcfa_test_concurrent_edit');
    $preview = cs_undo($change, $id, ['dry_run' => true]);
    cs_check(!empty($preview['ok']) && cs_snapshot($id) === $after, 'Undo preview must not modify content.');
    $attempted_commit = false;
    $interrupt_undo = static function ($sql) use (&$attempted_commit, $wpdb) {
        if (!$attempted_commit && LCFA_Changesets::active() && preg_match('/^UPDATE .*posts.* SET /i', $sql)) {
            $attempted_commit = true;
            $wpdb->query('COMMIT');
        }
        return $sql;
    };
    add_filter('query', $interrupt_undo, 5);
    try { $interrupted = cs_undo($change, $id); }
    finally { remove_filter('query', $interrupt_undo, 5); }
    cs_check($attempted_commit && ($interrupted['code'] ?? '') === 'changeset_transaction_interrupted' && !empty($interrupted['transaction']['database_rollback_verified']), 'A third-party commit during Undo must be blocked with verified rollback.');
    cs_check(cs_snapshot($id) === $after, 'Interrupted Undo must preserve the saved state for a later retry.');
    $undo = cs_undo($change, $id);
    cs_check(!empty($undo['ok']) && cs_snapshot($id) === $initial, 'Undo must restore content and all managed metadata byte-for-byte.');
    cs_check(!empty(cs_undo($change, $id)['already_undone']), 'Repeating Undo must be idempotent.');
    $results['page_and_managed_metadata'] = 'restored';
    $results['undo_transaction_interruption'] = 'rejected_and_rollback_verified';
    $results['stale_context_owner_tamper_conflict'] = 'rejected';

    $failure_payload = ['action' => 'update_page', 'target_id' => $id, 'body_html' => $markup, 'write_context' => cs_context($id)];
    $called = false;
    $snapshot_failure = static function ($sql) use ($table) {
        if (preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false) throw new RuntimeException('changeset_snapshot_failed: Injected snapshot storage failure.');
        return $sql;
    };
    add_filter('query', $snapshot_failure, 1);
    try { $failed = LCFA_Changesets::run_post($failure_payload, static function () use (&$called) { $called = true; return ['ok' => true]; }); }
    finally { remove_filter('query', $snapshot_failure, 1); }
    cs_check(!$called && ($failed['code'] ?? '') === 'changeset_snapshot_failed' && cs_snapshot($id) === $initial, 'Snapshot failure must prevent the write callback.');
    $failed = LCFA_Changesets::run_post($failure_payload, static function () use ($id) {
        wp_update_post(['ID' => $id, 'post_content' => 'Transient failed write']);
        update_post_meta($id, '_lcfa_page_css', 'Transient failed asset');
        return ['ok' => false, 'code' => 'injected_failure', 'message' => 'Fixture callback failed'];
    });
    cs_check(empty($failed['ok']) && !empty($failed['transaction']['database_rollback_verified']) && cs_snapshot($id) === $initial, 'Partial database writes must roll back together.');
    $wpdb->query('START TRANSACTION'); $wpdb->query('SAVEPOINT lcfa_fixture_outer');
    try {
        $nested = LCFA_Changesets::run_post($failure_payload, static function () { throw new RuntimeException('Must never execute'); });
        cs_check(($nested['code'] ?? '') === 'changeset_transaction_busy', 'Existing transactions must be rejected.');
        cs_check($wpdb->query('ROLLBACK TO SAVEPOINT lcfa_fixture_outer') !== false, 'Existing transaction must remain open.');
    } finally { $wpdb->query('ROLLBACK'); }
    $results['snapshot_failure_partial_failure_nested_transaction'] = 'protected';

    foreach (['lc_partial' => 'update_partial', 'lc_dynamic_template' => 'update_dynamic_template'] as $type => $action) {
        $shared = wp_insert_post(['post_type' => $type, 'post_title' => $prefix . ' ' . $type, 'post_status' => 'draft', 'post_content' => $markup], true);
        cs_check(!is_wp_error($shared), 'Shared fixture creation failed.'); $fixtures[] = (int) $shared;
        $before = cs_snapshot($shared);
        $apply = cs_call('command', ['action' => $action, 'target_id' => $shared, 'content' => str_replace('test team', 'shared team', $markup), 'acknowledge_shared' => true, 'write_context' => cs_context($shared)]);
        cs_check(!empty($apply['ok']) && !empty($apply['changeset']['id']), 'Shared target write needs a changeset: ' . wp_json_encode($apply));
        $restore = cs_undo($apply['changeset']['id'], $shared);
        cs_check(!empty($restore['ok']) && cs_snapshot($shared) === $before, 'Shared target must restore all content and assignments.');
        $results[$type] = 'restored';
    }

    $template_before = cs_snapshot($shared);
    $assigned_before = cs_snapshot($id);
    cs_check(get_post_field('post_name', $shared) === '', 'Regression fixture must start with an empty draft template slug.');
    $assigned = cs_call('command', ['action' => 'update_dynamic_template', 'target_id' => $shared, 'content' => $markup,
        'template_assignment' => ['target' => 'post', 'assigned_post_id' => $id], 'acknowledge_shared' => true, 'write_context' => cs_context($shared)]);
    cs_check(!empty($assigned['ok']) && get_post_meta($id, 'lc_use_template_of_slug', true) !== '', 'Template assignment must journal the related page: ' . wp_json_encode($assigned));
    cs_check(get_post_meta($id, 'lc_use_template_of_slug', true) === get_post_field('post_name', $shared), 'Assigned page must reference the verified template slug.');
    update_post_meta($id, '_lcfa_test_related_conflict', 'manual edit');
    cs_check((cs_undo($assigned['changeset']['id'], $shared)['code'] ?? '') === 'changeset_conflict', 'An edit to an assigned page must block the entire template Undo.');
    delete_post_meta($id, '_lcfa_test_related_conflict');
    cs_check(!empty(cs_undo($assigned['changeset']['id'], $shared)['ok']), 'Template assignment Undo failed.');
    cs_check(cs_snapshot($shared) === $template_before && cs_snapshot($id) === $assigned_before, 'Template Undo must restore both template and assigned-page metadata.');
    $results['template_assigned_page'] = 'related_conflict_rejected_and_both_restored';

    $reject_relation = static function ($check, $object_id, $key) use ($id) { return (int) $object_id === $id && $key === 'lc_use_template_of_slug' ? false : $check; };
    add_filter('add_post_metadata', $reject_relation, 10, 3);
    try {
        $failed_assignment = cs_call('command', ['action' => 'update_dynamic_template', 'target_id' => $shared, 'content' => $markup,
            'template_assignment' => ['target' => 'post', 'assigned_post_id' => $id], 'acknowledge_shared' => true, 'write_context' => cs_context($shared)]);
    } finally { remove_filter('add_post_metadata', $reject_relation, 10); }
    cs_check(empty($failed_assignment['ok']) && !empty($failed_assignment['transaction']['database_rollback_verified']), 'A failed relation write must fail and roll back the template operation.');
    cs_check(cs_snapshot($shared) === $template_before && cs_snapshot($id) === $assigned_before, 'Failed assignment must preserve both targets including the empty slug.');
    $results['failed_template_assignment'] = 'rolled_back';

    $template_context = LCFA_Write_Contract::prepare(['target_type' => 'dynamic_template']);
    cs_check(!empty($template_context['ok']), 'New template context must be available.');
    $new_template = cs_call('command', ['action' => 'create_dynamic_template', 'title' => $prefix . ' new template', 'status' => 'draft', 'content' => $markup,
        'template_assignment' => ['target' => 'post', 'assigned_post_id' => $id], 'acknowledge_shared' => true, 'write_context' => $template_context['write_context']]);
    $template_id = (int) ($new_template['target_id'] ?? 0);
    if ($template_id) $fixtures[] = $template_id;
    cs_check(!empty($new_template['ok']) && $template_id && get_post_status($template_id) === 'draft', 'New assigned template must remain a draft: ' . wp_json_encode($new_template));
    cs_check(get_post_meta($id, 'lc_use_template_of_slug', true) === get_post_field('post_name', $template_id) && get_post_field('post_name', $template_id) !== '', 'Created template needs a verified assignment slug.');
    cs_check(!empty(cs_undo($new_template['changeset']['id'], $template_id)['ok']) && get_post_status($template_id) === 'trash' && cs_snapshot($id) === $assigned_before, 'Undo new template must trash it and restore the assigned page.');
    $results['new_template_assignment'] = 'draft_created_and_related_page_restored';

    $article = wp_insert_post(['post_type' => 'post', 'post_title' => $prefix . ' editorial', 'post_status' => 'draft', 'post_content' => '<style>.legacy{color:red}</style><p>Legacy editorial bytes &amp; quotes.</p>', 'comment_status' => 'open', 'ping_status' => 'open'], true);
    cs_check(!is_wp_error($article), 'Editorial fixture failed.'); $fixtures[] = (int) $article;
    $editorial = cs_snapshot($article);
    cs_check(strpos($editorial['post']['post_content'], '<style>') !== false, 'The regression fixture must contain legacy CSS before KSES runs.');
    $discussion = ['action' => 'update_discussion_settings', 'target_id' => $article, 'comment_status' => 'closed', 'ping_status' => 'closed', 'write_context' => cs_context($article)];
    $closed = LCFA_Changesets::run_post($discussion, static function () use ($discussion, $owner) {
        wp_set_current_user(0); kses_init();
        try {
            // Execution identity changed: inspect the target again under the
            // actual worker identity, as required by the write contract.
            $discussion['write_context'] = cs_context($discussion['target_id']);
            return LCFA_Write_Contract::update_discussion($discussion);
        }
        finally { wp_set_current_user($owner); kses_init(); }
    });
    cs_check(!empty($closed['ok']) && $closed['execution_identity']['user_id'] === 0 && !empty($closed['content_preserved']), 'Discussion change must preserve content with KSES active under user 0: ' . wp_json_encode($closed));
    cs_check(cs_snapshot($article)['post']['post_content'] === $editorial['post']['post_content'], 'Editorial bytes changed while disabling comments.');
    cs_check(!empty(cs_undo($closed['changeset']['id'], $article)['ok']) && cs_snapshot($article) === $editorial, 'Discussion Undo must restore settings without sanitizing content.');
    $results['discussion_execution_user_0'] = 'content_preserved_and_restored';

    $trash = cs_undo($created_change, $id);
    cs_check(!empty($trash['ok']) && get_post_status($id) === 'trash', 'Undo creation must move the fixture to recoverable Trash.');
    $results['undo_creation'] = 'recoverable_trash';
    $existing = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN (" . implode(',', array_map('intval', $baseline_ids)) . ') ORDER BY ID', ARRAY_A);
    cs_check($baseline === $existing, 'A pre-existing site post changed during isolated fixture tests.');
    $current_meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($baseline_where) ORDER BY meta_id", ARRAY_A);
    if ($baseline_meta !== $current_meta) {
        // Diagnose concurrent editor/plugin writes without printing private values.
        $left = array_column($baseline_meta, null, 'meta_id'); $right = array_column($current_meta, null, 'meta_id'); $changed_keys = [];
        foreach (array_unique(array_merge(array_keys($left), array_keys($right))) as $meta_id) {
            if (($left[$meta_id] ?? null) !== ($right[$meta_id] ?? null)) {
                $row = $right[$meta_id] ?? $left[$meta_id];
                $changed_keys[] = ['post_id' => (int) $row['post_id'], 'meta_key' => $row['meta_key']];
            }
        }
        throw new RuntimeException('Pre-existing metadata changed during fixture tests: ' . wp_json_encode($changed_keys));
    }
    cs_check($baseline_terms === $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} WHERE object_id IN ($baseline_where) ORDER BY object_id,term_taxonomy_id", ARRAY_A), 'Pre-existing taxonomy relationships changed during fixture tests.');
    echo wp_json_encode(['ok' => true, 'site' => $host, 'framework' => (new LCFA_Environment())->detect_framework_family(), 'checks' => $results, 'preexisting_posts_unchanged' => true, 'preexisting_metadata_and_assignments_unchanged' => true, 'published' => false, 'visual_check' => 'not_checked']) . "\n";
} finally {
    wp_set_current_user($owner);
    foreach ($fixtures as $fixture) {
        if (strpos((string) get_post_field('post_title', $fixture), $prefix) === 0) {
            $wpdb->delete($table, ['target_id' => $fixture]);
            wp_delete_post($fixture, true);
        }
    }
}
