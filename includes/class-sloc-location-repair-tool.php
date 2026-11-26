<?php
/**
 * Location Repair page, and the `wp location repair` command.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Location Repair, to preview and run Sloc_Location_Repair, and its WP-CLI command.
 *
 * @since 6.5.0
 */
class Sloc_Location_Repair_Tool {

	/**
	 * Page slug.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	const PAGE = 'sloc-location-repair';

	/**
	 * Hooks the page, and the command under WP-CLI.
	 *
	 * @since 6.5.0
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'location repair', array( __CLASS__, 'command' ) );
		}
	}

	/**
	 * Adds the page under Tools.
	 *
	 * @since 6.5.0
	 */
	public static function admin_menu() {
		add_management_page(
			__( 'Location Repair', 'simple-location' ),
			__( 'Location Repair', 'simple-location' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Returns the request's choices, from the form or the command.
	 *
	 * @since 6.5.0
	 *
	 * @param array $input Steps (list), lookup and limit.
	 * @return array Steps (known ones, or the default steps without any), lookup ('offline', 'auto' or 'chain') and
	 *               limit (1 to 500).
	 */
	public static function choices( $input ) {
		$known = Sloc_Location_Repair::steps();
		$steps = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['steps'] ?? array() ) ), array_keys( $known ) ) );
		if ( empty( $steps ) ) {
			$steps = array_keys( array_filter( $known, fn( $step ) => $step['default'] ) );
		}
		$limit = isset( $input['limit'] ) && is_numeric( $input['limit'] ) ? (int) $input['limit'] : 50;
		return array(
			'steps'  => $steps,
			'lookup' => in_array( $input['lookup'] ?? '', array( 'auto', 'chain' ), true ) ? $input['lookup'] : 'offline',
			'limit'  => max( 1, min( 500, $limit ) ),
		);
	}

	/**
	 * Echoes the page: the steps to choose, a preview, and the result of a run.
	 *
	 * @since 6.5.0
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$choices = self::choices( array() );
		$report  = null;
		$applied = false;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified before the fields are used.
		if ( isset( $_POST['sloc_repair_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sloc_repair_nonce'] ) ), 'sloc_repair' ) ) {
			$choices = self::choices(
				array(
					'steps'  => isset( $_POST['sloc_repair_steps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sloc_repair_steps'] ) ) : array(),
					'lookup' => isset( $_POST['sloc_repair_lookup'] ) ? sanitize_key( wp_unslash( $_POST['sloc_repair_lookup'] ) ) : '',
					'limit'  => isset( $_POST['sloc_repair_limit'] ) ? absint( $_POST['sloc_repair_limit'] ) : 50,
				)
			);
			$applied = isset( $_POST['sloc_repair_apply'] );
			$report  = Sloc_Location_Repair::run( $choices['steps'], $applied, $choices );
		}
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Location Repair', 'simple-location' ); ?></h1>
			<p><?php esc_html_e( 'Brings locations saved by earlier versions up to date. Preview first: nothing changes until you choose Repair. Merged locations redirect to the ones kept.', 'simple-location' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'sloc_repair', 'sloc_repair_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Steps', 'simple-location' ); ?></th>
						<td>
							<?php foreach ( Sloc_Location_Repair::steps() as $step => $info ) : ?>
								<p>
									<label>
										<input type="checkbox" name="sloc_repair_steps[]" value="<?php echo esc_attr( $step ); ?>" <?php checked( in_array( $step, $choices['steps'], true ) ); ?> />
										<strong><?php echo esc_html( $info['label'] ); ?></strong>
									</label><br />
									<span class="description"><?php echo esc_html( $info['description'] ); ?></span>
								</p>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Look up posts', 'simple-location' ); ?></th>
						<td>
							<label><input type="radio" name="sloc_repair_lookup" value="offline" <?php checked( 'offline', $choices['lookup'] ); ?> /> <?php esc_html_e( 'From the data that comes with the plugin, without sending anything (towns, regions and countries)', 'simple-location' ); ?></label><br />
							<label><input type="radio" name="sloc_repair_lookup" value="auto" <?php checked( 'auto', $choices['lookup'] ); ?> /> <?php esc_html_e( 'From that data first, and through your geocoding providers only when it finds no town, or no region where addresses name one', 'simple-location' ); ?></label><br />
							<label><input type="radio" name="sloc_repair_lookup" value="chain" <?php checked( 'chain', $choices['lookup'] ); ?> /> <?php esc_html_e( 'Through your geocoding providers (more detail; some providers charge for lookups)', 'simple-location' ); ?></label>
							<p class="description"><?php esc_html_e( 'The preview never asks your providers; it says how many posts would be sent to them.', 'simple-location' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="sloc_repair_limit"><?php esc_html_e( 'Posts per run', 'simple-location' ); ?></label></th>
						<td>
							<input type="number" min="1" max="500" name="sloc_repair_limit" id="sloc_repair_limit" value="<?php echo esc_attr( (string) $choices['limit'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Run again for more.', 'simple-location' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<?php submit_button( __( 'Preview', 'simple-location' ), 'secondary', 'sloc_repair_preview', false ); ?>
					<?php submit_button( __( 'Repair', 'simple-location' ), 'primary', 'sloc_repair_apply', false ); ?>
				</p>
			</form>
			<?php
			if ( is_array( $report ) ) {
				self::print_report( $report, $applied );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Echoes a report as a table per step.
	 *
	 * @since 6.5.0
	 *
	 * @param array $report  Report, from Sloc_Location_Repair::run().
	 * @param bool  $applied Whether the changes were made.
	 */
	protected static function print_report( $report, $applied ) {
		$steps = Sloc_Location_Repair::steps();
		printf( '<h2>%s</h2>', esc_html( $applied ? __( 'Repaired', 'simple-location' ) : __( 'Preview', 'simple-location' ) ) );
		foreach ( $report as $step => $changes ) {
			printf( '<h3>%s</h3>', esc_html( $steps[ $step ]['label'] ?? $step ) );
			if ( empty( $changes ) ) {
				printf( '<p>%s</p>', esc_html__( 'Nothing to do.', 'simple-location' ) );
				continue;
			}
			echo '<table class="widefat striped"><thead><tr>';
			printf( '<th>%s</th><th>%s</th>', esc_html__( 'Location or post', 'simple-location' ), esc_html__( 'Change', 'simple-location' ) );
			echo '</tr></thead><tbody>';
			foreach ( $changes as $change ) {
				$link = '';
				if ( $change['id'] ) {
					$link = 'term' === $change['object'] ? get_edit_term_link( $change['id'], 'location' ) : get_edit_post_link( $change['id'] );
				}
				$name = '' === $change['name'] ? '—' : $change['name'];
				printf(
					'<tr><td>%s</td><td>%s</td></tr>',
					$link ? sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $name ) ) : esc_html( $name ),
					esc_html( $change['change'] )
				);
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Brings locations saved by earlier versions up to date.
	 *
	 * Previews unless --apply is given. Run as an administrator, with --user, since merging locations checks
	 * capabilities.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Make the changes. Without it, only shows them.
	 *
	 * [--steps=<steps>]
	 * : Comma-separated steps: region_codes, region_duplicates, types, orphan_localities, locality_duplicates, broken,
	 * untagged, coarse, relookup, names, addresses. Default: all but coarse, relookup and names.
	 *
	 * [--lookup=<lookup>]
	 * : How to look up posts: offline, from the bundled data; auto, from the bundled data and through the geocoding
	 * providers only when it finds no town, or no region where addresses name one; or chain, through the providers.
	 * ---
	 * default: offline
	 * options:
	 *   - offline
	 *   - auto
	 *   - chain
	 * ---
	 *
	 * [--limit=<limit>]
	 * : Most posts to look up in a run.
	 * ---
	 * default: 50
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp location repair --user=admin
	 *     wp location repair --user=admin --apply
	 *     wp location repair --user=admin --steps=untagged,coarse --lookup=auto --limit=200 --apply
	 *
	 * @since 6.5.0
	 *
	 * @param array $args       Positional arguments; none.
	 * @param array $assoc_args Options.
	 */
	public static function command( $args, $assoc_args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( __( 'Run as an administrator, for example with --user=admin.', 'simple-location' ) );
		}
		$choices = self::choices(
			array(
				'steps'  => isset( $assoc_args['steps'] ) ? explode( ',', (string) $assoc_args['steps'] ) : array(),
				'lookup' => $assoc_args['lookup'] ?? 'offline',
				'limit'  => $assoc_args['limit'] ?? 50,
			)
		);
		$apply   = ! empty( $assoc_args['apply'] );
		$steps   = Sloc_Location_Repair::steps();
		foreach ( Sloc_Location_Repair::run( $choices['steps'], $apply, $choices ) as $step => $changes ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%G' . ( $steps[ $step ]['label'] ?? $step ) . '%n' ) );
			if ( empty( $changes ) ) {
				WP_CLI::log( __( 'Nothing to do.', 'simple-location' ) );
				continue;
			}
			WP_CLI\Utils\format_items( 'table', $changes, array( 'object', 'id', 'name', 'change' ) );
		}
		WP_CLI::log( '' );
		if ( $apply ) {
			WP_CLI::success( __( 'Repaired.', 'simple-location' ) );
		} else {
			WP_CLI::log( __( 'Preview only. Add --apply to make these changes.', 'simple-location' ) );
		}
	}
}

add_action( 'init', array( 'Sloc_Location_Repair_Tool', 'init' ) );
