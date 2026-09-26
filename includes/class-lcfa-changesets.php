<?php

defined('ABSPATH') || exit;
require_once __DIR__ . '/class-lcfa-file-changesets.php';
require_once __DIR__ . '/class-lcfa-picostrap-changesets.php';
require_once __DIR__ . '/class-lcfa-windpress-changesets.php';

/** Private, owner-scoped journal for granular WordPress content writes. */
final class LCFA_Changesets {
    use LCFA_File_Changesets;
    use LCFA_Picostrap_Changesets;
    use LCFA_WindPress_Changesets;
    private const VERSION = '1';
    private const POST_ACTIONS = ['page_upsert', 'create_page', 'update_page', 'update_partial', 'create_dynamic_template', 'update_dynamic_template', 'update_discussion_settings'];
    private static bool $active = false;

    public static function active(): bool { return self::$active; }
    public static function supports(string $action): bool { return in_array($action, self::POST_ACTIONS, true); }

    public static function capabilities(): array {
        return ['schema_version' => 'changesets.v1', 'private_owner_required' => true,
            'content_actions' => self::POST_ACTIONS, 'child_theme_sources' => true,
            'file_write_coordinator' => 'authenticated_wordpress', 'undo_tool' => 'undo_changeset',
            'undo_target' => 'target_id for content; path for child-theme source; site scope for Picostrap bundle or WindPress compiled cache',
            'compiler_cache_and_media_undo' => false, 'multi_resource_atomicity' => false,
            'picostrap_bundle_and_metadata_undo' => true,
            'windpress_css_sourcemap_evidence_undo' => true,
            'media_undo' => false, 'whole_site_undo' => false,
            'legacy_file_restore' => 'review_required', 'external_shell_and_sql' => 'not_governed'];
    }

    public static function owner(): int {
        $owner = (int) get_current_user_id();
        if (!$owner && class_exists('LCFA_MCP_Session_Manager')) $owner = LCFA_MCP_Session_Manager::current_owner_user_id();
        if (!$owner || !user_can($owner, 'edit_pages')) throw new RuntimeException('changeset_owner_required: Connect with an identified WordPress user before writing. Legacy ownerless credentials cannot create private changesets.');
        return $owner;
    }

