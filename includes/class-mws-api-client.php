<?php
/**
 * REST API client that queries multiple WordPress sites.
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWS_API_Client
 *
 * Queries the WordPress REST API on one or more remote sites and aggregates
 * the results into a single list ordered by relevance (title match first,
 * then by site order as configured by the administrator).
 */
class MWS_API_Client {

	/**
	 * Number of results to request per site.
	 *
	 * @var int
	 */
	private $per_page;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Constructor.
	 *
	 * @param int $per_page Results to request from each site (default 10).
	 * @param int $timeout  HTTP timeout in seconds (default 10).
	 */
	public function __construct( $per_page = 10, $timeout = 10 ) {
		$this->per_page = absint( $per_page );
		$this->timeout  = absint( $timeout );
	}

	/**
	 * Searches all configured sites and returns a merged, deduplicated result list.
	 *
	 * @param string $query Search query string.
	 * @param array  $sites Array of site configs, each with at least a 'url' key.
	 * @return array[] Array of result items, each with keys: title, url, excerpt, site_name, site_url.
	 */
	public function search_all( $query, array $sites ) {
		$results = array();

		foreach ( $sites as $site ) {
			if ( empty( $site['url'] ) ) {
				continue;
			}

			$site_results = $this->search_site( $query, $site );
			$results      = array_merge( $results, $site_results );
		}

		return $results;
	}

	/**
	 * Queries the REST API of a single WordPress site.
	 *
	 * Endpoint: GET /wp-json/wp/v2/search?search=<query>&per_page=<n>&_embed=1
	 *
	 * @param string $query Search query string.
	 * @param array  $site  Site configuration array with keys: url, (optional) name.
	 * @return array[] Array of normalised result items.
	 */
	public function search_site( $query, array $site ) {
		$base_url = trailingslashit( esc_url_raw( $site['url'] ) );
		$site_name = isset( $site['name'] ) ? sanitize_text_field( $site['name'] ) : wp_parse_url( $base_url, PHP_URL_HOST );

		$api_url = add_query_arg(
			array(
				'search'   => rawurlencode( $query ),
				'per_page' => $this->per_page,
				'_embed'   => 1,
			),
			$base_url . 'wp-json/wp/v2/search'
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'    => $this->timeout,
				'user-agent' => 'MultiWordpressSearch/' . MWS_VERSION . '; ' . get_bloginfo( 'url' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		return $this->normalise_results( $data, $site_name, $base_url );
	}

	/**
	 * Normalises raw REST API items into a consistent result format.
	 *
	 * @param array  $items     Raw items from the API response.
	 * @param string $site_name Human-readable site label.
	 * @param string $site_url  Base URL of the remote site.
	 * @return array[] Normalised result items.
	 */
	private function normalise_results( array $items, $site_name, $site_url ) {
		$results = array();

		foreach ( $items as $item ) {
			if ( empty( $item['url'] ) || empty( $item['title'] ) ) {
				continue;
			}

			$excerpt = '';
			if ( ! empty( $item['_embedded']['self'][0]['excerpt']['rendered'] ) ) {
				$excerpt = wp_strip_all_tags( $item['_embedded']['self'][0]['excerpt']['rendered'] );
			} elseif ( ! empty( $item['_embedded']['self'][0]['content']['rendered'] ) ) {
				$excerpt = wp_trim_words(
					wp_strip_all_tags( $item['_embedded']['self'][0]['content']['rendered'] ),
					30
				);
			}

			$results[] = array(
				'title'     => sanitize_text_field( $item['title'] ),
				'url'       => esc_url_raw( $item['url'] ),
				'excerpt'   => $excerpt,
				'type'      => isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'post',
				'site_name' => $site_name,
				'site_url'  => $site_url,
			);
		}

		return $results;
	}
}
