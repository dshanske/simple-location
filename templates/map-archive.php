<?php
/**
 * Map Archive Template.
 *
 * @package Simple_Location
 */

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<?php if ( have_posts() ) { ?>
			<header class="page-header">
				<?php
					the_archive_title( '<h1 class="page-title">', '</h1>' );
					the_archive_description( '<div class="taxonomy-description">', '</div>' );
				?>
			</header><!-- .page-header -->
			<?php $map_provider = Loc_Config::map_provider(); ?>
		<img class="archive-map sloc-map" src="<?php echo wp_kses_post( $map_provider->get_archive_map( get_geo_archive_location_list() ) ); ?>" />
		<ul>
			<?php
		}
			// Start the Loop.
		while ( have_posts() ) {
			the_post();

			$location = get_post_geodata( get_post() );

			if ( is_array( $location ) ) {
				if ( 'public' === $location['visibility'] && array_key_exists( 'address', $location ) ) {
					printf( '<li class="h-entry"><a class="u-url p-location" href="%1$s">%2$s</a></li>', esc_url( get_the_permalink() ), esc_html( $location['address'] ) );
				}
			}
		}
		?>
		</ul>
		</main><!-- .site-main -->
	</div><!-- .content-area -->
<?php
get_sidebar();
get_footer();

?>
