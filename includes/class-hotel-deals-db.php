<?php
/**
 * Database table name helpers.
 *
 * All queries run through WordPress's own $wpdb connection.
 * When the deals database differs from the WordPress database, table names are
 * returned as `database`.table so MySQL resolves them cross-database.
 *
 * The WordPress DB user needs SELECT on the deals database:
 *   GRANT SELECT ON `your_deals_database`.* TO '<wp-db-user>'@'localhost';
 *
 * Add to wp-config.php:
 *   define( 'HOTEL_DEALS_DB_NAME', 'your_deals_database_name' );
 *
 * Database structure in the deals database:
 *   hotels: id, wp_hotel_id, hotel_name, city, action_id, external_id, website_url
 *   deals:  id, hotel_id (→ hotels.id), source, name, price, …
 *
 * @package HotelDealsApi
 */

defined( 'ABSPATH' ) || exit;

final class Hotel_Deals_Db {

	/** Fully-qualified deals table name (cross-database when needed). */
	public static function deals_table(): string {
		return self::qualified( apply_filters( 'hotel_deals_api_deals_table', 'deals' ) );
	}

	/** Fully-qualified hotels table name (in the deals database). */
	public static function hotels_table(): string {
		return self::qualified( apply_filters( 'hotel_deals_api_hotels_ref_table', 'hotels' ) );
	}

	/**
	 * Prepends the deals-database name when it differs from the WP database,
	 * so MySQL resolves the table across databases.
	 */
	private static function qualified( string $table ): string {
		if ( ! defined( 'HOTEL_DEALS_DB_NAME' ) || HOTEL_DEALS_DB_NAME === DB_NAME ) {
			return $table;
		}

		return '`' . HOTEL_DEALS_DB_NAME . '`.' . $table;
	}
}
