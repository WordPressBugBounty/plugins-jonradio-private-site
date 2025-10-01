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


// From CMB2 Snippet Library
// https://github.com/CMB2/CMB2-Snippet-Library/edit/master/options-and-settings-pages/options-pages-with-tabs-and-submenus.php
/**
 * A CMB2 options-page display callback override which adds tab navigation among
 * CMB2 options pages which share this same display callback.
 *
 * @param CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
 */
function my_private_site_cmb_options_display_with_tabs( $cmb_options ) {
    $tabs = my_private_site_cmb_options_page_tabs( $cmb_options );
    // All we're doing here is making sure we're on the right page
    // phpcs:ignore WordPress.Security.NonceVerification
    if ( isset( $_GET['page'] ) ) {
        $get_page = sanitize_text_field( $_GET['page'] );
    } else {
        $get_page = '';
    }

    // Determine secondary tabs for this page, if any
    $secondary      = my_private_site_cmb_collect_secondary_tabs( $cmb_options );
    $secondary_tabs = $secondary['tabs'];
    $cmb_id         = $secondary['cmb_id'];

    // Enforce specific subtab ordering on the Site Privacy page
    if ( $cmb_id === 'my_private_site_tab_site_privacy_page' && ! empty( $secondary_tabs ) ) {
        // Desired order: core subtabs first, then add-on subtabs
        $desired = array( 'privacy', 'protection', 'ai-intelligence', 'user_activity' );

        $ordered = array();
        foreach ( $desired as $slug ) {
            if ( isset( $secondary_tabs[ $slug ] ) ) {
                $ordered[ $slug ] = $secondary_tabs[ $slug ];
                unset( $secondary_tabs[ $slug ] );
            }
        }
        // Append any remaining subtabs in their existing order
        foreach ( $secondary_tabs as $slug => $label ) {
            $ordered[ $slug ] = $label;
        }
        $secondary_tabs = $ordered;
    }

    // Active (selected) secondary tab from querystring, defaults to first
    // phpcs:ignore WordPress.Security.NonceVerification
    $active_secondary = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : '';
    if ( empty( $active_secondary ) && ! empty( $secondary_tabs ) ) {
        $first_keys       = array_keys( $secondary_tabs );
        $active_secondary = $first_keys[0];
    }

    // Share active secondary to row-class filter for conditional display
    if ( ! isset( $GLOBALS['jrps_active_subtabs'] ) || ! is_array( $GLOBALS['jrps_active_subtabs'] ) ) {
        $GLOBALS['jrps_active_subtabs'] = array();
    }
    if ( ! isset( $GLOBALS['jrps_has_secondary'] ) || ! is_array( $GLOBALS['jrps_has_secondary'] ) ) {
        $GLOBALS['jrps_has_secondary'] = array();
    }
    $GLOBALS['jrps_active_subtabs'][ $cmb_id ] = $active_secondary;
    $GLOBALS['jrps_has_secondary'][ $cmb_id ]  = count( $secondary_tabs ) > 0;
    // Also store the per-field mapping built during collection
    if ( isset( $secondary['map'] ) && is_array( $secondary['map'] ) ) {
        if ( ! isset( $GLOBALS['jrps_subtab_map'] ) || ! is_array( $GLOBALS['jrps_subtab_map'] ) ) {
            $GLOBALS['jrps_subtab_map'] = array();
        }
        $GLOBALS['jrps_subtab_map'][ $cmb_id ] = $secondary['map'];
    }

    // Ensure our row class filter is available while rendering this form
    add_filter( 'cmb2_row_classes', 'my_private_site_cmb_secondary_row_classes', 10, 2 );
    // Enforce server-side rendering only for active subtab fields
    add_filter( 'cmb2_field_arguments', 'my_private_site_cmb_enforce_subtab_show_on', 20, 2 );

    ?>
    <div class="wrap cmb2-options-page option-<?php echo esc_attr( $cmb_options->option_key ); ?>">
        <?php if ( get_admin_page_title() ) : ?>
            <h2><?php echo wp_kses_post( get_admin_page_title() ); ?></h2>
        <?php endif; ?>
        <h2 class="nav-tab-wrapper">
            <?php foreach ( $tabs as $option_key => $tab_title ) : ?>
                <a class="nav-tab<?php if ( $get_page !== '' && $option_key === $get_page ) : ?> nav-tab-active<?php endif; ?>"
                   href="<?php menu_page_url( $option_key ); ?>"><?php echo wp_kses_post( $tab_title ); ?></a>
            <?php endforeach; ?>
        </h2>
        <style>
            /* Tighter spacing around our custom save-action rows (applies to all pages) */
            .cmb2-wrap .cmb-row.jrps-button-row{margin-top:-12px !important; margin-bottom:-10px !important;}
            .cmb2-wrap .cmb-row.jrps-button-row .cmb-td{padding-top:2px !important; padding-bottom:2px !important; padding-left:0 !important;}
            .cmb2-wrap .cmb-row.jrps-button-row form{margin:0 !important;}
            .cmb2-wrap .cmb-row.jrps-button-row p.submit{margin:0 !important; padding:0 !important;}
            .cmb2-wrap .cmb-row.jrps-button-row .button{margin:0 !important;}
            .cmb2-wrap .cmb-row.jrps-button-row .button.button-primary{margin:0 !important;}
            /* Full-width raw HTML rows (e.g., activity table) */
            .cmb2-wrap .cmb-row.jrps-full-row .cmb-th{display:none !important;}
            .cmb2-wrap .cmb-row.jrps-full-row .cmb-td{width:100% !important; padding-left:0 !important; padding-right:0 !important; float:none !important;}
            .cmb2-wrap .cmb-row.jrps-full-row{clear:both !important;}
            /* Just in case the secondary tab wrapper adds a rule */
            .nav-tab-wrapper.jrps-secondary-tabs{border-bottom:0 !important;}
        </style>

        <?php if ( count( $secondary_tabs ) > 1 ) : ?>
            <h2 class="nav-tab-wrapper jrps-secondary-tabs" style="margin-top: -10px;">
                <?php foreach ( $secondary_tabs as $sec_key => $sec_title ) :
                    // Build URL for this secondary tab under the current primary tab
                    $url = menu_page_url( $get_page, false );
                    $url = add_query_arg( 'subtab', $sec_key, $url );
                    ?>
                    <a class="nav-tab<?php if ( $sec_key === $active_secondary ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( $url ); ?>">
                        <?php echo esc_html( $sec_title ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            <style>
                /* Simple visibility control for secondary-tabbed rows */
                .cmb2-wrap .jrps-subtab-row{display:none;}
                .cmb2-wrap .jrps-subtab-row.jrps-active{display:block;}
                /* Tighter spacing around our custom save-action rows */
                .cmb2-wrap .cmb-row.jrps-button-row{margin-top:-12px !important; margin-bottom:-10px !important;}
                .cmb2-wrap .cmb-row.jrps-button-row .cmb-td{padding-top:2px !important; padding-bottom:2px !important; padding-left:0 !important;}
                .cmb2-wrap .cmb-row.jrps-button-row p.submit{margin:0 !important; padding:0 !important;}
                .cmb2-wrap .cmb-row.jrps-button-row .button.button-primary{margin:0 !important;}
            </style>
            <?php
            // Enqueue our subtabs script and pass data
            wp_enqueue_script(
                'my-private-site-subtabs',
                plugins_url( 'js/subtabs.js', dirname( __FILE__ ) . '/../jonradio-private-site.php' ),
                array(),
                '1.0.0',
                true
            );
            wp_localize_script(
                'my-private-site-subtabs',
                'jrpsSubtabs',
                array(
                    'map'    => isset( $secondary['map'] ) ? $secondary['map'] : array(),
                    'active' => $active_secondary,
                )
            );
            ?>
        <?php endif; ?>

        <form class="cmb-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST"
              id="<?php echo esc_attr( $cmb_options->cmb->cmb_id ); ?>" enctype="multipart/form-data"
              encoding="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo esc_attr( $cmb_options->option_key ); ?>">
            <?php $cmb_options->options_page_metabox(); ?>
            <?php submit_button( esc_attr( $cmb_options->cmb->prop( 'save_button' ) ), 'primary', 'submit-cmb' ); ?>
        </form>
    </div>
    <?php

    // Clean up after rendering this form so other admin pages are unaffected
    remove_filter( 'cmb2_row_classes', 'my_private_site_cmb_secondary_row_classes', 10 );
}

/**
 * Gets navigation tabs array for CMB2 options pages which share the given
 * display_cb param.
 *
 * @param CMB2_Options_Hookup $cmb_options The CMB2_Options_Hookup object.
 *
 * @return array Array of tab information.
 */
function my_private_site_cmb_options_page_tabs( $cmb_options ) {
    $tab_group = $cmb_options->cmb->prop( 'tab_group' );
    $tabs      = array();

	foreach ( CMB2_Boxes::get_all() as $cmb_id => $cmb ) {
		if ( $tab_group === $cmb->prop( 'tab_group' ) ) {
			$tabs[ $cmb->options_page_keys()[0] ] = $cmb->prop( 'tab_title' )
				? $cmb->prop( 'tab_title' )
				: $cmb->prop( 'title' );
		}
	}

	return $tabs;
}

/**
 * Scan CMB2 fields for markers that define secondary tab groups.
 *
 * A secondary tab group can be declared by adding the following keys to a field
 * (typically the first 'title' field for the section):
 *   - 'secondary_cb'    => <parent cmb_id> (ID of the primary tab's CMB2 box)
 *   - 'secondary_tab'   => <slug for this subtab>
 *   - 'secondary_title' => <label for this subtab> (falls back to field 'name')
 *
 * All rows following that declaration inherit the subtab until another field
 * declares a new 'secondary_tab'.
 *
 * @param CMB2_Options_Hookup $cmb_options
 * @return array { 'cmb_id' => string, 'tabs' => array slug => label }
 */
function my_private_site_cmb_collect_secondary_tabs( $cmb_options ) {
    $cmb_id = $cmb_options->cmb->cmb_id; // current metabox id for this page
    $fields = $cmb_options->cmb->prop( 'fields' );

    $tabs = array();
    $map  = array(); // field_id => subtab slug

    if ( empty( $fields ) || ! is_array( $fields ) ) {
        return array( 'cmb_id' => $cmb_id, 'tabs' => $tabs, 'map' => $map );
    }

    $current = '';
    foreach ( $fields as $field_id => $field ) {
        // Only consider fields explicitly tied to this CMB2 box (if limited)
        if ( isset( $field['secondary_cb'] ) && $field['secondary_cb'] !== $cmb_id ) {
            // Field declares a different parent; do not include in mapping.
            continue;
        }

        if ( isset( $field['secondary_tab'] ) && $field['secondary_tab'] !== '' ) {
            $current = sanitize_key( $field['secondary_tab'] );

            // Register tab label
            $label = isset( $field['secondary_title'] ) && $field['secondary_title'] !== ''
                ? $field['secondary_title']
                : ( isset( $field['name'] ) ? $field['name'] : $current );
            if ( ! isset( $tabs[ $current ] ) ) {
                $tabs[ $current ] = $label;
            }
        }

        if ( ! empty( $current ) ) {
            $map[ $field_id ] = $current;
        }
    }

    return array( 'cmb_id' => $cmb_id, 'tabs' => $tabs, 'map' => $map );
}

/**
 * Adds CSS classes to CMB2 field rows so they can be shown/hidden per
 * secondary tab selection.
 *
 * This relies on the field arguments containing either a 'secondary_tab'
 * (typically set on the first 'title' row for a section) or inheriting the
 * last seen 'secondary_tab' for the current metabox.
 *
 * @param string      $classes Existing row classes.
 * @param CMB2_Field  $field   Field object.
 * @return string
 */
function my_private_site_cmb_secondary_row_classes( $classes, $field ) {
    // Retrieve the current CMB2 box id for this field
    $cmb_id = isset( $field->cmb_id ) ? $field->cmb_id : '';

    // If this metabox doesn't use secondary tabs, leave untouched
    $has_secondary = isset( $GLOBALS['jrps_has_secondary'][ $cmb_id ] ) && $GLOBALS['jrps_has_secondary'][ $cmb_id ];
    if ( ! $has_secondary ) {
        return $classes;
    }

    static $last_seen_for_cmb = array();


    // Determine this row's subtab via the pre-built map (robust and order-independent)
    $field_id = method_exists( $field, 'id' ) ? $field->id( true ) : '';
    $slug     = '';
    if ( isset( $GLOBALS['jrps_subtab_map'][ $cmb_id ] ) && isset( $GLOBALS['jrps_subtab_map'][ $cmb_id ][ $field_id ] ) ) {
        $slug = $GLOBALS['jrps_subtab_map'][ $cmb_id ][ $field_id ];
    } else {
        // Fallback to inline args if present
        $slug = $field->args( 'secondary_tab' );
    }

    if ( ! empty( $slug ) ) {
        $active = isset( $GLOBALS['jrps_active_subtabs'][ $cmb_id ] ) ? $GLOBALS['jrps_active_subtabs'][ $cmb_id ] : '';
        $classes .= ' jrps-subtab-row jrps-subtab-' . sanitize_html_class( $slug );
        if ( $active === $slug ) {
            $classes .= ' jrps-active';
        } else {
            $classes .= ' jrps-inactive';
        }
    }

    return $classes;
}

// set up filter to pre-load field values from My Private Site database
// from: https://github.com/CMB2/CMB2/wiki/Tips-&-Tricks#override-the-data-storage-location-for-a-cmb2-box
function my_private_site_preload_cmb2_field_filter( $field_id, $handler_function_name ) {
	add_filter(
		'cmb2_override_' . $field_id . '_meta_value', // the filter
		$handler_function_name,
		10,
		4
	);
}

function my_private_site_display_cmb2_submit_button( $section_options, $button_options ) {
	$field_args = array(
		'name'           => $button_options['button_text'],
		'id'             => $button_options['button_id'],
		'button_options' => $button_options,
		'type'           => 'ignoreme',
		'render_row_cb'  => 'my_private_site_display_cmb2_submit_button_callback',
	);

	if ( isset( $button_options['row_classes'] ) ) {
		$field_args['row_classes'] = $button_options['row_classes'];
	}

	if ( ! empty( $button_options['field_args'] ) && is_array( $button_options['field_args'] ) ) {
		$field_args = array_merge( $field_args, $button_options['field_args'] );
	}

	$section_options->add_field( $field_args );
}

function my_private_site_display_cmb2_submit_button_callback( $field_args, $field ) {
	// get button values
	$page_stub          = $field->object_id;
	$button_id          = $field->args['button_options']['button_id'];
	$button_text        = $field->args['button_options']['button_text'];
	$button_success_msg = $field->args['button_options']['button_success_msg'];
	$button_error_msg   = $field->args['button_options']['button_error_msg'];

	// debug code
	// $button_list_option_name = 'jr_ps_my_private_site_tab_settings_button_list';
	// $button_list_option      = get_option($button_list_option_name);
	// if ($button_list_option == false) {
	// $button_list_array = array();
	// } else {
	// $button_list_array = unserialize($button_list_option);
	// }
	// $foo = serialize($button_list_array);

    // show error if option set (only when flagged active)
    $error_msg = my_private_site_get_cmb2_submit_button_error_message( $button_id );
	if ( $error_msg != '' ) {
		echo '<div id="' . esc_attr( $button_id ) . '" class="notice notice-error">';
		echo esc_attr( $error_msg );
		echo '</div>';
	}

    // show message if option set (only when flagged active)
    $button_msg = my_private_site_get_cmb2_submit_button_success_message( $button_id );
	if ( $button_msg != '' ) {
		echo '<div id="' . esc_attr( $button_id ) . '" class="notice notice-message">';
		echo esc_attr( $button_msg );
		echo '</div>';
	}

    // set up the fresh button message array for this admin page load
    $button_list_option_name = 'jr_ps_' . $page_stub . '_button_list';
    $button_list_option      = get_option( $button_list_option_name );

    if ( $button_list_option == false ) {
        $button_list_array = array();
    } else {
        $button_list_array = maybe_unserialize( $button_list_option );
    }
    $button_id_success = $button_id . '_success';
    $button_id_error   = $button_id . '_error';

    // Store default messages in a separate namespace so they do not display
    // unless explicitly flagged by a save handler.
    if ( ! isset( $button_list_array['__defaults'] ) || ! is_array( $button_list_array['__defaults'] ) ) {
        $button_list_array['__defaults'] = array();
    }
    $button_list_array['__defaults'][ $button_id_success ] = $button_success_msg;
    $button_list_array['__defaults'][ $button_id_error ]   = $button_error_msg;
    update_option( $button_list_option_name, $button_list_array );

	// create nonce code based on the ID of the button

	$nonce      = wp_create_nonce( $button_id );
	$nonce_name = $button_id . '_nonce';

    // Optional attributes for the submit button
    $button_attributes = array();
    if ( isset( $field->args['button_options']['attributes'] ) && is_array( $field->args['button_options']['attributes'] ) ) {
        $button_attributes = $field->args['button_options']['attributes'];
    }

    $attr_html = '';
    if ( ! empty( $button_attributes ) ) {
        foreach ( $button_attributes as $akey => $aval ) {
            if ( is_int( $akey ) ) {
                // Numeric key means boolean attribute e.g. [ 'disabled' ]
                $attr_name = sanitize_key( $aval );
                $attr_html .= ' ' . esc_attr( $attr_name );
            } else {
                $attr_name  = sanitize_key( $akey );
                $attr_value = (string) $aval;
                if ( $attr_value === '' ) {
                    $attr_html .= ' ' . esc_attr( $attr_name );
                } else {
                    $attr_html .= ' ' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
                }
            }
        }
    }

    // display the button inside the standard CMB2 row wrapper
    ?>
    <div class="cmb-row jrps-button-row <?php echo esc_attr( $field->row_classes() ); ?>" data-fieldtype="ignoreme">
        <div class="cmb-td">
            <div class="cmb-action-button-row">
                <p class="submit">
                    <input type="hidden" id="<?php echo esc_attr( $nonce_name ); ?>"
                           name="<?php echo esc_attr( $nonce_name ); ?>"
                           value="<?php echo esc_attr( $nonce ); ?>"/>
                    <input type="submit" name="<?php echo esc_attr( $button_id ); ?>"
                           id="<?php echo esc_attr( $button_id ); ?>"
                           class="button button-primary"
                           <?php echo $attr_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                           value="<?php echo esc_attr( $button_text ); ?>">
                </p>
            </div>
        </div>
    </div>
    <?php
}

function my_private_site_flag_cmb2_submit_button_success( $button_id, $msg = '' ) {
	my_private_site_set_cmb2_submit_button_flag( $button_id, '_success', $msg );
}

function my_private_site_flag_cmb2_submit_button_error( $button_id, $msg = '' ) {
	my_private_site_set_cmb2_submit_button_flag( $button_id, '_error', $msg );
}

function my_private_site_set_cmb2_submit_button_flag( $button_id, $what_to_set, $msg = '' ) {
	// uses the default param so we can have two different functions with essentially the same code
	// it will be easier to read on the form settings pages
	// All we're doing here is making sure we're on the right page
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_POST['action'] ) ) {
		$page_stub = sanitize_text_field( $_POST['action'] );
	} else {
		$page_stub = '';
	}

	$button_to_set = $button_id . $what_to_set;

    $button_list_option_name = 'jr_ps_' . $page_stub . '_button_list';
    $button_list_option      = get_option( $button_list_option_name );
	if ( $button_list_option != false ) {
		$button_list_array = maybe_unserialize( $button_list_option );

        if ( $msg == '' ) {
            if ( isset( $button_list_array[ $button_to_set ] ) ) {
                $message_to_set = $button_list_array[ $button_to_set ];
            } elseif ( isset( $button_list_array['__defaults'][ $button_to_set ] ) ) {
                // Fall back to default message if provided
                $message_to_set = $button_list_array['__defaults'][ $button_to_set ];
            } else {
                $message_to_set = '';
            }
        } else {
            $message_to_set = $msg;
        }

		unset( $button_list_array );
        $button_list_array                   = array();
        $button_list_array[ $button_to_set ] = $message_to_set;
        update_option( $button_list_option_name, $button_list_array );
    }
}

