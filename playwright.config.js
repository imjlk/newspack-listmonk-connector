const path = require( 'node:path' );

const { defineConfig, devices } = require( '@playwright/test' );

const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8888';
const artifactsPath =
	process.env.WP_ARTIFACTS_PATH || path.join( process.cwd(), 'artifacts' );

module.exports = defineConfig( {
	expect: {
		timeout: 15_000,
	},
	forbidOnly: Boolean( process.env.CI ),
	outputDir: path.join( artifactsPath, 'e2e-results' ),
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],
	retries: process.env.CI ? 2 : 0,
	testDir: './tests/e2e',
	timeout: 120_000,
	use: {
		actionTimeout: 15_000,
		baseURL: baseUrl,
		headless: true,
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		viewport: {
			height: 720,
			width: 1280,
		},
	},
	webServer: {
		command: 'pnpm run env:start',
		reuseExistingServer: true,
		timeout: 180_000,
		url: baseUrl,
	},
	workers: 1,
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
			},
		},
	],
} );
