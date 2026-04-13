<?php

defined('ABSPATH') || exit;

final class LCFA_Workspace_Access {
    public static function inspect(string $workspace_root, ?callable $is_dir = null, ?callable $is_writable = null): array {
        $is_dir = $is_dir ?: 'is_dir';
        $is_writable = $is_writable ?: 'is_writable';
        $workspace_root = untrailingslashit(trim($workspace_root));

        if ($workspace_root === '') {
            return [
                'path'      => '',
                'available' => false,
                'reason'    => 'missing',
                'exists'    => false,
            ];
        }

        if (self::is_runtime_workspace_root($workspace_root)) {
            return [
                'path'      => $workspace_root,
                'available' => false,
                'reason'    => 'runtime_only',
                'exists'    => false,
            ];
        }

        if (!self::looks_like_absolute_path($workspace_root)) {
            return [
                'path'      => $workspace_root,
                'available' => false,
                'reason'    => 'not_absolute',
                'exists'    => false,
            ];
        }

        if ((bool) call_user_func($is_dir, $workspace_root)) {
            return [
                'path'      => $workspace_root,
                'available' => (bool) call_user_func($is_writable, $workspace_root),
                'reason'    => (bool) call_user_func($is_writable, $workspace_root) ? 'ready' : 'not_writable',
                'exists'    => true,
            ];
        }

        $parent_directory = dirname($workspace_root);
        $parent_available = $parent_directory !== '' && $parent_directory !== '.' && $parent_directory !== $workspace_root
            && (bool) call_user_func($is_dir, $parent_directory)
            && (bool) call_user_func($is_writable, $parent_directory);

        return [
            'path'      => $workspace_root,
            'available' => $parent_available,
            'reason'    => $parent_available ? 'parent_writable' : 'unreachable',
            'exists'    => false,
        ];
    }

    public static function looks_like_absolute_path(string $path): bool {
        if ($path === '') {
            return false;
        }

        return (bool) preg_match('#^(?:/|[A-Za-z]:[\\\\/])#', $path);
    }

    public static function is_runtime_workspace_root(string $path): bool {
        $path = wp_normalize_path(untrailingslashit($path));

        return in_array($path, [
            '/wordpress',
            '/app',
            '/app/public',
            '/var/www',
            '/var/www/html',
            '/srv/www',
            '/srv/www/html',
            '/usr/share/nginx/html',
        ], true);
    }
}
