<?php
/**
 * Repairs location data saved by earlier versions.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Brings existing location terms and posts up to the current logic: region codes, duplicates, missing types,
 * localities without a region, broken terms, and posts with coordinates but no location term.
 *
 * Every step can be previewed. Used by the Tools page (Sloc_Location_Repair_Tool) and `wp location repair`.
 *
 * @since 6.5.0
 */
class Sloc_Location_Repair {

	/**
	 * Region codes a preview would set, so later steps of the same preview see them.
	 *
	 * @since 6.5.0
	 * @var array Term ID => code.
	 */
	protected static $previewed_codes = array();

	/**
	 * Returns the steps, in the order they run.
	 *
	 * @since 6.5.0
	 *
	 * @return array Step => array with 'label', 'description' and 'default' (whether it runs unless chosen otherwise).
	 */
	public static function steps() {
		return array(
			'region_codes'        => array(
				'label'       => __( 'Region codes', 'simple-location' ),
				'description' => __( 'Gives regions stored with a name or a number instead of their code the right code, so later lookups match them.', 'simple-location' ),
				'default'     => true,
			),
			'region_duplicates'   => array(
				'label'       => __( 'Duplicate regions', 'simple-location' ),
				'description' => __( 'Merges regions of a country that have the same code.', 'simple-location' ),
				'default'     => true,
			),
			'types'               => array(
				'label'       => __( 'Missing types', 'simple-location' ),
				'description' => __( 'Marks towns that lost their type, so lookups find them.', 'simple-location' ),
				'default'     => true,
			),
			'orphan_localities'   => array(
				'label'       => __( 'Towns without a region', 'simple-location' ),
				'description' => __( 'Merges a town stored straight under its country into the same town under a region, or moves it under its region from the bundled place data.', 'simple-location' ),
				'default'     => true,
			),
			'locality_duplicates' => array(
				'label'       => __( 'Duplicate towns', 'simple-location' ),
				'description' => __( 'Merges towns of a region that have the same name.', 'simple-location' ),
				'default'     => true,
			),
			'broken'              => array(
				'label'       => __( 'Broken locations', 'simple-location' ),
				'description' => __( 'Removes top-level locations with no posts whose country code isn\'t a country.', 'simple-location' ),
				'default'     => true,
			),
			'untagged'            => array(
				'label'       => __( 'Posts without a location', 'simple-location' ),
				'description' => __( 'Gives posts with public coordinates but no location term one, looked up from their coordinates.', 'simple-location' ),
				'default'     => true,
			),
			'relookup'            => array(
				'label'       => __( 'Regions without a code', 'simple-location' ),
				'description' => __( 'Looks up again, through your geocoding providers, the posts in regions that have no code, such as districts stored as regions, and removes the regions left empty.', 'simple-location' ),
				'default'     => false,
			),
			'names'               => array(
				'label'       => __( 'Names in the site language', 'simple-location' ),
				'description' => __( 'Renames countries and regions to their names in the site language, keeping the old name.', 'simple-location' ),
				'default'     => false,
			),
			'addresses'           => array(
				'label'       => __( 'Stored addresses', 'simple-location' ),
				'description' => __( 'Counts stored addresses the current rules would write differently. They are not changed, since some were typed by hand.', 'simple-location' ),
				'default'     => true,
			),
		);
	}

	/**
	 * Runs steps, or previews them.
	 *
	 * @since 6.5.0
	 *
	 * @param string[] $steps Steps to run; unknown ones are ignored.
	 * @param bool     $apply Whether to change anything; false previews.
	 * @param array    $args  {
	 *     Optional. Arguments.
	 *
	 *     @type string $lookup 'offline' to look up posts from the bundled data, or 'chain' through the geocoding
	 *                          providers. Default 'offline'.
	 *     @type int    $limit  Most posts to look up in a run. Default 50.
	 * }
	 * @return array Step => list of changes, each with 'object' ('term' or 'post'), 'id', 'name' and 'change'.
	 */
	public static function run( $steps, $apply = false, $args = array() ) {
		$args   = wp_parse_args(
			$args,
			array(
				'lookup' => 'offline',
				'limit'  => 50,
			)
		);
		$report                 = array();
		self::$previewed_codes = array();
		foreach ( array_keys( self::steps() ) as $step ) {
			if ( in_array( $step, (array) $steps, true ) ) {
				$report[ $step ] = call_user_func( array( __CLASS__, $step ), (bool) $apply, $args );
			}
		}
		return $report;
	}

