<?php
/** Isolated private-record fixtures. No external coding agent or provider is invoked. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || !class_exists('LCFA_Private_Chat')) throw new RuntimeException('Site or build mismatch.');
function pc_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
function pc_call($path, array $payload, $method = 'POST') {
    $request = new WP_REST_Request($method, '/lcfa/v1/' . $path);
    if ($method === 'GET') $request->set_query_params($payload);
    else { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload)); }
    return rest_do_request($request);
}
function pc_workers(array $input): array {
    $workers = []; $input['start'] = microtime(true) + 1.5;
    for ($worker = 0; $worker < 2; $worker++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get('mysqli.default_socket'), __DIR__ . '/private-chat-worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        pc_assert(is_resource($process), 'Worker process unavailable.');
        fwrite($pipes[0], wp_json_encode($input + ['worker' => $worker])); fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        pc_assert(proc_close($process) === 0, 'Fixture worker failed: ' . $error);
        $results[] = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
    }
    return $results;
}
global $wpdb;
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
pc_assert(!empty($admins), 'Existing administrator required.');
$owner = (int) $admins[0]; wp_set_current_user($owner);
$prefix = 'private-fixture-' . bin2hex(random_bytes(8));
$table = $wpdb->prefix . 'lcfa_private_records';
$scope = hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH));
$ids = []; $checks = [];
$legacy = [get_option(LCFA_Settings::THREADS_OPTION_KEY), get_option(LCFA_Settings::AGENT_REQUESTS_OPTION_KEY)];
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
try {
    $thread = LCFA_Settings::create_thread('Private Bridge fixture'); $thread_id = $thread['id']; $ids[] = $thread_id;
    LCFA_Settings::append_thread_message($thread_id, ['id' => 'private-text', 'role' => 'user', 'content' => $prefix]);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE owner_user_id=%d AND site_hash=%s AND kind='thread' AND id=%s", $owner, $scope, $thread_id), ARRAY_A);
    pc_assert(strpos($row['payload'], $prefix) === false, 'Private chat content must be encrypted at rest.');
    pc_assert(!isset(LCFA_Settings::get_thread($thread_id)['_revision']), 'Internal storage metadata must not reach chat clients.');
    $wpdb->update($table, ['owner_user_id' => $owner + 1000000], ['owner_user_id' => $owner, 'site_hash' => $scope, 'kind' => 'thread', 'id' => $thread_id]);
    try {
        pc_assert(LCFA_Settings::get_thread($thread_id) === [], 'A record owned by another user must not be readable.');
        $rename = pc_call('chat/thread', ['operation' => 'rename', 'thread_id' => $thread_id, 'title' => 'Forbidden rename']);
        pc_assert($rename->get_status() === 409, 'Another owner must not rename a conversation.');
    } finally { $wpdb->update($table, ['owner_user_id' => $owner], ['owner_user_id' => $owner + 1000000, 'site_hash' => $scope, 'kind' => 'thread', 'id' => $thread_id]); }
    $checks['owner_isolation'] = 'foreign_record_read_and_rename_rejected';
    $workers = pc_workers(['mode' => 'append', 'owner' => $owner, 'thread_id' => $thread_id, 'fixture_marker' => $prefix]);
    $messages = LCFA_Settings::get_thread($thread_id)['messages'];
    pc_assert(count(array_filter($messages, static fn($message) => str_starts_with($message['id'], 'concurrent-'))) === 24, 'Concurrent append must preserve every message.');
    $checks['concurrent_messages'] = '24_messages_preserved';

    $input = ['thread_id' => $thread_id, 'agent' => 'opencode', 'user_prompt' => 'Read-only local fixture prompt', 'idempotency_key' => $prefix . '-request'];
    $request = LCFA_Settings::enqueue_agent_request($input); $request_id = $request['id']; $ids[] = $request_id;
    $repeated = LCFA_Settings::enqueue_agent_request($input);
    pc_assert($repeated['id'] === $request_id, 'A duplicate enqueue must return the same request.');
    $messages = LCFA_Settings::get_thread($thread_id)['messages'];
    pc_assert(count(array_filter($messages, static fn($message) => $message['id'] === 'prompt-' . $request_id)) === 1, 'A retried enqueue must not duplicate the user message.');
    try { LCFA_Settings::enqueue_agent_request(array_replace($input, ['user_prompt' => 'Different prompt'])); throw new RuntimeException('Idempotency conflict was not rejected.'); }
    catch (RuntimeException $error) { pc_assert(str_starts_with($error->getMessage(), 'private_idempotency_conflict:'), $error->getMessage()); }
    pc_assert(pc_call('agent/request', ['claim' => '1', 'request_id' => $request_id], 'GET')->get_status() === 405, 'GET must not claim work.');
    pc_assert(LCFA_Settings::get_agent_request($request_id)['status'] === 'queued', 'Rejected GET must leave work queued.');
    $workers = pc_workers(['mode' => 'claim', 'owner' => $owner, 'thread_id' => $thread_id, 'request_id' => $request_id, 'fixture_marker' => $prefix]);
    pc_assert(count(array_filter($workers, static fn($worker) => $worker['claimed'])) === 1, 'Exactly one concurrent worker must claim the request.');
    pc_assert(!isset(LCFA_Settings::get_agent_request($request_id)['lease_hash']), 'Read responses must exclude worker capabilities.');
    $checks['idempotency_and_atomic_claim'] = 'one_prompt_one_worker';
    $winner = array_values(array_filter($workers, static fn($worker) => $worker['claimed']))[0];
    LCFA_Private_Chat::cancel($request_id);
    LCFA_Private_Chat::acknowledge_stop($request_id, $winner['lease_token']);

    $request = LCFA_Settings::enqueue_agent_request(array_replace($input, ['idempotency_key' => $prefix . '-complete'])); $request_id = $request['id']; $ids[] = $request_id;
    $claim = pc_call('agent/request/claim', ['request_id' => $request_id, 'agent' => 'opencode'])->get_data();
    pc_assert($claim['status'] === 'claimed' && !empty($claim['lease_token']), 'POST claim must return the capability only to the worker.');
    $token = $claim['lease_token'];
    pc_assert(strpos(wp_json_encode(pc_call('agent/request', ['request_id' => $request_id], 'GET')->get_data()), $token) === false, 'Polling must never expose the worker capability.');
    $result = ['ok' => true, 'message' => 'Fixture inspection completed without writing site content.', 'mode' => 'preview'];
    $before_messages = count(LCFA_Settings::get_thread($thread_id)['messages']);
    $rejected = pc_call('agent/request/complete', ['request_id' => $request_id, 'result' => $result, 'lease_token' => 'wrong-token']);
    pc_assert($rejected->get_status() === 409 && count(LCFA_Settings::get_thread($thread_id)['messages']) === $before_messages, 'An invalid completion must not append a result.');
    pc_assert(pc_call('agent/request/renew', ['request_id' => $request_id, 'lease_token' => $token])->get_status() === 200, 'Current lease must renew.');
    $complete = ['request_id' => $request_id, 'lease_token' => $token, 'result' => $result];
    pc_assert(pc_call('agent/request/complete', $complete)->get_status() === 200, 'Valid completion failed.');
    pc_assert(pc_call('agent/request/complete', $complete)->get_status() === 200, 'Completion retry must be idempotent.');
    pc_assert(count(LCFA_Settings::get_thread($thread_id)['messages']) === $before_messages + 1, 'Completion retry must not duplicate the result.');
    pc_assert(pc_call('agent/request/fail', ['request_id' => $request_id, 'lease_token' => $token, 'message' => 'Conflicting result'])->get_status() === 409, 'Completed request cannot change terminal state.');
    $checks['worker_completion'] = 'lease_required_idempotent_result';

    $request = LCFA_Settings::enqueue_agent_request(array_replace($input, ['idempotency_key' => $prefix . '-expire'])); $request_id = $request['id']; $ids[] = $request_id;
    $claim = LCFA_Private_Chat::claim($request_id, 'opencode');
    LCFA_Private_Store::mutate('request', $request_id, static function ($record) { $record['lease_expires'] = time() - 1; return $record; });
    pc_assert(LCFA_Settings::get_agent_request($request_id)['status'] === 'needs_attention', 'Lost workers must be visible as needs_attention.');
    pc_assert(LCFA_Private_Chat::claim($request_id, 'opencode') === null, 'Expired work must not be replayed automatically.');
    pc_assert(pc_call('agent/request/complete', ['request_id' => $request_id, 'lease_token' => $claim['lease_token'], 'result' => $result])->get_status() === 409, 'Expired workers must not complete a request.');
    $checks['expired_worker'] = 'attention_required_no_automatic_replay';
    wp_set_current_user(0);
    pc_assert(LCFA_Settings::get_threads() === [] && LCFA_Settings::get_agent_requests() === [], 'Anonymous users must not see private records.');
    wp_set_current_user($owner);
    pc_assert($legacy === [get_option(LCFA_Settings::THREADS_OPTION_KEY), get_option(LCFA_Settings::AGENT_REQUESTS_OPTION_KEY)], 'Legacy global data must remain unchanged and unassigned.');
    pc_assert($posts === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Queue tests must not change any site content.');
    echo wp_json_encode(['ok' => true, 'site' => $host, 'checks' => $checks, 'legacy_data' => 'unchanged_unassigned', 'site_content' => 'unchanged', 'actual_coding_agent' => 'not_invoked']) . "\n";
} finally {
    wp_set_current_user($owner);
    foreach ($ids as $id) $wpdb->delete($table, ['owner_user_id' => $owner, 'site_hash' => $scope, 'id' => $id]);
}
