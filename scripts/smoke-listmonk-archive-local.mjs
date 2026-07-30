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
		`${envFile} is required. Run pnpm run listmonk:start before this local archive smoke.`
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
		`Refusing to run archive smoke against non-local Listmonk host: ${parsedBaseUrl.hostname}`
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

const connectorSlug = 'connector-for-newspack-newsletters-and-listmonk';
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

logStep('Verifying local Listmonk campaign archive policy');
const php = `
$errors = array();
$settings = json_decode( ${phpString(JSON.stringify(settings))}, true );
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
	$administrator_ids = get_users(
		array(
			'role' => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	if ( ! empty( $administrator_ids ) ) {
		wp_set_current_user( absint( $administrator_ids[0] ) );
	}
}

function newspack_listmonk_connector_archive_smoke_get_campaign_status( $campaign_id ) {
	$client = new Newspack_Listmonk_Connector_Listmonk_Client();
	$campaign = $client->get_campaign( absint( $campaign_id ) );
	if ( is_wp_error( $campaign ) ) {
		return $campaign;
	}

	$campaign_data = $campaign['data'] ?? $campaign;
	return (string) ( $campaign_data['status'] ?? '' );
}

function newspack_listmonk_connector_archive_smoke_create_post( $settings, $title ) {
	$post_id = wp_insert_post(
		array(
			'post_type' => Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT,
			'post_status' => 'draft',
			'post_title' => $title . ' ' . gmdate( 'c' ),
			'post_content' => '<!-- wp:paragraph --><p>Smoke archive newsletter body.</p><!-- /wp:paragraph -->',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'send_list_id', absint( $settings['default_list_ids'][0] ) );
	update_post_meta( $post_id, 'senderName', 'Smoke Test' );
	update_post_meta( $post_id, 'senderEmail', 'smoke@example.com' );

	return $post_id;
}

function newspack_listmonk_connector_archive_smoke_assert_archived_draft( $post_id, $campaign_id, $label, &$errors, &$results ) {
	$status = newspack_listmonk_connector_archive_smoke_get_campaign_status( $campaign_id );
	if ( is_wp_error( $status ) ) {
		$errors[] = sprintf( 'Unable to fetch %s campaign after trash: %s', $label, $status->get_error_message() );
		return;
	}

	$active_campaign_id = get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true );
	$archived_id = absint( get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) );
	$archived_status = (string) get_post_meta( $post_id, '_wtnl_listmonk_archived_status', true );
	$archive_policy = (string) get_post_meta( $post_id, '_wtnl_listmonk_archive_policy', true );

	if ( 'draft' !== $status ) {
		$errors[] = sprintf( 'Expected %s campaign status draft, got: %s', $label, $status );
	}
	if ( '' !== $active_campaign_id ) {
		$errors[] = sprintf( 'Expected %s active campaign meta to be cleared.', $label );
	}
	if ( absint( $campaign_id ) !== $archived_id ) {
		$errors[] = sprintf( 'Expected %s archived campaign ID %d, got: %d', $label, $campaign_id, $archived_id );
	}
	if ( 'draft' !== $archived_status ) {
		$errors[] = sprintf( 'Expected %s archived status draft, got: %s', $label, $archived_status );
	}
	if ( 'preserved_draft_campaign_trash' !== $archive_policy ) {
		$errors[] = sprintf( 'Expected %s archive policy preserved_draft_campaign_trash, got: %s', $label, $archive_policy );
	}

	$results[ $label ] = array(
		'postId' => $post_id,
		'campaignId' => $campaign_id,
		'campaignStatus' => $status,
		'activeCampaignId' => $active_campaign_id,
		'archivedCampaignId' => $archived_id,
		'archivePolicy' => $archive_policy,
	);
}

if ( empty( $errors ) ) {
	$draft_post_id = newspack_listmonk_connector_archive_smoke_create_post( $settings, 'Smoke archive draft' );
	if ( is_wp_error( $draft_post_id ) ) {
		$errors[] = 'Unable to create draft archive smoke newsletter: ' . $draft_post_id->get_error_message();
	} else {
		$draft_sync = $provider->sync( get_post( $draft_post_id ) );
		if ( is_wp_error( $draft_sync ) ) {
			$errors[] = 'Draft archive sync failed: ' . $draft_sync->get_error_message();
		} else {
			$draft_campaign_id = absint( $draft_sync['campaign_id'] ?? 0 );
			wp_trash_post( $draft_post_id );
			newspack_listmonk_connector_archive_smoke_assert_archived_draft( $draft_post_id, $draft_campaign_id, 'draft', $errors, $results );
		}
	}
}

if ( empty( $errors ) ) {
	$scheduled_post_id = newspack_listmonk_connector_archive_smoke_create_post( $settings, 'Smoke archive scheduled' );
	if ( is_wp_error( $scheduled_post_id ) ) {
		$errors[] = 'Unable to create scheduled archive smoke newsletter: ' . $scheduled_post_id->get_error_message();
	} else {
		$future_gmt = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$scheduled_post = get_post( $scheduled_post_id );
		$scheduled_post->post_status = 'future';
		$scheduled_post->post_date_gmt = $future_gmt;
		$scheduled_send = $provider->send( $scheduled_post );
		if ( is_wp_error( $scheduled_send ) ) {
			$errors[] = 'Scheduled archive send failed: ' . $scheduled_send->get_error_message();
		} else {
			$scheduled_campaign_id = absint( get_post_meta( $scheduled_post_id, '_wtnl_listmonk_campaign_id', true ) );
			wp_trash_post( $scheduled_post_id );
			$status = newspack_listmonk_connector_archive_smoke_get_campaign_status( $scheduled_campaign_id );
			$active_campaign_id = get_post_meta( $scheduled_post_id, '_wtnl_listmonk_campaign_id', true );
			$archived_id = absint( get_post_meta( $scheduled_post_id, '_wtnl_listmonk_archived_campaign_id', true ) );
			$archived_status = (string) get_post_meta( $scheduled_post_id, '_wtnl_listmonk_archived_status', true );
			$archive_policy = (string) get_post_meta( $scheduled_post_id, '_wtnl_listmonk_archive_policy', true );

			if ( is_wp_error( $status ) ) {
				$errors[] = 'Unable to fetch scheduled campaign after trash: ' . $status->get_error_message();
			} elseif ( 'draft' !== $status ) {
				$errors[] = 'Expected scheduled campaign to revert to draft, got: ' . $status;
			}
			if ( '' !== $active_campaign_id ) {
				$errors[] = 'Expected scheduled active campaign meta to be cleared.';
			}
			if ( $scheduled_campaign_id !== $archived_id ) {
				$errors[] = sprintf( 'Expected scheduled archived campaign ID %d, got: %d', $scheduled_campaign_id, $archived_id );
			}
			if ( 'draft' !== $archived_status ) {
				$errors[] = 'Expected scheduled archived status draft, got: ' . $archived_status;
			}
			if ( 'reverted_scheduled_campaign_to_draft_trash' !== $archive_policy ) {
				$errors[] = 'Expected scheduled archive policy reverted_scheduled_campaign_to_draft_trash, got: ' . $archive_policy;
			}

			$results['scheduled'] = array(
				'postId' => $scheduled_post_id,
				'campaignId' => $scheduled_campaign_id,
				'campaignStatus' => is_wp_error( $status ) ? null : $status,
				'activeCampaignId' => $active_campaign_id,
				'archivedCampaignId' => $archived_id,
				'archivePolicy' => $archive_policy,
			);
		}
	}
}

if ( empty( $errors ) ) {
	$running_post_id = newspack_listmonk_connector_archive_smoke_create_post( $settings, 'Smoke archive running' );
	if ( is_wp_error( $running_post_id ) ) {
		$errors[] = 'Unable to create running archive smoke newsletter: ' . $running_post_id->get_error_message();
	} else {
		$running_post = get_post( $running_post_id );
		$running_post->post_status = 'publish';
		$running_send = $provider->send( $running_post );
		if ( is_wp_error( $running_send ) ) {
			$errors[] = 'Running archive send failed: ' . $running_send->get_error_message();
		} else {
			$running_campaign_id = absint( get_post_meta( $running_post_id, '_wtnl_listmonk_campaign_id', true ) );
			wp_trash_post( $running_post_id );
			$running_status = newspack_listmonk_connector_archive_smoke_get_campaign_status( $running_campaign_id );
			$running_active_id = absint( get_post_meta( $running_post_id, '_wtnl_listmonk_campaign_id', true ) );
			$running_policy = (string) get_post_meta( $running_post_id, '_wtnl_listmonk_archive_policy', true );

			if ( is_wp_error( $running_status ) ) {
				$errors[] = 'Unable to fetch running campaign after trash: ' . $running_status->get_error_message();
			} elseif ( 'running' !== $running_status ) {
				$errors[] = 'Expected running campaign to stay running, got: ' . $running_status;
			}
			if ( $running_campaign_id !== $running_active_id ) {
				$errors[] = 'Expected running active campaign meta to be preserved.';
			}
			if ( 'preserved_running_campaign_trash' !== $running_policy ) {
				$errors[] = 'Expected running archive policy preserved_running_campaign_trash, got: ' . $running_policy;
			}

			$results['running'] = array(
				'postId' => $running_post_id,
				'campaignId' => $running_campaign_id,
				'campaignStatus' => is_wp_error( $running_status ) ? null : $running_status,
				'activeCampaignId' => $running_active_id,
				'archivePolicy' => $running_policy,
			);
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
		'results' => $results,
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

const result = wpEval(php);
printCommandOutput(result);
