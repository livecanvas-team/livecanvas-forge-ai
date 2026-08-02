<?php

defined('ABSPATH') || exit;

if (!class_exists('LCFA_Picostrap_Compile_Service', false)) {
    require_once __DIR__ . '/class-lcfa-picostrap-compile-manifest.php';
    require_once __DIR__ . '/class-lcfa-picostrap-bundle-store.php';
    require_once __DIR__ . '/class-lcfa-picostrap-compile-service.php';
}

final class LCFA_Design_System_Picostrap_Executor {
    private const TOKEN_MAP = [
        'colors.body_bg' => 'SCSSvar_body-bg',
        'colors.body_color' => 'SCSSvar_body-color',
        'colors.link_color' => 'SCSSvar_link-color',
        'colors.link_hover_color' => 'SCSSvar_link-hover-color',
        'colors.primary' => 'SCSSvar_primary',
        'colors.secondary' => 'SCSSvar_secondary',
        'colors.success' => 'SCSSvar_success',
        'colors.info' => 'SCSSvar_info',
        'colors.warning' => 'SCSSvar_warning',
        'colors.danger' => 'SCSSvar_danger',
        'colors.light' => 'SCSSvar_light',
        'colors.dark' => 'SCSSvar_dark',
        'colors.headings_color' => 'SCSSvar_headings-color',
        'colors.text_muted' => 'SCSSvar_text-muted',
        'colors.border_color' => 'SCSSvar_border-color',
        'typography.font_family_base' => 'SCSSvar_font-family-base',
        'typography.font_family_sans_serif' => 'SCSSvar_font-family-sans-serif',
        'typography.font_family_monospace' => 'SCSSvar_font-family-monospace',
        'typography.font_size_base' => 'SCSSvar_font-size-base',
        'typography.font_size_sm' => 'SCSSvar_font-size-sm',
        'typography.font_size_lg' => 'SCSSvar_font-size-lg',
        'typography.font_weight_base' => 'SCSSvar_font-weight-base',
        'typography.line_height_base' => 'SCSSvar_line-height-base',
        'typography.headings_font_family' => 'SCSSvar_headings-font-family',
        'typography.headings_font_weight' => 'SCSSvar_headings-font-weight',
        'typography.headings_line_height' => 'SCSSvar_headings-line-height',
        'typography.h1_font_size' => 'SCSSvar_h1-font-size',
        'typography.h2_font_size' => 'SCSSvar_h2-font-size',
        'typography.h3_font_size' => 'SCSSvar_h3-font-size',
        'typography.h4_font_size' => 'SCSSvar_h4-font-size',
        'typography.h5_font_size' => 'SCSSvar_h5-font-size',
        'typography.h6_font_size' => 'SCSSvar_h6-font-size',
        'typography.display1_font_size' => 'SCSSvar_display1-font-size',
        'typography.display2_font_size' => 'SCSSvar_display2-font-size',
        'typography.display3_font_size' => 'SCSSvar_display3-font-size',
        'typography.display4_font_size' => 'SCSSvar_display4-font-size',
        'typography.display5_font_size' => 'SCSSvar_display5-font-size',
        'typography.display6_font_size' => 'SCSSvar_display6-font-size',
        'typography.display_font_weight' => 'SCSSvar_display-font-weight',
        'typography.display_line_height' => 'SCSSvar_display-line-height',
        'components.enable_rounded' => 'SCSSvar_enable-rounded',
        'components.enable_shadows' => 'SCSSvar_enable-shadows',
        'components.enable_gradients' => 'SCSSvar_enable-gradients',
        'components.spacer' => 'SCSSvar_spacer',
        'components.border_width' => 'SCSSvar_border-width',
        'components.border_style' => 'SCSSvar_border-style',
        'radius.border_radius' => 'SCSSvar_border-radius',
        'radius.border_radius_sm' => 'SCSSvar_border-radius-sm',
        'radius.border_radius_lg' => 'SCSSvar_border-radius-lg',
        'radius.border_radius_xl' => 'SCSSvar_border-radius-xl',
        'radius.border_radius_2xl' => 'SCSSvar_border-radius-2xl',
        'radius.border_radius_pill' => 'SCSSvar_border-radius-pill',
        'buttons.btn_padding_y' => 'SCSSvar_btn-padding-y',
        'buttons.btn_padding_x' => 'SCSSvar_btn-padding-x',
        'buttons.btn_font_family' => 'SCSSvar_btn-font-family',
        'buttons.btn_font_size' => 'SCSSvar_btn-font-size',
        'buttons.btn_line_height' => 'SCSSvar_btn-line-height',
        'buttons.btn_font_weight' => 'SCSSvar_btn-font-weight',
        'buttons.btn_border_width' => 'SCSSvar_btn-border-width',
        'buttons.btn_border_radius' => 'SCSSvar_btn-border-radius',
        'buttons.btn_border_radius_sm' => 'SCSSvar_btn-border-radius-sm',
        'buttons.btn_border_radius_lg' => 'SCSSvar_btn-border-radius-lg',
        'buttons.btn_padding_y_sm' => 'SCSSvar_btn-padding-y-sm',
        'buttons.btn_padding_x_sm' => 'SCSSvar_btn-padding-x-sm',
        'buttons.btn_padding_y_lg' => 'SCSSvar_btn-padding-y-lg',
        'buttons.btn_padding_x_lg' => 'SCSSvar_btn-padding-x-lg',
        'forms.input_btn_padding_y' => 'SCSSvar_input-btn-padding-y',
        'forms.input_btn_padding_x' => 'SCSSvar_input-btn-padding-x',
        'forms.input_btn_font_size' => 'SCSSvar_input-btn-font-size',
        'forms.input_btn_line_height' => 'SCSSvar_input-btn-line-height',
        'navbars.navbar_brand_font_size' => 'SCSSvar_navbar-brand-font-size',
        'navbars.nav_link_font_size' => 'SCSSvar_nav-link-font-size',
        'navbars.navbar_toggler_font_size' => 'SCSSvar_navbar-toggler-font-size',
    ];

