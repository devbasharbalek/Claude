<?php
// Import av artiklar, priser, kunder och kundpriser från Excel- eller CSV-filer, t.ex. exporter från Partihandel.
// Samma kod räknar ut förhandsvisningen och gör importen, så att det som visas är det som sparas.

require_once __DIR__ . '/tabell.php';

const IMPORT_TYPER = [
  'artiklar'   => ['namn' => 'Artiklar och priser', 'text' => 'Artikelregistret eller en prislista. Nya artiklar läggs till och ändrade uppdateras. Kolumnerna Grupp 1, 2 och 3 blir prisgruppernas priser.'],
  'kunder'     => ['namn' => 'Kunder', 'text' => 'Kundregistret med adresser, e-post för följesedel och faktura, kundgrupp och prisgrupp.'],
  'kundpriser' => ['namn' => 'Kundpriser', 'text' => 'Egna priser för enskilda kunder. Filen behöver artikelnummer och pris, och kundnummer om den gäller flera kunder.'],
];

// Fält => rubriker som känns igen (efter rubrik_nyckel). Både Partihandels engelska och svenska rubriker.
const IMPORT_ARTIKEL_FALT = [
  'artikelnr' => ['articlenumber', 'artnr', 'artikelnr', 'artikelnummer'],
  'namn' => ['articlename', 'artikelnamn', 'benämning', 'namn'],
  'enhet' => ['unit', 'enhet'],
  'kollistorlek' => ['kollistorlek'],
  'vikt' => ['unitweight', 'vikt', 'kollivikt'],
  'antal_per_kolli' => ['piecesperunit', 'antalperkolli', 'stperkolli'],
  'produktgrupp' => ['productgroup', 'produktgrupp', 'varugrupp'],
  'artikelgrupp' => ['articlegroup', 'artikelgrupp'],
  'inpris' => ['purchaseprice', 'gällandeinpris', 'inpris', 'inköpspris'],
  'utpris' => ['price', 'gällandeutpris', 'utpris', 'grundpris'],
  'pris' => ['pris', 'nettopris', 'försäljningspris'],
  'grupp1' => ['grupp1', 'prisgrupp1'],
  'grupp2' => ['grupp2', 'prisgrupp2'],
  'grupp3' => ['grupp3', 'prisgrupp3'],
  'moms' => ['articlevatrate', 'moms', 'momssats', 'vat'],
  'ursprung' => ['articlecountrycode', 'ursprung', 'ursprungsland'],
  'gtin' => ['gtin', 'ean', 'streckkod'],
  'lagerartikel' => ['warehousearticle', 'lagerartikel'],
  'hel_forpackning' => ['soldaswholeunit', 'helförpackning'],
  'losvikt' => ['soldinbulk', 'lösvikt'],
  'viktartikel' => ['isweightarticle', 'viktartikel'],
  'webbshop' => ['vismasendtowebshop', 'webbshop'],
  'fakturatext' => ['vismacomment', 'fakturatext'],
  'kommentar' => ['comment', 'kommentar'],
  'visma_id' => ['vismaid'],
  'visma_kontering_id' => ['vismacodingid'],
  'visma_enhet_id' => ['vismaunitid'],
];

const IMPORT_KUND_FALT = [
  'kundnr' => ['customernumber', 'kundnr', 'kundnummer'],
  'namn' => ['customername', 'kundnamn', 'namn', 'företag'],
  'orgnr' => ['corporateidentitynumber', 'orgnr', 'organisationsnummer'],
  'momsnr' => ['vatregistrationnumber', 'momsnr', 'momsregistreringsnummer', 'vatnummer'],
  'kundgrupp' => ['customergroup', 'kundgrupp'],
  'prisgrupp' => ['pricegroup', 'prisgrupp'],
  'moderbolag' => ['parentcompany', 'moderbolag', 'huvudkund'],
  'lev_namn' => ['deliveryaddressname', 'leveransnamn'],
  'lev_adress' => ['addressstreet', 'leveransadress', 'adress', 'gatuadress'],
  'lev_postnr' => ['zipcode', 'postnr', 'postnummer'],
  'lev_ort' => ['location', 'ort', 'postort'],
  'land' => ['countrycode', 'landkod'],
  'fakt_namn' => ['invoiceaddressname', 'fakturanamn'],
  'fakt_adress' => ['invoiceaddressstreet', 'fakturaadress'],
  'fakt_postnr' => ['invoiceaddresszipcode', 'fakturapostnr'],
  'fakt_ort' => ['invoiceaddresspostaltown', 'fakturaort'],
  'telefon' => ['phone', 'telefon', 'tel'],
  'foljesedel_epost' => ['deliverynoteemail', 'följesedelepost'],
  'foljesedel_kopia' => ['deliverynoteemailcc'],
  'faktura_epost' => ['invoicenoteemail', 'fakturaepost'],
  'faktura_kopia' => ['invoicenoteemailcc'],
  'paminnelse_epost' => ['remindernoteemail', 'påminnelseepost'],
  'var_referens' => ['ourreference', 'vårreferens'],
  'er_referens' => ['yourreference', 'erreferens'],
  'referens' => ['reference', 'referens'],
  'gln' => ['glnnumber', 'gln', 'glnnummer'],
  'tur' => ['journey', 'tur', 'leveranstur'],
  'betalningsvillkor' => ['paymenttermsamountdays', 'betalningsvillkor', 'betalvillkor'],
  'fakturaavgift' => ['invoiceadministrationfee', 'fakturaavgift'],
  'visma_id' => ['vismaid'],
  'visma_betalvillkor_id' => ['vismatermsofpaymentid'],
];

