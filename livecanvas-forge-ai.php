<?php
/**
 * Plugin Name: LiveCanvas AI Bridge
 * Plugin URI: https://livecanvas.com/
 * Description: AI companion and guided setup flow for LiveCanvas, Picostrap, Picowind, and WindPress.
 * Version: 0.1.7
 * Update URI: https://github.com/livecanvas-team/livecanvas-forge-ai
 * Author: The LiveCanvas Team
 * Author URI: https://livecanvas.com/
 * Text Domain: livecanvas-forge-ai
 */

defined('ABSPATH') || exit;

define('LCFA_VERSION', '0.1.7');
define('LCFA_FILE', __FILE__);
define('LCFA_DIR', plugin_dir_path(__FILE__));
define('LCFA_URL', plugin_dir_url(__FILE__));

require_once LCFA_DIR . 'includes/class-lcfa-settings.php';
require_once LCFA_DIR . 'includes/class-lcfa-environment.php';
require_once LCFA_DIR . 'includes/class-lcfa-installer.php';
require_once LCFA_DIR . 'includes/class-lcfa-inventory.php';
require_once LCFA_DIR . 'includes/class-lcfa-genesis-planner.php';
require_once LCFA_DIR . 'includes/class-lcfa-genesis-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-windpress-bridge.php';
require_once LCFA_DIR . 'includes/class-lcfa-theme-files-bridge.php';
require_once LCFA_DIR . 'includes/class-lcfa-local-mcp-bridge.php';
require_once LCFA_DIR . 'includes/class-lcfa-mcp-session-manager.php';
require_once LCFA_DIR . 'includes/class-lcfa-codex-config-manager.php';
require_once LCFA_DIR . 'includes/class-lcfa-connection-tester.php';
require_once LCFA_DIR . 'includes/class-lcfa-codex-autorunner.php';
require_once LCFA_DIR . 'includes/class-lcfa-remote-client.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-fallback-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-preview.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-manifest.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-bundle-store.php';
require_once LCFA_DIR . 'includes/class-lcfa-picostrap-compile-service.php';
require_once LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require_once LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';
require_once LCFA_DIR . 'includes/class-lcfa-direct-agent-onboarding.php';
require_once LCFA_DIR . 'includes/class-lcfa-power-mode.php';
require_once LCFA_DIR . 'includes/class-lcfa-connection-wizard-presenter.php';
require_once LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require_once LCFA_DIR . 'includes/class-lcfa-workspace-access.php';
require_once LCFA_DIR . 'includes/class-lcfa-prompt-suggester.php';
require_once LCFA_DIR . 'includes/class-lcfa-ai-client.php';
require_once LCFA_DIR . 'includes/class-lcfa-context-builder.php';
require_once LCFA_DIR . 'includes/class-lcfa-command-deck.php';
require_once LCFA_DIR . 'includes/class-lcfa-block-patterns.php';
require_once LCFA_DIR . 'includes/class-lcfa-ability-registry.php';
require_once LCFA_DIR . 'includes/class-lcfa-rest-api.php';
require_once LCFA_DIR . 'includes/class-lcfa-admin.php';
require_once LCFA_DIR . 'includes/class-lcfa-plugin.php';

register_activation_hook(LCFA_FILE, ['LCFA_Plugin', 'activate']);

function lcfa(): LCFA_Plugin {
    return LCFA_Plugin::instance();
}

lcfa();
