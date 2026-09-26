<?php
defined('ABSPATH') || exit;

/** Files share the private journal, but cannot share a database transaction. */
trait LCFA_File_Changesets {
    private static bool $file_active = false;

    public static function run_file(array $payload): array {
        if (self::$active || self::$file_active) return self::error('nested_changeset', 'Finish the current change before writing a file.');
        self::$file_active = true;
        $record = null; $lock = null; $before = null; $after = null; $root = ''; $path = '';
        try {
            $owner = self::owner();
            self::file_permission($owner);
            self::assert_no_transaction();
            $check = LCFA_Write_Contract::validate($payload, 'write_theme_file');
            if (empty($check['ok'])) return $check;
            $root = $check['context']['roots']['stylesheet'];
            $path = (string) ($payload['path'] ?? '');
            // Serialize source changes in this child theme, including new path
            // aliases on case-insensitive filesystems.
            $lock = self::lock('theme-files:' . $root);
            $before = self::file_snapshot($root, $path);
            if (($before['exists'] ? hash('sha256', base64_decode($before['content'])) : '') !== $check['context']['target']['file_sha256']) {
                throw new RuntimeException('changeset_conflict: The file changed after context validation.');
            }
            $content = (string) ($payload['content'] ?? '');
            if (strlen($content) > 8 * 1024 * 1024) throw new RuntimeException('changeset_file_too_large: Theme files must be at most 8 MiB.');
            $after = ['exists' => true, 'content' => base64_encode($content), 'mode' => $before['exists'] ? $before['mode'] : 0644];
            $changed = $before !== $after;
            $result = ['ok' => true, 'dry_run' => !empty($payload['dry_run']), 'writable' => true,
                'root_scope' => 'stylesheet', 'root' => 'stylesheet', 'theme' => $check['context']['theme']['stylesheet'],
                'relative_path' => $path, 'absolute_path' => $root . '/' . $path, 'created' => !$before['exists'], 'changed' => $changed,
                'bytes_before' => strlen(base64_decode($before['content'])), 'bytes_after' => strlen($content),
                'checksum_before' => $before['exists'] ? hash('sha256', base64_decode($before['content'])) : '',
                'checksum_after' => hash('sha256', $content), 'rollback_available' => false,
                'verification_states' => self::file_verification(false)];
            if (!empty($payload['dry_run']) || !$changed) {
                $result['exists'] = $before['exists'];
                $result['verification_states']['saved'] = empty($payload['dry_run']);
                return $result;
            }
            self::schema();
            // Store both states privately. The after state also makes removal of
            // a newly created file recoverable without a public backup copy.
            $record = self::prepare_record($owner, 0, 'write_theme_file', ['kind' => 'theme_file', 'path' => $path, 'before' => $before, 'after' => $after], self::scope($check['context']));
            self::file_journal($record, ['after_hash' => self::hash($after), 'status' => 'applying']);
            $fresh = LCFA_Write_Contract::validate($payload, 'write_theme_file');
            if (empty($fresh['ok'])) throw new LCFA_Changeset_Write_Failure($fresh);
            self::file_replace($root, $path, $before, $after, ($payload['create_directories'] ?? true) !== false);
            self::file_journal($record, ['status' => 'saved']);
            $result['exists'] = true;
            $result['changeset'] = self::summary(self::record($record['id'], $owner));
            $result['rollback_available'] = true;
            $result['undo_tool'] = 'undo_changeset';
            $result['verification_states'] = self::file_verification(true);
            return $result;
        } catch (Throwable $error) {
            return self::file_failure($error, $record, $root, $path, $before, $after, 'rolled_back');
        } finally { self::$file_active = false; if ($lock) self::unlock($lock); }
    }

