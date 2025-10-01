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

// MENU ////
function my_private_site_admin_selective_content_menu() {
	$args = array(
		'id'           => 'my_private_site_tab_selective_content_page',
		'title'        => 'My Private Site - Shortcodes',
		// page title
		'menu_title'   => 'Shortcodes',
		// title on left sidebar
		'tab_title'    => 'Shortcodes',
		// title displayed on the tab
		'object_types' => array( 'options-page' ),
		'option_key'   => 'my_private_site_tab_selective_content',
		'parent_slug'  => 'my_private_site_tab_main',
		'tab_group'    => 'my_private_site_tab_set',

	);

	// 'tab_group' property is supported in > 2.4.0.
	if ( version_compare( CMB2_VERSION, '2.4.0' ) ) {
		$args['display_cb'] = 'my_private_site_cmb_options_display_with_tabs';
	}

	do_action( 'my_private_site_tab_selective_content_before', $args );

	// call on button hit for page save
	add_action( 'admin_post_my_private_site_tab_selective_content', 'my_private_site_tab_selective_content_process_buttons' );

	// clear previous error messages if coming from another page
	my_private_site_clear_cmb2_submit_button_messages( $args['option_key'] );

	$args          = apply_filters( 'my_private_site_tab_selective_content_menu', $args );
	$addon_options = new_cmb2_box( $args );

	my_private_site_admin_selective_content_shortcodes_section_data( $addon_options );

	do_action( 'my_private_site_tab_selective_content_after', $addon_options );
}

add_action( 'cmb2_admin_init', 'my_private_site_admin_selective_content_menu' );
add_action( 'admin_enqueue_scripts', 'my_private_site_selective_content_enqueue_tutorial_assets' );

function my_private_site_selective_content_enqueue_tutorial_assets( $hook ) {
	// Only load on the Shortcodes tab of My Private Site
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'my_private_site_tab_selective_content' !== $page ) {
		return;
	}

	$script_path = plugin_dir_path( __FILE__ ) . '../js/tutorial-accordion.js';
	$script_url  = plugins_url( 'js/tutorial-accordion.js', dirname( __FILE__ ) . '/../jonradio-private-site.php' );
	$script_ver  = file_exists( $script_path ) ? filemtime( $script_path ) : null;

	wp_enqueue_script( 'my-private-site-tutorial-accordion', $script_url, array(), $script_ver, true );
}

function my_private_site_admin_selective_content_shortcodes_section_data( $section_options ) {
	$handler_function = 'my_private_site_admin_selective_content_preload'; // setup the preload handler function

	$section_options = apply_filters( 'my_private_site_tab_selective_content_section_data', $section_options );

	$section_desc = '<i>Hide areas of content based on user access conditions.</i><br>';

	// promo
	$feature_desc  = 'Selective Content allows you to hide, scramble, and truncate blocks of text based ';
	$feature_desc .= 'on login, editor, or admin status. Also allows you to selectively hide widgets ';
	$feature_desc .= 'or entire sidebars based on access conditions.';
	$feature_url   = 'https://zatzlabs.com/project/my-private-site-plugins-and-extensions/';
	$section_desc .= my_private_site_get_feature_promo( $feature_desc, $feature_url, 'UPGRADE', ' ' );

	$section_desc .= '<br><br><B >SYNTAX:</B> PRIVACY HIDE-IF<br>';
	$section_desc .= '<B>HIDE-IF PARAMETERS:</B> logged-in, logged-out <br>';
	$section_desc .= '<B>EXAMPLES:</B><br>';
	$section_desc .= '<div style="margin-top: 10px; background-color:darkslategrey; padding:8px">';
	$section_desc .= '<span style="color:#fdd79a">[privacy hide-if="logged-in"]</span>';
	$section_desc .= '<span style="color:white">This will be hidden if the user is logged in.</span>';
	$section_desc .= '<span style="color:#fdd79a">[/privacy]</span><br>';
	$section_desc .= '<span style="color:#fdd79a">[privacy hide-if="logged-out"]</span>';
	$section_desc .= '<span style="color:white">This will be hidden if the user is logged out.</span>';
	$section_desc .= '<span style="color:#fdd79a">[/privacy]</span><br>';
	$section_desc .= '</div>';
	$selective_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'selective_content_tutorial' ) );
	$section_desc .= '<div class="jrps-promo-video">'
	               . '<div class="jrps-video-accordion jrps-accordion-open" data-storage-key="jrps_selective_shortcodes_tutorial" id="jrps-selective-shortcodes-tutorial">'
	               . '<button type="button" class="jrps-accordion-toggle" aria-expanded="true" aria-controls="jrps-selective-shortcodes-tutorial-panel">'
	               . '<span class="jrps-accordion-title" id="jrps-selective-shortcodes-tutorial-heading">Tutorial video</span>'
	               . '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
	               . '</button>'
	               . '<div class="jrps-accordion-panel" id="jrps-selective-shortcodes-tutorial-panel" role="region" aria-labelledby="jrps-selective-shortcodes-tutorial-heading">'
	               . '<div class="jrps-video-frame">'
	               . '<iframe src="' . $selective_tutorial_url . '" title="My Private Site Selective Content Tutorial" '
	               . 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
	               . '</div>'
	               . '</div>'
	               . '</div>'
	               . '</div>';

	$section_options->add_field(
		array(
			'name'        => 'Manage Access With Shortcodes',
			'id'          => 'jr_ps_admin_selective_shortcodes_title',
			'type'        => 'title',
			'after_field' => $section_desc,
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_selective_content_section_data_options', $section_options );
}
