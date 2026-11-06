"""
Capture visual verification screenshots for Mood-Based Food Selection.
"""
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
import time
import os

os.makedirs('pooja_project/tests/screenshots/mood_verification', exist_ok=True)

chrome_options = Options()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--window-size=1600,1100")
chrome_options.add_argument("--disable-gpu")
chrome_options.add_argument("--no-sandbox")

driver = webdriver.Chrome(options=chrome_options)
driver.implicitly_wait(5)

try:
    print("1. Capturing Storefront with Mood-Based Food Selection section...")
    driver.get("http://127.0.0.1:8080/customer/index.php")
    time.sleep(1)
    
    # Scroll to Mood Section
    mood_sec = driver.find_element(By.ID, "moodSection")
    driver.execute_script("arguments[0].scrollIntoView({behavior: 'instant', block: 'center'});", mood_sec)
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/01_storefront_mood_section.png")
    print("   -> Saved: 01_storefront_mood_section.png")

    print("2. Clicking 'Comfort & Soul' mood card...")
    comfort_card = driver.find_element(By.CSS_SELECTOR, '.mood-card[data-mood-id="comfort"]')
    comfort_card.click()
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/02_storefront_filtered_comfort.png")
    print("   -> Saved: 02_storefront_filtered_comfort.png")

    print("3. Testing 'Surprise My Mood'...")
    surprise_btn = driver.find_element(By.ID, "btnSurpriseMood")
    driver.execute_script("arguments[0].scrollIntoView({behavior: 'instant', block: 'center'});", surprise_btn)
    time.sleep(0.5)
    surprise_btn.click()
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/03_storefront_surprise_mood.png")
    print("   -> Saved: 03_storefront_surprise_mood.png")

    print("4. Capturing Search Page with Mood Filter...")
    driver.get("http://127.0.0.1:8080/customer/search.php?mood=spicy")
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/04_search_spicy_mood.png")
    print("   -> Saved: 04_search_spicy_mood.png")

    print("5. Capturing Restaurant Page with Mood Filter...")
    driver.get("http://127.0.0.1:8080/customer/restaurant.php")
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/05_restaurant_page_mood.png")
    print("   -> Saved: 05_restaurant_page_mood.png")

    print("6. Capturing Admin Dishes Catalog with Mood Badges & Filter...")
    # Login as admin
    driver.get("http://127.0.0.1:8080/admin/login.php")
    time.sleep(0.5)
    driver.find_element(By.NAME, "email").send_keys("admin@zippy.com")
    driver.find_element(By.NAME, "password").send_keys("password123")
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    time.sleep(1)

    driver.get("http://127.0.0.1:8080/admin/dishes.php")
    time.sleep(1)
    driver.save_screenshot("pooja_project/tests/screenshots/mood_verification/06_admin_dishes_mood_badges.png")
    print("   -> Saved: 06_admin_dishes_mood_badges.png")

    print("\nVisual verification screenshots captured successfully!")

finally:
    driver.quit()
