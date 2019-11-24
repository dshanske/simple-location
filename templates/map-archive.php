<?php

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
		<?php $map = Loc_Config::map_provider(); ?>
		<img class="archive-map sloc-map" src="<?php echo $map->get_archive_map( WP_Geo_Data::get_archive_public_location_list() ); ?>" />
		<ul>
		<?php } 
			// Start the Loop.
			while ( have_posts() ) {
				the_post();
				
				$location = WP_Geo_Data::get_geodata( get_post(), false );
				if ( 'public' === $location['visibility'] && array_key_exists( 'address', $location ) ) {
					printf( '<li class="h-entry"><a class="u-url p-location" href="%1$s">%2$s</a></li>', get_the_permalink(), $location['address'] );	
				}
			}
?>
		</ul>
<?php
					// Previous/next page navigation.
								the_posts_pagination(
									array(
										'prev_text'          => __( 'Previous page', 'simple-location' ),
										'next_text'          => __( 'Next page', 'simple-location' ),
										'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'simple-location' ) . ' </span>',
									)																															);
?>
		</main><!-- .site-main -->
	</div><!-- .content-area -->
<?php
get_sidebar();
get_footer();

?>
