<?php
/*
Plugin Name: My Private Site
Plugin URI: http://zatzlabs.com/plugins/
Description: Make your WordPress site private with one click for family, projects, or teams. Protection for content, login, and registration.
Version: 4.1.0
Author: David Gewirtz
Author URI: http://zatzlabs.com/plugins/
License: GPLv2
*/

/*
  Copyright 2014-2025  David Gewirtz (email : info@zatz.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Security violation detected. Access denied. Codes up to [A008].

/*
  Exit if .php file accessed directly
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'JR_PS_PLUGIN_VERSION' ) ) {
	define( 'JR_PS_PLUGIN_VERSION', '4.1.0' );
}

if ( ! defined( 'JR_PS_PLUGIN_NAME' ) ) {
	define( 'JR_PS_PLUGIN_NAME', 'My Private Site' );
}

global $jr_ps_path;
$jr_ps_path = plugin_dir_path( __FILE__ );
/**
 * Return Plugin's full directory path with trailing slash
 *
 * Local XAMPP install might return:
 *    C:\xampp\htdocs\wpbeta\wp-content\plugins\jonradio-private-site/
 */
function jr_ps_path() {
	global $jr_ps_path;

	return $jr_ps_path;
}

global $jr_ps_plugin_basename;
$jr_ps_plugin_basename = plugin_basename( __FILE__ );
/**
 * Return Plugin's Basename
 *
 * For this plugin, it would be:
 *    jonradio-multiple-themes/jonradio-multiple-themes.php
 */
function jr_ps_plugin_basename() {
	global $jr_ps_plugin_basename;

	return $jr_ps_plugin_basename;
}

if ( ! function_exists( 'jr_ps_profiler_mark' ) ) {
	function jr_ps_profiler_mark( $label ) {
		if ( function_exists( 'mps_startup_profiler_mark' ) ) {
			mps_startup_profiler_mark( $label );
		}
	}
}

global $jr_ps_plugin_data;
$jr_ps_plugin_data = array(
	'Name'    => JR_PS_PLUGIN_NAME,
	'Version' => JR_PS_PLUGIN_VERSION,
	'slug'    => basename( dirname( __FILE__ ) ),
);

jr_ps_profiler_mark( 'mps-core:plugin-data' );

/*
  Detect initial activation or a change in plugin's Version number

	Sometimes special processing is required when the plugin is updated to a new version of the plugin.
	Also used in place of standard activation and new site creation exits provided by WordPress.
	Once that is complete, update the Version number in the plugin's Network-wide settings.
*/

if ( ( false === ( $internal_settings = get_option( 'jr_ps_internal_settings' ) ) )
	|| empty( $internal_settings['version'] ) ) {
	/*
	  Plugin is either:
		- updated from a version so old that Version was not yet stored in the plugin's settings, or
		- first use after install:
			- first time ever installed, or
			- installed previously and properly uninstalled (data deleted)
	*/

	$old_version = '0.1';
} else {
	$old_version = $internal_settings['version'];
}

if ( version_compare( $old_version, $jr_ps_plugin_data['Version'], '!=' ) ) {
	/*
	  Create, if internal settings do not exist; update if they do exist
	*/
	$internal_settings['version'] = $jr_ps_plugin_data['Version'];
	if ( version_compare( $old_version, '2', '<' ) ) {
		/*
		  Previous versions turned Privacy on at Activation;
			Now it is a Setting on the Settings page,
			so warn Admin.
		*/
		$internal_settings['warning_privacy'] = true;
	}
	update_option( 'jr_ps_internal_settings', $internal_settings );
}

jr_ps_profiler_mark( 'mps-core:version-sync' );

require_once jr_ps_path() . 'includes/common-functions.php';

jr_ps_profiler_mark( 'mps-core:common-functions' );

require_once jr_ps_path() . 'util/utilities.php';

jr_ps_profiler_mark( 'mps-core:utilities' );

// Legacy compatibility helpers are needed by add-ons on both admin and public requests.
require_once jr_ps_path() . 'legacy/legacy.php';

// this is the complement of the activation hook which we don't have
// would be for going to the welcome page
register_deactivation_hook( __FILE__, 'jr_ps_deactivation' );
function jr_ps_deactivation() {
	delete_option( 'jr_ps_activated' );
}

