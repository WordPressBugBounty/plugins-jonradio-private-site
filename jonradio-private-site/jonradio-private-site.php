<?php
/*
Plugin Name: My Private Site
Plugin URI: http://zatzlabs.com/plugins/
Description: Make your WordPress site private with one click for family, projects, or teams. Protection for content, login, registration, and spam account cleanup.
Version: 4.2
Requires at least: 5.8
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
	define( 'JR_PS_PLUGIN_VERSION', '4.2' );
}

if ( ! defined( 'JR_PS_PLUGIN_NAME' ) ) {
	define( 'JR_PS_PLUGIN_NAME', 'My Private Site' );
}

if ( ! defined( 'JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_MIN_LENGTH' ) ) {
	define( 'JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_MIN_LENGTH', 8 );
	define( 'JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_RATIO', 0.5 );
	define( 'JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_RUN', 6 );
	define( 'JR_PS_SPAM_EMAIL_CONSONANT_RUN', 6 );
	define( 'JR_PS_SPAM_EMAIL_CONSONANT_DISTINCT', 3 );
	define( 'JR_PS_SPAM_EMAIL_CONSONANT_MAX_REPEAT_RATIO', 0.5 );
	define( 'JR_PS_SPAM_EMAIL_LOW_VOWEL_MIN_LETTERS', 12 );
	define( 'JR_PS_SPAM_EMAIL_LOW_VOWEL_RATIO', 0.18 );
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
		'recaptcha_registration_guard_enabled' => false,
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
 * Check whether reCAPTCHA Guard is enabled and configured for a context.
 *
 * @param string $context Guard context: login or registration.
 * @return array{enabled:bool,ready:bool,site_key:string,secret_key:string}
 */
function my_private_site_recaptcha_guard_settings( $context ) {
	$settings = get_option( 'jr_ps_settings' );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	if ( 'registration' === $context ) {
		$enabled = ! empty( $settings['recaptcha_registration_guard_enabled'] );
	} else {
		$enabled = ! empty( $settings['recaptcha_login_guard_enabled'] );
	}

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
 * Check whether reCAPTCHA Login Guard is enabled and configured.
 *
 * @return array{enabled:bool,ready:bool,site_key:string,secret_key:string}
 */
function my_private_site_recaptcha_login_guard_settings() {
	return my_private_site_recaptcha_guard_settings( 'login' );
}

/**
 * Check whether reCAPTCHA Registration Guard is enabled and configured.
 *
 * @return array{enabled:bool,ready:bool,site_key:string,secret_key:string}
 */
function my_private_site_recaptcha_registration_guard_settings() {
	return my_private_site_recaptcha_guard_settings( 'registration' );
}

/**
 * Render a reCAPTCHA widget.
 *
 * @param array $recaptcha reCAPTCHA settings.
 */
function my_private_site_recaptcha_guard_render_widget( $recaptcha ) {
	echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $recaptcha['site_key'] ) . '"></div>';
	echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Render reCAPTCHA widget on the WordPress login form when enabled.
 */
function my_private_site_recaptcha_login_guard_render() {
	$recaptcha = my_private_site_recaptcha_login_guard_settings();
	if ( ! $recaptcha['ready'] ) {
		return;
	}

	my_private_site_recaptcha_guard_render_widget( $recaptcha );
}

/**
 * Render reCAPTCHA widget on the WordPress registration form when enabled.
 */
function my_private_site_recaptcha_registration_guard_render() {
	$recaptcha = my_private_site_recaptcha_registration_guard_settings();
	if ( ! $recaptcha['ready'] ) {
		return;
	}

	my_private_site_recaptcha_guard_render_widget( $recaptcha );
}

/**
 * Get the submitted reCAPTCHA token.
 *
 * @return string
 */
function my_private_site_recaptcha_guard_submitted_token() {
	// phpcs:ignore WordPress.Security.NonceVerification
	return isset( $_POST['g-recaptcha-response'] )
		// phpcs:ignore WordPress.Security.NonceVerification
		? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) )
		: '';
}

/**
 * Mark a successful reCAPTCHA verification for the current request.
 *
 * @param string $context Guard context.
 */
function my_private_site_recaptcha_guard_mark_request_verified( $context ) {
	$GLOBALS['my_private_site_recaptcha_verified_' . $context] = true;
}

/**
 * Check if reCAPTCHA has been verified during the current request.
 *
 * @param string $context Guard context.
 * @return bool
 */
