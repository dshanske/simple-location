=== Simple Location ===
Contributors: dshanske
Tags: geolocation, timezones, geo, maps, location, weather, indieweb
Stable tag: 4.0.6
Requires at least: 4.9
Tested up to: 5.4
Requires PHP: 5.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds geographic location and weather support to WordPress.

== Description == 

Supports adding geo coordinates or textual description to a post, comment, user, or attachment. Supports adding weather data to a post or to a widget based on location.
Offers choice of map displays. It supports retrieving location using the HTML5 geolocation API by default. Clicking the location icon or 'Lookup Location' will retrieve the location. 

As it stores the GeoData in a WordPress standard format, Geodata can also be added from other plugins.

Automatically saves location data from image metadata when uploaded as well.

Offers the opportunity to change the displayed timezone on a per-post basis for those posts from far off locations and set this based on the coordinates of the location. 
While Gutenberg compatible, this is not built for Gutenberg.

* If your site is set to a Manual UTC Offset as opposed to a named timezone, the timezone override feature will not work properly if you are running PHP5.4 or less

== Privacy and Data Notice ==

Simple Location stores location and weather data inside posts, attachments, comments, and term meta...optionally other post types. This data respects a public, private or 
protected setting. Attachment data is automatically extracted from images if location is present, which could be extracted by any third-party downloading the picture
unless removed. For all other data, it is provided by the user, who decides its ultimate use. Location data is made available through a geolocation provider...the default is currently
HTML5 browser geolocation, for which the user must give consent to share). Other information is secured through use of third-party APIs to identify a 
location, calculate elevation, display maps, and weather conditions.

== Zones == 

Zones allow for geofencing. You can set coordinates and a radius around them. If you set location to a place within a zone, the default behavior is to replace the location
with a preset Name and hide the coordinates. This allows you to protect private locations such as your home, or your place of business. For Micropub, it will set the location
as protected if the location is in the zone and the location-visibility property is not set. For the post editor in WordPress, looking up the location of an item inside the 
zone will result in the visibility being set to protected and the name being set to the zone name. This can be overridden.

== Venues ==

Venues are locations stored as a custom taxonomy in WordPress using the Term Metadata functionality added in Version 4.4 of WordPress. Venues as taxonomies
have the advantage of supporting an archive page of all posts from that location and giving the location a permalink on your site. 

To add anything more than a basic location you will have to create a venue. This functionality is still pending.

== WordPress GeoData ==

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standardized way to store geodata about a post, user, comment, or term.

