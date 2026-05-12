<?php
/**
 * Uninstall cleanup.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/inc/uninstall.php';

newspack_listmonk_connector_cleanup_local_data();
