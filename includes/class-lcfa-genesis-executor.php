<?php

defined('ABSPATH') || exit;

final class LCFA_Genesis_Executor {
    private LCFA_Environment $environment;
    private LCFA_Command_Deck $command_deck;

    public function __construct(LCFA_Environment $environment, LCFA_Command_Deck $command_deck) {
        $this->environment  = $environment;
        $this->command_deck = $command_deck;
    }

    public function get_execution_plan(): array {
        $brief_hash     = LCFA_Settings::get_project_brief_hash();
        $plan           = LCFA_Settings::get_genesis_plan();
        $progress       = LCFA_Settings::get_genesis_progress();
        $snapshot       = $this->environment->get_snapshot();
        $plan_stack     = is_array($plan['stack'] ?? null) ? $plan['stack'] : [];
        $plan_tasks     = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];
        $progress_tasks = is_array($progress['tasks'] ?? null) ? $progress['tasks'] : [];
        $available      = !empty($plan_tasks);
        $stale          = $available && (
            (string) ($plan['brief_hash'] ?? '') !== $brief_hash
            || (string) ($plan_stack['framework'] ?? '') !== (string) ($snapshot['detected_framework'] ?? '')
            || (string) ($plan_stack['theme'] ?? '') !== (string) ($snapshot['current_theme_stylesheet'] ?? '')
            || (string) ($plan_stack['site_mode'] ?? '') !== (string) ($snapshot['site_mode'] ?? '')
        );

        $tasks         = [];
        $next_task     = null;
        $counts        = [
            'pending'   => 0,
            'previewed' => 0,
            'applied'   => 0,
            'failed'    => 0,
            'total'     => count($plan_tasks),
        ];

        foreach ($plan_tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = sanitize_key((string) ($task['id'] ?? ''));

            if ($task_id === '') {
                continue;
            }

            $progress_item = is_array($progress_tasks[$task_id] ?? null) ? $progress_tasks[$task_id] : [];
            $status        = in_array($progress_item['status'] ?? '', ['pending', 'previewed', 'applied', 'failed'], true)
                ? (string) $progress_item['status']
                : 'pending';

            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }

            $counts[$status]++;

            $enriched_task = $task;
            $enriched_task['status'] = $status;
            $enriched_task['progress'] = $progress_item;
            $enriched_task['requires_action'] = !empty($task['payload']['action']);
            $enriched_task['completed'] = $status === 'applied';

            $tasks[] = $enriched_task;

