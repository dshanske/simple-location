<?php
/**
 * Temperature Archive Template.
 *
 * A chart of the temperatures of the posts in an archive, with a list of them. A theme can override it with a
 * temperature-archive.php of its own.
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
$sloc_unit = Sloc_Weather_Archive::is_imperial() ? __( '°F', 'simple-location' ) : __( '°C', 'simple-location' );
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
			<?php echo Sloc_Weather_Archive::get_temperature_chart( Sloc_Weather_Archive::get_temperatures() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_temperature_chart(). ?>
			<ul class="sloc-temperature-list">
				<?php foreach ( Sloc_Weather_Archive::get_temperatures() as $sloc_post_id => $sloc_temperature ) { ?>
					<li class="h-entry">
						<a class="u-url" href="<?php echo esc_url( get_permalink( $sloc_post_id ) ); ?>"><time class="dt-published" datetime="<?php echo esc_attr( get_post_time( DATE_W3C, true, $sloc_post_id ) ); ?>"><?php echo esc_html( get_the_time( $sloc_date_format, $sloc_post_id ) ); ?></time></a>
						<?php echo esc_html( Sloc_Weather_Archive::display_temperature( $sloc_temperature['temperature'] ) . $sloc_unit ); ?>
					</li>
				<?php } ?>
			</ul>
		<?php } else { ?>
			<p><?php esc_html_e( 'There are no posts with temperatures here.', 'simple-location' ); ?></p>
		<?php } ?>
	</main><!-- .site-main -->
</div><!-- .content-area -->
<?php
get_sidebar();
get_footer();
