# Provider test fixtures

Each file is the body of one HTTP response from a provider's API, used by the provider tests through
`Sloc_Http_Mock` (`tests/includes/trait-sloc-http-mock.php`). Tests never call live APIs.

## Replacing a fixture with a real response

1. Make the request listed below. Use the same location or query, so the stable assertions still hold.
2. Remove anything secret from the saved body (API keys, account names, tokens). Most APIs don't echo
   keys, but some echo the query.
3. Save it over the file with the same name, pretty-printed JSON, and update its **Status** below to
   `Captured YYYY-MM-DD`.
4. Run `./vendor/bin/phpunit --filter GeoProvidersTest` (or `WeatherProvidersTest`, `ElevationProvidersTest`,
   `VenueProvidersTest`, `MapProvidersTest`, `LocationProvidersTest`).

The tests check stable facts for every provider: city (Philadelphia), region (Pennsylvania / `PA`),
country (`US`), a `191xx` ZIP code, a street containing "Kennedy" or "JFK", and the requested
coordinates. Vendor-specific details (names, neighborhoods, URLs, plus codes, returned coordinates) are
compared against the fixture file itself, so a real capture passes as long as the parser handles it. If
a real capture fails, either the vendor's format differs from what the plugin expects (a bug to file)
or the place really has different data (adjust the test).

Weather fixtures work the same way: each test reads the expected values from its fixture and checks
the plugin's keys and unit conversions (for example km/h to m/s, kilometers to meters, fractions to
percent).

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
| `geoapify-reverse.json` | `GET https://api.geoapify.com/v1/geocode/reverse/?apiKey=…&lat=39.9524&lon=-75.1636&type=building` | Hand-built from docs |
| `geoapify-search.json` | `GET https://api.geoapify.com/v1/geocode/search?text=City Hall, Philadelphia&apiKey=…` | Hand-built from docs |
| `geoapify-empty.json` | A Geoapify request that finds nothing (`features` is empty) | Hand-built from docs |
| `openroute-reverse.json` | `GET https://api.openrouteservice.org/geocode/reverse?point.lat=39.9524&point.lon=-75.1636&api_key=…` | Hand-built from docs |
| `openroute-search.json` | `GET https://api.openrouteservice.org/geocode/search?text=City Hall&api_key=…` | Hand-built from docs |
| `here-revgeocode.json` | `GET https://revgeocode.search.hereapi.com/v1/revgeocode?apiKey=…&at=39.9524,-75.1636&lang=en-US` (also used for `https://geocode.search.hereapi.com/v1/geocode?q=City Hall, Philadelphia`) | Hand-built from docs |
| `here-empty.json` | A HERE request that finds nothing (`items` is empty) | Hand-built from docs |
| `geonames-error.json` | `GET https://secure.geonames.org/search?q=Philadelphia&type=json&style=FULL&username=…` with an account that isn't enabled | Hand-built from docs |

