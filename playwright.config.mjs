import { defineConfig, devices } from '@playwright/test';

// Read by @wordpress/e2e-test-utils-playwright (its config and fixtures),
// which the global setup and specs load after this file.
process.env.WP_BASE_URL ??= 'http://wp-dario-test.lndo.site';
process.env.WP_USERNAME ??= 'admin';
process.env.WP_PASSWORD ??= 'admin';
process.env.STORAGE_STATE_PATH ??= 'artifacts/storage-states/admin.json';

/**
 * Browser end-to-end tests against the Lando site (`lando start` first).
 * Specs share one WordPress, so they run serially in one worker and each
 * one resets the plugin's state itself.
 */
export default defineConfig( {
	testDir: './tests/e2e/specs',
	globalSetup: './tests/e2e/global-setup.mjs',
	outputDir: 'test-results',
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	reporter: [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'playwright-report' } ] ],
	use: {
		baseURL: process.env.WP_BASE_URL,
		storageState: process.env.STORAGE_STATE_PATH,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
