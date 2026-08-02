<?php

defined('ABSPATH') || exit;

final class LCFA_Inventory {
    private LCFA_Environment $environment;
    private ?array $inventory_cache = null;
    private ?array $summary_cache = null;
    private array $count_cache = [];
    private array $partial_id_cache = [];

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_inventory(): array {
        if ($this->inventory_cache !== null) {
            return $this->inventory_cache;
        }

        $this->inventory_cache = [
            'summary'           => $this->get_summary(),
            'livecanvas_pages'  => $this->query_posts([
                'post_type'      => 'page',
                'post_status'    => ['publish', 'draft', 'future', 'private', 'pending'],
                'posts_per_page' => 100,
                'meta_key'       => '_lc_livecanvas_enabled',
                'meta_value'     => '1',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]),
            'header_partials'   => $this->find_partials_by_flag('is_header'),
            'footer_partials'   => $this->find_partials_by_flag('is_footer'),
            'partial_types'     => $this->get_partial_type_taxonomy_terms(),
            'other_partials'    => $this->query_posts([
                'post_type'      => 'lc_partial',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ], static function (WP_Post $post): bool {
                return (string) get_post_meta($post->ID, 'is_header', true) === '' && (string) get_post_meta($post->ID, 'is_footer', true) === '';
            }),
            'dynamic_templates' => $this->query_posts([
                'post_type'      => 'lc_dynamic_template',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]),
            'blocks'            => $this->query_posts([
                'post_type'      => 'lc_block',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]),
            'sections'          => $this->query_posts([
                'post_type'      => 'lc_section',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]),
            'custom_post_types' => $this->get_custom_post_types(),
        ];

        return $this->inventory_cache;
    }

    public function get_summary(): array {
        if ($this->summary_cache !== null) {
            return $this->summary_cache;
        }

        $snapshot          = $this->environment->get_snapshot();
        $pages             = $this->query_count('page', '_lc_livecanvas_enabled');
        $headers           = $this->query_flagged_count('lc_partial', 'is_header');
        $footers           = $this->query_flagged_count('lc_partial', 'is_footer');
        $dynamic_templates = $this->query_count('lc_dynamic_template');
        $blocks            = $this->query_count('lc_block');
        $sections          = $this->query_count('lc_section');

        $this->summary_cache = [
            'pages'             => $pages,
            'headers'           => $headers,
            'footers'           => $footers,
            'partial_types'     => count($this->get_partial_type_taxonomy_terms()),
            'dynamic_templates' => $dynamic_templates,
            'blocks'            => $blocks,
            'sections'          => $sections,
            'framework'         => (string) ($snapshot['detected_framework'] ?? 'unknown'),
            'editor_config'     => (string) ($snapshot['framework_slug'] ?? ''),
            'site_mode'         => (string) ($snapshot['site_mode'] ?? 'unknown'),
        ];

        return $this->summary_cache;
    }

    public function get_target_content(string $target_type, int $target_id = 0, string $variant = '1'): array {
        switch ($target_type) {
            case 'page':
                $post = get_post($target_id);
                break;

            case 'header':
                $post = get_post($this->resolve_partial_post_id('is_header', $variant));
                break;

            case 'footer':
                $post = get_post($this->resolve_partial_post_id('is_footer', $variant));
                break;

            case 'dynamic_template':
                $post = get_post($target_id);
                break;

            case 'partial':
                $post = get_post($target_id);
                break;

            default:
                $post = null;
        }

        if (!$post instanceof WP_Post) {
            return [
                'post'    => null,
                'content' => '',
            ];
        }

        return [
            'post'    => $this->normalize_post($post),
            'content' => (string) get_post_field('post_content', $post->ID, 'raw'),
        ];
    }

    public function resolve_partial_post_id(string $flag, string $variant = '1'): int {
        $cache_key = $flag . ':' . $variant;
        if (array_key_exists($cache_key, $this->partial_id_cache)) {
            return $this->partial_id_cache[$cache_key];
        }

        if (function_exists('lc_get_partial_postid')) {
            $resolved = lc_get_partial_postid($flag, $variant);

            $this->partial_id_cache[$cache_key] = $resolved ? (int) $resolved : 0;

            return $this->partial_id_cache[$cache_key];
        }

        $posts = get_posts([
            'post_type'      => 'lc_partial',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'meta_key'       => $flag,
            'meta_value'     => $variant,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ]);

        $this->partial_id_cache[$cache_key] = isset($posts[0]) ? (int) $posts[0]->ID : 0;

        return $this->partial_id_cache[$cache_key];
    }

