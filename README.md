# Simple Location #
**Contributors:** dshanske  
**Tags:** location, indieweb  
**Stable tag:** 0.1.0  
**Requires at least:** 4.0  
**Tested up to:** 4.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Adds geographic location support to pages and posts.

## Description ##

The goal of this plugin, as the Simple name implies, is to support the 
collection and basic display of location data. 

It does this using the HTML5 geolocation API. As it stores the GeoData in a 
WordPress standard format, GeoData can be also be stored by the mobile 
WordPress apps.

It allows pages to act as venues, which is a page for a specific location.

## WordPress GeoData ##

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post.

**It consists of four fields:** latitude, longitude, public, and address. For   
Indieweb Compatibility, there is a 5th parameter, venue.

Venue would identify the location's name or would optionally be a URL for a 
Venue elsewhere on the site.
