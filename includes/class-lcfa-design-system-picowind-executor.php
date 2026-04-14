<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picowind_Executor {
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Design_System_Build_Gateway $build_gateway;

    public function __construct(LCFA_WindPress_Bridge $windpress_bridge, LCFA_Theme_Files_Bridge $theme_files_bridge, LCFA_Design_System_Build_Gateway $build_gateway) {
        $this->windpress_bridge = $windpress_bridge;
        $this->theme_files_bridge = $theme_files_bridge;
        $this->build_gateway = $build_gateway;
    }

    public function execute(array $payload, bool $dry_run): array {
        $status = $this->windpress_bridge->get_status();

        if (empty($status['active'])) {
            return [
                'ok' => false,
                'action' => 'design_system_apply',
                'mode' => $dry_run ? 'preview' : 'apply',
                'execution_target' => 'local',
                'message' => __('WindPress must be active before Picowind design tokens can be applied.', 'livecanvas-forge-ai'),
                'summary' => '',
                'warnings' => [],
                'data' => [],
            ];
        }

        $preset = is_array($payload['preset'] ?? null) ? $payload['preset'] : [];
        $theme_json = $this->build_theme_json($payload);
        $changed_keys = $this->collect_changed_keys($payload, $preset);
        $warnings = [];
        $build_required = true;
        $build_executed = false;

        if (!$dry_run) {
            if (!empty($preset['active_theme'])) {
                set_theme_mod('data_theme', (string) $preset['active_theme']);
            }

            if (!empty($preset['skin'])) {
                $this->theme_files_bridge->write_file([
                    'root_scope' => 'stylesheet',
                    'path' => 'public/styles/presets/daisyui.css',
                    'content' => $this->render_daisyui_preset((string) $preset['skin']),
                ]);
            }

            $stored = $this->windpress_bridge->save_theme_json($theme_json);

            if (empty($stored['ok'])) {
                return [
                    'ok' => false,
                    'action' => 'design_system_apply',
                    'mode' => 'apply',
                    'execution_target' => 'local',
                    'message' => (string) ($stored['message'] ?? __('Unable to store WindPress theme.json.', 'livecanvas-forge-ai')),
                    'summary' => '',
                    'warnings' => [],
                    'data' => [],
                ];
            }
        }

        $gateway_status = $this->build_gateway->get_status();

        if (!$dry_run && !empty($gateway_status['build_available'])) {
            $build = $this->build_gateway->build_windpress_cache([
                'kind' => 'full',
                'store' => true,
                'source_map' => false,
            ]);

            if (!empty($build['ok'])) {
                $build_executed = true;
            } else {
                $warnings[] = (string) ($build['message'] ?? __('WindPress build failed after storing theme.json.', 'livecanvas-forge-ai'));
            }
        } elseif (!$dry_run) {
            $warnings[] = (string) ($gateway_status['message'] ?? __('WindPress build is not available from this runtime.', 'livecanvas-forge-ai'));
        }

        return [
            'ok' => true,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => $dry_run
                ? __('Picowind design system preview prepared.', 'livecanvas-forge-ai')
                : __('Picowind design system applied.', 'livecanvas-forge-ai'),
            'target_stack' => 'picowind',
            'source_of_truth' => 'windpress_cache_runtime',
            'summary' => $dry_run
                ? __('Preview Picowind design system changes.', 'livecanvas-forge-ai')
                : __('Applied design system tokens to Picowind and WindPress.', 'livecanvas-forge-ai'),
            'changed_keys' => $changed_keys,
            'build_required' => $build_required,
            'build_executed' => $build_executed,
            'build_strategy' => 'windpress_runtime_build',
            'warnings' => $warnings,
            'data' => [
                'theme_json' => $theme_json,
                'active_theme' => (string) ($preset['active_theme'] ?? ''),
                'preset_skin' => (string) ($preset['skin'] ?? ''),
            ],
        ];
    }

    private function build_theme_json(array $payload): array {
        $colors = is_array($payload['colors'] ?? null) ? $payload['colors'] : [];
        $typography = is_array($payload['typography'] ?? null) ? $payload['typography'] : [];
        $radius = is_array($payload['radius'] ?? null) ? $payload['radius'] : [];

        return [
            '$schema' => 'https://schemas.wp.org/trunk/theme.json',
            'version' => 3,
            'settings' => [
                'color' => [
                    'palette' => array_values(array_filter([
                        !empty($colors['primary']) ? ['slug' => 'primary', 'name' => 'Primary', 'color' => (string) $colors['primary']] : null,
                        !empty($colors['secondary']) ? ['slug' => 'secondary', 'name' => 'Secondary', 'color' => (string) $colors['secondary']] : null,
                        !empty($colors['light']) ? ['slug' => 'light', 'name' => 'Light', 'color' => (string) $colors['light']] : null,
                        !empty($colors['dark']) ? ['slug' => 'dark', 'name' => 'Dark', 'color' => (string) $colors['dark']] : null,
                    ])),
                ],
                'typography' => [
                    'fontFamilies' => !empty($typography['font_family_base']) ? [[
                        'slug' => 'body',
                        'name' => 'Body',
                        'fontFamily' => (string) $typography['font_family_base'],
                    ]] : [],
                ],
            ],
            'styles' => [
                'color' => [
                    'background' => (string) ($colors['body_bg'] ?? ''),
                    'text' => (string) ($colors['body_color'] ?? ''),
                ],
                'typography' => [
                    'fontSize' => (string) ($typography['font_size_base'] ?? ''),
                    'lineHeight' => (string) ($typography['line_height_base'] ?? ''),
                ],
                'elements' => [
                    'button' => [
                        'border' => [
                            'radius' => (string) ($radius['border_radius'] ?? ''),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function collect_changed_keys(array $payload, array $preset): array {
        $changed = [];

        if (!empty($preset['skin'])) {
            $changed[] = 'preset.skin';
        }

        if (!empty($preset['active_theme'])) {
            $changed[] = 'theme_mod.data_theme';
        }

        foreach (['primary', 'secondary', 'light', 'dark', 'body_bg', 'body_color'] as $key) {
            if (!empty($payload['colors'][$key])) {
                $changed[] = 'theme_json.color.' . $key;
            }
        }

        foreach (['font_family_base', 'font_size_base', 'line_height_base'] as $key) {
            if (!empty($payload['typography'][$key])) {
                $changed[] = 'theme_json.typography.' . $key;
            }
        }

        if (!empty($payload['radius']['border_radius'])) {
            $changed[] = 'theme_json.radius.border_radius';
        }

        return $changed;
    }

    private function render_daisyui_preset(string $skin): string {
        return "/**\n * Create a custom theme for yourself using daisyUI theme generator.\n * https://daisyui.com/theme-generator/\n */\n\n@plugin \"daisyui\" {\n    themes: {$skin} --default, dark;\n}\n\n/* @plugin \"@tailwindcss/typography\"; */\n\n/* Add your custom styles below this line */\n\nbody {\n}\n";
    }
}
