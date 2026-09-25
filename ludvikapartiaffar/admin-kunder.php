<?php
require __DIR__ . '/includes/app.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $kid = (int)($_POST['id'] ?? 0);
  $aktiv = ($_POST['action'] ?? '') === 'aktivera' ? 1 : 0;
  $st = db()->prepare("UPDATE users SET active = ? WHERE id = ? AND role = 'kund'");
  $st->execute([$aktiv, $kid]);
  if ($st->rowCount()) {
    flash($aktiv ? 'Kontot är aktiverat igen.' : 'Kontot är avstängt. Kunden loggas ut direkt och kan inte logga in.');
  }
  redirect('admin-kunder.php' . (($_POST['sok'] ?? '') !== '' ? '?sok=' . rawurlencode((string)$_POST['sok']) : ''));
}

$sok = enRad($_GET['sok'] ?? '', 80);
$params = [];
$where = "u.role = 'kund'";
if ($sok !== '') {
  $where .= ' AND (u.foretag LIKE ? OR u.kontakt LIKE ? OR u.email LIKE ? OR u.telefon LIKE ?)';
  $like = '%' . addcslashes($sok, '%_\\') . '%';
  $params = [$like, $like, $like, $like];
}
$st = db()->prepare("SELECT u.id, u.foretag, u.kontakt, u.email, u.telefon, u.active, u.created_at, u.last_login,
                            COUNT(o.id) AS antal, MAX(o.created_at) AS senaste
                     FROM users u LEFT JOIN orders o ON o.user_id = u.id
                     WHERE $where GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500");
$st->execute($params);
$kunder = $st->fetchAll();

$active = 'admin';
$pageTitle = 'Kunder – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin.php">&larr; Beställningar</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Kunder</h1>
        <p><?php echo count($kunder); ?> <?php echo $sok !== '' ? 'träffar' : 'registrerade konton'; ?>. Stäng av konton som ser misstänkta ut.</p>
      </div>
    </div>

    <form class="search" method="get" action="admin-kunder.php">
      <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Sök företag, namn, e-post eller telefon" aria-label="Sök kunder">
      <button class="btn ghost small" type="submit">Sök</button>
    </form>

    <?php if (!$kunder): ?>
      <div class="empty"><p><?php echo $sok !== '' ? 'Inga kunder matchar sökningen.' : 'Inga kunder har registrerat sig än.'; ?></p></div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Företag</th><th>Kontakt</th><th>Registrerad</th><th>Beställningar</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($kunder as $k): ?>
            <tr>
              <td data-label="Företag"><strong><?php echo e($k['foretag']); ?></strong></td>
              <td data-label="Kontakt"><div><?php echo e($k['kontakt']); ?><br><span class="muted" style="font-size:.85rem;"><?php echo e($k['email']); ?> &middot; <?php echo e($k['telefon']); ?></span></div></td>
              <td data-label="Registrerad" class="muted"><?php echo e(date('Y-m-d', strtotime($k['created_at']))); ?></td>
              <td data-label="Beställningar"><div>
                <?php if ((int)$k['antal']): ?>
                  <a class="row-link" href="admin.php?visa=alla&amp;kund=<?php echo (int)$k['id']; ?>"><?php echo (int)$k['antal']; ?> st</a>
                  <br><span class="muted" style="font-size:.8rem;">senast <?php echo e(date('Y-m-d', strtotime($k['senaste']))); ?></span>
                <?php else: ?>
                  <span class="muted">Inga</span>
                <?php endif; ?>
              </div></td>
              <td data-label="Status"><?php echo (int)$k['active'] ? '<span class="pill pill-levererad">Aktiv</span>' : '<span class="pill pill-off">Avstängd</span>'; ?></td>
              <td class="actions">
                <form method="post" action="admin-kunder.php" style="margin:0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                  <input type="hidden" name="sok" value="<?php echo e($sok); ?>">
                  <?php if ((int)$k['active']): ?>
                    <button class="btn danger small" type="submit" name="action" value="stang" onclick="return confirm('Stänga av kontot? Kunden loggas ut direkt.');">Stäng av</button>
                  <?php else: ?>
                    <button class="btn ghost small" type="submit" name="action" value="aktivera">Aktivera</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