function my_private_site_recaptcha_guard_request_verified( $context ) {
	return ! empty( $GLOBALS['my_private_site_recaptcha_verified_' . $context] );
}

/**
 * Verify a submitted reCAPTCHA response.
 *
 * @param array  $recaptcha reCAPTCHA settings.
 * @param string $context Guard context.
 * @return true|WP_Error
 */
function my_private_site_recaptcha_guard_verify( $recaptcha, $context ) {
	if ( my_private_site_recaptcha_guard_request_verified( $context ) ) {
		return true;
	}

	$captcha_token = my_private_site_recaptcha_guard_submitted_token();
	if ( '' === $captcha_token ) {
		return new WP_Error( 'recaptcha', __( 'Please complete the reCAPTCHA.', 'my-private-site' ) );
	}

	$hash          = md5( $context . '|' . $captcha_token );
	$transient_key = 'jr_ps_recaptcha_checked_' . $hash;
	if ( get_transient( $transient_key ) ) {
		my_private_site_recaptcha_guard_mark_request_verified( $context );
		return true;
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
	my_private_site_recaptcha_guard_mark_request_verified( $context );

	return true;
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

	$verification = my_private_site_recaptcha_guard_verify( $recaptcha, 'login' );
	if ( is_wp_error( $verification ) ) {
		return $verification;
	}

	return $user;
}

add_action( 'login_form', 'my_private_site_recaptcha_login_guard_render' );
add_action( 'register_form', 'my_private_site_recaptcha_registration_guard_render' );
add_filter( 'wp_authenticate_user', 'my_private_site_recaptcha_login_guard_validate', 11, 2 );

/**
 * Determine whether this request is a core WordPress registration submission.
 *
 * @return bool
 */
function my_private_site_is_core_registration_submission() {
	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( 'POST' !== $request_method || is_user_logged_in() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 'register' !== $action ) {
		return false;
	}

	$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
	$php_self    = isset( $_SERVER['PHP_SELF'] ) ? wp_basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) : '';
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	return ( 'wp-login.php' === $script_name || 'wp-login.php' === $php_self || false !== strpos( $request_uri, 'wp-login.php' ) );
}

/**
 * Determine whether a user insert is coming from an unauthenticated public entry point.
 *
 * This deliberately includes logged-out REST, XML-RPC, and admin-ajax requests,
 * but excludes admin screens, WP-CLI, imports, cron, and logged-in users.
 *
 * @return bool
 */
function my_private_site_is_public_user_insert_request() {
	if ( is_user_logged_in() ) {
		return false;
	}

	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || wp_doing_cron() ) {
		return false;
	}

	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}

	/**
	 * Filter whether Registration Spam Guard backstop checks should run for
	 * unauthenticated wp_insert_user() calls outside the core registration form.
	 *
	 * @param bool $enabled Whether to run the public insert backstop.
	 */
	return (bool) apply_filters( 'my_private_site_spam_guard_public_user_insert_backstop', true );
}

/**
 * Return the submitted username when available.
 *
 * @param string $fallback Fallback username.
 * @return string
 */
function my_private_site_spam_guard_submitted_username( $fallback ) {
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $_POST['user_login'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		return trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) );
	}

	return $fallback;
}

/**
 * Return the first spam-block reason for a registration/user insert.
 *
 * @param string $username Username.
 * @param string $email Email address.
 * @param string $ip IP address.
 * @param string $submitted_username Submitted username, when available.
 * @param bool   $include_form_checks Whether to check form-only fields.
 * @param bool   $include_recaptcha Whether to enforce reCAPTCHA.
 * @return string
 */
