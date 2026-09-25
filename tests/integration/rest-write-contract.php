<?php
if (PHP_SAPI !== 'cli') exit(1);
if (!getenv('LCFA_TEST_WP_ROOT')) { echo "SKIP: LCFA_TEST_WP_ROOT is not configured\n"; exit; }
$_SERVER['HTTP_HOST'] = 'test-ai-forge.local'; $_SERVER['REQUEST_URI'] = '/';
require rtrim(getenv('LCFA_TEST_WP_ROOT'), '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'test-ai-forge.local') throw new RuntimeException('Test site only.');
wp_set_current_user(1);
function contract_request($route, $payload, $method = 'POST') {
    $request = new WP_REST_Request($method, '/lcfa/v1/' . $route);
    if ($method === 'GET') $request->set_query_params($payload);
    else { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($payload)); }
    return rest_do_request($request)->get_data();
}
function ensure_contract($value, $message) { if (!$value) throw new RuntimeException($message); }
$path = 'views/lcfa-contract-never-written.twig';
$before = is_file(get_stylesheet_directory() . '/' . $path);
ensure_contract(!$before, 'Sentinel must not already exist.');
$missing = contract_request('theme/file', ['path' => $path, 'content' => '<p>blocked</p>']);
ensure_contract(($missing['code'] ?? '') === 'context_required', json_encode($missing));
$context = contract_request('write-context', ['target_type' => 'theme_file', 'path' => $path], 'GET');
ensure_contract(!empty($context['ok']), json_encode($context));
$bad = contract_request('theme/file', ['path' => $path, 'write_context' => $context['write_context'], 'acknowledge_shared' => true, 'content' => '<style>body{display:none}</style>']);
ensure_contract(($bad['code'] ?? '') === 'markup_contract_violation', json_encode($bad));
ensure_contract(!is_file(get_stylesheet_directory() . '/' . $path), 'Rejected file must never be written.');
$ability = function_exists('wp_get_ability') ? wp_get_ability('livecanvas-forge-ai/theme-file-write') : null;
if ($ability) {
    $result = $ability->execute(['path' => $path, 'content' => '<p>blocked</p>']);
    ensure_contract(is_array($result) && ($result['code'] ?? '') === 'context_required', 'Remote ability failed to enforce context: ' . wp_json_encode($result));
}
$site = LCFA_Write_Contract::prepare(['target_type' => 'site']);
$windpress = new LCFA_WindPress_Bridge(new LCFA_Environment());
$cache = $windpress->get_status()['cache']['css']['path'] ?? '';
$hash = is_file($cache) ? hash_file('sha256', $cache) : null;
$stale = $windpress->save_cache_css('.example{display:block}', '', null, ['write_context' => $site['write_context'], 'acknowledge_shared' => true, 'source_revision' => 'not-current']);
ensure_contract(($stale['code'] ?? '') === 'stale_sources', json_encode($stale));
$source = $windpress->save_cache_css('@tailwind utilities;', '', null, ['write_context' => $site['write_context'], 'acknowledge_shared' => true, 'source_revision' => $site['context']['source_revision']]);
ensure_contract(empty($source['ok']), 'Source CSS must not overwrite compiled CSS.');
ensure_contract((is_file($cache) ? hash_file('sha256', $cache) : null) === $hash, 'Previous cache changed after a rejected build.');
$fixture_id = 0;
try {
    $page_context = LCFA_Write_Contract::prepare(['target_type' => 'new_page']);
    $created = contract_request('command', ['action' => 'create_page', 'title' => 'Bridge write contract draft fixture', 'status' => 'draft', 'body_html' => '<section class="container"><h1>Bridge test</h1></section>', 'write_context' => $page_context['write_context']]);
    $created = $created['result'] ?? $created;
    $fixture_id = (int) ($created['target_id'] ?? 0);
    ensure_contract(!empty($created['ok']) && $fixture_id > 0 && get_post_status($fixture_id) === 'draft', 'Valid draft creation failed: ' . wp_json_encode($created));
    $edit_context = LCFA_Write_Contract::prepare(['target_id' => $fixture_id]);
    $edit_ability = function_exists('wp_get_ability') ? wp_get_ability('livecanvas-forge-ai/apply-page-upsert') : null;
    if ($edit_ability) {
        $edited = $edit_ability->execute(['target_id' => $fixture_id, 'body_html' => '<section class="container"><h1>Verified update</h1></section>', 'write_context' => $edit_context['write_context']]);
        ensure_contract(!is_wp_error($edited), 'Ability schema rejected valid context.');
        ensure_contract(strpos(get_post_field('post_content', $fixture_id, 'raw'), 'Verified update') !== false, 'Valid ability update failed: ' . wp_json_encode($edited));
    }
    echo wp_json_encode(['ok' => true, 'rest_missing_context' => 'rejected', 'rest_inline_css' => 'rejected', 'remote_ability' => $ability ? 'rejected_missing_context' : 'unavailable', 'stale_sources' => 'rejected', 'previous_cache_preserved' => true, 'draft_creation' => 'saved', 'ability_draft_update' => $edit_ability ? 'saved' : 'unavailable']) . "\n";
} finally {
    if ($fixture_id && get_post_field('post_title', $fixture_id) === 'Bridge write contract draft fixture') wp_delete_post($fixture_id, true);
}
