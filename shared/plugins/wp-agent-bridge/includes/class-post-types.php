<?php
/**
 * Portfolio custom post type and meta fields.
 *
 * Demonstrates the "custom code the agent edits" half of the workflow: post type
 * and field registration lives in PHP under version control, not in the database.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Post_Types {

	/**
	 * Post type slug.
	 */
	const PORTFOLIO = 'portfolio';

	/**
	 * Meta fields registered against the portfolio post type.
	 *
	 * @return array<string, array{type: string, label: string}>
	 */
	public static function meta_fields(): array {
		return array(
			'portfolio_client' => array(
				'type'  => 'string',
				'label' => __( 'Client', 'wp-agent-bridge' ),
			),
			'portfolio_url'    => array(
				'type'  => 'string',
				'label' => __( 'Project URL', 'wp-agent-bridge' ),
			),
			'portfolio_year'   => array(
				'type'  => 'integer',
				'label' => __( 'Year', 'wp-agent-bridge' ),
			),
		);
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'add_elementor_support' ), 20 );
	}

	/**
	 * Register the post type and its meta.
	 */
	public static function register(): void {
		register_post_type(
			self::PORTFOLIO,
			array(
				'labels'       => array(
					'name'          => __( 'Portfolio', 'wp-agent-bridge' ),
					'singular_name' => __( 'Project', 'wp-agent-bridge' ),
					'add_new_item'  => __( 'Add New Project', 'wp-agent-bridge' ),
					'edit_item'     => __( 'Edit Project', 'wp-agent-bridge' ),
				),
				'description'  => __( 'Portfolio projects with client metadata.', 'wp-agent-bridge' ),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => self::PORTFOLIO,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-portfolio',
				'rewrite'      => array( 'slug' => 'portfolio' ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'elementor' ),
			)
		);

		foreach ( self::meta_fields() as $key => $field ) {
			register_post_meta(
				self::PORTFOLIO,
				$key,
				array(
					'type'              => $field['type'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'integer' === $field['type'] ? 'absint' : 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Register the Elementor editor support flag for the post type.
	 *
	 * Elementor only offers its editor on post types that declare support for it,
	 * so this is what makes "Edit with Elementor" appear on a project.
	 */
	public static function add_elementor_support(): void {
		if ( ! post_type_exists( self::PORTFOLIO ) ) {
			return;
		}

		add_post_type_support( self::PORTFOLIO, 'elementor' );
	}
}
