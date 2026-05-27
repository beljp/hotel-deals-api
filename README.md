# Hotel Deals API

A free, public REST API for hotel deals in the Netherlands.  
Search by city, price, star rating, or provider — no authentication required.

**Live demo →** [beljp.github.io/hotel-deals-api](https://beljp.github.io/hotel-deals-api)  
**Data source →** [HotelAanbiedingen.com](https://hotelaanbiedingen.com)

---

## Base URL

```
https://www.hotelaanbiedingen.com/wp-json/hotel-deals/v1
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/deals` | Search deals across all hotels |
| GET | `/hotels` | Browse the hotel list |
| GET | `/hotels/{id}` | Single hotel details |
| GET | `/hotels/{id}/deals` | All deals for one hotel |

---

## GET /deals

Search and filter available hotel deals.

### Query parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `city` | string | Filter by city | `Amsterdam` |
| `province` | string | Filter by province | `Noord-Holland` |
| `max_price` | number | Maximum price in EUR | `150` |
| `min_price` | number | Minimum price in EUR | `50` |
| `stars` | integer | Star rating (1–5) | `4` |
| `source` | string | Provider: `voordeeluitjes`, `hotelspecials`, `zoweg` | `voordeeluitjes` |
| `check_in_from` | date | Earliest check-in (YYYY-MM-DD) | `2026-06-01` |
| `check_in_to` | date | Latest check-in (YYYY-MM-DD) | `2026-06-30` |
| `limit` | integer | Results per page, max 50 (default: 10) | `20` |
| `page` | integer | Page number (default: 1) | `2` |

### Example requests

```bash
# Deals in Amsterdam under €150
curl "https://www.hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?city=Amsterdam&max_price=150"

# 4-star deals from Voordeeluitjes
curl "https://www.hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?stars=4&source=voordeeluitjes"

# Deals in June 2026, page 2
curl "https://www.hotelaanbiedingen.com/wp-json/hotel-deals/v1/deals?check_in_from=2026-06-01&check_in_to=2026-06-30&limit=20&page=2"
```

### Example response

```json
{
  "source": "hotelaanbiedingen.com",
  "count": 142,
  "pages": 8,
  "page": 1,
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
      "deal_url": "https://www.hotelaanbiedingen.com/ga.php?id=98234",
      "hotel_url": "https://www.hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
      "last_updated": "2026-05-27"
    }
  ]
}
```

---

## GET /hotels

Browse available hotels, optionally filtered by city, province or star rating.

### Query parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `city` | string | Filter by city |
| `province` | string | Filter by province |
| `stars` | integer | Star rating (1–5) |
| `limit` | integer | Results per page, max 50 (default: 10) |
| `page` | integer | Page number |

### Example response

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
      "image": "https://www.hotelaanbiedingen.com/wp-content/uploads/...",
      "hotel_url": "https://www.hotelaanbiedingen.com/hotel/van-der-valk-amsterdam/",
      "chains": ["Van der Valk"],
      "categories": ["Met ontbijt", "Wellnesshotel"]
    }
  ]
}
```

---

## GET /hotels/{id}/deals

Returns all current deals for a specific hotel.

```bash
curl "https://www.hotelaanbiedingen.com/wp-json/hotel-deals/v1/hotels/4821/deals"
```

Response follows the same deal format as `/deals`, grouped under the hotel.

---

## Response fields

### Deal fields

| Field | Type | Description |
|-------|------|-------------|
| `deal_id` | integer | Unique deal identifier |
| `hotel_id` | integer | Hotel identifier |
| `hotel_name` | string | Hotel name |
| `city` | string | City |
| `stars` | integer | Star rating |
| `source` | string | Provider name |
| `deal_title` | string | Deal name |
| `price` | number | Current price in EUR |
| `price_original` | number | Original price before discount |
| `discount_pct` | integer | Discount percentage |
| `currency` | string | Always `EUR` |
| `offer_nights` | integer | Number of nights included |
| `meal_type` | string | Meal plan (e.g. breakfast included) |
| `room_type` | string | Room type |
| `price_info` | string | Additional price information |
| `contents` | array | Included amenities |
| `check_in` | date | Check-in date |
| `check_out` | date | Check-out date |
| `deal_url` | string | Link to the deal (tracked redirect) |
| `hotel_url` | string | Hotel page on HotelAanbiedingen.com |
| `last_updated` | date | Date the deal was last updated |

---

## Use cases

- **Travel blogs** — embed live deals for a specific city in your articles
- **Price comparison** — pull deals filtered by star rating and budget
- **Destination guides** — show what's available in a region right now
- **Affiliate integration** — `deal_url` links are tracked and go directly to the partner booking page

---

## Limits

- Max 50 results per request (use `page` to paginate)
- Rate limited to 60 requests per minute per IP
- Responses are cached for 5 minutes

---

## License

MIT — free to use.

Data provided by **[HotelAanbiedingen.com](https://hotelaanbiedingen.com)**
