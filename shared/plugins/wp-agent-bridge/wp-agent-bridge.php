<?php
/**
 * Plugin Name:       WP Agent Bridge
 * Description:       Exposes Elementor authoring and content abilities to AI agents over the WordPress MCP Adapter.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Local Dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-agent-bridge
 *
 * Relationship to Elementor's own MCP server:
 * Elementor 4.3+ ships a first-party MCP server at /wp-json/elementor/mcp with
 * abilities for creating pages, building compositions, and managing elements.
 * That is the primary route for layout work and is wired up separately in
 * .vscode/mcp.json. The Elementor abilities in this plugin are intentionally a
 * smaller, deterministic surface (named layout recipes plus direct tree reads
 * and patches) for cases where you want predictable output rather than a model
 * composing a composition. The content abilities here - registered post meta,
 * the portfolio post type, and environment diagnostics - have no Elementor
 * equivalent and are the main reason this plugin exists.
 *
 * @package WP_Agent_Bridge
 */

namespace WP_Agent_Bridge;

defined( 'ABSPATH' ) || exit;

const VERSION  = '0.1.0';
const FILE     = __FILE__;
const DIR      = __DIR__;
const SLUG     = 'wp-agent-bridge';
const CATEGORY = 'wp-agent-bridge';

require_once DIR . '/includes/class-element-id.php';
require_once DIR . '/includes/class-elementor-document.php';
require_once DIR . '/includes/class-widget-catalog.php';
require_once DIR . '/includes/class-layout-recipes.php';
require_once DIR . '/includes/class-post-types.php';
require_once DIR . '/includes/local-env.php';
require_once DIR . '/includes/class-abilities.php';
require_once DIR . '/includes/abilities/class-schema-abilities.php';
require_once DIR . '/includes/abilities/class-page-abilities.php';
require_once DIR . '/includes/abilities/class-structure-abilities.php';
require_once DIR . '/includes/abilities/class-layout-abilities.php';
require_once DIR . '/includes/abilities/class-content-abilities.php';

Post_Types::init();
Abilities::init();

/**
 * Surface a clear admin notice when a hard dependency is missing.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$problems = array();

		if ( ! Elementor_Document::is_available() ) {
			$problems[] = __( 'Elementor is not active. Layout abilities will return errors.', 'wp-agent-bridge' );
		}

		if ( ! Abilities::api_available() ) {
			$problems[] = __( 'The WordPress Abilities API (wp_register_ability) was not found. Abilities cannot be registered.', 'wp-agent-bridge' );
		}

		if ( ! $problems ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong></p><ul style="list-style:disc;margin-left:1.5em;">%s</ul></div>',
			esc_html__( 'WP Agent Bridge', 'wp-agent-bridge' ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static list of translated strings.
			implode( '', array_map( static fn( $problem ) => '<li>' . esc_html( $problem ) . '</li>', $problems ) )
		);
	}
);
