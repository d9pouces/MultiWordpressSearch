<?php
/**
 * Admin settings page partial: the dynamic list of WordPress sites.
 *
 * Variables available in this scope:
 *   $sites (array) – current list of site configurations.
 *
 * @package MultiWordpressSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="mws-sites-list">
	<?php if ( ! empty( $sites ) ) : ?>
		<?php foreach ( $sites as $index => $site ) : ?>
		<div class="mws-site-row">
			<input
				type="url"
				name="<?php echo esc_attr( MWS_OPTION_SITES ); ?>[<?php echo absint( $index ); ?>][url]"
				value="<?php echo esc_url( $site['url'] ); ?>"
				placeholder="https://example.com"
				class="regular-text"
				required
			/>
			<input
				type="text"
				name="<?php echo esc_attr( MWS_OPTION_SITES ); ?>[<?php echo absint( $index ); ?>][name]"
				value="<?php echo esc_attr( $site['name'] ); ?>"
				placeholder="<?php esc_attr_e( 'Site label (optional)', 'multi-wordpress-search' ); ?>"
				class="regular-text"
			/>
			<button type="button" class="button mws-remove-site">
				<?php esc_html_e( 'Remove', 'multi-wordpress-search' ); ?>
			</button>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<button type="button" class="button" id="mws-add-site">
	<?php esc_html_e( 'Add site', 'multi-wordpress-search' ); ?>
</button>

<p class="description">
	<?php esc_html_e( 'Enter the base URL of each WordPress site (e.g. https://example.com). The plugin appends /wp-json/wp/v2/search to build the API endpoint.', 'multi-wordpress-search' ); ?>
</p>
