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

    public function get_pattern_library(array $args = []): array {
        $include_content = !array_key_exists('include_content', $args) || !empty($args['include_content']);
        $manifest = $this->get_pattern_manifest();
        $patterns = [];
        $total_bytes = 0;

        foreach ($this->get_patterns() as $pattern) {
            $content = (string) ($pattern['content'] ?? '');
            $bytes = strlen($content);
            $total_bytes += $bytes;

            $entry = [
                'name'          => sanitize_text_field((string) ($pattern['name'] ?? '')),
                'title'         => sanitize_text_field((string) ($pattern['title'] ?? '')),
                'description'   => sanitize_text_field((string) ($pattern['description'] ?? '')),
                'categories'    => [self::CATEGORY],
                'block'         => self::SECTION_BLOCK,
                'bytes'         => $bytes,
                'sha256'        => hash('sha256', $content),
                'suggested_use' => $this->describe_pattern_use((string) ($pattern['name'] ?? '')),
            ];

            if ($include_content) {
                $entry['content'] = $content;
            }

            $patterns[] = $entry;
        }

        return [
            'schema_version' => 'block-pattern-library.v1',
            'available'      => true,
            'source'         => 'registered_forge_patterns',
            'category'       => self::CATEGORY,
            'block'          => self::SECTION_BLOCK,
            'include_content' => $include_content,
            'counts'         => [
                'patterns' => count($patterns),
                'bytes'    => $total_bytes,
            ],
            'context'        => is_array($manifest['context'] ?? null) ? $manifest['context'] : [],
            'export'         => [
                'format'              => 'wordpress_block_patterns',
                'can_import'          => false,
                'preview_ability'     => 'livecanvas-forge-ai/preview-block-pattern',
                'recommended_target'  => !empty($manifest['context']['livecanvas_active']) ? 'native_fallback_or_reusable_pattern' : 'native_wordpress_page',
            ],
            'patterns'       => $patterns,
        ];
    }

    public function get_native_page_blueprints(array $args = []): array {
        $include_patterns = !array_key_exists('include_patterns', $args) || !empty($args['include_patterns']);
        $blueprints = [];

        foreach ($this->get_native_page_blueprint_definitions() as $blueprint) {
            $pattern_names = array_values(array_map([$this, 'normalize_pattern_name'], (array) ($blueprint['pattern_names'] ?? [])));
            $preview_payload = [
                'title'     => sanitize_text_field((string) ($blueprint['preview_title'] ?? $blueprint['title'] ?? '')),
                'blueprint' => sanitize_key((string) ($blueprint['id'] ?? '')),
            ];
            $apply_payload = $preview_payload + [
                'status' => 'draft',
            ];
            $entry = [
                'id'          => sanitize_key((string) ($blueprint['id'] ?? '')),
                'title'       => sanitize_text_field((string) ($blueprint['title'] ?? '')),
                'description' => sanitize_text_field((string) ($blueprint['description'] ?? '')),
                'pattern_count' => count($pattern_names),
                'preview_payload' => $preview_payload,
                'apply_payload' => $apply_payload,
                'preview_request' => [
                    'method'     => 'POST',
                    'rest_route' => '/wp-json/lcfa/v1/studio/native-pattern-page-preview',
                    'ability'    => 'livecanvas-forge-ai/preview-native-pattern-page',
                    'mcp_tool'   => 'preview_native_pattern_page',
                    'payload'    => $preview_payload,
                ],
                'apply_request' => [
                    'method'     => 'POST',
                    'rest_route' => '/wp-json/lcfa/v1/studio/native-pattern-page-apply',
                    'ability'    => 'livecanvas-forge-ai/apply-native-pattern-page',
                    'mcp_tool'   => 'apply_native_pattern_page',
                    'payload'    => $apply_payload,
                ],
                'suggested_use' => sanitize_text_field((string) ($blueprint['suggested_use'] ?? '')),
            ];

            if ($include_patterns) {
                $entry['pattern_names'] = $pattern_names;
            }

            $blueprints[] = $entry;
        }

        return [
            'schema_version' => 'native-pattern-page-blueprints.v1',
            'available'      => true,
            'source'         => 'registered_forge_patterns',
            'include_patterns' => $include_patterns,
            'counts'         => [
                'blueprints' => count($blueprints),
            ],
            'preview_ability'=> 'livecanvas-forge-ai/preview-native-pattern-page',
            'preview_tool'   => 'preview_native_pattern_page',
            'preview_route'  => '/wp-json/lcfa/v1/studio/native-pattern-page-preview',
            'apply_ability'  => 'livecanvas-forge-ai/apply-native-pattern-page',
            'apply_tool'     => 'apply_native_pattern_page',
            'apply_route'    => '/wp-json/lcfa/v1/studio/native-pattern-page-apply',
            'blueprints'     => $blueprints,
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

    public function build_native_page_preview(array $args): array {
        $title = sanitize_text_field((string) ($args['title'] ?? __('Forge native page preview', 'livecanvas-forge-ai')));
        if ($title === '') {
            $title = __('Forge native page preview', 'livecanvas-forge-ai');
        }

        $patterns_by_name = [];
        foreach ($this->get_patterns() as $pattern) {
            $name = sanitize_text_field((string) ($pattern['name'] ?? ''));
            if ($name !== '') {
                $patterns_by_name[$name] = $pattern;
            }
        }

        $requested_blueprint_id = $this->normalize_native_page_blueprint_id($args);
        $blueprint = $this->find_native_page_blueprint($args);
        $requested_names = $this->normalize_requested_pattern_names($args);
        if ($requested_blueprint_id !== '' && !$blueprint && empty($requested_names)) {
            return [
                'ok'         => false,
                'message'    => __('Unknown native page blueprint. Choose one of the available blueprints or pass explicit pattern names.', 'livecanvas-forge-ai'),
                'missing'    => [],
                'warnings'   => [
                    [
                        'code'    => 'missing_blueprint',
                        'message' => sprintf(
                            /* translators: %s: requested blueprint id. */
                            __('Requested native page blueprint is not registered: %s', 'livecanvas-forge-ai'),
                            $requested_blueprint_id
                        ),
                    ],
                ],
                'library'    => $this->get_pattern_library(['include_content' => false]),
                'blueprints' => $this->get_native_page_blueprints(['include_patterns' => true]),
            ];
        }

        if ($blueprint && (empty($args['title']) || !is_scalar($args['title']))) {
            $title = sanitize_text_field((string) ($blueprint['preview_title'] ?? $blueprint['title'] ?? $title));
        }

        if (empty($requested_names) && $blueprint) {
            $requested_names = array_values(array_map([$this, 'normalize_pattern_name'], (array) ($blueprint['pattern_names'] ?? [])));
        }
        if (empty($requested_names)) {
            $requested_names = array_keys($patterns_by_name);
        }

        $requested_names = array_slice(array_values(array_unique($requested_names)), 0, 8);
        $selected = [];
        $missing = [];
        $content_parts = [];
        $warnings = [];

        if ($requested_blueprint_id !== '' && !$blueprint) {
            $warnings[] = [
                'code'    => 'missing_blueprint',
                'message' => sprintf(
                    /* translators: %s: requested blueprint id. */
                    __('Requested native page blueprint is not registered: %s', 'livecanvas-forge-ai'),
                    $requested_blueprint_id
                ),
            ];
        }

        foreach ($requested_names as $requested_name) {
            $name = $this->normalize_pattern_name($requested_name);
            if ($name === '') {
                continue;
            }

            if (!isset($patterns_by_name[$name])) {
                $missing[] = $name;
                continue;
            }

            $pattern = $patterns_by_name[$name];
            $content = (string) ($pattern['content'] ?? '');
            if ($content === '') {
                continue;
            }

            $selected[] = [
                'name'        => $name,
                'title'       => sanitize_text_field((string) ($pattern['title'] ?? '')),
                'description' => sanitize_text_field((string) ($pattern['description'] ?? '')),
                'bytes'       => strlen($content),
                'sha256'      => hash('sha256', $content),
            ];
            $content_parts[] = $content;
        }

        if (empty($content_parts)) {
            return [
                'ok'       => false,
                'message'  => __('Native page preview requires at least one known Forge pattern.', 'livecanvas-forge-ai'),
                'missing'  => $missing,
                'library'  => $this->get_pattern_library(['include_content' => false]),
            ];
        }

        $content = implode("\n\n", $content_parts);
        if (!empty($missing)) {
            $warnings[] = [
                'code'    => 'missing_patterns',
                'message' => sprintf(
                    /* translators: %s: comma-separated missing pattern names. */
                    __('Some requested patterns are not registered: %s', 'livecanvas-forge-ai'),
                    implode(', ', $missing)
                ),
            ];
        }

        return [
            'ok'             => true,
            'schema_version' => 'native-pattern-page-preview.v1',
            'message'        => __('Native page preview prepared.', 'livecanvas-forge-ai'),
            'page'           => [
                'title'          => $title,
                'post_type'      => 'page',
                'status'         => 'draft',
                'content_format' => 'wordpress_blocks',
                'content'        => $content,
                'bytes'          => strlen($content),
                'sha256'         => hash('sha256', $content),
                'blueprint'      => $blueprint ? sanitize_key((string) ($blueprint['id'] ?? '')) : '',
                'patterns'       => $selected,
                'can_apply'      => false,
            ],
            'warnings'       => $warnings,
            'next_actions'   => [
                [
                    'id'      => 'review_preview',
                    'label'   => __('Review the generated native block content before using it in a page.', 'livecanvas-forge-ai'),
                    'writes'  => false,
                ],
                [
                    'id'      => 'choose_target',
                    'label'   => __('Use this as a WordPress-native fallback; keep LiveCanvas page_upsert for LiveCanvas pages.', 'livecanvas-forge-ai'),
                    'writes'  => false,
                ],
            ],
            'library'        => $this->get_pattern_library(['include_content' => false]),
            'blueprints'     => $this->get_native_page_blueprints(['include_patterns' => true]),
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

    private function describe_pattern_use(string $pattern_name): string {
        if (strpos($pattern_name, 'conversion-hero') !== false) {
            return __('Use as a native hero fallback for simple WordPress pages or reusable pattern previews.', 'livecanvas-forge-ai');
        }

        if (strpos($pattern_name, 'feature-grid') !== false) {
            return __('Use as a native feature grid fallback when a LiveCanvas layout is not required.', 'livecanvas-forge-ai');
        }

        return __('Use as a WordPress-native fallback pattern generated by LiveCanvas Forge AI.', 'livecanvas-forge-ai');
    }

    private function get_native_page_blueprint_definitions(): array {
        return [
            [
                'id'            => 'starter-landing',
                'title'         => __('Starter landing page', 'livecanvas-forge-ai'),
                'preview_title' => __('Forge native starter landing', 'livecanvas-forge-ai'),
                'description'   => __('A compact native page composed from the Forge hero and feature grid patterns.', 'livecanvas-forge-ai'),
                'pattern_names' => [
                    'conversion-hero',
                    'feature-grid',
                ],
                'suggested_use' => __('Use when LiveCanvas is absent or when an agent needs a WordPress-native fallback draft.', 'livecanvas-forge-ai'),
            ],
            [
                'id'            => 'hero-only',
                'title'         => __('Hero only', 'livecanvas-forge-ai'),
                'preview_title' => __('Forge native hero preview', 'livecanvas-forge-ai'),
                'description'   => __('A minimal native page preview with only the conversion hero pattern.', 'livecanvas-forge-ai'),
                'pattern_names' => [
                    'conversion-hero',
                ],
                'suggested_use' => __('Use for quick native block smoke tests or first-section previews.', 'livecanvas-forge-ai'),
            ],
            [
                'id'            => 'feature-summary',
                'title'         => __('Feature summary', 'livecanvas-forge-ai'),
                'preview_title' => __('Forge native feature summary', 'livecanvas-forge-ai'),
                'description'   => __('A compact native page preview with only the feature grid pattern.', 'livecanvas-forge-ai'),
                'pattern_names' => [
                    'feature-grid',
                ],
                'suggested_use' => __('Use when the agent needs reusable native comparison or benefit blocks.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    private function find_native_page_blueprint(array $args): ?array {
        $blueprint_id = $this->normalize_native_page_blueprint_id($args);
        if ($blueprint_id === '') {
            return null;
        }

        foreach ($this->get_native_page_blueprint_definitions() as $blueprint) {
            if (sanitize_key((string) ($blueprint['id'] ?? '')) === $blueprint_id) {
                return $blueprint;
            }
        }

        return null;
    }

    private function normalize_native_page_blueprint_id(array $args): string {
        return sanitize_key((string) ($args['blueprint'] ?? $args['blueprint_id'] ?? ''));
    }

    private function normalize_requested_pattern_names(array $args): array {
        $names = [];

        if (isset($args['pattern_names']) && is_array($args['pattern_names'])) {
            $names = array_merge($names, $args['pattern_names']);
        }

        if (isset($args['patterns']) && is_array($args['patterns'])) {
            $names = array_merge($names, $args['patterns']);
        }

        if (isset($args['pattern_name']) && is_scalar($args['pattern_name'])) {
            $names[] = (string) $args['pattern_name'];
        }

        return array_values(array_filter(array_map([$this, 'normalize_pattern_name'], $names)));
    }

    private function normalize_pattern_name($name): string {
        $name = sanitize_text_field((string) $name);
        if ($name === '') {
            return '';
        }

        if (strpos($name, '/') === false) {
            $slug = sanitize_title($name);
            if ($slug === '') {
                return '';
            }

            return self::CATEGORY . '/' . $slug;
        }

        return $name;
    }
}
