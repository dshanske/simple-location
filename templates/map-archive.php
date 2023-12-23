<?php
/**
 * Map Archive Template.
 *
 * @package Simple_Location
 */

get_header();
if ( is_day() ) {
	$date_format = get_option( 'time_format' );
} else {
	$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
}
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

			if ( 'public' === get_post_geodata( get_the_ID(), 'visibility' ) ) {
					echo get_post_location(
						get_the_ID(),
						array(
							'weather'       => false,
							'wrapper-type'  => 'li',
							'wrapper-class' => 'h-entry',
							'markup'        => false,
							'icon'          => false,
							'object_link'   => true,
							'altitude'      => false,
							'text' => true,
							'description' => get_the_time( $date_format, get_the_ID() )
						)
					);
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
