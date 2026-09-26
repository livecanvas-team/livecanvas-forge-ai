<?php
/** Source-build test against real local WordPress. Fixture records only; no agent/provider calls. */
require __DIR__ . '/source-plugin-bootstrap.php';
function dj_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
function dj_reject(callable $call, string $code): void {
    try { $call(); } catch (RuntimeException $error) { dj_assert(str_starts_with($error->getMessage(), $code), 'Unexpected fixture failure: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected ' . $code);
}
function dj_workers(array $input, int $count = 2): array {
    $workers = []; $input['start'] = microtime(true) + 1.0;
    for ($i = 0; $i < $count; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get('mysqli.default_socket'), __DIR__ . '/desktop-delivery-worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        dj_assert(is_resource($process), 'Fixture process unavailable.');
        fwrite($pipes[0], wp_json_encode($input)); fclose($pipes[0]); $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        dj_assert(proc_close($process) === 0, 'Fixture worker failed: ' . $error);
        $results[] = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    }
    return $results;
}
global $wpdb;
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
dj_assert($admins, 'Existing administrator required.'); $owner = (int) $admins[0];
$marker = 'delivery-fixture-' . bin2hex(random_bytes(8));
$table = $wpdb->prefix . 'lcfa_private_records';
$scope = hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH));
$baseline_sessions = LCFA_Session_Store::read();
$baseline_posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$baseline_meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A);
$baseline_private = $wpdb->get_results("SELECT * FROM $table ORDER BY owner_user_id,site_hash,kind,id", ARRAY_A);
$baseline_plugins = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'");
$session_token = 'lcfa_sess_' . bin2hex(random_bytes(40));
$session = ['session_id' => $marker, 'token_hash' => hash_hmac('sha256', $session_token, wp_salt('auth')),
    'owner_user_id' => $owner, 'client' => 'codex', 'site_fingerprint' => LCFA_Settings::get_site_fingerprint(),
    'scopes' => LCFA_Agent_Registry::full_access_scopes(), 'access_profile' => 'full', 'expires_at' => gmdate('c', time() + 300), 'revoked_at' => ''];
