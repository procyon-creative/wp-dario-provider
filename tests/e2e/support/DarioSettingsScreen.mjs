/**
 * Settings → Dario AI Connector, driven through the browser.
 */
export class DarioSettingsScreen {
	static PATH = 'options-general.php';

	static QUERY = 'page=dario-ai-connector';

	/**
	 * @param {{ page: import('@playwright/test').Page, admin: import('@wordpress/e2e-test-utils-playwright').Admin }} fixtures
	 */
	constructor( { page, admin } ) {
		this.page = page;
		this.admin = admin;
		this.backendEnabled = page.locator( '#dario-openai_backend_enabled' );
		this.baseUrl = page.locator( '#dario-openai_base_url' );
		this.apiKey = page.locator( '#dario-openai_api_key' );
		this.saveButton = page.getByRole( 'button', { name: 'Save settings' } );
		this.errorNotice = page.locator( '.wrap .notice-error' );
		this.successNotice = page.locator( '.wrap .notice-success' );
	}

	async visit() {
		await this.admin.visitAdminPage( DarioSettingsScreen.PATH, DarioSettingsScreen.QUERY );
	}

	/**
	 * Fills the OpenAI backend fields and submits the settings form.
	 *
	 * @param {{ enabled: boolean, baseUrl?: string, apiKey?: string }} backend
	 */
	async saveBackend( { enabled, baseUrl = '', apiKey = '' } ) {
		await this.backendEnabled.setChecked( enabled );
		await this.baseUrl.fill( baseUrl );
		await this.apiKey.fill( apiKey );
		await Promise.all( [
			this.page.waitForURL( `**/${ DarioSettingsScreen.PATH }?${ DarioSettingsScreen.QUERY }` ),
			this.saveButton.click(),
		] );
	}
}
