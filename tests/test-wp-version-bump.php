<?php
/**
 * WordPressVersionBump: the file + api.wordpress.org side of the
 * version-bump workflow, run against temp files and canned API responses.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/scripts/WordPressVersionBump.php';

use Procyon\Dario\Tooling\WordPressVersionBump;

$dir = sys_get_temp_dir() . '/wpd-version-bump-' . getmypid();
@mkdir( $dir );
$readme_path = $dir . '/readme.txt';
$plugin_path = $dir . '/plugin.php';

$readme = static fn ( string $tested, string $required ): string => "=== X ===\nRequires at least: {$required}\nTested up to:      {$tested}\n\n== Changelog ==\n";
$plugin = static fn ( string $required ): string => "<?php\n/**\n * Plugin Name:       X\n * Requires at least: {$required}\n */\n";

$api = static fn ( string $latest ): \Closure => static fn ( string $url ): array => str_contains( $url, 'version-check' )
	? [ 'offers' => [ [ 'current' => $latest ] ] ]
	: [ '7.0.2' => 'insecure', '7.0.7' => 'outdated', '7.1.3' => 'latest' ];

// Case 1: a newer release bumps both files and reports the new versions.
file_put_contents( $readme_path, $readme( '7.0', '6.9' ) );
file_put_contents( $plugin_path, $plugin( '6.9' ) );
$result = ( new WordPressVersionBump( $readme_path, $plugin_path, $api( '7.1.3' ) ) )->bump();
assert( [ 'tested' => '7.1', 'required' => '7.0' ] === $result, 'bump reports 7.1 / 7.0' );
assert( $readme( '7.1', '7.0' ) === file_get_contents( $readme_path ), 'readme.txt bumped' );
assert( $plugin( '7.0' ) === file_get_contents( $plugin_path ), 'plugin header bumped' );

// Case 2: a Tested up to ahead of the latest release (e.g. testing a beta)
// is never downgraded; nothing is written.
file_put_contents( $readme_path, $readme( '7.2', '7.1' ) );
file_put_contents( $plugin_path, $plugin( '7.1' ) );
$result = ( new WordPressVersionBump( $readme_path, $plugin_path, $api( '7.1.3' ) ) )->bump();
assert( null === $result, 'no bump when Tested up to is already ahead' );
assert( $readme( '7.2', '7.1' ) === file_get_contents( $readme_path ), 'readme.txt untouched' );
assert( $plugin( '7.1' ) === file_get_contents( $plugin_path ), 'plugin header untouched' );

// Case 3: CI download versions: `latest`, or the newest patch of the
// readme.txt Requires at least branch.
file_put_contents( $readme_path, $readme( '7.1', '7.0' ) );
$bump = new WordPressVersionBump( $readme_path, $plugin_path, $api( '7.1.3' ) );
assert( 'latest' === $bump->downloadVersion( 'latest' ), 'latest leg downloads latest' );
assert( '7.0.7' === $bump->downloadVersion( 'minimum' ), 'minimum leg downloads newest 7.0.x' );

unlink( $readme_path );
unlink( $plugin_path );
rmdir( $dir );

echo "test-wp-version-bump ok\n";
