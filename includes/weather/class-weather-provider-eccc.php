<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using Environment and Climate Change Canada's city page weather, https://api.weather.gc.ca/.
 *
 * Environment Canada reports the current conditions for hundreds of Canadian cities and towns, from the nearest
 * weather station, and needs no key. Its data is licensed under the Open Government Licence – Canada, which asks for
 * credit.
 *
 * @since 6.1.0
 */
class Weather_Provider_ECCC extends Weather_Provider {

	/**
	 * City page weather endpoint, an OGC API collection.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const ENDPOINT = 'https://api.weather.gc.ca/collections/citypageweather-realtime/items';

	/**
	 * Furthest city, in meters, whose conditions are used.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_DISTANCE = 100000;

	/**
	 * Half the sides of the boxes searched for cities, in degrees: a near one first, then a wider one.
	 *
	 * City pages come with their forecasts, so the search starts small.
	 *
	 * @since 6.1.0
	 * @var float[]
	 */
	const SEARCH = array( 0.25, 1.0 );

	/**
	 * Environment Canada's current condition icon codes and the plugin's condition codes.
	 *
	 * See current_conditions_icon_code_descriptions_e.csv in the City Page Weather documentation.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	const ICON_CODES = array(
		0  => 800, // Sunny.
		1  => 801, // Mainly sunny.
		2  => 802, // Partly cloudy.
		3  => 803, // Mostly cloudy.
		6  => 520, // Light rain shower.
		7  => 615, // Light rain shower and flurries.
		8  => 620, // Light flurries.
		10 => 804, // Cloudy.
		11 => 900, // Precipitation.
		12 => 500, // Rain or light rain.
		13 => 503, // Rain or heavy rain.
		14 => 511, // Freezing rain or drizzle.
		15 => 616, // Rain and snow.
		16 => 601, // Snow.
		17 => 623, // Flurries.
		18 => 602, // Heavy snow.
		19 => 211, // Thunderstorm.
		23 => 721, // Haze.
		24 => 741, // Fog or mist.
		25 => 625, // Drifting snow.
		26 => 626, // Ice crystals.
		27 => 624, // Hail or ice pellets.
		28 => 300, // Drizzle or snow grains.
		30 => 800, // Clear, at night.
		31 => 801, // Mainly clear.
		32 => 802, // Partly cloudy.
		33 => 803, // Mostly cloudy.
		36 => 520, // Light rain shower.
		37 => 615, // Light rain shower and flurries.
		38 => 620, // Light flurries.
		39 => 211, // Thunderstorm.
		40 => 625, // Blowing snow.
		41 => 781, // Funnel cloud.
		42 => 781, // Tornado.
		43 => 400, // Windy.
		44 => 711, // Smoke.
		45 => 761, // Dust or sand.
		46 => 233, // Thunderstorm with hail.
		47 => 211, // Thunderstorm with dust.
		48 => 772, // Waterspout.
	);

	/**
	 * Constructor
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Weather_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Environment Canada', 'simple-location' );
		$this->url         = 'https://weather.gc.ca/';
		$this->description = __( 'Environment and Climate Change Canada reports the current conditions for Canadian cities and towns, and needs no key. Only in Canada, and only current conditions. Licensed under the Open Government Licence – Canada.', 'simple-location' );
		$this->slug        = 'eccc';
		$this->region      = 'CA';
		parent::__construct( $args );
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @since 6.1.0
	 *
	 * @return boolean False: conditions are found by location, from the nearest city's page.
	 */
	public function is_station() {
		return false;
	}

	/**
	 * Returns whether the provider has weather for a time: only current weather, within an hour of now.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		return $this->is_current( $time );
	}

	/**
	 * Returns the language of the text asked for: French on French sites, otherwise English.
	 *
	 * @since 6.1.0
	 *
	 * @return string 'fr' or 'en'.
	 */
	public static function language() {
		return 0 === strpos( (string) get_locale(), 'fr' ) ? 'fr' : 'en';
	}

