<?php
/**
 * Toolkit module bootstrap.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEYKA_TOOLKIT_VERSION' ) ) {
	define( 'LEYKA_TOOLKIT_VERSION', '1.0.0' );
	define( 'LEYKA_TOOLKIT_MIN_WP_VERSION', LEYKA_BOOST_MIN_WP_VERSION );
	define( 'LEYKA_TOOLKIT_MIN_PHP_VERSION', LEYKA_BOOST_MIN_PHP_VERSION );
	define( 'LEYKA_TOOLKIT_MIN_LEYKA_VERSION', LEYKA_BOOST_MIN_LEYKA_VERSION );
	define( 'LEYKA_TOOLKIT_FILE', LEYKA_BOOST_FILE );
	define( 'LEYKA_TOOLKIT_PATH', LEYKA_BOOST_PATH . 'includes/modules/toolkit/' );
}

require_once LEYKA_TOOLKIT_PATH . 'class-toolkit.php';

/**
 * Toolkit module.
 */
class LeykaBoost_Module_Toolkit {
	/**
	 * Initialize module.
	 *
	 * @return void
	 */
	public function init() {
		Leyka_Toolkit::get_instance();
	}

	/**
	 * Activate module defaults.
	 *
	 * @return void
	 */
	public static function activate() {
		Leyka_Toolkit::activate();
	}
}
