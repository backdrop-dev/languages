<?php
/**
 * Tests for the WP-free helper logic in src/functions-helpers.php.
 *
 * The helpers depend on a few WordPress functions, so this script stubs
 * them (locale, RTL flag, sanitize_key(), apply_filters()) and then
 * exercises language(), region() and hierarchy() directly.
 *
 * Run: php tests/run.php
 * Exits 0 when every case passes, 1 otherwise.
 */

use function Backdrop\Languages\language;
use function Backdrop\Languages\region;
use function Backdrop\Languages\hierarchy;

// --- WordPress function stubs (global namespace). -------------------------

$GLOBALS['__test_locale'] = 'en_US';
$GLOBALS['__test_rtl']    = false;

function is_admin() {
    return false;
}

function get_locale() {
    return $GLOBALS['__test_locale'];
}

function get_user_locale() {
    return $GLOBALS['__test_locale'];
}

function sanitize_key( $key ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
}

function apply_filters( $tag, $value ) {
    return $value;
}

function is_rtl() {
    return ! empty( $GLOBALS['__test_rtl'] );
}

// --- Load the code under test. --------------------------------------------

require_once __DIR__ . '/../src/functions-helpers.php';

// --- Assertions. ----------------------------------------------------------

$failures = 0;

function check( $label, $actual, $expected ) {
    global $failures;

    if ( $actual === $expected ) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
    echo '  expected: ' . var_export( $expected, true ) . "\n";
    echo '  actual:   ' . var_export( $actual, true ) . "\n";
}

// language(): strips the region from the locale.
check( "language('en_US') is 'en'", language( 'en_US' ), 'en' );
check( "language('en') is 'en'", language( 'en' ), 'en' );
check( "language('pt_BR') is 'pt'", language( 'pt_BR' ), 'pt' );
check( "language() uses the current locale ('en_US')", language(), 'en' );

// region(): strips the language from the locale, '' when there is no region.
check( "region('en_US') is 'us'", region( 'en_US' ), 'us' );
check( "region('en') is '' (no region)", region( 'en' ), '' );
check( "region('pt_BR') is 'br'", region( 'pt_BR' ), 'br' );
check( "region() uses the current locale ('en_US')", region(), 'us' );

// hierarchy(): locale, region, language, text direction.
$GLOBALS['__test_locale'] = 'en_US';
$GLOBALS['__test_rtl']    = false;
check(
    "hierarchy() for 'en_US' is ['en-us', 'us', 'en', 'ltr']",
    hierarchy(),
    [ 'en-us', 'us', 'en', 'ltr' ]
);

$GLOBALS['__test_locale'] = 'en';
check(
    "hierarchy() for 'en' is ['en', 'ltr'] (no region added)",
    hierarchy(),
    [ 'en', 'ltr' ]
);

$GLOBALS['__test_locale'] = 'en_US';
$GLOBALS['__test_rtl']    = true;
check(
    "hierarchy() for 'en_US' RTL is ['en-us', 'us', 'en', 'rtl']",
    hierarchy(),
    [ 'en-us', 'us', 'en', 'rtl' ]
);

// --- Summary. --------------------------------------------------------------

if ( $failures ) {
    echo "\n{$failures} test(s) failed.\n";
    exit( 1 );
}

echo "\nAll tests passed.\n";
exit( 0 );
