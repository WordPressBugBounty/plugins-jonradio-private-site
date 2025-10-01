<?php
/**
 * My Private Site by David Gewirtz, adopted from Jon ‘jonradio’ Pearkins
 *
 * Lab Notes: http://zatzlabs.com/lab-notes/
 * Plugin Page: https://zatzlabs.com/project/my-private-site/
 * Contact: http://zatzlabs.com/contact-us/
 *
 * Copyright (c) 2015-2025 by David Gewirtz
 */

function my_private_site_load_admin_dependencies() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$loaded   = true;
	$base_dir = __DIR__;

	if ( my_private_site_should_load_cmb2() ) {
		$cmb2_paths = array(
			$base_dir . '/library/cmb2/init.php',
			$base_dir . '/library/CMB2/init.php',
		);

		foreach ( $cmb2_paths as $cmb2_path ) {
			if ( file_exists( $cmb2_path ) ) {
				require_once $cmb2_path;
				break;
			}
		}
	}

	require_once $base_dir . '/util/utilities.php';
	require_once $base_dir . '/util/cmbhelpers.php';
	require_once $base_dir . '/util/licensing.php';
	require_once $base_dir . '/legacy/legacy.php';
}

/**
 * Decide whether the bundled CMB2 library should load for this request.
 *
 * CMB2 powers the admin settings UI, but it is expensive to bootstrap and
 * unnecessary for lightweight AJAX heartbeats. Skipping it here keeps CMB2
 * updatable while avoiding the 500ms pause on idle dashboards.
 *
 * @return bool
 */
function my_private_site_should_load_cmb2() {
	$doing_ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	if ( $doing_ajax ) {
		$action       = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$skip_actions = (array) apply_filters( 'my_private_site_skip_cmb2_ajax_actions', array( 'heartbeat', 'wp-remove-post-lock' ) );
		if ( $action && in_array( $action, $skip_actions, true ) ) {
			return false;
		}
	}

	return true;
}

function my_private_site_admin_loader() {
	my_private_site_load_admin_dependencies();

	// bring in telemetry
	require_once __DIR__ . '/telemetry/deactivate.php';

	// bring in the admin page tabs
	require_once __DIR__ . '/admin/main.php';
	require_once __DIR__ . '/admin/site-privacy.php';
	require_once __DIR__ . '/admin/landing-page.php';
	require_once __DIR__ . '/admin/public-pages.php';
	require_once __DIR__ . '/admin/selective-content.php';
	require_once __DIR__ . '/admin/membership.php';
	require_once __DIR__ . '/admin/addons.php';
	require_once __DIR__ . '/admin/licenses.php';
	require_once __DIR__ . '/admin/advanced.php';
}

// load and enqueue supporting resources

function my_private_site_queue_admin_stylesheet() {
	do_action( 'my_private_site_add_styles_first' );

    $style_url  = plugins_url( '/css/adminstyles.css', __FILE__ );
    $style_path = plugin_dir_path( __FILE__ ) . 'css/adminstyles.css';
    $style_ver  = file_exists( $style_path ) ? filemtime( $style_path ) : null;

    wp_register_style( 'my_private_site_admin_css', $style_url, array(), $style_ver );
	wp_enqueue_style( 'my_private_site_admin_css' );

	// remodal library used by telemetry
	wp_enqueue_script( 'remodal', plugins_url( '/library/remodal/remodal.min.js', __FILE__ ) );
	wp_enqueue_style( 'remodal', plugins_url( '/library/remodal/remodal.css', __FILE__ ) );
	wp_enqueue_style( 'remodal-default-theme', plugins_url( '/library/remodal/remodal-default-theme.css', __FILE__ ) );

	do_action( 'my_private_site_add_styles_after' );
}

add_action( 'admin_enqueue_scripts', 'my_private_site_queue_admin_stylesheet' );

function my_private_site_init() {
	// Initialize options to defaults as needed
	my_private_site_admin_loader();

	// check to see if user has been told where the My Private Site settings are
	$internal_settings = get_option( 'jr_ps_internal_settings' );
	if ( isset( $internal_settings['warning_privacy'] ) ) {
		unset( $internal_settings['warning_privacy'] );
		update_option( 'jr_ps_internal_settings', $internal_settings );
	}

	// check to see if first run time has been recorded
	$first_run = get_option( 'jr_ps_first_run_time' );
	if ( $first_run == false ) {
		update_option( 'jr_ps_first_run_time', time() );
	}
}
