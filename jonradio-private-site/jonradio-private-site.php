<?php
/*
Plugin Name: My Private Site with AI Defense
Plugin URI: http://zatzlabs.com/plugins/
Description: Lock down your site with one click. Privacy for family, projects, or teams.
Version: 4.0.3
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
	define( 'JR_PS_PLUGIN_VERSION', '4.0.3' );
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
