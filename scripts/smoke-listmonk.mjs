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

const envFileArgIndex = process.argv.indexOf('--env-file');
if (envFileArgIndex >= 0 && process.argv[envFileArgIndex + 1]) {
	loadDotEnv(process.argv[envFileArgIndex + 1]);
} else {
	loadDotEnv();
	loadDotEnv('.listmonk.env');
}

const settings = {
	base_url: requireEnv('LISTMONK_BASE_URL'),
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

logStep('Verifying live Listmonk connection and draft campaign sync');
const php = `
$errors = array();
$settings = json_decode( ${phpString(JSON.stringify(settings))}, true );

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
	$list_id = absint( $settings['default_list_ids'][0] );
	$post_id = wp_insert_post(
		array(
			'post_type' => Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT,
			'post_status' => 'draft',
			'post_title' => 'Listmonk smoke test ' . gmdate( 'c' ),
			'post_content' => '<!-- wp:paragraph --><p>Smoke test newsletter body.</p><!-- /wp:paragraph -->',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		$errors[] = 'Unable to create smoke newsletter: ' . $post_id->get_error_message();
	} else {
		update_post_meta( $post_id, 'send_list_id', $list_id );
		update_post_meta( $post_id, 'senderName', 'Smoke Test' );
		update_post_meta( $post_id, 'senderEmail', $settings['default_from_email'] );

		$sync = $provider->sync( get_post( $post_id ) );
		if ( is_wp_error( $sync ) ) {
			$errors[] = 'Provider sync failed: ' . $sync->get_error_message();
		}
	}
}

if ( empty( $errors ) ) {
	$campaign_id = absint( get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
	$payload_hash = (string) get_post_meta( $post_id, '_wtnl_listmonk_payload_hash', true );
	$last_synced_at = (string) get_post_meta( $post_id, '_wtnl_listmonk_last_synced_at', true );

	if ( ! $campaign_id ) {
		$errors[] = 'Missing _wtnl_listmonk_campaign_id post meta.';
	}
	if ( '' === $payload_hash ) {
		$errors[] = 'Missing _wtnl_listmonk_payload_hash post meta.';
	}
	if ( '' === $last_synced_at ) {
		$errors[] = 'Missing _wtnl_listmonk_last_synced_at post meta.';
	}
}

if ( empty( $errors ) ) {
	$client = new Newspack_Listmonk_Connector_Listmonk_Client();
	$campaign = $client->get_campaign( $campaign_id );
	if ( is_wp_error( $campaign ) ) {
		$errors[] = 'Unable to retrieve synced Listmonk campaign: ' . $campaign->get_error_message();
	} else {
		$campaign_data = $campaign['data'] ?? $campaign;
		$status = (string) ( $campaign_data['status'] ?? '' );
		if ( 'draft' !== $status ) {
			$errors[] = 'Expected synced Listmonk campaign to stay draft, got: ' . $status;
		}
	}
}

if ( ! empty( $errors ) ) {
	echo wp_json_encode( array( 'ok' => false, 'errors' => $errors ), JSON_PRETTY_PRINT ) . PHP_EOL;
	exit( 1 );
}

echo wp_json_encode(
	array(
		'ok' => true,
		'postId' => $post_id,
		'campaignId' => $campaign_id,
		'campaignStatus' => $status,
		'payloadHash' => $payload_hash,
		'lastSyncedAt' => $last_synced_at,
		'listCount' => count( $lists ),
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

printCommandOutput(wpEval(php));
