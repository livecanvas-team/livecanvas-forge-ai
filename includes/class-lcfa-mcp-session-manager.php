<?php

defined('ABSPATH') || exit;

final class LCFA_MCP_Session_Manager {
    private const SESSIONS_OPTION_KEY = 'lcfa_mcp_sessions';
    private const PAIRING_INDEX_OPTION_KEY = 'lcfa_mcp_pairing_index';
    private const PAIRING_TRANSIENT_PREFIX = 'lcfa_mcp_pairing_';
    private const PAIRING_RATE_PREFIX = 'lcfa_mcp_pairing_rate_';
    private const PAIRING_ADMIN_ANCHOR = 'lcfa-secure-codex-pairing-sessions';
    private const PAIRING_TTL = 600;
    private const SESSION_TTL = 2592000;

    public static function start_pairing(array $payload, ?WP_REST_Request $request = null) {
        $rate_limited = self::is_pairing_rate_limited($request);
        if ($rate_limited instanceof WP_Error) {
            return $rate_limited;
        }

        $site_fingerprint = sanitize_text_field((string) ($payload['site_fingerprint'] ?? ''));
        $expected_fingerprint = self::get_site_fingerprint();
        if ($site_fingerprint !== '' && $expected_fingerprint !== '' && !hash_equals($expected_fingerprint, $site_fingerprint)) {
            return new WP_Error(
                'lcfa_pairing_site_mismatch',
                __('This pairing request targets a different AI Bridge site fingerprint.', 'livecanvas-forge-ai'),
                ['status' => 403]
            );
        }

        $now = time();
        $pairing_id = 'pair_' . strtolower(self::random_url_token(12));
        $device_secret = 'lcfa_dev_' . self::random_url_token(32);
        $user_code = self::generate_user_code();
        $record = [
            'pairing_id'         => $pairing_id,
            'user_code'          => $user_code,
            'client'             => self::sanitize_client((string) ($payload['client'] ?? 'codex')),
            'project_label'      => sanitize_text_field((string) ($payload['project_label'] ?? 'Codex project')),
            'site_fingerprint'   => $site_fingerprint !== '' ? $site_fingerprint : $expected_fingerprint,
            'scopes'             => self::sanitize_scopes((array) ($payload['scopes'] ?? ['read', 'preview', 'write'])),
            'status'             => 'pending',
            'device_secret_hash' => self::hash_token($device_secret),
            'session_id'         => '',
            'created_at'         => gmdate('c', $now),
            'expires_at'         => gmdate('c', $now + self::PAIRING_TTL),
            'approved_at'        => '',
            'consumed_at'        => '',
        ];

        set_transient(self::PAIRING_TRANSIENT_PREFIX . $pairing_id, $record, self::PAIRING_TTL);
        self::index_pairing($pairing_id);

        return [
            'ok'               => true,
            'pairing_id'       => $pairing_id,
            'device_secret'    => $device_secret,
            'user_code'        => $user_code,
            'verification_url' => self::get_verification_url(),
            'expires_at'       => $record['expires_at'],
            'message'          => __('Approve this Codex pairing request in LiveCanvas AI Bridge.', 'livecanvas-forge-ai'),
        ];
    }

    public static function get_pairing_status(string $pairing_id, string $device_secret) {
        $pairing_id = sanitize_key($pairing_id);
        $record = self::get_pairing_record($pairing_id);
        if (!$record) {
            return [
                'ok'      => false,
                'status'  => 'expired',
                'message' => __('The pairing request expired or was not found.', 'livecanvas-forge-ai'),
            ];
        }

        if (!hash_equals((string) ($record['device_secret_hash'] ?? ''), self::hash_token($device_secret))) {
            return new WP_Error(
                'lcfa_pairing_forbidden',
                __('The pairing device secret is invalid.', 'livecanvas-forge-ai'),
                ['status' => 403]
            );
        }

        if (self::is_expired((string) ($record['expires_at'] ?? ''))) {
            self::delete_pairing($pairing_id);
            return [
                'ok'      => false,
                'status'  => 'expired',
                'message' => __('The pairing request expired.', 'livecanvas-forge-ai'),
            ];
        }

        if (($record['status'] ?? '') !== 'approved') {
            return [
                'ok'          => true,
                'status'      => (string) ($record['status'] ?? 'pending'),
                'pairing_id'  => $pairing_id,
                'user_code'   => (string) ($record['user_code'] ?? ''),
                'expires_at'  => (string) ($record['expires_at'] ?? ''),
                'message'     => __('Waiting for a WordPress admin to approve this Codex pairing request.', 'livecanvas-forge-ai'),
            ];
        }

        $session_token = (string) ($record['session_token'] ?? '');
        if ($session_token === '') {
            return [
                'ok'         => true,
                'status'     => 'consumed',
                'session_id' => (string) ($record['session_id'] ?? ''),
                'message'    => __('The pairing token was already consumed. Restart the MCP server if the session was cached, or create a new pairing request.', 'livecanvas-forge-ai'),
            ];
        }

        unset($record['session_token']);
        $record['status'] = 'consumed';
        $record['consumed_at'] = gmdate('c');
        set_transient(self::PAIRING_TRANSIENT_PREFIX . $pairing_id, $record, self::PAIRING_TTL);

        return [
            'ok'            => true,
            'status'        => 'approved',
            'session_id'    => (string) ($record['session_id'] ?? ''),
            'session_token' => $session_token,
            'expires_at'    => self::get_session_expires_at((string) ($record['session_id'] ?? '')),
            'message'       => __('Codex pairing approved. The AI Bridge session token was issued once.', 'livecanvas-forge-ai'),
        ];
    }

