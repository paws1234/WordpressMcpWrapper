<?php
/**
 * Canonical element builders.
 *
 * These helpers are the single source of truth for the element JSON the agent is
 * allowed to produce. Keeping widget markup here means the model never has to
 * hand-write Elementor's settings shape.
 *
 * Elementor 3.16+ renders the container element by default; legacy installs still
 * use section/column. row()/column() emit whichever the site actually supports.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

class Widget_Catalog {

	/**
	 * Default gap applied to generated containers.
	 */
	public static function default_gap(): array {
		return array(
			'column'   => '24',
			'row'      => '24',
			'isLinked' => true,
			'unit'     => 'px',
			'size'     => 24,
		);
	}

	/**
	 * Default container settings.
	 */
	public static function container_defaults(): array {
		return array(
			'content_width'  => 'boxed',
			'flex_direction' => 'column',
			'flex_gap'       => self::default_gap(),
		);
	}

	/**
	 * Padding dimensions helper.
	 */
	public static function padding( string $top, string $right, string $bottom, string $left ): array {
		return array(
			'unit'     => 'px',
			'top'      => $top,
			'right'    => $right,
			'bottom'   => $bottom,
			'left'     => $left,
			'isLinked' => false,
		);
	}

	/**
	 * A container element.
	 */
	public static function container( array $settings = array(), array $elements = array(), bool $inner = false ): array {
		return array(
			'id'       => Element_ID::generate(),
			'elType'   => 'container',
			'settings' => array_merge( self::container_defaults(), $settings ),
			'elements' => array_values( $elements ),
			'isInner'  => $inner,
		);
	}

	/**
	 * A legacy section element.
	 */
	public static function section( array $settings = array(), array $columns = array(), bool $inner = false ): array {
		return array(
			'id'       => Element_ID::generate(),
			'elType'   => 'section',
			'settings' => array_merge( array( 'layout' => 'boxed' ), $settings ),
			'elements' => array_values( $columns ),
			'isInner'  => $inner,
		);
	}

	/**
	 * A legacy column element.
	 */
	public static function legacy_column( array $settings = array(), array $elements = array(), bool $inner = false ): array {
		return array(
			'id'       => Element_ID::generate(),
			'elType'   => 'column',
			'settings' => $settings,
			'elements' => array_values( $elements ),
			'isInner'  => $inner,
		);
	}

	/**
	 * A horizontal row that adapts to the active layout mode.
	 */
	public static function row( array $settings = array(), array $elements = array(), bool $inner = false ): array {
		if ( Elementor_Document::uses_containers() ) {
			return self::container( array_merge( array( 'flex_direction' => 'row' ), $settings ), $elements, $inner );
		}

		return self::section( $settings, array( self::legacy_column( array(), $elements ) ), $inner );
	}

	/**
	 * A column of a given percentage width that adapts to the layout mode.
	 */
	public static function column( array $settings = array(), array $elements = array(), ?int $width = null ): array {
		if ( Elementor_Document::uses_containers() ) {
			$settings = array_merge( $settings, array( 'flex_direction' => 'column' ) );

			if ( $width ) {
				$settings['width'] = array(
					'unit' => '%',
					'size' => $width,
				);
			}

			return self::container( $settings, $elements, true );
		}

		$size = $width ? $width : 100;

		return self::legacy_column(
			array_merge(
				array(
					'_column_size' => (int) round( $size / 100 * 12 ),
					'_inline_size' => $size,
				),
				$settings
			),
			$elements,
			false
		);
	}

	/**
	 * A generic widget element.
	 */
	public static function widget( string $type, array $settings = array() ): array {
		return array(
			'id'         => Element_ID::generate(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Widget types this bridge will emit.
	 */
	public static function supported(): array {
		return array( 'heading', 'text-editor', 'button', 'image', 'icon-box', 'icon-list', 'divider', 'spacer' );
	}

	/**
	 * Heading widget.
	 */
	public static function heading( string $text, string $tag = 'h2', array $extra = array() ): array {
		if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) {
			$tag = 'h2';
		}

		return self::widget(
			'heading',
			array_merge(
				array(
					'title'       => $text,
					'header_size' => $tag,
				),
				$extra
			)
		);
	}

	/**
	 * Text editor widget. Expects HTML.
	 */
	public static function text( string $html, array $extra = array() ): array {
		return self::widget( 'text-editor', array_merge( array( 'editor' => $html ), $extra ) );
	}

	/**
	 * Button widget.
	 */
	public static function button( string $text, string $url = '#', array $extra = array() ): array {
		return self::widget(
			'button',
			array_merge(
				array(
					'text'  => $text,
					'link'  => array(
						'url'               => $url,
						'is_external'       => '',
						'nofollow'          => '',
						'custom_attributes' => '',
					),
					'size'  => 'md',
					'align' => 'left',
				),
				$extra
			)
		);
	}

	/**
	 * Image widget.
	 */
	public static function image( string $url, int $attachment_id = 0, array $extra = array() ): array {
		return self::widget(
			'image',
			array_merge(
				array(
					'image'      => array(
						'url'    => $url,
						'id'     => $attachment_id,
						'alt'    => '',
						'source' => 'library',
					),
					'image_size' => 'large',
					'align'      => 'center',
				),
				$extra
			)
		);
	}

	/**
	 * Icon box widget.
	 */
	public static function icon_box( string $title, string $description, string $icon = 'fas fa-star', array $extra = array() ): array {
		return self::widget(
			'icon-box',
			array_merge(
				array(
					'selected_icon'    => self::icon( $icon ),
					'title_text'       => $title,
					'description_text' => $description,
					'position'         => 'top',
					'title_size'       => 'h3',
				),
				$extra
			)
		);
	}

	/**
	 * Icon list widget.
	 *
	 * @param array $items List of array( 'text' => string, 'icon' => string ).
	 */
	public static function icon_list( array $items, array $extra = array() ): array {
		$list = array();

		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$item = array( 'text' => $item );
			}

			$list[] = array(
				'_id'           => Element_ID::generate(),
				'text'          => isset( $item['text'] ) ? (string) $item['text'] : '',
				'selected_icon' => self::icon( isset( $item['icon'] ) ? (string) $item['icon'] : 'fas fa-check' ),
			);
		}

		return self::widget(
			'icon-list',
			array_merge(
				array(
					'icon_list'     => $list,
					'space_between' => array(
						'unit' => 'px',
						'size' => 8,
					),
				),
				$extra
			)
		);
	}

	/**
	 * Divider widget.
	 */
	public static function divider( array $extra = array() ): array {
		return self::widget(
			'divider',
			array_merge(
				array(
					'style'  => 'solid',
					'weight' => array(
						'unit' => 'px',
						'size' => 1,
					),
				),
				$extra
			)
		);
	}

	/**
	 * Spacer widget.
	 */
	public static function spacer( int $pixels = 40, array $extra = array() ): array {
		return self::widget(
			'spacer',
			array_merge(
				array(
					'space' => array(
						'unit' => 'px',
						'size' => $pixels,
					),
				),
				$extra
			)
		);
	}

	/**
	 * Font Awesome icon descriptor as Elementor stores it.
	 */
	public static function icon( string $value ): array {
		$library = 'fa-solid';

		if ( str_starts_with( $value, 'far ' ) ) {
			$library = 'fa-regular';
		} elseif ( str_starts_with( $value, 'fab ' ) ) {
			$library = 'fa-brands';
		}

		return array(
			'value'   => $value,
			'library' => $library,
		);
	}

	/**
	 * Machine readable description of what the agent may emit.
	 */
	public static function schema(): array {
		return array(
			'layout_mode'    => Elementor_Document::uses_containers() ? 'container' : 'section',
			'supported_types' => self::supported(),
			'container_defaults' => self::container_defaults(),
			'widgets'        => array(
				'heading'     => array(
					'description' => 'Section or page title.',
					'settings'    => array(
						'title'       => 'string (required)',
						'header_size' => 'h1|h2|h3|h4|h5|h6|div|span|p',
						'align'       => 'left|center|right|justify',
						'title_color' => '#rrggbb',
					),
				),
				'text-editor' => array(
					'description' => 'Rich text block. Accepts a small subset of HTML.',
					'settings'    => array(
						'editor'     => 'string, HTML (required)',
						'align'      => 'left|center|right|justify',
						'text_color' => '#rrggbb',
					),
				),
				'button'      => array(
					'description' => 'Call to action link.',
					'settings'    => array(
						'text'               => 'string (required)',
						'link'               => array( 'url' => 'string', 'is_external' => 'string', 'nofollow' => 'string' ),
						'align'              => 'left|center|right|justify',
						'size'               => 'xs|sm|md|lg|xl',
						'background_color'   => '#rrggbb',
						'button_text_color'  => '#rrggbb',
						'border_radius'      => array( 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => true ),
					),
				),
				'image'       => array(
					'description' => 'Image from the media library or a remote URL.',
					'settings'    => array(
						'image'      => array( 'url' => 'string (required)', 'id' => 'int', 'alt' => 'string' ),
						'image_size' => 'thumbnail|medium|large|full',
						'align'      => 'left|center|right',
					),
				),
				'icon-box'    => array(
					'description' => 'Icon, title, and description card. Best used inside a column.',
					'settings'    => array(
						'selected_icon'    => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ),
						'title_text'       => 'string (required)',
						'description_text' => 'string (required)',
						'position'         => 'top|left|right',
						'title_size'       => 'h1|h2|h3|h4|h5|h6|div|span|p',
					),
				),
				'icon-list'   => array(
					'description' => 'Bulleted list with icons.',
					'settings'    => array(
						'icon_list' => 'array of { text: string, selected_icon: { value, library } }',
					),
				),
				'divider'     => array(
					'description' => 'Horizontal rule.',
					'settings'    => array(
						'style'  => 'solid|double|dotted|dashed',
						'weight' => array( 'unit' => 'px', 'size' => 1 ),
					),
				),
				'spacer'      => array(
					'description' => 'Vertical whitespace.',
					'settings'    => array( 'space' => array( 'unit' => 'px', 'size' => 40 ) ),
				),
			),
			'container_controls' => array(
				'flex_direction' => 'row|column|row-reverse|column-reverse',
				'flex_wrap'      => 'wrap|nowrap|wrap-reverse',
				'content_width'  => 'boxed|full',
				'flex_gap'       => array( 'column' => '24', 'row' => '24', 'isLinked' => true, 'unit' => 'px', 'size' => 24 ),
				'padding'        => array( 'unit' => 'px', 'top' => '80', 'right' => '24', 'bottom' => '80', 'left' => '24', 'isLinked' => false ),
				'background_background' => 'classic',
				'background_color'      => '#rrggbb',
				'width'                 => array( 'unit' => '%', 'size' => 33 ),
			),
			'notes'          => array(
				'Every element needs a unique 7 character hex id; ids are generated automatically when omitted.',
				'Prefer the render-layout-recipe ability over writing containers by hand.',
			),
		);
	}
}
