<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('order');
erp_schema();
$seVinst = far($user, 'inpris_se');
$idag = date('Y-m-d');

// Försäljning räknas på leveransdatum, utan avbrutna order, och bara för personens kundgrupper.
[$gv, $gp] = grupp_villkor($user, 'COALESCE(k.kundgrupp, o.kundgrupp)');
$fran = "FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
         JOIN order_rader r ON r.order_id = o.id AND r.typ = 'artikel'
         WHERE o.status <> 'avbruten' AND $gv";

function ov_summa(string $fran, array $gp, string $fom, string $tom): array {
  $st = db()->prepare("SELECT COALESCE(SUM(r.belopp), 0) AS v, COALESCE(SUM(r.belopp - r.kostnad), 0) AS vinst, COUNT(DISTINCT o.id) AS n
                       $fran AND o.leveransdatum BETWEEN ? AND ?");
  $st->execute(array_merge($gp, [$fom, $tom]));
  return array_map('floatval', $st->fetch());
}
function ov_dag(int $dagar): string {
  return date('Y-m-d', strtotime(($dagar >= 0 ? '+' : '') . $dagar . ' days'));
}
function ov_andring(float $nu, float $fore): string {
  if ($fore <= 0) return '';
  $p = round(($nu - $fore) / $fore * 100);
  return '<small class="' . ($p >= 0 ? 'upp' : 'ner') . '">' . ($p >= 0 ? '+' : '−') . abs($p) . ' %</small>';
}
function ov_kr(float $v): string {
  return number_format(round($v), 0, ',', "\u{202F}") . ' kr';
}

$sIdag = ov_summa($fran, $gp, $idag, $idag);
$sIdagForra = ov_summa($fran, $gp, ov_dag(-7), ov_dag(-7));
$s7 = ov_summa($fran, $gp, ov_dag(-6), $idag);
$s7Forra = ov_summa($fran, $gp, ov_dag(-13), ov_dag(-7));
$st = db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                     WHERE o.status <> 'avbruten' AND DATE(o.created_at) = ? AND $gv");
$st->execute(array_merge([$idag], $gp));
$nyaIdag = (int)$st->fetchColumn();
$st->execute(array_merge([ov_dag(-7)], $gp));
$nyaForra = (int)$st->fetchColumn();

// Dag för dag, 30 dagar bakåt och 30 dagar före det.
$st = db()->prepare("SELECT o.leveransdatum AS d, SUM(r.belopp) AS v $fran AND o.leveransdatum BETWEEN ? AND ? GROUP BY o.leveransdatum");
$st->execute(array_merge($gp, [ov_dag(-59), $idag]));
$perDag = array_map('floatval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
$nu = $forra = $etiketter = [];
for ($i = 29; $i >= 0; $i--) {
  $d = ov_dag(-$i);
  $nu[] = $perDag[$d] ?? 0.0;
  $forra[] = $perDag[ov_dag(-$i - 30)] ?? 0.0;
  $etiketter[] = $d;
}
$summa30 = array_sum($nu);
$summa30Forra = array_sum($forra);

// Månaden hittills mot hela förra månaden.
$manad = ov_summa($fran, $gp, date('Y-m-01'), $idag);
$forraManad = ov_summa($fran, $gp, date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month')));
$procent = $forraManad['v'] > 0 ? (int)min(999, round($manad['v'] / $forraManad['v'] * 100)) : null;

// Att plocka: öppna order med leverans idag eller senare, närmast först.
$st = db()->prepare("SELECT o.id, o.foretag, o.leveransdatum, o.leveransdag, o.status,
                            (SELECT COUNT(*) FROM order_rader x WHERE x.order_id = o.id AND x.typ = 'artikel') AS rader,
                            (SELECT COUNT(*) FROM order_rader x WHERE x.order_id = o.id AND x.typ = 'artikel' AND x.plockat IS NOT NULL) AS plockade
                     FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                     WHERE o.status IN ('ny','bekraftad') AND $gv ORDER BY o.leveransdatum IS NULL, o.leveransdatum, o.id LIMIT 5");
$st->execute($gp);
$plocka = $st->fetchAll();
$st = db()->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id LEFT JOIN kunder k ON k.id = COALESCE(o.kund_id, u.kund_id)
                     WHERE o.status IN ('ny','bekraftad') AND $gv");
$st->execute($gp);
$plockaAntal = (int)$st->fetchColumn();

// Att fakturera per kund.
$fakturera = [];
if (far($user, 'fakturera')) {
  $st = db()->prepare("SELECT k.id, k.kundnr, k.namn, COUNT(DISTINCT o.id) AS n, SUM(r.belopp) AS v $fran AND o.status IN ('plockad','levererad') AND o.faktura_id IS NULL AND k.id IS NOT NULL
                       GROUP BY k.id, k.kundnr, k.namn ORDER BY v DESC LIMIT 5");
  $st->execute($gp);
  $fakturera = $st->fetchAll();
}

// Varifrån försäljningen kom, senaste 30 dagarna.
$st = db()->prepare("SELECT o.kalla, SUM(r.belopp) AS v $fran AND o.leveransdatum BETWEEN ? AND ? GROUP BY o.kalla ORDER BY v DESC");
$st->execute(array_merge($gp, [ov_dag(-29), $idag]));
$kallor = array_map('floatval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
$kallFarg = ['personal' => '#0051BA', 'webb' => '#6FA3FF', 'superklipp' => '#FFDA1A', 'veckansklipp' => '#2FD484', 'proceedo' => '#B78CFF', 'abicart' => '#FF8A65'];

// Största kunderna, senaste 30 dagarna.
$st = db()->prepare("SELECT COALESCE(k.namn, o.foretag) AS namn, k.kundnr, COUNT(DISTINCT o.id) AS n, SUM(r.belopp) AS v $fran AND o.leveransdatum BETWEEN ? AND ?
                     GROUP BY COALESCE(k.id, o.foretag), COALESCE(k.namn, o.foretag), k.kundnr ORDER BY v DESC LIMIT 5");
$st->execute(array_merge($gp, [ov_dag(-29), $idag]));
$toppKunder = $st->fetchAll();

// Grafen.
$B = 760; $H = 220; $vMarg = 46; $nMarg = 26;
$max = max(1.0, max($nu), max($forra));
$steg = [500, 1000, 2000, 5000, 10000, 20000, 50000, 100000, 200000, 500000, 1000000];
$rad = 1000000;
foreach ($steg as $s) if ($max / $s <= 4) { $rad = $s; break; }
$topp = ceil($max / $rad) * $rad;
$x = fn(int $i) => $vMarg + $i * ($B - $vMarg - 6) / 29;
$y = fn(float $v) => round(($H - $nMarg) - $v / $topp * ($H - $nMarg - 10), 1);
$linje = fn(array $v) => implode(' ', array_map(fn($i) => round($x($i), 1) . ',' . $y($v[$i]), array_keys($v)));

$timme = (int)date('G');
$halsning = $timme < 10 ? 'God morgon' : ($timme < 18 ? 'Hej' : 'God kväll');
$namn = trim(explode(' ', trim($user['kontakt'] ?: ''))[0] ?? '');
$veckodag = ['', 'måndag', 'tisdag', 'onsdag', 'torsdag', 'fredag', 'lördag', 'söndag'][(int)date('N')];
$manader = ['', 'januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
$initialer = fn(string $s) => mb_strtoupper(implode('', array_map(fn($d) => mb_substr($d, 0, 1, 'UTF-8'), array_slice(preg_split('/\s+/u', trim($s)), 0, 2))), 'UTF-8');

$active = 'admin';
$adminSida = 'oversikt';
$pageTitle = 'Översikt – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="hej">
      <div>
        <h1><?php echo e($halsning . ($namn !== '' ? ', ' . $namn : '')); ?></h1>
        <p><?php echo e(ucfirst($veckodag) . ' ' . (int)date('j') . ' ' . $manader[(int)date('n')]); ?> &middot; Översikt</p>
      </div>
      <div class="portal-actions">
        <?php if (far($user, 'plock')): ?><a class="btn ghost small" href="admin-plock">Plock</a><?php endif; ?>
        <?php if (far($user, 'order_andra')): ?><a class="btn primary small" href="admin-order?ny=1">Ny order</a><?php endif; ?>
      </div>
    </div>

    <div class="ov-grid">
      <div class="detail-card">
        <div class="ov-tal">
          <div><span>Levereras idag</span><b><?php echo ov_kr($sIdag['v']); ?></b><?php echo ov_andring($sIdag['v'], $sIdagForra['v']); ?> <small class="muted" style="color:var(--ink-soft); font-weight:400;">mot förra veckan</small></div>
          <div><span>Senaste 7 dagarna</span><b><?php echo ov_kr($s7['v']); ?></b><?php echo ov_andring($s7['v'], $s7Forra['v']); ?> <small style="color:var(--ink-soft); font-weight:400;">mot veckan före</small></div>
          <div><span>Nya order idag</span><b><?php echo $nyaIdag; ?></b><small style="color:var(--ink-soft); font-weight:400;"><?php echo $nyaForra; ?> samma dag förra veckan</small></div>
        </div>
        <div class="ov-kort-head"><h2>Försäljning, 30 dagar</h2><span class="muted" style="font-size:.86rem;"><?php echo ov_kr($summa30); ?> <?php echo ov_andring($summa30, $summa30Forra); ?></span></div>
        <svg class="ov-graf" viewBox="0 0 <?php echo $B; ?> <?php echo $H; ?>" role="img" aria-label="Försäljning per leveransdag de senaste 30 dagarna, totalt <?php echo e(ov_kr($summa30)); ?>">
          <defs><linearGradient id="ovYta" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--fill)" stop-opacity=".4"/><stop offset="1" style="stop-color:var(--fill)" stop-opacity="0"/></linearGradient></defs>
          <?php for ($g = 0; $g <= 4; $g++): $v = $topp / 4 * $g; $gy = $y($v); ?>
            <line class="rutnat" x1="<?php echo $vMarg; ?>" x2="<?php echo $B; ?>" y1="<?php echo $gy; ?>" y2="<?php echo $gy; ?>"/>
            <text x="<?php echo $vMarg - 8; ?>" y="<?php echo $gy + 4; ?>" text-anchor="end"><?php echo $v >= 1000 ? e(rtrim(rtrim(number_format($v / 1000, 1, ',', ''), '0'), ',')) . ' tkr' : (int)$v; ?></text>
          <?php endfor; ?>
          <?php foreach ([0, 7, 14, 21, 29] as $i): ?>
            <text x="<?php echo round($x($i), 1); ?>" y="<?php echo $H - 6; ?>" text-anchor="middle"><?php echo e(date('j/n', strtotime($etiketter[$i]))); ?></text>
          <?php endforeach; ?>
          <polyline class="forra" points="<?php echo $linje($forra); ?>"/>
          <polygon class="yta" points="<?php echo $vMarg . ',' . ($H - $nMarg) . ' ' . $linje($nu) . ' ' . round($x(29), 1) . ',' . ($H - $nMarg); ?>"/>
          <polyline class="linje" points="<?php echo $linje($nu); ?>"/>
          <circle class="punkt" cx="<?php echo round($x(29), 1); ?>" cy="<?php echo $y($nu[29]); ?>" r="4.5"/>
        </svg>
        <div class="ov-legend"><span><i></i>Senaste 30 dagarna</span><span><i class="streck"></i>30 dagarna före</span></div>
      </div>

      <div>
        <div class="detail-card">
          <h2>Månaden hittills</h2>
          <div class="ov-mal">
            <?php $andel = $procent === null ? 0 : min(100, $procent); $omkrets = 2 * M_PI * 44; ?>
            <svg class="ov-ring" viewBox="0 0 110 110" role="img" aria-label="<?php echo $procent === null ? 'Ingen jämförelse' : $procent . ' procent av förra månaden'; ?>">
              <circle class="bak" cx="55" cy="55" r="44"/>
              <circle class="fram" cx="55" cy="55" r="44" stroke-dasharray="<?php echo round($omkrets * $andel / 100, 1) . ' ' . round($omkrets, 1); ?>"/>
              <text x="55" y="62" text-anchor="middle"><?php echo $procent === null ? '–' : $procent . '%'; ?></text>
            </svg>
            <div>
              <div class="ov-stort" style="font-size:1.6rem;"><?php echo ov_kr($manad['v']); ?></div>
              <p class="muted" style="margin:6px 0 0; font-size:.86rem; color:var(--ink-soft);">av förra månadens <?php echo ov_kr($forraManad['v']); ?></p>
              <?php if ($seVinst): ?><p style="margin:8px 0 0; font-size:.86rem;">Vinst hittills <b><?php echo ov_kr($manad['vinst']); ?></b></p><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="detail-card">
          <div class="ov-kort-head"><h2>Varifrån beställningarna kom</h2><span class="muted" style="font-size:.78rem; color:var(--ink-soft);">30 dagar</span></div>
          <?php $kallSumma = array_sum($kallor); ?>
          <?php if ($kallSumma <= 0): ?>
            <p class="muted" style="color:var(--ink-soft); font-size:.9rem;">Ingen försäljning de senaste 30 dagarna.</p>
          <?php else: ?>
            <div class="ov-stapel"><?php foreach ($kallor as $k => $v): ?><span style="width:<?php echo round($v / $kallSumma * 100, 2); ?>%; background:<?php echo $kallFarg[$k] ?? '#8C97AD'; ?>;"></span><?php endforeach; ?></div>
            <?php foreach ($kallor as $k => $v): ?>
              <div class="ov-kalla"><i style="background:<?php echo $kallFarg[$k] ?? '#8C97AD'; ?>;"></i><span><?php echo e(ORDER_KALLOR[$k] ?? $k); ?></span><b><?php echo ov_kr($v); ?></b><small><?php echo round($v / $kallSumma * 100); ?> %</small></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="ov-tre">
      <div class="detail-card">
        <div class="ov-kort-head"><h2>Att plocka</h2><?php if (far($user, 'plock')): ?><a href="admin-plock"><?php echo $plockaAntal; ?> order &rarr;</a><?php endif; ?></div>
        <?php if (!$plocka): ?><p class="muted" style="color:var(--ink-soft); font-size:.9rem;">Inga order väntar på plock.</p><?php endif; ?>
        <?php foreach ($plocka as $o): ?>
          <a class="ov-rad" href="admin-order?id=<?php echo (int)$o['id']; ?>">
            <span class="av"><?php echo e($initialer($o['foretag'])); ?></span>
            <span class="mitt"><b><?php echo e($o['foretag']); ?></b><small>#<?php echo (int)$o['id']; ?> &middot; <?php echo e(leverans_text($o['leveransdatum'], $o['leveransdag'])); ?></small></span>
            <span class="hoger"><?php echo (int)$o['rader'] ? (int)$o['plockade'] . ' / ' . (int)$o['rader'] : status_pill($o['status']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="detail-card">
        <?php if (far($user, 'fakturera')): ?>
          <div class="ov-kort-head"><h2>Att fakturera</h2><a href="admin-fakturering">Fakturering &rarr;</a></div>
          <?php if (!$fakturera): ?><p class="muted" style="color:var(--ink-soft); font-size:.9rem;">Inget väntar på fakturering.</p><?php endif; ?>
          <?php foreach ($fakturera as $f): ?>
            <a class="ov-rad" href="admin-fakturering?sok=<?php echo rawurlencode($f['kundnr']); ?>">
              <span class="av"><?php echo e($initialer($f['namn'])); ?></span>
              <span class="mitt"><b><?php echo e($f['namn']); ?></b><small><?php echo e($f['kundnr']); ?> &middot; <?php echo (int)$f['n']; ?> order</small></span>
              <span class="hoger"><?php echo ov_kr((float)$f['v']); ?></span>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="ov-kort-head"><h2>Levererat per dag</h2></div>
          <div class="ov-staplar" aria-hidden="true"><?php $m = max(1, max($nu)); foreach (array_slice($nu, -14) as $v): ?><span style="height:<?php echo round($v / $m * 100); ?>%;"></span><?php endforeach; ?></div>
          <p class="muted" style="color:var(--ink-soft); font-size:.84rem;">De senaste 14 dagarna.</p>
        <?php endif; ?>
      </div>

      <div class="detail-card">
        <div class="ov-kort-head"><h2>Största kunderna</h2><span class="muted" style="font-size:.78rem; color:var(--ink-soft);">30 dagar</span></div>
        <?php if (!$toppKunder): ?><p class="muted" style="color:var(--ink-soft); font-size:.9rem;">Ingen försäljning de senaste 30 dagarna.</p><?php endif; ?>
        <?php foreach ($toppKunder as $k): ?>
          <div class="ov-rad">
            <span class="av"><?php echo e($initialer($k['namn'])); ?></span>
            <span class="mitt"><b><?php echo e($k['namn']); ?></b><small><?php echo e(trim(($k['kundnr'] ?? '') . ' · ' . (int)$k['n'] . ' order', ' ·')); ?></small></span>
            <span class="hoger"><?php echo ov_kr((float)$k['v']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