const IMPORT_KUNDPRIS_FALT = [
  'kundnr' => ['customernumber', 'kundnr', 'kundnummer'],
  'artikelnr' => ['articlenumber', 'artnr', 'artikelnr', 'artikelnummer'],
  'pris' => ['pris', 'kundpris', 'price', 'nettopris', 'avtalspris'],
];

// Fältens typ och längd i registren.
const ARTIKEL_FALT_TYP = [
  'namn' => ['text', 200], 'enhet' => ['text', 12], 'vikt' => ['tal'], 'antal_per_kolli' => ['tal'],
  'produktgrupp' => ['text', 60], 'artikelgrupp' => ['text', 20], 'inpris' => ['pris'], 'utpris' => ['pris'],
  'moms' => ['moms'], 'ursprung' => ['text', 40], 'gtin' => ['text', 20],
  'lagerartikel' => ['bool'], 'hel_forpackning' => ['bool'], 'losvikt' => ['bool'], 'viktartikel' => ['bool'], 'webbshop' => ['bool'],
  'fakturatext' => ['text', 255], 'kommentar' => ['text', 255],
  'visma_id' => ['id', 40], 'visma_kontering_id' => ['id', 40], 'visma_enhet_id' => ['id', 40],
];
const KUND_FALT_TYP = [
  'namn' => ['text', 160], 'orgnr' => ['text', 20], 'momsnr' => ['text', 20], 'kundgrupp' => ['grupp'], 'prisgrupp' => ['prisgrupp'],
  'moderbolag' => ['text', 160], 'lev_namn' => ['text', 160], 'lev_adress' => ['text', 160], 'lev_postnr' => ['text', 12], 'lev_ort' => ['text', 80],
  'land' => ['land'], 'fakt_namn' => ['text', 160], 'fakt_adress' => ['text', 160], 'fakt_postnr' => ['text', 12], 'fakt_ort' => ['text', 80],
  'telefon' => ['text', 40], 'foljesedel_epost' => ['epost', 255], 'foljesedel_kopia' => ['epost', 255], 'faktura_epost' => ['epost', 255],
  'faktura_kopia' => ['epost', 255], 'paminnelse_epost' => ['epost', 255], 'var_referens' => ['text', 80], 'er_referens' => ['text', 120],
  'referens' => ['text', 120], 'gln' => ['text', 20], 'tur' => ['tur'], 'betalningsvillkor' => ['dagar'], 'fakturaavgift' => ['pris'],
  'visma_id' => ['id', 40], 'visma_betalvillkor_id' => ['id', 40],
];

const ARTIKEL_STANDARD = [
  'namn' => '', 'enhet' => 'st', 'vikt' => null, 'antal_per_kolli' => null, 'produktgrupp' => '', 'artikelgrupp' => '',
  'inpris' => null, 'utpris' => null, 'moms' => 6.0, 'ursprung' => '', 'gtin' => '', 'lagerartikel' => 0, 'hel_forpackning' => 0,
  'losvikt' => 0, 'viktartikel' => 0, 'webbshop' => 0, 'fakturatext' => '', 'kommentar' => '',
  'visma_id' => null, 'visma_kontering_id' => null, 'visma_enhet_id' => null,
];

