<?php
/**
 * Plugin Name: TapCard Pro
 * Plugin URI: https://example.com/
 * Description: NFC + QR digital profile platform with separate User and Customer PWAs.
 * Version: 1.7.7
 * Author: Apostolis Karamichalis
 * Author URI: https://h2oworld.gr/
 * Text Domain: tapcard-pro
 *
 * Created by Apostolis Karamichalis.
 */

defined('ABSPATH') || exit;

define('TCP_VERSION', '1.7.7');
define('TCP_FILE', __FILE__);
define('TCP_DIR', plugin_dir_path(__FILE__));
define('TCP_URL', plugin_dir_url(__FILE__));

// Create includes directory if it doesn't exist
$includes_dir = TCP_DIR . 'includes';
if (!is_dir($includes_dir)) {
    mkdir($includes_dir, 0755, true);
}

require_once TCP_DIR . 'includes/class-tcp-plugin.php';

register_activation_hook(__FILE__, ['TCP_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['TCP_Plugin', 'deactivate']);

TCP_Plugin::instance();
