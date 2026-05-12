<?php
/**
 * UTM Tracker module bootstrap.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEYKA_UTM_TRACKER_VERSION' ) ) {
	define( 'LEYKA_UTM_TRACKER_VERSION', '1.5.1' );
	define( 'LEYKA_UTM_TRACKER_FILE', LEYKA_BOOST_FILE );
	define( 'LEYKA_UTM_TRACKER_PATH', LEYKA_BOOST_PATH . 'includes/modules/utm-tracker/' );
	define( 'LEYKA_UTM_TRACKER_URL', LEYKA_BOOST_URL . 'includes/modules/utm-tracker/' );
}

if ( ! function_exists( 'leyka_boost_utm_log' ) ) {
	/**
	 * Write UTM Tracker message to the unified Leyka Boost log.
	 *
	 * @param string $message Message.
	 * @param string $level   Log level.
	 * @return void
	 */
	function leyka_boost_utm_log( $message, $level = 'INFO' ) {
		$level = 'WARN' === strtoupper( (string) $level ) ? 'INFO' : $level;
		$level = strtoupper( (string) $level );

		if ( 'ERROR' === $level ) {
			LeykaBoost_Logger::error( 'utm-tracker', $message );
		} elseif ( 'DEBUG' === $level ) {
			LeykaBoost_Logger::debug( 'utm-tracker', $message );
		} else {
			LeykaBoost_Logger::info( 'utm-tracker', $message );
		}
	}
}

/**
 * UTM Tracker module.
 */
class LeykaBoost_Module_UTM {
	/**
	 * Initialize module.
	 *
	 * @return void
	 */
	public function init() {
		require_once LEYKA_UTM_TRACKER_PATH . 'class-db.php';
		require_once LEYKA_UTM_TRACKER_PATH . 'class-utm.php';
		require_once LEYKA_UTM_TRACKER_PATH . 'class-tracker.php';
		require_once LEYKA_UTM_TRACKER_PATH . 'class-analytics.php';
		require_once LEYKA_UTM_TRACKER_PATH . 'class-generator.php';
		require_once LEYKA_UTM_TRACKER_PATH . 'class-admin.php';

		LeykaUTMTrackerDB::maybe_upgrade_schema();
		add_action( 'init', array( 'LeykaUTMTrackerUTM', 'capture_utm' ), 2 );
		add_action( 'wp_insert_post', array( 'LeykaUTMTrackerTracker', 'handle_donation_created' ), 10, 3 );
		add_action( 'transition_post_status', array( 'LeykaUTMTrackerTracker', 'handle_status_change' ), 10, 3 );
		add_action( 'admin_menu', array( 'LeykaUTMTrackerAdmin', 'menu' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_post_lutm_export_csv', array( 'LeykaUTMTrackerAdmin', 'handle_csv_export' ) );
		add_action( 'wp_ajax_lutm_gen_save', array( 'LeykaUTMTrackerGenerator', 'ajax_save' ) );
		add_action( 'wp_ajax_lutm_gen_load', array( 'LeykaUTMTrackerGenerator', 'ajax_load' ) );
		add_action( 'wp_ajax_lutm_gen_clear', array( 'LeykaUTMTrackerGenerator', 'ajax_clear' ) );
	}

	/**
	 * Enqueue frontend UTM capture script.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_scripts() {
		wp_enqueue_script(
			'leyka-utm-capture',
			LEYKA_UTM_TRACKER_URL . 'assets/js/utm-capture.js',
			array(),
			LEYKA_UTM_TRACKER_VERSION,
			true
		);
	}

	/**
	 * Activate module database.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once LEYKA_UTM_TRACKER_PATH . 'class-db.php';
		LeykaUTMTrackerDB::activate();
	}
}