function my_private_site_get_cmb2_submit_button_success_message( $button_id ) {
	return my_private_site_get_cmb2_submit_button_message( $button_id, '_success' );
}

function my_private_site_get_cmb2_submit_button_error_message( $button_id ) {
	return my_private_site_get_cmb2_submit_button_message( $button_id, '_error' );
}

function my_private_site_get_cmb2_submit_button_message( $button_id, $what_to_get ) {
	// uses the default param so we can have two different functions with essentially the same code
	// it will be easier to read on the form settings pages
	// All we're doing here is making sure we're on the right page
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_GET['page'] ) ) {
		$page_stub = sanitize_text_field( $_GET['page'] );

		$button_list_option_name = 'jr_ps_' . $page_stub . '_button_list';
		$button_list_option      = get_option( $button_list_option_name );
        if ( $button_list_option == false ) {
            return '';
        } else {
            $button_list_array = maybe_unserialize( $button_list_option );
        }

        // Only display if this message has been explicitly flagged (top-level key)
        if ( isset( $button_list_array[ $button_id . $what_to_get ] ) ) {
            return $button_list_array[ $button_id . $what_to_get ];
        }
        return '';
	} else {
		return '';
	}
}

function my_private_site_clear_cmb2_submit_button_messages( $page_stub ) {
	if ( ! my_private_site_is_referred_by_page( $page_stub ) ) {
		// clear previous error messages if coming from another page
		$button_list_option_name = 'jr_ps_' . $page_stub . '_button_list';
		$button_list_array       = array();
		update_option( $button_list_option_name, $button_list_array );
	}
}

