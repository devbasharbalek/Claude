<?php
$active = 'hem';
$pageTitle = 'Ludvika Partiaffär – Grossist inom frukt, grönt och storköksvaror';
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/head.php';
$pil = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
?>

<section class="hero">
  <div class="wrap">
    <div class="hero-card">
      <span class="hero-sticker">Leverans alla vardagar</span>
      <div>
        <span class="eyebrow">Grossist för kommuner, restauranger och livsbutiker</span>
        <h1 class="headline">Din grossist sedan 60-talet</h1>
        <p class="hero-sub">Ludvika Partiaffär levererar frukt, grönt och ett brett storkökssortiment till kommuner, restauranger, pizzerior och livsbutiker &mdash; snabbt, pålitligt och med personlig service.</p>
        <div class="hero-actions">
          <a class="btn accent" href="bestall">Skicka beställning</a>
          <a class="btn outline" href="tel:+4624018355">Ring 0240-183 55</a>
        </div>
      </div>
      <div class="hero-art" aria-hidden="true">
        <?php foreach (['frukt', 'gront', 'kylvaror', 'fryst', 'konserv', 'kryddor'] as $key): ?>
          <span><?php echo ikon($key); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section style="padding-block:36px 0;">
  <div class="wrap">
    <div class="kat-head">
      <h2>Allt ni behöver, från en leverantör</h2>
      <a class="pil mork" href="sortiment">Se hela sortimentet <?php echo $pil; ?></a>
    </div>
    <nav class="kat-rad" aria-label="Varugrupper">
      <?php foreach ($sortiment as $key => $s): ?>
        <a class="kat" href="sortiment#<?php echo $key; ?>"><?php echo ikon($key); ?><span><?php echo e($s['namn']); ?></span></a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<section>
  <div class="wrap statement">
    <span class="eyebrow">Om oss</span>
    <p style="margin-top:14px;">Med snabba leveranser och personlig service håller vi samma löfte idag som när vi startade: du ska aldrig behöva oroa dig för din varuförsörjning.</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap duo">
    <div class="duo-kort ljus">
      <span class="eyebrow">Sedan 60-talet</span>
      <h2>En partihandel du kan <mark class="hl">lita på</mark></h2>
      <p>Ludvika Partiaffär har funnits sedan 60-talet och är en rutinerad grossist inom frukt och grönt. Vi levererar till kommuner, restauranger, pizzerior och livsbutiker och vet vad som krävs &mdash; jämn kvalitet, rätt volymer och leveranser som kommer när de ska.</p>
    </div>
    <div class="duo-kort bla">
      <h3><mark class="hl">60+ år</mark> i branschen, leverans <mark class="hl">mån&ndash;fre</mark> och <mark class="hl">tre</mark> kundgrupper: kommuner, restauranger och livsbutiker.</h3>
      <p>Lång erfarenhet av partihandel och snabba leveranser, så att kök och butikshyllor aldrig står tomma.</p>
      <div class="pil-lankar">
        <a class="pil" href="sortiment">Se hela sortimentet <?php echo $pil; ?></a>
        <a class="pil" href="kontakt">Kontakt <?php echo $pil; ?></a>
      </div>
    </div>
  </div>
</section>

<section class="cta-full">
  <div class="wrap">
    <div class="cta-inner">
      <h2>Redo att beställa?</h2>
      <p>Fyll i vårt beställningsformulär så återkommer vi med bekräftelse, eller ring oss direkt under öppettiderna.</p>
      <div class="hero-actions">
        <a class="btn accent" href="bestall">Till beställningen</a>
        <a class="btn outline" href="tel:+4624018355">0240-183 55</a>
      </div>
    </div>
  </div>
</section>

<section class="alt">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Våra kunder</span>
      <h2>Kommuner, restauranger och livsbutiker</h2>
      <p>Vi levererar till tre typer av kunder, med samma fokus på kvalitet och leveranser som går att lita på.</p>
    </div>
    <div class="kort-grid">
      <article class="kort">
        <div class="kort-bild bla" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10M19 21V10M9.5 21v-6h5v6M2 10l10-6 10 6"/></svg></div>
        <span class="kort-meta">Storkök</span>
        <h3>Kommuner</h3>
        <p>Skolor, förskolor, äldreboenden och andra storkök. Jämn kvalitet, rätt volymer och leveranser som går att planera.</p>
      </article>
      <article class="kort">
        <div class="kort-bild gul" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v7a2 2 0 0 0 4 0V2M10 9v13"/><path d="M16 2v8c0 1.5 1 2.5 2 2.5s2-1 2-2.5V2M18 12.5V22"/></svg></div>
        <span class="kort-meta">Lunch, à la carte eller pizza</span>
        <h3>Restauranger &amp; pizzerior</h3>
        <p>Frukt, grönt, kylvaror och tillbehör till à la carte, lunch och pizzakök &mdash; med leveranser som passar er rytm.</p>
      </article>
      <article class="kort">
        <div class="kort-bild" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h8.1a2 2 0 0 0 2-1.5L21.5 8H6.2"/></svg></div>
        <span class="kort-meta">Butikshyllan</span>
        <h3>Livsbutiker</h3>
        <p>Frukt och grönt till butikshyllan, för ICA, Coop och andra livsmedelsbutiker &mdash; färska varor på fasta leveransdagar.</p>
      </article>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
