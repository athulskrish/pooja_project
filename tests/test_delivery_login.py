import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. GET login page
resp = opener.open('http://127.0.0.1:8080/delivery/login.php')
html = resp.read().decode('utf-8')
csrf = re.search(r'name="csrf_token" value="([^"]+)"', html).group(1)
print('Got CSRF:', csrf)

# 2. POST login
post_data = urllib.parse.urlencode({
    'csrf_token': csrf,
    'email': 'rider@zippy.com',
    'password': 'password123'
}).encode('utf-8')

req = urllib.request.Request('http://127.0.0.1:8080/delivery/login.php', data=post_data)
resp = opener.open(req)
print('Redirected to URL:', resp.geturl())
content = resp.read().decode('utf-8')
print('Content length:', len(content))
print('--- FIRST 500 CHARS OF RESPONSE ---')
print(content[:500])
print('--- END PREVIEW ---')

# Check cookies
print('Cookies in jar:')
for cookie in cj:
    print(f"  {cookie.name} = {cookie.value} (path={cookie.path}, domain={cookie.domain})")
