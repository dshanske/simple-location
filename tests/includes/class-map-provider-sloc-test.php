<?php
/**
 * Fake map provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake map provider.
 */
class Map_Provider_Sloc_Test extends Map_Provider {

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = 'Test Map';
		$this->slug = 'sloc_test';
		parent::__construct( $args );
	}

	/**
	 * Styles.
	 *
	 * @return array
	 */
	public function get_styles() {
		return array();
	}

	/**
	 * Pixel densities.
	 *
	 * @return int[]
	 */
	public function get_scales() {
		return array( 1, 2 );
	}

	/**
	 * Static map URL built from the current settings.
	 *
	 * @return string
	 */
	public function get_the_static_map() {
		$url = sprintf( 'https://maps.example.com/static?c=%s,%s&z=%s&s=%sx%s', $this->latitude, $this->longitude, $this->map_zoom, $this->width, $this->height );
		if ( 1 < $this->scale ) {
			$url .= '@' . $this->scale . 'x';
		}
		return $url;
	}

	/**
	 * Archive map.
	 *
	 * @param array $locations Locations.
	 * @return string
	 */
	public function get_archive_map( $locations ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by the parent signature.
		return '';
	}

	/**
	 * Link to the location.
	 *
	 * @return string
	 */
	public function get_the_map_url() {
		return sprintf( 'https://maps.example.com/?c=%s,%s', $this->latitude, $this->longitude );
	}

	/**
	 * Map markup.
	 *
	 * @param boolean $static Static.
	 * @return string
	 */
	public function get_the_map( $static = false ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by the parent signature.
		return '<img src="' . esc_url( $this->get_the_static_map() ) . '" />';
	}
}
