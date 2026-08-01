<?php

declare(strict_types=1);

$wp_root = rtrim((string) getenv('LCFA_WP_ROOT'), '/');
if ($wp_root === '' || !is_readable($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "LCFA_WP_ROOT must point to a readable WordPress installation.\n");
    exit(1);
}

require $wp_root . '/wp-load.php';

wp_set_current_user(1);

if (!function_exists('wp_get_ability')) {
    fwrite(STDERR, "WordPress Abilities API is unavailable.\n");
    exit(1);
}

function houseflow_fixture(string $name): string {
    $path = __DIR__ . '/' . $name;
    if (!is_readable($path)) {
        throw new RuntimeException('Missing fixture: ' . $name);
    }

    return (string) file_get_contents($path);
}

function houseflow_json_fixture(string $name): array {
    $decoded = json_decode(houseflow_fixture($name), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON fixture: ' . $name);
    }

    return $decoded;
}

function houseflow_execute(string $name, array $input): array {
    $ability = wp_get_ability($name);
    if (!$ability) {
        throw new RuntimeException('Ability not registered: ' . $name);
    }

    $result = $ability->execute($input);
    if (is_wp_error($result)) {
        throw new RuntimeException($name . ': ' . $result->get_error_message());
    }
    if (!is_array($result)) {
        throw new RuntimeException($name . ': unexpected result type.');
    }

    return $result;
}

function houseflow_result(array $result, string $key): array {
    $value = $result[$key] ?? null;
    if (!is_array($value)) {
        throw new RuntimeException('Missing result key: ' . $key);
    }
    if (array_key_exists('ok', $value) && empty($value['ok'])) {
        throw new RuntimeException((string) ($value['message'] ?? ($key . ' failed.')));
    }

    return $value;
}

function houseflow_substitute(string $content, array $values): string {
    return str_replace(array_keys($values), array_values($values), $content);
}

function houseflow_audit_id(array $result): string {
    return sanitize_key((string) (
        $result['audit_id']
        ?? $result['data']['audit']['id']
        ?? $result['data']['audit_id']
        ?? ''
    ));
}

function houseflow_upload(string $path, string $filename, string $title, string $alt): array {
    if (!is_readable($path)) {
        throw new RuntimeException('Generated asset is not readable: ' . $path);
    }

    $result = houseflow_execute('livecanvas-forge-ai/media-upload', [
        'source_type' => 'base64',
        'base64'      => base64_encode((string) file_get_contents($path)),
        'mime_type'   => 'image/png',
        'filename'    => $filename,
        'title'       => $title,
        'alt'         => $alt,
    ]);

    return houseflow_result($result, 'media_upload');
}

function houseflow_existing_media(array $stored, string $key): ?array {
    $attachment_id = absint($stored[$key]['attachment_id'] ?? 0);
    if ($attachment_id < 1 || get_post_type($attachment_id) !== 'attachment') {
        return null;
    }

    return [
        'attachment_id' => $attachment_id,
        'url'           => (string) wp_get_attachment_url($attachment_id),
    ];
}

function houseflow_upsert_sample_post(string $slug, string $title, string $excerpt, string $content, int $thumbnail_id): int {
    $existing = get_posts([
        'post_type'      => 'post',
        'post_status'    => ['publish', 'draft'],
        'posts_per_page' => 1,
        'meta_key'       => '_lcfa_houseflow_demo_post',
        'meta_value'     => $slug,
    ]);

    $post_data = [
        'post_type'    => 'post',
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
        'post_status'  => 'publish',
    ];

    if ($existing) {
        $post_data['ID'] = (int) $existing[0]->ID;
        $post_id = wp_update_post($post_data, true);
    } else {
        $post_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($post_id)) {
        throw new RuntimeException($post_id->get_error_message());
    }

    update_post_meta((int) $post_id, '_lcfa_houseflow_demo_post', $slug);
    set_post_thumbnail((int) $post_id, $thumbnail_id);

    return (int) $post_id;
}

$report = [
    'started_at' => gmdate('c'),
    'site_url'   => home_url('/'),
    'framework'  => 'picostrap',
    'steps'      => [],
];

