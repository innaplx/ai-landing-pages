<?php
defined( 'ABSPATH' ) || exit;

/**
 * Turns validated sections into core Gutenberg blocks and saves a draft.
 * Only core blocks, no inline styles or colors: the theme's theme.json decides the look.
 * Each section is a Group locked to "contentOnly", so editors change text, not layout.
 */
class ALP_Page_Builder {

	public static function create( array $type, $title, array $sections ) {
		$content = '';
		foreach ( $sections as $section ) {
			$method   = 'render_' . $section['type'];
			$content .= self::section( $section['type'], self::$method( $section['fields'] ) ) . "\n\n";
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => $content,
			'meta_input'   => array( '_alp_page_type' => $type['slug'] ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'page_id'      => $post_id,
			'status'       => 'draft',
			'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'preview_url'  => get_preview_post_link( $post_id ),
			'next_step'    => 'Review and publish the draft in WordPress.',
		);
	}

	/* ---------- section renderers ---------- */

	private static function render_hero( $f ) {
		return self::heading( $f['headline'], 1 )
			. ( isset( $f['subheadline'] ) ? self::paragraph( $f['subheadline'] ) : '' )
			. self::paragraph( $f['date_time'] )
			. self::button( $f['button_label'], $f['button_url'] );
	}

	private static function render_speaker( $f ) {
		return self::heading( $f['name'], 3 )
			. self::paragraph( $f['role'] )
			. ( isset( $f['bio'] ) ? self::paragraph( $f['bio'] ) : '' );
	}

	private static function render_agenda( $f ) {
		return self::heading( $f['heading'], 2 ) . self::listing( $f['items'] );
	}

	private static function render_faq( $f ) {
		return '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( $f['question'] ) . '</summary>'
			. self::paragraph( $f['answer'] )
			. '</details><!-- /wp:details -->';
	}

	private static function render_cta( $f ) {
		return self::heading( $f['headline'], 2 ) . self::button( $f['button_label'], $f['button_url'] );
	}

	/* ---------- core block helpers ---------- */

	private static function section( $type, $inner ) {
		$class = 'alp-section alp-' . $type;
		$attrs = wp_json_encode( array(
			'tagName'      => 'section',
			'className'    => $class,
			'templateLock' => 'contentOnly',
			'layout'       => array( 'type' => 'constrained' ),
		) );
		return "<!-- wp:group {$attrs} --><section class=\"wp-block-group {$class}\">{$inner}</section><!-- /wp:group -->";
	}

	private static function heading( $text, $level ) {
		$attrs = 2 === $level ? '' : ' ' . wp_json_encode( array( 'level' => $level ) );
		return "<!-- wp:heading{$attrs} --><h{$level} class=\"wp-block-heading\">" . esc_html( $text ) . "</h{$level}><!-- /wp:heading -->";
	}

	private static function paragraph( $text ) {
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
	}

	private static function button( $label, $url ) {
		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button">'
			. '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'
			. '</div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	private static function listing( array $items ) {
		$li = '';
		foreach ( $items as $item ) {
			$li .= '<!-- wp:list-item --><li>' . esc_html( $item ) . '</li><!-- /wp:list-item -->';
		}
		return '<!-- wp:list --><ul class="wp-block-list">' . $li . '</ul><!-- /wp:list -->';
	}
}
