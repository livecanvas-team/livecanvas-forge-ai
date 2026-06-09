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

$library = $patterns->get_pattern_library();
lcfa_patterns_assert_same('block-pattern-library.v1', $library['schema_version'] ?? '', 'pattern library should expose a stable schema version');
lcfa_patterns_assert_same('wordpress_block_patterns', $library['export']['format'] ?? '', 'pattern library should expose its export format');
lcfa_patterns_assert_same(2, $library['counts']['patterns'] ?? 0, 'pattern library should expose the registered pattern count');
lcfa_patterns_assert_true(!empty($library['patterns'][0]['content']), 'pattern library should include content by default');
lcfa_patterns_assert_same(64, strlen((string) ($library['patterns'][0]['sha256'] ?? '')), 'pattern library entries should include sha256 checksums');
lcfa_patterns_assert_true(($library['patterns'][0]['bytes'] ?? 0) > 0, 'pattern library entries should include byte size');

$metadata_only_library = $patterns->get_pattern_library(['include_content' => false]);
lcfa_patterns_assert_true(empty($metadata_only_library['include_content']), 'pattern library should support metadata-only exports');
lcfa_patterns_assert_true(!isset($metadata_only_library['patterns'][0]['content']), 'metadata-only pattern exports should omit full block content');

$blueprints = $patterns->get_native_page_blueprints();
lcfa_patterns_assert_same('native-pattern-page-blueprints.v1', $blueprints['schema_version'] ?? '', 'native page blueprints should expose a stable schema version');
lcfa_patterns_assert_same(3, $blueprints['counts']['blueprints'] ?? 0, 'native page blueprints should expose registered recipe count');
lcfa_patterns_assert_same('livecanvas-forge-ai/preview-native-pattern-page', $blueprints['preview_ability'] ?? '', 'native page blueprints should point to the preview ability');
lcfa_patterns_assert_same('preview_native_pattern_page', $blueprints['preview_tool'] ?? '', 'native page blueprints should point to the local MCP preview tool');
lcfa_patterns_assert_same('/wp-json/lcfa/v1/studio/native-pattern-page-preview', $blueprints['preview_route'] ?? '', 'native page blueprints should expose the preview REST route');
lcfa_patterns_assert_same('livecanvas-forge-ai/apply-native-pattern-page', $blueprints['apply_ability'] ?? '', 'native page blueprints should point to the apply ability');
lcfa_patterns_assert_same('apply_native_pattern_page', $blueprints['apply_tool'] ?? '', 'native page blueprints should point to the local MCP apply tool');
lcfa_patterns_assert_same('/wp-json/lcfa/v1/studio/native-pattern-page-apply', $blueprints['apply_route'] ?? '', 'native page blueprints should expose the apply REST route');
lcfa_patterns_assert_same('starter-landing', $blueprints['blueprints'][0]['id'] ?? '', 'native page blueprints should expose the starter landing recipe first');
lcfa_patterns_assert_true(in_array('livecanvas-forge-ai/conversion-hero', $blueprints['blueprints'][0]['pattern_names'] ?? [], true), 'native page blueprints should include normalized pattern names');
lcfa_patterns_assert_same('POST', $blueprints['blueprints'][0]['preview_request']['method'] ?? '', 'native page blueprints should expose preview request method');
lcfa_patterns_assert_same('preview_native_pattern_page', $blueprints['blueprints'][0]['preview_request']['mcp_tool'] ?? '', 'native page blueprints should expose preview request MCP tool');
lcfa_patterns_assert_same('starter-landing', $blueprints['blueprints'][0]['preview_request']['payload']['blueprint'] ?? '', 'native page blueprints should expose copy-ready preview request payload');
lcfa_patterns_assert_same('POST', $blueprints['blueprints'][0]['apply_request']['method'] ?? '', 'native page blueprints should expose apply request method');
lcfa_patterns_assert_same('apply_native_pattern_page', $blueprints['blueprints'][0]['apply_request']['mcp_tool'] ?? '', 'native page blueprints should expose apply request MCP tool');
lcfa_patterns_assert_same('draft', $blueprints['blueprints'][0]['apply_request']['payload']['status'] ?? '', 'native page blueprints should expose draft-only apply request payload');

