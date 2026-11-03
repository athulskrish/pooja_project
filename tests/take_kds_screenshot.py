import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

options = Options()
options.add_argument('--headless=new')
options.add_argument('--window-size=1600,1000')
driver = webdriver.Chrome(options=options)

try:
    driver.get('http://localhost:8080/kitchen/login.php?demo=1')
    time.sleep(2)
    driver.get('http://localhost:8080/kitchen/index.php')
    time.sleep(2)
    driver.save_screenshot('tests/screenshots/current_kds_view.png')
    print('Screenshot saved successfully!')
finally:
    driver.quit()
