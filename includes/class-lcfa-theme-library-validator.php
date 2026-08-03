<?php

defined('ABSPATH') || exit;

final class LCFA_Theme_Library_Validator {
    private const REQUIRED_FILES = [
        'style.css',
        'functions.php',
        'screenshot.jpg',
        'page-templates/empty.php',
        'views/page-templates/empty.twig',
        'livecanvas/configuration.php',
        'public/styles/presets/daisyui.css',
        'public/styles/tailwind.css',
        'starter-data/lcfa-theme.json',
        'starter-data/livecanvas-settings.json',
        'starter-data/design-system.json',
        'starter-data/media-manifest.json',
        'starter-data/menus.json',
        'starter-data/qa-report.json',
    ];

    public function validate_zip(string $zip_path, array $theme = []): array {
        if (!class_exists('ZipArchive')) {
            return $this->error(__('ZipArchive is not available on this server.', 'livecanvas-forge-ai'));
        }

        if (!is_file($zip_path) || !is_readable($zip_path)) {
            return $this->error(__('Theme ZIP is missing or unreadable.', 'livecanvas-forge-ai'));
        }

        $expected_checksum = $this->normalize_checksum((string) ($theme['checksum'] ?? ''));
        $actual_checksum = hash_file('sha256', $zip_path);
        if ($expected_checksum !== '' && !hash_equals(strtolower($expected_checksum), strtolower((string) $actual_checksum))) {
            return $this->error(__('Theme ZIP checksum does not match the catalog.', 'livecanvas-forge-ai'), [
                'expected_checksum' => $expected_checksum,
                'actual_checksum'   => $actual_checksum,
            ]);
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return $this->error(__('Theme ZIP could not be opened.', 'livecanvas-forge-ai'));
        }

        $entries = $this->get_entries($zip);
        $root = $this->detect_root($entries);
        $missing = [];
        foreach (self::REQUIRED_FILES as $relative_path) {
            if (!$this->entry_exists($entries, $root . $relative_path)) {
                $missing[] = $relative_path;
            }
        }

        if ($missing) {
            $zip->close();
            return $this->error(__('Theme ZIP is missing required files.', 'livecanvas-forge-ai'), [
                'missing_files' => $missing,
            ]);
        }

        $style_result = $this->validate_style_css($zip, $root);
        if (empty($style_result['ok'])) {
            $zip->close();
            return $style_result;
        }

        $template_result = $this->validate_page_template_shell($zip, $root);
        if (empty($template_result['ok'])) {
            $zip->close();
            return $template_result;
        }

        $stylesheet_result = $this->validate_stylesheet_imports($zip, $entries, $root);
        if (empty($stylesheet_result['ok'])) {
            $zip->close();
            return $stylesheet_result;
        }

        $manifest_json = $zip->getFromName($root . 'starter-data/lcfa-theme.json');
        $manifest = json_decode(is_string($manifest_json) ? $manifest_json : '', true);
        if (!is_array($manifest)) {
            $zip->close();
            return $this->error(__('Theme manifest JSON is invalid.', 'livecanvas-forge-ai'));
        }

        $manifest_result = $this->validate_manifest($manifest, $entries, $root);
        if (empty($manifest_result['ok'])) {
            $zip->close();
            return $manifest_result;
        }

        $content_result = $this->validate_content_files($zip, $root, $manifest_result['manifest']);
        if (empty($content_result['ok'])) {
            $zip->close();
            return $content_result;
        }

        $media_result = $this->validate_media_manifest($zip, $entries, $root, $manifest_result['manifest']);
        $zip->close();

        if (empty($media_result['ok'])) {
            return $media_result;
        }

        return [
            'ok'              => true,
            'zip_path'        => $zip_path,
            'root'            => $root,
            'checksum'        => $actual_checksum,
            'manifest'        => $manifest_result['manifest'],
            'requires_php'    => (string) ($style_result['requires_php'] ?? ''),
            'required_files'  => self::REQUIRED_FILES,
            'preview_plan'    => $this->build_preview_plan($manifest_result['manifest']),
        ];
    }

