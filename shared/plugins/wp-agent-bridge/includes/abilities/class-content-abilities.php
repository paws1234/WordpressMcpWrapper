<?php
/**
 * Content abilities: inspect post types and create entries.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Content_Abilities {

	/**
	 * Register the ability group.
	 */
	public static function register(): void {
		Abilities::register(
			'list-post-types',
			array(
				'label'               => __( 'List registered post types', 'wp-agent-bridge' ),
				'description'         => __( 'List the public post types and their registered meta keys. Use before create-post so meta is only written to registered fields.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => array( __CLASS__, 'list_post_types' ),
			)
		);

		Abilities::register(
			'create-post',
			array(
				'label'            => __( 'Create a post entry', 'wp-agent-bridge' ),
				'description'         => __( 'Create a post, page, or portfolio project. Meta is only applied when the key is already registered for that post type, so arbitrary meta cannot be written.', 'wp-agent-bridge' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Registered post type slug, for example post, page, or portfolio.',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'Entry title.',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'Optional post content. Leave empty for Elementor built entries.',
						),
						'slug'      => array(
							'type'        => 'string',
							'description' => 'Optional URL slug.',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => 'Optional short summary.',
						),
						'status'    => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish', 'private' ),
							'description' => 'Defaults to draft.',
						),
						'meta'      => array(
							'type'        => 'object',
							'description' => 'Registered meta values, for example { portfolio_client: "Acme", portfolio_year: 2026 }.',
						),
					),
					'required'   => array( 'post_type', 'title' ),
				),
				'execute_callback'    => array( __CLASS__, 'create_post' ),
			)
		);
	}

	/**
	 * Public post types and their REST exposed meta.
	 */
	public static function list_post_types( array $input = array() ): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$meta_keys = array();

			if ( function_exists( 'get_registered_meta_keys' ) ) {
				foreach ( get_registered_meta_keys( 'post', $type->name ) as $key => $field ) {
					$meta_keys[ $key ] = array(
						'type'         => $field['type'] ?? 'string',
						'single'       => (bool) ( $field['single'] ?? true ),
						'show_in_rest' => (bool) ( $field['show_in_rest'] ?? false ),
					);
				}
			}

			$types[] = array(
				'name'      => $type->name,
				'label'     => $type->labels->name,
				'public'    => (bool) $type->public,
				'rest_base' => $type->rest_base ? $type->rest_base : $type->name,
				'elementor' => post_type_supports( $type->name, 'elementor' ),
				'meta'      => $meta_keys,
			);
		}

		return array(
			'post_types' => $types,
			'note'       => __( 'Only meta keys listed under a post type can be written by create-post.', 'wp-agent-bridge' ),
		);
	}

	/**
	 * Create an entry.
	 *
	 * @return array|\WP_Error
	 */
	public static function create_post( array $input ) {
		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : '';
		$title     = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$status    = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';

		if ( '' === $title ) {
			return new \WP_Error( 'wab_missing_title', __( 'A title is required.', 'wp-agent-bridge' ) );
		}

		$type = get_post_type_object( $post_type );

		if ( ! $type ) {
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

		$postarr = array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_status'  => $status,
			'post_content' => isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '',
		);

		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		if ( ! empty( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( (string) $input['excerpt'] );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$applied = array();
		$skipped = array();

		$registered = function_exists( 'get_registered_meta_keys' )
			? get_registered_meta_keys( 'post', $post_type )
			: array();

		$meta = isset( $input['meta'] ) && is_array( $input['meta'] ) ? $input['meta'] : array();

		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || ! isset( $registered[ $key ] ) ) {
				$skipped[] = $key;
				continue;
			}

			$type_of_field = $registered[ $key ]['type'] ?? 'string';

			update_post_meta( $post_id, $key, 'integer' === $type_of_field ? absint( $value ) : sanitize_text_field( (string) $value ) );
			$applied[] = $key;
		}

		return array(
			'post_id'          => (int) $post_id,
			'title'            => get_the_title( $post_id ),
			'post_type'        => $post_type,
			'status'           => get_post_status( $post_id ),
			'slug'             => get_post_field( 'post_name', $post_id ),
			'meta_applied'     => $applied,
			'meta_skipped'     => $skipped,
			'edit_url'         => admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
			'preview_url'      => get_permalink( $post_id ) ? get_permalink( $post_id ) : null,
			'elementor_support' => post_type_supports( $post_type, 'elementor' ),
		);
	}
}
