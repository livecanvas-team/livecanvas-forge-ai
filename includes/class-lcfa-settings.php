<?php

defined('ABSPATH') || exit;

final class LCFA_Settings {
    public const OPTION_KEY = 'lcfa_settings';
    public const BRIEF_OPTION_KEY = 'lcfa_project_brief';
    public const GENESIS_PLAN_OPTION_KEY = 'lcfa_genesis_plan';
    public const GENESIS_PROGRESS_OPTION_KEY = 'lcfa_genesis_progress';
    public const CONNECTIONS_OPTION_KEY = 'lcfa_connections';
    public const HISTORY_OPTION_KEY = 'lcfa_command_history';
    public const ROLLBACK_RECORDS_OPTION_KEY = 'lcfa_rollback_records';
    public const THREADS_OPTION_KEY = 'lcfa_command_threads';
    public const AGENT_REQUESTS_OPTION_KEY = 'lcfa_agent_requests';
    public const REDIRECT_OPTION_KEY = 'lcfa_do_activation_redirect';
    private const DEFAULT_THREAD_ID = 'default';
    private const NOTICE_PREFIX = 'lcfa_notice_';
    private const COMMAND_RESULT_PREFIX = 'lcfa_command_result_';
    private const COMMAND_SUGGESTION_PREFIX = 'lcfa_command_suggestion_';
    private const CONNECTION_TEST_PREFIX = 'lcfa_connection_test_';

    public static function defaults(): array {
        return [
            'wizard_version'      => 1,
            'completed'           => false,
            'framework'           => '',
            'site_mode'           => '',
            'ai_tool'             => '',
            'permission_profile'  => 'advanced_templates',
            'allow_file_fallback' => true,
            'last_completed_step' => 0,
        ];
    }

    public static function get(): array {
        $settings = get_option(self::OPTION_KEY, []);

        if (!is_array($settings)) {
            $settings = [];
        }

        return wp_parse_args($settings, self::defaults());
    }

    public static function update(array $settings): void {
        update_option(self::OPTION_KEY, wp_parse_args($settings, self::defaults()));
    }

    public static function patch(array $changes): array {
        $settings = array_merge(self::get(), $changes);
        self::update($settings);

        return $settings;
    }

    public static function get_project_brief(): array {
        $brief = get_option(self::BRIEF_OPTION_KEY, []);

        if (!is_array($brief)) {
            $brief = [];
        }

        return wp_parse_args($brief, [
            'project_mode'   => 'from_scratch',
            'brand_name'     => '',
            'sector'         => '',
            'tone'           => '',
            'logo_status'    => 'existing',
            'required_pages' => '',
            'notes'          => '',
        ]);
    }

    public static function update_project_brief(array $brief): void {
        update_option(self::BRIEF_OPTION_KEY, self::get_sanitized_project_brief($brief));
    }

    public static function get_project_brief_hash(?array $brief = null): string {
        $brief = is_array($brief) ? self::get_sanitized_project_brief($brief) : self::get_project_brief();

        return md5((string) wp_json_encode($brief));
    }

    public static function get_sanitized_project_brief(array $brief): array {
        return [
            'project_mode'   => in_array($brief['project_mode'] ?? '', ['from_scratch', 'step_by_step'], true) ? $brief['project_mode'] : 'from_scratch',
            'brand_name'     => sanitize_text_field($brief['brand_name'] ?? ''),
            'sector'         => sanitize_text_field($brief['sector'] ?? ''),
            'tone'           => sanitize_text_field($brief['tone'] ?? ''),
            'logo_status'    => in_array($brief['logo_status'] ?? '', ['existing', 'to_generate', 'not_needed'], true) ? $brief['logo_status'] : 'existing',
            'required_pages' => sanitize_textarea_field($brief['required_pages'] ?? ''),
            'notes'          => sanitize_textarea_field($brief['notes'] ?? ''),
        ];
    }

    public static function get_connections(): array {
        $stored_connections = get_option(self::CONNECTIONS_OPTION_KEY, []);
        $connections = $stored_connections;

        if (!is_array($connections)) {
            $connections = [];
        }

        $connections = wp_parse_args($connections, self::connection_defaults());
        $connections = self::normalize_connections_snapshot($connections);

        if (empty($connections['mcp_token'])) {
            $connections['mcp_token'] = self::generate_mcp_token();
            update_option(self::CONNECTIONS_OPTION_KEY, $connections);
        } elseif ($stored_connections !== $connections) {
            update_option(self::CONNECTIONS_OPTION_KEY, $connections);
        }

        return $connections;
    }

    public static function update_connections(array $connections): void {
        update_option(self::CONNECTIONS_OPTION_KEY, self::sanitize_connections($connections));
    }

    public static function sync_local_workspace_root(bool $force = false): array {
        $connections = self::get_connections();
        $normalized = self::normalize_local_workspace_root($connections, $force);

        if ($normalized !== $connections) {
            update_option(self::CONNECTIONS_OPTION_KEY, $normalized);
        }

        return $normalized;
    }

    public static function normalize_local_workspace_root(array $connections, bool $force = false): array {
        $wp_root = defined('ABSPATH') && is_string(ABSPATH) ? rtrim((string) ABSPATH, '/\\') : '';
        if ($wp_root === '' || !self::looks_like_wordpress_root($wp_root)) {
            return $connections;
        }

        $mode = sanitize_key((string) ($connections['connection_mode'] ?? ''));
        if (!$force && $mode !== 'local' && !self::looks_like_local_site_url(home_url('/'))) {
            return $connections;
        }

        $workspace_root = untrailingslashit(trim((string) ($connections['workspace_root'] ?? '')));
        if ($workspace_root !== '' && self::looks_like_wordpress_root($workspace_root)) {
            return $connections;
        }

        $connections['workspace_root'] = $wp_root;
        $connections['connection_last_bundle_hash'] = '';
        if ((string) ($connections['connection_status'] ?? '') === 'ready') {
            $connections['connection_status'] = 'needs_attention';
            $connections['connection_last_error'] = __('Workspace root changed. Sync Codex config and rerun the smoke test.', 'livecanvas-forge-ai');
            $connections['connection_current_step'] = 'smoke_test';
        }

        return $connections;
    }

    public static function get_public_connections(): array {
        $connections = self::get_connections();
        $connections['remote_application_password'] = $connections['remote_application_password'] !== '' ? 'stored' : '';
        $connections['mcp_token'] = $connections['mcp_token'] !== '' ? 'generated' : '';

        return $connections;
    }

    public static function connection_defaults(): array {
        return [
            'transport'                   => 'rest',
            'picowind_package_url'        => '',
            'picostrap_package_url'       => '',
            'local_bridge_url'            => rest_url('lcfa/v1/'),
            'mcp_enabled'                 => true,
            'mcp_write_abilities_enabled' => false,
            'mcp_public_write_abilities'  => [],
            'mcp_public_write_abilities_configured' => false,
            'mcp_host'                    => '127.0.0.1',
            'mcp_port'                    => '7681',
            'mcp_token'                   => self::generate_mcp_token(),
            'remote_site_url'             => '',
            'remote_username'             => '',
            'remote_application_password' => '',
            'mcp_server_command'          => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
            'preferred_client'            => '',
            'claude_connection_target'    => '',
            'workspace_root'              => '',
            'codex_config_scope'          => 'project',
            'codex_model'                 => 'gpt-5.3-codex-spark',
            'codex_speed'                 => 'balanced',
            'codex_reasoning_effort'      => 'medium',
            'codex_sandbox'               => '',
            'power_mode'                  => 'auto',
            'connection_status'           => '',
            'connection_mode'             => '',
            'connection_last_verified_at' => '',
            'connection_last_error'       => '',
            'connection_last_bundle_hash' => '',
            'connection_current_step'     => '',
            'framework_change_pending'    => false,
            'framework_change_previous'   => '',
            'framework_change_next'       => '',
        ];
    }

