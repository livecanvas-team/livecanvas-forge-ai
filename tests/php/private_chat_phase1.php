<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/lcfa-private-chat-fixture/');
$GLOBALS['private_fixture_user'] = 7;
function get_current_user_id() { return $GLOBALS['private_fixture_user']; }
function user_can($id, $capability) { return $id === 7; }
function wp_salt($type) { return 'fixture-only-private-storage-salt'; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
final class LCFA_MCP_Session_Manager {
    public static array $session = ['session_id' => 'fixture-session-a', 'client' => 'codex', 'owner' => 7];
    public static function current_worker_identity(): array { return self::$session; }
    public static function current_owner_user_id(): int { return (int) (self::$session['owner'] ?? 0); }
}
require dirname(__DIR__, 2) . '/includes/class-lcfa-agent-registry.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-private-store.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-private-chat.php';
function private_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function private_call($class, $method, ...$args) { return (new ReflectionMethod($class, $method))->invoke(null, ...$args); }
$identity = ['owner_user_id' => 7, 'site_hash' => 'site-a', 'kind' => 'thread', 'id' => 'fixture'];
$value = ['messages' => [['content' => 'Private fixture text']]];
$encrypted = private_call(LCFA_Private_Store::class, 'encode', $value, $identity);
$row = $identity + ['payload' => $encrypted, 'state' => 'active', 'revision' => 1];
private_assert(strpos($encrypted, 'Private fixture text') === false, 'Stored messages must be encrypted.');
private_assert(private_call(LCFA_Private_Store::class, 'decode', $row)['messages'] === $value['messages'], 'Private data must round trip.');
foreach (['owner_user_id', 'site_hash', 'kind', 'id', 'payload'] as $field) {
    $bad = $row; $bad[$field] = $field === 'owner_user_id' ? 8 : 'tampered';
    $rejected = false;
    try { private_call(LCFA_Private_Store::class, 'decode', $bad); } catch (Throwable $error) { $rejected = true; }
    private_assert($rejected, 'Changing private record identity or ciphertext must fail authentication.');
}
$record = ['agent' => 'codex', 'lease_hash' => hash('sha256', 'fixture-token'), 'worker_binding' => 'session:' . hash('sha256', 'fixture-session-a')];
private_call(LCFA_Private_Chat::class, 'check_lease', $record, 'fixture-token');
foreach ([
    ['session_id' => 'fixture-session-b', 'client' => 'codex', 'owner' => 7],
    ['session_id' => 'fixture-session-a', 'client' => 'opencode', 'owner' => 7],
    ['session_id' => 'fixture-session-a', 'client' => 'codex', 'owner' => 8],
    [],
] as $session) {
    LCFA_MCP_Session_Manager::$session = $session;
    $rejected = false;
    try { private_call(LCFA_Private_Chat::class, 'check_lease', $record, 'fixture-token'); } catch (Throwable $error) { $rejected = true; }
    private_assert($rejected, 'A different, revoked or mismatched session must not use the worker capability.');
}
$GLOBALS['private_fixture_user'] = 0;
private_assert(LCFA_Private_Store::owner() === 0, 'Anonymous requests cannot acquire private ownership.');
echo "PASS private chat encryption, identity binding, session/client/owner mismatch and revocation\n";
