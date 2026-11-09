<?php
/**
 * Release check for the staged plugin's vendor/ folder (run by bin/build.sh).
 *
 * The zip must ship only the production Composer packages. A local
 * `composer install` also installs the dev packages (WP-CLI, PHP_CodeSniffer, ...)
 * and writes them into vendor/composer/autoload_*.php. If those package folders are
 * then left out of the zip, the plugin fatals on load because the autoloader tries to
 * include files that are not there.
 *
 * This script:
 *   1. fails if vendor/ was installed with dev packages (fix: `git checkout -- vendor` to
 *      restore the committed production vendor/, or `composer install --no-dev`);
 *   2. removes package folders that Composer does not list as installed (for example a
 *      leftover vendor/wp-cli from an earlier dev install), so they are not shipped;
 *   3. loads the staged autoloader in a separate PHP process and checks that the
 *      Checkout.com SDK resolves.
 *
 * Usage: php bin/check-vendor.php <staged-plugin-dir>
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$plugin_dir = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
$vendor_dir = $plugin_dir . '/vendor';
$installed  = $vendor_dir . '/composer/installed.php';

if ( '' === $plugin_dir || ! is_file( $installed ) ) {
	fwrite( STDERR, "❌ vendor/composer/installed.php not found in '{$plugin_dir}'.\n" );
	exit( 1 );
}

$data = require $installed;

// 1. Production-only install.
if ( ! empty( $data['root']['dev'] ) ) {
	fwrite( STDERR, "❌ vendor/ was installed with dev packages (WP-CLI, PHP_CodeSniffer, ...).\n" );
	fwrite( STDERR, "   Restore the committed production vendor/ with 'git checkout -- vendor'\n" );
	fwrite( STDERR, "   (or run 'composer install --no-dev') and build again.\n" );
	exit( 1 );
}

// 2. Remove package folders Composer does not list as installed.
$listed = array();
foreach ( $data['versions'] as $package ) {
	if ( ! empty( $package['install_path'] ) ) {
		$path = realpath( $package['install_path'] );
		if ( false !== $path ) {
			$listed[ $path ] = true;
		}
	}
}

$remove_dir = function ( $dir ) use ( &$remove_dir ) {
	foreach ( array_diff( scandir( $dir ), array( '.', '..' ) ) as $entry ) {
		$path = $dir . '/' . $entry;
		( is_dir( $path ) && ! is_link( $path ) ) ? $remove_dir( $path ) : unlink( $path );
	}
	rmdir( $dir );
};

foreach ( glob( $vendor_dir . '/*', GLOB_ONLYDIR ) as $vendor_name_dir ) {
	if ( in_array( basename( $vendor_name_dir ), array( 'composer', 'bin' ), true ) ) {
		continue;
	}
	foreach ( glob( $vendor_name_dir . '/*', GLOB_ONLYDIR ) as $package_dir ) {
		if ( ! isset( $listed[ realpath( $package_dir ) ] ) ) {
			echo '   Removing unlisted package folder: ' . substr( $package_dir, strlen( $plugin_dir ) + 1 ) . "\n";
			$remove_dir( $package_dir );
		}
	}
	if ( array() === array_diff( scandir( $vendor_name_dir ), array( '.', '..' ) ) ) {
		rmdir( $vendor_name_dir );
	}
}

// 3. The staged autoloader loads and resolves the SDK.
$probe = 'require ' . var_export( $vendor_dir . '/autoload.php', true ) . ';'
	. ' exit( class_exists( "Checkout\\\\CheckoutSdk" ) ? 0 : 2 );';
$cmd   = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $probe ) . ' 2>&1';
exec( $cmd, $output, $status );

if ( 0 !== $status ) {
	fwrite( STDERR, "❌ The staged vendor/autoload.php failed to load the Checkout.com SDK:\n" );
	fwrite( STDERR, '   ' . implode( "\n   ", $output ) . "\n" );
	exit( 1 );
}

echo "✅ vendor/ is production-only and its autoloader loads\n";
