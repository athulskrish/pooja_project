"""
Automated Headless Chrome Multi-Portal UI & Interactive E2E Suite
Zippy Food Ordering Platform | MCSP-232 Major Project

Interactive Selenium suite verifying every portal via real browser logins,
clicks, modals, client-side JS state, and zero SEVERE console/network errors:
  1. Static pages, unauthenticated guards & negative password checks on all 4 portals
  2. Customer signup, 2-step offline security-question reset, profile & address modal CRUD
  3. Storefront toggles, Offers, Search, Help FAQ/ticket, Dish Reviews modal,
     Customization modal, Cart + coupons, Razorpay simulation modal, 60s cancel,
     Advance Scheduled COD order, Standard COD order & Order History tabs + Reorder
  4. Kitchen KDS UI transitions, 86-List modal toggle, History/Salary/Payslip modals & KOT
  5. Multi-rider FCFS claim, GPS route simulation modal, Wrong/Right OTP handshake,
     IMPS Cashout modal, Customer post-delivery star rating & SAC 996331 invoice
  6. Admin Console: all 14 pages, Command Palette search, Order GPS/Status modals,
     Dishes bulk toolbar, Customization group lifecycle, FAQ ops, self-demotion guard,
     COD cash collection, kitchen duty toggle, fleet salary payout & logout guard
"""

from __future__ import annotations

import json
import os
import re
import sys
import time
import unittest

from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select, WebDriverWait

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from e2e_test_helpers import (  # noqa: E402
    BASE_URL,
    accept_alert_if_present,
    assert_no_severe_console_errors,
    assert_page_clean_in_browser,
    db_conn,
    extract_csrf,
    js_click,
    make_headless_chrome,
    seed_clean_database,
)


