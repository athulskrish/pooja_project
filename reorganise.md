# Codebase Reorganization Blueprint & Completed Architecture Guide
**Project:** Zippy — E-Commerce Platform for Food Order (MCSP-232, MCA Major Project)  
**Author / Student:** Pooja JP (Enrollment No: 2500051247)  
**Date:** October 2026  
**Document Status:** ✅ Completed, Verified & Maintained (Executed in Git Commit `6a38551`)

---

## 1. Executive Assessment & Completion Status

> **Status: Successfully Completed & Verified in Production.**  
> The architectural reorganization outlined in this blueprint was successfully executed and committed in Git commit `6a38551` (*"Reorganize project structure: clean docs, archive legacy prototypes, add gitignore and env template"*). The active codebase now operates on a clean, single-source-of-truth foundation across all 4 operational portals.

While the running features of the 4 application portals (**Customer**, **Kitchen**, **Delivery**, and **Admin**) are feature-rich and function well together, the underlying folder and file organization suffers from **severe technical debt, architectural fragmentation, duplicate source files, and orphaned legacy prototypes**.

Continuing further development without reorganizing will lead to:
1. **Developer confusion:** Unclear whether new features should be written in procedural scripts, controllers, classes, or models.
2. **Ghost bugs:** Modifying a file in `customer/classes/` or `customer/models/` will appear to have no effect because the active application actually runs on `includes/functions.php` and `config/database.php`.
3. **Database desynchronization:** Having 3 different `schema.sql` files and 2 different database installers causes confusion over which table definitions and seeds are canonical.
4. **Maintenance friction:** Monolithic files like `kitchen/index.php` (1,626 lines) and `delivery/index.php` (1,124 lines) combine inline CSS, JavaScript, HTML templates, and backend logic in single files, unlike the clean partial structure used in `customer/` and `admin/`.

---

## 2. Key Organizational Problems Identified

```
POOJA WORKSPACE
├── customer/                       <-- [ISSUE 1] Windows Junction symlink pointing to pooja_project/customer
├── improve.txt                     <-- [ISSUE 2] Loose root docs mixed with git repo
├── Project Report MCA.docx         <-- Loose academic reports in root
├── PROJECT SYNOPSIS.docx
├── synopsis_extracted.txt
│
└── pooja_project/                  <-- Main Git Repository
    ├── index.php                   <-- Portals launchpad (ACTIVE)
    ├── config/                     <-- Global system config (ACTIVE)
    ├── database/                   <-- Schema & seed.php (ACTIVE)
    ├── includes/                   <-- Shared helpers & 3 Algorithms (ACTIVE)
    ├── assets/                     <-- Shared CSS & JS (ACTIVE)
    ├── admin/                      <-- Admin portal (ACTIVE, well structured)
    ├── delivery/                   <-- Rider portal (ACTIVE, but 1,124-line monolith)
    ├── kitchen/                    <-- KDS portal (ACTIVE, but 1,626-line monolith)
    │
    ├── html_design/                <-- [ISSUE 3] 33 static HTML mockups polluting source tree
    │
    └── customer/                   <-- [ISSUE 4] Severe internal fragmentation & dead code
        ├── index.php, cart.php...  <-- Active customer pages
        ├── includes/               <-- Active header & footer partials
        ├── ajax/                   <-- Active status polling
        │
        ├── classes/ (7 files)      <-- [DEAD] Shadow OOP classes using isolated DB singleton
        ├── models/ (7 files)       <-- [DEAD] Shadow model layer not loaded by active pages
        ├── controllers/ (3 files)  <-- [DEAD] Unreferenced controller classes
        ├── config/ (3 files)       <-- [CONFLICT] Duplicate database.php & 19KB schema.sql
        ├── database/ (2 files)     <-- [CONFLICT] Duplicate 9KB schema.sql & seed.sql
        ├── api/ (5 files)          <-- [ORPHANED] Alternative API endpoints (cart, coupon...)
        ├── css/style.css           <-- [DUPLICATE] 50KB duplicate of assets/css/style.css
        ├── js/app.js               <-- [DUPLICATE] 21KB outdated version of assets/js/app.js
        ├── js/customer.js          <-- [ORPHANED] 8.3KB unreferenced JS calling dead api/cart.php
        ├── kitchen-display.php     <-- [MISPLACED] 15KB KDS dashboard in customer folder
        ├── delivery-partner.php    <-- [STUB] 9-line redirect to ../delivery/index.php
        ├── portals.php             <-- [DUPLICATE] Duplicate gateway of pooja_project/index.php
        ├── setup_db.php            <-- [DUPLICATE] Duplicate installer of database/seed.php
        └── addresses.php           <-- [STUB] 1-line redirect to manage-addresses.php
```

