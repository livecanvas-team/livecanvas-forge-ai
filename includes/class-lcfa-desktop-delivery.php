<?php

defined('ABSPATH') || exit;

/**
 * Private delivery journal for the future authenticated desktop controller.
 * Internal only: no REST, Ability or browser route exposes this service.
 * WordPress identity is verified here. Desktop identity must be verified by
 * the controller before using this service; these records are not attestation.
 */
final class LCFA_Desktop_Delivery {
    private const TERMINAL = ['completed', 'interrupted', 'failed'];

    /** A lost reservation response must NEVER result in a second send grant. */
    public static function reserve(string $request_id, string $desktop_thread_id, string $payload_hash): array {
        self::validate_input($request_id, $desktop_thread_id, $payload_hash);
        $session = self::session();
        return LCFA_Private_Store::locked('desktop-delivery:' . hash('sha256', $desktop_thread_id), static function () use ($request_id, $desktop_thread_id, $payload_hash, $session) {
            $id = self::record_id($request_id);
            $existing = LCFA_Private_Store::read('delivery', $id);
            if ($existing) {
                self::check_binding($existing, $request_id, $desktop_thread_id, $session, $payload_hash);
                return self::summary($existing) + ['send_allowed' => false];
            }
            $request = LCFA_Private_Store::read('request', $request_id);
            if (!$request || ($request['agent'] ?? '') !== 'codex' || ($request['_state'] ?? '') !== 'queued') self::fail('delivery_queued_request_required');
            self::check_conversation($request);
            if (self::pending_for_thread($desktop_thread_id)) self::fail('delivery_review_required');
            $token = bin2hex(random_bytes(32));
            $record = [
                'version' => 1, 'request_id' => $request_id, 'desktop_thread_id' => $desktop_thread_id,
                'wordpress_thread_id' => $request['thread_id'], 'message_epoch' => (int) ($request['message_epoch'] ?? 0),
                'session_hash' => $session, 'payload_hash' => $payload_hash,
                'receipt_token' => $token, 'turn_id' => null, 'turn_status' => null,
                'created_at' => gmdate('c'), 'updated_at' => gmdate('c'),
            ];
            if (self::session() !== $session) self::fail('delivery_session_changed');
            if (!LCFA_Private_Store::insert('delivery', $id, $record, 'reserved')) {
                // Another request may reserve the same WordPress request for a
                // different desktop thread. The primary key is request-scoped.
                $existing = LCFA_Private_Store::read('delivery', $id);
                if (!$existing) self::fail('delivery_storage_unavailable');
                self::check_binding($existing, $request_id, $desktop_thread_id, $session, $payload_hash);
                return self::summary($existing) + ['send_allowed' => false];
            }
            $saved = LCFA_Private_Store::read('delivery', $id);
            if (!$saved || $saved['_state'] !== 'reserved' || array_diff_key($record, $saved) ||
                array_intersect_key($saved, $record) !== $record) self::fail('delivery_storage_unverified');
            // Cancellation, archive and session revocation may race storage.
            // Keep the durable reservation on failure; never release it for retry.
            $current = LCFA_Private_Store::read('request', $request_id);
            if (!$current || $current['_state'] !== 'queued') self::fail('delivery_request_changed');
            self::check_conversation($current);
            if (self::session() !== $session) self::fail('delivery_session_changed');
            return self::summary($saved) + ['send_allowed' => true, 'receipt_token' => $token];
        });
    }

    /** Private controller recovery only. Returned receipts must never enter UI or logs. */
    public static function pending(string $desktop_thread_id): ?array {
        self::validate_desktop_id($desktop_thread_id);
        $session = self::session();
        $records = self::pending_for_thread($desktop_thread_id);
        if (count($records) > 1) self::fail('delivery_review_required');
        $record = $records ? reset($records) : null;
        if (!$record) return null;
        self::check_binding($record, $record['request_id'] ?? '', $desktop_thread_id, $session);
        if (self::session() !== $session) self::fail('delivery_session_changed');
        return self::summary($record) + ['send_allowed' => false, 'receipt_token' => $record['receipt_token']];
    }

