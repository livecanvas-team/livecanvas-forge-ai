<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_actions'] = [];
$GLOBALS['lcfa_test_blocks'] = [];
$GLOBALS['lcfa_test_pattern_categories'] = [];
$GLOBALS['lcfa_test_patterns'] = [];

function __($text, $domain = null) { return $text; }
function add_action($hook, $callback) { $GLOBALS['lcfa_test_actions'][$hook] = $callback; }
function register_block_type($name, array $args = []) { $GLOBALS['lcfa_test_blocks'][$name] = $args; }
function register_block_pattern_category($name, array $args = []) { $GLOBALS['lcfa_test_pattern_categories'][$name] = $args; }
function register_block_pattern($name, array $args = []) { $GLOBALS['lcfa_test_patterns'][$name] = $args; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $value)), '-'); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_kses_post($value) { return (string) $value; }

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'livecanvas_active' => true,
            'detected_framework' => 'picowind',
            'current_theme_stylesheet' => 'picowind-child',
        ];
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-block-patterns.php';

function lcfa_patterns_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function lcfa_patterns_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$patterns = new LCFA_Block_Patterns(new LCFA_Environment());
$patterns->hooks();

lcfa_patterns_assert_true(isset($GLOBALS['lcfa_test_actions']['init']), 'block pattern registry should hook init');

$patterns->register_blocks_and_patterns();

lcfa_patterns_assert_true(isset($GLOBALS['lcfa_test_blocks']['livecanvas-forge-ai/section-shell']), 'section-shell block should be registered');
lcfa_patterns_assert_true(is_callable($GLOBALS['lcfa_test_blocks']['livecanvas-forge-ai/section-shell']['render_callback'] ?? null), 'section-shell block should expose a render callback');
lcfa_patterns_assert_true(isset($GLOBALS['lcfa_test_pattern_categories']['livecanvas-forge-ai']), 'Forge pattern category should be registered');
lcfa_patterns_assert_true(isset($GLOBALS['lcfa_test_patterns']['livecanvas-forge-ai/conversion-hero']), 'conversion hero pattern should be registered');
lcfa_patterns_assert_true(isset($GLOBALS['lcfa_test_patterns']['livecanvas-forge-ai/feature-grid']), 'feature grid pattern should be registered');
lcfa_patterns_assert_true(strpos((string) ($GLOBALS['lcfa_test_patterns']['livecanvas-forge-ai/conversion-hero']['content'] ?? ''), 'wp:livecanvas-forge-ai/section-shell') !== false, 'patterns should use the Forge section shell block');

$rendered = $patterns->render_section_shell_block([
    'eyebrow' => 'Small',
    'heading' => 'Native fallback',
    'variant' => 'hero',
    'align' => 'wide',
], '<p>Content</p>');

lcfa_patterns_assert_true(strpos($rendered, 'lcfa-forge-section--hero') !== false, 'rendered block should include the variant class');
lcfa_patterns_assert_true(strpos($rendered, 'alignwide') !== false, 'rendered block should include alignwide when requested');
lcfa_patterns_assert_true(strpos($rendered, '<h2 class="lcfa-forge-section__heading">Native fallback</h2>') !== false, 'rendered block should include the heading');

$manifest = $patterns->get_pattern_manifest();
lcfa_patterns_assert_same('livecanvas-forge-ai', $manifest['category'] ?? '', 'manifest should expose the pattern category');
lcfa_patterns_assert_same('livecanvas-forge-ai/section-shell', $manifest['block'] ?? '', 'manifest should expose the dynamic block name');
lcfa_patterns_assert_same(2, $manifest['counts']['patterns'] ?? 0, 'manifest should expose the registered pattern count');
lcfa_patterns_assert_same('picowind', $manifest['context']['framework'] ?? '', 'manifest should expose environment context');

$preview = $patterns->build_pattern_preview([
    'html' => '<section><h2>Reusable section</h2></section>',
    'title' => 'Reusable Section',
    'slug' => 'reusable-section',
]);

lcfa_patterns_assert_true(!empty($preview['ok']), 'pattern preview should succeed for supplied HTML');
lcfa_patterns_assert_same('livecanvas-forge-ai/reusable-section', $preview['pattern']['name'] ?? '', 'pattern preview should derive the namespaced pattern name');
lcfa_patterns_assert_true(strpos((string) ($preview['pattern']['content'] ?? ''), 'wp:html') !== false, 'pattern preview should wrap supplied markup in a core HTML block');

echo "PASS\n";
