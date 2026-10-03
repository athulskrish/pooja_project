# SYSTEM ANALYSIS & DESIGN SPECIFICATIONS
## E-Commerce Platform for Food Order (MCSP-232)
**Academic Level:** Master of Computer Applications (MCA Major Project)  
**Student:** Pooja JP | **Enrollment No:** 2500051247  
**Institution:** Indira Gandhi National Open University (IGNOU)  

---

## 1. DATA FLOW DIAGRAMS (DFD)

Data Flow Diagrams (DFD) model the flow of data through the system, identifying external entities, data transformation processes, data stores, and directional data pathways.

---

### 1.1 DFD Level 0: Context Diagram
The Level 0 Context Diagram establishes the boundary of the **E-Commerce Food Ordering & Restaurant Management System**, illustrating all external entities and their primary interactions with the centralized system.

```mermaid
flowchart TD
    %% External Entities
    Customer["👤 Customer"]
    Admin["👨‍💼 Restaurant Administrator"]
    Kitchen["👨‍🍳 Kitchen Staff / Chef"]
    Delivery["🚴 Delivery Partner / Courier"]
    PaymentGW["💳 Payment Gateway (Razorpay/UPI)"]
    SMS["📲 SMS / Notification Gateway"]

    %% Central Process
    System(("0.0<br/><b>E-Commerce Platform for<br/>Food Order System</b>"))

    %% Data Flows - Customer
    Customer -->|"Registration, Login Credentials,<br/>Cart Items, Delivery Address, Reviews"| System
    System -->|"Menu Catalog, Apriori Recommendations,<br/>Order Summary, Live ETA, Tax Invoice"| Customer

    %% Data Flows - Admin
    Admin -->|"Category & Dish CRUD, Price Updates,<br/>Stock Adjustments, Staff RBAC Roles"| System
    System -->|"Sales BI Reports, SES Demand Forecasts,<br/>Inventory Alerts, Order Audit Logs"| Admin

    %% Data Flows - Kitchen Staff
    Kitchen -->|"Order Status Advances<br/>(Cooking, Ready for Dispatch)"| System
    System -->|"Prioritized Kitchen Queue (WSJF),<br/>Ticket Details, Item Prep Times"| Kitchen

    %% Data Flows - Delivery Partner
    Delivery -->|"Duty Toggle (Online/Offline),<br/>Delivery OTP Verification, GPS Location"| System
    System -->|"Assigned Dispatch Orders, Customer Address,<br/>Turn-by-Turn Route, Delivery Fee Payouts"| Delivery

    %% Data Flows - External Services
    System -->|"Payment Initiation Request<br/>(Amount, Order ID, User Info)"| PaymentGW
    PaymentGW -->|"Transaction Webhook / Token<br/>(Payment Status, Ref ID)"| System

    System -->|"Order Confirmation, Status Milestone Alerts,<br/>Delivery Verification OTP"| SMS
```

---

### 1.2 DFD Level 1: System Decomposition Diagram
The Level 1 DFD decomposes the system into six core functional subsystems and maps their interactions with the **Ten 3NF Data Stores**.

