# Simple Location #
**Contributors:** dshanske  
**Tags:** location, indieweb  
**Stable tag:** 2.0.1  
**Requires at least:** 4.0  
**Tested up to:** 4.2  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Adds geographic location support to pages and posts.

## Description ##

Completely rewritten from the initial version. Supports the collection and basic display of location data for both posts and pages. 

It supports retrieving location using the HTML5 geolocation API. As it stores the GeoData in a 
WordPress standard format, GeoData can be also be stored by the mobile WordPress apps.

Offers the opportunity to change the timezone on a per-post basis.

## Other Notes ##

As of Version 2.0.0, there is the start of support for multiple map providers.

The option to select your choice of provider is not yet there. Until then,
Google is the static maps provider and Nominatim(OpenStreetMap) is the reverse
geocoder.

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).


## WordPress GeoData ##

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post.

**It consists of four fields:** latitude, longitude, public, and address. Altitude has been added as part of the HTML5 geolocation spec, but have yet to get a return from it on any browser.  

## Changelog ##

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