const FALT_NAMN = [
  'artikelnr' => 'Artikelnr', 'namn' => 'Namn', 'enhet' => 'Enhet', 'kollistorlek' => 'Kollistorlek', 'vikt' => 'Vikt per kolli',
  'antal_per_kolli' => 'Antal per kolli', 'produktgrupp' => 'Produktgrupp', 'artikelgrupp' => 'Artikelgrupp', 'inpris' => 'Inpris',
  'utpris' => 'Grundpris', 'pris' => 'Pris', 'grupp1' => 'Grupp 1', 'grupp2' => 'Grupp 2', 'grupp3' => 'Grupp 3', 'moms' => 'Moms',
  'ursprung' => 'Ursprung', 'gtin' => 'EAN', 'lagerartikel' => 'Lagerartikel', 'hel_forpackning' => 'Hel förpackning',
  'losvikt' => 'Lösvikt', 'viktartikel' => 'Viktartikel', 'webbshop' => 'Webbshop', 'fakturatext' => 'Fakturatext', 'kommentar' => 'Kommentar',
  'visma_id' => 'Visma-id', 'visma_kontering_id' => 'Visma kontering', 'visma_enhet_id' => 'Visma enhet', 'visma_betalvillkor_id' => 'Visma betalvillkor',
  'kundnr' => 'Kundnr', 'orgnr' => 'Org.nr', 'momsnr' => 'Momsnr', 'kundgrupp' => 'Kundgrupp', 'prisgrupp' => 'Prisgrupp',
  'moderbolag' => 'Huvudkund', 'lev_namn' => 'Leveransnamn', 'lev_adress' => 'Leveransadress', 'lev_postnr' => 'Postnr', 'lev_ort' => 'Ort',
  'land' => 'Land', 'fakt_namn' => 'Fakturanamn', 'fakt_adress' => 'Fakturaadress', 'fakt_postnr' => 'Fakturapostnr', 'fakt_ort' => 'Fakturaort',
  'telefon' => 'Telefon', 'foljesedel_epost' => 'E-post följesedel', 'foljesedel_kopia' => 'Kopia följesedel', 'faktura_epost' => 'E-post faktura',
  'faktura_kopia' => 'Kopia faktura', 'paminnelse_epost' => 'E-post påminnelse', 'var_referens' => 'Vår referens', 'er_referens' => 'Er referens',
  'referens' => 'Referens', 'gln' => 'GLN', 'tur' => 'Tur', 'betalningsvillkor' => 'Betalningsvillkor', 'fakturaavgift' => 'Fakturaavgift',
];

const IMPORT_MAX_VARNINGAR = 80;
const IMPORT_MAX_EXEMPEL = 60;

function import_rapport(string $typ): array {
  return ['typ' => $typ, 'rader' => 0, 'nya' => 0, 'andrade' => 0, 'oforandrade' => 0, 'overhoppade' => 0,
          'priser' => 0, 'saknas' => 0, 'inaktiveras' => 0, 'kolumner' => [], 'varningar' => [], 'antal_varningar' => 0,
          'exempel' => [], 'okanda' => [], 'moms_misstanke' => 0, 'fel' => null];
}

function import_varna(array &$r, string $text): void {
  $r['antal_varningar']++;
  if (count($r['varningar']) < IMPORT_MAX_VARNINGAR) $r['varningar'][] = $text;
}

function import_exempel(array &$r, string $nr, string $namn, string $typ, array $andringar): void {
  if (count($r['exempel']) < IMPORT_MAX_EXEMPEL) $r['exempel'][] = ['nr' => $nr, 'namn' => $namn, 'typ' => $typ, 'andringar' => $andringar];
}

// Kör importen. Utan användare räknas bara förändringarna ut (förhandsvisning) och inget sparas.
function import_kor(string $typ, array $rader, array $val, ?array $user): array {
  erp_schema();
  $r = import_rapport($typ);
  match ($typ) {
    'artiklar' => import_artiklar($rader, $val, $user, $r),
    'kunder' => import_kunder($rader, $val, $user, $r),
    'kundpriser' => import_kundpriser($rader, $val, $user, $r),
  };
  return $r;
}

// Ett värde ur filen omvandlat till registrets format. Returnerar [värde, fel].
function import_varde(array $typ, string $cell): array {
  $cell = trim($cell);
  switch ($typ[0]) {
    case 'text':
      return [enRad($cell, $typ[1]), null];
    case 'id':
      $v = enRad($cell, $typ[1]);
      return [$v === '' ? null : $v, null];
    case 'epost':
      return [mb_strtolower(enRad($cell, $typ[1]), 'UTF-8'), null];
    case 'pris':
      $p = pris_ur_text($cell);
      return $p === false ? [null, "\"$cell\" är inget pris"] : [$p, null];
    case 'tal':
      $t = tal_ur_text($cell);
      if ($t === false || ($t !== null && ($t < 0 || $t > 999999))) return [null, "\"$cell\" är inget giltigt tal"];
      return [$t === null ? null : round($t, 3), null];
    case 'moms':
      $m = moms_ur_text($cell);
      return $m === null ? [null, "\"$cell\" är ingen momssats (6, 12, 25 eller 0 %)"] : [$m, null];
    case 'bool':
      $b = ja_nej($cell);
      return $b === null ? [null, "\"$cell\" är varken ja eller nej"] : [$b, null];
    case 'grupp':
      $g = mb_strtoupper(mb_substr($cell, 0, 1, 'UTF-8'), 'UTF-8');
      if ($g === '') return [null, null];
      return preg_match('/^[A-Z]$/', $g) && mb_strlen($cell, 'UTF-8') <= 2 ? [$g, null] : [null, "\"$cell\" är ingen kundgrupp"];
    case 'prisgrupp':
      if ($cell === '') return [null, null];
      return preg_match('/^(?:(?:pris)?grupp\s*)?([1-9])$/iu', $cell, $m) && isset(PRISGRUPPER[(int)$m[1]])
        ? [(int)$m[1], null] : [null, "\"$cell\" är ingen prisgrupp"];
    case 'land':
      $l = strtoupper($cell);
      return [preg_match('/^[A-Z]{2}$/', $l) ? $l : 'SE', null];
    case 'tur':
      // Partihandel skriver ibland turens interna id i stället för namnet. Det sparas inte.
      return [preg_match('/^[0-9a-f]{20,}$/i', $cell) ? '' : enRad($cell, 40), null];
    case 'dagar':
      if ($cell === '') return [null, null];
      return ctype_digit($cell) && (int)$cell <= 365 ? [(int)$cell, null] : [null, "\"$cell\" är inget antal dagar"];
  }
  return [null, null];
}

