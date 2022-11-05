<?php
/**
 * Timezone Sidebar Metabox.
 */


if ( 'comment' === get_current_screen()->id ) {
	$timezone = get_comment_geodata( $comment->comment_ID, 'timezone' );
} else {
	$timezone = get_post_geodata( $post->ID, 'timezone' );
	if ( ! $timezone ) {
		$timezone = get_post_meta( $post->ID, '_timezone', true );
		if ( $timezone ) {
			set_post_geodata( $post->ID, 'timezone', $timezone );
			delete_post_meta( $post->ID, '_timezone' );
		}
	}
}

if ( ! $timezone ) {
	$user     = wp_get_current_user();
	$timezone = get_user_geodata( $user->ID, 'timezone' );
	if ( ! $timezone ) {
		$timezone = wp_timezone_string();
	}
}

wp_nonce_field( 'timezone_override_metabox', 'timezone_override_nonce' );

?>
<div class="location-section location-section-timezone">
	<span class="dashicons-before dashicons-clock" id="timezone-browser" title="<?php esc_html_e( 'Set Local Timezone', 'simple-location' ); ?>"> <?php esc_html_e( 'Timezone:', 'simple-location' ); ?></span>
		<span id="post-timezone-label">
		<?php
		if ( $timezone ) {
			echo esc_html( $timezone ); }
		?>
	</span>
	<a href="#post_timezone" class="edit-post-timezone hide-if-no-js" role="button"><span aria-hidden="true">Edit</span> <span class="screen-reader-text">Override Timezone</span></a>
		<div id="post-timezone-select" class="hide-if-js">
		<input type="hidden" name="hidden_post_timezone" id="hidden_post_timezone" value="<?php echo esc_html( $timezone ); ?>" />
		<input type="hidden" name="timezone_default" id="timezone_default" value="<?php echo esc_attr( wp_timezone_string() ); ?>" />
		<select name="post_timezone" id="post-timezone" width="90%">
		<?php
			echo Loc_Timezone::wp_timezone_choice( $timezone ); // phpcs:ignore
			echo '</select>';
		?>
		<p>
			<a href="#post_timezone" class="save-post-timezone hide-if-no-js button">OK</a>
			<a href="#post_timezone" class="cancel-post-timezone hide-if-no-js button-cancel">Cancel</a>
		</p>
	</div><!-- #post-timezone-select -->
</div><!-- .location-section -->
<?php
