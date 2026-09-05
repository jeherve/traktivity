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
		add_filter( 'get_block_template', array( __CLASS__, 'provide_template_part' ), 10, 3 );
		add_filter( 'get_block_templates', array( __CLASS__, 'list_template_parts' ), 10, 3 );
		add_action( 'pre_get_posts', array( __CLASS__, 'shape_archive_query' ) );
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
		/*
		 * basename() on the file, so a name cannot walk out of the directory,
		 * and the subdirectory is chosen here rather than taken from the
		 * caller.
		 */
		$directory = 0 === strpos( $file, 'parts/' ) ? 'templates/parts/' : 'templates/';
		$path      = TRAKTIVITY__PLUGIN_DIR . $directory . basename( $file );

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
				'{{RECENT_TITLE}}'  => esc_html__( 'Recently watched', 'traktivity' ),
				'{{LATEST_TITLE}}'  => esc_html__( 'Last watched', 'traktivity' ),
				'{{INDEX_TITLE}}'   => esc_html__( 'Every series', 'traktivity' ),
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
	 * Whether one of our templates is the one actually rendering.
	 *
	 * Switched on is not enough. The registry is only consulted by block
	 * themes, and where the active theme carries the same slug its own
	 * template wins over ours. In both cases the markup on screen belongs to
	 * somebody else, and shaping the query would reorder a page we are not
	 * drawing. The settings screen says as much on a theme-provided template,
	 * so it had better be true.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Template slug.
	 *
	 * @return bool
	 */
	private static function applies( string $slug ): bool {
		if ( ! self::is_enabled( $slug ) || ! wp_is_block_theme() ) {
			return false;
		}

		return ! in_array( $slug, self::theme_provided_slugs(), true );
	}

	/**
	 * Shape the archive queries our templates inherit.
	 *
	 * Two things the templates cannot do for themselves.
	 *
	 * A Query Loop with `inherit` set takes its page size from the main query,
	 * which means from the site's "Blog pages show at most" setting rather than
	 * from the `perPage` in the template. At the default of ten that leaves a
	 * four-column grid two and a half rows tall, so the number the template
	 * declares is set here instead of being quietly ignored.
	 *
	 * And a single series reads oldest first, because a series is a run watched
	 * in order and newest-first is the wrong way round for it.
	 *
	 * Both apply only while our own template is in use. A theme's template is
	 * entitled to its own ordering and its own page size.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Query $query The query about to run.
	 */
	public static function shape_archive_query( $query ): void {
		if ( ! $query instanceof WP_Query || is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_tax( 'trakt_show' ) && self::applies( 'taxonomy-trakt_show' ) ) {
			/**
			 * Filter the order a single series' archive reads in.
			 *
			 * @since 3.1.0
			 *
			 * @param string $order Either ASC or DESC. Defaults to ASC.
			 */
			$order = (string) apply_filters( 'traktivity_show_archive_order', 'ASC' );

			$query->set( 'order', 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC' );
			$query->set( 'posts_per_page', self::posts_per_page( 'taxonomy-trakt_show', 48 ) );

			return;
		}

		if ( $query->is_post_type_archive( 'traktivity_event' ) && self::applies( 'archive-traktivity_event' ) ) {
			$query->set( 'posts_per_page', self::posts_per_page( 'archive-traktivity_event', 24 ) );

			return;
		}

		foreach ( array( 'trakt_genre', 'trakt_year', 'trakt_type' ) as $taxonomy ) {
			if ( $query->is_tax( $taxonomy ) && self::applies( 'taxonomy-' . $taxonomy ) ) {
				$query->set( 'posts_per_page', self::posts_per_page( 'taxonomy-' . $taxonomy, 24 ) );

				return;
			}
		}
	}

	/**
	 * How many entries one of our archives shows per page.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug    Template slug the number belongs to.
	 * @param int    $default_count What the template's own markup declares.
	 *
	 * @return int Entries per page.
	 */
	private static function posts_per_page( string $slug, int $default_count ): int {
		/**
		 * Filter how many entries one of Traktivity's archives shows per page.
		 *
		 * Return the site's own setting to hand control back to it:
		 * `get_option( 'posts_per_page' )`.
		 *
		 * @since 3.1.0
		 *
		 * @param int    $default_count Entries per page.
		 * @param string $slug          Template slug.
		 */
		$number = (int) apply_filters( 'traktivity_archive_posts_per_page', $default_count, $slug );

		return $number > 0 ? $number : $default_count;
	}

	/**
	 * The editable template parts this plugin can provide.
	 *
	 * Keyed by part slug. Unlike the templates above, these have no automatic
	 * placement: nothing renders them until a site owner adds core's Template
	 * Part block to a template and picks one. See issue #699.
	 *
	 * @since 3.1.0
	 *
	 * @return array<string, array{file: string, title: string, description: string}>
	 */
	public static function available_parts(): array {
		return array(
			'traktivity-recent-watches' => array(
				'file'        => 'parts/traktivity-recent-watches.html',
				'title'       => __( 'Recently watched', 'traktivity' ),
				'description' => __( 'The last few things you watched, as a grid of cards.', 'traktivity' ),
			),
			'traktivity-recent-compact' => array(
				'file'        => 'parts/traktivity-recent-compact.html',
				'title'       => __( 'Recently watched, compact', 'traktivity' ),
				'description' => __( 'A short text list, for a sidebar or a footer.', 'traktivity' ),
			),
		);
	}

	/**
	 * Whether a part is needed by something that is switched on.
	 *
	 * A part is not switched on in its own right. It exists to give a
	 * placement its markup, so it is provided exactly when a placement using
	 * it is on. One switch per thing the site owner actually sees.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Part slug.
	 *
	 * @return bool
	 */
	public static function part_is_needed( string $slug ): bool {
		foreach ( Traktivity_Placements::available() as $placement_slug => $placement ) {
			if (
				'part' === $placement['type']
				&& $placement['part'] === $slug
				&& Traktivity_Placements::is_enabled( $placement_slug )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Which of our template slugs the active theme already covers.
	 *
	 * A theme's own template wins over ours, so offering to switch one on when
	 * the theme already answers for it would be offering nothing. Asked once
	 * for every slug rather than once per template.
	 *
	 * @since 3.1.0
	 *
	 * @return string[] Slugs the theme provides.
	 */
	public static function theme_provided_slugs(): array {
		if ( ! wp_is_block_theme() ) {
			return array();
		}

		$slugs      = array_keys( self::available() );
		$found      = get_block_templates( array( 'slug__in' => $slugs ), 'wp_template' );
		$from_theme = array();

		foreach ( $found as $template ) {
			if ( 'theme' === $template->source ) {
				$from_theme[] = $template->slug;
			}
		}

		return array_values( array_unique( $from_theme ) );
	}

	/**
	 * Everything the settings screen needs to describe what we can provide.
	 *
	 * Templates and parts in one list, each carrying the type the Site Editor
	 * wants in a URL, so the dashboard does not have to know which is which.
	 *
	 * @since 3.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_settings(): array {
		$theme_provides = self::theme_provided_slugs();
		$entries        = array();

		foreach ( self::available() as $slug => $template ) {
			$entries[] = array(
				'slug'          => $slug,
				'type'          => 'wp_template',
				'title'         => $template['title'],
				'description'   => $template['description'],
				'enabled'       => self::is_enabled( $slug ),
				'themeProvides' => in_array( $slug, $theme_provides, true ),
			);
		}

		/*
		 * Parts are absent on purpose. One is never switched on by itself; it
		 * backs a placement, and the placement's card links to it for editing.
		 */
		return array_merge( $entries, Traktivity_Placements::for_settings() );
	}

	/**
	 * Save which templates and parts are switched on.
	 *
	 * Only slugs this plugin knows about are stored, so a stale or invented
	 * key cannot accumulate in the option.
	 *
	 * @since 3.1.0
	 *
	 * @param array $enabled Slug => boolean.
	 *
	 * @return array The stored value.
	 */
	public static function save_enabled( array $enabled ): array {
		$known = array_merge(
			array_keys( self::available() ),
			array_keys( Traktivity_Placements::available() )
		);
		$clean = array();

		foreach ( $known as $slug ) {
			if ( ! empty( $enabled[ $slug ] ) ) {
				$clean[ $slug ] = true;
			}
		}

		update_option( self::OPTION, $clean );

		return $clean;
	}

	/**
	 * The ID a template part is known by.
	 *
	 * Namespaced to the active theme on purpose. The moment someone edits one
	 * of these in the Site Editor, WordPress saves a real wp_template_part
	 * post under that ID, get_block_template() stops coming back empty, and
	 * the filter below quietly steps aside. The site owner gets an editable
	 * default with nothing to migrate.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Part slug.
	 *
	 * @return string
	 */
	public static function part_id( string $slug ): string {
		return get_stylesheet() . '//' . $slug;
	}

	/**
	 * Build the WP_Block_Template object for one of our parts.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Part slug.
	 * @param array  $part Part definition.
	 *
	 * @return WP_Block_Template|null Null when the markup is unreadable.
	 */
	private static function build_part( string $slug, array $part ): ?WP_Block_Template {
		$content = self::content( $part['file'] );

		if ( '' === $content ) {
			return null;
		}

		$template                 = new WP_Block_Template();
		$template->id             = self::part_id( $slug );
		$template->theme          = get_stylesheet();
		$template->slug           = $slug;
		$template->type           = 'wp_template_part';
		$template->area           = 'uncategorized';
		$template->source         = 'plugin';
		$template->status         = 'publish';
		$template->has_theme_file = false;
		$template->is_custom      = true;
		$template->title          = $part['title'];
		$template->description    = $part['description'];
		$template->content        = $content;

		return $template;
	}

	/**
	 * Add our parts to the lists the editor reads.
	 *
	 * The single-template filter answers for one ID, which is enough to render
	 * a part and to open it from a direct link, and not enough for anything to
	 * find it: the Site Editor's parts list and the Template Part block's
	 * picker both read the collection rather than asking for IDs one at a
	 * time. Without this the parts are editable but unplaceable, which makes
	 * them close to useless.
	 *
	 * Anything already in the list wins, so a part the site owner has edited
	 * and saved is not shadowed by ours.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Block_Template[] $query_result  Templates found so far.
	 * @param array               $query         Arguments the caller asked for.
	 * @param string              $template_type Either wp_template or wp_template_part.
	 *
	 * @return WP_Block_Template[]
	 */
	public static function list_template_parts( $query_result, $query, $template_type ) {
		if ( 'wp_template_part' !== $template_type || ! wp_is_block_theme() ) {
			return $query_result;
		}

		$existing = wp_list_pluck( (array) $query_result, 'slug' );
		$wanted   = isset( $query['slug__in'] ) ? (array) $query['slug__in'] : array();

		foreach ( self::available_parts() as $slug => $part ) {
			if ( in_array( $slug, $existing, true ) || ! self::part_is_needed( $slug ) ) {
				continue;
			}

			if ( ! empty( $wanted ) && ! in_array( $slug, $wanted, true ) ) {
				continue;
			}

			$template = self::build_part( $slug, $part );

			if ( null !== $template ) {
				$query_result[] = $template;
			}
		}

		return $query_result;
	}

	/**
	 * Hand back one of our parts when nothing else claims that ID.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Block_Template|null $block_template Template found so far.
	 * @param string                 $id             Template ID being asked for.
	 * @param string                 $template_type  Either wp_template or wp_template_part.
	 *
	 * @return WP_Block_Template|null
	 */
	public static function provide_template_part( $block_template, $id, $template_type ) {
		if ( ! empty( $block_template ) || 'wp_template_part' !== $template_type || ! wp_is_block_theme() ) {
			return $block_template;
		}

		foreach ( self::available_parts() as $slug => $part ) {
			if ( self::part_id( $slug ) !== $id || ! self::part_is_needed( $slug ) ) {
				continue;
			}

			$template = self::build_part( $slug, $part );

			return null === $template ? $block_template : $template;
		}

		return $block_template;
	}
}

Traktivity_Templates::init();
