<?php
defined('ABSPATH') || exit;

/** Journals the framework's compiled CSS, optional source map and capability evidence. */
trait LCFA_WindPress_Changesets {
    private const WINDPRESS_EVIDENCE = 'lcfa_windpress_compile_evidence';

    public static function run_windpress_cache(string $css, string $sourcemap, ?int $full_build, array $payload, array $plugins): array {
        if (self::$active || self::$file_active) return self::error('nested_changeset', 'Finish the current change before storing a compiled cache.');
        self::$file_active = true;
        $record = null; $locks = []; $target = []; $before = null; $after = null;
        try {
            $owner = self::owner(); self::windpress_permission($owner); self::assert_no_transaction();
            $check = self::windpress_context($payload, 'save_windpress_cache');
            if (empty($check['ok'])) return $check;
            $target = self::windpress_targets();
            $locks[] = self::lock('theme-files:' . $check['context']['roots']['stylesheet']);
            $locks[] = self::lock('windpress-cache:' . $target['root']);
            if (strlen($css) > 8 * 1024 * 1024 || strlen($sourcemap) > 8 * 1024 * 1024) return self::error('compiled_asset_too_large', 'Each compiled asset must be at most 8 MiB.');
            if ($sourcemap !== '') {
                $map = json_decode($sourcemap, true);
                if (!is_array($map) || ($map['version'] ?? null) !== 3 || !is_array($map['sources'] ?? null) || !is_string($map['mappings'] ?? null)) return self::error('source_map_invalid', 'Supply a version 3 source map or leave it empty.');
            }
            self::windpress_sources($payload);
            $before = self::windpress_snapshot($target);
            $after = $before;
            foreach (['css' => $css, 'sourcemap' => $sourcemap] as $key => $bytes) {
                $exists = $key === 'css' || $bytes !== '';
                $after['files'][$key] = ['exists' => $exists, 'content' => $exists ? base64_encode($bytes) : '', 'mode' => $exists ? ($before['files'][$key]['exists'] ? $before['files'][$key]['mode'] : 0644) : 0];
            }
            $evidence = ['source_revision' => $payload['source_revision'], 'cache_path' => $target['root'] . '/' . $target['paths']['css'], 'css_sha256' => hash('sha256', $css), 'plugins' => $plugins];
            $after['evidence'] = ['exists' => true, 'raw' => base64_encode(maybe_serialize($evidence)), 'autoload' => $before['evidence']['exists'] ? $before['evidence']['autoload'] : 'no'];
            if (!empty($payload['dry_run'])) return ['ok' => true, 'dry_run' => true, 'verification_states' => self::file_verification(false)];
            self::schema();
            $record = self::prepare_record($owner, 0, 'save_windpress_cache', ['kind' => 'windpress_cache', 'target' => $target, 'before' => $before, 'after' => $after], self::scope($check['context']));
            self::file_journal($record, ['after_hash' => self::hash($after), 'status' => 'applying']);
            $fresh = self::windpress_context($payload, 'save_windpress_cache');
            if (empty($fresh['ok'])) throw new LCFA_Changeset_Write_Failure($fresh);
            if (self::windpress_targets() !== $target) throw new RuntimeException('changeset_scope_changed: The native cache destination changed.');
            self::windpress_sources($payload);
            self::windpress_replace($target, $before, $after);
            self::windpress_sources($payload);
            do_action('a!windpress/core/cache:save_cache.after', $css);
            if ($sourcemap !== '') do_action('a!windpress/core/cache:save_sourcemap.after', $sourcemap);
            if (class_exists('WindPress\\WindPress\\Utils\\Cache')) \WindPress\WindPress\Utils\Cache::flush_cache_plugin();
            self::windpress_sources($payload);
            if (self::windpress_snapshot($target) !== $after) throw new RuntimeException('changeset_conflict: Compiled artifacts changed before verification.');
            self::file_journal($record, ['status' => 'saved']);
            // This volatile WindPress timing hint is not durable build evidence.
            $warning = '';
            if ($full_build !== null && $full_build > 0) {
                try { wp_cache_set('last_full_build', $full_build, 'windpress'); }
                catch (Throwable $ignored) { $warning = 'Compiled artifacts saved; volatile build timing is unavailable.'; }
            }
            return ['ok' => true, 'message' => 'WindPress compiled cache stored.', 'warning' => $warning,
                'changeset' => self::summary(self::record($record['id'], $owner)), 'rollback_available' => true, 'undo_tool' => 'undo_changeset',
                'verification_states' => array_replace(self::file_verification(true), ['compiled' => 'client_reported']),
                'compilation_evidence' => ['source_revision_verified' => true, 'css_sha256' => hash('sha256', $css), 'plugins' => $plugins, 'compiler' => 'external'],
                'database_filesystem_atomic' => false, 'external_hook_effects' => 'not_covered'];
        } catch (Throwable $error) {
            return self::windpress_failure($error, $record, $target, $before, $after, 'rolled_back');
        } finally { self::$file_active = false; foreach (array_reverse($locks) as $lock) self::unlock($lock); }
    }

