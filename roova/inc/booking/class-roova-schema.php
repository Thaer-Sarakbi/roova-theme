<?php
/**
 * The bookings table.
 *
 * Every hold, pending and confirmed stay lives here — this table is the single
 * source of truth for availability.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Schema
 */
class Roova_Schema {

	/**
	 * Bump when the table definition changes.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option storing the installed schema version.
	 */
	const VERSION_OPTION = 'roova_db_version';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'after_switch_theme', array( __CLASS__, 'install' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'roova_bookings';
	}

	/**
	 * Does the table exist?
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * order_item_id is nullable so the UNIQUE key can guarantee one booking
		 * row per order line without blocking the many rows that are still
		 * holds and have no order yet (MySQL allows repeated NULLs in a
		 * unique index).
		 */
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			room_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			hotel_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			order_item_id BIGINT UNSIGNED DEFAULT NULL,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			cart_item_key VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'hold',
			check_in DATE NOT NULL,
			check_out DATE NOT NULL,
			units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			adults SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			guest_name VARCHAR(200) NOT NULL DEFAULT '',
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY room_dates (room_id, check_in, check_out),
			KEY status_expiry (status, expires_at),
			KEY order_id (order_id),
			KEY hotel_id (hotel_id),
			KEY session_id (session_id),
			UNIQUE KEY order_item (order_item_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Install or upgrade when the stored version is behind.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION && self::table_exists() ) {
			return;
		}
		self::install();
	}
}