jr_ps_init_settings(
	'jr_ps_settings',
	array(
		'private_site'        => false,
		'reveal_registration' => true,
		'landing'             => 'return',
		'specific_url'        => '',
		'wplogin_php'         => false,
		'custom_login'        => false,
		'login_url'           => '',
		'custom_login_onsite' => true,
		'excl_url'            => array(),
		'excl_url_prefix'     => array(),
		'excl_url_reverse'    => false,
		'excl_home'           => false,
		'check_role'          => true,
		'override_omit'       => false,
		'hide_admin_bar'      => false,
		'registration_spam_guard_checks'   => array(),
		'recaptcha_login_guard_enabled'    => false,
		'recaptcha_login_guard_site_key'   => '',
		'recaptcha_login_guard_secret_key' => '',
	),
	array( 'user_submenu' )
);
jr_ps_profiler_mark( 'mps-core:init-settings' );
$settings = get_option( 'jr_ps_settings' );
jr_ps_profiler_mark( 'mps-core:settings-loaded' );

/**
 * Maybe hide the admin bar based on plugin settings.
 */
function jr_ps_maybe_hide_admin_bar() {
	$settings = get_option( 'jr_ps_settings' );
	if ( ! empty( $settings['hide_admin_bar'] ) && is_user_logged_in() && ! is_admin() ) {
		show_admin_bar( false );
	}
}

/**
 * Privacy shortcode handler.
 *
 * Mirrors the legacy behaviour from the admin bootstrap but lives here so
 * front-end requests no longer need to load the full admin stack.
 *
 * @param array       $atts    Shortcode attributes.
 * @param string|null $content Wrapped content.
 *
 * @return string Shortcode output.
 */
function my_private_site_shortcode( $atts, $content = null ) {
	if ( isset( $atts['hide-if'] ) ) {
		$condition_to_check = strtolower( $atts['hide-if'] );
		switch ( $condition_to_check ) {
			case 'logged-in':
				if ( is_user_logged_in() ) {
					$content = '';
				}
				break;
			case 'logged-out':
				if ( ! is_user_logged_in() ) {
					$content = '';
				}
				break;
		}
	}

	return $content;
}

add_shortcode( 'privacy', 'my_private_site_shortcode' );
jr_ps_profiler_mark( 'mps-core:shortcode-registered' );

/**
 * Check whether reCAPTCHA Login Guard is enabled and configured.
 *
 * @return array{enabled:bool,ready:bool,site_key:string,secret_key:string}
 */
function my_private_site_recaptcha_login_guard_settings() {
	$settings = get_option( 'jr_ps_settings' );
	$enabled  = ! empty( $settings['recaptcha_login_guard_enabled'] );
	$site_key = isset( $settings['recaptcha_login_guard_site_key'] ) ? trim( $settings['recaptcha_login_guard_site_key'] ) : '';
	$secret   = isset( $settings['recaptcha_login_guard_secret_key'] ) ? trim( $settings['recaptcha_login_guard_secret_key'] ) : '';
	$ready    = ( $enabled && $site_key !== '' && $secret !== '' );

	return array(
		'enabled'    => $enabled,
		'ready'      => $ready,
		'site_key'   => $site_key,
		'secret_key' => $secret,
	);
}

/**
 * Render reCAPTCHA widget on the WordPress login form when enabled.
 */
function my_private_site_recaptcha_login_guard_render() {
	$recaptcha = my_private_site_recaptcha_login_guard_settings();
	if ( ! $recaptcha['ready'] ) {
		return;
	}

	echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $recaptcha['site_key'] ) . '"></div>';
	echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Validate reCAPTCHA on login when enabled and configured.
 *
 * @param WP_User|WP_Error $user User object from authentication.
 * @return WP_User|WP_Error
 */
