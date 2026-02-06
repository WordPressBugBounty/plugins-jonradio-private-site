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

// advanced - MENU ////
function my_private_site_admin_advanced_menu() {
	$args = array(
		'id'           => 'my_private_site_tab_advanced_page',
		'title'        => 'My Private Site - Advanced',
		// page title
		'menu_title'   => 'Advanced',
		// title on left sidebar
		'tab_title'    => 'Advanced',
		// title displayed on the tab
		'object_types' => array( 'options-page' ),
		'option_key'   => 'my_private_site_tab_advanced',
		'parent_slug'  => 'my_private_site_tab_main',
		'tab_group'    => 'my_private_site_tab_set',

	);

	// 'tab_group' property is supported in > 2.4.0.
	if ( version_compare( CMB2_VERSION, '2.4.0' ) ) {
		$args['display_cb'] = 'my_private_site_cmb_options_display_with_tabs';
	}

	do_action( 'my_private_site_tab_advanced_before', $args );

	// call on button hit for page save
	add_action( 'admin_post_my_private_site_tab_advanced', 'my_private_site_tab_advanced_process_buttons' );

	// clear previous error messages if coming from another page
	my_private_site_clear_cmb2_submit_button_messages( $args['option_key'] );

	$args          = apply_filters( 'my_private_site_tab_advanced_menu', $args );
	$addon_options = new_cmb2_box( $args );

    my_private_site_admin_advanced_section_data( $addon_options );
    // Show Backups tab before System Log
    my_private_site_admin_backups_section_data( $addon_options );
    my_private_site_admin_logs_section_data( $addon_options );
    my_private_site_admin_spam_log_section_data( $addon_options );

	do_action( 'my_private_site_tab_advanced_after', $addon_options );
}

add_action( 'cmb2_admin_init', 'my_private_site_admin_advanced_menu' );

