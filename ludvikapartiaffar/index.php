<?php
$active = 'hem';
$pageTitle = 'Ludvika Partiaffär – Grossist för restaurang & pizzeria i Dalarna';
require __DIR__ . '/includes/sortiment-data.php';
require __DIR__ . '/includes/head.php';
?>

<section class="hero">
  <div class="hero-inner">
    <span class="eyebrow">Grossist för restaurang &amp; pizzeria i Dalarna</span>
    <h1 class="headline">Din lokala leverantör sedan 60-talet</h1>
    <p class="hero-sub">Ludvika Partiaffär levererar frukt, grönt och ett brett storkökssortiment till restauranger och pizzerior i hela Dalarna &mdash; snabbt, pålitligt och med personlig service.</p>
    <div class="hero-actions">
      <a class="btn light" href="bestall.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg>
        Skicka beställning
      </a>
      <a class="btn outline" href="tel:+4624018355">Ring 0240-183 55</a>
    </div>
  </div>
  <div class="stat-strip">
    <div class="stat-inner">
      <div class="stat"><b>60+ år</b><span>I branschen sedan 60-talet</span></div>
      <div class="stat"><b>Mån&ndash;fre</b><span>Leverans alla vardagar</span></div>
      <div class="stat"><b>Hela Dalarna</b><span>Plus angränsande områden</span></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap about">
    <div>
      <span class="eyebrow">Om oss</span>
      <h2>En lokal partiaffär du kan lita på</h2>
      <p>Ludvika Partiaffär har funnits sedan 60-talet och är en rutinerad grossist inom frukt och grönt. Vi vet vad restauranger och pizzerior behöver för att köket ska rulla &mdash; jämn kvalitet, rätt volymer och leveranser som kommer när de ska.</p>
      <p>Med snabba leveranser och personlig service håller vi samma löfte idag som när vi startade: du ska aldrig behöva oroa dig för din varuförsörjning.</p>
    </div>
    <div class="badge-stack">
      <div class="badge-card"><b>Sedan 60-talet</b><span>Lokal partihandel i Ludvika</span></div>
      <div class="badge-card"><b>Snabba leveranser</b><span>Så att ditt kök aldrig står still</span></div>
    </div>
  </div>
</section>

<section class="alt">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Sortiment</span>
      <h2>Allt ditt kök behöver, från en leverantör</h2>
      <p>Tio varugrupper anpassade för restaurang- och pizzeriakök.</p>
    </div>
    <div class="icon-row">
      <?php foreach ($sortiment as $key => $s): ?>
        <span class="icon-pill"><span class="i"><?php echo ikon($key); ?></span><?php echo htmlspecialchars($s['namn']); ?></span>
      <?php endforeach; ?>
    </div>
    <a class="link-arrow" href="sortiment.php">Se hela sortimentet
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Våra kunder</span>
      <h2>Byggt för restaurang &amp; pizzeria</h2>
    </div>
    <div class="kund-grid">
      <div class="kund-card">
        <div class="kund-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v7a2 2 0 0 0 4 0V2M10 9v13"/><path d="M16 2v8c0 1.5 1 2.5 2 2.5s2-1 2-2.5V2M18 12.5V22"/></svg></div>
        <h3>Restauranger</h3>
        <p>Jämn kvalitet och rätt volymer, dag efter dag &mdash; så att köket kan lita på leveransen och fokusera på gästerna.</p>
      </div>
      <div class="kund-card">
        <div class="kund-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 19h18Z"/><path d="M8.5 12h7"/><circle cx="12" cy="9.5" r=".9" fill="currentColor" stroke="none"/><circle cx="9.5" cy="15" r=".9" fill="currentColor" stroke="none"/><circle cx="14.5" cy="15" r=".9" fill="currentColor" stroke="none"/></svg></div>
        <h3>Pizzerior</h3>
        <p>Grönt, konserver, kylvaror och tillbehör för pizzeriakök &mdash; med leveranser som passar er rytm.</p>
      </div>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <div class="cta-band">
      <div>
        <h2>Redo att beställa?</h2>
        <p>Fyll i vårt beställningsformulär så återkommer vi med bekräftelse, eller ring oss direkt under öppettiderna.</p>
      </div>
      <div class="actions">
        <a class="btn light" href="bestall.php">Till beställningen</a>
        <a class="btn outline" href="tel:+4624018355">0240-183 55</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
