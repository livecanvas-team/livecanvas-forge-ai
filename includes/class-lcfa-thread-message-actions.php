<?php

defined('ABSPATH') || exit;

final class LCFA_Thread_Message_Actions {
    public static function decorate_message(array $message, array $context = []): array {
        $message['actions'] = self::sanitize_actions((array) ($message['actions'] ?? []));

        if (($message['role'] ?? '') !== 'tool_result') {
            return $message;
        }

        $derived_actions = self::build_actions((array) ($message['meta'] ?? []), $context);

        if ($derived_actions) {
            $message['actions'] = self::merge_actions($message['actions'], $derived_actions);
        }

        return $message;
    }

    public static function build_actions(array $meta, array $context = []): array {
        $actions = [];
        $seen_urls = [];
        $order = 0;
        $target_type = sanitize_key((string) ($meta['target_type'] ?? ''));
        $frontend_url = self::sanitize_url((string) ($meta['frontend_url'] ?? ''));
        $edit_url = self::sanitize_url((string) ($meta['edit_url'] ?? ''));
        $file_path = sanitize_text_field((string) ($meta['file_path'] ?? ''));
        $root_scope = sanitize_key((string) ($meta['root_scope'] ?? ''));
        $backup_id = sanitize_text_field((string) ($meta['backup_id'] ?? ''));

        $push_action = static function (int $priority, string $label, string $url) use (&$actions, &$seen_urls, &$order): void {
            if ($url === '' || isset($seen_urls[$url])) {
                return;
            }

            $seen_urls[$url] = true;
            $actions[] = [
                'priority' => $priority,
                'order'    => $order++,
                'label'    => $label,
                'url'      => $url,
            ];
        };

        if ($frontend_url !== '') {
            $push_action(100, __('View page', 'livecanvas-forge-ai'), $frontend_url);
        }

        if ($edit_url !== '') {
            $push_action(
                90,
                $target_type === 'dynamic_template'
                    ? __('Open template', 'livecanvas-forge-ai')
                    : __('Edit page', 'livecanvas-forge-ai'),
                $edit_url
            );
        }

        if ($file_path !== '' && in_array($target_type, ['theme_file', 'theme_template'], true)) {
            $push_action(
                60,
                $target_type === 'theme_template'
                    ? __('Open theme template', 'livecanvas-forge-ai')
                    : __('Open theme file', 'livecanvas-forge-ai'),
                self::build_command_url([
                    'suggest_action' => $target_type === 'theme_template' ? 'write_theme_template' : 'write_theme_file',
                    'file_path'      => $file_path,
                    'root_scope'     => $root_scope !== '' ? $root_scope : 'stylesheet',
                ], $context)
            );
        }

        if ($backup_id !== '') {
            $backup_args = [
                'suggest_action' => 'restore_theme_backup',
                'backup_id'      => $backup_id,
            ];

            if ($file_path !== '') {
                $backup_args['file_path'] = $file_path;
            }

            if ($root_scope !== '') {
                $backup_args['root_scope'] = $root_scope;
            }

            $push_action(40, __('Open backup', 'livecanvas-forge-ai'), self::build_command_url($backup_args, $context));
        }

        usort($actions, static function (array $left, array $right): int {
            if ($left['priority'] === $right['priority']) {
                return $left['order'] <=> $right['order'];
            }

            return $right['priority'] <=> $left['priority'];
        });

        return array_map(static function (array $action): array {
            return [
                'label' => (string) $action['label'],
                'url'   => (string) $action['url'],
            ];
        }, array_slice($actions, 0, 2));
    }

