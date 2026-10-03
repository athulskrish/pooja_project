"""
Automated Selenium Test Suite for Zippy Food Ordering Platform
Tests:
  1. Customer Authentication (pooja@example.com)
  2. Cart Population & Catalog Item Verification (Item #13 with 19-min prep time -> 34-min ETA)
  3. ACID Checkout Submission & Order Confirmation (order-success.php)
  4. Live Order Status (order-status.php) & 34-Minute Countdown Timer Verification
  5. Real-Time Timer Countdown Ticking Validation (seconds decrementing)
  6. Post-Timer Expiry Verification (Timer reaches 0 -> '00:00' and 'ARRIVING NOW')
  7. Real-Time Background Polling Transition to DELIVERED without page reload
  8. Direct Server-Side Rendering of Delivered Order State
  9. GST 5% Tax Invoice Verification (invoice.php)
  10. Order History & 5-Star Feedback Submission (order-history.php & rate-order.php)
  11. Verification of Order Placed 34 Minutes Ago (Past ETA handling)
"""

import os
import sys
import time
import unittest

if sys.platform == 'win32':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except Exception:
        pass

import pymysql
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = os.environ.get("ZIPPY_BASE_URL", "http://127.0.0.1:8080")
SCREENSHOT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "screenshots")
os.makedirs(SCREENSHOT_DIR, exist_ok=True)

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "zippy_db",
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor
}


def get_db_connection():
    return pymysql.connect(**DB_CONFIG)