// Adds custom action button to form
// $name is the the text displayed in the button
// $id is the unique id of the action button
function my_private_site_cmb2_add_action_button( $section_options, $name, $id ) {
	$section_options->add_field(
		array(
			'name'          => esc_attr( $name ),
			'id'            => esc_attr( $id ),
			'render_row_cb' => 'my_private_site_cmb2_row_callback_for_action_button',
		)
	);
}

function my_private_site_cmb2_row_callback_for_action_button( $field_args, $field ) {
	$button_name     = $field->args['name'];
	$button_id       = $field->args['id'];
	$button_error_id = $button_id . '_error';
	$button_msg_id   = $button_id . '_msg';

	// show error if option set
	$error_msg = get_option( $button_error_id );
	if ( $error_msg != false ) {
		if ( $error_msg != '' ) {
			echo '<div id="' . esc_attr( $button_error_id ) . '" class="notice notice-error">';
			echo esc_attr( $error_msg );
			echo '</div>';
		}
	}
	// show message if option set
	$button_msg = get_option( $button_msg_id );
	if ( $button_msg != false ) {
		if ( $button_msg != '' ) {
			echo '<div id="' . esc_attr( $button_msg_id ) . '" class="notice notice-message">';
			echo esc_attr( $error_msg );
			echo '</div>';
		}
	}

	// display the button
	?>
    <div class="cmb-action-button-row">
        <p class="submit">
            <input type="submit" name="<?php echo esc_attr( $button_id ); ?>"
                   id="<?php echo esc_attr( $button_id ); ?>"
                   class="button button-primary"
                   value="<?php echo esc_attr( $button_name ); ?>"></p>
    </div>
	<?php
}

