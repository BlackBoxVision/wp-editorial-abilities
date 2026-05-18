<?php

declare(strict_types=1);

namespace WpEditorialAbilities;

use WpEditorialAbilities\Abilities\AbilityRegistrar;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if (! function_exists('wp_register_ability')) {
            add_action('admin_notices', [$this, 'renderAbilitiesApiNotice']);

            return;
        }

        (new AbilityRegistrar())->registerHooks();

        if (! class_exists('WP\\MCP\\Core\\McpAdapter')) {
            add_action('admin_notices', [$this, 'renderMcpAdapterNotice']);
        }
    }

    public function renderAbilitiesApiNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP Editorial Abilities requires WordPress 6.9 or later with the Abilities API available.', 'wp-editorial-abilities');
        echo '</p></div>';
    }

    public function renderMcpAdapterNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('WP Editorial Abilities is active. Install and activate the official WordPress MCP Adapter to expose public abilities to MCP clients.', 'wp-editorial-abilities');
        echo '</p></div>';
    }
}
