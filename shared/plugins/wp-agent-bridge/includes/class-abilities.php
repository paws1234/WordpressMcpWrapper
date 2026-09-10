<?php
/**
 * Ability registration shared plumbing.
 *
 * Abilities are private by default in the Abilities API; the MCP Adapter only
 * exposes the ones flagged with meta.public, which register() applies for us.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Abilities {

	/**
	 * Hook into the Abilities API lifecycle.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Whether the Abilities API is present.
	 */
	public static function api_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Register our ability category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			CATEGORY,
			array(
				'label'       => __( 'WP Agent Bridge', 'wp-agent-bridge' ),
				'description' => __( 'Author Elementor layouts and manage content on this local site.', 'wp-agent-bridge' ),
			)
		);
	}

	/**
	 * Register every ability this plugin provides.
	 */
	public static function register_abilities(): void {
		if ( ! self::api_available() ) {
			return;
		}

		Schema_Abilities::register();
		Page_Abilities::register();
		Structure_Abilities::register();
		Layout_Abilities::register();
		Content_Abilities::register();
	}

	/**
	 * Register one ability with the defaults this plugin always wants.
	 */
	public static function register( string $name, array $args ): void {
		$args = wp_parse_args(
			$args,
			array(
				'category'            => CATEGORY,
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'meta'                => array(),
			)
		);

		$args['meta'] = wp_parse_args(
			$args['meta'],
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		if ( empty( $args['output_schema'] ) ) {
			$args['output_schema'] = array( 'type' => 'object' );
		}

		wp_register_ability( SLUG . '/' . $name, $args );
	}

	/**
	 * Names of abilities currently registered, when the API can report them.
	 */
	public static function registered(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$names = array();

		foreach ( (array) wp_get_abilities() as $key => $ability ) {
			if ( is_string( $key ) ) {
				$names[] = $key;
			} elseif ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ) {
				$names[] = $ability->get_name();
			}
		}

		return array_values( array_filter( $names ) );
	}

	/**
	 * Accept an elements tree supplied as an array or a JSON string.
	 *
	 * @return array|\WP_Error
	 */
	public static function elements_from( $value ) {
		if ( is_string( $value ) ) {
			try {
				$value = json_decode( $value, true, 512, JSON_THROW_ON_ERROR );
			} catch ( \JsonException $exception ) {
				return new \WP_Error(
					'wab_invalid_json',
					__( 'The elements value was a string but not valid JSON.', 'wp-agent-bridge' )
				);
			}
		}

		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'wab_invalid_elements',
				__( 'Elements must be an array of element objects.', 'wp-agent-bridge' )
			);
		}

		return array_values( $value );
	}
}
