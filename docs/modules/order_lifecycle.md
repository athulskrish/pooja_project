# Module: Order Lifecycle, Cart & ACID Checkout

## 1. Status State Machine
Defined by `orders.order_status` `ENUM`:
- `PLACED` *(Initial checkout status)*
- `CONFIRMED` *(Admin/kitchen acceptance)*
- `COOKING` *(Kitchen active preparation)*
- `READY` *(Food packed; `orders.ready_at` timestamp recorded & eligible for FCFS dispatch)*
- `OUT_FOR_DELIVERY` *(Claimed or auto-allocated to active `RIDER`)*
- `DELIVERED` *(Doorstep OTP verified)*
- `CANCELLED` *(Customer self-cancel within 60 seconds of `placed_at`, or Admin cancellation)*

## 2. Database & Audit Trail Schema
- `orders`: `order_id`, `order_number`, `user_id`, `address_id`, `subtotal`, `tax_amount`, `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`, `coupon_code`, `order_status`, `ready_at`, `is_scheduled`, `scheduled_for`, `scheduled_slot`, `order_notes`, `placed_at`, `estimated_delivery_time`.
- `order_items`: `order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `line_total`, `customizations`, `customization_total`.
- `payments`: `payment_id`, `order_id`, `payment_mode` (`UPI`, `CREDIT_CARD`, `DEBIT_CARD`, `NET_BANKING`, `COD`), `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`.
- `order_audit_trail`: `audit_id`, `order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`.

## 3. Core Services & Endpoints
- **`includes/Services/OrderService.php`**: `processCheckout()`, `cancelCustomerOrder()`, `getOrderInvoiceData()`, `getOrderSuccessData()`, `getCustomerOrderHistoryData()`, `getOrderStatusData()`, `getRateOrderData()`, `submitOrderRating()`, `getCustomerAddresses()`, `addCustomerAddress()`, `setDefaultCustomerAddress()`, `softDeleteCustomerAddress()`.
- **`includes/functions.php`**: `calculateBillTotals()`, `transitionOrderStatus()`, `dbTransactional()`, `getSessionCart()`, `setSessionCart()`, `clearSessionCart()`.
- **Views & AJAX/Webhooks**: `customer/cart.php`, `customer/checkout.php`, `customer/order-success.php`, `customer/order-status.php`, `customer/order-history.php`, `customer/invoice.php`, `customer/rate-order.php`, `customer/manage-addresses.php`, `customer/ajax/order-status-api.php`, `api/payment-webhook.php`.

## 4. Technical Rules
1. **ACID Checkout & Stock Locking:** `OrderService::processCheckout()` wraps verification, `SELECT ... FROM menu_items WHERE item_id IN (...) ORDER BY item_id ASC FOR UPDATE`, coupon/customization validation (`calculateBillTotals()`), `orders`/`order_items`/`payments`/`money_transactions`/`order_audit_trail` inserts, and stock decrements inside `dbTransactional()`.
2. **Idempotent Audit Logging:** `trg_after_order_status_update` was retired (`database/migrate_drop_audit_trigger.php`) so PHP `transitionOrderStatus()` and service transactions write one accurate row per status change with `updated_by_user_id`.
3. **IDOR & Signature Security:** Customer order views and `customer/ajax/order-status-api.php` enforce `requireCustomer()` + `canAccessOrder()`. `api/payment-webhook.php` verifies `X-Razorpay-Signature` via `hash_hmac('sha256', ...)` against `RAZORPAY_WEBHOOK_SECRET` inside a locked transaction.
4. **Soft-Deleted Address Preservation:** Removing a saved address archives it (`UPDATE user_addresses SET is_active = 0`) so past `orders.address_id` references remain intact.
