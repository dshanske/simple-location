<?php
/**
 * Weather Station Widget
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;


/**
 * Adds widget to display weather station data with per-provider support
 */
class Sloc_Station_Widget extends Sloc_Weather_Widget {

	/**
	 * Parts of a station's day the widget can show, with whether each is shown when the widget doesn't say.
	 *
	 * @since 6.3.0
	 * @var bool[]
	 */
	const EXTRAS = array(
		'today'  => true,
		'trend'  => false,
		'rain'   => false,
		'webcam' => false,
		'alerts' => false,
	);

	/**
	 * Widget constructor
	 */
	public function __construct() {
		WP_Widget::__construct(
			'Sloc_Station_Widget',
			__( 'Weather Station', 'simple-location' ),
			array(
				'description'           => __( 'Adds current weather conditions', 'simple-location' ),
				'show_instance_in_rest' => true,
			)
		);
	}

	/**
	 * Widget data updater
	 *
	 * @param array $new_instance New widget settings.
	 * @param array $old_instance Previous widget settings.
	 *
	 * @return mixed widget data
	 */
	public function update( $new_instance, $old_instance ) {
		foreach ( $new_instance as $key => $value ) {
			if ( is_string( $value ) ) {
				$new_instance[ $key ] = trim( $value );
			}
		}
		return $new_instance;
	}