function my_private_site_recaptcha_login_guard_validate( $user ) {
	if ( is_wp_error( $user ) ) {
		return $user;
	}

	$recaptcha = my_private_site_recaptcha_login_guard_settings();
	if ( ! $recaptcha['ready'] ) {
		return $user;
	}

	$captcha_token = isset( $_POST['g-recaptcha-response'] )
		? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) )
		: '';

	if ( '' === $captcha_token ) {
		return new WP_Error( 'recaptcha', __( 'Please complete the reCAPTCHA.', 'my-private-site' ) );
	}

	$hash          = md5( $captcha_token );
	$transient_key = 'jr_ps_recaptcha_checked_' . $hash;
	if ( get_transient( $transient_key ) ) {
		return $user;
	}

	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 5,
			'body'    => array(
				'secret'   => $recaptcha['secret_key'],
				'response' => $captcha_token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'recaptcha', __( 'reCAPTCHA verification failed. Please try again.', 'my-private-site' ) );
	}

	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $decoded ) ) {
		return new WP_Error( 'recaptcha', __( 'reCAPTCHA verification failed. Please try again.', 'my-private-site' ) );
	}

	if ( empty( $decoded['success'] ) ) {
		return new WP_Error( 'recaptcha', __( 'reCAPTCHA failed. Please try again.', 'my-private-site' ) );
	}

	set_transient( $transient_key, true, 60 );

	return $user;
}

add_action( 'login_form', 'my_private_site_recaptcha_login_guard_render' );
add_filter( 'wp_authenticate_user', 'my_private_site_recaptcha_login_guard_validate', 11, 2 );

/**
 * Return enabled registration spam guard checks.
 *
 * @return array
 */
function my_private_site_spam_guard_enabled_checks() {
	$settings = get_option( 'jr_ps_settings' );
	if ( ! is_array( $settings ) || empty( $settings['registration_spam_guard_checks'] ) || ! is_array( $settings['registration_spam_guard_checks'] ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( $settings['registration_spam_guard_checks'] ) ) );
}

/**
 * Check if a specific spam guard check is enabled.
 *
 * @param string $check
 * @return bool
 */
function my_private_site_spam_guard_is_enabled( $check ) {
	return in_array( $check, my_private_site_spam_guard_enabled_checks(), true );
}

/**
 * Inject hidden honeypot field on the registration form.
 */
function my_private_site_spam_guard_add_honeypot() {
	if ( ! my_private_site_spam_guard_is_enabled( 'honeypot' ) ) {
		return;
	}

	echo '<input type="text" name="confirm_email" value="" style="display:none" autocomplete="off">';
}
add_action( 'register_form', 'my_private_site_spam_guard_add_honeypot' );

/**
 * Validate registration for spam.
 *
 * @param WP_Error $errors
 * @param string   $sanitized_user_login
 * @param string   $user_email
 * @return WP_Error
 */