try {
    $design_system = houseflow_json_fixture('design-system.json');
    $design_preview = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/preview-design-system', $design_system),
        'result'
    );
    $design_apply = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/apply-design-system', $design_system),
        'result'
    );
    $report['steps']['design_system'] = [
        'preview_ok'    => !empty($design_preview['ok']),
        'apply_ok'      => !empty($design_apply['ok']),
        'changed_keys'  => (array) ($design_apply['changed_keys'] ?? []),
        'build_required'=> !empty($design_apply['build_required']),
    ];

    foreach ([
        'sass/_theme_variables.scss' => 'theme-variables.scss',
        'sass/_custom.scss'          => 'custom.scss',
    ] as $theme_path => $fixture) {
        $content = houseflow_fixture($fixture);
        $preview = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/theme-file-preview-write', [
                'root_scope' => 'stylesheet',
                'path'       => $theme_path,
                'content'    => $content,
            ]),
            'theme_file_preview_write'
        );
        $write = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/theme-file-write', [
                'root_scope' => 'stylesheet',
                'path'       => $theme_path,
                'content'    => $content,
            ]),
            'theme_file_write'
        );
        $report['steps']['theme_files'][$theme_path] = [
            'preview_changed' => !empty($preview['changed']),
            'written'         => !empty($write['ok']),
            'backup_file'     => (string) ($write['backup_file'] ?? ''),
        ];
    }

    $asset_paths = [
        'hero' => (string) (getenv('LCFA_HOUSEFLOW_HERO') ?: __DIR__ . '/assets/hearthline-hero.png'),
        'meal' => (string) (getenv('LCFA_HOUSEFLOW_MEAL') ?: __DIR__ . '/assets/hearthline-meal-planning.png'),
        'routines' => (string) (getenv('LCFA_HOUSEFLOW_ROUTINES') ?: __DIR__ . '/assets/hearthline-routines.png'),
    ];
    $stored_media = (array) get_option('lcfa_houseflow_media', []);
    $media = [];
    foreach ([
        'hero' => ['hearthline-hero.png', 'Hearthline family morning', 'Family sharing breakfast and planning the day together'],
        'meal' => ['hearthline-meal-planning.png', 'Hearthline meal planning', 'Parent and child preparing a meal plan together'],
        'routines' => ['hearthline-routines.png', 'Hearthline family routines', 'Family preparing school bags and a weekly routine board'],
    ] as $key => $metadata) {
        $media[$key] = houseflow_existing_media($stored_media, $key);
        if (!$media[$key]) {
            $media[$key] = houseflow_upload($asset_paths[$key], $metadata[0], $metadata[1], $metadata[2]);
        }
        $stored_media[$key] = [
            'attachment_id' => absint($media[$key]['attachment_id'] ?? 0),
            'url'           => (string) ($media[$key]['url'] ?? ''),
        ];
    }
    update_option('lcfa_houseflow_media', $stored_media, false);
    $report['steps']['media'] = $stored_media;

    $substitutions = [
        '{{HERO_URL}}'     => (string) $stored_media['hero']['url'],
        '{{MEAL_URL}}'     => (string) $stored_media['meal']['url'],
        '{{ROUTINES_URL}}' => (string) $stored_media['routines']['url'],
    ];

    $shell_payload = [
        'variant'     => '1',
        'framework'   => 'picostrap',
        'header_html' => houseflow_fixture('header.html'),
        'footer_html' => houseflow_fixture('footer.html'),
    ];
    $shell_preview = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/preview-global-shell', $shell_payload),
        'result'
    );
    $shell_apply = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/apply-global-shell', $shell_payload),
        'result'
    );
    $report['steps']['global_shell'] = [
        'preview_ok' => !empty($shell_preview['ok']),
        'apply_ok'   => !empty($shell_apply['ok']),
        'parts'      => (array) ($shell_apply['data']['parts'] ?? []),
        'audit_id'   => houseflow_audit_id($shell_apply),
    ];

    $existing_pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => 1,
        'meta_key'       => '_lcfa_houseflow_demo',
        'meta_value'     => 'homepage',
    ]);
    $page_payload = [
        'title'          => 'Hearthline',
        'slug'           => 'hearthline',
        'status'         => 'publish',
        'body_html'      => houseflow_substitute(houseflow_fixture('home.html'), $substitutions),
        'page_css'       => houseflow_fixture('page.css'),
        'page_js'        => houseflow_fixture('page.js'),
        'no_theme_edits' => false,
        'seo'            => [
            'title'       => 'Hearthline — More room for family, less life admin',
            'description' => 'A calm shared place for family schedules, meals, lists and everyday routines.',
            'noindex'     => true,
        ],
        'framework'      => 'picostrap',
    ];
    if ($existing_pages) {
        $page_payload['target_id'] = (int) $existing_pages[0]->ID;
    }

    $page_preview = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/preview-page-upsert', $page_payload),
        'result'
    );
    $page_apply = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/apply-page-upsert', $page_payload),
        'result'
    );
    $page_id = absint($page_apply['target_id'] ?? 0);
    if ($page_id < 1) {
        throw new RuntimeException('Homepage apply did not return a target ID.');
    }
    update_post_meta($page_id, '_lcfa_houseflow_demo', 'homepage');

    $state = (array) get_option('lcfa_houseflow_state', []);
    if (empty($state['baseline_saved'])) {
        $state['baseline_saved'] = true;
        $state['previous_show_on_front'] = (string) get_option('show_on_front', 'posts');
        $state['previous_page_on_front'] = (int) get_option('page_on_front', 0);
        $state['previous_page_for_posts'] = (int) get_option('page_for_posts', 0);
        $state['previous_lc_settings'] = (array) get_option('lc_settings', []);
    }
    $state['homepage_id'] = $page_id;
    update_option('show_on_front', 'page');
    update_option('page_on_front', $page_id);

    $lc_settings = (array) get_option('lc_settings', []);
    $lc_settings['header'] = '1';
    $lc_settings['footerV2'] = '1';
    $lc_settings['enable-dynamic-templating'] = '1';
    update_option('lc_settings', $lc_settings);

    $windpress_main_css_path = trailingslashit(WP_CONTENT_DIR) . 'uploads/windpress/data/main.css';
    $windpress_main_css = is_readable($windpress_main_css_path)
        ? (string) file_get_contents($windpress_main_css_path)
        : '';
    if (strpos($windpress_main_css, '@picowind/tailwind.css') !== false) {
        if (!array_key_exists('previous_windpress_main_css', $state)) {
            $state['previous_windpress_main_css'] = $windpress_main_css;
        }
        $windpress_reset_payload = [
            'action'        => 'windpress_reset_entry',
            'relative_path' => 'main.css',
        ];
        $windpress_reset_preview = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/preview-command', $windpress_reset_payload),
            'result'
        );
        $windpress_reset_apply = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/apply-command', $windpress_reset_payload),
            'result'
        );
        $report['steps']['windpress_residual_cleanup'] = [
            'preview_ok' => !empty($windpress_reset_preview['ok']),
            'apply_ok'   => !empty($windpress_reset_apply['ok']),
            'audit_id'   => houseflow_audit_id($windpress_reset_apply),
            'reason'     => 'Removed a stale Picowind import from the active Picostrap flow.',
        ];
    } else {
        $report['steps']['windpress_residual_cleanup'] = [
            'skipped' => true,
            'reason'  => 'No stale Picowind import was found.',
        ];
    }

    $report['steps']['homepage'] = [
        'preview_ok'   => !empty($page_preview['ok']),
        'apply_ok'     => !empty($page_apply['ok']),
        'page_id'      => $page_id,
        'frontend_url' => (string) ($page_apply['frontend_url'] ?? get_permalink($page_id)),
        'edit_url'     => (string) ($page_apply['edit_url'] ?? get_edit_post_link($page_id, 'raw')),
        'audit_id'     => houseflow_audit_id($page_apply),
    ];

    $existing_journal_pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => 1,
        'meta_key'       => '_lcfa_houseflow_demo',
        'meta_value'     => 'journal',
    ]);
    $journal_payload = [
        'title'          => 'Journal',
        'slug'           => 'journal',
        'status'         => 'publish',
        'body_html'      => '<section class="py-5"><div class="container"><h1 editable="inline">Hearthline Journal</h1></div></section>',
        'no_theme_edits' => true,
        'seo'            => [
            'title'       => 'Hearthline Journal',
            'description' => 'Practical ideas for calmer family planning and shared routines.',
            'noindex'     => true,
        ],
        'framework'      => 'picostrap',
    ];
    if ($existing_journal_pages) {
        $journal_payload['target_id'] = (int) $existing_journal_pages[0]->ID;
    }
    $journal_preview = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/preview-page-upsert', $journal_payload),
        'result'
    );
    $journal_apply = houseflow_result(
        houseflow_execute('livecanvas-forge-ai/apply-page-upsert', $journal_payload),
        'result'
    );
    $journal_id = absint($journal_apply['target_id'] ?? 0);
    if ($journal_id < 1) {
        throw new RuntimeException('Journal apply did not return a target ID.');
    }
    update_post_meta($journal_id, '_lcfa_houseflow_demo', 'journal');
    update_option('page_for_posts', $journal_id);
    $state['journal_id'] = $journal_id;
    $report['steps']['journal'] = [
        'preview_ok'   => !empty($journal_preview['ok']),
        'apply_ok'     => !empty($journal_apply['ok']),
        'page_id'      => $journal_id,
        'frontend_url' => get_permalink($journal_id),
        'audit_id'     => houseflow_audit_id($journal_apply),
    ];

    $template_specs = [
        'single' => [
            'title'      => 'Hearthline Single Post',
            'slug'       => 'hearthline-single-post',
            'content'    => houseflow_fixture('single-post.html'),
            'assignment' => ['target' => 'single', 'post_type' => 'post', 'source' => 'forge'],
        ],
        'archive' => [
            'title'      => 'Hearthline Post Archive',
            'slug'       => 'hearthline-post-archive',
            'content'    => houseflow_fixture('archive-post.html'),
            'assignment' => ['target' => 'global', 'specialty' => 'blog', 'source' => 'forge'],
        ],
    ];
    foreach ($template_specs as $kind => $spec) {
        $existing_templates = get_posts([
            'post_type'      => 'lc_dynamic_template',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'meta_key'       => '_lcfa_houseflow_template',
            'meta_value'     => $kind,
        ]);
        $template_payload = [
            'operation'           => $existing_templates ? 'update' : 'create',
            'title'               => $spec['title'],
            'slug'                => $spec['slug'],
            'status'              => 'publish',
            'content'             => $spec['content'],
            'template_assignment' => $spec['assignment'],
        ];
        if ($existing_templates) {
            $template_payload['target_id'] = (int) $existing_templates[0]->ID;
        }
        $preview_payload = $template_payload;
        $preview_payload['action'] = $existing_templates ? 'update_dynamic_template' : 'create_dynamic_template';
        $template_preview = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/preview-command', $preview_payload),
            'result'
        );
        $template_apply = houseflow_result(
            houseflow_execute('livecanvas-forge-ai/apply-dynamic-template', $template_payload),
            'result'
        );
        $template_id = absint($template_apply['target_id'] ?? 0);
        if ($template_id < 1) {
            throw new RuntimeException('Dynamic template apply did not return a target ID for ' . $kind . '.');
        }
        update_post_meta($template_id, '_lcfa_houseflow_template', $kind);
        $state['templates'][$kind] = $template_id;
        $report['steps']['dynamic_templates'][$kind] = [
            'preview_ok'  => !empty($template_preview['ok']),
            'apply_ok'    => !empty($template_apply['ok']),
            'template_id' => $template_id,
            'native_keys' => (array) ($template_apply['data']['native_template_keys'] ?? []),
            'audit_id'    => houseflow_audit_id($template_apply),
        ];
    }

    $sample_posts = [
        ['a-ten-minute-family-reset', 'A ten-minute family reset for Sunday evening', 'A short weekly ritual that makes Monday easier for everyone.', '<p>Choose one calm moment before the week starts. Look at the calendar together, name the two busiest handovers and decide dinner for the first two nights.</p><h2>Keep the ritual deliberately small</h2><p>The goal is not to plan every hour. It is to make the next few days legible enough that no one person has to carry every detail.</p>', (int) $stored_media['routines']['attachment_id']],
        ['meal-planning-without-the-spreadsheet', 'Meal planning without turning dinner into a spreadsheet', 'A flexible way to decide enough meals without over-planning the week.', '<p>Start with three anchor meals, one leftovers night and one deliberately open evening. Add ingredients only after checking what is already at home.</p><h2>Plan for energy, not perfection</h2><p>Match the simplest meal to the hardest day. A useful plan should reduce decisions when everyone is tired.</p>', (int) $stored_media['meal']['attachment_id']],
        ['shared-routines-children-can-see', 'Shared routines children can actually see', 'Make repeated responsibilities visible without adding more reminders.', '<p>A routine works better when it is short, concrete and placed where the action happens. Use pictures or simple labels, and let children mark progress themselves.</p><h2>Make ownership obvious</h2><p>Each person should know what they own and when it is done. Shared visibility replaces repeated verbal prompting.</p>', (int) $stored_media['hero']['attachment_id']],
    ];
    foreach ($sample_posts as $post_spec) {
        $state['posts'][] = houseflow_upsert_sample_post(...$post_spec);
    }
    $state['posts'] = array_values(array_unique(array_map('absint', (array) $state['posts'])));
    update_option('lcfa_houseflow_state', $state, false);
    $report['steps']['sample_posts'] = $state['posts'];

    $report['completed_at'] = gmdate('c');
    $report['ok'] = true;
    update_option('lcfa_houseflow_last_report', $report, false);
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $throwable) {
    $report['ok'] = false;
    $report['error'] = $throwable->getMessage();
    $report['failed_at'] = gmdate('c');
    update_option('lcfa_houseflow_last_report', $report, false);
    fwrite(STDERR, wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}
