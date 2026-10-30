<?php
require __DIR__ . '/includes/app.php';
require_admin();
superklipp_schema();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$sk = $id ? sk_hamta($id) : null;
if ($id && !$sk) {
  http_response_code(404);
  exit('Superklippet finns inte.');
}

function redigeringsrad(array $r): array {
  return [
    'kategori' => (string)$r['kategori'],
    'produkt'  => (string)$r['produkt'],
    'ursprung' => (string)$r['ursprung'],
    'vikt'     => (string)$r['vikt'],
    'enhet'    => (string)$r['enhet'],
    'pris'     => number_format((float)$r['pris'], 2, ',', ''),
    'topp'     => (bool)$r['topp'],
  ];
}

function veckoformular(int $ar, int $vecka, ?string $deadline, bool $publicerad): array {
  $t = strtotime($deadline ?? sk_standard_deadline($ar, $vecka));
  return ['vecka' => $vecka, 'ar' => $ar, 'datum' => date('Y-m-d', $t), 'tid' => date('H:i', $t), 'publicerad' => $publicerad];
}

function redigera_rad(string $n, array $r): string {
  $falt = fn(string $k, string $klass, int $max, string $etikett, string $extra = '') =>
    '<td class="' . $klass . '"><input type="text" name="rader[' . $n . '][' . $k . ']" value="' . e($r[$k]) . '" maxlength="' . $max . '" aria-label="' . $etikett . '"' . $extra . '></td>';
  return '<tr>'
    . $falt('kategori', 'c-kat', 60, 'Kategori', ' list="skKategorier"')
    . $falt('produkt', 'c-prod', 160, 'Produkt')
    . $falt('ursprung', 'c-urs', 20, 'Ursprung')
    . $falt('vikt', 'c-vikt', 30, 'Vikt/antal')
    . $falt('enhet', 'c-enh', 20, 'Enhet')
    . $falt('pris', 'c-pris', 20, 'Pris', ' inputmode="decimal"')
    . '<td class="c-topp"><input type="checkbox" name="rader[' . $n . '][topp]" value="1"' . ($r['topp'] ? ' checked' : '') . ' aria-label="Toppklipp"></td>'
    . '<td class="c-x"><button type="button" class="ta-bort" aria-label="Ta bort raden" title="Ta bort raden">&times;</button></td>'
    . '</tr>';
}

$tomRad = ['kategori' => '', 'produkt' => '', 'ursprung' => '', 'vikt' => '', 'enhet' => '', 'pris' => '', 'topp' => false];
$fel = [];
$varningar = [];
$info = null;
$forhandsvisning = false;

