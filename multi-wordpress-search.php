<?php
/**
 * Plugin Name: Multi WordPress Search
 * Plugin URI:  https://github.com/d9pouces/MultiWordpressSearch
 * Description: Replaces the default WordPress search form with a custom search engine that queries multiple WordPress sites via the official REST API.
 * Version:     1.0.0
 * Author:      d9pouces
 * Author URI:  https://github.com/d9pouces
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multi-wordpress-search
 * Domain Path: /languages
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MWS_VERSION', '1.0.0' );
define( 'MWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MWS_OPTION_SITES', 'mws_sites' );

require_once MWS_PLUGIN_DIR . 'includes/class-mws-api-client.php';
require_once MWS_PLUGIN_DIR . 'includes/class-mws-search-form.php';
require_once MWS_PLUGIN_DIR . 'admin/class-mws-admin.php';

/**
 * Main plugin class.
 */
class Multi_Wordpress_Search {

	/**
	 * Singleton instance.
	 *
	 * @var Multi_Wordpress_Search
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return Multi_Wordpress_Search
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
		$this->init_hooks();
	}

	/**
	 * Registers all plugin hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		// Replace default search form.
		add_filter( 'get_search_form', array( 'MWS_Search_Form', 'render_search_form' ), 10, 2 );

		// Handle AJAX search requests.
		add_action( 'wp_ajax_mws_search', array( $this, 'handle_ajax_search' ) );
		add_action( 'wp_ajax_nopriv_mws_search', array( $this, 'handle_ajax_search' ) );

		// Register shortcode.
		add_shortcode( 'multi_wordpress_search', array( $this, 'render_shortcode' ) );

		// Admin.
		if ( is_admin() ) {
			MWS_Admin::get_instance();
		}
	}

	/**
	 * Loads plugin text domain for i18n.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'multi-wordpress-search',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Enqueues public CSS and JS.
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style(
			'multi-wordpress-search',
			MWS_PLUGIN_URL . 'public/css/multi-wordpress-search.css',
			array(),
			MWS_VERSION
		);

		wp_enqueue_script(
			'multi-wordpress-search',
			MWS_PLUGIN_URL . 'public/js/multi-wordpress-search.js',
			array( 'jquery' ),
			MWS_VERSION,
			true
		);

		wp_localize_script(
			'multi-wordpress-search',
			'mwsData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mws_search_nonce' ),
				'loading' => esc_html__( 'Searching…', 'multi-wordpress-search' ),
				'noResults' => esc_html__( 'No results found.', 'multi-wordpress-search' ),
			)
		);
	}

	/**
	 * Handles AJAX search requests and returns JSON results.
	 */
	public function handle_ajax_search() {
		check_ajax_referer( 'mws_search_nonce', 'nonce' );

		$query = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';

		if ( empty( $query ) ) {
			wp_send_json_error( array( 'message' => __( 'Empty search query.', 'multi-wordpress-search' ) ) );
		}

		$sites  = get_option( MWS_OPTION_SITES, array() );
		$client = new MWS_API_Client();
		$results = $client->search_all( $query, $sites );

		wp_send_json_success( $results );
	}

	/**
	 * Renders the search shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder' => __( 'Search across WordPress sites…', 'multi-wordpress-search' ),
			),
			$atts,
			'multi_wordpress_search'
		);

		return MWS_Search_Form::render_search_form( '', array( 'placeholder' => $atts['placeholder'] ) );
	}
}

/**
 * Plugin activation hook: sets default options.
 */
function mws_activate() {
	if ( false === get_option( MWS_OPTION_SITES ) ) {
		add_option( MWS_OPTION_SITES, array() );
	}
}
register_activation_hook( __FILE__, 'mws_activate' );

/**
 * Plugin deactivation hook.
 */
function mws_deactivate() {
	// Nothing to clean up on deactivation.
}
register_deactivation_hook( __FILE__, 'mws_deactivate' );

// Bootstrap the plugin.
Multi_Wordpress_Search::get_instance();
