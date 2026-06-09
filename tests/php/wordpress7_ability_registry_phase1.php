<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-wp7-abilities/');
define('LCFA_VERSION', 'test');

$GLOBALS['lcfa_test_actions'] = [];
$GLOBALS['lcfa_test_ability_categories'] = [];
$GLOBALS['lcfa_test_abilities'] = [];
$GLOBALS['lcfa_test_connections'] = [
    'mcp_write_abilities_enabled' => false,
    'mcp_public_write_abilities' => [],
];

function __($text, $domain = null) { return $text; }
function add_action($hook, $callback) { $GLOBALS['lcfa_test_actions'][$hook] = $callback; }
function wp_register_ability_category($name, array $definition) { $GLOBALS['lcfa_test_ability_categories'][$name] = $definition; }
function wp_register_ability($name, array $definition) { $GLOBALS['lcfa_test_abilities'][$name] = $definition; }
function current_user_can($capability) { return in_array($capability, ['edit_pages', 'manage_options'], true); }
function absint($value) { return max(0, (int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }

final class LCFA_Settings {
    public static function get_connections(): array {
        return $GLOBALS['lcfa_test_connections'];
    }

    public static function get_public_connections(): array {
        return ['preferred_client' => 'codex'];
    }

    public static function get_history(): array {
        return [
            [
                'time' => '2026-05-27 12:00:00',
                'audit_id' => 'audit-test',
                'action' => 'page_upsert',
                'mode' => 'apply',
                'ok' => true,
                'message' => 'Applied.',
                'summary' => 'Update page #123.',
                'target_type' => 'page',
                'target_id' => 123,
                'target_title' => 'Page',
                'rollback_available' => true,
                'execution_target' => 'local',
                'origin' => 'wp_ability',
                'processed_by' => 'wp_ability_apply_page_upsert',
            ],
        ];
    }

    public static function get_mcp_write_ability_options(): array {
        return [
            'livecanvas-forge-ai/apply-page-upsert' => ['label' => 'Apply page upsert'],
            'livecanvas-forge-ai/apply-native-pattern-page' => ['label' => 'Apply native pattern page'],
            'livecanvas-forge-ai/apply-global-shell' => ['label' => 'Apply global shell'],
            'livecanvas-forge-ai/apply-dynamic-template' => ['label' => 'Apply dynamic template'],
            'livecanvas-forge-ai/apply-design-system' => ['label' => 'Apply design system'],
            'livecanvas-forge-ai/restore-audit-rollback' => ['label' => 'Restore audit rollback'],
        ];
    }

    public static function sanitize_mcp_write_abilities($abilities): array {
        $allowed = array_keys(self::get_mcp_write_ability_options());
        $abilities = is_array($abilities) ? $abilities : [];

        return array_values(array_intersect($allowed, array_values(array_unique(array_map('strval', $abilities)))));
    }
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return ['detected_framework' => 'picostrap', 'site_mode' => 'local'];
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

final class LCFA_Inventory {
    public function get_inventory(): array {
        return ['summary' => ['pages' => 1]];
    }
}

final class LCFA_Context_Builder {
    public function get_mcp_status(): array {
        return ['enabled' => true];
    }

    public function build_context(array $args = []): array {
        return ['args' => $args];
    }

    public function get_theme_context(array $args = []): array {
        return ['theme' => 'picostrap', 'args' => $args];
    }

    public function get_page_html(int $post_id): array {
        return ['post' => ['id' => $post_id], 'content' => '<section>Test</section>'];
    }
}

final class LCFA_Command_Deck {
    public function get_actions(): array {
        return ['site_audit' => ['label' => 'Run site audit']];
    }

    public function execute(array $payload): array {
        return [
            'ok'      => true,
            'action'  => $payload['action'] ?? '',
            'mode'    => !empty($payload['dry_run']) ? 'preview' : 'apply',
            'origin'  => $payload['_lcfa_origin'] ?? '',
        ];
    }
}

final class LCFA_WindPress_Bridge {
    public function get_status(): array {
        return ['available' => true, 'tailwind_version' => 4];
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-ai-client.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-ability-registry.php';

function lcfa_wp7_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$registry = new LCFA_Ability_Registry(
    new LCFA_Environment(),
    new LCFA_Inventory(),
    new LCFA_Context_Builder(),
    new LCFA_Command_Deck(),
    new LCFA_WindPress_Bridge(),
    new LCFA_AI_Client()
);

$registry->hooks();
lcfa_wp7_assert_true(isset($GLOBALS['lcfa_test_actions']['wp_abilities_api_categories_init']), 'registry should hook ability category registration');
lcfa_wp7_assert_true(isset($GLOBALS['lcfa_test_actions']['wp_abilities_api_init']), 'registry should hook ability registration');
lcfa_wp7_assert_true(isset($GLOBALS['lcfa_test_actions']['mcp_adapter_init']), 'registry should hook MCP Adapter registration');

$registry->register_categories();
lcfa_wp7_assert_true(isset($GLOBALS['lcfa_test_ability_categories']['livecanvas-forge-ai']), 'registry should register the Forge ability category');

$registry->register_abilities();
lcfa_wp7_assert_true(count($GLOBALS['lcfa_test_abilities']) === 32, 'registry should register read, runs, connection handoff, handoff summary, handoff package, preview, dedicated apply, rollback, diagnostics, block pattern, native pattern page, blueprint, native page apply, and AI Client abilities');

foreach ($GLOBALS['lcfa_test_abilities'] as $name => $definition) {
    lcfa_wp7_assert_true(strpos($name, 'livecanvas-forge-ai/') === 0, 'ability names should use the Forge namespace');
    lcfa_wp7_assert_true(is_callable($definition['execute_callback']), 'ability execute callback should be callable');
    lcfa_wp7_assert_true(is_callable($definition['permission_callback']), 'ability permission callback should be callable');
}

lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-snapshot']['meta']['mcp']['public']), 'snapshot should be public to MCP by default');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-ai-client-status']['meta']['mcp']['public']), 'AI Client status should be public to MCP by default');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-ability-diagnostics']['meta']['mcp']['public']), 'ability diagnostics should be public to MCP by default');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-block-patterns']['meta']['mcp']['public']), 'block pattern manifest should be public to MCP by default');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-block-pattern-library']['meta']['mcp']['public']), 'block pattern library should be public to MCP by default because it is read-only');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-native-pattern-page-blueprints']['meta']['mcp']['public']), 'native page blueprints should be public to MCP by default because they are read-only');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-runs']['meta']['mcp']['public']), 'run history should be public to MCP by default because it omits rollback content');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-connection-handoff']['meta']['mcp']['public']), 'connection handoff should be public to MCP by default because it is read-only and sanitized');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-handoff-summary']['meta']['mcp']['public']), 'handoff summary should be public to MCP by default because it is read-only and sanitized');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/get-agent-handoff-package']['meta']['mcp']['public']), 'agent handoff package should be public to MCP by default because it is read-only and sanitized');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/validate-markup-for-framework']['meta']['annotations']['readonly']), 'framework validation should remain a read-only preview ability');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-page-upsert']['meta']['mcp']['public']), 'page preview should be public to MCP because it forces dry-run');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-global-shell']['meta']['mcp']['public']), 'global shell preview should be public to MCP because it forces dry-run');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-design-system']['meta']['mcp']['public']), 'design-system preview should be public to MCP because it forces dry-run');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-block-pattern']['meta']['mcp']['public']), 'block pattern preview should be public to MCP because it never writes');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-native-pattern-page']['meta']['mcp']['public']), 'native pattern page preview should be public to MCP because it never writes');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/generate-ai-text']['meta']['mcp']['public']), 'AI text generation should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/preview-command']['meta']['mcp']['public']), 'generic command preview should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-command']['meta']['mcp']['public']), 'apply command should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-page-upsert']['meta']['mcp']['public']), 'page apply should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-native-pattern-page']['meta']['mcp']['public']), 'native pattern page apply should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-global-shell']['meta']['mcp']['public']), 'global shell apply should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-dynamic-template']['meta']['mcp']['public']), 'dynamic template apply should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-design-system']['meta']['mcp']['public']), 'design-system apply should not be public on MCP by default');
lcfa_wp7_assert_true(empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/restore-audit-rollback']['meta']['mcp']['public']), 'audit rollback restore should not be public on MCP by default');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-command']['meta']['annotations']['destructive']), 'apply command should be marked as destructive');
lcfa_wp7_assert_true(!empty($GLOBALS['lcfa_test_abilities']['livecanvas-forge-ai/apply-page-upsert']['meta']['annotations']['destructive']), 'dedicated apply abilities should be marked as destructive');

