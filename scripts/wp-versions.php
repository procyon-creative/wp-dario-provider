<?php
/**
 * CLI client for WordPressVersionBump, used by GitHub Actions.
 *
 *   php scripts/wp-versions.php bump
 *       When a newer WordPress release exists than readme.txt `Tested up to`,
 *       sets it to that release and `Requires at least` (readme.txt + plugin
 *       header) to the previous major, and prints `tested=X` / `required=Y`
 *       lines for $GITHUB_OUTPUT. Otherwise changes and prints nothing.
 *
 *   php scripts/wp-versions.php download-version latest|minimum
 *       Prints the `wp core download --version` value for a CI leg.
 *
 * @package Procyon\Dario
 */

declare(strict_types=1);

require_once __DIR__ . '/WordPressVersionBump.php';

use Procyon\Dario\Tooling\WordPressVersionBump;

$root = dirname( __DIR__ );
$bump = new WordPressVersionBump( $root . '/readme.txt', $root . '/procyon-dario-provider.php' );

switch ( $argv[1] ?? '' ) {
	case 'bump':
		$result = $bump->bump();
		if ( null !== $result ) {
			fwrite( STDOUT, "tested={$result['tested']}\nrequired={$result['required']}\n" );
		}
		break;

	case 'download-version':
		fwrite( STDOUT, $bump->downloadVersion( $argv[2] ?? '' ) . "\n" );
		break;

	default:
		fwrite( STDERR, "Usage: php scripts/wp-versions.php bump | download-version latest|minimum\n" );
		exit( 2 );
}
