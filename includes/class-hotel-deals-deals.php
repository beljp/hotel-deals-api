<?php
/**
 * Deals endpoint handler.
 *
 * GET /wp-json/hotel-deals/v1/deals               – filterable deal list
 * GET /wp-json/hotel-deals/v1/hotels/{id}/deals   – deals for one hotel
 *
 * Returned fields match what [hotel_aanbiedingen] shortcode displays.
 * Raw affiliate links are never exposed; the cloaked /ga.php?id=X URL is used.
 *
 * Database join path:
 *   deals_db.deals  →  deals_db.hotels (via hotel_id = hotels.id)
 *                   →  wp_db.wp_posts  (via hotels.wp_hotel_id = wp_posts.ID)
 *
 * @package HotelDealsApi
 */

defined( 'ABSPATH' ) || exit;

final class Hotel_Deals_Deals_Endpoint {

	private const CACHE_TTL       = 300;
	private const ALLOWED_SOURCES = [ 'voordeeluitjes', 'hotelspecials', 'zoweg', 'weekendesk' ];

	// ── Argument definitions ─────────────────────────────────────────────────

	public function get_collection_args(): array {
		return [
			'city'          => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'province'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'source'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static fn( $v ) => in_array( sanitize_key( $v ), self::ALLOWED_SOURCES, true ),
			],
			'max_price'     => [
				'type'              => 'number',
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && (float) $v > 0,
				'sanitize_callback' => static fn( $v ) => round( (float) $v, 2 ),
			],
			'min_price'     => [
				'type'              => 'number',
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && (float) $v >= 0,
				'sanitize_callback' => static fn( $v ) => round( (float) $v, 2 ),
			],
			'stars'         => [
				'type'              => 'integer',
				'validate_callback' => static fn( $v ) => in_array( (int) $v, [ 1, 2, 3, 4, 5 ], true ),
				'sanitize_callback' => 'absint',
			],
			'check_in_from' => [
				'type'              => 'string',
				'validate_callback' => static fn( $v ) => (bool) strtotime( $v ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'check_in_to'   => [
				'type'              => 'string',
				'validate_callback' => static fn( $v ) => (bool) strtotime( $v ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'limit'         => [
				'type'              => 'integer',
				'default'           => 10,
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v > 0 && $v <= 50,
				'sanitize_callback' => 'absint',
			],
			'page'          => [
				'type'              => 'integer',
				'default'           => 1,
				'validate_callback' => static fn( $v ) => is_numeric( $v ) && $v > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	// ── /deals ───────────────────────────────────────────────────────────────

	public function get_deals( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate = Hotel_Deals_Rate_Limiter::check();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$params    = $request->get_params();
		$cache_key = 'hda_deals_' . md5( serialize( $params ) );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		$limit  = min( (int) ( $params['limit'] ?? 10 ), 50 );
		$page   = max( (int) ( $params['page']  ?? 1  ), 1  );

		// Province filter: resolve wp_post_ids via WP term tables first.
		$province_wp_ids = null;
		if ( ! empty( $params['province'] ) ) {
			$province_wp_ids = $this->wp_ids_by_province( $params['province'] );
			if ( empty( $province_wp_ids ) ) {
				return rest_ensure_response( $this->empty_response( $page ) );
			}
		}

		[ $where, $values ] = $this->build_where( $params, $province_wp_ids );

		global $wpdb;
		$d_table  = Hotel_Deals_Db::deals_table();
		$h_table  = Hotel_Deals_Db::hotels_table();
		$wp_posts = '`' . DB_NAME . '`.' . $wpdb->posts;
		$wp_meta  = '`' . DB_NAME . '`.' . $wpdb->postmeta;

		$joins = "JOIN {$h_table} h ON h.id = d.hotel_id
		          INNER JOIN {$wp_posts} p
		               ON p.ID = h.wp_hotel_id
		              AND p.post_status = 'publish'
		              AND p.post_type   = 'hotel'
		          LEFT JOIN {$wp_meta} pm_stars
		               ON pm_stars.post_id  = h.wp_hotel_id
		              AND pm_stars.meta_key = 'stars'";

		$base = "FROM {$d_table} d {$joins} WHERE d.is_available = 1 {$where}";

		$total = (int) ( empty( $values )
			? $wpdb->get_var( "SELECT COUNT(*) {$base}" ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", ...$values ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$select = $this->select_columns();
		$sql    = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"{$select} {$base} ORDER BY d.price ASC LIMIT %d OFFSET %d",
			...array_merge( $values, [ $limit, ( $page - 1 ) * $limit ] )
		);

		$rows  = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = array_map( [ $this, 'format_deal' ], $rows ?: [] );
		$items = apply_filters( 'hotel_deals_api_deals_items', $items, $params );

		$response = [
			'source' => 'hotelaanbiedingen.com',
			'count'  => $total,
			'pages'  => $total > 0 ? (int) ceil( $total / $limit ) : 0,
			'page'   => $page,
			'items'  => $items,
		];

		$response = apply_filters( 'hotel_deals_api_deals_response', $response, $params );
		set_transient( $cache_key, $response, self::CACHE_TTL );

		return rest_ensure_response( $response );
	}

	// ── /hotels/{id}/deals ───────────────────────────────────────────────────

	public function get_hotel_deals( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate = Hotel_Deals_Rate_Limiter::check();
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$wp_post_id = (int) $request->get_param( 'id' );

		// Verify the hotel post exists and is published.
		$post = get_post( $wp_post_id );
		if ( ! $post || $post->post_type !== 'hotel' || $post->post_status !== 'publish' ) {
			return new WP_Error( 'hotel_not_found', 'Hotel not found.', [ 'status' => 404 ] );
		}

		$cache_key = 'hda_hotel_deals_' . $wp_post_id;
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		global $wpdb;
		$d_table  = Hotel_Deals_Db::deals_table();
		$h_table  = Hotel_Deals_Db::hotels_table();
		$wp_meta  = '`' . DB_NAME . '`.' . $wpdb->postmeta;

		// City and stars from WordPress; rest from deals database.
		$sql = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT
			    d.id              AS deal_id,
			    d.source,
			    d.name            AS deal_title,
			    d.price,
			    d.price_original,
			    d.discount_pct,
			    d.offer_nights,
			    d.offer_price_info AS price_info,
			    d.check_in,
			    d.check_out,
			    d.meal_type,
			    d.room_type,
			    d.contents,
			    d.updated_at,
			    h.hotel_name,
			    h.city,
			    pm_stars.meta_value AS stars
			FROM {$d_table} d
			JOIN {$h_table} h ON h.id = d.hotel_id
			LEFT JOIN {$wp_meta} pm_stars
			       ON pm_stars.post_id  = h.wp_hotel_id
			      AND pm_stars.meta_key = 'stars'
			WHERE h.wp_hotel_id = %d
			  AND d.is_available = 1
			ORDER BY d.price ASC",
			$wp_post_id
		);

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$hotel_url = get_permalink( $wp_post_id ) ?: null;
		$items     = array_map(
			fn( $row ) => $this->format_deal( $row, $hotel_url ),
			$rows ?: []
		);

		$response = [
			'source'    => 'hotelaanbiedingen.com',
			'hotel_id'  => $wp_post_id,
			'hotel_url' => $hotel_url,
			'count'     => count( $items ),
			'items'     => $items,
		];

		$response = apply_filters( 'hotel_deals_api_hotel_deals_response', $response, $wp_post_id );
		set_transient( $cache_key, $response, self::CACHE_TTL );

		return rest_ensure_response( $response );
	}

	// ── Query helpers ─────────────────────────────────────────────────────────

	private function select_columns(): string {
		return 'SELECT
		    d.id              AS deal_id,
		    d.source,
		    d.name            AS deal_title,
		    d.price,
		    d.price_original,
		    d.discount_pct,
		    d.offer_nights,
		    d.offer_price_info AS price_info,
		    d.check_in,
		    d.check_out,
		    d.meal_type,
		    d.room_type,
		    d.contents,
		    d.updated_at,
		    h.wp_hotel_id     AS wp_post_id,
		    h.hotel_name,
		    h.city,
		    pm_stars.meta_value AS stars';
	}

	/**
	 * @return array{string, array<mixed>}  [$where_clause, $values]
	 */
	private function build_where( array $params, ?array $province_wp_ids ): array {
		global $wpdb;

		$conditions = [];
		$values     = [];

		if ( ! empty( $params['city'] ) ) {
			$conditions[] = 'h.city LIKE %s';
			$values[]     = '%' . $wpdb->esc_like( $params['city'] ) . '%';
		}

		if ( ! empty( $params['source'] ) && in_array( $params['source'], self::ALLOWED_SOURCES, true ) ) {
			$conditions[] = 'd.source = %s';
			$values[]     = $params['source'];
		}

		if ( isset( $params['max_price'] ) && $params['max_price'] !== '' ) {
			$conditions[] = 'd.price <= %f';
			$values[]     = (float) $params['max_price'];
		}

		if ( isset( $params['min_price'] ) && $params['min_price'] !== '' ) {
			$conditions[] = 'd.price >= %f';
			$values[]     = (float) $params['min_price'];
		}

		if ( ! empty( $params['stars'] ) ) {
			$conditions[] = 'pm_stars.meta_value = %d';
			$values[]     = (int) $params['stars'];
		}

		if ( ! empty( $params['check_in_from'] ) ) {
			$conditions[] = 'd.check_in >= %s';
			$values[]     = gmdate( 'Y-m-d', strtotime( $params['check_in_from'] ) );
		}

		if ( ! empty( $params['check_in_to'] ) ) {
			$conditions[] = 'd.check_in <= %s';
			$values[]     = gmdate( 'Y-m-d', strtotime( $params['check_in_to'] ) );
		}

		if ( $province_wp_ids !== null && ! empty( $province_wp_ids ) ) {
			$ph           = implode( ',', array_fill( 0, count( $province_wp_ids ), '%d' ) );
			$conditions[] = "h.wp_hotel_id IN ({$ph})";
			array_push( $values, ...$province_wp_ids );
		}

		$where = $conditions ? ( ' AND ' . implode( ' AND ', $conditions ) ) : '';

		return [ $where, $values ];
	}

	/** Returns WordPress post IDs belonging to a provincie term slug. */
	private function wp_ids_by_province( string $province ): array {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT tr.object_id
			 FROM {$wpdb->term_relationships} tr
			 INNER JOIN {$wpdb->term_taxonomy} tt
			        ON tt.term_taxonomy_id = tr.term_taxonomy_id
			       AND tt.taxonomy         = 'provincie'
			 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE t.slug = %s",
			sanitize_title( $province )
		) );
	}

	// ── Formatting ────────────────────────────────────────────────────────────

	/**
	 * Formats one deal row into the public API shape.
	 * Matches the data the [hotel_aanbiedingen] shortcode renders.
	 *
	 * @param object      $row       DB result row.
	 * @param string|null $hotel_url Pre-computed permalink (avoids repeated get_permalink calls).
	 */
	private function format_deal( object $row, ?string $hotel_url = null ): array {
		// Cloaked redirect URL — same mechanism as [hotel_aanbiedingen] shortcode.
		$deal_url = home_url( '/ga.php?id=' . (int) $row->deal_id );

		// hotel_url: use pre-computed value when called in bulk, otherwise look it up.
		if ( $hotel_url === null ) {
			$wp_post_id = isset( $row->wp_post_id ) ? (int) $row->wp_post_id : null;
			$hotel_url  = $wp_post_id ? ( get_permalink( $wp_post_id ) ?: null ) : null;
		}

		// Decode the contents JSON array (amenity icons/labels the shortcode renders).
		$contents = [];
		if ( ! empty( $row->contents ) ) {
			$decoded = json_decode( $row->contents, true );
			if ( is_array( $decoded ) ) {
				$contents = $decoded;
			}
		}

		$deal = [
			'deal_id'        => (int) $row->deal_id,
			'hotel_id'       => isset( $row->wp_post_id ) ? (int) $row->wp_post_id : null,
			'hotel_name'     => $row->hotel_name ?? '',
			'city'           => $row->city ?? '',
			'stars'          => isset( $row->stars ) ? (int) $row->stars : null,
			'source'         => $row->source,
			'deal_title'     => $row->deal_title,
			'price'          => $row->price !== null ? (float) $row->price : null,
			'price_original' => $row->price_original !== null ? (float) $row->price_original : null,
			'discount_pct'   => $row->discount_pct !== null ? (int) $row->discount_pct : null,
			'currency'       => 'EUR',
			'offer_nights'   => $row->offer_nights !== null ? (int) $row->offer_nights : null,
			'meal_type'      => $row->meal_type,
			'room_type'      => $row->room_type,
			'price_info'     => $row->price_info,
			'contents'       => $contents,
			'check_in'       => $row->check_in,
			'check_out'      => $row->check_out,
			'deal_url'       => $deal_url,
			'hotel_url'      => $hotel_url,
			'last_updated'   => substr( $row->updated_at, 0, 10 ),
		];

		return apply_filters( 'hotel_deals_api_deal_item', $deal, $row );
	}

	private function empty_response( int $page ): array {
		return [
			'source' => 'hotelaanbiedingen.com',
			'count'  => 0,
			'pages'  => 0,
			'page'   => $page,
			'items'  => [],
		];
	}
}