```mermaid
flowchart TB
    %% External Entities
    Customer["👤 Customer"]
    Admin["👨‍💼 Restaurant Admin"]
    Kitchen["👨‍🍳 Kitchen Staff"]
    Rider["🚴 Delivery Rider"]
    PaymentGW["💳 Payment Gateway"]

    %% Data Stores
    D1[("D1: roles & users")]
    D2[("D2: user_addresses")]
    D3[("D3: categories")]
    D4[("D4: menu_items")]
    D5[("D5: orders")]
    D6[("D6: order_items")]
    D7[("D7: payments")]
    D8[("D8: delivery_dispatches")]
    D9[("D9: order_audit_trail")]

    %% Subsystems / Processes
    P1(("1.0<br/>User Authentication<br/>& Access Control"))
    P2(("2.0<br/>Menu Browsing &<br/>Apriori Engine"))
    P3(("3.0<br/>Cart Validation &<br/>Checkout Transaction"))
    P4(("4.0<br/>Kitchen Queue<br/>Scheduling (WSJF)"))
    P5(("5.0<br/>Delivery Dispatch<br/>& Live Tracking"))
    P6(("6.0<br/>BI Analytics &<br/>SES Demand Forecast"))

    %% Process 1.0 Flows
    Customer -->|"Auth Credentials"| P1
    Admin -->|"Staff Credentials"| P1
    P1 -->|"Verify / Store Argon2id Hash"| D1
    P1 -->|"Session Token & RBAC Claims"| Customer

    %% Process 2.0 Flows
    Customer -->|"Search Query, Filter by Category"| P2
    D3 -->|"Category Data"| P2
    D4 -->|"Dishes, Live Stock, Prep Time"| P2
    D6 -->|"Historical Basket Pairs"| P2
    P2 -->|"Categorized Menu & Add-on Recs"| Customer

    %% Process 3.0 Flows
    Customer -->|"Submit Cart & Address Selection"| P3
    D2 -->|"Saved Delivery Address"| P3
    P3 -->|"Row Lock & Stock Check (FOR UPDATE)"| D4
    P3 -->|"Process Payment Request"| PaymentGW
    PaymentGW -->|"Transaction Status & Ref"| P3
    P3 -->|"Insert Order Header"| D5
    P3 -->|"Insert Line Items & Deduct Stock"| D6
    P3 -->|"Record Payment Entry"| D7
    P3 -->|"Log Status: PLACED"| D9
    P3 -->|"Order Confirmation & Delivery OTP"| Customer

    %% Process 4.0 Flows
    D5 -->|"Active Placed / Confirmed Orders"| P4
    D6 -->|"Ordered Items & Prep Times"| P4
    P4 -->|"Prioritized KDS Kanban Tickets"| Kitchen
    Kitchen -->|"Mark Cooking / Ready"| P4
    P4 -->|"Update Order Status"| D5
    P4 -->|"Append Status Transition"| D9

    %% Process 5.0 Flows
    D5 -->|"Ready Orders for Dispatch"| P5
    P5 -->|"Assign Order & Dispatch Ticket"| D8
    D8 -->|"Pickup Notification & Route"| Rider
    Rider -->|"Submit Delivery OTP"| P5
    P5 -->|"Update Status: DELIVERED"| D5
    P5 -->|"Log Status Transition"| D9
    P5 -->|"Real-time ETA & Courier Map"| Customer

    %% Process 6.0 Flows
    Admin -->|"Generate Sales / Forecast Request"| P6
    D5 -->|"Historical Sales Data"| P6
    D6 -->|"Item Velocities"| P6
    D4 -->|"Current Stock Levels"| P6
    P6 -->|"SES Stock Forecast & BI Visuals"| Admin
    Admin -->|"Catalog Updates & Stock Adjustments"| D4
```

---

### 1.3 DFD Level 2: Critical Subsystem Decompositions

#### A. Level 2 DFD for Process 3.0: Cart Validation & Concurrency-Controlled Checkout
This diagram demonstrates the high-concurrency ACID transactional checkout mechanism, preventing overselling via pessimistic row-level locking.

