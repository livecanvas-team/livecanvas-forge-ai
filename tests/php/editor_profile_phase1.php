<?php
declare(strict_types=1);
define('ABSPATH', '/tmp/lcfa-editor-profile-test/');
function lc_get_framework_slug(): string { return $GLOBALS['editor_slug']; }
function __(string $text, string $domain = ''): string { return $text; }
require dirname(__DIR__, 2) . '/includes/class-lcfa-environment.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-write-contract.php';
require dirname(__DIR__, 2) . '/includes/class-lcfa-context-builder.php';
function profile_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
foreach (['daisyui-5', 'bootstrap-5.3', 'custom-profile', ''] as $slug) {
    $GLOBALS['editor_slug'] = $slug;
    $profile = (new LCFA_Environment())->get_editor_profile();
    profile_check($profile['slug'] === $slug && $profile['role'] === 'editor_preset_only', 'Preserve exact editor preset with its scope.');
    profile_check($profile['is_compile_evidence'] === false, 'No preset is build evidence.');
    profile_check($profile['capability_check'] === 'get_write_context.context.pipeline', 'Direct all presets to the same authoritative check.');
    $state = ['framework' => 'picowind', 'pipeline' => ['windpress_active' => true, 'daisyui' => 'unverified', 'typography' => 'unverified']];
    profile_check(str_contains(LCFA_Write_Contract::validate_markup('<a class="btn btn-primary">Open</a>', $state), 'DaisyUI'), 'Preset cannot allow unverified DaisyUI.');
    profile_check(str_contains(LCFA_Write_Contract::validate_markup('<article class="prose">Text</article>', $state), 'Typography'), 'Preset cannot allow unverified Typography.');
    profile_check(LCFA_Write_Contract::validate_markup('<section class="px-4 py-8">Text</section>', $state) === '', 'Plain Tailwind remains available.');
    $state['pipeline']['daisyui'] = 'compiled';
    profile_check(LCFA_Write_Contract::validate_markup('<a class="btn btn-primary">Open</a>', $state) === '', 'Verified compile evidence allows the plugin regardless of preset name.');
}
$reflection = new ReflectionClass(LCFA_Context_Builder::class);
$rules = $reflection->getMethod('get_output_rules')->invoke($reflection->newInstanceWithoutConstructor(), 'unknown');
profile_check(str_contains(implode(' ', $rules['notes']), 'Resolve the effective renderer'), 'Unknown themes must resolve the renderer before choosing a write target.');
profile_check(!str_contains(implode(' ', $rules['notes']), 'target post content first'), 'Unknown themes must not redirect layout writes to editorial content.');
echo "PASS editor preset versus compile evidence\n";
