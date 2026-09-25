<?php
require __DIR__ . '/includes/app.php';
$user = require_login();

$order = order_for_user((int)($_GET['id'] ?? 0), (int)$user['id']);
if (!$order) {
  http_response_code(404);
  $active = 'konto';
  $pageTitle = 'Beställningen hittades inte – Ludvika Partiaffär';
  require __DIR__ . '/includes/head.php';
  echo '<section><div class="wrap narrow"><div class="form-card"><h1 class="card-title">Hittades inte</h1><p class="lead">Beställningen finns inte, eller tillhör ett annat konto.</p><a class="btn ghost" href="konto.php" style="margin-top:18px;">Till Mitt konto</a></div></div></section>';
  require __DIR__ . '/includes/foot.php';
  exit;
}

$active = 'konto';
$pageTitle = 'Beställning #' . $order['id'] . ' – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="konto.php">&larr; Mina beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Beställning</span>
        <h1>#<?php echo (int)$order['id']; ?> <?php echo status_pill($order['status']); ?></h1>
        <p>Skickad <?php echo e(datum($order['created_at'])); ?><?php if ($order['updated_at'] !== $order['created_at']): ?> &middot; uppdaterad <?php echo e(datum($order['updated_at'])); ?><?php endif; ?></p>
      </div>
      <div class="portal-actions">
        <a class="btn primary" href="bestall.php?igen=<?php echo (int)$order['id']; ?>">Beställ samma igen</a>
      </div>
    </div>

    <div class="detail-grid">
      <div>
        <div class="detail-card">
          <h2>Beställning</h2>
          <dl class="kv">
            <dt>Leveransdag</dt><dd><?php echo e($leveransdagar[$order['leveransdag']] ?? $order['leveransdag']); ?></dd>
            <dt>Varugrupper</dt><dd><?php echo e(kategori_namn($order['kategorier'])); ?></dd>
          </dl>
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