```mermaid
flowchart TD
    Cust["👤 Customer"]
    PG["💳 Payment Gateway"]

    subgraph Process_3_Checkout["3.0 Order Checkout Subsystem (ACID Transaction)"]
        P3_1(("3.1<br/>Validate Cart &<br/>Address"))
        P3_2(("3.2<br/>Acquire Row Lock &<br/>Verify Stock Quantity"))
        P3_3(("3.3<br/>Compute Tax (GST)<br/>& Delivery Charge"))
        P3_4(("3.4<br/>Execute Payment Handshake<br/>& Callback Webhook"))
        P3_5(("3.5<br/>Atomic Commit:<br/>Order, Items & Stock Deduct"))
        P3_6(("3.6<br/>Generate 6-Digit OTP<br/>& Dispatch Invoice"))
    end

    DS_Menu[("D4: menu_items")]
    DS_Order[("D5: orders")]
    DS_Items[("D6: order_items")]
    DS_Pay[("D7: payments")]
    DS_Audit[("D9: order_audit_trail")]

    Cust -->|"Cart Items, Address ID, Payment Method"| P3_1
    P3_1 -->|"Sanitized Item IDs & Quantities"| P3_2
    P3_2 -->|"SELECT ... FOR UPDATE"| DS_Menu
    DS_Menu -->|"Available Stock Balance"| P3_2
    P3_2 -->|"Stock Validated"| P3_3
    P3_3 -->|"Calculated Bill Total"| P3_4
    P3_4 <-->|"Process Online Transaction"| PG
    P3_4 -->|"Payment Verified"| P3_5
    P3_5 -->|"INSERT Order (Status: CONFIRMED)"| DS_Order
    P3_5 -->|"INSERT Line Items"| DS_Items
    P3_5 -->|"UPDATE Stock (stock = stock - qty)"| DS_Menu
    P3_5 -->|"INSERT Payment Record"| DS_Pay
    P3_5 -->|"LOG State: PLACED -> CONFIRMED"| DS_Audit
    P3_5 -->|"Trigger Invoice & OTP Creation"| P3_6
    P3_6 -->|"Tax Invoice & Secret OTP"| Cust
```

#### B. Level 2 DFD for Process 4.0: Kitchen Display Scheduling (WSJF Priority Queue)
This diagram illustrates the algorithmic prioritization of kitchen prep tickets based on waiting duration and item preparation times.

```mermaid
flowchart TD
    DS_Orders[("D5: orders")]
    DS_Items[("D6: order_items")]
    Cook["👨‍🍳 Kitchen Cook"]
    DS_Audit[("D9: order_audit_trail")]

    subgraph Process_4_KDS["4.0 Kitchen Display & Queue Scheduling Subsystem"]
        P4_1(("4.1<br/>Ingest New Confirmed<br/>Kitchen Orders"))
        P4_2(("4.2<br/>Fetch Dish Prep Times &<br/>Compute Ticket Sum"))
        P4_3(("4.3<br/>Calculate WSJF Priority:<br/>Cost of Delay / Prep Time"))
        P4_4(("4.4<br/>Render Sorted Kanban<br/>Columns (WSJF Order)"))
        P4_5(("4.5<br/>Handle 1-Click State Advance<br/>& Sound Alert Trigger"))
    end

    DS_Orders -->|"New Orders (Status: CONFIRMED)"| P4_1
    P4_1 -->|"Order ID & Items"| P4_2
    DS_Items -->|"Item Quantities & Prep Minutes"| P4_2
    P4_2 -->|"Total Ticket Prep Time & Elapsed Time"| P4_3
    P4_3 -->|"WSJF Urgency Score"| P4_4
    P4_4 -->|"Audio Beep & Prioritized Cards"| Cook
    Cook -->|"Click 'Start Cooking' / 'Mark Ready'"| P4_5
    P4_5 -->|"UPDATE orders.order_status"| DS_Orders
    P4_5 -->|"INSERT Audit Trail (COOKING/READY)"| DS_Audit
```

---

## 2. USE CASE MODELING

### 2.1 Complete Use Case Diagram
The Use Case diagram depicts four human actors and one external system actor, detailing user goals and dependency relationships (`<<include>>` and `<<extend>>`).

