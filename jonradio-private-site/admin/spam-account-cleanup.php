<?php
/**
 * Spam Account Cleanup admin tool.
 *
 * @package My_Private_Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JR_PS_CLEANUP_STATE_OPTION', 'jr_ps_cleanup_state' );
define( 'JR_PS_CLEANUP_LOG_OPTION', 'jr_ps_cleanup_log' );
define( 'JR_PS_CLEANUP_BATCH_SIZE', 200 );
define( 'JR_PS_CLEANUP_SUMMARY_BATCH_SIZE', 2000 );
define( 'JR_PS_CLEANUP_ANALYSIS_BATCH_SIZE', 200 );
define( 'JR_PS_CLEANUP_ANALYSIS_PROGRESS_INTERVAL', 25 );
define( 'JR_PS_CLEANUP_IMPORT_BATCH_SIZE', 2000 );
define( 'JR_PS_CLEANUP_ORPHAN_BATCH_SIZE', 2000 );
define( 'JR_PS_CLEANUP_PAGE_SLUG', 'jr-ps-spam-cleanup' );
define( 'JR_PS_CLEANUP_SIGNAL_VERSION', 4 );
define( 'JR_PS_CLEANUP_SCAN_TIME_LIMIT', 20 );

/**
 * Return default cleanup spam signals.
 *
 * @return array
 */
function my_private_site_cleanup_default_spam_signals() {
	if ( ! function_exists( 'my_private_site_spam_guard_signal_definitions' ) ) {
		return array();
	}

	$signals = array();
	foreach ( my_private_site_spam_guard_signal_definitions() as $key => $definition ) {
		if ( ! empty( $definition['cleanup'] ) && ! empty( $definition['cleanup_default'] ) ) {
			$signals[] = $key;
		}
	}

	return $signals;
}

/**
 * Return the cleanup allowlist table name.
 *
 * @return string
 */
function my_private_site_cleanup_allowlist_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'jr_ps_cleanup_allowlist';
}

/**
 * Return the cleanup queue table name.
 *
 * @return string
 */
function my_private_site_cleanup_queue_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'jr_ps_cleanup_queue';
}

/**
 * Return the default cleanup state.
 *
 * @return array
 */
function my_private_site_cleanup_default_state() {
	return array(
		'phase'                      => 'idle',
		'date_start'                 => '',
		'date_end'                   => '',
		'admin_allowlist'            => array(),
		'fold_gmail'                 => true,
		'mailing_list_mode'          => 'absolute',
		'recent_login_protection_enabled' => true,
		'recent_days'                => 90,
		'cleanup_spam_signals'       => my_private_site_cleanup_default_spam_signals(),
		'totals'                     => array(
			'scanned'            => 0,
			'queued'             => 0,
			'excluded'           => 0,
			'total_users'        => 0,
			'not_eligible'       => 0,
			'usermeta_queued'    => 0,
			'usermeta_kept'      => 0,
			'usermeta_deleted'   => 0,
			'deleted'            => 0,
			'errors'             => 0,
			'allowlist_hashes'   => 0,
			'imported'           => 0,
			'import_invalid'     => 0,
			'list_protected'     => 0,
			'list_spam_eligible' => 0,
			'orphan_count'       => 0,
			'orphan_deleted'     => 0,
		),
		'reasons'                    => array(),
		'list_signals'               => array(),
		'queue_signals'              => array(),
		'analysis'                   => array(
			'signature'     => '',
			'done'          => false,
			'stale'         => false,
			'last_user_id'  => 0,
			'scanned'       => 0,
			'excluded'      => 0,
			'matched_users' => 0,
			'matches'       => array(),
			'selected'      => array(),
			'updated_at'    => '',
		),
		'summary'                    => array(
			'done'         => false,
			'last_user_id' => 0,
			'scanned'      => 0,
			'eligible'     => 0,
			'years'        => array(),
			'date_start'   => '',
			'date_end'     => '',
			'updated_at'   => '',
			),
			'scan_last_user_id'          => 0,
			'scan_signature'             => '',
			'scan_pause_reason_code'     => '',
			'scan_pause_reason_detail'   => '',
			'delete_started'             => false,
			'delete_confirmed'           => false,
			'backup_ack'                 => false,
		'confirmation_token'         => wp_generate_password( 12, false, false ),
		'completed_awaiting_reset' => false,
		'upload'                     => array(),
		'allowlist_keep'             => false,
		'lock_until'                 => 0,
		'report'                     => array(),
		'orphan'                     => array(
			'confirmed' => false,
			'last_id'   => 0,
			'max_id'    => 0,
		),
	);
}

/**
 * Get cleanup state.
 *
 * @return array
 */
function my_private_site_cleanup_get_state() {
	$state = get_option( JR_PS_CLEANUP_STATE_OPTION );
	if ( ! is_array( $state ) ) {
		$state = array();
	}

	$stored = $state;
	$state  = array_replace_recursive( my_private_site_cleanup_default_state(), $state );
	if ( isset( $stored['cleanup_spam_signals'] ) && is_array( $stored['cleanup_spam_signals'] ) ) {
		$state['cleanup_spam_signals'] = $stored['cleanup_spam_signals'];
	}
	if ( in_array( $state['phase'], array( 'analyzing', 'analysis_paused' ), true ) ) {
		$state['phase'] = 'idle';
	}

	return $state;
}

/**
 * Save cleanup state.
 *
 * @param array $state State.
 * @return void
 */
function my_private_site_cleanup_update_state( $state ) {
	update_option( JR_PS_CLEANUP_STATE_OPTION, $state, false );
}

/**
 * Create cleanup tables if needed.
 *
 * @return void
 */
function my_private_site_cleanup_ensure_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$allowlist_table = my_private_site_cleanup_allowlist_table_name();
	$queue_table     = my_private_site_cleanup_queue_table_name();

	dbDelta(
		"CREATE TABLE $allowlist_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email_hash CHAR(32) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_hash (email_hash)
		) $charset_collate;"
	);

	dbDelta(
		"CREATE TABLE $queue_table (
			user_id BIGINT UNSIGNED NOT NULL,
			user_login VARCHAR(60) NOT NULL DEFAULT '',
			user_email VARCHAR(100) NOT NULL DEFAULT '',
			registered DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending_delete',
			reason VARCHAR(100) NOT NULL DEFAULT '',
			PRIMARY KEY  (user_id),
			KEY status (status)
		) $charset_collate;"
	);
}

/**
 * Clear transient cleanup working data.
 *
 * @param bool $keep_allowlist Whether to keep allowlist hashes.
 * @param bool $drop_queue Whether to empty queue.
 * @return void
 */
function my_private_site_cleanup_clear_working_data( $keep_allowlist = false, $drop_queue = false ) {
	global $wpdb;

	$state = my_private_site_cleanup_get_state();
	if ( ! empty( $state['upload']['path'] ) && file_exists( $state['upload']['path'] ) ) {
		wp_delete_file( $state['upload']['path'] );
	}

	my_private_site_cleanup_ensure_tables();
	if ( ! $keep_allowlist ) {
		$wpdb->query( 'TRUNCATE TABLE ' . my_private_site_cleanup_allowlist_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	if ( $drop_queue ) {
		$wpdb->query( 'TRUNCATE TABLE ' . my_private_site_cleanup_queue_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

/**
 * Reset the entire cleanup state.
 *
 * @return void
 */
function my_private_site_cleanup_reset_all() {
	my_private_site_cleanup_clear_working_data( false, true );
	delete_option( JR_PS_CLEANUP_STATE_OPTION );
}

/**
 * Verify cleanup AJAX permissions.
 *
 * @return void
 */
function my_private_site_cleanup_ajax_guard() {
	if ( is_multisite() || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to use this tool.', 'my-private-site' ) ), 403 );
	}

	check_ajax_referer( 'jr_ps_cleanup_nonce', 'nonce' );
}

/**
 * Acquire a short lock for batch processing.
 *
 * @param string $name Lock name.
 * @return bool
 */
function my_private_site_cleanup_acquire_lock( $name ) {
	$state = my_private_site_cleanup_get_state();
	$now   = time();
	$key   = 'lock_' . sanitize_key( $name ) . '_until';
	if ( ! empty( $state[ $key ] ) && $state[ $key ] > $now ) {
		return false;
	}

	$state[ $key ] = $now + 45;
	my_private_site_cleanup_update_state( $state );

	return true;
}

/**
 * Release a batch lock.
 *
 * @param string $name Lock name.
 * @return void
 */
function my_private_site_cleanup_release_lock( $name ) {
	$state = my_private_site_cleanup_get_state();
	$key   = 'lock_' . sanitize_key( $name ) . '_until';
	unset( $state[ $key ] );
	my_private_site_cleanup_update_state( $state );
}

/**
 * Normalize an email for allowlist matching.
 *
 * @param string $email Email.
 * @param bool   $fold_gmail Whether to fold Gmail dots/plus tags.
 * @return string
 */
function my_private_site_cleanup_normalize_email( $email, $fold_gmail = true ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return '';
	}

	$parts = explode( '@', $email, 2 );
	if ( 2 !== count( $parts ) ) {
		return '';
	}

	$local  = $parts[0];
	$domain = $parts[1];
	if ( $fold_gmail && in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		$local  = preg_replace( '/\+.*/', '', $local );
		$local  = str_replace( '.', '', $local );
		$domain = 'gmail.com';
	}

	return $local . '@' . $domain;
}

/**
 * Hash normalized email.
 *
 * @param string $email Email.
 * @param bool   $fold_gmail Whether to fold Gmail variants.
 * @return string
 */
function my_private_site_cleanup_email_hash( $email, $fold_gmail = true ) {
	$normalized = my_private_site_cleanup_normalize_email( $email, $fold_gmail );
	return '' === $normalized ? '' : md5( $normalized );
}

/**
 * Add a reason count to state.
 *
 * @param array  $state State.
 * @param string $reason Reason.
 * @return array
 */
function my_private_site_cleanup_increment_reason( $state, $reason ) {
	if ( ! isset( $state['reasons'] ) || ! is_array( $state['reasons'] ) ) {
		$state['reasons'] = array();
	}
	if ( ! isset( $state['reasons'][ $reason ] ) ) {
		$state['reasons'][ $reason ] = 0;
	}
	$state['reasons'][ $reason ]++;

	return $state;
}

/**
 * Add mailing-list override signal counts to state.
 *
 * @param array $state State.
 * @param array $signals Signals.
 * @return array
 */
function my_private_site_cleanup_increment_list_signals( $state, $signals ) {
	if ( ! isset( $state['list_signals'] ) || ! is_array( $state['list_signals'] ) ) {
		$state['list_signals'] = array();
	}

	foreach ( $signals as $signal ) {
		if ( ! isset( $state['list_signals'][ $signal ] ) ) {
			$state['list_signals'][ $signal ] = 0;
		}
		$state['list_signals'][ $signal ]++;
	}

	return $state;
}

/**
 * Add queued-deletion signal counts to state.
 *
 * @param array $state State.
 * @param array $signals Signals.
 * @return array
 */
function my_private_site_cleanup_increment_queue_signals( $state, $signals ) {
	if ( ! isset( $state['queue_signals'] ) || ! is_array( $state['queue_signals'] ) ) {
		$state['queue_signals'] = array();
	}

	foreach ( $signals as $signal ) {
		if ( ! isset( $state['queue_signals'][ $signal ] ) ) {
			$state['queue_signals'][ $signal ] = 0;
		}
		$state['queue_signals'][ $signal ]++;
	}

	return $state;
}

/**
 * Log cleanup summary.
 *
 * @param array $entry Entry.
 * @return void
 */
function my_private_site_cleanup_log( $entry ) {
	$log = get_option( JR_PS_CLEANUP_LOG_OPTION );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	array_unshift( $log, $entry );
	update_option( JR_PS_CLEANUP_LOG_OPTION, array_slice( $log, 0, 20 ), false );
}

/**
 * Remove server-only fields before state is sent to the browser.
 *
 * @param array $state State.
 * @return array
 */
function my_private_site_cleanup_public_state( $state ) {
	if ( empty( $state['upload'] ) || ! is_array( $state['upload'] ) ) {
		$state['upload'] = array( 'path' => '' );
	} elseif ( isset( $state['upload']['path'] ) ) {
		$state['upload']['path'] = '';
	}

	foreach ( array_keys( $state ) as $key ) {
		if ( 0 === strpos( $key, 'lock_' ) ) {
			unset( $state[ $key ] );
		}
	}

	return $state;
}

/**
 * Set the persisted dry-run pause reason.
 *
 * @param array  $state State.
 * @param string $code Reason code.
 * @param string $detail Optional detail.
 * @return array
 */
function my_private_site_cleanup_set_scan_pause_reason( $state, $code, $detail = '' ) {
	$state['scan_pause_reason_code']   = sanitize_key( $code );
	$state['scan_pause_reason_detail'] = sanitize_text_field( $detail );

	return $state;
}

/**
 * Clear the persisted dry-run pause reason.
 *
 * @param array $state State.
 * @return array
 */
function my_private_site_cleanup_clear_scan_pause_reason( $state ) {
	$state['scan_pause_reason_code']   = '';
	$state['scan_pause_reason_detail'] = '';

	return $state;
}

/**
 * Convert an abandoned dry-run scan into a paused scan.
 *
 * @param array $state State.
 * @return array
 */
function my_private_site_cleanup_maybe_pause_stale_scan( $state ) {
	if ( 'scanning' !== $state['phase'] ) {
		return $state;
	}

	if ( ! empty( $state['lock_scan_until'] ) && (int) $state['lock_scan_until'] >= time() ) {
		return $state;
	}

	$state['phase'] = 'scan_paused';
	return my_private_site_cleanup_set_scan_pause_reason(
		$state,
		'stale_lock',
		__( 'No active dry-run request was detected.', 'my-private-site' )
	);
}

/**
 * Return browser-ready year rows from summary state.
 *
 * @param array $summary Summary state.
 * @return array
 */
function my_private_site_cleanup_summary_year_rows( $summary ) {
	$counts = isset( $summary['years'] ) && is_array( $summary['years'] ) ? $summary['years'] : array();
	krsort( $counts );

	$rows = array();
	foreach ( $counts as $year => $total ) {
		$rows[] = array(
			'yr'    => (string) $year,
			'total' => (int) $total,
		);
	}

	return $rows;
}

/**
 * Return browser-ready date range from summary state.
 *
 * @param array $summary Summary state.
 * @return array
 */
function my_private_site_cleanup_summary_date_range( $summary ) {
	return array(
		'date_start' => isset( $summary['date_start'] ) ? (string) $summary['date_start'] : '',
		'date_end'   => isset( $summary['date_end'] ) ? (string) $summary['date_end'] : '',
	);
}

/**
 * Parse admin allowlist lines.
 *
 * @param string $text Textarea contents.
 * @return array
 */
function my_private_site_cleanup_parse_admin_allowlist( $text ) {
	$entries = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( is_email( $line ) ) {
			$entries[] = strtolower( $line );
		} else {
			$entries[] = sanitize_user( $line, true );
		}
	}

	return array_values( array_unique( array_filter( $entries ) ) );
}

/**
 * Return user IDs and registration dates in a date window.
 *
 * @param string $date_start Start date.
 * @param string $date_end   End date.
 * @return array
 */
function my_private_site_cleanup_user_rows_in_date_window( $date_start = '', $date_end = '' ) {
	global $wpdb;

	$where  = array();
	$params = array();
	if ( '' !== $date_start ) {
		$where[]  = 'user_registered >= %s';
		$params[] = $date_start . ' 00:00:00';
	}
	if ( '' !== $date_end ) {
		$where[]  = 'user_registered <= %s';
		$params[] = $date_end . ' 23:59:59';
	}

	$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';
	$sql              = "SELECT u.ID, u.user_registered, um.meta_value AS capabilities
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->usermeta} um
			ON u.ID = um.user_id
			AND um.meta_key = %s";
	$params           = array_merge( array( $capabilities_key ), $params );
	if ( ! empty( $where ) ) {
		$sql .= ' WHERE ' . implode( ' AND ', array_map( static function( $condition ) {
			return 'u.' . $condition;
		}, $where ) );
	}
	$sql .= ' ORDER BY u.ID ASC';

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return a bounded batch of users and capabilities after a user ID.
 *
 * @param int $last_user_id Last processed user ID.
 * @param int $limit Batch size.
 * @return array
 */
function my_private_site_cleanup_user_rows_after_id( $last_user_id, $limit ) {
	global $wpdb;

	$capabilities_key = $wpdb->get_blog_prefix() . 'capabilities';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT u.ID, u.user_registered, um.meta_value AS capabilities
			FROM {$wpdb->users} u
			LEFT JOIN {$wpdb->usermeta} um
				ON u.ID = um.user_id
				AND um.meta_key = %s
			WHERE u.ID > %d
			ORDER BY u.ID ASC
			LIMIT %d",
			$capabilities_key,
			(int) $last_user_id,
			(int) $limit
		),
		ARRAY_A
	);
}

/**
 * Normalize raw user capabilities data from usermeta.
 *
 * @param mixed $capabilities Raw capabilities value.
 * @return array
 */
function my_private_site_cleanup_normalize_capabilities_data( $capabilities ) {
	if ( is_string( $capabilities ) ) {
		$capabilities = maybe_unserialize( $capabilities );
	}

	if ( ! is_array( $capabilities ) ) {
		return array();
	}

	return $capabilities;
}

/**
 * Expand a raw capabilities row into effective primitive capabilities.
 *
 * This avoids loading full WP_User objects while building large year/date
 * summaries, but still uses WordPress role definitions instead of role-name
 * assumptions.
 *
 * @param mixed $capabilities Raw capabilities value.
 * @return array
 */
function my_private_site_cleanup_expand_capabilities_data( $capabilities ) {
	$capabilities = my_private_site_cleanup_normalize_capabilities_data( $capabilities );
	if ( empty( $capabilities ) ) {
		return array();
	}

	$allcaps  = array();
	$wp_roles = wp_roles();
	foreach ( $capabilities as $capability => $enabled ) {
		if ( empty( $enabled ) || ! isset( $wp_roles->roles[ $capability ] ) ) {
			continue;
		}

		$role_caps = isset( $wp_roles->roles[ $capability ]['capabilities'] ) ? $wp_roles->roles[ $capability ]['capabilities'] : array();
		foreach ( $role_caps as $role_capability => $role_enabled ) {
			if ( ! empty( $role_enabled ) ) {
				$allcaps[ $role_capability ] = true;
			}
		}
	}

	foreach ( $capabilities as $capability => $enabled ) {
		$allcaps[ $capability ] = ! empty( $enabled );
	}

	return $allcaps;
}

/**
 * Check whether raw capabilities data can enter the initial cleanup pool.
 *
 * @param mixed $capabilities Raw capabilities value.
 * @return bool
 */
function my_private_site_cleanup_capabilities_are_candidate_eligible( $capabilities ) {
	$allcaps = my_private_site_cleanup_expand_capabilities_data( $capabilities );
	if ( empty( $allcaps['read'] ) ) {
		return false;
	}

	foreach ( my_private_site_cleanup_privileged_capabilities() as $capability ) {
		if ( ! empty( $allcaps[ $capability ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Return date counts by registration year for cleanup-eligible users.
 *
 * @return array
 */
function my_private_site_cleanup_counts_by_year() {
	$counts = array();
	foreach ( my_private_site_cleanup_user_rows_in_date_window() as $row ) {
		if ( ! my_private_site_cleanup_capabilities_are_candidate_eligible( isset( $row['capabilities'] ) ? $row['capabilities'] : array() ) ) {
			continue;
		}
		$year = gmdate( 'Y', strtotime( $row['user_registered'] ) );
		if ( ! isset( $counts[ $year ] ) ) {
			$counts[ $year ] = 0;
		}
		$counts[ $year ]++;
	}
	krsort( $counts );

	$rows = array();
	foreach ( $counts as $year => $total ) {
		$rows[] = array(
			'yr'    => $year,
			'total' => $total,
		);
	}

	return $rows;
}

/**
 * Return the full registration date range for cleanup-eligible users.
 *
 * @return array
 */
function my_private_site_cleanup_date_range() {
	$start = '';
	$end   = '';
	foreach ( my_private_site_cleanup_user_rows_in_date_window() as $row ) {
		if ( ! my_private_site_cleanup_capabilities_are_candidate_eligible( isset( $row['capabilities'] ) ? $row['capabilities'] : array() ) ) {
			continue;
		}
		$date = gmdate( 'Y-m-d', strtotime( $row['user_registered'] ) );
		if ( '' === $start || $date < $start ) {
			$start = $date;
		}
		if ( '' === $end || $date > $end ) {
			$end = $date;
		}
	}

	return array(
		'date_start' => $start,
		'date_end'   => $end,
	);
}

/**
 * Count all WordPress users in the selected registration date window.
 *
 * @param string $date_start Start date.
 * @param string $date_end   End date.
 * @return int
 */
function my_private_site_cleanup_count_users_in_date_window( $date_start, $date_end ) {
	return count( my_private_site_cleanup_user_rows_in_date_window( $date_start, $date_end ) );
}

/**
 * Count usermeta rows attached to a batch of users.
 *
 * @param array $user_ids User IDs.
 * @return array
 */
function my_private_site_cleanup_count_usermeta_for_users( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $user_ids ) ) ) );
	if ( empty( $user_ids ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
	$rows         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, COUNT(*) AS total
			FROM {$wpdb->usermeta}
			WHERE user_id IN ($placeholders)
			GROUP BY user_id",
			$user_ids
		),
		ARRAY_A
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$counts       = array_fill_keys( $user_ids, 0 );
	foreach ( $rows as $row ) {
		$counts[ (int) $row['user_id'] ] = (int) $row['total'];
	}

	return $counts;
}

/**
 * Check whether a database table exists.
 *
 * @param string $table Table name.
 * @return bool
 */
function my_private_site_cleanup_table_exists( $table ) {
	global $wpdb;

	$table = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );
	if ( '' === $table ) {
		return false;
	}

	static $cache = array();
	if ( array_key_exists( $table, $cache ) ) {
		return $cache[ $table ];
	}

	$cache[ $table ] = ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	return $cache[ $table ];
}

/**
 * Check whether a database table column exists.
 *
 * @param string $table Table name.
 * @param string $column Column name.
 * @return bool
 */
function my_private_site_cleanup_table_column_exists( $table, $column ) {
	global $wpdb;

	$table  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );
	$column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $column );
	if ( '' === $table || '' === $column || ! my_private_site_cleanup_table_exists( $table ) ) {
		return false;
	}

	static $cache = array();
	$key = $table . '.' . $column;
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$cache[ $key ] = ( $column === $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $cache[ $key ];
}

/**
 * Return capabilities that keep a user out of the initial cleanup pool.
 *
 * @return array
 */
function my_private_site_cleanup_privileged_capabilities() {
	$capabilities = array(
		'edit_posts',
		'edit_pages',
		'edit_others_posts',
		'edit_others_pages',
		'edit_published_posts',
		'edit_published_pages',
		'publish_posts',
		'publish_pages',
		'delete_posts',
		'delete_pages',
		'delete_others_posts',
		'delete_others_pages',
		'delete_published_posts',
		'delete_published_pages',
		'manage_categories',
		'moderate_comments',
		'upload_files',
		'edit_theme_options',
		'manage_options',
		'list_users',
		'edit_users',
		'create_users',
		'delete_users',
		'promote_users',
		'manage_woocommerce',
	);

	/**
	 * Filter privileged capabilities that exclude users from cleanup candidacy.
	 *
	 * @param array $capabilities Capability names.
	 */
	$capabilities = apply_filters( 'my_private_site_cleanup_privileged_capabilities', $capabilities );
	if ( ! is_array( $capabilities ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $capabilities ) ) ) );
}

