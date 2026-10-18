# Changelog

Releases before the ones listed in readme.txt. Newer releases are in [readme.txt](readme.txt).

## 5.0.23 ( 2024-02-02 )

* Fix issues with media metadata saving
* Save location taxonomy for images
* Fix unset variables in HERE provider
* Refresh data files(airports, geo subdivisions, etc)

## 5.0.22 ( 2023-12-25 )

* Fix issue where dropdown filter was not working when value set to no filter.
* Limit venues when you change location pulldown.

## 5.0.21 ( 2023-12-23 )

* Fix fatal error when no venue
* Read OpenMapQuest as a Geo Provider as no longer available
* Add dates to map archive view
* If missing location but post has a venue, set the location of the post to the venue's location

## 5.0.20 ( 2023-12-17 )

* Dropdown for Venue will now on new edit load only, limited to venues at that location
* Temporarily disble sunset adjustment by elevation due to PHP8 deprecation of date_sunrise/sunset. Need to write wrapper in future
* Dropdown filter for location taxonomy added
* Improve some strings

## 5.0.19 ( 2023-12-12 )

* Pulldown will set option by default
* Fix micropub integration to add additional check

## 5.0.18 ( 2023-11-11 )

* Update dependencies
* Support Widgets in Legacy Widget Block
* Fix Venue Being Set to 0
* Switch venue to a pulldown. Would like to link it to location at some point, so it only shows venues in that region.

## 5.0.17 ( 2023-10-02 )

* Refresh dependencies
* Bump minimum PHP to 7.0

## 5.0.16 ( 2023-07-15 )

* Fix issue with missing AQI icon

## 5.0.15 ( 2023-07-01 )

* Fix conflict with WooCommerce Subscription plugin

## 5.0.14 ( 2023-06-03 )

* Fix undeclared variable
* Fix unclosed string

## 5.0.13 ( 2023-05-02 )

* Fix issue with geoapify and bing map providers due calling old bounding box function
* Change signature of display name function for taxonomy to allow for multiple arguments for display
* Fix issue with google marker display
* Check for ExifVersion before using it

## 5.0.12 ( 2023-04-30 )

* Remove Dark Sky as API is turned off
* Fix several issues where errors occurred because a non-numeric value was passed to a function that required a numeric one
* Check for existence of array properties before using them

## 5.0.11 ( 2023-04-14 )

* Add code to store webmention location in comments previously handled by Semantic Linkbacks
* Fix issue with typing in PHP8.1 causing errors
* Fix for no set map provider

## 5.0.10 (2023-03-13 )

* Fix call to deprecated function

## 5.0.9 ( 2023-03-12 )

* Fix issue with filter of location archives

## 5.0.8 ( 2023-03-08 )

* Fix EXIF data issue
* Introduce attachement date function to get created time and use it to show this in the attachment editor.

## 5.0.7 ( 2023-01-10 )


* Minor bug fixes
* Add function to return child venues.

## 5.0.6 ( 2023-01-02 )

* Add functions for parent venues and to extract media from posts made at venue
* Check error on return

## 5.0.5 ( 2022-12-27 )

* Add count of posts attached to venues to list table
* Allow importing an address from associated posts to a venue
* Improve list table display

## 5.0.4 ( 2022-11-25 )

* Add uid when updating the checkin property of a post
* Add location to post slug for venues to assist in disambiguation
* Add location to venue when displayed to assist in disambiguation

## 5.0.3 ( 2022-11-13 )

* Add support to lookup location to update older posts with venue creation
* Update map archive to call get_location over custom code. Add arguments to get_location to allow better customization for the map.
* Set map zoom level to building level if Micropub post is a checkin
* Allow for passing thorugh a zoom when creating a venue, otherwise building level default

## 5.0.2 ( 2022-11-12 )

* Add function to get posts for a specific venue
* Add function to retrieve venue type

## 5.0.1 ( 2022-11-06 )

* Fix issue raising warning during block editor
* Fix strict typing on zenith

