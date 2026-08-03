<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-legacy-rest-filter/');

final class WP_Error {
}

final class WP_REST_Request {
    private string $route;

    public function __construct(string $route) {
        $this->route = $route;
    }

    public function get_route(): string {
        return $this->route;
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-oauth-server.php';

function oauth_legacy_filter_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$identity = new ReflectionProperty(LCFA_OAuth_Server::class, 'current_identity');
$identity->setValue(null, [
    'auth_method' => 'oauth_direct',
    'user_id' => 1,
]);

$server = new LCFA_OAuth_Server();
$request = new WP_REST_Request('/livecanvas-forge-ai/mcp');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer diagnostic.jwt.value';
$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer diagnostic.redirect.value';

$server->mask_oauth_bearer_for_legacy_rest_filters(null, null, $request);
oauth_legacy_filter_assert(!isset($_SERVER['HTTP_AUTHORIZATION']), 'Primary bearer should be masked from legacy REST filters');
oauth_legacy_filter_assert(!isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']), 'Redirect bearer should be masked from legacy REST filters');

$server->restore_oauth_bearer_after_legacy_rest_filters(null, null, $request);
oauth_legacy_filter_assert(
    ($_SERVER['HTTP_AUTHORIZATION'] ?? '') === 'Bearer diagnostic.jwt.value',
    'Primary bearer should be restored before the MCP callback'
);
oauth_legacy_filter_assert(
    ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') === 'Bearer diagnostic.redirect.value',
    'Redirect bearer should be restored before the MCP callback'
);

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer unrelated.jwt.value';
$server->mask_oauth_bearer_for_legacy_rest_filters(null, null, new WP_REST_Request('/wp/v2/pages'));
oauth_legacy_filter_assert(
    ($_SERVER['HTTP_AUTHORIZATION'] ?? '') === 'Bearer unrelated.jwt.value',
    'Non-MCP routes must keep their authorization header'
);

echo "PASS\n";
