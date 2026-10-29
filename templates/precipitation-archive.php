<?php
/**
 * Precipitation Archive Template.
 *
 * A bar chart of the rain or snow of the posts in an archive, with a list of them. Used by rain-archive.php and
 * snow-archive.php, which set $sloc_property to 'rain' or 'snow'.
 *
 * @package Simple_Location
 * @since 6.2.0
 */

defined( 'ABSPATH' ) || exit;

$sloc_property = ( isset( $sloc_property ) && 'snow' === $sloc_property ) ? 'snow' : 'rain';

get_header();
if ( is_day() ) {
	$sloc_date_format = get_option( 'time_format' );
} else {
	$sloc_date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
}
$sloc_readings = Sloc_Weather_Archive::get_readings( $sloc_property );
$sloc_unit     = Sloc_Weather_Archive::precipitation_unit();
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
			<?php echo Sloc_Weather_Archive::get_precipitation_chart( $sloc_readings, $sloc_property ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_precipitation_chart(). ?>
			<ul class="sloc-precipitation-list sloc-<?php echo esc_attr( $sloc_property ); ?>-list">
				<?php foreach ( $sloc_readings as $sloc_post_id => $sloc_reading ) { ?>
					<li class="h-entry">
						<a class="u-url" href="<?php echo esc_url( get_permalink( $sloc_post_id ) ); ?>"><time class="dt-published" datetime="<?php echo esc_attr( get_post_time( DATE_W3C, true, $sloc_post_id ) ); ?>"><?php echo esc_html( get_the_time( $sloc_date_format, $sloc_post_id ) ); ?></time></a>
						<?php echo esc_html( Sloc_Weather_Archive::display_precipitation( $sloc_reading[ $sloc_property ] ) . ' ' . $sloc_unit ); ?>
					</li>
				<?php } ?>
			</ul>
		<?php } elseif ( 'snow' === $sloc_property ) { ?>
			<p><?php esc_html_e( 'There are no posts with snowfall here.', 'simple-location' ); ?></p>
		<?php } else { ?>
			<p><?php esc_html_e( 'There are no posts with rainfall here.', 'simple-location' ); ?></p>
		<?php } ?>
	</main><!-- .site-main -->
</div><!-- .content-area -->
<?php
get_sidebar();
get_footer();
