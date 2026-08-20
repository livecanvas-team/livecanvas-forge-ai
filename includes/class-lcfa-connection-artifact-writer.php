<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Artifact_Writer {
    public function write(array $artifact, string $workspace_root, string $wordpress_root = ''): array {
        $path = (string) ($artifact['path'] ?? '');
        $content = (string) ($artifact['content'] ?? '');
        $client = sanitize_key((string) ($artifact['client'] ?? 'generic'));
        $type = sanitize_key((string) ($artifact['type'] ?? 'text'));
        $server_name = sanitize_key((string) ($artifact['server_name'] ?? 'livecanvas-forge')) ?: 'livecanvas-forge';
        $normalized_path = $this->normalize_absolute_path($path);
        $normalized_root = $this->normalize_absolute_path($workspace_root);
        $normalized_wordpress_root = $this->normalize_absolute_path($wordpress_root);

        if (
            $normalized_path === ''
            || $normalized_root === ''
            || !$this->is_absolute_path($normalized_path)
            || !$this->is_absolute_path($normalized_root)
        ) {
            return $this->failure('The bundle path and configured workspace root must be absolute paths.', 'path_not_absolute');
        }

        $canonical_path = $this->canonicalize_path($normalized_path);
        $canonical_root = $this->canonicalize_path($normalized_root);
        if ($canonical_path === '' || $canonical_root === '' || !$this->is_path_inside($canonical_path, $canonical_root)) {
            return $this->failure('The bundle can only be written inside the configured agent workspace root.', 'outside_workspace');
        }

        $canonical_wordpress_root = $normalized_wordpress_root !== ''
            ? $this->canonicalize_path($normalized_wordpress_root)
            : '';
        $inside_wordpress_root = $canonical_wordpress_root !== ''
            && $this->is_path_inside($canonical_path, $canonical_wordpress_root);
        if ($inside_wordpress_root && $client === 'opencode') {
            return $this->failure('OpenCode project config cannot be written inside the public WordPress root. Open the parent project folder or use the setup command with a safe OPENCODE_CONFIG path.', 'public_opencode_config');
        }

        if ($inside_wordpress_root && preg_match('/LCFA_MCP_TOKEN|application[_ -]?password|WP_API_PASSWORD/i', $content)) {
            return $this->failure('A project config containing credentials cannot be written inside the public WordPress root.', 'public_secret');
        }

        $directory = dirname($normalized_path);
        if (!is_dir($directory) && !$this->make_directory($directory)) {
            return $this->failure('Failed to create the project config directory.', 'mkdir_failed');
        }

        $current = '';
        if (is_file($normalized_path)) {
            $read = file_get_contents($normalized_path);
            if (!is_string($read)) {
                return $this->failure('Failed to read the existing project config.', 'read_failed');
            }
            $current = $read;
        }

        $merged = $content;
        if ($type === 'json') {
            $merge = $this->merge_json($current, $content, $client, $server_name);
            if (empty($merge['ok'])) {
                return $merge;
            }
            $merged = (string) ($merge['content'] ?? '');
        } elseif ($type === 'toml') {
            $merged = $this->merge_toml($current, $content, $server_name);
        }

        $backup_path = '';
        if ($current !== '') {
            $backup_path = $this->build_backup_path($normalized_path);
            if (!copy($normalized_path, $backup_path)) {
                return $this->failure('Failed to create the automatic project config backup.', 'backup_failed');
            }
            @chmod($backup_path, 0600);
        }

        $temp_path = tempnam($directory, '.lcfa-config-');
        if (!is_string($temp_path) || $temp_path === '') {
            return $this->failure('Failed to create a temporary project config file.', 'temp_failed', $backup_path);
        }

        $written = file_put_contents($temp_path, $merged, LOCK_EX);
        if ($written === false || !@chmod($temp_path, 0600) || !@rename($temp_path, $normalized_path)) {
            @unlink($temp_path);
            return $this->failure('Failed to atomically write the project config.', 'write_failed', $backup_path);
        }

        @chmod($normalized_path, 0600);

        return [
            'ok' => true,
            'path' => $normalized_path,
            'backup_path' => $backup_path,
            'merged' => $current !== '',
            'mode' => substr(sprintf('%o', (int) fileperms($normalized_path)), -4),
            'sha256' => hash_file('sha256', $normalized_path),
        ];
    }

    private function merge_json(string $current, string $generated, string $client, string $server_name): array {
        $incoming = json_decode($generated, true);
        if (!is_array($incoming)) {
            return $this->failure('The generated project config is not valid JSON.', 'generated_json_invalid');
        }

        $existing = [];
        if (trim($current) !== '') {
            $existing = json_decode($current, true);
            if (!is_array($existing)) {
                return $this->failure('The existing project config is not valid JSON. Fix it before AI Bridge performs a safe merge.', 'existing_json_invalid');
            }
        }

        if ($client === 'opencode') {
            $incoming_mcp = is_array($incoming['mcp'] ?? null) ? $incoming['mcp'] : [];
            $incoming_server = $incoming_mcp[$server_name] ?? ($incoming_mcp['servers'][$server_name] ?? null);
            if (!is_array($incoming_server)) {
                return $this->failure('The generated OpenCode config does not contain the expected MCP server.', 'missing_server');
            }

            if (empty($existing['$schema']) && !empty($incoming['$schema'])) {
                $existing['$schema'] = $incoming['$schema'];
            }
            $existing['mcp'] = is_array($existing['mcp'] ?? null) ? $existing['mcp'] : [];
            if (is_array($existing['mcp']['servers'] ?? null)) {
                $existing['mcp']['servers'][$server_name] = $incoming_server;
            } else {
                $existing['mcp'][$server_name] = $incoming_server;
            }
        } else {
            $incoming_servers = is_array($incoming['mcpServers'] ?? null) ? $incoming['mcpServers'] : [];
            $incoming_server = $incoming_servers[$server_name] ?? null;
            if (!is_array($incoming_server)) {
                return $this->failure('The generated project config does not contain the expected MCP server.', 'missing_server');
            }

            $existing['mcpServers'] = is_array($existing['mcpServers'] ?? null) ? $existing['mcpServers'] : [];
            $existing['mcpServers'][$server_name] = $incoming_server;
        }

        return [
            'ok' => true,
            'content' => (string) wp_json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        ];
    }

    private function merge_toml(string $current, string $snippet, string $server_name): string {
        $lines = preg_split('/\R/', $current);
        $lines = is_array($lines) ? $lines : [];
        $quoted_server = preg_quote($server_name, '/');
        $section_pattern = '/^\s*\[mcp_servers\.(?:"' . $quoted_server . '"|' . $quoted_server . ')(?:\.env)?\]\s*$/';
        $kept = [];
        $skipping = false;

        foreach ($lines as $line) {
            if (preg_match($section_pattern, (string) $line)) {
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

    private function make_directory(string $directory): bool {
        if (function_exists('wp_mkdir_p')) {
            return (bool) wp_mkdir_p($directory);
        }

        return @mkdir($directory, 0755, true) || is_dir($directory);
    }

    private function build_backup_path(string $path): string {
        $suffix = gmdate('Ymd-His');
        try {
            $suffix .= '-' . bin2hex(random_bytes(3));
        } catch (Throwable $error) {
            $suffix .= '-' . uniqid();
        }

        return $path . '.lcfa-backup-' . $suffix;
    }

    private function normalize_absolute_path(string $path): string {
        $path = trim(wp_normalize_path($path));
        if ($path === '') {
            return '';
        }

        $prefix = str_starts_with($path, '/') ? '/' : '';
        if (preg_match('/^[A-Za-z]:\//', $path, $matches)) {
            $prefix = strtoupper(substr($path, 0, 2)) . '/';
            $path = substr($path, 3);
        } else {
            $path = ltrim($path, '/');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return rtrim($prefix . implode('/', $parts), '/');
    }

    private function is_path_inside(string $path, string $root): bool {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    private function is_absolute_path(string $path): bool {
        return str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:\//', $path);
    }

    private function canonicalize_path(string $path): string {
        $path = $this->normalize_absolute_path($path);
        if ($path === '' || !$this->is_absolute_path($path)) {
            return '';
        }

        $cursor = $path;
        $tail = [];

        while (!file_exists($cursor) && !is_link($cursor)) {
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return '';
            }

            array_unshift($tail, basename($cursor));
            $cursor = $parent;
        }

        $resolved = realpath($cursor);
        if (!is_string($resolved) || $resolved === '') {
            return '';
        }

        $canonical = $this->normalize_absolute_path($resolved);
        foreach ($tail as $part) {
            $canonical = rtrim($canonical, '/') . '/' . $part;
        }

        return $this->normalize_absolute_path($canonical);
    }

    private function failure(string $message, string $code, string $backup_path = ''): array {
        return [
            'ok' => false,
            'code' => $code,
            'message' => function_exists('__') ? __($message, 'livecanvas-forge-ai') : $message,
            'backup_path' => $backup_path,
        ];
    }
}
