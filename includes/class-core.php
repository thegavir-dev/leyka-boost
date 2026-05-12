<?php
/**
 * Core bootstrap for Leyka Boost.
 *
 * @package LeykaBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin core.
 */
final class LeykaBoost_Core {
	const OPTION_KEY = 'leyka_boost_settings';

	/**
	 * Singleton instance.
	 *
	 * @var LeykaBoost_Core|null
	 */
	private static $instance = null;

	/**
	 * Loaded module instances.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Get singleton instance.
	 *
	 * @return LeykaBoost_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::check_requirements_for_activation();
		self::ensure_logs_directory();
		self::ensure_log_hash();
		self::ensure_default_settings();
		self::maybe_set_original_plugins_notice();
		self::activate_modules();

		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Check whether Leyka is active.
	 *
	 * @return bool
	 */
	public static function is_leyka_active() {
		if ( class_exists( 'Leyka' ) || defined( 'LEYKA_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'leyka/leyka.php' );
	}

	/**
	 * Get Leyka Boost setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::get_default_settings() );

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		require_once LEYKA_BOOST_PATH . 'includes/class-logger.php';

			add_action( 'plugins_loaded', array( $this, 'runtime_check_leyka' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'load_modules' ), 30 );
		add_action( 'admin_notices', array( $this, 'show_original_plugins_notice' ) );

		$this->init_admin_menu();
	}

		/**
		 * Show runtime warning when Leyka is unavailable.
	 *
	 * @return void
	 */
	public function runtime_check_leyka() {
		if ( self::is_leyka_active() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'show_leyka_missing_notice' ) );
	}

	/**
	 * Load enabled modules.
	 *
	 * @return void
	 */
	public function load_modules() {
		if ( ! self::is_leyka_active() ) {
			return;
		}

		$module_files = array(
			'module_utm'     => array(
				'file'  => LEYKA_BOOST_PATH . 'includes/modules/utm-tracker/class-module.php',
				'class' => 'LeykaBoost_Module_UTM',
			),
			'module_toolkit' => array(
				'file'  => LEYKA_BOOST_PATH . 'includes/modules/toolkit/class-module.php',
				'class' => 'LeykaBoost_Module_Toolkit',
			),
			'module_close'   => array(
				'file'  => LEYKA_BOOST_PATH . 'includes/modules/close-campaign/class-module.php',
				'class' => 'LeykaBoost_Module_Close',
			),
		);

		foreach ( $module_files as $setting_key => $module ) {
			if ( ! self::get_setting( $setting_key, true ) || ! file_exists( $module['file'] ) ) {
				continue;
			}

			require_once $module['file'];

			if ( ! class_exists( $module['class'] ) ) {
				continue;
			}

			$instance = new $module['class']();

			if ( method_exists( $instance, 'init' ) ) {
				$instance->init();
			}

			$this->modules[ $setting_key ] = $instance;
		}
	}

	/**
	 * Initialize admin menu.
	 *
	 * @return void
	 */
	public function init_admin_menu() {
		if ( ! is_admin() ) {
			return;
		}

		$file = LEYKA_BOOST_PATH . 'includes/class-admin-menu.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}

		if ( class_exists( 'LeykaBoost_AdminMenu' ) ) {
			new LeykaBoost_AdminMenu();
		}
	}

	/**
	 * Display Leyka missing notice.
	 *
	 * @return void
	 */
	public function show_leyka_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Leyka Boost requires the Leyka plugin to be active.', 'leyka-boost' );
		echo '</p></div>';
	}

	/**
	 * Display notice about original plugins.
	 *
	 * @return void
	 */
	public function show_original_plugins_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$plugins = get_transient( 'leyka_boost_original_plugins_notice' );

		if ( empty( $plugins ) || ! is_array( $plugins ) ) {
			return;
		}

