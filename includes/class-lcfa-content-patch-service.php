<?php

defined('ABSPATH') || exit;

final class LCFA_Content_Patch_Service {
    private LCFA_Inventory $inventory;
    private LCFA_Command_Deck $command_deck;

    public function __construct(LCFA_Inventory $inventory, LCFA_Command_Deck $command_deck) {
        $this->inventory = $inventory;
        $this->command_deck = $command_deck;
    }

    public function preview(array $payload): array {
        return $this->run($payload, true);
    }

    public function apply(array $payload): array {
        return $this->run($payload, false);
    }

    private function run(array $payload, bool $dry_run): array {
        $target = $this->resolve_target($payload);
        if (empty($target['ok'])) {
            return $target;
        }

        $patch = $this->patch_html((string) $target['content'], $payload);
        if (empty($patch['ok'])) {
            return $patch;
        }

        $diff = $this->build_diff((string) $target['content'], (string) $patch['patched_html']);
        $result = [
            'ok' => true,
            'mode' => $dry_run ? 'preview' : 'apply',
            'target_type' => (string) $target['target_type'],
            'target_id' => (int) $target['target_id'],
            'target_title' => (string) ($target['target_title'] ?? ''),
            'operation' => (string) $patch['operation'],
            'match_count' => (int) $patch['match_count'],
            'changed' => (string) $target['content'] !== (string) $patch['patched_html'],
            'existing_html' => (string) $target['content'],
            'patched_html' => (string) $patch['patched_html'],
            'diff_html' => $diff,
            'message' => __('Content patch preview prepared.', 'livecanvas-forge-ai'),
        ];
        $result['framework_validation'] = $this->validate_framework((string) $patch['patched_html'], $payload);

        $validation_data = is_array($result['framework_validation']['data'] ?? null) ? $result['framework_validation']['data'] : [];
        if (!$dry_run && array_key_exists('valid', $validation_data) && empty($validation_data['valid'])) {
            return $this->error((string) ($validation_data['validation_error'] ?? __('Framework validation failed for the patched content.', 'livecanvas-forge-ai')));
        }

        if ($dry_run) {
            return $result;
        }

        $command_payload = [
            'action' => (string) $target['command_action'],
            'target_id' => (int) $target['target_id'],
            'variant' => (string) ($target['variant'] ?? '1'),
            'content' => (string) $patch['patched_html'],
            'dry_run' => false,
            '_lcfa_origin' => sanitize_text_field((string) ($payload['_lcfa_origin'] ?? 'mcp_agent')),
            '_lcfa_transport' => sanitize_text_field((string) ($payload['_lcfa_transport'] ?? 'wordpress_rest')),
            '_lcfa_agent' => sanitize_text_field((string) ($payload['_lcfa_agent'] ?? 'codex')),
            '_lcfa_processed_by' => sanitize_text_field((string) ($payload['_lcfa_processed_by'] ?? 'content_patch_apply')),
            '_lcfa_site_fingerprint' => sanitize_text_field((string) ($payload['_lcfa_site_fingerprint'] ?? '')),
        ];

        if ((string) $target['command_action'] === 'update_page') {
            $command_payload['title'] = (string) ($target['target_title'] ?? '');
        }

        $apply = $this->command_deck->execute($command_payload);
        $apply['content_patch'] = [
            'operation' => (string) $patch['operation'],
            'match_count' => (int) $patch['match_count'],
            'changed' => !empty($result['changed']),
        ];

        return $apply;
    }

