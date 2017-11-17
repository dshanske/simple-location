<?php

class Timezone_Result {
	public $timezone = null;

	private $_now;
	private $_name;

	public function __construct( $timezone, $date = false ) {
		if ( $date ) {
			$this->_now = new DateTime( $date );
		} else {
			$this->_now = new DateTime();
		}
		$this->_now->setTimeZone( new DateTimeZone( $timezone ) );
		$this->_name = $timezone;
	}

	public function __get( $key ) {
		switch ( $key ) {
			case 'offset':
				return $this->_now->format( 'P' );
			case 'seconds':
				return (int) $this->_now->format( 'Z' );
			case 'localtime':
				return $this->_now->format( 'c' );
			case 'name':
				return $this->_name;
		}
	}

	public function __toString() {
		return $this->_name;
	}
}
