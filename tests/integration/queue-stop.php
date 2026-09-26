<?php
/** Isolated queue and session fixtures. Does not launch a coding agent. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || LCFA_Private_Chat::capabilities()['queue_protocol'] !== 'post_claim_lease_v2') throw new RuntimeException('Site or build mismatch.');
function qs_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
function qs_call($route, array $payload, array $headers = []): WP_REST_Response {
    $request = new WP_REST_Request('POST', '/lcfa/v1/' . $route);
    $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload));
    foreach ($headers as $name => $value) $request->set_header($name, $value);
    return rest_do_request($request);
}
global $wpdb;
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
qs_assert($owner > 0, 'Existing administrator required.');
$prefix = 'stop-fixture-' . bin2hex(random_bytes(8)); $session_id = $prefix;
$token = 'lcfa_sess_' . bin2hex(random_bytes(40));
$sessions_before = LCFA_Session_Store::read();
$posts_before = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$table = $wpdb->prefix . 'lcfa_private_records';
$scope = hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH));
$ids = []; $checks = []; $writes = 0;
$session = ['session_id' => $session_id, 'client' => 'codex', 'owner_user_id' => $owner, 'scopes' => LCFA_Agent_Registry::full_access_scopes(), 'access_profile' => 'full',
    'site_fingerprint' => LCFA_Settings::get_site_fingerprint(), 'token_hash' => hash_hmac('sha256', $token, wp_salt('auth')), 'expires_at' => gmdate('c', time() + 600), 'revoked_at' => ''];
$headers = ['X-LCFA-MCP-Session' => $token, 'X-LCFA-MCP-Package-Version' => LCFA_MCP_PACKAGE_VERSION];
try {
    LCFA_Session_Store::mutate(static function ($sessions) use ($session_id, $session) { $sessions[$session_id] = $session; return $sessions; });
    wp_set_current_user($owner);
    $thread = LCFA_Settings::create_thread('Stop protocol fixture'); $thread_id = $thread['id']; $ids[] = $thread_id;
    $enqueue = static function ($suffix) use ($thread_id, $prefix, &$ids, $owner) {
        wp_set_current_user($owner);
        $record = LCFA_Settings::enqueue_agent_request(['thread_id' => $thread_id, 'agent' => 'codex', 'user_prompt' => 'Read-only local lifecycle fixture', 'idempotency_key' => $prefix . '-' . $suffix]);
        $ids[] = $record['id']; wp_set_current_user(0); return $record['id'];
    };
    // In-process probe uses the actual REST permission callback, without a site write.
    $routes = rest_get_server()->get_routes();
    $controller = $routes['/lcfa/v1/command'][0]['permission_callback'][0];
    foreach (['write', 'media', 'theme_files', 'debug', 'cache', 'seo'] as $scope_name) {
        register_rest_route('lcfa/v1', '/fixture-stop-' . $scope_name, ['methods' => 'POST', 'permission_callback' => [$controller, 'can_' . $scope_name], 'callback' => static function () use (&$writes) { $writes++; return ['ok' => true]; }]);
    }
    $id = $enqueue('queued');
    qs_assert(rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/agent/request/pending'))->get_status() >= 400, 'Anonymous pending work must be private.');
    wp_set_current_user($owner);
    $pending = rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/agent/request/pending'));
    qs_assert($pending->get_status() === 200, 'Owned pending work could not be recovered.');
    $pending_items = $pending->get_data()['requests'];
    $fixture_pending = array_values(array_filter($pending_items, static fn($item) => $item['id'] === $id));
    qs_assert(count($fixture_pending) === 1 && $fixture_pending[0]['status'] === 'queued', 'Pending recovery lost queued work.');
    foreach ($pending_items as $item) {
        qs_assert(!array_diff(array_keys($item), ['id', 'thread_id', 'status', 'post_id', 'context_post_id', 'target_id', 'stop_requested_at', 'error']), 'Pending recovery exposed prompt data or private worker fields.');
    }
    $checks['pending_recovery'] = 'owner_only_without_prompts_or_capabilities';
    qs_assert(qs_call('chat/thread', ['operation' => 'clear', 'thread_id' => $thread_id])->get_status() === 409, 'Clearing queued work must be blocked.');
    $cancel = qs_call('agent/request/cancel', ['request_id' => $id]);
    qs_assert($cancel->get_status() === 200 && $cancel->get_data()['request']['status'] === 'cancelled', 'Queued cancellation failed.');
    wp_set_current_user(0);
    qs_assert(qs_call('agent/request/claim', ['request_id' => $id, 'agent' => 'codex'], $headers)->get_data()['status'] === 'empty', 'Cancelled work was claimed.');
    $checks['queued_stop'] = 'cancelled_before_execution';

    $id = $enqueue('running');
    $claim = qs_call('agent/request/claim', ['request_id' => $id, 'agent' => 'codex'], $headers);
    qs_assert($claim->get_status() === 200 && $claim->get_data()['status'] === 'claimed', 'Owned worker claim failed.');
    $lease = $claim->get_data()['lease_token'];
    $worker_headers = $headers + ['X-LCFA-Request-ID' => $id, 'X-LCFA-Worker-Lease' => $lease];
    qs_assert((LCFA_Session_Store::read()[$session_id]['worker_request_id'] ?? '') === $id, 'Claim did not persist the worker/session binding.');
    qs_assert(qs_call('fixture-stop-write', [], $headers)->get_status() >= 400, 'An active worker must not omit its capability.');
    qs_assert(qs_call('fixture-stop-write', [], $worker_headers)->get_status() === 200 && $writes === 1, 'Valid running worker was blocked.');
    wp_set_current_user($owner);
    qs_assert(qs_call('chat/thread', ['operation' => 'delete', 'thread_id' => $thread_id])->get_status() === 409, 'Archiving running work must be blocked.');
    $cancel = qs_call('agent/request/cancel', ['request_id' => $id]);
    qs_assert($cancel->get_data()['request']['status'] === 'stop_requested', 'Running Stop cannot claim immediate completion.');
    wp_set_current_user(0);
    $renew = qs_call('agent/request/renew', ['request_id' => $id, 'lease_token' => $lease], $headers);
    qs_assert($renew->get_status() === 200 && $renew->get_data()['request']['status'] === 'stop_requested', 'Worker heartbeat must receive the Stop signal.');
    foreach (['write', 'media', 'theme_files', 'debug', 'cache', 'seo'] as $scope_name) {
        qs_assert(qs_call('fixture-stop-' . $scope_name, [], $worker_headers)->get_status() >= 400, 'Stopped worker retained ' . $scope_name . ' access.');
        // Even a concurrent administrator cookie cannot bypass a supplied worker session.
        wp_set_current_user($owner);
        qs_assert(qs_call('fixture-stop-' . $scope_name, [], $headers)->get_status() >= 400, 'Removing lease headers bypassed ' . $scope_name . ' fencing.');
        wp_set_current_user(0);
    }
    qs_assert($writes === 1, 'A rejected worker reached a mutation callback.');
    qs_assert(qs_call('agent/request/complete', ['request_id' => $id, 'lease_token' => $lease, 'result' => ['ok' => true]], $headers)->get_status() === 409, 'Stopped work must not report completion.');
    qs_assert(qs_call('agent/request/acknowledge-stop', ['request_id' => $id, 'lease_token' => 'wrong'], $headers)->get_status() === 409, 'Another worker must not acknowledge Stop.');
    $ack = qs_call('agent/request/acknowledge-stop', ['request_id' => $id, 'lease_token' => $lease], $headers);
    qs_assert($ack->get_status() === 200 && $ack->get_data()['request']['status'] === 'stopped', 'Worker acknowledgement failed.');
    qs_assert($ack->get_data()['request']['external_process_stop_verified'] === false, 'Bridge must not claim to stop external processes.');
    qs_assert(qs_call('agent/request/acknowledge-stop', ['request_id' => $id, 'lease_token' => $lease], $headers)->get_status() === 200, 'Stop acknowledgement must be idempotent.');
    qs_assert(qs_call('fixture-stop-write', [], $headers)->get_status() >= 400, 'Restarting a stopped runtime must not remove the server write fence.');
    $checks['running_stop'] = 'acknowledged_without_external_process_claim';
    $checks['server_fence'] = 'all_six_mutation_scopes_require_current_live_worker';

    $id = $enqueue('next');
    $claim = qs_call('agent/request/claim', ['request_id' => $id, 'agent' => 'codex'], $headers)->get_data();
    qs_assert($claim['status'] === 'claimed', 'An explicit different request must obtain a new binding.');
    $lease = $claim['lease_token'];
    qs_assert(qs_call('fixture-stop-write', [], $headers + ['X-LCFA-Request-ID' => $id, 'X-LCFA-Worker-Lease' => $lease])->get_status() === 200, 'New worker binding did not restore scoped writes.');
    $complete = ['request_id' => $id, 'lease_token' => $lease, 'result' => ['ok' => true, 'mode' => 'preview', 'message' => 'Fixture finished without site edits.']];
    qs_assert(qs_call('agent/request/complete', $complete, $headers)->get_status() === 200, 'Valid new request completion failed.');
    wp_set_current_user($owner);
    qs_assert(qs_call('chat/thread', ['operation' => 'clear', 'thread_id' => $thread_id])->get_status() === 200, 'Clearing terminal work should succeed.');
    $cleared = LCFA_Settings::get_thread($thread_id)['messages'];
    wp_set_current_user(0);
    qs_assert(qs_call('agent/request/complete', $complete, $headers)->get_status() === 409, 'Late delivery must not repopulate a cleared conversation.');
    wp_set_current_user($owner);
    qs_assert(LCFA_Settings::get_thread($thread_id)['messages'] === $cleared, 'Late completion changed cleared messages.');
    qs_assert(qs_call('chat/thread', ['operation' => 'delete', 'thread_id' => $thread_id])->get_status() === 200, 'Archiving terminal work should succeed.');
    try { $enqueue('archived'); throw new RuntimeException('Archived conversation accepted new work.'); }
    catch (RuntimeException $error) { qs_assert(str_starts_with($error->getMessage(), 'private_record_not_found:'), 'Wrong archived enqueue result.'); }
    $checks['conversation_lifecycle'] = 'active_work_visible_archived_enqueue_and_late_delivery_rejected';
} finally {
    wp_set_current_user($owner);
    foreach ($ids as $id) $wpdb->delete($table, ['owner_user_id' => $owner, 'site_hash' => $scope, 'id' => $id]);
    LCFA_Session_Store::mutate(static function ($sessions) use ($session_id) { unset($sessions[$session_id]); return $sessions; });
}
qs_assert(LCFA_Session_Store::read() === $sessions_before, 'Existing sessions changed.');
qs_assert($posts_before === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Site content changed.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'version' => LCFA_VERSION, 'checks' => $checks, 'existing_sessions_and_content' => 'unchanged', 'actual_coding_agent' => 'not_invoked']) . "\n";
