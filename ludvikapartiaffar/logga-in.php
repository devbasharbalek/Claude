<?php
require __DIR__ . '/includes/app.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if ($u = current_user()) redirect($u['role'] === 'admin' && $next === 'konto.php' ? 'admin.php' : $next);

$fel = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = mb_strtolower(enRad($_POST['email'] ?? '', 160));
  $pw = (string)($_POST['password'] ?? '');

  if (login_throttled($email)) {
    $fel = 'För många misslyckade försök. Vänta 15 minuter och försök igen, eller återställ lösenordet.';
  } else {
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    // Verifies against a dummy hash when the account is missing so response time doesn't reveal which emails exist.
    $ok = password_verify($pw, $u['password_hash'] ?? '$2y$12$smY7gHvDxA2b5pALlgTCnO6I71BpDnalEpB3ONfR3tSkPCajqU6Yi');

    if (!$u || !$ok) {
      record_failed_login($email);
      $fel = 'Fel e-post eller lösenord.';
    } elseif (!(int)$u['active']) {
      $fel = 'Kontot är avstängt. Ring oss på 0240-183 55 om du tror att det är fel.';
    } else {
      if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $u['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$u['password_hash'], $u['id']]);
      }
      clear_failed_logins($email);
      login_user($u);
      redirect($u['role'] === 'admin' && $next === 'konto.php' ? 'admin.php' : $next);
    }
  }
}

$active = 'login';
$pageTitle = 'Logga in – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap narrow">
    <form class="form-card" method="post" action="logga-in.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="next" value="<?php echo e($next); ?>">
      <h1 class="card-title">Logga in</h1>
      <p class="lead">Se dina beställningar och beställ igen med ett klick.</p>
      <?php if ($fel): ?><div class="alert error" role="alert" style="margin-top:18px;"><?php echo e($fel); ?></div><?php endif; ?>
      <div class="field-grid" style="margin-top:20px; grid-template-columns:1fr;">
        <div class="field">
          <label for="email">E-post</label>
          <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="username" required autofocus>
        </div>
        <div class="field">
          <label for="password">Lösenord</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
      </div>
      <div class="form-actions"><button class="btn primary" type="submit">Logga in</button></div>
      <p class="form-foot"><a href="glomt-losenord.php">Glömt lösenordet?</a><br>Inget konto? <a href="registrera.php">Skapa ett här</a></p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
