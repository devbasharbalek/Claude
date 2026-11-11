<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('artiklar');
erp_schema();
$kanPriser = far($user, 'priser');
$seInpris = far($user, 'inpris_se');
$andraInpris = far($user, 'inpris_andra');
$kanRedigera = $kanPriser || $andraInpris;

$f = [
  'sok' => enRad($_GET['sok'] ?? $_POST['sok'] ?? '', 80),
  'pg' => enRad($_GET['pg'] ?? $_POST['pg'] ?? '', 60),
  'ag' => enRad($_GET['ag'] ?? $_POST['ag'] ?? '', 20),
  'status' => in_array($_GET['status'] ?? $_POST['status'] ?? '', ['inaktiva', 'alla'], true) ? ($_GET['status'] ?? $_POST['status']) : '',
  'sort' => in_array($_GET['sort'] ?? $_POST['sort'] ?? '', ['nr', 'grupp'], true) ? ($_GET['sort'] ?? $_POST['sort']) : '',
  'kund' => (int)($_GET['kund'] ?? $_POST['kund'] ?? 0),
  'redigera' => $kanRedigera && !empty($_GET['redigera'] ?? $_POST['redigera'] ?? '') ? 1 : 0,
];
$kund = $f['kund'] ? kund_hamta($f['kund']) : null;
if (!$kund) $f['kund'] = 0;

// Sparar de priser som ändrats i tabellen. Bara ändrade fält skickas, så två personer skriver inte över varandra.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (!$kanRedigera) {
    http_response_code(403);
    exit('Du har inte behörighet att ändra priser.');
  }
  $av = vem($user);
  $antal = 0;
  $fel = [];
  db()->beginTransaction();
  foreach ((array)($_POST['p'] ?? []) as $id => $falt) {
    $a = artikel_hamta((int)$id);
    if (!$a || !is_array($falt) || !far_grupp($user, $a['artikelgrupp'], 'artikelgrupper')) continue;
    foreach ($falt as $namn => $text) {
      // Inpris kräver sin egen behörighet, försäljningspriser sin.
      if ($namn === 'inpris' ? !$andraInpris : !$kanPriser) continue;
      $pris = pris_ur_text((string)$text);
      if ($pris === false) {
        $fel[] = $a['artikelnr'] . ': "' . enRad($text, 20) . '" är inget pris';
        continue;
      }
      $andrat = match (true) {
        $namn === 'inpris', $namn === 'utpris' => satt_artikelpris((int)$a['id'], $namn, $pris, $av),
        in_array($namn, ['g1', 'g2', 'g3'], true) => satt_grupppris((int)$a['id'], (int)substr($namn, 1), $pris, $av),
        $namn === 'kund' && $kund !== null => satt_kundpris((int)$kund['id'], (int)$a['id'], $pris, $av),
        default => false,
      };
      if ($andrat) $antal++;
    }
  }
  db()->commit();
  flash(($antal === 1 ? '1 pris är ändrat.' : "$antal priser är ändrade.") . ($fel ? ' Sparades inte: ' . implode('; ', array_slice($fel, 0, 5)) . '.' : ''));
  redirect(adm_lank('admin-artiklar', $f, ['sida' => (int)($_POST['sida'] ?? 1)]));
}

