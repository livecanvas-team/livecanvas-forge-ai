<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-theme-library-build-gate/');

$GLOBALS['lcfa_build_gate_options'] = [];

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { return strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '', $value)); }
function current_time(string $type, bool $gmt = false): string { return '2026-08-02 10:00:00'; }
function get_option(string $name, $default = false) { return $GLOBALS['lcfa_build_gate_options'][$name] ?? $default; }
function update_option(string $name, $value, bool $autoload = false): bool { $GLOBALS['lcfa_build_gate_options'][$name] = $value; return true; }

final class LCFA_Theme_Library_Installer {}
final class LCFA_Theme_Library_Validator {}

final class LCFA_WindPress_Bridge {
    public array $verification = [
        'ready' => false,
        'status' => 'missing',
        'message' => 'No compiled cache.',
    ];

    public function get_compiled_cache_state(): array {
        return $this->verification;
    }
}

class LCFA_Design_System_Build_Gateway {
    public array $status = [
        'build_available' => false,
        'message' => 'Build unavailable.',
    ];
    public array $result = [
        'ok' => false,
        'message' => 'Build failed.',
    ];

    public function get_status(): array { return $this->status; }
    public function refresh_status(): array { return $this->status; }
    public function build_windpress_cache(array $arguments = []): array { return $this->result; }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-theme-library-importer.php';

function lcfa_build_gate_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_build_gate_seed(string $status = 'build_required'): void {
    $GLOBALS['lcfa_build_gate_options']['lcfa_theme_library_imports'] = [
        'sample-theme' => [
            'slug' => 'sample-theme',
            'status' => $status,
            'audit_id' => 'theme-import-sample-theme-abc123',
            'import_key' => 'sample-theme:1.0.0:checksum',
        ],
    ];
}

$installer = new LCFA_Theme_Library_Installer();
$validator = new LCFA_Theme_Library_Validator();
$windpress = new LCFA_WindPress_Bridge();
$gateway = new LCFA_Design_System_Build_Gateway();
$importer = new LCFA_Theme_Library_Importer($installer, $validator, $windpress, $gateway);

$missing = $importer->build('missing-theme');
lcfa_build_gate_assert(empty($missing['ok']) && ($missing['status'] ?? '') === 'missing_import', 'Build should require an existing starter-data import.');

lcfa_build_gate_seed();
$required = $importer->build('sample-theme');
lcfa_build_gate_assert(empty($required['ok']) && ($required['status'] ?? '') === 'build_required', 'Unavailable local compiler should keep an explicit build-required state.');
lcfa_build_gate_assert(($GLOBALS['lcfa_build_gate_options']['lcfa_theme_library_imports']['sample-theme']['status'] ?? '') === 'build_required', 'Build-required state should be persisted.');

lcfa_build_gate_seed();
$gateway->status = [
    'build_available' => true,
    'local_site' => true,
    'windpress_active' => true,
    'node_available' => true,
    'rest_reachable' => true,
];
$gateway->result = ['ok' => false, 'message' => 'Compiler exploded.'];
$failed = $importer->build('sample-theme');
lcfa_build_gate_assert(empty($failed['ok']) && ($failed['status'] ?? '') === 'build_failed', 'Compiler errors should persist a build-failed state.');

lcfa_build_gate_seed();
$gateway->result = [
    'ok' => true,
    'result' => [
        'tailwind_version' => 4,
        'provider_count' => 2,
        'candidate_count' => 37,
    ],
];
$windpress->verification = [
    'ready' => false,
    'status' => 'missing',
    'message' => 'Compiler returned but cache is missing.',
];
$unverified = $importer->build('sample-theme');
lcfa_build_gate_assert(empty($unverified['ok']) && ($unverified['status'] ?? '') === 'build_failed', 'A successful process without a persistent cache must still fail verification.');

lcfa_build_gate_seed();
$windpress->verification = [
    'ready' => true,
    'status' => 'ready',
    'message' => 'Compiled cache verified.',
    'cache' => [
        'exists' => true,
        'bytes' => 4096,
        'sha256' => str_repeat('a', 64),
    ],
];
$ready = $importer->build('sample-theme');
lcfa_build_gate_assert(!empty($ready['ok']) && !empty($ready['ready']) && ($ready['status'] ?? '') === 'ready', 'Verified persistent CSS should move the import to ready.');
$stored = $GLOBALS['lcfa_build_gate_options']['lcfa_theme_library_imports']['sample-theme'] ?? [];
lcfa_build_gate_assert(($stored['status'] ?? '') === 'ready', 'Ready build state should be persisted.');
lcfa_build_gate_assert((int) ($stored['build']['candidate_count'] ?? 0) === 37, 'Build metadata should retain candidate count without storing the CSS payload.');
lcfa_build_gate_assert(($stored['build']['verification']['cache']['sha256'] ?? '') === str_repeat('a', 64), 'Build metadata should retain cache checksum verification.');

echo "PASS\n";
