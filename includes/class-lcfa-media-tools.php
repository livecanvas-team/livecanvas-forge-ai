<?php

defined('ABSPATH') || exit;

final class LCFA_Media_Tools {
    private LCFA_Command_Deck $command_deck;

    public function __construct(LCFA_Command_Deck $command_deck) {
        $this->command_deck = $command_deck;
    }

    public function upload(array $payload): array {
        $this->load_media_dependencies();

        $source_type = sanitize_key((string) ($payload['source_type'] ?? ''));
        $filename = sanitize_file_name((string) ($payload['filename'] ?? ''));
        $post_id = absint($payload['post_id'] ?? 0);

        if ($filename === '') {
            $filename = 'ai-bridge-media-' . gmdate('Ymd-His') . '.png';
        }

        if ($source_type === '' && trim((string) ($payload['url'] ?? '')) !== '') {
            $source_type = 'url';
        }
        if ($source_type === '' && (trim((string) ($payload['data_url'] ?? '')) !== '' || trim((string) ($payload['base64'] ?? '')) !== '')) {
            $source_type = 'base64';
        }

        if ($source_type === 'url') {
            $result = $this->upload_from_url(esc_url_raw((string) ($payload['url'] ?? '')), $filename, $post_id);
        } elseif ($source_type === 'base64') {
            $result = $this->upload_from_base64($payload, $filename, $post_id);
        } else {
            return $this->error(__('Unsupported media source. Use source_type=url or source_type=base64.', 'livecanvas-forge-ai'));
        }

        if (empty($result['ok'])) {
            return $result;
        }

        $attachment_id = (int) $result['attachment_id'];
        $this->update_attachment_fields($attachment_id, $payload);

        if ($post_id > 0 && !empty($payload['set_featured'])) {
            set_post_thumbnail($post_id, $attachment_id);
            $result['featured_image_set'] = true;
        }

        return array_merge($result, $this->describe_attachment($attachment_id), [
            'message' => __('Media uploaded to the WordPress Media Library.', 'livecanvas-forge-ai'),
        ]);
    }

    public function replace(array $payload): array {
        $target_type = sanitize_key((string) ($payload['target_type'] ?? 'page'));
        $target_id = absint($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $old_url = trim((string) ($payload['old_url'] ?? ''));
        $new_url = trim((string) ($payload['new_url'] ?? ''));
        $attachment_id = absint($payload['attachment_id'] ?? 0);

        if ($new_url === '' && $attachment_id > 0) {
            $new_url = (string) wp_get_attachment_url($attachment_id);
        }
        if ($old_url === '' || $new_url === '') {
            return $this->error(__('Both old_url and new_url or attachment_id are required for media replacement.', 'livecanvas-forge-ai'));
        }
        if ($target_id <= 0) {
            return $this->error(__('A target page, partial, or template ID is required for media replacement.', 'livecanvas-forge-ai'));
        }

        $content = $this->get_post_content($target_id);
        if ($content === null) {
            return $this->error(__('The requested media replacement target was not found.', 'livecanvas-forge-ai'));
        }
        $count = substr_count($content, $old_url);
        if ($count < 1) {
            return $this->error(__('The old media URL was not found in the target content.', 'livecanvas-forge-ai'));
        }

        $patched = str_replace($old_url, $new_url, $content);
        $action = $this->command_action_for_target($target_type);
        if ($action === '') {
            return $this->error(__('Unsupported media replacement target type.', 'livecanvas-forge-ai'));
        }

        $dry_run = !empty($payload['dry_run']);
        $result = $this->command_deck->execute([
            'action' => $action,
            'target_id' => $target_id,
            'variant' => sanitize_text_field((string) ($payload['variant'] ?? '1')),
            'content' => $patched,
            'dry_run' => $dry_run,
            '_lcfa_origin' => sanitize_text_field((string) ($payload['_lcfa_origin'] ?? 'mcp_agent')),
            '_lcfa_transport' => sanitize_text_field((string) ($payload['_lcfa_transport'] ?? 'wordpress_rest')),
            '_lcfa_agent' => sanitize_text_field((string) ($payload['_lcfa_agent'] ?? 'codex')),
            '_lcfa_processed_by' => sanitize_text_field((string) ($payload['_lcfa_processed_by'] ?? 'media_replace')),
            '_lcfa_site_fingerprint' => sanitize_text_field((string) ($payload['_lcfa_site_fingerprint'] ?? '')),
        ]);
        $result['media_replace'] = [
            'old_url' => $old_url,
            'new_url' => $new_url,
            'attachment_id' => $attachment_id,
            'match_count' => $count,
        ];

        return $result;
    }

    private function upload_from_url(string $url, string $filename, int $post_id): array {
        if ($url === '') {
            return $this->error(__('A URL is required for URL media upload.', 'livecanvas-forge-ai'));
        }

        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $this->error($tmp->get_error_message());
        }

        $file = [
            'name' => $filename,
            'tmp_name' => $tmp,
        ];
        $attachment_id = media_handle_sideload($file, $post_id);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $this->error($attachment_id->get_error_message());
        }

