<?php
// Fakturering: fakturautkast av en eller flera order, som skickas till Spiris (Visma eEkonomi).
// Spiris gör om utkastet till en faktura och skickar den till kunden. Fakturanumret sätts i Spiris.

// Order som kan faktureras: plockade eller levererade, och inte redan på ett utkast.
const FAKTURA_KLARA = ['plockad', 'levererad'];
const FAKTURA_STATUS = ['utkast' => 'Ej skickat', 'skickad' => 'Skickat till Spiris'];
// Moms på fakturaavgiften när den läggs till som en rad.
const FAKTURAAVGIFT_MOMS = 25.0;

// Adresserna till Spiris. Testmiljön är Vismas sandlåda för utvecklare.
const SPIRIS_MILJO = [
  'produktion' => ['api' => 'https://eaccountingapi.vismaonline.com/v2/', 'id' => 'https://identity.vismaonline.com/'],
  'test' => ['api' => 'https://eaccountingapi-sandbox.test.vismaonline.com/v2/', 'id' => 'https://identity-sandbox.test.vismaonline.com/'],
];
const SPIRIS_BEHORIGHET = 'ea:api offline_access ea:sales';
// Vismas id för tjänsten eEkonomi, som inloggningen hos Visma behöver.
const SPIRIS_TJANST = 'service:44643EB1-3F76-4C1C-A672-402AE8085934';

class SpirisFel extends RuntimeException {}

