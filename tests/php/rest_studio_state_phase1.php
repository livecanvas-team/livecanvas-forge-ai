<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

final class WP_REST_Server {
    public const READABLE = 'GET';
    public const CREATABLE = 'POST';
}

final class WP_REST_Request {
    public function __construct(private array $params = []) {}

    public function get_param(string $name) {
        return $this->params[$name] ?? null;
    }

    public function get_params(): array {
        return $this->params;
    }

    public function get_json_params(): array {
        return $this->params;
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
                'total' => 6,
                'mcp_public_total' => 5,
                'mcp_public' => [
                    'livecanvas-forge-ai/get-snapshot',
                    'livecanvas-forge-ai/get-block-pattern-library',
                    'livecanvas-forge-ai/get-native-pattern-page-blueprints',
                    'livecanvas-forge-ai/preview-native-pattern-page',
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
                    'livecanvas-forge-ai/apply-native-pattern-page',
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
                    [
                        'name' => 'livecanvas-forge-ai/get-block-pattern-library',
                        'label' => 'Get Forge block pattern library',
                        'mcp_public' => true,
                        'readonly' => true,
                        'destructive' => false,
                        'idempotent' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'include_content' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'livecanvas-forge-ai/get-native-pattern-page-blueprints',
                        'label' => 'Get native pattern page blueprints',
                        'mcp_public' => true,
                        'readonly' => true,
                        'destructive' => false,
                        'idempotent' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'include_patterns' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'livecanvas-forge-ai/preview-native-pattern-page',
                        'label' => 'Preview native pattern page',
                        'mcp_public' => true,
                        'readonly' => true,
                        'destructive' => false,
                        'idempotent' => true,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'pattern_names' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'livecanvas-forge-ai/apply-native-pattern-page',
                        'label' => 'Apply native pattern page',
                        'mcp_public' => false,
                        'readonly' => false,
                        'destructive' => true,
                        'idempotent' => false,
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'blueprint' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ],
                        ],
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

    public function get_native_pattern_page_blueprints(array $input = []): array {
        $include_patterns = !isset($input['include_patterns']) || !empty($input['include_patterns']);
        $blueprint = [
            'id' => 'test-landing',
            'title' => 'Test landing',
            'description' => 'A test native page recipe.',
            'pattern_count' => 1,
            'preview_payload' => [
                'title' => 'Test landing preview',
                'blueprint' => 'test-landing',
            ],
            'preview_request' => [
                'method' => 'POST',
                'rest_route' => '/wp-json/lcfa/v1/studio/native-pattern-page-preview',
                'ability' => 'livecanvas-forge-ai/preview-native-pattern-page',
                'mcp_tool' => 'preview_native_pattern_page',
                'payload' => [
                    'title' => 'Test landing preview',
                    'blueprint' => 'test-landing',
                ],
            ],
            'suggested_use' => 'Use for tests.',
        ];
        if ($include_patterns) {
            $blueprint['pattern_names'] = ['livecanvas-forge-ai/test-pattern'];
        }

        return [
            'native_pattern_page_blueprints' => [
                'schema_version' => 'native-pattern-page-blueprints.v1',
                'available' => true,
                'source' => 'test_registry',
                'include_patterns' => $include_patterns,
                'counts' => [
                    'blueprints' => 1,
                ],
                'preview_ability' => 'livecanvas-forge-ai/preview-native-pattern-page',
                'preview_tool' => 'preview_native_pattern_page',
                'preview_route' => '/wp-json/lcfa/v1/studio/native-pattern-page-preview',
                'blueprints' => [$blueprint],
            ],
        ];
    }

    public function preview_native_pattern_page(array $input = []): array {
        return [
            'native_pattern_page_preview' => [
                'ok' => true,
                'schema_version' => 'native-pattern-page-preview.v1',
                'page' => [
                    'title' => (string) ($input['title'] ?? 'Native Pattern Page'),
                    'content_format' => 'wordpress_blocks',
                    'content' => '<!-- wp:paragraph --><p>Pattern page</p><!-- /wp:paragraph -->',
                    'sha256' => hash('sha256', '<!-- wp:paragraph --><p>Pattern page</p><!-- /wp:paragraph -->'),
                    'patterns' => [
                        ['name' => 'livecanvas-forge-ai/test-pattern'],
                    ],
                ],
            ],
        ];
    }

    public function apply_native_pattern_page(array $input = []): array {
        return [
            'native_pattern_page_apply' => [
                'ok' => true,
                'schema_version' => 'native-pattern-page-apply.v1',
                'page' => [
                    'id' => 456,
                    'title' => (string) ($input['title'] ?? 'Native Pattern Page'),
                    'status' => 'draft',
                    'content_format' => 'wordpress_blocks',
                ],
                'audit_id' => 'audit-native',
                'rollback_available' => true,
            ],
        ];
    }

    public function get_block_pattern_library(array $input = []): array {
        $include_content = !isset($input['include_content']) || !empty($input['include_content']);
        $pattern = [
            'name' => 'livecanvas-forge-ai/test-pattern',
            'title' => 'Test Pattern',
            'description' => 'A test block pattern.',
            'categories' => ['livecanvas-forge-ai'],
            'block' => 'livecanvas-forge-ai/section-shell',
            'bytes' => 58,
            'sha256' => hash('sha256', '<!-- wp:paragraph --><p>Pattern</p><!-- /wp:paragraph -->'),
        ];
        if ($include_content) {
            $pattern['content'] = '<!-- wp:paragraph --><p>Pattern</p><!-- /wp:paragraph -->';
        }

        return [
            'block_pattern_library' => [
                'schema_version' => 'block-pattern-library.v1',
                'available' => true,
                'source' => 'test_registry',
                'category' => 'livecanvas-forge-ai',
                'block' => 'livecanvas-forge-ai/section-shell',
                'include_content' => $include_content,
                'counts' => [
                    'patterns' => 1,
                    'bytes' => 58,
                ],
                'context' => [
                    'framework' => 'picowind',
                ],
                'export' => [
                    'format' => 'wordpress_block_patterns',
                    'can_import' => false,
                    'preview_ability' => 'livecanvas-forge-ai/preview-block-pattern',
                ],
                'patterns' => [$pattern],
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
            'preferred_client' => 'codex',
            'connection_mode' => 'remote',
            'connection_status' => 'ready',
            'connection_current_step' => 'ready',
            'connection_last_verified_at' => '2026-05-27 11:30:00',
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
            'livecanvas-forge-ai/apply-native-pattern-page' => [],
            'livecanvas-forge-ai/restore-audit-rollback' => [],
        ];
    }
}

require LCFA_DIR . 'includes/class-lcfa-rest-api.php';

$rest_source = file_get_contents(LCFA_DIR . 'includes/class-lcfa-rest-api.php');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/handoff-package'"), 'REST API should register a dedicated handoff package route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_handoff_package']"), 'REST API should route handoff package requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/handoff-summary'"), 'REST API should register a dedicated handoff summary route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_handoff_summary']"), 'REST API should route handoff summary requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/connection-handoff'"), 'REST API should register a dedicated connection handoff route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_connection_handoff']"), 'REST API should route connection handoff requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/block-pattern-library'"), 'REST API should register a dedicated block pattern library route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_block_pattern_library']"), 'REST API should route block pattern library requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/native-pattern-page-blueprints'"), 'REST API should register a dedicated native pattern page blueprints route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'get_studio_native_pattern_page_blueprints']"), 'REST API should route native pattern page blueprint requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/native-pattern-page-preview'"), 'REST API should register a native pattern page preview route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'preview_studio_native_pattern_page']"), 'REST API should route native pattern page preview requests to the dedicated callback');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "register_rest_route('lcfa/v1', '/studio/native-pattern-page-apply'"), 'REST API should register a native pattern page apply route');
lcfa_assert_true(is_string($rest_source) && str_contains($rest_source, "'callback'            => [\$this, 'apply_studio_native_pattern_page']"), 'REST API should route native pattern page apply requests to the dedicated callback');

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
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/handoff-summary', $data['studio']['handoff_summary_route'] ?? '', 'studio state should expose handoff summary route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/connection-handoff', $data['studio']['connection_handoff_route'] ?? '', 'studio state should expose connection handoff route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/block-pattern-library', $data['studio']['block_pattern_library_route'] ?? '', 'studio state should expose block pattern library route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/native-pattern-page-blueprints', $data['studio']['native_pattern_page_blueprints_route'] ?? '', 'studio state should expose native pattern page blueprints route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/native-pattern-page-preview', $data['studio']['native_pattern_page_preview_route'] ?? '', 'studio state should expose native pattern page preview route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/native-pattern-page-apply', $data['studio']['native_pattern_page_apply_route'] ?? '', 'studio state should expose native pattern page apply route');
lcfa_assert_same('studio.v1', $data['contract']['schema_version'] ?? '', 'studio contract should expose schema version');
lcfa_assert_same(1, $data['contract']['payload_version'] ?? 0, 'studio contract should expose payload version');
lcfa_assert_same(64, strlen($data['contract']['fingerprint'] ?? ''), 'studio contract should expose a sha256 fingerprint');
lcfa_assert_true(in_array('agent_runbook', $data['contract']['sections'] ?? [], true), 'studio contract should list agent runbook section');
lcfa_assert_true(in_array('handoff_readiness', $data['contract']['sections'] ?? [], true), 'studio contract should list handoff readiness section');
lcfa_assert_true(in_array('handoff_summary', $data['contract']['sections'] ?? [], true), 'studio contract should list handoff summary section');
lcfa_assert_true(in_array('connection_handoff', $data['contract']['sections'] ?? [], true), 'studio contract should list connection handoff section');
lcfa_assert_true(in_array('block_pattern_library', $data['contract']['sections'] ?? [], true), 'studio contract should list block pattern library section');
lcfa_assert_true(in_array('native_pattern_page_blueprints', $data['contract']['sections'] ?? [], true), 'studio contract should list native pattern page blueprints section');
lcfa_assert_true(in_array('agent_handoff_package', $data['contract']['sections'] ?? [], true), 'studio contract should list agent handoff package section');
lcfa_assert_same(2, $data['contract']['limits']['runs'] ?? 0, 'studio contract should expose requested run limit');
lcfa_assert_true(!empty($data['contract']['readiness']['setup_complete']), 'studio contract should expose readiness flags');
lcfa_assert_same(6, $data['summary']['abilities'] ?? 0, 'studio summary should expose ability count');
lcfa_assert_same(5, $data['summary']['mcp_public'] ?? 0, 'studio summary should expose MCP-public count');
lcfa_assert_same(1, $data['summary']['public_writes'] ?? 0, 'studio summary should expose public write count');
lcfa_assert_same(2, $data['summary']['runs'] ?? 0, 'studio summary should respect requested run limit');
lcfa_assert_same(1, $data['summary']['run_errors'] ?? 0, 'studio summary should count failed recent runs');
lcfa_assert_same(1, $data['summary']['rollbacks'] ?? 0, 'studio summary should count rollback-ready runs');
lcfa_assert_true(!empty($data['summary']['mcp_adapter_ready']), 'studio summary should expose MCP Adapter readiness');
lcfa_assert_true(!empty($data['summary']['ai_text_ready']), 'studio summary should expose AI text readiness');
lcfa_assert_true(!empty($data['summary']['mcp_write_master_enabled']), 'studio summary should expose MCP write master state');
lcfa_assert_same('diagnostics', $data['ability_manifest']['source'] ?? '', 'studio ability manifest should fall back to diagnostics without registry manifest access');
lcfa_assert_same(5, $data['ability_manifest']['counts']['items'] ?? 0, 'studio ability manifest should expose compact ability entries');
lcfa_assert_same(4, $data['ability_manifest']['counts']['public'] ?? 0, 'studio ability manifest should count MCP-public entries');
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
lcfa_assert_same(10, $data['agent_smoke_tests']['counts']['total'] ?? 0, 'studio smoke tests should expose all planned checks');
lcfa_assert_same(5, $data['agent_smoke_tests']['counts']['available'] ?? 0, 'studio smoke tests should count available fallback abilities');
lcfa_assert_same(2, $data['agent_smoke_tests']['counts']['write_guarded'] ?? 0, 'studio smoke tests should count guarded write checks');
lcfa_assert_same('livecanvas-forge-ai/get-snapshot', $data['agent_smoke_tests']['tests'][0]['ability'] ?? '', 'studio smoke tests should start with snapshot');
lcfa_assert_true(!empty($data['agent_smoke_tests']['tests'][0]['available']), 'studio smoke tests should mark available abilities');
$smoke_tests = array_column($data['agent_smoke_tests']['tests'] ?? [], null, 'id');
lcfa_assert_true(!empty($smoke_tests['write_guard']['public_write_exposed']), 'studio smoke tests should flag exposed write guards');
lcfa_assert_same('livecanvas-forge-ai/apply-native-pattern-page', $smoke_tests['native_pattern_page_apply_guard']['ability'] ?? '', 'studio smoke tests should include native apply guard ability');
lcfa_assert_true(empty($smoke_tests['native_pattern_page_apply_guard']['public_write_exposed']), 'studio native apply guard should not be marked public when it is not exposed');
lcfa_assert_true(str_contains($smoke_tests['native_pattern_page_apply_guard']['expected'] ?? '', 'Do not execute automatically'), 'studio native apply guard should warn agents not to execute automatically');
lcfa_assert_true(!str_contains(json_encode($data['agent_smoke_tests']), 'secret rollback HTML'), 'studio smoke tests should not expose rollback payload content');
lcfa_assert_same('LiveCanvas Forge AI Agent Runbook', $data['agent_runbook']['title'] ?? '', 'studio agent runbook should expose a title');
lcfa_assert_same('markdown', $data['agent_runbook']['format'] ?? '', 'studio agent runbook should expose markdown format');
lcfa_assert_true(($data['agent_runbook']['line_count'] ?? 0) > 10, 'studio agent runbook should expose a non-empty markdown document');
lcfa_assert_true(str_contains($data['agent_runbook']['markdown'] ?? '', 'Smoke Test Order'), 'studio agent runbook should include smoke test order');
lcfa_assert_true(str_contains($data['agent_runbook']['markdown'] ?? '', 'get_snapshot'), 'studio agent runbook should include read-only ability guidance');
lcfa_assert_true(str_contains($data['agent_runbook']['markdown'] ?? '', 'apply-native-pattern-page'), 'studio agent runbook should include native apply guard guidance');
lcfa_assert_true(!str_contains($data['agent_runbook']['markdown'] ?? '', 'secret rollback HTML'), 'studio agent runbook should not expose rollback payload content');
lcfa_assert_same('blocked', $data['handoff_readiness']['status'] ?? '', 'studio handoff readiness should expose blocked status when required gates fail');
lcfa_assert_same('read_only_only', $data['handoff_readiness']['recommended_mode'] ?? '', 'studio handoff readiness should recommend read-only mode when blocked');
lcfa_assert_true(($data['handoff_readiness']['score'] ?? 100) < 60, 'studio handoff readiness should lower score for missing gates');
lcfa_assert_same(8, $data['handoff_readiness']['counts']['gates'] ?? 0, 'studio handoff readiness should expose gate count');
lcfa_assert_same(5, $data['handoff_readiness']['counts']['read_only_required'] ?? 0, 'studio handoff readiness should derive read-only gate size from smoke tests');
lcfa_assert_same(3, $data['handoff_readiness']['counts']['read_only_available'] ?? 0, 'studio handoff readiness should count available read-only smoke tests');
lcfa_assert_same(3, $data['handoff_readiness']['counts']['preview_required'] ?? 0, 'studio handoff readiness should derive preview gate size from smoke tests');
lcfa_assert_same(1, $data['handoff_readiness']['counts']['preview_available'] ?? 0, 'studio handoff readiness should count native page preview availability');
lcfa_assert_same(2, $data['handoff_readiness']['counts']['write_guard_required'] ?? 0, 'studio handoff readiness should derive guarded write gate size from smoke tests');
lcfa_assert_same(1, $data['handoff_readiness']['counts']['write_guard_available'] ?? 0, 'studio handoff readiness should count available guarded write tests');
$connection_handoff = is_array($data['connection_handoff'] ?? null) ? $data['connection_handoff'] : [];
lcfa_assert_same('connection-handoff.v1', $connection_handoff['schema_version'] ?? '', 'studio connection handoff should expose schema version');
lcfa_assert_same('codex', $connection_handoff['client'] ?? '', 'studio connection handoff should expose selected agent client');
lcfa_assert_same('remote', $connection_handoff['mode'] ?? '', 'studio connection handoff should expose selected connection mode');
lcfa_assert_same('wordpress_mcp_adapter', $connection_handoff['transport'] ?? '', 'studio connection handoff should prefer WordPress MCP Adapter transport for remote Codex');
lcfa_assert_same('livecanvas-forge-ai/get-connection-handoff', $connection_handoff['agent_start_tool'] ?? '', 'studio connection handoff should start remote Codex with the lightweight WordPress Ability handoff tool');
lcfa_assert_same('livecanvas-forge-ai/get-connection-handoff', $connection_handoff['connection_handoff_tool'] ?? '', 'studio connection handoff should expose the lightweight WordPress Ability handoff tool');
lcfa_assert_same('livecanvas-forge-ai/get-agent-handoff-package', $connection_handoff['handoff_package_tool'] ?? '', 'studio connection handoff should keep the full WordPress Ability package tool available');
lcfa_assert_true(str_contains($connection_handoff['agent_start_prompt'] ?? '', 'livecanvas-forge-ai/get-connection-handoff'), 'studio connection handoff prompt should start with the lightweight connection handoff ability');
lcfa_assert_true(str_contains($connection_handoff['agent_start_prompt'] ?? '', 'livecanvas-forge-ai/get-agent-handoff-package'), 'studio connection handoff prompt should mention the full handoff package as a follow-up');
lcfa_assert_true(str_contains($connection_handoff['agent_start_prompt'] ?? '', 'get_snapshot'), 'studio connection handoff prompt should still instruct read-only smoke checks');
lcfa_assert_true(!empty($data['operator_briefing']['agent_prompt']) && str_contains($data['operator_briefing']['agent_prompt'], 'livecanvas-forge-ai/get-connection-handoff'), 'studio briefing should reuse the connection handoff prompt');
$block_pattern_library = is_array($data['block_pattern_library'] ?? null) ? $data['block_pattern_library'] : [];
lcfa_assert_same('block-pattern-library.v1', $block_pattern_library['schema_version'] ?? '', 'studio block pattern library should expose schema version');
lcfa_assert_same('wordpress_block_patterns', $block_pattern_library['export']['format'] ?? '', 'studio block pattern library should expose export format');
lcfa_assert_same(1, $block_pattern_library['counts']['patterns'] ?? 0, 'studio block pattern library should expose pattern counts');
lcfa_assert_same(64, strlen((string) ($block_pattern_library['patterns'][0]['sha256'] ?? '')), 'studio block pattern library should expose pattern checksums');
$native_pattern_page_blueprints = is_array($data['native_pattern_page_blueprints'] ?? null) ? $data['native_pattern_page_blueprints'] : [];
lcfa_assert_same('native-pattern-page-blueprints.v1', $native_pattern_page_blueprints['schema_version'] ?? '', 'studio native pattern page blueprints should expose schema version');
lcfa_assert_same(1, $native_pattern_page_blueprints['counts']['blueprints'] ?? 0, 'studio native pattern page blueprints should expose blueprint counts');
lcfa_assert_same('test-landing', $native_pattern_page_blueprints['blueprints'][0]['id'] ?? '', 'studio native pattern page blueprints should expose recipe ids');
lcfa_assert_same('preview_native_pattern_page', $native_pattern_page_blueprints['preview_tool'] ?? '', 'studio native pattern page blueprints should expose the preview MCP tool');
lcfa_assert_same('POST', $native_pattern_page_blueprints['blueprints'][0]['preview_request']['method'] ?? '', 'studio native pattern page blueprints should expose preview request method');
$handoff_blocker_codes = array_column($data['handoff_readiness']['blockers'] ?? [], 'id');
lcfa_assert_true(in_array('read_only_smoke_tests', $handoff_blocker_codes, true), 'studio handoff readiness should flag missing read-only smoke tests');
lcfa_assert_true(in_array('preview_smoke_tests', $handoff_blocker_codes, true), 'studio handoff readiness should flag missing preview smoke tests');
$handoff_warning_codes = array_column($data['handoff_readiness']['warnings'] ?? [], 'id');
lcfa_assert_true(in_array('mcp_write_exposure', $handoff_warning_codes, true), 'studio handoff readiness should warn about MCP write exposure');
lcfa_assert_true(in_array('write_guard_smoke_tests', $handoff_warning_codes, true), 'studio handoff readiness should warn about incomplete guarded write smoke tests');
lcfa_assert_same('handoff-summary.v1', $data['handoff_summary']['schema_version'] ?? '', 'studio handoff summary should expose schema version');
lcfa_assert_same('forge_studio', $data['handoff_summary']['source'] ?? '', 'studio handoff summary should expose source');
lcfa_assert_same($data['handoff_readiness']['status'] ?? '', $data['handoff_summary']['status'] ?? 'missing', 'studio handoff summary should mirror readiness status');
lcfa_assert_same($data['handoff_readiness']['score'] ?? -1, $data['handoff_summary']['score'] ?? 0, 'studio handoff summary should mirror readiness score');
lcfa_assert_same('resolve_blockers', $data['handoff_summary']['next_action'] ?? '', 'studio handoff summary should expose next action');
lcfa_assert_same(5, count($data['handoff_summary']['unavailable_tests'] ?? []), 'studio handoff summary should list unavailable tests');
lcfa_assert_same(2, count($data['handoff_summary']['write_guard_tests'] ?? []), 'studio handoff summary should list write guards');
lcfa_assert_same(1, $data['agent_handoff_package']['package_version'] ?? 0, 'studio handoff package should expose a package version');
lcfa_assert_same('virtual_files', $data['agent_handoff_package']['format'] ?? '', 'studio handoff package should expose virtual file format');
lcfa_assert_same('blocked', $data['agent_handoff_package']['status'] ?? '', 'studio handoff package should mirror readiness status');
lcfa_assert_same('read_only_only', $data['agent_handoff_package']['recommended_mode'] ?? '', 'studio handoff package should mirror recommended mode');
lcfa_assert_true(($data['agent_handoff_package']['summary']['files'] ?? 0) >= 6, 'studio handoff package should include all logical files');
lcfa_assert_same(64, strlen($data['agent_handoff_package']['summary']['checksum'] ?? ''), 'studio handoff package should expose a sha256 checksum');
lcfa_assert_same($data['handoff_readiness']['score'] ?? 0, $data['agent_handoff_package']['summary']['readiness_score'] ?? -1, 'studio handoff package summary should mirror readiness score');
lcfa_assert_same(2, $data['agent_handoff_package']['summary']['blockers'] ?? 0, 'studio handoff package summary should count blockers');
lcfa_assert_same(3, $data['agent_handoff_package']['summary']['warnings'] ?? 0, 'studio handoff package summary should count warnings');
lcfa_assert_same(5, $data['agent_handoff_package']['summary']['unavailable_tests'] ?? 0, 'studio handoff package summary should count unavailable smoke tests');
lcfa_assert_same(2, $data['agent_handoff_package']['summary']['write_guards'] ?? 0, 'studio handoff package summary should count write guards');
$package_paths = array_column($data['agent_handoff_package']['files'] ?? [], 'path');
lcfa_assert_true(in_array('forge-agent-start-prompt.txt', $package_paths, true), 'studio handoff package should include first prompt file');
lcfa_assert_true(in_array('forge-handoff-summary.json', $package_paths, true), 'studio handoff package should include compact handoff summary file');
lcfa_assert_true(in_array('forge-connection-handoff.json', $package_paths, true), 'studio handoff package should include connection handoff file');
lcfa_assert_true(in_array('forge-block-pattern-library.json', $package_paths, true), 'studio handoff package should include block pattern library file');
lcfa_assert_true(in_array('forge-native-pattern-page-blueprints.json', $package_paths, true), 'studio handoff package should include native pattern page blueprints file');
lcfa_assert_true(in_array('forge-agent-runbook.md', $package_paths, true), 'studio handoff package should include the agent runbook file');
lcfa_assert_true(in_array('forge-agent-smoke-tests.json', $package_paths, true), 'studio handoff package should include smoke tests file');
lcfa_assert_true(in_array('forge-mcp-write-policy.json', $package_paths, true), 'studio handoff package should include write policy file');
lcfa_assert_same(64, strlen($data['agent_handoff_package']['files'][0]['sha256'] ?? ''), 'studio handoff package files should include sha256 hashes');
$summary_file_index = array_search('forge-handoff-summary.json', $package_paths, true);
lcfa_assert_true($summary_file_index !== false, 'studio handoff summary file should be indexed');
$handoff_summary_file = json_decode((string) ($data['agent_handoff_package']['files'][$summary_file_index]['content'] ?? ''), true);
lcfa_assert_same('handoff-summary.v1', $handoff_summary_file['schema_version'] ?? '', 'studio handoff summary file should expose schema version');
lcfa_assert_same('forge_studio', $handoff_summary_file['source'] ?? '', 'studio handoff summary file should expose Studio source');
lcfa_assert_same('resolve_blockers', $handoff_summary_file['next_action'] ?? '', 'studio handoff summary file should expose next action');
lcfa_assert_same(5, count($handoff_summary_file['unavailable_tests'] ?? []), 'studio handoff summary file should list unavailable tests');
lcfa_assert_same(2, count($handoff_summary_file['write_guard_tests'] ?? []), 'studio handoff summary file should list write guard tests');
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

