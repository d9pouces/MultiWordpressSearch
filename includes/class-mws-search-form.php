<?php
/**
 * Handles the custom search form rendering.
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWS_Search_Form
 *
 * Replaces the native WordPress search form (via the `get_search_form` filter)
 * with a custom form that submits queries to the Multi WordPress Search engine.
 */
class MWS_Search_Form {

	/**
	 * Renders the custom search form HTML.
	 *
	 * This method is wired to the `get_search_form` WordPress filter, so it
	 * receives (and replaces) the default search form markup whenever a theme
	 * calls `get_search_form()`.
	 *
	 * @param string $form      The default WordPress search form HTML (unused).
	 * @param array  $args      Arguments passed to get_search_form().
	 * @return string The custom search form HTML.
	 */
	public static function render_search_form( $form, $args = array() ) {
		$placeholder = isset( $args['placeholder'] )
			? esc_attr( $args['placeholder'] )
			: esc_attr( get_option( MWS_OPTION_PLACEHOLDER ) ?: __( 'Search across WordPress sites…', 'multi-wordpress-search' ) );

		$search_value = isset( $_GET['mws_query'] )
			? esc_attr( sanitize_text_field( wp_unslash( $_GET['mws_query'] ) ) )
			: '';

		ob_start();
		?>
		<form role="search" method="get" class="mws-search-form" id="mws-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="mws-search-input">
				<?php esc_html_e( 'Search', 'multi-wordpress-search' ); ?>
			</label>
			<div class="mws-search-form__inner">
				<input
					type="search"
					id="mws-search-input"
					class="mws-search-form__input"
					name="mws_query"
					placeholder="<?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>"
					value="<?php echo $search_value; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>"
					autocomplete="off"
					required
				/>
				<button type="submit" class="mws-search-form__submit">
					<svg class="mws-search-form__submit-icon" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
						<circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
						<line x1="15.5" y1="15.5" x2="22" y2="22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					</svg>
					<span class="screen-reader-text"><?php esc_html_e( 'Search', 'multi-wordpress-search' ); ?></span>
				</button>
			</div>
			<div id="mws-live-results" class="mws-live-results" aria-live="polite" hidden></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the full-page search results.
	 *
	 * Called from templates/search-results.php when a search query is present.
	 *
	 * @param array[] $results   Array of result items (from MWS_API_Client::search_all).
	 * @param string  $query     The search query string.
	 */
	public static function render_results( array $results, $query ) {
		include MWS_PLUGIN_DIR . 'templates/search-results.php';
	}
}

// Intercept regular page requests that carry the mws_query parameter.
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['mws_query'] ) ) {
			return;
		}

		$query = sanitize_text_field( wp_unslash( $_GET['mws_query'] ) );
		if ( '' === $query ) {
			return;
		}

		$sites   = get_option( MWS_OPTION_SITES, array() );
		$client  = new MWS_API_Client();
		$results = $client->search_all( $query, $sites );

		// Capture full page output so we can strip "site-content" from the theme's #content element.
		ob_start();
		MWS_Search_Form::render_results( $results, $query );
		$html = ob_get_clean();

		// Remove the "site-content" class from the tag that carries id="content", keeping "container".
		echo preg_replace_callback(
			'/<[^>]+\bid=["\']content["\'][^>]*>/',
			function ( $tag_match ) {
				return preg_replace_callback(
					'/\bclass=(["\'])([^"\']*)\1/',
					function ( $class_match ) {
						$quote   = $class_match[1];
						$classes = preg_split( '/\s+/', trim( $class_match[2] ) );
						$classes = array_values( array_filter( $classes, function ( $c ) { return 'site-content' !== $c; } ) );
						if ( empty( $classes ) ) {
							// Drop the class attribute entirely if no classes remain.
							return '';
						}
						return 'class=' . $quote . implode( ' ', $classes ) . $quote;
					},
					$tag_match[0]
				);
			},
			$html
		);
		exit;
	}
);
