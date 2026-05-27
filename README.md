# Hotel Deals API

A public, read-only WordPress REST API exposing hotel deals in the Netherlands.
Built for developers, travel bloggers, and affiliates who want structured access to hotel deal data.

**Live demo →** [hotelaanbiedingen.github.io/hotel-deals-api](https://beljp.github.io/hotel-deals-api)  
**Data source →** [HotelAanbiedingen.com](https://hotelaanbiedingen.com)

---

## What it does

The plugin registers three REST endpoints on any WordPress site:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/hotel-deals/v1/hotels` | Paginated hotel list |
| GET | `/wp-json/hotel-deals/v1/hotels/{id}` | Single hotel |
| GET | `/wp-json/hotel-deals/v1/hotels/{id}/deals` | All deals for one hotel |
| GET | `/wp-json/hotel-deals/v1/deals` | Filterable deal list across all hotels |

All responses are JSON, read-only, cached, and rate-limited. No authentication required.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- The `hotel` custom post type registered on your site
- A `deals` table (see [Database setup](#database-setup))

---

## Installation

1. Download or clone this repository.
2. Copy the plugin folder (everything except `docs/`) into `wp-content/plugins/hotel-deals-api/`.
3. Activate the plugin in **WordPress Admin → Plugins**.
4. Follow the [Database setup](#database-setup) and [Configuration](#configuration) steps below.

---

## Database setup

The plugin reads deals from a `deals` table. This table may live in the same database as WordPress or in a separate database.

### Same database

If the `deals` table is in the WordPress database, no extra configuration is needed.

### Separate database (recommended setup)

When the `deals` table is in a different MySQL database you need to:

**1. Grant SELECT access to the WordPress DB user:**

```sql
GRANT SELECT ON `your_deals_database`.`deals` TO 'wp_db_user'@'localhost';
FLUSH PRIVILEGES;
```

You can find your WordPress database credentials in `wp-config.php`.

**2. Tell the plugin which database to use** (see [Configuration](#configuration)).

---

## Configuration

Add these constants to your `wp-config.php` (before `/* That's all, stop editing! */`):

```php
// Required only when the deals table is in a different database than WordPress
define( 'HOTEL_DEALS_DB_NAME', 'your_deals_database_name' );
```

### Optional: enable CORS for the GitHub Pages demo

Add this to your theme's `functions.php` or a site-specific plugin:

```php
// Allow any origin to read the public API (GET only)
add_filter( 'hotel_deals_api_enable_cors', '__return_true' );

// Or restrict to specific origins:
add_filter( 'hotel_deals_api_cors_origins', fn() => [
    'https://your-frontend.com',
    'https://beljp.github.io',
] );
```

---

## Endpoints

### GET /wp-json/hotel-deals/v1/hotels/{id}/deals

Returns all current deals for a single hotel. This is the same data that the `[hotel_aanbiedingen]` shortcode renders on the hotel page.

```bash
curl "https://hotelaanbiedingen.com/wp-json/hotel-deals/v1/hotels/4821/deals"
```

#### Example response

```json
{
  "source": "hotelaanbiedingen.com",
  "hotel_id": 4821,
  "hotel_url": "https://hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
  "count": 3,
  "items": [
    {
      "deal_id": 98234,
      "hotel_id": 4821,
      "hotel_name": "Van der Valk Hotel Amsterdam",
      "city": "Amsterdam",
      "stars": 4,
      "source": "voordeeluitjes",
      "deal_title": "Weekenddeal inclusief ontbijt",
      "price": 119.00,
      "price_original": 159.00,
      "discount_pct": 25,
      "currency": "EUR",
      "offer_nights": 2,
      "meal_type": "Ontbijt inbegrepen",
      "room_type": "Standaard tweepersoonskamer",
      "price_info": "Per persoon, per nacht",
      "contents": ["Ontbijt", "Gratis parkeren", "Late checkout"],
      "check_in": "2026-06-14",
      "check_out": "2026-06-16",
      "deal_url": "https://hotelaanbiedingen.com/ga.php?id=98234",
      "hotel_url": "https://hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
      "last_updated": "2026-05-27"
    }
  ]
}
```


### GET /wp-json/hotel-deals/v1/deals

Returns a paginated list of available deals.

#### Query parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `city` | string | — | Filter by city (partial match) |
| `province` | string | — | Filter by province slug (exact match) |
| `max_price` | number | — | Maximum price in EUR |
| `min_price` | number | — | Minimum price in EUR |
| `stars` | integer | — | Hotel star rating (1–5) |
| `source` | string | — | Deal source: `voordeeluitjes`, `hotelspecials`, or `zoweg` |
| `check_in_from` | date | — | Earliest check-in date (YYYY-MM-DD) |
| `check_in_to` | date | — | Latest check-in date (YYYY-MM-DD) |
| `limit` | integer | 10 | Results per page (max 50) |
| `page` | integer | 1 | Page number |

#### Example requests

```bash
# Deals in Amsterdam under €150
curl "https://hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?city=Amsterdam&max_price=150"

# 4-star deals from Voordeeluitjes
curl "https://hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?stars=4&source=voordeeluitjes"

# Weekend deals in June 2026
curl "https://hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?check_in_from=2026-06-01&check_in_to=2026-06-30&limit=20"
```

#### Example response

```json
{
  "source": "hotelaanbiedingen.com",
  "count": 142,
  "pages": 15,
  "page": 1,
  "items": [
    {
      "hotel_id": 4821,
      "hotel_name": "Van der Valk Hotel Amsterdam",
      "city": "Amsterdam",
      "stars": 4,
      "source": "voordeeluitjes",
      "deal_title": "Weekenddeal inclusief ontbijt",
      "price": 119.00,
      "price_original": 159.00,
      "discount_pct": 25,
      "currency": "EUR",
      "offer_nights": 2,
      "meal_type": "Ontbijt inbegrepen",
      "room_type": "Standaard tweepersoonskamer",
      "price_info": "Per persoon, per nacht",
      "check_in": "2026-06-14",
      "check_out": "2026-06-16",
      "offer_link": "https://www.voordeeluitjes.nl/...",
      "hotel_url": "https://hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
      "last_updated": "2026-05-27"
    }
  ]
}
```

---

### GET /wp-json/hotel-deals/v1/hotels

Returns a list of hotels.

#### Query parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `city` | string | Filter by city |
| `province` | string | Filter by province slug |
| `stars` | integer | Filter by star rating (1–5) |
| `limit` | integer | Results per page (max 50, default 10) |
| `page` | integer | Page number |

#### Example response

```json
{
  "source": "hotelaanbiedingen.com",
  "count": 1286,
  "pages": 129,
  "page": 1,
  "items": [
    {
      "hotel_id": 4821,
      "hotel_name": "Van der Valk Hotel Amsterdam",
      "city": "Amsterdam",
      "province": "Noord-Holland",
      "stars": 4,
      "image": "https://hotelaanbiedingen.com/wp-content/uploads/...",
      "hotel_url": "https://hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
      "chains": ["Van der Valk"],
      "categories": ["Met ontbijt", "Wellnesshotel"]
    }
  ]
}
```

---

### GET /wp-json/hotel-deals/v1/hotels/{id}

Returns a single hotel by its WordPress post ID.

```bash
curl "https://hotelaanbiedingen.com/wp-json/hotel-deals/v1/hotels/4821"
```

---

## Caching & rate limiting

| Setting | Default | Override filter |
|---------|---------|-----------------|
| Cache TTL | 5 minutes | `hotel_deals_api_cache_ttl` |
| Rate limit | 60 req/min per IP | `hotel_deals_api_rate_limit` |
| Rate window | 60 seconds | `hotel_deals_api_rate_window` |

To disable rate limiting:

```php
add_filter( 'hotel_deals_api_rate_limit', fn() => 0 );
```

---

## Extensibility (filters)

```php
// Add fields to the hotel response
add_filter( 'hotel_deals_api_hotel_item', function( array $hotel, WP_Post $post ) {
    $hotel['phone'] = get_post_meta( $post->ID, 'phone', true );
    return $hotel;
}, 10, 2 );

// Add fields to a deal response
add_filter( 'hotel_deals_api_deal_item', function( array $deal, object $row ) {
    $deal['has_pool'] = (bool) get_post_meta( $deal['hotel_id'], 'pool', true );
    return $deal;
}, 10, 2 );

// Change the deals table name
add_filter( 'hotel_deals_api_deals_table', fn() => 'ha_deals' );

// Modify the full response
add_filter( 'hotel_deals_api_deals_response', function( array $response, array $params ) {
    $response['api_version'] = '1.0';
    return $response;
}, 10, 2 );
```

---

## Use cases

**For developers**
- Integrate live Dutch hotel deals into any web application via a simple REST API.
- Build travel widgets, price trackers, or destination guides.

**For travel bloggers**
- Embed the latest deals for a specific city directly on your blog.
- Automate deal roundups without manual data entry.

## License

MIT — free to use, modify, and redistribute.

---

## Credits

Hotel deal data is provided by **[HotelAanbiedingen.com](https://hotelaanbiedingen.com)** — the Netherlands' hotel deal
