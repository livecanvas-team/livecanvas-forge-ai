<?php

defined('ABSPATH') || exit;

final class LCFA_Genesis_Planner {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory) {
        $this->environment = $environment;
        $this->inventory   = $inventory;
    }

    public function generate(?array $brief = null): array {
        $brief     = is_array($brief) ? LCFA_Settings::get_sanitized_project_brief($brief) : LCFA_Settings::get_project_brief();
        $snapshot  = $this->environment->get_snapshot();
        $summary   = $this->inventory->get_summary();
        $brief_hash = LCFA_Settings::get_project_brief_hash($brief);
        $pages     = $this->build_page_plan($brief, $snapshot);
        $tasks     = $this->build_task_plan($brief, $snapshot, $summary, $pages);

        return [
            'generated_at' => current_time('mysql', true),
            'brief_hash'   => $brief_hash,
            'mode'         => (string) $brief['project_mode'],
            'brand'        => [
                'name'        => (string) $brief['brand_name'],
                'sector'      => (string) $brief['sector'],
                'tone'        => (string) $brief['tone'],
                'logo_status' => (string) $brief['logo_status'],
            ],
            'stack'        => [
                'framework'          => (string) ($snapshot['detected_framework'] ?? ''),
                'theme'              => (string) ($snapshot['current_theme_stylesheet'] ?? ''),
                'site_mode'          => (string) ($snapshot['site_mode'] ?? ''),
                'windpress_active'   => !empty($snapshot['windpress_active']),
                'woocommerce_active' => !empty($snapshot['woocommerce_active']),
                'acf_active'         => !empty($snapshot['acf_active']),
            ],
            'inventory_summary' => $summary,
            'pages'         => $pages,
            'tasks'         => $tasks,
            'counts'        => [
                'pages'     => count($pages),
                'tasks'     => count($tasks),
                'advisories'=> count(array_filter($tasks, static function (array $task): bool {
                    return empty($task['payload']['action']);
                })),
            ],
        ];
    }

    private function build_page_plan(array $brief, array $snapshot): array {
        $requested = $this->parse_required_pages((string) ($brief['required_pages'] ?? ''));
        $notes     = strtolower((string) ($brief['notes'] ?? ''));
        $pages     = [];

        if (!$requested && ($brief['project_mode'] ?? '') === 'from_scratch') {
            $requested = ['Home', 'About', 'Services', 'Contact'];
        }

        if ($requested && !$this->contains_page($requested, 'home')) {
            array_unshift($requested, 'Home');
        }

        if (!$this->contains_page($requested, 'blog') && (str_contains($notes, 'blog') || !empty($snapshot['woocommerce_active']) === false)) {
            if (str_contains($notes, 'blog') || str_contains($notes, 'news') || str_contains($notes, 'articles')) {
                $requested[] = 'Blog';
            }
        }

        if (!empty($snapshot['woocommerce_active']) && !$this->contains_page($requested, 'shop')) {
            $requested[] = 'Shop';
        }

        foreach ($requested as $index => $page_title) {
            $normalized_title = sanitize_text_field($page_title);

            if ($normalized_title === '') {
                continue;
            }

            $slug = sanitize_title($normalized_title);
            $kind = $this->detect_page_kind($normalized_title);

            $pages[] = [
                'title'       => $normalized_title,
                'slug'        => $slug !== '' ? $slug : 'page-' . ($index + 1),
                'kind'        => $kind,
                'homepage'    => $index === 0 || $kind === 'home',
                'description' => $this->describe_page_kind($kind, $normalized_title),
            ];
        }

        return $pages;
    }

    private function build_task_plan(array $brief, array $snapshot, array $summary, array $pages): array {
        $tasks = [];
        $notes = strtolower((string) ($brief['notes'] ?? ''));
        $framework = (string) ($snapshot['detected_framework'] ?? '');

        $tasks[] = $this->task(
            'foundation-prepare',
            'foundation',
            __('Prepare the site foundation', 'livecanvas-forge-ai'),
            __('Preflight the stack, inventory, theme roots, and foundation readiness before writing structure.', 'livecanvas-forge-ai'),
            [
                'action' => 'site_prepare',
            ],
            __('Inspect the current site foundation and confirm the safe build path.', 'livecanvas-forge-ai')
        );

        $tasks[] = $this->task(
            'foundation-shell',
            'foundation',
            __('Create or refresh the global shell', 'livecanvas-forge-ai'),
            __('Create or update the header and footer partials for the selected stack and brand direction.', 'livecanvas-forge-ai'),
            [
                'action' => 'global_shell_apply',
                'variant' => '1',
            ],
            __('Use the Genesis brief to define navigation, branding, footer links, and top-level CTA structure.', 'livecanvas-forge-ai')
        );

        if (($brief['logo_status'] ?? '') === 'to_generate') {
            $tasks[] = $this->task(
                'brand-logo',
                'foundation',
                __('Prepare the logo asset workflow', 'livecanvas-forge-ai'),
                __('The brief says the logo still needs generation, so this should be handled before polishing header and hero areas.', 'livecanvas-forge-ai'),
                [],
                __('Advisory step: generate or source a logo asset before final UI refinement.', 'livecanvas-forge-ai')
            );
        }

        if ($framework === 'picowind') {
            $tasks[] = $this->task(
                'style-windpress',
                'foundation',
                __('Audit Tailwind / WindPress runtime', 'livecanvas-forge-ai'),
                __('Before building pages, confirm that provider scan and cache generation are available for Picowind.', 'livecanvas-forge-ai'),
                [
                    'action' => 'windpress_audit',
                ],
                __('Inspect Tailwind providers and cache state before generating page sections.', 'livecanvas-forge-ai')
            );
        } else {
            $tasks[] = $this->task(
                'style-theme',
                'foundation',
                __('Inspect fallback theme files', 'livecanvas-forge-ai'),
                __('Bootstrap/Picostrap projects still need a safe fallback layer for templates and assets.', 'livecanvas-forge-ai'),
                [
                    'action' => 'theme_files_audit',
                ],
                __('Inspect the active theme roots and fallback templates before adding custom files.', 'livecanvas-forge-ai')
            );
        }

        foreach ($pages as $page) {
            $tasks[] = $this->task(
                'page-' . $page['slug'],
                'pages',
                sprintf(__('Create page: %s', 'livecanvas-forge-ai'), $page['title']),
                $page['description'],
                [
                    'action' => 'create_page',
                    'title'  => $page['title'],
                    'slug'   => $page['slug'],
                    'status' => 'draft',
                ],
                sprintf(__('Create a %1$s page for the Genesis plan.', 'livecanvas-forge-ai'), $page['title'])
            );
        }

        if ($this->contains_page(array_column($pages, 'title'), 'blog') || str_contains($notes, 'blog')) {
            $tasks[] = $this->task(
                'dynamic-blog-archive',
                'dynamic',
                __('Prepare blog archive template', 'livecanvas-forge-ai'),
                __('If the project includes editorial content, create the archive template early so content pages are consistent.', 'livecanvas-forge-ai'),
                [
                    'action' => 'create_dynamic_template',
                    'title'  => __('Blog Archive', 'livecanvas-forge-ai'),
                    'slug'   => 'blog-archive',
                    'status' => 'draft',
                ],
                __('Create a dynamic archive template for the blog listing.', 'livecanvas-forge-ai')
            );
        }

        if (!empty($snapshot['woocommerce_active'])) {
            $tasks[] = $this->task(
                'dynamic-shop',
                'dynamic',
                __('Prepare WooCommerce template strategy', 'livecanvas-forge-ai'),
                __('WooCommerce is active, so product and archive templates should be planned from the start.', 'livecanvas-forge-ai'),
                [],
                __('Advisory step: define product, shop, and account template priorities before implementation.', 'livecanvas-forge-ai')
            );
        }

        if (!empty($snapshot['acf_active'])) {
            $tasks[] = $this->task(
                'dynamic-acf',
                'dynamic',
                __('Review ACF field groups before dynamic work', 'livecanvas-forge-ai'),
                __('ACF is active, so dynamic templates should map fields deliberately instead of hardcoding placeholders.', 'livecanvas-forge-ai'),
                [
                    'action' => 'site_audit',
                ],
                __('Review ACF-backed content before generating dynamic templates.', 'livecanvas-forge-ai')
            );
        }

        if (($brief['project_mode'] ?? '') === 'step_by_step' && $summary['pages'] > 0) {
            array_unshift($tasks, $this->task(
                'step-by-step-audit',
                'discovery',
                __('Audit the existing site before changing structure', 'livecanvas-forge-ai'),
                __('Step-by-step mode should start from the current site inventory instead of generating a full net-new sitemap blindly.', 'livecanvas-forge-ai'),
                [
                    'action' => 'site_audit',
                ],
                __('Audit the current site and reuse existing pages where possible.', 'livecanvas-forge-ai')
            ));
        }

        return $tasks;
    }

    private function task(string $id, string $stage, string $label, string $description, array $payload = [], string $prompt = ''): array {
        return [
            'id'          => $id,
            'stage'       => $stage,
            'label'       => $label,
            'description' => $description,
            'payload'     => $payload,
            'user_prompt' => $prompt,
        ];
    }

    private function parse_required_pages(string $raw): array {
        $source = preg_split('/[\n,;]+/', $raw) ?: [];
        $pages  = [];

        foreach ($source as $candidate) {
            $candidate = trim(wp_strip_all_tags($candidate));

            if ($candidate === '') {
                continue;
            }

            $pages[] = sanitize_text_field($candidate);
        }

        return array_values(array_unique($pages));
    }

    private function contains_page(array $pages, string $needle): bool {
        $needle = strtolower($needle);

        foreach ($pages as $page) {
            if (strtolower((string) $page) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function detect_page_kind(string $title): string {
        $normalized = strtolower($title);

        if (in_array($normalized, ['home', 'homepage', 'home page'], true)) {
            return 'home';
        }

        if (in_array($normalized, ['about', 'about us'], true)) {
            return 'about';
        }

        if (in_array($normalized, ['services', 'service'], true)) {
            return 'services';
        }

        if (in_array($normalized, ['contact', 'contact us'], true)) {
            return 'contact';
        }

        if (in_array($normalized, ['blog', 'news', 'articles'], true)) {
            return 'blog';
        }

        if (in_array($normalized, ['shop', 'store'], true)) {
            return 'shop';
        }

        return 'standard';
    }

    private function describe_page_kind(string $kind, string $title): string {
        switch ($kind) {
            case 'home':
                return __('Primary landing surface for the project. This should usually establish the tone, hero, and core sections first.', 'livecanvas-forge-ai');
            case 'about':
                return __('Brand credibility page with story, positioning, and trust elements.', 'livecanvas-forge-ai');
            case 'services':
                return __('Service overview page with offer structure, differentiators, and supporting CTA sections.', 'livecanvas-forge-ai');
            case 'contact':
                return __('Conversion/support page for contact details, form, location, or booking CTA.', 'livecanvas-forge-ai');
            case 'blog':
                return __('Editorial entry point that usually needs archive and single template support.', 'livecanvas-forge-ai');
            case 'shop':
                return __('Commerce landing page that should align with WooCommerce catalogue and product flows.', 'livecanvas-forge-ai');
            default:
                return sprintf(__('General site page planned from the Genesis brief: %s.', 'livecanvas-forge-ai'), $title);
        }
    }
}