$where = [];
$params = [];
[$gv, $gp] = grupp_villkor($user, 'a.artikelgrupp', 'artikelgrupper');
$where[] = $gv;
$params = $gp;
if ($f['status'] === '') $where[] = 'a.aktiv = 1';
elseif ($f['status'] === 'inaktiva') $where[] = 'a.aktiv = 0';
if ($f['pg'] !== '') {
  $where[] = 'a.produktgrupp = ?';
  $params[] = $f['pg'];
}
if ($f['ag'] !== '') {
  $where[] = 'a.artikelgrupp = ?';
  $params[] = $f['ag'];
}
// Varje ord ska finnas i namnet eller vara början på artikelnumret.
foreach (array_slice(preg_split('/\s+/u', $f['sok'], -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $ord) {
  $where[] = '(a.namn LIKE ? OR a.artikelnr LIKE ?)';
  $esc = addcslashes($ord, '%_\\');
  array_push($params, "%$esc%", "$esc%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$ordning = match ($f['sort']) {
  'nr' => 'LENGTH(a.artikelnr), a.artikelnr',
  'grupp' => 'a.produktgrupp, a.namn',
  default => 'a.namn, a.artikelnr',
};
$kundKol = '';
$kundJoin = '';
$kundParams = [];
if ($kund) {
  [$kol, $join] = kundpris_sql();
  $kundKol = ', ' . $kol;
  $kundJoin = $join;
  $kundParams = [(int)$kund['id'], (int)($kund['prisgrupp'] ?? 0)];
}
$bas = "FROM artiklar a
        LEFT JOIN artikel_priser p1 ON p1.artikel_id = a.id AND p1.prisgrupp = 1
        LEFT JOIN artikel_priser p2 ON p2.artikel_id = a.id AND p2.prisgrupp = 2
        LEFT JOIN artikel_priser p3 ON p3.artikel_id = a.id AND p3.prisgrupp = 3
        $kundJoin $whereSql";

// Prislistan som fil för Excel, med samma filter som på sidan.
if (($_GET['export'] ?? '') === 'csv') {
  $st = db()->prepare("SELECT a.*, p1.pris AS g1, p2.pris AS g2, p3.pris AS g3 $kundKol $bas ORDER BY $ordning");
  $st->execute(array_merge($kundParams, $params));
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="prislista-' . ($kund ? preg_replace('/[^A-Za-z0-9-]/', '', $kund['kundnr']) . '-' : '') . date('Y-m-d') . '.csv"');
  header('Cache-Control: private, no-store');
  $ut = fopen('php://output', 'w');
  fwrite($ut, "\xEF\xBB\xBF");
  $rubriker = ['Art. Nr', 'Artikelnamn', 'Kollistorlek', 'Enhet', 'Produktgrupp', 'Artikelgrupp', 'Gällande inpris', 'Gällande utpris', 'Grupp 1', 'Grupp 2', 'Grupp 3', 'Moms'];
  if (!$seInpris) unset($rubriker[6]);
  if ($kund) $rubriker[] = 'Pris ' . $kund['kundnr'];
  fputcsv($ut, $rubriker, ';', '"', '');
  foreach ($st as $a) {
    // Text som börjar med = + - @ skulle Excel tolka som en formel.
    $cell = fn($t) => preg_match('/^[=+\-@\t\r]/', (string)$t) ? "'" . $t : (string)$t;
    $rad = [$cell($a['artikelnr']), $cell($a['namn']), kolli_text($a), $cell($a['enhet']), $cell($a['produktgrupp']), $cell($a['artikelgrupp']), pris_text($a['inpris']), pris_text($a['utpris']),
            pris_text($a['g1']), pris_text($a['g2']), pris_text($a['g3']), tal_text($a['moms'])];
    if (!$seInpris) unset($rad[6]);
    if ($kund) $rad[] = pris_text($a['kundens_pris']);
    fputcsv($ut, $rad, ';', '"', '');
  }
  exit;
}

$st = db()->prepare("SELECT COUNT(*) $bas");
$st->execute(array_merge($kundParams, $params));
$totalt = (int)$st->fetchColumn();
$perSida = $f['redigera'] ? 50 : 100;
$sidor = max(1, (int)ceil($totalt / $perSida));
$sida = min(max(1, (int)($_GET['sida'] ?? 1)), $sidor);
$st = db()->prepare("SELECT a.*, p1.pris AS g1, p2.pris AS g2, p3.pris AS g3 $kundKol $bas ORDER BY $ordning LIMIT $perSida OFFSET " . (($sida - 1) * $perSida));
$st->execute(array_merge($kundParams, $params));
$artiklar = $st->fetchAll();

$produktgrupper = db()->query("SELECT produktgrupp, COUNT(*) AS n FROM artiklar WHERE produktgrupp <> '' GROUP BY produktgrupp ORDER BY produktgrupp")->fetchAll();
$artikelgrupper = db()->query("SELECT artikelgrupp, COUNT(*) AS n FROM artiklar WHERE artikelgrupp <> '' GROUP BY artikelgrupp ORDER BY artikelgrupp")->fetchAll();
$registretTomt = !$totalt && !(int)db()->query('SELECT COUNT(*) FROM artiklar')->fetchColumn();

function pris_cell(?string $pris, bool $redigera, int $id, string $namn, string $platshallare = ''): string {
  if (!$redigera) return $pris === null ? '<td class="num tom">–</td>' : '<td class="num">' . e(pris_text($pris)) . '</td>';
  return '<td class="num"><input class="pris" type="text" inputmode="decimal" name="p[' . $id . '][' . $namn . ']" value="' . e(pris_text($pris)) . '"'
       . ($platshallare !== '' ? ' placeholder="' . e($platshallare) . '"' : '') . ' aria-label="' . e($namn) . '" autocomplete="off"></td>';
}

$sortLank = fn(string $s, string $text) => '<a href="' . e(adm_lank('admin-artiklar', $f, ['sort' => $s])) . '" style="color:inherit;' . ($f['sort'] === $s ? '' : ' text-decoration:none;') . '">' . $text . '</a>';

$active = 'admin';
$adminSida = 'artiklar';
$pageTitle = 'Artiklar och priser – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <h1>Artiklar och priser</h1>
        <p>Grundpriset gäller när prisgruppen saknar eget pris. Ett kundpris går före allt annat. Alla priser är exklusive moms.</p>
      </div>
      <div class="portal-actions">
        <?php if (!$registretTomt): ?>
          <a class="btn ghost small" href="<?php echo e(adm_lank('admin-artiklar', array_merge($f, ['redigera' => 0]), ['export' => 'csv'])); ?>">Ladda ner för Excel</a>
          <?php if ($kanRedigera && !$f['redigera']): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin-artiklar', $f, ['redigera' => 1])); ?>">Ändra priser</a><?php endif; ?>
        <?php endif; ?>
        <a class="btn primary small" href="admin-artikel?ny=1">Ny artikel</a>
      </div>
    </div>

    <?php if ($registretTomt): ?>
      <div class="detail-card tomt-register">
        <h2>Artikelregistret är tomt</h2>
        <p>Läs in artiklarna från Partihandel, eller lägg upp dem en och en.</p>
        <?php if (far($user, 'import')): ?><a class="btn primary" href="admin-import">Importera artiklar</a><?php else: ?><p>Be en administratör att importera artiklarna.</p><?php endif; ?>
      </div>
    <?php else: ?>

    <?php if ($kund): ?>
      <div class="kund-banner">
        <span>Priser för <strong><?php echo e($kund['namn']); ?></strong> (<?php echo e($kund['kundnr']); ?>), <?php echo e(prisgrupp_text($kund['prisgrupp'] === null ? null : (int)$kund['prisgrupp'])); ?>. Kolumnen Kundens pris visar vad kunden betalar.</span>
        <span><a href="admin-kund?id=<?php echo (int)$kund['id']; ?>">Till kunden</a> &middot; <a href="<?php echo e(adm_lank('admin-artiklar', $f, ['kund' => 0])); ?>">Visa utan kund</a></span>
      </div>
    <?php endif; ?>

    <form class="verktyg" method="get" action="admin-artiklar">
      <?php foreach (['kund', 'redigera', 'sort'] as $dold): if ($f[$dold]): ?><input type="hidden" name="<?php echo $dold; ?>" value="<?php echo e($f[$dold]); ?>"><?php endif; endforeach; ?>
      <input type="search" name="sok" value="<?php echo e($f['sok']); ?>" placeholder="Sök namn eller artikelnummer" aria-label="Sök artiklar">
      <select name="pg" aria-label="Produktgrupp">
        <option value="">Alla produktgrupper</option>
        <?php foreach ($produktgrupper as $g): ?><option value="<?php echo e($g['produktgrupp']); ?>"<?php echo $f['pg'] === $g['produktgrupp'] ? ' selected' : ''; ?>><?php echo e($g['produktgrupp'] . ' (' . $g['n'] . ')'); ?></option><?php endforeach; ?>
      </select>
      <select name="ag" aria-label="Artikelgrupp">
        <option value="">Alla artikelgrupper</option>
        <?php foreach ($artikelgrupper as $g): ?><option value="<?php echo e($g['artikelgrupp']); ?>"<?php echo $f['ag'] === $g['artikelgrupp'] ? ' selected' : ''; ?>><?php echo e('Grupp ' . $g['artikelgrupp'] . ' (' . $g['n'] . ')'); ?></option><?php endforeach; ?>
      </select>
      <select name="status" aria-label="Status">
        <option value="">Aktiva</option>
        <option value="inaktiva"<?php echo $f['status'] === 'inaktiva' ? ' selected' : ''; ?>>Inaktiva</option>
        <option value="alla"<?php echo $f['status'] === 'alla' ? ' selected' : ''; ?>>Alla</option>
      </select>
      <button class="btn ghost small" type="submit">Visa</button>
      <?php if ($f['sok'] !== '' || $f['pg'] !== '' || $f['ag'] !== '' || $f['status'] !== ''): ?><a class="btn ghost small" href="<?php echo e(adm_lank('admin-artiklar', ['kund' => $f['kund'], 'redigera' => $f['redigera']])); ?>">Rensa</a><?php endif; ?>
    </form>
    <p class="rad-info"><?php echo number_format($totalt, 0, ',', ' '); ?> artiklar<?php echo $sidor > 1 ? ', sida ' . $sida . ' av ' . $sidor : ''; ?>.<?php if ($f['redigera']): ?> Ändra priserna direkt i tabellen. Töm en gruppruta så gäller grundpriset.<?php endif; ?></p>

    <?php if (!$artiklar): ?>
      <div class="empty"><p>Inga artiklar matchar sökningen.</p></div>
    <?php else: ?>
    <?php if ($f['redigera']): ?>
    <form method="post" action="admin-artiklar" id="prisForm">
      <?php echo csrf_field(); ?>
      <?php foreach (['sok', 'pg', 'ag', 'status', 'sort', 'kund', 'redigera'] as $dold): ?><input type="hidden" name="<?php echo $dold; ?>" value="<?php echo e($f[$dold]); ?>"><?php endforeach; ?>
      <input type="hidden" name="sida" value="<?php echo $sida; ?>">
    <?php endif; ?>
      <div class="table-wrap bred">
        <table class="erp">
          <thead><tr>
            <th><?php echo $sortLank('nr', 'Art.nr'); ?></th><th><?php echo $sortLank('', 'Artikelnamn'); ?></th><th class="num">Kolli</th><th>Enhet</th>
            <th><?php echo $sortLank('grupp', 'Produktgrupp'); ?></th><th>Grp</th><?php if ($seInpris): ?><th class="num">Inpris</th><?php endif; ?><th class="num">Grundpris</th>
            <?php foreach (PRISGRUPPER as $g => $pg): ?><th class="num" title="<?php echo e($pg['for']); ?>"><?php echo e($pg['namn']); ?></th><?php endforeach; ?>
            <?php if ($kund): ?><th class="num">Kundens pris</th><?php endif; ?>
            <th class="num">Moms</th>
          </tr></thead>
          <tbody>
          <?php foreach ($artiklar as $a): $id = (int)$a['id']; ?>
            <tr<?php echo (int)$a['aktiv'] ? '' : ' class="inaktiv"'; ?>>
              <td class="nr"><?php echo e($a['artikelnr']); ?></td>
              <td><a class="row-link" href="admin-artikel?id=<?php echo $id; ?>"><?php echo e($a['namn']); ?></a><?php echo (int)$a['aktiv'] ? '' : ' <span class="pill pill-off liten">Inaktiv</span>'; ?></td>
              <td class="num"><?php echo e(kolli_text($a)); ?></td>
              <td><?php echo e($a['enhet']); ?></td>
              <td class="muted"><?php echo e($a['produktgrupp']); ?></td>
              <td class="muted"><?php echo e($a['artikelgrupp']); ?></td>
              <?php if ($seInpris) echo pris_cell($a['inpris'], $f['redigera'] && $andraInpris, $id, 'inpris'); ?>
              <?php echo pris_cell($a['utpris'], $f['redigera'] && $kanPriser, $id, 'utpris'); ?>
              <?php foreach (PRISGRUPPER as $g => $_) echo pris_cell($a['g' . $g], $f['redigera'] && $kanPriser, $id, 'g' . $g, pris_text($a['utpris'])); ?>
              <?php if ($kund): ?>
                <?php if ($f['redigera'] && $kanPriser): ?>
                  <td class="num kund-pris"><input class="pris" type="text" inputmode="decimal" name="p[<?php echo $id; ?>][kund]" value="<?php echo e(pris_text($a['kundpris'])); ?>" placeholder="<?php echo e(pris_text($a['kundgrupppris'] ?? $a['utpris'])); ?>" aria-label="Kundpris" autocomplete="off"></td>
                <?php else: ?>
                  <td class="num kund-pris"><strong><?php echo e(pris_text($a['kundens_pris'])); ?></strong><span class="kalla"><?php echo e(priskalla_text($a['priskalla'], $kund['prisgrupp'] === null ? null : (int)$kund['prisgrupp'])); ?></span></td>
                <?php endif; ?>
              <?php endif; ?>
              <td class="num muted"><?php echo e(tal_text($a['moms'])); ?> %</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php if ($f['redigera']): ?>
      <div class="sk-bar">
        <span id="prisAntal">Inga ändringar än.</span>
        <span style="display:flex; gap:8px; flex-wrap:wrap;">
          <a class="btn ghost small" href="<?php echo e(adm_lank('admin-artiklar', $f, ['redigera' => 0, 'sida' => $sida])); ?>">Klar</a>
          <button class="btn primary small" type="submit">Spara priser</button>
        </span>
      </div>
    </form>
    <?php endif; ?>
    <?php echo sida_navigering('admin-artiklar', $f, $sida, $sidor); ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php if ($f['redigera']): ?>
<script>
// Markerar ändrade och felaktiga priser, och skickar bara de ändrade när man sparar.
(function () {
  var form = document.getElementById('prisForm');
  if (!form) return;
  var falt = form.querySelectorAll('input.pris');
  var antal = document.getElementById('prisAntal');
  var giltig = /^\s*(\d{1,3}([  ]?\d{3})*|\d+)([.,]\d{1,2})?\s*$/;
  var sparar = false;
  function uppdatera() {
    var n = 0, fel = 0;
    falt.forEach(function (f) {
      var andrad = f.value !== f.defaultValue;
      var ok = f.value.trim() === '' || giltig.test(f.value);
      f.classList.toggle('andrad', andrad && ok);
      f.classList.toggle('ogiltig', !ok);
      if (andrad) n++;
      if (!ok) fel++;
    });
    antal.textContent = (n ? n + (n === 1 ? ' ändrat pris' : ' ändrade priser') + ' att spara.' : 'Inga ändringar än.') + (fel ? ' ' + fel + ' ser fel ut.' : '');
    return n;
  }
  form.addEventListener('input', uppdatera);
  form.addEventListener('submit', function () {
    sparar = true;
    falt.forEach(function (f) { if (f.value === f.defaultValue) f.disabled = true; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (!sparar && uppdatera()) { e.preventDefault(); e.returnValue = ''; }
  });
  // Enter flyttar till rutan nedanför, som i ett kalkylark.
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || !e.target.classList.contains('pris')) return;
    e.preventDefault();
    var td = e.target.closest('td'), tr = td.parentElement, i = Array.prototype.indexOf.call(tr.children, td);
    var nasta = tr.nextElementSibling && tr.nextElementSibling.children[i];
    var inp = nasta && nasta.querySelector('input.pris');
    if (inp) { inp.focus(); inp.select(); }
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/foot.php'; ?>
