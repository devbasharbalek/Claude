<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('kunder');
erp_schema();

$f = [
  'sok' => enRad($_GET['sok'] ?? '', 80),
  'grupp' => isset(REGISTER_KUNDGRUPPER[$_GET['grupp'] ?? '']) ? $_GET['grupp'] : '',
  'pg' => in_array($_GET['pg'] ?? '', ['1', '2', '3', 'ingen'], true) ? $_GET['pg'] : '',
  'status' => in_array($_GET['status'] ?? '', ['inaktiva', 'alla'], true) ? $_GET['status'] : '',
];

$where = [];
$params = [];
if ($f['status'] === '') $where[] = 'k.aktiv = 1';
elseif ($f['status'] === 'inaktiva') $where[] = 'k.aktiv = 0';
if ($f['pg'] === 'ingen') $where[] = 'k.prisgrupp IS NULL';
elseif ($f['pg'] !== '') {
  $where[] = 'k.prisgrupp = ?';
  $params[] = (int)$f['pg'];
}
// Varje ord ska finnas någonstans: namn, kundnummer, org.nr, ort, huvudkund, telefon eller e-post.
foreach (array_slice(preg_split('/\s+/u', $f['sok'], -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $ord) {
  $esc = '%' . addcslashes($ord, '%_\\') . '%';
  $where[] = '(k.namn LIKE ? OR k.lev_namn LIKE ? OR k.kundnr LIKE ? OR k.orgnr LIKE ? OR k.lev_ort LIKE ? OR k.moderbolag LIKE ? OR k.telefon LIKE ? OR k.foljesedel_epost LIKE ? OR k.faktura_epost LIKE ?)';
  array_push($params, $esc, $esc, addcslashes($ord, '%_\\') . '%', $esc, $esc, $esc, $esc, $esc, $esc);
}
$basWhere = $where;
$basParams = $params;
if ($f['grupp'] !== '') {
  $where[] = 'k.kundgrupp = ?';
  $params[] = $f['grupp'];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Antal per kundgrupp med samma sökning, till flikarna.
$st = db()->prepare('SELECT k.kundgrupp, COUNT(*) AS n FROM kunder k ' . ($basWhere ? 'WHERE ' . implode(' AND ', $basWhere) : '') . ' GROUP BY k.kundgrupp');
$st->execute($basParams);
$perGrupp = [];
foreach ($st as $r) $perGrupp[(string)$r['kundgrupp']] = (int)$r['n'];

$st = db()->prepare("SELECT COUNT(*) FROM kunder k $whereSql");
$st->execute($params);
$totalt = (int)$st->fetchColumn();
$perSida = 50;
$sidor = max(1, (int)ceil($totalt / $perSida));
$sida = min(max(1, (int)($_GET['sida'] ?? 1)), $sidor);
$st = db()->prepare("SELECT k.*,
                       (SELECT COUNT(*) FROM users u WHERE u.kund_id = k.id) AS webbkonton,
                       (SELECT COUNT(*) FROM kund_priser kp WHERE kp.kund_id = k.id) AS kundpriser
                     FROM kunder k $whereSql ORDER BY k.namn, k.kundnr LIMIT $perSida OFFSET " . (($sida - 1) * $perSida));
$st->execute($params);
$kunder = $st->fetchAll();
$registretTomt = !$totalt && !(int)db()->query('SELECT COUNT(*) FROM kunder')->fetchColumn();

$active = 'admin';
$adminSida = 'kunder';
$pageTitle = 'Kunder – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <h1>Kunder</h1>
        <p>Kundregistret med leverans- och fakturauppgifter, kundgrupp och prisgrupp.</p>
      </div>
      <div class="portal-actions"><a class="btn primary small" href="admin-kund.php?ny=1">Ny kund</a></div>
    </div>

    <?php if ($registretTomt): ?>
      <div class="detail-card tomt-register">
        <h2>Kundregistret är tomt</h2>
        <p>Läs in kunderna från Partihandel, eller lägg upp dem en och en.</p>
        <?php if (far($user, 'import')): ?><a class="btn primary" href="admin-import.php">Importera kunder</a><?php else: ?><p>Be en administratör att importera kunderna.</p><?php endif; ?>
      </div>
    <?php else: ?>

    <div class="tabs">
      <a href="<?php echo e(adm_lank('admin-kundregister.php', $f, ['grupp' => ''])); ?>"<?php echo $f['grupp'] === '' ? ' class="on"' : ''; ?>>Alla<span class="count"><?php echo array_sum($perGrupp); ?></span></a>
      <?php foreach (REGISTER_KUNDGRUPPER as $g => $namn): if (!isset($perGrupp[$g]) && $f['grupp'] !== $g) continue; ?>
        <a href="<?php echo e(adm_lank('admin-kundregister.php', $f, ['grupp' => $g])); ?>"<?php echo $f['grupp'] === $g ? ' class="on"' : ''; ?>><?php echo e($g . ' ' . explode(' / ', $namn)[0]); ?><span class="count"><?php echo $perGrupp[$g] ?? 0; ?></span></a>
      <?php endforeach; ?>
    </div>

    <form class="verktyg" method="get" action="admin-kundregister.php">
      <?php if ($f['grupp'] !== ''): ?><input type="hidden" name="grupp" value="<?php echo e($f['grupp']); ?>"><?php endif; ?>
      <input type="search" name="sok" value="<?php echo e($f['sok']); ?>" placeholder="Sök namn, kundnr, ort, org.nr eller e-post" aria-label="Sök kunder">
      <select name="pg" aria-label="Prisgrupp">
        <option value="">Alla prisgrupper</option>
        <?php foreach (PRISGRUPPER as $g => $pg): ?><option value="<?php echo $g; ?>"<?php echo $f['pg'] === (string)$g ? ' selected' : ''; ?>><?php echo e($pg['namn'] . ' – ' . $pg['for']); ?></option><?php endforeach; ?>
        <option value="ingen"<?php echo $f['pg'] === 'ingen' ? ' selected' : ''; ?>>Ingen (grundpris)</option>
      </select>
      <select name="status" aria-label="Status">
        <option value="">Aktiva</option>
        <option value="inaktiva"<?php echo $f['status'] === 'inaktiva' ? ' selected' : ''; ?>>Inaktiva</option>
        <option value="alla"<?php echo $f['status'] === 'alla' ? ' selected' : ''; ?>>Alla</option>
      </select>
      <button class="btn ghost small" type="submit">Visa</button>
      <?php if ($f['sok'] !== '' || $f['pg'] !== '' || $f['status'] !== ''): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin-kundregister.php', ['grupp' => $f['grupp']])); ?>">Rensa</a><?php endif; ?>
    </form>
    <p class="rad-info"><?php echo number_format($totalt, 0, ',', ' '); ?> kunder<?php echo $sidor > 1 ? ', sida ' . $sida . ' av ' . $sidor : ''; ?>.</p>

    <?php if (!$kunder): ?>
      <div class="empty"><p>Inga kunder matchar sökningen.</p></div>
    <?php else: ?>
      <div class="table-wrap bred">
        <table class="erp">
          <thead><tr><th>Kundnr</th><th>Namn</th><th>Grupp</th><th>Prisgrupp</th><th>Ort</th><th>Telefon</th><th class="num">Kundpriser</th><th>Webbkonto</th></tr></thead>
          <tbody>
          <?php foreach ($kunder as $k): ?>
            <tr<?php echo (int)$k['aktiv'] ? '' : ' class="inaktiv"'; ?>>
              <td class="nr"><?php echo e($k['kundnr']); ?></td>
              <td><a class="row-link" href="admin-kund.php?id=<?php echo (int)$k['id']; ?>"><?php echo e($k['namn']); ?></a>
                <?php if ($k['lev_namn'] !== '' && mb_strtolower($k['lev_namn']) !== mb_strtolower($k['namn'])): ?><br><span class="muted" style="font-size:.84rem;"><?php echo e($k['lev_namn']); ?></span><?php endif; ?></td>
              <td><?php if ($k['kundgrupp'] !== null): ?><span class="pill pill-grupp liten" title="<?php echo e(REGISTER_KUNDGRUPPER[$k['kundgrupp']] ?? ''); ?>" style="margin-left:0;"><?php echo e($k['kundgrupp']); ?></span><?php endif; ?></td>
              <td class="<?php echo $k['prisgrupp'] === null ? 'tom' : ''; ?>"><?php echo e($k['prisgrupp'] === null ? 'Grundpris' : prisgrupp_text((int)$k['prisgrupp'])); ?></td>
              <td class="muted"><?php echo e($k['lev_ort']); ?></td>
              <td class="muted" style="white-space:nowrap;"><?php echo e($k['telefon']); ?></td>
              <td class="num<?php echo (int)$k['kundpriser'] ? '' : ' tom'; ?>"><?php echo (int)$k['kundpriser'] ?: '–'; ?></td>
              <td><?php echo (int)$k['webbkonton'] ? '<span class="pill pill-levererad liten">Ja</span>' : '<span class="tom">–</span>'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php echo sida_navigering('admin-kundregister.php', $f, $sida, $sidor); ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
