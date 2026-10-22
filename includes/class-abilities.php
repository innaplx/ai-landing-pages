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
			'permission_callback' => array( __CLASS__, 'can_edit' ),
			'meta'                => array( 'mcp' => array( 'public' => true ) ),
		) );

		wp_register_ability( 'ai-landing-pages/get-page-type-schema', array(
			'label'               => 'Get a landing page type schema',
			'description'         => 'Returns the sections a page type allows: which are required, how many times each may appear, their order rules, and the fields of each. Read this before creating a page and follow it exactly.',
			'category'            => 'landing-pages',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array( 'page_type' => array( 'type' => 'string', 'description' => 'Slug from list-page-types, e.g. webinar.' ) ),
				'required'   => array( 'page_type' ),
			),
			'execute_callback'    => array( __CLASS__, 'get_page_type_schema' ),
			'permission_callback' => array( __CLASS__, 'can_edit' ),
			'meta'                => array( 'mcp' => array( 'public' => true ) ),
		) );

		wp_register_ability( 'ai-landing-pages/create-landing-page', array(
			'label'               => 'Create a landing page draft',
			'description'         => 'Creates a landing page as a DRAFT from an ordered list of sections. Send content only (plain text and URLs), never HTML. Sections must follow the page type schema; a section type can be left out or repeated within its min/max. If the request is invalid, nothing is created and you get a list of problems to fix. Returns links to edit and preview the draft.',
			'category'            => 'landing-pages',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'page_type' => array( 'type' => 'string' ),
					'title'     => array( 'type' => 'string', 'description' => 'Internal page title shown in WordPress.' ),
					'sections'  => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'type'   => array( 'type' => 'string', 'description' => 'Section type from the schema.' ),
								'fields' => array( 'type' => 'object', 'description' => 'Field values for this section.' ),
							),
							'required'   => array( 'type', 'fields' ),
						),
					),
				),
				'required'   => array( 'page_type', 'title', 'sections' ),
			),
			'execute_callback'    => array( __CLASS__, 'create_landing_page' ),
			'permission_callback' => array( __CLASS__, 'can_edit' ),
			'meta'                => array( 'mcp' => array( 'public' => true ) ),
		) );
	}

	public static function can_edit() {
		return current_user_can( 'edit_pages' );
	}

	public static function get_page_type_schema( $input ) {
		$type = ALP_Registry::get( $input['page_type'] ?? '' );
		if ( ! $type ) {
			return new WP_Error( 'alp_unknown_type', 'Unknown page type. Call list-page-types to see the available types.' );
		}
		return $type;
	}

	public static function create_landing_page( $input ) {
		$type = ALP_Registry::get( $input['page_type'] ?? '' );
		if ( ! $type ) {
			return new WP_Error( 'alp_unknown_type', 'Unknown page type. Call list-page-types to see the available types.' );
		}
		$sections = ALP_Validator::validate( $type, (array) ( $input['sections'] ?? array() ) );
		if ( is_wp_error( $sections ) ) {
			return $sections;
		}
		return ALP_Page_Builder::create( $type, $input['title'] ?? $type['label'], $sections );
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
