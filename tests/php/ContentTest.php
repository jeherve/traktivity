<?php
/**
 * Tests for how event content is stored.
 *
 * @package Traktivity
 */

/**
 * Event content is stored as blocks.
 */
class ContentTest extends WP_UnitTestCase {

	/**
	 * Call the private content builder.
	 *
	 * @param string $synopsis Event synopsis.
	 * @param array  $image    Image details.
	 *
	 * @return string Serialized blocks.
	 */
	private function build( $synopsis, $image = array() ) {
		$method = new ReflectionMethod( 'Traktivity_Calls', 'build_post_content' );
		$method->setAccessible( true );

		return $method->invoke( new Traktivity_Calls(), $synopsis, $image );
	}

	/**
	 * A synopsis on its own becomes a single paragraph block.
	 */
	public function test_synopsis_becomes_a_paragraph_block() {
		$content = $this->build( 'Cassian lays low on Ferrix.' );

		$this->assertSame(
			array( 'core/paragraph' ),
			$this->block_names( $content )
		);
		$this->assertStringContainsString( '<p>Cassian lays low on Ferrix.</p>', $content );
	}

	/**
	 * An image is published as an image block ahead of the synopsis.
	 */
	public function test_image_becomes_an_image_block_before_the_paragraph() {
		$content = $this->build(
			'Cassian lays low on Ferrix.',
			array(
				'id'  => 42,
				'url' => 'https://example.org/poster-1024x576.jpg',
				'alt' => 'Andor',
			)
		);

		$this->assertSame(
			array( 'core/image', 'core/paragraph' ),
			$this->block_names( $content )
		);

		$blocks = array_values( array_filter( parse_blocks( $content ), array( $this, 'is_block' ) ) );
		$this->assertSame( 42, $blocks[0]['attrs']['id'] );
		$this->assertSame( 'large', $blocks[0]['attrs']['sizeSlug'] );
		$this->assertSame( 'none', $blocks[0]['attrs']['linkDestination'] );
		$this->assertStringContainsString( 'alt="Andor"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'class="wp-image-42"', $blocks[0]['innerHTML'] );
	}

	/**
	 * The wrapper the plugin used before blocks is gone.
	 */
	public function test_no_poster_image_wrapper() {
		$content = $this->build(
			'A synopsis.',
			array(
				'id'  => 42,
				'url' => 'https://example.org/poster.jpg',
				'alt' => 'Andor',
			)
		);

		$this->assertStringNotContainsString( 'poster-image', $content );
	}

	/**
	 * Only the characters that have to be escaped in element text are escaped.
	 *
	 * The block editor leaves apostrophes alone, so escaping them here would
	 * make stored markup differ from a post saved by hand.
	 */
	public function test_escapes_markup_characters_but_leaves_quotes_alone() {
		$content = $this->build( "Ben & Juliette can't remember <anything>." );

		$this->assertStringContainsString( 'Ben &amp; Juliette', $content );
		$this->assertStringContainsString( '&lt;anything&gt;', $content );
		$this->assertStringContainsString( "can't", $content );
		$this->assertStringNotContainsString( '&#039;', $content );
	}

	/**
	 * Nothing to say means no blocks at all, rather than an empty paragraph.
	 */
	public function test_empty_synopsis_and_no_image_produces_nothing() {
		$this->assertSame( '', $this->build( '' ) );
		$this->assertSame( '', $this->build( '   ' ) );
	}

	/**
	 * The editor should not rewrite the markup the moment someone saves a post.
	 */
	public function test_markup_survives_an_editor_round_trip() {
		$content = $this->build(
			"Ben & Juliette can't remember.",
			array(
				'id'  => 42,
				'url' => 'https://example.org/poster.jpg',
				'alt' => 'Andor',
			)
		);

		$this->assertSame( $content, serialize_blocks( parse_blocks( $content ) ) );
	}

	/**
	 * Block markup has to survive kses, which events meet because they are
	 * created from cron, where there is no user holding unfiltered_html.
	 */
	public function test_blocks_survive_being_stored_without_a_user() {
		wp_set_current_user( 0 );

		$content = $this->build(
			"Ben & Juliette can't remember <anything>.",
			array(
				'id'  => 42,
				'url' => 'https://example.org/poster.jpg',
				'alt' => 'Andor',
			)
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'An event',
				'post_type'    => 'traktivity_event',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		$this->assertSame( $content, get_post( $post_id )->post_content );
		$this->assertSame(
			array( 'core/image', 'core/paragraph' ),
			$this->block_names( get_post( $post_id )->post_content )
		);
	}

	/**
	 * Sideloaded images should describe themselves.
	 */
	public function test_sideloaded_image_gets_alt_text() {
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Andor' );

		$content = $this->build(
			'A synopsis.',
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_image_url( $attachment_id, 'large' ),
				'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			)
		);

		$this->assertStringContainsString( 'alt="Andor"', $content );
		$this->assertStringNotContainsString( 'alt=""', $content );
	}

	/**
	 * Filter callback: is this a real block rather than whitespace between them?
	 *
	 * @param array $block Parsed block.
	 *
	 * @return bool
	 */
	public function is_block( $block ) {
		return ! empty( $block['blockName'] );
	}

	/**
	 * The names of the blocks in a piece of content, in order.
	 *
	 * @param string $content Serialized blocks.
	 *
	 * @return array
	 */
	private function block_names( $content ) {
		return array_values(
			array_map(
				static function ( $block ) {
					return $block['blockName'];
				},
				array_filter( parse_blocks( $content ), array( $this, 'is_block' ) )
			)
		);
	}
}
