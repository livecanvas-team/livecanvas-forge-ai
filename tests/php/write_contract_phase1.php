<?php
// Isolated contract tests. Real WordPress/KSES coverage lives in tests/integration.
define('ABSPATH', sys_get_temp_dir() . '/lcfa-contract-' . bin2hex(random_bytes(5)));
define('ARRAY_A', 'ARRAY_A');
mkdir(ABSPATH); mkdir(ABSPATH . '/child'); mkdir(ABSPATH . '/parent');
$GLOBALS['framework'] = 'picowind';
$GLOBALS['child'] = true;
$GLOBALS['posts'] = [7 => (object) ['ID' => 7, 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => '<p>Original</p>']];
$GLOBALS['meta'] = [];
class LCFA_Settings { static function get_site_fingerprint() { return 'test-site'; } }
class LCFA_Environment {
    function detect_framework_family() { return $GLOBALS['framework']; }
    function is_windpress_active() { return true; }
    function is_livecanvas_active() { return true; }
}
class LCFA_Render_Target { static function resolve($selector, $roots, $revision) { return ['verified' => true, 'kind' => 'livecanvas_page']; } }
class ContractTheme {
    function get_stylesheet() { return $GLOBALS['child'] ? 'child' : 'parent'; }
    function get_template() { return 'parent'; }
    function get($key) { return $key === 'Version' ? '1.0' : 'Test theme'; }
}
function wp_get_theme($name = '') { return new ContractTheme(); }
function get_stylesheet_directory() { return ABSPATH . '/' . ($GLOBALS['child'] ? 'child' : 'parent'); }
function get_template_directory() { return ABSPATH . '/parent'; }
function get_stylesheet() { return $GLOBALS['child'] ? 'child' : 'parent'; }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_current_blog_id() { return 1; }
function get_option($key, $default = false) { return $default; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key = '', $single = false) { return $key === '' ? $GLOBALS['meta'] : ($GLOBALS['meta'][$key] ?? ''); }
function wp_json_encode($value) { return json_encode($value); }
function wp_salt($scheme) { return 'unit-test-only'; }
function wp_parse_url($url) { return parse_url($url); }
function url_to_postid($url) { return str_contains($url, '/example') ? 7 : 0; }
$wpdb = new class {
    public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta';
    function get_results($sql, $format) { return str_contains($sql, 'postmeta') ? $GLOBALS['meta'] : array_values($GLOBALS['posts']); }
};
require dirname(__DIR__, 2) . '/includes/class-lcfa-write-contract.php';
function expect_contract($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function proof_for($args) { $result = LCFA_Write_Contract::prepare($args); expect_contract($result['ok'], json_encode($result)); return $result['write_context']; }
try {
    expect_contract(LCFA_Write_Contract::validate([])['code'] === 'context_required', 'Missing context must fail.');
    $proof = proof_for(['target_id' => 7]);
    $payload = ['target_id' => 7, 'write_context' => $proof, 'body_html' => '<section class="grid gap-6"><p>A row of prose mentioning btn.</p></section>'];
    expect_contract(LCFA_Write_Contract::validate($payload, 'update_page')['ok'], 'Tailwind fragment should pass without textual false positives.');
    foreach (['<style>p{color:red}</style>', '<p style="color:red">x</p>', '<script>x()</script>', '<main>x</main>', '<p class="btn btn-primary">x</p>', '<div class="prose">x</div>', '<div class="row">x</div>', '<a class=btn>x</a>', '<a class="b&#116;n">x</a>', '<button onclick="run()">x</button>'] as $html) {
        expect_contract(!LCFA_Write_Contract::validate(array_merge($payload, ['body_html' => $html]), 'update_page')['ok'], 'Unsafe/incompatible markup must fail: ' . $html);
    }
    expect_contract(LCFA_Write_Contract::validate(array_merge($payload, ['framework' => 'picostrap']), 'update_page')['code'] === 'framework_mismatch', 'Caller must not override framework.');
    expect_contract(LCFA_Write_Contract::validate(array_merge($payload, ['target_id' => 8]), 'update_page')['code'] === 'target_mismatch', 'Target mismatch must fail.');
    $GLOBALS['posts'][7]->post_content = 'Changed elsewhere';
    expect_contract(LCFA_Write_Contract::validate($payload, 'update_page')['code'] === 'stale_context', 'Content change must stale the proof.');
    $GLOBALS['framework'] = 'picostrap';
    $payload['write_context'] = proof_for(['target_id' => 7]);
    $payload['body_html'] = '<section class="container"><div class="row"><div class="col-md-6">Bootstrap</div></div></section>';
    expect_contract(LCFA_Write_Contract::validate($payload, 'update_page')['ok'], 'Picostrap Bootstrap should pass.');
    expect_contract(!LCFA_Write_Contract::validate(array_merge($payload, ['body_html' => '<div class="grid-cols-3">x</div>']), 'update_page')['ok'], 'Picostrap must reject Tailwind layout.');
    $file = ['path' => 'views/single.twig', 'write_context' => proof_for(['target_type' => 'theme_file', 'path' => 'views/single.twig']), 'content' => '<article>{{ post.content }}</article>'];
    expect_contract(LCFA_Write_Contract::validate($file, 'write_theme_file')['code'] === 'shared_impact_required', 'Shared impact must be acknowledged.');
    $file['acknowledge_shared'] = true;
    expect_contract(LCFA_Write_Contract::validate($file, 'write_theme_file')['ok'], 'Child template should pass.');
    expect_contract(LCFA_Write_Contract::validate(array_merge($file, ['root_scope' => 'template']), 'write_theme_file')['code'] === 'parent_theme_read_only', 'Parent must stay read-only.');
    $GLOBALS['framework'] = 'unknown';
    $payload['write_context'] = proof_for(['target_id' => 7]);
    $payload['body_html'] = '<section class="native-theme-component">Native</section>';
    expect_contract(LCFA_Write_Contract::validate($payload, 'update_page')['ok'], 'Barebone/theme-native HTML should pass.');
    $payload['write_context']['selector']['target_id'] = 8;
    expect_contract(LCFA_Write_Contract::validate($payload, 'update_page')['code'] === 'context_expired', 'Tampered proof must fail.');
    expect_contract(!LCFA_Write_Contract::prepare(['target_id' => 7, 'target_url' => 'https://elsewhere.test/example'])['ok'], 'Foreign site URL must fail.');
    $GLOBALS['meta']['_lcfa_no_theme_edits'] = '1';
    $payload['write_context'] = proof_for(['target_id' => 7]);
    expect_contract(LCFA_Write_Contract::validate(array_merge($payload, ['no_theme_edits' => false]), 'update_page')['code'] === 'scope_violation', 'Caller flags must not relax persisted scope.');
    echo "PASS: signed context, staleness, scope, child roots, Picowind/Picostrap/barebone and unavailable framework plugins\n";
} finally { rmdir(ABSPATH . '/child'); rmdir(ABSPATH . '/parent'); rmdir(ABSPATH); }
