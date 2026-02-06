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


// site_privacy - MENU ////
function my_private_site_admin_site_privacy_menu() {
	$args = array(
		'id'           => 'my_private_site_tab_site_privacy_page',
		'title'        => 'My Private Site - Site Privacy',
		// page title
		'menu_title'   => 'Site Privacy',
		// title on left sidebar
		'tab_title'    => 'Site Privacy',
		// title displayed on the tab
		'object_types' => array( 'options-page' ),
		'option_key'   => 'my_private_site_tab_site_privacy',
		'parent_slug'  => 'my_private_site_tab_main',
		'tab_group'    => 'my_private_site_tab_set',

	);

	// 'tab_group' property is supported in > 2.4.0.
	if ( version_compare( CMB2_VERSION, '2.4.0' ) ) {
		$args['display_cb'] = 'my_private_site_cmb_options_display_with_tabs';
	}

	do_action( 'my_private_site_tab_site_privacy_before', $args );

	// call on button hit for page save
	add_action( 'admin_post_my_private_site_tab_site_privacy', 'my_private_site_tab_site_privacy_process_buttons' );

	// clear previous error messages if coming from another page
	my_private_site_clear_cmb2_submit_button_messages( $args['option_key'] );

	$args          = apply_filters( 'my_private_site_tab_site_privacy_menu', $args );
	$addon_options = new_cmb2_box( $args );

	my_private_site_admin_site_privacy_section_data( $addon_options );
	my_private_site_admin_rest_api_section_data( $addon_options );
	my_private_site_admin_ai_intelligence_section_data( $addon_options );
	my_private_site_admin_visitor_intelligence_section_data( $addon_options );
	my_private_site_admin_guest_access_section_data( $addon_options );

	do_action( 'my_private_site_tab_site_privacy_after', $addon_options );
}

add_action( 'cmb2_admin_init', 'my_private_site_admin_site_privacy_menu' );
add_action( 'admin_enqueue_scripts', 'my_private_site_site_privacy_enqueue_tutorial_assets' );

add_action( 'admin_post_my_private_site_retest_robots', 'my_private_site_handle_retest_robots' );
add_action( 'admin_notices', 'my_private_site_show_recaptcha_login_notice' );

function my_private_site_handle_retest_robots() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'my-private-site' ), 403 );
	}

	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'my_private_site_retest_robots' ) ) {
		wp_die( esc_html__( 'Security violation detected [A012]. Access denied.', 'my-private-site' ), 403 );
	}

	if ( function_exists( 'my_private_site_refresh_robots_url_status_cache' ) ) {
		my_private_site_refresh_robots_url_status_cache();
	} else {
		my_private_site_robots_url_is_404( true );
	}

	$still_404       = function_exists( 'my_private_site_robots_url_is_404' ) ? my_private_site_robots_url_is_404() : false;
	$physical_robots = function_exists( 'my_private_site_physical_robots_exists' ) && my_private_site_physical_robots_exists();
	if ( function_exists( 'my_private_site_set_ai_defense_notice' ) ) {
		if ( $still_404 ) {
			my_private_site_set_ai_defense_notice( 'robots.txt is still returning 404. Ensure the server routes robots.txt to WordPress and try again.', 'warning' );
		} elseif ( $physical_robots ) {
			my_private_site_set_ai_defense_notice( 'robots.txt file exists in the site root. Related AI crawler defense options remain disabled.', 'warning' );
		} else {
			my_private_site_set_ai_defense_notice( 'robots.txt check passed. Related options can now be enabled.', 'success' );
		}
	}

	$redirect = wp_get_referer();
	if ( ! $redirect ) {
		$redirect = admin_url( 'admin.php?page=my_private_site_tab_site_privacy&subtab=ai-intelligence' );
	}

	wp_safe_redirect( $redirect );
	exit;
}

function my_private_site_show_recaptcha_login_notice() {
	if ( ! is_admin() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'my_private_site_tab_site_privacy' !== $page ) {
		return;
	}

	$internal_settings = get_option( 'jr_ps_internal_settings' );
	if ( ! is_array( $internal_settings ) || empty( $internal_settings['recaptcha_login_notice'] ) ) {
		return;
	}

	$message = (string) $internal_settings['recaptcha_login_notice'];
	$type    = isset( $internal_settings['recaptcha_login_notice_type'] )
		? (string) $internal_settings['recaptcha_login_notice_type']
		: 'error';
	$class = 'notice';
	switch ( $type ) {
		case 'warning':
			$class .= ' notice-warning';
			break;
		case 'success':
			$class .= ' notice-success';
			break;
		default:
			$class .= ' notice-error';
			break;
	}

	echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';

	unset( $internal_settings['recaptcha_login_notice'], $internal_settings['recaptcha_login_notice_type'] );
	update_option( 'jr_ps_internal_settings', $internal_settings );
}

function my_private_site_site_privacy_enqueue_tutorial_assets( $hook ) {
	// Only load on the Site Privacy tab of My Private Site
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'my_private_site_tab_site_privacy' !== $page ) {
		return;
	}

	$script_path = plugin_dir_path( __FILE__ ) . '../js/tutorial-accordion.js';
	$script_url  = plugins_url( 'js/tutorial-accordion.js', dirname( __FILE__ ) . '/../jonradio-private-site.php' );
	$script_ver  = file_exists( $script_path ) ? filemtime( $script_path ) : null;

	wp_enqueue_script( 'my-private-site-tutorial-accordion', $script_url, array(), $script_ver, true );
}