// advanced - SECTION - DATA ////
function my_private_site_admin_advanced_section_data( $section_options ) {
	$handler_function = 'my_private_site_admin_advanced_preload'; // setup the preload handler function
	$home_url         = trim( get_home_url(), '\ /' );
	$section_options  = apply_filters( 'my_private_site_tab_advanced_section_data', $section_options );

	$section_desc = '<i>Choose advanced custom login options.</i>';

	$section_options->add_field(
		array(
			'name'        => 'Advanced Options',
			'id'          => 'jr_ps_admin_advanced_title',
			'type'        => 'title',
            'secondary_cb'   => 'my_private_site_tab_advanced_page',
			'secondary_tab'  => 'advanced_options',
			'secondary_title'=> 'Advanced Options',
			'after_field' => $section_desc,
		)
	);

	$section_options->add_field(
		array(
			'name'  => 'Custom Login Page',
			'id'    => 'jr_ps_admin_advanced_enable_custom_login',
			'type'  => 'checkbox',
			'after' => 'Enable custom login page',
			// 'desc'  =>  'This is the same advanced option displayed on the General Settings admin panel' ),
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_advanced_enable_custom_login', $handler_function );

	$section_options->add_field(
		array(
			'name' => 'Custom Login URL',
			'id'   => 'jr_ps_admin_advanced_url',
			'type' => 'text',
			'desc' => 'Add custom login page URL. Must begin with ' . $home_url . '.',
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_advanced_url', $handler_function );

	if ( ! function_exists( 'my_private_site_pp_plugin_updater' ) ) {
		$section_options->add_field(
			array(
				'name' => 'Add Password Reset URL',
				'id'   => 'jr_ps_admin_advanced_password_reset_url',
				'type' => 'text',
				'desc' => 'Add public password reset page URL. Must begin with ' . $home_url . '.',
			)
		);
		my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_advanced_password_reset_url', $handler_function );
	}

	// $compatibility_mode = array(
	// 'STANDARD'  => 'Standard',
	// 'ELEMENTOR' => 'Elementor Fix',
	// );
	//
	// $compatibility_desc = "Adjust this setting if My Private Site doesn't properly block access.";
	//
	// $section_options->add_field(array(
	// 'name'    => __('Compatibility Mode'),
	// 'id'      => 'jr_ps_admin_advanced_compatibility_mode',
	// 'type'    => 'select',
	// 'default' => 'STANDARD',
	// the index key of the label array below
	// 'options' => $compatibility_mode,
	// 'desc'    => $compatibility_desc,
	// ));
	// my_private_site_preload_cmb2_field_filter('jr_ps_admin_advanced_compatibility_mode', $handler_function);

	// although this feature was in Jonradio's original code, there's nothing he does with it other than set it
	// $section_options->add_field(array(
	// 'name'  => 'Validate Login URL',
	// 'id'    => 'jr_ps_admin_advanced_validate_login_url',
	// 'type'  => 'checkbox',
	// 'after' => 'URL for your custom login page must begin with ' . $home_url .
	// '<br><span style="color:red">It is recommended you leave this option checked.',
	// 'desc'  =>  'This is the same advanced option displayed on the General Settings admin panel' ),
	// ));
	// my_private_site_preload_cmb2_field_filter('jr_ps_admin_advanced_validate_login_url', $handler_function);

	$section_options->add_field(
		array(
			'name'  => 'Custom Landing Location',
			'id'    => 'jr_ps_admin_advanced_custom_landing',
			'type'  => 'checkbox',
			'after' => 'Allow landing location for custom login pages. ' .
			           '<br><span style="color:red">This is dangerous. It could permanently lock you out of your site.<br>' .
			           '<h1 style="color:red">If you lock yourself out, I will not be able to help you get back in!</h1>',
			// 'desc'  =>  'This is the same advanced option displayed on the General Settings admin panel' ),
		)
	);
	my_private_site_preload_cmb2_field_filter( 'jr_ps_admin_advanced_custom_landing', $handler_function );

	$section_desc = <<<EOD
<p>These settings allow you to specify a custom login page that ignores the standard WordPress login at
    THIS-SITE/wp-login.php.</p>

<p>If the Custom Login page is not based on the standard WordPress Login page, it may not accept the
    ?redirect_to=http://landingurl query that is automatically added to the URL of the custom login page. If this causes
    difficultly, choose the Omit ?redirect_to= from URL option on the Landing Page tab.</p>

<p>Even with a custom login page configured, the standard WordPress login page will still appear in certain
    circumstances, such as logging into the Admin panels.</p>

<p>The Custom Landing Location advanced option may, under some circumstances, lock you out of your own WordPress site
    and prevent visitors from viewing your site. To recover, you will have to rename or delete the
    /wp-contents/plugins/jonradio-private-site/ folder with FTP or a file manager provided with your web hosting. If you
    are not familiar with either of these methods for deleting files within your WordPress installation, you risk making
    your WordPress site completely inoperative. In other words, don't check that button unless you know what you're
    doing and are prepared to recover your site.</p>
EOD;

	$section_options->add_field(
		array(
			'name'        => 'Tips for advanced login options',
			'id'          => 'my_private_site_admin_public_pages_tips',
			'type'        => 'title',
			'after_field' => $section_desc,
		)
	);

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_advanced_save',
			'button_text'        => 'Save Advanced Options',
			'button_success_msg' => 'Advanced options saved.',
			'button_error_msg'   => 'Please enter a valid URL',
		)
	);
	$section_options = apply_filters( 'my_private_site_tab_advanced_section_data_options', $section_options );
}

// LOGS - SECTION - DATA ////
function my_private_site_admin_logs_section_data( $section_options ) {
	$section_options->add_field(
		array(
			'name'    => __( 'Log Data', 'cmb2' ),
			'id'      => 'my_private_site_log_data',
			'type'    => 'title',
            'secondary_cb'   => 'my_private_site_tab_advanced_page',
			'secondary_tab'  => 'system_log',
			'secondary_title'=> 'System Log',
			'default' => 'log data',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_logs_section_data', $section_options );

	$debug_log_content = get_option( 'jr_ps_log' );
	$log_data          = '';

	if ( empty( $debug_log_content ) ) {
		$log_data = esc_html__( 'The log is empty.', 'my-private-site' );
	} else {
		foreach ( $debug_log_content as $debug_log_entry ) {
			if ( $log_data != '' ) {
				$log_data .= "\n";
			}
			$log_data .= esc_html( $debug_log_entry );
		}
	}

	$debug_mode = get_option( 'jr_ps_debug_mode' );
	if ( $debug_mode == 1 ) {
		// we're in debug, so we'll return lots of log info

		$display_options = array(
			'My Private Site Log Data' => $log_data,
			// Removes the default data by passing an empty value below.
			'Admin Page Framework'     => '',
			'Browser'                  => '',
		);
	} else {
		$display_options = array(
			'My Private Site Log Data' => $log_data,
			// Removes the default data by passing an empty value below.
			'Admin Page Framework'     => '',
			'WordPress'                => '',
			'PHP'                      => '',
			'Server'                   => '',
			'PHP Error Log'            => '',
			'MySQL'                    => '',
			'MySQL Error Log'          => '',
			'Browser'                  => '',
		);
	}

	$section_options->add_field(
		array(
			'name'    => 'System Information',
			'id'      => 'my_private_site_system_information',
			'type'    => 'textarea_code',
			'default' => $log_data,
		)
	);

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_settings_logs_delete',
			'button_text'        => 'Delete Log',
			'button_success_msg' => 'Log deleted.',
			'button_error_msg'   => '',
		)
	);

	$section_options = apply_filters( 'my_private_site_tab_logs_section_data_options', $section_options );
}

// SPAM LOG - SECTION - DATA ////
function my_private_site_admin_spam_log_section_data( $section_options ) {
	$section_options->add_field(
		array(
			'name'            => 'Spam Log',
			'id'              => 'my_private_site_spam_log_title',
			'type'            => 'title',
			'secondary_cb'    => 'my_private_site_tab_advanced_page',
			'secondary_tab'   => 'spam_log',
			'secondary_title' => 'Spam Log',
			'after_field'     => '<i>Review blocked registration attempts and their reasons.</i>',
		)
	);

	$log_entries   = get_option( 'jr_ps_spam_guard_log' );
	$spam_log_empty = ( ! is_array( $log_entries ) || empty( $log_entries ) );
	if ( $spam_log_empty ) {
		$log_html = '<p class="jrps-spam-log-empty">The spam log is empty.</p>';
	} else {
		$rows = '';
		foreach ( $log_entries as $entry ) {
			$rows .= '<tr>'
				. '<td>' . esc_html( isset( $entry['time'] ) ? $entry['time'] : '' ) . '</td>'
				. '<td>' . esc_html( isset( $entry['login'] ) ? $entry['login'] : '' ) . '</td>'
				. '<td>' . esc_html( isset( $entry['email'] ) ? $entry['email'] : '' ) . '</td>'
				. '<td>' . esc_html( isset( $entry['ip'] ) ? $entry['ip'] : '' ) . '</td>'
				. '<td>' . esc_html( isset( $entry['reason'] ) ? $entry['reason'] : '' ) . '</td>'
				. '</tr>';
		}
		$log_html = '<table class="widefat striped jrps-spam-log-table">'
			. '<thead><tr>'
			. '<th>Date</th>'
			. '<th>Username</th>'
			. '<th>Email</th>'
			. '<th>IP Address</th>'
			. '<th>Reason</th>'
			. '</tr></thead>'
			. '<tbody>' . $rows . '</tbody>'
			. '</table>';
	}

	my_private_site_cmb2_add_raw_html(
		$section_options,
		$log_html,
		'my_private_site_spam_log_table',
		array(
			'secondary_cb'  => 'my_private_site_tab_advanced_page',
			'secondary_tab' => 'spam_log',
			'classes'       => 'jrps-full-row',
		)
	);

	my_private_site_display_cmb2_submit_button(
		$section_options,
		array(
			'button_id'          => 'jr_ps_button_spam_log_clear',
			'button_text'        => 'Clear Spam Log',
			'button_success_msg' => 'Spam log cleared.',
			'button_error_msg'   => '',
			'attributes'         => $spam_log_empty ? array( 'disabled' => 'disabled' ) : array(),
			'field_args'         => array(
				'secondary_cb'  => 'my_private_site_tab_advanced_page',
				'secondary_tab' => 'spam_log',
			),
		)
	);
}

// BACKUPS - SECTION - DATA ////
function my_private_site_admin_backups_section_data( $section_options ) {
    // Backup block header (also declares/labels the Backups sub-tab)
    $section_options->add_field(
        array(
            'name'            => 'Backup Settings',
            'id'              => 'my_private_site_backups_backup_header',
            'type'            => 'title',
            'secondary_cb'    => 'my_private_site_tab_advanced_page',
            'secondary_tab'   => 'backups',
            'secondary_title' => 'Manage Settings',
            'after_field'     => '<i>Backup all your My Private Site settings.</i>',
        )
    );

    // Backup block description
    // (message placed in after_field of the title above)

    $backup_button  = '<div class="jrps-backup-actions">';
    $backup_button .= sprintf(
        '<button type="button" class="button button-primary jr-ps-backup-button" data-action="%s" data-nonce="%s">%s</button>',
        esc_url( admin_url( 'admin-post.php' ) ),
        esc_attr( wp_create_nonce( 'jr_ps_backup_settings' ) ),
        esc_html__( 'Backup Now', 'my-private-site' )
    );
    $backup_button .= '</div>';
    my_private_site_cmb2_add_raw_html(
        $section_options,
        $backup_button,
        'my_private_site_backups_backup_button',
        array( 'secondary_cb' => 'my_private_site_tab_advanced_page', 'secondary_tab' => 'backups', 'classes' => 'jrps-button-row' )
    );

    // Restore block header
    $section_options->add_field(
        array(
            'name'         => 'Restore Settings',
            'id'           => 'my_private_site_backups_restore_header',
            'type'         => 'title',
            'secondary_cb' => 'my_private_site_tab_advanced_page',
            'secondary_tab'=> 'backups',
            'after_field'  => '<i>This restores a current or earlier version of your saved settings.</i> <span style="color:red">Warning: this will overwrite all your existing settings and replace them with the contents of the restored file.</span>',
        )
    );

    // Restore block description
    // (message placed in after_field of the title above)

    $restore_controls  = '<div class="jrps-restore-actions">';
    $restore_controls .= '<input type="file" id="jr_ps_restore_file" name="jr_ps_restore_file" accept=".json,.txt" style="display:block;margin-bottom:12px;" />';
    $restore_controls .= sprintf(
        '<button type="button" class="button jr-ps-restore-button" data-action="%s" data-nonce="%s" data-confirm="%s" data-require="%s">%s</button>',
        esc_url( admin_url( 'admin-post.php' ) ),
        esc_attr( wp_create_nonce( 'jr_ps_restore_settings' ) ),
        esc_attr__( 'This will overwrite all your existing settings and replace them with the contents of the restored file. Continue?', 'my-private-site' ),
        esc_attr__( 'Please choose a backup file before restoring.', 'my-private-site' ),
        esc_html__( 'Restore Settings', 'my-private-site' )
    );
    $restore_controls .= '</div>';
    my_private_site_cmb2_add_raw_html(
        $section_options,
        $restore_controls,
        'my_private_site_backups_restore_controls',
        array( 'secondary_cb' => 'my_private_site_tab_advanced_page', 'secondary_tab' => 'backups', 'classes' => 'jrps-button-row' )
    );

    // Reset Settings section
    $section_options->add_field(
        array(
            'name'         => 'Reset Settings',
            'id'           => 'my_private_site_backups_reset_header',
            'type'         => 'title',
            'secondary_cb' => 'my_private_site_tab_advanced_page',
            'secondary_tab'=> 'backups',
            'after_field'  => '<i>Restore My Private Site to default configuration.</i> <span style="color:red">Warning: this will overwrite all your existing settings and replace them with defaults.</span>',
        )
    );

    $reset_controls  = '<div class="jrps-reset-actions">';
    $reset_controls .= sprintf(
        '<button type="button" class="button jr-ps-reset-button" data-action="%s" data-nonce="%s" data-confirm="%s">%s</button>',
        esc_url( admin_url( 'admin-post.php' ) ),
        esc_attr( wp_create_nonce( 'jr_ps_reset_settings' ) ),
        esc_attr__( 'This will reset all My Private Site settings to defaults. Continue?', 'my-private-site' ),
        esc_html__( 'Reset Settings', 'my-private-site' )
    );
    $reset_controls .= '</div>';
    my_private_site_cmb2_add_raw_html(
        $section_options,
        $reset_controls,
        'my_private_site_backups_reset_controls',
        array( 'secondary_cb' => 'my_private_site_tab_advanced_page', 'secondary_tab' => 'backups', 'classes' => 'jrps-button-row' )
    );

    if ( ! defined( 'JR_PS_BACKUP_SCRIPT_ADDED' ) ) {
        define( 'JR_PS_BACKUP_SCRIPT_ADDED', true );
        $script = '<style>.jr-ps-reset-button{background:#b32d2e!important;color:#fff!important;border-color:#b32d2e!important;} .jr-ps-reset-button:hover,.jr-ps-reset-button:focus,.jr-ps-reset-button:active{background:#922025!important;border-color:#922025!important;color:#fff!important;}</style>';
        $script .= '<script>(function(){function createHiddenInput(name,value){var input=document.createElement("input");input.type="hidden";input.name=name;input.value=value;return input;}function buildForm(action){var form=document.createElement("form");form.method="post";form.action=action;form.style.display="none";return form;}document.addEventListener("DOMContentLoaded",function(){var backupBtn=document.querySelector(".jr-ps-backup-button");if(backupBtn){backupBtn.addEventListener("click",function(ev){ev.preventDefault();var form=buildForm(backupBtn.dataset.action);form.appendChild(createHiddenInput("action","my_private_site_backup_settings"));form.appendChild(createHiddenInput("jr_ps_backup_settings_nonce",backupBtn.dataset.nonce));document.body.appendChild(form);form.submit();});}var restoreBtn=document.querySelector(".jr-ps-restore-button");var restoreInput=document.getElementById("jr_ps_restore_file");if(restoreInput){restoreInput.addEventListener("change",function(){if(restoreInput.files&&restoreInput.files.length){restoreBtn&&restoreBtn.classList.add("button-primary");}else{restoreBtn&&restoreBtn.classList.remove("button-primary");}});var resetState=' . ( isset( $_GET['jrps_restore_status'] ) ? 'true' : 'false' ) . ';if(resetState){try{restoreInput.value="";}catch(e){}}}
if(restoreBtn&&restoreInput){restoreBtn.addEventListener("click",function(ev){ev.preventDefault();if(!restoreInput.files||!restoreInput.files.length){var requireMsg=restoreBtn.dataset.require||' . wp_json_encode( __( 'Please choose a backup file before restoring.', 'my-private-site' ) ) . ';window.alert(requireMsg);restoreInput.focus();return;}if(restoreBtn.dataset.confirm&&!window.confirm(restoreBtn.dataset.confirm)){return;}var form=buildForm(restoreBtn.dataset.action);form.enctype="multipart/form-data";form.appendChild(createHiddenInput("action","my_private_site_restore_settings"));form.appendChild(createHiddenInput("jr_ps_restore_settings_nonce",restoreBtn.dataset.nonce));var placeholder=document.createElement("span");placeholder.id="jr-ps-restore-placeholder";restoreInput.parentNode.insertBefore(placeholder,restoreInput);form.appendChild(restoreInput);document.body.appendChild(form);setTimeout(function(){if(placeholder.parentNode){placeholder.parentNode.insertBefore(restoreInput,placeholder);placeholder.remove();}},5000);form.submit();});}
var resetBtn=document.querySelector(".jr-ps-reset-button");if(resetBtn){resetBtn.addEventListener("click",function(ev){ev.preventDefault();if(resetBtn.dataset.confirm&&!window.confirm(resetBtn.dataset.confirm)){return;}var form=buildForm(resetBtn.dataset.action);form.appendChild(createHiddenInput("action","my_private_site_reset_settings"));form.appendChild(createHiddenInput("jr_ps_reset_settings_nonce",resetBtn.dataset.nonce));document.body.appendChild(form);form.submit();});}});})();</script>';
        my_private_site_cmb2_add_raw_html(
            $section_options,
            $script,
            'my_private_site_backups_script',
            array( 'secondary_cb' => 'my_private_site_tab_advanced_page', 'secondary_tab' => 'backups', 'classes' => 'jrps-full-row' )
        );
    }

    return $section_options;
}

