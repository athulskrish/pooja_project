import unittest

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait


BASE_URL = "http://127.0.0.1:8080"


class TestAdminOperationsUI(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        chrome_options = Options()
        chrome_options.add_argument("--headless=new")
        chrome_options.add_argument("--no-sandbox")
        chrome_options.add_argument("--disable-dev-shm-usage")
        chrome_options.add_argument("--window-size=1440,900")
        cls.driver = webdriver.Chrome(options=chrome_options)
        cls.driver.implicitly_wait(5)

        cls.driver.get(f"{BASE_URL}/admin/login.php")
        cls.driver.find_element(By.ID, "adminEmail").send_keys("admin@zippy.com")
        cls.driver.find_element(By.ID, "adminPassword").send_keys("password123")
        cls.driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
        WebDriverWait(cls.driver, 6).until(lambda driver: "admin/index.php" in driver.current_url)

    @classmethod
    def tearDownClass(cls):
        cls.driver.quit()

    def test_dashboard_prioritizes_exceptions_and_workspaces(self):
        self.driver.get(f"{BASE_URL}/admin/index.php")
        page = self.driver

        self.assertTrue(page.find_element(By.ID, "attentionHeading").is_displayed())
        self.assertTrue(page.find_element(By.ID, "workspacesHeading").is_displayed())
        self.assertTrue(page.find_element(By.CSS_SELECTOR, 'a[href*="orders.php?status=LIVE"]').is_displayed())
        self.assertTrue(page.find_element(By.CSS_SELECTOR, 'a[href$="kitchen-view.php"]').is_displayed())
        self.assertTrue(page.find_element(By.CSS_SELECTOR, 'a[href$="financial-transactions.php"]').is_displayed())
        self.assertEqual([], page.find_elements(By.CSS_SELECTOR, ".order-live-card, #recentTransactionsTable"))
        self.assertNotIn("TOTAL REVENUE", page.page_source)

    def test_orders_use_one_searchable_sortable_ledger(self):
        self.driver.get(f"{BASE_URL}/admin/orders.php?status=LIVE")
        page = self.driver

        self.assertIn("Orders Management", page.title)
        table = page.find_element(By.ID, "ordersMasterTable")
        self.assertTrue(table.is_displayed())
        self.assertTrue(page.find_element(By.ID, "orderLiveSearchInput").is_displayed())
        self.assertTrue(page.find_elements(By.CSS_SELECTOR, "#ordersMasterTable [data-sort-column]"))
        self.assertEqual([], page.find_elements(By.ID, "btnViewCards"))
        self.assertEqual([], page.find_elements(By.ID, "btnViewTable"))
        self.assertEqual([], page.find_elements(By.CSS_SELECTOR, ".order-live-card"))

        rows = page.find_elements(By.CSS_SELECTOR, ".order-table-row")
        if rows:
            page.find_element(By.CSS_SELECTOR, '[data-sort-column="1"]').click()
            search = page.find_element(By.ID, "orderLiveSearchInput")
            search.send_keys("no-order-should-match-this")
            self.assertTrue(page.find_element(By.ID, "ordersZeroSearchState").is_displayed())
            page.find_element(By.ID, "clearOrderSearchBtn").click()
            self.assertTrue(page.find_element(By.CSS_SELECTOR, ".order-table-row").is_displayed())

    def test_reports_use_real_period_filtered_charts(self):
        self.driver.get(f"{BASE_URL}/admin/reports.php")
        page = self.driver

        self.assertTrue(page.find_element(By.ID, "reportPeriod").is_displayed())
        self.assertTrue(page.find_element(By.ID, "salesTrendChart").is_displayed())
        self.assertTrue(page.find_element(By.ID, "categorySalesChart").is_displayed())
        self.assertNotIn("GST TAX REMITTANCE", page.page_source)
        self.assertNotIn("8 PM (Peak)", page.page_source)
        self.assertNotIn("Menu Item Demand Predictions", page.page_source)


if __name__ == "__main__":
    unittest.main()
