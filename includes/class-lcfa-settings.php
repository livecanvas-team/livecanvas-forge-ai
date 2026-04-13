<?php

defined('ABSPATH') || exit;

final class LCFA_Settings {
    public const OPTION_KEY = 'lcfa_settings';
    public const BRIEF_OPTION_KEY = 'lcfa_project_brief';
    public const GENESIS_PLAN_OPTION_KEY = 'lcfa_genesis_plan';
    public const GENESIS_PROGRESS_OPTION_KEY = 'lcfa_genesis_progress';
    public const CONNECTIONS_OPTION_KEY = 'lcfa_connections';
    public const HISTORY_OPTION_KEY = 'lcfa_command_history';
    public const THREADS_OPTION_KEY = 'lcfa_command_threads';
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
            'permission_profile'  => 'draft_preview',
            'allow_file_fallback' => false,
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
        $connections = get_option(self::CONNECTIONS_OPTION_KEY, []);

        if (!is_array($connections)) {
            $connections = [];
        }

        $connections = wp_parse_args($connections, self::connection_defaults());

        if (empty($connections['mcp_token'])) {
            $connections['mcp_token'] = self::generate_mcp_token();
            update_option(self::CONNECTIONS_OPTION_KEY, $connections);
        }

        return $connections;
    }

    public static function update_connections(array $connections): void {
        update_option(self::CONNECTIONS_OPTION_KEY, self::sanitize_connections($connections));
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
            'mcp_host'                    => '127.0.0.1',
            'mcp_port'                    => '7681',
            'mcp_token'                   => self::generate_mcp_token(),
            'remote_site_url'             => '',
            'remote_username'             => '',
            'remote_application_password' => '',
            'mcp_server_command'          => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
            'preferred_client'            => '',
            'workspace_root'              => '',
            'connection_status'           => '',
            'connection_mode'             => '',
            'connection_last_verified_at' => '',
            'connection_last_error'       => '',
            'connection_last_bundle_hash' => '',
            'connection_current_step'     => '',
        ];
    }

    public static function sanitize_connections(array $connections): array {
        $current = self::get_connections();
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

        return [
            'transport'                   => in_array($connections['transport'] ?? '', ['rest', 'mcp', 'hybrid'], true) ? $connections['transport'] : 'rest',
            'picowind_package_url'        => esc_url_raw($connections['picowind_package_url'] ?? ''),
            'picostrap_package_url'       => esc_url_raw($connections['picostrap_package_url'] ?? ''),
            'local_bridge_url'            => esc_url_raw($connections['local_bridge_url'] ?? rest_url('lcfa/v1/')),
            'mcp_enabled'                 => !empty($connections['mcp_enabled']),
            'mcp_host'                    => sanitize_text_field($connections['mcp_host'] ?? '127.0.0.1'),
            'mcp_port'                    => (string) $mcp_port,
            'mcp_token'                   => $mcp_token,
            'remote_site_url'             => esc_url_raw($connections['remote_site_url'] ?? ''),
            'remote_username'             => sanitize_text_field($connections['remote_username'] ?? ''),
            'remote_application_password' => $password,
            'mcp_server_command'          => sanitize_textarea_field($connections['mcp_server_command'] ?? ''),
            'preferred_client'            => sanitize_key($connections['preferred_client'] ?? ''),
            'workspace_root'              => sanitize_text_field($connections['workspace_root'] ?? ''),
            'connection_status'           => sanitize_key($connections['connection_status'] ?? ''),
            'connection_mode'             => in_array($connections['connection_mode'] ?? '', ['local', 'remote'], true) ? $connections['connection_mode'] : '',
            'connection_last_verified_at' => sanitize_text_field($connections['connection_last_verified_at'] ?? ''),
            'connection_last_error'       => sanitize_text_field($connections['connection_last_error'] ?? ''),
            'connection_last_bundle_hash' => sanitize_text_field($connections['connection_last_bundle_hash'] ?? ''),
            'connection_current_step'     => in_array($connections['connection_current_step'] ?? '', ['', 'choose_client', 'choose_mode', 'confirm_details', 'generate_bundle', 'smoke_test', 'ready'], true) ? $connections['connection_current_step'] : '',
        ];
    }

    public static function rotate_mcp_token(): array {
        $connections = self::get_connections();
        $connections['mcp_token'] = self::generate_mcp_token();
        self::update_connections($connections);

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
                'last_preview'  => is_array($last_message) ? wp_trim_words((string) ($last_message['content'] ?? ''), 14, '...') : '',
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
        $thread_id  = 'thread-' . strtolower(wp_generate_password(8, false, false));
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

    public static function append_thread_message(string $thread_id, array $message): array {
        $normalized_id = self::normalize_thread_id($thread_id ?: self::DEFAULT_THREAD_ID);
        $threads       = self::get_threads();
        $thread        = $threads[$normalized_id] ?? self::default_thread();
        $messages      = is_array($thread['messages'] ?? null) ? $thread['messages'] : [];
        $timestamp     = current_time('mysql', true);
        $meta          = is_array($message['meta'] ?? null) ? $message['meta'] : [];

        $messages[] = [
            'id'      => sanitize_key((string) ($message['id'] ?? ('msg-' . strtolower(wp_generate_password(10, false, false))))),
            'role'    => in_array($message['role'] ?? '', ['user', 'assistant', 'system'], true) ? (string) $message['role'] : 'assistant',
            'label'   => sanitize_text_field((string) ($message['label'] ?? '')),
            'time'    => sanitize_text_field((string) ($message['time'] ?? $timestamp)),
            'content' => sanitize_textarea_field((string) ($message['content'] ?? '')),
            'meta'    => self::sanitize_thread_meta($meta),
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

        foreach ((array) ($thread['messages'] ?? []) as $message) {
            if (!is_array($message)) {
                continue;
            }

            $messages[] = [
                'id'      => sanitize_key((string) ($message['id'] ?? ('msg-' . strtolower(wp_generate_password(10, false, false))))),
                'role'    => in_array($message['role'] ?? '', ['user', 'assistant', 'system'], true) ? (string) $message['role'] : 'assistant',
                'label'   => sanitize_text_field((string) ($message['label'] ?? '')),
                'time'    => sanitize_text_field((string) ($message['time'] ?? current_time('mysql', true))),
                'content' => sanitize_textarea_field((string) ($message['content'] ?? '')),
                'meta'    => self::sanitize_thread_meta((array) ($message['meta'] ?? [])),
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
}
