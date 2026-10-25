<?php
/**
 * Plugin Name: AI Landing Pages
 * Description: Lets any MCP client (e.g. Claude) create landing page drafts from a strict schema, rendered as core blocks.
 * Version: 0.2.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Inna Plaksin
 */

defined( 'ABSPATH' ) || exit;

define( 'ALP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALP_VERSION', '0.2.0' );

require_once ALP_DIR . 'includes/class-registry.php';
require_once ALP_DIR . 'includes/class-validator.php';
require_once ALP_DIR . 'includes/class-page-builder.php';
require_once ALP_DIR . 'includes/class-abilities.php';

// The Abilities API is in core since WordPress 6.9.
if ( ! function_exists( 'wp_register_ability' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>AI Landing Pages needs WordPress 6.9+ (Abilities API).</p></div>';
	} );
	return;
}

ALP_Abilities::init();

/** True when the post was made by this plugin (meta set on create). */
function alp_is_landing_page( $post_id ) {
	return $post_id && '' !== get_post_meta( $post_id, '_alp_page_type', true );
}

/**
 * Layout-only styles: spacing, alignment and grid. No fonts and no fixed colors.
 * Values come from the theme's own presets (theme.json), so every theme keeps its look.
 * Loaded only on landing pages made by the plugin, on the front end and in the editor.
 */
function alp_enqueue_styles() {
	wp_enqueue_style( 'ai-landing-pages', plugins_url( 'assets/alp.css', __FILE__ ), array(), ALP_VERSION );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( 'page' ) && alp_is_landing_page( get_queried_object_id() ) ) {
		alp_enqueue_styles();
	}
} );

// enqueue_block_assets also loads the CSS inside the editor iframe.
add_action( 'enqueue_block_assets', function () {
	if ( ! is_admin() ) {
		return; // front end is handled above
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$post   = get_post();
	if ( $screen && 'page' === $screen->post_type && $post && alp_is_landing_page( $post->ID ) ) {
		alp_enqueue_styles();
	}
} );