```mermaid
flowchart LR
    %% Actors
    Customer["👤 Customer"]
    Admin["👨‍💼 Administrator"]
    Kitchen["👨‍🍳 Kitchen Staff"]
    Rider["🚴 Delivery Partner"]
    PaymentSystem["🏛️ Payment Gateway"]

    %% Use Cases - Customer
    subgraph Boundary["🍱 E-Commerce Platform for Food Order Boundary"]
        UC1(["UC-01: Register & Authenticate"])
        UC2(["UC-02: Manage Delivery Addresses"])
        UC3(["UC-03: Browse & Search Menu Items"])
        UC4(["UC-04: View Apriori Recommendations"])
        UC5(["UC-05: Manage Cart & Quantities"])
        UC6(["UC-06: Apply Coupon Code"])
        UC7(["UC-07: Checkout & Place Order"])
        UC8(["UC-08: Process Digital Payment"])
        UC9(["UC-09: Live Track Order & Delivery ETA"])
        UC10(["UC-10: Rate Order & Rider"])
        UC11(["UC-11: Dispute & Chatbot Support"])

        %% Use Cases - Kitchen Staff
        UC12(["UC-12: View WSJF Kitchen Queue"])
        UC13(["UC-13: Advance Cooking Milestones"])

        %% Use Cases - Delivery Partner
        UC14(["UC-14: Toggle Online/Offline Duty"])
        UC15(["UC-15: Accept Delivery Assignment"])
        UC16(["UC-16: Navigate Turn-by-Turn GPS"])
        UC17(["UC-17: Verify Customer OTP & Finish"])

        %% Use Cases - Admin
        UC18(["UC-18: Manage Categories & Dishes"])
        UC19(["UC-19: Manage Stock & Low-Stock Alerts"])
        UC20(["UC-20: Manage User RBAC Permissions"])
        UC21(["UC-21: View BI Analytics & SES Forecast"])
        UC22(["UC-22: Audit System Logs & Trails"])
    end

    %% Customer Connections
    Customer --> UC1
    Customer --> UC2
    Customer --> UC3
    Customer --> UC5
    Customer --> UC7
    Customer --> UC9
    Customer --> UC10
    Customer --> UC11

    %% <<extend>> and <<include>>
    UC3 -.->|"<<extend>>"| UC4
    UC7 -.->|"<<include>>"| UC8
    UC7 -.->|"<<extend>>"| UC6
    UC8 --- PaymentSystem

    %% Kitchen Connections
    Kitchen --> UC12
    Kitchen --> UC13

    %% Delivery Rider Connections
    Rider --> UC14
    Rider --> UC15
    Rider --> UC16
    Rider --> UC17

    %% Admin Connections
    Admin --> UC18
    Admin --> UC19
    Admin --> UC20
    Admin --> UC21
    Admin --> UC22
```

---

### 2.2 Formal Use Case Specifications

#### Use Case 1: UC-07: Checkout & Place Order (with Concurrency Control)
* **Actor:** Registered Customer
* **Pre-conditions:** Customer is authenticated with an active session, has at least one valid item in the cart, and has selected a saved delivery address.
* **Post-conditions:** An immutable order header and line items are committed in MySQL, inventory stock is decremented atomically, payment record is persisted, and a unique 6-digit delivery OTP is generated.
* **Main Success Scenario:**
  1. Customer navigates to checkout, selects the delivery address, and chooses a payment method (UPI / Card / NetBanking / COD).
  2. Customer clicks **"Pay & Place Order"**.
  3. System initiates an atomic database transaction (`START TRANSACTION`).
  4. System acquires row-level pessimistic locks on all cart dishes (`SELECT stock_quantity FROM menu_items WHERE item_id = ? FOR UPDATE`).
  5. System validates that `stock_quantity >= cart_quantity` for every item.
  6. System calculates 5% GST (2.5% CGST + 2.5% SGST), packaging fees, and delivery fee.
  7. System invokes Payment Gateway (UC-08: Razorpay Sandbox Checkout SDK / Offline Test Simulator). Upon payment authorization token return (`pay_...`), system inserts record into `orders` and `order_items`.
  8. System decrements `stock_quantity` by ordered amounts and sets `is_available = FALSE` if stock drops to 0.
  9. System inserts payment details into `payments` table (storing Razorpay payment ID as `transaction_reference` with status `SUCCESSFUL`) and records an initial audit entry in `order_audit_trail`.
  10. System commits transaction (`COMMIT`) and displays animated confirmation with Order ID and Delivery OTP.
* **Alternative / Exception Flows:**
  * *Insufficient Stock:* If any item has fewer portions than requested, the system aborts the transaction (`ROLLBACK`), frees locks, and notifies customer: *"Sorry, [Dish Name] just sold out!"*
  * *Payment Failure:* If the transaction is declined or cancelled, the system logs payment status as `FAILED`, rolls back inventory changes, and allows customer to retry.

---

