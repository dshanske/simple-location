<?php
/**
 * Weather Archives.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Weather_Archive', 'init' ) );

/**
 * Weather views of archives, like the map view: a temperature chart at `…/temperature/`, rain and snow charts at
 * `…/rain/` and `…/snow/`, and the weather of each post at `…/weather/`.
 *
 * Each view has a template, which a theme can override with a file of the same name.
 *
 * @since 6.1.0
 * @since 6.2.0 Adds the rain and snow views.
 */
class Sloc_Weather_Archive {

	/**
	 * Templates of the views, keyed by query variable.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 Adds the rain and snow views.
	 * @var string[]
	 */
	const VIEWS = array(
		'sloc_temperature' => 'temperature-archive.php',
		'sloc_rain'        => 'rain-archive.php',
		'sloc_snow'        => 'snow-archive.php',
		'sloc_weather'     => 'weather-archive.php',
	);

	/**
	 * The weather property each chart view shows, keyed by query variable.
	 *
	 * @since 6.2.0
	 * @var string[]
	 */
	const CHARTS = array(
		'sloc_temperature' => 'temperature',
		'sloc_rain'        => 'rain',
		'sloc_snow'        => 'snow',
	);

	/**
	 * Registers the hooks.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
	}

	/**
	 * Returns the weather view a query asks for.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Query|null $query Optional. Query. Default the main query.
	 * @return string Query variable of the view, or an empty string for none.
	 */
	public static function get_view( $query = null ) {
		if ( ! $query instanceof WP_Query ) {
			global $wp_query;
			$query = $wp_query;
		}
		if ( ! $query instanceof WP_Query ) {
			return '';
		}
		foreach ( array_keys( self::VIEWS ) as $view ) {
			if ( array_key_exists( $view, $query->query_vars ) ) {
				return $view;
			}
		}
		return '';
	}

	/**
	 * Limits a weather view to posts with weather whose location isn't private, oldest first, without pagination.
	 *
	 * A private location hides the post's weather too, so those posts are left out.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 The rain and snow views have posts with a rain or snow reading, including 0.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_query( $query ) {
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$view = self::get_view( $query );
		if ( '' === $view ) {
			return;
		}
		$weather = isset( self::CHARTS[ $view ] )
			? array(
				'key'     => 'weather_' . self::CHARTS[ $view ],
				'compare' => 'EXISTS',
			)
			: array(
				'relation' => 'OR',
				array(
					'key'     => 'weather_temperature',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'weather_code',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'weather_summary',
					'compare' => 'EXISTS',
				),
			);
		$query->set(
			'meta_query',
			array(
				'relation' => 'AND',
				$weather,
				array(
					'key'     => 'geo_public',
					'value'   => array( 1, 2 ),
					'type'    => 'numeric',
					'compare' => 'IN',
				),
			)
		);
		$query->set( 'posts_per_page', SLOC_PER_PAGE );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Uses the view's template for a weather view of an archive, from the theme if it has one.
	 *
	 * @since 6.1.0
	 *
	 * @param string $original_template The template that would normally be used.
	 * @return string Template path.
	 */
	public static function template( $original_template ) {
		$view = self::get_view();
		if ( '' === $view || ! is_archive() ) {
			return $original_template;
		}
		$template_name = self::VIEWS[ $view ];
		foreach ( array( get_theme_file_path(), plugin_dir_path( __DIR__ ) . 'templates/' ) as $directory ) {
			if ( sloc_file_exists( trailingslashit( $directory ) . $template_name ) ) {
				return trailingslashit( $directory ) . $template_name;
			}
		}
		return $original_template;
	}

	/**
	 * Returns the temperatures of the posts in the main query, for the temperature view.
	 *
	 * @since 6.1.0
	 *
	 * @return array[] Arrays with 'time' (Unix timestamp) and 'temperature' (Celsius), keyed by post ID, in the query's order.
	 */
	public static function get_temperatures() {
		return self::get_readings( 'temperature' );
	}

	/**
	 * Returns a weather reading of each post in the main query, for a chart view.
	 *
	 * Posts without the reading, without a date, or whose location is private are left out.
	 *
	 * @since 6.2.0
	 *
	 * @param string $property Weather property, such as 'temperature', 'rain' or 'snow'.
	 * @return array[] Arrays with 'time' (Unix timestamp) and the property (in metric units), keyed by post ID, in the
	 *                 query's order.
	 */
	public static function get_readings( $property ) {
		global $wp_query;
		$readings = array();
		if ( empty( $wp_query->posts ) ) {
			return $readings;
		}
		foreach ( $wp_query->posts as $post ) {
			$value = get_post_weatherdata( $post->ID, $property );
			$time  = get_post_timestamp( $post );
			if ( ! is_numeric( $value ) || ! $time || 'private' === get_post_geodata( $post->ID, 'visibility' ) ) {
				continue;
			}
			$readings[ $post->ID ] = array(
				'time'    => $time,
				$property => (float) $value,
			);
		}
		return $readings;
	}

