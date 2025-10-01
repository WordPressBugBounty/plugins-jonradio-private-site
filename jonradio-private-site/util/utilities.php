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

// quick array name-of function
// from http://php.net/manual/en/function.key.php
function my_private_site_name_of( array $a, $pos ) {
	$temp = array_slice( $a, $pos, 1, true );

	return key( $temp );
}

// from http://www.w3schools.com/php/filter_validate_url.asp
// returns a clean URL or false
// use === false to check it
function my_private_site_validate_url( $url ) {
	// Remove all illegal characters from a url
	$url = filter_var( $url, FILTER_SANITIZE_URL );

	// Validate url
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
		return $url;
	} else {
		return false;
	}
}

function my_private_site_obscurify_string( $s, $char = '*', $inner_obscure = true ) {
	$length = strlen( $s );
	if ( $length > 6 ) {
		$segment_size = intval( $length / 3 );
		$seg1         = substr( $s, 0, $segment_size );
		$seg2         = substr( $s, $segment_size, $segment_size );
		$seg3         = substr( $s, $segment_size * 2, $length - ( $segment_size * 2 ) );

		if ( $inner_obscure ) {
			$seg2 = str_repeat( $char, $segment_size );
		} else {
			$seg1 = str_repeat( $char, $segment_size );
			$seg3 = str_repeat( $char, strlen( $seg3 ) );
		}

		$s = $seg1 . $seg2 . $seg3;
	}

	return $s;
}

// label display functions

function my_private_site_get_feature_promo( $desc, $url, $upgrade = 'UPGRADE', $break = '<BR>' ) {
	$feature_desc = sanitize_text_field( htmlspecialchars( $desc ) );

	$promo  = $break;
	$promo .= '<span style="background-color:DarkGoldenRod; color:white;font-style:normal;text-weight:bold">';
	$promo .= '&nbsp;' . $upgrade . ':&nbsp;';
	$promo .= '</span>';
	$promo .= '<span style="color:DarkGoldenRod;font-style:normal;">';
	$promo .= '&nbsp;' . $feature_desc . ' ';
	$promo .= '<A target="_blank" HREF="' . $url . '">Learn more.</A>';
	$promo .= '</span>';

	return $promo;
}

function my_private_site_display_label( $before = '&nbsp;', $message = 'BETA', $after = '', $background = '' ) {
	if ( $background == '' ) {
		$background = 'darkgrey';
	}
	$label  = $before . '<span style="background-color:' . $background . '; color:white;font-style:normal;text-weight:bold">';
	$label .= '&nbsp;' . $message . '&nbsp;';
	$label .= '</span>' . $after;

	return $label;
}

function my_private_site_display_fail() {
	return my_private_site_display_label( '&nbsp;', 'FAIL', '', 'red' );
}

function my_private_site_display_pass() {
	return my_private_site_display_label( '&nbsp;', 'PASS', '', 'green' );
}

function my_private_site_telemetry_url() {
	return 'https://zatzlabs.com';
}

/******************************************************************************************************/
function my_private_site_debug_log( $message ) {
	$max_log_line_count = 200;

	$debug_log = get_option( 'jr_ps_donate_log' );

	if ( empty( $debug_log ) ) {
		$debug_log = array();
	}

	$timestamp = current_time( 'mysql' );

	$debug_log[] = $timestamp . ' ' . $message;

	if ( count( $debug_log ) > $max_log_line_count ) {
		$debug_log = array_slice( $debug_log, -$max_log_line_count, 0 );
	}

	update_option( 'jr_ps_donate_log', $debug_log );
}



/**
 * Check if AI Defense (RSL block) is enabled in settings.
 *
 * @return bool
 */
/**
 * Detect if a physical robots.txt exists in the site root.
 *
 * WordPress serves a virtual robots.txt when no physical file exists.
 * If a physical file is present, the robots_txt filter will not run.
 *
 * @return bool
 */
