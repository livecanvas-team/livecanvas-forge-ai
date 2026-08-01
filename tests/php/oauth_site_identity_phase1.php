<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-site-identity/');

function oauth_site_identity_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function oauth_site_identity_assert_false(bool $condition, string $message): void {
    oauth_site_identity_assert_true(!$condition, $message);
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-settings.php';

oauth_site_identity_assert_true(
    LCFA_Settings::site_urls_match('https://example.com', 'https://EXAMPLE.com:443/'),
    'Equivalent public site URLs should resolve to the same identity'
);
oauth_site_identity_assert_true(
    LCFA_Settings::site_urls_match('https://example.com/network/site-a', 'https://example.com/network/site-a/'),
    'Equivalent multisite paths should resolve to the same identity'
);
oauth_site_identity_assert_false(
    LCFA_Settings::site_urls_match('https://example.com/network/site-a/', 'https://example.com/network/site-b/'),
    'Different multisite paths on the same host must not be treated as one WordPress site'
);
oauth_site_identity_assert_false(
    LCFA_Settings::site_urls_match('http://example.com/', 'https://example.com/'),
    'HTTP and HTTPS targets must not be treated as one connection identity'
);

oauth_site_identity_assert_true(
    LCFA_Settings::is_public_https_site_url('https://example.com/'),
    'A public HTTPS URL should support Direct OAuth'
);
oauth_site_identity_assert_false(
    LCFA_Settings::is_public_https_site_url('http://example.com/'),
    'Plain HTTP must not support Direct OAuth'
);
oauth_site_identity_assert_false(
    LCFA_Settings::is_public_https_site_url('https://project.local/'),
    'Local HTTPS sites must use pairing or local runtime instead of Direct OAuth'
);
oauth_site_identity_assert_false(
    LCFA_Settings::is_public_https_site_url('https://127.0.0.1:8443/'),
    'Loopback HTTPS sites must not be classified as public'
);
oauth_site_identity_assert_false(
    LCFA_Settings::is_public_https_site_url('https://192.168.1.20/'),
    'Private-network HTTPS sites must not be classified as public'
);

echo "PASS\n";
