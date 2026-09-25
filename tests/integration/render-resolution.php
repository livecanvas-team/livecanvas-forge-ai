<?php
if (PHP_SAPI !== 'cli') exit(1);
if (!getenv('LCFA_TEST_WP_ROOT')) { echo "SKIP: LCFA_TEST_WP_ROOT is not configured\n"; exit; }
$_SERVER['HTTP_HOST'] = 'test-ai-forge.local'; $_SERVER['REQUEST_URI'] = '/';
require rtrim(getenv('LCFA_TEST_WP_ROOT'), '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'test-ai-forge.local') throw new RuntimeException('Test site only.');
$original_theme = get_stylesheet();
$original_lc_settings = get_option('lc_settings');
$fixtures = [];
try {
    switch_theme('picowind-child');
    $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1]);
    if (!$posts) throw new RuntimeException('A pre-existing published article is required. This test never publishes content.');
    $context = LCFA_Write_Contract::prepare(['target_id' => $posts[0]->ID, 'target_url' => get_permalink($posts[0])]);
    if (!$context['ok']) throw new RuntimeException(json_encode($context));
    $render = $context['context']['rendering'];
    $assigned_renderer = $render;
    if ($render['kind'] === 'livecanvas_dynamic_template') {
        $settings = (array) $original_lc_settings;
        unset($settings['enable-dynamic-templating']);
        update_option('lc_settings', $settings);
        $context = LCFA_Write_Contract::prepare(['target_id' => $posts[0]->ID, 'target_url' => get_permalink($posts[0])]);
        $render = $context['context']['rendering'];
    }
    if (empty($render['verified']) || !str_ends_with((string) $render['effective_template'], '/picowind-child/views/single.twig')) throw new RuntimeException('Unexpected renderer: ' . json_encode($render));
    $proof = $context['write_context'];
    foreach (['lc_partial', 'lc_dynamic_template'] as $type) {
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Bridge renderer fixture']);
        $fixtures[] = $id;
        $shared = LCFA_Write_Contract::prepare(['target_id' => $id]);
        if (!$shared['ok'] || $shared['context']['rendering']['kind'] !== $type || $shared['context']['target']['impact'] !== 'multiple_pages') throw new RuntimeException('Shared target classification failed.');
    }
    $stale = LCFA_Write_Contract::validate(['target_id' => $posts[0]->ID, 'write_context' => $proof], 'update_discussion_settings');
    if (($stale['code'] ?? '') !== 'stale_context') throw new RuntimeException('Changed source inventory must invalidate the old context.');
    echo json_encode(['ok' => true, 'assigned_renderer' => $assigned_renderer, 'theme_fallback' => $render, 'shared_targets' => 'passed', 'stale_context' => 'rejected', 'visually_verified' => 'not_checked'], JSON_PRETTY_PRINT) . "\n";
} finally {
    foreach ($fixtures as $id) wp_delete_post($id, true);
    switch_theme($original_theme);
    update_option('lc_settings', $original_lc_settings);
}