        return [
            'ok' => true,
            'attachment_id' => (int) $attachment_id,
            'source_type' => 'url',
        ];
    }

    private function upload_from_base64(array $payload, string $filename, int $post_id): array {
        $raw = trim((string) ($payload['data_url'] ?? $payload['base64'] ?? ''));
        if ($raw === '') {
            return $this->error(__('A data_url or base64 payload is required for base64 media upload.', 'livecanvas-forge-ai'));
        }

        $mime = sanitize_mime_type((string) ($payload['mime_type'] ?? ''));
        if (preg_match('/^data:([^;]+);base64,(.+)$/', $raw, $matches)) {
            $mime = $mime !== '' ? $mime : sanitize_mime_type($matches[1]);
            $raw = $matches[2];
        }
        $bytes = base64_decode($raw, true);
        if ($bytes === false || $bytes === '') {
            return $this->error(__('The base64 media payload could not be decoded.', 'livecanvas-forge-ai'));
        }

        $upload = wp_upload_bits($filename, null, $bytes);
        if (!empty($upload['error'])) {
            return $this->error((string) $upload['error']);
        }

        $file_path = (string) ($upload['file'] ?? '');
        $file_type = wp_check_filetype_and_ext($file_path, basename($file_path));
        $detected_mime = sanitize_mime_type((string) ($file_type['type'] ?? $mime));
        if (!$this->is_allowed_mime($detected_mime)) {
            @unlink($file_path);
            return $this->error(__('The uploaded media type is not allowed.', 'livecanvas-forge-ai'));
        }

        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $detected_mime,
            'post_title' => sanitize_text_field((string) ($payload['title'] ?? pathinfo($filename, PATHINFO_FILENAME))),
            'post_content' => sanitize_textarea_field((string) ($payload['description'] ?? '')),
            'post_excerpt' => sanitize_text_field((string) ($payload['caption'] ?? '')),
            'post_status' => 'inherit',
        ], $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($file_path);
            return $this->error($attachment_id->get_error_message());
        }

        $metadata = wp_generate_attachment_metadata((int) $attachment_id, $file_path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $attachment_id, $metadata);
        }

        return [
            'ok' => true,
            'attachment_id' => (int) $attachment_id,
            'source_type' => 'base64',
        ];
    }

    private function update_attachment_fields(int $attachment_id, array $payload): void {
        $updates = ['ID' => $attachment_id];
        if (isset($payload['title'])) {
            $updates['post_title'] = sanitize_text_field((string) $payload['title']);
        }
        if (isset($payload['caption'])) {
            $updates['post_excerpt'] = sanitize_text_field((string) $payload['caption']);
        }
        if (isset($payload['description'])) {
            $updates['post_content'] = sanitize_textarea_field((string) $payload['description']);
        }
        if (count($updates) > 1) {
            wp_update_post($updates);
        }
        if (isset($payload['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field((string) $payload['alt']));
        }
    }

    private function describe_attachment(int $attachment_id): array {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $url = (string) wp_get_attachment_url($attachment_id);

        return [
            'url' => $url,
            'edit_url' => function_exists('get_edit_post_link') ? (string) get_edit_post_link($attachment_id, 'raw') : '',
            'mime_type' => (string) get_post_mime_type($attachment_id),
            'metadata' => is_array($metadata) ? $metadata : [],
        ];
    }

    private function get_post_content(int $post_id): ?string {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return null;
        }

        return (string) $post->post_content;
    }

    private function command_action_for_target(string $target_type): string {
        if ($target_type === 'page') {
            return 'update_page';
        }
        if ($target_type === 'partial') {
            return 'update_partial';
        }
        if ($target_type === 'dynamic_template') {
            return 'update_dynamic_template';
        }
        if ($target_type === 'header') {
            return 'update_header';
        }
        if ($target_type === 'footer') {
            return 'update_footer';
        }

        return '';
    }

    private function is_allowed_mime(string $mime): bool {
        return strpos($mime, 'image/') === 0 || in_array($mime, ['video/mp4', 'video/webm'], true);
    }

    private function load_media_dependencies(): void {
        if (defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    private function error(string $message): array {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
