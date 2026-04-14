<?php

defined('ABSPATH') || exit;

final class LCFA_Picostrap_Compile_Service {
    private LCFA_Environment $environment;
    private LCFA_Picostrap_Compile_Manifest $manifest;
    private LCFA_Picostrap_Bundle_Store $bundle_store;

    public function __construct(LCFA_Environment $environment, ?LCFA_Picostrap_Compile_Manifest $manifest = null, ?LCFA_Picostrap_Bundle_Store $bundle_store = null) {
        $this->environment = $environment;
        $this->manifest = $manifest ?: new LCFA_Picostrap_Compile_Manifest($environment);
        $this->bundle_store = $bundle_store ?: new LCFA_Picostrap_Bundle_Store($environment);
    }

    public function get_manifest(): array {
        return $this->manifest->build();
    }

    public function get_source(string $import_path): array {
        $normalized = $this->sanitize_import_path($import_path);

        foreach ($this->build_source_roots() as $root) {
            $absolute = wp_normalize_path(trailingslashit($root['path']) . $normalized);

            if (is_readable($absolute) && is_file($absolute)) {
                return [
                    'ok' => true,
                    'normalized_path' => $normalized,
                    'contents' => (string) file_get_contents($absolute),
                    'origin' => $root['origin'],
                ];
            }
        }

        return [
            'ok' => false,
            'message' => __('SCSS import not found.', 'livecanvas-forge-ai'),
            'normalized_path' => $normalized,
        ];
    }

    public function store_bundle(string $css): array {
        return $this->bundle_store->store($css);
    }

    public function get_compile_url(): string {
        return home_url('/?compile_sass=1&sass_nocache=1');
    }

    private function sanitize_import_path(string $import_path): string {
        $normalized = ltrim(wp_normalize_path(trim($import_path)), '/');

        if ($normalized === '' || str_contains($normalized, '..') || !str_ends_with(strtolower($normalized), '.scss')) {
            throw new RuntimeException(__('Invalid SCSS import path.', 'livecanvas-forge-ai'));
        }

        if (str_contains($normalized, "\0")) {
            throw new RuntimeException(__('Invalid SCSS import path.', 'livecanvas-forge-ai'));
        }

        return $normalized;
    }

    private function build_source_roots(): array {
        $roots = [
            [
                'origin' => 'child',
                'path' => trailingslashit(get_stylesheet_directory()) . 'sass',
            ],
        ];

        $template_directory = get_template_directory();
        $stylesheet_directory = get_stylesheet_directory();

        if (wp_normalize_path($template_directory) !== wp_normalize_path($stylesheet_directory)) {
            $roots[] = [
                'origin' => 'parent',
                'path' => trailingslashit($template_directory) . 'sass',
            ];
        }

        return $roots;
    }
}
