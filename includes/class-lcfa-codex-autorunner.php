<?php

defined('ABSPATH') || exit;

final class LCFA_Codex_Autorunner {
    public static function desktop_readiness(): array {
        return ['available' => false, 'state' => 'needs_action', 'code' => 'desktop_transport_required',
            'same_desktop_conversation_verified' => false,
            'message' => __('MCP is separate from desktop chat. A verified connection to the same Codex conversation is not available yet. Continue in your coding agent.', 'livecanvas-forge-ai')];
    }

    public static function maybe_spawn(array $agent_request): array {
        $request_id = sanitize_key((string) ($agent_request['id'] ?? ''));

        if ($request_id === '') {
            return ['state' => 'skipped', 'reason' => 'missing_request_id'];
        }

        if (($agent_request['agent'] ?? 'codex') !== 'codex') {
            $runner = ['state' => 'waiting_worker', 'reason' => 'agent_claim_required',
                'message' => __('Waiting for the connected coding agent to claim this request. No local process was started.', 'livecanvas-forge-ai'),
                'updated_at' => current_time('mysql', true)];
            LCFA_Settings::update_agent_request_runner($request_id, $runner);
            return $runner;
        }

        // PHP may run on a remote web host. A CLI job there is neither the user's
        // computer nor their desktop conversation. Never launch it as a fallback.
        $runner = [
            'state'      => 'unavailable',
            'reason'     => 'desktop_transport_required',
            'message'    => __('Desktop chat requires a verified local connection to the same Codex conversation. No separate CLI job was started.', 'livecanvas-forge-ai'),
            'updated_at' => current_time('mysql', true),
        ];

        LCFA_Settings::update_agent_request_runner($request_id, $runner);

        return $runner;
    }

    public static function build_launch_plan(array $agent_request, string $codex_binary, string $workspace_root, string $run_dir): array {
        $request_id = sanitize_key((string) ($agent_request['id'] ?? 'request'));
        $request_id = $request_id !== '' ? $request_id : 'request';
        $run_dir = rtrim(wp_normalize_path($run_dir), '/\\');

        if (!is_dir($run_dir)) {
            @mkdir($run_dir, 0775, true);
        }

        $prompt_file = $run_dir . '/codex-prompt-' . $request_id . '.txt';
        $log_file = $run_dir . '/codex-run-' . $request_id . '.log';
        $prompt = self::build_prompt($agent_request);
        file_put_contents($prompt_file, $prompt);

        $codex_options = LCFA_Settings::sanitize_codex_options((array) ($agent_request['codex_options'] ?? []));
        $sandbox_mode = self::resolve_sandbox_mode($codex_options['sandbox']);
        $process_path = self::resolve_process_path();
        $parts = [
            escapeshellarg($codex_binary),
            'exec',
            '--skip-git-repo-check',
        ];

        if ($codex_options['model'] !== '') {
            $parts[] = '--model';
            $parts[] = escapeshellarg($codex_options['model']);
        }

        if ($codex_options['reasoning_effort'] !== '') {
            $parts[] = '-c';
            $parts[] = escapeshellarg('model_reasoning_effort="' . $codex_options['reasoning_effort'] . '"');
        }

        $parts[] = '--sandbox';
        $parts[] = escapeshellarg($sandbox_mode);

        $parts = array_merge($parts, [
            '-c',
            escapeshellarg('shell_environment_policy.inherit=core'),
            escapeshellarg('--cd'),
            escapeshellarg($workspace_root),
            '-',
        ]);

        $command = 'PATH=' . escapeshellarg($process_path) . ' ' . implode(' ', $parts)
            . ' < ' . escapeshellarg($prompt_file)
            . ' >> ' . escapeshellarg($log_file)
            . ' 2>&1 & echo $!';

        return [
            'prompt'      => $prompt,
            'prompt_file' => $prompt_file,
            'log_file'    => $log_file,
            'command'       => $command,
            'codex_options' => [
                'model'            => $codex_options['model'],
                'speed'            => $codex_options['speed'],
                'reasoning_effort' => $codex_options['reasoning_effort'],
                'sandbox'          => $sandbox_mode,
            ],
        ];
    }

