<?php
/** Isolated, unused theme sources only. Never edit an existing template. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('An authorized local site and root are required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || !method_exists('LCFA_Changesets', 'run_file')) throw new RuntimeException('Site or installed build mismatch.');
function fc_check($value, $message) { if (!$value) throw new RuntimeException($message); }
function fc_call($route, $payload, $method = 'POST') {
    $request = new WP_REST_Request($method, '/lcfa/v1/' . $route);
    if ($method === 'GET') $request->set_query_params($payload);
    else { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload)); }
    $response = rest_do_request($request); $body = $response->get_data();
    return $body['result'] ?? $body;
}
function fc_payload($path, $content = '') {
    $context = LCFA_Write_Contract::prepare(['target_type' => 'theme_file', 'path' => $path]);
    fc_check(!empty($context['ok']), 'Theme-file context is unavailable.');
    return ['path' => $path, 'content' => $content, 'write_context' => $context['write_context'], 'acknowledge_shared' => true];
}
function fc_undo($change, $path, $extra = []) { return fc_call('changesets/undo', $extra + ['changeset_id' => $change] + fc_payload($path)); }
function fc_tree($root) {
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && !$file->isLink()) $files[$file->getPathname()] = [hash_file('sha256', $file->getPathname()), $file->getPerms() & 0777];
    }
    ksort($files); return $files;
}
global $wpdb;
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
fc_check($owner > 0, 'An existing administrator identity is required.'); wp_set_current_user($owner);
$child = realpath(get_stylesheet_directory());
fc_check($child !== realpath(get_template_directory()), 'A child theme is required.');
$baseline = fc_tree($child);
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$prefix = 'lcfa-file-fixture-' . bin2hex(random_bytes(6));
$path = $prefix . '.css'; $absolute = $child . '/' . $path;
$other_path = $prefix . '.twig'; $other = $child . '/' . $other_path;
$link_path = $prefix . '-link.css';
$created_paths = [$absolute, $other, $child . '/' . $link_path];
$table = $wpdb->prefix . 'lcfa_changesets'; $results = [];
$original = "/* unused test fixture */\n.test-fixture::after { content: 'a\\b'; }\n";
$changed = "/* unused test fixture */\n.test-fixture::after { content: 'c\\d'; }\n";
try {
    fc_check(!file_exists($absolute), 'Fixture collision.');
    file_put_contents($absolute, $original); chmod($absolute, 0640);
    $payload = fc_payload($path, $changed);
    $preview = fc_call('theme/file', ['dry_run' => true] + $payload);
    fc_check(!empty($preview['ok']) && empty($preview['verification_states']['saved']) && file_get_contents($absolute) === $original, 'Preview must leave the theme unchanged.');
    $save = fc_call('theme/file', $payload);
    fc_check(!empty($save['ok']) && !empty($save['changeset']['undo_available']), 'Theme write needs a saved changeset: ' . wp_json_encode($save));
    $change = $save['changeset']['id'];
    fc_check(file_get_contents($absolute) === $changed && (fileperms($absolute) & 0777) === 0640, 'REST must preserve backslashes and file permissions exactly.');
    fc_check(($save['verification_states']['compiled'] ?? '') === 'not_checked' && !empty($save['verification_states']['may_affect_live_rendering']), 'A file save must not claim compilation or visual verification.');
    $stale = fc_call('theme/file', $payload);
    fc_check(($stale['code'] ?? '') === 'stale_context', 'Reused context must be rejected.');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s", $change), ARRAY_A);
    fc_check(strpos($row['before_snapshot'], 'test-fixture') === false && strpos($row['before_snapshot'], $path) === false, 'Journal payload must be encrypted.');
    $list = fc_call('changesets', [], 'GET');
    fc_check(strpos(wp_json_encode($list), $path) !== false && strpos(wp_json_encode($list), 'before_snapshot') === false, 'Private index must expose paths, not snapshot bytes.');
    $wpdb->update($table, ['owner_user_id' => $owner + 1000000], ['id' => $change]);
    fc_check((fc_undo($change, $path)['code'] ?? '') === 'changeset_not_found', 'Foreign ownership must block Undo.');
    $wpdb->update($table, ['owner_user_id' => $owner], ['id' => $change]);
    $wpdb->update($table, ['before_snapshot' => 'corrupt'], ['id' => $change]);
    fc_check((fc_undo($change, $path)['code'] ?? '') === 'changeset_snapshot_invalid', 'Corrupt snapshots must block Undo.');
    $wpdb->update($table, ['before_snapshot' => $row['before_snapshot']], ['id' => $change]);
    file_put_contents($absolute, $changed . '/* manual edit */');
    $manual = file_get_contents($absolute);
    fc_check((fc_undo($change, $path, ['force' => true])['code'] ?? '') === 'changeset_conflict' && file_get_contents($absolute) === $manual, 'Even force must not overwrite intervening edits.');
    file_put_contents($absolute, $changed); chmod($absolute, 0644);
    fc_check((fc_undo($change, $path)['code'] ?? '') === 'changeset_conflict', 'Permission changes must also block Undo.');
    chmod($absolute, 0640);
    $preview = fc_undo($change, $path, ['dry_run' => true]);
    fc_check(!empty($preview['ok']) && file_get_contents($absolute) === $changed, 'Undo preview must not write.');
    $undo_failed = false;
    $undo_failure = static function ($sql) use ($table, &$undo_failed) {
        if (!$undo_failed && preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'undone'") !== false) {
            $undo_failed = true; throw new RuntimeException('changeset_journal_failed: Injected Undo acknowledgement failure.');
        }
        return $sql;
    };
    add_filter('query', $undo_failure, 1);
    try { $failed_undo = fc_undo($change, $path); }
    finally { remove_filter('query', $undo_failure, 1); }
    fc_check($undo_failed && ($failed_undo['changeset']['status'] ?? '') === 'saved' && !empty($failed_undo['recovery']['file_compensation_verified']) && file_get_contents($absolute) === $changed, 'An interrupted Undo must restore the saved state for retry.');
    $undo_ability = wp_get_ability('livecanvas-forge-ai/undo-changeset');
    $undo = $undo_ability->execute(['changeset_id' => $change] + fc_payload($path));
    fc_check(!is_wp_error($undo) && !empty($undo['ok']) && file_get_contents($absolute) === $original && (fileperms($absolute) & 0777) === 0640, 'Remote Ability must restore the REST write exactly.');
    fc_check(!empty(fc_undo($change, $path)['already_undone']), 'Repeated file Undo must be idempotent.');
    $results['rest_ability_byte_exact_undo_and_permissions'] = 'passed';
    $results['undo_acknowledgement_failure'] = 'compensated_for_safe_retry';
    $results['stale_owner_tamper_manual_edit_and_mode_conflicts'] = 'rejected';

    $nested = [];
    $failure = static function ($sql) use ($table, &$nested) {
        if (preg_match('/^\s*INSERT/i', $sql) && strpos($sql, $table) !== false) {
            $nested = [LCFA_Changesets::run_file([]), LCFA_Changesets::undo([]), LCFA_Changesets::run_post([], static function () { throw new RuntimeException('Nested callback must never run.'); })];
            throw new RuntimeException('changeset_snapshot_failed: Injected storage failure.');
        }
        return $sql;
    };
    add_filter('query', $failure, 1);
    try { $failed = fc_call('theme/file', fc_payload($path, $changed)); }
    finally { remove_filter('query', $failure, 1); }
    fc_check(($failed['code'] ?? '') === 'changeset_snapshot_failed' && file_get_contents($absolute) === $original, 'Failed snapshot must prevent any file write.');
    fc_check(count($nested) === 3 && array_column($nested, 'code') === ['nested_changeset', 'nested_changeset', 'nested_changeset'], 'Nested content, file or Undo operations must be rejected before execution.');

    $fired = false;
    $journal_failure = static function ($sql) use ($table, &$fired) {
        if (!$fired && preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'saved'") !== false) {
            $fired = true; throw new RuntimeException('changeset_journal_failed: Injected final journal failure.');
        }
        return $sql;
    };
    add_filter('query', $journal_failure, 1);
    try { $failed = fc_call('theme/file', fc_payload($path, $changed)); }
    finally { remove_filter('query', $journal_failure, 1); }
    fc_check($fired && empty($failed['ok']) && !empty($failed['recovery']['file_compensation_verified']) && file_get_contents($absolute) === $original, 'Final journal failure must compensate the file, not report saved.');

    $fired = false;
    $external_edit = static function ($sql) use ($table, $absolute, &$fired) {
        if (!$fired && preg_match('/^\s*UPDATE/i', $sql) && strpos($sql, $table) !== false && strpos($sql, "'saved'") !== false) {
            $fired = true; file_put_contents($absolute, '/* external fixture edit */');
            throw new RuntimeException('changeset_journal_failed: Injected external conflict after file replacement.');
        }
        return $sql;
    };
    add_filter('query', $external_edit, 1);
    try { $failed = fc_call('theme/file', fc_payload($path, $changed)); }
    finally { remove_filter('query', $external_edit, 1); }
    fc_check($fired && ($failed['changeset']['status'] ?? '') === 'needs_recovery' && file_get_contents($absolute) === '/* external fixture edit */', 'Compensation must not overwrite an external change.');
    file_put_contents($absolute, $original);
    $results['snapshot_failure_and_partial_failure'] = 'blocked_or_compensated';
    $results['concurrent_external_change_during_failure'] = 'preserved_needs_recovery';

    symlink($absolute, $child . '/' . $link_path);
    $symlink = fc_call('theme/file', fc_payload($link_path, $changed));
    fc_check(($symlink['code'] ?? '') === 'changeset_path_invalid' && file_get_contents($absolute) === $original, 'Symlinks must be rejected even inside the child root.');
    unlink($child . '/' . $link_path);
    $new_content = '<section><h2>Unused About us fixture</h2></section>';
    $write_ability = wp_get_ability('livecanvas-forge-ai/theme-file-write');
    fc_check((bool) $write_ability, 'Theme-file Ability is required.');
    $created = $write_ability->execute(fc_payload($other_path, $new_content));
    fc_check(!is_wp_error($created), 'Theme-file Ability failed.'); $created = $created['theme_file_write'] ?? $created;
    fc_check(!empty($created['ok']) && !empty($created['created']), 'New template write must have a private changeset: ' . wp_json_encode($created));
    $new_undo = fc_undo($created['changeset']['id'], $other_path);
    fc_check(!empty($new_undo['ok']) && !file_exists($other) && !empty($new_undo['removed_file_recoverable_from_private_journal']), 'Undo must safely remove the unchanged created file.');
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s", $created['changeset']['id']), ARRAY_A);
    $snapshot = (new ReflectionMethod(LCFA_Changesets::class, 'open_snapshot'))->invoke(null, $record);
    fc_check(base64_decode($snapshot['after']['content']) === $new_content, 'Removed bytes must remain privately recoverable.');
    $results['new_file_undo_and_private_recovery'] = 'passed';

    $command = fc_call('command', ['action' => 'write_theme_file', 'file_path' => $path] + fc_payload($path, $changed));
    fc_check(!empty($command['ok']) && !empty($command['changeset']['id']), 'Command writes must return the same journal contract: ' . wp_json_encode($command));
    fc_check(!empty(fc_undo($command['changeset']['id'], $path)['ok']), 'Command write must use the same Undo tool.');
    $results['command_route_journal_parity'] = 'passed';
    $deny_editor = static function ($allcaps) { $allcaps['edit_themes'] = false; return $allcaps; };
    add_filter('user_has_cap', $deny_editor);
    try { $forbidden = LCFA_Changesets::run_file(fc_payload($path, $changed)); }
    finally { remove_filter('user_has_cap', $deny_editor); }
    fc_check(($forbidden['code'] ?? '') === 'changeset_forbidden' && file_get_contents($absolute) === $original, 'Content editing permission alone must not allow executable theme-file changes.');
    $results['theme_editor_permission_required'] = 'passed';
} finally {
    foreach ($created_paths as $file) if (is_file($file) || is_link($file)) unlink($file);
    if (get_option('lcfa_changeset_schema')) {
        foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE owner_user_id=%d", $owner), ARRAY_A) as $row) {
            if ($row['action'] !== 'write_theme_file') continue;
            try { $snapshot = (new ReflectionMethod(LCFA_Changesets::class, 'open_snapshot'))->invoke(null, $row); }
            catch (Throwable $error) { continue; }
            if (strpos($snapshot['path'] ?? '', $prefix) === 0) $wpdb->delete($table, ['id' => $row['id']]);
        }
    }
    fc_check(fc_tree($child) === $baseline, 'Existing theme sources or permissions changed.');
    fc_check($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A) === $posts, 'Existing content changed.');
}
echo wp_json_encode(['ok' => true, 'host' => $host, 'tests' => $results, 'fixtures_removed' => true, 'existing_theme_and_content_unchanged' => true, 'actual_coding_agent' => 'not_invoked'], JSON_PRETTY_PRINT) . "\n";
