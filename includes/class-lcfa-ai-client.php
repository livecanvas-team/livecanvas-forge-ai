<?php

defined('ABSPATH') || exit;

final class LCFA_AI_Client {
    public function get_status(): array {
        $connectors = $this->detect_connectors();

        if (!function_exists('wp_ai_client_prompt')) {
            return [
                'available'                 => false,
                'text_generation_supported' => false,
                'connectors'                => $connectors,
                'message'                   => __('WordPress AI Client is not available on this site.', 'livecanvas-forge-ai'),
            ];
        }

        $builder = wp_ai_client_prompt('LiveCanvas Forge AI capability check.');
        $supported = is_object($builder)
            && method_exists($builder, 'is_supported_for_text_generation')
            && $builder->is_supported_for_text_generation();

        return [
            'available'                 => true,
            'text_generation_supported' => $supported,
            'json_response_supported'   => is_object($builder) && method_exists($builder, 'as_json_response'),
            'model_preference_supported'=> is_object($builder) && method_exists($builder, 'using_model_preference'),
            'connectors'                => $connectors,
            'message'                   => $supported
                ? __('WordPress AI Client text generation is available.', 'livecanvas-forge-ai')
                : __('WordPress AI Client is installed, but no configured connector supports text generation for this request.', 'livecanvas-forge-ai'),
        ];
    }

    public function generate_text(string $prompt, array $options = []) {
        $generated = $this->generate_raw($prompt, $options);
        if (is_wp_error($generated)) {
            return $generated;
        }

        if (is_array($generated) || is_object($generated)) {
            $generated = $this->encode_json($generated);
        }

        return [
            'ok'     => true,
            'text'   => (string) $generated,
            'status' => $this->get_status(),
        ];
    }

    public function generate_json(string $prompt, array $schema, array $options = []) {
        $options['response_schema'] = $schema;
        $generated = $this->generate_raw($prompt, $options);

        if (is_wp_error($generated)) {
            return $generated;
        }

        if (is_object($generated)) {
            $generated = (array) $generated;
        }

        if (is_array($generated)) {
            return [
                'ok'     => true,
                'data'   => $generated,
                'status' => $this->get_status(),
            ];
        }

        $decoded = json_decode((string) $generated, true);
        if (!is_array($decoded)) {
            return new WP_Error('lcfa_ai_json_invalid', __('WordPress AI Client did not return valid JSON for this structured request.', 'livecanvas-forge-ai'));
        }

        return [
            'ok'     => true,
            'data'   => $decoded,
            'status' => $this->get_status(),
        ];
    }

    private function generate_raw(string $prompt, array $options = []) {
        $prompt = trim($prompt);

        if ($prompt === '') {
            return new WP_Error('lcfa_ai_empty_prompt', __('A prompt is required.', 'livecanvas-forge-ai'));
        }

        if (!function_exists('wp_ai_client_prompt')) {
            return new WP_Error('lcfa_ai_client_unavailable', __('WordPress AI Client is not available on this site.', 'livecanvas-forge-ai'));
        }

        $builder = wp_ai_client_prompt($prompt);
        if (!is_object($builder)) {
            return new WP_Error('lcfa_ai_client_invalid_builder', __('WordPress AI Client did not return a prompt builder.', 'livecanvas-forge-ai'));
        }

        $builder = $this->apply_options($builder, $options);

        if (method_exists($builder, 'is_supported_for_text_generation') && !$builder->is_supported_for_text_generation()) {
            return new WP_Error('lcfa_ai_text_unsupported', __('No configured connector supports this AI text generation request.', 'livecanvas-forge-ai'));
        }

        if (!method_exists($builder, 'generate_text')) {
            return new WP_Error('lcfa_ai_text_method_missing', __('The WordPress AI Client text generation method is unavailable.', 'livecanvas-forge-ai'));
        }

        return $builder->generate_text();
    }