    private function get_custom_post_types(): array {
        $post_types = get_post_types(
            [
                '_builtin' => false,
            ],
            'objects'
        );

        $items = [];

        foreach ($post_types as $post_type) {
            if (strpos($post_type->name, 'lc_') === 0) {
                continue;
            }

            $items[] = [
                'name'         => $post_type->name,
                'label'        => $post_type->label,
                'has_archive'  => (bool) $post_type->has_archive,
                'public'       => (bool) $post_type->public,
                'show_in_rest' => (bool) $post_type->show_in_rest,
            ];
        }

        return $items;
    }

    private function query_posts(array $args, ?callable $filter = null): array {
        $posts = get_posts($args);
        $items = [];

        foreach ($posts as $post) {
            if ($filter && !$filter($post)) {
                continue;
            }

            $items[] = $this->normalize_post($post);
        }

        return $items;
    }

    private function normalize_post(WP_Post $post): array {
        $item = [
            'id'           => (int) $post->ID,
            'title'        => html_entity_decode(get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai')),
            'slug'         => $post->post_name,
            'post_type'    => $post->post_type,
            'status'       => $post->post_status,
            'modified_gmt' => $post->post_modified_gmt,
            'edit_url'     => get_edit_post_link($post->ID, 'raw'),
            'view_url'     => get_permalink($post->ID),
        ];

        if ($post->post_type === 'lc_partial') {
            $header_variant = (string) get_post_meta($post->ID, 'is_header', true);
            $footer_variant = (string) get_post_meta($post->ID, 'is_footer', true);

            if ($header_variant !== '') {
                $item['partial_type'] = 'header';
                $item['variant'] = $header_variant;
            } elseif ($footer_variant !== '') {
                $item['partial_type'] = 'footer';
                $item['variant'] = $footer_variant;
            } else {
                $item['partial_type'] = 'partial';
                $item['variant'] = '';
            }

            $partial_type_terms = $this->get_post_partial_type_terms($post->ID);
            $item['partial_type_terms'] = $partial_type_terms;
            $item['partial_type_slugs'] = array_values(array_filter(array_map(static function (array $term): string {
                return (string) ($term['slug'] ?? '');
            }, $partial_type_terms)));
        }

        if ($post->post_type === 'lc_dynamic_template') {
            $assignment = get_post_meta($post->ID, '_lcfa_template_assignment', true);

            $priority = (int) ($post->menu_order ?? 0);
            $item['priority'] = $priority;

            if (is_array($assignment)) {
                if (!array_key_exists('priority', $assignment)) {
                    $assignment['priority'] = $priority;
                }
                $item['template_assignment'] = $assignment;
            } else {
                $assignment = [];
            }

            $language = function_exists('pll_get_post_language')
                ? sanitize_key((string) pll_get_post_language($post->ID, 'slug'))
                : '';
            if ($language === '') {
                $language = sanitize_key((string) get_post_meta($post->ID, '_lcfa_template_language', true));
            }
            $item['language'] = $language;
            if ($language !== '' && !array_key_exists('language', $assignment)) {
                $assignment['language'] = $language;
            }

            $assigned_post_id = absint($assignment['assigned_post_id'] ?? get_post_meta($post->ID, '_lcfa_template_assigned_post_id', true));
            $item['assigned_post_id'] = $assigned_post_id;
            if ($assigned_post_id > 0 && !array_key_exists('assigned_post_id', $assignment)) {
                $assignment['assigned_post_id'] = $assigned_post_id;
            }
            if ($assigned_post_id > 0) {
                $assigned_post = get_post($assigned_post_id);
                if ($assigned_post instanceof WP_Post) {
                    $item['assigned_post'] = [
                        'id'        => $assigned_post_id,
                        'title'     => html_entity_decode(get_the_title($assigned_post_id) ?: __('Untitled', 'livecanvas-forge-ai')),
                        'post_type' => sanitize_key((string) $assigned_post->post_type),
                        'url'       => (string) get_permalink($assigned_post_id),
                    ];
                }
            }

            if ($assignment) {
                $item['template_assignment'] = $assignment;
            }

            $item['preview_target'] = $this->get_dynamic_template_preview_target($assignment);
            $item['preview_url'] = (string) ($item['preview_target']['url'] ?? '');

            $native_template_keys = [];
            foreach ((array) get_post_meta($post->ID) as $meta_key => $meta_value) {
                if (strpos((string) $meta_key, 'is_') !== 0) {
                    continue;
                }

                $value = is_array($meta_value) ? reset($meta_value) : $meta_value;
                if ((string) $value === '1' || $value === 1 || $value === true) {
                    $native_template_keys[] = (string) $meta_key;
                }
            }

            if ($native_template_keys) {
                $item['native_template_keys'] = array_values(array_unique($native_template_keys));
            }
        }

        return $item;
    }

    private function get_dynamic_template_preview_target(array $assignment): array {
        $target = [
            'url'       => '',
            'source'    => 'unavailable',
            'post_id'   => 0,
            'post_type' => '',
            'title'     => '',
        ];

        $explicit_url = esc_url_raw((string) ($assignment['preview_url'] ?? ''));
        if ($explicit_url !== '') {
            $target['url'] = $explicit_url;
            $target['source'] = 'explicit';

            return $target;
        }

        $assigned_post_id = absint($assignment['assigned_post_id'] ?? 0);
        if ($assigned_post_id > 0) {
            $post = get_post($assigned_post_id);
            if ($post instanceof WP_Post) {
                $target['url'] = (string) get_permalink($assigned_post_id);
                $target['source'] = 'assigned_post';
                $target['post_id'] = $assigned_post_id;
                $target['post_type'] = sanitize_key((string) $post->post_type);
                $target['title'] = sanitize_text_field((string) $post->post_title);

                return $target;
            }
        }

        $assignment_target = sanitize_key((string) ($assignment['target'] ?? ''));
        $post_type = sanitize_key((string) ($assignment['post_type'] ?? ''));
        if ($assignment_target === 'single' && $post_type !== '') {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]);
            if (!empty($posts[0]) && $posts[0] instanceof WP_Post) {
                $post = $posts[0];
                $target['url'] = (string) get_permalink((int) $post->ID);
                $target['source'] = 'sample_single';
                $target['post_id'] = (int) $post->ID;
                $target['post_type'] = $post_type;
                $target['title'] = sanitize_text_field((string) $post->post_title);

                return $target;
            }
        }

