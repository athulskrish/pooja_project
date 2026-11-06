"""
End-to-End Verification Test for Dish-Level Feedback:
1. Customer submits dish-level feedback (Appearance, Taste, Portion Size, Value) via API.
2. API returns aggregated dimension scores and individual feedback breakdowns.
3. Customer submits order review with per-dish Appearance, Taste, Portion Size, Value ratings.
4. Admin views dish-level feedback KPIs (Taste, Appearance, Portion Size, Value) and table breakdown.
5. Admin inspects Dish Feedback HUD modal.
6. Admin exports Dish Feedback to CSV with 4 dimension columns.
7. Admin Dishes catalog displays dimension badges.
"""

import urllib.request
import urllib.parse
import json
import http.cookiejar
import re
import sys
import unittest

BASE_URL = "http://127.0.0.1:8080"

def create_session():
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    return opener, cj

def get_csrf(html):
    m = re.search(r'name="csrf_token" value="([^"]+)"', html)
    if m:
        return m.group(1)
    m = re.search(r"csrf_token['\"]\s*,\s*['\"]([^'\"]+)['\"]", html)
    if m:
        return m.group(1)
    return "dummy_token"

class TestDishLevelFeedback(unittest.TestCase):

    def setUp(self):
        self.opener, self.cj = create_session()

    def login_admin(self):
        resp = self.opener.open(f"{BASE_URL}/admin/login.php")
        csrf = get_csrf(resp.read().decode('utf-8'))
        data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'admin@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/admin/login.php", data=data)
        self.assertIn("admin", resp.geturl())

    def login_customer(self):
        resp = self.opener.open(f"{BASE_URL}/customer/login.php")
        csrf = get_csrf(resp.read().decode('utf-8'))
        data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'athul@example.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/customer/login.php", data=data)
        self.assertTrue("customer" in resp.geturl() or "index.php" in resp.geturl())

    def test_01_api_submit_and_get_dimensions(self):
        print("\n--- TEST 1: Dish-Level Feedback API Submission & Retrieval ---")
        dish_id = 1 # Meghana Biryani

        # Submit 4-dimension feedback
        post_data = urllib.parse.urlencode({
            'action': 'submit_review',
            'dish_id': dish_id,
            'appearance_rating': 5,
            'taste_rating': 4,
            'portion_rating': 5,
            'value_rating': 4,
            'review_text': 'Great aroma and presentation, succulent chicken and very generous portions!',
            'tags': 'Generous Portion,Authentic Andhra Spice'
        }).encode('utf-8')

        req = urllib.request.Request(f"{BASE_URL}/api/dish-reviews.php", data=post_data)
        resp = self.opener.open(req)
        res_json = json.loads(resp.read().decode('utf-8'))

        print("  API Response on Submit: success=", res_json.get('success'), "dimensions=", res_json.get('dimensions'))
        self.assertTrue(res_json.get('success'), "API submission must be successful")
        self.assertIn('dimensions', res_json, "Response must include dimensions object")
        self.assertEqual(res_json['dimensions']['appearance'], 5)
        self.assertEqual(res_json['dimensions']['taste'], 4)
        self.assertEqual(res_json['dimensions']['portion'], 5)
        self.assertEqual(res_json['dimensions']['value'], 4)

        # GET dish reviews and check dimensions summary
        get_resp = self.opener.open(f"{BASE_URL}/api/dish-reviews.php?dish_id={dish_id}")
        get_data = json.loads(get_resp.read().decode('utf-8'))
        print("  API Response on GET:", get_data.get('dimensions'))
        self.assertTrue(get_data.get('success'))
        self.assertIn('appearance', get_data['dimensions'])
        self.assertIn('taste', get_data['dimensions'])
        self.assertIn('portion', get_data['dimensions'])
        self.assertIn('value', get_data['dimensions'])

        # Check that individual reviews in list carry the 4 criteria
        first_rev = get_data['reviews'][0]
        self.assertIn('appearance_rating', first_rev)
        self.assertIn('taste_rating', first_rev)
        self.assertIn('portion_rating', first_rev)
        self.assertIn('value_rating', first_rev)
        print("  [PASS] API successfully persists and returns all 4 dish feedback dimensions.")

    def test_02_customer_order_rating_with_dish_feedback(self):
        print("\n--- TEST 2: Customer Order Rating with Per-Dish Dimension Feedback ---")
        self.login_customer()

        order_id = 1010 # Belongs to user 2 (Athul)
        rate_page = self.opener.open(f"{BASE_URL}/customer/rate-order.php?order_id={order_id}&edit=1")
        rate_html = rate_page.read().decode('utf-8')
        csrf = get_csrf(rate_html)

        self.assertIn("Dish-Level Feedback", rate_html, "rate-order.php must have Dish-Level Feedback section")
        self.assertIn("Appearance:", rate_html)
        self.assertIn("Taste:", rate_html)
        self.assertIn("Portion:", rate_html)
        self.assertIn("Value:", rate_html)

        # Submit review with specific dish dimension values
        submit_data = urllib.parse.urlencode({
            'action': 'submit_rating',
            'csrf_token': csrf,
            'food_rating': 5,
            'rider_rating': 5,
            'comments': 'Exquisite meal and prompt delivery partner!',
            'dish_appearance_1': 5,
            'dish_taste_1': 5,
            'dish_portion_1': 4,
            'dish_value_1': 5,
            'is_ajax': '1'
        }).encode('utf-8')

        req = urllib.request.Request(
            f"{BASE_URL}/customer/rate-order.php?order_id={order_id}",
            data=submit_data,
            headers={'X-Requested-With': 'XMLHttpRequest'}
        )
        resp = self.opener.open(req)
        resp_json = json.loads(resp.read().decode('utf-8'))
        print("  Submit Result: success=", resp_json.get('success'))
        self.assertTrue(resp_json.get('success'), "Order rating with dish dimensions should succeed")
        print("  [PASS] Customer order rating with dish feedback recorded successfully.")

    def test_03_admin_dish_feedback_view(self):
        print("\n--- TEST 3: Admin Reviews Console Dish Feedback View ---")
        self.login_admin()

        resp = self.opener.open(f"{BASE_URL}/admin/reviews.php?tab=dishes")
        html = resp.read().decode('utf-8')

        # Check KPIs
        self.assertIn("AVERAGE APPEARANCE", html, "Admin must display Average Appearance KPI")
        self.assertIn("AVERAGE TASTE", html, "Admin must display Average Taste KPI")
        self.assertIn("AVERAGE PORTION SIZE", html, "Admin must display Average Portion Size KPI")
        self.assertIn("AVERAGE VALUE", html, "Admin must display Average Value KPI")

        # Check Dimension Breakdown in distribution section
        self.assertIn("Culinary Dimensions Averages", html)

        # Check Table Headers and Columns
        self.assertIn("Dish Feedback (4 Criteria)", html, "Dish feedback breakdown column header missing")
        self.assertIn("Appearance", html)
        self.assertIn("Taste", html)
        self.assertIn("Portion", html)
        self.assertIn("Value", html)

        # Check Inspection Modal
        self.assertIn("dishReviewInspectModal", html, "Dish inspection modal missing in admin DOM")
        self.assertIn("dishModalAppVal", html, "Appearance metric container missing in modal")
        self.assertIn("dishModalTasteVal", html, "Taste metric container missing in modal")
        self.assertIn("dishModalPortionVal", html, "Portion metric container missing in modal")
        self.assertIn("dishModalValueVal", html, "Value metric container missing in modal")
        print("  [PASS] Admin reviews console fully renders all 4 feedback criteria, KPIs, and inspection HUD.")

    def test_04_admin_csv_export_includes_dimensions(self):
        print("\n--- TEST 4: Admin CSV Export with Dimension Scores ---")
        self.login_admin()

        resp = self.opener.open(f"{BASE_URL}/admin/reviews.php?export=csv&tab=dishes")
        csv_text = resp.read().decode('utf-8')

        lines = csv_text.strip().split("\n")
        header = lines[0]
        print("  CSV Header:", header)
        self.assertIn("Appearance Rating", header, "CSV export must contain Appearance Rating")
        self.assertIn("Taste Rating", header, "CSV export must contain Taste Rating")
        self.assertIn("Portion Size Rating", header, "CSV export must contain Portion Size Rating")
        self.assertIn("Value Rating", header, "CSV export must contain Value Rating")
        self.assertTrue(len(lines) > 1, "CSV must contain data rows")
        print("  [PASS] CSV Export contains all 4 dish-level feedback dimensions.")

    def test_05_admin_dishes_catalog_shows_dimensions(self):
        print("\n--- TEST 5: Admin Dishes Catalog Dimension Badges ---")
        self.login_admin()

        resp = self.opener.open(f"{BASE_URL}/admin/dishes.php")
        html = resp.read().decode('utf-8')

        self.assertIn("Appearance Score", html, "Dishes table should display Appearance badge")
        self.assertIn("Taste Score", html, "Dishes table should display Taste badge")
        self.assertIn("Portion Size Score", html, "Dishes table should display Portion Size badge")
        self.assertIn("Value Score", html, "Dishes table should display Value badge")
        print("  [PASS] Admin Dishes catalog shows dimension breakdown badges.")

if __name__ == '__main__':
    unittest.main()
