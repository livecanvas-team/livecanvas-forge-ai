<?php

defined('ABSPATH') || exit;

final class LCFA_Inventory {
    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_inventory(): array {
        return [
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
            'other_partials'    => $this->query_posts([
                'post_type'      => 'lc_partial',
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ], static function (WP_Post $post): bool {
                return get_post_meta($post->ID, 'is_header', true) !== '1' && get_post_meta($post->ID, 'is_footer', true) !== '1';
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
    }

    public function get_summary(): array {
        $pages             = $this->query_count('page', '_lc_livecanvas_enabled');
        $headers           = $this->query_flagged_count('lc_partial', 'is_header');
        $footers           = $this->query_flagged_count('lc_partial', 'is_footer');
        $dynamic_templates = $this->query_count('lc_dynamic_template');
        $blocks            = $this->query_count('lc_block');
        $sections          = $this->query_count('lc_section');

        return [
            'pages'             => $pages,
            'headers'           => $headers,
            'footers'           => $footers,
            'dynamic_templates' => $dynamic_templates,
            'blocks'            => $blocks,
            'sections'          => $sections,
            'framework'         => $this->environment->detect_framework_family(),
            'editor_config'     => $this->environment->get_livecanvas_editor_config_slug(),
            'site_mode'         => $this->environment->detect_site_mode(),
        ];
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
        if (function_exists('lc_get_partial_postid')) {
            $resolved = lc_get_partial_postid($flag, $variant);

            return $resolved ? (int) $resolved : 0;
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

        return isset($posts[0]) ? (int) $posts[0]->ID : 0;
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
        return [
            'id'           => (int) $post->ID,
            'title'        => html_entity_decode(get_the_title($post->ID) ?: __('Untitled', 'livecanvas-forge-ai')),
            'slug'         => $post->post_name,
            'post_type'    => $post->post_type,
            'status'       => $post->post_status,
            'modified_gmt' => $post->post_modified_gmt,
            'edit_url'     => get_edit_post_link($post->ID, 'raw'),
            'view_url'     => get_permalink($post->ID),
        ];
    }

    private function find_partials_by_flag(string $flag): array {
        return $this->query_posts([
            'post_type'      => 'lc_partial',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 100,
            'meta_key'       => $flag,
            'meta_value'     => '1',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);
    }

    private function query_count(string $post_type, string $meta_key = '', string $meta_value = '1'): int {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft', 'future', 'private', 'pending'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];

        if ($meta_key !== '') {
            $args['meta_key']   = $meta_key;
            $args['meta_value'] = $meta_value;
        }

        $query = new WP_Query($args);

        return (int) $query->found_posts;
    }

    private function query_flagged_count(string $post_type, string $meta_key): int {
        return $this->query_count($post_type, $meta_key, '1');
    }
}
