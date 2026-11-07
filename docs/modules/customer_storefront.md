# 🛒 Module: Customer Storefront & Catalog (customer_storefront.md)

> **Context Domain:** Food Discovery, Search, Categorization & Dish Customizations  
> **Relevant Tables:** `categories`, `menu_items`, `dish_customization_groups`, `dish_customization_options`, `dish_reviews`

---

## 1. Storefront Features

- **Dynamic Catalog:** Dishes displayed by category with Veg/Non-Veg filter, rating badges, prep times, and real-time stock indicators.
- **Food Moods Filter:** Mood-based quick filters (e.g., Comfort, Party, Focus, Late Night).
- **Dish Customization Engine:** Dishes with customization options launch an AJAX modal fetching group selections (e.g. Size, Crust, Add-ons) with min/max selection bounds.
- **Cross-Selling Recommendations:** Powered by the Apriori algorithm (`includes/AprioriEngine.php`), suggesting complementary dishes based on frequent itemset association rules.

---

## 2. Key Source Files

- **`customer/index.php`**: Storefront homepage, categories slider, popular items, food moods.
- **`customer/restaurant.php`**: Menu catalog, sticky cart drawer, veg/non-veg toggle, Apriori recommendations.
- **`customer/search.php`**: Live multi-field search (dish name, category, mood).
- **`customer/ajax/get-dish-customizations.php`**: Returns JSON options for customization modal.
- **`customer/ajax/apriori-api.php`**: Live cross-selling recommendations based on current cart items.

---

## 3. Engineering Invariants

1. **Stock Display:** If `stock_quantity <= 0`, disable the "Add to Cart" button and render an "Out of Stock" badge.
2. **Customization Pricing:** Calculate base price + customization option additional prices accurately before persisting to cart state.
3. **Escaping:** All user-supplied search queries must be sanitized and matched with PDO `LIKE :query` with appropriate escaping.
