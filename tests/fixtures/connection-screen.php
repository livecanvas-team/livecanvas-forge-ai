<?php
/** Render the real component with synthetic site data, without loading WordPress. */
declare(strict_types=1);
define('ABSPATH', '/tmp/bridge-render-fixture/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://bridge.test/');
define('LCFA_MCP_PACKAGE_VERSION', '0.2.0-beta.7');
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_attr($text) { return esc_html($text); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function esc_url($text) { return esc_attr($text); }
function selected($value, $expected, $echo = false) { return $value === $expected ? ' selected="selected"' : ''; }
function get_current_user_id() { return 7; }
function get_user_meta($id, $key, $single) { return ''; }
function home_url($path) { return 'http://bridge.test' . $path; }
function admin_url($path) { return 'http://bridge.test/wp-admin/' . $path; }
function rest_url($path) { return 'http://bridge.test/wp-json/' . $path; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function wp_json_encode($value) { return json_encode($value); }
require LCFA_DIR . 'includes/class-lcfa-connection-attempt.php';
require LCFA_DIR . 'includes/class-lcfa-connection-screen.php';
ob_start(); LCFA_Connection_Screen::render(['preferred_client' => 'codex']); $html = ob_get_clean();
$instructions = [];
foreach (['codex', 'opencode', 'cursor', 'claude-code', 'claude-desktop'] as $client) {
    $instructions[$client] = LCFA_Connection_Screen::instructions(['id' => str_repeat('a', 32), 'client' => $client, 'site_fingerprint' => 'fixture-site']);
}
echo json_encode(['html' => $html, 'labels' => LCFA_Connection_Screen::labels(), 'instructions' => $instructions]);
