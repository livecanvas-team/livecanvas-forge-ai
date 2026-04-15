<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Onboarding {
    private LCFA_Connection_Bundle_Builder $bundle_builder;
    private const STEP_ORDER = [
        'choose_client',
        'choose_claude_target',
        'choose_mode',
        'confirm_details',
        'generate_bundle',
        'smoke_test',
        'ready',
    ];

    public function __construct(LCFA_Connection_Bundle_Builder $bundle_builder) {
        $this->bundle_builder = $bundle_builder;
    }

    public function derive_state(array $connections, array $capabilities = []): array {
        $status = sanitize_key((string) ($connections['connection_status'] ?? ''));
        $saved_step = $this->normalize_step((string) ($connections['connection_current_step'] ?? ''));
        $preferred_client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        $claude_connection_target = sanitize_key((string) ($connections['claude_connection_target'] ?? ''));
        $connection_mode = sanitize_key((string) ($connections['connection_mode'] ?? ''));
        $last_verified_at = sanitize_text_field((string) ($connections['connection_last_verified_at'] ?? ''));
        $last_error = sanitize_text_field((string) ($connections['connection_last_error'] ?? ''));
        $is_opencode_local = $this->is_opencode_local($preferred_client, $connection_mode);
        $is_claude = $this->is_claude($preferred_client);

        if ($status === 'ready' && $last_verified_at !== '') {
            return [
                'status'           => 'ready',
                'current_step'     => 'ready',
                'last_verified_at' => $last_verified_at,
                'message'          => __('Connection verified.', 'livecanvas-forge-ai'),
            ];
        }

        if ($last_error !== '') {
            return [
                'status'       => 'needs_attention',
                'current_step' => $saved_step !== '' ? $this->normalize_saved_step($saved_step, $is_opencode_local) : 'smoke_test',
                'message'      => $last_error,
            ];
        }

        if ($preferred_client === '') {
            return [
                'status'       => 'not_connected',
                'current_step' => 'choose_client',
                'message'      => __('Choose the coding agent you want to connect.', 'livecanvas-forge-ai'),
            ];
        }

        if ($is_claude && !in_array($claude_connection_target, ['desktop_app', 'cli'], true)) {
            return [
                'status'       => 'not_connected',
                'current_step' => 'choose_claude_target',
                'message'      => __('Choose whether you want to connect Claude through Desktop App or the Command Line Interface.', 'livecanvas-forge-ai'),
            ];
        }

        if ($connection_mode === '') {
            return [
                'status'       => 'not_connected',
                'current_step' => 'choose_mode',
                'message'      => __('Choose whether the coding agent should target this local site or a remote site.', 'livecanvas-forge-ai'),
            ];
        }

        return [
            'status'       => 'not_connected',
            'current_step' => $saved_step !== '' ? $this->normalize_saved_step($saved_step, $is_opencode_local) : 'confirm_details',
            'message'      => __('Confirm the connection details and generate the client bundle.', 'livecanvas-forge-ai'),
        ];
    }

    public function build_bundle(array $payload): array {
        return $this->bundle_builder->build($payload);
    }

    public function next_step(string $current_step, array $connections = []): string {
        $normalized_step = $this->normalize_step($current_step);
        $step_order = $this->get_step_order($connections);
        $index = array_search($normalized_step, $step_order, true);

        if ($index === false || !isset($step_order[$index + 1])) {
            return $normalized_step !== '' ? $normalized_step : 'choose_client';
        }

        return $step_order[$index + 1];
    }

    private function normalize_step(string $step): string {
        $step = sanitize_key($step);

        return in_array($step, self::STEP_ORDER, true) ? $step : '';
    }

    private function normalize_saved_step(string $step, bool $is_opencode_local): string {
        if ($is_opencode_local && $step === 'choose_mode') {
            return 'confirm_details';
        }

        return $step;
    }

    private function get_step_order(array $connections): array {
        $preferred_client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
        $connection_mode = sanitize_key((string) ($connections['connection_mode'] ?? ''));

        if ($this->is_opencode_local($preferred_client, $connection_mode)) {
            return [
                'choose_client',
                'confirm_details',
                'generate_bundle',
                'smoke_test',
                'ready',
            ];
        }

        if ($this->is_claude($preferred_client)) {
            return self::STEP_ORDER;
        }

        return self::STEP_ORDER;
    }

    private function is_opencode_local(string $preferred_client, string $connection_mode): bool {
        return $preferred_client === 'opencode' && $connection_mode === 'local';
    }

    private function is_claude(string $preferred_client): bool {
        return $preferred_client === 'claude';
    }
}