### Detailed Breakdown of Flaws:

### Problem 1: The "Ghost Sub-project" Inside `customer/`
Inside [`pooja_project/customer/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer), there is an entire legacy sub-application left over from an earlier commit. It includes:
- [`customer/classes/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/classes): `Auth.php`, `Cart.php`, `Category.php`, `Database.php`, `MenuItem.php`, `Order.php`, `User.php`.
- [`customer/models/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/models): `Address.php`, `AprioriEngine.php`, `Category.php`, `KitchenQueue.php`, `MenuItem.php`, `Order.php`, `User.php`.
- [`customer/controllers/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/controllers): `AuthController.php`, `MenuController.php`, `OrderController.php`.
- None of the live storefront pages (`index.php`, `restaurant.php`, `checkout.php`, etc.) call these controllers or models. All active storefront logic runs directly on `config/config.php` and `includes/functions.php`.

### Problem 2: Three Competing Database Connection Engines
There are three completely different ways the project attempts to connect to MySQL:
1. **Canonical Engine:** [`pooja_project/config/database.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/config/database.php) (`getDBConnection()`, `checkDBStatus()`) — Used by all active pages across Customer, Admin, Kitchen, Delivery.
2. **Isolated Singleton A:** [`pooja_project/customer/classes/Database.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/classes/Database.php) (`Database::getInstance()`) — Used only inside `customer/classes/`.
3. **Isolated Singleton B:** [`pooja_project/customer/config/database.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/config/database.php) (`Database::getConnection()`) — Used only inside `customer/models/` and `customer/api/`.

### Problem 3: Three Divergent Schemas and Two Installers
- **Canonical Schema:** [`pooja_project/database/schema.sql`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/database/schema.sql) (22 KB, 10 normalized 3NF tables, triggers, sample dataset).
- **Secondary Schema:** [`pooja_project/customer/config/schema.sql`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/config/schema.sql) (19 KB, intermediate).
- **Outdated Schema:** [`pooja_project/customer/database/schema.sql`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/database/schema.sql) (9 KB, missing tables and triggers).
- **Canonical Installer:** [`pooja_project/database/seed.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/database/seed.php) (Runs via browser or CLI, auto-creates DB, verifies triggers, re-hashes demo passwords).
- **Secondary Installer:** [`pooja_project/customer/setup_db.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/setup_db.php) (Relies on `customer/classes/Database.php`).

### Problem 4: Static Prototype Clutter (`html_design/`)
[`pooja_project/html_design/`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/html_design) contains 33 static `.html` prototypes plus duplicate CSS and JS files totaling over 500 KB. Having these directly inside the application source tree pollutes global searches, code completion, and version control.

### Problem 5: Structural Inconsistency Across Portals
- **Customer & Admin:** Clean separation with dedicated `includes/header.php`, `includes/footer.php`, and sub-endpoints.
- **Kitchen & Delivery:** Monolithic single-file architectures. [`kitchen/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php) is 1,626 lines and [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php) is 1,124 lines. They contain embedded `<style>` blocks, embedded `<script>` blocks, modals, and PHP processing scripts packed into one file.

### Problem 6: Redundant Stubs and Redirects
- [`customer/kitchen-display.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/kitchen-display.php) is an obsolete 15 KB KDS dashboard that should not exist in the customer folder (the real KDS is at `kitchen/index.php`).
- [`customer/delivery-partner.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/delivery-partner.php) is just a 9-line redirect to `../delivery/index.php`.
- [`customer/portals.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/portals.php) duplicates the master portal gateway at `pooja_project/index.php`.
- [`customer/addresses.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/addresses.php) is a 1-line redirect to `manage-addresses.php`.

