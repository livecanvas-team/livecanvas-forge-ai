<?php

defined('ABSPATH') || exit;

final class LCFA_Remote_Client {
    private const STATUS_TTL = 30;

    private ?array $status_cache = null;

    public function is_configured(): bool {
        $connections = LCFA_Settings::get_connections();

        return $this->get_site_url($connections) !== ''
            && trim((string) ($connections['remote_username'] ?? '')) !== ''
            && trim((string) ($connections['remote_application_password'] ?? '')) !== '';
    }

    public function get_status(): array {
        if ($this->status_cache !== null) {
            return $this->status_cache;
        }

        $connections = LCFA_Settings::get_connections();
        $site_url    = $this->get_site_url($connections);

        if (!$this->is_configured()) {
            $this->status_cache = [
                'configured' => false,
                'available'  => false,
                'endpoint'   => $site_url !== '' ? $this->build_rest_base($site_url) : '',
                'message'    => __('Remote site URL, username, and Application Password are required before remote execution can start.', 'livecanvas-forge-ai'),
            ];

            return $this->status_cache;
        }

        $cache_key = $this->get_status_cache_key($connections);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $this->status_cache = $cached;

            return $this->status_cache;
        }

        $response = $this->request('GET', 'snapshot');

        if (is_wp_error($response)) {
            $this->status_cache = [
                'configured' => true,
                'available'  => false,
                'endpoint'   => $this->build_rest_base($site_url),
                'message'    => $response->get_error_message(),
            ];

            set_transient($cache_key, $this->status_cache, self::STATUS_TTL);

            return $this->status_cache;
        }

        $snapshot = is_array($response['snapshot'] ?? null) ? $response['snapshot'] : [];
        $mcp      = is_array($response['mcp'] ?? null) ? $response['mcp'] : [];
        $mcp_adapter = is_array($snapshot['mcp_adapter'] ?? null) ? $snapshot['mcp_adapter'] : [];

        $this->status_cache = [
            'configured' => true,
            'available'  => true,
            'endpoint'   => $this->build_rest_base($site_url),
            'message'    => __('Remote companion reachable.', 'livecanvas-forge-ai'),
            'snapshot'   => [
                'theme'             => (string) ($snapshot['current_theme_name'] ?? ''),
                'framework'         => (string) ($snapshot['detected_framework'] ?? ''),
                'livecanvas_active' => !empty($snapshot['livecanvas_active']),
                'windpress_active'  => !empty($snapshot['windpress_active']),
                'site_mode'         => (string) ($snapshot['site_mode'] ?? ''),
            ],
            'mcp_adapter' => [
                'available'     => !empty($mcp_adapter['available']),
                'custom_server' => is_array($mcp_adapter['custom_server'] ?? null) ? $mcp_adapter['custom_server'] : [],
                'remote_proxy'  => is_array($mcp_adapter['remote_proxy'] ?? null) ? $mcp_adapter['remote_proxy'] : [],
            ],
            'mcp'        => [
                'enabled'         => !empty($mcp['enabled']),
                'rest_base'       => (string) ($mcp['rest_base'] ?? $this->build_rest_base($site_url)),
                'token'           => (string) ($mcp['token'] ?? ''),
                'filesystem_mode' => (string) ($mcp['filesystem_mode'] ?? ''),
            ],
        ];

        set_transient($cache_key, $this->status_cache, self::STATUS_TTL);

        return $this->status_cache;
    }

    public function get_actions() {
        return $this->request('GET', 'command/actions');
    }

    public function get_inventory() {
        return $this->request('GET', 'inventory');
    }

    public function run_command(array $payload) {
        return $this->request('POST', 'command', $payload);
    }

    private function request(string $method, string $path, array $body = []) {
        $connections = LCFA_Settings::get_connections();
        $site_url    = $this->get_site_url($connections);

        if ($site_url === '') {
            return new WP_Error('lcfa_remote_missing_site', __('Remote site URL is missing.', 'livecanvas-forge-ai'));
        }

        $username = trim((string) ($connections['remote_username'] ?? ''));
        $password = trim((string) ($connections['remote_application_password'] ?? ''));

        if ($username === '' || $password === '') {
            return new WP_Error('lcfa_remote_missing_credentials', __('Remote username or Application Password is missing.', 'livecanvas-forge-ai'));
        }

        $options = [
            'method'  => strtoupper($method),
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Accept'        => 'application/json',
            ],
        ];

        if ($options['method'] !== 'GET') {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = wp_remote_request($this->build_rest_base($site_url) . ltrim($path, '/'), $options);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $payload     = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'lcfa_remote_http_error',
                $this->get_http_error_message($status_code, is_array($payload) ? $payload : [])
            );
        }

        return is_array($payload) ? $payload : [];
    }

    private function get_site_url(array $connections): string {
        return trim((string) ($connections['remote_site_url'] ?? ''));
    }

    private function build_rest_base(string $site_url): string {
        return trailingslashit(untrailingslashit($site_url)) . 'wp-json/lcfa/v1/';
    }

    private function get_http_error_message(int $status_code, array $payload): string {
        if (!empty($payload['message']) && is_string($payload['message'])) {
            return $payload['message'];
        }

        if (!empty($payload['error']) && is_string($payload['error'])) {
            return $payload['error'];
        }

        switch ($status_code) {
            case 401:
                return __('Remote authentication failed. Verify the username and Application Password.', 'livecanvas-forge-ai');
            case 403:
                return __('Remote authentication worked, but the user does not have enough permissions for the companion routes.', 'livecanvas-forge-ai');
            case 404:
                return __('Remote companion routes were not found. Install and activate LiveCanvas AI Bridge on the remote site.', 'livecanvas-forge-ai');
            default:
                return sprintf(__('Unexpected remote HTTP %d response.', 'livecanvas-forge-ai'), $status_code);
        }
    }

    private function get_status_cache_key(array $connections): string {
        return 'lcfa_remote_status_' . md5(implode('|', [
            trim((string) ($connections['remote_site_url'] ?? '')),
            trim((string) ($connections['remote_username'] ?? '')),
            trim((string) ($connections['remote_application_password'] ?? '')),
            LCFA_VERSION,
        ]));
    }
}