    private function validate_framework(string $html, array $payload): array {
        $validation_payload = [
            'action' => 'validate_markup_for_framework',
            'content' => $html,
            'dry_run' => true,
            'framework' => sanitize_key((string) ($payload['framework'] ?? '')),
            'variant' => sanitize_text_field((string) ($payload['variant'] ?? '1')),
        ];

        try {
            return $this->command_deck->execute($validation_payload);
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function resolve_target(array $payload): array {
        $target_type = sanitize_key((string) ($payload['target_type'] ?? 'page'));
        $target_id = absint($payload['target_id'] ?? $payload['post_id'] ?? 0);
        $variant = sanitize_text_field((string) ($payload['variant'] ?? '1'));

        if ($target_type === 'header' || $target_type === 'footer') {
            $flag = $target_type === 'header' ? 'is_header' : 'is_footer';
            if ($target_id <= 0) {
                $target_id = $this->inventory->resolve_partial_post_id($flag, $variant);
            }
            if ($target_id <= 0) {
                return $this->error(__('The requested LiveCanvas global partial was not found.', 'livecanvas-forge-ai'));
            }
            $content = $this->inventory->get_target_content($target_type, $target_id, $variant);
            return [
                'ok' => true,
                'target_type' => $target_type,
                'target_id' => $target_id,
                'target_title' => (string) ($content['post']['title'] ?? ''),
                'variant' => $variant,
                'content' => (string) ($content['content'] ?? ''),
                'command_action' => $target_type === 'header' ? 'update_header' : 'update_footer',
            ];
        }

        $map = [
            'page' => ['inventory' => 'page', 'command' => 'update_page'],
            'partial' => ['inventory' => 'partial', 'command' => 'update_partial'],
            'dynamic_template' => ['inventory' => 'dynamic_template', 'command' => 'update_dynamic_template'],
        ];

        if (!isset($map[$target_type])) {
            return $this->error(__('Unsupported content patch target type.', 'livecanvas-forge-ai'));
        }

        if ($target_id <= 0) {
            return $this->error(__('A target ID is required for content patching.', 'livecanvas-forge-ai'));
        }

        $content = $this->inventory->get_target_content($map[$target_type]['inventory'], $target_id, $variant);
        if (empty($content['post'])) {
            return $this->error(__('The requested content patch target was not found.', 'livecanvas-forge-ai'));
        }

        return [
            'ok' => true,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'target_title' => (string) ($content['post']['title'] ?? ''),
            'variant' => $variant,
            'content' => (string) ($content['content'] ?? ''),
            'command_action' => $map[$target_type]['command'],
        ];
    }

    private function patch_html(string $html, array $payload): array {
        $operation = sanitize_key((string) ($payload['operation'] ?? 'replace_html'));
        $allow_multiple = !empty($payload['allow_multiple']);

        if ($operation === 'replace_text' || trim((string) ($payload['search'] ?? '')) !== '') {
            $search = (string) ($payload['search'] ?? '');
            $replacement = (string) ($payload['replacement'] ?? $payload['content'] ?? $payload['html'] ?? '');
            if ($search === '') {
                return $this->error(__('A search string is required for text replacement.', 'livecanvas-forge-ai'));
            }
            $count = substr_count($html, $search);
            if ($count === 0) {
                return $this->error(__('The requested text was not found.', 'livecanvas-forge-ai'));
            }
            if ($count > 1 && !$allow_multiple) {
                return $this->error(sprintf(__('The requested text appears %d times. Refine the patch target.', 'livecanvas-forge-ai'), $count));
            }

            return [
                'ok' => true,
                'operation' => 'replace_text',
                'match_count' => $count,
                'patched_html' => str_replace($search, $replacement, $html),
            ];
        }

        $selector = trim((string) ($payload['selector'] ?? ''));
        if ($selector === '' && trim((string) ($payload['livecanvas_block'] ?? '')) !== '') {
            $block = preg_replace('/[^A-Za-z0-9_\-:.]/', '', (string) $payload['livecanvas_block']);
            $selector = '[data-lc-block="' . $block . '"], [lc-block="' . $block . '"], #' . $block;
        }
        if ($selector === '') {
            return $this->error(__('A selector or search string is required for content patching.', 'livecanvas-forge-ai'));
        }

        $document = $this->load_fragment_document($html);
        $xpath = new DOMXPath($document);
        $query = $this->selector_to_xpath($selector);
        if ($query === '') {
            return $this->error(__('Unsupported selector. Use #id, .class, tag, tag.class, tag#id, or [attr=value].', 'livecanvas-forge-ai'));
        }

        $nodes = $xpath->query($query);
        $count = $nodes instanceof DOMNodeList ? $nodes->length : 0;
        if ($count === 0) {
            return $this->error(__('The requested selector did not match any element.', 'livecanvas-forge-ai'));
        }
        if ($count > 1 && !$allow_multiple) {
            return $this->error(sprintf(__('The requested selector matched %d elements. Refine the selector before applying.', 'livecanvas-forge-ai'), $count));
        }

        $html_fragment = (string) ($payload['html'] ?? $payload['replacement'] ?? $payload['content'] ?? '');
        $attribute = sanitize_key((string) ($payload['attribute'] ?? ''));
        $value = (string) ($payload['value'] ?? '');

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($operation === 'set_attribute') {
                if ($attribute === '') {
                    return $this->error(__('An attribute name is required for set_attribute.', 'livecanvas-forge-ai'));
                }
                $node->setAttribute($attribute, $value);
                continue;
            }
            if ($operation === 'append_html') {
                $this->append_fragment($document, $node, $html_fragment);
                continue;
            }
            if ($operation === 'prepend_html') {
                $this->prepend_fragment($document, $node, $html_fragment);
                continue;
            }
            if ($operation === 'replace_outer_html') {
                $this->replace_node_with_fragment($document, $node, $html_fragment);
                continue;
            }
            $this->replace_children_with_fragment($document, $node, $html_fragment);
        }

        return [
            'ok' => true,
            'operation' => $operation,
            'match_count' => $count,
            'patched_html' => $this->document_inner_html($document),
        ];
    }

    private function load_fragment_document(string $html): DOMDocument {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="lcfa-patch-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function selector_to_xpath(string $selector): string {
        $selector = trim($selector);
        if (strpos($selector, ',') !== false) {
            $parts = array_filter(array_map('trim', explode(',', $selector)));
            $queries = array_filter(array_map([$this, 'selector_to_xpath'], $parts));
            return $queries ? implode(' | ', $queries) : '';
        }
        if (preg_match('/^#([A-Za-z][A-Za-z0-9_\-:.]*)$/', $selector, $matches)) {
            return '//*[@id=' . $this->xpath_literal($matches[1]) . ']';
        }
        if (preg_match('/^\.([A-Za-z][A-Za-z0-9_\-]*)$/', $selector, $matches)) {
            return '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $matches[1] . ' ")]';
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)$/', $selector, $matches)) {
            return '//' . strtolower($matches[1]);
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)#([A-Za-z][A-Za-z0-9_\-:.]*)$/', $selector, $matches)) {
            return '//' . strtolower($matches[1]) . '[@id=' . $this->xpath_literal($matches[2]) . ']';
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\.([A-Za-z][A-Za-z0-9_\-]*)$/', $selector, $matches)) {
            return '//' . strtolower($matches[1]) . '[contains(concat(" ", normalize-space(@class), " "), " ' . $matches[2] . ' ")]';
        }
        if (preg_match('/^\[([A-Za-z_:][A-Za-z0-9_:\-\.]*)(?:=(["\']?)(.*?)\2)?\]$/', $selector, $matches)) {
            $attr = $matches[1];
            if (!isset($matches[3]) || $matches[3] === '') {
                return '//*[@' . $attr . ']';
            }
            return '//*[@' . $attr . '=' . $this->xpath_literal($matches[3]) . ']';
        }

