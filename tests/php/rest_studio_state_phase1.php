<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

final class WP_REST_Server {
    public const READABLE = 'GET';
}

final class WP_REST_Request {
    public function __construct(private array $params = []) {}

    public function get_param(string $name) {
        return $this->params[$name] ?? null;
    }
}

final class WP_REST_Response {
    public function __construct(private array $data = [], private int $status = 200) {}

    public function get_data(): array {
        return $this->data;
    }

    public function get_status(): int {
        return $this->status;
    }
}

function __(string $text, string $domain = ''): string {
    return $text;
}

function absint($value): int {
    return max(0, (int) $value);
}

function sanitize_key(string $value): string {
    $value = strtolower($value);

    return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function sanitize_text_field($value): string {
    return trim(strip_tags((string) $value));
}

function rest_url(string $path = ''): string {
    return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function current_time(string $type, bool $gmt = false): string {
    return '2026-05-27 12:00:00';
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

final class LCFA_Thread_Message_Actions {}
final class LCFA_Genesis_Executor {
    public function __construct(...$args) {}
}
final class LCFA_Codex_Autorunner {}
final class LCFA_Picostrap_Compile_Service {
    public function __construct(...$args) {}
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'framework' => 'picowind',
            'detected_framework' => 'tailwind',
        ];
    }

    public function get_mcp_adapter_status(): array {
        return [
            'available' => true,
            'custom_server' => [
                'url' => 'https://example.test/wp-json/livecanvas-forge-ai/mcp',
            ],
        ];
    }
}

final class LCFA_Inventory {}
final class LCFA_WindPress_Bridge {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Local_MCP_Bridge {}
final class LCFA_Context_Builder {}
final class LCFA_Command_Deck {}
final class LCFA_Prompt_Suggester {}
final class LCFA_Genesis_Planner {}

final class LCFA_Ability_Registry {
    public function get_ability_diagnostics(): array {
        return [
            'ability_diagnostics' => [
                'total' => 3,
                'mcp_public_total' => 2,
                'mcp_public' => [
                    'livecanvas-forge-ai/get-snapshot',
                    'livecanvas-forge-ai/apply-page-upsert',
                ],
                'mcp_public_preview' => [],
                'mcp_public_write' => [
                    'livecanvas-forge-ai/apply-page-upsert',
                ],
                'has_mcp_public_write' => true,
                'mcp_write_opt_in_enabled' => true,
                'mcp_write_allowlist' => [
                    'livecanvas-forge-ai/apply-page-upsert',
                ],
                'mcp_write_available' => [
                    'livecanvas-forge-ai/apply-page-upsert',
                    'livecanvas-forge-ai/restore-audit-rollback',
                ],
                'items' => [
                    [
                        'name' => 'livecanvas-forge-ai/get-snapshot',
                        'label' => 'Get Forge snapshot',
                        'mcp_public' => true,
                        'readonly' => true,
                        'destructive' => false,
                        'idempotent' => true,
                    ],
                ],
            ],
            'mcp_adapter' => [
                'available' => true,
            ],
            'ai_client' => [
                'available' => true,
                'text_generation_supported' => true,
                'connectors' => [
                    'available' => true,
                    'count' => 2,
                    'text_generation_count' => 1,
                ],
            ],
        ];
    }
}

final class LCFA_Settings {
    public static function get(): array {
        return [
            'completed' => true,
            'framework' => 'picowind',
        ];
    }

    public static function get_connections(): array {
        return [
            'mcp_write_abilities_enabled' => true,
        ];
    }

    public static function get_history(): array {
        return [
            [
                'time' => '2026-05-27 10:00:00',
                'audit_id' => 'audit-123',
                'action' => 'page_upsert',
                'mode' => 'apply',
                'ok' => true,
                'summary' => 'Updated <strong>homepage</strong>',
                'target_type' => 'page',
                'target_id' => 86,
                'target_title' => 'Home',
                'rollback_available' => true,
                'execution_target' => 'local',
                'origin' => 'wp_ability',
                'processed_by' => 'wp_ability_apply_page_upsert',
                'restore' => [
                    'previous_content' => '<section>secret rollback HTML</section>',
                ],
            ],
            [
                'time' => '2026-05-27 11:00:00',
                'audit_id' => '',
                'action' => 'site_audit',
                'mode' => 'preview',
                'ok' => false,
                'message' => 'Audit failed',
            ],
            [
                'time' => '2026-05-27 12:00:00',
                'audit_id' => 'audit-ignored',
                'action' => 'global_shell_apply',
                'mode' => 'apply',
                'ok' => true,
            ],
        ];
    }

    public static function get_mcp_write_ability_options(): array {
        return [
            'livecanvas-forge-ai/apply-page-upsert' => [],
            'livecanvas-forge-ai/restore-audit-rollback' => [],
        ];
    }
}

require LCFA_DIR . 'includes/class-lcfa-rest-api.php';

$rest_source = file_get_contents(LCFA_DIR . 'includes/class-lcfa-rest-api.php');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/handoff-package'"), 'REST API should register a dedicated handoff package route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_handoff_package']"), 'REST API should route handoff package requests to the dedicated callback');

$api = new LCFA_Rest_Api(
    new LCFA_Environment(),
    new LCFA_Inventory(),
    new LCFA_WindPress_Bridge(),
    new LCFA_Theme_Files_Bridge(),
    new LCFA_Local_MCP_Bridge(),
    new LCFA_Context_Builder(),
    new LCFA_Command_Deck(),
    new LCFA_Prompt_Suggester(),
    new LCFA_Genesis_Planner(),
    new LCFA_Genesis_Executor(),
    new LCFA_Ability_Registry()
);

$response = $api->get_studio_state(new WP_REST_Request(['limit' => 2]));
$data = $response->get_data();

lcfa_assert_same(200, $response->get_status(), 'studio state endpoint should return HTTP 200');
lcfa_assert_same(1, $data['studio']['version'] ?? 0, 'studio state should expose a version');
lcfa_assert_same('studio.v1', $data['studio']['schema_version'] ?? '', 'studio state should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/handoff-package', $data['studio']['handoff_package_route'] ?? '', 'studio state should expose handoff package route');
lcfa_assert_same('studio.v1', $data['contract']['schema_version'] ?? '', 'studio contract should expose schema version');
lcfa_assert_same(1, $data['contract']['payload_version'] ?? 0, 'studio contract should expose payload version');
lcfa_assert_same(64, strlen($data['contract']['fingerprint'] ?? ''), 'studio contract should expose a sha256 fingerprint');
lcfa_assert_true(in_array('agent_runbook', $data['contract']['sections'] ?? [], true), 'studio contract should list agent runbook section');
lcfa_assert_true(in_array('handoff_readiness', $data['contract']['sections'] ?? [], true), 'studio contract should list handoff readiness section');
lcfa_assert_true(in_array('agent_handoff_package', $data['contract']['sections'] ?? [], true), 'studio contract should list agent handoff package section');
lcfa_assert_same(2, $data['contract']['limits']['runs'] ?? 0, 'studio contract should expose requested run limit');
lcfa_assert_true(!empty($data['contract']['readiness']['setup_complete']), 'studio contract should expose readiness flags');
lcfa_assert_same(3, $data['summary']['abilities'] ?? 0, 'studio summary should expose ability count');
lcfa_assert_same(2, $data['summary']['mcp_public'] ?? 0, 'studio summary should expose MCP-public count');
lcfa_assert_same(1, $data['summary']['public_writes'] ?? 0, 'studio summary should expose public write count');
lcfa_assert_same(2, $data['summary']['runs'] ?? 0, 'studio summary should respect requested run limit');
lcfa_assert_same(1, $data['summary']['run_errors'] ?? 0, 'studio summary should count failed recent runs');
lcfa_assert_same(1, $data['summary']['rollbacks'] ?? 0, 'studio summary should count rollback-ready runs');
lcfa_assert_true(!empty($data['summary']['mcp_adapter_ready']), 'studio summary should expose MCP Adapter readiness');
lcfa_assert_true(!empty($data['summary']['ai_text_ready']), 'studio summary should expose AI text readiness');
lcfa_assert_true(!empty($data['summary']['mcp_write_master_enabled']), 'studio summary should expose MCP write master state');
lcfa_assert_same('diagnostics', $data['ability_manifest']['source'] ?? '', 'studio ability manifest should fall back to diagnostics without registry manifest access');
lcfa_assert_same(1, $data['ability_manifest']['counts']['items'] ?? 0, 'studio ability manifest should expose compact ability entries');
lcfa_assert_same(1, $data['ability_manifest']['counts']['public'] ?? 0, 'studio ability manifest should count MCP-public entries');
lcfa_assert_same('livecanvas-forge-ai/get-snapshot', $data['ability_manifest']['items'][0]['name'] ?? '', 'studio ability manifest should expose ability names');
lcfa_assert_same('object', $data['ability_manifest']['items'][0]['input_schema_type'] ?? '', 'studio ability manifest should expose schema type fallback');
lcfa_assert_same(1, $data['mcp_write_policy']['counts']['allowed'] ?? 0, 'studio write policy should expose allowlist count');
lcfa_assert_same(1, $data['mcp_write_policy']['counts']['exposed'] ?? 0, 'studio write policy should expose public write count');
$alert_codes = array_column($data['alerts'] ?? [], 'code');
lcfa_assert_true(in_array('mcp_write_exposed', $alert_codes, true), 'studio alerts should flag MCP write exposure');
lcfa_assert_true(in_array('recent_run_errors', $alert_codes, true), 'studio alerts should flag recent run errors');
lcfa_assert_same('Forge Studio operator briefing', $data['operator_briefing']['title'] ?? '', 'studio briefing should expose a title');
lcfa_assert_true(str_contains($data['operator_briefing']['agent_prompt'] ?? '', 'get_snapshot'), 'studio briefing should include a read-only agent prompt');
$briefing_risk_codes = array_column($data['operator_briefing']['risks'] ?? [], 'code');
lcfa_assert_true(in_array('mcp_write_exposed', $briefing_risk_codes, true), 'studio briefing should include write exposure risks');
$briefing_action_codes = array_column($data['operator_briefing']['next_actions'] ?? [], 'code');
lcfa_assert_true(in_array('review_write_allowlist', $briefing_action_codes, true), 'studio briefing should recommend write allowlist review');
lcfa_assert_true(in_array('test_readonly_abilities', $briefing_action_codes, true), 'studio briefing should recommend read-only ability tests');
lcfa_assert_true(!str_contains($data['operator_briefing']['agent_prompt'] ?? '', 'secret rollback HTML'), 'studio briefing should not expose rollback payload content');
lcfa_assert_same('read_only_first', $data['agent_smoke_tests']['mode'] ?? '', 'studio smoke tests should expose a read-only-first mode');
lcfa_assert_same(6, $data['agent_smoke_tests']['counts']['total'] ?? 0, 'studio smoke tests should expose all planned checks');
lcfa_assert_same(1, $data['agent_smoke_tests']['counts']['available'] ?? 0, 'studio smoke tests should count available fallback abilities');
lcfa_assert_same('livecanvas-forge-ai/get-snapshot', $data['agent_smoke_tests']['tests'][0]['ability'] ?? '', 'studio smoke tests should start with snapshot');
lcfa_assert_true(!empty($data['agent_smoke_tests']['tests'][0]['available']), 'studio smoke tests should mark available abilities');
$smoke_tests = array_column($data['agent_smoke_tests']['tests'] ?? [], null, 'id');
lcfa_assert_true(!empty($smoke_tests['write_guard']['public_write_exposed']), 'studio smoke tests should flag exposed write guards');
lcfa_assert_true(!str_contains(json_encode($data['agent_smoke_tests']), 'secret rollback HTML'), 'studio smoke tests should not expose rollback payload content');
lcfa_assert_same('LiveCanvas Forge AI Agent Runbook', $data['agent_runbook']['title'] ?? '', 'studio agent runbook should expose a title');
lcfa_assert_same('markdown', $data['agent_runbook']['format'] ?? '', 'studio agent runbook should expose markdown format');
lcfa_assert_true(($data['agent_runbook']['line_count'] ?? 0) > 10, 'studio agent runbook should expose a non-empty markdown document');
lcfa_assert_true(str_contains($data['agent_runbook']['markdown'] ?? '', 'Smoke Test Order'), 'studio agent runbook should include smoke test order');
lcfa_assert_true(str_contains($data['agent_runbook']['markdown'] ?? '', 'get_snapshot'), 'studio agent runbook should include read-only ability guidance');
lcfa_assert_true(!str_contains($data['agent_runbook']['markdown'] ?? '', 'secret rollback HTML'), 'studio agent runbook should not expose rollback payload content');
lcfa_assert_same('blocked', $data['handoff_readiness']['status'] ?? '', 'studio handoff readiness should expose blocked status when required gates fail');
lcfa_assert_same('read_only_only', $data['handoff_readiness']['recommended_mode'] ?? '', 'studio handoff readiness should recommend read-only mode when blocked');
lcfa_assert_true(($data['handoff_readiness']['score'] ?? 100) < 60, 'studio handoff readiness should lower score for missing gates');
lcfa_assert_same(7, $data['handoff_readiness']['counts']['gates'] ?? 0, 'studio handoff readiness should expose gate count');
$handoff_blocker_codes = array_column($data['handoff_readiness']['blockers'] ?? [], 'id');
lcfa_assert_true(in_array('read_only_smoke_tests', $handoff_blocker_codes, true), 'studio handoff readiness should flag missing read-only smoke tests');
lcfa_assert_true(in_array('preview_smoke_tests', $handoff_blocker_codes, true), 'studio handoff readiness should flag missing preview smoke tests');
$handoff_warning_codes = array_column($data['handoff_readiness']['warnings'] ?? [], 'id');
lcfa_assert_true(in_array('mcp_write_exposure', $handoff_warning_codes, true), 'studio handoff readiness should warn about MCP write exposure');
lcfa_assert_same(1, $data['agent_handoff_package']['package_version'] ?? 0, 'studio handoff package should expose a package version');
lcfa_assert_same('virtual_files', $data['agent_handoff_package']['format'] ?? '', 'studio handoff package should expose virtual file format');
lcfa_assert_same('blocked', $data['agent_handoff_package']['status'] ?? '', 'studio handoff package should mirror readiness status');
lcfa_assert_same('read_only_only', $data['agent_handoff_package']['recommended_mode'] ?? '', 'studio handoff package should mirror recommended mode');
lcfa_assert_true(($data['agent_handoff_package']['summary']['files'] ?? 0) >= 6, 'studio handoff package should include all logical files');
lcfa_assert_same(64, strlen($data['agent_handoff_package']['summary']['checksum'] ?? ''), 'studio handoff package should expose a sha256 checksum');
$package_paths = array_column($data['agent_handoff_package']['files'] ?? [], 'path');
lcfa_assert_true(in_array('forge-agent-runbook.md', $package_paths, true), 'studio handoff package should include the agent runbook file');
lcfa_assert_true(in_array('forge-agent-smoke-tests.json', $package_paths, true), 'studio handoff package should include smoke tests file');
lcfa_assert_true(in_array('forge-mcp-write-policy.json', $package_paths, true), 'studio handoff package should include write policy file');
lcfa_assert_same(64, strlen($data['agent_handoff_package']['files'][0]['sha256'] ?? ''), 'studio handoff package files should include sha256 hashes');
lcfa_assert_true(!str_contains(json_encode($data['agent_handoff_package']), 'secret rollback HTML'), 'studio handoff package should not expose rollback payload content');
lcfa_assert_same(2, $data['run_analysis']['totals']['runs'] ?? 0, 'studio run analysis should count recent runs');
lcfa_assert_same(1, $data['run_analysis']['totals']['ok'] ?? 0, 'studio run analysis should count successful runs');
lcfa_assert_same(1, $data['run_analysis']['totals']['errors'] ?? 0, 'studio run analysis should count failed runs');
lcfa_assert_same(1, $data['run_analysis']['totals']['apply'] ?? 0, 'studio run analysis should count apply runs');
lcfa_assert_same(1, $data['run_analysis']['totals']['preview'] ?? 0, 'studio run analysis should count preview runs');
lcfa_assert_same(1, $data['run_analysis']['totals']['audited'] ?? 0, 'studio run analysis should count audited runs');
$actions = array_column($data['run_analysis']['by_action'] ?? [], null, 'name');
lcfa_assert_same(1, $actions['page_upsert']['count'] ?? 0, 'studio run analysis should group page upsert runs');
lcfa_assert_same(1, $actions['site_audit']['errors'] ?? 0, 'studio run analysis should group action errors');
lcfa_assert_same('site_audit', $data['run_analysis']['recent_errors'][0]['action'] ?? '', 'studio run analysis should expose sanitized recent error metadata');
lcfa_assert_true(!isset($data['run_analysis']['recent_errors'][0]['restore']), 'studio run analysis should not expose rollback payloads');
lcfa_assert_same('Updated homepage', $data['runs']['items'][0]['summary'] ?? '', 'studio runs should sanitize summary HTML');
lcfa_assert_true(!isset($data['runs']['items'][0]['restore']), 'studio runs should not expose rollback payload content');
lcfa_assert_same('audit-123', $data['runs']['items'][0]['audit_id'] ?? '', 'studio runs should keep audit IDs');
lcfa_assert_same('', $data['runs']['items'][1]['audit_id'] ?? 'missing', 'studio runs should allow unaudited read/error entries');

$package_response = $api->get_studio_handoff_package(new WP_REST_Request(['limit' => 2]));
$package_data = $package_response->get_data();
lcfa_assert_same(200, $package_response->get_status(), 'studio handoff package endpoint should return HTTP 200');
lcfa_assert_same('studio.handoff-package.v1', $package_data['studio']['schema_version'] ?? '', 'studio handoff package endpoint should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio', $package_data['studio']['source_route'] ?? '', 'studio handoff package endpoint should expose source Studio route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/handoff-package', $package_data['studio']['rest_route'] ?? '', 'studio handoff package endpoint should expose its route');
lcfa_assert_same('studio.handoff-package.v1', $package_data['contract']['schema_version'] ?? '', 'studio handoff package endpoint should expose contract schema');
lcfa_assert_same(64, strlen($package_data['contract']['fingerprint'] ?? ''), 'studio handoff package endpoint should expose payload fingerprint');
lcfa_assert_same(2, $package_data['contract']['limits']['runs'] ?? 0, 'studio handoff package endpoint should respect requested run limit');
lcfa_assert_same($data['agent_handoff_package']['summary']['checksum'] ?? '', $package_data['agent_handoff_package']['summary']['checksum'] ?? 'missing', 'studio handoff package endpoint should return same package checksum');
lcfa_assert_true(!str_contains(json_encode($package_data), 'secret rollback HTML'), 'studio handoff package endpoint should not expose rollback payload content');

echo "PASS\n";
