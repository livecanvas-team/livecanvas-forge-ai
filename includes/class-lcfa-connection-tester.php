<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Tester {
    private LCFA_Environment $environment;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Remote_Client $remote_client;

    public function __construct(LCFA_Environment $environment, LCFA_Local_MCP_Bridge $local_mcp_bridge, LCFA_Remote_Client $remote_client) {
        $this->environment       = $environment;
        $this->local_mcp_bridge  = $local_mcp_bridge;
        $this->remote_client     = $remote_client;
    }

    public function run_checks(): array {
        $connections = LCFA_Settings::get_connections();
        $checks = [
            'local_rest' => $this->test_local_rest($connections),
            'local_mcp'  => $this->test_local_mcp(),
            'remote_rest'=> $this->test_remote_rest($connections),
        ];

        $blocking_failures = array_filter($checks, static function (array $check): bool {
            return empty($check['ok']) && empty($check['skipped']);
        });
        $successful = count(array_filter($checks, static function (array $check): bool {
            return !empty($check['ok']);
        }));
        $skipped = count(array_filter($checks, static function (array $check): bool {
            return !empty($check['skipped']);
        }));

        return [
            'ok'         => count($blocking_failures) === 0,
            'checked_at' => current_time('mysql', true),
            'summary'    => count($blocking_failures) === 0
                ? sprintf(__('Connection checks completed. %1$d successful, %2$d skipped.', 'livecanvas-forge-ai'), $successful, $skipped)
                : sprintf(__('Connection checks found %1$d blocking issue(s).', 'livecanvas-forge-ai'), count($blocking_failures)),
            'checks'     => $checks,
        ];
    }

    private function test_local_rest(array $connections): array {
        $url = trim((string) ($connections['local_bridge_url'] ?? ''));

        if ($url === '') {
            $url = rest_url('lcfa/v1/');
        }

        $endpoint = trailingslashit($url) . 'mcp/status';
        $response = wp_remote_get($endpoint, [
            'timeout' => 8,
            'headers' => [
                'X-LCFA-MCP-Token' => (string) ($connections['mcp_token'] ?? ''),
                'Accept'           => 'application/json',
            ],
        ]);

        return $this->normalize_http_test(
            __('Local REST bridge', 'livecanvas-forge-ai'),
            $endpoint,
            $response,
            static function (array $payload): array {
                return [
                    'mcp_enabled'      => !empty($payload['mcp']['enabled']),
                    'filesystem_mode'  => (string) ($payload['mcp']['filesystem_mode'] ?? ''),
                    'preferred_client' => (string) ($payload['mcp']['preferred_client'] ?? ''),
                ];
            }
        );
    }

    private function test_local_mcp(): array {
        $status = $this->local_mcp_bridge->get_status();

        return [
            'label'   => __('Local MCP runtime', 'livecanvas-forge-ai'),
            'ok'      => !empty($status['available']),
            'skipped' => false,
            'message' => (string) ($status['message'] ?? ''),
            'details' => [
                'node_available' => !empty($status['node_available']),
                'node_version'   => (string) ($status['node_version'] ?? ''),
                'rest_reachable' => !empty($status['rest_reachable']),
                'script_exists'  => !empty($status['script_exists']),
                'build_available'=> !empty($status['build_available']),
            ],
        ];
    }

    private function test_remote_rest(array $connections): array {
        if (!$this->remote_client->is_configured()) {
            return [
                'label'   => __('Remote WordPress bridge', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => true,
                'message' => __('Remote credentials are not configured yet.', 'livecanvas-forge-ai'),
                'details' => [],
            ];
        }

        $status = $this->remote_client->get_status();

        return [
            'label'   => __('Remote WordPress bridge', 'livecanvas-forge-ai'),
            'ok'      => !empty($status['available']),
            'skipped' => false,
            'message' => (string) ($status['message'] ?? ''),
            'details' => [
                'endpoint'         => (string) ($status['endpoint'] ?? ''),
                'theme'            => (string) ($status['snapshot']['theme'] ?? ''),
                'framework'        => (string) ($status['snapshot']['framework'] ?? ''),
                'livecanvas_active'=> !empty($status['snapshot']['livecanvas_active']),
                'windpress_active' => !empty($status['snapshot']['windpress_active']),
                'filesystem_mode'  => (string) ($status['mcp']['filesystem_mode'] ?? ''),
            ],
        ];
    }

    private function normalize_http_test(string $label, string $endpoint, $response, callable $payload_mapper): array {
        if (is_wp_error($response)) {
            return [
                'label'   => $label,
                'ok'      => false,
                'skipped' => false,
                'message' => $response->get_error_message(),
                'details' => [
                    'endpoint' => $endpoint,
                ],
            ];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = (string) wp_remote_retrieve_body($response);
        $payload     = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            return [
                'label'   => $label,
                'ok'      => false,
                'skipped' => false,
                'message' => $this->get_http_error_message($status_code, is_array($payload) ? $payload : []),
                'details' => [
                    'endpoint'    => $endpoint,
                    'status_code' => $status_code,
                ],
            ];
        }

        return [
            'label'   => $label,
            'ok'      => true,
            'skipped' => false,
            'message' => __('Connection verified.', 'livecanvas-forge-ai'),
            'details' => array_merge([
                'endpoint'    => $endpoint,
                'status_code' => $status_code,
            ], is_array($payload) ? $payload_mapper($payload) : []),
        ];
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
                return __('Authentication failed. Verify the username and Application Password.', 'livecanvas-forge-ai');
            case 403:
                return __('The authenticated user does not have enough permissions to access the companion routes.', 'livecanvas-forge-ai');
            case 404:
                return __('The companion routes were not found on the remote site. Verify that the plugin is installed and active.', 'livecanvas-forge-ai');
            default:
                return sprintf(__('Unexpected HTTP %d while testing the connection.', 'livecanvas-forge-ai'), $status_code);
        }
    }
}
