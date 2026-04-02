<?php

defined('ABSPATH') || exit;

final class LCFA_Command_Deck {
    private LCFA_Environment $environment;
    private LCFA_Inventory $inventory;

    public function __construct(LCFA_Environment $environment, LCFA_Inventory $inventory) {
        $this->environment = $environment;
        $this->inventory   = $inventory;
    }

    public function get_actions(): array {
        return [
            'site_audit' => [
                'label'       => __('Run site audit', 'livecanvas-forge-ai'),
                'description' => __('Returns the current stack summary and LiveCanvas inventory without writing.', 'livecanvas-forge-ai'),
            ],
            'create_page' => [
                'label'       => __('Create LiveCanvas page', 'livecanvas-forge-ai'),
                'description' => __('Creates a page in draft or publish state and enables LiveCanvas on it.', 'livecanvas-forge-ai'),
            ],
            'update_page' => [
                'label'       => __('Update page', 'livecanvas-forge-ai'),
                'description' => __('Updates an existing page with new HTML content.', 'livecanvas-forge-ai'),
            ],
            'update_header' => [
                'label'       => __('Update header partial', 'livecanvas-forge-ai'),
                'description' => __('Writes content into the LiveCanvas header partial.', 'livecanvas-forge-ai'),
            ],
            'update_footer' => [
                'label'       => __('Update footer partial', 'livecanvas-forge-ai'),
                'description' => __('Writes content into the LiveCanvas footer partial.', 'livecanvas-forge-ai'),
            ],
            'create_dynamic_template' => [
                'label'       => __('Create dynamic template', 'livecanvas-forge-ai'),
                'description' => __('Creates a new LiveCanvas dynamic template entry.', 'livecanvas-forge-ai'),
            ],
            'update_dynamic_template' => [
                'label'       => __('Update dynamic template', 'livecanvas-forge-ai'),
                'description' => __('Updates an existing LiveCanvas dynamic template.', 'livecanvas-forge-ai'),
            ],
        ];
    }