    public static function build_prompt(array $agent_request): string {
        $request_id = sanitize_key((string) ($agent_request['id'] ?? ''));
        $agent = sanitize_key((string) ($agent_request['agent'] ?? 'codex'));
        $post_id = absint($agent_request['post_id'] ?? $agent_request['target_id'] ?? 0);
        $action = sanitize_key((string) ($agent_request['action'] ?? 'page_upsert'));
        $codex_options = LCFA_Settings::sanitize_codex_options((array) ($agent_request['codex_options'] ?? []));
        $speed = $codex_options['speed'] !== '' ? $codex_options['speed'] : 'balanced';
        $speed_instruction = self::get_speed_instruction($speed);

        return implode("\n", [
            'You are Codex running as the LiveCanvas AI Bridge frontend worker.',
            '',
            'Process exactly one queued frontend request.',
            '',
            'Required MCP flow:',
            'Use these LiveCanvas MCP tool names exactly when your runtime exposes namespaced MCP calls:',
            '- mcp__livecanvas_forge__get_frontend_prompt_request',
            '- mcp__livecanvas_forge__run_lc_command',
            '- mcp__livecanvas_forge__complete_frontend_prompt_request',
            '- mcp__livecanvas_forge__fail_frontend_prompt_request',
            '',
            '1. Before any analysis, call mcp__livecanvas_forge__get_frontend_prompt_request with {"agent":"' . $agent . '","request_id":"' . $request_id . '"}. If your runtime exposes un-namespaced MCP tools, call get_frontend_prompt_request with the same payload.',
            '2. If no request is returned, call fail_frontend_prompt_request for request_id "' . $request_id . '" with a clear reason and stop.',
            '3. Call list_workflows and read_workflow for the relevant task, then get_write_context for the exact target. Inspect the effective renderer, site identity, child-theme roots, framework and shared impact.',
            '4. Preview and apply through run_lc_command with the unchanged write_context. Use action "' . $action . '", target_id/post_id ' . (string) $post_id . '. Apply only the requested scope. Automatic apply requires a verified snapshot and conflict-aware Undo. Refresh context after each mutation.',
            '5. Call complete_frontend_prompt_request with request_id "' . $request_id . '" and the exact run_lc_command result.',
            '6. If you cannot safely apply the request, call fail_frontend_prompt_request with request_id "' . $request_id . '".',
            '',
            'LiveCanvas output rules:',
            'Never wrap generated LiveCanvas page content in <main>, <html>, <head>, or <body>; LiveCanvas already owns the page shell.',
            'Return content fragments. Keep CSS and scripts out of editorial content; use supported managed assets or the verified child-theme pipeline.',
            'Picostrap uses Bootstrap/Sass. Picowind uses Tailwind/WindPress. Use DaisyUI or Typography only with current successful compile evidence.',
            'Report saved, compiled, visually_verified and published separately. New pages remain drafts unless publication is authorized.',
            '',
            'Operational constraints:',
            'Read only the relevant workflow and target context. Do not inspect unrelated personal chats or projects.',
            'Use MCP tools for WordPress and LiveCanvas writes. Do not edit plugin files for this frontend request.',
            'Speed profile: ' . $speed . '. ' . $speed_instruction,
            'Keep the final Codex message short; the drawer reads completion from complete_frontend_prompt_request.',
        ]);
    }

    private static function get_speed_instruction(string $speed): string {
        if ($speed === 'fast') {
            return 'Favor the smallest safe context read, avoid broad exploration, and apply a scoped change as soon as the target markup is clear.';
        }

        if ($speed === 'thorough') {
            return 'Inspect enough surrounding context to preserve layout contracts, run validation before applying, and spend extra effort on edge cases.';
        }

        return 'Use a balanced workflow: inspect the target context, validate generated markup, and avoid unnecessary full-site exploration.';
    }

    public static function is_stale_queued_request(array $agent_request, int $now = 0, int $timeout = 180): bool {
        if (sanitize_key((string) ($agent_request['status'] ?? '')) !== 'queued') {
            return false;
        }

        if (sanitize_text_field((string) ($agent_request['claimed_at'] ?? '')) !== '') {
            return false;
        }

        $runner = is_array($agent_request['runner'] ?? null) ? $agent_request['runner'] : [];
        if (!in_array((string) ($runner['state'] ?? ''), ['started', 'running'], true)) {
            return false;
        }

        $updated_at = strtotime((string) ($runner['updated_at'] ?? $agent_request['updated_at'] ?? ''));
        if (!$updated_at) {
            return false;
        }

        $now = $now > 0 ? $now : time();
        $timeout = max(30, $timeout);

        return ($now - $updated_at) >= $timeout;
    }

    public static function get_stale_timeout(): int {
        $timeout = absint(getenv('LCFA_CODEX_AUTORUN_TIMEOUT') ?: 180);
        $timeout = $timeout > 0 ? $timeout : 180;

        if (function_exists('apply_filters')) {
            $timeout = absint(apply_filters('lcfa_codex_autorun_stale_timeout', $timeout));
        }

        return max(30, $timeout);
    }

    private static function resolve_sandbox_mode(string $preferred_sandbox = ''): string {
        $allowed = ['read-only', 'workspace-write'];
        $preferred_sandbox = sanitize_key($preferred_sandbox);

        if (in_array($preferred_sandbox, $allowed, true)) {
            return $preferred_sandbox;
        }

        $env_sandbox = sanitize_key((string) getenv('LCFA_CODEX_SANDBOX'));

        if (in_array($env_sandbox, $allowed, true)) {
            return $env_sandbox;
        }

        return 'read-only';
    }

    private static function resolve_process_path(): string {
        $paths = [
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/bin',
            '/usr/sbin',
            '/sbin',
        ];

        $current_path = (string) getenv('PATH');
        if ($current_path !== '') {
            $paths = array_merge($paths, explode(PATH_SEPARATOR, $current_path));
        }

        $paths = array_values(array_unique(array_filter(array_map('trim', $paths))));

        return implode(PATH_SEPARATOR, $paths);
    }

}
