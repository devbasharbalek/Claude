<?php
require __DIR__ . '/includes/app.php';
$admin = require_behorighet('order');
erp_schema();
$visaVinst = far($admin, 'inpris_se');

$flikar = [
  'aktiva'     => ['Ej plockade', "o.status IN ('ny','bekraftad')"],
  'plockad'    => ['Plockade', "o.status = 'plockad'"],
  'levererad'  => ['Levererade', "o.status = 'levererad'"],
  'fakturerad' => ['Fakturerade', "o.status = 'fakturerad'"],
  'avbruten'   => ['Avbrutna', "o.status = 'avbruten'"],
  'alla'       => ['Alla', '1=1'],
];
// Äldre länkar använde visa=ny och visa=bekraftad.
$visa = (string)($_GET['visa'] ?? '');
if (in_array($visa, ['ny', 'bekraftad'], true)) $visa = 'aktiva';
$f = [
  'visa' => isset($flikar[$visa]) ? $visa : 'aktiva',
  'seg' => in_array($_GET['seg'] ?? '', ['kommun', 'foretag'], true) ? $_GET['seg'] : '',
  'datum' => ($_GET['datum'] ?? '') === 'utan' ? 'utan' : (giltigt_datum((string)($_GET['datum'] ?? '')) ?? ''),
  'sok' => enRad($_GET['sok'] ?? '', 80),
  'kund' => (int)($_GET['kund'] ?? 0),
  'kid' => (int)($_GET['kid'] ?? 0),
];

// Filter utom fliken, så att flikarna visar antal för samma urval.
$grupp = 'COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp)';
// Personal som bara jobbar med vissa kundgrupper ser bara deras order.
[$gv, $params] = grupp_villkor($admin, $grupp);
$where = [$gv];
if ($f['seg'] === 'kommun') $where[] = "$grupp = 'A'";
elseif ($f['seg'] === 'foretag') $where[] = "($grupp IS NULL OR $grupp <> 'A')";
if ($f['sok'] !== '') {
  if (preg_match('/^#?(\d+)$/', $f['sok'], $m)) {
    $where[] = '(o.id = ? OR k.kundnr = ? OR o.kundens_ordernr = ?)';
    array_push($params, (int)$m[1], $m[1], $m[1]);
  } else {
    $like = '%' . addcslashes($f['sok'], '%_\\') . '%';
    $where[] = '(o.foretag LIKE ? OR o.kontakt LIKE ? OR o.epost LIKE ? OR k.namn LIKE ? OR k.lev_namn LIKE ? OR k.kundnr LIKE ? OR o.kundens_ordernr LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
  }
}
if ($f['kund']) {
  $where[] = 'o.user_id = ?';
  $params[] = $f['kund'];
}
if ($f['kid']) {
  $where[] = 'k.id = ?';
  $params[] = $f['kid'];
}
$fran = 'FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id) LEFT JOIN order_superklipp os ON os.order_id = o.id';
// Datumvalet läggs till sist, så att listan med datum kan räknas utan det.
$utanDatum = implode(' AND ', $where);
$utanDatumParams = $params;
if ($f['datum'] === 'utan') $where[] = 'o.leveransdatum IS NULL';
elseif ($f['datum'] !== '') {
  $where[] = 'o.leveransdatum = ?';
  $params[] = $f['datum'];
}
$basWhere = implode(' AND ', $where);

$antal = array_fill_keys(array_keys($flikar), 0);
$st = db()->prepare("SELECT o.status, COUNT(*) AS n $fran WHERE $basWhere GROUP BY o.status");
$st->execute($params);
foreach ($st as $r) {
  $antal['alla'] += (int)$r['n'];
  if (in_array($r['status'], ORDER_OPPNA, true)) $antal['aktiva'] += (int)$r['n'];
  elseif (isset($antal[$r['status']])) $antal[$r['status']] += (int)$r['n'];
}

