<?php

defined('ABSPATH') || exit;

final class LCFA_Plugin {
    private static ?LCFA_Plugin $instance = null;
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
    private LCFA_Inventory $inventory;
    private LCFA_Genesis_Planner $genesis_planner;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Connection_Tester $connection_tester;
    private LCFA_Remote_Client $remote_client;
    private LCFA_Connection_Bundle_Builder $connection_bundle_builder;
    private LCFA_Connection_Onboarding $connection_onboarding;
    private LCFA_Prompt_Suggester $prompt_suggester;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Rest_Api $rest_api;
    private LCFA_Admin $admin;
    private LCFA_Design_System_Preview $design_system_preview;

    public static function instance(): LCFA_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        if (!get_option(LCFA_Settings::OPTION_KEY)) {
            LCFA_Settings::update(LCFA_Settings::defaults());
        }

        if (!get_option(LCFA_Settings::CONNECTIONS_OPTION_KEY)) {
            LCFA_Settings::update_connections(LCFA_Settings::connection_defaults());
        }

        add_option(LCFA_Settings::REDIRECT_OPTION_KEY, 1);
    }

    private function __construct() {
        $this->environment = new LCFA_Environment();
        $this->installer   = new LCFA_Installer($this->environment);
        $this->inventory   = new LCFA_Inventory($this->environment);
        $this->genesis_planner = new LCFA_Genesis_Planner($this->environment, $this->inventory);
        $this->windpress_bridge = new LCFA_WindPress_Bridge($this->environment);
        $this->theme_files_bridge = new LCFA_Theme_Files_Bridge($this->environment);
        $this->local_mcp_bridge = new LCFA_Local_MCP_Bridge($this->environment);
        $this->remote_client = new LCFA_Remote_Client();
        $this->connection_tester = new LCFA_Connection_Tester($this->environment, $this->local_mcp_bridge, $this->remote_client);
        $this->connection_bundle_builder = new LCFA_Connection_Bundle_Builder();
        $this->connection_onboarding = new LCFA_Connection_Onboarding($this->connection_bundle_builder);
        $this->prompt_suggester = new LCFA_Prompt_Suggester($this->environment, $this->inventory);
        $this->context_builder = new LCFA_Context_Builder($this->environment, $this->inventory, $this->windpress_bridge, $this->local_mcp_bridge);
        $this->design_system_preview = new LCFA_Design_System_Preview();
        $design_system_build_gateway = new LCFA_Design_System_Build_Gateway($this->local_mcp_bridge);
        $picostrap_design_system = new LCFA_Design_System_Picostrap_Executor();
        $picowind_design_system = new LCFA_Design_System_Picowind_Executor(
            $this->windpress_bridge,
            $this->theme_files_bridge,
            $design_system_build_gateway
        );
        $design_system_apply = new LCFA_Design_System_Apply(
            $this->environment,
            $picostrap_design_system,
            $picowind_design_system
        );
        $design_system_compose = new LCFA_Design_System_Compose(
            $this->environment,
            new LCFA_Design_System_Picostrap_Composer(),
            $design_system_apply,
            $this->design_system_preview
        );
        $this->command_deck = new LCFA_Command_Deck($this->environment, $this->inventory, $this->windpress_bridge, $this->theme_files_bridge, $this->local_mcp_bridge, $this->remote_client, $design_system_apply, $design_system_compose);
        $this->rest_api    = new LCFA_Rest_Api($this->environment, $this->inventory, $this->windpress_bridge, $this->theme_files_bridge, $this->local_mcp_bridge, $this->context_builder, $this->command_deck, $this->prompt_suggester, $this->genesis_planner);
        $this->admin       = new LCFA_Admin($this->environment, $this->installer, $this->inventory, $this->theme_files_bridge, $this->connection_tester, $this->remote_client, $this->context_builder, $this->connection_onboarding, $this->command_deck, $this->prompt_suggester, $this->genesis_planner);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        $this->design_system_preview->hooks();
        $this->admin->hooks();
        $this->rest_api->hooks();
    }
}
