<?php

add_action( 'init', array( 'Loc_View', 'init' ) );

// Location Display
class Loc_View {

	public static function init() {
		add_filter( 'comment_text', array( 'Loc_View', 'location_comment' ), 12, 2 );
		add_filter( 'the_content', array( 'Loc_View', 'content_map' ), 11 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( 'Loc_View', 'location_content' ), 12 );
		}
	}

	public static function get_icon( ) {
		// Substitute another svg sprite file
		$sprite = plugin_dir_url( __FILE__ ) . 'location.svg';
		return '<img class="icon-location" aria-label=' . __( 'Location: ', 'simple-location' ) . 'aria-hidden="true" src="' . $sprite . '" />';
	}

	public static function get_location($object = null, $args = array() ) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( ! isset( $loc ) || '0' === $loc['public'] ) {
			return null;
		}
		$defaults = array(
			'height' => null,
			'width' => null,
			'map_zoom' => null,
			'public' => get_option( 'geo_public' ),
			'icon' => true, // Show Location Icon
			'text' => false, // Show Description
			'description' => __( 'Location: ', 'simple-location' ),
			'wrapper-class' => 'sloc-display', // Class to wrap the entire location in
			'wrapper-type' => 'p' // HTML type to wrap the entire location in
		);
		$default = apply_filters( 'simple_location_display_defaults', $defaults );
		$args = wp_parse_args( $args, $defaults );
		$args = array_merge( $loc, $args );
		$map = Loc_Config::default_map_provider( $args );
		$wrap = '<%1$s class="%2$s">%3$s</%1$s>';
		$c = '';
		if ( $args['icon'] ) {
			$c .= self::get_icon();
		}
		if ( $args['text'] ) {
			$c .= $args['description'];
		}
		// 1 is full public
		if ( '1' === $loc['public'] ) {
			$c .= self::get_the_geo( $loc );
			$c .= '<a href="' . $map->get_the_map_url() . '">' . $loc['address'] . '</span></a>';
		} else {
			$c = $loc['address'];
		}
		return sprintf( $wrap, $args['wrapper-type'], $args['wrapper-class'], $c );
	}

	public static function get_map($object = null, $args = array() ) {
		$loc = WP_Geo_Data::get_geodata( $object );
		if ( isset( $loc ) && ( '1' === $loc['public'] ) ) {
			$map = Loc_Config::default_map_provider( array_merge( $loc, $args ) );
			return $map->get_the_map();
		}
		return '';
	}

	// Return marked up coordinates
	public static function get_the_geo( $loc, $display = false ) {
		if ( isset( $loc['latitude'] ) && isset( $loc['longitude'] ) ) {
			if ( $display ) {
				return sprintf('<span class="h-geo">
					<span class="p-latitude">%1$f</span>,
					<span class="p-longitude">%2$f</span></span>', $loc['latitude'], $loc['longitude'] );
			}
			else {
				return sprintf( '<span class="h-geo">
					<data class="p-latitude" value="%1$f"></data>
					<data class="p-latitude" value="%2$f"></data></span>', $loc['latitude'], $loc['longitude'] );
			}
		}
		return '';
	}

	public static function location_content($content) {
		$loc = self::get_location();
		if ( ! empty( $loc ) ) {
			$content .= $loc;
		}
		return $content;
	}

	public static function location_comment( $comment_text, $comment ) {
		$loc = self::get_location( $comment, array( 'text' => false, 'icon' => false ) );
		if ( ! empty( $loc ) ) {
			$comment_text .= $loc;
		}
		return $comment_text;
	}

	public static function content_map($content) {
		if ( is_single() ) {
			$content .= self::get_map();
		}
		return $content;
	}

} // Class Ends

function get_simple_location( $object = null, $args = array() ) {
	Loc_View::get_location( $object, $args );
}

?>