    private function apply_options(object $builder, array $options): object {
        $system_instruction = trim((string) ($options['system_instruction'] ?? ''));
        if ($system_instruction !== '' && method_exists($builder, 'using_system_instruction')) {
            $builder = $builder->using_system_instruction($system_instruction);
        }

        if (isset($options['temperature']) && method_exists($builder, 'using_temperature')) {
            $temperature = (float) $options['temperature'];
            if ($temperature >= 0 && $temperature <= 2) {
                $builder = $builder->using_temperature($temperature);
            }
        }

        if (isset($options['max_tokens']) && method_exists($builder, 'using_max_tokens')) {
            $max_tokens = absint($options['max_tokens']);
            if ($max_tokens > 0) {
                $builder = $builder->using_max_tokens($max_tokens);
            }
        }

        if (!empty($options['model_preference']) && is_array($options['model_preference']) && method_exists($builder, 'using_model_preference')) {
            $models = array_values(array_filter(array_map(static function ($model): string {
                return sanitize_text_field((string) $model);
            }, $options['model_preference'])));

            if ($models !== []) {
                $builder = $builder->using_model_preference(...$models);
            }
        }

        if (!empty($options['response_schema']) && is_array($options['response_schema']) && method_exists($builder, 'as_json_response')) {
            $builder = $builder->as_json_response($options['response_schema']);
        }

        return $builder;
    }

    private function encode_json($value): string {
        if (function_exists('wp_json_encode')) {
            return (string) wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function detect_connectors(): array {
        $candidates = [
            'wp_get_ai_connectors',
            'wp_ai_get_connectors',
            'wp_get_available_ai_connectors',
            'wp_get_ai_services',
        ];

        foreach ($candidates as $function_name) {
            if (!function_exists($function_name)) {
                continue;
            }

            try {
                $reflection = new ReflectionFunction($function_name);
                if ($reflection->getNumberOfRequiredParameters() > 0) {
                    continue;
                }

                $raw_connectors = $function_name();
            } catch (Throwable $throwable) {
                return [
                    'available' => false,
                    'source'    => $function_name,
                    'count'     => 0,
                    'items'     => [],
                    'message'   => $throwable->getMessage(),
                ];
            }

            if (!is_array($raw_connectors)) {
                continue;
            }

            $items = [];
            foreach ($raw_connectors as $key => $connector) {
                $items[] = $this->normalize_connector_item($key, $connector);
            }

            return [
                'available' => true,
                'source'    => $function_name,
                'count'     => count($items),
                'items'     => $items,
                'text_generation_count' => count(array_filter($items, static function (array $item): bool {
                    return !empty($item['supports_text_generation']);
                })),
            ];
        }

        return [
            'available' => false,
            'source'    => '',
            'count'     => 0,
            'items'     => [],
            'message'   => __('No WordPress Connectors registry function was detected.', 'livecanvas-forge-ai'),
        ];
    }

    private function normalize_connector_item($key, $connector): array {
        $data = is_array($connector) ? $connector : [];
        $id = sanitize_key((string) ($data['id'] ?? $data['name'] ?? $key));
        $label = sanitize_text_field((string) ($data['label'] ?? $data['title'] ?? $data['name'] ?? $id));
        $capabilities = array_values(array_filter(array_map('sanitize_key', (array) ($data['capabilities'] ?? $data['supports'] ?? []))));

        if (is_object($connector)) {
            if (method_exists($connector, 'get_id')) {
                $id = sanitize_key((string) $connector->get_id());
            }
            if (method_exists($connector, 'get_name')) {
                $label = sanitize_text_field((string) $connector->get_name());
            } elseif (method_exists($connector, 'get_label')) {
                $label = sanitize_text_field((string) $connector->get_label());
            }
            if (method_exists($connector, 'get_capabilities')) {
                $capabilities = array_values(array_filter(array_map('sanitize_key', (array) $connector->get_capabilities())));
            }
        }

        $supports_text = in_array('text_generation', $capabilities, true)
            || in_array('generate_text', $capabilities, true)
            || in_array('text', $capabilities, true);

        return [
            'id'                       => $id,
            'label'                    => $label !== '' ? $label : $id,
            'capabilities'             => $capabilities,
            'supports_text_generation' => $supports_text,
        ];
    }
}