    private static function windpress_context(array $payload, string $action): array {
        $check = LCFA_Write_Contract::validate($payload, $action);
        if (empty($check['ok'])) return $check;
        if (($check['context']['pipeline']['strategy'] ?? '') !== 'tailwind-windpress' || empty($check['context']['pipeline']['windpress_active'])) return self::error('windpress_pipeline_required', 'Use the verified active theme pipeline. WindPress cache writes require an active Tailwind/WindPress stack.');
        return $check;
    }

    private static function windpress_permission(int $owner): void {
        if (!user_can($owner, 'manage_options') || !wp_is_file_mod_allowed('lcfa-windpress-cache')) throw new RuntimeException('changeset_forbidden: Compiled cache writes require site administration and enabled file modifications.');
    }

    private static function windpress_sources(array $payload): void {
        if (empty($payload['source_revision']) || !hash_equals(LCFA_Write_Contract::source_revision(), (string) $payload['source_revision'])) throw new LCFA_Changeset_Write_Failure(self::error('stale_sources', 'Sources changed. Compile again with current site context.'));
    }

    private static function windpress_targets(): array {
        $cache = '\\WindPress\\WindPress\\Core\\Cache';
        if (!class_exists($cache) || !defined($cache . '::CSS_SOURCEMAP_FILE')) throw new RuntimeException('changeset_cache_unavailable: The installed cache API does not expose the required destinations.');
        $uploads = wp_upload_dir(null, false);
        $root = realpath($uploads['basedir']);
        if (!empty($uploads['error']) || !$root || wp_normalize_path($root) !== untrailingslashit(wp_normalize_path($uploads['basedir']))) throw new RuntimeException('changeset_cache_unavailable: A canonical uploads root is required.');
        $paths = [];
        foreach (['css' => $cache::CSS_CACHE_FILE, 'sourcemap' => $cache::CSS_SOURCEMAP_FILE] as $key => $file) {
            $absolute = wp_normalize_path($cache::get_cache_path($file));
            if (strpos($absolute, $root . '/') !== 0) throw new RuntimeException('changeset_path_invalid: The native cache destination is outside uploads.');
            $paths[$key] = substr($absolute, strlen($root) + 1);
            self::file_path($root, $paths[$key]);
            if (!preg_match($key === 'css' ? '/\.css$/' : '/\.css\.map$/', $paths[$key])) throw new RuntimeException('changeset_path_invalid: The cache API returned an unexpected artifact type.');
        }
        return ['root' => $root, 'paths' => $paths];
    }

    private static function windpress_snapshot(array $target): array {
        $files = [];
        foreach ($target['paths'] as $key => $path) $files[$key] = self::file_snapshot($target['root'], $path);
        return ['files' => $files, 'evidence' => self::windpress_evidence()];
    }

