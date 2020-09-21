<?php
/**
 * Custom Post Type and Taxonomies.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Define post type, taxonomies, and other term meta where our movie / show / episode data will be stored.
 *
 * @since 1.0.0
 */
class Traktivity_Data_Storage {
	/**
	 * Constructor
	 */
	function __construct() {
		// Post Type.
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
		add_filter( 'rest_api_allowed_post_types', array( $this, 'whitelist_post_type_wpcom' ) );

		// Taxonomies.
		add_action( 'init', array( $this, 'register_event_type_taxonomy' ), 0 );
		add_action( 'init', array( $this, 'register_genre_taxonomy' ), 0 );
		add_action( 'init', array( $this, 'register_event_year_taxonomy' ), 0 );
		add_action( 'init', array( $this, 'register_show_taxonomy' ), 0 );
		add_action( 'init', array( $this, 'register_season_taxonomy' ), 0 );
		add_action( 'init', array( $this, 'register_episode_taxonomy' ), 0 );

		// Term Meta display.
		add_filter( 'manage_edit-trakt_show_columns', array( $this, 'show_poster_column' ) );
		add_filter( 'manage_trakt_show_custom_column', array( $this, 'show_poster_display_in_column' ), 10, 3 );
		add_filter( 'manage_trakt_show_custom_column', array( $this, 'show_network_display_in_column' ), 11, 3 );
		add_filter( 'manage_trakt_show_custom_column', array( $this, 'show_imdb_display_in_column' ), 12, 3 );
		add_filter( 'manage_trakt_show_custom_column', array( $this, 'show_runtime_display_in_column' ), 13, 3 );
	}

