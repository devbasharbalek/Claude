<?php
// Plocksedlar och följesedlar för utskrift. En order per sida.
require __DIR__ . '/includes/app.php';
$user = require_behorighet('order');
erp_schema();
veckansklipp_schema();

$typ = ($_GET['typ'] ?? '') === 'foljesedel' ? 'foljesedel' : 'plocksedel';
if ($typ === 'plocksedel' && !far($user, 'plock')) {
  http_response_code(403);
  exit('Du har inte behörighet att skriva ut plocksedlar.');
}
$visaPriser = $typ === 'foljesedel' && !empty($_GET['priser']);

// En order, eller alla order att plocka en leveransdag.
$ids = [];
if (($datum = giltigt_datum((string)($_GET['datum'] ?? ''))) !== null) {
  [$gv, $gp] = grupp_villkor($user, 'COALESCE(k.kundgrupp, o.kundgrupp, u.kundgrupp)');
  $st = db()->prepare("SELECT o.id FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                       WHERE o.leveransdatum = ? AND o.status IN ('ny','bekraftad','plockad') AND $gv ORDER BY o.foretag, o.id");
  $st->execute(array_merge([$datum], $gp));
  $ids = $st->fetchAll(PDO::FETCH_COLUMN);
} else {
  $ids = [(int)($_GET['id'] ?? 0)];
}

