<?php
/**
 * Local copy of the WooCommerce QIT "Validation" managed test, plus the header rules of the
 * WordPress.org Plugin Check (which QIT also runs).
 *
 * Errors fail the release, as in QIT; warnings are printed only.
 *
 * Usage: php bin/qit/check-validation.php [plugin-dir]
 * Exit:  0 = no errors, 1 = errors.
 */

$root      = rtrim( isset( $argv[1] ) ? $argv[1] : dirname( __DIR__, 2 ), '/' );
$main_file = $root . '/woocommerce-gateway-checkout-com.php';
$readme    = $root . '/readme.txt';

$errors   = array();
$warnings = array();

/**
 * Header fields from the first comment block of a file, or the header lines of a readme.
 */
function qit_headers( $file, $is_readme ) {
	$text = (string) file_get_contents( $file, false, null, 0, 8192 );
	if ( ! $is_readme && preg_match( '#/\*\*?(.*?)\*/#s', $text, $m ) ) {
		$text = $m[1];
	}
	$headers = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = preg_replace( '/^\s*\*?\s*/', '', $line );
		if ( $is_readme && '' === trim( $line ) && ! empty( $headers ) ) {
			break; // readme headers end at the first blank line.
		}
		if ( preg_match( '/^([A-Za-z][A-Za-z \-]+?):\s*(.+)$/', $line, $h ) ) {
			$headers[ strtolower( trim( $h[1] ) ) ] = trim( $h[2] );
		}
	}
	return $headers;
}

if ( ! is_file( $main_file ) ) {
	fwrite( STDERR, "Main plugin file not found: $main_file\n" );
	exit( 2 );
}
if ( ! is_file( $readme ) ) {
	$errors[] = 'readme.txt is missing (QIT Validation fails without it).';
}

$plugin = qit_headers( $main_file, false );
$read   = is_file( $readme ) ? qit_headers( $readme, true ) : array();

// QIT Validation: these plugin headers should be present (missing ones are warnings).
foreach ( array( 'requires at least', 'requires php', 'wc requires at least', 'wc tested up to', 'license' ) as $key ) {
	if ( empty( $plugin[ $key ] ) ) {
		$warnings[] = "Plugin header \"$key\" is missing.";
	}
}

// QIT Validation: the old "Woo:" header must not be used.
if ( isset( $plugin['woo'] ) ) {
	$errors[] = 'Remove the "Woo:" plugin header (QIT Validation).';
}

// Plugin Check: "Tested up to" belongs in the readme only (this failed 5.1.7).
if ( isset( $plugin['tested up to'] ) ) {
	$errors[] = 'Remove "Tested up to" from the main plugin file header; keep it in readme.txt only (Plugin Check error).';
}

// Plugin Check: readme headers.
foreach ( array( 'tested up to', 'stable tag', 'requires at least', 'requires php', 'license' ) as $key ) {
	if ( $read && empty( $read[ $key ] ) ) {
		$errors[] = "readme.txt header \"$key\" is missing.";
	}
}
if ( $read && isset( $read['stable tag'], $plugin['version'] ) && $read['stable tag'] !== $plugin['version'] ) {
	$errors[] = sprintf( 'readme.txt "Stable tag" (%s) does not match the plugin "Version" (%s).', $read['stable tag'], $plugin['version'] );
}
foreach ( array( 'requires at least', 'requires php' ) as $key ) {
	if ( isset( $read[ $key ], $plugin[ $key ] ) && $read[ $key ] !== $plugin[ $key ] ) {
		$warnings[] = sprintf( '"%s" differs: readme.txt %s, plugin header %s.', $key, $read[ $key ], $plugin[ $key ] );
	}
}
foreach ( array( 'wc requires at least', 'wc tested up to' ) as $key ) {
	if ( isset( $read[ $key ], $plugin[ $key ] ) && $read[ $key ] !== $plugin[ $key ] ) {
		$warnings[] = sprintf( '"%s" differs: readme.txt %s, plugin header %s.', $key, $read[ $key ], $plugin[ $key ] );
	}
}

// QIT Validation: HPOS must be declared compatible (declaring it incompatible is an error), and
// the Cart/Checkout blocks should be declared compatible (missing/incompatible is a warning).
$code = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	$rel = substr( $f->getPathname(), strlen( $root ) + 1 );
	if ( 'php' === $f->getExtension() && ! preg_match( '#^(vendor|node_modules|e2e-tests|tests|bin)/#', $rel ) ) {
		$code .= file_get_contents( $f->getPathname() );
	}
}
$declares = function ( $feature, $compatible ) use ( $code ) {
	return 1 === preg_match( "/declare_compatibility\(\s*'" . preg_quote( $feature, '/' ) . "'\s*,[^,]+,\s*" . ( $compatible ? 'true' : 'false' ) . '\s*\)/', $code );
};
if ( $declares( 'custom_order_tables', false ) ) {
	$errors[] = 'The plugin declares itself incompatible with HPOS (custom_order_tables).';
} elseif ( ! $declares( 'custom_order_tables', true ) ) {
	$warnings[] = 'No HPOS (custom_order_tables) compatibility declaration found.';
}
if ( ! $declares( 'cart_checkout_blocks', true ) ) {
	$warnings[] = 'No Cart/Checkout blocks (cart_checkout_blocks) compatibility declaration found.';
}

foreach ( $warnings as $w ) {
	echo "WARNING  $w\n";
}
foreach ( $errors as $e ) {
	echo "ERROR    $e\n";
}
printf( "Validation: %d error(s), %d warning(s).\n", count( $errors ), count( $warnings ) );
exit( empty( $errors ) ? 0 : 1 );
