<?php
/**
 * Compatibility helpers for Newspack Newsletters integration points.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'newspack_listmonk_connector_call_static_compat' ) ) {
	/**
	 * Call a static method with only the arguments accepted by its signature.
	 *
	 * @param string $class Class name.
	 * @param string $method Method name.
	 * @param array  $args Arguments.
	 * @return mixed|null
	 */
	function newspack_listmonk_connector_call_static_compat( $class, $method, array $args = array() ) {
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return null;
		}

		try {
			$reflection = new ReflectionMethod( $class, $method );
			if ( ! $reflection->isPublic() ) {
				return null;
			}
			return call_user_func_array( array( $class, $method ), array_slice( $args, 0, $reflection->getNumberOfParameters() ) );
		} catch ( ReflectionException $error ) {
			return null;
		}
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_can_register_newspack_provider' ) ) {
	/**
	 * Whether the Newspack provider base classes needed by this connector exist.
	 *
	 * @return bool
	 */
	function newspack_listmonk_connector_can_register_newspack_provider() {
		return class_exists( 'Newspack_Newsletters_Service_Provider' ) && class_exists( 'Newspack_Newsletters_Service_Provider_Controller' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_rest_namespace' ) ) {
	/**
	 * Get the Newspack REST namespace, optionally scoped to a provider service.
	 *
	 * @param string $service Optional service slug.
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_rest_namespace( $service = '' ) {
		if ( class_exists( 'Newspack_Newsletters_Service_Provider' ) && defined( 'Newspack_Newsletters_Service_Provider::BASE_NAMESPACE' ) ) {
			$namespace = constant( 'Newspack_Newsletters_Service_Provider::BASE_NAMESPACE' );
		} elseif ( class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::API_NAMESPACE' ) ) {
			$namespace = constant( 'Newspack_Newsletters::API_NAMESPACE' );
		} else {
			$namespace = 'newspack-newsletters/v1';
		}

		$namespace = trim( (string) $namespace, '/' );
		$service   = trim( (string) $service, '/' );

		return '' === $service ? $namespace : $namespace . '/' . $service;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_newsletter_post_type' ) ) {
	/**
	 * Get the Newspack newsletter post type.
	 *
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_newsletter_post_type() {
		if ( class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT' ) ) {
			return (string) constant( 'Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT' );
		}

		return 'newspack_nl_cpt';
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_email_html_meta_key' ) ) {
	/**
	 * Get the Newspack stored email HTML meta key.
	 *
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_email_html_meta_key() {
		if ( class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::EMAIL_HTML_META' ) ) {
			return (string) constant( 'Newspack_Newsletters::EMAIL_HTML_META' );
		}

		return 'newspack_email_html';
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_service_provider' ) ) {
	/**
	 * Get the active Newspack service provider slug.
	 *
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_service_provider() {
		$provider = newspack_listmonk_connector_call_static_compat( 'Newspack_Newsletters', 'service_provider' );
		if ( is_string( $provider ) && '' !== $provider ) {
			return $provider;
		}

		return (string) get_option( 'newspack_newsletters_service_provider', '' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_get_newspack_provider_instance' ) ) {
	/**
	 * Get a Newspack provider instance.
	 *
	 * @param string $provider Provider slug.
	 * @return mixed|null
	 */
	function newspack_listmonk_connector_get_newspack_provider_instance( $provider = 'listmonk' ) {
		$instance = newspack_listmonk_connector_call_static_compat( 'Newspack_Newsletters', 'get_service_provider_instance', array( $provider ) );
		if ( null !== $instance ) {
			return $instance;
		}

		if ( 'listmonk' !== $provider || 'listmonk' !== newspack_listmonk_connector_newspack_service_provider() || ! class_exists( 'Newspack_Listmonk_Connector_Provider' ) ) {
			return null;
		}

		static $fallback_provider = null;
		if ( null === $fallback_provider ) {
			$fallback_provider = new Newspack_Listmonk_Connector_Provider();
		}

		return $fallback_provider;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_authoring_permissions_check' ) ) {
	/**
	 * Check authoring permissions for Newspack editor routes.
	 *
	 * @param WP_REST_Request|null $request REST request.
	 * @return bool|WP_Error
	 */
	function newspack_listmonk_connector_newspack_authoring_permissions_check( $request = null ) {
		$result = newspack_listmonk_connector_call_static_compat( 'Newspack_Newsletters', 'api_authoring_permissions_check', array( $request ) );
		if ( null !== $result ) {
			return $result;
		}

		$post_id = 0;
		if ( $request instanceof WP_REST_Request ) {
			$post_id = absint( $request['id'] ?? $request['postId'] ?? 0 );
		}

		return $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_posts' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_validate_newsletter_id' ) ) {
	/**
	 * Validate a Newspack newsletter post ID.
	 *
	 * @param mixed                $value ID value.
	 * @param WP_REST_Request|null $request REST request.
	 * @param string               $param Param name.
	 * @return bool|WP_Error
	 */
	function newspack_listmonk_connector_newspack_validate_newsletter_id( $value, $request = null, $param = '' ) {
		$result = newspack_listmonk_connector_call_static_compat( 'Newspack_Newsletters', 'validate_newsletter_id', array( $value, $request, $param ) );
		if ( null !== $result ) {
			return $result;
		}

		$post = get_post( absint( $value ) );
		return $post instanceof WP_Post && newspack_listmonk_connector_newspack_newsletter_post_type() === $post->post_type;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_rest_response' ) ) {
	/**
	 * Wrap a Newspack provider REST response.
	 *
	 * @param mixed $response Response value.
	 * @return mixed
	 */
	function newspack_listmonk_connector_newspack_rest_response( $response ) {
		$wrapped = newspack_listmonk_connector_call_static_compat( 'Newspack_Newsletters_Service_Provider_Controller', 'get_api_response', array( $response ) );
		if ( null !== $wrapped ) {
			return $wrapped;
		}

		return is_wp_error( $response ) ? $response : rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_update_user_test_emails' ) ) {
	/**
	 * Persist fallback test email defaults for the current user.
	 *
	 * @param array $emails Emails.
	 * @return bool
	 */
	function newspack_listmonk_connector_update_user_test_emails( array $emails ) {
		$user_id   = get_current_user_id();
		$user_info = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user_id || ! $user_info ) {
			return false;
		}
		if ( 1 === count( $emails ) && $user_info->user_email === $emails[0] ) {
			return false;
		}

		return (bool) update_user_meta( $user_id, 'newspack_nl_test_emails', $emails );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_campaign_name' ) ) {
	/**
	 * Build a fallback Newspack campaign name.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_campaign_name( WP_Post $post ) {
		$campaign_name = get_post_meta( $post->ID, 'campaign_name', true );
		if ( $campaign_name ) {
			return (string) $campaign_name;
		}

		return sprintf( 'Newspack Newsletter (%d)', $post->ID );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newspack_sync_error_transient_name' ) ) {
	/**
	 * Build a fallback Newspack sync error transient name.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function newspack_listmonk_connector_newspack_sync_error_transient_name( $post_id ) {
		return sprintf( 'newspack_newsletters_error_%s_%s', absint( $post_id ), get_current_user_id() );
	}
}
