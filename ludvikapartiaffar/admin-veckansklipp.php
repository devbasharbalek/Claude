<?php
require __DIR__ . '/includes/app.php';
require_admin();
veckansklipp_schema();
vk_skapa_kortlank();

[$maxFil, $maxPost] = vk_servergranser();
[$stdAr, $stdVecka] = vk_standard_vecka();
$f = ['vecka' => $stdVecka, 'ar' => $stdAr];
$fel = [];
$uppladdningsVaror = null;   // varorna i uppladdningsformuläret efter ett fel
$redigeradeVaror = null;     // varorna i listan för ett publicerat blad efter ett fel

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // PHP kastar hela uppladdningen om den är större än post_max_size.
    $fel[] = 'Uppladdningen var större än servern tar emot (högst ' . vk_mb($maxPost) . '). Försök igen från en dator, då skickas bara bilderna om PDF:en är för stor.';
  } else {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'radera') {
      $vk = vk_hamta((int)($_POST['id'] ?? 0));
      if ($vk) {
        db()->prepare('DELETE FROM veckansklipp WHERE id = ?')->execute([$vk['id']]);
        vk_radera_filer($vk);
        flash(vk_etikett($vk) . ' är borttaget. Beställningar som redan kommit in finns kvar.');
      }
      redirect('admin-veckansklipp.php');
    }

    if ($action === 'spara_varor') {
      $vk = vk_hamta((int)($_POST['id'] ?? 0));
      if ($vk) {
        [$varor, $varorFel] = vk_rader_fran_post();
        if ($varorFel) {
          $fel = array_values($varorFel);
          $redigeradeVaror = $varor;
          $_GET['id'] = $vk['id'];
        } else {
          vk_spara_rader((int)$vk['id'], $varor);
          flash($varor ? 'Varorna är sparade (' . count($varor) . ' st).' : 'Varulistan är tömd. Kunderna kan bara se bladet.');
          redirect('admin-veckansklipp.php?id=' . (int)$vk['id']);
        }
      }
    }

    if ($action === 'ladda_upp') {
      $f['vecka'] = (int)($_POST['vecka'] ?? 0);
      $f['ar'] = (int)($_POST['ar'] ?? 0);
      if ($f['ar'] < 2020 || $f['ar'] > 2100 || $f['vecka'] < 1 || $f['vecka'] > 53) {
        $fel[] = 'Ange vecka och år.';
      } elseif ((int)sk_veckodag($f['ar'], $f['vecka'], 1)->format('W') !== $f['vecka']) {
        $fel[] = "År {$f['ar']} har ingen vecka {$f['vecka']}.";
      }

      [$varor, $varorFel] = vk_rader_fran_post();
      $fel = array_merge($fel, array_values($varorFel));

      // Sidorna som bilder, gjorda i webbläsaren.
      $bilder = [];
      $s = $_FILES['sidor'] ?? null;
      if (is_array($s) && is_array($s['error'] ?? null)) {
        foreach ($s['error'] as $i => $err) {
          if ($err === UPLOAD_ERR_NO_FILE) continue;
          if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($s['tmp_name'][$i])) {
            $fel[] = 'En av sidbilderna kom inte fram. Försök igen.';
            break;
          }
          $info = @getimagesize($s['tmp_name'][$i]);
          if ($s['size'][$i] > VK_MAX_BILD || !$info || $info[2] !== IMAGETYPE_JPEG
              || $info[0] < 200 || $info[0] > 4000 || $info[1] < 200 || $info[1] > 6000) {
            $fel[] = 'Sidbilderna kunde inte läsas. Välj PDF:en igen.';
            break;
          }
          $bilder[] = ['tmp' => $s['tmp_name'][$i], 'b' => $info[0], 'h' => $info[1]];
        }
      }
      if (count($bilder) > VK_MAX_SIDOR) $fel[] = 'Bladet får ha högst ' . VK_MAX_SIDOR . ' sidor.';

      // PDF:en för nedladdning. Behövs inte om bilderna finns och PDF:en är för stor för servern.
      $pdf = null;
      $pdfForStor = false;
      $p = $_FILES['pdf'] ?? null;
      $pdfFel = is_array($p) ? (int)($p['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
      if ($pdfFel === UPLOAD_ERR_INI_SIZE || $pdfFel === UPLOAD_ERR_FORM_SIZE || ($pdfFel === UPLOAD_ERR_OK && $p['size'] > VK_MAX_PDF)) {
        $pdfForStor = true;
        if (!$bilder) $fel[] = 'PDF:en är för stor för servern (högst ' . vk_mb(min($maxFil, VK_MAX_PDF)) . '). Spara en mindre PDF i Canva, eller ladda upp från en dator så skickas bara bilderna.';
      } elseif ($pdfFel === UPLOAD_ERR_OK) {
        if (!is_uploaded_file($p['tmp_name'])) {
          $fel[] = 'PDF:en kom inte fram. Försök igen.';
        } elseif (file_get_contents($p['tmp_name'], false, null, 0, 5) !== '%PDF-') {
          $fel[] = 'Filen är ingen PDF. Ladda ner bladet från Canva som PDF.';
        } else {
          $pdf = $p;
        }
      } elseif ($pdfFel !== UPLOAD_ERR_NO_FILE) {
        $fel[] = 'PDF:en kom inte fram. Försök igen.';
      }
      if (!$fel && !$bilder && !$pdf) $fel[] = 'Välj veckans blad som PDF.';

      if (!$fel && !vk_skapa_mapp()) {
        $fel[] = 'Filerna kunde inte sparas på servern: mappen uploads/veckansklipp går inte att skriva i.';
      }

      if (!$fel) {
        $nyckel = bin2hex(random_bytes(8));
        $ok = true;
        foreach ($bilder as $i => $b) {
          $ok = $ok && move_uploaded_file($b['tmp'], vk_fil($nyckel, $i + 1));
        }
        if ($pdf) $ok = $ok && move_uploaded_file($pdf['tmp_name'], vk_fil($nyckel, 'pdf'));

        if (!$ok) {
          vk_radera_filer(['nyckel' => $nyckel]);
          $fel[] = 'Filerna kunde inte sparas på servern. Försök igen.';
        } else {
          $varden = [$nyckel, count($bilder), $bilder[0]['b'] ?? 0, $bilder[0]['h'] ?? 0, $pdf ? (int)$pdf['size'] : 0, now()];
          $st = db()->prepare('SELECT * FROM veckansklipp WHERE ar = ? AND vecka = ?');
          $st->execute([$f['ar'], $f['vecka']]);
          $gammal = $st->fetch() ?: null;
          $pdo = db();
          $pdo->beginTransaction();
          if ($gammal) {
            $pdo->prepare('UPDATE veckansklipp SET nyckel = ?, sidor = ?, bild_b = ?, bild_h = ?, pdf_storlek = ?, created_at = ? WHERE id = ?')
                ->execute([...$varden, $gammal['id']]);
            $id = (int)$gammal['id'];
          } else {
            $pdo->prepare('INSERT INTO veckansklipp (nyckel, sidor, bild_b, bild_h, pdf_storlek, created_at, vecka, ar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([...$varden, $f['vecka'], $f['ar']]);
            $id = (int)$pdo->lastInsertId();
          }
          vk_spara_rader($id, $varor);
          $pdo->commit();
          if ($gammal) vk_radera_filer($gammal);
          flash('Veckansklipp v.' . $f['vecka'] . ($gammal ? ' är ersatt med det nya bladet.' : ' är publicerat.')
              . ($varor ? ' Kunderna kan beställa ' . count($varor) . ' varor.' : ' Inga varor att beställa, kunderna ser bara bladet.')
              . ($pdfForStor ? ' PDF:en var för stor för servern, så bara bilderna sparades.' : ''));
          redirect('admin-veckansklipp.php?id=' . $id);
        }
      }
      $uppladdningsVaror = $varor;
    }
  }
}