function import_lika(array $typ, mixed $fore, mixed $efter): bool {
  if ($fore === null || $efter === null || $fore === '' || $efter === '') {
    return ($fore === null || $fore === '') && ($efter === null || $efter === '');
  }
  return match ($typ[0]) {
    'pris', 'tal', 'moms' => abs((float)$fore - (float)$efter) < 0.0005,
    'bool', 'prisgrupp', 'dagar' => (int)$fore === (int)$efter,
    default => (string)$fore === (string)$efter,
  };
}

function import_visa(array $typ, mixed $v): string {
  if ($v === null || $v === '') return '–';
  return match ($typ[0]) {
    'pris' => pris_text($v),
    'tal' => tal_text($v),
    'moms' => tal_text($v) . ' %',
    'bool' => (int)$v ? 'Ja' : 'Nej',
    'prisgrupp' => prisgrupp_text((int)$v),
    default => mb_strimwidth((string)$v, 0, 60, '…', 'UTF-8'),
  };
}

// ---------- artiklar och priser ----------

function import_artiklar(array $rader, array $val, ?array $user, array &$r): void {
  [$hi, $kol] = tabell_kolumner($rader, IMPORT_ARTIKEL_FALT, 'artikelnr');
  if ($hi < 0) {
    $r['fel'] = 'Hittar ingen kolumn med artikelnummer. Rubriken ska heta till exempel "Art. Nr", "Artikelnummer" eller "articleNumber".';
    return;
  }
  // En ensam priskolumn gäller grundpriset eller den prisgrupp som valdes.
  if (isset($kol['pris'])) {
    $mal = ($val['prismal'] ?? '') !== '' && isset(PRISGRUPPER[(int)$val['prismal']]) ? 'grupp' . (int)$val['prismal'] : 'utpris';
    if (!isset($kol[$mal])) $kol[$mal] = $kol['pris'];
    unset($kol['pris']);
  }
  $r['kolumner'] = array_values(array_map(fn($f) => FALT_NAMN[$f] ?? $f, array_keys($kol)));
  $harPris = (bool)array_intersect(['inpris', 'utpris', 'grupp1', 'grupp2', 'grupp3'], array_keys($kol));
  if (count($kol) === 1) {
    $r['fel'] = 'Filen har artikelnummer men inga andra kolumner som känns igen, till exempel Artikelnamn, Gällande utpris eller Grupp 1.';
    return;
  }

  $skarp = $user !== null;
  $av = $skarp ? vem($user) : '';
  $befintliga = [];
  foreach (db()->query('SELECT * FROM artiklar') as $a) $befintliga[mb_strtolower($a['artikelnr'], 'UTF-8')] = $a;
  $grupp = [];
  foreach (db()->query('SELECT artikel_id, prisgrupp, pris FROM artikel_priser') as $p) $grupp[(int)$p['artikel_id']][(int)$p['prisgrupp']] = (float)$p['pris'];

  $sedda = [];
  $momsJamford = 0;
  foreach (array_slice($rader, $hi + 1) as $i => $rad) {
    $radnr = $hi + $i + 2;
    $nr = enRad($rad[$kol['artikelnr']] ?? '', 40);
    if ($nr === '') {
      if (implode('', $rad) !== '') {
        $r['overhoppade']++;
        import_varna($r, "Rad $radnr saknar artikelnummer och hoppas över.");
      }
      continue;
    }
    $r['rader']++;
    $nyckel = mb_strtolower($nr, 'UTF-8');
    if (isset($sedda[$nyckel])) {
      $r['overhoppade']++;
      import_varna($r, "Artikel $nr finns på flera rader. Bara den första används (rad $radnr hoppas över).");
      continue;
    }
    $sedda[$nyckel] = true;
    $fore = $befintliga[$nyckel] ?? null;

    $ny = [];
    foreach (ARTIKEL_FALT_TYP as $f => $typ) {
      if (!isset($kol[$f])) continue;
      $cell = (string)($rad[$kol[$f]] ?? '');
      // Ett tomt pris i filen ändrar inget. Bara gruppriser kan tas bort med en tom cell.
      if (($typ[0] === 'pris' || $typ[0] === 'moms') && trim($cell) === '') continue;
      [$v, $fel] = import_varde($typ, $cell);
      if ($fel !== null) {
        import_varna($r, "Artikel $nr: " . FALT_NAMN[$f] . " $fel, fältet ändras inte.");
        continue;
      }
      if ($f === 'enhet') {
        $v = mb_strtolower($v, 'UTF-8');
        if ($v === '') continue;
      }
      if ($f === 'namn' && $v === '') continue;
      $ny[$f] = $v;
    }
    if (isset($kol['kollistorlek'])) {
      [$v, $fel] = import_varde(['tal'], (string)($rad[$kol['kollistorlek']] ?? ''));
      if ($fel !== null) import_varna($r, "Artikel $nr: Kollistorlek $fel.");
      elseif ($v !== null) $ny[($ny['enhet'] ?? $fore['enhet'] ?? 'st') === 'kg' ? 'vikt' : 'antal_per_kolli'] = $v;
    }
    $nyaGrupp = [];
    foreach (PRISGRUPPER as $g => $_) {
      if (!isset($kol['grupp' . $g])) continue;
      $cell = (string)($rad[$kol['grupp' . $g]] ?? '');
      $p = pris_ur_text($cell);
      if ($p === false) import_varna($r, "Artikel $nr: Grupp $g \"$cell\" är inget pris, priset ändras inte.");
      else $nyaGrupp[$g] = $p;
    }

    if (!$fore) {
      if (($ny['namn'] ?? '') === '' || !isset($kol['moms'])) {
        // En prislista kan inte lägga upp artiklar: momsen saknas.
        $r['overhoppade']++;
        if (count($r['okanda']) < 200) $r['okanda'][] = $nr . (($ny['namn'] ?? '') !== '' ? ' ' . $ny['namn'] : '');
        continue;
      }
      $r['nya']++;
      $falt = array_merge(ARTIKEL_STANDARD, $ny);
      $andr = ['Grundpris ' . import_visa(['pris'], $falt['utpris']), 'Moms ' . import_visa(['moms'], $falt['moms'])];
      foreach (array_filter($nyaGrupp, fn($p) => $p !== null) as $g => $p) $andr[] = 'Grupp ' . $g . ' ' . pris_text($p);
      import_exempel($r, $nr, $falt['namn'], 'ny', $andr);
      $r['priser'] += count(array_filter([$falt['utpris'], $falt['inpris']], fn($p) => $p !== null)) + count(array_filter($nyaGrupp, fn($p) => $p !== null));
      if ($skarp) {
        $kolumner = array_keys($falt);
        db()->prepare('INSERT INTO artiklar (artikelnr, ' . implode(', ', $kolumner) . ', aktiv, skapad, uppdaterad, uppdaterad_av)
                       VALUES (?' . str_repeat(', ?', count($kolumner)) . ', 1, ?, ?, ?)')
            ->execute(array_merge([$nr], array_values($falt), [now(), now(), $av]));
        $id = (int)db()->lastInsertId();
        foreach (['utpris', 'inpris'] as $f) logga_pris($id, $f, null, null, null, $falt[$f], $av, 'import');
        foreach ($nyaGrupp as $g => $p) if ($p !== null) satt_grupppris($id, $g, $p, $av, 'import');
      }
      continue;
    }

    // Befintlig artikel: bara det som skiljer sig sparas.
    $id = (int)$fore['id'];
    $andrat = [];
    foreach ($ny as $f => $v) {
      if (!import_lika(ARTIKEL_FALT_TYP[$f], $fore[$f], $v)) $andrat[$f] = $v;
    }
    $andradeGrupp = [];
    foreach ($nyaGrupp as $g => $p) {
      if (!samma_pris($grupp[$id][$g] ?? null, $p)) $andradeGrupp[$g] = $p;
    }
    // Är grundpriset i filen gamla priset plus moms har filen troligen exporterats inklusive moms.
    if (isset($ny['utpris']) && $fore['utpris'] !== null && (float)$fore['utpris'] > 0) {
      $momsJamford++;
      $medMoms = round((float)$fore['utpris'] * (1 + (float)$fore['moms'] / 100), 2);
      if ((float)$fore['moms'] > 0 && abs($ny['utpris'] - $medMoms) <= 0.02) $r['moms_misstanke']++;
    }
    $aterAktiveras = !empty($val['synka']) && !(int)$fore['aktiv'];
    if (!$andrat && !$andradeGrupp && !$aterAktiveras) {
      $r['oforandrade']++;
      continue;
    }
    $r['andrade']++;
    $text = [];
    foreach ($andrat as $f => $v) {
      $text[] = FALT_NAMN[$f] . ': ' . import_visa(ARTIKEL_FALT_TYP[$f], $fore[$f]) . ' → ' . import_visa(ARTIKEL_FALT_TYP[$f], $v);
      if (in_array($f, ['utpris', 'inpris'], true)) $r['priser']++;
    }
    foreach ($andradeGrupp as $g => $p) {
      $text[] = 'Grupp ' . $g . ': ' . import_visa(['pris'], $grupp[$id][$g] ?? null) . ' → ' . ($p === null ? 'tas bort (grundpris)' : pris_text($p));
      $r['priser']++;
    }
    if ($aterAktiveras) $text[] = 'Blir aktiv igen';
    import_exempel($r, $nr, $ny['namn'] ?? $fore['namn'], 'andrad', $text);
    if ($skarp) {
      $set = array_keys($andrat);
      $varden = array_values($andrat);
      if ($aterAktiveras) {
        $set[] = 'aktiv';
        $varden[] = 1;
      }
      if ($set) {
        db()->prepare('UPDATE artiklar SET ' . implode(', ', array_map(fn($f) => "$f = ?", $set)) . ', uppdaterad = ?, uppdaterad_av = ? WHERE id = ?')
            ->execute(array_merge($varden, [now(), $av, $id]));
      }
      foreach (['utpris', 'inpris'] as $f) {
        if (array_key_exists($f, $andrat)) logga_pris($id, $f, null, null, $fore[$f] === null ? null : (float)$fore[$f], $andrat[$f], $av, 'import');
      }
      foreach ($andradeGrupp as $g => $p) satt_grupppris($id, $g, $p, $av, 'import');
    }
  }

  if ($momsJamford >= 10 && $r['moms_misstanke'] > $momsJamford * 0.3) {
    import_varna($r, 'Varning: många grundpriser i filen är exakt det gamla priset plus moms. Exportera prislistan med "Exkl. moms" och ladda upp den igen.');
  } else {
    $r['moms_misstanke'] = 0;
  }

  // Artiklar som finns här men inte i filen.
  $saknade = array_filter(array_diff_key($befintliga, $sedda), fn($a) => (int)$a['aktiv'] === 1);
  $r['saknas'] = count($saknade);
  if (!empty($val['synka']) && isset($kol['namn']) && $saknade) {
    $r['inaktiveras'] = count($saknade);
    if ($skarp) {
      $st = db()->prepare('UPDATE artiklar SET aktiv = 0, uppdaterad = ?, uppdaterad_av = ? WHERE id = ?');
      foreach ($saknade as $a) $st->execute([now(), $av, $a['id']]);
    }
  }
}

// ---------- kunder ----------

function import_kunder(array $rader, array $val, ?array $user, array &$r): void {
  [$hi, $kol] = tabell_kolumner($rader, IMPORT_KUND_FALT, 'kundnr');
  if ($hi < 0) {
    $r['fel'] = 'Hittar ingen kolumn med kundnummer. Rubriken ska heta till exempel "Kundnr", "Kundnummer" eller "customerNumber".';
    return;
  }
  if (!isset($kol['namn'])) {
    $r['fel'] = 'Hittar ingen kolumn med kundens namn. Rubriken ska heta till exempel "Namn", "Kundnamn" eller "customerName".';
    return;
  }
  $r['kolumner'] = array_values(array_map(fn($f) => FALT_NAMN[$f] ?? $f, array_keys($kol)));
  $skarp = $user !== null;
  $av = $skarp ? vem($user) : '';
  $befintliga = [];
  foreach (db()->query('SELECT * FROM kunder') as $k) $befintliga[mb_strtolower($k['kundnr'], 'UTF-8')] = $k;

  $sedda = [];
  foreach (array_slice($rader, $hi + 1) as $i => $rad) {
    $radnr = $hi + $i + 2;
    $nr = enRad($rad[$kol['kundnr']] ?? '', 40);
    if ($nr === '') {
      if (implode('', $rad) !== '') {
        $r['overhoppade']++;
        import_varna($r, "Rad $radnr saknar kundnummer och hoppas över.");
      }
      continue;
    }
    $r['rader']++;
    $nyckel = mb_strtolower($nr, 'UTF-8');
    if (isset($sedda[$nyckel])) {
      $r['overhoppade']++;
      import_varna($r, "Kund $nr finns på flera rader. Bara den första används (rad $radnr hoppas över).");
      continue;
    }
    $sedda[$nyckel] = true;
    $fore = $befintliga[$nyckel] ?? null;

    $ny = [];
    foreach (KUND_FALT_TYP as $f => $typ) {
      if (!isset($kol[$f])) continue;
      [$v, $fel] = import_varde($typ, (string)($rad[$kol[$f]] ?? ''));
      if ($fel !== null) {
        import_varna($r, "Kund $nr: " . FALT_NAMN[$f] . " $fel, fältet ändras inte.");
        continue;
      }
      $ny[$f] = $v;
    }
    if (($ny['namn'] ?? '') === '') {
      if ($fore) {
        unset($ny['namn']);
      } else {
        $r['overhoppade']++;
        import_varna($r, "Kund $nr saknar namn och hoppas över.");
        continue;
      }
    }

    if (!$fore) {
      $r['nya']++;
      import_exempel($r, $nr, $ny['namn'], 'ny', array_values(array_filter([
        isset($ny['kundgrupp']) ? 'Kundgrupp ' . $ny['kundgrupp'] : null,
        'Prisgrupp: ' . prisgrupp_text($ny['prisgrupp'] ?? null),
        ($ny['lev_ort'] ?? '') !== '' ? $ny['lev_ort'] : null,
      ])));
      if ($skarp) {
        $kolumner = array_keys($ny);
        db()->prepare('INSERT INTO kunder (kundnr, ' . implode(', ', $kolumner) . ', aktiv, skapad, uppdaterad, uppdaterad_av)
                       VALUES (?' . str_repeat(', ?', count($kolumner)) . ', 1, ?, ?, ?)')
            ->execute(array_merge([$nr], array_values($ny), [now(), now(), $av]));
      }
      continue;
    }

    $andrat = [];
    foreach ($ny as $f => $v) {
      if (!import_lika(KUND_FALT_TYP[$f], $fore[$f], $v)) $andrat[$f] = $v;
    }
    $aterAktiveras = !empty($val['synka']) && !(int)$fore['aktiv'];
    if (!$andrat && !$aterAktiveras) {
      $r['oforandrade']++;
      continue;
    }
    $r['andrade']++;
    $text = [];
    foreach ($andrat as $f => $v) {
      $text[] = FALT_NAMN[$f] . ': ' . import_visa(KUND_FALT_TYP[$f], $fore[$f]) . ' → ' . import_visa(KUND_FALT_TYP[$f], $v);
    }
    if ($aterAktiveras) $text[] = 'Blir aktiv igen';
    import_exempel($r, $nr, $ny['namn'] ?? $fore['namn'], 'andrad', $text);
    if ($skarp) {
      $set = array_keys($andrat);
      $varden = array_values($andrat);
      if ($aterAktiveras) {
        $set[] = 'aktiv';
        $varden[] = 1;
      }
      db()->prepare('UPDATE kunder SET ' . implode(', ', array_map(fn($f) => "$f = ?", $set)) . ', uppdaterad = ?, uppdaterad_av = ? WHERE id = ?')
          ->execute(array_merge($varden, [now(), $av, $fore['id']]));
    }
  }

  $saknade = array_filter(array_diff_key($befintliga, $sedda), fn($k) => (int)$k['aktiv'] === 1);
  $r['saknas'] = count($saknade);
  if (!empty($val['synka']) && $saknade) {
    $r['inaktiveras'] = count($saknade);
    if ($skarp) {
      $st = db()->prepare('UPDATE kunder SET aktiv = 0, uppdaterad = ?, uppdaterad_av = ? WHERE id = ?');
      foreach ($saknade as $k) $st->execute([now(), $av, $k['id']]);
    }
  }
}

// ---------- kundpriser ----------

function import_kundpriser(array $rader, array $val, ?array $user, array &$r): void {
  [$hi, $kol] = tabell_kolumner($rader, IMPORT_KUNDPRIS_FALT, 'artikelnr');
  if ($hi < 0 || !isset($kol['pris'])) {
    $r['fel'] = 'Filen behöver en kolumn med artikelnummer och en med pris, till exempel "Artikelnr" och "Pris".';
    return;
  }
  $valdKund = null;
  if (!isset($kol['kundnr'])) {
    $valdKund = ($val['kundnr'] ?? '') !== '' ? kund_via_nr((string)$val['kundnr']) : null;
    if (!$valdKund) {
      $r['fel'] = 'Filen har ingen kolumn med kundnummer. Skriv kundnumret som priserna gäller i rutan "Kundnr" och försök igen.';
      return;
    }
  }
  $r['kolumner'] = array_values(array_map(fn($f) => FALT_NAMN[$f] ?? $f, array_keys($kol)));
  $skarp = $user !== null;
  $av = $skarp ? vem($user) : '';
  $kunder = [];
  foreach (db()->query('SELECT id, kundnr, namn FROM kunder') as $k) $kunder[mb_strtolower($k['kundnr'], 'UTF-8')] = $k;
  $artiklar = [];
  foreach (db()->query('SELECT id, artikelnr, namn FROM artiklar') as $a) $artiklar[mb_strtolower($a['artikelnr'], 'UTF-8')] = $a;
  $fore = [];
  foreach (db()->query('SELECT kund_id, artikel_id, pris FROM kund_priser') as $p) $fore[$p['kund_id'] . ':' . $p['artikel_id']] = (float)$p['pris'];

  $sedda = [];
  foreach (array_slice($rader, $hi + 1) as $i => $rad) {
    $radnr = $hi + $i + 2;
    $anr = enRad($rad[$kol['artikelnr']] ?? '', 40);
    if ($anr === '') continue;
    $r['rader']++;
    $kund = $valdKund ?? ($kunder[mb_strtolower(enRad($rad[$kol['kundnr']] ?? '', 40), 'UTF-8')] ?? null);
    $artikel = $artiklar[mb_strtolower($anr, 'UTF-8')] ?? null;
    if (!$kund) {
      $r['overhoppade']++;
      import_varna($r, "Rad $radnr: kundnumret \"" . ($rad[$kol['kundnr']] ?? '') . '" finns inte i kundregistret.');
      continue;
    }
    if (!$artikel) {
      $r['overhoppade']++;
      import_varna($r, "Rad $radnr: artikel $anr finns inte i artikelregistret.");
      continue;
    }
    $cell = (string)($rad[$kol['pris']] ?? '');
    $pris = pris_ur_text($cell);
    if ($pris === false) {
      $r['overhoppade']++;
      import_varna($r, "Rad $radnr: \"$cell\" är inget pris.");
      continue;
    }
    $nyckel = $kund['id'] . ':' . $artikel['id'];
    if (isset($sedda[$nyckel])) {
      $r['overhoppade']++;
      import_varna($r, "Rad $radnr: artikel $anr för kund {$kund['kundnr']} finns redan på en tidigare rad.");
      continue;
    }
    $sedda[$nyckel] = true;
    $gammalt = $fore[$nyckel] ?? null;
    if (samma_pris($gammalt, $pris)) {
      $r['oforandrade']++;
      continue;
    }
    $gammalt === null ? $r['nya']++ : $r['andrade']++;
    $r['priser']++;
    import_exempel($r, $kund['kundnr'] . ' / ' . $anr, $kund['namn'] . ': ' . $artikel['namn'], $gammalt === null ? 'ny' : 'andrad',
      ['Kundpris: ' . import_visa(['pris'], $gammalt) . ' → ' . ($pris === null ? 'tas bort' : pris_text($pris))]);
    if ($skarp) satt_kundpris((int)$kund['id'], (int)$artikel['id'], $pris, $av, 'import');
  }
}

// Sparar importen i historiken.
function import_logga(array $r, string $filnamn, array $user): void {
  db()->prepare('INSERT INTO importlogg (typ, filnamn, av, nya, andrade, oforandrade, overhoppade, tid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$r['typ'], mb_substr($filnamn, 0, 200, 'UTF-8'), vem($user), $r['nya'], $r['andrade'], $r['oforandrade'], $r['overhoppade'], now()]);
}

// ---------- uppladdade filer ----------

// Filen sparas tills importen är genomförd eller avbruten. Mappen går inte att nå från webben.
function import_mapp(): ?string {
  $mapp = dirname(__DIR__) . '/uploads/import';
  if (!is_dir($mapp) && !@mkdir($mapp, 0755, true)) return null;
  $skydd = dirname($mapp) . '/.htaccess';
  if (!is_file($skydd)) {
    @file_put_contents($skydd, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
  }
  // Filer som blivit liggande i mer än ett dygn tas bort.
  foreach (glob($mapp . '/*') ?: [] as $f) {
    if (preg_match('/\.(xlsx|csv)$/', $f) && filemtime($f) < time() - 86400) @unlink($f);
  }
  return is_writable($mapp) ? $mapp : null;
}

function import_fil(array $imp): ?string {
  $mapp = import_mapp();
  if ($mapp === null || !preg_match('/^[a-f0-9]{32}$/', $imp['token'] ?? '') || !in_array($imp['ext'] ?? '', ['xlsx', 'csv'], true)) return null;
  $fil = $mapp . '/' . $imp['token'] . '.' . $imp['ext'];
  return is_file($fil) ? $fil : null;
}
