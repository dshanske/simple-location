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
				if ( empty( get_the_title() ) ) {
					printf( '<li><a href="%1$s">%2$s %3$s</a></li>', get_the_permalink(), get_the_date(), get_the_time() );
				} else {
					the_title( sprintf( '<li class="p-name"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></li>' );
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
