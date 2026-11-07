# 🧠 Module: Computational Algorithms (computational_algorithms.md)

> **Context Domain:** Data Science, Scheduling, Forecasting & Queue Operations  
> **Relevant Classes:** `AprioriEngine`, `WSJFQueue`, `SESDemandForecast`, `FCFSQueue`  
> **Location:** `includes/`

---

## 1. Algorithm Catalog & Mathematical Formulations

### 1.1 Market Basket Cross-Selling (`includes/AprioriEngine.php`)
- **Purpose:** Identifies item pairings that frequently appear together in past completed orders and surfaces contextual add-ons in the cart.
- **Formulas:**
  $$\text{Support}(A \cup B) = \frac{\text{Orders containing both } A \text{ and } B}{\text{Total Orders}}$$
  $$\text{Confidence}(A \to B) = \frac{\text{Support}(A \cup B)}{\text{Support}(A)}$$
  $$\text{Lift}(A \to B) = \frac{\text{Confidence}(A \to B)}{\text{Support}(B)}$$
- **Thresholds:** Minimum support $\ge 0.05$, minimum confidence $\ge 0.20$.

---

### 1.2 Weighted Shortest Job First Queue (`includes/WSJFQueue.php`)
- **Purpose:** Dynamically sorts the kitchen line orders so high-urgency, short-prep orders are completed quickly without starving large orders.
- **Formula:**
  $$\text{WSJF} = \frac{\text{Cost of Delay}}{\text{Duration}}$$
- **Parameters:**
  - Cost of Delay (CoD) = $\text{Total Value} \times 0.4 + \text{Wait Time (mins)} \times 0.6$
  - Duration = Maximum `prep_time_minutes` among items in the order.

---

### 1.3 Single Exponential Smoothing Demand Forecast (`includes/SESDemandForecast.php`)
- **Purpose:** Predicts future daily order volumes and ingredient demand based on historical moving averages.
- **Formula:**
  $$\hat{Y}_{t+1} = \alpha Y_t + (1 - \alpha) \hat{Y}_t$$
- **Smoothing Constant:** $\alpha \in [0.2, 0.4]$ (default: $0.3$).

---

### 1.4 First-Come-First-Served Dispatch Queue (`includes/FCFSQueue.php`)
- **Purpose:** Allocates ready orders to available delivery riders fairly while strictly enforcing concurrency limits (maximum 1 active delivery per rider).

---

## 2. Invariants & Usage

1. **Decoupling:** Algorithm classes must remain independent of direct HTML rendering; they consume raw arrays or PDO instances and return structured associative arrays.
2. **Defensive Defaults:** If historical order data is insufficient (e.g. fewer than 5 orders for Apriori or SES), the engine must gracefully fallback to top-rated catalog items without raising exceptions.
