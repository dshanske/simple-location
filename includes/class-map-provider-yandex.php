<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using Yandex Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Yandex extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name         = __( 'Yandex', 'simple-location' );
		$this->slug         = 'Yandex';
		$this->max_map_zoom = 17;
		$this->max_width    = 650;
		$this->max_height   = 450;

		parent::__construct( $args );
		if ( $this->width > 650 ) {
			$this->width = 650;
		}
		if ( $this->height > 450 ) {
			$this->height = 450;
		}
	}



	public function get_styles() {
		return array();
	}

	// Return code for map
	public function get_the_static_map() {
		$map = add_query_arg(
			array(
				'lang' => get_locale(),
				'l'    => 'map',
				'll'   => sprintf( '%1$s,%2$s', $this->longitude, $this->latitude ),
				'size' => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'z'    => $this->map_zoom,
				'pt'   => sprintf( '%1$s,%2$s,pm2rdl', $this->longitude, $this->latitude ),
			),
			'https://static-maps.yandex.ru/1.x/'
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		if ( empty( $locations ) ) {
			return '';
		}

		$polyline = array();
		$markers  = array();
		foreach ( $locations as $location ) {
			$polyline[] = $location[1];
			$polyline[] = $location[0];
			$markers[]  = sprintf( '%1$s,%2$s,pm2rdl', $location[1], $location[0] );
		}

		$map = add_query_arg(
			array(
				'lang' => get_bloginfo( 'language' ),
				'l'    => 'map',
				'pl'   => implode( ',', $polyline ),
				'pt'   => implode( '~', $markers ),
				'size' => sprintf( '%1$s,%2$s', $this->width, $this->height ),

			),
			'https://static-maps.yandex.ru/1.x/'
		);
		return $map;
	}

	public function get_the_map_url() {
		$map = add_query_arg(
			array(
				'lang' => get_bloginfo( 'language' ),
				'll'   => sprintf( '%1$s,%2$s', $this->longitude, $this->latitude ),
				'size' => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'z'    => $this->map_zoom,
				'pt'   => sprintf( '%1$s,%2$s,pm2rdl', $this->longitude, $this->latitude ),
			),
			'https://yandex.com/maps'
		);
		return $map;
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}

}

register_sloc_provider( new Map_Provider_Yandex() );

