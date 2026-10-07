/**
 * Finds PHP diagnostics that WordPress printed into a rendered page
 * (WP_DEBUG_DISPLAY on), e.g. "<b>Warning</b>: Undefined array key ...".
 */
export class PhpErrors {
	static FATALS = [ 'Fatal error', 'Parse error' ];

	static ALL = [ ...PhpErrors.FATALS, 'Warning', 'Notice', 'Deprecated' ];

	static CRITICAL_ERROR_TEXT = 'There has been a critical error on this website.';

	/**
	 * @param {string} html  Page HTML.
	 * @param {string[]} levels PHP error labels to look for.
	 * @return {string[]} One line per diagnostic found.
	 */
	static find( html, levels = PhpErrors.ALL ) {
		const label = levels.map( ( level ) => level.replace( ' ', '\\s+' ) ).join( '|' );
		// An uncaught fatal spans lines (message, stack trace, "thrown in ... on
		// line N"), so the message may run over newlines, within a bound.
		const pattern = new RegExp( `(?:<b>)?(?:PHP\\s+)?(?:${ label })(?:</b>)?:\\s[\\s\\S]{0,4000}?\\son\\sline\\s(?:<b>)?\\d+`, 'g' );
		const found = html.match( pattern ) ?? [];
		if ( html.includes( PhpErrors.CRITICAL_ERROR_TEXT ) ) {
			found.push( PhpErrors.CRITICAL_ERROR_TEXT );
		}
		return found;
	}

	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {string[]} levels
	 */
	static async onPage( page, levels = PhpErrors.ALL ) {
		return PhpErrors.find( await page.content(), levels );
	}
}
