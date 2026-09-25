<?php
require __DIR__ . '/includes/app.php';
$active = '';
$pageTitle = 'Tack för din beställning – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>

<section>
  <div class="wrap">
    <div class="tack">
      <div class="tack-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
      <h1>Tack för din beställning!</h1>
      <p>Vi har tagit emot din förfrågan och återkommer med bekräftelse så snart vi kan under våra öppettider, mån&ndash;fre 06:00&ndash;14:30.</p>
      <p>Har det bråttom? Ring oss på <a href="tel:+4624018355"><strong>0240-183 55</strong></a>.</p>
      <div class="actions">
        <a class="btn primary" href="index.php">Till startsidan</a>
        <a class="btn ghost" href="bestall.php">Skicka en till beställning</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
