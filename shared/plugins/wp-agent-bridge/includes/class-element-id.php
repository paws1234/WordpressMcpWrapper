<?php
/**
 * Unique element ID generation.
 *
 * Elementor identifies every node in the tree by a short id. Duplicated or
 * missing ids cause widgets to be dropped when the editor is opened, so every
 * tree we accept is normalised before it is saved.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Element_ID {

	/**
	 * Generate a fresh 7 character hexadecimal id.
	 */
	public static function generate(): string {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	/**
	 * Assign an id to any node missing one, and de-duplicate the tree in place.
	 *
	 * @param array $elements Elements tree, modified by reference.
	 * @param array $seen     Ids already used, modified by reference.
	 */
	public static function ensure( array &$elements, array &$seen = array() ): void {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$id = isset( $element['id'] ) ? (string) $element['id'] : '';

			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id            = self::unique( $seen );
				$element['id'] = $id;
			}

			$seen[ $id ] = true;

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::ensure( $element['elements'], $seen );
			}
		}

		unset( $element );
	}

	/**
	 * Produce an id that is not present in the seen map.
	 *
	 * @param array $seen Ids already used.
	 */
	private static function unique( array $seen ): string {
		do {
			$candidate = self::generate();
		} while ( isset( $seen[ $candidate ] ) );

		return $candidate;
	}
}
