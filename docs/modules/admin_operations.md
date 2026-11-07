# 👨‍💼 Module: Admin Operations & Governance (admin_operations.md)

> **Context Domain:** Catalog Management, Reports, Cash Registers, Payroll & Audit Logs  
> **Relevant Tables:** `menu_items`, `categories`, `money_transactions`, `cash_register_closures`, `staff_profiles`, `staff_salary_payouts`, `order_audit_trail`

---

## 1. Operational Capabilities

The Admin Console (`admin/`) offers end-to-end governance:
- **Menu & Stock Management:** Create, edit, toggle availability, and bulk-adjust dish prices and inventory.
- **Financial Ledger & Cash Register:** Record cash inflows/outflows, reconcile daily sales against drawer cash, and log closures with discrepancy tracking.
- **Staff & Payroll:** Manage staff designations, calculate monthly payouts, and record disbursement dates.
- **System Audit Viewer:** Full visibility into `order_audit_trail` and state mutations.
- **Sales Analytics:** Visual summaries of gross sales, net revenue, bestsellers, and hourly order distributions.

---

## 2. Key Source Files

- **`admin/index.php`**: Executive dashboard with KPI counters, revenue charts, and live order status tickers.
- **`admin/dishes.php` & `admin/categories.php`**: Full CRUD operations on menu catalog and taxonomy.
- **`admin/financial-transactions.php`**: Cashbook ledger for recording miscellaneous income and operational expenses.
- **`admin/audit-logs.php`**: Searchable audit log inspector with filter by date, order ID, and user.
- **`admin/reports.php`**: Demand forecasting reports utilizing Single Exponential Smoothing (SES).
- **`admin/ajax/toggle-stock.php` & `admin/ajax/adjust-stock.php`**: Fast inline AJAX stock adjustments.

---

## 3. Engineering Invariants

1. **Role Assertion:** Every script in `admin/` must execute `requireRole(['ADMIN'])`.
2. **Double-Entry Financial Checks:** Cash register closures must explicitly compute `cash_discrepancy = closing_cash - (opening_cash + cash_sales)`.
3. **Soft Deletions Preferred:** When disabling menu items or categories, update `is_active = 0` rather than issuing hard `DELETE` queries to preserve relational integrity with past `order_items`.