    private static function undo_file(array $payload, array $record, int $owner): array {
        self::$file_active = true;
        $lock = null; $started = false; $current = null; $desired = null; $root = ''; $path = '';
        try {
            self::file_permission($owner);
            $snapshot = self::open_snapshot($record);
            $path = (string) $snapshot['path'];
            if (($payload['path'] ?? '') !== $path || !empty($payload['target_id'])) throw new RuntimeException('changeset_target_mismatch: Inspect the exact child-theme path for this changeset.');
            // Validate restored markup too, not optional caller-supplied HTML.
            $payload['content'] = base64_decode($snapshot['before']['content']);
            $check = LCFA_Write_Contract::validate($payload, 'undo_changeset');
            if (empty($check['ok'])) return $check;
            if ($record['scope_hash'] !== self::scope($check['context'])) throw new RuntimeException('changeset_scope_changed: The site, theme or canonical roots differ from the saved change.');
            $root = $check['context']['roots']['stylesheet'];
            $lock = self::lock('theme-files:' . $root);
            $record = self::record($record['id'], $owner);
            if ($record['status'] === 'undone') return ['ok' => true, 'already_undone' => true, 'changeset' => self::summary($record)];
            if ($record['status'] !== 'saved') throw new RuntimeException('changeset_not_undoable: Inspect the file and recovery journal before retrying this change.');
            $current = self::file_snapshot($root, $path);
            $desired = $snapshot['before'];
            if (!hash_equals($record['after_hash'], self::hash($current))) throw new RuntimeException('changeset_conflict: The file contents or permissions changed after this operation. Nothing was restored.');
            $result = ['ok' => true, 'path' => $path, 'dry_run' => !empty($payload['dry_run']),
                'restore_action' => $desired['exists'] ? 'restore_file' : 'remove_created_file',
                'verification_states' => self::file_verification(false)];
            if (!empty($payload['dry_run'])) return $result + ['changeset' => self::summary($record)];
            // Persist intent before changing the filesystem. Crashes are never
            // misreported as completed database transactions or auto-replayed.
            self::file_journal($record, ['status' => 'undoing']); $started = true;
            self::file_replace($root, $path, $current, $desired, false);
            self::file_journal($record, ['status' => 'undone']);
            $result['changeset'] = self::summary(self::record($record['id'], $owner));
            $result['verification_states'] = self::file_verification(true);
            $result['removed_file_recoverable_from_private_journal'] = !$desired['exists'];
            return $result;
        } catch (Throwable $error) {
            return self::file_failure($error, $started ? $record : null, $root, $path, $current, $desired, 'saved');
        } finally { self::$file_active = false; if ($lock) self::unlock($lock); }
    }

    private static function file_permission(int $owner): void {
        if (!user_can($owner, 'edit_themes') || (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) || !wp_is_file_mod_allowed('lcfa-theme-file')) {
            throw new RuntimeException('changeset_forbidden: Theme-file changes require an authorized theme editor and WordPress file modifications to be enabled.');
        }
    }

    private static function file_path(string $root, string $path): string {
        if (realpath($root) !== $root || $path === '' || preg_match('~(^/|\\\\|[:\x00-\x1f]|(^|/)\.{1,2}(/|$)|//)~', $path) || substr($path, -1) === '/') throw new RuntimeException('changeset_path_invalid: Use a canonical child root and an exact relative file path.');
        if (!preg_match('/\.(css|html|js|json|latte|map|md|php|scss|twig|txt|xml|yml|yaml)$/i', $path) || preg_match('~(^|/)(\.git|\.github|\.lcfa-backups|node_modules|vendor)(/|$)|^public/build(/|$)~', $path)) throw new RuntimeException('changeset_path_invalid: This path is not a writable source or compiled artifact.');
        $absolute = $root;
        foreach (explode('/', $path) as $part) {
            $absolute .= '/' . $part;
            clearstatcache(true, $absolute);
            if (is_link($absolute)) throw new RuntimeException('changeset_path_invalid: Symlinks are not writable through the file journal.');
            if (file_exists($absolute) && realpath($absolute) !== $absolute) throw new RuntimeException('changeset_path_invalid: Use the exact canonical spelling of this theme path.');
            if (file_exists($absolute) && $absolute !== $root . '/' . $path && !is_dir($absolute)) throw new RuntimeException('changeset_path_invalid: A path component is not a directory.');
        }
        return $absolute;
    }

    private static function file_snapshot(string $root, string $path): array {
        $absolute = self::file_path($root, $path);
        if (!file_exists($absolute)) return ['exists' => false, 'content' => '', 'mode' => 0];
        $stat = lstat($absolute);
        if (!$stat || !is_file($absolute) || $stat['nlink'] !== 1 || $stat['size'] > 8 * 1024 * 1024 || ($stat['mode'] & 07000)) throw new RuntimeException('changeset_file_unsupported: Only ordinary theme files up to 8 MiB can be journaled.');
        $bytes = @file_get_contents($absolute);
        if ($bytes === false) throw new RuntimeException('changeset_snapshot_failed: The file could not be read.');
        self::file_path($root, $path);
        $verified = lstat($absolute);
        $stable_keys = array_flip(['dev', 'ino', 'mode', 'nlink', 'size', 'mtime', 'ctime']);
        if (!$verified || array_intersect_key($stat, $stable_keys) !== array_intersect_key($verified, $stable_keys) || hash_file('sha256', $absolute) !== hash('sha256', $bytes)) throw new RuntimeException('changeset_conflict: The file changed while its snapshot was read.');
        return ['exists' => true, 'content' => base64_encode($bytes), 'mode' => $stat['mode'] & 0777];
    }

