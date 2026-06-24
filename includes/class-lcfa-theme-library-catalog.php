<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Catalog {
    private const DEFAULT_CATALOG_URL = 'https://raw.githubusercontent.com/livecanvas-team/livecanvas-picowind-onepage-themes/main/catalog.json';
    private const FALLBACK_CATALOG_URL = 'https://raw.githubusercontent.com/livecanvas-team/livecanvas-forge-ai/main/examples/theme-library/catalog.json';
    private const CACHE_KEY = 'lcfa_theme_library_catalog';
    private const CACHE_TTL = 900;

    public function get_catalog(bool $force = false): array {
        $cached = get_transient(self::CACHE_KEY);
        if (!$force && is_array($cached) && (int) ($cached['schema'] ?? 0) === 1) {
            return $cached;
        }

        $url = $this->get_catalog_url();
        if ($url === '') {
            return $this->error(__('Theme Library catalog URL is empty.', 'livecanvas-forge-ai'));
        }

        $requested_url = $url;
        $response = $this->request_catalog($url);
        if (empty($response['ok']) && $url !== self::FALLBACK_CATALOG_URL) {
            $fallback = $this->request_catalog(self::FALLBACK_CATALOG_URL);
            if (!empty($fallback['ok'])) {
                $response = $fallback;
                $url = self::FALLBACK_CATALOG_URL;
            }
        }

        if (empty($response['ok'])) {
            return $this->error((string) ($response['message'] ?? __('Theme Library catalog is unavailable.', 'livecanvas-forge-ai')), [
                'requested_url' => $requested_url,
                'fallback_url'  => self::FALLBACK_CATALOG_URL,
            ]);
        }

        $payload = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($payload)) {
            return $this->error(__('Theme Library catalog JSON is invalid.', 'livecanvas-forge-ai'));
        }

        $catalog = $this->normalize_catalog($payload, $url);
        set_transient(self::CACHE_KEY, $catalog, self::CACHE_TTL);

        return $catalog;
    }

    public function get_theme(string $slug, bool $force = false): array {
        $slug = sanitize_key($slug);
        $catalog = $this->get_catalog($force);
        foreach ((array) ($catalog['themes'] ?? []) as $theme) {
            if ((string) ($theme['slug'] ?? '') === $slug) {
                return [
                    'ok'    => true,
                    'theme' => $theme,
                ];
            }
        }

        return $this->error(__('Theme Library item was not found.', 'livecanvas-forge-ai'));
    }

    public function get_catalog_url(): string {
        $url = self::DEFAULT_CATALOG_URL;
        if (function_exists('apply_filters')) {
            $url = (string) apply_filters('lcfa_theme_library_catalog_url', $url);
        }

        return esc_url_raw(trim($url));
    }

    private function request_catalog(string $url): array {
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'LiveCanvas AI Bridge/' . (defined('LCFA_VERSION') ? LCFA_VERSION : 'unknown'),
            ],
        ]);

        if (is_wp_error($response)) {
            return $this->error(__('Theme Library catalog request failed.', 'livecanvas-forge-ai'));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        if ($status !== 200 || $body === '') {
            return $this->error(__('Theme Library catalog is unavailable.', 'livecanvas-forge-ai'), [
                'status' => $status,
            ]);
        }

        return [
            'ok'   => true,
            'body' => $body,
        ];
    }

    private function normalize_catalog(array $payload, string $source_url): array {
        $raw_themes = [];
        if (isset($payload['themes']) && is_array($payload['themes'])) {
            $raw_themes = $payload['themes'];
        } elseif ($this->is_list($payload)) {
            $raw_themes = $payload;
        }

        $themes = [];
        $errors = [];
        foreach ($raw_themes as $index => $raw_theme) {
            if (!is_array($raw_theme)) {
                $errors[] = sprintf('Theme entry %d is not an object.', (int) $index);
                continue;
            }

            $theme = $this->normalize_theme($raw_theme);
            if (empty($theme['ok'])) {
                $errors[] = (string) ($theme['message'] ?? sprintf('Theme entry %d is invalid.', (int) $index));
                continue;
            }

            $themes[] = $theme['theme'];
        }

        return [
            'ok'         => true,
            'schema'     => 1,
            'source_url' => $source_url,
            'checked_at' => current_time('mysql', true),
            'themes'     => $themes,
            'errors'     => $errors,
        ];
    }

    private function normalize_theme(array $raw): array {
        $slug = sanitize_key((string) ($raw['slug'] ?? $raw['id'] ?? ''));
        $name = sanitize_text_field((string) ($raw['name'] ?? $raw['title'] ?? $slug));
        $version = sanitize_text_field((string) ($raw['version'] ?? ''));
        $package_url = esc_url_raw((string) ($raw['package_url'] ?? $raw['zip_url'] ?? $raw['download_url'] ?? ''));
        $checksum = $this->normalize_checksum((string) ($raw['checksum'] ?? $raw['sha256'] ?? ''));
        $screenshot = esc_url_raw((string) ($raw['screenshot'] ?? $raw['screenshot_url'] ?? ''));

        if ($screenshot === '' && isset($raw['screenshots']) && is_array($raw['screenshots'])) {
            $first = reset($raw['screenshots']);
            $screenshot = esc_url_raw(is_scalar($first) ? (string) $first : (string) ($first['url'] ?? ''));
        }

        if ($slug === '' || $name === '' || $version === '' || $package_url === '' || $checksum === '' || $screenshot === '') {
            return $this->error(sprintf(
                'Theme "%s" is missing slug, name, version, package_url, checksum, or screenshot.',
                $slug !== '' ? $slug : 'unknown'
            ));
        }

        return [
            'ok'    => true,
            'theme' => [
                'slug'        => $slug,
                'name'        => $name,
                'version'     => $version,
                'description' => sanitize_text_field((string) ($raw['description'] ?? '')),
                'category'    => sanitize_text_field((string) ($raw['category'] ?? '')),
                'screenshot'  => $screenshot,
                'package_url' => $package_url,
                'checksum'    => $checksum,
                'metadata_url'=> esc_url_raw((string) ($raw['metadata_url'] ?? '')),
            ],
        ];
    }

    private function is_list(array $value): bool {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalize_checksum(string $checksum): string {
        $checksum = strtolower(trim($checksum));
        $checksum = preg_replace('/^sha256[:=]/', '', $checksum);

        return preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : '';
    }

    private function error(string $message, array $extra = []): array {
        return array_merge([
            'ok'      => false,
            'message' => $message,
        ], $extra);
    }
}
