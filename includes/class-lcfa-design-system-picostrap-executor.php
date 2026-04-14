<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picostrap_Executor {
    private const TOKEN_MAP = [
        'colors.primary' => 'SCSSvar_primary',
        'colors.secondary' => 'SCSSvar_secondary',
        'colors.success' => 'SCSSvar_success',
        'colors.info' => 'SCSSvar_info',
        'colors.warning' => 'SCSSvar_warning',
        'colors.danger' => 'SCSSvar_danger',
        'colors.light' => 'SCSSvar_light',
        'colors.dark' => 'SCSSvar_dark',
        'colors.body_bg' => 'SCSSvar_body-bg',
        'colors.body_color' => 'SCSSvar_body-color',
        'typography.font_family_base' => 'SCSSvar_font-family-base',
        'typography.headings_font_family' => 'SCSSvar_headings-font-family',
        'typography.font_size_base' => 'SCSSvar_font-size-base',
        'typography.line_height_base' => 'SCSSvar_line-height-base',
        'radius.border_radius' => 'SCSSvar_border-radius',
        'radius.border_radius_sm' => 'SCSSvar_border-radius-sm',
        'radius.border_radius_lg' => 'SCSSvar_border-radius-lg',
        'buttons.btn_padding_y' => 'SCSSvar_btn-padding-y',
        'buttons.btn_padding_x' => 'SCSSvar_btn-padding-x',
        'buttons.btn_border_radius' => 'SCSSvar_btn-border-radius',
    ];

    public function execute(array $payload, bool $dry_run): array {
        $writes = $this->collect_theme_mod_writes($payload);
        $changed = [];

        foreach ($writes as $key => $value) {
            if (get_theme_mod($key, null) !== $value) {
                $changed[] = $key;
            }
        }

        if (!$dry_run) {
            foreach ($writes as $key => $value) {
                set_theme_mod($key, $value);
            }
        }

        return [
            'ok' => true,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => $dry_run
                ? __('Picostrap design system preview prepared.', 'livecanvas-forge-ai')
                : __('Picostrap design system applied.', 'livecanvas-forge-ai'),
            'target_stack' => 'picostrap',
            'source_of_truth' => 'theme_mods',
            'summary' => $dry_run
                ? __('Preview Picostrap design system changes.', 'livecanvas-forge-ai')
                : __('Applied design system tokens to Picostrap theme mods.', 'livecanvas-forge-ai'),
            'changed_keys' => $changed,
            'build_required' => true,
            'build_executed' => false,
            'build_strategy' => 'picosass_handoff',
            'compile_url' => home_url('/?compile_sass=1&sass_nocache=1'),
            'warnings' => $this->build_font_warnings($payload),
            'data' => [
                'changed_theme_mods' => array_intersect_key($writes, array_flip($changed)),
            ],
        ];
    }

    private function collect_theme_mod_writes(array $payload): array {
        $writes = [];

        foreach (self::TOKEN_MAP as $source => $target) {
            $value = $this->read_nested_value($payload, $source);

            if ($value === null || $value === '') {
                continue;
            }

            $writes[$target] = $value;
        }

        $font_assets = is_array($payload['font_assets'] ?? null) ? $payload['font_assets'] : [];

        if (!empty($font_assets['body_font_object'])) {
            $writes['body_font_object'] = $font_assets['body_font_object'];
        }

        if (!empty($font_assets['headings_font_object'])) {
            $writes['headings_font_object'] = $font_assets['headings_font_object'];
        }

        if (array_key_exists('fonts_header_code', $font_assets) && $font_assets['fonts_header_code'] !== '') {
            $writes['picostrap_fonts_header_code'] = (string) $font_assets['fonts_header_code'];
        }

        return $writes;
    }

    private function build_font_warnings(array $payload): array {
        $font_assets = is_array($payload['font_assets'] ?? null) ? $payload['font_assets'] : [];
        $typography = is_array($payload['typography'] ?? null) ? $payload['typography'] : [];

        if (($typography['font_family_base'] ?? '') === '' && ($typography['headings_font_family'] ?? '') === '') {
            return [];
        }

        if (!empty($font_assets['body_font_object']) || !empty($font_assets['headings_font_object']) || !empty($font_assets['fonts_header_code'])) {
            return [];
        }

        return [__('Font family tokens were applied, but no Picostrap font asset metadata was provided.', 'livecanvas-forge-ai')];
    }

    private function read_nested_value(array $payload, string $path) {
        $segments = explode('.', $path);
        $cursor = $payload;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
