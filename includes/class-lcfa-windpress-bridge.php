<?php

defined('ABSPATH') || exit;

final class LCFA_WindPress_Bridge {
    private const RUNTIME_BACKUP_SUBDIRECTORY = 'livecanvas-forge-ai/windpress-runtime-backups';

    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_status(): array {
        $installed = $this->environment->is_plugin_installed('windpress');
        $active    = $this->environment->is_windpress_active();

        $status = [
            'installed' => $installed,
            'active'    => $active,
            'available' => false,
        ];

        if (!$active || !class_exists('WIND_PRESS')) {
            return $status;
        }

        $config = $this->get_config();
        $cache  = $this->get_cache_summary();
        $data   = $this->get_data_summary();

        return array_merge($status, [
            'available'        => true,
            'version'          => $config['version'] ?? (defined('WIND_PRESS::VERSION') ? constant('WIND_PRESS::VERSION') : ''),
            'tailwind_version' => $config['tailwind_version'] ?? $this->get_tailwind_version(),
            'performance_mode' => $this->get_performance_mode(),
            'cache'            => $cache,
            'cache_status'     => $this->get_cache_status(),
            'data'             => $data,
            'providers'        => $this->get_providers(),
            'volume_handlers'  => $this->get_volume_handlers(),
        ]);
    }

    public function get_volume_entries(array $args = []): array {
        if (!$this->is_available()) {
            return [
                'available' => false,
                'entries'   => [],
            ];
        }

        $include_content = !empty($args['include_content']);
        $handler         = sanitize_key((string) ($args['handler'] ?? ''));
        $extension       = strtolower((string) ($args['extension'] ?? ''));
        $limit           = max(1, min(500, absint($args['limit'] ?? 100)));

        $entries = \WindPress\WindPress\Core\Volume::get_entries();
        $items   = [];

        foreach ($entries as $entry) {
            $relative_path = (string) ($entry['relative_path'] ?? '');
            $item_handler  = sanitize_key((string) ($entry['handler'] ?? ''));
            $item_ext      = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));

            if ($handler !== '' && $item_handler !== $handler) {
                continue;
            }

            if ($extension !== '' && $item_ext !== ltrim($extension, '.')) {
                continue;
            }

            $item = [
                'name'          => (string) ($entry['name'] ?? ''),
                'relative_path' => $relative_path,
                'handler'       => $item_handler,
                'readonly'      => !empty($entry['readonly']),
                'signature'     => (string) ($entry['signature'] ?? ''),
                'path_on_disk'  => (string) ($entry['path_on_disk'] ?? ''),
                'extension'     => $item_ext,
            ];

            if ($include_content) {
                $item['content'] = (string) ($entry['content'] ?? '');
            } else {
                $item['bytes'] = strlen((string) ($entry['content'] ?? ''));
            }

            $items[] = $item;