    public function execute(array $payload): array {
        $action    = sanitize_key($payload['action'] ?? '');
        $dry_run   = !empty($payload['dry_run']);
        $title     = sanitize_text_field($payload['title'] ?? '');
        $slug      = sanitize_title($payload['slug'] ?? '');
        $status    = sanitize_key($payload['status'] ?? 'draft');
        $target_id = absint($payload['target_id'] ?? 0);
        $variant   = sanitize_text_field($payload['variant'] ?? '1');
        $content   = wp_unslash((string) ($payload['content'] ?? ''));

        if (!isset($this->get_actions()[$action])) {
            return $this->error_result(__('Unsupported command action.', 'livecanvas-forge-ai'));
        }

        if (!$this->environment->is_livecanvas_active()) {
            return $this->error_result(__('LiveCanvas must be active before the Command Deck can write targets.', 'livecanvas-forge-ai'));
        }

        if (!in_array($status, ['draft', 'publish', 'private', 'pending'], true)) {
            $status = 'draft';
        }

        $result = [
            'ok'            => true,
            'action'        => $action,
            'mode'          => $dry_run ? 'preview' : 'apply',
            'message'       => '',
            'summary'       => '',
            'target_type'   => '',
            'target_id'     => 0,
            'target_title'  => '',
            'diff_html'     => '',
            'existing_html' => '',
            'proposed_html' => $content,
            'inventory'     => null,
        ];

        switch ($action) {
            case 'site_audit':
                $inventory            = $this->inventory->get_inventory();
                $result['message']    = __('Site audit prepared.', 'livecanvas-forge-ai');
                $result['summary']    = sprintf(
                    __('Inventory: %1$d pages, %2$d headers, %3$d footers, %4$d dynamic templates.', 'livecanvas-forge-ai'),
                    $inventory['summary']['pages'],
                    $inventory['summary']['headers'],
                    $inventory['summary']['footers'],
                    $inventory['summary']['dynamic_templates']
                );
                $result['target_type'] = 'audit';
                $result['inventory']   = $inventory;
                break;

            case 'create_page':
                if ($title === '') {
                    return $this->error_result(__('A page title is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type'] = 'page';
                $result['target_title'] = $title;
                $result['summary'] = sprintf(__('Create LiveCanvas page "%s".', 'livecanvas-forge-ai'), $title);
                $result['diff_html'] = $this->build_diff('', $content);

                if (!$dry_run) {
                    $post_id = wp_insert_post([
                        'post_type'    => 'page',
                        'post_title'   => $title,
                        'post_name'    => $slug !== '' ? $slug : '',
                        'post_status'  => $status,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($post_id)) {
                        return $this->error_result($post_id->get_error_message());
                    }

                    update_post_meta($post_id, '_lc_livecanvas_enabled', '1');

                    $result['target_id'] = (int) $post_id;
                    $result['message']   = __('LiveCanvas page created.', 'livecanvas-forge-ai');
                }
                break;

            case 'update_page':
                if (!$target_id) {
                    return $this->error_result(__('A target page ID is required.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content('page', $target_id);

                if (!$existing['post']) {
                    return $this->error_result(__('The requested page target was not found.', 'livecanvas-forge-ai'));
                }

                $result['target_type']   = 'page';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'];
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update page #%d.', 'livecanvas-forge-ai'), $target_id);

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
                    }

                    update_post_meta($target_id, '_lc_livecanvas_enabled', '1');

                    $result['message'] = __('Page updated.', 'livecanvas-forge-ai');
                }
                break;

            case 'update_header':
            case 'update_footer':
                $flag      = $action === 'update_header' ? 'is_header' : 'is_footer';
                $target_id = $this->inventory->resolve_partial_post_id($flag, $variant);

                if (!$target_id) {
                    return $this->error_result(__('The requested partial target was not found.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content($action === 'update_header' ? 'header' : 'footer', $target_id, $variant);

                $result['target_type']   = $action === 'update_header' ? 'header' : 'footer';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'] ?? '';
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update %s variant %s.', 'livecanvas-forge-ai'), $result['target_type'], $variant);

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
                    }

                    $result['message'] = $action === 'update_header'
                        ? __('Header partial updated.', 'livecanvas-forge-ai')
                        : __('Footer partial updated.', 'livecanvas-forge-ai');
                }
                break;

            case 'create_dynamic_template':
                if ($title === '') {
                    return $this->error_result(__('A dynamic template title is required.', 'livecanvas-forge-ai'));
                }

                $result['target_type']  = 'dynamic_template';
                $result['target_title'] = $title;
                $result['summary']      = sprintf(__('Create dynamic template "%s".', 'livecanvas-forge-ai'), $title);
                $result['diff_html']    = $this->build_diff('', $content);

                if (!$dry_run) {
                    $post_id = wp_insert_post([
                        'post_type'    => 'lc_dynamic_template',
                        'post_title'   => $title,
                        'post_name'    => $slug !== '' ? $slug : '',
                        'post_status'  => $status,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($post_id)) {
                        return $this->error_result($post_id->get_error_message());
                    }

                    $result['target_id'] = (int) $post_id;
                    $result['message']   = __('Dynamic template created.', 'livecanvas-forge-ai');
                }
                break;

            case 'update_dynamic_template':
                if (!$target_id) {
                    return $this->error_result(__('A dynamic template ID is required.', 'livecanvas-forge-ai'));
                }

                $existing = $this->inventory->get_target_content('dynamic_template', $target_id);

                if (!$existing['post']) {
                    return $this->error_result(__('The requested dynamic template was not found.', 'livecanvas-forge-ai'));
                }

                $result['target_type']   = 'dynamic_template';
                $result['target_id']     = $target_id;
                $result['target_title']  = $existing['post']['title'];
                $result['existing_html'] = $existing['content'];
                $result['diff_html']     = $this->build_diff($existing['content'], $content);
                $result['summary']       = sprintf(__('Update dynamic template #%d.', 'livecanvas-forge-ai'), $target_id);

                if (!$dry_run) {
                    $updated = wp_update_post([
                        'ID'           => $target_id,
                        'post_content' => $content,
                    ], true);

                    if (is_wp_error($updated)) {
                        return $this->error_result($updated->get_error_message());
                    }

                    $result['message'] = __('Dynamic template updated.', 'livecanvas-forge-ai');
                }
                break;
        }

        LCFA_Settings::append_history([
            'time'         => current_time('mysql', true),
            'action'       => $result['action'],
            'mode'         => $result['mode'],
            'ok'           => $result['ok'],
            'message'      => $result['message'],
            'summary'      => $result['summary'],
            'target_type'  => $result['target_type'],
            'target_id'    => $result['target_id'],
            'target_title' => $result['target_title'],
        ]);

        return $result;
    }

    private function build_diff(string $existing, string $proposed): string {
        if (function_exists('wp_text_diff')) {
            return (string) wp_text_diff(
                $existing,
                $proposed,
                [
                    'title_left'       => __('Current', 'livecanvas-forge-ai'),
                    'title_right'      => __('Proposed', 'livecanvas-forge-ai'),
                    'show_split_view'  => false,
                ]
            );
        }

        return '';
    }

    private function error_result(string $message): array {
        return [
            'ok'            => false,
            'action'        => '',
            'mode'          => 'preview',
            'message'       => $message,
            'summary'       => '',
            'target_type'   => '',
            'target_id'     => 0,
            'target_title'  => '',
            'diff_html'     => '',
            'existing_html' => '',
            'proposed_html' => '',
            'inventory'     => null,
        ];
    }
}
