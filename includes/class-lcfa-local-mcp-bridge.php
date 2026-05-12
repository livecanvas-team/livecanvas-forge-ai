<?php

defined('ABSPATH') || exit;

final class LCFA_Local_MCP_Bridge {
    private const STATUS_TTL = 30;

    private LCFA_Environment $environment;
    private ?array $status_cache = null;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_status(): array {
        if ($this->status_cache !== null) {
            return $this->status_cache;
        }

        $cached = get_transient($this->get_status_cache_key());
        if (is_array($cached)) {
            $this->status_cache = $cached;

            return $this->status_cache;
        }

        $snapshot    = $this->environment->get_snapshot();
        $node_binary = $this->resolve_node_binary();
        $script_path = $this->get_cli_script_path();
        $node_probe  = $this->probe_node_binary($node_binary);
        $loopback    = $this->probe_rest_loopback();
        $local_site  = $snapshot['site_mode'] === 'local';
        $script_ok   = is_readable($script_path);

        $message = __('The local MCP bridge is ready.', 'livecanvas-forge-ai');

        if (!$local_site) {
            $message = __('The current WordPress URL is not detected as local, so local MCP execution is disabled.', 'livecanvas-forge-ai');
        } elseif (!$script_ok) {
            $message = __('The local MCP CLI entrypoint was not found inside the plugin.', 'livecanvas-forge-ai');
        } elseif (empty($node_probe['ok'])) {
            $message = __('Node.js is not available to the current PHP process.', 'livecanvas-forge-ai');
        } elseif (empty($loopback['ok'])) {
            $message = (string) ($loopback['message'] ?? __('The local WordPress REST loopback is not reachable from this runtime.', 'livecanvas-forge-ai'));
        } elseif (empty($snapshot['windpress_active'])) {
            $message = __('WindPress is not active, so local Tailwind cache builds are currently unavailable.', 'livecanvas-forge-ai');
        }

        $this->status_cache = [
            'available'       => $local_site && $script_ok && !empty($node_probe['ok']) && !empty($loopback['ok']),
            'build_available' => $local_site && $script_ok && !empty($node_probe['ok']) && !empty($loopback['ok']) && !empty($snapshot['windpress_active']),
            'local_site'      => $local_site,
            'windpress_active'=> !empty($snapshot['windpress_active']),
            'node_available'  => !empty($node_probe['ok']),
            'node_binary'     => $node_binary,
            'node_version'    => (string) ($node_probe['version'] ?? ''),
            'rest_reachable'  => !empty($loopback['ok']),
            'rest_message'    => (string) ($loopback['message'] ?? ''),
            'script_path'     => $script_path,
            'script_exists'   => $script_ok,
            'wp_root'         => untrailingslashit(ABSPATH),
            'rest_base'       => rest_url('lcfa/v1/'),
            'message'         => $message,
        ];

        set_transient($this->get_status_cache_key(), $this->status_cache, self::STATUS_TTL);

        return $this->status_cache;
    }

    public function build_windpress_cache(array $arguments = []): array {
        return $this->run_tool('build_windpress_cache', $arguments);
    }

    public function run_tool(string $tool, array $arguments = []): array {
        $status = $this->get_status();

        if (empty($status['available'])) {
            return [
                'ok'      => false,
                'tool'    => $tool,
                'message' => $status['message'],
                'status'  => $status,
            ];
        }

        if (!function_exists('proc_open')) {
            return [
                'ok'      => false,
                'tool'    => $tool,
                'message' => __('The local MCP bridge requires proc_open, but it is disabled on this server.', 'livecanvas-forge-ai'),
                'status'  => $status,
            ];
        }

        $command   = $this->build_command((string) $status['node_binary'], (string) $status['script_path'], $tool, $arguments);
        $execution = $this->run_process($command, $this->build_process_environment(), (string) $status['wp_root']);

        if (empty($execution['ok'])) {
            return [
                'ok'        => false,
                'tool'      => $tool,
                'message'   => (string) ($execution['message'] ?? __('The local MCP tool failed.', 'livecanvas-forge-ai')),
                'command'   => $command,
                'status'    => $status,
                'execution' => $execution,
            ];
        }

        $payload = json_decode((string) $execution['stdout'], true);

        if (!is_array($payload)) {
            return [
                'ok'        => false,
                'tool'      => $tool,
                'message'   => __('The local MCP tool did not return valid JSON.', 'livecanvas-forge-ai'),
                'command'   => $command,
                'status'    => $status,
                'execution' => $execution,
            ];
        }

        $payload['command'] = $command;
        $payload['status']  = $status;

        return $payload;
    }

