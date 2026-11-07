# 🔐 Module: Authentication & RBAC (auth_rbac.md)

> **Context Domain:** User Identity, Session Security & Role-Based Access Control  
> **Relevant Tables:** `users`, `roles`

---

## 1. Architecture & Flow

The platform uses native PHP sessions with strict cookie parameters (`httponly`, `samesite=Lax`, secure where HTTPS is present). Authentication is managed centrally via `includes/auth.php`.

### Role Definitions (`roles` table):
- `1` / `ADMIN`: Unrestricted portal access, catalog management, reports, payroll.
- `2` / `CUSTOMER`: Storefront browsing, cart, order placement, order tracking.
- `3` / `KITCHEN`: Kitchen Display System (KDS), cooking status progression, KOT generation.
- `4` / `RIDER`: Delivery fleet, active order acceptance, GPS updates, OTP delivery confirmation.

---

## 2. Key Source Files

- **`includes/auth.php`**: Contains `loginUser()`, `logoutUser()`, `getCurrentUser()`, `hasRole()`, `requireRole()`.
- **`customer/includes/auth_guard.php`**: Enforces customer-specific login checks and redirects unauthenticated users to `/customer/login.php`.
- **`customer/login.php` & `customer/signup.php`**: Customer identity intake.
- **`admin/login.php`**, **`kitchen/login.php`**, **`delivery/login.php`**: Dedicated portal login endpoints.

---

## 3. Security Guidelines & Invariants

1. **Password Hashing:** Always use `password_hash($password, PASSWORD_ARGON2ID)` or `PASSWORD_BCRYPT`. Never store plain-text passwords.
2. **Session Regeneration:** Invoke `session_regenerate_id(true)` immediately after successful login to prevent Session Fixation attacks.
3. **Role Assertion:** Every protected page must invoke `requireRole(['ROLE_NAME'])` before rendering any HTML or executing queries.