    public static function approve_pairing(string $pairing_id): array {
        $pairing_id = sanitize_key($pairing_id);
        $record = self::get_pairing_record($pairing_id);
        if (!$record || self::is_expired((string) ($record['expires_at'] ?? ''))) {
            self::delete_pairing($pairing_id);
            return [
                'ok'      => false,
                'message' => __('The pairing request expired or was not found.', 'livecanvas-forge-ai'),
            ];
        }

        $session_token = 'lcfa_sess_' . self::random_url_token(40);
        $session_id = 'sess_' . strtolower(self::random_url_token(12));
        $now = time();
        $session = [
            'session_id'       => $session_id,
            'client'           => self::sanitize_client((string) ($record['client'] ?? 'codex')),
            'project_label'    => sanitize_text_field((string) ($record['project_label'] ?? 'Codex project')),
            'site_fingerprint' => sanitize_text_field((string) ($record['site_fingerprint'] ?? self::get_site_fingerprint())),
            'scopes'           => self::sanitize_scopes((array) ($record['scopes'] ?? ['read', 'preview', 'write'])),
            'created_at'       => gmdate('c', $now),
            'expires_at'       => gmdate('c', $now + self::SESSION_TTL),
            'last_seen_at'     => '',
            'revoked_at'       => '',
            'token_hash'       => self::hash_token($session_token),
        ];

        $sessions = self::get_sessions();
        $sessions[$session_id] = $session;
        update_option(self::SESSIONS_OPTION_KEY, $sessions, false);

        $record['status'] = 'approved';
        $record['session_id'] = $session_id;
        $record['session_token'] = $session_token;
        $record['approved_at'] = gmdate('c', $now);
        set_transient(self::PAIRING_TRANSIENT_PREFIX . $pairing_id, $record, self::PAIRING_TTL);

        self::invalidate_ready_state(__('A new Codex pairing session was approved. Run the smoke test again.', 'livecanvas-forge-ai'));

        return [
            'ok'         => true,
            'session_id' => $session_id,
            'message'    => __('Codex pairing approved. Ask Codex to retry the connection handoff.', 'livecanvas-forge-ai'),
        ];
    }

    public static function get_pending_pairings(): array {
        self::cleanup_pairing_index();
        $pairings = [];
        foreach (self::get_pairing_index() as $pairing_id) {
            $record = self::get_pairing_record($pairing_id);
            if (!$record || ($record['status'] ?? '') !== 'pending') {
                continue;
            }
            unset($record['device_secret_hash'], $record['session_token']);
            $pairings[] = $record;
        }

        return $pairings;
    }

    public static function get_sessions(bool $include_expired = true): array {
        $sessions = get_option(self::SESSIONS_OPTION_KEY, []);
        if (!is_array($sessions)) {
            return [];
        }

        $normalized = [];
        foreach ($sessions as $session_id => $session) {
            if (!is_array($session)) {
                continue;
            }
            $session_id = sanitize_key((string) ($session['session_id'] ?? $session_id));
            if ($session_id === '') {
                continue;
            }
            $session['session_id'] = $session_id;
            if (!$include_expired && self::session_is_expired($session)) {
                continue;
            }
            $normalized[$session_id] = $session;
        }

        return $normalized;
    }