### Problem 7: API and AJAX Naming Inconsistencies
- `customer/ajax/order-status-api.php` (active) vs `customer/api/order-status.php` (unused) vs `customer/api/order_status.php` (unused).
- Mixed naming conventions: kebab-case (`cart-action.php`, `order-status-api.php`, `update-status.php`, `toggle-stock.php`, `update-order-status.php`) alongside snake_case (`order_status.php`).

---

## 3. Comprehensive File Inventory & Categorization

To proceed cleanly, every file currently in the project is mapped to one of four classifications:

| Category | Description | Action Required |
| :--- | :--- | :--- |
| **🟢 Core Production** | Active, working files required for the 4 portals and core logic | Keep and maintain |
| **🟡 Refactor Candidate** | Monolithic files that work but should be split into partials | Modularize layout |
| **🔴 Obsolete / Duplicate** | Dead code, orphaned classes, duplicate schemas, and stubs | Delete or move to `archive/` |
| **🔵 Design & Academic Docs** | Word documents, text notes, and Figma HTML prototypes | Move to `docs/` |

### Detailed Inventory Table

| File Path | Category | Status & Role |
| :--- | :---: | :--- |
| `pooja_project/index.php` | 🟢 | **Master Gateway:** Central portals launchpad & system health |
| `pooja_project/config/config.php` | 🟢 | **Global Config:** URLs, constants, session, CSRF, security |
| `pooja_project/config/database.php` | 🟢 | **Canonical DB:** Shared PDO connection singleton |
| `pooja_project/database/schema.sql` | 🟢 | **Canonical Schema:** 10 3NF tables, triggers, sample data |
| `pooja_project/database/seed.php` | 🟢 | **Canonical Installer:** 1-click web & CLI database seeder |
| `pooja_project/includes/functions.php` | 🟢 | **Core Helpers:** Query wrappers, bill totals, coupon engine |
| `pooja_project/includes/auth.php` | 🟢 | **RBAC Engine:** Session auth, role enforcement (Admin/Kitchen/Rider) |
| `pooja_project/includes/AprioriEngine.php` | 🟢 | **Algorithm 1:** Market Basket Analysis recommendations |
| `pooja_project/includes/WSJFQueue.php` | 🟢 | **Algorithm 2:** Kitchen Priority Queue scheduling |
| `pooja_project/includes/SESDemandForecast.php` | 🟢 | **Algorithm 3:** Single Exponential Smoothing BI forecasting |
| `pooja_project/assets/css/style.css` | 🟢 | **Global Styles:** Figma "Lush Forest" design system |
| `pooja_project/assets/js/app.js` | 🟢 | **Global Client Logic:** Cart state, drawer, toasts, price calculator |
| `pooja_project/customer/*.php` (19 files) | 🟢 | **Customer Storefront:** Live pages (`index`, `cart`, `checkout`, etc.) |
| `pooja_project/customer/includes/*` | 🟢 | **Customer Partials:** `header.php`, `footer.php`, `auth_guard.php` |
| `pooja_project/customer/ajax/order-status-api.php` | 🟢 | **Live Polling:** Real-time customer order tracking |
| `pooja_project/admin/*.php` (9 files) | 🟢 | **Admin Console:** Dashboard, CRUDs, Reports, Auth |
| `pooja_project/admin/includes/*` | 🟢 | **Admin Partials:** `header.php`, `footer.php` |
| `pooja_project/admin/ajax/update-order-status.php` | 🟢 | **Admin AJAX:** Status transition handler |
| `pooja_project/kitchen/login.php`, `logout.php`, `kot.php` | 🟢 | **Kitchen Auth & Thermal Slip:** KOT generator |
| `pooja_project/kitchen/ajax/*` (3 files) | 🟢 | **Kitchen AJAX:** `poll-orders.php`, `toggle-stock.php`, `update-status.php` |
| `pooja_project/delivery/login.php`, `logout.php` | 🟢 | **Delivery Auth:** Rider login/logout |
| `pooja_project/kitchen/index.php` | 🟡 | **Refactor Candidate:** 1,626 lines. Working, but needs partial extraction |
| `pooja_project/delivery/index.php` | 🟡 | **Refactor Candidate:** 1,124 lines. Working, but needs partial extraction |
| `pooja_project/customer/classes/*` (7 files) | 🔴 | **Dead Code:** Unreferenced OOP classes with separate DB instance |
| `pooja_project/customer/models/*` (7 files) | 🔴 | **Dead Code:** Unreferenced model classes |
| `pooja_project/customer/controllers/*` (3 files) | 🔴 | **Dead Code:** Unreferenced controllers |
| `pooja_project/customer/config/*` (3 files) | 🔴 | **Duplicate / Conflicting:** `database.php`, `db.php`, `schema.sql` |
| `pooja_project/customer/database/*` (2 files) | 🔴 | **Duplicate / Outdated:** `schema.sql`, `seed.sql` |
| `pooja_project/customer/api/*` (5 files) | 🔴 | **Orphaned Endpoints:** Superseded by `ajax/` endpoints |
| `pooja_project/customer/css/style.css` | 🔴 | **Duplicate:** 50 KB duplicate of `assets/css/style.css` |
| `pooja_project/customer/js/app.js` | 🔴 | **Duplicate:** Outdated 21 KB version of `assets/js/app.js` |
| `pooja_project/customer/js/customer.js` | 🔴 | **Orphaned Script:** 8.3 KB script calling dead `api/cart.php` |
| `pooja_project/customer/kitchen-display.php` | 🔴 | **Misplaced Prototype:** 15 KB legacy KDS sitting in customer folder |
| `pooja_project/customer/delivery-partner.php` | 🔴 | **Redundant Stub:** 9-line redirect |
| `pooja_project/customer/portals.php` | 🔴 | **Duplicate Gateway:** Duplicates `pooja_project/index.php` |
| `pooja_project/customer/setup_db.php` | 🔴 | **Duplicate Installer:** Duplicates `database/seed.php` |
| `pooja_project/customer/addresses.php` | 🔴 | **Redundant Stub:** 1-line redirect to `manage-addresses.php` |
| `pooja_project/customer/ajax/cart-action.php` | 🔴 | **Unused AJAX:** Cart is handled client-side via `localStorage` |
| `pooja_project/html_design/*` (33 HTML + subdirs) | 🔵 | **Design Mockups:** Static HTML to be archived |
| `improve.txt`, `synopsis_extracted.txt` | 🔵 | **Project Notes:** Academic reference notes |
| `Project Report MCA.docx`, `PROJECT SYNOPSIS.docx` | 🔵 | **Official Submissions:** Word documentation |

