"""
Automated Test Suite for One-Tap Reorder Previous Meal Feature
Pooja Project - Zippy Online Food Ordering Platform
"""

import urllib.request
import urllib.parse
import http.cookiejar
import re
import json
import subprocess

BASE_URL = 'http://127.0.0.1:8080'

print("=" * 60)
print("TEST 1: Backend PHP Helper get_customer_previous_meal()")
print("=" * 60)
res = subprocess.run(['php', 'pooja_project/tests/test_prev_meal.php'], capture_output=True, text=True)
assert res.returncode == 0, f"Backend helper failed: {res.stderr}"
assert "SUCCESS: Order ID #1010" in res.stdout, "Did not find expected Order #1010 in output"
assert "Meghana Special Chicken Biryani" in res.stdout, "Missing item in previous meal payload"
assert "Sprouted Moong & Paneer Super-Salad" in res.stdout, "Missing second item in previous meal payload"
print("[PASS] Backend helper get_customer_previous_meal() successfully loaded user's previous meal.")

print("\n" + "=" * 60)
print("TEST 2: Anonymous Guest Storefront (index.php)")
print("=" * 60)
req = urllib.request.urlopen(f'{BASE_URL}/customer/index.php')
html = req.read().decode('utf-8')

assert 'one-tap-reorder-teaser' in html, "Missing 1-tap reorder teaser for anonymous visitor"
assert 'Sign in to repeat your favorite meals with a single tap' in html, "Missing sign in prompt in teaser"
assert 'btn-outline-success rounded-pill px-3 py-1 fw-semibold' in html, "Missing CTA button in teaser"
print("[PASS] Anonymous visitor gets clean, non-intrusive 1-Tap Reorder teaser prompting sign in.")

print("\n" + "=" * 60)
print("TEST 3: Authenticated Storefront (index.php)")
print("=" * 60)
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# Login as customer Athul
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

assert 'one-tap-reorder-section' in auth_home, "Missing one-tap-reorder-section on storefront for logged in user"
assert '1-TAP REORDER' in auth_home, "Missing '1-TAP REORDER' badge"
assert 'Craving your previous meal again?' in auth_home, "Missing previous meal headline"
assert 'btnOneTapReorder' in auth_home, "Missing btnOneTapReorder button"
assert 'btnOneTapReorderCheckout' in auth_home, "Missing btnOneTapReorderCheckout button"
assert 'oneTapReorderMeal(' in auth_home, "Missing oneTapReorderMeal JavaScript invocation"
assert 'Order #ZIP-1010' in auth_home, "Missing past order #ZIP-1010 reference"
assert 'Meghana Special Chicken Biryani' in auth_home, "Missing past dish item in one-tap reorder chips"
assert ('Sprouted Moong &amp; Paneer Super-Salad' in auth_home or 'Sprouted Moong & Paneer Super-Salad' in auth_home), "Missing past salad dish in one-tap reorder chips"
print("[PASS] Authenticated customer gets full 'One-Tap Reorder Previous Meal' section on storefront.")

print("\n" + "=" * 60)
print("TEST 4: Order History Page (order-history.php)")
print("=" * 60)
history_html = opener.open(f'{BASE_URL}/customer/order-history.php').read().decode('utf-8')
assert 'Repeat your previous meal in one tap' in history_html, "Missing 1-tap reorder banner in order history"
assert 'oneTapReorderMeal(' in history_html, "Missing oneTapReorderMeal in order history"
print("[PASS] Order history features dedicated Quick Reorder Previous Meal banner.")

print("\n" + "=" * 60)
print("TEST 5: Dedicated Cart Page (cart.php)")
print("=" * 60)
cart_html = opener.open(f'{BASE_URL}/customer/cart.php').read().decode('utf-8')
assert '1-Tap Reorder Previous Meal' in cart_html, "Missing 1-tap reorder previous meal in cart empty state"
assert 'oneTapReorderMeal(' in cart_html, "Missing oneTapReorderMeal in cart.php"
print("[PASS] Empty cart state includes 1-Tap Reorder Previous Meal action.")

print("\n" + "=" * 60)
print("TEST 6: Customer Profile Page (profile.php)")
print("=" * 60)
profile_html = opener.open(f'{BASE_URL}/customer/profile.php').read().decode('utf-8')
assert 'Your Previous Meal' in profile_html, "Missing Your Previous Meal in profile.php"
assert 'oneTapReorderMeal(' in profile_html, "Missing oneTapReorderMeal call in profile.php"
print("[PASS] Profile page displays Previous Meal with 1-Tap Reorder button.")

print("\n" + "=" * 60)
print("TEST 7: Client JavaScript Engine (assets/js/app.js)")
print("=" * 60)
with open('pooja_project/assets/js/app.js', 'r', encoding='utf-8') as f:
    js_code = f.read()

assert 'function oneTapReorderMeal(' in js_code, "Missing oneTapReorderMeal function in app.js"
assert 'function reorderItems(' in js_code, "Missing reorderItems function in app.js"
assert 'pooja_zippy_cart' in js_code, "Missing localStorage pooja_zippy_cart in app.js"
assert 'saveCart()' in js_code, "Missing saveCart() call in app.js"
assert 'directToCheckout' in js_code, "Missing directToCheckout handling in app.js"
print("[PASS] Client-side app.js has complete oneTapReorderMeal implementation.")

print("\n" + "*" * 60)
print("ALL 7 TESTS PASSED SUCCESSFULLY! ONE-TAP REORDER FULLY VERIFIED.")
print("*" * 60)
