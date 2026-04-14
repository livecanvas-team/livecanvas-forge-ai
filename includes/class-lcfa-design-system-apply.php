<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Apply {
    private LCFA_Environment $environment;
    private LCFA_Design_System_Picostrap_Executor $picostrap_executor;
    private LCFA_Design_System_Picowind_Executor $picowind_executor;

    public function __construct(LCFA_Environment $environment, LCFA_Design_System_Picostrap_Executor $picostrap_executor, LCFA_Design_System_Picowind_Executor $picowind_executor) {
        $this->environment = $environment;
        $this->picostrap_executor = $picostrap_executor;
        $this->picowind_executor = $picowind_executor;
    }

    public function run(array $payload, bool $dry_run): array {
        $framework = $this->resolve_framework($payload);

        if ($framework === 'picostrap') {
            return $this->picostrap_executor->execute($payload, $dry_run);
        }

        if ($framework === 'picowind') {
            return $this->picowind_executor->execute($payload, $dry_run);
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

        if (in_array($explicit, ['picostrap', 'picowind'], true)) {
            return $explicit;
        }

        return $this->environment->detect_framework_family();
    }
}
