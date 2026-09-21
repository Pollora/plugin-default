<?php
/**
 * Plugin Name: %plugin_name%
 * Plugin URI: %plugin_uri%
 * Description: %plugin_description%
 * Version: %plugin_version%
 * Author: %plugin_author%
 * Author URI: %plugin_author_uri%
 * Text Domain: %plugin_name%
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.2
 * Network: false
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

use Pollora\Modules\Domain\Enums\ModuleType;

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('%PLUGIN_NAME%_VERSION', '%plugin_version%');
define('%PLUGIN_NAME%_PLUGIN_FILE', __FILE__);
define('%PLUGIN_NAME%_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('%PLUGIN_NAME%_PLUGIN_URL', plugin_dir_url(__FILE__));

/*
 * Register with whichever Pollora version is running.
 *
 * make:plugin downloads this plugin's latest tag whatever framework the site
 * has installed, so this file is the one place that has to work on both:
 * pollora_register() arrived in 13.32, while sites still on 13.4 only have the
 * registrar service. Calling the helper unconditionally takes those sites down
 * with a fatal before anything can report why — so each path is guarded, and
 * an unknown combination leaves the plugin unregistered rather than fatal.
 */
if (function_exists('pollora_register')) {
    pollora_register(ModuleType::Plugin, '%plugin_slug%', __DIR__);
} elseif (class_exists('Pollora\\Plugin\\Application\\Services\\PluginRegistrar') && function_exists('app')) {
    app('Pollora\\Plugin\\Application\\Services\\PluginRegistrar')->register('%plugin_slug%', __DIR__);
}
