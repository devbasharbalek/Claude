<?php
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/import.php';
$user = require_behorighet('import');
erp_schema();

$imp = $_SESSION['import'] ?? null;
$fel = '';
$v = ['typ' => 'artiklar', 'prismal' => '', 'kundnr' => '', 'synka' => 0];

// Avbryter en påbörjad import och tar bort den uppladdade filen.
function import_rensa(?array $imp): void {
  if ($imp && ($fil = import_fil($imp))) @unlink($fil);
  unset($_SESSION['import']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'avbryt') {
    import_rensa($imp);
    redirect('admin-import.php');
  }

  if ($action === 'genomfor') {
    $fil = $imp ? import_fil($imp) : null;
    if (!$fil || !hash_equals($imp['token'], (string)($_POST['token'] ?? ''))) {
      import_rensa($imp);
      flash('Importen hade gått ut. Ladda upp filen igen.');
      redirect('admin-import.php');
    }
    try {
      $rader = tabell_las($fil, $imp['namn']);
      db()->beginTransaction();
      $r = import_kor($imp['typ'], $rader, $imp['val'] + ['filnamn' => $imp['namn']], $user);
      if ($r['fel'] !== null) throw new RuntimeException($r['fel']);
      import_logga($r, $imp['namn'], $user);
      db()->commit();
    } catch (Throwable $ex) {
      if (db()->inTransaction()) db()->rollBack();
      if (!$ex instanceof RuntimeException) error_log('Import misslyckades: ' . $ex);
      import_rensa($imp);
      flash('Importen gick inte igenom och inget har ändrats: ' . ($ex instanceof RuntimeException ? $ex->getMessage() : 'tekniskt fel.'));
      redirect('admin-import.php');
    }
    import_rensa($imp);
    $delar = [];
    if ($r['nya']) $delar[] = $r['nya'] . ' nya';
    if ($r['andrade']) $delar[] = $r['andrade'] . ' ändrade';
    if ($r['inaktiveras']) $delar[] = $r['inaktiveras'] . ' markerade som inaktiva';
    if ($r['grupp_bort']) $delar[] = $r['grupp_bort'] . ' grupppriser borttagna';
    flash('Importen är klar: ' . ($delar ? implode(', ', $delar) : 'inga ändringar') . '. ' . $r['oforandrade'] . ' var redan aktuella.');
    redirect('admin-import.php');
  }

  if ($action === 'ladda_upp') {
    $v['typ'] = isset(IMPORT_TYPER[$_POST['typ'] ?? '']) ? $_POST['typ'] : 'artiklar';
    $v['prismal'] = isset(PRISGRUPPER[(int)($_POST['prismal'] ?? 0)]) ? (string)(int)$_POST['prismal'] : '';
    $v['kundnr'] = enRad($_POST['kundnr'] ?? '', 40);
    $v['synka'] = !empty($_POST['synka']) ? 1 : 0;
    $f = $_FILES['fil'] ?? null;
    $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
    $mapp = import_mapp();
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      $fel = 'Välj en fil att ladda upp.';
    } elseif (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > 20 * 1048576) {
      $fel = 'Filen är för stor. Den får vara högst 20 MB.';
    } elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
      $fel = 'Filen kunde inte laddas upp. Försök igen.';
    } elseif ($ext === 'xls') {
      $fel = 'Filen är i det gamla Excel-formatet (.xls). Spara den som .xlsx i Excel och försök igen.';
    } elseif (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
      $fel = 'Ladda upp en Excel-fil (.xlsx) eller en CSV-fil.';
    } elseif ($mapp === null) {
      $fel = 'Servern kan inte spara filen. Kontrollera att mappen uploads finns och går att skriva till.';
    } else {
      try {
        tabell_las($f['tmp_name'], (string)$f['name']);
        import_rensa($imp);
        $token = bin2hex(random_bytes(16));
        $ext = $ext === 'xlsx' ? 'xlsx' : 'csv';
        if (!move_uploaded_file($f['tmp_name'], "$mapp/$token.$ext")) throw new RuntimeException('Servern kan inte spara filen.');
        $_SESSION['import'] = ['token' => $token, 'ext' => $ext, 'namn' => enRad($f['name'], 200), 'typ' => $v['typ'],
                               'val' => ['prismal' => $v['prismal'], 'kundnr' => $v['kundnr'], 'synka' => $v['synka']]];
        redirect('admin-import.php');
      } catch (RuntimeException $ex) {
        $fel = $ex->getMessage();
      }
    }
  }
}

