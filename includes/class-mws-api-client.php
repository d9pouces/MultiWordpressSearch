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
	 * Number of tag terms to request when matching keywords.
	 *
	 * @var int
	 */
	private const TAGS_PER_PAGE = 20;

	/**
	 * Fallback REST base to query when no tagged post type is detected.
	 *
	 * @var string
	 */
	private const FALLBACK_TAG_REST_BASE = 'posts';

	/**
	 * WordPress taxonomy identifier used for post keywords.
	 *
	 * @var string
	 */
	private const TAG_TAXONOMY_NAME = 'post_tag';

	/**
	 * Number of words to keep when generating excerpt snippets.
	 *
	 * @var int
	 */
	private const EXCERPT_WORD_COUNT = 30;


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

		$search_api_url = add_query_arg(
			array(
				'search'   => rawurlencode( $query ),
				'per_page' => $this->per_page,
				'_embed'   => 1,
			),
			$base_url . 'wp-json/wp/v2/search'
		);

		$search_data      = $this->request_json( $search_api_url );
		$search_results   = $this->normalise_results( $search_data, $site_name, $base_url );
		$keyword_results  = $this->search_site_by_keywords( $query, $site_name, $base_url );

		return $this->merge_unique_results( $search_results, $keyword_results );
	}

	/**
	 * Performs a GET request and returns a decoded JSON array.
	 *
	 * @param string $api_url REST endpoint URL.
	 * @return array JSON response as associative array.
	 */
	private function request_json( $api_url ) {
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

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Searches content by matching keyword terms, then loading related posts.
	 *
	 * @param string $query     Search query string.
	 * @param string $site_name Human-readable site label.
	 * @param string $base_url  Base URL of the remote site.
	 * @return array[] Normalised result items.
	 */
	private function search_site_by_keywords( $query, $site_name, $base_url ) {
		$tag_ids = $this->get_matching_tag_ids( $query, $base_url );
		if ( empty( $tag_ids ) ) {
			return array();
		}

		$results    = array();
		$rest_bases = $this->get_tag_searchable_rest_bases( $base_url );

		foreach ( $rest_bases as $rest_base ) {
			$posts_api_url = add_query_arg(
				array(
					'tags'     => implode( ',', $tag_ids ),
					'per_page' => $this->per_page,
					'_embed'   => 1,
				),
				$base_url . 'wp-json/wp/v2/' . $rest_base
			);

			$posts_data = $this->request_json( $posts_api_url );
			if ( empty( $posts_data ) ) {
				continue;
			}

			$results = array_merge( $results, $this->normalise_content_results( $posts_data, $site_name, $base_url ) );
		}

		return $results;
	}

	/**
	 * Returns tag IDs matching the given query.
	 *
	 * @param string $query    Search query string.
	 * @param string $base_url Base URL of the remote site.
	 * @return int[] Matching tag IDs.
	 */
	private function get_matching_tag_ids( $query, $base_url ) {
		$tags_api_url = add_query_arg(
			array(
				'search'   => rawurlencode( $query ),
				'per_page' => self::TAGS_PER_PAGE,
			),
			$base_url . 'wp-json/wp/v2/tags'
		);

		$tags_data = $this->request_json( $tags_api_url );
		if ( empty( $tags_data ) ) {
			return array();
		}

		$tag_ids = array();
		foreach ( $tags_data as $tag_item ) {
			if ( ! empty( $tag_item['id'] ) ) {
				$tag_ids[] = absint( $tag_item['id'] );
			}
		}

		return array_values( array_unique( array_filter( $tag_ids ) ) );
	}

	/**
	 * Finds all post-type REST bases that support WordPress tags.
	 *
	 * @param string $base_url Base URL of the remote site.
	 * @return string[] REST bases to query.
	 */
	private function get_tag_searchable_rest_bases( $base_url ) {
		$types_data = $this->request_json( $base_url . 'wp-json/wp/v2/types' );
		if ( empty( $types_data ) ) {
			return array( self::FALLBACK_TAG_REST_BASE );
		}

		$rest_bases = array();

		foreach ( $types_data as $type_item ) {
			if ( empty( $type_item['rest_base'] ) || empty( $type_item['taxonomies'] ) || ! is_array( $type_item['taxonomies'] ) ) {
				continue;
			}

			if ( ! in_array( self::TAG_TAXONOMY_NAME, $type_item['taxonomies'], true ) ) {
				continue;
			}

			$rest_base = sanitize_key( $type_item['rest_base'] );
			if ( '' !== $rest_base ) {
				$rest_bases[] = $rest_base;
			}
		}

		if ( empty( $rest_bases ) ) {
			$rest_bases[] = self::FALLBACK_TAG_REST_BASE;
		}

		return array_values( array_unique( $rest_bases ) );
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
				$excerpt = $this->clean_rendered_text( $item['_embedded']['self'][0]['excerpt']['rendered'] );
			} elseif ( ! empty( $item['_embedded']['self'][0]['content']['rendered'] ) ) {
				$excerpt = wp_trim_words(
					$this->clean_rendered_text( $item['_embedded']['self'][0]['content']['rendered'] ),
					self::EXCERPT_WORD_COUNT
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

	/**
	 * Normalises content endpoint items (e.g. /posts, /recipe) into result format.
	 *
	 * @param array  $items     Raw items from a post-type endpoint.
	 * @param string $site_name Human-readable site label.
	 * @param string $site_url  Base URL of the remote site.
	 * @return array[] Normalised result items.
	 */
	private function normalise_content_results( array $items, $site_name, $site_url ) {
		$results = array();

		foreach ( $items as $item ) {
			if ( empty( $item['link'] ) || empty( $item['title']['rendered'] ) ) {
				continue;
			}

			$title = sanitize_text_field(
				$this->clean_rendered_text( $item['title']['rendered'] )
			);

			if ( '' === $title ) {
				continue;
			}

			$excerpt = '';
			if ( ! empty( $item['excerpt']['rendered'] ) ) {
				$excerpt = $this->clean_rendered_text( $item['excerpt']['rendered'] );
			} elseif ( ! empty( $item['content']['rendered'] ) ) {
				$excerpt = wp_trim_words(
					$this->clean_rendered_text( $item['content']['rendered'] ),
					self::EXCERPT_WORD_COUNT
				);
			}

			$results[] = array(
				'title'     => $title,
				'url'       => esc_url_raw( $item['link'] ),
				'excerpt'   => $excerpt,
				'type'      => isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'post',
				'site_name' => $site_name,
				'site_url'  => $site_url,
			);
		}

		return $results;
	}

	/**
	 * Sanitizes rendered HTML from REST responses into plain text.
	 *
	 * @param string $value Rendered field value.
	 * @return string Decoded plain text.
	 */
	private function clean_rendered_text( $value ) {
		return html_entity_decode(
			wp_strip_all_tags( (string) $value ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
	}

	/**
	 * Merges result arrays while removing duplicates by URL.
	 *
	 * @param array[] $primary   Base result list.
	 * @param array[] $secondary Additional result list.
	 * @return array[] Deduplicated merged results.
	 */
	private function merge_unique_results( array $primary, array $secondary ) {
		$seen_urls = array();
		$merged    = array();

		foreach ( array_merge( $primary, $secondary ) as $item ) {
			if ( empty( $item['url'] ) ) {
				continue;
			}

			$url = (string) $item['url'];
			if ( isset( $seen_urls[ $url ] ) ) {
				continue;
			}

			$seen_urls[ $url ] = true;
			$merged[]          = $item;
		}

		return $merged;
	}
}
