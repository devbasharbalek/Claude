<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('fakturera');
erp_schema();
$av = vem($user);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$f = faktura_hamta($id);
if (!$f) {
  http_response_code(404);
  exit('Fakturautkastet finns inte. Det kan ha tagits bort.');
}
if (!far_grupp($user, $f['kundgrupp'])) {
  http_response_code(403);
  exit('Utkastet hör till en kundgrupp du inte har behörighet till.');
}
$skickad = $f['status'] === 'skickad';
$ansluten = spiris_ansluten();
$fel = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'skicka') {
    $m = $ansluten ? faktura_skicka($id, $av) : 'Spiris är inte anslutet än. En administratör ansluter under Inställningar → Spiris.';
    flash($m === null ? utkast_namn($id) . ' är skickat till Spiris. Öppna Spiris för att göra det till en faktura.' : 'Utkastet skickades inte. ' . $m);
    redirect('admin-faktura?id=' . $id);
  }

  if ($action === 'ta_bort') {
    try {
      faktura_ta_bort($id, $av);
      flash(utkast_namn($id) . ' är borttaget' . ($skickad ? ', även i Spiris' : '') . '. Orderna kan faktureras igen.');
      redirect('admin-fakturering');
    } catch (SpirisFel $e) {
      flash($e->getMessage());
      redirect('admin-faktura?id=' . $id);
    }
  }

  if ($skickad) {
    flash('Utkastet är redan skickat till Spiris. Ändra det i Spiris i stället.');
    redirect('admin-faktura?id=' . $id);
  }

  if ($action === 'slapp') {
    $oid = (int)($_POST['order_id'] ?? 0);
    faktura_slapp_order($id, $oid, $av);
    if (!faktura_ordrar($id)) {
      db()->prepare('DELETE FROM fakturor WHERE id = ?')->execute([$id]);
      flash("Order #$oid är borttagen och utkastet hade inga order kvar, så det togs bort.");
      redirect('admin-fakturering');
    }
    faktura_rakna($id);
    flash("Order #$oid är borttagen från utkastet och kan faktureras igen.");
    redirect('admin-faktura?id=' . $id);
  }

  if ($action === 'spara') {
    $datum = giltigt_datum((string)($_POST['fakturadatum'] ?? ''));
    if ($datum === null) $fel[] = 'Fakturadatum ska vara ett datum.';
    $dagar = trim((string)($_POST['betalningsvillkor'] ?? ''));
    if ($dagar !== '' && (!ctype_digit($dagar) || (int)$dagar > 365)) $fel[] = 'Betalningsvillkor ska vara antal dagar, till exempel 30.';
    if (!$fel) {
      db()->prepare('UPDATE fakturor SET fakturadatum = ?, betalningsvillkor = ?, er_referens = ?, var_referens = ?, kundens_ordernr = ? WHERE id = ?')
          ->execute([$datum, $dagar === '' ? null : (int)$dagar, enRad($_POST['er_referens'] ?? '', 120), enRad($_POST['var_referens'] ?? '', 80),
                     enRad($_POST['kundens_ordernr'] ?? '', 60), $id]);
      flash('Utkastet är sparat.');
      redirect('admin-faktura?id=' . $id);
    }
    $f = array_merge($f, ['fakturadatum' => $_POST['fakturadatum'] ?? '', 'betalningsvillkor' => $dagar,
                          'er_referens' => $_POST['er_referens'] ?? '', 'var_referens' => $_POST['var_referens'] ?? '', 'kundens_ordernr' => $_POST['kundens_ordernr'] ?? '']);
  }
}

$kund = kund_hamta((int)$f['kund_id']);
$inn = faktura_innehall(faktura_hamta($id));
$datumOk = giltigt_datum((string)$f['fakturadatum']);
$forfaller = $datumOk && ctype_digit((string)$f['betalningsvillkor']) ? faktura_forfallodag(['fakturadatum' => $datumOk, 'betalningsvillkor' => (int)$f['betalningsvillkor']]) : null;
$avgiftUtan = (float)$f['fakturaavgift'] > 0 && faktura_avgift_artikel() === '';
$perOrder = [];
foreach ($inn['rader'] as $r) if (in_array($r['typ'], ['artikel'], true)) $perOrder[$r['order_id']] = ($perOrder[$r['order_id']] ?? 0) + $r['belopp'];

