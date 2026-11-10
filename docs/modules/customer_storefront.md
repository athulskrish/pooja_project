# Module: Customer Storefront & Discovery Engine

## 1. Overview
The customer storefront powers browse-and-order workflows with live category filtering, dynamic food mood matching, dietary toggles (Vegetarian & Healthy/Fit), per-dish customization add-ons, Apriori cross-sell bundles, and FAQ/support self-service.

## 2. Database Entities & Soft-Delete Filtering
- `categories` (`is_active = 1`)
- `menu_items` (`is_active = 1`, `is_available`, `is_veg`, `is_healthy`, `mood`, `stock_quantity`)
- `dish_customization_groups` & `dish_customization_options` (`is_active = 1`, `is_available = 1`, unit surcharge in `price`)
- `food_moods` (`is_active = 1`, `mood_key`, theme tokens & `display_order`)
- `dish_reviews` (multi-dimension scores: `rating`, `appearance_rating`, `taste_rating`, `portion_rating`, `value_rating`)
- `faqs` (`is_active = 1`) & `support_tickets` (`ticket_status`)

## 3. Core Services, Helpers & Views
- **`includes/Services/CatalogService.php`**: `getHeaderCatalogState()`, `getStorefrontHomeData()`, `getRestaurantCatalog()`, `searchDishes()`.
- **`includes/Services/OrderService.php`**: `getCustomerUnreviewedDeliveredOrder()`, `getActiveFaqs()`, `submitSupportTicket()`.
- **`includes/functions.php`**: `getFoodMoods()`, `getDishesByMood()`, `getDishCustomizationGroups()`, `getDishReviews()`, `renderStarRatingHtml()`, `getCustomerPreviousMeal()`, `sanitizeFaqContent()`.
- **Views & Endpoints**: `customer/includes/header.php`, `customer/index.php`, `customer/restaurant.php`, `customer/search.php`, `customer/offers.php`, `customer/help.php`, `customer/profile.php`, `customer/ajax/get-dish-customizations.php`, `customer/ajax/apriori-api.php`, `api/dish-reviews.php`, `api/catalog-sync.php`.

## 4. Technical Rules
1. Storefront catalog queries filter by `is_active = 1` across categories, dishes, customization groups/options, moods, and FAQs.
2. Dish search in `CatalogService::searchDishes()` uses parameterized `LIKE` placeholders (`:q1`, `:q2`, `:q3`) alongside active category/mood/diet filters.
3. User-supplied text, JSON cart/modal attributes (`jsJson()` / `e()`), and rich FAQ HTML (`sanitizeFaqContent()`) are escaped/sanitized to prevent stored or reflected XSS.
4. `api/catalog-sync.php` exposes only public-safe active catalog fields to unauthenticated/customer requests while restricting admin inventory snapshots to authenticated `ADMIN`/`KITCHEN` sessions.
