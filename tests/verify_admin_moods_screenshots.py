"""
Capture visual verification screenshots for Admin Moods Management Workspace.
"""
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
import time
import os

os.makedirs('pooja_project/tests/screenshots/admin_moods_verification', exist_ok=True)

chrome_options = Options()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--window-size=1600,1100")
chrome_options.add_argument("--disable-gpu")
chrome_options.add_argument("--no-sandbox")

driver = webdriver.Chrome(options=chrome_options)
driver.implicitly_wait(5)

try:
    print("1. Logging into Admin Portal...")
    driver.get("http://127.0.0.1:8080/admin/login.php")
    time.sleep(0.5)
    driver.find_element(By.NAME, "email").send_keys("admin@zippy.com")
    driver.find_element(By.NAME, "password").send_keys("password123")
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    time.sleep(1)

    print("2. Capturing Admin Food Moods Workspace...")
    driver.get("http://127.0.0.1:8080/admin/moods.php")
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/admin_moods_verification/01_admin_moods_catalog.png")
    print("   -> Saved: 01_admin_moods_catalog.png")

    print("3. Opening Edit Mood & Photo modal...")
    edit_btn = driver.find_element(By.CSS_SELECTOR, "button[onclick*='openEditMoodModal']")
    edit_btn.click()
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/admin_moods_verification/02_admin_edit_mood_modal.png")
    print("   -> Saved: 02_admin_edit_mood_modal.png")

    # Close modal
    driver.find_element(By.CSS_SELECTOR, "#editMoodModal .btn-close").click()
    time.sleep(0.5)

    print("4. Opening Manage Dishes modal...")
    manage_btn = driver.find_element(By.CSS_SELECTOR, "button[onclick*='openManageDishesModal']")
    manage_btn.click()
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/admin_moods_verification/03_admin_manage_dishes_modal.png")
    print("   -> Saved: 03_admin_manage_dishes_modal.png")

    print("\nAll Admin Moods verification screenshots captured successfully!")

finally:
    driver.quit()
