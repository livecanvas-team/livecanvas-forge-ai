<?php

defined('ABSPATH') || exit;

final class LCFA_Settings {
    public const OPTION_KEY = 'lcfa_settings';
    public const BRIEF_OPTION_KEY = 'lcfa_project_brief';
    public const CONNECTIONS_OPTION_KEY = 'lcfa_connections';
    public const HISTORY_OPTION_KEY = 'lcfa_command_history';
    public const REDIRECT_OPTION_KEY = 'lcfa_do_activation_redirect';
    private const NOTICE_PREFIX = 'lcfa_notice_';
    private const COMMAND_RESULT_PREFIX = 'lcfa_command_result_';

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
}
