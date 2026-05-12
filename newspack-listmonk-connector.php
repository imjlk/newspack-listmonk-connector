<?php
/**
 * Plugin Name:       Newspack Listmonk Connector
 * Description:       Companion ESP provider for sending Newspack Newsletters campaigns with Listmonk.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires Plugins:  newspack-newsletters
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            imjlk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       newspack-listmonk-connector
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEWSPACK_LISTMONK_CONNECTOR_VERSION', '0.1.0' );
define( 'NEWSPACK_LISTMONK_CONNECTOR_FILE', __FILE__ );
define( 'NEWSPACK_LISTMONK_CONNECTOR_DIR', __DIR__ );

require_once __DIR__ . '/inc/bootstrap.php';

foreach ( glob( __DIR__ . '/src/blocks/*/server.php' ) ?: array() as $newspack_listmonk_connector_server_module ) {
	require_once $newspack_listmonk_connector_server_module;
}

function newspack_listmonk_connector_get_build_root() {
	return __DIR__ . '/build/blocks';
}

function newspack_listmonk_connector_get_blocks_manifest_path() {
	return __DIR__ . '/build/blocks-manifest.php';
}

function newspack_listmonk_connector_register_blocks_from_manifest_fallback() {
	$build_root     = newspack_listmonk_connector_get_build_root();
	$manifest_path  = newspack_listmonk_connector_get_blocks_manifest_path();
	$manifest_data  = file_exists( $manifest_path ) ? require $manifest_path : array();

	if ( ! is_array( $manifest_data ) || empty( $manifest_data ) ) {
		$block_dirs = glob( $build_root . '/*', GLOB_ONLYDIR );
		if ( ! is_array( $block_dirs ) ) {
			return;
		}

		foreach ( $block_dirs as $block_dir ) {
			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
		return;
	}

	foreach ( array_keys( $manifest_data ) as $block_name ) {
		$block_slug = is_string( $block_name ) && str_contains( $block_name, '/' )
			? substr( $block_name, strpos( $block_name, '/' ) + 1 )
			: (string) $block_name;
		$block_dir  = trailingslashit( $build_root ) . $block_slug;

		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}
}

function newspack_listmonk_connector_register_blocks() {
	$build_root    = newspack_listmonk_connector_get_build_root();
	$manifest_path = newspack_listmonk_connector_get_blocks_manifest_path();

	if ( ! is_dir( $build_root ) ) {
		return;
	}

	if (
		file_exists( $manifest_path ) && function_exists( 'wp_register_block_metadata_collection' ) && function_exists( 'wp_register_block_types_from_metadata_collection' )
	) {
		wp_register_block_metadata_collection( $build_root, $manifest_path );
		wp_register_block_types_from_metadata_collection( $build_root, $manifest_path );
		return;
	}

	newspack_listmonk_connector_register_blocks_from_manifest_fallback();
}

function newspack_listmonk_connector_register_binding_sources() {
	foreach ( glob( __DIR__ . '/src/bindings/*/server.php' ) ?: array() as $binding_source_module ) {
		require_once $binding_source_module;
	}
}

function newspack_listmonk_connector_enqueue_binding_sources_editor() {
	$script_path = __DIR__ . '/build/bindings/index.js';
	$asset_path  = __DIR__ . '/build/bindings/index.asset.php';

	if ( ! file_exists( $script_path ) || ! file_exists( $asset_path ) ) {
		return;
	}

	$asset = require $asset_path;
	if ( ! is_array( $asset ) ) {
		$asset = array();
	}

	wp_enqueue_script(
		'newspack-listmonk-connector-binding-sources',
		plugins_url( 'build/bindings/index.js', __FILE__ ),
		isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
		isset( $asset['version'] ) ? $asset['version'] : filemtime( $script_path ),
		true
	);
}

function newspack_listmonk_connector_enqueue_editor_plugins_editor() {
	$script_path = __DIR__ . '/build/editor-plugins/index.js';
	$asset_path  = __DIR__ . '/build/editor-plugins/index.asset.php';
	$style_path  = __DIR__ . '/build/editor-plugins/style-index.css';
	$style_rtl_path = __DIR__ . '/build/editor-plugins/style-index-rtl.css';

	if ( ! file_exists( $script_path ) || ! file_exists( $asset_path ) ) {
		return;
	}

	$asset = require $asset_path;
	if ( ! is_array( $asset ) ) {
		$asset = array();
	}

	wp_enqueue_script(
		'newspack-listmonk-connector-editor-plugins',
		plugins_url( 'build/editor-plugins/index.js', __FILE__ ),
		isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
		isset( $asset['version'] ) ? $asset['version'] : filemtime( $script_path ),
		true
	);

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'newspack-listmonk-connector-editor-plugins',
			plugins_url( 'build/editor-plugins/style-index.css', __FILE__ ),
			array(),
			isset( $asset['version'] ) ? $asset['version'] : filemtime( $style_path )
		);
		if ( file_exists( $style_rtl_path ) ) {
			wp_style_add_data( 'newspack-listmonk-connector-editor-plugins', 'rtl', 'replace' );
		}
	}
}

function newspack_listmonk_connector_register_pattern_category() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'newspack-listmonk-connector',
			array(
				'label' => __( 'Newspack Listmonk Connector Patterns', 'newspack-listmonk-connector' ),
			)
		);
	}
}

function newspack_listmonk_connector_register_patterns() {
	foreach ( glob( __DIR__ . '/src/patterns/*.php' ) ?: array() as $pattern_module ) {
		require $pattern_module;
	}
}



function newspack_listmonk_connector_register_rest_resources() {
	foreach ( glob( __DIR__ . '/inc/rest/*.php' ) ?: array() as $rest_resource_module ) {
		require_once $rest_resource_module;
	}
}

add_action( 'init', 'newspack_listmonk_connector_register_blocks' );
add_action( 'init', 'newspack_listmonk_connector_register_binding_sources', 20 );
add_action( 'init', 'newspack_listmonk_connector_register_pattern_category' );
add_action( 'init', 'newspack_listmonk_connector_register_patterns', 20 );
add_action( 'enqueue_block_editor_assets', 'newspack_listmonk_connector_enqueue_binding_sources_editor' );
add_action( 'enqueue_block_editor_assets', 'newspack_listmonk_connector_enqueue_editor_plugins_editor' );
add_action( 'init', 'newspack_listmonk_connector_register_rest_resources', 20 );
