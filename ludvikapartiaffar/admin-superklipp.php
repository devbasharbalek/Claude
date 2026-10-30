<?php
require __DIR__ . '/includes/app.php';
require_admin();
superklipp_schema();

$listor = db()->query("SELECT s.*,
    (SELECT COUNT(*) FROM superklipp_rader r WHERE r.superklipp_id = s.id) AS varor,
    (SELECT COUNT(*) FROM order_superklipp os JOIN orders o ON o.id = os.order_id
      WHERE os.superklipp_id = s.id AND o.status <> 'avbruten') AS bestallningar
  FROM superklipp s ORDER BY s.ar DESC, s.vecka DESC LIMIT 60")->fetchAll();

$active = 'admin';
$pageTitle = 'Superklipp – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin.php">&larr; Beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Superklipp</h1>
        <p>Lägg in veckans lista från Google Sheets. Inloggade kunder ser priserna och kan beställa fram till sista beställningsdag.</p>
      </div>
      <div class="portal-actions">
        <a class="btn ghost" href="superklipp.php">Visa som kund</a>
      </div>
    </div>

    <div class="detail-grid">
      <form class="detail-card" method="post" action="admin-superklipp-redigera.php" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="import">
        <h2>Nytt superklipp</h2>
        <?php echo sk_import_falt(); ?>
        <div class="form-actions" style="margin-top:18px;">
          <button class="btn primary" type="submit">Läs in listan</button>
          <span class="form-note">Du får se och rätta listan innan något syns för kunderna.</span>
        </div>
      </form>
      <div class="side-card">
        <h3>Så funkar det</h3>
        <ol style="margin:12px 0 0; padding-left:20px; line-height:1.7; color:var(--ink-soft);">
          <li>Gör listan i Google Sheets precis som vanligt.</li>
          <li>Kopiera hela arket och klistra in det här.</li>
          <li>Kontrollera, kryssa i veckans toppklipp och tryck <strong>Spara och publicera</strong>.</li>
          <li>Kopiera den färdiga mejltexten och skicka den till kunderna.</li>
        </ol>
        <p style="margin-top:12px; color:var(--ink-soft); line-height:1.6;">Beställningarna kommer in under <a href="admin.php" style="color:var(--brand);">Beställningar</a> och summeras i en plocklista per vecka.</p>
      </div>
    </div>

    <h2 style="font-size:1.8rem; text-transform:uppercase; margin:34px 0 14px;">Alla superklipp</h2>
    <?php if (!$listor): ?>
      <div class="empty"><p>Inga superklipp än. Klistra in veckans lista ovan för att komma igång.</p></div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Vecka</th><th>Sista beställning</th><th>Status</th><th>Varor</th><th>Beställningar</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($listor as $s): ?>
            <tr>
              <td data-label="Vecka"><a class="row-link" href="admin-superklipp-redigera.php?id=<?php echo (int)$s['id']; ?>">v.<?php echo (int)$s['vecka']; ?> <?php echo (int)$s['ar']; ?></a></td>
              <td data-label="Sista beställning" class="muted"><?php echo e(sk_deadline_text($s['deadline'])); ?></td>
              <td data-label="Status"><?php echo sk_status_pill($s); ?></td>
              <td data-label="Varor"><?php echo (int)$s['varor']; ?></td>
              <td data-label="Beställningar"><?php echo (int)$s['bestallningar']; ?></td>
              <td class="actions"><a class="btn ghost small" href="admin-superklipp-redigera.php?kopiera=<?php echo (int)$s['id']; ?>">Kopiera till ny vecka</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
