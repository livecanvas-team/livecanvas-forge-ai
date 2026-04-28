<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Apply {
    private LCFA_Environment $environment;
    private LCFA_Design_System_Picostrap_Executor $picostrap_executor;
    private LCFA_Design_System_Picowind_Executor $picowind_executor;
    private ?LCFA_Design_System_Fallback_Executor $fallback_executor;

    public function __construct(LCFA_Environment $environment, LCFA_Design_System_Picostrap_Executor $picostrap_executor, LCFA_Design_System_Picowind_Executor $picowind_executor, ?LCFA_Design_System_Fallback_Executor $fallback_executor = null) {
        $this->environment = $environment;
        $this->picostrap_executor = $picostrap_executor;
        $this->picowind_executor = $picowind_executor;
        $this->fallback_executor = $fallback_executor;
    }

    public function run(array $payload, bool $dry_run): array {
        $framework = $this->resolve_framework($payload);

        if ($framework === 'picostrap') {
            return $this->picostrap_executor->execute($payload, $dry_run);
        }

        if ($framework === 'picowind') {
            return $this->picowind_executor->execute($payload, $dry_run);
        }

        if ($this->fallback_executor) {
            return $this->fallback_executor->execute($payload, $dry_run);
        }

        return [
            'ok' => false,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => __('Unable to resolve a supported design-system target stack.', 'livecanvas-forge-ai'),
            'summary' => '',
            'warnings' => [],
            'data' => [
                'requested_framework' => (string) ($payload['framework'] ?? ''),
            ],
        ];
    }

    private function resolve_framework(array $payload): string {
        $explicit = sanitize_key((string) ($payload['framework'] ?? ''));

        if (in_array($explicit, ['picostrap', 'picowind', 'fallback_theme', 'custom', 'unknown'], true)) {
            if (in_array($explicit, ['custom', 'unknown'], true)) {
                return 'unknown';
            }

            return $explicit;
        }

        return $this->environment->detect_framework_family();
    }
}