$snapshot = $registry->get_snapshot();
lcfa_wp7_assert_true(($snapshot['snapshot']['detected_framework'] ?? '') === 'picostrap', 'snapshot ability should return environment data');

$page = $registry->get_page_html(['post_id' => 123]);
lcfa_wp7_assert_true(($page['page_html']['post']['id'] ?? 0) === 123, 'page HTML ability should pass the post ID through');

$ai_status = $registry->get_ai_client_status();
lcfa_wp7_assert_true(empty($ai_status['ai_client']['available']), 'AI Client status should gracefully report unavailable when wp_ai_client_prompt is missing');

$diagnostics = $registry->get_ability_diagnostics();
lcfa_wp7_assert_true(($diagnostics['ability_diagnostics']['total'] ?? 0) === 32, 'ability diagnostics should expose the full ability count');
lcfa_wp7_assert_true(($diagnostics['ability_diagnostics']['mcp_public_total'] ?? 0) === 23, 'ability diagnostics should expose the MCP-public ability count');
lcfa_wp7_assert_true(empty($diagnostics['ability_diagnostics']['has_mcp_public_write']), 'ability diagnostics should confirm no write ability is MCP-public');
lcfa_wp7_assert_true(empty($diagnostics['ability_diagnostics']['mcp_write_opt_in_enabled']), 'ability diagnostics should report disabled write opt-in by default');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/preview-page-upsert', $diagnostics['ability_diagnostics']['mcp_public_preview'] ?? [], true), 'ability diagnostics should list dedicated preview abilities');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-connection-handoff', $diagnostics['ability_diagnostics']['mcp_public'] ?? [], true), 'ability diagnostics should list the connection handoff ability as MCP-public');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-handoff-summary', $diagnostics['ability_diagnostics']['mcp_public'] ?? [], true), 'ability diagnostics should list the handoff summary ability as MCP-public');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-block-pattern-library', $diagnostics['ability_diagnostics']['mcp_public'] ?? [], true), 'ability diagnostics should list the block pattern library ability as MCP-public');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-native-pattern-page-blueprints', $diagnostics['ability_diagnostics']['mcp_public'] ?? [], true), 'ability diagnostics should list the native page blueprints ability as MCP-public');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-agent-handoff-package', $diagnostics['ability_diagnostics']['mcp_public'] ?? [], true), 'ability diagnostics should list the handoff package ability as MCP-public');

