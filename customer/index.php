<?php
/**
 * Customer Storefront Home Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Order Food Online | Zippy Lush Forest Edition';
$activePage = 'home';

require_once __DIR__ . '/includes/header.php';

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
?>

<main>

  <!-- =========================================================================
       HERO CAROUSEL & BANNER
       ========================================================================= -->
  <section class="hero-carousel-section py-4">
    <div class="container-xl">
      <div id="heroCarousel" class="carousel slide carousel-fade rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel" data-bs-interval="4500">
        <div class="carousel-indicators">
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
        </div>
        
        <div class="carousel-inner">
          
          <!-- Slide 1: Lush Forest Special -->
          <div class="carousel-item active">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #1C2B20 0%, #2E6F40 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #CEFFDC; color: #243E2C;">
                    🌿 FIGMA LUSH FOREST EXCLUSIVE
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Craving Piping Hot Dum Biryani?</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Get <strong>20% OFF up to ₹100</strong> on authentic slow-cooked claypot biryanis. Use coupon <code class="text-mint fw-bold bg-dark px-2 py-1 rounded">LUSHFOREST</code>
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

          <!-- Slide 2: Free Delivery -->
          <div class="carousel-item">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #243E2C 0%, #1a4d2e 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #68BA7F; color: #fff;">
                    ⚡ LIGHTNING DISPATCH
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Free Express Delivery on Orders > ₹499</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Guaranteed 30-minute kitchen-to-doorstep delivery backed by our dynamic WSJF scheduling algorithm.
                  </p>
                  <a href="#dishes" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background:#CEFFDC; color:#243E2C;">
                    Explore Dishes
                  </a>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                  <img src="https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=500&q=80" alt="Curry Feast" class="img-fluid rounded-4 shadow-lg hero-img-glow" style="max-height: 220px; object-fit: cover;">
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 3: 50% Off Mega Deals -->
          <div class="carousel-item">
            <div class="hero-slide-bg p-4 p-md-5 d-flex align-items-center" style="background: linear-gradient(135deg, #2E6F40 0%, #14351d 100%); min-height: 280px;">
              <div class="row align-items-center w-100 g-4">
                <div class="col-lg-7 text-white">
                  <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-semibold">
                    🔥 50% SUPER SALE
                  </span>
                  <h1 class="display-6 fw-bold mb-2 text-white">Flat 50% Off with Code ZIPPY50</h1>
                  <p class="text-white-50 mb-3 fs-6">
                    Enjoy mouth-watering burgers, rolls, crispy dosas, and royal desserts at half price today!
                  </p>
                  <a href="<?= CUSTOMER_URL ?>/offers.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4 py-2">
                    Claim Deal
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
    </div>
  </section>

  <!-- =========================================================================
       INSPIRATION FOR YOUR FIRST ORDER (FOOD CATEGORIES)
       ========================================================================= -->
  <section class="categories-section py-4">
    <div class="container-xl">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h3 class="fw-bold mb-0">What's on your mind?</h3>
          <p class="text-muted small mb-0">Explore fresh meals prepared by certified top kitchen chefs</p>
        </div>
      </div>

      <!-- Category Pills Carousel / Grid -->
      <div class="category-scroll-container d-flex gap-3 overflow-x-auto pb-3">
        <a href="<?= CUSTOMER_URL ?>/index.php" class="category-pill-card text-center text-decoration-none flex-shrink-0 active">
          <div class="category-pill-img-wrap rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: #2E6F40; color: #fff;">
            <i class="fa-solid fa-utensils fs-3"></i>
          </div>
          <span class="category-name d-block fw-bold small text-dark">All Dishes</span>
        </a>

        <?php foreach ($categories as $cat): ?>
          <a href="<?= CUSTOMER_URL ?>/search.php?category=<?= urlencode($cat['category_slug']) ?>" class="category-pill-card text-center text-decoration-none flex-shrink-0">
            <div class="category-pill-img-wrap rounded-circle shadow-sm mx-auto mb-2 overflow-hidden" style="width: 80px; height: 80px;">
              <img src="<?= e($cat['image_url']) ?>" alt="<?= e($cat['category_name']) ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
            </div>
            <span class="category-name d-block fw-bold small text-dark text-truncate" style="max-width: 90px;"><?= e($cat['category_name']) ?></span>
            <span class="text-muted" style="font-size: 0.65rem;"><?= (int)$cat['total_dishes'] ?> items</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       TOP RESTAURANT CHAINS IN BANGALORE
       ========================================================================= -->
  <section class="restaurant-chains-section py-3 bg-white border-top border-bottom">
    <div class="container-xl">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0">Top Restaurant Chains in Bangalore</h4>
        <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="text-success fw-bold small text-decoration-none">View All Chains &rarr;</a>
      </div>

      <div class="row g-3">
        
        <!-- Restaurant 1 -->
        <div class="col-lg-3 col-md-6">
          <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none overflow-hidden restaurant-card hover-lift">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=500&q=80" class="card-img-top" alt="Meghana Foods" style="height: 160px; object-fit: cover;">
              <span class="badge bg-dark position-absolute bottom-0 start-0 m-2 px-2 py-1 small fw-bold">₹100 OFF ABOVE ₹299</span>
            </div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h6 class="fw-bold text-dark mb-0">Meghana Foods</h6>
                <span class="badge bg-success d-flex align-items-center gap-1"><i class="bi bi-star-fill text-warning"></i> 4.5</span>
              </div>
              <div class="text-muted small mb-2 text-truncate">Biryani, Andhra, North Indian, Seafood</div>
              <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span><i class="bi bi-clock me-1 text-success"></i> 25-30 mins</span>
                <span>Indiranagar</span>
              </div>
            </div>
          </a>
        </div>

        <!-- Restaurant 2 -->
        <div class="col-lg-3 col-md-6">
          <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none overflow-hidden restaurant-card hover-lift">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=500&q=80" class="card-img-top" alt="Empire Restaurant" style="height: 160px; object-fit: cover;">
              <span class="badge bg-dark position-absolute bottom-0 start-0 m-2 px-2 py-1 small fw-bold">50% OFF UP TO ₹100</span>
            </div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h6 class="fw-bold text-dark mb-0">Hotel Empire</h6>
                <span class="badge bg-success d-flex align-items-center gap-1"><i class="bi bi-star-fill text-warning"></i> 4.3</span>
              </div>
              <div class="text-muted small mb-2 text-truncate">North Indian, Mughlai, Kebabs, Rolls</div>
              <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span><i class="bi bi-clock me-1 text-success"></i> 30-35 mins</span>
                <span>Koramangala</span>
              </div>
            </div>
          </a>
        </div>

        <!-- Restaurant 3 -->
        <div class="col-lg-3 col-md-6">
          <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none overflow-hidden restaurant-card hover-lift">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=500&q=80" class="card-img-top" alt="Truffles Burgers" style="height: 160px; object-fit: cover;">
              <span class="badge bg-dark position-absolute bottom-0 start-0 m-2 px-2 py-1 small fw-bold">ITEMS AT ₹179</span>
            </div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h6 class="fw-bold text-dark mb-0">Truffles Cafe & Burgers</h6>
                <span class="badge bg-success d-flex align-items-center gap-1"><i class="bi bi-star-fill text-warning"></i> 4.6</span>
              </div>
              <div class="text-muted small mb-2 text-truncate">Burgers, Pastas, Desserts, Shakes</div>
              <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span><i class="bi bi-clock me-1 text-success"></i> 20-25 mins</span>
                <span>Indiranagar</span>
              </div>
            </div>
          </a>
        </div>

        <!-- Restaurant 4 -->
        <div class="col-lg-3 col-md-6">
          <a href="<?= CUSTOMER_URL ?>/restaurant.php" class="card h-100 border-0 shadow-sm rounded-4 text-decoration-none overflow-hidden restaurant-card hover-lift">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=500&q=80" class="card-img-top" alt="A2B Veg" style="height: 160px; object-fit: cover;">
              <span class="badge bg-success position-absolute bottom-0 start-0 m-2 px-2 py-1 small fw-bold">PURE VEG</span>
            </div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h6 class="fw-bold text-dark mb-0">Adyar Ananda Bhavan (A2B)</h6>
                <span class="badge bg-success d-flex align-items-center gap-1"><i class="bi bi-star-fill text-warning"></i> 4.4</span>
              </div>
              <div class="text-muted small mb-2 text-truncate">South Indian, Crispy Dosas, Sweets</div>
              <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span><i class="bi bi-clock me-1 text-success"></i> 18-22 mins</span>
                <span>HSR Layout</span>
              </div>
            </div>
          </a>
        </div>

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
          <h3 class="fw-bold mb-1 font-outfit">Recommended Dishes with Fast Delivery</h3>
          <p class="text-muted small mb-0">Live stock synchronized with our MySQL 8.0 ACID transaction engine</p>
        </div>

        <!-- Dietary & Filter Toggles -->
        <div class="d-flex flex-wrap align-items-center gap-2">
          
          <!-- Veg Only Toggle Button -->
          <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 d-flex align-items-center gap-2" id="vegOnlyToggle">
            <span class="food-badge-dot veg" style="width:10px; height:10px; border-radius:50%; background:#229947; display:inline-block;"></span>
            <span>Pure Veg Only</span>
          </button>

          <!-- Sort / Fast Filter -->
          <a href="<?= CUSTOMER_URL ?>/search.php?sort=price_asc" class="btn btn-light btn-sm rounded-pill px-3 py-1 border text-muted">
            <i class="bi bi-sort-numeric-down me-1"></i> Price: Low to High
          </a>

          <a href="<?= CUSTOMER_URL ?>/search.php" class="btn btn-light btn-sm rounded-pill px-3 py-1 border text-muted">
            <i class="bi bi-sliders me-1"></i> More Filters
          </a>
        </div>
      </div>

      <!-- Dish Cards Grid -->
      <div class="row g-4" id="dishesGrid">
        <?php if (!empty($menuItems)): ?>
          <?php foreach ($menuItems as $dish): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 dish-card-wrapper" data-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" data-category="<?= e($dish['category_slug']) ?>">
              <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden dish-catalog-card hover-lift">
                
                <!-- Dish Image Box -->
                <div class="dish-card-img-container position-relative overflow-hidden" style="height: 190px;">
                  <img src="<?= e($dish['image_url']) ?>" alt="<?= e($dish['item_name']) ?>" class="w-100 h-100 object-fit-cover dish-thumbnail" loading="lazy">
                  
                  <!-- Veg / Non-Veg Indicator -->
                  <div class="position-absolute top-0 start-0 m-2">
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
                    <div class="dish-qty-control-slot" data-dish-id="<?= (int)$dish['item_id'] ?>" data-dish-name="<?= addslashes(e($dish['item_name'])) ?>" data-dish-price="<?= (float)$dish['price'] ?>" data-dish-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>">
                      <button 
                        type="button" 
                        class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold add-to-cart-btn"
                        style="border-color: #2E6F40; color: #2E6F40;"
                        onclick="addToCart(<?= (int)$dish['item_id'] ?>, '<?= addslashes(e($dish['item_name'])) ?>', <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods')"
                      >
                        ADD <i class="bi bi-plus"></i>
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
       APP DOWNLOAD & LOYALTY CTA
       ========================================================================= -->
  <section class="app-cta-section py-5">
    <div class="container-xl">
      <div class="rounded-4 p-4 p-md-5 shadow-sm" style="background: linear-gradient(135deg, #243E2C 0%, #2E6F40 100%); color: #fff;">
        <div class="row align-items-center g-4">
          <div class="col-lg-8">
            <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #CEFFDC; color: #243E2C;">
              ⚡ ZIPPY INSTANT DISPATCH
            </span>
            <h2 class="fw-bold mb-2 text-white">Experience Zero-Lag Food Ordering</h2>
            <p class="text-white-50 mb-4" style="max-width: 600px;">
              Engineered with 3NF Normalized relational storage, row-level pessimistic locking, and dynamic WSJF scheduling to ensure every meal arrives fresh and on time.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="<?= CUSTOMER_URL ?>/cart.php" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background: #CEFFDC; color: #243E2C;">
                <i class="bi bi-bag-check-fill me-1"></i> Go to Your Cart
              </a>
              <a href="<?= ADMIN_URL ?>/index.php" class="btn btn-outline-light rounded-pill px-4 py-2">
                <i class="bi bi-speedometer2 me-1"></i> Open Store Admin Portal
              </a>
            </div>
          </div>
          <div class="col-lg-4 text-center d-none d-lg-block">
            <div class="p-3 rounded-4" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
              <i class="bi bi-shield-check text-mint fs-1 mb-2 d-block" style="color: #CEFFDC;"></i>
              <div class="fw-bold text-white fs-5">FSSAI Certified</div>
              <div class="small text-white-50">100% Hygienic & Contactless Delivery Handshake</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
