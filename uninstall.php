<?php
/**
 * Uninstall routine for World Inspire Video Gallery for Gumlet.
 *
 * Removes plugin settings from wp_options while preserving user Media Library attachments.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wivg_settings' );