// Adds a static description line to the form
// $name is the the text displayed in the button
// 'desc' passed as an argument is the static text description displayed
// $id is the unique id of the action button
function my_private_site_cmb2_add_static_desc( $section_options, $desc, $id, $extras = array() ) {
    $args = array(
            'desc'          => $desc,
            'id'            => esc_attr( $id ),
            'type'          => 'ignoreme',
            'render_row_cb' => 'my_private_site_cmb2_row_callback_for_static_desc',
    );
    if ( is_array( $extras ) && ! empty( $extras ) ) {
        $args = array_merge( $args, $extras );
    }
    $section_options->add_field( $args );
}

function my_private_site_cmb2_row_callback_for_static_desc( $field_args, $field ) {
    $desc = $field->args['desc'];
    $id   = $field->args['id'];

    ?>
    <div class="cmb-row <?php echo esc_attr( $field->row_classes() ); ?>" id="<?php echo esc_attr( $id ); ?>" data-fieldtype="ignoreme">
        <div class="cmb-td">
            <p class="cmb2-metabox-description"><?php echo esc_textarea( $desc ); ?></p>
        </div>
    </div>
    <?php
}

// Adds a raw HTML row (unescaped) to the form
function my_private_site_cmb2_add_raw_html( $section_options, $html, $id, $extras = array() ) {
    $args = array(
        'desc'          => $html,
        'id'            => esc_attr( $id ),
        'type'          => 'ignoreme',
        'render_row_cb' => 'my_private_site_cmb2_row_callback_for_raw_html',
    );
    if ( is_array( $extras ) && ! empty( $extras ) ) {
        $args = array_merge( $args, $extras );
    }
    $section_options->add_field( $args );
}

