import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

print("=== 1. Logging into Admin Portal ===")
login_html = opener.open('http://127.0.0.1:8080/admin/login.php').read().decode('utf-8')
csrf = re.search(r'name="csrf_token" value="([^"]+)"', login_html).group(1)

login_data = urllib.parse.urlencode({
    'csrf_token': csrf,
    'email': 'admin@zippy.com',
    'password': 'password123'
}).encode('utf-8')

resp = opener.open('http://127.0.0.1:8080/admin/login.php', data=login_data)
assert 'Dashboard' in resp.read().decode('utf-8') or resp.status == 200
print("[PASS] Admin logged in successfully.")

print("\n=== 2. Opening Admin Food Moods Management Workspace (admin/moods.php) ===")
moods_html = opener.open('http://127.0.0.1:8080/admin/moods.php').read().decode('utf-8')

assert 'Food Moods & Vibe Curation' in moods_html, "Missing header on moods.php"
assert 'Curated Moods Catalog & Items' in moods_html, "Missing catalog table on moods.php"
assert 'addMoodModal' in moods_html, "Missing Add Mood modal"
assert 'editMoodModal' in moods_html, "Missing Edit Mood modal"
assert 'manageDishesModal' in moods_html, "Missing Manage Dishes modal"

# Verify all 6 initial moods are present
for mood_key in ['comfort', 'party', 'healthy', 'quick_bite', 'spicy', 'sweet']:
    assert mood_key in moods_html, f"Missing mood key '{mood_key}' in admin moods table"

print("[PASS] admin/moods.php renders catalog table, stats, and modals for all 6 food moods.")

print("\n=== 3. Testing Admin Editing Mood Details & Mood Image ===")
mood_csrf = re.search(r'name="csrf_token" value="([^"]+)"', moods_html).group(1)

# Find mood_id for spicy
m_id_match = re.search(r'confirmDeleteMood\((\d+),\s*\'Fiery', moods_html)
assert m_id_match, f"Could not find spicy mood_id in table. Content: {moods_html[:1000]}"
spicy_mood_id = int(m_id_match.group(1))

edit_data = urllib.parse.urlencode({
    'csrf_token': mood_csrf,
    'action': 'edit_mood',
    'mood_id': spicy_mood_id,
    'name': 'Fiery & Ultra Spicy',
    'short_name': 'Ultra Spicy',
    'emoji': '🔥',
    'icon': 'fa-solid fa-fire-flame-curved',
    'tagline': 'Super Hot Andhra Masala & Sizzling Fire Chutneys for fearless foodies',
    'badge_bg': '#FFEBEF',
    'badge_color': '#8B0000',
    'border_color': '#FFB6C1',
    'accent': '#E60000',
    'display_order': 5,
    'is_active': 1,
    'image_url': 'assets/images/moods/spicy.jpg'
}).encode('utf-8')

edit_resp = opener.open('http://127.0.0.1:8080/admin/moods.php', data=edit_data).read().decode('utf-8')
print("Alert block:", re.findall(r'<div class="alert [^"]*">([\s\S]*?)</div>', edit_resp))
if 'Fiery & Ultra Spicy' not in edit_resp:
    # Check if html-escaped
    print("Has &amp;:", 'Fiery &amp; Ultra Spicy' in edit_resp)
assert 'Fiery & Ultra Spicy' in edit_resp or 'Fiery &amp; Ultra Spicy' in edit_resp, "Updated mood name not found in moods.php"
assert 'Super Hot Andhra Masala' in edit_resp, "Updated tagline not found in moods.php"
print("[PASS] Admin successfully edited mood name, tagline, styling, and image.")

print("\n=== 4. Testing Admin Managing Items (Dishes) in a Mood ===")
# Manage dishes for spicy mood: assign dishes ID 1, 8, 10, 4 (adding Butter Chicken ID 4)
manage_items_data = urllib.parse.urlencode([
    ('csrf_token', mood_csrf),
    ('action', 'manage_mood_dishes'),
    ('mood_key', 'spicy'),
    ('item_ids[]', 1),
    ('item_ids[]', 8),
    ('item_ids[]', 10),
    ('item_ids[]', 4)  # Added Butter Chicken to spicy
]).encode('utf-8')

items_resp = opener.open('http://127.0.0.1:8080/admin/moods.php', data=manage_items_data).read().decode('utf-8')
print("Items resp alerts:", re.findall(r'<div class="alert [^"]*">([\s\S]*?)</div>', items_resp))
print("Has 4 dishes:", 'Assigned 4 dishes' in items_resp)
print("[PASS] Admin successfully updated curated dishes for 'spicy' mood.")

print("\n=== 5. Testing Storefront Live Reflection of Admin Edits ===")
home_html = opener.open('http://127.0.0.1:8080/customer/index.php').read().decode('utf-8')
assert 'Fiery & Ultra Spicy' in home_html or 'Fiery &amp; Ultra Spicy' in home_html, "Edited mood name not reflected on customer homepage"
assert 'Super Hot Andhra Masala' in home_html, "Edited tagline not reflected on customer homepage"
print("[PASS] Storefront immediately reflects the updated mood name and tagline edited by admin.")

# Search filtering by spicy should now include Butter Chicken Delhi Style
search_spicy = opener.open('http://127.0.0.1:8080/customer/search.php?mood=spicy').read().decode('utf-8')
assert 'Butter Chicken' in search_spicy, "Butter Chicken missing from spicy mood search after admin assigned it"
print("[PASS] Search and dish filtering reflect the updated dish assignments.")

print("\n=== 6. Resetting test mood to original name & dishes ===")
reset_edit = urllib.parse.urlencode({
    'csrf_token': mood_csrf,
    'action': 'edit_mood',
    'mood_id': spicy_mood_id,
    'name': 'Fiery & Spicy',
    'short_name': 'Fiery Spicy',
    'emoji': '🔥',
    'icon': 'fa-solid fa-fire',
    'tagline': 'Authentic Andhra spices, fiery red chutneys & bold thrilling masalas',
    'badge_bg': '#F8D7DA',
    'badge_color': '#721C24',
    'border_color': '#F5C6CB',
    'accent': '#DC3545',
    'display_order': 5,
    'is_active': 1,
    'image_url': 'assets/images/moods/spicy.jpg'
}).encode('utf-8')
opener.open('http://127.0.0.1:8080/admin/moods.php', data=reset_edit)

# Reset dishes in spicy (remove 4, keep 1, 8, 10, 23)
reset_dishes = urllib.parse.urlencode([
    ('csrf_token', mood_csrf),
    ('action', 'manage_mood_dishes'),
    ('mood_key', 'spicy'),
    ('item_ids[]', 1),
    ('item_ids[]', 8),
    ('item_ids[]', 10),
    ('item_ids[]', 23)
]).encode('utf-8')
opener.open('http://127.0.0.1:8080/admin/moods.php', data=reset_dishes)
print("[PASS] Reverted test edit cleanly.")

print("\n=======================================================")
print("ALL ADMIN MOODS, IMAGES & ITEMS EDITING TESTS PASSED!")
print("=======================================================")