    private LCFA_Environment $environment;
    private LCFA_Picostrap_Compile_Service $compile_service;

    public function __construct(?LCFA_Environment $environment = null, ?LCFA_Picostrap_Compile_Service $compile_service = null) {
        $this->environment = $environment ?: new LCFA_Environment();
        $this->compile_service = $compile_service ?: new LCFA_Picostrap_Compile_Service($this->environment);
    }

    public function execute(array $payload, bool $dry_run): array {
        $plan = $this->collect_theme_mod_plan($payload);
        $writes = $plan['writes'];
        $removals = $plan['removals'];
        $overrides = $writes;
        foreach ($removals as $key) {
            $overrides[$key] = null;
        }

        try {
            $current_manifest = $this->compile_service->get_manifest();
            $proposed_manifest = $this->compile_service->get_manifest($overrides);
        } catch (Throwable $throwable) {
            return $this->error_result($dry_run, $throwable->getMessage(), $plan['warnings']);
        }

        $snapshot = $this->snapshot_theme_mods(array_values(array_unique(array_merge(array_keys($writes), $removals))));
        $changed = $this->collect_changed_keys($snapshot, $writes, $removals);
        $diff = $this->build_theme_mod_diff($snapshot, $writes, $removals);
        $warnings = array_values(array_unique(array_merge($plan['warnings'], $this->build_font_warnings($payload))));
        $compiled_css = is_string($payload['compiled_css'] ?? null) ? (string) $payload['compiled_css'] : '';
        $compiled_source_fingerprint = sanitize_text_field((string) ($payload['compiled_source_fingerprint'] ?? ''));
        $expected_state_fingerprint = sanitize_text_field((string) ($payload['expected_state_fingerprint'] ?? ''));
        $proposed_source_fingerprint = (string) ($proposed_manifest['source_fingerprint'] ?? '');

        if (strlen($compiled_css) > 8 * 1024 * 1024) {
            return $this->error_result(
                $dry_run,
                __('The compiled Picostrap CSS exceeds the 8 MB safety limit.', 'livecanvas-forge-ai'),
                $warnings
            );
        }

        if (!$dry_run && $expected_state_fingerprint !== '' && !hash_equals((string) ($current_manifest['state_fingerprint'] ?? ''), $expected_state_fingerprint)) {
            return $this->error_result(
                false,
                __('Picostrap Customizer or Sass state changed after preview. Run preview again before applying.', 'livecanvas-forge-ai'),
                $warnings
            );
        }

        if (!$dry_run && $compiled_css !== '') {
            if ($compiled_source_fingerprint === '') {
                $compiled_source_fingerprint = $proposed_source_fingerprint;
                $warnings[] = __('The compiled CSS did not include an explicit source fingerprint; AI Bridge matched it to the current preview for backward compatibility.', 'livecanvas-forge-ai');
            }

            if ($proposed_source_fingerprint === '' || !hash_equals($proposed_source_fingerprint, $compiled_source_fingerprint)) {
                return $this->error_result(
                    false,
                    __('The compiled CSS fingerprint does not match the proposed Picostrap Customizer and Sass state.', 'livecanvas-forge-ai'),
                    $warnings
                );
            }
        }

        if (!$dry_run && $compiled_css === '') {
            $result = $this->error_result(
                false,
                __('Picostrap apply requires a CSS bundle compiled from this preview. No Customizer values were changed.', 'livecanvas-forge-ai'),
                $warnings
            );
            $result['build_required'] = true;
            $result['build_executed'] = false;
            $result['build_strategy'] = 'bridge_dart_sass_transaction';
            $result['data'] = [
                'current_state_fingerprint' => (string) ($current_manifest['state_fingerprint'] ?? ''),
                'proposed_source_fingerprint' => $proposed_source_fingerprint,
                'compile_manifest' => $proposed_manifest,
                'synchronization_before' => (array) ($current_manifest['synchronization'] ?? []),
            ];

            return $result;
        }

        $bundle = [];
        if (!$dry_run) {
            $this->apply_theme_mod_plan($writes, $removals);

            if ($compiled_css !== '') {
                try {
                    $bundle = $this->compile_service->store_bundle($compiled_css, [
                        'source_fingerprint' => $proposed_source_fingerprint,
                    ]);
                } catch (Throwable $throwable) {
                    $this->restore_theme_mod_snapshot($snapshot);
                    return $this->error_result(false, $throwable->getMessage(), $warnings);
                }

                if (empty($bundle['ok'])) {
                    $this->restore_theme_mod_snapshot($snapshot);
                    return $this->error_result(
                        false,
                        (string) ($bundle['message'] ?? __('Picostrap bundle storage failed; Customizer variables were restored.', 'livecanvas-forge-ai')),
                        $warnings
                    );
                }
            }
        }

        $synchronization_after = (array) ($proposed_manifest['synchronization'] ?? []);
        if (!$dry_run && $compiled_css !== '') {
            try {
                $synchronization_after = (array) ($this->compile_service->get_status()['synchronization'] ?? []);
            } catch (Throwable $throwable) {
                $warnings[] = $throwable->getMessage();
            }
        }

        $theme = wp_get_theme();
        $target_title = (string) $theme->get('Name');
        $rollback = [
            'framework' => 'picostrap',
            'theme_mods' => $snapshot,
            'bundle' => is_array($bundle['rollback'] ?? null) ? $bundle['rollback'] : [],
        ];

        return [
            'ok' => true,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => $dry_run
                ? __('Picostrap design system preview prepared.', 'livecanvas-forge-ai')
                : ($compiled_css !== ''
                    ? __('Picostrap design system and compiled bundle applied atomically.', 'livecanvas-forge-ai')
                    : __('Picostrap design system variables applied; bundle compilation is still required.', 'livecanvas-forge-ai')),
            'target_stack' => 'picostrap',
            'target_type' => 'design_system',
            'target_title' => $target_title,
            'source_of_truth' => 'picostrap_customizer_theme_mods',
            'summary' => $dry_run
                ? __('Preview Picostrap Customizer, Sass, and bundle synchronization changes.', 'livecanvas-forge-ai')
                : __('Applied Picostrap design tokens with synchronized Customizer and Sass build metadata.', 'livecanvas-forge-ai'),
            'changed_keys' => $changed,
            'existing_html' => $this->encode_diff_payload($this->snapshot_values($snapshot)),
            'proposed_html' => $this->encode_diff_payload($this->proposed_values($snapshot, $writes, $removals)),
            'build_required' => $dry_run,
            'build_executed' => !$dry_run && $compiled_css !== '' && !empty($bundle['ok']),
            'build_strategy' => $compiled_css !== '' ? 'bridge_dart_sass_transaction' : 'bridge_dart_sass_required',
            'compile_url' => $this->compile_service->get_compile_url(),
            'bundle_path' => (string) ($bundle['bundle_path'] ?? ''),
            'bundle_url' => (string) ($bundle['bundle_url'] ?? ''),
            'bundle_version' => (int) ($bundle['bundle_version'] ?? ($current_manifest['current_bundle_version'] ?? 0)),
            'compiled_at' => (string) ($bundle['compiled_at'] ?? ''),
            'warnings' => array_values(array_unique($warnings)),
            'data' => [
                'changed_theme_mods' => array_intersect_key($writes, array_flip($changed)),
                'removed_theme_mods' => array_values(array_intersect($removals, $changed)),
                'theme_mod_diff' => $diff,
                'rejected_scss_variables' => $plan['rejected'],
                'native_registry_available' => $plan['native_registry_available'],
                'current_state_fingerprint' => (string) ($current_manifest['state_fingerprint'] ?? ''),
                'proposed_source_fingerprint' => $proposed_source_fingerprint,
                'compile_manifest' => $proposed_manifest,
                'synchronization_before' => (array) ($current_manifest['synchronization'] ?? []),
                'synchronization_after' => $synchronization_after,
                'bundle' => $bundle,
                'picostrap_design_system_rollback' => $dry_run ? [] : $rollback,
            ],
        ];
    }

