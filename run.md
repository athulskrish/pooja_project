# 🚀 Zippy — How to Run on Your Laptop

> **Project:** Zippy – Online Food Ordering Platform  
> **Course:** MCSP-232 (MCA Major Project) | **University:** IGNOU  
> **Student:** Pooja JP (Enrollment No: `2500051247`)  
> **Stack:** PHP 8.1+ / 8.2+ + MariaDB 10.6+ / MySQL 8.0+ (InnoDB) + Bootstrap 5.3 + Vanilla JS  
> **Design System:** Figma "Lush Forest" Color Palette (#Combination-4)  
> **Version:** 2.0.0-MCA (Post-Reorganization Architecture)

---

## 📋 Prerequisites

| Software | Minimum Version | Download Link | Notes |
|:---|:---:|:---|:---|
| **PHP** | 8.1 or later (8.2 recommended) | [windows.php.net](https://windows.php.net/download/) | Thread Safe or NTS (x64) |
| **MariaDB** or **MySQL** | MariaDB 10.6+ / MySQL 8.0+ | [mariadb.org](https://mariadb.org/download/) / [mysql.com](https://dev.mysql.com/downloads/installer/) | InnoDB Storage Engine required |
| **Web Browser** | Modern (Chrome, Edge, Firefox) | Pre-installed | JavaScript & LocalStorage enabled |

> 💡 **Tip — All-in-One Alternative (XAMPP / WampServer / Laragon):**  
> If you prefer not to configure standalone PHP and MySQL, install **XAMPP (v8.1+)**. It bundles both PHP 8.x and MariaDB/MySQL. If using XAMPP, skip manual service steps and simply click **Start** for **Apache** and **MySQL** in the XAMPP Control Panel.

### Required PHP Extensions
Ensure the following extensions are enabled in your `php.ini` file (remove the leading `;` if commented out):

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=curl
```

To quickly verify your enabled extensions from PowerShell:
```powershell
php -m | Select-String -Pattern "pdo_mysql|mbstring|openssl"
```

---

## ⚙️ Environment Configuration (`.env`)

The project supports zero-dependency environment configuration via `.env` with automatic fallback to standard defaults.

1. In the `pooja_project` folder, copy the example template to create your `.env` file:
   ```powershell
   cd "E:\2026 project\2026 july\Pooja\pooja_project"
   copy .env.example .env
   ```

2. Inspect or edit `.env` if your local database uses a custom password or non-standard port:
   ```env
   # Database Credentials
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=zippy_db
   DB_USER=root
   DB_PASS=

   # Environment Settings
   APP_ENV=development
   APP_DEBUG=true
   ```

> ℹ️ **Note:** If no `.env` file exists, the system automatically defaults to `127.0.0.1:3306`, user `root`, empty password, and database `zippy_db`.

---

## 🛠️ Step 1 — Start the Database Server

### Option A: Via XAMPP / WampServer (Easiest)
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **MySQL** (and **Apache** if you plan to access via `http://localhost/Pooja/pooja_project/`).
3. Ensure the MySQL status turns green and port `3306` is shown.

### Option B: Standalone / Portable MariaDB or MySQL
1. Open a **PowerShell** terminal.
2. Initialize data directory (first time only, if using portable ZIP):
   ```powershell
   & "C:\path\to\mariadb\bin\mysql_install_db.exe" --datadir="C:\path\to\mariadb\data"
   ```
3. Start the daemon:
   ```powershell
   & "C:\path\to\mariadb\bin\mysqld.exe" --console
   ```
   *(Keep this terminal open while running the app)*

### Option C: Windows Service (Installed MySQL / MariaDB)
If installed as a Windows background service, start it from an elevated terminal:
```powershell
net start MySQL
# or for MySQL 8.0:
net start MySQL80
```

---

## 🗄️ Step 2 — Create the Relational Database

Open a new PowerShell terminal and run the following command to ensure the `zippy_db` database exists with full UTF-8 Unicode support:

```powershell
# If MySQL is in your system PATH:
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zippy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Or using the absolute path to your MySQL / MariaDB binary:
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS zippy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

*Alternatively, open **phpMyAdmin** (`http://127.0.0.1/phpmyadmin`) → click **New** → create a database named `zippy_db` with Collation `utf8mb4_unicode_ci`.*

---

## 🌐 Step 3 — Start the PHP Web Server

Open a PowerShell terminal, navigate to the `pooja_project` folder, and launch PHP's built-in development server:

```powershell
cd "E:\2026 project\2026 july\Pooja\pooja_project"

php -S 127.0.0.1:8080 -t .
```

You should see:
```text
[Sat Oct  3 21:45:00 2026] PHP 8.2.x Development Server (http://127.0.0.1:8080) started
```

> ⚠️ **Keep this terminal window open.** It serves all HTTP traffic, static assets, and AJAX API requests for all 4 portals.

---

## ⚡ Step 4 — Run the Automated Database Seeder

Once the server and database are running, open your browser and visit the automated seeder:

👉 **[http://127.0.0.1:8080/database/seed.php](http://127.0.0.1:8080/database/seed.php)**

*Or run directly via command line:*
```powershell
cd "E:\2026 project\2026 july\Pooja\pooja_project"
php database/seed.php
```

### What the Seeder Automatically Executes:
1. **Creates All 10 Normalized 3NF Relational Tables:**
   - `roles`: Role-Based Access Control catalog (`ADMIN`, `CUSTOMER`, `KITCHEN`, `RIDER`).
   - `users`: User profiles with Argon2id / Bcrypt password hashing and active flags.
   - `user_addresses`: 1-to-many customer delivery addresses with default indicators.
   - `categories`: Menu categorization with slugs, display order, and active toggles.
   - `menu_items`: Dish catalog with pricing, live stock, prep time, veg/non-veg tags, and spice indicators.
   - `orders`: Master order ledger with subtotal, 5% GST, platform fee, delivery charge, and real-time status.
   - `order_items`: Immutable transactional line items capturing ordered quantities and historical prices.
   - `payments`: Payment transaction records (COD, UPI, Card, NetBanking) with gateway references.
   - `delivery_dispatches`: Last-mile dispatch logs containing assigned riders, 6-digit verification OTPs, and timestamps.
   - `order_audit_trail`: Immutable chronological audit log recording every lifecycle status transition and event actor.
2. **Installs ACID MySQL Triggers:**
   - `trg_order_status_audit`: Automatically inserts an audit entry whenever `orders.order_status` transitions.
   - `trg_deduct_menu_stock`: Real-time stock balance adjustments upon order placement.
3. **Populates Seed Data & Sample Transactions:**
   - Pre-seeds authentic Indian culinary categories (Biryanis, Curries, Breads, Beverages, Desserts).
   - Seeds popular dishes with prep times and initial inventory reserves.
   - Seeds historical multi-item orders to initialize the **Apriori Recommendation Engine** and **SES Demand Forecast Model**.
   - Encrypts all user passwords securely (`password123`).

---

## 🖥️ Step 5 — Access the Application Portals

Open your browser to test and evaluate each specialized subsystem:

| Portal | Direct URL | Description & Key Responsibilities |
|:---|:---|:---|
| 🚀 **Master Launchpad Hub** | [http://127.0.0.1:8080/index.php](http://127.0.0.1:8080/index.php) | Central portal switcher, system health diagnostics, database connection status, and project overview. |
| 🛒 **Customer Storefront** | [http://127.0.0.1:8080/customer/index.php](http://127.0.0.1:8080/customer/index.php) | Customer menu browsing, live search, veg filters, Apriori cross-sell recommendations, cart drawer, coupons, and ACID checkout. |
| 🍳 **Kitchen Display (KDS)** | [http://127.0.0.1:8080/kitchen/index.php](http://127.0.0.1:8080/kitchen/index.php) | Chef workspace with dynamic Weighted Shortest Job First (WSJF) priority Kanban, live audio alerts, stove timers, and handover controls. |
| 🧾 **Thermal KOT Slip** | [http://127.0.0.1:8080/kitchen/kot.php?order_id=1](http://127.0.0.1:8080/kitchen/kot.php?order_id=1) | Clean, print-ready 80mm thermal Kitchen Order Ticket generator for kitchen line cooks. |
| 🚴 **Delivery Partner Fleet** | [http://127.0.0.1:8080/delivery/index.php](http://127.0.0.1:8080/delivery/index.php) | Courier app with Online/Offline duty toggle, dispatch claiming, turn-by-turn simulated GPS map, 6-digit OTP doorstep verification, and shift earnings. |
| 👨‍💼 **Admin Operations Console** | [http://127.0.0.1:8080/admin/index.php](http://127.0.0.1:8080/admin/index.php) | Executive KPI dashboard, full order lifecycle dispatcher, category/dish CRUD with 86-list toggle, staff RBAC management, and BI analytics. |
| 📊 **BI & SES Forecasts** | [http://127.0.0.1:8080/admin/reports.php](http://127.0.0.1:8080/admin/reports.php) | Business intelligence charts (Chart.js) and Single Exponential Smoothing demand forecasts. |

---

## 🔑 Demo Login Credentials

All test accounts share the universal demo password: **`password123`**  
*(Every login page also provides convenient **1-Click Demo Login** buttons for instant evaluation)*

| Role | Name | Email | Password | Dedicated Login Portal |
|:---|:---|:---|:---:|:---|
| 🔴 **Admin** | Pooja JP (Admin) | `admin@zippy.com` | `password123` | [`admin/login.php`](http://127.0.0.1:8080/admin/login.php) |
| 🟢 **Customer** | Pooja JP (Customer) | `pooja@example.com` | `password123` | [`customer/login.php`](http://127.0.0.1:8080/customer/login.php) |
| 🟢 **Customer** | Rahul Sharma | `rahul@example.com` | `password123` | [`customer/login.php`](http://127.0.0.1:8080/customer/login.php) |
| 🟡 **Kitchen Chef** | Chef Sanjeev | `chef@zippy.com` | `password123` | [`kitchen/login.php`](http://127.0.0.1:8080/kitchen/login.php) |
| 🔵 **Delivery Rider** | Suresh Kumar | `rider@zippy.com` | `password123` | [`delivery/login.php`](http://127.0.0.1:8080/delivery/login.php) |

---

## 🔄 Complete End-to-End Test Walkthrough

Follow this 6-stage walkthrough to simulate a live, multi-actor order cycle across all 4 portals:

```mermaid
sequenceDiagram
    autonumber
    actor C as 🛒 Customer (Pooja)
    actor K as 🍳 Kitchen (Chef Sanjeev)
    actor R as 🚴 Rider (Suresh Kumar)
    actor A as 👨‍💼 Admin Console
    
    C->>C: Place Order via ACID Checkout (Row Lock)
    Note over C: Secret OTP Generated (e.g. 852914)
    C-->>K: Real-time Polling & Audio Chime (WSJF Priority)
    K->>K: Accept Order & Start Cooking (Lane 1 -> Lane 2)
    K->>K: Print Thermal KOT Slip (kitchen/kot.php)
    K->>K: Mark Order Plated & Ready (Lane 2 -> Lane 3)
    K->>R: Handover Order to Delivery Partner
    R->>R: Claim Dispatch & Inspect GPS Turn-by-Turn Route
    R->>C: Doorstep Delivery Handshake (Submit 6-Digit OTP)
    Note over R,C: Status -> DELIVERED, ₹55.00 Credited to Rider
    C->>C: View GST Tax Invoice & Submit 5-Star Rating
    A->>A: Inspect Sales KPIs & SES Demand Forecasts
```

### Stage 1: Customer Ordering & Checkout
1. Open [http://127.0.0.1:8080/customer/login.php](http://127.0.0.1:8080/customer/login.php).
2. Click the **"Customer Demo Login"** button (or enter `pooja@example.com` / `password123`).
3. Browse the storefront:
   - Use the **Veg Only** toggle to filter vegetarian dishes.
   - Search for dishes (e.g., "Biryani" or "Paneer") via [`customer/search.php`](http://127.0.0.1:8080/customer/search.php).
   - Add **Hyderabadi Chicken Biryani** to cart.
   - Notice the **Apriori Recommendation Card** proposing frequently paired items (e.g., Garlic Naan or Mango Lassi).
4. Open the Cart Drawer or go to [`customer/cart.php`](http://127.0.0.1:8080/customer/cart.php):
   - Enter promo code `LUSHFOREST` or `ZIPPY50` to apply promotional discounts.
5. Click **"Proceed to Checkout"** ([`customer/checkout.php`](http://127.0.0.1:8080/customer/checkout.php)):
   - Select a saved delivery address (or add a new one).
   - Choose **Cash on Delivery (COD)** or **UPI / Net Banking**.
   - Click **"Place Order Now"**.
   - *Technical Note:* The checkout executes inside a MySQL `START TRANSACTION`, acquiring row-level pessimistic locks (`SELECT ... FOR UPDATE`) on dishes to verify and decrement stock atomically.
6. On [`customer/order-success.php`](http://127.0.0.1:8080/customer/order-success.php) and [`customer/order-status.php`](http://127.0.0.1:8080/customer/order-status.php), note the **6-digit Secret Delivery OTP** (e.g. `852914`). Keep this browser tab open to watch real-time status transitions.

### Stage 2: Kitchen Display System (KDS) & Thermal KOT
1. Open a new window or tab: [http://127.0.0.1:8080/kitchen/login.php](http://127.0.0.1:8080/kitchen/login.php).
2. Click **"1-Click Chef Login"** (`chef@zippy.com`).
3. In the KDS interface:
   - Notice that incoming orders are dynamically prioritized using the **Weighted Shortest Job First (WSJF)** algorithm.
   - An audible chime alerts line cooks when new tickets arrive.
4. **Lane 1 ("Incoming Tickets"):** Click **"Accept & Start Cooking"**. The ticket moves to Lane 2.
5. Click **"Print KOT"** to view and print the formatted 80mm thermal Kitchen Order Ticket (`kitchen/kot.php?order_id=...`).
6. **Lane 2 ("In Cooking"):** Check off prepared items on the prep checklist, then click **"Order Plated & Ready"**. The ticket moves to Lane 3.
7. **Lane 3 ("Ready for Dispatch"):** Review the assigned delivery partner (Suresh Kumar) and packaging items, then click **"Hand Over to Delivery Boy"**.
8. Ticket shifts to `OUT_FOR_DELIVERY` and notifies the rider!

### Stage 3: Delivery Partner Dispatch & Route Navigation
1. Open a new window or tab: [http://127.0.0.1:8080/delivery/login.php](http://127.0.0.1:8080/delivery/login.php).
2. Click **"1-Click Rider Login"** (`rider@zippy.com`).
3. Ensure the duty switch in the top bar is set to **ONLINE**.
4. In the active dispatch section:
   - Under "Bagged & Ready for Pickup at Kitchen", click **"Accept & Pick Up Order"**.
   - The ticket shifts into active transit.
5. Click **"Turn-by-Turn GPS Map"**:
   - Inspect the interactive routing modal displaying step-by-step turn directions, simulated transit speed, remaining distance, and a 1-click customer phone dialer (`tel:`).

### Stage 4: Doorstep OTP Handshake & Settlement
1. In the rider's active dispatch card, click **"Verify Customer OTP & Finish"**.
2. Enter the **6-digit Secret Delivery OTP** from Customer Stage 1 (e.g., `852914`).
3. Click **"Confirm & Complete Delivery"**:
   - The system validates the OTP against `delivery_dispatches.delivery_otp`.
   - On match: Order status transitions to **DELIVERED**.
   - Delivery timestamp is recorded (`delivered_at = NOW()`).
   - If COD, payment status updates to `SUCCESSFUL`.
   - Rider trip fee (**₹55.00**) is credited to the rider's shift earnings ledger.
   - The event is immutably logged into `order_audit_trail`.

### Stage 5: Customer Post-Order Experience & Invoice
1. Return to the customer's tracking page tab ([`customer/order-status.php`](http://127.0.0.1:8080/customer/order-status.php)):
   - The AJAX status poller automatically detects the delivery completion and shows the **Delivered** milestone.
2. Click **"Download Tax Invoice"** ([`customer/invoice.php`](http://127.0.0.1:8080/customer/invoice.php)):
   - View the official Indian GST Tax Invoice with SAC Service Code `996331`, 2.5% CGST, 2.5% SGST, and restaurant tax details.
3. Click **"Rate Order & Delivery Partner"** ([`customer/rate-order.php`](http://127.0.0.1:8080/customer/rate-order.php)):
   - Submit 5-star ratings for food and delivery, add optional courier tips, and leave feedback comments.

### Stage 6: Admin BI Analytics & Demand Forecasting
1. Open [http://127.0.0.1:8080/admin/login.php](http://127.0.0.1:8080/admin/login.php) and sign in as Admin (`admin@zippy.com`).
2. Explore the executive features:
   - **Dashboard ([`admin/index.php`](http://127.0.0.1:8080/admin/index.php)):** View real-time sales KPIs, active orders, customer counts, and order audit stream.
   - **Orders Dispatcher ([`admin/orders.php`](http://127.0.0.1:8080/admin/orders.php)):** Inspect complete order histories and override status transitions.
   - **Menu & Stock Management ([`admin/dishes.php`](http://127.0.0.1:8080/admin/dishes.php)):** Update pricing, adjust prep times, and toggle 86-list availability.
   - **BI Reports & SES Forecasting ([`admin/reports.php`](http://127.0.0.1:8080/admin/reports.php)):** Review Chart.js sales visuals and Single Exponential Smoothing ($\alpha = 0.3$) demand forecasts to predict ingredient requirements and prevent stockouts.

---

## 🧠 Master's Degree (MCA) Algorithmic Implementations

The platform features 5 core computational algorithms and software engineering patterns designed to satisfy MCA major project evaluation standards:

1. **Market Basket Analysis (Apriori Algorithm) — `includes/AprioriEngine.php`:**
   - Evaluates transactional co-occurrences in `order_items`.
   - Computes Item Support and Confidence metrics to recommend complementary food pairings (e.g., recommending Garlic Naan and Mango Lassi when Chicken Biryani is added).

2. **Dynamic Kitchen Queue Scheduling (WSJF) — `includes/WSJFQueue.php`:**
   $$\text{WSJF Priority Score} = \frac{\text{Cost of Delay (Elapsed Wait Time)}}{\text{Job Duration (Max Prep Time + Portions)}}$$
   - Dynamically reorders kitchen tickets so that orders experiencing delay automatically bubble up the stove queue.

3. **Single Exponential Smoothing (SES) Demand Forecasting — `includes/SESDemandForecast.php`:**
   $$\hat{Y}_{t+1} = \alpha Y_t + (1 - \alpha)\hat{Y}_t \quad (\alpha = 0.3)$$
   - Analyzes dish order velocities to project next-day and weekend culinary demand, highlighting dishes at risk of stock depletion.

4. **High-Concurrency ACID Checkout with Row-Level Locking — `customer/checkout.php`:**
   - Utilizes `SELECT ... FOR UPDATE` within database transactions to ensure two concurrent users cannot purchase the final portion of a dish under race conditions.

5. **Cryptographic Doorstep Handshake (6-Digit OTP) — `delivery/index.php`:**
   - Generates a cryptographically random 6-digit OTP stored in `delivery_dispatches.delivery_otp`. Couriers cannot close delivery tickets without entering the customer's code, eliminating dispute anomalies.

---

## 📁 Clean Reorganized Project Directory Structure

Following the structural reorganization in Commit `6a38551`, the codebase adheres to a clean, non-redundant architecture:

```text
pooja_project/
├── .env.example                       # Environment configuration template
├── .gitignore                         # Standard version control exclusions (.env, vendor, OS caches)
├── index.php                          # Master Portals Launchpad & System Status
├── README.md                          # Comprehensive project & architecture overview
├── run.md                             # ← Local execution guide (This Document)
│
├── config/                            # Global System Configuration
│   ├── config.php                     # App constants, URLs, sessions, CSRF helpers, formatting
│   └── database.php                   # Canonical PDO MySQL singleton & automatic .env loader
│
├── database/                          # Relational Database Engine
│   ├── schema.sql                     # Canonical 10-table 3NF schema with triggers & seeds
│   └── seed.php                       # One-click CLI & web automated database installer
│
├── includes/                          # Shared Services & Computational Algorithms
│   ├── functions.php                  # Shared DB query wrappers, bill totals, coupon engine
│   ├── auth.php                       # Session-based RBAC authentication & authorization
│   ├── AprioriEngine.php              # Algorithm 1: Market Basket Analysis Cross-Selling
│   ├── WSJFQueue.php                  # Algorithm 2: Dynamic WSJF Kitchen Queue Scheduling
│   └── SESDemandForecast.php          # Algorithm 3: Exponential Smoothing Demand Forecast
│
├── assets/                            # Shared Client Assets (Figma "Lush Forest" Palette)
│   ├── css/style.css                  # Design system CSS (custom properties, responsive layout)
│   └── js/app.js                      # Persistent cart, drawer engine, toasts, live calculations
│
├── customer/                          # 🛒 Customer Food Ordering Storefront
│   ├── index.php                      # Storefront home (Hero, categories, popular dishes)
│   ├── restaurant.php                 # Restaurant menu, veg switch, Apriori cross-sell
│   ├── cart.php                       # Dedicated cart page & coupon application
│   ├── checkout.php                   # Multi-step checkout with ACID transaction & row locking
│   ├── order-success.php              # Order confirmation with generated OTP & tracker link
│   ├── order-status.php               # Live tracking stepper with dynamic ETA
│   ├── order-history.php              # Past order history with Repeat Order & Invoice
│   ├── invoice.php                    # GST Tax Invoice (Print-ready layout, SAC 996331)
│   ├── search.php                     # Live search & multi-facet filters
│   ├── offers.php                     # Promotional coupons hub (LUSHFOREST, ZIPPY50)
│   ├── profile.php                    # Customer profile management
│   ├── manage-addresses.php           # Saved delivery addresses CRUD (user_addresses)
│   ├── rate-order.php                 # Post-delivery rating & tips
│   ├── help.php                       # FAQs accordions & support chatbot simulator
│   ├── login.php                      # Customer authentication with demo autofill
│   ├── signup.php                     # Customer registration
│   ├── logout.php                     # Session termination
│   ├── terms.php                      # Terms of service
│   ├── privacy.php                    # Privacy policy
│   ├── 404.php                        # Themed 404 error page
│   ├── includes/                      # Customer layout partials
│   │   ├── header.php                 # Navbar, cart badge, location modal trigger
│   │   ├── footer.php                 # Footer, offcanvas cart drawer, mobile bottom bar
│   │   └── auth_guard.php             # Customer session protection
│   └── ajax/                          # Asynchronous endpoints
│       └── order-status-api.php       # Real-time order status polling endpoint
│
├── kitchen/                           # 🍳 Dedicated Kitchen Display System (KDS)
│   ├── index.php                      # Standalone KDS Station (Incoming, Cooking, Ready)
│   ├── login.php                      # Chef & cook login portal with 1-click access
│   ├── logout.php                     # Kitchen session signout
│   ├── kot.php                        # Printable 80mm thermal Kitchen Order Ticket slip
│   └── ajax/                          # Kitchen asynchronous endpoints
│       ├── poll-orders.php            # Audio chime poller for new orders
│       ├── update-status.php          # Status transition API (Cooking, Ready, Handover)
│       └── toggle-stock.php           # 86-list instant dish availability switch
│
├── delivery/                          # 🚴 Delivery Partner Fleet Web App (Delivery Boy)
│   ├── index.php                      # Courier workspace (Duty switch, active route, OTP, earnings)
│   ├── login.php                      # Rider login portal with 1-click access
│   └── logout.php                     # Rider session signout
│
├── admin/                             # 👨‍💼 Administrator Operations Console
│   ├── index.php                      # Executive KPI Dashboard & live order stream
│   ├── orders.php                     # Full order lifecycle management & status transitions
│   ├── categories.php                 # Food Category CRUD (display orders, status)
│   ├── dishes.php                     # Menu Catalog CRUD (prices, stock, prep times)
│   ├── users.php                      # User & staff RBAC administration
│   ├── reports.php                    # BI analytics reports (Chart.js + SES forecasting)
│   ├── kitchen.php                    # Quick redirect bridge to Kitchen Station
│   ├── login.php                      # Admin/staff login
│   ├── logout.php                     # Admin signout
│   ├── includes/                      # Admin layout partials
│   │   ├── header.php                 # Admin sidebar & top navbar
│   │   └── footer.php                 # Admin footer scripts & toast listeners
│   └── ajax/                          # Admin asynchronous endpoints
│       └── update-order-status.php    # Live order status updater
│
├── docs/                              # 📚 Project Documentation & Academic Artifacts
│   ├── SYSTEM_ANALYSIS_AND_DESIGN.md  # DFD (Level 0, 1, 2), ERD, 3NF Schemas, Use Cases
│   ├── Project Report MCA.docx        # Master of Computer Applications Final Project Report
│   ├── PROJECT SYNOPSIS.docx          # University-approved project synopsis
│   ├── improve.txt                    # Technical specifications & roadmap notes
│   └── synopsis_extracted.txt         # Extracted synopsis notes
│
└── archive/                           # 📦 Preserved Legacy Prototypes (Out of Source Tree)
    ├── html_design/                   # 33 original static HTML mockups
    └── legacy_customer_mvc/           # Incomplete OOP prototype (classes, models, controllers)
```

---

## 🛑 Stopping the Servers

1. **Stop PHP Web Server:** Click in the terminal running `php -S` and press `Ctrl + C`.
2. **Stop Database Server:**
   - If using XAMPP: Click **Stop** next to MySQL in the XAMPP Control Panel.
   - If using standalone terminal: Click in the `mysqld` terminal and press `Ctrl + C`.
   - If running as a Windows service: Run `net stop MySQL` in an elevated terminal.

---

## ❓ Troubleshooting & FAQs

| Symptom / Error | Root Cause | Solution |
|:---|:---|:---|
| **"Cannot connect to MySQL server at 127.0.0.1:3306"** | MySQL or MariaDB daemon is not running. | Start MySQL via XAMPP Control Panel or run `mysqld --console` (Step 1). |
| **"Database tables are not yet created" banner** | `zippy_db` is empty or not yet seeded. | Visit [http://127.0.0.1:8080/database/seed.php](http://127.0.0.1:8080/database/seed.php) to install all 10 3NF tables and sample data. |
| **"Access denied for user 'root'@'localhost'"** | MySQL root user has a password set that doesn't match `.env`. | Open `.env` and set `DB_PASS=your_password`. If running without `.env`, update credentials in `config/database.php`. |
| **"Failed to listen on 127.0.0.1:8080 (reason: ...)"** | Another application is already using port 8080. | Launch PHP on an alternate port: `php -S 127.0.0.1:9090 -t .` and access via `http://127.0.0.1:9090`. |
| **"Class 'PDO' not found" or "could not find driver"** | The `pdo_mysql` extension is disabled in `php.ini`. | Open `php.ini`, uncomment `extension=pdo_mysql`, save the file, and restart the PHP development server. |
| **Blank white page or HTTP 500 error** | A PHP syntax error or unhandled fatal exception occurred. | Check the terminal where `php -S` is running to inspect the stack trace. Ensure `display_errors = On` in `php.ini`. |
| **Session logout upon page refresh** | Session cookies are blocked or the temp session directory is unwritable. | Ensure cookies are enabled in your browser. Verify the `session.save_path` in `php.ini` points to a writable folder (e.g., `C:\Windows\Temp`). |
| **CSS or JS assets not styling correctly** | Base URL path mismatch. | Access the site via `http://127.0.0.1:8080/` (built-in server) or ensure `pooja_project` is placed directly under your web root. The auto-detector in `config/config.php` handles path resolution automatically. |

---

*Document compiled and maintained for MCSP-232 Project Evaluation & Viva Voce.*
