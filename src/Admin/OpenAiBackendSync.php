<?php

declare(strict_types=1);

namespace Procyon\Dario\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Procyon\Dario\Sidecar\DarioBackendConfig;

/**
 * Brings Dario's OpenAI-compatible backend file in line with the effective
 * settings (stored options with DARIO_OPENAI_* overrides applied).
 *
 * Enabled with missing or invalid required config is an error, and the
 * backend file is left exactly as it was.
 *
 * @since 0.2.0
 */
class OpenAiBackendSync {

	/**
	 * @return array{ok:bool, message:string}
	 */
	public static function run(): array {
		$settings = DarioSettings::effective();
		$name     = (string) $settings['openai_backend_name'];

		if ( ! $settings['openai_backend_enabled'] ) {
			DarioBackendConfig::remove( $name );
			return [ 'ok' => true, 'message' => '' ];
		}

		$base_url = trim( (string) $settings['openai_base_url'] );
		if ( ! DarioSettings::isValidUrl( $base_url ) ) {
			return [
				'ok'      => false,
				'message' => __( 'OpenAI backend not saved: enter a valid base URL.', 'procyon-dario-provider' ),
			];
		}

		$api_key = trim( (string) $settings['openai_api_key'] );
		if ( $api_key === '' ) {
			return [
				'ok'      => false,
				'message' => __( 'OpenAI backend not saved: enter an API key.', 'procyon-dario-provider' ),
			];
		}

		$result = DarioBackendConfig::save( $name, $api_key, $base_url );
		if ( ! $result['ok'] ) {
			return [
				'ok'      => false,
				/* translators: %s: error message describing why the backend file could not be written. */
				'message' => sprintf( __( 'OpenAI backend not saved: %s.', 'procyon-dario-provider' ), (string) ( $result['error'] ?? 'unknown error' ) ),
			];
		}

		return [
			'ok'      => true,
			/* translators: %s: full filesystem path of the written backend JSON file. */
			'message' => sprintf( __( 'Backend file written to %s.', 'procyon-dario-provider' ), (string) ( $result['path'] ?? '' ) ),
		];
	}
}