        return '';
    }

    private function fragment_nodes(DOMDocument $document, string $html): array {
        $fragment_document = $this->load_fragment_document($html);
        $root = $this->find_patch_root($fragment_document);
        $nodes = [];
        if (!$root) {
            return $nodes;
        }
        foreach ($root->childNodes as $child) {
            $nodes[] = $document->importNode($child, true);
        }

        return $nodes;
    }

    private function replace_children_with_fragment(DOMDocument $document, DOMElement $node, string $html): void {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        foreach ($this->fragment_nodes($document, $html) as $child) {
            $node->appendChild($child);
        }
    }

    private function replace_node_with_fragment(DOMDocument $document, DOMElement $node, string $html): void {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        foreach ($this->fragment_nodes($document, $html) as $child) {
            $parent->insertBefore($child, $node);
        }
        $parent->removeChild($node);
    }

    private function append_fragment(DOMDocument $document, DOMElement $node, string $html): void {
        foreach ($this->fragment_nodes($document, $html) as $child) {
            $node->appendChild($child);
        }
    }

    private function prepend_fragment(DOMDocument $document, DOMElement $node, string $html): void {
        $first = $node->firstChild;
        foreach ($this->fragment_nodes($document, $html) as $child) {
            $first ? $node->insertBefore($child, $first) : $node->appendChild($child);
        }
    }

    private function document_inner_html(DOMDocument $document): string {
        $root = $this->find_patch_root($document);
        if (!$root) {
            return '';
        }
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function find_patch_root(DOMDocument $document): ?DOMElement {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@id="lcfa-patch-root"]');
        if ($nodes instanceof DOMNodeList && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
            return $nodes->item(0);
        }

        $first = $document->documentElement;
        return $first instanceof DOMElement ? $first : null;
    }

    private function xpath_literal(string $value): string {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        $quoted = array_map(static function (string $part): string {
            return "'" . $part . "'";
        }, $parts);

        return 'concat(' . implode(', "\'", ', $quoted) . ')';
    }

    private function build_diff(string $existing, string $proposed): string {
        if (function_exists('wp_text_diff')) {
            return (string) wp_text_diff($existing, $proposed, [
                'title_left' => __('Before', 'livecanvas-forge-ai'),
                'title_right' => __('After', 'livecanvas-forge-ai'),
            ]);
        }

        return $existing === $proposed ? '' : "--- Before\n+++ After\n";
    }

    private function error(string $message): array {
        return [
            'ok' => false,
            'message' => $message,
        ];
    }
}