$GLOBALS['lcfa_test_connections']['mcp_write_abilities_enabled'] = true;
$GLOBALS['lcfa_test_connections']['mcp_public_write_abilities'] = [
    'livecanvas-forge-ai/apply-page-upsert',
];
$write_public = $registry->get_public_mcp_abilities();
lcfa_wp7_assert_true(count($write_public) === 24, 'write opt-in should expose only the selected dedicated write abilities and no generic apply command');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/apply-page-upsert', array_keys($write_public), true), 'write opt-in should expose page apply ability');
lcfa_wp7_assert_true(!in_array('livecanvas-forge-ai/restore-audit-rollback', array_keys($write_public), true), 'write opt-in should not expose unselected rollback restore ability');
lcfa_wp7_assert_true(!in_array('livecanvas-forge-ai/apply-command', array_keys($write_public), true), 'write opt-in should not expose the generic apply command');
$GLOBALS['lcfa_test_connections']['mcp_public_write_abilities'] = array_keys(LCFA_Settings::get_mcp_write_ability_options());
$all_write_public = $registry->get_public_mcp_abilities();
lcfa_wp7_assert_true(count($all_write_public) === 29, 'selecting all write abilities should expose the full dedicated write surface');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/apply-native-pattern-page', array_keys($all_write_public), true), 'selecting native page apply should expose the native page apply ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/restore-audit-rollback', array_keys($all_write_public), true), 'selecting rollback should expose audit rollback restore ability');
$GLOBALS['lcfa_test_connections']['mcp_write_abilities_enabled'] = false;
$GLOBALS['lcfa_test_connections']['mcp_public_write_abilities'] = [];

