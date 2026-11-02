import unittest
import urllib.request
import urllib.parse
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

BASE_URL = "http://127.0.0.1:8080"

class TestAdminLiveOrdersUI(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        chrome_options = Options()
        chrome_options.add_argument("--headless=new")
        chrome_options.add_argument("--no-sandbox")
        chrome_options.add_argument("--disable-dev-shm-usage")
        chrome_options.add_argument("--window-size=1440,900")
        cls.driver = webdriver.Chrome(options=chrome_options)
        cls.driver.implicitly_wait(5)

        # Login as Admin
        cls.driver.get(f"{BASE_URL}/admin/login.php")
        email_input = cls.driver.find_element(By.ID, "adminEmail")
        pwd_input = cls.driver.find_element(By.ID, "adminPassword")
        email_input.clear()
        email_input.send_keys("admin@zippy.com")
        pwd_input.clear()
        pwd_input.send_keys("password123")
        submit_btn = cls.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        cls.driver.execute_script("arguments[0].click();", submit_btn)
        WebDriverWait(cls.driver, 6).until(lambda d: "admin/index.php" in d.current_url)

    @classmethod
    def tearDownClass(cls):
        cls.driver.quit()

    def test_01_kitchen_kds_removed_from_admin_nav(self):
        """Verify Kitchen KDS Portal link is removed from Admin navigation"""
        print("\n--- TEST: Kitchen KDS Removed from Admin Nav ---")
        self.driver.get(f"{BASE_URL}/admin/index.php")
        page_source = self.driver.page_source
        
        # Verify no "Kitchen KDS Portal" in sidebar
        sidebar_links = self.driver.find_elements(By.CSS_SELECTOR, ".admin-sidebar .admin-nav-link")
        link_texts = [l.text for l in sidebar_links]
        for t in link_texts:
            self.assertNotIn("Kitchen KDS Portal", t, f"Kitchen KDS Portal link found in admin sidebar: {t}")
        
        # Verify no "Open Kitchen KDS Portal" button on dashboard
        self.assertNotIn("Open Kitchen KDS Portal", page_source)
        print("  [PASS] Kitchen KDS Portal completely removed from Admin sidebar & dashboard!")

    def test_02_admin_dashboard_what_is_prepared_and_delivered(self):
        """Verify Admin Dashboard shows what is being prepared vs what is being delivered"""
        print("\n--- TEST: Admin Dashboard What is Prepared & Delivered ---")
        self.driver.get(f"{BASE_URL}/admin/index.php")
        
        # Check Cook-Line Load aggregation
        cook_line = self.driver.find_element(By.CSS_SELECTOR, ".cook-load-badge")
        self.assertTrue(cook_line.is_displayed())
        print(f"  [PASS] Found Cook-Line item: {cook_line.text}")

        # Check stage filter buttons
        stage_buttons = self.driver.find_elements(By.CSS_SELECTOR, "#dashboardStageFilters button")
        stage_texts = [b.text for b in stage_buttons]
        self.assertTrue(any("Being Prepared" in t for t in stage_texts))
        self.assertTrue(any("Out for Delivery" in t for t in stage_texts))
        print("  [PASS] Stage filter buttons (Being Prepared, Out for Delivery) present on dashboard!")

        # Check search input
        search_input = self.driver.find_element(By.ID, "dashboardLiveSearch")
        self.assertTrue(search_input.is_displayed())
        
        # Test typing search
        search_input.send_keys("Biryani")
        time.sleep(0.5)
        visible_cards = self.driver.find_elements(By.CSS_SELECTOR, ".live-order-card-col:not(.d-none)")
        print(f"  [PASS] Live search for 'Biryani' filtered to {len(visible_cards)} cards!")

    def test_03_admin_orders_page_ui_ux_and_searchable(self):
        """Verify Admin Orders page: cards/table view, OTP display, item breakdowns, and search"""
        print("\n--- TEST: Admin Orders Page Comprehensive Features ---")
        self.driver.get(f"{BASE_URL}/admin/orders.php?status=LIVE")
        
        # Verify page title
        self.assertIn("Orders Management", self.driver.title)

        # Check stage filters
        page_source = self.driver.page_source
        self.assertIn("Being Prepared", page_source)
        self.assertIn("Out for Delivery", page_source)
        self.assertIn("Live Active", page_source)

        # Check Live Search Input
        search_input = self.driver.find_element(By.ID, "orderLiveSearchInput")
        self.assertTrue(search_input.is_displayed())

        # Test View Switcher (Cards -> Table)
        btn_table = self.driver.find_element(By.ID, "btnViewTable")
        btn_table.click()
        time.sleep(0.3)
        table_container = self.driver.find_element(By.ID, "ordersTableViewContainer")
        self.assertTrue(table_container.is_displayed())
        print("  [PASS] Switched to Table View successfully!")

        # Test View Switcher (Table -> Cards)
        btn_cards = self.driver.find_element(By.ID, "btnViewCards")
        btn_cards.click()
        time.sleep(0.3)
        cards_container = self.driver.find_element(By.ID, "ordersCardsViewContainer")
        self.assertTrue(cards_container.is_displayed())
        print("  [PASS] Switched back to Cards View successfully!")

        # Verify delivery handshake OTP badge
        print(f"  [DEBUG] Current URL: {self.driver.current_url}, Title: {self.driver.title}")
        with open("pooja_project/tests/debug_orders_page.html", "w", encoding="utf-8") as f:
            f.write(self.driver.page_source)
        print(f"  [DEBUG] Has delivery-otp-pill in page_source: {'delivery-otp-pill' in self.driver.page_source}")
        print(f"  [DEBUG] Has OTP in page_source: {'OTP:' in self.driver.page_source}")
        otp_elements = self.driver.find_elements(By.CSS_SELECTOR, ".delivery-otp-pill")
        self.assertTrue(len(otp_elements) > 0, "No delivery OTP pills found on orders page")
        print(f"  [PASS] Found {len(otp_elements)} Delivery Handshake OTP pills (Sample: {otp_elements[0].text})")

        # Test live search filter on orders page
        search_input.send_keys("Suresh")
        time.sleep(0.5)
        filtered_cards = self.driver.find_elements(By.CSS_SELECTOR, ".order-card-item-col:not(.d-none)")
        print(f"  [PASS] Live search for rider 'Suresh' matched {len(filtered_cards)} order cards!")

        # Save verification screenshot
        self.driver.save_screenshot("pooja_project/tests/screenshots/admin_live_orders_verified.png")
        print("  [SCREENSHOT] Saved verification screenshot to pooja_project/tests/screenshots/admin_live_orders_verified.png")

if __name__ == "__main__":
    unittest.main()