    public static function sanitize_connections(array $connections): array {
        $current = self::get_connections();
        $raw_client = sanitize_key((string) ($connections['preferred_client'] ?? $current['preferred_client']));
        $preferred_client = self::normalize_preferred_client($raw_client);
        $claude_connection_target = self::normalize_claude_connection_target(
            (string) ($connections['claude_connection_target'] ?? ($current['claude_connection_target'] ?? '')),
            $preferred_client,
            $raw_client
        );
        $password = isset($connections['remote_application_password'])
            ? sanitize_text_field($connections['remote_application_password'])
            : $current['remote_application_password'];

        if ($password === '') {
            $password = $current['remote_application_password'];
        }

        $mcp_token = isset($connections['mcp_token'])
            ? sanitize_text_field($connections['mcp_token'])
            : $current['mcp_token'];

        if ($mcp_token === '') {
            $mcp_token = $current['mcp_token'] ?: self::generate_mcp_token();
        }

        $mcp_port = absint($connections['mcp_port'] ?? $current['mcp_port']);
        if ($mcp_port < 1 || $mcp_port > 65535) {
            $mcp_port = 7681;
        }
        $codex_options = self::sanitize_codex_options([
            'model'            => $connections['codex_model'] ?? $current['codex_model'] ?? '',
            'speed'            => $connections['codex_speed'] ?? $current['codex_speed'] ?? '',
            'reasoning_effort' => $connections['codex_reasoning_effort'] ?? $current['codex_reasoning_effort'] ?? '',
            'sandbox'          => $connections['codex_sandbox'] ?? $current['codex_sandbox'] ?? '',
        ]);
        $codex_options['model'] = $codex_options['model'] !== '' ? $codex_options['model'] : 'gpt-5.3-codex-spark';
        $codex_options['speed'] = $codex_options['speed'] !== '' ? $codex_options['speed'] : 'balanced';
        $codex_options['reasoning_effort'] = $codex_options['reasoning_effort'] !== '' ? $codex_options['reasoning_effort'] : 'medium';
        $connection_current_step = (string) ($connections['connection_current_step'] ?? '');
        $framework_change_previous = self::normalize_framework_key((string) ($connections['framework_change_previous'] ?? ''));
        $framework_change_next = self::normalize_framework_key((string) ($connections['framework_change_next'] ?? ''));
        $write_abilities_submitted = !empty($connections['mcp_public_write_abilities_submitted']);
        $public_write_abilities = self::sanitize_mcp_write_abilities($connections['mcp_public_write_abilities'] ?? []);

        if (!array_key_exists('mcp_public_write_abilities', $connections) && !$write_abilities_submitted) {
            $public_write_abilities = self::sanitize_mcp_write_abilities($current['mcp_public_write_abilities'] ?? []);
        }

        if (!empty($connections['mcp_write_abilities_enabled']) && !$write_abilities_submitted && $public_write_abilities === [] && empty($current['mcp_public_write_abilities'])) {
            $public_write_abilities = array_keys(self::get_mcp_write_ability_options());
        }
        $public_write_abilities_configured = $write_abilities_submitted
            ? true
            : !empty($current['mcp_public_write_abilities_configured']);

        return [
            'transport'                   => in_array($connections['transport'] ?? '', ['rest', 'mcp', 'hybrid'], true) ? $connections['transport'] : 'rest',
            'picowind_package_url'        => esc_url_raw($connections['picowind_package_url'] ?? ''),
            'picostrap_package_url'       => esc_url_raw($connections['picostrap_package_url'] ?? ''),
            'local_bridge_url'            => esc_url_raw($connections['local_bridge_url'] ?? rest_url('lcfa/v1/')),
            'mcp_enabled'                 => !empty($connections['mcp_enabled']),
            'mcp_write_abilities_enabled' => !empty($connections['mcp_write_abilities_enabled']),
            'mcp_public_write_abilities'  => $public_write_abilities,
            'mcp_public_write_abilities_configured' => $public_write_abilities_configured,
            'mcp_host'                    => sanitize_text_field($connections['mcp_host'] ?? '127.0.0.1'),
            'mcp_port'                    => (string) $mcp_port,
            'mcp_token'                   => $mcp_token,
            'remote_site_url'             => esc_url_raw($connections['remote_site_url'] ?? ''),
            'remote_username'             => sanitize_text_field($connections['remote_username'] ?? ''),
            'remote_application_password' => $password,
            'mcp_server_command'          => sanitize_textarea_field($connections['mcp_server_command'] ?? ''),
            'preferred_client'            => $preferred_client,
            'claude_connection_target'    => $claude_connection_target,
            'workspace_root'              => sanitize_text_field($connections['workspace_root'] ?? ''),
            'codex_config_scope'          => self::sanitize_codex_config_scope((string) ($connections['codex_config_scope'] ?? ($current['codex_config_scope'] ?? 'project'))),
            'codex_model'                 => $codex_options['model'],
            'codex_speed'                 => $codex_options['speed'],
            'codex_reasoning_effort'      => $codex_options['reasoning_effort'],
            'codex_sandbox'               => $codex_options['sandbox'],
            'power_mode'                  => self::sanitize_power_mode((string) ($connections['power_mode'] ?? ($current['power_mode'] ?? 'auto'))),
            'connection_status'           => sanitize_key($connections['connection_status'] ?? ''),
            'connection_mode'             => in_array($connections['connection_mode'] ?? '', ['local', 'remote'], true) ? $connections['connection_mode'] : '',
            'connection_last_verified_at' => sanitize_text_field($connections['connection_last_verified_at'] ?? ''),
            'connection_last_error'       => sanitize_text_field($connections['connection_last_error'] ?? ''),
            'connection_last_bundle_hash' => sanitize_text_field($connections['connection_last_bundle_hash'] ?? ''),
            'connection_current_step'     => in_array($connection_current_step, ['', 'choose_client', 'choose_claude_target', 'choose_mode', 'confirm_details', 'generate_bundle', 'smoke_test', 'ready'], true) ? $connection_current_step : '',
            'framework_change_pending'    => !empty($connections['framework_change_pending']) && $framework_change_previous !== '' && $framework_change_next !== '' && $framework_change_previous !== $framework_change_next,
            'framework_change_previous'   => $framework_change_previous,
            'framework_change_next'       => $framework_change_next,
        ];
    }

    public static function sanitize_codex_config_scope(string $scope): string {
        $scope = sanitize_key($scope);

        return in_array($scope, ['project', 'global'], true) ? $scope : 'project';
    }

    public static function get_site_fingerprint(): string {
        $wp_root = defined('ABSPATH') && is_string(ABSPATH) ? rtrim((string) ABSPATH, '/\\') : '';
        $payload = [
            'site_url'  => function_exists('home_url') ? rtrim(home_url('/'), '/') . '/' : '',
            'rest_base' => function_exists('rest_url') ? rtrim(rest_url('lcfa/v1/'), '/') . '/' : '',
            'wp_root'   => $wp_root,
        ];

        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', (string) $encoded), 0, 16);
    }

    public static function get_codex_model_options(): array {
        return [
            ''                     => 'Use Codex default',
            'gpt-5.3-codex-spark' => 'GPT-5.3 Codex Spark',
            'gpt-5.4-mini'        => 'GPT-5.4 Mini',
            'gpt-5.3-codex'       => 'GPT-5.3 Codex',
            'gpt-5.4'             => 'GPT-5.4',
            'gpt-5.5'             => 'GPT-5.5',
            'gpt-5.2'             => 'GPT-5.2',
        ];
    }

