<?php
// Läser första bladet i en Excel-fil (.xlsx) eller en CSV-fil som en lista med rader.

const TABELL_MAX_RADER = 20000;
const TABELL_MAX_KOLUMNER = 200;

// Kastar RuntimeException med ett meddelande som kan visas för användaren.
function tabell_las(string $fil, string $filnamn): array {
  $typ = strtolower(pathinfo($filnamn, PATHINFO_EXTENSION));
  $borjan = (string)@file_get_contents($fil, false, null, 0, 4);
  if ($typ === 'xls') {
    throw new RuntimeException('Filen är i det gamla Excel-formatet (.xls). Spara den som .xlsx eller .csv i Excel och försök igen.');
  }
  $rader = str_starts_with($borjan, "PK\x03\x04") ? xlsx_rader($fil) : csv_rader($fil);
  // Tomma rader i slutet tas bort, och alla rader får lika många kolumner.
  while ($rader && implode('', end($rader)) === '') array_pop($rader);
  if (!$rader) throw new RuntimeException('Filen är tom.');
  $bredd = max(array_map('count', $rader));
  foreach ($rader as &$r) $r = array_pad($r, $bredd, '');
  unset($r);
  return $rader;
}

function xlsx_rader(string $fil): array {
  if (!class_exists('ZipArchive')) {
    throw new RuntimeException('Servern kan inte läsa Excel-filer. Spara filen som CSV i Excel (Arkiv, Spara som, CSV UTF-8) och ladda upp den i stället.');
  }
  $zip = new ZipArchive();
  if ($zip->open($fil) !== true) throw new RuntimeException('Filen gick inte att öppna som en Excel-fil.');
  try {
    $blad = xlsx_forsta_blad($zip);
    $delade = xlsx_delade_strangar($zip);
    $info = $zip->statName($blad);
    if (!$info) throw new RuntimeException('Hittar inget blad i Excel-filen.');
    if ($info['size'] > 60 * 1048576) throw new RuntimeException('Bladet i Excel-filen är för stort.');
    $xml = xlsx_xml((string)$zip->getFromName($blad));
  } finally {
    $zip->close();
  }

  $rader = [];
  $data = $xml->sheetData;
  foreach ($data->row as $row) {
    if (count($rader) >= TABELL_MAX_RADER) throw new RuntimeException('Filen har fler än ' . TABELL_MAX_RADER . ' rader.');
    $nr = (int)($row['r'] ?? 0) ?: count($rader) + 1;
    // Hoppade radnummer betyder tomma rader.
    while (count($rader) < $nr - 1) $rader[] = [];
    $rad = [];
    $pos = 0;
    foreach ($row->c as $c) {
      $ref = (string)($c['r'] ?? '');
      $kol = $ref !== '' ? xlsx_kolumn($ref) : $pos;
      $pos = $kol + 1;
      if ($kol >= TABELL_MAX_KOLUMNER) continue;
      $typ = (string)($c['t'] ?? '');
      $v = isset($c->v) ? (string)$c->v : '';
      $varde = match ($typ) {
        's' => $delade[(int)$v] ?? '',
        'inlineStr' => xlsx_text($c->is),
        'b' => $v === '1' ? 'TRUE' : 'FALSE',
        'str', 'e' => $v,
        default => xlsx_tal($v),
      };
      while (count($rad) < $kol) $rad[] = '';
      $rad[$kol] = trim($varde);
    }
    $rader[] = $rad;
  }
  return $rader;
}

function xlsx_xml(string $text): SimpleXMLElement {
  if ($text === '') throw new RuntimeException('Excel-filen är skadad.');
  $gamla = libxml_use_internal_errors(true);
  $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
  libxml_clear_errors();
  libxml_use_internal_errors($gamla);
  if ($xml === false) throw new RuntimeException('Excel-filen är skadad.');
  return $xml;
}

