# Simple Location #
**Contributors:** dshanske  
**Tags:** location, indieweb  
**Stable tag:** 3.0.0  
**Requires at least:** 4.6  
**Tested up to:** 4.6  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Adds geographic location support to pages and posts.

## Description ##

Completely rewritten...again. Supports adding geo coordinates to a post, and will be supporting saving a location as a venue for reuse.

It supports retrieving location using the HTML5 geolocation API. As it stores the GeoData in a 
WordPress standard format, Geodata can also be added from other plugins.

Offers the opportunity to change the timezone on a per-post basis for those posts from far off locations.

## Other Notes ##

As of Version 2.0.0, there is the start of support for multiple map providers.

The option to select your choice of provider is not yet there. Until then,
Google is the static maps provider and Nominatim(OpenStreetMap) is the reverse
geocoder.

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).

## Venues ##

Venues are locations stored as a custom taxonomy in WordPress using the Term Metadata functionality added in Version 4.4 of WordPress. Venues as taxonomies
have the advantage of supporting an archive page of all posts from that location and giving the location a permalink on your site. To add anything more than a basic location you will have to create a venue.

## WordPress GeoData ##

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post.

**It consists of four fields:** latitude, longitude, public, and address.  

## Changelog ##
 
### Version 3.0.0 ###
	* New Version Takes Advantage of new WordPress Term Metadata to create Venues
	* The most Javascript I've ever used in a WordPress plugin.

### Version 2.1.0 ###
	* Revamp in Text Display Parameters, now offering three levels of Detail
	* Coordinates will now display and Location will link to map if set for full address

### Version 2.0.3 ###
	* Google Static Maps now link to Google Maps
	* Nominatim now defaultly tries to retrieve in the language of the blog

### Version 2.0.2 ###
	* Fixed formatting on timezone feature as it was not displaying the proper
		date formatting and falling back to default

### Version 2.0.1 ###
	* Option to override the displayed timezone on a per post basis for posts
		made at a location other than the default

### Version 2.0 ###
	* Complete Rewrite with improved scoping
	* Google Maps is now a provider of static maps
	* Maps providers are built with a common interface to allow multiple providers

### Version 1.0.1 ###
	* Some refinements to the presentation

### Version 1.0 ###
	* Initial Release

