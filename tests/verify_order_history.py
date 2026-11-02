import urllib.request
import re

tabs = ["all", "ongoing", "completed", "cancelled"]

for t in tabs:
    url = f"http://localhost:8080/customer/order-history.php?tab={t}"
    req = urllib.request.urlopen(url)
    html = req.read().decode('utf-8')
    
    assert req.status == 200, f"Failed for tab {t}"
    
    # Check that tab button is active
    assert f'id="tab-{t}-btn"' in html, f"Button tab-{t}-btn not found"
    assert f'id="tab-{t}-pane"' in html, f"Pane tab-{t}-pane not found"
    
    # Check button has active class
    btn_match = re.search(rf'<button[^>]*id="tab-{t}-btn"[^>]*>', html)
    if not btn_match:
        btn_match = re.search(rf'<button[^>]*class="[^"]*"[^>]*id="tab-{t}-btn"[^>]*>', html)
    btn_tag = btn_match.group(0) if btn_match else ""
    assert "active" in btn_tag, f"Button tag for tab {t} is: {btn_tag}, expected 'active'"
    
    # Check pane has show active class
    pane_match = re.search(rf'<div[^>]*id="tab-{t}-pane"[^>]*>', html)
    pane_tag = pane_match.group(0) if pane_match else ""
    assert "show active" in pane_tag, f"Pane tag for tab {t} is: {pane_tag}, expected 'show active'"
    
    print(f"PASS: Tab {t} verified active button and active pane.")

# Check features
url = "http://localhost:8080/customer/order-history.php"
html = urllib.request.urlopen(url).read().decode('utf-8')

assert "Customer Order History" in html, "Header title missing"
assert "reorderItems" in html, "Reorder function missing"
assert "orderSearchInput" in html, "Search input missing"
assert "cancelConfirmModal" in html, "Cancellation modal missing"
assert "Live Tracker" in html, "Live Tracker button missing"
assert "Tax Invoice" in html, "Tax Invoice button missing"
assert "badge-count-ongoing" in html, "Ongoing count badge missing"
assert "badge-count-completed" in html, "Completed count badge missing"
assert "badge-count-cancelled" in html, "Cancelled count badge missing"

print("ALL ORDER HISTORY ASSERTIONS PASSED SUCCESSFULLY!")