function my_private_site_physical_robots_exists() {
    $candidates = array();
    if ( defined( 'ABSPATH' ) ) {
        $candidates[] = trailingslashit( ABSPATH ) . 'robots.txt';
    }
    if ( isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) && $_SERVER['DOCUMENT_ROOT'] !== '' ) {
        $docroot_path = rtrim( wp_normalize_path( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ), "/" ) . '/robots.txt';
        if ( ! in_array( $docroot_path, $candidates, true ) ) {
            $candidates[] = $docroot_path;
        }
    }
    foreach ( $candidates as $path ) {
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_exists
        if ( file_exists( $path ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Check if AI Defense (RSL block) is enabled in settings and allowed.
 *
 * Returns true only if the setting is enabled and no physical robots.txt exists.
 *
 * @return bool
 */
function my_private_site_is_ai_defense_enabled() {
    $settings = get_option( 'jr_ps_settings' );
    $enabled  = ( is_array( $settings ) && ! empty( $settings['ai_defense_rsl_block'] ) );
    if ( ! $enabled ) {
        return false;
    }

    // Block activation if a physical robots.txt is present.
    if ( my_private_site_physical_robots_exists() ) {
        return false;
    }

    return true;
}

/**
 * Store an admin notice message to be displayed once.
 *
 * @param string $message Notice text.
 * @param string $type    One of 'success', 'error', 'warning'.
 */
function my_private_site_set_ai_defense_notice( $message, $type = 'success' ) {
    $internal = get_option( 'jr_ps_internal_settings' );
    if ( ! is_array( $internal ) ) {
        $internal = array();
    }
    $internal['ai_defense_notice']      = (string) $message;
    $internal['ai_defense_notice_type'] = in_array( $type, array( 'success', 'error', 'warning' ), true ) ? $type : 'success';
    update_option( 'jr_ps_internal_settings', $internal );
}

/**
 * Output any pending AI Defense admin notice and clear it.
 */
function my_private_site_show_ai_defense_admin_notice() {
    if ( ! is_admin() ) {
        return;
    }
    $internal = get_option( 'jr_ps_internal_settings' );
    if ( ! is_array( $internal ) || empty( $internal['ai_defense_notice'] ) ) {
        return;
    }
    $msg  = (string) $internal['ai_defense_notice'];
    $type = isset( $internal['ai_defense_notice_type'] ) ? (string) $internal['ai_defense_notice_type'] : 'success';
    $class = 'notice';
    switch ( $type ) {
        case 'error':
            $class .= ' notice-error';
            break;
        case 'warning':
            $class .= ' notice-warning';
            break;
        default:
            $class .= ' notice-success';
            break;
    }
    // Allow safe HTML (e.g., links) in the notice message.
    echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . wp_kses_post( $msg ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

    unset( $internal['ai_defense_notice'], $internal['ai_defense_notice_type'] );
    update_option( 'jr_ps_internal_settings', $internal );
}
add_action( 'admin_notices', 'my_private_site_show_ai_defense_admin_notice' );
add_action( 'network_admin_notices', 'my_private_site_show_ai_defense_admin_notice' );

/**
 * Determine if the Advanced AI Defense (AAD) add-on is active.
 *
 * We detect by the presence of its version constant or a known function it declares.
 *
 * @return bool
 */
function my_private_site_is_aad_active() {
    return ( defined( 'MY_PRIVATE_SITE_AAD_CURRENT_VERSION' ) || function_exists( 'my_private_site_aad_append_ai_defense_ui' ) );
}

/**
 * Retrieve the map of tutorial video URLs used across the admin UI.
 *
 * @return array<string,string> Associative array of tutorial identifiers to embed URLs.
 */
function my_private_site_get_tutorial_videos() {
    static $videos = null;

    if ( null === $videos ) {
        $videos = array(
            'dashboard_getting_started_tutorial'      => 'https://www.youtube-nocookie.com/embed/jry3DHD-OB8?rel=0&modestbranding=1&controls=1',
            'privacy_mode_tutorial'                   => 'https://www.youtube-nocookie.com/embed/u7BuYtzS_pI?rel=0&modestbranding=1&controls=1',
            'public_pages_overview_tutorial'          => 'https://www.youtube-nocookie.com/embed/u7BuYtzS_pI?rel=0&modestbranding=1&controls=1',
            'public_pages_tags_categories_tutorial'   => 'https://www.youtube-nocookie.com/embed/dEv7lXxU5lo?rel=0&modestbranding=1&controls=1',
            'selective_content_tutorial'              => 'https://www.youtube-nocookie.com/embed/exgJrJJSCNY?rel=0&modestbranding=1&controls=1',
            'block_ip_protection_tutorial'            => 'https://www.youtube-nocookie.com/embed/vsxLqYXWITs?rel=0&modestbranding=1&controls=1',
            'ai_defense_overview_tutorial'            => 'https://www.youtube-nocookie.com/embed/Eb4qQDafaRk?rel=0&modestbranding=1&controls=1',
            'visitor_intelligence_overview_tutorial'  => 'https://www.youtube-nocookie.com/embed/TTK8bGVD8pM?rel=0&modestbranding=1&controls=1',
            'guest_access_overview_tutorial'          => 'https://www.youtube-nocookie.com/embed/j1vYV8lhqcc?rel=0&modestbranding=1&controls=1',
            'block_ip_addon_tutorial'                 => 'https://www.youtube-nocookie.com/embed/vsxLqYXWITs?rel=0&modestbranding=1&controls=1',
            'advanced_ai_defense_addon_tutorial'      => 'https://www.youtube-nocookie.com/embed/Eb4qQDafaRk?rel=0&modestbranding=1&controls=1',
            'visitor_intelligence_addon_tutorial'     => 'https://www.youtube-nocookie.com/embed/TTK8bGVD8pM?rel=0&modestbranding=1&controls=1',
            'guest_access_addon_tutorial'             => 'https://www.youtube-nocookie.com/embed/j1vYV8lhqcc?rel=0&modestbranding=1&controls=1',
            'digital_fortress_overview_tutorial'      => 'https://www.youtube-nocookie.com/embed/B6s8O9VZLc0?rel=0&modestbranding=1&controls=1',
        );

        $videos = apply_filters( 'my_private_site_tutorial_videos', $videos );
    }

    return $videos;
}

/**
 * Look up a tutorial video URL by identifier.
 *
 * @param string $id Tutorial identifier from my_private_site_get_tutorial_videos().
 * @return string Tutorial URL or empty string when unknown.
 */
function my_private_site_get_tutorial_video_url( $id ) {
    $videos = my_private_site_get_tutorial_videos();

    return isset( $videos[ $id ] ) ? $videos[ $id ] : '';
}

/**
 * Attempt to purge common page caches across popular plugins/hosts.
 *
 * This is best-effort and safe to call even if none of the plugins are installed.
 */
function my_private_site_purge_page_caches() {
    // Core object cache (not a page cache, but harmless and useful)
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    // WP Super Cache
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        // Clears the entire cache for the current blog/site
        wp_cache_clear_cache();
    }

    // W3 Total Cache
    if ( function_exists( 'w3tc_flush_all' ) ) {
        w3tc_flush_all();
    } else {
        /**
         * Some versions listen for this action.
         */
        do_action( 'w3tc_flush_all' );
    }

    // WP Rocket
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
    } else {
        do_action( 'rocket_purge_all' );
    }

    // LiteSpeed Cache
    if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
        \LiteSpeed_Cache_API::purge_all();
    }
    do_action( 'litespeed_purge_all' );

    // SiteGround Optimizer
    do_action( 'sg_cachepress_purge' );
    do_action( 'sg_cachepress_purge_cache' );

    // WP Engine
    if ( class_exists( 'WpeCommon' ) ) {
        if ( is_callable( array( 'WpeCommon', 'purge_varnish_cache' ) ) ) {
            \WpeCommon::purge_varnish_cache();
        }
        if ( is_callable( array( 'WpeCommon', 'purge_memcached' ) ) ) {
            \WpeCommon::purge_memcached();
        }
    }

    // Kinsta
    do_action( 'kinsta_cache_purge' );

    // Pantheon
    if ( function_exists( 'pantheon_wp_clear_edge_cache' ) ) {
        pantheon_wp_clear_edge_cache();
    } else {
        do_action( 'pantheon_cache_clear_all' );
    }

    // Cloudflare (official + some community plugins listen for these)
    do_action( 'cloudflare_purge_all_cache' );
    do_action( 'cloudflare_purge_cache_by_url', array( home_url( '/' ) ) );
}

/**
 * Whether NoAI/NoImageAI protection is enabled.
 *
 * @return bool
 */
function my_private_site_is_ai_noai_enabled() {
    $settings = get_option( 'jr_ps_settings' );
    return ( is_array( $settings ) && ! empty( $settings['ai_defense_noai'] ) );
}

/**
 * Output NoAI/NoImageAI meta tags in the HTML head when enabled.
 */
function my_private_site_output_noai_meta() {
    if ( is_admin() ) {
        return;
    }
    if ( ! my_private_site_is_ai_noai_enabled() ) {
        return;
    }
    echo "\n<meta name=\"robots\" content=\"noai,noimageai\">\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'wp_head', 'my_private_site_output_noai_meta', 0 );

/**
 * Send X-Robots-Tag headers for NoAI/NoImageAI when enabled.
 */
function my_private_site_output_noai_headers() {
    if ( is_admin() ) {
        return;
    }
    if ( ! my_private_site_is_ai_noai_enabled() ) {
        return;
    }
    // Do not replace existing X-Robots-Tag headers; add ours in addition.
    header( 'X-Robots-Tag: noai, noimageai', false );
}
add_action( 'send_headers', 'my_private_site_output_noai_headers' );

/**
 * Detect whether the current request is generating WordPress' virtual robots.txt.
 *
 * @return bool
 */
function my_private_site_is_virtual_robots_context() {
    if ( function_exists( 'doing_filter' ) && doing_filter( 'robots_txt' ) ) {
        return true;
    }
    if ( function_exists( 'doing_action' ) && ( doing_action( 'do_robots' ) || did_action( 'do_robots' ) ) ) {
        return true;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ( '' === $request_uri ) {
        return false;
    }

    $path = wp_parse_url( $request_uri, PHP_URL_PATH );
    if ( ! is_string( $path ) ) {
        return false;
    }

    $normalized = '/' . ltrim( $path, '/' );
    return ( '/robots.txt' === rtrim( $normalized, '/' ) );
}

/**
 * Check if fetching the site's robots.txt URL returns a 404.
 *
 * We only treat an explicit 404 as failure. Network errors or other
 * statuses are ignored for this check per request requirements.
 *
 * @param bool $force_refresh When true, bypass caches and refresh the recorded status.
 * @return bool True if robots.txt URL returns HTTP 404.
 */
function my_private_site_robots_url_is_404( $force_refresh = false ) {
    static $request_cache = array(
        'set'   => false,
        'value' => false,
    );

    $force_refresh = (bool) $force_refresh;

    if ( ! $force_refresh && $request_cache['set'] ) {
        return $request_cache['value'];
    }

    // When we are actively generating robots.txt, avoid loopback checks.
    if ( my_private_site_is_virtual_robots_context() ) {
        $request_cache = array(
            'set'   => true,
            'value' => false,
        );
        return false;
    }

    $transient_key = 'jr_ps_robots_url_status';
    if ( $force_refresh ) {
        delete_transient( $transient_key );
    }

    $cached = get_transient( $transient_key );
    if ( ! $force_refresh && is_array( $cached ) && array_key_exists( 'is_404', $cached ) ) {
        $request_cache = array(
            'set'   => true,
            'value' => (bool) $cached['is_404'],
        );

        $option_record = get_option( 'jr_ps_robots_status' );
        if ( ! is_array( $option_record ) || ! array_key_exists( 'checked', $option_record ) ) {
            update_option(
                'jr_ps_robots_status',
                array(
                    'is_404'  => $cached['is_404'] ? 1 : 0,
                    'checked' => isset( $cached['checked'] ) ? (int) $cached['checked'] : time(),
                )
            );
        }

        return $request_cache['value'];
    }

    $lock_key      = 'jr_ps_robots_url_status_lock';
    $lock_acquired = false;

    if ( get_transient( $lock_key ) && ! $force_refresh ) {
        if ( is_array( $cached ) && array_key_exists( 'is_404', $cached ) ) {
            $request_cache = array(
                'set'   => true,
                'value' => (bool) $cached['is_404'],
            );
            return $request_cache['value'];
        }
        $request_cache = array(
            'set'   => true,
            'value' => false,
        );
        return false;
    }

    $lock_acquired = set_transient( $lock_key, 1, 15 );

    $url      = home_url( '/robots.txt' );
    $timeout  = (float) apply_filters( 'my_private_site_robots_probe_timeout', 2.0 );
    $timeout  = $timeout > 0 ? $timeout : 2.0;
    $args     = array(
        'timeout'     => $timeout,
        'redirection' => 3,
        'sslverify'   => apply_filters( 'my_private_site_robots_probe_sslverify', true ),
    );
    $is_404   = false;

    try {
        // Try HEAD first (fastest), then fall back to GET if unsupported.
        $resp = wp_remote_head( $url, $args );
        if ( is_wp_error( $resp ) ) {
            $code = 0;
        } else {
            $code = (int) wp_remote_retrieve_response_code( $resp );
        }

        if ( 405 === $code || 0 === $code ) {
            $resp = wp_remote_get( $url, $args );
            if ( is_wp_error( $resp ) ) {
                $code = 0;
            } else {
                $code = (int) wp_remote_retrieve_response_code( $resp );
            }
        }

        $is_404 = ( 404 === $code );

        $cache_ttl = (int) apply_filters( 'my_private_site_robots_probe_cache_ttl', 5 * MINUTE_IN_SECONDS );
        if ( $cache_ttl < 1 ) {
            $cache_ttl = 5 * MINUTE_IN_SECONDS;
        }

        set_transient(
            $transient_key,
            array(
                'is_404'   => $is_404,
                'checked'  => time(),
            ),
            $cache_ttl
        );

        update_option(
            'jr_ps_robots_status',
            array(
                'is_404'  => $is_404 ? 1 : 0,
                'checked' => time(),
            )
        );
    } finally {
        if ( $lock_acquired ) {
            delete_transient( $lock_key );
        }
    }

    $request_cache = array(
        'set'   => true,
        'value' => $is_404,
    );

    return $is_404;
}

/**
 * Force a refresh of the cached robots.txt status.
 *
 * @return bool Latest evaluation of whether robots.txt returns 404.
 */
function my_private_site_refresh_robots_url_status_cache() {
    return my_private_site_robots_url_is_404( true );
}

/**
 * Retrieve the last recorded robots.txt diagnostic result.
 *
 * @return array|null
 */
function my_private_site_get_robots_status_record() {
    $record = get_option( 'jr_ps_robots_status' );
    if ( ! is_array( $record ) ) {
        return null;
    }

    if ( ! array_key_exists( 'is_404', $record ) || ! array_key_exists( 'checked', $record ) ) {
        return null;
    }

    $record['is_404']  = (bool) $record['is_404'];
    $record['checked'] = (int) $record['checked'];
    return $record;
}

/**
 * Format a human-readable status line for the last robots.txt diagnostic.
 *
 * @return string
 */
function my_private_site_get_robots_status_summary() {
    $record = my_private_site_get_robots_status_record();
    if ( null === $record ) {
        return '';
    }

    $timestamp = $record['checked'];
    if ( $timestamp <= 0 ) {
        return '';
    }

    $status_label = $record['is_404'] ? __( 'Last check: robots.txt response error.', 'my-private-site' ) : __( 'Last check: robots.txt file found.', 'my-private-site' );
    $time_label   = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

    return sprintf( '%1$s %2$s', $status_label, '(' . $time_label . ')' );
}

/**
 * Generate the styled "retest robots.txt" button markup for admin UI.
 *
 * @param string $context Optional context identifier used for telemetry.
 * @return string HTML markup (or empty string when not permitted).
 */
function my_private_site_get_robots_retest_button_html( $context = 'general' ) {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return '';
    }

    static $styles_output = false;
    $style_block = '';
    if ( ! $styles_output ) {
        $styles_output = true;
        $style_block   = '<style id="jrps-robots-retest-style">'
                       . '.jrps-robots-retest-wrap{margin-top:8px;padding:10px;background:#fff3f3;border:1px solid #f7d7d7;border-radius:4px;display:inline-block;}'
                       . '.jrps-robots-status{margin:0 0 6px;font-size:13px;color:#5a5a5a;}'
                       . '.jrps-robots-retest-form{margin:0;display:inline-block;}'
                       . '.jrps-robots-retest-button{background:#ffe0e0;color:#a40000;border:1px solid #a40000;padding:6px 16px;border-radius:3px;font-weight:600;cursor:pointer;}'
                       . '.jrps-robots-retest-button:hover{background:#ffd0d0;color:#7a0000;}'
                       . '.jrps-robots-retest-button:focus{outline:2px solid #a40000;outline-offset:1px;}'
                       . '</style>';
    }

    $status_summary = my_private_site_get_robots_status_summary();
    $status_html    = '';
    if ( '' !== $status_summary ) {
        $status_html = '<p class="jrps-robots-status">' . esc_html( $status_summary ) . '</p>';
    }

    $nonce = wp_create_nonce( 'my_private_site_retest_robots' );
    $form  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="jrps-robots-retest-form">'
           . '<input type="hidden" name="action" value="my_private_site_retest_robots" />'
           . '<input type="hidden" name="context" value="' . esc_attr( $context ) . '" />'
           . '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />'
           . '<button type="submit" class="jrps-robots-retest-button">' . esc_html__( "I've fixed it. Please Retest", 'my-private-site' ) . '</button>'
           . '</form>';

    return $style_block . '<div class="jrps-robots-retest-wrap">' . $status_html . $form . '</div>';
}

/**
 * Add rewrite rule and query var for license.xml so requests route into WordPress.
 *
 * This maps /license.xml -> index.php?rsl_license=1
 * We always register the rule; the actual output is gated by the AI Defense setting.
 */
function my_private_site_register_rsl_rewrite() {
    add_rewrite_rule( '^license\.xml$', 'index.php?rsl_license=1', 'top' );
}
add_action( 'init', 'my_private_site_register_rsl_rewrite' );

/**
 * Register the rsl_license query var used by the rewrite target.
 *
 * @param array $vars
 * @return array
 */
function my_private_site_register_rsl_query_var( $vars ) {
    $vars[] = 'rsl_license';
    return $vars;
}
add_filter( 'query_vars', 'my_private_site_register_rsl_query_var' );

/**
 * Early route handler for license.xml for environments without permalinks.
 *
 * This duplicates our later handlers but runs at init so that even with
 * plain permalinks or non-standard routing, the request gets handled.
 */
function my_private_site_maybe_output_license_xml_early() {
    if ( is_admin() ) {
        return;
    }
    if ( ! my_private_site_is_ai_defense_enabled() ) {
        return;
    }
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ( $request_uri === '' ) {
        return;
    }
    $path = wp_parse_url( $request_uri, PHP_URL_PATH );
    if ( ! is_string( $path ) ) {
        return;
    }
    $normalized = '/' . ltrim( $path, '/' );
    if ( $normalized !== '/license.xml' ) {
        return;
    }

    // Output RSL license XML (Prohibit AI training template).
    nocache_headers();
    header( 'Content-Type: application/xml; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    status_header( 200 );

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <prohibits type="usage">train-ai</prohibits>
    </license>
  </content>
</rsl>
XML;

    echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
}
add_action( 'init', 'my_private_site_maybe_output_license_xml_early', 0 );


function my_private_site_is_referred_by_page( $page ) {
	// takes the value of $args['option_key']) from calling function as parameter
	// this is the name of the admin page we're checking
	// good for seeing if self-referring, if user was redirected from the current page
	$referring_page = '';
	if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
		$referring_page = sanitize_text_field( $_SERVER['HTTP_REFERER'] );
	}
	$parts_list = wp_parse_url( $referring_page );

	if ( isset( $parts_list['query'] ) ) {
		$query = $parts_list['query'];
	} else {
		$query = '';
	}

	// we could split the string to parse away the page= but why bother?
	if ( $query != 'page=' . $page ) {
		return false;
	} else {
		return true;
	}
}

function my_private_site_array_size( $array ) {
	// particularly for non-countable arrays
	$count = 0;
	if ( is_array( $array ) ) {
		foreach ( $array as $value ) {
			++ $count;
		}
	}

	return $count;
}

/**
 * Backup and Restore Utilities
 */

/**
 * Get current plugin version from global.
 *
 * @return string Plugin version or empty string
 */
function my_private_site_get_core_version() {
    if ( isset( $GLOBALS['jr_ps_plugin_data']['Version'] ) ) {
        return $GLOBALS['jr_ps_plugin_data']['Version'];
    }
    return '';
}

/**
 * Collect all WordPress options starting with prefix jr_ps_.
 *
 * @return array Associative array of option_name => option_value
 */
function my_private_site_get_all_prefixed_options() {
    global $wpdb;

    $options = array();
    $like    = $wpdb->esc_like( 'jr_ps_' ) . '%';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
    if ( is_array( $names ) ) {
        foreach ( $names as $name ) {
            // Use get_option to ensure proper serialization handling
            $options[ $name ] = get_option( $name );
        }
    }

    return $options;
}

/**
 * Delete all options whose names start with jr_ps_.
 */
function my_private_site_delete_all_prefixed_options() {
    global $wpdb;
    $like  = $wpdb->esc_like( 'jr_ps_' ) . '%';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
    if ( is_array( $names ) ) {
        foreach ( $names as $name ) {
            delete_option( $name );
        }
    }
}

/**
 * Derive a secret key for encrypting backup payload content using site salts.
 *
 * @return string Binary key (32 bytes) suitable for AES-256 operations.
 */
function my_private_site_get_backup_secret_key() {
    static $cached = null;
    if ( null !== $cached ) {
        return $cached;
    }

    $parts = array();
    foreach ( array(
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ) as $constant ) {
        if ( defined( $constant ) ) {
            $parts[] = constant( $constant );
        }
    }

    $parts[] = home_url();

    $raw = implode( '|', $parts );
    if ( '' === trim( $raw ) ) {
        $raw = wp_salt( 'auth' );
    }

    $cached = hash( 'sha256', 'jrps-backup-secret|' . $raw, true );
    return $cached;
}

/**
 * Encrypt plaintext for backup export.
 *
 * @param string $plaintext Data to encrypt.
 * @return array|false Array containing iv/cipher/mac on success, false on failure.
 */
function my_private_site_encrypt_for_backup( $plaintext ) {
    if ( ! is_string( $plaintext ) ) {
        return false;
    }

    if ( ! function_exists( 'openssl_encrypt' ) ) {
        return false;
    }

    $key = my_private_site_get_backup_secret_key();
    if ( empty( $key ) || strlen( $key ) !== 32 ) {
        return false;
    }

    try {
        $iv = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
    } catch ( Exception $e ) {
        $iv = false;
    }

    if ( false === $iv || strlen( $iv ) !== 16 ) {
        return false;
    }

    $cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
    if ( false === $cipher ) {
        return false;
    }

    $mac = hash_hmac( 'sha256', $iv . $cipher, $key, true );

    return array(
        'iv'     => base64_encode( $iv ),
        'cipher' => base64_encode( $cipher ),
        'mac'    => base64_encode( $mac ),
    );
}

/**
 * Decrypt payload previously produced for backup export.
 *
 * @param array $package Array containing iv/cipher/mac values.
 * @return string|false Decrypted plaintext or false on failure.
 */
function my_private_site_decrypt_for_backup( $package ) {
    if ( ! is_array( $package ) ) {
        return false;
    }

    foreach ( array( 'iv', 'cipher', 'mac' ) as $required_key ) {
        if ( empty( $package[ $required_key ] ) || ! is_string( $package[ $required_key ] ) ) {
            return false;
        }
    }

    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return false;
    }

    $key = my_private_site_get_backup_secret_key();
    if ( empty( $key ) || strlen( $key ) !== 32 ) {
        return false;
    }

    $iv     = base64_decode( $package['iv'], true );
    $cipher = base64_decode( $package['cipher'], true );
    $mac    = base64_decode( $package['mac'], true );

    if ( false === $iv || false === $cipher || false === $mac ) {
        return false;
    }

    $calc_mac = hash_hmac( 'sha256', $iv . $cipher, $key, true );
    if ( ! hash_equals( $mac, $calc_mac ) ) {
        return false;
    }

    $plaintext = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
    if ( false === $plaintext ) {
        return false;
    }

    return $plaintext;
}

/**
 * Encrypt license catalog for inclusion in backup payload.
 *
 * @param mixed $option_value Stored license option.
 * @return mixed Encrypted representation or original value if encryption not needed.
 */
function my_private_site_encrypt_license_catalog_for_backup( $option_value ) {
    $licenses = maybe_unserialize( $option_value );
    if ( ! is_array( $licenses ) || empty( $licenses ) ) {
        return $option_value;
    }

    $json = wp_json_encode( $licenses );
    if ( false === $json ) {
        return array( '__jrps_redacted' => true );
    }

    $package = my_private_site_encrypt_for_backup( $json );
    if ( false === $package ) {
        return array( '__jrps_redacted' => true );
    }

    $package['__jrps_encrypted'] = true;
    $package['version']          = 1;
    $package['algorithm']        = 'aes-256-cbc';

    return $package;
}

/**
 * Decrypt license catalog from backup payload.
 *
 * @param array $package Encrypted structure.
 * @return array|false Array of license keys or false on failure.
 */
function my_private_site_decrypt_license_catalog_from_backup( $package ) {
    if ( ! is_array( $package ) ) {
        return false;
    }

    if ( isset( $package['__jrps_redacted'] ) ) {
        return array();
    }

    if ( empty( $package['__jrps_encrypted'] ) ) {
        return false;
    }

    $plaintext = my_private_site_decrypt_for_backup( $package );
    if ( false === $plaintext ) {
        return false;
    }

    $decoded = json_decode( $plaintext, true );
    if ( ! is_array( $decoded ) ) {
        $decoded = maybe_unserialize( $plaintext );
    }

    if ( ! is_array( $decoded ) ) {
        return false;
    }

    return $decoded;
}

/**
 * Prepare options array for secure backup export.
 *
 * @param array $options Associative array of option values.
 * @return array Sanitized options ready for JSON export.
 */
function my_private_site_prepare_options_for_backup( $options ) {
    if ( isset( $options['jr_ps_licenses'] ) ) {
        $options['jr_ps_licenses'] = my_private_site_encrypt_license_catalog_for_backup( $options['jr_ps_licenses'] );
    }

    return $options;
}

/**
 * Build a JSON string representing all plugin settings with metadata.
 *
 * @return string JSON string
 */
function my_private_site_export_settings_json() {
    $payload = array(
        'plugin'         => 'jonradio-private-site',
        'plugin_version' => my_private_site_get_core_version(),
        'exported_at'    => gmdate( 'c' ),
        'site_url'       => home_url(),
        'options'        => my_private_site_prepare_options_for_backup( my_private_site_get_all_prefixed_options() ),
    );

    // JSON_UNESCAPED_SLASHES for portability, PRETTY_PRINT for readability
    $json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    if ( false === $json ) {
        $json = '{}';
    }

    return $json;
}

/**
 * Send backup JSON to browser as a file download.
 */
function my_private_site_send_backup_download() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'my-private-site' ), 403 );
    }

    $json = my_private_site_export_settings_json();

    // Default filename: YYYY-MM-DD MPS Settings Backup.json
    $filename = gmdate( 'Y-m-d' ) . ' MPS Settings Backup.json';

    nocache_headers();
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Transfer-Encoding: binary' );

    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
}

