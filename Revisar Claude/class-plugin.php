<?php

namespace QuadLayers\AICP;

use QuadLayers\AICP\Api\Services\Pexels\Routes_Library as Services_Pexels_Routes_Library;
use QuadLayers\AICP\Api\Services\OpenAI\Routes_Library as Services_OpenAI_Routes_Library;
use QuadLayers\AICP\Api\Entities\Actions_Templates\Routes_Library as Actions_Templates_Routes_Library;
use QuadLayers\AICP\Api\Entities\Admin_Menu\Routes_Library as Admin_Menu_Routes_Library;
use QuadLayers\AICP\Api\Entities\Content_Templates\Routes_Library as Content_Templates_Routes_Library;
use QuadLayers\AICP\Api\Entities\Transactions\Routes_Library as Transactions_Routes_Library;

final class Plugin {

	private static $instance;

	private function __construct() {
		/**
		 * Load plugin textdomain.
		 */
		add_action( 'init', array( $this, 'load_textdomain' ) );
		/**
		 * Add premium CSS
		 */
		add_action( 'admin_head', array( __CLASS__, 'add_premium_js' ) );
		add_action( 'admin_footer', array( __CLASS__, 'add_premium_css' ) );

		Setup::instance();

		Services_OpenAI_Routes_Library::instance();
		Services_Pexels_Routes_Library::instance();
		Transactions_Routes_Library::instance();
		Admin_Menu_Routes_Library::instance();
		Actions_Templates_Routes_Library::instance();
		Content_Templates_Routes_Library::instance();

		Controllers\Icons::instance();
		Controllers\Helpers::instance();
		Controllers\Hooks::instance();
		Controllers\Components::instance();
		Controllers\Api_Services::instance();
		Controllers\Api_Transactions::instance();
		Controllers\Api_Admin_Menu::instance();

		Controllers\Api_Actions_Templates::instance();
		Controllers\Api_Content_Templates::instance();
		Controllers\Api_Assistant_Messages::instance();
		Controllers\Api_Assistant_Threads::instance();
		Controllers\Api_Assistant_Files::instance();
		Controllers\Api_Assistant_Vector_Stores::instance();
		Controllers\Api_Assistants::instance();

		Controllers\Admin_Menu::instance();
		Controllers\Admin_Actions::instance();
		Controllers\Frontend_Chatbot::instance();
		Controllers\Admin_Content::instance();
		Controllers\Admin_Bulk::instance();
		Controllers\Admin_Playground::instance();

		Hooks\Content_Classic_Editor::instance();
		Hooks\File_Upload_Permissions::instance();

		$is_upgrade_required = Helpers::is_update_required();

		if ( $is_upgrade_required ) {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'AI Copilot Pro is disabled. Please update the AI Copilot plugin to the latest version.', 'ai-copilot' ); ?></p>
					</div>
				<?php
				}
			);
			return;
		}

		$is_api_key_required = Helpers::is_api_key_required();

		if ( $is_api_key_required ) {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
						<p>
							<b><?php esc_html_e( 'Connect AI Copilot to OpenAI', 'ai-copilot' ); ?></b>
							</br>
							<?php
							printf(
								wp_kses(
									/* translators: 1: OpenAI link, 2: API Keys tab link */
									__(
										'To make full use of AI Copilot, you need to connect it directly to OpenAI services. First, create an %1$s account and generate an API key on their website. Then, go to the %2$s tab in the settings and enter your key.',
										'ai-copilot'
									),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
										),
									)
								),
								'<a href="https://platform.openai.com/settings/organization/api-keys" target="_blank">' . __( 'OpenAI', 'ai-copilot' ) . '</a>',
								'<a href="' . esc_url( admin_url( 'admin.php?page=ai-copilot&tab=apikeys' ) ) . '" target="_blank">' . __( 'API Keys', 'ai-copilot' ) . '</a>'
							);
							?>
						</p>
					</div>
				<?php
				}
			);
			return;
		}

		do_action( 'quadlayers_aicp_init' );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'ai-copilot', false, QUADLAYERS_AICP_PLUGIN_DIR . '/languages/' );
	}

	public static function add_premium_js() {
		?>
			<script>
				var AICP_IS_FREE = true;
			</script>
		<?php
	}

	public static function add_premium_css() {
		?>
			<style>
				.aicp__premium-field {
					opacity: 0.5;
					pointer-events: none;
				}
				.aicp__premium-field input,
				.aicp__premium-field textarea,
				.aicp__premium-field select {
					background-color: #eee;
				}
				.aicp__premium-badge::before {
					content: "Pro";
					display: inline-block;
					font-size: 10px;
					color: #ffffff;
					background-color: #f57c00;
					border-radius: 3px;
					width: 30px;
					height: 15px;
					line-height: 15px;
					text-align: center;
					margin-right: 5px;
					vertical-align: middle;
					font-weight: 600;
					text-transform: uppercase;
				}
				.aicp__premium-hide {
					display: none;
				}
				.aicp__premium-field .description {
					display: inline-block !important;
					vertical-align: middle;
				}
			</style>
		<?php
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

Plugin::instance();