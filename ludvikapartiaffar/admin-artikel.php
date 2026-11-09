<?php
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/import.php';
$user = require_behorighet('artiklar');
erp_schema();
$kanPriser = far($user, 'priser');
$av = vem($user);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$artikel = $id ? artikel_hamta($id) : null;
if ($id && !$artikel) {
  http_response_code(404);
  exit('Artikeln finns inte.');
}
$ny = !$artikel;

$fel = [];
$v = $artikel ?? array_merge(ARTIKEL_STANDARD, ['artikelnr' => '', 'aktiv' => 1]);
$gp = $artikel ? grupppriser($id) : array_fill_keys(array_keys(PRISGRUPPER), null);
$prisV = ['inpris' => pris_text($v['inpris']), 'utpris' => pris_text($v['utpris'])];
foreach (PRISGRUPPER as $g => $_) $prisV['g' . $g] = pris_text($gp[$g]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'kundpris' && $artikel) {
    if (!$kanPriser) {
      http_response_code(403);
      exit('Du har inte behörighet att ändra priser.');
    }
    $k = kund_via_nr(enRad($_POST['kundnr'] ?? '', 40));
    $pris = pris_ur_text((string)($_POST['pris'] ?? ''));
    if (!$k) flash('Kundnumret finns inte i kundregistret.');
    elseif ($pris === false || $pris === null) flash('Skriv ett pris, till exempel 129,95.');
    else {
      satt_kundpris((int)$k['id'], $id, $pris, $av);
      flash('Kundpriset för ' . $k['namn'] . ' är ' . pris_text($pris) . ' kr.');
    }
    redirect("admin-artikel.php?id=$id#kundpriser");
  }

  if ($action === 'kundpris_bort' && $artikel) {
    if (!$kanPriser) {
      http_response_code(403);
      exit('Du har inte behörighet att ändra priser.');
    }
    $k = kund_hamta((int)($_POST['kund_id'] ?? 0));
    if ($k && satt_kundpris((int)$k['id'], $id, null, $av)) flash('Kundpriset för ' . $k['namn'] . ' är borttaget.');
    redirect("admin-artikel.php?id=$id#kundpriser");
  }

  if ($action === 'spara') {
    $v['artikelnr'] = $ny ? enRad($_POST['artikelnr'] ?? '', 40) : $artikel['artikelnr'];
    foreach (['namn' => 200, 'produktgrupp' => 60, 'artikelgrupp' => 20, 'ursprung' => 40, 'gtin' => 20, 'fakturatext' => 255, 'kommentar' => 255] as $k => $max) {
      $v[$k] = enRad($_POST[$k] ?? '', $max);
    }
    $v['enhet'] = mb_strtolower(enRad($_POST['enhet'] ?? '', 12), 'UTF-8');
    foreach (['vikt', 'antal_per_kolli'] as $k) {
      $t = tal_ur_text((string)($_POST[$k] ?? ''));
      if ($t === false || ($t !== null && ($t < 0 || $t > 999999))) $fel[$k] = 'Skriv ett tal, till exempel 4,8.';
      else $v[$k] = $t === null ? null : round($t, 3);
    }
    $moms = (float)($_POST['moms'] ?? -1);
    if (!in_array($moms, array_map('floatval', MOMSSATSER), true)) $fel['moms'] = 'Välj momssats.';
    else $v['moms'] = $moms;
    foreach (['lagerartikel', 'hel_forpackning', 'losvikt', 'viktartikel', 'webbshop', 'aktiv'] as $k) $v[$k] = !empty($_POST[$k]) ? 1 : 0;

    if ($v['artikelnr'] === '') $fel['artikelnr'] = 'Ange artikelnummer.';
    elseif ($ny && artikel_via_nr($v['artikelnr'])) $fel['artikelnr'] = 'Det finns redan en artikel med det numret.';
    if ($v['namn'] === '') $fel['namn'] = 'Ange artikelns namn.';
    if ($v['enhet'] === '') $fel['enhet'] = 'Ange enhet.';

    $priser = [];
    if ($kanPriser) {
      foreach (array_keys($prisV) as $k) {
        $prisV[$k] = enRad($_POST[$k] ?? '', 20);
        $p = pris_ur_text($prisV[$k]);
        if ($p === false) $fel[$k] = 'Skriv ett pris, till exempel 129,95.';
        else $priser[$k] = $p;
      }
    }

    if (!$fel) {
      $falt = array_keys(ARTIKEL_STANDARD);
      $falt = array_values(array_diff($falt, ['inpris', 'utpris', 'visma_id', 'visma_kontering_id', 'visma_enhet_id']));
      db()->beginTransaction();
      if ($ny) {
        db()->prepare('INSERT INTO artiklar (artikelnr, ' . implode(', ', $falt) . ', aktiv, skapad, uppdaterad, uppdaterad_av)
                       VALUES (?' . str_repeat(', ?', count($falt)) . ', ?, ?, ?, ?)')
            ->execute(array_merge([$v['artikelnr']], array_map(fn($k) => $v[$k], $falt), [$v['aktiv'], now(), now(), $av]));
        $id = (int)db()->lastInsertId();
      } else {
        $falt[] = 'aktiv';
        $andrade = array_filter($falt, fn($k) => !import_lika(ARTIKEL_FALT_TYP[$k] ?? ['bool'], $artikel[$k], $v[$k]));
        if ($andrade) {
          db()->prepare('UPDATE artiklar SET ' . implode(', ', array_map(fn($k) => "$k = ?", $andrade)) . ', uppdaterad = ?, uppdaterad_av = ? WHERE id = ?')
              ->execute(array_merge(array_map(fn($k) => $v[$k], array_values($andrade)), [now(), $av, $id]));
        }
      }
      foreach ($priser as $k => $p) {
        if ($k === 'inpris' || $k === 'utpris') satt_artikelpris($id, $k, $p, $av);
        else satt_grupppris($id, (int)substr($k, 1), $p, $av);
      }
      db()->commit();
      flash($ny ? 'Artikeln ' . $v['namn'] . ' är upplagd.' : 'Artikeln är sparad.');
      redirect("admin-artikel.php?id=$id");
    }
  }
}

