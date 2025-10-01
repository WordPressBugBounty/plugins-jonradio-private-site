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

function my_private_site_store_url() {
	return 'https://zatzlabs.com';
}


function my_private_site_get_license_key( $item ) {
	$license_key   = '';
	$license_array = maybe_unserialize( get_option( 'jr_ps_licenses' ) );
	if ( isset( $license_array[ $item ] ) ) {
		$license_key = $license_array[ $item ];
	}

	return $license_key;
}

function my_private_site_confirm_license_key( $key ) {
	if ( $key == '' ) {
		return false;
	}

	return true;
}

function my_private_site_edd_activate_license( $product, $license, $url ) {
	my_private_site_debug_log( '----------------------------------------' );
	my_private_site_debug_log( 'LICENSE ACTIVATION STARTED' );

	// retrieve the license from the database
	$license = trim( $license );
	my_private_site_debug_log( 'Product: ' . $product );
	my_private_site_debug_log( 'License key: ' . my_private_site_obscurify_string( $license ) );

	// Call the custom API.
	$response = wp_remote_get(
		add_query_arg(
			array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( $product ),
				// the name of our product in EDD
			),
			$url
		),
		array(
			'timeout'   => 15,
			'sslverify' => false,
		)
	);

	// make sure the response came back okay
	if ( is_wp_error( $response ) ) {
		my_private_site_debug_log( 'Response error detected: ' . $response->get_error_message() );

		return false;
	}

	// decode the license data
	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	// $license_data->license will be either "active" or "inactive" <-- "valid"
	if ( isset( $license_data->license ) && $license_data->license == 'active' || $license_data->license == 'valid' ) {
		my_private_site_debug_log( 'License check value: ' . $license_data->license );
		my_private_site_debug_log( 'License check returning valid.' );

		return 'valid';
	}

	my_private_site_debug_log( 'License check returning invalid.' );

	return 'invalid';
}

function my_private_site_edd_deactivate_license( $product, $license, $url ) {
	my_private_site_debug_log( '----------------------------------------' );
	my_private_site_debug_log( 'LICENSE DEACTIVATION STARTED' );

	// retrieve the license from the database

	$license = trim( $license );
	my_private_site_debug_log( 'Product: ' . $product );
	my_private_site_debug_log( 'License key: ' . my_private_site_obscurify_string( $license ) );

	// Call the custom API.
	$response = wp_remote_get(
		add_query_arg(
			array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => urlencode( $product ),
				// the name of our product in EDD
			),
			$url
		),
		array(
			'timeout'   => 15,
			'sslverify' => false,
		)
	);

	// make sure the response came back okay
	if ( is_wp_error( $response ) ) {
		my_private_site_debug_log( 'Response error detected: ' . $response->get_error_message() );

		return false;
	}

	// decode the license data
	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	// $license_data->license will be either "active" or "inactive" <-- "valid"
	if ( isset( $license_data->license ) && $license_data->license == 'deactivated' ) {
		my_private_site_debug_log( 'License check value: ' . $license_data->license );
		my_private_site_debug_log( 'License check returning deactivated.' );

		return 'deactivated';
	}

	my_private_site_debug_log( 'License check returning invalid.' );

	return 'invalid';
}

