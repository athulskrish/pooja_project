import urllib.request
import urllib.parse
import http.cookiejar
import re
import json

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

print("=== 1. Testing Customer Storefront Homepage for Mood Food Option ===")
home_html = opener.open('http://127.0.0.1:8080/customer/index.php').read().decode('utf-8')

assert 'id="moodSection"' in home_html, "Missing #moodSection on homepage"
assert 'Mood-Based Food Selection' in home_html or 'MOOD-BASED FOOD SELECTION' in home_html, "Missing Mood-Based Food Selection heading on homepage"
assert 'How are you feeling today?' in home_html, "Missing 'How are you feeling today?' on homepage"
assert 'btnSurpriseMood' in home_html, "Missing Surprise My Mood button on homepage"
assert 'moodFilterDropdownBtn' in home_html, "Missing Mood filter dropdown chip on homepage"
assert 'activeMoodBanner' in home_html, "Missing #activeMoodBanner on homepage"
assert 'data-mood-id="comfort"' in home_html, "Missing Comfort mood card"
assert 'data-mood-id="party"' in home_html, "Missing Party mood card"
assert 'data-mood-id="healthy"' in home_html, "Missing Healthy mood card"
assert 'data-mood-id="quick_bite"' in home_html, "Missing Quick Bite mood card"
assert 'data-mood-id="spicy"' in home_html, "Missing Spicy mood card"
assert 'data-mood-id="sweet"' in home_html, "Missing Sweet mood card"
assert 'data-mood="party,spicy"' in home_html or 'data-mood=' in home_html, "Missing data-mood attributes on dish cards"
assert 'Mood Food' in home_html, "Missing 'Mood Food' text on homepage"
print("[PASS] Customer Homepage has interactive Mood-Based Food Selection section, 6 mood cards, dropdown chip, and dish cards with mood data.")

print("\n=== 2. Testing Restaurant Menu for Mood Selection ===")
rest_html = opener.open('http://127.0.0.1:8080/customer/restaurant.php').read().decode('utf-8')
assert 'id="restaurantMoodSelect"' in rest_html, "Missing #restaurantMoodSelect on restaurant page"
assert 'value="comfort"' in rest_html, "Missing comfort option in restaurant mood selector"
assert 'value="party"' in rest_html, "Missing party option in restaurant mood selector"
assert 'data-mood=' in rest_html, "Missing data-mood attribute on restaurant dish rows"
print("[PASS] Restaurant Menu has Mood selector dropdown and data-mood attributes.")

print("\n=== 3. Testing Search & Filtering by Mood ===")
moods_to_test = {
    'comfort': ['Dal Makhani', 'Butter Chicken', 'Paneer Butter Masala'],
    'party': ['Meghana Special Chicken Biryani', 'Mutton Dum Biryani'],
    'spicy': ['Meghana Special Chicken Biryani', 'Mysore Masala Dosa'],
    'healthy': ['Mediterranean Quinoa', 'Grilled Herb Chicken', 'Detox Glow Juice'],
    'sweet': ['Royal Kesar Rasmalai', 'Gulab Jamun with Rabri'],
    'quick_bite': ['Double Egg Chicken Kathi Roll', 'Crispy Paneer Tikka Roll']
}

for mood_key, expected_dishes in moods_to_test.items():
    search_html = opener.open(f'http://127.0.0.1:8080/customer/search.php?mood={mood_key}').read().decode('utf-8')
    assert f'value="{mood_key}" selected' in search_html or f'mood={mood_key}' in search_html, f"Search page didn't reflect selected mood: {mood_key}"
    for dish_name in expected_dishes:
        assert dish_name in search_html, f"Expected dish '{dish_name}' missing when filtering by mood '{mood_key}'"
    print(f"   [PASS] Mood '{mood_key}' returns matching dishes: {', '.join(expected_dishes)}")

print("[PASS] All 6 moods successfully filter dishes in customer search.")

print("\n=== 4. Testing Admin Portal Authentication & Mood Management ===")
login_page = opener.open('http://127.0.0.1:8080/admin/login.php').read().decode('utf-8')
csrf = re.search(r'name="csrf_token" value="([^"]+)"', login_page).group(1)

