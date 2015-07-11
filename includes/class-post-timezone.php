<?php
// Overrides Timezone for a Post

add_action( 'init' , array('post_timezone', 'init') );

class post_timezone {
  public static function init() {
		add_filter( 'get_the_date', array('post_timezone', 'get_the_date'), 12, 2 );
    add_filter( 'get_the_time', array('post_timezone', 'get_the_time'), 12, 2 );
		add_filter( 'get_the_modified_date' , array('post_timezone', 'get_the_date'), 12, 2);
    add_filter( 'get_the_modified_time' , array('post_timezone', 'get_the_time')
, 12, 2);

	}

	public static function get_the_date($the_date, $d = '' , $post = null) {
		$post = get_post( $post );
		if (!$post) {
			return $the_date;
		}
	  $timezone = get_post_meta( $post->ID, '_timezone', true );
		if ( !$timezone ) {
			return $the_date;
		}
		if ( '' == $d ) {
			$d = get_option( 'date_format' );
		}
		$datetime = new DateTime($post->post_date_gmt, new DateTimeZone('GMT'));
		$datetime->setTimezone(new DateTimeZone($timezone));
		return $datetime->format($d);
	}

  public static function get_the_time($the_time, $d = '' , $post = null) {
		$post = get_post( $post );
		if (!$post) {
			return $the_time;
		}
    $timezone = get_post_meta( $post->ID, '_timezone', true );
    if ( !$timezone ) {
      return $the_time;
    }
    if ( '' == $d ) { 
			$d = get_option( 'time_format' );
		}	
    $datetime = new DateTime($post->post_date_gmt, new DateTimeZone('GMT'));
    $datetime->setTimezone(new DateTimeZone($timezone));
    $the_time = $datetime->format($d);
    return $the_time;
  }

} // End Class

