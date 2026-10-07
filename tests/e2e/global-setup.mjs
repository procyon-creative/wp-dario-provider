import { request } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
import { LandoSite } from './support/LandoSite.mjs';

/**
 * Logs in once and saves the admin storage state that the `admin`,
 * `requestUtils` and `page` fixtures reuse (the package's documented setup,
 * as in Gutenberg's own global setup).
 *
 * @param {import('@playwright/test').FullConfig} config
 */
export default async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath = typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, { storageStatePath, baseURL } );
	await requestUtils.setupRest();
	await requestContext.dispose();

	new LandoSite().enableDebugDisplay();
}
