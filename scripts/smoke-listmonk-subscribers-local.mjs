#!/usr/bin/env node

import fs from 'node:fs';

import {
	loadDotEnv,
	logStep,
	parseListIds,
	phpString,
	printCommandOutput,
	requireEnv,
	resolvePluginSlug,
	runWp,
	wpEval,
} from './smoke-lib.mjs';

const envFile = '.listmonk.env';
if (!fs.existsSync(envFile)) {
	console.error(
		`${envFile} is required. Run pnpm run listmonk:start before this local subscriber smoke.`
	);
	process.exit(1);
}

loadDotEnv(envFile);

const baseUrl = requireEnv('LISTMONK_BASE_URL');
const allowedHosts = new Set(['host.docker.internal', 'localhost', '127.0.0.1']);
let parsedBaseUrl;
try {
	parsedBaseUrl = new URL(baseUrl);
} catch {
	console.error(`Invalid LISTMONK_BASE_URL in ${envFile}: ${baseUrl}`);
	process.exit(1);
}

if (!allowedHosts.has(parsedBaseUrl.hostname)) {
	console.error(
		`Refusing to run subscriber smoke against non-local Listmonk host: ${parsedBaseUrl.hostname}`
	);
	process.exit(1);
}

const settings = {
	base_url: baseUrl,
	api_user: requireEnv('LISTMONK_API_USER'),
	api_token: requireEnv('LISTMONK_API_TOKEN'),
	default_from_email: requireEnv('LISTMONK_FROM_EMAIL'),
	default_template_id: 0,
	default_list_ids: parseListIds(requireEnv('LISTMONK_DEFAULT_LIST_IDS')),
	send_mode: 'campaign',
};

const email = `smoke-subscriber-${Date.now()}@example.com`;
const doubleOptInEmail = `smoke-double-optin-${Date.now()}@example.com`;
const existingDoubleOptInEmail = `smoke-existing-double-optin-${Date.now()}@example.com`;
const connectorSlug = 'wp-typia-newsletter-connector';
const newspackSlug = resolvePluginSlug([
	'newspack-newsletters',
	'newspack-newsletters.latest-stable',
]);

logStep('Activating Newspack Newsletters and the Listmonk connector');
printCommandOutput(runWp(['plugin', 'activate', newspackSlug]));
printCommandOutput(runWp(['plugin', 'activate', connectorSlug]));

logStep('Selecting Listmonk as the active Newspack provider');
printCommandOutput(
	runWp(['option', 'update', 'newspack_newsletters_service_provider', 'listmonk'])
);