    public function restore(array $snapshot, bool $dry_run = false): array {
        $theme_mods = is_array($snapshot['theme_mods'] ?? null) ? (array) $snapshot['theme_mods'] : [];
        $bundle = is_array($snapshot['bundle'] ?? null) ? (array) $snapshot['bundle'] : [];

        if ($theme_mods === [] && $bundle === []) {
            return [
                'ok' => false,
                'message' => __('The Picostrap design-system rollback snapshot is empty.', 'livecanvas-forge-ai'),
            ];
        }

        $bundle_restore = [];
        if ($bundle !== []) {
            $bundle_restore = $this->compile_service->restore_bundle($bundle, $dry_run);
            if (empty($bundle_restore['ok'])) {
                return [
                    'ok' => false,
                    'message' => (string) ($bundle_restore['message'] ?? __('Unable to restore the previous Picostrap bundle.', 'livecanvas-forge-ai')),
                    'bundle_restore' => $bundle_restore,
                ];
            }
        }

        if (!$dry_run) {
            $this->restore_theme_mod_snapshot($theme_mods);
        }

        $status = [];
        if (!$dry_run) {
            try {
                $status = $this->compile_service->get_status();
            } catch (Throwable $throwable) {
                $status = ['message' => $throwable->getMessage()];
            }
        }

        return [
            'ok' => true,
            'mode' => $dry_run ? 'preview' : 'apply',
            'message' => $dry_run
                ? __('Picostrap design-system rollback preview prepared.', 'livecanvas-forge-ai')
                : __('Picostrap Customizer variables and compiled bundle restored.', 'livecanvas-forge-ai'),
            'theme_mods' => array_keys($theme_mods),
            'bundle_restore' => $bundle_restore,
            'status' => $status,
        ];
    }

