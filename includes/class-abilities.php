<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the tools the AI can use, as WordPress abilities.
 * meta.mcp.public = true lets the MCP Adapter expose them to MCP clients.
 */
class ALP_Abilities {

	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	public static function register_category() {
		wp_register_ability_category( 'landing-pages', array(
			'label'       => 'Landing pages',
			'description' => 'Create landing page drafts from approved page types.',
		) );
	}

	public static function register_abilities() {
		wp_register_ability( 'ai-landing-pages/list-page-types', array(
			'label'               => 'List landing page types',
			'description'         => 'Lists the landing page types you can create. Call this first, then get the schema of the type you need.',
			'category'            => 'landing-pages',
			'input_schema'        => array( 'type' => 'object', 'properties' => new stdClass() ),
			'output_schema'       => array( 'type' => 'array' ),
			'execute_callback'    => array( __CLASS__, 'list_page_types' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
			'meta'                => array( 'mcp' => array( 'public' => true ) ),
		) );
	}

	public static function list_page_types() {
		$out = array();
		foreach ( ALP_Registry::all() as $type ) {
			$out[] = array(
				'slug'        => $type['slug'],
				'label'       => $type['label'],
				'description' => $type['description'],
			);
		}
		return $out;
	}
}
