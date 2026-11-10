# Zippy Project Hub — Master Navigation & Architectural Map

Welcome to the central index for the **Zippy Online Food Ordering Platform** (MCSP-232 MCA Major Project). This document serves as the primary navigation hub and architectural truth for both developers and AI coding assistants.

---

## 1. Documentation Index
- **[PHP_STANDARDS.md](./PHP_STANDARDS.md)** *(Rules)*: Strict types (`declare(strict_types=1)`), PDO prepared statements, ACID transaction + `FOR UPDATE` locking, PSR-12 brace/spacing rules, `camelCase` helpers/methods, and view-service separation.
- **[DATA_DICTIONARY.md](./DATA_DICTIONARY.md)** *(Truth)*: Complete 3NF relational schema across all **28 tables** (`schema.sql` + `food_moods` + v2 feature tables), soft-delete `is_active` governance, `order_status` state machine, and audit trail structure.
- **[features.md](./features.md)** *(Inventory)*: Complete catalog of implemented platform features, v2 enhancements, and engineering mechanics.
- **[TASK_LOG.md](./TASK_LOG.md)** *(Changelog)*: Chronological record of all implemented features, bug fixes, audits, and compliance phases.

### Module Specifications (`docs/modules/`)
1. [Authentication & RBAC System](./modules/auth_rbac.md)
2. [Customer Storefront & Discovery Engine](./modules/customer_storefront.md)
3. [Order Lifecycle, Cart & ACID Checkout](./modules/order_lifecycle.md)
4. [Kitchen Display System (KDS) & Queue Routing](./modules/kitchen_kds.md)
5. [Delivery Partner Fleet & Live Tracking](./modules/delivery_fleet.md)
6. [Admin Operations, Analytics & Finance](./modules/admin_operations.md)
7. [Computational Algorithms (Apriori, SES, WSJF, FCFS)](./modules/computational_algorithms.md)

---

## 2. Active Application Architecture & Routing Matrix

### Core Configuration, Helpers & Services
- **Core Bootstrap:** `config/config.php`, `config/database.php` (`getDbConnection()`, `checkDbStatus()`)
- **Shared Auth & Utilities:** `includes/auth.php` (`loginUser()`, `registerCustomer()`, `logoutUser()`), `includes/functions.php` (`dbQuery()`, `dbTransactional()`, `transitionOrderStatus()`, `autoAllocateReadyOrdersFcfs()`, `validateCouponForSubtotal()`, `validateServiceableLocation()`, `awardLoyaltyPointsForOrder()`, `getUserDietaryPreferences()`), `includes/view_helpers.php` (`getUserInitials()`, `renderOrderCard()`, `renderEmptyState()`, `formatKdsWaitTime()`, `getKdsPriorityBadge()`, `evaluateDishDietaryConflicts()`, `renderDishFavoriteButtonHtml()`, `renderDishDietaryMetadataHtml()`)
- **Algorithm Engines:** `includes/AprioriEngine.php`, `includes/WSJFQueue.php`, `includes/FCFSQueue.php`, `includes/SESDemandForecast.php`
- **Domain Services (`includes/Services/`):**
  - `CatalogService.php` — storefront menu/search/header state, nutrition/allergen catalog metadata, dish favorites, admin categories/dishes/customizations/moods CRUD & soft archival
  - `OrderService.php` — customer checkout (with FCFS slot throttling, loyalty redemption & coupons), 60s cancel, history, invoice, rating, FAQ/threaded tickets, KDS lanes/station checklists/KOT, and admin order batches/COD capture
  - `FleetService.php` — GPS route interpolation (`interpolateRoute()`), rider duty toggle, FCFS/manual claim, OTP delivery fulfillment + loyalty awards, Rider SOS/failed triage & re-dispatch, admin fleet payouts & COD settlements
  - `FinanceService.php` — money transactions ledger, daily Z-report cash drawer closure, kitchen payroll disbursement, executive BI reports
  - `AdminService.php` — operations dashboard KPIs, audit log export & filters, support ticket desk, dynamic coupon CRUD, FAQ CRUD, review moderation/export, user directory & RBAC management, customer dietary preferences & loyalty profile
- **Launchpad:** `index.php`

### Portal Routes & JSON/AJAX Endpoints