## 5.0.0 ( 2022-11-05 )

* Remove Yandex as a supported provider.
* Add URL and documentation strings to all providers
* Refactor reused code in providers into traits.
* Separate Elevation into a separate provider category. The Elevation provider is called by the Geo Provider when needed.
* Add additional error checks
* Introduce GeoApify for Geolocation
* Split off EXIF data enhancement into a separate file
* Move several geo faunctions to global scope.
* Add a series of global functions to get/set geo and weather data which are also pluggable to different storage locations
* Separate weather and geo into two separate sets of functions
* Set the weather condition to a code instead of storing icons and summary. Retain summary and icon for backcompat
* Introduce Venue as a Custom Post Type
* Replace Zones with Private Venues
* Automatically create venues when checking in using Micrpub.
* Make default for displaying location on posts the venue name and link, then location taxonomy, then address field.

## 4.5.0 ( 2022-06-17 )

* Adjust queries
* Update dependencies
* Show timezone on the post edit page when does not match site timezone

## 4.4.15 ( 2022-01-26 )

* Add airline brand icons
* Add labels now customizable for location taxonomy
* Update dependencies

## 4.4.14 ( 2021-11-12 )

* Fix for posts without location causing error
* Added rewrite for map and location taxonomy together. /maps/location/us for example
* Added option for showing taxonomy in the location display over the address field.

## 4.4.13 ( 2021-11-02 )

* Additional type fixing

## 4.4.12 ( 2021-10-30 )

* Fix minor bug
* Update airport data

## 4.4.11 ( 2021-09-28 )

* Fix issue with protected functions causing errors
* Remove duplicative data for country and region conversion
* Add unit tests
* Add flags to country pulldowns
* Add flags to Archive Views

## 4.4.10 ( 2021-09-06 )

* Set Default Time for Posts based on the User Timezone field.
* Ensure Last Seen Does Not Update if Off by More than Ten Minutes
* Update Rest Field return to show all returns, but only if the information is public, otherwise it is blank.
* Add timezone to REST comment endpoint
* Remove empty returns from location data in post, comment, and user endpoints
* Add custom capabilities

## 4.4.9 ( 2021-08-21 )

* Refresh of Map Providers based on latest API parameters
* Introduction of Max Height, Width, and Zoom parameters to prevent going larger than provider supports
* Introduction of TomTom maps
* Adjustment of icon styling

## 4.4.8 ( 2021-07-23 )

* Refresh of dependencies and compatibility check with WP5.8

## 4.4.7 ( 2021-05-14 )