$block_patterns = $registry->get_block_patterns();
lcfa_wp7_assert_true(empty($block_patterns['block_patterns']['available']), 'block pattern ability should gracefully report unavailable when the pattern registry service is not injected');
$block_pattern_library = $registry->get_block_pattern_library(['include_content' => true]);
lcfa_wp7_assert_true(empty($block_pattern_library['block_pattern_library']['available']), 'block pattern library ability should gracefully report unavailable when the pattern registry service is not injected');
lcfa_wp7_assert_true(!empty($block_pattern_library['block_pattern_library']['include_content']), 'block pattern library ability should preserve the include_content request');
$native_pattern_page_blueprints = $registry->get_native_pattern_page_blueprints(['include_patterns' => false]);
lcfa_wp7_assert_true(empty($native_pattern_page_blueprints['native_pattern_page_blueprints']['available']), 'native page blueprints ability should gracefully report unavailable when the pattern registry service is not injected');
lcfa_wp7_assert_true(empty($native_pattern_page_blueprints['native_pattern_page_blueprints']['include_patterns']), 'native page blueprints ability should preserve the include_patterns request');

$runs = $registry->get_runs(['limit' => 1]);
lcfa_wp7_assert_true(($runs['runs']['count'] ?? 0) === 1, 'runs ability should expose recent command history');
lcfa_wp7_assert_true(!empty($runs['runs']['items'][0]['rollback_available']), 'runs ability should preserve rollback availability without exposing rollback content');

$connection_handoff = $registry->get_connection_handoff(['limit' => 1]);
lcfa_wp7_assert_true(($connection_handoff['connection_handoff']['agent_start_tool'] ?? '') === 'livecanvas-forge-ai/get-connection-handoff', 'connection handoff ability should expose the lightweight WordPress Ability handoff tool');
lcfa_wp7_assert_true(($connection_handoff['connection_handoff']['connection_handoff_tool'] ?? '') === 'livecanvas-forge-ai/get-connection-handoff', 'connection handoff ability should expose its dedicated WordPress Ability tool');
lcfa_wp7_assert_true(($connection_handoff['connection_handoff']['handoff_package_tool'] ?? '') === 'livecanvas-forge-ai/get-agent-handoff-package', 'connection handoff ability should expose the full package tool as a follow-up');
lcfa_wp7_assert_true(str_contains($connection_handoff['connection_handoff']['agent_start_prompt'] ?? '', 'livecanvas-forge-ai/get-connection-handoff'), 'connection handoff ability should expose a first prompt that starts lightweight');
lcfa_wp7_assert_true(str_contains($connection_handoff['connection_handoff']['agent_start_prompt'] ?? '', 'livecanvas-forge-ai/get-agent-handoff-package'), 'connection handoff ability should mention the full package follow-up');
lcfa_wp7_assert_true(!isset($connection_handoff['agent_handoff_package']), 'connection handoff ability should not return the larger virtual package');

