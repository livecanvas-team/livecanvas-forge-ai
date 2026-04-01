<?php

defined('ABSPATH') || exit;

final class LCFA_Plugin {
    private static ?LCFA_Plugin $instance = null;
    private LCFA_Environment $environment;
    private LCFA_Installer $installer;
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

        add_option(LCFA_Settings::REDIRECT_OPTION_KEY, 1);
    }

    private function __construct() {
        $this->environment = new LCFA_Environment();
        $this->installer   = new LCFA_Installer($this->environment);
        $this->admin       = new LCFA_Admin($this->environment, $this->installer);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void {
        $this->admin->hooks();
    }
}
