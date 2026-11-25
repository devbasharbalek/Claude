<?php
require __DIR__ . '/includes/app.php';
// Bara administratörer: här stängs och öppnas webbplatsen för allmänheten.
$user = require_behorighet('personal');
erp_schema();
if (!empty($_GET['forhandsvisa'])) underhall_sida();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'oppna') {
    satt_installning('underhall', '0');
    flash('Webbplatsen är öppen för alla igen.');
  } elseif ($action === 'stang') {
    satt_installning('underhall', '1');
    flash('Underhållsläget är på. Besökare och kunder ser underhållssidan, personalen ser allt som vanligt.');
  } elseif ($action === 'text') {
    $text = trim(str_replace("\r", '', (string)($_POST['text'] ?? '')));
    satt_installning('underhall_text', $text !== '' ? mb_substr($text, 0, 600, 'UTF-8') : null);
    flash($text !== '' ? 'Texten på underhållssidan är sparad.' : 'Underhållssidan visar standardtexten igen.');
  }
  redirect('admin-webbplats');
}

$pa = underhall_pa();
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
          <p class="muted" style="color:var(--ink-soft);">Besökare och kunder ser bara underhållssidan. Ingen kan registrera sig eller beställa via webbplatsen. Personalen loggar in på <strong>/logga-in</strong> och ser allt som vanligt.</p>
          <form method="post" action="admin-webbplats" style="margin-top:14px;" onsubmit="return confirm('Öppna webbplatsen för alla? Då kan kunder registrera sig och beställa.');"><?php echo csrf_field(); ?><button class="btn primary small" type="submit" name="action" value="oppna">Öppna webbplatsen för alla</button></form>
        <?php else: ?>
          <p><span class="pill pill-levererad">Av, webbplatsen är öppen</span></p>
          <p class="muted" style="color:var(--ink-soft);">Alla kan se webbplatsen, registrera sig och beställa.</p>
          <form method="post" action="admin-webbplats" style="margin-top:14px;"><?php echo csrf_field(); ?><button class="btn ghost small" type="submit" name="action" value="stang">Sätt på underhållsläge</button></form>
        <?php endif; ?>
        <p style="margin:16px 0 0;"><a href="admin-webbplats?forhandsvisa=1" target="_blank" style="color:var(--brand); font-weight:600;">Se underhållssidan som besökarna ser den</a></p>
      </div>

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
<?php require __DIR__ . '/includes/foot.php'; ?>