/**
 * admin-post handler for backup download.
 */
function my_private_site_handle_backup_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'my-private-site' ), 403 );
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $nonce = isset( $_POST['jr_ps_backup_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jr_ps_backup_settings_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'jr_ps_backup_settings' ) ) {
        wp_die( 'Security violation detected [A010]. Access denied.', 'Security violation', array( 'response' => 403 ) );
    }
    my_private_site_send_backup_download();
}

add_action( 'admin_post_my_private_site_backup_settings', 'my_private_site_handle_backup_settings' );

/**
 * Validate backup payload version vs current plugin version.
 *
 * @param array $payload Decoded JSON payload
 * @return array [ bool allowed, string reason ]
 */
function my_private_site_validate_backup_version( $payload ) {
    $file_version = '';
    if ( is_array( $payload ) && isset( $payload['plugin_version'] ) ) {
        $file_version = (string) $payload['plugin_version'];
    }
    $core_version = my_private_site_get_core_version();

    if ( $file_version === '' || $core_version === '' ) {
        // If versions are missing, do not allow restore.
        return array( false, 'Backup file missing version metadata.' );
    }

    if ( version_compare( $file_version, $core_version, '>' ) ) {
        return array( false, sprintf( 'Backup was created with newer version (%s). Please update plugin (current %s).', $file_version, $core_version ) );
    }

    return array( true, '' );
}

