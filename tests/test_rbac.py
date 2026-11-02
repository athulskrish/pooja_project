"""
Comprehensive Role-Based Access Control (RBAC) Test Suite
Zippy Food Ordering Platform | MCSP-232 Major Project

Tests:
  1. Unauthenticated Route Guards: Direct access to /admin/ routes redirects to /admin/login.php
  2. Customer Role Boundary: Customer (Role 2) denied Admin console access
  3. Kitchen Role Boundary: Kitchen Chef (Role 3) denied Admin console access
  4. Delivery Role Boundary: Delivery Rider (Role 4) denied Admin console access
  5. Admin Role Superuser Authority: Admin (Role 1) authorized across Admin, Kitchen KDS, and Delivery Fleet
  6. RBAC Permissions Matrix Verification: Inspects live matrix tab at /admin/users.php?tab=matrix
  7. RBAC Self-Demotion & Lockout Guard: Enforces UC-20 anti-lockout policy
"""

import os
import sys
import time
import unittest
import urllib.request
import urllib.parse
import http.cookiejar
import re

BASE_URL = os.environ.get("ZIPPY_BASE_URL", "http://127.0.0.1:8080")


class ZippyRBACTest(unittest.TestCase):

    def setUp(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cj),
            urllib.request.HTTPRedirectHandler()
        )

    def extract_csrf(self, url):
        resp = self.opener.open(url)
        html = resp.read().decode('utf-8')
        m = re.search(r'name="csrf_token" value="([^"]+)"', html)
        if m:
            return m.group(1)
        return ""

    def test_01_unauthenticated_access_to_admin_redirects_to_login(self):
        """Verify unauthenticated user cannot access /admin/index.php or /admin/users.php"""
        print("\n--- RBAC TEST 1: Unauthenticated Guard ---")
        resp = self.opener.open(f"{BASE_URL}/admin/index.php")
        final_url = resp.geturl()
        self.assertIn("admin/login.php", final_url, "Unauthenticated access to /admin/index.php must redirect to admin/login.php")

        resp2 = self.opener.open(f"{BASE_URL}/admin/users.php")
        self.assertIn("admin/login.php", resp2.geturl(), "Unauthenticated access to /admin/users.php must redirect to admin/login.php")
        print("  [PASS] Unauthenticated access properly redirected to login")

    def test_02_customer_role_denied_admin_access(self):
        """Verify Customer (Role 2) is blocked from accessing Admin console routes"""
        print("\n--- RBAC TEST 2: Customer Role Isolation ---")
        # 1. Login as customer
        csrf = self.extract_csrf(f"{BASE_URL}/customer/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'pooja@example.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/customer/login.php", data=login_data)
        self.assertIn("customer", resp.geturl())

        # 2. Attempt to breach Admin portal
        resp_admin = self.opener.open(f"{BASE_URL}/admin/index.php")
        final_url = resp_admin.geturl()
        content = resp_admin.read().decode('utf-8')
        
        # Must NOT be inside admin/index.php; should be redirected to customer/index.php with access denied message
        self.assertNotIn("Operations & Kitchen Dashboard", content)
        self.assertTrue("customer" in final_url or "login.php" in final_url, "Customer role must be expelled from admin console")
        print("  [PASS] Customer role strictly prohibited from Admin Console")

    def test_03_kitchen_role_denied_admin_access(self):
        """Verify Kitchen Chef (Role 3) is blocked from Admin user management and catalog"""
        print("\n--- RBAC TEST 3: Kitchen Role Isolation ---")
        # 1. Login as kitchen chef
        csrf = self.extract_csrf(f"{BASE_URL}/kitchen/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'chef@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/kitchen/login.php", data=login_data)
        self.assertIn("kitchen", resp.geturl())

        # 2. Attempt to access Admin User RBAC
        resp_admin = self.opener.open(f"{BASE_URL}/admin/users.php")
        final_url = resp_admin.geturl()
        content = resp_admin.read().decode('utf-8')
        
        self.assertNotIn("Staff & Customer RBAC Directory", content)
        self.assertTrue("kitchen" in final_url or "login.php" in final_url, "Kitchen role must be expelled from admin users")
        print("  [PASS] Kitchen chef role strictly prohibited from Admin User RBAC")

    def test_04_rider_role_denied_admin_access(self):
        """Verify Delivery Rider (Role 4) is blocked from Admin console routes"""
        print("\n--- RBAC TEST 4: Rider Role Isolation ---")
        # 1. Login as rider
        csrf = self.extract_csrf(f"{BASE_URL}/delivery/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'rider@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/delivery/login.php", data=login_data)
        self.assertIn("delivery", resp.geturl())

        # 2. Attempt to access Admin reports
        resp_admin = self.opener.open(f"{BASE_URL}/admin/reports.php")
        final_url = resp_admin.geturl()
        content = resp_admin.read().decode('utf-8')
        
        self.assertNotIn("Executive BI Reports", content)
        self.assertTrue("delivery" in final_url or "login.php" in final_url, "Rider role must be expelled from admin reports")
        print("  [PASS] Delivery rider role strictly prohibited from Admin Console")

    def test_05_admin_full_access_all_portals(self):
        """Verify Administrator (Role 1) has supervisory access across Admin, Kitchen KDS, and Delivery Fleet"""
        print("\n--- RBAC TEST 5: Admin Superuser Authority ---")
        # 1. Login as admin
        csrf = self.extract_csrf(f"{BASE_URL}/admin/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'admin@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        resp = self.opener.open(f"{BASE_URL}/admin/login.php", data=login_data)
        self.assertIn("admin", resp.geturl())

        # 2. Verify Admin Console
        resp_admin = self.opener.open(f"{BASE_URL}/admin/index.php")
        content_admin = resp_admin.read().decode('utf-8')
        self.assertIn("ADMIN OPERATIONS", content_admin)

        # 3. Verify supervisory access to Kitchen KDS
        resp_kitchen = self.opener.open(f"{BASE_URL}/kitchen/index.php")
        content_kitchen = resp_kitchen.read().decode('utf-8')
        self.assertIn("Atelier", content_kitchen)

        # 4. Verify supervisory access to Delivery Fleet
        resp_delivery = self.opener.open(f"{BASE_URL}/delivery/index.php")
        content_delivery = resp_delivery.read().decode('utf-8')
        self.assertIn("Delivery Partner", content_delivery)
        print("  [PASS] Administrator successfully verified across Admin, Kitchen, and Delivery Fleet portals")

    def test_06_rbac_matrix_rendered(self):
        """Verify RBAC Permissions Matrix displays capability grid and role claims"""
        print("\n--- RBAC TEST 6: RBAC Matrix Verification ---")
        csrf = self.extract_csrf(f"{BASE_URL}/admin/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'admin@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        self.opener.open(f"{BASE_URL}/admin/login.php", data=login_data)

        resp = self.opener.open(f"{BASE_URL}/admin/users.php?tab=matrix")
        html = resp.read().decode('utf-8')
        self.assertIn("System Capability & Access Control Grid", html)
        self.assertIn("Role 1", html)
        self.assertIn("Role 2", html)
        self.assertIn("Role 3", html)
        self.assertIn("Role 4", html)
        self.assertIn("Dish Catalog & Inventory CRUD", html)
        self.assertIn("Thermal KOT", html)
        self.assertIn("6-Digit Doorstep Cryptographic OTP", html)
        print("  [PASS] RBAC Permissions Matrix successfully verified with all 4 roles")

    def test_07_rbac_self_demotion_guard(self):
        """Verify UC-20 anti-lockout policy prevents an administrator from demoting themselves"""
        print("\n--- RBAC TEST 7: Anti-Lockout Self-Demotion Guard ---")
        csrf = self.extract_csrf(f"{BASE_URL}/admin/login.php")
        login_data = urllib.parse.urlencode({
            'csrf_token': csrf,
            'email': 'admin@zippy.com',
            'password': 'password123'
        }).encode('utf-8')
        self.opener.open(f"{BASE_URL}/admin/login.php", data=login_data)

        # Get CSRF from users page
        users_html = self.opener.open(f"{BASE_URL}/admin/users.php").read().decode('utf-8')
        m = re.search(r'name="csrf_token" value="([^"]+)"', users_html)
        users_csrf = m.group(1)

        # Admin user ID is 1 (Pooja JP Admin). Attempt to demote to Customer (Role 2)
        post_data = urllib.parse.urlencode({
            'csrf_token': users_csrf,
            'action': 'update_role',
            'user_id': '1',
            'role_id': '2'
        }).encode('utf-8')

        resp = self.opener.open(f"{BASE_URL}/admin/users.php", data=post_data)
        resp_html = resp.read().decode('utf-8')
        self.assertIn("Self-demotion from Administrator role is prohibited", resp_html)
        print("  [PASS] Anti-lockout guard successfully blocked self-demotion")


if __name__ == '__main__':
    unittest.main(verbosity=2)