---

## 4. Target Architecture: How the Codebase Should Look

Below is the clean, organized target structure. Every file has a single, unambiguous responsibility, with zero duplicates:

```
pooja_project/
│
├── .env.example                       # Environment template (DB_HOST, DB_NAME, DB_USER, DB_PASS)
├── .gitignore                         # Standard git ignore (.env, vendor, IDE files, OS caches)
├── index.php                          # Master Portals Launchpad & System Status
├── README.md                          # Architecture, features & evaluation guide
├── run.md                             # Step-by-step local execution instructions
│
├── config/                            # Global System Configuration
│   ├── config.php                     # App constants, URLs, sessions, CSRF helpers
│   └── database.php                   # Canonical PDO MySQL singleton connection
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
├── assets/                            # Shared Client Assets
│   ├── css/
│   │   └── style.css                  # Figma "Lush Forest" design system CSS
│   └── js/
│       └── app.js                     # Persistent cart, drawer engine, toasts, calculations
│
├── customer/                          # 🛒 Customer Food Ordering Storefront
│   ├── index.php                      # Storefront home (Hero, categories, popular dishes)
│   ├── restaurant.php                 # Restaurant menu, veg switch, Apriori cross-sell
│   ├── cart.php                       # Dedicated cart page & coupon application
│   ├── checkout.php                   # Multi-step checkout with ACID transaction & locking
│   ├── order-success.php              # Order confirmation with generated OTP & tracker link
│   ├── order-status.php               # Live tracking stepper with dynamic ETA
│   ├── order-history.php              # Past order history with Repeat Order & Invoice
│   ├── invoice.php                    # GST Tax Invoice (Print-ready layout)
│   ├── search.php                     # Live search & multi-facet filters
│   ├── offers.php                     # Promotional coupons hub
│   ├── profile.php                    # Customer profile management
│   ├── manage-addresses.php           # Saved delivery addresses CRUD
│   ├── rate-order.php                 # Post-delivery rating & tips
│   ├── help.php                       # FAQs accordions & support chatbot simulator
│   ├── login.php                      # Customer authentication with demo autofill
│   ├── signup.php                     # Customer registration
│   ├── logout.php                     # Session termination
│   ├── terms.php                      # Terms of service
│   ├── privacy.php                    # Privacy policy
│   ├── 404.php                        # Themed 404 error page
│   ├── includes/                      # Customer-specific layout partials
│   │   ├── header.php                 # Navbar, cart badge, location selector
│   │   ├── footer.php                 # Footer, offcanvas cart drawer, mobile bar
│   │   └── auth_guard.php             # Customer session protection
│   └── ajax/                          # Customer asynchronous endpoints
│       └── order-status-api.php       # Live order status poller
│
├── admin/                             # 👨‍💼 Administrator Operations Console
│   ├── index.php                      # Executive KPI Dashboard & live order stream
│   ├── orders.php                     # Full order lifecycle management & status transitions
│   ├── categories.php                 # Category CRUD
│   ├── dishes.php                     # Menu catalog CRUD (prices, stock, prep times)
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
├── kitchen/                           # 🍳 Dedicated Kitchen Display System (KDS)
│   ├── index.php                      # Standalone KDS Station (Accept, Cook, Handover)
│   ├── login.php                      # Chef & cook login portal
│   ├── logout.php                     # Kitchen session signout
│   ├── kot.php                        # Printable thermal Kitchen Order Ticket
│   ├── includes/                      # Kitchen layout partials (Refactored)
│   │   ├── header.php                 # KDS top bar & station filter buttons
│   │   └── footer.php                 # KDS live audio chime & polling scripts
│   └── ajax/                          # Kitchen asynchronous endpoints
│       ├── poll-orders.php            # Audio chime poller for new orders
│       ├── update-status.php          # Status transition API (Cooking, Ready, Handover)
│       └── toggle-stock.php           # 86-list instant dish availability switch
│
├── delivery/                          # 🚴 Delivery Partner Fleet Web App
│   ├── index.php                      # Courier workspace (Duty toggle, claim, route, OTP)
│   ├── login.php                      # Rider login portal
│   ├── logout.php                     # Rider signout
│   ├── includes/                      # Delivery layout partials (Refactored)
│   │   ├── header.php                 # Rider topbar & duty toggle badge
│   │   └── footer.php                 # Rider geolocation & navigation scripts
│   └── ajax/                          # Delivery asynchronous endpoints
│       └── rider-action.php           # Extracted duty toggle & OTP verify endpoints
│
├── docs/                              # Project Documentation & Academic Artifacts
│   ├── SYSTEM_ANALYSIS_AND_DESIGN.md  # DFD, ERD, 3NF schemas, Use Cases
│   ├── Project Report MCA.docx        # MCA Project final report
│   ├── PROJECT SYNOPSIS.docx          # Approved synopsis
│   ├── improve.txt                    # Technical specification & improvement roadmap
│   └── synopsis_extracted.txt         # Extracted synopsis notes
│
└── archive/                           # Preserved Legacy Prototypes (Out of Source Tree)
    ├── html_design/                   # 33 original static HTML mockups
    └── legacy_customer_mvc/           # Incomplete OOP/MVC attempt (classes, models, controllers)
        ├── classes/
        ├── models/
        ├── controllers/
        └── legacy_api/
```