	/**
	 * Prints a select element of the weather providers that support stations.
	 *
	 * @since 3.5.0
	 *
	 * @param string $option Selected provider.
	 * @param string $name   Field name.
	 * @param string $id     Field ID. Unused.
	 */
	public static function provider_list( $option, $name, $id ) {
		$providers = Loc_Config::weather_providers( true );
		printf( '<select name="%1$s">', esc_attr( $name ) );
		foreach ( $providers as $key => $value ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $option, $key, false ), esc_html( $value['name'] ) );
		}
		echo '</select>';
		echo '<br /><br />';
	}

	/**
	 * Widget worker
	 *
	 * @since 6.0.0 Always closes the widget wrapper, and shows nothing without a station.
	 * @since 6.0.0 Only administrators see error details; visitors see a general message.
	 * @since 6.3.0 Shows the parts of a WeeWX station's day it is set to; see extras_markup().
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved widget settings.
	 *
	 * @output echoes current weather
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = wp_kses( apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ), Simple_Location_Plugin::kses_clean() );

		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore
		}

		if ( isset( $instance['cache_time'] ) ) {
			$cache_time = $instance['cache_time'];
		} else {
			$cache_time = null;
		}

		// Without a station there is nothing to show; weather_list() returns an empty string.
		$weather = null;
		if ( ! empty( $instance['station'] ) ) {
			$weather = Sloc_Weather_Data::get_weather_by_station( $instance['station'], $instance['provider'] ?? null, $cache_time ); // phpcs:ignore
			if ( is_wp_error( $weather ) ) {
				if ( 'http_request_failed' === $weather->get_error_code() ) {
					echo esc_html__( 'Unable to Connect to Station', 'simple-location' );
				} else {
					echo esc_html( self::error_message( $weather ) );
				}

				echo $args['after_widget']; // phpcs:ignore
				return;
			}
		}
		echo wp_kses( self::weather_list( $weather, 'fa-map', $instance ), Simple_Location_Plugin::kses_clean() );
		if ( is_array( $weather ) ) {
			echo wp_kses( self::extras_markup( $weather['station_extras'] ?? array(), $instance ), Simple_Location_Plugin::kses_clean() );
		}
		echo $args['after_widget']; // phpcs:ignore
	}

	/**
	 * Returns whether the widget shows a part of the station's day.
	 *
	 * @since 6.3.0
	 *
	 * @param array  $instance Widget settings.
	 * @param string $part     Part, a key of EXTRAS.
	 * @return bool Whether to show it.
	 */
	public static function shows( $instance, $part ) {
		$value = is_array( $instance ) ? ( $instance[ 'show_' . $part ] ?? null ) : null;
		return null === $value ? ( self::EXTRAS[ $part ] ?? false ) : 1 === (int) $value;
	}

	/**
	 * Returns a value with its unit, in the site's units, as text.
	 *
	 * @since 6.3.0
	 *
	 * @param float  $value    Value, in the plugin's metric units.
	 * @param string $property Weather property whose unit it has.
	 * @param bool   $imperial Whether to show imperial units.
	 * @param bool   $change   Optional. Whether it is a change, which converts without the scale's offset. Default false.
	 * @return string Value and unit, not escaped.
	 */
	protected static function measure( $value, $property, $imperial, $change = false ) {
		if ( $imperial ) {
			if ( 'temperature' === $property ) {
				$value = $change ? $value * 1.8 : Weather_Provider::celsius_to_fahrenheit( $value );
			} else {
				$value = Weather_Provider::metric_to_imperial( array( $property => $value ) )[ $property ];
			}
		}
		$names = Weather_Provider::get_names( $property, $imperial );
		$unit  = html_entity_decode( (string) ( $names['unit'] ?? '' ), ENT_QUOTES, 'UTF-8' );
		$value = number_format_i18n( (float) $value, 1 );
		return 0 === strpos( $unit, '°' ) || '%' === $unit ? $value . $unit : trim( $value . ' ' . $unit );
	}

	/**
	 * Returns a time as markup, in the site's time format.
	 *
	 * @since 6.3.0
	 *
	 * @param int    $timestamp Unix time.
	 * @param string $format    Optional. Date format. Default the time format.
	 * @return string Markup.
	 */
	protected static function time_markup( $timestamp, $format = '' ) {
		return sprintf(
			'<time datetime="%1$s">%2$s</time>',
			esc_attr( wp_date( DATE_W3C, $timestamp ) ),
			esc_html( wp_date( '' === $format ? get_option( 'time_format' ) : $format, $timestamp ) )
		);
	}

	/**
	 * Returns the parts of a station's day the widget shows: the day's high and low, the trend, the rain, the webcam
	 * and the alerts.
	 *
	 * @since 6.3.0
	 *
	 * @param array $extras   Station extras; see Weather_Provider_Weewx::parse_extras().
	 * @param array $instance Widget settings; see shows().
	 * @return string Markup, or an empty string with nothing to show.
	 */
	public static function extras_markup( $extras, $instance ) {
		if ( ! is_array( $extras ) || empty( $extras ) ) {
			return '';
		}
		$imperial = 'imperial' === get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		$items    = array();

		$today = self::shows( $instance, 'today' ) && is_array( $extras['today'] ?? null ) ? $extras['today'] : array();
		$lines = array(
			/* translators: 1: Temperature 2: Time. */
			'temperature_max' => array( 'temperature', __( 'High %1$s at %2$s', 'simple-location' ), __( 'High', 'simple-location' ) ),
			/* translators: 1: Temperature 2: Time. */
			'temperature_min' => array( 'temperature', __( 'Low %1$s at %2$s', 'simple-location' ), __( 'Low', 'simple-location' ) ),
			/* translators: 1: Wind speed 2: Time. */
			'windgust_max'    => array( 'windgust', __( 'Highest gust %1$s at %2$s', 'simple-location' ), __( 'Highest gust', 'simple-location' ) ),
		);
		foreach ( $lines as $key => $line ) {
			if ( ! isset( $today[ $key ] ) || ! is_numeric( $today[ $key ] ) ) {
				continue;
			}
			$value   = esc_html( self::measure( $today[ $key ], $line[0], $imperial ) );
			$time    = $today[ $key . '_time' ] ?? null;
			$text    = is_numeric( $time ) ? sprintf( esc_html( $line[1] ), $value, self::time_markup( (int) $time ) ) : esc_html( $line[2] ) . ' ' . $value;
			$items[] = sprintf( '<li class="sloc-station-%1$s">%2$s</li>', esc_attr( str_replace( '_', '-', $key ) ), $text );
		}

		$trend = self::shows( $instance, 'trend' ) && is_array( $extras['trend'] ?? null ) ? $extras['trend'] : array();
		$hours = (int) ( $trend['hours'] ?? 0 );
		if ( isset( $trend['pressure'] ) && is_numeric( $trend['pressure'] ) && $hours > 0 ) {
			$change = (float) $trend['pressure'];
			$amount = self::measure( abs( $change ), 'pressure', $imperial, true );
			if ( 0.0 === round( $change, 1 ) ) {
				$text = 1 === $hours
					? __( 'Pressure steady over the last hour', 'simple-location' )
					/* translators: %d: Hours. */
					: sprintf( _n( 'Pressure steady over the last %d hour', 'Pressure steady over the last %d hours', $hours, 'simple-location' ), $hours );
			} elseif ( $change > 0 ) {
				$text = 1 === $hours
					/* translators: %s: Pressure change. */
					? sprintf( __( 'Pressure rising, up %s in the last hour', 'simple-location' ), $amount )
					/* translators: 1: Pressure change 2: Hours. */
					: sprintf( _n( 'Pressure rising, up %1$s in the last %2$d hour', 'Pressure rising, up %1$s in the last %2$d hours', $hours, 'simple-location' ), $amount, $hours );
			} else {
				$text = 1 === $hours
					/* translators: %s: Pressure change. */
					? sprintf( __( 'Pressure falling, down %s in the last hour', 'simple-location' ), $amount )
					/* translators: 1: Pressure change 2: Hours. */
					: sprintf( _n( 'Pressure falling, down %1$s in the last %2$d hour', 'Pressure falling, down %1$s in the last %2$d hours', $hours, 'simple-location' ), $amount, $hours );
			}
			$items[] = sprintf( '<li class="sloc-station-pressure-trend">%s</li>', esc_html( $text ) );
		}
		if ( isset( $trend['temperature'] ) && is_numeric( $trend['temperature'] ) && $hours > 0 && 0.0 !== round( (float) $trend['temperature'], 1 ) ) {
			$change = (float) $trend['temperature'];
			$amount = self::measure( abs( $change ), 'temperature', $imperial, true );
			if ( $change > 0 ) {
				$text = 1 === $hours
					/* translators: %s: Temperature change. */
					? sprintf( __( 'Temperature up %s in the last hour', 'simple-location' ), $amount )
					/* translators: 1: Temperature change 2: Hours. */
					: sprintf( _n( 'Temperature up %1$s in the last %2$d hour', 'Temperature up %1$s in the last %2$d hours', $hours, 'simple-location' ), $amount, $hours );
			} else {
				$text = 1 === $hours
					/* translators: %s: Temperature change. */
					? sprintf( __( 'Temperature down %s in the last hour', 'simple-location' ), $amount )
					/* translators: 1: Temperature change 2: Hours. */
					: sprintf( _n( 'Temperature down %1$s in the last %2$d hour', 'Temperature down %1$s in the last %2$d hours', $hours, 'simple-location' ), $amount, $hours );
			}
			$items[] = sprintf( '<li class="sloc-station-temperature-trend">%s</li>', esc_html( $text ) );
		}

		$rain = self::shows( $instance, 'rain' ) && is_array( $extras['rain'] ?? null ) ? $extras['rain'] : array();
		if ( isset( $rain['storm'] ) && is_numeric( $rain['storm'] ) ) {
			$amount  = esc_html( self::measure( $rain['storm'], 'rain', $imperial ) );
			$items[] = sprintf(
				'<li class="sloc-station-storm">%s</li>',
				is_numeric( $rain['storm_start'] ?? null )
					/* translators: 1: Rain amount 2: Date and time the storm started. */
					? sprintf( esc_html__( 'Storm rain %1$s since %2$s', 'simple-location' ), $amount, self::time_markup( (int) $rain['storm_start'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
					/* translators: %s: Rain amount. */
					: sprintf( esc_html__( 'Storm rain %s', 'simple-location' ), $amount )
			);
		}
		if ( isset( $rain['last_24h'] ) && is_numeric( $rain['last_24h'] ) ) {
			/* translators: %s: Rain amount. */
			$items[] = sprintf( '<li class="sloc-station-rain-24h">%s</li>', esc_html( sprintf( __( 'Rain in the last 24 hours: %s', 'simple-location' ), self::measure( $rain['last_24h'], 'rain', $imperial ) ) ) );
		}

		if ( self::shows( $instance, 'alerts' ) && is_array( $extras['alerts'] ?? null ) ) {
			foreach ( $extras['alerts'] as $alert ) {
				if ( ! is_array( $alert ) || ! is_string( $alert['event'] ?? null ) ) {
					continue;
				}
				$text  = sprintf( '<strong>%s</strong>', esc_html( $alert['event'] ) );
				$text .= is_string( $alert['headline'] ?? null ) ? ': ' . esc_html( $alert['headline'] ) : '';
				$until = $alert['ends'] ?? $alert['expires'] ?? null;
				if ( is_numeric( $until ) ) {
					/* translators: %s: Date and time. */
					$text .= ' ' . sprintf( esc_html__( '(until %s)', 'simple-location' ), self::time_markup( (int) $until, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
				}
				$severity = is_string( $alert['severity'] ?? null ) ? sanitize_html_class( strtolower( $alert['severity'] ) ) : '';
				$items[]  = sprintf( '<li class="sloc-station-alert%1$s">%2$s</li>', '' === $severity ? '' : ' sloc-alert-' . $severity, $text );
			}
		}

		if ( self::shows( $instance, 'webcam' ) && is_string( $extras['webcam'] ?? null ) && '' !== esc_url( $extras['webcam'], array( 'http', 'https' ) ) ) {
			$items[] = sprintf(
				'<li class="sloc-station-webcam"><img src="%1$s" alt="%2$s" loading="lazy" /></li>',
				esc_url( $extras['webcam'], array( 'http', 'https' ) ),
				esc_attr__( 'The station\'s webcam', 'simple-location' )
			);
		}

		if ( empty( $items ) ) {
			return '';
		}
		return '<ul class="sloc-station-extras">' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Widget form
	 *
	 * @param array $instance Saved widget settings.
	 *
	 * @output displays the widget form
	 */
	public function form( $instance ) {
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title: ', 'simple-location' ); ?></label>
		<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ?? '' ); ?>" /></p>
		<p>
		<?php esc_html_e( 'Displays current weather at a weather station', 'simple-location' ); ?>
		</p>
		<p><?php self::provider_list( $instance['provider'] ?? '', $this->get_field_name( 'provider' ), $this->get_field_id( 'provider' ) ); ?>
			<p><label for="<?php echo esc_attr( $this->get_field_id( 'station' ) ); ?>"><?php esc_html_e( 'Station ID: ', 'simple-location' ); ?></label>
			<input type="text" size="7" name="<?php echo esc_attr( $this->get_field_name( 'station' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'station' ) ); ?>" value="<?php echo esc_attr( $instance['station'] ?? '' ); ?>" />
			</p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>"><?php esc_html_e( 'Show Astronomical Info: ', 'simple-location' ); ?></label>
		<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( 'showastro' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'showastro' ) ); ?>" type="checkbox" value="1" <?php checked( 1, $instance['showastro'] ?? 0 ); ?> />
		</p>
		<?php
		$extras = array(
			'today'  => __( 'Show the day\'s high and low, and highest gust: ', 'simple-location' ),
			'trend'  => __( 'Show the pressure and temperature trend: ', 'simple-location' ),
			'rain'   => __( 'Show the storm\'s rain and the last 24 hours\' rain: ', 'simple-location' ),
			'webcam' => __( 'Show the station\'s webcam: ', 'simple-location' ),
			'alerts' => __( 'Show weather alerts: ', 'simple-location' ),
		);
		foreach ( $extras as $part => $label ) :
			$field = 'show_' . $part;
			?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( $field ) ); ?>"><?php echo esc_html( $label ); ?></label>
			<input name="<?php echo esc_attr( $this->get_field_name( $field ) ); ?>" type="hidden" value="0" />
			<input name="<?php echo esc_attr( $this->get_field_name( $field ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( $field ) ); ?>" type="checkbox" value="1" <?php checked( self::shows( $instance, $part ) ); ?> />
		</p>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'These come from a WeeWX station\'s weewx-current-json file; the alerts need weewx-nws. Other stations have none of them.', 'simple-location' ); ?></p>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'cache_time' ) ); ?>"><?php esc_html_e( 'Cache Time: ', 'simple-location' ); ?></label>
			<input type="number" name="<?php echo esc_attr( $this->get_field_name( 'cache_time' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'cache_time' ) ); ?>" value="<?php echo esc_attr( $instance['cache_time'] ?? '' ); ?>" />
		</p>
		<?php
	}
}