    private function get_cli_script_path(): string {
        return LCFA_DIR . 'mcp/bin/livecanvas-forge-mcp.js';
    }

    private function get_status_cache_key(): string {
        return 'lcfa_local_mcp_status_' . md5(home_url('/') . '|' . LCFA_VERSION);
    }

    private function resolve_node_binary(): string {
        $candidates = array_filter([
            apply_filters('lcfa_local_node_binary', getenv('LCFA_NODE_BINARY') ?: ''),
            '/opt/homebrew/bin/node',
            '/usr/local/bin/node',
            '/usr/bin/node',
            'node',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'node') {
                return $candidate;
            }

            if (is_string($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'node';
    }

    private function probe_node_binary(string $node_binary): array {
        if (!function_exists('proc_open')) {
            return [
                'ok'      => false,
                'version' => '',
            ];
        }

        $command   = escapeshellarg($node_binary) . ' --version';
        $execution = $this->run_process($command, $this->collect_base_environment(), untrailingslashit(ABSPATH));

        return [
            'ok'      => !empty($execution['ok']),
            'version' => trim((string) ($execution['stdout'] ?? '')),
        ];
    }

    private function probe_rest_loopback(): array {
        $connections = LCFA_Settings::get_connections();
        // Keep this probe atomic: /mcp/status builds local bridge status and would recurse into this method.
        $response    = wp_remote_get(rest_url('lcfa/v1/mcp/health'), [
            'timeout' => 5,
            'headers' => [
                'X-LCFA-MCP-Token' => (string) $connections['mcp_token'],
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('REST loopback failed: %s. Start the local web server or verify the site URL and port.', 'livecanvas-forge-ai'),
                    $response->get_error_message()
                ),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return [
                'ok'      => false,
                'message' => sprintf(__('REST loopback returned HTTP %d. Verify the local site URL and that the web server is responding.', 'livecanvas-forge-ai'), $code),
            ];
        }

        return [
            'ok'      => true,
            'message' => __('REST loopback is reachable.', 'livecanvas-forge-ai'),
        ];
    }

    private function build_command(string $node_binary, string $script_path, string $tool, array $arguments): string {
        $json = $arguments === []
            ? '{}'
            : wp_json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json) || $json === '') {
            $json = '{}';
        }

        return implode(' ', [
            escapeshellarg($node_binary),
            escapeshellarg($script_path),
            '--tool',
            escapeshellarg($tool),
            '--tool-args',
            escapeshellarg($json),
            '--output',
            'json',
        ]);
    }

    private function build_process_environment(): array {
        $connections = LCFA_Settings::get_connections();

        return array_merge($this->collect_base_environment(), [
            'LCFA_SITE_URL'  => home_url('/'),
            'LCFA_REST_BASE' => rest_url('lcfa/v1/'),
            'LCFA_MCP_TOKEN' => (string) $connections['mcp_token'],
            'LCFA_WP_ROOT'   => untrailingslashit(ABSPATH),
        ]);
    }

    private function collect_base_environment(): array {
        $environment = [];

        foreach (['PATH', 'HOME', 'USER', 'TMPDIR', 'TMP', 'TEMP', 'SHELL'] as $key) {
            $value = getenv($key);

            if ($value !== false && $value !== '') {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    private function run_process(string $command, array $environment, string $working_directory): array {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, is_dir($working_directory) ? $working_directory : null, $environment);

        if (!is_resource($process)) {
            return [
                'ok'        => false,
                'exit_code' => 1,
                'stdout'    => '',
                'stderr'    => '',
                'message'   => __('Unable to start the local MCP process.', 'livecanvas-forge-ai'),
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exit_code = proc_close($process);
        $stdout    = trim((string) $stdout);
        $stderr    = trim((string) $stderr);
        $ok        = $exit_code === 0;

        return [
            'ok'        => $ok,
            'exit_code' => $exit_code,
            'stdout'    => $stdout,
            'stderr'    => $stderr,
            'message'   => $ok
                ? __('Local MCP process completed.', 'livecanvas-forge-ai')
                : ($stderr !== '' ? $stderr : __('The local MCP process failed.', 'livecanvas-forge-ai')),
        ];
    }
}
