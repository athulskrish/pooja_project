# 🚴 Module: Delivery Fleet & Dispatch (delivery_fleet.md)

> **Context Domain:** Rider Allocation, FCFS Dispatch Queue, Geolocation Tracking & OTP Verification  
> **Relevant Tables:** `delivery_dispatches`, `orders`, `users`, `order_audit_trail`

---

## 1. Fleet Architecture & Dispatch Triage

The delivery portal (`delivery/`) enables registered riders to accept and fulfill food deliveries. Allocation is governed by the **First-Come-First-Served (FCFS) Triage Queue** (`includes/FCFSQueue.php`):
- Single active delivery per rider enforcement (one driver cannot hoard multiple concurrent active orders).
- Orders in `READY_FOR_PICKUP` status appear on the rider radar.
- When a rider taps "Accept", the order is locked and assigned.
- Upon reaching the customer, the rider inputs the customer's 6-digit OTP to complete fulfillment.

---

## 2. Key Source Files

- **`delivery/index.php`**: Rider dashboard, available ready orders radar, and active delivery controls.
- **`delivery/login.php` & `delivery/logout.php`**: Rider identity authentication (`role_id = 4`).
- **`api/live-location.php`**: Receives GPS telemetry (`latitude`, `longitude`) via navigator.geolocation.
- **`includes/FCFSQueue.php`**: Concurrency-safe rider dispatch allocation logic.

---

## 3. Engineering Invariants

1. **Race Condition Prevention:** Rider claiming an order must execute with a pessimistic lock (`FOR UPDATE`) on `orders` and `delivery_dispatches` to prevent duplicate claims.
2. **OTP Verification:** Transition to `DELIVERED` must strictly check:
   ```php
   if ($submittedOtp !== $order['delivery_otp']) {
       throw new RuntimeException("Invalid OTP provided.");
   }
   ```
3. **Audit Trail Sync:** State changes to `OUT_FOR_DELIVERY` and `DELIVERED` must be logged in `order_audit_trail` with `changed_by_user_id = $riderId`.
