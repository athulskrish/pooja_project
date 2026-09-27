<?php
$pageTitle = "Terms of Service & Conditions";
require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-5" style="min-height: 75vh;">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      
      <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white">
        <span class="badge bg-mint-pill text-pine fw-bold mb-2 px-3 py-1 rounded-pill d-inline-block" style="background:#CEFFDC; color:#243E2C; width:fit-content;">
          LEGAL & COMPLIANCE
        </span>
        <h2 class="fw-bold text-pine mb-3">Terms of Service</h2>
        <p class="text-muted small mb-4">Last Updated: September 2026 • Platform: Zippy Online Food Ordering System</p>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">1. Platform Introduction</h5>
          <p class="small text-muted leading-relaxed">
            Welcome to the Zippy Food Ordering Platform ("Zippy", "we", "us"). This system was engineered as part of the Master of Computer Applications (MCA) Major Project (Course Code: MCSP-232, IGNOU) under the supervision of student Pooja JP (Enrollment No: 2500051247). By accessing our services, ordering food, or registering an account, you agree to comply with these terms.
          </p>
        </section>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">2. Food Ordering & ACID Transactions</h5>
          <p class="small text-muted leading-relaxed">
            All orders placed on Zippy are processed via an ACID-compliant transactional RDBMS workflow with row-level pessimistic locking (`SELECT ... FOR UPDATE`) to prevent race conditions on menu item stock. Once submitted, orders undergo kitchen verification, automated dynamic ETA estimation, and courier assignment.
          </p>
        </section>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">3. Delivery & Secret OTP Handover</h5>
          <p class="small text-muted leading-relaxed">
            Upon order confirmation, a 4-digit verification OTP is generated. The customer must furnish this OTP to the delivery partner upon arrival to confirm physical receipt of the parcel. Zippy bears no liability for parcel disputes if OTP is shared prematurely.
          </p>
        </section>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">4. Cancellation & Refund Policy</h5>
          <p class="small text-muted leading-relaxed">
            Customers may cancel their order unconditionally within a <strong>60-second grace window</strong> after placement. Once the kitchen starts food preparation, cancellations are strictly non-refundable due to the perishable nature of culinary items.
          </p>
        </section>

        <section class="mb-0">
          <h5 class="fw-bold text-pine">5. Indian GST & Compliance</h5>
          <p class="small text-muted leading-relaxed mb-0">
            All prices are quoted in Indian Rupees (INR) and include applicable Goods and Services Tax (GST @ 5% on restaurant food services) as mandated by the Central Board of Indirect Taxes and Customs (CBIC). Itemized tax invoices with SAC code 996331 are generated for every completed transaction.
          </p>
        </section>

      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