$ids = []; $hook = null; $checks = [];
try {
    wp_set_current_user($owner);
    $thread = LCFA_Settings::create_thread('Desktop delivery fixture'); $ids[] = $thread['id'];
    $make_request = static function () use ($thread, $marker, &$ids) {
        $request = LCFA_Settings::enqueue_agent_request(['thread_id' => $thread['id'], 'agent' => 'codex', 'user_prompt' => $marker,
            'idempotency_key' => $marker . '-' . bin2hex(random_bytes(4)), 'action' => 'page_upsert']);
        $ids[] = $request['id']; $ids[] = 'delivery-' . hash('sha256', $request['id']);
        return $request['id'];
    };
    $request_id = $make_request(); $payload_hash = hash('sha256', 'synthetic scoped prompt');
    dj_reject(static fn() => LCFA_Desktop_Delivery::reserve($request_id, $marker, $payload_hash), 'delivery_owned_codex_session_required');
    LCFA_Session_Store::mutate(static function ($sessions) use ($marker, $session) { $sessions[$marker] = $session; return $sessions; });
    wp_set_current_user(0);
    dj_assert(LCFA_MCP_Session_Manager::validate_session_token($session_token), 'Fixture session validation failed.');
    $input = ['marker' => $marker, 'request_id' => $request_id, 'desktop_thread_id' => $marker, 'payload_hash' => $payload_hash, 'session_token' => $session_token];
    $results = dj_workers($input);
    dj_assert(count(array_filter($results, static fn($r) => $r['send_allowed'])) === 1, 'Concurrent controllers must receive one send grant.');
    $winner = array_values(array_filter($results, static fn($r) => $r['send_allowed']))[0];
    $receipt = $winner['receipt_token'];
    $recovered = dj_workers($input + ['operation' => 'pending'], 1)[0];
    dj_assert(!$recovered['send_allowed'] && $recovered['receipt_token'] === $receipt, 'A restarted process must recover without replay permission.');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE owner_user_id=%d AND site_hash=%s AND kind='delivery' AND id=%s", $owner, $scope, 'delivery-' . hash('sha256', $request_id)), ARRAY_A);
    dj_assert(strpos($row['payload'], $receipt) === false && strpos($row['payload'], $marker) === false, 'Delivery data must be encrypted.');
    $public = wp_json_encode([LCFA_Settings::get_threads(), LCFA_Settings::get_agent_requests()]);
    dj_assert(strpos($public, $receipt) === false && strpos($public, 'receipt_token') === false, 'Delivery receipts must not enter normal conversation or request responses.');
    dj_assert(!$winner['desktop_conversation_verified'] && !$winner['site_changes_verified'], 'Storage cannot attest Desktop identity or site writes.');
    $checks['concurrent_reservation_and_restart'] = 'one_send_grant_recovery_never_replays';

    $request_two = $make_request();
    dj_reject(static fn() => LCFA_Desktop_Delivery::reserve($request_two, $marker, $payload_hash), 'delivery_review_required');
    dj_reject(static fn() => LCFA_Desktop_Delivery::reserve($request_id, $marker . '-other', $payload_hash), 'delivery_binding_conflict');
    dj_reject(static fn() => LCFA_Desktop_Delivery::record($request_id, $marker, str_repeat('0', 64), 'turn-fixture', 'inProgress'), 'delivery_receipt_invalid');
    $ack = LCFA_Desktop_Delivery::record($request_id, $marker, $receipt, 'turn-fixture', 'inProgress');
    dj_assert($ack['state'] === 'acknowledged', 'Acknowledgement not recorded.');
    dj_reject(static fn() => LCFA_Desktop_Delivery::record($request_id, $marker, $receipt, 'turn-other', 'completed'), 'delivery_turn_conflict');
    LCFA_Desktop_Delivery::record($request_id, $marker, $receipt, 'turn-fixture', 'completed');
    LCFA_Desktop_Delivery::record($request_id, $marker, $receipt, 'turn-fixture', 'completed');
    dj_reject(static fn() => LCFA_Desktop_Delivery::record($request_id, $marker, $receipt, 'turn-fixture', 'interrupted'), 'delivery_terminal_conflict');
    dj_assert(LCFA_Private_Store::read('request', $request_id)['_state'] === 'queued', 'Delivery state must not impersonate a claimed or completed worker.');
    dj_assert(LCFA_Desktop_Delivery::pending($marker) === null, 'Settled delivery must leave the pending index.');
    $checks['receipt_lifecycle'] = 'bound_idempotent_monotonic_separate_from_worker';

    // The first read after a committed INSERT fails. Retry must not return a send grant.
    $lost_id = $make_request(); $inserted = false;
    $hook = static function ($sql) use ($wpdb, $table, &$inserted) {
        if (str_starts_with($sql, "INSERT INTO `$table`") && str_contains($sql, "'delivery'")) $inserted = true;
        if ($inserted && str_starts_with($sql, "SELECT * FROM $table") && str_contains($sql, "kind='delivery'")) return 'SELECT 1 WHERE 0';
        return $sql;
    };
    add_filter('query', $hook);
    dj_reject(static fn() => LCFA_Desktop_Delivery::reserve($lost_id, $marker . '-lost', $payload_hash), 'delivery_storage_unverified');
    remove_filter('query', $hook); $hook = null;
    dj_assert(!LCFA_Desktop_Delivery::reserve($lost_id, $marker . '-lost', $payload_hash)['send_allowed'], 'Lost reservation acknowledgement enabled replay.');
    $checks['lost_storage_acknowledgement'] = 'durable_reservation_blocks_retry';

    $failed_id = $make_request();
    $hook = static fn($sql) => str_starts_with($sql, "INSERT INTO `$table`") && str_contains($sql, "'delivery'") ? 'SELECT LCFA_EXPECTED_DELIVERY_FIXTURE_FAILURE()' : $sql;
    add_filter('query', $hook); $old_errors = $wpdb->suppress_errors(true);
    try { dj_reject(static fn() => LCFA_Desktop_Delivery::reserve($failed_id, $marker . '-failed', $payload_hash), 'private_storage_unavailable'); }
    finally { remove_filter('query', $hook); $hook = null; $wpdb->suppress_errors($old_errors); }
    dj_assert(LCFA_Desktop_Delivery::pending($marker . '-failed') === null, 'Failed reservation left an unverifiable grant.');
    $checks['storage_failure'] = 'no_grant';

    $protocol_id = $make_request(); $pipes = [];
    $process = proc_open(['node', __DIR__ . '/desktop-protocol-delivery.js'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    dj_assert(is_resource($process), 'Protocol fixture process unavailable.');
    fwrite($pipes[0], wp_json_encode(['marker' => $marker, 'request_id' => $protocol_id, 'desktop_thread_id' => $marker . '-protocol',
        'session_token' => $session_token, 'php_binary' => PHP_BINARY, 'db_socket' => ini_get('mysqli.default_socket')]));
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    dj_assert(proc_close($process) === 0 && (json_decode($output, true)['ok'] ?? false), 'Protocol fixture failed: ' . $error);
    $checks['protocol_process_restart'] = 'real_journal_synthetic_transport_one_start_no_replay';

    // Same owner, different authenticated session still cannot adopt a receipt.
    $other_token = 'lcfa_sess_' . bin2hex(random_bytes(40));
    $other_session = array_replace($session, ['session_id' => $marker . '-other', 'token_hash' => hash_hmac('sha256', $other_token, wp_salt('auth'))]);
    LCFA_Session_Store::mutate(static function ($sessions) use ($marker, $other_session) { $sessions[$marker . '-other'] = $other_session; return $sessions; });
    dj_assert(LCFA_MCP_Session_Manager::validate_session_token($other_token), 'Second fixture session failed.');
    dj_reject(static fn() => LCFA_Desktop_Delivery::pending($marker . '-lost'), 'delivery_binding_conflict');
    dj_assert(LCFA_MCP_Session_Manager::validate_session_token($session_token), 'Original fixture session failed.');
    LCFA_Session_Store::mutate(static function ($sessions) use ($marker) { $sessions[$marker]['revoked_at'] = gmdate('c'); return $sessions; });
    dj_reject(static fn() => LCFA_Desktop_Delivery::pending($marker . '-lost'), 'delivery_owned_codex_session_required');
    $checks['session_revocation_and_isolation'] = 'rejected';
} finally {
    if ($hook) remove_filter('query', $hook);
    wp_set_current_user($owner);
    foreach ($ids as $id) $wpdb->delete($table, ['owner_user_id' => $owner, 'site_hash' => $scope, 'id' => $id]);
    LCFA_Session_Store::mutate(static function ($sessions) use ($marker) { unset($sessions[$marker], $sessions[$marker . '-other']); return $sessions; });
}
dj_assert(LCFA_Session_Store::read() === $baseline_sessions, 'Existing sessions changed.');
dj_assert($wpdb->get_results("SELECT * FROM $table ORDER BY owner_user_id,site_hash,kind,id", ARRAY_A) === $baseline_private, 'Existing private records changed or fixture cleanup failed.');
dj_assert($wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A) === $baseline_posts, 'Existing site content changed.');
dj_assert($wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A) === $baseline_meta, 'Existing content metadata changed.');
dj_assert($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'") === $baseline_plugins, 'Plugin activation settings changed.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'build' => $fixture_build === 'installed' ? 'installed_package' : 'working_tree_source_loaded_in_fixture_process_only', 'checks' => $checks,
    'existing_content_and_metadata' => 'unchanged', 'existing_sessions_and_private_records' => 'unchanged', 'plugin_activation' => 'unchanged', 'actual_agent' => 'not_invoked']) . "\n";