$handoff_summary = $registry->get_handoff_summary(['limit' => 1]);
lcfa_wp7_assert_true(($handoff_summary['handoff_summary']['schema_version'] ?? '') === 'handoff-summary.v1', 'handoff summary ability should expose schema version');
lcfa_wp7_assert_true(($handoff_summary['handoff_summary']['source'] ?? '') === 'wordpress_ability', 'handoff summary ability should expose source');
lcfa_wp7_assert_true(isset($handoff_summary['handoff_summary']['score']), 'handoff summary ability should expose readiness score');
lcfa_wp7_assert_true(in_array('native_pattern_page_apply_guard', array_column($handoff_summary['handoff_summary']['write_guard_tests'] ?? [], 'id'), true), 'handoff summary ability should include native apply guard');
lcfa_wp7_assert_true(!isset($handoff_summary['agent_handoff_package']), 'handoff summary ability should not return the larger virtual package');

$handoff = $registry->get_agent_handoff_package(['limit' => 1]);
lcfa_wp7_assert_true(($handoff['agent_handoff_package']['format'] ?? '') === 'virtual_files', 'handoff package ability should expose virtual file format');
lcfa_wp7_assert_true(($handoff['agent_handoff_package']['source'] ?? '') === 'wordpress_ability', 'handoff package ability should expose its ability source');
lcfa_wp7_assert_true(($handoff['connection_handoff']['agent_start_tool'] ?? '') === 'livecanvas-forge-ai/get-connection-handoff', 'handoff package ability should expose the lightweight WordPress Ability start tool');
lcfa_wp7_assert_true(($handoff['connection_handoff']['handoff_package_tool'] ?? '') === 'livecanvas-forge-ai/get-agent-handoff-package', 'handoff package ability should expose the full package follow-up tool');
lcfa_wp7_assert_true(str_contains($handoff['connection_handoff']['agent_start_prompt'] ?? '', 'livecanvas-forge-ai/get-snapshot'), 'handoff package ability should include read-only smoke guidance in the start prompt');
lcfa_wp7_assert_true(empty($handoff['block_pattern_library']['available']), 'handoff package ability should include block pattern library metadata even when the service is unavailable');
lcfa_wp7_assert_true(empty($handoff['native_pattern_page_blueprints']['available']), 'handoff package ability should include native page blueprint metadata even when the service is unavailable');
lcfa_wp7_assert_true(($handoff['agent_handoff_package']['summary']['files'] ?? 0) >= 9, 'handoff package ability should include logical files');
lcfa_wp7_assert_true(strlen($handoff['agent_handoff_package']['summary']['checksum'] ?? '') === 64, 'handoff package ability should expose a package checksum');
lcfa_wp7_assert_true(($handoff['agent_handoff_package']['summary']['status'] ?? '') !== '', 'handoff package ability should expose compact handoff status');
lcfa_wp7_assert_true(($handoff['agent_handoff_package']['summary']['readiness_score'] ?? -1) >= 0, 'handoff package ability should expose compact readiness score');
$handoff_paths = array_column($handoff['agent_handoff_package']['files'] ?? [], 'path');
lcfa_wp7_assert_true(in_array('forge-agent-start-prompt.txt', $handoff_paths, true), 'handoff package ability should include a first prompt file');
lcfa_wp7_assert_true(in_array('forge-handoff-summary.json', $handoff_paths, true), 'handoff package ability should include a compact handoff summary file');
lcfa_wp7_assert_true(in_array('forge-connection-handoff.json', $handoff_paths, true), 'handoff package ability should include connection handoff metadata');
lcfa_wp7_assert_true(in_array('forge-block-pattern-library.json', $handoff_paths, true), 'handoff package ability should include block pattern library metadata');
lcfa_wp7_assert_true(in_array('forge-native-pattern-page-blueprints.json', $handoff_paths, true), 'handoff package ability should include native page blueprint metadata');
lcfa_wp7_assert_true(in_array('forge-agent-runbook.md', $handoff_paths, true), 'handoff package ability should include a runbook file');
lcfa_wp7_assert_true(in_array('forge-agent-smoke-tests.json', $handoff_paths, true), 'handoff package ability should include smoke tests file');
$handoff_summary_index = array_search('forge-handoff-summary.json', $handoff_paths, true);
lcfa_wp7_assert_true($handoff_summary_index !== false, 'handoff package ability summary file should be indexed');
$ability_handoff_summary = json_decode((string) ($handoff['agent_handoff_package']['files'][$handoff_summary_index]['content'] ?? ''), true);
lcfa_wp7_assert_true(($ability_handoff_summary['schema_version'] ?? '') === 'handoff-summary.v1', 'handoff package ability summary should expose schema version');
lcfa_wp7_assert_true(($ability_handoff_summary['source'] ?? '') === 'wordpress_ability', 'handoff package ability summary should expose source');
lcfa_wp7_assert_true(in_array('native_pattern_page_apply_guard', array_column($ability_handoff_summary['write_guard_tests'] ?? [], 'id'), true), 'handoff package ability summary should include native apply guard');
lcfa_wp7_assert_true(!str_contains(json_encode($handoff), 'previous_content'), 'handoff package ability should not expose rollback restore payloads');