    private static function windpress_evidence(): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s", self::WINDPRESS_EVIDENCE), ARRAY_A);
        if ($wpdb->last_error) throw new RuntimeException('changeset_metadata_unavailable: Compile evidence could not be read.');
        return $row ? ['exists' => true, 'raw' => base64_encode($row['option_value']), 'autoload' => $row['autoload']] : ['exists' => false, 'raw' => '', 'autoload' => ''];
    }

    private static function windpress_replace_evidence(array $expected, array $desired): void {
        global $wpdb;
        if (self::windpress_evidence() !== $expected) throw new RuntimeException('changeset_conflict: Compile evidence changed during the operation.');
        if ($expected === $desired) return;
        if (!$expected['exists']) {
            $count = $wpdb->insert($wpdb->options, ['option_name' => self::WINDPRESS_EVIDENCE, 'option_value' => base64_decode($desired['raw']), 'autoload' => $desired['autoload']]);
        } else {
            $where = $wpdb->prepare(' WHERE option_name=%s AND BINARY option_value=BINARY %s AND autoload=%s', self::WINDPRESS_EVIDENCE, base64_decode($expected['raw']), $expected['autoload']);
            $sql = $desired['exists'] ? $wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s,autoload=%s", base64_decode($desired['raw']), $desired['autoload']) : "DELETE FROM {$wpdb->options}";
            $count = $wpdb->query($sql . $where);
        }
        wp_cache_delete(self::WINDPRESS_EVIDENCE, 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
        if ($count !== 1 || self::windpress_evidence() !== $desired) throw new RuntimeException('changeset_metadata_write_failed: Compile evidence could not be stored and verified.');
    }

    private static function windpress_replace(array $target, array $expected, array $desired): void {
        foreach ($target['paths'] as $key => $path) self::file_replace($target['root'], $path, $expected['files'][$key], $desired['files'][$key], true);
        self::windpress_replace_evidence($expected['evidence'], $desired['evidence']);
    }

    private static function undo_windpress_cache(array $payload, array $record, int $owner): array {
        self::$file_active = true;
        $locks = []; $target = []; $started = false; $current = null; $desired = null;
        try {
            self::windpress_permission($owner);
            $check = self::windpress_context($payload, 'undo_windpress_cache');
            if (empty($check['ok'])) return $check;
            if ($record['scope_hash'] !== self::scope($check['context'])) throw new RuntimeException('changeset_scope_changed: The site or active theme roots changed.');
            $snapshot = self::open_snapshot($record);
            $target = self::windpress_targets();
            if ($snapshot['target'] !== $target) throw new RuntimeException('changeset_scope_changed: The cache destinations changed.');
            $locks[] = self::lock('theme-files:' . $check['context']['roots']['stylesheet']);
            $locks[] = self::lock('windpress-cache:' . $target['root']);
            $record = self::record($record['id'], $owner);
            if ($record['status'] === 'undone') return ['ok' => true, 'already_undone' => true, 'changeset' => self::summary($record)];
            if ($record['status'] !== 'saved') throw new RuntimeException('changeset_not_undoable: Inspect the cache and private journal before retrying.');
            $current = self::windpress_snapshot($target); $desired = $snapshot['before'];
            if (!hash_equals($record['after_hash'], self::hash($current))) throw new RuntimeException('changeset_conflict: CSS, source map or compilation evidence changed. Nothing was restored.');
            if (!empty($payload['dry_run'])) return ['ok' => true, 'dry_run' => true, 'changeset' => self::summary($record), 'verification_states' => self::file_verification(false)];
            self::file_journal($record, ['status' => 'undoing']); $started = true;
            $fresh = self::windpress_context($payload, 'undo_windpress_cache');
            if (empty($fresh['ok'])) throw new LCFA_Changeset_Write_Failure($fresh);
            self::windpress_replace($target, $current, $desired);
            if (self::windpress_snapshot($target) !== $desired) throw new RuntimeException('changeset_conflict: Restored cache changed before verification.');
            self::file_journal($record, ['status' => 'undone']);
            return ['ok' => true, 'changeset' => self::summary(self::record($record['id'], $owner)), 'verification_states' => self::file_verification(true),
                'restored' => ['css' => true, 'sourcemap' => true, 'compilation_evidence' => true], 'database_filesystem_atomic' => false,
                'warning' => 'Cache bytes restored. Recheck current source compatibility and browser/CDN cache freshness.'];
        } catch (Throwable $error) {
            return self::windpress_failure($error, $started ? $record : null, $target, $current, $desired, 'saved');
        } finally { self::$file_active = false; foreach (array_reverse($locks) as $lock) self::unlock($lock); }
    }

    private static function windpress_failure(Throwable $error, ?array $record, array $target, ?array $original, ?array $attempted, string $recovered_status): array {
        $restored = false;
        if ($record) {
            try {
                foreach (array_reverse($target['paths'], true) as $key => $path) {
                    if (self::file_snapshot($target['root'], $path) === $attempted['files'][$key]) self::file_replace($target['root'], $path, $attempted['files'][$key], $original['files'][$key], false);
                }
                if (self::windpress_evidence() === $attempted['evidence']) self::windpress_replace_evidence($attempted['evidence'], $original['evidence']);
                $restored = self::windpress_snapshot($target) === $original;
                self::file_journal($record, ['status' => $restored ? $recovered_status : 'needs_recovery']);
            } catch (Throwable $ignored) {
                $restored = false;
                try { self::file_journal($record, ['status' => 'needs_recovery']); } catch (Throwable $ignored) { /* Preserve uncertain state for administrator review. */ }
            }
        }
        $result = $error instanceof LCFA_Changeset_Write_Failure ? $error->result : self::exception($error);
        if ($record) {
            $result['changeset'] = ['id' => $record['id'], 'status' => $restored ? $recovered_status : 'needs_recovery', 'undo_available' => $restored && $recovered_status === 'saved'];
            $result['recovery'] = ['cache_and_evidence_compensation_verified' => $restored, 'database_filesystem_atomic' => false, 'external_hook_effects' => 'not_covered'];
        }
        return $result;
    }
}