function my_private_site_spam_guard_validate_registration( $errors, $sanitized_user_login, $user_email ) {
	$enabled_checks = my_private_site_spam_guard_enabled_checks();
	if ( empty( $enabled_checks ) ) {
		return $errors;
	}

	$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$reason = '';

	if ( my_private_site_spam_guard_is_enabled( 'honeypot' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$honeypot_value = isset( $_POST['confirm_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['confirm_email'] ) ) ) : '';
		if ( '' !== $honeypot_value ) {
			$reason = 'Honeypot field filled';
		}
	}

	if ( '' === $reason && my_private_site_spam_guard_is_enabled( 'gibberish_username' ) ) {
		if ( my_private_site_spam_guard_is_gibberish_username( $sanitized_user_login ) ) {
			$reason = 'Excessively long gibberish username';
		}
	}

	if ( '' === $reason && my_private_site_spam_guard_is_enabled( 'excessive_dots' ) ) {
		if ( my_private_site_spam_guard_has_excessive_dots( $user_email ) ) {
			$reason = 'Excessive dots in email address';
		}
	}

	if ( '' === $reason && my_private_site_spam_guard_is_enabled( 'missing_mx' ) ) {
		if ( my_private_site_spam_guard_missing_mx_record( $user_email ) ) {
			$reason = 'Missing MX records';
		}
	}

	if ( '' === $reason && my_private_site_spam_guard_is_enabled( 'stop_forum_spam' ) ) {
		if ( my_private_site_spam_guard_check_stop_forum_spam( $ip, $user_email ) ) {
			$reason = 'StopForumSpam match';
		}
	}

	if ( '' !== $reason ) {
		$errors->add(
			'jr_ps_spam_guard_blocked',
			__( '<strong>Error</strong>: Registration blocked as suspected spam.', 'my-private-site' )
		);
		my_private_site_spam_guard_log( $sanitized_user_login, $user_email, $ip, $reason );
	}

	return $errors;
}
add_filter( 'registration_errors', 'my_private_site_spam_guard_validate_registration', 10, 3 );

/**
 * Determine if a username appears to be gibberish.
 *
 * @param string $username
 * @return bool
 */
function my_private_site_spam_guard_is_gibberish_username( $username ) {
	$len = strlen( $username );
	if ( $len < 16 ) {
		return false;
	}

	$transitions = 0;
	for ( $i = 1; $i < $len; $i++ ) {
		$prev_upper = ctype_upper( $username[ $i - 1 ] );
		$curr_upper = ctype_upper( $username[ $i ] );
		$prev_alpha = ctype_alpha( $username[ $i - 1 ] );
		$curr_alpha = ctype_alpha( $username[ $i ] );

		if ( $prev_alpha && $curr_alpha && $prev_upper !== $curr_upper ) {
			$transitions++;
		}
	}

	$transition_ratio = $transitions / ( $len - 1 );
	if ( $transition_ratio < 0.35 ) {
		return false;
	}

	$vowels      = preg_match_all( '/[aeiouAEIOU]/', $username );
	$vowel_ratio = $vowels / $len;

	if ( $transition_ratio > 0.4 && $vowel_ratio < 0.25 ) {
		return true;
	}

	if ( $len >= 20 && $transition_ratio > 0.45 ) {
		return true;
	}

	return false;
}

/**
 * Check if email has excessive dots in the local part.
 *
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_has_excessive_dots( $email ) {
	$at_pos = strrpos( $email, '@' );
	if ( false === $at_pos ) {
		return false;
	}

	$local_part = substr( $email, 0, $at_pos );
	$dot_count  = substr_count( $local_part, '.' );

	return ( $dot_count >= 6 );
}

/**
 * Determine if the email domain is missing MX records.
 *
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_missing_mx_record( $email ) {
	$domain = substr( strrchr( $email, '@' ), 1 );
	if ( ! $domain ) {
		return true;
	}
	if ( ! function_exists( 'checkdnsrr' ) ) {
		return false;
	}

	return ! checkdnsrr( $domain, 'MX' );
}

/**
 * Query StopForumSpam API.
 *
 * @param string $ip
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_check_stop_forum_spam( $ip, $email ) {
	$cache_key = 'jr_ps_sfs_' . md5( $ip . $email );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	$url  = add_query_arg(
		array(
			'json'  => '',
			'ip'    => rawurlencode( $ip ),
			'email' => rawurlencode( $email ),
		),
		'https://api.stopforumspam.org/api'
	);
	$resp = wp_remote_get(
		$url,
		array(
			'timeout' => 5,
		)
	);

	$is_spam = false;
	if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $body['ip']['confidence'] ) && $body['ip']['confidence'] >= 50 ) {
			$is_spam = true;
		} elseif ( isset( $body['email']['confidence'] ) && $body['email']['confidence'] >= 50 ) {
			$is_spam = true;
		}
	}

	set_transient( $cache_key, $is_spam ? 1 : 0, DAY_IN_SECONDS );

	return $is_spam;
}

/**
 * Log blocked registration attempts.
 *
 * @param string $login
 * @param string $email
 * @param string $ip
 * @param string $reason
 */
function my_private_site_spam_guard_log( $login, $email, $ip, $reason ) {
	$log = get_option( 'jr_ps_spam_guard_log' );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$entry = array(
		'time'   => current_time( 'mysql' ),
		'login'  => (string) $login,
		'email'  => (string) $email,
		'ip'     => (string) $ip,
		'reason' => (string) $reason,
	);

	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, 20 );

	update_option( 'jr_ps_spam_guard_log', $log );
}

if ( is_admin() ) {
	jr_ps_profiler_mark( 'mps-admin:bootstrap-start' );
	require_once jr_ps_path() . 'jonradio-private-site-admin.php';
	jr_ps_profiler_mark( 'mps-admin:admin-file-loaded' );
	require_once jr_ps_path() . 'includes/all-admin.php';
	jr_ps_profiler_mark( 'mps-admin:all-admin-loaded' );
	/*
	  Support WordPress Version 3.0.x before is_network_admin() existed
	*/
	if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
		// Network Admin pages in Network/Multisite install
		if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( jr_ps_plugin_basename() ) ) {
			// Network Admin Settings page for Plugin
			require_once jr_ps_path() . 'includes/net-settings.php';
			jr_ps_profiler_mark( 'mps-admin:network-settings-loaded' );
		}
	} else {
		// Regular (non-Network) Admin pages
		// Settings page for Plugin
		my_private_site_init();
		jr_ps_profiler_mark( 'mps-admin:init-complete' );
	}
	// All changes to all Admin-Installed Plugins pages
	require_once jr_ps_path() . 'includes/installed-plugins.php';
	jr_ps_profiler_mark( 'mps-admin:installed-plugins-loaded' );
} else {
	jr_ps_profiler_mark( 'mps-public:bootstrap-start' );
	/*
	  Public WordPress content, i.e. - not Admin pages
		Do nothing if Private Site setting not set by Administrator
	*/
	if ( $settings['private_site'] ) {
		// Private Site code
		require_once jr_ps_path() . 'includes/public.php';
		jr_ps_profiler_mark( 'mps-public:public-module-loaded' );
	}
	add_action( 'wp_loaded', 'jr_ps_maybe_hide_admin_bar' );
	jr_ps_profiler_mark( 'mps-public:bootstrap-complete' );
}

