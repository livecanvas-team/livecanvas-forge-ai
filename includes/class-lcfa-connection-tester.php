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

    public function run_checks(array $options = []): array {
        $connections = LCFA_Settings::get_connections();
        $mode = sanitize_key((string) ($options['mode'] ?? 'all'));
        $checks = [];

        if ($mode === 'remote') {
            $checks['remote_rest'] = $this->test_remote_rest($connections);
        } elseif ($mode === 'local') {
            $checks['local_rest'] = $this->test_local_rest($connections);
            $checks['local_mcp']  = $this->test_local_mcp();
            $client_registration = $this->test_local_client_registration($connections);
            if (is_array($client_registration)) {
                $checks['client_registration'] = $client_registration;
            }
        } else {
            $checks = [
                'local_rest' => $this->test_local_rest($connections),
                'local_mcp'  => $this->test_local_mcp(),
                'remote_rest'=> $this->test_remote_rest($connections),
            ];
        }

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
        $skipped = false;
        $message = (string) ($status['message'] ?? '');

        if (empty($status['local_site'])) {
            $skipped = true;
        } elseif (empty($status['script_exists'])) {
            $skipped = false;
        } elseif (empty($status['node_available'])) {
            $skipped = true;
            $message = __('Node.js is not available to the current PHP process. External coding agents can still connect through the local REST bridge.', 'livecanvas-forge-ai');
        } elseif (empty($status['rest_reachable'])) {
            $skipped = true;
        }

        return [
            'label'   => __('Local MCP runtime', 'livecanvas-forge-ai'),
            'ok'      => !empty($status['available']),
            'skipped' => $skipped,
            'message' => $message,
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

    private function test_local_client_registration(array $connections): ?array {
        $preferred_client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        $claude_target = sanitize_key((string) ($connections['claude_connection_target'] ?? ''));

        if ($preferred_client !== 'claude' || $claude_target !== 'desktop_app') {
            return null;
        }

        $config_path = $this->resolve_claude_desktop_config_path();

        if ($config_path === '') {
            return [
                'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => false,
                'message' => __('Claude Desktop config path could not be resolved from this PHP environment. Update the config manually, reopen Claude Desktop, then rerun the smoke test.', 'livecanvas-forge-ai'),
                'details' => [],
            ];
        }

        if (!is_file($config_path)) {
            return [
                'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => false,
                'message' => sprintf(__('Claude Desktop config was not found at %s. Merge the generated livecanvas-forge snippet there, reopen Claude Desktop, then rerun the smoke test.', 'livecanvas-forge-ai'), $config_path),
                'details' => [
                    'config_path' => $config_path,
                ],
            ];
        }

        $contents = file_get_contents($config_path);
        if (!is_string($contents) || trim($contents) === '') {
            return [
                'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => false,
                'message' => __('Claude Desktop config is empty. Merge the generated livecanvas-forge snippet, reopen Claude Desktop, then rerun the smoke test.', 'livecanvas-forge-ai'),
                'details' => [
                    'config_path' => $config_path,
                ],
            ];
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload)) {
            return [
                'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => false,
                'message' => __('Claude Desktop config is not valid JSON. Fix the file, make sure livecanvas-forge is registered under mcpServers, reopen Claude Desktop, then rerun the smoke test.', 'livecanvas-forge-ai'),
                'details' => [
                    'config_path' => $config_path,
                ],
            ];
        }

        $servers = $payload['mcpServers'] ?? null;
        if (!is_array($servers) || !array_key_exists('livecanvas-forge', $servers)) {
            return [
                'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
                'ok'      => false,
                'skipped' => false,
                'message' => __('Claude Desktop config does not contain mcpServers.livecanvas-forge yet. Merge the generated snippet, reopen Claude Desktop, then rerun the smoke test.', 'livecanvas-forge-ai'),
                'details' => [
                    'config_path' => $config_path,
                ],
            ];
        }

        return [
            'label'   => __('Claude Desktop registration', 'livecanvas-forge-ai'),
            'ok'      => true,
            'skipped' => false,
            'message' => __('Claude Desktop config includes the livecanvas-forge registration.', 'livecanvas-forge-ai'),
            'details' => [
                'config_path' => $config_path,
            ],
        ];
    }

    private function resolve_claude_desktop_config_path(): string {
        $home = (string) getenv('HOME');
        if ($home === '') {
            return '';
        }

        return rtrim($home, '/\\') . '/Library/Application Support/Claude/claude_desktop_config.json';
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
