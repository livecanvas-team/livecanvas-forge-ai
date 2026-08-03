<?php

defined('ABSPATH') || exit;

final class LCFA_OAuth_Client_Registration {
    private const MAX_REDIRECT_URIS = 5;
    private const MAX_REDIRECT_URI_LENGTH = 2048;

    public static function normalize(array $payload) {
        $auth_method = sanitize_key((string) ($payload['token_endpoint_auth_method'] ?? 'none'));
        if ($auth_method !== 'none') {
            return new WP_Error(
                'lcfa_oauth_confidential_client_not_supported',
                __('LiveCanvas AI Bridge accepts public OAuth clients only.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        $redirect_uris = $payload['redirect_uris'] ?? [];
        if (!is_array($redirect_uris) || $redirect_uris === []) {
            return new WP_Error(
                'lcfa_oauth_redirect_uris_required',
                __('At least one OAuth redirect URI is required.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        if (count($redirect_uris) > self::MAX_REDIRECT_URIS) {
            return new WP_Error(
                'lcfa_oauth_too_many_redirect_uris',
                __('Too many OAuth redirect URIs were supplied.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        $normalized_uris = [];
        foreach ($redirect_uris as $redirect_uri) {
            $normalized_uri = self::normalize_redirect_uri((string) $redirect_uri);
            if (is_wp_error($normalized_uri)) {
                return $normalized_uri;
            }
            $normalized_uris[] = $normalized_uri;
        }

        $grant_types = self::normalize_string_list(
            $payload['grant_types'] ?? ['authorization_code', 'refresh_token']
        );
        if ($grant_types === []) {
            $grant_types = ['authorization_code', 'refresh_token'];
        }
        foreach ($grant_types as $grant_type) {
            if (!in_array($grant_type, ['authorization_code', 'refresh_token'], true)) {
                return new WP_Error(
                    'lcfa_oauth_grant_type_not_supported',
                    __('The requested OAuth grant type is not supported.', 'livecanvas-forge-ai'),
                    ['status' => 400]
                );
            }
        }

        $response_types = self::normalize_string_list($payload['response_types'] ?? ['code']);
        if ($response_types === []) {
            $response_types = ['code'];
        }
        if ($response_types !== ['code']) {
            return new WP_Error(
                'lcfa_oauth_response_type_not_supported',
                __('Only the OAuth authorization code response type is supported.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        $client_name = sanitize_text_field((string) ($payload['client_name'] ?? 'Codex'));
        if ($client_name === '') {
            $client_name = 'Codex';
        }
        $client_name = function_exists('mb_substr')
            ? mb_substr($client_name, 0, 120)
            : substr($client_name, 0, 120);

        return [
            'client_name' => $client_name,
            'redirect_uris' => array_values(array_unique($normalized_uris)),
            'grant_types' => $grant_types,
            'response_types' => $response_types,
            'token_endpoint_auth_method' => 'none',
            'application_type' => sanitize_key((string) ($payload['application_type'] ?? 'native')) ?: 'native',
        ];
    }

    public static function normalize_redirect_uri(string $redirect_uri) {
        $redirect_uri = trim($redirect_uri);
        if ($redirect_uri === '' || strlen($redirect_uri) > self::MAX_REDIRECT_URI_LENGTH) {
            return new WP_Error(
                'lcfa_oauth_invalid_redirect_uri',
                __('The OAuth redirect URI is missing or too long.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        $parts = wp_parse_url($redirect_uri);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return new WP_Error(
                'lcfa_oauth_invalid_redirect_uri',
                __('The OAuth redirect URI must be an absolute HTTP URL.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        if (!empty($parts['fragment']) || !empty($parts['user']) || !empty($parts['pass'])) {
            return new WP_Error(
                'lcfa_oauth_unsafe_redirect_uri',
                __('OAuth redirect URIs cannot contain fragments or user information.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(trim((string) $parts['host'], '[]'));
        $loopback = self::is_loopback_host($host);

        if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
            return new WP_Error(
                'lcfa_oauth_insecure_redirect_uri',
                __('OAuth redirect URIs must use HTTPS, except for loopback callbacks.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        if (!$loopback && self::is_private_host($host)) {
            return new WP_Error(
                'lcfa_oauth_private_redirect_uri',
                __('Private-network OAuth redirect hosts are not allowed.', 'livecanvas-forge-ai'),
                ['status' => 400]
            );
        }

        return $redirect_uri;
    }

    private static function normalize_string_list($value): array {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = sanitize_key((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function is_loopback_host(string $host): bool {
        if ($host === 'localhost' || $host === '::1') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && strpos($host, '127.') === 0;
    }

    private static function is_private_host(string $host): bool {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }

        return (bool) preg_match('/(?:^|\.)(?:local|internal|lan|home|localhost)$/i', $host);
    }
}

final class LCFA_OAuth_Storage {
    private const SCHEMA_VERSION = '1';
    private const SCHEMA_OPTION = 'lcfa_oauth_schema_version';
    private const KEYS_OPTION = 'lcfa_oauth_keys';
    private const KEYS_LOCK_OPTION = 'lcfa_oauth_keys_lock';
    private const CLEANUP_HOOK = 'lcfa_oauth_cleanup';

    public static function install_schema() {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return new WP_Error(
                'lcfa_oauth_database_unavailable',
                __('The WordPress database is not available for OAuth setup.', 'livecanvas-forge-ai')
            );
        }

        if (!function_exists('dbDelta')) {
            $upgrade_file = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
            if ($upgrade_file === '' || !is_readable($upgrade_file)) {
                return new WP_Error(
                    'lcfa_oauth_schema_unavailable',
                    __('WordPress database upgrade helpers are unavailable.', 'livecanvas-forge-ai')
                );
            }
            require_once $upgrade_file;
        }

        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $clients = self::table('clients');
        $auth_codes = self::table('auth_codes');
        $access_tokens = self::table('access_tokens');
        $refresh_tokens = self::table('refresh_tokens');

        dbDelta("CREATE TABLE {$clients} (
            client_id varchar(128) NOT NULL,
            client_name varchar(191) NOT NULL,
            redirect_uris longtext NOT NULL,
            grant_types text NOT NULL,
            registered_ip_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            last_used_at datetime NULL,
            revoked_at datetime NULL,
            PRIMARY KEY  (client_id),
            KEY registered_ip_hash (registered_ip_hash),
            KEY revoked_at (revoked_at)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$auth_codes} (
            identifier_hash char(64) NOT NULL,
            client_id varchar(128) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            revoked_at datetime NULL,
            PRIMARY KEY  (identifier_hash),
            KEY client_id (client_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$access_tokens} (
            identifier_hash char(64) NOT NULL,
            client_id varchar(128) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            scopes text NOT NULL,
            site_fingerprint varchar(64) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            last_seen_at datetime NULL,
            revoked_at datetime NULL,
            PRIMARY KEY  (identifier_hash),
            KEY client_id (client_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$refresh_tokens} (
            identifier_hash char(64) NOT NULL,
            access_token_hash char(64) NOT NULL,
            client_id varchar(128) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            revoked_at datetime NULL,
            PRIMARY KEY  (identifier_hash),
            KEY access_token_hash (access_token_hash),
            KEY client_id (client_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};");

        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        self::ensure_cleanup_event();

        return self::ensure_keys();
    }

    public static function ensure_schema() {
        if ((string) get_option(self::SCHEMA_OPTION, '') === self::SCHEMA_VERSION) {
            return self::ensure_keys();
        }

        return self::install_schema();
    }

    public static function ensure_keys() {
        $stored_keys = self::read_stored_keys();
        if (is_array($stored_keys)) {
            return $stored_keys;
        }

        if (
            !function_exists('openssl_pkey_new')
            || !function_exists('openssl_pkey_export')
            || !function_exists('openssl_encrypt')
        ) {
            return new WP_Error(
                'lcfa_oauth_openssl_unavailable',
                __('OpenSSL is required to create OAuth signing keys.', 'livecanvas-forge-ai')
            );
        }

        $lock = self::acquire_key_generation_lock();
        if (is_wp_error($lock)) {
            return $lock;
        }

        try {
            $stored_keys = self::read_stored_keys();
            if (is_array($stored_keys)) {
                return $stored_keys;
            }

            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if ($resource === false) {
                return new WP_Error(
                    'lcfa_oauth_key_generation_failed',
                    __('OAuth signing keys could not be generated.', 'livecanvas-forge-ai')
                );
            }

            $private_key = '';
            if (!openssl_pkey_export($resource, $private_key)) {
                return new WP_Error(
                    'lcfa_oauth_private_key_export_failed',
                    __('The OAuth private key could not be exported.', 'livecanvas-forge-ai')
                );
            }

            $details = openssl_pkey_get_details($resource);
            $public_key = is_array($details) ? (string) ($details['key'] ?? '') : '';
            if ($public_key === '') {
                return new WP_Error(
                    'lcfa_oauth_public_key_export_failed',
                    __('The OAuth public key could not be exported.', 'livecanvas-forge-ai')
                );
            }

            try {
                $encryption_key = bin2hex(random_bytes(32));
            } catch (Throwable $exception) {
                return new WP_Error(
                    'lcfa_oauth_random_source_failed',
                    __('Secure OAuth key material could not be generated.', 'livecanvas-forge-ai')
                );
            }

            $encrypted_private_key = self::encrypt_secret($private_key);
            $encrypted_encryption_key = self::encrypt_secret($encryption_key);
            if ($encrypted_private_key === '' || $encrypted_encryption_key === '') {
                return new WP_Error(
                    'lcfa_oauth_key_encryption_failed',
                    __('OAuth signing material could not be encrypted for storage.', 'livecanvas-forge-ai')
                );
            }

            $payload = [
                'private_key' => $encrypted_private_key,
                'public_key' => $public_key,
                'encryption_key' => $encrypted_encryption_key,
                'created_at' => gmdate('c'),
            ];
            $stored = update_option(self::KEYS_OPTION, $payload, false);
            if (!$stored && self::read_stored_keys() === null) {
                return new WP_Error(
                    'lcfa_oauth_key_storage_failed',
                    __('OAuth signing material could not be stored in WordPress.', 'livecanvas-forge-ai')
                );
            }

            return [
                'private_key' => $private_key,
                'public_key' => $public_key,
                'encryption_key' => $encryption_key,
                'created_at' => $payload['created_at'],
            ];
        } finally {
            delete_option(self::KEYS_LOCK_OPTION);
        }
    }

    public static function create_client(array $registration, string $ip_hash) {
        global $wpdb;

        $schema = self::ensure_schema();
        if (is_wp_error($schema)) {
            return $schema;
        }

        try {
            $client_id = 'lcfa_' . bin2hex(random_bytes(18));
        } catch (Throwable $exception) {
            return new WP_Error(
                'lcfa_oauth_client_id_generation_failed',
                __('A secure OAuth client identifier could not be generated.', 'livecanvas-forge-ai'),
                ['status' => 500]
            );
        }
        $inserted = $wpdb->insert(
            self::table('clients'),
            [
                'client_id' => $client_id,
                'client_name' => (string) $registration['client_name'],
                'redirect_uris' => wp_json_encode(array_values((array) $registration['redirect_uris'])),
                'grant_types' => wp_json_encode(array_values((array) $registration['grant_types'])),
                'registered_ip_hash' => $ip_hash,
                'created_at' => self::now(),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error(
                'lcfa_oauth_client_create_failed',
                __('The OAuth client could not be registered.', 'livecanvas-forge-ai'),
                ['status' => 500]
            );
        }

        return array_merge($registration, [
            'client_id' => $client_id,
            'client_id_issued_at' => time(),
        ]);
    }

    public static function get_client(string $client_id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table('clients') . ' WHERE client_id = %s AND revoked_at IS NULL LIMIT 1',
                $client_id
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }

        $row['redirect_uris'] = self::decode_list((string) ($row['redirect_uris'] ?? ''));
        $row['grant_types'] = self::decode_list((string) ($row['grant_types'] ?? ''));

        return $row;
    }

    public static function count_active_clients(): int {
        global $wpdb;

        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table('clients') . ' WHERE revoked_at IS NULL');
    }

    public static function count_clients_for_ip(string $ip_hash): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . self::table('clients') . ' WHERE registered_ip_hash = %s AND revoked_at IS NULL',
                $ip_hash
            )
        );
    }

    public static function touch_client(string $client_id): void {
        global $wpdb;

        $wpdb->update(
            self::table('clients'),
            ['last_used_at' => self::now()],
            ['client_id' => $client_id],
            ['%s'],
            ['%s']
        );
    }

    public static function persist_auth_code(string $identifier, string $client_id, int $user_id, string $expires_at): bool {
        global $wpdb;

        return $wpdb->insert(
            self::table('auth_codes'),
            [
                'identifier_hash' => self::hash_identifier($identifier),
                'client_id' => $client_id,
                'user_id' => $user_id,
                'expires_at' => $expires_at,
                'created_at' => self::now(),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        ) !== false;
    }

    public static function revoke_auth_code(string $identifier): void {
        self::revoke_identifier('auth_codes', $identifier);
    }

    public static function is_auth_code_revoked(string $identifier): bool {
        return self::identifier_is_revoked('auth_codes', $identifier);
    }

    public static function persist_access_token(
        string $identifier,
        string $client_id,
        int $user_id,
        array $scopes,
        string $expires_at
    ): bool {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::table('access_tokens'),
            [
                'identifier_hash' => self::hash_identifier($identifier),
                'client_id' => $client_id,
                'user_id' => $user_id,
                'scopes' => wp_json_encode(array_values($scopes)),
                'site_fingerprint' => self::site_fingerprint(),
                'expires_at' => $expires_at,
                'created_at' => self::now(),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        if ($inserted !== false) {
            self::touch_client($client_id);
        }

        return $inserted !== false;
    }

    public static function get_access_token(string $identifier): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table('access_tokens') . ' WHERE identifier_hash = %s LIMIT 1',
                self::hash_identifier($identifier)
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }

        $row['scopes'] = self::decode_list((string) ($row['scopes'] ?? ''));

        return $row;
    }

    public static function revoke_access_token(string $identifier): void {
        self::revoke_identifier('access_tokens', $identifier);
    }

    public static function is_access_token_revoked(string $identifier): bool {
        $record = self::get_access_token($identifier);

        return !is_array($record)
            || !empty($record['revoked_at'])
            || self::is_expired((string) ($record['expires_at'] ?? ''))
            || !hash_equals(self::site_fingerprint(), (string) ($record['site_fingerprint'] ?? ''));
    }

    public static function touch_access_token(string $identifier): void {
        global $wpdb;

        $wpdb->update(
            self::table('access_tokens'),
            ['last_seen_at' => self::now()],
            ['identifier_hash' => self::hash_identifier($identifier)],
            ['%s'],
            ['%s']
        );
    }

    public static function persist_refresh_token(
        string $identifier,
        string $access_token_identifier,
        string $client_id,
        int $user_id,
        string $expires_at
    ): bool {
        global $wpdb;

        return $wpdb->insert(
            self::table('refresh_tokens'),
            [
                'identifier_hash' => self::hash_identifier($identifier),
                'access_token_hash' => self::hash_identifier($access_token_identifier),
                'client_id' => $client_id,
                'user_id' => $user_id,
                'expires_at' => $expires_at,
                'created_at' => self::now(),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        ) !== false;
    }

    public static function revoke_refresh_token(string $identifier): void {
        self::revoke_identifier('refresh_tokens', $identifier);
    }

    public static function is_refresh_token_revoked(string $identifier): bool {
        return self::identifier_is_revoked('refresh_tokens', $identifier);
    }

    public static function revoke_client(string $client_id): bool {
        global $wpdb;

        $now = self::now();
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table('access_tokens') . ' SET revoked_at = %s WHERE client_id = %s AND revoked_at IS NULL',
                $now,
                $client_id
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table('refresh_tokens') . ' SET revoked_at = %s WHERE client_id = %s AND revoked_at IS NULL',
                $now,
                $client_id
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table('auth_codes') . ' SET revoked_at = %s WHERE client_id = %s AND revoked_at IS NULL',
                $now,
                $client_id
            )
        );

        return $wpdb->update(
            self::table('clients'),
            ['revoked_at' => $now],
            ['client_id' => $client_id],
            ['%s'],
            ['%s']
        ) !== false;
    }

    public static function get_connected_apps(): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT c.client_id, c.client_name, c.created_at, c.last_used_at, c.revoked_at,
                MAX(a.last_seen_at) AS token_last_seen_at,
                MAX(a.expires_at) AS token_expires_at,
                SUM(CASE WHEN a.revoked_at IS NULL AND a.expires_at > UTC_TIMESTAMP() AND a.site_fingerprint = %s THEN 1 ELSE 0 END) AS active_tokens
             FROM ' . self::table('clients') . ' c
             LEFT JOIN ' . self::table('access_tokens') . ' a ON a.client_id = c.client_id
             GROUP BY c.client_id, c.client_name, c.created_at, c.last_used_at, c.revoked_at
             ORDER BY c.created_at DESC',
                self::site_fingerprint()
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public static function cleanup(): void {
        global $wpdb;

        $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        foreach (['auth_codes', 'access_tokens', 'refresh_tokens'] as $suffix) {
            $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM ' . self::table($suffix) . ' WHERE expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)',
                    self::now(),
                    $cutoff
                )
            );
        }
    }

    public static function resource_url(): string {
        global $wp_rewrite;

        if (is_object($wp_rewrite)) {
            return untrailingslashit(rest_url('livecanvas-forge-ai/mcp'));
        }

        return untrailingslashit(self::early_rest_url('livecanvas-forge-ai/mcp'));
    }

    public static function issuer_url(): string {
        return untrailingslashit(home_url('/'));
    }

    public static function authorization_url(): string {
        return admin_url('admin.php?page=lcfa-oauth-authorize');
    }

    public static function registration_url(): string {
        return rest_url('lcfa/v1/oauth/register');
    }

    public static function token_url(): string {
        return rest_url('lcfa/v1/oauth/token');
    }

    public static function revocation_url(): string {
        return rest_url('lcfa/v1/oauth/revoke');
    }

    public static function protected_resource_metadata_url(): string {
        return home_url('/.well-known/oauth-protected-resource');
    }

    public static function authorization_server_metadata_url(): string {
        return home_url('/.well-known/oauth-authorization-server');
    }

    public static function request_targets_resource(string $request_url): bool {
        $request = self::parse_absolute_url($request_url);
        $resource = self::parse_absolute_url(self::resource_url());
        if (!is_array($request) || !is_array($resource)) {
            return false;
        }

        foreach (['scheme', 'host', 'port'] as $key) {
            if ((string) ($request[$key] ?? '') !== (string) ($resource[$key] ?? '')) {
                return false;
            }
        }

        $resource_route = self::rest_route_from_url_parts($resource);
        if ($resource_route !== '') {
            $request_route = self::rest_route_from_url_parts($request);

            return self::route_matches($request_route, $resource_route);
        }

        return self::route_matches(
            (string) ($request['path'] ?? ''),
            (string) ($resource['path'] ?? '')
        );
    }

    public static function hash_ip(string $ip): string {
        return hash_hmac('sha256', trim($ip), wp_salt('auth'));
    }

    public static function site_fingerprint(): string {
        return class_exists('LCFA_Settings', false) && method_exists('LCFA_Settings', 'get_site_fingerprint')
            ? LCFA_Settings::get_site_fingerprint()
            : substr(hash('sha256', self::issuer_url()), 0, 16);
    }

    public static function activate(): void {
        self::install_schema();
        self::ensure_cleanup_event();
    }

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
    }

    public static function cleanup_hook(): string {
        return self::CLEANUP_HOOK;
    }

    private static function ensure_cleanup_event(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    private static function read_stored_keys(): ?array {
        $stored = get_option(self::KEYS_OPTION, []);
        if (
            !is_array($stored)
            || empty($stored['private_key'])
            || empty($stored['public_key'])
            || empty($stored['encryption_key'])
        ) {
            return null;
        }

        $private_key = self::decrypt_secret((string) $stored['private_key']);
        $encryption_key = self::decrypt_secret((string) $stored['encryption_key']);
        if ($private_key === '' || $encryption_key === '') {
            return null;
        }

        return [
            'private_key' => $private_key,
            'public_key' => (string) $stored['public_key'],
            'encryption_key' => $encryption_key,
            'created_at' => (string) ($stored['created_at'] ?? ''),
        ];
    }

    private static function acquire_key_generation_lock() {
        if (add_option(self::KEYS_LOCK_OPTION, time(), '', false)) {
            return true;
        }

        $started_at = (int) get_option(self::KEYS_LOCK_OPTION, 0);
        if ($started_at > 0 && $started_at < (time() - MINUTE_IN_SECONDS)) {
            delete_option(self::KEYS_LOCK_OPTION);
            if (add_option(self::KEYS_LOCK_OPTION, time(), '', false)) {
                return true;
            }
        }

        return new WP_Error(
            'lcfa_oauth_key_generation_busy',
            __('OAuth setup is already running. Retry the connection in a few seconds.', 'livecanvas-forge-ai'),
            ['status' => 503]
        );
    }

    private static function parse_absolute_url(string $url): ?array {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = 0;
        }

        return [
            'scheme' => $scheme,
            'host' => strtolower(trim((string) $parts['host'], '[]')),
            'port' => $port,
            'path' => '/' . ltrim((string) ($parts['path'] ?? '/'), '/'),
            'query' => (string) ($parts['query'] ?? ''),
        ];
    }

    private static function early_rest_url(string $route): string {
        $route = '/' . ltrim($route, '/');
        $permalink_structure = (string) get_option('permalink_structure', '');

        if ($permalink_structure === '') {
            return add_query_arg('rest_route', $route, home_url('/'));
        }

        $prefix = function_exists('rest_get_url_prefix')
            ? trim((string) rest_get_url_prefix(), '/')
            : 'wp-json';
        $uses_index = strpos($permalink_structure, '/index.php/') === 0;
        $path = ($uses_index ? 'index.php/' : '') . $prefix . '/' . ltrim($route, '/');

        return home_url('/' . $path);
    }

    private static function rest_route_from_url_parts(array $parts): string {
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        return isset($query['rest_route']) && is_scalar($query['rest_route'])
            ? '/' . ltrim((string) $query['rest_route'], '/')
            : '';
    }

    private static function route_matches(string $request_route, string $resource_route): bool {
        $request_route = '/' . trim($request_route, '/');
        $resource_route = '/' . trim($resource_route, '/');
        if ($request_route === '/' || $resource_route === '/') {
            return $request_route === $resource_route;
        }

        return $request_route === $resource_route
            || strpos($request_route, $resource_route . '/') === 0;
    }

    private static function table(string $suffix): string {
        global $wpdb;

        return $wpdb->prefix . 'lcfa_oauth_' . $suffix;
    }

    private static function hash_identifier(string $identifier): string {
        return hash_hmac('sha256', $identifier, wp_salt('secure_auth'));
    }

    private static function revoke_identifier(string $table_suffix, string $identifier): void {
        global $wpdb;

        $wpdb->update(
            self::table($table_suffix),
            ['revoked_at' => self::now()],
            ['identifier_hash' => self::hash_identifier($identifier)],
            ['%s'],
            ['%s']
        );
    }

    private static function identifier_is_revoked(string $table_suffix, string $identifier): bool {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT expires_at, revoked_at FROM ' . self::table($table_suffix) . ' WHERE identifier_hash = %s LIMIT 1',
                self::hash_identifier($identifier)
            ),
            ARRAY_A
        );

        return !is_array($row)
            || !empty($row['revoked_at'])
            || self::is_expired((string) ($row['expires_at'] ?? ''));
    }

    private static function is_expired(string $expires_at): bool {
        $timestamp = strtotime($expires_at . ' UTC');

        return $timestamp === false || $timestamp <= time();
    }

    private static function decode_list(string $value): array {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    private static function encrypt_secret(string $secret): string {
        if (!function_exists('openssl_encrypt')) {
            return '';
        }

        try {
            $key = self::option_encryption_key();
            $iv = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $secret,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
        } catch (Throwable $exception) {
            return '';
        }

        if ($ciphertext === false) {
            return '';
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt_secret(string $payload): string {
        if (!function_exists('openssl_decrypt') || strpos($payload, 'v1:') !== 0) {
            return '';
        }

        $decoded = base64_decode(substr($payload, 3), true);
        if (!is_string($decoded) || strlen($decoded) < 29) {
            return '';
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::option_encryption_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($plaintext) ? $plaintext : '';
    }

    private static function option_encryption_key(): string {
        return hash('sha256', wp_salt('secure_auth') . '|livecanvas-ai-bridge-oauth', true);
    }
}
