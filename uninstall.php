<?php
/**
 * Uninstall handler for Leyka Boost.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$leyka_boost_option_names = array(
	'leyka_boost_settings',
	'leyka_boost_log_hash',
	'leyka_boost_log_last_rotation',
	'leyka_toolkit_settings',
	'leyka_close_settings',
	'leyka_close_stats',
);

foreach ( $leyka_boost_option_names as $leyka_boost_option_name ) {
	delete_option( $leyka_boost_option_name );
	delete_site_option( $leyka_boost_option_name );
}

$leyka_boost_like = $wpdb->esc_like( 'leyka_utm_tracker_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $leyka_boost_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

$leyka_boost_table_name = $wpdb->prefix . 'leyka_utm_tracker';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Cleanup on uninstall, table name from wpdb prefix, safe.
$wpdb->query( "DROP TABLE IF EXISTS {$leyka_boost_table_name}" );

$leyka_boost_upload_dir = wp_upload_dir();
$leyka_boost_logs_dir   = trailingslashit( $leyka_boost_upload_dir['basedir'] ) . 'leyka-boost/logs/';

if ( is_dir( $leyka_boost_logs_dir ) ) {
	$leyka_boost_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $leyka_boost_logs_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $leyka_boost_iterator as $leyka_boost_item ) {
		if ( $leyka_boost_item->isDir() ) {
			rmdir( $leyka_boost_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		} else {
			unlink( $leyka_boost_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	rmdir( $leyka_boost_logs_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
