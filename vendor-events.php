<?php
/**
 * Plugin Name: Vendor Events
 * Description: Lightweight vendor event submission system
 * Version: 1.0.0
 * Author: Nirmal Ram
 * Text Domain: vendor-events
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once VEN_PLUGIN_DIR . 'includes/class-venevents.php';

// Initialize plugin
function ven_init_plugin() {
    $plugin = VENEVENTS::instance();
}
add_action('plugins_loaded', 'ven_init_plugin');