    public static function build_suggestion_actions(array $suggested_payload, array $request_payload = [], array $context = []): array {
        if (empty($suggested_payload['action'])) {
            return [];
        }

        $preview_payload = $suggested_payload;
        $preview_payload['dry_run'] = true;
        $apply_payload = $suggested_payload;

        if (array_key_exists('dry_run', $apply_payload)) {
            unset($apply_payload['dry_run']);
        }

        $thread_id = sanitize_key((string) ($context['thread_id'] ?? 'default'));
        $command_args = [
            'suggest_action'   => sanitize_key((string) ($suggested_payload['action'] ?? '')),
            'execution_target' => sanitize_key((string) ($suggested_payload['execution_target'] ?? '')),
            'target_id'        => absint($suggested_payload['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($suggested_payload['variant'] ?? '')),
            'title'            => sanitize_text_field((string) ($suggested_payload['title'] ?? '')),
            'slug'             => sanitize_text_field((string) ($suggested_payload['slug'] ?? '')),
            'provider_id'      => sanitize_text_field((string) ($suggested_payload['provider_id'] ?? '')),
            'relative_path'    => sanitize_text_field((string) ($suggested_payload['relative_path'] ?? '')),
            'root_scope'       => sanitize_key((string) ($suggested_payload['root_scope'] ?? '')),
            'file_path'        => sanitize_text_field((string) ($suggested_payload['file_path'] ?? '')),
            'backup_id'        => sanitize_text_field((string) ($suggested_payload['backup_id'] ?? '')),
            'status'           => sanitize_text_field((string) ($suggested_payload['status'] ?? '')),
        ];

        if ($thread_id !== '') {
            $command_args['thread_id'] = $thread_id;
        }

        $request_post_id = absint($request_payload['post_id'] ?? ($request_payload['context_post_id'] ?? 0));
        if ($request_post_id > 0) {
            $command_args['post_id'] = $request_post_id;
        }

        $genesis_task_id = sanitize_key((string) ($request_payload['genesis_task_id'] ?? ''));
        if ($genesis_task_id !== '') {
            $command_args['genesis_task_id'] = $genesis_task_id;
        }

        $user_prompt = sanitize_textarea_field((string) ($request_payload['user_prompt'] ?? ''));
        if ($user_prompt !== '') {
            $command_args['user_prompt'] = $user_prompt;
        }

        $command_args = array_filter($command_args, static function ($value) {
            return $value !== '' && $value !== 0;
        });

        return self::sanitize_actions([
            [
                'kind'    => 'apply',
                'label'   => __('Preview inline', 'livecanvas-forge-ai'),
                'payload' => $preview_payload,
            ],
            [
                'kind'    => 'apply',
                'label'   => __('Apply inline', 'livecanvas-forge-ai'),
                'payload' => $apply_payload,
            ],
            [
                'kind'  => 'url',
                'label' => __('Open suggested payload in Command Deck', 'livecanvas-forge-ai'),
                'url'   => self::build_command_url($command_args, $context),
            ],
        ]);
    }

    public static function build_result_actions(array $result, array $payload = [], array $context = []): array {
        $actions = [];
        $action = sanitize_key((string) ($payload['action'] ?? $result['action'] ?? ''));
        $result_ok = !empty($result['ok']);

        if (!$result_ok && $action !== '') {
            $preview_payload = $payload;
            $preview_payload['action'] = $action;
            $preview_payload['dry_run'] = true;

            $retry_payload = $payload;
            $retry_payload['action'] = $action;
            if (array_key_exists('dry_run', $retry_payload)) {
                unset($retry_payload['dry_run']);
            }

            $actions[] = [
                'kind'    => 'apply',
                'label'   => __('Preview inline', 'livecanvas-forge-ai'),
                'payload' => $preview_payload,
            ];
            $actions[] = [
                'kind'    => 'apply',
                'label'   => __('Retry inline', 'livecanvas-forge-ai'),
                'payload' => $retry_payload,
            ];
            $actions[] = [
                'kind'  => 'url',
                'label' => __('Open failed payload in Command Deck', 'livecanvas-forge-ai'),
                'url'   => self::build_command_url(self::build_command_args_from_payload($retry_payload, $context), $context),
            ];
        }

        $target_type = (string) ($result['target_type'] ?? '');
        $file_path = trim((string) ($payload['file_path'] ?? ''));
        $root_scope = sanitize_key((string) ($payload['root_scope'] ?? ''));
        $backup_id = trim((string) ($result['data']['restored_from_backup']['backup_id'] ?? ($payload['backup_id'] ?? '')));

        if ($file_path === '' && in_array($target_type, ['theme_file', 'theme_template'], true)) {
            $file_path = trim((string) ($result['target_title'] ?? ''));
        }

        return self::merge_actions(
            $actions,
            self::build_actions([
                'target_type'  => $target_type,
                'frontend_url' => (string) ($result['frontend_url'] ?? ''),
                'edit_url'     => (string) ($result['edit_url'] ?? ''),
                'file_path'    => $file_path,
                'root_scope'   => $root_scope,
                'backup_id'    => $backup_id,
            ], $context)
        );
    }

    public static function sanitize_actions(array $actions): array {
        $sanitized = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $kind = sanitize_key((string) ($action['kind'] ?? 'url'));
            $label = sanitize_text_field((string) ($action['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            if ($kind === 'apply') {
                $payload = self::sanitize_action_payload($action['payload'] ?? []);

                if (!is_array($payload) || empty($payload['action'])) {
                    continue;
                }

                $sanitized[] = [
                    'kind'    => 'apply',
                    'label'   => $label,
                    'payload' => $payload,
                ];
                continue;
            }

            $url = self::sanitize_url((string) ($action['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $sanitized[] = [
                'kind'  => 'url',
                'label' => $label,
                'url'   => $url,
            ];
        }

        return array_slice($sanitized, 0, 3);
    }

    public static function get_url_actions(array $actions): array {
        $url_actions = [];

        foreach (self::sanitize_actions($actions) as $action) {
            if (($action['kind'] ?? 'url') !== 'url' || empty($action['url'])) {
                continue;
            }

            $url_actions[] = [
                'label' => (string) $action['label'],
                'url'   => (string) $action['url'],
            ];
        }

        return $url_actions;
    }

    private static function merge_actions(array ...$action_sets): array {
        $merged = [];
        $seen = [];

        foreach ($action_sets as $action_set) {
            foreach (self::sanitize_actions($action_set) as $action) {
                $signature = self::action_signature($action);

                if (isset($seen[$signature])) {
                    continue;
                }

                $seen[$signature] = true;
                $merged[] = $action;
            }
        }

        return array_slice($merged, 0, 3);
    }

    private static function build_command_url(array $args, array $context = []): string {
        if (isset($context['command_url_builder']) && is_callable($context['command_url_builder'])) {
            return (string) call_user_func($context['command_url_builder'], $args);
        }

        $defaults = [
            'page' => 'lcfa-dashboard',
            'tab'  => 'command',
        ];
        $thread_id = sanitize_key((string) ($context['thread_id'] ?? ''));

        if ($thread_id !== '' && !array_key_exists('thread_id', $args)) {
            $defaults['thread_id'] = $thread_id;
        }

        $base_url = function_exists('admin_url') ? admin_url('admin.php') : 'admin.php';
        $query_args = array_merge($defaults, $args);

        if (function_exists('add_query_arg')) {
            return add_query_arg($query_args, $base_url);
        }

        return $base_url . '?' . http_build_query($query_args);
    }

    private static function build_command_args_from_payload(array $payload, array $context = []): array {
        $thread_id = sanitize_key((string) ($context['thread_id'] ?? ''));
        $command_args = [
            'suggest_action'   => sanitize_key((string) ($payload['action'] ?? '')),
            'execution_target' => sanitize_key((string) ($payload['execution_target'] ?? '')),
            'target_id'        => absint($payload['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($payload['variant'] ?? '')),
            'title'            => sanitize_text_field((string) ($payload['title'] ?? '')),
            'slug'             => sanitize_text_field((string) ($payload['slug'] ?? '')),
            'provider_id'      => sanitize_text_field((string) ($payload['provider_id'] ?? '')),
            'relative_path'    => sanitize_text_field((string) ($payload['relative_path'] ?? '')),
            'root_scope'       => sanitize_key((string) ($payload['root_scope'] ?? '')),
            'file_path'        => sanitize_text_field((string) ($payload['file_path'] ?? '')),
            'backup_id'        => sanitize_text_field((string) ($payload['backup_id'] ?? '')),
            'status'           => sanitize_text_field((string) ($payload['status'] ?? '')),
        ];

        if ($thread_id !== '') {
            $command_args['thread_id'] = $thread_id;
        }

        $request_post_id = absint($payload['post_id'] ?? ($payload['context_post_id'] ?? 0));
        if ($request_post_id > 0) {
            $command_args['post_id'] = $request_post_id;
        }

        $genesis_task_id = sanitize_key((string) ($payload['genesis_task_id'] ?? ''));
        if ($genesis_task_id !== '') {
            $command_args['genesis_task_id'] = $genesis_task_id;
        }

        $user_prompt = sanitize_textarea_field((string) ($payload['user_prompt'] ?? ''));
        if ($user_prompt !== '') {
            $command_args['user_prompt'] = $user_prompt;
        }

        return array_filter($command_args, static function ($value) {
            return $value !== '' && $value !== 0;
        });
    }

    private static function action_signature(array $action): string {
        if (($action['kind'] ?? 'url') === 'apply') {
            return 'apply:' . md5((string) ($action['label'] ?? '') . '|' . wp_json_encode($action['payload'] ?? []));
        }

        return 'url:' . (string) ($action['url'] ?? '');
    }

    private static function sanitize_url(string $url): string {
        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        return trim($url);
    }

    private static function sanitize_action_payload($value) {
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : $key;

                if ($sanitized_key === '') {
                    continue;
                }

                $sanitized[$sanitized_key] = self::sanitize_action_payload($item);
            }

            return $sanitized;
        }

        return sanitize_text_field((string) $value);
    }
}
