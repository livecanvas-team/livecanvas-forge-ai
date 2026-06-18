<?php

defined('ABSPATH') || exit;

final class LCFA_Codex_Config_Manager {
    private const SERVER_NAME = 'livecanvas-forge';
    private const NODE_PATH_CANDIDATES = [
        '/opt/homebrew/bin/node',
        '/usr/local/bin/node',
        '/usr/bin/node',
        '/opt/local/bin/node',
    ];

    public function get_expected_config(array $connections = []): array {
        $connections = $connections !== [] ? $connections : LCFA_Settings::get_connections();
        $wp_root = $this->resolve_wp_root($connections);
        $config_scope = $this->resolve_config_scope($connections);
        $site_fingerprint = $this->get_site_fingerprint();
        $script_path = LCFA_DIR . 'mcp/bin/livecanvas-forge-mcp.js';
        $environment = [
            'LCFA_MCP_ENDPOINT' => LCFA_Settings::get_mcp_endpoint(),
            'LCFA_MCP_TOKEN'    => (string) ($connections['mcp_token'] ?? ''),
            'LCFA_REST_BASE'    => trailingslashit(rest_url('lcfa/v1/')),
            'LCFA_SITE_URL'     => trailingslashit(home_url('/')),
            'LCFA_SITE_FINGERPRINT' => $site_fingerprint,
            'LCFA_WP_ROOT'      => $wp_root,
        ];
        $command = $this->resolve_node_command();
        $args = [$script_path, '--transport=stdio'];
        $fingerprint = [
            'node_command' => $command,
            'config_scope' => $config_scope,
            'script_path' => $script_path,
            'rest_base'   => $environment['LCFA_REST_BASE'],
            'site_url'    => $environment['LCFA_SITE_URL'],
            'site_fingerprint' => $site_fingerprint,
            'wp_root'     => $environment['LCFA_WP_ROOT'],
            'mcp_token'   => $environment['LCFA_MCP_TOKEN'],
        ];

        return [
            'server_name'    => self::SERVER_NAME,
            'config_scope'   => $config_scope,
            'config_path'    => $this->get_config_path($config_scope, $wp_root),
            'global_config_path' => $this->get_global_config_path(),
            'command'        => $command,
            'args'           => $args,
            'environment'    => $environment,
            'script_path'    => $script_path,
            'wp_root'        => $wp_root,
            'site_fingerprint' => $site_fingerprint,
            'hash'           => md5((string) wp_json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'snippet'        => $this->build_toml_snippet($command, $args, $environment),
            'remove_command' => 'codex mcp remove ' . self::SERVER_NAME,
            'add_command'    => $this->build_codex_add_command($command, $args, $environment),
        ];
    }

    public function inspect(array $connections = []): array {
        $expected = $this->get_expected_config($connections);
        $config_path = (string) $expected['config_path'];
        $contents = $config_path !== '' && is_file($config_path) ? file_get_contents($config_path) : false;
        $directory = $config_path !== '' ? dirname($config_path) : '';
        $writable = $config_path !== ''
            && ((is_file($config_path) && is_writable($config_path)) || (!is_file($config_path) && ($directory === '' || is_writable($directory) || is_writable(dirname($directory)))));

        if ($config_path === '') {
            return [
                'ok'       => false,
                'synced'   => false,
                'status'   => 'missing_home',
                'message'  => __('Codex config path could not be resolved for this project.', 'livecanvas-forge-ai'),
                'expected' => $expected,
                'writable' => false,
            ];
        }

        if (!is_string($contents) || trim($contents) === '') {
            return [
                'ok'       => false,
                'synced'   => false,
                'status'   => is_file($config_path) ? 'empty' : 'missing',
                'message'  => __('Codex config is missing or empty. Sync the Codex MCP config.', 'livecanvas-forge-ai'),
                'expected' => $expected,
                'writable' => $writable,
            ];
        }

        $matches = $this->contents_match_expected($contents, $expected);
        $synced = !in_array(false, $matches, true);

        return [
            'ok'       => $synced,
            'synced'   => $synced,
            'status'   => $synced ? 'synced' : 'stale',
            'message'  => $synced
                ? __('Codex config matches this WordPress site.', 'livecanvas-forge-ai')
                : __('Codex config is stale. Regenerate or sync the Codex MCP config.', 'livecanvas-forge-ai'),
            'expected' => $expected,
            'matches'  => $matches,
            'writable' => $writable,
        ];
    }

    public function sync(array $connections = []): array {
        $expected = $this->get_expected_config($connections);
        $config_path = (string) $expected['config_path'];

        if ($config_path === '') {
            return [
                'ok'      => false,
                'message' => __('Codex config path could not be resolved for this project.', 'livecanvas-forge-ai'),
                'expected'=> $expected,
            ];
        }

        $directory = dirname($config_path);
        $created = is_dir($directory) || (function_exists('wp_mkdir_p') ? wp_mkdir_p($directory) : @mkdir($directory, 0775, true));
        if (!$created) {
            return [
                'ok'      => false,
                'message' => sprintf(__('Could not create Codex config directory: %s', 'livecanvas-forge-ai'), $directory),
                'expected'=> $expected,
            ];
        }

        $current = is_file($config_path) ? (string) file_get_contents($config_path) : '';
        $backup_path = '';
        if ($current !== '') {
            $backup_path = $config_path . '.lcfa-backup-' . gmdate('Ymd-His');
            if (@file_put_contents($backup_path, $current) === false) {
                return [
                    'ok'      => false,
                    'message' => sprintf(__('Could not create Codex config backup: %s', 'livecanvas-forge-ai'), $backup_path),
                    'expected'=> $expected,
                ];
            }
        }

        $updated = $this->replace_livecanvas_sections($current, (string) $expected['snippet']);
        if (@file_put_contents($config_path, $updated) === false) {
            return [
                'ok'          => false,
                'message'     => sprintf(__('Could not write Codex config: %s', 'livecanvas-forge-ai'), $config_path),
                'expected'    => $expected,
                'backup_path' => $backup_path,
            ];
        }

        return [
            'ok'               => true,
            'message'          => __('Codex config updated. Restart Codex or reload the MCP server before testing.', 'livecanvas-forge-ai'),
            'expected'         => $expected,
            'config_path'      => $config_path,
            'backup_path'      => $backup_path,
            'restart_required' => true,
        ];
    }

    public function run_smoke_test(array $connections = []): array {
        $expected = $this->get_expected_config($connections);
        $checks = [];
        $checks['rest_health'] = $this->check_rest_health($expected);
        $checks['script'] = [
            'ok'      => is_readable((string) $expected['script_path']),
            'message' => is_readable((string) $expected['script_path'])
                ? __('MCP script is readable.', 'livecanvas-forge-ai')
                : __('Codex is pointing to an old plugin path. Sync Codex config.', 'livecanvas-forge-ai'),
            'path'    => (string) $expected['script_path'],
        ];
        $checks['node'] = $this->check_node($expected);

        if (empty($checks['rest_health']['ok']) || empty($checks['script']['ok']) || empty($checks['node']['ok'])) {
            return $this->summarize_smoke($expected, $checks);
        }

        $this->clear_local_mcp_status_cache();
        $checks['mcp_tool'] = $this->run_mcp_status_tool($expected);

        return $this->summarize_smoke($expected, $checks);
    }

    private function summarize_smoke(array $expected, array $checks): array {
        $blocking = array_filter($checks, static function (array $check): bool {
            return empty($check['ok']);
        });

        return [
            'ok'       => count($blocking) === 0,
            'message'  => count($blocking) === 0
                ? __('Codex MCP smoke test passed.', 'livecanvas-forge-ai')
                : __('Codex MCP smoke test failed. Review the repair details.', 'livecanvas-forge-ai'),
            'expected' => $expected,
            'checks'   => $checks,
        ];
    }

    private function check_rest_health(array $expected): array {
        $response = wp_remote_get(trailingslashit((string) $expected['environment']['LCFA_REST_BASE']) . 'mcp/health', [
            'timeout' => 8,
            'headers' => [
                'Accept'           => 'application/json',
                'X-LCFA-MCP-Token' => (string) $expected['environment']['LCFA_MCP_TOKEN'],
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'message' => sprintf(__('REST health failed: %s', 'livecanvas-forge-ai'), $response->get_error_message()),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401 || $code === 403) {
            return [
                'ok'      => false,
                'message' => __('Token mismatch between WordPress and Codex config. Sync Codex config or rotate token and regenerate.', 'livecanvas-forge-ai'),
                'status'  => $code,
            ];
        }

        return [
            'ok'      => $code >= 200 && $code < 300,
            'message' => $code >= 200 && $code < 300
                ? __('REST health endpoint accepted the MCP token.', 'livecanvas-forge-ai')
                : sprintf(__('REST health returned HTTP %d.', 'livecanvas-forge-ai'), $code),
            'status'  => $code,
        ];
    }

    private function check_node(array $expected): array {
        $command = (string) ($expected['command'] ?? 'node');
        $execution = $this->run_process($this->quote_shell_value($command) . ' --version', [], untrailingslashit(ABSPATH), 8);

        return [
            'ok'      => !empty($execution['ok']),
            'message' => !empty($execution['ok'])
                ? sprintf(__('Node.js is available: %1$s (%2$s)', 'livecanvas-forge-ai'), trim((string) ($execution['stdout'] ?? '')), $command)
                : sprintf(__('Node.js is not available to the current PHP process. Install Node.js or set LCFA_NODE_BINARY to an executable node path. Checked command: %s', 'livecanvas-forge-ai'), $command),
            'command' => $command,
        ];
    }

    private function run_mcp_status_tool(array $expected): array {
        $args = [
            (string) ($expected['command'] ?? 'node'),
            (string) $expected['script_path'],
            '--tool',
            'get_mcp_status',
            '--tool-args',
            '{}',
            '--output',
            'json',
        ];
        $command = implode(' ', array_map([$this, 'quote_shell_value'], $args));
        $execution = $this->run_process($command, (array) $expected['environment'], (string) $expected['wp_root'], 20);

        if (empty($execution['ok'])) {
            return [
                'ok'      => false,
                'message' => $this->get_process_failure_message((string) ($execution['stderr'] ?? $execution['stdout'] ?? '')),
                'stderr'  => (string) ($execution['stderr'] ?? ''),
            ];
        }

        $payload = json_decode((string) ($execution['stdout'] ?? ''), true);
        $available = is_array($payload)
            && !empty($payload['ok'])
            && !empty($payload['result']['mcp']['local_bridge']['available']);
        $local_bridge = is_array($payload['result']['mcp']['local_bridge'] ?? null)
            ? $payload['result']['mcp']['local_bridge']
            : [];

        return [
            'ok'      => $available,
            'message' => $available
                ? __('Local MCP tool returned available=true.', 'livecanvas-forge-ai')
                : __('Local MCP tool did not report an available bridge.', 'livecanvas-forge-ai'),
            'details' => $available || $local_bridge === [] ? [] : [
                'local_bridge' => [
                    'available'      => !empty($local_bridge['available']),
                    'local_site'     => !empty($local_bridge['local_site']),
                    'node_available' => !empty($local_bridge['node_available']),
                    'rest_reachable' => !empty($local_bridge['rest_reachable']),
                    'script_exists'  => !empty($local_bridge['script_exists']),
                    'message'        => (string) ($local_bridge['message'] ?? ''),
                ],
            ],
        ];
    }

    private function clear_local_mcp_status_cache(): void {
        if (!function_exists('delete_transient')) {
            return;
        }

        delete_transient('lcfa_local_mcp_status_' . md5(home_url('/') . '|' . LCFA_VERSION));
    }

    private function get_process_failure_message(string $stderr): string {
        if (stripos($stderr, 'Sorry, you are not allowed') !== false || stripos($stderr, 'rest_forbidden') !== false || stripos($stderr, '401') !== false) {
            return __('Token mismatch between WordPress and Codex config. Sync Codex config or rotate token and regenerate.', 'livecanvas-forge-ai');
        }

        if (stripos($stderr, 'No such file') !== false || stripos($stderr, 'MODULE_NOT_FOUND') !== false) {
            return __('Codex is pointing to an old plugin path. Sync Codex config.', 'livecanvas-forge-ai');
        }

        if (stripos($stderr, 'timed out') !== false || stripos($stderr, 'timeout') !== false) {
            return __('MCP command timed out. Check the local REST URL and PHP-FPM worker availability.', 'livecanvas-forge-ai');
        }

        return trim($stderr) !== '' ? trim($stderr) : __('The local MCP command failed.', 'livecanvas-forge-ai');
    }

    private function contents_match_expected(string $contents, array $expected): array {
        return [
            'server'    => strpos($contents, '[mcp_servers.' . self::SERVER_NAME . ']') !== false,
            'command'   => strpos($contents, 'command = "' . $this->escape_toml_string((string) $expected['command']) . '"') !== false,
            'script'    => strpos($contents, (string) $expected['script_path']) !== false,
            'rest_base' => strpos($contents, (string) $expected['environment']['LCFA_REST_BASE']) !== false,
            'site_url'  => strpos($contents, (string) $expected['environment']['LCFA_SITE_URL']) !== false,
            'site_fingerprint' => strpos($contents, (string) $expected['environment']['LCFA_SITE_FINGERPRINT']) !== false,
            'wp_root'   => strpos($contents, (string) $expected['environment']['LCFA_WP_ROOT']) !== false,
            'token'     => strpos($contents, (string) $expected['environment']['LCFA_MCP_TOKEN']) !== false,
        ];
    }

    private function replace_livecanvas_sections(string $contents, string $snippet): string {
        $lines = preg_split('/\R/', $contents);
        if (!is_array($lines)) {
            $lines = [];
        }

        $kept = [];
        $skipping = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*\[mcp_servers\.livecanvas-forge(?:\.env)?\]\s*$/', (string) $line)) {
                $skipping = true;
                continue;
            }

            if ($skipping && preg_match('/^\s*\[.+\]\s*$/', (string) $line)) {
                $skipping = false;
            }

            if (!$skipping) {
                $kept[] = (string) $line;
            }
        }

        $base = trim(implode("\n", $kept));

        return ($base !== '' ? $base . "\n\n" : '') . trim($snippet) . "\n";
    }

    private function build_toml_snippet(string $command, array $args, array $environment): string {
        $lines = [
            '[mcp_servers.' . self::SERVER_NAME . ']',
            'command = ' . $this->quote_toml_string($command),
            'args = [' . implode(', ', array_map([$this, 'quote_toml_string'], $args)) . ']',
            '',
            '[mcp_servers.' . self::SERVER_NAME . '.env]',
        ];

        foreach ($environment as $key => $value) {
            $lines[] = $key . ' = ' . $this->quote_toml_string((string) $value);
        }

        return implode("\n", $lines);
    }

    private function build_codex_add_command(string $command, array $args, array $environment): string {
        $parts = ['codex', 'mcp', 'add', self::SERVER_NAME];
        foreach ($environment as $key => $value) {
            $parts[] = '--env';
            $parts[] = $key . '=' . (string) $value;
        }
        $parts[] = '--';
        $parts[] = $command;
        $parts = array_merge($parts, $args);

        return implode(' ', array_map([$this, 'quote_shell_value'], $parts));
    }

    private function resolve_node_command(): string {
        foreach (['LCFA_NODE_BINARY', 'NODE_BINARY'] as $env_key) {
            $candidate = trim((string) getenv($env_key));
            if ($candidate !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach ($this->get_node_path_candidates() as $candidate) {
            if ($candidate !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        $execution = $this->run_process('command -v node', [], defined('ABSPATH') ? untrailingslashit((string) ABSPATH) : '', 3);
        $resolved = trim((string) ($execution['stdout'] ?? ''));
        if (!empty($execution['ok']) && $resolved !== '' && is_executable($resolved)) {
            return $resolved;
        }

        return 'node';
    }

    private function get_node_path_candidates(): array {
        $candidates = self::NODE_PATH_CANDIDATES;
        $home = trim((string) getenv('HOME'));
        if ($home !== '') {
            $nvm_matches = glob(rtrim($home, '/\\') . '/.nvm/versions/node/*/bin/node');
            if (is_array($nvm_matches)) {
                rsort($nvm_matches, SORT_NATURAL);
                $candidates = array_merge($candidates, $nvm_matches);
            }
            $fnm_matches = glob(rtrim($home, '/\\') . '/.local/share/fnm/node-versions/*/installation/bin/node');
            if (is_array($fnm_matches)) {
                rsort($fnm_matches, SORT_NATURAL);
                $candidates = array_merge($candidates, $fnm_matches);
            }
        }

        return array_values(array_unique(array_map('strval', $candidates)));
    }

    private function get_config_path(string $scope, string $wp_root): string {
        if ($scope === 'project' && $wp_root !== '') {
            return rtrim($wp_root, '/\\') . '/.codex/config.toml';
        }

        return $this->get_global_config_path();
    }

    private function get_global_config_path(): string {
        $home = (string) getenv('HOME');
        if ($home === '') {
            return '';
        }

        return rtrim($home, '/\\') . '/.codex/config.toml';
    }

    private function resolve_config_scope(array $connections): string {
        if (method_exists('LCFA_Settings', 'sanitize_codex_config_scope')) {
            return LCFA_Settings::sanitize_codex_config_scope((string) ($connections['codex_config_scope'] ?? 'project'));
        }

        $scope = (string) ($connections['codex_config_scope'] ?? 'project');

        return in_array($scope, ['project', 'global'], true) ? $scope : 'project';
    }

    private function get_site_fingerprint(): string {
        if (method_exists('LCFA_Settings', 'get_site_fingerprint')) {
            return LCFA_Settings::get_site_fingerprint();
        }

        $payload = [
            'site_url'  => function_exists('home_url') ? rtrim(home_url('/'), '/') . '/' : '',
            'rest_base' => function_exists('rest_url') ? rtrim(rest_url('lcfa/v1/'), '/') . '/' : '',
            'wp_root'   => defined('ABSPATH') && is_string(ABSPATH) ? rtrim((string) ABSPATH, '/\\') : '',
        ];

        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', (string) $encoded), 0, 16);
    }

    private function resolve_wp_root(array $connections): string {
        $workspace_root = untrailingslashit(trim((string) ($connections['workspace_root'] ?? '')));
        if ($this->looks_like_wordpress_root($workspace_root)) {
            return $workspace_root;
        }

        return defined('ABSPATH') && is_string(ABSPATH) ? untrailingslashit((string) ABSPATH) : '';
    }

    private function looks_like_wordpress_root(string $path): bool {
        return $path !== '' && (is_file($path . '/wp-load.php') || is_dir($path . '/wp-content'));
    }

    private function quote_toml_string(string $value): string {
        return '"' . $this->escape_toml_string($value) . '"';
    }

    private function escape_toml_string(string $value): string {
        return str_replace(["\\", '"'], ["\\\\", '\\"'], $value);
    }

    private function quote_shell_value(string $value): string {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function run_process(string $command, array $environment, string $working_directory, int $timeout): array {
        if (!function_exists('proc_open')) {
            return [
                'ok'      => false,
                'message' => __('proc_open is not available.', 'livecanvas-forge-ai'),
            ];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = array_merge($this->collect_base_environment(), $environment);
        $process = @proc_open($command, $descriptors, $pipes, $working_directory !== '' ? $working_directory : null, $env);

        if (!is_resource($process)) {
            return [
                'ok'      => false,
                'message' => __('Could not start the local process.', 'livecanvas-forge-ai'),
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $started = microtime(true);
        $timed_out = false;
        $status_exit_code = null;

        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $status_exit_code = isset($status['exitcode']) ? (int) $status['exitcode'] : null;
                break;
            }
            if ((microtime(true) - $started) >= $timeout) {
                $timed_out = true;
                proc_terminate($process);
                break;
            }
            usleep(50000);
        } while (true);

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);
        if ($exit_code === -1 && $status_exit_code !== null) {
            $exit_code = $status_exit_code;
        }

        return [
            'ok'        => !$timed_out && $exit_code === 0,
            'exit_code' => $exit_code,
            'stdout'    => $stdout,
            'stderr'    => $timed_out ? trim($stderr . "\nProcess timed out.") : $stderr,
        ];
    }

    private function collect_base_environment(): array {
        $environment = [];
        foreach (['PATH', 'HOME', 'USER', 'TMPDIR', 'TMP', 'TEMP', 'SHELL'] as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                $environment[$key] = $value;
            }
        }
        $path_parts = array_filter(explode(PATH_SEPARATOR, (string) ($environment['PATH'] ?? '')));
        $path_parts = array_merge($path_parts, [
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/bin',
            '/usr/sbin',
            '/sbin',
        ]);
        $environment['PATH'] = implode(PATH_SEPARATOR, array_values(array_unique($path_parts)));

        return $environment;
    }
}
