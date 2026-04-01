<?php
/**
 * Plugin Name: LiveCanvas Forge AI
 * Plugin URI: https://livecanvas.com/
 * Description: AI companion and guided setup flow for LiveCanvas, Picostrap, Picowind, and WindPress.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: livecanvas-forge-ai
 */

defined('ABSPATH') || exit;

define('LCFA_VERSION', '0.1.0');
define('LCFA_FILE', __FILE__);
define('LCFA_DIR', plugin_dir_path(__FILE__));
define('LCFA_URL', plugin_dir_url(__FILE__));

require_once LCFA_DIR . 'includes/class-lcfa-settings.php';
require_once LCFA_DIR . 'includes/class-lcfa-environment.php';
require_once LCFA_DIR . 'includes/class-lcfa-installer.php';
require_once LCFA_DIR . 'includes/class-lcfa-admin.php';
require_once LCFA_DIR . 'includes/class-lcfa-plugin.php';

register_activation_hook(LCFA_FILE, ['LCFA_Plugin', 'activate']);

function lcfa(): LCFA_Plugin {
    return LCFA_Plugin::instance();
}

lcfa();
