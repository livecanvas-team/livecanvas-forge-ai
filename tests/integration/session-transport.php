<?php
/** Temporary owned fixture sessions only. No provider or external agent calls. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || !class_exists('LCFA_Session_Store')) throw new RuntimeException('Site or build mismatch.');
function st_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function st_request(array $headers = [], array $query = []) {
    $request = new WP_REST_Request('GET', '/lcfa/v1/mcp/transport-identity');
    foreach ($headers as $key => $value) $request->set_header($key, $value);
    $request->set_query_params($query);
    return rest_do_request($request);
}
global $wpdb;
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
st_assert(!empty($admins), 'Existing administrator required.');
$owner = (int) $admins[0];
$prefix = 'transport-fixture-' . bin2hex(random_bytes(8));
$token = 'lcfa_sess_' . bin2hex(random_bytes(40));
$session = ['session_id' => $prefix, 'token_hash' => hash_hmac('sha256', $token, wp_salt('auth')),
    'owner_user_id' => $owner, 'client' => 'codex', 'site_fingerprint' => LCFA_Settings::get_site_fingerprint(),
    'scopes' => LCFA_Agent_Registry::full_access_scopes(), 'access_profile' => 'full',
    'expires_at' => gmdate('c', time() + 300), 'revoked_at' => ''];
$baseline = LCFA_Session_Store::read();
$posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$checks = []; $hook = null;
try {
    LCFA_Session_Store::mutate(static function ($sessions) use ($prefix, $session) { $sessions[$prefix] = $session; return $sessions; });
    wp_set_current_user(0);
    st_assert(st_request()->get_status() === 401, 'Anonymous identity access must fail.');
    st_assert(st_request(['Authorization' => 'Bearer ' . $token])->get_status() === 401, 'Bearer-only identity access must fail.');
    st_assert(st_request([], ['mcp_session' => $token])->get_status() === 401, 'URL tokens must not authorize identity access.');
    wp_set_current_user($owner);
    st_assert(st_request()->get_status() === 403, 'Administrator cookie alone must not bind a local listener.');
    wp_set_current_user(0);
    $headers = ['X-LCFA-MCP-Session' => $token, 'X-LCFA-MCP-Package-Version' => LCFA_MCP_PACKAGE_VERSION];
    $response = st_request($headers);
    st_assert($response->get_status() === 200, 'Owned approved identity access failed.');
    $identity = $response->get_data()['identity'];
    st_assert($identity['session_id'] === $prefix && $identity['owner_user_id'] === $owner && $identity['wordpress_root'] === realpath(ABSPATH), 'Wrong identity binding.');
    st_assert($identity['required_runtime'] === LCFA_MCP_PACKAGE_VERSION && !isset($identity['token_hash'], $identity['project_label']), 'Identity must expose runtime but no token or project label.');
    st_assert(strpos(wp_json_encode($response->get_data()), $token) === false, 'Identity leaked a credential.');
    $checks['identity'] = 'owned_header_only_no_credentials';
    foreach (['ownerless', 'expired', 'missing_scope'] as $invalid) {
        LCFA_Session_Store::mutate(static function ($sessions) use ($prefix, $session, $invalid) {
            $sessions[$prefix] = $session;
            if ($invalid === 'ownerless') unset($sessions[$prefix]['owner_user_id']);
            if ($invalid === 'expired') $sessions[$prefix]['expires_at'] = gmdate('c', time() - 1);
            if ($invalid === 'missing_scope') $sessions[$prefix]['scopes'] = ['media'];
            return $sessions;
        });
        st_assert(st_request($headers)->get_status() >= 400, 'Invalid session accepted: ' . $invalid);
    }
    LCFA_Session_Store::mutate(static function ($sessions) use ($prefix, $session) { $sessions[$prefix] = $session; return $sessions; });
    wp_cache_set('lcfa_mcp_sessions', [], 'options');
    st_assert(isset(LCFA_Session_Store::read()[$prefix]), 'Authorization must bypass a stale object cache.');
    wp_cache_delete('lcfa_mcp_sessions', 'options');

    // Insert a revocation between auth read and the last-seen CAS. The stale
    // update must retry against the revocation, never overwrite it.
    $hook = static function ($sql) use (&$hook, $wpdb, $prefix) {
        if (str_starts_with($sql, "UPDATE {$wpdb->options} SET option_value=") && str_contains($sql, ' AND BINARY option_value=')) {
            remove_filter('query', $hook);
            LCFA_Session_Store::mutate(static function ($sessions) use ($prefix) {
                $sessions[$prefix]['revoked_at'] = gmdate('c');
                $sessions[$prefix . '-during-revoke'] = ['revoked_at' => gmdate('c')];
                return $sessions;
            });
        }
        return $sql;
    };
    add_filter('query', $hook);
    st_assert(LCFA_MCP_Session_Manager::validate_session_token($token) === false, 'Concurrent revocation must reject stale validation.');
    remove_filter('query', $hook); $hook = null;
    $after = LCFA_Session_Store::read();
    st_assert(!empty($after[$prefix]['revoked_at']) && isset($after[$prefix . '-during-revoke']), 'Stale telemetry lost revocation or new session.');
    st_assert(st_request($headers)->get_status() >= 400 && !LCFA_MCP_Session_Manager::has_full_access_context(), 'Revoked session must not retain full access.');
    $checks['revocation_race'] = 'stale_touch_rejected_revocation_and_insert_preserved';

    $workers = []; $start = microtime(true) + 1.5;
    for ($worker = 0; $worker < 2; $worker++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-d', 'mysqli.default_socket=' . ini_get('mysqli.default_socket'), __DIR__ . '/session-store-worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        st_assert(is_resource($process), 'Worker unavailable.');
        fwrite($pipes[0], wp_json_encode(compact('prefix', 'worker', 'start'))); fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        st_assert(proc_close($process) === 0 && (json_decode($out, true)['inserted'] ?? 0) === 6, 'Concurrent worker failed: ' . $err);
    }
    $after = LCFA_Session_Store::read();
    for ($worker = 0; $worker < 2; $worker++) for ($i = 0; $i < 6; $i++) st_assert(isset($after[$prefix . '-' . $worker . '-' . $i]), 'A concurrent insertion was lost.');
    $checks['concurrent_inserts'] = '12_preserved';

    $hook = static function ($sql) use ($wpdb) {
        return str_starts_with($sql, "UPDATE {$wpdb->options} SET option_value=") && str_contains($sql, ' AND BINARY option_value=') ? 'SELECT LCFA_EXPECTED_FIXTURE_DATABASE_FAILURE()' : $sql;
    };
    add_filter('query', $hook); $old_errors = $wpdb->suppress_errors(true);
    try {
        LCFA_Session_Store::mutate(static function ($sessions) use ($prefix) { $sessions[$prefix]['last_seen_at'] = 'must-not-save'; return $sessions; });
        throw new RuntimeException('Failed storage was reported as saved.');
    } catch (RuntimeException $error) { st_assert($error->getMessage() === 'session_storage_unavailable', 'Wrong storage failure result.'); }
    finally { remove_filter('query', $hook); $hook = null; $wpdb->suppress_errors($old_errors); }
    st_assert((LCFA_Session_Store::read()[$prefix]['last_seen_at'] ?? '') !== 'must-not-save', 'Failed database write changed the session.');
    $checks['database_failure'] = 'fail_closed';
} finally {
    if ($hook) remove_filter('query', $hook);
    LCFA_Session_Store::mutate(static function ($sessions) use ($prefix) {
        foreach (array_keys($sessions) as $id) if ($id === $prefix || str_starts_with($id, $prefix . '-')) unset($sessions[$id]);
        return $sessions;
    });
    wp_cache_delete('lcfa_mcp_sessions', 'options');
}
st_assert(LCFA_Session_Store::read() === $baseline, 'Existing sessions changed during fixture testing.');
st_assert($posts === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Transport tests changed site content.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'version' => LCFA_VERSION, 'checks' => $checks, 'existing_sessions' => 'unchanged', 'site_content' => 'unchanged', 'actual_coding_agent' => 'not_invoked']) . "\n";
