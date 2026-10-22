<?php
/**
 * Plugin Name: AI Landing Pages
 * Description: Lets any MCP client (e.g. Claude) create landing page drafts from a strict schema, rendered as core blocks.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Inna Plaksin
 */

defined( 'ABSPATH' ) || exit;

define( 'ALP_DIR', plugin_dir_path( __FILE__ ) );

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
