# Provider test fixtures

Each file is the body of one HTTP response from a provider's API, used by the provider tests through
`Sloc_Http_Mock` (`tests/includes/trait-sloc-http-mock.php`). Tests never call live APIs.

## Replacing a fixture with a real response

1. Make the request listed below. Use the same location or query, so the stable assertions still hold.
2. Remove anything secret from the saved body (API keys, account names, tokens). Most APIs don't echo
   keys, but some echo the query.
3. Save it over the file with the same name, pretty-printed JSON, and update its **Status** below to
   `Captured YYYY-MM-DD`.
4. Run `./vendor/bin/phpunit --filter GeoProvidersTest`.

The tests check stable facts for every provider: city (Philadelphia), region (Pennsylvania / `PA`),
country (`US`), a `191xx` ZIP code, a street containing "Kennedy" or "JFK", and the requested
coordinates. Vendor-specific details (names, neighborhoods, URLs, plus codes, returned coordinates) are
compared against the fixture file itself, so a real capture passes as long as the parser handles it. If
a real capture fails, either the vendor's format differs from what the plugin expects (a bug to file)
or the place really has different data (adjust the test).

## Location

Philadelphia City Hall: latitude `39.9524`, longitude `-75.1636`; forward-geocoding query
`Philadelphia City Hall` (Nominatim) or `City Hall` / `City Hall, Philadelphia` (others, as noted).

## Geo providers (`geo/`)

| File | Request | Status |
|---|---|---|
| `nominatim-reverse.json` | `GET https://nominatim.openstreetmap.org/reverse?format=jsonv2&extratags=1&addressdetails=1&namedetails=1&lat=39.9524&lon=-75.1636&zoom=18` | Hand-built from docs |
| `nominatim-search.json` | `GET https://nominatim.openstreetmap.org/search?q=Philadelphia+City+Hall&format=jsonv2&extratags=1&addressdetails=1&namedetails=1` | Hand-built from docs |
| `nominatim-error.json` | Reverse geocoding a point with no address (e.g. open ocean) | Hand-built from docs |
| `locationiq-reverse.json` | `GET https://us1.locationiq.com/v1/reverse.php?key=…&format=json&lat=39.9524&lon=-75.1636&statecode=1&extratags=1&addressdetails=1` | Hand-built from docs |
| `google-reverse.json` | `GET https://maps.googleapis.com/maps/api/geocode/json?latlng=39.9524,-75.1636&location_type=ROOFTOP\|RANGE_INTERPOLATED&key=…` | Hand-built from docs |
| `google-denied.json` | The same request with an invalid key | Hand-built from docs |
| `bing-locations.json` | `GET https://dev.virtualearth.net/REST/v1/Locations/39.9524,-75.1636?incl=ciso2&inclnb=1&key=…` (also used for `?q=City Hall, Philadelphia`) | Hand-built from docs |
| `bing-invalid-key.json` | The same request with an invalid key | Hand-built from docs |
| `bing-empty.json` | A valid key with no `resourceSets` in the body | Hand-built |
| `mapquest-reverse.json` | `GET https://www.mapquestapi.com/geocoding/v1/reverse?location=39.9524,-75.1636&includeRoadMetadata=true&key=…` (also used for `/address?location=City Hall`) | Hand-built from docs |
| `mapquest-no-results.json` | A key that isn't authorized for geocoding | Hand-built |
| `mapquest-no-locations.json` | A result with no `locations` | Hand-built |
| `pelias-reverse.json` | `GET {Pelias URL}/reverse/?point.lat=39.9524&point.lon=-75.1636&size=1&api_key=…` | Hand-built from docs |
| `pelias-search.json` | `GET {Pelias URL}/search/?text=City Hall&api_key=…` | Hand-built from docs |
| `openroute-reverse.json` | `GET https://api.openrouteservice.org/geocode/reverse?point.lat=39.9524&point.lon=-75.1636&api_key=…` | Hand-built from docs |
| `openroute-search.json` | `GET https://api.openrouteservice.org/geocode/search?text=City Hall&api_key=…` | Hand-built from docs |
| `geonames-error.json` | `GET https://secure.geonames.org/search?q=Philadelphia&type=json&style=FULL&username=…` with an account that isn't enabled | Hand-built from docs |

HERE (#338), Geoapify (#339) and GeoNames parsing (#321, #343) have no response fixtures yet; add them
with the fixes.