$summary_response = $api->get_studio_handoff_summary(new WP_REST_Request(['limit' => 2]));
$summary_data = $summary_response->get_data();
lcfa_assert_same(200, $summary_response->get_status(), 'studio handoff summary endpoint should return HTTP 200');
lcfa_assert_same('studio.handoff-summary.v1', $summary_data['studio']['schema_version'] ?? '', 'studio handoff summary endpoint should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio', $summary_data['studio']['source_route'] ?? '', 'studio handoff summary endpoint should expose source Studio route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/handoff-summary', $summary_data['studio']['rest_route'] ?? '', 'studio handoff summary endpoint should expose its route');
lcfa_assert_same('studio.handoff-summary.v1', $summary_data['contract']['schema_version'] ?? '', 'studio handoff summary endpoint should expose contract schema');
lcfa_assert_same(64, strlen($summary_data['contract']['fingerprint'] ?? ''), 'studio handoff summary endpoint should expose payload fingerprint');
lcfa_assert_same(2, $summary_data['contract']['limits']['runs'] ?? 0, 'studio handoff summary endpoint should respect requested run limit');
lcfa_assert_same($data['handoff_summary']['score'] ?? -1, $summary_data['handoff_summary']['score'] ?? 0, 'studio handoff summary endpoint should return same score');
lcfa_assert_true(!isset($summary_data['agent_handoff_package']), 'studio handoff summary endpoint should not return the larger virtual package');
lcfa_assert_true(!str_contains(json_encode($summary_data), 'secret rollback HTML'), 'studio handoff summary endpoint should not expose rollback payload content');