* Add Pirate Weather( https://pirateweather.net ) as a weather provider. I like to support independents.

## 4.4.6 ( 2021-04-05 )

* Hide localities in parent list.
* Set location taxonomy in Micropub.
* Move location taxonomy into same box as rest of details.

## 4.4.5 ( 2021-02-27 )

* forgot to commit necessary data files in 4.4.4

## 4.4.4 ( 2021-02-27 )

* Fix issue where saving terms places other than the admin causes data loss. This also fixes data loss when using a taxonomy merge tool.
* Switch from querying for the term meta to determining type of location by hierarchy.
* Check permissions before allowing the edit of the term.
* Add region select pulldown which will be shown unless the value of the region is not on the list.

## 4.4.3 ( 2021-02-13 )

* Fix issue with saving Term Metadata
* Add list location function to output a list of used locations. Currently wrapper around wp_list_categories
* When you add a new term it automatically refreshes the entire list of locations so the new term will appear properly in the hierarchy.
* Additional unit testing.

## 4.4.2 ( 2021-02-09 )

* Reworking of the region search code using new data sources to try to get some consistency between providers.
* Add option to the debugger to not add new term, to allow for testing.
* Revamp of UI based on feedback to make it less fragile.

## 4.4.1 ( 2021-02-07 )

* Location was only being shown in DEBUG mode
* When a match for region code was not found the code was creating a region with a name but no code, which meant duplication. Going forward, it will use the full region name. It will also try additional matching to find the code.
* When looking up the location on an existing post, it was setting everything to private. Going forward, only if not already set.
* Display Name function now links each part of the location to the term page for it.
* Switch rewrite for taxonomy maps to /map/taxonomy instead of taxonomy being at the end.
* Update custom station provider to check timestamp and support future station historic weather.

## 4.4.0 ( 2021-02-07 )

* Rename the Mapquest provider to OpenMapquest as there is an alternative Mapquest API.
* Make the Mapquest open provider and LocationIQ provider a descendant of the nominatim provider as both return the same data.
* Introduce MapQuest geolocation provider, separate from openmapquest.
* Fix issue with Google reverse lookup return.
* Review and update all reverse/geocode lookups from API documentation.
* Standardize country codes in all returns to the iso2 coding.
* Add support for converting iso3 to iso2 country codes.
* Support generating street addresses for countries where the house number appears after the street name.
* Improve display location name generation from address components.
* Add Pelias Provider. OpenRoute is actually a hosted instance of the Pelias Geocoder, and now inherits its conversion functionality.
* Standardize the addition of region codes, special casing the US States and Canadian Provinces, but using a global list as well.
* If there is no address or locality then display the full region name instead of the code.
* Add home country option which will hide the country from the location display.
* Introduce Location Taxonomy.
* Introduce bulk lookup capability.
* fix issue where Micropub lookup did not add historic data.

## 4.3.0 ( 2021-01-09 )

* Support for historic weather data in providers who offer it, see readme for details.
* Addition of Visual Crossing as a provider. Visual Crossing is the first provider which in addition to 1000 queries per day, offers a pay per use model past that as opposed to a monthly subscription.
* Addition of Meteostat as a provider. Meteostat is free for use, but is set up for historical weather data only.

## 4.2.3 ( 2021-01-03 )

* Additional error checking
* Improved mapping for the Custom Station provider

## 4.2.2 ( 2021-01-02 )

* Measurements are always stored in metric units, and were displayed on the front end in imperial units when set.
** This version will now show it in imperial on the backend and convert to metric for storage after the fact.
* ?sloc_units=imperial or ?sloc_units=metric will override the display to show one or the other if added to any URL
* The units displayed will now show in the labels if edited in the Post UI.
* All weather markup now uses a common function and label list.

## 4.2.1 ( 2020-12-10 )

* Custom station provider said it would use the station within 10km, but was actually set to 100km. Fixed.

## 4.2.0 ( 2020-11-24 )

* Introduce custom weather station provider
* Add additional properties to the possible list of weather properties, inspired by the Weewx schema.
* Add fallback weather station setting which allows for the system to try a second weather option if the first fails. This is normally for the providers with limited geographic coverage.
* Fix issue where errant space in filenames made waxing and waning gibbous icons fail to appear.

## 4.1.12 ( 2020-11-01 )

* Deprecate cache_key and switch to using key generated by class name. Cache will be enabled now by whether cache_time is set to 0 or not.
* Sanitize coordinates when setting providers
* Add caching functions to simplify weather provider caching.
* Cache Time is now an Option for All Widgets

## 4.1.11 ( 2020-10-16 )

* Do additional error checking to ensure timezone notices do not appear.
* Start process of fixing caching.

## 4.1.10 ( 2020-10-11 )

* Do not store timezone string if the offset matches the site timezone.
* Do not store icon if it is the default icon.
* Check for valid map provider before attempting to use it.
* Sanitize REST API inputs
* Trim whitespace on widget inputs
* Switch OpenWeather Map to use the OneCall API.
* Adjust NWS and OpenWeatherMap to Store Wind Gust Info.
* Centralize static map img tag code and add lazy loading flag and srcset option.

## 4.1.9 ( 2020-08-15 )

* Minor bug fixes and check to ensure compatible with WordPress 5.5
* Dependency refresh

## 4.1.8 ( 2020-07-12 )

* Add additional sanity checks due reports of errors caused by removal of provider.
* Astronomical option now shows for all widgets, not just one.

## 4.1.7 ( 2020-07-12 )

* Minor fix for failure in previous release which is not showing up in testing.

## 4.1.6 ( 2020-07-12 )

* Introduce Custom Map Endpoint, based on a [fork](https://github.com/dshanske/Static-Maps-API-PHP) of a library by @aaronpk
* Clean up markers and other settings on the map provider to look more consistent.
* Moon phase code redone, and moonrise/moonset added
* Fix is_day code

## 4.1.5 ( 2020-07-04 )

* Wikimedia Maps is now deprecated. Their decision, not mine.
* OpenRoute Service is added as a geoprovider.
* GeoApify is added as a map provider.
* Yandex added as a map provider. Does not require an API key for limited use.
* Weather widgets all redesigned with new layout. Station and Airport widget now inherited from weather widget so same design in each.
* Last Seen Widget Redesigned.
* Moon Phase added to Last Seen Widget.

## 4.1.4 ( 2020-06-28 )

* Fix error message when timezone result has no timezone string
* Add ability to do map views of taxonomies and user archives

## 4.1.3 ( 2020-06-24 )

* Fix issue where airport widget would only accept IATA codes(3 letters), not ICAO codes(4 letters).

## 4.1.2 ( 2020-06-12 )

* Fix fatal error on user page

## 4.1.1 ( 2020-05-27 )

* AerisWeather is now a provider
* Add docblocks
* PHP5.6 now minimum
* Add additional icons
* Optimizations, refactoring, and bugfix
* Add the ability to change the location icon to various alternatives.
* Add support for retrieving airline data
* Improve airport data support
* Standardize units of measure

## 4.0.6 ( 2020-04-12 )

* Update HERE to use API Key over prior app id system
* Update HERE Map Service to use new endpoint
* Add HERE Weather and Geolocation Service
* Added support for Met Office UK...using the nearest station
* Add debugger tab for geocode lookup and weather lookup
* Misc development fixes to remove duplicative code

## 4.0.5 ( 2020-03-18 )

* Fix issue with timezone handling of photo EXIF data by allowing either timezone string or object
* Fix issue with EXIF altitude handling

## 4.0.4 ( 2020-02-17 )

* Update timezone handling in attachments
* Add timezone offsets to REST API

## 4.0.3 ( 2020-01-26 )

* Minor bug fixes
* Fix precision of weather fields
* Fix incorrect function call

## 4.0.2 ( 2019-12-22 )

* Extract more information from Compass normalize it and pass it to WordPress
* Extract flight information from Compass if in the properties and use this to replace the place name with flight info
* Round weather and location data to 2 or less decimal places
* Ensure timezone is a string
* Introduce new location provider which will derive location from a 3 letter airport code because why not

## 4.0.1 ( 2019-11-24 )

* Switch from removing pagination to limiting it to 100 per page by default
* Filter query by location instead of filtering posts from the quantity afterward

## 4.0.0 ( 2019-11-18 )

* Reimplement timezone handling using updated functions from WordPress 5.3 backported to this plugin
* Enable UTC and offset override support provided you are using PHP5.5 or above
* Reenable the option for nominatim despite it being prone to denial but provide the admin email address as requested by service
* Add support for comments using the timezone of the post they are part of
* APIXU is now Weatherstack.com
* Sunrise and sunset function now in astronomical calculation class and factor in elevation to calculate visual sunset
* Last seen widget now shows local time, sunrise and sunset times and map optionally
* Add airport widget
* Support user update using geojson
* Add tabbed settings page
* Compass API is now a user not a global setting
* Add support for LocationIQ as a map and geo provider
* Add support for WeatherBit as a weather provider
* Add polyline encoder
* Add support for historical locations, currently only Compass supported, in the Classic Editor
* Support generating a static map with multiple location markers for archive views
* Do not add current weather if publish date is not current
* Enhance HERE map styles
* Switch from default map width/height to width and aspect ratio
* Switch default map zoom to summary descriptions over numerical values
* Generate default map zooms when possible based on altitude or accuracy when available
* Update various form fields to use number over text types
* Add /map to archive pages and it will display a custom map archive page
* Misc validation checks to prevent PHP notices
* Fix timezone issues on attachments by using the location of the photo to update the timestamp
* If no location is provided Micropub will lookup the location factoring in the publish time if one is provided

## 3.8.2 ( 2019-09-21 )

* Minor Fixes to photo improvements released in 3.8.1
* Prioritize determining timezone from location over published time when using Micropub

## 3.8.1 ( 2019-06-16 )

* Fix issue where hidden location showed in RSS feed
* Automatically lookup location for uploaded photos
* Automatically convert timestamp on photo to published property

## 3.8.0 ( 2019-05-26 )

* Add Geonames as a Provider
* Fix issue with auto-location on micropub

## 3.7.2 ( 2019-05-17 )

* Additional style fixes
* Add option to show maps on archive and home pages not just single

## 3.7.1 ( 2019-05-12 )

* Fix reported issue with icons being oversized when loaded without style sheet
* Redid weather and location microformats markup. Temperature now marked up as h-measure and location as h-adr
* Update formatting in widgets to not include microformats

## 3.7.0 ( 2019-04-13 )

* Do not return maps if location is protected
* Set Micropub posts with location to public visibility
* Do not save raw weather data on Micropub
* Enhance post filter to include all visibilities
* Add location visibility column to posts
* Fix storage of timezone on Micropub entries
* Update airport data
* Add bulk edit post location visibility
* Automatically add private location on Micropub post
* Clean up data on loading settings page

## 3.6.4 ( 2019-04-01 )

* Fix/update default map styles for Mapbox
* Add default map style now available for Bing

## 3.6.3 ( 2019-03-31 )

* Add Compass as a location provider
* Add APIXU as a weather provider

## 3.6.2 ( 2019-02-25 )

* Fix timezone data conversion when get_the_date is called with a $post object
* Fix micropub timezone storage to store name of timezone over entire timezone object which messed up display
* Fix issue with default geographic options

## 3.6.1 ( 2019-02-23 )

* Missing commit

## 3.6.0 ( 2019-02-23 )

* Round altitude to nearest even number for display
* Minor bug fixes for PHP notices
* Add Wikimedia maps as a map provider. Link to their map site do not quite work but the static maps could be cached in future
* Add setting to display altitude only if above a certain number of meters
* Add zones to encourage privacy
* Visibility returned in geo query if in zone

## 3.5.3 ( 2019-01-04 )

* Fix bug in timezone scope causing Micropub to fail
* Fix sunset darksky error ( props @xavierroy )
* Rename Private to Hidden for logged in users to indicate location isn't shown if not logged in
* Show map on load not just on lookup
* Fix map zoom setting
* Fix properties not showing when post displayed in editor
* When HTML5 geolocation provider is set the rest endpoint will use the user location is queried as you cannot use a browser option outside of the browser

## 3.5.2 ( 2018-12-22 )

* Fix visibility issue finally
* Fix widget title issues
* Style changes/fixes( thanks @asuh)
* Add location provider that always returns the location in the author user profile
* Add endpoint to update the user location
* Show private or protected location if logged in as user who can publish posts with the notation private

## 3.5.1 ( 2018-12-19 )

* Did not merge in fix before pushing

## 3.5.0 ( 2018-12-19 )

* Another attempt to fix the setting of private location by adding testing
* Enhance rest integration
* Add timezone endpoint
* Add geocode endpoint to replace reverse endpoint
* Add airport lookup using method used by @aaronpk
* Rename metric to SI to be more accurate as multiple measurements not just temperature
* Store measurements in SI(meters, celsius, etc) and allow for change to imperial on the fly
* Store additional weather parameters
* Add sunrise and sunset parameters
* Split Weather Station into a Separate Widget
* Remove Station ID API settings in favor of widget
* Add support for the US National Weather Service as a weather provider. It finds the nearest weather station and reports current conditions
* Update Micropub return to add weather even if there is not a location lookup occurring
* Add Micropub query for location
* Removal of hidden underused feature that set timezone based on browser settings
* Fix of map endpoint and add map url to the reverse geocode endpoint as it is merely a URL
* Map now displayed when location is looked up.
* Plugin will now work with Gutenberg, though not strictly built for it.

## 3.4.1 ( 2018-11-02 )

* Fix for displaying map when altitude but no location is set
* Automatically update timezone based on location on Micropub posts

## 3.4.0 ( 2018-10-27 )

* Fix for incorrect return when there is an error
* Nominatim began to block reverse traffic so added additional options for reverse lookup.
* Map Providers and Geo Providers are now separated
* Unload Jetpack Geo Location which was added unknown to me in 2017. However added compatibility functions to ensure functionality matches
* Declares post-type support for geo-location which is something the JetPack plugin does
* Adds location meta tags
* Add sanitize function that ensures geo_public is always saved as an integer
* Empty address now causes plugin to display coordinates if not private
* Provider registration now done by function as opposed to filter
* Mapquests hosted version of Nominatim now offered but requires API Key
* Mapquest Static Maps now a supported map provider
* HERE Maps is now a supported map provider
* Google is now a supported reverse lookup provider
* Bing is now a supported reverse lookup provider
* Elevation/altitude in meters is now calculated using an Elevation API when not supplied by the location provider
* DarkSky is now a supported weather provider
* Removal of the SLOC_PUBLIC constant in favor of this being stored in the options table
* Default Location Visibility now allows all three options
* Display altitude over 500m
* Auto add weather and location textual description when coordinates are provided

## 3.3.8 ( 2018-05-27 )

* Fix for jsonFeed error

## 3.3.7 ( 2018-05-14 )

* Fix for jsonFeed privacy settings

## 3.3.6 ( 2018-05-12 )

* Privacy and data collection statement
* Add geoJSON to JSONFeed
* Fix incorrect permissions for GEORSS

## 3.3.5 ( 2018-04-15 )

* Minor location attachment storage changes

## 3.3.4 ( 2018-03-27 )

* Fix issue when default is not set

## 3.3.3 ( 2018-03-26 )

* Add markers to static map and map link for Mapbox
* Clean up URL generation code for static maps
* Update jstz dependency and set up scripted update for future
* Remove hard-coded options for provider selection and make code more dynamic to allow third-party additions
* Add support for alternative geolocation providers in the backend. Currently defaults to the HTML5 browser option in the frontend
* Weather now supported for non-SSL sites

## 3.3.2 ( 2018-02-03 )

* Simplify and refactor metadata saving functionality
* Improvements to metaboxes ( props to @kingkool68 for contributions )
* Refactored location JS
* Add confirmation before clearing data
* Added loading spinner while retrieving location information
* Move location visibility and lookup to Publish Metabox
* Rename visibility settings in an attempt at clarity
* Auto-load weather when retrieving location
* Add start of function to retrieve location optionally on backend
* Icons change color when hovered over to indicate functionality

## 3.3.1 ( 2018-01-13 )

* Add configuration setting for both imperial and metric temperatures. Defaults to metric unless locale is `en_us`.
* Store units in each new post in case the setting is switched
* Minor bug fixes
* Check for existence of property before trying to display it

## 3.3.0 ( 2018-01-11 )

* Introduce Weather Icons, licensed under SIL OFL 1.1
* Add support for weather providers
* Initial display for temperature
* Add user profile settings for location
* Add setting to set if user location is updated when a new post with location is published by that user
* Add weather widget that can be set based on coordinates, user last reported location, or station ID
* Allow filtering post admin and comment admin by location
* Add last seen widget

## 3.2.4 ( 2017-12-09 )

* Fix issue with rendering of timezone select when manual offset
* Disable timezone override for now when manual offset is used as not a valid timezone string
* Add p-location and change markup to accommodate
* Update location icon
* Add settings page and move settings out of media

## 3.2.3

* Restore PHP 5.3 and 5.4 compatibility
* Fix issues raised by automated testing
* If you click the clock next to the word timezone it will set the timezone based on browser time

## 3.2.2

* Allow setting timezone from Micropub posts(requires update to Micropub plugin)

## 3.2.1

* Show settings for current default map provider only
* Add style settings for each map provider ( props @miklb )

## 3.2.0

* Allow passing of coordinates directly in constructor for map provider
* Switch to argument array instead of individual properties for most display and data functions
* Support per post zoom settings
* Set initial map zoom based on geolocation accuracy
* Quick and dirty Bing Maps support
* Set location metadata for attachments if in EXIF data
* Add location metabox to attachments
* Add arguments for marking up location
* Add GEORSS support from defunct project

## 3.1.0

* New release with more functionality coming soon
* `get_geodata` function now supports WP_Post, WP_Comment, and WP_Term objects
* Fix registration of default settings
* Add global setting for public or private by default
* Switch from admin ajax to REST Route API for simple endpoints
* Add map endpoint to retrieve a URL for a map based on coordinates
* Add reverse endpoint to retrieve an address object based on coordinates
* Allow arguments to be passed to map provider
* Remove popup location metabox and replace with slidedown metabox
* Combine geolocation and reverse lookup buttons
* Set public/private to system default if post is published outside of Post UI

## 3.0.4

* Fix Activation Issue

## 3.0.3

* Add support for queries and permalinks to show location enabled posts
* Use built-in WP timezone list generation code
* Fix error with private setting
* Put in check for improperly timezone setting to avoid exception error

## 3.0.2

* Continuing to iterate based on initial feedback to 3.0.0
* Timezone box now hidden until checked
* Timezone now stored in geo_timezone in interest of consistency
* Icon Size for some fixed
* Priority of location and map box increased
* Text added to Location box to explain how to complete
* Constant SLOC_PUBLIC and filter geo_public_default allow the default to be changed from public(1) to private(0) if no geo_public is set
* Display Address generation tweaks
* Display now shows all HTML5 geolocation API stats.

## 3.0.1

* Some quick fixes on the release. Due to issues with the removal of the old location data, it will no longer be removed. Instead only the extra display metadata will be removed.
* If there is no geo_address set and there are coordinates a geo_address will be automatically set along with timezone

## 3.0.0

* New Version Takes Advantage of new WordPress Term Metadata to create Venues (Feature disabled until future release)
* The most Javascript I've ever used in a WordPress plugin. Retrieving location information is now done without page refresh.
* Timezone Override is set by location lookup.
* Google Map Services will now require an API key to be provided.
* MapBox is now the static map provider if you are using OSM maps and an API key must be provided
* You Can Now Choose Map Providers but not reverse lookup providers which may come in future
* Full Address data is no longer stored in the post. You will have the choice of either a textual description and coordinates in the post
or assigning a venue which can have full data. Venue support in future version and is disabled here.
* Warnings no longer showing in debug logs.
* Displayed name and timezone are now set if Micropub plugin provides geo coordinates

## 2.1.0

* Revamp in Text Display Parameters, now offering three levels of Detail
* Coordinates will now display and Location will link to map if set for full address

## 2.0.3

* Google Static Maps now link to Google Maps
* Nominatim now defaultly tries to retrieve in the language of the blog

## 2.0.2

* Fixed formatting on timezone feature as it was not displaying the proper date formatting and falling back to default

## 2.0.1

* Option to override the displayed timezone on a per post basis for posts made at a location other than the default

## 2.0.0

* Complete Rewrite with improved scoping
* Google Maps is now a provider of static maps
* Maps providers are built with a common interface to allow multiple providers

## 1.0.1

* Some refinements to the presentation

## 1.0.0

* Initial Release
