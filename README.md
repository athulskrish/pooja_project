# Zippy - Online Food Ordering Platform
**Student:** Pooja JP | **Course:** MCSP-232 (MCA Major Project) | **University:** IGNOU  
**Design System:** Figma Resource Library "Lush Forest" Color Palette (#Combination-4)  
**Implementation Phase:** Step 2 Completed - Full PHP Backend & Frontend with MySQL 8.0 3NF Relational Database  

---

## 🌟 Architectural & Implementation Summary

The system consists of dedicated role-based web applications implemented in native, modern PHP with shared 3NF database configuration, ACID transaction management, and Master-level computational algorithms:
- **Customer Storefront** (`customer/`)
- **Delivery Partner Fleet App** (`delivery/`)
- **Kitchen Display System (KDS)** (`kitchen/`)
- **Admin Operations Console** (`admin/`)

```
pooja_project/
├── index.php                  # Central Portals Launchpad & Architecture Hub
├── README.md                  # Comprehensive Documentation & Architecture Guide
├── SYSTEM_ANALYSIS_AND_DESIGN.md # DFD, ERD, Use Cases & 3NF Normalization
│
├── config/                    # Shared System Configuration
│   ├── config.php             # Constants, session security, CSRF protection, URL detection
│   └── database.php           # PDO MySQL singleton connection with diagnostic status
│
├── database/                  # 3NF Relational Database Schema & Seeder
│   ├── schema.sql             # 10-table 3NF schema, ACID triggers, foreign keys & seed data
│   └── seed.php               # 1-click web/CLI automated installer & password rehasher
│
├── includes/                  # Shared Business Logic & Computational Algorithms
│   ├── functions.php          # Database wrappers, bill calculations & coupon logic
│   ├── auth.php               # Authentication, Argon2id/Bcrypt hashing & RBAC claims
│   ├── AprioriEngine.php      # Algorithm 1: Market Basket Analysis Cross-Selling
│   ├── WSJFQueue.php          # Algorithm 2: Dynamic WSJF Kitchen Queue Prioritization
│   └── SESDemandForecast.php  # Algorithm 3: Single Exponential Smoothing Demand Forecast
│
├── assets/                    # Shared Figma "Lush Forest" Assets
│   ├── css/style.css          # CSS custom properties, responsive styles & components
│   └── js/app.js              # Client state, persistent cart, toasts & dynamic drawer
│
├── customer/                  # 🛒 Customer Food Ordering Storefront
│   ├── index.php              # Storefront home, hero carousel, categories, menu catalog
│   ├── restaurant.php         # Restaurant menu, veg switch & sticky sidebar cart
│   ├── cart.php               # Dedicated cart, coupon validation & bill breakdown
│   ├── checkout.php           # Multi-step checkout with ACID transaction & row locking
│   ├── order-success.php      # Order celebration, 6-digit delivery OTP & direct links
│   ├── order-status.php       # Live ETA countdown, 4-stage stepper & courier card
│   ├── order-history.php      # Past orders list, status badges & invoice access
│   ├── invoice.php            # Indian GST Tax Invoice (SAC 996331, 5% GST breakdown)
│   ├── search.php             # Real-time search, filters (veg, price, prep time)
│   ├── offers.php             # Coupons hub (`LUSHFOREST`, `ZIPPY50`, `WELCOME`)
│   ├── profile.php            # Account center, personal details edit & stats
│   ├── manage-addresses.php   # Address book CRUD for `user_addresses` (3NF)
│   ├── rate-order.php         # 5-star rating for food & delivery partner with tips
│   ├── help.php               # 24x7 FAQs accordions & support ticket form
│   ├── login.php              # Customer login with 1-click demo autofill
│   ├── signup.php             # New customer registration with address setup
│   ├── logout.php             # Secure session destruction
│   ├── includes/
│   │   ├── header.php         # Customer navbar, location modal trigger & cart badge
│   │   └── footer.php         # Offcanvas cart drawer, mobile bar & footer credits
│   └── ajax/
│       ├── cart-action.php    # AJAX cart synchronizer
│       └── order-status-api.php # Live order status polling endpoint
│
├── delivery/                  # 🚴 Delivery Partner Fleet Web App (Delivery Boy)
│   ├── index.php              # Rider workspace (Duty toggle, active dispatch, trip history, earnings)
│   ├── login.php              # Fleet partner secure login with 1-click demo access
│   └── logout.php             # Secure rider session termination
│
├── kitchen/                   # 🍳 Dedicated Kitchen Display System (KDS) & Chef Station
│   ├── index.php              # Standalone KDS (Accept order, WSJF prep, Handover to rider)
│   ├── login.php              # Dedicated Chef & Cook authentication portal
│   ├── logout.php             # Kitchen session signoff
│   ├── kot.php                # Printable thermal Kitchen Order Ticket slip
│   └── ajax/
│       ├── update-status.php  # AJAX order transition handler (Cooking, Ready, Handover)
│       ├── poll-orders.php    # Live order poller & audio chime listener
│       └── toggle-stock.php   # Instant 86-list item availability toggle
│
└── admin/                     # 👨‍💼 Admin Management & Kitchen Console
    ├── index.php              # Executive Dashboard with KPI cards & live orders
    ├── orders.php             # Full Order Management & status lifecycle transitions
    ├── kitchen.php            # Quick redirect to dedicated Kitchen Station
    ├── categories.php         # Food Categories CRUD (slugs, display order, active toggle)
    ├── dishes.php             # Menu Catalog CRUD (pricing, stock reserves, prep time)
    ├── users.php              # User & Staff RBAC (Admin, Kitchen Chef, Delivery Rider)
    ├── reports.php            # Executive BI Reports (Chart.js + SES Demand Forecasting)
    ├── login.php              # Staff & Admin secure authentication
    ├── logout.php             # Admin session logout
    ├── includes/
    │   ├── header.php         # Admin sidebar, responsive topbar & role verification
    │   └── footer.php         # Admin layout closing scripts & toast engine
    └── ajax/
        └── update-order-status.php # Real-time AJAX status updates
```

---

## 🔑 Standard Pre-Seeded Demo Credentials

All demo accounts share the standard password: `password123`

| Role | Name | Email | Password | Access Portal |
| :--- | :--- | :--- | :--- | :--- |
| **ADMIN** | Pooja JP (Admin) | `admin@zippy.com` | `password123` | [`admin/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php) |
| **CUSTOMER** | Pooja JP (Customer) | `pooja@example.com` | `password123` | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php) |
| **CUSTOMER** | Rahul Sharma | `rahul@example.com` | `password123` | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php) |
| **KITCHEN** | Chef Sanjeev | `chef@zippy.com` | `password123` | [`kitchen/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php) |
| **RIDER** | Suresh Kumar | `rider@zippy.com` | `password123` | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) |