    public static function get_mcp_write_ability_options(): array {
        return [
            'livecanvas-forge-ai/apply-page-upsert' => [
                'label'       => self::translate('Apply page upsert'),
                'description' => self::translate('Create or update LiveCanvas pages.'),
            ],
            'livecanvas-forge-ai/apply-native-pattern-page' => [
                'label'       => self::translate('Apply native pattern page'),
                'description' => self::translate('Create a draft WordPress-native page from AI Bridge block patterns.'),
            ],
            'livecanvas-forge-ai/apply-global-shell' => [
                'label'       => self::translate('Apply global shell'),
                'description' => self::translate('Create or update header/footer partials.'),
            ],
            'livecanvas-forge-ai/apply-dynamic-template' => [
                'label'       => self::translate('Apply dynamic template'),
                'description' => self::translate('Create or update LiveCanvas dynamic templates.'),
            ],
            'livecanvas-forge-ai/apply-design-system' => [
                'label'       => self::translate('Apply design system'),
                'description' => self::translate('Apply stack-native design tokens and related runtime assets.'),
            ],
            'livecanvas-forge-ai/restore-audit-rollback' => [
                'label'       => self::translate('Restore audit rollback'),
                'description' => self::translate('Restore or trash content from a stored audit rollback record.'),
            ],
        ];
    }

    public static function sanitize_mcp_write_abilities($abilities): array {
        $allowed = array_keys(self::get_mcp_write_ability_options());
        $abilities = is_array($abilities) ? $abilities : [];

        return array_values(array_intersect($allowed, array_values(array_unique(array_map(static function ($ability): string {
            return sanitize_text_field((string) $ability);
        }, $abilities)))));
    }

    public static function get_codex_reasoning_effort_options(): array {
        return [
            ''       => 'Use Codex default',
            'low'    => 'Low',
            'medium' => 'Medium',
            'high'   => 'High',
            'xhigh'  => 'Max',
        ];
    }

    public static function get_codex_speed_options(): array {
        return [
            ''         => 'Use AI Bridge default',
            'fast'     => 'Fast',
            'balanced' => 'Balanced',
            'thorough' => 'Thorough',
        ];
    }

    public static function get_codex_sandbox_options(): array {
        return [
            ''                   => 'Plugin default',
            'danger-full-access' => 'Full access',
            'workspace-write'    => 'Workspace write',
            'read-only'          => 'Read only',
        ];
    }

    public static function get_power_mode_options(): array {
        return [
            'auto'     => self::translate('Auto: local/development only'),
            'enabled'  => self::translate('Enabled by administrator'),
            'disabled' => self::translate('Disabled'),
        ];
    }

    public static function sanitize_power_mode(string $mode): string {
        $mode = sanitize_key($mode);

        return array_key_exists($mode, self::get_power_mode_options()) ? $mode : 'auto';
    }

    public static function sanitize_codex_options(array $options): array {
        $nested = is_array($options['codex_options'] ?? null) ? $options['codex_options'] : [];
        $model = sanitize_text_field((string) ($options['model'] ?? $options['codex_model'] ?? $nested['model'] ?? ''));
        $speed = sanitize_key((string) ($options['speed'] ?? $options['codex_speed'] ?? $nested['speed'] ?? ''));
        $reasoning_effort = sanitize_key((string) ($options['reasoning_effort'] ?? $options['codex_reasoning_effort'] ?? $nested['reasoning_effort'] ?? ''));
        $sandbox = sanitize_key((string) ($options['sandbox'] ?? $options['codex_sandbox'] ?? $nested['sandbox'] ?? ''));

        return [
            'model'            => array_key_exists($model, self::get_codex_model_options()) ? $model : '',
            'speed'            => array_key_exists($speed, self::get_codex_speed_options()) ? $speed : '',
            'reasoning_effort' => array_key_exists($reasoning_effort, self::get_codex_reasoning_effort_options()) ? $reasoning_effort : '',
            'sandbox'          => array_key_exists($sandbox, self::get_codex_sandbox_options()) ? $sandbox : '',
        ];
    }

    private static function normalize_framework_key(string $value): string {
        $value = sanitize_key($value);

        return in_array($value, ['picostrap', 'picowind'], true) ? $value : '';
    }

    private static function translate(string $text): string {
        return function_exists('__') ? __($text, 'livecanvas-forge-ai') : $text;
    }

    private static function normalize_connections_snapshot(array $connections): array {
        $raw_client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        $preferred_client = self::normalize_preferred_client($raw_client);

        $connections['preferred_client'] = $preferred_client;
        $connections['claude_connection_target'] = self::normalize_claude_connection_target(
            (string) ($connections['claude_connection_target'] ?? ''),
            $preferred_client,
            $raw_client
        );
        $connections['mcp_write_abilities_enabled'] = !empty($connections['mcp_write_abilities_enabled']);
        $connections['mcp_public_write_abilities'] = self::sanitize_mcp_write_abilities($connections['mcp_public_write_abilities'] ?? []);
        $connections['mcp_public_write_abilities_configured'] = !empty($connections['mcp_public_write_abilities_configured']);
        if (!empty($connections['mcp_write_abilities_enabled']) && empty($connections['mcp_public_write_abilities_configured']) && $connections['mcp_public_write_abilities'] === []) {
            $connections['mcp_public_write_abilities'] = array_keys(self::get_mcp_write_ability_options());
        }
        $codex_options = self::sanitize_codex_options([
            'model'            => $connections['codex_model'] ?? '',
            'speed'            => $connections['codex_speed'] ?? '',
            'reasoning_effort' => $connections['codex_reasoning_effort'] ?? '',
            'sandbox'          => $connections['codex_sandbox'] ?? '',
        ]);
        $connections['codex_model'] = $codex_options['model'] !== '' ? $codex_options['model'] : 'gpt-5.3-codex-spark';
        $connections['codex_speed'] = $codex_options['speed'] !== '' ? $codex_options['speed'] : 'balanced';
        $connections['codex_reasoning_effort'] = $codex_options['reasoning_effort'] !== '' ? $codex_options['reasoning_effort'] : 'medium';
        $connections['codex_sandbox'] = $codex_options['sandbox'];
        $connections['power_mode'] = self::sanitize_power_mode((string) ($connections['power_mode'] ?? 'auto'));

        return $connections;
    }

    private static function normalize_preferred_client(string $client): string {
        if ($client === 'other') {
            return 'generic';
        }

        if ($client === 'claude-code') {
            return 'claude';
        }

        return in_array($client, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true) ? $client : '';
    }

    private static function normalize_claude_connection_target(string $target, string $preferred_client, string $raw_client): string {
        if ($raw_client === 'claude-code') {
            return 'cli';
        }

        if ($preferred_client !== 'claude') {
            return '';
        }

        $target = sanitize_key($target);

        return in_array($target, ['desktop_app', 'cli'], true) ? $target : '';
    }

    public static function rotate_mcp_token(): array {
        $connections = self::get_connections();
        $connections['mcp_token'] = self::generate_mcp_token();
        $connections['connection_last_bundle_hash'] = '';
        $connections['connection_status'] = '';
        $connections['connection_last_verified_at'] = '';
        $connections['connection_last_error'] = __('MCP token rotated. Sync Codex config and rerun the smoke test.', 'livecanvas-forge-ai');
        $connections['connection_current_step'] = trim((string) ($connections['preferred_client'] ?? '')) !== '' ? 'smoke_test' : 'choose_client';
        self::update_connections($connections);

        if (function_exists('delete_transient')) {
            delete_transient('lcfa_local_mcp_status_' . md5(home_url('/') . '|' . LCFA_VERSION));
        }

        return self::get_connections();
    }

