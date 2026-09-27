<?php
/**
 * Zippy - Online Food Ordering Storefront (Customer Home)
 * MCSP-232: E-Commerce Platform for Food Order
 * Student: Pooja JP | University: IGNOU
 */
$pageTitle = 'Zippy - Online Food Delivery | Cloud Kitchen';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/controllers/MenuController.php';

$data = MenuController::getHomePageData();
$categories = $data['categories'];
$bestsellers = $data['bestsellers'];
?>

  <!-- =========================================================================
       HERO BANNER & PROMO CAROUSEL
       ========================================================================= -->
  <section class="hero-section py-4 py-md-5">
    <div class="container-xl">
      <div class="row align-items-center g-4">
        
        <div class="col-lg-7">
          <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-success bg-opacity-10 text-success small fw-bold mb-3 border border-success border-opacity-25">
            <i class="fa-solid fa-leaf"></i> Lush Edition • Cloud Kitchen Network
          </div>
          <h1 class="display-4 fw-extrabold text-pine mb-3">
            Craving Delicious Food? <br>
            <span class="text-success">Delivered in 25 Mins.</span>
          </h1>
          <p class="lead text-muted mb-4" style="max-width: 580px;">
            Order from top cloud kitchens and iconic restaurants like <strong>Meghana Foods</strong>. Freshly prepared, hygienically packed, and delivered blazing fast to your doorstep.
          </p>

          <!-- Search Input Box -->
          <form action="search.php" method="GET" class="hero-search-box mb-4">
            <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
              <span class="input-group-text bg-white border-0 ps-4 text-success">
                <i class="bi bi-search fs-5"></i>
              </span>
              <input type="text" name="q" class="form-control border-0 py-3" placeholder="Search for Biryani, Butter Chicken, Paneer Tikka, Desserts..." required>
              <button class="btn btn-success px-4 px-md-5 fw-bold" type="submit" style="background-color: var(--lush-forest);">
                Find Food
              </button>
            </div>
          </form>

          <!-- Quick Highlights -->
          <div class="d-flex flex-wrap gap-4 text-muted small">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-shield-check text-success fs-5"></i>
              <span>100% FSSAI Certified</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
              <span>Live WSJF Kitchen Queue</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-percent text-success fs-5"></i>
              <span>Flat 20% Off via LUSHFOREST</span>
            </div>
          </div>
        </div>

        <!-- Right Hero Visual / Featured Promo Card -->
        <div class="col-lg-5">
          <div class="hero-food-card position-relative rounded-4 overflow-hidden shadow-lg border">
            <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=700&q=80" class="w-100 object-fit-cover" style="height: 380px;" alt="Signature Biryani">
            <div class="position-absolute bottom-0 start-0 end-0 p-4 text-white" style="background: linear-gradient(to top, rgba(36, 62, 44, 0.95), transparent);">
              <span class="badge bg-warning text-dark fw-bold mb-2">MOST ORDERED</span>
              <h4 class="fw-bold mb-1">Meghana Special Chicken Biryani</h4>
              <p class="small text-white-50 mb-3">Authentic spicy Andhra recipe layered with rich marinated chicken.</p>
              <div class="d-flex align-items-center justify-content-between">
                <span class="fs-4 fw-bold">₹340.00</span>
                <a href="restaurant.php" class="btn btn-sm btn-light rounded-pill px-4 fw-bold text-pine shadow-sm">
                  Order Now <i class="bi bi-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- =========================================================================
       FOOD CATEGORIES INSPIRATION
       ========================================================================= -->
  <section class="py-5 bg-white border-top border-bottom">
    <div class="container-xl">
      <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
          <span class="text-success fw-bold small text-uppercase letter-spacing-1">What's on your mind?</span>
          <h2 class="fw-bold text-pine mb-0">Explore Culinary Categories</h2>
        </div>
        <a href="restaurant.php" class="text-success text-decoration-none fw-bold small">
          View Full Menu <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <div class="row g-3 g-md-4">
        <?php foreach ($categories as $cat): ?>
          <div class="col-6 col-md-4 col-lg-2">
            <a href="restaurant.php?category=<?= sanitize($cat['category_slug']) ?>" class="category-circle-card text-decoration-none d-block text-center p-3 rounded-4 border hover-lift">
              <div class="category-img-wrap mx-auto mb-2 rounded-circle overflow-hidden shadow-xs" style="width: 100px; height: 100px;">
                <img src="<?= sanitize($cat['image_url']) ?>" class="w-100 h-100 object-fit-cover" alt="<?= sanitize($cat['category_name']) ?>">
              </div>
              <h6 class="fw-bold text-pine mb-1 small"><?= sanitize($cat['category_name']) ?></h6>
              <?php if (!empty($cat['badge'])): ?>
                <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.65rem;"><?= sanitize($cat['badge']) ?></span>
              <?php endif; ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       TOP CLOUD KITCHENS & RESTAURANT OUTLETS
       ========================================================================= -->
  <section class="py-5" style="background-color: var(--lush-bg);">
    <div class="container-xl">
      <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
          <span class="text-success fw-bold small text-uppercase letter-spacing-1">Handpicked Brands</span>
          <h2 class="fw-bold text-pine mb-0">Top Cloud Kitchens in Indiranagar</h2>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success rounded-pill px-3 py-2">
            <i class="bi bi-geo-alt-fill me-1"></i> Delivery to 560038
          </span>
        </div>
      </div>

      <div class="row g-4">
        <!-- Card 1: Meghana Foods (Primary Featured Restaurant) -->
        <div class="col-md-6 col-lg-4">
          <div class="restaurant-card bg-white rounded-4 overflow-hidden border shadow-sm h-100 d-flex flex-column">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=500&q=80" class="w-100 object-fit-cover" style="height: 190px;" alt="Meghana Foods">
              <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 start-0 m-3 fw-bold">
                <i class="bi bi-clock me-1"></i> 20-25 MINS
              </span>
              <span class="badge bg-danger position-absolute top-0 end-0 m-3 fw-bold">
                50% OFF UPTO ₹100
              </span>
            </div>
            <div class="p-3 flex-grow-1 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h5 class="fw-bold text-pine mb-0">Meghana Foods</h5>
                  <span class="badge bg-success text-white fw-bold"><i class="bi bi-star-fill me-1"></i> 4.5</span>
                </div>
                <p class="small text-muted mb-2">Biryani, Andhra Special, North Indian, Seafood</p>
                <div class="small text-muted mb-3"><i class="bi bi-geo-alt text-success me-1"></i> Indiranagar • 2.4 km away</div>
              </div>
              <a href="restaurant.php" class="btn btn-outline-success w-100 rounded-pill fw-bold py-2">
                View Full Menu <i class="bi bi-chevron-right ms-1"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Card 2: Empire Restaurant -->
        <div class="col-md-6 col-lg-4">
          <div class="restaurant-card bg-white rounded-4 overflow-hidden border shadow-sm h-100 d-flex flex-column">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?auto=format&fit=crop&w=500&q=80" class="w-100 object-fit-cover" style="height: 190px;" alt="Empire Restaurant">
              <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 start-0 m-3 fw-bold">
                <i class="bi bi-clock me-1"></i> 30-35 MINS
              </span>
              <span class="badge bg-success position-absolute top-0 end-0 m-3 fw-bold">
                FLAT ₹125 OFF
              </span>
            </div>
            <div class="p-3 flex-grow-1 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h5 class="fw-bold text-pine mb-0">Empire Restaurant</h5>
                  <span class="badge bg-success text-white fw-bold"><i class="bi bi-star-fill me-1"></i> 4.3</span>
                </div>
                <p class="small text-muted mb-2">Kebabs, Shawarma, Ghee Rice, Mughlai</p>
                <div class="small text-muted mb-3"><i class="bi bi-geo-alt text-success me-1"></i> 80 Feet Road, Indiranagar • 1.8 km away</div>
              </div>
              <a href="restaurant.php" class="btn btn-outline-success w-100 rounded-pill fw-bold py-2">
                View Full Menu <i class="bi bi-chevron-right ms-1"></i>
              </a>
            </div>
          </div>
        </div>

        <!-- Card 3: Punjab Grill Cloud Kitchen -->
        <div class="col-md-6 col-lg-4">
          <div class="restaurant-card bg-white rounded-4 overflow-hidden border shadow-sm h-100 d-flex flex-column">
            <div class="position-relative">
              <img src="https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?auto=format&fit=crop&w=500&q=80" class="w-100 object-fit-cover" style="height: 190px;" alt="Punjab Grill">
              <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 start-0 m-3 fw-bold">
                <i class="bi bi-clock me-1"></i> 25-30 MINS
              </span>
              <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-3 fw-bold">
                FREE DELIVERY
              </span>
            </div>
            <div class="p-3 flex-grow-1 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h5 class="fw-bold text-pine mb-0">Punjab Grill Kitchen</h5>
                  <span class="badge bg-success text-white fw-bold"><i class="bi bi-star-fill me-1"></i> 4.6</span>
                </div>
                <p class="small text-muted mb-2">North Indian, Butter Chicken, Dal Makhani, Naan</p>
                <div class="small text-muted mb-3"><i class="bi bi-geo-alt text-success me-1"></i> HAL 2nd Stage • 2.9 km away</div>
              </div>
              <a href="restaurant.php" class="btn btn-outline-success w-100 rounded-pill fw-bold py-2">
                View Full Menu <i class="bi bi-chevron-right ms-1"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       RECOMMENDED BESTSELLERS (DIRECT ADD TO CART)
       ========================================================================= -->
  <section class="py-5 bg-white border-top">
    <div class="container-xl">
      <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
          <span class="text-success fw-bold small text-uppercase letter-spacing-1">Trending Right Now</span>
          <h2 class="fw-bold text-pine mb-0">Popular Chef Recommendations</h2>
        </div>
        <a href="restaurant.php" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
          Explore All Dishes
        </a>
      </div>

      <div class="row g-4">
        <?php foreach ($bestsellers as $dish): ?>
          <div class="col-md-6 col-lg-3">
            <div class="dish-card bg-white rounded-4 border p-3 shadow-xs h-100 d-flex flex-column justify-content-between hover-lift">
              <div class="position-relative mb-3 rounded-3 overflow-hidden" style="height: 160px;">
                <img src="<?= sanitize($dish['image_url']) ?>" class="w-100 h-100 object-fit-cover" alt="<?= sanitize($dish['item_name']) ?>">
                <span class="food-type-icon <?= $dish['is_veg'] ? 'veg' : 'non-veg' ?> position-absolute top-0 start-0 m-2"></span>
                <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 end-0 m-2 small">
                  <i class="bi bi-clock me-1"></i> <?= (int)$dish['prep_time_minutes'] ?>m prep
                </span>
              </div>
              <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h6 class="fw-bold text-pine mb-0 text-truncate" style="max-width: 170px;" title="<?= sanitize($dish['item_name']) ?>"><?= sanitize($dish['item_name']) ?></h6>
                  <span class="text-success small fw-bold"><i class="bi bi-star-fill text-warning me-1"></i><?= $dish['rating'] ?></span>
                </div>
                <p class="small text-muted dish-desc-clamp mb-3" style="font-size: 0.78rem;"><?= sanitize($dish['description']) ?></p>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="fw-bold text-pine fs-6"><?= formatPrice($dish['price']) ?></span>
                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="addToCart(<?= $dish['item_id'] ?>, '<?= addslashes($dish['item_name']) ?>', <?= $dish['price'] ?>, <?= $dish['is_veg'] ? 'true' : 'false' ?>)" style="background-color: var(--lush-forest);">
                  <i class="bi bi-plus-lg me-1"></i> ADD
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       PROMO COUPON CALLOUT
       ========================================================================= -->
  <section class="py-5" style="background: linear-gradient(135deg, #1C3322 0%, #243E2C 50%, #2E6F40 100%);">
    <div class="container-xl text-white">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <span class="badge bg-mint text-pine fw-bold px-3 py-1 rounded-pill mb-2">SPECIAL DISCOUNT CODE</span>
          <h2 class="display-6 fw-bold mb-2">Get 20% Off Your Entire Order!</h2>
          <p class="lead text-white-50 mb-0">Use promo code <span class="badge bg-white text-dark font-monospace px-3 py-1">LUSHFOREST</span> at checkout to unlock savings upto ₹100.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="offers.php" class="btn btn-light rounded-pill px-4 py-3 fw-bold text-pine shadow">
            <i class="fa-solid fa-gift text-success me-2"></i> View All 5 Coupons
          </a>
        </div>
      </div>
    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
