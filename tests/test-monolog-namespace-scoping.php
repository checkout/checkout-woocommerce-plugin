<?php
/**
 * Regression test for GH issue #27 — Monolog namespace collision.
 *
 * Background:
 *   The plugin bundles Monolog v2 (pulled in transitively via checkout/checkout-sdk-php)
 *   under the plain `Monolog\` namespace. If a merchant's own project also loads Monolog
 *   (any version, but v3 specifically was reported), PHP fatals with
 *   "Cannot declare class Monolog\Logger, because the name is already in use".
 *
 * Fix:
 *   Strauss (configured in composer.json under `extra.strauss`, installed as a .phar via
 *   the `prefix-namespaces` composer script — see composer.json) rewrites the bundled
 *   Monolog classes in place, inside the normal `vendor/` folder, under the new
 *   `CheckoutComWC\Vendor\Monolog\...` namespace — there is no separate "vendor-prefixed"
 *   folder to manage; `vendor/` ships exactly as it always did. The Checkout SDK's own
 *   `Checkout\...` namespace is excluded from being *renamed* (the plugin's own code
 *   references it directly in several files), but the SDK's one internal
 *   `use Monolog\...` reference (AbstractCheckoutSdkBuilder.php) must still get rewritten
 *   to point at the new scoped Monolog location — otherwise it breaks the moment the
 *   original (unscoped) Monolog class no longer exists.
 *
 * This script does NOT require WordPress — it only exercises Composer's autoloader, so it
 * can be run standalone from a plain PHP CLI (e.g. via Local's site shell, or WP-CLI):
 *
 *   php tests/test-monolog-namespace-scoping.php
 *   wp eval-file tests/test-monolog-namespace-scoping.php
 *
 * Before running, make sure dependencies have been installed and scoped:
 *
 *   composer install
 *   (Strauss runs automatically via the post-install-cmd hook. To re-run scoping
 *   without a full reinstall: composer run prefix-namespaces)
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- standalone CLI script, no WP bootstrap.

$plugin_root = dirname( __DIR__ );
$results     = array();
$failures    = array();

/**
 * Record a check result.
 *
 * @param string $label     Human readable description of what's being checked.
 * @param bool   $condition Whether the check passed.
 * @param string $detail    Extra context to show if the check failed.
 */
function cko_test_check( &$results, &$failures, $label, $condition, $detail = '' ) {
	$results[] = array( $label, $condition, $detail );
	if ( ! $condition ) {
		$failures[] = $label;
	}
}

if ( ! file_exists( $plugin_root . '/vendor/autoload.php' ) ) {
	fwrite( STDERR, "vendor/autoload.php not found. Run 'composer install' first.\n" );
	exit( 1 );
}

require_once $plugin_root . '/vendor/autoload.php';

// 1. The Checkout SDK's own namespace must be completely untouched — the plugin's own
//    code (11 files) references `Checkout\...` classes directly, so these must resolve
//    exactly as before the scoping change.
cko_test_check(
	$results,
	$failures,
	'Checkout\\CheckoutSdk is loadable (SDK namespace untouched by scoping)',
	class_exists( 'Checkout\\CheckoutSdk' )
);
cko_test_check(
	$results,
	$failures,
	'Checkout\\CheckoutApiException is loadable (SDK namespace untouched by scoping)',
	class_exists( 'Checkout\\CheckoutApiException' )
);

// 2. Monolog must now live under our scoped/prefixed namespace.
cko_test_check(
	$results,
	$failures,
	'Scoped Monolog\\Logger class exists (CheckoutComWC\\Vendor\\Monolog\\Logger)',
	class_exists( 'CheckoutComWC\\Vendor\\Monolog\\Logger' )
);

// 3. The SDK's one internal reference to Monolog must have been rewritten to the scoped
//    namespace (Strauss renames the Monolog classes in place inside vendor/, so this file
//    would otherwise be left pointing at a class that no longer exists).
$sdk_builder_file = $plugin_root . '/vendor/checkout/checkout-sdk-php/lib/Checkout/AbstractCheckoutSdkBuilder.php';
$sdk_builder_src   = file_exists( $sdk_builder_file ) ? file_get_contents( $sdk_builder_file ) : '';
cko_test_check(
	$results,
	$failures,
	'SDK\'s AbstractCheckoutSdkBuilder.php no longer references the unscoped Monolog\\ namespace',
	'' !== $sdk_builder_src && ! preg_match( '/use\s+Monolog\\\\(Logger|Handler)/', $sdk_builder_src ),
	$sdk_builder_file . ( $sdk_builder_src ? '' : ' (file not found)' )
);

// 4. The actual regression from the merchant report: simulate a site that already has
//    its own Monolog (v3, or any version) loaded globally under the plain `Monolog\`
//    namespace, then load our plugin's autoloader in the SAME process, and confirm PHP
//    does not fatal with "Cannot redeclare class". This is run as an isolated child
//    process (not an in-process try/catch) because a class redeclaration is a compile-time
//    fatal that can't be reliably caught inline.
$isolated_script = <<<'PHP'
<?php
// Simulate a merchant project that already loaded its own Monolog v3.
namespace Monolog {
	class Logger {
		public function __construct( ...$args ) {}
	}
}
namespace {
	// If our vendor still declared Monolog\Logger under the plain namespace, PHP would
	// have fataled while parsing/loading the line below, and we'd never reach the echo.
	require '%s/vendor/autoload.php';
	echo "NO_COLLISION\n";
	echo class_exists( 'CheckoutComWC\\Vendor\\Monolog\\Logger' ) ? "SCOPED_OK\n" : "SCOPED_MISSING\n";
}
PHP;

$isolated_script = sprintf( $isolated_script, addslashes( $plugin_root ) );
$tmp_file        = tempnam( sys_get_temp_dir(), 'cko_monolog_test_' ) . '.php';
file_put_contents( $tmp_file, $isolated_script );

$php_binary = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
$output     = array();
$exit_code  = 0;
exec( escapeshellarg( $php_binary ) . ' ' . escapeshellarg( $tmp_file ) . ' 2>&1', $output, $exit_code );
unlink( $tmp_file );

$output_str = implode( "\n", $output );
cko_test_check(
	$results,
	$failures,
	'Loading the plugin autoloader alongside a merchant-defined Monolog\\Logger does not fatal',
	0 === $exit_code && false !== strpos( $output_str, 'NO_COLLISION' ),
	$output_str
);
cko_test_check(
	$results,
	$failures,
	'Scoped Monolog class still resolves inside that same isolated process',
	false !== strpos( $output_str, 'SCOPED_OK' ),
	$output_str
);

// --- Report ------------------------------------------------------------
echo "\nMonolog Namespace Scoping — Regression Test (GH #27)\n";
echo str_repeat( '=', 70 ) . "\n";
foreach ( $results as $result ) {
	list( $label, $pass, $detail ) = $result;
	echo ( $pass ? '[PASS] ' : '[FAIL] ' ) . $label . "\n";
	if ( ! $pass && $detail ) {
		echo '        -> ' . str_replace( "\n", "\n           ", $detail ) . "\n";
	}
}
echo str_repeat( '=', 70 ) . "\n";

if ( $failures ) {
	echo count( $failures ) . " check(s) failed.\n";
	exit( 1 );
}

echo "All checks passed — Monolog is safely scoped and no longer collides with a merchant's own copy.\n";
exit( 0 );
