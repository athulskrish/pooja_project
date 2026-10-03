# 🚀 Zippy — How to Run on Your Laptop

> **Project:** Zippy – Online Food Ordering Platform  
> **Course:** MCSP-232 (MCA Major Project) | **Student:** Pooja JP  
> **Stack:** PHP 8.x + MariaDB/MySQL + Bootstrap 5

---

## Prerequisites

| Software | Minimum Version | Download |
|----------|----------------|----------|
| **PHP** | 8.1 or later | [windows.php.net](https://windows.php.net/download/) (Thread Safe, x64) |
| **MariaDB** or **MySQL** | MariaDB 10.6+ / MySQL 8.0+ | [mariadb.org/download](https://mariadb.org/download/) |

> **Tip:** You can use XAMPP or WampServer instead — they bundle both PHP and MariaDB. If you do, skip Steps 1 & 2 and just start Apache + MySQL from the XAMPP Control Panel.

### PHP Extensions Required

Make sure these extensions are enabled in your `php.ini` (uncomment the lines):

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
```

---

## Step 1 — Start the Database Server

### Option A: Portable MariaDB (No Install)

1. Download and extract the MariaDB ZIP archive (e.g., `mariadb-11.4.3-winx64.zip`).
2. Open a **terminal** and initialize the data directory (first time only):

```powershell
& "C:\path\to\mariadb\bin\mysql_install_db.exe" --datadir="C:\path\to\mariadb\data"
```

3. Start the server:

```powershell
& "C:\path\to\mariadb\bin\mysqld.exe" --console
```

> Leave this terminal window open. The server runs on `127.0.0.1:3306` by default.

### Option B: XAMPP / WampServer

Open the XAMPP Control Panel → click **Start** next to **MySQL**.

---

## Step 2 — Create the Database

Open a **new terminal** and run:

```powershell
& "C:\path\to\mariadb\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS zippy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Or via XAMPP's phpMyAdmin → create a database named `zippy_db`.

---

## Step 3 — Start the PHP Web Server

Open a **new terminal**, navigate to the project folder, and start PHP's built-in server:

```powershell
cd "E:\2026 project\2026 july\Pooja\pooja_project"

php -S 127.0.0.1:8080 -t .
```

You should see:

```
PHP Development Server (http://127.0.0.1:8080) started
```

> Leave this terminal open. The web server is now running.

---

## Step 4 — Seed the Database (Create Tables & Demo Data)

Open your browser and go to:

```
http://127.0.0.1:8080/database/seed.php
```

Or run from CLI:

```powershell
php database/seed.php
```

This will:
- Create all **10 normalized 3NF tables** (`roles`, `users`, `categories`, `menu_items`, `orders`, etc.)
- Insert demo food categories, menu dishes, and sample orders
- Create test user accounts (see below)

---

## Step 5 — Open the App! 🎉

| Portal | URL | Description |
|--------|-----|-------------|
| **Launchpad Hub** | [http://127.0.0.1:8080/index.php](http://127.0.0.1:8080/index.php) | Central Portal Switcher & System Diagnostics |
| **Customer Storefront** | [http://127.0.0.1:8080/customer/index.php](http://127.0.0.1:8080/customer/index.php) | Menu, Cart, Concurrency-Controlled Checkout |
| **Delivery Partner App** | [http://127.0.0.1:8080/delivery/index.php](http://127.0.0.1:8080/delivery/index.php) | Rider Duty, GPS Route, OTP Handshake Verification |
| **Kitchen Display (KDS)** | [http://127.0.0.1:8080/kitchen/index.php](http://127.0.0.1:8080/kitchen/index.php) | Standalone Chef Station: Accept, Prep, Handover to Rider |
| **Admin Console** | [http://127.0.0.1:8080/admin/index.php](http://127.0.0.1:8080/admin/index.php) | Catalog CRUD, User RBAC & SES Demand Forecasts |

---

## Demo Login Credentials

All demo accounts use the same password: **`password123`**

| Role | Email | Portal |
|------|-------|--------|
| 🔴 **Admin** | `admin@zippy.com` | Admin Console (`admin/login.php`) |
| 🟢 **Customer** | `pooja@example.com` | Customer Storefront (`customer/login.php`) |
| 🟡 **Kitchen Staff** | `chef@zippy.com` | Dedicated Kitchen Station (`kitchen/login.php`) |
| 🔵 **Delivery Rider** | `rider@zippy.com` | Delivery Partner Fleet App (`delivery/login.php`) |

---

## 🚴 How to Test the Complete End-to-End Flow

You can test the entire real-time lifecycle across customer, kitchen, and delivery boy:

1. **Step A — Customer Checkout & OTP Generation:**
   - Log in as customer (`pooja@example.com` / `password123`) at [http://127.0.0.1:8080/customer/login.php](http://127.0.0.1:8080/customer/login.php).
   - Add dishes to cart and complete checkout (select COD or UPI).
   - On the Order Status page ([`customer/order-status.php`](http://127.0.0.1:8080/customer/order-status.php)), note the **6-digit Delivery Handshake OTP** (e.g. `852914`).

2. **Step B — Kitchen Accepts, Prepares & Hands Over to Rider:**
   - Log in to the dedicated Kitchen Station at [http://127.0.0.1:8080/kitchen/login.php](http://127.0.0.1:8080/kitchen/login.php) as Chef Sanjeev (`chef@zippy.com` / `password123` or click 1-Click Login).
   - In Lane 1 ("Incoming"), click **"Accept & Start Cooking"** to move the order onto the stove line.
   - In Lane 2 ("In Cooking"), line cooks can check off dishes on their prep checklist, and click **"Order Plated & Ready"**.
   - In Lane 3 ("Hand Over to Courier"), view the assigned delivery boy (e.g. Suresh Kumar), packaging contents, and click **"Hand Over to Delivery Boy"**.
   - The ticket instantly shifts to `OUT_FOR_DELIVERY` and notifies the rider!

3. **Step C — Delivery Partner Pickup & Transit:**
   - Open the Delivery Partner portal at [http://127.0.0.1:8080/delivery/index.php](http://127.0.0.1:8080/delivery/index.php).
   - Log in as Suresh Kumar (`rider@zippy.com` / `password123` or click 1-Click Login).
   - Ensure the duty toggle is **ONLINE**.
   - Under "Bagged & Ready for Pickup at Kitchen", click **"Accept & Pick Up Order"**.
   - The order enters **IN TRANSIT** state with active stepper.

4. **Step D — GPS Routing & OTP Doorstep Verification:**
   - Click **"Turn-by-Turn GPS Map"** to inspect simulated turn instructions and live route.
   - Click **"Verify Customer OTP & Finish"**.
   - Enter the 6-digit OTP displayed on the customer's tracking screen (also displayed as a convenient testing hint in the modal).
   - Click **"Confirm & Complete Delivery"**.
   - Order instantly transitions to **DELIVERED**, logs in `order_audit_trail`, credits ₹55.00 trip payout to rider shift earnings, and refreshes the customer tracking page!

---

## Database Configuration

The app auto-connects with these defaults (see `config/database.php`):

| Setting | Default Value |
|---------|---------------|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `zippy_db` |
| Username | `root` |
| Password | *(empty)* |

To override, set environment variables before starting PHP:

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "zippy_db"
$env:DB_USER = "root"
$env:DB_PASS = ""
```

---

## Project Folder Structure

```
pooja_project/
├── admin/                  # Admin panel (Dashboard, Orders, KDS, RBAC)
│   ├── includes/           # Admin header, sidebar, footer
│   ├── index.php           # Admin dashboard
│   ├── orders.php          # Order management & dispatch
│   ├── kitchen.php         # Kitchen Display System (WSJF)
│   ├── categories.php      # Food category CRUD
│   ├── dishes.php          # Dish catalog & stock management
│   ├── users.php           # User & staff RBAC
│   └── reports.php         # Executive BI reports
├── customer/               # Customer storefront
│   ├── includes/           # Customer header, footer, modals
│   ├── index.php           # Homepage with restaurant listings
│   ├── restaurant.php      # Restaurant menu & add-to-cart
│   ├── cart.php            # Shopping cart
│   ├── checkout.php        # ACID transactional checkout
│   ├── order-status.php    # Live order tracking
│   ├── order-history.php   # Past orders
│   └── profile.php         # Account management
├── delivery/               # 🚴 Delivery Partner Fleet App (Delivery Boy)
│   ├── index.php           # Rider duty toggle, active dispatch, OTP verify, earnings
│   ├── login.php           # Dedicated fleet login with 1-click demo button
│   └── logout.php          # Rider session termination
├── assets/
│   ├── css/style.css       # Figma "Lush Forest" design system
│   └── js/app.js           # Cart logic, drawer, badge sync
├── config/
│   ├── config.php          # App constants, session, helpers
│   └── database.php        # PDO connection & credentials
├── database/
│   ├── schema.sql          # Full 3NF relational schema
│   └── seed.php            # Database seeder & demo data
├── includes/
│   ├── functions.php       # Shared helpers & business logic
│   ├── auth.php            # Authentication guards
│   ├── WSJFQueue.php       # WSJF priority algorithm
│   └── ...                 # Other shared modules
└── run.md                  # ← You are here!
```

---

## Stopping the App

1. Press `Ctrl + C` in the **PHP server** terminal
2. Press `Ctrl + C` in the **MariaDB server** terminal (or stop MySQL from XAMPP)

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| **"Connection refused"** on page load | MariaDB/MySQL is not running. Start it first (Step 1). |
| **"Database not found"** banner | Run the seeder: visit `http://127.0.0.1:8080/database/seed.php` |
| **Port 8080 already in use** | Use a different port: `php -S 127.0.0.1:9090 -t .` and open `http://127.0.0.1:9090` |
| **Port 3306 already in use** | Another MySQL instance is running. Stop it, or change `DB_PORT`. |
| **PDO extension missing** | Enable `extension=pdo_mysql` in your `php.ini` file. |
| **Page is blank / white screen** | Check the PHP terminal for errors. Ensure `display_errors = On` in `php.ini`. |