	/**
	 * Return array of current conditions.
	 *
	 * @since 6.1.0
	 *
	 * @param string|int|DateTime|null $time Time. Optional. Only now, or within an hour of it.
	 * @return array|WP_Error Conditions, or an error.
	 */
	public function get_conditions( $time = null ) {
		if ( ! $this->supports_time( $time ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return array();
		}
		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		$city = null;
		foreach ( self::SEARCH as $half ) {
			$json = $this->fetch_json(
				self::ENDPOINT,
				array(
					'f'     => 'json',
					'lang'  => self::language(),
					'limit' => 50,
					'bbox'  => implode(
						',',
						array(
							round( (float) $this->longitude - $half, 4 ),
							round( (float) $this->latitude - $half, 4 ),
							round( (float) $this->longitude + $half, 4 ),
							round( (float) $this->latitude + $half, 4 ),
						)
					),
				)
			);
			if ( is_wp_error( $json ) ) {
				return $json;
			}
			$city = $this->nearest_city( $json );
			if ( $city ) {
				break;
			}
		}
		if ( ! $city ) {
			return $this->no_results_error();
		}

		$return = $this->convert_data( $city['properties']['currentConditions'] );
		$return = $this->extra_data( $return );
		$this->set_cache( $return );

		if ( WP_DEBUG ) {
			$return['raw'] = $city;
		}
		return $return;
	}

	/**
	 * Returns the nearest city with current conditions, within MAX_DISTANCE.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $json Response: a GeoJSON feature collection of city pages.
	 * @return array|null City feature, or null if there is none.
	 */
	protected function nearest_city( $json ) {
		$nearest  = null;
		$distance = self::MAX_DISTANCE;
		foreach ( is_array( $json['features'] ?? null ) ? $json['features'] : array() as $feature ) {
			$point = $feature['geometry']['coordinates'] ?? null;
			if ( ! is_array( $point ) || ! is_numeric( $point[0] ?? null ) || ! is_numeric( $point[1] ?? null ) ) {
				continue;
			}
			// A city without a reading.
			if ( ! is_numeric( self::value( $feature['properties']['currentConditions']['temperature'] ?? null ) ) ) {
				continue;
			}
			$meters = geo_distance( $this->latitude, $this->longitude, $point[1], $point[0] );
			if ( $meters <= $distance ) {
				$nearest  = $feature;
				$distance = $meters;
			}
		}
		return $nearest;
	}

	/**
	 * Returns a value, which Environment Canada gives in English and French.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed  $field    Field, such as array( 'value' => array( 'en' => 8.8, 'fr' => 8.8 ) ) or
	 *                         array( 'value' => 2 ), or the language array itself.
	 * @param string $language Optional. Language. Default the site's; see language().
	 * @return mixed Value, or null.
	 */
	public static function value( $field, $language = null ) {
		if ( ! is_array( $field ) ) {
			return null;
		}
		// Some values, such as the icon code, are the same in both languages.
		if ( isset( $field['value'] ) && is_scalar( $field['value'] ) ) {
			return $field['value'];
		}
		$values   = is_array( $field['value'] ?? null ) ? $field['value'] : $field;
		$language = $language ? $language : self::language();
		return $values[ $language ] ?? $values['en'] ?? null;
	}

	/**
	 * Converts current conditions to the plugin's fields, in metric units.
	 *
	 * @since 6.1.0
	 *
	 * @param array $current Current conditions.
	 * @return array Conditions.
	 */
	public function convert_data( $current ) {
		$number = function ( $key ) use ( $current ) {
			$value = self::value( $current[ $key ] ?? null );
			return is_numeric( $value ) ? (float) $value : null;
		};
		$wind   = is_array( $current['wind'] ?? null ) ? $current['wind'] : array();
		$speed  = self::value( $wind['speed'] ?? null );
		$gust   = self::value( $wind['gust'] ?? null );
		$return = array(
			'temperature' => $number( 'temperature' ),
			'humidity'    => $number( 'relativeHumidity' ),
			'dewpoint'    => $number( 'dewpoint' ),
			'windchill'   => $number( 'windChill' ),
			'heatindex'   => $number( 'humidex' ),
			// Kilopascals.
			'pressure'    => is_numeric( $number( 'pressure' ) ) ? round( $number( 'pressure' ) * 10, 1 ) : null,
			// Kilometers per hour.
			'windspeed'   => is_numeric( $speed ) ? round( (float) $speed / 3.6, 2 ) : null,
			'windgust'    => is_numeric( $gust ) ? round( (float) $gust / 3.6, 2 ) : null,
			'winddegree'  => is_numeric( self::value( $wind['bearing'] ?? null ) ) ? (float) self::value( $wind['bearing'] ) : null,
			// Kilometers.
			'visibility'  => is_numeric( $number( 'visibility' ) ) ? $number( 'visibility' ) * 1000 : null,
		);
		$icon   = self::value( $current['iconCode'] ?? null );
		if ( is_numeric( $icon ) && isset( self::ICON_CODES[ (int) $icon ] ) ) {
			$return['code'] = self::ICON_CODES[ (int) $icon ];
		}
		$summary = self::value( $current['condition'] ?? null );
		if ( is_string( $summary ) && '' !== trim( $summary ) ) {
			$return['summary'] = sanitize_text_field( $summary );
		}
		return self::filter_weather( $return );
	}

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * The Open Government Licence – Canada asks for the source to be credited.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Weather data from Environment and Climate Change Canada', 'simple-location' ),
			'url'      => 'https://weather.gc.ca/',
			'adjacent' => true,
		);
	}
}