// advanced - PROCESS FORM SUBMISSIONS
function my_private_site_tab_advanced_process_buttons() {
        // Process Save changes button
        // This is a callback that has to be passed the full array for consideration
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! current_user_can( 'manage_options' ) ) {
                return;
        }
        $_POST = apply_filters( 'validate_page_slug_my_private_site_tab_advanced', $_POST );

	if ( isset( $_POST['jr_ps_button_advanced_save'], $_POST['jr_ps_button_advanced_save_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_advanced_save_nonce'], 'jr_ps_button_advanced_save' ) ) {
			wp_die( 'Security violation detected [A004]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}

		$settings = get_option( 'jr_ps_settings' );
		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_advanced_url'] ) ) {
			$url = my_private_site_validate_url( esc_url_raw( $_POST['jr_ps_admin_advanced_url'] ) );
			if ( $url != false ) {
				$url = jr_v1_sanitize_url( $url );
			} else {
				$url = '';
			}
		} else {
			$url = '';
		}

		if ( ! function_exists( 'my_private_site_pp_plugin_updater' ) ) {
			// these just check for value existence
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_POST['jr_ps_admin_advanced_password_reset_url'] ) ) {
				$reset_url = my_private_site_validate_url( esc_url_raw( $_POST['jr_ps_admin_advanced_password_reset_url'] ) );
				if ( $reset_url != '' && $reset_url == false ) {
					my_private_site_flag_cmb2_submit_button_error(
						'jr_ps_button_advanced_save',
						'Valid password reset URL must be provided.'
					);

					return;
				}
				if ( $reset_url == false ) {
					$settings['excl_url'] = array();
				} else {
					$settings['excl_url'] = array(); // clear it just to be sure
					$url_array            = jr_v1_prep_url( $reset_url );
					$add_array            = array(
						$reset_url,
						$url_array,
					);
					$settings['excl_url'] = array( $add_array );
				}
			}
		}

		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_advanced_enable_custom_login'] ) ) {
			// make sure a valid URL has been provided or set to empty
			if ( $url == '' ) {
				my_private_site_flag_cmb2_submit_button_error(
					'jr_ps_button_advanced_save',
					'URL must be provided if "Enable custom login page" is checked.'
				);

				return;
			}
			$settings['custom_login'] = true;
			$settings['login_url']    = $url;
		} else {
			if ( $url != '' ) {
				my_private_site_flag_cmb2_submit_button_error(
					'jr_ps_button_advanced_save',
					'Please check "Enable custom login page" to save custom login URL.'
				);

				return;
			}
			$settings['custom_login'] = false;
			$settings['login_url']    = '';
		}

		// these just check for value existence
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['jr_ps_admin_advanced_custom_landing'] ) ) {
			$settings['override_omit'] = true;
		} else {
			$settings['override_omit'] = false;
		}

		update_option( 'jr_ps_settings', $settings );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_advanced_save' );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=my_private_site_tab_advanced&subtab=advanced_options' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	if ( isset( $_POST['jr_ps_button_settings_logs_delete'], $_POST['jr_ps_button_settings_logs_delete_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_settings_logs_delete_nonce'], 'jr_ps_button_settings_logs_delete' ) ) {
			wp_die( 'Security violation detected [A007]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}
		delete_option( 'jr_ps_log' );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_settings_logs_delete' );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=my_private_site_tab_advanced&subtab=system_log' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	if ( isset( $_POST['jr_ps_button_spam_log_clear'], $_POST['jr_ps_button_spam_log_clear_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['jr_ps_button_spam_log_clear_nonce'], 'jr_ps_button_spam_log_clear' ) ) {
			wp_die( 'Security violation detected [A015]. Access denied.', 'Security violation', array( 'response' => 403 ) );
		}
		delete_option( 'jr_ps_spam_guard_log' );
		my_private_site_flag_cmb2_submit_button_success( 'jr_ps_button_spam_log_clear' );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=my_private_site_tab_advanced&subtab=spam_log' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

    // (Backup handled by admin_post_my_private_site_backup_settings)
}

function my_private_site_admin_advanced_preload( $data, $object_id, $args, $field ) {
	// find out what field we're getting
	$field_id = $args['field_id'];

	// get stored data from plugin
	$internal_settings = get_option( 'jr_ps_internal_settings' );
	$settings          = get_option( 'jr_ps_settings' );

	// Pull from existing My Private Site data formats
	switch ( $field_id ) {
		case 'jr_ps_admin_advanced_enable_custom_login':
			if ( isset( $settings['custom_login'] ) ) {
				return $settings['custom_login'];
			} else {
				return false;
			}
			break;
		case 'jr_ps_admin_advanced_url':
			if ( isset( $settings['login_url'] ) ) {
				return $settings['login_url'];
			} else {
				return false;
			}
			break;
		case 'jr_ps_admin_advanced_password_reset_url':
			if ( ! function_exists( 'my_private_site_pp_plugin_updater' ) ) {
				if ( isset( $settings['excl_url'] ) ) {
					if ( isset( $settings['excl_url'][0][0] ) ) {
						return $settings['excl_url'][0][0];
					}
				} else {
					return false;
				}
			}
			break;
		case 'jr_ps_admin_advanced_validate_login_url':
			if ( isset( $settings['custom_login_onsite'] ) ) {
				return $settings['custom_login_onsite'];
			} else {
				return false;
			}
			break;
		// case 'jr_ps_admin_advanced_compatibility_mode':
		// if (isset($settings['compatibility_mode'])) {
		// return $settings['compatibility_mode'];
		// } else {
		// return 'STANDARD';
		// }
		// break;
		case 'jr_ps_admin_advanced_custom_landing':
			if ( isset( $settings['override_omit'] ) ) {
				return $settings['override_omit'];
			} else {
				return false;
			}
			break;
	}
}

// Display restore/reset notices on the Backups sub-tab
add_action( 'admin_notices', function () {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'my_private_site_tab_advanced' ) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $status = isset( $_GET['jrps_restore_status'] ) ? sanitize_text_field( wp_unslash( $_GET['jrps_restore_status'] ) ) : '';
    $reset  = isset( $_GET['jrps_reset_status'] ) ? sanitize_text_field( wp_unslash( $_GET['jrps_reset_status'] ) ) : '';
    if ( $status === '' && $reset === '' ) {
        return;
    }
    $reason = isset( $_GET['jrps_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['jrps_reason'] ) ) : '';

    if ( $status === 'success' ) {
        echo '<div class="notice notice-success"><p>Settings restored successfully.</p></div>';
    } elseif ( $status === 'error' ) {
        echo '<div class="notice notice-error"><p>Restore failed: ' . esc_html( $reason ) . '</p></div>';
    }

    if ( $reset === 'success' ) {
        echo '<div class="notice notice-success"><p>Settings reset to defaults. You may now reconfigure the plugin.</p></div>';
    }
} );