    public static function get_public_sessions(): array {
        $public = [];
        foreach (self::get_sessions() as $session) {
            unset($session['token_hash']);
            $session['expired'] = self::session_is_expired($session);
            $session['revoked'] = trim((string) ($session['revoked_at'] ?? '')) !== '';
            $public[] = $session;
        }

        usort($public, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $public;
    }

    public static function revoke_session(string $session_id): array {
        $session_id = sanitize_key($session_id);
        $sessions = self::get_sessions();
        if (empty($sessions[$session_id])) {
            return [
                'ok'      => false,
                'message' => __('The AI Bridge MCP session was not found.', 'livecanvas-forge-ai'),
            ];
        }

        $sessions[$session_id]['revoked_at'] = gmdate('c');
        update_option(self::SESSIONS_OPTION_KEY, $sessions, false);
        self::invalidate_ready_state(__('The active Codex session was revoked. Pair Codex again before testing.', 'livecanvas-forge-ai'));

        return [
            'ok'      => true,
            'message' => __('AI Bridge MCP session revoked.', 'livecanvas-forge-ai'),
        ];
    }

    public static function get_session_from_request(?WP_REST_Request $request, string $required_scope = 'read') {
        if (!$request instanceof WP_REST_Request) {
            return false;
        }

        $token = trim((string) $request->get_header('x-lcfa-mcp-session'));
        if ($token === '') {
            $authorization = trim((string) $request->get_header('authorization'));
            if (stripos($authorization, 'Bearer ') === 0) {
                $token = trim(substr($authorization, 7));
            }
        }
        if ($token === '') {
            $token = sanitize_text_field((string) $request->get_param('mcp_session'));
        }
        if ($token === '') {
            return false;
        }

        return self::validate_session_token($token, $required_scope);
    }

    public static function validate_session_token(string $token, string $required_scope = 'read') {
        $token_hash = self::hash_token($token);
        $sessions = self::get_sessions();
        foreach ($sessions as $session_id => $session) {
            if (!hash_equals((string) ($session['token_hash'] ?? ''), $token_hash)) {
                continue;
            }
            if (trim((string) ($session['revoked_at'] ?? '')) !== '' || self::session_is_expired($session)) {
                return false;
            }
            $session_fingerprint = sanitize_text_field((string) ($session['site_fingerprint'] ?? ''));
            $site_fingerprint = self::get_site_fingerprint();
            if ($session_fingerprint !== '' && $site_fingerprint !== '' && !hash_equals($site_fingerprint, $session_fingerprint)) {
                self::invalidate_ready_state(__('The active Codex session belongs to another AI Bridge site fingerprint. Pair Codex again.', 'livecanvas-forge-ai'));
                return false;
            }
            $scopes = self::sanitize_scopes((array) ($session['scopes'] ?? []));
            if ($required_scope === 'write' && !in_array('write', $scopes, true)) {
                return false;
            }
            if ($required_scope === 'preview' && !in_array('preview', $scopes, true) && !in_array('write', $scopes, true)) {
                return false;
            }

            $sessions[$session_id]['last_seen_at'] = gmdate('c');
            update_option(self::SESSIONS_OPTION_KEY, $sessions, false);
            unset($sessions[$session_id]['token_hash']);
            self::mark_connection_verified_from_session($sessions[$session_id]);

            return $sessions[$session_id];
        }

        return false;
    }

    public static function has_active_session(): bool {
        foreach (self::get_sessions() as $session) {
            if (trim((string) ($session['revoked_at'] ?? '')) === '' && !self::session_is_expired($session)) {
                return true;
            }
        }

        return false;
    }

    private static function get_pairing_record(string $pairing_id) {
        $record = get_transient(self::PAIRING_TRANSIENT_PREFIX . sanitize_key($pairing_id));
        return is_array($record) ? $record : false;
    }

    private static function delete_pairing(string $pairing_id): void {
        $pairing_id = sanitize_key($pairing_id);
        delete_transient(self::PAIRING_TRANSIENT_PREFIX . $pairing_id);
        $index = array_values(array_diff(self::get_pairing_index(), [$pairing_id]));
        update_option(self::PAIRING_INDEX_OPTION_KEY, $index, false);
    }

    private static function index_pairing(string $pairing_id): void {
        $index = self::get_pairing_index();
        $index[] = sanitize_key($pairing_id);
        update_option(self::PAIRING_INDEX_OPTION_KEY, array_values(array_unique($index)), false);
    }

    private static function get_pairing_index(): array {
        $index = get_option(self::PAIRING_INDEX_OPTION_KEY, []);
        return is_array($index) ? array_values(array_filter(array_map('sanitize_key', $index))) : [];
    }

    private static function cleanup_pairing_index(): void {
        $next = [];
        foreach (self::get_pairing_index() as $pairing_id) {
            if (self::get_pairing_record($pairing_id)) {
                $next[] = $pairing_id;
            }
        }
        update_option(self::PAIRING_INDEX_OPTION_KEY, array_values(array_unique($next)), false);
    }

    private static function is_pairing_rate_limited(?WP_REST_Request $request) {
        $ip = $request instanceof WP_REST_Request ? (string) ($request->get_header('x-forwarded-for') ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) : 'unknown';
        $key = self::PAIRING_RATE_PREFIX . md5($ip);
        $count = absint(get_transient($key));
        if ($count >= 20) {
            return new WP_Error(
                'lcfa_pairing_rate_limited',
                __('Too many pairing requests. Wait a few minutes and try again.', 'livecanvas-forge-ai'),
                ['status' => 429]
            );
        }
        $minute = defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60;
        set_transient($key, $count + 1, 5 * $minute);

        return true;
    }

    private static function get_verification_url(): string {
        return function_exists('admin_url')
            ? admin_url('admin.php?page=lcfa-dashboard&tab=connections#' . self::PAIRING_ADMIN_ANCHOR)
            : '';
    }

    private static function get_session_expires_at(string $session_id): string {
        $sessions = self::get_sessions();
        return (string) ($sessions[$session_id]['expires_at'] ?? '');
    }

    private static function sanitize_client(string $client): string {
        $client = sanitize_key($client);
        return in_array($client, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true) ? $client : 'codex';
    }

    private static function sanitize_scopes(array $scopes): array {
        $allowed = ['read', 'preview', 'write'];
        $normalized = [];
        foreach ($scopes as $scope) {
            $scope = sanitize_key((string) $scope);
            if (in_array($scope, $allowed, true)) {
                $normalized[] = $scope;
            }
        }
        if ($normalized === []) {
            $normalized = ['read', 'preview', 'write'];
        }

        return array_values(array_unique($normalized));
    }

    private static function session_is_expired(array $session): bool {
        return self::is_expired((string) ($session['expires_at'] ?? ''));
    }

    private static function is_expired(string $expires_at): bool {
        $timestamp = strtotime($expires_at);
        return !$timestamp || $timestamp < time();
    }

    private static function invalidate_ready_state(string $message): void {
        if (!class_exists('LCFA_Settings', false)) {
            return;
        }
        $connections = LCFA_Settings::get_connections();
        if ((string) ($connections['connection_status'] ?? '') !== 'ready') {
            return;
        }
        $connections['connection_status'] = 'needs_attention';
        $connections['connection_current_step'] = 'smoke_test';
        $connections['connection_last_error'] = $message;
        LCFA_Settings::update_connections($connections);
    }

    private static function mark_connection_verified_from_session(array $session): void {
        if (!class_exists('LCFA_Settings', false)) {
            return;
        }

        $connections = LCFA_Settings::get_connections();
        $changed = false;
        $updates = [
            'preferred_client'       => 'codex',
            'connection_mode'        => 'remote',
            'connection_strategy'    => 'ai-bridge-session',
            'connection_status'      => 'ready',
            'connection_current_step' => 'ready',
            'connection_last_error'  => '',
        ];

        foreach ($updates as $key => $value) {
            if ((string) ($connections[$key] ?? '') !== $value) {
                $connections[$key] = $value;
                $changed = true;
            }
        }

        $verified_at = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        if ((string) ($connections['connection_last_verified_at'] ?? '') !== $verified_at) {
            $connections['connection_last_verified_at'] = $verified_at;
            $changed = true;
        }

        $project_label = sanitize_text_field((string) ($session['project_label'] ?? ''));
        if ($project_label !== '' && trim((string) ($connections['remote_project_label'] ?? '')) === '') {
            $connections['remote_project_label'] = $project_label;
            $changed = true;
        }

        if ($changed) {
            LCFA_Settings::update_connections($connections);
        }
    }

    private static function get_site_fingerprint(): string {
        return class_exists('LCFA_Settings', false) && method_exists('LCFA_Settings', 'get_site_fingerprint')
            ? LCFA_Settings::get_site_fingerprint()
            : '';
    }

    private static function hash_token(string $token): string {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'lcfa');
        return hash_hmac('sha256', $token, (string) $salt);
    }

    private static function random_url_token(int $bytes): string {
        try {
            $raw = random_bytes($bytes);
        } catch (Throwable $throwable) {
            $raw = function_exists('wp_generate_password')
                ? wp_generate_password($bytes, true, true)
                : uniqid('lcfa', true) . mt_rand();
        }

        return rtrim(strtr(base64_encode((string) $raw), '+/', '-_'), '=');
    }

    private static function generate_user_code(): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            try {
                $index = random_int(0, strlen($alphabet) - 1);
            } catch (Throwable $throwable) {
                $index = mt_rand(0, strlen($alphabet) - 1);
            }
            $code .= $alphabet[$index];
        }

        return substr($code, 0, 4) . '-' . substr($code, 4);
    }
}
