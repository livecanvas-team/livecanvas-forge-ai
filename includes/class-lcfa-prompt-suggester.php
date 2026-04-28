<?php

defined('ABSPATH') || exit;

final class LCFA_Prompt_Suggester {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory) {
        $this->environment = $environment;
        $this->inventory   = $inventory;
    }

    public function suggest(array $payload = []): array {
        $user_prompt = trim((string) ($payload['user_prompt'] ?? ''));

        if ($user_prompt === '') {
            return [
                'ok'      => false,
                'message' => __('A request prompt is required before the plugin can suggest an action.', 'livecanvas-forge-ai'),
            ];
        }

        $prompt = strtolower($user_prompt);
        $section_intent = $this->detect_section_intent($prompt);
        $attachment_count = absint($payload['attachment_count'] ?? 0);
        $attachments = is_array($payload['attachments'] ?? null) ? (array) $payload['attachments'] : [];
        $visual_reference = $this->build_visual_reference_hint($attachments, $attachment_count);
        $selected_section_anchor = $this->sanitize_selected_section_anchor(is_array($payload['selected_section_anchor'] ?? null) ? (array) $payload['selected_section_anchor'] : []);
        $suggested = [
            'action'           => 'site_audit',
            'execution_target' => $this->normalize_execution_target((string) ($payload['execution_target'] ?? 'local')),
            'target_id'        => absint($payload['target_id'] ?? 0),
            'variant'          => sanitize_text_field((string) ($payload['variant'] ?? '1')),
            'title'            => sanitize_text_field((string) ($payload['title'] ?? '')),
            'slug'             => sanitize_title((string) ($payload['slug'] ?? '')),
            'provider_id'      => sanitize_text_field((string) ($payload['provider_id'] ?? '')),
            'relative_path'    => sanitize_text_field((string) ($payload['relative_path'] ?? '')),
            'root_scope'       => $this->normalize_root_scope((string) ($payload['root_scope'] ?? 'stylesheet')),
            'file_path'        => sanitize_text_field((string) ($payload['file_path'] ?? '')),
            'backup_id'        => sanitize_text_field((string) ($payload['backup_id'] ?? '')),
            'status'           => $this->normalize_status((string) ($payload['status'] ?? 'draft')),
            'section_intent'   => $section_intent,
            'section_operation'=> $this->detect_section_operation($prompt, $section_intent),
            'content_strategy' => $section_intent !== '' ? 'section_starter' : '',
        ];

        if ($selected_section_anchor) {
            $suggested['selected_section_anchor'] = $selected_section_anchor;
        }

        if ($visual_reference) {
            $suggested['visual_reference'] = $visual_reference;
        }

        $reasons  = [];
        $warnings = [];
        $score    = 0;
        $post_id  = absint($payload['context_post_id'] ?? $payload['post_id'] ?? 0);
        $post     = $post_id ? get_post($post_id) : null;
        $context_target_type = $post instanceof WP_Post ? $this->detect_context_target_type($post) : '';
        $active_framework = (string) $this->environment->detect_framework_family();

        if ($post instanceof WP_Post && !$suggested['target_id']) {
            $suggested['target_id'] = (int) $post->ID;
        }

        if ($attachment_count > 0) {
            $reasons[] = sprintf(
                $attachment_count === 1
                    ? __('The request includes %d visual reference attachment.', 'livecanvas-forge-ai')
                    : __('The request includes %d visual reference attachments.', 'livecanvas-forge-ai'),
                $attachment_count
            );
            if ($visual_reference) {
                $reasons[] = __('The generated section should use the visual reference for layout density, hierarchy, and spacing.', 'livecanvas-forge-ai');
            }
            $score += 1;
        }

        if ($this->prompt_mentions_remote($prompt)) {
            $suggested['execution_target'] = 'remote';
            $reasons[] = __('The request explicitly mentions a remote target.', 'livecanvas-forge-ai');
            $score += 1;
        }

        $detected_path = $this->extract_file_path($user_prompt);
        if ($detected_path !== '' && $suggested['file_path'] === '') {
            $suggested['file_path'] = $detected_path;
            $reasons[] = sprintf(__('Detected a concrete theme file path in the request: %s.', 'livecanvas-forge-ai'), $detected_path);
            $score += 2;
        }

        $prioritize_page_intent = $this->should_prioritize_page_intent($prompt, $suggested['target_id'], $section_intent);

        if ($this->contains_any($prompt, ['restore', 'rollback', 'revert']) && $this->contains_any($prompt, ['backup', 'previous version'])) {
            $suggested['action'] = 'restore_theme_backup';
            $reasons[] = __('The request asks for a restore or rollback from backup.', 'livecanvas-forge-ai');
            $score += 3;
        } elseif ($this->contains_any($prompt, ['header', 'navigation bar', 'navbar'])) {
            $suggested['action'] = 'update_header';
            $suggested['variant'] = $suggested['variant'] !== '' ? $suggested['variant'] : '1';
            $reasons[] = __('Detected an explicit header/navigation intent.', 'livecanvas-forge-ai');
            $score += 3;
        } elseif ($this->contains_any($prompt, ['footer'])) {
            $suggested['action'] = 'update_footer';
            $suggested['variant'] = $suggested['variant'] !== '' ? $suggested['variant'] : '1';
            $reasons[] = __('Detected an explicit footer intent.', 'livecanvas-forge-ai');
            $score += 3;
        } elseif ($suggested['file_path'] !== '') {
            $suggested['action'] = $this->is_template_path($suggested['file_path']) ? 'write_theme_template' : 'write_theme_file';
            $reasons[] = __('A concrete file path was detected, so the request maps to the fallback theme layer.', 'livecanvas-forge-ai');
            $score += 2;
        } elseif ($this->contains_any($prompt, ['dynamic template', 'archive template', 'single template', 'taxonomy template'])) {
            $suggested['action'] = $this->contains_any($prompt, ['create', 'new', 'generate']) ? 'create_dynamic_template' : 'update_dynamic_template';
            $reasons[] = __('Detected a dynamic template request.', 'livecanvas-forge-ai');
            $score += 2;
        } elseif ($context_target_type === 'header' && $this->looks_like_write_request($prompt)) {
            $suggested['action'] = 'update_header';
            $reasons[] = __('The current editor context is a header partial, so the request maps to update_header.', 'livecanvas-forge-ai');
            $score += 2;
        } elseif ($context_target_type === 'footer' && $this->looks_like_write_request($prompt)) {
            $suggested['action'] = 'update_footer';
            $reasons[] = __('The current editor context is a footer partial, so the request maps to update_footer.', 'livecanvas-forge-ai');
            $score += 2;
        } elseif ($context_target_type === 'dynamic_template' && $this->looks_like_write_request($prompt)) {
            $suggested['action'] = 'update_dynamic_template';
            $reasons[] = __('The current editor context is a dynamic template, so the request maps to update_dynamic_template.', 'livecanvas-forge-ai');
            $score += 2;
        } elseif ($prioritize_page_intent) {
            $suggested['action'] = $this->detect_page_action($prompt, $suggested['target_id']);
            $reasons[] = __('Detected a page-oriented request.', 'livecanvas-forge-ai');
            $score += 2;

            if ($section_intent !== '') {
                $reasons[] = sprintf(__('Detected a %s section request inside the current page context.', 'livecanvas-forge-ai'), $section_intent);
                $score += 2;
            }
        } elseif ($this->contains_any($prompt, ['windpress', 'tailwind', 'theme.json', 'utility classes', 'daisyui'])) {
            $windpress_action = $this->detect_windpress_action($prompt);

            if ($windpress_action !== '') {
                $suggested['action'] = $windpress_action;
                $reasons[] = __('Detected a Tailwind/WindPress runtime request.', 'livecanvas-forge-ai');
                $score += 3;
            }
        } elseif ($this->contains_any($prompt, ['audit', 'inspect', 'analyze', 'review', 'check'])) {
            $suggested['action'] = 'site_audit';
            $reasons[] = __('The request is phrased as an inspection rather than a write.', 'livecanvas-forge-ai');
            $score += 1;
        }

        if ($suggested['action'] === 'restore_theme_backup' && $suggested['backup_id'] === '') {
            $warnings[] = __('A restore was detected, but no backup ID is available yet. Use the backup list to prefill a valid backup before apply.', 'livecanvas-forge-ai');
        }

        if (in_array($suggested['action'], ['write_theme_template', 'write_theme_file'], true) && $suggested['file_path'] === '') {
            $warnings[] = __('A theme fallback write needs a concrete file path.', 'livecanvas-forge-ai');
        }

        if (($suggested['section_operation'] ?? '') === 'after_selected_section' && empty($suggested['selected_section_anchor'])) {
            $warnings[] = __('Insert-after-selected-section was requested, but the editor has not provided a selected-section anchor yet.', 'livecanvas-forge-ai');
        }

        if ($suggested['action'] === 'windpress_scan_provider' && $suggested['provider_id'] === '') {
            $provider_id = $this->extract_provider_id($prompt);

            if ($provider_id !== '') {
                $suggested['provider_id'] = $provider_id;
                $reasons[] = sprintf(__('Detected a provider identifier in the request: %s.', 'livecanvas-forge-ai'), $provider_id);
                $score += 1;
            } else {
                $warnings[] = __('A WindPress provider scan needs a provider ID.', 'livecanvas-forge-ai');
            }
        }

        if ($suggested['action'] === 'windpress_reset_entry' && $suggested['relative_path'] === '') {
            $detected_entry = $this->extract_relative_entry($prompt);

            if ($detected_entry !== '') {
                $suggested['relative_path'] = $detected_entry;
                $reasons[] = sprintf(__('Detected a WindPress entry reference: %s.', 'livecanvas-forge-ai'), $detected_entry);
                $score += 1;
            } else {
                $warnings[] = __('Resetting a WindPress entry requires a relative path such as main.css or tailwind.config.js.', 'livecanvas-forge-ai');
            }
        }

        if (in_array($suggested['action'], ['page_upsert', 'create_page', 'create_dynamic_template'], true) && $suggested['title'] === '') {
            $detected_title = $this->extract_title($user_prompt, $suggested['action']);

            if ($detected_title !== '') {
                $suggested['title'] = $detected_title;
                $reasons[] = sprintf(__('Detected a likely title in the request: %s.', 'livecanvas-forge-ai'), $detected_title);
                $score += 1;
            } else {
                $warnings[] = __('Create actions still need a concrete title before apply.', 'livecanvas-forge-ai');
            }
        }

        if ($post instanceof WP_Post && $suggested['target_id'] === (int) $post->ID) {
            $reasons[] = sprintf(__('Reusing the current editor context target #%d.', 'livecanvas-forge-ai'), (int) $post->ID);
            $score += 1;
        }

        if ($suggested['action'] === 'site_audit' && !$reasons) {
            $reasons[] = __('No specific write target was clear enough, so the safest default is a site audit.', 'livecanvas-forge-ai');
        }

        $preflight = $this->build_preflight_recommendation($suggested, $active_framework);
        $workflow  = $this->build_suggested_workflow($suggested, $preflight);

        if ($preflight) {
            $reasons[] = __('Because Picowind is active, validate the generated markup before page_upsert so Bootstrap-like classes are caught before apply.', 'livecanvas-forge-ai');
            $score += 1;
        }

        return [
            'ok'                => true,
            'message'           => __('Request analyzed.', 'livecanvas-forge-ai'),
            'summary'           => sprintf(__('Suggested action: %s.', 'livecanvas-forge-ai'), $suggested['action']),
            'confidence'        => $this->confidence_from_score($score),
            'reasons'           => $reasons,
            'warnings'          => $warnings,
            'preflight'         => $preflight,
            'workflow'          => $workflow,
            'suggested_payload' => $suggested,
        ];
    }

    private function build_preflight_recommendation(array $suggested, string $active_framework): array {
        if ($active_framework !== 'picowind') {
            return [];
        }

        if (!in_array((string) ($suggested['action'] ?? ''), ['page_upsert', 'create_page', 'update_page'], true)) {
            return [];
        }

        return [
            'action' => 'validate_markup_for_framework',
            'execution_target' => (string) ($suggested['execution_target'] ?? 'local'),
            'payload' => [
                'framework' => $active_framework,
                'execution_target' => (string) ($suggested['execution_target'] ?? 'local'),
            ],
        ];
    }

    private function build_suggested_workflow(array $suggested, array $preflight): array {
        $workflow = [];

        if ($preflight) {
            $workflow[] = [
                'phase' => 'preflight',
                'action' => (string) ($preflight['action'] ?? ''),
                'execution_target' => (string) ($preflight['execution_target'] ?? ($suggested['execution_target'] ?? 'local')),
            ];
        }

        if (!empty($suggested['action'])) {
            $workflow[] = [
                'phase' => 'apply',
                'action' => (string) $suggested['action'],
                'execution_target' => (string) ($suggested['execution_target'] ?? 'local'),
            ];
        }

        return $workflow;
    }

    private function detect_page_action(string $prompt, int $target_id): string {
        return 'page_upsert';
    }

    private function detect_section_intent(string $prompt): string {
        $map = [
            'hero' => ['hero', 'above the fold', 'headline section'],
            'metrics' => ['metrics', 'stats', 'numbers', 'statistiche', 'metriche', 'metrica', 'kpis'],
            'pricing' => ['pricing', 'prezzi', 'price table', 'plans', 'piani'],
            'features' => ['features', 'feature', 'benefits', 'vantaggi', 'usp'],
            'testimonials' => ['testimonials', 'testimonial', 'reviews', 'recensioni', 'social proof'],
            'cta' => ['cta', 'call to action', 'final offer', 'finale'],
            'faq' => ['faq', 'frequently asked questions', 'questions', 'domande frequenti'],
            'team' => ['team', 'members', 'staff', 'people', 'profili'],
            'contact' => ['contact section', 'contact form', 'contact block', 'contatti', 'contact'],
            'logo_cloud' => ['logo cloud', 'client logos', 'partner logos', 'brand strip', 'loghi clienti', 'loghi partner'],
            'comparison' => ['comparison', 'compare', 'versus', 'vs', 'confronto', 'comparazione'],
            'timeline' => ['timeline', 'roadmap', 'process steps', 'milestones', 'cronologia', 'percorso', 'processo'],
        ];

        foreach ($map as $intent => $needles) {
            if ($this->contains_any($prompt, $needles)) {
                return $intent;
            }
        }

        return '';
    }

    private function default_section_operation(string $section_intent): string {
        if ($section_intent === 'hero') {
            return 'prepend';
        }

        if ($section_intent !== '') {
            return 'append';
        }

        return '';
    }

    private function detect_section_operation(string $prompt, string $section_intent): string {
        if ($section_intent === '') {
            return '';
        }

        if ($this->contains_any($prompt, [
            'after selected section',
            'after the selected section',
            'after this section',
            'below selected section',
            'below the selected section',
            'dopo la sezione selezionata',
            'dopo questa sezione',
            'sotto la sezione selezionata',
            'sotto questa sezione',
        ])) {
            return 'after_selected_section';
        }

        if ($this->contains_any($prompt, [
            'before footer',
            'before the footer',
            'prima del footer',
            'before footer of this page',
        ])) {
            return 'before_footer';
        }

        if ($section_intent === 'hero' && $this->contains_any($prompt, [
            'replace',
            'swap',
            'sostituisci',
            'rimpiazza',
            'rifai',
        ])) {
            return 'replace_hero';
        }

        return $this->default_section_operation($section_intent);
    }

    private function build_visual_reference_hint(array $attachments, int $attachment_count): array {
        if ($attachment_count <= 0 && !$attachments) {
            return [];
        }

        $names = [];
        $orientation_counts = [
            'landscape' => 0,
            'portrait'  => 0,
            'square'    => 0,
        ];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $name = sanitize_text_field((string) ($attachment['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }

            $orientation = sanitize_key((string) ($attachment['orientation'] ?? ''));
            $width = absint($attachment['width'] ?? 0);
            $height = absint($attachment['height'] ?? 0);

            if ($orientation === '' && $width > 0 && $height > 0) {
                $orientation = $width > $height ? 'landscape' : ($height > $width ? 'portrait' : 'square');
            }

            if (isset($orientation_counts[$orientation])) {
                $orientation_counts[$orientation]++;
            }
        }

        arsort($orientation_counts);
        $orientation = (string) array_key_first($orientation_counts);
        if (($orientation_counts[$orientation] ?? 0) <= 0) {
            $orientation = 'unknown';
        }

        return [
            'enabled'     => true,
            'count'       => max($attachment_count, count($attachments)),
            'orientation' => $orientation,
            'layout'      => $orientation === 'portrait' ? 'stacked-reference' : ($orientation === 'landscape' ? 'split-reference' : 'reference-aware'),
            'names'       => array_slice(array_values(array_unique($names)), 0, 2),
        ];
    }

    private function sanitize_selected_section_anchor(array $anchor): array {
        if (!$anchor) {
            return [];
        }

        $tag_name = sanitize_key((string) ($anchor['tag_name'] ?? 'section'));
        if (!in_array($tag_name, ['section', 'header', 'footer', 'article'], true)) {
            $tag_name = 'section';
        }

        $section_index = isset($anchor['section_index']) ? (int) $anchor['section_index'] : -1;

        return array_filter([
            'tag_name'      => $tag_name,
            'id'            => $this->sanitize_anchor_token((string) ($anchor['id'] ?? '')),
            'selector'      => sanitize_text_field((string) ($anchor['selector'] ?? '')),
            'class_token'   => $this->sanitize_anchor_token((string) ($anchor['class_token'] ?? '')),
            'section_index' => $section_index >= 0 ? $section_index : null,
            'source'        => sanitize_key((string) ($anchor['source'] ?? '')),
        ], static function ($value): bool {
            return $value !== '' && $value !== null;
        });
    }

    private function sanitize_anchor_token(string $value): string {
        return substr((string) preg_replace('/[^A-Za-z0-9_\-:.]/', '', $value), 0, 96);
    }

    private function detect_context_target_type(WP_Post $post): string {
        if ($post->post_type === 'lc_partial') {
            if (get_post_meta((int) $post->ID, 'is_header', true) === '1') {
                return 'header';
            }

            if (get_post_meta((int) $post->ID, 'is_footer', true) === '1') {
                return 'footer';
            }

            return 'partial';
        }

        if ($post->post_type === 'lc_dynamic_template') {
            return 'dynamic_template';
        }

        if ($post->post_type === 'page') {
            return 'page';
        }

        return sanitize_key((string) $post->post_type);
    }

    private function looks_like_write_request(string $prompt): bool {
        return $this->contains_any($prompt, [
            'create',
            'new',
            'generate',
            'build',
            'make',
            'add',
            'insert',
            'append',
            'update',
            'edit',
            'modify',
            'rewrite',
            'refresh',
            'tighten',
            'refine',
            'replace',
            'adjust',
            'crea',
            'genera',
            'aggiorna',
            'aggiungi',
            'inserisci',
            'metti',
            'fammi',
            'creami',
            'modifica',
            'riscrivi',
            'rifinisci',
        ]);
    }

    private function should_prioritize_page_intent(string $prompt, int $target_id, string $section_intent = ''): bool {
        $mentions_page = $this->contains_any($prompt, ['landing page', 'homepage', 'home page', 'page', 'pagina']);
        $mentions_page_write = $this->contains_any($prompt, ['create', 'new', 'generate', 'build', 'make', 'add', 'insert', 'append', 'update', 'edit', 'modify', 'rewrite', 'refresh', 'crea', 'genera', 'aggiorna', 'aggiungi', 'inserisci', 'metti', 'fammi', 'creami', 'modifica', 'riscrivi']);
        $explicit_windpress_runtime = $this->contains_any($prompt, [
            'build tailwind cache',
            'compile tailwind',
            'rebuild tailwind',
            'generate css',
            'theme.json',
            'flush cache',
            'clear cache',
            'purge cache',
            'scan provider',
            'provider audit',
            'cache css',
            'compiled css',
            'reset main.css',
            'reset tailwind.config.js',
            'reset wizard.css',
            'reset wizard.js',
            'reset entry',
        ]);

        if ($explicit_windpress_runtime) {
            return false;
        }

        if ($mentions_page && ($mentions_page_write || $section_intent !== '')) {
            return true;
        }

        return $target_id > 0 && ($mentions_page_write || $section_intent !== '');
    }

    private function detect_windpress_action(string $prompt): string {
        if ($this->contains_any($prompt, ['build', 'compile', 'rebuild', 'generate css'])) {
            return 'build_windpress_cache';
        }

        if ($this->contains_any($prompt, ['flush cache', 'clear cache', 'purge cache'])) {
            return 'windpress_flush_cache';
        }

        if ($this->contains_any($prompt, ['scan provider', 'scan', 'provider audit'])) {
            return 'windpress_scan_provider';
        }

        if ($this->contains_any($prompt, ['theme.json'])) {
            return 'windpress_store_theme_json';
        }

        if ($this->contains_any($prompt, ['cache css', 'compiled css', 'tailwind css'])) {
            return 'windpress_store_cache_css';
        }

        if ($this->contains_any($prompt, ['reset main.css', 'reset tailwind.config.js', 'reset wizard.css', 'reset wizard.js', 'reset entry'])) {
            return 'windpress_reset_entry';
        }

        return 'windpress_audit';
    }

    private function extract_file_path(string $prompt): string {
        if (!preg_match('/([A-Za-z0-9_\/\.-]+\.(twig|latte|php|html|css|js|json|scss|ya?ml))/i', $prompt, $matches)) {
            return '';
        }

        return sanitize_text_field((string) $matches[1]);
    }

    private function extract_provider_id(string $prompt): string {
        if (!preg_match('/\b([a-z0-9]+(?:-[a-z0-9]+)+)\b/i', $prompt, $matches)) {
            return '';
        }

        return sanitize_text_field((string) $matches[1]);
    }

    private function extract_relative_entry(string $prompt): string {
        if (!preg_match('/\b(main\.css|tailwind\.config\.js|wizard\.css|wizard\.js)\b/i', $prompt, $matches)) {
            return '';
        }

        return sanitize_text_field((string) $matches[1]);
    }

    private function extract_title(string $prompt, string $action): string {
        if (preg_match('/["“”\']([^"“”\']{3,80})["“”\']/u', $prompt, $matches)) {
            return sanitize_text_field((string) $matches[1]);
        }

        if (in_array($action, ['page_upsert', 'create_page'], true)) {
            if ($this->contains_any(strtolower($prompt), ['homepage', 'home page'])) {
                return __('Home', 'livecanvas-forge-ai');
            }

            if ($this->contains_any(strtolower($prompt), ['landing page'])) {
                return __('Landing Page', 'livecanvas-forge-ai');
            }
        }

        return '';
    }

    private function confidence_from_score(int $score): string {
        if ($score >= 5) {
            return 'high';
        }

        if ($score >= 3) {
            return 'medium';
        }

        return 'low';
    }

    private function contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function prompt_mentions_remote(string $prompt): bool {
        return $this->contains_any($prompt, ['remote', 'production', 'staging', 'live site']);
    }

    private function is_template_path(string $path): bool {
        return (bool) preg_match('/\.(twig|latte|php|html)$/i', $path);
    }

    private function normalize_execution_target(string $target): string {
        return in_array($target, ['local', 'remote'], true) ? $target : 'local';
    }

    private function normalize_root_scope(string $root_scope): string {
        return in_array($root_scope, ['stylesheet', 'template', 'active', 'all'], true) ? $root_scope : 'stylesheet';
    }

    private function normalize_status(string $status): string {
        return in_array($status, ['draft', 'publish', 'private', 'pending'], true) ? $status : 'draft';
    }
}
