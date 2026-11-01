<?php
// Visar veckansklippets bilder och PDF. Filnamnen byts vid varje uppladdning,
// så webbläsare och mobiler får spara dem länge.
define('LP_UTAN_SESSION', true);
require __DIR__ . '/includes/app.php';
veckansklipp_schema();

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
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($fil));
if ($sida === 'pdf') {
  header('Content-Type: application/pdf');
  header('Content-Disposition: ' . (isset($_GET['ladda']) ? 'attachment' : 'inline') . '; filename="' . $namn . '.pdf"');
} else {
  header('Content-Type: image/jpeg');
  header('Content-Disposition: inline; filename="' . $namn . '-sida-' . (int)$sida . '.jpg"');
}
readfile($fil);
