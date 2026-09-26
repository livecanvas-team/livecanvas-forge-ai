<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/lcfa-tests/');
function wp_salt($type) { return 'fixture-salt-not-a-secret'; }
require dirname(__DIR__, 2) . '/includes/class-lcfa-changesets.php';
function changeset_invoke($method, ...$args) { return (new ReflectionMethod(LCFA_Changesets::class, $method))->invoke(null, ...$args); }
function changeset_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$snapshot = ['post' => ['post_content' => 'private editorial bytes'], 'meta' => [], 'terms' => []];
$record = ['id' => '00000000-0000-4000-8000-000000000000', 'owner_user_id' => 2, 'site_hash' => 'site-a', 'scope_hash' => 'scope-a', 'before_hash' => changeset_invoke('hash', $snapshot)];
$record['before_snapshot'] = changeset_invoke('seal', json_encode($snapshot), $record);
changeset_assert(strpos($record['before_snapshot'], 'private editorial bytes') === false, 'Snapshots must not store plaintext.');
changeset_assert(changeset_invoke('open_snapshot', $record) === $snapshot, 'Encrypted snapshot must round-trip byte-for-byte.');
foreach (['owner_user_id', 'site_hash', 'scope_hash', 'id', 'before_hash', 'before_snapshot'] as $field) {
    $bad = $record;
    $bad[$field] = $field === 'owner_user_id' ? 3 : 'tampered';
    $rejected = false;
    try { changeset_invoke('open_snapshot', $bad); } catch (Throwable $e) { $rejected = true; }
    changeset_assert($rejected, 'Tampering must fail: ' . $field);
}
foreach (['COMMIT', 'ROLLBACK', 'CREATE TABLE example(id int)', 'SET SESSION autocommit=1', 'SET @@session.autocommit=1', 'SAVEPOINT unsafe', '/* hook */ ALTER TABLE example ADD id int', "-- hook\nCOMMIT", "# hook\nROLLBACK", '/*!80000 COMMIT */'] as $sql) {
    $rejected = false;
    try { LCFA_Changesets::guard_transaction($sql); } catch (RuntimeException $e) { $rejected = true; }
    changeset_assert($rejected, 'Save hooks must not commit or escape the content transaction.');
}
changeset_assert(LCFA_Changesets::guard_transaction('UPDATE posts SET post_content=\'COMMIT\' WHERE ID=1') !== '', 'Ordinary data containing a SQL keyword must remain valid.');
$summary = LCFA_Changesets::summary($record + ['action' => 'update_page', 'target_id' => '4', 'status' => 'saved', 'created_at' => 'now', 'updated_at' => 'now']);
changeset_assert(!isset($summary['before_snapshot'], $summary['before_hash'], $summary['owner_user_id']), 'Public summaries must exclude private payloads.');
changeset_assert($summary['undo_available'] === true && $summary['target_id'] === 4, 'Summary must report the actual saved target.');
echo "PASS changeset_crypto_phase1\n";
