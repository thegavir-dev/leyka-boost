<?php
/**
 * Admin menu for Leyka Boost.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu and shared Leyka Boost admin pages.
 */
class LeykaBoost_AdminMenu {
	const MENU_SLUG = 'leyka-boost';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_leyka_boost_clear_log', array( $this, 'ajax_clear_log' ) );
		add_action( 'wp_ajax_leyka_boost_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'admin_post_leyka_boost_download_log', array( $this, 'download_log' ) );
	}

	/**
	 * Register top-level menu and shared pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Leyka Boost', 'leyka-boost' ),
			__( 'Leyka Boost', 'leyka-boost' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-chart-line',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'leyka-boost' ),
			__( 'Settings', 'leyka-boost' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Event log', 'leyka-boost' ),
			__( 'Event log', 'leyka-boost' ),
			'manage_options',
			'leyka-boost-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue shared log page assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'leyka-boost_page_leyka-boost-logs' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'leyka-boost-admin-log',
			LEYKA_BOOST_URL . 'assets/css/admin-log.css',
			array(),
			LEYKA_BOOST_VERSION
		);

		wp_enqueue_script(
			'leyka-boost-admin-log',
			LEYKA_BOOST_URL . 'assets/js/admin-log.js',
			array(),
			LEYKA_BOOST_VERSION,
			true
		);

		wp_localize_script(
			'leyka-boost-admin-log',
			'leykaBoostLog',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'logNonce'     => wp_create_nonce( 'leyka_boost_log_nonce' ),
				'confirmClear' => __( 'Are you sure you want to clear the event log? This action cannot be undone.', 'leyka-boost' ),
			)
		);
	}

	/**
	 * Render main settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'leyka-boost' ) );
		}

		$settings = wp_parse_args(
			get_option( LeykaBoost_Core::OPTION_KEY, array() ),
			array(
				'module_utm'         => true,
				'module_toolkit'     => true,
				'module_close'       => true,
				'log_enabled'        => true,
				'log_level'          => 'INFO',
				'log_retention_days' => 30,
			)
		);
		?>
		<div class="wrap leyka-boost-settings">
			<h1><?php esc_html_e( 'Leyka Boost', 'leyka-boost' ); ?></h1>
			<form id="leyka-boost-settings-form" method="post">
				<?php wp_nonce_field( 'leyka_boost_settings_nonce', 'leyka_boost_settings_nonce' ); ?>
				<h2><?php esc_html_e( 'Modules', 'leyka-boost' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->render_module_row( 'module_utm', __( 'UTM Tracker v1.5.1', 'leyka-boost' ), 'leyka-utm-settings', $settings );
					$this->render_module_row( 'module_toolkit', __( 'Toolkit v1.0.0', 'leyka-boost' ), 'leyka-toolkit', $settings );
					$this->render_module_row( 'module_close', sprintf( '%s v1.1.4', __( 'Close Campaign', 'leyka-boost' ) ), 'leyka-close-campaign', $settings );
					?>
				</table>

				<h2><?php esc_html_e( 'Logging', 'leyka-boost' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Event log', 'leyka-boost' ); ?></th>
						<td>
							<label><input type="checkbox" name="log_enabled" value="1" <?php checked( ! empty( $settings['log_enabled'] ) ); ?>> <?php esc_html_e( 'Active', 'leyka-boost' ); ?></label>
							<p class="description"><?php esc_html_e( 'Writes Leyka Boost and module events to the shared event log.', 'leyka-boost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="leyka-boost-log-level"><?php esc_html_e( 'Level', 'leyka-boost' ); ?></label></th>
						<td>
							<select id="leyka-boost-log-level" name="log_level">
								<?php foreach ( array( 'ERROR', 'INFO', 'DEBUG' ) as $level ) : ?>
									<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $settings['log_level'], $level ); ?>><?php echo esc_html( $level ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'ERROR writes only errors, INFO adds regular events, DEBUG enables diagnostic entries.', 'leyka-boost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="leyka-boost-log-retention"><?php esc_html_e( 'Retention period', 'leyka-boost' ); ?></label></th>
						<td>
							<input id="leyka-boost-log-retention" type="number" min="1" max="365" name="log_retention_days" value="<?php echo esc_attr( absint( $settings['log_retention_days'] ) ); ?>">
							<p class="description"><?php esc_html_e( 'Number of days after which old entries are removed during rotation.', 'leyka-boost' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'leyka-boost' ), 'primary', 'submit', true, array( 'id' => 'leyka-boost-save-settings' ) ); ?>
				<p id="leyka-boost-settings-result" aria-live="polite"></p>
			</form>
			<script>
				(function () {
					var form = document.getElementById('leyka-boost-settings-form');
					if (!form) {
						return;
					}

					form.addEventListener('submit', function (event) {
						event.preventDefault();

						var result = document.getElementById('leyka-boost-settings-result');
						var data = new window.FormData(form);
						data.append('action', 'leyka_boost_save_settings');
						data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'leyka_boost_settings_nonce' ) ); ?>');

						window.fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
							method: 'POST',
							credentials: 'same-origin',
							body: data
						}).then(function (response) {
							return response.json();
						}).then(function (payload) {
							if (result) {
								result.textContent = payload.success && payload.data ? payload.data.message : '';
							}
						});
					});
				}());
			</script>
		</div>
		<?php
	}

	/**
	 * Render a module toggle row.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Module label.
	 * @param string $page     Settings page slug.
	 * @param array  $settings Settings.
	 * @return void
	 */
	private function render_module_row( $key, $label, $page, $settings ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>> <?php esc_html_e( 'Active', 'leyka-boost' ); ?></label>
				<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page ) ); ?>"><?php esc_html_e( 'Settings', 'leyka-boost' ); ?></a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render unified log page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'leyka-boost' ) );
		}

		$data       = $this->get_log_entries();
		$entries    = $data['entries'];
		$total      = $data['total'];
		$truncated  = $data['truncated'];
		$statistics = $this->get_log_statistics();
		$modules    = array(
			'utm-tracker'    => __( 'UTM Tracker', 'leyka-boost' ),
			'toolkit'        => __( 'Toolkit', 'leyka-boost' ),
			'close-campaign' => __( 'Close Campaign', 'leyka-boost' ),
			'core'           => __( 'Core', 'leyka-boost' ),
		);
		?>
		<div class="wrap leyka-boost-log-page">
			<h1><?php esc_html_e( 'Leyka Boost event log', 'leyka-boost' ); ?></h1>
			<div class="leyka-boost-log-stats">
				<?php foreach ( array( 'TOTAL', 'ERROR', 'INFO', 'DEBUG' ) as $level ) : ?>
					<div class="leyka-boost-log-card leyka-boost-log-card--<?php echo esc_attr( strtolower( $level ) ); ?>">
						<span><?php echo esc_html( 'TOTAL' === $level ? __( 'Total', 'leyka-boost' ) : $level ); ?></span>
						<strong><?php echo esc_html( isset( $statistics[ $level ] ) ? $statistics[ $level ] : 0 ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="leyka-boost-log-toolbar">
				<input type="search" id="leyka-boost-log-search" placeholder="<?php esc_attr_e( 'Search by message', 'leyka-boost' ); ?>">
				<select id="leyka-boost-log-module">
					<option value=""><?php esc_html_e( 'All modules', 'leyka-boost' ); ?></option>
					<?php foreach ( $modules as $module => $label ) : ?>
						<option value="<?php echo esc_attr( $module ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select id="leyka-boost-log-level">
					<option value=""><?php esc_html_e( 'All levels', 'leyka-boost' ); ?></option>
					<?php foreach ( array( 'ERROR', 'INFO', 'DEBUG' ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-link-delete" id="leyka-boost-log-clear"><?php esc_html_e( 'Clear event log', 'leyka-boost' ); ?></button>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=leyka_boost_download_log' ), 'leyka_boost_download_log' ) ); ?>"><?php esc_html_e( 'Download log', 'leyka-boost' ); ?></a>
			</div>

			<?php if ( $truncated ) : ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Showing the latest 500 entries out of %d.', 'leyka-boost' ), $total ) ); ?></p>
			<?php endif; ?>

			<table class="widefat striped leyka-boost-log-table">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Time', 'leyka-boost' ); ?></th>
						<th><?php esc_html_e( 'Level', 'leyka-boost' ); ?></th>
						<th><?php esc_html_e( 'Module', 'leyka-boost' ); ?></th>
						<th><?php esc_html_e( 'Message', 'leyka-boost' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr class="leyka-boost-log-empty"><td colspan="5"><span aria-hidden="true" class="dashicons dashicons-list-view"></span><strong><?php esc_html_e( 'The event log is empty.', 'leyka-boost' ); ?></strong><span><?php esc_html_e( 'Entries will appear here as the plugin runs.', 'leyka-boost' ); ?></span></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $index => $entry ) : ?>
							<tr data-message="<?php echo esc_attr( strtolower( $entry['message'] ) ); ?>" data-module="<?php echo esc_attr( $entry['module'] ); ?>" data-level="<?php echo esc_attr( $entry['level'] ); ?>">
								<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td><?php echo esc_html( $entry['time'] ); ?></td>
								<td><span class="leyka-boost-level leyka-boost-level--<?php echo esc_attr( strtolower( $entry['level'] ) ); ?>"><?php echo esc_html( $entry['level'] ); ?></span></td>
								<td><span class="leyka-boost-module leyka-boost-module--<?php echo esc_attr( $entry['module'] ); ?>"><?php echo esc_html( $this->get_module_label( $entry['module'] ) ); ?></span></td>
								<td><?php echo esc_html( $entry['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					<tr class="leyka-boost-log-empty leyka-boost-log-empty--filtered" hidden><td colspan="5"><span aria-hidden="true" class="dashicons dashicons-search"></span><strong><?php esc_html_e( 'No entries found for the selected filters.', 'leyka-boost' ); ?></strong></td></tr>
				</tbody>
			</table>
			<div class="tablenav bottom leyka-boost-log-pagination" hidden>
				<div class="tablenav-pages"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Clear log via AJAX.
	 *
	 * @return void
	 */
	public function ajax_clear_log() {
		check_ajax_referer( 'leyka_boost_log_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'leyka-boost' ) ), 403 );
		}

		LeykaBoost_Logger::clear();
		delete_transient( 'leyka_boost_log_stats' );
		wp_send_json_success();
	}

	/**
	 * Save shared settings via AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'leyka_boost_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'leyka-boost' ) ), 403 );
		}

		$settings = array(
			'module_utm'         => ! empty( $_POST['module_utm'] ),
			'module_toolkit'     => ! empty( $_POST['module_toolkit'] ),
			'module_close'       => ! empty( $_POST['module_close'] ),
			'log_enabled'        => ! empty( $_POST['log_enabled'] ),
			'log_level'          => in_array( strtoupper( sanitize_text_field( wp_unslash( $_POST['log_level'] ?? 'INFO' ) ) ), array( 'ERROR', 'INFO', 'DEBUG' ), true ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['log_level'] ) ) ) : 'INFO',
			'log_retention_days' => max( 1, absint( wp_unslash( $_POST['log_retention_days'] ?? 30 ) ) ),
		);

		update_option( LeykaBoost_Core::OPTION_KEY, $settings );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'leyka-boost' ) ) );
	}

	/**
	 * Download log file.
	 *
	 * @return void
	 */
	public function download_log() {
		check_admin_referer( 'leyka_boost_download_log' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'leyka-boost' ) );
		}

		$path = LeykaBoost_Logger::get_log_path();

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Log file does not exist.', 'leyka-boost' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Parse log entries.
	 *
	 * @return array
	 */
	private function get_log_entries() {
		$path = LeykaBoost_Logger::get_log_path();

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return array(
				'entries'   => array(),
				'total'     => 0,
				'truncated' => false,
			);
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file

		if ( false === $lines ) {
			$lines = array();
		}

		$total   = count( $lines );
		$lines   = array_slice( array_reverse( $lines ), 0, 500 );
		$entries = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/^\[([^\]]+)\] \[([A-Z]+)\] \[([^\]]+)\] (.*)$/', $line, $matches ) ) {
				$entries[] = array(
					'time'    => $matches[1],
					'level'   => $matches[2],
					'module'  => $matches[3],
					'message' => $matches[4],
				);
			}
		}

		return array(
			'entries'   => $entries,
			'total'     => $total,
			'truncated' => $total > 500,
		);
	}

	/**
	 * Get human-readable module label.
	 *
	 * @param string $module Module slug.
	 * @return string
	 */
	private function get_module_label( $module ) {
		$labels = array(
			'utm-tracker'    => __( 'UTM Tracker', 'leyka-boost' ),
			'toolkit'        => __( 'Toolkit', 'leyka-boost' ),
			'close-campaign' => __( 'Close Campaign', 'leyka-boost' ),
			'core'           => __( 'Core', 'leyka-boost' ),
			'logger'         => __( 'Logger', 'leyka-boost' ),
			'admin-ui'       => __( 'Admin UI', 'leyka-boost' ),
		);

		return isset( $labels[ $module ] ) ? $labels[ $module ] : $module;
	}

	/**
	 * Get cached log statistics.
	 *
	 * @return array
	 */
	private function get_log_statistics() {
		$stats = get_transient( 'leyka_boost_log_stats' );

		if ( is_array( $stats ) ) {
			return $stats;
		}

		$stats = array(
			'TOTAL' => 0,
			'ERROR' => 0,
			'INFO'  => 0,
			'DEBUG' => 0,
		);

		$path = LeykaBoost_Logger::get_log_path();

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			set_transient( 'leyka_boost_log_stats', $stats, 5 * MINUTE_IN_SECONDS );
			return $stats;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file

		if ( false === $lines ) {
			set_transient( 'leyka_boost_log_stats', $stats, 5 * MINUTE_IN_SECONDS );
			return $stats;
		}

		$stats['TOTAL'] = count( $lines );

		foreach ( $lines as $line ) {
			if ( preg_match( '/^\[[^\]]+\] \[([A-Z]+)\]/', $line, $matches ) ) {
				$level = $matches[1];
				if ( isset( $stats[ $level ] ) ) {
					$stats[ $level ]++;
				}
			}
		}

		set_transient( 'leyka_boost_log_stats', $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}
}