/**
 * Apply settings from decoded backup payload.
 *
 * Only options with names starting with jr_ps_ will be written.
 * This performs an import/replace per option key present in the file.
 *
 * @param array $payload Decoded JSON payload
 * @return array [ bool success, string reason ]
 */
function my_private_site_apply_settings_backup( $payload ) {
    if ( ! is_array( $payload ) || ! isset( $payload['options'] ) || ! is_array( $payload['options'] ) ) {
        return array( false, 'Backup payload missing options.' );
    }

    $prepared_options = array();

    foreach ( $payload['options'] as $name => $value ) {
        if ( strpos( $name, 'jr_ps_' ) !== 0 ) {
            continue;
        }

        list( $ok, $prepared_value, $error ) = my_private_site_prepare_option_for_restore( $name, $value );
        if ( ! $ok ) {
            return array( false, $error );
        }

        $prepared_options[ $name ] = $prepared_value;
    }

    my_private_site_delete_all_prefixed_options();

    foreach ( $prepared_options as $name => $value ) {
        update_option( $name, $value );
    }

    return array( true, '' );
}

/**
 * Prepare a single option for restore, decrypting when necessary.
 *
 * @param string $name  Option name.
 * @param mixed  $value Option value from backup file.
 * @return array [ bool $ok, mixed $value, string $error ]
 */
