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
            'helper' => 'Desktop App or Claude Code',
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
        $is_claude_desktop = $this->is_claude_desktop($bundle);

        if ($current_step === 'generate_bundle') {
            $local_writable = $this->can_write_workspace($bundle, $workspace_access);
            if ($is_claude_desktop && $local_writable) {
                return [
                    'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                    'title'   => __('Configure Claude Desktop automatically', 'livecanvas-forge-ai'),
                    'body'    => __('AI Bridge can safely merge only livecanvas-forge into the Claude Desktop app config. Existing preferences and connectors are preserved, a backup is created, and the file stays private.', 'livecanvas-forge-ai'),
                    'next'    => __('After configuration, reopen Claude Desktop, use the normal Chat screen, and call get_connection_handoff. Claude Code is not required for this flow.', 'livecanvas-forge-ai'),
                ];
            }
            if ($is_claude_desktop) {
                return [
                    'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                    'title'   => __('Copy the Claude Desktop config', 'livecanvas-forge-ai'),
                    'body'    => __('AI Bridge cannot write the app config from this WordPress runtime. Copy the generated JSON and merge only livecanvas-forge under mcpServers without replacing your existing preferences or connectors.', 'livecanvas-forge-ai'),
                    'next'    => __('Save the app config, reopen Claude Desktop, use the normal Chat screen, and call get_connection_handoff.', 'livecanvas-forge-ai'),
                ];
            }

            return [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => $is_codex && !$local_writable
                    ? ($is_codex_remote_adapter
                        ? __('Copy the secure Codex command', 'livecanvas-forge-ai')
                        : __('Install the Codex project config', 'livecanvas-forge-ai'))
                    : ($local_writable
                    ? __('Write the client config in this workspace', 'livecanvas-forge-ai')
                    : __('Copy the setup command', 'livecanvas-forge-ai')),
                'body'    => $is_codex && !$local_writable
                    ? ($is_codex_remote_adapter
                        ? __('Codex needs a one-time registration command. Copy the shortcut below and run it on the machine where Codex runs; it starts the secure AI Bridge pairing proxy.', 'livecanvas-forge-ai')
                        : __('Download the generated TOML snippet and merge only the livecanvas-forge server into this project’s .codex/config.toml. No static token is stored in the project.', 'livecanvas-forge-ai'))
                    : ($local_writable
                    ? __('AI Bridge can safely merge the client artifact directly inside this agent project. Existing servers are backed up and preserved.', 'livecanvas-forge-ai')
                    : __('Copy the command below and run it from the coding-agent project. It adds this WordPress site to that project without changing website content.', 'livecanvas-forge-ai')),
                'next'    => $is_codex
                    ? ($is_codex_remote_adapter
                        ? __('After running the secure command, open Codex and call get_connection_handoff, then come back here for the smoke test.', 'livecanvas-forge-ai')
                        : __('Open this project in VS Code or Cursor, reload Codex, call get_connection_handoff, approve the matching pairing request, then come back here for the smoke test.', 'livecanvas-forge-ai'))
                    : __('After copying, the wizard shows exactly how to restart the coding agent, approve pairing, and reach the smoke test.', 'livecanvas-forge-ai'),
            ];
        }

        if ($current_step === 'smoke_test') {
            if ($is_claude_desktop) {
                return [
                    'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                    'title'   => __('Verify Claude Desktop Chat', 'livecanvas-forge-ai'),
                    'body'    => __('Reopen Claude Desktop, use the normal Chat screen, call get_connection_handoff, and approve the matching pairing request in WordPress before running this smoke test.', 'livecanvas-forge-ai'),
                    'next'    => __('A passing smoke test confirms the Claude Desktop registration, secure pairing, and local MCP bridge. The paid Claude Code screen is not part of this path.', 'livecanvas-forge-ai'),
                ];
            }

            return [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => $is_codex ? __('Verify the Codex registration', 'livecanvas-forge-ai') : __('Run the smoke test', 'livecanvas-forge-ai'),
                'body'    => $is_codex
                    ? __('Open the project that contains .codex/config.toml, reload Codex, and call get_connection_handoff. Approve the matching pairing request in WordPress before running this smoke test.', 'livecanvas-forge-ai')
                    : __('Use the generated bundle to verify that AI Bridge can reach the plugin through the selected coding agent flow.', 'livecanvas-forge-ai'),
                'next'    => $is_codex
                    ? __('A passing smoke test confirms that the project config, secure pairing, and local MCP runtime are aligned.', 'livecanvas-forge-ai')
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
                    'description' => __('Choose Claude Desktop for the chat app, or Claude Code for a project-scoped .mcp.json connection.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Continue', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'choose_mode':
                return [
                    'title'       => __('Where does this WordPress site run?', 'livecanvas-forge-ai'),
                    'description' => __('Choose Local only for a WordPress site on this computer. Choose Remote for an online HTTPS site such as your staging or production website.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => __('Continue', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'confirm_details':
                return [
                    'title'       => __('Confirm the detected connection', 'livecanvas-forge-ai'),
                    'description' => __('AI Bridge has already chosen the connection for this site. Check the summary, then confirm to create the client setup.', 'livecanvas-forge-ai'),
                    'alert'       => $this->build_confirm_details_alert($bundle),
                    'primary_cta' => [
                        'label'  => __('Confirm connection', 'livecanvas-forge-ai'),
                        'action' => 'save_selection',
                    ],
                ];

            case 'generate_bundle':
                $local_writable = $this->can_write_workspace($bundle, $workspace_access);
                $is_opencode_local = $this->is_opencode_local($bundle);
                $is_codex = $this->is_codex($bundle);
                $is_codex_remote_adapter = $this->is_codex_remote_adapter($bundle);
                $is_claude_desktop = $this->is_claude_desktop($bundle);

                return [
                    'title'       => __('How do you want to continue?', 'livecanvas-forge-ai'),
                    'description' => $is_claude_desktop
                        ? ($local_writable
                            ? __('Recommended: let AI Bridge merge livecanvas-forge directly into the Claude Desktop app config. Existing preferences and connectors are preserved and backed up.', 'livecanvas-forge-ai')
                            : __('AI Bridge cannot write the Claude Desktop app config from this WordPress runtime. Download the snippet, merge only livecanvas-forge under mcpServers, then reopen Claude Desktop.', 'livecanvas-forge-ai'))
                        : ($is_codex
                        ? ($local_writable
                            ? __('Recommended: let AI Bridge merge the Codex MCP server into this project’s .codex/config.toml. Existing servers are preserved and backed up.', 'livecanvas-forge-ai')
                            : ($is_codex_remote_adapter
                                ? __('Choose one path below. Recommended: copy and run the secure Codex remote shortcut on the machine where Codex runs. Use the manual option only if you want to save the helper script.', 'livecanvas-forge-ai')
                                : __('AI Bridge cannot write this project folder from WordPress. Download the project-scoped TOML snippet, merge only livecanvas-forge into .codex/config.toml, then reopen the project in VS Code or Cursor.', 'livecanvas-forge-ai')))
                        : ($local_writable
                            ? __('Choose one path below. Recommended: let AI Bridge write the client configuration directly into this workspace. Use the manual option only if you prefer to place the file yourself.', 'livecanvas-forge-ai')
                            : __('Choose one path below. Recommended: copy and run the setup command in your coding agent. Download the bundle only if you prefer to place the configuration files yourself.', 'livecanvas-forge-ai'))),
                    'alert'       => $this->build_banner($current_step, $bundle, $workspace_access),
                    'primary_cta' => [
                        'label'  => $local_writable
                            ? ($is_claude_desktop ? __('Configure Claude Desktop', 'livecanvas-forge-ai') : __('Write config in workspace', 'livecanvas-forge-ai'))
                            : ($is_claude_desktop
                                ? __('Copy Claude Desktop config', 'livecanvas-forge-ai')
                                : ($is_codex && !$is_codex_remote_adapter ? __('Download Codex config snippet', 'livecanvas-forge-ai') : ($is_codex ? __('Copy secure Codex command', 'livecanvas-forge-ai') : __('Copy setup command', 'livecanvas-forge-ai')))),
                        'action' => $local_writable ? 'install' : ($is_codex && !$is_codex_remote_adapter ? 'download' : 'copy_command'),
                    ],
                    'secondary_ctas' => [
                        [
                        'label'  => $is_codex
                                ? __('Download Codex config snippet', 'livecanvas-forge-ai')
                                : ($is_claude_desktop
                                    ? __('Download Claude Desktop snippet', 'livecanvas-forge-ai')
                                    : ($is_opencode_local ? __('Download opencode.json', 'livecanvas-forge-ai') : __('Download client bundle', 'livecanvas-forge-ai'))),
                            'action' => 'download',
                        ],
                    ],
                ];

            case 'smoke_test':
                return [
                    'title'       => $this->is_codex($bundle)
                        ? __('Ready to verify Codex?', 'livecanvas-forge-ai')
                        : ($this->is_claude_desktop($bundle) ? __('Ready to verify Claude Desktop?', 'livecanvas-forge-ai') : __('Final check: test the connection', 'livecanvas-forge-ai')),
                    'description' => $this->is_codex($bundle)
                        ? __('Open the same project in VS Code or Cursor, call get_connection_handoff from Codex, approve pairing if requested, then run this smoke test.', 'livecanvas-forge-ai')
                        : ($this->is_claude_desktop($bundle)
                            ? __('Reopen Claude Desktop and use the normal Chat screen. Call get_connection_handoff, approve any pending request in WordPress, then run this test. Claude Code is not required.', 'livecanvas-forge-ai')
                            : __('First reopen your coding agent, call get_connection_handoff, and approve any pending request in WordPress. Then run this test. It checks the connection without changing website content.', 'livecanvas-forge-ai')),
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
            'title'   => __('No connection choice is needed here', 'livecanvas-forge-ai'),
            'body'    => $mode === 'remote'
                ? __('The bundle will connect your coding agent to the remote WordPress site. Confirm only after checking the site address.', 'livecanvas-forge-ai')
                : __('This WordPress site is already detected as local. Confirm the details, then check that the project folder is the real folder on your computer.', 'livecanvas-forge-ai'),
            'next'    => __('Click Confirm connection to generate the setup for the selected coding agent.', 'livecanvas-forge-ai'),
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
        $install_files = is_array($bundle['install_files'] ?? null)
            ? $bundle['install_files']
            : (is_array($bundle['workspace_files'] ?? null) ? $bundle['workspace_files'] : []);

        return (string) ($bundle['mode'] ?? 'local') === 'local'
            && !empty($install_files)
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
                'title' => __('Finish setup in Codex', 'livecanvas-forge-ai'),
                'client' => 'codex',
                'items' => [
                    [
                        'title' => $is_remote_adapter ? __('Copy and run the Codex shortcut', 'livecanvas-forge-ai') : __('Open the agent project in VS Code or Cursor', 'livecanvas-forge-ai'),
                        'caption' => $is_remote_adapter
                            ? __('Execute the generated install command once on the machine where Codex runs. It registers the secure AI Bridge pairing proxy.', 'livecanvas-forge-ai')
                            : __('Use the same folder that contains .codex/config.toml. WordPress may live deeper inside it, for example app/public. In VS Code, trust this exact folder when prompted because Restricted Mode prevents Codex from loading project MCP config.', 'livecanvas-forge-ai'),
                        'tone' => 'project',
                        'icon' => 'laptop',
                        'context' => $is_remote_adapter ? __('In Terminal', 'livecanvas-forge-ai') : __('In VS Code or Cursor', 'livecanvas-forge-ai'),
                    ],
                    [
                        'title' => $is_remote_adapter ? __('Check codex mcp list', 'livecanvas-forge-ai') : sprintf(__('Call %s', 'livecanvas-forge-ai'), $handoff_tool),
                        'caption' => $is_secure_remote
                            ? __('If codex is not in PATH, use /Applications/Codex.app/Contents/Resources/codex mcp list and make sure livecanvas-ai-bridge appears before you continue.', 'livecanvas-forge-ai')
                            : __('Open the Codex sidebar. If a pairing request appears, approve the matching code in WordPress and retry the handoff.', 'livecanvas-forge-ai'),
                        'tone' => 'mcp',
                        'icon' => 'plug',
                        'context' => $is_remote_adapter ? __('In Terminal', 'livecanvas-forge-ai') : __('In Codex', 'livecanvas-forge-ai'),
                    ],
                    [
                        'title' => $is_remote_adapter ? sprintf(__('Open Codex and call %s', 'livecanvas-forge-ai'), $handoff_tool) : __('Return here and run the smoke test', 'livecanvas-forge-ai'),
                        'caption' => $is_remote_adapter
                            ? __('Once Codex sees the MCP server, ask it to fetch the AI Bridge connection handoff, then return here and run the smoke test.', 'livecanvas-forge-ai')
                            : __('AI Bridge becomes Ready only after the Codex handoff has reached WordPress and this verification succeeds.', 'livecanvas-forge-ai'),
                        'tone' => 'verify',
                        'icon' => 'check-circle',
                        'context' => $is_remote_adapter ? __('In Codex', 'livecanvas-forge-ai') : __('Back in WordPress', 'livecanvas-forge-ai'),
                    ],
                ],
            ];
        }

        if (!$this->is_opencode_local($bundle)) {
            $client = sanitize_key((string) ($bundle['client'] ?? 'generic'));
            $is_claude_desktop = $this->is_claude_desktop($bundle);
            $labels = [
                'claude' => $is_claude_desktop ? 'Claude Desktop' : 'Claude Code',
                'cursor' => 'Cursor',
                'generic' => __('coding agent', 'livecanvas-forge-ai'),
            ];
            $label = (string) ($labels[$client] ?? $labels['generic']);

            return [
                'title' => sprintf(__('Finish setup in %s', 'livecanvas-forge-ai'), $label),
                'client' => $client,
                'items' => [
                    [
                        'title' => $is_claude_desktop
                            ? __('Reopen Claude Desktop', 'livecanvas-forge-ai')
                            : sprintf(__('Open this project in %s', 'livecanvas-forge-ai'), $label),
                        'caption' => $is_claude_desktop
                            ? __('Use the normal Chat screen. Claude Desktop reads livecanvas-forge from its app config while AI Bridge keeps the agent project folder separate from the nested app/public WordPress root.', 'livecanvas-forge-ai')
                            : __('Use the agent project folder shown by WordPress, not the nested app/public WordPress root.', 'livecanvas-forge-ai'),
                        'tone' => 'project',
                        'icon' => 'laptop',
                        'context' => sprintf(__('In %s', 'livecanvas-forge-ai'), $label),
                    ],
                    [
                        'title' => $client === 'cursor'
                            ? __('Enable, reload, and verify livecanvas-forge', 'livecanvas-forge-ai')
                            : __('Call get_connection_handoff', 'livecanvas-forge-ai'),
                        'caption' => $client === 'cursor'
                            ? __('Open Customize → MCPs. If livecanvas-forge is Disabled, enable it and click Reload. Then call get_connection_handoff, approve the matching request in WordPress, and retry.', 'livecanvas-forge-ai')
                            : __('Approve the matching pairing request in WordPress, then retry until the handoff reports the active site.', 'livecanvas-forge-ai'),
                        'tone' => 'mcp',
                        'icon' => 'plug',
                        'context' => sprintf(__('In %s', 'livecanvas-forge-ai'), $label),
                    ],
                    [
                        'title' => __('Return here and run the smoke test', 'livecanvas-forge-ai'),
                        'caption' => __('The project config is only considered Ready after the live handoff and verification succeed.', 'livecanvas-forge-ai'),
                        'tone' => 'verify',
                        'icon' => 'check-circle',
                        'context' => __('Back in WordPress', 'livecanvas-forge-ai'),
                    ],
                ],
            ];
        }

        return [
            'title' => __('Finish setup in OpenCode', 'livecanvas-forge-ai'),
            'client' => 'opencode',
            'items' => [
                [
                    'title' => __('Open this project in OpenCode', 'livecanvas-forge-ai'),
                    'caption' => __('Use the agent project folder shown by WordPress. The WordPress root may be nested at app/public.', 'livecanvas-forge-ai'),
                    'tone' => 'project',
                    'icon' => 'laptop',
                    'context' => __('In OpenCode', 'livecanvas-forge-ai'),
                ],
                [
                    'title' => __('Check MCP: livecanvas-forge', 'livecanvas-forge-ai'),
                    'caption' => __('The MCP indicator should turn green before you continue.', 'livecanvas-forge-ai'),
                    'tone' => 'mcp',
                    'icon' => 'plug',
                    'context' => __('In OpenCode', 'livecanvas-forge-ai'),
                ],
                [
                    'title' => __('Return here and run the smoke test', 'livecanvas-forge-ai'),
                    'caption' => __('Once OpenCode is connected, verify the connection back in WordPress.', 'livecanvas-forge-ai'),
                    'tone' => 'verify',
                    'icon' => 'check-circle',
                    'context' => __('Back in WordPress', 'livecanvas-forge-ai'),
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

    private function is_claude_desktop(array $bundle): bool {
        return (string) ($bundle['client'] ?? '') === 'claude'
            && (string) ($bundle['mode'] ?? '') === 'local'
            && (string) ($bundle['claude_connection_target'] ?? '') === 'desktop_app';
    }
}
