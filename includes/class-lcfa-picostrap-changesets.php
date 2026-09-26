<?php
defined('ABSPATH') || exit;

/** One compiled bundle and its three synchronization fields; not a general transaction. */
trait LCFA_Picostrap_Changesets {
    private const PICOSTRAP_MODS = ['css_bundle_version_number', 'lcfa_picostrap_compiled_source_fingerprint', 'lcfa_picostrap_compiled_at'];

    public static function run_picostrap_bundle(string $css, array $payload): array {
        if (self::$active || self::$file_active) return self::error('nested_changeset', 'Finish the current change before storing a compiled bundle.');
        self::$file_active = true;
        $record = null; $lock = null; $before = null; $after = null; $root = ''; $path = ''; $option = '';
        try {
            $owner = self::owner();
            self::file_permission($owner);
            self::assert_no_transaction();
            $check = LCFA_Write_Contract::validate($payload, 'store_picostrap_bundle');
            if (empty($check['ok'])) return $check;
            if ($check['context']['framework'] !== 'picostrap' || empty($check['context']['theme']['is_child_theme'])) return self::error('picostrap_child_required', 'Compiled Picostrap assets require an active Picostrap child theme.');
            if (trim($css) === '' || strlen($css) > 8 * 1024 * 1024) return self::error('compiled_css_required', 'Supply non-empty compiled CSS up to 8 MiB.');
            // Expanded Bootstrap output includes Sass examples in comments.
            // Inspect executable text; comments do not make a compiled bundle stale.
            $css_code = preg_replace('~/\*[\s\S]*?\*/~', '', $css);
            if (strpos($css_code, '{') === false || preg_match('/(?:^|[;{}])\s*@(import|use|forward|mixin|include|tailwind|plugin)\b|<\s*\/?\s*(style|script)\b/i', $css_code)) return self::error('compiled_css_required', 'Supply compiled CSS without unresolved imports, Sass or document wrappers.');
            if (!empty($payload['source_path']) || array_key_exists('source_content', $payload)) return self::error('granular_write_required', 'Write the inspected Sass source separately, then read a new manifest and site context before compiling.');
            $root = $check['context']['roots']['stylesheet'];
            $option = 'theme_mods_' . $check['context']['theme']['stylesheet'];
            $lock = self::lock('theme-files:' . $root);
            $manifest = self::picostrap_manifest($payload);
            $path = $manifest['target_bundle_theme_path'];
            $before = self::picostrap_snapshot($root, $path, $option);
            $after = ['file' => ['exists' => true, 'content' => base64_encode($css), 'mode' => $before['file']['exists'] ? $before['file']['mode'] : 0644], 'mods' => $before['mods']];
            $version = max(0, (int) $before['mods']['css_bundle_version_number']['value']) + 1;
            $values = [$version, $manifest['source_fingerprint'], current_time('mysql', true)];
            foreach (self::PICOSTRAP_MODS as $i => $key) $after['mods'][$key] = ['exists' => true, 'value' => $values[$i]];
            if (!empty($payload['dry_run'])) return ['ok' => true, 'dry_run' => true, 'verification_states' => self::file_verification(false)];
            self::schema();
            $record = self::prepare_record($owner, 0, 'store_picostrap_bundle', ['kind' => 'picostrap_bundle', 'path' => $path, 'before' => $before, 'after' => $after], self::scope($check['context']));
            self::file_journal($record, ['after_hash' => self::hash($after), 'status' => 'applying']);
            $fresh = LCFA_Write_Contract::validate($payload, 'store_picostrap_bundle');
            if (empty($fresh['ok'])) throw new LCFA_Changeset_Write_Failure($fresh);
            if (self::picostrap_manifest($payload)['target_bundle_theme_path'] !== $path) throw new RuntimeException('changeset_conflict: The compiled bundle destination changed.');
            self::file_replace($root, $path, $before['file'], $after['file'], true);
            self::picostrap_replace_mods($option, $before['mods'], $after['mods']);
            // The bundle and evidence are verified together. Filesystem and SQL
            // are not crash-atomic; an unfinished journal requires inspection.
            if (self::picostrap_snapshot($root, $path, $option) !== $after) throw new RuntimeException('changeset_conflict: Compiled assets changed during storage.');
            self::picostrap_manifest($payload);
            self::file_journal($record, ['status' => 'saved']);
            return ['ok' => true, 'bundle_path' => $root . '/' . $path,
                'bundle_url' => trailingslashit(get_stylesheet_directory_uri()) . $path . '?ver=' . $version,
                'bundle_version' => $version, 'source_fingerprint' => $manifest['source_fingerprint'], 'compiled_at' => $values[2],
                'changeset' => self::summary(self::record($record['id'], $owner)), 'rollback_available' => true, 'undo_tool' => 'undo_changeset',
                'verification_states' => array_replace(self::file_verification(true), ['compiled' => 'client_reported']),
                'compilation_evidence' => ['source_fingerprint_verified' => true, 'css_sha256' => hash('sha256', $css), 'compiler' => 'external'],
                'database_filesystem_atomic' => false];
        } catch (Throwable $error) {
            return self::picostrap_failure($error, $record, $root, $path, $option, $before, $after, 'rolled_back');
        } finally { self::$file_active = false; if ($lock) self::unlock($lock); }
    }

