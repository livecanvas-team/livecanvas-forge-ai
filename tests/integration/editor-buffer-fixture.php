<?php
/** Reversible drafts for manual/native-editor QA, never an external agent run. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST'); $root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_PORT'] = '80';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host || LCFA_VERSION !== '0.2.0-beta.8-dev.5') throw new RuntimeException('Site or build mismatch.');
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
if (!$owner) throw new RuntimeException('Existing administrator required.');
wp_set_current_user($owner);
$operation = $argv[1] ?? ''; $id = (int) ($argv[2] ?? 0); $marker = '_lcfa_editor_buffer_fixture_dev5';
if ($operation === 'create') {
    $context = LCFA_Write_Contract::prepare(['target_type' => 'new_page']);
    if (empty($context['ok'])) throw new RuntimeException('Site context unavailable.');
    $classes = $context['context']['framework'] === 'picostrap' ? 'container py-5' : 'mx-auto max-w-3xl px-6 py-12';
    $request = new WP_REST_Request('POST', '/lcfa/v1/command');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode(['action' => 'create_page', 'status' => 'draft', 'title' => 'About us — Bridge editor test',
        'body_html' => '<section id="bridge-editor-fixture" class="' . $classes . '"><h1>About our test team</h1><p>Private draft for editor protection testing. Not for publication.</p></section>', 'write_context' => $context['write_context']]));
    $result = rest_do_request($request)->get_data()['result'] ?? [];
    if (empty($result['ok']) || empty($result['target_id'])) throw new RuntimeException('Draft creation failed: ' . wp_json_encode($result));
    $id = (int) $result['target_id'];
    update_post_meta($id, $marker, hash('sha256', get_post_field('post_content', $id)));
} else {
    if (!$id || get_post_type($id) !== 'page' || !get_post_meta($id, $marker, true) || get_post_status($id) !== 'draft') throw new RuntimeException('Only the exact marked draft can be checked or trashed.');
    if ($operation === 'trash') { if (!wp_trash_post($id)) throw new RuntimeException('Trash failed.'); }
    elseif ($operation !== 'check') throw new RuntimeException('Expected create, check or trash.');
}
echo wp_json_encode(['site' => $host, 'version' => LCFA_VERSION, 'fixture_id' => $id, 'status' => get_post_status($id),
    'original_content_preserved' => hash_equals((string) get_post_meta($id, $marker, true), hash('sha256', get_post_field('post_content', $id))),
    'preview_url' => get_preview_post_link($id), 'editor_url' => add_query_arg(['lc_action_launch_editing' => '1', 'from_url' => lc_urlencode(get_permalink($id)), 'from_page_edit' => '1'], get_permalink($id))]) . "\n";
