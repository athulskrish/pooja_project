<?php
/**
 * Customer Storefront Home Page
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

$pageTitle = 'Order Food Online | Zippy Lush Forest Edition';
$activePage = 'home';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/AprioriEngine.php';

// Fetch Active Categories
$categories = db_fetch_all("
    SELECT c.*, COUNT(m.item_id) AS total_dishes 
    FROM `categories` c 
    LEFT JOIN `menu_items` m ON c.category_id = m.category_id AND m.is_available = 1
    WHERE c.is_active = 1 
    GROUP BY c.category_id 
    ORDER BY c.display_order ASC
");

// Fetch Menu Items
$menuItems = db_fetch_all("
    SELECT m.*, c.category_name, c.category_slug 
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE m.is_available = 1
    ORDER BY m.stock_quantity DESC, m.item_id ASC
");

// Algorithm 1: Apriori Association Rule Dynamic Recommendations
$aprioriCombos = AprioriEngine::getRecommendations([], 4);

// Active Order Tracker for Logged-In Customers
$activeOrder = null;
$statusBadge = null;
if ($currentUser && !empty($currentUser['id'])) {
    $activeOrder = db_fetch_one("
        SELECT o.*, d.delivery_partner_name, d.delivery_partner_phone
        FROM `orders` o
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.user_id = :uid 
          AND o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')
        ORDER BY o.order_id DESC
        LIMIT 1
    ", [':uid' => (int)$currentUser['id']]);

    if ($activeOrder) {
        $statusBadge = match($activeOrder['order_status']) {
            'PLACED' => ['badge' => 'bg-secondary', 'icon' => 'bi-hourglass-split', 'text' => 'Order Received', 'desc' => 'Waiting for kitchen team confirmation'],
            'CONFIRMED' => ['badge' => 'bg-info text-dark', 'icon' => 'bi-check2-circle', 'text' => 'Order Confirmed', 'desc' => 'Kitchen has queued your order for cooking'],
            'COOKING' => ['badge' => 'bg-warning text-dark', 'icon' => 'fa-solid fa-fire-burner', 'text' => 'Cooking on Fire', 'desc' => 'Chef is preparing your meal with fresh ingredients'],
            'READY' => ['badge' => 'bg-primary', 'icon' => 'bi-box-seam', 'text' => 'Ready for Pickup', 'desc' => 'Meal packed hot and waiting for delivery partner pickup'],
            'OUT_FOR_DELIVERY' => ['badge' => 'bg-success', 'icon' => 'bi-bicycle', 'text' => 'Out for Delivery', 'desc' => 'Delivery partner is en route to your doorstep'],
            default => ['badge' => 'bg-success', 'icon' => 'bi-bag-check', 'text' => 'In Progress', 'desc' => 'Your order is being handled']
        };
    }
}
?>

<main>

  <!-- =========================================================================
       ACTIVE ORDER LIVE TRACKER BANNER (Visible for customers with live order)
       ========================================================================= -->
  <?php if ($activeOrder && $statusBadge): ?>
    <section class="live-active-order-banner pt-3 pb-2" id="activeOrderBanner">
      <div class="container-xl">
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-3 p-md-4 mb-0 text-dark" style="background: linear-gradient(135deg, #EDFBF2 0%, #D8F7E3 100%); border-left: 6px solid #2E6F40 !important;">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm" style="width: 52px; height: 52px; background: #2E6F40;">
                <?php if (str_starts_with($statusBadge['icon'], 'fa-')): ?>
                  <i class="<?= $statusBadge['icon'] ?> fs-4"></i>
                <?php else: ?>
                  <i class="bi <?= $statusBadge['icon'] ?> fs-4"></i>
                <?php endif; ?>
              </div>
              <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                  <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #243E2C; color: #CEFFDC; font-size: 0.7rem;">
                    <span class="spinner-grow spinner-grow-sm text-success me-1" style="width: 0.55rem; height: 0.55rem;" role="status"></span>
                    LIVE ORDER IN PROGRESS
                  </span>
                  <span class="badge <?= $statusBadge['badge'] ?> rounded-pill fw-semibold">
                    <?= $statusBadge['text'] ?>
                  </span>
                  <span class="text-muted small">Order #ZIP-<?= (int)$activeOrder['order_id'] ?></span>
                </div>
                <h6 class="fw-bold mb-0 text-dark font-outfit">
                  <?= $statusBadge['desc'] ?>
                  <?php if (!empty($activeOrder['delivery_partner_name'])): ?>
                    <span class="text-muted fw-normal small">&bull; Courier: <?= e($activeOrder['delivery_partner_name']) ?></span>
                  <?php endif; ?>
                </h6>
                <div class="small text-muted mt-1">
                  Amount: <strong><?= format_inr($activeOrder['total_amount']) ?></strong> &bull; Placed at <?= date('h:i A', strtotime($activeOrder['placed_at'])) ?>
                </div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$activeOrder['order_id'] ?>" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2" style="background:#2E6F40; border-color:#2E6F40;">
                <span>Track Live Order</span>
                <i class="bi bi-arrow-right"></i>
              </a>
              <button type="button" class="btn btn-sm btn-link text-muted p-2" onclick="document.getElementById('activeOrderBanner').remove()" title="Dismiss banner">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>

          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- =========================================================================
       HERO CAROUSEL, BANNERS & QUICK SEARCH
       ========================================================================= -->
  <section class="hero-carousel-section py-4">
    <div class="container-xl">
      <div id="heroCarousel" class="carousel slide carousel-fade rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel" data-bs-interval="6500" data-bs-pause="hover">
        <div class="carousel-indicators">
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3"></button>
        </div>
        
        <div class="carousel-inner">
          
          <!-- Slide 1: Lush Forest Special Biryani -->
          <div class="carousel-item active">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #1C2B20 0%, #2E6F40 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #CEFFDC; color: #243E2C;">
                    🌿 FIGMA LUSH FOREST EXCLUSIVE
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Craving Piping Hot Dum Biryani?</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Get <strong>20% OFF up to ₹100</strong> on authentic slow-cooked claypot biryanis. Click code to copy: 
                    <code class="text-mint fw-bold bg-dark px-2 py-1 rounded copy-coupon-badge" style="cursor: pointer;" onclick="copyCouponCode('LUSHFOREST', this)" title="Click to copy coupon code">
                      LUSHFOREST <i class="bi bi-copy small ms-1"></i>
                    </code>
                  </p>
                  <div class="d-flex flex-wrap gap-2">
                    <a href="#dishes" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background:#CEFFDC; color:#243E2C;">
                      Order Biryani Now <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <a href="<?= CUSTOMER_URL ?>/offers.php" class="btn btn-outline-light rounded-pill px-4 py-2">
                      View All Offers
                    </a>
                  </div>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                  <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=500&q=80" alt="Biryani Feast" class="img-fluid rounded-4 shadow-lg hero-img-glow" style="max-height: 220px; object-fit: cover;">
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 2: Lush Green Organic Healthy Gourmet -->
          <div class="carousel-item">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #18331E 0%, #2E6F40 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #CEFFDC; color: #1C2B20;">
                    🌿 100% ORGANIC & NUTRITIOUS
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Farm Fresh Salads & Superfood Bowls</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Curated micronutrient-dense Mediterranean quinoa bowls, lean grilled herb chicken, and cold-pressed detox juices.
                  </p>
                  <div class="d-flex flex-wrap gap-2">
                    <a href="<?= CUSTOMER_URL ?>/search.php?category=healthy" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background:#CEFFDC; color:#243E2C;">
                      Explore Healthy Menu <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <button type="button" class="btn btn-outline-light rounded-pill px-4 py-2" onclick="filterHealthyQuick(event)">
                      <i class="fa-solid fa-leaf me-1 text-mint" style="color:#CEFFDC;"></i> Filter Healthy Dishes
                    </button>
                  </div>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                  <img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=500&q=80" alt="Fresh Healthy Salad" class="img-fluid rounded-4 shadow-lg hero-img-glow" style="max-height: 220px; object-fit: cover;">
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 3: Free Express Delivery -->
          <div class="carousel-item">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #243E2C 0%, #1a4d2e 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #68BA7F; color: #fff;">
                    ⚡ LIGHTNING DISPATCH
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Free Express Delivery on Orders > ₹499</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Guaranteed 30-minute kitchen-to-doorstep delivery backed by real-time GPS telemetry and insulated thermal food warmers.
                  </p>
                  <a href="#dishes" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background:#CEFFDC; color:#243E2C;">
                    Explore Dishes <i class="bi bi-arrow-right ms-1"></i>
                  </a>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                  <img src="https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=500&q=80" alt="Curry Feast" class="img-fluid rounded-4 shadow-lg hero-img-glow" style="max-height: 220px; object-fit: cover;">
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 4: 50% Super Sale -->
          <div class="carousel-item">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #2E6F40 0%, #14351d 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-semibold">
                    🔥 50% SUPER SALE
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Flat 50% Off Today</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Enjoy mouth-watering burgers, rolls, crispy dosas, and royal desserts at half price. Click code to copy: 
                    <code class="text-dark fw-bold bg-warning px-2 py-1 rounded copy-coupon-badge" style="cursor: pointer;" onclick="copyCouponCode('ZIPPY50', this)" title="Click to copy coupon code">
                      ZIPPY50 <i class="bi bi-copy small ms-1"></i>
                    </code>
                  </p>
                  <a href="<?= CUSTOMER_URL ?>/offers.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4 py-2">
                    Claim Deal Now <i class="bi bi-arrow-right ms-1"></i>
                  </a>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                  <img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=500&q=80" alt="Burger Feast" class="img-fluid rounded-4 shadow-lg hero-img-glow" style="max-height: 220px; object-fit: cover;">
                </div>
              </div>
            </div>
          </div>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
        </button>
      </div>

      <!-- Hero Quick Search Bar & Popular Tags -->
      <div class="hero-quick-search-wrap mt-3">
        <div class="card border-0 shadow-sm rounded-pill p-2 bg-white" style="border: 1px solid #E2ECE5 !important;">
          <div class="row g-2 align-items-center px-2">
            <div class="col-auto text-success fs-5 ps-2">
              <i class="bi bi-search"></i>
            </div>
            <div class="col">
              <input type="text" id="storefrontQuickSearch" class="form-control border-0 shadow-none ps-1 text-dark" placeholder="Search dishes, ingredients (e.g. 'Biryani', 'Salad', 'Paneer', 'Burger', 'Dessert')..." aria-label="Search storefront dishes">
            </div>
            <div class="col-auto d-flex align-items-center gap-1">
              <button type="button" class="btn btn-sm btn-light rounded-pill px-2 text-muted" id="clearQuickSearchBtn" style="display:none;" onclick="clearQuickSearch()" title="Clear search">
                <i class="bi bi-x-circle-fill"></i>
              </button>
              <a href="#dishes" class="btn btn-success rounded-pill px-3 py-2 fw-semibold d-none d-sm-inline-flex align-items-center gap-1" style="background:#2E6F40; border-color:#2E6F40;">
                <span>View Menu</span> <i class="bi bi-arrow-down-short fs-5"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Popular Tags Pills -->
        <div class="d-flex align-items-center gap-2 mt-2 px-3 flex-wrap small">
          <span class="text-muted fw-semibold">Trending:</span>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Biryani')">Dum Biryani</button>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Salad')">Healthy Salad</button>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Paneer')">Paneer Curries</button>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Burger')">Burgers</button>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Lassi')">Shakes & Drinks</button>
          <button type="button" class="badge rounded-pill bg-white text-dark border px-2 py-1 quick-tag-btn" onclick="setQuickSearchTag('Gulab Jamun')">Desserts</button>
        </div>
      </div>

    </div>
  </section>

  <!-- =========================================================================
       INSPIRATION FOR YOUR FIRST ORDER (FOOD CATEGORIES)
       ========================================================================= -->
  <section class="categories-section py-4" id="categoriesSection">
    <div class="container-xl">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h3 class="fw-bold mb-0 font-outfit">What's on your mind?</h3>
          <p class="text-muted small mb-0">Tap any category to filter chef-curated dishes instantly</p>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" onclick="resetAllStorefrontFilters()">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Category
          </button>
        </div>
      </div>

      <!-- Category Pills Carousel / Grid -->
      <div class="category-scroll-container d-flex gap-3 overflow-x-auto pb-3">
        <a href="<?= CUSTOMER_URL ?>/index.php" 
           class="category-pill-card text-center text-decoration-none flex-shrink-0 active" 
           data-cat-slug="all" 
           onclick="filterByCategory('all', this, event)">
          <div class="category-pill-img-wrap rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: #2E6F40; color: #fff;">
            <i class="fa-solid fa-utensils fs-3"></i>
          </div>
          <span class="category-name d-block fw-bold small text-dark">All Dishes</span>
          <span class="text-muted" style="font-size: 0.65rem;"><?= count($menuItems) ?> items</span>
        </a>

        <?php foreach ($categories as $cat): ?>
          <a href="<?= CUSTOMER_URL ?>/search.php?category=<?= urlencode($cat['category_slug']) ?>" 
             class="category-pill-card text-center text-decoration-none flex-shrink-0" 
             data-cat-slug="<?= e($cat['category_slug']) ?>" 
             onclick="filterByCategory('<?= e($cat['category_slug']) ?>', this, event)">
            <div class="category-pill-img-wrap rounded-circle shadow-sm mx-auto mb-2 overflow-hidden" style="width: 80px; height: 80px;">
              <img src="<?= e(get_category_image_url($cat['image_url'])) ?>" alt="<?= e($cat['category_name']) ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
            </div>
            <span class="category-name d-block fw-bold small text-dark text-truncate" style="max-width: 90px;"><?= e($cat['category_name']) ?></span>
            <span class="text-muted" style="font-size: 0.65rem;"><?= (int)$cat['total_dishes'] ?> items</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       ALGORITHM 1: APRIORI MARKET BASKET ANALYSIS (FREQUENTLY ORDERED TOGETHER)
       ========================================================================= -->
  <section class="apriori-recommendations-section py-4 bg-white border-top border-bottom">
    <div class="container-xl">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #CEFFDC; color: #243E2C; font-size: 0.7rem;">
              <i class="fa-solid fa-wand-magic-sparkles me-1 text-success"></i> APRIORI ALGORITHM AI
            </span>
            <span class="badge bg-light text-muted border small">Market Basket Mining</span>
          </div>
          <h4 class="fw-bold mb-0 text-dark font-outfit">Frequently Ordered Together</h4>
          <p class="text-muted small mb-0">High-confidence culinary pairings & chef complements discovered by transaction history</p>
        </div>
        <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="text-success fw-bold small text-decoration-none d-flex align-items-center gap-1">
          <span>Explore Flagship Indiranagar Kitchen</span> &rarr;
        </a>
      </div>

      <div class="row g-3">
        <?php if (!empty($aprioriCombos)): ?>
          <?php foreach ($aprioriCombos as $rec): ?>
            <div class="col-lg-3 col-sm-6">
              <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden apriori-card hover-lift" style="border: 1px solid #E2ECE5 !important; background: #FAFCFA;">
                
                <div class="position-relative overflow-hidden" style="height: 145px;">
                  <img src="<?= e($rec['image_url']) ?>" alt="<?= e($rec['item_name']) ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
                  
                  <span class="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2 px-2 py-1 small text-white">
                    <i class="bi bi-clock me-1 text-mint"></i> <?= (int)$rec['prep_time_minutes'] ?>m
                  </span>
                  
                  <span class="badge rounded-pill position-absolute bottom-0 start-0 m-2 px-2 py-1" style="background: #2E6F40; color: #fff; font-size: 0.65rem;">
                    <i class="fa-solid fa-chart-line me-1 text-mint"></i> High Pairing Match
                  </span>
                </div>

                <div class="card-body p-3 d-flex flex-column justify-content-between">
                  <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                      <span class="badge <?= $rec['is_veg'] ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $rec['is_veg'] ? 'text-success' : 'text-danger' ?> border <?= $rec['is_veg'] ? 'border-success' : 'border-danger' ?> border-opacity-25" style="font-size: 0.65rem;">
                        <?= $rec['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                      </span>
                      <span class="small text-muted"><i class="bi bi-star-fill text-warning"></i> <?= number_format((float)($rec['rating'] ?? 4.8), 1) ?></span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= e($rec['item_name']) ?>"><?= e($rec['item_name']) ?></h6>
                    <p class="text-muted small mb-2 dish-desc-clamp" style="font-size: 0.75rem; min-height: 32px;"><?= e($rec['description']) ?></p>
                  </div>

                  <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span class="fw-bold text-forest fs-6"><?= format_inr($rec['price']) ?></span>
                    <div class="dish-qty-control-slot" data-dish-id="<?= (int)$rec['item_id'] ?>" data-dish-name="<?= addslashes(e($rec['item_name'])) ?>" data-dish-price="<?= (float)$rec['price'] ?>" data-dish-veg="<?= $rec['is_veg'] ? 'true' : 'false' ?>" data-dish-img="<?= e($rec['image_url']) ?>">
                      <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-bold" onclick="addToCart(<?= (int)$rec['item_id'] ?>, '<?= addslashes(e($rec['item_name'])) ?>', <?= (float)$rec['price'] ?>, <?= $rec['is_veg'] ? 1 : 0 ?>, 'Meghana Foods', '<?= e($rec['image_url']) ?>')">
                        ADD +
                      </button>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       DISH CATALOG & LIVE MENU WITH ADD TO CART
       ========================================================================= -->
  <section class="dishes-catalog-section py-5" id="dishes">
    <div class="container-xl">
      
      <!-- Section Title & Filter Pills -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h3 class="fw-bold mb-0 font-outfit" id="dishesHeadingText">Recommended Dishes with Fast Delivery</h3>
            <span id="activeCategoryBadge" class="badge rounded-pill bg-success px-3 py-1 fw-semibold" style="display:none; font-size: 0.8rem;"></span>
          </div>
          <p class="text-muted small mb-0">Freshly prepared chef-curated meals delivered hot and fast to your doorstep</p>
        </div>

        <!-- Dietary & Filter Toggles (Material Design 3 Filter Chips) -->
        <div class="d-flex align-items-center gap-2 flex-wrap py-1 filter-chips-scroll">
          
          <!-- Veg Only Toggle Button -->
          <button type="button" class="btn m3-filter-chip btn-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2 filter-toggle-btn" id="vegOnlyToggle">
            <span class="food-badge-dot veg" style="width:10px; height:10px; border-radius:50%; background:#229947; display:inline-block;"></span>
            <span>Pure Veg Only</span>
          </button>

          <!-- Healthy Food Option Toggle Button -->
          <button type="button" class="btn m3-filter-chip btn-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2 filter-toggle-btn" id="healthyOnlyToggle">
            <i class="fa-solid fa-leaf text-success"></i>
            <span>Healthy Food</span>
          </button>

          <!-- Fast Sort Dropdown -->
          <div class="dropdown">
            <button class="btn m3-filter-chip btn-sm rounded-pill px-3 py-1 dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="storefrontSortBtn">
              <i class="bi bi-arrow-down-up"></i>
              <span id="sortLabel">Sort: Recommended</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2">
              <li><button class="dropdown-item small py-2 d-flex align-items-center justify-content-between" type="button" onclick="sortDishes('recommended')">Recommended <i class="bi bi-check2 text-success sort-check" id="check-recommended"></i></button></li>
              <li><button class="dropdown-item small py-2 d-flex align-items-center justify-content-between" type="button" onclick="sortDishes('price_asc')">Price: Low to High <i class="bi bi-check2 text-success sort-check d-none" id="check-price_asc"></i></button></li>
              <li><button class="dropdown-item small py-2 d-flex align-items-center justify-content-between" type="button" onclick="sortDishes('price_desc')">Price: High to Low <i class="bi bi-check2 text-success sort-check d-none" id="check-price_desc"></i></button></li>
              <li><button class="dropdown-item small py-2 d-flex align-items-center justify-content-between" type="button" onclick="sortDishes('rating')">Highest Rated <i class="bi bi-check2 text-success sort-check d-none" id="check-rating"></i></button></li>
              <li><button class="dropdown-item small py-2 d-flex align-items-center justify-content-between" type="button" onclick="sortDishes('prep_time')">Fastest Prep Time <i class="bi bi-check2 text-success sort-check d-none" id="check-prep_time"></i></button></li>
            </ul>
          </div>

          <!-- Clear Filters Button -->
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1" id="resetStorefrontFiltersBtn" style="display:none;" onclick="resetAllStorefrontFilters()">
            <i class="bi bi-x-circle me-1"></i>Clear Filters
          </button>

          <a href="<?= CUSTOMER_URL ?>/search.php" class="btn m3-filter-chip btn-sm rounded-pill px-3 py-1" title="Advanced Search">
            <i class="bi bi-sliders me-1"></i> More Filters
          </a>
        </div>
      </div>

      <!-- Dish Cards Grid -->
      <div class="row g-3 g-md-4" id="dishesGrid">
        <?php if (!empty($menuItems)): ?>
          <?php foreach ($menuItems as $dish): ?>
            <?php 
              $isHealthyDish = (!empty($dish['is_healthy']) || ($dish['category_slug'] ?? '') === 'healthy' || stripos($dish['category_name'] ?? '', 'healthy') !== false || stripos($dish['item_name'] ?? '', 'salad') !== false || stripos($dish['item_name'] ?? '', 'quinoa') !== false);
            ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 dish-card-wrapper" 
                 data-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" 
                 data-category="<?= e($dish['category_slug']) ?>" 
                 data-healthy="<?= $isHealthyDish ? 'true' : 'false' ?>" 
                 data-rating="<?= (float)($dish['rating'] ?? 4.8) ?>"
                 data-price="<?= (float)$dish['price'] ?>"
                 data-prep="<?= (int)$dish['prep_time_minutes'] ?>"
                 data-name="<?= strtolower(e($dish['item_name'])) ?>">
              
              <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden dish-catalog-card hover-lift m3-card">
                
                <!-- Dish Image Box -->
                <div class="dish-card-img-container position-relative overflow-hidden" style="height: 190px;">
                  <img src="<?= e(get_dish_image_url($dish['image_url'])) ?>" alt="<?= e($dish['item_name']) ?>" class="w-100 h-100 object-fit-cover dish-thumbnail" loading="lazy">
                  
                  <!-- Indicators (Veg/Non-Veg + Healthy) -->
                  <div class="position-absolute top-0 start-0 m-2 d-flex flex-column gap-1 align-items-start">
                    <?php if ($dish['is_veg']): ?>
                      <span class="badge bg-white text-success border border-success d-flex align-items-center gap-1 shadow-sm px-2 py-1" style="font-size: 0.7rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #229947; display: inline-block;"></span>
                        VEG
                      </span>
                    <?php else: ?>
                      <span class="badge bg-white text-danger border border-danger d-flex align-items-center gap-1 shadow-sm px-2 py-1" style="font-size: 0.7rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #D32F2F; display: inline-block;"></span>
                        NON-VEG
                      </span>
                    <?php endif; ?>

                    <?php if ($isHealthyDish): ?>
                      <span class="badge shadow-sm px-2 py-1" style="background: #CEFFDC; color: #1C2B20; font-size: 0.65rem; border: 1px solid #68BA7F;">
                        <i class="fa-solid fa-leaf me-1 text-success"></i> HEALTHY
                      </span>
                    <?php endif; ?>
                  </div>

                  <!-- Prep Time Badge -->
                  <div class="position-absolute top-0 end-0 m-2">
                    <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1" style="font-size: 0.7rem;">
                      <i class="bi bi-clock me-1 text-mint"></i> <?= (int)$dish['prep_time_minutes'] ?>m
                    </span>
                  </div>

                  <!-- Live Stock Pill -->
                  <?php if ((int)$dish['stock_quantity'] < 15): ?>
                    <div class="position-absolute bottom-0 start-0 m-2">
                      <span class="badge bg-danger shadow-sm px-2 py-1" style="font-size: 0.65rem;">
                        Only <?= (int)$dish['stock_quantity'] ?> left in stock
                      </span>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Dish Card Content -->
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                  <div>
                    <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                      <?= e($dish['category_name']) ?>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark dish-title" title="<?= e($dish['item_name']) ?>">
                      <?= e($dish['item_name']) ?>
                    </h6>

                    <!-- Star Rating & Customer Reviews Link -->
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <span class="dish-rating-badge" title="<?= number_format((float)(!empty($dish['rating']) ? $dish['rating'] : 4.8), 1) ?> out of 5 stars">
                        <i class="bi bi-star-fill rating-star"></i>
                        <span class="rating-val"><?= number_format((float)(!empty($dish['rating']) ? $dish['rating'] : 4.8), 1) ?></span>
                      </span>
                      <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted small open-dish-reviews-btn" 
                              style="font-size: 0.75rem;" 
                              onclick="openDishReviewsModal(<?= (int)$dish['item_id'] ?>, '<?= addslashes(e($dish['item_name'])) ?>', <?= (float)(!empty($dish['rating']) ? $dish['rating'] : 4.8) ?>, <?= (int)($dish['total_reviews'] ?? 120) ?>)">
                        (<?= (int)($dish['total_reviews'] ?? 120) ?> reviews)
                      </button>
                    </div>

                    <p class="text-muted small mb-3 dish-desc-clamp" style="min-height: 38px;">
                      <?= e($dish['description']) ?>
                    </p>
                  </div>

                  <!-- Pricing & Add to Cart Controls -->
                  <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <div>
                      <span class="fs-5 fw-bold text-forest"><?= format_inr($dish['price']) ?></span>
                    </div>

                    <!-- Client JS Synchronized ADD Button -->
                    <div class="dish-qty-control-slot" data-dish-id="<?= (int)$dish['item_id'] ?>" data-dish-name="<?= addslashes(e($dish['item_name'])) ?>" data-dish-price="<?= (float)$dish['price'] ?>" data-dish-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" data-dish-img="<?= e(get_dish_image_url($dish['image_url'])) ?>">
                      <button 
                        type="button" 
                        class="btn m3-add-btn rounded-pill px-3 py-1 fw-bold shadow-sm"
                        onclick="addToCart(<?= (int)$dish['item_id'] ?>, '<?= addslashes(e($dish['item_name'])) ?>', <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods', '<?= e(get_dish_image_url($dish['image_url'])) ?>')"
                      >
                        ADD <i class="bi bi-plus-lg ms-1"></i>
                      </button>
                    </div>
                  </div>

                </div>

              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12 text-center py-5">
            <i class="bi bi-egg-fried fs-1 text-muted mb-3 d-block"></i>
            <h5>No dishes available right now</h5>
            <p class="text-muted small">Please check back soon or initialize the database seeder.</p>
            <a href="<?= BASE_URL ?>/database/seed.php" class="btn btn-success btn-sm rounded-pill px-4">Initialize Menu</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </section>

  <!-- =========================================================================
       APP DOWNLOAD, HYGIENE & TRUST CTA
       ========================================================================= -->
  <section class="app-cta-section py-5">
    <div class="container-xl">
      <div class="rounded-4 p-4 p-md-5 shadow-sm" style="background: linear-gradient(135deg, #243E2C 0%, #2E6F40 100%); color: #fff;">
        <div class="row align-items-center g-4">
          <div class="col-lg-8">
            <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #CEFFDC; color: #243E2C;">
              ⚡ ZIPPY INSTANT DISPATCH
            </span>
            <h2 class="fw-bold mb-2 text-white font-outfit">Fresh Meals, Zero-Lag Delivery</h2>
            <p class="text-white-50 mb-4" style="max-width: 600px;">
              Prepared fresh to order in our FSSAI-certified hygiene kitchen and dispatched instantly with insulated thermal pods to ensure every bite arrives steaming hot.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="<?= CUSTOMER_URL ?>/cart.php" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background: #CEFFDC; color: #243E2C;">
                <i class="bi bi-bag-check-fill me-1"></i> Go to Your Cart
              </a>
              <a href="<?= CUSTOMER_URL ?>/search.php" class="btn btn-outline-light rounded-pill px-4 py-2">
                <i class="bi bi-search me-1"></i> Explore Full Menu
              </a>
            </div>
          </div>
          <div class="col-lg-4 text-center d-none d-lg-block">
            <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.15);">
              <i class="bi bi-shield-check text-mint fs-1 mb-2 d-block" style="color: #CEFFDC;"></i>
              <div class="fw-bold text-white fs-5">100% FSSAI Certified Kitchen</div>
              <div class="small text-white-50">Hygienic Preparation & Contactless Handshake</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
