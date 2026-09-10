<?php
/**
 * Elementor document access layer.
 *
 * Every write to Elementor data goes through this class so that Elementor's own
 * Document API owns serialisation, version stamping, and CSS regeneration.
 * Writing `_elementor_data` with raw update_post_meta() leaves stale generated
 * CSS behind, which is why it is deliberately avoided here.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Elementor_Document {

	/**
	 * Whether Elementor is loaded and usable.
	 */
	public static function is_available(): bool {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance );
	}

	/**
	 * The Elementor plugin instance, if available.
	 */
	public static function plugin(): ?\Elementor\Plugin {
		return self::is_available() ? \Elementor\Plugin::$instance : null;
	}

	/**
	 * Active Elementor version.
	 */
	public static function version(): ?string {
		return defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null;
	}

	/**
	 * Whether the container element is active. Containers are the default since
	 * Elementor 3.16; legacy installs still render section/column.
	 */
	public static function uses_containers(): bool {
		$plugin = self::plugin();

		if ( ! $plugin ) {
			return false;
		}

		return (bool) $plugin->experiments->is_feature_active( 'container' );
	}

	/**
	 * Resolve the Elementor document for a post.
	 */
	public static function get( int $post_id ): ?\Elementor\Core\Base\Document {
		$plugin = self::plugin();

		if ( ! $plugin ) {
			return null;
		}

		$document = $plugin->documents->get( $post_id );

		return $document instanceof \Elementor\Core\Base\Document ? $document : null;
	}

	/**
	 * Whether a post has already been built with Elementor.
	 */
	public static function is_built( int $post_id ): bool {
		$document = self::get( $post_id );

		return $document ? (bool) $document->is_built_with_elementor() : false;
	}

	/**
	 * The current elements tree for a post.
	 */
	public static function get_elements( int $post_id ): array {
		$document = self::get( $post_id );

		if ( ! $document ) {
			return array();
		}

		$elements = $document->get_elements_data();

		return is_array( $elements ) ? $elements : array();
	}

	/**
	 * Page level Elementor settings for a post.
	 */
	public static function get_page_settings( int $post_id ): array {
		$document = self::get( $post_id );

		if ( ! $document ) {
			return array();
		}

		$settings = $document->get_settings();

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Mark a post as built with Elementor and stamp the template type.
	 */
	public static function mark_as_built( int $post_id, ?string $template_type = null ): void {
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		if ( null === $template_type ) {
			$document      = self::get( $post_id );
			$template_type = $document ? $document->get_name() : 'wp-page';
		}

		update_post_meta( $post_id, '_elementor_template_type', $template_type );

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}
	}

	/**
	 * Persist an elements tree, and optionally page settings.
	 *
	 * @return array|\WP_Error
	 */
	public static function save( int $post_id, array $elements, array $page_settings = array() ) {
		if ( ! self::is_available() ) {
			return new \WP_Error(
				'wab_elementor_missing',
				__( 'Elementor is not active, so Elementor data cannot be written.', 'wp-agent-bridge' )
			);
		}

		$document = self::get( $post_id );

		if ( ! $document ) {
			return new \WP_Error(
				'wab_document_missing',
				__( 'No Elementor document could be resolved for this post.', 'wp-agent-bridge' )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'wab_cannot_edit',
				__( 'The current user cannot edit this post.', 'wp-agent-bridge' )
			);
		}

		$seen = array();
		Element_ID::ensure( $elements, $seen );

		self::mark_as_built( $post_id, $document->get_name() );

		$payload = array( 'elements' => array_values( $elements ) );

		if ( ! empty( $page_settings ) ) {
			$payload['settings'] = $page_settings;
		}

		$document->save( $payload );

		self::clear_cache();

		return array(
			'post_id'           => $post_id,
			'top_level_elements' => count( $elements ),
			'element_count'     => self::count_elements( $elements ),
			'built_with_elementor' => self::is_built( $post_id ),
			'edit_url'          => self::edit_url( $post_id ),
			'preview_url'       => get_permalink( $post_id ) ? get_permalink( $post_id ) : null,
		);
	}

	/**
	 * Drop Elementor's generated CSS so it is rebuilt on the next request.
	 */
	public static function clear_cache(): bool {
		$plugin = self::plugin();

		if ( ! $plugin || ! isset( $plugin->files_manager ) ) {
			return false;
		}

		$plugin->files_manager->clear_cache();

		return true;
	}

	/**
	 * Editor URL for a post, preferring the Elementor editor.
	 */
	public static function edit_url( int $post_id ): ?string {
		$document = self::get( $post_id );

		if ( $document && method_exists( $document, 'get_edit_url' ) ) {
			$url = $document->get_edit_url();

			if ( $url ) {
				return $url;
			}
		}

		$url = get_edit_post_link( $post_id, 'raw' );

		return $url ? $url : null;
	}

	/**
	 * Total node count in a tree.
	 */
	public static function count_elements( array $elements ): int {
		$count = 0;

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			++$count;

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$count += self::count_elements( $element['elements'] );
			}
		}

		return $count;
	}

	/**
	 * Reduce a tree to a readable outline.
	 */
	public static function summarize( array $elements, bool $with_settings = false ): array {
		$summary = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$node = array(
				'id'   => isset( $element['id'] ) ? $element['id'] : null,
				'type' => isset( $element['elType'] ) ? $element['elType'] : null,
			);

			if ( isset( $element['widgetType'] ) ) {
				$node['widget'] = $element['widgetType'];
			}

			if ( $with_settings && isset( $element['settings'] ) ) {
				$node['settings'] = $element['settings'];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$node['children'] = self::summarize( $element['elements'], $with_settings );
			}

			$summary[] = $node;
		}

		return $summary;
	}

	/**
	 * Find a single node by id.
	 */
	public static function find( array $elements, string $element_id ): ?array {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $element_id ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = self::find( $element['elements'], $element_id );

				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Merge settings into one node in place.
	 */
	public static function update_settings( array &$elements, string $element_id, array $settings ): bool {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $element_id ) {
				$existing            = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				$element['settings'] = array_merge( $existing, $settings );

				unset( $element );

				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::update_settings( $element['elements'], $element_id, $settings ) ) {
					unset( $element );

					return true;
				}
			}
		}

		unset( $element );

		return false;
	}

	/**
	 * Insert a node beneath a parent, or at the root when the parent is empty.
	 */
	public static function insert( array &$elements, string $parent_id, array $node, ?int $position = null ): bool {
		if ( '' === $parent_id ) {
			if ( null === $position || $position >= count( $elements ) ) {
				$elements[] = $node;
			} else {
				array_splice( $elements, max( 0, $position ), 0, array( $node ) );
			}

			return true;
		}

		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $parent_id ) {
				if ( empty( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
					$element['elements'] = array();
				}

				if ( null === $position || $position >= count( $element['elements'] ) ) {
					$element['elements'][] = $node;
				} else {
					array_splice( $element['elements'], max( 0, $position ), 0, array( $node ) );
				}

				unset( $element );

				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::insert( $element['elements'], $parent_id, $node, $position ) ) {
					unset( $element );

					return true;
				}
			}
		}

		unset( $element );

		return false;
	}
}