class TestChromeAllPortalsE2E(unittest.TestCase):
    """End-to-end headless Chrome UI suite spanning all 4 portals."""

    @classmethod
    def setUpClass(cls):
        seed_clean_database()
        cls.ts = int(time.time())
        cls.driver = make_headless_chrome()
        cls.wait = WebDriverWait(cls.driver, 10)
        cls.order_cod = None

    @classmethod
    def tearDownClass(cls):
        try:
            cls.driver.quit()
        finally:
            seed_clean_database()

    def _login_portal(self, login_path: str, email: str, password: str, target_substr: str,
                      email_sel: str = "input[name='email']", pass_sel: str = "input[name='password']") -> None:
        self.driver.get(f"{BASE_URL}{login_path}")
        em = self.driver.find_element(By.CSS_SELECTOR, email_sel)
        pw = self.driver.find_element(By.CSS_SELECTOR, pass_sel)
        em.clear()
        em.send_keys(email)
        pw.clear()
        pw.send_keys(password)
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(lambda d: target_substr in d.current_url)

    def test_01_guards_utility_pages_and_invalid_logins(self):
        for path, must in (
            ("/index.php", ("Customer Storefront", "Kitchen", "Delivery", "Admin")),
            ("/customer/privacy.php", ("Privacy",)),
            ("/customer/terms.php", ("Terms",)),
            ("/customer/404.php", ("404",)),
        ):
            self.driver.get(f"{BASE_URL}{path}")
            assert_page_clean_in_browser(self, self.driver, f"Static {path}", must)

        for guard_path, login_substr in (
            ("/customer/profile.php", "customer/login.php"),
            ("/customer/manage-addresses.php", "customer/login.php"),
            ("/customer/checkout.php", "customer/login.php"),
            ("/kitchen/index.php", "kitchen/login.php"),
            ("/delivery/index.php", "delivery/login.php"),
            ("/admin/index.php", "admin/login.php"),
        ):
            self.driver.get(f"{BASE_URL}{guard_path}")
            self.assertIn(login_substr, self.driver.current_url)
            assert_page_clean_in_browser(self, self.driver, f"Guard {guard_path}")

        for lp, email_sel, pass_sel, email in (
            ("/customer/login.php", "#loginEmail", "#loginPassword", "athul@example.com"),
            ("/kitchen/login.php", "input[name='email']", "input[name='password']", "chef@zippy.com"),
            ("/delivery/login.php", "input[name='email']", "input[name='password']", "shaji@zippy.com"),
            ("/admin/login.php", "input[name='email']", "input[name='password']", "admin@zippy.com"),
        ):
            self.driver.get(f"{BASE_URL}{lp}")
            em = self.driver.find_element(By.CSS_SELECTOR, email_sel)
            pw = self.driver.find_element(By.CSS_SELECTOR, pass_sel)
            em.clear()
            em.send_keys(email)
            pw.clear()
            pw.send_keys("WrongPass!999")
            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
            self.wait.until(lambda d: lp.split("/")[-1] in d.current_url)
            assert_page_clean_in_browser(self, self.driver, f"Bad login {lp}")
            src = self.driver.page_source
            self.assertTrue("Invalid" in src or "incorrect" in src or "alert-danger" in src)

        assert_no_severe_console_errors(self, self.driver, "test_01")

    def test_02_customer_signup_forgot_password_profile_and_address_crud(self):
        qa_email = f"qa_ui_{self.ts}@example.com"
        self.driver.get(f"{BASE_URL}/customer/signup.php")
        self.driver.find_element(By.NAME, "full_name").send_keys(f"QA Browser {self.ts}")
        self.driver.find_element(By.NAME, "email").send_keys(qa_email)
        self.driver.find_element(By.NAME, "phone_number").send_keys(f"98765{str(self.ts)[-5:]}")
        self.driver.find_element(By.NAME, "password").send_keys("QaPass@123")
        Select(self.driver.find_element(By.NAME, "security_question")).select_by_index(1)
        self.driver.find_element(By.NAME, "security_answer").send_keys("zippy")
        self.driver.find_element(By.NAME, "address_line").send_keys("101 Test Avenue, Indiranagar")
        pin = self.driver.find_element(By.NAME, "pincode")
        pin.clear()
        pin.send_keys("560038")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(lambda d: "customer/index.php" in d.current_url)
        assert_page_clean_in_browser(self, self.driver, "Signup Home", ("Explore",))

        # Logout -> 2-step offline Forgot Password (wrong then right answer)
        self.driver.get(f"{BASE_URL}/customer/logout.php")
        self.driver.get(f"{BASE_URL}/customer/forgot-password.php")
        self.driver.find_element(By.NAME, "email").send_keys(qa_email)
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(EC.presence_of_element_located((By.NAME, "security_answer")))

        self.driver.find_element(By.NAME, "security_answer").send_keys("wrong_secret")
        self.driver.find_element(By.NAME, "new_password").send_keys("NewPass@2026")
        self.driver.find_element(By.NAME, "confirm_password").send_keys("NewPass@2026")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(EC.presence_of_element_located((By.NAME, "security_answer")))
        self.assertIn("alert-danger", self.driver.page_source)

        sa = self.driver.find_element(By.NAME, "security_answer")
        sa.clear()
        sa.send_keys("zippy")
        np = self.driver.find_element(By.NAME, "new_password")
        np.clear()
        np.send_keys("NewPass@2026")
        cp = self.driver.find_element(By.NAME, "confirm_password")
        cp.clear()
        cp.send_keys("NewPass@2026")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(lambda d: "reset" in d.page_source.lower() or "login.php" in d.current_url)

        self._login_portal("/customer/login.php", qa_email, "NewPass@2026", "customer/index.php", "#loginEmail", "#loginPassword")

        # Profile + security question update
        self.driver.get(f"{BASE_URL}/customer/profile.php")
        assert_page_clean_in_browser(self, self.driver, "Customer Profile")
        fn = self.driver.find_element(By.NAME, "full_name")
        fn.clear()
        fn.send_keys(f"QA Updated {self.ts}")
        js_click(self.driver, self.driver.find_element(By.XPATH, "//form[.//input[@name='action' and @value='update_profile']]//button[@type='submit']"))
        self.wait.until(lambda d: f"QA Updated {self.ts}" in d.page_source)

        sec_form = self.driver.find_element(By.XPATH, "//form[.//input[@name='action' and @value='update_security_question']]")
        Select(sec_form.find_element(By.NAME, "security_question")).select_by_index(2)
        sec_form.find_element(By.NAME, "security_answer").send_keys("biryani2026")
        js_click(self.driver, sec_form.find_element(By.CSS_SELECTOR, "button[type='submit']"))
        self.wait.until(lambda d: "profile.php" in d.current_url)

        # Manage addresses add / set default / delete with native confirm dialog
        self.driver.get(f"{BASE_URL}/customer/manage-addresses.php")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "[data-bs-target='#addAddressModal']"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "addAddressModal")))
        modal = self.driver.find_element(By.ID, "addAddressModal")
        js_click(self.driver, modal.find_element(By.ID, "typeWork"))
        modal.find_element(By.NAME, "address_line").send_keys(f"Tech Park Tower {self.ts}")
        pin = modal.find_element(By.NAME, "pincode")
        pin.clear()
        pin.send_keys("560103")
        js_click(self.driver, modal.find_element(By.CSS_SELECTOR, "button[type='submit']"))
        self.wait.until(lambda d: f"Tech Park Tower {self.ts}" in d.page_source)

        new_card = self.driver.find_element(By.XPATH, f"//div[contains(@class,'card') and .//div[contains(text(),'Tech Park Tower {self.ts}')]]")
        def_btns = new_card.find_elements(By.XPATH, ".//form[.//input[@name='action' and @value='set_default']]//button[@type='submit']")
        if def_btns:
            js_click(self.driver, def_btns[0])
            self.wait.until(lambda d: f"Tech Park Tower {self.ts}" in d.page_source)

        new_card = self.driver.find_element(By.XPATH, f"//div[contains(@class,'card') and .//div[contains(text(),'Tech Park Tower {self.ts}')]]")
        js_click(self.driver, new_card.find_element(By.XPATH, ".//form[.//input[@name='action' and @value='delete_address']]//button[@type='submit']"))
        accept_alert_if_present(self.driver)
        self.wait.until(lambda d: f"Tech Park Tower {self.ts}" not in d.page_source)

        self.driver.get(f"{BASE_URL}/customer/logout.php")
        assert_no_severe_console_errors(self, self.driver, "test_02")

    def test_03_customer_storefront_reviews_cart_razorpay_cancel_and_reorder(self):
        self._login_portal("/customer/login.php", "athul@example.com", "password123", "customer/index.php", "#loginEmail", "#loginPassword")
        assert_page_clean_in_browser(self, self.driver, "Storefront Home")
        js_click(self.driver, self.driver.find_element(By.ID, "vegOnlyToggle"))
        js_click(self.driver, self.driver.find_element(By.ID, "healthyOnlyToggle"))
        js_click(self.driver, self.driver.find_element(By.ID, "healthyOnlyToggle"))
        js_click(self.driver, self.driver.find_element(By.ID, "vegOnlyToggle"))

        self.driver.get(f"{BASE_URL}/customer/offers.php")
        assert_page_clean_in_browser(self, self.driver, "Offers", ("LUSHFOREST",))
        self.driver.get(f"{BASE_URL}/customer/search.php?q=Biryani&mood=party")
        assert_page_clean_in_browser(self, self.driver, "Search", ("Meghana Special Chicken Biryani",))

        self.driver.get(f"{BASE_URL}/customer/help.php")
        acc = self.driver.find_elements(By.CSS_SELECTOR, "#faqAccordion .accordion-button")
        if acc:
            js_click(self.driver, acc[0])
        help_form = self.driver.find_element(By.XPATH, "//form[.//textarea[@name='description']]")
        Select(help_form.find_element(By.NAME, "category")).select_by_index(1)
        help_form.find_element(By.NAME, "description").send_keys(f"Chrome UI help ticket {self.ts}")
        js_click(self.driver, help_form.find_element(By.CSS_SELECTOR, "button[type='submit']"))
        self.wait.until(lambda d: "ticket" in d.page_source.lower() or "submitted" in d.page_source.lower())

        # Restaurant filters, dish reviews modal & customization modal
        self.driver.get(f"{BASE_URL}/customer/restaurant.php")
        js_click(self.driver, self.driver.find_element(By.ID, "vegOnlySwitch"))
        js_click(self.driver, self.driver.find_element(By.ID, "healthyOnlySwitch"))
        js_click(self.driver, self.driver.find_element(By.ID, "healthyOnlySwitch"))
        js_click(self.driver, self.driver.find_element(By.ID, "vegOnlySwitch"))

        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, ".open-dish-reviews-btn"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "dishReviewsModal")))
        self.wait.until(lambda d: len(d.find_element(By.ID, "dishReviewsFeed").text.strip()) > 0)
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#dishReviewsModal .btn-close"))
        time.sleep(0.3)

        self.driver.execute_script("localStorage.removeItem('pooja_zippy_cart'); localStorage.removeItem('pooja_active_coupon');")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, ".dish-qty-control-slot[data-dish-id='1'] button"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "customizationModal")))
        self.wait.until(EC.visibility_of_element_located((By.ID, "custModalGroupsContainer")))
        js_click(self.driver, self.driver.find_element(By.ID, "custModalAddToCartBtn"))
        time.sleep(0.4)

        # Cart page + coupon validation
        self.driver.get(f"{BASE_URL}/customer/cart.php")
        self.wait.until(EC.visibility_of_element_located((By.ID, "dedicatedCartItemsList")))
        assert_page_clean_in_browser(self, self.driver, "Cart", ("Meghana Special Chicken Biryani",))
        self.driver.find_element(By.ID, "cartCouponInput").send_keys("INVALID123")
        js_click(self.driver, self.driver.find_element(By.ID, "applyCouponBtn"))
        time.sleep(0.25)
        self.assertIn("Invalid coupon", self.driver.find_element(By.ID, "couponMessage").text)

        qty_plus = self.driver.find_elements(By.XPATH, "//div[@id='dedicatedCartItemsList']//button[contains(.,'+')]")
        if qty_plus:
            js_click(self.driver, qty_plus[0])
            time.sleep(0.25)
        ci = self.driver.find_element(By.ID, "cartCouponInput")
        ci.clear()
        ci.send_keys("LUSHFOREST")
        js_click(self.driver, self.driver.find_element(By.ID, "applyCouponBtn"))
        time.sleep(0.25)
        self.assertIn("LUSHFOREST", self.driver.find_element(By.ID, "couponMessage").text)

        # Razorpay modal checkout + failure banner + 1-click success -> cancel within 60s
        self.driver.get(f"{BASE_URL}/customer/checkout.php")
        self.wait.until(EC.presence_of_element_located((By.ID, "checkoutSubmitBtn")))
        js_click(self.driver, self.driver.find_element(By.ID, "checkoutSubmitBtn"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "razorpayDummyModal")))
        js_click(self.driver, self.driver.find_element(By.ID, "rzpSimulateFailBtn"))
        self.wait.until(lambda d: "d-none" not in d.find_element(By.ID, "rzpFailureBanner").get_attribute("class"))
        js_click(self.driver, self.driver.find_element(By.ID, "rzpQuickSuccessBtn"))
        self.wait.until(lambda d: "order-success.php" in d.current_url)
        order_upi = int(re.search(r"order_id=(\d+)", self.driver.current_url).group(1))

        self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={order_upi}")
        assert_page_clean_in_browser(self, self.driver, "Order Status Pre-Cancel", ("MINS REMAINING", "btnCancelOrder"))
        js_click(self.driver, self.driver.find_element(By.ID, "btnCancelOrder"))
        accept_alert_if_present(self.driver)
        self.wait.until(lambda d: "CANCELLED" in d.page_source and "CLOSED" in d.page_source)

        # Advance Scheduled COD Order
        self.driver.execute_script(
            "localStorage.setItem('pooja_zippy_cart', JSON.stringify([{id: 2, item_name: 'Hyderabadi Veg Dum Biryani', price: 249, qty: 1, image_url: 'assets/images/menu/veg_biryani.jpg', is_veg: 1}]));"
        )
        self.driver.get(f"{BASE_URL}/customer/checkout.php")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "input[name='delivery_type'][value='schedule']"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "advanceScheduleBox")))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "input[name='payment_mode'][value='COD']"))
        js_click(self.driver, self.driver.find_element(By.ID, "checkoutSubmitBtn"))
        self.wait.until(lambda d: "order-success.php" in d.current_url)
        order_sched = int(re.search(r"order_id=(\d+)", self.driver.current_url).group(1))
        self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={order_sched}")
        assert_page_clean_in_browser(self, self.driver, "Scheduled Tracker", ("ADVANCE PRE-ORDER",))

        # Standard COD order for Kitchen -> Delivery -> Rating
        self.driver.execute_script(
            "localStorage.setItem('pooja_zippy_cart', JSON.stringify([{id: 1, item_name: 'Meghana Special Chicken Biryani', price: 319, qty: 1, image_url: 'assets/images/menu/chicken_biryani.jpg', is_veg: 0}, {id: 5, item_name: 'Paneer Butter Masala', price: 279, qty: 1, image_url: 'assets/images/menu/paneer_butter_masala.jpg', is_veg: 1}]));"
        )
        self.driver.get(f"{BASE_URL}/customer/checkout.php")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "input[name='payment_mode'][value='COD']"))
        js_click(self.driver, self.driver.find_element(By.ID, "checkoutSubmitBtn"))
        self.wait.until(lambda d: "order-success.php" in d.current_url)
        order_cod = int(re.search(r"order_id=(\d+)", self.driver.current_url).group(1))
        type(self).order_cod = order_cod

        # Order history tabs, search & One-Tap Reorder
        self.driver.get(f"{BASE_URL}/customer/order-history.php")
        assert_page_clean_in_browser(self, self.driver, "Order History", (f"#ZIP-{order_cod}", f"#ZIP-{order_upi}"))
        for tab_name in ("ongoing", "scheduled", "completed", "cancelled", "all"):
            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, f"#orderHistoryTabs button[data-tab-name='{tab_name}']"))
            time.sleep(0.15)
        s_inp = self.driver.find_element(By.ID, "orderSearchInput")
        s_inp.send_keys(str(order_cod))
        s_inp.clear()
        s_inp.send_keys(Keys.BACKSPACE)

        reorder_btns = self.driver.find_elements(By.XPATH, "//button[contains(@onclick,'oneTapReorder') or contains(.,'Reorder')]")
        self.assertGreaterEqual(len(reorder_btns), 1)
        js_click(self.driver, reorder_btns[0])
        time.sleep(0.35)
        cart_reordered = json.loads(self.driver.execute_script("return localStorage.getItem('pooja_zippy_cart') || '[]';"))
        self.assertGreaterEqual(len(cart_reordered), 1)
        assert_no_severe_console_errors(self, self.driver, "test_03")

    def test_04_kitchen_kds_lanes_86_modal_tabs_payslip_and_kot(self):
        order_cod = type(self).order_cod
        self._login_portal("/kitchen/login.php", "chef@zippy.com", "password123", "kitchen/index.php")
        assert_page_clean_in_browser(self, self.driver, "Kitchen KDS", (f"#ZIP-{order_cod}",))

        new_ticket = self.driver.find_element(By.CSS_SELECTOR, f"#laneNewContainer .ticket-item[data-ticket-id='{order_cod}']")
        js_click(self.driver, new_ticket.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, f"#laneCookingContainer .ticket-item[data-ticket-id='{order_cod}']")))

        cook_ticket = self.driver.find_element(By.CSS_SELECTOR, f"#laneCookingContainer .ticket-item[data-ticket-id='{order_cod}']")
        js_click(self.driver, cook_ticket.find_element(By.CSS_SELECTOR, "form button[type='submit']"))
        self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, f"#laneReadyContainer .ticket-item[data-ticket-id='{order_cod}']")))

        # 86-List Modal toggle OFF -> ON
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, ".btn-kds-86"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "modal86List")))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "tr.dish-86-row button[onclick*='toggleStockItem(25,']"))
        self.wait.until(lambda d: "86 / SOLD OUT" in d.find_element(By.ID, "stockLabel_25").text)
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "tr.dish-86-row button[onclick*='toggleStockItem(25,']"))
        self.wait.until(lambda d: "IN STOCK" in d.find_element(By.ID, "stockLabel_25").text)
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#modal86List .btn-close"))
        time.sleep(0.25)

        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "[data-bs-target='#tab-scheduled']"))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "[data-bs-target='#tab-history']"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "kdsHistorySearchInput")))
        self.driver.find_element(By.ID, "kdsHistorySearchInput").send_keys("100")

        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "[data-bs-target='#tab-salary']"))
        time.sleep(0.25)
        payslip_btns = self.driver.find_elements(By.CSS_SELECTOR, "[data-bs-target^='#payslipModal']")
        if payslip_btns:
            target_ps = payslip_btns[0].get_attribute("data-bs-target")
            js_click(self.driver, payslip_btns[0])
            self.wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, target_ps)))
            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, f"{target_ps} .btn-close"))

        self.driver.get(f"{BASE_URL}/kitchen/kot.php?order_id={order_cod}")
        assert_page_clean_in_browser(self, self.driver, "Kitchen KOT", (f"#ZIP-{order_cod}",))
        assert_no_severe_console_errors(self, self.driver, "test_04")

    def _deliver_fcfs_until_target(self, target_id=None) -> bool:
        for _ in range(5):
            otp_btns = self.driver.find_elements(By.CSS_SELECTOR, "button[onclick*='openOtpModal(']")
            if not otp_btns:
                claim_btns = self.driver.find_elements(By.CSS_SELECTOR, "button[id^='btnClaimOrder']")
                if not claim_btns:
                    return False
                js_click(self.driver, claim_btns[0])
                self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button[onclick*='openOtpModal(']")))
                otp_btns = self.driver.find_elements(By.CSS_SELECTOR, "button[onclick*='openOtpModal(']")

            onclick = otp_btns[0].get_attribute("onclick") or ""
            active_oid = int(re.search(r"openOtpModal\((\d+)", onclick).group(1))
            with db_conn() as conn, conn.cursor() as cur:
                cur.execute("SELECT delivery_otp FROM delivery_dispatches WHERE order_id = %s", (active_oid,))
                active_otp = cur.fetchone()["delivery_otp"]

            if target_id is None or active_oid == target_id:
                gps_btns = self.driver.find_elements(By.CSS_SELECTOR, f"button[onclick*='openGpsModal({active_oid},']")
                if gps_btns:
                    js_click(self.driver, gps_btns[0])
                    self.wait.until(EC.visibility_of_element_located((By.ID, "gpsMapModal")))
                    js_click(self.driver, self.driver.find_element(By.ID, "btnSimulateRideStep"))
                    time.sleep(0.4)
                    js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#gpsMapModal .btn-close"))
                    time.sleep(0.25)

                js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, f"button[onclick*='openOtpModal({active_oid},']"))
                self.wait.until(EC.visibility_of_element_located((By.ID, "otpVerifyModal")))
                chk = self.driver.find_element(By.ID, "checkCashReceived")
                if chk.is_displayed() and not chk.is_selected():
                    js_click(self.driver, chk)
                inp = self.driver.find_element(By.ID, "inputOtp")
                inp.clear()
                inp.send_keys("000000")
                js_click(self.driver, self.driver.find_element(By.ID, "btnOtpSubmit"))
                self.wait.until(lambda d: "Invalid" in d.page_source or "incorrect" in d.page_source.lower() or "alert-danger" in d.page_source)

            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, f"button[onclick*='openOtpModal({active_oid},']"))
            self.wait.until(EC.visibility_of_element_located((By.ID, "otpVerifyModal")))
            chk = self.driver.find_element(By.ID, "checkCashReceived")
            if chk.is_displayed() and not chk.is_selected():
                js_click(self.driver, chk)
            inp = self.driver.find_element(By.ID, "inputOtp")
            inp.clear()
            inp.send_keys(active_otp)
            js_click(self.driver, self.driver.find_element(By.ID, "btnOtpSubmit"))
            self.wait.until(lambda d: len(d.find_elements(By.CSS_SELECTOR, f"button[onclick*='openOtpModal({active_oid},']")) == 0)

            if target_id is None or active_oid == target_id:
                return True
        return False

    def test_05_delivery_fcfs_multi_rider_gps_otp_cashout_rating_and_invoice(self):
        order_cod = type(self).order_cod
        self._login_portal("/delivery/login.php", "shaji@zippy.com", "password123", "delivery/index.php")
        assert_page_clean_in_browser(self, self.driver, "Delivery Fleet Index", ("Shaji",))

        if "OFFLINE" in self.driver.find_element(By.ID, "dutyStatusHeading").text.upper():
            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#dutyToggleForm input[type='checkbox']"))
            time.sleep(0.4)

        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "SELECT u.email FROM delivery_dispatches d JOIN users u ON d.rider_id = u.user_id WHERE d.order_id = %s",
                (order_cod,),
            )
            assigned = cur.fetchone()

        if not assigned or assigned["email"] == "shaji@zippy.com":
            delivered = self._deliver_fcfs_until_target(order_cod)
        else:
            self.assertTrue(self._deliver_fcfs_until_target(None))
            self.driver.get(f"{BASE_URL}/delivery/logout.php")
            self._login_portal("/delivery/login.php", assigned["email"], "password123", "delivery/index.php")
            if "OFFLINE" in self.driver.find_element(By.ID, "dutyStatusHeading").text.upper():
                js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#dutyToggleForm input[type='checkbox']"))
                time.sleep(0.4)
            delivered = self._deliver_fcfs_until_target(order_cod)

        self.assertTrue(delivered)

        js_click(self.driver, self.driver.find_element(By.ID, "tabBtnHistory"))
        self.assertIn(f"#ZIP-{order_cod}", self.driver.find_element(By.ID, "viewHistory").text)
        js_click(self.driver, self.driver.find_element(By.ID, "tabBtnEarnings"))
        js_click(self.driver, self.driver.find_element(By.ID, "btnCashOutMain"))
        self.wait.until(EC.visibility_of_element_located((By.ID, "cashOutModal")))
        js_click(self.driver, self.driver.find_element(By.ID, "btnConfirmCashOut"))
        self.wait.until(lambda d: "d-none" not in d.find_element(By.ID, "cashOutSuccessView").get_attribute("class"))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#cashOutSuccessView button[data-bs-dismiss='modal']"))
        time.sleep(0.25)

        # Customer post-delivery star rating + GST invoice
        self.driver.get(f"{BASE_URL}/customer/rate-order.php?order_id={order_cod}")
        assert_page_clean_in_browser(self, self.driver, "Customer Rate Order", ("Overall Food Quality",))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#foodStars .star-icon[data-value='4']"))
        r_stars = self.driver.find_elements(By.CSS_SELECTOR, "#riderStars .star-icon[data-value='5']")
        if r_stars:
            js_click(self.driver, r_stars[0])
        c_box = self.driver.find_elements(By.NAME, "comments")
        if c_box:
            c_box[0].send_keys(f"Fresh hot delivery in Chrome E2E {self.ts}")
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#orderRatingForm button[type='submit']"))
        self.wait.until(lambda d: "thank" in d.page_source.lower() or "saved" in d.page_source.lower() or "rating" in d.page_source.lower())

        self.driver.get(f"{BASE_URL}/customer/invoice.php?order_id={order_cod}")
        assert_page_clean_in_browser(self, self.driver, "Customer Invoice", ("996331", f"#ZIP-{order_cod}"))
        assert_no_severe_console_errors(self, self.driver, "test_05")

    def test_06_admin_all_pages_command_palette_bulk_customizations_and_finance(self):
        order_cod = type(self).order_cod
        self._login_portal("/admin/login.php", "admin@zippy.com", "password123", "admin/index.php")
        assert_page_clean_in_browser(self, self.driver, "Admin Dashboard", ("Prioritized triage",))

        # Command Palette live search
        self.driver.execute_script("if (typeof openCommandPalette === 'function') openCommandPalette();")
        self.wait.until(EC.visibility_of_element_located((By.ID, "commandPaletteModal")))
        pal = self.wait.until(EC.element_to_be_clickable((By.ID, "paletteSearchInput")))
        pal.send_keys("Biryani")
        self.wait.until(lambda d: "Biryani" in d.find_element(By.ID, "paletteResultsContainer").text)
        self.driver.execute_script("if (typeof paletteModalInstance !== 'undefined' && paletteModalInstance) paletteModalInstance.hide();")
        time.sleep(0.3)

        # Orders live search, payment mode filter, GPS modal & status modal update
        self.driver.get(f"{BASE_URL}/admin/orders.php")
        assert_page_clean_in_browser(self, self.driver, "Admin Orders")
        self.driver.find_element(By.ID, "orderLiveSearchInput").send_keys(str(order_cod))
        self.driver.find_element(By.ID, "orderLiveSearchInput").clear()
        Select(self.driver.find_element(By.ID, "paymentModeFilter")).select_by_value("COD")
        Select(self.driver.find_element(By.ID, "paymentModeFilter")).select_by_value("")

        gps_btns = self.driver.find_elements(By.CSS_SELECTOR, "button[onclick*='openAdminGpsModal(']")
        if gps_btns:
            js_click(self.driver, gps_btns[0])
            self.wait.until(EC.visibility_of_element_located((By.ID, "adminGpsTrackingModal")))
            js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#adminGpsTrackingModal .btn-close"))
            time.sleep(0.25)

        sm_btns = self.driver.find_elements(By.CSS_SELECTOR, "button[data-bs-target='#statusModal1001']")
        if sm_btns:
            js_click(self.driver, sm_btns[0])
            self.wait.until(EC.visibility_of_element_located((By.ID, "statusModal1001")))
            sm = self.driver.find_element(By.ID, "statusModal1001")
            Select(sm.find_element(By.NAME, "new_status")).select_by_value("COOKING")
            js_click(self.driver, sm.find_element(By.CSS_SELECTOR, "button[type='submit']"))
            self.wait.until(lambda d: "admin/orders.php" in d.current_url)
            with db_conn() as conn, conn.cursor() as cur:
                cur.execute("SELECT order_status FROM orders WHERE order_id = 1001")
                self.assertEqual(cur.fetchone()["order_status"], "COOKING")

        # Dishes bulk selection toolbar -> mark_soldout -> reload -> mark_available
        self.driver.get(f"{BASE_URL}/admin/dishes.php")
        assert_page_clean_in_browser(self, self.driver, "Admin Dishes")
        chks = self.driver.find_elements(By.CSS_SELECTOR, ".dish-row-checkbox")
        self.assertGreaterEqual(len(chks), 2)
        js_click(self.driver, chks[0])
        js_click(self.driver, chks[1])
        self.wait.until(lambda d: "show" in d.find_element(By.ID, "bulkActionBar").get_attribute("class"))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#bulkActionBar button[onclick*=\"mark_soldout\"]"))
        time.sleep(1.3)
        chks = self.driver.find_elements(By.CSS_SELECTOR, ".dish-row-checkbox")
        js_click(self.driver, chks[0])
        js_click(self.driver, chks[1])
        self.wait.until(lambda d: "show" in d.find_element(By.ID, "bulkActionBar").get_attribute("class"))
        js_click(self.driver, self.driver.find_element(By.CSS_SELECTOR, "#bulkActionBar button[onclick*=\"mark_available\"]"))
        time.sleep(1.3)

        # Visit all remaining admin console pages
        for admin_path, expected in (
            ("/admin/categories.php", ("Categories",)),
            ("/admin/dish-customizations.php?item_id=1", ("Customisations",)),
            ("/admin/moods.php", ("Food Moods",)),
            ("/admin/faqs.php", ("FAQ",)),
            ("/admin/reviews.php", ("Reviews",)),
            ("/admin/reviews.php?tab=dishes", ("Dish",)),
            ("/admin/audit-logs.php", ("Audit Trail",)),
            ("/admin/audit-logs.php?status=DELIVERED&date_preset=TODAY", ("Audit Trail",)),
            ("/admin/kitchen-view.php", ("KITCHEN ROSTER", "Sajeev")),
            ("/admin/delivery-view.php", ("DELIVERY FLEET", "Shaji")),
            ("/admin/financial-transactions.php", ("Financial", "Z-Report")),
            ("/admin/users.php", ("RBAC", "Pooja")),
            ("/admin/reports.php", ("Executive BI Reports", "Performance Breakdown Ledger")),
            ("/admin/reports.php?period=30d", ("Executive BI Reports", "Performance Breakdown Ledger")),
        ):
            self.driver.get(f"{BASE_URL}{admin_path}")
            assert_page_clean_in_browser(self, self.driver, f"Admin {admin_path}", expected)

        # Customization group add / edit / soft-delete
        self.driver.get(f"{BASE_URL}/admin/dish-customizations.php?item_id=2")
        dc_csrf = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf, name] = arguments;
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('action', 'add_group');
            fd.append('item_id', '2');
            fd.append('group_name', name);
            fd.append('selection_type', 'radio');
            fd.append('display_order', '9');
            fetch('dish-customizations.php?item_id=2', {method:'POST', body: fd}).then(r => window.__dcRes = r.status);
        """, dc_csrf, f"QA Group {self.ts}")
        self.wait.until(lambda d: d.execute_script("return window.__dcRes;") == 200)
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT group_id FROM dish_customization_groups WHERE group_name = %s", (f"QA Group {self.ts}",))
            gid = cur.fetchone()["group_id"]

        self.driver.get(f"{BASE_URL}/admin/dish-customizations.php?item_id=2")
        dc_csrf2 = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf, gid, newName] = arguments;
            (async () => {
              const fd1 = new FormData();
              fd1.append('csrf_token', csrf);
              fd1.append('action', 'edit_group');
              fd1.append('item_id', '2');
              fd1.append('group_id', gid);
              fd1.append('group_name', newName);
              fd1.append('selection_type', 'checkbox');
              fd1.append('display_order', '10');
              await fetch('dish-customizations.php?item_id=2', {method:'POST', body: fd1});

              const fd2 = new FormData();
              fd2.append('csrf_token', csrf);
              fd2.append('action', 'delete_group');
              fd2.append('item_id', '2');
              fd2.append('group_id', gid);
              await fetch('dish-customizations.php?item_id=2', {method:'POST', body: fd2});
              window.__dcDone = true;
            })();
        """, dc_csrf2, str(gid), f"QA Group Edited {self.ts}")
        self.wait.until(lambda d: d.execute_script("return window.__dcDone === true;"))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT group_name, is_active FROM dish_customization_groups WHERE group_id = %s", (gid,))
            row = cur.fetchone()
            self.assertEqual(row["group_name"], f"QA Group Edited {self.ts}")
            self.assertEqual(row["is_active"], 0)

        # FAQ duplicate & reorder
        self.driver.get(f"{BASE_URL}/admin/faqs.php")
        faq_csrf = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf] = arguments;
            (async () => {
              const post = (p) => {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                Object.entries(p).forEach(([k, v]) => fd.append(k, String(v)));
                return fetch('faqs.php', {method:'POST', body: fd});
              };
              await post({action:'duplicate_faq', faq_id: 1});
              await post({action:'reorder_faq', faq_id: 1, direction: 'down'});
              await post({action:'reorder_faq', faq_id: 1, direction: 'up'});
              window.__faqDone = true;
            })();
        """, faq_csrf)
        self.wait.until(lambda d: d.execute_script("return window.__faqDone === true;"))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT faq_id FROM faqs WHERE question LIKE 'Copy of %' ORDER BY faq_id DESC LIMIT 1")
            self.assertIsNotNone(cur.fetchone())

        # Anti-lockout self-demotion guard
        self.driver.get(f"{BASE_URL}/admin/users.php")
        u_csrf = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf] = arguments;
            (async () => {
              const fd = new FormData();
              fd.append('csrf_token', csrf);
              fd.append('action', 'update_role');
              fd.append('user_id', '1');
              fd.append('role_id', '2');
              await fetch('users.php', {method:'POST', body: fd});
              window.__uDone = true;
            })();
        """, u_csrf)
        self.wait.until(lambda d: d.execute_script("return window.__uDone === true;"))
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute("SELECT role_id FROM users WHERE user_id = 1")
            self.assertEqual(cur.fetchone()["role_id"], 1)

        # Pending COD collection -> Completed
        with db_conn() as conn, conn.cursor() as cur:
            cur.execute(
                "SELECT txn_id, order_id FROM money_transactions WHERE status = 'PENDING' AND payment_method = 'COD_CASH' ORDER BY txn_id DESC LIMIT 1"
            )
            pending_cod = cur.fetchone()
        if pending_cod:
            self.driver.get(f"{BASE_URL}/admin/financial-transactions.php")
            f_csrf = extract_csrf(self.driver.page_source)
            self.driver.execute_script("""
                const [csrf, tid, oid] = arguments;
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('action', 'mark_cod_collected');
                fd.append('txn_id', tid);
                fd.append('order_id', oid);
                fetch('financial-transactions.php', {method:'POST', body: fd}).then(() => window.__finDone = true);
            """, f_csrf, str(pending_cod["txn_id"]), str(pending_cod["order_id"]))
            self.wait.until(lambda d: d.execute_script("return window.__finDone === true;"))
            with db_conn() as conn, conn.cursor() as cur:
                cur.execute("SELECT status FROM money_transactions WHERE txn_id = %s", (pending_cod["txn_id"],))
                self.assertEqual(cur.fetchone()["status"], "COMPLETED")

        # Kitchen duty toggle & Delivery fleet payout
        self.driver.get(f"{BASE_URL}/admin/kitchen-view.php")
        kv_csrf = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf] = arguments;
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('action', 'toggle_duty');
            fd.append('profile_id', '1');
            fd.append('duty_status', 'ON_DUTY');
            fetch('kitchen-view.php', {method:'POST', body: fd}).then(() => window.__kvDone = true);
        """, kv_csrf)
        self.wait.until(lambda d: d.execute_script("return window.__kvDone === true;"))

        self.driver.get(f"{BASE_URL}/admin/delivery-view.php")
        dv_csrf = extract_csrf(self.driver.page_source)
        self.driver.execute_script("""
            const [csrf] = arguments;
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('action', 'disburse_payout');
            fd.append('user_id', '6');
            fd.append('month_year', 'Nov 2026');
            fd.append('base_amount', '15000');
            fd.append('incentives_amount', '500');
            fd.append('deductions_amount', '0');
            fd.append('payment_mode', 'UPI');
            fd.append('notes', 'QA Fleet Salary');
            fetch('delivery-view.php', {method:'POST', body: fd}).then(() => window.__dvDone = true);
        """, dv_csrf)
        self.wait.until(lambda d: d.execute_script("return window.__dvDone === true;"))

        # Admin logout session revocation
        self.driver.get(f"{BASE_URL}/admin/logout.php")
        self.driver.get(f"{BASE_URL}/admin/index.php")
        self.assertIn("admin/login.php", self.driver.current_url)
        assert_no_severe_console_errors(self, self.driver, "test_06")


if __name__ == "__main__":
    unittest.main(verbosity=2)