$connection_response = $api->get_studio_connection_handoff(new WP_REST_Request(['limit' => 2]));
$connection_data = $connection_response->get_data();
lcfa_assert_same(200, $connection_response->get_status(), 'studio connection handoff endpoint should return HTTP 200');
lcfa_assert_same('studio.connection-handoff.v1', $connection_data['studio']['schema_version'] ?? '', 'studio connection handoff endpoint should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio', $connection_data['studio']['source_route'] ?? '', 'studio connection handoff endpoint should expose source Studio route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/connection-handoff', $connection_data['studio']['rest_route'] ?? '', 'studio connection handoff endpoint should expose its route');
lcfa_assert_same('studio.connection-handoff.v1', $connection_data['contract']['schema_version'] ?? '', 'studio connection handoff endpoint should expose contract schema');
lcfa_assert_same(64, strlen($connection_data['contract']['fingerprint'] ?? ''), 'studio connection handoff endpoint should expose payload fingerprint');
lcfa_assert_same(2, $connection_data['contract']['limits']['runs'] ?? 0, 'studio connection handoff endpoint should respect requested run limit');
lcfa_assert_same($data['connection_handoff']['agent_start_tool'] ?? '', $connection_data['connection_handoff']['agent_start_tool'] ?? 'missing', 'studio connection handoff endpoint should return same start tool');
lcfa_assert_true(!isset($connection_data['agent_handoff_package']), 'studio connection handoff endpoint should not return the larger virtual package');

