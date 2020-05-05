<?php

class Loc_Timezone {

	public static function timezone_for_location( $lat, $lng, $date = false ) {
		$start = microtime( true );

		$tzfile = self::tz_data();

		$timezones = array();
		foreach ( $tzfile as $line ) {
			$tz       = explode( ' ', trim( $line ) );
			$distance = WP_Geo_Data::gc_distance( (float) $tz[0], (float) $tz[1], $lat, $lng );
			if ( $distance < 200000 ) {
				$timezones[] = array_merge( $tz, array( $distance ) );
			}
		}

		usort( $timezones, function( $a, $b ) {
			return $a[3] < $b[3] ? -1 : 1;
		});

		if ( count( $timezones ) > 0 ) {
			return new Timezone_Result( $timezones[0][2], $date );
		} else {
			return null;
		}
	}

	private static function tz_data() {
		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/tz_cities.txt';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'filesystem_error', "File doesn't exist" );
		}
		return file( $file );
	}
}
