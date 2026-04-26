<?php
/**
 * Uninstall script for Multi WordPress Search.
 *
 * This file is executed by WordPress when the plugin is deleted via the
 * Plugins admin screen. It removes all data the plugin has stored in the
 * database so no orphaned options are left behind.
 *
 * @package MultiWordpressSearch
 */

// Guard: only run when WordPress itself triggers the uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mws_sites' );
