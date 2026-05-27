<?php
/**
 * Plugin Name:  Hotel Deals API
 * Plugin URI:   https://github.com/hotelaanbiedingen/hotel-deals-api
 * Description:  Public, read-only REST API exposing hotel deals. Powered by HotelAanbiedingen.com
 * Version:      1.0.0
 * Requires PHP: 8.0
 * Author:       HotelAanbiedingen.com
 * Author URI:   https://hotelaanbiedingen.com
 * License:      MIT
 * Text Domain:  hotel-deals-api
 *
 * @package HotelDealsApi
 */

defined( 'ABSPATH' ) || exit;

define( 'HOTEL_DEALS_API_VERSION',   '1.0.0' );
define( 'HOTEL_DEALS_API_DIR',       plugin_dir_path( __FILE__ ) );
define( 'HOTEL_DEALS_API_NAMESPACE', 'hotel-deals/v1' );

require_once HOTEL_DEALS_API_DIR . 'includes/class-hotel-deals-db.php';
require_once HOTEL_DEALS_API_DIR . 'includes/class-hotel-deals-rate-limit.php';
require_once HOTEL_DEALS_API_DIR . 'includes/class-hotel-deals-hotels.php';
require_once HOTEL_DEALS_API_DIR . 'includes/class-hotel-deals-deals.php';

final class Hotel_Deals_Api {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'maybe_add_cors_headers' ] );
	}

	public function register_routes(): void {
		$hotels = new Hotel_Deals_Hotels_Endpoint();
		$deals  = new Hotel_Deals_Deals_Endpoint();

		// GET /wp-json/hotel-deals/v1/hotels
		register_rest_route( HOTEL_DEALS_API_NAMESPACE, '/hotels', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $hotels, 'get_hotels' ],
			'permission_callback' => '__return_true',
			'args'                => $hotels->get_collection_args(),
		] );

		// GET /wp-json/hotel-deals/v1/hotels/{id}
		register_rest_route( HOTEL_DEALS_API_NAMESPACE, '/hotels/(?P<id>[\d]+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $hotels, 'get_hotel' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// GET /wp-json/hotel-deals/v1/hotels/{id}/deals
		register_rest_route( HOTEL_DEALS_API_NAMESPACE, '/hotels/(?P<id>[\d]+)/deals', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $deals, 'get_hotel_deals' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// GET /wp-json/hotel-deals/v1/deals
		register_rest_route( HOTEL_DEALS_API_NAMESPACE, '/deals', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $deals, 'get_deals' ],
			'permission_callback' => '__return_true',
			'args'                => $deals->get_collection_args(),
		] );
	}

	public function maybe_add_cors_headers(): void {
		// CORS is opt-in. Enable with:  add_filter('hotel_deals_api_enable_cors', '__return_true');
		if ( ! apply_filters( 'hotel_deals_api_enable_cors', false ) ) {
			return;
		}

		$allowed = apply_filters( 'hotel_deals_api_cors_origins', [ '*' ] );
		$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

		if ( in_array( '*', $allowed, true ) ) {
			header( 'Access-Control-Allow-Origin: *' );
		} elseif ( in_array( $origin, $allowed, true ) ) {
			header( "Access-Control-Allow-Origin: {$origin}" );
		}

		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type' );
	}
}

new Hotel_Deals_Api();
