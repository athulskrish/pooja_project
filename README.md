# Zippy - Online Food Ordering Platform
**Student:** Pooja JP | **Course:** MCSP-232 (MCA Project) | **University:** IGNOU  
**Design System:** Figma Resource Library "Lush Forest" Color Palette (#Combination-4)  
**Current Phase:** Step 1 - Front-end UI/UX Architecture (HTML5, Bootstrap 5, CSS3, JavaScript)

---

## 🎨 Figma "Lush Forest" Color Palette Architecture

This project strictly adheres to the official **Figma Combination 4: Lush Forest** color specifications:

| Role | Color Name | Hex Code | Visual Application in UI |
| :--- | :--- | :--- | :--- |
| **Dark Pine** | Forest Floor | `#243E2C` | Headings, dark navbar contrast, primary text, footer background |
| **Forest Green** | Lush Forest Primary | `#2E6F40` | Primary buttons, brand CTA, active tabs, highlight badges |
| **Sage Green** | New Leaf Accent | `#68BA7F` | Secondary badges, borders, star ratings, progress bar |
| **Light Mint** | Forest Mist | `#CEFFDC` | Subtle badge backgrounds, discount pill tags, glow hover states |
| **Clean Surface** | Off-White Lush Tint | `#F7FAF8` | Page background, cards surface, input fields |

All colors are declared as reusable CSS variables inside `css/style.css`.

---

## 📁 Complete Project Pages & Architecture (25 Pages)

This platform covers the complete **Customer Ordering Journey**, the **Admin & Kitchen KDS Portal**, and the **Delivery & System Operations**:

### 🛒 1. Customer Portal Pages
| Page Name | File Path | Purpose & Key Features |
| :--- | :--- | :--- |
| **Home Page** | [`index.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/index.html) | Zippy home page with hero carousel, food categories, top restaurant chains, sticky filters, 12+ restaurant cards, app download, and footer. |
| **Interactive Search** | [`search.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/search.html) | Live query search for dishes/cuisines/restaurants, veg/non-veg filter toggle, price & rating sorters, and direct cart ADD controls. |
| **Offers & Discounts** | [`offers.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/offers.html) | Deals hub: coupon codes (`LUSHFOREST`, `ZIPPY50`, etc.) with copy buttons, bank offers (HDFC, ICICI, GPay), and partner restaurant discounts. |
| **User Profile & Account** | [`profile.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/profile.html) | Account center: Zippy One membership status, order statistics, savings tracker, dietary preferences, and interactive Edit Profile modal. |
| **Manage Addresses** | [`manage-addresses.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/manage-addresses.html) | Address book mapped to `user_addresses` (3NF), default address toggles, GPS locator detector, and Add/Edit address modal. |
| **Order Confirmed** | [`order-success.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/order-success.html) | Animated order celebration, Order ID, delivery OTP, Apriori add-on suggestions, and direct links to live tracking and tax invoice. |
| **Rate Order & Rider** | [`rate-order.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/rate-order.html) | Post-delivery 5-star rating system for restaurant and courier, feedback chips, individual dish thumbs-up, and tip selector. |
| **Help & Support** | [`help.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/help.html) | Customer care hub: recent order dispute tiles, categorized FAQ search accordions, and interactive 24x7 AI chatbot simulator. |
| **Restaurant Menu** | [`restaurant.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/restaurant.html) | Meghana Foods menu with veg/non-veg toggle, dish cards with interactive `ADD` / `- 1 +` counter controls, and Apriori cross-sell recommendations. |
| **Dedicated Cart** | [`cart.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/cart.html) | Full shopping cart page with item customization, cooking instructions, free delivery progress bar, Apriori add-ons, and coupon codes. |
| **Checkout & Pay** | [`checkout.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/checkout.html) | Multi-step checkout: saved address selector (Home/Office), delivery instructions, UPI (GPay/PhonePe)/Card/COD options, and bill summary. |
| **Live Order Tracking** | [`order-status.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/order-status.html) | Real-time ETA countdown timer, 4-stage animated progress stepper, simulated GPS courier route map, and delivery partner card. |
| **Order History** | [`order-history.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/order-history.html) | Active in-kitchen order status with link to live tracker, past delivered orders with "Reorder", "Rate Order", and "Tax Invoice". |
| **Login** | [`login.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/login.html) | Tabbed login (Customer vs Admin), password visibility toggle, remember me, and one-click demo credentials fill. |
| **Sign Up** | [`signup.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/signup.html) | New customer registration form capturing full name, email, phone, and delivery address. |

### 👨‍🍳 2. Admin & Restaurant KDS Portal
| Page Name | File Path | Purpose & Key Features |
| :--- | :--- | :--- |
| **Admin Dashboard** | [`admin-dashboard.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin-dashboard.html) | Store admin portal: KPI metrics (₹84k revenue, orders, prep time), Live Kitchen Queue with WSJF priority scores, and Dish Catalog CRUD modal. |
| **Kitchen Display (KDS)** | [`kitchen-display.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen-display.html) | Dedicated cook & expeditor interface: 3-column kanban swimlanes (New, Cooking, Ready), multi-station routing (Handi, Tandoor, Grills, Pantry), all-day item prep aggregator, live WSJF urgency calculations, interactive touch item checkboxes, ticket recall modal, fullscreen station mode, and sound alert engine (isolated from admin/portal privileges). |
| **Executive BI Reports** | [`admin-reports.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin-reports.html) | Chart.js interactive visualizations: Hourly sales volume, category share doughnut, Single Exponential Smoothing (SES) item velocity, and delivery logs. |
| **Food Categories CRUD** | [`admin-categories.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin-categories.html) | Complete CRUD for `categories` table: slug auto-generation, active/hidden toggles, dish counters, and Add/Edit category modal. |
| **User & Staff RBAC** | [`admin-users.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin-users.html) | Role-Based Access Control (`roles` & `users`): Customer, Kitchen Staff, Delivery Rider, Admin filters, Argon2id encryption context, and status toggles. |

### 🚴 3. Delivery & System Pages
| Page Name | File Path | Purpose & Key Features |
| :--- | :--- | :--- |
| **Delivery Partner App** | [`delivery-partner.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery-partner.html) | Courier portal: Online/Offline duty toggle, earnings stats, active delivery workflow, GPS turn-by-turn navigator, and secret customer OTP verification. |
| **GST Tax Invoice** | [`invoice.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/invoice.html) | Official Indian e-commerce GST Tax Invoice: SAC/HSN codes, 5% food GST breakdown (CGST+SGST), `@media print` layout, and digital verification QR. |
| **Terms of Service** | [`terms.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/terms.html) | Tripartite aggregator terms & conditions, perishable food cancellation policies, GST compliance, and sticky Table of Contents. |
| **Privacy Policy** | [`privacy.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/privacy.html) | DPDPA 2023 & GDPR compliant privacy policy, data minimization, Argon2id security guarantees, data rights, and grievance officer contact. |
| **404 Not Found** | [`404.html`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/404.html) | Playful food-themed error page ("Page Devoured"), quick search redirect, popular cuisine tags, and navigation shortcuts. |

---

## 💡 "ഇത്രേ മതിയോ?" (Is this enough for the MCA project?)

**Yes, absolutely!** This set of 9 pages covers the entire software engineering lifecycle required by university evaluators for **MCSP-232 (MCA Major Project)**:

1. **User Role (Customer Journey):**
   - User Registration & Authentication (`signup.html`, `login.html`)
   - Browsing & Discovery (`index.html`)
   - Menu Selection & Add to Cart (`restaurant.html`, `cart.html`)
   - Order Placement & Payments (`checkout.html`)
   - Real-time Order Tracking (`order-status.html`)
   - Order History & Tax Receipts (`order-history.html`)

2. **Admin Role (Store Management Journey):**
   - Store KPIs & Daily Sales Overview (`admin-dashboard.html`)
   - Kitchen Dispatch & Order Fulfillment (`admin-dashboard.html`)
   - Dish Catalog & Stock Availability Management (`admin-dashboard.html`)

3. **Academic Algorithmic Rigor (Highlighted in UI):**
   - **Market Basket Analysis (Apriori Algorithm):** Cross-selling recommendation system.
   - **Dynamic Kitchen Queue (WSJF Algorithm):** Weighted Shortest Job First scheduling.

---

## 🔮 Step 2: Preparing for PHP Backend (OOP/MVC)

When transitioning to PHP:
1. Extract `<header>` into `includes/header.php`.
2. Extract `<footer>` into `includes/footer.php`.
3. Create `config/database.php` for MySQL 8.0 InnoDB connection via PDO.
4. Implement MVC Controllers (`OrderController`, `MenuController`, `AuthController`).
