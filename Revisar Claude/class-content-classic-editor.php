<?php

namespace QuadLayers\AICP\Hooks;

use QuadLayers\AICP\Helpers;
use QuadLayers\AICP\Models\Admin_Menu_Modules;

class Content_Classic_Editor {

	protected static $instance;

	private function __construct() {
		add_action( 'current_screen', array( $this, 'initialize_plugin' ) );
	}

	public function initialize_plugin( $current_screen ) {
		$admin_menu_modules = Admin_Menu_Modules::instance()->get();
		$post_types         = array();

		if ( 1 !== $admin_menu_modules->get( 'content_enable' ) ) {
			return;
		}

		if ( 1 === $admin_menu_modules->get( 'content_enable' ) ) {
			$post_types = $admin_menu_modules->get( 'content_post_types' );
		} else {
			$post_types_object = Helpers::get_valid_post_types();
			foreach ( $post_types_object as $value ) {
				$post_types[] = $value->name;
			}
		}

		if ( is_admin() && in_array( $current_screen->post_type, $post_types ) && ! $current_screen->is_block_editor ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		}
	}

	public function add_meta_boxes() {
		$icon = '<span class="dashicon dashicons dashicons-admin-tools components-panel__icon"></span>';

		$title = '<span>' . QUADLAYERS_AICP_PLUGIN_NAME . $icon . '</span>';

		add_meta_box(
			'ai-copilot-metadata',
			$title,
			array( $this, 'render_metadata_metabox' ),
			null,
			'side',
			'high'
		);
	}

	public function render_metadata_metabox() {
		echo '<div id="ai-copilot-metabox"></div>';
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
