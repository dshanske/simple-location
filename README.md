# Simple Location #
**Contributors:** dshanske  
**Tags:** location, indieweb  
**Stable tag:** 2.0.0  
**Requires at least:** 4.0  
**Tested up to:** 4.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Adds geographic location support to pages and posts.

## Description ##

Completely rewritten from the initial version. Supports the collection and basic display of location data. 

It supports retrieving location using the HTML5 geolocation API. As it stores the GeoData in a 
WordPress standard format, GeoData can be also be stored by the mobile WordPress apps.

To get more information about the location and to display maps, Simple Location supports various map providers. A API key may be required for some.

It also adds location data to pages, allowing you to create a page about a location.

## Other Notes ##

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).


## WordPress GeoData ##

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post.

**It consists of four fields:** latitude, longitude, public, and address. Altitude has been added as part of the HTML5 geolocation spec, but have yet to get a return from it on any browser.  

## Changelog ##

### Version 2.0 ###
	* Complete Rewrite with improved scoping
	* Offer Google Maps as a provider
	*  

### Version 1.0.1 ###
	* Some refinements to the presentation

### Version 1.0 ###
	* Initial Release

