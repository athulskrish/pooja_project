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
  6. All Admin pages, 7 Admin AJAX endpoints, categories/dishes/customizations/FAQs/moods CRUD + XSS + soft deletes,
     Z-report closure, COD cash settlement, kitchen salary disbursement, CSV exports & Staff/RBAC CRUD
  7. V2 Customer/Admin APIs: Favorites, persistent cart sync, dietary profile + dish nutrition CRUD,
     Bengaluru geofencing & tiered distance fees, dynamic DB coupons & loyalty earn/redeem/refund, and FCFS scheduled slot caps
  8. V2 Kitchen/Fleet/Support/SSE APIs: KDS pre-alerts + dish prep checklists, Rider SOS failure + breakdown requeue
     + Admin re-dispatch, threaded Support Ticket replies, and live EventSource SSE streams
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
from datetime import datetime, timedelta

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

    def _login_client(self, login_path: str, email: str, password: str = "password123") -> HttpClient:
        client = HttpClient(BASE_URL)
        _, _, login_html = client.get(login_path)
        client.post_form(login_path, {
            "csrf_token": extract_csrf(login_html),
            "email": email,
            "password": password,
        })
        return client

    def test_07_v2_storefront_coupons_loyalty_geofence_and_slots(self):
        cust = self._login_client("/customer/login.php", "athul@example.com")
        admin = self._login_client("/admin/login.php", "admin@zippy.com")

        _, _, idx_html = cust.get("/customer/index.php")
        c_csrf = extract_csrf(idx_html)

        # 1. Favorites API: guest 401 -> customer toggle ON -> OFF -> ON
        unauth_c, _, _ = self.guest.post_json("/customer/ajax/toggle-favorite.php", {"item_id": 1})
        self.assertEqual(unauth_c, 401)

        c, _, fav1 = cust.post_json("/customer/ajax/toggle-favorite.php", {"item_id": 1, "csrf_token": c_csrf}, {"X-CSRF-TOKEN": c_csrf})
        self.assertEqual(c, 200)
        f1 = json.loads(fav1)
        self.assertTrue(f1["success"] and f1["favorited"] is True)

        c, _, fav2 = cust.post_json("/customer/ajax/toggle-favorite.php", {"item_id": 1, "csrf_token": c_csrf}, {"X-CSRF-TOKEN": c_csrf})
        self.assertEqual(c, 200)
        self.assertFalse(json.loads(fav2)["favorited"])

        c, _, fav3 = cust.post_json("/customer/ajax/toggle-favorite.php", {"item_id": 1, "csrf_token": c_csrf}, {"X-CSRF-TOKEN": c_csrf})
        self.assertEqual(c, 200)
        self.assertTrue(json.loads(fav3)["favorited"])

        # 2. Persistent Server Cart Sync (guest 401 -> merge -> GET -> clear)
        unauth_cart, _, _ = self.guest.get("/customer/ajax/cart-sync.php")
        self.assertEqual(unauth_cart, 401)

        c, _, cs_merge = cust.post_json("/customer/ajax/cart-sync.php", {
            "action": "merge",
            "csrf_token": c_csrf,
            "items": [{"id": 2, "cartItemId": "2", "qty": 2, "price": 249.0}],
        }, {"X-CSRF-TOKEN": c_csrf})
        self.assertEqual(c, 200)
        merged = json.loads(cs_merge)
        self.assertTrue(merged["success"] and any(it["id"] == 2 and it["qty"] == 2 for it in merged["items"]))

        c, _, cs_get = cust.get("/customer/ajax/cart-sync.php")
        self.assertEqual(c, 200)
        self.assertTrue(any(it["id"] == 2 and it["qty"] == 2 for it in json.loads(cs_get)["items"]))

        c, _, cs_clr = cust.post_json("/customer/ajax/cart-sync.php", {"action": "clear", "csrf_token": c_csrf}, {"X-CSRF-TOKEN": c_csrf})
        self.assertEqual(c, 200)
        self.assertEqual(json.loads(cs_clr)["items"], [])

        # 3. Dietary Profile Update + Admin Dish Nutrition/Allergen CRUD + Search Filters
        _, _, prof_html = cust.get("/customer/profile.php")
        c, _, prof_diet = cust.post_form("/customer/profile.php", {
            "csrf_token": extract_csrf(prof_html),
            "action": "update_dietary",
            "dietary_preferences[]": ["dairy_free", "mild_spice"],
        })
        assert_html_clean(self, "Update Dietary Prefs", c, prof_diet)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT dietary_preferences FROM users WHERE email = 'athul@example.com'")
            dp = cur.fetchone()["dietary_preferences"] or ""
            self.assertIn("dairy_free", dp)
            self.assertIn("mild_spice", dp)

        _, _, d_html = admin.get("/admin/dishes.php")
        dish_name = f"QA Tofu Protein Bowl {self.ts}"
        c, _, d_add = admin.post_form("/admin/dishes.php", {
            "csrf_token": extract_csrf(d_html),
            "action": "add_dish",
            "category_id": 1,
            "kitchen_station": "Cold Bar",
            "item_name": dish_name,
            "price": "280",
            "stock_quantity": "25",
            "prep_time_minutes": "14",
            "description": "High protein soy tofu bowl",
            "mood": "healthy",
            "calories_kcal": "410",
            "protein_g": "28.5",
            "carbs_g": "36.0",
            "fat_g": "11.0",
            "spice_level": "EXTRA_SPICY",
            "allergens[]": ["soy"],
            "is_veg": "1",
            "is_healthy": "1",
        })
        assert_html_clean(self, "Add Dish With Nutrition", c, d_add, (dish_name,))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT item_id, protein_g, allergens, spice_level FROM menu_items WHERE item_name = %s", (dish_name,))
            d_row = cur.fetchone()
            self.assertIsNotNone(d_row)
            self.assertEqual(float(d_row["protein_g"]), 28.5)
            self.assertEqual(d_row["allergens"], "soy")
            self.assertEqual(d_row["spice_level"], "EXTRA_SPICY")

        c, _, srch_html = cust.get(f"/customer/search.php?high_protein=1&allergen_free=nuts&sort=protein_desc")
        assert_html_clean(self, "High Protein Allergen-Free Search", c, srch_html, (dish_name, "Spicy"))

        # 4. Indian Bengaluru Geofence Validation & Distance-Based Tiered Pricing
        _, _, addr_html = cust.get("/customer/manage-addresses.php")
        a_csrf = extract_csrf(addr_html)

        _, _, addr_non_blr = cust.post_form("/customer/manage-addresses.php", {
            "csrf_token": a_csrf,
            "action": "add_address",
            "address_type": "WORK",
            "address_line": f"Marine Drive Mumbai {self.ts}",
            "pincode": "400001",
        })
        assert_html_clean(self, "Non-Bengaluru Pincode Reject", 200, addr_non_blr)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS cnt FROM user_addresses WHERE address_line = %s", (f"Marine Drive Mumbai {self.ts}",))
            self.assertEqual(cur.fetchone()["cnt"], 0)

        _, _, addr_far = cust.post_form("/customer/manage-addresses.php", {
            "csrf_token": extract_csrf(addr_non_blr),
            "action": "add_address",
            "address_type": "OTHER",
            "address_line": f"Far Outskirts {self.ts}",
            "pincode": "560038",
            "latitude": "13.500000",
            "longitude": "77.900000",
        })
        assert_html_clean(self, "Out-of-Range Geofence Reject", 200, addr_far)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS cnt FROM user_addresses WHERE address_line = %s", (f"Far Outskirts {self.ts}",))
            self.assertEqual(cur.fetchone()["cnt"], 0)

        near_line = f"Indiranagar 12th Main {self.ts}"
        c, _, addr_near = cust.post_form("/customer/manage-addresses.php", {
            "csrf_token": extract_csrf(addr_far),
            "action": "add_address",
            "address_type": "HOME",
            "address_line": near_line,
            "landmark": "Near Metro",
            "pincode": "560038",
            "latitude": "12.971800",
            "longitude": "77.641200",
            "is_default": "1",
        })
        assert_html_clean(self, "Bengaluru Pin Address Add", c, addr_near, (near_line,))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT address_id, distance_km FROM user_addresses WHERE address_line = %s", (near_line,))
            near_addr = cur.fetchone()
            self.assertIsNotNone(near_addr)
            self.assertLess(float(near_addr["distance_km"]), 3.0)
            near_addr_id = near_addr["address_id"]

        # Checkout under ₹499 using near_addr_id should charge ₹25.00 distance fee
        _, _, co_html = cust.get("/customer/checkout.php")
        c, u_dist, h_dist = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 2, "qty": 1}]),
            "address_id": near_addr_id,
            "payment_mode": "COD",
        })
        m_dist = re.search(r"order-success\.php\?order_id=(\d+)", h_dist + " " + u_dist)
        self.assertIsNotNone(m_dist)
        dist_oid = int(m_dist.group(1))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT delivery_fee, distance_km FROM orders WHERE order_id = %s", (dist_oid,))
            o_dist = cur.fetchone()
            self.assertEqual(float(o_dist["delivery_fee"]), 25.00)
            self.assertLess(float(o_dist["distance_km"]), 3.0)

        # 5. Dynamic DB Coupons CRUD + Limits + Loyalty Redeem/Cancel Refund
        _, _, cp_html = admin.get("/admin/coupons.php")
        assert_html_clean(self, "Admin Coupons Page", 200, cp_html, ("Dynamic Promo Coupons",))
        v2_code = f"V2SAVE{str(self.ts)[-4:]}"
        c, _, cp_add = admin.post_form("/admin/coupons.php", {
            "csrf_token": extract_csrf(cp_html),
            "action": "create_coupon",
            "code": v2_code,
            "title": "QA Dynamic Promo",
            "description": "Flat 60 OFF QA",
            "discount_type": "flat",
            "discount_value": "60",
            "max_discount": "60",
            "min_order": "200",
            "usage_limit": "10",
            "per_user_limit": "1",
            "is_active": "1",
        })
        assert_html_clean(self, "Create Dynamic Coupon", c, cp_add, (v2_code,))

        c, _, vc1 = cust.get(f"/customer/ajax/validate-coupon.php?code={v2_code}&subtotal=250")
        self.assertEqual(c, 200)
        vc1_j = json.loads(vc1)
        self.assertTrue(vc1_j["valid"] and float(vc1_j["discount"]) == 60.0)

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT user_id, loyalty_points FROM users WHERE email = 'athul@example.com'")
            athul = cur.fetchone()
            pts_before = int(athul["loyalty_points"])
        self.assertGreaterEqual(pts_before, 40)

        _, _, co_html = cust.get("/customer/checkout.php")
        c, u_cp, h_cp = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 1, "qty": 1}]),
            "address_id": near_addr_id,
            "coupon_code": v2_code,
            "loyalty_points_used": 40,
            "payment_mode": "UPI",
            "razorpay_payment_id": f"pay_v2_{self.ts}",
        })
        m_cp = re.search(r"order-success\.php\?order_id=(\d+)", h_cp + " " + u_cp)
        self.assertIsNotNone(m_cp)
        cp_oid = int(m_cp.group(1))

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT discount_amount, loyalty_points_used, loyalty_discount FROM orders WHERE order_id = %s", (cp_oid,))
            ord_cp = cur.fetchone()
            self.assertEqual(float(ord_cp["discount_amount"]), 60.0)
            self.assertEqual(int(ord_cp["loyalty_points_used"]), 40)
            self.assertEqual(float(ord_cp["loyalty_discount"]), 40.0)
            cur.execute("SELECT COUNT(*) AS cnt FROM coupon_redemptions WHERE order_id = %s", (cp_oid,))
            self.assertEqual(cur.fetchone()["cnt"], 1)
            cur.execute("SELECT loyalty_points FROM users WHERE user_id = %s", (athul["user_id"],))
            self.assertEqual(int(cur.fetchone()["loyalty_points"]), pts_before - 40)

        # Second validate fails per_user_limit=1
        c, _, vc2 = cust.get(f"/customer/ajax/validate-coupon.php?code={v2_code}&subtotal=300")
        self.assertFalse(json.loads(vc2)["valid"])

        # Cancel order within 60s -> Loyalty Points refunded
        _, _, st_cp = cust.get(f"/customer/order-status.php?order_id={cp_oid}")
        cust.post_form(f"/customer/order-status.php?order_id={cp_oid}", {
            "csrf_token": extract_csrf(st_cp),
            "action": "cancel_order",
            "order_id": cp_oid,
        })
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT loyalty_points FROM users WHERE user_id = %s", (athul["user_id"],))
            self.assertEqual(int(cur.fetchone()["loyalty_points"]), pts_before)
            cur.execute("SELECT COUNT(*) AS cnt FROM loyalty_transactions WHERE order_id = %s AND txn_type = 'REFUND_BONUS'", (cp_oid,))
            self.assertEqual(cur.fetchone()["cnt"], 1)

        # Admin toggle & archive coupon
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT coupon_id FROM coupons WHERE code = %s", (v2_code,))
            cid = cur.fetchone()["coupon_id"]
        _, _, cp_html2 = admin.get("/admin/coupons.php")
        admin.post_form("/admin/coupons.php", {"csrf_token": extract_csrf(cp_html2), "action": "delete_coupon", "coupon_id": cid})
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_active FROM coupons WHERE coupon_id = %s", (cid,))
            self.assertEqual(cur.fetchone()["is_active"], 0)

        # 6. FCFS Scheduled Slot Capacity Throttling + Release + Kitchen Pre-Alert Banner
        tomorrow = (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%d")
        slot_key = f"{tomorrow}|14:15"
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "INSERT INTO scheduled_slot_caps (slot_key, slot_date, slot_label, max_capacity, booked_count) "
                "VALUES (%s, %s, 'Lunch (02:00 PM - 02:30 PM)', 5, 4) "
                "ON DUPLICATE KEY UPDATE booked_count = 4, max_capacity = 5",
                (slot_key, tomorrow),
            )

        c, _, sa1 = cust.get(f"/customer/ajax/slot-availability.php?date={tomorrow}")
        self.assertEqual(c, 200)
        sa1_j = json.loads(sa1)
        self.assertEqual(sa1_j["policy"], "FCFS")
        self.assertEqual(sa1_j["slots_by_time"]["14:15"]["remaining"], 1)

        # 5th booking fills slot 4 -> 5
        _, _, co_html = cust.get("/customer/checkout.php")
        c, u_s5, h_s5 = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 2, "qty": 1}]),
            "address_id": near_addr_id,
            "delivery_type": "schedule",
            "scheduled_date": tomorrow,
            "scheduled_time": "14:15",
            "scheduled_slot": "Lunch (02:00 PM - 02:30 PM)",
            "payment_mode": "COD",
        })
        m_s5 = re.search(r"order-success\.php\?order_id=(\d+)", h_s5 + " " + u_s5)
        self.assertIsNotNone(m_s5)
        s5_oid = int(m_s5.group(1))

        sa2_j = json.loads(cust.get(f"/customer/ajax/slot-availability.php?date={tomorrow}")[2])
        self.assertEqual(sa2_j["slots_by_time"]["14:15"]["remaining"], 0)
        self.assertTrue(sa2_j["slots_by_time"]["14:15"]["is_full"])

        # 6th booking for full slot must be rejected
        _, _, co_html = cust.get("/customer/checkout.php")
        _, u_s6, h_s6 = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 2, "qty": 1}]),
            "address_id": near_addr_id,
            "delivery_type": "schedule",
            "scheduled_date": tomorrow,
            "scheduled_time": "14:15",
            "scheduled_slot": "Lunch (02:00 PM - 02:30 PM)",
            "payment_mode": "COD",
        })
        self.assertNotIn("order-success.php", u_s6)
        self.assertIn("full", h_s6.lower())

        # Cancel 5th scheduled order -> slot remaining increments back to 1
        _, _, st_s5 = cust.get(f"/customer/order-status.php?order_id={s5_oid}")
        cust.post_form(f"/customer/order-status.php?order_id={s5_oid}", {
            "csrf_token": extract_csrf(st_s5),
            "action": "cancel_order",
            "order_id": s5_oid,
        })
        sa3_j = json.loads(cust.get(f"/customer/ajax/slot-availability.php?date={tomorrow}")[2])
        self.assertEqual(sa3_j["slots_by_time"]["14:15"]["remaining"], 1)

        # Near-term scheduled pre-alert test on Kitchen KDS
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "INSERT INTO orders (user_id, address_id, subtotal, delivery_fee, platform_fee, tax_amount, discount_amount, total_amount, order_status, is_scheduled, scheduled_for, scheduled_slot) "
                "VALUES (%s, %s, 249.00, 25.00, 5.00, 12.45, 0.00, 291.45, 'CONFIRMED', 1, DATE_ADD(NOW(), INTERVAL 20 MINUTE), 'Express Pre-Alert Window')",
                (athul["user_id"], near_addr_id),
            )
            pa_oid = cur.lastrowid
            cur.execute("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (%s, 2, 1, 249.00, 249.00)", (pa_oid,))
        kitchen = self._login_client("/kitchen/login.php", "chef@zippy.com")
        c, _, k_pa_html = kitchen.get("/kitchen/index.php")
        assert_html_clean(self, "KDS Pre-Alert Banner", c, k_pa_html, ("scheduledPreAlertBanner", f"#ZIP-{pa_oid}"))
        kpoll = json.loads(kitchen.get("/kitchen/ajax/poll-orders.php")[2])
        self.assertGreaterEqual(int(kpoll.get("pre_alerts", 0)), 1)

    def test_08_v2_kitchen_sos_tickets_and_sse_streams(self):
        cust = self._login_client("/customer/login.php", "athul@example.com")
        kitchen = self._login_client("/kitchen/login.php", "chef@zippy.com")
        rider = self._login_client("/delivery/login.php", "shaji@zippy.com")
        admin = self._login_client("/admin/login.php", "admin@zippy.com")

        # Place fresh COD order for Kitchen prep checklist -> Rider SOS -> Re-dispatch -> Breakdown -> OTP -> Loyalty Earn
        _, _, co_html = cust.get("/customer/checkout.php")
        c, u_o, h_o = cust.post_form("/customer/checkout.php", {
            "csrf_token": extract_csrf(co_html),
            "action": "process_order",
            "cart_json": json.dumps([{"id": 1, "qty": 1}, {"id": 5, "qty": 1}]),
            "address_id": 1,
            "payment_mode": "COD",
        })
        m_o = re.search(r"order-success\.php\?order_id=(\d+)", h_o + " " + u_o)
        self.assertIsNotNone(m_o)
        oid = int(m_o.group(1))

        # 1. Kitchen Dish-Level Prep Checklists (COOKING -> toggle-item-prepped -> READY)
        _, _, k_idx = kitchen.get("/kitchen/index.php")
        k_csrf = extract_csrf(k_idx)
        kitchen.post_json("/kitchen/ajax/update-status.php", {"order_id": oid, "target_status": "COOKING"}, {"X-CSRF-TOKEN": k_csrf})

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_item_id FROM order_items WHERE order_id = %s ORDER BY order_item_id ASC LIMIT 1", (oid,))
            oi_id = int(cur.fetchone()["order_item_id"])

        c, _, tp1 = kitchen.post_json(
            "/kitchen/ajax/toggle-item-prepped.php",
            {"order_item_id": oi_id, "is_prepped": 1, "csrf_token": k_csrf},
            {"X-CSRF-TOKEN": k_csrf},
        )
        self.assertEqual(c, 200)
        tp1_j = json.loads(tp1)
        self.assertTrue(tp1_j["success"] and tp1_j["is_prepped"] == 1 and tp1_j["prepped_count"] >= 1)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT is_prepped, prepped_at FROM order_items WHERE order_item_id = %s", (oi_id,))
            row_oi = cur.fetchone()
            self.assertEqual(row_oi["is_prepped"], 1)
            self.assertIsNotNone(row_oi["prepped_at"])

        kitchen.post_json("/kitchen/ajax/update-status.php", {"order_id": oid, "target_status": "READY"}, {"X-CSRF-TOKEN": k_csrf})

        # 2. Rider SOS ('unreachable' -> FAILED -> Admin Re-Dispatch -> 'breakdown' -> ON_BREAK -> Deliver + Loyalty EARNED)
        _, _, r_idx = rider.get("/delivery/index.php")
        r_csrf = extract_csrf(r_idx)
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf, "action": "toggle_duty", "duty": "1"})
        _, _, r_on = rider.get("/delivery/index.php")
        r_csrf = extract_csrf(r_on)
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf, "action": "claim_order", "order_id": oid})

        c, _, sos1 = rider.post_form("/delivery/index.php", {
            "csrf_token": r_csrf,
            "action": "report_issue",
            "order_id": oid,
            "reason_code": "unreachable",
            "notes": f"QA unreachable gate closed {self.ts}",
            "is_ajax": "1",
        }, {"X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": r_csrf})
        self.assertEqual(c, 200)
        self.assertTrue(json.loads(sos1)["success"])

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT delivery_status, failure_reason, issue_notes, rider_id FROM delivery_dispatches WHERE order_id = %s", (oid,))
            disp = cur.fetchone()
            self.assertEqual(disp["delivery_status"], "FAILED")
            self.assertIn("not answering", disp["failure_reason"])
            self.assertIn("QA unreachable gate closed", disp["issue_notes"])
            self.assertIsNone(disp["rider_id"])

        _, _, a_fail = admin.get("/admin/orders.php?status=FAILED")
        assert_html_clean(self, "Admin Failed Orders Tab", 200, a_fail, (f"#ZIP-{oid}", "RIDER SOS"))
        _, _, dv_fail = admin.get("/admin/delivery-view.php")
        assert_html_clean(self, "Admin Delivery SOS Triage", 200, dv_fail, (f"#ZIP-{oid}", "Re-Dispatch"))

        # Admin Re-Dispatches Failed order
        admin.post_form("/admin/orders.php?status=FAILED", {
            "csrf_token": extract_csrf(a_fail),
            "action": "requeue_failed",
            "order_id": oid,
        })
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_status FROM orders WHERE order_id = %s", (oid,))
            self.assertIn(cur.fetchone()["order_status"], ("READY", "OUT_FOR_DELIVERY"))

        # Force READY unassigned so Shaji claims and reports 'breakdown'
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("UPDATE orders SET order_status = 'READY' WHERE order_id = %s", (oid,))
            cur.execute("UPDATE delivery_dispatches SET rider_id = NULL, delivery_status = 'ASSIGNED' WHERE order_id = %s", (oid,))
            cur.execute("UPDATE staff_profiles SET duty_status = 'OFF_DUTY' WHERE department = 'DELIVERY' AND user_id != 6")

        _, _, r_idx2 = rider.get("/delivery/index.php")
        r_csrf2 = extract_csrf(r_idx2)
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf2, "action": "claim_order", "order_id": oid})
        c, _, sos_brk = rider.post_form("/delivery/index.php", {
            "csrf_token": r_csrf2,
            "action": "report_issue",
            "order_id": oid,
            "reason_code": "breakdown",
            "notes": "Flat rear tire",
            "is_ajax": "1",
        }, {"X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": r_csrf2})
        self.assertEqual(c, 200)
        self.assertTrue(json.loads(sos_brk)["requeued"])
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT order_status FROM orders WHERE order_id = %s", (oid,))
            self.assertEqual(cur.fetchone()["order_status"], "READY")
            cur.execute("SELECT duty_status FROM staff_profiles WHERE user_id = 6")
            self.assertEqual(cur.fetchone()["duty_status"], "ON_BREAK")

        # Put Shaji back ON_DUTY, claim, deliver via OTP & verify Loyalty Points EARNED
        _, _, r_idx3 = rider.get("/delivery/index.php")
        r_csrf3 = extract_csrf(r_idx3)
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf3, "action": "toggle_duty", "duty": "1"})
        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf3, "action": "claim_order", "order_id": oid})
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT delivery_otp FROM delivery_dispatches WHERE order_id = %s", (oid,))
            otp = cur.fetchone()["delivery_otp"]
            cur.execute("SELECT loyalty_points FROM users WHERE email = 'athul@example.com'")
            pts_pre_del = int(cur.fetchone()["loyalty_points"])

        rider.post_form("/delivery/index.php", {"csrf_token": r_csrf3, "action": "verify_otp", "order_id": oid, "otp": otp})
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT loyalty_points FROM users WHERE email = 'athul@example.com'")
            self.assertGreater(int(cur.fetchone()["loyalty_points"]), pts_pre_del)
            cur.execute("SELECT COUNT(*) AS cnt FROM loyalty_transactions WHERE order_id = %s AND txn_type = 'EARNED'", (oid,))
            self.assertEqual(cur.fetchone()["cnt"], 1)

        # 3. Admin Support Ticket Desk & Customer Follow-Up Thread
        _, _, h_html = cust.get("/customer/help.php")
        subj_marker = f"QA Threaded Issue {self.ts}"
        cust.post_form("/customer/help.php", {
            "csrf_token": extract_csrf(h_html),
            "action": "submit_ticket",
            "category": "missing_item",
            "order_id": f"#ZIP-{oid}",
            "description": subj_marker,
        })
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT ticket_id FROM support_tickets WHERE message LIKE %s ORDER BY ticket_id DESC LIMIT 1", (f"%{subj_marker}%",))
            tid = int(cur.fetchone()["ticket_id"])

        _, _, st_adm = admin.get("/admin/support-tickets.php")
        assert_html_clean(self, "Admin Support Desk", 200, st_adm, (f"#TKT-{tid}", subj_marker))
        admin_reply_txt = f"We refunded the missing item {self.ts}"
        _, _, st_rep = admin.post_form("/admin/support-tickets.php", {
            "csrf_token": extract_csrf(st_adm),
            "action": "reply_ticket",
            "ticket_id": tid,
            "reply_message": admin_reply_txt,
            "ticket_status": "IN_PROGRESS",
        })
        assert_html_clean(self, "Admin Ticket Reply", 200, st_rep, (admin_reply_txt, "IN_PROGRESS"))

        _, _, cust_h2 = cust.get("/customer/help.php")
        assert_html_clean(self, "Customer Ticket Thread View", 200, cust_h2, (admin_reply_txt,))
        cust_reply_txt = f"Received thank you {self.ts}"
        _, _, cust_h3 = cust.post_form("/customer/help.php", {
            "csrf_token": extract_csrf(cust_h2),
            "action": "reply_ticket",
            "ticket_id": tid,
            "message": cust_reply_txt,
        })
        assert_html_clean(self, "Customer Follow-Up Reply", 200, cust_h3, (cust_reply_txt,))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS cnt FROM support_ticket_replies WHERE ticket_id = %s", (tid,))
            self.assertEqual(cur.fetchone()["cnt"], 2)

        # 4. True Server-Sent Events (SSE) Push Stream Verification
        c_unauth, _, _ = self.guest.get("/api/sse-stream.php?portal=kitchen&once=1")
        self.assertEqual(c_unauth, 401)
        c_forbid, _, _ = cust.get("/api/sse-stream.php?portal=admin&once=1")
        self.assertEqual(c_forbid, 403)

        for client, path, expected_evt in (
            (kitchen, "/api/sse-stream.php?portal=kitchen&once=1", "event: kds_update"),
            (rider, "/api/sse-stream.php?portal=delivery&once=1", "event: delivery_update"),
            (admin, "/api/sse-stream.php?portal=admin&once=1", "event: admin_update"),
            (cust, f"/api/sse-stream.php?portal=customer&order_id={oid}&once=1", "event: order_update"),
        ):
            sc, _, sbody = client.get(path)
            self.assertEqual(sc, 200, f"SSE failed for {path}")
            self.assertIn("event: connected", sbody)
            self.assertIn(expected_evt, sbody)


if __name__ == "__main__":
    unittest.main(verbosity=2)