function my_private_site_prepare_option_for_restore( $name, $value ) {
    if ( 'jr_ps_licenses' !== $name ) {
        return array( true, $value, '' );
    }

    // Handle encrypted payload
    if ( is_array( $value ) && isset( $value['__jrps_encrypted'] ) ) {
        $licenses = my_private_site_decrypt_license_catalog_from_backup( $value );
        if ( false === $licenses ) {
            return array( false, null, 'License data failed integrity check.' );
        }

        return array( true, $licenses, '' );
    }

    // Old-style backups stored raw array/string. Normalize to array.
    if ( is_string( $value ) ) {
        $maybe_array = maybe_unserialize( $value );
        if ( is_array( $maybe_array ) ) {
            return array( true, $maybe_array, '' );
        }
        return array( true, array(), '' );
    }

    if ( ! is_array( $value ) ) {
        return array( true, array(), '' );
    }

    return array( true, $value, '' );
}

/**
 * Handle restore posted file (admin_post action).
 */
function my_private_site_handle_restore_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'my-private-site' ), 403 );
    }

    // phpcs:ignore WordPress.Security.NonceVerification
    $nonce = isset( $_POST['jr_ps_restore_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jr_ps_restore_settings_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'jr_ps_restore_settings' ) ) {
        wp_die( 'Security violation detected [A008]. Access denied.', 'Security violation', array( 'response' => 403 ) );
    }

    $redirect_url = admin_url( 'admin.php?page=my_private_site_tab_advanced' );
    $redirect_url = add_query_arg( array( 'subtab' => 'backups' ), $redirect_url );

    if ( ! isset( $_FILES['jr_ps_restore_file'] ) || ! is_array( $_FILES['jr_ps_restore_file'] ) ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( 'No file uploaded.' ) ), $redirect_url ) );
        exit;
    }

    $file = $_FILES['jr_ps_restore_file'];
    if ( ! empty( $file['error'] ) ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( 'Upload error.' ) ), $redirect_url ) );
        exit;
    }

    $name = isset( $file['name'] ) ? (string) $file['name'] : '';
    $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, array( 'json', 'txt' ), true ) ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( 'Invalid file type. Use .json or .txt' ) ), $redirect_url ) );
        exit;
    }

    $tmp_path = $file['tmp_name'];
    $contents = file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    if ( $contents === false ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( 'Unable to read uploaded file.' ) ), $redirect_url ) );
        exit;
    }

    $payload = json_decode( $contents, true );
    if ( ! is_array( $payload ) ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( 'Invalid JSON format.' ) ), $redirect_url ) );
        exit;
    }

    list( $ok, $reason ) = my_private_site_validate_backup_version( $payload );
    if ( ! $ok ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( $reason ) ), $redirect_url ) );
        exit;
    }

    list( $apply_ok, $apply_error ) = my_private_site_apply_settings_backup( $payload );
    if ( ! $apply_ok ) {
        wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'error', 'jrps_reason' => rawurlencode( $apply_error ) ), $redirect_url ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( array( 'jrps_restore_status' => 'success' ), $redirect_url ) );
    exit;
}

