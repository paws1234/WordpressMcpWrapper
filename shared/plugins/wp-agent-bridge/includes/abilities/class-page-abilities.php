<?php
/**
 * Page authoring abilities.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Page_Abilities {

	/**
	 * Elementor page template shortcuts.
	 */
	private static function template_map(): array {
		return array(
			'default'    => 'default',
			'canvas'     => 'elementor_canvas',
			'full-width' => 'elementor_header_footer',
		);
	}

	/**
	 * Register the ability group.
	 */
	public static function register(): void {
		Abilities::register(
			'create-elementor-page',
			array(
				'label'            => __( 'Create an Elementor page', 'wp-agent-bridge' ),
				'description'         => __( 'Create a new page, post, or portfolio project and immediately populate it with an Elementor elements tree. Returns the editor and preview URLs.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => 'Page title.',
						),
						'slug'          => array(
							'type'        => 'string',
							'description' => 'Optional URL slug. Derived from the title when omitted.',
						),
						'post_type'     => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post', Post_Types::PORTFOLIO ),
							'description' => 'Defaults to page.',
						),
						'status'        => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish', 'private' ),
							'description' => 'Defaults to draft.',
						),
						'template'      => array(
							'type'        => 'string',
							'enum'        => array( 'default', 'canvas', 'full-width' ),
							'description' => 'canvas removes the theme header and footer; full-width keeps them.',
						),
						'elements'      => array(
							'type'        => 'array',
							'description' => 'Top level Elementor elements, usually the output of render-layout-recipe.',
							'items'       => array( 'type' => 'object' ),
						),
						'page_settings' => array(
							'type'        => 'object',
							'description' => 'Optional Elementor page settings, for example hide_title.',
						),
					),
					'required'   => array( 'title' ),
				),
				'execute_callback'    => array( __CLASS__, 'create_page' ),
			)
		);

		Abilities::register(
			'set-page-settings',
			array(
				'label'               => __( 'Set Elementor page settings', 'wp-agent-bridge' ),
				'description'         => __( 'Merge settings into an existing document page level settings, for example hide_title, background color, or padding.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => 'The post to update.',
						),
						'settings' => array(
							'type'        => 'object',
							'description' => 'Page settings to merge.',
						),
					),
					'required'   => array( 'post_id', 'settings' ),
				),
				'execute_callback'    => array( __CLASS__, 'set_page_settings' ),
			)
		);
	}

	/**
	 * Create a post and populate it with Elementor data.
	 *
	 * @return array|\WP_Error
	 */
	public static function create_page( array $input ) {
		$title     = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'page';
		$status    = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';

		if ( '' === $title ) {
			return new \WP_Error( 'wab_missing_title', __( 'A page title is required.', 'wp-agent-bridge' ) );
		}

		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object ) {
			return new \WP_Error(
				'wab_unknown_post_type',
				sprintf(
					/* translators: %s: post type slug. */
					__( 'Post type "%s" is not registered.', 'wp-agent-bridge' ),
					$post_type
				)
			);
		}

		if ( ! in_array( $status, array( 'draft', 'publish', 'private' ), true ) ) {
			$status = 'draft';
		}

		if ( ! Elementor_Document::is_available() ) {
			return new \WP_Error( 'wab_elementor_missing', __( 'Elementor must be active before an Elementor page can be created.', 'wp-agent-bridge' ) );
		}

		$postarr = array(
			'post_title'   => $title,
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_content' => '',
		);

		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $input['template'] ) ) {
			$templates = self::template_map();
			$template  = sanitize_key( (string) $input['template'] );

			if ( isset( $templates[ $template ] ) ) {
				update_post_meta( $post_id, '_wp_page_template', $templates[ $template ] );
			}
		}

		$elements = array();

		if ( isset( $input['elements'] ) ) {
			$elements = Abilities::elements_from( $input['elements'] );

			if ( is_wp_error( $elements ) ) {
				return $elements;
			}
		}

		$page_settings = isset( $input['page_settings'] ) && is_array( $input['page_settings'] ) ? $input['page_settings'] : array();

		$result = Elementor_Document::save( (int) $post_id, $elements, $page_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge(
			$result,
			array(
				'post_id'   => (int) $post_id,
				'title'     => get_the_title( $post_id ),
				'post_type' => $post_type,
				'status'    => get_post_status( $post_id ),
				'template'  => get_post_meta( $post_id, '_wp_page_template', true ),
			)
		);
	}

	/**
	 * Merge page level settings.
	 *
	 * @return array|\WP_Error
	 */
	public static function set_page_settings( array $input ) {
		$post_id  = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		if ( ! $settings ) {
			return new \WP_Error( 'wab_missing_settings', __( 'No settings were supplied.', 'wp-agent-bridge' ) );
		}

		$existing = Elementor_Document::get_page_settings( $post_id );

		return Elementor_Document::save(
			$post_id,
			Elementor_Document::get_elements( $post_id ),
			array_merge( $existing, $settings )
		);
	}
}