function my_private_site_spam_guard_registration_block_reason( $username, $email, $ip, $submitted_username, $include_form_checks = true, $include_recaptcha = true ) {
	if ( $include_form_checks && my_private_site_spam_guard_is_enabled( 'honeypot' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$honeypot_value = isset( $_POST['confirm_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['confirm_email'] ) ) ) : '';
		if ( '' !== $honeypot_value ) {
			return 'Honeypot field filled';
		}
	}

	$recaptcha = my_private_site_recaptcha_registration_guard_settings();
	if ( $include_recaptcha && $recaptcha['enabled'] ) {
		if ( ! $recaptcha['ready'] || '' === my_private_site_recaptcha_guard_submitted_token() ) {
			return 'reCAPTCHA failed';
		}
	}

	if ( my_private_site_spam_guard_is_enabled( 'gibberish_username' ) && my_private_site_spam_guard_signal_matches( 'gibberish_username', $username, $email, $ip, $submitted_username ) ) {
		return 'Excessively long gibberish username';
	}

	if ( my_private_site_spam_guard_is_enabled( 'reserved_username' ) && my_private_site_spam_guard_signal_matches( 'reserved_username', $username, $email, $ip, $submitted_username ) ) {
		return 'Reserved administrator username';
	}

	if ( my_private_site_spam_guard_is_enabled( 'url_like_username' ) && my_private_site_spam_guard_signal_matches( 'url_like_username', $username, $email, $ip, $submitted_username ) ) {
		return 'URL-like username';
	}

	if ( my_private_site_spam_guard_is_enabled( 'spam_phrase_username' ) && my_private_site_spam_guard_signal_matches( 'spam_phrase_username', $username, $email, $ip, $submitted_username ) ) {
		return 'Spam phrase username';
	}

	if ( my_private_site_spam_guard_is_enabled( 'excessive_dots' ) && my_private_site_spam_guard_signal_matches( 'excessive_dots', $username, $email, $ip, $submitted_username ) ) {
		return 'Excessive dots in email address';
	}

	if ( my_private_site_spam_guard_is_enabled( 'gmail_plus_alias' ) && my_private_site_spam_guard_signal_matches( 'gmail_plus_alias', $username, $email, $ip, $submitted_username ) ) {
		return 'Suspicious Gmail plus alias';
	}

	if ( my_private_site_spam_guard_is_enabled( 'email_digit_dominant' ) && my_private_site_spam_guard_signal_matches( 'email_digit_dominant', $username, $email, $ip, $submitted_username ) ) {
		return 'Digit-dominant email address';
	}

	if ( my_private_site_spam_guard_is_enabled( 'email_gibberish' ) && my_private_site_spam_guard_email_is_consonant_soup( $email ) ) {
		return 'Gibberish email address (consonant run)';
	}

	if ( my_private_site_spam_guard_is_enabled( 'email_gibberish' ) && my_private_site_spam_guard_email_is_low_vowel( $email ) ) {
		return 'Gibberish email address (low vowels)';
	}

	if ( my_private_site_spam_guard_is_enabled( 'missing_mx' ) && my_private_site_spam_guard_signal_matches( 'missing_mx', $username, $email, $ip, $submitted_username ) ) {
		return 'Missing MX records';
	}

	if ( my_private_site_spam_guard_is_enabled( 'disposable_email_domain' ) && my_private_site_spam_guard_signal_matches( 'disposable_email_domain', $username, $email, $ip, $submitted_username ) ) {
		return 'Disposable email domain';
	}

	if ( $include_recaptcha && $recaptcha['ready'] ) {
		$recaptcha_result = my_private_site_recaptcha_guard_verify( $recaptcha, 'registration' );
		if ( is_wp_error( $recaptcha_result ) ) {
			return 'reCAPTCHA failed';
		}
	}

	if ( my_private_site_spam_guard_is_enabled( 'stop_forum_spam' ) && my_private_site_spam_guard_signal_matches( 'stop_forum_spam', $username, $email, $ip, $submitted_username ) ) {
		return 'StopForumSpam match';
	}

	return '';
}

/**
 * Backstop spam checks for public user inserts.
 *
 * @param array    $data User data being inserted.
 * @param bool     $update Whether this is an update.
 * @param int|null $user_id User ID for updates.
 * @param array    $userdata Raw user data passed to wp_insert_user().
 * @return array
 */
function my_private_site_recaptcha_registration_guard_pre_insert_user_data( $data, $update, $user_id = null, $userdata = array() ) {
	unset( $user_id );

	if ( $update ) {
		return $data;
	}

	$is_core_registration = my_private_site_is_core_registration_submission();
	if ( ! $is_core_registration && ! my_private_site_is_public_user_insert_request() ) {
		return $data;
	}

	$enabled_checks = my_private_site_spam_guard_enabled_checks();
	$recaptcha      = my_private_site_recaptcha_registration_guard_settings();
	if ( empty( $enabled_checks ) && ( ! $is_core_registration || ! $recaptcha['enabled'] ) ) {
		return $data;
	}

	$login = isset( $data['user_login'] ) ? $data['user_login'] : '';
	if ( '' === $login && isset( $userdata['user_login'] ) ) {
		$login = $userdata['user_login'];
	}
	$email = isset( $data['user_email'] ) ? $data['user_email'] : '';
	if ( '' === $email && isset( $userdata['user_email'] ) ) {
		$email = $userdata['user_email'];
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$submitted_login = my_private_site_spam_guard_submitted_username( $login );

	$reason = my_private_site_spam_guard_registration_block_reason( $login, $email, $ip, $submitted_login, $is_core_registration, $is_core_registration );
	if ( '' !== $reason ) {
		my_private_site_spam_guard_log( $login, $email, $ip, $reason );
		return array();
	}

	return $data;
}

add_filter( 'wp_pre_insert_user_data', 'my_private_site_recaptcha_registration_guard_pre_insert_user_data', 10, 4 );

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
 * Return spam signal metadata shared by registration protection and cleanup.
 *
 * Keep this registry aligned with Registration Spam Guard checks where the
 * signal can be evaluated against an existing user account. Form-only checks
 * such as honeypot and reCAPTCHA do not belong in cleanup.
 *
 * @return array
 */
function my_private_site_spam_guard_signal_definitions() {
	return array(
		'honeypot'                => array(
			'registration'       => true,
			'cleanup'            => false,
			'registration_label' => __( 'Enable honeypot field on registration form', 'my-private-site' ),
		),
		'gibberish_username'      => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations with excessively long gibberish usernames', 'my-private-site' ),
			'cleanup_label'      => __( 'Gibberish/random username', 'my-private-site' ),
		),
		'reserved_username'       => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations using reserved administrator usernames', 'my-private-site' ),
			'cleanup_label'      => __( 'Reserved administrator username', 'my-private-site' ),
		),
		'url_like_username'       => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations with URL-like usernames', 'my-private-site' ),
			'cleanup_label'      => __( 'URL-like username', 'my-private-site' ),
		),
		'spam_phrase_username'    => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations with crypto scam phrase usernames', 'my-private-site' ),
			'cleanup_label'      => __( 'Crypto/scam phrase username', 'my-private-site' ),
		),
		'excessive_dots'          => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations with excessive dots in email address', 'my-private-site' ),
			'cleanup_label'      => __( 'Excessive dots in email address', 'my-private-site' ),
		),
		'gmail_plus_alias'        => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations with suspicious Gmail plus aliases', 'my-private-site' ),
			'cleanup_label'      => __( 'Suspicious Gmail plus alias', 'my-private-site' ),
		),
		'email_digit_dominant'    => array(
			'registration'       => true,
			'cleanup'            => true,
			'registration_label' => __( 'Block registrations with digit-dominant email addresses', 'my-private-site' ),
			'cleanup_label'      => __( 'Digit-dominant email address', 'my-private-site' ),
		),
		'email_gibberish'         => array(
			'registration'       => true,
			'cleanup'            => false,
			'registration_label' => __( 'Block registrations with gibberish email addresses', 'my-private-site' ),
		),
		'email_consonant_soup'    => array(
			'registration'  => false,
			'cleanup'       => true,
			'cleanup_label' => __( 'Gibberish email address (consonant run)', 'my-private-site' ),
		),
		'email_low_vowel'         => array(
			'registration'  => false,
			'cleanup'       => true,
			'cleanup_label' => __( 'Gibberish email address (low vowels)', 'my-private-site' ),
		),
		'missing_mx'              => array(
			'registration'       => true,
			'cleanup'            => true,
			'slow'               => true,
			'registration_label' => __( 'Block registrations when email domain lacks MX records', 'my-private-site' ),
			'cleanup_label'      => __( 'Missing MX record', 'my-private-site' ),
		),
		'disposable_email_domain' => array(
			'registration'       => true,
			'cleanup'            => true,
			'cleanup_default'    => true,
			'registration_label' => __( 'Block registrations from disposable email domains', 'my-private-site' ),
			'cleanup_label'      => __( 'Disposable/spam email domain', 'my-private-site' ),
		),
		'bot_subdomain'           => array(
			'registration'    => false,
			'cleanup'         => true,
			'cleanup_default' => true,
			'cleanup_label'   => __( 'Bot-style email subdomain', 'my-private-site' ),
		),
		'bio_has_url'             => array(
			'registration'  => false,
			'cleanup'       => true,
			'cleanup_label' => __( 'URL in profile bio', 'my-private-site' ),
		),
		'stop_forum_spam'         => array(
			'registration'       => true,
			'cleanup'            => true,
			'slow'               => true,
			'external'           => true,
			'registration_label' => __( 'Check registrants against StopForumSpam database', 'my-private-site' ),
			'cleanup_label'      => __( 'StopForumSpam match', 'my-private-site' ),
		),
	);
}

