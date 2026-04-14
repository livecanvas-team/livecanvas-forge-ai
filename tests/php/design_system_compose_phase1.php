<?php

declare(strict_types=1);

require_once __DIR__ . '/design_system_apply_phase1.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-preview.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';

function lcfa_compose_service(): LCFA_Design_System_Compose {
    $environment = new LCFA_Environment();
    $windpress_bridge = new LCFA_WindPress_Bridge($environment);
    $theme_files_bridge = new LCFA_Theme_Files_Bridge($environment);
    $local_mcp_bridge = new LCFA_Local_MCP_Bridge($environment);
    $apply = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            $windpress_bridge,
            $theme_files_bridge,
            new LCFA_Design_System_Build_Gateway($local_mcp_bridge)
        )
    );

    return new LCFA_Design_System_Compose(
        $environment,
        new LCFA_Design_System_Picostrap_Composer(),
        $apply,
        new LCFA_Design_System_Preview()
    );
}

function test_picostrap_compose_preview(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a bold, vibrant, slightly premium Bootstrap design system with bright pink, electric blue, rounded buttons, and expressive headings.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Compose preview should succeed for Picostrap');
    lcfa_assert_same('design_system_compose', $result['action'], 'Compose should expose the action name');
    lcfa_assert_same('preview', $result['mode'], 'Compose must stay preview-only');
    lcfa_assert_same('picostrap', $result['target_stack'], 'Compose should resolve Picostrap');
    lcfa_assert_same('design_system_apply', $result['apply_payload']['action'], 'Compose should emit an apply payload');
    lcfa_assert_same('http://localhost:8887/?lcfa_design_preview=1', $result['preview_url'], 'Preview should expose the design preview board URL');
    lcfa_assert_true(isset($result['preview']['palette']['primary']), 'Preview should expose a primary color');
    lcfa_assert_true(isset($result['preview']['buttons']['btn_border_radius']), 'Preview should expose button shape');
    lcfa_assert_true(is_array(get_option(LCFA_Design_System_Preview::OPTION_KEY, [])), 'Compose should persist the preview payload for the preview board');
}

function test_unsupported_concepts_are_warned_and_dropped(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a premium system with an accent color, card shadows, and soft motion.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Compose should still succeed with partial support');
    lcfa_assert_true(!empty($result['warnings']), 'Unsupported concepts should generate warnings');
    lcfa_assert_true(!isset($result['apply_payload']['colors']['accent']), 'Unsupported accent token must not leak into apply payload');
}

function test_vague_prompt_fails_cleanly(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'make it nice',
    ]);

    lcfa_assert_true(empty($result['ok']), 'Overly vague prompt should fail');
    lcfa_assert_true(stripos((string) $result['message'], 'more direction') !== false, 'Failure should ask for more direction');
}

function test_compose_roundtrip_into_apply(): void {
    $compose = lcfa_compose_service();

    $preview = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with warm body background, pill buttons, and bold display headings.',
    ]);

    $environment = new LCFA_Environment();
    $apply = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            new LCFA_WindPress_Bridge($environment),
            new LCFA_Theme_Files_Bridge($environment),
            new LCFA_Design_System_Build_Gateway(new LCFA_Local_MCP_Bridge($environment))
        )
    );

    $result = $apply->run($preview['apply_payload'], false);

    lcfa_assert_true(!empty($result['ok']), 'Apply should accept the compose payload without translation');
    lcfa_assert_same('picosass_handoff', $result['build_strategy'], 'Compose roundtrip should still use Picostrap handoff');
}

function test_command_deck_exposes_and_executes_design_system_compose(): void {
    $environment = new LCFA_Environment();
    $windpress_bridge = new LCFA_WindPress_Bridge($environment);
    $theme_files_bridge = new LCFA_Theme_Files_Bridge($environment);
    $local_mcp_bridge = new LCFA_Local_MCP_Bridge($environment);
    $apply = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            $windpress_bridge,
            $theme_files_bridge,
            new LCFA_Design_System_Build_Gateway($local_mcp_bridge)
        )
    );
    $compose = new LCFA_Design_System_Compose(
        $environment,
        new LCFA_Design_System_Picostrap_Composer(),
        $apply,
        new LCFA_Design_System_Preview()
    );

    $deck = new LCFA_Command_Deck(
        $environment,
        new LCFA_Inventory($environment),
        $windpress_bridge,
        $theme_files_bridge,
        $local_mcp_bridge,
        new LCFA_Remote_Client(),
        $apply,
        $compose
    );

    lcfa_assert_true(isset($deck->get_actions()['design_system_compose']), 'Command deck should expose design_system_compose');

    $result = $deck->execute([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with rounded buttons and expressive headings.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Command deck should execute design_system_compose');
    lcfa_assert_same('preview', $result['mode'], 'Command deck should preserve preview mode for compose');
    lcfa_assert_same('design_system_apply', $result['apply_payload']['action'], 'Command deck should return an apply-ready payload');
}

function test_unsupported_stack_fails_cleanly(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picowind',
        'prompt' => 'Create a vibrant design system.',
    ]);

    lcfa_assert_true(empty($result['ok']), 'Picowind should be rejected in the first slice');
    lcfa_assert_same([], $result['apply_payload'], 'Unsupported stacks must not return an apply payload');
}

function test_apply_payload_contains_only_supported_picostrap_buckets(): void {
    $compose = lcfa_compose_service();
    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with rounded buttons and display headings.',
    ]);

    lcfa_assert_same(
        ['action', 'framework', 'colors', 'typography', 'radius', 'buttons'],
        array_keys($result['apply_payload']),
        'Compose should expose a stable apply payload shape'
    );
}

function test_auto_apply_returns_preview_and_compiler_urls(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a bold, vibrant premium design system with rounded buttons and expressive headings.',
        'auto_apply' => true,
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Auto-apply should succeed');
    lcfa_assert_same('design_system_compose', $result['action'], 'Auto-apply should preserve compose action');
    lcfa_assert_same('apply', $result['mode'], 'Auto-apply should switch the mode to apply');
    lcfa_assert_same('http://localhost:8887/?lcfa_design_preview=1', $result['preview_url'], 'Auto-apply should expose the preview board URL');
    lcfa_assert_same('http://localhost:8887/', $result['frontend_url'], 'Auto-apply should mirror the preview URL in frontend_url');
    lcfa_assert_same('http://localhost:8887/?compile_sass=1&sass_nocache=1', $result['compile_url'], 'Auto-apply should expose the compiler URL');
    lcfa_assert_true(isset($result['preview']['palette']['primary']), 'Auto-apply should keep the preview payload');
    lcfa_assert_same('#ff2d55', get_theme_mod('SCSSvar_primary', ''), 'Auto-apply should write Picostrap theme mods');
}

function run_all_tests(): void {
    test_picostrap_compose_preview();
    test_unsupported_concepts_are_warned_and_dropped();
    test_vague_prompt_fails_cleanly();
    test_compose_roundtrip_into_apply();
    test_command_deck_exposes_and_executes_design_system_compose();
    test_unsupported_stack_fails_cleanly();
    test_apply_payload_contains_only_supported_picostrap_buckets();
    test_auto_apply_returns_preview_and_compiler_urls();
    echo "PASS\n";
}

run_all_tests();
