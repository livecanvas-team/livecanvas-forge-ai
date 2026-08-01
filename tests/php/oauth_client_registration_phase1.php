<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-registration/');

final class WP_Error {
    private string $code;
    private string $message;
    private $data;

    public function __construct(string $code, string $message, $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }
}

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function sanitize_text_field($value): string {
    return trim(strip_tags((string) $value));
}

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

function oauth_registration_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function oauth_registration_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-oauth-storage.php';

$registration = LCFA_OAuth_Client_Registration::normalize([
    'client_name' => 'Codex Desktop',
    'redirect_uris' => [
        'https://client.example/oauth/callback',
        'http://127.0.0.1:1455/callback',
    ],
    'grant_types' => ['authorization_code', 'refresh_token'],
    'response_types' => ['code'],
    'token_endpoint_auth_method' => 'none',
]);

oauth_registration_assert_true(is_array($registration), 'public OAuth registration should accept HTTPS and loopback callbacks');
oauth_registration_assert_same('none', $registration['token_endpoint_auth_method'] ?? '', 'registered clients must remain public clients');
oauth_registration_assert_same(
    ['authorization_code', 'refresh_token'],
    $registration['grant_types'] ?? [],
    'registration should preserve only supported grants'
);

$insecure = LCFA_OAuth_Client_Registration::normalize([
    'redirect_uris' => ['http://client.example/callback'],
]);
oauth_registration_assert_true(is_wp_error($insecure), 'non-loopback HTTP redirects must be rejected');
oauth_registration_assert_same('lcfa_oauth_insecure_redirect_uri', $insecure->get_error_code(), 'insecure redirect should use a stable error code');

$private = LCFA_OAuth_Client_Registration::normalize([
    'redirect_uris' => ['https://codex.internal/callback'],
]);
oauth_registration_assert_true(is_wp_error($private), 'private-network redirect hosts must be rejected');
oauth_registration_assert_same('lcfa_oauth_private_redirect_uri', $private->get_error_code(), 'private redirect should use a stable error code');

$confidential = LCFA_OAuth_Client_Registration::normalize([
    'redirect_uris' => ['https://client.example/callback'],
    'token_endpoint_auth_method' => 'client_secret_post',
]);
oauth_registration_assert_true(is_wp_error($confidential), 'DCR must not accept a client secret authentication method');

$unsupported_grant = LCFA_OAuth_Client_Registration::normalize([
    'redirect_uris' => ['https://client.example/callback'],
    'grant_types' => ['client_credentials'],
]);
oauth_registration_assert_true(is_wp_error($unsupported_grant), 'client credentials grant must not be accepted');

echo "PASS\n";
