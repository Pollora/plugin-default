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
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Pollora\Modules\Domain\Enums\ModuleType;

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

pollora_register(ModuleType::Plugin, '%plugin_slug%', __DIR__);
