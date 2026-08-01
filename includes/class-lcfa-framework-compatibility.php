<?php

defined('ABSPATH') || exit;

final class LCFA_Framework_Compatibility {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function hooks(): void {
        add_filter('f!windpress/core/runtime:is_prevent_load', [$this, 'prevent_windpress_runtime_on_picostrap'], 20);
    }

    public function prevent_windpress_runtime_on_picostrap(bool $prevent): bool {
        if ($prevent) {
            return true;
        }

        $snapshot = $this->environment->get_snapshot();
        if (sanitize_key((string) ($snapshot['detected_framework'] ?? '')) !== 'picostrap') {
            return false;
        }

        return (bool) apply_filters(
            'lcfa_disable_windpress_runtime_on_picostrap',
            true,
            $snapshot
        );
    }
}
