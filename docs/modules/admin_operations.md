# Module: Admin Operations, Analytics & Finance

## 1. Overview
The Admin Portal provides centralized operational control over menu catalog & customizations, food moods, live orders, kitchen/fleet rosters, daily cash register Z-report closures, payouts, BI analytics (`SESDemandForecast` + `AprioriEngine`), reviews, FAQs, RBAC user management, and immutable order audit logs.

## 2. Core Services (`includes/Services/`)
- **`CatalogService.php`**: Category CRUD & sort ordering (`admin/categories.php`), Dish CRUD & bulk/single stock adjustments (`admin/dishes.php`, `admin/ajax/adjust-stock.php`, `admin/ajax/toggle-stock.php`, `admin/ajax/bulk-dishes.php`), Customization groups/options (`admin/dish-customizations.php`), and Mood assignments (`admin/moods.php`).
- **`OrderService.php`**: Admin order board (`admin/orders.php`: `getAdminOrdersData()`, `batchUpdateOrderStatus()`, `markOrderCodPaid()`) and AJAX single-order transitions (`admin/ajax/update-order-status.php`).
- **`FinanceService.php`**: Financial ledger & daily cash drawer Z-report closure (`admin/financial-transactions.php`: `discrepancy_amount = actual_physical_cash - expected_cod_cash`), Kitchen HR & salary disbursement (`admin/kitchen-view.php`), and Executive BI reports (`admin/reports.php`).
- **`FleetService.php`**: Fleet overview, COD cash settlement, and rider payouts (`admin/delivery-view.php`).
- **`AdminService.php`**: Operations overview KPIs (`admin/index.php`), Global Command Search (`admin/ajax/command-search.php`), FAQ management (`admin/faqs.php` with `sanitizeFaqContent()`), Reviews moderation & CSV export (`admin/reviews.php`), User directory & RBAC transitions (`admin/users.php`), and Audit log explorer (`admin/audit-logs.php`, `admin/ajax/get-order-audit-trail.php`).

## 3. Data Integrity & Soft-Delete Governance
1. **Soft Deletes Mandatory:** Archiving users, categories, dishes, customization groups/options, moods, or FAQs executes `UPDATE ... SET is_active = 0` inside domain services (supported by `database/migrate_soft_deletes.php`) so historical `order_items`, `orders`, and `order_audit_trail` foreign keys remain intact.
2. **Audit Immutability:** `order_audit_trail` (`old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) has no UI edit/delete controls.
3. **ACID Multi-Table Mutations:** Stock adjustments, batch status transitions, COD settlements, salary disbursements, and cash drawer closures execute inside `dbTransactional()` with `SELECT ... FOR UPDATE` row locks.
4. **Strict Sort Allowlists:** Dynamic `ORDER BY` clauses (e.g., `$sortMap` in `CatalogService::getAdminDishesData()`) validate inputs against explicit allowlists.
