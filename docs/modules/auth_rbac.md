# Module: Authentication & RBAC System

## 1. Overview & Security Model
Zippy enforces role-based access control (**RBAC**) across four independent portals (`/admin/`, `/kitchen/`, `/delivery/`, `/customer/`). Each portal operates on an isolated PHP session cookie resolved dynamically by `getCurrentPortal()` in `config/config.php`, allowing multiple roles to be signed in simultaneously in different browser tabs/sessions without session overwrite.

## 2. Core Roles & Schema
Defined in `roles` (`database/schema.sql`) and enforced on `users.role_id` (`users.is_active = 1` required):
- `1` -> `ADMIN`
- `2` -> `CUSTOMER`
- `3` -> `KITCHEN`
- `4` -> `RIDER`

## 3. Core Code Entrypoints
- **`config/config.php`**:
  - Portal session isolation & resolution: `getCurrentPortal()`, `getAllPortalActors()`.
  - Session state & RBAC guards: `isLoggedIn()`, `getCurrentUser()`, `hasRole()`, `hasPermission()`, `checkPermission()`, `requireLogin()`, `requireRole()`, `requireAdmin()`, `requireKitchen()`, `requireRider()`, `requireCustomer()`, `canAccessOrder()`.
  - CSRF helpers: `csrfToken()`, `csrfField()`, `verifyCsrfToken()`, `verifyRequestCsrf()`.
- **`includes/auth.php`**:
  - Credentials & registration: `loginUser()`, `registerCustomer()`, `logoutUser()`, `getUserRole()`, `updateUserRole()`.
  - Password recovery (`security_question`, `security_answer_hash`): `getSecurityQuestions()`, `normalizeSecurityAnswer()`, `ensureSecurityQuestionColumns()`, `lookupUserSecurityQuestion()`, `resetPasswordWithSecurityAnswer()`, `updateUserSecurityQuestion()`.
  - Demo-only email normalization (`APP_DEMO_MODE`): `resolveEmailAlias()`.
- **Portal Auth Views & Guards:**
  - Customer: `customer/includes/auth_guard.php` (`requireCustomer()`), `customer/login.php`, `customer/signup.php`, `customer/forgot-password.php`, `customer/logout.php`.
  - Admin / Kitchen / Delivery: `{admin,kitchen,delivery}/login.php`, `{admin,kitchen,delivery}/logout.php`.

## 4. Security Constraints
1. Passwords and security recovery answers are hashed via PHP `password_hash()` and verified strictly via `password_verify()` (with automatic `password_needs_rehash()` upgrades; zero plaintext or master-password fallbacks).
2. `session_regenerate_id(true)` executes upon successful `loginUser()`.
3. All state-mutating `POST` and AJAX handlers validate `verifyCsrfToken()` / `verifyRequestCsrf()`.
4. All unauthenticated requests to private routes redirect cleanly (web views) or fail closed with HTTP `401`/`403` JSON errors (APIs & AJAX endpoints).
5. `users.is_active = 0` disables suspended accounts during login and active user queries (`AdminService::handleUserRbacAction()`).
