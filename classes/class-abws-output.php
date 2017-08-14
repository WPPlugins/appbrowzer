<?php

class ABWS_Output {

	private static $instance = null;

	/**
	 * Get singleton instance of class
	 *
	 * @return null|ABWS_Output
	 */
	public static function get() {

		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Constructor
	 */
	private function __construct() {
	}

	/**
	 * The correct way to ouput data in a webservice call
	 *
	 * @param $data
	 */
	public function output( $data ) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode( $data );
	}

} 