    public static function get_mcp_endpoint(): string {
        $connections = self::get_connections();

        return sprintf(
            'ws://%1$s:%2$s',
            $connections['mcp_host'] ?: '127.0.0.1',
            $connections['mcp_port'] ?: '7681'
        );
    }

    private static function generate_mcp_token(): string {
        return wp_generate_password(32, false, false);
    }

    private static function looks_like_wordpress_root(string $path): bool {
        $path = untrailingslashit($path);

        return $path !== '' && (is_file($path . '/wp-load.php') || is_dir($path . '/wp-content'));
    }

    private static function looks_like_local_site_url(string $url): bool {
        return (bool) preg_match('#://(?:localhost|127\.0\.0\.1|\[::1\])(?::|/)|\.local(?::|/)|\.test(?::|/)#i', $url);
    }

    public static function get_genesis_plan(): array {
        $plan = get_option(self::GENESIS_PLAN_OPTION_KEY, []);

        return is_array($plan) ? $plan : [];
    }

    public static function update_genesis_plan(array $plan): void {
        update_option(self::GENESIS_PLAN_OPTION_KEY, $plan);
    }

    public static function clear_genesis_plan(): void {
        delete_option(self::GENESIS_PLAN_OPTION_KEY);
    }

    public static function get_genesis_progress(): array {
        $progress = get_option(self::GENESIS_PROGRESS_OPTION_KEY, []);

        if (!is_array($progress)) {
            $progress = [];
        }

        $progress = wp_parse_args($progress, [
            'brief_hash' => self::get_project_brief_hash(),
            'tasks'      => [],
        ]);

        if (!is_array($progress['tasks'])) {
            $progress['tasks'] = [];
        }

        return [
            'brief_hash' => sanitize_text_field((string) ($progress['brief_hash'] ?? self::get_project_brief_hash())),
            'tasks'      => array_reduce(array_keys($progress['tasks']), static function (array $carry, string $task_id) use ($progress): array {
                $normalized_id = sanitize_key($task_id);
                $task          = is_array($progress['tasks'][$task_id] ?? null) ? $progress['tasks'][$task_id] : [];

                if ($normalized_id === '') {
                    return $carry;
                }

                $carry[$normalized_id] = [
                    'status'       => in_array($task['status'] ?? '', ['pending', 'previewed', 'applied', 'failed'], true) ? (string) $task['status'] : 'pending',
                    'updated_at'   => sanitize_text_field((string) ($task['updated_at'] ?? '')),
                    'thread_id'    => self::normalize_thread_id((string) ($task['thread_id'] ?? 'default')),
                    'action'       => sanitize_key((string) ($task['action'] ?? '')),
                    'mode'         => sanitize_key((string) ($task['mode'] ?? '')),
                    'ok'           => !empty($task['ok']),
                    'message'      => sanitize_textarea_field((string) ($task['message'] ?? '')),
                    'target_type'  => sanitize_key((string) ($task['target_type'] ?? '')),
                    'target_id'    => absint($task['target_id'] ?? 0),
                    'target_title' => sanitize_text_field((string) ($task['target_title'] ?? '')),
                ];

                return $carry;
            }, []),
        ];
    }

    public static function update_genesis_progress(array $progress): void {
        update_option(self::GENESIS_PROGRESS_OPTION_KEY, [
            'brief_hash' => sanitize_text_field((string) ($progress['brief_hash'] ?? self::get_project_brief_hash())),
            'tasks'      => is_array($progress['tasks'] ?? null) ? $progress['tasks'] : [],
        ]);
    }

    public static function clear_genesis_progress(): void {
        delete_option(self::GENESIS_PROGRESS_OPTION_KEY);
    }

    public static function reset_setup_state(): void {
        self::update(self::defaults());
        self::update_connections(self::connection_defaults());
        self::clear_runtime_feedback();
    }

    public static function update_genesis_task_progress(string $task_id, array $state, ?string $brief_hash = null): array {
        $normalized_task_id = sanitize_key($task_id);

        if ($normalized_task_id === '') {
            return self::get_genesis_progress();
        }

        $progress              = self::get_genesis_progress();
        $progress['brief_hash'] = sanitize_text_field((string) ($brief_hash ?: self::get_project_brief_hash()));
        $progress['tasks'][$normalized_task_id] = [
            'status'       => in_array($state['status'] ?? '', ['pending', 'previewed', 'applied', 'failed'], true) ? (string) $state['status'] : 'pending',
            'updated_at'   => sanitize_text_field((string) ($state['updated_at'] ?? current_time('mysql', true))),
            'thread_id'    => self::normalize_thread_id((string) ($state['thread_id'] ?? 'default')),
            'action'       => sanitize_key((string) ($state['action'] ?? '')),
            'mode'         => sanitize_key((string) ($state['mode'] ?? '')),
            'ok'           => !empty($state['ok']),
            'message'      => sanitize_textarea_field((string) ($state['message'] ?? '')),
            'target_type'  => sanitize_key((string) ($state['target_type'] ?? '')),
            'target_id'    => absint($state['target_id'] ?? 0),
            'target_title' => sanitize_text_field((string) ($state['target_title'] ?? '')),
        ];

        self::update_genesis_progress($progress);

        return self::get_genesis_progress();
    }

    public static function get_history(): array {
        $history = get_option(self::HISTORY_OPTION_KEY, []);

        return is_array($history) ? $history : [];
    }

    public static function append_history(array $entry): void {
        $history = self::get_history();
        array_unshift($history, $entry);
        $history = array_slice($history, 0, 40);

        update_option(self::HISTORY_OPTION_KEY, $history);
    }

    public static function get_rollback_records(): array {
        $records = get_option(self::ROLLBACK_RECORDS_OPTION_KEY, []);

        return is_array($records) ? $records : [];
    }

    public static function get_rollback_record(string $audit_id): array {
        $audit_id = sanitize_key($audit_id);
        $records = self::get_rollback_records();
        $record = $records[$audit_id] ?? [];

        return is_array($record) ? $record : [];
    }

    public static function store_rollback_record(string $audit_id, array $record): void {
        $audit_id = sanitize_key($audit_id);

        if ($audit_id === '') {
            return;
        }

        $records = self::get_rollback_records();
        $records[$audit_id] = $record;
        $records = array_slice($records, -40, 40, true);

        update_option(self::ROLLBACK_RECORDS_OPTION_KEY, $records);
    }

    public static function mark_rollback_record_restored(string $audit_id, array $restore_result): void {
        $audit_id = sanitize_key($audit_id);
        $records = self::get_rollback_records();

        if ($audit_id === '' || !isset($records[$audit_id]) || !is_array($records[$audit_id])) {
            return;
        }

        $records[$audit_id]['restored_at'] = current_time('mysql', true);
        $records[$audit_id]['restore_result'] = [
            'ok'      => !empty($restore_result['ok']),
            'message' => sanitize_text_field((string) ($restore_result['message'] ?? '')),
        ];

        update_option(self::ROLLBACK_RECORDS_OPTION_KEY, $records);
    }

    public static function get_threads(): array {
        $threads = get_option(self::THREADS_OPTION_KEY, []);

        if (!is_array($threads)) {
            $threads = [];
        }

        $normalized = [];

        foreach ($threads as $thread_id => $thread) {
            if (!is_array($thread)) {
                continue;
            }

            $normalized_id = self::normalize_thread_id((string) $thread_id);
            $normalized[$normalized_id] = self::normalize_thread($thread, $normalized_id);
        }

        if (!isset($normalized[self::DEFAULT_THREAD_ID])) {
            $normalized[self::DEFAULT_THREAD_ID] = self::default_thread();
        }

        update_option(self::THREADS_OPTION_KEY, self::trim_threads($normalized));

        return self::sort_threads_by_updated_at((array) get_option(self::THREADS_OPTION_KEY, $normalized));
    }

