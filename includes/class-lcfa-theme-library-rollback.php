<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Rollback {
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

        foreach ((array) ($record['updated_posts'] ?? []) as $post_id => $post_record) {
            $post_id = absint($post_id);
            if ($post_id <= 0 || !is_array($post_record)) {
                continue;
            }

            $restore = wp_update_post([
                'ID'           => $post_id,
                'post_title'   => (string) ($post_record['post_title'] ?? ''),
                'post_name'    => (string) ($post_record['post_name'] ?? ''),
                'post_status'  => (string) ($post_record['post_status'] ?? 'draft'),
                'post_content' => (string) ($post_record['post_content'] ?? ''),
            ], true);

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

        return [
            'ok'      => empty($errors),
            'message' => empty($errors) ? __('Theme Library rollback restored.', 'livecanvas-forge-ai') : __('Theme Library rollback completed with errors.', 'livecanvas-forge-ai'),
            'errors'  => $errors,
            'plan'    => $plan,
        ];
    }
}