/**
 * Return available spam signal choices for one context.
 *
 * @param string $context Context: registration or cleanup.
 * @return array
 */
function my_private_site_spam_guard_signal_choices( $context ) {
	$choices = array();
	foreach ( my_private_site_spam_guard_signal_definitions() as $key => $definition ) {
		if ( empty( $definition[ $context ] ) ) {
			continue;
		}
		$label_key       = $context . '_label';
		$choices[ $key ] = isset( $definition[ $label_key ] ) ? $definition[ $label_key ] : $key;
	}

	return $choices;
}

/**
 * Return allowed spam signal keys for one context.
 *
 * @param string $context Context: registration or cleanup.
 * @return array
 */
function my_private_site_spam_guard_allowed_signal_keys( $context ) {
	return array_fill_keys( array_keys( my_private_site_spam_guard_signal_choices( $context ) ), true );
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
	$recaptcha      = my_private_site_recaptcha_registration_guard_settings();
	if ( empty( $enabled_checks ) && ! $recaptcha['enabled'] ) {
		return $errors;
	}

	$ip                   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$submitted_user_login = my_private_site_spam_guard_submitted_username( $sanitized_user_login );
	$reason               = my_private_site_spam_guard_registration_block_reason( $sanitized_user_login, $user_email, $ip, $submitted_user_login, true, true );

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
 * Determine if a username is a reserved site-administration identity.
 *
 * @param string $username
 * @return bool
 */
function my_private_site_spam_guard_is_reserved_username( $username ) {
	$username = strtolower( trim( (string) $username ) );
	if ( '' === $username ) {
		return false;
	}

	$normalized = preg_replace( '/[^a-z0-9]+/', '', $username );
	if ( ! is_string( $normalized ) || '' === $normalized ) {
		return false;
	}

	$reserved_usernames = array(
		'admin',
		'administrator',
		'hostmaster',
		'postmaster',
		'root',
		'webmaster',
	);

	/**
	 * Filter reserved usernames blocked by Registration Spam Guard.
	 *
	 * @param array $reserved_usernames
	 */
	$reserved_usernames = apply_filters( 'my_private_site_spam_guard_reserved_usernames', $reserved_usernames );
	if ( ! is_array( $reserved_usernames ) ) {
		return false;
	}

	foreach ( array_map( 'strtolower', array_map( 'trim', $reserved_usernames ) ) as $reserved_username ) {
		if ( '' === $reserved_username ) {
			continue;
		}
		if ( $normalized === preg_replace( '/[^a-z0-9]+/', '', $reserved_username ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine if a username looks like a URL or domain name.
 *
 * @param string $username
 * @return bool
 */
function my_private_site_spam_guard_is_url_like_username( $username ) {
	$username = strtolower( trim( (string) $username ) );
	if ( '' === $username ) {
		return false;
	}

	if ( function_exists( 'is_email' ) && is_email( $username ) ) {
		return false;
	}

	if ( preg_match( '#^[a-z][a-z0-9+.-]*://#', $username ) ) {
		return true;
	}

	$candidates = array( $username );
	if ( preg_match_all( '/(?:^|[^a-z0-9.-])([a-z0-9][a-z0-9-]*(?:\.[a-z0-9][a-z0-9-]*){2,})(?=$|[^a-z0-9.-])/i', $username, $matches ) ) {
		$candidates = array_merge( $candidates, $matches[1] );
	}

	foreach ( array_unique( $candidates ) as $candidate ) {
		if ( my_private_site_spam_guard_is_domain_like_token( $candidate ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine if a token is syntactically similar to a multi-label domain.
 *
 * @param string $token
 * @return bool
 */
function my_private_site_spam_guard_is_domain_like_token( $token ) {
	$token = preg_replace( '#^www\.#', '', strtolower( trim( (string) $token ) ) );
	if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
		return false;
	}

	if ( ! preg_match( '/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $token ) ) {
		return false;
	}

	$labels = explode( '.', $token );
	if ( count( $labels ) < 3 ) {
		return false;
	}

	foreach ( $labels as $label ) {
		if ( '' === $label || strlen( $label ) > 63 || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label ) ) {
			return false;
		}
	}

	$tld = end( $labels );
	if ( ! is_string( $tld ) || ! preg_match( '/^[a-z]{2,24}$/', $tld ) ) {
		return false;
	}

	return true;
}

/**
 * Determine if a username contains common crypto scam registration phrases.
 *
 * @param string $username
 * @return bool
 */
function my_private_site_spam_guard_is_spam_phrase_username( $username ) {
	$username = strtolower( trim( (string) $username ) );
	if ( '' === $username ) {
		return false;
	}

	$normalized = preg_replace( '/[^a-z0-9]+/', ' ', $username );
	if ( ! is_string( $normalized ) ) {
		return false;
	}
	$normalized = trim( preg_replace( '/\s+/', ' ', $normalized ) );

	$has_scam_phrase = preg_match( '/\b(?:action required|check balance|withdraw funds|btc transfer|your balance)\b/', $normalized );
	$has_crypto_term = preg_match( '/\b(?:btc|crypto|usdt|usdc|wallet)\b/', $normalized );

	return (bool) ( $has_scam_phrase && $has_crypto_term );
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
 * Check if an email uses an opaque Gmail plus alias commonly seen in spam bursts.
 *
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_has_suspicious_gmail_plus_alias( $email ) {
	$email  = strtolower( trim( (string) $email ) );
	$at_pos = strrpos( $email, '@' );
	if ( false === $at_pos ) {
		return false;
	}

	$local_part = substr( $email, 0, $at_pos );
	$domain     = substr( $email, $at_pos + 1 );
	if ( ! in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		return false;
	}

	$plus_pos = strpos( $local_part, '+' );
	if ( false === $plus_pos ) {
		return false;
	}

	$alias = substr( $local_part, $plus_pos + 1 );
	if ( ! preg_match( '/^[a-z0-9]{3,}$/', $alias ) ) {
		return false;
	}

	if ( ! preg_match( '/[0-9]/', $alias ) ) {
		return false;
	}

	$alias_length = strlen( $alias );
	if ( $alias_length <= 5 ) {
		$transitions = 0;
		for ( $i = 1; $i < $alias_length; $i++ ) {
			if ( ctype_digit( $alias[ $i - 1 ] ) !== ctype_digit( $alias[ $i ] ) ) {
				$transitions++;
			}
		}

		$digit_count = preg_match_all( '/[0-9]/', $alias );
		return ( 2 <= $transitions || 2 <= $digit_count || ctype_digit( $alias[0] ) );
	}

	$vowels      = preg_match_all( '/[aeiou]/', $alias );
	$vowel_ratio = $vowels / $alias_length;
	if ( $vowel_ratio > 0.25 ) {
		return false;
	}

	$transitions = 0;
	for ( $i = 1; $i < $alias_length; $i++ ) {
		if ( ctype_digit( $alias[ $i - 1 ] ) !== ctype_digit( $alias[ $i ] ) ) {
			$transitions++;
		}
	}

	return ( $transitions >= 3 );
}

/**
 * Return the email local part before the last @.
 *
 * @param string $email Email address.
 * @return string
 */
function my_private_site_spam_guard_email_local_part( $email ) {
	$email  = strtolower( trim( (string) $email ) );
	$at_pos = strrpos( $email, '@' );
	if ( false === $at_pos ) {
		return '';
	}

	return substr( $email, 0, $at_pos );
}

/**
 * Determine whether the email local part is digit-dominant.
 *
 * @param string $email Email address.
 * @return bool
 */
function my_private_site_spam_guard_email_is_digit_dominant( $email ) {
	$local_part = my_private_site_spam_guard_email_local_part( $email );
	if ( '' === $local_part ) {
		return false;
	}

	if ( preg_match( '/^[a-z]+(?:19|20)[0-9]{2}$/', $local_part ) ) {
		return false;
	}

	$length      = strlen( $local_part );
	$digits      = preg_match_all( '/[0-9]/', $local_part );
	$longest_run = 0;
	if ( preg_match_all( '/[0-9]+/', $local_part, $matches ) ) {
		foreach ( $matches[0] as $run ) {
			$longest_run = max( $longest_run, strlen( $run ) );
		}
	}

	return ( $length >= JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_MIN_LENGTH && ( $digits / $length ) >= JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_RATIO )
		|| $longest_run >= JR_PS_SPAM_EMAIL_DIGIT_DOMINANT_RUN;
}

/**
 * Determine whether the email local part contains consonant soup.
 *
 * @param string $email Email address.
 * @return bool
 */
function my_private_site_spam_guard_email_is_consonant_soup( $email ) {
	$local_part = my_private_site_spam_guard_email_local_part( $email );
	if ( '' === $local_part ) {
		return false;
	}

	if ( ! preg_match_all( '/[bcdfghjklmnpqrstvwxyz]+/', $local_part, $matches ) ) {
		return false;
	}

	foreach ( $matches[0] as $run ) {
		if ( strlen( $run ) < JR_PS_SPAM_EMAIL_CONSONANT_RUN ) {
			continue;
		}

		$distinct = count( array_unique( str_split( $run ) ) );
		$counts   = array_count_values( str_split( $run ) );
		$max      = max( $counts );
		if ( $distinct >= JR_PS_SPAM_EMAIL_CONSONANT_DISTINCT && ( $max / strlen( $run ) ) <= JR_PS_SPAM_EMAIL_CONSONANT_MAX_REPEAT_RATIO ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether the email local part has very few vowels.
 *
 * @param string $email Email address.
 * @return bool
 */
function my_private_site_spam_guard_email_is_low_vowel( $email ) {
	$local_part = my_private_site_spam_guard_email_local_part( $email );
	if ( '' === $local_part ) {
		return false;
	}

	$letters = preg_replace( '/[^a-z]/', '', $local_part );
	if ( ! is_string( $letters ) || strlen( $letters ) < JR_PS_SPAM_EMAIL_LOW_VOWEL_MIN_LETTERS ) {
		return false;
	}

	$vowels = preg_match_all( '/[aeiou]/', $letters );

	return ( $vowels / strlen( $letters ) ) < JR_PS_SPAM_EMAIL_LOW_VOWEL_RATIO;
}

/**
 * Determine whether a user's profile bio contains explicit URL markers.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function my_private_site_spam_guard_bio_has_url( $user_id ) {
	$description = strtolower( (string) get_user_meta( (int) $user_id, 'description', true ) );
	if ( '' === $description ) {
		return false;
	}

	foreach ( array( 'http://', 'https://', 'www.', '[url' ) as $needle ) {
		if ( false !== strpos( $description, $needle ) ) {
			return true;
		}
	}

	return false;
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
 * Determine if the email domain is a known disposable email service.
 *
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_is_disposable_email_domain( $email ) {
	$at_pos = strrpos( $email, '@' );
	if ( false === $at_pos ) {
		return false;
	}

	$domain = strtolower( trim( (string) substr( $email, $at_pos + 1 ) ) );
	if ( '' === $domain ) {
		return false;
	}

	$blocked_domains = array(
		'10minutemail.com',
		'dispostable.com',
		'emailondeck.com',
		'fakemail.net',
		'getnada.com',
		'guerrillamail.com',
		'guerrillamail.net',
		'maildrop.cc',
		'mailinator.com',
		'mailnesia.com',
		'moakt.com',
		'mytemp.email',
		'sharklasers.com',
		'temp-mail.org',
		'tempail.com',
		'tempmail.com',
		'throwawaymail.com',
		'trashmail.com',
		'yopmail.com',
	);

	/**
	 * Filter disposable email domains blocked by Registration Spam Guard.
	 *
	 * @param array $blocked_domains
	 */
	$blocked_domains = apply_filters( 'my_private_site_spam_guard_disposable_email_domains', $blocked_domains );
	if ( ! is_array( $blocked_domains ) ) {
		return false;
	}

	foreach ( array_map( 'strtolower', array_map( 'trim', $blocked_domains ) ) as $blocked_domain ) {
		if ( '' === $blocked_domain ) {
			continue;
		}
		if ( $domain === $blocked_domain || substr( $domain, -1 * ( strlen( $blocked_domain ) + 1 ) ) === '.' . $blocked_domain ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine if an email domain matches a bot-style subdomain pattern.
 *
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_email_has_bot_subdomain( $email ) {
	$domain = strtolower( trim( (string) substr( strrchr( $email, '@' ), 1 ) ) );
	if ( '' === $domain ) {
		return false;
	}

	$labels = explode( '.', $domain );
	if ( count( $labels ) < 3 ) {
		return false;
	}

	$leftmost = $labels[0];
	if ( ! preg_match( '/^[a-z0-9]{5,}$/', $leftmost ) ) {
		return false;
	}

	return (bool) ( preg_match( '/[a-z]/', $leftmost ) && preg_match( '/[0-9]/', $leftmost ) );
}

/**
 * Determine whether one spam signal matches a registration/user record.
 *
 * @param string      $signal Signal key.
 * @param string      $username Username.
 * @param string      $email Email address.
 * @param string      $ip IP address.
 * @param string|null $submitted_username Raw submitted username when available.
 * @return bool
 */
function my_private_site_spam_guard_signal_matches( $signal, $username, $email, $ip = '', $submitted_username = null ) {
	$signal             = sanitize_key( $signal );
	$submitted_username = null === $submitted_username ? $username : $submitted_username;

	switch ( $signal ) {
		case 'gibberish_username':
			return my_private_site_spam_guard_is_gibberish_username( $username );
		case 'reserved_username':
			return my_private_site_spam_guard_is_reserved_username( $username ) || my_private_site_spam_guard_is_reserved_username( $submitted_username );
		case 'url_like_username':
			return my_private_site_spam_guard_is_url_like_username( $username ) || my_private_site_spam_guard_is_url_like_username( $submitted_username );
		case 'spam_phrase_username':
			return my_private_site_spam_guard_is_spam_phrase_username( $username ) || my_private_site_spam_guard_is_spam_phrase_username( $submitted_username );
		case 'excessive_dots':
			return my_private_site_spam_guard_has_excessive_dots( $email );
		case 'gmail_plus_alias':
			return my_private_site_spam_guard_has_suspicious_gmail_plus_alias( $email );
		case 'email_digit_dominant':
			return my_private_site_spam_guard_email_is_digit_dominant( $email );
		case 'email_gibberish':
			return my_private_site_spam_guard_email_is_consonant_soup( $email ) || my_private_site_spam_guard_email_is_low_vowel( $email );
		case 'email_consonant_soup':
			return my_private_site_spam_guard_email_is_consonant_soup( $email );
		case 'email_low_vowel':
			return my_private_site_spam_guard_email_is_low_vowel( $email );
		case 'missing_mx':
			return my_private_site_spam_guard_missing_mx_record( $email );
		case 'disposable_email_domain':
			return my_private_site_spam_guard_is_disposable_email_domain( $email );
		case 'bot_subdomain':
			return my_private_site_spam_guard_email_has_bot_subdomain( $email );
		case 'stop_forum_spam':
			return my_private_site_spam_guard_check_stop_forum_spam( $ip, $email );
	}

	return false;
}

/**
 * Query StopForumSpam API.
 *
 * @param string $ip
 * @param string $email
 * @return bool
 */
function my_private_site_spam_guard_check_stop_forum_spam( $ip, $email ) {
	$GLOBALS['my_private_site_stop_forum_spam_last_error'] = '';

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
			'timeout' => (int) apply_filters( 'my_private_site_stop_forum_spam_timeout', 5 ),
		)
	);

	$is_spam = false;
	if ( is_wp_error( $resp ) ) {
		$GLOBALS['my_private_site_stop_forum_spam_last_error'] = sprintf(
			/* translators: %s: StopForumSpam error message. */
			__( 'StopForumSpam lookup failed: %s', 'my-private-site' ),
			$resp->get_error_message()
		);
		return false;
	}

	$response_code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 429 === $response_code ) {
		$GLOBALS['my_private_site_stop_forum_spam_last_error'] = __( 'StopForumSpam API limit reached. Pause the dry run and try again later, or run without the StopForumSpam signal.', 'my-private-site' );
		return false;
	}

	if ( 200 !== $response_code ) {
		$GLOBALS['my_private_site_stop_forum_spam_last_error'] = sprintf(
			/* translators: %d: HTTP response code. */
			__( 'StopForumSpam lookup failed with HTTP %d.', 'my-private-site' ),
			$response_code
		);
		return false;
	}

	if ( 200 === $response_code ) {
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
 * Return the last StopForumSpam lookup error for the current request.
 *
 * @return string
 */
function my_private_site_spam_guard_stop_forum_spam_last_error() {
	return isset( $GLOBALS['my_private_site_stop_forum_spam_last_error'] ) ? (string) $GLOBALS['my_private_site_stop_forum_spam_last_error'] : '';
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
	$log = array_slice( $log, 0, 100 );

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
