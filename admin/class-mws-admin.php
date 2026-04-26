<?php
/**
 * Admin settings page for Multi WordPress Search.
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWS_Admin
 *
 * Registers the plugin settings page under Settings > Multi WP Search,
 * allowing administrators to add, edit, and remove the list of WordPress
 * sites that are queried by the search engine.
 */
class MWS_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var MWS_Admin
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return MWS_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registers the settings page under the Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Multi WordPress Search', 'multi-wordpress-search' ),
			__( 'Multi WP Search', 'multi-wordpress-search' ),
			'manage_options',
			'multi-wordpress-search',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers the plugin option with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'mws_settings_group',
			MWS_OPTION_SITES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_sites' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'mws_sites_section',
			__( 'WordPress Sites to Search', 'multi-wordpress-search' ),
			array( $this, 'render_section_description' ),
			'multi-wordpress-search'
		);

		add_settings_field(
			'mws_sites_field',
			__( 'Sites', 'multi-wordpress-search' ),
			array( $this, 'render_sites_field' ),
			'multi-wordpress-search',
			'mws_sites_section'
		);
	}

	/**
	 * Sanitises the list of sites before saving to the database.
	 *
	 * @param mixed $raw Raw option value submitted via the form.
	 * @return array Sanitised array of site configs.
	 */
	public function sanitize_sites( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$sanitised = array();
		foreach ( $raw as $site ) {
			if ( empty( $site['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( trim( $site['url'] ) );
			if ( empty( $url ) ) {
				continue;
			}

			$sanitised[] = array(
				'url'  => $url,
				'name' => isset( $site['name'] ) ? sanitize_text_field( $site['name'] ) : '',
			);
		}

		return $sanitised;
	}

	/**
	 * Renders the section description.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Add the base URLs of the WordPress sites you want to include in the search results. The plugin will query each site\'s REST API (/wp-json/wp/v2/search).', 'multi-wordpress-search' ) . '</p>';
	}

	/**
	 * Renders the sites input field (dynamic list managed with JS).
	 */
	public function render_sites_field() {
		$sites = get_option( MWS_OPTION_SITES, array() );
		include MWS_PLUGIN_DIR . 'admin/admin-page.php';
	}

	/**
	 * Renders the full settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Multi WordPress Search', 'multi-wordpress-search' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'mws_settings_group' );
				do_settings_sections( 'multi-wordpress-search' );
				submit_button( __( 'Save Sites', 'multi-wordpress-search' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueues admin JS on the plugin's own settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_multi-wordpress-search' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'mws-admin',
			MWS_PLUGIN_URL . 'admin/js/mws-admin.js',
			array( 'jquery' ),
			MWS_VERSION,
			true
		);

		wp_localize_script(
			'mws-admin',
			'mwsAdmin',
			array(
				'addSiteLabel'    => __( 'Add another site', 'multi-wordpress-search' ),
				'removeSiteLabel' => __( 'Remove', 'multi-wordpress-search' ),
				'urlPlaceholder'  => __( 'https://example.com', 'multi-wordpress-search' ),
				'namePlaceholder' => __( 'Site label (optional)', 'multi-wordpress-search' ),
			)
		);
	}
}
