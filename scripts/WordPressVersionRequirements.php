<?php
/**
 * WordPress version-requirement policy for the version-bump workflow.
 *
 * Pure logic, no I/O: scripts/wp-versions.php is the CLI client that fetches
 * from api.wordpress.org and reads/writes files.
 *
 * @package Procyon\Dario
 */

declare(strict_types=1);

namespace Procyon\Dario\Tooling;

final class WordPressVersionRequirements {

	/**
	 * `Requires at least` for a given latest release: the previous major
	 * (WooCommerce's L-1 policy). 7.1 -> 7.0, 8.0 -> 7.9.
	 */
	public function requiredFor( string $latest ): string {
		[ $major, $minor ] = $this->majorMinor( $latest );
		if ( 0 === $minor ) {
			return ( $major - 1 ) . '.9';
		}
		return $major . '.' . ( $minor - 1 );
	}

	/**
	 * `Tested up to` from an api.wordpress.org/core/version-check/1.7/
	 * response: the major.minor of the first (latest) offer.
	 *
	 * @param array<string, mixed> $version_check Decoded version-check JSON.
	 */
	public function testedUpToFrom( array $version_check ): string {
		$current = $version_check['offers'][0]['current'] ?? '';
		[ $major, $minor ] = $this->majorMinor( (string) $current );
		return $major . '.' . $minor;
	}

	/**
	 * Newest release in a major.minor branch, from an
	 * api.wordpress.org/core/stable-check/1.0/ response (version => status).
	 *
	 * @param array<string, string> $stable_check Decoded stable-check JSON.
	 */
	public function newestPatchOf( string $branch, array $stable_check ): string {
		$in_branch = array_filter(
			array_map( 'strval', array_keys( $stable_check ) ),
			static fn ( string $version ): bool => $version === $branch || str_starts_with( $version, $branch . '.' )
		);
		if ( [] === $in_branch ) {
			throw new \UnexpectedValueException( "No WordPress release found in branch {$branch}" );
		}
		usort( $in_branch, 'version_compare' );
		return (string) end( $in_branch );
	}

	/**
	 * Set `Tested up to` and `Requires at least` in a readme.txt header.
	 */
	public function updateReadme( string $readme, string $tested_up_to, string $requires_at_least ): string {
		$readme = $this->replaceHeaderField( $readme, "\n== ", 'Tested up to', $tested_up_to );
		return $this->replaceHeaderField( $readme, "\n== ", 'Requires at least', $requires_at_least );
	}

	/**
	 * Set `Requires at least` in the main plugin file's header docblock.
	 */
	public function updatePluginHeader( string $plugin_file, string $requires_at_least ): string {
		return $this->replaceHeaderField( $plugin_file, '*/', 'Requires at least', $requires_at_least );
	}

	/**
	 * Read `Requires at least` from a readme.txt header.
	 */
	public function requiresAtLeast( string $readme ): string {
		return $this->readHeaderField( $readme, "\n== ", 'Requires at least' );
	}

	/**
	 * Read `Tested up to` from a readme.txt header.
	 */
	public function testedUpTo( string $readme ): string {
		return $this->readHeaderField( $readme, "\n== ", 'Tested up to' );
	}

	private function readHeaderField( string $contents, string $header_end, string $field ): string {
		[ $head ] = $this->splitHeader( $contents, $header_end );
		if ( 1 !== preg_match( $this->fieldPattern( $field ), $head, $m ) ) {
			throw new \UnexpectedValueException( "Header field not found: {$field}" );
		}
		return $m[2];
	}

	/**
	 * Replace one field's value inside the header (everything before $header_end).
	 */
	private function replaceHeaderField( string $contents, string $header_end, string $field, string $value ): string {
		[ $head, $rest ] = $this->splitHeader( $contents, $header_end );
		$count           = 0;
		$head            = (string) preg_replace( $this->fieldPattern( $field ), '${1}' . $value, $head, 1, $count );
		if ( 1 !== $count ) {
			throw new \UnexpectedValueException( "Header field not found: {$field}" );
		}
		return $head . $rest;
	}

	/**
	 * @return array{0: string, 1: string} Header and the remainder.
	 */
	private function splitHeader( string $contents, string $header_end ): array {
		$end = strpos( $contents, $header_end );
		if ( false === $end ) {
			return [ $contents, '' ];
		}
		return [ substr( $contents, 0, $end ), substr( $contents, $end ) ];
	}

	private function fieldPattern( string $field ): string {
		return '/^([ \t*]*' . preg_quote( $field, '/' ) . ':[ \t]*)([0-9.]+)/m';
	}

	/**
	 * @return array{0: int, 1: int}
	 */
	private function majorMinor( string $version ): array {
		if ( 1 !== preg_match( '/^(\d+)\.(\d+)/', $version, $m ) ) {
			throw new \InvalidArgumentException( "Not a WordPress version: {$version}" );
		}
		return [ (int) $m[1], (int) $m[2] ];
	}
}
