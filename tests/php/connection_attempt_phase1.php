<?php
declare(strict_types=1);

function get_current_user_id(): int { return $GLOBALS['attempt_test_user'] ?? 7; }
function user_can(int $user_id, string $capability): bool { return empty($GLOBALS['attempt_test_demoted']) && ($GLOBALS['attempt_missing_capability'] ?? '') !== $capability; }
require __DIR__ . '/mcp_session_manager_phase1.php';

function attempt_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$GLOBALS['lcfa_test_transients'] = [];
$attempt = LCFA_Connection_Attempt::create('claude-code', 7);
attempt_expect($attempt['state'] === 'waiting_for_client', 'Creation must not mark ready or authorize access.');
attempt_expect(LCFA_Connection_Attempt::public_status($attempt['id'], 8) === [], 'Another administrator must not see this attempt.');
$payload = ['client' => 'claude-code', 'site_fingerprint' => 'site-fp', 'scopes' => LCFA_Agent_Registry::full_access_scopes(), 'connection_attempt' => $attempt['id']];
$wrong = LCFA_MCP_Session_Manager::start_pairing(array_merge($payload, ['client' => 'cursor']));
attempt_expect($wrong instanceof WP_Error, 'Wrong client must not bind the attempt.');
$limited = LCFA_MCP_Session_Manager::start_pairing(array_merge($payload, ['scopes' => ['read']]));
attempt_expect($limited instanceof WP_Error, 'Full Access must require the exact consent scopes.');
$pairing = LCFA_MCP_Session_Manager::start_pairing($payload);
attempt_expect(strpos($pairing['verification_url'], 'connection_attempt=' . $attempt['id']) !== false, 'Verification URLs must open the same attempt across tabs and clients.');
attempt_expect(is_array($pairing) && $pairing['ok'], 'Matching pairing should bind.');
attempt_expect(LCFA_Connection_Attempt::get($attempt['id'])['state'] === 'authorization_required', 'A pairing alone is not authorization.');
$GLOBALS['attempt_test_user'] = 8;
attempt_expect(!LCFA_MCP_Session_Manager::approve_pairing($pairing['pairing_id'])['ok'], 'Another admin cannot approve this attempt.');
$GLOBALS['attempt_test_user'] = 7;
$GLOBALS['attempt_missing_capability'] = 'upload_files';
attempt_expect(!LCFA_MCP_Session_Manager::approve_pairing($pairing['pairing_id'])['ok'], 'A partial administrative role must receive a clear failure before a session is created.');
$GLOBALS['attempt_missing_capability'] = '';
$approved = LCFA_MCP_Session_Manager::approve_pairing($pairing['pairing_id']);
attempt_expect($approved['ok'], 'The initiating admin can approve.');
attempt_expect(!LCFA_MCP_Session_Manager::approve_pairing($pairing['pairing_id'])['ok'], 'Duplicate approvals must not create another session.');
$session = LCFA_MCP_Session_Manager::get_sessions()[$approved['session_id']];
$token = LCFA_MCP_Session_Manager::get_pairing_status($pairing['pairing_id'], $pairing['device_secret'])['session_token'];
LCFA_MCP_Session_Manager::validate_session_token($token, 'write');
attempt_expect(LCFA_MCP_Session_Manager::has_full_access_context(), 'Full Access is active only in the new authenticated session.');
$GLOBALS['attempt_test_demoted'] = true;
attempt_expect(!LCFA_MCP_Session_Manager::validate_session_token($token, 'write'), 'Removing the owner capability must remove session access.');
attempt_expect(!LCFA_MCP_Session_Manager::has_full_access_context(), 'A failed authentication must clear Full Access context.');
$GLOBALS['attempt_test_demoted'] = false;
attempt_expect(LCFA_Connection_Attempt::get($attempt['id'])['state'] === 'verifying', 'Approval is not verification.');
attempt_expect(!LCFA_Connection_Attempt::verify($attempt['id'], array_merge($session, ['client' => 'cursor']), LCFA_MCP_PACKAGE_VERSION), 'Wrong client must not complete.');
attempt_expect(!LCFA_Connection_Attempt::verify($attempt['id'], array_merge($session, ['session_id' => 'old-session']), LCFA_MCP_PACKAGE_VERSION), 'Old session must not complete.');
attempt_expect(!LCFA_Connection_Attempt::verify($attempt['id'], $session, '0.0.1'), 'Old runtime must not complete.');
attempt_expect(!LCFA_Connection_Attempt::verify($attempt['id'], array_merge($session, ['site_fingerprint' => 'other-site']), LCFA_MCP_PACKAGE_VERSION), 'Other site must not complete.');
$second = LCFA_Connection_Attempt::create('claude-code', 7);
attempt_expect(!LCFA_Connection_Attempt::verify($second['id'], $session, LCFA_MCP_PACKAGE_VERSION), 'Concurrent attempt must not complete.');
attempt_expect(LCFA_Connection_Attempt::verify($attempt['id'], $session, LCFA_MCP_PACKAGE_VERSION), 'Authenticated matching proof should complete.');
attempt_expect(LCFA_Connection_Attempt::public_status($attempt['id'], 7)['state'] === 'ready', 'Ready should be visible to the owner.');
$GLOBALS['attempt_missing_capability'] = 'upload_files';
attempt_expect(LCFA_Connection_Attempt::public_status($attempt['id'], 7)['state'] === 'reconnect_required', 'A demoted owner must not keep a green connection status.');
$GLOBALS['attempt_missing_capability'] = '';
LCFA_MCP_Session_Manager::revoke_session($approved['session_id']);
attempt_expect(LCFA_Connection_Attempt::public_status($attempt['id'], 7)['state'] === 'reconnect_required', 'Revocation must invalidate the visible success.');
$expired = LCFA_Connection_Attempt::get($second['id']);
$expired['expires_at'] = time() - 1;
set_transient('lcfa_connect_attempt_' . $second['id'], $expired);
attempt_expect(LCFA_Connection_Attempt::get($second['id']) === [], 'Expired attempts must not be reused.');
echo "PASS: attempt ownership, Full Access consent binding, correlated proof, expiry and revocation\n";
