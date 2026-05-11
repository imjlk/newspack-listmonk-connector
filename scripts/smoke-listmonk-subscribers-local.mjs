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
const connectorSlug = 'newspack-listmonk-connector';
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

if ( ! empty( $errors ) ) {
	echo wp_json_encode( array( 'ok' => false, 'errors' => $errors, 'results' => $results ), JSON_PRETTY_PRINT ) . PHP_EOL;
	exit( 1 );
}

echo wp_json_encode(
	array(
		'ok' => true,
		'email' => $email,
		'subscriberId' => $subscriber_id,
		'primaryListId' => $primary_list_id,
		'secondaryListId' => $secondary_list_id,
		'initialLists' => $contact_lists,
		'updatedLists' => $updated_lists ?? $contact_lists,
		'removedLists' => $removed_lists ?? $contact_lists,
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

const result = wpEval(php);
printCommandOutput(result);