    public function extract_zip(string $zip_path, string $destination): array {
        if (!class_exists('ZipArchive')) {
            return $this->error(__('ZipArchive is not available on this server.', 'livecanvas-forge-ai'));
        }

        if (!wp_mkdir_p($destination)) {
            return $this->error(__('Temporary extraction directory could not be created.', 'livecanvas-forge-ai'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return $this->error(__('Theme ZIP could not be opened.', 'livecanvas-forge-ai'));
        }

        $entries = $this->get_entries($zip);
        foreach ($entries as $entry) {
            $normalized = $this->normalize_relative_path($entry);
            if ($normalized === '') {
                $zip->close();
                return $this->error(__('Theme ZIP contains an unsafe path.', 'livecanvas-forge-ai'));
            }
        }

        $ok = $zip->extractTo($destination);
        $zip->close();

        if (!$ok) {
            return $this->error(__('Theme ZIP extraction failed.', 'livecanvas-forge-ai'));
        }

        return [
            'ok'          => true,
            'destination' => $destination,
        ];
    }

    public function read_zip_file(string $zip_path, string $root, string $relative_path): array {
        $safe_path = $this->normalize_relative_path($relative_path);
        if ($safe_path === '') {
            return $this->error(__('Manifest file path is unsafe.', 'livecanvas-forge-ai'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return $this->error(__('Theme ZIP could not be opened.', 'livecanvas-forge-ai'));
        }

        $contents = $zip->getFromName($root . $safe_path);
        $zip->close();

        if (!is_string($contents)) {
            return $this->error(__('Manifest referenced file was not found in the ZIP.', 'livecanvas-forge-ai'));
        }

        return [
            'ok'       => true,
            'path'     => $safe_path,
            'contents' => $contents,
        ];
    }

    public function normalize_relative_path(string $path): string {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                return '';
            }

            $parts[] = $part;
        }

        return $parts ? implode('/', $parts) : '';
    }

    private function validate_manifest(array $manifest, array $entries, string $root): array {
        if ((string) ($manifest['schema'] ?? '') !== 'lcfa-theme.v1') {
            return $this->error(__('Theme manifest schema must be lcfa-theme.v1.', 'livecanvas-forge-ai'));
        }

        $theme = is_array($manifest['theme'] ?? null) ? $manifest['theme'] : [];
        $slug = sanitize_key((string) ($theme['slug'] ?? $manifest['slug'] ?? ''));
        $version = sanitize_text_field((string) ($theme['version'] ?? $manifest['version'] ?? ''));

        if ($slug === '' || $version === '') {
            return $this->error(__('Theme manifest requires theme.slug and theme.version.', 'livecanvas-forge-ai'));
        }

        $manifest['theme']['slug'] = $slug;
        $manifest['theme']['version'] = $version;
        $manifest['theme']['name'] = sanitize_text_field((string) ($theme['name'] ?? $slug));

        foreach (['homepage', 'header', 'footer'] as $section) {
            if (!is_array($manifest[$section] ?? null)) {
                return $this->error(sprintf('Theme manifest requires %s.', $section));
            }

            $content_file = $this->normalize_relative_path((string) ($manifest[$section]['content_file'] ?? ''));
            if ($content_file === '' || !$this->entry_exists($entries, $root . $content_file)) {
                return $this->error(sprintf('Theme manifest %s.content_file is missing or unsafe.', $section));
            }

            $manifest[$section]['content_file'] = $content_file;
        }

        $homepage_template = $this->normalize_relative_path((string) ($manifest['homepage']['template'] ?? ''));
        if ($homepage_template !== '' && $homepage_template !== 'default') {
            if (!$this->entry_exists($entries, $root . $homepage_template)) {
                return $this->error(__('Homepage template file declared in the manifest is missing from the child theme ZIP.', 'livecanvas-forge-ai'), [
                    'template' => $homepage_template,
                ]);
            }

            $manifest['homepage']['template'] = $homepage_template;
        }

        $defaults = [
            'media_manifest'      => 'starter-data/media-manifest.json',
            'menus_file'          => 'starter-data/menus.json',
            'design_system_file'  => 'starter-data/design-system.json',
            'livecanvas_settings' => 'starter-data/livecanvas-settings.json',
            'qa_report'           => 'starter-data/qa-report.json',
        ];

        foreach ($defaults as $key => $default_path) {
            $path = $this->normalize_relative_path((string) ($manifest[$key] ?? $default_path));
            if ($path === '' || !$this->entry_exists($entries, $root . $path)) {
                return $this->error(sprintf('Theme manifest file "%s" is missing or unsafe.', $key));
            }

            $manifest[$key] = $path;
        }

        $css_verification = $manifest['css_verification'] ?? [];
        if (!is_array($css_verification)) {
            return $this->error(__('Theme manifest css_verification must be an object.', 'livecanvas-forge-ai'));
        }

        foreach (['required_fragments', 'forbidden_fragments'] as $key) {
            $fragments = $css_verification[$key] ?? [];
            if (!is_array($fragments)) {
                return $this->error(sprintf('Theme manifest css_verification.%s must be an array.', $key));
            }

            $normalized = [];
            foreach (array_slice($fragments, 0, 20) as $fragment) {
                if (!is_string($fragment)) {
                    return $this->error(sprintf('Theme manifest css_verification.%s entries must be strings.', $key));
                }

                $fragment = trim($fragment);
                if ($fragment === '' || strlen($fragment) > 200 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $fragment)) {
                    return $this->error(sprintf('Theme manifest css_verification.%s contains an invalid CSS fragment.', $key));
                }

                $normalized[] = $fragment;
            }

            $css_verification[$key] = array_values(array_unique($normalized));
        }

        $manifest['css_verification'] = $css_verification;

        return [
            'ok'       => true,
            'manifest' => $manifest,
        ];
    }

    private function validate_style_css(ZipArchive $zip, string $root): array {
        $style = $zip->getFromName($root . 'style.css');
        if (!is_string($style) || trim($style) === '') {
            return $this->error(__('Theme style.css is empty.', 'livecanvas-forge-ai'));
        }

        $template = $this->read_theme_header($style, 'Template');
        if ($template === '' || stripos($template, 'picowind') === false) {
            return $this->error(__('Theme Library packages must be Picowind child themes with a Template header that references Picowind.', 'livecanvas-forge-ai'), [
                'template' => $template,
            ]);
        }

        return [
            'ok'           => true,
            'template'     => $template,
            'requires_php' => $this->read_theme_header($style, 'Requires PHP'),
        ];
    }

    private function validate_page_template_shell(ZipArchive $zip, string $root): array {
        $twig = $zip->getFromName($root . 'views/page-templates/empty.twig');
        if (!is_string($twig) || trim($twig) === '') {
            return $this->error(__('Theme page template view is empty.', 'livecanvas-forge-ai'));
        }

        $required = [
            'wp_head' => 'wp_head',
            'wp_body_open' => 'wp_body_open',
            'main' => '<main',
            'post.content' => 'post.content',
            'wp_footer' => 'wp_footer',
        ];
        $missing = [];
        foreach ($required as $label => $needle) {
            if (strpos($twig, $needle) === false) {
                $missing[] = $label;
            }
        }

        if (strpos($twig, 'lc_custom_header') === false && strpos($twig, 'lc_get_header') === false) {
            $missing[] = 'LiveCanvas header';
        }

        if (strpos($twig, 'lc_custom_footer') === false && strpos($twig, 'lc_get_footer') === false) {
            $missing[] = 'LiveCanvas footer';
        }

        if ($missing) {
            return $this->error(__('Theme page template must render a complete Picowind shell with WordPress hooks, a main content element, and separate LiveCanvas header/footer partials.', 'livecanvas-forge-ai'), [
                'missing_template_calls' => $missing,
            ]);
        }

        return ['ok' => true];
    }

    private function validate_stylesheet_imports(ZipArchive $zip, array $entries, string $root): array {
        foreach ($entries as $entry) {
            if (strpos($entry, $root . 'public/styles/') !== 0 || substr($entry, -4) !== '.css') {
                continue;
            }

            $contents = $zip->getFromName($entry);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            if (!preg_match_all('~@import\s+(?:url\()?\s*["\']([^"\']+)["\']~', $contents, $matches)) {
                continue;
            }

            $relative_entry = substr($entry, strlen($root));
            $base_dir = trim(dirname($relative_entry), '.');
            $base_dir = $base_dir === '' ? '' : trim($base_dir, '/') . '/';

            foreach ((array) ($matches[1] ?? []) as $import_path) {
                $import_path = trim((string) $import_path);
                if ($import_path === '' || preg_match('~^(?:https?:)?//~i', $import_path) || $import_path[0] !== '.') {
                    continue;
                }

                $resolved = $this->normalize_relative_path($base_dir . $import_path);
                if ($resolved === '' || !$this->entry_exists($entries, $root . $resolved)) {
                    return $this->error(__('Theme CSS imports a stylesheet that is missing from the ZIP.', 'livecanvas-forge-ai'), [
                        'stylesheet' => $relative_entry,
                        'import'     => $import_path,
                        'resolved'   => $resolved,
                    ]);
                }
            }
        }

        return ['ok' => true];
    }

    private function validate_content_files(ZipArchive $zip, string $root, array $manifest): array {
        $homepage_path = (string) ($manifest['homepage']['content_file'] ?? '');
        $homepage = $zip->getFromName($root . $homepage_path);
        if (!is_string($homepage)) {
            return $this->error(__('Homepage content file could not be read.', 'livecanvas-forge-ai'));
        }

        if (preg_match('/<\\/?(?:header|footer)\\b/i', $homepage)) {
            return $this->error(__('Homepage content must not contain inline header or footer markup.', 'livecanvas-forge-ai'));
        }

        $header_path = (string) ($manifest['header']['content_file'] ?? '');
        $header = $zip->getFromName($root . $header_path);
        if (!is_string($header) || trim($header) === '') {
            return $this->error(__('Header partial content file could not be read or is empty.', 'livecanvas-forge-ai'));
        }

        if (preg_match('/<\\/?header\\b/i', $header)) {
            return $this->error(__('Header partial content must not include an outer header element because LiveCanvas supplies that shell.', 'livecanvas-forge-ai'));
        }

        $footer_path = (string) ($manifest['footer']['content_file'] ?? '');
        $footer = $zip->getFromName($root . $footer_path);
        if (!is_string($footer) || trim($footer) === '') {
            return $this->error(__('Footer partial content file could not be read or is empty.', 'livecanvas-forge-ai'));
        }

        if (preg_match('/<\\/?footer\\b/i', $footer)) {
            return $this->error(__('Footer partial content must not include an outer footer element because LiveCanvas supplies that shell.', 'livecanvas-forge-ai'));
        }

        return ['ok' => true];
    }

    private function validate_media_manifest(ZipArchive $zip, array $entries, string $root, array $manifest): array {
        $media_path = (string) ($manifest['media_manifest'] ?? 'starter-data/media-manifest.json');
        $media_json = $zip->getFromName($root . $media_path);
        $media_manifest = json_decode(is_string($media_json) ? $media_json : '', true);
        if (!is_array($media_manifest)) {
            return $this->error(__('Media manifest JSON is invalid.', 'livecanvas-forge-ai'));
        }

        $items = [];
        if (isset($media_manifest['items']) && is_array($media_manifest['items'])) {
            $items = $media_manifest['items'];
        } elseif (isset($media_manifest['media']) && is_array($media_manifest['media'])) {
            $items = $media_manifest['media'];
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                return $this->error(sprintf('Media manifest item %d is not an object.', (int) $index));
            }

            $asset_id = sanitize_key((string) ($item['id'] ?? $item['asset_id'] ?? ''));
            $file = $this->normalize_relative_path((string) ($item['file'] ?? ''));
            if ($asset_id === '' || $file === '') {
                return $this->error(sprintf('Media manifest item %d requires id and file.', (int) $index));
            }

            if (strpos($file, 'starter-data/media/') !== 0) {
                return $this->error(sprintf('Media asset "%s" must live under starter-data/media/.', $asset_id));
            }

            if (!$this->entry_exists($entries, $root . $file)) {
                return $this->error(sprintf('Media asset "%s" was not found in the ZIP.', $asset_id), [
                    'asset_id' => $asset_id,
                    'file'     => $file,
                ]);
            }

            $expected_checksum = $this->normalize_checksum((string) ($item['checksum'] ?? $item['sha256'] ?? ''));
            if ($expected_checksum !== '') {
                $contents = $zip->getFromName($root . $file);
                $actual_checksum = hash('sha256', is_string($contents) ? $contents : '');
                if (!hash_equals($expected_checksum, $actual_checksum)) {
                    return $this->error(sprintf('Media asset "%s" checksum does not match.', $asset_id), [
                        'asset_id'          => $asset_id,
                        'expected_checksum' => $expected_checksum,
                        'actual_checksum'   => $actual_checksum,
                    ]);
                }
            }
        }

        return ['ok' => true];
    }

