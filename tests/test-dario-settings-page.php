<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// The "Run from a shell instead" snippet on the settings page must show
// users only the supported shell command. Lando is a developer-only env;
// its commands don't belong in the admin UI (WPD-31).

$source = file_get_contents( __DIR__ . '/../src/Admin/DarioSettingsPage.php' );
assert( is_string( $source ) && '' !== $source, 'expected to read DarioSettingsPage.php' );

assert(
	false === stripos( $source, 'lando' ),
	'DarioSettingsPage.php must not reference lando (found a lando string in the source).'
);

assert(
	false !== strpos( $source, 'dario login' ),
	'DarioSettingsPage.php must still show `dario login` as the shell fallback.'
);

// Saving settings syncs the backend through OpenAiBackendSync, which reads
// effective settings (overrides applied). The page must not write the backend
// file from the stored values itself.
assert(
	false !== strpos( $source, 'OpenAiBackendSync::run()' ),
	'handleSaveSettings must sync the backend via OpenAiBackendSync::run().'
);
assert(
	false === strpos( $source, 'DarioBackendConfig::save(' ),
	'DarioSettingsPage.php must not call DarioBackendConfig::save() directly.'
);

// No shipped code hard-codes an AI provider endpoint (Plugin Check
// AIProvider.DirectIntegration).
$shipped = [ __DIR__ . '/../procyon-dario-provider.php' ];
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( $file->isFile() ) {
		$shipped[] = $file->getPathname();
	}
}
foreach ( $shipped as $path ) {
	assert(
		false === stripos( (string) file_get_contents( $path ), 'api.openai.com' ),
		'shipped file must not reference api.openai.com: ' . $path
	);
}

echo "test-dario-settings-page ok\n";