logStep('Verifying local Listmonk subscriber sync helpers');
const php = `
$errors = array();
$settings = json_decode( ${phpString(JSON.stringify(settings))}, true );
$email = ${phpString(email)};
$double_optin_email = ${phpString(doubleOptInEmail)};
$existing_double_optin_email = ${phpString(existingDoubleOptInEmail)};
$results = array();

if ( ! is_array( $settings ) ) {
	$errors[] = 'Unable to decode Listmonk settings.';
}

if ( empty( $errors ) ) {
	update_option( 'newspack_listmonk_connector_settings', $settings, false );
	Newspack_Newsletters::set_service_provider( 'listmonk' );

	$provider = Newspack_Newsletters::get_service_provider_instance( 'listmonk' );
	if ( ! $provider instanceof Newspack_Listmonk_Connector_Provider ) {
		$errors[] = 'The listmonk provider instance has the wrong class.';
	}
}

if ( empty( $errors ) ) {
	$connection = $provider->test_connection();
	if ( is_wp_error( $connection ) ) {
		$errors[] = 'Listmonk connection failed: ' . $connection->get_error_message();
	}
}

if ( empty( $errors ) ) {
	$lists = $provider->get_lists();
	if ( is_wp_error( $lists ) ) {
		$errors[] = 'Listmonk list fetch failed: ' . $lists->get_error_message();
	} elseif ( empty( $lists ) ) {
		$errors[] = 'Listmonk returned no active lists.';
	}
}

if ( empty( $errors ) ) {
	$list_ids = array_values(
		array_map(
			static function ( $list ) {
				return absint( $list['id'] ?? 0 );
			},
			$lists
		)
	);
	$list_ids = array_values( array_filter( $list_ids ) );
	$primary_list_id = absint( $settings['default_list_ids'][0] ?? $list_ids[0] ?? 0 );
	$secondary_list_id = $primary_list_id;
	foreach ( $list_ids as $candidate_list_id ) {
		if ( absint( $candidate_list_id ) !== $primary_list_id ) {
			$secondary_list_id = absint( $candidate_list_id );
			break;
		}
	}

	if ( ! $primary_list_id ) {
		$errors[] = 'Unable to resolve a primary Listmonk list ID.';
	}
}

function newspack_listmonk_connector_subscriber_smoke_get_list_subscription_status( $client, $subscriber_id, $list_id ) {
	$subscriber = $client->get_subscriber( absint( $subscriber_id ) );
	if ( is_wp_error( $subscriber ) ) {
		return $subscriber;
	}

	$subscriber_data = $subscriber['data'] ?? $subscriber;
	$lists = $subscriber_data['lists'] ?? array();
	foreach ( $lists as $list ) {
		if ( is_array( $list ) && absint( $list['id'] ?? 0 ) === absint( $list_id ) ) {
			return (string) ( $list['subscription_status'] ?? '' );
		}
	}

	return new WP_Error(
		'newspack_listmonk_connector_smoke_missing_subscription_status',
		sprintf( 'Subscriber %d is missing list %d subscription status.', absint( $subscriber_id ), absint( $list_id ) )
	);
}

if ( empty( $errors ) ) {
	$create = $provider->add_contact(
		array(
			'email' => $email,
			'name' => 'Smoke Subscriber',
			'metadata' => array(
				'source' => 'subscriber-smoke',
				'membership' => 'local',
			),
		),
		$primary_list_id
	);
	if ( is_wp_error( $create ) ) {
		$errors[] = 'Unable to create Listmonk subscriber through provider: ' . $create->get_error_message();
	}
}

if ( empty( $errors ) ) {
	$contact = $provider->get_contact_data( $email, true );
	if ( is_wp_error( $contact ) ) {
		$errors[] = 'Unable to retrieve created subscriber: ' . $contact->get_error_message();
	} else {
		$subscriber_id = absint( $contact['id'] ?? 0 );
		if ( ! $subscriber_id ) {
			$errors[] = 'Created subscriber did not include an ID.';
		}
	}
}

if ( empty( $errors ) ) {
	$contact_lists = $provider->get_contact_lists( $email );
	if ( ! in_array( $primary_list_id, $contact_lists, true ) ) {
		$errors[] = 'Created subscriber is missing the primary list membership.';
	}
}

if ( empty( $errors ) && $secondary_list_id !== $primary_list_id ) {
	$update = $provider->update_contact_lists( $email, array( $secondary_list_id ), array() );
	if ( is_wp_error( $update ) ) {
		$errors[] = 'Unable to add secondary list membership: ' . $update->get_error_message();
	} else {
		$updated_lists = $provider->get_contact_lists( $email );
		if ( ! in_array( $secondary_list_id, $updated_lists, true ) ) {
			$errors[] = 'Updated subscriber is missing the secondary list membership.';
		}
	}
}

if ( empty( $errors ) && $secondary_list_id !== $primary_list_id ) {
	$remove = $provider->update_contact_lists( $email, array(), array( $secondary_list_id ) );
	if ( is_wp_error( $remove ) ) {
		$errors[] = 'Unable to remove secondary list membership: ' . $remove->get_error_message();
	} else {
		$removed_lists = $provider->get_contact_lists( $email );
		if ( in_array( $secondary_list_id, $removed_lists, true ) ) {
			$errors[] = 'Removed secondary list membership is still present.';
		}
	}
}

if ( empty( $errors ) ) {
	$client = new Newspack_Listmonk_Connector_Listmonk_Client();
	$double_optin_list_id = 0;
	foreach ( $lists as $list ) {
		if ( 'double' === (string) ( $list['optin'] ?? '' ) ) {
			$double_optin_list_id = absint( $list['id'] ?? 0 );
			break;
		}
	}

	if ( ! $double_optin_list_id ) {
		$created_list = $client->request(
			'POST',
			'/api/lists',
			array(
				'name' => 'Smoke Double Opt-In ' . gmdate( 'c' ),
				'type' => 'private',
				'optin' => 'double',
				'status' => 'active',
				'tags' => array( 'smoke' ),
				'description' => 'Temporary local smoke list for double opt-in policy verification.',
			)
		);
		if ( is_wp_error( $created_list ) ) {
			$errors[] = 'Unable to create local double opt-in Listmonk list: ' . $created_list->get_error_message();
		} else {
			$created_list_data = $created_list['data'] ?? $created_list;
			$double_optin_list_id = absint( $created_list_data['id'] ?? 0 );
			if ( ! $double_optin_list_id ) {
				$errors[] = 'Created double opt-in list did not include an ID.';
			}
		}
	}
}

if ( empty( $errors ) ) {
	$double_create = $provider->add_contact(
		array(
			'email' => $double_optin_email,
			'name' => 'Smoke Double Opt-In Subscriber',
		),
		$double_optin_list_id
	);
	if ( is_wp_error( $double_create ) ) {
		$errors[] = 'Unable to create double opt-in subscriber through provider: ' . $double_create->get_error_message();
	} else {
		$double_contact = $provider->get_contact_data( $double_optin_email, true );
		if ( is_wp_error( $double_contact ) ) {
			$errors[] = 'Unable to retrieve double opt-in subscriber: ' . $double_contact->get_error_message();
		} else {
			$double_subscriber_id = absint( $double_contact['id'] ?? 0 );
			$double_subscription_status = newspack_listmonk_connector_subscriber_smoke_get_list_subscription_status( $client, $double_subscriber_id, $double_optin_list_id );
			if ( is_wp_error( $double_subscription_status ) ) {
				$errors[] = $double_subscription_status->get_error_message();
			} elseif ( 'unconfirmed' !== $double_subscription_status ) {
				$errors[] = 'Expected new double opt-in subscriber membership status unconfirmed, got: ' . $double_subscription_status;
			}
		}
	}
}

if ( empty( $errors ) ) {
	$existing_create = $provider->add_contact(
		array(
			'email' => $existing_double_optin_email,
			'name' => 'Smoke Existing Double Opt-In Subscriber',
		),
		$primary_list_id
	);
	if ( is_wp_error( $existing_create ) ) {
		$errors[] = 'Unable to create existing subscriber for double opt-in add test: ' . $existing_create->get_error_message();
	} else {
		$existing_contact = $provider->get_contact_data( $existing_double_optin_email, true );
		if ( is_wp_error( $existing_contact ) ) {
			$errors[] = 'Unable to retrieve existing double opt-in subscriber: ' . $existing_contact->get_error_message();
		} else {
			$existing_double_subscriber_id = absint( $existing_contact['id'] ?? 0 );
			$existing_add = $provider->update_contact_lists( $existing_double_optin_email, array( $double_optin_list_id ), array() );
			if ( is_wp_error( $existing_add ) ) {
				$errors[] = 'Unable to add existing subscriber to double opt-in list: ' . $existing_add->get_error_message();
			} else {
				$existing_double_subscription_status = newspack_listmonk_connector_subscriber_smoke_get_list_subscription_status( $client, $existing_double_subscriber_id, $double_optin_list_id );
				if ( is_wp_error( $existing_double_subscription_status ) ) {
					$errors[] = $existing_double_subscription_status->get_error_message();
				} elseif ( 'unconfirmed' !== $existing_double_subscription_status ) {
					$errors[] = 'Expected existing double opt-in subscriber membership status unconfirmed, got: ' . $existing_double_subscription_status;
				}
			}
		}
	}
}

if ( empty( $errors ) ) {
	$client = new Newspack_Listmonk_Connector_Listmonk_Client();
	$blocklist = $client->request( 'PUT', sprintf( '/api/subscribers/%d/blocklist', $subscriber_id ) );
	if ( is_wp_error( $blocklist ) ) {
		$errors[] = 'Unable to blocklist local Listmonk subscriber: ' . $blocklist->get_error_message();
	}
}

if ( empty( $errors ) ) {
	$blocked_contact = $provider->get_contact_data( $email );
	if ( is_wp_error( $blocked_contact ) ) {
		$errors[] = 'Unable to retrieve blocklisted subscriber: ' . $blocked_contact->get_error_message();
	} elseif ( empty( $blocked_contact['is_blocklisted'] ) ) {
		$errors[] = 'Blocklisted subscriber was not reflected as blocklisted.';
	}
}

if ( empty( $errors ) ) {
	$blocked_add = $provider->add_contact(
		array(
			'email' => $email,
			'name' => 'Smoke Subscriber',
		),
		$primary_list_id
	);
	if ( ! is_wp_error( $blocked_add ) ) {
		$errors[] = 'Blocklisted subscriber add_contact unexpectedly succeeded.';
	} elseif ( 'newspack_listmonk_connector_subscriber_blocklisted' !== $blocked_add->get_error_code() ) {
		$errors[] = 'Blocklisted subscriber add_contact returned unexpected error: ' . $blocked_add->get_error_code();
	}
}

if ( empty( $errors ) ) {
	$blocked_update = $provider->update_contact_lists( $email, array( $primary_list_id ), array() );
	if ( ! is_wp_error( $blocked_update ) ) {
		$errors[] = 'Blocklisted subscriber update_contact_lists unexpectedly succeeded.';
	} elseif ( 'newspack_listmonk_connector_subscriber_blocklisted' !== $blocked_update->get_error_code() ) {
		$errors[] = 'Blocklisted subscriber update_contact_lists returned unexpected error: ' . $blocked_update->get_error_code();
	}
}

if ( ! empty( $errors ) ) {
	echo wp_json_encode( array( 'ok' => false, 'errors' => $errors, 'results' => $results ), JSON_PRETTY_PRINT ) . PHP_EOL;
	exit( 1 );
}

echo wp_json_encode(
	array(
		'ok' => true,
		'email' => $email,
		'subscriberId' => $subscriber_id,
		'doubleOptInSubscriberId' => $double_subscriber_id,
		'existingDoubleOptInSubscriberId' => $existing_double_subscriber_id,
		'primaryListId' => $primary_list_id,
		'secondaryListId' => $secondary_list_id,
		'doubleOptInListId' => $double_optin_list_id,
		'doubleOptInSubscriptionStatus' => $double_subscription_status,
		'existingDoubleOptInSubscriptionStatus' => $existing_double_subscription_status,
		'initialLists' => $contact_lists,
		'updatedLists' => $updated_lists ?? $contact_lists,
		'removedLists' => $removed_lists ?? $contact_lists,
		'isBlocklisted' => (bool) ( $blocked_contact['is_blocklisted'] ?? false ),
		'bounceCount' => absint( $blocked_contact['bounce_count'] ?? 0 ),
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

const result = wpEval(php);
printCommandOutput(result);