        if (in_array($assignment_target, ['archive', 'post_type'], true) && $post_type !== '' && function_exists('get_post_type_archive_link')) {
            $url = get_post_type_archive_link($post_type);
            if (is_string($url) && $url !== '') {
                $target['url'] = esc_url_raw($url);
                $target['source'] = 'post_type_archive';
                $target['post_type'] = $post_type;

                return $target;
            }
        }

        $taxonomy = sanitize_key((string) ($assignment['taxonomy'] ?? ''));
        $term_slug = sanitize_key((string) ($assignment['term'] ?? ''));
        if ($assignment_target === 'taxonomy' && $taxonomy !== '' && function_exists('get_term_link')) {
            $term = null;
            if ($term_slug !== '' && function_exists('get_term_by')) {
                $term = get_term_by('slug', $term_slug, $taxonomy);
            } elseif (function_exists('get_terms')) {
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'number'     => 1,
                ]);
                if (!is_wp_error($terms) && is_array($terms) && !empty($terms[0])) {
                    $term = $terms[0];
                }
            }

            if ($term && !is_wp_error($term)) {
                $url = get_term_link($term, $taxonomy);
                if (!is_wp_error($url) && is_string($url) && $url !== '') {
                    $target['url'] = esc_url_raw($url);
                    $target['source'] = $term_slug !== '' ? 'taxonomy_term' : 'sample_taxonomy_term';
                    $target['title'] = sanitize_text_field((string) ($term->name ?? $term_slug));

                    return $target;
                }
            }
        }

        $specialty = sanitize_key((string) ($assignment['specialty'] ?? $assignment['template_target'] ?? ''));
        if (in_array($specialty, ['front', 'front_page', 'homepage', 'home'], true)) {
            $front_id = absint(get_option('page_on_front', 0));
            $target['url'] = $front_id > 0 ? (string) get_permalink($front_id) : home_url('/');
            $target['source'] = $front_id > 0 ? 'front_page' : 'site_home';
            $target['post_id'] = $front_id;
        } elseif (in_array($specialty, ['blog', 'blog_index', 'blog_posts_index', 'posts_index'], true)) {
            $blog_id = absint(get_option('page_for_posts', 0));
            $target['url'] = $blog_id > 0 ? (string) get_permalink($blog_id) : home_url('/');
            $target['source'] = $blog_id > 0 ? 'posts_page' : 'site_home';
            $target['post_id'] = $blog_id;
        } elseif (in_array($specialty, ['author', 'author_archive'], true) && function_exists('get_users') && function_exists('get_author_posts_url')) {
            $users = get_users(['number' => 1, 'fields' => ['ID', 'display_name']]);
            if (!empty($users[0])) {
                $user = $users[0];
                $user_id = absint(is_object($user) ? ($user->ID ?? 0) : ($user['ID'] ?? 0));
                if ($user_id > 0) {
                    $target['url'] = (string) get_author_posts_url($user_id);
                    $target['source'] = 'author_archive';
                    $target['title'] = sanitize_text_field((string) (is_object($user) ? ($user->display_name ?? '') : ($user['display_name'] ?? '')));
                }
            }
        } elseif (in_array($specialty, ['date', 'date_archive'], true) && function_exists('get_month_link')) {
            $target['url'] = (string) get_month_link((int) gmdate('Y'), (int) gmdate('m'));
            $target['source'] = 'date_archive';
        } elseif ($specialty === 'search') {
            $target['url'] = function_exists('get_search_link') ? (string) get_search_link('') : home_url('?s=');
            $target['source'] = 'search';
        } elseif (in_array($specialty, ['404', 'not_found'], true)) {
            $target['url'] = home_url('/__lcfa-preview-404__/');
            $target['source'] = 'not_found';
        }

        if ($target['url'] === '') {
            $woocommerce_pages = [
                'shop'          => 'shop',
                'shop_page'     => 'shop',
                'cart'          => 'cart',
                'cart_page'     => 'cart',
                'checkout'      => 'checkout',
                'checkout_page' => 'checkout',
                'account'       => 'myaccount',
                'my_account'    => 'myaccount',
                'account_page'  => 'myaccount',
            ];
            if (isset($woocommerce_pages[$specialty]) && function_exists('wc_get_page_permalink')) {
                $url = wc_get_page_permalink($woocommerce_pages[$specialty]);
                if (is_string($url) && $url !== '') {
                    $target['url'] = esc_url_raw($url);
                    $target['source'] = 'woocommerce';
                }
            }
        }

        return $target;
    }

    private function get_partial_type_taxonomy_terms(): array {
        if (!function_exists('taxonomy_exists') || !taxonomy_exists('lc_partial_type') || !function_exists('get_terms')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => 'lc_partial_type',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_values(array_map([$this, 'normalize_partial_type_term'], $terms));
    }

    private function get_post_partial_type_terms(int $post_id): array {
        if (!function_exists('taxonomy_exists') || !taxonomy_exists('lc_partial_type') || !function_exists('wp_get_post_terms')) {
            return [];
        }

        $terms = wp_get_post_terms($post_id, 'lc_partial_type');
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_values(array_map([$this, 'normalize_partial_type_term'], $terms));
    }

    private function normalize_partial_type_term($term): array {
        return [
            'id'     => absint(is_object($term) ? ($term->term_id ?? 0) : ($term['term_id'] ?? 0)),
            'name'   => sanitize_text_field((string) (is_object($term) ? ($term->name ?? '') : ($term['name'] ?? ''))),
            'slug'   => sanitize_key((string) (is_object($term) ? ($term->slug ?? '') : ($term['slug'] ?? ''))),
            'parent' => absint(is_object($term) ? ($term->parent ?? 0) : ($term['parent'] ?? 0)),
        ];
    }

    private function find_partials_by_flag(string $flag): array {
        return $this->query_posts([
            'post_type'      => 'lc_partial',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 100,
            'meta_key'       => $flag,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ], static function (WP_Post $post) use ($flag): bool {
            return (string) get_post_meta($post->ID, $flag, true) !== '';
        });
    }

    private function query_count(string $post_type, string $meta_key = '', ?string $meta_value = '1'): int {
        $cache_key = md5(wp_json_encode([$post_type, $meta_key, $meta_value]));
        if (array_key_exists($cache_key, $this->count_cache)) {
            return $this->count_cache[$cache_key];
        }

        $args = [
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft', 'future', 'private', 'pending'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];

        if ($meta_key !== '') {
            $args['meta_key'] = $meta_key;

            if ($meta_value !== null) {
                $args['meta_value'] = $meta_value;
            }
        }

        $query = new WP_Query($args);

        $this->count_cache[$cache_key] = (int) $query->found_posts;

        return $this->count_cache[$cache_key];
    }

    private function query_flagged_count(string $post_type, string $meta_key): int {
        return $this->query_count($post_type, $meta_key, null);
    }
}
