<?php
require __DIR__ . '/includes/app.php';
$admin = require_admin();

$filters = [
  'aktiva'    => ['Aktiva', "o.status IN ('ny','bekraftad')"],
  'ny'        => ['Nya', "o.status = 'ny'"],
  'bekraftad' => ['Bekräftade', "o.status = 'bekraftad'"],
  'levererad' => ['Levererade', "o.status = 'levererad'"],
  'avbruten'  => ['Avbrutna', "o.status = 'avbruten'"],
  'alla'      => ['Alla', '1=1'],
];
$filter = isset($filters[$_GET['visa'] ?? '']) ? $_GET['visa'] : 'aktiva';
$sok = enRad($_GET['sok'] ?? '', 80);
$kundId = (int)($_GET['kund'] ?? 0);
$sida = max(1, (int)($_GET['sida'] ?? 1));
$perSida = 50;

$counts = array_fill_keys(array_keys(ORDER_STATUS), 0);
foreach (db()->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
  $counts[$r['status']] = (int)$r['n'];
}
$idag = db()->prepare('SELECT COUNT(*) FROM orders WHERE created_at >= ?');
$idag->execute([date('Y-m-d 00:00:00')]);
$idagAntal = (int)$idag->fetchColumn();
$tabCount = [
  'aktiva' => $counts['ny'] + $counts['bekraftad'], 'ny' => $counts['ny'], 'bekraftad' => $counts['bekraftad'],
  'levererad' => $counts['levererad'], 'avbruten' => $counts['avbruten'], 'alla' => array_sum($counts),
];

$where = [$filters[$filter][1]];
$params = [];
if ($sok !== '') {
  if (preg_match('/^#?(\d+)$/', $sok, $m)) {
    $where[] = 'o.id = ?';
    $params[] = (int)$m[1];
  } else {
    $where[] = '(o.foretag LIKE ? OR o.kontakt LIKE ? OR o.epost LIKE ?)';
    $like = '%' . addcslashes($sok, '%_\\') . '%';
    array_push($params, $like, $like, $like);
  }
}
if ($kundId) {
  $where[] = 'o.user_id = ?';
  $params[] = $kundId;
}
$whereSql = implode(' AND ', $where);

$st = db()->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$st->execute($params);
$totalt = (int)$st->fetchColumn();
$sidor = max(1, (int)ceil($totalt / $perSida));
$sida = min($sida, $sidor);

$st = db()->prepare("SELECT o.id, o.foretag, o.kontakt, o.leveransdag, o.kategorier, o.status, o.created_at, o.user_id
                     FROM orders o WHERE $whereSql
                     ORDER BY o.created_at DESC, o.id DESC LIMIT $perSida OFFSET " . (($sida - 1) * $perSida));
$st->execute($params);
$orders = $st->fetchAll();

$kund = null;
if ($kundId) {
  $k = db()->prepare("SELECT foretag FROM users WHERE id = ?");
  $k->execute([$kundId]);
  $kund = $k->fetchColumn();
}

function lank(array $andra): string {
  global $filter, $sok, $kundId;
  $q = array_filter(array_merge(['visa' => $filter, 'sok' => $sok, 'kund' => $kundId ?: null], $andra), fn($x) => $x !== null && $x !== '');
  return 'admin.php' . ($q ? '?' . http_build_query($q) : '');
}

$active = 'admin';
$pageTitle = 'Beställningar – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Beställningar</h1>
        <p>Inloggad som <?php echo e($admin['kontakt']); ?></p>
      </div>
      <div class="portal-actions">
        <a class="btn ghost" href="admin-kunder.php">Kunder</a>
        <a class="btn ghost" href="profil.php">Mitt konto</a>
        <form method="post" action="logga-ut.php" style="margin:0;"><?php echo csrf_field(); ?><button class="btn ghost" type="submit">Logga ut</button></form>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat-tile<?php echo $counts['ny'] ? ' warn' : ''; ?>"><b><?php echo $counts['ny']; ?></b><span>Nya, väntar på bekräftelse</span></div>
      <div class="stat-tile"><b><?php echo $counts['bekraftad']; ?></b><span>Bekräftade, ej levererade</span></div>
      <div class="stat-tile"><b><?php echo $idagAntal; ?></b><span>Inkomna idag</span></div>
      <div class="stat-tile"><b><?php echo $counts['levererad']; ?></b><span>Levererade totalt</span></div>
    </div>

    <?php if ($kund !== null && $kund !== false): ?>
      <div class="flash" style="margin:0 0 16px;">Visar beställningar från <strong><?php echo e($kund); ?></strong>. <a href="<?php echo e(lank(['kund' => null, 'sida' => null])); ?>" style="color:var(--brand);">Visa alla kunder</a></div>
    <?php endif; ?>

    <div class="tabs">
      <?php foreach ($filters as $key => [$label]): ?>
        <a href="<?php echo e(lank(['visa' => $key, 'sida' => null])); ?>"<?php echo $key === $filter ? ' class="on"' : ''; ?>><?php echo $label; ?><span class="count"><?php echo $tabCount[$key]; ?></span></a>
      <?php endforeach; ?>
    </div>

    <form class="search" method="get" action="admin.php">
      <input type="hidden" name="visa" value="<?php echo e($filter); ?>">
      <?php if ($kundId): ?><input type="hidden" name="kund" value="<?php echo $kundId; ?>"><?php endif; ?>
      <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Sök företag, kontakt, e-post eller nr" aria-label="Sök beställningar">
      <button class="btn ghost small" type="submit">Sök</button>
    </form>

    <?php if (!$orders): ?>
      <div class="empty"><p><?php echo $sok !== '' ? 'Inga beställningar matchar sökningen.' : 'Inga beställningar här just nu.'; ?></p></div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Nr</th><th>Inkom</th><th>Företag</th><th>Leveransdag</th><th>Varugrupper</th><th>Konto</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td data-label="Nr"><a class="row-link" href="admin-order.php?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
              <td data-label="Inkom" class="muted"><?php echo e(datum($o['created_at'])); ?></td>
              <td data-label="Företag"><div><strong><?php echo e($o['foretag']); ?></strong><br><span class="muted" style="font-size:.85rem;"><?php echo e($o['kontakt']); ?></span></div></td>
              <td data-label="Leveransdag"><?php echo e($leveransdagar[$o['leveransdag']] ?? $o['leveransdag']); ?></td>
              <td data-label="Varugrupper" class="muted"><?php echo e(kategori_namn($o['kategorier'])); ?></td>
              <td data-label="Konto" class="muted"><?php echo $o['user_id'] ? 'Kund' : 'Gäst'; ?></td>
              <td data-label="Status"><?php echo status_pill($o['status']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($sidor > 1): ?>
        <div class="tabs" style="margin-top:16px;">
          <?php if ($sida > 1): ?><a href="<?php echo e(lank(['sida' => $sida - 1])); ?>">&larr; Nyare</a><?php endif; ?>
          <a class="on">Sida <?php echo $sida; ?> av <?php echo $sidor; ?></a>
          <?php if ($sida < $sidor): ?><a href="<?php echo e(lank(['sida' => $sida + 1])); ?>">Äldre &rarr;</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
