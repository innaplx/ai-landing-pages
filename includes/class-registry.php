<?php
defined( 'ABSPATH' ) || exit;

/**
 * Loads page type definitions from /page-types/*.json.
 * Adding a page type = adding one JSON file.
 */
class ALP_Registry {

	public static function all() {
		$types = array();
		foreach ( glob( ALP_DIR . 'page-types/*.json' ) as $file ) {
			$data = json_decode( file_get_contents( $file ), true );
			if ( is_array( $data ) && ! empty( $data['slug'] ) ) {
				$types[ $data['slug'] ] = $data;
			}
		}
		return $types;
	}

	public static function get( $slug ) {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}
}
