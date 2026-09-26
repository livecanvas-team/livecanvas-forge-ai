<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/lcfa-delivery-unit/');
final class LCFA_Private_Store {
    public static array $rows = [];
    public static bool $fail = false;
    public static int $owner = 7;
    public static function owner(): int { return self::$owner; }
    public static function locked($key, $fn) { return $fn(); }
    public static function read($kind, $id) { return self::$rows[$kind][$id] ?? null; }
    public static function listing($kind, $state, $limit) { return array_filter(self::$rows[$kind] ?? [], static fn($r) => $r['_state'] === $state); }
    public static function insert($kind, $id, $row, $state) {
        if (self::$fail) throw new RuntimeException('fixture_storage_failed');
        if (isset(self::$rows[$kind][$id])) return false;
        self::$rows[$kind][$id] = $row + ['_state' => $state]; return true;
    }
    public static function mutate($kind, $id, $fn) {
        if (!isset(self::$rows[$kind][$id])) return null;
        $row = $fn(self::$rows[$kind][$id]);
        if (self::$fail) throw new RuntimeException('fixture_storage_failed');
        return self::$rows[$kind][$id] = $row;
    }
}
final class LCFA_MCP_Session_Manager {
    public static array $identity = ['session_id' => 'fixture-session', 'client' => 'codex'];
    public static bool $full = true;
    public static function current_worker_identity() { return self::$identity; }
    public static function current_owner_user_id() { return self::$identity ? 7 : 0; }
    public static function has_full_access_context() { return self::$full; }
}
require dirname(__DIR__, 2) . '/includes/class-lcfa-desktop-delivery.php';
function dd_assert($value, $message) { if (!$value) throw new RuntimeException($message); }
function dd_reject(callable $call, string $code) {
    try { $call(); } catch (RuntimeException $error) { dd_assert($error->getMessage() === $code, 'Wrong rejection: ' . $error->getMessage()); return; }
    throw new RuntimeException('Expected ' . $code);
}
function dd_request($id) { LCFA_Private_Store::$rows['request'][$id] = ['id' => $id, 'agent' => 'codex', 'thread_id' => 'default', '_state' => 'queued']; }
$hash = hash('sha256', 'synthetic command'); dd_request('request-one'); dd_request('request-two');
$first = LCFA_Desktop_Delivery::reserve('request-one', 'desktop-one', $hash);
dd_assert($first['send_allowed'] && strlen($first['receipt_token']) === 64, 'First reservation must have one private receipt.');
dd_assert(!$first['desktop_conversation_verified'] && !$first['site_changes_verified'], 'A receipt must not claim identity or saved content.');
$again = LCFA_Desktop_Delivery::reserve('request-one', 'desktop-one', $hash);
dd_assert(!$again['send_allowed'] && !isset($again['receipt_token']), 'Retrying a lost reservation response cannot grant another send.');
dd_reject(static fn() => LCFA_Desktop_Delivery::reserve('request-two', 'desktop-one', $hash), 'delivery_review_required');
dd_reject(static fn() => LCFA_Desktop_Delivery::reserve('request-one', 'desktop-other', $hash), 'delivery_binding_conflict');
dd_reject(static fn() => LCFA_Desktop_Delivery::reserve('request-one', 'desktop-one', hash('sha256', 'changed')), 'delivery_binding_conflict');
dd_assert(LCFA_Desktop_Delivery::pending('desktop-one')['receipt_token'] === $first['receipt_token'], 'Same session must recover its private receipt without resending.');
dd_reject(static fn() => LCFA_Desktop_Delivery::record('request-one', 'desktop-one', str_repeat('0', 64), 'turn-one', 'inProgress'), 'delivery_receipt_invalid');
$ack = LCFA_Desktop_Delivery::record('request-one', 'desktop-one', $first['receipt_token'], 'turn-one', 'inProgress');
dd_assert($ack['state'] === 'acknowledged' && !isset($ack['receipt_token']), 'Acknowledgement must exclude the receipt.');
dd_reject(static fn() => LCFA_Desktop_Delivery::record('request-one', 'desktop-one', $first['receipt_token'], 'turn-other', 'completed'), 'delivery_turn_conflict');
$done = LCFA_Desktop_Delivery::record('request-one', 'desktop-one', $first['receipt_token'], 'turn-one', 'interrupted');
dd_assert($done['state'] === 'settled' && LCFA_Desktop_Delivery::pending('desktop-one') === null, 'Terminal receipt should settle the delivery journal only.');
dd_assert(LCFA_Private_Store::$rows['request']['request-one']['_state'] === 'queued', 'Delivery must not claim the MCP worker or complete its request.');
LCFA_Desktop_Delivery::record('request-one', 'desktop-one', $first['receipt_token'], 'turn-one', 'interrupted');
dd_reject(static fn() => LCFA_Desktop_Delivery::record('request-one', 'desktop-one', $first['receipt_token'], 'turn-one', 'completed'), 'delivery_terminal_conflict');
dd_assert(!LCFA_Desktop_Delivery::reserve('request-one', 'desktop-one', $hash)['send_allowed'], 'Terminal delivery must never become resendable.');
LCFA_MCP_Session_Manager::$identity['session_id'] = 'different-session';
dd_reject(static fn() => LCFA_Desktop_Delivery::reserve('request-one', 'desktop-one', $hash), 'delivery_binding_conflict');
LCFA_MCP_Session_Manager::$identity = [];
dd_reject(static fn() => LCFA_Desktop_Delivery::pending('desktop-one'), 'delivery_owned_codex_session_required');
LCFA_MCP_Session_Manager::$identity = ['session_id' => 'fixture-session', 'client' => 'opencode'];
dd_reject(static fn() => LCFA_Desktop_Delivery::pending('desktop-one'), 'delivery_owned_codex_session_required');
LCFA_MCP_Session_Manager::$identity['client'] = 'codex'; LCFA_MCP_Session_Manager::$full = false;
dd_reject(static fn() => LCFA_Desktop_Delivery::pending('desktop-one'), 'delivery_owned_codex_session_required');
LCFA_MCP_Session_Manager::$full = true; LCFA_Private_Store::$fail = true;
dd_reject(static fn() => LCFA_Desktop_Delivery::reserve('request-two', 'desktop-one', $hash), 'fixture_storage_failed');
LCFA_Private_Store::$fail = false;
dd_assert(LCFA_Desktop_Delivery::pending('desktop-one') === null, 'Failed snapshot must not grant or retain an unverified receipt.');
foreach (['desktop\n', "desktop\n", 'desktop/foreign'] as $id) dd_reject(static fn() => LCFA_Desktop_Delivery::pending($id), 'delivery_input_invalid');
echo "PASS desktop delivery owner/session scope, one-send reservation, recovery, terminal monotonicity and failure handling\n";
