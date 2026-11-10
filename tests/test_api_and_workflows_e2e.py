"""
Automated API & Full-Lifecycle Integration Suite (HTTP + MySQL/MariaDB)
Zippy Food Ordering Platform | MCSP-232 Major Project

Covers end-to-end backend/API contracts across all four portals:
  1. Master Launchpad, 9 public customer routes & AJAX/JSON APIs (Apriori, Customizations, Catalog Sync gating)
  2. Customer signup, 2-step offline security-question password reset, profile, addresses soft-delete & support ticket
  3. ACID checkout with coupon + customizations + stock locking, 60s cancellation + refund + stock restore,
     insufficient-stock guard, advance scheduled checkout, GST invoice, and Razorpay HMAC-SHA256 webhook
  4. Kitchen KDS transitions (CONFIRMED -> COOKING -> READY), pollers, 86-toggle & KOT; Delivery Fleet duty toggle,
     order claim, GPS simulation & DELIVERED heading interpolation, wrong/right OTP & IMPS double-cashout prevention
  5. Post-delivery order review & 4D dish-reviews API (authenticated CSRF submit + public read)
  6. All 14 Admin pages, 7 Admin AJAX endpoints, categories/dishes/customizations/FAQs/moods CRUD + XSS + soft deletes,
     Z-report closure, COD cash settlement, kitchen salary disbursement, CSV exports & Staff/RBAC CRUD
"""

from __future__ import annotations

import hashlib
import hmac
import json
import os
import re
import sys
import time
import unittest
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from e2e_test_helpers import (  # noqa: E402
    BASE_URL,
    WEBHOOK_SECRET,
    HttpClient,
    assert_html_clean,
    db_conn,
    extract_csrf,
    seed_clean_database,
)


