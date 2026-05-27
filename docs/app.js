/**
 * Hotel Deals API – GitHub Pages demo
 *
 * Change API_BASE to your production URL before publishing.
 */

const API_BASE = 'https://hotelaanbiedingen.com/wp-json/hotel-deals/v1';

// Example hotel IDs for the "browse by hotel" panel (replace with real IDs from your site).
// GET /wp-json/hotel-deals/v1/hotels?limit=5  to find real IDs.
const EXAMPLE_HOTEL_IDS = [];

// ── DOM references ────────────────────────────────────────────────────────────
const form        = document.getElementById('search-form');
const btnReset    = document.getElementById('btn-reset');
const grid        = document.getElementById('results-grid');
const statusMsg   = document.getElementById('status-message');
const urlDisplay  = document.getElementById('api-url-display');

// ── Live URL preview ──────────────────────────────────────────────────────────
function updateUrlPreview() {
  const url = buildUrl();
  urlDisplay.textContent = url.replace('https://', '');
}

['city', 'max_price', 'stars', 'source'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', updateUrlPreview);
});

// Run once on load
updateUrlPreview();

// ── Form submit ───────────────────────────────────────────────────────────────
form.addEventListener('submit', async e => {
  e.preventDefault();
  await fetchDeals();
});

btnReset.addEventListener('click', () => {
  form.reset();
  grid.innerHTML = '';
  setStatus('', '');
  updateUrlPreview();
});

// ── URL builder ───────────────────────────────────────────────────────────────
function buildUrl() {
  const url    = new URL(`${API_BASE}/deals`);
  const city   = document.getElementById('city').value.trim();
  const price  = document.getElementById('max_price').value.trim();
  const stars  = document.getElementById('stars').value;
  const source = document.getElementById('source').value;

  if (city)   url.searchParams.set('city',      city);
  if (price)  url.searchParams.set('max_price', price);
  if (stars)  url.searchParams.set('stars',     stars);
  if (source) url.searchParams.set('source',    source);

  url.searchParams.set('limit', '12');
  return url.toString();
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchDeals() {
  setStatus('Searching for deals…', 'loading');
  grid.innerHTML = '';

  const url = buildUrl();
  urlDisplay.textContent = url.replace('https://', '');

  try {
    const res = await fetch(url);

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `HTTP ${res.status}`);
    }

    const data = await res.json();

    if (!data.items || data.items.length === 0) {
      setStatus('No deals found for these filters. Try adjusting your search.', 'info');
      return;
    }

    setStatus('', '');
    renderDeals(data.items, data.count);
  } catch (err) {
    setStatus(`Error: ${err.message}`, 'error');
  }
}

// ── Render ────────────────────────────────────────────────────────────────────
function renderDeals(items, total) {
  const fragment = document.createDocumentFragment();

  items.forEach(deal => {
    const card = buildCard(deal);
    fragment.appendChild(card);
  });

  grid.appendChild(fragment);
  setStatus(`Showing ${items.length} of ${total} deal${total !== 1 ? 's' : ''}`, 'info');
}

function buildCard(deal) {
  const article = document.createElement('article');
  article.className = 'deal-card';

  const stars      = deal.stars ? '★'.repeat(deal.stars) : '';
  const discount   = deal.discount_pct ? `−${deal.discount_pct}%` : '';
  const nights     = deal.offer_nights ? `${deal.offer_nights} night${deal.offer_nights > 1 ? 's' : ''}` : '';
  const meal       = deal.meal_type || '';
  const priceOrig  = deal.price_original && deal.price_original > deal.price
                     ? `€${deal.price_original.toFixed(0)}`
                     : '';

  const cityProv   = [deal.city, deal.province].filter(Boolean).join(', ');
  const dates      = deal.check_in ? `${deal.check_in}` + (deal.check_out ? ` → ${deal.check_out}` : '') : '';
  const sourceLabel = { voordeeluitjes: 'Voordeeluitjes', hotelspecials: 'Hotelspecials', zoweg: 'Zoweg' }[deal.source] || deal.source || '';

  article.innerHTML = `
    <div class="deal-card-header">
      <div class="hotel-name">${escape(deal.hotel_name)}</div>
      <div class="hotel-meta">
        ${stars ? `<span class="stars">${stars}</span>` : ''}
        ${cityProv ? `<span>${escape(cityProv)}</span>` : ''}
        ${dates ? `<span>${escape(dates)}</span>` : ''}
      </div>
    </div>
    <div class="deal-card-body">
      <div class="deal-title">${escape(deal.deal_title || 'Hotel deal')}</div>
      <div class="deal-details">
        ${sourceLabel ? `<span class="tag source">${escape(sourceLabel)}</span>` : ''}
        ${nights      ? `<span class="tag nights">${escape(nights)}</span>` : ''}
        ${meal        ? `<span class="tag meal">${escape(meal)}</span>` : ''}
      </div>
      <div class="deal-price-row">
        ${deal.price != null
          ? `<span class="price-current">€${deal.price.toFixed(0)}</span>`
          : '<span class="price-current">–</span>'}
        ${priceOrig ? `<span class="price-original">${priceOrig}</span>` : ''}
        ${discount  ? `<span class="discount-badge">${discount}</span>` : ''}
      </div>
    </div>
    <div class="deal-card-footer">
      <a class="btn-deal"
         href="${sanitizeUrl(deal.offer_link || deal.hotel_url)}"
         target="_blank"
         rel="noopener noreferrer sponsored">
        View deal →
      </a>
    </div>`;

  return article;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function setStatus(msg, type) {
  statusMsg.textContent = msg;
  statusMsg.className   = `status-message ${type}`;
}

function escape(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function sanitizeUrl(url) {
  if (!url) return '#';
  try {
    const parsed = new URL(url);
    if (parsed.protocol === 'https:' || parsed.protocol === 'http:') return url;
  } catch {}
  return '#';
}