---

## 5. Step-by-Step Reorganization Execution Plan

The reorganization can be executed safely in **5 sequential phases** with zero downtime or broken references:

### Phase 1: Create Dedicated Documentation & Archive Folders
Create clean directories outside the main code path for non-executable files:
1. Create `pooja_project/docs/` for project documentation.
2. Create `pooja_project/archive/` for retired prototypes and dead code.

### Phase 2: Move Academic & Documentation Files into `docs/`
Move the documentation files currently scattered across the root directory:
- Move `Project Report MCA.docx` → `pooja_project/docs/`
- Move `PROJECT SYNOPSIS.docx` → `pooja_project/docs/`
- Move `improve.txt` → `pooja_project/docs/`
- Move `synopsis_extracted.txt` → `pooja_project/docs/`
- Move `pooja_project/SYSTEM_ANALYSIS_AND_DESIGN.md` → `pooja_project/docs/`
- Remove the Windows junction `customer` in the root folder (it was an unnecessary shortcut to `pooja_project/customer`).

### Phase 3: Archive Legacy Prototypes & Dead Code into `archive/`
Safely move the dead/duplicate files without deleting them permanently:
1. Move the entire `pooja_project/html_design/` folder → `pooja_project/archive/html_design/`.
2. Move the unused MVC prototype files from `pooja_project/customer/`:
   - `customer/classes/` → `pooja_project/archive/legacy_customer_mvc/classes/`
   - `customer/models/` → `pooja_project/archive/legacy_customer_mvc/models/`
   - `customer/controllers/` → `pooja_project/archive/legacy_customer_mvc/controllers/`
   - `customer/config/` → `pooja_project/archive/legacy_customer_mvc/config/`
   - `customer/database/` → `pooja_project/archive/legacy_customer_mvc/database/`
   - `customer/api/` → `pooja_project/archive/legacy_customer_mvc/api/`
   - `customer/css/` → `pooja_project/archive/legacy_customer_mvc/css/`
   - `customer/js/` → `pooja_project/archive/legacy_customer_mvc/js/`