	/**
	 * Register Custom Post Type.
	 * Each post will match a unique ID (64-bit integer) to identify each event.
	 * The type of event (scrobble, checkin, or watch) doesn't matter to us. We'll consider everything as watched.
	 * Along with each event, we'll save meta data about the event in taxonomies and custom post meta.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {

		$labels = array(
			'name'                  => _x( 'Trakt.tv Events', 'Post Type General Name', 'traktivity' ),
			'singular_name'         => _x( 'Trakt.tv Event', 'Post Type Singular Name', 'traktivity' ),
			'menu_name'             => __( 'Traktivity', 'traktivity' ),
			'name_admin_bar'        => __( 'Trakt.tv Event', 'traktivity' ),
			'archives'              => __( 'Event Archives', 'traktivity' ),
			'all_items'             => __( 'All Trakt.tv Events', 'traktivity' ),
			'add_new_item'          => __( 'Add New Event', 'traktivity' ),
			'add_new'               => __( 'Add New', 'traktivity' ),
			'new_item'              => __( 'New Event', 'traktivity' ),
			'edit_item'             => __( 'Edit Event', 'traktivity' ),
			'update_item'           => __( 'Update Event', 'traktivity' ),
			'view_item'             => __( 'View Event', 'traktivity' ),
			'search_items'          => __( 'Search Event', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the main CPT (traktivity_event) slug.
			 *
			 * @since 2.3.0
			 *
			 * @param string $core_cpt_slug Core CPT slug. Defaults to watched.
			 */
			'slug'       => apply_filters( 'traktivity_core_cpt_slug', 'watched' ),
			'with_front' => true,
			'feeds'      => true,
			'pages'      => true,
		);
		$args = array(
			'label'                 => __( 'Trakt.tv Event', 'traktivity' ),
			'description'           => __( 'Trakt.tv Event', 'traktivity' ),
			'labels'                => $labels,
			'rewrite'               => $rewrites,
			'supports'              => array( 'title', 'editor', 'wpcom-markdown', 'publicize', 'thumbnail', 'excerpt', 'comments' ),
			'taxonomies'            => array( 'trakt_type', 'trakt_genre', 'trakt_year', 'trakt_show', 'trakt_season', 'trakt_episode' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'page',
			'map_meta_cap'          => true,
			'capabilities'          => array(
				'create_posts' => 'do_not_allow',
			),
			'menu_icon'             => 'dashicons-format-video',
			'show_in_rest'          => true,
		);
		register_post_type( 'traktivity_event', $args );

	}

	/**
	 * Display the Post Type in the WordPress.com REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $allowed_post_types Array of Post Types allowed in the WordPress.com REST API for that site.
	 */
	public function whitelist_post_type_wpcom( $allowed_post_types ) {
		$allowed_post_types[] = 'traktivity_event';
		return $allowed_post_types;
	}

	/**
	 * Register Event Type taxonomy.
	 * Trakt gives us `movie` or `episode`.
	 * When Event Type is `movie`, Trakt gives us everything we need (title, slug, ...) right away in that array.
	 * When Event Type is `episode`, we'll go dig in the `episode` array as well as in the `show` array to get more details about the event.
	 *
	 * @since 1.0.0
	 */
	public function register_event_type_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Event Types', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Event Type', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Type', 'traktivity' ),
			'all_items'                  => __( 'All Event Types', 'traktivity' ),
			'new_item_name'              => __( 'New Event Type', 'traktivity' ),
			'add_new_item'               => __( 'Add New Event Type', 'traktivity' ),
			'edit_item'                  => __( 'Edit Event Type', 'traktivity' ),
			'update_item'                => __( 'Update Event Type', 'traktivity' ),
			'view_item'                  => __( 'View Event Type', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Event Type', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Event Types', 'traktivity' ),
			'search_items'               => __( 'Search Event Types', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Event Types', 'traktivity' ),
			'items_list'                 => __( 'Event Type list', 'traktivity' ),
			'items_list_navigation'      => __( 'Event Type list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the show taxonomy slug  (trakt_type).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_type_tax_slug slug. Defaults to type.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_type_tax_slug', 'type' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_type', array( 'traktivity_event' ), $args );

	}

	/**
	 * Register Genre
	 * Trakt gives us an array of genre for each movie of shows.
	 *
	 * @since 1.0.0
	 */
	public function register_genre_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Genre', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Genre', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Genre', 'traktivity' ),
			'all_items'                  => __( 'All Genres', 'traktivity' ),
			'new_item_name'              => __( 'New Genre', 'traktivity' ),
			'add_new_item'               => __( 'Add New Genre', 'traktivity' ),
			'edit_item'                  => __( 'Edit Genre', 'traktivity' ),
			'update_item'                => __( 'Update Genre', 'traktivity' ),
			'view_item'                  => __( 'View Genre', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Genre', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Genres', 'traktivity' ),
			'search_items'               => __( 'Search Genres', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Genre', 'traktivity' ),
			'items_list'                 => __( 'Genre list', 'traktivity' ),
			'items_list_navigation'      => __( 'Genre list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the genre taxonomy slug (trakt_genre).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_genre_tax_slug slug. Defaults to genre.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_genre_tax_slug', 'genre' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_genre', array( 'traktivity_event' ), $args );

	}

	/**
	 * Register Event Year. The year the movie / TV show was released.
	 *
	 * @since 1.0.0
	 */
	public function register_event_year_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Event Year', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Event Year', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Year', 'traktivity' ),
			'all_items'                  => __( 'All Years', 'traktivity' ),
			'new_item_name'              => __( 'New Year', 'traktivity' ),
			'add_new_item'               => __( 'Add New Year', 'traktivity' ),
			'edit_item'                  => __( 'Edit Event Year', 'traktivity' ),
			'update_item'                => __( 'Update Event Year', 'traktivity' ),
			'view_item'                  => __( 'View Event Year', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Event Year', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Event Years', 'traktivity' ),
			'search_items'               => __( 'Search Event Years', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Event Years', 'traktivity' ),
			'items_list'                 => __( 'Event Year list', 'traktivity' ),
			'items_list_navigation'      => __( 'Event Year list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the year taxonomy slug (trakt_year).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_year_tax_slug slug. Defaults to year.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_year_tax_slug', 'year' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_year', array( 'traktivity_event' ), $args );

	}

	/**
	 * Register Show title.
	 *
	 * @since 1.0.0
	 */
	public function register_show_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Show', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Show', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Show', 'traktivity' ),
			'all_items'                  => __( 'All Shows', 'traktivity' ),
			'new_item_name'              => __( 'New Show', 'traktivity' ),
			'add_new_item'               => __( 'Add New Show', 'traktivity' ),
			'edit_item'                  => __( 'Edit Show', 'traktivity' ),
			'update_item'                => __( 'Update Show', 'traktivity' ),
			'view_item'                  => __( 'View Show', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Show', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Shows', 'traktivity' ),
			'search_items'               => __( 'Search Shows', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Shows', 'traktivity' ),
			'items_list'                 => __( 'Show list', 'traktivity' ),
			'items_list_navigation'      => __( 'Show list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the show taxonomy slug (trakt_show).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_show_tax_slug slug. Defaults to show.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_show_tax_slug', 'show' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_show', array( 'traktivity_event' ), $args );

	}

	/**
	 * Register Show season number.
	 *
	 * @since 1.0.0
	 */
	public function register_season_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Season', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Season', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Season', 'traktivity' ),
			'all_items'                  => __( 'All Seasons', 'traktivity' ),
			'new_item_name'              => __( 'New Season', 'traktivity' ),
			'add_new_item'               => __( 'Add New Season', 'traktivity' ),
			'edit_item'                  => __( 'Edit Season', 'traktivity' ),
			'update_item'                => __( 'Update Season', 'traktivity' ),
			'view_item'                  => __( 'View Season', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Season', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Seasons', 'traktivity' ),
			'search_items'               => __( 'Search Seasons', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Seasons', 'traktivity' ),
			'items_list'                 => __( 'Season list', 'traktivity' ),
			'items_list_navigation'      => __( 'Season list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the season taxonomy slug (trakt_season).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_season_tax_slug slug. Defaults to season.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_season_tax_slug', 'season' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_season', array( 'traktivity_event' ), $args );

	}

	/**
	 * Register Episode taxonomy to store episode numbers.
	 *
	 * @since 1.0.0
	 */
	public function register_episode_taxonomy() {

		$labels = array(
			'name'                       => _x( 'Episode', 'Taxonomy General Name', 'traktivity' ),
			'singular_name'              => _x( 'Episode', 'Taxonomy Singular Name', 'traktivity' ),
			'menu_name'                  => __( 'Episode Numbers', 'traktivity' ),
			'all_items'                  => __( 'All Episodes', 'traktivity' ),
			'new_item_name'              => __( 'New Episode', 'traktivity' ),
			'add_new_item'               => __( 'Add New Episode', 'traktivity' ),
			'edit_item'                  => __( 'Edit Episode number', 'traktivity' ),
			'update_item'                => __( 'Update Episode number', 'traktivity' ),
			'view_item'                  => __( 'View Episode', 'traktivity' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'traktivity' ),
			'add_or_remove_items'        => __( 'Add or remove Episode', 'traktivity' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'traktivity' ),
			'popular_items'              => __( 'Popular Episodes', 'traktivity' ),
			'search_items'               => __( 'Search Episodes', 'traktivity' ),
			'not_found'                  => __( 'Not Found', 'traktivity' ),
			'no_terms'                   => __( 'No Episodes', 'traktivity' ),
			'items_list'                 => __( 'Episode list', 'traktivity' ),
			'items_list_navigation'      => __( 'Episode list navigation', 'traktivity' ),
		);
		$rewrites = array(
			/**
			 * Filter the episode taxonomy slug (trakt_episode).
			 *
			 * @since 2.3.0
			 *
			 * @param string $trakt_episode_tax_slug slug. Defaults to episode.
			 */
			'slug'         => apply_filters( 'traktivity_trakt_episode_tax_slug', 'episode' ),
			'with_front'   => true,
			'hierarchical' => false,
			'ep_mask'      => EP_NONE,
		);
		$args = array(
			'labels'                     => $labels,
			'rewrite'                    => $rewrites,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
		);
		register_taxonomy( 'trakt_episode', array( 'traktivity_event' ), $args );

	}

	/**
	 * Add new columns to the show list in wp-admin, using Term meta.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns Array of columns on the screen we hook into.
	 */
	public function show_poster_column( $columns ) {
		$columns['show_poster']  = esc_html__( 'Poster', 'traktivity' );
		$columns['show_network'] = esc_html__( 'Network', 'traktivity' );
		$columns['show_imdb']    = esc_html__( 'On IMDb', 'traktivity' );
		$columns['show_runtime'] = esc_html__( 'Runtime', 'traktivity' );

		return $columns;
	}

	/**
	 * Display the Show Poster on the show list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 */
	public function show_poster_display_in_column( $content, $column_name, $term_id ) {
		global $feature_groups;

		if ( 'show_poster' != $column_name ) {
			return $content;
		}

		$term_id = absint( $term_id );
		$show_poster = get_term_meta( $term_id, 'show_poster', true );

		if ( is_array( $show_poster ) && ! empty( $show_poster ) ) {
			$content = sprintf(
				'%1$s<img %2$s src="%3$s" />',
				$content,
				'style="max-width:100%;"',
				wp_get_attachment_thumb_url( $show_poster['id'] )
			);
		}

		return $content;
	}

	/**
	 * Display the Show Network on the show list.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 */
	public function show_network_display_in_column( $content, $column_name, $term_id ) {
		global $feature_groups;

		if ( 'show_network' != $column_name ) {
			return $content;
		}

		$term_id = absint( $term_id );
		$show_network = get_term_meta( $term_id, 'show_network', true );

		if ( ! empty( $show_network ) ) {
			$content = sprintf(
				'%1$s%2$s',
				$content,
				esc_html( $show_network )
			);
		}

		return $content;
	}

	/**
	 * Display the Show IMDb link on the show list.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 */
	public function show_imdb_display_in_column( $content, $column_name, $term_id ) {
		global $feature_groups;

		if ( 'show_imdb' != $column_name ) {
			return $content;
		}

		$term_id = absint( $term_id );
		$show_ids = get_term_meta( $term_id, 'show_external_ids', true );

		if (
			is_array( $show_ids )
			&& isset( $show_ids['imdb'] )
			&& ! empty( $show_ids['imdb'] )
		) {
			$link = sprintf(
				'http://www.imdb.com/title/%1$s/',
				esc_attr( $show_ids['imdb'] )
			);
			$content = sprintf(
				'%1$s<a href="%2$s">%3$s</a>',
				$content,
				esc_url( $link ),
				esc_html__( 'View On IMDb', 'traktivity' )
			);
		}

		return $content;
	}

	/**
	 * Display the Show's total runtime on the show list.
	 *
	 * @since 2.1.0
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 */
	public function show_runtime_display_in_column( $content, $column_name, $term_id ) {
		global $feature_groups;

		if ( 'show_runtime' != $column_name ) {
			return $content;
		}

		$term_id = absint( $term_id );
		$show_runtime = get_term_meta( $term_id, 'show_runtime', true );

		if ( ! empty( $show_runtime ) ) {
			$content = sprintf(
				'%1$s%2$s',
				$content,
				esc_html( Traktivity_Stats::convert_time( $show_runtime ) )
			);
		}

		return $content;
	}
} // End class.
new Traktivity_Data_Storage();
