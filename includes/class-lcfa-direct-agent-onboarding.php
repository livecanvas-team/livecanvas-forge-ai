<?php

defined('ABSPATH') || exit;

final class LCFA_Direct_Agent_Onboarding {
    public function get_codex_direct_state(array $connections, array $bundle, array $remote_status = []): array {
        $strategy = sanitize_key((string) ($bundle['connection_strategy'] ?? ($connections['connection_strategy'] ?? 'ai-bridge-session')));
        $is_oauth_direct = $strategy === 'oauth-direct';
        $connection_status = sanitize_key((string) ($connections['connection_status'] ?? ''));
        $current_step = sanitize_key((string) ($connections['connection_current_step'] ?? ''));
        $last_verified = trim((string) ($connections['connection_last_verified_at'] ?? ''));
        $last_error = trim((string) ($connections['connection_last_error'] ?? ''));
        $remote_prerequisites = $this->get_remote_codex_prerequisites($connections, $remote_status);
        $target_ready = !empty($remote_prerequisites['ready']);
        $has_active_pairing_session = class_exists('LCFA_MCP_Session_Manager', false) && LCFA_MCP_Session_Manager::has_active_session();
        $has_active_oauth_grant = false;
        if ($is_oauth_direct && class_exists('LCFA_OAuth_Server', false)) {
            foreach ((new LCFA_OAuth_Server())->get_connected_apps() as $app) {
                if (empty($app['revoked_at']) && (int) ($app['active_tokens'] ?? 0) > 0) {
                    $has_active_oauth_grant = true;
                    break;
                }
            }
        }
        $has_active_session = $is_oauth_direct ? $has_active_oauth_grant : $has_active_pairing_session;
        $smoke_passed = $connection_status === 'ready' && $last_verified !== '';
        $restart_required = $target_ready && !$smoke_passed && $current_step === 'smoke_test' && $connection_status !== 'needs_attention';
        $has_fingerprint = trim((string) ($connections['connection_last_bundle_hash'] ?? '')) !== '';
        $status = 'needs_setup';
        $primary_action = 'connect';
        $message = $is_oauth_direct
            ? __('Connect Codex directly to the WordPress MCP endpoint with OAuth.', 'livecanvas-forge-ai')
            : __('Connect Codex through the secure AI Bridge pairing flow.', 'livecanvas-forge-ai');

        if (!$target_ready) {
            $status = 'missing_credentials';
            $primary_action = 'none';
            $message = __('Confirm the WordPress site URL before generating the secure Direct Mode Codex setup.', 'livecanvas-forge-ai');
        } elseif ($smoke_passed) {
            $status = 'ready';
            $primary_action = 'none';
            $message = $is_oauth_direct
                ? __('Direct Mode is ready. Codex is authenticated with a revocable OAuth grant for this site.', 'livecanvas-forge-ai')
                : __('Direct Mode is ready. Codex can reach this WordPress site through a scoped AI Bridge session.', 'livecanvas-forge-ai');
        } elseif ($connection_status === 'needs_attention') {
            $status = 'test_failed';
            $primary_action = 'run_smoke';
            $message = $last_error !== '' ? $last_error : __('The last Direct Mode smoke test failed.', 'livecanvas-forge-ai');
        } elseif ($has_active_session) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = $is_oauth_direct
                ? __('Codex has an active OAuth grant. Call the handoff tool or run the smoke test to confirm readiness.', 'livecanvas-forge-ai')
                : __('A Codex session is paired. Run the smoke test to mark the connection ready.', 'livecanvas-forge-ai');
        } elseif ($restart_required) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = $is_oauth_direct
                ? __('Restart Codex or reload the MCP server, then complete the one-time OAuth login.', 'livecanvas-forge-ai')
                : __('Restart Codex or reload the MCP server, then approve the pairing request before testing.', 'livecanvas-forge-ai');
        } elseif ($has_fingerprint) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = $is_oauth_direct
                ? __('Direct OAuth setup was generated. Restart Codex, then authorize this project in WordPress.', 'livecanvas-forge-ai')
                : __('Direct Mode setup was generated. Restart Codex or reload the MCP server, then approve the pairing request.', 'livecanvas-forge-ai');
        }

        $oauth_status = is_array($remote_prerequisites['oauth_status'] ?? null)
            ? $remote_prerequisites['oauth_status']
            : [];
        $checks = [
            'mcp_adapter_available' => [
                'label' => __('WordPress MCP endpoint', 'livecanvas-forge-ai'),
                'ok' => $is_oauth_direct ? !empty($oauth_status['mcp_adapter_available']) : $target_ready,
                'message' => $is_oauth_direct
                    ? (!empty($oauth_status['mcp_adapter_available'])
                        ? __('The official WordPress MCP Adapter is active.', 'livecanvas-forge-ai')
                        : __('MCP Adapter is unavailable; secure pairing fallback will be used.', 'livecanvas-forge-ai'))
                    : __('The AI Bridge pairing endpoint is available.', 'livecanvas-forge-ai'),
            ],
            'secure_auth_available' => [
                'label' => $is_oauth_direct ? __('Direct OAuth', 'livecanvas-forge-ai') : __('Secure pairing', 'livecanvas-forge-ai'),
                'ok' => $target_ready,
                'message' => $target_ready
                    ? ($is_oauth_direct
                        ? __('OAuth authorization is available without WordPress credentials in Codex.', 'livecanvas-forge-ai')
                        : __('Secure pairing can start without a WordPress Application Password.', 'livecanvas-forge-ai'))
                    : __('Site URL is required before secure authentication can start.', 'livecanvas-forge-ai'),
            ],
            'session_active' => [
                'label' => $is_oauth_direct ? __('OAuth grant', 'livecanvas-forge-ai') : __('AI Bridge session', 'livecanvas-forge-ai'),
                'ok' => $has_active_session,
                'message' => $has_active_session
                    ? ($is_oauth_direct ? __('At least one Codex OAuth grant is active.', 'livecanvas-forge-ai') : __('At least one Codex session is active.', 'livecanvas-forge-ai'))
                    : ($is_oauth_direct ? __('Codex has not completed OAuth login yet.', 'livecanvas-forge-ai') : __('No Codex session has been approved yet.', 'livecanvas-forge-ai')),
            ],
            'abilities_registered' => [
                'label' => __('Abilities', 'livecanvas-forge-ai'),
                'ok' => function_exists('wp_register_ability') || !empty($remote_status['mcp_adapter']['available']) || trim((string) ($remote_prerequisites['mcp_adapter_url'] ?? '')) !== '',
                'message' => __('AI Bridge REST and WordPress Abilities are the Direct Mode contract.', 'livecanvas-forge-ai'),
            ],
            'handoff_available' => [
                'label' => __('Handoff', 'livecanvas-forge-ai'),
                'ok' => trim((string) ($bundle['agent_start_tool'] ?? '')) === 'livecanvas-forge-ai/get-connection-handoff',
                'message' => __('Direct Mode starts from livecanvas-forge-ai/get-connection-handoff.', 'livecanvas-forge-ai'),
            ],
            'smoke_passed' => [
                'label' => __('Smoke test', 'livecanvas-forge-ai'),
                'ok' => $smoke_passed,
                'message' => $smoke_passed ? __('Passed.', 'livecanvas-forge-ai') : __('Pending.', 'livecanvas-forge-ai'),
            ],
        ];

        return [
            'mode' => 'direct',
            'connection_mode' => 'remote',
            'client' => 'codex',
            'status' => $status,
            'strategy' => $strategy,
            'primary_action' => $primary_action,
            'checks' => $checks,
            'message' => $message,
            'manual_fallback' => [
                'shortcut_command' => (string) ($bundle['copy_command_string'] ?? ($bundle['shortcut_command'] ?? '')),
                'command' => (string) ($bundle['command_string'] ?? ''),
                'codex_config_snippet' => (string) ($bundle['codex_config_snippet'] ?? ''),
                'codex_project_config_path' => (string) ($bundle['codex_project_config_path'] ?? '.codex/config.toml'),
                'start_tool' => (string) ($bundle['agent_start_tool'] ?? 'livecanvas-forge-ai/get-connection-handoff'),
                'prerequisites' => $remote_prerequisites,
                'server_name' => (string) ($bundle['server_name'] ?? ''),
                'mcp_url' => (string) ($bundle['mcp_url'] ?? ''),
            ],
            'last_smoke' => [
                'verified_at' => $last_verified,
                'error' => $last_error,
            ],
            'should_invalidate_ready' => false,
        ];
    }

    public function get_remote_codex_prerequisites(array $connections, array $remote_status = []): array {
        $remote_site_url = trim((string) ($connections['remote_site_url'] ?? ''));
        if ($remote_site_url === '' && function_exists('home_url')) {
            $remote_site_url = home_url('/');
        }
        $oauth_status = class_exists('LCFA_OAuth_Server', false)
            ? (new LCFA_OAuth_Server())->get_status()
            : ['available' => false, 'strategy' => 'ai-bridge-session'];
        $current_site_url = function_exists('home_url') ? home_url('/') : '';
        $targets_current_site = $remote_site_url !== ''
            && $current_site_url !== ''
            && class_exists('LCFA_Settings', false)
            && method_exists('LCFA_Settings', 'site_urls_match')
            && LCFA_Settings::site_urls_match($remote_site_url, $current_site_url);
        $oauth_available = !empty($oauth_status['available']) && $targets_current_site;
        $mcp_adapter_url = $oauth_available
            ? (string) ($oauth_status['resource_url'] ?? '')
            : $this->get_remote_mcp_adapter_url($remote_site_url, $remote_status);
        $items = [
            'remote_site_url' => [
                'label' => __('Remote site URL', 'livecanvas-forge-ai'),
                'ok' => $remote_site_url !== '',
            ],
            'mcp_adapter_url' => [
                'label' => $oauth_available ? __('WordPress MCP URL', 'livecanvas-forge-ai') : __('AI Bridge REST URL', 'livecanvas-forge-ai'),
                'ok' => $mcp_adapter_url !== '',
            ],
        ];
        $ready = true;
        foreach ($items as $item) {
            if (empty($item['ok'])) {
                $ready = false;
                break;
            }
        }

        return [
            'ready' => $ready,
            'items' => $items,
            'mcp_adapter_url' => $mcp_adapter_url,
            'strategy' => $oauth_available ? 'oauth-direct' : 'ai-bridge-session',
            'oauth_status' => $oauth_status,
        ];
    }

    private function get_remote_mcp_adapter_url(string $remote_site_url, array $remote_status): string {
        $custom_server = is_array($remote_status['mcp_adapter']['custom_server'] ?? null)
            ? $remote_status['mcp_adapter']['custom_server']
            : [];
        $remote_url = trim((string) ($custom_server['url'] ?? ''));

        if ($remote_url !== '') {
            return $remote_url;
        }

        if ($remote_site_url === '') {
            return '';
        }

        return trailingslashit(untrailingslashit($remote_site_url)) . 'wp-json/lcfa/v1/';
    }
}
