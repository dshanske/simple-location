<?php

class Timezone_Result {
	public $timezone = null;

	private $now;
	private $name;

	public function __construct( $timezone, $date = false ) {
		if ( $date ) {
			$this->now = new DateTime( $date );
		} else {
			$this->now = new DateTime();
		}
		$this->timezone = new DateTimeZone( $timezone );
		$this->now->setTimeZone( $this->timezone );
		$this->name = $timezone;
	}

	public function __get( $key ) {
		switch ( $key ) {
			case 'offset':
				return $this->now->format( 'P' );
			case 'seconds':
				return (int) $this->now->format( 'Z' );
			case 'localtime':
				return $this->now->format( 'c' );
			case 'name':
				return $this->name;
		}
	}

	public function __toString() {
		return $this->name;
	}
}