/**
 * Check whether a user can enter the initial cleanup candidate pool.
 *
 * @param WP_User $user User.
 * @return bool
 */
function my_private_site_cleanup_user_is_candidate_eligible( $user ) {
	if ( ! $user instanceof WP_User ) {
		return false;
	}

	if ( ! user_can( $user, 'read' ) ) {
		return false;
	}

	foreach ( my_private_site_cleanup_privileged_capabilities() as $capability ) {
		if ( user_can( $user, $capability ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Check a dynamic table/column for an integer match.
 *
 * @param string $table Table name.
 * @param string $column Column name.
 * @param int    $value Integer value.
 * @return bool
 */
function my_private_site_cleanup_db_has_int_match( $table, $column, $value ) {
	global $wpdb;

	$table  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );
	$column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $column );
	$value  = (int) $value;
	if ( $value <= 0 || '' === $table || '' === $column || ! my_private_site_cleanup_table_column_exists( $table, $column ) ) {
		return false;
	}

	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE `$column` = %d LIMIT 1", $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Check a dynamic table/column for a text match.
 *
 * @param string $table Table name.
 * @param string $column Column name.
 * @param string $value Text value.
 * @param bool   $like Whether to use a contains match.
 * @return bool
 */
function my_private_site_cleanup_db_has_text_match( $table, $column, $value, $like = false ) {
	global $wpdb;

	$table  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );
	$column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $column );
	$value  = trim( (string) $value );
	if ( '' === $value || '' === $table || '' === $column || ! my_private_site_cleanup_table_column_exists( $table, $column ) ) {
		return false;
	}

	if ( $like ) {
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE `$column` LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $value ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE `$column` = %s LIMIT 1", $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Check EDD database records without requiring EDD to be active.
 *
 * @param int    $user_id User ID.
 * @param string $email Email.
 * @return bool
 */
function my_private_site_cleanup_user_has_edd_database_record( $user_id, $email ) {
	global $wpdb;

	$user_id = (int) $user_id;
	$email   = sanitize_email( $email );
	if ( $user_id <= 0 && ! is_email( $email ) ) {
		return false;
	}

	$customer_tables = apply_filters(
		'my_private_site_cleanup_edd_customer_tables',
		array(
			array(
				'table'         => $wpdb->prefix . 'edd_customers',
				'user_column'   => 'user_id',
				'email_columns' => array( 'email', 'emails' ),
			),
		)
	);
	if ( is_array( $customer_tables ) ) {
		foreach ( $customer_tables as $table_definition ) {
			if ( ! is_array( $table_definition ) || empty( $table_definition['table'] ) ) {
				continue;
			}

			$table = (string) $table_definition['table'];
			if ( ! empty( $table_definition['user_column'] ) && my_private_site_cleanup_db_has_int_match( $table, $table_definition['user_column'], $user_id ) ) {
				return true;
			}

			$email_columns = isset( $table_definition['email_columns'] ) && is_array( $table_definition['email_columns'] ) ? $table_definition['email_columns'] : array();
			foreach ( $email_columns as $email_column ) {
				$use_like = 'emails' === $email_column;
				if ( is_email( $email ) && my_private_site_cleanup_db_has_text_match( $table, $email_column, $email, $use_like ) ) {
					return true;
				}
			}
		}
	}

	$order_tables = apply_filters(
		'my_private_site_cleanup_edd_order_tables',
		array(
			array(
				'table'         => $wpdb->prefix . 'edd_orders',
				'user_columns'  => array( 'user_id' ),
				'email_columns' => array( 'email' ),
			),
			array(
				'table'         => $wpdb->prefix . 'edd_order_addresses',
				'user_columns'  => array(),
				'email_columns' => array( 'email' ),
			),
		)
	);
	if ( is_array( $order_tables ) ) {
		foreach ( $order_tables as $table_definition ) {
			if ( ! is_array( $table_definition ) || empty( $table_definition['table'] ) ) {
				continue;
			}

			$table        = (string) $table_definition['table'];
			$user_columns = isset( $table_definition['user_columns'] ) && is_array( $table_definition['user_columns'] ) ? $table_definition['user_columns'] : array();
			foreach ( $user_columns as $user_column ) {
				if ( my_private_site_cleanup_db_has_int_match( $table, $user_column, $user_id ) ) {
					return true;
				}
			}

			$email_columns = isset( $table_definition['email_columns'] ) && is_array( $table_definition['email_columns'] ) ? $table_definition['email_columns'] : array();
			foreach ( $email_columns as $email_column ) {
				if ( is_email( $email ) && my_private_site_cleanup_db_has_text_match( $table, $email_column, $email ) ) {
					return true;
				}
			}
		}
	}

	if ( my_private_site_cleanup_db_has_int_match( $wpdb->posts, 'post_author', $user_id ) ) {
		$has_edd_payment = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s LIMIT 1",
				$user_id,
				'edd_payment'
			)
		);
		if ( $has_edd_payment ) {
			return true;
		}
	}

	if ( my_private_site_cleanup_db_has_text_match( $wpdb->postmeta, 'meta_key', '_edd_payment_user_id' ) ) {
		if ( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_edd_payment_user_id', (string) $user_id ) ) ) {
			return true;
		}
	}
	if ( is_email( $email ) && my_private_site_cleanup_db_has_text_match( $wpdb->postmeta, 'meta_key', '_edd_payment_user_email' ) ) {
		if ( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_edd_payment_user_email', $email ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check EDD purchases/customers.
 *
 * @param int    $user_id User ID.
 * @param string $email Email.
 * @return true|WP_Error True means has purchases/customer.
 */
function my_private_site_cleanup_user_has_edd_purchase( $user_id, $email ) {
	if ( my_private_site_cleanup_user_has_edd_database_record( $user_id, $email ) ) {
		return true;
	}

	if ( ! function_exists( 'edd_get_users_purchases' ) && ! function_exists( 'edd_get_customer_by' ) ) {
		return false;
	}

	try {
		if ( function_exists( 'edd_get_users_purchases' ) ) {
			$purchases = edd_get_users_purchases( $user_id );
			if ( ! empty( $purchases ) ) {
				return true;
			}
		}

		if ( function_exists( 'edd_get_customer_by' ) ) {
			$customer = edd_get_customer_by( 'user_id', $user_id );
			if ( $customer ) {
				return true;
			}

			$customer = edd_get_customer_by( 'email', $email );
			if ( $customer ) {
				return true;
			}
		}
	} catch ( Exception $e ) {
		return new WP_Error( 'edd_check_failed', $e->getMessage() );
	}

	return false;
}

/**
 * Check WooCommerce database records without requiring WooCommerce to be active.
 *
 * @param int    $user_id User ID.
 * @param string $email Email.
 * @return bool
 */
function my_private_site_cleanup_user_has_woocommerce_database_record( $user_id, $email ) {
	global $wpdb;

	$user_id = (int) $user_id;
	$email   = sanitize_email( $email );
	if ( $user_id <= 0 && ! is_email( $email ) ) {
		return false;
	}

	$tables = apply_filters(
		'my_private_site_cleanup_woocommerce_customer_tables',
		array(
			array(
				'table'         => $wpdb->prefix . 'wc_orders',
				'user_columns'  => array( 'customer_id' ),
				'email_columns' => array( 'billing_email' ),
			),
			array(
				'table'         => $wpdb->prefix . 'wc_order_stats',
				'user_columns'  => array( 'customer_id' ),
				'email_columns' => array(),
			),
			array(
				'table'         => $wpdb->prefix . 'wc_order_addresses',
				'user_columns'  => array(),
				'email_columns' => array( 'email' ),
			),
			array(
				'table'         => $wpdb->prefix . 'wc_customer_lookup',
				'user_columns'  => array( 'user_id' ),
				'email_columns' => array( 'email' ),
			),
		)
	);
	if ( is_array( $tables ) ) {
		foreach ( $tables as $table_definition ) {
			if ( ! is_array( $table_definition ) || empty( $table_definition['table'] ) ) {
				continue;
			}

			$table        = (string) $table_definition['table'];
			$user_columns = isset( $table_definition['user_columns'] ) && is_array( $table_definition['user_columns'] ) ? $table_definition['user_columns'] : array();
			foreach ( $user_columns as $user_column ) {
				if ( my_private_site_cleanup_db_has_int_match( $table, $user_column, $user_id ) ) {
					return true;
				}
			}

			$email_columns = isset( $table_definition['email_columns'] ) && is_array( $table_definition['email_columns'] ) ? $table_definition['email_columns'] : array();
			foreach ( $email_columns as $email_column ) {
				if ( is_email( $email ) && my_private_site_cleanup_db_has_text_match( $table, $email_column, $email ) ) {
					return true;
				}
			}
		}
	}

	$order_post_types = apply_filters( 'my_private_site_cleanup_woocommerce_order_post_types', array( 'shop_order', 'shop_subscription' ) );
	if ( ! is_array( $order_post_types ) ) {
		$order_post_types = array();
	}

	$order_post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $order_post_types ) ) ) );
	if ( ! empty( $order_post_types ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $order_post_types ), '%s' ) );

		if ( $user_id > 0 ) {
			$args = array_merge( array( '_customer_user', (string) $user_id ), $order_post_types );
			if ( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type IN ($placeholders) LIMIT 1", $args ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				return true;
			}
		}

		if ( is_email( $email ) ) {
			$args = array_merge( array( '_billing_email', $email ), $order_post_types );
			if ( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type IN ($placeholders) LIMIT 1", $args ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				return true;
			}
		}
	}

	return false;
}

/**
 * Check WooCommerce orders/customers through WooCommerce APIs when available.
 *
 * @param int    $user_id User ID.
 * @param string $email Email.
 * @return true|WP_Error True means has WooCommerce order/customer activity.
 */
function my_private_site_cleanup_user_has_woocommerce_purchase( $user_id, $email ) {
	$user_id = (int) $user_id;
	$email   = sanitize_email( $email );

	if ( my_private_site_cleanup_user_has_woocommerce_database_record( $user_id, $email ) ) {
		return true;
	}

	if ( $user_id <= 0 || ( ! function_exists( 'wc_get_customer_order_count' ) && ! function_exists( 'wc_get_orders' ) ) ) {
		return false;
	}

	try {
		if ( function_exists( 'wc_get_customer_order_count' ) && (int) wc_get_customer_order_count( $user_id ) > 0 ) {
			return true;
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$order_statuses = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
			$args           = array(
				'limit'  => 1,
				'return' => 'ids',
			);
			if ( ! empty( $order_statuses ) ) {
				$args['status'] = $order_statuses;
			}

			$user_orders = wc_get_orders(
				array_merge(
					$args,
					array(
						'customer_id' => $user_id,
					)
				)
			);
			if ( ! empty( $user_orders ) ) {
				return true;
			}

			if ( is_email( $email ) ) {
				$email_orders = wc_get_orders(
					array_merge(
						$args,
						array(
							'billing_email' => $email,
						)
					)
				);
				if ( ! empty( $email_orders ) ) {
					return true;
				}
			}
		}
	} catch ( Exception $e ) {
		return new WP_Error( 'woocommerce_check_failed', $e->getMessage() );
	}

	return false;
}

/**
 * Return post types ignored by the authored-content safety check.
 *
 * @return array
 */
function my_private_site_cleanup_authored_content_excluded_post_types() {
	$post_types = array(
		'revision',
		'nav_menu_item',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_global_styles',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
	);

	/**
	 * Filter post types ignored by the cleanup authored-content guard.
	 *
	 * @param array $post_types Post type names.
	 */
	$post_types = apply_filters( 'my_private_site_cleanup_authored_content_excluded_post_types', $post_types );
	if ( ! is_array( $post_types ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );
}

/**
 * Check whether user authored content in the posts table.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function my_private_site_cleanup_user_has_content( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$excluded_post_types = my_private_site_cleanup_authored_content_excluded_post_types();
	$args                = array( $user_id );
	$sql                 = "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d";
	if ( ! empty( $excluded_post_types ) ) {
		$sql   .= ' AND post_type NOT IN (' . implode( ',', array_fill( 0, count( $excluded_post_types ), '%s' ) ) . ')';
		$args   = array_merge( $args, $excluded_post_types );
	}
	$sql .= ' LIMIT 1';

	return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Check known store/order lookup tables for user-owned orders.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function my_private_site_cleanup_user_has_store_order( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$tables = apply_filters(
		'my_private_site_cleanup_order_customer_tables',
		array(
			array(
				'table'  => $wpdb->prefix . 'wc_orders',
				'column' => 'customer_id',
			),
			array(
				'table'  => $wpdb->prefix . 'wc_order_stats',
				'column' => 'customer_id',
			),
		)
	);
	if ( ! is_array( $tables ) ) {
		return false;
	}

	foreach ( $tables as $table_definition ) {
		if ( ! is_array( $table_definition ) || empty( $table_definition['table'] ) || empty( $table_definition['column'] ) ) {
			continue;
		}

		$table  = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table_definition['table'] );
		$column = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table_definition['column'] );
		if ( '' === $table || '' === $column || ! my_private_site_cleanup_table_column_exists( $table, $column ) ) {
			continue;
		}

		if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `$table` WHERE `$column` = %d LIMIT 1", $user_id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return true;
		}
	}

	return false;
}

/**
 * Check known postmeta ownership keys for user-linked records.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function my_private_site_cleanup_user_has_linked_postmeta_record( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$meta_keys = apply_filters( 'my_private_site_cleanup_user_linked_postmeta_keys', array( '_customer_user' ) );
	if ( ! is_array( $meta_keys ) ) {
		return false;
	}

	$meta_keys = array_values( array_unique( array_filter( array_map( 'sanitize_key', $meta_keys ) ) ) );
	if ( empty( $meta_keys ) ) {
		return false;
	}

	$args = array_merge( $meta_keys, array( (string) $user_id ) );
	$sql  = 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key IN (' . implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ) . ') AND meta_value = %s LIMIT 1';

	return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Check whether user authored comments.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function my_private_site_cleanup_user_has_comments( $user_id ) {
	$comments = get_comments(
		array(
			'user_id' => $user_id,
			'number'  => 1,
			'fields'  => 'ids',
			'status'  => 'all',
		)
	);

	return ! empty( $comments );
}

/**
 * Determine if user is recently active.
 *
 * @param int $user_id User ID.
 * @param int $days Recency window.
 * @return bool
 */
function my_private_site_cleanup_user_recently_active( $user_id, $days = 90 ) {
	$cutoff = time() - ( absint( $days ) * DAY_IN_SECONDS );
	$tokens = get_user_meta( $user_id, 'session_tokens', true );
	if ( is_array( $tokens ) ) {
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && ! empty( $token['expiration'] ) && (int) $token['expiration'] >= $cutoff ) {
				return true;
			}
		}
	}

	$keys = apply_filters(
		'my_private_site_cleanup_last_login_keys',
		array( 'last_login', 'wp-last-login', 'wfls-last-login', 'wc_last_active', '_last_login' )
	);
	if ( ! is_array( $keys ) ) {
		$keys = array();
	}

	foreach ( $keys as $key ) {
		$value = get_user_meta( $user_id, sanitize_key( $key ), true );
		if ( '' === $value || null === $value ) {
			continue;
		}

		if ( is_numeric( $value ) ) {
			$timestamp = (int) $value;
		} else {
			$timestamp = strtotime( (string) $value );
		}

		if ( $timestamp && $timestamp >= $cutoff ) {
			return true;
		}
	}

	return false;
}

