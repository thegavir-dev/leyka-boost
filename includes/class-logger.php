<?php
/**
 * Unified logger for Leyka Boost.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified file logger.
 */
final class LeykaBoost_Logger {
	const LEVEL_ERROR = 'ERROR';
	const LEVEL_INFO  = 'INFO';
	const LEVEL_DEBUG = 'DEBUG';

	/**
	 * Write log message.
	 *
	 * @param string $level   Log level.
	 * @param string $module  Module slug.
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $level, $module, $message ) {
		$level  = strtoupper( sanitize_key( $level ) );
		$module = sanitize_key( $module );

		if ( ! in_array( $level, self::get_levels(), true ) || ! self::can_log( $level ) ) {
			return;
		}

		self::rotate_if_needed();
		self::ensure_log_directory();

		$path = self::get_log_path();

		if ( ! $path ) {
			return;
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$message   = str_replace( array( "\r", "\n" ), ' ', wp_strip_all_tags( (string) $message ) );
		$line      = sprintf( "[%s] [%s] [%s] %s\n", $timestamp, $level, $module, $message );

		file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Write ERROR message.
	 *
	 * @param string $module  Module slug.
	 * @param string $message Message.
	 * @return void
	 */
	public static function error( $module, $message ) {
		self::log( self::LEVEL_ERROR, $module, $message );
	}

	/**
	 * Write INFO message.
	 *
	 * @param string $module  Module slug.
	 * @param string $message Message.
	 * @return void
	 */
	public static function info( $module, $message ) {
		self::log( self::LEVEL_INFO, $module, $message );
	}

	/**
	 * Write DEBUG message.
	 *
	 * @param string $module  Module slug.
	 * @param string $message Message.
	 * @return void
	 */
	public static function debug( $module, $message ) {
		self::log( self::LEVEL_DEBUG, $module, $message );
	}

	/**
	 * Get log file path.
	 *
	 * @return string
	 */
	public static function get_log_path() {
		return trailingslashit( LEYKA_BOOST_LOGS_DIR ) . 'leyka-boost-' . self::get_log_hash() . '.log';
	}

	/**
	 * Get log file URL.
	 *
	 * @return string
	 */
	public static function get_log_url() {
		$upload_dir = wp_upload_dir();
		$base_url   = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : content_url( 'uploads' );

		return trailingslashit( $base_url ) . 'leyka-boost/logs/leyka-boost-' . self::get_log_hash() . '.log';
	}

	/**
	 * Clear log file.
	 *
	 * @return void
	 */
	public static function clear() {
		$path = self::get_log_path();

		if ( file_exists( $path ) ) {
			file_put_contents( $path, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Remove entries older than the retention period.
	 *
	 * @return void
	 */
	public static function rotate() {
		$path = self::get_log_path();

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			update_option( 'leyka_boost_log_last_rotation', time() );
			return;
		}

		$retention_days = absint( LeykaBoost_Core::get_setting( 'log_retention_days', 30 ) );
		$retention_days = $retention_days > 0 ? $retention_days : 30;
		$threshold      = time() - ( DAY_IN_SECONDS * $retention_days );
		$lines          = file( $path, FILE_IGNORE_NEW_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file

		if ( false === $lines ) {
			return;
		}

		$kept = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/^\[([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})\]/', $line, $matches ) ) {
				$line_time = strtotime( $matches[1] );

				if ( $line_time && $line_time < $threshold ) {
					continue;
				}
			}

			$kept[] = $line;
		}

		$content = $kept ? implode( "\n", $kept ) . "\n" : '';

		file_put_contents( $path, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		update_option( 'leyka_boost_log_last_rotation', time() );
	}

	/**
	 * Get supported levels.
	 *
	 * @return array
	 */
	private static function get_levels() {
		return array( self::LEVEL_ERROR, self::LEVEL_INFO, self::LEVEL_DEBUG );
	}

	/**
	 * Check whether a level can be written.
	 *
	 * @param string $level Log level.
	 * @return bool
	 */
	private static function can_log( $level ) {
		if ( ! LeykaBoost_Core::get_setting( 'log_enabled', true ) ) {
			return false;
		}

		$configured_level = strtoupper( (string) LeykaBoost_Core::get_setting( 'log_level', self::LEVEL_INFO ) );
		$priority         = array(
			self::LEVEL_ERROR => 1,
			self::LEVEL_INFO  => 2,
			self::LEVEL_DEBUG => 3,
		);

		if ( ! isset( $priority[ $configured_level ], $priority[ $level ] ) ) {
			$configured_level = self::LEVEL_INFO;
		}

		return $priority[ $level ] <= $priority[ $configured_level ];
	}

	/**
	 * Rotate at most once per 24 hours.
	 *
	 * @return void
	 */
	private static function rotate_if_needed() {
		$last_rotation = absint( get_option( 'leyka_boost_log_last_rotation', 0 ) );

		if ( ! $last_rotation || ( time() - $last_rotation ) > DAY_IN_SECONDS ) {
			self::rotate();
		}
	}

	/**
	 * Ensure logs directory exists.
	 *
	 * @return void
	 */
	private static function ensure_log_directory() {
		if ( ! file_exists( LEYKA_BOOST_LOGS_DIR ) ) {
			wp_mkdir_p( LEYKA_BOOST_LOGS_DIR );
		}
	}

	/**
	 * Get or create log hash.
	 *
	 * @return string
	 */
	private static function get_log_hash() {
		$hash = get_option( 'leyka_boost_log_hash' );

		if ( ! $hash ) {
			$hash = wp_generate_password( 16, false, false );
			update_option( 'leyka_boost_log_hash', $hash );
		}

		return sanitize_key( $hash );
	}
}