$preview = $registry->preview_command(['payload' => ['action' => 'site_audit']]);
lcfa_wp7_assert_true(($preview['result']['mode'] ?? '') === 'preview', 'preview ability should force dry-run mode');

$page_preview = $registry->preview_page_upsert(['title' => 'Preview page', 'content' => '<section>Preview</section>']);
lcfa_wp7_assert_true(($page_preview['result']['action'] ?? '') === 'page_upsert', 'page preview ability should force page_upsert action');
lcfa_wp7_assert_true(($page_preview['result']['mode'] ?? '') === 'preview', 'page preview ability should force dry-run mode');

$shell_preview = $registry->preview_global_shell(['header_html' => '<header>Preview</header>']);
lcfa_wp7_assert_true(($shell_preview['result']['action'] ?? '') === 'global_shell_apply', 'global shell preview ability should force global_shell_apply action');
lcfa_wp7_assert_true(($shell_preview['result']['mode'] ?? '') === 'preview', 'global shell preview ability should force dry-run mode');

$design_preview = $registry->preview_design_system(['design_system' => ['colors' => ['primary' => '#111111']]]);
lcfa_wp7_assert_true(($design_preview['result']['action'] ?? '') === 'design_system_apply', 'design-system preview ability should force design_system_apply action');
lcfa_wp7_assert_true(($design_preview['result']['mode'] ?? '') === 'preview', 'design-system preview ability should force dry-run mode');

$block_pattern_preview = $registry->preview_block_pattern(['html' => '<section>Preview</section>', 'title' => 'Preview Pattern']);
lcfa_wp7_assert_true(empty($block_pattern_preview['block_pattern_preview']['ok']), 'block pattern preview should report unavailable when the pattern registry service is not injected');

$native_pattern_page_preview = $registry->preview_native_pattern_page(['title' => 'Native Pattern Page', 'pattern_names' => ['conversion-hero']]);
lcfa_wp7_assert_true(empty($native_pattern_page_preview['native_pattern_page_preview']['ok']), 'native pattern page preview should report unavailable when the pattern registry service is not injected');

$native_pattern_page_apply = $registry->apply_native_pattern_page(['title' => 'Native Pattern Page', 'blueprint' => 'starter-landing']);
lcfa_wp7_assert_true(empty($native_pattern_page_apply['native_pattern_page_apply']['ok']), 'native pattern page apply should report unavailable when the pattern registry service is not injected');

$page_apply = $registry->apply_page_upsert(['title' => 'Apply page', 'content' => '<section>Apply</section>', 'dry_run' => true]);
lcfa_wp7_assert_true(($page_apply['result']['action'] ?? '') === 'page_upsert', 'page apply ability should force page_upsert action');
lcfa_wp7_assert_true(($page_apply['result']['mode'] ?? '') === 'apply', 'page apply ability should force apply mode');

$shell_apply = $registry->apply_global_shell(['header_html' => '<header>Apply</header>', 'dry_run' => true]);
lcfa_wp7_assert_true(($shell_apply['result']['action'] ?? '') === 'global_shell_apply', 'global shell apply ability should force global_shell_apply action');
lcfa_wp7_assert_true(($shell_apply['result']['mode'] ?? '') === 'apply', 'global shell apply ability should force apply mode');

