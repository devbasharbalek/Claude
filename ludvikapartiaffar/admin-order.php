<?php
require __DIR__ . '/includes/app.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare('SELECT o.*, u.email AS kund_email, u.active AS kund_active
                     FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
  http_response_code(404);
  exit('Beställningen finns inte.');
}

$statusMejl = [
  'bekraftad' => "Din beställning #%d är nu bekräftad. Vi levererar enligt överenskommelse.",
  'levererad' => "Din beställning #%d är levererad. Tack för att du handlar hos oss!",
  'avbruten'  => "Din beställning #%d har avbrutits. Hör av dig på 0240-183 55 om du har frågor.",
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ny = (string)($_POST['status'] ?? '');
  if (isset(ORDER_STATUS[$ny]) && $ny !== $order['status']) {
    db()->prepare('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?')->execute([$ny, now(), $id]);
    $meddelat = false;
    if (!empty($_POST['meddela']) && isset($statusMejl[$ny]) && filter_var($order['epost'], FILTER_VALIDATE_EMAIL)) {
      $rader = ["Hej {$order['kontakt']}!", '', sprintf($statusMejl[$ny], $id)];
      if ($order['user_id']) {
        $rader[] = '';
        $rader[] = 'Se beställningen: ' . $CONFIG['site_url'] . '/order.php?id=' . $id;
      }
      array_push($rader, '', 'Med vänlig hälsning', 'Ludvika Partiaffär, 0240-183 55');
      $meddelat = send_mail($order['epost'], "Beställning #$id: " . mb_strtolower(ORDER_STATUS[$ny]), implode("\n", $rader), $CONFIG['mail_to']);
    }
    flash("Beställning #$id är nu " . mb_strtolower(ORDER_STATUS[$ny]) . ($meddelat ? ', och kunden har fått ett mejl.' : '.'));
  }
  redirect('admin-order.php?id=' . $id);
}

$active = 'admin';
$pageTitle = 'Beställning #' . $id . ' – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin.php">&larr; Alla beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Beställning</span>
        <h1>#<?php echo $id; ?> <?php echo status_pill($order['status']); ?></h1>
        <p>Inkom <?php echo e(datum($order['created_at'])); ?><?php if ($order['updated_at'] !== $order['created_at']): ?> &middot; ändrad <?php echo e(datum($order['updated_at'])); ?><?php endif; ?></p>
      </div>
      <div class="portal-actions">
        <a class="btn ghost" href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $order['telefon'])); ?>">Ring kunden</a>
        <a class="btn ghost" href="mailto:<?php echo e($order['epost']); ?>?subject=<?php echo rawurlencode('Er beställning #' . $id); ?>">Mejla kunden</a>
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
          <div class="order-text" style="margin-top:16px;"><?php echo $order['meddelande'] !== '' ? e($order['meddelande']) : '<span style="color:var(--ink-soft)">Inget meddelande</span>'; ?></div>
        </div>

        <form class="detail-card" method="post" action="admin-order.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <h2>Ändra status</h2>
          <div class="status-form">
            <label for="status" class="sr-only">Ny status</label>
            <select id="status" name="status">
              <?php foreach (ORDER_STATUS as $key => $label): ?>
                <option value="<?php echo $key; ?>"<?php echo $key === $order['status'] ? ' selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn primary small" type="submit">Spara</button>
          </div>
          <label class="check inline" style="margin-top:14px;"><input type="checkbox" name="meddela" value="1" checked> <span>Mejla kunden om ändringen (<?php echo e($order['epost']); ?>)</span></label>
        </form>
      </div>

      <div class="detail-card">
        <h2>Kund</h2>
        <dl class="kv">
          <dt>Företag</dt><dd><?php echo e($order['foretag']); ?></dd>
          <dt>Kontakt</dt><dd><?php echo e($order['kontakt']); ?></dd>
          <dt>Telefon</dt><dd><?php echo e($order['telefon']); ?></dd>
          <dt>E-post</dt><dd><?php echo e($order['epost']); ?></dd>
          <dt>Adress</dt><dd><?php echo e($order['adress']); ?></dd>
          <dt>Konto</dt><dd>
            <?php if ($order['user_id']): ?>
              <a href="admin.php?visa=alla&amp;kund=<?php echo (int)$order['user_id']; ?>" style="color:var(--brand);">Alla beställningar från kunden</a>
              <?php if (!(int)$order['kund_active']): ?><br><span class="pill pill-off">Avstängt konto</span><?php endif; ?>
            <?php else: ?>
              Gäst, beställde utan konto
            <?php endif; ?>
          </dd>
        </dl>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