    private function collect_theme_mod_plan(array $payload): array {
        $design_system = is_array($payload['design_system'] ?? null) ? (array) $payload['design_system'] : [];
        $source = array_replace_recursive($payload, $design_system);
        $registry = $this->get_native_scss_registry();
        $writes = [];
        $removals = [];
        $warnings = [];
        $rejected = [];

        foreach (self::TOKEN_MAP as $source_path => $target) {
            $value = $this->read_nested_value($source, $source_path);
            if ($value === null || $value === '') {
                continue;
            }

            if ($registry !== [] && !isset($registry[$target])) {
                $rejected[$target] = __('Variable is not registered by the active Picostrap Customizer.', 'livecanvas-forge-ai');
                continue;
            }

            $type = (string) ($registry[$target]['type'] ?? $this->infer_variable_type($target));
            $normalized = $this->sanitize_scss_value($value, $type);
            if ($normalized === null) {
                $rejected[$target] = __('Invalid or unsafe Sass token value.', 'livecanvas-forge-ai');
                continue;
            }
            $writes[$target] = $normalized;
        }

        $raw_variables = is_array($source['scss_variables'] ?? null) ? (array) $source['scss_variables'] : [];
        foreach ($raw_variables as $raw_key => $value) {
            $target = $this->normalize_scss_theme_mod_key((string) $raw_key);
            if ($target === '') {
                $rejected[(string) $raw_key] = __('Invalid Picostrap Sass variable name.', 'livecanvas-forge-ai');
                continue;
            }
            if ($registry !== [] && !isset($registry[$target])) {
                $rejected[$target] = __('Variable is not registered by the active Picostrap Customizer.', 'livecanvas-forge-ai');
                continue;
            }

            $type = (string) ($registry[$target]['type'] ?? $this->infer_variable_type($target));
            $normalized = $this->sanitize_scss_value($value, $type);
            if ($normalized === null) {
                $rejected[$target] = __('Invalid or unsafe Sass token value.', 'livecanvas-forge-ai');
                continue;
            }
            $writes[$target] = $normalized;
        }

        foreach ((array) ($source['unset_scss_variables'] ?? []) as $raw_key) {
            $target = $this->normalize_scss_theme_mod_key((string) $raw_key);
            if ($target !== '' && ($registry === [] || isset($registry[$target]))) {
                $removals[] = $target;
                unset($writes[$target]);
            } elseif ($target !== '') {
                $rejected[$target] = __('Variable is not registered by the active Picostrap Customizer.', 'livecanvas-forge-ai');
            }
        }

        if (!empty($source['clear_existing_scss_variables'])) {
            foreach (array_keys($this->get_current_scss_theme_mods()) as $target) {
                if (!isset($writes[$target])) {
                    $removals[] = $target;
                }
            }
        }

        $font_assets = is_array($source['font_assets'] ?? null) ? (array) $source['font_assets'] : [];
        if (!empty($font_assets['body_font_object'])) {
            $writes['body_font_object'] = $this->normalize_font_object($font_assets['body_font_object']);
        }
        if (!empty($font_assets['headings_font_object'])) {
            $writes['headings_font_object'] = $this->normalize_font_object($font_assets['headings_font_object']);
        }
        if (array_key_exists('fonts_header_code', $font_assets) && trim((string) $font_assets['fonts_header_code']) !== '') {
            $writes['picostrap_fonts_header_code'] = $this->sanitize_font_header_code((string) $font_assets['fonts_header_code']);
        }

        if ($registry === []) {
            $warnings[] = __('The active Picostrap Customizer registry was not callable; AI Bridge used strict variable-name and value validation.', 'livecanvas-forge-ai');
        }
        foreach ($rejected as $key => $reason) {
            $warnings[] = sprintf(__('Skipped Picostrap variable %1$s: %2$s', 'livecanvas-forge-ai'), $key, $reason);
        }

        ksort($writes, SORT_STRING);
        $removals = array_values(array_unique($removals));
        sort($removals, SORT_STRING);

        return [
            'writes' => $writes,
            'removals' => $removals,
            'warnings' => array_values(array_unique($warnings)),
            'rejected' => $rejected,
            'native_registry_available' => $registry !== [],
        ];
    }