// Hook restore handler
add_action( 'admin_post_my_private_site_restore_settings', 'my_private_site_handle_restore_settings' );

/**
 * Handle Reset Settings (delete all jr_ps_ options; plugin will re-init defaults).
 */
function my_private_site_handle_reset_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'my-private-site' ), 403 );
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $nonce = isset( $_POST['jr_ps_reset_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jr_ps_reset_settings_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'jr_ps_reset_settings' ) ) {
        wp_die( 'Security violation detected [A011]. Access denied.', 'Security violation', array( 'response' => 403 ) );
    }

    my_private_site_delete_all_prefixed_options();

    $redirect_url = admin_url( 'admin.php?page=my_private_site_tab_advanced' );
    $redirect_url = add_query_arg( array( 'subtab' => 'backups', 'jrps_reset_status' => 'success' ), $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}

add_action( 'admin_post_my_private_site_reset_settings', 'my_private_site_handle_reset_settings' );

/**
 * RSL: Serve a virtual license.xml at the site root.
 *
 * This outputs the “Prohibit AI training” template from
 * https://rslstandard.org/guide/getting-started when a request
 * targets `/license.xml`.
 *
 * We deliberately do not write a physical file and intercept the
 * request during template_redirect (similar to virtual robots.txt).
 */
function my_private_site_maybe_output_license_xml() {
    // Only act on front-end requests.
    if ( is_admin() ) {
        return;
    }

    // Respect AI Defense setting; only serve when enabled.
    if ( ! my_private_site_is_ai_defense_enabled() ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    $is_license  = false;
    if ( $request_uri !== '' ) {
        // Extract the path component, ignore query string.
        $path = wp_parse_url( $request_uri, PHP_URL_PATH );
        if ( is_string( $path ) ) {
            // Normalize trailing slash and compare to /license.xml
            // Support both /license.xml and license.xml (no leading slash).
            $normalized = '/' . ltrim( $path, '/' );
            if ( $normalized === '/license.xml' ) {
                $is_license = true;
            }
        }
    }

    // Also allow a query-param access for environments with unusual rewrites (debug aid).
    // Example: https://example.com/?rsl_license=1
    if ( ! $is_license && isset( $_GET['rsl_license'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_license = true;
    }

    if ( ! $is_license ) {
        return;
    }

    // Output RSL license XML (Prohibit AI training template).
    nocache_headers();
    // Be explicit about content type and charset.
    header( 'Content-Type: application/xml; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    status_header( 200 );

    // Per RSL getting started guide: Prohibit AI training template
    // Reference: https://rslstandard.org/guide/getting-started
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsl xmlns="https://rslstandard.org/rsl">
  <content url="/">
    <license>
      <prohibits type="usage">train-ai</prohibits>
    </license>
  </content>
</rsl>
XML;

    echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
}

// Hook late in the front-end lifecycle so we can short-circuit with our response before templates load.
add_action( 'template_redirect', 'my_private_site_maybe_output_license_xml', 0 );
// Some hosting environments/plugins interfere with template_redirect; also hook get_header as a fallback.
add_action( 'get_header', 'my_private_site_maybe_output_license_xml', 0 );

/**
 * Append the RSL License link to virtual robots.txt.
 *
 * Per RSL getting started guide (Step 2), add a line:
 *   License: https://your-website.com/license.xml
 *
 * This only affects WordPress' virtual robots.txt. If a physical
 * robots.txt exists at the web root, WordPress will not serve the
 * virtual file and this filter will have no effect.
 *
 * @param string $output Current robots.txt contents.
 * @param bool   $public Whether the site is public per blog_public.
 * @return string Modified robots.txt contents with License line.
 */
function my_private_site_add_license_to_robots( $output, $public ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    // Respect AI Defense setting; only modify robots.txt when enabled.
    if ( ! my_private_site_is_ai_defense_enabled() ) {
        return $output;
    }

    $license_url = home_url( '/license.xml' );

    // Avoid duplicate lines if another plugin already added it.
    $already_present = ( false !== stripos( $output, 'License:' ) ) && ( false !== stripos( $output, $license_url ) );

    if ( ! $already_present ) {
        // Ensure output ends with a single newline, then append.
        if ( $output !== '' && substr( $output, -1 ) !== "\n" ) {
            $output .= "\n";
        }
        $output .= 'License: ' . $license_url . "\n";
    }

    return $output;
}

add_filter( 'robots_txt', 'my_private_site_add_license_to_robots', 20, 2 );

/**
 * Append GPTBot block to robots.txt when enabled and robots controls are allowed.
 *
 * Adds lines:
 *   User-agent: GPTBot
 *   Disallow: /
 */
function my_private_site_add_gptbot_block_to_robots( $output, $public ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    // Only modify robots.txt when robots controls are allowed (no physical/404)
    $robots_allowed = ( ! my_private_site_physical_robots_exists() ) && ( ! my_private_site_robots_url_is_404() );
    if ( ! $robots_allowed ) {
        return $output;
    }
    $settings = get_option( 'jr_ps_settings' );
    if ( empty( $settings['ai_defense_gptbot_block'] ) ) {
        return $output;
    }

    // Avoid duplicating if already present
    if ( stripos( $output, 'User-agent: GPTBot' ) === false ) {
        if ( $output !== '' && substr( $output, -1 ) !== "\n" ) {
            $output .= "\n";
        }
        $output .= "User-agent: GPTBot\nDisallow: /\n";
    }

    return $output;
}
add_filter( 'robots_txt', 'my_private_site_add_gptbot_block_to_robots', 20, 2 );
