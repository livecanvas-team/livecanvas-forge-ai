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
            $args['meta_key']   = $meta_key;
            $args['meta_value'] = $meta_value;
        }

        $query = new WP_Query($args);

        $this->count_cache[$cache_key] = (int) $query->found_posts;

        return $this->count_cache[$cache_key];
    }

    private function query_flagged_count(string $post_type, string $meta_key): int {
        return $this->query_count($post_type, $meta_key, '1');
    }
}
