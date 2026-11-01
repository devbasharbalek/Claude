<?php
require __DIR__ . '/includes/app.php';
veckansklipp_schema();

$id = (int)($_GET['id'] ?? 0);
$vk = $id ? vk_hamta($id) : null;
$vk ??= vk_senaste();

$active = 'veckansklipp';
$pageTitle = ($vk ? vk_etikett($vk) : 'Veckansklipp') . ' – Ludvika Partiaffär';
if ($vk) {
  $metaBeskrivning = 'Veckans erbjudanden för restauranger och pizzerior från Ludvika Partiaffär. Gäller ' . vk_period($vk) . '.';
  // Bilden visas som förhandsvisning när länken skickas i sms.
  if ((int)$vk['sidor'] > 0) $ogBild = rtrim($CONFIG['site_url'], '/') . '/' . vk_url($vk, 1);
}
require __DIR__ . '/includes/head.php';
$sidor = $vk ? (int)$vk['sidor'] : 0;
?>
<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Veckans erbjudanden</span>
    <h1><?php echo $vk ? 'Veckansklipp v.' . (int)$vk['vecka'] : 'Veckansklipp'; ?></h1>
    <p>Veckans bästa priser för restauranger och pizzerior. Priserna är exkl. moms och pant.</p>
    <?php if ($vk): ?>
      <div class="sk-deadline"><small>Gäller</small><?php echo e(vk_period($vk)); ?></div>
      <div class="hero-actions">
        <a class="btn accent" href="bestall.php">Beställ</a>
        <a class="btn outline" href="tel:+4624018355">Ring 0240-183 55</a>
        <?php if ((int)$vk['pdf_storlek'] > 0): ?>
          <a class="btn outline" href="<?php echo e(vk_url($vk, 'pdf', true)); ?>">Ladda ner PDF</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="wrap">
    <?php if (!$vk): ?>
      <div class="empty">
        <p>Veckansklippet är inte ute just nu. Ett nytt blad kommer varje söndag.</p>
        <a class="btn primary" href="bestall.php">Till beställning</a>
      </div>
    <?php else: ?>
      <?php if (vk_har_passerat($vk)): ?>
        <div class="alert warn">Det här bladet gällde vecka <?php echo (int)$vk['vecka']; ?>. Nästa veckansklipp kommer på söndag.</div>
      <?php endif; ?>

      <?php if ($sidor > 0): ?>
        <div class="vk-sidor">
          <?php for ($i = 1; $i <= $sidor; $i++): ?>
            <img src="<?php echo e(vk_url($vk, $i)); ?>" width="<?php echo (int)$vk['bild_b']; ?>" height="<?php echo (int)$vk['bild_h']; ?>"
                 alt="<?php echo e(vk_etikett($vk) . ', sida ' . $i . ' av ' . $sidor); ?>" loading="<?php echo $i === 1 ? 'eager' : 'lazy'; ?>" decoding="async">
          <?php endfor; ?>
        </div>
      <?php else: ?>
        <object class="vk-pdf" data="<?php echo e(vk_url($vk, 'pdf')); ?>" type="application/pdf">
          <div class="empty"><p>Bladet kan inte visas här.</p><a class="btn primary" href="<?php echo e(vk_url($vk, 'pdf')); ?>">Öppna veckansklippet som PDF</a></div>
        </object>
      <?php endif; ?>

      <div class="cta-band" style="margin-top:32px;">
        <div>
          <h2>Vill du beställa?</h2>
          <p>Skicka en beställning via formuläret eller ring oss under öppettiderna, mån&ndash;fre 06:00&ndash;14:30.</p>
        </div>
        <div class="actions">
          <a class="btn accent" href="bestall.php">Till beställningen</a>
          <a class="btn outline" href="tel:+4624018355">0240-183 55</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