It consists of four fields: latitude, longitude, public, and address. This matches up with the HTML5 Geolocation fields. The [W3C Geolocation Specification](https://dev.w3.org/geo/api/spec-source.html) 
also provides for properties of altitude, accuracy, altitudeAccuracy, speed, and heading, which may be stored. Map Zoom is also stored as a geodata property.

Timezone is also stored as a property and is derived from the location by default or set manually.

== Weather ==

Weather consists of at minimum the current conditions and temperature but includes future parameters for use such as pressure, wind speed, wind direction degree, etc. Weather widgets are available 
that can be set to a specific location, a user, station ID, or airport code. Station ID is available from supported providers for weather stations, for example from a Personal Weather Station(PWS).

== Providers ==

The plugin is designed to be extensible and anyone could write a plugin that would add additional providers.

* Map Providers are services that offer an API to retrieve maps, which are displayed on posts with a location. Providers include Wikimedia, MapBox, Google, Mapquest's Open Static Map, HERE, LocationIQ, and Bing
* Geocoding Providers take geo coordinates and look up the actual location/address for textual display, as well as derive the elevation is possible. Geocoding Providers include Nominatim, HERE, the Mapquest hosted version of Nominatim, Google, Bing, LocationIQ and Geonames.
* Location Providers attempt to determine your location to add it to a post. Providers include  HTML5 Browser Geolocation, a Provider that takes the location setting out of the author profile, a provider that returns the exact
location of a three letter airport code, and [Compass](https://github.com/aaronpk/Compass), a self-hosted option for storing your location.
* Weather Providers retrieve weather data about your location and include OpenWeatherMap, Dark Sky, Weatherstack, WeatherBit, HERE and the US National Weather Service. HERE, Dark Sky, WeatherBit, and Weatherstack do not support stations.
** The National Weather Service(US) uses their station lookup API to find the closest weather station, and uses weather from there. Therefore, if this returns no options, if you are outside the US, it will return no weather.
** The Met Office(UK) uses the distance from your current location to the nearest UK weather station and finds the closest one. However, if the nearest station is more than 100km away, it will return nothing.

== Frequently Asked Questions ==

= What are the requirements to use this plugin? =

API Keys are required to use certain services.
* [Google](https://developers.google.com/maps/documentation/javascript/get-api-key)
* [Mapbox](https://www.mapbox.com/help/create-api-access-token/) - To retrieve style list inside the UI, you need a token with the styles:list scope
* [Bing](https://www.bingmapsportal.com/)
* [OpenWeatherMap](http://openweathermap.com/api)
* [MapQuest](https://developer.mapquest.com/)
* [HERE](https://developer.here.com/)
* [Dark Sky](https://darksky.net/dev)
* [Compass](https://github.com/aaronpk/Compass)
* [Weatherstack](https://weatherstack.com)
* [Weatherbit](https://www.weatherbit.io/api/weather-current)
* [GeoNames](https://www.geonames.org) - requires a username
* [LocationIQ](https://locationiq.com/)
* [Met Office UK](https://www.metoffice.gov.uk/services/data/datapoint)

At this time, the only map service available without an API key is Wikimedia maps
Nominatim does not require an API key, but it does ask for an email address, which will be the admin email of the site
If not provided there will be no map displayed regardless of setting, reverse geo lookup will not work 
Without a weather provider this service will not work. 

API Keys may have free limits, or may incur fees if overused. This plugin only uses a request when you post, which is usually well within the free tier which is usually thousands of requests.

The appropriate API keys should be entered in Settings->Simple Location or will move to Indieweb->Location if the Indieweb plugin if installed.

= Is this compatible with the WordPress mobile apps? =

Simple Location uses WordPress Geodata to store location, as does the WordPress app. So setting location with the app should allow it to be displayed by Simple Location. The only major difference
is that whether or not a location is public is set with either 0 for private or 1 for public. The spec implemented states a non-zero number is considered public. This plugin adds the option of 2,
also known as protected, which shows a textual description of the location but does not display a map or geographic coordinates.

= The Location Icon does not retrieve my location. =

Chrome Users: Retrieves the location using the HTML5 geolocation API(available in your browser) will not work on Chrome if your website is not secure(https). This is a Chrome decision to ensure safe control of personal data.

You can take advantage of the other built-in location providers, for example, one uses the location of the user or create your own location provider as a separate plugin.

= How can I update the location of my user profile? = 

You can do so under your user profile or alternatively update using a REST API endpoint. By posting to `/wp-json/sloc_geo/1.0/user` with the latitude, longitude, altitude parameters, or with a geojson body, will
update the user associated with the credentials you provide to the REST API.

= How can I access the location or weather data on the frontend? =

There are REST API endpoints to retrieve the data so it can be used in the admin under the namespace `/wp-json/sloc_geo/1.0`:
* /timezone which will return timezone data for a latitude/longitude or airport code
* /weather which will return the weather for a latitude/longitude or station ID
* /geocode which will return the address information for a latitude/longitude and optionally add the weather
* /lookup which will return the current location for a user based on the location provider
* /map which will return static map data for the provided latitude and longitude

= What is Compass? =

[Compass](https://github.com/aaronpk/Compass) is a GPS tracking server that stores data in flat files. The instructions for installation are available in the GitHub repository. GPS
data can be sent to it from iOS or Android devices using various apps. 

= How can I show a list of posts tagged with a location? =

You can filter any query or archive by adding `?geo={all|public|text}` to it to show only public posts with location. Adding /geo/all to the homepage or archive pages should also work

= How can I see a map of all the locations in an archive page? =

If you add /map to any archive URL, for example, example.com/2019/map it will return a template with a map view of that archive. It uses a default template built into the theme.
Being as styling this would not be customized to your theme, you can add a map-archive.php file to your theme to customize this.

= JetPack offers Location Display, why do I need this? =

JetPack only began offering location display in 2017, 3 years after this plugin was created. This plugin disables their implementation as it created conflicts.

They do not offer the features this plugin does and their goal is a minimal implementation.

= Why am I seeing location on private posts with the notation Hidden? =

This appears to users who can edit private posts when logged in.

= How can I report issues or request support? =

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).

= How can I add support for ___ ? = 

Simple Location has the concept of Providers. Providers are an abstract class that you can implement to take information from one format into the one Simple Location understands.
The plugin offers providers for:
* Geolocation - Looking up an address from coordinates or vice versa
* Location - By default this uses your browser to lookup your location but you can alternatively tap into a service to get your current location, perhaps from your phone
* Weather - Retrieves weather based on location or station ID
* Map - Provides maps for display

== Upgrade Notice ==

= 4.1.0 =

Dark Sky has been acquired by Apple and no longer permits you to apply for an API key. If you already have one, the functionality in this plugin will work, according to them, until end of 2021 after
which it will be removed from this plugin. The plugin offers several other weather providers. The Met Office weather plugin has not been tested in live use as the developer is not in the UK. It
is also not as detailed as some of the other options in what data it provides.

= 4.0.0 =

The Compass API/URL information is now stored in the user profile. When publishing, it will pull this information in from the current logged in user. This was previously stored globally. When using UTC offsets over
timezone strings, only systems running PHP5.5 and above will work correctly. Default height for maps has been replaced by aspect ratio.

= 3.7.0 =

This upgrade cleans up some possibly old data in the database when you load the settings page for the plugin. If you have a lot of posts, the load may be slow initially.

= 3.4.0 =

Hardcoded and filtered options for new providers have been replaced by a provider registration function with the strings and slug for the provider set inside the provider itself.

= 3.0.0 =

Recommend backup before upgrade to Version 3.0.0 due to the start of venue support. Full location data will not be saved in the post and old posts will be converted. The display name will be saved if set, otherwise a display name will be set from the coordinates. An API key
will now be required to show maps for services that require API keys.

== Changelog ==

= 4.0.6 ( 2020-04-12 ) =
* Update HERE to use API Key over prior app id system
* Update HERE Map Service to use new endpoint
* Add HERE Weather and Geolocation Service
* Added support for Met Office UK...using the nearest station
* Add debugger tab for geocode lookup and weather lookup
* Misc development fixes to remove duplicative code

= 4.0.5 ( 2020-03-18 ) =
* Fix issue with timezone handling of photo EXIF data by allowing either timezone string or object
* Fix issue with EXIF altitude handling

= 4.0.4 ( 2020-02-17 ) =
* Update timezone handling in attachments
* Add timezone offsets to REST API

= 4.0.3 ( 2020-01-26 ) =
* Minor bug fixes
* Fix precision of weather fields
* Fix incorrect function call

= 4.0.2 ( 2019-12-22 ) =
* Extract more information from Compass normalize it and pass it to WordPress
* Extract flight information from Compass if in the properties and use this to replace the place name with flight info
* Round weather and location data to 2 or less decimal places
* Ensure timezone is a string
* Introduce new location provider which will derive location from a 3 letter airport code because why not

= 4.0.1 ( 2019-11-24 ) =
* Switch from removing pagination to limiting it to 100 per page by default
* Filter query by location instead of filtering posts from the quantity afterward

= 4.0.0 ( 2019-11-18 ) =
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

= 3.8.2 ( 2019-09-21 ) =
* Minor Fixes to photo improvements released in 3.8.1
* Prioritize determining timezone from location over published time when using Micropub

= 3.8.1 ( 2019-06-16 ) = 
* Fix issue where hidden location showed in RSS feed
* Automatically lookup location for uploaded photos
* Automatically convert timestamp on photo to published property

= 3.8.0 ( 2019-05-26 ) =
* Add Geonames as a Provider
* Fix issue with auto-location on micropub

= 3.7.2 ( 2019-05-17 ) =
* Additional style fixes
* Add option to show maps on archive and home pages not just single

= 3.7.1 ( 2019-05-12 ) =
* Fix reported issue with icons being oversized when loaded without style sheet
* Redid weather and location microformats markup. Temperature now marked up as h-measure and location as h-adr
* Update formatting in widgets to not include microformats

= 3.7.0 ( 2019-04-13 ) = 
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

= 3.6.4 ( 2019-04-01 ) =
* Fix/update default map styles for Mapbox
* Add default map style now available for Bing

= 3.6.3 ( 2019-03-31 ) =
* Add Compass as a location provider
* Add APIXU as a weather provider

= 3.6.2 ( 2019-02-25 ) =
* Fix timezone data conversion when get_the_date is called with a $post object
* Fix micropub timezone storage to store name of timezone over entire timezone object which messed up display
* Fix issue with default geographic options

= 3.6.1 ( 2019-02-23 ) =
* Missing commit

= 3.6.0 ( 2019-02-23 ) =
* Round altitude to nearest even number for display
* Minor bug fixes for PHP notices 
* Add Wikimedia maps as a map provider. Link to their map site do not quite work but the static maps could be cached in future
* Add setting to display altitude only if above a certain number of meters
* Add zones to encourage privacy
* Visibility returned in geo query if in zone

= 3.5.3 ( 2019-01-04 ) =
* Fix bug in timezone scope causing Micropub to fail
* Fix sunset darksky error ( props @xavierroy )
* Rename Private to Hidden for logged in users to indicate location isn't shown if not logged in
* Show map on load not just on lookup
* Fix map zoom setting
* Fix properties not showing when post displayed in editor
* When HTML5 geolocation provider is set the rest endpoint will use the user location is queried as you cannot use a browser option outside of the browser

= 3.5.2 ( 2018-12-22 ) =
* Fix visibility issue finally
* Fix widget title issues
* Style changes/fixes( thanks @asuh)
* Add location provider that always returns the location in the author user profile
* Add endpoint to update the user location
* Show private or protected location if logged in as user who can publish posts with the notation private

= 3.5.1 ( 2018-12-19 ) =
* Did not merge in fix before pushing

= 3.5.0 ( 2018-12-19 ) =
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

= 3.4.1 ( 2018-11-02 ) =
* Fix for displaying map when altitude but no location is set
* Automatically update timezone based on location on Micropub posts

= 3.4.0 ( 2018-10-27 ) =
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

= 3.3.8 ( 2018-05-27 ) =
* Fix for jsonFeed error

= 3.3.7 ( 2018-05-14 ) =
* Fix for jsonFeed privacy settings

= 3.3.6 ( 2018-05-12 ) =
* Privacy and data collection statement
* Add geoJSON to JSONFeed
* Fix incorrect permissions for GEORSS

= 3.3.5 ( 2018-04-15 ) =
* Minor location attachment storage changes

= 3.3.4 ( 2018-03-27 ) =
* Fix issue when default is not set

= 3.3.3 ( 2018-03-26 ) =
* Add markers to static map and map link for Mapbox
* Clean up URL generation code for static maps
* Update jstz dependency and set up scripted update for future
* Remove hard-coded options for provider selection and make code more dynamic to allow third-party additions
* Add support for alternative geolocation providers in the backend. Currently defaults to the HTML5 browser option in the frontend
* Weather now supported for non-SSL sites

= 3.3.2 ( 2018-02-03 ) =
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
= 3.3.1 ( 2018-01-13 ) =
* Add configuration setting for both imperial and metric temperatures. Defaults to metric unless locale is `en_us`.
* Store units in each new post in case the setting is switched
* Minor bug fixes
* Check for existence of property before trying to display it
= 3.3.0 ( 2018-01-11 ) =
* Introduce Weather Icons, licensed under SIL OFL 1.1
* Add support for weather providers
* Initial display for temperature
* Add user profile settings for location
* Add setting to set if user location is updated when a new post with location is published by that user
* Add weather widget that can be set based on coordinates, user last reported location, or station ID
* Allow filtering post admin and comment admin by location
* Add last seen widget
= 3.2.4 ( 2017-12-09 ) =
* Fix issue with rendering of timezone select when manual offset
* Disable timezone override for now when manual offset is used as not a valid timezone string
* Add p-location and change markup to accommodate
* Update location icon
* Add settings page and move settings out of media
= 3.2.3 =
* Restore PHP 5.3 and 5.4 compatibility
* Fix issues raised by automated testing
* If you click the clock next to the word timezone it will set the timezone based on browser time
= 3.2.2 =
* Allow setting timezone from Micropub posts(requires update to Micropub plugin)
= 3.2.1 =
* Show settings for current default map provider only
* Add style settings for each map provider ( props @miklb )
= 3.2.0 =
* Allow passing of coordinates directly in constructor for map provider
* Switch to argument array instead of individual properties for most display and data functions
* Support per post zoom settings
* Set initial map zoom based on geolocation accuracy
* Quick and dirty Bing Maps support
* Set location metadata for attachments if in EXIF data
* Add location metabox to attachments
* Add arguments for marking up location
* Add GEORSS support from defunct project
= 3.1.0 =
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
= 3.0.4 =
* Fix Activation Issue
= 3.0.3 =
* Add support for queries and permalinks to show location enabled posts
* Use built-in WP timezone list generation code
* Fix error with private setting
* Put in check for improperly timezone setting to avoid exception error
= 3.0.2 =
* Continuing to iterate based on initial feedback to 3.0.0
* Timezone box now hidden until checked
* Timezone now stored in geo_timezone in interest of consistency
* Icon Size for some fixed
* Priority of location and map box increased
* Text added to Location box to explain how to complete
* Constant SLOC_PUBLIC and filter geo_public_default allow the default to be changed from public(1) to private(0) if no geo_public is set
* Display Address generation tweaks
* Display now shows all HTML5 geolocation API stats.
= 3.0.1 =
* Some quick fixes on the release. Due to issues with the removal of the old location data, it will no longer be removed. Instead only the extra display metadata will be removed.
* If there is no geo_address set and there are coordinates a geo_address will be automatically set along with timezone	
= 3.0.0 =
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
= 2.1.0 =
* Revamp in Text Display Parameters, now offering three levels of Detail
* Coordinates will now display and Location will link to map if set for full address
= 2.0.3 =
* Google Static Maps now link to Google Maps
* Nominatim now defaultly tries to retrieve in the language of the blog
= 2.0.2 =
* Fixed formatting on timezone feature as it was not displaying the proper date formatting and falling back to default
= 2.0.1 = 
* Option to override the displayed timezone on a per post basis for posts made at a location other than the default
= 2.0.0 =
* Complete Rewrite with improved scoping
* Google Maps is now a provider of static maps
* Maps providers are built with a common interface to allow multiple providers
= 1.0.1 =
* Some refinements to the presentation
= 1.0.0 =
* Initial Release
