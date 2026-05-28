<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Compose {
    private const SUPPORTED_COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'light', 'dark', 'body_bg', 'body_color'];
    private const SUPPORTED_TYPOGRAPHY = ['font_family_base', 'headings_font_family', 'font_size_base', 'line_height_base'];
    private const SUPPORTED_RADIUS = ['border_radius', 'border_radius_sm', 'border_radius_lg'];
    private const SUPPORTED_BUTTONS = ['btn_padding_y', 'btn_padding_x', 'btn_border_radius'];

    private LCFA_Environment $environment;
    private LCFA_Design_System_Picostrap_Composer $picostrap_composer;
    private LCFA_Design_System_Apply $design_system_apply;
    private LCFA_Design_System_Preview $design_system_preview;
    private ?LCFA_AI_Client $ai_client;

    public function __construct(LCFA_Environment $environment, LCFA_Design_System_Picostrap_Composer $picostrap_composer, LCFA_Design_System_Apply $design_system_apply, ?LCFA_Design_System_Preview $design_system_preview = null, ?LCFA_AI_Client $ai_client = null) {
        $this->environment = $environment;
        $this->picostrap_composer = $picostrap_composer;
        $this->design_system_apply = $design_system_apply;
        $this->design_system_preview = $design_system_preview ?: new LCFA_Design_System_Preview();
        $this->ai_client = $ai_client;
    }

    public function run(array $payload): array {
        $framework = sanitize_key((string) ($payload['framework'] ?? $this->environment->detect_framework_family()));
        $auto_apply = !empty($payload['auto_apply']);

        if ($framework !== 'picostrap') {
            return [
                'ok' => false,
                'action' => 'design_system_compose',
                'mode' => 'preview',
                'execution_target' => 'local',
                'target_stack' => $framework,
                'message' => __('design_system_compose first slice currently supports Picostrap only.', 'livecanvas-forge-ai'),
                'summary' => __('Unsupported design-system compose stack.', 'livecanvas-forge-ai'),
                'warnings' => [],
                'preview' => [],
                'apply_payload' => [],
                'data' => [
                    'supports_apply' => false,
                    'preview_only' => true,
                ],
            ];
        }

        $result = $this->picostrap_composer->compose($payload);

        if (empty($result['ok'])) {
            return array_merge([
                'ok' => false,
                'action' => 'design_system_compose',
                'mode' => 'preview',
                'execution_target' => 'local',
                'target_stack' => 'picostrap',
                'summary' => __('Unable to compose a safe Picostrap design system preview.', 'livecanvas-forge-ai'),
                'preview' => [],
                'apply_payload' => [],
                'data' => [
                    'supports_apply' => false,
                    'preview_only' => true,
                ],
            ], $result);
        }

        $result = $this->maybe_enhance_picostrap_compose_with_ai($payload, $result);

        $composed = array_merge([
            'ok' => true,
            'action' => 'design_system_compose',
            'mode' => 'preview',
            'execution_target' => 'local',
            'target_stack' => 'picostrap',
            'data' => [
                'supports_apply' => true,
                'preview_only' => true,
            ],
        ], $result);
        $composed['data'] = array_merge([
            'supports_apply' => true,
            'preview_only' => true,
        ], (array) ($result['data'] ?? []));

        $preview_url = $this->design_system_preview->store($composed);

        if (!$auto_apply) {
            $composed['preview_url'] = $preview_url;
            return $composed;
        }

        $apply_result = $this->design_system_apply->run($composed['apply_payload'], false);

        if (empty($apply_result['ok'])) {
            return array_merge($composed, [
                'ok' => false,
                'mode' => 'apply',
                'message' => __('Design system preview prepared, but apply failed.', 'livecanvas-forge-ai'),
                'summary' => __('Composed a Picostrap design system preview, but apply did not complete.', 'livecanvas-forge-ai'),
                'warnings' => array_values(array_merge((array) ($composed['warnings'] ?? []), [(string) ($apply_result['message'] ?? __('Apply failed.', 'livecanvas-forge-ai'))])),
                'data' => array_merge((array) ($composed['data'] ?? []), [
                    'supports_apply' => true,
                    'preview_only' => false,
                    'auto_applied' => false,
                    'apply_result' => $apply_result,
                ]),
            ]);
        }

        $this->design_system_preview->store(array_merge($composed, $apply_result));

        return array_merge($composed, $apply_result, [
            'ok' => true,
            'action' => 'design_system_compose',
            'mode' => 'apply',
            'execution_target' => 'local',
            'message' => __('Design system preview prepared and applied.', 'livecanvas-forge-ai'),
            'summary' => __('Composed and applied a Picostrap design system.', 'livecanvas-forge-ai'),
            'target_stack' => 'picostrap',
            'preview' => $composed['preview'],
            'apply_payload' => $composed['apply_payload'],
            'preview_url' => $preview_url,
            'frontend_url' => home_url('/'),
            'warnings' => array_values(array_merge((array) ($composed['warnings'] ?? []), (array) ($apply_result['warnings'] ?? []))),
            'data' => array_merge((array) ($apply_result['data'] ?? []), [
                'supports_apply' => true,
                'preview_only' => false,
                'auto_applied' => true,
                'frontend_url' => home_url('/'),
            ]),
        ]);
    }

    private function maybe_enhance_picostrap_compose_with_ai(array $payload, array $fallback): array {
        if (!$this->ai_client) {
            return $this->with_ai_metadata($fallback, false, __('WordPress AI Client is not wired into design_system_compose.', 'livecanvas-forge-ai'));
        }

        $status = $this->ai_client->get_status();
        if (empty($status['available']) || empty($status['text_generation_supported'])) {
            return $this->with_ai_metadata($fallback, false, (string) ($status['message'] ?? __('WordPress AI Client is not ready for design-system composition.', 'livecanvas-forge-ai')));
        }

        $generated = $this->ai_client->generate_json(
            $this->build_ai_design_system_prompt($payload, $fallback),
            $this->get_ai_design_system_schema(),
            [
                'system_instruction' => __('You produce strict Picostrap/Bootstrap design tokens. Return only supported token keys and avoid one-hue palettes.', 'livecanvas-forge-ai'),
                'temperature'        => 0.35,
                'max_tokens'         => 1400,
            ]
        );

        if (function_exists('is_wp_error') && is_wp_error($generated)) {
            return $this->with_ai_metadata($fallback, false, $generated->get_error_message());
        }

        $data = is_array($generated['data'] ?? null) ? $generated['data'] : [];
        if ($data === []) {
            return $this->with_ai_metadata($fallback, false, __('WordPress AI Client returned an empty design-system response.', 'livecanvas-forge-ai'));
        }

        $tokens = [
            'colors' => $this->merge_supported_tokens(
                (array) ($fallback['apply_payload']['colors'] ?? []),
                (array) ($data['colors'] ?? []),
                self::SUPPORTED_COLORS,
                [$this, 'sanitize_hex_color_value']
            ),
            'typography' => $this->merge_supported_tokens(
                (array) ($fallback['apply_payload']['typography'] ?? []),
                (array) ($data['typography'] ?? []),
                self::SUPPORTED_TYPOGRAPHY,
                [$this, 'sanitize_css_token_value']
            ),
            'radius' => $this->merge_supported_tokens(
                (array) ($fallback['apply_payload']['radius'] ?? []),
                (array) ($data['radius'] ?? []),
                self::SUPPORTED_RADIUS,
                [$this, 'sanitize_css_token_value']
            ),
            'buttons' => $this->merge_supported_tokens(
                (array) ($fallback['apply_payload']['buttons'] ?? []),
                (array) ($data['buttons'] ?? []),
                self::SUPPORTED_BUTTONS,
                [$this, 'sanitize_css_token_value']
            ),
        ];

        $warnings = array_values(array_merge(
            (array) ($fallback['warnings'] ?? []),
            $this->sanitize_text_list((array) ($data['warnings'] ?? []))
        ));
        $mood = sanitize_text_field((string) ($data['mood'] ?? ($fallback['preview']['mood'] ?? '')));

        return array_merge($fallback, [
            'summary' => __('Composed a Picostrap design system preview with WordPress AI Client.', 'livecanvas-forge-ai'),
            'message' => __('AI-assisted design system preview prepared.', 'livecanvas-forge-ai'),
            'preview' => [
                'mood' => $mood !== '' ? $mood : (string) ($fallback['preview']['mood'] ?? 'balanced'),
                'palette' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'apply_payload' => [
                'action' => 'design_system_apply',
                'framework' => 'picostrap',
                'colors' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'warnings' => $warnings,
            'data' => array_merge((array) ($fallback['data'] ?? []), [
                'ai_client' => [
                    'available' => true,
                    'used' => true,
                    'provider' => 'wordpress_ai_client',
                    'message' => __('Design system tokens were enhanced with WordPress AI Client.', 'livecanvas-forge-ai'),
                ],
            ]),
        ]);
    }

    private function with_ai_metadata(array $result, bool $used, string $message): array {
        $result['data'] = array_merge((array) ($result['data'] ?? []), [
            'ai_client' => [
                'available' => $this->ai_client ? !empty($this->ai_client->get_status()['available']) : false,
                'used' => $used,
                'provider' => 'wordpress_ai_client',
                'message' => $message,
            ],
        ]);

        return $result;
    }

    private function build_ai_design_system_prompt(array $payload, array $fallback): string {
        return implode("\n\n", [
            'Create a strict Picostrap design-system token pass from this creative request.',
            'Return JSON only. Use only the token keys in the schema. Keep values directly usable by Bootstrap/Picostrap Sass variables.',
            'Creative request:',
            (string) ($payload['prompt'] ?? ''),
            'Optional brand personality:',
            $this->encode_json((array) ($payload['brand_personality'] ?? [])),
            'Current deterministic fallback:',
            $this->encode_json($fallback),
        ]);
    }

    private function get_ai_design_system_schema(): array {
        $string_properties = static function (array $keys): array {
            $properties = [];
            foreach ($keys as $key) {
                $properties[$key] = ['type' => 'string'];
            }

            return $properties;
        };

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'mood' => ['type' => 'string'],
                'colors' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $string_properties(self::SUPPORTED_COLORS),
                ],
                'typography' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $string_properties(self::SUPPORTED_TYPOGRAPHY),
                ],
                'radius' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $string_properties(self::SUPPORTED_RADIUS),
                ],
                'buttons' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $string_properties(self::SUPPORTED_BUTTONS),
                ],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    private function merge_supported_tokens(array $fallback, array $candidate, array $supported_keys, callable $sanitize): array {
        $tokens = array_intersect_key($fallback, array_flip($supported_keys));

        foreach ($supported_keys as $key) {
            if (!array_key_exists($key, $candidate)) {
                continue;
            }

            $value = $sanitize((string) $candidate[$key]);
            if ($value !== '') {
                $tokens[$key] = $value;
            }
        }

        return $tokens;
    }

    private function sanitize_hex_color_value(string $value): string {
        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    private function sanitize_css_token_value(string $value): string {
        $value = trim(sanitize_text_field($value));

        if ($value === '' || preg_match('/[;{}<>]/', $value) === 1) {
            return '';
        }

        return $value;
    }

    private function sanitize_text_list(array $values): array {
        $sanitized = [];

        foreach ($values as $value) {
            $value = sanitize_text_field((string) $value);
            if ($value !== '') {
                $sanitized[] = $value;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private function encode_json($value): string {
        if (function_exists('wp_json_encode')) {
            return (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
