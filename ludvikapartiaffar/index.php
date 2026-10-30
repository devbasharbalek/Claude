<?php
$active = 'hem';
$pageTitle = 'Ludvika Partiaffär – Grossist inom frukt, grönt och storköksvaror';
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/head.php';
?>

<section class="hero">
  <div class="hero-inner">
    <span class="eyebrow">Grossist för kommuner, restauranger och livsbutiker</span>
    <h1 class="headline">Din grossist sedan 60-talet</h1>
    <p class="hero-sub">Ludvika Partiaffär levererar frukt, grönt och ett brett storkökssortiment till kommuner, restauranger, pizzerior och livsbutiker &mdash; snabbt, pålitligt och med personlig service.</p>
    <div class="hero-actions">
      <a class="btn accent" href="bestall.php">
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
      <div class="stat"><b>Tre kundgrupper</b><span>Kommuner, restauranger och livsbutiker</span></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap about">
    <div>
      <span class="eyebrow">Om oss</span>
      <h2>En partihandel du kan lita på</h2>
      <p>Ludvika Partiaffär har funnits sedan 60-talet och är en rutinerad grossist inom frukt och grönt. Vi levererar till kommuner, restauranger, pizzerior och livsbutiker och vet vad som krävs &mdash; jämn kvalitet, rätt volymer och leveranser som kommer när de ska.</p>
      <p>Med snabba leveranser och personlig service håller vi samma löfte idag som när vi startade: du ska aldrig behöva oroa dig för din varuförsörjning.</p>
    </div>
    <div class="badge-stack">
      <div class="badge-card"><b>Sedan 60-talet</b><span>Lång erfarenhet av partihandel</span></div>
      <div class="badge-card"><b>Snabba leveranser</b><span>Så att kök och butikshyllor aldrig står tomma</span></div>
    </div>
  </div>
</section>

<section class="alt">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Sortiment</span>
      <h2>Allt ni behöver, från en leverantör</h2>
      <p>Tio varugrupper för storkök, restauranger och butiker.</p>
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
      <h2>Kommuner, restauranger och livsbutiker</h2>
      <p>Vi levererar till tre typer av kunder, med samma fokus på kvalitet och leveranser som går att lita på.</p>
    </div>
    <div class="kund-grid">
      <div class="kund-card">
        <div class="kund-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10M19 21V10M9.5 21v-6h5v6M2 10l10-6 10 6"/></svg></div>
        <h3>Kommuner</h3>
        <p>Skolor, förskolor, äldreboenden och andra storkök. Jämn kvalitet, rätt volymer och leveranser som går att planera.</p>
      </div>
      <div class="kund-card">
        <div class="kund-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v7a2 2 0 0 0 4 0V2M10 9v13"/><path d="M16 2v8c0 1.5 1 2.5 2 2.5s2-1 2-2.5V2M18 12.5V22"/></svg></div>
        <h3>Restauranger &amp; pizzerior</h3>
        <p>Frukt, grönt, kylvaror och tillbehör till à la carte, lunch och pizzakök &mdash; med leveranser som passar er rytm.</p>
      </div>
      <div class="kund-card">
        <div class="kund-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h8.1a2 2 0 0 0 2-1.5L21.5 8H6.2"/></svg></div>
        <h3>Livsbutiker</h3>
        <p>Frukt och grönt till butikshyllan, för ICA, Coop och andra livsmedelsbutiker &mdash; färska varor på fasta leveransdagar.</p>
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
        <a class="btn accent" href="bestall.php">Till beställningen</a>
        <a class="btn outline" href="tel:+4624018355">0240-183 55</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
