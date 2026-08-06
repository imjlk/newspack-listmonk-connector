import {
	logStep,
	phpString,
	printCommandOutput,
	resolvePluginSlug,
	runWp,
	wpEval,
} from './smoke-lib.mjs';

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
	if ( 'listmonk' !== newspack_listmonk_connector_newspack_service_provider() ) {
		$errors[] = 'The active Newspack service provider is not listmonk.';
	}

	$provider = newspack_listmonk_connector_get_newspack_provider_instance( 'listmonk' );
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
	$route_namespaces = array(
		'newspack' => '/' . newspack_listmonk_connector_newspack_rest_namespace(),
		'listmonk' => '/' . newspack_listmonk_connector_newspack_rest_namespace( 'listmonk' ),
	);
	$route_checks = array(
		'sync-error' => static function ( $route ) use ( $route_namespaces ) {
			return 0 === strpos( $route, $route_namespaces['newspack'] . '/' ) && false !== strpos( $route, 'sync-error' );
		},
		'retrieve' => static function ( $route ) use ( $route_namespaces ) {
			return 0 === strpos( $route, $route_namespaces['listmonk'] . '/' ) && false !== strpos( $route, 'retrieve' );
		},
		'test' => static function ( $route ) use ( $route_namespaces ) {
			return 0 === strpos( $route, $route_namespaces['listmonk'] . '/' ) && false !== strpos( $route, 'test' );
		},
		'listmonk-settings' => static function ( $route ) {
			return '/wp-typia-newsletter-connector/v1/listmonk-settings/item' === $route;
		},
		'newsletter-preview' => static function ( $route ) {
			return '/wp-typia-newsletter-connector/v1/newsletter-preview/item' === $route;
		},
		'newsletter-sync' => static function ( $route ) {
			return '/wp-typia-newsletter-connector/v1/newsletter-sync' === $route;
		},
		'campaign-analytics' => static function ( $route ) {
			return '/wp-typia-newsletter-connector/v1/campaign-analytics/item' === $route;
		},
	);
	$matched_routes = array();
	foreach ( $route_checks as $label => $check ) {
		foreach ( array_keys( $routes ) as $route ) {
			if ( $check( $route ) ) {
				$matched_routes[ $label ] = $route;
				continue 2;
			}
		}
		$errors[] = sprintf( 'Missing REST route for %s.', $label );
	}

	if ( newspack_listmonk_connector_newspack_newsletter_post_type() !== Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT ) {
		$errors[] = 'Compatibility helper resolved an unexpected newsletter post type.';
	}
	if ( newspack_listmonk_connector_newspack_email_html_meta_key() !== Newspack_Newsletters::EMAIL_HTML_META ) {
		$errors[] = 'Compatibility helper resolved an unexpected email HTML meta key.';
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
		'providerClass' => get_class( newspack_listmonk_connector_get_newspack_provider_instance( 'listmonk' ) ),
		'controllerClass' => get_class( $controller ),
		'serviceProviderOption' => get_option( 'newspack_newsletters_service_provider' ),
		'compat' => array(
			'newspackNamespace' => newspack_listmonk_connector_newspack_rest_namespace(),
			'listmonkNamespace' => newspack_listmonk_connector_newspack_rest_namespace( 'listmonk' ),
			'newsletterPostType' => newspack_listmonk_connector_newspack_newsletter_post_type(),
			'emailHtmlMetaKey' => newspack_listmonk_connector_newspack_email_html_meta_key(),
		),
		'routes' => $matched_routes,
	),
	JSON_PRETTY_PRINT
) . PHP_EOL;
`;

printCommandOutput(wpEval(php));
