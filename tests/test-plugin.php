<?php

define( 'ABSPATH', __DIR__ );

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function apply_filters( $tag, $value ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }

require dirname( __DIR__ ) . '/ko-truncate-divi-blog-titles.php';

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

assert_same(
	'PTI Art Spotlight – July 2026',
	KO_Truncate_Divi_Blog_Titles::truncate_text( 'PTI Art Spotlight – July 2026', 30 ),
	'A title within the limit should remain unchanged.'
);

assert_same(
	'Five World Cup Cities...',
	KO_Truncate_Divi_Blog_Titles::truncate_text( 'Five World Cup Cities, Nonstop from PTI', 30 ),
	'A long title should truncate at a clean word boundary.'
);

assert_same(
	'Passengers, Cargo Flights...',
	KO_Truncate_Divi_Blog_Titles::truncate_text( 'Passengers, Cargo Flights, and Seats at PTI – June 2026', 30 ),
	'Trailing punctuation should be removed before the ellipsis.'
);

$target_html = '<div class="et_pb_blog ko-truncate-divi-blog-titles-30"><article><h5 class="entry-title"><a href="/post/">Five World Cup Cities, Nonstop from PTI</a></h5></article></div>';
$processed = KO_Truncate_Divi_Blog_Titles::truncate_markup( $target_html );

if ( false === strpos( $processed, '>Five World Cup Cities...</a>' ) ) {
	fwrite( STDERR, "Rendered Blog title was not truncated.\n" . $processed . "\n" );
	exit( 1 );
}
if ( false === strpos( $processed, 'aria-label="Five World Cup Cities, Nonstop from PTI"' ) ) {
	fwrite( STDERR, "Full title was not preserved in aria-label.\n" . $processed . "\n" );
	exit( 1 );
}

$untargeted_html = '<div class="et_pb_blog"><h5 class="entry-title"><a href="/post/">Five World Cup Cities, Nonstop from PTI</a></h5></div>';
assert_same(
	$untargeted_html,
	KO_Truncate_Divi_Blog_Titles::truncate_markup( $untargeted_html ),
	'Blog modules without the activation class must remain untouched.'
);

echo "All KO: Truncate Divi Blog Titles tests passed.\n";