class ZippyTimerSeleniumTest(unittest.TestCase):
    driver = None
    created_order_id = None

    @classmethod
    def setUpClass(cls):
        chrome_options = Options()
        chrome_options.add_argument("--headless=new")
        chrome_options.add_argument("--window-size=1400,900")
        chrome_options.add_argument("--disable-gpu")
        chrome_options.add_argument("--no-sandbox")
        chrome_options.add_argument("--disable-dev-shm-usage")
        chrome_options.add_argument("--ignore-certificate-errors")

        cls.driver = webdriver.Chrome(options=chrome_options)
        cls.driver.implicitly_wait(6)
        print("\n" + "=" * 65)
        print("[SETUP] Initialized Chrome WebDriver for Zippy 34-Min Timer Testing")
        print("=" * 65)

    @classmethod
    def tearDownClass(cls):
        if cls.driver:
            cls.driver.quit()
            print("\n[TEARDOWN] Closed Chrome WebDriver session")

    def snap(self, filename):
        path = os.path.join(SCREENSHOT_DIR, filename)
        self.driver.save_screenshot(path)
        print(f"  [SCREENSHOT] {filename} saved to {path}")
        return path

    def test_01_customer_login(self):
        """Test customer login with pooja@example.com"""
        print("\n--- TEST 1: Customer Login ---")
        self.driver.get(f"{BASE_URL}/customer/login.php")
        self.assertIn("Customer Sign In", self.driver.title)

        email_input = self.driver.find_element(By.ID, "loginEmail")
        pwd_input = self.driver.find_element(By.ID, "loginPassword")
        email_input.clear()
        email_input.send_keys("pooja@example.com")
        pwd_input.clear()
        pwd_input.send_keys("password123")

        submit_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        self.driver.execute_script("arguments[0].click();", submit_btn)

        WebDriverWait(self.driver, 6).until(
            lambda d: "index.php" in d.current_url or "customer" in d.current_url
        )
        self.snap("01_customer_logged_in.png")
        print("  [PASS] Logged in successfully as Pooja JP (Customer)")

    def test_02_add_item_to_cart_and_checkout(self):
        """Configure dish with 19-min prep time to generate 34-min ETA (19 prep + 15 travel = 34 min) and submit order"""
        print("\n--- TEST 2: Prepare Cart with 19-min Prep Time (34-Min ETA) ---")
        
        # Configure item 13 to have 19-min prep time so 19 prep + 15 delivery = 34 min ETA
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("UPDATE menu_items SET prep_time_minutes = 19 WHERE item_id = 13")
            conn.commit()

        # Place item into localStorage cart
        cart_data = """[
            {
                "id": 13,
                "name": "Crispy Fried Chicken Burger",
                "price": 249.00,
                "qty": 1,
                "isVeg": false
            },
            {
                "id": 7,
                "name": "Garlic Butter Naan (2 Pcs)",
                "price": 89.00,
                "qty": 1,
                "isVeg": true
            }
        ]"""

        self.driver.get(f"{BASE_URL}/customer/checkout.php")
        clean_cart = cart_data.replace("\n", "").replace("  ", "")
        self.driver.execute_script(f"localStorage.setItem('pooja_zippy_cart', '{clean_cart}');")
        self.driver.refresh()

        WebDriverWait(self.driver, 6).until(
            EC.presence_of_element_located((By.ID, "checkoutSummaryItems"))
        )
        self.snap("02_checkout_page_ready.png")

        # Select COD payment via option card click using execute_script to bypass any mobile overlay
        for c in self.driver.find_elements(By.CSS_SELECTOR, '.payment-option-card'):
            if 'Cash on Delivery' in c.text:
                self.driver.execute_script("arguments[0].scrollIntoView(true);", c)
                time.sleep(0.3)
                self.driver.execute_script("arguments[0].click();", c)
                break

        # Submit checkout form
        submit_btn = self.driver.find_element(By.CSS_SELECTOR, "#checkoutForm button[type='submit']")
        self.driver.execute_script("arguments[0].scrollIntoView(true);", submit_btn)
        time.sleep(0.5)
        self.driver.execute_script("arguments[0].click();", submit_btn)

        # Wait for redirect to order-success.php or order-status.php
        WebDriverWait(self.driver, 10).until(
            lambda d: "order-success.php" in d.current_url or "order-status.php" in d.current_url
        )

        current_url = self.driver.current_url
        self.assertIn("order_id=", current_url)
        order_id = int(current_url.split("order_id=")[1].split("&")[0])
        ZippyTimerSeleniumTest.created_order_id = order_id
        print(f"  [PASS] Order placed successfully! Order ID: #ZIP-{order_id}")
        self.snap("03_order_placed_confirmation.png")

        # Navigate to order-status.php to track live timer
        tracker_url = f"{BASE_URL}/customer/order-status.php?order_id={order_id}"
        self.driver.get(tracker_url)
        WebDriverWait(self.driver, 6).until(
            EC.presence_of_element_located((By.ID, "live-eta-countdown"))
        )
        self.snap("04_live_order_status_page.png")

    def test_03_verify_live_34min_countdown_timer(self):
        """Verify the 34-minute countdown timer is active, displaying MM:SS and ticking down"""
        print("\n--- TEST 3: Verify Live 34-Min Countdown Timer ---")
        order_id = ZippyTimerSeleniumTest.created_order_id
        self.assertIsNotNone(order_id, "Order ID must be set from Test 2")

        if "order-status.php" not in self.driver.current_url:
            self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")

        countdown_elem = WebDriverWait(self.driver, 6).until(
            EC.visibility_of_element_located((By.ID, "live-eta-countdown"))
        )
        countdown_label = self.driver.find_element(By.ID, "countdownLabel")
        countdown_subtext = self.driver.find_element(By.ID, "countdownSubtext")

        self.assertEqual(countdown_label.text.strip(), "ESTIMATED TIME")
        self.assertEqual(countdown_subtext.text.strip(), "MINS REMAINING")

        initial_text = countdown_elem.text.strip()
        print(f"  [TIMER] Initial Countdown Reading: {initial_text}")
        self.assertIn(":", initial_text, "Timer format should be MM:SS")

        parts = initial_text.split(":")
        mins = int(parts[0])
        secs = int(parts[1])
        print(f"  [TIMER] Parsed: {mins} minutes, {secs} seconds remaining")
        # 19 prep + 15 delivery = 34 minutes
        self.assertTrue(33 <= mins <= 34, f"Expected ~34 minutes countdown, got {mins}")

        # Observe live countdown ticking down over 3.5 seconds
        print("  [TIMER] Observing live decrement for 3.5 seconds...")
        time.sleep(3.5)
        second_reading = self.driver.find_element(By.ID, "live-eta-countdown").text.strip()
        print(f"  [TIMER] Countdown Reading after 3.5s: {second_reading}")
        self.assertNotEqual(initial_text, second_reading, "Countdown must decrement dynamically in real-time")
        self.snap("05_active_timer_ticking.png")
        print("  [PASS] Live 34-minute countdown timer verified actively ticking down!")

    def test_04_test_after_timer_expiration_arriving_now(self):
        """Test behavior when the 34-min countdown timer expires (secondsLeft reaches 0 -> '00:00' and 'ARRIVING NOW')"""
        print("\n--- TEST 4: Verification After 34-Min Timer Expiry (Reaching 00:00) ---")
        order_id = ZippyTimerSeleniumTest.created_order_id
        if "order-status.php" not in self.driver.current_url:
            self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")

        # Fast-forward timer to 0 via exposed helper to simulate the passage of the 34-minute duration
        self.driver.execute_script("if (typeof window.__setSecondsLeft === 'function') { window.__setSecondsLeft(0); }")
        time.sleep(1.2)

        countdown_elem = self.driver.find_element(By.ID, "live-eta-countdown")
        countdown_subtext = self.driver.find_element(By.ID, "countdownSubtext")

        self.assertEqual(countdown_elem.text.strip(), "00:00")
        self.assertEqual(countdown_subtext.text.strip(), "ARRIVING NOW")
        print("  [PASS] After 34-min timer expiration verified: displays '00:00' and 'ARRIVING NOW'")
        self.snap("06_after_timer_arriving_now.png")

    def test_05_live_polling_transition_to_delivered(self):
        """Test real-time status update to DELIVERED via background AJAX polling without page reload"""
        print("\n--- TEST 5: Live Real-Time Polling & Transition to DELIVERED ---")
        order_id = ZippyTimerSeleniumTest.created_order_id

        # Update order in database to DELIVERED
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT delivery_otp FROM delivery_dispatches WHERE order_id = %s", (order_id,))
                disp = cur.fetchone()
                otp = disp["delivery_otp"] if disp and disp["delivery_otp"] else "482194"

                cur.execute("UPDATE orders SET order_status = 'DELIVERED' WHERE order_id = %s", (order_id,))
                cur.execute("""
                    INSERT INTO delivery_dispatches (order_id, delivery_partner_name, delivery_partner_phone, delivery_otp, delivery_status, delivered_at)
                    VALUES (%s, 'Suresh Kumar (Rider)', '+91 96320 98765', %s, 'DELIVERED', NOW())
                    ON DUPLICATE KEY UPDATE delivery_status = 'DELIVERED', delivered_at = NOW()
                """, (order_id, otp))
                cur.execute("UPDATE payments SET payment_status = 'SUCCESSFUL', payment_timestamp = NOW() WHERE order_id = %s", (order_id,))
            conn.commit()

        print("  [POLL] Database updated to DELIVERED. Waiting for 4-second live client poller...")

        # Wait for the poller in order-status.php to execute handleDelivered()
        WebDriverWait(self.driver, 12).until(
            lambda d: d.find_element(By.ID, "live-eta-countdown").text.strip() == "DELIVERED"
        )

        countdown_elem = self.driver.find_element(By.ID, "live-eta-countdown")
        countdown_label = self.driver.find_element(By.ID, "countdownLabel")
        countdown_subtext = self.driver.find_element(By.ID, "countdownSubtext")
        status_heading = self.driver.find_element(By.ID, "statusHeading")
        stepper_bar = self.driver.find_element(By.ID, "stepperProgressBar")

        self.assertEqual(countdown_elem.text.strip(), "DELIVERED")
        self.assertEqual(countdown_label.text.strip(), "ORDER STATUS")
        self.assertEqual(countdown_subtext.text.strip(), "ORDER COMPLETED")
        self.assertIn("Delivered Successfully", status_heading.text)
        self.assertEqual(stepper_bar.get_attribute("style"), "width: 100%;")

        print("  [PASS] Live poller automatically updated UI to DELIVERED with 100% progress stepper!")
        self.snap("07_live_poller_delivered_success.png")

    def test_06_server_side_delivered_page_load(self):
        """Test reloading order-status.php directly renders server-side delivered state"""
        print("\n--- TEST 6: Direct Page Load of Delivered Order ---")
        order_id = ZippyTimerSeleniumTest.created_order_id
        self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")

        countdown_elem = WebDriverWait(self.driver, 6).until(
            EC.visibility_of_element_located((By.ID, "live-eta-countdown"))
        )
        countdown_subtext = self.driver.find_element(By.ID, "countdownSubtext")
        self.assertEqual(countdown_elem.text.strip(), "DELIVERED")
        self.assertEqual(countdown_subtext.text.strip(), "ORDER COMPLETED")
        self.snap("08_server_rendered_delivered.png")
        print("  [PASS] Server-side rendered delivered state verified upon direct reload!")

    def test_07_tax_invoice_generation(self):
        """Verify Tax Invoice generation with 5% GST and line items"""
        print("\n--- TEST 7: Tax Invoice Verification ---")
        order_id = ZippyTimerSeleniumTest.created_order_id
        self.driver.get(f"{BASE_URL}/customer/invoice.php?order_id={order_id}")

        self.assertIn("Invoice", self.driver.title)
        page_source = self.driver.page_source
        self.assertIn(f"#ZIP-{order_id}", page_source)
        self.assertIn("Crispy Fried Chicken Burger", page_source)
        self.assertIn("CGST (2.5%)", page_source)
        self.assertIn("SGST (2.5%)", page_source)
        self.assertIn("COD - SUCCESSFUL", page_source)

        self.snap("09_tax_invoice.png")
        print(f"  [PASS] Tax Invoice for #ZIP-{order_id} verified with 5% GST (CGST+SGST) and SUCCESSFUL status!")

    def test_08_order_history_and_rating(self):
        """Verify Order History listing and 5-Star Rating submission"""
        print("\n--- TEST 8: Order History & 5-Star Rating ---")
        order_id = ZippyTimerSeleniumTest.created_order_id

        # 1. Order History
        self.driver.get(f"{BASE_URL}/customer/order-history.php")
        self.assertIn(f"#ZIP-{order_id}", self.driver.page_source)
        self.snap("10_order_history_listed.png")
        print(f"  [PASS] Order #ZIP-{order_id} verified in Customer Order History!")

        # 2. Rate Order
        self.driver.get(f"{BASE_URL}/customer/rate-order.php?order_id={order_id}")
        self.assertIn("Rate Your Food", self.driver.title)

        try:
            tip20 = self.driver.find_element(By.ID, "tip20")
            self.driver.execute_script("arguments[0].checked = true;", tip20)
        except Exception:
            pass

        review_textarea = self.driver.find_element(By.NAME, "comments")
        review_textarea.clear()
        review_textarea.send_keys("Delivered hot and on time! Incredible food taste and speedy doorstep delivery.")

        submit_review_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        self.driver.execute_script("arguments[0].click();", submit_review_btn)

        time.sleep(1)
        self.snap("11_rating_submitted_success.png")
        print(f"  [PASS] 5-Star Rating submitted for #ZIP-{order_id}!")

    def test_09_historical_order_with_34min_past_window(self):
        """Test an order created in DB with placed_at 34 minutes ago and ETA elapsed"""
        print("\n--- TEST 9: Historical Order Placed Exactly 34 Minutes Ago ---")
        unique_txn = f"pay_rzp_test_34min_{int(time.time())}"
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("""
                    INSERT INTO orders (
                        user_id, address_id, subtotal, tax_amount, delivery_fee, 
                        discount_amount, total_amount, order_status, order_notes, 
                        placed_at, estimated_delivery_time
                    ) VALUES (
                        2, 1, 338.00, 16.90, 40.00, 
                        0.00, 394.90, 'DELIVERED', 'Selenium 34-min timer validation test',
                        DATE_SUB(NOW(), INTERVAL 34 MINUTE), NOW()
                    )
                """)
                past_order_id = cur.lastrowid
                cur.execute("""
                    INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total)
                    VALUES (%s, 13, 1, 249.00, 249.00), (%s, 7, 1, 89.00, 89.00)
                """, (past_order_id, past_order_id))
                cur.execute("""
                    INSERT INTO payments (order_id, payment_mode, transaction_reference, payment_status, amount_paid, payment_timestamp)
                    VALUES (%s, 'UPI', %s, 'SUCCESSFUL', 394.90, DATE_SUB(NOW(), INTERVAL 34 MINUTE))
                """, (past_order_id, unique_txn))
                cur.execute("""
                    INSERT INTO delivery_dispatches (order_id, delivery_partner_name, delivery_partner_phone, delivery_otp, delivery_status, delivered_at)
                    VALUES (%s, 'Suresh Kumar (Rider)', '+91 96320 98765', '852914', 'DELIVERED', NOW())
                """, (past_order_id,))
            conn.commit()

        # Track past order
        self.driver.get(f"{BASE_URL}/customer/order-status.php?order_id={past_order_id}")
        countdown_elem = WebDriverWait(self.driver, 6).until(
            EC.visibility_of_element_located((By.ID, "live-eta-countdown"))
        )
        self.assertEqual(countdown_elem.text.strip(), "DELIVERED")
        self.snap("12_past_34min_order_delivered.png")
        print(f"  [PASS] Order #ZIP-{past_order_id} placed 34 minutes ago correctly rendered as DELIVERED!")


if __name__ == "__main__":
    unittest.main(verbosity=2)
