<?php
/** Read-only preset provenance across the real WordPress REST and Ability surfaces. */
require __DIR__ . '/source-plugin-bootstrap.php';
function ep_check($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function ep_read(string $route): array {
    $response = rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/' . $route));
    ep_check($response->get_status() === 200, 'Authenticated read failed: ' . $route);
    return $response->get_data();
}
global $wpdb;
$baseline = static function () use ($wpdb) {
    return hash('sha256', wp_json_encode([
        $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A),
        $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A),
        get_option('active_plugins'), get_option('stylesheet'), get_option('template'),
        get_option('lc_settings'), get_option('windpress_options'), get_option('lcfa_windpress_compile_evidence'),
    ]));
};
$before = $baseline();
wp_set_current_user(0);
ep_check(in_array(rest_do_request(new WP_REST_Request('GET', '/lcfa/v1/context'))->get_status(), [401, 403], true), 'Anonymous context must remain denied.');
$owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
ep_check($owner > 0, 'Existing administrator required.'); wp_set_current_user($owner);
$expected = (new LCFA_Environment())->get_editor_profile();
foreach ([
    'snapshot' => ['snapshot'],
    'inventory' => ['inventory', 'summary'],
    'context' => ['context', 'stack'],
    'theme-context' => ['theme_context', 'stack'],
] as $route => $keys) {
    $rest = ep_read($route);
    $ability = wp_get_ability('livecanvas-forge-ai/get-' . $route);
    ep_check($ability !== null, 'Read Ability unavailable: ' . $route);
    $remote = $ability->execute([]);
    foreach ([$rest, $remote] as $result) {
        foreach ($keys as $key) $result = $result[$key];
        ep_check(($result['editor_profile'] ?? null) === $expected, 'Preset provenance missing from ' . $route);
        ep_check(($result[$route === 'snapshot' ? 'framework_slug' : 'editor_config'] ?? null) === $expected['slug'], 'Legacy preset value changed.');
    }
}
ep_check($expected['is_compile_evidence'] === false, 'Preset must not imply compiled output.');
$context = LCFA_Write_Contract::prepare(['target_type' => 'new_page']);
ep_check(!empty($context['ok']), 'Write context unavailable.');
$state = $context['context'];
if ($state['framework'] === 'picowind' && $state['pipeline']['daisyui'] !== 'compiled') {
    $result = LCFA_Write_Contract::validate(['write_context' => $context['write_context'], 'content' => '<a class="btn btn-primary">Example</a>'], 'create_page');
    ep_check(($result['code'] ?? '') === 'markup_contract_violation' && str_contains($result['message'], 'DaisyUI'), 'DaisyUI preset must not bypass compile evidence.');
}
$page_rules = ep_read('theme-context')['theme_context']['output_rules'];
ep_check($page_rules['css_strategy'] === ($state['framework'] === 'picowind' ? 'tailwind-utilities' : 'bootstrap-5'), 'Framework-native rules changed.');
ep_check($before === $baseline(), 'Read-only checks changed existing content or configuration.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'build' => $fixture_build, 'version' => LCFA_VERSION,
    'framework' => $state['framework'], 'editor_preset' => $expected['slug'], 'is_compile_evidence' => false,
    'daisyui' => $state['pipeline']['daisyui'], 'typography' => $state['pipeline']['typography'],
    'rest_ability_parity' => true, 'content_and_config_unchanged' => true, 'agent_execution' => 'not_tested']) . "\n";
