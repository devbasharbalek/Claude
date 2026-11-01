<?php
// Veckans superklipp: databastabeller, inläsning från Google Sheets och hjälpfunktioner.

const SK_MAX_RADER = 150;
const SK_MAX_ANTAL = 999;
const VECKODAGAR = ['måndag', 'tisdag', 'onsdag', 'torsdag', 'fredag', 'lördag', 'söndag'];

const SK_SCHEMA = [
  "CREATE TABLE IF NOT EXISTS superklipp (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vecka TINYINT UNSIGNED NOT NULL,
    ar SMALLINT UNSIGNED NOT NULL,
    deadline DATETIME NOT NULL,
    publicerad TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vecka (ar, vecka)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS superklipp_rader (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    superklipp_id INT UNSIGNED NOT NULL,
    sortering SMALLINT UNSIGNED NOT NULL,
    kategori VARCHAR(60) NOT NULL DEFAULT '',
    produkt VARCHAR(160) NOT NULL,
    ursprung VARCHAR(20) NOT NULL DEFAULT '',
    vikt VARCHAR(30) NOT NULL DEFAULT '',
    enhet VARCHAR(20) NOT NULL DEFAULT '',
    pris DECIMAL(10,2) NOT NULL,
    topp TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_lista (superklipp_id, sortering),
    CONSTRAINT fk_skrader_lista FOREIGN KEY (superklipp_id) REFERENCES superklipp(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS order_superklipp (
    order_id INT UNSIGNED PRIMARY KEY,
    superklipp_id INT UNSIGNED NULL,
    etikett VARCHAR(40) NOT NULL,
    leveransdatum DATE NOT NULL,
    KEY idx_lista (superklipp_id),
    CONSTRAINT fk_osk_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_osk_lista FOREIGN KEY (superklipp_id) REFERENCES superklipp(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS order_rader (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    sortering SMALLINT UNSIGNED NOT NULL,
    produkt VARCHAR(160) NOT NULL,
    ursprung VARCHAR(100) NOT NULL DEFAULT '',
    vikt VARCHAR(80) NOT NULL DEFAULT '',
    enhet VARCHAR(20) NOT NULL DEFAULT '',
    pris DECIMAL(10,2) NOT NULL,
    antal SMALLINT UNSIGNED NOT NULL,
    KEY idx_order (order_id),
    CONSTRAINT fk_orader_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
];

// Skapar tabellerna första gången de behövs, så att befintliga installationer inte behöver köra setup.php igen.
function superklipp_schema(): void {
  static $klar = false;
  if ($klar) return;
  try {
    db()->query('SELECT 1 FROM order_rader LIMIT 1');
  } catch (PDOException $ex) {
    foreach (SK_SCHEMA as $sql) db()->exec($sql);
  }
  $klar = true;
}

// ---------- format ----------

function kr(float $pris): string {
  return number_format($pris, 2, ',', ' ') . ' kr';
}

function sk_pris_per(array $rad): string {
  return kr((float)$rad['pris']) . ($rad['enhet'] !== '' ? '/' . $rad['enhet'] : '');
}

// "5" + "kg" blir "5 kg". En förpackning som redan står i klartext, t.ex. "6kg/låda", visas som den är.
function sk_forpackning(array $rad): string {
  $vikt = trim((string)$rad['vikt']);
  if ($vikt === '') return '';
  return preg_match('/^\d+([.,]\d+)?$/', $vikt) ? trim($vikt . ' ' . $rad['enhet']) : $vikt;
}

function sk_etikett(array $sk): string {
  return 'Superklipp v.' . (int)$sk['vecka'];
}

function sk_veckodag(int $ar, int $vecka, int $dag): DateTimeImmutable {
  return (new DateTimeImmutable('today'))->setISODate($ar, $vecka, $dag);
}

// Superklippet för vecka X beställs senast fredagen veckan innan, kl. 13:00.
function sk_standard_deadline(int $ar, int $vecka): string {
  return sk_veckodag($ar, $vecka, 5)->modify('-7 days')->setTime(13, 0)->format('Y-m-d H:i:s');
}

function sk_deadline_text(string $deadline): string {
  $t = strtotime($deadline);
  return VECKODAGAR[(int)date('N', $t) - 1] . ' ' . date('j/n', $t) . ' kl. ' . date('H:i', $t);
}

function sk_ar_oppen(array $sk): bool {
  return (int)$sk['publicerad'] === 1 && strtotime($sk['deadline']) > time();
}

function sk_status_pill(array $sk): string {
  if ((int)$sk['publicerad'] !== 1) return '<span class="pill pill-utkast">Utkast</span>';
  return sk_ar_oppen($sk) ? '<span class="pill pill-oppen">Öppen</span>' : '<span class="pill pill-stangd">Stängd</span>';
}

// Leveransdagarna i superklippets vecka, t.ex. 'man' => 'Måndag 26/10'.
function sk_leveransdagar(array $sk): array {
  global $leveransdagar;
  $ut = [];
  $dag = 1;
  foreach ($leveransdagar as $key => $namn) {
    $ut[$key] = $namn . ' ' . sk_veckodag((int)$sk['ar'], (int)$sk['vecka'], $dag++)->format('j/n');
  }
  return $ut;
}

function sk_nasta_vecka(): array {
  $t = strtotime('+7 days');
  return [(int)date('o', $t), (int)date('W', $t)];
}

// ---------- databas ----------

function sk_hamta(int $id): ?array {
  $st = db()->prepare('SELECT * FROM superklipp WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function sk_rader(int $id): array {
  $st = db()->prepare('SELECT * FROM superklipp_rader WHERE superklipp_id = ? ORDER BY sortering, id');
  $st->execute([$id]);
  return $st->fetchAll();
}

// Publicerade listor som kunderna ska se: alla som fortfarande går att beställa från,
// annars den senast publicerade.
function sk_for_kunder(): array {
  $st = db()->prepare('SELECT * FROM superklipp WHERE publicerad = 1 AND deadline > ? ORDER BY deadline, id');
  $st->execute([now()]);
  $oppna = $st->fetchAll();
  if ($oppna) return $oppna;
  $senaste = db()->query('SELECT * FROM superklipp WHERE publicerad = 1 ORDER BY ar DESC, vecka DESC LIMIT 1')->fetch();
  return $senaste ? [$senaste] : [];
}

function sk_spara_rader(int $id, array $rader): void {
  db()->prepare('DELETE FROM superklipp_rader WHERE superklipp_id = ?')->execute([$id]);
  $st = db()->prepare('INSERT INTO superklipp_rader (superklipp_id, sortering, kategori, produkt, ursprung, vikt, enhet, pris, topp)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  foreach (array_values($rader) as $i => $r) {
    $st->execute([$id, $i, $r['kategori'], $r['produkt'], $r['ursprung'], $r['vikt'], $r['enhet'], $r['pris'], $r['topp'] ? 1 : 0]);
  }
}

function order_superklipp(int $orderId): ?array {
  $st = db()->prepare('SELECT * FROM order_superklipp WHERE order_id = ?');
  $st->execute([$orderId]);
  return $st->fetch() ?: null;
}

function order_rader(int $orderId): array {
  $st = db()->prepare('SELECT * FROM order_rader WHERE order_id = ? ORDER BY sortering, id');
  $st->execute([$orderId]);
  return $st->fetchAll();
}

// Varorna i en superklipp-beställning, för kundens och adminens ordersida.
function sk_order_tabell(array $rader): string {
  $html = '<div class="sk-scroll"><table class="sk-table sk-order"><thead><tr><th>Vara</th><th class="num">Pris</th><th class="num">Kolli</th></tr></thead><tbody>';
  $kolli = 0;
  foreach ($rader as $r) {
    $kolli += (int)$r['antal'];
    $forp = sk_forpackning($r);
    $html .= '<tr><td>' . e($r['produkt']) . ($r['ursprung'] !== '' ? ' <span style="color:var(--ink-soft);">' . e($r['ursprung']) . '</span>' : '')
      . ($forp !== '' ? '<span class="sk-sub">' . e($forp) . '</span>' : '') . '</td>'
      . '<td class="pris" style="font-weight:500;">' . e(sk_pris_per($r)) . '</td>'
      . '<td class="pris">' . (int)$r['antal'] . '</td></tr>';
  }
  return $html . '<tr><td colspan="2" style="font-weight:600;">Totalt</td><td class="pris">' . $kolli . '</td></tr></tbody></table></div>';
}

// ---------- beställning från en veckolista (superklipp eller veckansklipp) ----------

// Läser antal kolli per rad från formuläret. $perId är listans rader per id.
// Felaktiga värden sparas som text så att kunden ser dem igen.
function lista_antal_fran_post(array $perId): array {
  $antal = [];
  $fel = [];
  $andrad = false;
  foreach ((array)($_POST['antal'] ?? []) as $radId => $varde) {
    $varde = is_scalar($varde) ? trim((string)$varde) : '';
    if ($varde === '' || $varde === '0') continue;
    if (!isset($perId[(int)$radId])) {
      $andrad = true;
      continue;
    }
    if (!ctype_digit($varde) || (int)$varde < 1 || (int)$varde > SK_MAX_ANTAL) {
      $fel['antal'] = 'Antal kolli måste vara ett heltal mellan 1 och ' . SK_MAX_ANTAL . '.';
      $antal[(int)$radId] = $varde;
      continue;
    }
    $antal[(int)$radId] = (int)$varde;
  }
  if ($andrad) $fel['andrad'] = 'Listan uppdaterades medan du fyllde i. Kontrollera antalen och skicka igen.';
  if (!$antal && !$fel) $fel['antal'] = 'Fyll i antal kolli på minst en vara.';
  return [$antal, $fel];
}

// Sparar beställningen med en kopia av varje rad och skickar mejl till admin och kund.
// $lista: ['etikett' => 'Superklipp v.46', 'superklipp_id' => 3] eller med 'veckansklipp_id'.
// Raderna har fälten produkt, ursprung, vikt, enhet, pris och sortering. Returnerar 0 om det inte gick att spara.
function lista_spara_order(array $user, array $lista, array $perId, array $antal, string $leveransdag, string $dag, string $leveransdatum, string $meddelande): int {
  global $CONFIG;
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare('INSERT INTO orders (user_id, foretag, kundgrupp, kontakt, telefon, epost, adress, leveransdag, kategorier, meddelande, status, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'\', ?, \'ny\', ?, ?)')
        ->execute([$user['id'], $user['foretag'], $user['kundgrupp'] ?? null, $user['kontakt'], $user['telefon'], $user['email'], $user['adress'],
                   $leveransdag, $meddelande, now(), now()]);
    $orderId = (int)$pdo->lastInsertId();
    $kolumner = ['order_id', 'superklipp_id', 'etikett', 'leveransdatum'];
    $varden = [$orderId, $lista['superklipp_id'] ?? null, $lista['etikett'], $leveransdatum];
    if (array_key_exists('veckansklipp_id', $lista)) {
      $kolumner[] = 'veckansklipp_id';
      $varden[] = $lista['veckansklipp_id'];
    }
    $pdo->prepare('INSERT INTO order_superklipp (' . implode(', ', $kolumner) . ') VALUES (' . implode(', ', array_fill(0, count($kolumner), '?')) . ')')
        ->execute($varden);
    $st = $pdo->prepare('INSERT INTO order_rader (order_id, sortering, produkt, ursprung, vikt, enhet, pris, antal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($antal as $radId => $n) {
      $r = $perId[$radId];
      $st->execute([$orderId, $r['sortering'], $r['produkt'], $r['ursprung'], $r['vikt'], $r['enhet'], $r['pris'], $n]);
    }
    $pdo->commit();
  } catch (PDOException $ex) {
    $pdo->rollBack();
    return 0;
  }

  $etikett = $lista['etikett'];
  $varor = [];
  foreach ($antal as $radId => $n) {
    $r = $perId[$radId];
    $varor[] = sprintf('%4d × %s', $n, $r['produkt'])
      . ($r['ursprung'] !== '' ? " ({$r['ursprung']})" : '')
      . (sk_forpackning($r) !== '' ? ', ' . sk_forpackning($r) : '')
      . ' – ' . sk_pris_per($r);
  }
  $kolli = array_sum($antal);

  send_mail($CONFIG['mail_to'], "$etikett #$orderId: {$user['foretag']} ($dag)", implode("\n", [
    "Ny beställning #$orderId på $etikett från ludvikaparti.se",
    str_repeat('-', 44),
    'Företag:            ' . $user['foretag'],
    'Kundgrupp:          ' . kundgrupp_text($user['kundgrupp'] ?? null),
    'Kontaktperson:      ' . $user['kontakt'],
    'Telefon:            ' . $user['telefon'],
    'E-post:             ' . $user['email'],
    'Leveransadress:     ' . $user['adress'],
    'Leveransdag:        ' . $dag,
    '',
    "Varor ($kolli kolli):",
    ...$varor,
    '',
    'Meddelande:',
    $meddelande !== '' ? $meddelande : '(inget meddelande)',
    '',
    str_repeat('-', 44),
    'Hantera beställningen: ' . $CONFIG['site_url'] . '/admin-order.php?id=' . $orderId,
    'Svara på det här mejlet för att svara kunden direkt.',
  ]), $user['email']);

  send_mail($user['email'], "Vi har tagit emot din beställning #$orderId ($etikett)", implode("\n", [
    "Hej {$user['kontakt']}!",
    '',
    "Tack för din beställning #$orderId från $etikett. Vi går igenom den och återkommer med bekräftelse.",
    '',
    'Leveransdag: ' . $dag,
    "Varor ($kolli kolli):",
    ...$varor,
    '',
    'Du kan följa beställningen under Mitt konto:',
    $CONFIG['site_url'] . '/order.php?id=' . $orderId,
    '',
    'Med vänlig hälsning',
    'Ludvika Partiaffär, 0240-183 55',
  ]), $CONFIG['mail_to']);

  return $orderId;
}

// ---------- inläsning ----------

// Tolkar ett pris som "32,95 kr", "32.95", "1 234,50 kr" eller "32:-". Returnerar null om det inte är ett pris.
function sk_pris(string $s): ?float {
  $s = mb_strtolower(str_replace(["\u{A0}", ' '], '', $s), 'UTF-8');
  $s = preg_replace('~/\p{L}+\.?$~u', '', $s);
  $s = str_replace(['sek', 'kr', ':-'], '', $s);
  $s = rtrim($s, '.');
  if (str_contains($s, ',') && str_contains($s, '.')) $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $s)) return null;
  $pris = (float)$s;
  return $pris > 0 ? $pris : null;
}

function sk_rad(string $kategori, string $produkt, string $ursprung, string $vikt, string $enhet, float $pris, bool $topp = false): array {
  return [
    'kategori' => enRad($kategori, 60),
    'produkt'  => enRad($produkt, 160),
    'ursprung' => mb_strtoupper(enRad($ursprung, 20), 'UTF-8'),
    'vikt'     => enRad($vikt, 30),
    'enhet'    => enRad($enhet, 20),
    'pris'     => $pris,
    'topp'     => $topp,
  ];
}

// Läser in en lista som klistrats in från Google Sheets (tabbseparerad) eller laddats upp som CSV.
// Rubrikraden (Produkt, Ursprung, Vikt/antal, Enhet, Pris) hittas automatiskt, rader med bara
// en kort text blir kategorier, och vecka och sista beställningsdag plockas ur texten runt tabellen.
function sk_las_in(string $text): array {
  $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
  if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
  $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);

  if (str_contains($text, "\t")) $avgr = "\t";
  else $avgr = substr_count($text, ';') > substr_count($text, ',') ? ';' : ',';

  $fh = fopen('php://temp', 'r+');
  fwrite($fh, $text);
  rewind($fh);
  $tabell = [];
  while (($rad = fgetcsv($fh, 0, $avgr, '"', '')) !== false) {
    if ($rad === [null]) continue;
    $tabell[] = array_map(fn($c) => trim(str_replace("\u{A0}", ' ', (string)$c)), $rad);
  }
  fclose($fh);

  $kol = null;
  $start = 0;
  foreach ($tabell as $i => $rad) {
    $map = [];
    foreach ($rad as $j => $c) {
      $l = mb_strtolower($c, 'UTF-8');
      if ($l === '') continue;
      if (!isset($map['produkt']) && str_starts_with($l, 'produkt')) $map['produkt'] = $j;
      elseif (!isset($map['ursprung']) && (str_starts_with($l, 'ursprung') || $l === 'land')) $map['ursprung'] = $j;
      elseif (!isset($map['vikt']) && (str_contains($l, 'vikt') || str_contains($l, 'antal') || str_contains($l, 'förp'))) $map['vikt'] = $j;
      elseif (!isset($map['enhet']) && str_starts_with($l, 'enhet')) $map['enhet'] = $j;
      elseif (!isset($map['pris']) && str_starts_with($l, 'pris')) $map['pris'] = $j;
    }
    if (isset($map['produkt'], $map['pris'])) {
      $kol = $map;
      $start = $i + 1;
      break;
    }
  }
  $hittadRubrik = $kol !== null;
  $kol ??= ['produkt' => 0, 'ursprung' => 1, 'vikt' => 2, 'enhet' => 3, 'pris' => 4];

  $info = ['vecka' => null, 'ar' => null, 'deadline' => null];
  $skanna = function (string $s) use (&$info): void {
    if ($info['deadline'] === null && preg_match('/(\d{4})-(\d{2})-(\d{2})\D{0,12}?(\d{1,2})[:.](\d{2})/', $s, $m)
        && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && (int)$m[4] < 24 && (int)$m[5] < 60) {
      $info['deadline'] = sprintf('%04d-%02d-%02d %02d:%02d:00', $m[1], $m[2], $m[3], $m[4], $m[5]);
    }
    if ($info['vecka'] === null && preg_match('/(?:^|[^\p{L}])v(?:ecka|\.)?\s*(\d{1,2})(?!\d)/iu', $s, $m)
        && (int)$m[1] >= 1 && (int)$m[1] <= 53) {
      $info['vecka'] = (int)$m[1];
      if (preg_match('/\b(20\d{2})\b(?!-)/', $s, $y)) $info['ar'] = (int)$y[1];
    }
  };
  for ($i = 0; $i < $start; $i++) $skanna(implode(' ', $tabell[$i]));

  $rader = [];
  $varningar = [];
  $kategori = '';
  $cell = fn(array $rad, string $k): string => isset($kol[$k]) ? ($rad[$kol[$k]] ?? '') : '';
  for ($i = $start, $n = count($tabell); $i < $n; $i++) {
    $rad = $tabell[$i];
    $ifyllda = array_values(array_filter($rad, fn($c) => $c !== ''));
    if (!$ifyllda) continue;
    $produkt = $cell($rad, 'produkt');
    $pris = sk_pris($cell($rad, 'pris'));

    if ($produkt !== '' && $pris !== null) {
      if (count($rader) >= SK_MAX_RADER) {
        $varningar[] = 'Listan får ha högst ' . SK_MAX_RADER . ' varor. Resten lästes inte in.';
        break;
      }
      $rader[] = sk_rad($kategori, $produkt, $cell($rad, 'ursprung'), $cell($rad, 'vikt'), $cell($rad, 'enhet'), $pris);
      continue;
    }
    if (count($ifyllda) === 1 && mb_strlen($ifyllda[0], 'UTF-8') <= 40 && !preg_match('/\d/', $ifyllda[0])) {
      $kategori = rtrim($ifyllda[0], ': ');
      continue;
    }
    $skanna(implode(' ', $ifyllda));
    if ($produkt !== '' && count($ifyllda) >= 2 && mb_strtolower($produkt, 'UTF-8') !== 'produkt') {
      $varningar[] = 'Raden "' . enRad($produkt, 60) . '" saknar ett giltigt pris och hoppades över.';
    }
  }

  if ($info['vecka'] !== null && $info['ar'] === null) {
    $info['ar'] = (int)date('o');
    if ($info['vecka'] < (int)date('W') - 26) $info['ar']++;
  }
  if (!$hittadRubrik && $rader) {
    $varningar[] = 'Hittade ingen rubrikrad med Produkt och Pris, så kolumnerna lästes som Produkt, Ursprung, Vikt/antal, Enhet, Pris.';
  }

  return ['rader' => $rader, 'varningar' => $varningar] + $info;
}

// Fälten för att klistra in eller ladda upp listan, används både för nya och befintliga superklipp.
function sk_import_falt(): string {
  return '<div class="field full">'
    . '<label for="klistra">Klistra in från Google Sheets</label>'
    . '<textarea id="klistra" name="klistra" rows="6" placeholder="Öppna superklippet i Google Sheets, markera allt (Ctrl+A), kopiera (Ctrl+C) och klistra in här (Ctrl+V)."></textarea>'
    . '<span class="hint">Rubrikraden (Produkt, Ursprung, Vikt/antal, Enhet, Pris), kategorierna och veckan hittas automatiskt. Logga och färger följer inte med, det gör inget.</span>'
    . '</div>'
    . '<div class="field full" style="margin-top:12px;">'
    . '<label for="fil">…eller ladda upp en CSV-fil</label>'
    . '<input type="file" id="fil" name="fil" accept=".csv,.tsv,.txt,text/csv,text/plain">'
    . '<span class="hint">I Google Sheets: Arkiv → Ladda ned → Kommaavgränsade värden (.csv).</span>'
    . '</div>';
}

// ---------- utskick ----------

function sk_mejl(array $sk, array $rader): array {
  global $CONFIG;
  $url = rtrim($CONFIG['site_url'], '/');
  $vecka = (int)$sk['vecka'];
  $deadline = sk_deadline_text($sk['deadline']);

  $text = ['Hej bästa kund,', '', "Här kommer vårt superklipp för vecka $vecka 🥳", ''];
  $topp = array_filter($rader, fn($r) => (int)$r['topp'] === 1);
  if ($topp) {
    $text[] = 'Veckans toppklipp:';
    foreach ($topp as $r) {
      $text[] = '• ' . $r['produkt'] . ($r['ursprung'] !== '' ? " ({$r['ursprung']})" : '') . ' – ' . sk_pris_per($r);
    }
    $text[] = '';
  }
  array_push($text,
    'Se hela listan och beställ direkt på hemsidan:',
    "$url/superklipp.php",
    '',
    'Logga in med ditt kundkonto för att se priserna. Inget konto än? Skapa ett här:',
    "$url/registrera.php",
    '',
    "Beställ senast $deadline, på hemsidan eller på telefon 0240-183 55.",
    '',
    'Vi på Ludvika Partiaffär önskar er en trevlig helg!',
    '',
    'Med vänliga hälsningar / Kind regards',
    '📧 info@ludvikapartiaffar.se',
    '📞 0240-183 55',
    '🌐 ludvikapartiaffar.se',
    '📍 Ludvika Partiaffär, Fläderstigen 2, 771 43 Ludvika, Sverige',
  );

  return ["Superklipp v.$vecka – beställ senast $deadline", implode("\n", $text)];
}
