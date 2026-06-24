<?php

defined('ABSPATH') || exit;

final class LCFA_Polylang_SEO_Tools {
    public function polylang(array $payload): array {
        if (!$this->polylang_available()) {
            return $this->unavailable('polylang', __('Polylang is not active on this site.', 'livecanvas-forge-ai'));
        }

        $action = sanitize_key((string) ($payload['action'] ?? 'list_languages'));
        if ($action === 'list_languages') {
            return [
                'ok' => true,
                'available' => true,
                'languages' => $this->get_languages(),
            ];
        }

        $post_id = absint($payload['post_id'] ?? 0);
        if ($post_id <= 0) {
            return $this->error(__('A post_id is required for Polylang tools.', 'livecanvas-forge-ai'));
        }

        if ($action === 'get_translations') {
            return [
                'ok' => true,
                'available' => true,
                'post_id' => $post_id,
                'language' => function_exists('pll_get_post_language') ? (string) pll_get_post_language($post_id) : '',
                'translations' => function_exists('pll_get_post_translations') ? (array) pll_get_post_translations($post_id) : [],
            ];
        }

        if ($action === 'set_translations') {
            $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
            $normalized = [];
            foreach ($translations as $language => $translation_id) {
                $language = sanitize_key((string) $language);
                $translation_id = absint($translation_id);
                if ($language !== '' && $translation_id > 0) {
                    $normalized[$language] = $translation_id;
                }
            }
            if ($normalized === []) {
                return $this->error(__('At least one translation mapping is required.', 'livecanvas-forge-ai'));
            }
            pll_save_post_translations($normalized);

            return [
                'ok' => true,
                'available' => true,
                'translations' => $normalized,
                'message' => __('Polylang translations saved.', 'livecanvas-forge-ai'),
            ];
        }

        if ($action === 'create_translation') {
            $language = sanitize_key((string) ($payload['language'] ?? ''));
            if ($language === '') {
                return $this->error(__('A target language is required to create a translation.', 'livecanvas-forge-ai'));
            }
            $source = get_post($post_id);
            if (!$source instanceof WP_Post) {
                return $this->error(__('The source post was not found.', 'livecanvas-forge-ai'));
            }
            $translation_id = wp_insert_post([
                'post_type' => $source->post_type,
                'post_status' => sanitize_key((string) ($payload['status'] ?? 'draft')),
                'post_title' => sanitize_text_field((string) ($payload['title'] ?? $source->post_title)),
                'post_name' => sanitize_title((string) ($payload['slug'] ?? '')),
                'post_content' => (string) ($payload['content'] ?? $source->post_content),
                'post_excerpt' => (string) ($payload['excerpt'] ?? $source->post_excerpt),
                'post_parent' => (int) $source->post_parent,
            ], true);
            if (is_wp_error($translation_id)) {
                return $this->error($translation_id->get_error_message());
            }

            pll_set_post_language((int) $translation_id, $language);
            $translations = function_exists('pll_get_post_translations') ? (array) pll_get_post_translations($post_id) : [];
            $source_language = function_exists('pll_get_post_language') ? (string) pll_get_post_language($post_id) : '';
            if ($source_language !== '') {
                $translations[$source_language] = $post_id;
            }
            $translations[$language] = (int) $translation_id;
            pll_save_post_translations($translations);

            return [
                'ok' => true,
                'available' => true,
                'post_id' => (int) $translation_id,
                'translations' => $translations,
                'edit_url' => function_exists('get_edit_post_link') ? (string) get_edit_post_link((int) $translation_id, 'raw') : '',
                'message' => __('Polylang translation draft created.', 'livecanvas-forge-ai'),
            ];
        }

        return $this->error(__('Unsupported Polylang action.', 'livecanvas-forge-ai'));
    }

    public function seo(array $payload): array {
        if (!$this->seopress_available()) {
            return $this->unavailable('seopress', __('SEOPress is not active on this site.', 'livecanvas-forge-ai'));
        }

        $action = sanitize_key((string) ($payload['action'] ?? 'get'));
        $post_id = absint($payload['post_id'] ?? 0);
        if ($post_id <= 0) {
            return $this->error(__('A post_id is required for SEO tools.', 'livecanvas-forge-ai'));
        }

        $keys = [
            'title' => '_seopress_titles_title',
            'description' => '_seopress_titles_desc',
            'canonical' => '_seopress_robots_canonical',
            'social_image' => '_seopress_social_fb_img',
            'twitter_image' => '_seopress_social_twitter_img',
        ];

        if ($action === 'get') {
            return [
                'ok' => true,
                'available' => true,
                'post_id' => $post_id,
                'seo' => $this->read_meta($post_id, $keys),
            ];
        }

        if ($action === 'update') {
            foreach ($keys as $field => $meta_key) {
                if (!array_key_exists($field, $payload)) {
                    continue;
                }
                $value = in_array($field, ['canonical', 'social_image', 'twitter_image'], true)
                    ? esc_url_raw((string) $payload[$field])
                    : sanitize_text_field((string) $payload[$field]);
                update_post_meta($post_id, $meta_key, $value);
            }

            return [
                'ok' => true,
                'available' => true,
                'post_id' => $post_id,
                'seo' => $this->read_meta($post_id, $keys),
                'message' => __('SEOPress metadata updated.', 'livecanvas-forge-ai'),
            ];
        }

        return $this->error(__('Unsupported SEO action.', 'livecanvas-forge-ai'));
    }

    private function get_languages(): array {
        if (!function_exists('pll_languages_list')) {
            return [];
        }

        $slugs = pll_languages_list(['fields' => 'slug']);
        $names = pll_languages_list(['fields' => 'name']);
        $languages = [];
        foreach ((array) $slugs as $index => $slug) {
            $languages[] = [
                'slug' => (string) $slug,
                'name' => (string) ($names[$index] ?? $slug),
            ];
        }

        return $languages;
    }

    private function read_meta(int $post_id, array $keys): array {
        $values = [];
        foreach ($keys as $field => $meta_key) {
            $values[$field] = (string) get_post_meta($post_id, $meta_key, true);
        }

        return $values;
    }

    private function polylang_available(): bool {
        return function_exists('pll_languages_list') && function_exists('pll_get_post_translations');
    }

    private function seopress_available(): bool {
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_activation')) {
            return true;
        }
        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return function_exists('is_plugin_active') && is_plugin_active('wp-seopress/seopress.php');
    }

    private function unavailable(string $integration, string $message): array {
        return [
            'ok' => false,
            'status' => 'unavailable',
            'integration' => $integration,
            'message' => $message,
        ];
    }

    private function error(string $message): array {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
