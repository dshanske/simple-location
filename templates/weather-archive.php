<?php
/**
 * Weather Archive Template.
 *
 * The weather of each post in an archive, with its icon and summary. A theme can override it with a
 * weather-archive.php of its own.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

get_header();
if ( is_day() ) {
	$sloc_date_format = get_option( 'time_format' );
} else {
	$sloc_date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
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
			<ul class="sloc-weather-list">
				<?php
				while ( have_posts() ) {
					the_post();
					// A private location hides its weather.
					if ( 'private' === get_post_geodata( get_the_ID(), 'visibility' ) ) {
						continue;
					}
					?>
					<li class="h-entry">
						<a class="u-url" href="<?php the_permalink(); ?>"><time class="dt-published" datetime="<?php echo esc_attr( get_post_time( DATE_W3C, true ) ); ?>"><?php echo esc_html( get_the_time( $sloc_date_format ) ); ?></time></a>
						<?php
						echo wp_kses(
							Sloc_Weather_Data::get_the_weather(
								'post',
								get_the_ID(),
								array(
									'description'  => '',
									'wrapper-type' => 'span',
								)
							),
							Simple_Location_Plugin::kses_clean()
						);
						?>
					</li>
				<?php } ?>
			</ul>
		<?php } else { ?>
			<p><?php esc_html_e( 'There are no posts with weather here.', 'simple-location' ); ?></p>
		<?php } ?>
	</main><!-- .site-main -->
</div><!-- .content-area -->
<?php
get_sidebar();
get_footer();
