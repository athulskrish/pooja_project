"""
Unit & Integration Test for Customer Storefront (customer/index.php) UI/UX Enhancements
Verifies:
1. Apriori algorithm recommendations section ("Frequently Ordered Together")
2. Absence of fake multi-restaurant chain cards (Meghana, Empire, Truffles, A2B)
3. Hero quick search bar & trending tag buttons
4. In-place category filters with data-cat-slug
5. Client-side sort dropdown & reset filters affordances
6. One-click copy coupon badges
7. Active order live tracking banner for logged-in customer
8. Clean customer-facing marketing banner (no technical database locking jargon)
"""

import urllib.request
import urllib.parse
import http.cookiejar
import re

BASE_URL = 'http://127.0.0.1:8080'

print("=== 1. Verifying Anonymous Storefront UI/UX ===")
req = urllib.request.urlopen(f'{BASE_URL}/customer/index.php')
html = req.read().decode('utf-8')

# 1. Apriori Recommendations
assert 'Frequently Ordered Together' in html, "Missing Apriori recommendations title"
assert 'APRIORI ALGORITHM AI' in html, "Missing Apriori algorithm badge"
assert 'High Pairing Match' in html, "Missing Apriori match badge"
print("[PASS] Apriori algorithm recommendations section is present")

# 2. Fake multi-restaurant chains removed
assert 'Top Restaurant Chains in Bangalore' not in html, "Fake restaurant chains section should be removed"
assert 'Hotel Empire' not in html, "Hardcoded fake chain 'Hotel Empire' should be removed"
assert 'Truffles Cafe' not in html, "Hardcoded fake chain 'Truffles' should be removed"
assert 'Adyar Ananda Bhavan' not in html, "Hardcoded fake chain 'A2B' should be removed"
print("[PASS] Fake multi-restaurant chains cleanly removed")

# 3. Hero Quick Search Bar & Trending Tags
assert 'storefrontQuickSearch' in html, "Missing storefrontQuickSearch input"
assert 'Trending:' in html, "Missing trending tags label"
assert 'quick-tag-btn' in html, "Missing quick tag buttons"
print("[PASS] Hero Quick Search bar & trending tags present")

# 4. In-place Category Filters
assert 'data-cat-slug="all"' in html, "Missing data-cat-slug='all' on All Dishes pill"
assert 'filterByCategory' in html, "Missing filterByCategory call on category pills"
print("[PASS] Category pills configured for in-place filtering")

# 5. Client-side Sort Dropdown & Toolbar
assert 'storefrontSortBtn' in html, "Missing storefrontSortBtn dropdown"
assert 'sortDishes' in html, "Missing sortDishes function call"
assert 'resetStorefrontFiltersBtn' in html, "Missing resetStorefrontFiltersBtn"
print("[PASS] Storefront sort dropdown and filter reset toolbar present")

# 6. One-click Copy Coupon Badges
assert 'copyCouponCode(\'LUSHFOREST\'' in html, "Missing copyCouponCode for LUSHFOREST"
assert 'copyCouponCode(\'ZIPPY50\'' in html, "Missing copyCouponCode for ZIPPY50"
assert 'copy-coupon-badge' in html, "Missing copy-coupon-badge class"
print("[PASS] Interactive copy-to-clipboard coupon badges present")

# 7. Customer Marketing Copy Refined
assert '3NF Normalized' not in html, "Technical database jargon '3NF Normalized' should not be in customer banner"
assert 'row-level pessimistic locking' not in html, "Technical jargon 'pessimistic locking' should not be in customer banner"
assert 'Fresh Meals, Zero-Lag Delivery' in html, "Refined customer-friendly headline missing"
assert '100% FSSAI Certified Kitchen' in html, "FSSAI trust badge missing"
print("[PASS] Customer marketing copy verified without internal database jargon")

print("\n=== 2. Verifying Authenticated Customer Live Order Tracker ===")
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# Login as customer
login_html = opener.open(f'{BASE_URL}/customer/login.php').read().decode('utf-8')
csrf_match = re.search(r'name="csrf_token" value="([^"]+)"', login_html)
csrf = csrf_match.group(1) if csrf_match else ''

login_data = urllib.parse.urlencode({
    'csrf_token': csrf,
    'email': 'athul@example.com',
    'password': 'password123'
}).encode('utf-8')
opener.open(f'{BASE_URL}/customer/login.php', data=login_data)

auth_home = opener.open(f'{BASE_URL}/customer/index.php').read().decode('utf-8')
assert 'LIVE ORDER IN PROGRESS' in auth_home, "Missing LIVE ORDER IN PROGRESS banner for customer with active order"
assert 'activeOrderBanner' in auth_home, "Missing activeOrderBanner container"
assert 'Track Live Order' in auth_home, "Missing 'Track Live Order' button"
print("[PASS] Authenticated customer with active order sees live order status banner with direct tracking button")

print("\nALL CUSTOMER STOREFRONT UI/UX RECOMMENDATIONS VERIFIED AND PASSED!")
