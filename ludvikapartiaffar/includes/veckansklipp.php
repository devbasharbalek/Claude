<?php
// Veckansklipp: bladet från Canva som PDF, plus sidorna som bilder för mobilen.
// Bilderna görs i adminens webbläsare vid uppladdningen, så servern behöver inga PDF-verktyg.

const VK_MAX_SIDOR = 12;
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

function veckansklipp_schema(): void {
  static $klar = false;
  if ($klar) return;
  try {
    db()->query('SELECT 1 FROM veckansklipp LIMIT 1');
  } catch (PDOException $ex) {
    db()->exec(VK_SCHEMA);
  }
  $klar = true;
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

function vk_lank(): string {
  global $CONFIG;
  return rtrim($CONFIG['site_url'], '/') . '/veckansklipp';
}

// Kort sms utan emoji, så att det får plats i ett sms (160 tecken).
function vk_sms(array $vk): string {
  return 'Veckansklipp v.' . (int)$vk['vecka'] . ' är ute! Se veckans erbjudanden: ' . vk_lank()
       . ' Beställ på 0240-183 55. Mvh Ludvika Partiaffär';
}
