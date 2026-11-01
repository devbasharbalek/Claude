<?php
require __DIR__ . '/includes/app.php';
$user = require_login();
if ($user['role'] === 'admin') redirect('admin.php');

superklipp_schema();
$st = db()->prepare('SELECT o.id, o.leveransdag, o.kategorier, o.status, o.created_at, os.etikett, os.leveransdatum
                     FROM orders o LEFT JOIN order_superklipp os ON os.order_id = o.id
                     WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC LIMIT 200');
$st->execute([$user['id']]);
$orders = $st->fetchAll();
$oppnaKlipp = array_values(array_filter(sk_for_kunder(), 'sk_ar_oppen'));
// Veckansklippet är för restauranger och pizzerior, och för kunder som inte valt grupp.
veckansklipp_schema();
$veckansklipp = vk_senaste();
if ($veckansklipp && (vk_har_passerat($veckansklipp) || !in_array($user['kundgrupp'] ?? null, [null, '', 'L'], true))) $veckansklipp = null;

$active = 'konto';
$pageTitle = 'Mitt konto – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Mitt konto</span>
        <h1><?php echo e($user['foretag']); ?></h1>
        <p>Inloggad som <?php echo e($user['kontakt']); ?> (<?php echo e($user['email']); ?>)</p>
      </div>
      <div class="portal-actions">
        <a class="btn primary" href="bestall.php">Ny beställning</a>
        <a class="btn ghost" href="profil.php">Mina uppgifter</a>
        <form method="post" action="logga-ut.php" style="margin:0;"><?php echo csrf_field(); ?><button class="btn ghost" type="submit">Logga ut</button></form>
      </div>
    </div>

    <?php foreach ($oppnaKlipp as $klipp): ?>
      <div class="sk-notis">
        <p><strong><?php echo e(sk_etikett($klipp)); ?> är ute!</strong> Veckans bästa priser på frukt och grönt. Beställ senast <?php echo e(sk_deadline_text($klipp['deadline'])); ?>.</p>
        <div class="actions"><a class="btn primary small" href="superklipp.php?id=<?php echo (int)$klipp['id']; ?>">Se superklippet</a></div>
      </div>
    <?php endforeach; ?>

    <?php if ($veckansklipp): ?>
      <div class="sk-notis">
        <p><strong><?php echo e(vk_etikett($veckansklipp)); ?> är ute!</strong> Veckans erbjudanden för restauranger och pizzerior, gäller <?php echo e(vk_period($veckansklipp)); ?>.</p>
        <div class="actions"><a class="btn primary small" href="veckansklipp.php">Se veckansklippet</a></div>
      </div>
    <?php endif; ?>

    <h2 style="font-size:1.8rem; text-transform:uppercase; margin-bottom:14px;">Mina beställningar</h2>

    <?php if (!$orders): ?>
      <div class="empty">
        <p>Du har inte gjort några beställningar än.</p>
        <a class="btn primary" href="bestall.php">Gör din första beställning</a>
      </div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Nr</th><th>Skickad</th><th>Leveransdag</th><th>Varugrupper</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td data-label="Nr"><a class="row-link" href="order.php?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
              <td data-label="Skickad" class="muted"><?php echo e(datum($o['created_at'])); ?></td>
              <td data-label="Leveransdag"><?php echo e(($leveransdagar[$o['leveransdag']] ?? $o['leveransdag']) . ($o['leveransdatum'] ? ' ' . date('j/n', strtotime($o['leveransdatum'])) : '')); ?></td>
              <td data-label="Varugrupper" class="muted"><?php echo e($o['etikett'] ?? kategori_namn($o['kategorier'])); ?></td>
              <td data-label="Status"><?php echo status_pill($o['status']); ?></td>
              <td class="actions"><?php if ($o['etikett'] === null): ?><a class="btn ghost small" href="bestall.php?igen=<?php echo (int)$o['id']; ?>">Beställ igen</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
