<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Wizard_Presenter {
    private const STEP_DEFINITIONS = [
        'choose_client' => [
            'title'  => 'Choose your coding agent',
            'helper' => 'Pick the client',
        ],
        'choose_claude_target' => [
            'title'  => 'Choose Claude connection target',
            'helper' => 'Desktop App or CLI',
        ],
        'choose_mode' => [
            'title'  => 'Choose local or remote',
            'helper' => 'Choose the target',
        ],
        'confirm_details' => [
            'title'  => 'Confirm connection details',
            'helper' => 'Review the inputs',
        ],
        'generate_bundle' => [
            'title'  => 'Generate the client bundle',
            'helper' => 'Create the config',
        ],
        'smoke_test' => [
            'title'  => 'Run the smoke test',
            'helper' => 'Verify the connection',
        ],
    ];

    public function build(array $payload): array {
        $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];
        $bundle = is_array($payload['bundle'] ?? null) ? $payload['bundle'] : [];
        $workspace_access = is_array($payload['workspace_access'] ?? null) ? $payload['workspace_access'] : [];
        $current_step = $this->normalize_step((string) ($state['current_step'] ?? 'choose_client'), $bundle);
        $status = sanitize_key((string) ($state['status'] ?? 'not_connected'));

        if ($status === 'ready') {
            return [
                'mode'        => 'ready',
                'banner'      => $this->build_banner('ready', $bundle, $workspace_access),
                'steps'       => $this->build_steps('ready', $bundle),
                'ready_panel' => $this->build_ready_panel($state, $bundle, $workspace_access),
            ];
        }

        return [
            'mode'              => 'wizard',
            'banner'            => $this->build_banner($current_step, $bundle, $workspace_access),
            'steps'             => $this->build_steps($current_step, $bundle),
            'active_panel'      => $this->build_active_panel($current_step, $bundle, $workspace_access),
            'visual_help'       => $this->build_visual_help($current_step, $bundle),
            'technical_summary' => $this->build_technical_summary($current_step, $bundle),
        ];
    }

    private function build_banner(string $current_step, array $bundle, array $workspace_access): array {
        $is_codex = $this->is_codex($bundle);
        $is_codex_remote_adapter = $this->is_codex_remote_adapter($bundle);

        if ($current_step === 'generate_bundle') {
            $local_writable = $this->can_write_workspace($bundle, $workspace_access);
            $is_opencode_local = $this->is_opencode_local($bundle);

            return [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => $is_codex && !$local_writable
                    ? __('Copy the Codex shortcut', 'livecanvas-forge-ai')
                    : ($local_writable
                    ? __('Write the client config in this workspace', 'livecanvas-forge-ai')
                    : ($is_opencode_local ? __('Download opencode.json', 'livecanvas-forge-ai') : __('Download the client bundle', 'livecanvas-forge-ai'))),
                'body'    => $is_codex && !$local_writable
                    ? ($is_codex_remote_adapter
                        ? __('Codex needs a one-time registration command. Copy the shortcut below and run it on the machine where Codex runs; it starts the secure AI Bridge pairing proxy.', 'livecanvas-forge-ai')
                        : __('Codex needs a one-time registration command. Copy the shortcut below, run it in this workspace, and let it auto-detect the embedded Codex desktop CLI if codex is not in your PATH.', 'livecanvas-forge-ai'))
                    : ($local_writable
                    ? __('AI Bridge can write the client artifact directly inside this workspace.', 'livecanvas-forge-ai')
                    : __('This browser runtime cannot write to the selected host workspace directly. Download the bundle, open the project in your coding agent, then return here for the smoke test.', 'livecanvas-forge-ai')),
                'next'    => $is_codex
                    ? __('After running the Codex shortcut, verify with codex mcp list or /Applications/Codex.app/Contents/Resources/codex mcp list, then come back here and run the smoke test.', 'livecanvas-forge-ai')
                    : __('After this step, come back here and run the smoke test.', 'livecanvas-forge-ai'),
            ];
        }

        if ($current_step === 'smoke_test') {
            return [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => $is_codex ? __('Verify the Codex registration', 'livecanvas-forge-ai') : __('Run the smoke test', 'livecanvas-forge-ai'),
                'body'    => $is_codex
                    ? __('Run the Codex shortcut first. Once codex mcp list, or the embedded Codex desktop CLI path, shows livecanvas-forge, use the smoke test to verify that AI Bridge is reachable from Codex.', 'livecanvas-forge-ai')
                    : __('Use the generated bundle to verify that AI Bridge can reach the plugin through the selected coding agent flow.', 'livecanvas-forge-ai'),
                'next'    => $is_codex
                    ? __('A passing smoke test confirms that Codex can now call the livecanvas-forge tools.', 'livecanvas-forge-ai')
                    : __('A passing smoke test will move this connection to Ready.', 'livecanvas-forge-ai'),
            ];
        }

        return [
            'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
            'title'   => $this->get_step_title($current_step),
            'body'    => __('Answer the current question, then the wizard will unlock the next step automatically.', 'livecanvas-forge-ai'),
            'next'    => __('You only need to focus on the active step.', 'livecanvas-forge-ai'),
        ];
    }

    private function build_steps(string $current_step, array $bundle): array {
        $definitions = $this->get_step_definitions($bundle);
        $keys = array_keys($definitions);
        $active_index = $current_step === 'ready'
            ? count($keys)
            : max(0, (int) array_search($current_step, $keys, true));
        $steps = [];

        foreach ($keys as $index => $key) {
            $definition = $definitions[$key];
            $steps[] = [
                'key'    => $key,
                'number' => sprintf('%02d', $index + 1),
                'title'  => __($definition['title'], 'livecanvas-forge-ai'),
                'helper' => __($definition['helper'], 'livecanvas-forge-ai'),
                'state'  => $index < $active_index ? 'done' : ($index === $active_index ? 'active' : 'locked'),
            ];
        }

        return $steps;
    }

    private function build_active_panel(string $current_step, array $bundle, array $workspace_access): array {
        switch ($current_step) {
            case 'choose_claude_target':
                return [
                    'title'       => __('How are you connecting Claude?', 'livecanvas-forge-ai'),
                    'description' => __('Choose whether this connection should target Claude Desktop App or the Claude Command Line Interface before you continue.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Continue', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'choose_mode':
                return [
                    'title'       => __('Is this local or remote?', 'livecanvas-forge-ai'),
                    'description' => __('Choose where the coding agent should connect before generating any bundle.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Continue', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'confirm_details':
                return [
                    'title'       => __('Are these connection details correct?', 'livecanvas-forge-ai'),
                    'description' => __('Review the generated connection details before you create the client bundle.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_confirm_details_alert($bundle),
                    'primary_cta' => [
                        'label'  => __('Confirm details', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'generate_bundle':
                $local_writable = $this->can_write_workspace($bundle, $workspace_access);
                $is_opencode_local = $this->is_opencode_local($bundle);
                $is_codex = $this->is_codex($bundle);
                $is_codex_remote_adapter = $this->is_codex_remote_adapter($bundle);

                return [
                    'title'       => __('How do you want to continue?', 'livecanvas-forge-ai'),
                    'description' => $is_codex
                        ? ($local_writable
                            ? __('Choose one path below. Recommended: let AI Bridge write the Codex helper directly into this workspace. Use the manual option only if you prefer to place the file yourself.', 'livecanvas-forge-ai')
                            : ($is_codex_remote_adapter
                                ? __('Choose one path below. Recommended: copy and run the secure Codex remote shortcut on the machine where Codex runs. Use the manual option only if you want to save the helper script.', 'livecanvas-forge-ai')
                                : __('Choose one path below. Recommended: copy and run the Codex shortcut from this exact project root. Use the manual option only if you want to install the helper yourself.', 'livecanvas-forge-ai')))
                        : ($is_opencode_local
                        ? __('Choose one path below. Recommended: download the OpenCode config and place it in the project root before switching to OpenCode.', 'livecanvas-forge-ai')
                        : __('Choose one path below. Recommended: create the client configuration now. Use the manual option only if you want to place the bundle yourself.', 'livecanvas-forge-ai')),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => $is_codex && !$local_writable
                            ? __('Copy Codex shortcut', 'livecanvas-forge-ai')
                            : ($local_writable
                            ? __('Write config in workspace', 'livecanvas-forge-ai')
                            : ($is_opencode_local ? __('Download opencode.json', 'livecanvas-forge-ai') : __('Download client bundle', 'livecanvas-forge-ai'))),
                        'action' => $is_codex && !$local_writable ? 'copy_command' : ($local_writable ? 'install' : 'download'),
                    ],
                    'secondary_ctas' => [
                        [
                            'label'  => $is_codex && !$local_writable
                                ? __('Download Codex helper', 'livecanvas-forge-ai')
                                : ($local_writable ? __('Download client bundle', 'livecanvas-forge-ai') : __('Copy command', 'livecanvas-forge-ai')),
                            'action' => $is_codex && !$local_writable ? 'download' : ($local_writable ? 'download' : 'copy_command'),
                        ],
                    ],
                ];

            case 'smoke_test':
                return [
                    'title'       => $this->is_codex($bundle) ? __('Ready to verify Codex?', 'livecanvas-forge-ai') : __('Ready to verify the connection?', 'livecanvas-forge-ai'),
                    'description' => $this->is_codex($bundle)
                        ? __('Run the smoke test after you have executed the Codex shortcut and verified that Codex can see livecanvas-forge.', 'livecanvas-forge-ai')
                        : __('Run the smoke test after the client bundle is in place.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Run smoke test', 'livecanvas-forge-ai'),
                        'action' => 'smoke_test',
                    ],
                ];

            case 'choose_client':
            default:
                return [
                    'title'       => __('Which coding agent are you connecting?', 'livecanvas-forge-ai'),
                    'description' => __('Start with the client choice, then the wizard will guide the rest of the setup.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner('choose_client', $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Continue', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];
        }
    }

    private function build_confirm_details_alert(array $bundle): array {
        $mode = (string) ($bundle['mode'] ?? 'local');

        return [
            'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
            'title'   => __('Review the connection details', 'livecanvas-forge-ai'),
            'body'    => $mode === 'remote'
                ? __('This bundle will be downloaded and used on your machine. The remote WordPress server cannot write the client configuration directly.', 'livecanvas-forge-ai')
                : __('Make sure the local workspace root matches the real project path on your machine before you generate the bundle.', 'livecanvas-forge-ai'),
            'next'    => __('Once confirmed, AI Bridge will generate the correct client bundle for the selected agent.', 'livecanvas-forge-ai'),
        ];
    }

    private function build_technical_summary(string $current_step, array $bundle): array {
        $expanded = in_array($current_step, ['generate_bundle', 'smoke_test'], true);

        if ($this->is_opencode_local($bundle)) {
            $expanded = false;
        }

        return [
            'expanded' => $expanded,
            'bundle'   => $bundle,
        ];
    }

    private function build_ready_panel(array $state, array $bundle, array $workspace_access): array {
        return [
            'title'       => __('Connection status', 'livecanvas-forge-ai'),
            'description' => __('The selected client bundle has already been verified.', 'livecanvas-forge-ai'),
            'alert'       => [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => __('Connection ready', 'livecanvas-forge-ai'),
                'body'    => __('The smoke test has already passed. You can rerun checks or regenerate the bundle if something changes.', 'livecanvas-forge-ai'),
                'next'    => __('Use Change coding agent only when you want to restart the wizard from the first step and generate a new client bundle.', 'livecanvas-forge-ai'),
            ],
            'primary_cta' => [
                'label'  => __('Run checks', 'livecanvas-forge-ai'),
                'action' => 'smoke_test',
            ],
            'secondary_ctas' => [
                [
                    'label'  => __('Regenerate bundle', 'livecanvas-forge-ai'),
                    'action' => 'download',
                ],
                [
                    'label'  => __('Change coding agent', 'livecanvas-forge-ai'),
                    'action' => 'reconfigure',
                ],
            ],
            'status_label' => __('Ready', 'livecanvas-forge-ai'),
            'last_verified_at' => (string) ($state['last_verified_at'] ?? ''),
            'workspace_access' => $workspace_access,
            'bundle' => $bundle,
        ];
    }

    private function can_write_workspace(array $bundle, array $workspace_access): bool {
        return (string) ($bundle['mode'] ?? 'local') === 'local'
            && !empty($bundle['workspace_files'])
            && !empty($workspace_access['available']);
    }

    private function normalize_step(string $step, array $bundle = []): string {
        $step = sanitize_key($step);

        if ($this->is_opencode_local($bundle) && $step === 'choose_mode') {
            $step = 'confirm_details';
        }

        return array_key_exists($step, $this->get_step_definitions($bundle)) ? $step : 'choose_client';
    }

    private function get_step_title(string $current_step): string {
        $definition = self::STEP_DEFINITIONS[$current_step] ?? self::STEP_DEFINITIONS['choose_client'];

        return __($definition['title'], 'livecanvas-forge-ai');
    }

    private function get_step_definitions(array $bundle): array {
        if ($this->is_opencode_local($bundle)) {
            return [
                'choose_client' => self::STEP_DEFINITIONS['choose_client'],
                'confirm_details' => self::STEP_DEFINITIONS['confirm_details'],
                'generate_bundle' => self::STEP_DEFINITIONS['generate_bundle'],
                'smoke_test' => self::STEP_DEFINITIONS['smoke_test'],
            ];
        }

        if (($bundle['client'] ?? '') !== 'claude') {
            return [
                'choose_client' => self::STEP_DEFINITIONS['choose_client'],
                'choose_mode' => self::STEP_DEFINITIONS['choose_mode'],
                'confirm_details' => self::STEP_DEFINITIONS['confirm_details'],
                'generate_bundle' => self::STEP_DEFINITIONS['generate_bundle'],
                'smoke_test' => self::STEP_DEFINITIONS['smoke_test'],
            ];
        }

        return self::STEP_DEFINITIONS;
    }

    private function build_visual_help(string $current_step, array $bundle): array {
        if (!in_array($current_step, ['generate_bundle', 'smoke_test'], true)) {
            return [];
        }

        if ($this->is_codex($bundle)) {
            $is_remote_adapter = $this->is_codex_remote_adapter($bundle);
            $is_secure_remote = (string) ($bundle['connection_strategy'] ?? '') === 'ai-bridge-session';
            $handoff_tool = (string) ($bundle['agent_start_tool'] ?? '');
            if ($handoff_tool === '') {
                $handoff_tool = $is_remote_adapter && !$is_secure_remote ? 'livecanvas-forge-ai/get-connection-handoff' : 'get_connection_handoff';
            }

            return [
                'title' => __('What to do in Codex', 'livecanvas-forge-ai'),
                'client' => 'codex',
                'items' => [
                    [
                        'title' => __('Copy and run the Codex shortcut', 'livecanvas-forge-ai'),
                        'caption' => $is_remote_adapter
                            ? __('Execute the generated install command once on the machine where Codex runs. It registers the secure AI Bridge pairing proxy.', 'livecanvas-forge-ai')
                            : __('Execute the generated install command once from this same project root. It auto-detects the embedded Codex desktop CLI.', 'livecanvas-forge-ai'),
                        'tone' => 'project',
                    ],
                    [
                        'title' => __('Check codex mcp list', 'livecanvas-forge-ai'),
                        'caption' => $is_secure_remote
                            ? __('If codex is not in PATH, use /Applications/Codex.app/Contents/Resources/codex mcp list and make sure livecanvas-ai-bridge appears before you continue.', 'livecanvas-forge-ai')
                            : __('If codex is not in PATH, use /Applications/Codex.app/Contents/Resources/codex mcp list and make sure livecanvas-forge appears before you continue.', 'livecanvas-forge-ai'),
                        'tone' => 'mcp',
                    ],
                    [
                        'title' => sprintf(__('Open Codex and call %s', 'livecanvas-forge-ai'), $handoff_tool),
                        'caption' => __('Once Codex sees the MCP server, ask it to fetch the AI Bridge connection handoff, then return here and run the smoke test.', 'livecanvas-forge-ai'),
                        'tone' => 'verify',
                    ],
                ],
            ];
        }

        if (!$this->is_opencode_local($bundle)) {
            return [];
        }

        return [
            'title' => __('What this looks like in OpenCode', 'livecanvas-forge-ai'),
            'client' => 'opencode',
            'items' => [
                [
                    'title' => __('Open this project in OpenCode', 'livecanvas-forge-ai'),
                    'caption' => __('Use the same project folder that contains this WordPress install.', 'livecanvas-forge-ai'),
                    'tone' => 'project',
                ],
                [
                    'title' => __('Check MCP: livecanvas-forge', 'livecanvas-forge-ai'),
                    'caption' => __('The MCP indicator should turn green before you continue.', 'livecanvas-forge-ai'),
                    'tone' => 'mcp',
                ],
                [
                    'title' => __('Return here and run the smoke test', 'livecanvas-forge-ai'),
                    'caption' => __('Once OpenCode is connected, verify the connection back in WordPress.', 'livecanvas-forge-ai'),
                    'tone' => 'verify',
                ],
            ],
        ];
    }

    private function is_opencode_local(array $bundle): bool {
        return (string) ($bundle['client'] ?? '') === 'opencode'
            && (string) ($bundle['mode'] ?? 'local') === 'local';
    }

    private function is_codex_remote_adapter(array $bundle): bool {
        return $this->is_codex($bundle)
            && in_array((string) ($bundle['connection_strategy'] ?? ''), ['ai-bridge-session', 'remote-mcp-adapter'], true);
    }

    private function is_codex(array $bundle): bool {
        return (string) ($bundle['client'] ?? '') === 'codex';
    }
}