    public static function run_post(array $payload, callable $write, array $related_ids = []): array {
        if (self::$active || self::$file_active) return self::error('nested_changeset', 'Finish the current granular write before starting another.');
        $record = null;
        $locked = [];
        $transaction = false;
        $committed = false;
        $touched = [];
        $created = [];
        $option_keys = [];
        $observe_option = static function ($key) use (&$option_keys) { $option_keys[(string) $key] = true; };
        $observe = static function ($id, $post, $update) use (&$touched, &$created) { $touched[(int) $id] = true; if (!$update) $created[(int) $id] = true; };
        try {
            $owner = self::owner();
            self::assert_no_transaction();
            self::schema();
            $id = (int) ($payload['target_id'] ?? $payload['post_id'] ?? 0);
            if ($id) $touched[$id] = true;
            $related_ids = array_values(array_diff(array_unique(array_filter(array_map('intval', $related_ids))), [$id]));
            sort($related_ids);
            $targets = array_unique(array_merge([$id], $related_ids)); sort($targets);
            foreach ($targets as $target) {
                if ($target && !user_can($owner, 'edit_post', $target)) throw new RuntimeException('changeset_forbidden: You cannot edit an affected content target.');
                if ($target) $touched[$target] = true;
                $locked[] = self::lock('post:' . ($target ?: 'new'));
            }
            $check = LCFA_Write_Contract::validate($payload, (string) ($payload['action'] ?? ''));
            if (empty($check['ok'])) return $check;
            $before = self::snapshot($id, false, $related_ids);
            if ($id && !$before['post']) throw new RuntimeException('changeset_target_missing: The target no longer exists.');
            $record = self::prepare_record($owner, $id, (string) $payload['action'], $before, self::scope($check['context']));
            // The verified snapshot is committed before the target transaction.
            self::query('START TRANSACTION');
            $transaction = true;
            $current = self::snapshot($id, true, $related_ids);
            if (self::hash($current) !== self::hash($before)) throw new RuntimeException('changeset_conflict: Content changed while preparing its snapshot.');
            self::$active = true;
            add_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
            add_action('wp_after_insert_post', $observe, PHP_INT_MAX, 3);
            foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) add_action($hook, $observe_option, PHP_INT_MAX);
            $result = $write();
            if (empty($result['ok'])) throw new LCFA_Changeset_Write_Failure($result);
            if (($result['mode'] ?? 'apply') === 'preview') throw new LCFA_Changeset_Write_Failure($result);
            $target = $id ?: (int) ($result['target_id'] ?? 0);
            if (!$target || ($id && (int) ($result['target_id'] ?? $id) !== $id)) throw new RuntimeException('changeset_target_mismatch: The write did not return its expected target.');
            if (!$id && empty($created[$target])) throw new RuntimeException('changeset_target_mismatch: The returned creation target was not created by this operation.');
            $after = self::snapshot($target, true, $related_ids);
            if (!$after['post']) throw new RuntimeException('changeset_target_missing: The saved target could not be verified.');
            $touched[$target] = true;
            self::update_record($record['id'], ['target_id' => $target, 'after_hash' => self::hash($after), 'status' => 'saved']);
            self::unguard($observe);
            self::query('COMMIT');
            $transaction = false;
            $committed = true;
            $record = self::record($record['id'], $owner);
            $result['changeset'] = self::summary($record);
            $result['rollback_available'] = true;
            $result['undo_tool'] = 'undo_changeset';
            $result['verification_states'] = array_merge(['compiled' => 'not_checked', 'visually_verified' => 'not_checked'], (array) ($result['verification_states'] ?? []), ['saved' => true, 'published' => $after['post']['post_status'] === 'publish']);
            // Link evidence only from a committed WordPress write, never from
            // the worker's eventual, model-authored completion payload.
            if (class_exists('LCFA_Private_Chat')) LCFA_Private_Chat::record_saved_changeset($record['id']);
            return $result;
        } catch (Throwable $error) {
            self::unguard($observe);
            $rollback_ok = !$committed && (!$transaction || self::try_query('ROLLBACK'));
            $transaction = false;
            if ($record && $rollback_ok) {
                try {
                    $rollback_ok = self::hash(self::snapshot($id, false, $related_ids)) === self::hash($before);
                    foreach (array_keys($created) as $created_id) if (self::snapshot($created_id)['post']) $rollback_ok = false;
                } catch (Throwable $ignored) { $rollback_ok = false; }
            }
            if ($record) {
                try { self::update_record($record['id'], ['status' => $rollback_ok ? 'rolled_back' : 'needs_recovery']); }
                catch (Throwable $ignored) { $rollback_ok = false; }
            }
            $result = $error instanceof LCFA_Changeset_Write_Failure ? $error->result : self::exception($error);
            if ($record) $result['changeset'] = ['id' => $record['id'], 'status' => $rollback_ok ? 'rolled_back' : 'needs_recovery', 'undo_available' => false];
            $result['transaction'] = ['database_rollback_verified' => $rollback_ok, 'external_side_effects' => 'not_covered'];
            return $result;
        } finally {
            self::unguard($observe);
            foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) remove_action($hook, $observe_option, PHP_INT_MAX);
            if ($transaction) self::try_query('ROLLBACK');
            foreach (array_keys($touched) as $id) clean_post_cache($id);
            // WordPress hooks can update option caches inside the transaction.
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            foreach (array_keys($option_keys) as $key) wp_cache_delete($key, 'options');
            foreach (array_reverse($locked) as $name) self::unlock($name);
        }
    }

    public static function listing(int $limit = 20): array {
        try {
            $owner = self::owner();
            if (get_option('lcfa_changeset_schema') !== self::VERSION) return ['ok' => true, 'changesets' => []];
            global $wpdb;
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE owner_user_id=%d AND site_hash=%s ORDER BY created_at DESC, id DESC LIMIT %d', $owner, self::site_hash(), max(1, min(50, $limit))), ARRAY_A);
            if (!is_array($rows)) throw new RuntimeException('changeset_read_failed: The changeset index is unavailable.');
            return ['ok' => true, 'changesets' => array_map([self::class, 'summary'], $rows)];
        } catch (Throwable $error) { return self::exception($error); }
    }

    public static function current_saved_target(string $id): int {
        try {
            $record = self::record($id, self::owner());
            if ($record['status'] !== 'saved' || !self::supports($record['action'])) return 0;
            $before = self::open_snapshot($record);
            $current = self::snapshot((int) $record['target_id'], false, array_keys($before['related'] ?? []));
            return hash_equals($record['after_hash'], self::hash($current)) ? (int) $record['target_id'] : 0;
        } catch (Throwable $error) { return 0; }
    }

    public static function undo(array $payload): array {
        if (self::$active || self::$file_active) return self::error('nested_changeset', 'Finish the current write before restoring a changeset.');
        $locked = [];
        $transaction = false;
        $committed = false;
        $record = null;
        $current = null;
        $option_keys = [];
        $observe_option = static function ($key) use (&$option_keys) { $option_keys[(string) $key] = true; };
        $id = 0;
        try {
            $owner = self::owner();
            self::assert_no_transaction();
            $record = self::record((string) ($payload['changeset_id'] ?? ''), $owner);
            self::schema();
            if ($record['action'] === 'write_theme_file') return self::undo_file($payload, $record, $owner);
            if ($record['action'] === 'store_picostrap_bundle') return self::undo_picostrap_bundle($payload, $record, $owner);
            if ($record['action'] === 'save_windpress_cache') return self::undo_windpress_cache($payload, $record, $owner);
            $id = (int) $record['target_id'];
            if (!$id || !user_can($owner, 'edit_post', $id)) throw new RuntimeException('changeset_forbidden: You cannot restore this target.');
            if ((int) ($payload['target_id'] ?? 0) !== $id) throw new RuntimeException('changeset_target_mismatch: Read fresh context for the changeset target.');
            $check = LCFA_Write_Contract::validate($payload, 'undo_changeset');
            if (empty($check['ok'])) return $check;
            if ($record['scope_hash'] !== self::scope($check['context'])) throw new RuntimeException('changeset_scope_changed: The site, theme or canonical roots differ from the saved change.');
            if ($record['status'] === 'undone') return ['ok' => true, 'already_undone' => true, 'changeset' => self::summary($record)];
            if ($record['status'] !== 'saved') throw new RuntimeException('changeset_not_undoable: This change is not in a verified saved state.');
            $before = self::open_snapshot($record);
            $related_ids = array_map('intval', array_keys($before['related'] ?? []));
            $targets = array_merge([$id], $related_ids); sort($targets);
            foreach ($targets as $target) {
                if (!user_can($owner, 'edit_post', $target)) throw new RuntimeException('changeset_forbidden: You cannot restore an affected content target.');
                $locked[] = self::lock('post:' . $target);
            }
            self::query('START TRANSACTION');
            $transaction = true;
            $record = self::record($record['id'], $owner, true);
            if ($record['status'] !== 'saved') throw new RuntimeException('changeset_conflict: Another request changed this changeset.');
            $current = self::snapshot($id, true, $related_ids);
            if (!hash_equals($record['after_hash'], self::hash($current))) throw new RuntimeException('changeset_conflict: Content, metadata or assignments changed after this operation. Nothing was restored.');
            if (!empty($payload['dry_run'])) {
                self::query('ROLLBACK'); $transaction = false;
                return ['ok' => true, 'dry_run' => true, 'target_id' => $id, 'changeset' => self::summary($record), 'restore_action' => $before['post'] ? 'restore' : 'trash_created_content'];
            }
            foreach (array_merge([$before], array_values($before['related'] ?? [])) as $snapshot) {
                if (($snapshot['post']['post_status'] ?? '') !== 'publish') continue;
                $type = get_post_type_object($snapshot['post']['post_type']);
                if (!$type || !user_can($owner, $type->cap->publish_posts)) throw new RuntimeException('changeset_forbidden: Restoring published content requires the target post type publishing permission.');
            }
            self::$active = true;
            add_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
            foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) add_action($hook, $observe_option, PHP_INT_MAX);
            self::restore_post($id, $before, $current);
            foreach ($related_ids as $related) self::restore_post($related, $before['related'][$related], $current['related'][$related]);
            $restored = self::snapshot($id, true, $related_ids);
            if ($before['post'] && self::hash($restored) !== self::hash($before)) throw new RuntimeException('changeset_restore_failed: Restored content did not match the snapshot.');
            if (!$before['post'] && ($restored['post']['post_status'] ?? '') !== 'trash') throw new RuntimeException('changeset_restore_failed: Created content could not be moved to Trash.');
            foreach ($related_ids as $related) if (self::hash($restored['related'][$related]) !== self::hash($before['related'][$related])) throw new RuntimeException('changeset_restore_failed: An affected content target did not match its snapshot.');
            self::update_record($record['id'], ['status' => 'undone']);
            remove_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
            self::$active = false;
            self::query('COMMIT'); $transaction = false;
            $committed = true;
            clean_post_cache($id);
            foreach ($related_ids as $related) clean_post_cache($related);
            return ['ok' => true, 'target_id' => $id, 'changeset' => self::summary(self::record($record['id'], $owner)),
                'restore_action' => $before['post'] ? 'restored' : 'trashed_created_content',
                'verification_states' => ['saved' => true, 'compiled' => 'not_checked', 'visually_verified' => 'not_checked', 'published' => $restored['post']['post_status'] === 'publish']];
        } catch (Throwable $error) {
            remove_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
            self::$active = false;
            $rollback_ok = !$committed && (!$transaction || self::try_query('ROLLBACK'));
            $transaction = false;
            if ($record && $current && $rollback_ok) {
                try { $rollback_ok = self::hash(self::snapshot($id, false, $related_ids)) === self::hash($current); }
                catch (Throwable $ignored) { $rollback_ok = false; }
            }
            if ($record && !$rollback_ok) {
                try { self::update_record($record['id'], ['status' => 'needs_recovery']); }
                catch (Throwable $ignored) { /* The response still reports uncertain recovery. */ }
            }
            $result = self::exception($error);
            $result['transaction'] = ['database_rollback_verified' => $rollback_ok, 'external_side_effects' => 'not_covered'];
            if ($record && !$rollback_ok) $result['changeset'] = ['id' => $record['id'], 'status' => 'needs_recovery', 'undo_available' => false];
            return $result;
        }
        finally {
            remove_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
            self::$active = false;
            foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) remove_action($hook, $observe_option, PHP_INT_MAX);
            if ($transaction) self::try_query('ROLLBACK');
            if ($id) clean_post_cache($id);
            foreach (($related_ids ?? []) as $related) clean_post_cache($related);
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            foreach (array_keys($option_keys) as $key) wp_cache_delete($key, 'options');
            foreach (array_reverse($locked) as $name) self::unlock($name);
        }
    }

    public static function guard_transaction(string $sql): string {
        // A third-party hook must not implicitly commit our content transaction.
        $statement = preg_replace('/\A(?:\s+|\/\*(?![!]).*?\*\/|--[^\r\n]*(?:\r?\n|$)|\#[^\r\n]*(?:\r?\n|$))+/s', '', $sql);
        if (strpos($statement, '/*!') === 0 || preg_match('/\A(?:COMMIT|ROLLBACK|START|BEGIN|SAVEPOINT|RELEASE|ALTER|CREATE|DROP|TRUNCATE|RENAME|LOCK|UNLOCK|SET\s+(?:(?:SESSION|LOCAL)\s+|@@(?:SESSION\.)?)?(?:autocommit|TRANSACTION))\b/is', $statement)) {
            throw new RuntimeException('changeset_transaction_interrupted: A save hook attempted to change transaction state.');
        }
        return $sql;
    }

    private static function unguard(callable $observe): void {
        remove_filter('query', [self::class, 'guard_transaction'], PHP_INT_MAX);
        remove_action('wp_after_insert_post', $observe, PHP_INT_MAX);
        self::$active = false;
    }

    private static function table(): string { global $wpdb; return $wpdb->prefix . 'lcfa_changesets'; }
    private static function site_hash(): string { return hash('sha256', home_url('/') . '|' . get_current_blog_id() . '|' . realpath(ABSPATH)); }
    private static function scope(array $state): string { return self::hash([$state['site'], $state['roots'], $state['theme']['stylesheet'], $state['theme']['template']]); }
    private static function hash(array $value): string { return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR)); }

    private static function schema(): void {
        global $wpdb;
        if (get_option('lcfa_changeset_schema') !== self::VERSION) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = self::table();
            dbDelta("CREATE TABLE $table (
                id varchar(36) NOT NULL,
                owner_user_id bigint(20) unsigned NOT NULL,
                site_hash char(64) NOT NULL,
                scope_hash char(64) NOT NULL,
                action varchar(64) NOT NULL,
                target_id bigint(20) unsigned NOT NULL DEFAULT 0,
                status varchar(32) NOT NULL,
                before_snapshot longtext NOT NULL,
                before_hash char(64) NOT NULL,
                after_hash char(64) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY owner_site (owner_user_id,site_hash),
                KEY target_id (target_id)
            ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) throw new RuntimeException('changeset_storage_unavailable: Private snapshot storage could not be created. No target was written.');
            update_option('lcfa_changeset_schema', self::VERSION, false);
        }
        foreach ([self::table(), $wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->termmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->options] as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
            if (strtolower((string) $engine) !== 'innodb') throw new RuntimeException('changeset_storage_unavailable: Transactional InnoDB tables are required for content Undo. No target was written.');
        }
    }

    private static function assert_no_transaction(): void {
        global $wpdb;
        if ((string) $wpdb->get_var('SELECT @@session.autocommit') !== '1') throw new RuntimeException('changeset_transaction_busy: Another component controls this database transaction.');
        $probe = 'lcfa_probe_' . bin2hex(random_bytes(8));
        self::query('SAVEPOINT ' . $probe);
        $suppressed = $wpdb->suppress_errors(true);
        $active = $wpdb->query('ROLLBACK TO SAVEPOINT ' . $probe) !== false;
        $missing = strpos($wpdb->last_error, 'does not exist') !== false;
        $wpdb->suppress_errors($suppressed);
        $wpdb->last_error = '';
        if ($active) { self::query('RELEASE SAVEPOINT ' . $probe); throw new RuntimeException('changeset_transaction_busy: Leave the existing transaction unchanged and retry outside it.'); }
        if (!$missing) throw new RuntimeException('changeset_transaction_unavailable: Could not verify a clean transaction boundary.');
    }

    private static function snapshot(int $id, bool $lock = false, array $related_ids = []): array {
        $related = [];
        foreach ($related_ids as $related_id) {
            $related[$related_id] = self::snapshot($related_id, $lock);
            if (!$related[$related_id]['post']) throw new RuntimeException('changeset_target_missing: An assigned content target no longer exists.');
        }
        if (!$id) return ['post' => null, 'meta' => [], 'terms' => [], 'related' => $related];
        global $wpdb;
        $suffix = $lock ? ' FOR UPDATE' : '';
        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d" . $suffix, $id), ARRAY_A);
        if ($wpdb->last_error) throw new RuntimeException('changeset_snapshot_failed: Could not read content.');
        $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_key,meta_id" . $suffix, $id), ARRAY_A);
        if (!is_array($meta)) throw new RuntimeException('changeset_snapshot_failed: Could not read content metadata.');
        $terms = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id,term_order FROM {$wpdb->term_relationships} WHERE object_id=%d ORDER BY term_taxonomy_id" . $suffix, $id), ARRAY_A);
        if (!is_array($terms)) throw new RuntimeException('changeset_snapshot_failed: Could not read template assignments.');
        return ['post' => $post, 'meta' => $meta, 'terms' => $terms, 'related' => $related];
    }

    private static function prepare_record(int $owner, int $id, string $action, array $before, string $scope): array {
        global $wpdb;
        $record = ['id' => wp_generate_uuid4(), 'owner_user_id' => $owner, 'site_hash' => self::site_hash(), 'scope_hash' => $scope,
            'action' => $action, 'target_id' => $id, 'status' => 'prepared', 'before_hash' => self::hash($before), 'after_hash' => '',
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')];
        $record['before_snapshot'] = self::seal(json_encode($before, JSON_THROW_ON_ERROR), $record);
        if ($wpdb->insert(self::table(), $record) !== 1) throw new RuntimeException('changeset_snapshot_failed: Could not save the snapshot. No target was written.');
        $verified = self::record($record['id'], $owner);
        if (self::hash(self::open_snapshot($verified)) !== $record['before_hash']) throw new RuntimeException('changeset_snapshot_failed: Snapshot read-back failed. No target was written.');
        return $verified;
    }

    private static function seal(string $plain, array $record): string {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('changeset_crypto_unavailable: OpenSSL is required for private snapshots.');
        $nonce = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, self::aad($record));
        if ($cipher === false) throw new RuntimeException('changeset_snapshot_failed: Snapshot encryption failed.');
        return base64_encode($nonce . $tag . $cipher);
    }

    private static function open_snapshot(array $record): array {
        $sealed = base64_decode($record['before_snapshot'], true);
        if ($sealed === false || strlen($sealed) < 28) throw new RuntimeException('changeset_snapshot_invalid: The private snapshot is damaged.');
        $plain = openssl_decrypt(substr($sealed, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($sealed, 0, 12), substr($sealed, 12, 16), self::aad($record));
        if ($plain === false) throw new RuntimeException('changeset_snapshot_invalid: Snapshot authentication failed. WordPress salt rotation also makes old snapshots unavailable.');
        $snapshot = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || !hash_equals($record['before_hash'], self::hash($snapshot))) throw new RuntimeException('changeset_snapshot_invalid: Snapshot checksum failed.');
        return $snapshot;
    }

    private static function key(): string { return hash('sha256', wp_salt('auth') . '|lcfa-private-changesets-v1', true); }
    private static function aad(array $record): string { return $record['id'] . '|' . $record['owner_user_id'] . '|' . $record['site_hash'] . '|' . $record['scope_hash']; }

    private static function record(string $id, int $owner, bool $lock = false): array {
        global $wpdb;
        if (get_option('lcfa_changeset_schema') !== self::VERSION) throw new RuntimeException('changeset_not_found: Changeset not found for this site and user.');
        if (!preg_match('/\A[a-f0-9-]{36}\z/D', $id)) throw new RuntimeException('changeset_not_found: Changeset not found for this site and user.');
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%s AND owner_user_id=%d AND site_hash=%s' . ($lock ? ' FOR UPDATE' : ''), $id, $owner, self::site_hash()), ARRAY_A);
        if (!is_array($row)) throw new RuntimeException('changeset_not_found: Changeset not found for this site and user.');
        return $row;
    }

    private static function update_record(string $id, array $fields): void {
        global $wpdb;
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        if ($wpdb->update(self::table(), $fields, ['id' => $id]) === false) throw new RuntimeException('changeset_journal_failed: The change journal could not be updated.');
    }

    public static function summary(array $row): array {
        $summary = ['id' => $row['id'], 'action' => $row['action'], 'target_id' => (int) $row['target_id'], 'status' => $row['status'],
            'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'], 'undo_available' => $row['status'] === 'saved'];
        if ($row['action'] === 'write_theme_file') {
            $summary['target_type'] = 'theme_file';
            try { $summary['path'] = self::open_snapshot($row)['path']; }
            catch (Throwable $error) { $summary['undo_available'] = false; $summary['snapshot_available'] = false; }
        }
        if ($row['action'] === 'store_picostrap_bundle') {
            $summary['target_type'] = 'site';
            $summary['asset_type'] = 'picostrap_bundle';
            try { $summary['bundle_path'] = self::open_snapshot($row)['path']; }
            catch (Throwable $error) { $summary['undo_available'] = false; $summary['snapshot_available'] = false; }
        }
        if ($row['action'] === 'save_windpress_cache') {
            $summary['target_type'] = 'site'; $summary['asset_type'] = 'windpress_cache';
            try { $summary['cache_paths'] = self::open_snapshot($row)['target']['paths']; }
            catch (Throwable $error) { $summary['undo_available'] = false; $summary['snapshot_available'] = false; }
        }
        return $summary;
    }

    private static function restore_post(int $id, array $before, array $current): void {
        global $wpdb;
        if (!$before['post']) {
            if ($wpdb->update($wpdb->posts, ['post_status' => 'trash'], ['ID' => $id]) === false) throw new RuntimeException('changeset_restore_failed: Could not move created content to Trash.');
            update_post_meta($id, '_wp_trash_meta_status', $current['post']['post_status']);
            update_post_meta($id, '_wp_trash_meta_time', time());
            self::refresh_term_counts($current['terms']);
            return;
        }
        $data = $before['post']; unset($data['ID']);
        // Restore known stored bytes directly inside Bridge, without re-running
        // KSES under a different execution identity. This accepts no caller HTML.
        if ($wpdb->update($wpdb->posts, $data, ['ID' => $id]) === false) throw new RuntimeException('changeset_restore_failed: Could not restore content.');
        self::query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id=%d", $id));
        foreach ($before['meta'] as $meta) if ($wpdb->insert($wpdb->postmeta, ['post_id' => $id] + $meta) !== 1) throw new RuntimeException('changeset_restore_failed: Could not restore metadata.');
        self::query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE object_id=%d", $id));
        foreach ($before['terms'] as $term) {
            if (!$wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d FOR UPDATE", $term['term_taxonomy_id']))) throw new RuntimeException('changeset_conflict: A saved taxonomy assignment no longer exists.');
            if ($wpdb->insert($wpdb->term_relationships, ['object_id' => $id] + $term) !== 1) throw new RuntimeException('changeset_restore_failed: Could not restore assignments.');
        }
        self::refresh_term_counts(array_merge($before['terms'], $current['terms']));
    }

    private static function refresh_term_counts(array $terms): void {
        global $wpdb;
        foreach (array_unique(array_column($terms, 'term_taxonomy_id')) as $term_id) {
            $taxonomy = $wpdb->get_var($wpdb->prepare("SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $term_id));
            if ($taxonomy) wp_update_term_count_now([(int) $term_id], $taxonomy);
        }
    }

    private static function lock(string $target): string {
        global $wpdb;
        $name = 'lcfa:' . substr(hash('sha256', self::site_hash() . $target), 0, 50);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name)) !== '1') throw new RuntimeException('changeset_busy: Another Bridge write is using this target. Retry with fresh context.');
        return $name;
    }
    private static function unlock(string $name): void { global $wpdb; $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
    private static function query(string $sql): void { if (!self::try_query($sql)) throw new RuntimeException('changeset_database_failed: A database operation failed. Inspect the changeset before retrying.'); }
    private static function try_query(string $sql): bool { global $wpdb; return $wpdb->query($sql) !== false; }
    private static function error(string $code, string $message): array { return ['ok' => false, 'code' => $code, 'message' => $message]; }
    private static function exception(Throwable $error): array {
        $message = $error->getMessage();
        if (preg_match('/\A(changeset_[a-z_]+): (.*)\z/s', $message, $match)) return self::error($match[1], $match[2]);
        return self::error('changeset_failed', 'The change failed. Its journal records whether database rollback completed; external hook side effects are not covered.');
    }
}

final class LCFA_Changeset_Write_Failure extends RuntimeException {
    public array $result;
    public function __construct(array $result) { parent::__construct('Changeset callback failed.'); $this->result = $result; }
}
