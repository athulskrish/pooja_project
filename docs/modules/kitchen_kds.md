# 🍳 Module: Kitchen Display System (kitchen_kds.md)

> **Context Domain:** Kitchen Display (KDS), Order Prioritization, KOT Tickets & Status Updates  
> **Relevant Tables:** `orders`, `order_items`, `menu_items`, `order_audit_trail`

---

## 1. Overview & Prioritization

The Kitchen Display System (`kitchen/`) provides line cooks with real-time incoming orders. Rather than simple First-In-First-Out, orders are dynamically prioritized using the **Weighted Shortest Job First (WSJF)** algorithm (`includes/WSJFQueue.php`).

### WSJF Formula:
$$\text{WSJF Score} = \frac{\text{Cost of Delay (CoD)}}{\text{Job Duration (Estimated Prep Time)}}$$
Where:
- $\text{Cost of Delay} = \text{Order Value} + (\text{Waiting Minutes} \times \text{Urgency Weight}) + \text{Customer Loyalty Bonus}$
- $\text{Job Duration} = \max(\text{prep\_time\_minutes of ordered items})$

---

## 2. Key Source Files

- **`kitchen/index.php`**: Primary kitchen dashboard displaying active prep tickets sorted by WSJF score.
- **`kitchen/kot.php`**: Kitchen Order Ticket printable view with item breakdown, customizations, and special cooking instructions.
- **`kitchen/ajax/poll-orders.php`**: Long-polling / AJAX heartbeat endpoint returning newly confirmed orders.
- **`kitchen/ajax/update-status.php`**: Transitions order to `PREPARING` or `READY_FOR_PICKUP`.
- **`includes/WSJFQueue.php`**: Object-oriented WSJF prioritization engine.

---

## 3. Engineering Invariants

1. **Station Segregation:** Display station labels (e.g., "Grill", "Beverages", "Main Kitchen") based on `menu_items.kitchen_station`.
2. **State Transition Assertion:** Kitchen staff can only advance orders with status `CONFIRMED` -> `PREPARING` -> `READY_FOR_PICKUP`.
3. **AJAX Security:** All AJAX status update endpoints must verify the `csrf_token` and `KITCHEN` role session.