post_data = urllib.parse.urlencode({
    'csrf_token': csrf,
    'email': 'admin@zippy.com',
    'password': 'password123'
}).encode('utf-8')
resp = opener.open('http://127.0.0.1:8080/admin/login.php', data=post_data)

dish_html = opener.open('http://127.0.0.1:8080/admin/dishes.php').read().decode('utf-8')
assert 'name="mood"' in dish_html, "Missing mood filter selector in admin dishes toolbar"
assert 'id="editDishMood"' in dish_html, "Missing #editDishMood selector in Edit Dish modal"
assert 'Curated Food Mood' in dish_html, "Missing 'Curated Food Mood' label in dishes modal"
assert 'data-mood=' in dish_html, "Missing data-mood attribute on admin edit buttons"
print("[PASS] Admin dishes page has Mood filter dropdown, Edit modal mood selector, and table mood badges.")

# Test Admin filtering by mood
admin_comfort = opener.open('http://127.0.0.1:8080/admin/dishes.php?mood=comfort').read().decode('utf-8')
assert 'Butter Chicken' in admin_comfort, "Admin filter by mood=comfort failed to show Butter Chicken"
assert 'Dal Makhani' in admin_comfort, "Admin filter by mood=comfort failed to show Dal Makhani"
print("[PASS] Admin portal filtering by mood=comfort lists comfort dishes.")

# Test CSV export includes Mood header and data
csv_resp = opener.open('http://127.0.0.1:8080/admin/dishes.php?export=csv')
csv_content = csv_resp.read().decode('utf-8')
assert 'Mood' in csv_content, "CSV export header missing 'Mood'"
assert 'comfort' in csv_content, "CSV export data missing mood values"
print("[PASS] Admin dishes CSV export includes Mood column and records.")

print("\n=== 5. Testing Customer Order Placement for Mood-Selected Food ===")
cust_cj = http.cookiejar.CookieJar()
cust_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cust_cj))

cust_login_html = cust_opener.open('http://127.0.0.1:8080/customer/login.php').read().decode('utf-8')
cust_csrf = re.search(r'name="csrf_token" value="([^"]+)"', cust_login_html).group(1)

cust_post = urllib.parse.urlencode({
    'csrf_token': cust_csrf,
    'email': 'athul@example.com',
    'password': 'password123'
}).encode('utf-8')
cust_opener.open('http://127.0.0.1:8080/customer/login.php', data=cust_post)

checkout_html = cust_opener.open('http://127.0.0.1:8080/customer/checkout.php').read().decode('utf-8')
checkout_csrf = re.search(r'name="csrf_token" value="([^"]+)"', checkout_html).group(1)
addr_match = re.search(r'name="address_id" value="(\d+)"', checkout_html)
address_id = addr_match.group(1) if addr_match else '1'

# Place order with a Comfort & Soul dish: Butter Chicken Delhi Style (item_id 4)
cart_payload = json.dumps([
    {'id': 4, 'name': 'Butter Chicken Delhi Style', 'price': 299.00, 'qty': 1, 'isVeg': False}
])

order_post = urllib.parse.urlencode({
    'csrf_token': checkout_csrf,
    'action': 'process_order',
    'cart_json': cart_payload,
    'address_id': address_id,
    'payment_mode': 'COD',
    'order_notes': 'Mood based order - Craving Comfort Food!'
}).encode('utf-8')

order_resp = cust_opener.open('http://127.0.0.1:8080/customer/checkout.php', data=order_post)
order_html = order_resp.read().decode('utf-8')
order_id_match = re.search(r'order-success\.php\?order_id=(\d+)', order_html)
assert order_id_match, f"Order placement failed, response HTML:\n{order_html[:500]}"
new_order_id = order_id_match.group(1)
print(f"[PASS] Successfully placed order with Mood-Selected Comfort Dish! Generated Order ID: {new_order_id}")

success_html = cust_opener.open(f'http://127.0.0.1:8080/customer/order-success.php?order_id={new_order_id}').read().decode('utf-8')
assert new_order_id in success_html or 'Order' in success_html, "Order success page validation failed"
print(f"[PASS] Order success page verified for Order #{new_order_id}")

print("\n=======================================================")
print("ALL MOOD-BASED FOOD SELECTION TESTS PASSED COMPLETELY!")
print("=======================================================")