		delete_transient( 'leyka_boost_original_plugins_notice' );

		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: comma-separated original plugin names. */
			esc_html__( 'Leyka Boost detected active original plugins: %s. Disable them after verifying Leyka Boost migration to avoid duplicated behavior.', 'leyka-boost' ),
			esc_html( implode( ', ', $plugins ) )
		);
		echo '</p></div>';
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @return void
	 */
		public function __wakeup() {
			_doing_it_wrong( __METHOD__, esc_html__( 'LeykaBoost_Core cannot be unserialized.', 'leyka-boost' ), esc_html( LEYKA_BOOST_VERSION ) );
		}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	private static function get_default_settings() {
		return array(
			'module_utm'         => true,
			'module_toolkit'     => true,
			'module_close'       => true,
			'log_enabled'        => true,
			'log_level'          => 'INFO',
			'log_retention_days' => 30,
		);
	}

	/**
	 * Check activation requirements.
	 *
	 * @return void
	 */
	private static function check_requirements_for_activation() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, LEYKA_BOOST_MIN_PHP_VERSION, '<' ) ) {
			wp_die(
				esc_html( sprintf( 'Leyka Boost requires PHP %s or higher.', LEYKA_BOOST_MIN_PHP_VERSION ) ),
				esc_html__( 'Leyka Boost activation error', 'leyka-boost' ),
				array( 'back_link' => true )
			);
		}

		if ( version_compare( $wp_version, LEYKA_BOOST_MIN_WP_VERSION, '<' ) ) {
			wp_die(
				esc_html( sprintf( 'Leyka Boost requires WordPress %s or higher.', LEYKA_BOOST_MIN_WP_VERSION ) ),
				esc_html__( 'Leyka Boost activation error', 'leyka-boost' ),
				array( 'back_link' => true )
			);
		}

		if ( ! self::is_leyka_active() ) {
			wp_die(
				esc_html__( 'Leyka Boost requires the Leyka plugin to be installed and active.', 'leyka-boost' ),
				esc_html__( 'Leyka Boost activation error', 'leyka-boost' ),
				array( 'back_link' => true )
			);
		}

		$leyka_version = self::get_leyka_version();

		if ( $leyka_version && version_compare( $leyka_version, LEYKA_BOOST_MIN_LEYKA_VERSION, '<' ) ) {
			wp_die(
				esc_html( sprintf( 'Leyka Boost requires Leyka %1$s or higher. Current version: %2$s.', LEYKA_BOOST_MIN_LEYKA_VERSION, $leyka_version ) ),
				esc_html__( 'Leyka Boost activation error', 'leyka-boost' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Get Leyka version when available.
	 *
	 * @return string
	 */
	private static function get_leyka_version() {
		if ( defined( 'LEYKA_VERSION' ) ) {
			return LEYKA_VERSION;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		return isset( $plugins['leyka/leyka.php']['Version'] ) ? $plugins['leyka/leyka.php']['Version'] : '';
	}

	/**
	 * Create logs directory and protection files.
	 *
	 * @return void
	 */
	private static function ensure_logs_directory() {
		if ( ! file_exists( LEYKA_BOOST_LOGS_DIR ) ) {
			wp_mkdir_p( LEYKA_BOOST_LOGS_DIR );
		}

		if ( is_dir( LEYKA_BOOST_LOGS_DIR ) ) {
			$htaccess = trailingslashit( LEYKA_BOOST_LOGS_DIR ) . '.htaccess';
			$index    = trailingslashit( LEYKA_BOOST_LOGS_DIR ) . 'index.php';

			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Ensure log hash exists.
	 *
	 * @return void
	 */
	private static function ensure_log_hash() {
		if ( get_option( 'leyka_boost_log_hash' ) ) {
			return;
		}

		update_option( 'leyka_boost_log_hash', wp_generate_password( 16, false, false ) );
	}

	/**
	 * Ensure default settings exist.
	 *
	 * @return void
	 */
	private static function ensure_default_settings() {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::get_default_settings() );

		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Activate module classes when they are implemented.
	 *
	 * @return void
	 */
	private static function activate_modules() {
		$modules = array(
			LEYKA_BOOST_PATH . 'includes/modules/utm-tracker/class-module.php'       => 'LeykaBoost_Module_UTM',
			LEYKA_BOOST_PATH . 'includes/modules/toolkit/class-module.php'           => 'LeykaBoost_Module_Toolkit',
			LEYKA_BOOST_PATH . 'includes/modules/close-campaign/class-module.php'    => 'LeykaBoost_Module_Close',
		);

		foreach ( $modules as $file => $class_name ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}

			if ( class_exists( $class_name ) && method_exists( $class_name, 'activate' ) ) {
				call_user_func( array( $class_name, 'activate' ) );
			}
		}
	}

	/**
	 * Set activation notice when original standalone plugins are active.
	 *
	 * @return void
	 */
	private static function maybe_set_original_plugins_notice() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$original_plugins = array(
			'leyka-utm-tracker/leyka-utm-tracker.php'         => 'UTM Tracker',
			'leyka-toolkit/leyka-toolkit.php'                 => 'Toolkit',
			'leyka-close-campaign/leyka-close-campaign.php'   => 'Close Campaign',
		);
		$active_plugins   = array();

		foreach ( $original_plugins as $plugin_file => $plugin_name ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active_plugins[] = $plugin_name;
			}
		}

		if ( $active_plugins ) {
			set_transient( 'leyka_boost_original_plugins_notice', $active_plugins, MINUTE_IN_SECONDS * 10 );
		}
	}
}