    public static function get_thread_summaries(): array {
        $summaries = [];

        foreach (self::get_threads() as $thread) {
            $messages = is_array($thread['messages'] ?? null) ? $thread['messages'] : [];
            $last_message = $messages ? $messages[0] : null;

            $summaries[] = [
                'id'            => (string) ($thread['id'] ?? ''),
                'title'         => (string) ($thread['title'] ?? ''),
                'created_at'    => (string) ($thread['created_at'] ?? ''),
                'updated_at'    => (string) ($thread['updated_at'] ?? ''),
                'message_count' => count($messages),
                'last_role'     => is_array($last_message) ? (string) ($last_message['role'] ?? '') : '',
                'last_preview'  => is_array($last_message)
                    ? (function_exists('wp_trim_words')
                        ? wp_trim_words((string) ($last_message['content'] ?? ''), 14, '...')
                        : substr(trim((string) ($last_message['content'] ?? '')), 0, 120))
                    : '',
            ];
        }

        return $summaries;
    }

    public static function get_thread(string $thread_id = ''): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads       = self::get_threads();

        if (!isset($threads[$normalized_id])) {
            return $threads[self::DEFAULT_THREAD_ID] ?? self::default_thread();
        }

        return $threads[$normalized_id];
    }

    public static function create_thread(string $title = ''): array {
        $threads    = self::get_threads();
        $timestamp  = current_time('mysql', true);
        $thread_id  = self::generate_thread_id($threads);
        $thread     = [
            'id'         => $thread_id,
            'title'      => $title !== '' ? sanitize_text_field($title) : __('New command thread', 'livecanvas-forge-ai'),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'messages'   => [
                [
                    'id'      => 'system-' . strtolower(wp_generate_password(6, false, false)),
                    'role'    => 'system',
                    'label'   => __('Thread created', 'livecanvas-forge-ai'),
                    'time'    => $timestamp,
                    'content' => __('This thread stores request context, execution notes, and results for the current Command Deck workflow.', 'livecanvas-forge-ai'),
                    'meta'    => [],
                ],
            ],
        ];

        $threads[$thread_id] = $thread;
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread($thread_id);
    }

    public static function duplicate_thread(string $thread_id, string $title = ''): array {
        $source_thread_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads = self::get_threads();
        $source = $threads[$source_thread_id] ?? self::default_thread();
        $timestamp = current_time('mysql', true);
        $new_thread_id = self::generate_thread_id($threads);
        $source_title = sanitize_text_field((string) ($source['title'] ?? __('Command thread', 'livecanvas-forge-ai')));

        $thread = [
            'id'         => $new_thread_id,
            'title'      => $title !== '' ? sanitize_text_field($title) : sprintf(__('%s copy', 'livecanvas-forge-ai'), $source_title),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'messages'   => array_slice((array) ($source['messages'] ?? []), -80),
        ];

        $threads[$new_thread_id] = self::normalize_thread($thread, $new_thread_id);
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread($new_thread_id);
    }

    public static function clear_thread(string $thread_id): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads = self::get_threads();
        $thread = $threads[$normalized_id] ?? ($normalized_id === self::DEFAULT_THREAD_ID ? self::default_thread() : [
            'id'         => $normalized_id,
            'title'      => __('Command thread', 'livecanvas-forge-ai'),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
            'messages'   => [],
        ]);

        $thread['messages'] = [];
        $thread['updated_at'] = current_time('mysql', true);
        $thread['title'] = sanitize_text_field((string) ($thread['title'] ?? __('Command thread', 'livecanvas-forge-ai')));
        $thread['created_at'] = sanitize_text_field((string) ($thread['created_at'] ?? current_time('mysql', true)));
        $thread['id'] = $normalized_id;

        $threads[$normalized_id] = self::normalize_thread($thread, $normalized_id);
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread($normalized_id);
    }

    public static function rename_thread(string $thread_id, string $title = ''): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads = self::get_threads();
        $thread = $threads[$normalized_id] ?? ($normalized_id === self::DEFAULT_THREAD_ID ? self::default_thread() : [
            'id'         => $normalized_id,
            'title'      => __('Command thread', 'livecanvas-forge-ai'),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
            'messages'   => [],
        ]);
        $next_title = sanitize_text_field($title);

        if ($next_title === '') {
            $next_title = sanitize_text_field((string) ($thread['title'] ?? __('Command thread', 'livecanvas-forge-ai')));
        }

        $thread['title'] = $next_title;
        $thread['updated_at'] = current_time('mysql', true);
        $thread['created_at'] = sanitize_text_field((string) ($thread['created_at'] ?? current_time('mysql', true)));
        $thread['id'] = $normalized_id;

        $threads[$normalized_id] = self::normalize_thread($thread, $normalized_id);
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread($normalized_id);
    }

    public static function delete_thread(string $thread_id): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);

        if ($normalized_id === self::DEFAULT_THREAD_ID) {
            return self::get_thread(self::DEFAULT_THREAD_ID);
        }

        $threads = self::get_threads();
        unset($threads[$normalized_id]);
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread(self::DEFAULT_THREAD_ID);
    }

    public static function append_thread_message(string $thread_id, array $message): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads       = self::get_threads();
        $thread        = $threads[$normalized_id] ?? self::default_thread();
        $messages      = is_array($thread['messages'] ?? null) ? $thread['messages'] : [];
        $timestamp     = current_time('mysql', true);
        $meta          = is_array($message['meta'] ?? null) ? $message['meta'] : [];
        $valid_roles   = ['user', 'assistant', 'suggestion_result', 'system', 'tool_result'];

        $messages[] = [
            'id'      => sanitize_key((string) ($message['id'] ?? ('msg-' . strtolower(wp_generate_password(10, false, false))))),
            'role'    => in_array($message['role'] ?? '', $valid_roles, true) ? (string) $message['role'] : 'assistant',
            'label'   => sanitize_text_field((string) ($message['label'] ?? '')),
            'time'    => sanitize_text_field((string) ($message['time'] ?? $timestamp)),
            'content' => sanitize_textarea_field((string) ($message['content'] ?? '')),
            'meta'    => self::sanitize_thread_meta($meta),
            'attachments' => self::sanitize_thread_attachments((array) ($message['attachments'] ?? [])),
            'actions' => self::sanitize_thread_actions((array) ($message['actions'] ?? [])),
        ];

        $messages = array_slice($messages, -80);

        $thread['messages']   = $messages;
        $thread['updated_at'] = $timestamp;
        $thread['title']      = sanitize_text_field((string) ($thread['title'] ?? __('Command thread', 'livecanvas-forge-ai')));
        $thread['created_at'] = sanitize_text_field((string) ($thread['created_at'] ?? $timestamp));
        $thread['id']         = $normalized_id;

        $threads[$normalized_id] = $thread;
        update_option(self::THREADS_OPTION_KEY, self::trim_threads($threads));

        return self::get_thread($normalized_id);
    }

    public static function get_agent_requests(): array {
        $requests = get_option(self::AGENT_REQUESTS_OPTION_KEY, []);

        if (!is_array($requests)) {
            $requests = [];
        }

        $normalized = [];

        foreach ($requests as $request_id => $request) {
            if (!is_array($request)) {
                continue;
            }

            $normalized_request = self::normalize_agent_request($request, (string) $request_id);

            if (($normalized_request['id'] ?? '') === '') {
                continue;
            }

            $normalized[$normalized_request['id']] = $normalized_request;
        }

        $normalized = self::trim_agent_requests($normalized);
        update_option(self::AGENT_REQUESTS_OPTION_KEY, $normalized);

        return $normalized;
    }

    public static function get_agent_request(string $request_id): ?array {
        $request_id = sanitize_key($request_id);

        if ($request_id === '') {
            return null;
        }

        $requests = self::get_agent_requests();

        return isset($requests[$request_id]) && is_array($requests[$request_id]) ? $requests[$request_id] : null;
    }

    public static function enqueue_agent_request(array $payload): array {
        $requests    = self::get_agent_requests();
        $connections = self::get_connections();
        $agent       = self::normalize_agent_client((string) ($payload['agent'] ?? $connections['preferred_client'] ?? 'codex'));
        $timestamp   = current_time('mysql', true);
        $request_id  = self::generate_agent_request_id($requests);
        $thread_id   = self::normalize_thread_id((string) ($payload['thread_id'] ?? 'default'));
        $user_prompt = sanitize_textarea_field((string) ($payload['user_prompt'] ?? $payload['message'] ?? ''));
        $attachments = self::sanitize_thread_attachments((array) ($payload['attachments'] ?? []));
        $codex_options = self::sanitize_codex_options($payload);
        $codex_options['model'] = $codex_options['model'] !== '' ? $codex_options['model'] : (string) ($connections['codex_model'] ?? 'gpt-5.3-codex-spark');
        $codex_options['speed'] = $codex_options['speed'] !== '' ? $codex_options['speed'] : (string) ($connections['codex_speed'] ?? 'balanced');
        $codex_options['reasoning_effort'] = $codex_options['reasoning_effort'] !== '' ? $codex_options['reasoning_effort'] : (string) ($connections['codex_reasoning_effort'] ?? 'medium');
        $codex_options = self::sanitize_codex_options($codex_options);
        $ability_contract = self::agent_ability_contract_for((string) ($payload['action'] ?? 'page_upsert'));
        $payload['ability_contract'] = $ability_contract;

        $request = [
            'id'               => $request_id,
            'status'           => 'queued',
            'agent'            => $agent,
            'queued_for'       => self::agent_processor_for($agent),
            'thread_id'        => $thread_id,
            'user_prompt'      => $user_prompt,
            'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? 'local')),
            'post_id'          => absint($payload['post_id'] ?? 0),
            'context_post_id'  => absint($payload['context_post_id'] ?? 0),
            'target_id'        => absint($payload['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($payload['variant'] ?? '1')),
            'action'           => sanitize_key((string) ($payload['action'] ?? 'page_upsert')),
            'ability_contract' => $ability_contract,
            'codex_options'    => $codex_options,
            'payload'          => self::sanitize_agent_payload($payload),
            'attachments'      => $attachments,
            'provenance'       => [
                'origin'       => 'frontend_bridge',
                'transport'    => 'browser_rest',
                'agent'        => $agent,
                'processed_by' => 'agent_queue',
            ],
            'created_at'       => $timestamp,
            'updated_at'       => $timestamp,
            'claimed_at'       => '',
            'completed_at'     => '',
            'result'           => null,
            'thread'           => null,
            'error'            => '',
        ];

        $requests[$request_id] = $request;
        update_option(self::AGENT_REQUESTS_OPTION_KEY, self::trim_agent_requests($requests));

        if ($user_prompt !== '') {
            self::append_thread_message($thread_id, [
                'role'        => 'user',
                'label'       => __('Request', 'livecanvas-forge-ai'),
                'content'     => $user_prompt,
                'meta'        => [
                    'agent'            => $agent,
                    'processed_by'     => 'agent_queue',
                    'queued_for'       => self::agent_processor_for($agent),
                    'execution_target' => $request['execution_target'],
                    'post_id'          => $request['post_id'],
                    'context_post_id'  => $request['context_post_id'],
                    'target_id'        => $request['target_id'],
                    'variant'          => $request['variant'],
                    'request_id'       => $request_id,
                    'ability_contract' => $ability_contract,
                    'codex_options'    => $codex_options,
                ],
                'attachments' => $attachments,
            ]);
        }

        return self::get_agent_request($request_id) ?: $request;
    }

    public static function claim_next_agent_request(string $agent = ''): ?array {
        $requests = self::get_agent_requests();
        $agent = self::normalize_agent_client($agent);
        $timestamp = current_time('mysql', true);

        foreach ($requests as $request_id => $request) {
            if (!is_array($request) || ($request['status'] ?? '') !== 'queued') {
                continue;
            }

            if ($agent !== '' && ($request['agent'] ?? '') !== $agent) {
                continue;
            }

            $request['status'] = 'running';
            $request['claimed_at'] = $timestamp;
            $request['updated_at'] = $timestamp;
            $request['claimed_by'] = $agent !== '' ? $agent : sanitize_key((string) ($request['agent'] ?? ''));

            $requests[$request_id] = $request;
            update_option(self::AGENT_REQUESTS_OPTION_KEY, self::trim_agent_requests($requests));

            return self::get_agent_request($request_id);
        }

        return null;
    }

    public static function claim_agent_request(string $request_id, string $agent = ''): ?array {
        $request_id = sanitize_key($request_id);
        $agent = self::normalize_agent_client($agent);

        if ($request_id === '') {
            return null;
        }

        $requests = self::get_agent_requests();

        if (!isset($requests[$request_id]) || !is_array($requests[$request_id])) {
            return null;
        }

        $request = $requests[$request_id];

        if (($request['status'] ?? '') !== 'queued') {
            return null;
        }

        if ($agent !== '' && ($request['agent'] ?? '') !== $agent) {
            return null;
        }

        $timestamp = current_time('mysql', true);
        $request['status'] = 'running';
        $request['claimed_at'] = $timestamp;
        $request['updated_at'] = $timestamp;
        $request['claimed_by'] = $agent !== '' ? $agent : sanitize_key((string) ($request['agent'] ?? ''));

        $requests[$request_id] = $request;
        update_option(self::AGENT_REQUESTS_OPTION_KEY, self::trim_agent_requests($requests));

        return self::get_agent_request($request_id);
    }

    public static function update_agent_request_runner(string $request_id, array $runner): ?array {
        $request_id = sanitize_key($request_id);

        if ($request_id === '') {
            return null;
        }

        $requests = self::get_agent_requests();

        if (!isset($requests[$request_id]) || !is_array($requests[$request_id])) {
            return null;
        }

        $requests[$request_id]['runner'] = self::sanitize_agent_payload($runner);
        $requests[$request_id]['updated_at'] = current_time('mysql', true);
        update_option(self::AGENT_REQUESTS_OPTION_KEY, self::trim_agent_requests($requests));

        return self::get_agent_request($request_id);
    }

    public static function complete_agent_request(string $request_id, array $result, array $thread = []): ?array {
        return self::update_agent_request_terminal_state($request_id, 'completed', [
            'result' => self::sanitize_agent_payload($result),
            'thread' => is_array($thread) && $thread !== [] ? self::sanitize_agent_payload($thread) : null,
            'error' => '',
        ]);
    }

    public static function fail_agent_request(string $request_id, string $message, array $thread = []): ?array {
        return self::update_agent_request_terminal_state($request_id, 'failed', [
            'result' => [
                'ok' => false,
                'message' => sanitize_textarea_field($message),
            ],
            'thread' => is_array($thread) && $thread !== [] ? self::sanitize_agent_payload($thread) : null,
            'error' => sanitize_textarea_field($message),
        ]);
    }

    public static function normalize_thread_id(string $thread_id): string {
        $normalized = sanitize_key($thread_id);

        return $normalized !== '' ? $normalized : self::DEFAULT_THREAD_ID;
    }

    public static function set_command_result(array $result): void {
        set_transient(self::COMMAND_RESULT_PREFIX . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS);
    }

    public static function consume_command_result(): ?array {
        $key    = self::COMMAND_RESULT_PREFIX . get_current_user_id();
        $result = get_transient($key);

        if ($result) {
            delete_transient($key);
        }

        return is_array($result) ? $result : null;
    }

    public static function set_command_suggestion(array $suggestion): void {
        set_transient(self::COMMAND_SUGGESTION_PREFIX . get_current_user_id(), $suggestion, 10 * MINUTE_IN_SECONDS);
    }

    public static function consume_command_suggestion(): ?array {
        $key        = self::COMMAND_SUGGESTION_PREFIX . get_current_user_id();
        $suggestion = get_transient($key);

        if ($suggestion) {
            delete_transient($key);
        }

        return is_array($suggestion) ? $suggestion : null;
    }

    public static function set_connection_test_result(array $result): void {
        set_transient(self::CONNECTION_TEST_PREFIX . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
    }

    public static function consume_connection_test_result(): ?array {
        $key    = self::CONNECTION_TEST_PREFIX . get_current_user_id();
        $result = get_transient($key);

        if ($result) {
            delete_transient($key);
        }

        return is_array($result) ? $result : null;
    }

    public static function set_notice(string $message, string $type = 'success'): void {
        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            [
                'message' => $message,
                'type'    => $type,
            ],
            MINUTE_IN_SECONDS
        );
    }

    public static function consume_notice(): ?array {
        $key    = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient($key);

        if ($notice) {
            delete_transient($key);
        }

        return is_array($notice) ? $notice : null;
    }

    private static function clear_runtime_feedback(): void {
        if (!function_exists('get_current_user_id') || !function_exists('delete_transient')) {
            return;
        }

        $user_id = (int) get_current_user_id();

        if ($user_id < 1) {
            return;
        }

        delete_transient(self::COMMAND_RESULT_PREFIX . $user_id);
        delete_transient(self::COMMAND_SUGGESTION_PREFIX . $user_id);
        delete_transient(self::CONNECTION_TEST_PREFIX . $user_id);
    }

    private static function default_thread(): array {
        return [
            'id'         => self::DEFAULT_THREAD_ID,
            'title'      => __('Main thread', 'livecanvas-forge-ai'),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
            'messages'   => [],
        ];
    }

    private static function normalize_thread(array $thread, string $thread_id): array {
        $messages = [];
        $valid_roles = ['user', 'assistant', 'suggestion_result', 'system', 'tool_result'];

        foreach ((array) ($thread['messages'] ?? []) as $message) {
            if (!is_array($message)) {
                continue;
            }

            $messages[] = [
                'id'      => sanitize_key((string) ($message['id'] ?? ('msg-' . strtolower(wp_generate_password(10, false, false))))),
                'role'    => in_array($message['role'] ?? '', $valid_roles, true) ? (string) $message['role'] : 'assistant',
                'label'   => sanitize_text_field((string) ($message['label'] ?? '')),
                'time'    => sanitize_text_field((string) ($message['time'] ?? current_time('mysql', true))),
                'content' => sanitize_textarea_field((string) ($message['content'] ?? '')),
                'meta'    => self::sanitize_thread_meta((array) ($message['meta'] ?? [])),
                'attachments' => self::sanitize_thread_attachments((array) ($message['attachments'] ?? [])),
                'actions' => self::sanitize_thread_actions((array) ($message['actions'] ?? [])),
            ];
        }

        return [
            'id'         => $thread_id,
            'title'      => sanitize_text_field((string) ($thread['title'] ?? __('Command thread', 'livecanvas-forge-ai'))),
            'created_at' => sanitize_text_field((string) ($thread['created_at'] ?? current_time('mysql', true))),
            'updated_at' => sanitize_text_field((string) ($thread['updated_at'] ?? current_time('mysql', true))),
            'messages'   => array_slice($messages, -80),
        ];
    }

    private static function sanitize_thread_meta(array $meta): array {
        $sanitized = [];

        foreach ($meta as $key => $value) {
            $normalized_key = sanitize_key((string) $key);

            if ($normalized_key === '') {
                continue;
            }

            if (is_bool($value) || is_numeric($value)) {
                $sanitized[$normalized_key] = $value;
                continue;
            }

            if (is_array($value)) {
                $sanitized[$normalized_key] = array_map(static function ($item) {
                    if (is_bool($item) || is_numeric($item)) {
                        return $item;
                    }

                    return sanitize_text_field((string) $item);
                }, $value);
                continue;
            }

            $sanitized[$normalized_key] = sanitize_text_field((string) $value);
        }

        return $sanitized;
    }

    private static function sanitize_thread_actions(array $actions): array {
        $sanitized = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $kind = sanitize_key((string) ($action['kind'] ?? 'url'));
            $label = sanitize_text_field((string) ($action['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            if ($kind === 'apply') {
                $payload = self::sanitize_thread_action_payload($action['payload'] ?? []);

                if (!is_array($payload) || empty($payload['action'])) {
                    continue;
                }

                $sanitized[] = [
                    'kind'    => 'apply',
                    'label'   => $label,
                    'payload' => $payload,
                ];
                continue;
            }

            $url = self::sanitize_thread_action_url((string) ($action['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $sanitized[] = [
                'kind'  => 'url',
                'label' => $label,
                'url'   => $url,
            ];
        }

        return array_slice($sanitized, 0, 3);
    }

    private static function sanitize_thread_attachments(array $attachments): array {
        $sanitized = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $kind = sanitize_key((string) ($attachment['kind'] ?? ''));
            $mime = sanitize_text_field((string) ($attachment['mime'] ?? ''));
            $name = sanitize_text_field((string) ($attachment['name'] ?? ''));
            $caption = sanitize_text_field((string) ($attachment['caption'] ?? ''));
            $data_url = trim((string) ($attachment['data_url'] ?? ''));

            if ($kind !== 'image' || $mime === '' || strpos($mime, 'image/') !== 0) {
                continue;
            }

            if ($data_url === '' || strpos($data_url, 'data:image/') !== 0) {
                continue;
            }

            if (strlen($data_url) > 500000) {
                continue;
            }

            $sanitized[] = [
                'kind'     => 'image',
                'name'     => $name,
                'mime'     => $mime,
                'caption'  => $caption,
                'data_url' => $data_url,
                'size'     => absint($attachment['size'] ?? 0),
                'width'    => absint($attachment['width'] ?? 0),
                'height'   => absint($attachment['height'] ?? 0),
                'orientation' => sanitize_key((string) ($attachment['orientation'] ?? '')),
            ];
        }

        return array_slice($sanitized, 0, 2);
    }

    private static function sanitize_thread_action_url(string $url): string {
        if (function_exists('esc_url_raw')) {
            return esc_url_raw($url);
        }

        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        return trim($url);
    }

    private static function sanitize_thread_action_payload($value) {
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : $key;

                if ($sanitized_key === '') {
                    continue;
                }

                $sanitized[$sanitized_key] = self::sanitize_thread_action_payload($item);
            }

            return $sanitized;
        }

        return sanitize_text_field((string) $value);
    }

    private static function normalize_agent_request(array $request, string $fallback_id): array {
        $status = sanitize_key((string) ($request['status'] ?? 'queued'));
        $agent = self::normalize_agent_client((string) ($request['agent'] ?? 'codex'));

        if (!in_array($status, ['queued', 'running', 'completed', 'failed'], true)) {
            $status = 'queued';
        }

        $result = is_array($request['result'] ?? null) ? self::sanitize_agent_payload($request['result']) : null;
        $thread = is_array($request['thread'] ?? null) ? self::sanitize_agent_payload($request['thread']) : null;

        return [
            'id'               => sanitize_key((string) ($request['id'] ?? $fallback_id)),
            'status'           => $status,
            'agent'            => $agent,
            'queued_for'       => self::agent_processor_for($agent),
            'thread_id'        => self::normalize_thread_id((string) ($request['thread_id'] ?? 'default')),
            'user_prompt'      => sanitize_textarea_field((string) ($request['user_prompt'] ?? '')),
            'execution_target' => sanitize_key((string) ($request['execution_target'] ?? 'local')),
            'post_id'          => absint($request['post_id'] ?? 0),
            'context_post_id'  => absint($request['context_post_id'] ?? 0),
            'target_id'        => absint($request['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($request['variant'] ?? '1')),
            'action'           => sanitize_key((string) ($request['action'] ?? 'page_upsert')),
            'ability_contract' => self::sanitize_agent_payload((array) ($request['ability_contract'] ?? self::agent_ability_contract_for((string) ($request['action'] ?? 'page_upsert')))),
            'codex_options'    => self::sanitize_codex_options((array) ($request['codex_options'] ?? [])),
            'payload'          => self::sanitize_agent_payload((array) ($request['payload'] ?? [])),
            'attachments'      => self::sanitize_thread_attachments((array) ($request['attachments'] ?? [])),
            'provenance'       => self::sanitize_agent_payload((array) ($request['provenance'] ?? [])),
            'runner'           => self::sanitize_agent_payload((array) ($request['runner'] ?? [])),
            'created_at'       => sanitize_text_field((string) ($request['created_at'] ?? '')),
            'updated_at'       => sanitize_text_field((string) ($request['updated_at'] ?? '')),
            'claimed_at'       => sanitize_text_field((string) ($request['claimed_at'] ?? '')),
            'completed_at'     => sanitize_text_field((string) ($request['completed_at'] ?? '')),
            'claimed_by'       => sanitize_key((string) ($request['claimed_by'] ?? '')),
            'result'           => $result,
            'thread'           => $thread,
            'error'            => sanitize_textarea_field((string) ($request['error'] ?? '')),
        ];
    }

    private static function update_agent_request_terminal_state(string $request_id, string $status, array $patch): ?array {
        $request_id = sanitize_key($request_id);

        if ($request_id === '') {
            return null;
        }

        $requests = self::get_agent_requests();

        if (!isset($requests[$request_id]) || !is_array($requests[$request_id])) {
            return null;
        }

        $request = $requests[$request_id];
        $timestamp = current_time('mysql', true);
        $request['status'] = $status;
        $request['updated_at'] = $timestamp;
        $request['completed_at'] = $timestamp;

        foreach ($patch as $key => $value) {
            $request[$key] = $value;
        }

        $requests[$request_id] = $request;
        update_option(self::AGENT_REQUESTS_OPTION_KEY, self::trim_agent_requests($requests));

        return self::get_agent_request($request_id);
    }

    private static function sanitize_agent_payload($value) {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : $key;

                if ($sanitized_key === '') {
                    continue;
                }

                $sanitized[$sanitized_key] = self::sanitize_agent_payload($item);
            }

            return $sanitized;
        }

        return is_string($value) ? $value : (string) $value;
    }

    private static function normalize_agent_client(string $agent): string {
        $agent = sanitize_key($agent);

        return in_array($agent, ['codex', 'opencode', 'claude', 'cursor', 'generic'], true) ? $agent : 'codex';
    }

    private static function agent_ability_contract_for(string $action): array {
        $action = sanitize_key($action);
        $map = [
            'page_upsert'             => ['preview' => 'livecanvas-forge-ai/preview-page-upsert', 'apply' => 'livecanvas-forge-ai/apply-page-upsert'],
            'create_page'             => ['preview' => 'livecanvas-forge-ai/preview-page-upsert', 'apply' => 'livecanvas-forge-ai/apply-page-upsert'],
            'update_page'             => ['preview' => 'livecanvas-forge-ai/preview-page-upsert', 'apply' => 'livecanvas-forge-ai/apply-page-upsert'],
            'update_partial'          => ['preview' => '', 'apply' => ''],
            'native_pattern_page_apply' => ['preview' => 'livecanvas-forge-ai/preview-native-pattern-page', 'apply' => 'livecanvas-forge-ai/apply-native-pattern-page'],
            'create_native_pattern_page' => ['preview' => 'livecanvas-forge-ai/preview-native-pattern-page', 'apply' => 'livecanvas-forge-ai/apply-native-pattern-page'],
            'global_shell_apply'      => ['preview' => 'livecanvas-forge-ai/preview-global-shell', 'apply' => 'livecanvas-forge-ai/apply-global-shell'],
            'update_header'           => ['preview' => 'livecanvas-forge-ai/preview-global-shell', 'apply' => 'livecanvas-forge-ai/apply-global-shell'],
            'update_footer'           => ['preview' => 'livecanvas-forge-ai/preview-global-shell', 'apply' => 'livecanvas-forge-ai/apply-global-shell'],
            'design_system_apply'     => ['preview' => 'livecanvas-forge-ai/preview-design-system', 'apply' => 'livecanvas-forge-ai/apply-design-system'],
            'create_dynamic_template' => ['preview' => '', 'apply' => 'livecanvas-forge-ai/apply-dynamic-template'],
            'update_dynamic_template' => ['preview' => '', 'apply' => 'livecanvas-forge-ai/apply-dynamic-template'],
            'restore_audit_rollback'  => ['preview' => '', 'apply' => 'livecanvas-forge-ai/restore-audit-rollback'],
        ];
        $contract = $map[$action] ?? ['preview' => '', 'apply' => ''];

        return [
            'action'          => $action !== '' ? $action : 'page_upsert',
            'preview_ability' => $contract['preview'],
            'apply_ability'   => $contract['apply'],
            'generic_preview' => 'livecanvas-forge-ai/preview-command',
            'generic_apply'   => 'livecanvas-forge-ai/apply-command',
        ];
    }

    private static function agent_processor_for(string $agent): string {
        $agent = self::normalize_agent_client($agent);

        return $agent === 'generic' ? 'generic_mcp' : $agent . '_mcp';
    }

    private static function sort_threads_by_updated_at(array $threads): array {
        uasort($threads, static function (array $left, array $right): int {
            return strtotime((string) ($right['updated_at'] ?? '')) <=> strtotime((string) ($left['updated_at'] ?? ''));
        });

        return $threads;
    }

    private static function trim_threads(array $threads): array {
        $threads = self::sort_threads_by_updated_at($threads);
        $default = $threads[self::DEFAULT_THREAD_ID] ?? self::default_thread();
        unset($threads[self::DEFAULT_THREAD_ID]);

        $threads = array_slice($threads, 0, 11, true);
        $threads[self::DEFAULT_THREAD_ID] = $default;

        return self::sort_threads_by_updated_at($threads);
    }

    private static function generate_thread_id(array $threads): string {
        $base = 'thread-' . strtolower(wp_generate_password(8, false, false));
        $candidate = self::normalize_thread_id($base);
        $suffix = 2;

        while ($candidate === '' || isset($threads[$candidate])) {
            $candidate = self::normalize_thread_id($base . '-' . $suffix);
            $suffix++;
        }

        return $candidate !== '' ? $candidate : 'thread-' . (string) time();
    }

    private static function trim_agent_requests(array $requests): array {
        uasort($requests, static function (array $left, array $right): int {
            return strtotime((string) ($right['updated_at'] ?: $right['created_at'] ?? '')) <=> strtotime((string) ($left['updated_at'] ?: $left['created_at'] ?? ''));
        });

        return array_slice($requests, 0, 50, true);
    }

    private static function generate_agent_request_id(array $requests): string {
        $base = 'agent-' . strtolower(wp_generate_password(10, false, false));
        $candidate = sanitize_key($base);
        $suffix = 2;

        while ($candidate === '' || isset($requests[$candidate])) {
            $candidate = sanitize_key($base . '-' . $suffix);
            $suffix++;
        }

        return $candidate !== '' ? $candidate : 'agent-' . (string) time();
    }
}
