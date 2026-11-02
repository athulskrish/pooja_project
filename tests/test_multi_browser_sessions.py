"""
Automated Integration Test: Same-Browser Multi-Actor Session Isolation & RBAC
Course: MCSP-232 | Student: Pooja JP | Zippy Food Ordering Platform

Tests that a single browser (simulated with a single http.cookiejar.CookieJar)
can simultaneously hold active sessions for:
1. Customer (Pooja JP - role: CUSTOMER) via ZIPPY_CUSTOMER_SESS
2. Kitchen Chef (Sanjeev - role: KITCHEN) via ZIPPY_KITCHEN_SESS
3. Delivery Rider (Suresh Kumar - role: RIDER) via ZIPPY_RIDER_SESS
4. Admin (Pooja JP - role: ADMIN) via ZIPPY_ADMIN_SESS

All four actors make simultaneous/interleaved requests in the same cookiejar
without session overwrites, privilege leaks, or state collision.
"""

import os
import unittest
import urllib.request
import urllib.parse
import http.cookiejar

BASE_URL = os.environ.get("ZIPPY_BASE_URL", "http://127.0.0.1:8080")

class TestMultiBrowserSessions(unittest.TestCase):

    def setUp(self):
        # A single CookieJar simulates a single browser window (like Chrome/Edge)
        # sharing one cookie store across multiple open tabs.
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cj),
            urllib.request.HTTPRedirectHandler()
        )

    def get(self, path):
        url = f"{BASE_URL}{path}"
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
        resp = self.opener.open(req)
        return resp.getcode(), resp.geturl(), resp.read().decode('utf-8', errors='ignore')

    def test_concurrent_four_actor_logins_in_single_browser(self):
        """Simulate opening 4 tabs in one browser and logging into all 4 portals with ?demo=1"""
        print("\n--- TEST 1: Logging into 4 separate tabs in the same browser ---")
        
        # Tab 1: Customer Login
        code, url, text = self.get("/customer/login.php?demo=1")
        self.assertEqual(code, 200)
        cookie_names = [c.name for c in self.cj]
        self.assertIn("ZIPPY_CUSTOMER_SESS", cookie_names)
        print("[OK] Tab 1 (Customer): Logged in as Pooja JP (CUSTOMER)")

        # Tab 2: Kitchen Chef Login
        code, url, text = self.get("/kitchen/login.php?demo=1")
        self.assertEqual(code, 200)
        cookie_names = [c.name for c in self.cj]
        self.assertIn("ZIPPY_KITCHEN_SESS", cookie_names)
        print("[OK] Tab 2 (Kitchen): Logged in as Chef Sanjeev (KITCHEN)")

        # Tab 3: Delivery Rider Login
        code, url, text = self.get("/delivery/login.php?demo=1")
        self.assertEqual(code, 200)
        cookie_names = [c.name for c in self.cj]
        self.assertIn("ZIPPY_RIDER_SESS", cookie_names)
        print("[OK] Tab 3 (Delivery): Logged in as Suresh Kumar (RIDER)")

        # Tab 4: Restaurant Admin Login
        code, url, text = self.get("/admin/login.php?demo=1")
        self.assertEqual(code, 200)
        cookie_names = [c.name for c in self.cj]
        self.assertIn("ZIPPY_ADMIN_SESS", cookie_names)
        print("[OK] Tab 4 (Admin): Logged in as Pooja JP (ADMIN)")

        # Verify the single browser's cookie jar holds all 4 distinct session cookies simultaneously
        all_cookies = [cookie.name for cookie in self.cj]
        self.assertIn("ZIPPY_CUSTOMER_SESS", all_cookies)
        self.assertIn("ZIPPY_KITCHEN_SESS", all_cookies)
        self.assertIn("ZIPPY_RIDER_SESS", all_cookies)
        self.assertIn("ZIPPY_ADMIN_SESS", all_cookies)
        print(f"[OK] Cookie jar holds all 4 session tokens simultaneously: {all_cookies}")

    def test_interleaved_portal_requests_retain_correct_identities(self):
        """Simulate switching between 4 open tabs and performing actions without session collision"""
        print("\n--- TEST 2: Interleaved requests across 4 tabs retain separate identities ---")
        
        # Initialize all 4 logins in this single browser
        self.get("/customer/login.php?demo=1")
        self.get("/kitchen/login.php?demo=1")
        self.get("/delivery/login.php?demo=1")
        self.get("/admin/login.php?demo=1")

        # Tab 1: Request Customer Profile -> Should identify as Pooja JP
        code, url, text = self.get("/customer/profile.php")
        self.assertEqual(code, 200)
        self.assertIn("Pooja JP", text)
        self.assertIn("Customer Scope", text)
        print("[OK] Tab 1 retained Customer identity (Pooja JP)")

        # Tab 2: Switch to Kitchen KDS -> Should show Station Online and Chef identity
        code, url, text = self.get("/kitchen/index.php")
        self.assertEqual(code, 200)
        self.assertIn("KITCHEN SESSION SCOPE", text)
        self.assertIn("Sanjeev", text)
        print("[OK] Tab 2 retained Kitchen Chef identity (Chef Sanjeev)")

        # Tab 3: Switch to Delivery Fleet -> Should show Rider Scope and Suresh Kumar
        code, url, text = self.get("/delivery/index.php")
        self.assertEqual(code, 200)
        self.assertIn("Rider Scope", text)
        self.assertIn("Suresh Kumar", text)
        print("[OK] Tab 3 retained Rider identity (Suresh Kumar)")

        # Tab 4: Switch to Admin Console -> Should show Admin Session and Admin Dashboard
        code, url, text = self.get("/admin/index.php")
        self.assertEqual(code, 200)
        self.assertIn("Admin Portal Session", text)
        self.assertIn("ADMIN OPERATIONS", text)
        print("[OK] Tab 4 retained Admin identity (Pooja JP - Admin)")

        # Switch back to Tab 1: Customer Order History -> Must still be Customer, NOT Admin!
        code, url, text = self.get("/customer/order-history.php")
        self.assertEqual(code, 200)
        self.assertIn("Pooja JP", text)
        self.assertIn("Customer Scope", text)
        print("[OK] Switched back to Tab 1: Still authenticated as Customer (no session overwrite!)")

    def test_rbac_matrix_and_role_management_in_admin(self):
        """Admin console renders the RBAC permissions matrix and role metrics"""
        print("\n--- TEST 3: RBAC Matrix and Role Inspection in Admin Console ---")
        self.get("/admin/login.php?demo=1")
        code, url, text = self.get("/admin/users.php?tab=matrix")
        self.assertEqual(code, 200)
        self.assertIn("RBAC Permissions Matrix", text)
        self.assertIn("System Capability & Access Control Grid", text)
        self.assertIn("Kitchen Display System (KDS) & Cooking Station", text)
        self.assertIn("Delivery Fleet & Doorstep Handshake Logistics", text)
        self.assertIn("Dish Catalog & Inventory CRUD", text)
        print("[OK] Admin Users page renders complete RBAC Matrix and access rules")

    def test_single_portal_logout_does_not_affect_other_tabs(self):
        """Logging out of Customer portal should NOT invalidate the Admin or Kitchen session in the same browser"""
        print("\n--- TEST 4: Single Portal Logout Isolation ---")
        self.get("/customer/login.php?demo=1")
        self.get("/admin/login.php?demo=1")
        self.get("/kitchen/login.php?demo=1")

        # Logout only from Customer
        code, url, text = self.get("/customer/logout.php")
        self.assertEqual(code, 200)
        print("[OK] Logged out from Customer tab")

        # Admin tab must STILL be logged in!
        code, url, text = self.get("/admin/index.php")
        self.assertEqual(code, 200)
        self.assertIn("Admin Portal Session", text)
        print("[OK] Admin portal is STILL logged in and protected (session independence verified)")

        # Kitchen tab must STILL be logged in!
        code, url, text = self.get("/kitchen/index.php")
        self.assertEqual(code, 200)
        self.assertIn("KITCHEN SESSION SCOPE", text)
        print("[OK] Kitchen portal is STILL logged in (session independence verified)")

if __name__ == "__main__":
    unittest.main()