$kundpriser = [];
$historik = [];
if ($artikel) {
  $st = db()->prepare('SELECT k.id, k.kundnr, k.namn, k.prisgrupp, kp.pris, kp.uppdaterad FROM kund_priser kp JOIN kunder k ON k.id = kp.kund_id
                       WHERE kp.artikel_id = ? ORDER BY k.namn');
  $st->execute([$id]);
  $kundpriser = $st->fetchAll();
  $st = db()->prepare('SELECT l.*, k.namn AS kund_namn FROM prislogg l LEFT JOIN kunder k ON k.id = l.kund_id WHERE l.artikel_id = ? ORDER BY l.tid DESC, l.id DESC LIMIT 25');
  $st->execute([$id]);
  $historik = $st->fetchAll();
}
$produktgrupper = db()->query("SELECT DISTINCT produktgrupp FROM artiklar WHERE produktgrupp <> '' ORDER BY produktgrupp")->fetchAll(PDO::FETCH_COLUMN);
$artikelgrupper = db()->query("SELECT DISTINCT artikelgrupp FROM artiklar WHERE artikelgrupp <> '' ORDER BY artikelgrupp")->fetchAll(PDO::FETCH_COLUMN);
$enheter = array_unique(array_merge(ENHETER, db()->query("SELECT DISTINCT enhet FROM artiklar")->fetchAll(PDO::FETCH_COLUMN)));

$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
$ro = $kanPriser ? '' : ' readonly';
$inpris = pris_ur_text($prisV['inpris']) ?: null;
$marg = function (string $k) use ($prisV, $inpris) {
  $p = pris_ur_text($prisV[$k]) ?: null;
  $m = marginal($inpris, $p);
  return $m === null ? '' : '<span class="marg">TG ' . e(str_replace('.', ',', (string)$m)) . ' %</span>';
};
$loggText = function (array $l): string {
  $vad = match ($l['typ']) {
    'inpris' => 'Inpris',
    'utpris' => 'Grundpris',
    'grupp' => prisgrupp_text((int)$l['prisgrupp']),
    'kund' => 'Kundpris ' . ($l['kund_namn'] ?? ''),
    default => $l['typ'],
  };
  $fore = $l['fore'] === null ? '–' : pris_text($l['fore']);
  $efter = $l['efter'] === null ? 'borttaget' : pris_text($l['efter']);
  return $vad . ': ' . $fore . ' → ' . $efter;
};

$active = 'admin';
$adminSida = 'artiklar';
$pageTitle = ($ny ? 'Ny artikel' : $artikel['namn']) . ' – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin-artiklar.php">&larr; Artiklar och priser</a>
    <div class="portal-head">
      <div>
        <h1><?php echo e($ny ? 'Ny artikel' : $artikel['namn']); ?></h1>
        <?php if (!$ny): ?><p>Art.nr <?php echo e($artikel['artikelnr']); ?> &middot; <?php echo e($artikel['produktgrupp'] ?: 'Ingen produktgrupp'); ?><?php echo (int)$artikel['aktiv'] ? '' : ' &middot; <strong>Inaktiv</strong>'; ?></p><?php endif; ?>
      </div>
    </div>
    <?php if ($fel): ?><div class="alert error" role="alert">Artikeln sparades inte, se markeringarna nedan.</div><?php endif; ?>

    <div class="detail-grid">
      <form method="post" action="admin-artikel.php" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="spara">
        <?php if (!$ny): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
        <div class="detail-card">
          <h2>Artikel</h2>
          <div class="field-grid">
            <div class="field"><label for="artikelnr">Artikelnummer</label><input type="text" id="artikelnr" name="artikelnr" value="<?php echo e($v['artikelnr']); ?>"<?php echo $ny ? ' required autofocus' : ' readonly'; ?>><?php echo $err('artikelnr'); ?></div>
            <div class="field"><label for="namn">Namn</label><input type="text" id="namn" name="namn" value="<?php echo e($v['namn']); ?>" required><?php echo $err('namn'); ?></div>
          </div>
          <div class="field-grid fyra" style="margin-top:14px;">
            <div class="field"><label for="enhet">Enhet</label><select id="enhet" name="enhet"><?php foreach ($enheter as $en): ?><option<?php echo $v['enhet'] === $en ? ' selected' : ''; ?>><?php echo e($en); ?></option><?php endforeach; ?></select><?php echo $err('enhet'); ?></div>
            <div class="field"><label for="vikt">Vikt per kolli (kg)</label><input type="text" inputmode="decimal" id="vikt" name="vikt" value="<?php echo e(tal_text($v['vikt'])); ?>"><?php echo $err('vikt'); ?></div>
            <div class="field"><label for="antal_per_kolli">Antal per kolli</label><input type="text" inputmode="decimal" id="antal_per_kolli" name="antal_per_kolli" value="<?php echo e(tal_text($v['antal_per_kolli'])); ?>"><?php echo $err('antal_per_kolli'); ?></div>
            <div class="field"><label for="moms">Moms</label><select id="moms" name="moms"><?php foreach (MOMSSATSER as $m): ?><option value="<?php echo $m; ?>"<?php echo (float)$v['moms'] === (float)$m ? ' selected' : ''; ?>><?php echo $m; ?> %</option><?php endforeach; ?></select><?php echo $err('moms'); ?></div>
            <div class="field"><label for="produktgrupp">Produktgrupp</label><input type="text" id="produktgrupp" name="produktgrupp" list="pgLista" value="<?php echo e($v['produktgrupp']); ?>"></div>
            <div class="field"><label for="artikelgrupp">Artikelgrupp</label><input type="text" id="artikelgrupp" name="artikelgrupp" list="agLista" value="<?php echo e($v['artikelgrupp']); ?>"></div>
            <div class="field"><label for="ursprung">Ursprung</label><input type="text" id="ursprung" name="ursprung" value="<?php echo e($v['ursprung']); ?>" placeholder="T.ex. SE"></div>
            <div class="field"><label for="gtin">EAN</label><input type="text" id="gtin" name="gtin" value="<?php echo e($v['gtin']); ?>" inputmode="numeric"></div>
          </div>
          <datalist id="pgLista"><?php foreach ($produktgrupper as $g): ?><option value="<?php echo e($g); ?>"><?php endforeach; ?></datalist>
          <datalist id="agLista"><?php foreach ($artikelgrupper as $g): ?><option value="<?php echo e($g); ?>"><?php endforeach; ?></datalist>
          <div class="field-grid" style="margin-top:14px;">
            <div class="field"><label for="fakturatext">Fakturatext</label><input type="text" id="fakturatext" name="fakturatext" value="<?php echo e($v['fakturatext']); ?>" placeholder="T.ex. En förpackning innehåller: 5 KG"></div>
            <div class="field"><label for="kommentar">Kommentar</label><input type="text" id="kommentar" name="kommentar" value="<?php echo e($v['kommentar']); ?>"></div>
          </div>
          <div class="flaggor" style="margin-top:16px;">
            <?php foreach (['aktiv' => 'Aktiv', 'webbshop' => 'Visas i webbshop', 'hel_forpackning' => 'Säljs i hel förpackning', 'viktartikel' => 'Viktartikel', 'losvikt' => 'Lösvikt', 'lagerartikel' => 'Lagerartikel'] as $k => $text): ?>
              <label class="check"><input type="checkbox" name="<?php echo $k; ?>" value="1"<?php echo (int)$v[$k] ? ' checked' : ''; ?>> <?php echo e($text); ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="detail-card">
          <h2>Priser <span class="h2-text">exkl. moms<?php echo $kanPriser ? '' : ', bara läsbehörighet'; ?></span></h2>
          <div class="field-grid fyra">
            <div class="field"><label for="inpris">Inpris</label><input type="text" inputmode="decimal" id="inpris" name="inpris" value="<?php echo e($prisV['inpris']); ?>"<?php echo $ro; ?>><?php echo $err('inpris'); ?></div>
            <div class="field"><label for="utpris">Grundpris</label><input type="text" inputmode="decimal" id="utpris" name="utpris" value="<?php echo e($prisV['utpris']); ?>"<?php echo $ro; ?>><?php echo $marg('utpris'); ?><?php echo $err('utpris'); ?></div>
          </div>
          <div class="field-grid tre" style="margin-top:14px;">
            <?php foreach (PRISGRUPPER as $g => $pg): ?>
              <div class="field"><label for="g<?php echo $g; ?>"><?php echo e($pg['namn']); ?> <span class="hint"><?php echo e($pg['for']); ?></span></label>
                <input type="text" inputmode="decimal" id="g<?php echo $g; ?>" name="g<?php echo $g; ?>" value="<?php echo e($prisV['g' . $g]); ?>" placeholder="<?php echo e($prisV['utpris'] !== '' ? 'Grundpris ' . $prisV['utpris'] : 'Grundpris'); ?>"<?php echo $ro; ?>><?php echo $marg('g' . $g); ?><?php echo $err('g' . $g); ?></div>
            <?php endforeach; ?>
          </div>
          <p class="form-note" style="margin-top:12px;">En tom grupp betalar grundpriset. TG är täckningsgraden mot inpriset.</p>
        </div>
        <div class="spara-rad"><button class="btn primary" type="submit"><?php echo $ny ? 'Lägg upp artikeln' : 'Spara artikeln'; ?></button><a class="btn ghost" href="admin-artiklar.php">Avbryt</a></div>
      </form>

      <?php if (!$ny): ?>
      <div>
        <div class="detail-card" id="kundpriser">
          <h2>Kundpriser <span class="h2-text">(<?php echo count($kundpriser); ?>)</span></h2>
          <?php if (!$kundpriser): ?>
            <p class="form-note">Ingen kund har eget pris på artikeln.</p>
          <?php else: ?>
            <ul class="logg">
              <?php foreach ($kundpriser as $kp): ?>
                <li>
                  <span><a class="row-link" style="color:var(--brand); text-decoration:none; font-weight:700;" href="admin-kund.php?id=<?php echo (int)$kp['id']; ?>"><?php echo e($kp['namn']); ?></a><br><span class="muted" style="font-size:.82rem;"><?php echo e($kp['kundnr']); ?> &middot; annars <?php echo e(prisgrupp_text($kp['prisgrupp'] === null ? null : (int)$kp['prisgrupp'])); ?></span></span>
                  <span style="display:flex; gap:8px; align-items:center;"><strong><?php echo e(pris_text($kp['pris'])); ?></strong>
                  <?php if ($kanPriser): ?>
                    <form method="post" action="admin-artikel.php" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="kund_id" value="<?php echo (int)$kp['id']; ?>"><button class="btn ghost small" type="submit" name="action" value="kundpris_bort" aria-label="Ta bort kundpriset för <?php echo e($kp['namn']); ?>">&times;</button></form>
                  <?php endif; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($kanPriser): ?>
            <form class="liten-form" method="post" action="admin-artikel.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <div class="field"><label for="kp_kundnr">Kundnr</label><input type="text" id="kp_kundnr" name="kundnr" required></div>
              <div class="field"><label for="kp_pris">Pris</label><input type="text" inputmode="decimal" id="kp_pris" name="pris" required></div>
              <button class="btn ghost small" type="submit" name="action" value="kundpris">Sätt kundpris</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="detail-card">
          <h2>Prishistorik</h2>
          <?php if (!$historik): ?>
            <p class="form-note">Inga prisändringar än.</p>
          <?php else: ?>
            <ul class="logg">
              <?php foreach ($historik as $l): ?>
                <li><span><?php echo e($loggText($l)); ?><br><span class="muted" style="font-size:.8rem;"><?php echo e($l['av'] . ($l['kalla'] === 'import' ? ', import' : '')); ?></span></span><span class="tid"><?php echo e(datum($l['tid'])); ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="detail-card">
          <h2>Visma</h2>
          <dl class="kv">
            <dt>Artikel i Visma</dt><dd><?php echo $artikel['visma_id'] ? 'Kopplad' : 'Inte kopplad än'; ?></dd>
            <dt>Ändrad</dt><dd><?php echo e(datum($artikel['uppdaterad']) . ($artikel['uppdaterad_av'] !== '' ? ' av ' . $artikel['uppdaterad_av'] : '')); ?></dd>
          </dl>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