    private static function picostrap_manifest(array $payload): array {
        $manifest = (new LCFA_Picostrap_Compile_Manifest(new LCFA_Environment()))->build();
        $fingerprint = (string) ($payload['source_fingerprint'] ?? '');
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint) || !hash_equals($manifest['source_fingerprint'], $fingerprint)) throw new LCFA_Changeset_Write_Failure(self::error('stale_compilation', 'The compiled source fingerprint is missing or stale. Read the current manifest and compile again.'));
        return $manifest;
    }

    private static function undo_picostrap_bundle(array $payload, array $record, int $owner): array {
        self::$file_active = true;
        $lock = null; $started = false; $root = ''; $path = ''; $option = ''; $current = null; $desired = null;
        try {
            self::file_permission($owner);
            $check = LCFA_Write_Contract::validate($payload, 'undo_picostrap_bundle');
            if (empty($check['ok'])) return $check;
            if ($record['scope_hash'] !== self::scope($check['context']) || $check['context']['framework'] !== 'picostrap' || empty($check['context']['theme']['is_child_theme'])) throw new RuntimeException('changeset_scope_changed: The site, framework or theme roots differ from the saved bundle.');
            $snapshot = self::open_snapshot($record);
            $manifest = (new LCFA_Picostrap_Compile_Manifest(new LCFA_Environment()))->build();
            $path = $snapshot['path'];
            if ($path !== $manifest['target_bundle_theme_path']) throw new RuntimeException('changeset_target_mismatch: The active bundle destination differs from this changeset.');
            $root = $check['context']['roots']['stylesheet'];
            $option = 'theme_mods_' . $check['context']['theme']['stylesheet'];
            $lock = self::lock('theme-files:' . $root);
            $record = self::record($record['id'], $owner);
            if ($record['status'] === 'undone') return ['ok' => true, 'already_undone' => true, 'changeset' => self::summary($record)];
            if ($record['status'] !== 'saved') throw new RuntimeException('changeset_not_undoable: Inspect the compiled assets and private journal before retrying.');
            $current = self::picostrap_snapshot($root, $path, $option);
            $desired = $snapshot['before'];
            if (!hash_equals($record['after_hash'], self::hash($current))) throw new RuntimeException('changeset_conflict: The bundle or compilation metadata changed. Nothing was restored.');
            if (!empty($payload['dry_run'])) return ['ok' => true, 'dry_run' => true, 'changeset' => self::summary($record), 'verification_states' => self::file_verification(false)];
            self::file_journal($record, ['status' => 'undoing']); $started = true;
            $fresh = LCFA_Write_Contract::validate($payload, 'undo_picostrap_bundle');
            if (empty($fresh['ok'])) throw new LCFA_Changeset_Write_Failure($fresh);
            self::file_replace($root, $path, $current['file'], $desired['file'], false);
            self::picostrap_replace_mods($option, $current['mods'], $desired['mods']);
            if (self::picostrap_snapshot($root, $path, $option) !== $desired) throw new RuntimeException('changeset_conflict: Restored assets changed before verification.');
            self::file_journal($record, ['status' => 'undone']);
            return ['ok' => true, 'changeset' => self::summary(self::record($record['id'], $owner)), 'verification_states' => self::file_verification(true),
                'restored' => ['bundle' => true, 'compilation_metadata' => true], 'database_filesystem_atomic' => false];
        } catch (Throwable $error) {
            return self::picostrap_failure($error, $started ? $record : null, $root, $path, $option, $current, $desired, 'saved');
        } finally { self::$file_active = false; if ($lock) self::unlock($lock); }
    }

    private static function picostrap_option(string $option): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $option), ARRAY_A);
        if ($wpdb->last_error || !$row) throw new RuntimeException('changeset_metadata_unavailable: The active theme metadata option must exist and be readable.');
        // Do not execute serialized object wakeup methods in theme metadata.
        $mods = is_serialized($row['option_value']) ? @unserialize($row['option_value'], ['allowed_classes' => false]) : null;
        if (!is_array($mods) || preg_match('/(?:^|[;{}])(?:O|C):\d+:/', $row['option_value'])) throw new RuntimeException('changeset_metadata_unavailable: Theme metadata must be a plain serialized array.');
        return ['raw' => $row['option_value'], 'mods' => $mods];
    }

    private static function picostrap_select_mods(array $mods): array {
        $selected = [];
        foreach (self::PICOSTRAP_MODS as $key) $selected[$key] = ['exists' => array_key_exists($key, $mods), 'value' => $mods[$key] ?? null];
        return $selected;
    }

    private static function picostrap_snapshot(string $root, string $path, string $option): array {
        return ['file' => self::file_snapshot($root, $path), 'mods' => self::picostrap_select_mods(self::picostrap_option($option)['mods'])];
    }

    private static function picostrap_replace_mods(string $option, array $expected, array $desired): void {
        global $wpdb;
        $current = self::picostrap_option($option);
        if (self::picostrap_select_mods($current['mods']) !== $expected) throw new RuntimeException('changeset_conflict: Compilation metadata changed during the operation.');
        if ($expected === $desired) return;
        foreach (self::PICOSTRAP_MODS as $key) {
            if ($desired[$key]['exists']) $current['mods'][$key] = $desired[$key]['value'];
            else unset($current['mods'][$key]);
        }
        // CAS preserves every unrelated theme mod and refuses concurrent writes.
        // Deliberately bypass option hooks: this restores known stored metadata,
        // not arbitrary Customizer input or executable user-provided options.
        $count = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s", maybe_serialize($current['mods']), $option, $current['raw']));
        wp_cache_delete($option, 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
        if ($count !== 1 || self::picostrap_select_mods(self::picostrap_option($option)['mods']) !== $desired) throw new RuntimeException('changeset_metadata_write_failed: Compilation metadata could not be verified.');
    }

    private static function picostrap_failure(Throwable $error, ?array $record, string $root, string $path, string $option, ?array $original, ?array $attempted, string $recovered_status): array {
        $restored = false;
        if ($record) {
            try {
                $current = self::picostrap_snapshot($root, $path, $option);
                if ($current['file'] === $attempted['file']) self::file_replace($root, $path, $attempted['file'], $original['file'], false);
                if ($current['mods'] === $attempted['mods']) self::picostrap_replace_mods($option, $attempted['mods'], $original['mods']);
                $restored = self::picostrap_snapshot($root, $path, $option) === $original;
                self::file_journal($record, ['status' => $restored ? $recovered_status : 'needs_recovery']);
            } catch (Throwable $ignored) {
                $restored = false;
                try { self::file_journal($record, ['status' => 'needs_recovery']); } catch (Throwable $ignored) { /* Report uncertainty; never overwrite an external edit. */ }
            }
        }
        $result = $error instanceof LCFA_Changeset_Write_Failure ? $error->result : self::exception($error);
        if ($record) {
            $result['changeset'] = ['id' => $record['id'], 'status' => $restored ? $recovered_status : 'needs_recovery', 'undo_available' => $restored && $recovered_status === 'saved'];
            $result['recovery'] = ['bundle_and_metadata_compensation_verified' => $restored, 'database_filesystem_atomic' => false];
        }
        return $result;
    }
}
