<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('fakturera');
erp_schema();
$av = vem($user);
$ansluten = spiris_ansluten();
$arAdmin = $user['role'] === 'admin';

// Utkasten personen får se: bara kunder i personens kundgrupper.
function synliga_utkast(array $user, array $ids): array {
  $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
  if (!$ids) return [];
  [$gv, $p] = grupp_villkor($user, 'k.kundgrupp');
  $st = db()->prepare('SELECT f.id FROM fakturor f JOIN kunder k ON k.id = f.kund_id WHERE f.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND $gv ORDER BY f.id");
  $st->execute(array_merge($ids, $p));
  return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// Skickar utkasten och berättar hur det gick.
function skicka_utkast(array $ids, string $av): string {
  $ok = 0;
  $fel = [];
  foreach ($ids as $fid) {
    $m = faktura_skicka($fid, $av);
    if ($m === null) $ok++;
    else $fel[$fid] = $m;
  }
  $text = $ok ? ($ok === 1 ? '1 fakturautkast skickades till Spiris.' : "$ok fakturautkast skickades till Spiris.") : '';
  if ($fel) {
    $forsta = reset($fel);
    $text .= ($text !== '' ? ' ' : '') . (count($fel) === 1 ? utkast_namn((int)array_key_first($fel)) . ' kunde inte skickas: ' . $forsta
             : count($fel) . ' kunde inte skickas. Felet för ' . utkast_namn((int)array_key_first($fel)) . ': ' . $forsta);
  }
  return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'skapa') {
    $datum = giltigt_datum((string)($_POST['fakturadatum'] ?? '')) ?? date('Y-m-d');
    $nya = faktura_skapa((array)($_POST['order'] ?? []), ($_POST['satt'] ?? '') === 'kund', $datum, $user);
    if (!$nya) {
      flash('Välj minst en order. Order som redan ligger på ett fakturautkast kan inte väljas igen.');
      redirect('admin-fakturering');
    }
    $text = (count($nya) === 1 ? '1 fakturautkast' : count($nya) . ' fakturautkast') . ' skapades.';
    if (!empty($_POST['skicka']) && $ansluten) $text = skicka_utkast($nya, $av);
    elseif (!$ansluten) $text .= ' De skickas till Spiris när Spiris är anslutet.';
    flash($text);
    redirect(count($nya) === 1 ? 'admin-faktura?id=' . $nya[0] : 'admin-fakturering?visa=utkast');
  }
  if ($action === 'skicka') {
    $ids = synliga_utkast($user, (array)($_POST['faktura'] ?? []));
    if (!$ids) flash('Välj minst ett utkast att skicka.');
    elseif (!$ansluten) flash('Spiris är inte anslutet än. En administratör ansluter under Inställningar → Spiris.');
    else flash(skicka_utkast($ids, $av));
    redirect('admin-fakturering?visa=utkast');
  }
  redirect('admin-fakturering');
}

$flikar = ['att' => 'Att fakturera', 'utkast' => 'Ej skickade', 'skickade' => 'Skickade till Spiris'];
$visa = isset($flikar[$_GET['visa'] ?? '']) ? $_GET['visa'] : 'att';
$sok = enRad($_GET['sok'] ?? '', 80);
$till = giltigt_datum((string)($_GET['till'] ?? '')) ?? '';

[$gvO, $pO] = grupp_villkor($user, 'COALESCE(k.kundgrupp, o.kundgrupp)');
[$gvF, $pF] = grupp_villkor($user, 'k.kundgrupp');
$klara = "'" . implode("','", FAKTURA_KLARA) . "'";
$st = db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                     WHERE o.status IN ($klara) AND o.faktura_id IS NULL AND $gvO");
$st->execute($pO);
$antal = ['att' => (int)$st->fetchColumn()];
$st = db()->prepare("SELECT f.status, COUNT(*) FROM fakturor f JOIN kunder k ON k.id = f.kund_id WHERE $gvF GROUP BY f.status");
$st->execute($pF);
$perStatus = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$antal['utkast'] = (int)($perStatus['utkast'] ?? 0);
$antal['skickade'] = (int)($perStatus['skickad'] ?? 0);

$sokVillkor = function (string $kolumner, array &$params) use ($sok): string {
  if ($sok === '') return '';
  $delar = [];
  foreach (array_slice(preg_split('/\s+/u', $sok, -1, PREG_SPLIT_NO_EMPTY), 0, 5) as $ord) {
    $esc = addcslashes($ord, '%_\\');
    $or = [];
    foreach (explode(',', $kolumner) as $k) {
      $or[] = "$k LIKE ?";
      $params[] = ctype_digit($ord) && str_contains($k, 'kundnr') ? "$esc%" : "%$esc%";
    }
    $delar[] = '(' . implode(' OR ', $or) . ')';
  }
  return ' AND ' . implode(' AND ', $delar);
};

$kunder = [];
$utanKund = [];
$utkast = [];
if ($visa === 'att') {
  $params = $pO;
  $where = "o.status IN ($klara) AND o.faktura_id IS NULL AND $gvO";
  if ($till !== '') {
    $where .= ' AND (o.leveransdatum IS NULL OR o.leveransdatum <= ?)';
    $params[] = $till;
  }
  $where .= $sokVillkor('k.namn,k.kundnr,o.foretag,CAST(o.id AS CHAR)', $params);
  $st = db()->prepare("SELECT o.id, o.status, o.leveransdatum, o.leveransdag, o.kundens_ordernr, o.er_referens, o.foretag,
                              k.id AS kid, k.kundnr, k.namn AS kund_namn, k.kundgrupp AS kgrupp, k.betalningsvillkor AS kvillkor, k.fakturaavgift, k.visma_id
                       FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                       WHERE $where ORDER BY k.namn IS NULL, k.namn, k.id, o.leveransdatum, o.id LIMIT 1000");
  $st->execute($params);
  $orders = $st->fetchAll();
  $summa = [];
  if ($orders) {
    $ids = array_map(fn($o) => (int)$o['id'], $orders);
    $st = db()->prepare("SELECT order_id, SUM(belopp) AS netto, COUNT(*) AS rader FROM order_rader WHERE typ = 'artikel' AND order_id IN ("
                        . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY order_id');
    $st->execute($ids);
    foreach ($st->fetchAll() as $s) $summa[(int)$s['order_id']] = $s;
  }
  foreach ($orders as $o) {
    $o['netto'] = (float)($summa[(int)$o['id']]['netto'] ?? 0);
    $o['rader'] = (int)($summa[(int)$o['id']]['rader'] ?? 0);
    if ($o['kid'] === null) $utanKund[] = $o;
    else $kunder[(int)$o['kid']][] = $o;
  }
} else {
  $params = $pF;
  $where = "f.status = ? AND $gvF";
  $params[] = $visa === 'utkast' ? 'utkast' : 'skickad';
  $where .= $sokVillkor('k.namn,k.kundnr,CAST(f.id AS CHAR)', $params);
  $st = db()->prepare("SELECT f.*, k.kundnr, k.namn AS kund_namn, k.kundgrupp,
                              (SELECT GROUP_CONCAT(o.id ORDER BY o.id SEPARATOR ',') FROM orders o WHERE o.faktura_id = f.id) AS ordrar
                       FROM fakturor f JOIN kunder k ON k.id = f.kund_id WHERE $where
                       ORDER BY " . ($visa === 'utkast' ? 'f.id' : 'f.skickad_at DESC, f.id DESC') . ' LIMIT 300');
  $st->execute($params);
  $utkast = $st->fetchAll();
}

$active = 'admin';
$adminSida = 'fakturering';
$pageTitle = 'Fakturering – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Försäljning</span>
        <h1>Fakturering</h1>
        <p>Välj plockade och levererade order och gör fakturautkast av dem, ett per order eller flera order på samma faktura. Utkasten skickas till Spiris, där ni gör dem till fakturor.</p>
      </div>
    </div>

    <?php if (!$ansluten): ?>
      <div class="alert warn"><strong>Spiris är inte anslutet än.</strong> Du kan skapa fakturautkast redan nu. De skickas till Spiris när anslutningen är klar.
        <?php if ($arAdmin): ?><a href="admin-spiris" style="color:var(--brand); font-weight:700;">Anslut till Spiris</a><?php else: ?>En administratör ansluter under Inställningar → Spiris.<?php endif; ?></div>
    <?php endif; ?>

    <div class="tabs">
      <?php foreach ($flikar as $key => $label): ?>
        <a href="<?php echo e(adm_lank('admin-fakturering', ['visa' => $key, 'sok' => $sok])); ?>"<?php echo $key === $visa ? ' class="on"' : ''; ?>><?php echo $label; ?><span class="count"><?php echo $antal[$key]; ?></span></a>
      <?php endforeach; ?>
    </div>

    <form class="verktyg" method="get" action="admin-fakturering">
      <input type="hidden" name="visa" value="<?php echo e($visa); ?>">
      <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Sök kund, kundnr eller nummer" aria-label="Sök">
      <?php if ($visa === 'att'): ?>
        <label class="muted" for="till" style="font-size:.88rem;">Levererade till och med</label>
        <input type="date" id="till" name="till" value="<?php echo e($till); ?>" style="width:auto;">
      <?php endif; ?>
      <button class="btn ghost small" type="submit">Visa</button>
      <?php if ($sok !== '' || $till !== ''): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin-fakturering', ['visa' => $visa])); ?>">Rensa</a><?php endif; ?>
    </form>

<?php if ($visa === 'att'): ?>
    <?php if (!$kunder && !$utanKund): ?>
      <div class="empty"><p><?php echo $sok !== '' || $till !== '' ? 'Inga order matchar sökningen.' : 'Inga order väntar på fakturering. Order hamnar här när de är plockade.'; ?></p></div>
    <?php endif; ?>

    <?php if ($kunder): ?>
    <form method="post" action="admin-fakturering" id="faktForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="skapa">
      <?php foreach ($kunder as $kid => $ordrar): $k = $ordrar[0]; $kundSumma = array_sum(array_column($ordrar, 'netto')); ?>
        <div class="detail-card fakt-kund" data-kund>
          <div class="fakt-kund-topp">
            <label class="check inline"><input type="checkbox" data-alla aria-label="Välj alla order för <?php echo e($k['kund_namn']); ?>"> <span><strong><?php echo e($k['kundnr'] . ' ' . $k['kund_namn']); ?></strong></span></label>
            <?php echo grupp_pill($k['kgrupp']); ?>
            <span class="fakt-info">
              <?php echo $k['kvillkor'] !== null ? (int)$k['kvillkor'] . ' dagar' : 'Villkor enligt Spiris'; ?>
              <?php if ((float)$k['fakturaavgift'] > 0): ?> &middot; fakturaavgift <?php echo e(pris_text($k['fakturaavgift'])); ?> kr<?php endif; ?>
              <?php if (!$k['visma_id']): ?> &middot; <span title="Kunden letas upp i Spiris med kundnumret när utkastet skickas.">ny i Spiris?</span><?php endif; ?>
            </span>
          </div>
          <div class="table-wrap">
            <table class="erp fakt-tabell">
              <thead><tr><th class="val"><span class="sr-only">Välj</span></th><th>Order</th><th>Leverans</th><th>Status</th><th>Er order / referens</th><th class="num">Exkl. moms</th></tr></thead>
              <tbody>
              <?php foreach ($ordrar as $o): $tom = $o['rader'] === 0; ?>
                <tr<?php echo $tom ? ' class="fakt-tom"' : ''; ?>>
                  <td class="val"><input type="checkbox" name="order[]" value="<?php echo (int)$o['id']; ?>" data-belopp="<?php echo e(number_format($o['netto'], 2, '.', '')); ?>" aria-label="Välj order <?php echo (int)$o['id']; ?>"<?php echo $tom ? ' disabled' : ''; ?>></td>
                  <td class="nr"><a class="row-link" href="admin-order?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
                  <td style="white-space:nowrap;"><?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></td>
                  <td><?php echo status_pill($o['status']); ?></td>
                  <td class="muted"><?php echo $tom ? '<span class="fakt-varning">Inga artikelrader. Lägg in varorna på ordern först.</span>' : e(trim($o['kundens_ordernr'] . ($o['kundens_ordernr'] !== '' && $o['er_referens'] !== '' ? ' · ' : '') . $o['er_referens'])); ?></td>
                  <td class="num"><?php echo $tom ? '<span class="tom">–</span>' : e(pris_text($o['netto'])); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
              <?php if (count($ordrar) > 1): ?><tfoot><tr><td></td><td colspan="4" class="muted"><?php echo count($ordrar); ?> order</td><td class="num"><strong><?php echo e(pris_text($kundSumma)); ?></strong></td></tr></tfoot><?php endif; ?>
            </table>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="sk-bar fakt-bar">
        <div>
          <strong id="valdaAntal">Inga order valda</strong>
          <span class="muted" id="valdaSumma"></span>
        </div>
        <div class="fakt-bar-val">
          <label for="fakturadatum" class="muted">Fakturadatum</label>
          <input type="date" id="fakturadatum" name="fakturadatum" value="<?php echo e(date('Y-m-d')); ?>" required>
          <?php if ($ansluten): ?>
            <label class="check inline"><input type="checkbox" name="skicka" value="1" checked> <span>Skicka till Spiris direkt</span></label>
          <?php endif; ?>
          <button class="btn ghost small" type="submit" name="satt" value="order" data-kraver-val disabled>En faktura per order</button>
          <button class="btn primary small" type="submit" name="satt" value="kund" data-kraver-val disabled>Samla per kund</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($utanKund): ?>
      <div class="detail-card fakt-kund">
        <h2>Order som saknar kund i kundregistret</h2>
        <p class="muted">De här kan inte faktureras förrän de är kopplade till en kund. Öppna ordern och koppla den med kundnumret.</p>
        <div class="table-wrap">
          <table class="erp">
            <thead><tr><th>Order</th><th>Leverans</th><th>Företag</th><th>Status</th><th class="num">Exkl. moms</th></tr></thead>
            <tbody>
            <?php foreach ($utanKund as $o): ?>
              <tr>
                <td class="nr"><a class="row-link" href="admin-order?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
                <td style="white-space:nowrap;"><?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></td>
                <td><?php echo e($o['foretag']); ?></td>
                <td><?php echo status_pill($o['status']); ?></td>
                <td class="num"><?php echo $o['rader'] ? e(pris_text($o['netto'])) : '<span class="tom">–</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

<?php else: ?>
    <?php if (!$utkast): ?>
      <div class="empty"><p><?php echo $sok !== '' ? 'Inga utkast matchar sökningen.' : ($visa === 'utkast' ? 'Alla fakturautkast är skickade till Spiris.' : 'Inga utkast är skickade än.'); ?></p></div>
    <?php else: ?>
      <?php if ($visa === 'utkast'): ?><form method="post" action="admin-fakturering" id="utkastForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="skicka"><?php endif; ?>
      <div class="table-wrap bred">
        <table class="erp">
          <thead><tr>
            <?php if ($visa === 'utkast'): ?><th class="val"><input type="checkbox" data-alla-utkast aria-label="Välj alla utkast"></th><?php endif; ?>
            <th>Utkast</th><th>Kund</th><th>Order</th><th>Fakturadatum</th><th class="num">Exkl. moms</th><th class="num">Inkl. moms</th><th><?php echo $visa === 'utkast' ? 'Status' : 'Skickat'; ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($utkast as $f): $ordrar = $f['ordrar'] === null ? [] : explode(',', $f['ordrar']); ?>
            <tr>
              <?php if ($visa === 'utkast'): ?><td class="val"><input type="checkbox" name="faktura[]" value="<?php echo (int)$f['id']; ?>" aria-label="Välj <?php echo e(utkast_namn((int)$f['id'])); ?>"></td><?php endif; ?>
              <td class="nr" style="white-space:nowrap;"><a class="row-link" href="admin-faktura?id=<?php echo (int)$f['id']; ?>"><?php echo e(utkast_namn((int)$f['id'])); ?></a></td>
              <td><strong><?php echo e($f['kund_namn']); ?></strong><?php echo grupp_pill($f['kundgrupp']); ?><br><span class="muted" style="font-size:.84rem;"><?php echo e($f['kundnr']); ?></span></td>
              <td class="muted"><?php echo e(implode(', ', array_map(fn($i) => '#' . $i, $ordrar))); ?></td>
              <td style="white-space:nowrap;"><?php echo e($f['fakturadatum']); ?></td>
              <td class="num"><?php echo e(pris_text($f['netto'])); ?></td>
              <td class="num"><?php echo e(pris_text((float)$f['netto'] + (float)$f['moms'])); ?></td>
              <td>
                <?php if ($visa === 'utkast'): ?>
                  <?php echo faktura_status_pill($f); ?>
                  <?php if ($f['fel']): ?><div class="fakt-fel"><?php echo e(mb_strimwidth($f['fel'], 0, 160, '…', 'UTF-8')); ?></div><?php endif; ?>
                <?php else: ?>
                  <span style="white-space:nowrap;"><?php echo e(datum($f['skickad_at'])); ?></span><br><span class="muted" style="font-size:.84rem;"><?php echo e($f['skickad_av']); ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($visa === 'utkast'): ?>
        <div class="sk-bar">
          <span class="muted"><?php echo $ansluten ? 'Valda utkast skickas till Spiris. Där gör ni dem till fakturor.' : 'Spiris är inte anslutet än.'; ?></span>
          <button class="btn primary small" type="submit"<?php echo $ansluten ? '' : ' disabled'; ?>>Skicka valda till Spiris</button>
        </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
  </div>
</section>
<script>
(function () {
  var form = document.getElementById('faktForm');
  if (form) {
    var kr = function (n) { return n.toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr'; };
    var uppdatera = function () {
      var valda = form.querySelectorAll('input[name="order[]"]:checked');
      var summa = 0;
      valda.forEach(function (c) { summa += parseFloat(c.dataset.belopp) || 0; });
      document.getElementById('valdaAntal').textContent = valda.length ? (valda.length === 1 ? '1 order vald' : valda.length + ' order valda') : 'Inga order valda';
      document.getElementById('valdaSumma').textContent = valda.length ? ' · ' + kr(summa) + ' exkl. moms' : '';
      form.querySelectorAll('[data-kraver-val]').forEach(function (b) { b.disabled = !valda.length; });
      form.querySelectorAll('[data-kund]').forEach(function (k) {
        var alla = k.querySelectorAll('input[name="order[]"]:not(:disabled)');
        var v = k.querySelectorAll('input[name="order[]"]:checked');
        var box = k.querySelector('[data-alla]');
        box.checked = alla.length > 0 && v.length === alla.length;
        box.indeterminate = v.length > 0 && v.length < alla.length;
        box.disabled = !alla.length;
      });
    };
    form.addEventListener('change', function (e) {
      if (e.target.matches('[data-alla]')) {
        e.target.closest('[data-kund]').querySelectorAll('input[name="order[]"]:not(:disabled)').forEach(function (c) { c.checked = e.target.checked; });
      }
      uppdatera();
    });
    uppdatera();
  }
  var alla = document.querySelector('[data-alla-utkast]');
  if (alla) alla.addEventListener('change', function () {
    document.querySelectorAll('input[name="faktura[]"]').forEach(function (c) { c.checked = alla.checked; });
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
