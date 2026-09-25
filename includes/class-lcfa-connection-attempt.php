<?php

defined('ABSPATH') || exit;
require_once __DIR__ . '/class-lcfa-agent-registry.php';

/** One installation, one consent, one authenticated proof. No credentials are stored here. */
final class LCFA_Connection_Attempt {
    private const PREFIX = 'lcfa_connect_attempt_';
    private const TTL = 1800;

    public static function create(string $client, int $user_id): array {
        $client = LCFA_Agent_Registry::normalize($client, '');
        if ($client === '' || $client === 'claude' || $user_id < 1) {
            throw new InvalidArgumentException('Choose a known client and an authenticated administrator.');
        }
        $attempt = [
            'id' => bin2hex(random_bytes(16)),
            'owner' => $user_id,
            'client' => $client,
            'site_fingerprint' => LCFA_Settings::get_site_fingerprint(),
            'package_version' => defined('LCFA_MCP_PACKAGE_VERSION') ? LCFA_MCP_PACKAGE_VERSION : '',
            'scopes' => LCFA_Agent_Registry::full_access_scopes(),
            'state' => 'waiting_for_client',
            'created_at' => time(),
            'expires_at' => time() + self::TTL,
            'pairing_id' => '',
            'session_id' => '',
            'verified_at' => '',
        ];
        self::save($attempt);
        return $attempt;
    }

    public static function get(string $id): array {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) return [];
        $attempt = get_transient(self::PREFIX . $id);
        return is_array($attempt) && (int) ($attempt['expires_at'] ?? 0) > time() ? $attempt : [];
    }

    public static function bind_pairing(string $id, array $pairing): bool {
        $attempt = self::get($id);
        if (!$attempt || $attempt['state'] !== 'waiting_for_client') return false;
        if ($attempt['client'] !== ($pairing['client'] ?? '')
            || !hash_equals($attempt['site_fingerprint'], (string) ($pairing['site_fingerprint'] ?? ''))
            || array_diff($attempt['scopes'], (array) ($pairing['scopes'] ?? []))
            || empty($pairing['pairing_id'])) return false;
        $attempt['pairing_id'] = $pairing['pairing_id'];
        $attempt['state'] = 'authorization_required';
        self::save($attempt);
        return true;
    }

    public static function can_approve(string $id, string $pairing_id, int $user_id): bool {
        $attempt = self::get($id);
        return $attempt && $attempt['state'] === 'authorization_required'
            && $attempt['owner'] === $user_id && hash_equals($attempt['pairing_id'], $pairing_id);
    }

    public static function approved(string $id, string $pairing_id, int $user_id, string $session_id): bool {
        if ($session_id === '' || !self::can_approve($id, $pairing_id, $user_id)) return false;
        $attempt = self::get($id);
        $attempt['session_id'] = $session_id;
        $attempt['state'] = 'verifying';
        self::save($attempt);
        return true;
    }

    // Called only after the authenticated handoff callback has built a successful response.
    public static function verify(string $id, array $session, string $package_version): bool {
        $attempt = self::get($id);
        if (!$attempt || !in_array($attempt['state'], ['verifying', 'ready'], true)) return false;
        if ($attempt['session_id'] === '' || !hash_equals($attempt['session_id'], (string) ($session['session_id'] ?? ''))
            || !hash_equals($attempt['id'], (string) ($session['connection_attempt'] ?? ''))
            || !hash_equals($attempt['site_fingerprint'], (string) ($session['site_fingerprint'] ?? ''))
            || !hash_equals($attempt['site_fingerprint'], LCFA_Settings::get_site_fingerprint())
            || $attempt['client'] !== ($session['client'] ?? '')
            || $attempt['package_version'] === '' || !hash_equals($attempt['package_version'], $package_version)
            || !empty($session['revoked_at']) || strtotime((string) ($session['expires_at'] ?? '')) <= time()
            || array_diff($attempt['scopes'], (array) ($session['scopes'] ?? []))) return false;
        if ($attempt['state'] === 'ready') return true;
        $attempt['state'] = 'ready';
        $attempt['verified_at'] = gmdate('c');
        $attempt['expires_at'] = strtotime($session['expires_at']);
        self::save($attempt);
        // Preserve legacy dashboards without using their global status as proof for this attempt.
        $connections = LCFA_Settings::get_connections();
        $connections['preferred_client'] = $attempt['client'];
        $connections['connection_mode'] = (string) ($session['connection_mode'] ?? 'remote');
        $connections['connection_status'] = 'ready';
        $connections['connection_current_step'] = 'ready';
        $connections['connection_last_verified_at'] = gmdate('Y-m-d H:i:s');
        $connections['connection_last_verified_mcp_package_version'] = $package_version;
        $connections['connection_last_handoff_session_id'] = $session['session_id'];
        $connections['connection_last_error'] = '';
        LCFA_Settings::update_connections($connections);
        return true;
    }

    public static function public_status(string $id, int $user_id): array {
        $attempt = self::get($id);
        if (!$attempt || $attempt['owner'] !== $user_id) return [];
        // A previously successful read must not hide a later revocation.
        if (in_array($attempt['state'], ['verifying', 'ready'], true)) {
            $sessions = LCFA_MCP_Session_Manager::get_sessions();
            $session = $sessions[$attempt['session_id']] ?? [];
            if (!$session || !empty($session['revoked_at']) || strtotime((string) ($session['expires_at'] ?? '')) <= time()) $attempt['state'] = 'reconnect_required';
            if ($session && !LCFA_MCP_Session_Manager::owner_can_authorize_full_access((int) ($session['owner_user_id'] ?? 0))) $attempt['state'] = 'reconnect_required';
            if (!hash_equals($attempt['site_fingerprint'], LCFA_Settings::get_site_fingerprint())
                || (defined('LCFA_MCP_PACKAGE_VERSION') && $attempt['package_version'] !== LCFA_MCP_PACKAGE_VERSION)) $attempt['state'] = 'reconnect_required';
        }
        unset($attempt['owner']);
        if ($attempt['state'] === 'authorization_required') {
            $pending = array_values(array_filter(LCFA_MCP_Session_Manager::get_pending_pairings(), static function (array $pairing) use ($attempt): bool {
                return ($pairing['pairing_id'] ?? '') === $attempt['pairing_id'];
            }));
            if ($pending) $attempt['user_code'] = $pending[0]['user_code'];
            else $attempt['state'] = 'expired';
        }
        return $attempt;
    }

    private static function save(array $attempt): void {
        set_transient(self::PREFIX . $attempt['id'], $attempt, max(1, $attempt['expires_at'] - time()));
    }
}
