<?php
/**
 * My Private Site by David Gewirtz, adopted from Jon ‘jonradio’ Pearkins
 *
 * Lab Notes: http://zatzlabs.com/lab-notes/
 * Plugin Page: https://zatzlabs.com/project/my-private-site/
 * Contact: http://zatzlabs.com/contact-us/
 *
 * Copyright (c) 2015-2020 by David Gewirtz
 */

// Exit if .php file accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'cmb2_admin_init', 'my_private_site_admin_addons_menu' );

// ADDONS - MENU ////
function my_private_site_admin_addons_menu() {
	$args = array(
		'id'           => 'my_private_site_tab_addons_page',
		'title'        => 'My Private Site - Add-ons',
		// page title
		'menu_title'   => 'Add-ons',
		// title on left sidebar
		'tab_title'    => 'Add-ons',
		// title displayed on the tab
		'object_types' => array( 'options-page' ),
		'option_key'   => 'my_private_site_tab_addons',
		'parent_slug'  => 'my_private_site_tab_main',
		'tab_group'    => 'my_private_site_tab_set',

	);

	// 'tab_group' property is supported in > 2.4.0.
	if ( version_compare( CMB2_VERSION, '2.4.0' ) ) {
		$args['display_cb'] = 'my_private_site_cmb2_options_display_with_tabs';
	}

	do_action( 'my_private_site_tab_addons_before', $args );

	$args          = apply_filters( 'my_private_site_tab_addons_menu', $args );
	$addon_options = new_cmb2_box( $args );

	// we don't need nonce verification here because all we're doing is checking to see
	// if we're on the page we expected to be on.
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == 'my_private_site_tab_addons' ) {
		my_private_site_admin_addons_section_data( $addon_options );
	}
	do_action( 'my_private_site_tab_addons_after', $addon_options );
}

// Remove primary Save button
// derived from https://github.com/CMB2/CMB2-Snippet-Library/blob/master/filters-and-actions/custom-css-for-specific-metabox.php
function my_private_site_delete_addons_button( $post_id, $cmb ) {
	?>
    <style type="text/css" media="screen">
        input#submit-cmb.button.button-primary {
            display : none;
        }
    </style>
	<?php
}

$object = 'options-page'; // post | term
$cmb_id = 'my_private_site_tab_addons_page';
add_action( "cmb2_after_{$object}_form_{$cmb_id}", 'my_private_site_delete_addons_button', 10, 2 );

// ADDONS - SECTION - DATA ////
function my_private_site_admin_addons_section_data( $section_options ) {
	$section_options = apply_filters( 'my_private_site_tab_addons_section_data', $section_options );

	$section_options->add_field(
		array(
			'name'          => 'Add-ons',
			'id'            => 'my_private_site_add-ons_area',
			'type'          => 'text',
			'savetxt'       => '',
			'render_row_cb' => 'my_private_site_render_addons_tab_html',
			// this builds static text as provided
		)
	);
	$section_options = apply_filters( 'my_private_site_tab_addons_section_data_options', $section_options );
}

function my_private_site_render_addons_tab_html( $field_args, $field ) {
	$html_folder   = dirname( dirname( __FILE__ ) ) . '/html/';
	$html_file     = $html_folder . 'admin-addons.html';

	// PHPCS Sniffer errored on this, but we're just getting the contents of a local file
	$html_readme = file_get_contents( $html_file );
    $html_readme = str_replace( '%CONTENT_URL%', content_url(), $html_readme);

	$allowed_html = array(
		'a'   => array(
			'href'  => array(),
			'title' => array(),
			'class' => array(),
		),
		'div' => array(
			'id'    => array(),
			'class' => array(),
		),
		'h3'  => array(),
		'h4'  => array(),
		'p'   => array(),
		'img' => array(
			'src' => array(),
		),
	);
	echo wp_kses( $html_readme, $allowed_html );
}