function my_private_site_cmb2_row_callback_for_raw_html( $field_args, $field ) {
    $html = $field->args['desc'];
    $id   = $field->args['id'];

    ?>
    <div class="cmb-row <?php echo esc_attr( $field->row_classes() ); ?>" id="<?php echo esc_attr( $id ); ?>" data-fieldtype="ignoreme">
        <div class="cmb-td">
            <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
    <?php
}

// Adds a labeled two-column row with custom HTML in the right column
function my_private_site_cmb2_add_labeled_html( $section_options, $label, $html, $id, $extras = array() ) {
    $args = array(
        'name'          => $label,
        'desc'          => $html,
        'id'            => esc_attr( $id ),
        'type'          => 'ignoreme',
        'render_row_cb' => 'my_private_site_cmb2_row_callback_for_labeled_html',
    );
    if ( is_array( $extras ) && ! empty( $extras ) ) {
        $args = array_merge( $args, $extras );
    }
    $section_options->add_field( $args );
}

function my_private_site_cmb2_row_callback_for_labeled_html( $field_args, $field ) {
    $label = isset( $field->args['name'] ) ? $field->args['name'] : '';
    $html  = isset( $field->args['desc'] ) ? $field->args['desc'] : '';
    $id    = $field->args['id'];
    ?>
    <div class="cmb-row <?php echo esc_attr( $field->row_classes() ); ?>" id="<?php echo esc_attr( $id ); ?>" data-fieldtype="ignoreme">
        <div class="cmb-th">
            <label><?php echo esc_html( $label ); ?></label>
        </div>
        <div class="cmb-td">
            <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
    <?php
}