	/**
	 * Returns whether temperatures are shown in Fahrenheit.
	 *
	 * @since 6.1.0
	 *
	 * @return bool
	 */
	public static function is_imperial() {
		return 'imperial' === get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
	}

	/**
	 * Returns a temperature in the site's units, rounded to one decimal place.
	 *
	 * @since 6.1.0
	 *
	 * @param float $celsius Temperature in Celsius.
	 * @return float Temperature.
	 */
	public static function display_temperature( $celsius ) {
		return round( self::is_imperial() ? Weather_Provider::celsius_to_fahrenheit( $celsius ) : (float) $celsius, 1 );
	}

	/**
	 * Returns a rain or snow amount in the site's units: millimeters to one decimal place, or inches to two.
	 *
	 * @since 6.2.0
	 *
	 * @param float $mm Amount in millimeters.
	 * @return float Amount.
	 */
	public static function display_precipitation( $mm ) {
		return self::is_imperial() ? round( Weather_Provider::mm_to_inches( (float) $mm ), 2 ) : round( (float) $mm, 1 );
	}

	/**
	 * Returns the unit of rain and snow amounts in the site's units.
	 *
	 * @since 6.2.0
	 *
	 * @return string 'in' or 'mm', translated.
	 */
	public static function precipitation_unit() {
		return self::is_imperial() ? __( 'in', 'simple-location' ) : __( 'mm', 'simple-location' );
	}

	/**
	 * Returns a line chart of temperatures over time, as an SVG drawn on the server.
	 *
	 * Each point has a title with its date and temperature, and the chart is labeled with the warmest and coldest
	 * temperatures and the first and last dates.
	 *
	 * @since 6.1.0
	 *
	 * @param array[] $temperatures Temperatures, from get_temperatures().
	 * @return string SVG markup, escaped, or an empty string without temperatures.
	 */
	public static function get_temperature_chart( $temperatures ) {
		if ( empty( $temperatures ) || ! is_array( $temperatures ) ) {
			return '';
		}
		$width   = 600;
		$height  = 240;
		$left    = 56;
		$right   = 16;
		$top     = 16;
		$bottom  = 32;
		$unit    = self::is_imperial() ? __( '°F', 'simple-location' ) : __( '°C', 'simple-location' );
		$format  = get_option( 'date_format' );
		$values  = array_map( array( __CLASS__, 'display_temperature' ), array_column( $temperatures, 'temperature' ) );
		$times   = array_column( $temperatures, 'time' );
		$min     = min( $values );
		$max     = max( $values );
		$first   = min( $times );
		$last    = max( $times );
		$t_range = max( 1, $last - $first );
		$v_range = max( 1, $max - $min );

		$points = array();
		$dots   = array();
		foreach ( array_values( $temperatures ) as $index => $temperature ) {
			$x        = ( 1 === count( $temperatures ) ) ? $left + ( $width - $left - $right ) / 2 : $left + ( $temperature['time'] - $first ) / $t_range * ( $width - $left - $right );
			$y        = $top + ( $max - $values[ $index ] ) / $v_range * ( $height - $top - $bottom );
			$points[] = sprintf( '%1$s,%2$s', round( $x, 1 ), round( $y, 1 ) );
			$dots[]   = sprintf(
				'<circle cx="%1$s" cy="%2$s" r="3"><title>%3$s</title></circle>',
				esc_attr( round( $x, 1 ) ),
				esc_attr( round( $y, 1 ) ),
				/* translators: 1: Date 2: Temperature 3: Unit */
				esc_html( sprintf( __( '%1$s: %2$s%3$s', 'simple-location' ), wp_date( $format, $temperature['time'] ), $values[ $index ], $unit ) )
			);
		}

		$label = sprintf(
			/* translators: 1: Number of posts 2: Lowest temperature 3: Highest temperature 4: Unit */
			_n( 'Temperature of %1$s post, from %2$s%4$s to %3$s%4$s', 'Temperatures of %1$s posts, from %2$s%4$s to %3$s%4$s', count( $values ), 'simple-location' ),
			number_format_i18n( count( $values ) ),
			$min,
			$max,
			$unit
		);
		$text  = '<text x="%1$s" y="%2$s" text-anchor="%3$s">%4$s</text>';
		return sprintf(
			'<svg class="sloc-temperature-chart" viewBox="0 0 %1$s %2$s" role="img" aria-label="%3$s"><title>%3$s</title>%4$s%5$s<polyline fill="none" stroke="currentColor" stroke-width="2" points="%6$s" />%7$s</svg>',
			esc_attr( $width ),
			esc_attr( $height ),
			esc_attr( $label ),
			sprintf( $text, $left - 8, $top + 4, 'end', esc_html( $max . $unit ) ) . sprintf( $text, $left - 8, $height - $bottom, 'end', esc_html( $min . $unit ) ),
			sprintf( $text, $left, $height - 8, 'start', esc_html( wp_date( $format, $first ) ) ) . sprintf( $text, $width - $right, $height - 8, 'end', esc_html( wp_date( $format, $last ) ) ),
			esc_attr( implode( ' ', $points ) ),
			implode( '', $dots )
		);
	}

