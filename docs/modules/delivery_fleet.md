# Module: Delivery Partner Fleet & Live Tracking

## 1. Overview
The Delivery Fleet module coordinates rider availability (`staff_profiles.duty_status`), First-Come First-Served (**FCFS**) order allocation, single-order-per-rider concurrency control, live GPS telemetry interpolation, doorstep OTP verification, and COD wallet reconciliation.

## 2. Database Schema & Entities
- `delivery_dispatches`: `dispatch_id`, `order_id` (`UNIQUE`), `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `current_latitude`, `current_longitude`, `delivery_latitude`, `delivery_longitude`, `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`, `assigned_at`, `dispatched_at`, `delivered_at`, `delivery_status` (`ENUM('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED')`).
- `staff_profiles`: `department = 'DELIVERY'`, `duty_status` (`ON_DUTY`, `ON_BREAK`, `OFF_DUTY`, `ON_LEAVE`), `cod_balance`, `total_orders_handled`, `rating`.
- `order_audit_trail`: `updated_by_user_id` records the acting rider or admin ID on every dispatch transition.

## 3. Core Services, Algorithms & Endpoints
- **`includes/FCFSQueue.php`**: `getPrioritizedOrders()`, `getTriageExceptions()` (FIFO ordering by `ready_at` / `placed_at` + SLA breach flagging).
- **`includes/functions.php`**: `autoAllocateReadyOrdersFcfs()`, `isDriverBusy()`, `getDriverActiveDelivery()`, `getDriverAllocatedOrder()`, `getAllDeliveryRiders()`, `getAvailableDeliveryPartner()`, `getRiderEarningsSummary()`, `isRiderAssignedToOrder()`.
- **`includes/Services/FleetService.php`**: `interpolateRoute()`, `toggleRiderDuty()`, `claimOrder()`, `verifyDeliveryOtp()`, `getRiderDashboardData()`, `handleAdminFleetAction()`, `getAdminDeliveryOverviewData()`.
- **Portal & APIs:** `delivery/index.php`, `delivery/login.php`, `delivery/logout.php`, `delivery/ajax/cashout.php`, `api/live-location.php`, `api/live-stream.php`, `admin/ajax/get-fleet-telemetry.php`.

## 4. Technical & Concurrency Rules
1. **One Active Delivery Per Rider:** Both `autoAllocateReadyOrdersFcfs()` and `FleetService::claimOrder()` execute inside `dbTransactional()` locking `orders`, `delivery_dispatches`, and rider `users` rows via `SELECT ... FOR UPDATE` so two riders cannot claim the same order and one rider never receives concurrent overlapping dispatches.
2. **Doorstep OTP & COD Settlement:** `FleetService::verifyDeliveryOtp()` verifies `delivery_dispatches.delivery_otp`, advances `orders.order_status` to `DELIVERED`, sets `delivered_at = NOW()`, updates `payments` / `money_transactions` / rider `staff_profiles.cod_balance` on COD orders, and writes `order_audit_trail` atomically.
3. **RBAC & CSRF:** All `delivery/*` endpoints enforce `requireRider()` and `verifyRequestCsrf()`; `api/live-location.php` enforces order ownership (`canAccessOrder()` / `isRiderAssignedToOrder()`) and CSRF on GPS simulation/update `POST` requests.
