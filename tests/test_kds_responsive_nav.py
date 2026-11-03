import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

resolutions = [
    ("desktop_1600", 1600, 900),
    ("laptop_1366", 1366, 768),
    ("tablet_1024", 1024, 768),
]

for name, w, h in resolutions:
    opts = Options()
    opts.add_argument('--headless=new')
    opts.add_argument(f'--window-size={w},{h}')
    driver = webdriver.Chrome(options=opts)
    try:
        driver.get('http://localhost:8080/kitchen/login.php?demo=1')
        time.sleep(1)
        driver.get('http://localhost:8080/kitchen/index.php')
        time.sleep(1.5)
        driver.save_screenshot(f'tests/screenshots/test_nav_{name}.png')
        print(f"Captured {name} ({w}x{h})")
    finally:
        driver.quit()
