<?php
/**
 * Loads and registers the fake providers used in tests.
 *
 * Each fake returns canned data and never makes a network request. Tests choose a fake by
 * setting the provider option (e.g. `sloc_geo_provider`) to `sloc_test`, and change what it
 * returns through its public static properties.
 *
 * @package Simple_Location
 */

require_once __DIR__ . '/class-geo-provider-sloc-test.php';
require_once __DIR__ . '/class-geo-provider-sloc-test-fallback.php';
require_once __DIR__ . '/class-map-provider-sloc-test.php';
require_once __DIR__ . '/class-weather-provider-sloc-test.php';
require_once __DIR__ . '/class-weather-provider-sloc-test-fallback.php';
require_once __DIR__ . '/class-elevation-provider-sloc-test.php';
require_once __DIR__ . '/class-elevation-provider-sloc-test-fallback.php';
require_once __DIR__ . '/class-venue-provider-sloc-test.php';

register_sloc_provider( new Geo_Provider_Sloc_Test() );
register_sloc_provider( new Geo_Provider_Sloc_Test_Fallback() );
register_sloc_provider( new Map_Provider_Sloc_Test() );
register_sloc_provider( new Weather_Provider_Sloc_Test() );
register_sloc_provider( new Weather_Provider_Sloc_Test_Fallback() );
register_sloc_provider( new Elevation_Provider_Sloc_Test() );
register_sloc_provider( new Elevation_Provider_Sloc_Test_Fallback() );
register_sloc_provider( new Venue_Provider_Sloc_Test() );