    /** Stores protocol evidence only. It neither completes a worker nor saves page content. */
    public static function record(string $request_id, string $desktop_thread_id, string $receipt_token, string $turn_id, string $turn_status): array {
        self::validate_input($request_id, $desktop_thread_id, str_repeat('0', 64));
        self::validate_desktop_id($turn_id);
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $receipt_token) || !in_array($turn_status, array_merge(['inProgress'], self::TERMINAL), true)) self::fail('delivery_receipt_invalid');
        $session = self::session();
        $id = self::record_id($request_id);
        $updated = LCFA_Private_Store::mutate('delivery', $id, static function ($record) use ($request_id, $desktop_thread_id, $receipt_token, $turn_id, $turn_status, $session) {
            self::check_binding($record, $request_id, $desktop_thread_id, $session);
            if (!hash_equals($record['receipt_token'], $receipt_token)) self::fail('delivery_receipt_invalid');
            if ($record['turn_id'] !== null && $record['turn_id'] !== $turn_id) self::fail('delivery_turn_conflict');
            if ($record['_state'] === 'settled') {
                if ($record['turn_status'] !== $turn_status) self::fail('delivery_terminal_conflict');
                return $record;
            }
            if (self::session() !== $session) self::fail('delivery_session_changed');
            $record['turn_id'] = $turn_id; $record['turn_status'] = $turn_status;
            $record['_state'] = in_array($turn_status, self::TERMINAL, true) ? 'settled' : 'acknowledged';
            $record['updated_at'] = gmdate('c');
            return $record;
        });
        if (!$updated) self::fail('delivery_not_found');
        $saved = LCFA_Private_Store::read('delivery', $id);
        if (!$saved || $saved['turn_id'] !== $turn_id || $saved['turn_status'] !== $turn_status || $saved['_state'] !== $updated['_state']) self::fail('delivery_storage_unverified');
        if (self::session() !== $session) self::fail('delivery_session_changed');
        return self::summary($saved) + ['send_allowed' => false];
    }

    private static function session(): string {
        $identity = LCFA_MCP_Session_Manager::current_worker_identity();
        $owner = LCFA_MCP_Session_Manager::current_owner_user_id();
        if (!$identity || !$owner || $owner !== LCFA_Private_Store::owner() ||
            ($identity['client'] ?? '') !== 'codex' || !LCFA_MCP_Session_Manager::has_full_access_context()) self::fail('delivery_owned_codex_session_required');
        return hash('sha256', $identity['session_id']);
    }

    private static function pending_for_thread(string $desktop_thread_id): array {
        $matches = [];
        foreach (['reserved', 'acknowledged'] as $state) {
            $records = LCFA_Private_Store::listing('delivery', $state, 1000);
            if (count($records) >= 1000) self::fail('delivery_index_review_required');
            foreach ($records as $id => $record) if (($record['desktop_thread_id'] ?? '') === $desktop_thread_id) $matches[$id] = $record;
        }
        return $matches;
    }

    private static function check_binding(array $record, string $request_id, string $desktop_thread_id, string $session, string $payload_hash = ''): void {
        if (($record['version'] ?? null) !== 1 || !isset($record['receipt_token']) ||
            !preg_match('/\A[a-f0-9]{64}\z/D', $record['receipt_token']) ||
            !in_array($record['_state'] ?? '', ['reserved', 'acknowledged', 'settled'], true)) self::fail('delivery_record_damaged');
        if (($record['request_id'] ?? '') !== $request_id || ($record['desktop_thread_id'] ?? '') !== $desktop_thread_id ||
            ($record['session_hash'] ?? '') !== $session || ($payload_hash !== '' && ($record['payload_hash'] ?? '') !== $payload_hash)) self::fail('delivery_binding_conflict');
    }

    private static function check_conversation(array $request): void {
        $thread = LCFA_Private_Store::read('thread', $request['thread_id']);
        if ((!$thread && $request['thread_id'] !== 'default') || ($thread && ($thread['_state'] !== 'active' ||
            (int) ($thread['message_epoch'] ?? 0) !== (int) ($request['message_epoch'] ?? 0)))) self::fail('delivery_conversation_changed');
    }

    private static function summary(array $record): array {
        return ['request_id' => $record['request_id'], 'desktop_thread_id' => $record['desktop_thread_id'],
            'state' => $record['_state'], 'turn_id' => $record['turn_id'], 'turn_status' => $record['turn_status'],
            'desktop_conversation_verified' => false, 'site_changes_verified' => false];
    }

    private static function record_id(string $request_id): string { return 'delivery-' . hash('sha256', $request_id); }
    private static function validate_input(string $request_id, string $desktop_thread_id, string $payload_hash): void {
        if (!preg_match('/\A[a-z0-9_-]{1,96}\z/D', $request_id) || !preg_match('/\A[a-f0-9]{64}\z/D', $payload_hash)) self::fail('delivery_input_invalid');
        self::validate_desktop_id($desktop_thread_id);
    }
    private static function validate_desktop_id(string $id): void {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z/D', $id)) self::fail('delivery_input_invalid');
    }
    private static function fail(string $code): void { throw new RuntimeException($code); }
}