3. Remove redundant stubs from `customer/`:
   - Delete `customer/kitchen-display.php` (it is an obsolete clone of `kitchen/index.php`).
   - Delete `customer/delivery-partner.php` (it is an obsolete redirect to `../delivery/index.php`).
   - Delete `customer/portals.php` (the canonical portal launchpad is `pooja_project/index.php`).
   - Delete `customer/setup_db.php` (the canonical installer is `pooja_project/database/seed.php`).
   - Delete `customer/addresses.php` (redirect stub to `manage-addresses.php`).
   - Delete `customer/ajax/cart-action.php` (cart is client-side).
   - Delete `customer/README.md` (the canonical README is `pooja_project/README.md`).

### Phase 4: Componentize Monolithic Portals (`kitchen/` and `delivery/`)
Extract layout headers and footers to match the structure of `admin/` and `customer/`:
1. **For `kitchen/index.php` (1,626 lines):**
   - Extract `<head>`, navbar, and station tabs into `kitchen/includes/header.php`.
   - Extract modal dialogs, audio chime elements, and WebSocket/polling JavaScript into `kitchen/includes/footer.php`.
   - Keep only the 3-lane Kanban ticket workflow in `kitchen/index.php`.
2. **For `delivery/index.php` (1,124 lines):**
   - Extract header and duty toggle topbar into `delivery/includes/header.php`.
   - Extract navigation map modals and OTP verification scripts into `delivery/includes/footer.php`.
   - Keep active dispatch cards and earnings history in `delivery/index.php`.

### Phase 5: Add Standard Developer Environment & Hygiene Files
1. **Create `.env.example`** in `pooja_project/`:
   ```env
   # Database Configuration
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=zippy_db
   DB_USER=root
   DB_PASS=

   # Application Configuration
   APP_ENV=development
   APP_DEBUG=true
   ```
2. **Create `.gitignore`** in `pooja_project/`:
   ```gitignore
   # Environment and local credentials
   .env
   *.log

   # OS and Editor metadata
   .DS_Store
   Thumbs.db
   .vscode/
   .idea/

   # Dependency directories (if composer added later)
   /vendor/
   ```

---

## 6. Ready-to-Run PowerShell Commands

When you are ready to execute the reorganization, you can run the following PowerShell commands from `E:\2026 project\2026 july\Pooja`:

```powershell
# 1. Navigate to project root
cd "E:\2026 project\2026 july\Pooja"

# 2. Create archive and documentation directories
New-Item -ItemType Directory -Force -Path "pooja_project\docs"
New-Item -ItemType Directory -Force -Path "pooja_project\archive\legacy_customer_mvc"

# 3. Move loose documentation into pooja_project/docs/
Move-Item -Path "Project Report MCA.docx" -Destination "pooja_project\docs\"
Move-Item -Path "PROJECT SYNOPSIS.docx" -Destination "pooja_project\docs\"
Move-Item -Path "improve.txt" -Destination "pooja_project\docs\"
Move-Item -Path "synopsis_extracted.txt" -Destination "pooja_project\docs\"
Move-Item -Path "pooja_project\SYSTEM_ANALYSIS_AND_DESIGN.md" -Destination "pooja_project\docs\"

# 4. Remove root junction symlink if present
if (Test-Path "customer") { (Get-Item "customer").Delete() }

# 5. Archive static HTML designs
Move-Item -Path "pooja_project\html_design" -Destination "pooja_project\archive\"

# 6. Archive dead customer MVC sub-project files
Move-Item -Path "pooja_project\customer\classes" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\models" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\controllers" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\config" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\database" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\api" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\css" -Destination "pooja_project\archive\legacy_customer_mvc\"
Move-Item -Path "pooja_project\customer\js" -Destination "pooja_project\archive\legacy_customer_mvc\"

# 7. Remove dead/stub files from customer folder
Remove-Item -Path "pooja_project\customer\kitchen-display.php" -Force
Remove-Item -Path "pooja_project\customer\delivery-partner.php" -Force
Remove-Item -Path "pooja_project\customer\portals.php" -Force
Remove-Item -Path "pooja_project\customer\setup_db.php" -Force
Remove-Item -Path "pooja_project\customer\addresses.php" -Force
Remove-Item -Path "pooja_project\customer\ajax\cart-action.php" -Force
Remove-Item -Path "pooja_project\customer\README.md" -Force
```

---

## 7. Verification Checklist After Reorganization

After executing the reorganization commands, verify that all system components operate without errors:

| Check # | Component / Flow | Test Procedure | Expected Result |
| :---: | :--- | :--- | :--- |
| **1** | **Database Seeding** | Visit `http://127.0.0.1:8080/database/seed.php` | All 10 tables created, seeded, and triggers installed. |
| **2** | **Central Hub** | Visit `http://127.0.0.1:8080/index.php` | System status reports "Connected (10 Tables)", all 4 portal cards render. |
| **3** | **Customer Ordering** | Add Biryani to cart at `customer/index.php`, checkout at `customer/checkout.php` | ACID transaction succeeds, stock decrements, OTP is generated. |
| **4** | **Apriori Algorithm** | View `customer/restaurant.php` | "Frequently Ordered Together" cross-sell recommendations render dynamically. |
| **5** | **Kitchen KDS** | Login at `kitchen/login.php` (`chef@zippy.com` / `password123`) | WSJF urgency priority scores compute, order transitions from Cooking to Ready. |
| **6** | **Delivery App** | Login at `delivery/login.php` (`rider@zippy.com` / `password123`) | Rider toggles duty status, claims ready order, inputs 6-digit OTP to complete delivery. |
| **7** | **Admin Reports** | Login at `admin/login.php` (`admin@zippy.com` / `password123`), visit `admin/reports.php` | Chart.js revenue charts and SES daily demand predictions display correctly. |

---

## 8. Summary of Benefits for Further Development

1. **Zero Confusion:** Developers have exactly ONE canonical database file (`config/database.php`), ONE canonical schema (`database/schema.sql`), and ONE canonical styling engine (`assets/css/style.css`).
2. **Clean IDE Workspace:** Fast searches and accurate code completions without 33 static HTML prototypes and 28 dead PHP files cluttering results.
3. **Consistent Architecture:** All 4 portals share identical conventions: `config/`, `includes/`, `assets/`, and dedicated role folders.
4. **Academic Safety:** All historical mockups, documents, and syllabus requirements are preserved in `docs/` and `archive/`, ensuring full documentation readiness for viva defense.
