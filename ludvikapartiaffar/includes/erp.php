<?php
// Affärssystemet: artikelregister, kundregister, priser och personalens roller.

// Prislistor som i Partihandel. En kund utan prisgrupp betalar grundpriset (utpriset).
const PRISGRUPPER = [
  1 => ['namn' => 'Grupp 1', 'for' => 'Butiker'],
  2 => ['namn' => 'Grupp 2', 'for' => 'Restauranger och pizzerior'],
  3 => ['namn' => 'Grupp 3', 'for' => 'Kommuner'],
];
// Kundgrupperna i kundregistret. Z och T kommer från Partihandel och har inget eget namn än.
const REGISTER_KUNDGRUPPER = KUNDGRUPPER + ['Z' => 'Grupp Z', 'T' => 'Grupp T'];
const MOMSSATSER = [6, 12, 25, 0];
const ENHETER = ['st', 'kg', 'förp', 'ask', 'hink', 'låda', 'kolli', 'fl', 'pkt'];

// Allt man kan ge en person behörighet till, grupperat som på personalsidan.
// Personal och behörigheter ingår inte: det har bara administratörer.
const BEHORIGHETER = [
  'Försäljning' => [
    'order' => 'Se order',
    'order_andra' => 'Lägga och ändra order',
    'plock' => 'Plocka order',
    'plock_alla' => 'Se alla order på plocksidan, inte bara sina egna',
  ],
  'Kunder' => [
    'kunder' => 'Kundregistret',
    'webbkonton' => 'Webbkonton och godkännande av nya kunder',
  ],
  'Sortiment och priser' => [
    'artiklar' => 'Artiklar och prislista',
    'priser' => 'Ändra försäljningspriser',
    'inpris_se' => 'Se inköpspriser och vinst',
    'inpris_andra' => 'Ändra inköpspriser',
  ],
  'Erbjudanden' => [
    'erbjudanden' => 'Superklipp och Veckansklipp',
  ],
  'Administration' => [
    'import' => 'Import av artiklar, priser och kunder',
  ],
];

// Personalens roller är mallar: varje person kan få egna kryss. Kunderna har rollen 'kund' och kommer aldrig åt adminportalen.
const ROLLER = [
  'admin'   => ['namn' => 'Administratör', 'text' => 'Allt, även personal, behörigheter och import.',
                'kan' => ['order', 'order_andra', 'plock', 'plock_alla', 'kunder', 'webbkonton', 'artiklar', 'priser', 'inpris_se', 'inpris_andra', 'erbjudanden', 'import', 'personal']],
  'kontor'  => ['namn' => 'Kontor och sälj', 'text' => 'Order, kundregister, artiklar och priser, erbjudanden och webbkonton.',
                'kan' => ['order', 'order_andra', 'plock', 'plock_alla', 'kunder', 'webbkonton', 'artiklar', 'priser', 'inpris_se', 'inpris_andra', 'erbjudanden']],
  'saljare' => ['namn' => 'Säljare', 'text' => 'Lägger order och ser kunder och prislistan, men ändrar inte priser och ser inte inköpspriser.',
                'kan' => ['order', 'order_andra', 'kunder', 'artiklar']],
  'ekonomi' => ['namn' => 'Ekonomi', 'text' => 'Ser order, kunder, priser och vinst. Ändrar inget i order eller priser.',
                'kan' => ['order', 'kunder', 'artiklar', 'inpris_se']],
  'lager'   => ['namn' => 'Lager och plock', 'text' => 'Ser order, plockar och skriver ut plocksedlar och följesedlar.',
                'kan' => ['order', 'plock', 'plock_alla']],
];

