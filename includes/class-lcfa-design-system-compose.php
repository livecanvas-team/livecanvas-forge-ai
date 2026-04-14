<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Compose {
    private LCFA_Environment $environment;
    private LCFA_Design_System_Picostrap_Composer $picostrap_composer;
    private LCFA_Design_System_Apply $design_system_apply;
    private LCFA_Design_System_Preview $design_system_preview;

    public function __construct(LCFA_Environment $environment, LCFA_Design_System_Picostrap_Composer $picostrap_composer, LCFA_Design_System_Apply $design_system_apply, ?LCFA_Design_System_Preview $design_system_preview = null) {
        $this->environment = $environment;
        $this->picostrap_composer = $picostrap_composer;
        $this->design_system_apply = $design_system_apply;
        $this->design_system_preview = $design_system_preview ?: new LCFA_Design_System_Preview();
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
}
