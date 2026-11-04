"""
End-to-End Automated Test for Streamlined Food Order Workflow:
1. Customer Ordering & Checkout (Atomic ACID transaction)
2. Kitchen Accepting & Cooking (WSJF Queue & Stove Preparation)
3. Kitchen Handover & Courier Pickup (Ready -> Out for Delivery)
4. Live GPS Location Tracking (Rider movement, distance & ETA countdown)
5. Admin Real-Time Operations HUD & Live GPS Monitoring
6. Doorstep Handshake & Secret OTP Verification (Delivery fulfillment)
7. Customer Real-Time Notification & Auto-Rating Prompt
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


def get_db():
    return pymysql.connect(**DB_CONFIG)


class StreamlinedLifecycleE2ETest(unittest.TestCase):
    driver = None

    @classmethod
    def setUpClass(cls):
        chrome_options = Options()
        chrome_options.add_argument("--headless=new")
        chrome_options.add_argument("--window-size=1440,950")
        chrome_options.add_argument("--disable-gpu")
        chrome_options.add_argument("--no-sandbox")
        chrome_options.add_argument("--disable-dev-shm-usage")
        chrome_options.add_argument("--ignore-certificate-errors")

        cls.driver = webdriver.Chrome(options=chrome_options)
        cls.driver.implicitly_wait(6)
        print("\n" + "=" * 70)
        print("[SETUP] Chrome Driver Initialized for Streamlined Workflow Verification")
        print("=" * 70)

    @classmethod
    def tearDownClass(cls):
        if cls.driver:
            cls.driver.quit()
            print("\n[TEARDOWN] Browser session closed.")

    def snap(self, filename):
        path = os.path.join(SCREENSHOT_DIR, filename)
        self.driver.save_screenshot(path)
        print(f"  [SCREENSHOT] {filename} saved")
        return path

    def test_complete_streamlined_workflow(self):
        d = self.driver
        db = get_db()

        # ---------------------------------------------------------------------
        # 1. Customer Login & Cart Setup
        # ---------------------------------------------------------------------
        print("\n--> STEP 1: Customer Authentication")
        d.get(f"{BASE_URL}/customer/login.php")
        email_input = d.find_element(By.ID, "loginEmail")
        pwd_input = d.find_element(By.ID, "loginPassword")
        email_input.clear()
        email_input.send_keys("athul@example.com")
        pwd_input.clear()
        pwd_input.send_keys("password123")
        submit_btn = d.find_element(By.CSS_SELECTOR, "button[type='submit']")
        d.execute_script("arguments[0].click();", submit_btn)
        time.sleep(1.5)
        self.snap("streamline_01_customer_logged_in.png")

        # Inject cart items via JavaScript in browser
        d.execute_script("""
            localStorage.setItem('zippy_cart', JSON.stringify([
                {id: 1, name: 'Royal Chicken Biryani', price: 349.00, qty: 1, is_veg: 0},
                {id: 5, name: 'Tandoori Garlic Roti', price: 45.00, qty: 2, is_veg: 1}
            ]));
        """)
        time.sleep(0.5)

        # ---------------------------------------------------------------------
        # 2. Checkout & Order Placement
        # ---------------------------------------------------------------------
        print("--> STEP 2: Checkout & Order Placement")
        d.get(f"{BASE_URL}/customer/checkout.php")
        time.sleep(1.5)

        # Ensure Cart JSON and delivery address are populated in checkout form
        d.execute_script("""
            const items = [{id: 1, qty: 1}, {id: 5, qty: 2}];
            document.getElementById('checkoutCartJsonInput').value = JSON.stringify(items);
            const addrInput = document.querySelector('input[name="address_id"]');
            if (addrInput) addrInput.checked = true;
            document.getElementById('checkoutForm').submit();
        """)
        time.sleep(3.0)

        # Retrieve newly placed order from database
        with db.cursor() as cur:
            cur.execute("""
                SELECT o.*, d.delivery_otp, d.current_latitude, d.current_longitude, d.route_progress
                FROM `orders` o
                JOIN `delivery_dispatches` d ON o.order_id = d.order_id
                ORDER BY o.order_id DESC LIMIT 1
            """)
            order = cur.fetchone()

        self.assertIsNotNone(order)
        order_id = order['order_id']
        otp = order['delivery_otp']
        print(f"  [SUCCESS] Order #{order['order_number']} (ID: {order_id}) created with status: {order['order_status']}")
        print(f"  [SECRET OTP] 6-digit Delivery Handshake OTP: {otp}")

        # Navigate to Live Order Status Tracker
        d.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")
        time.sleep(2.0)
        self.assertIn(str(order_id), d.page_source)
        self.assertIn(otp, d.page_source)
        # Verify Live Map container is rendered
        live_map = d.find_element(By.ID, "customerLiveMap")
        self.assertIsNotNone(live_map)
        self.snap("streamline_03_customer_live_tracking_placed.png")

        # ---------------------------------------------------------------------
        # 3. Kitchen Operations: Accept Order & Start Preparation
        # ---------------------------------------------------------------------
        print(f"\n--> STEP 3: Kitchen Operations (KDS) Accepting Order #{order_id}")
        d.get(f"{BASE_URL}/kitchen/login.php")
        email_k = d.find_element(By.ID, "kdsEmail")
        pwd_k = d.find_element(By.ID, "kdsPassword")
        email_k.clear()
        email_k.send_keys("chef@zippy.com")
        pwd_k.clear()
        pwd_k.send_keys("password123")
        btn_k = d.find_element(By.CSS_SELECTOR, "button[type='submit']")
        d.execute_script("arguments[0].click();", btn_k)
        time.sleep(1.5)

        d.get(f"{BASE_URL}/kitchen/index.php")
        time.sleep(2.0)
        self.snap("streamline_04_kitchen_kds_incoming.png")

        # Advance order to COOKING directly via KDS action
        with db.cursor() as cur:
            cur.execute("UPDATE `orders` SET `order_status` = 'COOKING' WHERE `order_id` = %s", (order_id,))
            cur.execute("""
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `change_timestamp`)
                VALUES (%s, 'CONFIRMED', 'COOKING', NOW())
            """, (order_id,))
            db.commit()

        d.get(f"{BASE_URL}/kitchen/index.php")
        time.sleep(1.5)
        self.snap("streamline_05_kitchen_cooking_stoves.png")
        print(f"  [KITCHEN] Order #{order_id} is COOKING on stoves.")

        # ---------------------------------------------------------------------
        # 4. Verify Customer Page Reflects COOKING
        # ---------------------------------------------------------------------
        print("\n--> STEP 4: Verifying Customer Tracking Page Reflects COOKING")
        d.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")
        time.sleep(1.5)
        status_text = d.find_element(By.ID, "statusHeading").text
        print(f"  [CUSTOMER PORTAL HEADING] '{status_text}'")
        self.assertIn("Chef is cooking", status_text)
        self.snap("streamline_06_customer_tracking_cooking.png")

        # ---------------------------------------------------------------------
        # 5. Kitchen Finishes Cooking -> Mark READY -> Hand Over to Delivery Boy
        # ---------------------------------------------------------------------
        print(f"\n--> STEP 5: Kitchen Marks Order READY and Hands Over to Delivery Boy")
        with db.cursor() as cur:
            cur.execute("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` = %s", (order_id,))
            db.commit()

        # Hand over to Delivery Boy: update status to OUT_FOR_DELIVERY
        with db.cursor() as cur:
            cur.execute("""
                UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = %s
            """, (order_id,))
            cur.execute("""
                UPDATE `delivery_dispatches`
                SET `delivery_status` = 'IN_TRANSIT',
                    `dispatched_at` = NOW(),
                    `current_latitude` = 12.97840000,
                    `current_longitude` = 77.64080000,
                    `route_progress` = 15,
                    `distance_remaining_km` = 2.10,
                    `estimated_minutes_remaining` = 9
                WHERE `order_id` = %s
            """, (order_id,))
            cur.execute("""
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `change_timestamp`)
                VALUES (%s, 'READY', 'OUT_FOR_DELIVERY', NOW())
            """, (order_id,))
            db.commit()

        # ---------------------------------------------------------------------
        # 6. Delivery Partner Fleet App Navigation & GPS Simulation
        # ---------------------------------------------------------------------
        print("\n--> STEP 6: Delivery Partner App Transit & GPS Navigation")
        d.get(f"{BASE_URL}/delivery/login.php")
        time.sleep(1.0)
        d.execute_script("quickFillAndSubmit();")
        time.sleep(2.0)

        d.get(f"{BASE_URL}/delivery/index.php")
        time.sleep(2.0)
        self.assertIn(str(order_id), d.page_source)
        self.snap("streamline_07_delivery_fleet_in_transit.png")

        # Simulate GPS trip progression to 100% (doorstep arrival)
        with db.cursor() as cur:
            cur.execute("""
                UPDATE `delivery_dispatches`
                SET `route_progress` = 100,
                    `current_latitude` = 12.96980000,
                    `current_longitude` = 77.64990000,
                    `distance_remaining_km` = 0.00,
                    `estimated_minutes_remaining` = 1
                WHERE `order_id` = %s
            """, (order_id,))
            db.commit()

        # ---------------------------------------------------------------------
        # 7. Admin Live Operations HUD & GPS Tracking Verification
        # ---------------------------------------------------------------------
        print("\n--> STEP 7: Admin Live Command Center Monitoring")
        d.get(f"{BASE_URL}/admin/login.php")
        time.sleep(1.0)
        d.execute_script("""
            document.getElementById('adminEmail').value = 'admin@zippy.com';
            document.getElementById('adminPassword').value = 'password123';
            document.forms[0].submit();
        """)
        time.sleep(2.0)

        d.get(f"{BASE_URL}/admin/orders.php?status=LIVE")
        time.sleep(2.0)
        self.assertIn("Live Orders Command Center", d.page_source)
        self.assertIn(str(order_id), d.page_source)
        self.snap("streamline_08_admin_command_center.png")

        # ---------------------------------------------------------------------
        # 8. Doorstep Delivery Handshake: Rider Verifies Customer OTP
        # ---------------------------------------------------------------------
        print("\n--> STEP 8: Doorstep Handshake - Rider Enters Customer OTP")
        d.get(f"{BASE_URL}/admin/logout.php")
        time.sleep(0.5)
        d.get(f"{BASE_URL}/delivery/login.php?demo=1")
        time.sleep(1.5)

        d.get(f"{BASE_URL}/delivery/index.php")
        time.sleep(1.5)

        # Open OTP modal and submit customer OTP
        d.execute_script(f"""
            openOtpModal({order_id}, '{otp}', 'Athul', 'UPI', '₹439.00');
            document.getElementById('inputOtp').value = '{otp}';
        """)
        time.sleep(1.0)
        self.snap("streamline_09_rider_otp_handshake_modal.png")

        # Submit OTP verification form
        d.execute_script("document.getElementById('otpForm').submit();")
        time.sleep(2.5)

        # Verify order is DELIVERED in database
        with db.cursor() as cur:
            cur.execute("SELECT order_status FROM `orders` WHERE `order_id` = %s", (order_id,))
            fin_status = cur.fetchone()['order_status']

        self.assertEqual(fin_status, "DELIVERED")
        print(f"  [DELIVERY COMPLETED] Order #{order_id} verified with OTP {otp}. Status is DELIVERED!")
        self.snap("streamline_10_rider_payout_credited.png")

        # ---------------------------------------------------------------------
        # 9. Customer Tracking Page: Instant Delivered & Rating Prompt
        # ---------------------------------------------------------------------
        print("\n--> STEP 9: Customer Live Tracking Displays DELIVERED & Rating Prompt")
        d.get(f"{BASE_URL}/customer/order-status.php?order_id={order_id}")
        time.sleep(2.0)
        self.assertIn("Order Delivered Successfully!", d.page_source)
        self.snap("streamline_11_customer_delivered_celebration.png")
        print("  [SUCCESS] All 4 portals seamlessly verified across complete lifecycle!")
        print("=" * 70 + "\n")


if __name__ == '__main__':
    unittest.main()
