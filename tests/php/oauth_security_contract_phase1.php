<?php

declare(strict_types=1);

function oauth_contract_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function oauth_contract_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$server = (string) file_get_contents($root . '/includes/class-lcfa-oauth-server.php');
$storage = (string) file_get_contents($root . '/includes/class-lcfa-oauth-storage.php');
$entities = (string) file_get_contents($root . '/includes/class-lcfa-oauth-entities.php');
$repositories = (string) file_get_contents($root . '/includes/class-lcfa-oauth-repositories.php');
$admin = (string) file_get_contents($root . '/includes/class-lcfa-admin.php');

oauth_contract_assert_contains("method !== 'S256'", $server, 'authorization must reject any PKCE method other than S256');
oauth_contract_assert_contains('looks_like_oauth_access_token', $server, 'OAuth middleware must distinguish JWT access tokens from legacy opaque pairing tokens');
oauth_contract_assert_contains('is_current_request_mcp_route', $server, 'OAuth bearer tokens must be scoped to the MCP route');
oauth_contract_assert_contains('permittedFor($this->resource)', $entities, 'access tokens must encode their MCP resource as audience');
oauth_contract_assert_contains('LCFA_OAuth_Storage::resource_url()', $repositories, 'access token creation must use the exact MCP resource URL');
oauth_contract_assert_contains("withClaim('site_fingerprint'", $entities, 'access tokens must carry the site fingerprint');
oauth_contract_assert_contains('hash_hmac', $storage, 'persisted OAuth token identifiers must be hashed');
oauth_contract_assert_contains('revoke_client', $storage, 'connected OAuth clients must be revocable');
oauth_contract_assert_contains('AES-256-GCM', strtoupper($storage), 'OAuth signing secrets must be encrypted at rest');
oauth_contract_assert_contains('lcfa_oauth_key_storage_failed', $storage, 'OAuth setup must fail closed when encrypted key storage is unavailable');
oauth_contract_assert_contains('lcfa_oauth_client_id_generation_failed', $storage, 'OAuth registration must fail cleanly when secure entropy is unavailable');
oauth_contract_assert_contains('Connected Codex apps', $admin, 'Connections must expose connected OAuth apps');
oauth_contract_assert_contains('Revoke OAuth grant', $admin, 'Connections must expose OAuth revocation');
oauth_contract_assert_contains('old global `livecanvas-forge` server', $admin, 'Direct onboarding must warn about duplicate legacy LiveCanvas servers');
oauth_contract_assert_not_contains('WP_API_PASSWORD', $server, 'OAuth server must not depend on a WordPress Application Password');

echo "PASS\n";