#### Use Case 2: UC-12: View WSJF Kitchen Queue & Fulfill Orders
* **Actor:** Kitchen Staff / Cook
* **Pre-conditions:** Kitchen staff is authenticated and viewing the Kitchen Display System (KDS) portal.
* **Post-conditions:** Order status advances in the database, order audit trail logs the staff member's ID, and delivery dispatch workflow is triggered when ready.
* **Main Success Scenario:**
  1. KDS interface fetches all active orders in `CONFIRMED` or `COOKING` states via SSE/WebSocket.
  2. For each order, the system computes the Weighted Shortest Job First (WSJF) score:
     $$\text{WSJF Priority} = \frac{\text{Elapsed Waiting Time (mins)} \times \text{Urgency Factor}}{\sum \text{Item Prep Times}}$$
  3. The system sorts tickets dynamically in descending order of WSJF score into Kanban columns: *New Tickets*, *In Kitchen*, and *Ready for Pickup*.
  4. Cook clicks **"Start Cooking"**. System updates `orders.order_status = 'COOKING'` and inserts an audit log entry.
  5. Cook completes preparation, checks off all line items, and clicks **"Order Ready"**.
  6. System plays audio chime, advances status to `'READY'`, and broadcasts a dispatch assignment event to the Delivery Partner pool.

---

#### Use Case 3: UC-17: Verify Customer OTP & Finish Delivery
* **Actor:** Delivery Partner
* **Pre-conditions:** Order status is `'OUT_FOR_DELIVERY'`, courier has reached the customer's delivery address, and customer holds the secret 6-digit OTP.
* **Post-conditions:** Order status transitions to `'DELIVERED'`, delivery timestamp is permanently logged, and rating prompt is pushed to customer.
* **Main Success Scenario:**
  1. Delivery partner arrives at customer location and requests the secret 6-digit verification OTP.
  2. Delivery partner enters OTP into the Delivery Mobile Web App.
  3. Backend verifies that the entered OTP matches `delivery_dispatches.delivery_otp`.
  4. System updates `orders.order_status = 'DELIVERED'` and sets `delivery_dispatches.delivered_at = CURRENT_TIMESTAMP`.
  5. System records audit trail entry and dispatches SMS notification to customer.
  6. Customer application refreshes to prompt for 5-star order and rider rating.
* **Alternative Flow:**
  * *Incorrect OTP:* System alerts courier of incorrect code and limits retry attempts to 3 to prevent brute-force attacks.

---

#### Use Case 4: UC-21: View BI Analytics & SES Demand Forecasting
* **Actor:** Restaurant Administrator
* **Pre-conditions:** Administrator has logged in with Role ID 4 (`ADMIN`).
* **Post-conditions:** System aggregates historical transactional data and renders predictive inventory demand metrics.
* **Main Success Scenario:**
  1. Admin opens the **Executive BI Reports** dashboard.
  2. System executes aggregate SQL queries on `orders` and `order_items` to generate hourly sales distribution, category revenue share, and average prep times.
  3. System executes Single Exponential Smoothing (SES) algorithm across past 30 days of item consumption:
     $$\hat{Y}_{t+1} = \alpha Y_t + (1 - \alpha) \hat{Y}_t \quad (\alpha = 0.3)$$
  4. Dashboard displays forecasted dish quantities for upcoming weekend service and flags items at risk of stockout.
  5. Admin reviews projections and places bulk inventory purchase orders.

---

## 3. ENTITY RELATIONSHIP DIAGRAM (ERD)

### 3.1 3NF Normalized Entity Relationship Diagram
The system schema consists of **10 normalized relational entities** designed to eliminate insertion, update, and deletion anomalies while enforcing strict referential integrity.

