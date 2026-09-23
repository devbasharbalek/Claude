<?php
$active = 'kontakt';
$pageTitle = 'Kontakt – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Kontakt</span>
    <h1>Hör av dig</h1>
    <p>Ring för att beställa eller prata sortiment, eller skicka en förfrågan via beställningsformuläret. Vi svarar under våra öppettider.</p>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="kontakt-grid">
      <div class="kontakt-card">
        <h3>Kontaktuppgifter</h3>
        <div class="kontakt-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 3a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.2-1.4a2 2 0 0 1 2.1-.4c1 .4 2 .7 3 .8a2 2 0 0 1 1.7 2Z"/></svg>
          <a href="tel:+4624018355">0240-183 55</a>
        </div>
        <div class="kontakt-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
          <a href="mailto:bashar@ludvikapartiaffar.se">bashar@ludvikapartiaffar.se</a>
        </div>
        <div class="kontakt-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
          <div>Fläderstigen 2<br>771 43 Ludvika</div>
        </div>
        <div class="kontakt-row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
          <div>Mån&ndash;fre 06:00&ndash;14:30<br>Lör&ndash;sön stängt</div>
        </div>
        <a class="maps-link" href="https://www.google.com/maps/search/?api=1&amp;query=Fl%C3%A4derstigen%202%2C%20771%2043%20Ludvika" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
          Öppna i Google Maps
        </a>
      </div>
      <div class="cta-card">
        <h3>Redo att beställa?</h3>
        <p>Skicka en beställningsförfrågan via formuläret, eller ring oss direkt så hjälper vi dig &mdash; från fast leverans till en enstaka extrabeställning.</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn light" href="bestall.php">Till beställningen</a>
          <a class="btn outline" href="tel:+4624018355">Ring oss</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <div class="leverans">
      <div>
        <span class="eyebrow">Leverans</span>
        <h2>Vi kommer till dig</h2>
        <p>Vi levererar måndag till fredag, så att du kan planera dina beställningar med god marginal. Behöver du en särskild leveransdag för din del av regionen &mdash; ring oss så löser vi det.</p>
        <div class="leverans-days">
          <span class="day-chip on">MÅN</span>
          <span class="day-chip on">TIS</span>
          <span class="day-chip on">ONS</span>
          <span class="day-chip on">TOR</span>
          <span class="day-chip on">FRE</span>
          <span class="day-chip">LÖR</span>
          <span class="day-chip">SÖN</span>
        </div>
      </div>
      <div class="leverans-area">
        <b>Leveransområde</b>
        <span>Hela Dalarna, samt delar av angränsande områden norr och söder om länet. Osäker på om vi kör till just din ort? Ring oss så får du besked direkt.</span>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
