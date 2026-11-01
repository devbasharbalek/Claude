<?php
// Veckansklipp: bladet från Canva som PDF, plus sidorna som bilder för mobilen.
// Bilderna görs i adminens webbläsare vid uppladdningen, så servern behöver inga PDF-verktyg.

const VK_MAX_SIDOR = 12;
const VK_MAX_RADER = 150;
const VK_MAX_BILD = 5 * 1024 * 1024;
const VK_MAX_PDF = 40 * 1024 * 1024;
const VK_PDFJS = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/';

const VK_SCHEMA = "CREATE TABLE IF NOT EXISTS veckansklipp (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vecka TINYINT UNSIGNED NOT NULL,
    ar SMALLINT UNSIGNED NOT NULL,
    nyckel CHAR(16) NOT NULL,
    sidor TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bild_b SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bild_h SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_storlek INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_vk_vecka (ar, vecka),
    UNIQUE KEY uq_vk_nyckel (nyckel)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci";

const VK_RADER_SCHEMA = "CREATE TABLE IF NOT EXISTS veckansklipp_rader (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    veckansklipp_id INT UNSIGNED NOT NULL,
    sortering SMALLINT UNSIGNED NOT NULL,
    produkt VARCHAR(160) NOT NULL,
    forpackning VARCHAR(80) NOT NULL DEFAULT '',
    info VARCHAR(100) NOT NULL DEFAULT '',
    enhet VARCHAR(20) NOT NULL DEFAULT '',
    pris DECIMAL(10,2) NOT NULL,
    KEY idx_vk (veckansklipp_id, sortering),
    CONSTRAINT fk_vkrader_vk FOREIGN KEY (veckansklipp_id) REFERENCES veckansklipp(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci";

// Skapar tabellerna första gången och uppdaterar äldre installationer.
// Tabellen veckansklipp_rader skapas sist och visar att allt är klart.
function veckansklipp_schema(): void {
  static $klar = false;
  if ($klar) return;
  $klar = true;
  superklipp_schema();
  try {
    db()->query('SELECT 1 FROM veckansklipp_rader LIMIT 1');
    return;
  } catch (PDOException $ex) {
  }
  db()->exec(VK_SCHEMA);
  $andringar = [
    'ALTER TABLE order_superklipp ADD COLUMN veckansklipp_id INT UNSIGNED NULL AFTER superklipp_id',
    'ALTER TABLE order_superklipp ADD KEY idx_vk (veckansklipp_id)',
    'ALTER TABLE order_superklipp ADD CONSTRAINT fk_osk_vk FOREIGN KEY (veckansklipp_id) REFERENCES veckansklipp(id) ON DELETE SET NULL',
    "ALTER TABLE order_rader MODIFY ursprung VARCHAR(100) NOT NULL DEFAULT '', MODIFY vikt VARCHAR(80) NOT NULL DEFAULT ''",
  ];
  foreach ($andringar as $sql) {
    try {
      db()->exec($sql);
    } catch (PDOException $ex) {
      // Redan gjort.
    }
  }
  db()->exec(VK_RADER_SCHEMA);
}

function vk_mapp(): string {
  return dirname(__DIR__) . '/uploads/veckansklipp';
}

// Skapar mappen och stänger den för direkt åtkomst. Filerna visas via veckansklipp-fil.php.
function vk_skapa_mapp(): bool {
  $mapp = vk_mapp();
  if (!is_dir($mapp) && !@mkdir($mapp, 0755, true)) return false;
  $skydd = dirname($mapp) . '/.htaccess';
  if (!is_file($skydd)) {
    @file_put_contents($skydd, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
  }
  return is_writable($mapp);
}

function vk_fil(string $nyckel, $sida): string {
  return vk_mapp() . '/' . $nyckel . ($sida === 'pdf' ? '.pdf' : '-' . (int)$sida . '.jpg');
}

function vk_url(array $vk, $sida, bool $ladda = false): string {
  return 'veckansklipp-fil.php?k=' . $vk['nyckel'] . '&s=' . ($sida === 'pdf' ? 'pdf' : (int)$sida) . ($ladda ? '&ladda=1' : '');
}

function vk_etikett(array $vk): string {
  return 'Veckansklipp v.' . (int)$vk['vecka'];
}

// "måndag 2/11 – söndag 8/11"
function vk_period(array $vk): string {
  $man = sk_veckodag((int)$vk['ar'], (int)$vk['vecka'], 1);
  $son = sk_veckodag((int)$vk['ar'], (int)$vk['vecka'], 7);
  return 'måndag ' . $man->format('j/n') . ' – söndag ' . $son->format('j/n');
}

function vk_har_passerat(array $vk): bool {
  return sk_veckodag((int)$vk['ar'], (int)$vk['vecka'], 7)->setTime(23, 59, 59) < new DateTimeImmutable();
}

function vk_hamta(int $id): ?array {
  $st = db()->prepare('SELECT * FROM veckansklipp WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function vk_senaste(): ?array {
  return db()->query('SELECT * FROM veckansklipp ORDER BY ar DESC, vecka DESC LIMIT 1')->fetch() ?: null;
}

function vk_radera_filer(array $vk): void {
  @unlink(vk_fil($vk['nyckel'], 'pdf'));
  for ($i = 1; $i <= VK_MAX_SIDOR; $i++) @unlink(vk_fil($vk['nyckel'], $i));
}

// Förvald vecka: från fredag laddar man upp nästa veckas blad.
function vk_standard_vecka(): array {
  $d = new DateTimeImmutable('today');
  if ((int)$d->format('N') >= 5) $d = $d->modify('+7 days');
  return [(int)$d->format('o'), (int)$d->format('W')];
}

// "8M" -> 8388608
function ini_bytes(string $varde): int {
  $varde = trim($varde);
  $n = (int)$varde;
  switch (strtolower(substr($varde, -1))) {
    case 'g': $n *= 1024;
    case 'm': $n *= 1024;
    case 'k': $n *= 1024;
  }
  return $n;
}

// Största fil och största totala uppladdning servern tar emot.
function vk_servergranser(): array {
  $fil = ini_bytes((string)ini_get('upload_max_filesize'));
  $post = ini_bytes((string)ini_get('post_max_size'));
  return [$fil > 0 ? $fil : PHP_INT_MAX, $post > 0 ? $post : PHP_INT_MAX];
}

function vk_mb(int $bytes): string {
  return number_format($bytes / 1048576, 1, ',', '') . ' MB';
}

// Den korta länken ludvikaparti.se/veckansklipp är en liten fil i mappen "veckansklipp".
// Sajten skapar den själv, så att den aldrig behöver laddas upp. En fil som heter
// index.php är lätt att råka lägga i fel mapp, och då ersätter den startsidan.
const VK_KORTLANK_KOD = "<?php\n// Kort länk för sms: ludvikaparti.se/veckansklipp. Skapas automatiskt av admin-veckansklipp.php.\nheader('Location: ../veckansklipp.php', true, 302);\n";

function vk_kortlank_fil(): string {
  return dirname(__DIR__) . '/veckansklipp/index.php';
}

function vk_skapa_kortlank(): bool {
  $fil = vk_kortlank_fil();
  if (is_file($fil)) return true;
  $mapp = dirname($fil);
  if (!is_dir($mapp) && !@mkdir($mapp, 0755)) return false;
  return @file_put_contents($fil, VK_KORTLANK_KOD) !== false;
}

// Sant om startsidan har ersatts av kortlänkens fil.
function vk_startsidan_ersatt(): bool {
  $start = dirname(__DIR__) . '/index.php';
  return is_file($start) && str_contains((string)file_get_contents($start, false, null, 0, 400), 'Kort länk för sms');
}

function vk_lank(): string {
  global $CONFIG;
  return rtrim($CONFIG['site_url'], '/') . (is_file(vk_kortlank_fil()) ? '/veckansklipp' : '/veckansklipp.php');
}

// Kort sms utan emoji, så att det får plats i ett sms (160 tecken).
function vk_sms(array $vk): string {
  return 'Veckansklipp v.' . (int)$vk['vecka'] . ' är ute! Se veckans erbjudanden: ' . vk_lank()
       . ' Beställ på 0240-183 55. Mvh Ludvika Partiaffär';
}

// ---------- varor och beställning ----------

function vk_rader(int $id): array {
  $st = db()->prepare('SELECT * FROM veckansklipp_rader WHERE veckansklipp_id = ? ORDER BY sortering, id');
  $st->execute([$id]);
  return $st->fetchAll();
}

// En rad i samma form som superklippets, så att beställningen sparas och visas likadant.
function vk_som_listrad(array $r): array {
  return ['id' => (int)$r['id'], 'sortering' => (int)$r['sortering'], 'produkt' => $r['produkt'], 'ursprung' => $r['info'],
          'vikt' => $r['forpackning'], 'enhet' => $r['enhet'], 'pris' => $r['pris']];
}

function vk_spara_rader(int $id, array $rader): void {
  db()->prepare('DELETE FROM veckansklipp_rader WHERE veckansklipp_id = ?')->execute([$id]);
  $st = db()->prepare('INSERT INTO veckansklipp_rader (veckansklipp_id, sortering, produkt, forpackning, info, enhet, pris) VALUES (?, ?, ?, ?, ?, ?, ?)');
  foreach (array_values($rader) as $i => $r) {
    $st->execute([$id, $i, $r['produkt'], $r['forpackning'], $r['info'], $r['enhet'], $r['pris']]);
  }
}

// Varulistan från admins formulär. Tomma rader hoppas över.
function vk_rader_fran_post(): array {
  $rader = [];
  $fel = [];
  $utanPris = [];
  foreach ((array)($_POST['varor'] ?? []) as $r) {
    if (!is_array($r)) continue;
    $produkt = enRad($r['produkt'] ?? '', 160);
    $forp = enRad($r['forpackning'] ?? '', 80);
    $info = enRad($r['info'] ?? '', 100);
    $enhet = mb_strtolower(trim(enRad($r['enhet'] ?? '', 20), " /."), 'UTF-8');
    $prisText = enRad($r['pris'] ?? '', 30);
    if ($produkt === '' && $forp === '' && $info === '' && $prisText === '') continue;
    $pris = sk_pris($prisText);
    if ($produkt === '') {
      $fel['namn'] = 'Varje vara behöver ett namn.';
    } elseif ($pris === null) {
      $utanPris[] = $produkt;
    }
    $rader[] = ['produkt' => $produkt, 'forpackning' => $forp, 'info' => $info, 'enhet' => $enhet !== '' ? $enhet : 'st',
                'pris' => $pris, 'pris_text' => $prisText];
  }
  if ($utanPris) $fel['pris'] = 'Pris saknas eller är ogiltigt för: ' . implode(', ', array_slice($utanPris, 0, 8)) . (count($utanPris) > 8 ? ' m.fl.' : '') . '.';
  if (count($rader) > VK_MAX_RADER) $fel['antal'] = 'Högst ' . VK_MAX_RADER . ' varor per blad.';
  return [$rader, $fel];
}

// Vardagarna i bladets vecka som går att beställa leverans till: från i morgon och framåt.
function vk_leveransdagar(array $vk): array {
  global $leveransdagar;
  $imorgon = new DateTimeImmutable('tomorrow');
  $ut = [];
  $dag = 1;
  foreach ($leveransdagar as $key => $namn) {
    $datum = sk_veckodag((int)$vk['ar'], (int)$vk['vecka'], $dag++);
    if ($datum >= $imorgon) $ut[$key] = $namn . ' ' . $datum->format('j/n');
  }
  return $ut;
}

// Varulistan som redigerbar tabell för admin. $rader har fälten från veckansklipp_rader eller formuläret.
function vk_varutabell(array $rader, int $extra = 0): string {
  $html = '<div class="sk-edit-wrap"><table class="sk-edit vk-varor"><thead><tr><th>Vara</th><th>Förpackning</th><th>Märke / info</th>'
        . '<th>Pris (kr)</th><th>Per</th><th><span class="sr-only">Ta bort</span></th></tr></thead><tbody data-varor>';
  $n = 0;
  foreach ($rader as $r) $html .= vk_varurad((string)$n++, $r);
  for ($i = 0; $i < $extra; $i++) $html .= vk_varurad((string)$n++, []);
  return $html . '</tbody></table></div>';
}

function vk_varurad(string $n, array $r): string {
  $pris = $r['pris_text'] ?? (isset($r['pris']) && $r['pris'] !== null && $r['pris'] !== '' ? number_format((float)$r['pris'], 2, ',', '') : '');
  $f = fn($falt, $varde, $extra = '') => '<input type="text" name="varor[' . $n . '][' . $falt . ']" value="' . e($varde) . '"' . $extra . '>';
  return '<tr>'
    . '<td>' . $f('produkt', $r['produkt'] ?? '', ' aria-label="Vara"') . '</td>'
    . '<td class="c-forp">' . $f('forpackning', $r['forpackning'] ?? '', ' aria-label="Förpackning"') . '</td>'
    . '<td class="c-info">' . $f('info', $r['info'] ?? '', ' aria-label="Märke eller info"') . '</td>'
    . '<td class="c-pris">' . $f('pris', $pris, ' inputmode="decimal" aria-label="Pris"') . '</td>'
    . '<td class="c-enh">' . $f('enhet', $r['enhet'] ?? '', ' list="vkEnheter" aria-label="Pris per"') . '</td>'
    . '<td class="c-x"><button type="button" class="ta-bort" aria-label="Ta bort raden">&times;</button></td>'
    . '</tr>';
}
