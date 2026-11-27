<?php
require __DIR__ . '/includes/app.php';
// Bara administratörer: här stängs och öppnas webbplatsen för allmänheten.
$user = require_behorighet('personal');
erp_schema();
if (!empty($_GET['forhandsvisa'])) underhall_sida();
// Nödöppningen har gjort sitt: ta bort filen och skapa en ny hemlig länk.
if (is_file(UNDERHALL_NODFIL)) {
  @unlink(UNDERHALL_NODFIL);
  underhall_ny_nyckel();
  flash(is_file(UNDERHALL_NODFIL)
    ? 'En ny inloggningslänk är skapad. Ta bort filen oppna-inloggning.txt i mappen includes med FileZilla, annars är inloggningen fortfarande öppen.'
    : 'Nödöppningen är stängd och en ny inloggningslänk är skapad. Spara länken nedan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'oppna') {
    satt_installning('underhall', '0');
    flash('Webbplatsen är öppen för alla igen.');
  } elseif ($action === 'stang') {
    satt_installning('underhall', '1');
    flash('Underhållsläget är på. Besökare och kunder ser underhållssidan, personalen ser allt som vanligt.');
  } elseif ($action === 'ny_nyckel') {
    underhall_ny_nyckel();
    flash('Inloggningslänken är bytt. Den gamla länken fungerar inte längre. Spara den nya.');
  } elseif ($action === 'text') {
    $text = trim(str_replace("\r", '', (string)($_POST['text'] ?? '')));
    satt_installning('underhall_text', $text !== '' ? mb_substr($text, 0, 600, 'UTF-8') : null);
    flash($text !== '' ? 'Texten på underhållssidan är sparad.' : 'Underhållssidan visar standardtexten igen.');
  }
  redirect('admin-webbplats');
}

$pa = underhall_pa();
// Länken skapas första gången en administratör öppnar sidan. Innan dess är inloggningen öppen.
$nyckel = underhall_nyckel() ?? underhall_ny_nyckel();
$lank = underhall_lank($nyckel);
$egenText = (string)installning('underhall_text', '');

$active = 'admin';
$adminSida = 'webbplats';
$pageTitle = 'Webbplats – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Inställningar</span>
        <h1>Webbplats</h1>
        <p>Medan webbplatsen inte är klar kan den visa en underhållssida för besökare och kunder. Personalen loggar in som vanligt och ser och jobbar med allt.</p>
      </div>
    </div>

    <div class="order-topp">
      <div class="detail-card">
        <h2>Underhållsläge</h2>
        <?php if ($pa): ?>
          <p><span class="pill pill-ny">På</span></p>
          <p class="muted" style="color:var(--ink-soft);">Besökare och kunder ser bara underhållssidan. Ingen kan registrera sig eller beställa via webbplatsen. Personalen loggar in via den hemliga länken och ser allt som vanligt.</p>
          <form method="post" action="admin-webbplats" style="margin-top:14px;" onsubmit="return confirm('Öppna webbplatsen för alla? Då kan kunder registrera sig och beställa.');"><?php echo csrf_field(); ?><button class="btn primary small" type="submit" name="action" value="oppna">Öppna webbplatsen för alla</button></form>
        <?php else: ?>
          <p><span class="pill pill-levererad">Av, webbplatsen är öppen</span></p>
          <p class="muted" style="color:var(--ink-soft);">Alla kan se webbplatsen, registrera sig och beställa.</p>
          <form method="post" action="admin-webbplats" style="margin-top:14px;"><?php echo csrf_field(); ?><button class="btn ghost small" type="submit" name="action" value="stang">Sätt på underhållsläge</button></form>
        <?php endif; ?>
        <p style="margin:16px 0 0;"><a href="admin-webbplats?forhandsvisa=1" target="_blank" style="color:var(--brand); font-weight:600;">Se underhållssidan som besökarna ser den</a></p>
      </div>

      <div class="detail-card">
        <h2>Hemlig inloggningslänk</h2>
        <p class="muted" style="color:var(--ink-soft); margin-top:0;">Medan underhållsläget är på finns ingen inloggning att se. Personalen loggar in via den här länken. Spara den som bokmärke och ge den bara till personalen.</p>
        <div class="kopiera-rad"><code id="hemligLank"><?php echo e($lank); ?></code><button class="btn ghost small" type="button" data-kopiera="hemligLank">Kopiera</button></div>
        <form method="post" action="admin-webbplats" style="margin-top:14px;" onsubmit="return confirm('Byta länk? Den gamla slutar fungera, och personalen behöver den nya.');"><?php echo csrf_field(); ?><button class="lank" type="submit" name="action" value="ny_nyckel" style="color:var(--brand); font-weight:600; background:none; border:none; padding:0; cursor:pointer; font:inherit;">Byt länk</button></form>
      </div>
    </div>

    <div class="order-topp" style="margin-top:20px;">
      <form class="detail-card" method="post" action="admin-webbplats">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="text">
        <h2>Text på underhållssidan</h2>
        <div class="field">
          <label for="text">Meddelande till besökarna</label>
          <textarea id="text" name="text" maxlength="600" placeholder="<?php echo e(UNDERHALL_STANDARDTEXT); ?>"><?php echo e($egenText); ?></textarea>
          <small class="muted" style="color:var(--ink-soft);">Lämna tomt för standardtexten. Telefonnummer, e-post och öppettider visas alltid under texten.</small>
        </div>
        <button class="btn primary small" type="submit" style="margin-top:12px;">Spara text</button>
      </form>
    </div>
  </div>
</section>
<script>
document.querySelectorAll('[data-kopiera]').forEach(function (b) {
  b.addEventListener('click', function () {
    var text = document.getElementById(b.dataset.kopiera).textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { b.textContent = 'Kopierad'; }, function () { b.textContent = 'Markera och kopiera'; });
  });
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
