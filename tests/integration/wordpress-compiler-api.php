<?php
// Test-only CLI adapter. No secrets or arbitrary method dispatch; production is read-only.
if (PHP_SAPI !== 'cli' || !getenv('LCFA_TEST_WP_ROOT')) exit(1);
$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$root = rtrim(getenv('LCFA_TEST_WP_ROOT'), '/');
$_SERVER['HTTP_HOST'] = getenv('LCFA_TEST_HOST') ?: 'test-ai-forge.local';
$_SERVER['REQUEST_URI'] = '/';
ob_start();
require $root . '/wp-load.php';
if (!class_exists('LCFA_Render_Target')) require dirname(__DIR__, 2) . '/includes/class-lcfa-render-target.php';
if (!class_exists('LCFA_Write_Contract')) require dirname(__DIR__, 2) . '/includes/class-lcfa-write-contract.php';
$environment = new LCFA_Environment();
$windpress = new LCFA_WindPress_Bridge($environment);
$op = $input['op'] ?? '';
$args = $input['args'] ?? [];
if (!in_array($op, ['status', 'volume', 'scan', 'picostrap_manifest', 'picostrap_source', 'context'], true) && wp_parse_url(home_url(), PHP_URL_HOST) !== 'test-ai-forge.local') throw new RuntimeException('Writes are restricted to test-ai-forge.local.');
switch ($op) {
    case 'test_theme':
        if (!in_array($args['stylesheet'] ?? '', ['picowind-child', 'picostrap5-child-base'], true)) throw new RuntimeException('Only the two installed test stacks are allowed.');
        $result = ['previous' => get_stylesheet()]; switch_theme($args['stylesheet']); break;
    case 'status': $result = $windpress->get_status(); $result['source_revision'] = LCFA_Write_Contract::source_revision(); break;
    case 'volume': $result = $windpress->get_volume_entries($args); break;
    case 'scan': $result = $windpress->scan_provider($args['provider_id'], $args['metadata'] ?? []); break;
    case 'context': $result = LCFA_Write_Contract::prepare($args); break;
    case 'store': $result = $windpress->save_cache_css($args['css'], $args['sourcemap'] ?? '', $args['full_build'] ?? null, $args); break;
    case 'picostrap_manifest': $result = (new LCFA_Picostrap_Compile_Service($environment))->get_manifest(); break;
    case 'picostrap_source': $result = (new LCFA_Picostrap_Compile_Service($environment))->get_source($args['import_path']); break;
    case 'picostrap_store':
        $check = LCFA_Write_Contract::validate($args, 'store_picostrap_bundle');
        $result = $check['ok'] ? (new LCFA_Picostrap_Compile_Service($environment))->store_bundle($args['css'], $args) : $check;
        break;
    default: throw new RuntimeException('Unknown test operation.');
}
ob_end_clean();
echo wp_json_encode($result);
