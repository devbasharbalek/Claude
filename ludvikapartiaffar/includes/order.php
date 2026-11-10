<?php
// Order i affärssystemet: kund ur kundregistret, artikelrader med kundens priser, plock och utskrifter.
// Order från hemsidan, Superklipp och Veckansklipp ligger i samma tabell. Deras rader har typen 'lista'.

const ORDER_KALLOR = [
  'webb' => 'Hemsidan', 'superklipp' => 'Superklipp', 'veckansklipp' => 'Veckansklipp',
  'personal' => 'Personal', 'proceedo' => 'Proceedo', 'abicart' => 'Abicart',
];
// Ej plockade order. Plockade och senare går inte att ändra rader på utan att plocket påverkas.
const ORDER_OPPNA = ['ny', 'bekraftad'];

// Lägger till kolumnerna för affärsorder på befintliga tabeller. Körs från erp_schema().
function order_kolumner(PDO $pdo): void {
  try {
    $pdo->query('SELECT kalla FROM orders LIMIT 0');
  } catch (PDOException $e) {
    $pdo->exec("ALTER TABLE orders
      ADD COLUMN kund_id INT UNSIGNED NULL,
      ADD COLUMN leveransdatum DATE NULL,
      ADD COLUMN kundens_ordernr VARCHAR(60) NOT NULL DEFAULT '',
      ADD COLUMN kundtext VARCHAR(500) NOT NULL DEFAULT '',
      ADD COLUMN plocktext VARCHAR(500) NOT NULL DEFAULT '',
      ADD COLUMN var_referens VARCHAR(80) NOT NULL DEFAULT '',
      ADD COLUMN er_referens VARCHAR(120) NOT NULL DEFAULT '',
      ADD COLUMN betalningsvillkor SMALLINT UNSIGNED NULL,
      ADD COLUMN kalla VARCHAR(20) NOT NULL DEFAULT 'webb',
      ADD COLUMN skapad_av VARCHAR(120) NOT NULL DEFAULT '',
      ADD COLUMN andrad_av VARCHAR(120) NOT NULL DEFAULT '',
      ADD COLUMN plockare_id INT UNSIGNED NULL,
      ADD COLUMN plockad_at DATETIME NULL,
      ADD COLUMN kontrollerad_av VARCHAR(120) NOT NULL DEFAULT '',
      ADD COLUMN kontrollerad_at DATETIME NULL,
      ADD COLUMN fakturerad_at DATETIME NULL,
      ADD KEY idx_leverans (leveransdatum, status),
      ADD KEY idx_kund (kund_id, created_at),
      ADD CONSTRAINT fk_orders_kund FOREIGN KEY (kund_id) REFERENCES kunder(id) ON DELETE SET NULL,
      ADD CONSTRAINT fk_orders_plockare FOREIGN KEY (plockare_id) REFERENCES users(id) ON DELETE SET NULL");
    // Order från Superklipp och Veckansklipp har redan ett leveransdatum.
    $pdo->exec("UPDATE orders o JOIN order_superklipp os ON os.order_id = o.id
                SET o.leveransdatum = os.leveransdatum, o.kalla = IF(os.etikett LIKE 'Veckansklipp%', 'veckansklipp', 'superklipp')");
  }
  try {
    $pdo->query('SELECT belopp FROM order_rader LIMIT 0');
  } catch (PDOException $e) {
    $pdo->exec("ALTER TABLE order_rader
      ADD COLUMN typ VARCHAR(10) NOT NULL DEFAULT 'lista',
      ADD COLUMN artikel_id INT UNSIGNED NULL,
      ADD COLUMN artikelnr VARCHAR(40) NOT NULL DEFAULT '',
      ADD COLUMN kolli DECIMAL(10,3) NULL,
      ADD COLUMN enheter DECIMAL(10,3) NULL,
      ADD COLUMN enheter_per_kolli DECIMAL(10,3) NULL,
      ADD COLUMN kg_per_kolli DECIMAL(10,3) NULL,
      ADD COLUMN per_kolli TINYINT(1) NOT NULL DEFAULT 0,
      ADD COLUMN viktvara TINYINT(1) NOT NULL DEFAULT 0,
      ADD COLUMN inpris DECIMAL(10,2) NULL,
      ADD COLUMN moms DECIMAL(4,2) NULL,
      ADD COLUMN priskalla VARCHAR(10) NOT NULL DEFAULT '',
      ADD COLUMN belopp DECIMAL(12,2) NULL,
      ADD COLUMN kostnad DECIMAL(12,2) NULL,
      ADD COLUMN plockat DECIMAL(10,3) NULL,
      ADD COLUMN plockvikt DECIMAL(10,3) NULL,
      ADD COLUMN plockkommentar VARCHAR(200) NOT NULL DEFAULT '',
      ADD COLUMN plockad_av VARCHAR(120) NOT NULL DEFAULT '',
      ADD KEY idx_artikel (artikel_id),
      ADD CONSTRAINT fk_orader_artikel FOREIGN KEY (artikel_id) REFERENCES artiklar(id) ON DELETE SET NULL");
  }
}

// ---------- datum ----------

const VECKODAG_KOD = [1 => 'man', 2 => 'tis', 3 => 'ons', 4 => 'tor', 5 => 'fre', 6 => 'lor', 7 => 'son'];
const VECKODAG_NAMN = [1 => 'Måndag', 2 => 'Tisdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lördag', 7 => 'Söndag'];

// "Idag", "Imorgon" eller "Onsdag 11/11".
function leverans_text(?string $datum, string $dagKod = ''): string {
  global $leveransdagar;
  if ($datum === null || $datum === '') return $leveransdagar[$dagKod] ?? ($dagKod !== '' ? $dagKod : 'Inget datum');
  $d = new DateTimeImmutable($datum);
  $idag = new DateTimeImmutable('today');
  $diff = (int)$idag->diff($d)->format('%r%a');
  $dag = VECKODAG_NAMN[(int)$d->format('N')] . ' ' . $d->format('j/n');
  return match ($diff) { 0 => 'Idag, ' . mb_strtolower($dag, 'UTF-8'), 1 => 'Imorgon, ' . mb_strtolower($dag, 'UTF-8'), default => $dag };
}

// Nästa vardag efter idag, eller nästa dag som matchar kundens tur ("FREDAG 1" ger nästa fredag).
function forslag_leveransdatum(?array $kund): string {
  $d = new DateTimeImmutable('tomorrow');
  $tur = mb_strtoupper(trim((string)($kund['tur'] ?? '')), 'UTF-8');
  foreach (VECKODAG_NAMN as $n => $namn) {
    if ($n <= 5 && str_starts_with($tur, mb_strtoupper($namn, 'UTF-8'))) {
      while ((int)$d->format('N') !== $n) $d = $d->modify('+1 day');
      return $d->format('Y-m-d');
    }
  }
  while ((int)$d->format('N') >= 6) $d = $d->modify('+1 day');
  return $d->format('Y-m-d');
}

function giltigt_datum(string $s): ?string {
  $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
  return $d && $d->format('Y-m-d') === $s ? $s : null;
}

// ---------- rader ----------

// En ny rad för en artikel, med kundens pris. $kund kan vara null (då gäller grundpriset).
function order_rad_for_artikel(array $a, ?array $kund): array {
  $epk = $a['enhet'] === 'kg' ? (float)($a['vikt'] ?? 0) : (float)($a['antal_per_kolli'] ?? 0);
  $pris = $kund ? pris_for_kund($kund, (int)$a['id']) : null;
  return [
    'typ' => 'artikel', 'artikel_id' => (int)$a['id'], 'artikelnr' => $a['artikelnr'], 'produkt' => $a['namn'], 'enhet' => $a['enhet'],
    'enheter_per_kolli' => $epk > 0 ? $epk : 1.0, 'kg_per_kolli' => $a['vikt'] === null ? null : (float)$a['vikt'],
    'per_kolli' => (int)$a['hel_forpackning'], 'viktvara' => (int)$a['viktartikel'],
    'inpris' => $a['inpris'] === null ? null : (float)$a['inpris'], 'moms' => (float)$a['moms'],
    'pris' => $pris['pris'] ?? (float)($a['utpris'] ?? 0), 'priskalla' => $pris['kalla'] ?? 'grund',
    'kolli' => null, 'enheter' => null, 'plockkommentar' => '',
  ];
}

// Kolli och enheter som levereras: det beställda, eller det plockade när plocket avviker.
function rad_levererat(array $r): array {
  $plockat = $r['plockat'] ?? null;
  $kolli = $r['kolli'] === null ? null : (float)$r['kolli'];
  if ($plockat === null || ($r['typ'] ?? '') !== 'artikel' || samma_pris((float)$plockat, rad_att_plocka($r))) return [$kolli, (float)($r['enheter'] ?? 0)];
  if ($kolli !== null) return [(float)$plockat, round((float)$plockat * max((float)$r['enheter_per_kolli'], 0.001), 3)];
  return [null, (float)$plockat];
}

// Antalet som priset gäller: kolli för varor som säljs i hel förpackning, annars kg eller styck.
// För viktvaror gäller den plockade vikten när den finns, och plockat antal när det avviker från ordern.
function rad_prisantal(array $r): float {
  $epk = max((float)($r['enheter_per_kolli'] ?? 1), 0.001);
  [$kolli, $enheter] = rad_levererat($r);
  if ((int)$r['per_kolli']) return $kolli !== null ? $kolli : $enheter / $epk;
  if ((int)$r['viktvara'] && ($r['plockvikt'] ?? null) !== null) return (float)$r['plockvikt'];
  return $enheter;
}

// Räknar om belopp och kostnad för en artikelrad.
function rad_berakna(array $r): array {
  if (($r['typ'] ?? '') !== 'artikel') return $r;
  $antal = rad_prisantal($r);
  $r['belopp'] = round($antal * (float)$r['pris'], 2);
  $r['kostnad'] = $r['inpris'] === null ? null : round($antal * (float)$r['inpris'], 2);
  $r['antal'] = (int)min(65535, max(0, round((float)($r['kolli'] ?? 0))));
  return $r;
}

// Kolli för raden, även när bara antal enheter är ifyllt.
function rad_kolli(array $r): float {
  if ($r['typ'] === 'lista') return (float)$r['antal'];
  if ($r['typ'] !== 'artikel') return 0.0;
  return $r['kolli'] !== null ? (float)$r['kolli'] : (float)($r['enheter'] ?? 0) / max((float)$r['enheter_per_kolli'], 0.001);
}

// Det som ska plockas: kolli, eller enheter när raden saknar kolli.
function rad_att_plocka(array $r): float {
  if ($r['typ'] === 'lista') return (float)$r['antal'];
  return $r['kolli'] !== null ? (float)$r['kolli'] : (float)($r['enheter'] ?? 0);
}

function rad_plockenhet(array $r): string {
  return $r['typ'] === 'artikel' && $r['kolli'] === null ? $r['enhet'] : 'kolli';
}

function rad_vikt(array $r): float {
  if ($r['typ'] !== 'artikel') return 0.0;
  [$kolli, $enheter] = rad_levererat($r);
  if ($r['enhet'] === 'kg') return (int)$r['viktvara'] && ($r['plockvikt'] ?? null) !== null ? (float)$r['plockvikt'] : $enheter;
  return ($kolli ?? $enheter / max((float)$r['enheter_per_kolli'], 0.001)) * (float)($r['kg_per_kolli'] ?? 0);
}

function order_rader_alla(int $orderId): array {
  $st = db()->prepare('SELECT * FROM order_rader WHERE order_id = ? ORDER BY sortering, id');
  $st->execute([$orderId]);
  return $st->fetchAll();
}

// Summor för en order: värde, inköp, vinst, moms, kolli och vikt.
function order_summor(array $rader): array {
  $s = ['netto' => 0.0, 'kostnad' => 0.0, 'moms' => 0.0, 'kolli' => 0.0, 'vikt' => 0.0, 'rader' => 0, 'utan_inpris' => false, 'har_pris' => false];
  foreach ($rader as $r) {
    if ($r['typ'] === 'text') continue;
    $s['rader']++;
    $s['kolli'] += rad_kolli($r);
    if ($r['typ'] !== 'artikel') continue;
    $s['har_pris'] = true;
    $s['netto'] += (float)$r['belopp'];
    $s['moms'] += round((float)$r['belopp'] * (float)$r['moms'] / 100, 2);
    if ($r['kostnad'] === null) $s['utan_inpris'] = true;
    else $s['kostnad'] += (float)$r['kostnad'];
    $s['vikt'] += rad_vikt($r);
  }
  $s['vinst'] = $s['netto'] - $s['kostnad'];
  $s['marginal'] = $s['netto'] > 0 ? round($s['vinst'] / $s['netto'] * 100, 1) : null;
  $s['totalt'] = $s['netto'] + $s['moms'];
  return $s;
}

// Sparar en rad. Tar emot en rad med samma fält som tabellen.
function order_rad_spara(int $orderId, array $r, int $sortering): void {
  $r = rad_berakna($r);
  $falt = ['typ', 'artikel_id', 'artikelnr', 'produkt', 'enhet', 'kolli', 'enheter', 'enheter_per_kolli', 'kg_per_kolli', 'per_kolli', 'viktvara',
           'inpris', 'moms', 'pris', 'priskalla', 'belopp', 'kostnad', 'antal', 'plockkommentar'];
  $v = [];
  foreach ($falt as $f) $v[$f] = $r[$f] ?? null;
  $v['produkt'] = mb_substr((string)$v['produkt'], 0, 160, 'UTF-8');
  $v['enhet'] = (string)($v['enhet'] ?? '');
  $v['artikelnr'] = (string)($v['artikelnr'] ?? '');
  $v['plockkommentar'] = (string)($v['plockkommentar'] ?? '');
  $v['priskalla'] = (string)($v['priskalla'] ?? '');
  $v['pris'] = (float)($v['pris'] ?? 0);
  $v['antal'] = (int)($v['antal'] ?? 0);
  $v['per_kolli'] = (int)($v['per_kolli'] ?? 0);
  $v['viktvara'] = (int)($v['viktvara'] ?? 0);
  if (!empty($r['id'])) {
    db()->prepare('UPDATE order_rader SET ' . implode(', ', array_map(fn($f) => "$f = ?", $falt)) . ', sortering = ? WHERE id = ? AND order_id = ?')
        ->execute(array_merge(array_values($v), [$sortering, (int)$r['id'], $orderId]));
  } else {
    db()->prepare('INSERT INTO order_rader (order_id, sortering, ' . implode(', ', $falt) . ') VALUES (?, ?' . str_repeat(', ?', count($falt)) . ')')
        ->execute(array_merge([$orderId, $sortering], array_values($v)));
  }
}

// ---------- plock ----------

// Sätter plockat antal på en rad. null nollställer. Viktvaror kan få en plockad vikt.
// Ordern blir plockad när alla rader är plockade, och går tillbaka till bekräftad om en rad nollställs.
function plocka_rad(int $radId, ?float $antal, ?float $vikt, string $av): ?array {
  $st = db()->prepare('SELECT r.*, o.status FROM order_rader r JOIN orders o ON o.id = r.order_id WHERE r.id = ?');
  $st->execute([$radId]);
  $r = $st->fetch();
  if (!$r || in_array($r['status'], ['avbruten', 'fakturerad'], true) || $r['typ'] === 'text') return null;
  $r['plockat'] = $antal;
  if ($vikt !== null || $antal === null) $r['plockvikt'] = $antal === null ? null : $vikt;
  $r = rad_berakna($r);
  db()->prepare('UPDATE order_rader SET plockat = ?, plockvikt = ?, plockad_av = ?, belopp = ?, kostnad = ? WHERE id = ?')
      ->execute([$r['plockat'], $r['plockvikt'], $antal === null ? '' : $av, $r['belopp'], $r['kostnad'], $radId]);
  order_plockstatus((int)$r['order_id'], $av);
  return $r;
}

function order_plockstatus(int $orderId, string $av): string {
  $st = db()->prepare("SELECT COUNT(*) AS rader, SUM(plockat IS NOT NULL) AS plockade FROM order_rader WHERE order_id = ? AND typ <> 'text'");
  $st->execute([$orderId]);
  $p = $st->fetch();
  $st = db()->prepare('SELECT status FROM orders WHERE id = ?');
  $st->execute([$orderId]);
  $status = (string)$st->fetchColumn();
  $klar = (int)$p['rader'] > 0 && (int)$p['plockade'] === (int)$p['rader'];
  if ($klar && in_array($status, ORDER_OPPNA, true)) {
    db()->prepare("UPDATE orders SET status = 'plockad', plockad_at = ?, updated_at = ?, andrad_av = ? WHERE id = ?")->execute([now(), now(), $av, $orderId]);
    return 'plockad';
  }
  if (!$klar && $status === 'plockad') {
    db()->prepare("UPDATE orders SET status = 'bekraftad', plockad_at = NULL, updated_at = ?, andrad_av = ? WHERE id = ?")->execute([now(), $av, $orderId]);
    return 'bekraftad';
  }
  return $status;
}

// ---------- uppslag ----------

function order_hamta(int $id): ?array {
  $st = db()->prepare('SELECT o.*, u.email AS kund_email, u.active AS kund_active, u.kundgrupp AS kund_kundgrupp, u.kund_id AS konto_kund_id,
                              k.id AS register_id, k.kundnr, k.namn AS kund_namn, k.lev_namn, k.kundgrupp AS register_grupp, k.prisgrupp, k.telefon AS kund_telefon,
                              p.kontakt AS plockare_namn
                       FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                       LEFT JOIN users p ON p.id = o.plockare_id WHERE o.id = ?');
  $st->execute([$id]);
  return $st->fetch() ?: null;
}

// Kundgruppen för en order: från kundregistret, annars som den sparades vid beställningen.
function order_grupp(array $o): ?string {
  return $o['register_grupp'] ?? $o['kundgrupp'] ?? $o['kund_kundgrupp'] ?? null;
}

function grupp_pill(?string $g): string {
  if ($g === null || !isset(REGISTER_KUNDGRUPPER[$g])) return '';
  return '<span class="pill pill-grupp" title="' . e(REGISTER_KUNDGRUPPER[$g]) . '">' . e($g) . '</span>';
}

// Personal som kan plocka, till valet av plockare.
function plockare_lista(): array {
  $roller = array_keys(array_filter(ROLLER, fn($r) => in_array('plock', $r['kan'], true)));
  $st = db()->prepare('SELECT id, kontakt FROM users WHERE active = 1 AND role IN (' . implode(',', array_fill(0, count($roller), '?')) . ') ORDER BY kontakt');
  $st->execute($roller);
  return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

function mangd_text(float|string|null $t): string {
  return $t === null || $t === '' ? '' : tal_text($t);
}
