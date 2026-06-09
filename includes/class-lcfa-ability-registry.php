<?php

defined('ABSPATH') || exit;

final class LCFA_Ability_Registry {
    private const CATEGORY = 'livecanvas-forge-ai';
    private const MCP_SERVER_ID = 'livecanvas-forge-ai';
    private const MCP_NAMESPACE = 'livecanvas-forge-ai';
    private const MCP_ROUTE = 'mcp';

    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_AI_Client $ai_client;
    private ?LCFA_Block_Patterns $block_patterns;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory, LCFA_Context_Builder $context_builder, LCFA_Command_Deck $command_deck, LCFA_WindPress_Bridge $windpress_bridge, LCFA_AI_Client $ai_client, ?LCFA_Block_Patterns $block_patterns = null) {
        $this->environment      = $environment;
        $this->inventory        = $inventory;
        $this->context_builder  = $context_builder;
        $this->command_deck     = $command_deck;
        $this->windpress_bridge = $windpress_bridge;
        $this->ai_client        = $ai_client;
        $this->block_patterns   = $block_patterns;
    }

    public function hooks(): void {
        add_action('wp_abilities_api_categories_init', [$this, 'register_categories']);
        add_action('wp_abilities_api_init', [$this, 'register_abilities']);
        add_action('mcp_adapter_init', [$this, 'register_mcp_server']);
    }

    public function register_categories(): void {
        if (!function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category(self::CATEGORY, [
            'label'       => __('LiveCanvas Forge AI', 'livecanvas-forge-ai'),
            'description' => __('WordPress-native abilities exposed by LiveCanvas Forge AI.', 'livecanvas-forge-ai'),
        ]);
    }

    public function register_abilities(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        foreach ($this->get_ability_manifest() as $name => $definition) {
            wp_register_ability($name, $definition);
        }
    }

    public function register_mcp_server($adapter): void {
        if (!is_object($adapter) || !method_exists($adapter, 'create_server')) {
            return;
        }

        $http_transport = 'WP\\MCP\\Transport\\HttpTransport';
        $error_handler = 'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler';
        $observability_handler = 'WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler';

        if (!class_exists($http_transport) || !class_exists($error_handler) || !class_exists($observability_handler)) {
            return;
        }

        $adapter->create_server(
            self::MCP_SERVER_ID,
            self::MCP_NAMESPACE,
            self::MCP_ROUTE,
            'LiveCanvas Forge AI',
            'WordPress-native MCP server for safe LiveCanvas Forge AI abilities.',
            LCFA_VERSION,
            [$http_transport],
            $error_handler,
            $observability_handler,
            array_keys($this->get_public_mcp_abilities()),
            [],
            [],
            [$this, 'can_read']
        );
    }

    public function get_ability_manifest(): array {
        $readonly_annotations = [
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ];
        $preview_annotations = [
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ];
        $write_annotations = [
            'readonly'    => false,
            'destructive' => true,
            'idempotent'  => false,
        ];
        return [
            'livecanvas-forge-ai/get-snapshot' => $this->ability(
                __('Get Forge snapshot', 'livecanvas-forge-ai'),
                __('Returns the current WordPress, LiveCanvas, connection, and MCP snapshot without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_snapshot'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-inventory' => $this->ability(
                __('Get Forge inventory', 'livecanvas-forge-ai'),
                __('Returns the LiveCanvas-aware inventory of pages, partials, templates, blocks, and sections without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_inventory'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-context' => $this->ability(
                __('Get Forge context', 'livecanvas-forge-ai'),
                __('Returns the full Forge context for the site and optional target post without writing.', 'livecanvas-forge-ai'),
                $this->target_context_schema(),
                [$this, 'get_context'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-theme-context' => $this->ability(
                __('Get Forge theme context', 'livecanvas-forge-ai'),
                __('Returns stack, theme, output rules, ACF, MCP, WindPress, and target context without writing.', 'livecanvas-forge-ai'),
                $this->target_context_schema(),
                [$this, 'get_theme_context'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-page-html' => $this->ability(
                __('Get page HTML', 'livecanvas-forge-ai'),
                __('Returns raw post content and basic post metadata for a WordPress page or post without writing.', 'livecanvas-forge-ai'),
                $this->page_html_schema(),
                [$this, 'get_page_html'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/list-command-actions' => $this->ability(
                __('List Forge command actions', 'livecanvas-forge-ai'),
                __('Returns the supported Forge Command Deck actions and descriptions without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'list_command_actions'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-mcp-status' => $this->ability(
                __('Get Forge MCP status', 'livecanvas-forge-ai'),
                __('Returns MCP bridge, client, filesystem mode, and capability status without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_mcp_status'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-windpress-status' => $this->ability(
                __('Get WindPress status', 'livecanvas-forge-ai'),
                __('Returns WindPress runtime, cache, providers, and handler status without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_windpress_status'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-ai-client-status' => $this->ability(
                __('Get WordPress AI Client status', 'livecanvas-forge-ai'),
                __('Returns WordPress AI Client and connector text-generation availability without sending a prompt.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_ai_client_status'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-ability-diagnostics' => $this->ability(
                __('Get Forge ability diagnostics', 'livecanvas-forge-ai'),
                __('Returns the registered Forge ability inventory, MCP exposure flags, and MCP Adapter status without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_ability_diagnostics'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-block-patterns' => $this->ability(
                __('Get Forge block patterns', 'livecanvas-forge-ai'),
                __('Returns the WordPress-native block and pattern manifest registered by LiveCanvas Forge AI without writing.', 'livecanvas-forge-ai'),
                $this->empty_object_schema(),
                [$this, 'get_block_patterns'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-block-pattern-library' => $this->ability(
                __('Get Forge block pattern library', 'livecanvas-forge-ai'),
                __('Returns export-ready WordPress-native Forge block patterns with checksums for connected agents without writing.', 'livecanvas-forge-ai'),
                $this->block_pattern_library_schema(),
                [$this, 'get_block_pattern_library'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-native-pattern-page-blueprints' => $this->ability(
                __('Get native pattern page blueprints', 'livecanvas-forge-ai'),
                __('Returns no-write WordPress-native page blueprints composed from registered Forge patterns.', 'livecanvas-forge-ai'),
                $this->native_pattern_page_blueprints_schema(),
                [$this, 'get_native_pattern_page_blueprints'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-runs' => $this->ability(
                __('Get Forge runs', 'livecanvas-forge-ai'),
                __('Returns recent Forge command history and rollback availability without exposing stored rollback content.', 'livecanvas-forge-ai'),
                $this->runs_schema(),
                [$this, 'get_runs'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-connection-handoff' => $this->ability(
                __('Get connection handoff', 'livecanvas-forge-ai'),
                __('Returns the first prompt, connection mode, transport, and read-only guardrails for a new Codex or MCP agent session.', 'livecanvas-forge-ai'),
                $this->runs_schema(),
                [$this, 'get_connection_handoff'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-handoff-summary' => $this->ability(
                __('Get handoff summary', 'livecanvas-forge-ai'),
                __('Returns a compact readiness summary for Codex and MCP agents without the full virtual file package.', 'livecanvas-forge-ai'),
                $this->runs_schema(),
                [$this, 'get_handoff_summary'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/get-agent-handoff-package' => $this->ability(
                __('Get agent handoff package', 'livecanvas-forge-ai'),
                __('Returns a copy-ready virtual file package for Codex and MCP agents without writing or exposing rollback payload content.', 'livecanvas-forge-ai'),
                $this->runs_schema(),
                [$this, 'get_agent_handoff_package'],
                [$this, 'can_read'],
                $readonly_annotations,
                true
            ),
            'livecanvas-forge-ai/generate-ai-text' => $this->ability(
                __('Generate text with WordPress AI Client', 'livecanvas-forge-ai'),
                __('Generates server-side text through WordPress AI Client and configured Connectors. This ability is admin-only and not MCP-public by default.', 'livecanvas-forge-ai'),
                $this->ai_text_schema(),
                [$this, 'generate_ai_text'],
                [$this, 'can_manage'],
                [
                    'readonly'    => true,
                    'destructive' => false,
                    'idempotent'  => false,
                ],
                false,
                false
            ),
            'livecanvas-forge-ai/validate-markup-for-framework' => $this->ability(
                __('Validate markup for framework', 'livecanvas-forge-ai'),
                __('Preflights generated page markup against the detected or requested framework policy without writing.', 'livecanvas-forge-ai'),
                $this->command_payload_schema([], false),
                [$this, 'validate_markup_for_framework'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/preview-page-upsert' => $this->ability(
                __('Preview page upsert', 'livecanvas-forge-ai'),
                __('Previews a LiveCanvas page create/update command and returns diff, warnings, and target URLs without writing.', 'livecanvas-forge-ai'),
                $this->page_upsert_preview_schema(),
                [$this, 'preview_page_upsert'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/preview-global-shell' => $this->ability(
                __('Preview global shell', 'livecanvas-forge-ai'),
                __('Previews header/footer shell changes and framework warnings without writing partials.', 'livecanvas-forge-ai'),
                $this->global_shell_preview_schema(),
                [$this, 'preview_global_shell'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/preview-design-system' => $this->ability(
                __('Preview design system', 'livecanvas-forge-ai'),
                __('Previews stack-native design system token application without writing theme or runtime assets.', 'livecanvas-forge-ai'),
                $this->design_system_preview_schema(),
                [$this, 'preview_design_system'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/preview-block-pattern' => $this->ability(
                __('Preview block pattern', 'livecanvas-forge-ai'),
                __('Converts supplied section HTML into a WordPress-native block pattern preview without registering or writing it.', 'livecanvas-forge-ai'),
                $this->block_pattern_preview_schema(),
                [$this, 'preview_block_pattern'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/preview-native-pattern-page' => $this->ability(
                __('Preview native pattern page', 'livecanvas-forge-ai'),
                __('Composes a WordPress-native block page preview from registered Forge patterns without creating or updating a page.', 'livecanvas-forge-ai'),
                $this->native_pattern_page_preview_schema(),
                [$this, 'preview_native_pattern_page'],
                [$this, 'can_read'],
                $preview_annotations,
                true
            ),
            'livecanvas-forge-ai/apply-page-upsert' => $this->ability(
                __('Apply page upsert', 'livecanvas-forge-ai'),
                __('Applies a LiveCanvas page create/update command. The action is forced to page_upsert and writes only after the caller invokes this dedicated apply ability.', 'livecanvas-forge-ai'),
                $this->page_upsert_preview_schema(),
                [$this, 'apply_page_upsert'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/apply-page-upsert')
            ),
            'livecanvas-forge-ai/apply-native-pattern-page' => $this->ability(
                __('Apply native pattern page', 'livecanvas-forge-ai'),
                __('Creates a new WordPress-native draft page from registered Forge patterns. This dedicated write ability never updates existing content.', 'livecanvas-forge-ai'),
                $this->native_pattern_page_apply_schema(),
                [$this, 'apply_native_pattern_page'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/apply-native-pattern-page')
            ),
            'livecanvas-forge-ai/apply-global-shell' => $this->ability(
                __('Apply global shell', 'livecanvas-forge-ai'),
                __('Applies header/footer shell changes. The action is forced to global_shell_apply and returns audit/rollback metadata when possible.', 'livecanvas-forge-ai'),
                $this->global_shell_preview_schema(),
                [$this, 'apply_global_shell'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/apply-global-shell')
            ),
            'livecanvas-forge-ai/apply-dynamic-template' => $this->ability(
                __('Apply dynamic template', 'livecanvas-forge-ai'),
                __('Creates or updates a LiveCanvas dynamic template through a dedicated write ability with audit metadata.', 'livecanvas-forge-ai'),
                $this->dynamic_template_apply_schema(),
                [$this, 'apply_dynamic_template'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/apply-dynamic-template')
            ),
            'livecanvas-forge-ai/apply-design-system' => $this->ability(
                __('Apply design system', 'livecanvas-forge-ai'),
                __('Applies stack-native design tokens through the design_system_apply action and returns build/audit metadata.', 'livecanvas-forge-ai'),
                $this->design_system_preview_schema(),
                [$this, 'apply_design_system'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/apply-design-system')
            ),
            'livecanvas-forge-ai/restore-audit-rollback' => $this->ability(
                __('Restore audit rollback', 'livecanvas-forge-ai'),
                __('Restores the previous WordPress content stored for a Forge audit ID, or trashes posts created by that audited run.', 'livecanvas-forge-ai'),
                $this->audit_rollback_schema(),
                [$this, 'restore_audit_rollback'],
                [$this, 'can_write'],
                $write_annotations,
                $this->write_ability_is_mcp_public('livecanvas-forge-ai/restore-audit-rollback')
            ),
            'livecanvas-forge-ai/preview-command' => $this->ability(
                __('Preview Forge command', 'livecanvas-forge-ai'),
                __('Runs any supported Forge command in dry-run mode and returns the preview result without writing.', 'livecanvas-forge-ai'),
                $this->command_payload_schema(['action'], true),
                [$this, 'preview_command'],
                [$this, 'can_read'],
                $preview_annotations,
                false
            ),
            'livecanvas-forge-ai/apply-command' => $this->ability(
                __('Apply Forge command', 'livecanvas-forge-ai'),
                __('Runs a supported Forge command in apply mode. This can modify WordPress content or theme/runtime assets.', 'livecanvas-forge-ai'),
                $this->command_payload_schema(['action'], true),
                [$this, 'apply_command'],
                [$this, 'can_write'],
                $write_annotations,
                false,
                false
            ),
        ];
    }

    public function get_connection_handoff($input = []): array {
        $input = $this->normalize_input($input);
        $limit = absint($input['limit'] ?? 20);
        if ($limit < 1 || $limit > 40) {
            $limit = 20;
        }

        $snapshot = $this->get_snapshot();
        $diagnostics = $this->get_ability_diagnostics();
        $runs = $this->get_runs(['limit' => $limit]);
        $ability = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $run_items = is_array($runs['runs']['items'] ?? null) ? $runs['runs']['items'] : [];
        $run_errors = count(array_filter($run_items, static function (array $run): bool {
            return empty($run['ok']);
        }));
        $rollback_ready = count(array_filter($run_items, static function (array $run): bool {
            return !empty($run['rollback_available']);
        }));
        $summary = [
            'source'         => 'wordpress_ability',
            'framework'      => sanitize_key((string) (($snapshot['snapshot']['detected_framework'] ?? '') ?: ($snapshot['snapshot']['framework'] ?? ''))),
            'abilities'      => (int) ($ability['total'] ?? 0),
            'mcp_public'     => (int) ($ability['mcp_public_total'] ?? 0),
            'public_writes'  => count((array) ($ability['mcp_public_write'] ?? [])),
            'runs'           => count($run_items),
            'run_errors'     => $run_errors,
            'rollbacks'      => $rollback_ready,
            'limit'          => $limit,
        ];

        return [
            'connection_handoff' => $this->build_agent_handoff_connection_handoff($summary),
        ];
    }

    public function get_handoff_summary($input = []): array {
        $input = $this->normalize_input($input);
        $limit = absint($input['limit'] ?? 20);
        if ($limit < 1 || $limit > 40) {
            $limit = 20;
        }

        $snapshot = $this->get_snapshot();
        $diagnostics = $this->get_ability_diagnostics();
        $runs = $this->get_runs(['limit' => $limit]);
        $ability = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $run_items = is_array($runs['runs']['items'] ?? null) ? $runs['runs']['items'] : [];
        $run_errors = count(array_filter($run_items, static function (array $run): bool {
            return empty($run['ok']);
        }));
        $rollback_ready = count(array_filter($run_items, static function (array $run): bool {
            return !empty($run['rollback_available']);
        }));
        $summary = [
            'source'         => 'wordpress_ability',
            'framework'      => sanitize_key((string) (($snapshot['snapshot']['detected_framework'] ?? '') ?: ($snapshot['snapshot']['framework'] ?? ''))),
            'abilities'      => (int) ($ability['total'] ?? 0),
            'mcp_public'     => (int) ($ability['mcp_public_total'] ?? 0),
            'public_writes'  => count((array) ($ability['mcp_public_write'] ?? [])),
            'runs'           => count($run_items),
            'run_errors'     => $run_errors,
            'rollbacks'      => $rollback_ready,
            'limit'          => $limit,
        ];
        $smoke_tests = $this->build_agent_handoff_smoke_tests($summary, $ability);

        return [
            'handoff_summary' => $this->build_agent_handoff_summary($summary, $smoke_tests),
        ];
    }

    public function get_agent_handoff_package($input = []): array {
        $input = $this->normalize_input($input);
        $limit = absint($input['limit'] ?? 20);
        if ($limit < 1 || $limit > 40) {
            $limit = 20;
        }

        $snapshot = $this->get_snapshot();
        $mcp_status = $this->get_mcp_status();
        $ai_status = $this->get_ai_client_status();
        $diagnostics = $this->get_ability_diagnostics();
        $runs = $this->get_runs(['limit' => $limit]);
        $ability = is_array($diagnostics['ability_diagnostics'] ?? null) ? $diagnostics['ability_diagnostics'] : [];
        $run_items = is_array($runs['runs']['items'] ?? null) ? $runs['runs']['items'] : [];
        $run_errors = count(array_filter($run_items, static function (array $run): bool {
            return empty($run['ok']);
        }));
        $rollback_ready = count(array_filter($run_items, static function (array $run): bool {
            return !empty($run['rollback_available']);
        }));
        $summary = [
            'source'         => 'wordpress_ability',
            'framework'      => sanitize_key((string) (($snapshot['snapshot']['detected_framework'] ?? '') ?: ($snapshot['snapshot']['framework'] ?? ''))),
            'abilities'      => (int) ($ability['total'] ?? 0),
            'mcp_public'     => (int) ($ability['mcp_public_total'] ?? 0),
            'public_writes'  => count((array) ($ability['mcp_public_write'] ?? [])),
            'runs'           => count($run_items),
            'run_errors'     => $run_errors,
            'rollbacks'      => $rollback_ready,
            'limit'          => $limit,
        ];
        $smoke_tests = $this->build_agent_handoff_smoke_tests($summary, $ability);
        $connection_handoff = $this->build_agent_handoff_connection_handoff($summary);
        $block_pattern_library_result = $this->get_block_pattern_library(['include_content' => true]);
        $block_pattern_library = is_array($block_pattern_library_result['block_pattern_library'] ?? null)
            ? $block_pattern_library_result['block_pattern_library']
            : [];
        $native_pattern_page_blueprints_result = $this->get_native_pattern_page_blueprints(['include_patterns' => true]);
        $native_pattern_page_blueprints = is_array($native_pattern_page_blueprints_result['native_pattern_page_blueprints'] ?? null)
            ? $native_pattern_page_blueprints_result['native_pattern_page_blueprints']
            : [];
        $runbook = $this->build_agent_handoff_runbook($summary, $smoke_tests, $connection_handoff);
        $handoff_summary = $this->build_agent_handoff_summary($summary, $smoke_tests);
        $summary['handoff_status'] = $handoff_summary['status'];
        $summary['readiness_score'] = $handoff_summary['score'];
        $summary['unavailable_tests'] = count((array) ($handoff_summary['unavailable_tests'] ?? []));
        $summary['write_guards'] = count((array) ($handoff_summary['write_guard_tests'] ?? []));
        $package = $this->build_virtual_handoff_package([
            [
                'path'       => 'forge-agent-start-prompt.txt',
                'media_type' => 'text/plain',
                'content'    => (string) ($connection_handoff['agent_start_prompt'] ?? ''),
            ],
            [
                'path'       => 'forge-handoff-summary.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($handoff_summary),
            ],
            [
                'path'       => 'forge-connection-handoff.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($connection_handoff),
            ],
            [
                'path'       => 'forge-agent-runbook.md',
                'media_type' => 'text/markdown',
                'content'    => $runbook,
            ],
            [
                'path'       => 'forge-agent-smoke-tests.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($smoke_tests),
            ],
            [
                'path'       => 'forge-block-pattern-library.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($block_pattern_library),
            ],
            [
                'path'       => 'forge-native-pattern-page-blueprints.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($native_pattern_page_blueprints),
            ],
            [
                'path'       => 'forge-ability-diagnostics.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($diagnostics),
            ],
            [
                'path'       => 'forge-runs.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($runs),
            ],
            [
                'path'       => 'forge-mcp-status.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($mcp_status),
            ],
            [
                'path'       => 'forge-ai-client-status.json',
                'media_type' => 'application/json',
                'content'    => $this->encode_handoff_json($ai_status),
            ],
        ], $summary);

        return [
            'connection_handoff' => $connection_handoff,
            'block_pattern_library' => $block_pattern_library,
            'native_pattern_page_blueprints' => $native_pattern_page_blueprints,
            'agent_handoff_package' => $package,
        ];
    }

    public function get_public_mcp_abilities(): array {
        return array_filter($this->get_ability_manifest(), static function (array $definition): bool {
            return !empty($definition['meta']['mcp']['public']);
        });
    }

    public function get_snapshot($input = []): array {
        return [
            'snapshot'    => $this->environment->get_snapshot(),
            'connections' => LCFA_Settings::get_public_connections(),
            'mcp'         => $this->context_builder->get_mcp_status(),
        ];
    }

    public function get_inventory($input = []): array {
        return [
            'inventory' => $this->inventory->get_inventory(),
        ];
    }

    public function get_context($input = []): array {
        return [
            'context' => $this->context_builder->build_context($this->normalize_input($input)),
        ];
    }

    public function get_theme_context($input = []): array {
        return [
            'theme_context' => $this->context_builder->get_theme_context($this->normalize_input($input)),
        ];
    }

    public function get_page_html($input = []): array {
        $input = $this->normalize_input($input);
        $post_id = absint($input['post_id'] ?? 0);

        return [
            'page_html' => $this->context_builder->get_page_html($post_id),
        ];
    }

    public function list_command_actions($input = []): array {
        return [
            'actions' => $this->command_deck->get_actions(),
        ];
    }

    public function get_mcp_status($input = []): array {
        return [
            'mcp' => $this->context_builder->get_mcp_status(),
        ];
    }

    public function get_windpress_status($input = []): array {
        return [
            'windpress' => $this->windpress_bridge->get_status(),
        ];
    }

    public function get_ai_client_status($input = []): array {
        return [
            'ai_client' => $this->ai_client->get_status(),
        ];
    }

    public function get_ability_diagnostics($input = []): array {
        $manifest = $this->get_ability_manifest();
        $public_mcp = $this->get_public_mcp_abilities();
        $items = [];
        $write_public = [];
        $preview_public = [];

        foreach ($manifest as $name => $definition) {
            $annotations = is_array($definition['meta']['annotations'] ?? null) ? $definition['meta']['annotations'] : [];
            $is_public = !empty($definition['meta']['mcp']['public']);
            $is_preview = strpos($name, '/preview-') !== false || strpos($name, '/validate-') !== false;
            $is_write = empty($annotations['readonly']) || !empty($annotations['destructive']);

            if ($is_public && $is_preview) {
                $preview_public[] = $name;
            }

            if ($is_public && $is_write) {
                $write_public[] = $name;
            }

            $items[] = [
                'name'        => $name,
                'label'       => (string) ($definition['label'] ?? ''),
                'category'    => (string) ($definition['category'] ?? ''),
                'mcp_public'  => $is_public,
                'readonly'    => !empty($annotations['readonly']),
                'destructive' => !empty($annotations['destructive']),
                'idempotent'  => !empty($annotations['idempotent']),
            ];
        }

        return [
            'ability_diagnostics' => [
                'total'               => count($manifest),
                'mcp_public_total'    => count($public_mcp),
                'mcp_public'          => array_keys($public_mcp),
                'mcp_public_preview'  => $preview_public,
                'mcp_public_write'    => $write_public,
                'has_mcp_public_write' => !empty($write_public),
                'mcp_write_opt_in_enabled' => $this->write_abilities_are_mcp_public(),
                'mcp_write_allowlist' => $this->get_public_write_ability_allowlist(),
                'mcp_write_available' => array_keys(LCFA_Settings::get_mcp_write_ability_options()),
                'items'               => $items,
            ],
            'mcp_adapter' => method_exists($this->environment, 'get_mcp_adapter_status')
                ? $this->environment->get_mcp_adapter_status()
                : [],
            'ai_client' => $this->ai_client->get_status(),
        ];
    }

    public function get_block_patterns($input = []): array {
        return [
            'block_patterns' => $this->block_patterns instanceof LCFA_Block_Patterns
                ? $this->block_patterns->get_pattern_manifest()
                : [
                    'available' => false,
                    'category'  => 'livecanvas-forge-ai',
                    'block'     => 'livecanvas-forge-ai/section-shell',
                    'patterns'  => [],
                    'counts'    => ['patterns' => 0],
                    'context'   => [],
                ],
        ];
    }

    public function get_block_pattern_library($input = []): array {
        $input = $this->normalize_input($input);
        $include_content = !array_key_exists('include_content', $input) || !empty($input['include_content']);

        return [
            'block_pattern_library' => $this->block_patterns instanceof LCFA_Block_Patterns
                ? $this->block_patterns->get_pattern_library(['include_content' => $include_content])
                : [
                    'schema_version' => 'block-pattern-library.v1',
                    'available'      => false,
                    'source'         => 'unavailable',
                    'category'       => 'livecanvas-forge-ai',
                    'block'          => 'livecanvas-forge-ai/section-shell',
                    'include_content' => $include_content,
                    'counts'         => ['patterns' => 0, 'bytes' => 0],
                    'context'        => [],
                    'export'         => [
                        'format'          => 'wordpress_block_patterns',
                        'can_import'      => false,
                        'preview_ability' => 'livecanvas-forge-ai/preview-block-pattern',
                    ],
                    'patterns'       => [],
                ],
        ];
    }

    public function get_native_pattern_page_blueprints($input = []): array {
        $input = $this->normalize_input($input);
        $include_patterns = !array_key_exists('include_patterns', $input) || !empty($input['include_patterns']);

        return [
            'native_pattern_page_blueprints' => $this->block_patterns instanceof LCFA_Block_Patterns
                ? $this->block_patterns->get_native_page_blueprints(['include_patterns' => $include_patterns])
                : [
                    'schema_version'   => 'native-pattern-page-blueprints.v1',
                    'available'        => false,
                    'source'           => 'unavailable',
                    'include_patterns' => $include_patterns,
                    'counts'           => ['blueprints' => 0],
                    'preview_ability'  => 'livecanvas-forge-ai/preview-native-pattern-page',
                    'preview_tool'     => 'preview_native_pattern_page',
                    'preview_route'    => '/wp-json/lcfa/v1/studio/native-pattern-page-preview',
                    'blueprints'       => [],
                ],
        ];
    }

    public function get_runs($input = []): array {
        $input = $this->normalize_input($input);
        $limit = absint($input['limit'] ?? 20);
        if ($limit < 1 || $limit > 40) {
            $limit = 20;
        }

        $history = array_slice(LCFA_Settings::get_history(), 0, $limit);
        $runs = [];

        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $audit_id = sanitize_key((string) ($entry['audit_id'] ?? ''));
            $runs[] = [
                'time'               => sanitize_text_field((string) ($entry['time'] ?? '')),
                'audit_id'           => $audit_id,
                'action'             => sanitize_key((string) ($entry['action'] ?? '')),
                'mode'               => sanitize_key((string) ($entry['mode'] ?? '')),
                'ok'                 => !empty($entry['ok']),
                'message'            => sanitize_text_field((string) ($entry['message'] ?? '')),
                'summary'            => sanitize_text_field((string) ($entry['summary'] ?? '')),
                'target_type'        => sanitize_key((string) ($entry['target_type'] ?? '')),
                'target_id'          => absint($entry['target_id'] ?? 0),
                'target_title'       => sanitize_text_field((string) ($entry['target_title'] ?? '')),
                'rollback_available' => !empty($entry['rollback_available']) && $audit_id !== '',
                'execution_target'   => sanitize_key((string) ($entry['execution_target'] ?? 'local')),
                'origin'             => sanitize_key((string) ($entry['origin'] ?? '')),
                'processed_by'       => sanitize_key((string) ($entry['processed_by'] ?? '')),
            ];
        }

        return [
            'runs' => [
                'items' => $runs,
                'count' => count($runs),
                'limit' => $limit,
            ],
        ];
    }

    public function generate_ai_text($input = []) {
        $input = $this->normalize_input($input);
        $generated = $this->ai_client->generate_text(
            (string) ($input['prompt'] ?? ''),
            [
                'system_instruction' => (string) ($input['system_instruction'] ?? ''),
                'temperature'        => $input['temperature'] ?? null,
                'max_tokens'         => $input['max_tokens'] ?? null,
                'model_preference'   => is_array($input['model_preference'] ?? null) ? $input['model_preference'] : [],
                'response_schema'    => is_array($input['response_schema'] ?? null) ? $input['response_schema'] : [],
            ]
        );

        if (is_wp_error($generated)) {
            return $generated;
        }

        return [
            'ai_text' => $generated,
        ];
    }

    public function validate_markup_for_framework($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'validate_markup_for_framework';
        $payload['dry_run'] = true;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_preview', 'wordpress_abilities')),
        ];
    }

    public function preview_command($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['dry_run'] = true;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_preview', 'wordpress_abilities')),
        ];
    }

    public function preview_page_upsert($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'page_upsert';
        $payload['dry_run'] = true;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_preview_page_upsert', 'wordpress_abilities')),
        ];
    }

    public function preview_global_shell($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'global_shell_apply';
        $payload['dry_run'] = true;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_preview_global_shell', 'wordpress_abilities')),
        ];
    }

    public function preview_design_system($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'design_system_apply';
        $payload['dry_run'] = true;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_preview_design_system', 'wordpress_abilities')),
        ];
    }

    public function preview_block_pattern($input = []): array {
        $input = $this->normalize_input($input);

        if (!$this->block_patterns instanceof LCFA_Block_Patterns) {
            return [
                'block_pattern_preview' => [
                    'ok'      => false,
                    'message' => __('Block pattern service is not available in this runtime.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        return [
            'block_pattern_preview' => $this->block_patterns->build_pattern_preview($input),
        ];
    }

    public function preview_native_pattern_page($input = []): array {
        $input = $this->normalize_input($input);

        if (!$this->block_patterns instanceof LCFA_Block_Patterns) {
            return [
                'native_pattern_page_preview' => [
                    'ok'      => false,
                    'message' => __('Block pattern service is not available in this runtime.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        return [
            'native_pattern_page_preview' => $this->block_patterns->build_native_page_preview($input),
        ];
    }

    public function apply_page_upsert($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'page_upsert';
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_apply_page_upsert', 'wordpress_abilities')),
        ];
    }

    public function apply_native_pattern_page($input = []): array {
        $input = $this->normalize_input($input);

        if (!$this->block_patterns instanceof LCFA_Block_Patterns) {
            return [
                'native_pattern_page_apply' => [
                    'ok'      => false,
                    'message' => __('Block pattern service is not available in this runtime.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        $preview = $this->block_patterns->build_native_page_preview($input);
        if (empty($preview['ok']) || !is_array($preview['page'] ?? null)) {
            return [
                'native_pattern_page_apply' => [
                    'ok'      => false,
                    'message' => sanitize_text_field((string) ($preview['message'] ?? __('Native page preview failed.', 'livecanvas-forge-ai'))),
                    'preview' => $preview,
                ],
            ];
        }

        if (!function_exists('wp_insert_post')) {
            return [
                'native_pattern_page_apply' => [
                    'ok'      => false,
                    'message' => __('WordPress post insertion is not available in this runtime.', 'livecanvas-forge-ai'),
                    'preview' => $preview,
                ],
            ];
        }

        $preview_page = $preview['page'];
        $title = sanitize_text_field((string) ($input['title'] ?? $preview_page['title'] ?? __('Forge native page', 'livecanvas-forge-ai')));
        if ($title === '') {
            $title = __('Forge native page', 'livecanvas-forge-ai');
        }

        $status = sanitize_key((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'pending', 'private'], true)) {
            $status = 'draft';
        }

        $postarr = [
            'post_type'    => 'page',
            'post_status'  => $status,
            'post_title'   => $title,
            'post_content' => (string) ($preview_page['content'] ?? ''),
        ];
        $slug = sanitize_title((string) ($input['slug'] ?? ''));
        if ($slug !== '') {
            $postarr['post_name'] = $slug;
        }

        $post_id = wp_insert_post($postarr, true);
        if (function_exists('is_wp_error') && is_wp_error($post_id)) {
            return [
                'native_pattern_page_apply' => [
                    'ok'      => false,
                    'message' => method_exists($post_id, 'get_error_message') ? $post_id->get_error_message() : __('WordPress returned an error while creating the native page.', 'livecanvas-forge-ai'),
                    'preview' => $preview,
                ],
            ];
        }

        $post_id = absint($post_id);
        if ($post_id < 1) {
            return [
                'native_pattern_page_apply' => [
                    'ok'      => false,
                    'message' => __('WordPress did not return a created page ID.', 'livecanvas-forge-ai'),
                    'preview' => $preview,
                ],
            ];
        }

        $audit_id = $this->create_apply_audit_id();
        $created_at = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
        $provenance = [
            'origin'       => 'wp_ability',
            'processed_by' => 'wp_ability_apply_native_pattern_page',
            'ruleset'      => 'wordpress_abilities',
        ];
        $edit_url = function_exists('get_edit_post_link') ? (string) get_edit_post_link($post_id, '') : '';
        $frontend_url = function_exists('get_permalink') ? (string) get_permalink($post_id) : '';
        $message = __('Native pattern page created as a WordPress draft.', 'livecanvas-forge-ai');

        $result = [
            'ok'             => true,
            'schema_version' => 'native-pattern-page-apply.v1',
            'message'        => $message,
            'audit_id'       => $audit_id,
            'rollback_available' => true,
            'page'           => [
                'id'             => $post_id,
                'title'          => $title,
                'status'         => $status,
                'post_type'      => 'page',
                'content_format' => 'wordpress_blocks',
                'bytes'          => absint($preview_page['bytes'] ?? strlen((string) ($preview_page['content'] ?? ''))),
                'sha256'         => sanitize_text_field((string) ($preview_page['sha256'] ?? '')),
                'blueprint'      => sanitize_key((string) ($preview_page['blueprint'] ?? '')),
                'patterns'       => is_array($preview_page['patterns'] ?? null) ? $preview_page['patterns'] : [],
                'edit_url'       => $edit_url,
                'frontend_url'   => $frontend_url,
            ],
            'preview'        => $preview,
            'audit'          => [
                'id'                 => $audit_id,
                'created_at'         => $created_at,
                'action'             => 'native_pattern_page_apply',
                'mode'               => 'apply',
                'execution_target'   => 'local',
                'target_type'        => 'page',
                'target_id'          => $post_id,
                'target_title'       => $title,
                'rollback_available' => true,
                'rollback_reference' => [
                    'available'   => true,
                    'type'        => 'created_post',
                    'target_type' => 'page',
                    'target_id'   => $post_id,
                    'content_hash'=> '',
                ],
                'provenance'         => $provenance,
            ],
        ];

        if (method_exists('LCFA_Settings', 'store_rollback_record')) {
            LCFA_Settings::store_rollback_record($audit_id, [
                'audit_id'           => $audit_id,
                'created_at'         => $created_at,
                'action'             => 'native_pattern_page_apply',
                'execution_target'   => 'local',
                'target_type'        => 'page',
                'target_id'          => $post_id,
                'target_title'       => $title,
                'rollback_reference' => $result['audit']['rollback_reference'],
                'provenance'         => $provenance,
                'restore'            => [
                    'type'             => 'created_post',
                    'target_type'      => 'page',
                    'target_id'        => $post_id,
                    'target_title'     => $title,
                    'previous_content' => '',
                    'created_post'     => true,
                ],
            ]);
        }

        if (method_exists('LCFA_Settings', 'append_history')) {
            LCFA_Settings::append_history([
                'time'               => $created_at,
                'audit_id'           => $audit_id,
                'action'             => 'native_pattern_page_apply',
                'mode'               => 'apply',
                'ok'                 => true,
                'message'            => $message,
                'summary'            => sprintf(__('Create native pattern page #%d.', 'livecanvas-forge-ai'), $post_id),
                'target_type'        => 'page',
                'target_id'          => $post_id,
                'target_title'       => $title,
                'rollback_available' => true,
                'execution_target'   => 'local',
            ] + $provenance);
        }

        return [
            'native_pattern_page_apply' => $result,
        ];
    }

    public function apply_global_shell($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'global_shell_apply';
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_apply_global_shell', 'wordpress_abilities')),
        ];
    }

    public function apply_dynamic_template($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $operation = sanitize_key((string) ($payload['operation'] ?? $payload['template_operation'] ?? ''));
        $target_id = absint($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $payload['action'] = $operation === 'create' || $target_id < 1 ? 'create_dynamic_template' : 'update_dynamic_template';
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_apply_dynamic_template', 'wordpress_abilities')),
        ];
    }

    public function apply_design_system($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'design_system_apply';
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_apply_design_system', 'wordpress_abilities')),
        ];
    }

    public function restore_audit_rollback($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['action'] = 'restore_audit_rollback';
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_restore_audit_rollback', 'wordpress_abilities')),
        ];
    }

    public function apply_command($input = []): array {
        $payload = $this->normalize_command_payload($input);
        $payload['dry_run'] = false;

        return [
            'result' => $this->command_deck->execute($this->with_provenance($payload, 'wp_ability_apply', 'wordpress_abilities')),
        ];
    }

    public function can_read(...$args): bool {
        return current_user_can('edit_pages');
    }

    public function can_write(...$args): bool {
        return current_user_can('edit_pages');
    }

    public function can_manage(...$args): bool {
        return current_user_can('manage_options');
    }

    private function ability(string $label, string $description, array $input_schema, callable $execute_callback, callable $permission_callback, array $annotations, bool $mcp_public, bool $show_in_rest = true): array {
        return [
            'label'               => $label,
            'description'         => $description,
            'category'            => self::CATEGORY,
            'input_schema'        => $input_schema,
            'output_schema'       => $this->object_schema(),
            'execute_callback'    => $execute_callback,
            'permission_callback' => $permission_callback,
            'meta'                => [
                'annotations'  => $annotations,
                'show_in_rest' => $show_in_rest,
                'mcp'          => ['public' => $mcp_public],
            ],
        ];
    }

    private function normalize_input($input): array {
        if (is_array($input)) {
            return $input;
        }

        if (is_object($input) && method_exists($input, 'get_data')) {
            $data = $input->get_data();
            return is_array($data) ? $data : [];
        }

        return [];
    }

    private function normalize_command_payload($input): array {
        $input = $this->normalize_input($input);
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : $input;
        $payload['action'] = sanitize_key((string) ($payload['action'] ?? ''));

        return $payload;
    }

    private function with_provenance(array $payload, string $processed_by, string $ruleset): array {
        $payload['_lcfa_origin'] = 'wp_ability';
        $payload['_lcfa_processed_by'] = $processed_by;
        $payload['_lcfa_ruleset'] = $ruleset;

        return $payload;
    }

    private function create_apply_audit_id(): string {
        if (function_exists('wp_generate_password')) {
            return sanitize_key('audit-' . strtolower(wp_generate_password(12, false, false)));
        }

        return sanitize_key('audit-' . substr(md5((string) microtime(true)), 0, 12));
    }

    private function write_abilities_are_mcp_public(): bool {
        $connections = LCFA_Settings::get_connections();

        return !empty($connections['mcp_write_abilities_enabled']);
    }

    private function write_ability_is_mcp_public(string $ability_name): bool {
        if (!$this->write_abilities_are_mcp_public()) {
            return false;
        }

        return in_array($ability_name, $this->get_public_write_ability_allowlist(), true);
    }

    private function get_public_write_ability_allowlist(): array {
        $connections = LCFA_Settings::get_connections();
        return LCFA_Settings::sanitize_mcp_write_abilities($connections['mcp_public_write_abilities'] ?? []);
    }

    private function target_context_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'post_id'   => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => __('Optional WordPress post ID to contextualize.', 'livecanvas-forge-ai'),
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => __('Optional WordPress post type to inspect ACF and target context.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function page_html_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['post_id'],
            'properties'           => [
                'post_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => __('WordPress post ID to read.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function runs_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'limit' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 40,
                    'description' => __('Maximum number of recent runs to return.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function block_pattern_library_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'include_content' => [
                    'type'        => 'boolean',
                    'description' => __('Whether to include full block pattern content in the library export.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function native_pattern_page_blueprints_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'include_patterns' => [
                    'type'        => 'boolean',
                    'description' => __('Whether to include the pattern names used by each native page blueprint.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function command_payload_schema(array $required = [], bool $allow_payload_wrapper = true): array {
        $command_schema = [
            'type'                 => 'object',
            'additionalProperties' => true,
            'required'             => $required,
            'properties'           => [
                'action' => [
                    'type'        => 'string',
                    'description' => __('Forge Command Deck action.', 'livecanvas-forge-ai'),
                ],
                'dry_run' => [
                    'type'        => 'boolean',
                    'description' => __('Whether to run the command as a preview.', 'livecanvas-forge-ai'),
                ],
                'target_id' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => __('Optional WordPress target ID.', 'livecanvas-forge-ai'),
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => __('Optional generated HTML/CSS/JSON payload for the command.', 'livecanvas-forge-ai'),
                ],
                'framework' => [
                    'type'        => 'string',
                    'description' => __('Optional framework override for validation.', 'livecanvas-forge-ai'),
                ],
            ],
        ];

        if (!$allow_payload_wrapper) {
            return $command_schema;
        }

        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'payload' => $command_schema,
            ],
        ];
    }

    private function page_upsert_preview_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'payload' => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'properties'           => $this->page_upsert_preview_properties(),
                ],
            ] + $this->page_upsert_preview_properties(),
        ];
    }

    private function global_shell_preview_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'payload' => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'properties'           => $this->global_shell_preview_properties(),
                ],
            ] + $this->global_shell_preview_properties(),
        ];
    }

    private function design_system_preview_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'payload' => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'properties'           => $this->design_system_preview_properties(),
                ],
            ] + $this->design_system_preview_properties(),
        ];
    }

    private function dynamic_template_apply_schema(): array {
        $properties = [
            'target_id' => [
                'type'        => 'integer',
                'minimum'     => 1,
                'description' => __('Existing dynamic template ID to update. Omit it to create a template.', 'livecanvas-forge-ai'),
            ],
            'post_id' => [
                'type'        => 'integer',
                'minimum'     => 1,
                'description' => __('Alias for target_id.', 'livecanvas-forge-ai'),
            ],
            'operation' => [
                'type'        => 'string',
                'enum'        => ['create', 'update'],
                'description' => __('Optional operation hint. Forge updates when target_id exists, otherwise creates.', 'livecanvas-forge-ai'),
            ],
            'title' => [
                'type'        => 'string',
                'description' => __('Dynamic template title for create operations.', 'livecanvas-forge-ai'),
            ],
            'slug' => [
                'type'        => 'string',
                'description' => __('Optional template slug.', 'livecanvas-forge-ai'),
            ],
            'status' => [
                'type'        => 'string',
                'enum'        => ['draft', 'publish', 'private', 'pending'],
                'description' => __('Template post status.', 'livecanvas-forge-ai'),
            ],
            'content' => [
                'type'        => 'string',
                'description' => __('Generated LiveCanvas-safe template HTML.', 'livecanvas-forge-ai'),
            ],
            'template_assignment' => [
                'type'        => 'object',
                'description' => __('Optional dynamic template assignment metadata.', 'livecanvas-forge-ai'),
            ],
            'template_target' => [
                'type'        => 'string',
                'description' => __('Optional LiveCanvas dynamic template target shortcut.', 'livecanvas-forge-ai'),
            ],
            'native_key' => [
                'type'        => 'string',
                'description' => __('Optional LiveCanvas native template meta key such as is_single_post.', 'livecanvas-forge-ai'),
            ],
        ];

        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'properties'           => [
                'payload' => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'properties'           => $properties,
                ],
            ] + $properties,
        ];
    }

    private function block_pattern_preview_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['html'],
            'properties'           => [
                'html' => [
                    'type'        => 'string',
                    'description' => __('Section or component HTML to wrap in a WordPress block pattern preview.', 'livecanvas-forge-ai'),
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => __('Human-readable pattern title.', 'livecanvas-forge-ai'),
                ],
                'slug' => [
                    'type'        => 'string',
                    'description' => __('Optional pattern slug.', 'livecanvas-forge-ai'),
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => __('Optional pattern description.', 'livecanvas-forge-ai'),
                ],
                'source' => [
                    'type'        => 'string',
                    'description' => __('Optional source tag such as livecanvas_section or generated_brief.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function native_pattern_page_preview_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'title' => [
                    'type'        => 'string',
                    'description' => __('Draft page title for the native block preview.', 'livecanvas-forge-ai'),
                ],
                'pattern_name' => [
                    'type'        => 'string',
                    'description' => __('Optional single Forge pattern name or slug to include.', 'livecanvas-forge-ai'),
                ],
                'blueprint' => [
                    'type'        => 'string',
                    'description' => __('Optional native pattern page blueprint id to compose.', 'livecanvas-forge-ai'),
                ],
                'blueprint_id' => [
                    'type'        => 'string',
                    'description' => __('Alias for blueprint.', 'livecanvas-forge-ai'),
                ],
                'pattern_names' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => __('Optional ordered Forge pattern names or slugs to compose.', 'livecanvas-forge-ai'),
                ],
                'patterns' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => __('Alias for pattern_names.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function native_pattern_page_apply_schema(): array {
        $schema = $this->native_pattern_page_preview_schema();
        $schema['properties']['slug'] = [
            'type'        => 'string',
            'description' => __('Optional new page slug. The ability always creates a new native page.', 'livecanvas-forge-ai'),
        ];
        $schema['properties']['status'] = [
            'type'        => 'string',
            'enum'        => ['draft', 'pending', 'private'],
            'description' => __('Post status for the new native page. Publish is intentionally not supported by this ability.', 'livecanvas-forge-ai'),
        ];

        return $schema;
    }

    private function audit_rollback_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
            'required'             => ['audit_id'],
            'properties'           => [
                'audit_id' => [
                    'type'        => 'string',
                    'description' => __('Forge audit ID returned by a previous apply run.', 'livecanvas-forge-ai'),
                ],
                'dry_run' => [
                    'type'        => 'boolean',
                    'description' => __('Ignored by the ability. This dedicated ability always applies the rollback.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function build_agent_handoff_smoke_tests(array $summary, array $ability): array {
        $public_abilities = array_flip(array_map('strval', (array) ($ability['mcp_public'] ?? [])));
        $public_writes = array_flip(array_map('strval', (array) ($ability['mcp_public_write'] ?? [])));
        $tests = [];
        $add = static function (string $id, string $phase, string $label, string $ability_name, array $payload, string $expected, string $risk) use (&$tests, $public_abilities, $public_writes): void {
            $tests[] = [
                'id'        => sanitize_key($id),
                'phase'     => sanitize_key($phase),
                'label'     => sanitize_text_field($label),
                'ability'   => sanitize_text_field($ability_name),
                'payload'   => $payload,
                'expected'  => sanitize_text_field($expected),
                'risk'      => sanitize_key($risk),
                'available' => isset($public_abilities[$ability_name]) || isset($public_writes[$ability_name]),
                'public_write_exposed' => isset($public_writes[$ability_name]),
            ];
        };

        $add(
            'snapshot',
            'read_only',
            __('Snapshot handshake', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-snapshot',
            [],
            __('Returns runtime context without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'ability_diagnostics',
            'read_only',
            __('Ability diagnostics', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-ability-diagnostics',
            [],
            __('Returns public abilities and write exposure without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'block_pattern_library',
            'read_only',
            __('Block pattern library export', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-block-pattern-library',
            ['include_content' => true],
            __('Returns export-ready native WordPress patterns without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'native_pattern_page_blueprints',
            'read_only',
            __('Native pattern page blueprints', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-native-pattern-page-blueprints',
            ['include_patterns' => true],
            __('Returns no-write native page blueprint recipes without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'agent_handoff_package',
            'read_only',
            __('Agent handoff package', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-agent-handoff-package',
            ['limit' => (int) ($summary['limit'] ?? 20)],
            __('Returns this virtual file bundle without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'handoff_summary',
            'read_only',
            __('Handoff summary', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-handoff-summary',
            ['limit' => (int) ($summary['limit'] ?? 20)],
            __('Returns compact readiness, blocker, warning, and next-action metadata without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'recent_runs',
            'read_only',
            __('Recent runs', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/get-runs',
            ['limit' => 5],
            __('Returns sanitized run metadata and rollback availability only.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'framework_validation',
            'preview',
            __('Framework validation preview', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/validate-markup-for-framework',
            [
                'content' => '<section class="py-12"><div class="container mx-auto"><h1>Forge smoke test</h1></div></section>',
                'framework' => (string) (($summary['framework'] ?? '') ?: 'auto'),
            ],
            __('Returns validation output without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'native_pattern_page_preview',
            'preview',
            __('Native pattern page preview', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/preview-native-pattern-page',
            [
                'title' => __('Forge native page smoke test', 'livecanvas-forge-ai'),
                'pattern_names' => ['conversion-hero', 'feature-grid'],
            ],
            __('Returns a composed WordPress block page preview without writing.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'page_preview',
            'preview',
            __('Page upsert preview', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/preview-page-upsert',
            [
                'title' => 'Forge Smoke Test',
                'slug' => 'forge-smoke-test',
                'status' => 'draft',
                'content' => '<section class="py-12"><div class="container mx-auto"><h1>Forge smoke test</h1><p>Preview only.</p></div></section>',
            ],
            __('Returns preview metadata without creating or updating content.', 'livecanvas-forge-ai'),
            'low'
        );
        $add(
            'write_guard',
            'write_guard',
            __('Write ability guard', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/apply-page-upsert',
            [],
            __('Do not execute automatically; review preview, allowlist, and rollback plan first.', 'livecanvas-forge-ai'),
            'write'
        );
        $add(
            'native_pattern_page_apply_guard',
            'write_guard',
            __('Native pattern page apply guard', 'livecanvas-forge-ai'),
            'livecanvas-forge-ai/apply-native-pattern-page',
            [],
            __('Do not execute automatically; run native_pattern_page_preview first, review the result, then create only a new draft page after approval.', 'livecanvas-forge-ai'),
            'write'
        );

        return [
            'mode'              => 'read_only_first',
            'recommended_order' => array_values(array_map(static function (array $test): string {
                return sanitize_key((string) ($test['id'] ?? ''));
            }, $tests)),
            'counts'            => [
                'total' => count($tests),
                'available' => count(array_filter($tests, static function (array $test): bool {
                    return !empty($test['available']);
                })),
                'public_write_exposed' => count(array_filter($tests, static function (array $test): bool {
                    return !empty($test['public_write_exposed']);
                })),
            ],
            'tests'             => $tests,
        ];
    }

    private function build_agent_handoff_connection_handoff(array $summary): array {
        $connections = LCFA_Settings::get_public_connections();
        $client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        if ($client === 'claude-code') {
            $client = 'claude';
        }
        if (!in_array($client, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true)) {
            $client = '';
        }

        $mode = sanitize_key((string) ($connections['connection_mode'] ?? ''));
        if (!in_array($mode, ['local', 'remote'], true)) {
            $mode = '';
        }

        $status = sanitize_key((string) ($connections['connection_status'] ?? ''));
        if ($status === '') {
            $status = 'unknown';
        }

        $prompt_lines = [
            __('Use the LiveCanvas Forge AI WordPress Ability connection for this project.', 'livecanvas-forge-ai'),
            __('First call livecanvas-forge-ai/get-connection-handoff with {"limit":5}.', 'livecanvas-forge-ai'),
            __('If this prompt appears inside a returned connection_handoff payload, treat that call as already complete and continue.', 'livecanvas-forge-ai'),
            __('Read the returned connection status, transport, first-prompt guardrails, and recommended sequence.', 'livecanvas-forge-ai'),
            __('Then call livecanvas-forge-ai/get-agent-handoff-package with {"limit":5} only if you need the full runbook, smoke tests, ability diagnostics, MCP status, AI status, or recent run summary.', 'livecanvas-forge-ai'),
            __('Then run read-only checks starting with livecanvas-forge-ai/get-snapshot, livecanvas-forge-ai/get-ability-diagnostics, and livecanvas-forge-ai/get-runs when available.', 'livecanvas-forge-ai'),
            __('Summarize the site framework, public abilities, active risks, and write exposure before previewing changes.', 'livecanvas-forge-ai'),
            __('Stay read-only until a preview or dry-run has been reviewed.', 'livecanvas-forge-ai'),
        ];
        $prompt_lines = array_values(array_map('sanitize_text_field', $prompt_lines));

        return [
            'schema_version' => 'connection-handoff.v1',
            'source'         => 'wordpress_ability',
            'client'         => $client,
            'mode'           => $mode,
            'status'         => $status,
            'transport'      => 'wordpress_ability',
            'agent_start_tool' => 'livecanvas-forge-ai/get-connection-handoff',
            'connection_handoff_tool' => 'livecanvas-forge-ai/get-connection-handoff',
            'handoff_package_tool' => 'livecanvas-forge-ai/get-agent-handoff-package',
            'agent_start_prompt' => implode("\n", $prompt_lines),
            'agent_start_prompt_lines' => $prompt_lines,
            'guardrail'      => 'read_only_first',
            'summary'        => [
                'framework'        => sanitize_key((string) ($summary['framework'] ?? '')),
                'abilities'        => (int) ($summary['abilities'] ?? 0),
                'mcp_public'       => (int) ($summary['mcp_public'] ?? 0),
                'public_writes'    => (int) ($summary['public_writes'] ?? 0),
                'run_errors'       => (int) ($summary['run_errors'] ?? 0),
            ],
        ];
    }

    private function build_agent_handoff_runbook(array $summary, array $smoke_tests, array $connection_handoff): string {
        $lines = [
            '# LiveCanvas Forge AI Agent Handoff',
            '',
            '## Current State',
            '- Source: WordPress Ability',
            '- Framework: ' . ((string) ($summary['framework'] ?: 'auto')),
            '- Abilities: ' . (int) ($summary['abilities'] ?? 0) . ' total, ' . (int) ($summary['mcp_public'] ?? 0) . ' MCP-public',
            '- MCP-public writes: ' . (int) ($summary['public_writes'] ?? 0),
            '- Recent runs: ' . (int) ($summary['runs'] ?? 0) . ', errors: ' . (int) ($summary['run_errors'] ?? 0) . ', rollback-ready: ' . (int) ($summary['rollbacks'] ?? 0),
            '- Agent start tool: `' . sanitize_text_field((string) ($connection_handoff['agent_start_tool'] ?? '')) . '`',
            '',
            '## Guardrails',
            '- [ ] Fetch the connection handoff before inspecting or editing content.',
            '- [ ] Start with get-snapshot, get-ability-diagnostics, and get-runs.',
            '- [ ] Use preview abilities before apply abilities.',
            '- [ ] Do not execute write abilities automatically.',
            '- [ ] Confirm rollback availability before applying content changes.',
            '',
            '## Smoke Test Order',
        ];

        foreach ((array) ($smoke_tests['tests'] ?? []) as $index => $test) {
            if (!is_array($test)) {
                continue;
            }

            $lines[] = sprintf(
                '%d. [%s] %s - `%s`',
                (int) $index + 1,
                sanitize_key((string) ($test['phase'] ?? 'read_only')),
                sanitize_text_field((string) ($test['label'] ?? $test['id'] ?? 'Smoke test')),
                sanitize_text_field((string) ($test['ability'] ?? ''))
            );
        }

        $prompt = (string) ($connection_handoff['agent_start_prompt'] ?? '');
        if (trim($prompt) !== '') {
            $lines[] = '';
            $lines[] = '## First Agent Prompt';
            foreach (preg_split('/\r\n|\r|\n/', $prompt) ?: [] as $line) {
                $line = sanitize_text_field((string) $line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", array_values(array_map('sanitize_text_field', $lines)));
    }

    private function build_virtual_handoff_package(array $raw_files, array $summary): array {
        $files = [];
        $checksums = [];
        $total_bytes = 0;

        foreach ($raw_files as $raw_file) {
            if (!is_array($raw_file)) {
                continue;
            }

            $path = sanitize_text_field((string) ($raw_file['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $content = (string) ($raw_file['content'] ?? '');
            $media_type = sanitize_text_field((string) ($raw_file['media_type'] ?? 'text/plain'));
            $bytes = strlen($content);
            $sha256 = hash('sha256', $content);
            $files[] = [
                'path'       => $path,
                'media_type' => $media_type,
                'bytes'      => $bytes,
                'sha256'     => $sha256,
                'content'    => $content,
            ];
            $checksums[$path] = $sha256;
            $total_bytes += $bytes;
        }

        $manifest = [
            'checksum_algorithm' => 'sha256',
            'paths'              => array_values(array_column($files, 'path')),
            'checksums'          => $checksums,
            'files'              => array_values(array_map(static function (array $file): array {
                return [
                    'path'       => $file['path'],
                    'media_type' => $file['media_type'],
                    'bytes'      => $file['bytes'],
                    'sha256'     => $file['sha256'],
                ];
            }, $files)),
        ];
        $encoded_manifest = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded_manifest)) {
            $encoded_manifest = '';
        }
        $package_checksum = hash('sha256', $encoded_manifest);
        $manifest['package_checksum'] = $package_checksum;

        return [
            'package_version'  => 1,
            'format'           => 'virtual_files',
            'source'           => 'wordpress_ability',
            'summary'          => [
                'source'        => 'wordpress_ability',
                'framework'     => sanitize_key((string) ($summary['framework'] ?? '')),
                'abilities'     => (int) ($summary['abilities'] ?? 0),
                'mcp_public'    => (int) ($summary['mcp_public'] ?? 0),
                'public_writes' => (int) ($summary['public_writes'] ?? 0),
                'run_errors'    => (int) ($summary['run_errors'] ?? 0),
                'status'        => sanitize_key((string) ($summary['handoff_status'] ?? '')),
                'readiness_score' => absint($summary['readiness_score'] ?? 0),
                'unavailable_tests' => absint($summary['unavailable_tests'] ?? 0),
                'write_guards'   => absint($summary['write_guards'] ?? 0),
                'files'         => count($files),
                'bytes'         => $total_bytes,
                'checksum'      => $package_checksum,
            ],
            'manifest'         => $manifest,
            'files'            => $files,
        ];
    }

    private function build_agent_handoff_summary(array $summary, array $smoke_tests): array {
        $tests = is_array($smoke_tests['tests'] ?? null) ? $smoke_tests['tests'] : [];
        $unavailable_tests = [];
        $write_guard_tests = [];
        $public_write_tests = [];

        foreach ($tests as $test) {
            if (!is_array($test)) {
                continue;
            }

            $entry = [
                'id'                   => sanitize_key((string) ($test['id'] ?? '')),
                'phase'                => sanitize_key((string) ($test['phase'] ?? 'read_only')),
                'label'                => sanitize_text_field((string) ($test['label'] ?? '')),
                'ability'              => sanitize_text_field((string) ($test['ability'] ?? '')),
                'risk'                 => sanitize_key((string) ($test['risk'] ?? 'low')),
                'available'            => !empty($test['available']),
                'public_write_exposed' => !empty($test['public_write_exposed']),
            ];

            if (empty($entry['available'])) {
                $unavailable_tests[] = $entry;
            }

            if (($entry['phase'] ?? '') === 'write_guard') {
                $write_guard_tests[] = $entry;
            }

            if (!empty($entry['public_write_exposed'])) {
                $public_write_tests[] = $entry;
            }
        }

        $run_errors = (int) ($summary['run_errors'] ?? 0);
        $public_writes = (int) ($summary['public_writes'] ?? 0);
        $status = !empty($unavailable_tests) ? 'blocked' : (($run_errors > 0 || $public_writes > 0) ? 'review' : 'ready');
        $score = 100;
        if (!empty($unavailable_tests)) {
            $score -= min(50, count($unavailable_tests) * 10);
        }
        if ($run_errors > 0) {
            $score -= 10;
        }
        if ($public_writes > 0) {
            $score -= 15;
        }
        $score = max(0, min(100, $score));

        return [
            'schema_version'       => 'handoff-summary.v1',
            'source'               => 'wordpress_ability',
            'status'               => $status,
            'recommended_mode'     => $status === 'ready' ? 'preview_first' : ($status === 'blocked' ? 'read_only_only' : 'guarded_preview'),
            'score'                => $score,
            'next_action'          => $status === 'ready' ? 'run_preview_before_apply' : ($status === 'blocked' ? 'resolve_missing_abilities' : 'review_warnings'),
            'framework'            => sanitize_key((string) ($summary['framework'] ?? '')),
            'public_writes'        => $public_writes,
            'run_errors'           => $run_errors,
            'smoke_counts'         => is_array($smoke_tests['counts'] ?? null) ? $smoke_tests['counts'] : [],
            'unavailable_tests'    => $unavailable_tests,
            'write_guard_tests'    => $write_guard_tests,
            'public_write_tests'   => $public_write_tests,
        ];
    }

    private function encode_handoff_json($value): string {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '{}';
    }

    private function page_upsert_preview_properties(): array {
        return [
            'target_id' => [
                'type'        => 'integer',
                'minimum'     => 1,
                'description' => __('Existing WordPress page ID to update. Omit it to preview creating a page.', 'livecanvas-forge-ai'),
            ],
            'post_id' => [
                'type'        => 'integer',
                'minimum'     => 1,
                'description' => __('Alias for target_id when the current editor target is a post object.', 'livecanvas-forge-ai'),
            ],
            'title' => [
                'type'        => 'string',
                'description' => __('Page title for create previews or title overrides.', 'livecanvas-forge-ai'),
            ],
            'slug' => [
                'type'        => 'string',
                'description' => __('Optional page slug for create previews.', 'livecanvas-forge-ai'),
            ],
            'status' => [
                'type'        => 'string',
                'enum'        => ['draft', 'publish', 'private', 'pending'],
                'description' => __('Target post status to preview.', 'livecanvas-forge-ai'),
            ],
            'content' => [
                'type'        => 'string',
                'description' => __('Generated LiveCanvas-safe page HTML.', 'livecanvas-forge-ai'),
            ],
            'framework' => [
                'type'        => 'string',
                'description' => __('Optional framework override such as picostrap or picowind.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    private function global_shell_preview_properties(): array {
        return [
            'variant' => [
                'type'        => 'string',
                'description' => __('LiveCanvas header/footer variant to preview.', 'livecanvas-forge-ai'),
            ],
            'header_html' => [
                'type'        => 'string',
                'description' => __('Proposed header HTML.', 'livecanvas-forge-ai'),
            ],
            'footer_html' => [
                'type'        => 'string',
                'description' => __('Proposed footer HTML.', 'livecanvas-forge-ai'),
            ],
            'content' => [
                'type'        => 'string',
                'description' => __('Optional combined header/footer markup. Forge will extract the parts when possible.', 'livecanvas-forge-ai'),
            ],
            'framework' => [
                'type'        => 'string',
                'description' => __('Optional framework override such as picostrap or picowind.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    private function design_system_preview_properties(): array {
        return [
            'design_system' => [
                'type'        => 'object',
                'description' => __('Stack-native design tokens to preview applying.', 'livecanvas-forge-ai'),
            ],
            'colors' => [
                'type'        => 'object',
                'description' => __('Optional color token shortcut.', 'livecanvas-forge-ai'),
            ],
            'typography' => [
                'type'        => 'object',
                'description' => __('Optional typography token shortcut.', 'livecanvas-forge-ai'),
            ],
            'buttons' => [
                'type'        => 'object',
                'description' => __('Optional button token shortcut.', 'livecanvas-forge-ai'),
            ],
            'radius' => [
                'type'        => 'object',
                'description' => __('Optional radius token shortcut.', 'livecanvas-forge-ai'),
            ],
            'framework' => [
                'type'        => 'string',
                'description' => __('Optional framework override such as picostrap or picowind.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    private function ai_text_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['prompt'],
            'properties'           => [
                'prompt' => [
                    'type'        => 'string',
                    'description' => __('Prompt text to send through WordPress AI Client.', 'livecanvas-forge-ai'),
                ],
                'system_instruction' => [
                    'type'        => 'string',
                    'description' => __('Optional system instruction for the AI Client prompt builder.', 'livecanvas-forge-ai'),
                ],
                'temperature' => [
                    'type'        => 'number',
                    'minimum'     => 0,
                    'maximum'     => 2,
                    'description' => __('Optional temperature preference.', 'livecanvas-forge-ai'),
                ],
                'max_tokens' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => __('Optional max token preference.', 'livecanvas-forge-ai'),
                ],
                'model_preference' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => __('Optional ordered model preference list.', 'livecanvas-forge-ai'),
                ],
                'response_schema' => [
                    'type'        => 'object',
                    'description' => __('Optional JSON schema for structured text output.', 'livecanvas-forge-ai'),
                ],
            ],
        ];
    }

    private function empty_object_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [],
        ];
    }

    private function object_schema(): array {
        return [
            'type'                 => 'object',
            'additionalProperties' => true,
        ];
    }
}
