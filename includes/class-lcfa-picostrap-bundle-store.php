<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Bundle_Store {
    private LCFA_Environment $environment;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;

    public function __construct(LCFA_Environment $environment, ?LCFA_Theme_Files_Bridge $theme_files_bridge = null) {
        $this->environment = $environment;
        $this->theme_files_bridge = $theme_files_bridge ?: new LCFA_Theme_Files_Bridge($environment);
    }

    public function store(string $css, array $metadata = []): array {
        $this->ensure_picostrap_helpers_loaded();

        if (trim($css) === '') {
            return [
                'ok' => false,
                'message' => __('Unable to store an empty Picostrap bundle.', 'livecanvas-forge-ai'),
            ];
        }

        $target_relative = $this->get_bundle_relative_path();
        $target_path = trailingslashit(get_stylesheet_directory()) . $target_relative;
        $bundle_existed = is_file($target_path);
        $theme_mod_snapshot = $this->snapshot_theme_mods([
            'css_bundle_version_number',
            'lcfa_picostrap_compiled_source_fingerprint',
            'lcfa_picostrap_compiled_at',
        ]);

        try {
            $write = $this->theme_files_bridge->write_file([
                'root_scope' => 'stylesheet',
                'path' => $target_relative,
                'content' => $css,
                'dry_run' => false,
                'create_directories' => true,
            ]);
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'message' => $throwable->getMessage(),
            ];
        }

        if (empty($write['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($write['message'] ?? __('Unable to write Picostrap bundle.', 'livecanvas-forge-ai')),
            ];
        }

        $version = (int) get_theme_mod('css_bundle_version_number', 0);
        $version = $version > 0 ? $version + 1 : 1;
        set_theme_mod('css_bundle_version_number', $version);

        $source_fingerprint = sanitize_text_field((string) ($metadata['source_fingerprint'] ?? ''));
        $compiled_at = current_time('mysql', true);
        if ($source_fingerprint !== '') {
            set_theme_mod('lcfa_picostrap_compiled_source_fingerprint', $source_fingerprint);
        }
        set_theme_mod('lcfa_picostrap_compiled_at', $compiled_at);

        $bundle_url = trailingslashit(get_stylesheet_directory_uri()) . trim($target_relative, '/\\') . '?ver=' . $version;

        return [
            'ok' => true,
            'bundle_path' => $target_path,
            'bundle_url' => $bundle_url,
            'bundle_version' => $version,
            'source_fingerprint' => $source_fingerprint,
            'compiled_at' => $compiled_at,
            'write' => $write,
            'rollback' => [
                'bundle_relative_path' => $target_relative,
                'bundle_existed' => $bundle_existed,
                'bundle_created' => !$bundle_existed,
                'backup_id' => (string) ($write['backup_id'] ?? ''),
                'theme_mods' => $theme_mod_snapshot,
            ],
        ];
    }

    public function restore(array $snapshot, bool $dry_run = false): array {
        $this->ensure_picostrap_helpers_loaded();

        $expected_relative = $this->get_bundle_relative_path();
        $relative_path = sanitize_text_field((string) ($snapshot['bundle_relative_path'] ?? $expected_relative));
        if ($relative_path !== $expected_relative) {
            return [
                'ok' => false,
                'message' => __('The Picostrap rollback bundle path does not match the active theme target.', 'livecanvas-forge-ai'),
            ];
        }

        $backup_id = sanitize_text_field((string) ($snapshot['backup_id'] ?? ''));
        $bundle_created = !empty($snapshot['bundle_created']);
        $plan = $backup_id !== '' ? 'restore_backup' : ($bundle_created ? 'remove_created_bundle' : 'restore_theme_mods_only');

        if ($dry_run) {
            return [
                'ok' => true,
                'dry_run' => true,
                'operation' => $plan,
                'bundle_relative_path' => $relative_path,
                'backup_id' => $backup_id,
                'theme_mods' => (array) ($snapshot['theme_mods'] ?? []),
            ];
        }

        $bundle_result = [
            'ok' => true,
            'operation' => $plan,
        ];

        try {
            if ($backup_id !== '') {
                $bundle_result = $this->theme_files_bridge->restore_backup([
                    'backup_id' => $backup_id,
                    'root_scope' => 'stylesheet',
                    'path' => $relative_path,
                    'dry_run' => false,
                ]);
            } elseif ($bundle_created) {
                $target_path = wp_normalize_path(trailingslashit(get_stylesheet_directory()) . $relative_path);
                $stylesheet_root = wp_normalize_path(trailingslashit(get_stylesheet_directory()));
                if (strpos($target_path, $stylesheet_root) !== 0) {
                    throw new RuntimeException(__('Picostrap rollback target escaped the active child theme.', 'livecanvas-forge-ai'));
                }
                if (is_file($target_path) && !unlink($target_path)) {
                    throw new RuntimeException(__('Unable to remove the Picostrap bundle created by the reverted operation.', 'livecanvas-forge-ai'));
                }
                $bundle_result = [
                    'ok' => true,
                    'operation' => 'remove_created_bundle',
                    'absolute_path' => $target_path,
                ];
            }
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'message' => $throwable->getMessage(),
                'bundle' => $bundle_result,
            ];
        }

        $this->restore_theme_mods((array) ($snapshot['theme_mods'] ?? []));

        return [
            'ok' => !empty($bundle_result['ok']),
            'dry_run' => false,
            'operation' => $plan,
            'bundle' => $bundle_result,
            'theme_mods_restored' => array_keys((array) ($snapshot['theme_mods'] ?? [])),
        ];
    }

    private function get_bundle_relative_path(): string {
        $subfolder = function_exists('picostrap_get_css_optional_subfolder_name')
            ? (string) picostrap_get_css_optional_subfolder_name()
            : 'css-output/';
        $filename = function_exists('picostrap_get_complete_css_filename')
            ? (string) picostrap_get_complete_css_filename()
            : 'bundle.css';

        return trim($subfolder, '/\\') . '/' . ltrim($filename, '/\\');
    }

    private function ensure_picostrap_helpers_loaded(): void {
        if (!function_exists('picostrap_get_complete_css_filename')) {
            $enqueues = trailingslashit(get_template_directory()) . 'inc/enqueues.php';

            if (is_readable($enqueues)) {
                require_once $enqueues;
            }
        }
    }

    private function snapshot_theme_mods(array $keys): array {
        $mods = function_exists('get_theme_mods') ? (array) get_theme_mods() : [];
        $snapshot = [];

        foreach ($keys as $key) {
            $exists = array_key_exists($key, $mods);
            $snapshot[$key] = [
                'exists' => $exists,
                'value' => $exists ? $mods[$key] : get_theme_mod($key, null),
            ];
        }

        return $snapshot;
    }

    private function restore_theme_mods(array $snapshot): void {
        foreach ($snapshot as $key => $state) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !is_array($state)) {
                continue;
            }

            if (!empty($state['exists'])) {
                set_theme_mod($key, $state['value'] ?? '');
            } elseif (function_exists('remove_theme_mod')) {
                remove_theme_mod($key);
            }
        }
    }
}
