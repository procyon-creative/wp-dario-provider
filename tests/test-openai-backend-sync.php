<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Procyon\Dario\Admin\DarioSettings;
use Procyon\Dario\Admin\OpenAiBackendSync;
use Procyon\Dario\Sidecar\DarioBackendConfig;

// Saving settings syncs the OpenAI-compatible backend file from the
// effective settings (stored options + DARIO_OPENAI_* overrides). Missing or
// invalid required config fails loudly and leaves the backend file alone.

$tmp_home = sys_get_temp_dir() . '/wp-dario-sync-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $tmp_home, 0700, true );
putenv( 'HOME=' . $tmp_home );
$_SERVER['HOME'] = $tmp_home;

function sync_test_store( array $values ): void {
	test_option_store_reset();
	update_option( DarioSettings::OPTION_NAME, array_merge( DarioSettings::defaults(), $values ) );
}

function sync_test_backend_file( string $name ): ?array {
	$path = DarioBackendConfig::backendPath( $name );
	if ( $path === null || ! is_file( $path ) ) {
		return null;
	}
	$decoded = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $decoded ) ? $decoded : null;
}

// Seed an existing backend file so "not changed" is observable.
$seed = DarioBackendConfig::save( 'wordpress', 'sk-seed', 'https://seed.example.test/v1' );
assert( true === $seed['ok'] );

// Enabled, API key present, base URL blank: error, file untouched.
sync_test_store( [
	'openai_backend_enabled' => true,
	'openai_api_key'         => 'sk-new',
	'openai_base_url'        => '',
] );
$result = OpenAiBackendSync::run();
assert( false === $result['ok'], 'blank base URL with backend enabled is an error' );
assert( false !== stripos( $result['message'], 'base URL' ), 'error names the base URL' );
$file = sync_test_backend_file( 'wordpress' );
assert( is_array( $file ) && 'sk-seed' === $file['apiKey'] && 'https://seed.example.test/v1' === $file['baseUrl'], 'backend file unchanged after blank base URL' );

// Enabled, invalid base URL: error, file untouched.
sync_test_store( [
	'openai_backend_enabled' => true,
	'openai_api_key'         => 'sk-new',
	'openai_base_url'        => 'not-a-url',
] );
$result = OpenAiBackendSync::run();
assert( false === $result['ok'], 'invalid base URL with backend enabled is an error' );
$file = sync_test_backend_file( 'wordpress' );
assert( is_array( $file ) && 'sk-seed' === $file['apiKey'], 'backend file unchanged after invalid base URL' );

// Enabled, base URL valid, API key blank: error naming the key, file untouched.
sync_test_store( [
	'openai_backend_enabled' => true,
	'openai_api_key'         => '',
	'openai_base_url'        => 'https://llm.example.test/v1',
] );
$result = OpenAiBackendSync::run();
assert( false === $result['ok'], 'blank API key with backend enabled is an error' );
assert( false !== strpos( $result['message'], 'enter an API key' ), 'error tells the admin to enter an API key' );
$file = sync_test_backend_file( 'wordpress' );
assert( is_array( $file ) && 'sk-seed' === $file['apiKey'] && 'https://seed.example.test/v1' === $file['baseUrl'], 'backend file unchanged after blank API key' );

// Enabled with valid stored config: file written with the stored values.
sync_test_store( [
	'openai_backend_enabled' => true,
	'openai_api_key'         => 'sk-stored',
	'openai_base_url'        => 'https://stored.example.test/v1',
] );
$result = OpenAiBackendSync::run();
assert( true === $result['ok'], 'valid config writes the backend file' );
$file = sync_test_backend_file( 'wordpress' );
assert( is_array( $file ) && 'sk-stored' === $file['apiKey'] && 'https://stored.example.test/v1' === $file['baseUrl'], 'backend file has the stored values' );

// DARIO_OPENAI_* overrides win over stored options.
sync_test_store( [
	'openai_backend_enabled' => false,
	'openai_backend_name'    => 'wordpress',
	'openai_api_key'         => '',
	'openai_base_url'        => '',
] );
putenv( 'DARIO_OPENAI_BACKEND_ENABLED=1' );
putenv( 'DARIO_OPENAI_BACKEND_NAME=other' );
putenv( 'DARIO_OPENAI_BASE_URL=https://override.example.test/v1' );
putenv( 'DARIO_OPENAI_API_KEY=sk-override' );
$result = OpenAiBackendSync::run();
putenv( 'DARIO_OPENAI_BACKEND_ENABLED' );
putenv( 'DARIO_OPENAI_BACKEND_NAME' );
putenv( 'DARIO_OPENAI_BASE_URL' );
putenv( 'DARIO_OPENAI_API_KEY' );
assert( true === $result['ok'], 'override config writes the backend file' );
$file = sync_test_backend_file( 'other' );
assert( is_array( $file ) && 'sk-override' === $file['apiKey'] && 'https://override.example.test/v1' === $file['baseUrl'], 'backend file uses override values' );

// An invalid override base URL is an error too (overrides bypass sanitize()).
sync_test_store( [
	'openai_backend_enabled' => true,
	'openai_api_key'         => 'sk-stored',
	'openai_base_url'        => 'https://stored.example.test/v1',
] );
putenv( 'DARIO_OPENAI_BASE_URL=not-a-url' );
$result = OpenAiBackendSync::run();
putenv( 'DARIO_OPENAI_BASE_URL' );
assert( false === $result['ok'], 'invalid override base URL is an error' );
$file = sync_test_backend_file( 'wordpress' );
assert( is_array( $file ) && 'https://stored.example.test/v1' === $file['baseUrl'], 'backend file unchanged after invalid override' );

// Disabled with blank fields: no error, backend file removed.
sync_test_store( [
	'openai_backend_enabled' => false,
	'openai_api_key'         => '',
	'openai_base_url'        => '',
] );
$result = OpenAiBackendSync::run();
assert( true === $result['ok'], 'disabled backend with blank fields is not an error' );
assert( null === sync_test_backend_file( 'wordpress' ), 'disabled backend removes the backend file' );

// Cleanup
DarioBackendConfig::remove( 'wordpress' );
DarioBackendConfig::remove( 'other' );
@rmdir( $tmp_home . '/.dario/backends' );
@rmdir( $tmp_home . '/.dario' );
@rmdir( $tmp_home );

echo "test-openai-backend-sync ok\n";