            if ($next_task === null && $status !== 'applied') {
                $next_task = $enriched_task;
            }
        }

        return [
            'brief_hash'   => $brief_hash,
            'available'    => $available,
            'stale'        => $stale,
            'plan'         => $plan,
            'progress'     => $progress,
            'tasks'        => $tasks,
            'counts'       => $counts,
            'next_task'    => $next_task,
            'next_task_id' => is_array($next_task) ? sanitize_key((string) ($next_task['id'] ?? '')) : '',
        ];
    }

    public function execute_next(array $options = []): array {
        $execution_plan = $this->get_execution_plan();

        if (empty($execution_plan['available'])) {
            return [
                'ok'      => false,
                'message' => __('Generate a Genesis build plan before executing tasks.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
            ];
        }

        if (!empty($execution_plan['stale'])) {
            return [
                'ok'      => false,
                'message' => __('The Genesis build plan is outdated. Regenerate it before executing the next task.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
            ];
        }

        $next_task_id = sanitize_key((string) ($execution_plan['next_task_id'] ?? ''));

        if ($next_task_id === '') {
            return [
                'ok'      => true,
                'message' => __('All Genesis tasks are already completed.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
                'result'  => [
                    'ok'      => true,
                    'action'  => '',
                    'mode'    => 'noop',
                    'message' => __('No next Genesis task is pending.', 'livecanvas-forge-ai'),
                ],
            ];
        }

        return $this->execute_task($next_task_id, $options);
    }

    public function execute_task(string $task_id, array $options = []): array {
        $normalized_task_id = sanitize_key($task_id);
        $execution_plan     = $this->get_execution_plan();

        if (empty($execution_plan['available'])) {
            return [
                'ok'      => false,
                'message' => __('Generate a Genesis build plan before executing tasks.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
            ];
        }

        if (!empty($execution_plan['stale'])) {
            return [
                'ok'      => false,
                'message' => __('The Genesis build plan is outdated. Regenerate it before executing tasks.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
            ];
        }

        $task = $this->find_task($execution_plan['tasks'], $normalized_task_id);

        if (!$task) {
            return [
                'ok'      => false,
                'message' => __('The requested Genesis task was not found in the current build plan.', 'livecanvas-forge-ai'),
                'execution_plan' => $execution_plan,
            ];
        }

        $request_context = is_array($options['request_context'] ?? null) ? $options['request_context'] : [];
        $payload         = is_array($task['payload'] ?? null) ? $task['payload'] : [];
        $overrides       = is_array($options['overrides'] ?? null) ? $options['overrides'] : [];
        $payload         = array_merge($payload, $overrides);
        $payload['genesis_task_id'] = $normalized_task_id;

        if (!isset($payload['execution_target']) && !empty($options['execution_target'])) {
            $payload['execution_target'] = sanitize_key((string) $options['execution_target']);
        }

        if (!array_key_exists('dry_run', $payload) && array_key_exists('dry_run', $options)) {
            $payload['dry_run'] = !empty($options['dry_run']);
        }

        $result = [];

        if (empty($payload['action'])) {
            $result = $this->build_advisory_result($task);
        } else {
            $result = $this->command_deck->execute($payload);
        }

        $status = !$result['ok']
            ? 'failed'
            : (($result['mode'] ?? '') === 'preview' ? 'previewed' : 'applied');

        LCFA_Settings::update_genesis_task_progress($normalized_task_id, [
            'status'       => $status,
            'thread_id'    => LCFA_Settings::normalize_thread_id((string) ($request_context['thread_id'] ?? $options['thread_id'] ?? 'default')),
            'action'       => sanitize_key((string) ($result['action'] ?? ($payload['action'] ?? ''))),
            'mode'         => sanitize_key((string) ($result['mode'] ?? '')),
            'ok'           => !empty($result['ok']),
            'message'      => sanitize_textarea_field((string) ($result['message'] ?? '')),
            'target_type'  => sanitize_key((string) ($result['target_type'] ?? '')),
            'target_id'    => absint($result['target_id'] ?? 0),
            'target_title' => sanitize_text_field((string) ($result['target_title'] ?? '')),
        ], (string) ($execution_plan['brief_hash'] ?? ''));

        return [
            'ok'             => !empty($result['ok']),
            'task_id'        => $normalized_task_id,
            'task'           => $this->find_task($this->get_execution_plan()['tasks'], $normalized_task_id),
            'result'         => $result,
            'execution_plan' => $this->get_execution_plan(),
            'message'        => (string) ($result['message'] ?? ''),
        ];
    }

    private function find_task(array $tasks, string $task_id): ?array {
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (sanitize_key((string) ($task['id'] ?? '')) === $task_id) {
                return $task;
            }
        }

        return null;
    }

    private function build_advisory_result(array $task): array {
        $summary = sanitize_text_field((string) ($task['label'] ?? __('Advisory task', 'livecanvas-forge-ai')));
        $message = sanitize_textarea_field((string) ($task['user_prompt'] ?? $task['description'] ?? __('Review this advisory step manually before continuing.', 'livecanvas-forge-ai')));

        return [
            'ok'               => true,
            'action'           => '',
            'mode'             => 'apply',
            'execution_target' => 'local',
            'message'          => $message,
            'summary'          => $summary,
            'target_type'      => 'genesis_advisory',
            'target_id'        => 0,
            'target_title'     => $summary,
            'frontend_url'     => '',
            'edit_url'         => '',
            'diff_html'        => '',
            'existing_html'    => '',
            'proposed_html'    => '',
            'inventory'        => null,
            'warnings'         => [],
            'data'             => [
                'advisory' => true,
            ],
        ];
    }
}
