<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/forge-screen/');
$fixture = sys_get_temp_dir() . '/forge-screen-' . bin2hex(random_bytes(8)) . '/';
define('LCFA_DIR', $fixture);
define('LCFA_URL', 'https://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_MCP_PACKAGE_VERSION', '0.2.0-beta.6');
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_attr($text) { return esc_html($text); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function esc_url($url) { return esc_attr($url); }
function selected($value, $expected, $echo = false) { return $value === $expected ? ' selected="selected"' : ''; }
function get_current_user_id() { return 7; }
function get_user_meta($id, $key, $single) { return ''; }
function home_url($path) { return 'https://example.test' . $path; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function wp_json_encode($value) { return json_encode($value); }
require dirname(__DIR__, 2) . '/includes/class-lcfa-connection-attempt.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-connection-screen.php';
function screen_expect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$attempt = ['client' => 'cursor', 'id' => str_repeat('a', 32), 'site_fingerprint' => 'site1'];
screen_expect(LCFA_Connection_Screen::instructions($attempt) === [], 'Missing archives must not produce broken commands.');
mkdir($fixture . 'assets/runtime', 0700, true);
$archive = $fixture . 'assets/runtime/livecanvas-ai-bridge-mcp-' . LCFA_MCP_PACKAGE_VERSION . '.tgz';
file_put_contents($archive, 'test fixture');
try {
    foreach (['codex', 'opencode', 'cursor', 'claude-code', 'claude-desktop'] as $client) {
        $attempt['client'] = $client;
        $instructions = LCFA_Connection_Screen::instructions($attempt);
        screen_expect(strpos($instructions['command'], 'livecanvas-forge-connect') !== false, 'Every primary client uses the same installer.');
        preg_match("/--descriptor '([^']+)'/", $instructions['command'], $matches);
        $descriptor = json_decode(base64_decode(strtr($matches[1], '-_', '+/')), true);
        screen_expect($descriptor['client'] === $client && $descriptor['attempt'] === $attempt['id'], 'Descriptor must bind the exact client and attempt.');
        screen_expect($descriptor['runtime_package'] === LCFA_URL . 'assets/runtime/livecanvas-ai-bridge-mcp-' . LCFA_MCP_PACKAGE_VERSION . '.tgz', 'Instructions use the bundled release archive.');
        ob_start(); LCFA_Connection_Screen::render(['preferred_client' => $client]); $html = ob_get_clean();
        screen_expect(strpos($html, 'value="' . $client . '" selected=') !== false, 'The selected client must survive rendering.');
        screen_expect(substr_count($html, 'data-connect-copy') === 1, 'One copy action for every client.');
        screen_expect(strpos($html, 'Authorize Full Access') !== false && strpos($html, 'data-connect-approve hidden') !== false, 'Consent is explicit and hidden until an actual request exists.');
        screen_expect(strpos($html, 'connection_ui=manual') !== false, 'The previous setup remains available for recovery.');
    }
    echo "PASS: unified screen, five client descriptors, explicit consent and versioned bundled installer\n";
} finally { unlink($archive); rmdir($fixture . 'assets/runtime'); rmdir($fixture . 'assets'); rmdir($fixture); }
