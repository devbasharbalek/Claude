<?php
require __DIR__ . '/includes/app.php';
$user = require_login();

$order = order_for_user((int)($_GET['id'] ?? 0), (int)$user['id']);
veckansklipp_schema();
$osk = $order ? order_superklipp((int)$order['id']) : null;
if (!$order) {
  http_response_code(404);
  $active = 'konto';
  $pageTitle = 'Beställningen hittades inte – Ludvika Partiaffär';
  require __DIR__ . '/includes/head.php';
  echo '<section><div class="wrap narrow"><div class="form-card"><h1 class="card-title">Hittades inte</h1><p class="lead">Beställningen finns inte, eller tillhör ett annat konto.</p><a class="btn ghost" href="konto" style="margin-top:18px;">Till Mitt konto</a></div></div></section>';
  require __DIR__ . '/includes/foot.php';
  exit;
}

$active = 'konto';
$pageTitle = 'Beställning #' . $order['id'] . ' – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="konto">&larr; Mina beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Beställning</span>
        <h1>#<?php echo (int)$order['id']; ?> <?php echo status_pill($order['status']); ?></h1>
        <p>Skickad <?php echo e(datum($order['created_at'])); ?><?php if ($order['updated_at'] !== $order['created_at']): ?> &middot; uppdaterad <?php echo e(datum($order['updated_at'])); ?><?php endif; ?></p>
      </div>
      <div class="portal-actions">
        <?php if ($osk): ?>
          <?php if (!empty($osk['veckansklipp_id']) || str_starts_with($osk['etikett'], 'Veckansklipp')): ?>
            <a class="btn primary" href="veckansklipp">Till veckansklippet</a>
          <?php else: ?>
            <a class="btn primary" href="superklipp">Till veckans superklipp</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn primary" href="bestall?igen=<?php echo (int)$order['id']; ?>">Beställ samma igen</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="detail-grid">
      <div>
        <div class="detail-card">
          <h2>Beställning</h2>
          <dl class="kv">
            <dt>Leveransdag</dt><dd><?php echo e(($leveransdagar[$order['leveransdag']] ?? $order['leveransdag']) . ($osk ? ' ' . date('j/n', strtotime($osk['leveransdatum'])) : '')); ?></dd>
            <?php if ($osk): ?>
              <dt>Från</dt><dd><?php echo e($osk['etikett']); ?></dd>
            <?php else: ?>
              <dt>Varugrupper</dt><dd><?php echo e(kategori_namn($order['kategorier'])); ?></dd>
            <?php endif; ?>
          </dl>
          <?php if ($osk) echo sk_order_tabell(order_rader((int)$order['id'])); ?>
          <?php
            // Rader som vi har lagt in på ordern, med kundens priser.
            $artRader = array_values(array_filter(order_rader_alla((int)$order['id']), fn($r) => in_array($r['typ'] ?? 'lista', ['artikel', 'text'], true)));
            if ($artRader):
              $sum = order_summor($artRader);
          ?>
            <div class="sk-scroll"><table class="sk-table sk-order">
              <thead><tr><th>Vara</th><th class="num">Antal</th><th class="num">Pris</th><th class="num">Belopp</th></tr></thead>
              <tbody>
              <?php foreach ($artRader as $r): ?>
                <?php if ($r['typ'] === 'text'): ?><tr><td colspan="4" style="font-style:italic;"><?php echo e($r['produkt']); ?></td></tr><?php continue; endif; ?>
                <tr><td><?php echo e($r['produkt']); ?></td>
                  <td class="pris" style="font-weight:500;"><?php echo e(($r['kolli'] !== null ? tal_text($r['kolli']) . ' kolli, ' : '') . tal_text($r['enheter']) . ' ' . $r['enhet']); ?></td>
                  <td class="pris" style="font-weight:500;"><?php echo e(pris_text($r['pris']) . ' kr/' . ((int)$r['per_kolli'] ? 'kolli' : $r['enhet'])); ?></td>
                  <td class="pris"><?php echo e(pris_text($r['belopp'])); ?></td></tr>
              <?php endforeach; ?>
              <tr><td colspan="3" style="font-weight:600;">Summa exkl. moms</td><td class="pris"><?php echo e(pris_text($sum['netto'])); ?></td></tr>
              <tr><td colspan="3">Moms</td><td class="pris" style="font-weight:500;"><?php echo e(pris_text($sum['moms'])); ?></td></tr>
              </tbody>
            </table></div>
          <?php endif; ?>
          <?php if ($order['meddelande'] !== ''): ?>
            <div class="order-text" style="margin-top:16px;"><?php echo e($order['meddelande']); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="detail-card">
        <h2>Leverans till</h2>
        <dl class="kv">
          <dt>Företag</dt><dd><?php echo e($order['foretag']); ?></dd>
          <dt>Kontakt</dt><dd><?php echo e($order['kontakt']); ?></dd>
          <dt>Telefon</dt><dd><?php echo e($order['telefon']); ?></dd>
          <dt>E-post</dt><dd><?php echo e($order['epost']); ?></dd>
          <dt>Adress</dt><dd><?php echo e($order['adress']); ?></dd>
        </dl>
      </div>
    </div>
    <p class="form-foot" style="margin-top:20px;">Vill du ändra eller avbryta beställningen? Ring oss på <a href="tel:+4624018355">0240-183 55</a>.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
