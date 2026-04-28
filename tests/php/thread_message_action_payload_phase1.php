<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-thread-message-actions/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    $value = strtolower($value);

    return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
}

function sanitize_text_field(string $value): string {
    return trim(strip_tags($value));
}

function wp_unslash($value) {
    return is_string($value) ? stripslashes($value) : $value;
}

function wp_json_encode($value, int $flags = 0): string {
    return json_encode($value, $flags) ?: '';
}

function absint($value): int {
    return abs((int) $value);
}

function esc_url(string $value): string {
    return trim($value);
}

function admin_url(string $path = ''): string {
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg(array $args, string $url): string {
    return $url . '?' . http_build_query($args);
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected `' . var_export($expected, true) . '`, got `' . var_export($actual, true) . '`.');
    }
}

require LCFA_DIR . 'includes/class-lcfa-thread-message-actions.php';

$actions = LCFA_Thread_Message_Actions::sanitize_actions([
    [
        'kind'    => 'apply',
        'label'   => 'Apply shell',
        'payload' => [
            'action'            => 'global_shell_apply',
            'title'             => '<strong>Unsafe title</strong>',
            'header_html'       => '<header><nav>Keep header markup</nav></header>',
            'footer_html_lines' => [
                '<footer>Keep footer markup</footer>',
            ],
            'pages'             => [
                [
                    'title'           => '<em>Home</em>',
                    'body_html_lines' => [
                        '<section><h1>Keep page markup</h1></section>',
                    ],
                ],
            ],
        ],
    ],
]);

$payload = $actions[0]['payload'] ?? [];

lcfa_assert_same('Unsafe title', (string) ($payload['title'] ?? ''), 'non-markup payload fields should still use text sanitization');
lcfa_assert_same('<header><nav>Keep header markup</nav></header>', (string) ($payload['header_html'] ?? ''), 'thread apply payloads should preserve header_html markup');
lcfa_assert_same('<footer>Keep footer markup</footer>', (string) ($payload['footer_html_lines'][0] ?? ''), 'thread apply payloads should preserve footer_html_lines markup');
lcfa_assert_same('Home', (string) ($payload['pages'][0]['title'] ?? ''), 'nested non-markup page fields should still be sanitized');
lcfa_assert_same('<section><h1>Keep page markup</h1></section>', (string) ($payload['pages'][0]['body_html_lines'][0] ?? ''), 'nested page body_html_lines should preserve markup');

echo "PASS\n";
