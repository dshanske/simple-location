# Simple Location #
**Contributors:** dshanske  
**Tags:** location, indieweb  
**Stable tag:** 1.0.1  
**Requires at least:** 4.0  
**Tested up to:** 4.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Adds geographic location support to pages and posts.

## Description ##

Supports the collection and basic display of location data.

It does this using the HTML5 geolocation API. As it stores the GeoData in a 
WordPress standard format, GeoData can be also be stored by the mobile 
WordPress apps. It also stores address data which can be optionally retrieved from OpenStreetMap.

It also adds location data to pages, allowing you to create a page about a location.

## Other Notes ##

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).

Simple Location uses Nominatim and the OpenStreetMap project by default for geodata. The plugin may retrieve resources from these services.

To generate maps, the plugin is currently using [Static Map Lite](https://github.com/dfacts/staticmaplite) by Gerhard Koch. This code optionally retrieves tiles for the static maps from three different OSM servers. The OSM tile CDN offered 
by MapQuest, OSMs own tile server, and the OpenCycleMap tile server. By default,the plugin is only using the OSM tile server.

Future versions of the plugin may switch to alternative map generation options or offer a choice of service providers.

## WordPress GeoData ##

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standard
used to store geodata about a post.

**It consists of four fields:** latitude, longitude, public, and address. Altitude has been added as part of the HTML5 geolocation spec, but is not currently in use.  

## Changelog ##

* Version 1.0.1 - Some refinements to the presentation
* Version 1.0  - Initial Release

