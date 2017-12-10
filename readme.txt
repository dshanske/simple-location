=== Simple Location ===
Contributors: dshanske
Tags: geolocation, geo, maps, location, indieweb
Stable tag: 3.2.4
Requires at least: 4.7
Tested up to: 4.9
Requires PHP: 5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds geographic location support to WordPress.

== Description == 

Supports adding geo coordinates or textual description to a post, comment, user, or attachment. Will be supporting saving a 
location as a venue for reuse. Offers choice of map displays.

It supports retrieving location using the HTML5 geolocation API. As it stores the GeoData in a WordPress standard format, Geodata can also be added from other plugins.

Offers the opportunity to change the displayed timezone on a per-post basis for those posts from far off locations and set this based on the coordinates of the location. 
Clicking the clock icon next to the timezone will set to the browser timezone.

* If your site is set to a Manual UTC Offset as opposed to a named timezone, the timezone override feature will not work at this time

== Venues ==

Venues are locations stored as a custom taxonomy in WordPress using the Term Metadata functionality added in Version 4.4 of WordPress. Venues as taxonomies
have the advantage of supporting an archive page of all posts from that location and giving the location a permalink on your site. 

To add anything more than a basic location you will have to create a venue. This functionality is still pending.

== WordPress GeoData ==

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post, user, comment, or term.

It consists of four fields: latitude, longitude, public, and address. The plugin also saves zoom which is for the purpose of map display.

== Frequently Asked Questions ==

= What are the requirements to use this plugin? =

API Keys are required to use [Google Static Maps](https://developers.google.com/maps/documentation/javascript/get-api-key), [Mapbox Static Maps](https://www.mapbox.com/help/create-api-access-token/), or [Bing Maps](https://www.bingmapsportal.com/). 
If not provided there will be no map displayed regardless of setting. The appropriate API keys should be entered in Settings->Simple Location or will move to Indieweb->Location if the Indieweb plugin
if installed.

= Is this compatible with the WordPress mobile apps? =

Simple Location uses WordPress Geodata to store location, as does the WordPress app. So setting location with the app should allow it to be displayed by Simple Location.

= The Location Icon does not retrieve my location. =

Chrome Users: The button that retrieves the location using the HTML5 geolocation API will not work on Chrome if your website is not secure(https). This is a Chrome decision to ensure safe control of personal
data.

= How can I show a list of posts tagged with a location? =

You can filter any query or archive by adding `?geo={all|public|text}` to it to show only public posts with location. Adding /geo/all to the homepage or archive pages should also work

= How can I report issues or request support? =

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).

== Upgrade Notice ==

Recommend backup before upgrade to Version 3.0.0 due to the start of venue support. Full location data will not be saved in the post and old posts will be converted. The display name will be saved if set, otherwise a display name will be set from the coordinates. An API key
will now be required to show maps for services that require API keys.

== Changelog ==
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