$template_apply = $registry->apply_dynamic_template(['target_id' => 456, 'content' => '<section>Template</section>', 'dry_run' => true]);
lcfa_wp7_assert_true(($template_apply['result']['action'] ?? '') === 'update_dynamic_template', 'dynamic template apply ability should update when target_id is present');
lcfa_wp7_assert_true(($template_apply['result']['mode'] ?? '') === 'apply', 'dynamic template apply ability should force apply mode');

$design_apply = $registry->apply_design_system(['design_system' => ['colors' => ['primary' => '#111111']], 'dry_run' => true]);
lcfa_wp7_assert_true(($design_apply['result']['action'] ?? '') === 'design_system_apply', 'design-system apply ability should force design_system_apply action');
lcfa_wp7_assert_true(($design_apply['result']['mode'] ?? '') === 'apply', 'design-system apply ability should force apply mode');

$rollback_apply = $registry->restore_audit_rollback(['audit_id' => 'audit-test', 'dry_run' => true]);
lcfa_wp7_assert_true(($rollback_apply['result']['action'] ?? '') === 'restore_audit_rollback', 'rollback ability should force restore_audit_rollback action');
lcfa_wp7_assert_true(($rollback_apply['result']['mode'] ?? '') === 'apply', 'rollback ability should force apply mode');

$apply = $registry->apply_command(['payload' => ['action' => 'site_audit', 'dry_run' => true]]);
lcfa_wp7_assert_true(($apply['result']['mode'] ?? '') === 'apply', 'apply ability should force apply mode');

eval('namespace WP\\MCP\\Transport { class HttpTransport {} }');
eval('namespace WP\\MCP\\Infrastructure\\ErrorHandling { class ErrorLogMcpErrorHandler {} }');
eval('namespace WP\\MCP\\Infrastructure\\Observability { class NullMcpObservabilityHandler {} }');

$adapter = new class {
    public array $args = [];

    public function create_server(...$args): void {
        $this->args = $args;
    }
};

$registry->register_mcp_server($adapter);
lcfa_wp7_assert_true(($adapter->args[0] ?? '') === 'livecanvas-forge-ai', 'registry should create a custom Forge MCP server when the adapter is available');
lcfa_wp7_assert_true(($adapter->args[1] ?? '') === 'livecanvas-forge-ai', 'Forge MCP server should use the Forge REST namespace');
lcfa_wp7_assert_true(count($adapter->args[9] ?? []) === 23, 'Forge MCP server should expose public read-only, runs, connection handoff, handoff summary, handoff package, block manifest, block library, native page blueprints, native pattern preview, and dedicated preview abilities');
lcfa_wp7_assert_true(!in_array('livecanvas-forge-ai/apply-command', $adapter->args[9] ?? [], true), 'Forge MCP server must not expose apply-command by default');
lcfa_wp7_assert_true(!in_array('livecanvas-forge-ai/apply-native-pattern-page', $adapter->args[9] ?? [], true), 'Forge MCP server must not expose native pattern page apply by default');
lcfa_wp7_assert_true(!in_array('livecanvas-forge-ai/generate-ai-text', $adapter->args[9] ?? [], true), 'Forge MCP server must not expose arbitrary AI generation by default');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-connection-handoff', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the read-only connection handoff ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-handoff-summary', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the read-only handoff summary ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-block-pattern-library', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the read-only block pattern library ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-native-pattern-page-blueprints', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the read-only native page blueprints ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/get-agent-handoff-package', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the read-only agent handoff package ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/preview-native-pattern-page', $adapter->args[9] ?? [], true), 'Forge MCP server should expose the native pattern page preview ability');
lcfa_wp7_assert_true(in_array('livecanvas-forge-ai/preview-global-shell', $adapter->args[9] ?? [], true), 'Forge MCP server should expose dedicated preview abilities');

echo "PASS\n";
