<?php
if (PHP_SAPI !== 'cli') exit(1);
/** Run with LCFA_TEST_WP_ROOT set to the disposable test-ai-forge WordPress root. */
$root = getenv('LCFA_TEST_WP_ROOT');
if (!$root) { echo "SKIP: LCFA_TEST_WP_ROOT is not configured\n"; exit; }
$_SERVER['HTTP_HOST'] = 'test-ai-forge.local';
$_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'test-ai-forge.local') throw new RuntimeException('Only the authorized disposable test site is allowed.');
if (!class_exists('LCFA_Write_Contract')) throw new RuntimeException('Deploy the working Bridge version to the test site first.');
function lcfa_check($test, $message) { if (!$test) throw new RuntimeException($message); }
$original_user = get_current_user_id();
$id = 0;
try {
    wp_set_current_user(1);
    kses_remove_filters();
    $html = '<style>.legacy-layout { display:grid; }</style><article data-reference="original"><p>Editorial &amp; content \'exact\'.</p></article>';
    $id = wp_insert_post(wp_slash(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Bridge regression fixture', 'post_content' => $html, 'comment_status' => 'open', 'ping_status' => 'open']), true);
    lcfa_check(!is_wp_error($id), 'Fixture creation failed.');
    $original = get_post_field('post_content', $id, 'raw');
    wp_set_current_user(0); // The local MCP bearer-token execution identity.
    kses_init_filters();
    lcfa_check(!current_user_can('unfiltered_html'), 'Test must run with sanitization enabled.');
    lcfa_check(apply_filters('content_save_pre', wp_slash($original)) !== wp_slash($original), 'The fixture must actually trigger KSES filtering.');
    $context = LCFA_Write_Contract::prepare(['target_id' => $id]);
    lcfa_check($context['ok'], json_encode($context));
    $payload = ['target_id' => $id, 'write_context' => $context['write_context'], 'comment_status' => 'closed', 'ping_status' => 'closed'];
    lcfa_check(LCFA_Write_Contract::validate($payload, 'update_discussion_settings')['ok'], 'Fresh discussion context should pass.');
    $result = LCFA_Write_Contract::update_discussion($payload);
    lcfa_check($result['ok'] && $result['content_preserved'], json_encode($result));
    lcfa_check(get_post_field('post_content', $id, 'raw') === $original, 'Editorial bytes changed under KSES.');
    lcfa_check(get_post_field('comment_status', $id) === 'closed' && get_post_field('ping_status', $id) === 'closed', 'Discussion settings were not saved.');
    lcfa_check(LCFA_Write_Contract::validate($payload, 'update_discussion_settings')['code'] === 'stale_context', 'The consumed snapshot must be stale after save.');
    echo json_encode(['ok' => true, 'test' => 'real_wordpress_kses_content_preservation', 'identity' => $result['execution_identity'], 'framework' => (new LCFA_Environment())->detect_framework_family(), 'content_sha256' => hash('sha256', $original), 'verification_states' => $result['verification_states']], JSON_PRETTY_PRINT) . "\n";
} finally {
    wp_set_current_user(1);
    if ($id && !is_wp_error($id)) wp_delete_post($id, true); // Only this test's fixture.
    wp_set_current_user($original_user);
    kses_init();
}