$active = 'admin';
$adminSida = 'fakturering';
$pageTitle = utkast_namn($id) . ' – Fakturering – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin-fakturering?visa=<?php echo $skickad ? 'skickade' : 'utkast'; ?>">&larr; Fakturering</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Fakturautkast</span>
        <h1><?php echo e(utkast_namn($id)); ?> <?php echo faktura_status_pill($f); ?></h1>
        <p><?php echo e($f['kundnr'] . ' ' . $f['kund_namn']); ?> &middot; skapat <?php echo e(datum($f['skapad_at'])); ?><?php echo $f['skapad_av'] !== '' ? ' av ' . e($f['skapad_av']) : ''; ?><?php if ($skickad): ?> &middot; skickat till Spiris <?php echo e(datum($f['skickad_at'])); ?><?php echo $f['skickad_av'] !== '' ? ' av ' . e($f['skickad_av']) : ''; ?><?php endif; ?></p>
      </div>
      <div class="portal-actions">
        <?php if (!$skickad): ?>
          <form method="post" action="admin-faktura" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn primary small" type="submit" name="action" value="skicka"<?php echo $ansluten ? '' : ' disabled title="Spiris är inte anslutet än"'; ?>>Skicka till Spiris</button></form>
        <?php endif; ?>
        <form method="post" action="admin-faktura" style="margin:0;" onsubmit="return confirm('<?php echo $skickad ? 'Ta bort utkastet här och i Spiris? Orderna kan sedan faktureras igen.' : 'Ta bort utkastet? Orderna kan sedan faktureras igen.'; ?>');"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn ghost small" type="submit" name="action" value="ta_bort">Ta bort utkastet</button></form>
      </div>
    </div>

    <?php if ($fel): ?><div class="alert error" role="alert"><strong>Utkastet sparades inte:</strong><ul><?php foreach ($fel as $m): ?><li><?php echo e($m); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$skickad && $f['fel']): ?><div class="alert error" role="alert"><strong>Senaste försöket att skicka misslyckades.</strong> <?php echo e($f['fel']); ?></div><?php endif; ?>
    <?php if (!$skickad && !$ansluten): ?><div class="alert warn">Spiris är inte anslutet än. Utkastet ligger kvar här tills det kan skickas.</div><?php endif; ?>
    <?php if ($skickad): ?><div class="alert info">Utkastet finns i Spiris under Fakturor → Utkast. Där gör ni det till en faktura, och då får det fakturanummer. Behöver något ändras gör ni det i Spiris.</div><?php endif; ?>

    <div class="order-topp">
      <div class="detail-card">
        <h2>Kund</h2>
        <dl class="kv">
          <dt>Kund</dt><dd><?php if (far($user, 'kunder')): ?><a href="admin-kund?id=<?php echo (int)$kund['id']; ?>" style="color:var(--brand);"><?php echo e($kund['kundnr'] . ' ' . $kund['namn']); ?></a><?php else: echo e($kund['kundnr'] . ' ' . $kund['namn']); endif; ?></dd>
          <dt>Fakturaadress</dt><dd><?php echo e(implode(', ', array_filter([$kund['fakt_namn'] ?: $kund['namn'], $kund['fakt_adress'] ?: $kund['lev_adress'], trim(($kund['fakt_postnr'] ?: $kund['lev_postnr']) . ' ' . ($kund['fakt_ort'] ?: $kund['lev_ort']))]))); ?></dd>
          <dt>Faktura-e-post</dt><dd><?php echo $kund['faktura_epost'] !== '' ? e($kund['faktura_epost']) : '<span class="muted">Ingen</span>'; ?></dd>
          <dt>Spiris</dt><dd><?php echo $kund['visma_id'] ? 'Kunden finns i Spiris' : 'Letas upp i Spiris med kundnummer ' . e($kund['kundnr']); ?></dd>
          <?php if ((float)$f['fakturaavgift'] > 0): ?><dt>Fakturaavgift</dt><dd><?php echo e(pris_text($f['fakturaavgift'])); ?> kr<?php echo $avgiftUtan ? ' <span class="muted">(läggs inte till här, se nedan)</span>' : ''; ?></dd><?php endif; ?>
        </dl>
      </div>

      <?php if ($skickad): ?>
        <div class="detail-card">
          <h2>Faktura</h2>
          <dl class="kv">
            <dt>Fakturadatum</dt><dd><?php echo e($f['fakturadatum']); ?></dd>
            <dt>Förfallodag</dt><dd><?php echo $forfaller ? e($forfaller) . ' (' . (int)$f['betalningsvillkor'] . ' dagar)' : 'Enligt kunden i Spiris'; ?></dd>
            <?php foreach (['er_referens' => 'Er referens', 'var_referens' => 'Vår referens', 'kundens_ordernr' => 'Er order'] as $k => $etikett): ?>
              <dt><?php echo $etikett; ?></dt><dd><?php echo $f[$k] !== '' ? e($f[$k]) : '<span class="tom">–</span>'; ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php else: ?>
        <form class="detail-card" method="post" action="admin-faktura">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="spara">
          <h2>Faktura</h2>
          <div class="field-grid">
            <div class="field"><label for="fakturadatum">Fakturadatum</label><input type="date" id="fakturadatum" name="fakturadatum" value="<?php echo e((string)$f['fakturadatum']); ?>" required></div>
            <div class="field"><label for="betalningsvillkor">Betalningsvillkor (dagar)</label><input type="text" inputmode="numeric" id="betalningsvillkor" name="betalningsvillkor" value="<?php echo e((string)$f['betalningsvillkor']); ?>" placeholder="Enligt Spiris"><small class="muted"><?php echo $forfaller ? 'Förfaller ' . e($forfaller) : 'Tomt: Spiris räknar ut förfallodagen'; ?></small></div>
            <div class="field"><label for="er_referens">Er referens</label><input type="text" id="er_referens" name="er_referens" value="<?php echo e($f['er_referens']); ?>"></div>
            <div class="field"><label for="var_referens">Vår referens</label><input type="text" id="var_referens" name="var_referens" value="<?php echo e($f['var_referens']); ?>"></div>
            <div class="field"><label for="kundens_ordernr">Er order (kundens ordernr)</label><input type="text" id="kundens_ordernr" name="kundens_ordernr" value="<?php echo e($f['kundens_ordernr']); ?>"></div>
          </div>
          <button class="btn primary small" type="submit" style="margin-top:14px;">Spara</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="detail-card" style="margin-top:20px;">
      <h2>Order på fakturan</h2>
      <div class="table-wrap">
        <table class="erp">
          <thead><tr><th>Order</th><th>Leverans</th><th>Er order / referens</th><th class="num">Exkl. moms</th><?php if (!$skickad): ?><th><span class="sr-only">Ta bort</span></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($inn['ordrar'] as $o): ?>
            <tr>
              <td class="nr"><a class="row-link" href="admin-order?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
              <td style="white-space:nowrap;"><?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></td>
              <td class="muted"><?php echo e(trim($o['kundens_ordernr'] . ($o['kundens_ordernr'] !== '' && $o['er_referens'] !== '' ? ' · ' : '') . $o['er_referens'])); ?></td>
              <td class="num"><?php echo e(pris_text($perOrder[(int)$o['id']] ?? 0)); ?></td>
              <?php if (!$skickad): ?>
                <td style="text-align:right;">
                  <form method="post" action="admin-faktura" style="margin:0;" onsubmit="return confirm('Ta bort order #<?php echo (int)$o['id']; ?> från utkastet? Den kan faktureras igen senare.');">
                    <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                    <button class="lank" type="submit" name="action" value="slapp">Ta bort från utkastet</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="detail-card" style="margin-top:20px;">
      <h2>Fakturarader</h2>
      <div class="table-wrap">
        <table class="erp fakt-rader">
          <thead><tr><th>Art.nr</th><th>Benämning</th><th class="num">Antal</th><th>Enhet</th><th class="num">À-pris</th><th class="num">Moms</th><th class="num">Belopp</th></tr></thead>
          <tbody>
          <?php foreach ($inn['rader'] as $r): ?>
            <?php if ($r['typ'] === 'rubrik' || $r['typ'] === 'text'): ?>
              <tr class="fakt-<?php echo $r['typ']; ?>"><td></td><td colspan="6"><?php echo e($r['text']); ?></td></tr>
            <?php else: ?>
              <tr>
                <td class="muted"><?php echo e($r['artikelnr']); ?></td>
                <td><?php echo e($r['text']); ?></td>
                <td class="num"><?php echo e(tal_text($r['antal'])); ?></td>
                <td><?php echo e($r['enhet']); ?></td>
                <td class="num"><?php echo e(pris_text($r['pris'])); ?></td>
                <td class="num"><?php echo e(tal_text($r['moms'])); ?> %</td>
                <td class="num"><?php echo e(pris_text($r['belopp'])); ?></td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="summor">
        <div><span>Exkl. moms</span><b><?php echo e(pris_text($inn['netto'])); ?> kr</b></div>
        <?php foreach ($inn['moms'] as $sats => $belopp): ?><div><span>Moms <?php echo e(tal_text($sats)); ?> %</span><b><?php echo e(pris_text($belopp)); ?> kr</b></div><?php endforeach; ?>
        <div><span>Att betala</span><b><?php echo e(pris_text($inn['totalt'])); ?> kr</b></div>
      </div>
      <p class="muted" style="font-size:.86rem; margin:12px 0 0;">Spiris sätter fakturanumret när utkastet blir en faktura. Eventuell öresavrundning görs också där.
        <?php if ($avgiftUtan): ?>Kunden har fakturaavgift <?php echo e(pris_text($f['fakturaavgift'])); ?> kr i kundregistret. Den läggs inte till här, eftersom ingen artikel för fakturaavgift är vald under Inställningar → Spiris.<?php endif; ?></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