$metadata_only_blueprints = $patterns->get_native_page_blueprints(['include_patterns' => false]);
lcfa_patterns_assert_true(empty($metadata_only_blueprints['include_patterns']), 'native page blueprints should support metadata-only exports');
lcfa_patterns_assert_true(!isset($metadata_only_blueprints['blueprints'][0]['pattern_names']), 'metadata-only native page blueprints should omit pattern names');

$preview = $patterns->build_pattern_preview([
    'html' => '<section><h2>Reusable section</h2></section>',
    'title' => 'Reusable Section',
    'slug' => 'reusable-section',
]);

lcfa_patterns_assert_true(!empty($preview['ok']), 'pattern preview should succeed for supplied HTML');
lcfa_patterns_assert_same('livecanvas-forge-ai/reusable-section', $preview['pattern']['name'] ?? '', 'pattern preview should derive the namespaced pattern name');
lcfa_patterns_assert_true(strpos((string) ($preview['pattern']['content'] ?? ''), 'wp:html') !== false, 'pattern preview should wrap supplied markup in a core HTML block');

$page_preview = $patterns->build_native_page_preview([
    'title' => 'Native Pattern Page',
    'pattern_names' => ['conversion-hero', 'feature-grid'],
]);

lcfa_patterns_assert_true(!empty($page_preview['ok']), 'native page preview should succeed for known pattern slugs');
lcfa_patterns_assert_same('native-pattern-page-preview.v1', $page_preview['schema_version'] ?? '', 'native page preview should expose a stable schema version');
lcfa_patterns_assert_same('wordpress_blocks', $page_preview['page']['content_format'] ?? '', 'native page preview should expose WordPress block content format');
lcfa_patterns_assert_same(2, count($page_preview['page']['patterns'] ?? []), 'native page preview should include selected pattern metadata');
lcfa_patterns_assert_same(64, strlen((string) ($page_preview['page']['sha256'] ?? '')), 'native page preview should expose a content checksum');
lcfa_patterns_assert_true(strpos((string) ($page_preview['page']['content'] ?? ''), 'wp:livecanvas-forge-ai/section-shell') !== false, 'native page preview should compose registered pattern content');

$blueprint_page_preview = $patterns->build_native_page_preview([
    'blueprint' => 'starter-landing',
]);
lcfa_patterns_assert_true(!empty($blueprint_page_preview['ok']), 'native page preview should succeed from a known blueprint');
lcfa_patterns_assert_same('starter-landing', $blueprint_page_preview['page']['blueprint'] ?? '', 'native page preview should expose the selected blueprint');
lcfa_patterns_assert_same(2, count($blueprint_page_preview['page']['patterns'] ?? []), 'native page blueprint preview should include its recipe patterns');

$missing_page_preview = $patterns->build_native_page_preview([
    'pattern_names' => ['missing-pattern'],
]);
lcfa_patterns_assert_true(empty($missing_page_preview['ok']), 'native page preview should fail when no requested pattern is available');
lcfa_patterns_assert_true(in_array('livecanvas-forge-ai/missing-pattern', $missing_page_preview['missing'] ?? [], true), 'native page preview should report missing normalized pattern names');

$missing_blueprint_preview = $patterns->build_native_page_preview([
    'blueprint' => 'missing-blueprint',
]);
lcfa_patterns_assert_true(empty($missing_blueprint_preview['ok']), 'native page preview should fail for an unknown blueprint without explicit pattern names');
lcfa_patterns_assert_same('native-pattern-page-blueprints.v1', $missing_blueprint_preview['blueprints']['schema_version'] ?? '', 'unknown blueprint previews should return available blueprints');

echo "PASS\n";