```mermaid
erDiagram
    ROLES ||--o{ USERS : "assigned to"
    USERS ||--o{ USER_ADDRESSES : "maintains"
    USERS ||--o{ ORDERS : "places"
    USERS ||--o{ ORDER_AUDIT_TRAIL : "triggers"
    CATEGORIES ||--o{ MENU_ITEMS : "contains"
    USER_ADDRESSES ||--o{ ORDERS : "delivered to"
    ORDERS ||--|{ ORDER_ITEMS : "consists of"
    MENU_ITEMS ||--o{ ORDER_ITEMS : "included in"
    ORDERS ||--|| PAYMENTS : "settled via"
    ORDERS ||--o| DELIVERY_DISPATCHES : "fulfilled by"
    ORDERS ||--o{ ORDER_AUDIT_TRAIL : "tracked in"

    ROLES {
        int role_id PK "AUTO_INCREMENT"
        varchar role_name UK "CUSTOMER, KITCHEN, RIDER, ADMIN"
        varchar description "Role permissions context"
    }

    USERS {
        int user_id PK "AUTO_INCREMENT"
        int role_id FK "References ROLES(role_id)"
        varchar full_name "User legal name"
        varchar email UK "RFC 5322 compliant unique email"
        varchar phone_number UK "E.164 phone format"
        varchar password_hash "Argon2id cryptographic hash"
        boolean is_active "Account status flag"
        timestamp created_at "Account creation timestamp"
    }

    USER_ADDRESSES {
        int address_id PK "AUTO_INCREMENT"
        int user_id FK "References USERS(user_id) ON DELETE CASCADE"
        varchar address_type "HOME, WORK, OTHER"
        text address_line "Street name, door no, building"
        varchar landmark "Nearby reference landmark"
        varchar pincode "6-digit Indian Postal Code"
        boolean is_default "Default delivery destination flag"
    }

    CATEGORIES {
        int category_id PK "AUTO_INCREMENT"
        varchar category_name UK "Biryani, Burgers, Desserts, etc."
        text category_description "Detailed category summary"
        varchar category_slug UK "URL-safe routing identifier"
        boolean is_active "Menu visibility toggle"
    }

    MENU_ITEMS {
        int item_id PK "AUTO_INCREMENT"
        int category_id FK "References CATEGORIES(category_id)"
        varchar item_name "Commercial title of dish"
        text description "Ingredients & culinary profile"
        decimal price "DECIMAL(10,2) CHECK (price > 0)"
        int stock_quantity "Live inventory count"
        boolean is_available "Calculated availability flag"
        boolean is_veg "Dietary vegetarian indicator"
        int prep_time_minutes "Expected kitchen prep duration"
        varchar image_url "Relative path to asset"
        timestamp created_at "Catalog listing timestamp"
    }

    ORDERS {
        int order_id PK "AUTO_INCREMENT"
        int user_id FK "References USERS(user_id)"
        int address_id FK "References USER_ADDRESSES(address_id)"
        decimal subtotal "DECIMAL(10,2) Sum of line items"
        decimal tax_amount "DECIMAL(10,2) 5% Total GST"
        decimal delivery_fee "DECIMAL(10,2) Flat or zoned fee"
        decimal discount_amount "DECIMAL(10,2) Applied coupon savings"
        decimal total_amount "DECIMAL(10,2) Final payable sum"
        enum order_status "PLACED, CONFIRMED, COOKING, READY, OUT_FOR_DELIVERY, DELIVERED, CANCELLED"
        text order_notes "Cooking or contactless instructions"
        timestamp placed_at "Order placement timestamp"
        timestamp estimated_delivery_time "Calculated dynamic ETA"
    }

    ORDER_ITEMS {
        int order_item_id PK "AUTO_INCREMENT"
        int order_id FK "References ORDERS(order_id) ON DELETE CASCADE"
        int item_id FK "References MENU_ITEMS(item_id)"
        int quantity "Portions ordered CHECK (quantity > 0)"
        decimal unit_price "DECIMAL(10,2) Price frozen at order time"
        decimal line_total "DECIMAL(10,2) quantity * unit_price"
    }

    PAYMENTS {
        int payment_id PK "AUTO_INCREMENT"
        int order_id FK "UNIQUE References ORDERS(order_id)"
        enum payment_mode "UPI, CREDIT_CARD, DEBIT_CARD, NET_BANKING, COD"
        varchar transaction_reference UK "Bank/Gateway Ref No"
        decimal amount_paid "DECIMAL(10,2) Verified transaction sum"
        enum payment_status "PENDING, SUCCESSFUL, FAILED, REFUNDED"
        timestamp payment_timestamp "Gateway callback timestamp"
    }

    DELIVERY_DISPATCHES {
        int dispatch_id PK "AUTO_INCREMENT"
        int order_id FK "UNIQUE References ORDERS(order_id)"
        varchar delivery_partner_name "Assigned courier full name"
        varchar delivery_partner_phone "Courier contact number"
        varchar delivery_otp "6-digit cryptographic verification OTP"
        timestamp assigned_at "Dispatch assignment timestamp"
        timestamp dispatched_at "Order picked up from kitchen"
        timestamp delivered_at "Customer confirmation timestamp"
        enum delivery_status "ASSIGNED, PICKED_UP, IN_TRANSIT, DELIVERED, FAILED"
    }

    ORDER_AUDIT_TRAIL {
        int audit_id PK "AUTO_INCREMENT"
        int order_id FK "References ORDERS(order_id)"
        varchar old_status "Previous lifecycle status"
        varchar new_status "Updated lifecycle status"
        int updated_by_user_id FK "References USERS(user_id)"
        timestamp change_timestamp "Immutable state change timestamp"
    }
```