$library_response = $api->get_studio_block_pattern_library(new WP_REST_Request(['limit' => 2]));
$library_data = $library_response->get_data();
lcfa_assert_same(200, $library_response->get_status(), 'studio block pattern library endpoint should return HTTP 200');
lcfa_assert_same('studio.block-pattern-library.v1', $library_data['studio']['schema_version'] ?? '', 'studio block pattern library endpoint should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio', $library_data['studio']['source_route'] ?? '', 'studio block pattern library endpoint should expose source Studio route');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/block-pattern-library', $library_data['studio']['rest_route'] ?? '', 'studio block pattern library endpoint should expose its route');
lcfa_assert_same('studio.block-pattern-library.v1', $library_data['contract']['schema_version'] ?? '', 'studio block pattern library endpoint should expose contract schema');
lcfa_assert_same(64, strlen($library_data['contract']['fingerprint'] ?? ''), 'studio block pattern library endpoint should expose payload fingerprint');
lcfa_assert_same(2, $library_data['contract']['limits']['runs'] ?? 0, 'studio block pattern library endpoint should respect requested run limit');
lcfa_assert_same($data['block_pattern_library']['counts']['patterns'] ?? -1, $library_data['block_pattern_library']['counts']['patterns'] ?? 'missing', 'studio block pattern library endpoint should return same pattern count');
lcfa_assert_true(!isset($library_data['agent_handoff_package']), 'studio block pattern library endpoint should not return the larger virtual package');

