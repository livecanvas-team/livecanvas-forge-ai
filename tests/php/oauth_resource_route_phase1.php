<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-resource-route/');

final class WP_Error {
}

function home_url(string $path = ''): string {
    return 'https://example.com/' . ltrim($path, '/');
}

function rest_url(string $path = ''): string {
    throw new RuntimeException('rest_url() must not run before $wp_rewrite is initialized');
}

function rest_get_url_prefix(): string {
    return 'wp-json';
}

function get_option(string $name, $default = false) {
    if ($name === 'permalink_structure') {
        return $GLOBALS['lcfa_plain_rest'] ? '' : '/%postname%/';
    }

    return $default;
}

function add_query_arg(string $key, string $value, string $url): string {
    return rtrim($url, '/') . '/?' . rawurlencode($key) . '=' . rawurlencode($value);
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-oauth-storage.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-settings.php';

$GLOBALS['wp_rewrite'] = null;

function oauth_resource_route_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$GLOBALS['lcfa_plain_rest'] = false;
oauth_resource_route_assert(
    LCFA_OAuth_Storage::request_targets_resource('https://example.com/wp-json/livecanvas-forge-ai/mcp'),
    'Pretty-permalink MCP resource should match'
);
oauth_resource_route_assert(
    !LCFA_OAuth_Storage::request_targets_resource('https://example.com/wp-json/wp/v2/posts'),
    'OAuth bearer must not match a different pretty-permalink REST route'
);
oauth_resource_route_assert(
    !LCFA_OAuth_Storage::request_targets_resource('https://other.example/wp-json/livecanvas-forge-ai/mcp'),
    'OAuth bearer must not match the same path on a different host'
);

$GLOBALS['lcfa_plain_rest'] = true;
oauth_resource_route_assert(
    LCFA_OAuth_Storage::request_targets_resource('https://example.com/index.php?rest_route=%2Flivecanvas-forge-ai%2Fmcp'),
    'Plain-permalink MCP resource should match'
);
oauth_resource_route_assert(
    !LCFA_OAuth_Storage::request_targets_resource('https://example.com/index.php?rest_route=%2Fwp%2Fv2%2Fposts'),
    'OAuth bearer must not escape to a different plain-permalink REST route'
);
oauth_resource_route_assert(
    !LCFA_OAuth_Storage::request_targets_resource('https://example.com/index.php'),
    'Plain-permalink requests without rest_route must not match the MCP resource'
);

$GLOBALS['lcfa_plain_rest'] = false;
$GLOBALS['wp_rewrite'] = null;
$early_fingerprint = LCFA_Settings::get_site_fingerprint();
$GLOBALS['wp_rewrite'] = (object) [];
$ready_fingerprint = LCFA_Settings::get_site_fingerprint();
oauth_resource_route_assert(
    preg_match('/^[a-f0-9]{16}$/', $early_fingerprint) === 1,
    'Site fingerprint should be generated during early bearer authentication'
);
oauth_resource_route_assert(
    hash_equals($early_fingerprint, $ready_fingerprint),
    'Site fingerprint must remain stable before and after rewrite initialization'
);

echo "PASS\n";
