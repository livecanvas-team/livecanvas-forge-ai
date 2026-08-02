<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Rollback {
    private const IMPORTS_OPTION = 'lcfa_theme_library_imports';

    private ?LCFA_WindPress_Bridge $windpress_bridge;

    public function __construct(?LCFA_WindPress_Bridge $windpress_bridge = null) {
        $this->windpress_bridge = $windpress_bridge;
    }

    public function rollback(string $audit_id, bool $dry_run = false): array {
        $audit_id = sanitize_key($audit_id);
        if ($audit_id === '') {
            return [
                'ok'      => false,
                'message' => __('An import audit ID is required.', 'livecanvas-forge-ai'),
            ];
        }

        $record = LCFA_Settings::get_rollback_record($audit_id);
        if (!$record || (string) ($record['type'] ?? '') !== 'theme_library_import') {
            return [
                'ok'      => false,
                'message' => __('No Theme Library rollback record was found for this audit ID.', 'livecanvas-forge-ai'),
            ];
        }

        $plan = [
            'theme'          => (string) ($record['previous_theme'] ?? ''),
            'options'        => array_keys((array) ($record['updated_options'] ?? [])),
            'updated_posts'  => array_map('intval', array_keys((array) ($record['updated_posts'] ?? []))),
            'created_posts'  => array_map('intval', (array) ($record['created_posts'] ?? [])),
            'created_media'  => array_map('intval', (array) ($record['created_media'] ?? [])),
            'created_menus'  => array_map('intval', (array) ($record['created_menus'] ?? [])),
            'windpress_runtime' => [
                'available' => !empty($record['windpress_runtime']['available']),
                'files'     => array_keys((array) ($record['windpress_runtime']['files'] ?? [])),
                'options'   => !empty($record['windpress_runtime']['available']),
            ],
        ];

        if ($dry_run) {
            return [
                'ok'      => true,
                'message' => __('Theme Library rollback preview prepared.', 'livecanvas-forge-ai'),
                'plan'    => $plan,
            ];
        }

        $errors = [];

        $previous_theme = sanitize_key((string) ($record['previous_theme'] ?? ''));
        if ($previous_theme !== '' && wp_get_theme($previous_theme)->exists()) {
            switch_theme($previous_theme);
        }

        foreach ((array) ($record['updated_options'] ?? []) as $option_name => $option) {
            $option_name = sanitize_key((string) $option_name);
            if ($option_name === '') {
                continue;
            }

            if (!empty($option['exists'])) {
                update_option($option_name, $option['value'] ?? null, false);
            } else {
                delete_option($option_name);
            }
        }

        $windpress_runtime = is_array($record['windpress_runtime'] ?? null) ? $record['windpress_runtime'] : [];
        if (!empty($windpress_runtime['available'])) {
            if (!$this->windpress_bridge) {
                $errors[] = __('The WindPress bridge is unavailable, so its runtime state could not be restored.', 'livecanvas-forge-ai');
            } else {
                $windpress_restore = $this->windpress_bridge->restore_runtime_state($windpress_runtime);
                if (empty($windpress_restore['ok'])) {
                    $restore_errors = array_filter(array_map('strval', (array) ($windpress_restore['errors'] ?? [])));
                    $errors = array_merge($errors, $restore_errors ?: [
                        (string) ($windpress_restore['message'] ?? __('WindPress runtime state could not be restored.', 'livecanvas-forge-ai')),
                    ]);
                }
            }
        }

        foreach ((array) ($record['updated_posts'] ?? []) as $post_id => $post_record) {
            $post_id = absint($post_id);
            if ($post_id <= 0 || !is_array($post_record)) {
                continue;
            }

            $restore = $this->with_unfiltered_post_content(static function () use ($post_id, $post_record) {
                return wp_update_post([
                    'ID'           => $post_id,
                    'post_title'   => (string) ($post_record['post_title'] ?? ''),
                    'post_name'    => (string) ($post_record['post_name'] ?? ''),
                    'post_status'  => (string) ($post_record['post_status'] ?? 'draft'),
                    'post_content' => (string) ($post_record['post_content'] ?? ''),
                ], true);
            });

            if (is_wp_error($restore)) {
                $errors[] = $restore->get_error_message();
                continue;
            }

            foreach ((array) ($post_record['meta'] ?? []) as $meta_key => $meta_value) {
                $meta_key = sanitize_key((string) $meta_key);
                if ($meta_key === '') {
                    continue;
                }

                if ($meta_value === '' || $meta_value === null) {
                    delete_post_meta($post_id, $meta_key);
                } else {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }

            foreach ((array) ($post_record['taxonomies'] ?? []) as $taxonomy => $terms) {
                $taxonomy = sanitize_key((string) $taxonomy);
                if ($taxonomy === '' || !function_exists('taxonomy_exists') || !taxonomy_exists($taxonomy) || !function_exists('wp_set_object_terms')) {
                    continue;
                }

                $restored_terms = wp_set_object_terms($post_id, array_values((array) $terms), $taxonomy, false);
                if (is_wp_error($restored_terms)) {
                    $errors[] = $restored_terms->get_error_message();
                }
            }
        }

        foreach ((array) ($record['created_posts'] ?? []) as $post_id) {
            $post_id = absint($post_id);
            if ($post_id > 0 && get_post($post_id)) {
                wp_trash_post($post_id);
            }
        }

        foreach ((array) ($record['created_media'] ?? []) as $attachment_id) {
            $attachment_id = absint($attachment_id);
            if ($attachment_id > 0) {
                wp_delete_attachment($attachment_id, true);
            }
        }

        foreach ((array) ($record['created_menus'] ?? []) as $menu_id) {
            $menu_id = absint($menu_id);
            if ($menu_id > 0) {
                wp_delete_nav_menu($menu_id);
            }
        }

        $mods = is_array($record['previous_theme_mods'] ?? null) ? $record['previous_theme_mods'] : [];
        if (array_key_exists('nav_menu_locations', $mods)) {
            set_theme_mod('nav_menu_locations', $mods['nav_menu_locations']);
        }

        LCFA_Settings::mark_rollback_record_restored($audit_id, [
            'ok'      => empty($errors),
            'message' => empty($errors) ? __('Theme Library rollback restored.', 'livecanvas-forge-ai') : implode(' ', $errors),
        ]);

        $this->update_import_state($audit_id, empty($errors), $errors);

        return [
            'ok'      => empty($errors),
            'message' => empty($errors) ? __('Theme Library rollback restored.', 'livecanvas-forge-ai') : __('Theme Library rollback completed with errors.', 'livecanvas-forge-ai'),
            'errors'  => $errors,
            'plan'    => $plan,
        ];
    }

    private function update_import_state(string $audit_id, bool $restored, array $errors): void {
        $imports = get_option(self::IMPORTS_OPTION, []);
        if (!is_array($imports)) {
            return;
        }

        foreach ($imports as $slug => $import) {
            if (!is_array($import) || (string) ($import['audit_id'] ?? '') !== $audit_id) {
                continue;
            }

            $import['status'] = $restored ? 'rolled_back' : 'rollback_failed';
            $import['rolled_back_at'] = current_time('mysql', true);
            $import['rollback_errors'] = array_values(array_filter(array_map('strval', $errors)));
            $imports[$slug] = $import;
            update_option(self::IMPORTS_OPTION, $imports, false);
            break;
        }
    }

    private function with_unfiltered_post_content(callable $operation) {
        if ((function_exists('current_user_can') && current_user_can('unfiltered_html')) || !function_exists('remove_filter') || !function_exists('add_filter')) {
            return $operation();
        }

        $removed = [];
        foreach ([
            ['content_save_pre', 'wp_filter_post_kses', 10, 1],
            ['content_filtered_save_pre', 'wp_filter_post_kses', 10, 1],
        ] as $filter) {
            [$hook, $callback, $priority, $accepted_args] = $filter;
            if (remove_filter($hook, $callback, $priority)) {
                $removed[] = [$hook, $callback, $priority, $accepted_args];
            }
        }

        try {
            return $operation();
        } finally {
            foreach ($removed as [$hook, $callback, $priority, $accepted_args]) {
                add_filter($hook, $callback, $priority, $accepted_args);
            }
        }
    }
}