class TestApiAndWorkflowsE2E(unittest.TestCase):
    """Comprehensive API & transactional workflow verification across all portals."""

    @classmethod
    def setUpClass(cls):
        seed_clean_database()
        cls.ts = int(time.time())
        cls.guest = HttpClient(BASE_URL)
        cls.cust = None
        cls.order_c = None
        cls.otp_c = None

    @classmethod
    def tearDownClass(cls):
        seed_clean_database()

    def test_01_gateway_public_pages_and_ajax_apis(self):
        code, _, html = self.guest.get("/index.php")
        assert_html_clean(
            self,
            "Launchpad",
            code,
            html,
            ("Customer Storefront", "Kitchen Station", "Delivery Partner", "Admin Console"),
        )

        public_pages = (
            ("/customer/index.php", ("Frequently Ordered Together", "moodSection")),
            ("/customer/restaurant.php", ("Meghana Special Chicken Biryani",)),
            ("/customer/restaurant.php?healthy=1", ("Healthy",)),
            ("/customer/search.php?q=Biryani&mood=party", ("Meghana Special Chicken Biryani",)),
            ("/customer/offers.php", ("LUSHFOREST", "ZIPPY50", "WELCOME")),
            ("/customer/help.php", ("Frequently Asked Questions",)),
            ("/customer/privacy.php", ("Privacy",)),
            ("/customer/terms.php", ("Terms",)),
            ("/customer/404.php", ("404",)),
        )
        for path, markers in public_pages:
            c, _, h = self.guest.get(path)
            assert_html_clean(self, f"Public {path}", c, h, markers)

        c, _, h = self.guest.get("/customer/ajax/get-dish-customizations.php?item_id=1")
        cust_opts = json.loads(h)
        self.assertEqual(c, 200)
        self.assertTrue(cust_opts["success"] and cust_opts["has_customizations"])
        self.assertGreaterEqual(len(cust_opts["groups"]), 2)

        c, _, h = self.guest.get("/customer/ajax/apriori-api.php?item_ids=1&limit=4")
        rec = json.loads(h)
        self.assertEqual(c, 200)
        self.assertTrue(rec["success"] and len(rec["recommendations"]) >= 1)

        c2, _, h2 = self.guest.get("/customer/ajax/apriori-api.php?action=report")
        rep = json.loads(h2)
        self.assertEqual(c2, 200)
        self.assertIn(rep.get("status"), ("SUCCESS", "INSUFFICIENT_DATA"))
        self.assertIn("rules", rep)

        c, _, h = self.guest.get("/api/catalog-sync.php")
        sync = json.loads(h)
        self.assertEqual(c, 200)
        self.assertTrue(sync["success"] and "version" in sync and sync["latest_dish"] is not None)

        c_priv, _, _ = self.guest.get("/api/catalog-sync.php?portal=admin")
        self.assertEqual(c_priv, 401)

    def test_02_customer_signup_recovery_profile_addresses_and_help(self):
        cust_reg = HttpClient(BASE_URL)
        email = f"qa_api_{self.ts}@example.com"
        phone = f"98765{str(self.ts)[-5:]}"

        _, _, signup_html = cust_reg.get("/customer/signup.php")
        c, url, h = cust_reg.post_form("/customer/signup.php", {
            "csrf_token": extract_csrf(signup_html),
            "full_name": "QA API User",
            "email": email,
            "phone_number": phone,
            "password": "Password123!",
            "security_question": "What was the name of your first school?",
            "security_answer": "Greenwood High",
            "address_line": "Flat 101, Lush Towers, Indiranagar",
            "pincode": "560038",
        })
        assert_html_clean(self, "Signup", c, h)
        self.assertTrue("customer/index.php" in url or "Welcome" in h)

        # Logout and run 2-step offline security-question password reset
        cust_reg.get("/customer/logout.php")
        _, _, fp_html = cust_reg.get("/customer/forgot-password.php")
        c, _, fp_step2 = cust_reg.post_form("/customer/forgot-password.php", {
            "csrf_token": extract_csrf(fp_html),
            "action": "lookup_email",
            "email": email,
        })
        assert_html_clean(self, "Forgot Step 1", c, fp_step2, ("What was the name of your first school?",))

        c, url, h = cust_reg.post_form("/customer/forgot-password.php", {
            "csrf_token": extract_csrf(fp_step2),
            "action": "reset_password",
            "email": email,
            "security_answer": "greenwood high",
            "new_password": "ResetPass456!",
            "confirm_password": "ResetPass456!",
        })
        assert_html_clean(self, "Forgot Step 2", c, h)
        self.assertTrue("customer/login.php" in url and "Sign In" in h)

        c, url, h = cust_reg.post_form("/customer/login.php", {
            "csrf_token": extract_csrf(h),
            "email": email,
            "password": "ResetPass456!",
        })
        self.assertTrue("customer/index.php" in url or "QA API User" in h)

        # Profile update
        _, _, prof_html = cust_reg.get("/customer/profile.php")
        assert_html_clean(self, "Profile", 200, prof_html, (email,))
        c, _, prof_upd = cust_reg.post_form("/customer/profile.php", {
            "csrf_token": extract_csrf(prof_html),
            "action": "update_profile",
            "full_name": "QA API Updated",
            "phone_number": phone,
        })
        assert_html_clean(self, "Profile Update", c, prof_upd, ("QA API Updated",))

        # Addresses CRUD + soft delete
        _, _, addr_html = cust_reg.get("/customer/manage-addresses.php")
        c, _, addr_add = cust_reg.post_form("/customer/manage-addresses.php", {
            "csrf_token": extract_csrf(addr_html),
            "action": "add_address",
            "address_label": "Office",
            "address_line": "Suite 900, MG Road",
            "landmark": "Near Metro",
            "pincode": "560001",
        })
        assert_html_clean(self, "Address Add", c, addr_add, ("Suite 900, MG Road",))

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "SELECT address_id FROM user_addresses WHERE address_line LIKE %s ORDER BY address_id DESC LIMIT 1",
                ("%Suite 900, MG Road%",),
            )
            addr_id = cur.fetchone()["address_id"]

        c, _, addr_del = cust_reg.post_form("/customer/manage-addresses.php", {
            "csrf_token": extract_csrf(addr_add),
            "action": "delete_address",
            "address_id": addr_id,
        })
        assert_html_clean(self, "Address Soft Delete", c, addr_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM user_addresses WHERE address_id = %s", (addr_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # Support ticket
        _, _, help_html = cust_reg.get("/customer/help.php")
        c, _, help_post = cust_reg.post_form("/customer/help.php", {
            "csrf_token": extract_csrf(help_html),
            "action": "submit_ticket",
            "category": "order_delay",
            "order_id": "#ZIP-1001",
            "description": f"QA Ticket API check {self.ts}",
        })
        assert_html_clean(self, "Help Ticket", c, help_post, ("ticket",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "SELECT ticket_id FROM support_tickets WHERE message LIKE %s ORDER BY ticket_id DESC LIMIT 1",
                (f"%QA Ticket API check {self.ts}%",),
            )
            self.assertIsNotNone(cur.fetchone())

    def test_03_checkout_coupon_cancel_stock_guard_scheduled_and_webhook(self):
        cust = HttpClient(BASE_URL)
        _, _, cl_html = cust.get("/customer/login.php")
        cust.post_form("/customer/login.php", {
            "csrf_token": extract_csrf(cl_html),
            "email": "athul@example.com",
            "password": "password123",
        })
        type(self).cust = cust

        _, _, co_html = cust.get("/customer/checkout.php")
        assert_html_clean(self, "Checkout Page", 200, co_html, ("checkoutForm",))

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT stock_quantity FROM menu_items WHERE item_id = 1")
            stock_before = cur.fetchone()["stock_quantity"]

        # Order A: UPI + Coupon LUSHFOREST + Customizations -> 60s Cancel + Refund + Stock Restore
        cart_a = json.dumps([
            {
                "id": 1,
                "qty": 2,
                "customizationTotal": 30.0,
                "customizations": "Spice Level: Medium Spicy, Extra Mirchi Ka Salan (+₹30.00)",
            }
        ])
        c, url, h = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": cart_a,
            "address_id": 1,
            "coupon_code": "LUSHFOREST",
            "payment_mode": "UPI",
            "razorpay_payment_id": f"pay_qa_{self.ts}",
            "order_notes": "QA Cancel Test",
        })
        m_ord = re.search(r"order-success\.php\?order_id=(\d+)", h + " " + url)
        self.assertIsNotNone(m_ord, f"Checkout Order A failed: {url}")
        order_a = int(m_ord.group(1))

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT stock_quantity FROM menu_items WHERE item_id = 1")
            self.assertEqual(cur.fetchone()["stock_quantity"], stock_before - 2)
            cur.execute("SELECT discount_amount, coupon_code FROM orders WHERE order_id = %s", (order_a,))
            ord_a = cur.fetchone()
            self.assertGreater(float(ord_a["discount_amount"]), 0)
            self.assertEqual(ord_a["coupon_code"], "LUSHFOREST")

        # Cancel Order A within 60s
        _, _, os_html = cust.get(f"/customer/order-status.php?order_id={order_a}")
        assert_html_clean(self, "Order Status Pre-Cancel", 200, os_html, ("MINS REMAINING", "btnCancelOrder"))
        c, _, os_cancel = cust.post_form(f"/customer/order-status.php?order_id={order_a}", {
            "csrf_token": extract_csrf(os_html),
            "action": "cancel_order",
            "order_id": order_a,
        })
        assert_html_clean(self, "Order Status Cancelled", c, os_cancel, ("CANCELLED", "CLOSED"))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT stock_quantity FROM menu_items WHERE item_id = 1")
            self.assertEqual(cur.fetchone()["stock_quantity"], stock_before)
            cur.execute("SELECT payment_status FROM payments WHERE order_id = %s", (order_a,))
            self.assertEqual(cur.fetchone()["payment_status"], "REFUNDED")

        # Over-stock checkout guard
        _, _, co_html = cust.get("/customer/checkout.php")
        c_bad, url_bad, h_bad = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 1, "qty": 9999}]),
            "address_id": 1,
            "payment_mode": "COD",
        })
        self.assertNotIn("order-success.php", url_bad)
        assert_html_clean(self, "Overstock Reject", c_bad, h_bad, ("Insufficient stock",))

        # Order B: Advance Scheduled Order
        _, _, co_html = cust.get("/customer/checkout.php")
        c, url, hb = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 2, "qty": 1}]),
            "address_id": 1,
            "delivery_type": "schedule",
            "scheduled_slot": "Tomorrow Lunch (01:00 PM - 01:30 PM)",
            "payment_mode": "UPI",
            "razorpay_payment_id": f"pay_sched_{self.ts}",
        })
        m_b = re.search(r"order-success\.php\?order_id=(\d+)", hb + " " + url)
        self.assertIsNotNone(m_b, f"Scheduled checkout failed: {url}")
        order_b = int(m_b.group(1))
        _, _, os_b = cust.get(f"/customer/order-status.php?order_id={order_b}")
        assert_html_clean(self, "Scheduled Tracker", 200, os_b, ("ADVANCE PRE-ORDER", "Tomorrow Lunch"))

        # Order C: COD Order for KDS -> Delivery -> OTP -> Review -> COD Settlement
        _, _, co_html = cust.get("/customer/checkout.php")
        c, url, hc = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 1, "qty": 1}, {"id": 5, "qty": 1}]),
            "address_id": 1,
            "payment_mode": "COD",
            "order_notes": "QA Full Lifecycle Order",
        })
        m_c = re.search(r"order-success\.php\?order_id=(\d+)", hc + " " + url)
        self.assertIsNotNone(m_c, f"COD checkout failed: {url}")
        order_c = int(m_c.group(1))
        type(self).order_c = order_c

        c_succ, _, succ_html = cust.get(f"/customer/order-success.php?order_id={order_c}")
        assert_html_clean(self, "Order Success", c_succ, succ_html, (f"#ZIP-{order_c}",))

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT delivery_otp FROM delivery_dispatches WHERE order_id = %s", (order_c,))
            type(self).otp_c = cur.fetchone()["delivery_otp"]

        c1, _, j1 = cust.get(f"/customer/ajax/order-status-api.php?order_id={order_c}")
        self.assertEqual(c1, 200)
        self.assertEqual(json.loads(j1)["status"], "CONFIRMED")

        c2, _, j2 = cust.get(f"/api/live-location.php?portal=customer&order_id={order_c}")
        self.assertEqual(c2, 200)
        self.assertEqual(json.loads(j2)["status"], "CONFIRMED")

        c3, _, inv = cust.get(f"/customer/invoice.php?order_id={order_c}")
        assert_html_clean(self, "GST Invoice", c3, inv, ("996331", f"#ZIP-{order_c}"))

        # Razorpay HMAC-SHA256 webhook verification
        wh_body = json.dumps({"order_id": order_b, "payment_id": f"pay_wh_{self.ts}", "status": "CAPTURED"}).encode("utf-8")
        bad_code, _, _ = self.guest.post_json(
            "/api/payment-webhook.php",
            json.loads(wh_body),
            headers={"X-Razorpay-Signature": "deadbeef" * 8},
        )
        self.assertEqual(bad_code, 403)

        sig = hmac.new(WEBHOOK_SECRET.encode("utf-8"), wh_body, hashlib.sha256).hexdigest()
        req = urllib.request.Request(
            f"{BASE_URL}/api/payment-webhook.php",
            data=wh_body,
            headers={"Content-Type": "application/json", "X-Razorpay-Signature": sig},
        )
        wh_resp = json.loads(urllib.request.urlopen(req).read().decode("utf-8"))
        self.assertTrue(wh_resp["success"] and wh_resp["payment_status"] == "SUCCESSFUL")

    def test_04_kitchen_kds_and_delivery_otp_gps_cashout_lifecycle(self):
        order_c = type(self).order_c
        otp_c = type(self).otp_c
        cust = type(self).cust

        # Kitchen KDS login -> poll -> transition -> 86-toggle -> KOT
        kitchen = HttpClient(BASE_URL)
        _, _, kl_html = kitchen.get("/kitchen/login.php")
        kitchen.post_form("/kitchen/login.php", {
            "csrf_token": extract_csrf(kl_html),
            "email": "chef@zippy.com",
            "password": "password123",
        })
        _, _, k_idx = kitchen.get("/kitchen/index.php")
        assert_html_clean(self, "Kitchen Index", 200, k_idx, ("ATELIER KDS", f"#ZIP-{order_c}"))
        k_csrf = extract_csrf(k_idx)

        self.assertTrue(json.loads(kitchen.get("/kitchen/ajax/poll-orders.php")[2])["success"])
        self.assertTrue(json.loads(kitchen.get("/api/live-stream.php?portal=kitchen")[2])["success"])

        c, _, r1 = kitchen.post_json(
            "/kitchen/ajax/update-status.php",
            {"order_id": order_c, "target_status": "COOKING"},
            {"X-CSRF-TOKEN": k_csrf},
        )
        self.assertEqual((c, json.loads(r1)["new_status"]), (200, "COOKING"))

        c, _, r2 = kitchen.post_json(
            "/kitchen/ajax/update-status.php",
            {"order_id": order_c, "target_status": "READY"},
            {"X-CSRF-TOKEN": k_csrf},
        )
        self.assertEqual((c, json.loads(r2)["new_status"]), (200, "READY"))

        c, _, ts1 = kitchen.post_json(
            "/kitchen/ajax/toggle-stock.php",
            {"item_id": 25, "is_available": 0},
            {"X-CSRF-TOKEN": k_csrf},
        )
        self.assertEqual(c, 200)
        self.assertFalse(json.loads(ts1)["is_available"])
        c, _, ts2 = kitchen.post_json(
            "/kitchen/ajax/toggle-stock.php",
            {"item_id": 25, "is_available": 1},
            {"X-CSRF-TOKEN": k_csrf},
        )
        self.assertEqual(c, 200)
        self.assertTrue(json.loads(ts2)["is_available"])

        c, _, kot = kitchen.get(f"/kitchen/kot.php?order_id={order_c}")
        assert_html_clean(self, "Kitchen KOT", c, kot, ("KITCHEN ORDER TICKET", f"#ZIP-{order_c}"))

        # Delivery Rider login -> duty ON/OFF -> claim -> GPS -> wrong/right OTP -> heading at 100% -> IMPS cashout
        rider = HttpClient(BASE_URL)
        _, _, rl_html = rider.get("/delivery/login.php")
        rider.post_form("/delivery/login.php", {
            "csrf_token": extract_csrf(rl_html),
            "email": "shaji@zippy.com",
            "password": "password123",
        })
        _, _, r_idx = rider.get("/delivery/index.php")
        assert_html_clean(self, "Delivery Index", 200, r_idx, ("Shaji",))
        r_csrf = extract_csrf(r_idx)
        self.assertTrue(json.loads(rider.get("/api/live-stream.php?portal=delivery")[2])["success"])

        # OFFLINE duty guard blocks claim
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf, "action": "toggle_duty", "duty": "0"})
        _, _, r_off = rider.get("/delivery/index.php")
        r_csrf = extract_csrf(r_off)
        _, _, r_block = rider.post_form("/delivery/index.php", {
            "csrf_token": r_csrf,
            "action": "claim_order",
            "order_id": order_c,
        })
        self.assertIn("OFFLINE", r_block)

        # Back ONLINE and claim Order C
        rider.post_form("/delivery/index.php", {
            "csrf_token": extract_csrf(r_block),
            "action": "toggle_duty",
            "duty": "1",
        })
        _, _, r_on = rider.get("/delivery/index.php")
        r_csrf = extract_csrf(r_on)
        c, _, r_claim = rider.post_form("/delivery/index.php", {
            "csrf_token": r_csrf,
            "action": "claim_order",
            "order_id": order_c,
        })
        assert_html_clean(self, "Rider Claim", c, r_claim)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_status FROM orders WHERE order_id = %s", (order_c,))
            self.assertEqual(cur.fetchone()["order_status"], "OUT_FOR_DELIVERY")

        c, _, gps1 = rider.post_json(
            "/api/live-location.php?portal=delivery",
            {"order_id": order_c, "action": "simulate_step"},
            {"X-CSRF-TOKEN": r_csrf},
        )
        self.assertEqual(c, 200)
        self.assertTrue(json.loads(gps1)["success"])

        c, _, gps_get = cust.get(f"/api/live-location.php?portal=customer&order_id={order_c}")
        gps_data = json.loads(gps_get)
        self.assertEqual(c, 200)
        self.assertGreater(gps_data["route"]["progress_pct"], 0)
        self.assertIn("heading", gps_data["locations"]["rider"])

        # Wrong OTP stays OUT_FOR_DELIVERY
        c, _, r_bad_otp = rider.post_form("/delivery/index.php", {
            "csrf_token": extract_csrf(r_claim),
            "action": "verify_otp",
            "order_id": order_c,
            "otp": "000000",
        })
        assert_html_clean(self, "Wrong OTP", c, r_bad_otp, ("Incorrect OTP",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_status FROM orders WHERE order_id = %s", (order_c,))
            self.assertEqual(cur.fetchone()["order_status"], "OUT_FOR_DELIVERY")

        # Right OTP completes delivery cleanly
        c, _, r_good_otp = rider.post_form("/delivery/index.php", {
            "csrf_token": extract_csrf(r_bad_otp),
            "action": "verify_otp",
            "order_id": order_c,
            "otp": otp_c,
        })
        assert_html_clean(self, "Correct OTP", c, r_good_otp)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_status FROM orders WHERE order_id = %s", (order_c,))
            self.assertEqual(cur.fetchone()["order_status"], "DELIVERED")

        # Delivered live-location endpoint still returns valid JSON + heading key (no PHP warning)
        c, _, gps_done = cust.get(f"/api/live-location.php?portal=customer&order_id={order_c}")
        gps_done_json = json.loads(gps_done)
        self.assertEqual(c, 200)
        self.assertTrue(gps_done_json["is_delivered"])
        self.assertIn("heading", gps_done_json["locations"]["rider"])

        # IMPS cashout + double-cashout rejection (400)
        _, _, r_after = rider.get("/delivery/index.php")
        r_csrf = extract_csrf(r_after)
        c, _, co1 = rider.post_form("/delivery/ajax/cashout.php", {"csrf_token": r_csrf}, {"X-CSRF-TOKEN": r_csrf})
        co1_json = json.loads(co1)
        self.assertEqual(c, 200)
        self.assertTrue(co1_json["success"] and float(co1_json["amount"]) > 0)

        c2, _, co2 = rider.post_form("/delivery/ajax/cashout.php", {"csrf_token": r_csrf}, {"X-CSRF-TOKEN": r_csrf})
        self.assertEqual(c2, 400)
        self.assertFalse(json.loads(co2)["success"])

    def test_05_post_delivery_order_rating_and_4d_dish_reviews_api(self):
        order_c = type(self).order_c
        cust = type(self).cust

        _, _, rate_html = cust.get(f"/customer/rate-order.php?order_id={order_c}")
        assert_html_clean(self, "Rate Order Page", 200, rate_html, (f"#ZIP-{order_c}",))
        c, _, rate_resp = cust.post_form(f"/customer/rate-order.php?order_id={order_c}", {
            "csrf_token": extract_csrf(rate_html),
            "food_rating": 5,
            "delivery_rating": 5,
            "rider_tip": 25,
            "comments": "Excellent biryani and punctual delivery!",
        })
        assert_html_clean(self, "Rate Order Submit", c, rate_resp)

        _, _, rest_html = cust.get("/customer/restaurant.php")
        cust_csrf = extract_csrf(rest_html)
        c, _, dr_post = cust.post_form("/api/dish-reviews.php", {
            "csrf_token": cust_csrf,
            "action": "submit_review",
            "dish_id": 1,
            "appearance_rating": 5,
            "taste_rating": 5,
            "portion_rating": 4,
            "value_rating": 5,
            "review_text": "Verified 4D feedback review",
            "tags": "Authentic Spice,Hot & Fresh",
        }, {"X-CSRF-TOKEN": cust_csrf})
        dr_json = json.loads(dr_post)
        self.assertEqual(c, 200)
        self.assertTrue(dr_json["success"])
        self.assertEqual(dr_json["dimensions"]["taste"], 5)

        c_get, _, dr_get = self.guest.get("/api/dish-reviews.php?dish_id=1")
        self.assertEqual(c_get, 200)
        self.assertTrue(json.loads(dr_get)["success"])

    def test_06_admin_pages_ajax_crud_soft_deletes_finance_exports_and_rbac(self):
        order_c = type(self).order_c
        admin = HttpClient(BASE_URL)
        _, _, al_html = admin.get("/admin/login.php")
        admin.post_form("/admin/login.php", {
            "csrf_token": extract_csrf(al_html),
            "email": "admin@zippy.com",
            "password": "password123",
        })

        for ap in (
            "/admin/index.php",
            "/admin/orders.php",
            "/admin/kitchen-view.php",
            "/admin/delivery-view.php",
            "/admin/financial-transactions.php",
            "/admin/categories.php",
            "/admin/dishes.php",
            "/admin/dish-customizations.php",
            "/admin/moods.php",
            "/admin/faqs.php",
            "/admin/reviews.php",
            "/admin/reports.php",
            "/admin/users.php",
            "/admin/audit-logs.php",
        ):
            c, _, h = admin.get(ap)
            assert_html_clean(self, f"Admin {ap}", c, h)

        _, _, a_idx = admin.get("/admin/index.php")
        a_csrf = extract_csrf(a_idx)
        self.assertTrue(json.loads(admin.get("/api/live-stream.php?portal=admin")[2])["success"])
        self.assertGreaterEqual(json.loads(admin.get("/admin/ajax/command-search.php?q=Biryani")[2])["count"], 1)
        self.assertTrue(json.loads(admin.get("/admin/ajax/get-fleet-telemetry.php")[2])["success"])
        self.assertGreaterEqual(json.loads(admin.get(f"/admin/ajax/get-order-audit-trail.php?order_id={order_c}")[2])["total_transitions"], 2)
        self.assertTrue(json.loads(admin.post_json("/admin/ajax/adjust-stock.php", {"item_id": 25, "delta": 2}, {"X-CSRF-TOKEN": a_csrf})[2])["success"])
        self.assertTrue(json.loads(admin.post_json("/admin/ajax/adjust-stock.php", {"item_id": 25, "delta": -2}, {"X-CSRF-TOKEN": a_csrf})[2])["success"])
        self.assertTrue(json.loads(admin.post_json("/admin/ajax/toggle-stock.php", {"item_id": 25, "is_available": 1}, {"X-CSRF-TOKEN": a_csrf})[2])["success"])
        self.assertTrue(json.loads(admin.post_json("/admin/ajax/bulk-dishes.php", {"action": "add_stock", "item_ids": [25], "delta": 1}, {"X-CSRF-TOKEN": a_csrf})[2])["success"])

        # Categories CRUD + soft-delete
        _, _, cat_html = admin.get("/admin/categories.php")
        c, _, cat_add = admin.post_form("/admin/categories.php", {
            "csrf_token": extract_csrf(cat_html),
            "action": "add_category",
            "category_name": f"QA Snacks {self.ts}",
            "category_description": "QA test category",
            "display_order": 99,
        })
        assert_html_clean(self, "Category Add", c, cat_add, (f"QA Snacks {self.ts}",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT category_id FROM categories WHERE category_name = %s", (f"QA Snacks {self.ts}",))
            cat_id = cur.fetchone()["category_id"]

        c, _, cat_del = admin.post_form("/admin/categories.php", {
            "csrf_token": extract_csrf(cat_add),
            "action": "delete_category",
            "category_id": cat_id,
        })
        assert_html_clean(self, "Category Delete", c, cat_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM categories WHERE category_id = %s", (cat_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # Dishes CRUD + soft-delete
        _, _, dish_html = admin.get("/admin/dishes.php")
        c, _, dish_add = admin.post_form("/admin/dishes.php", {
            "csrf_token": extract_csrf(dish_html),
            "action": "add_dish",
            "item_name": f"QA Dish {self.ts}",
            "category_id": 1,
            "price": "199.00",
            "stock_quantity": 10,
            "prep_time_minutes": 15,
            "mood": "comfort",
            "description": "QA test dish",
            "is_veg": 1,
        })
        assert_html_clean(self, "Dish Add", c, dish_add, (f"QA Dish {self.ts}",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT item_id FROM menu_items WHERE item_name = %s", (f"QA Dish {self.ts}",))
            dish_id = cur.fetchone()["item_id"]

        c, _, dish_del = admin.post_form("/admin/dishes.php", {
            "csrf_token": extract_csrf(dish_add),
            "action": "delete_dish",
            "item_id": dish_id,
        })
        assert_html_clean(self, "Dish Delete", c, dish_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM menu_items WHERE item_id = %s", (dish_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # FAQs XSS sanitization + duplicate + reorder + soft-delete
        _, _, faq_html = admin.get("/admin/faqs.php")
        c, _, faq_add = admin.post_form("/admin/faqs.php", {
            "csrf_token": extract_csrf(faq_html),
            "action": "add_faq",
            "question": f"QA FAQ {self.ts}?",
            "answer": "<p>Safe <b>answer</b><script>alert('xss')</script></p>",
            "category": "General",
            "display_order": 99,
            "is_active": "1",
        })
        assert_html_clean(self, "FAQ Add", c, faq_add, (f"QA FAQ {self.ts}?",))
        _, _, pub_help = self.guest.get("/customer/help.php")
        self.assertNotIn("alert('xss')", pub_help)
        self.assertNotIn("<script>alert", pub_help)

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT faq_id FROM faqs WHERE question = %s", (f"QA FAQ {self.ts}?",))
            faq_id = cur.fetchone()["faq_id"]

        c, _, faq_del = admin.post_form("/admin/faqs.php", {
            "csrf_token": extract_csrf(faq_add),
            "action": "delete_faq",
            "faq_id": faq_id,
        })
        assert_html_clean(self, "FAQ Soft Delete", c, faq_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM faqs WHERE faq_id = %s", (faq_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # Customizations option add & soft-delete
        _, _, dc_html = admin.get("/admin/dish-customizations.php?item_id=1")
        c, _, dc_add = admin.post_form("/admin/dish-customizations.php?item_id=1", {
            "csrf_token": extract_csrf(dc_html),
            "action": "add_option",
            "group_id": 1,
            "item_id": 1,
            "option_name": f"QA Extra Spice {self.ts}",
            "price": "25.00",
            "is_available": "1",
        })
        assert_html_clean(self, "Customisation Option Add", c, dc_add, (f"QA Extra Spice {self.ts}",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT option_id FROM dish_customization_options WHERE option_name = %s", (f"QA Extra Spice {self.ts}",))
            opt_id = cur.fetchone()["option_id"]

        c, _, dc_del = admin.post_form("/admin/dish-customizations.php?item_id=1", {
            "csrf_token": extract_csrf(dc_add),
            "action": "delete_option",
            "option_id": opt_id,
        })
        assert_html_clean(self, "Customisation Option Delete", c, dc_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM dish_customization_options WHERE option_id = %s", (opt_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # Moods CRUD + dish linking + soft-archive
        _, _, m_html = admin.get("/admin/moods.php")
        mood_key = f"qa_mood_{self.ts}"
        c, _, m_add = admin.post_form("/admin/moods.php", {
            "csrf_token": extract_csrf(m_html),
            "action": "add_mood",
            "name": f"QA Vibe {self.ts}",
            "mood_key": mood_key,
            "short_name": "QA Vibe",
            "emoji": "🔥",
            "tagline": "Created during QA",
            "is_active": "1",
        })
        assert_html_clean(self, "Mood Add", c, m_add, (f"QA Vibe {self.ts}",))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT mood_id FROM food_moods WHERE mood_key = %s", (mood_key,))
            mood_id = cur.fetchone()["mood_id"]

        c, _, m_edit = admin.post_form("/admin/moods.php", {
            "csrf_token": extract_csrf(m_add),
            "action": "edit_mood",
            "mood_id": mood_id,
            "name": f"QA Vibe Edited {self.ts}",
            "short_name": "QA Edited",
            "emoji": "⚡",
            "tagline": "Updated QA Mood",
            "display_order": 99,
            "is_active": "1",
        })
        assert_html_clean(self, "Mood Edit", c, m_edit, (f"QA Vibe Edited {self.ts}",))
        c, _, m_link = admin.post_form("/admin/moods.php", {
            "csrf_token": extract_csrf(m_edit),
            "action": "quick_add_dish",
            "mood_key": mood_key,
            "item_id": 1,
        })
        assert_html_clean(self, "Mood Link Dish", c, m_link)
        c, _, m_del = admin.post_form("/admin/moods.php", {
            "csrf_token": extract_csrf(m_link),
            "action": "delete_mood",
            "mood_id": mood_id,
        })
        assert_html_clean(self, "Mood Delete", c, m_del)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM food_moods WHERE mood_id = %s", (mood_id,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # Financial Z-report, COD cash settlement & Kitchen salary disbursement
        _, _, fin_html = admin.get("/admin/financial-transactions.php")
        c, _, fin_z = admin.post_form("/admin/financial-transactions.php", {
            "csrf_token": extract_csrf(fin_html),
            "action": "close_cash_register",
            "physical_cash": "500.00",
            "discrepancy_notes": "QA End of Shift Audit",
        })
        assert_html_clean(self, "Z-Report Closure", c, fin_z, ("Z-Report", "QA End of Shift Audit"))

        _, _, dv_html = admin.get("/admin/delivery-view.php")
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT profile_id, cod_balance FROM staff_profiles WHERE user_id = 6")
            shaji_prof = cur.fetchone()
        self.assertGreater(float(shaji_prof["cod_balance"]), 0.0)
        c, _, dv_settle = admin.post_form("/admin/delivery-view.php", {
            "csrf_token": extract_csrf(dv_html),
            "action": "settle_cod_cash",
            "profile_id": shaji_prof["profile_id"],
        })
        assert_html_clean(self, "COD Settle", c, dv_settle)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT cod_balance FROM staff_profiles WHERE user_id = 6")
            self.assertEqual(float(cur.fetchone()["cod_balance"]), 0.0)

        _, _, kv_html = admin.get("/admin/kitchen-view.php")
        c, _, kv_pay = admin.post_form("/admin/kitchen-view.php", {
            "csrf_token": extract_csrf(kv_html),
            "action": "disburse_salary",
            "user_id": 5,
            "month_year": f"QA {self.ts}",
            "base_amount": "35000.00",
            "incentives_amount": "2000.00",
            "deductions_amount": "0.00",
            "payment_mode": "BANK_TRANSFER",
            "notes": "QA Salary Disbursement",
        })
        assert_html_clean(self, "Kitchen Salary Disbursement", c, kv_pay)

        # CSV exports (including dish reviews & BI report)
        for exp_path in (
            "/admin/dishes.php?export=csv",
            "/admin/financial-transactions.php?export=csv",
            "/admin/audit-logs.php?status=DELIVERED&role=RIDER&export=csv",
            "/admin/reviews.php?export=csv",
            "/admin/reviews.php?tab=dishes&export=csv",
            "/admin/reports.php?export=csv",
        ):
            ec, _, eh = admin.get(exp_path)
            self.assertEqual(ec, 200, f"CSV export failed: {exp_path}")
            self.assertGreater(len(eh), 30)

        # Admin Staff & RBAC CRUD
        _, _, u_html = admin.get("/admin/users.php")
        staff_email = f"qastaff_{self.ts}@zippy.com"
        c, _, u_add = admin.post_form("/admin/users.php", {
            "csrf_token": extract_csrf(u_html),
            "action": "add_staff",
            "role_id": 3,
            "full_name": f"QA Chef {self.ts}",
            "email": staff_email,
            "phone_number": f"91234{str(self.ts)[-5:]}",
            "password": "Password123!",
        })
        assert_html_clean(self, "Add Staff", c, u_add, (staff_email,))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT user_id FROM users WHERE email = %s", (staff_email,))
            staff_id = cur.fetchone()["user_id"]

        c, _, u_role = admin.post_form("/admin/users.php", {
            "csrf_token": extract_csrf(u_add),
            "action": "update_role",
            "user_id": staff_id,
            "role_id": 4,
        })
        assert_html_clean(self, "Update Staff Role", c, u_role)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT role_id FROM users WHERE user_id = %s", (staff_id,))
            self.assertEqual(cur.fetchone()["role_id"], 4)


if __name__ == "__main__":
    unittest.main(verbosity=2)
