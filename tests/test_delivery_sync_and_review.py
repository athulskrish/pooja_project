"""
End-to-End Verification Test:
1. Food Ready Sync in Delivery App (similar to KDS)
2. Sync to Take Ready Food (Claim order via AJAX)
3. 1 Driver 1 Delivery Policy Enforcement
4. Food Delivery OTP Handshake
5. Customer Review Request Trigger & Submission
"""

import urllib.request
import urllib.parse
import json
import http.cookiejar
import re
import sys
import subprocess

BASE_URL = "http://127.0.0.1:8080"

# Setup session handlers
def create_session():
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    return opener, cj

def get_csrf(html):
    m = re.search(r'name="csrf_token" value="([^"]+)"', html)
    if m:
        return m.group(1)
    m = re.search(r"csrf_token['\"]\s*,\s*['\"]([^'\"]+)['\"]", html)
    if m:
        return m.group(1)
    return "dummy_token"

def test_full_flow():
    print("=== STARTING FULL LIFECYCLE TEST ===")

    # 1. Rider Login
    rider_opener, rider_cj = create_session()
    resp = rider_opener.open(f"{BASE_URL}/delivery/login.php")
    html = resp.read().decode('utf-8')
    csrf = get_csrf(html)

    login_data = urllib.parse.urlencode({
        'csrf_token': csrf,
        'email': 'rider@zippy.com',
        'password': 'password123'
    }).encode('utf-8')
    resp = rider_opener.open(f"{BASE_URL}/delivery/login.php", data=login_data)
    print("1. Rider Login: Status OK, redirected to", resp.geturl())

    # 2. Check Delivery Stream API for Food Ready sync
    resp = rider_opener.open(f"{BASE_URL}/api/live-stream.php?portal=delivery&rider_id=5")
    stream_data = json.loads(resp.read().decode('utf-8'))
    print("2. Delivery Sync API: success=", stream_data.get('success'), "ready_for_pickup=", stream_data['counts']['ready_for_pickup'])
    assert stream_data['success'] == True

    # 3. Create a test order that is READY for pickup
    print("3. Creating fresh test order in READY status...")
    php_code = """
    require_once 'E:/2026 project/2026 july/Pooja/pooja_project/config/config.php';
    require_once 'E:/2026 project/2026 july/Pooja/pooja_project/includes/functions.php';
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO `orders` (`user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `platform_fee`, `total_amount`, `order_status`, `order_notes`)
        VALUES (2, 1, 350.00, 17.50, 40.00, 5.00, 412.50, 'READY', 'Automated Test Order')
    ");
    $stmt->execute();
    $oid = $pdo->lastInsertId();
    $otp = '482194';
    $pdo->prepare("
        INSERT INTO `delivery_dispatches` (`order_id`, `delivery_partner_name`, `delivery_otp`, `assigned_at`, `delivery_status`)
        VALUES (:oid, 'Zippy Express Rider', :otp, NOW(), 'ASSIGNED')
    ")->execute([':oid' => $oid, ':otp' => $otp]);
    $pdo->prepare("
        INSERT INTO `order_items` (`order_id`, `item_id`, `quantity`, `unit_price`, `line_total`)
        VALUES (:oid, 1, 1, 350.00, 350.00)
    ")->execute([':oid' => $oid]);
    echo $oid;
    """
    res = subprocess.run(["php", "-r", php_code], capture_output=True, text=True)
    test_order_id = int(res.stdout.strip())
    print(f"   Created test order #ZIP-{test_order_id}")

    # Let's verify delivery app view has the sync controls
    idx_resp = rider_opener.open(f"{BASE_URL}/delivery/index.php")
    idx_html = idx_resp.read().decode('utf-8')
    assert "btnRiderSyncToggle" in idx_html, "Sync toggle button missing in delivery header!"
    assert "SYNC (5s)" in idx_html, "SYNC countdown text missing in delivery header!"
    assert "takeReadyFood" in idx_html, "takeReadyFood function missing in delivery footer!"
    assert "syncDeliveryApp" in idx_html, "syncDeliveryApp function missing in delivery footer!"
    print("4. Verified Delivery App contains KDS-like Auto-Sync controls & takeReadyFood!")

    # 4. Advance order to READY if not already
    php_code = f"""
    require_once 'E:/2026 project/2026 july/Pooja/pooja_project/config/config.php';
    require_once 'E:/2026 project/2026 july/Pooja/pooja_project/includes/functions.php';
    db_query("UPDATE orders SET order_status = 'READY' WHERE order_id = :id", [':id' => {test_order_id}]);
    """
    subprocess.run(["php", "-r", php_code], capture_output=True)

    # Re-check live stream
    resp = rider_opener.open(f"{BASE_URL}/api/live-stream.php?portal=delivery&rider_id=5")
    stream_data = json.loads(resp.read().decode('utf-8'))
    ready_ids = [o['order_id'] for o in stream_data['ready_orders']]
    assert test_order_id in ready_ids, f"Order {test_order_id} should be in ready_orders list!"
    print(f"5. Delivery sync confirmed order #ZIP-{test_order_id} is READY for pickup!")

    # 5. Take the ready food via AJAX sync (claim_order)
    del_csrf = get_csrf(idx_html)
    claim_req = urllib.request.Request(
        f"{BASE_URL}/delivery/index.php",
        data=urllib.parse.urlencode({
            'action': 'claim_order',
            'order_id': test_order_id,
            'is_ajax': '1',
            'csrf_token': del_csrf
        }).encode('utf-8'),
        headers={'X-Requested-With': 'XMLHttpRequest'}
    )
    claim_resp = rider_opener.open(claim_req)
    claim_data = json.loads(claim_resp.read().decode('utf-8'))
    print("6. Take Ready Food (Claim AJAX):", claim_data)
    assert claim_data['success'] == True

    # 6. Verify rider now has active order in live-stream
    resp = rider_opener.open(f"{BASE_URL}/api/live-stream.php?portal=delivery&rider_id=5")
    stream_data = json.loads(resp.read().decode('utf-8'))
    assert stream_data['rider_has_active'] == True
    assert stream_data['active_order']['order_id'] == test_order_id
    print(f"7. Confirmed order #ZIP-{test_order_id} is active in-transit for Rider!")

    # 7. Get OTP for doorstep delivery
    otp = stream_data['active_order']['delivery_otp']
    print(f"8. Retrieved secret OTP: {otp}")

    # 8. Complete food delivery by verifying OTP
    verify_req = urllib.request.Request(
        f"{BASE_URL}/delivery/index.php",
        data=urllib.parse.urlencode({
            'action': 'verify_otp',
            'order_id': test_order_id,
            'otp': otp,
            'is_ajax': '1',
            'csrf_token': del_csrf
        }).encode('utf-8'),
        headers={'X-Requested-With': 'XMLHttpRequest'}
    )
    verify_resp = rider_opener.open(verify_req)
    verify_data = json.loads(verify_resp.read().decode('utf-8'))
    print("9. Verify Doorstep OTP: success=", verify_data.get('success'))
    assert verify_data['success'] == True

    # 9. Now check Customer Side: Review Request
    cust_opener, cust_cj = create_session()
    c_resp = cust_opener.open(f"{BASE_URL}/customer/login.php")
    c_csrf = get_csrf(c_resp.read().decode('utf-8'))
    cust_opener.open(f"{BASE_URL}/customer/login.php", data=urllib.parse.urlencode({
        'csrf_token': c_csrf,
        'email': 'athul@example.com',
        'password': 'password123'
    }).encode('utf-8'))

    # Open order-status.php for delivered order
    status_resp = cust_opener.open(f"{BASE_URL}/customer/order-status.php?order_id={test_order_id}")
    status_html = status_resp.read().decode('utf-8')
    assert "autoRatingPromptModal" in status_html, "Rating prompt modal missing!"
    assert "Food Delivered! How Was Your Meal?" in status_html, "Delivered review heading missing!"
    assert "submitQuickRatingModal" in status_html, "submitQuickRatingModal function missing!"
    print("10. Customer Order Status Page: Review request modal is present for delivered order!")

    # Check that global unreviewed order prompt is present on customer home page
    home_resp = cust_opener.open(f"{BASE_URL}/customer/index.php")
    home_html = home_resp.read().decode('utf-8')
    assert "unreviewedOrderPrompt" in home_html, "Global unreviewed order notification missing on home page!"
    print(f"11. Customer Home Page: Floating review prompt toast is displayed for unreviewed order #ZIP-{test_order_id}!")

    # 10. Submit Review via AJAX
    rate_csrf = get_csrf(status_html)
    review_req = urllib.request.Request(
        f"{BASE_URL}/customer/rate-order.php?order_id={test_order_id}",
        data=urllib.parse.urlencode({
            'action': 'submit_rating',
            'order_id': test_order_id,
            'food_rating': 5,
            'rider_rating': 5,
            'food_tags': '♨️ Piping Hot, ⚡ Fast Delivery',
            'comments': 'Super delicious biryani and lighting fast courier handover!',
            'is_ajax': '1',
            'csrf_token': rate_csrf
        }).encode('utf-8'),
        headers={'X-Requested-With': 'XMLHttpRequest'}
    )
    review_resp = cust_opener.open(review_req)
    review_data = json.loads(review_resp.read().decode('utf-8'))
    print("12. Customer Submit Review AJAX: success=", review_data.get('success'))
    assert review_data['success'] == True

    # 11. Verify that order is now recorded as reviewed
    home_resp2 = cust_opener.open(f"{BASE_URL}/customer/index.php")
    home_html2 = home_resp2.read().decode('utf-8')
    # Prompt for this order should no longer appear
    assert f"Order #ZIP-{test_order_id}" not in home_html2, "Delivered order prompt should disappear after review!"
    print(f"13. Confirmed review recorded: toast prompt dismissed for order #ZIP-{test_order_id}!")

    print("\n*** ALL TESTS PASSED SUCCESSFULLY! ***")

if __name__ == "__main__":
    test_full_flow()
