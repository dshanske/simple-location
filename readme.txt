=== Simple Location ===
Contributors: dshanske
Tags: geolocation, timezones, maps, location, weather
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 6.1.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds geographic location and weather support to WordPress.

== Description ==

Supports adding geo coordinates, a location taxonomy, and a textual location description to a post, comment, user, or attachment. Supports adding weather data to a post or to a widget based on location.
Offers choice of map displays. It supports retrieving location using the HTML5 geolocation API by default. Clicking the location icon or 'Lookup Location' will retrieve the location.

As it stores the GeoData in the classic WordPress format, Geodata can also be added from other plugins.

Automatically saves location data from image metadata when uploaded as well.

Offers the opportunity to change the displayed timezone on a per-post basis for those posts from far off locations and set this based on the coordinates of the location.
While Gutenberg compatible, this is not built for Gutenberg.

== Privacy and Data Notice ==

Simple Location stores location and weather data inside posts, attachments, comments, and term meta...optionally other post types. This data respects a public, private or
protected setting. Attachment data is automatically extracted from images if location is present, which could be extracted by any third-party downloading the picture
unless removed. For all other data, it is provided by the user, who decides its ultimate use. Location data is made available through a geolocation provider...the default is currently
HTML5 browser geolocation, for which the user must give consent to share). Other information is secured through use of third-party APIs to identify a
location, calculate elevation, display maps, and weather conditions.

== Locations and Venues ==

Locations uses WordPress taxonomies to allow you to generate an archive page of all posts from that location and giving it a permalink on your site. Locations are meant to reflect a more general
location...for example, "Here are posts I made in California."

Locations are divided by administrative areas. The hierarchy for locations is:
* Country
* Region - state, county, or province level.
* Locality - city, town, or village level

You can choose to display the location over the textual address field. There is an option in settings to change this behavior.

The reverse geocoding system attempts to consistently retrieve the country code, region code, and locality for the location and add them to the location taxonomy. This can be potentially problematic, as if the returns
from the locations added aren't consistent, it will duplicate and explode the list of terms. This is the same issue that has held back venues.

Locations are matched using the country code, region code, and locality field, and therefore, you can edit the displayed name or the region code without issue. Due to matching issues, you may have to make some manual changes and there may be some occasional duplication.

When duplicates are created, such as two localities for the same city, select them in the Locations list and choose Merge. On the next screen, pick the location to keep; the oldest is picked by default. You can merge only locations of the same kind: countries, regions or localities. Their posts and child locations move to the one kept, the others' names and codes are added to it so future lookups find it instead of creating the duplicate again, and the others are deleted, with their archive addresses redirecting to it.

There is no top level Location page, /location included in the plugin. You can create a page at this location and use a custom template to allow it to show a list of your locations.

== Venues ==

Venues are meant to reflect a specific location, such as a business or point of interest. Venues were originally set up as a taxonomy, and were in the plugin unimplemented for several versions. However,
due to various changes since that was set up, they were switched to a custom post type. Under this setup, each venue is represented by a URL on the site that posts can be linked to. The URL contains
information about the venue. At this time, venues are shared between users and it is not assumed that each user has unique venues, though this may be a consideration in future.

Venues can be set to hide the actual location for privacy purposes, for example 'Home'.
A venue can also set the visibility of posts made there with "Visibility of Posts Here", for example Private at home. It applies when a post's location is looked up in the editor, found in the background, sent by Micropub, or a check-in. A visibility chosen on the post, or sent by a Micropub client, still wins.

A venue is created when you check in, such as from a Micropub client, or with a bulk location lookup of check-ins. Before creating one, Simple Location looks for the venue you already have: the same place at a venue provider, the same website or page, or the same name close by. Checking in again from another provider adds to the venue you have instead of making a second one.

A venue's website is shown on its page. Its pages on other sites, such as OpenStreetMap, Google Maps, Foursquare or Wikipedia, are shown too when the venue's location is public, since a map link gives away where it is. You can edit both on the venue, under Website and Other Pages.

A new venue gets one of your venue types when one fits, such as Food for a restaurant, but Simple Location doesn't create types. When a venue has no type, the venue editor offers one, which you can add.

To combine duplicate venues, select them in the Venues list and choose Merge. On the next screen, pick the venue to keep; the oldest is picked by default. Its posts, photos, child venues, links and types then include those of the others, check-ins by the others' names or links find it, and the others are deleted, with their addresses redirecting to it.

= What is kept from venue providers =

Venue providers have different terms on what a site may keep. The plugin follows the maintainers' reading of them, below. It is your site and your decision: if you read a provider's terms differently, or aren't comfortable with them, choose another provider.

* OpenStreetMap Overpass, Nominatim, LocationIQ, Geoapify and your own Pelias server - the place's details are kept: address, phone, categories and links. OpenStreetMap data is under the Open Database License, which allows this with credit to OpenStreetMap contributors; a Pelias server's other sources, such as Who's On First and Foursquare's open places, are open data too.
* Google, Foursquare, HERE, TomTom and Stadia Maps - only the place's identifier and links are kept, such as its Google Maps or Foursquare link and its own website. The name is the one you checked in with, the coordinates are your own, and the address is looked up with your geocoding providers. Their phone numbers, categories and addresses are not stored.
* A check-in from a Micropub client that doesn't name a venue provider is your own, and is kept as sent.

A search for venues is remembered for 10 minutes, so a check-in app asking again from the same place doesn't use up your quota. This applies to the OpenStreetMap-based providers, Geoapify, Pelias and HERE, whose terms allow it, but not to Google, Foursquare, TomTom or Stadia Maps.

== Weather ==