/**
 * Enforce that only fields for the active secondary tab render at all.
 * This prevents inactive subtab content from painting even briefly.
 */
function my_private_site_cmb_enforce_subtab_show_on( $args, $field ) {
    if ( ! isset( $field->cmb_id ) ) { return $args; }
    $cmb_id = $field->cmb_id;
    $map    = isset( $GLOBALS['jrps_subtab_map'][ $cmb_id ] ) ? $GLOBALS['jrps_subtab_map'][ $cmb_id ] : array();
    $active = isset( $GLOBALS['jrps_active_subtabs'][ $cmb_id ] ) ? $GLOBALS['jrps_active_subtabs'][ $cmb_id ] : '';
    if ( empty( $active ) ) { return $args; }

    $fid  = isset( $args['id'] ) ? $args['id'] : '';
    $slug = isset( $map[ $fid ] ) ? $map[ $fid ] : ( isset( $args['secondary_tab'] ) ? sanitize_key( $args['secondary_tab'] ) : '' );
    if ( empty( $slug ) ) { return $args; }

    $want = ( $slug === $active );
    // Respect existing show_on_cb if present, but AND it with our subtab rule.
    $prior_cb = isset( $args['show_on_cb'] ) ? $args['show_on_cb'] : null;
    $args['show_on_cb'] = function( $cmb_or_field ) use ( $want, $prior_cb ) {
        if ( $prior_cb && is_callable( $prior_cb ) ) {
            if ( ! call_user_func( $prior_cb, $cmb_or_field ) ) {
                return false;
            }
        }
        return $want;
    };

    return $args;
}