$visad = isset($_GET['id']) ? vk_hamta((int)$_GET['id']) : vk_senaste();
$alla = db()->query('SELECT v.*, (SELECT COUNT(*) FROM veckansklipp_rader r WHERE r.veckansklipp_id = v.id) AS varor,
    (SELECT COUNT(*) FROM order_superklipp os JOIN orders o ON o.id = os.order_id WHERE os.veckansklipp_id = v.id AND o.status <> \'avbruten\') AS bestallningar
  FROM veckansklipp v ORDER BY v.ar DESC, v.vecka DESC LIMIT 60')->fetchAll();

$plocklista = $bestallningar = [];
if ($visad) {
  $visadeVaror = $redigeradeVaror ?? vk_rader((int)$visad['id']);
  $st = db()->prepare("SELECT r.produkt, r.ursprung, r.vikt, r.enhet, r.pris, SUM(r.antal) AS kolli, COUNT(DISTINCT r.order_id) AS kunder
                       FROM order_rader r JOIN order_superklipp os ON os.order_id = r.order_id JOIN orders o ON o.id = r.order_id
                       WHERE os.veckansklipp_id = ? AND o.status <> 'avbruten'
                       GROUP BY r.produkt, r.ursprung, r.vikt, r.enhet, r.pris ORDER BY MIN(r.sortering)");
  $st->execute([$visad['id']]);
  $plocklista = $st->fetchAll();
  $st = db()->prepare("SELECT o.id, o.foretag, o.status, os.leveransdatum, (SELECT SUM(antal) FROM order_rader WHERE order_id = o.id) AS kolli
                       FROM orders o JOIN order_superklipp os ON os.order_id = o.id
                       WHERE os.veckansklipp_id = ? ORDER BY o.id DESC");
  $st->execute([$visad['id']]);
  $bestallningar = $st->fetchAll();
}
$aktivaBestallningar = count(array_filter($bestallningar, fn($o) => $o['status'] !== 'avbruten'));

$active = 'admin';
$pageTitle = 'Veckansklipp – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin.php">&larr; Beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Veckansklipp</h1>
        <p>Ladda upp bladet från Canva. Kunderna ser det och kan beställa direkt på <a href="veckansklipp.php" style="color:var(--brand);"><?php echo e(preg_replace('~^https?://~', '', vk_lank())); ?></a>.</p>
      </div>
      <div class="portal-actions">
        <a class="btn ghost" href="veckansklipp.php">Visa som kund</a>
      </div>
    </div>

    <?php if (vk_startsidan_ersatt()): ?>
      <div class="alert error" role="alert"><strong>Startsidan är ersatt.</strong> Filen index.php i rotmappen är kortlänken för veckansklippet, så den som går till startsidan hamnar här. Ladda upp startsidans index.php igen till public_html. Mappen veckansklipp behöver du inte ladda upp, den skapas automatiskt.</div>
    <?php endif; ?>
    <?php if ($fel): ?>
      <div class="alert error" role="alert"><?php echo implode('<br>', array_map('e', $fel)); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
      <form class="detail-card" id="vkForm" method="post" action="admin-veckansklipp.php" enctype="multipart/form-data"
            data-max-fil="<?php echo min($maxFil, VK_MAX_PDF); ?>" data-max-post="<?php echo $maxPost; ?>"
            data-max-sidor="<?php echo VK_MAX_SIDOR; ?>" data-worker="<?php echo e(VK_PDFJS . 'pdf.worker.min.js'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="ladda_upp">
        <h2>Ladda upp veckans blad</h2>
        <div class="field">
          <label for="vkPdf">PDF från Canva</label>
          <input type="file" id="vkPdf" name="pdf" accept="application/pdf,.pdf" required>
          <input type="file" id="vkSidor" name="sidor[]" accept="image/jpeg" multiple hidden>
          <p class="vk-status" id="vkStatus" aria-live="polite"><?php echo $uppladdningsVaror !== null ? 'Välj PDF:en igen. Varorna nedan är kvar.' : ''; ?></p>
          <div class="vk-tumnaglar" id="vkTumnaglar"></div>
        </div>
        <div class="sk-form-grid" style="margin-top:16px;">
          <div class="field">
            <label for="vecka">Vecka</label>
            <input type="number" id="vecka" name="vecka" min="1" max="53" value="<?php echo (int)$f['vecka']; ?>" required>
          </div>
          <div class="field">
            <label for="ar">År</label>
            <input type="number" id="ar" name="ar" min="2020" max="2100" value="<?php echo (int)$f['ar']; ?>" required>
          </div>
        </div>

        <div class="vk-varor-del" id="vkVarorDel"<?php echo $uppladdningsVaror === null ? ' hidden' : ''; ?>>
          <h3 class="vk-rubrik">Varor att beställa</h3>
          <p class="form-note" id="vkVarorInfo">Kontrollera namn och priser. Kunderna beställer från den här listan.</p>
          <?php echo vk_varutabell($uppladdningsVaror ?? []); ?>
          <button type="button" class="btn ghost small" data-lagg-till style="margin-top:10px;">+ Lägg till vara</button>
        </div>

        <div class="form-actions" style="margin-top:18px;">
          <button class="btn primary" type="submit" id="vkKnapp">Publicera</button>
          <span class="form-note">Samma vecka igen ersätter det gamla bladet och dess varor. Beställningar som redan kommit in finns kvar.</span>
        </div>
      </form>
      <div class="side-card">
        <h3>Så funkar det</h3>
        <ol style="margin:12px 0 0; padding-left:20px; line-height:1.7; color:var(--ink-soft);">
          <li>Gör bladet i Canva som vanligt och ladda ner det som PDF.</li>
          <li>Välj filen här. Sidorna blir bilder och varorna läses av från bladet.</li>
          <li>Kontrollera veckan och varorna, och tryck <strong>Publicera</strong>.</li>
          <li>Kopiera sms-texten och skicka den till kunderna. Länken är densamma varje vecka.</li>
        </ol>
        <p style="margin-top:12px; color:var(--ink-soft); line-height:1.6;">Inloggade kunder beställer i kolli med leverans måndag&ndash;fredag samma vecka. Beställningarna kommer in under <a href="admin.php" style="color:var(--brand);">Beställningar</a>.</p>
      </div>
    </div>

    <?php if ($visad): ?>
      <div class="detail-grid" style="margin-top:18px;">
        <div class="detail-card">
          <h2><?php echo e(vk_etikett($visad)); ?> <?php echo (int)$visad['ar']; ?> &middot; gäller <?php echo e(vk_period($visad)); ?></h2>
          <?php if ((int)$visad['sidor'] > 0): ?>
            <div class="vk-tumnaglar">
              <?php for ($i = 1; $i <= (int)$visad['sidor']; $i++): ?>
                <a href="<?php echo e(vk_url($visad, $i)); ?>" target="_blank" rel="noopener"><img src="<?php echo e(vk_url($visad, $i)); ?>" alt="Sida <?php echo $i; ?>" loading="lazy"></a>
              <?php endfor; ?>
            </div>
          <?php else: ?>
            <p class="form-note">Bara PDF, inga sidbilder. Kunderna ser PDF:en på sidan.</p>
          <?php endif; ?>
          <p class="form-note" style="margin-top:12px;">
            <?php echo (int)$visad['sidor']; ?> <?php echo (int)$visad['sidor'] === 1 ? 'sida' : 'sidor'; ?>
            &middot; <?php echo (int)$visad['pdf_storlek'] > 0 ? 'PDF ' . e(vk_mb((int)$visad['pdf_storlek'])) : 'ingen PDF'; ?>
            &middot; uppladdat <?php echo e(datum($visad['created_at'])); ?>
          </p>
          <div class="form-actions" style="margin-top:14px;">
            <a class="btn ghost small" href="veckansklipp.php?id=<?php echo (int)$visad['id']; ?>">Visa som kund</a>
            <?php if ((int)$visad['pdf_storlek'] > 0): ?><a class="btn ghost small" href="<?php echo e(vk_url($visad, 'pdf')); ?>" target="_blank" rel="noopener">Öppna PDF</a><?php endif; ?>
            <form method="post" action="admin-veckansklipp.php" style="margin:0;" onsubmit="return confirm('Ta bort <?php echo e(vk_etikett($visad)); ?>? Kunderna ser det inte längre. Beställningar som redan kommit in finns kvar.');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="radera">
              <input type="hidden" name="id" value="<?php echo (int)$visad['id']; ?>">
              <button class="btn danger small" type="submit">Ta bort</button>
            </form>
          </div>
        </div>
        <div class="detail-card">
          <h2>Sms till kunderna</h2>
          <label class="sr-only" for="smsText">Sms-text</label>
          <textarea id="smsText" class="sms" rows="5"><?php echo e(vk_sms($visad)); ?></textarea>
          <p class="form-note" id="smsRakna" style="margin-top:6px;"></p>
          <div class="form-actions" style="margin-top:10px;">
            <button type="button" class="btn primary small" data-kopiera="smsText">Kopiera texten</button>
            <a class="btn ghost small" id="smsLank" href="sms:?&amp;body=<?php echo rawurlencode(vk_sms($visad)); ?>">Öppna i sms</a>
          </div>
          <p class="form-note" style="margin-top:10px;">Länken visar alltid det senaste bladet, så kunderna kan spara den.</p>
        </div>
      </div>

      <form class="detail-card" method="post" action="admin-veckansklipp.php" style="margin-top:18px;" id="vkVarorForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="spara_varor">
        <input type="hidden" name="id" value="<?php echo (int)$visad['id']; ?>">
        <h2>Varor att beställa &middot; <?php echo count($visadeVaror); ?> st</h2>
        <?php if (!$visadeVaror): ?>
          <p class="form-note" style="margin-bottom:6px;">Inga varor ännu, så kunderna kan bara se bladet. Lägg till varorna här för att de ska kunna beställa.</p>
        <?php endif; ?>
        <?php echo vk_varutabell($visadeVaror, $visadeVaror ? 0 : 3); ?>
        <div class="form-actions" style="margin-top:12px;">
          <button type="button" class="btn ghost small" data-lagg-till>+ Lägg till vara</button>
          <button class="btn primary small" type="submit">Spara varor</button>
          <span class="form-note">Ändringar syns direkt för kunderna. Beställningar som redan kommit in ändras inte.</span>
        </div>
      </form>

      <div class="detail-grid" style="margin-top:18px;">
        <div class="detail-card">
          <h2>Plocklista &middot; <?php echo $aktivaBestallningar; ?> <?php echo $aktivaBestallningar === 1 ? 'beställning' : 'beställningar'; ?></h2>
          <?php if (!$plocklista): ?>
            <p style="color:var(--ink-soft);">Inga beställningar på det här veckansklippet än.</p>
          <?php else: ?>
            <table class="sk-table" style="font-size:.9rem;">
              <thead><tr><th>Vara</th><th class="num">Kolli</th><th class="num">Kunder</th></tr></thead>
              <tbody>
              <?php foreach ($plocklista as $p): ?>
                <tr><td><?php echo e($p['produkt']); ?> <span style="color:var(--ink-soft);"><?php echo e(implode(' · ', array_filter([$p['ursprung'], sk_forpackning($p)]))); ?></span></td><td class="pris"><?php echo (int)$p['kolli']; ?></td><td class="pris" style="font-weight:500;"><?php echo (int)$p['kunder']; ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <p class="form-note" style="margin-top:10px;">Avbrutna beställningar räknas inte med.</p>
          <?php endif; ?>
        </div>
        <div class="detail-card">
          <h2>Beställningar</h2>
          <?php if (!$bestallningar): ?>
            <p style="color:var(--ink-soft);">Inga än.</p>
          <?php else: ?>
            <table class="sk-table" style="font-size:.9rem;">
              <tbody>
              <?php foreach ($bestallningar as $o): ?>
                <tr>
                  <td><a style="font-weight:600; color:var(--brand); text-decoration:none;" href="admin-order.php?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a> <?php echo e($o['foretag']); ?><br><span style="color:var(--ink-soft); font-size:.82rem;"><?php echo (int)$o['kolli']; ?> kolli &middot; leverans <?php echo e(date('j/n', strtotime($o['leveransdatum']))); ?></span></td>
                  <td style="text-align:right;"><?php echo status_pill($o['status']); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <h2 style="font-size:1.8rem; text-transform:uppercase; margin:34px 0 14px;">Alla veckansklipp</h2>
    <?php if (!$alla): ?>
      <div class="empty"><p>Inga veckansklipp än. Ladda upp veckans blad ovan.</p></div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Vecka</th><th>Gäller</th><th>Sidor</th><th>Varor</th><th>Beställningar</th><th>Uppladdat</th></tr></thead>
          <tbody>
          <?php foreach ($alla as $v): ?>
            <tr>
              <td data-label="Vecka"><a class="row-link" href="admin-veckansklipp.php?id=<?php echo (int)$v['id']; ?>">v.<?php echo (int)$v['vecka']; ?> <?php echo (int)$v['ar']; ?></a></td>
              <td data-label="Gäller" class="muted"><?php echo e(vk_period($v)); ?></td>
              <td data-label="Sidor"><?php echo (int)$v['sidor']; ?></td>
              <td data-label="Varor"><?php echo (int)$v['varor']; ?></td>
              <td data-label="Beställningar"><?php echo (int)$v['bestallningar']; ?></td>
              <td data-label="Uppladdat" class="muted"><?php echo e(datum($v['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<template id="vkRadMall"><?php echo vk_varurad('__N__', []); ?></template>
<datalist id="vkEnheter"><option value="kg"><option value="st"><option value="låda"><option value="säck"><option value="hink"><option value="kartong"><option value="förp"></datalist>

<script src="<?php echo e(VK_PDFJS . 'pdf.min.js'); ?>" defer></script>
<script>
<?php readfile(__DIR__ . '/includes/vk-las.js'); ?>
</script>
<script>
(function(){
  var form = document.getElementById('vkForm');
  var pdfIn = document.getElementById('vkPdf');
  var sidorIn = document.getElementById('vkSidor');
  var status = document.getElementById('vkStatus');
  var tum = document.getElementById('vkTumnaglar');
  var knapp = document.getElementById('vkKnapp');
  var veckaIn = document.getElementById('vecka');
  var arIn = document.getElementById('ar');
  var varorDel = document.getElementById('vkVarorDel');
  var varorInfo = document.getElementById('vkVarorInfo');
  var uppTbody = varorDel.querySelector('[data-varor]');
  var mall = document.getElementById('vkRadMall').innerHTML;
  var maxFil = +form.dataset.maxFil, maxPost = +form.dataset.maxPost, maxSidor = +form.dataset.maxSidor;
  var arbetar = false;
  var radNr = 1000;
  var andrat = uppTbody.rows.length > 0;

  // ---- varutabellerna: lägg till, ta bort, kontrollera ----
  function nyRad(tbody, v) {
    tbody.insertAdjacentHTML('beforeend', mall.replace(/__N__/g, String(radNr++)));
    var tr = tbody.lastElementChild;
    if (v) ['produkt', 'forpackning', 'info', 'pris', 'enhet'].forEach(function (k) { tr.querySelector('[name$="[' + k + ']"]').value = v[k] || ''; });
    return tr;
  }
  document.querySelectorAll('[data-varor]').forEach(function (tbody) {
    var f = tbody.closest('form');
    f.querySelector('[data-lagg-till]').addEventListener('click', function () { nyRad(tbody).querySelector('input').focus(); });
    tbody.addEventListener('click', function (ev) { var b = ev.target.closest('.ta-bort'); if (b) { b.closest('tr').remove(); andrat = true; } });
    tbody.addEventListener('input', function () { andrat = true; });
    f.addEventListener('submit', function (ev) {
      var fel = 0;
      Array.prototype.forEach.call(tbody.rows, function (tr) {
        var falt = {};
        tr.querySelectorAll('input').forEach(function (i) { falt[i.name.replace(/^.*\[(\w+)\]$/, '$1')] = i; i.classList.remove('ogiltig'); });
        var tom = ['produkt', 'forpackning', 'info', 'pris'].every(function (k) { return falt[k].value.trim() === ''; });
        if (tom) return;
        if (falt.produkt.value.trim() === '') { falt.produkt.classList.add('ogiltig'); fel++; }
        if (!/^\s*\d{1,6}(?:[.,]\d{1,2})?\s*(?:kr|:-)?\s*$/i.test(falt.pris.value)) { falt.pris.classList.add('ogiltig'); fel++; }
      });
      if (fel) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
        tbody.querySelector('.ogiltig').focus();
        alert('Kontrollera de markerade fälten. Varje vara behöver namn och pris, t.ex. 23,95.');
      }
    });
  });

  // ---- uppladdning: bilder och varor från PDF:en ----
  function visa(text, fel) { status.textContent = text; status.className = 'vk-status' + (fel ? ' fel' : ''); }
  function mb(b) { return (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
  function hittaVecka(text) {
    var m = text.match(/vecka\s*:?\s*(\d{1,2})\s*[\/-]\s*(20\d{2})/i) || text.match(/v(?:ecka|\.)?[\s_.-]*(\d{1,2})[\s_.-]+(20\d{2})/i);
    if (m) { veckaIn.value = +m[1]; arIn.value = +m[2]; }
  }
  // Skickar PDF:en bara om servern orkar ta emot den. Bilderna räcker för kunderna.
  function storlekOk(fil, bildBytes, text) {
    if (fil.size <= maxFil && fil.size + bildBytes <= maxPost * 0.95) { visa(text + ' Kontrollera veckan och varorna och tryck Publicera.'); return true; }
    if (bildBytes > 0 && bildBytes <= maxPost * 0.95) {
      pdfIn.value = ''; pdfIn.required = false;
      visa(text + ' PDF:en (' + mb(fil.size) + ') är för stor för servern, så bara bilderna laddas upp. Kunderna ser bladet ändå.');
      return true;
    }
    visa('PDF:en är ' + mb(fil.size) + ' men servern tar emot högst ' + mb(Math.min(maxFil, maxPost)) + '. Spara en mindre PDF i Canva och försök igen.', true);
    return false;
  }
  function fyllVaror(res) {
    varorDel.hidden = false;
    if (andrat && uppTbody.rows.length && !confirm('Ersätta varorna i listan med de som lästes av från den nya filen?')) return;
    uppTbody.innerHTML = '';
    res.varor.forEach(function (v) { nyRad(uppTbody, v); });
    andrat = false;
    if (res.varor.length) {
      varorInfo.textContent = res.varor.length + ' varor hittades i bladet. Kontrollera namn och priser, kunderna beställer från den här listan.'
        + (res.utanNamn ? ' ' + res.utanNamn + (res.utanNamn === 1 ? ' pris' : ' priser') + ' saknade namn, lägg till dem för hand.' : '');
    } else {
      varorInfo.textContent = 'Inga varor kunde läsas av från bladet. Lägg till dem för hand, eller publicera bara bladet.';
      for (var i = 0; i < 3; i++) nyRad(uppTbody);
    }
  }

  pdfIn.addEventListener('change', function () {
    tum.innerHTML = '';
    sidorIn.value = '';
    pdfIn.required = true;
    var fil = pdfIn.files[0];
    if (!fil) { visa(''); return; }
    hittaVecka(fil.name);
    if (!window.pdfjsLib) {
      fyllVaror({ varor: [], utanNamn: 0 });
      knapp.disabled = !storlekOk(fil, 0, 'Förhandsvisningen kunde inte laddas, så bladet laddas upp som PDF.');
      return;
    }

    arbetar = true;
    knapp.disabled = true;
    visa('Gör bilder av sidorna …');
    pdfjsLib.GlobalWorkerOptions.workerSrc = form.dataset.worker;
    var filer = [], texter = [];
    fil.arrayBuffer()
      .then(function (data) { return pdfjsLib.getDocument({ data: data }).promise; })
      .then(function (pdf) {
        var antal = Math.min(pdf.numPages, maxSidor);
        var kedja = Promise.resolve();
        for (var n = 1; n <= antal; n++) (function (n) {
          kedja = kedja.then(function () { return pdf.getPage(n); }).then(function (sida) {
            var bas = sida.getViewport({ scale: 1 });
            var text = sida.getTextContent().then(function (tc) {
              texter[n - 1] = tc.items.map(function (it) {
                return { str: it.str, x: it.transform[4], y: bas.height - it.transform[5], h: Math.hypot(it.transform[2], it.transform[3]), w: it.width };
              });
              if (n === 1) hittaVecka(tc.items.map(function (i) { return i.str; }).join(' '));
            });
            var vp = sida.getViewport({ scale: 1240 / bas.width });
            var c = document.createElement('canvas');
            c.width = Math.round(vp.width);
            c.height = Math.round(vp.height);
            var ctx = c.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, c.width, c.height);
            return text
              .then(function () { return sida.render({ canvasContext: ctx, viewport: vp }).promise; })
              .then(function () { return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.82); }); })
              .then(function (blob) {
                filer.push(new File([blob], 'sida-' + n + '.jpg', { type: 'image/jpeg' }));
                var img = document.createElement('img');
                img.src = URL.createObjectURL(blob);
                img.alt = 'Sida ' + n;
                tum.appendChild(img);
                visa('Gör bilder av sidorna … ' + n + ' av ' + antal);
              });
          });
        })(n);
        return kedja.then(function () {
          var dt = new DataTransfer();
          filer.forEach(function (f) { dt.items.add(f); });
          sidorIn.files = dt.files;
          var res = vkLasVaror(texter);
          fyllVaror(res);
          var bytes = filer.reduce(function (s, f) { return s + f.size; }, 0);
          var text = antal + (antal === 1 ? ' sida klar' : ' sidor klara') + ', ' + res.varor.length + ' varor hittades.'
            + (pdf.numPages > maxSidor ? ' Bara de ' + maxSidor + ' första sidorna visas.' : '');
          return storlekOk(fil, bytes, text);
        });
      })
      .catch(function () {
        tum.innerHTML = '';
        sidorIn.value = '';
        fyllVaror({ varor: [], utanNamn: 0 });
        return storlekOk(fil, 0, 'Kunde inte göra bilder av PDF:en, så den laddas upp som den är.');
      })
      .then(function (ok) { arbetar = false; knapp.disabled = !ok; });
  });

  form.addEventListener('submit', function (ev) {
    if (arbetar) { ev.preventDefault(); return; }
    knapp.disabled = true;
    knapp.textContent = 'Laddar upp …';
  });

  // ---- sms ----
  var sms = document.getElementById('smsText');
  if (sms) {
    var rakna = document.getElementById('smsRakna');
    var lank = document.getElementById('smsLank');
    var gsm = /^[A-Za-z0-9 \r\n@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&'()*+,\-.\/:;<=>?¡ÄÖÑÜ§¿äöñüà]*$/;
    var uppdatera = function () {
      var t = sms.value, vanlig = gsm.test(t), n = t.length;
      var antal = n <= (vanlig ? 160 : 70) ? 1 : Math.ceil(n / (vanlig ? 153 : 67));
      rakna.textContent = n + ' tecken · ' + antal + ' sms' + (vanlig ? '' : ' (emoji och vissa tecken gör sms:et kortare)');
      lank.href = 'sms:?&body=' + encodeURIComponent(t);
    };
    sms.addEventListener('input', uppdatera);
    uppdatera();
  }
  document.querySelectorAll('[data-kopiera]').forEach(function (b) {
    b.addEventListener('click', function () {
      var el = document.getElementById(b.getAttribute('data-kopiera'));
      var text = b.textContent;
      var klart = function () { b.textContent = 'Kopierat!'; setTimeout(function () { b.textContent = text; }, 1600); };
      var reserv = function () { el.select(); document.execCommand('copy'); klart(); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(el.value).then(klart, reserv);
      else reserv();
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
