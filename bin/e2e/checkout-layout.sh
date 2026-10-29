#!/usr/bin/env bash
# Switch the store's checkout page between the classic shortcode and the Checkout block.
# The plugin decides classic vs Blocks from the configured checkout page, so the suite
# switches the page content rather than using two pages.
#
# Usage: bin/e2e/checkout-layout.sh classic|blocks
set -euo pipefail

cd "$(dirname "$0")/../.."
WP_ENV="${WP_ENV:-e2e-tests/node_modules/.bin/wp-env}"

case "${1:-}" in
	classic | blocks) ;;
	*) echo "Usage: $0 classic|blocks" >&2; exit 1 ;;
esac

"$WP_ENV" run cli wp eval "
\$id = (int) get_option( 'woocommerce_checkout_page_id' );
\$content = 'classic' === '$1'
	? '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
	: get_option( 'cko_e2e_blocks_checkout_content' );
if ( ! \$id || ! \$content ) { fwrite( STDERR, 'Checkout page or saved block content missing - run bin/e2e/seed.sh first.' ); exit( 1 ); }
wp_update_post( array( 'ID' => \$id, 'post_content' => \$content ) );
echo 'Checkout layout: $1' . PHP_EOL;
"