// Global early gate: only render active Site Privacy subtab fields during field registration
add_filter( 'cmb2_field_arguments', function( $args, $field ) {
    // Only for Site Privacy page subtabs using our secondary system
    if ( ! isset( $args['secondary_cb'] ) || $args['secondary_cb'] !== 'my_private_site_tab_site_privacy_page' ) {
        return $args;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $active = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : '';
    if ( $active === '' ) { $active = 'privacy'; }
    $slug = isset( $args['secondary_tab'] ) ? sanitize_key( $args['secondary_tab'] ) : '';
    if ( $slug === '' ) { return $args; }
    $want = ( $slug === $active );
    $prior_cb = isset( $args['show_on_cb'] ) ? $args['show_on_cb'] : null;
    $args['show_on_cb'] = function( $cmb_or_field ) use ( $want, $prior_cb ) {
        if ( $prior_cb && is_callable( $prior_cb ) ) {
            if ( ! call_user_func( $prior_cb, $cmb_or_field ) ) { return false; }
        }
        return $want;
    };
    return $args;
}, 0, 2 );

// Early CSS in <head> so inactive subtabs never paint on first render
add_action( 'admin_head', function(){
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset( $_GET['page'] ) ? (string) $_GET['page'] : '';
    if ( ! is_admin() || strpos( $page, 'my_private_site_tab_' ) !== 0 ) { return; }
    echo '<style id="jrps-early-subtabs-css">.cmb2-wrap .jrps-subtab-row{display:none !important;} .cmb2-wrap .jrps-subtab-row.jrps-active{display:block !important;}</style>';
}, 0 );
