<?php

defined('ABSPATH') || exit;

final class LCFA_Settings {
    public const OPTION_KEY = 'lcfa_settings';
    public const BRIEF_OPTION_KEY = 'lcfa_project_brief';
    public const REDIRECT_OPTION_KEY = 'lcfa_do_activation_redirect';
    private const NOTICE_PREFIX = 'lcfa_notice_';

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
