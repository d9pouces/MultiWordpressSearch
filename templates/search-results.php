<?php
/**
 * Template: Full-page search results.
 *
 * Variables available in this scope (passed from MWS_Search_Form::render_results):
 *   $results (array)  – list of result items from MWS_API_Client::search_all().
 *   $query   (string) – the search term entered by the user.
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use the active theme's header and footer so the results page fits the site design.
get_header();
?>

<div class="mws-results-page">

	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput -- render_search_form returns escaped HTML.
	echo MWS_Search_Form::render_search_form( '', array() );
	?>

	<h2>
		<?php
		printf(
			/* translators: %s: search query */
			esc_html__( 'Search results for: %s', 'multi-wordpress-search' ),
			'<em>' . esc_html( $query ) . '</em>'
		);
		?>
	</h2>

	<?php if ( empty( $results ) ) : ?>
		<p><?php esc_html_e( 'No results found. Please try a different search term.', 'multi-wordpress-search' ); ?></p>
	<?php else : ?>
		<ul class="mws-results-list">
			<?php foreach ( $results as $item ) : ?>
			<li class="mws-result-item">
				<a href="<?php echo esc_url( $item['url'] ); ?>">
					<?php echo esc_html( $item['title'] ); ?>
				</a>

				<?php if ( ! empty( $item['excerpt'] ) ) : ?>
				<p class="mws-result-item__excerpt">
					<?php echo esc_html( $item['excerpt'] ); ?>
				</p>
				<?php endif; ?>

				<p class="mws-result-item__meta">
					<?php
					printf(
						/* translators: %s: site name as a link */
						__( 'Provenance : %s', 'multi-wordpress-search' ),
						'<a href="' . esc_url( $item['site_url'] ) . '" rel="noopener noreferrer" target="_blank">'
							. esc_html( $item['site_name'] )
						. '</a>'
					);
					?>
				</p>
			</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

</div><!-- .mws-results-page -->

<?php
get_footer();
