<?php
defined( 'ABSPATH' ) || exit;

/**
 * Checks the AI's input against the page type schema.
 * Anything outside the schema is rejected, whatever the AI attempts.
 * Errors are written so the AI can fix its request and retry.
 */
class ALP_Validator {

	/** @return array|WP_Error Clean sections, or all problems found. */
	public static function validate( array $type, array $sections ) {
		$errors = array();
		$clean  = array();
		$defs   = $type['sections'];
		$counts = array_fill_keys( array_keys( $defs ), 0 );

		if ( empty( $sections ) ) {
			return new WP_Error( 'alp_invalid', 'No sections given.' );
		}

		foreach ( array_values( $sections ) as $i => $section ) {
			$n     = $i + 1;
			$stype = isset( $section['type'] ) ? (string) $section['type'] : '';

			if ( ! isset( $defs[ $stype ] ) ) {
				$errors[] = "Section {$n}: unknown type '{$stype}'. Allowed: " . implode( ', ', array_keys( $defs ) ) . '.';
				continue;
			}
			$def = $defs[ $stype ];
			$counts[ $stype ]++;

			$position = $def['position'] ?? '';
			if ( 'first' === $position && 0 !== $i ) {
				$errors[] = "Section {$n}: '{$stype}' must be the first section.";
			}
			if ( 'last' === $position && count( $sections ) - 1 !== $i ) {
				$errors[] = "Section {$n}: '{$stype}' must be the last section.";
			}

			$fields = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
			foreach ( array_keys( $fields ) as $key ) {
				if ( ! isset( $def['fields'][ $key ] ) ) {
					$errors[] = "Section {$n} ({$stype}): unknown field '{$key}'.";
				}
			}

			$out = array();
			foreach ( $def['fields'] as $key => $rule ) {
				$value = $fields[ $key ] ?? null;
				$empty = null === $value || '' === $value || array() === $value;

				if ( $empty ) {
					if ( ! empty( $rule['required'] ) ) {
						$errors[] = "Section {$n} ({$stype}): '{$key}' is required.";
					}
					continue;
				}

				$result = self::check_field( $value, $rule );
				if ( is_string( $result ) ) {
					$errors[] = "Section {$n} ({$stype}): '{$key}' {$result}";
				} else {
					$out[ $key ] = $result['value'];
				}
			}
			$clean[] = array( 'type' => $stype, 'fields' => $out );
		}

		foreach ( $defs as $stype => $def ) {
			if ( $counts[ $stype ] < $def['min'] ) {
				$errors[] = "Page needs at least {$def['min']} '{$stype}' section(s), got {$counts[ $stype ]}.";
			}
			if ( $counts[ $stype ] > $def['max'] ) {
				$errors[] = "Page allows at most {$def['max']} '{$stype}' section(s), got {$counts[ $stype ]}.";
			}
		}

		if ( $errors ) {
			return new WP_Error( 'alp_invalid', "The page was not created. Fix these and try again:\n- " . implode( "\n- ", $errors ) );
		}
		return $clean;
	}

	/** @return array{value:mixed}|string Clean value, or an error message. */
	private static function check_field( $value, array $rule ) {
		$max = $rule['max_length'] ?? 0;

		switch ( $rule['type'] ) {
			case 'string':
				if ( ! is_string( $value ) ) {
					return 'must be text.';
				}
				$value = sanitize_text_field( $value );
				if ( $max && mb_strlen( $value ) > $max ) {
					return "is too long (max {$max} characters).";
				}
				return array( 'value' => $value );

			case 'text': // longer text; keeps line breaks so the builder can split paragraphs
				if ( ! is_string( $value ) ) {
					return 'must be text.';
				}
				$value = trim( sanitize_textarea_field( $value ) );
				if ( $max && mb_strlen( $value ) > $max ) {
					return "is too long (max {$max} characters).";
				}
				return array( 'value' => $value );

			case 'url':
				$url = esc_url_raw( (string) $value, array( 'http', 'https' ) );
				if ( ! $url || ! wp_http_validate_url( $url ) ) {
					return 'must be a full http(s) URL.';
				}
				return array( 'value' => $url );

			case 'list':
				if ( ! is_array( $value ) ) {
					return 'must be a list of short texts.';
				}
				$items = array();
				foreach ( $value as $item ) {
					if ( ! is_scalar( $item ) ) {
						return 'items must be strings.';
					}
					$item = sanitize_text_field( (string) $item );
					if ( $max && mb_strlen( $item ) > $max ) {
						return "has an item that is too long (max {$max} characters).";
					}
					if ( '' !== $item ) {
						$items[] = $item;
					}
				}
				$min_items = $rule['min_items'] ?? 0;
				$max_items = $rule['max_items'] ?? PHP_INT_MAX;
				if ( count( $items ) < $min_items || count( $items ) > $max_items ) {
					return "must have {$min_items}-{$max_items} items.";
				}
				return array( 'value' => $items );
		}
		return 'has an unsupported type.';
	}
}