    private function read_theme_header(string $style, string $header): string {
        $pattern = '/^[ \t\\/*#@]*' . preg_quote($header, '/') . ':(.*)$/mi';
        if (!preg_match($pattern, $style, $matches)) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function normalize_checksum(string $checksum): string {
        $checksum = strtolower(trim($checksum));
        $checksum = preg_replace('/^sha256[:=]/', '', $checksum);

        return preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : '';
    }

    private function build_preview_plan(array $manifest): array {
        return [
            'theme' => [
                'slug'    => (string) ($manifest['theme']['slug'] ?? ''),
                'name'    => (string) ($manifest['theme']['name'] ?? ''),
                'version' => (string) ($manifest['theme']['version'] ?? ''),
            ],
            'steps' => [
                'Validate child theme ZIP and manifest.',
                'Install and activate the Picowind child theme.',
                'Import LiveCanvas settings and WindPress design data.',
                'Import media and replace placeholders.',
                'Create or update header and footer partials.',
                'Create or update the LiveCanvas homepage and assign it as front page.',
                'Create menus, flush WindPress and AI Bridge caches.',
                'Store rollback metadata.',
            ],
        ];
    }

    private function get_entries(ZipArchive $zip): array {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && substr($name, -1) !== '/') {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    private function detect_root(array $entries): string {
        if ($this->entry_exists($entries, 'starter-data/lcfa-theme.json')) {
            return '';
        }

        foreach ($entries as $entry) {
            if (substr($entry, -strlen('/starter-data/lcfa-theme.json')) === '/starter-data/lcfa-theme.json') {
                return substr($entry, 0, -strlen('starter-data/lcfa-theme.json'));
            }
        }

        return '';
    }

    private function entry_exists(array $entries, string $path): bool {
        return in_array($path, $entries, true);
    }

    private function error(string $message, array $extra = []): array {
        return array_merge([
            'ok'      => false,
            'message' => $message,
        ], $extra);
    }
}