/**
 * Check the hashed mailing-list allowlist.
 *
 * @param string $email Email.
 * @param bool   $fold_gmail Whether Gmail folding is enabled.
 * @return bool
 */
function my_private_site_cleanup_email_on_allowlist( $email, $fold_gmail ) {
	global $wpdb;

	$hash = my_private_site_cleanup_email_hash( $email, $fold_gmail );
	if ( '' === $hash ) {
		return false;
	}

	$table = my_private_site_cleanup_allowlist_table_name();
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $table WHERE email_hash = %s LIMIT 1", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Return an absolute keep reason for a user.
 *
 * @param WP_User $user User.
 * @param array   $state State.
 * @return string
 */
function my_private_site_cleanup_get_absolute_keep_reason( $user, $state ) {
	if ( ! $user instanceof WP_User ) {
		return 'User load failed';
	}

	$user_id = (int) $user->ID;
	if ( get_current_user_id() === $user_id ) {
		return 'Current user';
	}

	$admin_allowlist = isset( $state['admin_allowlist'] ) && is_array( $state['admin_allowlist'] ) ? $state['admin_allowlist'] : array();
	if ( in_array( $user->user_login, $admin_allowlist, true ) || in_array( strtolower( $user->user_email ), $admin_allowlist, true ) ) {
		return 'Manual keep-list';
	}

	$edd = my_private_site_cleanup_user_has_edd_purchase( $user_id, $user->user_email );
	if ( is_wp_error( $edd ) ) {
		return 'EDD check failed';
	}
	if ( $edd ) {
		return 'EDD customer';
	}

	$woocommerce = my_private_site_cleanup_user_has_woocommerce_purchase( $user_id, $user->user_email );
	if ( is_wp_error( $woocommerce ) ) {
		return 'WooCommerce check failed';
	}
	if ( $woocommerce ) {
		return 'WooCommerce customer';
	}

	if ( my_private_site_cleanup_user_has_store_order( $user_id ) ) {
		return 'Store order/customer record';
	}

	if ( my_private_site_cleanup_user_has_linked_postmeta_record( $user_id ) ) {
		return 'Linked user record';
	}

	if ( my_private_site_cleanup_user_has_content( $user_id ) ) {
		return 'Authored content';
	}

	if ( my_private_site_cleanup_user_has_comments( $user_id ) ) {
		return 'Authored comment';
	}

	if ( ! empty( $state['recent_login_protection_enabled'] ) && my_private_site_cleanup_user_recently_active( $user_id, isset( $state['recent_days'] ) ? (int) $state['recent_days'] : 90 ) ) {
		return 'Recently active';
	}

	return '';
}

/**
 * Return selected cleanup spam signal keys.
 *
 * @param array $state State.
 * @return array
 */
function my_private_site_cleanup_selected_spam_signal_keys( $state ) {
	$selected = isset( $state['cleanup_spam_signals'] ) && is_array( $state['cleanup_spam_signals'] ) ? $state['cleanup_spam_signals'] : my_private_site_cleanup_default_spam_signals();
	$allowed  = function_exists( 'my_private_site_spam_guard_allowed_signal_keys' ) ? my_private_site_spam_guard_allowed_signal_keys( 'cleanup' ) : array();
	$signals  = array();
	foreach ( $selected as $signal ) {
		$signal = sanitize_key( $signal );
		if ( isset( $allowed[ $signal ] ) ) {
			$signals[] = $signal;
		}
	}

	return array_values( array_unique( $signals ) );
}

/**
 * Return a signature for inputs that affect signal analysis.
 *
 * @param array $state State.
 * @return string
 */
function my_private_site_cleanup_analysis_signature( $state ) {
	$admin_allowlist = isset( $state['admin_allowlist'] ) && is_array( $state['admin_allowlist'] ) ? $state['admin_allowlist'] : array();
	sort( $admin_allowlist );

	$payload = array(
		'date_start'           => isset( $state['date_start'] ) ? (string) $state['date_start'] : '',
		'date_end'             => isset( $state['date_end'] ) ? (string) $state['date_end'] : '',
		'admin_allowlist'      => $admin_allowlist,
		'fold_gmail'           => ! empty( $state['fold_gmail'] ),
		'mailing_list_mode'    => isset( $state['mailing_list_mode'] ) ? sanitize_key( $state['mailing_list_mode'] ) : 'absolute',
		'cleanup_spam_signals' => my_private_site_cleanup_selected_spam_signal_keys( $state ),
		'recent_login_protection_enabled' => ! empty( $state['recent_login_protection_enabled'] ),
		'recent_days'          => isset( $state['recent_days'] ) ? (int) $state['recent_days'] : 90,
		'allowlist_hashes'     => isset( $state['totals']['allowlist_hashes'] ) ? (int) $state['totals']['allowlist_hashes'] : 0,
		'signal_version'       => JR_PS_CLEANUP_SIGNAL_VERSION,
	);

	return md5( wp_json_encode( $payload ) );
}

/**
 * Mark cached analysis stale if setup inputs changed.
 *
 * @param array $state State.
 * @return array
 */
function my_private_site_cleanup_refresh_analysis_staleness( $state ) {
	$signature = my_private_site_cleanup_analysis_signature( $state );
	if ( ! empty( $state['analysis']['signature'] ) && $signature !== $state['analysis']['signature'] ) {
		$state['analysis']['stale'] = true;
	}

	return $state;
}

/**
 * Reset signal analysis data for a fresh run.
 *
 * @param array  $state     State.
 * @param string $signature Signature.
 * @return array
 */
function my_private_site_cleanup_reset_analysis( $state, $signature = '' ) {
	$selected = my_private_site_cleanup_selected_spam_signal_keys( $state );
	$matches  = array();
	foreach ( $selected as $signal ) {
		$matches[ $signal ] = 0;
	}

	$state['analysis'] = array(
		'signature'     => '' !== $signature ? $signature : my_private_site_cleanup_analysis_signature( $state ),
		'done'          => false,
		'stale'         => false,
		'last_user_id'  => 0,
		'scanned'       => 0,
		'excluded'      => 0,
		'matched_users' => 0,
		'matches'       => $matches,
		'selected'      => $selected,
		'updated_at'    => '',
	);

	return $state;
}

/**
 * Return the cleanup label for a spam signal.
 *
 * @param string $signal Signal key.
 * @return string
 */
function my_private_site_cleanup_spam_signal_label( $signal ) {
	if ( function_exists( 'my_private_site_spam_guard_signal_definitions' ) ) {
		$definitions = my_private_site_spam_guard_signal_definitions();
		if ( isset( $definitions[ $signal ]['cleanup_label'] ) ) {
			return (string) $definitions[ $signal ]['cleanup_label'];
		}
	}

	return $signal;
}

/**
 * Return selected spam signals that match a user.
 *
 * @param WP_User $user User.
 * @param array   $state State.
 * @return array|WP_Error
 */
function my_private_site_cleanup_matching_spam_signals( $user, $state ) {
	if ( ! $user instanceof WP_User ) {
		return array();
	}

	$selected    = my_private_site_cleanup_selected_spam_signal_keys( $state );
	$definitions = function_exists( 'my_private_site_spam_guard_signal_definitions' ) ? my_private_site_spam_guard_signal_definitions() : array();
	$fast        = array();
	$slow        = array();
	foreach ( $selected as $signal ) {
		if ( ! empty( $definitions[ $signal ]['slow'] ) || ! empty( $definitions[ $signal ]['external'] ) ) {
			$slow[] = $signal;
		} else {
			$fast[] = $signal;
		}
	}

	$signals = array();
	foreach ( array_merge( $fast, $slow ) as $signal ) {
		if ( 'bio_has_url' === $signal ) {
			$matched = function_exists( 'my_private_site_spam_guard_bio_has_url' ) && my_private_site_spam_guard_bio_has_url( $user->ID );
		} else {
			$matched = function_exists( 'my_private_site_spam_guard_signal_matches' ) && my_private_site_spam_guard_signal_matches( $signal, $user->user_login, $user->user_email );
		}
		if ( 'stop_forum_spam' === $signal && function_exists( 'my_private_site_spam_guard_stop_forum_spam_last_error' ) ) {
			$stop_forum_spam_error = my_private_site_spam_guard_stop_forum_spam_last_error();
			if ( '' !== $stop_forum_spam_error ) {
				return new WP_Error( 'stop_forum_spam_lookup_failed', $stop_forum_spam_error );
			}
		}

		if ( $matched ) {
			$signals[ $signal ] = my_private_site_cleanup_spam_signal_label( $signal );
		}
	}

	return $signals;
}

/**
 * Return spam signals allowed to override mailing-list protection in Strong mode.
 *
 * @param array $signals Matched signal labels keyed by signal key.
 * @return array
 */
function my_private_site_cleanup_mailing_list_override_signals( $signals ) {
	if ( ! is_array( $signals ) ) {
		return array();
	}

	$non_override = apply_filters( 'my_private_site_cleanup_mailing_list_non_override_signals', array( 'email_low_vowel' ) );
	if ( ! is_array( $non_override ) ) {
		$non_override = array( 'email_low_vowel' );
	}

	$blocked = array_fill_keys( array_map( 'sanitize_key', $non_override ), true );

	return array_diff_key( $signals, $blocked );
}

/**
 * Evaluate cleanup disposition for a user.
 *
 * @param WP_User $user User.
 * @param array   $state State.
 * @return array|WP_Error
 */
function my_private_site_cleanup_evaluate_user( $user, $state ) {
	$result = array(
		'skip_reason'            => '',
		'mailing_list_match'     => false,
		'mailing_list_override'  => false,
		'spam_signals'           => array(),
	);

	$absolute_reason = my_private_site_cleanup_get_absolute_keep_reason( $user, $state );
	if ( '' !== $absolute_reason ) {
		$result['skip_reason'] = $absolute_reason;
		return $result;
	}

	$result['mailing_list_match'] = my_private_site_cleanup_email_on_allowlist( $user->user_email, ! empty( $state['fold_gmail'] ) );
	if ( $result['mailing_list_match'] ) {
		$mode = isset( $state['mailing_list_mode'] ) ? sanitize_key( $state['mailing_list_mode'] ) : 'absolute';
		if ( 'strong' !== $mode ) {
			$result['skip_reason'] = 'Mailing list';
			return $result;
		}
	}

	$result['spam_signals'] = my_private_site_cleanup_matching_spam_signals( $user, $state );
	if ( is_wp_error( $result['spam_signals'] ) ) {
		return $result['spam_signals'];
	}
	if ( empty( $result['spam_signals'] ) ) {
		$result['skip_reason'] = $result['mailing_list_match'] ? 'Mailing list' : 'No selected spam signal';
		return $result;
	}
	$result['mailing_list_override'] = (bool) $result['mailing_list_match'];
	if ( $result['mailing_list_match'] ) {
		$result['spam_signals'] = my_private_site_cleanup_mailing_list_override_signals( $result['spam_signals'] );
		if ( empty( $result['spam_signals'] ) ) {
			$result['skip_reason']           = 'Mailing list';
			$result['mailing_list_override'] = false;
			return $result;
		}
	}

	return $result;
}

/**
 * Apply deletion-time safety protections without re-running spam signals.
 *
 * @param WP_User $user User.
 * @param array   $state State.
 * @return string Empty means pending delete; otherwise skip reason.
 */
function my_private_site_cleanup_get_user_skip_reason( $user, $state ) {
	$absolute_reason = my_private_site_cleanup_get_absolute_keep_reason( $user, $state );
	if ( '' !== $absolute_reason ) {
		return $absolute_reason;
	}

	if ( $user instanceof WP_User ) {
		$mailing_list_match = my_private_site_cleanup_email_on_allowlist( $user->user_email, ! empty( $state['fold_gmail'] ) );
		$mode               = isset( $state['mailing_list_mode'] ) ? sanitize_key( $state['mailing_list_mode'] ) : 'absolute';
		if ( $mailing_list_match && 'strong' !== $mode ) {
			return 'Mailing list';
		}
	}

	return '';
}

/**
 * Extract email candidates from a delimited row.
 *
 * @param array $row Row.
 * @return array
 */
function my_private_site_cleanup_row_email_scores( $row ) {
	$scores = array();
	foreach ( $row as $index => $value ) {
		$value = trim( (string) $value );
		if ( is_email( $value ) ) {
			$scores[ $index ] = isset( $scores[ $index ] ) ? $scores[ $index ] + 1 : 1;
		}
	}

	return $scores;
}

/**
 * Detect delimiter from a line.
 *
 * @param string $line Line.
 * @return string
 */
function my_private_site_cleanup_detect_delimiter( $line ) {
	$candidates = array( ',', "\t", ';' );
	$best       = ',';
	$best_count = 0;
	foreach ( $candidates as $candidate ) {
		$count = substr_count( $line, $candidate );
		if ( $count > $best_count ) {
			$best       = $candidate;
			$best_count = $count;
		}
	}

	return $best;
}

/**
 * Parse an email from a line.
 *
 * @param string $line Line.
 * @param array  $upload Upload state.
 * @return string
 */
function my_private_site_cleanup_extract_email_from_line( $line, $upload ) {
	$line = trim( $line );
	if ( '' === $line ) {
		return '';
	}

	if ( 'txt' === ( $upload['type'] ?? '' ) ) {
		return is_email( $line ) ? $line : '';
	}

	$delimiter = isset( $upload['delimiter'] ) ? (string) $upload['delimiter'] : ',';
	$column    = isset( $upload['email_column'] ) ? (int) $upload['email_column'] : 0;
	$row       = str_getcsv( $line, $delimiter );
	$email     = isset( $row[ $column ] ) ? trim( $row[ $column ] ) : '';

	return is_email( $email ) ? $email : '';
}

/**
 * AJAX: return current state.
 */
function my_private_site_cleanup_ajax_state() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$state   = my_private_site_cleanup_get_state();
	$updated = my_private_site_cleanup_maybe_pause_stale_scan( $state );
	if ( $updated !== $state ) {
		$state = $updated;
		my_private_site_cleanup_update_state( $state );
	}
	$summary = isset( $state['summary'] ) && is_array( $state['summary'] ) ? $state['summary'] : array();

	wp_send_json_success(
		array(
			'state'      => my_private_site_cleanup_public_state( $state ),
			'years'      => my_private_site_cleanup_summary_year_rows( $summary ),
			'date_range' => my_private_site_cleanup_summary_date_range( $summary ),
			'nonce'      => wp_create_nonce( 'jr_ps_cleanup_nonce' ),
		)
	);
}

/**
 * AJAX: build the date/year summary in a bounded batch.
 */
function my_private_site_cleanup_ajax_summary_batch() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();
	if ( ! my_private_site_cleanup_acquire_lock( 'summary' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another date summary batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( ! isset( $state['summary'] ) || ! is_array( $state['summary'] ) ) {
		$state['summary'] = array(
			'done'         => false,
			'last_user_id' => 0,
			'scanned'      => 0,
			'eligible'     => 0,
			'years'        => array(),
			'date_start'   => '',
			'date_end'     => '',
			'updated_at'   => '',
		);
	}
	if ( ! empty( $state['summary']['done'] ) ) {
		my_private_site_cleanup_release_lock( 'summary' );
		wp_send_json_success(
			array(
				'state'      => my_private_site_cleanup_public_state( $state ),
				'years'      => my_private_site_cleanup_summary_year_rows( $state['summary'] ),
				'date_range' => my_private_site_cleanup_summary_date_range( $state['summary'] ),
				'done'       => true,
				'nonce'      => wp_create_nonce( 'jr_ps_cleanup_nonce' ),
			)
		);
	}

	$rows = my_private_site_cleanup_user_rows_after_id( (int) $state['summary']['last_user_id'], JR_PS_CLEANUP_SUMMARY_BATCH_SIZE );
	foreach ( $rows as $row ) {
		$state['summary']['last_user_id'] = (int) $row['ID'];
		$state['summary']['scanned']++;
		if ( ! my_private_site_cleanup_capabilities_are_candidate_eligible( isset( $row['capabilities'] ) ? $row['capabilities'] : array() ) ) {
			continue;
		}

		$date = gmdate( 'Y-m-d', strtotime( $row['user_registered'] ) );
		$year = gmdate( 'Y', strtotime( $row['user_registered'] ) );
		if ( ! isset( $state['summary']['years'][ $year ] ) ) {
			$state['summary']['years'][ $year ] = 0;
		}
		$state['summary']['years'][ $year ]++;
		$state['summary']['eligible']++;
		if ( '' === $state['summary']['date_start'] || $date < $state['summary']['date_start'] ) {
			$state['summary']['date_start'] = $date;
		}
		if ( '' === $state['summary']['date_end'] || $date > $state['summary']['date_end'] ) {
			$state['summary']['date_end'] = $date;
		}
	}

	$done = count( $rows ) < JR_PS_CLEANUP_SUMMARY_BATCH_SIZE;
	if ( $done ) {
		$state['summary']['done'] = true;
	}
	$state['summary']['updated_at'] = current_time( 'mysql', true );
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'summary' );

	wp_send_json_success(
		array(
			'state'      => my_private_site_cleanup_public_state( $state ),
			'years'      => my_private_site_cleanup_summary_year_rows( $state['summary'] ),
			'date_range' => my_private_site_cleanup_summary_date_range( $state['summary'] ),
			'done'       => $done,
			'nonce'      => wp_create_nonce( 'jr_ps_cleanup_nonce' ),
		)
	);
}

/**
 * AJAX: reset/start over.
 */
function my_private_site_cleanup_ajax_reset() {
	my_private_site_cleanup_ajax_guard();

	$state = my_private_site_cleanup_get_state();
	if ( ! empty( $state['delete_started'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Start Over is disabled after real deletions have started.', 'my-private-site' ) ), 400 );
	}

	my_private_site_cleanup_reset_all();
	my_private_site_cleanup_ensure_tables();

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( my_private_site_cleanup_get_state() ) ) );
}

/**
 * AJAX: reset after a completed real deletion run.
 */
function my_private_site_cleanup_ajax_prepare_next_run() {
	my_private_site_cleanup_ajax_guard();

	$state = my_private_site_cleanup_get_state();
	if ( 'complete' !== $state['phase'] && empty( $state['completed_awaiting_reset'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Prepare another run is available after the deletion run completes.', 'my-private-site' ) ), 400 );
	}

	my_private_site_cleanup_reset_all();
	my_private_site_cleanup_ensure_tables();

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( my_private_site_cleanup_get_state() ) ) );
}

/**
 * AJAX: validate setup and continue to dry run.
 */
