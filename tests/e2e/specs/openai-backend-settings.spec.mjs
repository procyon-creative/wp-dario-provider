import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { DarioSettingsScreen } from '../support/DarioSettingsScreen.mjs';
import { LandoSite } from '../support/LandoSite.mjs';
import { PhpErrors } from '../support/PhpErrors.mjs';

const site = new LandoSite();

const BASE_URL = 'https://llm.example.test/v1';

const API_KEY = 'sk-e2e-test-key';

test.describe( 'Settings → Dario AI Connector: OpenAI backend', () => {
	/** @type {DarioSettingsScreen} */
	let screen;

	test.beforeEach( async ( { page, admin } ) => {
		site.resetPlugin();
		screen = new DarioSettingsScreen( { page, admin } );
		await screen.visit();
	} );

	test.afterAll( () => {
		site.resetPlugin();
	} );

	test( 'renders with no PHP diagnostics and an empty base URL', async ( { page } ) => {
		await expect( page.getByRole( 'heading', { level: 1, name: 'Dario AI Connector' } ) ).toBeVisible();
		expect( await PhpErrors.onPage( page ) ).toEqual( [] );
		await expect( screen.baseUrl ).toHaveValue( '' );
	} );

	test( 'saves with the backend disabled and writes no backend file', async ( { page } ) => {
		await screen.saveBackend( { enabled: false } );

		await expect( screen.successNotice ).toContainText( 'Settings saved.' );
		await expect( screen.errorNotice ).toHaveCount( 0 );
		expect( await PhpErrors.onPage( page ) ).toEqual( [] );
		expect( site.readBackendFile() ).toBeNull();
	} );

	test( 'enabled with a blank base URL shows an error and writes no backend file', async () => {
		await screen.saveBackend( { enabled: true, baseUrl: '', apiKey: API_KEY } );

		await expect( screen.errorNotice ).toContainText( 'enter a valid base URL' );
		expect( site.readBackendFile() ).toBeNull();
	} );

	test( 'enabled with an invalid base URL shows an error, writes no file and keeps the typed value', async () => {
		await screen.saveBackend( { enabled: true, baseUrl: 'not a url', apiKey: API_KEY } );

		await expect( screen.errorNotice ).toContainText( 'enter a valid base URL' );
		expect( site.readBackendFile() ).toBeNull();
		await expect( screen.baseUrl ).toHaveValue( 'not a url' );
	} );

	test( 'enabled with a blank API key shows an error and writes no backend file', async () => {
		await screen.saveBackend( { enabled: true, baseUrl: BASE_URL, apiKey: '' } );

		await expect( screen.errorNotice ).toContainText( 'enter an API key' );
		expect( site.readBackendFile() ).toBeNull();
	} );

	test( 'a valid base URL and API key write the backend file', async () => {
		await screen.saveBackend( { enabled: true, baseUrl: BASE_URL, apiKey: API_KEY } );

		// The notice names the path PHP wrote under the web user's home, so
		// this also pins where `~/.dario/backends/` resolves in the container.
		await expect( screen.successNotice ).toContainText( `Backend file written to ${ site.backendPath() }.` );
		expect( site.readBackendFile() ).toEqual( {
			name: LandoSite.DEFAULT_BACKEND_NAME,
			provider: 'openai',
			apiKey: API_KEY,
			baseUrl: BASE_URL,
		} );
	} );

	test( 'disabling the backend afterward removes the backend file', async () => {
		await screen.saveBackend( { enabled: true, baseUrl: BASE_URL, apiKey: API_KEY } );
		expect( site.readBackendFile() ).not.toBeNull();

		await screen.saveBackend( { enabled: false, baseUrl: BASE_URL } );

		await expect( screen.successNotice ).toContainText( 'Settings saved.' );
		expect( site.readBackendFile() ).toBeNull();
	} );
} );
