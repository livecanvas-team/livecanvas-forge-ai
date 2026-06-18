<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Bundle_Builder {
    public function build(array $payload): array {
        $client         = $this->normalize_client((string) ($payload['client'] ?? 'codex'));
        $mode           = $this->normalize_mode((string) ($payload['mode'] ?? 'local'));
        $common         = is_array($payload['common'] ?? null) ? $payload['common'] : [];
        $workspace_root = $this->normalize_workspace_root((string) ($payload['workspace_root'] ?? ''), $common, $mode);
        $command_input  = trim((string) ($payload['client_payload']['command'] ?? ''));
        $command        = $this->normalize_command($this->tokenize_command($command_input), $workspace_root, $mode);
        $command_string = $this->join_shell_tokens($command);
        $environment    = $this->normalize_environment((array) ($payload['client_payload']['env'] ?? []), $workspace_root, $mode);
        $claude_connection_target = $this->normalize_claude_target($payload, $client);
        $agent_start_tool = $this->build_connection_handoff_tool($environment, $command);
        $handoff_package_tool = $this->build_handoff_package_tool($environment, $command);

        $shortcut = $this->build_client_shortcut($client, $mode, $claude_connection_target, $command, $environment);

        return [
            'client'              => $client,
            'claude_connection_target' => $claude_connection_target,
            'mode'                => $mode,
            'server_name'         => 'livecanvas-forge',
            'workspace_root'      => $workspace_root,
            'connection_strategy' => (string) ($common['connection_strategy'] ?? ($mode === 'remote' ? 'remote-rest' : 'local-mcp')),
            'mcp_adapter_url'     => (string) ($common['mcp_adapter_url'] ?? ''),
            'remote_site_url'     => (string) ($common['remote_site_url'] ?? ''),
            'command'             => $command,
            'command_string'      => $command_string,
            'copy_command_string' => $shortcut['command'] ?: $command_string,
            'shortcut_title'      => $shortcut['title'],
            'shortcut_command'    => $shortcut['command'],
            'environment'         => $environment,
            'workspace_files'     => $this->build_workspace_files($client, $mode, $workspace_root, $claude_connection_target, $command, $environment),
            'download_files'      => $this->build_download_files($client, $mode, $claude_connection_target, $command, $environment),
            'smoke_test_command'  => $this->build_smoke_test_command($environment, $command),
            'agent_start_tool'    => $agent_start_tool,
            'connection_handoff_tool' => $agent_start_tool,
            'handoff_package_tool' => $handoff_package_tool,
            'agent_start_prompt'  => $this->build_agent_start_prompt($agent_start_tool, $handoff_package_tool),
            'status'              => 'generated',
        ];
    }

    private function normalize_client(string $client): string {
        $client = sanitize_key($client);

        if ($client === 'claude-code') {
            return 'claude';
        }

        return in_array($client, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true)
            ? $client
            : 'codex';
    }

    private function normalize_claude_target(array $payload, string $client): string {
        if ($client !== 'claude') {
            return '';
        }

        $raw_client = sanitize_key((string) ($payload['client'] ?? ''));
        if ($raw_client === 'claude-code') {
            return 'cli';
        }

        $target = sanitize_key((string) ($payload['claude_connection_target'] ?? ''));

        return in_array($target, ['desktop_app', 'cli'], true) ? $target : 'cli';
    }

    private function normalize_mode(string $mode): string {
        return $mode === 'remote' ? 'remote' : 'local';
    }

    private function normalize_workspace_root(string $workspace_root, array $common, string $mode): string {
        $workspace_root = trim($workspace_root);
        $candidates = $this->collect_workspace_root_candidates($common);
        $preferred_candidate = $candidates[0] ?? '';

        if ($workspace_root !== '') {
            $normalized_workspace_root = untrailingslashit($workspace_root);

            if ($this->looks_like_runtime_workspace_root($normalized_workspace_root)) {
                return $preferred_candidate;
            }

            if ($this->should_replace_runtime_workspace_root($normalized_workspace_root, $preferred_candidate, $common)) {
                return $preferred_candidate;
            }

            return $normalized_workspace_root;
        }

        if ($mode !== 'local') {
            return '';
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '') {
                continue;
            }

            return untrailingslashit($candidate);
        }

        return '';
    }

    private function collect_workspace_root_candidates(array $common): array {
        $candidates = [];

        if (defined('WP_CONTENT_DIR') && is_string(WP_CONTENT_DIR)) {
            $derived_root = $this->derive_wordpress_root_from_content_dir((string) WP_CONTENT_DIR);
            if ($derived_root !== '') {
                $candidates[] = $derived_root;
            }
        }

        if (defined('WP_PLUGIN_DIR') && is_string(WP_PLUGIN_DIR)) {
            $derived_root = $this->derive_wordpress_root_from_plugin_dir((string) WP_PLUGIN_DIR);
            if ($derived_root !== '') {
                $candidates[] = $derived_root;
            }
        }

        $current_working_directory = getcwd();
        if (is_string($current_working_directory) && $this->looks_like_wordpress_root($current_working_directory)) {
            $candidates[] = $current_working_directory;
        }

        if (defined('LCFA_DIR') && is_string(LCFA_DIR)) {
            $derived_root = $this->derive_wordpress_root_from_plugin_dir((string) LCFA_DIR);
            if ($derived_root !== '') {
                $candidates[] = $derived_root;
            }
        }

        if (!empty($common['wp_root'])) {
            $candidates[] = (string) $common['wp_root'];
        }

        if (defined('ABSPATH') && is_string(ABSPATH)) {
            $candidates[] = (string) ABSPATH;
        }

        $normalized_candidates = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $normalized_candidate = untrailingslashit($candidate);
            if ($normalized_candidate === '') {
                continue;
            }

            if ($this->looks_like_runtime_workspace_root($normalized_candidate)) {
                continue;
            }

            $normalized_candidates[] = $normalized_candidate;
        }

        return array_values(array_unique($normalized_candidates));
    }

    private function should_replace_runtime_workspace_root(string $workspace_root, string $preferred_candidate, array $common): bool {
        $workspace_root = wp_normalize_path(untrailingslashit($workspace_root));
        $preferred_candidate = wp_normalize_path(untrailingslashit($preferred_candidate));

        if ($workspace_root === '' || $preferred_candidate === '' || $workspace_root === $preferred_candidate) {
            return false;
        }

        $runtime_roots = [];

        if (!empty($common['wp_root'])) {
            $runtime_roots[] = wp_normalize_path(untrailingslashit((string) $common['wp_root']));
        }

        if (defined('ABSPATH') && is_string(ABSPATH)) {
            $runtime_roots[] = wp_normalize_path(untrailingslashit((string) ABSPATH));
        }

        $runtime_roots = array_values(array_unique(array_filter($runtime_roots, static function (string $value): bool {
            return $value !== '';
        })));

        return in_array($workspace_root, $runtime_roots, true) && !in_array($preferred_candidate, $runtime_roots, true);
    }

    private function derive_wordpress_root_from_content_dir(string $content_dir): string {
        $content_dir = wp_normalize_path(untrailingslashit($content_dir));
        $needle = '/wp-content';
        $position = strpos($content_dir, $needle);

        if ($position === false) {
            return '';
        }

        return substr($content_dir, 0, $position);
    }

    private function derive_wordpress_root_from_plugin_dir(string $plugin_dir): string {
        $plugin_dir = wp_normalize_path(untrailingslashit($plugin_dir));
        $needle = '/wp-content/plugins/';
        $position = strpos($plugin_dir, $needle);

        if ($position === false) {
            return '';
        }

        return substr($plugin_dir, 0, $position);
    }

    private function looks_like_wordpress_root(string $path): bool {
        $path = untrailingslashit($path);

        if ($path === '') {
            return false;
        }

        return is_dir($path . '/wp-content') || file_exists($path . '/wp-config.php');
    }

    private function looks_like_runtime_workspace_root(string $path): bool {
        $path = wp_normalize_path(untrailingslashit($path));

        if ($path === '') {
            return false;
        }

        return in_array($path, [
            '/wordpress',
            '/app',
            '/app/public',
            '/var/www',
            '/var/www/html',
            '/srv/www',
            '/srv/www/html',
            '/usr/share/nginx/html',
        ], true);
    }

    private function normalize_environment(array $environment, string $workspace_root, string $mode): array {
        $normalized = [];

        foreach ($environment as $key => $value) {
            if (is_int($key)) {
                $parts = explode('=', (string) $value, 2);
                $env_key = trim((string) ($parts[0] ?? ''));
                $env_value = (string) ($parts[1] ?? '');
            } else {
                $env_key = trim((string) $key);
                $env_value = (string) $value;
            }

            if ($env_key === '') {
                continue;
            }

            $normalized[$env_key] = $env_value;
        }

        if (!empty($normalized['LCFA_REST_BASE'])) {
            $normalized['LCFA_REST_BASE'] = trailingslashit($normalized['LCFA_REST_BASE']);
        }

        if ($mode === 'local') {
            if ($workspace_root !== '') {
                $normalized['LCFA_WP_ROOT'] = $workspace_root;
            } else {
                unset($normalized['LCFA_WP_ROOT']);
            }
        }

        if ($mode === 'remote') {
            unset($normalized['LCFA_WP_ROOT']);
        }

        ksort($normalized);

        return $normalized;
    }

    private function tokenize_command(string $command): array {
        if ($command === '') {
            return [];
        }

        preg_match_all('/"(?:\\\\.|[^"])*"|\'(?:\\\\.|[^\'])*\'|[^\s]+/', $command, $matches);

        return array_values(array_filter(array_map(static function (string $token): string {
            $token = trim($token);
            if ($token === '') {
                return '';
            }

            if (($token[0] === '"' && substr($token, -1) === '"') || ($token[0] === "'" && substr($token, -1) === "'")) {
                return stripcslashes(substr($token, 1, -1));
            }

            return $token;
        }, $matches[0] ?? [])));
    }

    private function normalize_command(array $command, string $workspace_root, string $mode): array {
        if ($command === [] || $mode !== 'local' || $workspace_root === '') {
            return $command;
        }

        $workspace_root = untrailingslashit($workspace_root);
        $relative_script = 'wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';

        foreach ($command as $index => $token) {
            $normalized = ltrim(wp_normalize_path((string) $token), './');

            if ($normalized !== $relative_script) {
                continue;
            }

            $command[$index] = $workspace_root . '/' . $relative_script;
        }

        return $command;
    }

    private function build_workspace_files(string $client, string $mode, string $workspace_root, string $claude_connection_target, array $command, array $environment): array {
        if ($mode !== 'local' || $workspace_root === '') {
            return [];
        }

        switch ($client) {
            case 'opencode':
                return [[
                    'path'    => $workspace_root . '/opencode.json',
                    'type'    => 'json',
                    'label'   => __('OpenCode config', 'livecanvas-forge-ai'),
                    'content' => $this->build_opencode_config($command, $environment),
                ]];
            case 'cursor':
                return [[
                    'path'    => $workspace_root . '/.cursor/mcp.json',
                    'type'    => 'json',
                    'label'   => __('Cursor MCP config', 'livecanvas-forge-ai'),
                    'content' => $this->build_cursor_config($command, $environment),
                ]];
            case 'codex':
                return [[
                    'path'    => $workspace_root . '/livecanvas-forge.codex.sh',
                    'type'    => 'shell',
                    'label'   => __('Codex registration helper', 'livecanvas-forge-ai'),
                    'content' => $this->build_codex_script($command, $environment),
                ]];
            case 'claude':
                if ($claude_connection_target === 'desktop_app') {
                    return [[
                        'path'    => $workspace_root . '/livecanvas-forge.claude-desktop.json',
                        'type'    => 'json',
                        'label'   => __('Claude Desktop config', 'livecanvas-forge-ai'),
                        'content' => $this->build_claude_desktop_config($command, $environment),
                    ]];
                }

                return [[
                    'path'    => $workspace_root . '/livecanvas-forge.claude-cli.sh',
                    'type'    => 'shell',
                    'label'   => __('Claude CLI registration helper', 'livecanvas-forge-ai'),
                    'content' => $this->build_claude_script($command, $environment),
                ]];
            default:
                return [[
                    'path'    => $workspace_root . '/livecanvas-forge.mcp.txt',
                    'type'    => 'text',
                    'label'   => __('Generic MCP bootstrap', 'livecanvas-forge-ai'),
                    'content' => $this->build_generic_snippet($command, $environment),
                ]];
        }
    }

    private function build_download_files(string $client, string $mode, string $claude_connection_target, array $command, array $environment): array {
        switch ($client) {
            case 'opencode':
                return [[
                    'name'    => 'opencode.json',
                    'mime'    => 'application/json',
                    'content' => $this->build_opencode_config($command, $environment),
                ]];
            case 'cursor':
                return [[
                    'name'    => 'mcp.json',
                    'mime'    => 'application/json',
                    'content' => $this->build_cursor_config($command, $environment),
                ]];
            case 'codex':
                return [[
                    'name'    => 'livecanvas-forge.codex.sh',
                    'mime'    => 'text/x-shellscript',
                    'content' => $this->build_codex_script($command, $environment),
                ]];
            case 'claude':
                if ($claude_connection_target === 'desktop_app') {
                    return [[
                        'name'    => $mode === 'remote' ? 'livecanvas-forge.claude-desktop.txt' : 'livecanvas-forge.claude-desktop.json',
                        'mime'    => $mode === 'remote' ? 'text/plain' : 'application/json',
                        'content' => $mode === 'remote'
                            ? $this->build_claude_desktop_reference($command, $environment)
                            : $this->build_claude_desktop_config($command, $environment),
                    ]];
                }

                return [[
                    'name'    => 'livecanvas-forge.claude-cli.sh',
                    'mime'    => 'text/x-shellscript',
                    'content' => $this->build_claude_script($command, $environment),
                ]];
            default:
                return [[
                    'name'    => 'livecanvas-forge-mcp.txt',
                    'mime'    => 'text/plain',
                    'content' => $this->build_generic_snippet($command, $environment),
                ]];
        }
    }

    private function build_opencode_config(array $command, array $environment): string {
        return (string) wp_json_encode([
            '$schema' => 'https://opencode.ai/config.json',
            'mcp'     => [
                'livecanvas-forge' => [
                    'type'        => 'local',
                    'command'     => $command,
                    'enabled'     => true,
                    'environment' => (object) $environment,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function build_cursor_config(array $command, array $environment): string {
        return (string) wp_json_encode([
            'mcpServers' => [
                'livecanvas-forge' => [
                    'command' => $command[0] ?? 'node',
                    'args'    => array_slice($command, 1),
                    'env'     => (object) $environment,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function build_claude_desktop_config(array $command, array $environment): string {
        return (string) wp_json_encode([
            'mcpServers' => [
                'livecanvas-forge' => [
                    'type'    => 'stdio',
                    'command' => $command[0] ?? 'node',
                    'args'    => array_slice($command, 1),
                    'env'     => (object) $environment,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function build_claude_desktop_reference(array $command, array $environment): string {
        $lines = [
            '# Claude Desktop reference',
            '# Review these values before editing Claude Desktop on another machine or remote target.',
            '# Command',
            $command[0] ?? 'node',
            '',
            '# Args',
        ];

        foreach (array_slice($command, 1) as $token) {
            $lines[] = $token;
        }

        $lines[] = '';
        $lines[] = '# Environment';
        foreach ($environment as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $lines[] = '';
        $lines[] = '# Smoke test';
        $lines[] = $this->build_smoke_test_command($environment, $command);
        $lines[] = '';
        $lines[] = '# First agent prompt';
        $lines[] = $this->build_agent_start_prompt($this->build_connection_handoff_tool($environment, $command), $this->build_handoff_package_tool($environment, $command));

        return implode("\n", $lines) . "\n";
    }

    private function build_codex_script(array $command, array $environment): string {
        return "#!/usr/bin/env bash\nset -euo pipefail\n\n"
            . $this->build_codex_register_command($command, $environment)
            . "\n\n"
            . $this->build_agent_start_shell_notice('Codex', $environment, $command)
            . "\n";
    }

    private function build_claude_script(array $command, array $environment): string {
        return "#!/usr/bin/env bash\nset -euo pipefail\n\n"
            . $this->build_claude_register_command($command, $environment)
            . "\n\n"
            . $this->build_agent_start_shell_notice('Claude', $environment, $command)
            . "\n";
    }

    private function build_client_shortcut(string $client, string $mode, string $claude_connection_target, array $command, array $environment): array {
        switch ($client) {
            case 'codex':
                return [
                    'title'   => __('Codex shortcut', 'livecanvas-forge-ai'),
                    'command' => $this->build_codex_register_command($command, $environment),
                ];
            case 'claude':
                if ($claude_connection_target === 'desktop_app') {
                    return [
                        'title'   => $mode === 'remote'
                            ? __('Claude Desktop reference', 'livecanvas-forge-ai')
                            : __('Claude Desktop config', 'livecanvas-forge-ai'),
                        'command' => $mode === 'remote'
                            ? $this->build_claude_desktop_reference($command, $environment)
                            : $this->build_claude_desktop_config($command, $environment),
                    ];
                }

                return [
                    'title'   => __('Claude CLI shortcut', 'livecanvas-forge-ai'),
                    'command' => $this->build_claude_register_command($command, $environment),
                ];
            default:
                return [
                    'title'   => '',
                    'command' => '',
                ];
        }
    }

    private function build_codex_register_command(array $command, array $environment): string {
        $lines = [
            'LCFA_CODEX_BIN=""',
            'if command -v codex >/dev/null 2>&1; then',
            '  LCFA_CODEX_BIN="$(command -v codex)"',
            'elif [ -x "/Applications/Codex.app/Contents/Resources/codex" ]; then',
            '  LCFA_CODEX_BIN="/Applications/Codex.app/Contents/Resources/codex"',
            'fi',
            '',
            'if [ -n "$LCFA_CODEX_BIN" ]; then',
            '  "$LCFA_CODEX_BIN" mcp remove livecanvas-forge >/dev/null 2>&1 || true',
            '  "$LCFA_CODEX_BIN" mcp add livecanvas-forge \\',
        ];

        foreach ($environment as $key => $value) {
            $lines[] = '    --env ' . $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        $lines[] = '    -- ' . $this->join_shell_tokens($command);
        $lines[] = $this->uses_wordpress_mcp_remote_proxy($environment, $command)
            ? '  echo "Codex MCP server livecanvas-forge updated for the remote WordPress MCP Adapter. Restart Codex or reload the MCP server before testing."'
            : '  echo "Codex MCP server livecanvas-forge updated. Restart Codex or reload the MCP server before testing."';
        $lines[] = 'else';
        $lines[] = "  cat <<'EOF'";
        $lines[] = 'Codex CLI not found in PATH and the embedded desktop CLI was not found at /Applications/Codex.app/Contents/Resources/codex.';
        $lines[] = 'Add this MCP server to ~/.codex/config.toml, then reopen Codex:';
        $lines[] = '';
        $lines[] = $this->build_codex_config_snippet($command, $environment);
        $lines[] = 'EOF';
        $lines[] = '  exit 1';
        $lines[] = 'fi';

        return implode("\n", $lines);
    }

    private function build_codex_config_snippet(array $command, array $environment): string {
        $command_bin = $command[0] ?? 'node';
        $args = array_slice($command, 1);
        $lines = [
            '[mcp_servers.livecanvas-forge]',
            'command = ' . $this->quote_toml_string($command_bin),
            'args = [' . implode(', ', array_map([$this, 'quote_toml_string'], $args)) . ']',
            '',
            '[mcp_servers.livecanvas-forge.env]',
        ];

        foreach ($environment as $key => $value) {
            $lines[] = $key . ' = ' . $this->quote_toml_string($value);
        }

        return implode("\n", $lines);
    }

    private function build_claude_register_command(array $command, array $environment): string {
        $lines = [
            'claude mcp add --transport stdio livecanvas-forge \\',
        ];

        foreach ($environment as $key => $value) {
            $lines[] = '  --env ' . $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        $lines[] = '  -- ' . $this->join_shell_tokens($command);

        return implode("\n", $lines);
    }

    private function build_generic_snippet(array $command, array $environment): string {
        $lines = ['# Environment'];

        foreach ($environment as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $lines[] = '';
        $lines[] = '# Command';
        $lines[] = $this->join_shell_tokens($command);
        $lines[] = '';
        $lines[] = '# Smoke test';
        $lines[] = $this->build_smoke_test_command($environment, $command);
        $lines[] = '';
        $lines[] = '# First agent prompt';
        $lines[] = $this->build_agent_start_prompt($this->build_connection_handoff_tool($environment, $command), $this->build_handoff_package_tool($environment, $command));

        return implode("\n", $lines) . "\n";
    }

    private function build_smoke_test_command(array $environment, array $command): string {
        if ($this->uses_wordpress_mcp_remote_proxy($environment, $command)) {
            return "codex mcp get livecanvas-forge || /Applications/Codex.app/Contents/Resources/codex mcp get livecanvas-forge\n# Then reopen Codex and ask it to call livecanvas-forge-ai/get-snapshot.";
        }

        $lines = [];

        foreach ($environment as $key => $value) {
            $lines[] = $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        if ($command === []) {
            return '';
        }

        $command_with_tool = array_merge($command, ['--tool', 'get_snapshot', '--output', 'pretty']);
        $last_index = count($command_with_tool) - 1;
        $joined = [];

        foreach ($command_with_tool as $index => $token) {
            $suffix = $index === $last_index ? '' : ' \\';
            $joined[] = ($index === 0 ? '' : '  ') . $this->quote_shell_value($token) . $suffix;
        }

        $joined[0] = ltrim($joined[0], ' ');
        $lines[] = implode("\n", $joined);

        return implode("\n", $lines);
    }

    private function build_connection_handoff_tool(array $environment, array $command): string {
        return $this->uses_wordpress_mcp_remote_proxy($environment, $command)
            ? 'livecanvas-forge-ai/get-connection-handoff'
            : 'get_connection_handoff';
    }

    private function build_handoff_package_tool(array $environment, array $command): string {
        return $this->uses_wordpress_mcp_remote_proxy($environment, $command)
            ? 'livecanvas-forge-ai/get-agent-handoff-package'
            : 'get_agent_handoff_package';
    }

    private function build_agent_start_prompt(string $connection_tool, string $package_tool): string {
        $connection_tool = $connection_tool !== '' ? $connection_tool : 'get_connection_handoff';
        $package_tool = $package_tool !== '' ? $package_tool : 'get_agent_handoff_package';

        return implode("\n", [
            'Use the LiveCanvas AI Bridge MCP connection for this WordPress project.',
            'First call ' . $connection_tool . ' with {"limit":5}.',
            'If this prompt appears inside a returned connection_handoff payload, treat that call as already complete and continue.',
            'Read the returned connection status, transport, first-prompt guardrails, and recommended sequence.',
            'Then call ' . $package_tool . ' with {"limit":5} only if you need the full runbook, smoke tests, readiness files, ability manifest, MCP write policy, or recent run summary.',
            'Summarize the site framework, available tools, active risks, and whether write abilities are exposed.',
            'Stay read-only until a preview or dry-run has been reviewed.',
        ]);
    }

    private function build_agent_start_shell_notice(string $agent_label, array $environment, array $command): string {
        return "cat <<'EOF'\n\nNext prompt for " . $agent_label . ":\n"
            . $this->build_agent_start_prompt($this->build_connection_handoff_tool($environment, $command), $this->build_handoff_package_tool($environment, $command))
            . "\nEOF";
    }

    private function uses_wordpress_mcp_remote_proxy(array $environment, array $command): bool {
        $command_string = implode(' ', $command);

        return !empty($environment['WP_API_URL'])
            && strpos($command_string, '@automattic/mcp-wordpress-remote') !== false;
    }

    private function join_shell_tokens(array $command): string {
        return implode(' ', array_map([$this, 'quote_shell_value'], $command));
    }

    private function quote_shell_value(string $value): string {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function quote_toml_string(string $value): string {
        return (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
