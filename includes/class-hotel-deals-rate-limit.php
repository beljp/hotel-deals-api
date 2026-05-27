<?php
/**
 * Simple IP-based rate limiting via WordPress transients.
 *
 * Defaults: 60 requests per 60 seconds per IP.
 * Override with filters:
 *   add_filter( 'hotel_deals_api_rate_limit',  fn() => 120 );  // requests
 *   add_filter( 'hotel_deals_api_rate_window', fn() => 60  );  // seconds
 *   add_filter( 'hotel_deals_api_rate_limit',  fn() => 0   );  // 0 = disabled
 *
 * @package HotelDealsApi
 */

defined( 'ABSPATH' ) || exit;

final class Hotel_Deals_Rate_Limiter {

	/**
	 * @return true|WP_Error  Returns true on pass, WP_Error with 429 on reject.
	 */
	public static function check(): true|WP_Error {
		$limit  = (int) apply_filters( 'hotel_deals_api_rate_limit',  60 );
		$window = (int) apply_filters( 'hotel_deals_api_rate_window', 60 );

		if ( $limit <= 0 ) {
			return true;
		}

		$ip    = self::client_ip();
		$key   = 'hda_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				'Too many requests. Please try again in a moment.',
				[ 'status' => 429 ]
			);
		}

		// Slight over-counting at burst boundaries is acceptable.
		if ( $count === 0 ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, $count + 1, $window );
		}

		return true;
	}

	private static function client_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}
}
