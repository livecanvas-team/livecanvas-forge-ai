<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Fallback_Executor {
    private const CSS_PATH = 'assets/lcfa-design-system.css';
    private const JSON_PATH = 'assets/lcfa-design-system.json';

    private LCFA_Environment $environment;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;

    public function __construct(LCFA_Environment $environment, LCFA_Theme_Files_Bridge $theme_files_bridge) {
        $this->environment = $environment;
        $this->theme_files_bridge = $theme_files_bridge;
    }

    public function execute(array $payload, bool $dry_run): array {
        $settings = LCFA_Settings::get();
        $effective_dry_run = $dry_run || empty($settings['allow_file_fallback']);
        $framework = sanitize_key((string) ($payload['framework'] ?? ''));
        $framework = $framework !== '' ? $framework : $this->environment->detect_framework_family();
        $tokens = $this->collect_tokens($payload);
        $css = $this->build_css($tokens);
        $manifest = $this->build_manifest($tokens, $framework);
        $writes = [];
        $warnings = [
            __('No Picostrap or Picowind design-system target was detected, so Forge uses portable theme assets instead.', 'livecanvas-forge-ai'),
            __('Import or enqueue assets/lcfa-design-system.css from the active theme to make the fallback tokens affect the frontend.', 'livecanvas-forge-ai'),
        ];

        if (!$dry_run && $effective_dry_run) {
            $warnings[] = __('Fallback file apply was downgraded to preview because theme/PHP fallback is disabled in policy.', 'livecanvas-forge-ai');
        }

        try {
            $writes['css'] = $this->theme_files_bridge->write_file([
                'root_scope' => 'stylesheet',
                'path'       => self::CSS_PATH,
                'content'    => $css,
                'dry_run'    => $effective_dry_run,
            ]);
            $writes['manifest'] = $this->theme_files_bridge->write_file([
                'root_scope' => 'stylesheet',
                'path'       => self::JSON_PATH,
                'content'    => wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'dry_run'    => $effective_dry_run,
            ]);
        } catch (Throwable $throwable) {
            if ($effective_dry_run) {
                $warnings[] = $throwable->getMessage();

                return [
                    'ok'               => true,
                    'action'           => 'design_system_apply',
                    'mode'             => 'preview',
                    'execution_target' => 'local',
                    'message'          => __('Fallback design-system asset preview prepared.', 'livecanvas-forge-ai'),
                    'target_stack'     => 'fallback_theme',
                    'source_of_truth'  => 'theme_file_assets',
                    'summary'          => __('Preview portable design-system assets for the active theme.', 'livecanvas-forge-ai'),
                    'changed_keys'     => array_values(array_filter(array_keys($tokens), static function (string $key) use ($tokens): bool {
                        return (string) $tokens[$key] !== '';
                    })),
                    'build_required'   => false,
                    'build_executed'   => false,
                    'build_strategy'   => 'theme_asset_fallback',
                    'warnings'         => array_values(array_unique(array_filter($warnings))),
                    'data'             => [
                        'requested_framework' => $framework,
                        'asset_paths'         => [
                            'css'      => self::CSS_PATH,
                            'manifest' => self::JSON_PATH,
                        ],
                        'writes'              => [
                            'css' => [
                                'dry_run' => true,
                                'path'    => self::CSS_PATH,
                                'bytes'   => strlen($css),
                            ],
                            'manifest' => [
                                'dry_run' => true,
                                'path'    => self::JSON_PATH,
                                'bytes'   => strlen(wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                            ],
                        ],
                        'tokens'              => $tokens,
                    ],
                ];
            }

            return [
                'ok'               => false,
                'action'           => 'design_system_apply',
                'mode'             => $effective_dry_run ? 'preview' : 'apply',
                'execution_target' => 'local',
                'message'          => $throwable->getMessage(),
                'target_stack'     => 'fallback_theme',
                'source_of_truth'  => 'theme_file_assets',
                'summary'          => '',
                'changed_keys'     => [],
                'build_required'   => false,
                'build_executed'   => false,
                'build_strategy'   => 'theme_asset_fallback',
                'warnings'         => $warnings,
                'data'             => [
                    'requested_framework' => $framework,
                    'asset_paths'         => [
                        'css'      => self::CSS_PATH,
                        'manifest' => self::JSON_PATH,
                    ],
                ],
            ];
        }

        $changed_keys = array_values(array_filter(array_keys($tokens), static function (string $key) use ($tokens): bool {
            return (string) $tokens[$key] !== '';
        }));

        return [
            'ok'               => true,
            'action'           => 'design_system_apply',
            'mode'             => $effective_dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message'          => $effective_dry_run
                ? __('Fallback design-system asset preview prepared.', 'livecanvas-forge-ai')
                : __('Fallback design-system assets written.', 'livecanvas-forge-ai'),
            'target_stack'     => 'fallback_theme',
            'source_of_truth'  => 'theme_file_assets',
            'summary'          => $effective_dry_run
                ? __('Preview portable design-system assets for the active theme.', 'livecanvas-forge-ai')
                : __('Wrote portable design-system assets into the active child theme.', 'livecanvas-forge-ai'),
            'changed_keys'     => $changed_keys,
            'build_required'   => false,
            'build_executed'   => false,
            'build_strategy'   => 'theme_asset_fallback',
            'warnings'         => $warnings,
            'data'             => [
                'requested_framework' => $framework,
                'asset_paths'         => [
                    'css'      => self::CSS_PATH,
                    'manifest' => self::JSON_PATH,
                ],
                'writes'              => $writes,
                'tokens'              => $tokens,
            ],
        ];
    }

    private function collect_tokens(array $payload): array {
        $colors = is_array($payload['colors'] ?? null) ? $payload['colors'] : [];
        $typography = is_array($payload['typography'] ?? null) ? $payload['typography'] : [];
        $radius = is_array($payload['radius'] ?? null) ? $payload['radius'] : [];
        $buttons = is_array($payload['buttons'] ?? null) ? $payload['buttons'] : [];

        return array_filter([
            'color_primary'            => $this->normalize_css_value((string) ($colors['primary'] ?? '')),
            'color_secondary'          => $this->normalize_css_value((string) ($colors['secondary'] ?? '')),
            'color_success'            => $this->normalize_css_value((string) ($colors['success'] ?? '')),
            'color_warning'            => $this->normalize_css_value((string) ($colors['warning'] ?? '')),
            'color_danger'             => $this->normalize_css_value((string) ($colors['danger'] ?? '')),
            'color_body_bg'            => $this->normalize_css_value((string) ($colors['body_bg'] ?? '')),
            'color_body_color'         => $this->normalize_css_value((string) ($colors['body_color'] ?? '')),
            'font_family_base'         => $this->normalize_css_value((string) ($typography['font_family_base'] ?? '')),
            'headings_font_family'     => $this->normalize_css_value((string) ($typography['headings_font_family'] ?? '')),
            'font_size_base'           => $this->normalize_css_value((string) ($typography['font_size_base'] ?? '')),
            'line_height_base'         => $this->normalize_css_value((string) ($typography['line_height_base'] ?? '')),
            'border_radius'            => $this->normalize_css_value((string) ($radius['border_radius'] ?? '')),
            'border_radius_sm'         => $this->normalize_css_value((string) ($radius['border_radius_sm'] ?? '')),
            'border_radius_lg'         => $this->normalize_css_value((string) ($radius['border_radius_lg'] ?? '')),
            'button_padding_y'         => $this->normalize_css_value((string) ($buttons['btn_padding_y'] ?? '')),
            'button_padding_x'         => $this->normalize_css_value((string) ($buttons['btn_padding_x'] ?? '')),
            'button_border_radius'     => $this->normalize_css_value((string) ($buttons['btn_border_radius'] ?? '')),
        ], static function (string $value): bool {
            return $value !== '';
        });
    }

    private function build_css(array $tokens): string {
        $lines = [
            '/* Generated by LiveCanvas Forge AI fallback design-system executor. */',
            ':root {',
        ];

        foreach ($tokens as $key => $value) {
            $lines[] = '  --lcfa-' . str_replace('_', '-', $key) . ': ' . $value . ';';
        }

        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'body {';
        $lines[] = '  background: var(--lcfa-color-body-bg, inherit);';
        $lines[] = '  color: var(--lcfa-color-body-color, inherit);';
        $lines[] = '  font-family: var(--lcfa-font-family-base, inherit);';
        $lines[] = '  font-size: var(--lcfa-font-size-base, inherit);';
        $lines[] = '  line-height: var(--lcfa-line-height-base, inherit);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'h1, h2, h3, h4, h5, h6 {';
        $lines[] = '  font-family: var(--lcfa-headings-font-family, var(--lcfa-font-family-base, inherit));';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'a, .lcfa-link {';
        $lines[] = '  color: var(--lcfa-color-primary, currentColor);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.btn-primary, .lcfa-button-primary {';
        $lines[] = '  background-color: var(--lcfa-color-primary, currentColor);';
        $lines[] = '  border-color: var(--lcfa-color-primary, currentColor);';
        $lines[] = '  border-radius: var(--lcfa-button-border-radius, var(--lcfa-border-radius, inherit));';
        $lines[] = '  padding-block: var(--lcfa-button-padding-y, inherit);';
        $lines[] = '  padding-inline: var(--lcfa-button-padding-x, inherit);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.lcfa-section-starter, .lcfa-card {';
        $lines[] = '  border-radius: var(--lcfa-border-radius-lg, var(--lcfa-border-radius, inherit));';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function build_manifest(array $tokens, string $framework): array {
        return [
            'generator'       => 'livecanvas-forge-ai',
            'generated_at'    => current_time('mysql', true),
            'target_stack'    => 'fallback_theme',
            'framework'       => $framework !== '' ? $framework : 'unknown',
            'source_of_truth' => 'theme_file_assets',
            'css_path'        => self::CSS_PATH,
            'tokens'          => $tokens,
        ];
    }

    private function normalize_css_value(string $value): string {
        $value = trim(sanitize_text_field($value));

        if ($value === '' || preg_match('/[;{}<>]/', $value)) {
            return '';
        }

        return substr($value, 0, 160);
    }
}
