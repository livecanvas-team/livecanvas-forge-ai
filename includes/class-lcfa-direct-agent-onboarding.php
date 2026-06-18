<?php

defined('ABSPATH') || exit;

final class LCFA_Direct_Agent_Onboarding {
    public function get_codex_direct_state(array $connections, array $bundle, array $remote_status = []): array {
        $connection_status = sanitize_key((string) ($connections['connection_status'] ?? ''));
        $current_step = sanitize_key((string) ($connections['connection_current_step'] ?? ''));
        $last_verified = trim((string) ($connections['connection_last_verified_at'] ?? ''));
        $last_error = trim((string) ($connections['connection_last_error'] ?? ''));
        $remote_prerequisites = $this->get_remote_codex_prerequisites($connections, $remote_status);
        $credentials_ready = !empty($remote_prerequisites['ready']);
        $smoke_passed = $connection_status === 'ready' && $last_verified !== '';
        $restart_required = $credentials_ready && !$smoke_passed && $current_step === 'smoke_test' && $connection_status !== 'needs_attention';
        $has_fingerprint = trim((string) ($connections['connection_last_bundle_hash'] ?? '')) !== '';
        $status = 'needs_setup';
        $primary_action = 'connect';
        $message = __('Connect Codex through the WordPress MCP Adapter direct path.', 'livecanvas-forge-ai');

        if (!$credentials_ready) {
            $status = 'missing_credentials';
            $primary_action = 'none';
            $message = __('Add the site URL, WordPress username, and Application Password before generating the Direct Mode Codex setup.', 'livecanvas-forge-ai');
        } elseif ($smoke_passed) {
            $status = 'ready';
            $primary_action = 'none';
            $message = __('Direct Mode is ready. Codex can reach the WordPress Ability server.', 'livecanvas-forge-ai');
        } elseif ($connection_status === 'needs_attention') {
            $status = 'test_failed';
            $primary_action = 'run_smoke';
            $message = $last_error !== '' ? $last_error : __('The last Direct Mode smoke test failed.', 'livecanvas-forge-ai');
        } elseif ($restart_required) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = __('Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai');
        } elseif ($has_fingerprint) {
            $status = 'restart_required';
            $primary_action = 'run_smoke';
            $message = __('Direct Mode setup was generated. Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai');
        }

        $checks = [
            'mcp_adapter_available' => [
                'label' => __('MCP Adapter', 'livecanvas-forge-ai'),
                'ok' => trim((string) ($remote_prerequisites['mcp_adapter_url'] ?? '')) !== '',
                'message' => trim((string) ($remote_prerequisites['mcp_adapter_url'] ?? '')) !== ''
                    ? __('WordPress MCP Adapter URL is available.', 'livecanvas-forge-ai')
                    : __('WordPress MCP Adapter URL is missing.', 'livecanvas-forge-ai'),
            ],
            'application_password_present' => [
                'label' => __('Application Password', 'livecanvas-forge-ai'),
                'ok' => $credentials_ready,
                'message' => $credentials_ready
                    ? __('Remote username and Application Password are present.', 'livecanvas-forge-ai')
                    : __('Remote username or Application Password is missing.', 'livecanvas-forge-ai'),
            ],
            'abilities_registered' => [
                'label' => __('Abilities', 'livecanvas-forge-ai'),
                'ok' => function_exists('wp_register_ability') || !empty($remote_status['mcp_adapter']['available']) || trim((string) ($remote_prerequisites['mcp_adapter_url'] ?? '')) !== '',
                'message' => __('Forge WordPress Abilities are the Direct Mode contract.', 'livecanvas-forge-ai'),
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
            'strategy' => 'wordpress-mcp-adapter',
            'primary_action' => $primary_action,
            'checks' => $checks,
            'message' => $message,
            'manual_fallback' => [
                'shortcut_command' => (string) ($bundle['copy_command_string'] ?? ($bundle['shortcut_command'] ?? '')),
                'command' => (string) ($bundle['command_string'] ?? ''),
                'start_tool' => (string) ($bundle['agent_start_tool'] ?? 'livecanvas-forge-ai/get-connection-handoff'),
                'prerequisites' => $remote_prerequisites,
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
        $username = trim((string) ($connections['remote_username'] ?? ''));
        $application_password = trim((string) ($connections['remote_application_password'] ?? ''));
        $mcp_adapter_url = $this->get_remote_mcp_adapter_url($remote_site_url, $remote_status);
        $items = [
            'remote_site_url' => [
                'label' => __('Remote site URL', 'livecanvas-forge-ai'),
                'ok' => $remote_site_url !== '',
            ],
            'remote_username' => [
                'label' => __('Remote username', 'livecanvas-forge-ai'),
                'ok' => $username !== '',
            ],
            'remote_application_password' => [
                'label' => __('Application Password', 'livecanvas-forge-ai'),
                'ok' => $application_password !== '',
            ],
            'mcp_adapter_url' => [
                'label' => __('MCP Adapter URL', 'livecanvas-forge-ai'),
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

        return trailingslashit(untrailingslashit($remote_site_url)) . 'wp-json/livecanvas-forge-ai/mcp';
    }
}
