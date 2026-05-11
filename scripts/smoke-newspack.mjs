import {
	logStep,
	phpString,
	printCommandOutput,
	resolvePluginSlug,
	runWp,
	wpEval,
} from './smoke-lib.mjs';

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

runWp(['option', 'delete', 'newspack_listmonk_connector_settings'], {
	allowFailure: true,
});

logStep('Verifying provider registration, provider instance, REST routes, and missing-credentials handling');
const php = `
$errors = array();

if ( ! class_exists( 'Newspack_Newsletters' ) ) {
	$errors[] = 'Newspack_Newsletters class is missing.';
}

if ( ! class_exists( 'Newspack_Listmonk_Connector_Provider' ) ) {
	$errors[] = 'Newspack_Listmonk_Connector_Provider class is missing.';
}

if ( empty( $errors ) ) {
	$providers = Newspack_Newsletters::get_registered_providers();
	if ( empty( $providers['listmonk'] ) ) {
		$errors[] = 'The listmonk provider is not registered.';
	}

	Newspack_Newsletters::set_service_provider( 'listmonk' );
	if ( 'listmonk' !== Newspack_Newsletters::service_provider() ) {
		$errors[] = 'The active Newspack service provider is not listmonk.';
	}

	$provider = Newspack_Newsletters::get_service_provider_instance( 'listmonk' );
	if ( ! $provider instanceof Newspack_Listmonk_Connector_Provider ) {
		$errors[] = 'The listmonk provider instance has the wrong class.';
	}
	if ( $provider instanceof Newspack_Listmonk_Connector_Provider ) {
		$reflection = new ReflectionClass( $provider );
		$controller_property = $reflection->getProperty( 'controller' );
		$controller_property->setAccessible( true );
		$controller = $controller_property->getValue( $provider );
		if ( ! $controller instanceof Newspack_Listmonk_Connector_Controller ) {
			$errors[] = 'The listmonk provider controller is not registered.';
		}
	}

	$routes = rest_get_server()->get_routes();
	$expected_routes = array(
		${phpString('/newspack-newsletters/v1/(?P<id>[\\a-z]+)/sync-error')},
		${phpString('/newspack-newsletters/v1/listmonk/(?P<id>[\\d]+)/retrieve')},
		${phpString('/newspack-newsletters/v1/listmonk/(?P<id>[\\d]+)/test')},
		'/newspack-listmonk-connector/v1/listmonk-settings/item',
		'/newspack-listmonk-connector/v1/newsletter-preview/item',
		'/newspack-listmonk-connector/v1/newsletter-sync',
	);
	foreach ( $expected_routes as $route ) {
		if ( empty( $routes[ $route ] ) ) {
			$errors[] = sprintf( 'Missing REST route: %s', $route );
		}
	}

	if ( $provider instanceof Newspack_Listmonk_Connector_Provider ) {
		$connection = $provider->test_connection();
		if ( ! is_wp_error( $connection ) ) {
			$errors[] = 'Missing credentials did not return a WP_Error.';
		} elseif ( 'newspack_listmonk_connector_missing_credentials' !== $connection->get_error_code() ) {
			$errors[] = 'Missing credentials returned unexpected error code: ' . $connection->get_error_code();
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
		'provider' => 'listmonk',
		'providerClass' => get_class( Newspack_Newsletters::get_service_provider_instance( 'listmonk' ) ),
		'controllerClass' => get_class( $controller ),
		'serviceProviderOption' => get_option( 'newspack_newsletters_service_provider' ),
		'routes' => array(
			${phpString('/newspack-newsletters/v1/(?P<id>[\\a-z]+)/sync-error')},
			${phpString('/newspack-newsletters/v1/listmonk/(?P<id>[\\d]+)/retrieve')},
			${phpString('/newspack-newsletters/v1/listmonk/(?P<id>[\\d]+)/test')},
			${phpString('/newspack-listmonk-connector/v1/listmonk-settings/item')},
			${phpString('/newspack-listmonk-connector/v1/newsletter-preview/item')},
			${phpString('/newspack-listmonk-connector/v1/newsletter-sync')}
		),
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

printCommandOutput(wpEval(php));