	/**
	 * Returns a bar chart of rain or snow amounts over time, as an SVG drawn on the server.
	 *
	 * Each bar has a title with its date and amount, and the chart is labeled with the largest amount and the first
	 * and last dates. A reading of 0 has no bar.
	 *
	 * @since 6.2.0
	 *
	 * @param array[] $readings Readings, from get_readings().
	 * @param string  $property 'rain' or 'snow'.
	 * @return string SVG markup, escaped, or an empty string without readings.
	 */
	public static function get_precipitation_chart( $readings, $property ) {
		if ( empty( $readings ) || ! is_array( $readings ) || ! in_array( $property, array( 'rain', 'snow' ), true ) ) {
			return '';
		}
		$width   = 600;
		$height  = 240;
		$left    = 56;
		$right   = 16;
		$top     = 16;
		$bottom  = 32;
		$unit    = self::precipitation_unit();
		$format  = get_option( 'date_format' );
		$values  = array_map( array( __CLASS__, 'display_precipitation' ), array_column( $readings, $property ) );
		$times   = array_column( $readings, 'time' );
		$max     = max( $values );
		$first   = min( $times );
		$last    = max( $times );
		$t_range = max( 1, $last - $first );
		$plot_w  = $width - $left - $right;
		$plot_h  = $height - $top - $bottom;
		$bar     = max( 2, min( 12, $plot_w / count( $values ) * 0.6 ) );

		$bars = array();
		foreach ( array_values( $readings ) as $index => $reading ) {
			// Bar centers are inset by half a bar, so the first and last bars stay inside the plot.
			$x      = ( 1 === count( $readings ) ) ? $left + $plot_w / 2 : $left + $bar / 2 + ( $reading['time'] - $first ) / $t_range * ( $plot_w - $bar );
			$h      = ( $max > 0 ) ? $values[ $index ] / $max * $plot_h : 0;
			$bars[] = sprintf(
				'<rect x="%1$s" y="%2$s" width="%3$s" height="%4$s"><title>%5$s</title></rect>',
				esc_attr( round( $x - $bar / 2, 1 ) ),
				esc_attr( round( $top + $plot_h - $h, 1 ) ),
				esc_attr( round( $bar, 1 ) ),
				esc_attr( round( $h, 1 ) ),
				/* translators: 1: Date 2: Amount 3: Unit */
				esc_html( sprintf( __( '%1$s: %2$s %3$s', 'simple-location' ), wp_date( $format, $reading['time'] ), $values[ $index ], $unit ) )
			);
		}

		$count = count( $values );
		$label = ( 'rain' === $property )
			? sprintf(
				/* translators: 1: Number of posts 2: Largest amount 3: Unit */
				_n( 'Rainfall of %1$s post, up to %2$s %3$s', 'Rainfall of %1$s posts, up to %2$s %3$s', $count, 'simple-location' ),
				number_format_i18n( $count ),
				$max,
				$unit
			)
			: sprintf(
				/* translators: 1: Number of posts 2: Largest amount 3: Unit */
				_n( 'Snowfall of %1$s post, up to %2$s %3$s', 'Snowfall of %1$s posts, up to %2$s %3$s', $count, 'simple-location' ),
				number_format_i18n( $count ),
				$max,
				$unit
			);
		$text = '<text x="%1$s" y="%2$s" text-anchor="%3$s">%4$s</text>';
		return sprintf(
			'<svg class="sloc-precipitation-chart sloc-%1$s-chart" viewBox="0 0 %2$s %3$s" role="img" aria-label="%4$s"><title>%4$s</title>%5$s%6$s<line x1="%7$s" y1="%8$s" x2="%9$s" y2="%8$s" stroke="currentColor" />%10$s</svg>',
			esc_attr( $property ),
			esc_attr( $width ),
			esc_attr( $height ),
			esc_attr( $label ),
			sprintf( $text, $left - 8, $top + 4, 'end', esc_html( $max . ' ' . $unit ) ) . sprintf( $text, $left - 8, $height - $bottom, 'end', esc_html( '0 ' . $unit ) ),
			sprintf( $text, $left, $height - 8, 'start', esc_html( wp_date( $format, $first ) ) ) . sprintf( $text, $width - $right, $height - 8, 'end', esc_html( wp_date( $format, $last ) ) ),
			esc_attr( $left ),
			esc_attr( $top + $plot_h ),
			esc_attr( $width - $right ),
			implode( '', $bars )
		);
	}
}
