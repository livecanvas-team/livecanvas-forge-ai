<?php
defined('ABSPATH') || exit;

/** Compare-and-swap updates preserve concurrent revocations and new sessions. */
final class LCFA_Session_Store {
    private const KEY = 'lcfa_mcp_sessions';

    private static function row(): ?array {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) throw new RuntimeException('session_storage_unavailable');
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name=%s", self::KEY), ARRAY_A);
        if ($wpdb->last_error) throw new RuntimeException('session_storage_unavailable');
        return is_array($row) ? $row : null;
    }

    private static function decode(?array $row): array {
        if (!$row) return [];
        $value = @unserialize($row['option_value'], ['allowed_classes' => false]);
        if (!is_array($value)) throw new RuntimeException('session_storage_invalid');
        return $value;
    }

    public static function read(): array { return self::decode(self::row()); }

    public static function mutate(callable $change): array {
        global $wpdb;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $row = self::row();
            if (!$row) {
                // Concurrent initialization is safe because option_name is unique.
                add_option(self::KEY, [], '', false);
                $row = self::row();
                if (!$row) throw new RuntimeException('session_storage_unavailable');
            }
            $current = self::decode($row);
            $updated = $change($current);
            if (!is_array($updated)) throw new RuntimeException('session_storage_invalid');
            $serialized = serialize($updated);
            if ($serialized === $row['option_value']) return $updated;
            $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s, autoload='off' WHERE option_id=%d AND BINARY option_value=BINARY %s", $serialized, $row['option_id'], $row['option_value']));
            if ($changed === false) throw new RuntimeException('session_storage_unavailable');
            if ($changed === 1) {
                wp_cache_delete(self::KEY, 'options');
                wp_cache_delete('alloptions', 'options');
                wp_cache_delete('notoptions', 'options');
                return $updated;
            }
        }
        throw new RuntimeException('session_storage_busy');
    }
}