$whereSql = $basWhere . ' AND ' . $flikar[$f['visa']][1];
$st = db()->prepare("SELECT COUNT(*) $fran WHERE $whereSql");
$st->execute($params);
$totalt = (int)$st->fetchColumn();
$perSida = 50;
$sidor = max(1, (int)ceil($totalt / $perSida));
$sida = min(max(1, (int)($_GET['sida'] ?? 1)), $sidor);
$ordning = in_array($f['visa'], ['aktiva', 'plockad'], true)
  ? 'o.leveransdatum IS NULL, o.leveransdatum, o.foretag, o.id'
  : 'o.created_at DESC, o.id DESC';
$st = db()->prepare("SELECT o.id, o.foretag, o.kontakt, o.leveransdag, o.leveransdatum, o.kategorier, o.status, o.created_at, o.user_id, o.kalla,
                            o.plocktext, o.meddelande, o.kundens_ordernr, o.skapad_av, os.etikett, k.kundnr, k.lev_namn, $grupp AS grupp
                     $fran WHERE $whereSql ORDER BY $ordning LIMIT $perSida OFFSET " . (($sida - 1) * $perSida));
$st->execute($params);
$orders = $st->fetchAll();

// Värde, kolli och plock per order på sidan.
$summa = [];
if ($orders) {
  $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
  foreach (db()->query("SELECT order_id, SUM(belopp) AS belopp, SUM(kostnad) AS kostnad, SUM(typ = 'artikel' AND kostnad IS NULL) AS utan_inpris,
                               SUM(CASE WHEN typ = 'lista' THEN antal WHEN typ = 'artikel' THEN COALESCE(kolli, enheter / NULLIF(enheter_per_kolli, 0)) ELSE 0 END) AS kolli,
                               SUM(typ <> 'text') AS rader, SUM(typ <> 'text' AND plockat IS NOT NULL) AS plockade
                        FROM order_rader WHERE order_id IN ($ids) GROUP BY order_id") as $r) {
    $summa[(int)$r['order_id']] = $r;
  }
}

// Leveransdatum att välja bland, för fliken och urvalet utom datumet.
$st = db()->prepare("SELECT o.leveransdatum AS d, COUNT(*) AS n $fran WHERE $utanDatum AND " . $flikar[$f['visa']][1] . "
                     AND (o.leveransdatum IS NULL OR o.leveransdatum >= ?) GROUP BY o.leveransdatum ORDER BY o.leveransdatum IS NULL, o.leveransdatum LIMIT 60");
$st->execute(array_merge($utanDatumParams, [date('Y-m-d', strtotime('-14 days'))]));
$datumLista = $st->fetchAll();

$idag = db()->prepare('SELECT COUNT(*) FROM orders WHERE created_at >= ?');
$idag->execute([date('Y-m-d 00:00:00')]);
$idagAntal = (int)$idag->fetchColumn();
$statusAntal = array_fill_keys(array_keys(ORDER_STATUS), 0);
foreach (db()->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) $statusAntal[$r['status']] = (int)$r['n'];

$kundNamn = null;
if ($f['kund']) {
  $k = db()->prepare('SELECT foretag FROM users WHERE id = ?');
  $k->execute([$f['kund']]);
  $kundNamn = $k->fetchColumn() ?: null;
} elseif ($f['kid']) {
  $kundNamn = kund_hamta($f['kid'])['namn'] ?? null;
}

$active = 'admin';
$adminSida = 'order';
$pageTitle = 'Order – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Order</h1>
        <p>Alla order: från hemsidan, Superklipp, Veckansklipp och de som personalen lägger.</p>
      </div>
      <div class="portal-actions">
        <?php if (far($admin, 'plock')): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin-plock', ['datum' => $f['datum'] !== 'utan' ? $f['datum'] : ''])); ?>">Plock</a><?php endif; ?>
        <?php if (far($admin, 'order_andra')): ?><a class="btn primary small" href="admin-order?ny=1">Ny order</a><?php endif; ?>
      </div>
    </div>

    <?php $nyaKonton = far($admin, 'webbkonton') ? (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'kund' AND godkand = 0 AND active = 1")->fetchColumn() : 0; ?>
    <?php if ($nyaKonton): ?>
      <div class="sk-notis">
        <p><strong><?php echo $nyaKonton === 1 ? '1 nytt kundkonto väntar' : $nyaKonton . ' nya kundkonton väntar'; ?> på godkännande.</strong> De ser inga erbjudanden förrän du godkänner dem.</p>
        <div class="actions"><a class="btn primary small" href="admin-kunder?visa=nya">Godkänn konton</a></div>
      </div>
    <?php endif; ?>

    <?php if (!korta_adresser() && $admin['role'] === 'admin'): ?>
      <div class="alert warn"><strong>Korta adresser är avstängda.</strong> Filen .htaccess saknas i public_html på servern, eller så läses den inte. Webbplatsen fungerar ändå, men adresserna slutar på .php. Ladda upp .htaccess till public_html (slå på "Visa dolda filer" i FileZilla), så blir adresserna korta av sig själva.</div>
    <?php endif; ?>

    <div class="stat-row">
      <div class="stat-tile<?php echo $statusAntal['ny'] ? ' warn' : ''; ?>"><b><?php echo $statusAntal['ny']; ?></b><span>Nya, väntar på bekräftelse</span></div>
      <div class="stat-tile"><b><?php echo $statusAntal['ny'] + $statusAntal['bekraftad']; ?></b><span>Ej plockade</span></div>
      <div class="stat-tile"><b><?php echo $statusAntal['plockad']; ?></b><span>Plockade, ej levererade</span></div>
      <div class="stat-tile"><b><?php echo $idagAntal; ?></b><span>Inkomna idag</span></div>
    </div>

    <?php if ($kundNamn !== null): ?>
      <div class="flash" style="margin:0 0 16px;">Visar order från <strong><?php echo e($kundNamn); ?></strong>. <a href="<?php echo e(adm_lank('admin', array_merge($f, ['kund' => 0, 'kid' => 0]))); ?>" style="color:var(--brand);">Visa alla kunder</a></div>
    <?php endif; ?>

    <div class="tabs">
      <?php foreach ($flikar as $key => [$label]): ?>
        <a href="<?php echo e(adm_lank('admin', array_merge($f, ['visa' => $key]))); ?>"<?php echo $key === $f['visa'] ? ' class="on"' : ''; ?>><?php echo $label; ?><span class="count"><?php echo $antal[$key]; ?></span></a>
      <?php endforeach; ?>
    </div>

    <form class="verktyg" method="get" action="admin">
      <input type="hidden" name="visa" value="<?php echo e($f['visa']); ?>">
      <?php foreach (['kund', 'kid'] as $dold): if ($f[$dold]): ?><input type="hidden" name="<?php echo $dold; ?>" value="<?php echo $f[$dold]; ?>"><?php endif; endforeach; ?>
      <input type="search" name="sok" value="<?php echo e($f['sok']); ?>" placeholder="Sök kund, kundnr eller ordernr" aria-label="Sök order">
      <select name="datum" aria-label="Leveransdatum">
        <option value="">Alla leveransdatum</option>
        <?php $valdFinns = false; foreach ($datumLista as $d): $varde = $d['d'] ?? 'utan'; $valdFinns = $valdFinns || $varde === $f['datum']; ?>
          <option value="<?php echo e($varde); ?>"<?php echo $varde === $f['datum'] ? ' selected' : ''; ?>><?php echo e(($d['d'] === null ? 'Utan datum' : leverans_text($d['d'])) . ' (' . $d['n'] . ')'); ?></option>
        <?php endforeach; ?>
        <?php if ($f['datum'] !== '' && !$valdFinns): ?><option value="<?php echo e($f['datum']); ?>" selected><?php echo e($f['datum'] === 'utan' ? 'Utan datum' : leverans_text($f['datum'])); ?></option><?php endif; ?>
      </select>
      <select name="seg" aria-label="Kundtyp">
        <option value="">Alla kunder</option>
        <option value="kommun"<?php echo $f['seg'] === 'kommun' ? ' selected' : ''; ?>>Kommuner</option>
        <option value="foretag"<?php echo $f['seg'] === 'foretag' ? ' selected' : ''; ?>>Företag och butiker</option>
      </select>
      <button class="btn ghost small" type="submit">Visa</button>
      <?php if ($f['sok'] !== '' || $f['datum'] !== '' || $f['seg'] !== ''): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin', ['visa' => $f['visa'], 'kund' => $f['kund'], 'kid' => $f['kid']])); ?>">Rensa</a><?php endif; ?>
      <?php if ($f['datum'] !== '' && $f['datum'] !== 'utan' && far($admin, 'plock')): ?>
        <span class="hoger"><a class="btn ghost small" href="admin-utskrift?typ=plocksedel&amp;datum=<?php echo e($f['datum']); ?>" target="_blank">Skriv ut plocksedlar</a></span>
      <?php endif; ?>
    </form>
    <p class="rad-info"><?php echo $totalt; ?> order<?php echo $sidor > 1 ? ', sida ' . $sida . ' av ' . $sidor : ''; ?>.</p>

    <?php if (!$orders): ?>
      <div class="empty"><p><?php echo $f['sok'] !== '' ? 'Inga order matchar sökningen.' : 'Inga order här just nu.'; ?></p></div>
    <?php else: ?>
      <div class="table-wrap bred">
        <table class="erp">
          <thead><tr><th>Nr</th><th>Leverans</th><th>Kund</th><th>Från</th><th class="num">Kolli</th><th class="num">Värde</th><?php if ($visaVinst): ?><th class="num">Vinst</th><?php endif; ?><th>Kommentar</th><th>Plock</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): $s = $summa[(int)$o['id']] ?? null; $kommentar = trim($o['plocktext'] !== '' ? $o['plocktext'] : $o['meddelande']); ?>
            <tr>
              <td class="nr"><a class="row-link" href="admin-order?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
              <td style="white-space:nowrap;"><?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></td>
              <td><strong><?php echo e($o['foretag']); ?></strong><?php echo grupp_pill($o['grupp']); ?><br><span class="muted" style="font-size:.84rem;"><?php echo e(trim(($o['kundnr'] ?? '') . ' ' . ($o['lev_namn'] ?: $o['kontakt']))); ?><?php echo $o['kundens_ordernr'] !== '' ? ' &middot; Er ref. ' . e($o['kundens_ordernr']) : ''; ?></span></td>
              <td class="muted"><?php echo e($o['etikett'] ?? ($o['kalla'] === 'webb' ? 'Hemsidan' . ($o['kategorier'] !== '' ? ': ' . kategori_namn($o['kategorier']) : '') : (ORDER_KALLOR[$o['kalla']] ?? $o['kalla']) . ($o['skapad_av'] !== '' ? ', ' . $o['skapad_av'] : ''))); ?></td>
              <td class="num"><?php echo $s && (float)$s['kolli'] > 0 ? e(tal_text($s['kolli'])) : '<span class="tom">–</span>'; ?></td>
              <td class="num"><?php echo $s && $s['belopp'] !== null ? e(pris_text($s['belopp'])) : '<span class="tom">–</span>'; ?></td>
              <?php if ($visaVinst): ?><td class="num"><?php echo $s && $s['belopp'] !== null && !(int)$s['utan_inpris'] ? e(pris_text((float)$s['belopp'] - (float)$s['kostnad'])) : '<span class="tom">–</span>'; ?></td><?php endif; ?>
              <td class="muted" style="max-width:220px; overflow-wrap:anywhere; font-size:.85rem;"><?php echo e(mb_strimwidth($kommentar, 0, 70, '…', 'UTF-8')); ?></td>
              <td class="muted" style="white-space:nowrap;"><?php echo $s && (int)$s['rader'] ? (int)$s['plockade'] . ' / ' . (int)$s['rader'] : '<span class="tom">–</span>'; ?></td>
              <td><?php echo status_pill($o['status']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php echo sida_navigering('admin', $f, $sida, $sidor); ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