const ERP_SCHEMA = [
  "CREATE TABLE IF NOT EXISTS artiklar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikelnr VARCHAR(40) NOT NULL,
    namn VARCHAR(200) NOT NULL,
    enhet VARCHAR(12) NOT NULL DEFAULT 'st',
    vikt DECIMAL(10,3) NULL,
    antal_per_kolli DECIMAL(10,3) NULL,
    produktgrupp VARCHAR(60) NOT NULL DEFAULT '',
    artikelgrupp VARCHAR(20) NOT NULL DEFAULT '',
    inpris DECIMAL(10,2) NULL,
    utpris DECIMAL(10,2) NULL,
    moms DECIMAL(4,2) NOT NULL DEFAULT 6.00,
    ursprung VARCHAR(40) NOT NULL DEFAULT '',
    gtin VARCHAR(20) NOT NULL DEFAULT '',
    lagerartikel TINYINT(1) NOT NULL DEFAULT 0,
    hel_forpackning TINYINT(1) NOT NULL DEFAULT 0,
    losvikt TINYINT(1) NOT NULL DEFAULT 0,
    viktartikel TINYINT(1) NOT NULL DEFAULT 0,
    webbshop TINYINT(1) NOT NULL DEFAULT 0,
    fakturatext VARCHAR(255) NOT NULL DEFAULT '',
    kommentar VARCHAR(255) NOT NULL DEFAULT '',
    visma_id VARCHAR(40) NULL,
    visma_kontering_id VARCHAR(40) NULL,
    visma_enhet_id VARCHAR(40) NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    skapad DATETIME NOT NULL,
    uppdaterad DATETIME NOT NULL,
    uppdaterad_av VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uq_artikelnr (artikelnr),
    KEY idx_namn (namn),
    KEY idx_produktgrupp (produktgrupp),
    KEY idx_aktiv (aktiv, namn)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS artikel_priser (
    artikel_id INT UNSIGNED NOT NULL,
    prisgrupp TINYINT UNSIGNED NOT NULL,
    pris DECIMAL(10,2) NOT NULL,
    uppdaterad DATETIME NOT NULL,
    PRIMARY KEY (artikel_id, prisgrupp),
    CONSTRAINT fk_ap_artikel FOREIGN KEY (artikel_id) REFERENCES artiklar(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS kunder (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kundnr VARCHAR(40) NOT NULL,
    namn VARCHAR(160) NOT NULL,
    orgnr VARCHAR(20) NOT NULL DEFAULT '',
    momsnr VARCHAR(20) NOT NULL DEFAULT '',
    kundgrupp CHAR(1) NULL,
    prisgrupp TINYINT UNSIGNED NULL,
    moderbolag VARCHAR(160) NOT NULL DEFAULT '',
    lev_namn VARCHAR(160) NOT NULL DEFAULT '',
    lev_adress VARCHAR(160) NOT NULL DEFAULT '',
    lev_postnr VARCHAR(12) NOT NULL DEFAULT '',
    lev_ort VARCHAR(80) NOT NULL DEFAULT '',
    land CHAR(2) NOT NULL DEFAULT 'SE',
    fakt_namn VARCHAR(160) NOT NULL DEFAULT '',
    fakt_adress VARCHAR(160) NOT NULL DEFAULT '',
    fakt_postnr VARCHAR(12) NOT NULL DEFAULT '',
    fakt_ort VARCHAR(80) NOT NULL DEFAULT '',
    telefon VARCHAR(40) NOT NULL DEFAULT '',
    foljesedel_epost VARCHAR(255) NOT NULL DEFAULT '',
    foljesedel_kopia VARCHAR(255) NOT NULL DEFAULT '',
    faktura_epost VARCHAR(255) NOT NULL DEFAULT '',
    faktura_kopia VARCHAR(255) NOT NULL DEFAULT '',
    paminnelse_epost VARCHAR(255) NOT NULL DEFAULT '',
    var_referens VARCHAR(80) NOT NULL DEFAULT '',
    er_referens VARCHAR(120) NOT NULL DEFAULT '',
    referens VARCHAR(120) NOT NULL DEFAULT '',
    gln VARCHAR(20) NOT NULL DEFAULT '',
    tur VARCHAR(40) NOT NULL DEFAULT '',
    betalningsvillkor SMALLINT UNSIGNED NULL,
    fakturaavgift DECIMAL(8,2) NULL,
    visma_id VARCHAR(40) NULL,
    visma_betalvillkor_id VARCHAR(40) NULL,
    anteckning TEXT NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    skapad DATETIME NOT NULL,
    uppdaterad DATETIME NOT NULL,
    uppdaterad_av VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uq_kundnr (kundnr),
    KEY idx_namn (namn),
    KEY idx_orgnr (orgnr),
    KEY idx_grupp (kundgrupp, prisgrupp)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS kund_priser (
    kund_id INT UNSIGNED NOT NULL,
    artikel_id INT UNSIGNED NOT NULL,
    pris DECIMAL(10,2) NOT NULL,
    uppdaterad DATETIME NOT NULL,
    PRIMARY KEY (kund_id, artikel_id),
    KEY idx_artikel (artikel_id),
    CONSTRAINT fk_kp_kund FOREIGN KEY (kund_id) REFERENCES kunder(id) ON DELETE CASCADE,
    CONSTRAINT fk_kp_artikel FOREIGN KEY (artikel_id) REFERENCES artiklar(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS prislogg (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artikel_id INT UNSIGNED NOT NULL,
    typ VARCHAR(10) NOT NULL,
    prisgrupp TINYINT UNSIGNED NULL,
    kund_id INT UNSIGNED NULL,
    fore DECIMAL(10,2) NULL,
    efter DECIMAL(10,2) NULL,
    av VARCHAR(120) NOT NULL,
    kalla VARCHAR(12) NOT NULL,
    tid DATETIME NOT NULL,
    KEY idx_artikel (artikel_id, tid),
    KEY idx_kund (kund_id, tid),
    CONSTRAINT fk_pl_artikel FOREIGN KEY (artikel_id) REFERENCES artiklar(id) ON DELETE CASCADE,
    CONSTRAINT fk_pl_kund FOREIGN KEY (kund_id) REFERENCES kunder(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS importlogg (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    typ VARCHAR(20) NOT NULL,
    filnamn VARCHAR(200) NOT NULL,
    av VARCHAR(120) NOT NULL,
    nya INT UNSIGNED NOT NULL DEFAULT 0,
    andrade INT UNSIGNED NOT NULL DEFAULT 0,
    oforandrade INT UNSIGNED NOT NULL DEFAULT 0,
    overhoppade INT UNSIGNED NOT NULL DEFAULT 0,
    tid DATETIME NOT NULL,
    KEY idx_tid (tid)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
];

// Skapar tabellerna första gången en sida i affärssystemet används.
function erp_schema(): void {
  static $klar = false;
  if ($klar) return;
  $klar = true;
  $pdo = db();
  try {
    $pdo->query('SELECT 1 FROM importlogg LIMIT 0');
    $pdo->query('SELECT kund_id FROM users LIMIT 0');
    $pdo->query('SELECT kalla FROM orders LIMIT 0');
    $pdo->query('SELECT behorigheter FROM users LIMIT 0');
    $pdo->query('SELECT belopp FROM order_rader LIMIT 0');
    return;
  } catch (PDOException $e) {
    // Något saknas, skapas nedan.
  }
  foreach (ERP_SCHEMA as $sql) $pdo->exec($sql);
  try {
    $pdo->query('SELECT kund_id FROM users LIMIT 0');
  } catch (PDOException $e) {
    // Ett webbkonto kan kopplas till en kund i kundregistret.
    $pdo->exec('ALTER TABLE users ADD COLUMN kund_id INT UNSIGNED NULL, ADD KEY idx_kund (kund_id),
                ADD CONSTRAINT fk_users_kund FOREIGN KEY (kund_id) REFERENCES kunder(id) ON DELETE SET NULL');
  }
  try {
    $pdo->query('SELECT behorigheter FROM users LIMIT 0');
  } catch (PDOException $e) {
    // Egna behörigheter och grupper per person. Null betyder enligt rollen och alla grupper.
    $pdo->exec('ALTER TABLE users ADD COLUMN behorigheter VARCHAR(500) NULL, ADD COLUMN kundgrupper VARCHAR(20) NULL, ADD COLUMN artikelgrupper VARCHAR(60) NULL');
  }
  // Order och orderrader får kolumnerna för kund, artiklar, priser och plock.
  superklipp_schema();
  veckansklipp_schema();
  order_kolumner($pdo);
}

// ---------- roller ----------

function ar_personal(?array $u): bool {
  return $u !== null && isset(ROLLER[$u['role'] ?? '']);
}

// Det personen får göra: rollens mall, eller egna kryss om administratören har ändrat dem.
// Administratörer har alltid allt, så att ingen kan låsa ute sig.
function behorigheter(?array $u): array {
  if (!ar_personal($u)) return [];
  $egna = $u['behorigheter'] ?? null;
  if ($u['role'] === 'admin' || $egna === null) return ROLLER[$u['role']]['kan'];
  $giltiga = array_keys(array_merge(...array_values(BEHORIGHETER)));
  return array_values(array_intersect($giltiga, explode(',', $egna)));
}

function far(?array $u, string $vad): bool {
  return in_array($vad, behorigheter($u), true);
}

// Kundgrupper och artikelgrupper personen jobbar med. Null betyder alla.
function anv_grupper(?array $u, string $vilka): ?array {
  if (!ar_personal($u) || $u['role'] === 'admin') return null;
  $v = trim((string)($u[$vilka] ?? ''));
  return $v === '' ? null : array_values(array_filter(explode(',', $v), fn($g) => $g !== ''));
}

// SQL-villkor som begränsar till personens kundgrupper. $uttryck är kolumnen med kundgruppen.
function grupp_villkor(?array $u, string $uttryck, string $vilka = 'kundgrupper'): array {
  $g = anv_grupper($u, $vilka);
  if ($g === null) return ['1=1', []];
  return ["$uttryck IN (" . implode(',', array_fill(0, count($g), '?')) . ')', $g];
}

function far_grupp(?array $u, ?string $grupp, string $vilka = 'kundgrupper'): bool {
  $g = anv_grupper($u, $vilka);
  return $g === null || ($grupp !== null && in_array($grupp, $g, true));
}

function require_behorighet(string $vad): array {
  $u = require_login();
  if (!far($u, $vad)) {
    http_response_code(403);
    exit('Du har inte behörighet till den här sidan.');
  }
  return $u;
}

function roll_namn(string $roll): string {
  return ROLLER[$roll]['namn'] ?? $roll;
}

// "Kontor och sälj" eller "Kontor och sälj, anpassad" när personen har egna kryss.
function roll_text(array $u): string {
  return roll_namn($u['role']) . ($u['role'] !== 'admin' && ($u['behorigheter'] ?? null) !== null ? ', anpassad' : '');
}

// Namnet som sparas i historiken när någon ändrar något.
function vem(array $u): string {
  return mb_substr($u['kontakt'] !== '' ? $u['kontakt'] : $u['email'], 0, 120, 'UTF-8');
}

// ---------- tal och priser ----------

// Läser "129,95", "129.95", "1 299,50 kr" eller "1,299.50". Tom text ger null, ogiltig text false.
function tal_ur_text(string $s): float|null|false {
  $s = str_ireplace(["\u{A0}", "\u{202F}", ' ', 'kr', 'sek', ':-', '%'], '', trim($s));
  if ($s === '') return null;
  $komma = strrpos($s, ',');
  $punkt = strrpos($s, '.');
  if ($komma !== false && $punkt !== false) {
    // Den sista avgränsaren är decimaltecknet, den andra skiljer tusental.
    $s = $komma > $punkt ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
  } else {
    $s = str_replace(',', '.', $s);
  }
  if (!preg_match('/^-?\d{1,9}(\.\d+)?$/', $s)) return false;
  return (float)$s;
}

// Ett pris i kronor: 0 till 999 999,99, avrundat till ören.
function pris_ur_text(string $s): float|null|false {
  $t = tal_ur_text($s);
  if ($t === null || $t === false) return $t;
  if ($t < 0 || $t > 999999.99) return false;
  return round($t, 2);
}

// Momsen som procent. Partihandel skriver 6 % som 1.060.
function moms_ur_text(string $s): ?float {
  $t = tal_ur_text($s);
  if ($t === null || $t === false) return null;
  if ($t > 0 && $t < 1) $t *= 100;
  elseif ($t >= 1 && $t < 2) $t = ($t - 1) * 100;
  $t = round($t, 2);
  return in_array($t, array_map('floatval', MOMSSATSER), true) ? $t : null;
}

function pris_text(float|string|null $p): string {
  return $p === null || $p === '' ? '' : number_format((float)$p, 2, ',', ' ');
}

function tal_text(float|string|null $t): string {
  if ($t === null || $t === '') return '';
  $s = rtrim(rtrim(number_format((float)$t, 3, ',', ' '), '0'), ',');
  return $s === '' ? '0' : $s;
}

function samma_pris(?float $a, ?float $b): bool {
  if ($a === null || $b === null) return $a === $b;
  return abs($a - $b) < 0.005;
}

function prisgrupp_text(?int $g): string {
  return $g !== null && isset(PRISGRUPPER[$g]) ? PRISGRUPPER[$g]['namn'] : 'Grundpris';
}

// "grundpriset" eller "priserna i Grupp 2", efter kundens prisgrupp.
function betalar_text(array $kund): string {
  $g = $kund['prisgrupp'] ?? null;
  return $g === null || !isset(PRISGRUPPER[(int)$g]) ? 'grundpriset' : 'priserna i ' . PRISGRUPPER[(int)$g]['namn'];
}

function register_grupp_text(?string $k): string {
  return $k !== null && isset(REGISTER_KUNDGRUPPER[$k]) ? $k . ' – ' . REGISTER_KUNDGRUPPER[$k] : 'Ej angiven';
}

// Kollistorleken som i Partihandel: kg per kolli för kg-varor, annars antal per kolli.
function kolli_text(array $a): string {
  $t = $a['enhet'] === 'kg' ? $a['vikt'] : $a['antal_per_kolli'];
  return $t === null ? '' : tal_text($t);
}

// Täckningsgrad i procent, eller null om inpris eller pris saknas.
function marginal(?float $inpris, ?float $pris): ?float {
  if ($inpris === null || $pris === null || $pris <= 0) return null;
  return round(($pris - $inpris) / $pris * 100, 1);
}

function logga_pris(int $artikelId, string $typ, ?int $grupp, ?int $kundId, ?float $fore, ?float $efter, string $av, string $kalla): void {
  if (samma_pris($fore, $efter)) return;
  db()->prepare('INSERT INTO prislogg (artikel_id, typ, prisgrupp, kund_id, fore, efter, av, kalla, tid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$artikelId, $typ, $grupp, $kundId, $fore, $efter, $av, $kalla, now()]);
}

// Sätter grundpris eller inpris. Returnerar true om priset ändrades.
function satt_artikelpris(int $artikelId, string $falt, ?float $pris, string $av, string $kalla = 'hand'): bool {
  if (!in_array($falt, ['utpris', 'inpris'], true)) return false;
  $st = db()->prepare("SELECT $falt FROM artiklar WHERE id = ?");
  $st->execute([$artikelId]);
  $fore = $st->fetchColumn();
  $fore = $fore === null || $fore === false ? null : (float)$fore;
  if (samma_pris($fore, $pris)) return false;
  db()->prepare("UPDATE artiklar SET $falt = ?, uppdaterad = ?, uppdaterad_av = ? WHERE id = ?")->execute([$pris, now(), $av, $artikelId]);
  logga_pris($artikelId, $falt, null, null, $fore, $pris, $av, $kalla);
  return true;
}

// Sätter priset för en prisgrupp. Null tar bort det, då gäller grundpriset.
function satt_grupppris(int $artikelId, int $grupp, ?float $pris, string $av, string $kalla = 'hand'): bool {
  if (!isset(PRISGRUPPER[$grupp])) return false;
  $st = db()->prepare('SELECT pris FROM artikel_priser WHERE artikel_id = ? AND prisgrupp = ?');
  $st->execute([$artikelId, $grupp]);
  $fore = $st->fetchColumn();
  $fore = $fore === false ? null : (float)$fore;
  if (samma_pris($fore, $pris)) return false;
  if ($pris === null) {
    db()->prepare('DELETE FROM artikel_priser WHERE artikel_id = ? AND prisgrupp = ?')->execute([$artikelId, $grupp]);
  } else {
    db()->prepare('INSERT INTO artikel_priser (artikel_id, prisgrupp, pris, uppdaterad) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE pris = ?, uppdaterad = ?')->execute([$artikelId, $grupp, $pris, now(), $pris, now()]);
  }
  logga_pris($artikelId, 'grupp', $grupp, null, $fore, $pris, $av, $kalla);
  return true;
}

// Sätter kundens eget pris på en artikel. Null tar bort det.
function satt_kundpris(int $kundId, int $artikelId, ?float $pris, string $av, string $kalla = 'hand'): bool {
  $st = db()->prepare('SELECT pris FROM kund_priser WHERE kund_id = ? AND artikel_id = ?');
  $st->execute([$kundId, $artikelId]);
  $fore = $st->fetchColumn();
  $fore = $fore === false ? null : (float)$fore;
  if (samma_pris($fore, $pris)) return false;
  if ($pris === null) {
    db()->prepare('DELETE FROM kund_priser WHERE kund_id = ? AND artikel_id = ?')->execute([$kundId, $artikelId]);
  } else {
    db()->prepare('INSERT INTO kund_priser (kund_id, artikel_id, pris, uppdaterad) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE pris = ?, uppdaterad = ?')->execute([$kundId, $artikelId, $pris, now(), $pris, now()]);
  }
  logga_pris($artikelId, 'kund', null, $kundId, $fore, $pris, $av, $kalla);
  return true;
}

// Kolumner och join som räknar ut kundens pris: kundpris, annars kundens prisgrupp, annars grundpris.
// Används med parametrarna [kund-id, prisgrupp] före sökningens egna parametrar.
function kundpris_sql(): array {
  return [
    'kp.pris AS kundpris, kg.pris AS kundgrupppris, COALESCE(kp.pris, kg.pris, a.utpris) AS kundens_pris,
     CASE WHEN kp.pris IS NOT NULL THEN \'kund\' WHEN kg.pris IS NOT NULL THEN \'grupp\' ELSE \'grund\' END AS priskalla',
    'LEFT JOIN kund_priser kp ON kp.artikel_id = a.id AND kp.kund_id = ?
     LEFT JOIN artikel_priser kg ON kg.artikel_id = a.id AND kg.prisgrupp = ?',
  ];
}

// Priset en kund betalar för en artikel och var det kommer ifrån.
function pris_for_kund(array $kund, int $artikelId): ?array {
  [$kol, $join] = kundpris_sql();
  $st = db()->prepare("SELECT a.utpris, $kol FROM artiklar a $join WHERE a.id = ?");
  $st->execute([(int)$kund['id'], (int)($kund['prisgrupp'] ?? 0), $artikelId]);
  $r = $st->fetch();
  if (!$r || $r['kundens_pris'] === null) return null;
  return ['pris' => (float)$r['kundens_pris'], 'kalla' => $r['priskalla']];
}

function priskalla_text(string $kalla, ?int $prisgrupp): string {
  return match ($kalla) {
    'kund' => 'Kundpris',
    'grupp' => prisgrupp_text($prisgrupp),
    default => 'Grundpris',
  };
}

// ---------- uppslag ----------

function artikel_hamta(int $id): ?array {
  $st = db()->prepare('SELECT * FROM artiklar WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function artikel_via_nr(string $nr): ?array {
  $st = db()->prepare('SELECT * FROM artiklar WHERE artikelnr = ?');
  $st->execute([trim($nr)]);
  return $st->fetch() ?: null;
}

function kund_hamta(int $id): ?array {
  $st = db()->prepare('SELECT * FROM kunder WHERE id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function kund_via_nr(string $nr): ?array {
  $st = db()->prepare('SELECT * FROM kunder WHERE kundnr = ?');
  $st->execute([trim($nr)]);
  return $st->fetch() ?: null;
}

function grupppriser(int $artikelId): array {
  $st = db()->prepare('SELECT prisgrupp, pris FROM artikel_priser WHERE artikel_id = ?');
  $st->execute([$artikelId]);
  $ut = array_fill_keys(array_keys(PRISGRUPPER), null);
  foreach ($st as $r) $ut[(int)$r['prisgrupp']] = (float)$r['pris'];
  return $ut;
}

// Länk som behåller sidans filter och byter ut några av dem.
function adm_lank(string $sida, array $filter, array $andra = []): string {
  $q = array_filter(array_merge($filter, $andra), fn($x) => $x !== null && $x !== '' && $x !== 0);
  return $sida . ($q ? '?' . http_build_query($q) : '');
}

function sida_navigering(string $sida, array $filter, int $nu, int $antalSidor): string {
  if ($antalSidor <= 1) return '';
  $html = '<nav class="adm-sidor" aria-label="Sidor">';
  if ($nu > 1) $html .= '<a href="' . e(adm_lank($sida, $filter, ['sida' => $nu - 1])) . '">&larr; Föregående</a>';
  $html .= '<span>Sida ' . $nu . ' av ' . $antalSidor . '</span>';
  if ($nu < $antalSidor) $html .= '<a href="' . e(adm_lank($sida, $filter, ['sida' => $nu + 1])) . '">Nästa &rarr;</a>';
  return $html . '</nav>';
}
