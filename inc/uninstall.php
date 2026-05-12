<?php
/**
 * Local cleanup helpers used during uninstall.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete local connector data that may contain credentials.
 *
 * Remote Listmonk campaigns/subscribers and newsletter post meta are preserved.
 *
 * @return void
 */
function newspack_listmonk_connector_cleanup_local_data() {
	delete_option( 'newspack_listmonk_connector_settings' );
	newspack_listmonk_connector_delete_sync_error_transients();
}

/**
 * Delete transient rows used for Newspack sync errors.
 *
 * @return void
 */
function newspack_listmonk_connector_delete_sync_error_transients() {
	global $wpdb;

	$transient_like = $wpdb->esc_like( '_transient_newspack_listmonk_connector_sync_error_' ) . '%';
	$timeout_like   = $wpdb->esc_like( '_transient_timeout_newspack_listmonk_connector_sync_error_' ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$option_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$transient_like
		)
	);

	foreach ( $option_names as $option_name ) {
		delete_transient( substr( (string) $option_name, strlen( '_transient_' ) ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$transient_like,
			$timeout_like
		)
	);
}
