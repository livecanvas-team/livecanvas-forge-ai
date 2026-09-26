<?php
/** Read-only context checks on the two authorized sites, without theme switching. */
if (PHP_SAPI !== 'cli') exit(1);
$host = (string) getenv('LCFA_TEST_HOST');
$root = (string) getenv('LCFA_TEST_WP_ROOT');
if (!in_array($host, ['test-ai-forge.local', 'marketing-rocks.local'], true) || !$root) throw new RuntimeException('Explicit authorized site and root required.');
$_SERVER['HTTP_HOST'] = $host; $_SERVER['REQUEST_URI'] = '/';
require rtrim($root, '/') . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== $host) throw new RuntimeException('Site mismatch.');
function context_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
context_check(!empty($admins), 'Existing administrator required.');
wp_set_current_user((int) $admins[0]);
global $wpdb;
$before = $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A);
$theme_before = get_stylesheet();
$context = LCFA_Write_Contract::prepare(['target_type' => 'new_page']);
context_check(!empty($context['ok']), 'Fresh page context unavailable.');
$state = $context['context'];
context_check(!empty($state['theme']['is_child_theme']), 'Both qualification sites should use their actual child theme.');
$blocked = LCFA_Write_Contract::validate(['action' => 'create_page', 'content' => '<style>body{display:none}</style>', 'write_context' => $context['write_context']], 'create_page');
context_check(($blocked['code'] ?? '') === 'markup_contract_violation', 'Layout CSS in content must be rejected.');
$daisy = 'not_applicable';
$cross_framework = 'not_applicable';
if ($state['framework'] !== 'picostrap') {
    $site_context = LCFA_Write_Contract::prepare(['target_type' => 'site']);
    $blocked_bundle = (new LCFA_Picostrap_Bundle_Store(new LCFA_Environment()))->store('.fixture{color:red}', ['write_context' => $site_context['write_context'], 'acknowledge_shared' => true]);
    context_check(($blocked_bundle['code'] ?? '') === 'picostrap_child_required', 'Picostrap bundle writes must not run on a different framework.');
    $cross_framework = 'picostrap_bundle_rejected';
}
if ($state['framework'] === 'picowind' && $state['pipeline']['daisyui'] !== 'compiled') {
    $blocked = LCFA_Write_Contract::validate(['content' => '<section class="hero-content"><a class="btn btn-primary">Example</a></section>', 'write_context' => $context['write_context']], 'create_page');
    context_check(($blocked['code'] ?? '') === 'markup_contract_violation' && strpos($blocked['message'], 'DaisyUI') !== false, 'Unavailable DaisyUI must be rejected before writing.');
    $daisy = 'unavailable_classes_rejected';
} elseif ($state['framework'] === 'picowind') $daisy = 'compiled_evidence_present';
$posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1]);
context_check(!empty($posts), 'A pre-existing public article is required; this test never publishes content.');
$article = LCFA_Write_Contract::prepare(['target_id' => $posts[0]->ID, 'target_url' => get_permalink($posts[0])]);
context_check(!empty($article['ok']), 'Article context unavailable.');
$render = $article['context']['rendering'];
context_check(!empty($render['verified']), 'Actual article renderer must be verified.');
if ($host === 'marketing-rocks.local') context_check(str_ends_with((string) $render['effective_template'], '/picowind-child/views/single.twig'), 'MarketingRocks must resolve its actual single.twig renderer.');
context_check($before === $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A), 'Context checks must preserve all content.');
context_check(get_stylesheet() === $theme_before, 'Context checks must not switch themes.');
echo wp_json_encode(['ok' => true, 'site' => $host, 'framework' => $state['framework'], 'child_theme' => $state['theme']['stylesheet'],
    'render_kind' => $render['kind'], 'effective_template' => str_replace(rtrim(ABSPATH, '/'), '[wordpress]', $render['effective_template']),
    'inline_css' => 'rejected', 'daisyui' => $daisy, 'cross_framework_store' => $cross_framework, 'preexisting_posts_unchanged' => true, 'visually_verified' => 'not_checked']) . "\n";