$metadata_library_response = $api->get_studio_block_pattern_library(new WP_REST_Request(['limit' => 2, 'include_content' => false]));
$metadata_library_data = $metadata_library_response->get_data();
lcfa_assert_same(200, $metadata_library_response->get_status(), 'studio block pattern metadata endpoint should return HTTP 200');
lcfa_assert_true(empty($metadata_library_data['contract']['include_content']), 'studio block pattern library endpoint should expose metadata-only contract state');
lcfa_assert_true(empty($metadata_library_data['block_pattern_library']['include_content']), 'studio block pattern library endpoint should pass metadata-only mode to the payload');
lcfa_assert_true(!isset($metadata_library_data['block_pattern_library']['patterns'][0]['content']), 'studio block pattern metadata endpoint should omit full pattern content');

$blueprints_response = $api->get_studio_native_pattern_page_blueprints(new WP_REST_Request([]));
$blueprints_data = $blueprints_response->get_data();
lcfa_assert_same(200, $blueprints_response->get_status(), 'studio native pattern page blueprints endpoint should return HTTP 200');
lcfa_assert_same('studio.native-pattern-page-blueprints.v1', $blueprints_data['studio']['schema_version'] ?? '', 'studio native pattern page blueprints endpoint should expose schema version');
lcfa_assert_same('https://example.test/wp-json/lcfa/v1/studio/native-pattern-page-blueprints', $blueprints_data['studio']['rest_route'] ?? '', 'studio native pattern page blueprints endpoint should expose its route');
lcfa_assert_same('studio.native-pattern-page-blueprints.v1', $blueprints_data['contract']['schema_version'] ?? '', 'studio native pattern page blueprints endpoint should expose contract schema');
lcfa_assert_same(64, strlen($blueprints_data['contract']['fingerprint'] ?? ''), 'studio native pattern page blueprints endpoint should expose payload fingerprint');
lcfa_assert_same($data['native_pattern_page_blueprints']['counts']['blueprints'] ?? -1, $blueprints_data['native_pattern_page_blueprints']['counts']['blueprints'] ?? 'missing', 'studio native pattern page blueprints endpoint should return same blueprint count');
lcfa_assert_true(!isset($blueprints_data['agent_handoff_package']), 'studio native pattern page blueprints endpoint should not return the larger virtual package');