// Sökvägen till första bladet enligt arbetsboken, annars standardnamnet.
function xlsx_forsta_blad(ZipArchive $zip): string {
  $wb = $zip->getFromName('xl/workbook.xml');
  $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
  if ($wb !== false && $rels !== false) {
    $bok = xlsx_xml($wb);
    $sheet = $bok->sheets->sheet[0] ?? null;
    if ($sheet !== null) {
      $rid = (string)($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
      foreach (xlsx_xml($rels)->Relationship as $r) {
        if ((string)$r['Id'] === $rid) {
          $mal = (string)$r['Target'];
          $mal = str_starts_with($mal, '/') ? ltrim($mal, '/') : 'xl/' . $mal;
          if (!str_contains($mal, '..')) return $mal;
        }
      }
    }
  }
  return 'xl/worksheets/sheet1.xml';
}

function xlsx_delade_strangar(ZipArchive $zip): array {
  $text = $zip->getFromName('xl/sharedStrings.xml');
  if ($text === false) return [];
  $ut = [];
  foreach (xlsx_xml($text)->si as $si) $ut[] = xlsx_text($si);
  return $ut;
}

// Texten i en cell, även när den har olika formatering i olika delar.
function xlsx_text(?SimpleXMLElement $el): string {
  if ($el === null) return '';
  if (isset($el->t)) return (string)$el->t;
  $s = '';
  // Tomma celler har varken t eller r.
  foreach ($el->r ?? [] as $r) $s .= (string)$r->t;
  return $s;
}

// "AB12" ger kolumn 27 (räknat från 0).
function xlsx_kolumn(string $ref): int {
  $n = 0;
  for ($i = 0, $len = strlen($ref); $i < $len && ctype_alpha($ref[$i]); $i++) {
    $n = $n * 26 + (ord(strtoupper($ref[$i])) - 64);
  }
  return max(0, $n - 1);
}

// Tal sparas som 4.7999999999999998 i Excel. Det blir 4.8 här.
function xlsx_tal(string $v): string {
  if ($v === '' || preg_match('/^-?\d+$/', $v) || !is_numeric($v)) return $v;
  return rtrim(rtrim(number_format(round((float)$v, 9), 9, '.', ''), '0'), '.');
}

function csv_rader(string $fil): array {
  $text = (string)file_get_contents($fil, false, null, 0, 30 * 1048576);
  if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
  if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
  if (str_contains(substr($text, 0, 2000), "\0")) throw new RuntimeException('Filen gick inte att läsa. Ladda upp en Excel-fil (.xlsx) eller en CSV-fil.');
  // Avgränsaren är det tecken som förekommer mest på första raden.
  $forsta = strtok($text, "\n") ?: '';
  $antal = ['tab' => substr_count($forsta, "\t"), ';' => substr_count($forsta, ';'), ',' => substr_count($forsta, ',')];
  arsort($antal);
  $avgr = array_key_first($antal) === 'tab' ? "\t" : array_key_first($antal);
  $strom = fopen('php://temp', 'r+');
  fwrite($strom, $text);
  rewind($strom);
  $rader = [];
  while (($r = fgetcsv($strom, 0, $avgr, '"', '')) !== false) {
    if (count($rader) >= TABELL_MAX_RADER) throw new RuntimeException('Filen har fler än ' . TABELL_MAX_RADER . ' rader.');
    $rader[] = array_map(fn($x) => trim((string)$x), array_slice($r, 0, TABELL_MAX_KOLUMNER));
  }
  fclose($strom);
  return $rader;
}

// Gör rubriker jämförbara: "Art. Nr" blir "artnr" och "Gällande utpris" blir "gällandeutpris".
function rubrik_nyckel(string $s): string {
  return (string)preg_replace('/[^a-z0-9åäöé]+/u', '', mb_strtolower(trim($s), 'UTF-8'));
}

// Hittar raden med rubriker (bland de första tio) och vilken kolumn varje fält har.
// $falt är fält => lista med möjliga rubriker. Returnerar [rubrikrad-index, fält => kolumn].
function tabell_kolumner(array $rader, array $falt, string $maste): array {
  foreach (array_slice($rader, 0, 10, true) as $i => $rad) {
    $nycklar = array_map('rubrik_nyckel', $rad);
    $hittade = [];
    foreach ($falt as $namn => $alias) {
      foreach ($alias as $a) {
        $k = array_search($a, $nycklar, true);
        if ($k !== false) {
          $hittade[$namn] = $k;
          break;
        }
      }
    }
    if (isset($hittade[$maste])) return [$i, $hittade];
  }
  return [-1, []];
}

function ja_nej(string $s): ?int {
  $s = mb_strtolower(trim($s), 'UTF-8');
  if (in_array($s, ['true', 'yes', 'ja', '1', 'x', 'sant'], true)) return 1;
  if (in_array($s, ['false', 'no', 'nej', '0', 'falskt', ''], true)) return 0;
  return null;
}
