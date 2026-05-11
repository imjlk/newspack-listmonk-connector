<?php
/**
 * Admin settings screen.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register settings page.
 */
function newspack_listmonk_connector_register_settings_page() {
	add_options_page(
		__( 'Newspack Listmonk', 'newspack-listmonk-connector' ),
		__( 'Newspack Listmonk', 'newspack-listmonk-connector' ),
		'manage_options',
		'newspack-listmonk-connector',
		'newspack_listmonk_connector_render_settings_page'
	);
}
add_action( 'admin_menu', 'newspack_listmonk_connector_register_settings_page' );

/**
 * Handle settings submission.
 */
function newspack_listmonk_connector_maybe_save_settings() {
	if ( empty( $_POST['newspack_listmonk_connector_settings_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'newspack_listmonk_connector_settings', 'newspack_listmonk_connector_settings_nonce' );

	$settings = array(
		'base_url'            => isset( $_POST['base_url'] ) ? wp_unslash( $_POST['base_url'] ) : '',
		'api_user'            => isset( $_POST['api_user'] ) ? wp_unslash( $_POST['api_user'] ) : '',
		'api_token'           => isset( $_POST['api_token'] ) ? wp_unslash( $_POST['api_token'] ) : '',
		'default_from_email'  => isset( $_POST['default_from_email'] ) ? wp_unslash( $_POST['default_from_email'] ) : '',
		'default_template_id' => isset( $_POST['default_template_id'] ) ? wp_unslash( $_POST['default_template_id'] ) : '',
		'default_list_ids'    => isset( $_POST['default_list_ids'] ) ? wp_unslash( $_POST['default_list_ids'] ) : '',
	);

	newspack_listmonk_connector_save_settings( $settings );

	if ( ! empty( $_POST['test_connection'] ) ) {
		$result = ( new Newspack_Listmonk_Connector_Listmonk_Client() )->test_connection();
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'newspack_listmonk_connector', 'connection_failed', $result->get_error_message(), 'error' );
		} else {
			add_settings_error( 'newspack_listmonk_connector', 'connection_ok', __( 'Listmonk connection succeeded.', 'newspack-listmonk-connector' ), 'success' );
		}
	} else {
		add_settings_error( 'newspack_listmonk_connector', 'settings_saved', __( 'Listmonk settings saved.', 'newspack-listmonk-connector' ), 'success' );
	}
}
add_action( 'admin_init', 'newspack_listmonk_connector_maybe_save_settings' );

/**
 * Render settings page.
 */
function newspack_listmonk_connector_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = newspack_listmonk_connector_get_settings( true );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Newspack Listmonk Connector', 'newspack-listmonk-connector' ); ?></h1>
		<?php settings_errors( 'newspack_listmonk_connector' ); ?>
		<form method="post">
			<?php wp_nonce_field( 'newspack_listmonk_connector_settings', 'newspack_listmonk_connector_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="base_url"><?php esc_html_e( 'Listmonk API URL', 'newspack-listmonk-connector' ); ?></label></th>
					<td><input class="regular-text" id="base_url" name="base_url" type="url" value="<?php echo esc_attr( $settings['base_url'] ); ?>" placeholder="https://listmonk.example.com" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="api_user"><?php esc_html_e( 'API user', 'newspack-listmonk-connector' ); ?></label></th>
					<td><input class="regular-text" id="api_user" name="api_user" type="text" value="<?php echo esc_attr( $settings['api_user'] ); ?>" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="api_token"><?php esc_html_e( 'API token', 'newspack-listmonk-connector' ); ?></label></th>
					<td>
						<input class="regular-text" id="api_token" name="api_token" type="password" value="" autocomplete="new-password" />
						<?php if ( ! empty( $settings['api_token'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'A token is saved. Leave this blank to keep it unchanged.', 'newspack-listmonk-connector' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="default_from_email"><?php esc_html_e( 'Default From email', 'newspack-listmonk-connector' ); ?></label></th>
					<td><input class="regular-text" id="default_from_email" name="default_from_email" type="text" value="<?php echo esc_attr( $settings['default_from_email'] ); ?>" placeholder="Newsroom <news@example.com>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="default_template_id"><?php esc_html_e( 'Default template ID', 'newspack-listmonk-connector' ); ?></label></th>
					<td><input id="default_template_id" name="default_template_id" type="number" min="0" value="<?php echo esc_attr( $settings['default_template_id'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="default_list_ids"><?php esc_html_e( 'Default list IDs', 'newspack-listmonk-connector' ); ?></label></th>
					<td><input class="regular-text" id="default_list_ids" name="default_list_ids" type="text" value="<?php echo esc_attr( implode( ',', $settings['default_list_ids'] ) ); ?>" placeholder="1,2" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'newspack-listmonk-connector' ) ); ?>
			<?php submit_button( __( 'Save and test connection', 'newspack-listmonk-connector' ), 'secondary', 'test_connection', false ); ?>
		</form>
	</div>
	<?php
}
