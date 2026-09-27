<?php
$pageTitle = "Privacy Policy & Data Protection";
require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-5" style="min-height: 75vh;">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      
      <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white">
        <span class="badge bg-mint-pill text-pine fw-bold mb-2 px-3 py-1 rounded-pill d-inline-block" style="background:#CEFFDC; color:#243E2C; width:fit-content;">
          SECURITY & PRIVACY
        </span>
        <h2 class="fw-bold text-pine mb-3">Privacy & Security Policy</h2>
        <p class="text-muted small mb-4">DPDPA 2023 & OWASP Compliant • Platform: Zippy Food Ordering System</p>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">1. Cryptographic Security & Password Storage</h5>
          <p class="small text-muted leading-relaxed">
            In strict compliance with OWASP enterprise standards, passwords on Zippy are NEVER stored in plaintext or weak hashes like MD5/SHA-1. We enforce <strong>Argon2id cryptographic hashing</strong> (`PASSWORD_ARGON2ID`) with secure salt generation, offering memory-hard defense against brute-force and GPU dictionary attacks.
          </p>
        </section>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">2. SQL Injection & XSS Defenses</h5>
          <p class="small text-muted leading-relaxed">
            Every database interaction is executed via <strong>100% Parameterized Prepared Statements</strong> using PHP Data Objects (PDO) with strict type bindings, making the application immune to SQL injection. All view outputs are sanitized through UTF-8 <code>htmlspecialchars()</code> to prevent Cross-Site Scripting (XSS).
          </p>
        </section>

        <section class="mb-4">
          <h5 class="fw-bold text-pine">3. CSRF & Session Security</h5>
          <p class="small text-muted leading-relaxed">
            All POST request forms are protected with cryptographically random anti-CSRF session tokens. Session cookies are configured with <code>HttpOnly</code> and <code>SameSite=Strict</code> attributes to prevent session hijacking and packet inspection.
          </p>
        </section>

        <section class="mb-0">
          <h5 class="fw-bold text-pine">4. Contact Grievance Officer</h5>
          <p class="small text-muted leading-relaxed mb-0">
            For academic queries or privacy requests regarding this IGNOU MCA major project:
            <br>
            <strong>Project Student:</strong> Pooja JP (Enrollment No: 2500051247)
            <br>
            <strong>Email:</strong> <code>pooja@zippy.test</code> • Bengaluru, Karnataka, India.
          </p>
        </section>

      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
