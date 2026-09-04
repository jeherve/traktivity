<?php
/**
 * Block templates.
 *
 * Default templates for the post type and taxonomies this plugin registers, so
 * a synced site looks right in a stock block theme without anyone assembling
 * blocks by hand.
 *
 * They are off until switched on from the dashboard. Templates change what a
 * site looks like, and doing that to an existing site on an update, with no
 * visible switch anywhere, is not a reasonable default. See issue #683.
 *
 * @package Traktivity
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Register the plugin's default block templates.
 *
 * @since 3.1.0
 */
class Traktivity_Templates {

	/**
	 * Option holding which templates the site owner has switched on.
	 *
	 * A single array keyed by template slug, so the settings screen reads and
	 * writes one option rather than one per template.
	 *
	 * @var string
	 */
	const OPTION = 'traktivity_templates';

	/**
	 * Hook everything up.
	 *
	 * @since 3.1.0
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_templates' ), 20 );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_show_archive' ) );
	}

	/**
	 * The templates this plugin can provide.
	 *
	 * Keyed by template slug. `file` is the markup to use, which several
	 * taxonomies share, and `title` and `description` are what the Site Editor
	 * shows in its template list.
	 *
	 * @since 3.1.0
	 *
	 * @return array<string, array{file: string, title: string, description: string}>
	 */
	public static function available(): array {
		$shared = __( 'Traktivity archive', 'traktivity' );

		$templates = array(
			'single-traktivity_event'  => array(
				'file'        => 'single-traktivity_event.html',
				'title'       => __( 'Single watch entry', 'traktivity' ),
				'description' => __( 'One thing you watched, with its series, episode numbers and links out.', 'traktivity' ),
			),
			'archive-traktivity_event' => array(
				'file'        => 'archive-traktivity_event.html',
				'title'       => __( 'Everything watched', 'traktivity' ),
				'description' => __( 'The full archive of what you have watched.', 'traktivity' ),
			),
			'taxonomy-trakt_show'      => array(
				'file'        => 'taxonomy-trakt_show.html',
				'title'       => __( 'One series', 'traktivity' ),
				'description' => __( 'Every episode logged for one series, oldest first.', 'traktivity' ),
			),
		);

		/*
		 * Genre, year and type archives all want the same layout, so they share
		 * one file rather than carrying three near-identical copies. They are
		 * registered separately because the template hierarchy asks for a slug
		 * per taxonomy, and a bare `taxonomy` template would apply to a site's
		 * categories and tags too.
		 */
		$generic = array(
			'taxonomy-trakt_genre' => __( 'Genre', 'traktivity' ),
			'taxonomy-trakt_year'  => __( 'Release year', 'traktivity' ),
			'taxonomy-trakt_type'  => __( 'Type', 'traktivity' ),
		);

		foreach ( $generic as $slug => $label ) {
			$templates[ $slug ] = array(
				'file'        => 'taxonomy-traktivity.html',
				/* translators: %s: name of the taxonomy, e.g. Genre. */
				'title'       => sprintf( __( 'Watch entries by %s', 'traktivity' ), $label ),
				'description' => $shared,
			);
		}

		return $templates;
	}

	/**
	 * Whether a template is switched on.
	 *
	 * Off unless the option says otherwise, on updates and fresh installs
	 * alike.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Template slug.
	 *
	 * @return bool
	 */
	public static function is_enabled( string $slug ): bool {
		$enabled = get_option( self::OPTION );

		return is_array( $enabled ) && ! empty( $enabled[ $slug ] );
	}

	/**
	 * Read one template's markup, with its strings translated.
	 *
	 * The markup lives in .html files so it can be edited and diffed like
	 * markup rather than buried in a heredoc. Anything a reader sees is a
	 * placeholder here and goes through __() on the way out.
	 *
	 * @since 3.1.0
	 *
	 * @param string $file File name under templates/.
	 *
	 * @return string Block markup, or an empty string when unreadable.
	 */
	public static function content( string $file ): string {
		$path = TRAKTIVITY__PLUGIN_DIR . 'templates/' . basename( $file );

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file bundled with the plugin.
		$markup = (string) file_get_contents( $path );

		return strtr(
			$markup,
			array(
				'{{ARCHIVE_TITLE}}' => esc_html__( 'Everything watched', 'traktivity' ),
				'{{NO_RESULTS}}'    => esc_html__( 'Nothing logged here yet.', 'traktivity' ),
			)
		);
	}

	/**
	 * Register the templates the site owner has switched on.
	 *
	 * @since 3.1.0
	 */
	public static function register_templates(): void {
		// The registry is only consulted by block themes, so there is nothing to do otherwise.
		if ( ! wp_is_block_theme() ) {
			return;
		}

		foreach ( self::available() as $slug => $template ) {
			if ( ! self::is_enabled( $slug ) ) {
				continue;
			}

			$content = self::content( $template['file'] );

			/*
			 * An empty template would render a blank page where the theme's own
			 * would have rendered something, so a missing file means falling
			 * through to the theme rather than registering nothing.
			 */
			if ( '' === $content ) {
				continue;
			}

			register_block_template(
				'traktivity//' . $slug,
				array(
					'title'       => $template['title'],
					'description' => $template['description'],
					'content'     => $content,
				)
			);
		}
	}

	/**
	 * Read a single series' archive oldest first.
	 *
	 * A series is a run, watched in order, so newest-first is simply the wrong
	 * way round for it. Only applied while our own series template is in use,
	 * since a theme's template is entitled to its own ordering.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Query $query The query about to run.
	 */
	public static function order_show_archive( $query ): void {
		if (
			! $query instanceof WP_Query
			|| is_admin()
			|| ! $query->is_main_query()
			|| ! $query->is_tax( 'trakt_show' )
			|| ! self::is_enabled( 'taxonomy-trakt_show' )
		) {
			return;
		}

		/**
		 * Filter the order a single series' archive reads in.
		 *
		 * @since 3.1.0
		 *
		 * @param string $order Either ASC or DESC. Defaults to ASC.
		 */
		$order = (string) apply_filters( 'traktivity_show_archive_order', 'ASC' );

		$query->set( 'order', 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC' );
	}
}

Traktivity_Templates::init();
