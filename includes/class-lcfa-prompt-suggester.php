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
        ];

        $reasons  = [];
        $warnings = [];
        $score    = 0;
        $post_id  = absint($payload['context_post_id'] ?? $payload['post_id'] ?? 0);
        $post     = $post_id ? get_post($post_id) : null;
        $active_framework = (string) $this->environment->detect_framework_family();

        if ($post instanceof WP_Post && !$suggested['target_id']) {
            if ($post->post_type === 'page') {
                $suggested['target_id'] = (int) $post->ID;
            } elseif ($post->post_type === 'lc_dynamic_template') {
                $suggested['target_id'] = (int) $post->ID;
            }
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

        $prioritize_page_intent = $this->should_prioritize_page_intent($prompt, $suggested['target_id']);

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
        } elseif ($prioritize_page_intent) {
            $suggested['action'] = $this->detect_page_action($prompt, $suggested['target_id']);
            $reasons[] = __('Detected a page-oriented request.', 'livecanvas-forge-ai');
            $score += 2;
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

    private function should_prioritize_page_intent(string $prompt, int $target_id): bool {
        $mentions_page = $this->contains_any($prompt, ['landing page', 'homepage', 'home page', 'page', 'pagina']);
        $mentions_page_write = $this->contains_any($prompt, ['create', 'new', 'generate', 'build', 'make', 'update', 'edit', 'modify', 'rewrite', 'refresh', 'crea', 'genera', 'aggiorna', 'modifica', 'riscrivi']);
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

        if ($mentions_page && $mentions_page_write) {
            return true;
        }

        return $target_id > 0 && $mentions_page_write;
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
