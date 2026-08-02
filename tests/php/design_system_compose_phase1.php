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

function test_compose_roundtrip_requires_compiled_bundle(): void {
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

    $apply_preview = $apply->run($preview['apply_payload'], true);
    $result = $apply->run($preview['apply_payload'], false);

    lcfa_assert_true(!empty($apply_preview['ok']), 'The compose payload should pass directly into Picostrap apply preview');
    lcfa_assert_true(empty($result['ok']), 'Direct Picostrap apply should stop until the preview manifest is compiled');
    lcfa_assert_true(!empty($result['build_required']), 'The rejected direct apply should report that a build is required');
    lcfa_assert_same('bridge_dart_sass_transaction', $result['build_strategy'], 'Compose roundtrip should require the guarded MCP compile transaction');
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

function test_direct_auto_apply_stops_before_uncompiled_write(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a bold, vibrant premium design system with rounded buttons and expressive headings.',
        'auto_apply' => true,
    ]);

    lcfa_assert_true(empty($result['ok']), 'Direct PHP auto-apply should stop before an uncompiled write');
    lcfa_assert_same('design_system_compose', $result['action'], 'Rejected auto-apply should preserve the compose action');
    lcfa_assert_same('apply', $result['mode'], 'Rejected auto-apply should report the attempted apply mode');
    lcfa_assert_same('http://localhost:8887/?lcfa_design_preview=1', $result['preview_url'], 'Rejected auto-apply should keep the preview board URL');
    lcfa_assert_true(isset($result['preview']['palette']['primary']), 'Rejected auto-apply should keep the preview payload');
    lcfa_assert_true(!empty($result['data']['apply_result']['build_required']), 'Rejected auto-apply should direct the caller to the MCP compile transaction');
    lcfa_assert_same('', get_theme_mod('SCSSvar_primary', ''), 'Rejected auto-apply must not write Picostrap theme mods');
}

function run_all_tests(): void {
    test_picostrap_compose_preview();
    test_unsupported_concepts_are_warned_and_dropped();
    test_vague_prompt_fails_cleanly();
    test_compose_roundtrip_requires_compiled_bundle();
    test_command_deck_exposes_and_executes_design_system_compose();
    test_unsupported_stack_fails_cleanly();
    test_apply_payload_contains_only_supported_picostrap_buckets();
    test_direct_auto_apply_stops_before_uncompiled_write();
    echo "PASS\n";
}

run_all_tests();
