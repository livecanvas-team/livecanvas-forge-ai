<?php

defined('ABSPATH') || exit;

final class LCFA_Power_Mode {
    public function get_state(array $connections, array $snapshot = []): array {
        $setting = sanitize_key((string) ($connections['power_mode'] ?? 'auto'));
        if (!in_array($setting, ['auto', 'enabled', 'disabled'], true)) {
            $setting = 'auto';
        }

        $environment_type = $this->get_environment_type();
        $site_mode = sanitize_key((string) (($snapshot['site_mode'] ?? '') ?: ($snapshot['mode'] ?? '')));
        if (!in_array($site_mode, ['local', 'remote', 'hybrid'], true)) {
            $site_mode = $this->looks_like_local_site() ? 'local' : 'remote';
        }

        $is_dev = in_array($environment_type, ['local', 'development'], true) || $site_mode === 'local';
        $auto_enabled = $is_dev && $site_mode !== 'remote';
        if (in_array($environment_type, ['staging', 'production'], true) && $site_mode !== 'local') {
            $auto_enabled = false;
        }
        $enabled = $setting === 'enabled' || ($setting === 'auto' && $auto_enabled);

        if ($setting === 'disabled') {
            $enabled = false;
        }

        $status = $enabled ? 'enabled' : 'disabled';
        $reason = $enabled
            ? __('Power Mode is available for trusted local/development agent sessions.', 'livecanvas-forge-ai')
            : __('Power Mode is off unless an administrator explicitly enables it for this site.', 'livecanvas-forge-ai');

        if ($setting === 'enabled' && !$auto_enabled) {
            $reason = __('Power Mode was explicitly enabled by an administrator. Keep this off on production unless backups and review workflows are in place.', 'livecanvas-forge-ai');
        }

        return [
            'setting'          => $setting,
            'status'           => $status,
            'enabled'          => $enabled,
            'auto_enabled'     => $auto_enabled,
            'site_mode'        => $site_mode,
            'environment_type' => $environment_type,
            'reason'           => $reason,
            'available_tools'  => [
                'content-patch',
                'theme-file-read',
                'theme-file-write',
                'media-upload',
                'picostrap-compile',
                'wp-debug',
                'cache-flush',
                'polylang-tools',
                'seo-tools',
                'visual-check',
            ],
            'implemented'      => true,
        ];
    }

    private function get_environment_type(): string {
        if (function_exists('wp_get_environment_type')) {
            $type = sanitize_key((string) wp_get_environment_type());
            if ($type !== '') {
                return $type;
            }
        }

        if (defined('WP_ENVIRONMENT_TYPE')) {
            $type = sanitize_key((string) WP_ENVIRONMENT_TYPE);
            if ($type !== '') {
                return $type;
            }
        }

        return 'production';
    }

    private function looks_like_local_site(): bool {
        if (!function_exists('home_url')) {
            return false;
        }

        $url = (string) home_url('/');
        $host = function_exists('wp_parse_url')
            ? (string) wp_parse_url($url, PHP_URL_HOST)
            : (string) parse_url($url, PHP_URL_HOST);
        $host = strtolower($host);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || $this->ends_with($host, '.local')
            || $this->ends_with($host, '.test')
            || $this->ends_with($host, '.localhost');
    }

    private function ends_with(string $value, string $suffix): bool {
        if ($suffix === '') {
            return true;
        }

        return substr($value, -strlen($suffix)) === $suffix;
    }
}
