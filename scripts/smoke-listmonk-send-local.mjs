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
		`${envFile} is required. Run pnpm run listmonk:start before this local send smoke.`
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
		`Refusing to run send smoke against non-local Listmonk host: ${parsedBaseUrl.hostname}`
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

logStep('Verifying local Listmonk running and scheduled send transitions');
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

function newspack_listmonk_connector_smoke_get_campaign_status( $campaign_id ) {
	$client = new Newspack_Listmonk_Connector_Listmonk_Client();
	$campaign = $client->get_campaign( absint( $campaign_id ) );
	if ( is_wp_error( $campaign ) ) {
		return $campaign;
	}

	$campaign_data = $campaign['data'] ?? $campaign;
	return (string) ( $campaign_data['status'] ?? '' );
}

function newspack_listmonk_connector_smoke_create_post( $status, $settings, $date_gmt = null ) {
	$post_args = array(
		'post_type' => Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT,
		'post_status' => $status,
		'post_title' => sprintf( 'Smoke Listmonk %s send %s', $status, gmdate( 'c' ) ),
		'post_content' => '<!-- wp:paragraph --><p>Smoke send transition newsletter body.</p><!-- /wp:paragraph -->',
	);

	if ( null !== $date_gmt ) {
		$post_args['post_date_gmt'] = $date_gmt;
		$post_args['post_date'] = get_date_from_gmt( $date_gmt );
	}

	$post_id = wp_insert_post( $post_args, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, 'send_list_id', absint( $settings['default_list_ids'][0] ) );
	update_post_meta( $post_id, 'senderName', 'Smoke Test' );
	update_post_meta( $post_id, 'senderEmail', 'smoke@example.com' );

	return $post_id;
}

if ( empty( $errors ) ) {
	$immediate_post_id = newspack_listmonk_connector_smoke_create_post( 'draft', $settings );
	if ( is_wp_error( $immediate_post_id ) ) {
		$errors[] = 'Unable to create immediate smoke newsletter: ' . $immediate_post_id->get_error_message();
	} else {
		$immediate_sync = $provider->sync( get_post( $immediate_post_id ) );
		if ( is_wp_error( $immediate_sync ) ) {
			$errors[] = 'Immediate draft sync failed: ' . $immediate_sync->get_error_message();
		} else {
			$immediate_campaign_id = absint( $immediate_sync['campaign_id'] ?? 0 );
			$immediate_draft_status = newspack_listmonk_connector_smoke_get_campaign_status( $immediate_campaign_id );
			if ( is_wp_error( $immediate_draft_status ) ) {
				$errors[] = 'Unable to fetch immediate draft campaign: ' . $immediate_draft_status->get_error_message();
			} elseif ( 'draft' !== $immediate_draft_status ) {
				$errors[] = 'Expected immediate campaign to start as draft, got: ' . $immediate_draft_status;
			}
		}
	}
}

if ( empty( $errors ) ) {
	$immediate_post = get_post( $immediate_post_id );
	$immediate_post->post_status = 'publish';
	$send_result = $provider->send( $immediate_post );
	if ( is_wp_error( $send_result ) ) {
		$errors[] = 'Immediate send failed: ' . $send_result->get_error_message();
	} else {
		$immediate_status = newspack_listmonk_connector_smoke_get_campaign_status( $immediate_campaign_id );
		$immediate_meta_status = (string) get_post_meta( $immediate_post_id, '_wtnl_listmonk_last_status', true );

		if ( is_wp_error( $immediate_status ) ) {
			$errors[] = 'Unable to fetch immediate sent campaign: ' . $immediate_status->get_error_message();
		} elseif ( 'running' !== $immediate_status ) {
			$errors[] = 'Expected immediate campaign status running, got: ' . $immediate_status;
		}
		if ( 'running' !== $immediate_meta_status ) {
			$errors[] = 'Expected immediate post meta status running, got: ' . $immediate_meta_status;
		}

		$results['immediate'] = array(
			'postId' => $immediate_post_id,
			'campaignId' => $immediate_campaign_id,
			'campaignStatus' => is_wp_error( $immediate_status ) ? null : $immediate_status,
			'metaStatus' => $immediate_meta_status,
		);
	}
}

if ( empty( $errors ) ) {
	$captured_scheduled_payload = null;
	$future_gmt = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
	$scheduled_post_id = newspack_listmonk_connector_smoke_create_post( 'draft', $settings, $future_gmt );

	if ( is_wp_error( $scheduled_post_id ) ) {
		$errors[] = 'Unable to create scheduled smoke newsletter: ' . $scheduled_post_id->get_error_message();
	} else {
		$payload_capture = static function ( $payload, $post ) use ( $scheduled_post_id, &$captured_scheduled_payload ) {
			if ( absint( $post->ID ) === absint( $scheduled_post_id ) ) {
				$captured_scheduled_payload = $payload;
			}

			return $payload;
		};
		add_filter( 'newspack_listmonk_connector_campaign_payload', $payload_capture, 10, 2 );
		$scheduled_post = get_post( $scheduled_post_id );
		$scheduled_post->post_status = 'future';
		$scheduled_post->post_date_gmt = $future_gmt;
		$scheduled_send = $provider->send( $scheduled_post );
		remove_filter( 'newspack_listmonk_connector_campaign_payload', $payload_capture, 10 );

		if ( is_wp_error( $scheduled_send ) ) {
			$errors[] = 'Scheduled send failed: ' . $scheduled_send->get_error_message();
		} else {
			$scheduled_campaign_id = absint( get_post_meta( $scheduled_post_id, '_wtnl_listmonk_campaign_id', true ) );
			$scheduled_status = newspack_listmonk_connector_smoke_get_campaign_status( $scheduled_campaign_id );
			$scheduled_meta_status = (string) get_post_meta( $scheduled_post_id, '_wtnl_listmonk_last_status', true );
			$scheduled_send_at = (string) ( $captured_scheduled_payload['send_at'] ?? '' );

			if ( '' === $scheduled_send_at ) {
				$errors[] = 'Scheduled payload did not include send_at.';
			}
			if ( is_wp_error( $scheduled_status ) ) {
				$errors[] = 'Unable to fetch scheduled campaign: ' . $scheduled_status->get_error_message();
			} elseif ( 'scheduled' !== $scheduled_status ) {
				$errors[] = 'Expected scheduled campaign status scheduled, got: ' . $scheduled_status;
			}
			if ( 'scheduled' !== $scheduled_meta_status ) {
				$errors[] = 'Expected scheduled post meta status scheduled, got: ' . $scheduled_meta_status;
			}

			$results['scheduled'] = array(
				'postId' => $scheduled_post_id,
				'campaignId' => $scheduled_campaign_id,
				'campaignStatus' => is_wp_error( $scheduled_status ) ? null : $scheduled_status,
				'metaStatus' => $scheduled_meta_status,
				'sendAt' => $scheduled_send_at,
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

printCommandOutput(wpEval(php));
