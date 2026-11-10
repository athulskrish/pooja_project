# 🛠️ PHP Engineering & Scalability Standards (PHP_STANDARDS.md)

> **Mandatory Quality Gate:** All code generated, refactored, or reviewed within the Zippy platform must strictly adhere to these rules. This guarantees enterprise maintainability, high concurrency handling, and defense-in-depth security.

---

## 1. Language & Type Safety (PHP 8.1+)

### 1.1 Strict Typing
Every PHP file containing classes, functions, or business logic **must** declare strict typing as the first statement:
```php
<?php
declare(strict_types=1);
```

### 1.2 Explicit Method Signatures
- All function/method arguments must declare explicit scalar or object types.
- All function/method returns must declare return types (`void`, `array`, `?int`, `float`, etc.).
- Never use untyped signatures or omit nullable indicators (`?string`).

```php
// ✅ CORRECT
public function calculateSubtotal(array $items, float $taxRate): float 
{
    // ...
}

// ❌ INCORRECT (Untyped, error-prone)
function calculateSubtotal($items, $taxRate) 
{
    // ...
}
```

---

## 2. Database Concurrency & ACID Integrity

### 2.1 Parameterized Queries Only (Zero Raw SQL Interpolation)
Direct variable interpolation in SQL strings is strictly prohibited to prevent SQL Injection:
```php
// ✅ CORRECT (PDO Parameter Binding)
$stmt = $pdo->prepare("SELECT user_id, password_hash, role_id FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ❌ FORBIDDEN (Vulnerable to SQL Injection)
$query = "SELECT * FROM users WHERE email = '$email'";
```

### 2.2 ACID Transactions & Concurrency Control
Any operation mutating multiple records or transitioning critical states (e.g., checkout, wallet deductions, stock depletion, status progression) **must** run within a transaction with error rollback:
```php
try {
    $pdo->beginTransaction();

    // Pessimistic locking to prevent race conditions during high concurrent traffic
    $stmt = $pdo->prepare("SELECT stock_quantity FROM menu_items WHERE item_id = :id FOR UPDATE");
    $stmt->execute([':id' => $itemId]);
    $stock = (int) $stmt->fetchColumn();

    if ($stock < $requestedQty) {
        throw new RuntimeException("Insufficient stock for item {$itemId}");
    }

    // Deduct stock
    $updateStmt = $pdo->prepare("UPDATE menu_items SET stock_quantity = stock_quantity - :qty WHERE item_id = :id");
    $updateStmt->execute([':qty' => $requestedQty, ':id' => $itemId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Transaction Failed: " . $e->getMessage());
    throw $e;
}
```

---

## 3. Defense-in-Depth Security

### 3.1 CSRF Verification
Every `POST`, `PUT`, or `DELETE` request in forms or AJAX endpoints must validate a session-bound CSRF token:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken((string)$submittedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or expired CSRF token']);
        exit;
    }
}
```

### 3.2 Context-Aware Output Escaping (XSS Prevention)
Never output unsanitized user or database values directly to HTML templates. Always pass through escaping:
```php
// ✅ CORRECT
<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>

// Or using helper function:
<?= e($item['item_name']) ?>
```

### 3.3 Strict Role-Based Access Control (RBAC)
Every portal endpoint must assert authorization before executing queries:
```php
require_once __DIR__ . '/../includes/auth.php';
// Rejects any session not explicitly holding the specified role:
requireRole(['CUSTOMER']);
```

---

## 4. Architecture & Separation of Concerns

1. **View / Template Layer (`customer/`, `kitchen/`, `delivery/`, `admin/`):**
   - Focuses strictly on layout composition, sanitizing output variables, and UI state.
   - Must **not** define raw SQL queries or business algorithms directly inside HTML templates.
2. **Business Engine & Service Layer (`includes/`, `includes/Services/`):**
   - Reusable domain services, calculators, and computational models (`AprioriEngine`, `WSJFQueue`, `SESDemandForecast`, `FCFSQueue`, plus `CatalogService`, `OrderService`, `FleetService`, `FinanceService`, and `AdminService`).
   - Must be decoupled, stateless where possible, and unit-testable.
3. **Database Layer (`config/database.php`):**
   - Centralized PDO connection instance via `getDbConnection()` with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` and `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`.
4. **Audit Trail Logging:**
   - Any state change to an order (e.g. `PLACED` -> `CONFIRMED` -> `COOKING` -> `READY` -> `OUT_FOR_DELIVERY` -> `DELIVERED`) must record an entry in `order_audit_trail` with `order_id`, `old_status`, `new_status`, and `updated_by_user_id` (`change_timestamp` is populated automatically).

---

## 5. Coding Style & Formatting (PSR-12)

- **Indentation:** 4 spaces (no hard tabs).
- **Control Structures:** Opening braces on the same line; 1 space between keyword and parenthesis:
  ```php
  if ($condition) {
      // action
  } elseif ($otherCondition) {
      // alternative
  } else {
      // fallback
  }
  ```
- **Classes & Methods:** Class braces on their own line; method braces on their own line:
  ```php
  class OrderService
  {
      public function executeOrder(int $orderId): bool
      {
          // ...
      }
  }
  ```
- **Naming Conventions:**
  - Classes: `PascalCase` (`AprioriEngine`, `WSJFQueue`)
  - Methods & Functions: `camelCase` (`calculateTotal`, `verifyCsrfToken`)
  - Constants: `SCREAMING_SNAKE_CASE` (`APP_ENV_PRODUCTION`, `MAX_DISCOUNT_PERCENT`)
  - Database Columns & Tables: `snake_case` (`order_items`, `created_at`)

---

## 6. Verification Checklist for AI Tasks

Before delivering any code changes or proposals:
- [ ] Has `declare(strict_types=1);` been placed?
- [ ] Are all query inputs parameterized via PDO?
- [ ] Is input sanitized and output HTML-escaped?
- [ ] Are transactions and locks utilized for multi-record or concurrent operations?
- [ ] Is the RBAC check at the file entry point?
- [ ] Does this break existing PSR-12 styling?
