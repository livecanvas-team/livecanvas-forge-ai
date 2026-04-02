<?php

defined('ABSPATH') || exit;

final class LCFA_WindPress_Bridge {
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

    private function is_available(): bool {
        return $this->environment->is_windpress_active()
            && class_exists('\WindPress\WindPress\Core\Volume')
            && class_exists('\WindPress\WindPress\Core\Cache');
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
