<?php
/**
 * Hotels endpoint handler.
 *
 * GET /wp-json/hotel-deals/v1/hotels
 * GET /wp-json/hotel-deals/v1/hotels/{id}
 *
 * @package HotelDealsApi
 */

defined( 'ABSPATH' ) || exit;

final class Hotel_Deals_Hotels_Endpoint {

	private const CACHE_TTL = 300; // 5 minutes

	// ── Arguments ────────────────────────────────────────────────────────────

	public function get_collection_args(): array {
		return [
			'city'     => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'province' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'stars'    => [
				'type'              => 'integer',
				'validate_callback' => static fn( $v ) => in_array( (int) $v, [ 1, 2, 3, 4, 5 ], true ),
				'sanitize_callback' => 'absint',
			],
			'limit'    => [
				'type'              => 'integer',
				'default'           => 10,
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v > 0 && $v <= 50,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	// ── Collection ───────────────────────────────────────────────────────────

	public function get_hotels( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate = Hotel_Deals_Rate_Limiter::check();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params    = $request->get_params();
		$cache_key = 'hda_hotels_' . md5( serialize( $params ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		$limit  = min( (int) ( $params['limit'] ?? 10 ), 50 );
		$page   = max( (int) ( $params['page']  ?? 1  ), 1  );

		$args = [
			'post_type'      => 'hotel',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'no_found_rows'  => false,
			'meta_query'     => [],
			'tax_query'      => [],
		];

		if ( ! empty( $params['city'] ) ) {
			$args['meta_query'][] = [
				'key'     => 'plaats_naam',
				'value'   => $params['city'],
				'compare' => 'LIKE',
			];
		}

		if ( ! empty( $params['stars'] ) ) {
			$args['meta_query'][] = [
				'key'     => 'stars',
				'value'   => (int) $params['stars'],
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		if ( ! empty( $params['province'] ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'provincie',
				'field'    => 'slug',
				'terms'    => sanitize_title( $params['province'] ),
			];
		}

		$query  = new WP_Query( $args );
		$items  = array_map( [ $this, 'format_hotel' ], $query->posts );

		$response = [
			'source' => 'hotelaanbiedingen.com',
			'count'  => $query->found_posts,
			'pages'  => (int) ceil( $query->found_posts / $limit ),
			'page'   => $page,
			'items'  => $items,
		];

		$response = apply_filters( 'hotel_deals_api_hotels_response', $response, $params );
		set_transient( $cache_key, $response, self::CACHE_TTL );

		return rest_ensure_response( $response );
	}

	// ── Single item ──────────────────────────────────────────────────────────

	public function get_hotel( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate = Hotel_Deals_Rate_Limiter::check();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$id        = $request->get_param( 'id' );
		$cache_key = 'hda_hotel_' . $id;
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		$post = get_post( $id );

		if ( ! $post || $post->post_type !== 'hotel' || $post->post_status !== 'publish' ) {
			return new WP_Error( 'hotel_not_found', 'Hotel not found.', [ 'status' => 404 ] );
		}

		$response = $this->format_hotel( $post );
		$response = apply_filters( 'hotel_deals_api_single_hotel_response', $response, $id );
		set_transient( $cache_key, $response, self::CACHE_TTL );

		return rest_ensure_response( $response );
	}

	// ── Formatting ───────────────────────────────────────────────────────────

	public function format_hotel( WP_Post $post ): array {
		$meta = get_post_meta( $post->ID );

		$provinces  = wp_get_post_terms( $post->ID, 'provincie',   [ 'fields' => 'names' ] );
		$chains     = wp_get_post_terms( $post->ID, 'hotel-keten', [ 'fields' => 'names' ] );
		$categories = wp_get_post_terms( $post->ID, 'categorie',   [ 'fields' => 'names' ] );

		$hotel = [
			'hotel_id'   => $post->ID,
			'hotel_name' => $post->post_title,
			'city'       => $meta['plaats_naam'][0] ?? '',
			'province'   => ! is_wp_error( $provinces )  ? ( $provinces[0] ?? '' ) : '',
			'stars'      => isset( $meta['stars'][0] ) ? (int) $meta['stars'][0] : null,
			'image'      => has_post_thumbnail( $post->ID )
							? get_the_post_thumbnail_url( $post->ID, 'medium' )
							: null,
			'hotel_url'  => get_permalink( $post->ID ),
			'chains'     => ! is_wp_error( $chains )     ? $chains     : [],
			'categories' => ! is_wp_error( $categories ) ? $categories : [],
		];

		return apply_filters( 'hotel_deals_api_hotel_item', $hotel, $post );
	}
}