function faktura_schema(PDO $pdo): void {
  try {
    $pdo->query('SELECT faktura_id FROM orders LIMIT 0');
    return;
  } catch (PDOException $e) {
    // Skapas nedan.
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS installningar (
    namn VARCHAR(64) NOT NULL PRIMARY KEY,
    varde TEXT NOT NULL,
    uppdaterad DATETIME NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS fakturor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kund_id INT UNSIGNED NOT NULL,
    fakturadatum DATE NOT NULL,
    er_referens VARCHAR(120) NOT NULL DEFAULT '',
    var_referens VARCHAR(80) NOT NULL DEFAULT '',
    kundens_ordernr VARCHAR(60) NOT NULL DEFAULT '',
    betalningsvillkor SMALLINT UNSIGNED NULL,
    fakturaavgift DECIMAL(8,2) NULL,
    netto DECIMAL(12,2) NOT NULL DEFAULT 0,
    moms DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(12) NOT NULL DEFAULT 'utkast',
    spiris_id VARCHAR(40) NULL,
    skickad_at DATETIME NULL,
    skickad_av VARCHAR(120) NOT NULL DEFAULT '',
    fel TEXT NULL,
    skapad_av VARCHAR(120) NOT NULL DEFAULT '',
    skapad_at DATETIME NOT NULL,
    KEY idx_status (status, skapad_at),
    KEY idx_kund (kund_id),
    CONSTRAINT fk_faktura_kund FOREIGN KEY (kund_id) REFERENCES kunder(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci");
  $pdo->exec('ALTER TABLE orders ADD COLUMN faktura_id INT UNSIGNED NULL, ADD COLUMN status_fore_faktura VARCHAR(20) NULL,
              ADD KEY idx_faktura (faktura_id), ADD CONSTRAINT fk_orders_faktura FOREIGN KEY (faktura_id) REFERENCES fakturor(id) ON DELETE SET NULL');
}

// ---------- inställningar ----------

function installning(string $namn, ?string $standard = null): ?string {
  $st = db()->prepare('SELECT varde FROM installningar WHERE namn = ?');
  $st->execute([$namn]);
  $v = $st->fetchColumn();
  return $v === false ? $standard : $v;
}

function satt_installning(string $namn, ?string $varde): void {
  if ($varde === null) {
    db()->prepare('DELETE FROM installningar WHERE namn = ?')->execute([$namn]);
    return;
  }
  db()->prepare('INSERT INTO installningar (namn, varde, uppdaterad) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE varde = VALUES(varde), uppdaterad = VALUES(uppdaterad)')
      ->execute([$namn, $varde, now()]);
}

// Nycklar och lösenord till Spiris sparas krypterade, så att en kopia av databasen inte räcker för att komma åt bokföringen.
function hemlig_nyckel(): string {
  global $CONFIG;
  return hash('sha256', 'lp-spiris|' . ($CONFIG['setup_key'] ?? '') . '|' . ($CONFIG['db_pass'] ?? '') . '|' . ($CONFIG['db_name'] ?? ''), true);
}

function hemlig_spara(string $namn, ?string $varde): void {
  if ($varde === null || $varde === '') {
    satt_installning($namn, null);
    return;
  }
  $iv = random_bytes(12);
  $tagg = '';
  $krypt = openssl_encrypt($varde, 'aes-256-gcm', hemlig_nyckel(), OPENSSL_RAW_DATA, $iv, $tagg);
  satt_installning($namn, 'v1:' . base64_encode($iv . $tagg . $krypt));
}

function hemlig_las(string $namn): ?string {
  $v = installning($namn);
  if ($v === null || !str_starts_with($v, 'v1:')) return null;
  $b = base64_decode(substr($v, 3), true);
  if ($b === false || strlen($b) < 29) return null;
  $klar = openssl_decrypt(substr($b, 28), 'aes-256-gcm', hemlig_nyckel(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
  return $klar === false ? null : $klar;
}

// ---------- Spiris ----------

function spiris_miljo(): string {
  return installning('spiris_miljo') === 'test' ? 'test' : 'produktion';
}

// Adressen till Spiris. config.php kan peka om den, men bara för test på en egen dator.
function spiris_url(string $vad): string {
  global $CONFIG;
  $egen = $CONFIG['spiris_' . $vad . '_url'] ?? '';
  return $egen !== '' ? $egen : SPIRIS_MILJO[spiris_miljo()][$vad];
}

// Adressen Visma skickar tillbaka till efter inloggningen. Den ska anges när integrationen registreras hos Visma.
// Den slutar alltid på .php, så att den fungerar med och utan korta adresser.
function spiris_tillbaka_url(): string {
  global $CONFIG;
  return rtrim((string)$CONFIG['site_url'], '/') . '/admin-spiris.php';
}

function spiris_ansluten(): bool {
  return installning('spiris_refresh_token') !== null;
}

function spiris_inloggning_url(string $state): string {
  return spiris_url('id') . 'connect/authorize?' . http_build_query([
    'client_id' => (string)installning('spiris_client_id', ''),
    'redirect_uri' => spiris_tillbaka_url(),
    'scope' => SPIRIS_BEHORIGHET,
    'state' => $state,
    'response_type' => 'code',
    'prompt' => 'login',
    'acr_values' => SPIRIS_TJANST,
  ], '', '&', PHP_QUERY_RFC3986);
}

function spiris_http(string $metod, string $url, array $rubriker, ?string $kropp): array {
  if (!function_exists('curl_init')) throw new SpirisFel('Servern saknar stöd för att prata med Spiris (PHP-tillägget curl).');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $metod,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $rubriker,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 40,
  ]);
  if ($kropp !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $kropp);
  $svar = curl_exec($ch);
  if ($svar === false) {
    $fel = curl_error($ch);
    curl_close($ch);
    throw new SpirisFel('Kunde inte nå Spiris just nu. Försök igen om en stund. (' . $fel . ')');
  }
  $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  return [$kod, (string)$svar];
}

// Hämtar nycklar från Vismas inloggning, antingen med koden efter inloggningen eller med den sparade förnyelsenyckeln.
function spiris_token_anrop(array $falt): array {
  $id = (string)installning('spiris_client_id', '');
  $hemlig = (string)hemlig_las('spiris_client_secret');
  if ($id === '' || $hemlig === '') throw new SpirisFel('Client ID och Client secret från Visma saknas. Fyll i dem under Inställningar → Spiris.');
  [$kod, $svar] = spiris_http('POST', spiris_url('id') . 'connect/token', [
    'Authorization: Basic ' . base64_encode($id . ':' . $hemlig),
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
  ], http_build_query($falt, '', '&'));
  $json = json_decode($svar, true);
  if ($kod !== 200 || !is_array($json) || empty($json['access_token'])) {
    $fel = is_array($json) ? (string)($json['error'] ?? '') : '';
    if ($fel === 'invalid_grant' && ($falt['grant_type'] ?? '') === 'refresh_token') {
      spiris_koppla_fran();
      throw new SpirisFel('Anslutningen till Spiris har gått ut. En administratör behöver ansluta igen under Inställningar → Spiris.');
    }
    if ($fel === 'invalid_client') throw new SpirisFel('Visma godkände inte Client ID eller Client secret. Kontrollera dem under Inställningar → Spiris.');
    throw new SpirisFel('Visma svarade med fel ' . $kod . ($fel !== '' ? ' (' . $fel . ')' : '') . ' vid inloggningen.');
  }
  return $json;
}

function spiris_spara_token(array $t): void {
  hemlig_spara('spiris_access_token', (string)$t['access_token']);
  if (!empty($t['refresh_token'])) hemlig_spara('spiris_refresh_token', (string)$t['refresh_token']);
  satt_installning('spiris_token_utgar', (string)(time() + max(60, (int)($t['expires_in'] ?? 3600))));
}

function spiris_koppla_fran(): void {
  foreach (['spiris_access_token', 'spiris_refresh_token', 'spiris_token_utgar', 'spiris_foretag', 'spiris_ansluten_av', 'spiris_ansluten_at'] as $n) satt_installning($n, null);
}

// En giltig nyckel för anrop. Förnyas när den snart går ut. Låset hindrar två samtidiga förnyelser.
function spiris_token(bool $tvinga = false): string {
  $t = hemlig_las('spiris_access_token');
  if (!$tvinga && $t !== null && (int)installning('spiris_token_utgar', '0') > time() + 60) return $t;
  if (!spiris_ansluten()) throw new SpirisFel('Systemet är inte anslutet till Spiris än. En administratör ansluter under Inställningar → Spiris.');
  $las = db()->query("SELECT GET_LOCK('lp_spiris_token', 15)")->fetchColumn();
  try {
    if (!$tvinga) {
      $t = hemlig_las('spiris_access_token');
      if ($t !== null && (int)installning('spiris_token_utgar', '0') > time() + 60) return $t;
    }
    $forny = hemlig_las('spiris_refresh_token');
    if ($forny === null) {
      spiris_koppla_fran();
      throw new SpirisFel('Anslutningen till Spiris kunde inte läsas. En administratör behöver ansluta igen under Inställningar → Spiris.');
    }
    $svar = spiris_token_anrop(['grant_type' => 'refresh_token', 'refresh_token' => $forny]);
    spiris_spara_token($svar);
    return (string)$svar['access_token'];
  } finally {
    if ($las) db()->query("SELECT RELEASE_LOCK('lp_spiris_token')");
  }
}

function spiris_felmeddelande(int $kod, mixed $json): string {
  $delar = [];
  if (is_array($json)) {
    foreach (['ErrorMessage', 'Message', 'DeveloperErrorMessage', 'error_description'] as $k) {
      if (!empty($json[$k]) && is_string($json[$k]) && !in_array($json[$k], $delar, true)) $delar[] = $json[$k];
    }
    foreach ((array)($json['Errors'] ?? []) as $e) {
      if (is_array($e) && !empty($e['Message'])) $delar[] = (!empty($e['Field']) ? $e['Field'] . ': ' : '') . $e['Message'];
    }
    foreach ((array)($json['ValidationErrors'] ?? $json['ModelState'] ?? []) as $falt => $v) {
      foreach ((array)$v as $m) if (is_string($m)) $delar[] = (is_string($falt) ? $falt . ': ' : '') . $m;
    }
  }
  $text = match (true) {
    $kod === 401 => 'Spiris godkände inte inloggningen.',
    $kod === 403 => 'Kontot i Spiris saknar behörighet för det här.',
    $kod === 404 => 'Det finns inte i Spiris.',
    $kod === 429 => 'Spiris tar emot för många anrop just nu. Vänta en minut och försök igen.',
    $kod >= 500 => 'Spiris har tekniska problem just nu. Försök igen om en stund.',
    default => 'Spiris tog inte emot uppgifterna.',
  };
  return $text . ($delar ? ' ' . mb_strimwidth(implode(' ', $delar), 0, 600, '…', 'UTF-8') : '') . " (fel $kod)";
}

function spiris_api(string $metod, string $sokvag, ?array $data = null, bool $igen = true): array {
  $kropp = $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
  $rubriker = ['Authorization: Bearer ' . spiris_token(), 'Accept: application/json'];
  if ($kropp !== null) $rubriker[] = 'Content-Type: application/json';
  [$kod, $svar] = spiris_http($metod, spiris_url('api') . ltrim($sokvag, '/'), $rubriker, $kropp);
  if ($kod === 401 && $igen) {
    spiris_token(true);
    return spiris_api($metod, $sokvag, $data, false);
  }
  $json = $svar === '' ? [] : json_decode($svar, true);
  if ($kod >= 400) throw new SpirisFel(spiris_felmeddelande($kod, $json), $kod);
  return is_array($json) ? $json : [];
}

function odata_text(string $s): string {
  return "'" . str_replace("'", "''", $s) . "'";
}

function spiris_hitta(string $resurs, string $falt, string $varde): ?array {
  $svar = spiris_api('GET', $resurs . '?$filter=' . rawurlencode($falt . ' eq ' . odata_text($varde)) . '&$pagesize=2');
  $data = $svar['Data'] ?? (array_is_list($svar) ? $svar : []);
  return is_array($data[0] ?? null) ? $data[0] : null;
}

// Kundens id i Spiris. Kunder från Partihandel har det redan. Nya kunder letas upp med kundnumret.
function spiris_kund_id(array $kund): string {
  if (!empty($kund['visma_id'])) return (string)$kund['visma_id'];
  $k = spiris_hitta('customers', 'CustomerNumber', (string)$kund['kundnr']);
  if (!$k || empty($k['Id'])) {
    throw new SpirisFel('Kunden ' . $kund['kundnr'] . ' ' . $kund['namn'] . ' finns inte i Spiris. Lägg upp kunden i Spiris med kundnummer ' . $kund['kundnr'] . ' och skicka igen.');
  }
  db()->prepare('UPDATE kunder SET visma_id = ? WHERE id = ?')->execute([$k['Id'], $kund['id']]);
  return (string)$k['Id'];
}

// Artikelns id i Spiris, från artikelregistret eller uppslaget på artikelnumret. Null om den inte finns.
function spiris_artikel_id(?int $artikelId, string $artikelnr): ?string {
  $a = $artikelId ? artikel_hamta($artikelId) : null;
  $a = $a ?: ($artikelnr !== '' ? artikel_via_nr($artikelnr) : null);
  if ($a && !empty($a['visma_id'])) return (string)$a['visma_id'];
  $nr = $a['artikelnr'] ?? $artikelnr;
  if ($nr === '') return null;
  $s = spiris_hitta('articles', 'Number', $nr);
  if (!$s || empty($s['Id'])) return null;
  if ($a) db()->prepare('UPDATE artiklar SET visma_id = ? WHERE id = ?')->execute([$s['Id'], $a['id']]);
  return (string)$s['Id'];
}

// ---------- fakturautkast ----------

function faktura_hamta(int $id): ?array {
  $st = db()->prepare('SELECT f.*, k.kundnr, k.namn AS kund_namn, k.kundgrupp FROM fakturor f JOIN kunder k ON k.id = f.kund_id WHERE f.id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

function faktura_ordrar(int $fakturaId): array {
  $st = db()->prepare('SELECT * FROM orders WHERE faktura_id = ? ORDER BY leveransdatum IS NULL, leveransdatum, id');
  $st->execute([$fakturaId]);
  return $st->fetchAll();
}

function faktura_avgift_artikel(): string {
  return trim((string)installning('spiris_avgift_artikel', ''));
}

// Raderna på fakturan, i den ordning de skickas: en textrad per order, sedan orderns rader.
function faktura_innehall(array $f): array {
  $ordrar = faktura_ordrar((int)$f['id']);
  $rader = [];
  $netto = 0.0;
  $moms = [];
  foreach ($ordrar as $o) {
    $rubrik = 'Order ' . $o['id'] . ($o['leveransdatum'] ? ', leverans ' . date('Y-m-d', strtotime($o['leveransdatum'])) : '');
    if ($o['kundens_ordernr'] !== '') $rubrik .= ', er order ' . $o['kundens_ordernr'];
    if (count($ordrar) > 1 && $o['er_referens'] !== '' && $o['er_referens'] !== $f['er_referens']) $rubrik .= ', ' . $o['er_referens'];
    $rader[] = ['typ' => 'rubrik', 'text' => $rubrik, 'order_id' => (int)$o['id']];
    foreach (order_rader_alla((int)$o['id']) as $r) {
      if ($r['typ'] === 'text') {
        $rader[] = ['typ' => 'text', 'text' => $r['produkt'], 'order_id' => (int)$o['id']];
        continue;
      }
      if ($r['typ'] !== 'artikel') continue;
      $antal = rad_prisantal($r);
      if ($antal <= 0 && (float)$r['belopp'] == 0.0) continue;
      $enhet = (int)$r['per_kolli'] ? 'kolli' : $r['enhet'];
      $belopp = (float)$r['belopp'];
      $sats = (float)$r['moms'];
      $netto += $belopp;
      $moms[(string)$sats] = ($moms[(string)$sats] ?? 0) + round($belopp * $sats / 100, 2);
      $rader[] = ['typ' => 'artikel', 'artikel_id' => $r['artikel_id'] === null ? null : (int)$r['artikel_id'], 'artikelnr' => $r['artikelnr'],
                  'text' => $r['produkt'], 'antal' => $antal, 'enhet' => $enhet, 'pris' => (float)$r['pris'], 'moms' => $sats, 'belopp' => $belopp,
                  'order_id' => (int)$o['id']];
    }
  }
  $avgift = (float)($f['fakturaavgift'] ?? 0);
  if ($avgift > 0 && faktura_avgift_artikel() !== '') {
    $netto += $avgift;
    $k = (string)FAKTURAAVGIFT_MOMS;
    $moms[$k] = ($moms[$k] ?? 0) + round($avgift * FAKTURAAVGIFT_MOMS / 100, 2);
    $rader[] = ['typ' => 'avgift', 'artikel_id' => null, 'artikelnr' => faktura_avgift_artikel(), 'text' => 'Fakturaavgift', 'antal' => 1.0, 'enhet' => 'st',
                'pris' => $avgift, 'moms' => FAKTURAAVGIFT_MOMS, 'belopp' => $avgift, 'order_id' => null];
  }
  krsort($moms, SORT_NUMERIC);
  $momsSumma = array_sum($moms);
  return ['ordrar' => $ordrar, 'rader' => $rader, 'netto' => round($netto, 2), 'moms' => $moms, 'moms_summa' => round($momsSumma, 2), 'totalt' => round($netto + $momsSumma, 2)];
}

function faktura_forfallodag(array $f): ?string {
  return $f['betalningsvillkor'] === null ? null : date('Y-m-d', strtotime($f['fakturadatum'] . ' +' . (int)$f['betalningsvillkor'] . ' days'));
}

// Räknar om summorna på utkastet efter att en order har tagits bort.
function faktura_rakna(int $id): void {
  $f = faktura_hamta($id);
  if (!$f) return;
  $inn = faktura_innehall($f);
  db()->prepare('UPDATE fakturor SET netto = ?, moms = ? WHERE id = ?')->execute([$inn['netto'], $inn['moms_summa'], $id]);
}

// Samma text på alla order blir fakturans, annars kundens från kundregistret.
function gemensam(array $ordrar, string $falt, string $annars): string {
  $v = array_values(array_unique(array_filter(array_map(fn($o) => trim((string)$o[$falt]), $ordrar), fn($s) => $s !== '')));
  return count($v) === 1 ? $v[0] : $annars;
}

// Skapar utkast av valda order: ett per order, eller ett per kund. Returnerar id:n för de nya utkasten.
function faktura_skapa(array $orderIds, bool $samla, string $fakturadatum, array $user): array {
  $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
  if (!$orderIds) return [];
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare('SELECT o.*, COALESCE(o.kund_id, u.kund_id) AS reg_id, k.kundgrupp AS reg_grupp
                         FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                         WHERE o.id IN (' . implode(',', array_fill(0, count($orderIds), '?')) . ') ORDER BY o.leveransdatum, o.id FOR UPDATE');
    $st->execute($orderIds);
    $grupper = [];
    foreach ($st->fetchAll() as $o) {
      if ($o['faktura_id'] !== null || !in_array($o['status'], FAKTURA_KLARA, true) || $o['reg_id'] === null) continue;
      if (!far_grupp($user, $o['reg_grupp'])) continue;
      $nyckel = $samla ? 'k' . $o['reg_id'] : 'o' . $o['id'];
      $grupper[$nyckel][] = $o;
    }
    $nya = [];
    $av = vem($user);
    foreach ($grupper as $ordrar) {
      $kund = kund_hamta((int)$ordrar[0]['reg_id']);
      $villkor = $ordrar[0]['betalningsvillkor'] ?? $kund['betalningsvillkor'];
      $pdo->prepare('INSERT INTO fakturor (kund_id, fakturadatum, er_referens, var_referens, kundens_ordernr, betalningsvillkor, fakturaavgift, status, skapad_av, skapad_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, \'utkast\', ?, ?)')
          ->execute([$kund['id'], $fakturadatum, mb_substr(gemensam($ordrar, 'er_referens', $kund['er_referens']), 0, 120, 'UTF-8'),
                     mb_substr(gemensam($ordrar, 'var_referens', $kund['var_referens']), 0, 80, 'UTF-8'),
                     count($ordrar) === 1 ? $ordrar[0]['kundens_ordernr'] : '', $villkor === null ? null : (int)$villkor,
                     $kund['fakturaavgift'], $av, now()]);
      $fid = (int)$pdo->lastInsertId();
      $upd = $pdo->prepare("UPDATE orders SET faktura_id = ?, status_fore_faktura = status, status = 'fakturerad', fakturerad_at = NOW(), updated_at = ?, andrad_av = ? WHERE id = ?");
      foreach ($ordrar as $o) $upd->execute([$fid, now(), $av, $o['id']]);
      faktura_rakna($fid);
      $nya[] = $fid;
    }
    $pdo->commit();
    return $nya;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

// Tar bort en order från ett utkast som inte är skickat. Ordern får tillbaka sin status.
function faktura_slapp_order(int $fakturaId, int $orderId, string $av): void {
  db()->prepare("UPDATE orders SET faktura_id = NULL, status = COALESCE(status_fore_faktura, 'levererad'), status_fore_faktura = NULL, fakturerad_at = NULL,
                 updated_at = ?, andrad_av = ? WHERE id = ? AND faktura_id = ?")->execute([now(), $av, $orderId, $fakturaId]);
}

// Tar bort utkastet. Är det skickat tas det bort i Spiris först, så att ingen order blir fakturerad två gånger.
function faktura_ta_bort(int $id, string $av): void {
  $f = faktura_hamta($id);
  if (!$f) return;
  if ($f['status'] === 'skickad' && $f['spiris_id']) {
    try {
      spiris_api('DELETE', 'customerinvoicedrafts/' . rawurlencode($f['spiris_id']));
    } catch (SpirisFel $e) {
      if ($e->getCode() === 404) {
        throw new SpirisFel('Utkastet finns inte kvar i Spiris. Det har troligen redan blivit en faktura där. Kreditera fakturan i Spiris om den är fel. Utkastet här tas inte bort, så att ordern inte faktureras två gånger.');
      }
      throw $e;
    }
  }
  $pdo = db();
  $pdo->beginTransaction();
  foreach (faktura_ordrar($id) as $o) faktura_slapp_order($id, (int)$o['id'], $av);
  $pdo->prepare('DELETE FROM fakturor WHERE id = ?')->execute([$id]);
  $pdo->commit();
}

function spiris_kort(?string $s, int $max): string {
  return mb_substr(trim((string)$s), 0, $max, 'UTF-8');
}

// Det som skickas till Spiris. Kastar SpirisFel med alla saknade kunder och artiklar på en gång.
function faktura_spiris_data(array $f, array $inn): array {
  $kund = kund_hamta((int)$f['kund_id']);
  $kundId = spiris_kund_id($kund);
  $rader = [];
  $saknas = [];
  foreach ($inn['rader'] as $r) {
    if ($r['typ'] === 'rubrik' || $r['typ'] === 'text') {
      $rader[] = ['IsTextRow' => true, 'Text' => spiris_kort($r['text'], 200), 'ReversedConstructionServicesVatFree' => false];
      continue;
    }
    $artikel = spiris_artikel_id($r['artikel_id'], (string)$r['artikelnr']);
    if ($artikel === null) {
      $saknas[] = trim($r['artikelnr'] . ' ' . $r['text']);
      continue;
    }
    // Spiris räknar med två decimaler i antal. Blir summan då en annan skickas raden som 1 st för hela beloppet.
    $antal = round((float)$r['antal'], 2);
    $text = $r['text'];
    $pris = (float)$r['pris'];
    if (abs(round($antal * $pris, 2) - (float)$r['belopp']) >= 0.005) {
      $text .= ', ' . tal_text($r['antal']) . ' ' . $r['enhet'] . ' à ' . pris_text($pris) . ' kr';
      $antal = 1.0;
      $pris = (float)$r['belopp'];
    }
    $rader[] = ['ArticleId' => $artikel, 'IsTextRow' => false, 'Text' => spiris_kort($text, 200), 'Quantity' => $antal, 'UnitPrice' => $pris,
                'DiscountPercentage' => 0.0, 'ReversedConstructionServicesVatFree' => false];
  }
  if ($saknas) {
    throw new SpirisFel('De här artiklarna finns inte i Spiris: ' . implode(', ', array_slice($saknas, 0, 8)) . (count($saknas) > 8 ? ' med flera' : '')
                        . '. Lägg upp dem i Spiris med samma artikelnummer och skicka igen.');
  }
  $leverans = array_filter(array_map(fn($o) => $o['leveransdatum'], $inn['ordrar']));
  $data = [
    'CustomerId' => $kundId,
    'InvoiceDate' => $f['fakturadatum'],
    'RotReducedInvoicingType' => 0,
    'EuThirdParty' => false,
    // Fakturaadressen tar Spiris från kunden där. Leveransadressen skickas från kundregistret, som på följesedeln.
    'DeliveryCustomerName' => spiris_kort($kund['lev_namn'] ?: $kund['namn'], 50),
    'DeliveryAddress1' => spiris_kort($kund['lev_adress'], 50),
    'DeliveryPostalCode' => spiris_kort($kund['lev_postnr'], 10),
    'DeliveryCity' => spiris_kort($kund['lev_ort'], 50),
    'DeliveryCountryCode' => $kund['land'] ?: 'SE',
    'DeliveryDate' => $leverans ? max($leverans) : null,
    'DueDate' => faktura_forfallodag($f),
    'YourReference' => spiris_kort($f['er_referens'], 100),
    'OurReference' => spiris_kort($f['var_referens'], 100),
    'BuyersOrderReference' => spiris_kort($f['kundens_ordernr'], 35),
    'Rows' => $rader,
  ];
  return array_filter($data, fn($v) => $v !== null && $v !== '');
}

// Skickar utkastet till Spiris. Returnerar null när det gick bra, annars felet.
function faktura_skicka(int $id, string $av): ?string {
  $las = db()->query('SELECT GET_LOCK(' . db()->quote('lp_faktura_' . $id) . ', 20)')->fetchColumn();
  try {
    $f = faktura_hamta($id);
    if (!$f) return 'Utkastet finns inte.';
    if ($f['status'] === 'skickad') return null;
    $inn = faktura_innehall($f);
    if (!array_filter($inn['rader'], fn($r) => $r['typ'] === 'artikel')) {
      $fel = 'Utkastet har inga artikelrader att fakturera.';
    } else {
      try {
        $svar = spiris_api('POST', 'customerinvoicedrafts', faktura_spiris_data($f, $inn));
        db()->prepare("UPDATE fakturor SET status = 'skickad', spiris_id = ?, skickad_at = ?, skickad_av = ?, fel = NULL WHERE id = ?")
            ->execute([mb_substr((string)($svar['Id'] ?? ''), 0, 40), now(), $av, $id]);
        return null;
      } catch (SpirisFel $e) {
        $fel = $e->getMessage();
      }
    }
    db()->prepare('UPDATE fakturor SET fel = ? WHERE id = ?')->execute([$fel, $id]);
    return $fel;
  } finally {
    if ($las) db()->query('SELECT RELEASE_LOCK(' . db()->quote('lp_faktura_' . $id) . ')');
  }
}

function faktura_status_pill(array $f): string {
  if ($f['status'] === 'skickad') return '<span class="pill pill-levererad">Skickat till Spiris</span>';
  if ($f['fel']) return '<span class="pill pill-avbruten">Kunde inte skickas</span>';
  return '<span class="pill pill-ny">Ej skickat</span>';
}

function utkast_namn(int $id): string {
  return 'Utkast ' . $id;
}
