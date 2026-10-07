import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { LandoSite } from '../support/LandoSite.mjs';
import { PhpErrors } from '../support/PhpErrors.mjs';

const site = new LandoSite();

const PROVIDER_ID = 'procyon_dario';

test.describe( 'Dario provider on the AI Client', () => {
	test.beforeEach( () => {
		site.resetPlugin();
	} );

	test.afterAll( () => {
		site.resetPlugin();
	} );

	test( 'is registered with the AI Client and listed on the Connectors screen', async ( { page, admin } ) => {
		expect( site.registeredProviderIds() ).toContain( PROVIDER_ID );

		await admin.visitAdminPage( 'options-connectors.php' );
		await expect( page.getByText( 'Dario', { exact: true } ) ).toBeVisible();
		expect( await PhpErrors.onPage( page, PhpErrors.FATALS ) ).toEqual( [] );
	} );

	test( "the ai plugin's admin pages load without PHP fatals", async ( { page, admin } ) => {
		await admin.visitAdminPage( 'index.php' );
		const aiPages = await page
			.locator( '#adminmenu a[href*="page=ai"]' )
			.evaluateAll( ( links ) => [ ...new Set( links.map( ( link ) => link.getAttribute( 'href' ) ) ) ] );
		expect( aiPages.length ).toBeGreaterThan( 0 );

		for ( const href of aiPages ) {
			const [ path, query ] = href.split( '?' );
			await admin.visitAdminPage( path, query );
			expect( await PhpErrors.onPage( page, PhpErrors.FATALS ), href ).toEqual( [] );
		}
	} );
} );
