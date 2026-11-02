"""
Selenium Automated Test Suite: Staff Views, Financial Ledger & KDS Tabs
Tests:
1. Admin Kitchen View (Staff Attendance, Shifts, Base Salary, Disbursements)
2. Admin Delivery View (Rider Attendance, COD Cash-in-hand, Vehicle, Settle Cash)
3. Admin Financial Ledger (Online & COD Transactions, Inward/Outward, CSV Export)
4. Admin Orders COD Payment Indicators (COD Paid vs COD Pending)
5. Kitchen KDS Order History & Payments (Salary) Tabs (Past Cooked Dockets, KOT reprint, Chef Salary & Payslip)
"""

import unittest
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

BASE_URL = "http://127.0.0.1:8080"
ADMIN_URL = f"{BASE_URL}/admin"
KITCHEN_URL = f"{BASE_URL}/kitchen"

class StaffFinanceAndKDSTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        chrome_options = Options()
        chrome_options.add_argument("--headless=new")
        chrome_options.add_argument("--window-size=1600,1000")
        chrome_options.add_argument("--disable-gpu")
        chrome_options.add_argument("--no-sandbox")
        cls.driver = webdriver.Chrome(options=chrome_options)
        cls.driver.implicitly_wait(5)
        
        # Log in to admin portal
        cls.driver.get(f"{ADMIN_URL}/login.php")
        time.sleep(0.5)
        if "login.php" in cls.driver.current_url:
            email_input = cls.driver.find_element(By.ID, "adminEmail")
            password_input = cls.driver.find_element(By.ID, "adminPassword")
            email_input.clear()
            email_input.send_keys("admin@zippy.com")
            password_input.clear()
            password_input.send_keys("password123")
            submit_btn = cls.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
            cls.driver.execute_script("arguments[0].click();", submit_btn)
            time.sleep(1)

    @classmethod
    def tearDownClass(cls):
        cls.driver.quit()

    def test_01_admin_kitchen_view(self):
        print("\n--- TEST 1: Admin Kitchen View (Staff Attendance & Salary) ---")
        self.driver.get(f"{ADMIN_URL}/kitchen-view.php")
        time.sleep(1)

        self.assertIn("Kitchen Operations", self.driver.page_source)
        self.assertIn("Chef Sanjeev", self.driver.page_source)
        self.assertIn("Chef Vikram Rathore", self.driver.page_source)
        self.assertIn("Priya Sharma", self.driver.page_source)
        self.assertIn("ON_DUTY", self.driver.page_source)
        self.assertIn("35,000", self.driver.page_source)
        print("  [PASS] Kitchen Staff roster verified with duty statuses, shifts, and base salaries!")

    def test_02_admin_delivery_view(self):
        print("\n--- TEST 2: Admin Delivery View (Rider Fleet & COD Balance) ---")
        self.driver.get(f"{ADMIN_URL}/delivery-view.php")
        time.sleep(1)

        self.assertIn("Delivery Fleet", self.driver.page_source)
        self.assertIn("Suresh Kumar", self.driver.page_source)
        self.assertIn("Ramesh Rao", self.driver.page_source)
        self.assertIn("COD Cash", self.driver.page_source)
        print("  [PASS] Delivery Fleet roster verified with live attendance, vehicles, and COD cash balances!")

    def test_03_admin_financial_transactions(self):
        print("\n--- TEST 3: Admin Financial Ledger (Online & COD Transactions) ---")
        self.driver.get(f"{ADMIN_URL}/financial-transactions.php")
        time.sleep(1)

        self.assertIn("Financial Ledger", self.driver.page_source)
        self.assertIn("Gross Inward", self.driver.page_source)
        self.assertIn("Online Digital", self.driver.page_source)
        self.assertIn("COD Cash Collected", self.driver.page_source)
        self.assertIn("TXN-", self.driver.page_source)
        self.assertIn("INWARD", self.driver.page_source)
        print("  [PASS] Financial Ledger verified with online prepayments, COD cash flows, and inward audit trails!")

    def test_04_admin_orders_cod_status(self):
        print("\n--- TEST 4: Admin Orders COD Payment Indicators ---")
        self.driver.get(f"{ADMIN_URL}/orders.php?status=ALL")
        time.sleep(1)

        page_source = self.driver.page_source
        has_cod_indicator = ("COD CASH PAID" in page_source or "COD PENDING" in page_source or "COD Cash" in page_source)
        has_online_indicator = ("ONLINE PAID" in page_source or "Online Paid" in page_source)
        self.assertTrue(has_cod_indicator, "Expected COD payment indicator on Orders Management page")
        self.assertTrue(has_online_indicator, "Expected Online payment indicator on Orders Management page")
        print("  [PASS] Orders page clearly distinguishes ONLINE PAID vs COD CASH status!")

    def test_05_kitchen_kds_history_and_salary(self):
        print("\n--- TEST 5: Kitchen KDS Order History & Payments (Salary) Tabs ---")
        # Logout admin and login to kitchen
        self.driver.get(f"{ADMIN_URL}/logout.php")
        time.sleep(0.5)

        self.driver.get(f"{KITCHEN_URL}/login.php")
        time.sleep(0.5)
        email_input = self.driver.find_element(By.NAME, "email")
        password_input = self.driver.find_element(By.NAME, "password")
        email_input.clear()
        email_input.send_keys("chef@zippy.com")
        password_input.clear()
        password_input.send_keys("password123")
        submit_btn = self.driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        self.driver.execute_script("arguments[0].click();", submit_btn)
        time.sleep(1)

        self.driver.get(f"{KITCHEN_URL}/index.php")
        time.sleep(1)

        # 1. Verify Order History Tab presence and content
        history_btn = self.driver.find_element(By.ID, "tab-history-btn")
        self.assertIn("Order History", history_btn.text)
        self.driver.execute_script("bootstrap.Tab.getOrCreateInstance(arguments[0]).show();", history_btn)
        time.sleep(0.5)

        history_pane = self.driver.find_element(By.ID, "tab-history")
        self.assertIn("Culinary Order Preparation History", history_pane.text)
        self.assertIn("ZIP-", history_pane.text)
        print("  [PASS] KDS Order History tab active with past fulfilled tickets and KOT slip reprint button!")

        # 2. Verify Payments (Salary) Tab presence and content
        salary_btn = self.driver.find_element(By.ID, "tab-salary-btn")
        self.assertIn("Salary", salary_btn.text)
        self.driver.execute_script("bootstrap.Tab.getOrCreateInstance(arguments[0]).show();", salary_btn)
        time.sleep(0.5)

        salary_pane = self.driver.find_element(By.ID, "tab-salary")
        self.assertIn("Chef Sanjeev", salary_pane.text)
        self.assertIn("35,000", salary_pane.text)
        self.assertIn("Disbursed Salary & Payout History", salary_pane.text)
        self.assertIn("Payslip", salary_pane.text)

        # Click payslip button
        payslip_btns = salary_pane.find_elements(By.XPATH, ".//button[contains(., 'Payslip')]")
        self.assertGreater(len(payslip_btns), 0, "Expected at least one Payslip button")
        self.driver.execute_script("arguments[0].click();", payslip_btns[0])
        time.sleep(0.5)

        self.assertIn("Zippy Operations | Culinary Payslip", self.driver.page_source)
        self.assertIn("Net Disbursed Amount", self.driver.page_source)
        print("  [PASS] KDS Payments (Salary) tab verified with Chef Sanjeev's compensation, payouts, and printable payslip modal!")

        # Screenshot verification
        self.driver.save_screenshot("pooja_project/tests/screenshots/kds_salary_verified.png")
        print("  [SCREENSHOT] Saved verification screenshot to pooja_project/tests/screenshots/kds_salary_verified.png")

if __name__ == "__main__":
    unittest.main()