if ($sk) {
  $f = veckoformular((int)$sk['ar'], (int)$sk['vecka'], $sk['deadline'], (int)$sk['publicerad'] === 1);
  $rader = array_map('redigeringsrad', sk_rader($id));
} else {
  [$ar, $vecka] = sk_nasta_vecka();
  $f = veckoformular($ar, $vecka, null, false);
  $rader = [];
  $kalla = isset($_GET['kopiera']) ? sk_hamta((int)$_GET['kopiera']) : null;
  if ($kalla && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $nasta = sk_veckodag((int)$kalla['ar'], (int)$kalla['vecka'], 1)->modify('+7 days');
    $f = veckoformular((int)$nasta->format('o'), (int)$nasta->format('W'), date('Y-m-d H:i:s', strtotime($kalla['deadline'] . ' +7 days')), false);
    $rader = array_map('redigeringsrad', sk_rader((int)$kalla['id']));
    $info = 'Kopia av v.' . (int)$kalla['vecka'] . '. Uppdatera priserna och tryck Spara.';
    $forhandsvisning = true;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'radera' && $sk) {
    db()->prepare('DELETE FROM superklipp WHERE id = ?')->execute([$id]);
    flash('Superklipp v.' . (int)$sk['vecka'] . ' är borttaget. Beställningar som redan kommit in finns kvar.');
    redirect('admin-superklipp.php');
  }

  if ($action === 'import') {
    $forhandsvisning = true;
    $text = (string)($_POST['klistra'] ?? '');
    $fil = $_FILES['fil'] ?? null;
    if (is_array($fil) && ($fil['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      if ($fil['error'] !== UPLOAD_ERR_OK || $fil['size'] > 1024 * 1024 || !is_uploaded_file($fil['tmp_name'])) {
        $fel[] = 'Filen kunde inte läsas. Den får vara högst 1 MB.';
      } else {
        $text = (string)file_get_contents($fil['tmp_name']);
      }
    }
    if (!$fel && trim($text) === '') $fel[] = 'Klistra in listan från Google Sheets eller välj en CSV-fil.';
    if (!$fel) {
      $res = sk_las_in($text);
      $varningar = $res['varningar'];
      if (!$res['rader']) {
        $fel[] = 'Hittade inga varor med pris. Kontrollera att du kopierade hela tabellen, med kolumnerna Produkt och Pris.';
      } else {
        $rader = array_map('redigeringsrad', $res['rader']);
        if ($sk) {
          if ($res['vecka'] !== null && $res['vecka'] !== (int)$sk['vecka']) {
            $varningar[] = "Listan du klistrade in gäller v.{$res['vecka']}, men du redigerar v.{$sk['vecka']}. Kontrollera veckan innan du sparar.";
          }
          if ($res['deadline'] !== null) {
            $f['datum'] = substr($res['deadline'], 0, 10);
            $f['tid'] = substr($res['deadline'], 11, 5);
          }
        } elseif ($res['vecka'] !== null) {
          $f = veckoformular($res['ar'], $res['vecka'], $res['deadline'], false);
        } else {
          $varningar[] = 'Hittade ingen vecka i listan. Kontrollera vecka och sista beställning nedan.';
        }
        $kategorier = count(array_unique(array_column($res['rader'], 'kategori')));
        $info = sprintf('Läste in %d varor i %d %s. Kontrollera listan, kryssa i veckans toppklipp och spara.',
          count($res['rader']), $kategorier, $kategorier === 1 ? 'kategori' : 'kategorier');
      }
    }
  }

  if ($action === 'spara') {
    $f = [
      'vecka' => (int)($_POST['vecka'] ?? 0),
      'ar' => (int)($_POST['ar'] ?? 0),
      'datum' => (string)($_POST['datum'] ?? ''),
      'tid' => (string)($_POST['tid'] ?? ''),
      'publicerad' => ($_POST['publicerad'] ?? '') === '1',
    ];
    $rader = [];
    $giltiga = [];
    $prisfel = [];
    foreach ((array)($_POST['rader'] ?? []) as $r) {
      if (!is_array($r)) continue;
      $rad = ['topp' => !empty($r['topp'])];
      foreach (['kategori' => 60, 'produkt' => 160, 'ursprung' => 20, 'vikt' => 30, 'enhet' => 20, 'pris' => 20] as $k => $max) {
        $rad[$k] = enRad(is_string($r[$k] ?? null) ? $r[$k] : '', $max);
      }
      if ($rad['produkt'] === '') continue;
      $rader[] = $rad;
      $pris = sk_pris($rad['pris']);
      if ($pris === null) {
        $prisfel[] = $rad['produkt'];
        continue;
      }
      $giltiga[] = sk_rad($rad['kategori'], $rad['produkt'], $rad['ursprung'], $rad['vikt'], $rad['enhet'], $pris, $rad['topp']);
    }

    if ($f['vecka'] < 1 || $f['vecka'] > 53 || $f['ar'] < 2020 || $f['ar'] > 2100) {
      $fel[] = 'Ange en giltig vecka och ett giltigt år.';
    } elseif ((int)sk_veckodag($f['ar'], $f['vecka'], 1)->format('W') !== $f['vecka']) {
      $fel[] = "År {$f['ar']} har ingen vecka {$f['vecka']}.";
    }
    $deadline = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $f['datum'] . ' ' . $f['tid']);
    if (!$deadline || $deadline->format('Y-m-d H:i') !== $f['datum'] . ' ' . $f['tid']) {
      $fel[] = 'Ange datum och klockslag för sista beställning.';
    }
    if ($prisfel) {
      $fel[] = 'Pris saknas eller är ogiltigt för: ' . implode(', ', array_slice($prisfel, 0, 8)) . (count($prisfel) > 8 ? ' med flera' : '') . '. Skriv t.ex. 32,95.';
    }
    if (!$rader) $fel[] = 'Listan måste innehålla minst en vara.';
    if (count($rader) > SK_MAX_RADER) $fel[] = 'Listan får ha högst ' . SK_MAX_RADER . ' varor.';
    if (!$fel) {
      $st = db()->prepare('SELECT id FROM superklipp WHERE ar = ? AND vecka = ? AND id <> ?');
      $st->execute([$f['ar'], $f['vecka'], $id]);
      if ($st->fetchColumn()) {
        $fel[] = "Det finns redan ett superklipp för v.{$f['vecka']} {$f['ar']}. Öppna det under Superklipp och klistra in listan där i stället.";
      }
    }

    if (!$fel) {
      $pdo = db();
      $pdo->beginTransaction();
      try {
        $dl = $deadline->format('Y-m-d H:i:s');
        if ($sk) {
          $pdo->prepare('UPDATE superklipp SET vecka = ?, ar = ?, deadline = ?, publicerad = ?, updated_at = ? WHERE id = ?')
              ->execute([$f['vecka'], $f['ar'], $dl, $f['publicerad'] ? 1 : 0, now(), $id]);
        } else {
          $pdo->prepare('INSERT INTO superklipp (vecka, ar, deadline, publicerad, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
              ->execute([$f['vecka'], $f['ar'], $dl, $f['publicerad'] ? 1 : 0, now(), now()]);
          $id = (int)$pdo->lastInsertId();
        }
        sk_spara_rader($id, $giltiga);
        $pdo->commit();
      } catch (PDOException $ex) {
        $pdo->rollBack();
        $fel[] = 'Superklippet kunde inte sparas just nu. Försök igen.';
      }
      if (!$fel) {
        $sparat = sk_hamta($id);
        if (!$f['publicerad']) {
          flash("Superklipp v.{$f['vecka']} är sparat som utkast. Det syns inte för kunderna förrän du publicerar det.");
        } elseif (!sk_ar_oppen($sparat)) {
          flash("Superklipp v.{$f['vecka']} är sparat och publicerat, men sista beställningsdag har redan passerat, så kunderna kan inte beställa.");
        } else {
          flash("Superklipp v.{$f['vecka']} är sparat och publicerat. Inloggade kunder kan beställa fram till " . sk_deadline_text($sparat['deadline']) . '.');
        }
        redirect('admin-superklipp-redigera.php?id=' . $id);
      }
    }
  }
}

$kategorier = array_values(array_unique(array_filter(array_merge(['Grönsaker', 'Frukt'], array_column($rader, 'kategori')))));
$visaUtskick = $sk && !$forhandsvisning && $_SERVER['REQUEST_METHOD'] !== 'POST';

if ($visaUtskick) {
  [$mejlAmne, $mejlText] = sk_mejl($sk, sk_rader($id));

  $st = db()->prepare("SELECT r.produkt, r.ursprung, r.vikt, r.enhet, SUM(r.antal) AS kolli, COUNT(DISTINCT r.order_id) AS kunder
    FROM order_rader r
    JOIN order_superklipp os ON os.order_id = r.order_id
    JOIN orders o ON o.id = r.order_id
    WHERE os.superklipp_id = ? AND o.status <> 'avbruten'
    GROUP BY r.produkt, r.ursprung, r.vikt, r.enhet
    ORDER BY MIN(r.sortering)");
  $st->execute([$id]);
  $plocklista = $st->fetchAll();

  $st = db()->prepare('SELECT o.id, o.foretag, o.status, o.created_at, os.leveransdatum,
      (SELECT SUM(r.antal) FROM order_rader r WHERE r.order_id = o.id) AS kolli
    FROM order_superklipp os JOIN orders o ON o.id = os.order_id
    WHERE os.superklipp_id = ? ORDER BY o.created_at DESC, o.id DESC');
  $st->execute([$id]);
  $bestallningar = $st->fetchAll();
}

$active = 'admin';
$pageTitle = ($sk ? 'Superklipp v.' . (int)$sk['vecka'] : 'Nytt superklipp') . ' – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin-superklipp.php">&larr; Alla superklipp</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Superklipp</span>
        <h1><?php echo $sk ? 'v.' . (int)$sk['vecka'] . ' ' . (int)$sk['ar'] . ' ' . sk_status_pill($sk) : 'Nytt superklipp'; ?></h1>
        <p><?php echo $sk ? 'Sista beställning ' . e(sk_deadline_text($sk['deadline'])) . ' &middot; sparat ' . e(datum($sk['updated_at'])) : 'Inget är sparat än. Kontrollera listan och spara.'; ?></p>
      </div>
      <?php if ($sk): ?>
        <div class="portal-actions">
          <a class="btn ghost" href="superklipp.php?id=<?php echo $id; ?>">Visa som kund</a>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($fel): ?>
      <div class="alert error" role="alert"><strong>Kunde inte <?php echo ($_POST['action'] ?? '') === 'import' ? 'läsa in listan' : 'spara'; ?>:</strong><ul><?php foreach ($fel as $m): ?><li><?php echo e($m); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($info): ?><div class="alert info" role="status"><?php echo e($info); ?></div><?php endif; ?>
    <?php if ($varningar): ?>
      <div class="alert warn"><strong>Kontrollera:</strong><ul><?php foreach ($varningar as $m): ?><li><?php echo e($m); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form class="form-card" method="post" action="admin-superklipp-redigera.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="spara">
      <input type="hidden" name="id" value="<?php echo $id; ?>">
      <?php /* Enter i ett fält sparar utan att ändra publiceringen. */ ?>
      <button type="submit" name="publicerad" value="<?php echo $f['publicerad'] ? '1' : '0'; ?>" class="sr-only" tabindex="-1" aria-hidden="true">Spara</button>

      <div class="sk-form-grid">
        <div class="field"><label for="vecka">Vecka</label><input type="number" id="vecka" name="vecka" min="1" max="53" value="<?php echo (int)$f['vecka']; ?>" required></div>
        <div class="field"><label for="ar">År</label><input type="number" id="ar" name="ar" min="2020" max="2100" value="<?php echo (int)$f['ar']; ?>" required></div>
        <div class="field"><label for="datum">Sista beställning, datum</label><input type="date" id="datum" name="datum" value="<?php echo e($f['datum']); ?>" required></div>
        <div class="field"><label for="tid">Klockslag</label><input type="time" id="tid" name="tid" value="<?php echo e($f['tid']); ?>" required></div>
      </div>
      <p class="form-note" style="margin-top:10px;">Efter sista beställning stängs beställningen på hemsidan automatiskt. Kunderna ser leveransdagarna i vecka <?php echo (int)$f['vecka']; ?>.</p>

      <div class="sk-edit-wrap">
        <table class="sk-edit">
          <thead><tr><th>Kategori</th><th>Produkt</th><th>Urspr.</th><th>Vikt/antal</th><th>Enhet</th><th>Pris (kr)</th><th>Toppklipp</th><th><span class="sr-only">Ta bort</span></th></tr></thead>
          <tbody id="skRader">
            <?php
            $n = 0;
            foreach ($rader as $r) echo redigera_rad((string)$n++, $r);
            for ($i = 0; $i < 3; $i++) echo redigera_rad((string)$n++, $tomRad);
            ?>
          </tbody>
        </table>
      </div>
      <datalist id="skKategorier"><?php foreach ($kategorier as $k): ?><option value="<?php echo e($k); ?>"><?php endforeach; ?></datalist>
      <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:12px;">
        <button type="button" class="btn ghost small" id="laggTill">+ Lägg till rad</button>
        <span class="form-note">Tomma rader sparas inte. Kryssa i <strong>Toppklipp</strong> för varor som ska lyftas fram på sidan och i mejlet.</span>
      </div>

      <div class="form-actions">
        <?php if ($f['publicerad']): ?>
          <button class="btn primary" type="submit" name="publicerad" value="1">Spara</button>
          <button class="btn ghost" type="submit" name="publicerad" value="0">Avpublicera</button>
        <?php else: ?>
          <button class="btn primary" type="submit" name="publicerad" value="1">Spara och publicera</button>
          <button class="btn ghost" type="submit" name="publicerad" value="0">Spara som utkast</button>
        <?php endif; ?>
      </div>
    </form>
    <template id="radMall"><?php echo redigera_rad('__N__', $tomRad); ?></template>

    <details class="detail-card sk-import" style="margin-top:16px;"<?php echo !$rader || ($fel && ($_POST['action'] ?? '') === 'import') ? ' open' : ''; ?>>
      <summary><?php echo $rader ? 'Klistra in en ny version från Google Sheets' : 'Klistra in listan från Google Sheets'; ?></summary>
      <form method="post" action="admin-superklipp-redigera.php" enctype="multipart/form-data" style="margin-top:16px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php echo sk_import_falt(); ?>
        <div class="form-actions" style="margin-top:16px;">
          <button class="btn primary small" type="submit">Läs in</button>
          <span class="form-note">Ersätter raderna ovan. Inget sparas förrän du trycker Spara.</span>
        </div>
      </form>
    </details>

    <?php if ($visaUtskick): ?>
      <div class="detail-grid" style="margin-top:16px;">
        <div class="detail-card">
          <h2>Mejl till kunderna</h2>
          <?php if ((int)$sk['publicerad'] !== 1): ?>
            <div class="alert warn">Superklippet är inte publicerat än. Publicera det innan du skickar mejlet, annars hittar kunderna det inte.</div>
          <?php endif; ?>
          <div class="field">
            <label for="mejlAmne">Ämnesrad</label>
            <input type="text" id="mejlAmne" value="<?php echo e($mejlAmne); ?>" readonly>
          </div>
          <div class="field" style="margin-top:12px;">
            <label for="mejlText">Text</label>
            <textarea id="mejlText" class="mejl" readonly><?php echo e($mejlText); ?></textarea>
          </div>
          <div class="form-actions" style="margin-top:14px;">
            <button type="button" class="btn primary small" data-kopiera="mejlText">Kopiera texten</button>
            <button type="button" class="btn ghost small" data-kopiera="mejlAmne">Kopiera ämnesraden</button>
            <a class="btn ghost small" href="mailto:?subject=<?php echo rawurlencode($mejlAmne); ?>&amp;body=<?php echo rawurlencode($mejlText); ?>">Öppna i mejlprogrammet</a>
          </div>
          <p class="form-note" style="margin-top:12px;">Inga bilagor behövs. Lägg kunderna som hemlig kopia (Bcc) så att de inte ser varandras adresser. Toppklippen i mejlet är de rader du kryssat i.</p>
        </div>

        <div>
          <div class="detail-card">
            <h2>Plocklista &middot; <?php echo count($bestallningar); ?> <?php echo count($bestallningar) === 1 ? 'beställning' : 'beställningar'; ?></h2>
            <?php if (!$plocklista): ?>
              <p style="color:var(--ink-soft);">Inga beställningar på det här superklippet än.</p>
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
          <?php if ($bestallningar): ?>
            <div class="detail-card">
              <h2>Beställningar</h2>
              <table class="sk-table" style="font-size:.9rem;">
                <tbody>
                <?php foreach ($bestallningar as $o): ?>
                  <tr>
                    <td><a class="row-link" style="font-weight:600; color:var(--brand); text-decoration:none;" href="admin-order.php?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a> <?php echo e($o['foretag']); ?><br><span style="color:var(--ink-soft); font-size:.82rem;"><?php echo (int)$o['kolli']; ?> kolli &middot; leverans <?php echo e(date('j/n', strtotime($o['leveransdatum']))); ?></span></td>
                    <td style="text-align:right;"><?php echo status_pill($o['status']); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <form method="post" action="admin-superklipp-redigera.php" style="margin-top:28px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="radera">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <button class="btn danger small" type="submit" onclick="return confirm('Ta bort superklippet? Beställningar som redan kommit in finns kvar.');">Ta bort superklippet</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<script>
(function(){
  var tbody = document.getElementById('skRader');
  var mall = document.getElementById('radMall');
  var knapp = document.getElementById('laggTill');
  var n = <?php echo $n; ?>;
  if (tbody && mall && knapp) {
    knapp.addEventListener('click', function(){
      var rader = tbody.querySelectorAll('tr');
      var forra = rader.length ? rader[rader.length - 1].querySelector('[name$="[kategori]"]') : null;
      tbody.insertAdjacentHTML('beforeend', mall.innerHTML.replace(/__N__/g, String(n++)));
      var ny = tbody.lastElementChild;
      if (forra) ny.querySelector('[name$="[kategori]"]').value = forra.value;
      ny.querySelector('[name$="[produkt]"]').focus();
    });
    tbody.addEventListener('click', function(ev){
      var b = ev.target.closest('.ta-bort');
      if (b) b.closest('tr').remove();
    });
  }
  document.querySelectorAll('[data-kopiera]').forEach(function(b){
    b.addEventListener('click', function(){
      var el = document.getElementById(b.getAttribute('data-kopiera'));
      var text = b.textContent;
      var klart = function(){ b.textContent = 'Kopierat!'; setTimeout(function(){ b.textContent = text; }, 1600); };
      var reserv = function(){ el.select(); document.execCommand('copy'); klart(); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(el.value).then(klart, reserv);
      else reserv();
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
