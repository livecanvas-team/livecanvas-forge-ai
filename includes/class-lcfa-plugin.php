<?php

defined('ABSPATH') || exit;

final class LCFA_Plugin {
    private static ?LCFA_Plugin $instance = null;
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
    private LCFA_Inventory $inventory;
    private LCFA_WindPress_Bridge $windpress_bridge;
    private LCFA_Theme_Files_Bridge $theme_files_bridge;
    private LCFA_Local_MCP_Bridge $local_mcp_bridge;
    private LCFA_Context_Builder $context_builder;
    private LCFA_Command_Deck $command_deck;
    private LCFA_Rest_Api $rest_api;
    private LCFA_Admin $admin;

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
        $this->windpress_bridge = new LCFA_WindPress_Bridge($this->environment);
        $this->theme_files_bridge = new LCFA_Theme_Files_Bridge($this->environment);
        $this->local_mcp_bridge = new LCFA_Local_MCP_Bridge($this->environment);
        $this->context_builder = new LCFA_Context_Builder($this->environment, $this->inventory, $this->windpress_bridge, $this->local_mcp_bridge);
        $this->command_deck = new LCFA_Command_Deck($this->environment, $this->inventory, $this->windpress_bridge, $this->theme_files_bridge, $this->local_mcp_bridge);
        $this->rest_api    = new LCFA_Rest_Api($this->environment, $this->inventory, $this->windpress_bridge, $this->theme_files_bridge, $this->local_mcp_bridge, $this->context_builder, $this->command_deck);
        $this->admin       = new LCFA_Admin($this->environment, $this->installer, $this->inventory, $this->theme_files_bridge, $this->context_builder, $this->command_deck);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        $this->admin->hooks();
        $this->rest_api->hooks();
    }
}
