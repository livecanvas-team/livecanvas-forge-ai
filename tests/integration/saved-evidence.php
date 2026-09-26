<?php
/** Real WordPress / MCP request fixtures. No coding agent or provider call. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || !method_exists('LCFA_Changesets', 'current_saved_target')) throw new RuntimeException('Site or build mismatch.');
function se_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function se_call($route, array $payload, array $headers = []): array {
    $request = new WP_REST_Request('POST', '/lcfa/v1/' . $route);
    $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload));
    foreach ($headers as $key => $value) $request->set_header($key, $value);
    $response = rest_do_request($request);
    se_assert($response->get_status() === 200, 'REST fixture failed: ' . wp_json_encode($response->get_data()));
    return $response->get_data();
}
global $wpdb;
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
se_assert($owner > 0, 'Existing administrator required.');
$session_id = 'evidence-fixture-' . bin2hex(random_bytes(8)); $token = 'lcfa_sess_' . bin2hex(random_bytes(40));
$sessions_before = LCFA_Session_Store::read();
$posts_before = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$meta_before = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A);
$private_table = $wpdb->prefix . 'lcfa_private_records'; $journal = $wpdb->prefix . 'lcfa_changesets';
$scope = hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH));
$private_ids = []; $post_id = 0;
$headers = ['X-LCFA-MCP-Session' => $token, 'X-LCFA-MCP-Package-Version' => LCFA_MCP_PACKAGE_VERSION];
try {
    $session = ['session_id' => $session_id, 'client' => 'codex', 'owner_user_id' => $owner, 'scopes' => LCFA_Agent_Registry::full_access_scopes(), 'access_profile' => 'full',
        'site_fingerprint' => LCFA_Settings::get_site_fingerprint(), 'token_hash' => hash_hmac('sha256', $token, wp_salt('auth')), 'expires_at' => gmdate('c', time() + 600), 'revoked_at' => ''];
    LCFA_Session_Store::mutate(static function ($sessions) use ($session_id, $session) { $sessions[$session_id] = $session; return $sessions; });
    wp_set_current_user($owner);
    $context = LCFA_Write_Contract::prepare(['target_type' => 'new_page']);
    $created = se_call('command', ['action' => 'create_page', 'status' => 'draft', 'title' => 'Bridge evidence fixture', 'body_html' => '<section><h1>About the fixture</h1></section>', 'write_context' => $context['write_context']])['result'];
    $post_id = (int) ($created['target_id'] ?? 0);
    se_assert(!empty($created['ok']) && $post_id > 0, 'LiveCanvas draft fixture creation failed.');
    $thread = LCFA_Settings::create_thread('Save evidence fixture'); $private_ids[] = $thread['id'];
    $claim = static function ($suffix) use ($thread, $post_id, $session_id, $owner, $headers, &$private_ids): array {
        wp_set_current_user($owner);
        $record = LCFA_Settings::enqueue_agent_request(['thread_id' => $thread['id'], 'agent' => 'codex', 'target_id' => $post_id,
            'user_prompt' => 'Local test fixture only', 'server_saved_targets' => [$post_id], 'saved_changesets' => ['spoof'], 'idempotency_key' => $session_id . $suffix]);
        $private_ids[] = $record['id'];
        se_assert($record['server_saved_targets'] === [], 'Enqueue payload forged evidence.');
        wp_set_current_user(0);
        $claimed = se_call('agent/request/claim', ['request_id' => $record['id'], 'agent' => 'codex'], $headers);
        return ['request_id' => $record['id'], 'lease_token' => $claimed['lease_token']];
    };
    $spoof = $claim('-spoof');
    $reported = ['ok' => true, 'mode' => 'apply', 'action' => 'update_page', 'target_id' => $post_id, 'verification_states' => ['saved' => true], 'server_saved_targets' => [$post_id], 'saved_changesets' => ['spoof']];
    $complete = se_call('agent/request/complete', $spoof + ['result' => $reported, 'server_saved_targets' => [$post_id], 'saved_changesets' => ['spoof']], $headers);
    se_assert($complete['request']['server_saved_targets'] === [], 'Completion payload forged server evidence.');

    $work = $claim('-real');
    $write_headers = $headers + ['X-LCFA-Request-ID' => $work['request_id'], 'X-LCFA-Worker-Lease' => $work['lease_token']];
    // Establish the actual MCP identity first, then inspect under that identity.
    se_call('agent/request/renew', $work, $headers);
    $context = LCFA_Write_Contract::prepare(['target_id' => $post_id]);
    $write = se_call('command', ['action' => 'update_page', 'target_id' => $post_id, 'body_html' => '<section><h1>Saved fixture</h1></section>', 'write_context' => $context['write_context']], $write_headers)['result'];
    se_assert(!empty($write['ok']) && !empty($write['changeset']['id']), 'Journal write failed: ' . wp_json_encode($write));
    $change = $write['changeset']['id'];
    se_assert(LCFA_Changesets::current_saved_target($change) === $post_id, 'Current journal did not verify the target.');
    $complete = se_call('agent/request/complete', $work + ['result' => $reported], $headers);
    se_assert($complete['request']['server_saved_targets'] === [$post_id], 'Committed worker write lost its server evidence.');
    se_assert(!isset($complete['request']['saved_changesets']), 'Internal journal binding leaked.');
    wp_set_current_user($owner);
    update_post_meta($post_id, '_lcfa_evidence_fixture_conflict', 'new local edit');
    se_assert(LCFA_Private_Chat::request($work['request_id'])['server_saved_targets'] === [], 'Later edits must invalidate old evidence.');
    delete_post_meta($post_id, '_lcfa_evidence_fixture_conflict');
    se_assert(LCFA_Private_Chat::request($work['request_id'])['server_saved_targets'] === [$post_id], 'Restored exact bytes must match the journal.');
    $context = LCFA_Write_Contract::prepare(['target_id' => $post_id]);
    $undo = LCFA_Changesets::undo(['changeset_id' => $change, 'target_id' => $post_id, 'write_context' => $context['write_context']]);
    se_assert(!empty($undo['ok']) && LCFA_Private_Chat::request($work['request_id'])['server_saved_targets'] === [], 'Undone writes must not authorize a reload.');
} finally {
    wp_set_current_user($owner);
    if (is_int($post_id) && $post_id > 0) { wp_delete_post($post_id, true); $wpdb->delete($journal, ['target_id' => $post_id, 'owner_user_id' => $owner]); }
    foreach ($private_ids as $id) $wpdb->delete($private_table, ['owner_user_id' => $owner, 'site_hash' => $scope, 'id' => $id]);
    LCFA_Session_Store::mutate(static function ($sessions) use ($session_id) { unset($sessions[$session_id]); return $sessions; });
}
se_assert(LCFA_Session_Store::read() === $sessions_before, 'Existing sessions changed.');
se_assert($posts_before === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Existing content changed.');
se_assert($meta_before === $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A), 'Existing metadata changed.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'version' => LCFA_VERSION, 'forged_evidence' => 'rejected', 'committed_write' => 'verified', 'later_edit_and_undo' => 'invalidate_evidence', 'existing_content_meta_sessions' => 'unchanged', 'actual_coding_agent' => 'not_invoked']) . "\n";
