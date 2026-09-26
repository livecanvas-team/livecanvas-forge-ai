<?php

defined('ABSPATH') || exit;

/** One read-only instruction source for REST, remote Abilities and local MCP. */
final class LCFA_Workflows {
    private const ROOT = __DIR__ . '/../workflows/';

    public static function catalog(): array {
        try {
            $catalog = json_decode(self::read_file('catalog.json'), true, 32, JSON_THROW_ON_ERROR);
            $items = [];
            foreach ($catalog['workflows'] as $item) {
                // Hash the delivered body, so client caches also track shared-rule changes.
                $body = self::body($item['id']);
                $items[] = $item + ['version' => $catalog['version'], 'sha256' => hash('sha256', $body), 'uri' => 'livecanvas://workflows/' . $item['id']];
            }
            return ['ok' => true, 'schema_version' => $catalog['schema_version'], 'version' => $catalog['version'], 'workflows' => $items];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => 'workflows_unavailable', 'message' => 'The installed workflow package is incomplete. Repair the Bridge installation.'];
        }
    }

    public static function read(string $id): array {
        $catalog = self::catalog();
        if (empty($catalog['ok'])) return $catalog;
        foreach ($catalog['workflows'] as $item) {
            // Only IDs from the packaged manifest are readable. No caller-controlled paths.
            if ($item['id'] === $id) return ['ok' => true, 'schema_version' => $catalog['schema_version'], 'workflow' => $item + ['instructions' => self::body($id)]];
        }
        return ['ok' => false, 'code' => 'unknown_workflow', 'message' => 'Use an ID returned by list_workflows.'];
    }

    public static function discovery(): array {
        return ['catalog_tool' => 'list_workflows', 'read_tool' => 'read_workflow', 'remote_catalog_ability' => 'livecanvas-forge-ai/list-workflows',
            'remote_read_ability' => 'livecanvas-forge-ai/read-workflow', 'catalog' => self::catalog(),
            'instruction' => 'Select the relevant workflow and read its body before generating changes. Instructions do not replace fresh write context, authorization or server-side validation.'];
    }

    private static function body(string $id): string {
        if (!preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $id)) throw new RuntimeException('Invalid workflow ID.');
        return self::read_file('shared.md') . "\n\n" . self::read_file($id . '.md');
    }

    private static function read_file(string $file): string {
        $text = @file_get_contents(self::ROOT . $file);
        if ($text === false || trim($text) === '') throw new RuntimeException('Missing workflow file.');
        return $text;
    }
}
