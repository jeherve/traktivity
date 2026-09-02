<?php
/**
 * Tests for the TMDb credits appended to event pages.
 *
 * @package Traktivity
 */

/**
 * Traktivity_Content::credits().
 */
class CreditsTest extends WP_UnitTestCase {

	/**
	 * Markup containing an image, which is what triggers the credits.
	 *
	 * @var string
	 */
	private $with_image = '<figure><img src="https://example.org/a.jpg" alt="x" /></figure>';

	/**
	 * Put WordPress on a single event, which is the only place credits show.
	 *
	 * @return Traktivity_Content
	 */
	private function on_an_event_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );
		$this->go_to( get_permalink( $post_id ) );

		return new Traktivity_Content();
	}

	/**
	 * The credit line and its link are shown under an event that has an image.
	 */
	public function test_adds_credits_to_an_event_with_an_image() {
		$output = $this->on_an_event_page()->credits( $this->with_image );

		$this->assertStringContainsString( 'tmdb_credits', $output );
		$this->assertStringContainsString( 'href="https://www.themoviedb.org/"', $output );
	}

	/**
	 * Nothing to credit when the event has no image.
	 */
	public function test_leaves_an_event_without_an_image_alone() {
		$content = '<p>Just a synopsis.</p>';

		$this->assertSame( $content, $this->on_an_event_page()->credits( $content ) );
	}

	/**
	 * Credits belong on event pages, not on every post on the site.
	 */
	public function test_leaves_other_posts_alone() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$content = new Traktivity_Content();

		$this->assertSame( $this->with_image, $content->credits( $this->with_image ) );
	}

	/**
	 * The credit string carries its own markup so translators can move the
	 * link, which means a translation decides what HTML reaches the page.
	 */
	public function test_a_translation_cannot_introduce_markup() {
		add_filter(
			'gettext',
			static function ( $translated, $text, $domain ) {
				if ( 'traktivity' === $domain && 0 === strpos( $text, 'Image source:' ) ) {
					return 'Image source: <a href="%s" onclick="alert(1)">tmdb</a>'
						. '<script>alert(2)</script><iframe src="https://evil.test"></iframe>';
				}

				return $translated;
			},
			10,
			3
		);

		$output = $this->on_an_event_page()->credits( $this->with_image );

		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '<iframe', $output );

		// The link itself still survives.
		$this->assertStringContainsString( 'href="https://www.themoviedb.org/"', $output );
	}
}