            if (count($items) >= $limit) {
                break;
            }
        }

        return [
            'available'       => true,
            'include_content' => $include_content,
            'handler'         => $handler,
            'extension'       => $extension,
            'limit'           => $limit,
            'entries'         => $items,
        ];
    }

    public function get_volume_handlers(): array {
        if (!$this->is_available()) {
            return [];
        }

        return array_values(array_map(static function (array $handler): array {
            return [
                'value'       => (string) ($handler['value'] ?? ''),
                'label'       => (string) ($handler['label'] ?? ''),
                'description' => (string) ($handler['description'] ?? ''),
            ];
        }, \WindPress\WindPress\Core\Volume::get_available_handlers()));
    }

    public function get_providers(): array {
        if (!class_exists('\WindPress\WindPress\Core\Cache')) {
            return [];
        }

        return array_values(array_map(static function (array $provider): array {
            return [
                'id'          => (string) ($provider['id'] ?? ''),
                'name'        => (string) ($provider['name'] ?? ''),
                'description' => (string) ($provider['description'] ?? ''),
                'type'        => (string) ($provider['type'] ?? ''),
                'enabled'     => !empty($provider['enabled']),
                'homepage'    => (string) ($provider['homepage'] ?? ''),
                'installed'   => isset($provider['is_installed_active']) && is_callable($provider['is_installed_active'])
                    ? (bool) call_user_func($provider['is_installed_active'])
                    : null,
            ];
        }, \WindPress\WindPress\Core\Cache::get_providers()));
    }

    public function scan_provider(string $provider_id, array $metadata = [], bool $decode_contents = true): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        $provider_id = sanitize_key($provider_id);
        $provider    = null;

        foreach (\WindPress\WindPress\Core\Cache::get_providers() as $candidate) {
            if (($candidate['id'] ?? '') === $provider_id) {
                $provider = $candidate;
                break;
            }
        }

        if (!$provider) {
            return [
                'ok'      => false,
                'message' => __('WindPress provider not found.', 'livecanvas-forge-ai'),
            ];
        }

        try {
            $result = \WindPress\WindPress\Core\Cache::fetch_contents($provider['callback'], $metadata);
        } catch (\Throwable $throwable) {
            return [
                'ok'      => false,
                'message' => $throwable->getMessage(),
            ];
        }

        $contents = is_array($result['contents'] ?? null) ? $result['contents'] : [];

        if ($decode_contents) {
            $contents = array_map(static function (array $item): array {
                $decoded = base64_decode((string) ($item['content'] ?? ''), true);

                if ($decoded !== false) {
                    $item['decoded_content'] = $decoded;
                }

                return $item;
            }, $contents);
        }

        return [
            'ok'       => true,
            'provider' => [
                'id'          => (string) ($provider['id'] ?? ''),
                'name'        => (string) ($provider['name'] ?? ''),
                'description' => (string) ($provider['description'] ?? ''),
                'type'        => (string) ($provider['type'] ?? ''),
            ],
            'metadata' => is_array($result['metadata'] ?? null) ? $result['metadata'] : [],
            'contents' => $contents,
        ];
    }

    public function save_volume_entries(array $entries): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        $payload = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $payload[] = [
                'name'          => sanitize_file_name((string) ($entry['name'] ?? '')),
                'relative_path' => sanitize_text_field((string) ($entry['relative_path'] ?? '')),
                'content'       => (string) ($entry['content'] ?? ''),
                'handler'       => sanitize_key((string) ($entry['handler'] ?? 'internal')),
                'signature'     => sanitize_text_field((string) ($entry['signature'] ?? '')),
                'readonly'      => !empty($entry['readonly']),
            ];
        }

        \WindPress\WindPress\Core\Volume::save_entries($payload);

        return [
            'ok'           => true,
            'saved_entries'=> count($payload),
            'message'      => __('WindPress volume entries stored.', 'livecanvas-forge-ai'),
        ];
    }

    public function reset_volume_entry(string $relative_path): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        if (!class_exists('\WindPress\WindPress\Abilities\Abilities\ResetVolumeEntry')) {
            return [
                'ok'      => false,
                'message' => __('WindPress reset ability is not available.', 'livecanvas-forge-ai'),
            ];
        }

        $result = \WindPress\WindPress\Abilities\Abilities\ResetVolumeEntry::execute([
            'relative_path' => sanitize_text_field($relative_path),
        ]);

        if (is_wp_error($result)) {
            return [
                'ok'      => false,
                'message' => $result->get_error_message(),
            ];
        }

        return [
            'ok'      => !empty($result['success']),
            'message' => (string) ($result['message'] ?? __('WindPress volume entry reset.', 'livecanvas-forge-ai')),
            'content' => (string) ($result['content'] ?? ''),
        ];
    }

    public function save_theme_json($theme_json): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        $blob = is_array($theme_json) ? wp_json_encode($theme_json) : (string) $theme_json;

        if ($blob === '') {
            return [
                'ok'      => false,
                'message' => __('A valid theme.json payload is required.', 'livecanvas-forge-ai'),
            ];
        }

        \WindPress\WindPress\Core\Cache::save_theme_json($blob);

        return [
            'ok'      => true,
            'message' => __('WindPress theme.json cache stored.', 'livecanvas-forge-ai'),
            'cache'   => $this->get_cache_summary(),
        ];
    }

    public function save_cache_css(string $css, string $sourcemap = '', ?int $full_build = null): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        if ($css === '') {
            return [
                'ok'      => false,
                'message' => __('A CSS payload is required.', 'livecanvas-forge-ai'),
            ];
        }

        \WindPress\WindPress\Core\Cache::save_cache($css);

        if ($sourcemap !== '') {
            \WindPress\WindPress\Core\Cache::save_sourcemap($sourcemap);
        }

        if ($full_build !== null && $full_build > 0) {
            wp_cache_set('last_full_build', $full_build, 'windpress');
        }

        return [
            'ok'      => true,
            'message' => __('WindPress CSS cache stored.', 'livecanvas-forge-ai'),
            'cache'   => $this->get_cache_summary(),
        ];
    }

    public function flush_runtime_cache(): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        if (class_exists('\WindPress\WindPress\Utils\Cache')) {
            \WindPress\WindPress\Utils\Cache::flush_cache_plugin();
        }

        wp_cache_flush();

        return [
            'ok'      => true,
            'message' => __('WindPress runtime cache flushed.', 'livecanvas-forge-ai'),
            'cache'   => $this->get_cache_summary(),
        ];
    }

    public function ensure_picowind_runtime(): array {
        if (!$this->is_available()) {
            return [
                'ok'      => false,
                'message' => __('WindPress is not active on this site.', 'livecanvas-forge-ai'),
            ];
        }

        if (class_exists('\WindPress\WindPress\Utils\Config')) {
            try {
                \WindPress\WindPress\Utils\Config::set('integration.picowind.enabled', true);

                $mode = (string) \WindPress\WindPress\Utils\Config::get('performance.mode', 'hybrid');
                if (!in_array($mode, ['cached', 'hybrid', 'compiler'], true)) {
                    \WindPress\WindPress\Utils\Config::set('performance.mode', 'hybrid');
                }
            } catch (\Throwable $throwable) {
                return [
                    'ok'      => false,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        $main_css_path = trailingslashit($this->get_windpress_data_directory()) . 'main.css';
        $main_css_dir  = dirname($main_css_path);

        if (!is_dir($main_css_dir) && !wp_mkdir_p($main_css_dir)) {
            return [
                'ok'      => false,
                'message' => __('WindPress data directory could not be created.', 'livecanvas-forge-ai'),
            ];
        }

        $content = '';
        if (is_readable($main_css_path)) {
            $content = (string) file_get_contents($main_css_path);
        }

        if ($content === '') {
            $stub = trailingslashit(WP_PLUGIN_DIR) . 'windpress/stubs/tailwindcss-v4/main.css';
            if (is_readable($stub)) {
                $content = (string) file_get_contents($stub);
            }
        }

        if ($content === '') {
            $content = "@layer theme, base, components, utilities;\n\n"
                . "@import \"tailwindcss/theme.css\" layer(theme) theme(static);\n"
                . "@import \"tailwindcss/preflight.css\" layer(base);\n"
                . "@import \"tailwindcss/utilities.css\" layer(utilities);\n";
        }

        $picowind_import = '@import "./@picowind/tailwind.css";';
        $content = (string) preg_replace(
            '~\/\*\s*@import\s+["\']\./@picowind/tailwind\.css["\']\s*;\s*\*\/~',
            $picowind_import,
            $content
        );

        if (!preg_match('~@import\s+["\']\./@picowind/tailwind\.css["\']\s*;~', $content)) {
            $content = rtrim($content) . "\n\n" . $picowind_import . "\n";
        }

        $preflight_import = '@import "tailwindcss/preflight.css" layer(base);';
        $content = (string) preg_replace(
            '~\/\*\s*@import\s+["\']tailwindcss/preflight\.css["\']\s+layer\(base\)\s*;\s*\*\/~',
            $preflight_import,
            $content
        );

        if (file_put_contents($main_css_path, $content) === false) {
            return [
                'ok'      => false,
                'message' => __('WindPress main.css could not be written.', 'livecanvas-forge-ai'),
            ];
        }

        $cache_path = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE);
        if (is_readable($cache_path)) {
            $cached_css = (string) file_get_contents($cache_path);
            if (preg_match('/@(import|tailwind)\b/', $cached_css)) {
                @unlink($cache_path);
            }
        }

        return [
            'ok'       => true,
            'message'  => __('WindPress Picowind runtime is initialized.', 'livecanvas-forge-ai'),
            'main_css' => $main_css_path,
            'cache'    => $this->get_cache_summary(),
        ];
    }

    /**
     * Capture the mutable WindPress runtime before a Theme Library import.
     * Large files stay on disk; rollback records only keep metadata and hashes.
     */
    public function capture_runtime_state(string $audit_id): array {
        $audit_id = sanitize_key($audit_id);
        if ($audit_id === '') {
            return [
                'ok'      => false,
                'message' => __('A valid audit ID is required for the WindPress runtime backup.', 'livecanvas-forge-ai'),
            ];
        }

        if (!$this->is_available()) {
            return [
                'ok'        => true,
                'available' => false,
                'audit_id'  => $audit_id,
                'message'   => __('WindPress is unavailable; no runtime state needed to be captured.', 'livecanvas-forge-ai'),
            ];
        }

        $backup_root = $this->get_runtime_backup_root();
        if ($backup_root === '') {
            return [
                'ok'      => false,
                'message' => __('The AI Bridge backup directory is unavailable.', 'livecanvas-forge-ai'),
            ];
        }

        $backup_directory = wp_normalize_path(trailingslashit($backup_root) . $audit_id);
        if (!is_dir($backup_directory) && !wp_mkdir_p($backup_directory)) {
            return [
                'ok'      => false,
                'message' => __('The WindPress runtime backup directory could not be created.', 'livecanvas-forge-ai'),
            ];
        }

        $this->protect_runtime_backup_root($backup_root);

        $option_name = $this->get_windpress_options_name();
        $missing     = new \stdClass();
        $option_value = get_option($option_name, $missing);
        $state = [
            'ok'               => true,
            'available'        => true,
            'audit_id'         => $audit_id,
            'captured_at'      => current_time('mysql', true),
            'backup_directory' => $audit_id,
            'option'           => [
                'exists' => $option_value !== $missing,
                'value'  => $option_value !== $missing ? $option_value : null,
            ],
            'files'            => [],
        ];

        foreach ($this->get_runtime_file_paths() as $key => $path) {
            $path = wp_normalize_path($path);
            $exists = is_file($path);
            $file_state = [
                'exists'      => $exists,
                'bytes'       => 0,
                'sha256'      => '',
                'backup_file' => '',
            ];

            if ($exists) {
                if (!is_readable($path)) {
                    return [
                        'ok'      => false,
                        'message' => sprintf(__('WindPress runtime file is not readable: %s', 'livecanvas-forge-ai'), basename($path)),
                    ];
                }

                $backup_file = sanitize_key((string) $key) . '.bak';
                $backup_path = wp_normalize_path(trailingslashit($backup_directory) . $backup_file);
                if (!copy($path, $backup_path)) {
                    return [
                        'ok'      => false,
                        'message' => sprintf(__('WindPress runtime file could not be backed up: %s', 'livecanvas-forge-ai'), basename($path)),
                    ];
                }

                @chmod($backup_path, 0600);
                $file_state['bytes'] = (int) filesize($backup_path);
                $file_state['sha256'] = (string) hash_file('sha256', $backup_path);
                $file_state['backup_file'] = $backup_file;
            }

            $state['files'][$key] = $file_state;
        }

        return $state;
    }

    public function restore_runtime_state(array $state): array {
        if (empty($state['available'])) {
            return [
                'ok'       => true,
                'skipped'  => true,
                'restored' => [],
                'message'  => __('No WindPress runtime state needed to be restored.', 'livecanvas-forge-ai'),
            ];
        }

        $audit_id = sanitize_key((string) ($state['audit_id'] ?? ''));
        $backup_root = $this->get_runtime_backup_root();
        if ($audit_id === '' || $backup_root === '') {
            return [
                'ok'      => false,
                'message' => __('The WindPress runtime backup reference is invalid.', 'livecanvas-forge-ai'),
                'errors'  => [__('The WindPress runtime backup reference is invalid.', 'livecanvas-forge-ai')],
            ];
        }

        $backup_directory = wp_normalize_path(trailingslashit($backup_root) . $audit_id);
        $runtime_files = $this->get_runtime_file_paths();
        $errors = [];
        $restored = [];

        foreach ((array) ($state['files'] ?? []) as $key => $file_state) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !isset($runtime_files[$key]) || !is_array($file_state)) {
                $errors[] = __('A WindPress runtime backup contains an unsupported file reference.', 'livecanvas-forge-ai');
                continue;
            }

            $target_path = wp_normalize_path($runtime_files[$key]);
            if (empty($file_state['exists'])) {
                if (is_file($target_path) && !@unlink($target_path)) {
                    $errors[] = sprintf(__('WindPress runtime file could not be removed: %s', 'livecanvas-forge-ai'), basename($target_path));
                } else {
                    $restored[] = $key;
                }
                continue;
            }

            $expected_backup_file = $key . '.bak';
            $backup_file = sanitize_file_name((string) ($file_state['backup_file'] ?? ''));
            if ($backup_file !== $expected_backup_file) {
                $errors[] = sprintf(__('WindPress runtime backup reference is invalid for %s.', 'livecanvas-forge-ai'), $key);
                continue;
            }

            $backup_path = wp_normalize_path(trailingslashit($backup_directory) . $backup_file);
            if (!is_file($backup_path) || !is_readable($backup_path)) {
                $errors[] = sprintf(__('WindPress runtime backup file is missing: %s', 'livecanvas-forge-ai'), $backup_file);
                continue;
            }

            $expected_hash = strtolower((string) ($file_state['sha256'] ?? ''));
            $actual_hash = (string) hash_file('sha256', $backup_path);
            if ($expected_hash === '' || !hash_equals($expected_hash, $actual_hash)) {
                $errors[] = sprintf(__('WindPress runtime backup checksum failed: %s', 'livecanvas-forge-ai'), $backup_file);
                continue;
            }

            $target_directory = dirname($target_path);
            if (!is_dir($target_directory) && !wp_mkdir_p($target_directory)) {
                $errors[] = sprintf(__('WindPress runtime directory could not be created: %s', 'livecanvas-forge-ai'), basename($target_directory));
                continue;
            }

            $temporary_path = $target_path . '.lcfa-restore-' . uniqid('', true);
            if (!copy($backup_path, $temporary_path)) {
                @unlink($temporary_path);
                $errors[] = sprintf(__('WindPress runtime file could not be restored: %s', 'livecanvas-forge-ai'), basename($target_path));
                continue;
            }

            if (!@rename($temporary_path, $target_path) && !copy($backup_path, $target_path)) {
                @unlink($temporary_path);
                $errors[] = sprintf(__('WindPress runtime file could not be restored: %s', 'livecanvas-forge-ai'), basename($target_path));
                continue;
            }
            @unlink($temporary_path);

            $restored[] = $key;
        }

        $option = is_array($state['option'] ?? null) ? $state['option'] : [];
        $option_name = $this->get_windpress_options_name();
        if (!empty($option['exists'])) {
            update_option($option_name, $option['value'] ?? null, false);
        } else {
            delete_option($option_name);
        }
        $restored[] = 'options';

        if (class_exists('\WindPress\WindPress\Utils\Cache')) {
            \WindPress\WindPress\Utils\Cache::flush_cache_plugin();
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return [
            'ok'       => empty($errors),
            'message'  => empty($errors)
                ? __('WindPress runtime state restored.', 'livecanvas-forge-ai')
                : __('WindPress runtime state was restored with errors.', 'livecanvas-forge-ai'),
            'errors'   => $errors,
            'restored' => array_values(array_unique($restored)),
        ];
    }

    private function is_available(): bool {
        return $this->environment->is_windpress_active()
            && class_exists('\WindPress\WindPress\Core\Volume')
            && class_exists('\WindPress\WindPress\Core\Cache');
    }

    private function get_windpress_options_name(): string {
        if (defined('WIND_PRESS::WP_OPTION')) {
            return (string) constant('WIND_PRESS::WP_OPTION') . '_options';
        }

        return 'windpress_options';
    }

    private function get_runtime_file_paths(): array {
        $files = [
            'main_css' => trailingslashit($this->get_windpress_data_directory()) . 'main.css',
        ];

        if (!class_exists('\WindPress\WindPress\Core\Cache')) {
            return $files;
        }

        $cache_class = '\WindPress\WindPress\Core\Cache';
        $files['cache_css'] = $cache_class::get_cache_path($cache_class::CSS_CACHE_FILE);
        $files['theme_json'] = $cache_class::get_cache_path($cache_class::THEME_JSON_FILE);

        if (defined($cache_class . '::CSS_SOURCEMAP_FILE')) {
            $files['cache_sourcemap'] = $cache_class::get_cache_path(constant($cache_class . '::CSS_SOURCEMAP_FILE'));
        }

        return array_map('wp_normalize_path', $files);
    }

    private function get_windpress_data_directory(): string {
        if (class_exists('\WindPress\WindPress\Core\Volume') && method_exists('\WindPress\WindPress\Core\Volume', 'data_dir_path')) {
            return wp_normalize_path((string) \WindPress\WindPress\Core\Volume::data_dir_path());
        }

        $uploads = wp_upload_dir(null, false);
        $base = !empty($uploads['basedir']) ? (string) $uploads['basedir'] : trailingslashit(WP_CONTENT_DIR) . 'uploads';
        $relative = defined('WIND_PRESS::DATA_DIR') ? (string) constant('WIND_PRESS::DATA_DIR') : '/windpress/data/';

        return wp_normalize_path(rtrim($base, '/\\') . '/' . trim($relative, '/\\'));
    }

    private function get_runtime_backup_root(): string {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return wp_normalize_path(trailingslashit((string) $uploads['basedir']) . self::RUNTIME_BACKUP_SUBDIRECTORY);
    }

    private function protect_runtime_backup_root(string $backup_root): void {
        if (!is_dir($backup_root) && !wp_mkdir_p($backup_root)) {
            return;
        }

        $index_path = trailingslashit($backup_root) . 'index.php';
        if (!is_file($index_path)) {
            @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
        }

        $htaccess_path = trailingslashit($backup_root) . '.htaccess';
        if (!is_file($htaccess_path)) {
            @file_put_contents($htaccess_path, "Require all denied\nDeny from all\n");
        }
    }

    private function get_config(): array {
        if (class_exists('\WindPress\WindPress\Abilities\Abilities\GetConfig')) {
            return \WindPress\WindPress\Abilities\Abilities\GetConfig::execute();
        }

        return [];
    }

    private function get_tailwind_version(): int {
        if (class_exists('\WindPress\WindPress\Core\Runtime')) {
            return (int) \WindPress\WindPress\Core\Runtime::tailwindcss_version();
        }

        return 0;
    }

    private function get_performance_mode(): string {
        if (class_exists('\WindPress\WindPress\Utils\Config')) {
            return (string) \WindPress\WindPress\Utils\Config::get('performance.mode', 'hybrid');
        }

        return 'unknown';
    }

    private function get_cache_summary(): array {
        if (!class_exists('\WindPress\WindPress\Core\Cache')) {
            return [];
        }

        $css_path       = \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE);
        $css_url        = \WindPress\WindPress\Core\Cache::get_cache_url(\WindPress\WindPress\Core\Cache::CSS_CACHE_FILE);
        $theme_json_path= \WindPress\WindPress\Core\Cache::get_cache_path(\WindPress\WindPress\Core\Cache::THEME_JSON_FILE);
        $theme_json_url = \WindPress\WindPress\Core\Cache::get_cache_url(\WindPress\WindPress\Core\Cache::THEME_JSON_FILE);

        return [
            'css'        => $this->format_file_state($css_path, $css_url),
            'theme_json' => $this->format_file_state($theme_json_path, $theme_json_url),
        ];
    }

    private function get_cache_status(): array {
        $summary         = $this->get_cache_summary();
        $last_full_build = wp_cache_get('last_full_build', 'windpress', true);

        return [
            'last_generated' => !empty($summary['css']['modified_at']) ? strtotime((string) $summary['css']['modified_at']) : null,
            'last_full_build'=> $last_full_build ?: null,
            'file_url'       => $summary['css']['url'] ?? '',
            'file_size'      => $summary['css']['bytes'] ?? 0,
        ];
    }

    private function get_data_summary(): array {
        if (!class_exists('\WindPress\WindPress\Core\Volume')) {
            return [];
        }

        $path = \WindPress\WindPress\Core\Volume::data_dir_path();
        $url  = \WindPress\WindPress\Core\Volume::data_dir_url();

        return [
            'path'   => $path,
            'url'    => $url,
            'exists' => is_dir($path),
        ];
    }

    private function format_file_state(string $path, string $url): array {
        $exists = file_exists($path) && is_readable($path);

        return [
            'path'        => $path,
            'url'         => $url,
            'exists'      => $exists,
            'bytes'       => $exists ? filesize($path) : 0,
            'modified_at' => $exists ? gmdate('c', (int) filemtime($path)) : null,
        ];
    }
}
