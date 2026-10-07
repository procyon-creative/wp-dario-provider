<?php
/**
 * File and api.wordpress.org side of the WordPress version-bump workflow.
 *
 * Applies WordPressVersionRequirements to readme.txt and the plugin header,
 * and resolves the WordPress version each CI leg downloads.
 *
 * @package Procyon\Dario
 */

declare(strict_types=1);

namespace Procyon\Dario\Tooling;

require_once __DIR__ . '/WordPressVersionRequirements.php';

final class WordPressVersionBump {

	public const VERSION_CHECK_URL = 'https://api.wordpress.org/core/version-check/1.7/';
	public const STABLE_CHECK_URL  = 'https://api.wordpress.org/core/stable-check/1.0/';

	/** @var callable(string): array<string, mixed> */
	private $fetch_json;

	private WordPressVersionRequirements $policy;

	/**
	 * @param callable(string): array<string, mixed>|null $fetch_json URL -> decoded JSON; defaults to an HTTP GET.
	 */
	public function __construct(
		private string $readme_path,
		private string $plugin_path,
		?callable $fetch_json = null
	) {
		$this->fetch_json = $fetch_json ?? [ self::class, 'httpGetJson' ];
		$this->policy     = new WordPressVersionRequirements();
	}

	/**
	 * Bump `Tested up to` to the latest release and `Requires at least` to
	 * the previous major. Never downgrades: returns null and writes nothing
	 * unless the latest release is newer than the current `Tested up to`.
	 *
	 * @return array{tested: string, required: string}|null
	 */
	public function bump(): ?array {
		$readme  = $this->read( $this->readme_path );
		$tested  = $this->policy->testedUpToFrom( ( $this->fetch_json )( self::VERSION_CHECK_URL ) );
		$current = $this->policy->testedUpTo( $readme );
		if ( ! version_compare( $tested, $current, '>' ) ) {
			return null;
		}
		$required = $this->policy->requiredFor( $tested );
		$this->write( $this->readme_path, $this->policy->updateReadme( $readme, $tested, $required ) );
		$this->write( $this->plugin_path, $this->policy->updatePluginHeader( $this->read( $this->plugin_path ), $required ) );
		return [
			'tested'   => $tested,
			'required' => $required,
		];
	}

	/**
	 * `wp core download --version` value for a CI leg: `latest`, or the
	 * newest patch of readme.txt's `Requires at least` branch for `minimum`.
	 */
	public function downloadVersion( string $target ): string {
		return match ( $target ) {
			'latest'  => 'latest',
			'minimum' => $this->policy->newestPatchOf(
				$this->policy->requiresAtLeast( $this->read( $this->readme_path ) ),
				( $this->fetch_json )( self::STABLE_CHECK_URL )
			),
			default   => throw new \InvalidArgumentException( "Unknown download target: {$target}" ),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function httpGetJson( string $url ): array {
		$body = file_get_contents( $url );
		if ( false === $body ) {
			throw new \RuntimeException( "Failed to fetch {$url}" );
		}
		return json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
	}

	private function read( string $path ): string {
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			throw new \RuntimeException( "Failed to read {$path}" );
		}
		return $contents;
	}

	private function write( string $path, string $contents ): void {
		if ( false === file_put_contents( $path, $contents ) ) {
			throw new \RuntimeException( "Failed to write {$path}" );
		}
	}
}
