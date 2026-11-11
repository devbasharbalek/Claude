<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('plock');
erp_schema();
$seAlla = far($user, 'plock_alla');
$av = vem($user);
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// En order personen får plocka: rätt kundgrupp, och sin egen eller ingens om personen inte ser allas.
function plock_order(int $id, array $user, bool $seAlla): ?array {
  $o = order_hamta($id);
  if (!$o || !far_grupp($user, order_grupp($o))) return null;
  if (!$seAlla && $o['plockare_id'] !== null && (int)$o['plockare_id'] !== (int)$user['id']) return null;
  return $o;
}

function plock_rader(int $orderId): array {
  $st = db()->prepare("SELECT r.*, a.produktgrupp FROM order_rader r LEFT JOIN artiklar a ON a.id = r.artikel_id
                       WHERE r.order_id = ? ORDER BY r.typ = 'text', a.produktgrupp IS NULL, a.produktgrupp, r.sortering, r.id");
  $st->execute([$orderId]);
  return $st->fetchAll();
}

function plock_kort(array $o, array $rader, array $user, bool $seAlla, array $plockare): string {
  $id = (int)$o['id'];
  $varor = array_values(array_filter($rader, fn($r) => $r['typ'] !== 'text'));
  $klara = count(array_filter($varor, fn($r) => $r['plockat'] !== null));
  $vikt = array_sum(array_map(fn($r) => rad_vikt($r), $varor));
  $csrf = csrf_field();
  $h = '<article class="plock-kort' . ($klara && $klara === count($varor) ? ' klar' : '') . '" id="order-' . $id . '" data-id="' . $id . '">';
  $h .= '<header><span class="plock-datum">' . e(leverans_text($o['leveransdatum'], $o['leveransdag'])) . '</span>' . status_pill($o['status']) . '</header>';
  $h .= '<h3>' . (far($user, 'order') ? '<a href="admin-order?id=' . $id . '">Order #' . $id . '</a>' : 'Order #' . $id) . '</h3>';
  $h .= '<p class="plock-kund"><strong>' . e(trim(($o['kundnr'] ?? '') . ' ' . $o['foretag'])) . '</strong>' . grupp_pill(order_grupp($o))
      . ($o['lev_namn'] && $o['lev_namn'] !== $o['foretag'] ? '<br>' . e($o['lev_namn']) : '')
      . ($o['kundens_ordernr'] !== '' ? '<br><span class="muted">Kundens ordernr ' . e($o['kundens_ordernr']) . '</span>' : '') . '</p>';
  // Plockare och kontroll.
  $h .= '<div class="plock-knappar"><form method="post" action="admin-plock" data-ajax>' . $csrf . '<input type="hidden" name="action" value="plockare"><input type="hidden" name="order_id" value="' . $id . '">';
  if ($seAlla) {
    $h .= '<select name="plockare_id" aria-label="Plockare för order ' . $id . '" data-auto><option value="0">Ej tilldelad</option>';
    foreach ($plockare as $pid => $namn) $h .= '<option value="' . (int)$pid . '"' . ((int)$o['plockare_id'] === (int)$pid ? ' selected' : '') . '>' . e($namn) . '</option>';
    $h .= '</select>';
  } else {
    $min = (int)$o['plockare_id'] === (int)$user['id'];
    $h .= '<input type="hidden" name="plockare_id" value="' . ($min ? 0 : (int)$user['id']) . '"><button class="btn ghost small" type="submit">' . ($min ? 'Släpp ordern' : 'Ta ordern') . '</button>';
  }
  $h .= '</form><form method="post" action="admin-plock" data-ajax>' . $csrf . '<input type="hidden" name="action" value="kontroll"><input type="hidden" name="order_id" value="' . $id . '">'
      . '<button class="btn small ' . ($o['kontrollerad_at'] ? 'kontroll-ok' : 'kontroll-ej') . '" type="submit" title="' . ($o['kontrollerad_at'] ? 'Kontrollerad av ' . e($o['kontrollerad_av']) : 'Markera som kontrollerad') . '">'
      . ($o['kontrollerad_at'] ? 'Kontrollerad' : 'Ej kontrollerad') . '</button></form></div>';
  if ($o['plocktext'] !== '') $h .= '<p class="plock-text">' . e($o['plocktext']) . '</p>';
  $h .= '<ul class="plock-rader">';
  $grupp = null;
  foreach ($rader as $r) {
    if ($r['typ'] === 'text') {
      $h .= '<li class="plock-textrad">' . e($r['produkt']) . '</li>';
      continue;
    }
    if (($r['produktgrupp'] ?? null) !== $grupp && $r['produktgrupp'] !== null) {
      $grupp = $r['produktgrupp'];
      $h .= '<li class="plock-grupp">' . e($grupp) . '</li>';
    }
    $mal = rad_att_plocka($r);
    $plockat = $r['plockat'] === null ? null : (float)$r['plockat'];
    $avvik = $plockat !== null && !samma_pris($plockat, $mal);
    $enhet = rad_plockenhet($r);
    $under = $r['typ'] === 'artikel' && $r['kolli'] !== null && $r['enhet'] !== '' ? mangd_text($r['enheter']) . ' ' . $r['enhet'] : ($r['typ'] === 'lista' ? sk_forpackning($r) : '');
    $h .= '<li class="plock-rad' . ($plockat !== null ? ($avvik ? ' avvik' : ' plockad') : '') . '">'
        . '<span class="plock-antal"><b>' . ($plockat === null ? '0' : e(tal_text($plockat))) . '</b> / ' . e(tal_text($mal)) . '<small>' . e($enhet) . '</small></span>'
        . '<span class="plock-vara">' . e($r['produkt']) . ((int)$r['viktvara'] ? ' <span class="vikt-markering" title="Viktvara: väg och skriv vikten">!</span>' : '')
        . ($under !== '' ? '<small>' . e($under) . ((int)$r['viktvara'] && $r['plockvikt'] !== null ? ', vägt ' . e(tal_text($r['plockvikt'])) . ' kg' : '') . '</small>' : '')
        . ($r['plockkommentar'] !== '' ? '<small class="plock-kommentar">' . e($r['plockkommentar']) . '</small>' : '') . '</span>';
    $h .= '<form method="post" action="admin-plock" data-ajax class="plock-form">' . $csrf . '<input type="hidden" name="action" value="rad"><input type="hidden" name="rad_id" value="' . (int)$r['id'] . '">';
    if ($plockat === null) {
      $h .= '<details class="plock-annat"><summary aria-label="Annat antal">±</summary><span>'
          . '<input type="text" inputmode="decimal" name="antal" placeholder="' . e(tal_text($mal)) . '" aria-label="Plockat antal">'
          . ((int)$r['viktvara'] ? '<input type="text" inputmode="decimal" name="vikt" placeholder="kg" aria-label="Vägd vikt i kg">' : '')
          . '<button class="btn ghost small" type="submit" name="varde" value="antal">OK</button></span></details>'
          . '<button class="plock-bock" type="submit" name="varde" value="full" aria-label="Plocka ' . e($r['produkt']) . '" title="Plockad"></button>';
    } else {
      $h .= '<button class="plock-bock vald" type="submit" name="varde" value="noll" aria-label="Ångra plock av ' . e($r['produkt']) . '" title="Ångra"></button>';
    }
    $h .= '</form></li>';
  }
  $h .= '</ul><footer><span>' . $klara . ' av ' . count($varor) . ' plockade' . ($vikt > 0 ? ' &middot; netto ' . e(tal_text(round($vikt, 1))) . ' kg' : '') . '</span>'
      . '<span class="plock-lankar">';
  if ($klara < count($varor)) {
    $h .= '<form method="post" action="admin-plock" data-ajax>' . $csrf . '<input type="hidden" name="action" value="allt"><input type="hidden" name="order_id" value="' . $id . '"><button class="btn ghost small" type="submit">Plocka allt</button></form>';
  }
  $h .= '<a class="btn ghost small" href="admin-utskrift?typ=plocksedel&amp;id=' . $id . '" target="_blank">Plocksedel</a>'
      . '<a class="btn ghost small" href="admin-utskrift?typ=foljesedel&amp;id=' . $id . '" target="_blank">Följesedel</a></span></footer></article>';
  return $h;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');
  $orderId = (int)($_POST['order_id'] ?? 0);
  $meddelande = '';
  if ($action === 'rad') {
    $st = db()->prepare('SELECT * FROM order_rader WHERE id = ?');
    $st->execute([(int)($_POST['rad_id'] ?? 0)]);
    $rad = $st->fetch();
    $orderId = (int)($rad['order_id'] ?? 0);
    if ($rad && plock_order($orderId, $user, $seAlla)) {
      $varde = (string)($_POST['varde'] ?? '');
      $vikt = tal_ur_text((string)($_POST['vikt'] ?? ''));
      $antal = $varde === 'noll' ? null : ($varde === 'antal' && trim((string)($_POST['antal'] ?? '')) !== '' ? tal_ur_text((string)$_POST['antal']) : rad_att_plocka($rad));
      if ($antal === false || ($antal !== null && ($antal < 0 || $antal > 99999)) || $vikt === false || ($vikt !== null && ($vikt <= 0 || $vikt > 99999))) {
        $meddelande = 'Skriv antal och vikt som tal, till exempel 2 eller 14,6.';
      } else {
        plocka_rad((int)$rad['id'], $antal === null ? null : round($antal, 3), $vikt === null ? null : round($vikt, 3), $av);
      }
    }
  } elseif ($action === 'allt' && ($o = plock_order($orderId, $user, $seAlla))) {
    foreach (order_rader_alla($orderId) as $rad) {
      if ($rad['typ'] !== 'text' && $rad['plockat'] === null) plocka_rad((int)$rad['id'], rad_att_plocka($rad), null, $av);
    }
  } elseif ($action === 'plockare' && ($o = plock_order($orderId, $user, $seAlla))) {
    $ny = (int)($_POST['plockare_id'] ?? 0);
    // Den som inte ser allas order kan bara ta eller släppa en order själv.
    if (!$seAlla && !in_array($ny, [0, (int)$user['id']], true)) $ny = (int)$user['id'];
    if ($ny === 0 || isset(plockare_lista()[$ny])) {
      db()->prepare('UPDATE orders SET plockare_id = ?, updated_at = ?, andrad_av = ? WHERE id = ?')->execute([$ny ?: null, now(), $av, $orderId]);
    }
  } elseif ($action === 'kontroll' && ($o = plock_order($orderId, $user, $seAlla))) {
    if ($o['kontrollerad_at']) db()->prepare("UPDATE orders SET kontrollerad_at = NULL, kontrollerad_av = '' WHERE id = ?")->execute([$orderId]);
    else db()->prepare('UPDATE orders SET kontrollerad_at = ?, kontrollerad_av = ? WHERE id = ?')->execute([now(), $av, $orderId]);
  }
  if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    $o = $orderId ? plock_order($orderId, $user, $seAlla) : null;
    echo json_encode(['ok' => $meddelande === '', 'meddelande' => $meddelande, 'html' => $o ? plock_kort($o, plock_rader($orderId), $user, $seAlla, $seAlla ? plockare_lista() : []) : ''], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($meddelande !== '') flash($meddelande);
  $tillbaka = array_filter(['datum' => giltigt_datum((string)($_POST['datum'] ?? '')), 'visa' => (string)($_POST['visa'] ?? ''), 'seg' => (string)($_POST['seg'] ?? '')]);
  redirect('admin-plock' . ($tillbaka ? '?' . http_build_query($tillbaka) : '') . ($orderId ? '#order-' . $orderId : ''));
}

// Dagarna med order att plocka. Utan val visas första dagen från idag som har något kvar att plocka.
[$gv, $gp] = grupp_villkor($user, 'COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp)');
$egen = $seAlla ? '1=1' : '(o.plockare_id IS NULL OR o.plockare_id = ' . (int)$user['id'] . ')';
$fran = "FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
         WHERE o.status IN ('ny','bekraftad','plockad') AND o.leveransdatum IS NOT NULL AND $gv AND $egen";
$st = db()->prepare("SELECT o.leveransdatum AS d, COUNT(*) AS n, SUM(o.status <> 'plockad') AS kvar $fran AND o.leveransdatum >= ? GROUP BY o.leveransdatum ORDER BY o.leveransdatum LIMIT 30");
$st->execute(array_merge($gp, [date('Y-m-d', strtotime('-7 days'))]));
$dagar = $st->fetchAll();
$datum = giltigt_datum((string)($_GET['datum'] ?? ''));
if ($datum === null) {
  $datum = date('Y-m-d');
  foreach ($dagar as $d) {
    if ($d['d'] >= date('Y-m-d') && (int)$d['kvar'] > 0) {
      $datum = $d['d'];
      break;
    }
  }
}
$visa = in_array($_GET['visa'] ?? '', ['ej', 'plockade', 'ej_kontroll'], true) ? $_GET['visa'] : '';
$seg = in_array($_GET['seg'] ?? '', ['kommun', 'foretag'], true) ? $_GET['seg'] : '';
$sok = enRad($_GET['sok'] ?? '', 80);

$where = [];
$params = array_merge($gp, [$datum]);
if ($seg === 'kommun') $where[] = "COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp) = 'A'";
elseif ($seg === 'foretag') $where[] = "(COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp) IS NULL OR COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp) <> 'A')";
if ($sok !== '') {
  $like = '%' . addcslashes($sok, '%_\\') . '%';
  $where[] = '(o.foretag LIKE ? OR k.kundnr LIKE ? OR k.lev_namn LIKE ? OR o.id = ? OR EXISTS (SELECT 1 FROM order_rader x WHERE x.order_id = o.id AND x.produkt LIKE ?))';
  array_push($params, $like, $like, $like, (int)$sok, $like);
}
$st = db()->prepare("SELECT o.id $fran AND o.leveransdatum = ? " . ($where ? ' AND ' . implode(' AND ', $where) : '') . ' ORDER BY o.foretag, o.id');
$st->execute($params);
$ids = $st->fetchAll(PDO::FETCH_COLUMN);

$kort = [];
$stat = ['order' => 0, 'rader' => 0, 'plockade' => 0, 'avvik' => 0, 'kolli' => 0.0, 'vikt' => 0.0];
foreach ($ids as $oid) {
  $o = order_hamta((int)$oid);
  $rader = plock_rader((int)$oid);
  $varor = array_filter($rader, fn($r) => $r['typ'] !== 'text');
  $klar = $varor && count(array_filter($varor, fn($r) => $r['plockat'] !== null)) === count($varor);
  if ($visa === 'ej' && $klar) continue;
  if ($visa === 'plockade' && !$klar) continue;
  if ($visa === 'ej_kontroll' && $o['kontrollerad_at']) continue;
  $stat['order']++;
  foreach ($varor as $r) {
    $stat['rader']++;
    $stat['kolli'] += rad_kolli($r);
    $stat['vikt'] += rad_vikt($r);
    if ($r['plockat'] !== null) {
      $stat['plockade']++;
      if (!samma_pris((float)$r['plockat'], rad_att_plocka($r))) $stat['avvik']++;
    }
  }
  $kort[] = [$o, $rader];
}
$plockare = $seAlla ? plockare_lista() : [];
$procent = $stat['rader'] ? (int)round($stat['plockade'] / $stat['rader'] * 100) : 0;
$filter = ['datum' => $datum, 'visa' => $visa, 'seg' => $seg, 'sok' => $sok];

$active = 'admin';
$adminSida = 'plock';
$pageTitle = 'Plock – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <h1>Plock</h1>
        <p><?php echo e(leverans_text($datum)); ?>. Tryck på rutan när en rad är plockad. Viktvaror (!) vägs och vikten skrivs in under ±.</p>
      </div>
      <div class="portal-actions">
        <?php if ($kort): ?><a class="btn ghost small" href="admin-utskrift?typ=plocksedel&amp;datum=<?php echo e($datum); ?>" target="_blank">Skriv ut alla plocksedlar</a><?php endif; ?>
      </div>
    </div>

    <form class="verktyg" method="get" action="admin-plock">
      <select name="datum" aria-label="Leveransdag" onchange="this.form.submit()">
        <?php $finns = false; foreach ($dagar as $d): $finns = $finns || $d['d'] === $datum; ?>
          <option value="<?php echo e($d['d']); ?>"<?php echo $d['d'] === $datum ? ' selected' : ''; ?>><?php echo e(leverans_text($d['d']) . ' (' . $d['n'] . ' order, ' . $d['kvar'] . ' kvar)'); ?></option>
        <?php endforeach; ?>
        <?php if (!$finns): ?><option value="<?php echo e($datum); ?>" selected><?php echo e(leverans_text($datum)); ?> (inga order)</option><?php endif; ?>
      </select>
      <select name="visa" aria-label="Visa" onchange="this.form.submit()">
        <option value="">Alla order</option>
        <option value="ej"<?php echo $visa === 'ej' ? ' selected' : ''; ?>>Ej plockade</option>
        <option value="plockade"<?php echo $visa === 'plockade' ? ' selected' : ''; ?>>Plockade</option>
        <option value="ej_kontroll"<?php echo $visa === 'ej_kontroll' ? ' selected' : ''; ?>>Ej kontrollerade</option>
      </select>
      <select name="seg" aria-label="Kundtyp" onchange="this.form.submit()">
        <option value="">Kommuner och företag</option>
        <option value="kommun"<?php echo $seg === 'kommun' ? ' selected' : ''; ?>>Kommuner</option>
        <option value="foretag"<?php echo $seg === 'foretag' ? ' selected' : ''; ?>>Företag och butiker</option>
      </select>
      <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Sök kund, ordernr eller vara" aria-label="Sök">
      <button class="btn ghost small" type="submit">Visa</button>
    </form>

    <div class="plock-status">
      <span><b><?php echo $stat['order']; ?></b> order</span>
      <span><b><?php echo $stat['rader']; ?></b> rader</span>
      <span><b><?php echo e(tal_text(round($stat['kolli'], 1))); ?></b> kolli</span>
      <span><b><?php echo e(tal_text(round($stat['vikt']))); ?></b> kg</span>
      <span class="plock-mataren" role="img" id="plockMatare" aria-label="<?php echo $procent; ?> procent plockat"><i style="width:<?php echo $procent; ?>%"></i></span>
      <span id="plockProcent"><b><?php echo $procent; ?> %</b> plockat<?php echo $stat['avvik'] ? ', <b class="avvik-text">' . $stat['avvik'] . '</b> med avvikelse' : ''; ?></span>
    </div>

    <?php if (!$kort): ?>
      <div class="empty"><p>Inga order att plocka <?php echo e(mb_strtolower(leverans_text($datum), 'UTF-8')); ?><?php echo $visa || $seg || $sok !== '' ? ' med det urvalet' : ''; ?>.<?php echo $seAlla ? '' : ' Du ser order som är dina eller inte tilldelade någon.'; ?></p></div>
    <?php else: ?>
      <div class="plock-grid" id="plockGrid">
        <?php foreach ($kort as [$o, $rader]) echo plock_kort($o, $rader, $user, $seAlla, $plockare); ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
// Plockar utan att sidan laddas om: formuläret skickas i bakgrunden och kortet byts ut.
(function () {
  var grid = document.getElementById('plockGrid');
  if (!grid || !window.fetch) return;
  grid.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.hasAttribute('data-ajax')) return;
    e.preventDefault();
    var data = new FormData(form);
    if (e.submitter && e.submitter.name) data.append(e.submitter.name, e.submitter.value);
    var kort = form.closest('.plock-kort');
    kort.classList.add('sparar');
    fetch('admin-plock', {method: 'POST', body: data, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (svar) {
        if (svar.meddelande) alert(svar.meddelande);
        if (svar.html) { var tmp = document.createElement('div'); tmp.innerHTML = svar.html; kort.replaceWith(tmp.firstElementChild); }
        else kort.remove();
      })
      .catch(function () { form.submit(); });
  });
  // Räknar om hur stor del som är plockad efter varje ändring.
  function raknaOm() {
    var alla = grid.querySelectorAll('li.plock-rad').length;
    var klara = grid.querySelectorAll('li.plock-rad.plockad, li.plock-rad.avvik').length;
    var avvik = grid.querySelectorAll('li.plock-rad.avvik').length;
    var p = alla ? Math.round(klara / alla * 100) : 0;
    var m = document.getElementById('plockMatare');
    m.firstElementChild.style.width = p + '%';
    m.setAttribute('aria-label', p + ' procent plockat');
    document.getElementById('plockProcent').innerHTML = '<b>' + p + ' %</b> plockat' + (avvik ? ', <b class="avvik-text">' + avvik + '</b> med avvikelse' : '');
  }
  new MutationObserver(raknaOm).observe(grid, {childList: true});
  grid.addEventListener('change', function (e) {
    if (e.target.hasAttribute('data-auto')) e.target.form.requestSubmit();
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
