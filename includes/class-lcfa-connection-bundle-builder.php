<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Bundle_Builder {
    public function build(array $payload): array {
        $client         = $this->normalize_client((string) ($payload['client'] ?? 'codex'));
        $mode           = $this->normalize_mode((string) ($payload['mode'] ?? 'local'));
        $common         = is_array($payload['common'] ?? null) ? $payload['common'] : [];
        $wordpress_root = $this->normalize_wordpress_root((string) ($payload['wordpress_root'] ?? ''), $common, $mode);
        $workspace_root = $this->normalize_workspace_root((string) ($payload['workspace_root'] ?? ''), $wordpress_root, $common, $mode);
        $mcp_url        = $this->normalize_remote_url((string) ($payload['client_payload']['url'] ?? ($common['mcp_url'] ?? '')));
        $oauth_resource = $this->normalize_remote_url((string) ($common['oauth_resource'] ?? $mcp_url));
        $command_input  = trim((string) ($payload['client_payload']['command'] ?? ''));
        $command        = $this->normalize_command($this->tokenize_command($command_input), $wordpress_root, $mode);
        $command_string = $this->join_shell_tokens($command);
        $environment    = $this->normalize_environment((array) ($payload['client_payload']['env'] ?? []), $wordpress_root, $workspace_root, $mode, $client, $common);
        $claude_connection_target = $this->normalize_claude_target($payload, $client);
        $claude_desktop_config_path = $this->normalize_claude_desktop_config_path((string) ($payload['claude_desktop_config_path'] ?? ''));
        $server_name = $this->resolve_server_name($client, $mode, $common, $command, $mcp_url);
        $agent_start_tool = $this->build_connection_handoff_tool($environment, $command, $mcp_url);
        $handoff_package_tool = $this->build_handoff_package_tool($environment, $command, $mcp_url);
        $codex_config_snippet = $client === 'codex'
            ? $this->build_codex_config_snippet($command, $environment, $server_name, $mcp_url, $oauth_resource)
            : '';

        $shortcut = $this->build_client_shortcut($client, $mode, $claude_connection_target, $command, $environment, $server_name, $mcp_url, $oauth_resource);
        $workspace_files = $this->build_workspace_files($client, $mode, $workspace_root, $claude_connection_target, $command, $environment, $server_name);
        $install_files = $this->build_install_files($client, $mode, $claude_connection_target, $claude_desktop_config_path, $workspace_files);

        return [
            'client'              => $client,
            'claude_connection_target' => $claude_connection_target,
            'mode'                => $mode,
            'server_name'         => $server_name,
            'workspace_root'      => $workspace_root,
            'agent_workspace_root'=> $workspace_root,
            'wordpress_root'      => $wordpress_root,
            'connection_strategy' => (string) ($common['connection_strategy'] ?? ($mode === 'remote' ? 'remote-rest' : 'local-mcp')),
            'mcp_adapter_url'     => (string) ($common['mcp_adapter_url'] ?? ''),
            'mcp_url'             => $mcp_url,
            'oauth_resource'      => $oauth_resource,
            'remote_site_url'     => (string) ($common['remote_site_url'] ?? ''),
            'command'             => $command,
            'command_string'      => $command_string,
            'copy_command_string' => $shortcut['command'] ?: $command_string,
            'shortcut_title'      => $shortcut['title'],
            'shortcut_command'    => $shortcut['command'],
            'codex_config_snippet' => $codex_config_snippet,
            'codex_project_config_path' => $client === 'codex' ? ($mode === 'local' && $workspace_root !== '' ? $workspace_root . '/.codex/config.toml' : '.codex/config.toml') : '',
            'claude_desktop_config_path' => $claude_desktop_config_path,
            'environment'         => $environment,
            'workspace_files'     => $workspace_files,
            'install_files'       => $install_files,
            'install_target'      => $client === 'claude' && $mode === 'local' && $claude_connection_target === 'desktop_app' && $claude_desktop_config_path !== ''
                ? 'claude_desktop'
                : 'workspace',
            'download_files'      => $this->build_download_files($client, $mode, $claude_connection_target, $command, $environment, $server_name, $mcp_url, $oauth_resource),
            'smoke_test_command'  => $this->build_smoke_test_command($environment, $command, $server_name, $mcp_url, $client),
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

    private function normalize_claude_desktop_config_path(string $path): string {
        $path = trim(wp_normalize_path($path));
        if ($path === '' || !preg_match('#^(?:/|[A-Za-z]:/)#', $path)) {
            return '';
        }

        if (basename($path) !== 'claude_desktop_config.json') {
            return '';
        }

        return $path;
    }

    private function normalize_remote_url(string $url): string {
        $url = trim($url);

        return function_exists('esc_url_raw') ? esc_url_raw($url) : $url;
    }

    private function resolve_server_name(string $client, string $mode, array $common, array $command, string $mcp_url = ''): string {
        if ($client === 'codex' && $mode === 'remote' && (string) ($common['connection_strategy'] ?? '') === 'oauth-direct' && $mcp_url !== '') {
            $host = sanitize_key(str_replace('.', '-', (string) wp_parse_url($mcp_url, PHP_URL_HOST)));
            $fingerprint = sanitize_key((string) ($common['site_fingerprint'] ?? ''));
            $suffix = $fingerprint !== '' ? '-' . substr($fingerprint, 0, 8) : '';

            return substr('livecanvas-' . ($host !== '' ? $host : 'site') . $suffix, 0, 64);
        }

        if ($mode === 'remote' && (string) ($common['connection_strategy'] ?? '') === 'ai-bridge-session') {
            return 'livecanvas-ai-bridge';
        }

        return 'livecanvas-forge';
    }

    private function normalize_workspace_root(string $workspace_root, string $wordpress_root, array $common, string $mode): string {
        $workspace_root = trim($workspace_root);

        if ($workspace_root !== '') {
            $normalized_workspace_root = untrailingslashit($workspace_root);

            if ($this->looks_like_runtime_workspace_root($normalized_workspace_root)) {
                return $wordpress_root !== '' && !$this->looks_like_runtime_workspace_root($wordpress_root)
                    ? $this->infer_agent_workspace_root($wordpress_root)
                    : '';
            }

            if (
                $wordpress_root !== ''
                && wp_normalize_path($normalized_workspace_root) === wp_normalize_path($wordpress_root)
            ) {
                return $this->infer_agent_workspace_root($wordpress_root);
            }

            return $normalized_workspace_root;
        }

        if ($mode !== 'local') {
            return '';
        }

        $common_workspace_root = trim((string) ($common['agent_workspace_root'] ?? ''));
        if ($common_workspace_root !== '' && !$this->looks_like_runtime_workspace_root($common_workspace_root)) {
            return untrailingslashit($common_workspace_root);
        }

        return $wordpress_root !== '' && !$this->looks_like_runtime_workspace_root($wordpress_root)
            ? $this->infer_agent_workspace_root($wordpress_root)
            : '';
    }

    private function normalize_wordpress_root(string $wordpress_root, array $common, string $mode): string {
        if ($mode !== 'local') {
            return '';
        }

        $wordpress_root = untrailingslashit(trim($wordpress_root));
        $candidates = $this->collect_wordpress_root_candidates($common);
        $preferred_candidate = $candidates[0] ?? '';

        if ($wordpress_root !== '' && !$this->looks_like_runtime_workspace_root($wordpress_root)) {
            return $wordpress_root;
        }

        return $preferred_candidate;
    }

    private function collect_wordpress_root_candidates(array $common): array {
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

    private function infer_agent_workspace_root(string $wordpress_root): string {
        $wordpress_root = untrailingslashit($wordpress_root);
        $normalized = wp_normalize_path($wordpress_root);

        if (basename($normalized) === 'public' && basename(dirname($normalized)) === 'app') {
            return dirname(dirname($wordpress_root));
        }

        return $wordpress_root;
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

    private function normalize_environment(array $environment, string $wordpress_root, string $workspace_root, string $mode, string $client, array $common): array {
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
            unset(
                $normalized['LCFA_MCP_TOKEN'],
                $normalized['LCFA_MCP_SESSION'],
                $normalized['LCFA_MCP_SESSION_TOKEN'],
                $normalized['WP_API_PASSWORD']
            );

            if ($wordpress_root !== '') {
                $normalized['LCFA_WP_ROOT'] = $wordpress_root;
            } else {
                unset($normalized['LCFA_WP_ROOT']);
            }
            if ($workspace_root !== '') {
                $normalized['LCFA_AGENT_WORKSPACE_ROOT'] = $workspace_root;
            } else {
                unset($normalized['LCFA_AGENT_WORKSPACE_ROOT']);
            }

            $normalized['LCFA_AGENT'] = $client;
            $normalized['LCFA_PAIRING_SCOPES'] = trim((string) ($common['pairing_scopes'] ?? ($normalized['LCFA_PAIRING_SCOPES'] ?? 'read,preview')));
            if ($client === 'opencode') {
                $normalized['LCFA_TOOL_PROFILE'] = 'compact';
            }

            $framework = sanitize_key((string) ($common['framework'] ?? ($normalized['LCFA_FRAMEWORK'] ?? '')));
            if (in_array($framework, ['picowind', 'picostrap'], true)) {
                $normalized['LCFA_FRAMEWORK'] = $framework;
            } else {
                unset($normalized['LCFA_FRAMEWORK']);
            }

            foreach ([
                'LCFA_REST_BASE' => 'rest_base',
                'LCFA_SITE_URL' => 'site_url',
                'LCFA_SITE_FINGERPRINT' => 'site_fingerprint',
            ] as $environment_key => $common_key) {
                if (empty($normalized[$environment_key]) && !empty($common[$common_key])) {
                    $normalized[$environment_key] = (string) $common[$common_key];
                }
            }

            if (empty($normalized['LCFA_PROJECT_LABEL'])) {
                $site_host = !empty($normalized['LCFA_SITE_URL'])
                    ? (string) (function_exists('wp_parse_url')
                        ? wp_parse_url((string) $normalized['LCFA_SITE_URL'], PHP_URL_HOST)
                        : parse_url((string) $normalized['LCFA_SITE_URL'], PHP_URL_HOST))
                    : '';
                $normalized['LCFA_PROJECT_LABEL'] = $site_host !== ''
                    ? $site_host
                    : ($workspace_root !== '' ? basename($workspace_root) : ucfirst($client) . ' project');
            }
        }

        if ($mode === 'remote') {
            unset($normalized['LCFA_WP_ROOT'], $normalized['LCFA_AGENT_WORKSPACE_ROOT']);
            if ($client === 'opencode') {
                $normalized['LCFA_TOOL_PROFILE'] = 'compact';
            }

            $framework = sanitize_key((string) ($common['framework'] ?? ($normalized['LCFA_FRAMEWORK'] ?? '')));
            if (in_array($framework, ['picowind', 'picostrap'], true)) {
                $normalized['LCFA_FRAMEWORK'] = $framework;
            } else {
                unset($normalized['LCFA_FRAMEWORK']);
            }
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

    private function normalize_command(array $command, string $wordpress_root, string $mode): array {
        if ($command === [] || $mode !== 'local' || $wordpress_root === '') {
            return $command;
        }

        $wordpress_root = untrailingslashit($wordpress_root);
        $relative_script = 'wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js';

        foreach ($command as $index => $token) {
            $normalized = ltrim(wp_normalize_path((string) $token), './');

            if ($normalized !== $relative_script) {
                continue;
            }

            $command[$index] = $wordpress_root . '/' . $relative_script;
        }

        return $command;
    }

    private function build_workspace_files(string $client, string $mode, string $workspace_root, string $claude_connection_target, array $command, array $environment, string $server_name): array {
        if ($mode !== 'local' || $workspace_root === '') {
            return [];
        }

        switch ($client) {
            case 'opencode':
                return [[
                    'path'    => $workspace_root . '/opencode.json',
                    'type'    => 'json',
                    'client'  => 'opencode',
                    'server_name' => $server_name,
                    'label'   => __('OpenCode config', 'livecanvas-forge-ai'),
                    'content' => $this->build_opencode_config($command, $environment, $server_name),
                ]];
            case 'cursor':
                return [[
                    'path'    => $workspace_root . '/.cursor/mcp.json',
                    'type'    => 'json',
                    'client'  => 'cursor',
                    'server_name' => 'livecanvas-forge',
                    'label'   => __('Cursor MCP config', 'livecanvas-forge-ai'),
                    'content' => $this->build_cursor_config($command, $environment),
                ]];
            case 'codex':
                return [[
                    'path'    => $workspace_root . '/.codex/config.toml',
                    'type'    => 'toml',
                    'client'  => 'codex',
                    'server_name' => $server_name,
                    'label'   => __('Codex project MCP config', 'livecanvas-forge-ai'),
                    'content' => $this->build_codex_config_snippet($command, $environment, $server_name),
                ]];
            case 'claude':
                if ($claude_connection_target === 'desktop_app') {
                    return [[
                        'path'    => $workspace_root . '/livecanvas-forge.claude-desktop.json',
                        'type'    => 'json',
                        'client'  => 'claude-desktop',
                        'server_name' => 'livecanvas-forge',
                        'label'   => __('Claude Desktop config', 'livecanvas-forge-ai'),
                        'content' => $this->build_claude_desktop_config($command, $environment),
                    ]];
                }

                return [[
                    'path'    => $workspace_root . '/.mcp.json',
                    'type'    => 'json',
                    'client'  => 'claude',
                    'server_name' => 'livecanvas-forge',
                    'label'   => __('Claude Code project MCP config', 'livecanvas-forge-ai'),
                    'content' => $this->build_claude_project_config($command, $environment),
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

    private function build_install_files(string $client, string $mode, string $claude_connection_target, string $claude_desktop_config_path, array $workspace_files): array {
        if (
            $client !== 'claude'
            || $mode !== 'local'
            || $claude_connection_target !== 'desktop_app'
            || $claude_desktop_config_path === ''
            || !isset($workspace_files[0])
            || !is_array($workspace_files[0])
        ) {
            return $workspace_files;
        }

        $artifact = $workspace_files[0];
        $artifact['path'] = $claude_desktop_config_path;
        $artifact['label'] = __('Claude Desktop app config', 'livecanvas-forge-ai');

        return [$artifact];
    }

    private function build_download_files(string $client, string $mode, string $claude_connection_target, array $command, array $environment, string $server_name, string $mcp_url = '', string $oauth_resource = ''): array {
        switch ($client) {
            case 'opencode':
                return [[
                    'name'    => 'opencode.json',
                    'mime'    => 'application/json',
                    'content' => $this->build_opencode_config($command, $environment, $server_name),
                ]];
            case 'cursor':
                return [[
                    'name'    => 'mcp.json',
                    'mime'    => 'application/json',
                    'content' => $this->build_cursor_config($command, $environment),
                ]];
            case 'codex':
                if ($mcp_url !== '') {
                    return [[
                        'name'    => 'livecanvas-ai-bridge.codex.toml',
                        'mime'    => 'text/plain',
                        'content' => $this->build_codex_config_snippet($command, $environment, $server_name, $mcp_url, $oauth_resource),
                    ]];
                }

                return [[
                    'name'    => 'livecanvas-forge.codex.toml',
                    'mime'    => 'text/plain',
                    'content' => $this->build_codex_config_snippet($command, $environment, $server_name),
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
                    'name'    => '.mcp.json',
                    'mime'    => 'application/json',
                    'content' => $this->build_claude_project_config($command, $environment),
                ]];
            default:
                return [[
                    'name'    => 'livecanvas-forge-mcp.txt',
                    'mime'    => 'text/plain',
                    'content' => $this->build_generic_snippet($command, $environment),
                ]];
        }
    }

    private function build_opencode_config(array $command, array $environment, string $server_name = 'livecanvas-forge'): string {
        return (string) wp_json_encode([
            '$schema' => 'https://opencode.ai/config.json',
            'mcp'     => [
                $server_name => $this->build_opencode_server_config($command, $environment),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function build_opencode_server_config(array $command, array $environment): array {
        return [
            'type'        => 'local',
            'command'     => $command,
            'enabled'     => true,
            'timeout'     => 60000,
            'environment' => (object) $environment,
        ];
    }

    private function build_cursor_config(array $command, array $environment): string {
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

    private function build_claude_project_config(array $command, array $environment): string {
        return $this->build_claude_desktop_config($command, $environment);
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

    private function build_codex_script(array $command, array $environment, string $server_name): string {
        return "#!/usr/bin/env bash\nset -euo pipefail\n\n"
            . $this->build_codex_register_command($command, $environment, $server_name)
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

    private function build_client_shortcut(string $client, string $mode, string $claude_connection_target, array $command, array $environment, string $server_name, string $mcp_url = '', string $oauth_resource = ''): array {
        switch ($client) {
            case 'codex':
                return [
                    'title'   => __('Codex shortcut', 'livecanvas-forge-ai'),
                    'command' => $this->build_codex_register_command($command, $environment, $server_name, $mcp_url, $oauth_resource),
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
            case 'opencode':
                return [
                    'title'   => __('OpenCode setup command', 'livecanvas-forge-ai'),
                    'command' => $this->build_opencode_register_command($command, $environment, $server_name),
                ];
            default:
                return [
                    'title'   => '',
                    'command' => '',
                ];
        }
    }

    private function build_codex_register_command(array $command, array $environment, string $server_name, string $mcp_url = '', string $oauth_resource = ''): string {
        $lines = [
            'LCFA_CODEX_BIN=""',
            'if command -v codex >/dev/null 2>&1; then',
            '  LCFA_CODEX_BIN="$(command -v codex)"',
            'elif [ -x "/Applications/Codex.app/Contents/Resources/codex" ]; then',
            '  LCFA_CODEX_BIN="/Applications/Codex.app/Contents/Resources/codex"',
            'fi',
            '',
            'if [ -n "$LCFA_CODEX_BIN" ]; then',
            '  "$LCFA_CODEX_BIN" mcp remove ' . $server_name . ' >/dev/null 2>&1 || true',
        ];

        if ($mcp_url !== '') {
            $lines[] = '  "$LCFA_CODEX_BIN" mcp add ' . $server_name
                . ' --url ' . $this->quote_shell_value($mcp_url)
                . ' --oauth-resource ' . $this->quote_shell_value($oauth_resource !== '' ? $oauth_resource : $mcp_url);
            $lines[] = '  echo "Codex MCP server ' . $server_name . ' registered for Direct OAuth. Run: $LCFA_CODEX_BIN mcp login --scopes mcp ' . $server_name . '"';
            $lines[] = 'else';
            $lines[] = "  cat <<'EOF'";
            $lines[] = 'Codex CLI not found. Add this MCP server to the project .codex/config.toml, then reopen Codex:';
            $lines[] = '';
            $lines[] = $this->build_codex_config_snippet($command, $environment, $server_name, $mcp_url, $oauth_resource);
            $lines[] = 'EOF';
            $lines[] = '  exit 1';
            $lines[] = 'fi';

            return implode("\n", $lines);
        }

        $lines[] = '  "$LCFA_CODEX_BIN" mcp add ' . $server_name . ' \\';
        foreach ($environment as $key => $value) {
            $lines[] = '    --env ' . $key . '=' . $this->quote_shell_value($value) . ' \\';
        }

        $lines[] = '    -- ' . $this->join_shell_tokens($command);
        $lines[] = $this->uses_secure_ai_bridge_remote($environment, $command)
            ? '  echo "Codex MCP server ' . $server_name . ' updated for secure LiveCanvas AI Bridge pairing. Restart Codex or reload the MCP server, then approve the pairing request in WordPress."'
            : ($this->uses_wordpress_mcp_remote_proxy($environment, $command)
                ? '  echo "Codex MCP server ' . $server_name . ' updated for the legacy remote WordPress MCP Adapter. Restart Codex or reload the MCP server before testing."'
                : '  echo "Codex MCP server ' . $server_name . ' updated. Restart Codex or reload the MCP server before testing."');
        $lines[] = 'else';
        $lines[] = "  cat <<'EOF'";
        $lines[] = 'Codex CLI not found in PATH and the embedded desktop CLI was not found at /Applications/Codex.app/Contents/Resources/codex.';
        $lines[] = 'Add this MCP server to the project .codex/config.toml, then reopen Codex:';
        $lines[] = '';
        $lines[] = $this->build_codex_config_snippet($command, $environment, $server_name);
        $lines[] = 'EOF';
        $lines[] = '  exit 1';
        $lines[] = 'fi';

        return implode("\n", $lines);
    }

    private function build_opencode_register_command(array $command, array $environment, string $server_name): string {
        $server_json = (string) wp_json_encode($this->build_opencode_server_config($command, $environment), JSON_UNESCAPED_SLASHES);
        $server_payload = base64_encode($server_json);
        $lines = [
            'LCFA_OPENCODE_CONFIG="${OPENCODE_CONFIG:-$PWD/opencode.json}"',
            'LCFA_OPENCODE_SERVER=' . $this->quote_shell_value($server_name),
            'LCFA_OPENCODE_SERVER_B64=' . $this->quote_shell_value($server_payload),
            'node - "$LCFA_OPENCODE_CONFIG" "$LCFA_OPENCODE_SERVER" "$LCFA_OPENCODE_SERVER_B64" <<\'NODE\'',
            "const fs = require('fs');",
            "const path = require('path');",
            'const [configPath, serverName, payload] = process.argv.slice(2);',
            'let config = {};',
            'if (fs.existsSync(configPath)) {',
            "  const raw = fs.readFileSync(configPath, 'utf8').trim();",
            '  if (raw !== "") {',
            '    try {',
            '      config = JSON.parse(raw);',
            '    } catch (error) {',
            "      console.error('Cannot safely update ' + configPath + ': the file is not valid JSON. Use Download opencode.json and merge it manually.');",
            '      process.exit(1);',
            '    }',
            '  }',
            "  fs.copyFileSync(configPath, configPath + '.lcfa-backup');",
            '}',
            "const server = JSON.parse(Buffer.from(payload, 'base64').toString('utf8'));",
            'config[\'$schema\'] = config[\'$schema\'] || \'https://opencode.ai/config.json\';',
            "config.mcp = config.mcp && typeof config.mcp === 'object' ? config.mcp : {};",
            "if (config.mcp.servers && typeof config.mcp.servers === 'object') {",
            '  config.mcp.servers[serverName] = server;',
            '} else {',
            '  config.mcp[serverName] = server;',
            '}',
            'fs.mkdirSync(path.dirname(configPath), { recursive: true });',
            "const tempPath = configPath + '.lcfa-tmp';",
            "fs.writeFileSync(tempPath, JSON.stringify(config, null, 2) + '\\n', { mode: 0o600 });",
            'fs.renameSync(tempPath, configPath);',
            "console.log('OpenCode project config updated: ' + configPath);",
            'NODE',
            "cat <<'EOF'",
            '',
            'Setup complete. The website has not been changed.',
            '',
            'Next steps:',
            '1. Close and reopen OpenCode so it loads the connection for this project.',
            '2. In OpenCode, send: Call get_connection_handoff with {"limit":5}.',
            '3. If OpenCode shows a pending pairing request, open its WordPress approval link and approve it.',
            '4. Return to AI Bridge in WordPress and run the smoke test.',
            'EOF',
        ];

        return implode("\n", $lines);
    }

    private function build_codex_config_snippet(array $command, array $environment, string $server_name = 'livecanvas-forge', string $mcp_url = '', string $oauth_resource = ''): string {
        if ($mcp_url !== '') {
            return implode("\n", [
                '[mcp_servers.' . $server_name . ']',
                'url = ' . $this->quote_toml_string($mcp_url),
                'oauth_resource = ' . $this->quote_toml_string($oauth_resource !== '' ? $oauth_resource : $mcp_url),
                'default_tools_approval_mode = "writes"',
            ]);
        }

        $command_bin = $command[0] ?? 'node';
        $args = array_slice($command, 1);
        $lines = [
            '[mcp_servers.' . $server_name . ']',
            'command = ' . $this->quote_toml_string($command_bin),
            'args = [' . implode(', ', array_map([$this, 'quote_toml_string'], $args)) . ']',
            'startup_timeout_sec = 60',
            'default_tools_approval_mode = "writes"',
            '',
            '[mcp_servers.' . $server_name . '.env]',
        ];

        foreach ($environment as $key => $value) {
            $lines[] = $key . ' = ' . $this->quote_toml_string($value);
        }

        return implode("\n", $lines);
    }

    private function build_claude_register_command(array $command, array $environment): string {
        $lines = [
            'claude mcp add --scope project --transport stdio livecanvas-forge \\',
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

    private function build_smoke_test_command(array $environment, array $command, string $server_name = 'livecanvas-forge', string $mcp_url = '', string $client = 'codex'): string {
        if ($mcp_url !== '') {
            return "codex mcp get " . $server_name
                . " || /Applications/Codex.app/Contents/Resources/codex mcp get " . $server_name
                . "\n# Authenticate once with: codex mcp login --scopes mcp " . $server_name
                . "\n# Then reopen the project and call livecanvas-forge-ai/get-connection-handoff.";
        }

        if ($this->uses_secure_ai_bridge_remote($environment, $command)) {
            if ($client === 'codex') {
                return "codex mcp get " . $server_name . " || /Applications/Codex.app/Contents/Resources/codex mcp get " . $server_name . "\n# Then reopen Codex, approve the pending AI Bridge session in WordPress, and ask Codex to call get_connection_handoff.";
            }

            $labels = [
                'opencode' => 'OpenCode',
                'claude'   => 'Claude',
                'cursor'   => 'Cursor',
                'generic'  => 'the coding agent',
            ];
            $label = $labels[$client] ?? $labels['generic'];

            return '# Restart or reload ' . $label . ', approve the pending AI Bridge session in WordPress, then call get_connection_handoff.';
        }

        if ($this->uses_wordpress_mcp_remote_proxy($environment, $command)) {
            return "codex mcp get " . $server_name . " || /Applications/Codex.app/Contents/Resources/codex mcp get " . $server_name . "\n# Then reopen Codex and ask it to call livecanvas-forge-ai/get-snapshot.";
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

    private function build_connection_handoff_tool(array $environment, array $command, string $mcp_url = ''): string {
        return $mcp_url !== '' || $this->uses_wordpress_mcp_remote_proxy($environment, $command)
            ? 'livecanvas-forge-ai/get-connection-handoff'
            : 'get_connection_handoff';
    }

    private function build_handoff_package_tool(array $environment, array $command, string $mcp_url = ''): string {
        return $mcp_url !== '' || $this->uses_wordpress_mcp_remote_proxy($environment, $command)
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

    private function uses_secure_ai_bridge_remote(array $environment, array $command): bool {
        $command_string = implode(' ', $command);

        return !empty($environment['LCFA_SITE_URL'])
            && strpos($command_string, '@livecanvas/ai-bridge-mcp') !== false;
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