	/**
	 * Returns a change for the report.
	 *
	 * @since 6.5.0
	 *
	 * @param string $object Object type: 'term' or 'post'.
	 * @param int    $id     ID.
	 * @param string $name   Name or title.
	 * @param string $change What changes.
	 * @return array Change.
	 */
	protected static function change( $object, $id, $name, $change ) {
		return array(
			'object' => $object,
			'id'     => (int) $id,
			'name'   => (string) $name,
			'change' => (string) $change,
		);
	}

	/**
	 * Returns every location term with its type, codes, country code and depth.
	 *
	 * @since 6.5.0
	 *
	 * @return array Term ID => array with 'term' (WP_Term), 'type' (from the term meta: country, region or locality,
	 *               or '' without), 'code' (first value of that meta), 'depth' and 'country' (country code of its
	 *               top-level term).
	 */
	protected static function terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'location',
				'hide_empty' => false,
			)
		);
		$list  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$type = '';
			foreach ( array( 'locality', 'region', 'country' ) as $key ) {
				if ( '' !== (string) get_term_meta( $term->term_id, $key, true ) ) {
					$type = $key;
					break;
				}
			}
			$list[ $term->term_id ] = array(
				'term' => $term,
				'type' => $type,
				'code' => self::$previewed_codes[ $term->term_id ] ?? ( '' === $type ? '' : (string) get_term_meta( $term->term_id, $type, true ) ),
			);
		}
		foreach ( $list as $id => $item ) {
			$depth = 0;
			$top   = $item['term'];
			while ( $top->parent && isset( $list[ $top->parent ] ) && $depth < 5 ) {
				$top = $list[ $top->parent ]['term'];
				++$depth;
			}
			$list[ $id ]['depth']   = $depth;
			$list[ $id ]['country'] = strtoupper( (string) get_term_meta( $top->term_id, 'country', true ) );
		}
		return $list;
	}

	/**
	 * Returns whether a code is a region of a country in the ISO 3166-2 data.
	 *
	 * @since 6.5.0
	 *
	 * @param string $code    Region code.
	 * @param string $country Country code.
	 * @return bool Whether it is.
	 */
	protected static function is_region_code( $code, $country ) {
		return '' !== $code && '' !== $country && (bool) Geo_Provider::region_name( strtoupper( $code ), $country );
	}

	/**
	 * Returns the code of a region by name: from the ISO 3166-2 data, or names in other languages.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name    Region name.
	 * @param string $country Country code.
	 * @return string Code, or an empty string.
	 */
	protected static function region_code_for( $name, $country ) {
		$code = Geo_Provider::region_code( $name, $country );
		if ( ! $code ) {
			$kind = 'county' === Sloc_Address_Format::region_key( $country ) ? 'county' : 'state';
			$code = Sloc_Address_Format::code( $country, $name, $kind );
		}
		return $code ? strtoupper( (string) $code ) : '';
	}

	/**
	 * Region codes: regions stored with a name or a number instead of their code get the code, first, keeping the old
	 * names so lookups by name still match. The code is looked up from the term's name, its local_name, or a name
	 * stored in place of a code.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function region_codes( $apply ) {
		$changes = array();
		foreach ( self::terms() as $id => $item ) {
			if ( 'region' !== $item['type'] || self::is_region_code( $item['code'], $item['country'] ) ) {
				continue;
			}
			// The term's name, the provider's name, and names stored in place of a code.
			$names = array_merge( array( $item['term']->name, (string) get_term_meta( $id, 'local_name', true ) ), get_term_meta( $id, 'region' ) );
			$code  = '';
			foreach ( array_unique( array_filter( $names, fn( $name ) => is_string( $name ) && '' !== $name && ! ctype_digit( $name ) ) ) as $name ) {
				$code = self::region_code_for( $name, $item['country'] );
				if ( '' !== $code ) {
					break;
				}
			}
			if ( '' === $code ) {
				continue;
			}
			/* translators: 1: Old region code, 2: New region code. */
			$changes[] = self::change( 'term', $id, $item['term']->name, sprintf( __( 'Code %1$s becomes %2$s', 'simple-location' ), $item['code'], $code ) );
			if ( ! $apply ) {
				self::$previewed_codes[ $id ] = $code;
			} else {
				$old = get_term_meta( $id, 'region' );
				delete_term_meta( $id, 'region' );
				add_term_meta( $id, 'region', $code );
				foreach ( $old as $value ) {
					// Names still match lookups by name; numbers and the code itself don't need keeping.
					if ( is_string( $value ) && ! ctype_digit( $value ) && strtoupper( $value ) !== $code ) {
						add_term_meta( $id, 'region', $value );
					}
				}
			}
		}
		return $changes;
	}

	/**
	 * Duplicate regions: regions of the same country with the same code are merged into the one with the most posts.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function region_duplicates( $apply ) {
		$groups = array();
		foreach ( self::terms() as $id => $item ) {
			if ( 'region' === $item['type'] && self::is_region_code( $item['code'], $item['country'] ) && $item['term']->parent ) {
				$groups[ $item['term']->parent . ':' . strtoupper( $item['code'] ) ][ $id ] = $item['term'];
			}
		}
		return self::merge_groups( $groups, $apply );
	}

	/**
	 * Merges each group of terms into the one with the most posts, or the oldest.
	 *
	 * @since 6.5.0
	 *
	 * @param array $groups Groups of term ID => WP_Term.
	 * @param bool  $apply  Whether to change anything.
	 * @return array Changes.
	 */
	protected static function merge_groups( $groups, $apply ) {
		$changes = array();
		foreach ( $groups as $group ) {
			if ( count( $group ) < 2 ) {
				continue;
			}
			uasort( $group, fn( $a, $b ) => array( $b->count, $a->term_id ) <=> array( $a->count, $b->term_id ) );
			$keep = reset( $group );
			foreach ( $group as $id => $term ) {
				if ( $id === $keep->term_id ) {
					continue;
				}
				/* translators: 1: Name of the location merged into, 2: Its term ID, 3: Number of posts moved. */
				$changes[] = self::change( 'term', $id, $term->name, sprintf( __( 'Merged into %1$s (%2$d), with its %3$d posts', 'simple-location' ), $keep->name, $keep->term_id, $term->count ) );
			}
			if ( $apply ) {
				Location_Taxonomy::merge_locations( $keep->term_id, array_diff( array_keys( $group ), array( $keep->term_id ) ) );
			}
		}
		return $changes;
	}

	/**
	 * Missing types: terms below a region without a type are marked as towns.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function types( $apply ) {
		$changes = array();
		foreach ( self::terms() as $id => $item ) {
			if ( '' !== $item['type'] || 2 !== $item['depth'] ) {
				continue;
			}
			$changes[] = self::change( 'term', $id, $item['term']->name, __( 'Marked as a town', 'simple-location' ) );
			if ( $apply ) {
				add_term_meta( $id, 'locality', $item['term']->name );
			}
		}
		return $changes;
	}

	/**
	 * Towns without a region: a town stored straight under its country is merged into the town of the same name under
	 * one of the country's regions, or moved under its region found from its posts' coordinates in the bundled place
	 * data. The place data's regions are first-level ones, so this is only done where addresses name that level.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function orphan_localities( $apply ) {
		$changes = array();
		$terms   = self::terms();
		foreach ( $terms as $id => $item ) {
			if ( 'locality' !== $item['type'] || 1 !== $item['depth'] ) {
				continue;
			}
			$target = null;
			foreach ( $terms as $other_id => $other ) {
				if ( 'locality' === $other['type'] && 2 === $other['depth'] && $other['country'] === $item['country'] && 0 === strcasecmp( $other['term']->name, $item['term']->name ) ) {
					$target = $other['term'];
					break;
				}
			}
			if ( $target ) {
				/* translators: 1: Name of the town merged into, 2: Its term ID. */
				$changes[] = self::change( 'term', $id, $item['term']->name, sprintf( __( 'Merged into %1$s (%2$d)', 'simple-location' ), $target->name, $target->term_id ) );
				if ( $apply ) {
					self::move_term_posts( $id, $target->term_id );
				}
				continue;
			}
			$region = self::region_from_posts( $id, $item['country'] );
			if ( ! $region ) {
				$changes[] = self::change( 'term', $id, $item['term']->name, __( 'Left as it is: its region couldn\'t be found without looking up its posts again', 'simple-location' ) );
				continue;
			}
			/* translators: 1: Region name, 2: Region code. */
			$changes[] = self::change( 'term', $id, $item['term']->name, sprintf( __( 'Moved under %1$s (%2$s)', 'simple-location' ), $region['region'], $region['region-code'] ) );
			if ( $apply ) {
				$parent = Location_Taxonomy::get_region( $region );
				if ( ! $parent ) {
					$parent = Location_Taxonomy::get_location( $region, true );
				}
				if ( $parent ) {
					wp_update_term( $id, 'location', array( 'parent' => (int) $parent ) );
				}
			}
		}
		return $changes;
	}

	/**
	 * Returns the region of a term's posts from the bundled place data, where the country's addresses name the
	 * place data's level of region.
	 *
	 * @since 6.5.0
	 *
	 * @param int    $term_id Term ID.
	 * @param string $country Country code.
	 * @return array|null Address with region, region-code, country-code and country-name, or null.
	 */
	protected static function region_from_posts( $term_id, $country ) {
		$key = Sloc_Address_Format::region_key( $country );
		if ( '' === $country || ( null !== $key && 'state' !== $key ) || ! class_exists( 'Sloc_Offline_Geo' ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- An admin tool, run on demand.
				'tax_query'      => array(
					array(
						'taxonomy' => 'location',
						'terms'    => $term_id,
					),
				),
			)
		);
		foreach ( $posts as $post_id ) {
			$latitude  = get_post_meta( $post_id, 'geo_latitude', true );
			$longitude = get_post_meta( $post_id, 'geo_longitude', true );
			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				continue;
			}
			$place = Sloc_Offline_Geo::place( (float) $latitude, (float) $longitude, $country );
			$code  = $place ? self::region_code_for( (string) ( $place['region'] ?? '' ), $country ) : '';
			if ( '' !== $code ) {
				return array(
					'region'       => (string) $place['region'],
					'region-code'  => $code,
					'country-code' => $country,
					'country-name' => Geo_Provider::country_name( $country ),
				);
			}
		}
		return null;
	}

	/**
	 * Moves a term's posts to another term and removes it, with a redirect from its archive.
	 *
	 * @since 6.5.0
	 *
	 * @param int $from Term to remove.
	 * @param int $to   Term to keep.
	 */
	protected static function move_term_posts( $from, $to ) {
		$link = get_term_link( (int) $from, 'location' );
		Sloc_Merge::add_redirects( 'term', (int) $to, (int) $from, is_wp_error( $link ) ? '' : $link );
		foreach ( get_term_meta( $from, 'locality' ) as $value ) {
			if ( ! in_array( $value, get_term_meta( $to, 'locality' ), true ) ) {
				add_term_meta( $to, 'locality', $value );
			}
		}
		wp_delete_term(
			(int) $from,
			'location',
			array(
				'default'       => (int) $to,
				'force_default' => true,
			)
		);
	}

	/**
	 * Duplicate towns: towns of the same region with the same name are merged into the one with the most posts.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function locality_duplicates( $apply ) {
		$groups = array();
		foreach ( self::terms() as $id => $item ) {
			if ( 'locality' === $item['type'] && 2 === $item['depth'] ) {
				$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $item['term']->name, 'UTF-8' ) : strtolower( $item['term']->name );
				$groups[ $item['term']->parent . ':' . remove_accents( $name ) ][ $id ] = $item['term'];
			}
		}
		return self::merge_groups( $groups, $apply );
	}

	/**
	 * Broken locations: top-level terms with no posts or children whose country code isn't a country are removed.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function broken( $apply ) {
		$changes = array();
		$terms   = self::terms();
		$parents = array_map( fn( $item ) => $item['term']->parent, $terms );
		foreach ( $terms as $id => $item ) {
			if ( 0 !== (int) $item['term']->parent || 0 !== (int) $item['term']->count || in_array( $id, $parents, true ) ) {
				continue;
			}
			if ( 'country' === $item['type'] && preg_match( '/^[A-Z]{2}$/', $item['country'] ) && Geo_Provider::country_name( $item['country'] ) ) {
				continue;
			}
			$changes[] = self::change( 'term', $id, $item['term']->name, __( 'Removed: not a country, and no posts', 'simple-location' ) );
			if ( $apply ) {
				wp_delete_term( $id, 'location' );
			}
		}
		return $changes;
	}

	/**
	 * Returns posts with coordinates but no location term.
	 *
	 * @since 6.5.0
	 *
	 * @param int $limit Most to return.
	 * @return int[] Post IDs.
	 */
	protected static function untagged_posts( $limit ) {
		$taxonomy = get_taxonomy( 'location' );
		return get_posts(
			array(
				'post_type'      => $taxonomy ? $taxonomy->object_type : 'post',
				'post_status'    => array( 'publish', 'private', 'future', 'draft', 'pending' ),
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- An admin tool, run on demand.
				'meta_query'     => array(
					array(
						'key'     => 'geo_latitude',
						'compare' => 'EXISTS',
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- An admin tool, run on demand.
				'tax_query'      => array(
					array(
						'taxonomy' => 'location',
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Looks up the address of a location.
	 *
	 * @since 6.5.0
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $lookup    'offline' or 'chain'.
	 * @return array|null Address, or null if none was found.
	 */
	protected static function lookup( $latitude, $longitude, $lookup ) {
		if ( 'chain' === $lookup ) {
			$address = Sloc_Provider_Chain::reverse_lookup( $latitude, $longitude );
		} else {
			$provider = Loc_Config::geo_provider( 'offline' );
			if ( ! $provider ) {
				return null;
			}
			$provider->set( $latitude, $longitude );
			$address = Sloc_Address_Finisher::finish( $provider->reverse_lookup() );
		}
		return is_array( $address ) && ! empty( $address['country-code'] ) ? $address : null;
	}

	/**
	 * Posts without a location: posts with public coordinates but no location term get one, from their coordinates.
	 * Posts whose location is private or protected are left out, since location terms are public.
	 *
	 * @since 6.5.0
	 *
	 * @param bool  $apply Whether to change anything.
	 * @param array $args  Arguments: 'lookup' and 'limit'. See run().
	 * @return array Changes.
	 */
	protected static function untagged( $apply, $args ) {
		$changes = array();
		$skipped = 0;
		foreach ( self::untagged_posts( (int) $args['limit'] ) as $post_id ) {
			if ( 'public' !== get_post_geodata( $post_id, 'visibility' ) ) {
				++$skipped;
				continue;
			}
			$latitude  = get_post_meta( $post_id, 'geo_latitude', true );
			$longitude = get_post_meta( $post_id, 'geo_longitude', true );
			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				continue;
			}
			if ( ! $apply ) {
				$changes[] = self::change( 'post', $post_id, get_the_title( $post_id ), __( 'Will be given a location from its coordinates', 'simple-location' ) );
				continue;
			}
			$address = self::lookup( (float) $latitude, (float) $longitude, $args['lookup'] );
			$term    = $address ? Location_Taxonomy::get_location( $address, true ) : false;
			if ( ! $term ) {
				$changes[] = self::change( 'post', $post_id, get_the_title( $post_id ), __( 'No location found', 'simple-location' ) );
				continue;
			}
			Location_Taxonomy::set_location( $post_id, (int) $term );
			$location  = get_term( (int) $term, 'location' );
			/* translators: %s: Location name. */
			$changes[] = self::change( 'post', $post_id, get_the_title( $post_id ), sprintf( __( 'Given the location %s', 'simple-location' ), $location instanceof WP_Term ? $location->name : (string) $term ) );
		}
		if ( $skipped ) {
			/* translators: %d: Number of posts. */
			$changes[] = self::change( 'post', 0, '', sprintf( _n( '%d post left out because its location isn\'t public', '%d posts left out because their location isn\'t public', $skipped, 'simple-location' ), $skipped ) );
		}
		return $changes;
	}

	/**
	 * Regions without a code: the posts in regions that have no ISO 3166-2 code, such as districts stored as regions,
	 * are looked up again through the geocoding providers, and regions left with no posts or towns are removed.
	 *
	 * @since 6.5.0
	 *
	 * @param bool  $apply Whether to change anything.
	 * @param array $args  Arguments: 'limit'. See run().
	 * @return array Changes.
	 */
	protected static function relookup( $apply, $args ) {
		$changes = array();
		$budget  = (int) $args['limit'];
		foreach ( self::terms() as $id => $item ) {
			if ( 'region' !== $item['type'] || self::is_region_code( $item['code'], $item['country'] ) || $budget <= 0 ) {
				continue;
			}
			$term_ids = array_merge( array( $id ), get_term_children( $id, 'location' ) );
			$posts    = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'any',
					'posts_per_page' => $budget,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- An admin tool, run on demand.
					'tax_query'      => array(
						array(
							'taxonomy'         => 'location',
							'terms'            => $term_ids,
							'include_children' => false,
						),
					),
				)
			);
			/* translators: %d: Number of posts. */
			$changes[] = self::change( 'term', $id, $item['term']->name, sprintf( _n( 'Has no region code; %d post looked up again', 'Has no region code; %d posts looked up again', count( $posts ), 'simple-location' ), count( $posts ) ) );
			if ( ! $apply ) {
				$budget -= count( $posts );
				continue;
			}
			foreach ( $posts as $post_id ) {
				--$budget;
				$latitude  = get_post_meta( $post_id, 'geo_latitude', true );
				$longitude = get_post_meta( $post_id, 'geo_longitude', true );
				$address   = ( is_numeric( $latitude ) && is_numeric( $longitude ) ) ? self::lookup( (float) $latitude, (float) $longitude, 'chain' ) : null;
				$term      = $address ? Location_Taxonomy::get_location( $address, true ) : false;
				if ( $term && ! in_array( (int) $term, $term_ids, true ) ) {
					Location_Taxonomy::set_location( $post_id, (int) $term );
				}
			}
			// Remove the region and its towns once nothing is left in them.
			foreach ( array_reverse( $term_ids ) as $term_id ) {
				$term = get_term( (int) $term_id, 'location' );
				if ( $term instanceof WP_Term && 0 === (int) $term->count && ! get_term_children( $term_id, 'location' ) ) {
					wp_delete_term( $term_id, 'location' );
				}
			}
		}
		return $changes;
	}

	/**
	 * Names in the site language: countries and regions are renamed to their names in the site language, keeping the
	 * old name as local_name.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Whether to change anything.
	 * @return array Changes.
	 */
	protected static function names( $apply ) {
		$changes = array();
		foreach ( self::terms() as $id => $item ) {
			$name = null;
			if ( 'country' === $item['type'] && 0 === $item['depth'] ) {
				$name = Sloc_Address_Format::country_name( $item['country'] );
			} elseif ( 'region' === $item['type'] && self::is_region_code( $item['code'], $item['country'] ) ) {
				$name = Sloc_Address_Format::region_name( $item['country'], $item['code'] );
			}
			if ( ! $name || $name === $item['term']->name ) {
				continue;
			}
			/* translators: %s: New name. */
			$changes[] = self::change( 'term', $id, $item['term']->name, sprintf( __( 'Renamed %s', 'simple-location' ), $name ) );
			if ( $apply ) {
				if ( '' === (string) get_term_meta( $id, 'local_name', true ) ) {
					add_term_meta( $id, 'local_name', $item['term']->name, true );
				}
				wp_update_term( $id, 'location', array( 'name' => $name ) );
			}
		}
		return $changes;
	}

	/**
	 * Stored addresses: counts addresses that the current rules would write differently, without changing them.
	 *
	 * @since 6.5.0
	 *
	 * @param bool $apply Unused; addresses are never changed.
	 * @return array Changes: one summary line, or none.
	 */
	protected static function addresses( $apply ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- An admin tool, run on demand, over every stored address.
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", 'geo_address' ) );
		$count = 0;
		foreach ( $rows as $address ) {
			if ( preg_match( '/\d;\d|Community (Board|District) \d|\bWard \d|Municipal District|London Borough of|\b(Village|Town|City|Borough) of /u', (string) $address ) ) {
				++$count;
			}
		}
		if ( ! $count ) {
			return array();
		}
		/* translators: %d: Number of addresses. */
		return array( self::change( 'post', 0, '', sprintf( _n( '%d stored address would be written differently now; addresses are not changed', '%d stored addresses would be written differently now; addresses are not changed', $count, 'simple-location' ), $count ) ) );
	}
}
