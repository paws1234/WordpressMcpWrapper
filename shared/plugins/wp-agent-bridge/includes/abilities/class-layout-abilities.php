<?php
/**
 * Layout recipe ability.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Layout_Abilities {

	/**
	 * Register the ability group.
	 */
	public static function register(): void {
		Abilities::register(
			'render-layout-recipe',
			array(
				'label'               => __( 'Render an Elementor layout recipe', 'wp-agent-bridge' ),
				'description'         => __( 'Build a named layout (hero, feature-grid-3, cta-band, two-column) and return the generated elements. Pass post_id to write it to a page in the same call; omit post_id to preview the tree as a dry run.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'recipe'  => array(
							'type'        => 'string',
							'enum'        => Layout_Recipes::names(),
							'description' => 'Which layout to build.',
						),
						'args'    => array(
							'type'        => 'object',
							'description' => 'Recipe arguments such as heading, subheading, button_text, button_url, items, colors.',
						),
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Optional post to write to. Omit for a dry run that only returns elements.',
						),
						'mode'    => array(
							'type'        => 'string',
							'enum'        => array( 'append', 'replace' ),
							'description' => 'How to combine with existing content. Defaults to append.',
						),
					),
					'required'   => array( 'recipe' ),
				),
				'execute_callback'    => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Build and optionally persist a recipe.
	 *
	 * @return array|\WP_Error
	 */
	public static function render( array $input ) {
		$recipe = isset( $input['recipe'] ) ? sanitize_key( (string) $input['recipe'] ) : '';

		if ( '' === $recipe ) {
			return new \WP_Error( 'wab_missing_recipe', __( 'A recipe name is required.', 'wp-agent-bridge' ) );
		}

		$args = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();

		$elements = Layout_Recipes::build( $recipe, $args );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		$response = array(
			'recipe'      => $recipe,
			'layout_mode' => Elementor_Document::uses_containers() ? 'container' : 'section',
			'elements'    => $elements,
			'saved'       => false,
		);

		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( ! $post_id ) {
			$response['note'] = __( 'Dry run only. Pass post_id to write these elements to a page.', 'wp-agent-bridge' );

			return $response;
		}

		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'wab_post_not_found', __( 'No post exists with that id.', 'wp-agent-bridge' ) );
		}

		$mode = isset( $input['mode'] ) ? sanitize_key( (string) $input['mode'] ) : 'append';

		if ( 'replace' === $mode ) {
			$tree = $elements;
		} else {
			$tree = Elementor_Document::get_elements( $post_id );
			$tree = array_merge( $tree, $elements );
		}

		$page_settings = Elementor_Document::get_page_settings( $post_id );

		$result = Elementor_Document::save( $post_id, $tree, $page_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response['saved']   = true;
		$response['mode']    = $mode;
		$response['post_id'] = $post_id;

		return array_merge( $response, $result );
	}
}
