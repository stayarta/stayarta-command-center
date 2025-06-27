<?php

namespace QuadLayers\AICP;

use QuadLayers\AICP\Models\Transactions;
use QuadLayers\AICP\Models\Assistants_Threads;

class Setup {

	public static $instance;

	protected function __construct() {
		add_action( 'quadlayers_aicp_activation', array( $this, 'create_table' ) );
	}

	public static function create_table() {
		Transactions::create_table();
		Assistants_Threads::create_table();
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