function my_private_site_cleanup_ajax_save_setup() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$date_start = isset( $_POST['date_start'] ) ? sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) : '';
	$date_end   = isset( $_POST['date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['date_end'] ) ) : '';
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) || strtotime( $date_start ) > strtotime( $date_end ) ) {
		wp_send_json_error( array( 'message' => __( 'Please choose a valid registration date window.', 'my-private-site' ) ), 400 );
	}

	$mailing_list_mode = isset( $_POST['mailing_list_mode'] ) ? sanitize_key( wp_unslash( $_POST['mailing_list_mode'] ) ) : 'absolute';
	if ( ! in_array( $mailing_list_mode, array( 'absolute', 'strong' ), true ) ) {
		$mailing_list_mode = 'absolute';
	}
	$allowed_signals = function_exists( 'my_private_site_spam_guard_allowed_signal_keys' ) ? my_private_site_spam_guard_allowed_signal_keys( 'cleanup' ) : array();
	$spam_signals    = array();
	$posted_signals  = isset( $_POST['cleanup_spam_signals'] ) ? sanitize_text_field( wp_unslash( $_POST['cleanup_spam_signals'] ) ) : '';
	foreach ( preg_split( '/[\s,]+/', $posted_signals ) as $signal ) {
		$signal = sanitize_key( $signal );
		if ( '' !== $signal && isset( $allowed_signals[ $signal ] ) ) {
			$spam_signals[] = $signal;
		}
	}
	$spam_signals = array_values( array_unique( $spam_signals ) );
	if ( empty( $spam_signals ) ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one spam signal before continuing to the dry run.', 'my-private-site' ) ), 400 );
	}

	$state                       = my_private_site_cleanup_get_state();
	$previous_phase              = isset( $state['phase'] ) ? $state['phase'] : 'idle';
	$previous_scan_signature     = isset( $state['scan_signature'] ) ? $state['scan_signature'] : '';
	$state['phase']              = 'defined';
	$state['date_start']             = $date_start;
	$state['date_end']               = $date_end;
	$state['admin_allowlist']        = my_private_site_cleanup_parse_admin_allowlist( isset( $_POST['admin_allowlist'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_allowlist'] ) ) : '' );
	$state['fold_gmail']             = ! empty( $_POST['fold_gmail'] );
	$state['mailing_list_mode']      = $mailing_list_mode;
	$state['cleanup_spam_signals']   = $spam_signals;
	$state['recent_login_protection_enabled'] = ! empty( $_POST['recent_login_protection_enabled'] );
	$state['recent_days']            = isset( $_POST['recent_days'] ) ? max( 1, absint( $_POST['recent_days'] ) ) : 90;
	$state['backup_ack']             = ! empty( $_POST['backup_ack'] );
	$state['confirmation_token']     = wp_generate_password( 12, false, false );
	$state['delete_confirmed']       = false;
	$state['delete_started']         = false;
	$state['completed_awaiting_reset'] = false;
	$state                           = my_private_site_cleanup_refresh_analysis_staleness( $state );
	$current_signature               = my_private_site_cleanup_analysis_signature( $state );
	if ( 'scan_paused' === $previous_phase && $current_signature === $previous_scan_signature ) {
		$state['phase'] = 'scan_paused';
	}
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success(
		array(
			'state'              => my_private_site_cleanup_public_state( $state ),
			'admin_allow_count'  => count( $state['admin_allowlist'] ),
		)
	);
}

/**
 * AJAX: start signal analysis.
 */
function my_private_site_cleanup_ajax_start_analysis() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['date_start'] ) || empty( $state['date_end'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Choose a valid registration date window before analyzing spam signals.', 'my-private-site' ) ), 400 );
	}
	if ( empty( my_private_site_cleanup_selected_spam_signal_keys( $state ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one spam signal before analyzing.', 'my-private-site' ) ), 400 );
	}

	$signature = my_private_site_cleanup_analysis_signature( $state );
	if ( ! empty( $state['analysis']['done'] ) && empty( $state['analysis']['stale'] ) && isset( $state['analysis']['signature'] ) && $signature === $state['analysis']['signature'] ) {
		wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => true, 'cached' => true ) );
	}

	if ( isset( $state['analysis']['signature'] ) && $signature === $state['analysis']['signature'] && empty( $state['analysis']['stale'] ) && ( 'analysis_paused' === $state['phase'] || ( ! empty( $state['analysis']['last_user_id'] ) && empty( $state['analysis']['done'] ) ) ) ) {
		$state['phase'] = 'analyzing';
		my_private_site_cleanup_update_state( $state );
		wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => false, 'cached' => false ) );
	}

	$state          = my_private_site_cleanup_reset_analysis( $state, $signature );
	$state['phase'] = 'analyzing';
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => false, 'cached' => false ) );
}

/**
 * AJAX: pause signal analysis after the current batch.
 */
function my_private_site_cleanup_ajax_pause_analysis() {
	my_private_site_cleanup_ajax_guard();

	$state = my_private_site_cleanup_get_state();
	if ( 'analyzing' === $state['phase'] ) {
		$state['phase'] = 'analysis_paused';
		my_private_site_cleanup_update_state( $state );
	}

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: cancel signal analysis.
 */
function my_private_site_cleanup_ajax_cancel_analysis() {
	my_private_site_cleanup_ajax_guard();

	$state          = my_private_site_cleanup_get_state();
	$state          = my_private_site_cleanup_reset_analysis( $state );
	$state['phase'] = 'defined';
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: analyze one signal batch.
 */
function my_private_site_cleanup_ajax_analysis_batch() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();
	if ( ! my_private_site_cleanup_acquire_lock( 'analysis' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another signal analysis batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['analysis']['signature'] ) ) {
		my_private_site_cleanup_release_lock( 'analysis' );
		wp_send_json_error( array( 'message' => __( 'Signal analysis has not been started.', 'my-private-site' ) ), 400 );
	}
	if ( 'analyzing' !== $state['phase'] ) {
		my_private_site_cleanup_release_lock( 'analysis' );
		wp_send_json_error( array( 'message' => __( 'Signal analysis is not running.', 'my-private-site' ) ), 400 );
	}

	$users   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT u.ID
			FROM {$wpdb->users} u
			WHERE u.ID > %d
				AND u.user_registered BETWEEN %s AND %s
			ORDER BY u.ID ASC
			LIMIT %d",
			(int) $state['analysis']['last_user_id'],
			$state['date_start'] . ' 00:00:00',
			$state['date_end'] . ' 23:59:59',
			JR_PS_CLEANUP_ANALYSIS_BATCH_SIZE
		),
		ARRAY_A
	);

	$progress_count = 0;
	foreach ( $users as $row ) {
		$progress_count++;
		$user_id = (int) $row['ID'];
		$state['analysis']['last_user_id'] = $user_id;
		$save_analysis_progress = ( 0 === $progress_count % JR_PS_CLEANUP_ANALYSIS_PROGRESS_INTERVAL );
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			if ( $save_analysis_progress ) {
				my_private_site_cleanup_update_state( $state );
			}
			continue;
		}

		if ( ! my_private_site_cleanup_user_is_candidate_eligible( $user ) ) {
			if ( $save_analysis_progress ) {
				my_private_site_cleanup_update_state( $state );
			}
			continue;
		}

		$state['analysis']['scanned']++;

		$absolute_reason = my_private_site_cleanup_get_absolute_keep_reason( $user, $state );
		if ( '' !== $absolute_reason ) {
			$state['analysis']['excluded']++;
			if ( $save_analysis_progress ) {
				my_private_site_cleanup_update_state( $state );
			}
			continue;
		}

		$mailing_list_match = my_private_site_cleanup_email_on_allowlist( $user->user_email, ! empty( $state['fold_gmail'] ) );
		$mode               = isset( $state['mailing_list_mode'] ) ? sanitize_key( $state['mailing_list_mode'] ) : 'absolute';
		if ( $mailing_list_match && 'strong' !== $mode ) {
			$state['analysis']['excluded']++;
			if ( $save_analysis_progress ) {
				my_private_site_cleanup_update_state( $state );
			}
			continue;
		}

		$signals = my_private_site_cleanup_matching_spam_signals( $user, $state );
		if ( empty( $signals ) ) {
			if ( $save_analysis_progress ) {
				my_private_site_cleanup_update_state( $state );
			}
			continue;
		}

		$state['analysis']['matched_users']++;
		foreach ( array_keys( $signals ) as $signal ) {
			if ( ! isset( $state['analysis']['matches'][ $signal ] ) ) {
				$state['analysis']['matches'][ $signal ] = 0;
			}
			$state['analysis']['matches'][ $signal ]++;
		}
		if ( $save_analysis_progress ) {
			my_private_site_cleanup_update_state( $state );
		}
	}

	$done = count( $users ) < JR_PS_CLEANUP_ANALYSIS_BATCH_SIZE;
	if ( $done ) {
		$state['phase']                  = 'defined';
		$state['analysis']['done']       = true;
		$state['analysis']['stale']      = false;
		$state['analysis']['updated_at'] = current_time( 'mysql' );
	}
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'analysis' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => $done ) );
}

/**
 * AJAX: upload allowlist file and detect email column.
 */
