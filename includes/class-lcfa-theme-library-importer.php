<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Importer {
    private const IMPORTS_OPTION = 'lcfa_theme_library_imports';

    private LCFA_Theme_Library_Installer $installer;
    private LCFA_Theme_Library_Validator $validator;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private ?LCFA_Design_System_Build_Gateway $build_gateway;
    private ?LCFA_Theme_Library_Rollback $rollback_service;

    public function __construct(LCFA_Theme_Library_Installer $installer, LCFA_Theme_Library_Validator $validator, LCFA_WindPress_Bridge $windpress_bridge, ?LCFA_Design_System_Build_Gateway $build_gateway = null, ?LCFA_Theme_Library_Rollback $rollback_service = null) {
        $this->installer = $installer;
        $this->validator = $validator;
        $this->windpress_bridge = $windpress_bridge;
        $this->build_gateway = $build_gateway;
        $this->rollback_service = $rollback_service;
    }

    public function import(array $theme, bool $force = false, ?bool $auto_rollback = null): array {
        $download = $this->installer->download_theme_zip($theme);
        if (empty($download['ok'])) {
            return $download;
        }

        $zip_path = (string) $download['zip_path'];
        $validation = $this->validator->validate_zip($zip_path, $theme);
        if (empty($validation['ok'])) {
            $this->delete_file($zip_path);
            return $validation;
        }

        $manifest = is_array($validation['manifest'] ?? null) ? $validation['manifest'] : [];
        $slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? ''));
        $version = sanitize_text_field((string) ($manifest['theme']['version'] ?? $theme['version'] ?? ''));
        $checksum = (string) ($validation['checksum'] ?? '');
        $import_key = $slug . ':' . $version . ':' . $checksum;

        $imports = $this->get_imports();
        $existing_import = is_array($imports[$slug] ?? null) ? $imports[$slug] : [];
        $existing_status = (string) ($existing_import['status'] ?? 'imported');
        if (!$force && $existing_import && in_array($existing_status, ['ready', 'ready_degraded', 'imported'], true) && (string) ($existing_import['import_key'] ?? '') === $import_key) {
            $this->delete_file($zip_path);
            return [
                'ok'       => true,
                'status'   => 'already_imported',
                'message'  => __('This Theme Library item is already imported at the same version and checksum.', 'livecanvas-forge-ai'),
                'import'   => $existing_import,
                'manifest' => $manifest,
            ];
        }

        if (!$force && $existing_import && in_array($existing_status, ['build_required', 'build_failed'], true) && (string) ($existing_import['import_key'] ?? '') === $import_key) {
            $this->delete_file($zip_path);
            return [
                'ok'       => true,
                'ready'    => false,
                'status'   => $existing_status,
                'message'  => __('Starter data is already imported. Complete the pending Tailwind CSS build instead of importing it again.', 'livecanvas-forge-ai'),
                'import'   => $existing_import,
                'build'    => is_array($existing_import['build'] ?? null) ? $existing_import['build'] : [],
                'manifest' => $manifest,
            ];
        }

        $stylesheet = sanitize_key((string) ($manifest['theme']['stylesheet'] ?? $slug));
        $stylesheet = $this->resolve_installed_stylesheet($stylesheet, $manifest, $theme);
        if ($stylesheet !== '' && !wp_get_theme($stylesheet)->exists()) {
            $this->delete_file($zip_path);
            return [
                'ok'      => false,
                'message' => __('Install the child theme before importing starter data.', 'livecanvas-forge-ai'),
            ];
        }

        $destination = trailingslashit(get_temp_dir()) . 'lcfa-theme-library-' . wp_generate_password(8, false, false);
        $extract = $this->validator->extract_zip($zip_path, $destination);
        $this->delete_file($zip_path);
        if (empty($extract['ok'])) {
            return $extract;
        }

        $base_dir = trailingslashit($destination);
        $root = trim((string) ($validation['root'] ?? ''), '/');
        if ($root !== '') {
            $base_dir .= trailingslashit($root);
        }

        $audit_id = sanitize_key('theme-import-' . $slug . '-' . strtolower(wp_generate_password(8, false, false)));
        $is_picowind = ($manifest['compatibility']['picowind'] ?? null) !== null || ($theme['framework'] ?? '') === 'picowind';
        $pending_install = $this->installer->consume_pending_install_state($stylesheet, $slug);
        $previous_theme = sanitize_key((string) ($pending_install['previous_theme'] ?? wp_get_theme()->get_stylesheet()));
        $previous_theme_mods = is_array($pending_install['previous_theme_mods'] ?? null)
            ? $pending_install['previous_theme_mods']
            : [
                'nav_menu_locations' => get_theme_mod('nav_menu_locations', []),
            ];
        $windpress_runtime = is_array($pending_install['windpress_runtime'] ?? null)
            ? $pending_install['windpress_runtime']
            : [];
        $rollback = [
            'type'              => 'theme_library_import',
            'audit_id'          => $audit_id,
            'theme_slug'        => $slug,
            'theme_version'     => $version,
            'checksum'          => $checksum,
            'import_key'        => $import_key,
            'created_posts'     => [],
            'updated_posts'     => [],
            'created_media'     => [],
            'updated_options'   => [],
            'previous_theme'    => $previous_theme,
            'previous_theme_mods'=> $previous_theme_mods,
            'created_menus'     => [],
            'windpress_runtime' => $windpress_runtime,
        ];

        $result = [
            'ok'              => true,
            'status'          => 'imported',
            'message'         => __('Theme Library starter data imported.', 'livecanvas-forge-ai'),
            'import_audit_id' => $audit_id,
            'theme_slug'      => $slug,
            'theme_version'   => $version,
            'checksum'        => $checksum,
            'steps'           => [],
            'warnings'        => [],
        ];
        $auto_rollback = $auto_rollback ?? (bool) apply_filters(
            'lcfa_theme_library_auto_rollback',
            true,
            $theme,
            $manifest
        );

        try {
            if ($is_picowind && empty($rollback['windpress_runtime']['available'])) {
                $runtime_backup = $this->windpress_bridge->capture_runtime_state($audit_id);
                if (empty($runtime_backup['ok'])) {
                    throw new RuntimeException((string) ($runtime_backup['message'] ?? __('WindPress runtime backup failed.', 'livecanvas-forge-ai')));
                }
                $rollback['windpress_runtime'] = $runtime_backup;
                if (!empty($runtime_backup['available'])) {
                    $result['steps'][] = 'windpress_runtime_backup_captured';
                }
            } elseif ($is_picowind && !empty($rollback['windpress_runtime']['available'])) {
                $result['steps'][] = 'windpress_runtime_backup_reused';
            }

            if ($stylesheet !== '' && wp_get_theme($stylesheet)->exists() && wp_get_theme()->get_stylesheet() !== $stylesheet) {
                switch_theme($stylesheet);
                $result['steps'][] = 'theme_activated';
            }

            if ($is_picowind) {
                $runtime = $this->windpress_bridge->ensure_picowind_runtime();
                if (empty($runtime['ok'])) {
                    $result['warnings'][] = (string) ($runtime['message'] ?? __('WindPress Picowind runtime could not be initialized.', 'livecanvas-forge-ai'));
                } else {
                    $result['steps'][] = 'windpress_picowind_runtime_initialized';
                }
            }

            $this->import_options($base_dir, (string) ($manifest['livecanvas_settings'] ?? ''), $rollback, $result);
            $this->ensure_livecanvas_partial_settings($rollback, $result);
            $design_system_state = $this->import_design_system($base_dir, $manifest, $rollback, $result);
            $media_map = $this->import_media($base_dir, (string) ($manifest['media_manifest'] ?? ''), $slug, $checksum, $rollback, $result);
            $this->maybe_inject_e2e_failure('after_media', $audit_id, $slug);

            $header = $this->read_content_file($base_dir, (string) ($manifest['header']['content_file'] ?? ''), $media_map);
            $footer = $this->read_content_file($base_dir, (string) ($manifest['footer']['content_file'] ?? ''), $media_map);
            $homepage = $this->read_content_file($base_dir, (string) ($manifest['homepage']['content_file'] ?? ''), $media_map);

            if (preg_match('/<\\/?(?:header|footer)\\b/i', $homepage)) {
                throw new RuntimeException(__('Homepage content must not contain inline header or footer markup.', 'livecanvas-forge-ai'));
            }

            $header_id = $this->upsert_partial('header', $manifest['header'], $header, $slug, $version, $audit_id, $rollback);
            $footer_id = $this->upsert_partial('footer', $manifest['footer'], $footer, $slug, $version, $audit_id, $rollback);
            $this->maybe_inject_e2e_failure('after_partials', $audit_id, $slug);
            $page_id = $this->upsert_homepage($manifest['homepage'], $homepage, $slug, $version, $audit_id, $rollback);
            $this->maybe_inject_e2e_failure('after_homepage', $audit_id, $slug);

            $result['header_id'] = $header_id;
            $result['footer_id'] = $footer_id;
            $result['homepage_id'] = $page_id;
            $result['steps'][] = 'livecanvas_content_imported';

            $this->import_menus($base_dir, (string) ($manifest['menus_file'] ?? ''), $rollback, $result);
            $this->set_homepage($page_id, $rollback, $result);

            $build = $this->finalize_build($is_picowind, $design_system_state);
            $this->maybe_inject_e2e_failure('after_build', $audit_id, $slug);
            $result['build'] = $build;
            $result['ready'] = !empty($build['ready']);
            $result['status'] = (string) ($build['status'] ?? 'ready');

            if ($result['status'] === 'ready') {
                $result['message'] = __('Theme Library starter data imported and its compiled CSS cache was verified.', 'livecanvas-forge-ai');
                $result['steps'][] = 'windpress_compiled_cache_verified';
            } elseif ($result['status'] === 'build_failed') {
                $result['message'] = __('Starter data was imported, but the Tailwind CSS build failed. Retry the build or roll back the import.', 'livecanvas-forge-ai');
                $result['warnings'][] = (string) ($build['message'] ?? '');
            } else {
                $result['message'] = __('Starter data was imported. Build Tailwind CSS before treating this theme as ready.', 'livecanvas-forge-ai');
                $result['warnings'][] = (string) ($build['message'] ?? '');
            }

            $flush = $this->windpress_bridge->flush_runtime_cache();
            if (empty($flush['ok'])) {
                $result['warnings'][] = (string) ($flush['message'] ?? __('WindPress cache flush was not available.', 'livecanvas-forge-ai'));
            } else {
                $result['steps'][] = 'windpress_cache_flushed';
            }

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            $imports[$slug] = [
                'slug'       => $slug,
                'version'    => $version,
                'checksum'   => $checksum,
                'status'     => $result['status'],
                'import_key' => $import_key,
                'audit_id'   => $audit_id,
                'imported_at'=> current_time('mysql', true),
                'homepage_id'=> $page_id,
                'header_id'  => $header_id,
                'footer_id'  => $footer_id,
                'stylesheet' => $stylesheet,
                'build'      => $build,
            ];
            update_option(self::IMPORTS_OPTION, $imports, false);

            LCFA_Settings::store_rollback_record($audit_id, $rollback);
        } catch (Throwable $throwable) {
            $original_error = $throwable->getMessage();
            $imports[$slug] = [
                'slug'       => $slug,
                'version'    => $version,
                'checksum'   => $checksum,
                'status'     => 'failed',
                'import_key' => $import_key,
                'audit_id'   => $audit_id,
                'imported_at'=> current_time('mysql', true),
                'error'      => $original_error,
            ];
            update_option(self::IMPORTS_OPTION, $imports, false);
            LCFA_Settings::store_rollback_record($audit_id, $rollback);

            $automatic_rollback = $this->recover_failed_import($audit_id, $auto_rollback);
            $rollback_ok = !empty($automatic_rollback['attempted']) && !empty($automatic_rollback['ok']);
            $rollback_failed = !empty($automatic_rollback['attempted']) && empty($automatic_rollback['ok']);
            $status = $rollback_ok ? 'failed_rolled_back' : ($rollback_failed ? 'rollback_failed' : 'failed');
            $message = $original_error;

            if ($rollback_ok) {
                $message .= ' ' . __('Automatic rollback restored the previous site state.', 'livecanvas-forge-ai');
            } elseif ($rollback_failed) {
                $message .= ' ' . __('Automatic rollback also failed; review the rollback details before retrying.', 'livecanvas-forge-ai');
            } else {
                $message .= ' ' . __('A manual rollback remains available for this audit ID.', 'livecanvas-forge-ai');
            }

            $result = [
                'ok'                        => false,
                'ready'                     => false,
                'status'                    => $status,
                'message'                   => $message,
                'original_error'            => $original_error,
                'import_audit_id'           => $audit_id,
                'rollback_stored'           => true,
                'automatic_rollback'        => $automatic_rollback,
                'manual_rollback_available' => !$rollback_ok,
            ];
        }

        $this->delete_directory($destination);

        return $result;
    }

    private function recover_failed_import(string $audit_id, bool $enabled): array {
        if (!$enabled) {
            return [
                'attempted' => false,
                'ok'        => false,
                'message'   => __('Automatic rollback was disabled for this import.', 'livecanvas-forge-ai'),
            ];
        }

        $rollback_service = $this->rollback_service;
        if (!$rollback_service && class_exists('LCFA_Theme_Library_Rollback')) {
            $rollback_service = new LCFA_Theme_Library_Rollback($this->windpress_bridge);
        }

        if (!$rollback_service) {
            return [
                'attempted' => false,
                'ok'        => false,
                'message'   => __('Automatic rollback is unavailable in this runtime.', 'livecanvas-forge-ai'),
            ];
        }

        try {
            $rollback = $rollback_service->rollback($audit_id, false);
        } catch (Throwable $throwable) {
            return [
                'attempted' => true,
                'ok'        => false,
                'message'   => $throwable->getMessage(),
                'errors'    => [$throwable->getMessage()],
            ];
        }

        return [
            'attempted' => true,
            'ok'        => !empty($rollback['ok']),
            'message'   => (string) ($rollback['message'] ?? ''),
            'errors'    => array_values(array_filter(array_map('strval', (array) ($rollback['errors'] ?? [])))),
            'plan'      => is_array($rollback['plan'] ?? null) ? $rollback['plan'] : [],
        ];
    }

    private function maybe_inject_e2e_failure(string $stage, string $audit_id, string $theme_slug): void {
        if (!defined('LCFA_E2E_MODE') || LCFA_E2E_MODE !== true) {
            return;
        }

        $requested_stage = defined('LCFA_E2E_FAILURE_STAGE') ? (string) LCFA_E2E_FAILURE_STAGE : '';
        if (function_exists('apply_filters')) {
            $requested_stage = (string) apply_filters(
                'lcfa_theme_library_e2e_failure_stage',
                $requested_stage,
                $stage,
                $audit_id,
                $theme_slug
            );
        }

        $requested_stage = sanitize_key($requested_stage);
        if ($requested_stage !== '' && hash_equals(sanitize_key($stage), $requested_stage)) {
            throw new RuntimeException(sprintf(
                /* translators: %s: controlled E2E checkpoint name. */
                __('Controlled Theme Library E2E failure after checkpoint: %s.', 'livecanvas-forge-ai'),
                $stage
            ));
        }
    }

    public static function get_imports(): array {
        if (!function_exists('get_option')) {
            return [];
        }

        $imports = get_option(self::IMPORTS_OPTION, []);

        return is_array($imports) ? $imports : [];
    }

    public static function get_build_state_summary(): array {
        $counts = [
            'total'          => 0,
            'pending'        => 0,
            'ready'          => 0,
            'degraded'       => 0,
            'failed'         => 0,
        ];
        $pending = [];

        foreach (self::get_imports() as $slug => $import) {
            if (!is_array($import)) {
                continue;
            }

            $counts['total']++;
            $status = sanitize_key((string) ($import['status'] ?? ''));
            if (in_array($status, ['build_required', 'build_failed'], true)) {
                $counts['pending']++;
                $pending[] = [
                    'theme_slug' => sanitize_key((string) $slug),
                    'status'     => $status,
                    'audit_id'   => sanitize_key((string) ($import['audit_id'] ?? '')),
                ];
            } elseif ($status === 'ready') {
                $counts['ready']++;
            } elseif ($status === 'ready_degraded') {
                $counts['degraded']++;
            } elseif (str_starts_with($status, 'failed') || $status === 'rollback_failed') {
                $counts['failed']++;
            }
        }

        return [
            'status'  => $counts['pending'] > 0
                ? 'build_required'
                : ($counts['degraded'] > 0 ? 'ready_degraded' : ($counts['total'] > 0 ? 'ready' : 'none')),
            'counts'  => $counts,
            'pending' => $pending,
        ];
    }

    public function get_build_capability(): array {
        if (!$this->build_gateway) {
            return [
                'build_available' => false,
                'message'         => __('The local WindPress build gateway is not configured.', 'livecanvas-forge-ai'),
            ];
        }

        return $this->build_gateway->get_status();
    }

    public function build(string $slug): array {
        $slug = sanitize_key($slug);
        $imports = self::get_imports();
        $import = is_array($imports[$slug] ?? null) ? $imports[$slug] : [];

        if ($slug === '' || !$import) {
            return [
                'ok'      => false,
                'status'  => 'missing_import',
                'message' => __('Import starter data before building its Tailwind CSS.', 'livecanvas-forge-ai'),
            ];
        }

        if ((string) ($import['status'] ?? '') === 'failed') {
            return [
                'ok'      => false,
                'status'  => 'failed',
                'message' => __('This import failed before the build stage. Roll it back or force a new import.', 'livecanvas-forge-ai'),
            ];
        }

        $stylesheet = sanitize_key((string) ($import['stylesheet'] ?? ''));
        if ($stylesheet !== '' && function_exists('wp_get_theme')) {
            $active_stylesheet = sanitize_key((string) wp_get_theme()->get_stylesheet());
            if ($active_stylesheet !== $stylesheet) {
                return [
                    'ok'              => false,
                    'ready'           => false,
                    'status'          => 'build_required',
                    'message'         => __('Activate the imported child theme before building its Tailwind CSS.', 'livecanvas-forge-ai'),
                    'theme_slug'      => $slug,
                    'import_audit_id' => (string) ($import['audit_id'] ?? ''),
                ];
            }
        }

        $build = $this->execute_windpress_build();
        $import['status'] = (string) ($build['status'] ?? 'build_failed');
        $import['build'] = $build;
        $import['build_updated_at'] = current_time('mysql', true);
        $imports[$slug] = $import;
        update_option(self::IMPORTS_OPTION, $imports, false);

        return [
            'ok'              => !empty($build['ready']),
            'ready'           => !empty($build['ready']),
            'status'          => $import['status'],
            'message'         => (string) ($build['message'] ?? ''),
            'theme_slug'      => $slug,
            'import_audit_id' => (string) ($import['audit_id'] ?? ''),
            'build'           => $build,
        ];
    }

    public function get_pending_build(string $slug = ''): array {
        $slug = sanitize_key($slug);
        $imports = self::get_imports();
        $pending = [];

        foreach ($imports as $import_slug => $import) {
            if (!is_array($import)) {
                continue;
            }

            $import_slug = sanitize_key((string) $import_slug);
            if ($slug !== '' && $import_slug !== $slug) {
                continue;
            }

            $status = sanitize_key((string) ($import['status'] ?? ''));
            if (!in_array($status, ['build_required', 'build_failed'], true)) {
                continue;
            }

            $pending[] = [
                'theme_slug'              => $import_slug,
                'theme_version'           => sanitize_text_field((string) ($import['version'] ?? '')),
                'status'                  => $status,
                'stylesheet'              => sanitize_key((string) ($import['stylesheet'] ?? '')),
                'import_audit_id'         => sanitize_key((string) ($import['audit_id'] ?? '')),
                'expected_import_checksum'=> strtolower(trim((string) ($import['checksum'] ?? ''))),
                'build'                   => is_array($import['build'] ?? null) ? $import['build'] : [],
                'required_scopes'         => ['write', 'cache'],
            ];
        }

        if ($slug !== '') {
            if (!$pending) {
                return [
                    'ok'      => false,
                    'status'  => 'missing_pending_build',
                    'message' => __('No pending Theme Library CSS build matches this theme.', 'livecanvas-forge-ai'),
                ];
            }

            return [
                'ok'      => true,
                'status'  => 'build_required',
                'pending' => $pending[0],
            ];
        }

        return [
            'ok'      => true,
            'status'  => $pending ? 'build_required' : 'ready',
            'pending' => $pending,
            'count'   => count($pending),
        ];
    }

    public function complete_remote_build(array $payload): array {
        $slug = sanitize_key((string) ($payload['theme_slug'] ?? $payload['slug'] ?? ''));
        $audit_id = sanitize_key((string) ($payload['import_audit_id'] ?? $payload['audit_id'] ?? ''));
        $expected_import_checksum = strtolower(trim((string) ($payload['expected_import_checksum'] ?? '')));
        $cache_sha256 = strtolower(trim((string) ($payload['cache_sha256'] ?? '')));
        $tailwind_version = (int) ($payload['tailwind_version'] ?? 0);
        $imports = self::get_imports();
        $import = is_array($imports[$slug] ?? null) ? $imports[$slug] : [];

        if ($slug === '' || !$import) {
            return [
                'ok'      => false,
                'ready'   => false,
                'status'  => 'missing_import',
                'message' => __('Import starter data before completing its Tailwind CSS build.', 'livecanvas-forge-ai'),
            ];
        }

        if (!in_array((string) ($import['status'] ?? ''), ['build_required', 'build_failed'], true)) {
            return [
                'ok'      => false,
                'ready'   => (string) ($import['status'] ?? '') === 'ready',
                'status'  => (string) ($import['status'] ?? 'invalid_import_state'),
                'message' => __('This Theme Library import is not waiting for a remote CSS build.', 'livecanvas-forge-ai'),
            ];
        }

        $stored_audit_id = (string) ($import['audit_id'] ?? '');
        if ($audit_id === '' || $stored_audit_id === '' || !hash_equals($stored_audit_id, $audit_id)) {
            return [
                'ok'      => false,
                'ready'   => false,
                'status'  => 'audit_mismatch',
                'message' => __('The import audit ID does not match the pending Theme Library build.', 'livecanvas-forge-ai'),
            ];
        }

        $stored_import_checksum = strtolower(trim((string) ($import['checksum'] ?? '')));
        if ($expected_import_checksum === '' || $stored_import_checksum === '' || !hash_equals($stored_import_checksum, $expected_import_checksum)) {
            return [
                'ok'      => false,
                'ready'   => false,
                'status'  => 'import_checksum_mismatch',
                'message' => __('The import checksum does not match the pending Theme Library build.', 'livecanvas-forge-ai'),
            ];
        }

        $stylesheet = sanitize_key((string) ($import['stylesheet'] ?? ''));
        if ($stylesheet === '' || !function_exists('wp_get_theme') || sanitize_key((string) wp_get_theme()->get_stylesheet()) !== $stylesheet) {
            return [
                'ok'      => false,
                'ready'   => false,
                'status'  => 'inactive_theme',
                'message' => __('Activate the imported child theme before completing its Tailwind CSS build.', 'livecanvas-forge-ai'),
            ];
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $cache_sha256)) {
            return [
                'ok'      => false,
                'ready'   => false,
                'status'  => 'invalid_cache_checksum',
                'message' => __('A valid compiled CSS SHA-256 checksum is required.', 'livecanvas-forge-ai'),
            ];
        }

        $verification = $this->windpress_bridge->get_compiled_cache_state();
        $verified_checksum = strtolower(trim((string) ($verification['cache']['sha256'] ?? '')));
        if (empty($verification['ready']) || $verified_checksum === '' || !hash_equals($verified_checksum, $cache_sha256)) {
            return [
                'ok'           => false,
                'ready'        => false,
                'status'       => 'cache_checksum_mismatch',
                'message'      => __('The stored WindPress CSS cache does not match the locally compiled checksum.', 'livecanvas-forge-ai'),
                'verification' => $verification,
            ];
        }

        $tailwind_version = $tailwind_version === 3 ? 3 : 4;
        $degraded = $tailwind_version === 3;
        $status = $degraded ? 'ready_degraded' : 'ready';
        $build = [
            'ready'             => !$degraded,
            'usable'            => true,
            'status'            => $status,
            'support_level'     => $degraded ? 'degraded' : 'full',
            'strategy'          => 'windpress_remote_mcp',
            'tailwind_version'  => $tailwind_version,
            'cache_sha256'      => $verified_checksum,
            'verification'      => $verification,
            'verified_at'       => current_time('mysql', true),
            'message'           => $degraded
                ? __('Tailwind 3 CSS was compiled and verified. This runtime remains in guided degraded mode; prefer Tailwind 4 for full beta support.', 'livecanvas-forge-ai')
                : __('Tailwind 4 CSS was compiled, stored, and verified for this Theme Library import.', 'livecanvas-forge-ai'),
        ];

        $import['status'] = $status;
        $import['build'] = $build;
        $import['build_updated_at'] = current_time('mysql', true);
        $imports[$slug] = $import;
        update_option(self::IMPORTS_OPTION, $imports, false);

        return [
            'ok'              => true,
            'ready'           => !$degraded,
            'usable'          => true,
            'status'          => $status,
            'message'         => $build['message'],
            'theme_slug'      => $slug,
            'import_audit_id' => $stored_audit_id,
            'build'           => $build,
        ];
    }

    private function import_options(string $base_dir, string $relative_path, array &$rollback, array &$result): void {
        $settings = $this->read_json_file($base_dir, $relative_path);
        $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        foreach ($options as $option_name => $value) {
            $option_name = sanitize_key((string) $option_name);
            if ($option_name === '') {
                continue;
            }

            $this->record_option_rollback($option_name, $rollback);

            update_option($option_name, $value, false);
        }

        if ($options) {
            $result['steps'][] = 'livecanvas_settings_imported';
        }
    }

    private function ensure_livecanvas_partial_settings(array &$rollback, array &$result): void {
        $settings = get_option('lc_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $updated = $settings;
        $updated['header'] = '1';
        $updated['footerV2'] = '1';

        if ($updated === $settings) {
            return;
        }

        $this->record_option_rollback('lc_settings', $rollback);
        update_option('lc_settings', $updated, false);
        $result['steps'][] = 'livecanvas_header_footer_enabled';
    }

    private function record_option_rollback(string $option_name, array &$rollback): void {
        if ($option_name === '' || array_key_exists($option_name, $rollback['updated_options'])) {
            return;
        }

        $missing = new stdClass();
        $value = get_option($option_name, $missing);
        $rollback['updated_options'][$option_name] = [
            'exists' => $value !== $missing,
            'value'  => $value !== $missing ? $value : null,
        ];
    }

    private function import_design_system(string $base_dir, array $manifest, array &$rollback, array &$result): array {
        $state = [
            'source'      => 'none',
            'cache_ready' => false,
            'error'       => '',
        ];
        $design_path = (string) ($manifest['design_system_file'] ?? '');
        $design = $this->read_json_file($base_dir, $design_path);
        if ($design) {
            $saved = $this->windpress_bridge->save_theme_json($design);
            if (empty($saved['ok'])) {
                $result['warnings'][] = (string) ($saved['message'] ?? __('WindPress design system import was not available.', 'livecanvas-forge-ai'));
            } else {
                $result['steps'][] = 'windpress_theme_json_imported';
            }
        }

        $css_path = $this->safe_join($base_dir, 'public/styles/tailwind.css');
        if ($css_path !== '' && is_readable($css_path)) {
            $css = (string) file_get_contents($css_path);
            if ($css !== '') {
                if (preg_match('/@(import|tailwind)\b/', $css)) {
                    $result['steps'][] = 'windpress_source_css_ready';
                    $state['source'] = 'tailwind_source';
                } else {
                    $saved_css = $this->windpress_bridge->save_cache_css($css);
                    if (empty($saved_css['ok'])) {
                        $state['source'] = 'compiled_css';
                        $state['error'] = (string) ($saved_css['message'] ?? __('WindPress CSS cache import was not available.', 'livecanvas-forge-ai'));
                        $result['warnings'][] = $state['error'];
                    } else {
                        $result['steps'][] = 'windpress_css_imported';
                        $state['source'] = 'compiled_css';
                        $cache = $this->windpress_bridge->get_compiled_cache_state();
                        $state['cache_ready'] = !empty($cache['ready']);
                        $state['cache'] = $cache;
                    }
                }
            }
        }

        return $state;
    }

    private function finalize_build(bool $is_picowind, array $design_system_state): array {
        if (!$is_picowind) {
            return [
                'ready'    => true,
                'status'   => 'ready',
                'strategy' => 'not_required',
                'message'  => __('No WindPress build is required for this theme.', 'livecanvas-forge-ai'),
            ];
        }

        if (!empty($design_system_state['cache_ready'])) {
            return [
                'ready'        => true,
                'status'       => 'ready',
                'strategy'     => 'packaged_compiled_css',
                'message'      => __('The packaged compiled CSS was stored and verified.', 'livecanvas-forge-ai'),
                'verification' => $design_system_state['cache'] ?? [],
            ];
        }

        if (!empty($design_system_state['error'])) {
            return [
                'ready'    => false,
                'status'   => 'build_failed',
                'strategy' => 'packaged_compiled_css',
                'message'  => (string) $design_system_state['error'],
            ];
        }

        return $this->execute_windpress_build();
    }

    private function execute_windpress_build(): array {
        if (!$this->build_gateway) {
            return [
                'ready'       => false,
                'status'      => 'build_required',
                'strategy'    => 'windpress_local_mcp',
                'next_action' => 'configure_build_gateway',
                'message'     => __('A persistent Tailwind build is required, but the local build gateway is not configured.', 'livecanvas-forge-ai'),
            ];
        }

        $gateway_status = $this->build_gateway->refresh_status();
        if (empty($gateway_status['build_available'])) {
            return [
                'ready'       => false,
                'status'      => 'build_required',
                'strategy'    => 'windpress_local_mcp',
                'next_action' => 'make_local_build_available',
                'message'     => (string) ($gateway_status['message'] ?? __('A persistent Tailwind build is required, but it is unavailable from this runtime.', 'livecanvas-forge-ai')),
                'gateway'     => $this->summarize_gateway_status($gateway_status),
            ];
        }

        $build = $this->build_gateway->build_windpress_cache([
            'kind'       => 'full',
            'store'      => true,
            'source_map' => false,
        ]);

        if (empty($build['ok'])) {
            return [
                'ready'       => false,
                'status'      => 'build_failed',
                'strategy'    => 'windpress_local_mcp',
                'next_action' => 'retry_build',
                'message'     => (string) ($build['message'] ?? __('The local WindPress build failed.', 'livecanvas-forge-ai')),
                'gateway'     => $this->summarize_gateway_status($gateway_status),
            ];
        }

        $verification = $this->windpress_bridge->get_compiled_cache_state();
        if (empty($verification['ready'])) {
            return [
                'ready'        => false,
                'status'       => 'build_failed',
                'strategy'     => 'windpress_local_mcp',
                'next_action'  => 'retry_build',
                'message'      => (string) ($verification['message'] ?? __('WindPress reported a successful build, but no compiled cache could be verified.', 'livecanvas-forge-ai')),
                'verification' => $verification,
                'gateway'      => $this->summarize_gateway_status($gateway_status),
            ];
        }

        $build_result = is_array($build['result'] ?? null) ? $build['result'] : [];

        return [
            'ready'           => true,
            'status'          => 'ready',
            'strategy'        => 'windpress_local_mcp',
            'message'         => __('Tailwind CSS was compiled, stored, and verified in the WindPress cache.', 'livecanvas-forge-ai'),
            'tailwind_version'=> (int) ($build_result['tailwind_version'] ?? 0),
            'provider_count'  => (int) ($build_result['provider_count'] ?? 0),
            'candidate_count' => (int) ($build_result['candidate_count'] ?? 0),
            'verification'    => $verification,
            'gateway'         => $this->summarize_gateway_status($gateway_status),
        ];
    }

    private function summarize_gateway_status(array $status): array {
        return [
            'build_available' => !empty($status['build_available']),
            'local_site'      => !empty($status['local_site']),
            'windpress_active'=> !empty($status['windpress_active']),
            'node_available'  => !empty($status['node_available']),
            'node_version'    => (string) ($status['node_version'] ?? ''),
            'rest_reachable'  => !empty($status['rest_reachable']),
        ];
    }

    private function import_media(string $base_dir, string $relative_path, string $theme_slug, string $checksum, array &$rollback, array &$result): array {
        $manifest = $this->read_json_file($base_dir, $relative_path);
        $items = [];
        if (isset($manifest['items']) && is_array($manifest['items'])) {
            $items = $manifest['items'];
        } elseif (isset($manifest['media']) && is_array($manifest['media'])) {
            $items = $manifest['media'];
        }

        $map = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $asset_id = sanitize_key((string) ($item['id'] ?? $item['asset_id'] ?? ''));
            $file = (string) ($item['file'] ?? '');
            if ($asset_id === '' || $file === '') {
                continue;
            }

            $existing_id = $this->find_attachment($theme_slug, $asset_id, $checksum);
            if ($existing_id > 0) {
                $map[$asset_id] = [
                    'id'  => $existing_id,
                    'url' => wp_get_attachment_url($existing_id),
                ];
                continue;
            }

            $source_path = $this->safe_join($base_dir, $file);
            if ($source_path === '' || !is_readable($source_path)) {
                $result['warnings'][] = sprintf('Media asset "%s" was not found.', $asset_id);
                continue;
            }

            $attachment_id = $this->sideload_local_file($source_path, [
                'title'   => sanitize_text_field((string) ($item['title'] ?? $asset_id)),
                'alt'     => sanitize_text_field((string) ($item['alt'] ?? '')),
                'caption' => sanitize_text_field((string) ($item['caption'] ?? '')),
            ]);

            if ($attachment_id <= 0) {
                $result['warnings'][] = sprintf('Media asset "%s" could not be imported.', $asset_id);
                continue;
            }

            update_post_meta($attachment_id, '_lcfa_theme_library_slug', $theme_slug);
            update_post_meta($attachment_id, '_lcfa_theme_library_asset_id', $asset_id);
            update_post_meta($attachment_id, '_lcfa_theme_library_checksum', $checksum);
            $rollback['created_media'][] = $attachment_id;

            $map[$asset_id] = [
                'id'  => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
            ];
        }

        if ($map) {
            $result['steps'][] = 'media_imported';
        }

        return $map;
    }

    private function read_content_file(string $base_dir, string $relative_path, array $media_map): string {
        $path = $this->safe_join($base_dir, $relative_path);
        if ($path === '' || !is_readable($path)) {
            throw new RuntimeException(__('Theme content file was not found.', 'livecanvas-forge-ai'));
        }

        $content = (string) file_get_contents($path);
        foreach ($media_map as $asset_id => $media) {
            $url = (string) ($media['url'] ?? '');
            $content = str_replace([
                '{{media:' . $asset_id . '}}',
                '{{media:' . $asset_id . ':url}}',
            ], $url, $content);
        }

        return $content;
    }

    private function upsert_partial(string $type, array $definition, string $content, string $theme_slug, string $version, string $audit_id, array &$rollback): int {
        $variant = sanitize_text_field((string) ($definition['variant'] ?? '1'));
        $title = sanitize_text_field((string) ($definition['title'] ?? ucfirst($type)));
        $post_id = $this->find_imported_post('lc_partial', $theme_slug, $type);

        $postarr = [
            'post_type'    => 'lc_partial',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
        ];

        if ($post_id > 0) {
            $this->record_post_rollback($post_id, $rollback);
            $postarr['ID'] = $post_id;
            $saved = $this->persist_content_post($postarr, true);
        } else {
            $saved = $this->persist_content_post($postarr, false);
        }

        if (is_wp_error($saved) || (int) $saved <= 0) {
            $message = is_wp_error($saved) ? $saved->get_error_message() : __('LiveCanvas partial could not be created.', 'livecanvas-forge-ai');
            throw new RuntimeException($message);
        }

        $post_id = (int) $saved;
        if (!isset($postarr['ID'])) {
            $rollback['created_posts'][] = $post_id;
        }

        update_post_meta($post_id, $type === 'header' ? 'is_header' : 'is_footer', $variant);
        update_post_meta($post_id, '_lcfa_theme_library_slug', $theme_slug);
        update_post_meta($post_id, '_lcfa_theme_library_version', $version);
        update_post_meta($post_id, '_lcfa_theme_library_import_id', $audit_id);
        update_post_meta($post_id, '_lcfa_theme_library_part', $type);

        if (array_key_exists('partial_types', $definition)) {
            $this->persist_manifest_partial_types($post_id, (array) $definition['partial_types']);
        }

        return $post_id;
    }

    private function persist_manifest_partial_types(int $post_id, array $partial_types): void {
        if (!function_exists('taxonomy_exists') || !taxonomy_exists('lc_partial_type') || !function_exists('wp_set_object_terms')) {
            throw new RuntimeException(__('The LiveCanvas lc_partial_type taxonomy is unavailable.', 'livecanvas-forge-ai'));
        }

        $terms = [];
        foreach ($partial_types as $term) {
            if (is_int($term) || (is_string($term) && ctype_digit($term))) {
                $term_id = absint($term);
                if ($term_id > 0) {
                    $terms[] = $term_id;
                }
                continue;
            }

            if (is_scalar($term)) {
                $slug = sanitize_title((string) $term);
                if ($slug !== '') {
                    $terms[] = $slug;
                }
            }
        }

        $updated = wp_set_object_terms($post_id, array_values(array_unique($terms, SORT_REGULAR)), 'lc_partial_type', false);
        if (is_wp_error($updated)) {
            throw new RuntimeException($updated->get_error_message());
        }
    }

    private function upsert_homepage(array $definition, string $content, string $theme_slug, string $version, string $audit_id, array &$rollback): int {
        $title = sanitize_text_field((string) ($definition['title'] ?? 'Home'));
        $slug = sanitize_title((string) ($definition['slug'] ?? 'home'));
        $template = sanitize_text_field((string) ($definition['template'] ?? ''));
        $post_id = $this->find_imported_post('page', $theme_slug, 'homepage');

        if ($post_id <= 0 && $slug !== '') {
            $existing = get_page_by_path($slug, OBJECT, 'page');
            if ($existing instanceof WP_Post) {
                $post_id = (int) $existing->ID;
            }
        }

        $postarr = [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
        ];

        if ($post_id > 0) {
            $this->record_post_rollback($post_id, $rollback);
            $postarr['ID'] = $post_id;
            $saved = $this->persist_content_post($postarr, true);
        } else {
            $saved = $this->persist_content_post($postarr, false);
        }

        if (is_wp_error($saved) || (int) $saved <= 0) {
            $message = is_wp_error($saved) ? $saved->get_error_message() : __('Homepage could not be created.', 'livecanvas-forge-ai');
            throw new RuntimeException($message);
        }

        $post_id = (int) $saved;
        if (!isset($postarr['ID'])) {
            $rollback['created_posts'][] = $post_id;
        }

        update_post_meta($post_id, '_lc_livecanvas_enabled', '1');
        update_post_meta($post_id, '_lcfa_theme_library_slug', $theme_slug);
        update_post_meta($post_id, '_lcfa_theme_library_version', $version);
        update_post_meta($post_id, '_lcfa_theme_library_import_id', $audit_id);
        update_post_meta($post_id, '_lcfa_theme_library_part', 'homepage');
        if ($template !== '') {
            update_post_meta($post_id, '_wp_page_template', $template);
        }

        return $post_id;
    }

    private function persist_content_post(array $postarr, bool $is_update) {
        return $this->with_unfiltered_post_content(static function () use ($postarr, $is_update) {
            return $is_update
                ? wp_update_post($postarr, true)
                : wp_insert_post($postarr, true);
        });
    }

    private function with_unfiltered_post_content(callable $operation) {
        if ((function_exists('current_user_can') && current_user_can('unfiltered_html')) || !function_exists('remove_filter') || !function_exists('add_filter')) {
            return $operation();
        }

        $removed = [];
        foreach ([
            ['content_save_pre', 'wp_filter_post_kses', 10, 1],
            ['content_filtered_save_pre', 'wp_filter_post_kses', 10, 1],
        ] as $filter) {
            [$hook, $callback, $priority, $accepted_args] = $filter;
            if (remove_filter($hook, $callback, $priority)) {
                $removed[] = [$hook, $callback, $priority, $accepted_args];
            }
        }

        try {
            return $operation();
        } finally {
            foreach ($removed as [$hook, $callback, $priority, $accepted_args]) {
                add_filter($hook, $callback, $priority, $accepted_args);
            }
        }
    }

    private function import_menus(string $base_dir, string $relative_path, array &$rollback, array &$result): void {
        $manifest = $this->read_json_file($base_dir, $relative_path);
        $menus = is_array($manifest['menus'] ?? null) ? $manifest['menus'] : [];
        if (!$menus) {
            return;
        }

        $locations = get_theme_mod('nav_menu_locations', []);
        $rollback['previous_theme_mods']['nav_menu_locations'] = $locations;

        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $name = sanitize_text_field((string) ($menu['name'] ?? 'Theme Library Menu'));
            $location = sanitize_key((string) ($menu['location'] ?? 'primary'));
            $menu_term = wp_get_nav_menu_object($name);
            if (!$menu_term) {
                $menu_id = wp_create_nav_menu($name);
                if (is_wp_error($menu_id)) {
                    $result['warnings'][] = $menu_id->get_error_message();
                    continue;
                }
                $rollback['created_menus'][] = (int) $menu_id;
            } else {
                $menu_id = (int) $menu_term->term_id;
            }

            foreach ((array) ($menu['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title'  => sanitize_text_field((string) ($item['title'] ?? 'Item')),
                    'menu-item-url'    => esc_url_raw((string) ($item['url'] ?? home_url('/'))),
                    'menu-item-status' => 'publish',
                ]);
            }

            $locations[$location] = $menu_id;
        }

        set_theme_mod('nav_menu_locations', $locations);
        $result['steps'][] = 'menus_imported';
    }

    private function set_homepage(int $page_id, array &$rollback, array &$result): void {
        foreach (['show_on_front', 'page_on_front'] as $option_name) {
            if (!array_key_exists($option_name, $rollback['updated_options'])) {
                $rollback['updated_options'][$option_name] = [
                    'exists' => get_option($option_name, '__lcfa_missing__') !== '__lcfa_missing__',
                    'value'  => get_option($option_name),
                ];
            }
        }

        update_option('show_on_front', 'page', false);
        update_option('page_on_front', $page_id, false);
        $result['steps'][] = 'homepage_assigned';
    }

    private function read_json_file(string $base_dir, string $relative_path): array {
        $path = $this->safe_join($base_dir, $relative_path);
        if ($path === '' || !is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function safe_join(string $base_dir, string $relative_path): string {
        $relative_path = $this->validator->normalize_relative_path($relative_path);
        if ($relative_path === '') {
            return '';
        }

        $base = realpath($base_dir);
        $path = realpath(trailingslashit($base_dir) . $relative_path);
        if (!$base || !$path || strpos($path, $base) !== 0) {
            return '';
        }

        return $path;
    }

    private function sideload_local_file(string $source_path, array $metadata): int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = wp_tempnam(basename($source_path));
        if (!$tmp || !copy($source_path, $tmp)) {
            return 0;
        }

        $file = [
            'name'     => basename($source_path),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file, 0, (string) ($metadata['title'] ?? ''));
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return 0;
        }

        if (!empty($metadata['alt'])) {
            update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', (string) $metadata['alt']);
        }

        if (!empty($metadata['caption'])) {
            wp_update_post([
                'ID'           => (int) $attachment_id,
                'post_excerpt' => (string) $metadata['caption'],
            ]);
        }

        return (int) $attachment_id;
    }

    private function find_attachment(string $theme_slug, string $asset_id, string $checksum): int {
        $posts = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lcfa_theme_library_slug',
                    'value' => $theme_slug,
                ],
                [
                    'key'   => '_lcfa_theme_library_asset_id',
                    'value' => $asset_id,
                ],
                [
                    'key'   => '_lcfa_theme_library_checksum',
                    'value' => $checksum,
                ],
            ],
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function find_imported_post(string $post_type, string $theme_slug, string $part): int {
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lcfa_theme_library_slug',
                    'value' => $theme_slug,
                ],
                [
                    'key'   => '_lcfa_theme_library_part',
                    'value' => $part,
                ],
            ],
        ]);

        return isset($posts[0]) ? (int) $posts[0] : 0;
    }

    private function resolve_installed_stylesheet(string $stylesheet, array $manifest, array $theme): string {
        if ($stylesheet !== '' && wp_get_theme($stylesheet)->exists()) {
            return $stylesheet;
        }

        $expected_name = sanitize_text_field((string) ($manifest['theme']['name'] ?? $theme['name'] ?? ''));
        $expected_slug = sanitize_key((string) ($manifest['theme']['slug'] ?? $theme['slug'] ?? $stylesheet));
        $expected_text_domain = sanitize_key((string) ($manifest['theme']['text_domain'] ?? $expected_slug));

        foreach (wp_get_themes() as $candidate_stylesheet => $candidate) {
            if (!$candidate->exists()) {
                continue;
            }

            $candidate_name = sanitize_text_field((string) $candidate->get('Name'));
            $candidate_text_domain = sanitize_key((string) $candidate->get('TextDomain'));
            $candidate_template = sanitize_key((string) $candidate->get_template());
            $candidate_key = sanitize_key((string) $candidate_stylesheet);

            if ($expected_name !== '' && strcasecmp($candidate_name, $expected_name) === 0 && $candidate_template === 'picowind') {
                return (string) $candidate_stylesheet;
            }

            if ($expected_text_domain !== '' && $candidate_text_domain === $expected_text_domain) {
                return (string) $candidate_stylesheet;
            }

            if ($expected_slug !== '' && strpos($candidate_key, $expected_slug . '-') === 0) {
                return (string) $candidate_stylesheet;
            }
        }

        return $stylesheet;
    }

    private function record_post_rollback(int $post_id, array &$rollback): void {
        if (isset($rollback['updated_posts'][$post_id])) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return;
        }

        $record = [
            'ID'           => $post_id,
            'post_title'   => $post->post_title,
            'post_name'    => $post->post_name,
            'post_status'  => $post->post_status,
            'post_content' => (string) get_post_field('post_content', $post_id, 'raw'),
            'meta'         => [
                '_lc_livecanvas_enabled' => get_post_meta($post_id, '_lc_livecanvas_enabled', true),
                '_wp_page_template'      => get_post_meta($post_id, '_wp_page_template', true),
                'is_header'              => get_post_meta($post_id, 'is_header', true),
                'is_footer'              => get_post_meta($post_id, 'is_footer', true),
                '_lcfa_theme_library_slug' => get_post_meta($post_id, '_lcfa_theme_library_slug', true),
                '_lcfa_theme_library_version' => get_post_meta($post_id, '_lcfa_theme_library_version', true),
                '_lcfa_theme_library_import_id' => get_post_meta($post_id, '_lcfa_theme_library_import_id', true),
                '_lcfa_theme_library_part' => get_post_meta($post_id, '_lcfa_theme_library_part', true),
            ],
        ];

        if ($post->post_type === 'lc_partial') {
            $record['taxonomies'] = [
                'lc_partial_type' => $this->get_post_partial_type_slugs($post_id),
            ];
        }

        $rollback['updated_posts'][$post_id] = $record;
    }

    private function get_post_partial_type_slugs(int $post_id): array {
        if (!function_exists('taxonomy_exists') || !taxonomy_exists('lc_partial_type') || !function_exists('wp_get_post_terms')) {
            return [];
        }

        $terms = wp_get_post_terms($post_id, 'lc_partial_type', ['fields' => 'slugs']);

        return is_wp_error($terms) || !is_array($terms)
            ? []
            : array_values(array_filter(array_map('sanitize_key', $terms)));
    }

    private function delete_file(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function delete_directory(string $path): void {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->delete_directory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
