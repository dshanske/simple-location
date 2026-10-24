
## class-elevation-provider.php

- [`sloc_elevation_provider_defaults`](sloc_elevation_provider_defaults) Filters the default arguments of elevation providers.

## class-geo-base.php

- [`simple_location_sidebox`](simple_location_sidebox) Fires after the location metabox is shown, so other plugins can add to it.
- [`sloc_post_types`](sloc_post_types) Filters the post types that get the location metabox and admin column.

## class-geo-data.php

- [`simple_location_display_defaults`](simple_location_display_defaults) Filters the default arguments for displaying a location.
- [`sloc_geo_taxonomies`](sloc_geo_taxonomies) Filters the taxonomies whose terms get geo meta registered.
- [`sloc_show_hidden_location`](sloc_show_hidden_location) Filters whether a hidden location is shown to the current user.

## class-geo-provider.php

- [`location_display_name`](location_display_name) This filter is documented in includes/class-geo-provider.php
- [`sloc_display_region_codes`](sloc_display_region_codes) Filters the regions whose code is shown after a locality of the same name.
- [`sloc_geo_provider_defaults`](sloc_geo_provider_defaults) This filter is documented in includes/class-geo-provider.php

## class-loc-config.php

- [`default_sloc_aspect_ratios`](default_sloc_aspect_ratios) Filters the aspect ratios offered for maps.

## class-location-provider.php

- [`sloc_location_provider_defaults`](sloc_location_provider_defaults) Filters the default arguments of location providers.

## class-map-provider-staticmap.php

- [`sloc_staticmap_max_size`](sloc_staticmap_max_size) Filters the largest map size requested from the custom static map server.

## class-map-provider.php

- [`sloc_map_alt`](sloc_map_alt) Filters the alternative text of a static map image.
- [`sloc_map_language`](sloc_map_language) Filters the language of map labels, for map providers that can translate them.

## class-post-venue.php

- [`pre_at_venue`](pre_at_venue) Short-circuits the checking for a venue if it is not stored as normal.
- [`pre_nearby_venue`](pre_nearby_venue) Short-circuits the checking for a venue if it is not stored as normal.

## class-rest-geo.php

- [~~`sloc_local_api_protected`~~](sloc_local_api_protected) **DEPRECATED** Use `sloc_local_api_public` instead
- [`sloc_local_api_public`](sloc_local_api_public) Filters whether the local airport and timezone lookup endpoints are open to everyone.

## class-sloc-map-cache.php

- [`sloc_map_cache_directory`](sloc_map_cache_directory) Filters where stored map images are kept.
- [`sloc_map_cache_max_age`](sloc_map_cache_max_age) Filters how many days a stored map image is used before it is fetched again from the provider.

## class-sloc-provider-chain.php

- [`sloc_provider_chain`](sloc_provider_chain) Filters the providers a lookup tries, in order.

## class-sloc-provider-errors.php

- [`sloc_provider_error`](sloc_provider_error) Fires when a provider starts failing with an error an administrator can fix.
- [`sloc_provider_error_actionable`](sloc_provider_error_actionable) Filters whether a provider error is recorded for administrators to fix.
- [`sloc_provider_error_message`](sloc_provider_error_message) Filters the one-line message about a provider error, or the provider's recovery.
- [`sloc_provider_error_resolved`](sloc_provider_error_resolved) Fires when a provider with a recorded error answers again.

## class-venue-provider.php

- [`sloc_venue_provider_defaults`](sloc_venue_provider_defaults) Filters the default arguments of venue providers.

## class-weather-provider.php

- [`sloc_weather_credit`](sloc_weather_credit) Filters the credit shown with a weather provider's weather.
- [`sloc_weather_provider_defaults`](sloc_weather_provider_defaults) Filters the default arguments of weather providers.