$sidor = [];
foreach ($ids as $id) {
  $o = order_hamta((int)$id);
  if (!$o || !far_grupp($user, order_grupp($o))) continue;
  $st = db()->prepare("SELECT r.*, a.produktgrupp FROM order_rader r LEFT JOIN artiklar a ON a.id = r.artikel_id WHERE r.order_id = ?
                       ORDER BY r.typ = 'text', " . ($typ === 'plocksedel' ? 'a.produktgrupp IS NULL, a.produktgrupp, ' : '') . 'r.sortering, r.id');
  $st->execute([(int)$id]);
  $kund = $o['register_id'] ? kund_hamta((int)$o['register_id']) : null;
  $sidor[] = [$o, $st->fetchAll(), $kund, order_superklipp((int)$id)];
}
if (!$sidor) {
  http_response_code(404);
  exit('Det finns ingen order att skriva ut.');
}

$rubrik = $typ === 'plocksedel' ? 'Plocksedel' : 'Följesedel';
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?php echo e($rubrik . (count($sidor) === 1 ? ' #' . $sidor[0][0]['id'] : ' ' . leverans_text($datum))); ?> – Ludvika Partiaffär</title>
<style>
  @page{size:A4; margin:14mm 12mm;}
  *{box-sizing:border-box;}
  body{margin:0; font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; font-size:11pt; color:#111; background:#e9e9e6;}
  .verktyg{position:sticky; top:0; display:flex; gap:10px; justify-content:center; align-items:center; padding:10px; background:#021F86; color:#fff; font-size:10.5pt;}
  .verktyg button, .verktyg a{font:inherit; font-weight:700; border:none; border-radius:999px; padding:8px 18px; cursor:pointer; background:#F7CC11; color:#111; text-decoration:none;}
  .verktyg a{background:#fff;}
  .sida{background:#fff; width:210mm; min-height:297mm; margin:12px auto; padding:14mm 12mm; box-shadow:0 2px 12px rgba(0,0,0,.15);}
  .huvud{display:flex; justify-content:space-between; gap:20px; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:12px;}
  .huvud h1{font-size:20pt; margin:0;}
  .huvud .nr{font-size:14pt; font-weight:700; text-align:right;}
  .huvud small{display:block; font-size:9.5pt; font-weight:400; color:#444;}
  .foretag{font-size:9.5pt; line-height:1.4; color:#333;}
  .foretag b{font-size:11pt; color:#111;}
  .info{display:grid; grid-template-columns:1fr 1fr; gap:6px 24px; margin-bottom:12px;}
  .info h2{font-size:9pt; text-transform:uppercase; letter-spacing:.05em; color:#555; margin:0 0 3px;}
  .info p{margin:0; line-height:1.4;}
  dl{display:grid; grid-template-columns:auto 1fr; gap:2px 10px; margin:0; font-size:10pt;}
  dt{color:#555;} dd{margin:0;}
  .text{border:2px solid #111; padding:6px 10px; font-weight:700; margin:0 0 12px; font-size:12pt;}
  .meddelande{border:1px solid #999; padding:6px 10px; margin:12px 0 0; font-size:10.5pt;}
  table{width:100%; border-collapse:collapse;}
  th{text-align:left; font-size:8.5pt; text-transform:uppercase; letter-spacing:.04em; color:#444; border-bottom:1.5px solid #111; padding:5px 6px;}
  td{border-bottom:1px solid #bbb; padding:7px 6px; vertical-align:top;}
  .num{text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums;}
  .ruta{width:16px; height:16px; border:1.5px solid #111; display:inline-block;}
  .grupp td{font-size:8.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.05em; background:#f1f1ee; padding:4px 6px;}
  .textrad td{font-style:italic;}
  .plockat{width:70px; border-bottom:1px solid #111;}
  .viktvara{font-weight:700;}
  .summa{display:flex; justify-content:space-between; gap:20px; margin-top:12px; font-size:10.5pt;}
  .signatur{display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px; font-size:9.5pt; color:#444;}
  .signatur span{display:block; border-top:1px solid #111; padding-top:4px;}
  @media print{
    body{background:#fff;}
    .verktyg{display:none;}
    .sida{width:auto; min-height:0; margin:0; padding:0; box-shadow:none; page-break-after:always;}
    .sida:last-child{page-break-after:auto;}
  }
</style>
</head>
<body>
<div class="verktyg"><span><?php echo count($sidor) === 1 ? e($rubrik) : count($sidor) . ' ' . mb_strtolower($rubrik === 'Plocksedel' ? 'plocksedlar' : 'följesedlar', 'UTF-8'); ?></span><button type="button" onclick="window.print()">Skriv ut</button>
  <?php if ($typ === 'foljesedel' && count($sidor) === 1): ?><a href="admin-utskrift?typ=foljesedel&amp;id=<?php echo (int)$sidor[0][0]['id']; ?><?php echo $visaPriser ? '' : '&amp;priser=1'; ?>"><?php echo $visaPriser ? 'Utan priser' : 'Med priser'; ?></a><?php endif; ?></div>
<?php foreach ($sidor as [$o, $rader, $kund, $osk]): $varor = array_filter($rader, fn($r) => $r['typ'] !== 'text'); $s = order_summor($rader); ?>
<div class="sida">
  <div class="huvud">
    <div>
      <h1><?php echo e($rubrik); ?></h1>
      <div class="foretag"><b>Ludvika Partiaffär</b><br>Fläderstigen 2, 771 43 Ludvika<br>0240-183 55 &middot; <?php echo e($CONFIG['mail_to']); ?></div>
    </div>
    <div class="nr">Order #<?php echo (int)$o['id']; ?><small>Leverans <?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></small>
      <?php if ($osk): ?><small><?php echo e($osk['etikett']); ?></small><?php endif; ?>
      <?php if ($typ === 'plocksedel' && $o['plockare_namn']): ?><small>Plockare: <?php echo e($o['plockare_namn']); ?></small><?php endif; ?></div>
  </div>

  <div class="info">
    <div>
      <h2>Leveransadress</h2>
      <p><strong><?php echo e($kund ? $kund['kundnr'] . ' ' . $kund['namn'] : $o['foretag']); ?></strong><br>
        <?php if ($kund): ?>
          <?php if ($kund['lev_namn'] !== '' && $kund['lev_namn'] !== $kund['namn']) echo e($kund['lev_namn']) . '<br>'; ?>
          <?php echo e($kund['lev_adress']); ?><br><?php echo e(trim($kund['lev_postnr'] . ' ' . $kund['lev_ort'])); ?>
        <?php else: ?>
          <?php echo e($o['adress']); ?>
        <?php endif; ?></p>
    </div>
    <div>
      <dl>
        <?php if ($kund && $kund['tur'] !== ''): ?><dt>Tur</dt><dd><?php echo e($kund['tur']); ?></dd><?php endif; ?>
        <?php if ($o['kundens_ordernr'] !== ''): ?><dt>Ert ordernr</dt><dd><?php echo e($o['kundens_ordernr']); ?></dd><?php endif; ?>
        <?php if ($o['er_referens'] !== '' || $o['kontakt'] !== ''): ?><dt>Er referens</dt><dd><?php echo e($o['er_referens'] !== '' ? $o['er_referens'] : $o['kontakt']); ?></dd><?php endif; ?>
        <?php if ($o['var_referens'] !== ''): ?><dt>Vår referens</dt><dd><?php echo e($o['var_referens']); ?></dd><?php endif; ?>
        <?php if ($o['telefon'] !== ''): ?><dt>Telefon</dt><dd><?php echo e($o['telefon']); ?></dd><?php endif; ?>
      </dl>
    </div>
  </div>

  <?php if ($typ === 'plocksedel' && $o['plocktext'] !== ''): ?><p class="text"><?php echo e($o['plocktext']); ?></p><?php endif; ?>

  <table>
    <thead><tr>
      <?php if ($typ === 'plocksedel'): ?><th></th><?php endif; ?>
      <th class="num">Antal</th><th>Enhet</th><th>Art.nr</th><th>Artikel</th>
      <?php if ($typ === 'plocksedel'): ?><th>Kommentar</th><th>Plockat</th><?php endif; ?>
      <?php if ($visaPriser): ?><th class="num">Pris</th><th class="num">Belopp</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php $grupp = null; foreach ($rader as $r): ?>
      <?php if ($r['typ'] === 'text'): ?>
        <tr class="textrad"><td colspan="<?php echo $typ === 'plocksedel' ? 7 : ($visaPriser ? 6 : 4); ?>"><?php echo e($r['produkt']); ?></td></tr>
        <?php continue; endif; ?>
      <?php if ($typ === 'plocksedel' && ($r['produktgrupp'] ?? null) !== null && $r['produktgrupp'] !== $grupp): $grupp = $r['produktgrupp']; ?>
        <tr class="grupp"><td colspan="7"><?php echo e($grupp); ?></td></tr>
      <?php endif; ?>
      <?php
        // Följesedeln visar det som plockats när ordern är plockad, annars det beställda.
        $antal = $typ === 'foljesedel' && $r['plockat'] !== null ? (float)$r['plockat'] : rad_att_plocka($r);
        $enhet = rad_plockenhet($r);
        $info = $r['typ'] === 'artikel' && $r['kolli'] !== null ? mangd_text($r['enheter']) . ' ' . $r['enhet'] : ($r['typ'] === 'lista' ? sk_forpackning($r) : '');
        if ((int)$r['viktvara'] && $r['plockvikt'] !== null) $info = tal_text($r['plockvikt']) . ' kg vägt';
      ?>
      <tr>
        <?php if ($typ === 'plocksedel'): ?><td><span class="ruta"></span></td><?php endif; ?>
        <td class="num"><strong><?php echo e(tal_text($antal)); ?></strong></td>
        <td><?php echo e($enhet); ?><?php echo $info !== '' ? '<br><small>' . e($info) . '</small>' : ''; ?></td>
        <td><?php echo e($r['artikelnr']); ?></td>
        <td class="<?php echo (int)$r['viktvara'] ? 'viktvara' : ''; ?>"><?php echo e($r['produkt']); ?><?php echo (int)$r['viktvara'] && $typ === 'plocksedel' ? ' (väg)' : ''; ?></td>
        <?php if ($typ === 'plocksedel'): ?><td><?php echo e($r['plockkommentar']); ?></td><td><span class="plockat">&nbsp;</span><?php echo (int)$r['viktvara'] ? ' kg' : ''; ?></td><?php endif; ?>
        <?php if ($visaPriser): ?><td class="num"><?php echo $r['typ'] === 'artikel' ? e(pris_text($r['pris'])) : ''; ?></td><td class="num"><?php echo $r['typ'] === 'artikel' ? e(pris_text($r['belopp'])) : ''; ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="summa">
    <span><?php echo count($varor); ?> rader &middot; <?php echo e(tal_text(round($s['kolli'], 2))); ?> kolli<?php echo $s['vikt'] > 0 ? ' &middot; netto ' . e(tal_text(round($s['vikt'], 1))) . ' kg' : ''; ?></span>
    <?php if ($visaPriser && $s['har_pris']): ?><span>Summa exkl. moms <strong><?php echo e(pris_text($s['netto'])); ?> kr</strong> &middot; moms <?php echo e(pris_text($s['moms'])); ?> kr</span><?php endif; ?>
  </div>
  <?php if ($typ === 'foljesedel' && $o['kundtext'] !== ''): ?><p class="meddelande"><?php echo e($o['kundtext']); ?></p><?php endif; ?>
  <div class="signatur">
    <?php if ($typ === 'plocksedel'): ?><span>Plockad av</span><span>Kontrollerad av</span>
    <?php else: ?><span>Mottaget av (namnförtydligande)</span><span>Datum och tid</span><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</body>
</html>
