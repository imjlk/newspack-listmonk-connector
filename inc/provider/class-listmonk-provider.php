<?php
/**
 * Newspack Newsletters Listmonk ESP provider.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Newspack\Newsletters\Send_List;
use Newspack\Newsletters\Send_Lists;

require_once dirname( __DIR__ ) . '/compat.php';
require_once __DIR__ . '/class-listmonk-controller.php';

/**
 * Listmonk service provider.
 */
final class Newspack_Listmonk_Connector_Provider extends Newspack_Newsletters_Service_Provider {
	/**
	 * Provider display name.
	 *
	 * @var string
	 */
	public $name = 'Listmonk';

	/**
	 * Whether the provider supports Newspack local-list/tag features.
	 *
	 * @var bool
	 */
	public static $support_local_lists = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service    = 'listmonk';
		$this->controller = new Newspack_Listmonk_Connector_Controller( $this );

		add_action( 'updated_post_meta', array( $this, 'save' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'trash' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'delete' ), 10, 1 );

		parent::__construct();
	}

	/**
	 * Get configuration for conditional tag support.
	 *
	 * @return array
	 */
	public static function get_conditional_tag_support() {
		return array(
			'support_url' => 'https://listmonk.app/docs/templating/',
			'example'     => array(
				'before' => '{{ if eq .Subscriber.Attribs.membership "paid" }}',
				'after'  => '{{ end }}',
			),
		);
	}

	/**
	 * Provider labels.
	 *
	 * @param mixed $context Label context.
	 * @return array
	 */
	public static function get_labels( $context = '' ) {
		return array_merge(
			parent::get_labels( $context ),
			array(
				'name'       => 'Listmonk',
				'list'       => __( 'list', 'newspack-listmonk-connector' ),
				'lists'      => __( 'lists', 'newspack-listmonk-connector' ),
				'List'       => __( 'List', 'newspack-listmonk-connector' ),
				'Lists'      => __( 'Lists', 'newspack-listmonk-connector' ),
				'tag_prefix' => 'Newspack: ',
			)
		);
	}

	/**
	 * Get stored API credentials.
	 *
	 * @return array
	 */
	public function api_credentials() {
		$settings = newspack_listmonk_connector_get_settings( true );
		return array(
			'base_url'  => $settings['base_url'],
			'api_user'  => $settings['api_user'],
			'api_token' => $settings['api_token'],
		);
	}

	/**
	 * Save API credentials.
	 *
	 * @param object|array $credentials API credentials.
	 * @return bool|WP_Error
	 */
	public function set_api_credentials( $credentials ) {
		$credentials = (array) $credentials;
		$settings    = newspack_listmonk_connector_get_settings( true );

		$settings['base_url']  = $credentials['base_url'] ?? $credentials['url'] ?? $settings['base_url'];
		$settings['api_user']  = $credentials['api_user'] ?? $credentials['user'] ?? $settings['api_user'];
		$settings['api_token'] = $credentials['api_token'] ?? $credentials['token'] ?? $settings['api_token'];

		$settings = newspack_listmonk_connector_save_settings( $settings );
		if ( empty( $settings['base_url'] ) || empty( $settings['api_user'] ) || empty( $settings['api_token'] ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_credentials',
				__( 'Please enter the Listmonk API URL, user, and token.', 'newspack-listmonk-connector' )
			);
		}

		return true;
	}

	/**
	 * Whether credentials are configured.
	 *
	 * @return bool
	 */
	public function has_api_credentials() {
		return $this->client()->has_credentials();
	}

	/**
	 * Test the Listmonk connection.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		return $this->client()->test_connection();
	}

	/**
	 * Set the selected campaign list.
	 *
	 * @param string $post_id Post ID.
	 * @param string $list_id List ID.
	 * @return array|WP_Error
	 */
	public function list( $post_id, $list_id ) {
		update_post_meta( absint( $post_id ), 'send_list_id', absint( $list_id ) );
		return $this->retrieve( absint( $post_id ) );
	}

	/**
	 * Retrieve campaign state for Newspack editor UI.
	 *
	 * @param int $post_id Post ID.
	 * @return array|WP_Error
	 */
	public function retrieve( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_post',
				__( 'Newsletter post not found.', 'newspack-listmonk-connector' )
			);
		}

		$list_ids = $this->get_post_list_ids( $post );
		$lists    = $this->get_send_lists(
			array(
				'ids'  => $list_ids,
				'type' => 'list',
			),
			true
		);
		if ( is_wp_error( $lists ) ) {
			return $lists;
		}

		return array(
			'campaign'                           => true,
			'campaign_id'                        => get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ),
			'listmonk_campaign_id'               => get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ),
			'listmonk_campaign_uuid'             => get_post_meta( $post_id, '_wtnl_listmonk_campaign_uuid', true ),
			'listmonk_last_status'               => get_post_meta( $post_id, '_wtnl_listmonk_last_status', true ),
			'listmonk_last_synced_at'            => get_post_meta( $post_id, '_wtnl_listmonk_last_synced_at', true ),
			'listmonk_last_error'                => get_post_meta( $post_id, '_wtnl_listmonk_last_error', true ),
			'listmonk_last_error_code'           => get_post_meta( $post_id, '_wtnl_listmonk_last_error_code', true ),
			'listmonk_last_error_at'             => get_post_meta( $post_id, '_wtnl_listmonk_last_error_at', true ),
			'send_list_id'                       => ! empty( $list_ids ) ? (string) $list_ids[0] : '',
			'lists'                              => $lists,
			'senderName'                         => get_post_meta( $post_id, 'senderName', true ),
			'senderEmail'                        => get_post_meta( $post_id, 'senderEmail', true ),
			'supports_multiple_test_recipients' => true,
		);
	}

	/**
	 * Send a test email.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $emails Emails.
	 * @return array|WP_Error
	 */
	public function test( $post_id, $emails ) {
		$emails = array_values(
			array_filter(
				array_map( 'sanitize_email', (array) $emails ),
				'is_email'
			)
		);
		if ( empty( $emails ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_test_email',
				__( 'Please enter at least one valid test email address.', 'newspack-listmonk-connector' )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_post',
				__( 'Newsletter post not found.', 'newspack-listmonk-connector' )
			);
		}

		$sync = $this->sync( $post );
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}

		$payload = $this->build_listmonk_campaign_payload( $post );
		$result  = $this->client()->send_test( absint( $sync['campaign_id'] ), $emails, $payload );
		if ( is_wp_error( $result ) ) {
			$this->store_last_error( $post->ID, $result );
			return $result;
		}

		return array(
			'message' => sprintf(
				/* translators: %s: comma-separated email addresses. */
				__( 'Listmonk test message sent to %s.', 'newspack-listmonk-connector' ),
				implode( ', ', $emails )
			),
			'result'  => $result,
		);
	}

	/**
	 * Sync a Newspack newsletter to a Listmonk draft campaign.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return array|WP_Error
	 */
	public function sync( $post ) {
		if ( ! $this->has_api_credentials() ) {
			return new WP_Error(
				'newspack_listmonk_connector_missing_credentials',
				__( 'Listmonk API credentials are missing.', 'newspack-listmonk-connector' )
			);
		}
		if ( empty( $post->post_title ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_empty_subject',
				__( 'The newsletter subject cannot be empty.', 'newspack-listmonk-connector' )
			);
		}

		delete_transient( $this->get_sync_error_transient_name( $post->ID ) );

		$campaign_id = absint( get_post_meta( $post->ID, '_wtnl_listmonk_campaign_id', true ) );
		$payload     = $this->build_listmonk_campaign_payload( $post );
		$hash        = md5( wp_json_encode( $payload ) );

		if ( empty( $payload['lists'] ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_missing_list',
				__( 'Please select at least one Listmonk list.', 'newspack-listmonk-connector' )
			);
		}

		if ( $campaign_id && $hash === get_post_meta( $post->ID, '_wtnl_listmonk_payload_hash', true ) ) {
			return $this->build_sync_response( $post, $campaign_id, array() );
		}

		$result = $campaign_id ? $this->client()->update_campaign( $campaign_id, $payload ) : $this->client()->create_campaign( $payload );
		if ( is_wp_error( $result ) ) {
			$this->store_last_error( $post->ID, $result );
			set_transient(
				$this->get_sync_error_transient_name( $post->ID ),
				__( 'Listmonk sync error: ', 'newspack-listmonk-connector' ) . $result->get_error_message(),
				45
			);
			return $result;
		}

		$data        = $result['data'] ?? $result;
		$campaign_id = absint( $data['id'] ?? $campaign_id );

		update_post_meta( $post->ID, '_wtnl_listmonk_campaign_id', $campaign_id );
		if ( ! empty( $data['uuid'] ) ) {
			update_post_meta( $post->ID, '_wtnl_listmonk_campaign_uuid', sanitize_text_field( $data['uuid'] ) );
		}
		update_post_meta( $post->ID, '_wtnl_listmonk_payload_hash', $hash );
		update_post_meta( $post->ID, '_wtnl_listmonk_last_synced_at', gmdate( 'c' ) );
		update_post_meta( $post->ID, '_wtnl_listmonk_last_status', sanitize_text_field( $data['status'] ?? 'draft' ) );
		$this->clear_last_error( $post->ID );

		return $this->build_sync_response( $post, $campaign_id, $data );
	}

	/**
	 * Send or schedule a Listmonk campaign.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return true|WP_Error
	 */
	public function send( $post ) {
		$sync = $this->sync( $post );
		if ( is_wp_error( $sync ) ) {
			$this->store_last_error( $post->ID, $sync );
			set_transient(
				$this->get_sync_error_transient_name( $post->ID ),
				__( 'Listmonk send error: ', 'newspack-listmonk-connector' ) . $sync->get_error_message(),
				45
			);
			return $sync;
		}

		$status = 'future' === $post->post_status ? 'scheduled' : 'running';
		$result = $this->client()->set_status( absint( $sync['campaign_id'] ), $status );
		if ( is_wp_error( $result ) ) {
			$this->store_last_error( $post->ID, $result );
			set_transient(
				$this->get_sync_error_transient_name( $post->ID ),
				__( 'Listmonk send error: ', 'newspack-listmonk-connector' ) . $result->get_error_message(),
				45
			);
			return $result;
		}

		update_post_meta( $post->ID, '_wtnl_listmonk_last_status', $status );
		$this->clear_last_error( $post->ID );
		delete_transient( $this->get_sync_error_transient_name( $post->ID ) );
		return true;
	}

	/**
	 * Sync the ESP campaign after Newspack refreshes email HTML.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public function save( $meta_id, $post_id, $meta_key ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( newspack_listmonk_connector_newspack_email_html_meta_key() !== $meta_key ) {
			return;
		}
		if ( newspack_listmonk_connector_newspack_service_provider() !== $this->service ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'trash' === $post->post_status ) {
			return;
		}

		$this->sync( $post );
	}

	/**
	 * Handle newsletter trash cleanup.
	 *
	 * @param string $post_id Post ID.
	 * @return void
	 */
	public function trash( $post_id ) {
		$this->archive_post_campaign( absint( $post_id ), 'trash' );
	}

	/**
	 * Handle permanent newsletter deletion cleanup.
	 *
	 * @param string $post_id Post ID.
	 * @return void
	 */
	public function delete( $post_id ) {
		$this->archive_post_campaign( absint( $post_id ), 'delete' );
	}

	/**
	 * List active Listmonk lists.
	 *
	 * @return array|WP_Error
	 */
	public function get_lists() {
		$lists = $this->client()->get_lists();
		if ( is_wp_error( $lists ) ) {
			return $lists;
		}

		return array_values(
			array_map(
				static function ( $list ) {
					return array(
						'id'               => absint( $list['id'] ?? 0 ),
						'name'             => (string) ( $list['name'] ?? '' ),
						'type'             => (string) ( $list['type'] ?? '' ),
						'optin'            => (string) ( $list['optin'] ?? '' ),
						'subscriber_count' => absint( $list['subscriber_count'] ?? $list['subscribers'] ?? 0 ),
					);
				},
				$lists
			)
		);
	}

	/**
	 * Get lists in Newspack Send_List shape.
	 *
	 * @param array $args Args.
	 * @param bool  $to_array Convert to arrays.
	 * @return array|WP_Error
	 */
	public function get_send_lists( $args = array(), $to_array = false ) {
		if ( class_exists( Send_Lists::class ) ) {
			$args = wp_parse_args( $args, Send_Lists::get_default_args() );
		}

		$lists = $this->get_lists();
		if ( is_wp_error( $lists ) ) {
			return $lists;
		}

		if ( ! empty( $args['ids'] ) ) {
			$ids   = array_map( 'absint', (array) $args['ids'] );
			$lists = array_values(
				array_filter(
					$lists,
					static function ( $list ) use ( $ids ) {
						return in_array( absint( $list['id'] ), $ids, true );
					}
				)
			);
		}

		if ( ! empty( $args['search'] ) && ! is_array( $args['search'] ) ) {
			$search = strtolower( (string) $args['search'] );
			$lists  = array_values(
				array_filter(
					$lists,
					static function ( $list ) use ( $search ) {
						return false !== strpos( strtolower( (string) $list['name'] ), $search );
					}
				)
			);
		}

		if ( ! empty( $args['limit'] ) ) {
			$lists = array_slice( $lists, 0, absint( $args['limit'] ) );
		}

		$send_lists = array_map(
			function ( $list ) {
				$data = array(
					'provider'    => $this->service,
					'type'        => 'list',
					'id'          => (string) $list['id'],
					'name'        => $list['name'],
					'entity_type' => 'list',
					'count'       => $list['subscriber_count'],
				);
				return class_exists( Send_List::class ) ? new Send_List( $data ) : $data;
			},
			$lists
		);

		if ( $to_array ) {
			$send_lists = array_map(
				static function ( $list ) {
					return is_object( $list ) && method_exists( $list, 'to_array' ) ? $list->to_array() : (array) $list;
				},
				$send_lists
			);
		}

		return $send_lists;
	}

	/**
	 * Add or update a Listmonk subscriber.
	 *
	 * @param array       $contact Contact data.
	 * @param string|bool $list_id List ID.
	 * @return array|WP_Error
	 */
	public function add_contact( $contact, $list_id = false ) {
		$payload = $this->build_subscriber_payload( $contact, $list_id );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$client     = $this->client();
		$subscriber = $client->find_subscriber_by_email( $payload['email'] );
		if ( is_wp_error( $subscriber ) ) {
			if ( 'newspack_listmonk_connector_subscriber_not_found' !== $subscriber->get_error_code() ) {
				return $subscriber;
			}

			return $client->create_subscriber( $payload );
		}

		$subscriber_id = absint( $subscriber['id'] ?? 0 );
		if ( ! $subscriber_id ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_id',
				__( 'Listmonk subscriber response did not include a valid ID.', 'newspack-listmonk-connector' )
			);
		}
		if ( $this->is_subscriber_blocklisted( $subscriber ) ) {
			return $this->subscriber_blocklisted_error( $payload['email'], $subscriber );
		}

		$update_payload = array(
			'email'                    => $payload['email'],
			'name'                     => $payload['name'],
			'status'                   => $payload['status'],
			'attribs'                  => $payload['attribs'],
			'preconfirm_subscriptions' => $payload['preconfirm_subscriptions'],
		);
		$updated        = $client->update_subscriber( $subscriber_id, $update_payload );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( ! empty( $payload['lists'] ) ) {
			$list_update = $client->update_subscriber_lists(
				array( $subscriber_id ),
				$payload['lists'],
				'add',
				$this->get_subscriber_list_add_status( $payload['email'], $payload['lists'] )
			);
			if ( is_wp_error( $list_update ) ) {
				return $list_update;
			}
		}

		return $updated;
	}

	/**
	 * Get contact data.
	 *
	 * @param string $email Email.
	 * @param bool   $return_details Return details.
	 * @return array|WP_Error
	 */
	public function get_contact_data( $email, $return_details = false ) {
		$client     = $this->client();
		$subscriber = $client->find_subscriber_by_email( $email );
		if ( is_wp_error( $subscriber ) ) {
			return $subscriber;
		}

		$bounces       = array();
		$subscriber_id = absint( $subscriber['id'] ?? 0 );
		if ( $subscriber_id ) {
			$bounce_response = $client->get_subscriber_bounces( $subscriber_id );
			if ( ! is_wp_error( $bounce_response ) ) {
				$bounces                       = $bounce_response;
				$subscriber['_listmonk_bounces'] = $bounces;
			}
		}

		if ( $return_details ) {
			return $subscriber;
		}

		$status = sanitize_key( $subscriber['status'] ?? '' );

		return array(
			'id'             => $subscriber_id,
			'uuid'           => (string) ( $subscriber['uuid'] ?? '' ),
			'email'          => sanitize_email( $subscriber['email'] ?? '' ),
			'name'           => sanitize_text_field( $subscriber['name'] ?? '' ),
			'status'         => $status,
			'is_blocklisted' => 'blocklisted' === $status,
			'bounce_count'   => count( $bounces ),
			'has_bounces'    => ! empty( $bounces ),
			'lists'          => $this->extract_subscriber_list_ids( $subscriber ),
		);
	}

	/**
	 * Get contact lists.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function get_contact_lists( $email ) {
		$subscriber = $this->client()->find_subscriber_by_email( $email );
		if ( is_wp_error( $subscriber ) ) {
			return array();
		}

		return $this->extract_subscriber_list_ids( $subscriber );
	}

	/**
	 * Update contact lists.
	 *
	 * @param string $email Email.
	 * @param array  $lists_to_add Lists to add.
	 * @param array  $lists_to_remove Lists to remove.
	 * @return true|WP_Error
	 */
	public function update_contact_lists( $email, $lists_to_add = array(), $lists_to_remove = array() ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_email',
				__( 'A valid subscriber email is required.', 'newspack-listmonk-connector' )
			);
		}

		$client     = $this->client();
		$subscriber = $client->find_subscriber_by_email( $email );
		if ( is_wp_error( $subscriber ) ) {
			return $subscriber;
		}

		$subscriber_id   = absint( $subscriber['id'] ?? 0 );
		$lists_to_add    = newspack_listmonk_connector_normalize_list_ids( $lists_to_add );
		$lists_to_remove = newspack_listmonk_connector_normalize_list_ids( $lists_to_remove );
		if ( ! $subscriber_id ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_id',
				__( 'Listmonk subscriber response did not include a valid ID.', 'newspack-listmonk-connector' )
			);
		}
		if ( $this->is_subscriber_blocklisted( $subscriber ) ) {
			return $this->subscriber_blocklisted_error( $email, $subscriber );
		}

		if ( ! empty( $lists_to_add ) ) {
			$add = $client->update_subscriber_lists(
				array( $subscriber_id ),
				$lists_to_add,
				'add',
				$this->get_subscriber_list_add_status( $email, $lists_to_add )
			);
			if ( is_wp_error( $add ) ) {
				return $add;
			}
		}

		if ( ! empty( $lists_to_remove ) ) {
			$remove = $client->update_subscriber_lists( array( $subscriber_id ), $lists_to_remove, 'remove' );
			if ( is_wp_error( $remove ) ) {
				return $remove;
			}
		}

		return true;
	}

	/**
	 * Get tag ID.
	 *
	 * @param string $tag_name Tag.
	 * @param bool   $create_if_not_found Create if missing.
	 * @param string $list_id List ID.
	 * @return WP_Error
	 */
	public function get_tag_id( $tag_name, $create_if_not_found = true, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Get tag by ID.
	 *
	 * @param int    $tag_id Tag ID.
	 * @param string $list_id List ID.
	 * @return WP_Error
	 */
	public function get_tag_by_id( $tag_id, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Create tag.
	 *
	 * @param string $tag Tag.
	 * @param string $list_id List ID.
	 * @return WP_Error
	 */
	public function create_tag( $tag, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Update tag.
	 *
	 * @param string|int $tag_id Tag ID.
	 * @param string     $tag Tag.
	 * @param string     $list_id List ID.
	 * @return WP_Error
	 */
	public function update_tag( $tag_id, $tag, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Add tag to contact.
	 *
	 * @param string     $email Email.
	 * @param string|int $tag Tag.
	 * @param string     $list_id List ID.
	 * @return WP_Error
	 */
	public function add_tag_to_contact( $email, $tag, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Remove tag from contact.
	 *
	 * @param string     $email Email.
	 * @param string|int $tag Tag.
	 * @param string     $list_id List ID.
	 * @return WP_Error
	 */
	public function remove_tag_from_contact( $email, $tag, $list_id = null ) {
		return $this->not_implemented();
	}

	/**
	 * Get contact tags.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public function get_contact_tags_ids( $email ) {
		return array();
	}

	/**
	 * Get usage report.
	 *
	 * @return WP_Error
	 */
	public function get_usage_report() {
		return $this->not_implemented( __( 'Listmonk usage reports are not implemented yet.', 'newspack-listmonk-connector' ) );
	}

	/**
	 * Whether a Listmonk subscriber is blocklisted.
	 *
	 * @param array $subscriber Subscriber data.
	 * @return bool
	 */
	private function is_subscriber_blocklisted( array $subscriber ) {
		return 'blocklisted' === sanitize_key( $subscriber['status'] ?? '' );
	}

	/**
	 * Build a blocklisted subscriber error.
	 *
	 * @param string $email Email address.
	 * @param array  $subscriber Subscriber data.
	 * @return WP_Error
	 */
	private function subscriber_blocklisted_error( $email, array $subscriber ) {
		return new WP_Error(
			'newspack_listmonk_connector_subscriber_blocklisted',
			__( 'The Listmonk subscriber is blocklisted and must be reviewed in Listmonk before it can be resubscribed.', 'newspack-listmonk-connector' ),
			array(
				'email'         => sanitize_email( $email ),
				'subscriber_id' => absint( $subscriber['id'] ?? 0 ),
				'status'        => sanitize_key( $subscriber['status'] ?? '' ),
			)
		);
	}

	/**
	 * Build a Listmonk subscriber payload from Newspack contact data.
	 *
	 * @param array       $contact Contact data.
	 * @param string|bool $list_id List ID.
	 * @return array|WP_Error
	 */
	private function build_subscriber_payload( $contact, $list_id = false ) {
		$contact = is_array( $contact ) ? $contact : array();
		$email   = strtolower( sanitize_email( $contact['email'] ?? $contact['email_address'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_email',
				__( 'A valid subscriber email is required.', 'newspack-listmonk-connector' )
			);
		}

		$name = sanitize_text_field( $contact['name'] ?? '' );
		if ( '' === $name ) {
			$name = trim(
				sprintf(
					'%s %s',
					sanitize_text_field( $contact['first_name'] ?? '' ),
					sanitize_text_field( $contact['last_name'] ?? '' )
				)
			);
		}

		$list_ids = false === $list_id ? newspack_listmonk_connector_get_settings( true )['default_list_ids'] : (array) $list_id;
		$list_ids = newspack_listmonk_connector_normalize_list_ids( $list_ids );
		$metadata = isset( $contact['metadata'] ) && is_array( $contact['metadata'] ) ? $contact['metadata'] : array();

		$payload = array(
			'email'                    => $email,
			'name'                     => $name,
			'status'                   => 'enabled',
			'lists'                    => $list_ids,
			'attribs'                  => $this->sanitize_subscriber_attribs( $metadata ),
			'preconfirm_subscriptions' => (bool) apply_filters(
				'newspack_listmonk_connector_preconfirm_subscriptions',
				false,
				$contact,
				$list_ids
			),
		);

		/**
		 * Filter the Listmonk subscriber payload before create/update requests.
		 *
		 * @param array $payload Subscriber payload.
		 * @param array $contact Newspack contact data.
		 * @param int[] $list_ids List IDs.
		 */
		return apply_filters( 'newspack_listmonk_connector_subscriber_payload', $payload, $contact, $list_ids );
	}

	/**
	 * Get the Listmonk list membership status used when adding an existing subscriber.
	 *
	 * @param string $email Email address.
	 * @param int[]  $list_ids List IDs.
	 * @return string
	 */
	private function get_subscriber_list_add_status( $email, array $list_ids ) {
		$status = sanitize_key(
			(string) apply_filters(
				'newspack_listmonk_connector_subscriber_list_add_status',
				'unconfirmed',
				$email,
				$list_ids
			)
		);

		return in_array( $status, array( 'confirmed', 'unconfirmed', 'unsubscribed' ), true ) ? $status : 'unconfirmed';
	}

	/**
	 * Sanitize subscriber attributes recursively.
	 *
	 * @param mixed $attribs Raw attributes.
	 * @return array
	 */
	private function sanitize_subscriber_attribs( $attribs ) {
		if ( ! is_array( $attribs ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $attribs as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_subscriber_attribs( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( null === $value ) {
				$sanitized[ $key ] = null;
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Extract numeric list IDs from a Listmonk subscriber response.
	 *
	 * @param array $subscriber Subscriber data.
	 * @return int[]
	 */
	private function extract_subscriber_list_ids( array $subscriber ) {
		$lists = $subscriber['lists'] ?? array();
		if ( ! is_array( $lists ) ) {
			return array();
		}

		$list_ids = array();
		foreach ( $lists as $list ) {
			$list_ids[] = is_array( $list ) ? absint( $list['id'] ?? 0 ) : absint( $list );
		}

		return newspack_listmonk_connector_normalize_list_ids( $list_ids );
	}

	/**
	 * Client factory.
	 *
	 * @return Newspack_Listmonk_Connector_Listmonk_Client
	 */
	private function client() {
		return new Newspack_Listmonk_Connector_Listmonk_Client();
	}

	/**
	 * Build a Listmonk campaign payload from a newsletter post.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return array
	 */
	private function build_listmonk_campaign_payload( WP_Post $post ) {
		$settings      = newspack_listmonk_connector_get_settings( true );
		$html_builder  = new Newspack_Listmonk_Connector_Raw_HTML_Builder();
		$text_builder  = new Newspack_Listmonk_Connector_Plain_Text_Builder();
		$from_email    = $this->get_from_email( $post, $settings );
		$template_id   = absint( get_post_meta( $post->ID, '_wtnl_listmonk_template_id', true ) );
		$template_id   = $template_id ? $template_id : absint( $settings['default_template_id'] );
		$raw_html      = $html_builder->build( $post, array( 'template_id' => $template_id ) );
		$plain_text    = $text_builder->build( $raw_html );
		$list_ids      = $this->get_post_list_ids( $post );
		$campaign_name = $this->get_newspack_campaign_name( $post );

		$payload = array(
			'name'         => $campaign_name,
			'subject'      => html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'lists'        => $list_ids,
			'type'         => 'regular',
			'content_type' => 'html',
			'body'         => $raw_html,
			'altbody'      => $plain_text,
			'messenger'    => 'email',
			'tags'         => array( 'newspack', 'wp-post:' . $post->ID ),
		);

		if ( '' !== $from_email ) {
			$payload['from_email'] = $from_email;
		}
		if ( $template_id ) {
			$payload['template_id'] = $template_id;
		}
		if ( 'future' === $post->post_status && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
			$payload['send_at'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_date_gmt ) );
		}

		return (array) apply_filters( 'newspack_listmonk_connector_campaign_payload', $payload, $post );
	}

	/**
	 * Get configured post list IDs.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return int[]
	 */
	private function get_post_list_ids( WP_Post $post ) {
		$settings = newspack_listmonk_connector_get_settings( true );
		$list_ids = newspack_listmonk_connector_normalize_list_ids( get_post_meta( $post->ID, 'send_list_id', true ) );
		if ( empty( $list_ids ) ) {
			$list_ids = newspack_listmonk_connector_normalize_list_ids( get_post_meta( $post->ID, '_wtnl_listmonk_list_ids', true ) );
		}
		if ( empty( $list_ids ) ) {
			$list_ids = newspack_listmonk_connector_normalize_list_ids( $settings['default_list_ids'] );
		}

		return $list_ids;
	}

	/**
	 * Resolve the From email.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @param array   $settings Settings.
	 * @return string
	 */
	private function get_from_email( WP_Post $post, array $settings ) {
		$sender_name  = trim( (string) get_post_meta( $post->ID, 'senderName', true ) );
		$sender_email = trim( (string) get_post_meta( $post->ID, 'senderEmail', true ) );
		if ( $sender_email && is_email( $sender_email ) ) {
			return $sender_name ? sprintf( '%s <%s>', $sender_name, $sender_email ) : $sender_email;
		}

		return (string) $settings['default_from_email'];
	}

	/**
	 * Build sync response for Newspack.
	 *
	 * @param WP_Post $post Post.
	 * @param int     $campaign_id Campaign ID.
	 * @param array   $data Listmonk campaign data.
	 * @return array
	 */
	private function build_sync_response( WP_Post $post, $campaign_id, array $data ) {
		$list_ids = $this->get_post_list_ids( $post );
		$lists    = $this->get_send_lists(
			array(
				'ids'  => $list_ids,
				'type' => 'list',
			),
			true
		);

		return array(
			'campaign'                           => true,
			'campaign_id'                        => $campaign_id,
			'listmonk_campaign_id'               => $campaign_id,
			'listmonk_campaign_uuid'             => $data['uuid'] ?? get_post_meta( $post->ID, '_wtnl_listmonk_campaign_uuid', true ),
			'listmonk_status'                    => $data['status'] ?? get_post_meta( $post->ID, '_wtnl_listmonk_last_status', true ),
			'send_list_id'                       => ! empty( $list_ids ) ? (string) $list_ids[0] : '',
			'lists'                              => is_wp_error( $lists ) ? array() : $lists,
			'supports_multiple_test_recipients' => true,
		);
	}

	/**
	 * Resolve a Newspack-compatible campaign name.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @return string
	 */
	private function get_newspack_campaign_name( WP_Post $post ) {
		if ( method_exists( $this, 'get_campaign_name' ) ) {
			return (string) $this->get_campaign_name( $post );
		}

		return newspack_listmonk_connector_newspack_campaign_name( $post );
	}

	/**
	 * Resolve the Newspack sync-error transient name.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_sync_error_transient_name( $post_id ) {
		if ( method_exists( $this, 'get_transient_name' ) ) {
			return (string) $this->get_transient_name( $post_id );
		}

		return newspack_listmonk_connector_newspack_sync_error_transient_name( $post_id );
	}

	/**
	 * Store last error.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private function store_last_error( $post_id, WP_Error $error ) {
		update_post_meta( $post_id, '_wtnl_listmonk_last_error', sanitize_text_field( $error->get_error_message() ) );
		update_post_meta( $post_id, '_wtnl_listmonk_last_error_code', sanitize_key( $error->get_error_code() ) );
		update_post_meta( $post_id, '_wtnl_listmonk_last_error_at', gmdate( 'c' ) );
	}

	/**
	 * Clear stored Listmonk error details.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function clear_last_error( $post_id ) {
		delete_post_meta( $post_id, '_wtnl_listmonk_last_error' );
		delete_post_meta( $post_id, '_wtnl_listmonk_last_error_code' );
		delete_post_meta( $post_id, '_wtnl_listmonk_last_error_at' );
	}

	/**
	 * Archive a post's active campaign reference.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $event Archive event.
	 * @return void
	 */
	private function archive_post_campaign( $post_id, $event ) {
		if ( newspack_listmonk_connector_newspack_newsletter_post_type() !== get_post_type( $post_id ) ) {
			return;
		}

		delete_transient( $this->get_sync_error_transient_name( $post_id ) );

		$campaign_id = absint( get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
		if ( ! $campaign_id ) {
			return;
		}

		$campaign_uuid = (string) get_post_meta( $post_id, '_wtnl_listmonk_campaign_uuid', true );
		$result        = $this->client()->archive_campaign( $campaign_id );
		if ( is_wp_error( $result ) ) {
			$this->store_last_error( $post_id, $result );
			set_transient(
				$this->get_sync_error_transient_name( $post_id ),
				__( 'Listmonk archive error: ', 'newspack-listmonk-connector' ) . $result->get_error_message(),
				45
			);
			return;
		}

		$archived_status = sanitize_text_field( $result['archived_status'] ?? $result['previous_status'] ?? '' );
		$policy          = sanitize_key( $result['policy'] ?? 'preserved_unknown_status' );
		$this->store_archive_meta( $post_id, $campaign_id, $campaign_uuid, $archived_status, $policy, $event );

		if ( in_array( $archived_status, array( 'cancelled', 'draft' ), true ) ) {
			$this->clear_active_campaign_meta( $post_id );
		}

		$this->clear_last_error( $post_id );
		delete_transient( $this->get_sync_error_transient_name( $post_id ) );
	}

	/**
	 * Store campaign archive metadata.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $campaign_id Campaign ID.
	 * @param string $campaign_uuid Campaign UUID.
	 * @param string $status Archived status.
	 * @param string $policy Archive policy.
	 * @param string $event Archive event.
	 * @return void
	 */
	private function store_archive_meta( $post_id, $campaign_id, $campaign_uuid, $status, $policy, $event ) {
		update_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', absint( $campaign_id ) );
		if ( '' !== $campaign_uuid ) {
			update_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_uuid', sanitize_text_field( $campaign_uuid ) );
		} else {
			delete_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_uuid' );
		}
		update_post_meta( $post_id, '_wtnl_listmonk_archived_status', sanitize_text_field( $status ) );
		update_post_meta( $post_id, '_wtnl_listmonk_archived_at', gmdate( 'c' ) );
		update_post_meta( $post_id, '_wtnl_listmonk_archive_policy', sanitize_key( $policy . '_' . $event ) );
	}

	/**
	 * Clear active campaign metadata so future sync creates a new campaign.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function clear_active_campaign_meta( $post_id ) {
		delete_post_meta( $post_id, '_wtnl_listmonk_campaign_id' );
		delete_post_meta( $post_id, '_wtnl_listmonk_campaign_uuid' );
		delete_post_meta( $post_id, '_wtnl_listmonk_payload_hash' );
		delete_post_meta( $post_id, '_wtnl_listmonk_last_synced_at' );
		delete_post_meta( $post_id, '_wtnl_listmonk_last_status' );
	}

	/**
	 * Build a not-implemented error.
	 *
	 * @param string|null $message Optional message.
	 * @return WP_Error
	 */
	private function not_implemented( $message = null ) {
		return new WP_Error(
			'newspack_listmonk_connector_not_implemented',
			$message ? $message : __( 'This Listmonk provider method is not implemented yet.', 'newspack-listmonk-connector' )
		);
	}
}