// Helper to render a unified status banner matching Visitor Intelligence styling
if ( ! function_exists( 'my_private_site_build_status_banner' ) ) {
    function my_private_site_build_status_banner( $ok, $ok_text, $alert_text ) {
        $icon   = $ok ? 'dashicons-yes' : 'dashicons-warning';
        $class  = $ok ? 'jrps-vi-banner is-ok' : 'jrps-vi-banner';
        $label  = $ok ? $ok_text : $alert_text;
        // Return HTML for the status banner
        return '<div class="' . esc_attr( $class ) . '">'
             . '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>'
             . '<span class="summary-title">' . esc_html( $label ) . '</span>'
             . '</div>';
    }
}

// site_privacy - SECTION - DATA ////
function my_private_site_admin_site_privacy_section_data( $section_options ) {
	$handler_function = 'my_private_site_admin_site_privacy_preload'; // setup the preload handler function

	$section_options = apply_filters( 'my_private_site_tab_site_privacy_section_data', $section_options );

    $settings = get_option( 'jr_ps_settings' );
    $is_private = ( isset( $settings['private_site'] ) && $settings['private_site'] == true );
    $privacy_status = my_private_site_build_status_banner( $is_private, 'SITE IS PRIVATE', 'SITE IS NOT PRIVATE' );
    $privacy_status = apply_filters( 'my_private_site_tab_site_privacy_status', $privacy_status );

	$section_desc  = '<i>Turn on or off the My Private Site security features.</i>';
	$section_desc .= $privacy_status;

	$section_options->add_field(
		array(
			'name'        => 'Make Site Private',
			'id'          => 'jr_ps_admin_site_privacy_title',
			'type'        => 'title',
			'after_field' => $section_desc,
			// Secondary tab controls: this starts the "Privacy" subtab section
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'privacy',
			'secondary_title'=> 'Privacy',
		)
	);

	$section_options->add_field(
		array(
			'name'  => 'Site Privacy',
			'id'    => 'jr_ps_admin_site_privacy_enable',
			'type'  => 'checkbox',
			'after' => 'Enable login privacy',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_site_privacy_enable', $handler_function );

	$section_options->add_field(
		array(
			'name'  => 'Admin Bar',
			'id'    => 'jr_ps_admin_hide_admin_bar_enable',
			'type'  => 'checkbox',
			'after' => 'Hide admin bar',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_hide_admin_bar_enable', $handler_function );

	$privacy_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'privacy_mode_tutorial' ) );
	$feature_desc  = 'Public Pages gives you choose the overall privacy mode of the site. You can set the site to ';
	$feature_desc .= 'private and then open some pages to the public. Or you can set the site to public and restrict ';
	$feature_desc .= 'access to just some specific pages.';
	$feature_url   = 'https://zatzlabs.com/project/my-private-site-public-pages/';
	$feature_desc  = my_private_site_get_feature_promo( $feature_desc, $feature_url, 'UPGRADE', ' ' );
	$feature_desc .= '<div class="jrps-promo-video">'
	              . '<div class="jrps-video-accordion jrps-accordion-closed" data-storage-key="jrps_privacy_promo_tutorial" id="jrps-privacy-promo-tutorial">'
	              . '<button type="button" class="jrps-accordion-toggle" aria-expanded="false" aria-controls="jrps-privacy-promo-tutorial-panel">'
	              . '<span class="jrps-accordion-title" id="jrps-privacy-promo-tutorial-heading">Tutorial video</span>'
	              . '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
	              . '</button>'
	              . '<div class="jrps-accordion-panel" id="jrps-privacy-promo-tutorial-panel" role="region" aria-labelledby="jrps-privacy-promo-tutorial-heading" hidden>'
	              . '<div class="jrps-video-frame">'
	              . '<iframe src="' . $privacy_tutorial_url . '" title="My Private Site Privacy Tutorial" '
	              . 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
	              . '</div>'
	              . '</div>'
	              . '</div>'
	              . '</div>';

	$section_options->add_field(
		array(
			'name'    => __( 'Site Privacy Mode' ),
			'id'      => 'jr_ps_admin_default_privacy_mode',
			'type'    => 'select',
			'default' => 'STANDARD',
			'options' => array( 'STANDARD' => 'Site Private, Some Pages Public' ),
			'desc'    => $feature_desc,
		)
	);

	$compatibility_mode = array(
		'STANDARD'  => 'Standard',
		'ELEMENTOR' => 'Theme Fix',
	);

	$compatibility_desc = "Switch this setting if My Private Site doesn't properly block access for your theme.";

	$section_options->add_field(
		array(
			'name'    => __( 'Compatibility Mode' ),
			'id'      => 'jr_ps_admin_advanced_compatibility_mode',
			'type'    => 'select',
			'default' => 'STANDARD',
			// the index key of the label array below
			'options' => $compatibility_mode,
			'desc'    => $compatibility_desc,
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_advanced_compatibility_mode', $handler_function );

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_site_privacy_save',
			'button_text'        => 'Save Privacy Status',
			'button_success_msg' => 'Privacy status saved.',
			'button_error_msg'   => '',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_site_privacy_section_data_options', $section_options );
}

// rest_api - SECTION - DATA ////
function my_private_site_admin_rest_api_section_data( $section_options ) {
	$handler_function = 'my_private_site_admin_site_privacy_preload'; // setup the preload handler function

	$section_options = apply_filters( 'my_private_site_tab_rest_api_section_data', $section_options );

    $settings = get_option( 'jr_ps_settings' );
    $api_private = ( isset( $settings['private_api'] ) && $settings['private_api'] == true );
    $privacy_status = my_private_site_build_status_banner( $api_private, 'REST API IS PRIVATE', 'REST API IS NOT PRIVATE' );
    $privacy_status = apply_filters( 'my_private_site_tab_rest_api_status', $privacy_status );

	$section_desc  = '<i>Turn on or off the My Private Site REST API security features.</i>';
	$section_desc .= $privacy_status;

	$section_options->add_field(
		array(
			'name'        => 'REST API Guardian',
			'id'          => 'jr_ps_admin_rest_api_title',
			'type'        => 'title',
			'after_field' => $section_desc,
			// Secondary tab controls: this starts the "Protection" subtab section
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'protection',
			'secondary_title'=> 'Protection',
		)
	);

	$feature_desc = '<br><br>REST API in WordPress is a powerful tool. Modifying its behavior can have significant impact ';
	$feature_desc .= 'on your site\'s functionality, especially if other plugins or themes rely on the default behavior of the API. ';

	$section_options->add_field(
		array(
			'name'  => 'API Security',
			'id'    => 'jr_ps_admin_api_security_enable',
			'type'  => 'checkbox',
			'after' => 'Block REST API access for logged-out users' . $feature_desc,
			//'desc' => $feature_desc,
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_api_security_enable', $handler_function );

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_rest_api_save',
			'button_text'        => 'Save REST API Option',
			'button_success_msg' => 'REST API Option saved.',
			'button_error_msg'   => '',
		)
	);

	$registration_spam_checks = array();
	if ( isset( $settings['registration_spam_guard_checks'] ) && is_array( $settings['registration_spam_guard_checks'] ) ) {
		$registration_spam_checks = array_filter( $settings['registration_spam_guard_checks'] );
	}
	$registration_spam_active = ! empty( $registration_spam_checks );
	$registration_spam_status = my_private_site_build_status_banner(
		$registration_spam_active,
		'REGISTRATION SPAM GUARD ACTIVE',
		'REGISTRATION SPAM GUARD DISABLED'
	);
	$registration_spam_desc  = '<i>Stop automated registration spam before it hits your user list.</i>';
	$registration_spam_desc .= $registration_spam_status;

	$section_options->add_field(
		array(
			'name'           => 'REGISTRATION SPAM GUARD',
			'id'             => 'jr_ps_admin_rest_api_registration_spam_guard_promo',
			'type'           => 'title',
			'after_field'    => $registration_spam_desc,
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'protection',
			'secondary_title'=> 'Protection',
		)
	);

	$section_options->add_field(
		array(
			'name'              => 'Registration Spam Guard',
			'id'                => 'jr_ps_admin_registration_spam_guard_checks',
			'type'              => 'multicheck',
			'select_all_button' => false,
			'options'           => array(
				'honeypot'            => 'Enable honeypot field on registration form',
				'gibberish_username'  => 'Block registrations with excessively long gibberish usernames',
				'excessive_dots'      => 'Block registrations with excessive dots in email address',
				'missing_mx'          => 'Block registrations when email domain lacks MX records',
				'stop_forum_spam'     => 'Check registrants against StopForumSpam database',
			),
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_registration_spam_guard_checks', $handler_function );

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_registration_spam_guard_save',
			'button_text'        => 'Save Spam Guard Options',
			'button_success_msg' => 'Spam guard options saved.',
			'button_error_msg'   => '',
		)
	);

	$recaptcha_login_enabled = ! empty( $settings['recaptcha_login_guard_enabled'] );
	$recaptcha_site_key      = isset( $settings['recaptcha_login_guard_site_key'] ) ? trim( $settings['recaptcha_login_guard_site_key'] ) : '';
	$recaptcha_secret_key    = isset( $settings['recaptcha_login_guard_secret_key'] ) ? trim( $settings['recaptcha_login_guard_secret_key'] ) : '';
	$recaptcha_has_keys      = ( $recaptcha_site_key !== '' && $recaptcha_secret_key !== '' );
	$recaptcha_login_active  = ( $recaptcha_login_enabled && $recaptcha_has_keys );
	$recaptcha_login_status = my_private_site_build_status_banner(
		$recaptcha_login_active,
		'RECAPTCHA LOGIN GUARD ACTIVE',
		'RECAPTCHA LOGIN GUARD DISABLED'
	);
	$recaptcha_login_desc  = '<i>Protect login forms with reCAPTCHA challenges.</i>';
	$recaptcha_login_desc .= $recaptcha_login_status;
	$recaptcha_login_desc .= '<div class="jrps-recaptcha-note">Learn about reCAPTCHA at '
		. '<a href="https://www.google.com/recaptcha/about/" target="_blank" rel="noopener noreferrer">Google reCAPTCHA</a> '
		. 'and set up keys at <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">reCAPTCHA Admin</a>. '
		. 'For reCAPTCHA type choose "Challenge (v2)" and "I\'m not a robot" checkbox.'
		. '</div>';

	$section_options->add_field(
		array(
			'name'           => 'reCAPTCHA LOGIN GUARD',
			'id'             => 'jr_ps_admin_rest_api_recaptcha_login_guard_promo',
			'type'           => 'title',
			'after_field'    => $recaptcha_login_desc,
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'protection',
			'secondary_title'=> 'Protection',
		)
	);

	$section_options->add_field(
		array(
			'name'  => 'reCAPTCHA Login Guard',
			'id'    => 'jr_ps_admin_recaptcha_login_guard_enable',
			'type'  => 'checkbox',
			'after' => 'Enable reCAPTCHA Login Guard',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_recaptcha_login_guard_enable', $handler_function );

	$section_options->add_field(
		array(
			'name' => 'Site Key',
			'id'   => 'jr_ps_admin_recaptcha_site_key',
			'type' => 'text',
			'desc' => 'Google reCAPTCHA site key.',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_recaptcha_site_key', $handler_function );

	$section_options->add_field(
		array(
			'name' => 'Secret Key',
			'id'   => 'jr_ps_admin_recaptcha_secret_key',
			'type' => 'text',
			'desc' => 'Google reCAPTCHA secret key.',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_recaptcha_secret_key', $handler_function );

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_recaptcha_login_save',
			'button_text'        => 'Save reCAPTCHA Settings',
			'button_success_msg' => 'reCAPTCHA settings saved.',
			'button_error_msg'   => '',
		)
	);

	$block_ip_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'block_ip_protection_tutorial' ) );
	$block_ip_desc  = '<i>Block all matching IP addresses.</i><br>';
	$block_ip_desc .= my_private_site_get_feature_promo(
		'Block unwanted visitors by IP address or range with full IPv4/IPv6 support, configurable scope, and fast enforcement to secure your WordPress site.',
		'https://zatzlabs.com/project/my-private-site-plugins-and-extensions/',
		'UPGRADE',
		' '
	);
	$block_ip_desc .= '<div class="jrps-promo-video">'
	               . '<div class="jrps-video-accordion jrps-accordion-open" data-storage-key="jrps_block_ip_tutorial" id="jrps-block-ip-tutorial">'
	               . '<button type="button" class="jrps-accordion-toggle" aria-expanded="true" aria-controls="jrps-block-ip-tutorial-panel">'
	               . '<span class="jrps-accordion-title" id="jrps-block-ip-tutorial-heading">Tutorial video</span>'
	               . '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
	               . '</button>'
	               . '<div class="jrps-accordion-panel" id="jrps-block-ip-tutorial-panel" role="region" aria-labelledby="jrps-block-ip-tutorial-heading">'
	               . '<div class="jrps-video-frame">'
	               . '<iframe src="' . $block_ip_tutorial_url . '" title="My Private Site Block IP Tutorial" '
	               . 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
	               . '</div>'
	               . '</div>'
	               . '</div>'
	               . '</div>';

	$section_options->add_field(
		array(
			'name'           => 'BLOCK IP ADDRESS',
			'id'             => 'jr_ps_admin_rest_api_block_ip_promo',
			'type'           => 'title',
			'after_field'    => $block_ip_desc,
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'protection',
			'secondary_title'=> 'Protection',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_rest_api_section_data_options', $section_options );
}

// ai_intelligence - SECTION - DATA ////
function my_private_site_admin_ai_intelligence_section_data( $section_options ) {
    $handler_function = 'my_private_site_admin_site_privacy_preload'; // reuse standard preload handler

    $aad_active = ( function_exists( 'my_private_site_aad_is_compatible' ) && my_private_site_aad_is_compatible() );

    $settings = get_option( 'jr_ps_settings' );
    $robots_allowed = ( ! function_exists( 'my_private_site_physical_robots_exists' ) || ! my_private_site_physical_robots_exists() )
                      && ( ! function_exists( 'my_private_site_robots_url_is_404' ) || ! my_private_site_robots_url_is_404() );
    $active_rsl     = ! empty( $settings['ai_defense_rsl_block'] ) && $robots_allowed;
    $active_gptbot  = ! empty( $settings['ai_defense_gptbot_block'] ) && $robots_allowed;
    $active_noai    = ! empty( $settings['ai_defense_noai'] );
    $enabled_count  = ( $active_rsl ? 1 : 0 ) + ( $active_gptbot ? 1 : 0 ) + ( $active_noai ? 1 : 0 );
    $ai_active      = ( $enabled_count > 0 );

    // When AAD is not active, enrich the green banner with a count suffix.
    $ok_label = 'AI CRAWLER DEFENSE ACTIVE';
    if ( ! function_exists( 'my_private_site_is_aad_active' ) || ! my_private_site_is_aad_active() ) {
        if ( $ai_active ) {
            $ok_label .= ' (' . $enabled_count . ' DEFENSE' . ( $enabled_count === 1 ? '' : 'S' ) . ' DEPLOYED)';
        }
    }
    $ai_status = my_private_site_build_status_banner( $ai_active, $ok_label, 'AI CRAWLER DEFENSE DISABLED' );

    // Subhead + status, matching REST API Guardian style
    $ai_section_desc  = '<i>Turn on or off the My Private Site AI crawler defense features.</i>';
    $ai_section_desc .= $ai_status;
    // Do not show robots warnings here; they will appear below the related checkboxes for clarity

    // Header row which creates the subtab and shows status
    $section_options->add_field(
        array(
            'name'           => 'AI Crawler Defense',
            'id'             => 'jr_ps_admin_ai_defense_title',
            'type'           => 'title',
            'after_field'    => $ai_section_desc,
            // Secondary tab controls: this starts the "AI Defense" subtab section
            'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
            'secondary_tab'  => 'ai-intelligence',
            'secondary_title'=> 'AI Crawler Defense',
        )
    );

    if ( $aad_active ) {
        // Core controls hidden when Advanced AI Defense is active.
    } else {
        // Toggle to enable AI bot blocking via Really Simple Licensing
        $ai_checkbox_attributes = array();
        $robots_block_physical  = ( function_exists( 'my_private_site_physical_robots_exists' ) && my_private_site_physical_robots_exists() );
        $robots_block_404       = ( function_exists( 'my_private_site_robots_url_is_404' ) && my_private_site_robots_url_is_404() );
        $robots_blocked         = ( $robots_block_physical || $robots_block_404 );
        if ( $robots_blocked ) {
            // Disable the checkbox in UI when robots controls are blocked
            $ai_checkbox_attributes['disabled'] = 'disabled';
        }
        // Inline warning messages for robots.txt issues (shown under each checkbox)
        $robots_inline_msg = '';
        if ( $robots_block_physical ) {
            $robots_inline_msg .= '<div class="jrps-ai-warning jrps-ai-warning-inline">Option disabled because physical robots.txt file exists. Option can\'t be enabled while that file exists in this website\'s root directory.</div>'
                                . my_private_site_get_robots_retest_button_html( 'site-privacy-physical' );
        }
        if ( $robots_block_404 ) {
            $robots_inline_msg .= '<div class="jrps-ai-warning jrps-ai-warning-inline">Option disabled because the robots.txt URL returns 404. Usually this is because NGINX has not been configured to route robots.txt to WordPress. <a href="https://medium.com/%40oktay.acikalin/wordpress-nginx-virtual-robots-txt-and-404-bd5cc082725d" target="_blank" rel="noopener noreferrer">This article</a> has some details on how to fix.</div>'
                                . my_private_site_get_robots_retest_button_html( 'site-privacy-inline' );
        }

        $section_options->add_field(
            array(
                'name'       => 'Block Using RSL',
                'id'         => 'jr_ps_admin_ai_defense_enable',
                'type'       => 'checkbox',
                'after'      => 'Block AI crawler access using Really Simple Licensing (Prohibit AI training)' . $robots_inline_msg,
                'attributes' => $ai_checkbox_attributes,
            )
        );
        my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_ai_defense_enable', $handler_function );

        // (Removed separate raw warning row – warnings are injected inline under each checkbox.)

        // Checkbox to block GPTBot via robots.txt (same disable behavior as RSL)
        $section_options->add_field(
            array(
                'name'       => 'Block GPTBot',
                'id'         => 'jr_ps_admin_ai_gptbot_enable',
                'type'       => 'checkbox',
                'after'      => 'Block GPTBot access using robots.txt' . $robots_inline_msg,
                'attributes' => $ai_checkbox_attributes,
            )
        );
        my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_ai_gptbot_enable', $handler_function );

        // Checkbox to enable NoAI/NoImageAI meta + headers (independent of robots.txt status)
        $section_options->add_field(
            array(
                'name'  => 'NoAI / NoImageAI',
                'id'    => 'jr_ps_admin_ai_noai_enable',
                'type'  => 'checkbox',
                'after' => 'Block AI crawler access using NoAI and NoImageAI tags in meta tag and X-Robots-Tag headers',
            )
        );
        my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_ai_noai_enable', $handler_function );

        // Save button for all AI Defense options (always enabled; saves available options)
        $ai_button_options = array(
            'button_id'          => 'jr_ps_button_ai_defense_save',
            'button_text'        => 'Save AI Crawler Defense Options',
            'button_success_msg' => 'AI crawler defense options saved.',
            'button_error_msg'   => '',
        );
	    my_private_site_display_cmb2_submit_button( $section_options, $ai_button_options );
    }

	$advanced_ai_desc  = '<i>Comprehensive AI crawler protection with layered defense countermeasures.</i><br>';
	$advanced_ai_desc .= my_private_site_get_feature_promo(
		'Protect WordPress content from AI crawlers using licensing, opt-out tags, selective bot blocking, and firewall defenses to control and safeguard your data.',
		'https://zatzlabs.com/project/my-private-site-plugins-and-extensions/',
		'UPGRADE',
		' '
	);
	$advanced_ai_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'ai_defense_overview_tutorial' ) );

	$section_options->add_field(
		array(
			'name'           => 'ADVANCED AI CRAWLER DEFENSE',
			'id'             => 'jr_ps_admin_ai_defense_promo',
			'type'           => 'title',
			'after_field'    => $advanced_ai_desc
				. '<div class="jrps-promo-video">'
				. '<div class="jrps-video-accordion jrps-accordion-open" data-storage-key="jrps_ai_defense_tutorial" id="jrps-ai-defense-tutorial">'
				. '<button type="button" class="jrps-accordion-toggle" aria-expanded="true" aria-controls="jrps-ai-defense-tutorial-panel">'
				. '<span class="jrps-accordion-title" id="jrps-ai-defense-tutorial-heading">Tutorial video</span>'
				. '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
				. '</button>'
				. '<div class="jrps-accordion-panel" id="jrps-ai-defense-tutorial-panel" role="region" aria-labelledby="jrps-ai-defense-tutorial-heading">'
				. '<div class="jrps-video-frame">'
				. '<iframe src="' . $advanced_ai_tutorial_url . '" title="My Private Site Advanced AI Crawler Defense Tutorial" '
				. 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
				. '</div>'
				. '</div>'
				. '</div>'
				. '</div>',
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'ai-intelligence',
			'secondary_title'=> 'AI Crawler Defense',
		)
	);
}

// visitor_intelligence - SECTION - DATA ////
function my_private_site_admin_visitor_intelligence_section_data( $section_options ) {
	$visitor_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'visitor_intelligence_overview_tutorial' ) );
	$visitor_desc  = '<i>Analyze user and bot site activity.</i><br>';
	$visitor_desc .= my_private_site_get_feature_promo(
		'Track logins, logouts, failed attempts, and bot activity with a unified log, anomaly detection, and export tools for stronger site oversight and security.',
		'https://zatzlabs.com/project/my-private-site-plugins-and-extensions/',
		'UPGRADE',
		' '
	);

	$section_options->add_field(
		array(
			'name'           => 'VISITOR INTELLIGENCE',
			'id'             => 'jr_ps_admin_visitor_intelligence_promo',
			'type'           => 'title',
			'after_field'    => $visitor_desc
				. '<div class="jrps-promo-video">'
				. '<div class="jrps-video-accordion jrps-accordion-open" data-storage-key="jrps_visitor_intelligence_tutorial" id="jrps-visitor-intelligence-tutorial">'
				. '<button type="button" class="jrps-accordion-toggle" aria-expanded="true" aria-controls="jrps-visitor-intelligence-tutorial-panel">'
				. '<span class="jrps-accordion-title" id="jrps-visitor-intelligence-tutorial-heading">Tutorial video</span>'
				. '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
				. '</button>'
				. '<div class="jrps-accordion-panel" id="jrps-visitor-intelligence-tutorial-panel" role="region" aria-labelledby="jrps-visitor-intelligence-tutorial-heading">'
				. '<div class="jrps-video-frame">'
				. '<iframe src="' . $visitor_tutorial_url . '" title="My Private Site Visitor Intelligence Tutorial" '
				. 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
				. '</div>'
				. '</div>'
				. '</div>'
				. '</div>',
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'visitor-intelligence',
			'secondary_title'=> 'Visitor Intelligence',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_visitor_intelligence_section_data_options', $section_options );
}

// guest_access - SECTION - DATA ////
function my_private_site_admin_guest_access_section_data( $section_options ) {
	$guest_tutorial_url = esc_url( my_private_site_get_tutorial_video_url( 'guest_access_overview_tutorial' ) );
	$guest_desc  = '<i>Provide guests with unique access URLs.</i><br>';
	$guest_desc .= my_private_site_get_feature_promo(
		'Grant temporary, secure access to private WordPress content using unique shareable links with expiration, one-time use, and full admin-controlled invite management..',
		'https://zatzlabs.com/project/my-private-site-plugins-and-extensions/',
		'UPGRADE',
		' '
	);

	$section_options->add_field(
		array(
			'name'           => 'GUEST ACCESS',
			'id'             => 'jr_ps_admin_guest_access_promo',
			'type'           => 'title',
			'after_field'    => $guest_desc
				. '<div class="jrps-promo-video">'
				. '<div class="jrps-video-accordion jrps-accordion-open" data-storage-key="jrps_guest_access_tutorial" id="jrps-guest-access-tutorial">'
				. '<button type="button" class="jrps-accordion-toggle" aria-expanded="true" aria-controls="jrps-guest-access-tutorial-panel">'
				. '<span class="jrps-accordion-title" id="jrps-guest-access-tutorial-heading">Tutorial video</span>'
				. '<span class="jrps-accordion-icon" aria-hidden="true"></span>'
				. '</button>'
				. '<div class="jrps-accordion-panel" id="jrps-guest-access-tutorial-panel" role="region" aria-labelledby="jrps-guest-access-tutorial-heading">'
				. '<div class="jrps-video-frame">'
				. '<iframe src="' . $guest_tutorial_url . '" title="My Private Site Guest Access Tutorial" '
				. 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
				. '</div>'
				. '</div>'
				. '</div>'
				. '</div>',
			'secondary_cb'   => 'my_private_site_tab_site_privacy_page',
			'secondary_tab'  => 'guest-access',
			'secondary_title'=> 'Guest Access',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_guest_access_section_data_options', $section_options );
}

// site_privacy - PROCESS FORM SUBMISSIONS
function my_private_site_tab_site_privacy_process_buttons() {
        // Process Save changes button
        // This is a callback that has to be passed the full array for consideration
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! current_user_can( 'manage_options' ) ) {
                return;
        }
        $_POST    = apply_filters( 'validate_page_slug_my_private_site_tab_site_privacy', $_POST );
        $settings = get_option( 'jr_ps_settings' );

	if ( isset( $_POST['jr_ps_button_site_privacy_save'], $_POST['jr_ps_button_site_privacy_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_site_privacy_save_nonce'], 'jr_ps_button_site_privacy_save' ) ) {
			wp_die( 'Security violation detected [A002]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}
		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_site_privacy_enable'] ) ) {
			$settings['private_site'] = true;
		} else {
			$settings['private_site'] = false;
		}
		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_advanced_compatibility_mode'] ) ) {
			$compatibility_mode             = trim( sanitize_text_field( $_POST['jr_ps_admin_advanced_compatibility_mode']) );
			$settings['compatibility_mode'] = $compatibility_mode;
		}
		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_hide_admin_bar_enable'] ) ) {
			$settings['hide_admin_bar'] = true;
		} else {
			$settings['hide_admin_bar'] = false;
		}
		$result = update_option( 'jr_ps_settings', $settings );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_site_privacy_save' );
	}
	if ( isset( $_POST['jr_ps_button_ai_defense_save'], $_POST['jr_ps_button_ai_defense_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_ai_defense_save_nonce'], 'jr_ps_button_ai_defense_save' ) ) {
			wp_die( 'Security violation detected [A004]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}

		$robots_physical = ( function_exists( 'my_private_site_physical_robots_exists' ) && my_private_site_physical_robots_exists() );
		$robots_404      = ( function_exists( 'my_private_site_robots_url_is_404' ) && my_private_site_robots_url_is_404( true ) );
		$robots_blocked  = ( $robots_physical || $robots_404 );

		// phpcs:ignore WordPress.Security.NonceVerification
		$requested_enable = isset( $_POST['jr_ps_admin_ai_defense_enable'] );
		if ( $requested_enable ) {
			if ( $robots_blocked ) {
				// Cannot enable: robots controls are blocked. Force disable.
				$settings['ai_defense_rsl_block'] = false;
			} else {
				$settings['ai_defense_rsl_block'] = true;
				// Purge page caches so new robots.txt/license.xml are visible immediately.
				if ( function_exists( 'my_private_site_purge_page_caches' ) ) {
					my_private_site_purge_page_caches();
					// Cache purge handled here to reflect robots.txt changes quickly.
				}
			}
		} else {
			$settings['ai_defense_rsl_block'] = false;
		}

		// Save NoAI/NoImageAI checkbox alongside RSL settings
		// phpcs:ignore WordPress.Security.NonceVerification
		$settings['ai_defense_noai'] = isset( $_POST['jr_ps_admin_ai_noai_enable'] );

		// Save GPTBot robots block. If robots controls are blocked, force false.
		// phpcs:ignore WordPress.Security.NonceVerification
		$settings['ai_defense_gptbot_block'] = isset( $_POST['jr_ps_admin_ai_gptbot_enable'] ) && ! $robots_blocked;


			$result = update_option( 'jr_ps_settings', $settings );
			// Ensure rewrite rules are updated so /license.xml routes into WordPress.
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules( false );
			}
				// Generic admin notice
				my_private_site_set_ai_defense_notice( 'AI crawler defense options saved.', 'success' );
				my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_ai_defense_save' );
		}
	if ( isset( $_POST['jr_ps_button_rest_api_save'], $_POST['jr_ps_button_rest_api_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_rest_api_save_nonce'], 'jr_ps_button_rest_api_save' ) ) {
			wp_die( 'Security violation detected [A003]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}
		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_api_security_enable'] ) ) {
			$settings['private_api'] = true;
		} else {
			$settings['private_api'] = false;
		}

		$result = update_option( 'jr_ps_settings', $settings );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_rest_api_save' );
	}
	if ( isset( $_POST['jr_ps_button_registration_spam_guard_save'], $_POST['jr_ps_button_registration_spam_guard_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_registration_spam_guard_save_nonce'], 'jr_ps_button_registration_spam_guard_save' ) ) {
			wp_die( 'Security violation detected [A013]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}

		$allowed_checks = array(
			'honeypot'           => true,
			'gibberish_username' => true,
			'excessive_dots'     => true,
			'missing_mx'         => true,
			'stop_forum_spam'    => true,
		);
		$spam_guard_checks = array();
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_registration_spam_guard_checks'] ) && is_array( $_POST['jr_ps_admin_registration_spam_guard_checks'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			foreach ( $_POST['jr_ps_admin_registration_spam_guard_checks'] as $check ) {
				$check = sanitize_key( wp_unslash( $check ) );
				if ( '' !== $check && isset( $allowed_checks[ $check ] ) ) {
					$spam_guard_checks[] = $check;
				}
			}
		}

		$settings['registration_spam_guard_checks'] = array_values( array_unique( $spam_guard_checks ) );

		$result = update_option( 'jr_ps_settings', $settings );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_registration_spam_guard_save' );
	}
	if ( isset( $_POST['jr_ps_button_recaptcha_login_save'], $_POST['jr_ps_button_recaptcha_login_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_recaptcha_login_save_nonce'], 'jr_ps_button_recaptcha_login_save' ) ) {
			wp_die( 'Security violation detected [A014]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$recaptcha_enabled = isset( $_POST['jr_ps_admin_recaptcha_login_guard_enable'] );
		// phpcs:ignore WordPress.Security.NonceVerification
		$recaptcha_site_key = isset( $_POST['jr_ps_admin_recaptcha_site_key'] )
			? trim( sanitize_text_field( wp_unslash( $_POST['jr_ps_admin_recaptcha_site_key'] ) ) )
			: '';
		// phpcs:ignore WordPress.Security.NonceVerification
		$recaptcha_secret_key = isset( $_POST['jr_ps_admin_recaptcha_secret_key'] )
			? trim( sanitize_text_field( wp_unslash( $_POST['jr_ps_admin_recaptcha_secret_key'] ) ) )
			: '';

		if ( $recaptcha_enabled && ( $recaptcha_site_key === '' || $recaptcha_secret_key === '' ) ) {
			$missing = array();
			if ( $recaptcha_site_key === '' ) {
				$missing[] = 'Site Key';
			}
			if ( $recaptcha_secret_key === '' ) {
				$missing[] = 'Secret Key';
			}
			$missing_label = implode( ' and ', $missing );
			my_private_site_flag_cmb2_submit_button_error(
				'jr_ps_button_recaptcha_login_save',
				'Please provide the ' . $missing_label . ' to enable reCAPTCHA Login Guard.'
			);
			$internal_settings = get_option( 'jr_ps_internal_settings' );
			if ( ! is_array( $internal_settings ) ) {
				$internal_settings = array();
			}
			$internal_settings['recaptcha_login_notice']      = 'Please provide the ' . $missing_label . ' to enable reCAPTCHA Login Guard.';
			$internal_settings['recaptcha_login_notice_type'] = 'error';
			update_option( 'jr_ps_internal_settings', $internal_settings );

			return;
		}

		$settings['recaptcha_login_guard_enabled']    = $recaptcha_enabled;
		$settings['recaptcha_login_guard_site_key']   = $recaptcha_site_key;
		$settings['recaptcha_login_guard_secret_key'] = $recaptcha_secret_key;

		$result = update_option( 'jr_ps_settings', $settings );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_recaptcha_login_save' );
	}

}

function my_private_site_admin_site_privacy_preload( $data, $object_id, $args, $field ) {
	// find out what field we're getting
	$field_id = $args['field_id'];

	// get stored data from plugin
	$internal_settings = get_option( 'jr_ps_internal_settings' );
	$settings          = get_option( 'jr_ps_settings' );

	// Pull from existing My Private Site data formats
	switch ( $field_id ) {
		case 'jr_ps_admin_site_privacy_enable':
			if ( isset( $settings['private_site'] ) ) {
				return $settings['private_site'];
			} else {
				return false;
			}
			break;
		case 'jr_ps_admin_advanced_compatibility_mode':
			if ( isset( $settings['compatibility_mode'] ) ) {
				return $settings['compatibility_mode'];
			} else {
				return 'STANDARD';
			}
			break;
		case 'jr_ps_admin_api_security_enable':
			if ( isset( $settings['private_api'] ) ) {
				return $settings['private_api'];
			} else {
				return false;
			}
			break;
		case 'jr_ps_admin_registration_spam_guard_checks':
			if ( isset( $settings['registration_spam_guard_checks'] ) && is_array( $settings['registration_spam_guard_checks'] ) ) {
				return $settings['registration_spam_guard_checks'];
			}
			return array();
			break;
		case 'jr_ps_admin_recaptcha_login_guard_enable':
			if ( isset( $settings['recaptcha_login_guard_enabled'] ) ) {
				return (bool) $settings['recaptcha_login_guard_enabled'];
			}
			return false;
			break;
		case 'jr_ps_admin_recaptcha_site_key':
			if ( isset( $settings['recaptcha_login_guard_site_key'] ) ) {
				return $settings['recaptcha_login_guard_site_key'];
			}
			return '';
			break;
		case 'jr_ps_admin_recaptcha_secret_key':
			if ( isset( $settings['recaptcha_login_guard_secret_key'] ) ) {
				return $settings['recaptcha_login_guard_secret_key'];
			}
			return '';
			break;
		case 'jr_ps_admin_hide_admin_bar_enable':
			if ( isset( $settings['hide_admin_bar'] ) ) {
				return $settings['hide_admin_bar'];
			} else {
				return false;
			}
			break;
        case 'jr_ps_admin_ai_defense_enable':
            if ( ( function_exists( 'my_private_site_physical_robots_exists' ) && my_private_site_physical_robots_exists() )
                 || ( function_exists( 'my_private_site_robots_url_is_404' ) && my_private_site_robots_url_is_404() ) ) {
                // Force unchecked when blocked by physical file or 404 robots URL
                return false;
            }
            if ( isset( $settings['ai_defense_rsl_block'] ) ) {
                return $settings['ai_defense_rsl_block'];
            } else {
                return false;
            }
            break;
        case 'jr_ps_admin_ai_noai_enable':
            if ( isset( $settings['ai_defense_noai'] ) ) {
                return (bool) $settings['ai_defense_noai'];
            }
            return false;
            break;
        case 'jr_ps_admin_ai_gptbot_enable':
            // Force unchecked when robots controls are blocked
            if ( ( function_exists( 'my_private_site_physical_robots_exists' ) && my_private_site_physical_robots_exists() )
                 || ( function_exists( 'my_private_site_robots_url_is_404' ) && my_private_site_robots_url_is_404() ) ) {
                return false;
            }
            if ( isset( $settings['ai_defense_gptbot_block'] ) ) {
                return (bool) $settings['ai_defense_gptbot_block'];
            }
            return false;
            break;
        
    }
}
