# 📦 Module: Order Lifecycle & Payments (order_lifecycle.md)

> **Context Domain:** Cart State, Bill Calculation, Checkout, Payment Gateways & Status Transitions  
> **Relevant Tables:** `orders`, `order_items`, `payments`, `order_audit_trail`, `user_addresses`

---

## 1. Lifecycle State Machine

Orders transition deterministically through these states:
1. `PENDING`: Order created upon checkout; waiting for payment confirmation.
2. `CONFIRMED`: Payment captured or COD selected; stock deducted.
3. `PREPARING`: Kitchen team picks order and initiates cooking.
4. `READY_FOR_PICKUP`: Cooking finished; assigned for rider collection.
5. `OUT_FOR_DELIVERY`: Rider collected order; OTP shared with customer.
6. `DELIVERED`: Rider enters 6-digit OTP; order marked fulfilled.
7. `CANCELLED`: Order aborted due to customer action or out-of-stock event.

---

## 2. Key Source Files

- **`customer/cart.php`**: Cart review, quantity updates, bill breakdown (Item Total, Tax 5%, Delivery Fee, Packaging).
- **`customer/checkout.php`**: Delivery address selection, payment mode selection (COD / Online), order creation.
- **`customer/order-status.php`**: Real-time customer tracking view with animated progression tracker.
- **`customer/invoice.php`**: Formal tax invoice generator.
- **`api/payment-webhook.php`**: Callback handler verifying digital transaction references and transitioning order from `PENDING` to `CONFIRMED`.
- **`includes/functions.php`**: Core bill calculation logic, coupon verification, order placement transaction wrappers.

---

## 3. Concurrency & Conformance Rules

1. **Transaction Wrapping:** `INSERT INTO orders` and `INSERT INTO order_items` must always run within an ACID transaction (`$pdo->beginTransaction()`).
2. **Pessimistic Locking on Inventory:** Before order insertion, `SELECT stock_quantity FROM menu_items WHERE item_id = :id FOR UPDATE` to avoid overselling during high concurrent checkout spikes.
3. **Audit Trail Invariant:** Every status change must insert a row into `order_audit_trail` documenting `from_status`, `to_status`, `changed_by_user_id`, and `notes`.
4. **OTP Security:** The 6-digit `delivery_otp` must be cryptographically generated (`random_int(100000, 999999)`), stored securely, and verified by the rider upon delivery.
