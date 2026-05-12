<?php
/**
 * Plugin Name: Leyka Boost
 * Plugin URI: https://github.com/thegavir-dev/leyka-boost
 * Description: Additional tools for the Leyka donation plugin: UTM tracking, form improvements, campaign controls.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: StudioAVP
 * Author URI: https://github.com/thegavir-dev
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: leyka-boost
 * Domain Path: /languages
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEYKA_BOOST_VERSION', '1.0.0' );
define( 'LEYKA_BOOST_MIN_WP_VERSION', '6.4' );
define( 'LEYKA_BOOST_MIN_PHP_VERSION', '7.4' );
define( 'LEYKA_BOOST_MIN_LEYKA_VERSION', '3.20' );
define( 'LEYKA_BOOST_FILE', __FILE__ );
define( 'LEYKA_BOOST_BASENAME', plugin_basename( __FILE__ ) );
define( 'LEYKA_BOOST_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEYKA_BOOST_URL', plugin_dir_url( __FILE__ ) );
define( 'LEYKA_BOOST_LOGS_DIR', WP_CONTENT_DIR . '/uploads/leyka-boost/logs/' );

require_once LEYKA_BOOST_PATH . 'includes/class-core.php';

register_activation_hook( __FILE__, array( 'LeykaBoost_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LeykaBoost_Core', 'deactivate' ) );

LeykaBoost_Core::get_instance();
