<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-resource-route/');

final class WP_Error {
}

function home_url(string $path = ''): string {
    return 'https://example.com/' . ltrim($path, '/');
}

function rest_url(string $path = ''): string {
    return $GLOBALS['lcfa_plain_rest']
        ? 'https://example.com/index.php?rest_route=/' . ltrim($path, '/')
        : 'https://example.com/wp-json/' . ltrim($path, '/');
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-oauth-storage.php';

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

echo "PASS\n";
