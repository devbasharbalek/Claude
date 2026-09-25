<?php
require __DIR__ . '/includes/app.php';

$skickat = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = mb_strtolower(enRad($_POST['email'] ?? '', 160));

  if (!looks_like_bot() && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $st = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u) {
      $st = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?');
      $st->execute([$u['id'], date('Y-m-d H:i:s', time() - 3600)]);
      if ((int)$st->fetchColumn() < 3) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO password_resets (user_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$u['id'], hash('sha256', $token), now(), date('Y-m-d H:i:s', time() + 3600)]);
        send_mail($u['email'], 'Återställ ditt lösenord', implode("\n", [
          "Hej {$u['kontakt']}!",
          '',
          'Någon (förhoppningsvis du) har bett om att återställa lösenordet till ditt konto hos Ludvika Partiaffär.',
          'Klicka på länken nedan för att välja ett nytt lösenord. Länken gäller i en timme.',
          '',
          $CONFIG['site_url'] . '/aterstall-losenord.php?token=' . $token,
          '',
          'Har du inte bett om det här kan du bortse från mejlet, lösenordet ändras inte.',
          '',
          'Ludvika Partiaffär, 0240-183 55',
        ]));
      }
    }
  }
  $skickat = true;
}

$active = 'login';
$pageTitle = 'Glömt lösenord – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap narrow">
    <?php if ($skickat): ?>
      <div class="form-card">
        <h1 class="card-title">Kolla din mejl</h1>
        <p class="lead">Om det finns ett konto med adressen <strong><?php echo e($email); ?></strong> har vi skickat en länk för att välja nytt lösenord. Länken gäller i en timme. Hittar du inget mejl, titta i skräpposten.</p>
        <a class="btn ghost" href="logga-in.php" style="margin-top:20px;">Tillbaka till inloggningen</a>
      </div>
    <?php else: ?>
      <form class="form-card" method="post" action="glomt-losenord.php">
        <?php echo csrf_field(); ?>
        <h1 class="card-title">Glömt lösenord</h1>
        <p class="lead">Skriv e-postadressen du loggar in med, så skickar vi en länk för att välja ett nytt lösenord.</p>
        <div class="field" style="margin-top:20px;">
          <label for="email">E-post</label>
          <input type="email" id="email" name="email" autocomplete="username" required autofocus>
        </div>
        <?php echo bot_fields(); ?>
        <div class="form-actions"><button class="btn primary" type="submit">Skicka länk</button></div>
        <p class="form-foot"><a href="logga-in.php">Tillbaka till inloggningen</a></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
