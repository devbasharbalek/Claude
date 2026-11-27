<?php
// Visar veckansklippets bilder och PDF för inloggade kunder som ser veckansklippet.
// Filnamnen byts vid varje uppladdning, så webbläsaren får spara dem länge.
define('LP_SESSION_LAS', true);
require __DIR__ . '/includes/app.php';
veckansklipp_schema();

// Samma kontroll som current_user(), men utan att skriva till sessionen.
$user = null;
if (!empty($_SESSION['uid'])) {
  $st = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
  $st->execute([(int)$_SESSION['uid']]);
  $rad = $st->fetch();
  if ($rad && hash_equals($_SESSION['pwh'] ?? '', password_fingerprint($rad['password_hash']))) $user = $rad;
}
if (!ser_erbjudande($user, 'veckansklipp')) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  header('Cache-Control: no-store');
  exit('Logga in med ett kundkonto som har veckansklippet för att se bladet.');
}

$nyckel = (string)($_GET['k'] ?? '');
$sida = (string)($_GET['s'] ?? '');
$vk = null;
if (preg_match('/^[a-f0-9]{16}$/', $nyckel) && ($sida === 'pdf' || preg_match('/^([1-9]|1[0-2])$/', $sida))) {
  $st = db()->prepare('SELECT * FROM veckansklipp WHERE nyckel = ?');
  $st->execute([$nyckel]);
  $vk = $st->fetch() ?: null;
}
$fil = $vk ? vk_fil($nyckel, $sida === 'pdf' ? 'pdf' : (int)$sida) : '';
if (!$vk || !is_file($fil)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Filen finns inte.');
}

$namn = 'Veckansklipp-v' . (int)$vk['vecka'] . '-' . (int)$vk['ar'];
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=31536000, immutable');
header('Content-Length: ' . filesize($fil));
if ($sida === 'pdf') {
  header('Content-Type: application/pdf');
  header('Content-Disposition: ' . (isset($_GET['ladda']) ? 'attachment' : 'inline') . '; filename="' . $namn . '.pdf"');
} else {
  header('Content-Type: image/jpeg');
  header('Content-Disposition: inline; filename="' . $namn . '-sida-' . (int)$sida . '.jpg"');
}
readfile($fil);