// Förhandsvisning: samma import körs utan att något sparas.
$rapport = null;
if ($imp && !$fel) {
  $fil = import_fil($imp);
  if (!$fil) {
    unset($_SESSION['import']);
    $imp = null;
  } else {
    try {
      $rapport = import_kor($imp['typ'], tabell_las($fil, $imp['namn']), $imp['val'] + ['filnamn' => $imp['namn']], null);
    } catch (RuntimeException $ex) {
      import_rensa($imp);
      $imp = null;
      $fel = $ex->getMessage();
    }
  }
}

$historik = db()->query('SELECT * FROM importlogg ORDER BY tid DESC, id DESC LIMIT 10')->fetchAll();
$antal = [
  'artiklar' => (int)db()->query('SELECT COUNT(*) FROM artiklar')->fetchColumn(),
  'kunder' => (int)db()->query('SELECT COUNT(*) FROM kunder')->fetchColumn(),
  'kundpriser' => (int)db()->query('SELECT COUNT(*) FROM kund_priser')->fetchColumn(),
];
$enhet = ['artiklar' => 'artiklar', 'kunder' => 'kunder', 'kundpriser' => 'kundpriser'];

$active = 'admin';
$adminSida = 'import';
$pageTitle = 'Import – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <h1>Import</h1>
        <p>Läs in artiklar, priser och kunder från Excel, till exempel exporter från Partihandel. Du ser alltid vad som ändras innan något sparas.</p>
      </div>
    </div>

    <?php if ($fel): ?><div class="alert error" role="alert"><?php echo e($fel); ?></div><?php endif; ?>

    <?php if ($imp && $rapport): $t = IMPORT_TYPER[$imp['typ']]; ?>
      <div class="detail-card">
        <div class="rubrik-rad">
          <h2>Förhandsvisning: <?php echo e($t['namn']); ?> <span class="h2-text">från <?php echo e($imp['namn']); ?></span></h2>
        </div>

        <?php if ($rapport['fel'] !== null): ?>
          <div class="alert error" role="alert"><?php echo e($rapport['fel']); ?></div>
          <form method="post" action="admin-import.php"><?php echo csrf_field(); ?><button class="btn primary" type="submit" name="action" value="avbryt">Välj en annan fil</button></form>
        <?php else: ?>
          <div class="stat-row">
            <div class="stat-tile"><b><?php echo $rapport['nya']; ?></b><span>Nya</span></div>
            <div class="stat-tile<?php echo $rapport['andrade'] ? ' warn' : ''; ?>"><b><?php echo $rapport['andrade']; ?></b><span>Ändras</span></div>
            <div class="stat-tile"><b><?php echo $rapport['oforandrade']; ?></b><span>Redan aktuella</span></div>
            <div class="stat-tile"><b><?php echo $rapport['overhoppade']; ?></b><span>Hoppas över</span></div>
          </div>
          <?php foreach ($rapport['info'] as $info): ?><div class="alert info" style="margin-bottom:12px;"><?php echo e($info); ?></div><?php endforeach; ?>
          <?php if ($rapport['prislista']): ?><p class="form-note" style="margin-bottom:6px;"><strong>Filen är en prislista.</strong> Den ändrar bara priser. Namn, enheter och grupper på artiklarna lämnas som de är.</p><?php endif; ?>
          <p class="form-note">Filen har <?php echo $rapport['rader']; ?> rader. Kolumner som läses: <?php echo e(implode(', ', $rapport['kolumner'])); ?>.<?php if ($rapport['priser']): ?> <strong><?php echo $rapport['priser']; ?> priser</strong> sätts eller ändras, och alla prisändringar sparas i prishistoriken.<?php endif; ?></p>

          <?php if ($rapport['moms_misstanke']): ?>
            <div class="alert error" role="alert" style="margin-top:14px;"><strong>Priserna ser ut att vara inklusive moms.</strong> <?php echo $rapport['moms_misstanke']; ?> grundpriser i filen är exakt det nuvarande priset plus moms. Exportera prislistan med <strong>Exkl. moms</strong> och ladda upp den igen.</div>
          <?php endif; ?>
          <?php if ($rapport['okanda']): ?>
            <div class="alert warn" style="margin-top:14px;"><strong><?php echo count($rapport['okanda']); ?> artiklar i filen finns inte i artikelregistret</strong> och hoppas över. Importera artikelregistret först, eller lägg upp dem under Artiklar.
              <details><summary>Visa artiklarna</summary><ul class="import-lista"><?php foreach ($rapport['okanda'] as $o): ?><li><?php echo e($o); ?></li><?php endforeach; ?></ul></details>
            </div>
          <?php endif; ?>
          <?php if ($rapport['saknas'] && $imp['typ'] !== 'kundpriser'): ?>
            <div class="alert <?php echo $rapport['inaktiveras'] ? 'warn' : 'info'; ?>" style="margin-top:14px;"><?php echo $rapport['saknas']; ?> aktiva <?php echo $enhet[$imp['typ']]; ?> i registret finns inte i filen. <?php echo $rapport['inaktiveras'] ? '<strong>De markeras som inaktiva.</strong>' : 'De lämnas som de är.'; ?></div>
          <?php endif; ?>
          <?php if ($rapport['grupp_bort']): ?>
            <div class="alert warn" style="margin-top:14px;"><strong><?php echo $rapport['grupp_bort']; ?> grupppriser tas bort</strong> för artiklar som inte finns i filen. De artiklarna får grundpriset i den gruppen.</div>
          <?php endif; ?>
          <?php if ($rapport['varningar']): ?>
            <div class="alert warn" style="margin-top:14px;"><strong><?php echo $rapport['antal_varningar']; ?> saker att kontrollera</strong>
              <details<?php echo $rapport['antal_varningar'] <= 5 ? ' open' : ''; ?>><summary>Visa</summary><ul class="import-lista"><?php foreach ($rapport['varningar'] as $w): ?><li><?php echo e($w); ?></li><?php endforeach; ?></ul>
              <?php if ($rapport['antal_varningar'] > count($rapport['varningar'])): ?><p class="form-note">Visar de första <?php echo count($rapport['varningar']); ?>.</p><?php endif; ?></details>
            </div>
          <?php endif; ?>

          <?php if ($rapport['exempel']): ?>
            <h3 class="vk-rubrik">Det här ändras<?php echo $rapport['nya'] + $rapport['andrade'] > count($rapport['exempel']) ? ' (de första ' . count($rapport['exempel']) . ')' : ''; ?></h3>
            <div class="table-wrap">
              <table class="erp">
                <thead><tr><th>Nr</th><th>Namn</th><th></th><th>Ändring</th></tr></thead>
                <tbody>
                <?php foreach ($rapport['exempel'] as $ex): ?>
                  <tr>
                    <td class="nr"><?php echo e($ex['nr']); ?></td>
                    <td><?php echo e($ex['namn']); ?></td>
                    <td><?php echo $ex['typ'] === 'ny' ? '<span class="pill pill-levererad liten">Ny</span>' : '<span class="pill pill-ny liten">Ändras</span>'; ?></td>
                    <td class="andring"><?php echo implode('', array_map(fn($a) => '<span>' . e($a) . '</span>', $ex['andringar'])); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <form class="spara-rad" method="post" action="admin-import.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="token" value="<?php echo e($imp['token']); ?>">
            <?php if ($rapport['nya'] + $rapport['andrade'] + $rapport['inaktiveras'] + $rapport['grupp_bort'] > 0): ?>
              <button class="btn primary" type="submit" name="action" value="genomfor">Genomför importen</button>
            <?php else: ?>
              <span class="form-note"><strong>Inget att ändra.</strong> Registret stämmer redan med filen.</span>
            <?php endif; ?>
            <button class="btn ghost" type="submit" name="action" value="avbryt" formnovalidate>Avbryt</button>
          </form>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <div class="detail-grid">
        <form class="detail-card" method="post" action="admin-import.php" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="ladda_upp">
          <h2>Vad vill du importera?</h2>
          <div class="val-kort" role="radiogroup" aria-label="Typ av import">
            <?php foreach (IMPORT_TYPER as $k => $t): ?>
              <label class="check"><input type="radio" name="typ" value="<?php echo $k; ?>"<?php echo $v['typ'] === $k ? ' checked' : ''; ?>>
                <span><strong><?php echo e($t['namn']); ?></strong><small><?php echo e($t['text']); ?></small><small><?php echo number_format($antal[$k], 0, ',', ' '); ?> i registret nu</small></span></label>
            <?php endforeach; ?>
          </div>

          <div class="field" style="margin-top:20px;">
            <label for="fil">Fil (Excel .xlsx eller CSV)</label>
            <input type="file" id="fil" name="fil" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
          </div>

          <div class="field-grid" style="margin-top:16px;" data-for="artiklar">
            <div class="field">
              <label for="prismal">En kolumn som bara heter "Pris" gäller</label>
              <select id="prismal" name="prismal">
                <option value="">Grundpriset (utpris)</option>
                <?php foreach (PRISGRUPPER as $g => $pg): ?><option value="<?php echo $g; ?>"<?php echo $v['prismal'] === (string)$g ? ' selected' : ''; ?>><?php echo e($pg['namn'] . ' – ' . $pg['for']); ?></option><?php endforeach; ?>
              </select>
              <span class="hint">Kolumnerna Grupp 1, 2 och 3 känns igen automatiskt. Står "grupp_1" i filnamnet används Grupp 1.</span>
            </div>
          </div>
          <div class="field" style="margin-top:16px; max-width:280px;" data-for="kundpriser">
            <label for="kundnr">Kundnr</label>
            <input type="text" id="kundnr" name="kundnr" value="<?php echo e($v['kundnr']); ?>">
            <span class="hint">Bara om filen gäller en enda kund och saknar kolumn med kundnummer.</span>
          </div>
          <label class="check inline" style="margin-top:16px;" data-for="artiklar kunder"><input type="checkbox" name="synka" value="1"<?php echo $v['synka'] ? ' checked' : ''; ?>>
            <span>Filen är hela registret eller hela prislistan: artiklar och kunder som saknas blir inaktiva, och grupppriser som saknas i en prislista tas bort</span></label>

          <div class="form-actions"><button class="btn primary" type="submit">Ladda upp och förhandsgranska</button></div>
        </form>

        <div>
          <div class="side-card">
            <h3>Ordning första gången</h3>
            <ol>
              <li>Artiklar (all-articles.xlsx)</li>
              <li>Prislistorna för Grupp 1, 2 och 3. Exportera dem från Priser i Partihandel med <strong>Exkl. moms</strong> och Excel. Filnamnet talar om vilken grupp det är.</li>
              <li>Kunder (customer-register.xlsx)</li>
              <li>Kundpriser, om ni har dem i en fil</li>
            </ol>
            <p>Du kan importera samma fil igen senare. Bara det som har ändrats uppdateras.</p>
          </div>
          <?php if ($historik): ?>
            <div class="detail-card" style="margin-top:14px;">
              <h2>Senaste importer</h2>
              <ul class="logg">
                <?php foreach ($historik as $h): ?>
                  <li><span><strong><?php echo e(IMPORT_TYPER[$h['typ']]['namn'] ?? $h['typ']); ?></strong> · <?php echo (int)$h['nya']; ?> nya, <?php echo (int)$h['andrade']; ?> ändrade<br><span class="muted" style="font-size:.82rem;"><?php echo e($h['filnamn']); ?> · <?php echo e($h['av']); ?></span></span><span class="tid"><?php echo e(datum($h['tid'])); ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
// Visar bara inställningarna som hör till vald typ av import.
(function () {
  var val = document.querySelectorAll('input[name=typ]');
  function visa() {
    var typ = (document.querySelector('input[name=typ]:checked') || {}).value;
    document.querySelectorAll('[data-for]').forEach(function (el) {
      el.hidden = el.getAttribute('data-for').split(' ').indexOf(typ) === -1;
    });
  }
  val.forEach(function (r) { r.addEventListener('change', visa); });
  visa();
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
