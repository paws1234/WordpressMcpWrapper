<?php
/**
 * Deterministic layout recipes.
 *
 * Asking a model to compose raw Elementor JSON is unreliable. These recipes turn
 * a small set of named arguments into a valid tree, so "hero banner + three
 * column grid + CTA" becomes a predictable operation.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Layout_Recipes {

	/**
	 * Recipe names exposed to the agent.
	 */
	public static function names(): array {
		return array( 'hero', 'feature-grid-3', 'cta-band', 'two-column' );
	}

	/**
	 * Build a recipe and return its top level elements.
	 *
	 * @return array|\WP_Error
	 */
	public static function build( string $name, array $args = array() ) {
		switch ( $name ) {
			case 'hero':
				return self::hero( $args );
			case 'feature-grid-3':
				return self::feature_grid_3( $args );
			case 'cta-band':
				return self::cta_band( $args );
			case 'two-column':
				return self::two_column( $args );
		}

		return new \WP_Error(
			'wab_unknown_recipe',
			sprintf(
				/* translators: %s: comma separated recipe names. */
				__( 'Unknown recipe. Available recipes: %s', 'wp-agent-bridge' ),
				implode( ', ', self::names() )
			)
		);
	}

	/**
	 * Hero banner: background band with heading, supporting copy, and a button.
	 */
	public static function hero( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				'heading'          => __( 'Build faster with a local WordPress sandbox', 'wp-agent-bridge' ),
				'subheading'       => __( 'A disposable WordPress site your AI assistant can drive directly.', 'wp-agent-bridge' ),
				'button_text'      => __( 'Get started', 'wp-agent-bridge' ),
				'button_url'       => '#',
				'background_color' => '#0f172a',
				'text_color'       => '#ffffff',
			)
		);

		$children = array(
			Widget_Catalog::heading(
				(string) $args['heading'],
				'h1',
				array(
					'align'       => 'center',
					'title_color' => (string) $args['text_color'],
				)
			),
		);

		if ( '' !== (string) $args['subheading'] ) {
			$children[] = Widget_Catalog::text(
				'<p>' . esc_html( (string) $args['subheading'] ) . '</p>',
				array(
					'align'      => 'center',
					'text_color' => (string) $args['text_color'],
				)
			);
		}

		$children[] = Widget_Catalog::button(
			(string) $args['button_text'],
			(string) $args['button_url'],
			array( 'align' => 'center' )
		);

		return array(
			Widget_Catalog::container(
				array(
					'flex_direction'        => 'column',
					'content_width'         => 'boxed',
					'padding'               => Widget_Catalog::padding( '96', '24', '96', '24' ),
					'background_background' => 'classic',
					'background_color'      => (string) $args['background_color'],
				),
				$children
			),
		);
	}

	/**
	 * Three column feature grid.
	 *
	 * @param array $args Expects an `items` array of { title, description, icon }.
	 */
	public static function feature_grid_3( array $args ): array {
		$defaults = array(
			array(
				'title'       => __( 'Write custom PHP', 'wp-agent-bridge' ),
				'description' => __( 'Register post types, fields, and templates straight from a prompt.', 'wp-agent-bridge' ),
				'icon'        => 'fas fa-code',
			),
			array(
				'title'       => __( 'Compose layouts', 'wp-agent-bridge' ),
				'description' => __( 'Generate Elementor structures without opening the editor.', 'wp-agent-bridge' ),
				'icon'        => 'fas fa-layer-group',
			),
			array(
				'title'       => __( 'Verify instantly', 'wp-agent-bridge' ),
				'description' => __( 'Changes are live on the local site the moment they are saved.', 'wp-agent-bridge' ),
				'icon'        => 'fas fa-bolt',
			),
		);

		$items = isset( $args['items'] ) && is_array( $args['items'] ) && $args['items'] ? $args['items'] : $defaults;
		$items = array_slice( array_values( $items ), 0, 3 );

		$columns = array();

		foreach ( $items as $item ) {
			$item = is_array( $item ) ? $item : array( 'title' => (string) $item );

			$columns[] = Widget_Catalog::column(
				array( 'padding' => Widget_Catalog::padding( '24', '24', '24', '24' ) ),
				array(
					Widget_Catalog::icon_box(
						isset( $item['title'] ) ? (string) $item['title'] : '',
						isset( $item['description'] ) ? (string) $item['description'] : '',
						isset( $item['icon'] ) ? (string) $item['icon'] : 'fas fa-star'
					),
				),
				33
			);
		}

		return array(
			Widget_Catalog::row(
				array(
					'flex_wrap'     => 'wrap',
					'content_width' => 'boxed',
					'padding'       => Widget_Catalog::padding( '64', '24', '64', '24' ),
				),
				$columns
			),
		);
	}

	/**
	 * Full width call to action band.
	 */
	public static function cta_band( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				'heading'          => __( 'Ready to build?', 'wp-agent-bridge' ),
				'subheading'       => '',
				'button_text'      => __( 'Start now', 'wp-agent-bridge' ),
				'button_url'       => '#',
				'background_color' => '#1d4ed8',
				'text_color'       => '#ffffff',
			)
		);

		$children = array(
			Widget_Catalog::heading(
				(string) $args['heading'],
				'h2',
				array(
					'align'       => 'center',
					'title_color' => (string) $args['text_color'],
				)
			),
		);

		if ( '' !== (string) $args['subheading'] ) {
			$children[] = Widget_Catalog::text(
				'<p>' . esc_html( (string) $args['subheading'] ) . '</p>',
				array(
					'align'      => 'center',
					'text_color' => (string) $args['text_color'],
				)
			);
		}

		$children[] = Widget_Catalog::button(
			(string) $args['button_text'],
			(string) $args['button_url'],
			array(
				'align'              => 'center',
				'background_color'   => '#ffffff',
				'button_text_color' => (string) $args['background_color'],
			)
		);

		return array(
			Widget_Catalog::container(
				array(
					'flex_direction'        => 'column',
					'padding'               => Widget_Catalog::padding( '56', '24', '56', '24' ),
					'background_background' => 'classic',
					'background_color'      => (string) $args['background_color'],
				),
				$children
			),
		);
	}

	/**
	 * Two column text layout with optional list on the left.
	 */
	public static function two_column( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				'left_heading'  => __( 'What you get', 'wp-agent-bridge' ),
				'left_items'    => array(
					__( 'A disposable WordPress site in Docker', 'wp-agent-bridge' ),
					__( 'Elementor wired to your AI client over MCP', 'wp-agent-bridge' ),
					__( 'A theme and plugin you fully control', 'wp-agent-bridge' ),
				),
				'right_heading' => __( 'How it works', 'wp-agent-bridge' ),
				'right_body'    => __( 'Describe the layout you want. The agent builds the Elementor tree and saves it through the WordPress APIs, then hands you a preview link.', 'wp-agent-bridge' ),
			)
		);

		$left_children = array( Widget_Catalog::heading( (string) $args['left_heading'], 'h3' ) );

		$items = isset( $args['left_items'] ) && is_array( $args['left_items'] ) ? $args['left_items'] : array();

		if ( $items ) {
			$left_children[] = Widget_Catalog::icon_list( $items );
		}

		$right_children = array(
			Widget_Catalog::heading( (string) $args['right_heading'], 'h3' ),
			Widget_Catalog::text( '<p>' . esc_html( (string) $args['right_body'] ) . '</p>' ),
		);

		return array(
			Widget_Catalog::row(
				array(
					'flex_wrap'     => 'wrap',
					'content_width' => 'boxed',
					'padding'       => Widget_Catalog::padding( '64', '24', '64', '24' ),
				),
				array(
					Widget_Catalog::column( array(), $left_children, 50 ),
					Widget_Catalog::column( array(), $right_children, 50 ),
				)
			),
		);
	}
}