$metadata_blueprints_response = $api->get_studio_native_pattern_page_blueprints(new WP_REST_Request(['include_patterns' => false]));
$metadata_blueprints_data = $metadata_blueprints_response->get_data();
lcfa_assert_same(200, $metadata_blueprints_response->get_status(), 'studio native pattern page blueprint metadata endpoint should return HTTP 200');
lcfa_assert_true(empty($metadata_blueprints_data['contract']['include_patterns']), 'studio native pattern page blueprints endpoint should expose metadata-only contract state');
lcfa_assert_true(empty($metadata_blueprints_data['native_pattern_page_blueprints']['include_patterns']), 'studio native pattern page blueprints endpoint should pass metadata-only mode to the payload');
lcfa_assert_true(!isset($metadata_blueprints_data['native_pattern_page_blueprints']['blueprints'][0]['pattern_names']), 'studio native pattern page blueprint metadata endpoint should omit pattern names');

$native_preview_response = $api->preview_studio_native_pattern_page(new WP_REST_Request([
    'title' => 'Native Pattern Page',
    'pattern_names' => ['test-pattern'],
]));
$native_preview_data = $native_preview_response->get_data();
lcfa_assert_same(200, $native_preview_response->get_status(), 'studio native pattern page preview endpoint should return HTTP 200');
lcfa_assert_true(!empty($native_preview_data['native_pattern_page_preview']['ok']), 'studio native pattern page preview endpoint should return a preview payload');
lcfa_assert_same('native-pattern-page-preview.v1', $native_preview_data['native_pattern_page_preview']['schema_version'] ?? '', 'studio native pattern page preview endpoint should expose schema version');
lcfa_assert_same('wordpress_blocks', $native_preview_data['native_pattern_page_preview']['page']['content_format'] ?? '', 'studio native pattern page preview endpoint should expose block content format');

$native_apply_response = $api->apply_studio_native_pattern_page(new WP_REST_Request([
    'title' => 'Native Pattern Page',
    'blueprint' => 'test-landing',
]));
$native_apply_data = $native_apply_response->get_data();
lcfa_assert_same(200, $native_apply_response->get_status(), 'studio native pattern page apply endpoint should return HTTP 200');
lcfa_assert_true(!empty($native_apply_data['native_pattern_page_apply']['ok']), 'studio native pattern page apply endpoint should return an apply payload');
lcfa_assert_same('native-pattern-page-apply.v1', $native_apply_data['native_pattern_page_apply']['schema_version'] ?? '', 'studio native pattern page apply endpoint should expose schema version');
lcfa_assert_same(456, $native_apply_data['native_pattern_page_apply']['page']['id'] ?? 0, 'studio native pattern page apply endpoint should expose created page id');

echo "PASS\n";