---

## 🗄️ Database Setup & Installation

To initialize or reset the database:
1. Open XAMPP / MariaDB / MySQL.
2. In your browser, navigate to:  
   `http://localhost/Pooja/pooja_project/database/seed.php`  
   *(or run via terminal: `php database/seed.php`)*
3. The automated seeder will create `zippy_db`, create all 10 normalized tables (3NF), install audit triggers, and populate initial seed records with encrypted passwords.

---

## 🧠 Master's Degree (MCA) Algorithmic Depth

1. **Market Basket Analysis (Apriori Algorithm) - `includes/AprioriEngine.php`:**
   - Scans historical `order_items` transactional pairs.
   - Calculates item support and confidence to recommend complementary items (e.g. Biryani + Garlic Naan + Mango Lassi).

2. **Weighted Shortest Job First (WSJF) Dynamic Kitchen Queue - `includes/WSJFQueue.php`:**
   $$\text{WSJF Score} = \frac{\text{Cost of Delay (Elapsed Wait Time)}}{\text{Job Duration (Max Prep Time + Portions)}}$$
   - Ensures orders experiencing delay automatically bubble to the top of the kitchen Kanban queue.

3. **Single Exponential Smoothing (SES) Demand Forecasting - `includes/SESDemandForecast.php`:**
   $$\hat{Y}_{t+1} = \alpha Y_t + (1 - \alpha) \hat{Y}_t \quad (\alpha = 0.3)$$
   - Analyzes historical dish velocity to forecast tomorrow and weekend demand, flagging dishes at risk of stockouts.

4. **High-Concurrency ACID Checkout with Pessimistic Row Locking - `customer/checkout.php`:**
   - Utilizes `SELECT ... FOR UPDATE` inside `START TRANSACTION ... COMMIT / ROLLBACK` to prevent overselling of limited food portions under race conditions.

5. **Cryptographic Doorstep Handshake (6-Digit OTP Verification) - `delivery/index.php`:**
   - Orders create a random 6-digit cryptographic verification OTP in `delivery_dispatches`.
   - The delivery partner cannot complete an order without entering this customer-held secret code, preventing false delivery disputes and guaranteeing physical handover.

---

## 🚴 Delivery Partner Fleet Portal (Delivery Boy)

The Delivery Partner subsystem is implemented as a standalone web application under [`delivery/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) mapped directly to Use Cases **UC-14 through UC-17**:

1. **UC-14: Toggle Online/Offline Duty:**
   - Rider can toggle duty mode from the top bar. When offline, dispatches are paused.
2. **UC-15: Accept Delivery Assignment / Claim Order:**
   - Rider views available orders marked `READY` by the kitchen and clicks **"Accept & Pick Up Order"**.
   - Updates `orders.order_status = 'OUT_FOR_DELIVERY'`, assigns courier credentials to `delivery_dispatches`, and logs to `order_audit_trail`.
3. **UC-16: Turn-by-Turn GPS Route Navigation:**
   - Interactive modal rendering turn-by-turn route, simulated speed, distance, and direct 1-click customer phone dialer (`tel:`).
4. **UC-17: Doorstep 6-Digit OTP Verification & Cash Settlement:**
   - Verification modal checks submitted OTP against `delivery_dispatches.delivery_otp`.
   - On match: transitions order to `DELIVERED`, stamps `delivered_at = NOW()`, settles COD cash payments to `SUCCESSFUL`, credits trip earnings (₹55.00), and records state change in `order_audit_trail`.
5. **Shift Earnings & Trip History:**
   - Real-time aggregated financial ledger tracking base fares, distance allowances, customer tips, and instant IMPS bank cashout simulation.

