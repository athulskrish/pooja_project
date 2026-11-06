# Zippy Food Ordering Platform — Application Verification & Quality Assurance Checklist

**Project Title:** E-Commerce Platform for Food Order  
**Course Code:** MCSP-232 (MCA Major Project) | **University:** IGNOU  
**Student:** Pooja JP | **Enrollment No:** `2500051247`  
**Document Purpose:** Exhaustive QA, Feature Verification, and Viva-Voce Demonstration Checklist across all 4 operational portals, background engines, database state, and academic algorithms.

---

## 📋 Table of Contents
1. [Pre-Requisites & Environment Verification](#1-pre-requisites--environment-verification)
2. [Demo Credentials & Seed State Reference](#2-demo-credentials--seed-state-reference)
3. [Master Gateway & Presentation Hub (index.php)](#3-master-gateway--presentation-hub-indexphp)
4. [Portal 1: Customer Storefront (customer/)](#4-portal-1-customer-storefront-customer)
5. [Portal 2: Kitchen Display System (kitchen/)](#5-portal-2-kitchen-display-system-kitchen)
6. [Portal 3: Delivery Partner Fleet App (delivery/)](#6-portal-3-delivery-partner-fleet-app-delivery)
7. [Portal 4: Admin Management Console (admin/)](#7-portal-4-admin-management-console-admin)
8. [End-to-End Order Lifecycle Flow Test](#8-end-to-end-order-lifecycle-flow-test)
9. [Academic Algorithms & Computational Verification](#9-academic-algorithms--computational-verification)
10. [Database Schema, Integrity & Trigger Checks](#10-database-schema-integrity--trigger-checks)
11. [Automated Test Suite Execution](#11-automated-test-suite-execution)
12. [Troubleshooting & Quick Fixes](#12-troubleshooting--quick-fixes)
13. [QA Sign-Off Sheet](#13-qa-sign-off-sheet)

---

## 1. Pre-Requisites & Environment Verification

Before testing, verify the runtime environment is running correctly:

- [ ] **Apache & MySQL Running:**
  - Open XAMPP Control Panel. Ensure **Apache** (Port `80` or `8080`) and **MySQL** (Port `3306`) are active (green indicators).
- [ ] **Database Connection Check:**
  - Verify [`pooja_project/.env`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/.env) has valid credentials (`DB_HOST=127.0.0.1`, `DB_NAME=zippy_db`, `DB_USER=root`, `DB_PASS=`).
- [ ] **Fresh Database Seeding:**
  - Run the automated seeder via CLI:
    ```powershell
    php database/seed.php
    ```
  - Verify terminal output ends with: `Setup Completed Successfully!`.
  - Alternatively, visit in browser: `http://localhost/pooja_project/database/seed.php`.
- [ ] **Web Server Root Routing:**
  - Navigate to `http://localhost/pooja_project/` and ensure the Master Launchpad loads cleanly with zero PHP warnings or fatal errors.

---

## 2. Demo Credentials & Seed State Reference

All pre-seeded demo accounts share the universal password: **`password123`**. Every portal login page also provides **1-Click Demo Quick-Fill Buttons** for instant evaluation access.

| Portal | Role | Actor Name | Login Email | Active Orders in Seed | Quick Portal Route |
|---|---|---|---|:---:|---|
| **Admin** | Administrator | **Pooja (Admin)** | `admin@zippy.com` | Full Control | [`admin/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php) |
| **Customer 1** | Customer | **Athul (Customer)** | `athul@example.com` | #1001, #1004, #1007 (Active), #1010 (Delivered) | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php) |
| **Customer 2** | Customer | **Amal (Customer)** | `amal@example.com` | #1002, #1005 (Active), #1008 (Delivered) | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php) |
| **Customer 3** | Customer | **Abhijith (Customer)** | `abhijith@example.com` | #1003, #1006 (Active), #1009 (Delivered) | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php) |
| **Kitchen** | Head Cook | **Chef Sajeev** | `chef@zippy.com` | 7 Active Kitchen Orders | [`kitchen/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php) |
| **Delivery 1** | Captain Rider | **Shaji (Rider)** | `shaji@zippy.com` | Available (0/1 Active) | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) |
| **Delivery 2** | Partner Rider | **Kichu (Rider)** | `kichu@zippy.com` | Available (0/1 Active) | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) |
| **Delivery 3** | Partner Rider | **Abhi (Rider)** | `abhi@zippy.com` | Available (0/1 Active) | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) |

### Pre-Seeded 10 Demo Orders Initial State
- **2 In Kitchen KDS Queue (`CONFIRMED`):** Orders `#1001` (Athul) and `#1002` (Amal).
- **3 Cooking on Stoves (`COOKING`):** Orders `#1003` (Abhijith), `#1004` (Athul), and `#1005` (Amal).
- **2 Packed & Ready for Dispatch (`READY`):** Orders `#1006` (Abhijith) and `#1007` (Athul).
- **3 Completed Doorstep Deliveries (`DELIVERED`):** Orders `#1008` (Amal), `#1009` (Abhijith), and `#1010` (Athul) with full audit trails, 5-star reviews, tips, and tax invoices.

---

## 3. Master Gateway & Presentation Hub ([`index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/index.php))

- [ ] **System Status Banner:**
  - Verify green pill indicates: `MySQL Connected & Seeded` with `22 Menu Items` and `10 Orders`.
- [ ] **🎓 Viva Presentation Mode (Multi-Actor Concurrent Isolation):**
  - Click the **"Launch All 4 Tabs"** button.
  - Verify that 4 new browser tabs open:
    1. Customer App (Athul)
    2. Kitchen Station (Chef Sajeev)
    3. Delivery Fleet (Shaji)
    4. Admin Console (Pooja)
  - Verify that logging in or operating in one tab **never logs out or corrupts the session** in the other tabs (handled by portal-isolated cookie namespaces: `ZIPPY_CUSTOMER_SESS`, `ZIPPY_KITCHEN_SESS`, `ZIPPY_RIDER_SESS`, `ZIPPY_ADMIN_SESS`).
- [ ] **Individual Portal Launcher Cards:**
  - Customer Storefront card -> links to [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php).
  - Delivery Partner card -> links to [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php).
  - Kitchen Station card -> links to [`kitchen/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php).
  - Admin Console card -> links to [`admin/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php).
  - BI Reports card -> links to [`admin/reports.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/reports.php).
- [ ] **Figma "Lush Forest" Design System:**
  - Verify brand palette `#243E2C` (Deep Forest Green), `#2E6F40` (Hunter Green), `#68BA7F` (Sage Green), and `#CEFFDC` (Mint Accent).
  - Check typography fonts: *Outfit* (headings) and *Plus Jakarta Sans* (body).

---

## 4. Portal 1: Customer Storefront ([`customer/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php))

### 4.1 Authentication & Profile
- [ ] **Login Page ([`customer/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/login.php)):**
  - Verify 1-Click quick login buttons for Athul, Amal, and Abhijith.
  - Test manual login with email `athul@example.com` and password `password123`.
- [ ] **Registration Page ([`customer/signup.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/signup.php)):**
  - Register a new account with full name, phone number, address line, landmark, and pincode.
  - Verify new record is created in `users` and primary entry in `user_addresses`.
- [ ] **Customer Profile ([`customer/profile.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/profile.php)):**
  - Verify display of customer name, phone, email, and lifetime order metrics.
  - Test editing profile information.
- [ ] **Address Book Management ([`customer/manage-addresses.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/manage-addresses.php)):**
  - Add a new address (Home, Work, or Other).
  - Set default address and test address deletion.

### 4.2 Menu Browsing & Filtering ([`customer/restaurant.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/restaurant.php))
- [ ] **Category Navigation:**
  - Click on category tabs (Biryani & Rice, Curries, Breads & Tandoor, Healthy & Salads, Beverages, Desserts).
  - Verify dishes filter instantly according to the chosen category.
- [ ] **Dietary Filters:**
  - Toggle **"Veg Only"** switch: verify non-veg items hide immediately.
  - Toggle **"Healthy Choices"** switch: verify dishes tagged with calories, protein, and health badges appear.
- [ ] **Live Dish Search ([`customer/search.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/search.php)):**
  - Search for terms like "Biryani", "Paneer", "Lassi", or "Salad".
  - Verify responsive search results.
- [ ] **Apriori Recommendation Engine (Algorithm 1):**
  - Add "Royal Awadhi Mutton Dum Biryani" to the cart.
  - Verify complementary cross-sell recommendations appear (e.g. "Garlic Butter Naan" and "Mango Lassi") mined from historical `order_items` pairing patterns.

### 4.3 Shopping Cart & Calculations ([`customer/cart.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/cart.php))
- [ ] **Quantity Adjustment:**
  - Increment (`+`), decrement (`-`), and remove dishes from cart.
  - Verify item line totals update dynamically.
- [ ] **Coupon Code Engine:**
  - Test entering the following pre-configured coupons:
    - `WELCOME` (₹40 off on first order)
    - `ZIPPY50` (₹50 off on orders above ₹350)
    - `FEAST50` (₹50 off)
    - `HEALTHY50` (₹50 off healthy bowls)
    - `LUSHFOREST` (10% festive discount)
  - Verify discount is deducted from the payable total and success toast appears.
- [ ] **Tax & Service Fee Calculation:**
  - Subtotal matches sum of line items.
  - **GST (5% Restaurant Service)**: Verify exact 2.5% CGST + 2.5% SGST calculation.
  - **Delivery Fee**: Free (₹0.00) if subtotal exceeds ₹499.00; otherwise ₹40.00.
  - **Platform Fee**: ₹5.00 flat fee.
- [ ] **Cooking Instructions / Order Notes:**
  - Enter custom note (e.g. "Extra spicy gravy, please ring doorbell twice").

### 4.4 Checkout & Transactional Concurrency ([`customer/checkout.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/checkout.php))
- [ ] **Address Selection:** Choose delivery address from user's address book.
- [ ] **Payment Methods:**
  - **Cash on Delivery (COD):** Status set to `PENDING`, payment marked as COD.
  - **Online Payment (Razorpay Test Mode / UPI / Card / Net Banking):** Status set to `SUCCESSFUL`.
- [ ] **Atomic Concurrency & Pessimistic Row Locking (Algorithm 4):**
  - Checkout executes inside a database transaction with `FOR UPDATE` row lock on `menu_items`.
  - Stock quantity is decremented atomically; if stock falls to `0`, dish availability is toggled to unavailable.
- [ ] **Delivery OTP Generation (Algorithm 5):**
  - System generates a secure 6-digit random doorstep OTP (e.g. `123456`) stored in `delivery_dispatches`.

### 4.5 Order Confirmation & Celebration ([`customer/order-success.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/order-success.php))
- [ ] Displays celebration badge, generated Order Number (e.g. `#ZIP-1011`), and total amount paid.
- [ ] Highlights the **6-digit Secret Delivery OTP** with instructions to share with courier upon arrival.
- [ ] Direct links: "Track Live Order" and "Download GST Tax Invoice".

### 4.6 Live Order Tracking ([`customer/order-status.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/order-status.php))
- [ ] **Real-Time Status Polling:** Polling via [`customer/ajax/order-status-api.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/ajax/order-status-api.php) updates stepper without page reloads.
- [ ] **4-Stage Stepper:**
  1. `CONFIRMED` (Order Placed & Sent to Kitchen)
  2. `COOKING` (Chef is Preparing Food)
  3. `READY` (Order Packed & Handed to Courier)
  4. `OUT_FOR_DELIVERY` / `DELIVERED` (Live transit & Doorstep delivery)
- [ ] **Dynamic ETA Timer:** Live countdown based on prep time + travel time.
- [ ] **Assigned Courier Card:** Displays Rider Name, phone number, vehicle details, and simulated route distance.

### 4.7 Order History & Tax Invoice
- [ ] **Order History ([`customer/order-history.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/order-history.php)):**
  - Filter tabs: **All Orders**, **Active Orders**, **Past Deliveries**, **Cancelled Orders**.
  - Check seeded orders for Athul: 3 active (#1001, #1004, #1007) and 1 delivered (#1010).
- [ ] **Indian GST Tax Invoice ([`customer/invoice.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/invoice.php)):**
  - Open invoice for order `#1010`.
  - Verify official GSTIN, SAC Code `996331` (Restaurant Service), itemized line items, CGST (2.5%), SGST (2.5%), and print-ready CSS layout.
- [ ] **Rating & Feedback ([`customer/rate-order.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/rate-order.php)):**
  - Open rate order for a completed order.
  - Submit 5-star food rating, rider rating, rider tip (e.g. ₹30), and textual comments.
  - Verify record is saved in `order_reviews` and `dish_reviews`.

---

## 5. Portal 2: Kitchen Display System ([`kitchen/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php))

### 5.1 Authentication & Station Setup
- [ ] **Login ([`kitchen/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/login.php)):**
  - Quick login as **Chef Sajeev** (`chef@zippy.com` / `password123`).
  - Verify redirect to KDS dashboard.

### 5.2 Active Kitchen Queue & WSJF Triage (Algorithm 2)
- [ ] **Live Order Cards Display:**
  - Displays orders grouped or ordered by FCFS / WSJF:
    - **`CONFIRMED` Orders (Awaiting Preparation):** Orders `#1001`, `#1002`.
    - **`COOKING` Orders (In Prep on Stoves):** Orders `#1003`, `#1004`, `#1005`.
    - **`READY` Orders (Packed / Awaiting Rider Pickup):** Orders `#1006`, `#1007`.
- [ ] **Station Badging:**
  - Verify item badges indicate preparation station: `Curry & Tandoor`, `Salad & Cold Bar`, `Beverage Bar`.
- [ ] **Dynamic SLA Timers & Overdue Warnings:**
  - Check overdue badges on aged orders (e.g. `+10m OVERDUE` on `#1005` or `+7m OVERDUE` on `#1004`).
  - Orders approaching SLA deadline show warning badge with remaining minutes.
- [ ] **Order Status Transitions:**
  - On a `CONFIRMED` ticket (e.g. `#1001`), click **"Start Cooking"** -> moves status to `COOKING`.
  - On a `COOKING` ticket (e.g. `#1004`), click **"Mark Ready & Pack"** -> moves status to `READY`.
- [ ] **Printable Thermal KOT Slip ([`kitchen/kot.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/kot.php)):**
  - Click "Print KOT" on any active docket.
  - Verify standard 80mm thermal receipt format displays Order Number, Timestamp, Table/Customer Name, Item Quantities, and Chef Notes.
- [ ] **86-List / Stock Toggle Drawer ([`kitchen/ajax/toggle-stock.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/ajax/toggle-stock.php)):**
  - Open 86 stock drawer in KDS.
  - Toggle an item (e.g. "Kashmiri Kahwa") to Out of Stock (`is_available = 0`).
  - Check customer storefront: verify dish is grayed out and cannot be added to cart.
- [ ] **Chef Salary & Payslip Tab:**
  - View staff base salary (₹35,000/mo), duty status (`ON_DUTY`), shift timing (`09:00 AM - 05:00 PM`), and recent salary disbursements.

---

## 6. Portal 3: Delivery Partner Fleet App ([`delivery/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php))

### 6.1 Authentication & Duty Toggle
- [ ] **Login ([`delivery/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/login.php)):**
  - Quick login as **Shaji (Rider)** (`shaji@zippy.com` / `password123`).
- [ ] **Duty Toggle Switch:**
  - Toggle switch between **"Online / On Duty"** and **"Offline / Off Duty"**.
  - Verify status changes in `staff_profiles.duty_status`.

### 6.2 "One Driver One Delivery" Concurrency Rule
- [ ] **Single Active Delivery Limit:**
  - Verify each driver can only handle **at most 1 active order** at any given moment.
  - When Shaji accepts an order, his status changes to `BUSY (1/1)` and no other dispatch can be assigned to him until completion.
  - Run CLI verification:
    ```powershell
    php tests/test_one_driver_one_delivery.php
    ```
    Must output: `ALL TESTS PASSED: ONE DRIVER ONE DELIVERY RULE VERIFIED!`.

### 6.3 Active Trip & Doorstep Delivery Handshake (Algorithm 5)
- [ ] **Pickup & In-Transit Stepper:**
  - Order details display: Restaurant pickup address -> Customer delivery address, customer phone call button, and Google Maps waypoint link.
  - Click **"Pickup from Kitchen"** -> transitions dispatch to `IN_TRANSIT` and order status to `OUT_FOR_DELIVERY`.
- [ ] **6-Digit Doorstep OTP Verification:**
  - When courier arrives at customer doorstep, click **"Verify OTP & Complete Delivery"**.
  - Enter an invalid OTP (e.g. `000000`): verify system rejects and warns "Invalid Delivery OTP".
  - Enter customer's valid 6-digit OTP from `order_success.php` or `order-status.php` (e.g. check `delivery_dispatches.delivery_otp` for that order).
  - Verify OTP is accepted:
    - Order status updates to `DELIVERED`.
    - `delivered_at` timestamp recorded.
    - Delivery rider is freed back to `AVAILABLE (0/1)`.
- [ ] **Cash-on-Delivery (COD) Cash Handling:**
  - If the order was COD, rider is prompted to confirm cash collection.
  - Collected amount is added to the driver's `cod_balance` in `staff_profiles` and logged in `money_transactions` as `CUSTOMER_PAYMENT_COD`.
- [ ] **Earnings & Shift History:**
  - Displays daily trip count, base earnings, customer tips collected, and performance rating.
  - "Trip History" tab lists all past delivered orders.

---

## 7. Portal 4: Admin Management Console ([`admin/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php))

### 7.1 Authentication & RBAC Protection
- [ ] **Admin Login ([`admin/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/login.php)):**
  - Login as **Pooja (Admin)** (`admin@zippy.com` / `password123`).
- [ ] **RBAC Access Control:**
  - Attempt accessing `admin/index.php` while logged in as Customer or Rider.
  - Verify system denies access and redirects with 403 / Access Denied message.

### 7.2 Executive Dashboard ([`admin/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php))
- [ ] **KPI Summary Cards:**
  - Verify Today's Gross Revenue (₹), Active Orders count, Total Delivered count, and Low Stock Alerts.
- [ ] **Operational Triage & SLA Alerts:**
  - Check warning cards flagging overdue cooking orders (e.g. `#1005`, `#1004`) and pending courier dispatch (e.g. `#1007`, `#1006`).
  - Verify links navigate directly to filtered orders.

### 7.3 Full Order Lifecycle Management ([`admin/orders.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/orders.php))
- [ ] **Status Filter Tabs:**
  - Filter by `ALL`, `CONFIRMED`, `COOKING`, `READY`, `OUT_FOR_DELIVERY`, `DELIVERED`, `CANCELLED`.
- [ ] **Live Search:** Search by Order ID (`#ZIP-1001`) or customer name.
- [ ] **COD vs Online Paid Indicators:**
  - Verify orders clearly display payment status badge: `ONLINE PAID` (green) vs `COD CASH PAID` / `COD PENDING` (warning).
- [ ] **Order Details & Audit Trail:**
  - Click on an order to view customer details, line items, delivery address, assigned rider, and payment info.
  - Click **"View Audit Trail"**: verify immutable chronological log of state changes (`PLACED` -> `CONFIRMED` -> `COOKING` -> `READY` -> `DELIVERED`) with timestamps and user IDs.
- [ ] **Direct Invoice Button:** Click print invoice icon to open GST Tax Invoice.

### 7.4 Kitchen Staff View ([`admin/kitchen-view.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/kitchen-view.php))
- [ ] View Chef Sajeev's profile, designation (Head Chef), shift timing, and attendance status (`ON_DUTY`).
- [ ] View active cooking queue breakdown.
- [ ] View base salary (₹35,000) and click **"Disburse Salary"** to record monthly payout in `staff_salary_payouts`.

### 7.5 Delivery Fleet View ([`admin/delivery-view.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/delivery-view.php))
- [ ] View fleet roster (Shaji, Kichu, Abhi) with vehicle types and phone numbers.
- [ ] Check driver duty indicators: `AVAILABLE (0/1)` vs `BUSY (1/1)`.
- [ ] Check **COD Cash Held**: view physical cash collected by drivers.
- [ ] Test **"Settle Cash"** action: records cash deposit into store register and resets driver's `cod_balance` to ₹0.00.

### 7.6 Financial Ledger & Day-End Cash Register ([`admin/financial-transactions.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/financial-transactions.php))
- [ ] **Financial Ledger:**
  - Audit all inward and outward cash flows:
    - `CUSTOMER_PAYMENT_ONLINE` (`INWARD`)
    - `CUSTOMER_PAYMENT_COD` (`INWARD`)
    - `COD_DEPOSIT_STORE` (`INWARD`)
    - `STAFF_SALARY_PAYOUT` (`OUTWARD`)
    - `CUSTOMER_REFUND` (`OUTWARD`)
- [ ] **CSV Export:** Click "Export CSV" and verify spreadsheet download contains transaction IDs, categories, flow direction, and amounts.
- [ ] **End-of-Day Cash Register Closure:**
  - View **Expected COD Cash** vs enter **Actual Physical Cash Count**.
  - Enter discrepancy note if variance exists.
  - Click **"Close Daily Register"**: stores immutable closure record in `cash_register_closures`.

### 7.7 Catalog & Dish CRUD Management
- [ ] **Categories CRUD ([`admin/categories.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/categories.php)):**
  - Add a new food category with name, slug, description, and display order.
  - Edit existing category details.
  - Toggle category active status.
- [ ] **Menu Dishes CRUD ([`admin/dishes.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/dishes.php)):**
  - Add a new dish with category, price (INR ₹), prep time (mins), kitchen station, veg/non-veg flag, and healthy flag.
  - Edit existing dish price and stock reserves.
  - Test real-time stock adjustment modal.

### 7.8 User RBAC, Customer Feedback & FAQs
- [ ] **Users & Staff RBAC ([`admin/users.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/users.php)):**
  - Filter accounts by role: Admin (Role 1), Customer (Role 2), Kitchen (Role 3), Delivery (Role 4).
  - Add new staff account with assigned role.
- [ ] **Customer Reviews ([`admin/reviews.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/reviews.php)):**
  - View food ratings, courier ratings, tips received, and dish reviews.
- [ ] **FAQs & Support Tickets ([`admin/faqs.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/faqs.php)):**
  - Add/edit customer FAQ questions and answers.
  - View open customer support tickets and update resolution status.
- [ ] **System Audit Logs ([`admin/audit-logs.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/audit-logs.php)):**
  - View complete audit log table populated by database triggers.

### 7.9 Executive BI Reports & Demand Forecasting ([`admin/reports.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/reports.php))
- [ ] **Interactive Sales Chart:** Daily gross sales trend powered by Chart.js.
- [ ] **Top 5 Best-Selling Dishes:** Item velocity table with units sold and total item revenue.
- [ ] **SES Demand Forecasting (Algorithm 3):**
  - Single Exponential Smoothing with parameter $\alpha = 0.3$.
  - Displays predicted demand volume for upcoming week and identifies dishes at risk of stockout.
- [ ] **GST Tax Report:**
  - Total GST collected with 5% breakdown: 2.5% CGST + 2.5% SGST.

---

## 8. End-to-End Order Lifecycle Flow Test

Perform this complete live walkthrough to verify end-to-end synchronization across all 4 portals:

1. **Place Order as Customer (Tab 1):**
   - Log in as Athul (`athul@example.com`).
   - Add "Royal Awadhi Mutton Dum Biryani" and "Garlic Butter Naan" to cart.
   - Apply coupon `WELCOME` (₹40 off).
   - Choose COD or Online payment and place order.
   - Note down generated **Order Number** (e.g. `#ZIP-1011`) and **6-Digit Delivery OTP** (e.g. `482915`).
2. **Accept & Cook in Kitchen KDS (Tab 2):**
   - Switch to Kitchen KDS tab (Chef Sajeev).
   - Locate new docket `#ZIP-1011` in `CONFIRMED` column.
   - Click **"Start Cooking"** -> status updates to `COOKING`.
   - Customer tab automatically reflects "Chef is Preparing Food".
   - When timer finishes, click **"Mark Ready & Pack"** -> status updates to `READY`.
3. **Dispatch & Deliver in Rider Fleet App (Tab 3):**
   - Switch to Delivery tab (Shaji).
   - Order `#ZIP-1011` appears ready for pickup.
   - Click **"Pickup from Kitchen"** -> status updates to `OUT_FOR_DELIVERY`.
   - Shaji's status changes to `BUSY (1/1)`.
   - Customer tab live stepper shows "Out for Delivery" with courier details.
   - At destination, Rider clicks **"Verify OTP & Complete Delivery"**.
   - Rider enters customer's 6-digit OTP (`482915`).
   - Order completes -> status updates to `DELIVERED`.
   - Shaji returns to `AVAILABLE (0/1)`.
4. **Audit in Admin Console (Tab 4):**
   - Switch to Admin Orders tab.
   - Locate order `#ZIP-1011`: status shows `DELIVERED`.
   - Open Audit Trail: verify complete timestamped log of each actor's transition.
   - Open Tax Invoice: verify correct GST breakdown.
5. **Customer Review (Tab 1):**
   - In Customer Order History, order `#ZIP-1011` is now in "Completed".
   - Click **"Rate Food & Rider"**: submit 5 stars and ₹20 tip.

---

## 9. Academic Algorithms & Computational Verification

| Algorithm | Implementation File | Verification Step | Expected Result |
|---|---|---|---|
| **Algorithm 1: Apriori Market Basket Engine** | [`includes/AprioriEngine.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/includes/AprioriEngine.php) | Add Biryani in customer menu; observe recommendations drawer | Complementary items (Naan, Lassi, Raita) appear with support/confidence scores |
| **Algorithm 2: WSJF Kitchen Queue Scheduling** | [`includes/WSJFQueue.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/includes/WSJFQueue.php) | Run `php tests/verify_fcfs_triage.php` | Calculates $WSJF = \frac{\text{Cost of Delay}}{\text{Duration}}$ and bubbles delayed tickets to the top |
| **Algorithm 3: SES Demand Forecasting** | [`includes/SESDemandForecast.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/includes/SESDemandForecast.php) | Open [`admin/reports.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/reports.php) | Single Exponential Smoothing ($\alpha = 0.3$) outputs 7-day predicted order quantities |
| **Algorithm 4: Concurrency-Controlled ACID Checkout** | [`customer/checkout.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/checkout.php) | Place concurrent order with row-level locks | Pessimistic `FOR UPDATE` locks prevent overselling and race conditions |
| **Algorithm 5: 6-Digit Cryptographic OTP Handshake** | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) | Attempt delivery completion without OTP vs valid OTP | Wrong OTP rejected; valid OTP transitions status to `DELIVERED` atomically |

---

## 10. Database Schema, Integrity & Trigger Checks

Run through these database verification items:

- [ ] **17 Relational Tables (3NF Normalization):**
  - Verify all tables exist: `roles`, `users`, `user_addresses`, `categories`, `menu_items`, `orders`, `order_items`, `payments`, `delivery_dispatches`, `order_audit_trail`, `order_reviews`, `dish_reviews`, `faqs`, `support_tickets`, `staff_profiles`, `staff_salary_payouts`, `money_transactions`, `cash_register_closures`.
- [ ] **Foreign Key Constraints:**
  - Cascading deletes and referential integrity strictly enforced on `user_id`, `order_id`, `category_id`, and `item_id`.
- [ ] **ACID Audit Trigger ([`trg_after_order_status_update`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/database/seed.php#L83-L94)):**
  - When `orders.order_status` changes, trigger automatically appends a record into `order_audit_trail` with old status, new status, timestamp, and user ID.
- [ ] **Menu Stock Auto-Availability Trigger ([`trg_after_menu_stock_update`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/database/seed.php#L96-L107)):**
  - When `menu_items.stock_quantity <= 0`, trigger automatically sets `is_available = 0`.
- [ ] **Stored Procedures:**
  - `sp_GenerateDailySalesReport(p_report_date)`: Computes daily gross sales, delivered vs cancelled count, and top 5 dishes.
  - `sp_ProcessOrderCheckout(...)`: Atomic checkout transaction with rollback handler.

---

## 11. Automated Test Suite Execution

Execute the built-in automated test suites from the project directory:

### 11.1 PHP CLI Verification Tests
Run in terminal / PowerShell:
```powershell
# 1. Verify Clean Seeding
php database/seed.php

# 2. Verify One Driver One Delivery Rule
php tests/test_one_driver_one_delivery.php

# 3. Verify Kitchen FCFS / WSJF Queue & Overdue Triage
php tests/verify_fcfs_triage.php

# 4. Verify Customer Order Breakdown Query
php tests/test_query.php
```

### 11.2 Python / Selenium End-to-End Browser Tests
If Python and Selenium Chrome driver are installed, start the local server:
```powershell
php -S 127.0.0.1:8080 -t .
```
And run:
```powershell
# 1. Comprehensive Browser Verification Suite (All 4 Portals + Master Gateway)
python tests/browser_check_everything.py

# 2. Staff Views, Financial Ledger & KDS Tabs Test
python tests/test_staff_finance_and_kds.py

# 3. Multi-Browser Isolated Session Test
python tests/test_multi_browser_sessions.py

# 4. Complete Role-Based Access Control Test
python tests/test_rbac.py

# 5. Customer Storefront UI/UX Recommendations Test
python tests/test_customer_storefront_uiux.py

# 6. Healthy & Fit Food Dietary Option Test
python tests/test_healthy_option.py
```

> [!NOTE]
> All browser verification passes automatically save visual snapshot proofs into [`tests/screenshots/browser_verification/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/tests/screenshots/browser_verification/) across 30 UI checkpoints (from Master Gateway to Customer Checkout, Kitchen KDS, Delivery OTP Handshake, and Admin Financial Ledger).

---

## 12. Troubleshooting & Quick Fixes

| Issue Encountered | Root Cause | Exact Solution |
|---|---|---|
| **Database Connection Error** | MySQL service in XAMPP is stopped | Open XAMPP Control Panel and click **Start** next to MySQL. |
| **Access Denied for user 'root'@'localhost'** | MySQL has a root password set | Edit `pooja_project/.env` and update `DB_PASS=your_password`. |
| **Orders or Menu items missing** | Database was dropped or partially seeded | Run `php database/seed.php` in terminal or visit `http://localhost/pooja_project/database/seed.php`. |
| **Logged out of one portal when opening another** | Session cookies colliding | Use the Master Launchpad ([`index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/index.php)) or click **"Launch All 4 Tabs"** which uses portal-isolated cookies. |
| **Driver cannot accept new order** | "One Driver One Delivery" rule active | Driver already has an ongoing dispatch. Complete the current order with customer's 6-digit OTP first. |
| **Changes not visible on page** | Browser cache | Hard-refresh page with `Ctrl + F5` (or `Shift + Reload`). |

---

## 13. QA Sign-Off Sheet

| Test Category | Evaluated By | Date Tested | Status (Pass / Fail) | Notes |
|---|---|---|:---:|---|
| **Environment & Seeder** | Pooja JP | 2026-11-05 | [x] PASS | MySQL 8.0 connected, 17 tables, 10 demo orders seeded cleanly |
| **Master Gateway Hub** | Pooja JP | 2026-11-05 | [x] PASS | All 4 portals accessible, Viva Presentation mode verified |
| **Customer Storefront** | Pooja JP | 2026-11-05 | [x] PASS | Auth with `athul@example.com`, cart, 5% GST, ACID checkout passed |
| **Kitchen KDS Station** | Pooja JP | 2026-11-05 | [x] PASS | WSJF lanes, live state advances, and thermal KOT slip verified |
| **Delivery Fleet App** | Pooja JP | 2026-11-05 | [x] PASS | Rider duty, active trip transit, doorstep OTP handshake verified |
| **Admin Console & Ledger** | Pooja JP | 2026-11-05 | [x] PASS | Orders management, staff roster, financial ledger, and day closure verified |
| **End-to-End Lifecycle** | Pooja JP | 2026-11-05 | [x] PASS | Customer -> Kitchen -> Rider -> Admin synchronized flawlessly |
| **Academic Algorithms** | Pooja JP | 2026-11-05 | [x] PASS | Apriori, WSJF, SES ($\alpha=0.3$), ACID row lock, OTP verified |
| **Database & Triggers** | Pooja JP | 2026-11-05 | [x] PASS | Foreign keys, audit triggers, and stored procedures active |
| **Automated Test Suite** | Pooja JP | 2026-11-05 | [x] PASS | Python Selenium suites & PHP CLI verification scripts passed 100% |

**Final Evaluation Status:** `[x] READY FOR UNIVERSITY SUBMISSION & VIVA-VOCE`  
**Student Signature:** Pooja JP (`2500051247`)  
**Project Guide / Evaluator Signature:** ___________________________
