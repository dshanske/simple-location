<?php
/**
 * Merging duplicates.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Merge', 'init' ) );

/**
 * Merges duplicate venues: a Merge bulk action, a screen to choose which to keep, and redirects from the
 * addresses of those merged.
 *
 * @since 6.1.0
 */
class Sloc_Merge {

	/**
	 * Admin page slug.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PAGE = 'sloc-merge';

	/**
	 * Bulk action name.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const ACTION = 'sloc_merge';

	/**
	 * Meta key holding each address that redirects to the object, one per row.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const REDIRECT_KEY = '_sloc_merged_url';

	/**
	 * Registers the hooks.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_merge' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
		foreach ( self::screens() as $type => $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( __CLASS__, 'register_bulk_action' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Returns the list screens with a Merge action, by the type of object they list.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Screen IDs, keyed by type.
	 */
	public static function screens() {
		return array(
			'venue' => 'edit-venue',
		);
	}

	/**
	 * Adds the merge screen, which has no menu item of its own.
	 *
	 * @since 6.1.0
	 */
	public static function admin_menu() {
		add_submenu_page(
			'options.php',
			__( 'Merge', 'simple-location' ),
			__( 'Merge', 'simple-location' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Adds the Merge action to a list.
	 *
	 * @since 6.1.0
	 *
	 * @param array $actions Bulk actions.
	 * @return array Bulk actions.
	 */
	public static function register_bulk_action( $actions ) {
		$actions[ self::ACTION ] = __( 'Merge', 'simple-location' );
		return $actions;
	}

	/**
	 * Sends the chosen items to the merge screen. Hooked to the list's bulk action handler.
	 *
	 * @since 6.1.0
	 *
	 * @param string $redirect_to Where to go after the action.
	 * @param string $doaction    Action.
	 * @param int[]  $ids         Chosen items.
	 * @return string Where to go: the merge screen, with two or more items.
	 */
	public static function handle_bulk_action( $redirect_to, $doaction, $ids ) {
		if ( self::ACTION !== $doaction ) {
			return $redirect_to;
		}
		$type = array_search( current_filter() ? substr( current_filter(), strlen( 'handle_bulk_actions-' ) ) : '', self::screens(), true );
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
		if ( ! $type || count( $ids ) < 2 ) {
			return add_query_arg( 'sloc_merged', 'few', $redirect_to );
		}
		return add_query_arg(
			array(
				'page'     => self::PAGE,
				'type'     => $type,
				'ids'      => implode( ',', $ids ),
				'_wpnonce' => wp_create_nonce( self::ACTION ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns the items of a type that can be merged, oldest first, with their names and number of posts.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Type, a key of screens().
	 * @param int[]  $ids  IDs.
	 * @return array[] Items, each with 'id', 'name', 'detail' and 'count'.
	 */
	public static function items( $type, $ids ) {
		$items = array();
		if ( 'venue' === $type ) {
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( $post && 'venue' === $post->post_type && current_user_can( 'edit_post', $id ) ) {
					$items[] = array(
						'id'     => $post->ID,
						'name'   => get_the_title( $post ),
						'detail' => get_the_date( '', $post ),
						'count'  => count( Post_Venue::get_venue_posts( $post->ID ) ),
						'order'  => $post->post_date_gmt . sprintf( '%020d', $post->ID ),
					);
				}
			}
		}
		usort( $items, fn( $a, $b ) => strcmp( $a['order'], $b['order'] ) );
		return $items;
	}

	/**
	 * Shows the merge screen: the chosen items, with the oldest selected to keep.
	 *
	 * @since 6.1.0
	 */
	public static function page() {
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$ids  = isset( $_GET['ids'] ) ? array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) ) : array();
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ACTION ) || ! isset( self::screens()[ $type ] ) ) {
			wp_die( esc_html__( 'This link has expired. Choose what to merge again.', 'simple-location' ) );
		}
		$items = self::items( $type, $ids );
		echo '<div class="wrap"><h1>' . esc_html__( 'Merge', 'simple-location' ) . '</h1>';
		if ( count( $items ) < 2 ) {
			echo '<p>' . esc_html__( 'Choose two or more that you can edit to merge.', 'simple-location' ) . '</p></div>';
			return;
		}
		echo '<p>' . esc_html__( 'Choose the one to keep. The others are merged into it and deleted, and their addresses redirect to it. This cannot be undone.', 'simple-location' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		echo '<input type="hidden" name="type" value="' . esc_attr( $type ) . '" />';
		echo '<input type="hidden" name="ids" value="' . esc_attr( implode( ',', wp_list_pluck( $items, 'id' ) ) ) . '" />';
		wp_nonce_field( self::ACTION . '_' . $type, 'sloc_merge_nonce' );
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Keep', 'simple-location' ) . '</legend>';
		foreach ( $items as $index => $item ) {
			printf(
				'<p><label><input type="radio" name="keep" value="%1$d"%2$s /> %3$s <span class="description">%4$s</span></label></p>',
				(int) $item['id'],
				0 === $index ? ' checked="checked"' : '',
				esc_html( $item['name'] ),
				esc_html(
					sprintf(
						/* translators: 1: Date or other detail, 2: Number of posts. */
						_n( '%1$s, %2$s post', '%1$s, %2$s posts', $item['count'], 'simple-location' ),
						$item['detail'],
						number_format_i18n( $item['count'] )
					)
				)
			);
		}
		echo '</fieldset>';
		submit_button( __( 'Merge', 'simple-location' ) );
		echo '</form></div>';
	}

	/**
	 * Merges the chosen items into the one to keep. Hooked to admin_post.
	 *
	 * @since 6.1.0
	 */
	public static function handle_merge() {
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		if ( ! isset( self::screens()[ $type ] ) || ! isset( $_POST['sloc_merge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sloc_merge_nonce'] ) ), self::ACTION . '_' . $type ) ) {
			wp_die( esc_html__( 'This link has expired. Choose what to merge again.', 'simple-location' ) );
		}
		$keep  = isset( $_POST['keep'] ) ? intval( $_POST['keep'] ) : 0;
		$ids   = isset( $_POST['ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['ids'] ) ) ) ) : array();
		$count = self::merge( $type, $keep, $ids );
		wp_safe_redirect( add_query_arg( 'sloc_merged', $count, self::list_url( $type ) ) );
		exit;
	}

	/**
	 * Merges items into the one to keep.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Type, a key of screens().
	 * @param int    $keep Item to keep.
	 * @param int[]  $ids  Items to merge, which can include the one to keep.
	 * @return int Number merged.
	 */
	public static function merge( $type, $keep, $ids ) {
		if ( ! in_array( (int) $keep, array_map( 'intval', (array) $ids ), true ) ) {
			return 0;
		}
		if ( 'venue' === $type ) {
			return Post_Venue::merge_venues( $keep, $ids );
		}
		return 0;
	}

	/**
	 * Returns the list a type's items are merged from.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Type, a key of screens().
	 * @return string URL.
	 */
	public static function list_url( $type ) {
		return 'venue' === $type ? admin_url( 'edit.php?post_type=venue' ) : admin_url();
	}

	/**
	 * Shows how many were merged.
	 *
	 * @since 6.1.0
	 */
	public static function admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the count the merge redirect added, to show a notice.
		if ( ! isset( $_GET['sloc_merged'] ) ) {
			return;
		}
		$count = sanitize_key( wp_unslash( $_GET['sloc_merged'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the count the merge redirect added, to show a notice.
		if ( 'few' === $count ) {
			$message = __( 'Choose two or more to merge.', 'simple-location' );
		} elseif ( 0 === (int) $count ) {
			$message = __( 'Nothing was merged.', 'simple-location' );
		} else {
			/* translators: %s: Number merged. */
			$message = sprintf( _n( 'Merged %s into the one you kept.', 'Merged %s into the one you kept.', (int) $count, 'simple-location' ), number_format_i18n( (int) $count ) );
		}
		printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Returns an address as the part redirects are matched on: its path, without slashes at the ends, and its query.
	 *
	 * @since 6.1.0
	 *
	 * @param string $url URL.
	 * @return string Path and query, such as venue/corner-cafe or ?venue=corner-cafe.
	 */
	public static function redirect_key( $url ) {
		$path  = trim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );
		$query = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
		return $path . ( '' === $query ? '' : '?' . $query );
	}

	/**
	 * Has the address of a merged object, and those that redirected to it, redirect to the object kept.
	 *
	 * @since 6.1.0
	 *
	 * @param string $meta_type Meta type: 'post' or 'term'.
	 * @param int    $keep      Object kept.
	 * @param int    $merged    Object merged into it.
	 * @param string $old_url   Address of the object merged.
	 */
	public static function add_redirects( $meta_type, $keep, $merged, $old_url ) {
		$keys = get_metadata( $meta_type, $merged, self::REDIRECT_KEY );
		if ( is_string( $old_url ) && '' !== $old_url ) {
			$keys[] = self::redirect_key( $old_url );
		}
		$current = get_metadata( $meta_type, $keep, self::REDIRECT_KEY );
		foreach ( array_unique( array_filter( $keys ) ) as $key ) {
			if ( ! in_array( $key, $current, true ) ) {
				add_metadata( $meta_type, $keep, self::REDIRECT_KEY, $key );
			}
		}
	}

	/**
	 * Returns where an address of something merged now is.
	 *
	 * @since 6.1.0
	 *
	 * @param string $url Address requested.
	 * @return string|false Address of what it was merged into, or false.
	 */
	public static function redirect_url( $url ) {
		$key = self::redirect_key( $url );
		if ( '' === $key ) {
			return false;
		}
		$posts = get_posts(
			array(
				'post_type'   => 'venue',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Run only for addresses that weren't found.
				'meta_query'  => array(
					array(
						'key'   => self::REDIRECT_KEY,
						'value' => $key,
					),
				),
			)
		);
		if ( ! empty( $posts ) ) {
			return get_permalink( $posts[0] );
		}
		return false;
	}

	/**
	 * Redirects an address that wasn't found, if what was there was merged. Hooked to template_redirect.
	 *
	 * @since 6.1.0
	 */
	public static function template_redirect() {
		if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$url = self::redirect_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		if ( $url && wp_safe_redirect( $url, 301 ) ) {
			exit;
		}
	}
}
