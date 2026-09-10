<?php
/**
 * Discovery abilities: environment status and the widget contract.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Schema_Abilities {

	/**
	 * Register the ability group.
	 */
	public static function register(): void {
		Abilities::register(
			'get-environment-info',
			array(
				'label'               => __( 'Get environment info', 'wp-agent-bridge' ),
				'description'         => __( 'Report WordPress, Elementor, Abilities API, and MCP status for this site. Call this first when a layout operation fails or behaves unexpectedly.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => array( __CLASS__, 'environment_info' ),
			)
		);

		Abilities::register(
			'list-widget-schema',
			array(
				'label'               => __( 'List Elementor widget schema', 'wp-agent-bridge' ),
				'description'         => __( 'Return the supported Elementor widgets, their settings shape, the container controls, and the available layout recipes. Read this before composing element JSON by hand.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'execute_callback'    => array( __CLASS__, 'widget_schema' ),
			)
		);

		Abilities::register(
			'clear-elementor-cache',
			array(
				'label'               => __( 'Clear Elementor CSS cache', 'wp-agent-bridge' ),
				'description'         => __( 'Regenerate Elementor generated CSS files. Use when a saved layout does not appear to be styled on the front end.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'execute_callback'    => array( __CLASS__, 'clear_cache' ),
			)
		);
	}

	/**
	 * Environment diagnostics.
	 */
	public static function environment_info( array $input = array() ): array {
		$user = wp_get_current_user();

		return array(
			'wordpress_version'     => get_bloginfo( 'version' ),
			'php_version'           => PHP_VERSION,
			'site_url'              => home_url(),
			'admin_url'             => admin_url(),
			'mcp_endpoint'          => home_url( '/wp-json/mcp/mcp-adapter-default-server' ),
			'elementor'             => array(
				'active'      => Elementor_Document::is_available(),
				'version'     => Elementor_Document::version(),
				'layout_mode' => Elementor_Document::uses_containers() ? 'container' : 'section',
			),
			'abilities_api'         => Abilities::api_available(),
			'mcp_adapter'           => class_exists( '\WP\MCP\Core\McpAdapter' ) || defined( 'MCP_ADAPTER_VERSION' ),
			'application_passwords' => application_passwords_available(),
			'portfolio_post_type'   => post_type_exists( Post_Types::PORTFOLIO ),
			'current_user'          => array(
				'id'             => $user->ID,
				'login'          => $user->user_login,
				'can_edit_pages' => current_user_can( 'edit_pages' ),
				'can_manage'     => current_user_can( 'manage_options' ),
			),
			'theme'                 => array(
				'stylesheet' => get_stylesheet(),
				'template'   => get_template(),
			),
		);
	}

	/**
	 * The widget contract.
	 */
	public static function widget_schema( array $input = array() ): array {
		$schema                   = Widget_Catalog::schema();
		$schema['recipes']        = Layout_Recipes::names();
		$schema['recipe_arguments'] = array(
			'hero'            => array( 'heading', 'subheading', 'button_text', 'button_url', 'background_color', 'text_color' ),
			'feature-grid-3'  => array( 'items' => 'array of { title, description, icon }, max 3' ),
			'cta-band'        => array( 'heading', 'subheading', 'button_text', 'button_url', 'background_color', 'text_color' ),
			'two-column'      => array( 'left_heading', 'left_items' => 'array of strings', 'right_heading', 'right_body' ),
		);
		$schema['recommended_flow'] = array(
			'1. list-widget-schema to learn the shapes',
			'2. create-elementor-page with elements built from render-layout-recipe output',
			'3. get-page-structure to confirm the tree',
			'4. update-widget for small content tweaks',
		);

		return $schema;
	}

	/**
	 * Clear generated CSS.
	 */
	public static function clear_cache( array $input = array() ): array {
		$cleared = Elementor_Document::clear_cache();

		return array(
			'cleared' => $cleared,
			'message' => $cleared
				? __( 'Elementor CSS cache cleared. It regenerates on the next front end request.', 'wp-agent-bridge' )
				: __( 'Elementor is not active, so there was no cache to clear.', 'wp-agent-bridge' ),
		);
	}
}
