<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Files_Bridge {
    private const DEFAULT_LIST_LIMIT = 250;
    private const READABLE_EXTENSIONS = ['.css', '.html', '.js', '.json', '.latte', '.md', '.php', '.scss', '.svg', '.twig', '.txt', '.xml', '.yml', '.yaml'];
    private const WRITABLE_EXTENSIONS = ['.css', '.html', '.js', '.json', '.latte', '.md', '.php', '.scss', '.twig', '.txt', '.xml', '.yml', '.yaml'];
    private const TEMPLATE_EXTENSIONS = ['.html', '.latte', '.php', '.twig'];
    private const TEMPLATE_DIRECTORIES = ['views', 'templates', 'partials', 'page-templates', 'loops', 'livecanvas'];
    private const BLOCKED_SEGMENTS = ['.git', '.github', 'node_modules', 'vendor'];
    private const BLOCKED_PREFIXES = ['public/build/'];

    private LCFA_Environment $environment;

    public function __construct(LCFA_Environment $environment) {
        $this->environment = $environment;
    }

    public function get_theme_roots(): array {
        $theme      = wp_get_theme();
        $stylesheet = (string) $theme->get_stylesheet();
        $template   = (string) $theme->get_template();

        if ($stylesheet === '') {
            throw new RuntimeException(__('Unable to resolve the active stylesheet.', 'livecanvas-forge-ai'));
        }

        $stylesheet_root = get_stylesheet_directory();
        $template_root   = get_template_directory();

        if (!is_dir($stylesheet_root)) {
            throw new RuntimeException(sprintf(__('Stylesheet theme directory not found: %s', 'livecanvas-forge-ai'), $stylesheet_root));
        }

        if (!is_dir($template_root)) {
            throw new RuntimeException(sprintf(__('Template theme directory not found: %s', 'livecanvas-forge-ai'), $template_root));
        }

        $roots = [
            [
                'key'   => 'stylesheet',
                'label' => $stylesheet,
                'path'  => $stylesheet_root,
            ],
        ];

        if ($template !== '' && $template !== $stylesheet) {
            $roots[] = [
                'key'   => 'template',
                'label' => $template,
                'path'  => $template_root,
            ];
        }

        return [
            'ok'                => true,
            'wp_root'           => untrailingslashit(ABSPATH),
            'themes_root'       => get_theme_root($stylesheet),
            'backups_directory' => $this->get_backups_directory(),
            'stylesheet'        => $stylesheet,
            'template'          => $template ?: $stylesheet,
            'stylesheet_root'   => $stylesheet_root,
            'template_root'     => $template_root,
            'framework'         => $this->environment->detect_framework_family(),
            'site_mode'         => $this->environment->detect_site_mode(),
            'filesystem_mode'   => 'php-theme-access',
            'is_child_theme'    => $template !== '' && $template !== $stylesheet,
            'roots'             => $roots,
        ];
    }

    public function list_files(array $options = []): array {
        $roots      = $this->get_theme_roots();
        $root_scope = $options['root_scope'] ?? 'active';
        $directory  = $this->sanitize_relative_path((string) ($options['directory'] ?? ''), true);
        $extensions = $this->normalize_extensions($options['extensions'] ?? [], self::READABLE_EXTENSIONS);
        $limit      = $this->normalize_limit($options['limit'] ?? self::DEFAULT_LIST_LIMIT);
        $files      = [];

        foreach ($this->resolve_targets($root_scope, $roots, false) as $root) {
            $base_directory = $directory !== '' ? $this->resolve_absolute_path($root['path'], $directory) : $root['path'];

            if (!is_dir($base_directory)) {
                continue;
            }

            $this->walk_directory($base_directory, $root['path'], function (string $absolute_path, string $relative_path) use (&$files, $limit, $extensions, $root): bool {
                if (count($files) >= $limit) {
                    return false;
                }

                $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
                $extension = $extension !== '' ? '.' . $extension : '';

                if (!in_array($extension, $extensions, true)) {
                    return true;
                }

                $files[] = $this->format_file_descriptor($root, $relative_path, $absolute_path);
                return true;
            });

            if (count($files) >= $limit) {
                break;
            }
        }

        return [
            'ok'         => true,
            'root_scope' => $root_scope,
            'directory'  => $directory,
            'limit'      => $limit,
            'truncated'  => count($files) >= $limit,
            'files'      => $files,
        ];
    }

    public function list_templates(array $options = []): array {
        return $this->list_templates_by_extension('', $options);
    }

    public function list_templates_by_extension(string $extension = '', array $options = []): array {
        $normalized_extension = $extension !== '' ? $this->normalize_template_extension($extension) : '';
        $roots      = $this->get_theme_roots();
        $root_scope = $options['root_scope'] ?? 'active';
        $limit      = $this->normalize_limit($options['limit'] ?? self::DEFAULT_LIST_LIMIT);
        $directories= $this->get_template_directories((string) $roots['framework']);
        $files      = [];

        foreach ($this->resolve_targets($root_scope, $roots, false) as $root) {
            foreach ($directories as $directory) {
                $absolute_directory = $this->resolve_absolute_path($root['path'], $directory);

                if (!is_dir($absolute_directory)) {
                    continue;
                }

                $this->walk_directory($absolute_directory, $root['path'], function (string $absolute_path, string $relative_path) use (&$files, $limit, $root, $normalized_extension): bool {
                    if (count($files) >= $limit) {
                        return false;
                    }

                    $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
                    $extension = $extension !== '' ? '.' . $extension : '';

                    if (!in_array($extension, self::TEMPLATE_EXTENSIONS, true)) {
                        return true;
                    }

                    if ($normalized_extension !== '' && $extension !== $normalized_extension) {
                        return true;
                    }

                    $files[] = $this->format_file_descriptor($root, $relative_path, $absolute_path);
                    return true;
                });

                if (count($files) >= $limit) {
                    break;
                }
            }

            if (count($files) >= $limit) {
                break;
            }
        }

        return [
            'ok'            => true,
            'root_scope'    => $root_scope,
            'template_type' => $normalized_extension !== '' ? ltrim($normalized_extension, '.') : 'all',
            'directories'   => $directories,
            'limit'         => $limit,
            'truncated'     => count($files) >= $limit,
            'files'         => $files,
        ];
    }

    public function read_file(array $options = []): array {
        $roots       = $this->get_theme_roots();
        $root_scope  = $options['root_scope'] ?? 'active';
        $relative_path = $this->sanitize_relative_path((string) ($options['path'] ?? ''));
        $this->assert_allowed_extension($relative_path, self::READABLE_EXTENSIONS, 'read');

        $resolved = $this->resolve_readable_file($root_scope, $relative_path, $roots);
        $content  = (string) file_get_contents($resolved['absolute_path']);

        return [
            'ok'            => true,
            'root_scope'    => $root_scope,
            'root'          => $resolved['root']['key'],
            'theme'         => $resolved['root']['label'],
            'relative_path' => $relative_path,
            'absolute_path' => $resolved['absolute_path'],
            'extension'     => strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION)) ? '.' . strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION)) : '',
            'kind'          => $this->classify_file_kind($relative_path),
            'size'          => filesize($resolved['absolute_path']) ?: 0,
            'modified_at'   => gmdate('c', filemtime($resolved['absolute_path']) ?: time()),
            'content'       => $content,
        ];
    }

    public function read_template_file(array $options = []): array {
        $relative_path = $this->sanitize_relative_path((string) ($options['path'] ?? ''));
        $this->normalize_template_extension((string) pathinfo($relative_path, PATHINFO_EXTENSION));

        return $this->read_file(array_merge($options, [
            'path' => $relative_path,
        ]));
    }

    public function write_file(array $options = []): array {
        $roots            = $this->get_theme_roots();
        $root_scope       = $options['root_scope'] ?? 'stylesheet';
        $relative_path    = $this->sanitize_relative_path((string) ($options['path'] ?? ''));
        $content          = is_string($options['content'] ?? null) ? $options['content'] : (string) ($options['content'] ?? '');
        $dry_run          = !empty($options['dry_run']);
        $record_audit     = !array_key_exists('record_audit', $options) || !empty($options['record_audit']);
        $create_directories = !array_key_exists('create_directories', $options) || !empty($options['create_directories']);

        $this->assert_allowed_extension($relative_path, self::WRITABLE_EXTENSIONS, 'write');
        $this->assert_writable_path($relative_path);

        $root          = $this->resolve_write_target($root_scope, $roots);
        $write_policy  = $this->get_write_root_policy($root, $roots, $options);
        $absolute_path = $this->resolve_absolute_path($root['path'], $relative_path);
        $exists        = file_exists($absolute_path) && is_file($absolute_path);
        $previous      = $exists ? (string) file_get_contents($absolute_path) : '';
        $changed       = !$exists || $previous !== $content;
        $created       = !$exists;

        if (!$write_policy['writable']) {
            if (!$dry_run) {
                throw new RuntimeException((string) $write_policy['message']);
            }

            return [
                'ok'            => true,
                'dry_run'       => true,
                'writable'      => false,
                'blocked'       => true,
                'status'        => 'parent_theme_read_only',
                'message'       => (string) $write_policy['message'],
                'root_scope'    => $root_scope,
                'root'          => $root['key'],
                'theme'         => $root['label'],
                'relative_path' => $relative_path,
                'absolute_path' => $absolute_path,
                'exists'        => $exists,
                'created'       => $created,
                'changed'       => $changed,
                'bytes_before'  => strlen($previous),
                'bytes_after'   => strlen($content),
            ];
        }

        if ($dry_run) {
            return [
                'ok'           => true,
                'dry_run'      => true,
                'root_scope'   => $root_scope,
                'root'         => $root['key'],
                'theme'        => $root['label'],
                'relative_path'=> $relative_path,
                'absolute_path'=> $absolute_path,
                'exists'       => $exists,
                'created'      => $created,
                'changed'      => $changed,
                'bytes_before' => strlen($previous),
                'bytes_after'  => strlen($content),
            ];
        }

        if ($create_directories) {
            wp_mkdir_p(dirname($absolute_path));
        }

        $backup_file = null;
        if ($exists) {
            $backup_file = $this->create_backup($root, $relative_path, $previous);
        }

        if ($changed) {
            $written = file_put_contents($absolute_path, $content);
            if ($written === false) {
                throw new RuntimeException(sprintf(__('Unable to write theme file: %s', 'livecanvas-forge-ai'), $relative_path));
            }
        }

        $result = [
            'ok'           => true,
            'dry_run'      => false,
            'writable'     => true,
            'root_scope'   => $root_scope,
            'root'         => $root['key'],
            'theme'        => $root['label'],
            'relative_path'=> $relative_path,
            'absolute_path'=> $absolute_path,
            'exists'       => true,
            'created'      => $created,
            'changed'      => $changed,
            'backup_file'  => $backup_file,
            'backup_id'    => $backup_file !== null ? $this->get_backup_id_from_path($backup_file) : '',
            'checksum_before' => $exists ? hash('sha256', $previous) : '',
            'checksum_after'  => hash('sha256', $content),
            'bytes_before' => strlen($previous),
            'bytes_after'  => strlen($content),
            'modified_at'  => gmdate('c', filemtime($absolute_path) ?: time()),
        ];

        if ($record_audit && $changed) {
            $result = $this->attach_write_audit($result);
        } else {
            $result['audit_id'] = '';
            $result['rollback_available'] = false;
        }

        return $result;
    }

    public function write_template_file(array $options = []): array {
        $relative_path = $this->sanitize_relative_path((string) ($options['path'] ?? ''));
        $this->normalize_template_extension((string) pathinfo($relative_path, PATHINFO_EXTENSION));

        return $this->write_file(array_merge($options, [
            'path' => $relative_path,
        ]));
    }

    public function list_backups(array $options = []): array {
        $limit              = $this->normalize_limit($options['limit'] ?? 20);
        $filter_path        = $this->sanitize_relative_path((string) ($options['path'] ?? ''), true);
        $filter_kind        = sanitize_key((string) ($options['kind'] ?? ''));
        $backups_directory  = $this->get_backups_directory();
        $backup_files       = [];
        $descriptors        = [];

        if (!is_dir($backups_directory)) {
            return [
                'ok'                => true,
                'backups_directory' => $backups_directory,
                'limit'             => $limit,
                'truncated'         => false,
                'backups'           => [],
            ];
        }

        $this->collect_backup_files($backups_directory, $backup_files);

        foreach ($backup_files as $backup_file) {
            $descriptor = $this->describe_backup_file($backup_file, $backups_directory);

            if ($filter_path !== '' && (string) ($descriptor['relative_path'] ?? '') !== $filter_path) {
                continue;
            }

            if ($filter_kind !== '' && (string) ($descriptor['kind'] ?? '') !== $filter_kind) {
                continue;
            }

            $descriptors[] = $descriptor;
        }

        usort($descriptors, static function (array $left, array $right): int {
            return ((int) ($right['_timestamp'] ?? 0)) <=> ((int) ($left['_timestamp'] ?? 0));
        });

        $truncated = count($descriptors) > $limit;
        $descriptors = array_slice($descriptors, 0, $limit);

        foreach ($descriptors as &$descriptor) {
            unset($descriptor['_timestamp']);
        }
        unset($descriptor);

        return [
            'ok'                => true,
            'backups_directory' => $backups_directory,
            'limit'             => $limit,
            'truncated'         => $truncated,
            'backups'           => $descriptors,
        ];
    }

    public function read_backup(array $options = []): array {
        $backup_id     = $this->sanitize_relative_path((string) ($options['backup_id'] ?? $options['id'] ?? ''));
        $absolute_path = $this->resolve_backup_absolute_path($backup_id);
        $descriptor    = $this->describe_backup_file($absolute_path, $this->get_backups_directory());
        $content       = (string) file_get_contents($absolute_path);

        unset($descriptor['_timestamp']);
        $descriptor['content'] = $content;

        return $descriptor;
    }

    public function restore_backup(array $options = []): array {
        $backup       = $this->read_backup($options);
        $requested_root = sanitize_key((string) ($options['root_scope'] ?? ''));
        $root_scope   = in_array($requested_root, ['stylesheet', 'template', 'active', 'all'], true)
            ? $requested_root
            : (string) ($backup['root'] ?? 'stylesheet');
        $relative_path = $this->sanitize_relative_path((string) ($options['path'] ?? ($backup['relative_path'] ?? '')));
        $dry_run       = !empty($options['dry_run']);

        if ($relative_path === '') {
            throw new RuntimeException(__('Unable to infer the original theme file path from the selected backup.', 'livecanvas-forge-ai'));
        }

        $current_file = null;

        try {
            $current_file = $this->read_file([
                'root_scope' => $root_scope === 'all' ? 'active' : $root_scope,
                'path'       => $relative_path,
            ]);
        } catch (Throwable $throwable) {
            $current_file = null;
        }

        $write_result = $this->write_file([
            'root_scope'         => $root_scope,
            'path'               => $relative_path,
            'content'            => (string) ($backup['content'] ?? ''),
            'dry_run'            => $dry_run,
            'create_directories' => !array_key_exists('create_directories', $options) || !empty($options['create_directories']),
            'record_audit'       => !array_key_exists('record_audit', $options) || !empty($options['record_audit']),
        ]);

        $write_result['restored_from_backup'] = [
            'backup_id'     => (string) ($backup['backup_id'] ?? ''),
            'created_at'    => (string) ($backup['created_at'] ?? ''),
            'relative_path' => (string) ($backup['relative_path'] ?? ''),
            'root'          => (string) ($backup['root'] ?? ''),
            'theme'         => (string) ($backup['theme'] ?? ''),
            'kind'          => (string) ($backup['kind'] ?? ''),
            'bytes'         => (int) ($backup['bytes'] ?? 0),
        ];
        $write_result['current_file'] = $current_file ? [
            'exists'         => true,
            'root'           => (string) ($current_file['root'] ?? ''),
            'theme'          => (string) ($current_file['theme'] ?? ''),
            'relative_path'  => (string) ($current_file['relative_path'] ?? ''),
            'absolute_path'  => (string) ($current_file['absolute_path'] ?? ''),
            'size'           => (int) ($current_file['size'] ?? 0),
            'modified_at'    => (string) ($current_file['modified_at'] ?? ''),
        ] : [
            'exists' => false,
        ];

        return $write_result;
    }

    public function rollback_write(array $options = []): array {
        $roots          = $this->get_theme_roots();
        $root_scope     = sanitize_key((string) ($options['root_scope'] ?? 'stylesheet'));
        $relative_path  = $this->sanitize_relative_path((string) ($options['path'] ?? $options['relative_path'] ?? ''));
        $target_theme   = sanitize_text_field((string) ($options['target_theme'] ?? ''));
        $backup_id      = sanitize_text_field((string) ($options['backup_id'] ?? ''));
        $created_file   = !empty($options['created_file']);
        $expected_hash  = strtolower(trim((string) ($options['expected_checksum'] ?? '')));
        $dry_run        = !empty($options['dry_run']);
        $force          = !empty($options['force']);

        $this->assert_allowed_extension($relative_path, self::WRITABLE_EXTENSIONS, 'rollback');
        $this->assert_writable_path($relative_path);

        $root = $this->resolve_write_target($root_scope, $roots);
        $write_policy = $this->get_write_root_policy($root, $roots, $options);
        if (empty($write_policy['writable'])) {
            throw new RuntimeException((string) $write_policy['message']);
        }

        if ($target_theme !== '' && $target_theme !== (string) ($root['label'] ?? '')) {
            throw new RuntimeException(sprintf(
                __('Rollback targets theme "%1$s", but the active writable theme is "%2$s". Activate the original theme before retrying.', 'livecanvas-forge-ai'),
                $target_theme,
                (string) ($root['label'] ?? '')
            ));
        }

        $absolute_path = $this->resolve_absolute_path((string) $root['path'], $relative_path);
        $exists = is_file($absolute_path);
        $current_hash = $exists ? hash_file('sha256', $absolute_path) : '';

        if (!$force && $exists && $expected_hash !== '' && !hash_equals($expected_hash, (string) $current_hash)) {
            throw new RuntimeException(__('The theme file changed after the audited write. Review the current file or retry with an explicit force rollback.', 'livecanvas-forge-ai'));
        }

        $operation = $created_file ? 'delete_created_file' : 'restore_backup';
        $result = [
            'ok'                => true,
            'dry_run'           => $dry_run,
            'operation'         => $operation,
            'root_scope'        => $root_scope,
            'root'              => (string) ($root['key'] ?? ''),
            'theme'             => (string) ($root['label'] ?? ''),
            'relative_path'     => $relative_path,
            'absolute_path'     => $absolute_path,
            'exists'            => $exists,
            'expected_checksum' => $expected_hash,
            'current_checksum'  => (string) $current_hash,
        ];

        if ($dry_run) {
            return $result;
        }

        if ($created_file) {
            if ($exists && !unlink($absolute_path)) {
                throw new RuntimeException(sprintf(__('Unable to delete the theme file created by AI Bridge: %s', 'livecanvas-forge-ai'), $relative_path));
            }

            $result['deleted'] = $exists;
            $result['already_missing'] = !$exists;
            return $result;
        }

        if ($backup_id === '') {
            throw new RuntimeException(__('The theme-file rollback record does not contain a backup ID.', 'livecanvas-forge-ai'));
        }

        $result['restore_result'] = $this->restore_backup([
            'backup_id'    => $backup_id,
            'root_scope'   => $root_scope,
            'path'         => $relative_path,
            'record_audit' => false,
        ]);

        return $result;
    }

    private function attach_write_audit(array $result): array {
        $audit_id = $this->create_audit_id();
        $rollback_available = !empty($result['created']) || (string) ($result['backup_id'] ?? '') !== '';
        $audit = [
            'id'                 => $audit_id,
            'created_at'         => current_time('mysql', true),
            'action'             => 'theme_file_write',
            'target_type'        => 'theme_file',
            'target_title'       => sanitize_text_field((string) ($result['relative_path'] ?? '')),
            'rollback_available' => $rollback_available,
        ];

        $result['audit_id'] = $audit_id;
        $result['rollback_available'] = $rollback_available;
        $result['audit'] = $audit;

        if ($rollback_available && class_exists('LCFA_Settings') && method_exists('LCFA_Settings', 'store_rollback_record')) {
            LCFA_Settings::store_rollback_record($audit_id, [
                'audit_id'           => $audit_id,
                'created_at'         => (string) $audit['created_at'],
                'action'             => 'theme_file_write',
                'target_type'        => 'theme_file',
                'target_id'          => 0,
                'target_title'       => (string) $audit['target_title'],
                'rollback_reference' => [
                    'available' => true,
                    'type'      => 'theme_file_write',
                ],
                'restore' => [
                    'type'              => 'theme_file_write',
                    'root_scope'        => sanitize_key((string) ($result['root'] ?? 'stylesheet')),
                    'relative_path'     => sanitize_text_field((string) ($result['relative_path'] ?? '')),
                    'target_theme'      => sanitize_text_field((string) ($result['theme'] ?? '')),
                    'backup_id'         => sanitize_text_field((string) ($result['backup_id'] ?? '')),
                    'created_file'      => !empty($result['created']),
                    'expected_checksum' => sanitize_text_field((string) ($result['checksum_after'] ?? '')),
                ],
            ]);
        }

        return $result;
    }

    private function create_audit_id(): string {
        if (function_exists('wp_generate_password')) {
            return sanitize_key('audit-' . strtolower(wp_generate_password(12, false, false)));
        }

        return sanitize_key('audit-' . substr(md5((string) microtime(true)), 0, 12));
    }

    private function resolve_readable_file(string $root_scope, string $relative_path, array $roots): array {
        foreach ($this->resolve_targets($root_scope, $roots, false) as $root) {
            $absolute_path = $this->resolve_absolute_path($root['path'], $relative_path);

            if (file_exists($absolute_path) && is_file($absolute_path)) {
                return [
                    'root'          => $root,
                    'absolute_path' => $absolute_path,
                ];
            }
        }

        throw new RuntimeException(sprintf(__('Theme file not found inside the allowed roots: %s', 'livecanvas-forge-ai'), $relative_path));
    }

    private function resolve_write_target(string $root_scope, array $roots): array {
        $targets = $this->resolve_targets($root_scope, $roots, true);

        if (!$targets) {
            throw new RuntimeException(__('No writable theme root is available for the requested scope.', 'livecanvas-forge-ai'));
        }

        return $targets[0];
    }

    private function get_write_root_policy(array $root, array $roots, array $options): array {
        $is_child_theme = !empty($roots['is_child_theme']);
        $is_parent_root = ($root['key'] ?? '') === 'template' || !$is_child_theme;
        $allow_parent   = (bool) apply_filters(
            'lcfa_allow_parent_theme_writes',
            false,
            $root,
            $roots,
            $options
        );

        if (!$is_parent_root || $allow_parent) {
            return [
                'writable' => true,
                'message'  => '',
            ];
        }

        return [
            'writable' => false,
            'message'  => __('The active target is a parent theme. Install and activate a child theme before writing files, or explicitly opt in with the lcfa_allow_parent_theme_writes filter.', 'livecanvas-forge-ai'),
        ];
    }

    private function resolve_targets(string $root_scope, array $roots, bool $for_write): array {
        $stylesheet_root = null;
        $template_root   = null;

        foreach ((array) ($roots['roots'] ?? []) as $root) {
            if (($root['key'] ?? '') === 'stylesheet') {
                $stylesheet_root = $root;
            }

            if (($root['key'] ?? '') === 'template') {
                $template_root = $root;
            }
        }

        if (!$template_root) {
            $template_root = $stylesheet_root;
        }

        switch ($root_scope) {
            case 'stylesheet':
                return $stylesheet_root ? [$stylesheet_root] : [];

            case 'template':
                return $template_root ? [$template_root] : [];

            case 'all':
                return $this->unique_targets([$stylesheet_root, $template_root]);

            case 'active':
            default:
                if ($for_write) {
                    return $stylesheet_root ? [$stylesheet_root] : [];
                }

                return $this->unique_targets([$stylesheet_root, $template_root]);
        }
    }

    private function resolve_absolute_path(string $root_path, string $relative_path): string {
        $absolute_path = wp_normalize_path(realpath($root_path) ?: $root_path);
        $candidate     = wp_normalize_path($absolute_path . '/' . ltrim($relative_path, '/'));
        $this->assert_inside_root($candidate, $absolute_path);

        return $candidate;
    }

    private function format_file_descriptor(array $root, string $relative_path, string $absolute_path): array {
        return [
            'root'          => $root['key'],
            'theme'         => $root['label'],
            'relative_path' => $relative_path,
            'absolute_path' => $absolute_path,
            'extension'     => strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION)) ? '.' . strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION)) : '',
            'kind'          => $this->classify_file_kind($relative_path),
            'size'          => filesize($absolute_path) ?: 0,
            'modified_at'   => gmdate('c', filemtime($absolute_path) ?: time()),
        ];
    }

    private function get_template_directories(string $framework): array {
        if ($framework === 'picowind') {
            return ['views', 'page-templates', 'livecanvas'];
        }

        if ($framework === 'picostrap') {
            return ['partials', 'loops', 'page-templates', 'livecanvas'];
        }

        return self::TEMPLATE_DIRECTORIES;
    }

    private function get_backups_directory(): string {
        $uploads = wp_get_upload_dir();
        $base    = !empty($uploads['basedir']) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';

        return wp_normalize_path(trailingslashit($base) . 'livecanvas-forge-ai/backups');
    }

    private function create_backup(array $root, string $relative_path, string $content): string {
        $stamp            = gmdate('Y-m-d\TH-i-s');
        $backup_directory = $this->get_backups_directory() . '/' . gmdate('Y-m-d') . '/' . sanitize_file_name((string) $root['label']);
        $safe_filename    = str_replace(['/', '\\'], '__', $relative_path);
        $backup_path      = $backup_directory . '/' . $stamp . '__' . $safe_filename;

        wp_mkdir_p($backup_directory);
        file_put_contents($backup_path, $content);
        file_put_contents($this->get_backup_metadata_path($backup_path), wp_json_encode([
            'root'          => (string) ($root['key'] ?? ''),
            'theme'         => (string) ($root['label'] ?? ''),
            'relative_path' => $relative_path,
            'kind'          => $this->classify_file_kind($relative_path),
            'created_at'    => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return wp_normalize_path($backup_path);
    }

    private function get_backup_id_from_path(string $backup_path): string {
        $backups_directory = wp_normalize_path($this->get_backups_directory());
        $normalized_path = wp_normalize_path($backup_path);

        if (strpos($normalized_path, trailingslashit($backups_directory)) !== 0) {
            return '';
        }

        return ltrim(substr($normalized_path, strlen($backups_directory)), '/');
    }

    private function collect_backup_files(string $directory, array &$files): void {
        $entries = @scandir($directory);

        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolute_path = wp_normalize_path($directory . '/' . $entry);

            if (is_dir($absolute_path)) {
                $this->collect_backup_files($absolute_path, $files);
                continue;
            }

            if (!is_file($absolute_path) || str_ends_with($absolute_path, '.json')) {
                continue;
            }

            $files[] = $absolute_path;
        }
    }

    private function describe_backup_file(string $absolute_path, string $backups_directory): array {
        $normalized_backups_directory = wp_normalize_path($backups_directory);
        $normalized_path              = wp_normalize_path($absolute_path);
        $metadata                     = $this->read_backup_metadata($absolute_path);
        $backup_id                    = ltrim(str_replace($normalized_backups_directory, '', $normalized_path), '/');
        $relative_path                = (string) ($metadata['relative_path'] ?? '');

        if ($relative_path === '') {
            $relative_path = $this->infer_backup_relative_path($normalized_path);
        }

        $theme = (string) ($metadata['theme'] ?? '');
        if ($theme === '') {
            $segments = explode('/', $backup_id);
            $theme    = isset($segments[1]) ? (string) $segments[1] : '';
        }

        $created_at = (string) ($metadata['created_at'] ?? '');
        $timestamp  = filemtime($normalized_path) ?: time();

        if ($created_at === '') {
            $created_at = gmdate('c', $timestamp);
        } else {
            $parsed_timestamp = strtotime($created_at);
            if ($parsed_timestamp !== false) {
                $timestamp = $parsed_timestamp;
            }
        }

        return [
            'backup_id'      => $backup_id,
            'backup_path'    => $normalized_path,
            'relative_path'  => $relative_path,
            'root'           => (string) ($metadata['root'] ?? ''),
            'theme'          => $theme,
            'kind'           => (string) ($metadata['kind'] ?? ($relative_path !== '' ? $this->classify_file_kind($relative_path) : 'text')),
            'bytes'          => filesize($normalized_path) ?: 0,
            'created_at'     => $created_at,
            'modified_at'    => gmdate('c', filemtime($normalized_path) ?: $timestamp),
            '_timestamp'     => $timestamp,
        ];
    }

    private function resolve_backup_absolute_path(string $backup_id): string {
        $backups_directory = $this->get_backups_directory();
        $absolute_path     = wp_normalize_path($backups_directory . '/' . ltrim($backup_id, '/'));

        $this->assert_inside_root($absolute_path, $backups_directory);

        if (!is_file($absolute_path) || str_ends_with($absolute_path, '.json')) {
            throw new RuntimeException(sprintf(__('Backup file not found: %s', 'livecanvas-forge-ai'), $backup_id));
        }

        return $absolute_path;
    }

    private function get_backup_metadata_path(string $backup_path): string {
        return wp_normalize_path($backup_path . '.json');
    }

    private function read_backup_metadata(string $backup_path): array {
        $metadata_path = $this->get_backup_metadata_path($backup_path);

        if (!is_file($metadata_path)) {
            return [];
        }

        $content = (string) file_get_contents($metadata_path);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function infer_backup_relative_path(string $backup_path): string {
        $filename = basename($backup_path);
        $marker   = strpos($filename, '__');

        if ($marker === false) {
            return '';
        }

        $encoded = substr($filename, $marker + 2);

        return str_replace('__', '/', $encoded);
    }

    private function walk_directory(string $directory, string $root_path, callable $on_file): bool {
        $entries = @scandir($directory);

        if (!is_array($entries)) {
            return true;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::BLOCKED_SEGMENTS, true)) {
                continue;
            }

            $absolute_path = wp_normalize_path($directory . '/' . $entry);
            $relative_path = ltrim(str_replace(wp_normalize_path($root_path), '', $absolute_path), '/');

            if (is_dir($absolute_path)) {
                foreach (self::BLOCKED_PREFIXES as $prefix) {
                    if (str_starts_with($relative_path . '/', $prefix)) {
                        continue 2;
                    }
                }

                if ($this->walk_directory($absolute_path, $root_path, $on_file) === false) {
                    return false;
                }

                continue;
            }

            if (!is_file($absolute_path)) {
                continue;
            }

            if ($on_file($absolute_path, $relative_path) === false) {
                return false;
            }
        }

        return true;
    }

    private function sanitize_relative_path(string $value, bool $allow_empty = false): string {
        $normalized = str_replace('\\', '/', ltrim(trim($value), '/'));

        if ($normalized === '') {
            if ($allow_empty) {
                return '';
            }

            throw new RuntimeException(__('A relative theme file path is required.', 'livecanvas-forge-ai'));
        }

        $normalized = ltrim(wp_normalize_path($normalized), '/');

        if (
            $normalized === '.' ||
            str_contains($normalized, '../') ||
            str_starts_with($normalized, '../') ||
            str_contains($normalized, "\0")
        ) {
            throw new RuntimeException(sprintf(__('Invalid relative path: %s', 'livecanvas-forge-ai'), $value));
        }

        return $normalized;
    }

    private function assert_inside_root(string $absolute_path, string $root_path): void {
        $normalized_root   = wp_normalize_path($root_path);
        $normalized_target = wp_normalize_path($absolute_path);

        if ($normalized_target !== $normalized_root && !str_starts_with($normalized_target, $normalized_root . '/')) {
            throw new RuntimeException(sprintf(__('Path escapes the allowed root: %s', 'livecanvas-forge-ai'), $absolute_path));
        }
    }

    private function assert_allowed_extension(string $relative_path, array $allowed_extensions, string $mode): void {
        $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? '.' . $extension : '';

        if (!in_array($extension, $allowed_extensions, true)) {
            throw new RuntimeException(sprintf(__('Theme file extension not allowed for %1$s: %2$s', 'livecanvas-forge-ai'), $mode, $extension ?: '(none)'));
        }
    }

    private function normalize_template_extension(string $extension): string {
        $normalized = strtolower(trim($extension));
        $normalized = $normalized !== '' && $normalized[0] !== '.' ? '.' . $normalized : $normalized;

        if (!in_array($normalized, self::TEMPLATE_EXTENSIONS, true)) {
            throw new RuntimeException(sprintf(__('Template extension not supported: %s', 'livecanvas-forge-ai'), $extension ?: '(none)'));
        }

        return $normalized;
    }

    private function assert_writable_path(string $relative_path): void {
        $segments = explode('/', $relative_path);

        foreach ($segments as $segment) {
            if (in_array($segment, self::BLOCKED_SEGMENTS, true)) {
                throw new RuntimeException(sprintf(__('Writing inside protected directories is not allowed: %s', 'livecanvas-forge-ai'), $relative_path));
            }
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($relative_path, $prefix)) {
                throw new RuntimeException(sprintf(__('Writing inside protected paths is not allowed: %s', 'livecanvas-forge-ai'), $relative_path));
            }
        }
    }

    private function normalize_extensions($input, array $allowed_extensions): array {
        if (is_string($input)) {
            $source = array_filter(array_map('trim', explode(',', $input)));
        } elseif (is_array($input)) {
            $source = $input;
        } else {
            $source = [];
        }

        if (!$source) {
            return $allowed_extensions;
        }

        $normalized = [];

        foreach ($source as $item) {
            $extension = strtolower((string) $item);
            $extension = $extension !== '' && $extension[0] !== '.' ? '.' . $extension : $extension;

            if (in_array($extension, $allowed_extensions, true)) {
                $normalized[] = $extension;
            }
        }

        return $normalized ?: $allowed_extensions;
    }

    private function normalize_limit($value): int {
        $parsed = absint($value);

        if ($parsed < 1) {
            return self::DEFAULT_LIST_LIMIT;
        }

        return min($parsed, 1000);
    }

    private function classify_file_kind(string $relative_path): string {
        $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? '.' . $extension : '';

        if (in_array($extension, self::TEMPLATE_EXTENSIONS, true)) {
            return 'template';
        }

        if (in_array($extension, ['.css', '.scss'], true)) {
            return 'style';
        }

        if ($extension === '.js') {
            return 'script';
        }

        if (in_array($extension, ['.json', '.yml', '.yaml', '.xml'], true)) {
            return 'config';
        }

        return 'text';
    }

    private function unique_targets(array $roots): array {
        $seen = [];
        $unique = [];

        foreach ($roots as $root) {
            if (!$root || empty($root['path']) || in_array($root['path'], $seen, true)) {
                continue;
            }

            $seen[] = $root['path'];
            $unique[] = $root;
        }

        return $unique;
    }
}
