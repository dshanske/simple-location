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
| `openmeteo-current.json` | `GET https://api.open-meteo.com/v1/forecast?latitude=39.9524&longitude=-75.1636&wind_speed_unit=ms&timeformat=unixtime&current={Weather_Provider_OpenMeteo::VARIABLES}` | Captured 2026-10-22 |
| `openmeteo-archive.json` | `GET https://archive-api.open-meteo.com/v1/archive?latitude=39.9524&longitude=-75.1636&wind_speed_unit=ms&timeformat=unixtime&hourly={VARIABLES}&start_date=2025-07-01&end_date=2025-07-01` | Captured 2026-10-22 |
| `openmeteo-error.json` | `GET https://api.open-meteo.com/v1/forecast?latitude=39.9524&longitude=-75.1636&current=nonsense` (HTTP 400) | Captured 2026-10-22 |
| `metno-complete.json` | `GET https://api.met.no/weatherapi/locationforecast/2.0/complete?lat=39.9524&lon=-75.1636` with an identifying `User-Agent` (`TIME0` and `TIME1` are set by the test to the nearest hour and the one after) | Hand-built from docs |
| `here-observation.json` | `GET https://weather.hereapi.com/v3/report?apiKey=…&products=observation&location=39.9524,-75.1636&units=metric&oneObservation=true` | Hand-built from docs |
| `weatherbit-current.json` | `GET https://api.weatherbit.io/v2.0/current?key=…&lat=39.9524&lon=-75.1636&units=M` | Hand-built from docs |
| `weatherstack-current.json` | `GET https://api.weatherstack.com/current?access_key=…&query=39.9524,-75.1636&units=m` (free plans: `http://`) | Hand-built from docs |
| `visualcrossing-current.json` | `GET https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/39.9524,-75.1636?key=…&unitGroup=metric&lang=id` | Hand-built from docs |
| `visualcrossing-timeline.json` | The same with `/{timestamp}` for 2025-07-01 14:00 America/New_York | Hand-built from docs |
| `aeris-observations.json` | `GET https://data.api.xweather.com/observations/closest?client_id=…&client_secret=…&p=39.9524,-75.1636&filter=allstations` (also used for `…&p=KPHL`) | Hand-built from docs |
| `nws-observation.json` | `GET https://api.weather.gov/stations/KPHL/observations/latest` | Hand-built from docs |
| `nws-station.json` | `GET https://api.weather.gov/stations/KPHL` | Hand-built from docs |
| `meteostat-hourly.json` | `GET https://meteostat.p.rapidapi.com/stations/hourly?station=72408&start=2025-07-01&end=2025-07-02` with `x-rapidapi-key` | Hand-built from docs |
| `meteostat-stations.csv` | Not a response: a hand-made station list in the format of `data/meteostat-stations.csv` (built by `bin/update-data.php`), with stations near City Hall that have hourly data for different years, one without hourly data, and one more than 100 km away | Hand-built |
| `station-custom.json` | A custom station URL (the plugin's own format; `timestamp` is set by the test) | Hand-built from the plugin's parser |
| `station-current-conditions.json` | A custom station URL in the current conditions format, version 1, as weewx-current-json writes it (`time` is set by the test) | Hand-built from weewx-current-json's output |

Weatherstack (API-5), HERE weather (API-4) and the location lookups that sort
stations (NWS, Meteostat, custom station) have no fixtures yet.

## Elevation providers (`elevation/`)

Each test checks that the elevation the plugin returns is the one in the fixture, rounded to two places.

| File | Request | Status |
|---|---|---|
| `geonames-srtm1.json` | `GET https://secure.geonames.org/srtm1?username=…&lat=39.9524&lng=-75.1636` (errors reuse `geo/geonames-error.json`) | Hand-built from docs |
| `google-elevation.json` | `GET https://maps.googleapis.com/maps/api/elevation/json?locations=39.9524,-75.1636&key=…` (errors reuse `geo/google-denied.json`) | Hand-built from docs |
| `openroute-point.json` | `GET https://api.openrouteservice.org/elevation/point?api_key=…&format_out=point&geometry=-75.1636,39.9524` | Hand-built from docs |
| `opentopodata-mapzen.json` | `GET https://api.opentopodata.org/v1/mapzen?locations=39.9524,-75.1636` | Captured 2026-10-22 |
| `opentopodata-no-data.json` | `GET https://api.opentopodata.org/v1/srtm30m?locations=70,-75.1636` (north of SRTM's coverage) | Captured 2026-10-22 |
| `opentopodata-invalid-dataset.json` | `GET https://api.opentopodata.org/v1/nosuchdataset?locations=39.9524,-75.1636` (HTTP 400) | Captured 2026-10-22 |

## Venue providers (`venue/`)

The venue tests check the same stable address facts as the geo tests for every venue, plus the
timezone. Nominatim venues reuse `geo/nominatim-reverse.json`, since it is the same request.

| File | Request | Status |
|---|---|---|
| `google-places-nearby.json` | `POST https://places.googleapis.com/v1/places:searchNearby` with `X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.addressComponents,places.location,places.types,places.primaryType,places.plusCode,places.viewport,places.googleMapsUri` and the body `{"locationRestriction":{"circle":{"center":{"latitude":39.9524,"longitude":-75.1636},"radius":250}},"maxResultCount":10,"rankPreference":"DISTANCE","languageCode":"en-US"}` | Hand-built from docs |
| `overpass-nearby.json` | `GET https://overpass-api.de/api/interpreter?data=…` with the query from `Venue_Provider_Overpass::query()` around City Hall | Hand-built from docs |
| `geoapify-places.json` | `GET https://api.geoapify.com/v2/places?categories=…&filter=circle:-75.1636,39.9524,250&bias=proximity:-75.1636,39.9524&limit=10&lang=en&apiKey=…` | Hand-built from docs |
| `locationiq-nearby.json` | `GET https://us1.locationiq.com/v1/nearby?key=…&lat=39.9524&lon=-75.1636` | Hand-built from docs |

## Map providers (`map/`)

Map providers only build image and link URLs, so their tests need no fixtures except Mapbox's list of
a user's styles and the custom static map server's query API. The custom server's fixtures were captured from
a local Static Maps API 1.0.0 with its shipped configuration (`php -S 127.0.0.1:8765 -t public`); the provider
list is shortened to a few providers and styles.

| File | Request | Status |
|---|---|---|
| `mapbox-styles.json` | `GET https://api.mapbox.com/styles/v1/{user}?access_token=…&limit=50` | Hand-built from docs |
| `staticmap-api.json` | `GET {staticmap_url}/api/v1/` | Captured 2026-10-22 |
| `staticmap-providers.json` | `GET {staticmap_url}/api/v1/providers` | Captured 2026-10-22, shortened |
| `staticmap-overlays.json` | `GET {staticmap_url}/api/v1/overlays` | Captured 2026-10-22, shortened |
| `staticmap-attribution.json` | `GET {staticmap_url}/api/v1/attribution?provider=esri&style=topo&overlay=railways,seamarks` | Captured 2026-10-22 |
| `staticmap-plan.json` | `GET {staticmap_url}/api/v1/plan` with the archive map of the tests' two Philadelphia points (600×400, `esri/topo`) | Captured 2026-10-22 |

## Location providers (`location/`)

Compass, Dawarich and GeoPulse are self-hosted, so the requests go to the user's own server (the tests
use `https://compass.example.com`, `https://dawarich.example.com` and `https://geopulse.example.com`). `compass-last.json` is a GeoJSON point at Philadelphia City
Hall; `compass-query.json` is a GeoJSON line starting there. The tests compare coordinates, altitude,
speed, heading and activity against the fixture itself.

| File | Request | Status |
|---|---|---|
| `compass-last.json` | `GET {compass_url}/api/last/?token=…` (optionally `&before=YYYY-MM-DDTHH:MM:SS` in UTC) | Hand-built from docs |
| `compass-query.json` | `GET {compass_url}/api/query/?token=…&format=linestring&start=…&end=…&tz=…` | Hand-built from docs |
| `dawarich-points.json` | `GET {dawarich_url}/api/v1/points?api_key=…&order=desc&per_page=1` (optionally `&end_at=…Z`) | Hand-built from the Dawarich OpenAPI spec (`swagger/v1/swagger.yaml`) |
| `dawarich-track.json` | `GET {dawarich_url}/api/v1/points?api_key=…&start_at=…&end_at=…&order=asc&per_page=1000&page=1` | Hand-built from the Dawarich OpenAPI spec |
| `geopulse-points.json` | `GET {geopulse_url}/api/v1/gps/points?size=1&sortBy=timestamp&sortDirection=desc` (optionally `&to=…Z`), with `Authorization: Bearer …` | Hand-built from GeoPulse 2.1.0's `GpsPointResource`, `PageResponse` and `GpsPointDTO` |
| `geopulse-path.json` | `GET {geopulse_url}/api/v1/gps/points/path?from=…&to=…&simplify=true`, with `Authorization: Bearer …` | Hand-built from GeoPulse 2.1.0's `GpsPointPathDTO` |
