<?php

defined('ABSPATH') || exit;

/** Private records with optimistic concurrency. Legacy global options are never imported implicitly. */
final class LCFA_Private_Store {
    private static array $locks = [];

    /** Serialize lifecycle decisions; CAS still protects each stored record. */
    public static function locked(string $resource, callable $operation) {
        $scope = self::scope();
        $name = 'lcfa-chat-' . substr(hash('sha256', implode('|', $scope) . '|' . $resource), 0, 48);
        if (isset(self::$locks[$name])) return $operation();
        global $wpdb;
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) !== '1') throw new RuntimeException('private_record_busy: Another operation is updating this conversation. Retry without starting new work.');
        self::$locks[$name] = true;
        try { return $operation(); }
        finally { unset(self::$locks[$name]); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
    }
    public static function owner(): int {
        $owner = (int) get_current_user_id();
        if (!$owner && class_exists('LCFA_MCP_Session_Manager')) $owner = LCFA_MCP_Session_Manager::current_owner_user_id();
        return $owner && user_can($owner, 'edit_pages') ? $owner : 0;
    }

    private static function scope(): array {
        $owner = self::owner();
        if (!$owner) throw new RuntimeException('private_owner_required: Reconnect with an identified WordPress user.');
        return ['owner_user_id' => $owner, 'site_hash' => hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH))];
    }

    private static function table(): string { global $wpdb; return $wpdb->prefix . 'lcfa_private_records'; }

    private static function schema(): void {
        if (get_option('lcfa_private_store_schema') === '1') return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        dbDelta("CREATE TABLE $table (
            owner_user_id bigint(20) unsigned NOT NULL,
            site_hash char(64) NOT NULL,
            kind varchar(16) NOT NULL,
            id varchar(96) NOT NULL,
            state varchar(32) NOT NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            payload longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (owner_user_id,site_hash,kind,id),
            KEY queue (owner_user_id,site_hash,kind,state,created_at)
        ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) throw new RuntimeException('private_storage_unavailable: Private conversation storage is unavailable.');
        update_option('lcfa_private_store_schema', '1', false);
    }

    private static function identity(string $kind, string $id): array {
        if (!in_array($kind, ['thread', 'request', 'delivery'], true) || !preg_match('/\A[a-z0-9_-]{1,96}\z/D', $id)) throw new RuntimeException('private_record_invalid: Invalid private record ID.');
        return self::scope() + ['kind' => $kind, 'id' => $id];
    }

    public static function read(string $kind, string $id): ?array {
        if (!self::owner() || get_option('lcfa_private_store_schema') !== '1') return null;
        $row = self::row(self::identity($kind, $id));
        return $row ? self::decode($row) : null;
    }

    public static function listing(string $kind, string $state = '', int $limit = 200): array {
        if (!self::owner() || get_option('lcfa_private_store_schema') !== '1') return [];
        $identity = self::identity($kind, 'index');
        global $wpdb;
        $sql = $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE owner_user_id=%d AND site_hash=%s AND kind=%s', $identity['owner_user_id'], $identity['site_hash'], $kind);
        if ($state !== '') $sql .= $wpdb->prepare(' AND state=%s', $state);
        $order = $state === 'queued' ? 'created_at,id' : 'updated_at DESC,id DESC';
        $rows = $wpdb->get_results($sql . $wpdb->prepare(' ORDER BY ' . $order . ' LIMIT %d', min(1000, max(1, $limit))), ARRAY_A);
        if (!is_array($rows)) throw new RuntimeException('private_storage_unavailable: Private records could not be read.');
        $records = [];
        foreach ($rows as $row) $records[$row['id']] = self::decode($row);
        return $records;
    }

    public static function insert(string $kind, string $id, array $payload, string $state): bool {
        $identity = self::identity($kind, $id);
        self::schema();
        global $wpdb;
        $time = gmdate('Y-m-d H:i:s');
        $row = $identity + ['state' => $state, 'revision' => 1, 'payload' => self::encode($payload, $identity), 'created_at' => $time, 'updated_at' => $time];
        $suppressed = $wpdb->suppress_errors(true);
        try { $inserted = $wpdb->insert(self::table(), $row); }
        finally { $wpdb->suppress_errors($suppressed); }
        if ($inserted === 1) return true;
        if (self::row($identity)) return false;
        throw new RuntimeException('private_storage_unavailable: The private record could not be saved.');
    }

    /** The callback must have no side effects: a concurrent update may retry it. */
    public static function mutate(string $kind, string $id, callable $change): ?array {
        $identity = self::identity($kind, $id);
        if (get_option('lcfa_private_store_schema') !== '1') return null;
        global $wpdb;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $row = self::row($identity);
            if (!$row) return null;
            $value = self::decode($row);
            $next = $change($value);
            if ($next === null) return null;
            if ($next === $value) return $value;
            $state = (string) ($next['_state'] ?? $row['state']);
            unset($next['_state'], $next['_revision']);
            $revision = (int) $row['revision'] + 1;
            $updated = $wpdb->update(self::table(), ['payload' => self::encode($next, $identity), 'state' => $state, 'revision' => $revision, 'updated_at' => gmdate('Y-m-d H:i:s')], $identity + ['revision' => $row['revision']]);
            if ($updated === false) throw new RuntimeException('private_storage_unavailable: The private record could not be updated.');
            if ($updated === 1) return $next + ['_state' => $state, '_revision' => $revision];
        }
        throw new RuntimeException('private_record_conflict: Another request changed this conversation. Retry without replacing its history.');
    }

    private static function row(array $identity): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE owner_user_id=%d AND site_hash=%s AND kind=%s AND id=%s', $identity['owner_user_id'], $identity['site_hash'], $identity['kind'], $identity['id']), ARRAY_A);
        if ($wpdb->last_error) throw new RuntimeException('private_storage_unavailable: The private record could not be read.');
        return is_array($row) ? $row : null;
    }

    private static function key(): string { return hash('sha256', wp_salt('auth') . '|lcfa-private-records-v1', true); }
    private static function aad(array $identity): string { return implode('|', [$identity['owner_user_id'], $identity['site_hash'], $identity['kind'], $identity['id']]); }
    private static function encode(array $value, array $identity): string {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('private_crypto_unavailable: OpenSSL is required for private conversations.');
        unset($value['_state'], $value['_revision']);
        $plain = json_encode($value, JSON_THROW_ON_ERROR);
        if (strlen($plain) > 16 * 1024 * 1024) throw new RuntimeException('private_record_full: Start a new conversation; this conversation reached its storage limit.');
        $nonce = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, self::aad($identity));
        if ($cipher === false) throw new RuntimeException('private_crypto_unavailable: Private record encryption failed.');
        return base64_encode($nonce . $tag . $cipher);
    }
    private static function decode(array $row): array {
        $bytes = base64_decode($row['payload'], true);
        if ($bytes === false || strlen($bytes) < 28) throw new RuntimeException('private_record_damaged: The private record is damaged.');
        $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), self::aad($row));
        if ($plain === false) throw new RuntimeException('private_record_damaged: The private record cannot be authenticated. Authentication salt rotation invalidates old records.');
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR) + ['_state' => $row['state'], '_revision' => (int) $row['revision']];
    }
}
