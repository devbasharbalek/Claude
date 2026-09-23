<?php
$active = 'sortiment';
$pageTitle = 'Sortiment – Ludvika Partiaffär';
require __DIR__ . '/includes/sortiment-data.php';
require __DIR__ . '/includes/head.php';
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Sortiment</span>
    <h1>Allt ditt kök behöver</h1>
    <p>Från färsk frukt och grönt till kylvaror, fryst och non-food. Vi har byggt vårt sortiment för restaurang- och pizzeriakök &mdash; och hittar du inte det du söker, fråga oss.</p>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="sortiment-grid">
      <?php foreach ($sortiment as $key => $s): ?>
        <div class="produkt-card">
          <div class="produkt-icon"><?php echo ikon($key); ?></div>
          <h3><?php echo htmlspecialchars($s['namn']); ?></h3>
          <p><?php echo htmlspecialchars($s['text']); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <div class="cta-band">
      <div>
        <h2>Vill du beställa?</h2>
        <p>Kryssa i varugrupperna och skriv vad du behöver i beställningsformuläret, så återkommer vi med bekräftelse.</p>
      </div>
      <div class="actions">
        <a class="btn light" href="bestall.php">Till beställningen</a>
        <a class="btn outline" href="tel:+4624018355">Ring oss</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
