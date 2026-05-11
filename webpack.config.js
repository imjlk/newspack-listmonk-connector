const fs = require( 'fs' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const STATIC_METADATA_FILENAMES = new Set( [
	'block.json',
	'render.php',
	'typia.openapi.json',
	'typia.manifest.json',
	'typia.schema.json',
	'typia-validator.php',
] );

function isMetadataAsset( filename ) {
	return (
		STATIC_METADATA_FILENAMES.has( filename ) ||
		filename.endsWith( '.schema.json' ) ||
		filename.endsWith( '.openapi.json' )
	);
}

function normalizeScriptModuleAssetSource( source ) {
	return String( source ).replace(
		/'dependencies'\s*=>\s*array\([^)]*\)/,
		"'dependencies' => array()"
	);
}

function getBlockDirs() {
	const blocksRoot = path.resolve( process.cwd(), 'src/blocks' );

	if ( ! fs.existsSync( blocksRoot ) ) {
		return [];
	}

	return fs
		.readdirSync( blocksRoot, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() )
		.map( ( entry ) => ( {
			dir: path.join( blocksRoot, entry.name ),
			slug: entry.name,
		} ) );
}

function getMetadataEntries() {
	const entries = [];

	for ( const blockDir of getBlockDirs() ) {
		for ( const inputPath of findMetadataFiles( blockDir.dir ) ) {
			entries.push( {
				inputPath,
				outputPath: path.join(
					'blocks',
					blockDir.slug,
					path.relative( blockDir.dir, inputPath )
				),
			} );
		}
	}

	return entries;
}

function findMetadataFiles( directory ) {
	const metadataFiles = [];

	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const entryPath = path.join( directory, entry.name );

		if ( entry.isDirectory() ) {
			metadataFiles.push( ...findMetadataFiles( entryPath ) );
			continue;
		}

		if ( entry.isFile() && isMetadataAsset( entry.name ) ) {
			metadataFiles.push( entryPath );
		}
	}

	return metadataFiles;
}

function isScriptModuleAsset( assetName ) {
	return /(^|\/)(interactivity|view)\.asset\.php$/.test( assetName );
}

function normalizeScriptModuleAssetsInDirectory( directory ) {
	if ( ! fs.existsSync( directory ) ) {
		return;
	}

	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const entryPath = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			normalizeScriptModuleAssetsInDirectory( entryPath );
			continue;
		}
		if ( entry.isFile() && isScriptModuleAsset( entryPath ) ) {
			fs.writeFileSync(
				entryPath,
				normalizeScriptModuleAssetSource(
					fs.readFileSync( entryPath, 'utf8' )
				)
			);
		}
	}
}

class MetadataAssetPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'WorkspaceMetadataAssetPlugin',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'WorkspaceMetadataAssetPlugin',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_ADDITIONS,
					},
					() => {
						for ( const entry of getMetadataEntries() ) {
							if ( compilation.getAsset( entry.outputPath ) ) {
								continue;
							}

							compilation.emitAsset(
								entry.outputPath,
								new compiler.webpack.sources.RawSource(
									fs.readFileSync( entry.inputPath )
								)
							);
						}

						for ( const asset of compilation.getAssets() ) {
							if ( ! isScriptModuleAsset( asset.name ) ) {
								continue;
							}

							compilation.updateAsset(
								asset.name,
								new compiler.webpack.sources.RawSource(
									normalizeScriptModuleAssetSource(
										asset.source.source()
									)
								)
							);
						}
					}
				);
			}
		);

		compiler.hooks.afterEmit.tap(
			'WorkspaceMetadataAssetPlugin',
			( compilation ) => {
				if ( ! compilation.outputOptions.path ) {
					return;
				}

				normalizeScriptModuleAssetsInDirectory(
					compilation.outputOptions.path
				);
			}
		);
	}
}

function toWebpackConfigs( config ) {
	return Array.isArray( config ) ? config : [ config ];
}

function isModuleConfig( config ) {
	return config?.output?.module === true;
}

function getEditorEntries() {
	return Object.fromEntries(
		getBlockDirs().map( ( blockDir ) => [
			`blocks/${ blockDir.slug }/index`,
			path.join( blockDir.dir, 'index.tsx' ),
		] )
	);
}

function getSharedEditorEntries() {
	const entries = [];

	for ( const [ entryName, candidates ] of [
		[
			'bindings/index',
			[ 'src/bindings/index.ts', 'src/bindings/index.js' ],
		],
		[
			'editor-plugins/index',
			[ 'src/editor-plugins/index.ts', 'src/editor-plugins/index.js' ],
		],
		[
			'admin-views/index',
			[ 'src/admin-views/index.ts', 'src/admin-views/index.js' ],
		],
	] ) {
		for ( const relativePath of candidates ) {
			const entryPath = path.resolve( process.cwd(), relativePath );
			if ( ! fs.existsSync( entryPath ) ) {
				continue;
			}

			entries.push( [ entryName, entryPath ] );
			break;
		}
	}

	return Object.fromEntries( entries );
}

function getOptionalModuleEntries() {
	const entries = [];

	for ( const blockDir of getBlockDirs() ) {
		for ( const filename of [
			'interactivity.ts',
			'interactivity.js',
			'view.ts',
			'view.js',
		] ) {
			const entryPath = path.join( blockDir.dir, filename );
			if ( ! fs.existsSync( entryPath ) ) {
				continue;
			}

			entries.push( [
				`blocks/${ blockDir.slug }/${ filename.replace(
					/\.[^.]+$/,
					''
				) }`,
				entryPath,
			] );
		}
	}

	return Object.fromEntries( entries );
}

module.exports = async () => {
	const { loadCompatibleTypiaWebpackPlugin } = await import(
		'@wp-typia/block-runtime/blocks'
	);
	const UnpluginTypia = await loadCompatibleTypiaWebpackPlugin( {
		importTypiaWebpackPlugin: () => import( '@typia/unplugin/webpack' ),
		projectRoot: process.cwd(),
	} );
	const resolvedDefaultConfig =
		typeof defaultConfig === 'function'
			? await defaultConfig()
			: defaultConfig;
	const editorEntries = {
		...getSharedEditorEntries(),
		...getEditorEntries(),
	};
	const moduleEntries = getOptionalModuleEntries();
	const configs = toWebpackConfigs( resolvedDefaultConfig ).map(
		( config ) => ( {
			...config,
			entry: async () =>
				isModuleConfig( config ) ? moduleEntries : editorEntries,
			resolve: {
				...( config.resolve || {} ),
				extensionAlias: {
					...( config.resolve?.extensionAlias || {} ),
					'.js': [ '.js', '.ts', '.tsx' ],
				},
			},
			plugins: [
				UnpluginTypia(),
				...( config.plugins || [] ),
				new MetadataAssetPlugin(),
			],
		} )
	);

	return configs.length === 1 ? configs[ 0 ] : configs;
};
