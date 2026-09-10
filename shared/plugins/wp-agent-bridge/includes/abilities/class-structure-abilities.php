<?php
/**
 * Structure abilities: read, replace, extend, and patch the elements tree.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Structure_Abilities {

	/**
	 * Register the ability group.
	 */
	public static function register(): void {
		Abilities::register(
			'get-page-structure',
			array(
				'label'               => __( 'Get Elementor page structure', 'wp-agent-bridge' ),
				'description'         => __( 'Return the element outline for a post: ids, types, widget types, and optionally each node settings. Use this to find the element id to patch with update-widget.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array(
							'type'        => 'integer',
							'description' => 'The post to inspect.',
						),
						'include_settings' => array(
							'type'        => 'boolean',
							'description' => 'Include each node settings. Defaults to false because the output can be large.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'get_structure' ),
			)
		);

		Abilities::register(
			'replace-elements',
			array(
				'label'               => __( 'Replace all Elementor elements', 'wp-agent-bridge' ),
				'description'         => __( 'Replace the entire top level elements tree of a post. Use for a full redesign. Existing element ids are discarded.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => 'The post to overwrite.',
						),
						'elements'      => array(
							'type'        => 'array',
							'description' => 'The new top level elements.',
							'items'       => array( 'type' => 'object' ),
						),
						'page_settings' => array(
							'type'        => 'object',
							'description' => 'Optional page settings to merge.',
						),
					),
					'required'   => array( 'post_id', 'elements' ),
				),
				'execute_callback'    => array( __CLASS__, 'replace_elements' ),
			)
		);

		Abilities::register(
			'add-container',
			array(
				'label'               => __( 'Add a container to a page', 'wp-agent-bridge' ),
				'description'         => __( 'Insert a new container (or section) into an existing tree, either at the root or inside another container. This is the additive counterpart to replace-elements.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => 'The post to modify.',
						),
						'parent_id' => array(
							'type'        => 'string',
							'description' => 'Element id of the parent container. Omit or send an empty string to insert at the top level.',
						),
						'position'  => array(
							'type'        => 'integer',
							'description' => 'Zero based index among the parent children. Appends when omitted.',
						),
						'settings'  => array(
							'type'        => 'object',
							'description' => 'Container settings, for example flex_direction, padding, background_color.',
						),
						'elements'  => array(
							'type'        => 'array',
							'description' => 'Child elements to place inside the new container.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'execute_callback'    => array( __CLASS__, 'add_container' ),
			)
		);

		Abilities::register(
			'update-widget',
			array(
				'label'               => __( 'Update a single Elementor element', 'wp-agent-bridge' ),
				'description'         => __( 'Merge settings into one existing element identified by its id. Ideal for fixing copy, colors, or links without rebuilding the page.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => 'The post that owns the element.',
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => 'The element id from get-page-structure.',
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => 'Settings to merge into the element.',
						),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'execute_callback'    => array( __CLASS__, 'update_widget' ),
			)
		);
	}

	/**
	 * Read the outline of a document.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_structure( array $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		$elements = Elementor_Document::get_elements( $post_id );

		return array(
			'post_id'              => $post_id,
			'title'                => get_the_title( $post ),
			'post_type'            => $post->post_type,
			'built_with_elementor' => Elementor_Document::is_built( $post_id ),
			'total_elements'       => Elementor_Document::count_elements( $elements ),
			'layout_mode'          => Elementor_Document::uses_containers() ? 'container' : 'section',
			'edit_url'             => Elementor_Document::edit_url( $post_id ),
			'preview_url'          => get_permalink( $post_id ) ? get_permalink( $post_id ) : null,
			'structure'            => Elementor_Document::summarize( $elements, ! empty( $input['include_settings'] ) ),
		);
	}

	/**
	 * Overwrite the whole tree.
	 *
	 * @return array|\WP_Error
	 */
	public static function replace_elements( array $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		$elements = Abilities::elements_from( $input['elements'] ?? array() );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		$page_settings = isset( $input['page_settings'] ) && is_array( $input['page_settings'] )
			? array_merge( Elementor_Document::get_page_settings( $post_id ), $input['page_settings'] )
			: Elementor_Document::get_page_settings( $post_id );

		$result = Elementor_Document::save( $post_id, $elements, $page_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge( $result, array( 'outline' => Elementor_Document::summarize( $elements ) ) );
	}

	/**
	 * Insert a container.
	 *
	 * @return array|\WP_Error
	 */
	public static function add_container( array $input ) {
		$post_id   = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$position  = isset( $input['position'] ) && '' !== $input['position'] ? (int) $input['position'] : null;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		$children = array();

		if ( isset( $input['elements'] ) ) {
			$children = Abilities::elements_from( $input['elements'] );

			if ( is_wp_error( $children ) ) {
				return $children;
			}
		}

		$elements = Elementor_Document::get_elements( $post_id );

		if ( '' !== $parent_id && ! Elementor_Document::find( $elements, $parent_id ) ) {
			return new \WP_Error(
				'wab_parent_not_found',
				sprintf(
					/* translators: %s: element id. */
					__( 'No element with id "%s" exists on this post.', 'wp-agent-bridge' ),
					$parent_id
				)
			);
		}

		$container = Widget_Catalog::container( $settings, $children );

		if ( ! Elementor_Document::insert( $elements, $parent_id, $container, $position ) ) {
			return new \WP_Error( 'wab_insert_failed', __( 'The container could not be inserted at the requested position.', 'wp-agent-bridge' ) );
		}

		$result = Elementor_Document::save( $post_id, $elements );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge(
			$result,
			array(
				'container_id' => $container['id'],
				'outline'      => Elementor_Document::summarize( $elements ),
			)
		);
	}

	/**
	 * Patch one element.
	 *
	 * @return array|\WP_Error
	 */
	public static function update_widget( array $input ) {
		$post_id    = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$element_id = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';
		$settings   = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		if ( '' === $element_id ) {
			return new \WP_Error( 'wab_missing_element_id', __( 'An element id is required.', 'wp-agent-bridge' ) );
		}

		if ( ! $settings ) {
			return new \WP_Error( 'wab_missing_settings', __( 'No settings were supplied.', 'wp-agent-bridge' ) );
		}

		$elements = Elementor_Document::get_elements( $post_id );

		if ( ! Elementor_Document::update_settings( $elements, $element_id, $settings ) ) {
			return new \WP_Error(
				'wab_element_not_found',
				sprintf(
					/* translators: %s: element id. */
					__( 'No element with id "%s" exists on this post.', 'wp-agent-bridge' ),
					$element_id
				)
			);
		}

		$result = Elementor_Document::save( $post_id, $elements );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge(
			$result,
			array(
				'element_id' => $element_id,
				'element'    => Elementor_Document::find( $elements, $element_id ),
			)
		);
	}
}
