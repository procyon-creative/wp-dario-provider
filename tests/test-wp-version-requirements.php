<?php
/**
 * Version-requirement policy for the WordPress version-bump workflow.
 *
 * `Tested up to` tracks the latest WordPress release (major.minor) and
 * `Requires at least` is the previous major (L-1), in both readme.txt and
 * the plugin header. CI's minimum-version run downloads the newest patch of
 * the `Requires at least` branch.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/scripts/WordPressVersionRequirements.php';

use Procyon\Dario\Tooling\WordPressVersionRequirements;

$policy = new WordPressVersionRequirements();

// Case 1: Requires at least is the previous major of the latest release.
assert( '7.0' === $policy->requiredFor( '7.1' ), '7.1 -> 7.0' );
assert( '7.0' === $policy->requiredFor( '7.1.3' ), 'patch is ignored: 7.1.3 -> 7.0' );
assert( '7.9' === $policy->requiredFor( '8.0' ), '8.0 -> 7.9' );

// Case 2: Tested up to is the major.minor of version-check's first offer.
$version_check = [ 'offers' => [ [ 'response' => 'upgrade', 'current' => '7.1.3' ], [ 'current' => '7.0.7' ] ] ];
assert( '7.1' === $policy->testedUpToFrom( $version_check ), 'latest offer 7.1.3 -> Tested up to 7.1' );

// Case 3: readme.txt header fields are rewritten in place, alignment kept,
// and nothing below the header block is touched.
$readme = "=== Dario AI Connector ===\n"
	. "Requires at least: 7.0\n"
	. "Tested up to:      7.1\n"
	. "Stable tag:        0.2.3\n"
	. "\n"
	. "== Changelog ==\n"
	. "* Tested up to: 7.1 on the old box. Requires at least: 7.0 too.\n";
$expected_readme = "=== Dario AI Connector ===\n"
	. "Requires at least: 7.1\n"
	. "Tested up to:      7.2\n"
	. "Stable tag:        0.2.3\n"
	. "\n"
	. "== Changelog ==\n"
	. "* Tested up to: 7.1 on the old box. Requires at least: 7.0 too.\n";
assert( $expected_readme === $policy->updateReadme( $readme, '7.2', '7.1' ), 'readme.txt header bumped to 7.2 / 7.1' );
assert( '7.0' === $policy->requiresAtLeast( $readme ), 'reads Requires at least from readme.txt' );

// Case 4: the plugin header's Requires at least is rewritten in place.
$header          = "<?php\n\n/**\n * Plugin Name:       Dario AI Connector\n * Requires at least: 7.0\n * Requires PHP:      8.0\n */\n";
$expected_header = "<?php\n\n/**\n * Plugin Name:       Dario AI Connector\n * Requires at least: 7.1\n * Requires PHP:      8.0\n */\n";
assert( $expected_header === $policy->updatePluginHeader( $header, '7.1' ), 'plugin header bumped to 7.1' );

// Case 5: a file missing the field fails loudly instead of silently no-op'ing.
$threw = false;
try {
	$policy->updatePluginHeader( "<?php\n/**\n * Plugin Name: X\n */\n", '7.1' );
} catch ( \UnexpectedValueException $e ) {
	$threw = true;
}
assert( $threw, 'missing Requires at least must throw' );

// Case 6: the minimum-version CI run installs the newest patch of the
// Requires at least branch (from api.wordpress.org/core/stable-check/1.0/).
$stable_check = [
	'6.9.10' => 'outdated',
	'7.0'    => 'insecure',
	'7.0.2'  => 'insecure',
	'7.0.10' => 'outdated',
	'7.0.7'  => 'outdated',
	'7.1.3'  => 'latest',
	'7.10'   => 'latest',
];
assert( '7.0.10' === $policy->newestPatchOf( '7.0', $stable_check ), 'newest 7.0.x is 7.0.10 (version order, not string order)' );
assert( '7.1.3' === $policy->newestPatchOf( '7.1', $stable_check ), 'newest 7.1.x is 7.1.3, not 7.10' );

// Case 7: reads Tested up to from readme.txt.
assert( '7.1' === $policy->testedUpTo( $readme ), 'reads Tested up to from readme.txt' );

// Case 8: the shipped files carry the bump workflow's fields, so it can
// rewrite them, and they agree with each other. Values aren't pinned here:
// the bot's own PR changes them.
$root        = dirname( __DIR__ );
$repo_readme = (string) file_get_contents( $root . '/readme.txt' );
$repo_plugin = (string) file_get_contents( $root . '/procyon-dario-provider.php' );
$tested      = $policy->testedUpTo( $repo_readme );
$required    = $policy->requiresAtLeast( $repo_readme );
assert( $policy->requiredFor( $tested ) === $required, "readme.txt Requires at least ({$required}) must be the previous major of Tested up to ({$tested})" );
assert( 1 === preg_match( '/^ \* Requires at least: ' . preg_quote( $required, '/' ) . '$/m', $repo_plugin ), "plugin header Requires at least must match readme.txt ({$required})" );

echo "test-wp-version-requirements ok\n";