    private function get_native_scss_registry(): array {
        if (!function_exists('picostrap_get_scss_variables_array')) {
            return [];
        }

        $registry = [];
        foreach ((array) picostrap_get_scss_variables_array() as $section => $variables) {
            foreach ((array) $variables as $variable => $properties) {
                $target = $this->normalize_scss_theme_mod_key((string) $variable);
                if ($target === '') {
                    continue;
                }
                $registry[$target] = [
                    'section' => sanitize_key((string) $section),
                    'variable' => (string) $variable,
                    'type' => sanitize_key((string) (((array) $properties)['type'] ?? 'text')),
                ];
            }
        }

        return $registry;
    }

    private function normalize_scss_theme_mod_key(string $key): string {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        if (strpos($key, 'SCSSvar_') === 0) {
            $variable = substr($key, 8);
        } elseif ($key[0] === '$') {
            $variable = substr($key, 1);
        } else {
            $variable = str_replace('_', '-', $key);
        }

        $variable = strtolower(trim($variable));
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $variable)) {
            return '';
        }

        return 'SCSSvar_' . $variable;
    }

    private function sanitize_scss_value($value, string $type) {
        if ($type === 'boolean') {
            if (is_bool($value)) {
                return $value;
            }
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 500 || preg_match('/[;{}\r\n]/', $value) || preg_match('/@(import|use|forward)\b/i', $value)) {
            return null;
        }

        if ($type === 'color' && !preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value)) {
            return null;
        }

        return $value;
    }

    private function infer_variable_type(string $target): string {
        if (strpos($target, 'SCSSvar_enable-') === 0) {
            return 'boolean';
        }
        if (preg_match('/(?:^|-)color$|SCSSvar_(?:primary|secondary|success|info|warning|danger|light|dark|body-bg|body-color|text-muted)$/', $target)) {
            return 'color';
        }

        return 'text';
    }

    private function snapshot_theme_mods(array $keys): array {
        $mods = function_exists('get_theme_mods') ? (array) get_theme_mods() : [];
        $snapshot = [];

        foreach ($keys as $key) {
            $exists = array_key_exists($key, $mods);
            if (!$exists) {
                $sentinel = '__lcfa_missing_' . md5($key) . '__';
                $value = get_theme_mod($key, $sentinel);
                $exists = $value !== $sentinel;
            } else {
                $value = $mods[$key];
            }
            $snapshot[$key] = [
                'exists' => $exists,
                'value' => $exists ? $value : null,
            ];
        }

        ksort($snapshot, SORT_STRING);

        return $snapshot;
    }

    private function apply_theme_mod_plan(array $writes, array $removals): void {
        foreach ($removals as $key) {
            if (function_exists('remove_theme_mod')) {
                remove_theme_mod($key);
            }
        }
        foreach ($writes as $key => $value) {
            set_theme_mod($key, $value);
        }
    }

    private function restore_theme_mod_snapshot(array $snapshot): void {
        foreach ($snapshot as $key => $state) {
            if (!is_array($state)) {
                continue;
            }
            if (!empty($state['exists'])) {
                set_theme_mod((string) $key, $state['value'] ?? '');
            } elseif (function_exists('remove_theme_mod')) {
                remove_theme_mod((string) $key);
            }
        }
    }

    private function collect_changed_keys(array $snapshot, array $writes, array $removals): array {
        $changed = [];
        foreach ($writes as $key => $value) {
            if (empty($snapshot[$key]['exists']) || ($snapshot[$key]['value'] ?? null) !== $value) {
                $changed[] = $key;
            }
        }
        foreach ($removals as $key) {
            if (!empty($snapshot[$key]['exists'])) {
                $changed[] = $key;
            }
        }

        $changed = array_values(array_unique($changed));
        sort($changed, SORT_STRING);

        return $changed;
    }

    private function build_theme_mod_diff(array $snapshot, array $writes, array $removals): array {
        $diff = [];
        foreach ($writes as $key => $value) {
            $diff[$key] = [
                'before_exists' => !empty($snapshot[$key]['exists']),
                'before' => $snapshot[$key]['value'] ?? null,
                'after_exists' => true,
                'after' => $value,
            ];
        }
        foreach ($removals as $key) {
            $diff[$key] = [
                'before_exists' => !empty($snapshot[$key]['exists']),
                'before' => $snapshot[$key]['value'] ?? null,
                'after_exists' => false,
                'after' => null,
            ];
        }
        ksort($diff, SORT_STRING);

        return $diff;
    }

    private function snapshot_values(array $snapshot): array {
        $values = [];
        foreach ($snapshot as $key => $state) {
            if (!empty($state['exists'])) {
                $values[$key] = $state['value'] ?? null;
            }
        }

        return $values;
    }

    private function proposed_values(array $snapshot, array $writes, array $removals): array {
        $values = $this->snapshot_values($snapshot);
        foreach ($removals as $key) {
            unset($values[$key]);
        }
        foreach ($writes as $key => $value) {
            $values[$key] = $value;
        }
        ksort($values, SORT_STRING);

        return $values;
    }

    private function encode_diff_payload(array $values): string {
        return (string) wp_json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function get_current_scss_theme_mods(): array {
        $mods = function_exists('get_theme_mods') ? (array) get_theme_mods() : [];

        return array_filter($mods, static function ($value, $key): bool {
            return strpos((string) $key, 'SCSSvar_') === 0;
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function normalize_font_object($value): string {
        if (is_array($value) || is_object($value)) {
            return substr((string) wp_json_encode($value, JSON_UNESCAPED_SLASHES), 0, 5000);
        }

        return substr(trim((string) $value), 0, 5000);
    }

    private function sanitize_font_header_code(string $value): string {
        $value = substr(trim($value), 0, 10000);
        if (function_exists('wp_kses')) {
            return (string) wp_kses($value, [
                'link' => [
                    'rel' => true,
                    'href' => true,
                    'crossorigin' => true,
                    'type' => true,
                    'media' => true,
                ],
                'style' => [
                    'type' => true,
                    'media' => true,
                ],
            ]);
        }

        return strip_tags($value, '<link><style>');
    }

    private function build_font_warnings(array $payload): array {
        $source = is_array($payload['design_system'] ?? null)
            ? array_replace_recursive($payload, (array) $payload['design_system'])
            : $payload;
        $font_assets = is_array($source['font_assets'] ?? null) ? (array) $source['font_assets'] : [];
        $typography = is_array($source['typography'] ?? null) ? (array) $source['typography'] : [];

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

    private function error_result(bool $dry_run, string $message, array $warnings = []): array {
        return [
            'ok' => false,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'target_stack' => 'picostrap',
            'target_type' => 'design_system',
            'message' => $message,
            'summary' => __('Picostrap design system operation did not complete.', 'livecanvas-forge-ai'),
            'warnings' => array_values(array_unique($warnings)),
            'data' => [],
        ];
    }
}
