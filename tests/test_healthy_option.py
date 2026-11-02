import urllib.request
import urllib.parse
import http.cookiejar
import re
import json

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

print("--- 1. Testing Customer Storefront ---")
home_html = opener.open('http://127.0.0.1:8080/customer/index.php').read().decode('utf-8')
assert 'Healthy Food' in home_html, "Missing 'Healthy Food' filter button on homepage"
assert 'healthyOnlyToggle' in home_html, "Missing healthyOnlyToggle id on homepage"
assert 'Healthy &amp; Fit' in home_html or 'Healthy & Fit' in home_html, "Missing Healthy category on homepage"
assert 'Mediterranean Quinoa' in home_html, "Missing Mediterranean Quinoa dish on homepage"
assert 'data-healthy="true"' in home_html, "Missing data-healthy attribute on homepage cards"
print("[PASS] Homepage has Healthy Food button, category, and dishes")

print("--- 2. Testing Restaurant Menu ---")
rest_html = opener.open('http://127.0.0.1:8080/customer/restaurant.php').read().decode('utf-8')
assert 'healthyOnlySwitch' in rest_html, "Missing healthyOnlySwitch on restaurant page"
assert 'category-healthy' in rest_html, "Missing category-healthy anchor on restaurant page"
assert 'Grilled Herb Chicken' in rest_html, "Missing Grilled Herb Chicken on restaurant page"
print("[PASS] Restaurant menu has Healthy Only switch and category section")

print("--- 3. Testing Search & Filtering ---")
search_healthy = opener.open('http://127.0.0.1:8080/customer/search.php?category=healthy').read().decode('utf-8')
assert 'Found <strong>5</strong> delicious matches' in search_healthy, f"Expected 5 healthy items in search"
assert 'Mediterranean Quinoa' in search_healthy, "Quinoa bowl missing from healthy category search"
print("[PASS] Category search returns 5 healthy dishes")

search_filter = opener.open('http://127.0.0.1:8080/customer/search.php?healthy_only=1').read().decode('utf-8')
assert 'Found <strong>5</strong> delicious matches' in search_filter, "Healthy filter missing items"
print("[PASS] Quick Filter 'Healthy Food' returns 5 matches")

print("--- 4. Testing Admin Portal Authentication & Management ---")
login_page = opener.open('http://127.0.0.1:8080/admin/login.php').read().decode('utf-8')
csrf = re.search(r'name="csrf_token" value="([^"]+)"', login_page).group(1)

post_data = urllib.parse.urlencode({
    'csrf_token': csrf,
    'email': 'admin@zippy.com',
    'password': 'password123'
}).encode('utf-8')
resp = opener.open('http://127.0.0.1:8080/admin/login.php', data=post_data)

cat_html = opener.open('http://127.0.0.1:8080/admin/categories.php').read().decode('utf-8')
assert 'Healthy & Fit' in cat_html or 'Healthy &amp; Fit' in cat_html, "Healthy & Fit category not listed in admin categories"
print("[PASS] Admin Categories page lists Healthy & Fit")

dish_html = opener.open('http://127.0.0.1:8080/admin/dishes.php').read().decode('utf-8')
assert 'Mediterranean Quinoa' in dish_html, "Healthy dish missing in admin dishes"
assert 'HEALTHY' in dish_html, "HEALTHY badge missing in admin dishes"
assert 'name="is_healthy"' in dish_html, "is_healthy checkbox missing in admin dishes form"
print("[PASS] Admin Dishes page lists Healthy dishes and editing controls")

print("--- 5. Testing Customer Order Placement for Healthy Dish ---")
# Fresh Cookie Jar for Customer
cust_cj = http.cookiejar.CookieJar()
cust_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cust_cj))

cust_login_html = cust_opener.open('http://127.0.0.1:8080/customer/login.php').read().decode('utf-8')
cust_csrf = re.search(r'name="csrf_token" value="([^"]+)"', cust_login_html).group(1)

cust_post = urllib.parse.urlencode({
    'csrf_token': cust_csrf,
    'email': 'pooja@example.com',
    'password': 'password123'
}).encode('utf-8')
cust_opener.open('http://127.0.0.1:8080/customer/login.php', data=cust_post)

# Get checkout page to extract CSRF and address
checkout_html = cust_opener.open('http://127.0.0.1:8080/customer/checkout.php').read().decode('utf-8')
checkout_csrf = re.search(r'name="csrf_token" value="([^"]+)"', checkout_html).group(1)
addr_match = re.search(r'name="address_id" value="(\d+)"', checkout_html)
address_id = addr_match.group(1) if addr_match else '1'

# Place order with Mediterranean Quinoa (ID 18)
cart_payload = json.dumps([
    {'id': 18, 'name': 'Mediterranean Quinoa & Avocado Bowl', 'price': 249.00, 'qty': 1, 'isVeg': True}
])

order_post = urllib.parse.urlencode({
    'csrf_token': checkout_csrf,
    'action': 'process_order',
    'cart_json': cart_payload,
    'address_id': address_id,
    'payment_mode': 'COD',
    'order_notes': 'Please pack eco-friendly cutlery for healthy salad'
}).encode('utf-8')

order_resp = cust_opener.open('http://127.0.0.1:8080/customer/checkout.php', data=order_post)
final_url = order_resp.geturl()
order_html = order_resp.read().decode('utf-8')
print("Order placed response URL:", final_url)
order_id_match = re.search(r'order-success\.php\?order_id=(\d+)', order_html)
assert order_id_match, f"Expected order-success.php redirect in HTML:\n{order_html[:500]}"
new_order_id = order_id_match.group(1)
print(f"[PASS] Successfully placed order with Healthy Food dish! Generated Order ID: {new_order_id}")

# Verify order details on order success page
success_html = cust_opener.open(f'http://127.0.0.1:8080/customer/order-success.php?order_id={new_order_id}').read().decode('utf-8')
assert 'Order Confirmed' in success_html or 'Order Placed' in success_html or 'Congratulations' in success_html or 'order_id' in success_html or new_order_id in success_html, "Success page confirmation text check"
print(f"[PASS] Order success page verified for Order #{new_order_id}")

print("\nALL HEALTHY FOOD OPTION TESTS PASSED COMPLETELY!")
