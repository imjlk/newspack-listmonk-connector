<?php
/**
 * Plugin bootstrap.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/options.php';
require_once __DIR__ . '/listmonk/class-listmonk-client.php';
require_once __DIR__ . '/render/class-plain-text-builder.php';
require_once __DIR__ . '/render/class-raw-html-builder.php';

/**
 * Register Listmonk as a Newspack Newsletters ESP provider.
 *
 * @param array $providers Registered providers.
 * @return array
 */
function newspack_listmonk_connector_register_provider( array $providers ) {
	if ( ! newspack_listmonk_connector_can_register_newspack_provider() ) {
		return $providers;
	}

	$providers['listmonk'] = array(
		'name'       => __( 'Listmonk', 'newspack-listmonk-connector' ),
		'class'      => 'Newspack_Listmonk_Connector_Provider',
		'class_file' => NEWSPACK_LISTMONK_CONNECTOR_DIR . '/inc/provider/class-listmonk-provider.php',
	);

	return $providers;
}
add_filter( 'newspack_newsletters_registered_providers', 'newspack_listmonk_connector_register_provider' );

if ( is_admin() ) {
	require_once __DIR__ . '/admin/settings-page.php';
}
