<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Diagnostics {
    private const TRANSIENT_KEY = 'lcfa_connection_diagnostics';
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function run(): array {
        $checked_at = gmdate('c');
        $adapter = $this->environment->get_mcp_adapter_status();
        $oauth = class_exists('LCFA_OAuth_Server', false)
            ? (new LCFA_OAuth_Server())->get_status($adapter)
            : [
                'available' => false,
                'https' => false,
                'dependencies' => false,
                'mcp_adapter_available' => !empty($adapter['available']),
                'message' => __('The bundled OAuth runtime is unavailable.', 'livecanvas-forge-ai'),
            ];
        $checks = [];

        $checks['https'] = $this->check(
            __('Public HTTPS URL', 'livecanvas-forge-ai'),
            !empty($oauth['public_https']),
            !empty($oauth['public_https'])
                ? __('The WordPress home URL is public and uses HTTPS.', 'livecanvas-forge-ai')
                : __('Direct OAuth requires a public HTTPS site. AI Bridge will use secure pairing or the local runtime here.', 'livecanvas-forge-ai'),
            !empty($oauth['public_https']) ? 'pass' : 'warn'
        );
        $checks['oauth_runtime'] = $this->check(
            __('OAuth runtime', 'livecanvas-forge-ai'),
            !empty($oauth['dependencies']),
            !empty($oauth['dependencies'])
                ? __('OAuth 2.1 dependencies, OpenSSL, and PHP requirements are available.', 'livecanvas-forge-ai')
                : __('The bundled OAuth runtime is incomplete. Reinstall the current AI Bridge release.', 'livecanvas-forge-ai'),
            !empty($oauth['dependencies']) ? 'pass' : 'error'
        );
        $checks['mcp_adapter'] = $this->check(
            __('WordPress MCP Adapter', 'livecanvas-forge-ai'),
            !empty($adapter['available']),
            !empty($adapter['available'])
                ? __('The official MCP Adapter classes required by AI Bridge are active.', 'livecanvas-forge-ai')
                : __('Install and activate the official WordPress MCP Adapter, or use secure pairing fallback.', 'livecanvas-forge-ai'),
            !empty($adapter['available']) ? 'pass' : 'warn',
            ['classes' => (array) ($adapter['classes'] ?? [])]
        );

        $routes = $this->registered_routes();
        $checks['mcp_route'] = $this->check(
            __('MCP HTTP route', 'livecanvas-forge-ai'),
            in_array('/livecanvas-forge-ai/mcp', $routes, true),
            in_array('/livecanvas-forge-ai/mcp', $routes, true)
                ? __('The LiveCanvas MCP route is registered.', 'livecanvas-forge-ai')
                : __('The LiveCanvas MCP route is missing. Confirm that MCP Adapter initialized without a fatal error.', 'livecanvas-forge-ai'),
            in_array('/livecanvas-forge-ai/mcp', $routes, true) ? 'pass' : 'error'
        );
        $checks['oauth_routes'] = $this->check(
            __('OAuth endpoints', 'livecanvas-forge-ai'),
            in_array('/lcfa/v1/oauth/register', $routes, true)
                && in_array('/lcfa/v1/oauth/token', $routes, true),
            in_array('/lcfa/v1/oauth/register', $routes, true)
                && in_array('/lcfa/v1/oauth/token', $routes, true)
                ? __('Dynamic client registration and token routes are registered.', 'livecanvas-forge-ai')
                : __('One or more AI Bridge OAuth routes are missing.', 'livecanvas-forge-ai'),
            in_array('/lcfa/v1/oauth/register', $routes, true)
                && in_array('/lcfa/v1/oauth/token', $routes, true)
                ? 'pass'
                : 'error'
        );

        if (!empty($oauth['public_https']) && !empty($oauth['dependencies'])) {
            $keys = LCFA_OAuth_Storage::ensure_schema();
            $checks['oauth_storage'] = $this->check(
                __('OAuth storage', 'livecanvas-forge-ai'),
                !is_wp_error($keys),
                is_wp_error($keys)
                    ? $keys->get_error_message()
                    : __('OAuth tables and encrypted signing material are ready.', 'livecanvas-forge-ai'),
                is_wp_error($keys) ? 'error' : 'pass'
            );
        }

        if (!empty($oauth['public_https']) && !empty($oauth['dependencies'])) {
            $checks['protected_resource_metadata'] = $this->probe_json_endpoint(
                __('Protected resource discovery', 'livecanvas-forge-ai'),
                (string) ($oauth['protected_resource_metadata_url'] ?? ''),
                'resource',
                (string) ($oauth['resource_url'] ?? '')
            );
            $checks['authorization_server_metadata'] = $this->probe_json_endpoint(
                __('Authorization server discovery', 'livecanvas-forge-ai'),
                (string) ($oauth['authorization_server_metadata_url'] ?? ''),
                'token_endpoint',
                LCFA_OAuth_Storage::token_url()
            );
        }

        if (!empty($oauth['available'])) {
            $checks['mcp_auth_challenge'] = $this->probe_mcp_challenge((string) ($oauth['resource_url'] ?? ''));
        }

        $errors = array_filter($checks, static function (array $check): bool {
            return ($check['severity'] ?? '') === 'error' && empty($check['ok']);
        });
        $warnings = array_filter($checks, static function (array $check): bool {
            return ($check['severity'] ?? '') === 'warn' && empty($check['ok']);
        });
        $ok = $errors === [];
        $summary = $ok
            ? ($warnings === []
                ? __('Direct OAuth diagnostics passed.', 'livecanvas-forge-ai')
                : __('Core diagnostics passed with fallback or network warnings.', 'livecanvas-forge-ai'))
            : __('Direct OAuth diagnostics found a blocking issue.', 'livecanvas-forge-ai');

        $result = [
            'ok' => $ok,
            'checked_at' => $checked_at,
            'summary' => $summary,
            'strategy' => !empty($oauth['available']) ? 'oauth-direct' : 'ai-bridge-session',
            'checks' => $checks,
        ];
        set_transient(self::TRANSIENT_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    public function get_last_result(): array {
        $result = get_transient(self::TRANSIENT_KEY);

        return is_array($result) ? $result : [];
    }

    private function registered_routes(): array {
        if (!function_exists('rest_get_server')) {
            return [];
        }

        $server = rest_get_server();
        if (!is_object($server) || !method_exists($server, 'get_routes')) {
            return [];
        }

        return array_keys((array) $server->get_routes());
    }

    private function probe_json_endpoint(
        string $label,
        string $url,
        string $expected_key,
        string $expected_value
    ): array {
        if ($url === '') {
            return $this->check(
                $label,
                false,
                __('The discovery URL could not be generated.', 'livecanvas-forge-ai'),
                'error'
            );
        }

        $response = wp_remote_get($url, [
            'timeout' => 12,
            'redirection' => 2,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'LiveCanvas AI Bridge/' . (defined('LCFA_VERSION') ? LCFA_VERSION : 'unknown'),
            ],
        ]);
        if (is_wp_error($response)) {
            return $this->check(
                $label,
                false,
                sprintf(
                    __('The site could not reach its own discovery URL: %s. A firewall, DNS rule, or hosting loopback policy may be blocking it.', 'livecanvas-forge-ai'),
                    $response->get_error_message()
                ),
                'warn',
                ['url' => $url]
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $actual = is_array($payload) ? (string) ($payload[$expected_key] ?? '') : '';
        $matches = $status === 200
            && $actual !== ''
            && ($expected_value === '' || hash_equals(untrailingslashit($expected_value), untrailingslashit($actual)));

        return $this->check(
            $label,
            $matches,
            $matches
                ? __('Discovery metadata is reachable and matches this site.', 'livecanvas-forge-ai')
                : sprintf(
                    __('Discovery returned HTTP %d or metadata for a different endpoint. Check redirects, proxy headers, and security plugins.', 'livecanvas-forge-ai'),
                    $status
                ),
            $matches ? 'pass' : ($status === 401 || $status === 403 || $status >= 500 ? 'warn' : 'error'),
            [
                'url' => $url,
                'status' => $status,
                'expected' => $expected_value,
                'actual' => $actual,
            ]
        );
    }

    private function probe_mcp_challenge(string $url): array {
        if ($url === '') {
            return $this->check(
                __('MCP OAuth challenge', 'livecanvas-forge-ai'),
                false,
                __('The MCP resource URL could not be generated.', 'livecanvas-forge-ai'),
                'error'
            );
        }

        $response = wp_remote_post($url, [
            'timeout' => 12,
            'redirection' => 0,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LiveCanvas AI Bridge/' . (defined('LCFA_VERSION') ? LCFA_VERSION : 'unknown'),
            ],
            'body' => wp_json_encode([
                'jsonrpc' => '2.0',
                'id' => 'lcfa-diagnostic',
                'method' => 'initialize',
                'params' => [],
            ]),
        ]);
        if (is_wp_error($response)) {
            return $this->check(
                __('MCP OAuth challenge', 'livecanvas-forge-ai'),
                false,
                sprintf(
                    __('The site could not probe its MCP endpoint: %s. External Codex access may still work when hosting blocks loopback requests.', 'livecanvas-forge-ai'),
                    $response->get_error_message()
                ),
                'warn',
                ['url' => $url]
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $challenge = (string) wp_remote_retrieve_header($response, 'www-authenticate');
        $ok = $status === 401
            && stripos($challenge, 'Bearer') !== false
            && stripos($challenge, 'resource_metadata=') !== false;

        return $this->check(
            __('MCP OAuth challenge', 'livecanvas-forge-ai'),
            $ok,
            $ok
                ? __('The unauthenticated MCP request returns the OAuth discovery challenge Codex expects.', 'livecanvas-forge-ai')
                : sprintf(
                    __('Expected an OAuth 401 challenge but received HTTP %d. A cache, WAF, redirect, or server rule may be intercepting MCP requests.', 'livecanvas-forge-ai'),
                    $status
                ),
            $ok ? 'pass' : ($status === 403 || $status >= 500 ? 'warn' : 'error'),
            [
                'url' => $url,
                'status' => $status,
                'www_authenticate' => $challenge,
            ]
        );
    }

    private function check(
        string $label,
        bool $ok,
        string $message,
        string $severity,
        array $details = []
    ): array {
        return [
            'label' => $label,
            'ok' => $ok,
            'severity' => in_array($severity, ['pass', 'warn', 'error'], true) ? $severity : 'warn',
            'message' => $message,
            'details' => $details,
        ];
    }
}