GeoNames parsing (#321, #343) has no response fixtures yet; add them
with the fixes.

## Weather providers (`weather/`)

Current conditions are for Philadelphia (`39.9524,-75.1636`); station requests use the station shown.

| File | Request | Status |
|---|---|---|
| `openweathermap-onecall.json` | `GET https://api.openweathermap.org/data/3.0/onecall?appid=…&units=metric&lat=39.9524&lon=-75.1636` | Hand-built from docs |
| `pirateweather-forecast.json` | `GET https://api.pirateweather.net/forecast/{key}/39.9524,-75.1636?units=si&exclude=minutely,hourly,daily,alerts,flags` | Hand-built from docs |
| `here-observation.json` | `GET https://weather.hereapi.com/v3/report?apiKey=…&products=observation&location=39.9524,-75.1636&units=metric&oneObservation=true` | Hand-built from docs |
| `weatherbit-current.json` | `GET https://api.weatherbit.io/v2.0/current?key=…&lat=39.9524&lon=-75.1636&units=M` | Hand-built from docs |
| `weatherstack-current.json` | `GET https://api.weatherstack.com/current?access_key=…&query=39.9524,-75.1636&units=m` (free plans: `http://`) | Hand-built from docs |
| `visualcrossing-current.json` | `GET https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/39.9524,-75.1636?key=…&unitGroup=metric&lang=id` | Hand-built from docs |
| `visualcrossing-timeline.json` | The same with `/{timestamp}` for 2025-07-01 14:00 America/New_York | Hand-built from docs |
| `aeris-observations.json` | `GET https://data.api.xweather.com/observations/closest?client_id=…&client_secret=…&p=39.9524,-75.1636&filter=allstations` (also used for `…&p=KPHL`) | Hand-built from docs |
| `nws-observation.json` | `GET https://api.weather.gov/stations/KPHL/observations/latest` | Hand-built from docs |
| `nws-station.json` | `GET https://api.weather.gov/stations/KPHL` | Hand-built from docs |
| `meteostat-hourly.json` | `GET https://meteostat.p.rapidapi.com/stations/hourly?station=72408&start=2025-07-01&end=2025-07-02` with `x-rapidapi-key` | Hand-built from docs |
| `station-custom.json` | A custom station URL (the plugin's own format; `timestamp` is set by the test) | Hand-built from the plugin's parser |

Weatherstack (API-5), HERE weather (API-4), Met Office (API-3) and the location lookups that sort
stations (NWS, Meteostat, custom station) have no fixtures yet.

## Elevation providers (`elevation/`)

Each test checks that the elevation the plugin returns is the one in the fixture, rounded to two places.

| File | Request | Status |
|---|---|---|
| `bing-elevation.json` | `GET http://dev.virtualearth.net/REST/v1/Elevation/List?points=39.9524,-75.1636&key=…` | Hand-built from docs |
| `geonames-srtm1.json` | `GET https://secure.geonames.org/srtm1?username=…&lat=39.9524&lng=-75.1636` (errors reuse `geo/geonames-error.json`) | Hand-built from docs |
| `google-elevation.json` | `GET https://maps.googleapis.com/maps/api/elevation/json?locations=39.9524,-75.1636&key=…` (errors reuse `geo/google-denied.json`) | Hand-built from docs |
| `openroute-point.json` | `GET https://api.openrouteservice.org/elevation/point?api_key=…&format_out=point&geometry=-75.1636,39.9524` | Hand-built from docs |

The MapQuest and OpenMapQuest elevation providers call shut-down APIs (API-2) and have no fixtures.

## Venue providers (`venue/`)

The venue tests check the same stable address facts as the geo tests for every venue, plus the
timezone. Nominatim venues reuse `geo/nominatim-reverse.json`, since it is the same request.

| File | Request | Status |
|---|---|---|
| `google-nearbysearch.json` | `GET https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=39.9524,-75.1636&radius=200&type=establishment&language=en-US&key=…` | Hand-built from docs |
| `locationiq-nearby.json` | `GET https://us1.locationiq.com/v1/nearby?key=…&lat=39.9524&lon=-75.1636` | Hand-built from docs |

## Map providers (`map/`)

Map providers only build image and link URLs, so their tests need no fixtures except Mapbox's list of
a user's styles.

| File | Request | Status |
|---|---|---|
| `mapbox-styles.json` | `GET https://api.mapbox.com/styles/v1/{user}?access_token=…&limit=50` | Hand-built from docs |

## Location providers (`location/`)

Compass is a self-hosted location tracker, so the requests go to the user's own Compass URL (the tests
use `https://compass.example.com`). `compass-last.json` is a GeoJSON point at Philadelphia City
Hall; `compass-query.json` is a GeoJSON line starting there. The tests compare coordinates, altitude,
speed, heading and activity against the fixture itself.

| File | Request | Status |
|---|---|---|
| `compass-last.json` | `GET {compass_url}/api/last/?token=…` (optionally `&before=YYYY-MM-DDTHH:MM:SS` in UTC) | Hand-built from docs |
| `compass-query.json` | `GET {compass_url}/api/query/?token=…&format=linestring&start=…&end=…&tz=…` | Hand-built from docs |