| Actor / Portal | Guard / Middleware | Entry Views | AJAX / API Endpoints |
| :--- | :--- | :--- | :--- |
| **Customer Portal** (`/customer/`) | `requireCustomer()` (`customer/includes/auth_guard.php`) for private account/order/checkout routes | `index.php`, `restaurant.php`, `search.php`, `cart.php`, `checkout.php`, `order-success.php`, `order-status.php`, `order-history.php`, `invoice.php`, `rate-order.php`, `manage-addresses.php`, `profile.php`, `offers.php`, `help.php`, `login.php`, `signup.php`, `forgot-password.php`, `logout.php`, `privacy.php`, `terms.php`, `404.php` | `customer/ajax/apriori-api.php`, `customer/ajax/cart-sync.php`, `customer/ajax/get-dish-customizations.php`, `customer/ajax/order-status-api.php`, `customer/ajax/slot-availability.php`, `customer/ajax/toggle-favorite.php`, `customer/ajax/validate-coupon.php` |
| **Kitchen KDS** (`/kitchen/`) | `requireKitchen()` | `kitchen/index.php`, `kitchen/kot.php`, `kitchen/login.php`, `kitchen/logout.php` | `kitchen/ajax/poll-orders.php`, `kitchen/ajax/toggle-item-prepped.php`, `kitchen/ajax/toggle-stock.php`, `kitchen/ajax/update-status.php` |
| **Delivery Fleet** (`/delivery/`) | `requireRider()` | `delivery/index.php`, `delivery/login.php`, `delivery/logout.php` | `delivery/ajax/cashout.php` |
| **Admin Dashboard** (`/admin/`) | `requireAdmin()` | `admin/index.php`, `admin/orders.php`, `admin/dishes.php`, `admin/categories.php`, `admin/moods.php`, `admin/dish-customizations.php`, `admin/kitchen-view.php`, `admin/delivery-view.php`, `admin/financial-transactions.php`, `admin/reports.php`, `admin/reviews.php`, `admin/faqs.php`, `admin/support-tickets.php`, `admin/coupons.php`, `admin/users.php`, `admin/audit-logs.php`, `admin/login.php`, `admin/logout.php` | `admin/ajax/adjust-stock.php`, `admin/ajax/bulk-dishes.php`, `admin/ajax/command-search.php`, `admin/ajax/get-fleet-telemetry.php`, `admin/ajax/get-order-audit-trail.php`, `admin/ajax/toggle-stock.php`, `admin/ajax/update-order-status.php` |
| **Shared Platform API** (`/api/`) | Role-scoped session / HMAC-SHA256 webhook signature | — | `api/catalog-sync.php`, `api/dish-reviews.php`, `api/live-location.php`, `api/live-stream.php`, `api/payment-webhook.php`, `api/sse-stream.php` |
| **Database & Verification** | CLI / Admin maintenance | `database/schema.sql`, `database/seed.php`, `database/ensure_seed_orders.php`, `database/migrate_*.php` (`12` migrations) | `tests/*.php` & `tests/*.py` verification suite |

---

## 3. Compliance Rollout Roadmap
1. **Phase 0 & 1 *(Completed)*:** CSRF header standardization, repaired `auth_guard.php`, removed password fallback / gated demo mode, closed API/customer RBAC & IDOR gaps, enforced fail-closed CSRF + HMAC webhook signature verification, and eliminated XSS/JS sinks (`TASK-006`, `TASK-007`).
2. **Phase 2 *(Completed)*:** Wrapped checkout, claim, OTP delivery, inventory adjustments, financial settlements, and reviews inside `dbTransactional()` + `FOR UPDATE`, retired duplicate audit DB trigger, and eliminated raw SQL interpolation (`TASK-007`).
3. **Phase 3 *(Completed)*:** Enabled `declare(strict_types=1);` and complete parameter/return/closure type declarations across all active PHP files (`TASK-008`).
4. **Phase 4 *(Completed)*:** Extracted all portal queries/business logic into `includes/Services/*` and `includes/view_helpers.php`, and enforced soft-delete (`is_active = 0`) governance across catalog, customizations, moods, FAQs, and addresses (`TASK-009`).
5. **Phase 5 *(Completed)*:** Applied PSR-12 formatting sweep (`class`/`function` braces on new line, control spacing), standardized all functions/methods to `camelCase`, and synchronized all governance/module docs (`TASK-010`).
