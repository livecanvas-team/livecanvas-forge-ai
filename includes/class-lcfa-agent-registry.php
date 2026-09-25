<?php

defined('ABSPATH') || exit;

/** Shared with the distributed Node client; unknown agents must never become Codex. */
final class LCFA_Agent_Registry {
    public static function all(bool $include_legacy = false): array {
        static $catalog;
        if ($catalog === null) {
            $decoded = json_decode((string) file_get_contents(dirname(__DIR__) . '/mcp/src/agent-catalog.json'), true);
            $catalog = is_array($decoded) ? $decoded : [];
        }
        return $include_legacy ? $catalog : array_filter($catalog, static function (array $agent): bool {
            return empty($agent['hidden']);
        });
    }

    public static function normalize(string $client, string $fallback = 'generic'): string {
        $client = strtolower(trim($client));
        $aliases = ['other' => 'generic', 'codex-cli' => 'codex', 'codex-app' => 'codex', 'copilot-cli' => 'github-copilot', 'roo' => 'roo-code', 'kilo' => 'kilo-code'];
        $client = $aliases[$client] ?? $client;
        return isset(self::all(true)[$client]) ? $client : $fallback;
    }

    public static function get(string $client): array {
        return self::all(true)[self::normalize($client)] ?? [];
    }

    public static function label(string $client): string {
        return (string) (self::get($client)['label'] ?? 'Coding agent');
    }

    /** Audit metadata only: a client name must never authorize a request. */
    public static function provenance_client(string $client): string {
        return $client === 'forge' ? 'forge' : self::normalize($client);
    }

    public static function mcp_processors(): array {
        return array_map(static function (string $client): string {
            return $client . '_mcp';
        }, array_keys(self::all(true)));
    }

    public static function from_connections(array $connections): string {
        $client = self::normalize((string) ($connections['preferred_client'] ?? ''), 'codex');
        if ($client === 'claude') {
            return ($connections['claude_connection_target'] ?? '') === 'desktop_app' ? 'claude-desktop' : 'claude-code';
        }
        return $client;
    }

    public static function full_access_scopes(): array {
        return ['read', 'preview', 'write', 'media', 'theme_files', 'debug', 'cache', 'seo'];
    }
}