Weather consists of at minimum the current conditions and temperature but includes future parameters for use such as pressure, wind speed, wind direction degree, etc. Weather widgets are available
that can be set to a specific location, a user, station ID, or airport code. Station ID is available from supported providers for weather stations, for example from a Personal Weather Station(PWS).

== Stations ==

Stations allow for using a custom data source for weather data: a URL that returns the station's current conditions as JSON. When using the Custom Station provider for weather,
it uses the nearest station within 10 km of your location, and the widgets can show any station by its ID. Readings more than an hour old are not used.

Each station has an ID, a URL, and optionally its latitude and longitude, which are read from the station when it gives them, and a credit with a link, shown with its weather.

The JSON should use the current conditions format, version 1, which the [weewx-current-json](https://github.com/dshanske/weewx-current-json) extension writes for WeeWX stations:

    {
      "format": "current-conditions",
      "version": 1,
      "time": 1761231600,
      "units": "metric",
      "station": { "id": "backyard", "name": "Backyard Weather", "latitude": 39.9524, "longitude": -75.1636, "altitude": 12,
                   "attribution": { "text": "Weather from Backyard Weather", "url": "https://weather.example.com/" } },
      "observations": { "temperature": 21.5, "humidity": 61, "pressure": 1015.2, "windspeed": 3.58, "winddegree": 230, "rain": 1.27, "rainrate": 3.05, "code": 503 }
    }

* `time` is when the observations were made, in Unix seconds.
* `units` is `metric` (degrees C, hPa, m/s, mm, mm/h, meters) or `us` (degrees F, inHg, mph, inches, inches per hour, miles).
* `observations` has any of the plugin's weather properties: temperature, humidity, dewpoint, heatindex, windchill, pressure, cloudiness, windspeed, winddegree, windgust, rain (in the last hour), rainrate, snow, visibility, radiation, illuminance, uv, aqi, and the pollutants pm1_0, pm2_5, pm10_0, co, co2, nh3, o3, pb and so2. Leave out what the station doesn't measure.
* `code` is a condition code, as listed in the weather settings, and `summary` is text; a known summary such as "Partly Cloudy" also gives a code. Without either, a station that measures solar radiation (or illuminance) gets an estimate of the sky by day: its reading is compared with what a clear sky would give at that time and place, from Clear Sky through Few, Scattered and Broken Clouds to Overcast. It isn't estimated with the sun low in the sky, or at night.
* `station.attribution` is the station's credit. A credit set in the station settings replaces it.

For a WeeWX station, install [weewx-current-json](https://github.com/dshanske/weewx-current-json) and use the WeeWX weather provider instead. Both are in beta: the provider is new in this version, and the extension hasn't had a release yet. Its stations, under WeeWX Stations on the same tab, only need the URL of the file the extension writes, such as `https://weather.example.com/current.json`: the station's ID, name and location are read from it when you save. The extension also estimates the condition, such as rain or partly cloudy, from the station's sensors, a nearby airport, or the National Weather Service forecast.

JSON without `format` is read in the earlier format: the weather properties at the top level, in metric units, with wind as `wind: { speed, degree, gust }`, `timestamp` for the time, and `N/A` for missing values.

== WordPress GeoData ==

[WordPress Geodata](http://codex.wordpress.org/Geodata) is an existing standardized way to store geodata about a post, user, comment, or term.

It consists of four fields: latitude, longitude, public, and address. This matches up with the HTML5 Geolocation fields. The [W3C Geolocation Specification](https://dev.w3.org/geo/api/spec-source.html)
also provides for properties of altitude, accuracy, altitudeAccuracy, speed, and heading, which may be stored. Map Zoom is also stored as a geodata property.

Timezone is also stored as a property and is derived from the location by default or set manually.

In order to access private location information, the following custom capabilities are added to WordPress:
* 'read_{$object_type}_location' - Whether or not a user is allowed to read information locations for object types: posts, comments, terms, users. A user is always allowed to read the location from their objects.
* 'edit_{$object_type}_location' - Whether or not a user is allowed to edit information locations for object types: posts, comments, terms, users. A user is always allowed to read the location from their objects.


== Providers ==

The plugin is designed to be extensible and anyone could write a plugin that would add additional providers.

* Map Providers are services that offer an API to retrieve maps, which are displayed on posts with a location. Providers include MapBox, Google, Mapquest, HERE, LocationIQ, Geoapify, TomTom, and Stadia Maps, as well as your own map server: a custom Static Maps API server, or TileServer GL.
* Geocoding Providers take geo coordinates and look up the actual location/address for textual display. Geocoding Providers include Nominatim, which can use the public OpenStreetMap server, your own server or another host, Photon, HERE, MapQuest, Google, LocationIQ, OpenRoute and Geonames. They also support looking up coordinate for physical addresses. Photon needs no key, and you can use komoot's public server or run your own
* Venue providers, similar to Geocoding providers, search for locations from coordinates, however, instead of returning street addresses, or municipality, it returns a list of places or venues. Providers include Google, Foursquare, HERE, TomTom, LocationIQ, Geoapify, Stadia Maps, Nominatim, OpenStreetMap Overpass, which needs no key, and your own Pelias server.
* Elevation Providers optionally derive the elevation from an API when not available. Elevation is used to estimate altitude and adjust estimated sunrise/sunset levels accordingly. Providers include GeoNames, Google, OpenRoute and Open Topo Data, whose public API needs no key and which you can also host yourself.
* Location Providers attempt to determine your location to add it to a post. Providers include  HTML5 Browser Geolocation, a Provider that takes the location setting out of the author profile, a provider that returns the exact
location of a three letter airport code, and three self-hosted location history services: [Compass](https://github.com/aaronpk/Compass), [Dawarich](https://dawarich.app/) and [GeoPulse](https://github.com/tess1o/geopulse).
* Weather Providers retrieve weather data about your location and include OpenWeatherMap, Pirate Weather, Weatherstack, WeatherBit, HERE, Open-Meteo, MET Norway, Environment Canada and the US National Weather Service. Open-Meteo needs no key for non-commercial use, has historical weather, and can be self-hosted. MET Norway needs no key and covers the whole world. Environment Canada needs no key and gives the current conditions of the nearest Canadian city or town, within 100 km. HERE, Pirate Weather, WeatherBit, and Weatherstack do not support stations. There is also an option for custom station URLs that must return the properties as they are stored in WordPress.
** The National Weather Service(US) uses their station lookup API to find the closest weather station, and uses weather from there. Therefore, if this returns no options, if you are outside the US, it will return no weather.
** For weather services like the NWS, Environment Canada and your own stations, where coverage is not worldwide, up to two fallback weather providers can be set. They are tried in order when a provider has no weather. See the FAQ.

== Frequently Asked Questions ==

= What are the requirements to use this plugin? =

Nothing else is needed to save locations. Looking up addresses, drawing maps, and adding weather and elevation use outside services, chosen under Settings > Simple Location > Providers (IndieWeb > Location when the IndieWeb plugin is installed). Some need no key: Nominatim for addresses, Open-Meteo, MET Norway and the US National Weather Service for weather, Open Topo Data for elevation, and your own weather stations. The others need an account, and an API key entered on the API Keys tab, where each service has its own section. See "How do I set up each service?"

Without a map provider, no maps are shown. Without a geocoding provider, addresses aren't looked up. Without a weather provider, weather isn't added.

Most services have a free allowance and charge, or stop, beyond it. The plugin only asks a service when you look up a location or publish a post, which usually stays well within it. You must follow each provider's terms. If a key stops working, or a quota runs out, administrators are told; see "How do I know when a provider stops working?"

= How do I set up each service? =

Sign up with the service, create an API key, and enter it in the service's section of the API Keys tab. Notes for each:

* [Google Maps Platform](https://developers.google.com/maps/get-started) - needs a billing account, and gives each API a monthly free allowance. In the Google Cloud console, enable the APIs for what you use Google for: the Maps Static API for maps, the Geocoding API for addresses, the Elevation API for elevation, and the Places API (New) for venues. Restrict the key to those APIs. For maps, see also "How do I protect my map API keys?"
* [Mapbox](https://account.mapbox.com/access-tokens/) - a public token (starting pk.) draws the maps. To list your own styles in the settings, enter a secret token with the styles:list scope as the Mapbox API Key, your username, and the public token as the Mapbox Map API Key; the secret token is never shown on your site.
* [HERE](https://platform.here.com/) - one API key for maps, addresses and weather.
* [LocationIQ](https://locationiq.com/) - one API key for maps, addresses and venues.
* [Foursquare](https://foursquare.com/developers/) - venues, with a service API key from the Foursquare developer console. Place search is a Pro endpoint, billed per request after a small free allowance.
* [MapQuest](https://developer.mapquest.com/) - one API key for maps and addresses.
* [Geoapify](https://www.geoapify.com/) - one API key for maps and addresses.
* [TomTom](https://developer.tomtom.com/) - maps.
* [Stadia Maps](https://stadiamaps.com/) - static maps need a paid plan. The same key finds venues.
* [OpenRouteService](https://openrouteservice.org/) - one API key for addresses and elevation.
* [GeoNames](https://www.geonames.org/login) - addresses and elevation, with your username instead of a key. After signing up, enable the free web services on your GeoNames account page, or every lookup is refused.
* [Pelias](https://pelias.io/) - addresses and venues from a Pelias server: enter its URL, and its API key if it needs one.
* [OpenWeatherMap](https://openweathermap.org/api/one-call-3) - the plugin uses One Call API 3.0, which needs its own "One Call by Call" subscription, with a payment card, even for the free daily calls. A key without it is refused.
* [Weatherbit](https://www.weatherbit.io/), [Weatherstack](https://weatherstack.com/), [Visual Crossing](https://www.visualcrossing.com/) and [Pirate Weather](https://pirateweather.net/) - an API key each.
* [Xweather](https://www.xweather.com/) - a client ID and client secret. People who share their personal weather station with PWSWeather.com get a free contributor account. You can choose whether personal weather stations are used.
* [Meteostat](https://dev.meteostat.net/) - historic weather, with an API key from RapidAPI.
* Nominatim, Open-Meteo, MET Norway, Open Topo Data and the National Weather Service need no key. Nominatim's public server is for light use only; you can set your own, with the public one as a fallback. Open-Meteo is free for non-commercial use; commercial use needs a key. Open-Meteo and Open Topo Data can also use your own server.
* The custom map provider, custom station provider and WeeWX use your own servers; see their questions below. Compass, Dawarich and GeoPulse find where you are; see "How do I use Dawarich or GeoPulse?"

= How do I add support for location to a custom post type? =

`add_post_type_support( 'geo-location');`
`add_post_type_support( 'weather' );`

The software uses two custom features to declare that the post type supports these features, otherwise the location and weather features do not show in the editor.

= How do I choose the look of my maps? =

The Map Style setting, under Map Settings, chooses a family: Standard, Light, Dark, Satellite or Terrain. Each map provider draws its closest style, so maps keep their look when you change providers. A provider without that kind of style, such as Geoapify for satellite, draws its standard style.
Each provider also has its own style setting, with all its styles. It is empty by default, which uses the Map Style setting; choosing a style there uses that style instead. Sites that chose a provider style before version 6.1.0 keep it.
Google's light and dark styles are its road map recolored with Google's silver and night styling.

With Dark Mode Maps on, maps also have the provider's dark style for visitors whose system is set to dark mode. Browsers only load the one they show. For providers served from your site, the dark style is stored as its own image.

= Why are my maps served from the provider instead of saved as images to my site? =

The terms of service of most map providers expressly forbid this. While the likelihood of one of these providers coming after an individual is slim, this would be a violation of WordPress plugin guidelines.
This is why the plugin does not save maps to the media library.

Geoapify's terms allow storing its static maps, so Geoapify maps are served from your site. The first time a map is shown, your site fetches it from Geoapify, stores it in `wp-content/uploads/sloc-maps`, and serves it from there. Your API key stays on your server, and visitors' browsers never contact Geoapify.
The image links are signed with a secret only your site knows, so nobody else can use your site to request other maps with your key.

Stadia Maps maps are stored the same way when you enter a Stadia Maps API key. Your site then fetches them from Stadia's cacheable endpoint, whose images you may keep for as long as you have a paid Stadia subscription.
Each map from the cacheable endpoint costs many more credits than a standard one (see Stadia's pricing), and each high-density copy and each refresh is a new map, so consider setting Refresh Stored Maps After to 0.
Without a key, or with Serve Maps From This Site turned off, maps load from Stadia's standard endpoint, which may not be stored.

Maps from your own custom map server (see the custom map provider below) are stored the same way, when it signs its URLs or needs no key. Maps the server marks as private, such as Stadia Maps, whose terms forbid caching, are never stored: visitors are sent to the server's signed image link instead. So are maps where the server had to draw its default map, which are asked for again after 5 minutes. With only a plain client key, the server marks every map private, so those maps are loaded from the server.
Maps from your own TileServer GL server are stored the same way. It needs the full tileserver-gl build, version 4.6 or later; the light build can't draw static maps. Choose its style from the ones the server lists. Each location is a dot in the marker color, or set a marker icon from the server's icon folder to draw that instead. The credit under the map, when you choose it there, is the one the server gives for the style; the credit on the image is the server's own static attribution text, set in its configuration. Images are fetched by your site's server, so a TileServer GL on a private address, such as localhost, needs WordPress's http_request_host_is_external filter to allow it.

These settings appear under Map Settings when your map provider allows storing its maps:
* Serve Maps From This Site: on by default. Turn it off to load maps from the provider, with the API key in the image link.
* Refresh Stored Maps After (Days): 30 by default. After this many days, the next page that shows a map fetches it again and replaces the stored image in place. Pages cached with the old image keep working. If the provider can't be reached, the stored image is still shown. 0 never fetches stored maps again. A provider whose terms limit how long maps may be stored is refreshed at least that often.
* Clear Stored Maps: deletes every stored map image. Stored maps are otherwise never deleted, including maps of locations you later changed or made private. Pages kept by a page cache may show broken images until their cache expires.

Developers can change the refresh age with the `sloc_map_cache_max_age` filter, and the storage directory with the `sloc_map_cache_directory` filter.

The other self-serve option is to self-host a caching static map API endpoint (see the custom map provider below).

= How are weather providers credited? =

Most weather providers' terms ask for a credit where their weather is shown, so weather looked up from a provider records which one it came from, and its credit is shown with it, linked to the provider.
The Weather Credits setting, under General Settings, puts the credits next to the weather (the default) or once in the page footer. Open-Meteo requires its credit next to its data, so it stays there either way.
Providers without a credit requirement have no credit: the US National Weather Service, whose data is public domain, and HERE, Visual Crossing, weatherstack and Pirate Weather, where no requirement was found. Weather saved before version 6.1.0, or entered by hand, doesn't record a provider, so it has no credit.
Environment Canada's data is under the Open Government Licence – Canada, which asks for the source to be credited.
OpenWeather and Weatherbit only require their credit on free plans. The plugin can't tell which plan a key is on, so it shows them; a site on a paid plan can remove them, or change any credit, with the `sloc_weather_credit` filter.

= How do I protect my map API keys? =

For most map providers, the map image is loaded directly from the provider, so the API key is part of the image link and anyone viewing the page can read it.
The same key is often used for geocoding, elevation, venues or weather, which your server requests itself.

To limit what someone can do with a visible key, Google, HERE, LocationIQ, MapQuest and Mapbox have an optional Map API Key setting. Use a separate key there that only allows static maps and is restricted to your site.
If it is empty, the provider's main key is used for maps too.

Don't restrict your main key to your site's domain. Requests your server makes don't come from a web page, so a domain restriction would block geocoding and the other lookups.

**Google:** Use a separate key restricted to the Maps Static API. Then set up URL signing:
* Copy the URL signing secret from the Secret Generator on the Credentials page of the Google Cloud console, in the same project as the map key, into the Google Maps URL Signing Secret setting.
* On the Quotas page for the Maps Static API, set "Unsigned requests per day" to 0, so Google refuses map links that weren't signed by your site.
* A signed link can't be changed to request a different map. Unlike a website restriction, signing still works where no referring page is sent, such as in feed readers and email.

**HERE:** HERE restricts keys by app, not by key, so create a second app for maps:
* In the HERE platform, open Access Manager, create a new app, and create an API key for it. Enter that key as the HERE Map API Key.
* Open the new app's Trusted domains tab, turn on "Enable Trusted domains", and enter your site's domain without a path, for example `example.com`. Changes can take up to 30 minutes to take effect.
* Leave trusted domains off for the app that holds your main HERE key, which your server uses for geocoding and weather.
* Maps may not load where no referring page is sent, such as some feed readers and email.

**Mapbox:** Mapbox uses its token only for maps, and for listing your own styles in the settings.
* Create a public token (starting `pk.`) with the default public scopes. Add URL restrictions for your site, for example `https://example.com`, and enter the token as the Mapbox Map API Key. Mapbox's default public token can't be restricted.
* Listing your own styles needs the `styles:list` scope, which only secret tokens (starting `sk.`) can have. Enter that token as the Mapbox API Key. It is only sent from your server. The plugin never puts a secret token in a map image, so if the Mapbox API Key is a secret token and the Mapbox Map API Key is empty, no Mapbox maps are shown.
* Mapbox checks URL restrictions against the referring page and refuses requests without one. Restricted maps may therefore not load in feed readers or email, or on a site whose referrer policy is `no-referrer` or `same-origin`.

**LocationIQ:** LocationIQ tokens can be restricted by HTTP referrer (for maps) or by IP address (for your server's lookups).
* If your account allows a second token, enter it as the LocationIQ Map API Key and restrict it by HTTP referrer to your site, for example `example.com/*`. Restrict the main token to your server's IP address.
* Free accounts get one token, used for both maps and geocoding. LocationIQ doesn't say whether one token with both kinds of restriction accepts requests matching either, so leave it unrestricted, or test both maps and lookups after restricting it.

**TomTom:** TomTom uses its key only for maps. In the TomTom developer dashboard, allow the key only the Map Display API, so a copied key can't be used for other products.
TomTom's domain whitelist works through CORS, which browsers don't apply to images, so it may not stop others from using a copied key.

**MapQuest:** MapQuest doesn't document a way to restrict a key. Use a separate key as the MapQuest Map API Key, so you can replace it without changing geocoding if it is misused.

**Stadia Maps:** With an API key, Stadia maps are served from your site, so the key is never shown to visitors. If you turn off Serve Maps From This Site, leave the Stadia Maps API Key empty and add your site's domain under Authentication in Stadia's client dashboard instead: maps then load from Stadia without a key in the page. As with other domain restrictions, maps may not load where no referring page is sent.

**Geoapify:** Geoapify maps are served from your site, so the key is never shown to visitors and doesn't need a separate map key. Every Geoapify request comes from your server, so you can restrict the key to your server's IP address:
* In Geoapify's MyProjects, open your project's API Keys section and add your server's outgoing IP address to the key's allowed IP addresses.
* Your host can tell you the outgoing IP address. If your server connects over both IPv4 and IPv6, add both. If the address changes, for example on some shared hosting, update the setting, or leave the key unrestricted.

= What is the custom map provider? =

The custom map provider is an endpoint you provide yourself. You can install and use the repository [here](https://github.com/dshanske/Static-Maps-API-PHP), which is a fork of a project by [Aaron Parecki](https://aaronparecki.com).
It needs version 1.0.0 or later of the server. All the commercial static map APIs used assemble the pieces of a map from public tile servers, which
is what this code does. While the code allows for multiple tile sources, please ensure compliance with their licenses. At the least, most of them require attribution.

The Static Map Style setting lists the providers and styles your server offers, read from its query API. If the server can't be reached, it lists the ones every server offers without keys,
and the settings page says why. Styles chosen before version 6.1.0 of this plugin are converted to their new names. The Stamen styles no longer exist, so they use your server's default map.

Maps that need a key on your server (such as CARTO or Stadia, once the server has their API keys) need a client. Run `php bin/clients.php add <name>` on the server, then enter the name as the Custom Static Map Client ID
and the signing secret it shows as the Custom Static Map Signing Secret, on the API settings page. Map URLs are then signed: they carry the client name and a signature, never the secret, so they are safe to publish.
The plain client key also works (Custom Static Map Client Key), but it appears in every map image URL, so anyone who views a page can copy it.

On map archive pages, the plugin asks the server where it draws each marker, and stores the answer, so each marker can link to its post.

The server fetches every tile a map covers, so its work grows with the image size. The plugin asks it for maps no larger than the server's own limit (1280 by 1280 pixels unless the server changes it), shrinking larger maps to fit.
To lower this, define `SLOC_STATICMAP_MAX_WIDTH` and `SLOC_STATICMAP_MAX_HEIGHT` in wp-config.php, or use the `sloc_staticmap_max_size` filter.

= What is the custom weather provider? =

This allows you to use a custom endpoint that returns current weather conditions in json, using the property names used by this plugin, which can be found outlined in the class-weather-provider.php file. The configuration for this
takes a URL and an ID. So you could run a script that generates a static page anywhere from any source with this data.

= Which providers support historic weather data? =

Currently, only Visual Crossing supports historic weather data on their basic plan. Meteostat consists solely of historical data and has no current data.

= What are the fallback weather providers? =

Weather is looked up with up to three providers, tried in order: the weather provider, then the 2nd and 3rd under Fallback Weather Providers, for example your own station, a free service and a paid one. The first that has weather is used, and its credit is shown.

A provider is skipped, without asking it, where it can't have weather: outside the country it covers, such as the National Weather Service outside the US; more than 10 km from your stations, for Custom Station and WeeWX; or for a time it has no weather for. If a provider fails with an error, the next one is tried.

Geocoding, elevation and venues work the same way, with Fallback Geo Providers, Fallback Elevation Providers and Fallback Venue Providers, tried when a provider finds nothing or fails, such as when its quota runs out. Venues are searched for within the Venue Search Radius and listed nearest first. Maps have no fallback, because the map provider decides how your maps look.

= Can I enter a Plus Code? =

Yes. [Plus Codes](https://maps.google.com/pluscodes/) are the short codes for places that Google Maps shows, such as 87F6XR2P+WH. Enter one in the Plus Code field of the Location box and choose Lookup, and it replaces the location. A short code, such as XR2P+WH, needs the town or city after it, such as XR2P+WH Philadelphia. Plus Codes are decoded on your site; the town is looked up with your geocoding provider.
= Can I get my locations as GeoJSON? =

Yes. Add geojson to any feed address: /feed/geojson/ for your latest posts, /location/philadelphia/feed/geojson/ for a location's posts, or a post's own feed, such as /2024/07/01/a-post/feed/geojson/, for that post. Without pretty permalinks, add ?feed=geojson instead. Each post with a public location is a point, with its title, link, date and address. Posts whose location isn't public are left out. Pages with a feed link to it, so mapping tools can find it. The sloc_geojson and sloc_geojson_feature filters change what is included.

= How do I know when a provider stops working? =

When a provider fails with an error you can fix, such as a missing or rejected API key, an exhausted quota, or one of your stations that can't be read or has stopped updating, administrators see a notice on the Dashboard and the Simple Location settings, and it is listed in Tools → Site Health. The notice can be dismissed, and the error clears by itself the next time that provider works.

To be notified elsewhere, such as on your phone, hook the `sloc_provider_error` and `sloc_provider_error_resolved` actions, which receive a one-line message.

= How do I show the location in my theme? =

By default, the location, with the weather, is added after each post's content, and the map after the content of single posts, or of every post with "Show Maps on Home and Archive Pages". A comment's location is added after its text.

To place the location yourself, declare support for it in your theme's functions.php. The location is then no longer added to the content:

`add_theme_support( 'simple-location' );`

Then show it in your templates, where you want it:

`if ( function_exists( 'get_post_location' ) ) { echo get_post_location(); }`

get_post_location() returns the location's HTML, already escaped, and nothing for a location that isn't shown. It respects the post's location visibility: a protected location shows its description without coordinates, and a private one shows nothing to visitors. Its second argument changes what is shown, for example `get_post_location( null, array( 'weather' => false ) )` without the weather. The simple_location_display_defaults filter changes the defaults everywhere. Similar functions show the location of comments, users and terms: get_comment_location(), get_user_location() and get_term_location().

get_post_map() returns the map, for a public location. The map is still added to the content with theme support; to place it yourself, remove it after the plugin adds it:

`add_action( 'init', function () { remove_filter( 'the_content', array( 'Geo_Data', 'content_map' ), 11 ); }, 2 );`

The location is wrapped in a div with the class sloc-display, for styling. get_post_geodata() and get_post_weatherdata() return the raw data. Check the 'visibility' they return before showing coordinates.

= How do my profile location and my posts' locations relate? =

Each user has a location in their profile, set under Users > Profile or with the user REST endpoint (see "How can I update the location of my user profile?"). The Last Seen widget shows it, and the weather widget can show the weather there.

Two settings connect it with posts:

* "Update the Author with Posts Location when Saving a Post", on by default: when you publish a new post with a location, within ten minutes of its date, the post's location becomes your profile location, unless the post's location is private. Editing or publishing older posts doesn't change it.
* The location provider "Set Location from Author Profile": looking up the location in the editor uses your profile location, as do Micropub posts with automatic lookup, and the bulk lookup of posts without one.

With both, a post takes your profile location, and publishing it saves the same location back, so your profile only changes when you edit it, or change a new post's location before publishing. To keep your profile as a fixed default location, such as home, turn the first setting off. With another location provider, such as your browser's location or Compass, the first setting keeps your profile up to date with where you last posted from.

= Is this compatible with the WordPress mobile apps? =

Simple Location uses WordPress Geodata to store location, as does the WordPress app. So setting location with the app should allow it to be displayed by Simple Location. The only major difference
is that whether or not a location is public is set with either 0 for private or 1 for public. The spec implemented states a non-zero number is considered public. This plugin adds the option of 2,
also known as protected, which shows a textual description of the location but does not display a map or geographic coordinates.

However, now that the WordPress apps no longer support location, this may be moot.

= The Location Icon does not retrieve my location. =

Chrome Users: Retrieves the location using the HTML5 geolocation API(available in your browser) will not work on Chrome if your website is not secure(https). This is a Chrome decision to ensure safe control of personal data.

When your browser doesn't give its location, because it can't or you didn't allow it, the address you typed in the location box is looked up instead. Type an address, or the latitude and longitude, and look it up again.

You can take advantage of the other built-in location providers, for example, one uses the location of the user or create your own location provider as a separate plugin.

= How can I update the location of my user profile? =

You can do so under your user profile or alternatively update using a REST API endpoint. Posting to `/wp-json/sloc_geo/1.0/user` updates the user associated with the credentials you provide to the REST API. Send the location in one of these ways:

* the `latitude` and `longitude` parameters, with `altitude`, `speed` and `heading` if you have them;
* a `geo` parameter with a geo URI, such as `geo:45.01,-75.44,20`;
* a JSON body with a GeoJSON Point, or a Feature with a Point geometry and `speed` and `heading` properties, on its own or as `{"locations": …}`.

A `visibility` parameter (public, private or protected) can come with any of them, or alone. Without an altitude, it is looked up, and the address is looked up from the coordinates.

= How can I access the location or weather data on the frontend? =

There are REST API endpoints to retrieve the data so it can be used in the admin under the namespace `/wp-json/sloc_geo/1.0`:
* /timezone which will return timezone data for a latitude/longitude or airport code
* /weather which will return the weather for a latitude/longitude or station ID
* /geocode which will return the address information for a latitude/longitude and optionally add the weather
* /lookup which will return the current location for a user based on the location provider
* /airport allows search for an airport by IATA code, ICAO code, or Municipality. Used for the airport location search
* /map which will return static map data for the provided latitude and longitude

= What is Compass? =

[Compass](https://github.com/aaronpk/Compass) is a GPS tracking server that stores data in flat files. The instructions for installation are available in the GitHub repository. GPS
data can be sent to it from iOS or Android devices using various apps.

= How do I use Dawarich or GeoPulse? =

[Dawarich](https://dawarich.app/) and [GeoPulse](https://github.com/tess1o/geopulse) are self-hosted location history services, which receive your location from apps such as OwnTracks or Overland.
Choose one as the Location Provider in Settings, then each user enters their server's URL and API key on their profile. Dawarich's key is on its Account page. GeoPulse needs version 2.1.0 or later; create an API token with only the read scope.
The key is never shown again after it is saved, and only the user, or someone who can edit them, can use their account.
Your server must be reachable at a public address: WordPress refuses requests to local and private network addresses.

= How do I add a map of several places to a post or page? =

Use the `[sloc_map]` shortcode. It draws a map with a marker at each place, in the style of the archive maps, and each marker links to its post or image where the map provider allows.
* `[sloc_map]` maps the post's attached images that have GPS data.
* `[sloc_map ids="12,34,56"]` maps those posts or images.
* `[sloc_map points="39.9524,-75.1636|40.7128,-74.0060"]` maps the given latitude and longitude points.
* `[sloc_map track="_p~iF~ps|U_ulLnnqC"]` draws a route, given as an encoded polyline, as a line. `markers="ends"` (the default) marks its start and end, and `markers="none"` draws only the line. Long routes are simplified to 200 points, keeping their shape.
* `width` and `height` set the map's size in pixels.

To make a route map without writing the polyline yourself, use Tools > Location Track. Choose a start and end time, and it gets your route from your location provider (Compass, Dawarich or GeoPulse), shows a preview of the map, and gives you the shortcode to paste. Points inside venues whose posts are private or protected, such as home, are left out.

Only published posts and images with a public location are drawn, and images take the visibility of the post they are attached to. At most 100 places are drawn, and if there are none, nothing is shown.

= Can I see the weather of an archive? =

Yes. Like `/map/` for a map, add `/temperature/`, `/rain/`, `/snow/` or `/weather/` to any archive, such as a year, category, author or location: for example `/2025/temperature/`. For a location, use `/temperature/location/pennsylvania/` or `/rain/location/pennsylvania/`, as with `/map/location/pennsylvania/`.
* `/temperature/` shows a chart of the temperatures of the archive's posts, in your site's units, followed by a list of them.
* `/rain/` and `/snow/` show a bar chart of the rain or snow in the hour before each post, in millimeters or inches, followed by a list of them. Posts whose weather recorded none are included, with no bar.
* `/weather/` lists each post with its weather icon, summary and temperature.

Only posts with weather are shown, and only when their location isn't private, as their weather isn't shown otherwise. All are drawn without JavaScript.
A theme can change any view with its own `temperature-archive.php`, `rain-archive.php`, `snow-archive.php` or `weather-archive.php`, as it can the map view with `map-archive.php`.

= How can I show a list of posts tagged with a location? =

You can filter any query or archive by adding `?geo={all|public|text}` to it to show only public posts with location. Adding /geo/all to the homepage or archive pages should also work

= How can I see a map of all the locations in an archive page? =

If you add /map to any archive URL, for example, example.com/2019/map it will return a template with a map view of that archive. It uses a default template built into the theme.
Being as styling this would not be customized to your theme, you can add a map-archive.php file to your theme to customize this. For taxonomies, use /map/taxonomy/term.

Each marker on the map links to its post, with every map provider except TomTom, whose maps have no markers. The plugin chooses the area the map shows so it knows where each marker is drawn, and the links need no scripts in the page.

= JetPack offers Location Display, why do I need this? =

JetPack only began offering location display in 2017, 3 years after this plugin was created. This plugin disables their implementation as it created conflicts.

They do not offer the features this plugin does and their goal is a minimal implementation.

= Why am I seeing location on private posts with the notation Hidden? =

This appears to users who can read private posts when logged in. It does not appear when not logged in. The same applies to the location taxonomy. If you are not logged in, or
are logged in and don't have the read_private_posts permission, you won't see posts with private locations.

= How can I report issues or request support? =

The Development Version as well as support can be found on [Github](https://github.com/dshanske/simple-location).

= How can I add support for ___ ? =

Simple Location has the concept of Providers. Providers are an abstract class that you can implement to take information from one format into the one Simple Location understands.
The plugin offers providers for:
* Geolocation - Looking up an address from coordinates or vice versa
* Location - By default this uses your browser to lookup your location but you can alternatively tap into a service to get your current location, perhaps from your phone
* Weather - Retrieves weather based on location or station ID
* Map - Provides maps for display

= Where does the bundled data come from? =

The plugin includes data files in its `data` folder, so these lookups don't need an online service:
* Weather stations for the Meteostat provider: [Meteostat](https://meteostat.net), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Only each station's location, name and the dates it has hourly data for are included.
* Airports: [OurAirports](https://ourairports.com/data/), public domain.
* Airlines: [OpenFlights](https://openflights.org/data), licensed under the [Open Database License](https://opendatacommons.org/licenses/odbl/1-0/).
* Countries and regions: ISO 3166 data from [php-isocodes](https://github.com/sokil/php-isocodes).

Developers can refresh the station, airport and airline files with `composer update-data`.

= What icons are used here? =

* [Weather Icons](https://erikflowers.github.io/weather-icons/) are used for most of the weather options.
* [Font Awesome](https://fontawesome.com/) is used for the transportation icons.
* [Simple Icons](https://simpleicons.org/) supplies the transportation brand icons.

== Upgrade Notice ==

= 6.1.1 =

Track maps made with Tools > Location Track in 6.1.0 show text instead of a map; make their shortcodes again. The GeoPulse provider needs GeoPulse 2.1.0 or later.

= 6.1.0 =

The Met Office provider has been removed; if you used it, choose another weather provider. Venues found with Google, HERE, TomTom, Foursquare or Stadia Maps now keep only their identifier and links, as those providers' terms require. Many new providers, fallback providers, and venue and location merging.

= 6.0.0 =

Requires PHP 7.4, WordPress 6.2 or ClassicPress 2.7. The Bing providers and MapQuest elevation have been removed because their services were retired; if you used one, choose another provider in Settings. Many fixes to location visibility and permissions.

== Changelog ==

= 6.1.1 ( 2026-10-25 ) =
* Fix track maps showing the route as text: [sloc_map track] shortcodes from Tools > Location Track now escape the route
* Say when a GeoPulse server is older than 2.1.0, or where it found nothing, instead of "Resource not found"
* Label archive map markers with the post's venue or location instead of its title
* Show every location history account, such as Dawarich and GeoPulse, on the user profile, with the one in use open

= 6.1.0 ( 2026-10-24 ) =
* Add weather providers: Open-Meteo, MET Norway, Environment Canada and WeeWX stations (beta); remove the Met Office provider
* Add Photon geocoding, Open Topo Data elevation, Stadia Maps and TileServer GL maps, and Dawarich and GeoPulse location providers; set your own Nominatim server
* Try fallback weather, geocoding, elevation and venue providers in order, and tell administrators when a provider fails
* Credit weather providers where their weather is shown
* Calculate wind chill, heat index, dew point or humidity, and cloud cover when a provider doesn't give them, and estimate the sky from a station's sunlight
* Custom static maps: choose provider and style, sign URLs, store images on the site, and set format, markers, scale bar, overlays and credit
* Every map provider: image format and marker settings, high-density archive maps, clickable archive markers, map style families and dark mode
* Add the [sloc_map] shortcode for maps of several places and tracks, and Tools > Location Track
* Add temperature and weather views of archives, and a GeoJSON feed of post locations
* Venues: search within a set radius, nearest first, with shared categories; new providers Overpass, Geoapify, HERE, TomTom, Pelias, Stadia Maps and Foursquare; Google uses Places API (New)
* Venues: match check-ins to existing venues by identifier, address or name, keep only what each provider's terms allow, show and edit a venue's website and other pages, suggest venue types, and cache searches
* Merge duplicate venues and locations from their lists
* Let a venue set the visibility of posts made there, and add an about URL to locations
* Enter Plus Codes in the location box, and accept GeoJSON and geo URIs on the user location endpoint
* Look up the typed address when the browser doesn't give its location
* Show weather in the editor at the precision stations report, keeping stored values
* Fix clearing the location in the editor, keep the chosen venue after a lookup, and keep the loading spinner until a lookup ends
* Save and read a latitude, longitude, altitude, speed or heading of 0
* Fix distances for identical points, keep New York, NY, and name a locality and region once

= 6.0.0 ( 2026-10-18 ) =
* Requires ClassicPress 2.7; tested with WordPress 7.1
* Remove the Bing providers and MapQuest elevation, whose services were retired, and stop calling retired MapQuest hosts
* Move HERE weather and maps to the current v3 APIs and fix Weatherstack, Pirate Weather, NWS, Meteostat, Visual Crossing, Xweather, GeoNames, Geoapify, Google, LocationIQ, Nominatim and Pelias parsing
* Add optional map-only API keys for Google, HERE, LocationIQ and MapQuest, Google URL signing, and serve Geoapify map images from the site
* Keep hidden locations out of ActivityPub, use the visibility a Micropub client sends, and honor location visibility in widgets, photos and archives
* Let authors and commenters manage the location of their own content, and check permissions on bulk actions and lookups
* Validate coordinates and location fields when saving, and from Micropub and Webmentions
* Use the post's timezone in sloc_get_comment_datetime(), and ignore invalid timezones instead of failing
* Calculate sun and moon times for the local date and name moon phases correctly
* Use the fallback weather provider when a provider can't serve a time, and cache past weather by hour
* Keep readings of 0 and convert units correctly
* Find airports and weather stations from small sorted indexes instead of large data files, and refresh the airport data
* Fix the map archive fatal error when no map provider is set
* Fix widget forms, wrappers and errors, and keep service responses and credentials out of error messages
* Escape output late and sanitize request input
* Give static map images alternative text and keep them within provider limits
* Fix PHP 8 deprecations
* Deprecate ifset(), ifset_round() and Simple_Location_Plugin::load_textdomain()
* Document all classes, functions and hooks, and add a test suite for WordPress and ClassicPress

= 5.0.25 ( 2026-07-27 ) =
* Checked for compatibility with latest version
* Remove live ability to use development sites
* Lock airport and timezone lookup behind optional user login requirement
* Remove ability for bulk update if the user does not have permission to do so

= 5.0.24 ( 2026-02-25 ) =
* Fix issues with Last Seen Widget
* Refresh data
* Identify short circuit logic that was not triggering
* Fix variable naming
* Toggle srcset to false by default due load
* Update weather providers to latest APIs where possible
* Fix text domain warning error

Older releases are listed in [changelog.md](https://github.com/dshanske/simple-location/blob/trunk/changelog.md).
