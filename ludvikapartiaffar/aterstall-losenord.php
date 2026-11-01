<?php
require __DIR__ . '/includes/app.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
  $st = db()->prepare('SELECT r.id AS reset_id, u.*
                       FROM password_resets r JOIN users u ON u.id = r.user_id
                       WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.active = 1');
  $st->execute([hash('sha256', $token), now()]);
  $reset = $st->fetch() ?: null;
}

$fel = '';
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $pw  = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');
  if (mb_strlen($pw) < MIN_PASSWORD) $fel = 'Lösenordet måste vara minst ' . MIN_PASSWORD . ' tecken.';
  elseif (strlen($pw) > 200) $fel = 'Lösenordet får vara högst 200 tecken.';
  elseif ($pw !== $pw2) $fel = 'Lösenorden matchar inte.';

  if (!$fel) {
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $reset['id']]);
    db()->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([now(), $reset['id']]);
    clear_failed_logins($reset['email']);
    logout_user();
    flash('Ditt lösenord är ändrat. Logga in med det nya lösenordet.');
    redirect('logga-in.php');
  }
}

$active = 'login';
$pageTitle = 'Nytt lösenord – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap narrow">
    <?php if (!$reset): ?>
      <div class="form-card">
        <h1 class="card-title">Länken fungerar inte</h1>
        <p class="lead">Länken har redan använts eller har gått ut (den gäller i en timme). Beställ en ny länk så fixar vi det.</p>
        <a class="btn primary" href="glomt-losenord.php" style="margin-top:20px;">Skicka ny länk</a>
      </div>
    <?php else: ?>
      <form class="form-card" method="post" action="aterstall-losenord.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo e($token); ?>">
        <h1 class="card-title">Välj nytt lösenord</h1>
        <p class="lead">För kontot <?php echo e($reset['email']); ?>.</p>
        <?php if ($fel): ?><div class="alert error" role="alert" style="margin-top:18px;"><?php echo e($fel); ?></div><?php endif; ?>
        <div class="field-grid" style="margin-top:20px; grid-template-columns:1fr;">
          <div class="field">
            <label for="password">Nytt lösenord</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD; ?>" required autofocus>
            <span class="hint">Minst <?php echo MIN_PASSWORD; ?> tecken.</span>
          </div>
          <div class="field">
            <label for="password2">Upprepa nytt lösenord</label>
            <input type="password" id="password2" name="password2" autocomplete="new-password" required>
          </div>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit">Spara lösenord</button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
