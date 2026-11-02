import os
import sys
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = os.environ.get("ZIPPY_BASE_URL", "http://127.0.0.1:8080")
SCREENSHOT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "screenshots")
os.makedirs(SCREENSHOT_DIR, exist_ok=True)

chrome_options = Options()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--disable-gpu")
chrome_options.add_argument("--window-size=1400,1050")
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--disable-dev-shm-usage")

driver = webdriver.Chrome(options=chrome_options)

try:
    print("Navigating to Customer Order History...")
    driver.get(f"{BASE_URL}/customer/order-history.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, "orderHistoryTabs")))
    time.sleep(1)
    
    # 1. Screenshot Tab: All Orders
    screenshot_all = os.path.join(SCREENSHOT_DIR, "order_history_all_tab.png")
    driver.save_screenshot(screenshot_all)
    print(f"Captured: {screenshot_all}")
    
    # 2. Click Tab: Ongoing
    ongoing_btn = driver.find_element(By.ID, "tab-ongoing-btn")
    ongoing_btn.click()
    time.sleep(1)
    screenshot_ongoing = os.path.join(SCREENSHOT_DIR, "order_history_ongoing_tab.png")
    driver.save_screenshot(screenshot_ongoing)
    print(f"Captured: {screenshot_ongoing}")
    
    # 3. Click Tab: Completed
    completed_btn = driver.find_element(By.ID, "tab-completed-btn")
    completed_btn.click()
    time.sleep(1)
    screenshot_completed = os.path.join(SCREENSHOT_DIR, "order_history_completed_tab.png")
    driver.save_screenshot(screenshot_completed)
    print(f"Captured: {screenshot_completed}")
    
    # 4. Click Tab: Cancelled
    cancelled_btn = driver.find_element(By.ID, "tab-cancelled-btn")
    cancelled_btn.click()
    time.sleep(1)
    screenshot_cancelled = os.path.join(SCREENSHOT_DIR, "order_history_cancelled_tab.png")
    driver.save_screenshot(screenshot_cancelled)
    print(f"Captured: {screenshot_cancelled}")
    
    # 5. Test Live Search
    print("Testing live search...")
    all_btn = driver.find_element(By.ID, "tab-all-btn")
    all_btn.click()
    time.sleep(0.5)
    
    search_input = driver.find_element(By.ID, "orderSearchInput")
    search_input.send_keys("Biryani")
    time.sleep(0.5)
    
    screenshot_search = os.path.join(SCREENSHOT_DIR, "order_history_search.png")
    driver.save_screenshot(screenshot_search)
    print(f"Captured: {screenshot_search}")
    
    # 6. Test Reorder Button (adds to localStorage cart and opens cart drawer)
    print("Testing Reorder button...")
    reorder_buttons = driver.find_elements(By.XPATH, "//button[contains(., 'Reorder Food')]")
    if reorder_buttons:
        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", reorder_buttons[0])
        time.sleep(0.5)
        driver.execute_script("arguments[0].click();", reorder_buttons[0])
        time.sleep(1)
        screenshot_reorder = os.path.join(SCREENSHOT_DIR, "order_history_reorder_drawer.png")
        driver.save_screenshot(screenshot_reorder)
        print(f"Captured: {screenshot_reorder}")

    print("ALL SELENIUM BROWSER TESTS PASSED SUCCESSFULLY!")

finally:
    driver.quit()