---

### 3.2 Database Normalization Analysis (1NF to 3NF)

1. **First Normal Form (1NF) Compliance:**
   - Every column contains atomic (indivisible) values. No comma-delimited strings of ordered items exist in the `orders` table; items are segregated into individual rows in `order_items`.
   - Each table possesses a designated Primary Key (`user_id`, `order_id`, `item_id`, etc.).

2. **Second Normal Form (2NF) Compliance:**
   - The database is in 1NF and satisfies 2NF because all non-key attributes are fully functionally dependent on the entire primary key.
   - In junction table `order_items`, the composite natural key `(order_id, item_id)` maps to surrogate key `order_item_id`. The attribute `unit_price` is captured explicitly to preserve historic point-in-time pricing independent of future menu price fluctuations.

3. **Third Normal Form (3NF) Compliance:**
   - The database is in 2NF and exhibits no transitive dependencies ($X \to Y$ and $Y \to Z$ where $Z$ is non-prime).
   - Customer addresses are extracted out of `users` into `user_addresses`.
   - Category names and metadata are extracted out of `menu_items` into `categories`.
   - Payment and dispatch records are detached from the order header into `payments` and `delivery_dispatches` to prevent null-value anomalies during multi-stage order lifecycles.

---

### 3.3 Physical Implementation Mapping Matrix (Portals & Relational Tables)

