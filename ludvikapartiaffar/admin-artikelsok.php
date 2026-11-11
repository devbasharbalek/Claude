<?php
// Söker artiklar till en order och svarar med kundens pris. Används av ordersidan.
require __DIR__ . '/includes/app.php';
$user = require_behorighet('order_andra');
erp_schema();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$q = enRad($_GET['q'] ?? '', 80);
$kund = (int)($_GET['kund'] ?? 0) ? kund_hamta((int)$_GET['kund']) : null;
$visaInpris = far($user, 'inpris_se');
if ($q === '') {
  echo '[]';
  exit;
}

// Exakt artikelnummer först, sedan nummer som börjar likadant, sedan namn som innehåller alla ord.
[$gv, $params] = grupp_villkor($user, 'a.artikelgrupp', 'artikelgrupper');
$where = ['a.aktiv = 1', $gv];
$ordDel = [];
foreach (array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $ord) {
  $esc = addcslashes($ord, '%_\\');
  $ordDel[] = '(a.namn LIKE ? OR a.artikelnr LIKE ?)';
  array_push($params, "%$esc%", "$esc%");
}
$where[] = '(' . implode(' AND ', $ordDel) . ')';
$st = db()->prepare('SELECT a.* FROM artiklar a WHERE ' . implode(' AND ', $where) . '
                     ORDER BY a.artikelnr = ? DESC, a.artikelnr LIKE ? DESC, a.namn LIMIT 15');
$st->execute(array_merge($params, [$q, addcslashes($q, '%_\\') . '%']));

$ut = [];
foreach ($st as $a) {
  $r = order_rad_for_artikel($a, $kund);
  $ut[] = [
    'artikel_id' => $r['artikel_id'], 'artikelnr' => $r['artikelnr'], 'namn' => $r['produkt'], 'enhet' => $r['enhet'],
    'epk' => $r['enheter_per_kolli'], 'per_kolli' => $r['per_kolli'], 'viktvara' => $r['viktvara'], 'moms' => $r['moms'],
    'pris' => $r['pris'], 'kalla' => priskalla_text($r['priskalla'], $kund && $kund['prisgrupp'] !== null ? (int)$kund['prisgrupp'] : null),
    'inpris' => $visaInpris ? $r['inpris'] : null, 'kolli' => kolli_text($a), 'kg' => $r['kg_per_kolli'],
  ];
}
echo json_encode($ut, JSON_UNESCAPED_UNICODE);