    private static function file_replace(string $root, string $path, array $expected, array $desired, bool $create_directories): void {
        $absolute = self::file_path($root, $path);
        $staged = null; $staging_dir = null;
        try {
            if ($desired['exists']) {
                if (!is_dir(dirname($absolute)) && (!$create_directories || !wp_mkdir_p(dirname($absolute)))) throw new RuntimeException('changeset_file_write_failed: The destination directory is unavailable.');
                self::file_path($root, $path);
                // Never stage plaintext in the theme or uploads directory.
                $temp = realpath(sys_get_temp_dir());
                foreach (array_filter([realpath(ABSPATH), realpath(WP_CONTENT_DIR), $root, empty($_SERVER['DOCUMENT_ROOT']) ? false : realpath($_SERVER['DOCUMENT_ROOT'])]) as $public) {
                    if (!$temp || $temp === $public || strpos($temp, $public . '/') === 0) throw new RuntimeException('changeset_staging_unavailable: A private temporary directory outside the web root is required.');
                }
                if (!$temp || stat($temp)['dev'] !== stat(dirname($absolute))['dev']) throw new RuntimeException('changeset_staging_unavailable: Private staging must share the theme filesystem for atomic replacement.');
                $private_dir = $temp . '/lcfa-stage-' . bin2hex(random_bytes(16));
                if (!@mkdir($private_dir, 0700)) throw new RuntimeException('changeset_staging_unavailable: Could not create a private staging directory.');
                $staging_dir = $private_dir;
                $staged = $staging_dir . '/payload';
                $bytes = base64_decode($desired['content'], true);
                $stream = @fopen($staged, 'x+b');
                if (!$stream) throw new RuntimeException('changeset_file_write_failed: Could not open the staging file.');
                try {
                    $offset = 0;
                    while ($offset < strlen($bytes)) {
                        $count = fwrite($stream, substr($bytes, $offset));
                        if (!$count) throw new RuntimeException('changeset_file_write_failed: Could not finish the staging write.');
                        $offset += $count;
                    }
                    if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) throw new RuntimeException('changeset_file_write_failed: Could not flush the staging file.');
                } finally { fclose($stream); }
                if (hash_file('sha256', $staged) !== hash('sha256', $bytes) || !chmod($staged, $desired['mode'])) throw new RuntimeException('changeset_file_write_failed: Staging verification failed.');
            }
            if (self::file_snapshot($root, $path) !== $expected) throw new RuntimeException('changeset_conflict: The file changed while preparing this operation.');
            if ($desired['exists']) {
                if (!@rename($staged, $absolute)) throw new RuntimeException('changeset_file_write_failed: Atomic file replacement failed.');
                $staged = null;
            } elseif ($expected['exists'] && !@unlink($absolute)) throw new RuntimeException('changeset_file_write_failed: Could not remove the unchanged created file.');
            if (function_exists('opcache_invalidate')) @opcache_invalidate($absolute, true);
            if (self::file_snapshot($root, $path) !== $desired) throw new RuntimeException('changeset_file_write_failed: The resulting file does not match the intended state.');
        } finally {
            if ($staged && is_file($staged)) @unlink($staged);
            if ($staging_dir) @rmdir($staging_dir);
        }
    }

    private static function file_journal(array $record, array $fields): void {
        self::update_record($record['id'], $fields);
        $verified = self::record($record['id'], (int) $record['owner_user_id']);
        foreach ($fields as $key => $value) if ($verified[$key] !== $value) throw new RuntimeException('changeset_journal_failed: Journal read-back failed.');
    }

    private static function file_failure(Throwable $error, ?array $record, string $root, string $path, ?array $original, ?array $attempted, string $recovered_status): array {
        $restored = false;
        if ($record) {
            try {
                $current = self::file_snapshot($root, $path);
                if ($current === $attempted) self::file_replace($root, $path, $attempted, $original, false);
                $restored = self::file_snapshot($root, $path) === $original;
                self::file_journal($record, ['status' => $restored ? $recovered_status : 'needs_recovery']);
            } catch (Throwable $ignored) {
                $restored = false;
                try { self::file_journal($record, ['status' => 'needs_recovery']); } catch (Throwable $ignored) { /* Report uncertain state even if the journal is unavailable. */ }
            }
        }
        $result = $error instanceof LCFA_Changeset_Write_Failure ? $error->result : self::exception($error);
        if ($record) {
            $result['changeset'] = ['id' => $record['id'], 'status' => $restored ? $recovered_status : 'needs_recovery', 'undo_available' => $restored && $recovered_status === 'saved'];
            $result['recovery'] = ['file_compensation_verified' => $restored, 'database_filesystem_atomic' => false];
        }
        return $result;
    }

    private static function file_verification(bool $saved): array {
        return ['saved' => $saved, 'compiled' => 'not_checked', 'visually_verified' => 'not_checked', 'published' => 'not_applicable', 'may_affect_live_rendering' => true];
    }
}