| Subsystem / Portal | Physical Implementation Path | Primary Actors | Use Cases Implemented | Underlying 3NF Relational Tables |
| :--- | :--- | :--- | :--- | :--- |
| **Customer Storefront** | [`customer/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/index.php)<br>[`customer/checkout.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/checkout.php)<br>[`customer/order-status.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/customer/order-status.php) | Customer (Role 2) | UC-01 to UC-11 | `users`, `user_addresses`, `categories`, `menu_items`, `orders`, `order_items`, `payments`, `delivery_dispatches` |
| **Delivery Partner Fleet Portal (Delivery Boy)** | [`delivery/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/index.php)<br>[`delivery/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/delivery/login.php) | Courier Rider (Role 4: Suresh Kumar) | **UC-14** (Duty Toggle)<br>**UC-15** (Claim Dispatch)<br>**UC-16** (Turn-by-Turn GPS)<br>**UC-17** (Doorstep OTP Handshake) | `users`, `orders`, `order_items`, `delivery_dispatches`, `payments`, `order_audit_trail` |
| **Kitchen Display System (KDS)** | [`kitchen/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/index.php)<br>[`kitchen/login.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/login.php)<br>[`kitchen/kot.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/kitchen/kot.php)<br>*(Admin bridge: [`admin/kitchen.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/kitchen.php))* | Kitchen Chef (Role 3: Chef Sanjeev) | **UC-12** (WSJF Prioritized Queue)<br>**UC-13** (Cooking & Ready Milestones) | `orders`, `order_items`, `menu_items`, `order_audit_trail` |
| **Restaurant Admin Console** | [`admin/index.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/index.php)<br>[`admin/orders.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/orders.php)<br>[`admin/reports.php`](file:///e:/2026%20project/2026%20july/Pooja/pooja_project/admin/reports.php) | Administrator (Role 1: Pooja JP) | **UC-18** to **UC-22** (Menu CRUD, SES Demand Forecasting, RBAC) | `roles`, `users`, `categories`, `menu_items`, `orders`, `payments`, `order_audit_trail` |

---

## 4. FUTURE SCOPE & SYSTEM ENHANCEMENTS

To advance the platform from an academic major project into an enterprise-scale commercial ecosystem, the following eight technical enhancement avenues are established:

### 4.1 AI-Powered Conversational Voice Ordering (Multilingual NLP)
* **Architecture:** Integration of Whisper ASR (Automatic Speech Recognition) and fine-tuned open-source LLMs (Llama 3 / Mistral) via WebSocket streaming.
* **Impact:** Enables voice ordering in regional Indian languages (Hindi, Kannada, Malayalam, Tamil) for non-tech-savvy and visually impaired users.
* **Mechanism:** Converts audio streams into structured JSON cart intents (`{"action": "ADD_TO_CART", "item": "Chicken Biryani", "qty": 2, "spice_level": "medium"}`).

### 4.2 Autonomous Drone & Robotic Delivery Fleet Integration
* **Architecture:** Unmanned Aerial Vehicle (UAV) dispatch integration using MAVLink protocols and UTM (Unmanned Traffic Management) APIs.
* **Impact:** Drastically bypasses urban traffic bottlenecks in metropolitan zones, reducing last-mile delivery times from 40 minutes to under 12 minutes for perishable meals.
* **Mechanism:** Automatic calculation of payload weight limits against dish orders and geofenced safe landing waypoint verification.

### 4.3 Dynamic Surge Pricing & Predictive Kitchen Load Balancing
* **Architecture:** Reinforcement learning agent trained on historical weather radar APIs (OpenWeather), live traffic congestion (Google Distance Matrix), and pending kitchen queue depths.
* **Impact:** Prevents kitchen burnout during extreme rain or festival peaks by dynamically adjusting delivery fees and throttling order acceptance windows.

### 4.4 Blockchain-Enabled Farm-to-Fork Food Traceability
* **Architecture:** Hyperledger Fabric private permissioned blockchain recording supplier meat/produce batch certificates and cold-chain temperature telemetry.
* **Impact:** Provides tamper-proof verification of organic claims, Halal certification, and food freshness timestamps accessible to customers via on-box QR code scans.

### 4.5 Multi-Tenant Cloud Kitchen SaaS Architecture
* **Architecture:** Transition from single-restaurant deployment to a multi-tenant microservices model with horizontal pod autoscaling on Kubernetes (EKS/GKE).
* **Impact:** Allows the platform to onboard thousands of independent restaurant brands with isolated tenant databases, custom domains, and automated billing tiers.

### 4.6 IoT Connected Smart Kitchen Appliance Telemetry
* **Architecture:** MQTT broker integration with smart ovens, deep fryers, and digital weight scales.
* **Impact:** Automatically notifies the Kitchen Display System (KDS) when a fryer reaches target temperature or when bulk ingredient containers (rice, oil, flour) drop below reserve thresholds, automatically queuing inventory replenishment orders.

### 4.7 Multi-Drop Fleet Routing via Genetic Algorithms
* **Architecture:** Vehicle Routing Problem with Time Windows (VRPTW) solved using Genetic Algorithms and Ant Colony Optimization.
* **Impact:** Bundles orders from neighboring food outlets destined for adjacent residential apartments to a single rider, slashing fleet fuel consumption and carbon footprint by 35%.

### 4.8 Offline-First Progressive Web App (PWA) with Biometric Passkeys
* **Architecture:** WebAuthn standard with FIDO2 hardware passkeys (fingerprint / FaceID) paired with Service Workers and IndexedDB cache storage.
* **Impact:** Allows users in low-connectivity 2G/3G zones to browse menus and curate offline carts that automatically synchronize when network connectivity resumes.

---
*End of System Analysis & Design Specification (MCSP-232)*
