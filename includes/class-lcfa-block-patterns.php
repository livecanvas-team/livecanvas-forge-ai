<?php

defined('ABSPATH') || exit;

final class LCFA_Block_Patterns {
    private const CATEGORY = 'livecanvas-forge-ai';
    private const SECTION_BLOCK = 'livecanvas-forge-ai/section-shell';

    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function hooks(): void {
        add_action('init', [$this, 'register_blocks_and_patterns']);
    }

    public function register_blocks_and_patterns(): void {
        if (function_exists('register_block_type')) {
            register_block_type(self::SECTION_BLOCK, [
                'api_version'     => 3,
                'title'           => __('Forge Section Shell', 'livecanvas-forge-ai'),
                'description'     => __('PHP-rendered section shell used by Forge AI block patterns.', 'livecanvas-forge-ai'),
                'category'        => 'design',
                'attributes'      => [
                    'eyebrow' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'heading' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'variant' => [
                        'type'    => 'string',
                        'default' => 'default',
                    ],
                    'align' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
                'supports'        => [
                    'align'   => true,
                    'html'    => false,
                    'spacing' => [
                        'margin'  => true,
                        'padding' => true,
                    ],
                ],
                'render_callback' => [$this, 'render_section_shell_block'],
            ]);
        }

        if (function_exists('register_block_pattern_category')) {
            register_block_pattern_category(self::CATEGORY, [
                'label' => __('LiveCanvas Forge AI', 'livecanvas-forge-ai'),
            ]);
        }

        if (!function_exists('register_block_pattern')) {
            return;
        }

        foreach ($this->get_patterns() as $pattern) {
            register_block_pattern($pattern['name'], [
                'title'       => $pattern['title'],
                'description' => $pattern['description'],
                'categories'  => [self::CATEGORY],
                'content'     => $pattern['content'],
            ]);
        }
    }

    public function get_pattern_manifest(): array {
        $snapshot = $this->environment->get_snapshot();
        $patterns = array_map(static function (array $pattern): array {
            return [
                'name'        => (string) ($pattern['name'] ?? ''),
                'title'       => (string) ($pattern['title'] ?? ''),
                'description' => (string) ($pattern['description'] ?? ''),
            ];
        }, $this->get_patterns());

        return [
            'available' => true,
            'category'  => self::CATEGORY,
            'block'     => self::SECTION_BLOCK,
            'patterns'  => $patterns,
            'counts'    => [
                'patterns' => count($patterns),
            ],
            'context'   => [
                'livecanvas_active' => !empty($snapshot['livecanvas_active']),
                'framework'         => (string) ($snapshot['detected_framework'] ?? 'unknown'),
                'theme'             => (string) ($snapshot['current_theme_stylesheet'] ?? ''),
            ],
        ];
    }

    public function render_section_shell_block(array $attributes = [], string $content = ''): string {
        $eyebrow = sanitize_text_field((string) ($attributes['eyebrow'] ?? ''));
        $heading = sanitize_text_field((string) ($attributes['heading'] ?? ''));
        $variant = sanitize_key((string) ($attributes['variant'] ?? 'default'));
        $align = sanitize_key((string) ($attributes['align'] ?? ''));

        if ($variant === '') {
            $variant = 'default';
        }

        $classes = [
            'wp-block-livecanvas-forge-ai-section-shell',
            'lcfa-forge-section',
            'lcfa-forge-section--' . sanitize_html_class($variant),
        ];

        if (in_array($align, ['wide', 'full'], true)) {
            $classes[] = 'align' . $align;
        }

        $html = '<section class="' . esc_attr(implode(' ', array_filter($classes))) . '">';
        $html .= '<div class="lcfa-forge-section__inner">';

        if ($eyebrow !== '') {
            $html .= '<p class="lcfa-forge-section__eyebrow">' . esc_html($eyebrow) . '</p>';
        }

        if ($heading !== '') {
            $html .= '<h2 class="lcfa-forge-section__heading">' . esc_html($heading) . '</h2>';
        }

        $content = trim($content);
        if ($content !== '') {
            $html .= '<div class="lcfa-forge-section__content">' . wp_kses_post($content) . '</div>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    public function build_pattern_preview(array $args): array {
        $html = trim((string) ($args['html'] ?? $args['content'] ?? ''));
        $title = sanitize_text_field((string) ($args['title'] ?? __('Forge generated pattern', 'livecanvas-forge-ai')));
        $description = sanitize_text_field((string) ($args['description'] ?? __('Generated by LiveCanvas Forge AI from selected section markup.', 'livecanvas-forge-ai')));
        $source = sanitize_key((string) ($args['source'] ?? 'livecanvas_section'));

        if ($html === '') {
            return [
                'ok'      => false,
                'message' => __('Pattern preview requires HTML content.', 'livecanvas-forge-ai'),
            ];
        }

        if ($title === '') {
            $title = __('Forge generated pattern', 'livecanvas-forge-ai');
        }

        $safe_html = function_exists('wp_kses_post') ? wp_kses_post($html) : $html;
        $slug = sanitize_title((string) ($args['slug'] ?? $title));
        if ($slug === '') {
            $slug = substr(md5($safe_html), 0, 10);
        }

        $content = '<!-- wp:group {"align":"wide","className":"lcfa-generated-pattern"} -->'
            . '<div class="wp-block-group alignwide lcfa-generated-pattern">'
            . '<!-- wp:html -->' . $safe_html . '<!-- /wp:html -->'
            . '</div>'
            . '<!-- /wp:group -->';

        return [
            'ok'      => true,
            'message' => __('Pattern preview prepared.', 'livecanvas-forge-ai'),
            'pattern' => [
                'name'        => self::CATEGORY . '/' . $slug,
                'title'       => $title,
                'description' => $description,
                'categories'  => [self::CATEGORY],
                'content'     => $content,
                'source'      => $source !== '' ? $source : 'livecanvas_section',
                'can_register'=> false,
            ],
            'manifest' => $this->get_pattern_manifest(),
        ];
    }

    private function get_patterns(): array {
        return [
            [
                'name'        => 'livecanvas-forge-ai/conversion-hero',
                'title'       => __('Forge conversion hero', 'livecanvas-forge-ai'),
                'description' => __('A WordPress-native hero pattern for non-LiveCanvas fallback pages.', 'livecanvas-forge-ai'),
                'content'     => $this->get_conversion_hero_pattern_content(),
            ],
            [
                'name'        => 'livecanvas-forge-ai/feature-grid',
                'title'       => __('Forge feature grid', 'livecanvas-forge-ai'),
                'description' => __('A compact three-column feature grid for WordPress-native pattern generation.', 'livecanvas-forge-ai'),
                'content'     => $this->get_feature_grid_pattern_content(),
            ],
        ];
    }

    private function get_conversion_hero_pattern_content(): string {
        return '<!-- wp:livecanvas-forge-ai/section-shell {"eyebrow":"Forge pattern","heading":"Build a focused WordPress page","variant":"hero","align":"wide"} -->'
            . '<!-- wp:paragraph --><p>Use this section as a native block fallback when a page does not need the LiveCanvas editor.</p><!-- /wp:paragraph -->'
            . '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Start the plan</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
            . '<!-- /wp:livecanvas-forge-ai/section-shell -->';
    }

    private function get_feature_grid_pattern_content(): string {
        return '<!-- wp:livecanvas-forge-ai/section-shell {"eyebrow":"Forge pattern","heading":"Three focused reasons","variant":"features","align":"wide"} -->'
            . '<!-- wp:columns -->'
            . '<div class="wp-block-columns">'
            . '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Plan</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Turn the brief into a scoped implementation path.</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
            . '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Preview</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Inspect generated structure before writes are applied.</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
            . '<!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Apply</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Keep changes auditable with rollback references.</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
            . '</div>'
            . '<!-- /wp:columns -->'
            . '<!-- /wp:livecanvas-forge-ai/section-shell -->';
    }
}
