<?php
// En person i personalen: uppgifter, roll, egna behörigheter och vilka kund- och artikelgrupper personen jobbar med.
require __DIR__ . '/includes/app.php';
$admin = require_behorighet('personal');
erp_schema();

$st = db()->prepare("SELECT * FROM users WHERE id = ? AND role <> 'kund'");
$st->execute([(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) {
  http_response_code(404);
  exit('Personen finns inte.');
}
$id = (int)$p['id'];
$sjalv = $id === (int)$admin['id'];
$artikelgrupper = db()->query("SELECT DISTINCT artikelgrupp FROM artiklar WHERE artikelgrupp <> '' ORDER BY artikelgrupp")->fetchAll(PDO::FETCH_COLUMN);
$allaBehorigheter = array_keys(array_merge(...array_values(BEHORIGHETER)));
$fel = [];
$v = ['kontakt' => $p['kontakt'], 'email' => $p['email'], 'telefon' => $p['telefon'], 'role' => $p['role'], 'kan' => behorigheter($p),
      'kundgrupper' => anv_grupper($p, 'kundgrupper') ?? [], 'artikelgrupper' => anv_grupper($p, 'artikelgrupper') ?? []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $v['kontakt'] = enRad($_POST['kontakt'] ?? '', 120);
  $v['email'] = mb_strtolower(enRad($_POST['email'] ?? '', 160), 'UTF-8');
  $v['telefon'] = enRad($_POST['telefon'] ?? '', 40);
  if ($v['kontakt'] === '') $fel['kontakt'] = 'Ange namnet.';
  if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $fel['email'] = 'Ange en giltig e-postadress.';
  else {
    $st = db()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
    $st->execute([$v['email'], $id]);
    if ($st->fetchColumn()) $fel['email'] = 'Det finns redan ett konto med den e-postadressen.';
  }
  // Sin egen roll och sina egna behörigheter ändrar man inte, så att ingen låser ute sig själv.
  if (!$sjalv) {
    $v['role'] = isset(ROLLER[$_POST['role'] ?? '']) ? $_POST['role'] : $p['role'];
    $v['kan'] = array_values(array_intersect($allaBehorigheter, (array)($_POST['kan'] ?? [])));
    $v['kundgrupper'] = array_values(array_intersect(array_keys(KUNDGRUPPER), (array)($_POST['kundgrupper'] ?? [])));
    $v['artikelgrupper'] = array_values(array_intersect($artikelgrupper, (array)($_POST['artikelgrupper'] ?? [])));
    if ($p['role'] === 'admin' && $v['role'] !== 'admin' && (int)$p['active'] === 1
        && (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn() <= 1) {
      $fel['role'] = 'Det måste finnas minst en administratör.';
    }
  }
  if (!$fel) {
    if ($sjalv) {
      db()->prepare('UPDATE users SET kontakt = ?, email = ?, telefon = ? WHERE id = ?')->execute([$v['kontakt'], $v['email'], $v['telefon'], $id]);
    } else {
      // Samma kryss som rollens mall sparas som "enligt rollen", så att mallen kan ändras senare.
      $mall = array_values(array_intersect($allaBehorigheter, ROLLER[$v['role']]['kan']));
      $egna = $v['role'] === 'admin' || $v['kan'] == $mall ? null : implode(',', $v['kan']);
      $kg = $v['role'] === 'admin' || !$v['kundgrupper'] || count($v['kundgrupper']) === count(KUNDGRUPPER) ? null : implode(',', $v['kundgrupper']);
      $ag = $v['role'] === 'admin' || !$v['artikelgrupper'] || count($v['artikelgrupper']) === count($artikelgrupper) ? null : implode(',', $v['artikelgrupper']);
      db()->prepare('UPDATE users SET kontakt = ?, email = ?, telefon = ?, role = ?, behorigheter = ?, kundgrupper = ?, artikelgrupper = ? WHERE id = ?')
          ->execute([$v['kontakt'], $v['email'], $v['telefon'], $v['role'], $egna, $kg, $ag, $id]);
    }
    flash($v['kontakt'] . ' är sparad.');
    redirect('admin-person.php?id=' . $id);
  }
}

$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
$las = $sjalv ? ' disabled' : '';
$active = 'admin';
$adminSida = 'personal';
$pageTitle = $p['kontakt'] . ' – Personal – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin-personal.php">&larr; Personal</a>
    <div class="portal-head">
      <div>
        <h1><?php echo e($p['kontakt']); ?></h1>
        <p><?php echo e(roll_text($p)); ?> &middot; <?php echo (int)$p['active'] ? 'Aktiv' : 'Avstängd'; ?> &middot; senast inloggad <?php echo $p['last_login'] ? e(datum($p['last_login'])) : 'aldrig'; ?></p>
      </div>
    </div>
    <?php if ($fel): ?><div class="alert error" role="alert">Inget sparades, se markeringarna nedan.</div><?php endif; ?>
    <?php if ($sjalv): ?><div class="alert info">Det här är du. Din egen roll och dina behörigheter ändras av en annan administratör.</div><?php endif; ?>

    <form method="post" action="admin-person.php" id="personForm" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo $id; ?>">
      <div class="detail-card">
        <h2>Uppgifter</h2>
        <div class="field-grid tre">
          <div class="field"><label for="kontakt">Namn</label><input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" required><?php echo $err('kontakt'); ?></div>
          <div class="field"><label for="email">E-post</label><input type="email" id="email" name="email" value="<?php echo e($v['email']); ?>" required><?php echo $err('email'); ?></div>
          <div class="field"><label for="telefon">Telefon</label><input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>"></div>
        </div>
      </div>

      <div class="detail-card">
        <h2>Roll</h2>
        <div class="val-kort" role="radiogroup" aria-label="Roll">
          <?php foreach (ROLLER as $r => $info): ?>
            <label class="check"><input type="radio" name="role" value="<?php echo $r; ?>" data-kan="<?php echo e(implode(',', $info['kan'])); ?>"<?php echo $v['role'] === $r ? ' checked' : ''; ?><?php echo $las; ?>>
              <span><strong><?php echo e($info['namn']); ?></strong><small><?php echo e($info['text']); ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <?php echo $err('role'); ?>
        <p class="form-note" style="margin-top:12px;">Rollen fyller i kryssen nedan. Ändra dem om personen ska kunna mer eller mindre än rollen.</p>
      </div>

      <div class="detail-card" id="behorigheter">
        <h2>Behörigheter</h2>
        <div class="beh-grid">
          <?php foreach (BEHORIGHETER as $omrade => $poster): ?>
            <fieldset class="beh-omrade">
              <legend><?php echo e($omrade); ?></legend>
              <?php foreach ($poster as $nyckel => $text): ?>
                <label class="check inline"><input type="checkbox" name="kan[]" value="<?php echo $nyckel; ?>"<?php echo in_array($nyckel, $v['kan'], true) ? ' checked' : ''; ?><?php echo $las; ?>> <span><?php echo e($text); ?></span></label>
              <?php endforeach; ?>
            </fieldset>
          <?php endforeach; ?>
          <fieldset class="beh-omrade">
            <legend>Personal</legend>
            <p class="form-note">Personal och behörigheter har bara administratörer.</p>
          </fieldset>
        </div>
      </div>

      <div class="detail-card" id="grupper">
        <h2>Grupper</h2>
        <p class="form-note" style="margin-bottom:12px;">Kryssa i om personen bara ska se vissa kunder eller artiklar, till exempel bara kommuner. Inga kryss betyder alla.</p>
        <div class="field-grid">
          <fieldset>
            <legend style="font-size:1rem;">Kundgrupper</legend>
            <div class="flaggor">
              <?php foreach (KUNDGRUPPER as $g => $namn): ?>
                <label class="check"><input type="checkbox" name="kundgrupper[]" value="<?php echo $g; ?>"<?php echo in_array($g, $v['kundgrupper'], true) ? ' checked' : ''; ?><?php echo $las; ?>> <?php echo e($g . ' – ' . $namn); ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <fieldset>
            <legend style="font-size:1rem;">Artikelgrupper</legend>
            <div class="flaggor">
              <?php foreach ($artikelgrupper as $g): ?>
                <label class="check"><input type="checkbox" name="artikelgrupper[]" value="<?php echo e($g); ?>"<?php echo in_array($g, $v['artikelgrupper'], true) ? ' checked' : ''; ?><?php echo $las; ?>> <?php echo e($g === 'A' ? 'A – kommunsortiment' : ($g === 'L' ? 'L – övrigt sortiment' : $g)); ?></label>
              <?php endforeach; ?>
              <?php if (!$artikelgrupper): ?><span class="form-note">Inga artiklar är inlästa än.</span><?php endif; ?>
            </div>
          </fieldset>
        </div>
      </div>
      <div class="spara-rad"><button class="btn primary" type="submit">Spara</button><a class="btn ghost" href="admin-personal.php">Tillbaka</a></div>
    </form>
  </div>
</section>
<script>
// Byte av roll fyller i rollens kryss. Administratörer har alltid allt.
(function () {
  var form = document.getElementById('personForm');
  function lasAdmin() {
    var admin = (form.querySelector('input[name=role]:checked') || {}).value === 'admin';
    form.querySelectorAll('#behorigheter input, #grupper input').forEach(function (cb) { if (!cb.hasAttribute('data-las')) cb.disabled = admin; });
    if (admin) form.querySelectorAll('#behorigheter input').forEach(function (cb) { cb.checked = true; });
  }
  form.querySelectorAll('input[name=role]').forEach(function (r) {
    if (r.disabled) form.querySelectorAll('#behorigheter input, #grupper input').forEach(function (cb) { cb.setAttribute('data-las', ''); });
    r.addEventListener('change', function () {
      var kan = r.dataset.kan.split(',');
      form.querySelectorAll('input[name="kan[]"]').forEach(function (cb) { cb.checked = kan.indexOf(cb.value) !== -1; });
      lasAdmin();
    });
  });
  lasAdmin();
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
