<?php
/**
 * Close Campaign module bootstrap.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEYKA_CLOSE_VERSION' ) ) {
	define( 'LEYKA_CLOSE_VERSION', '1.1.4' );
	define( 'LEYKA_CLOSE_PLUGIN_FILE', LEYKA_BOOST_FILE );
	define( 'LEYKA_CLOSE_PLUGIN_DIR', LEYKA_BOOST_PATH . 'includes/modules/close-campaign/' );
	define( 'LEYKA_CLOSE_PLUGIN_URL', LEYKA_BOOST_URL . 'includes/modules/close-campaign/' );
	define( 'LEYKA_CLOSE_PLUGIN_BASENAME', LEYKA_BOOST_BASENAME );
	define( 'LEYKA_CLOSE_LOGS_DIR', LEYKA_BOOST_LOGS_DIR );
	define( 'LEYKA_CLOSE_LOGS_URL', LeykaBoost_Logger::get_log_url() );
	define( 'LEYKA_CLOSE_OPTION_SETTINGS', 'leyka_close_settings' );
	define( 'LEYKA_CLOSE_OPTION_STATS', 'leyka_close_stats' );
}

/**
 * Close Campaign module.
 */
class LeykaBoost_Module_Close {
	/**
	 * Initialize module.
	 *
	 * @return void
	 */
	public function init() {
		require_once LEYKA_CLOSE_PLUGIN_DIR . 'class-statistics.php';
		require_once LEYKA_CLOSE_PLUGIN_DIR . 'class-ajax.php';
		require_once LEYKA_CLOSE_PLUGIN_DIR . 'class-admin.php';
		require_once LEYKA_CLOSE_PLUGIN_DIR . 'class-frontend.php';

		new Leyka_Close_Admin();

		$settings = get_option( LEYKA_CLOSE_OPTION_SETTINGS, array() );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		new Leyka_Close_Statistics();
		new Leyka_Close_Ajax();
		new Leyka_Close_Frontend();
	}

	/**
	 * Activate module defaults.
	 *
	 * @return void
	 */
	public static function activate() {
		$default_settings = array(
			'enabled'               => true,
			'auto_toggle_threshold' => 1000,
			'min_amount_to_show'    => 200,
			'active_color'          => '#43a047',
			'inactive_color'        => '#cccccc',
			'button_text'           => __( 'Close the whole campaign: {sum} ₽', 'leyka-boost' ),
			'show_icon'             => true,
			'border_color'          => '#e9ecef',
			'border_width'          => 1,
		);

		$default_stats = array(
			'total_toggles'       => 0,
			'total_activations'   => 0,
			'total_deactivations' => 0,
			'campaigns'           => array(),
			'last_reset'          => current_time( 'mysql' ),
		);

		add_option( LEYKA_CLOSE_OPTION_SETTINGS, $default_settings );
		add_option( LEYKA_CLOSE_OPTION_STATS, $default_stats );
	}
}