/**
 * Check for missing Settings and set them to defaults
 *
 * Ensures that the Named Setting exists, and populates it with defaults for any missing values.
 * Safe to use on every execution of a plugin because it only does an expensive Database Write
 * when it finds missing Settings.
 *
 * @param string $name    Name of Settings as looked up with get_option()
 * @param array  $defaults Each default Settings value in [key] => value format
 * @param array  $deletes  Each old Settings value to delete as [0] => key format
 *
 * @return  bool/Null            Return value from update_option(), or NULL if update_option() not called
 */
function jr_ps_init_settings( $name, $defaults, $deletes = array() ) {
	$updated = false;
	if ( false === ( $settings = get_option( $name ) ) ) {
		$settings = $defaults;
		$updated  = true;
	} else {
		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
				$updated          = true;
			}
		}
		foreach ( $deletes as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				/*
				  Don't need to check to UNSET,
					but do need to know to set $updated
				*/
				unset( $settings[ $key ] );
				$updated = true;
			}
		}
	}
	if ( $updated ) {
		$return = update_option( $name, $settings );
	} else {
		$return = null;
	}

	return $return;
}

/*
  Documentation of Research Done for this Plugin:
	Registration URL (based on a root install in http://localhost):
	WordPress 3.6.1 without jonradio Private Site installed
	Single Site - not a network
		http://localhost/wp-login.php?action=register
	Primary Site of a Network
		http://localhost/wp-signup.php
	Secondary Site of a Network
		http://localhost/wp-signup.php
	This last URL needs a lot of thought because it means that what begins on one site ends up on another.

	WordPress 3.7-beta without jonradio Private Site installed
	Single Site - not a network
		http://localhost/wp-login.php?action=register
	Primary Site of a Network
		http://localhost/wp-signup.php
	Secondary Site of a Network
		http://localhost/wp-signup.php

	WordPress 3.0.0 without jonradio Private Site installed
	Single Site - not a network
		http://localhost/wp-login.php?action=register
	Primary Site of a Network
		http://localhost/wp-signup.php
	Secondary Site of a Network
		http://localhost/wp-signup.php

	wp_registration_url() was not available prior to WordPress Version 3.6.0

	Self-Registration allows potential Users to Register their own ID and Password without Administrator intervention or knowledge.
	It is controlled by:
		get_option( 'users_can_register' ) - non-Network
			'1' - allows Self-Registration
			'0' - no Self-Registration
		get_site_option( 'registration' ) - Network (Multisite)
			'user' - allows Self-Registration
			'none' - no Self-Registration
			'blog' - Users can create new Sites in a Network
			'all' - allows Self-Registration and the creation of new Sites in a Network
*/
