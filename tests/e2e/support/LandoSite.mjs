import { execFileSync } from 'node:child_process';

/**
 * The Lando WordPress site the e2e specs run against, driven through
 * `lando wp` (wp-cli inside the appserver container).
 *
 * Plugin state that the WordPress REST API does not expose (the plugin's
 * options, Dario's backend files under the web user's home) is set up,
 * torn down and inspected here.
 */
export class LandoSite {
	/**
	 * Apache runs PHP as www-data, whose home in the Lando php image is
	 * /var/www, so the plugin's `~/.dario/backends/` resolves here for web
	 * requests. The "valid URL and key" spec asserts that the settings page
	 * reports this same path, so a drift fails loudly.
	 */
	static BACKENDS_DIR = '/var/www/.dario/backends';

	static DEFAULT_BACKEND_NAME = 'wordpress';

	static PLUGIN_OPTIONS = [ 'procyon_dario_settings', 'procyon_dario_flash' ];

	/**
	 * @param {string[]} args Arguments passed to `lando wp`.
	 * @return {string} Trimmed stdout.
	 */
	wp( args ) {
		return execFileSync( 'lando', [ 'wp', ...args ], {
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} ).trim();
	}

	/**
	 * Runs PHP inside the loaded WordPress and decodes its JSON output.
	 *
	 * @param {string} php PHP that echoes a JSON document.
	 */
	evalJson( php ) {
		return JSON.parse( this.wp( [ 'eval', php ] ) );
	}

	/**
	 * Shows PHP errors, warnings and notices in rendered pages so specs can
	 * see them. Idempotent.
	 */
	enableDebugDisplay() {
		this.wp( [ 'config', 'set', 'WP_DEBUG', 'true', '--raw' ] );
		this.wp( [ 'config', 'set', 'WP_DEBUG_DISPLAY', 'true', '--raw' ] );
	}

	/**
	 * Back to a site with no saved plugin settings and no backend file.
	 */
	resetPlugin() {
		const options = JSON.stringify( LandoSite.PLUGIN_OPTIONS );
		this.wp( [
			'eval',
			`foreach ( json_decode( '${ options }' ) as $o ) { delete_option( $o ); }
			$f = ${ this.backendPathLiteral() };
			foreach ( [ $f, $f . '.tmp' ] as $p ) { if ( file_exists( $p ) ) { unlink( $p ); } }`,
		] );
	}

	backendPath() {
		return `${ LandoSite.BACKENDS_DIR }/${ LandoSite.DEFAULT_BACKEND_NAME }.json`;
	}

	/**
	 * @return {object|null} The decoded backend file, or null when absent.
	 */
	readBackendFile() {
		return this.evalJson(
			`$f = ${ this.backendPathLiteral() }; echo file_exists( $f ) ? file_get_contents( $f ) : 'null';`
		);
	}

	/**
	 * The backend file path as a PHP string literal, for `wp eval` code.
	 */
	backendPathLiteral() {
		return JSON.stringify( this.backendPath() );
	}

	/**
	 * @return {string[]} Provider IDs registered with the AI Client.
	 */
	registeredProviderIds() {
		return this.evalJson(
			'echo wp_json_encode( \\WordPress\\AiClient\\AiClient::defaultRegistry()->getRegisteredProviderIds() );'
		);
	}
}
