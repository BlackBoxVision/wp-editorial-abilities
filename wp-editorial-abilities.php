<?php
/**
 * Plugin Name: WP Editorial Abilities
 * Description: Editorial workflow abilities for WordPress Abilities API and MCP Adapter.
 * Version: 0.2.0
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: BlackBox Vision
 * License: MIT
 * Text Domain: wp-editorial-abilities
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WPEA_PLUGIN_FILE', __FILE__);
define('WPEA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPEA_PLUGIN_VERSION', '0.2.0');

spl_autoload_register(static function (string $class): void {
    $prefix = 'WpEditorialAbilities\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = WPEA_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', static function (): void {
    WpEditorialAbilities\Plugin::instance()->boot();
});