function my_private_site_cleanup_ajax_upload_allowlist() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	if ( empty( $_FILES['allowlist_file']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Please choose a CSV or text file.', 'my-private-site' ) ), 400 );
	}

	$file = $_FILES['allowlist_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Only .csv and .txt files are accepted.', 'my-private-site' ) ), 400 );
	}

	$max = min( (int) wp_max_upload_size(), 100 * MB_IN_BYTES );
	if ( ! empty( $file['size'] ) && (int) $file['size'] > $max ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: %s: file size. */
					__( 'This file is too large. Maximum size is %s. Split the list into multiple uploads or raise the server upload limit.', 'my-private-site' ),
					size_format( $max )
				),
			),
			400
		);
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		wp_send_json_error( array( 'message' => esc_html( $uploads['error'] ) ), 500 );
	}

	$dir = trailingslashit( $uploads['basedir'] ) . 'my-private-site-cleanup/' . wp_generate_password( 24, false, false );
	if ( ! wp_mkdir_p( $dir ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not create a protected upload folder.', 'my-private-site' ) ), 500 );
	}
	file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( trailingslashit( $dir ) . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( trailingslashit( $dir ) . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	$target = trailingslashit( $dir ) . wp_generate_password( 20, false, false ) . '.' . $ext;
	if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
		wp_send_json_error( array( 'message' => __( 'Could not store the uploaded file.', 'my-private-site' ) ), 500 );
	}

	$total_lines  = 0;
	$sample_lines = array();
	$handle       = fopen( $target, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( $handle ) {
		while ( false !== ( $line = fgets( $handle ) ) ) {
			$total_lines++;
			if ( count( $sample_lines ) < 25 ) {
				$sample_lines[] = $line;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	$delimiter = isset( $sample_lines[0] ) ? my_private_site_cleanup_detect_delimiter( $sample_lines[0] ) : ',';
	$headers   = array();
	$samples   = array();
	$column    = 0;
	if ( 'csv' === $ext && ! empty( $sample_lines ) ) {
		$headers = str_getcsv( $sample_lines[0], $delimiter );
		foreach ( $headers as $index => $header ) {
			if ( preg_match( '/e-?mail/i', (string) $header ) ) {
				$column = (int) $index;
				break;
			}
		}
		if ( 0 === $column ) {
			$scores = array();
			foreach ( array_slice( $sample_lines, 1, 10 ) as $line ) {
				foreach ( my_private_site_cleanup_row_email_scores( str_getcsv( $line, $delimiter ) ) as $index => $score ) {
					$scores[ $index ] = isset( $scores[ $index ] ) ? $scores[ $index ] + $score : $score;
				}
			}
			if ( ! empty( $scores ) ) {
				arsort( $scores );
				$column = (int) array_key_first( $scores );
			}
		}
		foreach ( array_slice( $sample_lines, 1, 3 ) as $line ) {
			$row = str_getcsv( $line, $delimiter );
			if ( isset( $row[ $column ] ) ) {
				$samples[] = trim( $row[ $column ] );
			}
		}
	} elseif ( ! empty( $sample_lines ) ) {
		foreach ( array_slice( $sample_lines, 0, 3 ) as $line ) {
			$samples[] = trim( $line );
		}
	}

	$state           = my_private_site_cleanup_get_state();
	$state['upload'] = array(
		'path'          => $target,
		'type'          => $ext,
		'delimiter'     => $delimiter,
		'email_column'  => $column,
		'headers'       => $headers,
		'samples'       => $samples,
		'total_lines'   => $total_lines,
		'processed'     => 0,
		'byte_offset'   => 0,
		'imported'      => 0,
		'invalid'       => 0,
		'has_header'    => ( 'csv' === $ext && ! empty( $headers ) ),
	);
	$state['phase']  = 'allowlist_uploaded';
	$state           = my_private_site_cleanup_refresh_analysis_staleness( $state );
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: import allowlist batch.
 */
function my_private_site_cleanup_ajax_import_allowlist_batch() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();
	if ( ! my_private_site_cleanup_acquire_lock( 'import' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another import batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['upload']['path'] ) || ! file_exists( $state['upload']['path'] ) ) {
		my_private_site_cleanup_release_lock( 'import' );
		wp_send_json_error( array( 'message' => __( 'No uploaded list is ready to import.', 'my-private-site' ) ), 400 );
	}

	$column = isset( $_POST['email_column'] ) ? absint( $_POST['email_column'] ) : (int) $state['upload']['email_column'];
	$state['upload']['email_column'] = $column;
	$path   = $state['upload']['path'];
	$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $handle ) {
		my_private_site_cleanup_release_lock( 'import' );
		wp_send_json_error( array( 'message' => __( 'Could not reopen the uploaded file.', 'my-private-site' ) ), 500 );
	}

	if ( ! empty( $state['upload']['byte_offset'] ) ) {
		fseek( $handle, (int) $state['upload']['byte_offset'] );
	} elseif ( ! empty( $state['upload']['has_header'] ) ) {
		fgets( $handle );
		$state['upload']['processed'] = max( 1, (int) $state['upload']['processed'] );
	}

	$values    = array();
	$processed = 0;
	$imported  = 0;
	$invalid   = 0;
	while ( $processed < JR_PS_CLEANUP_IMPORT_BATCH_SIZE && false !== ( $line = fgets( $handle ) ) ) {
		$processed++;
		$email = my_private_site_cleanup_extract_email_from_line( $line, $state['upload'] );
		$hash  = my_private_site_cleanup_email_hash( $email, ! empty( $state['fold_gmail'] ) );
		if ( '' === $hash ) {
			$invalid++;
			continue;
		}
		$values[] = $wpdb->prepare( '(%s)', $hash );
		$imported++;
	}
	$done = feof( $handle );
	$state['upload']['byte_offset'] = ftell( $handle );
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	if ( ! empty( $values ) ) {
		$table = my_private_site_cleanup_allowlist_table_name();
		$wpdb->query( 'INSERT IGNORE INTO ' . $table . ' (email_hash) VALUES ' . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	$state['upload']['processed'] += $processed;
	$state['upload']['imported']  += $imported;
	$state['upload']['invalid']   += $invalid;
	$state['totals']['imported']  += $imported;
	$state['totals']['import_invalid'] += $invalid;
	$state['totals']['allowlist_hashes'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . my_private_site_cleanup_allowlist_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $done ) {
		wp_delete_file( $path );
		$state['upload']['path'] = '';
		$state['phase']          = 'allowlist_imported';
	}
	$state = my_private_site_cleanup_refresh_analysis_staleness( $state );
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'import' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => $done ) );
}

/**
 * AJAX: start dry-run scan.
 */
function my_private_site_cleanup_ajax_start_scan() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['backup_ack'] ) || empty( $state['date_start'] ) || empty( $state['date_end'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Complete the backup gate and candidate definition before scanning.', 'my-private-site' ) ), 400 );
	}
	if ( empty( my_private_site_cleanup_selected_spam_signal_keys( $state ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one spam signal before scanning.', 'my-private-site' ) ), 400 );
	}

	$signature = my_private_site_cleanup_analysis_signature( $state );
	if ( $signature === $state['scan_signature'] && ( 'scan_paused' === $state['phase'] || ( 'scanning' === $state['phase'] && ! empty( $state['scan_last_user_id'] ) ) ) ) {
		$state['phase'] = 'scanning';
		$state          = my_private_site_cleanup_clear_scan_pause_reason( $state );
		my_private_site_cleanup_update_state( $state );
		wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'resumed' => true ) );
	}

	$wpdb->query( 'TRUNCATE TABLE ' . my_private_site_cleanup_queue_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total_users                              = my_private_site_cleanup_count_users_in_date_window( $state['date_start'], $state['date_end'] );
	$state['phase']                          = 'scanning';
	$state['scan_signature']                 = $signature;
	$state['scan_last_user_id']              = 0;
	$state                                  = my_private_site_cleanup_clear_scan_pause_reason( $state );
	$state['delete_confirmed']               = false;
	$state['delete_started']                 = false;
	$state['totals']['scanned']              = 0;
	$state['totals']['queued']               = 0;
	$state['totals']['excluded']             = 0;
	$state['totals']['total_users']          = $total_users;
	$state['totals']['not_eligible']         = 0;
	$state['totals']['usermeta_queued']      = 0;
	$state['totals']['usermeta_kept']        = 0;
	$state['totals']['usermeta_deleted']     = 0;
	$state['totals']['deleted']              = 0;
	$state['totals']['errors']               = 0;
	$state['totals']['list_protected']       = 0;
	$state['totals']['list_spam_eligible']   = 0;
	$state['reasons']                        = array();
	$state['list_signals']                   = array();
	$state['queue_signals']                  = array();
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: pause dry-run scan after the current batch.
 */
function my_private_site_cleanup_ajax_pause_scan() {
	my_private_site_cleanup_ajax_guard();

	$state = my_private_site_cleanup_get_state();
	if ( 'scanning' === $state['phase'] ) {
		$state['phase'] = 'scan_paused';
		$reason_code    = isset( $_POST['pause_reason_code'] ) ? sanitize_key( wp_unslash( $_POST['pause_reason_code'] ) ) : 'manual';
		$reason_detail  = isset( $_POST['pause_reason_detail'] ) ? sanitize_text_field( wp_unslash( $_POST['pause_reason_detail'] ) ) : __( 'The dry run was paused by the administrator.', 'my-private-site' );
		$state          = my_private_site_cleanup_set_scan_pause_reason( $state, $reason_code, $reason_detail );
		my_private_site_cleanup_update_state( $state );
	}

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: cancel dry-run scan and remove the partial queue.
 */
function my_private_site_cleanup_ajax_cancel_scan() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$state = my_private_site_cleanup_get_state();
	$wpdb->query( 'TRUNCATE TABLE ' . my_private_site_cleanup_queue_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$state['phase']                        = 'defined';
	$state['scan_last_user_id']            = 0;
	$state['scan_signature']               = '';
	$state                                = my_private_site_cleanup_clear_scan_pause_reason( $state );
	$state['delete_confirmed']             = false;
	$state['delete_started']               = false;
	$state['totals']['scanned']            = 0;
	$state['totals']['queued']             = 0;
	$state['totals']['excluded']           = 0;
	$state['totals']['not_eligible']       = 0;
	$state['totals']['usermeta_queued']    = 0;
	$state['totals']['usermeta_kept']      = 0;
	$state['totals']['usermeta_deleted']   = 0;
	$state['totals']['deleted']            = 0;
	$state['totals']['errors']             = 0;
	$state['totals']['list_protected']     = 0;
	$state['totals']['list_spam_eligible'] = 0;
	$state['reasons']                      = array();
	$state['list_signals']                 = array();
	$state['queue_signals']                = array();
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: stop scanning and use the partial dry-run queue already built.
 */
function my_private_site_cleanup_ajax_use_scanned_results() {
	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();

	$state   = my_private_site_cleanup_get_state();
	$updated = my_private_site_cleanup_maybe_pause_stale_scan( $state );
	if ( $updated !== $state ) {
		$state = $updated;
		my_private_site_cleanup_update_state( $state );
	}

	if ( 'scan_paused' !== $state['phase'] ) {
		wp_send_json_error( array( 'message' => __( 'Pause or resume the dry run before using scanned results.', 'my-private-site' ) ), 400 );
	}
	if ( empty( $state['totals']['scanned'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No scanned dry-run results are available yet.', 'my-private-site' ) ), 400 );
	}
	if ( ! my_private_site_cleanup_acquire_lock( 'scan' ) ) {
		wp_send_json_error( array( 'message' => __( 'A dry-run batch is still running. Wait a moment, then try again.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( 'scan_paused' !== $state['phase'] || empty( $state['totals']['scanned'] ) ) {
		my_private_site_cleanup_release_lock( 'scan' );
		wp_send_json_error( array( 'message' => __( 'The dry run state changed. Refresh the cleanup page and try again.', 'my-private-site' ) ), 409 );
	}

	$state['phase']              = 'preview';
	$state['confirmation_token'] = wp_generate_password( 12, false, false );
	$state                       = my_private_site_cleanup_clear_scan_pause_reason( $state );
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'scan' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: scan one batch.
 */
function my_private_site_cleanup_ajax_scan_batch() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();
	if ( ! my_private_site_cleanup_acquire_lock( 'scan' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another scan batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state   = my_private_site_cleanup_get_state();
	if ( 'scanning' !== $state['phase'] ) {
		my_private_site_cleanup_release_lock( 'scan' );
		wp_send_json_error( array( 'message' => __( 'The dry run is not running.', 'my-private-site' ) ), 400 );
	}
	$users   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT u.ID
			FROM {$wpdb->users} u
			WHERE u.ID > %d
				AND u.user_registered BETWEEN %s AND %s
			ORDER BY u.ID ASC
			LIMIT %d",
			(int) $state['scan_last_user_id'],
			$state['date_start'] . ' 00:00:00',
			$state['date_end'] . ' 23:59:59',
			JR_PS_CLEANUP_BATCH_SIZE
		),
		ARRAY_A
	);

	$queue_table     = my_private_site_cleanup_queue_table_name();
	$queued_user_ids = array();
	$kept_user_ids   = array();
	$batch_started   = microtime( true );
	$processed_rows   = 0;
	foreach ( $users as $row ) {
		$processed_rows++;
		$user_id = (int) $row['ID'];
		$user = get_user_by( 'id', $user_id );
		if ( ! my_private_site_cleanup_user_is_candidate_eligible( $user ) ) {
			$state['totals']['not_eligible']++;
			$state['scan_last_user_id'] = $user_id;
			if ( ( microtime( true ) - $batch_started ) >= JR_PS_CLEANUP_SCAN_TIME_LIMIT ) {
				break;
			}
			continue;
		}
		$evaluation = my_private_site_cleanup_evaluate_user( $user, $state );
			if ( is_wp_error( $evaluation ) ) {
				$state['phase'] = 'scan_paused';
				$state          = my_private_site_cleanup_set_scan_pause_reason( $state, $evaluation->get_error_code(), $evaluation->get_error_message() );
				my_private_site_cleanup_update_state( $state );
				my_private_site_cleanup_release_lock( 'scan' );
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: failure reason. */
						__( 'Dry run paused: %s', 'my-private-site' ),
						$evaluation->get_error_message()
					),
					'state'   => my_private_site_cleanup_public_state( $state ),
				),
				503
			);
		}
		$state['totals']['scanned']++;
		$reason     = $evaluation['skip_reason'];
		if ( '' === $reason ) {
			$pending_reason = 'Spam signal: ' . implode( ', ', $evaluation['spam_signals'] );
			$state = my_private_site_cleanup_increment_queue_signals( $state, $evaluation['spam_signals'] );
			if ( ! empty( $evaluation['mailing_list_override'] ) ) {
				$state['totals']['list_spam_eligible']++;
				$state = my_private_site_cleanup_increment_list_signals( $state, $evaluation['spam_signals'] );
				$pending_reason = 'Mailing-list spam signal: ' . implode( ', ', $evaluation['spam_signals'] );
			}
			$wpdb->replace(
				$queue_table,
				array(
					'user_id'    => $user_id,
					'user_login' => $user->user_login,
					'user_email' => $user->user_email,
					'registered' => $user->user_registered,
					'status'     => 'pending_delete',
					'reason'     => $pending_reason,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$state['totals']['queued']++;
			$queued_user_ids[] = $user_id;
		} else {
			if ( ! empty( $evaluation['mailing_list_match'] ) && 'Mailing list' === $reason ) {
				$state['totals']['list_protected']++;
			}
			$wpdb->replace(
				$queue_table,
				array(
					'user_id'    => $user_id,
					'user_login' => $user instanceof WP_User ? $user->user_login : '',
					'user_email' => $user instanceof WP_User ? $user->user_email : '',
					'registered' => $user instanceof WP_User ? $user->user_registered : null,
					'status'     => 'skipped',
					'reason'     => $reason,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$state['totals']['excluded']++;
			$state = my_private_site_cleanup_increment_reason( $state, $reason );
			$kept_user_ids[] = $user_id;
		}
		$state['scan_last_user_id'] = $user_id;
		if ( ( microtime( true ) - $batch_started ) >= JR_PS_CLEANUP_SCAN_TIME_LIMIT ) {
			break;
		}
	}
	if ( ! empty( $queued_user_ids ) ) {
		$state['totals']['usermeta_queued'] += array_sum( my_private_site_cleanup_count_usermeta_for_users( $queued_user_ids ) );
	}
	if ( ! empty( $kept_user_ids ) ) {
		$state['totals']['usermeta_kept'] += array_sum( my_private_site_cleanup_count_usermeta_for_users( $kept_user_ids ) );
	}
	$done = $processed_rows === count( $users ) && count( $users ) < JR_PS_CLEANUP_BATCH_SIZE;
	if ( $done ) {
		$state['phase'] = 'preview';
		$state['confirmation_token'] = wp_generate_password( 12, false, false );
	}
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'scan' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => $done ) );
}

/**
 * AJAX: confirm deletion.
 */
function my_private_site_cleanup_ajax_confirm_delete() {
	my_private_site_cleanup_ajax_guard();

	$state     = my_private_site_cleanup_get_state();
	$typed     = isset( $_POST['typed'] ) ? sanitize_text_field( wp_unslash( $_POST['typed'] ) ) : '';
	$expected  = 'DELETE ' . number_format_i18n( (int) $state['totals']['queued'] );
	$expected2 = 'DELETE ' . (int) $state['totals']['queued'];
	if ( empty( $state['backup_ack'] ) || ( $typed !== $expected && $typed !== $expected2 ) ) {
		wp_send_json_error( array( 'message' => __( 'The typed confirmation did not match the previewed deletion count.', 'my-private-site' ) ), 400 );
	}

	$state['phase']            = 'deleting';
	$state['delete_confirmed'] = true;
	$state['delete_started']   = true;
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: delete one batch.
 */
function my_private_site_cleanup_ajax_delete_batch() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	my_private_site_cleanup_ensure_tables();
	if ( ! my_private_site_cleanup_acquire_lock( 'delete' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another deletion batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['delete_confirmed'] ) ) {
		my_private_site_cleanup_release_lock( 'delete' );
		wp_send_json_error( array( 'message' => __( 'Deletion has not been confirmed.', 'my-private-site' ) ), 400 );
	}

	$table = my_private_site_cleanup_queue_table_name();
	$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $table WHERE status = %s ORDER BY user_id ASC LIMIT %d", 'pending_delete', JR_PS_CLEANUP_BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( empty( $ids ) ) {
		$state['phase'] = 'complete';
		$state['completed_awaiting_reset'] = true;
		$state['report']['completed_at'] = current_time( 'mysql' );
		my_private_site_cleanup_clear_working_data( ! empty( $state['allowlist_keep'] ), false );
		my_private_site_cleanup_update_state( $state );
		my_private_site_cleanup_log(
			array(
					'time'   => current_time( 'mysql' ),
					'type'   => 'spam_account_cleanup',
					'counts' => $state['totals'],
					'cleanup_spam_signals' => my_private_site_cleanup_selected_spam_signal_keys( $state ),
				)
			);
		my_private_site_cleanup_release_lock( 'delete' );
		wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => true ) );
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'processing' WHERE user_id IN ($placeholders) AND status = 'pending_delete'", array_map( 'intval', $ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$user_meta_counts = my_private_site_cleanup_count_usermeta_for_users( $ids );
	foreach ( $ids as $user_id ) {
		$user_id          = (int) $user_id;
		$user             = get_user_by( 'id', $user_id );
		$user_meta_count  = isset( $user_meta_counts[ $user_id ] ) ? (int) $user_meta_counts[ $user_id ] : 0;
		$reason           = my_private_site_cleanup_get_user_skip_reason( $user, $state );
		if ( '' !== $reason ) {
			$wpdb->update( $table, array( 'status' => 'skipped', 'reason' => $reason ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
			$state['totals']['excluded']++;
			$state = my_private_site_cleanup_increment_reason( $state, $reason );
			continue;
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$reassign_user_id = (int) apply_filters( 'my_private_site_cleanup_deleted_user_reassign_id', get_current_user_id(), $user_id, $state );
		$result           = $reassign_user_id > 0 ? wp_delete_user( $user_id, $reassign_user_id ) : wp_delete_user( $user_id );
		if ( $result ) {
			$wpdb->update( $table, array( 'status' => 'deleted', 'reason' => 'Deleted' ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
			$state['totals']['deleted']++;
			$state['totals']['usermeta_deleted'] += $user_meta_count;
		} else {
			$wpdb->update( $table, array( 'status' => 'skipped', 'reason' => 'Delete failed' ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
			$state['totals']['errors']++;
		}
	}

	clean_user_cache( 0 );
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'delete' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => false ) );
}

/**
 * Stream queue CSV.
 */
function my_private_site_cleanup_download_csv() {
	global $wpdb;

	if ( is_multisite() || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Access denied.', 'my-private-site' ), esc_html__( 'Spam Account Cleanup', 'my-private-site' ) );
	}
	check_admin_referer( 'jr_ps_cleanup_download' );
	my_private_site_cleanup_ensure_tables();

	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending_delete';
	if ( ! in_array( $status, array( 'pending_delete', 'deleted', 'skipped', 'all' ), true ) ) {
		$status = 'pending_delete';
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=my-private-site-cleanup-' . $status . '.csv' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $out, array( 'user_id', 'user_login', 'user_email', 'registered', 'status', 'reason' ) );
	$table = my_private_site_cleanup_queue_table_name();
	if ( 'all' === $status ) {
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY user_id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = %s ORDER BY user_id ASC", $status ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	foreach ( $rows as $row ) {
		fputcsv( $out, $row );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

/**
 * AJAX: dry-run orphaned usermeta count.
 */
function my_private_site_cleanup_ajax_orphan_dry_run() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} m LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE u.ID IS NULL" );
	$max   = (int) $wpdb->get_var( "SELECT MAX(umeta_id) FROM {$wpdb->usermeta}" );
	$state = my_private_site_cleanup_get_state();
	$state['totals']['orphan_count'] = $count;
	$state['totals']['orphan_deleted'] = 0;
	$state['orphan'] = array(
		'confirmed' => false,
		'last_id'   => 0,
		'max_id'    => $max,
	);
	my_private_site_cleanup_update_state( $state );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: confirm orphaned usermeta sweep.
 */
function my_private_site_cleanup_ajax_orphan_confirm() {
	my_private_site_cleanup_ajax_guard();
	$state = my_private_site_cleanup_get_state();
	$typed = isset( $_POST['typed'] ) ? sanitize_text_field( wp_unslash( $_POST['typed'] ) ) : '';
	$expected_plain     = 'DELETE META ' . (int) $state['totals']['orphan_count'];
	$expected_localized = 'DELETE META ' . number_format_i18n( (int) $state['totals']['orphan_count'] );
	$typed_normalized   = strtoupper( preg_replace( '/[^\p{L}\p{N}]+/u', '', $typed ) );
	$expected_plain_normalized = strtoupper( preg_replace( '/[^\p{L}\p{N}]+/u', '', $expected_plain ) );
	$expected_localized_normalized = strtoupper( preg_replace( '/[^\p{L}\p{N}]+/u', '', $expected_localized ) );
	if ( empty( $state['backup_ack'] ) || ( $typed_normalized !== $expected_localized_normalized && $typed_normalized !== $expected_plain_normalized ) ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: %s: Expected typed confirmation phrase. */
					__( 'The typed leftover metadata confirmation did not match. Expected: %s', 'my-private-site' ),
					$expected_plain
				),
			),
			400
		);
	}
	$state['orphan']['confirmed'] = true;
	my_private_site_cleanup_update_state( $state );
	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ) ) );
}

/**
 * AJAX: delete orphaned usermeta batch.
 */
function my_private_site_cleanup_ajax_orphan_delete_batch() {
	global $wpdb;

	my_private_site_cleanup_ajax_guard();
	if ( ! my_private_site_cleanup_acquire_lock( 'orphan' ) ) {
		wp_send_json_error( array( 'message' => __( 'Another leftover metadata cleanup batch is already running.', 'my-private-site' ) ), 409 );
	}

	$state = my_private_site_cleanup_get_state();
	if ( empty( $state['orphan']['confirmed'] ) ) {
		my_private_site_cleanup_release_lock( 'orphan' );
		wp_send_json_error( array( 'message' => __( 'Leftover metadata cleanup has not been confirmed.', 'my-private-site' ) ), 400 );
	}

	$lo = (int) $state['orphan']['last_id'] + 1;
	$hi = $lo + JR_PS_CLEANUP_ORPHAN_BATCH_SIZE - 1;
	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE m FROM {$wpdb->usermeta} m
			LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
			WHERE u.ID IS NULL AND m.umeta_id BETWEEN %d AND %d",
			$lo,
			$hi
		)
	);
	$state['orphan']['last_id'] = $hi;
	$state['totals']['orphan_deleted'] += max( 0, (int) $deleted );
	$done = $hi >= (int) $state['orphan']['max_id'];
	my_private_site_cleanup_update_state( $state );
	my_private_site_cleanup_release_lock( 'orphan' );

	wp_send_json_success( array( 'state' => my_private_site_cleanup_public_state( $state ), 'done' => $done ) );
}

/**
 * Register hidden cleanup admin page.
 */
function my_private_site_cleanup_register_admin_page() {
	add_submenu_page(
		'',
		__( 'Spam Account Cleanup', 'my-private-site' ),
		__( 'Spam Account Cleanup', 'my-private-site' ),
		'manage_options',
		JR_PS_CLEANUP_PAGE_SLUG,
		'my_private_site_cleanup_render_page'
	);
}
add_action( 'admin_menu', 'my_private_site_cleanup_register_admin_page' );

/**
 * Register AJAX/download hooks.
 */
function my_private_site_cleanup_register_hooks() {
	$actions = array(
		'jr_ps_cleanup_state'               => 'my_private_site_cleanup_ajax_state',
		'jr_ps_cleanup_summary_batch'       => 'my_private_site_cleanup_ajax_summary_batch',
		'jr_ps_cleanup_reset'               => 'my_private_site_cleanup_ajax_reset',
		'jr_ps_cleanup_prepare_next_run'    => 'my_private_site_cleanup_ajax_prepare_next_run',
		'jr_ps_cleanup_save_setup'          => 'my_private_site_cleanup_ajax_save_setup',
		'jr_ps_cleanup_upload_allowlist'    => 'my_private_site_cleanup_ajax_upload_allowlist',
		'jr_ps_cleanup_import_allowlist_batch' => 'my_private_site_cleanup_ajax_import_allowlist_batch',
		'jr_ps_cleanup_start_scan'          => 'my_private_site_cleanup_ajax_start_scan',
			'jr_ps_cleanup_scan_batch'          => 'my_private_site_cleanup_ajax_scan_batch',
			'jr_ps_cleanup_pause_scan'          => 'my_private_site_cleanup_ajax_pause_scan',
			'jr_ps_cleanup_cancel_scan'         => 'my_private_site_cleanup_ajax_cancel_scan',
			'jr_ps_cleanup_use_scanned_results' => 'my_private_site_cleanup_ajax_use_scanned_results',
			'jr_ps_cleanup_confirm_delete'      => 'my_private_site_cleanup_ajax_confirm_delete',
		'jr_ps_cleanup_delete_batch'        => 'my_private_site_cleanup_ajax_delete_batch',
		'jr_ps_cleanup_orphan_dry_run'      => 'my_private_site_cleanup_ajax_orphan_dry_run',
		'jr_ps_cleanup_orphan_confirm'      => 'my_private_site_cleanup_ajax_orphan_confirm',
		'jr_ps_cleanup_orphan_delete_batch' => 'my_private_site_cleanup_ajax_orphan_delete_batch',
	);

	foreach ( $actions as $action => $callback ) {
		add_action( 'wp_ajax_' . $action, $callback );
	}
	add_action( 'admin_post_jr_ps_cleanup_download_csv', 'my_private_site_cleanup_download_csv' );
}
my_private_site_cleanup_register_hooks();

/**
 * Return cleanup download URL.
 *
 * @param string $status Queue status.
 * @return string
 */
function my_private_site_cleanup_download_url( $status ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'jr_ps_cleanup_download_csv',
				'status' => sanitize_key( $status ),
			),
			admin_url( 'admin-post.php' )
		),
		'jr_ps_cleanup_download'
	);
}

/**
 * Render cleanup admin page.
 */
function my_private_site_cleanup_render_page() {
	if ( is_multisite() ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Spam Account Cleanup', 'my-private-site' ) . '</h1><p>' . esc_html__( 'Spam Account Cleanup is not available on multisite installations in this version.', 'my-private-site' ) . '</p></div>';
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Access denied.', 'my-private-site' ), esc_html__( 'Spam Account Cleanup', 'my-private-site' ) );
	}
	$launch_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $launch_nonce, 'jr_ps_cleanup_launch' ) ) {
		wp_die( esc_html__( 'Invalid cleanup launch link.', 'my-private-site' ), esc_html__( 'Spam Account Cleanup', 'my-private-site' ) );
	}

	my_private_site_cleanup_ensure_tables();
	$nonce                      = wp_create_nonce( 'jr_ps_cleanup_nonce' );
	$cleanup_signal_definitions = function_exists( 'my_private_site_spam_guard_signal_definitions' ) ? my_private_site_spam_guard_signal_definitions() : array();
	uasort(
		$cleanup_signal_definitions,
		function ( $a, $b ) {
			$a_slow = ! empty( $a['slow'] ) || ! empty( $a['external'] );
			$b_slow = ! empty( $b['slow'] ) || ! empty( $b['external'] );
			return (int) $a_slow <=> (int) $b_slow;
		}
	);
	$default_cleanup_signals    = array_fill_keys( my_private_site_cleanup_default_spam_signals(), true );
	$cleanup_signal_labels      = array();
	foreach ( $cleanup_signal_definitions as $signal_key => $signal ) {
		if ( ! empty( $signal['cleanup'] ) ) {
			$cleanup_signal_labels[ $signal_key ] = isset( $signal['cleanup_label'] ) ? $signal['cleanup_label'] : $signal_key;
		}
	}
	$ui                         = array(
		'ready'                   => __( 'Ready.', 'my-private-site' ),
		'loading'                 => __( 'Loading cleanup state...', 'my-private-site' ),
		'reset_confirm'           => __( 'Start over and remove cleanup working data?', 'my-private-site' ),
		'reset_complete'          => __( 'Reset complete.', 'my-private-site' ),
		'prepare_next_run_complete' => __( 'Ready for another cleanup run.', 'my-private-site' ),
		'setup_saved'             => __( 'Starting dry run...', 'my-private-site' ),
		'dry_run_complete'        => __( 'Dry run complete. Review the results before confirming deletion.', 'my-private-site' ),
		'upload_accepted'         => __( 'Mailing list verified. Review samples, then import the selected list.', 'my-private-site' ),
		'upload_imported'         => __( 'Mailing list imported.', 'my-private-site' ),
		'upload_importing'        => __( 'Importing selected list...', 'my-private-site' ),
		'upload_import_summary'   => __( 'Imported %1$s addresses. Skipped %2$s invalid rows.', 'my-private-site' ),
		'verify_list'             => __( 'Verify list', 'my-private-site' ),
		'import_uploaded_list'    => __( 'Import selected list', 'my-private-site' ),
		'batch_complete'          => __( 'Batch process complete.', 'my-private-site' ),
		'queue_confirmed'         => __( 'Deletion queue confirmed.', 'my-private-site' ),
			'dry_run_working'         => __( 'Dry run running... elapsed %1$s. Batches completed: %2$s. Accounts scanned so far: %3$s of %4$s.', 'my-private-site' ),
			'dry_run_pausing'         => __( 'Pausing dry run after the current batch...', 'my-private-site' ),
			'dry_run_paused'          => __( 'Dry run paused. Resume when you are ready.', 'my-private-site' ),
			'dry_run_canceling'       => __( 'Canceling dry run after the current batch...', 'my-private-site' ),
			'dry_run_canceled'        => __( 'Dry run canceled and the partial queue was cleared.', 'my-private-site' ),
			'pause_reason_prefix'     => __( 'Paused because', 'my-private-site' ),
			'pause_recommend_prefix'  => __( 'Recommendation', 'my-private-site' ),
			'pause_reason_manual'     => __( 'the dry run was paused by an administrator.', 'my-private-site' ),
			'pause_reason_stale'      => __( 'no active dry-run request was detected.', 'my-private-site' ),
			'pause_reason_timeout'    => __( 'the last dry-run request timed out.', 'my-private-site' ),
			'pause_reason_sfs'        => __( 'StopForumSpam paused the scan.', 'my-private-site' ),
			'pause_reason_generic'    => __( 'the dry run stopped before it completed.', 'my-private-site' ),
			'pause_recommend_manual'  => __( 'Click Resume dry run when you are ready to continue.', 'my-private-site' ),
				'pause_recommend_stale'   => __( 'Click Resume dry run, or use scanned results if you want to continue with only the accounts already scanned. If it pauses again, try a smaller date range or turn off slow external checks.', 'my-private-site' ),
				'pause_recommend_sfs'     => __( 'Come back later or tomorrow and resume, or turn off StopForumSpam and run a new dry run if you want to continue without that external check.', 'my-private-site' ),
				'pause_recommend_generic' => __( 'Click Resume dry run. If it pauses again, review the message and consider turning off slow external checks.', 'my-private-site' ),
					'perform_dry_run'         => __( 'Perform dry run', 'my-private-site' ),
					'resume_dry_run'          => __( 'Resume dry run', 'my-private-site' ),
					'use_scanned_results'     => __( 'Use scanned results', 'my-private-site' ),
					'use_scanned_complete'    => __( 'Using scanned results. Review the partial queue before confirming deletion.', 'my-private-site' ),
					'continue_to_confirm'     => __( 'Continue to confirmation', 'my-private-site' ),
					'detached_scan'           => __( 'Dry run appears disconnected from this browser. Checking whether it is still running...', 'my-private-site' ),
			'pause_dry_run'           => __( 'Pause dry run', 'my-private-site' ),
			'cancel_dry_run'          => __( 'Cancel dry run', 'my-private-site' ),
		'delete_not_ready'        => __( 'Deletion can run after the dry run is complete and the deletion queue is confirmed.', 'my-private-site' ),
		'delete_no_queue'         => __( 'No accounts are queued for deletion.', 'my-private-site' ),
		'delete_ready'            => __( 'Deletion queue confirmed. Click Run / Resume deletion to delete the queued accounts.', 'my-private-site' ),
		'delete_running'          => __( 'Deletion in progress.', 'my-private-site' ),
		'delete_paused'           => __( 'Deletion paused. Click Run / Resume deletion to continue.', 'my-private-site' ),
		'delete_complete_status'  => __( 'Deletion complete.', 'my-private-site' ),
		'orphan_dry_run_complete' => __( 'Leftover metadata count complete.', 'my-private-site' ),
		'orphan_confirmed'        => __( 'Leftover metadata cleanup confirmed.', 'my-private-site' ),
		'orphan_confirming'       => __( 'Confirming leftover metadata cleanup...', 'my-private-site' ),
		'orphan_ready'            => __( 'Confirmed. You can now remove leftover metadata.', 'my-private-site' ),
		'orphan_removing'         => __( 'Removing leftover metadata... removed %1$s of %2$s rows.', 'my-private-site' ),
		'orphan_complete'         => __( 'Leftover metadata cleanup complete. Removed %1$s rows.', 'my-private-site' ),
		'request_failed'          => __( 'Request failed', 'my-private-site' ),
		'request_timeout'         => __( 'The request timed out. Wait a moment, then resume or cancel the dry run.', 'my-private-site' ),
		'detected_column'         => __( 'Email address detected in column', 'my-private-site' ),
		'first_column'            => __( 'first column', 'my-private-site' ),
		'column_number'           => __( 'column %s', 'my-private-site' ),
		'upload_samples'          => __( 'Sample addresses from that column', 'my-private-site' ),
		'upload_review'           => __( 'Review these samples before importing. If they are not email addresses from the list you want to protect, choose a different file and verify again.', 'my-private-site' ),
		'would_delete'            => __( 'Would delete', 'my-private-site' ),
		'scanned'                 => __( 'Scanned', 'my-private-site' ),
		'kept'                    => __( 'Kept', 'my-private-site' ),
		'not_eligible'            => __( 'Not eligible found so far', 'my-private-site' ),
		'signal'                  => __( 'Signal', 'my-private-site' ),
		'usermeta_would_clear'    => __( 'User meta records that would be cleared', 'my-private-site' ),
		'usermeta_kept'           => __( 'User meta records kept', 'my-private-site' ),
		'usermeta_cleared'        => __( 'User meta records cleared', 'my-private-site' ),
		'type_delete'             => __( 'Type DELETE %s to proceed.', 'my-private-site' ),
		'deleted_of'              => __( 'Deleted %1$s of %2$s. Errors: %3$s. Status: %4$s', 'my-private-site' ),
		'phase'                   => __( 'Status', 'my-private-site' ),
			'deleted'                 => __( 'Deleted', 'my-private-site' ),
			'queued'                  => __( 'Queued', 'my-private-site' ),
			'errors'                  => __( 'Errors', 'my-private-site' ),
			'list_protected'          => __( 'Found in mailing list', 'my-private-site' ),
			'list_spam_eligible'      => __( 'List members eligible for deletion (also matched spam signals)', 'my-private-site' ),
			'list_override_heading'   => __( 'Mailing-list members still queued', 'my-private-site' ),
			'list_override_note'      => __( 'These accounts were found in the uploaded mailing list but are still in the deletion queue because strong mailing-list mode is enabled and they matched high-confidence spam signals.', 'my-private-site' ),
			'queue_signal_heading'    => __( 'Deletion queue signals', 'my-private-site' ),
			'queue_signal_note'       => __( 'Counts are by signal. One account can match more than one signal, so these numbers may not add up to the deletion count.', 'my-private-site' ),
			'kept_reason_heading'     => __( 'Kept out of deletion queue', 'my-private-site' ),
			'orphaned_rows'           => __( 'Leftover metadata rows: %1$s Removed: %2$s', 'my-private-site' ),
			'orphan_confirm_copy'     => __( 'To remove these rows, type DELETE META %s.', 'my-private-site' ),
			'year'                    => __( 'Year', 'my-private-site' ),
		'eligible_accounts'       => __( '# of basic users in range', 'my-private-site' ),
		'use_year'                => __( 'Use year', 'my-private-site' ),
		'all_eligible_dates'      => __( 'All eligible dates', 'my-private-site' ),
		'no_eligible_dates'       => __( 'No eligible basic users were found.', 'my-private-site' ),
		'summary_building'        => __( 'Building date summary... scanned %1$s users so far, found %2$s basic users.', 'my-private-site' ),
		'summary_complete'        => __( 'Date summary complete.', 'my-private-site' ),
		'date_window_selected'    => __( 'Date window selected. Review protections and spam signals, then run the dry run.', 'my-private-site' ),
		'spam_signal_summary'     => __( 'Only accounts matching at least one selected spam signal can be queued for deletion.', 'my-private-site' ),
			'step_hints'              => array(
				0 => __( 'Review the safeguards before starting.', 'my-private-site' ),
				1 => __( 'Confirm that a restorable backup exists.', 'my-private-site' ),
				2 => __( 'Choose which registration dates to include in the basic-user pool.', 'my-private-site' ),
				3 => __( 'Add optional keep-lists before scanning.', 'my-private-site' ),
			4 => __( 'Choose the spam signals that qualify an account for the dry-run deletion queue.', 'my-private-site' ),
			5 => __( 'Run the non-destructive dry run and export the CSV.', 'my-private-site' ),
			6 => __( 'Confirm the exact deletion queue count.', 'my-private-site' ),
			7 => __( 'Delete queued accounts in resumable batches.', 'my-private-site' ),
			8 => __( 'Review the result and optionally remove leftover user metadata.', 'my-private-site' ),
		),
		'phase_labels'            => array(
			'idle'               => __( 'Ready to configure cleanup.', 'my-private-site' ),
			'defined'            => __( 'Setup saved; ready for dry run.', 'my-private-site' ),
			'allowlist_uploaded' => __( 'Protected mailing list verified; ready to import.', 'my-private-site' ),
			'allowlist_imported' => __( 'Protected mailing list imported; ready for dry run.', 'my-private-site' ),
			'scanning'           => __( 'Dry run in progress.', 'my-private-site' ),
			'scan_paused'        => __( 'Dry run paused.', 'my-private-site' ),
			'preview'            => __( 'Dry run complete; review before deleting.', 'my-private-site' ),
			'deleting'           => __( 'Deletion in progress.', 'my-private-site' ),
			'complete'           => __( 'Cleanup complete.', 'my-private-site' ),
		),
		'signal_labels'           => $cleanup_signal_labels,
	);
	?>
	<div class="wrap jrps-cleanup-wrap">
		<h1><?php esc_html_e( 'Spam Account Cleanup', 'my-private-site' ); ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=my_private_site_tab_advanced&subtab=spam_account_cleanup' ) ); ?>">&larr; <?php esc_html_e( 'Back to My Private Site - Advanced', 'my-private-site' ); ?></a></p>
		<div id="jrps-cleanup-status" class="notice notice-info inline" aria-live="polite"><p><?php esc_html_e( 'Loading cleanup state...', 'my-private-site' ); ?></p></div>
		<style>
					.jrps-cleanup-wrap{max-width:1180px}.jrps-cleanup-step-help{margin:10px 0 0;color:#50575e}.jrps-cleanup-steps{position:sticky;top:32px;z-index:20;display:grid;grid-template-columns:repeat(auto-fit,minmax(115px,1fr));gap:8px;margin:8px 0 16px;padding:8px 0;background:#f0f0f1}.jrps-cleanup-step{background:#fff;border:1px solid #c3c4c7;padding:10px;min-height:54px;cursor:pointer}.jrps-cleanup-step:hover{border-color:#2271b1}.jrps-cleanup-step:focus{outline:2px solid #2271b1;outline-offset:2px}.jrps-cleanup-step strong{display:block}.jrps-cleanup-grid{display:grid;gap:16px}.jrps-cleanup-card{background:#fff;border:1px solid #ccd0d4;padding:18px;scroll-margin-top:125px;transition:opacity .15s ease}.jrps-cleanup-card h2{margin-top:0}.jrps-cleanup-critical-warning{color:#b32d2e;font-size:24px;font-weight:700;line-height:1.25;margin:14px 0}.jrps-cleanup-pause-reason{color:#b32d2e;font-weight:600;margin:10px 0}.jrps-cleanup-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.jrps-cleanup-danger-button{border-color:#b32d2e!important;color:#b32d2e!important}.jrps-cleanup-danger-button:hover,.jrps-cleanup-danger-button:focus{background:#b32d2e!important;border-color:#8a2424!important;color:#fff!important}.jrps-cleanup-danger-button-filled{display:block!important;width:max-content;background:#b32d2e!important;border-color:#8a2424!important;color:#fff!important}.jrps-cleanup-danger-button-filled:hover,.jrps-cleanup-danger-button-filled:focus{background:#8a2424!important;border-color:#691c1c!important;color:#fff!important}.jrps-cleanup-danger-button-filled:disabled{background:#f0f0f1!important;border-color:#c3c4c7!important;color:#a7aaad!important}.jrps-cleanup-button-spacer{height:1em}.jrps-cleanup-progress{height:18px;background:#e5e5e5;max-width:520px;overflow:hidden}.jrps-cleanup-progress span{display:block;height:18px;background:#2271b1;width:0;transition:width .2s ease}.jrps-cleanup-progress.is-active span{width:100%!important;background:repeating-linear-gradient(45deg,#2271b1 0,#2271b1 10px,#4f94c6 10px,#4f94c6 20px);background-size:28px 28px;animation:jrps-cleanup-progress 1s linear infinite}.jrps-live-status{margin:8px 0;color:#1d2327}.jrps-cleanup-muted{color:#646970}.jrps-cleanup-table{border-collapse:collapse}.jrps-cleanup-table th,.jrps-cleanup-table td{padding:6px 10px;border-bottom:1px solid #ddd;text-align:left}.jrps-cleanup-year-picker{margin-top:12px}.jrps-cleanup-year-picker caption{text-align:left;margin-bottom:6px;color:#50575e}.jrps-cleanup-advanced{border:1px dashed #8c8f94;padding:12px;margin-top:12px}.jrps-cleanup-card input[type="date"],.jrps-cleanup-card input[type="number"],.jrps-cleanup-card input[type="text"],.jrps-cleanup-card textarea{max-width:100%}.jrps-cleanup-card .button[aria-disabled="true"],.jrps-cleanup-card .button:disabled{pointer-events:none;opacity:.55;cursor:default}@keyframes jrps-cleanup-progress{from{background-position:0 0}to{background-position:28px 0}}
		</style>
		<p class="jrps-cleanup-step-help"><?php esc_html_e( 'Click a step to jump to that section.', 'my-private-site' ); ?></p>
		<div class="jrps-cleanup-steps" aria-label="<?php esc_attr_e( 'Cleanup progress', 'my-private-site' ); ?>">
			<div class="jrps-cleanup-step" data-step-nav="1" role="button" tabindex="0"><strong><?php esc_html_e( '1. Backup', 'my-private-site' ); ?></strong><?php esc_html_e( 'Required', 'my-private-site' ); ?></div>
				<div class="jrps-cleanup-step" data-step-nav="2" role="button" tabindex="0"><strong><?php esc_html_e( '2. Dates', 'my-private-site' ); ?></strong><?php esc_html_e( 'Registration window', 'my-private-site' ); ?></div>
			<div class="jrps-cleanup-step" data-step-nav="3" role="button" tabindex="0"><strong><?php esc_html_e( '3. Keep', 'my-private-site' ); ?></strong><?php esc_html_e( 'Protections', 'my-private-site' ); ?></div>
				<div class="jrps-cleanup-step" data-step-nav="4" role="button" tabindex="0"><strong><?php esc_html_e( '4. Signals', 'my-private-site' ); ?></strong><?php esc_html_e( 'Spam checks', 'my-private-site' ); ?></div>
			<div class="jrps-cleanup-step" data-step-nav="5" role="button" tabindex="0"><strong><?php esc_html_e( '5. Non-destructive dry run', 'my-private-site' ); ?></strong><?php esc_html_e( 'Queue build', 'my-private-site' ); ?></div>
			<div class="jrps-cleanup-step" data-step-nav="6" role="button" tabindex="0"><strong><?php esc_html_e( '6. Confirm', 'my-private-site' ); ?></strong><?php esc_html_e( 'Exact count', 'my-private-site' ); ?></div>
			<div class="jrps-cleanup-step" data-step-nav="7" role="button" tabindex="0"><strong><?php esc_html_e( '7. Delete', 'my-private-site' ); ?></strong><?php esc_html_e( 'Batches', 'my-private-site' ); ?></div>
			<div class="jrps-cleanup-step" data-step-nav="8" role="button" tabindex="0"><strong><?php esc_html_e( '8. Finish', 'my-private-site' ); ?></strong><?php esc_html_e( 'Summary', 'my-private-site' ); ?></div>
		</div>
		<div class="jrps-cleanup-grid">
				<section class="jrps-cleanup-card" data-step-card="0">
					<h2><?php esc_html_e( 'Intro - What this does', 'my-private-site' ); ?></h2>
					<p><?php esc_html_e( 'This tool previews and deletes spam basic user accounts in safe batches. It never deletes administrators, detected customers or store-order accounts, content authors, commenters, mailing-list matches, or other protected users.', 'my-private-site' ); ?></p>
					<p class="jrps-cleanup-muted"><?php esc_html_e( 'Deletion is permanent. You will dry-run, export CSV, and type the exact count before deletion can begin. If unexpected authored content is found during deletion, WordPress content is reassigned to the current admin instead of being deleted.', 'my-private-site' ); ?></p>
					<p class="jrps-cleanup-critical-warning"><?php esc_html_e( 'This cannot be undone and could damage your system. Think carefully before proceeding.', 'my-private-site' ); ?></p>
				</section>
			<section class="jrps-cleanup-card jrps-cleanup-danger" data-step-card="1">
				<h2><?php esc_html_e( 'Step 1 - Confirm backup (or you could be in a world of hurt)', 'my-private-site' ); ?></h2>
				<p><strong><?php esc_html_e( 'Deletion cannot be undone. A current, restorable database backup is required before continuing.', 'my-private-site' ); ?></strong></p>
				<label><input type="checkbox" id="jrps-backup-ack"> <?php esc_html_e( 'I have a current, restorable backup.', 'my-private-site' ); ?></label>
			</section>
			<section class="jrps-cleanup-card" data-step-card="2">
				<h2><?php esc_html_e( 'Step 2 - Choose registration dates', 'my-private-site' ); ?></h2>
				<p><?php esc_html_e( 'This tool starts with basic users: accounts that can log in and read the site, but cannot edit content, upload files, moderate comments, manage users, or administer the site. That safety boundary is not optional.', 'my-private-site' ); ?></p>
				<p class="jrps-cleanup-muted"><?php esc_html_e( 'Enter dates, click a year, or use all dates shown below.', 'my-private-site' ); ?></p>
				<label><?php esc_html_e( 'Start date', 'my-private-site' ); ?> <input type="date" id="jrps-date-start"></label>
				<label><?php esc_html_e( 'End date', 'my-private-site' ); ?> <input type="date" id="jrps-date-end"></label>
				<button class="button" id="jrps-all-dates"><?php esc_html_e( 'All eligible dates', 'my-private-site' ); ?></button>
				<div id="jrps-year-counts"></div>
			</section>
				<section class="jrps-cleanup-card" data-step-card="3">
					<h2><?php esc_html_e( 'Step 3 - Protections', 'my-private-site' ); ?></h2>
					<p><?php esc_html_e( 'Hard exclusions always apply: EDD customers, detected store-order accounts, site managers, content authors, commenters, current user, manual keep-list, and protected mailing-list matches. Recent-login protection is optional below.', 'my-private-site' ); ?></p>
				<label><?php esc_html_e( 'Manual keep-list: one username or email per line', 'my-private-site' ); ?><br><textarea id="jrps-admin-allowlist" rows="5" cols="70"></textarea></label>
				<form id="jrps-upload-form" enctype="multipart/form-data">
					<input type="file" id="jrps-allowlist-file" name="allowlist_file" accept=".csv,.txt,text/plain,text/csv">
					<button class="button" id="jrps-list-action" type="submit"><?php esc_html_e( 'Verify list', 'my-private-site' ); ?></button>
				</form>
				<div id="jrps-upload-result"></div>
				<p><label><input type="checkbox" id="jrps-fold-gmail" checked> <?php esc_html_e( 'Fold Gmail/googlemail dots and plus aliases when matching uploaded lists.', 'my-private-site' ); ?></label></p>
				<p class="jrps-cleanup-muted"><?php esc_html_e( 'For Gmail addresses, dots and plus tags do not normally create separate inboxes. This treats addresses like first.last+promo@gmail.com and firstlast@gmail.com as the same person when checking the uploaded keep-list.', 'my-private-site' ); ?></p>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Mailing-list protection mode', 'my-private-site' ); ?></strong></legend>
					<p><label><input type="radio" name="jrps-mailing-list-mode" value="absolute" checked> <?php esc_html_e( 'Absolute - never delete a list member. Safest choice for clean lists.', 'my-private-site' ); ?></label></p>
					<p><label><input type="radio" name="jrps-mailing-list-mode" value="strong"> <?php esc_html_e( 'Strong - protect list members unless they also match a high-confidence spam signal. Use this for contaminated lists.', 'my-private-site' ); ?></label></p>
				</fieldset>
				<p><label><input type="checkbox" id="jrps-recent-login-enabled" checked> <?php esc_html_e( 'Protect accounts with a recent login', 'my-private-site' ); ?></label></p>
				<p><label><?php esc_html_e( 'Recent-login protection window', 'my-private-site' ); ?> <input type="number" id="jrps-recent-days" value="90" min="1" max="3650"> <?php esc_html_e( 'days', 'my-private-site' ); ?></label></p>
				<p class="jrps-cleanup-muted"><?php esc_html_e( 'Any account that appears to have logged in during this many days is protected from deletion, even if other checks look suspicious.', 'my-private-site' ); ?></p>
			</section>
			<section class="jrps-cleanup-card" data-step-card="4">
				<h2><?php esc_html_e( 'Step 4 - Choose spam signals', 'my-private-site' ); ?></h2>
				<p><?php esc_html_e( 'Only accounts matching at least one selected signal can be queued for deletion. Accounts matching none of these are kept out of the deletion queue.', 'my-private-site' ); ?></p>
				<p class="jrps-cleanup-muted"><?php esc_html_e( 'Slow checks can make the dry run take much longer: Missing MX performs DNS lookups, and StopForumSpam may call an external database for uncached accounts.', 'my-private-site' ); ?></p>
				<div class="jrps-cleanup-signal-list">
					<?php foreach ( $cleanup_signal_definitions as $signal_key => $signal ) : ?>
						<?php
						if ( empty( $signal['cleanup'] ) ) {
							continue;
						}
						$signal_id = 'jrps-cleanup-signal-' . sanitize_html_class( $signal_key );
						$label     = isset( $signal['cleanup_label'] ) ? $signal['cleanup_label'] : $signal_key;
						$checked   = isset( $default_cleanup_signals[ $signal_key ] );
						$flags     = array();
						if ( 'stop_forum_spam' !== $signal_key ) {
							if ( ! empty( $signal['slow'] ) ) {
								$flags[] = __( 'slow', 'my-private-site' );
							}
							if ( ! empty( $signal['external'] ) ) {
								$flags[] = __( 'external', 'my-private-site' );
							}
						}
						?>
						<p>
							<label for="<?php echo esc_attr( $signal_id ); ?>">
								<input type="checkbox" class="jrps-cleanup-spam-signal" id="<?php echo esc_attr( $signal_id ); ?>" value="<?php echo esc_attr( $signal_key ); ?>" <?php checked( $checked ); ?>>
								<?php echo esc_html( $label ); ?>
								<?php if ( ! empty( $flags ) ) : ?>
									<span class="jrps-cleanup-muted"><?php echo esc_html( '(' . implode( ', ', $flags ) . ')' ); ?></span>
								<?php endif; ?>
							</label>
							<?php if ( 'stop_forum_spam' === $signal_key ) : ?>
								<span class="jrps-cleanup-muted">
									<?php esc_html_e( '(slow, API may ', 'my-private-site' ); ?><a href="https://stopforumspam.com/usage" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'limit the number of scans', 'my-private-site' ); ?></a><?php esc_html_e( ')', 'my-private-site' ); ?>
								</span>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="jrps-cleanup-card" data-step-card="5">
				<h2><?php esc_html_e( 'Step 5 - Non-destructive dry run', 'my-private-site' ); ?></h2>
					<p><?php esc_html_e( 'The dry run scans basic users and builds the exact queue. Nothing is deleted.', 'my-private-site' ); ?></p>
					<div class="jrps-cleanup-progress"><span id="jrps-scan-bar"></span></div>
					<p class="jrps-live-status" id="jrps-scan-live" aria-live="polite"></p>
					<div id="jrps-preview"></div>
					<p class="jrps-cleanup-pause-reason" id="jrps-scan-pause-reason" aria-live="polite"></p>
					<div class="jrps-cleanup-actions">
						<button class="button button-primary" id="jrps-save-setup"><?php esc_html_e( 'Perform dry run', 'my-private-site' ); ?></button>
						<button class="button" id="jrps-pause-scan"><?php esc_html_e( 'Pause dry run', 'my-private-site' ); ?></button>
						<button class="button" id="jrps-cancel-scan"><?php esc_html_e( 'Cancel dry run', 'my-private-site' ); ?></button>
						<button class="button" id="jrps-use-scanned-results"><?php esc_html_e( 'Use scanned results', 'my-private-site' ); ?></button>
						<button class="button button-primary" id="jrps-continue-confirm"><?php esc_html_e( 'Continue to confirmation', 'my-private-site' ); ?></button>
						<button class="button jrps-cleanup-danger-button" id="jrps-reset"><?php esc_html_e( 'Start Over', 'my-private-site' ); ?></button>
					<a class="button" id="jrps-download-delete" href="<?php echo esc_url( my_private_site_cleanup_download_url( 'pending_delete' ) ); ?>"><?php esc_html_e( 'Download CSV of accounts to delete', 'my-private-site' ); ?></a>
					<a class="button" id="jrps-download-all" href="<?php echo esc_url( my_private_site_cleanup_download_url( 'all' ) ); ?>"><?php esc_html_e( 'Download full audit CSV', 'my-private-site' ); ?></a>
				</div>
			</section>
				<section class="jrps-cleanup-card jrps-cleanup-danger" data-step-card="6">
					<h2><?php esc_html_e( 'Step 6 - Confirm', 'my-private-site' ); ?></h2>
					<p id="jrps-confirm-copy"></p>
					<input type="text" id="jrps-confirm-text" size="30">
					<button class="button button-primary" id="jrps-confirm-delete"><?php esc_html_e( 'Confirm deletion queue', 'my-private-site' ); ?></button>
			</section>
			<section class="jrps-cleanup-card" data-step-card="7">
				<h2><?php esc_html_e( 'Step 7 - Run', 'my-private-site' ); ?></h2>
				<p class="jrps-cleanup-critical-warning"><?php esc_html_e( 'This cannot be undone and could damage your system. Think carefully before proceeding.', 'my-private-site' ); ?></p>
				<div class="jrps-cleanup-progress"><span id="jrps-delete-bar"></span></div>
				<div id="jrps-delete-status"></div>
				<div class="jrps-cleanup-button-spacer" aria-hidden="true"></div>
				<button class="button jrps-cleanup-danger-button-filled" id="jrps-delete-batch"><?php esc_html_e( 'Run / Resume deletion', 'my-private-site' ); ?></button>
			</section>
			<section class="jrps-cleanup-card" data-step-card="8">
				<h2><?php esc_html_e( 'Step 8 - Summary and optional leftover metadata cleanup', 'my-private-site' ); ?></h2>
				<div id="jrps-summary"></div>
				<div class="jrps-cleanup-actions">
					<button class="button button-primary" id="jrps-prepare-next-run"><?php esc_html_e( 'Prepare another run', 'my-private-site' ); ?></button>
				</div>
				<hr>
				<h3><?php esc_html_e( 'Optional: remove leftover user metadata', 'my-private-site' ); ?></h3>
				<p><?php esc_html_e( 'This does not select or delete more user accounts. It only removes leftover user metadata rows whose user account no longer exists.', 'my-private-site' ); ?></p>
				<button type="button" class="button" id="jrps-orphan-dry-run"><?php esc_html_e( 'Count leftover metadata rows', 'my-private-site' ); ?></button>
				<p id="jrps-orphan-count"></p>
				<label id="jrps-orphan-confirm-copy" for="jrps-orphan-confirm-text"></label><br>
				<input type="text" id="jrps-orphan-confirm-text" size="35" placeholder="<?php esc_attr_e( 'Type the phrase shown above', 'my-private-site' ); ?>">
				<button type="button" class="button" id="jrps-orphan-confirm"><?php esc_html_e( 'Confirm leftover metadata cleanup', 'my-private-site' ); ?></button>
				<button type="button" class="button button-primary" id="jrps-orphan-run"><?php esc_html_e( 'Remove leftover metadata', 'my-private-site' ); ?></button>
				<p class="jrps-live-status" id="jrps-orphan-status" aria-live="polite"></p>
			</section>
		</div>
	</div>
	<script>
	(function(){
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const i18n = <?php echo wp_json_encode( $ui ); ?>;
		let nonce = <?php echo wp_json_encode( $nonce ); ?>;
		let state = {};
		let dateRange = {};
		let listAction = 'verify';
		let hydrated = false;
		let busy = false;
		let activityTimer = null;
		let activityStarted = 0;
		let activityBatches = 0;
		let activeActivity = '';
		let orphanCountVisible = false;
			let pauseRequested = false;
			let cancelRequested = false;
			let summaryRunning = false;
			let stateWatchTimer = null;
		function esc(text){return String(text).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));}
		function byId(id){return document.getElementById(id);}
		function msg(text, error){byId('jrps-cleanup-status').className='notice inline '+(error?'notice-error':'notice-info');byId('jrps-cleanup-status').innerHTML='<p>'+esc(text)+'</p>';}
		function sprintf(text){let args=Array.prototype.slice.call(arguments,1);let i=0;return String(text).replace(/%(\d+\$)?s/g,function(match,position){let index=position?parseInt(position,10)-1:i++;return typeof args[index] === 'undefined' ? match : args[index];});}
		function setBusy(nextBusy){busy=nextBusy;render();}
		function post(action, data, file){data=data||{};data.action=action;data.nonce=nonce;let controller=window.AbortController?new AbortController():null,timer=controller?window.setTimeout(()=>controller.abort(),70000):null,options={method:'POST',credentials:'same-origin',body:file?data:new URLSearchParams(data)};if(controller){options.signal=controller.signal;}return fetch(ajaxUrl,options).then(r=>r.text().then(text=>{let j=null;try{j=JSON.parse(text);}catch(e){throw new Error(i18n.request_failed+': '+(text?text.substring(0,180):'empty response'));}if(j&&j.data&&j.data.nonce){nonce=j.data.nonce;}if(j&&j.data&&j.data.state){state=j.data.state;render();}if(!r.ok||!j.success){throw new Error((j&&j.data&&j.data.message)||i18n.request_failed);}return j.data;})).catch(e=>{if(e.name==='AbortError'){throw new Error(i18n.request_timeout);}throw e;}).finally(()=>{if(timer){window.clearTimeout(timer);}});}
		function syncFormFromState(force){if(hydrated&&!force){return;}byId('jrps-date-start').value=state.date_start||'';byId('jrps-date-end').value=state.date_end||'';byId('jrps-admin-allowlist').value=(state.admin_allowlist||[]).join("\n");byId('jrps-recent-login-enabled').checked=state.recent_login_protection_enabled!==false;byId('jrps-recent-days').value=state.recent_days||90;byId('jrps-backup-ack').checked=!!state.backup_ack;byId('jrps-fold-gmail').checked=state.fold_gmail!==false;document.querySelectorAll('input[name="jrps-mailing-list-mode"]').forEach(input=>{input.checked=(input.value===(state.mailing_list_mode||'absolute'));});document.querySelectorAll('.jrps-cleanup-spam-signal').forEach(input=>{input.checked=(state.cleanup_spam_signals||[]).indexOf(input.value)!==-1;});hydrated=true;}
			function clearTransientFields(){stopActivity();orphanCountVisible=false;byId('jrps-upload-result').innerHTML='';byId('jrps-confirm-text').value='';byId('jrps-orphan-confirm-text').value='';byId('jrps-scan-bar').style.width='0';byId('jrps-delete-bar').style.width='0';byId('jrps-scan-live').textContent='';byId('jrps-scan-pause-reason').textContent='';byId('jrps-allowlist-file').value='';setListAction('verify');}
			function pauseNotice(){let code=state.scan_pause_reason_code||'', detail=state.scan_pause_reason_detail||'', reason=i18n.pause_reason_generic, recommendation=i18n.pause_recommend_generic;if(code==='manual'){reason=i18n.pause_reason_manual;recommendation=i18n.pause_recommend_manual;}else if(code==='stale_lock'){reason=i18n.pause_reason_stale;recommendation=i18n.pause_recommend_stale;}else if(code==='request_timeout'){reason=i18n.pause_reason_timeout;recommendation=i18n.pause_recommend_stale;}else if(code==='stop_forum_spam_lookup_failed'||detail.indexOf('StopForumSpam')!==-1){reason=detail||i18n.pause_reason_sfs;recommendation=i18n.pause_recommend_sfs;}else if(detail){reason=detail;}return i18n.pause_reason_prefix+' '+reason+' '+i18n.pause_recommend_prefix+': '+recommendation;}
				function render(){
					const t=state.totals||{}, phase=state.phase||'idle', q=parseInt(t.queued||0,10), d=parseInt(t.deleted||0,10), scanned=parseInt(t.scanned||0,10), totalUsers=parseInt(t.total_users||0,10), orphanCount=parseInt(t.orphan_count||0,10), orphanDeleted=parseInt(t.orphan_deleted||0,10), detachedScan=(phase==='scanning'&&activeActivity!=='scan');
					byId('jrps-preview').innerHTML='<p><strong>'+esc(i18n.would_delete)+':</strong> '+esc(q)+' &nbsp; <strong>'+esc(i18n.scanned)+':</strong> '+esc(scanned)+' &nbsp; <strong>'+esc(i18n.kept)+':</strong> '+esc(t.excluded||0)+' &nbsp; <strong>'+esc(i18n.not_eligible)+':</strong> '+esc(t.not_eligible||0)+'</p><p><strong>'+esc(i18n.usermeta_would_clear)+':</strong> '+esc(t.usermeta_queued||0)+' &nbsp; <strong>'+esc(i18n.usermeta_kept)+':</strong> '+esc(t.usermeta_kept||0)+'</p>'+listEffect()+queueSignals()+listOverrideSignals()+reasons();
					byId('jrps-scan-pause-reason').textContent=phase==='scan_paused'?pauseNotice():'';
					if(detachedScan){byId('jrps-scan-live').textContent=i18n.detached_scan;}else if(phase==='preview'){byId('jrps-scan-live').textContent=i18n.dry_run_complete;}else if(activeActivity!=='scan'&&phase!=='scan_paused'){byId('jrps-scan-live').textContent='';}
					byId('jrps-confirm-copy').textContent=sprintf(i18n.type_delete, q);
					byId('jrps-delete-status').textContent=deleteStatusText(phase,q,d,t.errors||0);
					byId('jrps-summary').innerHTML='<p><strong>'+esc(i18n.phase)+':</strong> '+esc(cleanupStatusText(phase,q,d))+'</p><p><strong>'+esc(i18n.deleted)+':</strong> '+esc(d)+' <strong>'+esc(i18n.queued)+':</strong> '+esc(q)+' <strong>'+esc(i18n.errors)+':</strong> '+esc(t.errors||0)+'</p><p><strong>'+esc(i18n.usermeta_cleared)+':</strong> '+esc(t.usermeta_deleted||0)+' &nbsp; <strong>'+esc(i18n.usermeta_kept)+':</strong> '+esc(t.usermeta_kept||0)+'</p>';
					byId('jrps-orphan-count').textContent=orphanCountVisible?sprintf(i18n.orphaned_rows, orphanCount, orphanDeleted):'';
					byId('jrps-orphan-confirm-copy').textContent=orphanCountVisible&&orphanCount?sprintf(i18n.orphan_confirm_copy, orphanCount):'';
					byId('jrps-orphan-status').textContent=(orphanCountVisible&&state.orphan&&state.orphan.confirmed&&orphanCount>orphanDeleted)?i18n.orphan_ready:'';
					syncFormFromState(false);
					byId('jrps-delete-bar').style.width=(q?Math.min(100,Math.round(d/q*100)):0)+'%';
					let scanPct=0;
					if(phase==='preview'||phase==='deleting'||phase==='complete'){scanPct=100;}else if(totalUsers>0&&scanned>0){scanPct=Math.max(1,Math.min(99,Math.round(scanned/totalUsers*100)));}else if(scanned>0){scanPct=5;}
					byId('jrps-scan-bar').style.width=scanPct+'%';
					byId('jrps-recent-days').disabled=!byId('jrps-recent-login-enabled').checked;
					let readyForDryRun=byId('jrps-backup-ack').checked&&val('jrps-date-start')&&val('jrps-date-end')&&selectedSpamSignals().length;
					let canResumeDryRun=phase==='scan_paused'&&!!state.scan_signature&&scanned>0;
					byId('jrps-save-setup').textContent=phase==='scan_paused'?i18n.resume_dry_run:i18n.perform_dry_run;
					let canContinueToConfirm=phase==='preview'&&q>0;
					let canUseScannedResults=phase==='scan_paused'&&scanned>0;
					setVisible('jrps-save-setup', !canContinueToConfirm);
					setVisible('jrps-pause-scan', phase==='scanning'&&activeActivity==='scan');
					setVisible('jrps-cancel-scan', phase==='scanning'||phase==='scan_paused');
					setVisible('jrps-use-scanned-results', !canContinueToConfirm&&canUseScannedResults);
					setVisible('jrps-continue-confirm', canContinueToConfirm);
					setDisabled('jrps-save-setup', busy || phase==='scanning' || phase==='deleting' || (phase==='scan_paused' ? !canResumeDryRun : !readyForDryRun));
					setDisabled('jrps-pause-scan', !(phase==='scanning'&&activeActivity==='scan'));
					setDisabled('jrps-cancel-scan', busy && activeActivity!=='scan' || (phase!=='scanning' && phase!=='scan_paused'));
					setDisabled('jrps-use-scanned-results', busy || !canUseScannedResults);
					setDisabled('jrps-continue-confirm', busy || !canContinueToConfirm);
					setDisabled('jrps-reset', busy || !!state.delete_started || phase==='deleting' || phase==='complete');
					setDisabled('jrps-confirm-delete', busy || phase!=='preview' || q<1);
					setDisabled('jrps-delete-batch', busy || phase==='complete' || (phase!=='deleting' && !state.delete_confirmed) || q<1 || d>=q);
					setDisabled('jrps-prepare-next-run', busy || (phase!=='complete' && !state.completed_awaiting_reset));
					setDisabled('jrps-orphan-dry-run', busy || !state.backup_ack);
					setDisabled('jrps-orphan-confirm', busy || !state.backup_ack || !orphanCountVisible || orphanCount<1);
					setDisabled('jrps-orphan-run', busy || !orphanCountVisible || !(state.orphan&&state.orphan.confirmed) || orphanCount<1 || orphanDeleted>=orphanCount);
					setDisabled('jrps-all-dates', summaryRunning || !dateRange.date_start || !dateRange.date_end);
					setLinkDisabled('jrps-download-delete', q<1);
					setLinkDisabled('jrps-download-all', scanned<1);
			}
		function setDisabled(id, disabled){const el=byId(id);if(el){el.disabled=!!disabled;}}
		function setVisible(id, visible){const el=byId(id);if(el){el.style.display=visible?'':'none';}}
		function setLinkDisabled(id, disabled){const el=byId(id);if(el){el.setAttribute('aria-disabled',disabled?'true':'false');}}
		function setListAction(action){let button=byId('jrps-list-action');listAction=action;if(!button){return;}button.textContent=action==='import'?i18n.import_uploaded_list:i18n.verify_list;button.classList.toggle('button-primary',action==='import');}
		function phaseLabel(phase){return (i18n.phase_labels&&i18n.phase_labels[phase])?i18n.phase_labels[phase]:phase;}
		function deleteStatusText(phase,q,d,errors){let status='';if(q<1){return i18n.delete_no_queue;}if(!state.delete_confirmed&&phase!=='deleting'&&phase!=='complete'){return i18n.delete_not_ready;}if(phase==='complete'||d>=q){status=i18n.delete_complete_status;}else if(activeActivity==='delete'){status=i18n.delete_running;}else if(d>0){status=i18n.delete_paused;}else{status=i18n.delete_ready;}return sprintf(i18n.deleted_of,d,q,errors,status);}
		function cleanupStatusText(phase,q,d){if(phase==='deleting'&&activeActivity!=='delete'){if(q>0&&d>=q){return i18n.delete_complete_status;}if(d>0){return i18n.delete_paused;}return i18n.delete_ready;}return phaseLabel(phase);}
		function markAnalysisStale(){if(state.analysis&&state.analysis.done){state.analysis.stale=true;}}
		function formatDuration(seconds){seconds=Math.max(0,parseInt(seconds,10)||0);let hours=Math.floor(seconds/3600), minutes=Math.floor((seconds%3600)/60), secs=seconds%60, parts=[];if(hours){parts.push(hours+'h');}if(hours||minutes){parts.push(minutes+'m');}parts.push(secs+'s');return parts.join(' ');}
			function updateActivity(){let elapsed=activityStarted?Math.max(0,Math.floor((Date.now()-activityStarted)/1000)):0, elapsedText=formatDuration(elapsed);if(activeActivity==='scan'){let totals=state.totals||{}, checked=parseInt(totals.scanned||0,10), total=parseInt(totals.total_users||0,10);byId('jrps-scan-live').textContent=sprintf(i18n.dry_run_working,elapsedText,activityBatches,checked,total||'?');}}
			function startActivity(name){stopActivity();activeActivity=name;activityStarted=Date.now();activityBatches=0;pauseRequested=false;cancelRequested=false;if(name==='scan'){byId('jrps-scan-bar').parentElement.classList.add('is-active');}updateActivity();activityTimer=window.setInterval(updateActivity,1000);render();}
		function stopActivity(){if(activityTimer){window.clearInterval(activityTimer);activityTimer=null;}if(activeActivity==='scan'){byId('jrps-scan-bar').parentElement.classList.remove('is-active');byId('jrps-scan-live').textContent='';}activeActivity='';}
			function requestPause(activity){if(activeActivity===activity){pauseRequested=true;msg(i18n.dry_run_pausing);return;}setBusy(true);post('jr_ps_cleanup_pause_scan',{pause_reason_code:'manual'}).then(()=>msg(i18n.dry_run_paused)).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));}
		function requestCancel(activity){if(activeActivity===activity){cancelRequested=true;msg(i18n.dry_run_canceling);return;}setBusy(true);post('jr_ps_cleanup_cancel_scan').then(()=>msg(i18n.dry_run_canceled)).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));}
		function refreshFormState(){window.setTimeout(render,0);window.setTimeout(render,120);}
		function reasons(){let r=state.reasons||{}, keys=Object.keys(r);if(!keys.length){return '';}let out='<h3>'+esc(i18n.kept_reason_heading)+'</h3><table class="jrps-cleanup-table"><tbody>';keys.forEach(k=>{out+='<tr><td>'+esc(k)+'</td><td>'+esc(r[k])+'</td></tr>';});return out+'</tbody></table>';}
		function listEffect(){let t=state.totals||{};return '<p><strong>'+esc(i18n.list_protected)+':</strong> '+esc(t.list_protected||0)+'<br><strong>'+esc(i18n.list_spam_eligible)+':</strong> '+esc(t.list_spam_eligible||0)+'</p>';}
		function queueSignals(){let signals=state.queue_signals||{}, keys=Object.keys(signals);if(!keys.length){return '';}let out='<h3>'+esc(i18n.queue_signal_heading)+'</h3><p class="jrps-cleanup-muted">'+esc(i18n.queue_signal_note)+'</p><table class="jrps-cleanup-table"><tbody>';keys.forEach(k=>{out+='<tr><td>'+esc(k)+'</td><td>'+esc(signals[k])+'</td></tr>';});return out+'</tbody></table>';}
		function listOverrideSignals(){let signals=state.list_signals||{}, keys=Object.keys(signals);if(!keys.length){return '';}let out='<h3>'+esc(i18n.list_override_heading)+'</h3><p class="jrps-cleanup-muted">'+esc(i18n.list_override_note)+'</p><table class="jrps-cleanup-table"><tbody>';keys.forEach(k=>{out+='<tr><td>'+esc(k)+'</td><td>'+esc(signals[k])+'</td></tr>';});return out+'</tbody></table>';}
		function setDateWindow(start,end){byId('jrps-date-start').value=start||'';byId('jrps-date-end').value=end||'';markAnalysisStale();msg(i18n.date_window_selected);render();}
		function renderYearPicker(years){years=years||[];let summary=state.summary||{}, html='';if(summaryRunning||(!summary.done&&!years.length)){html+='<p class="jrps-cleanup-muted">'+esc(sprintf(i18n.summary_building,summary.scanned||0,summary.eligible||0))+'</p>';}else if(!years.length){html+='<p class="jrps-cleanup-muted">'+esc(i18n.no_eligible_dates)+'</p>';}if(years.length){let rows=years.map(r=>'<tr><td>'+esc(r.yr)+'</td><td>'+esc(r.total)+'</td><td><button type="button" class="button jrps-use-year" data-year="'+esc(r.yr)+'">'+esc(i18n.use_year)+'</button></td></tr>').join('');html+='<table class="jrps-cleanup-table jrps-cleanup-year-picker"><caption>'+esc(i18n.step_hints[2])+'</caption><thead><tr><th>'+esc(i18n.year)+'</th><th>'+esc(i18n.eligible_accounts)+'</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>';}byId('jrps-year-counts').innerHTML=html;document.querySelectorAll('.jrps-use-year').forEach(button=>button.addEventListener('click',function(e){e.preventDefault();let year=this.getAttribute('data-year');setDateWindow(year+'-01-01',year+'-12-31');}));render();}
			function applySummaryPayload(d){dateRange=d.date_range||{};renderYearPicker(d.years||[]);}
			function refreshState(){return post('jr_ps_cleanup_state').then(d=>{applySummaryPayload(d);return d;});}
			function buildSummary(){if(summaryRunning){return;}summaryRunning=true;renderYearPicker([]);function next(){post('jr_ps_cleanup_summary_batch').then(d=>{applySummaryPayload(d);if(!d.done){window.setTimeout(next,100);return;}summaryRunning=false;renderYearPicker(d.years||[]);msg(i18n.summary_complete);}).catch(e=>{summaryRunning=false;render();msg(e.message,true);});}next();}
		function scrollToStep(step){let card=document.querySelector('[data-step-card="'+step+'"]');if(card){card.scrollIntoView({behavior:'smooth',block:'start'});}}
		function resetUiAfterStateReset(message){hydrated=false;syncFormFromState(true);clearTransientFields();render();scrollToStep(0);msg(message);if(!(state.summary&&state.summary.done)){buildSummary();}}
			function loop(action,completeMessage,activity){setBusy(true);return post(action).then(d=>{if(activity){activityBatches++;updateActivity();}if(activity&&cancelRequested){return post('jr_ps_cleanup_cancel_scan').then(()=>{stopActivity();pauseRequested=false;cancelRequested=false;setBusy(false);msg(i18n.dry_run_canceled);});}if(activity&&pauseRequested){return post('jr_ps_cleanup_pause_scan',{pause_reason_code:'manual'}).then(()=>{stopActivity();pauseRequested=false;cancelRequested=false;setBusy(false);msg(i18n.dry_run_paused);});}if(!d.done){return loop(action,completeMessage,activity);}if(activity){stopActivity();}pauseRequested=false;cancelRequested=false;setBusy(false);msg(completeMessage||i18n.batch_complete);}).catch(e=>{if(activity){stopActivity();}pauseRequested=false;cancelRequested=false;setBusy(false);if(activity==='scan'&&e.message===i18n.request_timeout){return post('jr_ps_cleanup_pause_scan',{pause_reason_code:'request_timeout',pause_reason_detail:e.message}).catch(()=>{}).then(()=>{throw e;});}throw e;});}
		function setupData(){return {date_start:val('jrps-date-start'),date_end:val('jrps-date-end'),backup_ack:byId('jrps-backup-ack').checked?1:0,admin_allowlist:val('jrps-admin-allowlist'),fold_gmail:byId('jrps-fold-gmail').checked?1:0,mailing_list_mode:mailingListMode(),cleanup_spam_signals:selectedSpamSignals().join(','),recent_login_protection_enabled:byId('jrps-recent-login-enabled').checked?1:0,recent_days:val('jrps-recent-days')};}
			function saveSetup(){return post('jr_ps_cleanup_save_setup',setupData());}
			function runDryRun(){setBusy(true);return post('jr_ps_cleanup_start_scan').then(()=>{startActivity('scan');return loop('jr_ps_cleanup_scan_batch',i18n.dry_run_complete,'scan');}).catch(e=>{stopActivity();setBusy(false);throw e;});}
			function useScannedResults(){setBusy(true);return post('jr_ps_cleanup_use_scanned_results').then(()=>{msg(i18n.use_scanned_complete);scrollToStep(6);}).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));}
		function confirmOrphanMetadata(){let typed=val('jrps-orphan-confirm-text'), button=byId('jrps-orphan-confirm');byId('jrps-orphan-status').textContent=i18n.orphan_confirming;button.disabled=true;post('jr_ps_cleanup_orphan_confirm',{typed:typed}).then(d=>{state=d.state||state;if(!state.orphan){state.orphan={};}state.orphan.confirmed=true;render();byId('jrps-orphan-status').textContent=i18n.orphan_ready;msg(i18n.orphan_confirmed);byId('jrps-orphan-run').disabled=false;byId('jrps-orphan-run').focus();}).catch(e=>{byId('jrps-orphan-status').textContent=e.message;msg(e.message,true);}).finally(()=>{button.disabled=false;});}
		function runOrphanCleanup(){let button=byId('jrps-orphan-run');button.disabled=true;function next(){let t=state.totals||{};byId('jrps-orphan-status').textContent=sprintf(i18n.orphan_removing,t.orphan_deleted||0,t.orphan_count||0);post('jr_ps_cleanup_orphan_delete_batch').then(d=>{state=d.state||state;render();let totals=state.totals||{};byId('jrps-orphan-status').textContent=sprintf(i18n.orphan_removing,totals.orphan_deleted||0,totals.orphan_count||0);if(!d.done){window.setTimeout(next,100);return;}byId('jrps-orphan-status').textContent=sprintf(i18n.orphan_complete,totals.orphan_deleted||0);msg(sprintf(i18n.orphan_complete,totals.orphan_deleted||0));}).catch(e=>{byId('jrps-orphan-status').textContent=e.message;msg(e.message,true);button.disabled=false;});}next();}
			byId('jrps-save-setup').onclick=function(e){e.preventDefault();if((state.phase||'')==='scan_paused'){runDryRun().catch(e=>msg(e.message,true));return;}setBusy(true);saveSetup().then(()=>{msg(i18n.setup_saved);return runDryRun();}).catch(e=>{setBusy(false);msg(e.message,true);});};
			byId('jrps-pause-scan').onclick=function(e){e.preventDefault();requestPause('scan');};
			byId('jrps-cancel-scan').onclick=function(e){e.preventDefault();requestCancel('scan');};
			byId('jrps-use-scanned-results').onclick=function(e){e.preventDefault();useScannedResults();};
			byId('jrps-continue-confirm').onclick=function(e){e.preventDefault();scrollToStep(6);};
		byId('jrps-reset').onclick=function(e){e.preventDefault();if(confirm(i18n.reset_confirm)){setBusy(true);post('jr_ps_cleanup_reset').then(()=>resetUiAfterStateReset(i18n.reset_complete)).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));}};
		byId('jrps-prepare-next-run').onclick=function(e){e.preventDefault();setBusy(true);post('jr_ps_cleanup_prepare_next_run').then(()=>resetUiAfterStateReset(i18n.prepare_next_run_complete)).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));};
		byId('jrps-upload-form').onsubmit=function(e){e.preventDefault();if(listAction==='import'){byId('jrps-upload-result').insertAdjacentHTML('beforeend','<p><strong>'+esc(i18n.upload_importing)+'</strong></p>');loop('jr_ps_cleanup_import_allowlist_batch').then(()=>{let u=state.upload||{};byId('jrps-upload-result').innerHTML='<p><strong>'+esc(i18n.upload_imported)+'</strong></p><p>'+esc(sprintf(i18n.upload_import_summary,u.imported||0,u.invalid||0))+'</p>';setListAction('verify');msg(i18n.upload_imported);}).catch(e=>msg(e.message,true));return;}let fd=new FormData(this);fd.append('action','jr_ps_cleanup_upload_allowlist');fd.append('nonce',nonce);setBusy(true);post('jr_ps_cleanup_upload_allowlist',fd,true).then(d=>{let u=d.state.upload||{}, displayColumn=parseInt(u.email_column||0,10)+1, columnLabel=displayColumn===1?i18n.first_column:sprintf(i18n.column_number,displayColumn);byId('jrps-upload-result').innerHTML='<p><strong>'+esc(i18n.detected_column)+':</strong> '+esc(displayColumn)+' ('+esc(columnLabel)+')</p><p><strong>'+esc(i18n.upload_samples)+':</strong> '+esc((u.samples||[]).join(', '))+'</p><p class="jrps-cleanup-muted">'+esc(i18n.upload_review)+'</p>';setListAction('import');msg(i18n.upload_accepted);}).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));};
		byId('jrps-allowlist-file').onchange=function(){setListAction('verify');byId('jrps-upload-result').innerHTML='';};
		byId('jrps-confirm-delete').onclick=function(e){e.preventDefault();setBusy(true);post('jr_ps_cleanup_confirm_delete',{typed:val('jrps-confirm-text')}).then(()=>msg(i18n.queue_confirmed)).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));};
		byId('jrps-delete-batch').onclick=function(e){e.preventDefault();startActivity('delete');loop('jr_ps_cleanup_delete_batch',i18n.batch_complete,'delete').catch(e=>msg(e.message,true));};
		byId('jrps-orphan-dry-run').onclick=function(e){e.preventDefault();setBusy(true);post('jr_ps_cleanup_orphan_dry_run').then(()=>{orphanCountVisible=true;render();msg(i18n.orphan_dry_run_complete);}).catch(e=>msg(e.message,true)).finally(()=>setBusy(false));};
		byId('jrps-orphan-confirm').onclick=function(e){e.preventDefault();confirmOrphanMetadata();};
		byId('jrps-orphan-run').onclick=function(e){e.preventDefault();runOrphanCleanup();};
		byId('jrps-backup-ack').onchange=function(){state.backup_ack=this.checked;render();};
		byId('jrps-date-start').onchange=function(){markAnalysisStale();render();};
		byId('jrps-date-end').onchange=function(){markAnalysisStale();render();};
		byId('jrps-date-start').oninput=function(){markAnalysisStale();render();};
		byId('jrps-date-end').oninput=function(){markAnalysisStale();render();};
		byId('jrps-admin-allowlist').oninput=function(){markAnalysisStale();};
		byId('jrps-fold-gmail').onchange=function(){markAnalysisStale();render();};
		byId('jrps-recent-login-enabled').onchange=function(){state.recent_login_protection_enabled=this.checked;markAnalysisStale();render();};
		byId('jrps-recent-days').oninput=function(){markAnalysisStale();};
		document.querySelectorAll('input[name="jrps-mailing-list-mode"]').forEach(input=>{input.onchange=function(){state.mailing_list_mode=mailingListMode();markAnalysisStale();render();};});
		document.querySelectorAll('.jrps-cleanup-spam-signal').forEach(input=>{input.onchange=function(){state.cleanup_spam_signals=selectedSpamSignals();markAnalysisStale();render();};});
		byId('jrps-all-dates').onclick=function(e){e.preventDefault();if(dateRange.date_start&&dateRange.date_end){setDateWindow(dateRange.date_start,dateRange.date_end);}};
		document.querySelectorAll('[data-step-nav]').forEach(nav=>{nav.addEventListener('click',function(){scrollToStep(this.getAttribute('data-step-nav'));});nav.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();scrollToStep(this.getAttribute('data-step-nav'));}});});
		document.querySelectorAll('a.button').forEach(a=>a.addEventListener('click',function(e){if(this.getAttribute('aria-disabled')==='true'){e.preventDefault();}}));
		function val(id){return byId(id).value;}
		function mailingListMode(){let checked=document.querySelector('input[name="jrps-mailing-list-mode"]:checked');return checked?checked.value:'absolute';}
		function selectedSpamSignals(){return Array.from(document.querySelectorAll('.jrps-cleanup-spam-signal:checked')).map(input=>input.value);}
			msg(i18n.loading);
			window.addEventListener('pageshow',refreshFormState);
			stateWatchTimer=window.setInterval(function(){if((state.phase||'')==='scanning'&&activeActivity!=='scan'&&!busy){refreshState().catch(()=>{});}},15000);
			post('jr_ps_cleanup_state').then(d=>{applySummaryPayload(d);refreshFormState();msg(i18n.ready);if(!(state.summary&&state.summary.done)){buildSummary();}}).catch(e=>msg(e.message,true));
		})();
		</script>
	<?php
}
