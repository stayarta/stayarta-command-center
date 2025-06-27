<?php

namespace QuadLayers\AICP\Hooks;

class File_Upload_Permissions {

	protected static $instance;

	private function __construct() {
		add_filter( 'upload_mimes', array( $this, 'allow_custom_file_types' ) );
	}

	public function allow_custom_file_types( $mimes ) {
		$custom_mimes  = array(
			'json' => 'application/json',
		);
		$allowed_mimes = array_merge( $mimes, $custom_mimes );

		return $allowed_mimes;
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
